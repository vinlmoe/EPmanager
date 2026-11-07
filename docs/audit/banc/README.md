# Banc de charge de mod_ep

Scripts du test décrit dans `../test-de-charge.tex`. Ils tournent contre un vrai Moodle 4.5
(PostgreSQL 16) où `mod/ep`, `mod/epsynthesis`, `mod/stage` et `mod/stagesynthesis` sont
**copiés** (avec des liens symboliques, le `require('../../config.php')` des pages ne trouve pas
`config.php`). `config.php` doit contenir `$CFG->noemailever = true;` et, pour compter les
requêtes des pages, `$CFG->perfdebug = 15;`.

Variable d'environnement : `MOODLE_CONFIG` (chemin de `config.php`, par défaut
`/home/user/moodle-stress/moodle/config.php`).

Serveur intégré de PHP, plusieurs workers :

```bash
PHP_CLI_SERVER_WORKERS=8 php -d max_input_vars=5000 -d memory_limit=512M \
  -d post_max_size=64M -d upload_max_filesize=64M -S localhost:8100 -t moodle
```

- `charge_setup.php <tag> [étudiants] [enseignants] [ep] [crédits par étudiant]` : jeu de
  données (par défaut 2 000 étudiants, 150 enseignants référents et responsables, 4 DEVE,
  200 EP avec syllabus et prérequis, 30 crédits par étudiant à tous les états avec leurs
  décisions, 3 éléments du supplément par étudiant, décisions des années passées). Écrit
  `site-<tag>.json`. Mot de passe des comptes : `Banc123!`.
- `pages.py site-<tag>.json [répétitions]` : chronométrage de toutes les pages par rôle
  (étudiant, enseignant, DEVE) : durée médiane, taille, lectures et écritures en base, erreurs.
- `fonctions.php site-<tag>.json` : chronométrage des fonctions de lecture de `locallib.php`.
- `migration_avant.php`, `migration_apres.php` : répétition de la mise à niveau depuis la
  version 0.1.0 sur un site réel (voir le lot 1).

Options de `charge_setup.php` (variables d'environnement) : `PERSONNEL=<tag>` reprend les
enseignants et la DEVE d'une promotion déjà créée ; `VIERGE=1` crée une promotion sans aucun
crédit, élément ni décision (minimum de 6 ECTS en 3e année), pour `interactions.py`.

- `synthese_setup.php site-<tag>.json` : synthèse enseignant liée à la promotion (affiche son
  identifiant de module de cours).
- `interactions.py site-<tag>.json <synthèse>` : une promotion vierge vécue de bout en bout par
  l'interface web (inscriptions, déclarations et éléments avec fichiers, décisions des
  responsables, des référents et de la DEVE, validation des années, émission des suppléments,
  vérification publique, exports), puis contrôles en base (rapport
  `../test-des-interactions.tex`). Variables `PHASES`, `DEBUT`, `LIMITE`, `REFERENTS` pour en
  rejouer une partie.
- `stress.py`, `profil.py`, `lourds.py`, `ruee.py` : test de stress (`../test-de-stress.tex`).
- `muc_modinfo.php` : associe le cache `core/coursemodinfo` au magasin fichier quand le cache
  applicatif est dans APCu (voir `mod/ep/INSTALL.md`).
- `liaison_stage_prep.php` et `liaison_stage.py` : liaison avec mod_stage par les pages des deux
  modules (barème du type stage, validation en masse dans la page DEVE de mod_stage, corrections,
  annulations, changement de référent, validation des années et supplément juste après une
  validation de stage, tâche de nuit). `liaison_stage_prep.php site-x.json reset` pour rejouer.
- `syllabus_setup.php` et `syllabus_interactions.py` : modules syllabus de bout en bout par les
  pages web (référentiel DEVE, fiches, relecture, fenêtre en lot, tâche d'ouverture, 200
  étudiants qui évaluent, résultats), rapport `../test-syllabus.tex`. `syllabus_setup.php
  site-x.json reset` pour rejouer.
- `syllabus_captures.sh site-x.json syllabus.json [nom ...]` (avec `syllabus_captures_prep.php`) : captures du
  guide des modules syllabus après le scénario de bout en bout (mise à jour de fiche soumise à la
  DEVE, recueil rouvert le temps des captures étudiantes, puis remis en état).
- `syllabus_charge_setup.php`, `syllabus_pages.py`, `syllabus_stress.py`, `syllabus_ruee.py`,
  `syllabus_lourds.py`, `syllabus_profil.py` : modules syllabus à l'échelle d'une école (cinq
  années de douze modules, 1 000 étudiants), rapport `../test-syllabus-charge.tex`. Le jeu se
  génère après le scénario de bout en bout (qui recrée le référentiel commun) ;
  `syllabus_charge_setup.php site-x.json reset` pour le supprimer.
- `balayage_droits.py site-x.json syllabus.json [comptes...]` : chaque page des cinq modules
  appelée par un étudiant d'une autre promotion, un étudiant inscrit et le responsable d'un autre
  module ; les pages non refusées sont listées sur la sortie d'erreur, à examiner une à une.
  Rapport `../audit-complet.tex`. Les scripts PHP du banc se lancent en `sudo -u www-data` avec
  des fichiers lisibles par ce compte (copier au besoin `site-x.json` à côté de Moodle).
