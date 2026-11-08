#!/usr/bin/env python3
"""Ruée à l'ouverture des évaluations : les 1 000 étudiants du cursus évaluent au même instant le
même module de leur année (200 étudiants par module, en concurrence sur le verrou du module).

Usage : python3 syllabus_ruee.py site-x.json syllabus-charge.json [étudiants par année=200] > ruee.json

Préparation : les évaluations du premier module du second semestre de chaque année sont
remises à zéro (réponses et participations du banc effacées, questions défigées), puis ouvertes.
Parcours de chaque étudiant, vu par son navigateur : « Mes modules », le formulaire d'évaluation,
l'envoi. Mesures : durée totale jusqu'à la dernière réponse, durée médiane et 95e centile par
étudiant, réponses confirmées, erreurs ; puis contrôles en base : une participation et une réponse
par étudiant, aucune réponse en double, aucune réponse sans ses réponses obligatoires.
"""
import json
import random
import re
import statistics
import subprocess
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

import requests

SITE = json.load(open(sys.argv[1]))
DATA = json.load(open(sys.argv[2]))
PERYEAR = int(sys.argv[3]) if len(sys.argv) > 3 else 200
BASE, PW = SITE['wwwroot'], SITE['password']


def sql(query):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', query], capture_output=True,
                          text=True).stdout.strip()


targets = {y: next(m for m in DATA['modules'] if m['year'] == y and int(m['semester'][1:]) % 2 == 0) for y in range(1, 6)}
ids = ','.join(str(m['id']) for m in targets.values())
sql(f"delete from mdl_syllabus_answer where responseid in (select id from mdl_syllabus_response where syllabusid in ({ids}))")
sql(f"delete from mdl_syllabus_response where syllabusid in ({ids})")
sql(f"delete from mdl_syllabus_participant where syllabusid in ({ids})")
sql(f"delete from mdl_syllabus_question where syllabusid in ({ids}) and origin <> 'teacher'")
sql(f"update mdl_syllabus set evalfrozen = 0, evalnotified = 0, evalopen = extract(epoch from now())::int - 5, "
    f"evalclose = extract(epoch from now())::int + 86400 where id in ({ids})")


def login(item):
    year, username = item
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    s.post(BASE + '/login/index.php', data={'username': username, 'password': PW, 'logintoken': tok})
    s.year = year
    return s


people = [(y, u) for y in range(1, 6) for u in DATA['promos'][str(y)]['students'][:PERYEAR]]
with ThreadPoolExecutor(32) as pool:
    sessions = list(pool.map(login, people))
barrier = threading.Barrier(len(sessions))
results = []
lock = threading.Lock()


def run(s):
    rnd = random.Random(id(s))
    barrier.wait()
    t, error, ok = time.time(), None, False
    try:
        s.get(BASE + f'/mod/syllabusstudent/view.php?id={DATA["promos"][str(s.year)]["cmid"]}', timeout=600)
        cmid = targets[s.year]['cmid']
        page = s.get(BASE + f'/mod/syllabus/evaluate.php?id={cmid}', timeout=600).text
        form = re.search(r'<form[^>]*action="([^"]*evaluate\.php[^"]*)"[^>]*>(.*?)</form>', page, re.S)
        fields, radios = {}, {}
        for tag in re.findall(r'<input[^>]*>', form.group(2)):
            kind, name = re.search(r'type="([^"]+)"', tag), re.search(r'name="([^"]+)"', tag)
            value = re.search(r'value="([^"]*)"', tag)
            if kind and name and value:
                if kind.group(1) == 'hidden':
                    fields[name.group(1)] = value.group(1)
                elif kind.group(1) == 'radio':
                    radios.setdefault(name.group(1), []).append(value.group(1))
        for name, values in radios.items():
            fields[name] = rnd.choice(values)
        fields['submitbutton'] = 'Envoyer'
        r = s.post(form.group(1).replace('&amp;', '&'), data=fields, timeout=600)
        ok = 'Merci' in r.text
        if r.status_code >= 500 or 'Exception - ' in r.text:
            error = f'HTTP {r.status_code}'
    except Exception as e:  # noqa: BLE001 - toute erreur compte.
        error = type(e).__name__
    with lock:
        results.append((time.time() - t, ok, error))


start = time.time()
threads = [threading.Thread(target=run, args=(s,)) for s in sessions]
for th in threads:
    th.start()
for th in threads:
    th.join()
total = time.time() - start
durations = sorted(d for d, _, _ in results)
summary = {
    'etudiants': len(sessions), 'modules': len(targets), 'duree_totale_s': round(total, 1),
    'mediane_s': round(statistics.median(durations), 2), 'p95_s': round(durations[int(len(durations) * 0.95) - 1], 2),
    'confirmees': sum(1 for _, ok, _ in results if ok), 'erreurs': sorted({e for *_, e in results if e}),
    'participations': sql(f"select count(*) from mdl_syllabus_participant where syllabusid in ({ids})"),
    'reponses': sql(f"select count(*) from mdl_syllabus_response where syllabusid in ({ids})"),
    'doublons': sql(f"select count(*) from (select syllabusid, userid from mdl_syllabus_participant "
                    f"where syllabusid in ({ids}) group by 1, 2 having count(*) > 1) x"),
    'questions_figees': sql(f"select count(*) from mdl_syllabus where id in ({ids}) and evalfrozen = 1"),
    'obligatoires_manquantes': sql(f"""select count(*) from mdl_syllabus_question q
        join mdl_syllabus_response r on r.syllabusid = q.syllabusid
        where q.syllabusid in ({ids}) and q.required = 1
        and not exists (select 1 from mdl_syllabus_answer a where a.responseid = r.id and a.questionid = q.id)"""),
}
print(json.dumps(summary, ensure_ascii=False))
