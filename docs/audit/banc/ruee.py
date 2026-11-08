#!/usr/bin/env python3
"""Ruée à l'ouverture des inscriptions : N étudiants d'une promotion s'inscrivent au même instant,
chacun avec le parcours complet vu par son navigateur.

Usage : python3 ruee.py site-<tag>.json <ancien|direct> <premier étudiant> <nombre>

- ancien : liste du catalogue, page de confirmation, envoi (puis tableau de bord) ;
- direct : liste du catalogue, envoi depuis la ligne de l'EP (puis retour à la liste).
Mesure : durée totale jusqu'à la dernière inscription, durée médiane et 95e centile par étudiant,
inscriptions réussies, requêtes servies.
"""
import json
import random
import re
import statistics
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor

import requests

site = json.load(open(sys.argv[1]))
MODE, FIRST, COUNT = sys.argv[2], int(sys.argv[3]), int(sys.argv[4])
BASE, CM, T = site['wwwroot'], site['cmid'], site['tag']


def login(i):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    r = s.post(BASE + '/login/index.php', data={'username': f'etu{T}_{i}', 'password': site['password'],
                                                 'logintoken': tok})
    s.sesskey = re.search(r'"sesskey":"([^"]+)"', r.text).group(1)
    return s


with ThreadPoolExecutor(32) as pool:
    sessions = list(pool.map(login, range(FIRST, FIRST + COUNT)))
barrier = threading.Barrier(COUNT)
results = []
lock = threading.Lock()


def run(s):
    random.seed(id(s))
    barrier.wait()
    t, requests_done, ok = time.time(), 0, False
    page = random.randint(0, 4)
    r = s.get(BASE + f'/mod/ep/catalog.php?id={CM}&page={page}', timeout=600)
    requests_done += 1
    # Un bouton « S'inscrire » (activityid) par EP, dans le formulaire unique de la page.
    forms = [BASE + f'/mod/ep/catalog.php?id={CM}&action=register&activityid={a}'
             for a in re.findall(r'<button[^>]*name="activityid" value="(\d+)"', r.text)]
    if forms:
        url = random.choice(forms)
        if MODE == 'ancien':
            r = s.get(url, timeout=600)
            requests_done += 1
            r = s.post(url, data={'sesskey': s.sesskey, 'studyyear': 3}, timeout=600)
        else:
            r = s.post(url, data={'sesskey': s.sesskey, 'studyyear': 3, 'fromlist': 1, 'page': page, 'search': ''},
                       timeout=600)
        requests_done += 1 + len(r.history)
        ok = 'alert-success' in r.text
    with lock:
        results.append((time.time() - t, requests_done, ok))


start = time.time()
threads = [threading.Thread(target=run, args=(s,)) for s in sessions]
for th in threads:
    th.start()
for th in threads:
    th.join()
total = time.time() - start
durations = sorted(d for d, _, _ in results)
summary = {'mode': MODE, 'etudiants': COUNT, 'duree_totale_s': round(total, 1),
           'mediane_s': round(statistics.median(durations), 2), 'p95_s': round(durations[int(len(durations) * 0.95) - 1], 2),
           'inscrits': sum(1 for *_, ok in results if ok), 'requetes': sum(n for _, n, _ in results)}
print(json.dumps(summary, ensure_ascii=False))
