#!/usr/bin/env python3
"""Balayage des droits : chaque page de chaque module, appelée avec l'identifiant d'une activité
par des comptes qui ne devraient pas y accéder, ou seulement en lecture.

Usage : python3 balayage_droits.py site-x.json syllabus.json [comptes...] > balayage.json

Comptes par défaut :
- etua_1, étudiant d'une autre promotion (non inscrit) ;
- etux_1, étudiant inscrit ;
- ensa_3, enseignant responsable d'un autre module.

Pour chaque appel sont relevés le code HTTP, l'adresse finale, le type de contenu, le titre et
le message d'erreur Moodle. Les lignes sans refus sont listées sur la sortie d'erreur : elles
sont à examiner une à une (page ouverte à ce rôle ou défaut).
Le balayage ne fait que des requêtes GET sans paramètre d'action : les actions (POST, sesskey)
relèvent des tests unitaires et de la revue du code.
"""
import glob
import json
import os
import re
import subprocess
import sys

import requests

SITE = json.load(open(sys.argv[1]))
DATA = json.load(open(sys.argv[2]))
ACTORS = sys.argv[3:] or ['etua_1', 'etux_1', 'ensa_3']
BASE, PW = SITE['wwwroot'], SITE['password']
MOD = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', 'mod')
EXCLUDE = {'lib.php', 'locallib.php', 'mod_form.php', 'version.php', 'settings.php'}


def sql(query):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', query], capture_output=True,
                          text=True).stdout.strip()


synthesis = sql("select cm.id from mdl_course_modules cm join mdl_modules m on m.id = cm.module "
                f"and m.name = 'epsynthesis' where cm.course = {SITE['courseid']} limit 1")
TARGETS = {'ep': SITE['cmid'], 'epsynthesis': int(synthesis or 0), 'syllabus': DATA['modules'][0]['cmid'],
           'syllabusstaff': DATA['staffcmid'], 'syllabusstudent': DATA['studentcmid']}


def login(user):
    s = requests.Session()
    token = re.search(r'name="logintoken" value="([^"]+)"', s.get(BASE + '/login/index.php').text).group(1)
    s.post(BASE + '/login/index.php', data={'username': user, 'password': PW, 'logintoken': token})
    return s


def text(fragment):
    return re.sub(r'<[^>]+>|\s+', ' ', fragment).strip()


rows = []
for actor in ACTORS:
    session = login(actor)
    for mod, cmid in TARGETS.items():
        if not cmid:
            continue
        for path in sorted(glob.glob(os.path.join(MOD, mod, '*.php'))):
            name = os.path.basename(path)
            if name in EXCLUDE:
                continue
            r = session.get(f'{BASE}/mod/{mod}/{name}?id={cmid}', timeout=120)
            error = re.search(r'<div[^>]*class="[^"]*(?:errorbox|alert-danger)[^"]*"[^>]*>(.*?)</div>', r.text, re.S) \
                or re.search(r'<p class="errormessage">(.*?)</p>', r.text, re.S)
            title = re.search(r'<title>(.*?)</title>', r.text, re.S)
            final = r.url.replace(BASE, '')
            row = {'acteur': actor, 'page': f'{mod}/{name}', 'http': r.status_code, 'final': final,
                   'type': r.headers.get('Content-Type', '').split(';')[0], 'taille': len(r.content),
                   'erreur': text(error.group(1))[:120] if error else '', 'titre': text(title.group(1))[:80] if title else ''}
            rows.append(row)
            if not row['erreur'] and '/login/' not in final and '/enrol/' not in final:
                print(f"ouvert : {actor:8s} {row['page']:40s} {row['http']} {row['type']} {row['titre']}", file=sys.stderr)
json.dump(rows, sys.stdout, ensure_ascii=False, indent=1)
