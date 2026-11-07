#!/usr/bin/env python3
"""Traitements lourds des modules syllabus lancés ensemble pendant une charge interactive.

Usage : python3 syllabus_lourds.py site-x.json syllabus-charge.json > syllabus-lourds.json

Pendant que 50 utilisateurs virtuels ouvrent leurs pages (syllabus_stress.py, palier de 50,
150 s), lancés au même instant au bout de 30 s :
- la tâche d'ouverture des évaluations du second semestre des cinq années (questions figées,
  étudiants prévenus : plusieurs milliers de messages), en ligne de commande ;
- le livret de tout le cursus par la DEVE, et le livret de chaque année par un enseignant
  (livrets conservés effacés auparavant) ;
- les exports CSV de la matrice, des résultats comparés et de la progression d'un domaine.
Pour chacun : durée, taille, statut ; et l'effet sur les temps de réponse interactifs (sortie du
palier, à comparer au palier de 50 sans traitement).
"""
import json
import os
import re
import subprocess
import sys
import threading
import time

import requests

SITE = json.load(open(sys.argv[1]))
DATA = json.load(open(sys.argv[2]))
BASE, PW = SITE['wwwroot'], SITE['password']
STAFF = DATA['staffcmid']
HERE = os.path.dirname(os.path.abspath(__file__))
MOODLE = '/home/user/moodle-stress/moodle'


def sql(query):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', query], capture_output=True,
                          text=True).stdout.strip()


def login(user):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    s.post(BASE + '/login/index.php', data={'username': user, 'password': PW, 'logintoken': tok})
    return s


even = [m for m in DATA['modules'] if int(m['semester'][1:]) % 2 == 0]
ids = ','.join(str(m['id']) for m in even)
sql(f"update mdl_syllabus set evalnotified = 0, evalopen = extract(epoch from now())::int + 40, "
    f"evalclose = extract(epoch from now())::int + 86400 where id in ({ids})")
sql("delete from mdl_files where component = 'mod_syllabusstaff' and filearea = 'booklet'")
before = int(sql("select count(*) from mdl_notifications where component = 'mod_syllabus'"))
domain = sql(f"select id from mdl_syllabus_comp where frameworkid = {DATA['frameworkid']} and parentid = 0 order by id limit 1")
# Une session par traitement : Moodle sert l'une après l'autre les requêtes d'une même session
# (verrou de session), ce qui fausserait des traitements lancés ensemble par un seul compte.
deve, deve2, deve3, deve4 = (login(f'devea_{i}') for i in range(1, 5))
teachers = {y: login(next(m for m in DATA['modules'] if m['year'] == y)['leaders'][0]) for y in range(1, 6)}

load = subprocess.Popen([sys.executable, os.path.join(HERE, 'syllabus_stress.py'), 'paliers', sys.argv[1], sys.argv[2]],
                        env=dict(os.environ, PALIERS='50', DUREE='150'), stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                        text=True)
time.sleep(45)   # Connexions des utilisateurs virtuels, puis 30 s de charge seule.
results = []
lock = threading.Lock()


def timed(label, func):
    t = time.time()
    try:
        detail = func()
    except Exception as e:  # noqa: BLE001
        detail = f'erreur : {type(e).__name__} {e}'
    with lock:
        results.append({'traitement': label, 'duree_s': round(time.time() - t, 2), 'detail': detail})
    print(f'{label:45s} {time.time() - t:7.2f} s  {detail}', file=sys.stderr, flush=True)


def get(session, url):
    r = session.get(BASE + url, timeout=900)
    return f'HTTP {r.status_code}, {len(r.content) // 1024} Ko, {r.headers.get("Content-Type", "").split(";")[0]}'


def task():
    out = subprocess.run(['sudo', '-u', 'www-data', 'php', 'admin/cli/scheduled_task.php',
                          '--execute=\\mod_syllabus\\task\\open_evaluations'], cwd=MOODLE, capture_output=True, text=True)
    sent = int(sql("select count(*) from mdl_notifications where component = 'mod_syllabus'")) - before
    frozen = sql(f"select count(*) from mdl_syllabus where id in ({ids}) and evalfrozen = 1")
    return f'{sent} messages, {frozen} modules figés, code {out.returncode}'


jobs = [('tâche d\'ouverture des évaluations (30 modules)', task),
        ('livret du cursus (60 fiches, DEVE)', lambda: get(deve, f'/mod/syllabusstaff/booklet.php?id={STAFF}'
                                                                 '&selection=all&download=1'))]
for y, s in teachers.items():
    jobs.append((f'livret de l\'année {y} (enseignant)',
                 lambda s=s, y=y: get(s, f'/mod/syllabusstaff/booklet.php?id={STAFF}&selection=y{y}&download=1')))
jobs += [('export CSV de la matrice', lambda: get(deve2, f'/mod/syllabusstaff/competencies.php?id={STAFF}'
                                                         f'&frameworkid={DATA["frameworkid"]}&download=csv')),
         ('export CSV des résultats comparés', lambda: get(deve3, f'/mod/syllabusstaff/results.php?id={STAFF}&download=csv')),
         ('progression d\'un domaine (CSV)', lambda: get(deve4, f'/mod/syllabusstaff/progression.php?id={STAFF}'
                                                                f'&compid={domain}&download=csv'))]
time.sleep(5)   # Fenêtres ouvertes (ouverture fixée à +40 s).
threads = [threading.Thread(target=timed, args=job) for job in jobs]
for th in threads:
    th.start()
for th in threads:
    th.join()
out, err = load.communicate()
print(err, file=sys.stderr)
json.dump({'traitements': results, 'charge': json.loads(out)}, sys.stdout, indent=1, ensure_ascii=False)
