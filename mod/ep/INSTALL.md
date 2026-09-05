# Enseignement personnalisé (mod_ep)

Module d'activité qui gère les ECTS d'enseignement personnalisé (EP) d'une
promotion : le catalogue des EP académiques internes auxquels les étudiants
s'inscrivent, les EP déclarés hors catalogue (engagement étudiant, expérience
professionnelle, sport, académique externe) et les EP de type stage, attribués
automatiquement d'après les stages complémentaires déjà validés par la DEVE.

Il fonctionne en binôme avec `mod_epsynthesis`, exactement comme `mod_stage`
avec `mod_stagesynthesis` : cette activité-ci est celle de la promotion (DEVE
et étudiants), l'autre regroupe pour chaque enseignant tout ce qu'il a à
valider, toutes promotions confondues.

- **Prérequis** : `mod_stage` installé (dépendance déclarée dans
  `version.php`). Les EP de type stage et l'identification des enseignants
  référents sont lus dans ses tables ; rien n'y est écrit.

## Installation

Copier le dossier `mod/ep` dans `<moodle>/mod/ep`, puis lancer la mise à jour
de la base :

```bash
php admin/cli/upgrade.php
```

## Mise en place

1. Dans le cours de la promotion — celui qui porte déjà l'activité « Gestion
   des stages » —, ajouter une activité **Enseignement personnalisé**.
2. Renseigner l'**année d'étude courante** (elle détermine quelles années sont
   présentées comme exigibles) et, le cas échéant, le **minimum d'ECTS sur
   l'ensemble du cursus**.
3. Depuis **Administration → Types d'EP et plafonds** :
   - fixer le **maximum d'ECTS retenus** de chaque type, sur le cursus et/ou
     par année (0 = pas de plafond) ;
   - pour le type **stage**, fixer le **nombre d'ECTS par jour** de stage
     complémentaire validé par la DEVE ;
   - désactiver les types qui ne sont pas utilisés ;
   - rédiger la consigne affichée à l'étudiant pour chaque type déclarable.
4. Depuis **Administration → Minimums d'ECTS**, saisir le minimum à valider
   pour chaque année d'étude.
5. Depuis **Administration → Catalogue des EP internes**, créer les EP
   proposés (intitulé, ECTS, années concernées, nombre de places) et désigner
   pour chacun son ou ses **responsables** : ce sont eux qui valideront les
   inscriptions.

## Les six types et leur circuit

| Type | Comment l'ECTS arrive | Qui valide |
| --- | --- | --- |
| Académique interne | Inscription à un EP du catalogue, qui porte son propre nombre d'ECTS | Le responsable de cet EP |
| Stage | Automatiquement, d'après les stages complémentaires (EP) validés par la DEVE dans `mod_stage`, à raison de N ECTS par jour retenu | Personne : aucune vérification n'est demandée |
| Engagement étudiant | Déclaration de l'étudiant, avec justificatifs | Son enseignant référent |
| Expérience professionnelle | idem | idem |
| Sport | idem | idem |
| Académique externe | idem | idem |

L'enseignant référent d'un étudiant n'est pas ressaisi ici : c'est celui qui
lui est attribué dans l'activité « Gestion des stages » du même cours
(`stage_entry_teacher`). Retirer une attribution là-bas la retire donc
immédiatement ici. Sans référent attribué, la demande revient à la DEVE
(capacité `mod/ep:validatedeve`).

## Plafonds et minimums

Les ECTS validés au-delà d'un plafond de type **restent acquis** : ils cessent
seulement d'être comptés, et reviennent au décompte si le plafond est relevé —
sans avoir à revalider quoi que ce soit. Les bilans les affichent en colonne
« non retenus ».

Le plafond annuel d'un type s'applique d'abord à chaque année, puis le plafond
de cursus se consomme par années croissantes : les ECTS acquis en premier sont
retenus en premier, si bien qu'une année déjà validée ne peut pas être remise
en cause par un EP saisi plus tard.

Les minimums annuels et le minimum de cursus se cumulent : atteindre chaque
minimum annuel ne suffit pas nécessairement à atteindre celui du cursus.

## Attribution automatique depuis les stages

Un stage donne des ECTS d'EP s'il remplit trois conditions dans `mod_stage` :
il appartient à une activité « Gestion des stages » du même cours (ou à celle
désignée dans les paramètres), il est marqué **complémentaire (EP)** et il est
**validé par la DEVE**. Le crédit vaut alors `durée retenue × ECTS par jour`.

Ces crédits suivent leur stage d'origine : ils sont mis à jour si la durée
retenue change, et retirés si le stage est dévalidé, annulé ou repassé en
obligatoire. Ils ne sont donc ni modifiables ni validables à la main — c'est le
stage d'origine qu'il faut reprendre dans `mod_stage`.

Le recalcul a lieu :

- chaque nuit, par la tâche planifiée
  `mod_ep\task\sync_stage_credits` (5 h 15) ;
- à l'ouverture des pages de l'activité, au plus une fois toutes les cinq
  minutes ;
- à la demande, depuis le bouton « Resynchroniser les EP de type stage » de la
  page des types.

## Capacités

| Capacité | Pour qui |
| --- | --- |
| `mod/ep:view` | Tous les inscrits |
| `mod/ep:submit` | Étudiants : s'inscrire au catalogue, déclarer un EP |
| `mod/ep:evaluateteacher` | Enseignants : valider ce dont ils ont la charge |
| `mod/ep:validatedeve` | DEVE : valider et retirer pour tout le monde |
| `mod/ep:manage` | DEVE : types, catalogue, minimums |
| `mod/ep:viewall` | DEVE : voir tous les étudiants, exporter |

## Tests

```bash
vendor/bin/phpunit --testsuite mod_ep_testsuite
```

Les tests de `tests/stage_sync_test.php` utilisent le générateur de `mod_stage`
et supposent donc les deux modules installés.
