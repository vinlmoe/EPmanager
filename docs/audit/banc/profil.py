#!/usr/bin/env python3
"""Profil CPU des pages de mod_ep (Excimer côté serveur, voir README) : chaque page est ouverte N
fois avec l'en-tête X-Profil, puis les piles repliées sont résumées : part du temps dans le code du
module (mod/ep, mod/epsynthesis), dans la base de données, dans le rendu (thème, gabarits) et
dans le reste de Moodle, et les fonctions les plus coûteuses du module.

Usage : python3 profil.py <dossier prof> <synthese_cmid> site-a.json [N=20]
"""
import collections
import json
import os
import re
import sys

import requests

PROF = sys.argv[1]
SYN = int(sys.argv[2])
site = json.load(open(sys.argv[3]))
N = int(sys.argv[4]) if len(sys.argv) > 4 else 20
BASE, CM, T = site['wwwroot'], site['cmid'], site['tag']


def login(user):
    s = requests.Session()
    r = s.get(BASE + '/login/index.php')
    tok = re.search(r'name="logintoken" value="([^"]+)"', r.text).group(1)
    s.post(BASE + '/login/index.php', data={'username': user, 'password': site['password'], 'logintoken': tok})
    return s


pages = {
    'etu-tableau': (f'etu{T}_500', f'/mod/ep/view.php?id={CM}'),
    'etu-catalogue': (f'etu{T}_500', f'/mod/ep/catalog.php?id={CM}'),
    'etu-supplement': (f'etu{T}_500', f'/mod/ep/supplement.php?id={CM}'),
    'etu-syllabus': (f'etu{T}_500', f'/mod/ep/syllabus.php?id={CM}&activityid={site["activities"][5]}'),
    'ens-validation': ('ensa_1', f'/mod/ep/credits.php?id={CM}'),
    'ens-participants': ('ensa_1', f'/mod/ep/participants.php?id={CM}&activityid={site["activities"][149]}'),
    'ens-synthese-todo': ('ensa_1', f'/mod/epsynthesis/todo.php?id={SYN}'),
    'ens-synthese-validation': ('ensa_1', f'/mod/epsynthesis/entries.php?id={SYN}'),
    'deve-pilotage': (f'deve{T}_1', f'/mod/ep/dashboard.php?id={CM}&page=3'),
    'deve-validation': (f'deve{T}_1', f'/mod/ep/credits.php?id={CM}&status=0'),
    'reference-moodle-profil': (f'etu{T}_500', '/user/profile.php'),
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
        elif any(re.search(r'mod/(ep|epsynthesis)/', f) or re.match(r'(ep_|epsynthesis_|mod_ep\\\\|mod_epsynthesis\\\\)', f)
                 for f in frames[-3:]):
            cats['module'] += count
        else:
            cats['cœur'] += count
        for f in reversed(frames):
            if re.match(r'(ep_|epsynthesis_|mod_ep\\\\|mod_epsynthesis\\\\)', f):
                funcs[f] += count
                break
    ms = total / N
    top = ', '.join(f'{k} {v / N:.0f}' for k, v in funcs.most_common(3))
    print(f"{label:26s} {ms:7.1f} {100 * cats['module'] / total:6.0f}% {100 * cats['BD'] / total:5.0f}% "
          f"{100 * cats['rendu'] / total:5.0f}% {100 * cats['cœur'] / total:5.0f}%  {top}")
