#!/usr/bin/env python3
"""Test de stress des modules syllabus : montée en charge par paliers, ou endurance.

Usage :
  python3 syllabus_stress.py paliers site-x.json syllabus-charge.json > syllabus-stress.json
  python3 syllabus_stress.py endurance site-x.json syllabus-charge.json <utilisateurs> <minutes> > endurance.json

Jeu de données : syllabus_charge_setup.php (cinq années de douze modules, 1 000 étudiants). Avant
la charge, les évaluations du second semestre de chaque année sont ouvertes : les étudiants
virtuels évaluent leurs modules pendant le test (écritures sous verrou).

Utilisateurs virtuels, sans temps de réflexion : 75 % d'étudiants (Mes modules, fiches,
compétences, PDF, évaluations), 20 % d'enseignants (suivi, fiche, rédaction, évaluation, résultats,
matrice, progression, livret), 5 % de la DEVE (tous les modules, résultats comparés, relecture,
matrice, progression, référentiel, livret). Paliers par défaut 25 à 400 utilisateurs, 60 s chacun
(variables PALIERS et DUREE). Mesures et sortie comme stress.py.
"""
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

import requests
from requests.adapters import HTTPAdapter

MODE = sys.argv[1]
SITE = json.load(open(sys.argv[2]))
DATA = json.load(open(sys.argv[3]))
if MODE == 'endurance':
    ENDURANCE_USERS, ENDURANCE_MINUTES = int(sys.argv[4]), float(sys.argv[5])
BASE, PW = SITE['wwwroot'], SITE['password']
STAFF = DATA['staffcmid']
STAGES = [int(x) for x in os.environ.get('PALIERS', '25,50,100,200,300,400').split(',')]
DURATION = float(os.environ.get('DUREE', '60'))
TIMEOUT = 60
ERRORS = [r'Fatal error[^<]*', r'Exception - [^<]*', r'Coding error detected[^<]*', r'<b>Warning</b>:[^<]*',
          r'Error code: [a-z_]+', r'Maximum execution time[^<]*', r'Debug info:[^<]*']
MODULES = DATA['modules']
BYYEAR = {y: [m for m in MODULES if m['year'] == y] for y in range(1, 6)}
COMPS = [int(x) for x in subprocess.run(
    ['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc',
     f"select id from mdl_syllabus_comp where frameworkid = {DATA['frameworkid']} order by id"],
    capture_output=True, text=True).stdout.split()]


def sql(query):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', query], capture_output=True,
                          text=True).stdout.strip()


class Vu:
    """Utilisateur virtuel : une session connectée, un rôle."""

    def __init__(self, role, index):
        self.role, self.index = role, index
        if role == 'etu':
            self.year = 1 + index % 5
            students = DATA['promos'][str(self.year)]['students']
            self.user = students[(index // 5) % len(students)]
            self.todo = [m for m in BYYEAR[self.year] if int(m['semester'][1:]) % 2 == 0]
            random.shuffle(self.todo)
        elif role == 'ens':
            self.owned = MODULES[index % len(MODULES)]
            self.user = self.owned['leaders'][0]
        else:
            self.user = f'devea_{1 + index % 4}'
        self.session = requests.Session()
        self.session.mount('http://', HTTPAdapter(pool_connections=1, pool_maxsize=1))

    def login(self):
        r = self.session.get(BASE + '/login/index.php', timeout=TIMEOUT)
        token = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
        self.session.post(BASE + '/login/index.php', timeout=TIMEOUT,
                          data={'username': self.user, 'password': PW, 'logintoken': token})
        return self

    def evaluate(self, cmid):
        """Ouvre le formulaire d'évaluation et l'envoie : deux requêtes, la seconde est l'écriture."""
        page = self.session.get(BASE + f'/mod/syllabus/evaluate.php?id={cmid}', timeout=TIMEOUT).text
        form = re.search(r'<form[^>]*action="([^"]*evaluate\.php[^"]*)"[^>]*>(.*?)</form>', page, re.S)
        if not form:
            return None
        fields, radios = {}, {}
        for tag in re.findall(r'<input[^>]*>', form.group(2)):
            kind, name = re.search(r'type="([^"]+)"', tag), re.search(r'name="([^"]+)"', tag)
            value = re.search(r'value="([^"]*)"', tag)
            if not (kind and name and value):
                continue
            if kind.group(1) == 'hidden':
                fields[name.group(1)] = value.group(1)
            elif kind.group(1) == 'radio':
                radios.setdefault(name.group(1), []).append(value.group(1))
        for name, values in radios.items():
            fields[name] = random.choice(values)
        for name in re.findall(r'<textarea[^>]*name="([^"]+)"', form.group(2)):
            fields[name] = random.choice(['', 'Très bon module.', 'Plus de cas pratiques.'])
        fields['submitbutton'] = 'Envoyer'
        return ('POST', form.group(1).replace('&amp;', '&'), fields, 'etu:evaluation')

    def next_request(self):
        if self.role == 'etu':
            if self.todo and random.random() < 0.2:
                module = self.todo.pop()
                request = self.evaluate(module['cmid'])
                if request:
                    return request
            module = random.choice(BYYEAR[self.year])
            promo = DATA['promos'][str(self.year)]['cmid']
            pages = [(f'/mod/syllabusstudent/view.php?id={promo}', 'etu:mes-modules'),
                     (f'/mod/syllabus/view.php?id={module["cmid"]}', 'etu:fiche'),
                     (f'/mod/syllabus/view.php?id={module["cmid"]}', 'etu:fiche'),
                     (f'/mod/syllabus/competencies.php?id={module["cmid"]}', 'etu:competences')]
            if random.random() < 0.05:
                pages = [(f'/mod/syllabus/pdf.php?id={module["cmid"]}', 'etu:fiche-pdf')]
        elif self.role == 'ens':
            cm = self.owned['cmid']
            pages = [(f'/mod/syllabusstaff/view.php?id={STAFF}', 'ens:mes-modules'),
                     (f'/mod/syllabus/view.php?id={cm}', 'ens:fiche'), (f'/mod/syllabus/edit.php?id={cm}', 'ens:redaction'),
                     (f'/mod/syllabus/evaluation.php?id={cm}', 'ens:evaluation'),
                     (f'/mod/syllabus/results.php?id={cm}', 'ens:resultats'),
                     (f'/mod/syllabus/competencies.php?id={cm}&frameworkid={DATA["frameworkid"]}', 'ens:competences'),
                     (f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={DATA["frameworkid"]}', 'ens:matrice'),
                     (f'/mod/syllabusstaff/progression.php?id={STAFF}&compid={random.choice(COMPS)}', 'ens:progression'),
                     (f'/mod/syllabusstaff/booklet.php?id={STAFF}&selection=y{self.owned["year"]}', 'ens:livret')]
        else:
            pages = [(f'/mod/syllabusstaff/all.php?id={STAFF}&page={random.randint(0, 1)}', 'deve:tous'),
                     (f'/mod/syllabusstaff/all.php?id={STAFF}&evalstate={random.choice(["open", "closed"])}', 'deve:tous-filtre'),
                     (f'/mod/syllabusstaff/results.php?id={STAFF}', 'deve:resultats'),
                     (f'/mod/syllabusstaff/review.php?id={STAFF}', 'deve:a-relire'),
                     (f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={DATA["frameworkid"]}', 'deve:matrice'),
                     (f'/mod/syllabusstaff/progression.php?id={STAFF}&compid={random.choice(COMPS)}', 'deve:progression'),
                     (f'/mod/syllabusstaff/referential.php?id={STAFF}', 'deve:referentiel'),
                     (f'/mod/syllabusstaff/booklet.php?id={STAFF}', 'deve:livret')]
        url, label = random.choice(pages)
        return ('GET', url, None, label)


class Recorder:
    def __init__(self):
        self.lock = threading.Lock()
        self.samples = []

    def add(self, sample):
        with self.lock:
            self.samples.append(sample)

    def take(self):
        with self.lock:
            out, self.samples = self.samples, []
        return out


def run_vu(vu, stop, recorder):
    while time.time() < stop:
        t0 = time.time()
        try:
            method, url, data, label = vu.next_request()
        except requests.exceptions.RequestException as e:
            recorder.add((time.time() - t0, 'etu:evaluation-form', 0, type(e).__name__))
            continue
        t = time.time()
        status, error = 0, None
        try:
            r = vu.session.request(method, url if url.startswith('http') else BASE + url, data=data, timeout=TIMEOUT)
            status = r.status_code
            if status >= 500:
                error = f'HTTP {status}'
            elif 'text/html' in r.headers.get('Content-Type', ''):
                for pattern in ERRORS:
                    m = re.search(pattern, r.text)
                    if m:
                        error = re.sub(r'\s+', ' ', m.group(0))[:160]
                        break
                if label == 'etu:evaluation' and not error and 'Merci' not in r.text:
                    error = 'évaluation non confirmée'
        except requests.exceptions.Timeout:
            error = 'délai dépassé'
        except requests.exceptions.RequestException as e:
            error = type(e).__name__
        recorder.add((time.time() - t, label, status, error))


def cpu_times():
    with open('/proc/stat') as f:
        values = [int(x) for x in f.readline().split()[1:]]
    return sum(values), values[3] + values[4]


def server_sampler(stop, out):
    last = cpu_times()
    while time.time() < stop:
        time.sleep(2)
        now = cpu_times()
        total, idle = now[0] - last[0], now[1] - last[1]
        last = now
        mem = {}
        with open('/proc/meminfo') as f:
            for line in f:
                key, value = line.split(':')
                mem[key] = int(value.split()[0])
        pg = sql("select count(*) filter (where state='active'), count(*) from pg_stat_activity where datname='moodlestress'")
        out.append({'t': time.time(), 'cpu': round(100 * (1 - idle / total), 1) if total else 0,
                    'mem_used_mb': (mem['MemTotal'] - mem['MemAvailable']) // 1024, 'pg': pg})


def summarise(name, users, samples, server, duration):
    durations = sorted(d for d, _, _, _ in samples)
    errors = [e for _, _, _, e in samples if e]
    n = len(durations)
    pct = lambda p: round(durations[min(n - 1, int(n * p))], 3) if n else None
    bylabel = {}
    for d, label, _, e in samples:
        bylabel.setdefault(label, []).append(d)
    entry = {
        'palier': name, 'utilisateurs': users, 'requetes': n, 'pages_par_s': round(n / duration, 1),
        'p50': pct(0.5), 'p95': pct(0.95), 'p99': pct(0.99), 'max': round(durations[-1], 3) if n else None,
        'erreurs': len(errors), 'taux_erreur': round(100 * len(errors) / n, 2) if n else 0,
        'exemples_erreurs': sorted(set(errors))[:5],
        'evaluations': len(bylabel.get('etu:evaluation', [])),
        'cpu_moyen': round(statistics.mean(s['cpu'] for s in server), 1) if server else None,
        'memoire_max_mo': max(s['mem_used_mb'] for s in server) if server else None,
        'pages': {k: {'n': len(v), 'p50': round(statistics.median(v), 3),
                      'p95': round(sorted(v)[min(len(v) - 1, int(len(v) * 0.95))], 3)} for k, v in sorted(bylabel.items())},
    }
    print(f"{name:>8} {users:4d} VU  {entry['pages_par_s']:6.1f} p/s  p50={entry['p50']}s  p95={entry['p95']}s  "
          f"p99={entry['p99']}s  max={entry['max']}s  erreurs={entry['erreurs']} ({entry['taux_erreur']} %)  "
          f"évaluations={entry['evaluations']}  CPU={entry['cpu_moyen']} %  mém={entry['memoire_max_mo']} Mo",
          file=sys.stderr, flush=True)
    if entry['exemples_erreurs']:
        print('         ', entry['exemples_erreurs'], file=sys.stderr, flush=True)
    return entry


def build_vus(count, start=0):
    vus = []
    for i in range(start, start + count):
        r = random.random()
        role = 'etu' if r < 0.75 else ('ens' if r < 0.95 else 'deve')
        vus.append(Vu(role, i))
    with ThreadPoolExecutor(32) as pool:
        return list(pool.map(lambda v: v.login(), vus))


# Évaluations du second semestre ouvertes pour le test.
even = ','.join(str(m['id']) for m in MODULES if int(m['semester'][1:]) % 2 == 0)
sql(f"update mdl_syllabus set evalopen = extract(epoch from now())::int - 60, "
    f"evalclose = extract(epoch from now())::int + 86400 where id in ({even})")

random.seed(20261108)
report = []
if MODE == 'paliers':
    vus = []
    for users in STAGES:
        if len(vus) < users:
            vus += build_vus(users - len(vus), len(vus))
        recorder, server = Recorder(), []
        stop = time.time() + DURATION
        sampler = threading.Thread(target=server_sampler, args=(stop, server))
        sampler.start()
        with ThreadPoolExecutor(users) as pool:
            for vu in vus[:users]:
                pool.submit(run_vu, vu, stop, recorder)
        sampler.join()
        report.append(summarise('palier', users, recorder.take(), server, DURATION))
else:
    vus = build_vus(ENDURANCE_USERS)
    recorder, server = Recorder(), []
    stop = time.time() + ENDURANCE_MINUTES * 60
    sampler = threading.Thread(target=server_sampler, args=(stop, server))
    sampler.start()

    def windows():
        start = time.time()
        while time.time() < stop:
            time.sleep(min(300, max(1, stop - time.time())))
            window = [s for s in server if s['t'] > time.time() - 300]
            report.append(summarise(f'{round((time.time() - start) / 60)} min', ENDURANCE_USERS, recorder.take(), window, 300))

    watcher = threading.Thread(target=windows)
    watcher.start()
    with ThreadPoolExecutor(ENDURANCE_USERS) as pool:
        for vu in vus:
            pool.submit(run_vu, vu, stop, recorder)
    watcher.join()
    sampler.join()

report.append({'reponses_en_base': sql(f"select count(*) from mdl_syllabus_participant where syllabusid in ({even})"),
               'reponses_orphelines': sql(f"select count(*) from mdl_syllabus_response r where syllabusid in ({even}) and "
                                          "not exists (select 1 from mdl_syllabus_answer a where a.responseid = r.id)"),
               'participants_sans_reponse': sql(
                   f"select (select count(*) from mdl_syllabus_participant where syllabusid in ({even})) - "
                   f"(select count(*) from mdl_syllabus_response where syllabusid in ({even}))")})
print(report[-1], file=sys.stderr)
json.dump(report, sys.stdout, indent=1, ensure_ascii=False)
