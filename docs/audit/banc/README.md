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
