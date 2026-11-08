# Enseignement personnalisé (mod_ep)

Module d'activité qui gère les enseignements personnalisés (EP) d'une promotion : le
catalogue des EP auxquels les étudiants s'inscrivent, avec leurs syllabus ; les EP déclarés
hors catalogue ; les EP attribués d'après les stages complémentaires ; leur validation par des
circuits réglables ; le décompte des ECTS face aux minimums annuels ; la validation des années ;
le supplément au diplôme certifié.

Il fonctionne en binôme avec `mod_epsynthesis`, comme `mod_stage` avec `mod_stagesynthesis` :
cette activité-ci est celle de la promotion (DEVE, enseignants, étudiants), l'autre regroupe
pour chaque enseignant ce qu'il a à traiter, toutes promotions confondues.

- **Moodle 4.3 ou plus récent**, PHP 8.0 ou plus récent.
- **Prérequis** : `mod_stage` installé (dépendance déclarée dans `version.php`). Les EP de type
  stage et l'identification des enseignants référents sont lus dans ses tables ; rien n'y est
  écrit.
- **Prérequis** (depuis la 1.1) : `mod_syllabus` installé. La fiche de chaque EP est une
  activité « Syllabus du module » du cours, créée avec l'EP dans une section « Fiches des EP » ;
  la mise à jour vers la 1.1 y reprend les fiches existantes (tâche `migrate_syllabi`, lancée
  par la mise à jour). Pour que ces fiches n'encombrent pas la page du cours, autoriser les
  activités furtives (réglage du site `allowstealth`) : elles ne s'y affichent alors plus.

## Installation et mise à niveau

Copier le dossier `mod/ep` dans `<moodle>/mod/ep`, puis :

```bash
php admin/cli/upgrade.php
```

La mise à niveau depuis la version 0.1.0 convertit les données existantes (types, crédits,
historique des décisions, catalogue) sans perte ; elle a été répétée sur une copie d'un site
réel (`docs/audit/banc/migration_avant.php` et `migration_apres.php`). Faire une sauvegarde de
la base avant, comme pour toute mise à niveau.

Aucun réglage d'administration du site n'est nécessaire : tout le paramétrage se fait dans
l'activité, depuis son **panneau d'administration**, par la DEVE (capacité `mod/ep:manage`).

## Mise en place dans une promotion

1. Dans le cours de la promotion — celui qui porte l'activité « Gestion des stages » —, ajouter
   une activité **Enseignement personnalisé** et renseigner l'**année d'étude courante**.
2. Si une autre activité de l'école est déjà paramétrée, le plus simple est
   **Administration → Transférer le paramétrage → Copier depuis une autre activité** : types,
   minimums, supplément, courriels et catalogue sont repris en une fois. Sinon :
3. **Types d'EP** : six types sont créés d'office (académique interne, stage, engagement
   étudiant, expérience professionnelle, sport, académique externe). Pour chacun, ou pour un
   type ajouté sans programmation :
   - **mode d'acquisition** : inscription au catalogue, déclaration de l'étudiant, automatique
     d'après les stages, ou saisie par la DEVE seule ;
   - **circuit de validation**, en une à trois étapes : responsable de l'EP, enseignant référent,
     DEVE (par exemple « référent puis DEVE »), ou aucun (validé d'office) ;
   - **évaluation** : sans note (validé / non validé) ou notée (note maximale, seuil) ;
   - **justificatifs** : aucun, facultatifs ou obligatoires, et formats acceptés ;
   - **plafonds** d'ECTS retenus sur le cursus et par année, ECTS par jour (stages), consigne
     affichée à l'étudiant, présence dans le supplément au diplôme.
4. **Minimums d'ECTS** par année d'étude et sur le cursus.
5. **Catalogue** : créer les EP (ou les importer en CSV, voir `docs/formats-import.tex`) avec
   leurs ECTS, années, places, périodes d'inscription et de déroulement, responsables,
   prérequis (EP exigés, ECTS minimum), état (brouillon, proposé, publié, clos, archivé).
6. **Supplément au diplôme** : textes des rubriques fixes en français et en anglais, catégories
   d'éléments complémentaires, circuit et preuves, préfixe des numéros, cachet et signataire.
7. **Éditions PDF et courriels** : en-tête, pied de page et logo des PDF ; texte des courriels.

## Ce que chacun fait

| Qui | Où | Quoi |
| --- | --- | --- |
| Étudiant | Tableau de bord, catalogue, déclarer, mon supplément | S'inscrire, déclarer un EP avec justificatifs, suivre ses décisions et ses ECTS, demander des éléments pour son supplément, télécharger son supplément émis |
| Enseignant responsable d'un EP | Validation, participants, propositions | Inscrire ou retirer des étudiants en cours de session, noter ou valider (une par une, en lot ou par import CSV), proposer un nouvel EP ou mettre à jour le syllabus du sien |
| Enseignant référent | Validation, éléments du supplément | Trancher les déclarations de ses étudiants et leurs éléments du supplément, à son étape du circuit |
| DEVE | Panneau d'administration, validation, pilotage | Tout le paramétrage ; trancher et corriger toute décision ; examiner les propositions ; valider les années ; émettre les suppléments ; importer, exporter, restaurer |

Les décisions suivent le circuit du type, étape par étape, et chacune est gardée dans
l'historique de la demande (qui, quand, à quel titre, note, commentaire). Deux personnes qui
tranchent la même demande en même temps ne peuvent pas l'enregistrer deux fois : la seconde est
prévenue que la demande a changé.

## Attribution automatique depuis les stages

Un stage donne des ECTS d'EP s'il appartient à une activité « Gestion des stages » du même cours
(ou à celle désignée dans les paramètres), s'il est marqué **complémentaire (EP)** et
**validé par la DEVE**. Le crédit vaut `durée retenue × ECTS par jour` ; il suit son stage
d'origine. Recalcul dès qu'un stage concerné ou le barème change : une empreinte des stages
complémentaires validés (nombre, durées retenues, années, mobilité, dates de modification) est
comparée à celle du dernier calcul à l'ouverture des pages qui décomptent (1 à 2 ms), et avant la
validation des années et l'émission d'un supplément, qui attendent le recalcul. La DEVE peut donc
valider un stage dans « Gestion des stages » puis, aussitôt, les années ou le supplément. Mod_stage
ne signale pas toutes ses modifications par un événement (une durée retenue corrigée, par
exemple) : c'est pourquoi la comparaison porte sur les données elles-mêmes. Recalcul aussi chaque
nuit (tâche `mod_ep\task\sync_stage_credits`) et à la demande depuis la page des types. Les
enseignants référents sont lus en direct dans « Gestion des stages » : un changement d'attribution
y vaut aussitôt pour les demandes en attente.

## Supplément au diplôme

Au modèle Europass (huit rubriques). La rubrique 4.3 reprend les EP retenus ; la rubrique 6.1
les éléments complémentaires validés. La DEVE émet un supplément **certifié** : numéro unique,
empreinte SHA-256 du contenu imprimée sur le document, PDF conservé ; une nouvelle émission
remplace la précédente. Émission groupée pour toute une promotion (tâche ad hoc, avec message
à la fin). Toute personne peut vérifier un supplément, sans compte, sur
`/mod/ep/supplement_verify.php`, avec son numéro et son code.

## Imports, exports, sauvegarde

- Imports CSV du catalogue, des séances des syllabus et des crédits, avec prévisualisation
  (formats dans `docs/formats-import.tex`, exemples dans `docs/exemples/`).
- Export complet (ZIP : classeur Excel lisible, paramétrage, données des étudiants, fichiers)
  et restauration dans une autre activité, sans doublon.
- La sauvegarde et la restauration Moodle du cours comprennent toute l'activité.
- Réinitialisation du cours : dossiers des étudiants, décisions d'année, suppléments, journal
  des imports, au choix ; le paramétrage et le catalogue sont conservés.

## Tâches

| Tâche | Quand | Rôle |
| --- | --- | --- |
| `mod_ep\task\sync_stage_credits` | chaque nuit, 5 h 15 | EP de type stage |
| `mod_ep\task\send_pending_reminders` | le lundi, 6 h 40 | Relance des validateurs qui ont des demandes en attente depuis plus d'une semaine |
| `mod_ep\task\issue_supplements` | ad hoc | Émission groupée des suppléments |

## Capacités

| Capacité | Pour qui (par défaut) |
| --- | --- |
| `mod/ep:view` | Tous les inscrits |
| `mod/ep:submit` | Étudiants : s'inscrire, déclarer, demander des éléments du supplément |
| `mod/ep:evaluateteacher` | Enseignants : trancher ce dont ils ont la charge (EP dont ils sont responsables, étudiants dont ils sont référents) |
| `mod/ep:propose` | Enseignants : proposer un EP, mettre à jour le syllabus du leur |
| `mod/ep:validatedeve` | DEVE : trancher et corriger pour tout le monde, valider les années |
| `mod/ep:manage` | DEVE : paramétrage, catalogue, supplément, imports |
| `mod/ep:viewall` | DEVE : voir tous les étudiants, exporter |

Par défaut, `validatedeve`, `manage` et `viewall` sont données aux rôles « enseignant
éditeur » et « gestionnaire » : dans une école où les enseignants ont le rôle « enseignant
éditeur » dans le cours de la promotion, les leur retirer au niveau de l'activité ou du cours,
et donner à la DEVE le rôle « gestionnaire ».

## Dimensionnement et réglages du serveur

Mesures du test de stress (`docs/audit/test-de-stress.pdf`), sur un serveur de 4 cœurs avec six
promotions (10 000 étudiants, 540 000 crédits) : une page du module coûte de 40 à 75 ms de
processeur, à peine plus qu'une page ordinaire de Moodle ; réglé comme ci-dessous, un serveur de
4 cœurs en sert environ 60 par seconde, sans erreur jusqu'à 400 utilisateurs simultanés sans
temps de réflexion, et en continu. Réglages, du plus au moins rentable :

| Réglage | Où | Gain mesuré |
| --- | --- | --- |
| Connexions persistantes à la base : `$CFG->dboptions['dbpersist'] = 1;` (ou un pool comme pgbouncer) | `config.php` | +23 % |
| APCu comme magasin du cache applicatif (extension `apcu`, `apc.shm_size = 256M`, magasin « APCu » associé au mode application dans *Administration du site > Plugins > Caches*) | PHP, Moodle | +11 à +18 % |
| JIT de PHP : `opcache.jit = tracing`, `opcache.jit_buffer_size = 128M` | `php.ini` de PHP-FPM | +5 à +8 % |
| OPcache sans vérification des fichiers : `opcache.validate_timestamps = 0` | `php.ini` de PHP-FPM | ≈ +2 % |

- Avec APCu, **associer la définition `core/coursemodinfo` (cache des activités de chaque cours)
  au magasin fichier par défaut** (*Administration du site > Plugins > Caches*, correspondances de
  cette définition). APCu n'a pas de verrouillage natif : dans
  Moodle 4.5, une requête qui doit reconstruire le cache d'un cours pendant qu'une autre le fait
  échoue aussitôt (« Unable to acquire a lock for caching ») au lieu d'attendre. Sur le banc,
  après un redémarrage de PHP-FPM (qui vide APCu) ou une purge des caches, 12 à 14 des 40 premières
  connexions simultanées échouaient ainsi ; avec ce cache sur le magasin fichier, aucune, sans
  perte de débit mesurable (rapport `test-des-interactions.pdf`). Redis convient aussi.
- Avec APCu ou `validate_timestamps = 0`, **redémarrer PHP-FPM après chaque mise à jour** du code
  ou mise à niveau faite en ligne de commande : le cache en mémoire et le code compilé ne sont
  pas partagés avec les scripts en ligne de commande.
- Dimensionner PHP-FPM à environ 4 processus par cœur (`pm = static`) et prévoir 512 Mo de mémoire
  par processus pour les exports complets et les livrets PDF.
- Redis pour les sessions et le cache applicatif n'est utile qu'avec plusieurs serveurs web.
- À l'ouverture des inscriptions, une promotion entière qui s'inscrit à la même minute est servie
  sans erreur ni surréservation ; 300 inscriptions simultanées demandent 12 s sur 4 cœurs.

## Tests

```bash
vendor/bin/phpunit --testsuite mod_ep_testsuite
```

Les tests utilisent le générateur de `mod_stage` et supposent donc les deux modules installés.
Banc de charge et de concurrence : `docs/audit/` (rapports `test-de-charge.pdf` et
`test-de-concurrence.pdf`).
