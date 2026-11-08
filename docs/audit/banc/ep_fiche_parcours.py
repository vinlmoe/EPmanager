#!/usr/bin/env python3
"""Fiche d'un EP tenue par mod_syllabus, par les pages du banc (activité EP du site x, cmid 33) :
l'enseignant ensa_1 propose un EP (caractéristiques) puis rédige et soumet sa fiche dans
mod_syllabus ; la DEVE devea_1 la relit depuis « Propositions et syllabus » et la publie ;
l'étudiant etux_1 l'ouvre depuis le catalogue, sans la voir dans « Mes modules » faute d'y être
inscrit. Chaque étape est contrôlée en base.

Usage : python3 ep_fiche_parcours.py
"""
import html, re, subprocess, sys, requests
B, PW = 'http://localhost:8100', 'Banc123!'
def sql(q):
    return subprocess.run(['sudo', '-u', 'postgres', 'psql', 'moodlestress', '-tAc', q], capture_output=True, text=True).stdout.strip()
def login(u):
    s = requests.Session(); t = re.search(r'name="logintoken" value="([^"]+)"', s.get(B + '/login/index.php').text).group(1)
    s.post(B + '/login/index.php', data={'username': u, 'password': PW, 'logintoken': t}); return s
def form(page, marker):
    for m in re.finditer(r'<form[^>]*action="([^"]+)"[^>]*>(.*?)</form>', page, re.S):
        if marker in m.group(2):
            fields = {}
            for tag in re.findall(r'<(?:input|select|textarea)[^>]*>', m.group(2)):
                n = re.search(r'name="([^"]+)"', tag); v = re.search(r'value="([^"]*)"', tag)
                t = re.search(r'type="([^"]+)"', tag)
                if not n or (t and t.group(1) in ('submit', 'checkbox', 'radio')):
                    continue
                fields[n.group(1)] = html.unescape(v.group(1)) if v else ''
            for sel in re.finditer(r'<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>', m.group(2), re.S):
                opts = re.findall(r'<option[^>]*value="([^"]*)"([^>]*)>', sel.group(2))
                chosen = [v for v, attrs in opts if 'selected' in attrs] or [v for v, _ in opts[:1]]
                fields[sel.group(1)] = html.unescape(chosen[0]) if chosen else ''
            for ta in re.finditer(r'<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>', m.group(2), re.S):
                fields[ta.group(1)] = html.unescape(ta.group(2))
            return html.unescape(m.group(1)), fields
    raise SystemExit('formulaire introuvable : ' + marker)
def check(label, ok, extra=''):
    print(('OK ' if ok else 'ÉCHEC ') + label, extra); globals().setdefault('fails', 0)
    if not ok: globals()['fails'] += 1

ens, deve, etu = login('ensa_1'), login('devea_1'), login('etux_1')
# 1. Proposition : caractéristiques.
r = ens.get(B + '/mod/ep/syllabus_edit.php?id=33&activityid=0')
check('formulaire de proposition réduit aux caractéristiques', 'objectives' not in r.text and 'name="ects"' in r.text)
url, f = form(r.text, 'name="ects"')
f.update({'name': 'Parcours audit EP', 'ects': '2', 'capacity': '10', 'description': 'Proposition de test', 'submitbutton': 'x'})
r = ens.post(url, data=f)
check('redirection vers la fiche mod_syllabus', '/mod/syllabus/edit.php' in r.url, r.url)
aid = sql("select id from mdl_ep_activity where name='Parcours audit EP' order by id desc limit 1")
check('EP en brouillon', sql(f"select state from mdl_ep_activity where id={aid}") == 'draft')
# 2. Rédaction et soumission dans mod_syllabus.
url, f = form(r.text, 'name="objectives"')
f.update({'objectives': 'Objectifs du parcours', 'outcomes': 'Acquis', 'content': 'Contenu', 'assessment': 'Examen',
          'language': 'Français', 'sessionrow[0][sessiontitle]': 'Cours', 'sessionrow[0][sessionkind]': 'lecture',
          'sessionrow[0][sessionduration]': '2', 'submitforreview': 'x'})
f.pop('submitbutton', None); f.pop('cancel', None)
r = ens.post(url, data=f)
check('EP proposé après soumission', sql(f"select state from mdl_ep_activity where id={aid}") == 'proposed', r.url)
# 3. DEVE : propositions puis décision sous la fiche.
r = deve.get(B + '/mod/ep/proposals.php?id=33')
link = re.search(r'href="([^"]*syllabus\.php\?id=33&amp;activityid=' + aid + r'&amp;review=1)"', r.text)
check('proposition listée pour la DEVE', bool(link))
r = deve.get(html.unescape(link.group(1)))
check('relecture dans mod_syllabus', '/mod/syllabus/view.php' in r.url and 'show=working' in r.url, r.url)
url, f = form(r.text, 'name="decision"')
f.update({'decision': 'publish', 'reviewcomment': 'Parfait'})
r = deve.post(url, data=f)
check('EP publié au catalogue', sql(f"select state from mdl_ep_activity where id={aid}") == 'published')
# 4. Étudiant : catalogue -> fiche.
r = etu.get(B + '/mod/ep/catalog.php?id=33&search=Parcours+audit')
cm = sql(f"select syllabuscmid from mdl_ep_activity where id={aid}")
check('lien du catalogue vers la fiche', f'/mod/syllabus/view.php?id={cm}' in r.text)
r = etu.get(B + f'/mod/syllabus/view.php?id={cm}')
check('fiche lisible par l\'étudiant', 'Objectifs du parcours' in r.text and 'catalog.php' in r.text)
check('fiche absente de « Mes modules » d\'un non-inscrit', f'view.php?id={cm}' not in etu.get(B + '/mod/syllabusstudent/view.php?id=386').text)
print('échecs :', globals().get('fails', 0))
