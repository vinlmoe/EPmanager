#!/usr/bin/env python3
"""Interactions de bout en bout d'une promotion de 1 000 étudiants, par l'interface web.

Usage : python3 interactions.py site-u.json <synthese_cmid> > interactions.json

Variables : PHASES (liste parmi etudiants, responsables, referents, deve, controles ; toutes par
défaut), DEBUT et LIMITE (rang du premier étudiant et nombre d'étudiants de la phase 1),
REFERENTS (plage des enseignants référents de la phase 3, 1-150 par défaut). Par
exemple, une seconde vague de 60 étudiants laissée en attente, pour les captures du guide :
PHASES=etudiants DEBUT=1 LIMITE=60 python3 interactions.py site-w.json 31

Promotion vierge (charge_setup.php avec VIERGE=1). Toutes les données naissent des formulaires,
envoyés comme par un navigateur (champs cachés, clé de session, dépôts de fichiers dans la zone
brouillon par le sélecteur de fichiers de Moodle). Phases, chacune en parallèle :

1. Étudiants : tableau de bord, catalogue, trois inscriptions directes, une déclaration avec
   justificatif PDF, un élément du supplément avec preuve PDF ; un sur dix retire sa déclaration
   (première demande en attente de son tableau de bord), un sur dix la modifie.
2. Responsables d'EP : notes ou validations en lot dans la liste des participants ; un EP par
   import CSV (prévisualisation puis confirmation).
3. Référents : décision sur chaque déclaration de leurs étudiants (9 sur 10 validées), puis
   sur leurs éléments du supplément depuis la page « À traiter » de la synthèse.
4. DEVE : paramétrage du supplément (textes des rubriques, signataire), décision finale sur
   les éléments du supplément, validation des années en lot,
   émission groupée des 1 000 suppléments (tâche de fond), vérification publique d'un
   échantillon, exports CSV et complet.
5. Contrôles en base : chaque compteur attendu, historique des décisions, unicité.

Le résumé lisible est écrit sur la sortie d'erreur, le détail JSON sur la sortie standard.
"""
import html
import json
import os
import random
import re
import statistics
import subprocess
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser

import requests

SITE = json.load(open(sys.argv[1]))
SYN = int(sys.argv[2])
BASE, CM, T, PW = SITE['wwwroot'], SITE['cmid'], SITE['tag'], SITE['password']
EPID = SITE['epid']
MOODLE = '/home/user/moodle-stress/moodle'
WORKERS = 40
PDF = b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
ERRORS = [r'Fatal error[^<]*', r'Exception - [^<]*', r'Coding error detected[^<]*', r'<b>Warning</b>:[^<]*',
          r'Error code: [a-z_]+', r'class="errormessage"[^>]*>[^<]*', r'debuggingmessage[^>]*>[^<]*']
PHASES = set((os.environ.get('PHASES') or 'etudiants,responsables,referents,deve,controles').split(','))
random.seed(20261108)
report = {'phases': [], 'anomalies': []}
alock = threading.Lock()


def anomaly(text):
    with alock:
        if len(report['anomalies']) < 200:
            report['anomalies'].append(text[:300])


class FormParser(HTMLParser):
    """Formulaires d'une page : action, méthode et valeurs par défaut de chaque champ."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.forms, self.form, self.select, self.textarea = [], None, None, None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.form = {'action': html.unescape(a.get('action', '')), 'method': a.get('method', 'get').lower(),
                         'fields': [], 'id': a.get('id', '')}
            self.forms.append(self.form)
        elif self.form is None:
            return
        elif tag == 'input' and a.get('name'):
            kind = a.get('type', 'text')
            if kind in ('checkbox', 'radio') and 'checked' not in a:
                return
            if kind in ('submit', 'button', 'image', 'file'):
                return
            self.form['fields'].append([a['name'], a.get('value', '')])
        elif tag == 'select' and a.get('name'):
            self.select = [a['name'], None, None]
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
            self.form['fields'].append([self.select[0], self.select[1] if self.select[1] is not None else (self.select[2] or '')])
            self.select = None
        elif tag == 'textarea' and self.textarea is not None and self.form is not None:
            self.form['fields'].append(self.textarea)
            self.textarea = None
        elif tag == 'form':
            self.form = None


def forms_of(text):
    p = FormParser()
    p.feed(text)
    return p.forms


def form_with(text, field):
    for f in forms_of(text):
        if any(n == field for n, _ in f['fields']):
            return f
    return None


class User:
    def __init__(self, username):
        self.username = username
        self.s = requests.Session()
        self.sesskey = None
        self.requests = 0

    def login(self):
        r = self.get('/login/index.php')
        token = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
        r = self.post('/login/index.php', {'username': self.username, 'password': PW, 'logintoken': token})
        m = re.search(r'"sesskey":"([^"]+)"', r.text)
        if m is None or '/login/index.php' in r.url:
            reason = re.search(r'(Error code: [a-z_]+|id="loginerrormessage"[^>]*>[^<]*|alert-danger[^>]*>[^<]*)', r.text)
            raise RuntimeError(f'connexion de {self.username} refusée : HTTP {r.status_code} {r.url} '
                               f'{reason.group(0) if reason else r.text[:200]!r}')
        self.sesskey = m.group(1)
        self.userctx = re.search(r'"contextid":(\d+)', r.text)
        return self

    def check(self, r, what):
        if r.status_code >= 500:
            anomaly(f'{self.username} {what}: HTTP {r.status_code}')
        elif 'text/html' in r.headers.get('Content-Type', ''):
            for pattern in ERRORS:
                m = re.search(pattern, r.text)
                if m:
                    anomaly(f'{self.username} {what}: {re.sub(chr(10), " ", m.group(0))}')
                    break
        return r

    def get(self, url, what=''):
        self.requests += 1
        return self.check(self.s.get(BASE + url if url.startswith('/') else url, timeout=300), what or url)

    def post(self, url, data, what='', files=None):
        self.requests += 1
        return self.check(self.s.post(BASE + url if url.startswith('/') else url, data=data, files=files, timeout=300),
                          what or url)

    def submit(self, form, overrides, what, drop=()):
        fields = [(n, v) for n, v in form['fields'] if n not in overrides and n not in drop]
        fields += list(overrides.items())
        return self.post(form['action'], fields, what)

    def upload(self, draftid, ctxid, filename):
        r = self.s.post(BASE + '/repository/repository_ajax.php?action=upload', timeout=120, data={
            'sesskey': self.sesskey, 'repo_id': 5, 'itemid': draftid, 'ctx_id': ctxid, 'savepath': '/',
            'title': filename, 'env': 'filemanager', 'author': self.username, 'license': 'allrightsreserved',
        }, files={'repo_upload_file': (filename, PDF, 'application/pdf')})
        self.requests += 1
        if '"url"' not in r.text and '"id"' not in r.text:
            anomaly(f'{self.username} dépôt {filename}: {r.text[:200]}')


def phase(name, func, items):
    """Exécute func sur chaque élément en parallèle, chronomètre, compte les requêtes."""
    t = time.time()
    durations = []
    counter = {'requests': 0}

    def run(item):
        t0 = time.time()
        try:
            n = func(item)
        except Exception as e:  # Toute exception est une anomalie du test, notée et comptée.
            anomaly(f'{name}: {type(e).__name__} {e}')
            n = 0
        with alock:
            durations.append(time.time() - t0)
            counter['requests'] += n or 0

    with ThreadPoolExecutor(WORKERS) as pool:
        list(pool.map(run, items))
    total = time.time() - t
    entry = {'phase': name, 'acteurs': len(items), 'duree_s': round(total, 1), 'requetes': counter['requests'],
             'requetes_par_s': round(counter['requests'] / total, 1) if total else None,
             'mediane_acteur_s': round(statistics.median(durations), 2) if durations else None}
    report['phases'].append(entry)
    print(f"{name:52s} {len(items):5d} acteurs {entry['duree_s']:7.1f} s {entry['requetes']:6d} requêtes "
          f"({entry['requetes_par_s']} /s)  anomalies cumulées={len(report['anomalies'])}", file=sys.stderr, flush=True)


def php(code):
    out = subprocess.run(['sudo', '-u', 'www-data', 'php', '-r',
                          f"define('CLI_SCRIPT', true); require '{MOODLE}/config.php'; {code}"],
                         capture_output=True, text=True)
    return out.stdout.strip()


def sql(query):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', query], capture_output=True,
                          text=True).stdout.strip()


expected = {'withdrawn': 0, 'edited': 0}
students = SITE['students']
referent_of = {sid: f'ensa_{1 + k % 150}' for k, sid in enumerate(students)}


# 1. Étudiants.
def student_journey(k):
    u = User(f'etu{T}_{k}').login()
    u.get(f'/mod/ep/view.php?id={CM}', 'tableau de bord')
    page = random.randint(0, 1)
    r = u.get(f'/mod/ep/catalog.php?id={CM}&page={page}', 'catalogue')
    registered = 0
    # Un seul formulaire d'inscription par page, un bouton « S'inscrire » (activityid) par EP.
    ids = re.findall(r'<button[^>]*name="activityid" value="(\d+)"', r.text)
    for activityid in random.sample(ids, min(3, len(ids))):
        r = u.post(f'/mod/ep/catalog.php?id={CM}', {'sesskey': u.sesskey, 'action': 'register', 'activityid': activityid,
                                                    'studyyear': '3', 'fromlist': 1, 'page': page, 'search': ''},
                   'inscription directe')
        registered += 'alert-success' in r.text
    # Déclaration avec justificatif.
    r = u.get(f'/mod/ep/declare.php?id={CM}', 'formulaire de déclaration')
    f = form_with(r.text, 'claimedects')
    types = re.findall(r'<option value="(\d+)"[^>]*>([^<]*)</option>', r.text.split('name="typeid"')[1].split('</select>')[0])
    draft = dict(f['fields']).get('evidence')
    ctxid = re.search(r'"contextid":(\d+)', r.text).group(1)
    u.upload(draft, ctxid, f'attestation-{k}.pdf')
    typeid = random.choice(types)[0]
    r = u.submit(f, {'typeid': typeid, 'name': f'Activité déclarée par {k}', 'studyyear': '3', 'claimedects': '1',
                     'description': 'Engagement au sein d\'une association, 40 heures.', 'submitbutton': 'Envoyer'},
                 'envoi de la déclaration')
    if 'alert-success' not in r.text and 'alert-info' not in r.text:
        anomaly(f'{u.username} déclaration non confirmée')
    # Élément du supplément avec preuve.
    r = u.get(f'/mod/ep/supplement_item.php?id={CM}', 'formulaire d\'élément')
    f = form_with(r.text, 'title')
    if f is None:
        anomaly(f'{u.username}: pas de formulaire d\'élément')
    else:
        draft = dict(f['fields']).get('proofs')
        if draft:
            u.upload(draft, ctxid, f'preuve-{k}.pdf')
        r = u.submit(f, {'title': f'Responsabilité associative {k}', 'titleen': f'Student association role {k}',
                         'description': 'Trésorier du bureau des élèves.', 'periodstart[enabled]': '1',
                         'periodstart[day]': '1', 'periodstart[month]': '9', 'periodstart[year]': '2025',
                         'periodend[enabled]': '1', 'periodend[day]': '30', 'periodend[month]': '6',
                         'periodend[year]': '2026', 'submitbutton': 'Envoyer'}, 'envoi de l\'élément')
    # Retrait de la déclaration (un sur dix), modification de la déclaration (un sur dix).
    if k % 10 == 0 or k % 10 == 5:
        r = u.get(f'/mod/ep/view.php?id={CM}', 'tableau de bord')
        if k % 10 == 0:
            m = re.search(r'href="([^"]*declare\.php[^"]*action=cancel[^"]*)"', r.text)
            if m:
                link = html.unescape(m.group(1))
                r2 = u.get(link, 'retrait')
                if 'alert-success' in r2.text:
                    with alock:
                        expected['withdrawn'] += 1
        else:
            m = re.search(r'href="([^"]*declare\.php\?id=\d+&amp;creditid=\d+)"', r.text)
            if m:
                r2 = u.get(html.unescape(m.group(1)), 'modification')
                f = form_with(r2.text, 'claimedects')
                if f:
                    r3 = u.submit(f, {'name': f'Activité déclarée par {k} (complétée)', 'submitbutton': 'Enregistrer'},
                                  'enregistrement de la modification')
                    if 'alert-success' in r3.text:
                        with alock:
                            expected['edited'] += 1
    with alock:
        expected['registrations'] = expected.get('registrations', 0) + registered
    return u.requests


def first_student_index():
    return int(re.search(r'_(\d+)$', sql(f"select username from mdl_user where id={students[0]}")).group(1))


first = first_student_index() + int(os.environ.get('DEBUT', 1)) - 1
count = int(os.environ.get('LIMITE', len(students)))
if 'etudiants' in PHASES:
    phase('1. étudiants : inscriptions, déclarations, éléments', student_journey, list(range(first, first + count)))


# 2. Responsables d'EP : décisions en lot dans la liste des participants.
owners = sql(f"""select a.id || ':' || u.username from mdl_ep_activity a join mdl_ep_activity_teacher t on t.activityid = a.id
                join mdl_user u on u.id = t.teacherid where a.epid = {EPID} and exists (select 1 from mdl_ep_credit c
                where c.activityid = a.id and c.status = 0) order by a.id, u.username""").splitlines()
byactivity = {}
for line in owners:
    activityid, username = line.split(':')
    byactivity.setdefault(activityid, username)
csvactivity = sorted(byactivity)[0] if byactivity else None


def owner_decides(item):
    activityid, username = item
    u = User(username).login()
    url = f'/mod/ep/participants.php?id={CM}&activityid={activityid}'
    r = u.get(url, 'participants')
    if activityid == csvactivity:
        # Import CSV des notes : prévisualisation puis confirmation.
        emails = re.findall(r'([a-z0-9_.]+@example\.com)', r.text)
        rows = ['Email;Note;Décision;Commentaire'] + [f'{e};14;validé;Import CSV' for e in sorted(set(emails))]
        r = u.get(f'/mod/ep/grades_import.php?id={CM}&activityid={activityid}', 'import des notes')
        f = form_with(r.text, 'csvfile')
        draft = dict(f['fields']).get('csvfile')
        ctxid = re.search(r'"contextid":(\d+)', r.text).group(1)
        u.s.post(BASE + '/repository/repository_ajax.php?action=upload', timeout=120, data={
            'sesskey': u.sesskey, 'repo_id': 5, 'itemid': draft, 'ctx_id': ctxid, 'savepath': '/', 'title': 'notes.csv',
            'env': 'filepicker'}, files={'repo_upload_file': ('notes.csv', '\n'.join(rows).encode(), 'text/csv')})
        r = u.submit(f, {'submitbutton': 'Prévisualiser'}, 'prévisualisation de l\'import')
        f = form_with(r.text, 'action')
        if f:
            r = u.submit(f, {}, 'confirmation de l\'import')
        return u.requests
    f = form_with(r.text, 'action')
    forms = [x for x in forms_of(r.text) if dict(x['fields']).get('action') == 'decide']
    if not forms:
        return u.requests
    f = forms[0]
    overrides = {}
    for name, _ in f['fields']:
        m = re.match(r'(grade|decision)\[(\d+)\]', name)
        if m:
            overrides[name] = str(random.choice([8, 12, 15, 17])) if m.group(1) == 'grade' else \
                ('validate' if random.random() < 0.9 else 'reject')
    for name in re.findall(r'name="(decision\[\d+\])"', r.text):
        overrides.setdefault(name, 'validate' if random.random() < 0.9 else 'reject')
    for name in re.findall(r'name="(grade\[\d+\])"', r.text):
        overrides.setdefault(name, str(random.choice([8, 12, 15, 17])))
    u.submit(f, overrides, 'décisions en lot')
    return u.requests


if 'responsables' in PHASES:
    phase('2. responsables : notes et validations en lot', owner_decides, sorted(byactivity.items()))


# 3. Référents : déclarations, puis éléments du supplément depuis la synthèse.
def referent_decides(n):
    u = User(f'ensa_{n}').login()
    seen = set()
    for _ in range(20):
        r = u.get(f'/mod/ep/credits.php?id={CM}', 'validation')
        links = {}
        for link in re.findall(r'href="([^"]*validate\.php\?id=\d+&amp;creditid=\d+[^"]*)"[^>]*>Valider<', r.text):
            creditid = re.search(r'creditid=(\d+)', link).group(1)
            if creditid not in seen:
                links.setdefault(creditid, html.unescape(link))
        if not links:
            break
        seen.update(links)
        for link in links.values():
            r = u.get(link, 'demande')
            f = form_with(r.text, 'retainedects')
            if not f:
                continue
            if random.random() < 0.9:
                u.submit(f, {'validatecredit': 'Valider'}, 'validation', drop=('rejectcredit',))
            else:
                u.submit(f, {'rejectcredit': 'Refuser', 'validatorcomment': 'Justificatif illisible.'}, 'refus',
                         drop=('validatecredit',))
    for _ in range(20):
        r = u.get(f'/mod/epsynthesis/todo.php?id={SYN}', 'synthèse à traiter')
        forms = [f for f in forms_of(r.text) if any(n == 'itemid' for n, _ in f['fields'])
                 and dict(f['fields'])['itemid'] not in seen]
        if not forms:
            break
        seen.update(dict(f['fields'])['itemid'] for f in forms)
        for f in forms:
            u.submit(f, {'validate': 'Valider', 'validatorcomment': 'Vu avec l\'étudiant.'}, 'élément (référent)')
    return u.requests


if 'referents' in PHASES:
    first_ref, last_ref = (int(x) for x in os.environ.get('REFERENTS', '1-150').split('-'))
    phase('3. référents : déclarations et éléments (synthèse)', referent_decides, list(range(first_ref, last_ref + 1)))


# 4. DEVE.
SUPPLEMENT_TEXTS = {
    'qualification': ('Diplôme d\'État de docteur vétérinaire', 'State diploma of Doctor of Veterinary Medicine'),
    'fieldofstudy': ('Médecine vétérinaire', 'Veterinary medicine'),
    'institution': ('École nationale vétérinaire (établissement public)', 'National veterinary school (public)'),
    'languages': ('Français', 'French'),
    'level': ('Niveau 8 du cadre européen des certifications', 'Level 8 of the European Qualifications Framework'),
    'length': ('Six ans, 360 ECTS', 'Six years, 360 ECTS'),
    'signatoryname': ('Claire Martin', 'Claire Martin'),
    'signatorytitle': ('Directrice de l\'école', 'Dean'),
}


def deve_settings(_):
    """Textes des rubriques fixes et signataire, par le formulaire de paramétrage du supplément."""
    u = User('devea_4').login()
    r = u.get(f'/mod/ep/supplement_settings.php?id={CM}', 'paramétrage du supplément')
    f = form_with(r.text, 'text_fr_signatoryname')
    overrides = {}
    for key, (fr, en) in SUPPLEMENT_TEXTS.items():
        overrides[f'text_fr_{key}'], overrides[f'text_en_{key}'] = fr, en
    r = u.submit(f, dict(overrides, submitbutton='Enregistrer'), 'enregistrement du paramétrage')
    return u.requests


if 'deve' in PHASES:
    phase('4. DEVE : paramétrage du supplément (textes, signataire)', deve_settings, [0])


def deve_items(n):
    """Les quatre comptes de la DEVE se partagent la file : chacun traite les éléments dont le
    numéro, modulo 4, est le sien, en parcourant les pages de la file jusqu'à n'en plus trouver."""
    u = User(f'devea_{n}').login()
    seen = set()
    for _ in range(200):
        found = False
        for qpage in range(50):
            r = u.get(f'/mod/ep/supplement_items.php?id={CM}&qpage={qpage}', 'éléments à examiner')
            forms = [f for f in forms_of(r.text) if 'itemid' in dict(f['fields'])]
            if not forms:
                break
            mine = [f for f in forms if int(dict(f['fields'])['itemid']) % 4 == n - 1
                    and dict(f['fields'])['itemid'] not in seen]
            for f in mine:
                seen.add(dict(f['fields'])['itemid'])
                u.submit(f, {'validate': 'Valider', 'validatorcomment': ''}, 'élément (DEVE)')
            if mine:
                found = True
                break
        if not found:
            break
    return u.requests


if 'deve' in PHASES:
    phase('4a. DEVE : décisions finales sur les éléments', deve_items, [1, 2, 3, 4])


def deve_years(_):
    u = User(f'devea_1').login()
    u.get(f'/mod/ep/year_validation.php?id={CM}&studyyear=3', 'validation des années')
    u.post(f'/mod/ep/year_validation.php?id={CM}', {'sesskey': u.sesskey, 'action': 'bulk', 'studyyear': 3},
           'années atteintes')
    u.post(f'/mod/ep/year_validation.php?id={CM}', {'sesskey': u.sesskey, 'action': 'bulk', 'studyyear': 3, 'reject': 1},
           'années non atteintes')
    return u.requests


if 'deve' in PHASES:
    phase('4b. DEVE : validation des années en lot', deve_years, [0])


def deve_issue(_):
    u = User('devea_2').login()
    u.post(f'/mod/ep/supplement_issue.php?id={CM}', {'sesskey': u.sesskey, 'action': 'issueall', 'lang': 'fr',
                                                      'force': 1}, 'émission groupée')
    t = time.time()
    out = subprocess.run(['sudo', '-u', 'www-data', 'php', 'admin/cli/adhoc_task.php', '--execute'], cwd=MOODLE,
                         capture_output=True, text=True).stdout
    report['emission'] = {'duree_s': round(time.time() - t, 1), 'trace': out[-400:]}
    return u.requests + 1


if 'deve' in PHASES:
    phase('4c. DEVE : émission groupée des 1 000 suppléments', deve_issue, [0])

sample = [] if 'deve' not in PHASES else sql(f"select number || ' ' || verifycode from mdl_ep_supplement_issue where epid = {EPID} and replacedby is null "
             "order by random() limit 50").splitlines()


def verify(line):
    number, code = line.split()
    r = requests.get(BASE + '/mod/ep/supplement_verify.php', params={'number': number, 'code': code[:16]}, timeout=60)
    if 'alert-success' not in r.text:
        anomaly(f'vérification refusée pour {number}')
    r = requests.get(BASE + '/mod/ep/supplement_verify.php', params={'number': number, 'code': '0' * 16}, timeout=60)
    if 'alert-success' in r.text:
        anomaly(f'faux code accepté pour {number}')
    return 2


if 'deve' in PHASES:
    phase('4d. public : vérification de 50 suppléments (et faux codes)', verify, sample)


def deve_exports(_):
    u = User('devea_3').login()
    for mode in ('students', 'credits'):
        r = u.get(f'/mod/ep/export.php?id={CM}&mode={mode}', f'export {mode}')
        report[f'export_{mode}_ko'] = len(r.content) // 1024
    r = u.post(f'/mod/ep/archive.php?id={CM}', {'sesskey': u.sesskey, 'action': 'export'}, 'export complet')
    report['export_complet_ko'] = len(r.content) // 1024
    return u.requests


if 'deve' in PHASES:
    phase('4e. DEVE : exports CSV et export complet', deve_exports, [0])

# 5. Contrôles en base.
if 'controles' not in PHASES:
    for a in report['anomalies'][:15]:
        print('  ', a, file=sys.stderr)
    json.dump(report, sys.stdout, indent=1, ensure_ascii=False)
    sys.exit(0)
n = len(students)
checks = {
    'etudiants': n,
    'inscriptions': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is not null")),
    'inscriptions_attendues': expected.get('registrations', 0),
    'inscriptions_validees': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is not null and status=1")),
    'inscriptions_refusees': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is not null and status=-1")),
    'retraits_de_declarations': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is null and status=-2")),
    'retraits_attendus': expected['withdrawn'],
    'declarations': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is null")),
    'declarations_validees': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is null and status=1")),
    'declarations_refusees': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and activityid is null and status=-1")),
    'declarations_modifiees': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and name like '%(complétée)'")),
    'modifications_attendues': expected['edited'],
    'justificatifs': int(sql("select count(*) from mdl_files where component='mod_ep' and filearea='evidence' and filename<>'.'"
                             f" and itemid in (select id from mdl_ep_credit where epid={EPID})")),
    'demandes_encore_en_attente': int(sql(f"select count(*) from mdl_ep_credit where epid={EPID} and status=0")),
    'credits_sans_decision': int(sql(f"select count(*) from mdl_ep_credit c where epid={EPID} and status in (1,-1) and not exists "
                                      "(select 1 from mdl_ep_credit_decision d where d.creditid=c.id)")),
    'decisions_en_double': int(sql("select count(*) from (select d.creditid, d.step from mdl_ep_credit_decision d join mdl_ep_credit c "
                                   f"on c.id=d.creditid where c.epid={EPID} group by d.creditid, d.step having count(*)>1) x")),
    'inscriptions_en_double': int(sql("select count(*) from (select userid, activityid from mdl_ep_credit where "
                                      f"epid={EPID} and activityid is not null and status in (0,1) group by userid, activityid having count(*)>1) x")),
    'surreservations': int(sql("select count(*) from (select a.id from mdl_ep_activity a join mdl_ep_credit c on c.activityid=a.id "
                               f"and c.status in (0,1) where a.epid={EPID} and a.capacity>0 group by a.id, a.capacity having count(*)>a.capacity) x")),
    'elements': int(sql(f"select count(*) from mdl_ep_supplement_item where epid={EPID}")),
    'elements_valides': int(sql(f"select count(*) from mdl_ep_supplement_item where epid={EPID} and status=1")),
    'preuves': int(sql("select count(*) from mdl_files where component='mod_ep' and filearea='supplementevidence' and filename<>'.'"
                             f" and itemid in (select id from mdl_ep_supplement_item where epid={EPID})")),
    'decisions_annee': int(sql(f"select count(*) from mdl_ep_year_validation where epid={EPID}")),
    'annees_validees': int(sql(f"select count(*) from mdl_ep_year_validation where epid={EPID} and decision=1")),
    'supplements_emis': int(sql(f"select count(*) from mdl_ep_supplement_issue where epid={EPID} and replacedby is null")),
    'supplements_scelles': int(sql(f"select count(*) from mdl_ep_supplement_issue where epid={EPID} and seal is not null")),
    'numeros_en_double': int(sql("select count(*) from (select number from mdl_ep_supplement_issue group by number having count(*)>1) x")),
    'pdf_emis': int(sql("select count(*) from mdl_files where component='mod_ep' and filearea='supplementissued' and filename<>'.'"
                        f" and itemid in (select id from mdl_ep_supplement_issue where epid={EPID})")),
    'verrous_restants': int(sql("select count(*) from pg_locks where locktype='advisory'")),
}
report['controles'] = checks
report['anomalies_total'] = len(report['anomalies'])
print(json.dumps(checks, ensure_ascii=False, indent=1), file=sys.stderr)
print(f"anomalies : {len(report['anomalies'])}", file=sys.stderr)
for a in report['anomalies'][:15]:
    print('  ', a, file=sys.stderr)
json.dump(report, sys.stdout, indent=1, ensure_ascii=False)
