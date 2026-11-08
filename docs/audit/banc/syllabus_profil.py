#!/usr/bin/env python3
"""Profil CPU des pages des modules syllabus (Excimer côté serveur, voir README et profil.py) :
chaque page est ouverte N fois avec l'en-tête X-Profil, puis les piles repliées sont résumées :
part du temps dans le code des modules (mod/syllabus*), dans la base de données, dans le rendu
(thème, gabarits) et dans le reste de Moodle, et les fonctions des modules les plus coûteuses.

Usage : python3 syllabus_profil.py <dossier prof> site-x.json syllabus-charge.json [N=20]
"""
import collections
import json
import os
import re
import sys

import requests

PROF = sys.argv[1]
site = json.load(open(sys.argv[2]))
DATA = json.load(open(sys.argv[3]))
N = int(sys.argv[4]) if len(sys.argv) > 4 else 20
BASE = site['wwwroot']
STAFF = DATA['staffcmid']
M = next(m for m in DATA['modules'] if m['year'] == 3)
ETU = DATA['promos']['3']['students'][7]
FW = DATA['frameworkid']


def login(user):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    s.post(BASE + '/login/index.php', data={'username': user, 'password': site['password'], 'logintoken': tok})
    return s


pages = {
    'etu-mes-modules': (ETU, f'/mod/syllabusstudent/view.php?id={DATA["promos"]["3"]["cmid"]}'),
    'etu-fiche': (ETU, f'/mod/syllabus/view.php?id={M["cmid"]}'),
    'ens-mes-modules': (M['leaders'][0], f'/mod/syllabusstaff/view.php?id={STAFF}'),
    'ens-redaction': (M['leaders'][0], f'/mod/syllabus/edit.php?id={M["cmid"]}'),
    'ens-resultats': (M['leaders'][0], f'/mod/syllabus/results.php?id={M["cmid"]}'),
    'ens-matrice': (M['leaders'][0], f'/mod/syllabusstaff/competencies.php?id={STAFF}&frameworkid={FW}'),
    'deve-tous': ('devea_1', f'/mod/syllabusstaff/all.php?id={STAFF}'),
    'deve-resultats': ('devea_1', f'/mod/syllabusstaff/results.php?id={STAFF}'),
    'reference-moodle-profil': (ETU, '/user/profile.php'),
}
for f in os.listdir(PROF):
    os.remove(os.path.join(PROF, f))
sessions = {}
for label, (user, url) in pages.items():
    s = sessions.setdefault(user, login(user))
    s.get(BASE + url)  # chauffe
    for _ in range(N):
        s.get(BASE + url, headers={'X-Profil': label})

print(f"{'page':26s} {'ms CPU':>7s} {'module':>7s} {'BD':>6s} {'rendu':>6s} {'cœur':>6s}  fonctions du module les plus coûteuses")
for label in pages:
    path = os.path.join(PROF, label + '.txt')
    if not os.path.exists(path):
        continue
    total = 0
    cats = collections.Counter()
    funcs = collections.Counter()
    for line in open(path):
        stack, _, count = line.rstrip().rpartition(' ')
        count = int(count)
        total += count
        frames = stack.split(';')
        leaf = frames[-1]
        if 'moodle_database' in stack and re.search(r'pgsql|query_|get_record|get_field|execute|count_records', leaf):
            cats['BD'] += count
        elif re.search(r'renderer|mustache|Mustache|theme_|output\\\\|html_writer|format_text|core_renderer', leaf):
            cats['rendu'] += count
        elif any(re.search(r'mod/syllabus', f) or re.match(r'(syllabus|mod_syllabus)', f)
                 for f in frames[-3:]):
            cats['module'] += count
        else:
            cats['cœur'] += count
        for f in reversed(frames):
            if re.match(r'(syllabus|mod_syllabus)', f):
                funcs[f] += count
                break
    ms = total / N
    top = ', '.join(f'{k} {v / N:.0f}' for k, v in funcs.most_common(3))
    print(f"{label:26s} {ms:7.1f} {100 * cats['module'] / total:6.0f}% {100 * cats['BD'] / total:5.0f}% "
          f"{100 * cats['rendu'] / total:5.0f}% {100 * cats['cœur'] / total:5.0f}%  {top}")
