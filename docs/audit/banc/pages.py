#!/usr/bin/env python3
"""Chronométrage des pages de mod_ep, par rôle, sur le jeu de données du banc.

Usage : python3 pages.py site-<tag>.json [répétitions=3] > pages-<tag>.json

Pour chaque page : durée médiane, taille, nombre de lectures et d'écritures en base (pied de
page de Moodle avec $CFG->perfdebug = 15), et erreurs PHP ou Moodle repérées dans la réponse.
Le résumé lisible est écrit sur la sortie d'erreur, le détail en JSON sur la sortie standard.
"""
import json
import re
import statistics
import sys
import time

import requests

site = json.load(open(sys.argv[1]))
REPEAT = int(sys.argv[2]) if len(sys.argv) > 2 else 3
BASE = site['wwwroot']
PW = site['password']
CM = site['cmid']
TAG = site['tag']

PATTERNS = [r'Fatal error[^<]*', r'Maximum execution time[^<]*', r'class="errormessage"[^>]*>[^<]*',
            r'Debug info:[^<]*', r'Error code: [a-z_]+', r'Exception - [^<]*', r'<b>Warning</b>:[^<]*',
            r'<b>Notice</b>:[^<]*', r'<b>Deprecated</b>:[^<]*', r'Coding error detected[^<]*',
            r'class="[^"]*debuggingmessage[^"]*"[^>]*>[^<]*']


def login(user):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    r = s.post(BASE + '/login/index.php', data={'username': user, 'password': PW, 'logintoken': tok})
    m = re.search(r'"sesskey":"([^"]+)"', r.text) or re.search(r'"sesskey":"([^"]+)"', s.get(BASE + '/my/').text)
    s.sesskey = m.group(1)
    return s


def measure(s, method, url, data=None):
    durations = []
    for _ in range(REPEAT):
        t = time.time()
        r = s.request(method, BASE + url, data=data, timeout=900, allow_redirects=True)
        durations.append(time.time() - t)
    text = r.text if 'text/html' in r.headers.get('Content-Type', '') else ''
    errors = []
    for pattern in PATTERNS:
        m = re.search(pattern, text)
        if m:
            errors.append(re.sub(r'\s+', ' ', m.group(0))[:300])
    db = re.search(r'DB reads/writes: (\d+)/(\d+)', text)
    return {
        'url': url, 'status': r.status_code, 'seconds': round(statistics.median(durations), 3),
        'kb': len(r.content) // 1024, 'type': r.headers.get('Content-Type', '').split(';')[0],
        'dbreads': int(db.group(1)) if db else None, 'dbwrites': int(db.group(2)) if db else None, 'errors': errors,
    }


student = site['students'][3]   # 4e étudiant : année 4, dossier complet.
activity = site['activities'][9]
# EP dont le premier enseignant est responsable : le n° k a pour responsable l'enseignant k mod n.
owned = site['activities'][len(site['teachers']) - 1] if len(site['activities']) >= len(site['teachers']) \
    else site['activities'][0]
deve = login(f'deve{TAG}_1')
teacher = login(f'ens{TAG}_1')
etu = login(f'etu{TAG}_4')

scenarios = {
    'etudiant': (etu, [
        f'/mod/ep/view.php?id={CM}',
        f'/mod/ep/catalog.php?id={CM}',
        f'/mod/ep/declare.php?id={CM}',
        f'/mod/ep/supplement.php?id={CM}',
        f'/mod/ep/syllabus.php?id={CM}&activityid={activity}',
        f'/mod/ep/syllabus_pdf.php?id={CM}&activityid={activity}',
    ]),
    'enseignant': (teacher, [
        f'/mod/ep/view.php?id={CM}',
        f'/mod/ep/credits.php?id={CM}',
        f'/mod/ep/dashboard.php?id={CM}',
        f'/mod/ep/proposals.php?id={CM}',
        f'/mod/ep/supplement_items.php?id={CM}',
        f'/mod/ep/participants.php?id={CM}&activityid={owned}',
    ]),
    'deve': (deve, [
        f'/mod/ep/view.php?id={CM}',
        f'/mod/ep/dashboard.php?id={CM}',
        f'/mod/ep/dashboard.php?id={CM}&search=Banc&page=5',
        f'/mod/ep/dashboard.php?id={CM}&studentid={student}',
        f'/mod/ep/credits.php?id={CM}',
        f'/mod/ep/credits.php?id={CM}&status=0&page=3',
        f'/mod/ep/catalog.php?id={CM}',
        f'/mod/ep/activities.php?id={CM}',
        f'/mod/ep/participants.php?id={CM}&activityid={activity}',
        f'/mod/ep/types.php?id={CM}',
        f'/mod/ep/year_requirements.php?id={CM}',
        f'/mod/ep/administration.php?id={CM}',
        f'/mod/ep/proposals.php?id={CM}',
        f'/mod/ep/year_validation.php?id={CM}',
        f'/mod/ep/year_validation.php?id={CM}&studyyear=4&page=3',
        f'/mod/ep/supplement_items.php?id={CM}',
        f'/mod/ep/supplement_issue.php?id={CM}',
        f'/mod/ep/supplement.php?id={CM}&userid={student}',
        f'/mod/ep/supplement.php?id={CM}&userid={student}&action=preview&lang=fr',
        f'/mod/ep/supplement_settings.php?id={CM}',
        f'/mod/ep/rollover.php?id={CM}',
        f'/mod/ep/settings_transfer.php?id={CM}',
        f'/mod/ep/archive.php?id={CM}',
        f'/mod/ep/import_catalog.php?id={CM}',
        f'/mod/ep/export.php?id={CM}&mode=students',
        f'/mod/ep/export.php?id={CM}&mode=credits',
        f'/mod/ep/booklet.php?id={CM}',
        f'/mod/ep/index.php?id={site["courseid"]}',
    ]),
}

results = []
for role, (session, urls) in scenarios.items():
    for url in urls:
        result = measure(session, 'GET', url)
        result['role'] = role
        results.append(result)
        print(f"{role:10s} {result['seconds']:7.2f}s {result['status']} {result['kb']:6d}KB "
              f"db={result['dbreads']}/{result['dbwrites']} {url.split('/mod/ep/')[1][:60]} "
              f"{result['errors'] if result['errors'] else ''}", file=sys.stderr, flush=True)

# Écritures lourdes de la DEVE : export complet et paramétrage JSON (une fois chacune).
for label, url, data in [
    ('archive', f'/mod/ep/archive.php?id={CM}', {'action': 'export', 'sesskey': deve.sesskey}),
    ('parametrage', f'/mod/ep/settings_transfer.php?id={CM}', {'action': 'export', 'withcatalog': 1, 'sesskey': deve.sesskey}),
]:
    t = time.time()
    r = deve.post(BASE + url, data=data, timeout=900)
    result = {'role': 'deve', 'url': url + ' (' + label + ')', 'status': r.status_code,
              'seconds': round(time.time() - t, 3), 'kb': len(r.content) // 1024,
              'type': r.headers.get('Content-Type', '').split(';')[0], 'dbreads': None, 'dbwrites': None, 'errors': []}
    results.append(result)
    print(f"deve       {result['seconds']:7.2f}s {r.status_code} {result['kb']:6d}KB {label}", file=sys.stderr, flush=True)

json.dump(results, sys.stdout, indent=1, ensure_ascii=False)
