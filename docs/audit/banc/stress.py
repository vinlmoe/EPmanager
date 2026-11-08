#!/usr/bin/env python3
"""Test de stress de mod_ep : montée en charge par paliers, ou endurance à charge constante.

Usage :
  python3 stress.py paliers  <synthese_cmid> site-a.json [site-b.json ...] > stress.json
  python3 stress.py endurance <synthese_cmid> <utilisateurs> <minutes> site-a.json [...] > endurance.json

Des utilisateurs virtuels (70 % d'étudiants, 22 % d'enseignants, 8 % de la DEVE, répartis sur
toutes les promotions données) ouvrent leurs pages en boucle, sans temps de réflexion : c'est la
charge maximale que chacun peut produire. Une part des étudiants s'inscrit à un EP (écriture).
Paliers par défaut : 25, 50, 100, 200, 300, 400 utilisateurs simultanés, 60 s chacun (variable
PALIERS="25,50,100" et DUREE=60 pour les changer).

Pour chaque palier : pages par seconde, durées (médiane, 95e et 99e centiles, maximum), erreurs
(HTTP 5xx, erreurs PHP ou Moodle dans la page, délais dépassés), et côté serveur la charge CPU,
la mémoire, les processus PHP-FPM actifs et les connexions PostgreSQL. Résumé lisible sur la
sortie d'erreur, détail JSON sur la sortie standard.
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
SYNTHESIS = int(sys.argv[2])
if MODE == 'endurance':
    ENDURANCE_USERS, ENDURANCE_MINUTES = int(sys.argv[3]), float(sys.argv[4])
    SITEFILES = sys.argv[5:]
else:
    SITEFILES = sys.argv[3:]
SITES = [json.load(open(f)) for f in SITEFILES]
BASE = SITES[0]['wwwroot']
PW = SITES[0]['password']
STAGES = [int(x) for x in os.environ.get('PALIERS', '25,50,100,200,300,400').split(',')]
DURATION = float(os.environ.get('DUREE', '60'))
TIMEOUT = 60

ERRORS = [r'Fatal error[^<]*', r'Exception - [^<]*', r'Coding error detected[^<]*', r'<b>Warning</b>:[^<]*',
          r'Error code: dml[a-z_]+', r'Maximum execution time[^<]*', r'Debug info:[^<]*']


class Vu:
    """Utilisateur virtuel : une session connectée, un rôle, une promotion."""

    def __init__(self, role, site, index):
        self.role, self.site, self.index = role, site, index
        tag = site['tag']
        if role == 'etu':
            # Étudiants au-delà des 400 premiers : ceux-là ont servi aux autres tests.
            self.user = f'etu{tag}_{400 + index % (len(site["students"]) - 400)}'
        elif role == 'ens':
            self.user = f'ensa_{1 + index % 150}'
        else:
            self.user = f'devea_{1 + index % 4}'
        self.session = requests.Session()
        self.session.mount('http://', HTTPAdapter(pool_connections=1, pool_maxsize=1))
        self.sesskey = None

    def login(self):
        r = self.session.get(BASE + '/login/index.php', timeout=TIMEOUT)
        token = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
        r = self.session.post(BASE + '/login/index.php', timeout=TIMEOUT,
                              data={'username': self.user, 'password': PW, 'logintoken': token})
        m = re.search(r'"sesskey":"([^"]+)"', r.text) or re.search(r'"sesskey":"([^"]+)"',
                                                                   self.session.get(BASE + '/my/', timeout=TIMEOUT).text)
        self.sesskey = m.group(1)
        return self

    def next_request(self):
        """Page suivante de la boucle, tirée selon le rôle : (méthode, url, données, étiquette)."""
        s, cm = self.site, self.site['cmid']
        activity = random.choice(s['activities'])
        if self.role == 'etu':
            choice = random.random()
            if choice < 0.05:
                return ('POST', f'/mod/ep/catalog.php?id={cm}&action=register&activityid={activity}',
                        {'sesskey': self.sesskey, 'studyyear': 3}, 'etu:inscription')
            pages = [(f'/mod/ep/view.php?id={cm}', 'etu:tableau'), (f'/mod/ep/catalog.php?id={cm}&page={random.randint(0, 4)}',
                     'etu:catalogue'), (f'/mod/ep/supplement.php?id={cm}', 'etu:supplement'),
                     (f'/mod/ep/syllabus.php?id={cm}&activityid={activity}', 'etu:syllabus')]
        elif self.role == 'ens':
            owned = s['activities'][(self.index % 150) - 1] if len(s['activities']) >= 150 else activity
            pages = [(f'/mod/ep/credits.php?id={cm}', 'ens:validation'), (f'/mod/ep/dashboard.php?id={cm}', 'ens:pilotage'),
                     (f'/mod/ep/participants.php?id={cm}&activityid={owned}', 'ens:participants'),
                     (f'/mod/epsynthesis/todo.php?id={SYNTHESIS}', 'ens:synthese-a-traiter'),
                     (f'/mod/epsynthesis/entries.php?id={SYNTHESIS}', 'ens:synthese-validation'),
                     (f'/mod/epsynthesis/dashboard.php?id={SYNTHESIS}', 'ens:synthese-pilotage')]
        else:
            pages = [(f'/mod/ep/dashboard.php?id={cm}&page={random.randint(0, 40)}', 'deve:pilotage'),
                     (f'/mod/ep/credits.php?id={cm}&status=0&page={random.randint(0, 30)}', 'deve:validation'),
                     (f'/mod/ep/credits.php?id={cm}&search=Etu%20{random.randint(1, 99)}', 'deve:recherche'),
                     (f'/mod/ep/year_validation.php?id={cm}&studyyear={random.randint(1, 6)}', 'deve:annees'),
                     (f'/mod/ep/supplement_items.php?id={cm}', 'deve:supplement'),
                     (f'/mod/ep/activities.php?id={cm}', 'deve:catalogue')]
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
        method, url, data, label = vu.next_request()
        t = time.time()
        status, error = 0, None
        try:
            r = vu.session.request(method, BASE + url, data=data, timeout=TIMEOUT)
            status = r.status_code
            if status >= 500:
                error = f'HTTP {status}'
            elif 'text/html' in r.headers.get('Content-Type', ''):
                for pattern in ERRORS:
                    m = re.search(pattern, r.text)
                    if m:
                        error = re.sub(r'\s+', ' ', m.group(0))[:160]
                        break
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
    """Toutes les deux secondes : CPU, mémoire, PHP-FPM actifs, connexions PostgreSQL."""
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
        fpm = subprocess.run(['pgrep', '-c', '-f', 'php-fpm: pool'], capture_output=True, text=True).stdout.strip()
        busy = subprocess.run(['bash', '-c', "ps -o stat=,pcpu= -C php-fpm8.3 | awk '$2>1' | wc -l"],
                              capture_output=True, text=True).stdout.strip()
        pg = subprocess.run(['sudo', '-u', 'postgres', 'psql', '-tAc',
                             "select count(*) filter (where state='active'), count(*) from pg_stat_activity"
                             " where datname='moodlestress'"], capture_output=True, text=True).stdout.strip()
        out.append({'t': time.time(), 'cpu': round(100 * (1 - idle / total), 1) if total else 0,
                    'mem_used_mb': (mem['MemTotal'] - mem['MemAvailable']) // 1024, 'fpm': fpm, 'fpm_busy': busy,
                    'pg': pg})


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
        'cpu_moyen': round(statistics.mean(s['cpu'] for s in server), 1) if server else None,
        'memoire_max_mo': max(s['mem_used_mb'] for s in server) if server else None,
        'pages': {k: {'n': len(v), 'p50': round(statistics.median(v), 3),
                      'p95': round(sorted(v)[min(len(v) - 1, int(len(v) * 0.95))], 3)} for k, v in sorted(bylabel.items())},
        'serveur': server,
    }
    print(f"{name:>12} {users:4d} VU  {entry['pages_par_s']:6.1f} p/s  p50={entry['p50']}s  p95={entry['p95']}s  "
          f"p99={entry['p99']}s  max={entry['max']}s  erreurs={entry['erreurs']} ({entry['taux_erreur']} %)  "
          f"CPU={entry['cpu_moyen']} %  mém={entry['memoire_max_mo']} Mo", file=sys.stderr, flush=True)
    return entry


def build_vus(count, start=0):
    vus = []
    for i in range(start, start + count):
        r = random.random()
        role = 'etu' if r < 0.70 else ('ens' if r < 0.92 else 'deve')
        vus.append(Vu(role, SITES[i % len(SITES)], i))
    with ThreadPoolExecutor(32) as pool:
        return list(pool.map(lambda v: v.login(), vus))


random.seed(20261107)
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
        report.append(summarise(f'palier', users, recorder.take(), server, DURATION))
else:
    vus = build_vus(ENDURANCE_USERS)
    recorder, server = Recorder(), []
    total = ENDURANCE_MINUTES * 60
    stop = time.time() + total
    sampler = threading.Thread(target=server_sampler, args=(stop, server))
    sampler.start()

    def windows():
        # Une ligne de résumé toutes les cinq minutes, pour voir une dérive éventuelle.
        start = time.time()
        while time.time() < stop:
            time.sleep(min(300, max(1, stop - time.time())))
            chunk = recorder.take()
            window = [s for s in server if s['t'] > time.time() - 300]
            report.append(summarise(f'{round((time.time() - start) / 60)} min', ENDURANCE_USERS, chunk, window, 300))

    watcher = threading.Thread(target=windows)
    watcher.start()
    with ThreadPoolExecutor(ENDURANCE_USERS) as pool:
        for vu in vus:
            pool.submit(run_vu, vu, stop, recorder)
    watcher.join()
    sampler.join()

json.dump(report, sys.stdout, indent=1, ensure_ascii=False)
