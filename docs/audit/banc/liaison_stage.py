#!/usr/bin/env python3
"""Liaison entre mod_stage et mod_ep, de bout en bout, par les pages web des deux modules.

Usage : python3 liaison_stage.py site-x.json liaison.json > liaison-resultats.json
(liaison.json : sortie de « sudo -u www-data php liaison_stage_prep.php site-x.json » ; pour
rejouer, « liaison_stage_prep.php site-x.json reset » puis de nouveau la préparation).

1. La DEVE fixe le barème du type stage (0,5 ECTS par jour) dans le formulaire du type.
2. Elle valide en masse, dans la page DEVE de « Gestion des stages », 200 stages complémentaires
   (A) et 50 obligatoires (B) ; 50 complémentaires (C) restent à valider.
3. Un étudiant ouvre son tableau de bord : les crédits de stage de toute la promotion sont
   calculés à ce moment, ni avant ni après.
4. Corrections : 20 durées retenues revalidées dans la page DEVE de mod_stage, 20 corrigées dans
   la saisie (sans événement), 10 stages annulés, 10 repassés en obligatoire.
5. Validation d'un stage C puis, aussitôt, validation des années et émission du supplément.
6. Changement d'enseignant référent dans mod_stage : la déclaration en attente change de
   validateur côté EP.
7. Tâche de la nuit : plus rien à faire. Durée de l'empreinte des stages.
"""
import html
import json
import re
import subprocess
import sys
import time
from html.parser import HTMLParser

import requests

SITE = json.load(open(sys.argv[1]))
PREP = json.load(open(sys.argv[2]))
BASE, CM, EPID, PW = SITE['wwwroot'], SITE['cmid'], SITE['epid'], SITE['password']
SCM = PREP['stagecmid']
MOODLE = '/home/user/moodle-stress/moodle'
RATE = 0.5
checks, timings = {}, {}


class FormParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.forms, self.form, self.select, self.textarea = [], None, None, None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.form = {'action': html.unescape(a.get('action', '')), 'fields': []}
            self.forms.append(self.form)
        elif self.form is None:
            return
        elif tag == 'input' and a.get('name'):
            kind = a.get('type', 'text')
            if (kind in ('checkbox', 'radio') and 'checked' not in a) or kind in ('submit', 'button', 'file'):
                return
            self.form['fields'].append([a['name'], a.get('value', '')])
        elif tag == 'select' and a.get('name'):
            self.select = [a['name'], None, None, 'multiple' in a]
        elif tag == 'option' and self.select is not None:
            if self.select[2] is None:
                self.select[2] = a.get('value', '')
            if 'selected' in a:
                self.select[1] = a.get('value', '')
        elif tag == 'textarea' and a.get('name'):
            self.textarea = [a['name'], '']

    def handle_data(self, data):
        if self.textarea is not None:
            self.textarea[1] += data

    def handle_endtag(self, tag):
        if tag == 'select' and self.select is not None and self.form is not None:
            name, chosen, first, multiple = self.select
            if chosen is not None or not multiple:
                self.form['fields'].append([name, chosen if chosen is not None else (first or '')])
            self.select = None
        elif tag == 'textarea' and self.textarea is not None and self.form is not None:
            self.form['fields'].append(self.textarea)
            self.textarea = None
        elif tag == 'form':
            self.form = None


def form_with(text, field):
    p = FormParser()
    p.feed(text)
    return next((f for f in p.forms if any(n == field for n, _ in f['fields'])), None)


class User:
    def __init__(self, username):
        self.s = requests.Session()
        r = self.s.get(BASE + '/login/index.php')
        token = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
        r = self.s.post(BASE + '/login/index.php', data={'username': username, 'password': PW, 'logintoken': token})
        self.sesskey = re.search(r'"sesskey":"([^"]+)"', r.text).group(1)

    def get(self, url):
        r = self.s.get(BASE + url if url.startswith('/') else url, timeout=300)
        if r.status_code >= 500 or re.search(r'Error code: |Exception - ', r.text):
            raise RuntimeError(f'{url} : HTTP {r.status_code}')
        return r

    def post(self, url, data):
        r = self.s.post(BASE + url if url.startswith('/') else url, data=data, timeout=300)
        if r.status_code >= 500 or re.search(r'Error code: |Exception - ', r.text):
            raise RuntimeError(f'{url} : HTTP {r.status_code} {re.findall(r"Error code: [a-z_]+", r.text)}')
        return r

    def submit(self, form, overrides, drop=()):
        fields = [(n, v) for n, v in form['fields'] if n not in overrides and n not in drop]
        return self.post(form['action'], fields + list(overrides.items()))


def sql(query):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', query], capture_output=True,
                          text=True).stdout.strip()


def prep(*args):
    return subprocess.run(['sudo', '-u', 'www-data', 'php', 'liaison_stage_prep.php', sys.argv[1], *args],
                          capture_output=True, text=True).stdout.strip()


def stage_credits():
    """sourceref => retainedects des crédits de type stage de l'instance."""
    rows = sql(f"select sourceref, retainedects from mdl_ep_credit where epid={EPID} and source='stage'").splitlines()
    return {int(r.split('|')[0]): float(r.split('|')[1]) for r in rows if r}


def expect(name, ok, detail=''):
    checks[name] = {'ok': bool(ok), 'detail': detail}
    print(f"{'OK ' if ok else 'KO '} {name} {detail}", file=sys.stderr, flush=True)


A, B, C = PREP['A'], PREP['B'], PREP['C']
deve = User('devea_1')
student = User(A[0]['username'])

# 1. Barème du type stage, par le formulaire du type.
typeid = sql(f"select id from mdl_ep_type where epid={EPID} and code='stage'")
r = deve.get(f'/mod/ep/type_edit.php?id={CM}&typeid={typeid}')
deve.submit(form_with(r.text, 'ectsperday'), {'ectsperday': str(RATE), 'submitbutton': 'Enregistrer'})
expect('bareme_enregistre', float(sql(f"select ectsperday from mdl_ep_type where id={typeid}")) == RATE)
expect('aucun_credit_avant_validation', not stage_credits())

# 2. Validation en masse dans la page DEVE de mod_stage.
ids = [e['entry'] for e in A + B]
t = time.time()
deve.post(f'/mod/stage/deve.php?id={SCM}', [('sesskey', deve.sesskey), ('bulkvalidate', 1)] + [('selected[]', i) for i in ids])
timings['validation_en_masse_250_s'] = round(time.time() - t, 2)
expect('stages_valides_dans_mod_stage', sql(f"select count(*) from mdl_stage_entry where id in ({','.join(map(str, ids))}) and status=3") == '250')

# 3. Premier tableau de bord ouvert : calcul de toute la promotion.
t = time.time()
student.get(f'/mod/ep/view.php?id={CM}')
timings['tableau_de_bord_avec_calcul_s'] = round(time.time() - t, 2)
t = time.time()
r = student.get(f'/mod/ep/view.php?id={CM}')
timings['tableau_de_bord_suivant_s'] = round(time.time() - t, 2)
credits = stage_credits()
expect('200_credits_de_stage', len(credits) == 200 and all(credits.get(e['entry']) == e['days'] * RATE for e in A),
       f'{len(credits)} crédits')
expect('obligatoires_et_non_valides_sans_credit', not any(e['entry'] in credits for e in B + C))
expect('credit_visible_par_l_etudiant', PREP['theme'] in r.text)

# 4. Corrections.
for e in A[1:21]:                 # Revalidation avec une autre durée, page DEVE de mod_stage.
    r = deve.get(f'/mod/stage/deve.php?id={SCM}&entryid={e["entry"]}')
    f = form_with(r.text, 'retainedduration')
    deve.submit(f, {'retainedduration': str(e['days'] + 3), 'validatestage': 'Valider'}, drop=('rejectstage',))
    e['days'] += 3
for e in A[21:41]:                # Correction dans la saisie, sans événement.
    prep('details', str(e['entry']), str(e['days'] - 2))
    e['days'] -= 2
for e in A[41:51]:                # Annulation par la DEVE.
    r = deve.get(f'/mod/stage/cancel_entry.php?id={SCM}&entryid={e["entry"]}')
    f = form_with(r.text, 'cancelcomment')
    deve.submit(f, {'cancelcomment': 'Stage interrompu.', 'submitbutton': 'Annuler le stage'})
for e in A[51:61]:                # Stage repassé en obligatoire.
    prep('type', str(e['entry']), 'obligatoire')
student.get(f'/mod/ep/view.php?id={CM}')
credits = stage_credits()
kept = A[:41] + A[61:]
expect('corrections_suivies', all(credits.get(e['entry']) == e['days'] * RATE for e in kept), f'{len(credits)} crédits')
expect('annules_et_obligatoires_retires', not any(e['entry'] in credits for e in A[41:61]))
expect('annulations_dans_mod_stage', sql(f"select count(*) from mdl_stage_entry where id in ({','.join(str(e['entry']) for e in A[41:51])}) and status=-2") == '10')

# 5. Validation d'un stage puis, aussitôt, années et supplément : aucune page EP entre les deux.
c0, c1 = C[0], C[1]
deve.post(f'/mod/stage/deve.php?id={SCM}', [('sesskey', deve.sesskey), ('bulkvalidate', 1), ('selected[]', c0['entry']),
                                           ('selected[]', c1['entry'])])
sql(f"delete from mdl_ep_year_validation where epid={EPID} and userid={c0['userid']}")
deve.post(f'/mod/ep/year_validation.php?id={CM}', {'sesskey': deve.sesskey, 'action': 'decide', 'userid': c0['userid'],
                                                   'studyyear': 3, 'decision': 1, 'comment': ''})
retained = sql(f"select retainedects from mdl_ep_year_validation where epid={EPID} and userid={c0['userid']} and studyyear=3")
expect('stage_compte_dans_la_decision_d_annee', c0['entry'] in stage_credits(), f'ECTS retenus enregistrés : {retained}')
deve.post(f'/mod/ep/supplement_issue.php?id={CM}', {'sesskey': deve.sesskey, 'action': 'issue', 'userid': c1['userid'],
                                                    'lang': 'fr', 'force': 1})
issue = sql(f"select id from mdl_ep_supplement_issue where epid={EPID} and userid={c1['userid']} and replacedby is null")
pdftext = ''
if issue:
    path = sql(f"select contenthash from mdl_files where component='mod_ep' and filearea='supplementissued' and itemid={issue} "
               "and filename<>'.'")
    blob = f"/home/user/moodle-stress/moodledata/filedir/{path[:2]}/{path[2:4]}/{path}"
    pdftext = subprocess.run(['sudo', 'pdftotext', blob, '-'], capture_output=True, text=True).stdout
# Le PDF coupe les libellés sur plusieurs lignes : on cherche le début du libellé du crédit.
expect('stage_au_supplement_emis_aussitot', 'Stage complémentaire — Faune sauvage' in pdftext)

# 6. Changement d'enseignant référent dans mod_stage.
old, new = 'ensa_1', 'ensa_150'
pending = sql(f"select c.id from mdl_ep_credit c join mdl_user u on u.id=c.userid where c.epid={EPID} and u.username='etux_1' "
              "and c.activityid is null and c.status=0 limit 1")
etu1 = sql("select id from mdl_user where username='etux_1'")
newid = sql(f"select id from mdl_user where username='{new}'")
deve.post(f'/mod/stage/teachers.php?id={SCM}', [('sesskey', deve.sesskey), ('rowstudentids[]', etu1),
                                                (f'teachers_{etu1}[]', newid)])
see = lambda who: f'creditid={pending}' in User(who).get(f'/mod/ep/credits.php?id={CM}').text
expect('referent_change_dans_mod_stage', sql(f"select count(*) from mdl_stage_entry_teacher where studentid={etu1} "
                                             f"and teacherid={newid}") == '1')
expect('demande_suit_le_nouveau_referent', pending and see(new) and not see(old), f'demande {pending}')

# 7. Tâche de la nuit et coût de l'empreinte.
out = subprocess.run(['sudo', '-u', 'www-data', 'php', 'admin/cli/scheduled_task.php',
                      '--execute=\\mod_ep\\task\\sync_stage_credits'], cwd=MOODLE, capture_output=True, text=True).stdout
expect('tache_de_nuit_sans_rien_a_faire', stage_credits() == credits | {c0['entry']: c0['days'] * RATE, c1['entry']: c1['days'] * RATE},
       out.strip().splitlines()[-1] if out.strip() else '')
timings.update(json.loads(prep('signature')))

json.dump({'controles': checks, 'durees': timings}, sys.stdout, indent=1, ensure_ascii=False)
print(f"\n{sum(c['ok'] for c in checks.values())}/{len(checks)} contrôles réussis ; durées : {timings}", file=sys.stderr)
