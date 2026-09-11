# Suivi de l'enseignement personnalisé (mod_epsynthesis)

Module d'activité complémentaire à `mod_ep` : donne à un enseignant une vue
unique de tout ce qu'il a à suivre en matière d'enseignement personnalisé —
les inscriptions aux EP dont il est responsable et les déclarations des
étudiants dont il est enseignant référent —, tous cours/promotions `mod_ep`
confondus, sans avoir à naviguer d'un cours à l'autre.

C'est le pendant exact de `mod_stagesynthesis` pour `mod_stage`.

- **Prérequis** : `mod_ep` installé (dépendance déclarée dans `version.php`),
  lui-même dépendant de `mod_stage`.

## Installation

Copier le dossier `mod/epsynthesis` dans `<moodle>/mod/epsynthesis`, puis
lancer la mise à jour de la base :

```bash
php admin/cli/upgrade.php
```

## Mise en place

1. Créer un cours dédié (par exemple « Suivi de l'enseignement personnalisé »),
   distinct des cours de promotion qui portent chacun leur propre activité
   « Enseignement personnalisé ». Le même cours que celui de
   `mod_stagesynthesis` convient très bien : un enseignant y retrouve alors ses
   stages et ses EP au même endroit.
2. Y inscrire les enseignants concernés (rôle Enseignant non éditeur suffit).
3. Ajouter une activité **Suivi de l'enseignement personnalisé**.
4. Depuis la page de l'activité, cliquer sur **Gérer les liens** (visible aux
   enseignants éditeurs/managers) et cocher les activités « Enseignement
   personnalisé » (une par promotion) qui doivent y remonter. Décocher une
   activité (promotion sortie, pas encore concernée...) la retire de la
   synthèse sans rien modifier dans l'activité d'origine.

## Fonctionnement

L'activité ne stocke ni droit ni donnée d'enseignement personnalisé : à
l'affichage, elle relit, pour l'utilisateur connecté, les attributions déjà
existantes dans chaque activité liée —

- les étudiants dont il est **enseignant référent**, lus dans l'activité
  « Gestion des stages » du cours de la promotion (`stage_entry_teacher`), via
  `mod_ep` ;
- les EP du catalogue dont il est **responsable** (`ep_activity_teacher`) —

et ne montre que les activités où il a toujours la capacité
`mod/ep:evaluateteacher` sur l'instance d'origine. Retirer un enseignant d'un
cours, ou révoquer un de ces rôles, le retire donc automatiquement de la
synthèse — aucune synchronisation à faire.

Deux écrans, comme dans `mod_ep` :

- **Validation** (page d'atterrissage) : ce qui attend une décision, puis la
  liste filtrable de tout le périmètre.
- **Tableau de pilotage** : une ligne par étudiant dont l'utilisateur est
  référent, avec l'accès à sa situation détaillée. Être responsable d'un EP
  donne à valider des inscriptions, pas à suivre le dossier complet d'un
  étudiant : ces étudiants-là ne figurent donc pas dans le pilotage.

Chaque ligne renvoie vers la page habituelle de l'activité d'origine
(`mod/ep/validate.php`) : la décision elle-même continue de se prendre dans le
cours de la promotion concernée.

## Sauvegarde et restauration

Le module fournit une implémentation `backup/moodle2/` : la sauvegarde emporte
l'instance et la liste des activités liées. N'ayant aucune donnée de suivi
propre, il n'a rien d'autre à sauvegarder.

Les liens désignent des activités `mod_ep` par leur identifiant de
course-module, qui change à la restauration. À la fin de la restauration, une
fois toutes les activités du cours recréées :

- un lien vers une activité **restaurée en même temps** pointe vers sa copie ;
- un lien vers une activité **absente de la sauvegarde** n'est conservé que si la
  restauration a lieu sur le même site, où l'identifiant d'origine désigne
  toujours la bonne activité ; ailleurs il est supprimé, faute de quoi il
  désignerait une activité sans rapport.

Après une restauration sur un autre site, vérifier donc la liste depuis
**Gérer les liens**.

## Tests

```bash
vendor/bin/phpunit --testsuite mod_epsynthesis_testsuite
```

`tests/backup_restore_test.php` vérifie la restauration des liens : un lien vers
une activité sauvegardée en même temps suit sa copie, un lien vers une activité
extérieure reste en l'état sur le même site.
