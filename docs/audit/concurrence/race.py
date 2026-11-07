#!/usr/bin/env python3
"""Courses concurrentes sur mod_ep, par HTTP, contre le banc (vrai Moodle, serveur à plusieurs
workers). Chaque scénario part d'un état préparé par race_prep.php, lance ses requêtes ensemble,
puis race_prep.php check vérifie les invariants.

Usage : python3 race.py ../banc/site-<tag>.json > race-<tag>.json
Résumé lisible sur la sortie d'erreur, détail JSON sur la sortie standard.
"""
import json
import re
import statistics
import subprocess
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

import requests

SITEFILE = sys.argv[1]
site = json.load(open(SITEFILE))
BASE = site['wwwroot']
PW = site['password']
CM = site['cmid']
TAG = site['tag']
HERE = __file__.rsplit('/', 1)[0] if '/' in __file__ else '.'

ERRORS = [r'Fatal error[^<]*', r'Exception - [^<]*', r'Coding error detected[^<]*', r'<b>Warning</b>:[^<]*',
          r'Error code: dml[a-z_]+', r'Maximum execution time[^<]*']


def php(mode):
    out = subprocess.run(['php', f'{HERE}/race_prep.php', SITEFILE, mode], capture_output=True, text=True, check=True)
    return json.loads(out.stdout)


def login(user):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    r = s.post(BASE + '/login/index.php', data={'username': user, 'password': PW, 'logintoken': tok})
    m = re.search(r'"sesskey":"([^"]+)"', r.text) or re.search(r'"sesskey":"([^"]+)"', s.get(BASE + '/my/').text)
    s.sesskey = m.group(1)
    s.user = user
    return s


def logins(users, workers=16):
    with ThreadPoolExecutor(workers) as pool:
        return list(pool.map(login, users))


def fire(calls, workers=64):
    """Lance toutes les requêtes ensemble (barrière), renvoie [(durée, statut, texte final)]."""
    barrier = threading.Barrier(len(calls))

    def run(call):
        session, method, url, data = call
        barrier.wait()
        t = time.time()
        r = session.request(method, BASE + url, data=data, timeout=600, allow_redirects=True)
        return time.time() - t, r.status_code, r.text

    with ThreadPoolExecutor(max(workers, len(calls))) as pool:
        return list(pool.map(run, calls))


def summary(name, results, extra=None):
    durations = sorted(d for d, _, _ in results)
    errors = []
    for _, status, text in results:
        for pattern in ERRORS:
            m = re.search(pattern, text)
            if m:
                errors.append(re.sub(r'\s+', ' ', m.group(0))[:200])
        if status >= 500:
            errors.append(f'HTTP {status}')
    entry = {
        'scenario': name, 'requests': len(results),
        'p50': round(statistics.median(durations), 3) if durations else None,
        'p95': round(durations[int(len(durations) * 0.95) - 1], 3) if durations else None,
        'max': round(durations[-1], 3) if durations else None,
        'errors': errors[:10], 'errorcount': len(errors),
    }
    entry.update(extra or {})
    print(f"{name:28s} n={entry['requests']:4d} p50={entry['p50']}s p95={entry['p95']}s max={entry['max']}s "
          f"erreurs={entry['errorcount']} {json.dumps(extra or {}, ensure_ascii=False)}", file=sys.stderr, flush=True)
    return entry


report = []
targets = php('prep')
deve = logins([f'deve{TAG}_1', f'deve{TAG}_2'])
teacher = login(f'ens{TAG}_1')

# 1. Ruée : 200 étudiants s'inscrivent ensemble à un EP de 30 places.
students = logins([f'etu{TAG}_{i}' for i in range(1, 201)])
url = f'/mod/ep/catalog.php?id={CM}&action=register&activityid={targets["rush"]}'
results = fire([(s, 'POST', url, {'sesskey': s.sesskey, 'studyyear': 3}) for s in students])
check = php('check')
report.append(summary('ruee_200_pour_30_places', results, {'inscrits': check['RUSH']['actives'],
                                                           'doublons': check['RUSH']['doubles']}))

# 2. Double-clic : un étudiant envoie dix fois la même inscription en même temps.
url = f'/mod/ep/catalog.php?id={CM}&action=register&activityid={targets["click"]}'
results = fire([(students[0], 'POST', url, {'sesskey': students[0].sesskey, 'studyyear': 3}) for _ in range(10)])
check = php('check')
report.append(summary('double_clic_x10', results, {'inscriptions': check['CLICK']['actives']}))

# 3. Décisions simultanées : le responsable et la DEVE tranchent les mêmes demandes en même temps,
# puis deux membres de la DEVE tranchent ensemble cent déclarations.
calls = []
for creditid in targets['ownercredits']:
    url = f'/mod/ep/validate.php?id={CM}&creditid={creditid}'
    calls.append((teacher, 'POST', url, {'sesskey': teacher.sesskey, 'validatecredit': 1, 'retainedects': 1}))
    calls.append((deve[0], 'POST', url, {'sesskey': deve[0].sesskey, 'rejectcredit': 1, 'validatorcomment': 'course'}))
results = fire(calls)
calls = []
for creditid in targets['devecredits']:
    url = f'/mod/ep/validate.php?id={CM}&creditid={creditid}'
    for k, session in enumerate(deve):
        data = {'sesskey': session.sesskey, 'validatorcomment': f'DEVE {k}'}
        data['validatecredit' if k == 0 else 'rejectcredit'] = 1
        data['retainedects'] = 1
        calls.append((session, 'POST', url, data))
results += fire(calls)
check = php('check')
report.append(summary('decisions_simultanees', results, {'decisions_en_double': check['decisions_en_double'],
                                                         'tranchees_sans_decision': check['tranchees_sans_decision']}))

# 4. Émissions simultanées : deux membres de la DEVE émettent le supplément des mêmes dix
# étudiants en même temps (émission forcée, en français et en anglais).
calls = []
for studentid in site['students'][:10]:
    for k, session in enumerate(deve):
        calls.append((session, 'POST', f'/mod/ep/supplement_issue.php?id={CM}',
                      {'sesskey': session.sesskey, 'action': 'issue', 'userid': studentid, 'force': 1,
                       'lang': 'fr' if k == 0 else 'en'}))
results = fire(calls)
check = php('check')
report.append(summary('emissions_simultanees', results, {
    'en_vigueur_multiples': check['supplements_en_vigueur_multiples'], 'numeros_en_double': check['numeros_en_double']}))

# 5. Charge mixte pendant 60 s : 40 étudiants consultent leur dossier et le catalogue, 10
# enseignants leurs listes, la DEVE son pilotage et sa file, pendant que la DEVE exporte.
browsers = students[20:60] + logins([f'ens{TAG}_{i}' for i in range(2, 12)]) + deve
pages = {
    'etu': ['/mod/ep/view.php?id={cm}', '/mod/ep/catalog.php?id={cm}', '/mod/ep/supplement.php?id={cm}'],
    'ens': ['/mod/ep/credits.php?id={cm}', '/mod/ep/dashboard.php?id={cm}', '/mod/ep/supplement_items.php?id={cm}'],
    'deve': ['/mod/ep/dashboard.php?id={cm}', '/mod/ep/credits.php?id={cm}', '/mod/ep/year_validation.php?id={cm}'],
}
stop = time.time() + 60
lock = threading.Lock()
mixed = []


def browse(session):
    kind = re.match(r'(etu|ens|deve)', session.user).group(1)
    i = 0
    while time.time() < stop:
        url = pages[kind][i % 3].format(cm=CM)
        t = time.time()
        r = session.get(BASE + url, timeout=600)
        with lock:
            mixed.append((time.time() - t, r.status_code, r.text))
        i += 1


def export():
    t = time.time()
    r = deve[1].post(BASE + f'/mod/ep/archive.php?id={CM}', data={'action': 'export', 'sesskey': deve[1].sesskey},
                     timeout=600)
    return time.time() - t, r.status_code, len(r.content)


with ThreadPoolExecutor(len(browsers) + 1) as pool:
    exported = pool.submit(export)
    list(pool.map(browse, browsers))
    exportresult = exported.result()
check = php('check')
report.append(summary('charge_mixte_60s', mixed, {
    'export_s': round(exportresult[0], 1), 'export_statut': exportresult[1], 'pages_par_s': round(len(mixed) / 60, 1),
    'verrous_restants': check['verrous_restants'], 'transactions_ouvertes': check['transactions_ouvertes']}))

json.dump({'check': check, 'report': report}, sys.stdout, indent=1, ensure_ascii=False)
