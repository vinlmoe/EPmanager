# Suivi de l'enseignement personnalisé (mod_epsynthesis)

Module d'activité complémentaire à `mod_ep`, avec deux rôles :

- **suivre** : donner à un enseignant une vue unique de tout ce qu'il a à
  suivre en matière d'enseignement personnalisé — les inscriptions aux EP dont
  il est responsable et les déclarations des étudiants dont il est enseignant
  référent —, tous cours/promotions `mod_ep` confondus ;
- **définir les EP académiques partagés** : ceux auxquels des étudiants de
  plusieurs promotions s'inscrivent. Ils sont créés ici une seule fois et
  apparaissent au catalogue de toutes les promotions suivies, au lieu d'être
  recopiés dans chacune — deux copies auraient chacune leurs places et leurs
  inscrits, alors que ce sont les mêmes.

C'est le pendant de `mod_stagesynthesis` pour `mod_stage`.

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
5. Depuis **EP académiques partagés**, créer les EP ouverts à plusieurs
   promotions (intitulé, ECTS, années d'étude concernées, nombre de places) et
   désigner pour chacun son ou ses **responsables**, parmi les enseignants de
   ce cours de suivi.

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

La seule exception est le **responsable d'un EP partagé** : cet EP s'adressant
à plusieurs promotions, il n'a pas de raison d'être enseignant dans l'une
d'elles en particulier. C'est sa désignation comme responsable qui lui donne la
main sur les inscriptions de son EP, et sur elles seules.

Trois écrans :

- **Validation** (page d'atterrissage) : ce qui attend une décision —
  inscriptions à accepter, puis EP terminés dont les ECTS restent à valider —,
  puis la liste filtrable de tout le périmètre.
- **Tableau de pilotage** : une ligne par étudiant dont l'utilisateur est
  référent, avec l'accès à sa situation détaillée. Être responsable d'un EP
  donne à statuer sur des inscriptions, pas à suivre le dossier complet d'un
  étudiant : ces étudiants-là ne figurent donc pas dans le pilotage.
- **Suivi des EP académiques** : une ligne par EP — les EP partagés définis
  ici et ceux propres à chaque promotion suivie — avec l'état de ses
  inscriptions (demandées, acceptées, validées) et l'accès à la liste de ses
  inscrits, toutes promotions confondues. C'est la vue du responsable, et
  celle de la DEVE : avec `mod/epsynthesis:viewall`, elle y suit l'ensemble
  des EP académiques du périmètre.

Une ligne renvoie vers la page habituelle de l'activité d'origine
(`mod/ep/validate.php`), où l'on retrouve le dossier de l'étudiant et ses
justificatifs. Les inscriptions aux EP partagés font exception et se traitent
dans la synthèse (`mod/epsynthesis/decide.php`), leur responsable n'ayant pas
forcément accès au cours de l'étudiant ; le formulaire de décision y est le
même, à l'identique.

## Le circuit d'un EP académique

1. **Inscription** de l'étudiant, depuis le catalogue de sa promotion. Elle
   n'est pas limitée au nombre de places et ne donne aucun ECTS.
2. **Acceptation de l'inscription** par le responsable de l'EP. Le nombre de
   places lui est rappelé, mais ne le lie pas : il peut le dépasser s'il le
   juge utile.
3. **Validation des ECTS** par le responsable, à la fin de l'EP, au vu de ce
   que l'étudiant y a fait. C'est là seulement que les ECTS sont acquis.

Un étudiant ne peut s'inscrire à un EP que si l'année d'étude courante de sa
promotion est dans la plage d'années de cet EP. Cette année est celle que
renseigne l'activité « Gestion des stages » de son cours (`mod_stage`) : elle
n'est pas ressaisie ici.

## Capacités

| Capacité | Pour qui |
| --- | --- |
| `mod/epsynthesis:view` | Enseignants : leur propre périmètre |
| `mod/epsynthesis:viewall` | DEVE : suivre tous les EP académiques du périmètre |
| `mod/epsynthesis:manageactivities` | DEVE : définir les EP partagés et leurs responsables |
| `mod/epsynthesis:managelinks` | DEVE : choisir les activités liées |

## Tests

```bash
vendor/bin/phpunit --testsuite mod_epsynthesis_testsuite
```
