#!/usr/bin/env python3
"""Traitements lourds simultanés pendant une charge interactive.

Usage : python3 lourds.py <synthese_cmid> <archive.zip> site-a.json ... site-f.json > lourds.json

Lancés ensemble, pendant que 50 utilisateurs ouvrent leurs pages (stress.py, palier de 50) :
- un export complet (ZIP) par membre de la DEVE sur trois promotions ;
- le livret PDF de deux promotions (livret conservé effacé auparavant) ;
- l'export CSV détaillé de deux promotions ;
- l'émission groupée des suppléments de 300 étudiants d'une promotion (tâche ad hoc, exécutée
  par le planificateur en ligne de commande) ;
- la restauration d'une archive de 60 000 crédits dans une activité vide (ligne de commande).
Pour chacun : durée, taille, statut ; côté serveur, la mémoire résidente maximale des processus
PHP, et l'effet sur les temps de réponse interactifs (comparé au palier de 50 sans traitement).
"""
import json
import os
import re
import subprocess
import sys
import threading
import time

import requests

SYN = sys.argv[1]
ARCHIVE = sys.argv[2]
SITEFILES = sys.argv[3:]
SITES = [json.load(open(f)) for f in SITEFILES]
BASE, PW = SITES[0]['wwwroot'], SITES[0]['password']
HERE = os.path.dirname(os.path.abspath(__file__))
MOODLE = '/home/user/moodle-stress/moodle'


def login(user):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    r = s.post(BASE + '/login/index.php', data={'username': user, 'password': PW, 'logintoken': tok})
    s.sesskey = re.search(r'"sesskey":"([^"]+)"', r.text or s.get(BASE + '/my/').text).group(1)
    return s


def php(script):
    return subprocess.run(['sudo', '-u', 'www-data', 'php', '-r', "define('CLI_SCRIPT', true); require '"
                           + MOODLE + "/config.php'; " + script], capture_output=True, text=True)


results = []
lock = threading.Lock()


def record(name, start, **extra):
    with lock:
        results.append(dict(name=name, seconds=round(time.time() - start, 1), **extra))
    print(f"{name:40s} {results[-1]['seconds']:7.1f} s {extra}", file=sys.stderr, flush=True)


def http_job(name, user, method, url, data=None):
    s = login(user)
    if data is not None:
        data = dict(data, sesskey=s.sesskey)
    t = time.time()
    r = s.request(method, BASE + url, data=data, timeout=1800)
    record(name, t, status=r.status_code, ko=len(r.content) // 1024)


def cli_job(name, args):
    t = time.time()
    p = subprocess.run(['sudo', '-u', 'www-data', '/usr/bin/time', '-f', '%M', 'php'] + args,
                       capture_output=True, text=True, cwd=MOODLE)
    peak = p.stderr.strip().splitlines()[-1] if p.stderr.strip() else '?'
    record(name, t, code=p.returncode, memoire_max_mo=round(int(peak) / 1024) if peak.isdigit() else peak,
           sortie=p.stdout.strip()[-300:])


# Préparation : livrets conservés effacés, 300 émissions mises en file, activité cible vide.
for s in SITES[3:5]:
    php(f"$ctx = context_module::instance({s['cmid']}); get_file_storage()->delete_area_files($ctx->id, 'mod_ep', 'booklet');")
f = SITES[5]
users = ','.join(str(u) for u in f['students'][400:700])
php(f"$t = new \\mod_ep\\task\\issue_supplements(); $t->set_custom_data(['epid' => {f['epid']}, 'userids' => [{users}],"
    f" 'issuerid' => {f['deve'][0]}, 'lang' => 'fr', 'force' => true]); \\core\\task\\manager::queue_adhoc_task($t);")
target = php("require_once($CFG->dirroot . '/lib/phpunit/classes/util.php'); \\core\\session\\manager::set_user(get_admin());"
             f" echo phpunit_util::get_data_generator()->create_module('ep', ['course' => {SITES[0]['courseid']},"
             " 'name' => 'Cible restauration lourde'])->cmid;").stdout.strip()

# Charge interactive : 50 utilisateurs pendant 240 s.
load = subprocess.Popen(['python3', f'{HERE}/stress.py', 'paliers', SYN] + SITEFILES,
                        env=dict(os.environ, PALIERS='50', DUREE='240'), stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                        text=True)
time.sleep(20)

# Mémoire résidente maximale des processus PHP pendant les traitements.
peak = {'php_rss_max_mo': 0, 'php_rss_total_max_mo': 0}
stop = threading.Event()


def watch_memory():
    while not stop.is_set():
        out = subprocess.run(['ps', '-C', 'php-fpm8.3,php', '-o', 'rss='], capture_output=True, text=True).stdout.split()
        values = [int(v) // 1024 for v in out if v.isdigit()]
        if values:
            peak['php_rss_max_mo'] = max(peak['php_rss_max_mo'], max(values))
            peak['php_rss_total_max_mo'] = max(peak['php_rss_total_max_mo'], sum(values))
        time.sleep(1)


threading.Thread(target=watch_memory, daemon=True).start()
jobs = []
for k, s in enumerate(SITES[:3]):
    jobs.append(threading.Thread(target=http_job, args=(f"export complet {s['tag']}", f'devea_{k + 1}', 'POST',
                                                         f"/mod/ep/archive.php?id={s['cmid']}", {'action': 'export'})))
for s in SITES[3:5]:
    jobs.append(threading.Thread(target=http_job, args=(f"livret PDF {s['tag']}", 'devea_4', 'GET',
                                                         f"/mod/ep/booklet.php?id={s['cmid']}")))
    jobs.append(threading.Thread(target=http_job, args=(f"export CSV détaillé {s['tag']}", 'devea_4', 'GET',
                                                         f"/mod/ep/export.php?id={s['cmid']}&mode=credits")))
jobs.append(threading.Thread(target=cli_job, args=('émission groupée 300 suppléments', ['admin/cli/adhoc_task.php',
                                                                                       '--execute'])))
jobs.append(threading.Thread(target=cli_job, args=('restauration 60 000 crédits', [
    f'{HERE}/../concurrence/worker.php', f'{HERE}/site-a.json', 'restore', ARCHIVE, target])))
for j in jobs:
    j.start()
for j in jobs:
    j.join()
stop.set()
out, err = load.communicate()
print(err.strip(), file=sys.stderr)
json.dump({'traitements': results, 'memoire': peak, 'charge': json.loads(out) if out.strip() else None}, sys.stdout,
          indent=1, ensure_ascii=False)
