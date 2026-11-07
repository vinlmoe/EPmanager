#!/usr/bin/env python3
"""Modules syllabus de bout en bout, par les pages web des trois modules.

Usage : python3 syllabus_interactions.py site-x.json syllabus.json > syllabus-resultats.json
(syllabus.json : sortie de « sudo -u www-data php syllabus_setup.php site-x.json »).

1. DEVE, référentiel : relecture obligatoire, seuil de 5 réponses, deux types de module, quatre
   questions communes (trois pour tous les modules, une pour le type « Clinique ») ; type de
   chaque module dans ses réglages.
2. Responsables (12 modules en parallèle) : fiche rédigée et soumise, deux questions posées.
3. DEVE : relecture, onze fiches publiées, une renvoyée ; le responsable la corrige, elle est
   publiée. La DEVE crée le référentiel de compétences dans le suivi des modules (import CSV) ;
   chaque responsable lie son module à des compétences, avec un niveau ; la DEVE lie un cours
   sans module, consulte la matrice compétences × cours et l'exporte.
4. DEVE : même fenêtre d'évaluation pour les 12 modules, en lot ; tâche d'ouverture (questions
   figées, étudiants prévenus).
5. 200 étudiants (40 en parallèle) : « Mes modules », puis évaluation de chaque module (neuf sur
   dix) ; une seconde réponse est refusée.
6. Avant la fermeture, le responsable ne voit pas les résultats ; la DEVE ferme l'évaluation en
   lot ; résultats du module, synthèse comparée, exports CSV.
7. Contrôles en base.
"""
import html
import json
import random
import re
import subprocess
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from html.parser import HTMLParser
from urllib.parse import urljoin

import requests

SITE = json.load(open(sys.argv[1]))
SETUP = json.load(open(sys.argv[2]))
BASE, PW = SITE['wwwroot'], SITE['password']
STAFF, STUDENT = SETUP['staffcmid'], SETUP['studentcmid']
MODULES = SETUP['modules']
MOODLE = '/home/user/moodle-stress/moodle'
ERRORS = [r'Fatal error[^<]*', r'Exception - [^<]*', r'Coding error detected[^<]*', r'<b>Warning</b>:[^<]*',
          r'Error code: [a-z_]+', r'debuggingmessage[^>]*>[^<]*']
random.seed(20261120)
report = {'phases': [], 'anomalies': [], 'controles': {}}
lock = threading.Lock()


def anomaly(text):
    with lock:
        report['anomalies'].append(text[:300])
    print('  ANOMALIE', text[:200], file=sys.stderr, flush=True)


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


def forms(text):
    p = FormParser()
    p.feed(text)
    return p.forms


def check(user, r, what):
    for pattern in ERRORS:
        m = re.search(pattern, r.text)
        if m:
            anomaly(f'{user}: {what}: {m.group(0)}')
            return
    if r.status_code >= 500:
        anomaly(f'{user}: {what}: HTTP {r.status_code}')


class Web(User):
    """Utilisateur dont chaque page est contrôlée (erreur PHP, exception, code 500)."""

    def __init__(self, username):
        self.name = username
        super().__init__(username)

    def get(self, url):
        r = self.s.get(BASE + url if url.startswith('/') else url, timeout=300)
        check(self.name, r, url)
        self.last = r.url
        return r

    def submit(self, form, overrides, drop=()):
        # Action relative (« modedit.php ») : résolue par rapport à la page du formulaire.
        form = dict(form, action=urljoin(getattr(self, 'last', BASE + '/'), form['action']))
        return super().submit(form, overrides, drop)

    def post(self, url, data):
        r = self.s.post(BASE + url if url.startswith('/') else url, data=data, timeout=300)
        check(self.name, r, url)
        return r


def expect(name, ok, detail=''):
    report['controles'][name] = {'ok': bool(ok), 'detail': str(detail)}
    print(f"{'OK ' if ok else 'KO '} {name} {detail}", file=sys.stderr, flush=True)


def phase(name, func, items, workers=12):
    t = time.time()
    with ThreadPoolExecutor(workers) as pool:
        results = list(pool.map(lambda item: _safe(name, func, item), items))
    report['phases'].append({'phase': name, 'acteurs': len(items), 'duree_s': round(time.time() - t, 1)})
    print(f'{name} : {len(items)} acteurs, {time.time() - t:.1f} s', file=sys.stderr, flush=True)
    return results


def _safe(name, func, item):
    try:
        return func(item)
    except Exception as e:  # Toute exception du scénario est une anomalie.
        anomaly(f'{name}: {type(e).__name__} {e}')
        return None


def local(ts):
    """Horodatage en saisie datetime-local (fuseau du serveur, celui des comptes du banc)."""
    return datetime.fromtimestamp(ts).strftime('%Y-%m-%dT%H:%M')


ids = [m['id'] for m in MODULES]
idlist = ','.join(map(str, ids))

# 1. Référentiel de la DEVE.
deve = Web('devea_1')
ref = f'/mod/syllabusstaff/referential.php?id={STAFF}'
deve.post(ref, {'sesskey': deve.sesskey, 'action': 'savesettings', 'reviewrequired': 1, 'resultsthreshold': 5,
                'teacherresults': 'afterclose'})
for name in ('Fondamental', 'Clinique'):
    deve.post(ref, {'sesskey': deve.sesskey, 'action': 'savetype', 'typename': name})
types = dict(line.split('|')[::-1] for line in sql("select id || '|' || name from mdl_syllabus_type").splitlines())
common = [('common', 0, 'scale', 'Le module était bien organisé.', '', 1),
          ('common', 0, 'scale', 'Les objectifs du module étaient clairs.', '', 1),
          ('common', 0, 'text', 'Qu\'améliorer en priorité ?', '', 0),
          ('type', types['Clinique'], 'yesno', 'Les cas cliniques étaient en nombre suffisant.', '', 1)]
for origin, typeid, kind, text, options, required in common:
    f = form_with(deve.get(ref).text, 'text')
    deve.submit(f, {'origin': origin, 'typeid': str(typeid), 'kind': kind, 'text': text, 'options': options,
                    'required': str(required), 'enabled': '1', 'submitbutton': 'Enregistrer'})
expect('referentiel', sql("select count(*) from mdl_syllabus_question where syllabusid = 0") == '4'
       and sql("select value from mdl_config_plugins where plugin='mod_syllabus' and name='resultsthreshold'") == '5')
for k, module in enumerate(MODULES):
    r = deve.get(f'/course/modedit.php?update={module["cmid"]}')
    f = form_with(r.text, 'typeid')
    deve.submit(f, {'typeid': types['Clinique' if k >= 6 else 'Fondamental'], 'submitbutton': 'Enregistrer et revenir au cours'},
                drop=('cancel', 'submitbutton2'))
expect('types_des_modules', sql(f"select count(*) from mdl_syllabus where id in ({idlist}) and typeid > 0") == '12')


# 2. Responsables : fiche et questions.
def leader_writes(module):
    u = Web(module['leaders'][0])
    r = u.get(f'/mod/syllabus/edit.php?id={module["cmid"]}')
    f = form_with(r.text, 'objectives')
    over = {'objectives': f'Maîtriser les bases de {module["name"].lower()}.', 'outcomes': 'Identifier, expliquer, appliquer.',
            'content': 'Cours, travaux dirigés et cas pratiques.', 'assessment': 'Examen écrit (70 %) et contrôle continu.',
            'language': 'Français', 'personalhours': '20', 'submitforreview': 'Soumettre'}
    for i, (title, kind, hours) in enumerate([('Introduction', 'lecture', 2), ('Travaux dirigés', 'tutorial', 4),
                                               ('Cas pratiques', 'practical', 3)]):
        over[f'sessionrow[{i}][sessiontitle]'] = title
        over[f'sessionrow[{i}][sessionkind]'] = kind
        over[f'sessionrow[{i}][sessionduration]'] = str(hours)
    u.submit(f, over, drop=('submitbutton', 'cancel'))
    url = f'/mod/syllabus/evaluation.php?id={module["cmid"]}'
    for kind, text, options in [('scale', 'Les supports de cours étaient utiles.', ''),
                                ('choice', 'La séance la plus utile', 'Cours\nTravaux dirigés\nCas pratiques')]:
        f = next(x for x in forms(u.get(url).text) if any(n == 'text' for n, _ in x['fields']))
        u.submit(f, {'kind': kind, 'text': text, 'options': options, 'required': '1', 'submitbutton': 'Enregistrer'})


phase('2. responsables : fiches et questions', leader_writes, MODULES)
expect('fiches_soumises', sql(f"select count(*) from mdl_syllabus_version where syllabusid in ({idlist}) "
                              "and status='submitted'") == '12')
expect('questions_enseignants', sql(f"select count(*) from mdl_syllabus_question where syllabusid in ({idlist})") == '24')

# 3. Relecture.
r = deve.get(f'/mod/syllabusstaff/review.php?id={STAFF}')
expect('fiches_a_relire_listees', len(set(re.findall(r'view\.php\?id=(\d+)&amp;show=working', r.text))) == 12)
returned = MODULES[2]
for module in MODULES:
    decision = 'return' if module is returned else 'publish'
    deve.post(f'/mod/syllabus/view.php?id={module["cmid"]}&show=working', {'sesskey': deve.sesskey, 'decision': decision,
              'reviewcomment': 'Préciser les modalités d\'évaluation.' if decision == 'return' else ''})
expect('fiche_renvoyee', sql(f"select status from mdl_syllabus_version where syllabusid={returned['id']}") == 'returned')
u = Web(returned['leaders'][0])
f = form_with(u.get(f'/mod/syllabus/edit.php?id={returned["cmid"]}').text, 'objectives')
u.submit(f, {'assessment': 'Examen écrit de 2 h (70 %) et deux contrôles continus (30 %).', 'submitforreview': 'Soumettre'},
         drop=('submitbutton', 'cancel'))
deve.post(f'/mod/syllabus/view.php?id={returned["cmid"]}&show=working', {'sesskey': deve.sesskey, 'decision': 'publish',
          'reviewcomment': ''})
expect('fiches_publiees', sql(f"select count(*) from mdl_syllabus_version where syllabusid in ({idlist}) "
                              "and status='published'") == '12')
# 3 bis. Compétences. La DEVE crée le référentiel dans le suivi des modules et en importe les
# compétences (CSV : 5 domaines de 4 compétences), puis en ajoute une à la main ; chaque
# responsable lie son module à 3 à 6 compétences, avec un niveau ; la DEVE lie un cours sans
# module ; matrice et export.
fw = f'/mod/syllabusstaff/frameworks.php?id={STAFF}'
f = form_with(deve.get(fw).text, 'idnumber')
deve.submit(f, {'name': 'Compétences du vétérinaire', 'idnumber': 'REFVET', 'description': 'Référentiel de l\'école.',
                'submitbutton': 'Enregistrer'}, drop=('cancel',))
FRAMEWORK = int(sql("select id from mdl_syllabus_framework where idnumber='REFVET'") or 0)
domains = ['Démarche clinique', 'Santé publique vétérinaire', 'Thérapeutique', 'Communication', 'Professionnalisme']
lines = ['Code;Code parent;Intitulé;Description']
for d, name in enumerate(domains, 1):
    lines += [f'D{d}.{c};D{d};{name} : compétence {c};' for c in range(1, 5)] + [f'D{d};;{name};Domaine {d}']
page = deve.get(f'{fw}&frameworkid={FRAMEWORK}').text
f = next(x for x in forms(page) if any(n == 'csvfile' for n, _ in x['fields']))
draft = dict(f['fields'])['csvfile']
ctx = re.search(r'"contextid":(\d+)', page).group(1)
deve.s.post(BASE + '/repository/repository_ajax.php?action=upload', data={'sesskey': deve.sesskey, 'repo_id': 5,
            'itemid': draft, 'ctx_id': ctx, 'savepath': '/', 'title': 'referentiel.csv', 'env': 'filepicker'},
            files={'repo_upload_file': ('referentiel.csv', '\n'.join(lines).encode('utf-8'), 'text/csv')}, timeout=60)
deve.last = BASE + f'{fw}&frameworkid={FRAMEWORK}'
r = deve.submit(f, {'submitbutton': 'Importer'})
page = deve.get(f'{fw}&frameworkid={FRAMEWORK}').text
f = next(x for x in forms(page) if any(n == 'compid' for n, _ in x['fields']))
d5 = sql(f"select id from mdl_syllabus_comp where frameworkid={FRAMEWORK} and idnumber='D5'")
deve.submit(f, {'parentid': d5, 'idnumber': 'D5.5', 'name': 'Professionnalisme : compétence 5',
                'submitbutton': 'Enregistrer'}, drop=('cancel',))
expect('referentiel_de_competences_cree', sql(f"select count(*) from mdl_syllabus_comp where frameworkid={FRAMEWORK}") == '26'
       and sql(f"select count(*) from mdl_syllabus_comp where frameworkid={FRAMEWORK} and parentid > 0") == '21')
LEAVES = [int(x) for x in sql(f"select id from mdl_syllabus_comp where frameworkid={FRAMEWORK} and parentid > 0 "
                              "order by idnumber").splitlines()]
expected_links = {}


def leader_links(k_module):
    k, module = k_module
    u = Web(module['leaders'][0])
    url = f'/mod/syllabus/competencies.php?id={module["cmid"]}&frameworkid={FRAMEWORK}'
    page = u.get(url).text
    rng = random.Random(k)
    chosen = rng.sample(LEAVES[:19], rng.randint(3, 6))
    levels = {c: rng.randint(1, 3) for c in chosen}
    data = [('sesskey', u.sesskey), ('save', 1)] + [(f'levels[{c}]', levels.get(c, 0)) for c in map(int, re.findall(
        r'name="levels\[(\d+)\]"', page))]
    r = u.post(url, data)
    if 'ajoutée' not in r.text:
        anomaly(f'{module["leaders"][0]}: compétences non confirmées')
    with lock:
        expected_links[module['id']] = levels


phase('3 bis. responsables : compétences des modules', leader_links, list(enumerate(MODULES)))
links = sql(f"select count(*) from mdl_syllabus_complink where syllabusid in ({idlist})")
expect('competences_liees_aux_modules', int(links) == sum(len(v) for v in expected_links.values()), links)
levels_ok = all(sql(f"select string_agg(compid || ':' || level, ',' order by compid) from mdl_syllabus_complink "
                    f"where syllabusid={sid}") == ','.join(f'{c}:{l}' for c, l in sorted(v.items()))
                for sid, v in expected_links.items())
expect('niveaux_enregistres', levels_ok)
url = f'/mod/syllabusstaff/courselink.php?id={STAFF}&courseid={SETUP["nomodulecourse"]}&frameworkid={FRAMEWORK}'
page = deve.get(url).text
deve.post(url, [('sesskey', deve.sesskey), ('save', 1)] + [(f'levels[{c}]', 3 if c == LEAVES[-1] else 0)
                                                          for c in map(int, re.findall(r'name="levels\[(\d+)\]"', page))])
expect('cours_sans_module_lie', sql(f"select count(*) from mdl_syllabus_complink where courseid={SETUP['nomodulecourse']} "
                                    "and syllabusid = 0") == '1')
r = deve.get(f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={FRAMEWORK}')
cells = len(re.findall(r'<td class="cell[^"]*"[^>]*>[IAM]\*?</td>', r.text))
expect('matrice_des_competences', cells == int(links) + 1, f'{cells} cellules')
expect('competence_non_couverte_signalee', 'table-warning' in r.text)
csv = deve.get(f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={FRAMEWORK}&download=csv')
expect('export_csv_des_competences', csv.text.count('\n') >= 27, f'{csv.text.count(chr(10))} lignes')
fiche = Web(SETUP['students'][0]).get(f'/mod/syllabus/view.php?id={MODULES[0]["cmid"]}')
expect('competences_sur_la_fiche', 'Compétences visées' in fiche.text)
teacher_matrix = Web(MODULES[0]['leaders'][0]).get(f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={FRAMEWORK}')
expect('matrice_ouverte_aux_enseignants', 'syllabusstaff-matrix' in teacher_matrix.text)

# 4. Fenêtre en lot, tâche d'ouverture.
now = int(time.time())
deve.post(f'/mod/syllabusstaff/all.php?id={STAFF}', [('sesskey', deve.sesskey), ('setwindow', 1),
          ('evalopen', local(now - 120)), ('evalclose', local(now + 2 * 86400))] + [('syllabusids[]', i) for i in ids])
expect('fenetre_en_lot', sql(f"select count(*) from mdl_syllabus where id in ({idlist}) and evalopen > 0") == '12')
t = time.time()
out = subprocess.run(['sudo', '-u', 'www-data', 'php', 'admin/cli/scheduled_task.php',
                      '--execute=\\mod_syllabus\\task\\open_evaluations'], cwd=MOODLE, capture_output=True, text=True).stdout
report['tache_ouverture_s'] = round(time.time() - t, 1)
expect('questions_figees', sql(f"select count(*) from mdl_syllabus where id in ({idlist}) and evalfrozen = 1") == '12')
sent = int(sql(f"select count(*) from mdl_notifications where component='mod_syllabus' and eventtype='evaluationopen' and timecreated >= {now - 5}"))
expect('etudiants_prevenus', sent == 2400, f'{sent} messages')

# 5. Étudiants.
counts = {'answered': 0, 'skipped': 0, 'listed': 0}


def student_evaluates(username):
    u = Web(username)
    r = u.get(f'/mod/syllabusstudent/view.php?id={STUDENT}')
    links = sorted(set(re.findall(r'href="[^"]*/mod/syllabus/evaluate\.php\?id=(\d+)"', r.text)))
    with lock:
        counts['listed'] += len(links)
    for k, cmid in enumerate(links):
        if random.random() < 0.1:
            with lock:
                counts['skipped'] += 1
            continue
        page = u.get(f'/mod/syllabus/evaluate.php?id={cmid}').text
        f = next(x for x in forms(page) if any(n.startswith('_qf__') and 'evaluate_form' in n for n, _ in x['fields']))
        answers = {}
        radios = {}
        for m in re.finditer(r'<input[^>]*type="radio"[^>]*>', page):
            tag = m.group(0)
            name = re.search(r'name="(q\d+)"', tag)
            value = re.search(r'value="(\d+)"', tag)
            if name and value:
                radios.setdefault(name.group(1), []).append(int(value.group(1)))
        for name, values in radios.items():
            answers[name] = str(random.choice(values) if len(values) != 5 else random.choice([3, 4, 4, 5, 5, 2]))
        for name in re.findall(r'<textarea[^>]*name="(q\d+)"', page):
            if random.random() < 0.4:
                answers[name] = random.choice(['Plus de cas pratiques.', 'Rythme trop rapide.', 'Très bon module.'])
        r = u.submit(f, dict(answers, submitbutton='Envoyer'), drop=('cancel',))
        if 'Merci' not in r.text:
            anomaly(f'{username}: évaluation du cm {cmid} non confirmée')
        else:
            with lock:
                counts['answered'] += 1
        if k == 0 and username.endswith('_1'):
            r = u.get(f'/mod/syllabus/evaluate.php?id={cmid}')
            expect('seconde_reponse_refusee', 'évalué ce module' in r.text)
    return len(links)


phase('5. étudiants : mes modules et évaluations', student_evaluates, SETUP['students'], workers=40)
expect('modules_listes_aux_etudiants', counts['listed'] == 12 * len(SETUP['students']), counts)
answered = int(sql(f"select count(*) from mdl_syllabus_participant where syllabusid in ({idlist})"))
responses = int(sql(f"select count(*) from mdl_syllabus_response where syllabusid in ({idlist})"))
expect('une_reponse_par_participation', answered == responses == counts['answered'], f'{answered} / {responses}')

# 6. Résultats.
leader = Web(MODULES[0]['leaders'][0])
r = leader.get(f'/mod/syllabus/results.php?id={MODULES[0]["cmid"]}')
expect('resultats_caches_avant_fermeture', 'Moyenne' not in r.text)
deve.post(f'/mod/syllabusstaff/all.php?id={STAFF}', [('sesskey', deve.sesskey), ('setwindow', 1),
          ('evalopen', local(now - 3 * 86400)), ('evalclose', local(now - 60))] + [('syllabusids[]', i) for i in ids])
r = leader.get(f'/mod/syllabus/results.php?id={MODULES[0]["cmid"]}')
expect('resultats_du_module_apres_fermeture', 'Moyenne' in r.text)
csv = leader.get(f'/mod/syllabus/results.php?id={MODULES[0]["cmid"]}&download=csv')
expect('export_csv_du_module', csv.text.count('\n') >= 6, f'{len(csv.content)} octets')
r = deve.get(f'/mod/syllabusstaff/results.php?id={STAFF}')
means = re.findall(r'<td[^>]*>([1-5],\d+|[1-5])</td>', r.text)
expect('synthese_comparee', len(means) >= 24, f'{len(means)} moyennes')
r = deve.get(f'/mod/syllabusstaff/view.php?id={STAFF}')
r = deve.get(f'/mod/syllabusstaff/all.php?id={STAFF}&evalstate=closed')
expect('liste_dev_filtree', '(12)' in r.text)
pdf = Web(SETUP['students'][0]).get(f'/mod/syllabus/pdf.php?id={MODULES[0]["cmid"]}')
expect('fiche_pdf', pdf.content[:4] == b'%PDF')

# 7. Contrôles.
expect('reponses_sans_identifiant', sql("select string_agg(column_name, ',' order by ordinal_position) from "
                                        "information_schema.columns where table_name='mdl_syllabus_response'")
       == 'id,syllabusid,daysubmitted')
expect('reponses_obligatoires_completes', sql(f"""select count(*) from mdl_syllabus_question q
    join mdl_syllabus_response r on r.syllabusid = q.syllabusid
    where q.syllabusid in ({idlist}) and q.required = 1
    and not exists (select 1 from mdl_syllabus_answer a where a.responseid = r.id and a.questionid = q.id)""") == '0')
expect('questions_par_module', sql(f"""select string_agg(n::text, ',') from (select count(*) n from mdl_syllabus_question
    where syllabusid in ({idlist}) group by syllabusid order by syllabusid) x""") == ','.join(['5'] * 6 + ['6'] * 6))
expect('aucune_anomalie', not report['anomalies'], len(report['anomalies']))
json.dump(report, sys.stdout, indent=1, ensure_ascii=False)
ok = sum(c['ok'] for c in report['controles'].values())
print(f"\n{ok}/{len(report['controles'])} contrôles réussis", file=sys.stderr)
