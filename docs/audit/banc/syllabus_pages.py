#!/usr/bin/env python3
"""Chronométrage des pages des modules syllabus, par rôle, sur le jeu de données à l'échelle d'une
école (syllabus_charge_setup.php).

Usage : python3 syllabus_pages.py site-x.json syllabus-charge.json [répétitions=3] > syllabus-pages.json

Pour chaque page : durée médiane (après une première ouverture qui remplit les caches), taille,
lectures et écritures en base (pied de page de Moodle avec $CFG->perfdebug = 15), erreurs PHP ou
Moodle repérées. Le livret du cursus est mesuré à part : première édition (composition du PDF),
puis livret conservé.
"""
import json
import re
import statistics
import sys
import time

import requests

site = json.load(open(sys.argv[1]))
data = json.load(open(sys.argv[2]))
REPEAT = int(sys.argv[3]) if len(sys.argv) > 3 else 3
BASE, PW = site['wwwroot'], site['password']
STAFF = data['staffcmid']
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


def measure(s, url, repeat=REPEAT, warm=True):
    if warm:
        s.get(BASE + url, timeout=900)
    durations = []
    for _ in range(repeat):
        t = time.time()
        r = s.get(BASE + url, timeout=900)
        durations.append(time.time() - t)
    text = r.text if 'text/html' in r.headers.get('Content-Type', '') else ''
    errors = []
    for pattern in PATTERNS:
        m = re.search(pattern, text)
        if m:
            errors.append(re.sub(r'\s+', ' ', m.group(0))[:300])
    db = re.search(r'DB reads/writes: (\d+)/(\d+)', text)
    return {'url': url, 'status': r.status_code, 'seconds': round(statistics.median(durations), 3),
            'kb': len(r.content) // 1024, 'type': r.headers.get('Content-Type', '').split(';')[0],
            'dbreads': int(db.group(1)) if db else None, 'dbwrites': int(db.group(2)) if db else None, 'errors': errors}


modules = data['modules']
y3 = [m for m in modules if m['year'] == 3]
closed = y3[0]            # S5 : évaluation close avec réponses.
upcoming = y3[6]          # S6 : évaluation pas encore ouverte.
student = data['promos']['3']['students'][5]
domain = data['frameworkid']
comp = None
deve, teacher, etu = login('devea_1'), login(closed['leaders'][0]), login(student)
# Compétence et domaine : les premiers du référentiel du cursus (lus dans la page des référentiels).
page = deve.get(BASE + f'/mod/syllabusstaff/frameworks.php?id={STAFF}&frameworkid={domain}').text
compids = [int(x) for x in re.findall(r'compid=(\d+)', page)]
domainid, leafid = compids[0], compids[1]
promo = data['promos']['3']

scenarios = {
    'etudiant': (etu, [
        f'/mod/syllabusstudent/view.php?id={promo["cmid"]}',
        f'/mod/syllabus/view.php?id={closed["cmid"]}',
        f'/mod/syllabus/view.php?id={upcoming["cmid"]}',
        f'/mod/syllabus/competencies.php?id={closed["cmid"]}',
        f'/mod/syllabus/pdf.php?id={closed["cmid"]}',
    ]),
    'enseignant': (teacher, [
        f'/mod/syllabusstaff/view.php?id={STAFF}',
        f'/mod/syllabus/view.php?id={closed["cmid"]}',
        f'/mod/syllabus/edit.php?id={closed["cmid"]}',
        f'/mod/syllabus/teachers.php?id={closed["cmid"]}',
        f'/mod/syllabus/competencies.php?id={closed["cmid"]}&frameworkid={domain}',
        f'/mod/syllabus/evaluation.php?id={closed["cmid"]}',
        f'/mod/syllabus/results.php?id={closed["cmid"]}',
        f'/mod/syllabus/results.php?id={closed["cmid"]}&download=csv',
        f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={domain}',
        f'/mod/syllabusstaff/progression.php?id={STAFF}&compid={leafid}',
        f'/mod/syllabusstaff/booklet.php?id={STAFF}&selection=y3',
    ]),
    'deve': (deve, [
        f'/mod/syllabusstaff/view.php?id={STAFF}',
        f'/mod/syllabusstaff/all.php?id={STAFF}',
        f'/mod/syllabusstaff/all.php?id={STAFF}&page=1',
        f'/mod/syllabusstaff/all.php?id={STAFF}&evalstate=closed',
        f'/mod/syllabusstaff/review.php?id={STAFF}',
        f'/mod/syllabusstaff/results.php?id={STAFF}',
        f'/mod/syllabusstaff/results.php?id={STAFF}&download=csv',
        f'/mod/syllabusstaff/referential.php?id={STAFF}',
        f'/mod/syllabusstaff/frameworks.php?id={STAFF}',
        f'/mod/syllabusstaff/frameworks.php?id={STAFF}&frameworkid={domain}',
        f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={domain}',
        f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={domain}&download=csv',
        f'/mod/syllabusstaff/progression.php?id={STAFF}&compid={domainid}',
        f'/mod/syllabusstaff/progression.php?id={STAFF}&compid={domainid}&download=csv',
        f'/mod/syllabusstaff/booklet.php?id={STAFF}',
        f'/mod/syllabus/view.php?id={closed["cmid"]}&show=working',
        f'/mod/syllabus/results.php?id={closed["cmid"]}',
        f'/mod/syllabus/index.php?id={closed["course"]}',
    ]),
}

results = []
for role, (session, urls) in scenarios.items():
    for url in urls:
        result = measure(session, url)
        result['role'] = role
        results.append(result)
        print(f"{role:10s} {result['seconds']:7.3f}s {result['status']} {result['kb']:6d}KB "
              f"db={result['dbreads']}/{result['dbwrites']} {url.split('/mod/')[1][:70]} "
              f"{result['errors'] if result['errors'] else ''}", file=sys.stderr, flush=True)

# Livret de tout le cursus : première édition, puis livret conservé.
for label, warm in (('livret du cursus (composé)', False), ('livret du cursus (conservé)', True)):
    url = f'/mod/syllabusstaff/booklet.php?id={STAFF}&selection=all&download=1'
    if not warm:
        import subprocess
        subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-qc',
                        "delete from mdl_files where component='mod_syllabusstaff' and filearea='booklet'"])
    result = measure(deve, url, repeat=1, warm=warm)
    result.update(role='deve', label=label)
    results.append(result)
    print(f"deve       {result['seconds']:7.3f}s {result['status']} {result['kb']:6d}KB {label}", file=sys.stderr, flush=True)

json.dump(results, sys.stdout, indent=1, ensure_ascii=False)
