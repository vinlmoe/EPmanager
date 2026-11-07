# Syllabus et évaluation des modules

Trois modules d'activité, à installer ensemble :

| Module | Où | Rôle |
| --- | --- | --- |
| `mod_syllabus` | Cours de chaque module d'enseignement | Fiche du module (syllabus au modèle ECTS, versions, relecture par la DEVE, PDF) et évaluation du module par les étudiants |
| `mod_syllabusstaff` | Cours d'équipe | Modules dont l'enseignant est responsable ; pour la DEVE, tous les modules, relectures, fenêtres en lot, résultats comparés, référentiel commun |
| `mod_syllabusstudent` | Cours de la promotion | Modules des cours où l'étudiant est inscrit, fiches, évaluations à faire |

- **Moodle 4.3 ou plus récent**, PHP 8.0 ou plus récent. Aucune dépendance à `mod_ep` ni à
  `mod_stage`.

## Installation

Copier `mod/syllabus`, `mod/syllabusstaff` et `mod/syllabusstudent` dans `<moodle>/mod/`, puis :

```bash
php admin/cli/upgrade.php
```

Aucun réglage d'administration du site : le référentiel commun (relecture obligatoire, seuil
d'anonymat, visibilité des résultats, types de module, questions communes) se tient dans
l'activité « Suivi des modules », page **Référentiel commun**, par la DEVE
(`mod/syllabusstaff:manageall`). Il vaut pour tout le site.

## Mise en place

1. Dans un cours d'équipe, ajouter « Suivi des modules (enseignants et DEVE) », avec pour
   périmètre la catégorie des cours de modules (ou tout le site) ; y inscrire les enseignants
   et donner à la DEVE le rôle « gestionnaire ».
2. Donner à la DEVE le rôle « gestionnaire » dans les cours de modules (au niveau de leur
   catégorie) : c'est ce rôle (`mod/syllabus:manage`) qui permet de relire les fiches et de
   fixer les fenêtres d'évaluation.
3. Dans chaque cours de module, ajouter « Syllabus du module » ; celui qui l'ajoute en devient
   responsable, d'autres enseignants du cours peuvent l'être.
4. Dans chaque cours de promotion, ajouter « Mes modules (étudiants) ».

## Compétences

Les référentiels de compétences sont propres à ces modules et tenus par la DEVE dans le suivi
des modules (**Référentiels de compétences**, droit `mod/syllabusstaff:manageall`) : référentiels
en arbre, compétences saisies à la main ou importées en CSV (`Code;Code parent;Intitulé;Description`).
Ni l'administration du site ni les compétences de Moodle ne sont utilisées. Chaque module est lié
depuis sa page **Compétences** par ses responsables ou la DEVE, avec un niveau (introduite,
approfondie, maîtrisée) ; la DEVE lie aussi les cours sans module depuis la matrice
compétences × cours (couverture, export CSV).

La mise à jour depuis la 1.1.0 (compétences de Moodle) recopie les référentiels utilisés et les
liens avec leur niveau, puis supprime la table `syllabus_competency` ; les référentiels de Moodle
ne sont pas touchés.

## Tâche

| Tâche | Quand | Rôle |
| --- | --- | --- |
| `mod_syllabus\task\open_evaluations` | tous les quarts d'heure | Évaluations qui viennent d'ouvrir : questions figées, étudiants prévenus |

## Capacités

| Capacité | Pour qui (par défaut) |
| --- | --- |
| `mod/syllabus:view` | Tous : lire la fiche publiée |
| `mod/syllabus:edit` | Enseignants : pouvoir être désigné responsable |
| `mod/syllabus:manage` | Gestionnaire (DEVE) : responsables, relecture, fenêtre, tous les résultats |
| `mod/syllabus:evaluate` | Étudiants : évaluer le module |
| `mod/syllabusstaff:view` | Enseignants : leurs modules |
| `mod/syllabusstaff:manageall` | Gestionnaire (DEVE) : tous les modules, référentiel commun |
| `mod/syllabusstudent:view` | Étudiants : leurs modules |

## Anonymat des évaluations

La réponse d'un étudiant ne porte ni son identifiant ni l'heure (seulement le jour) ; sa
participation est notée à part pour qu'il ne réponde qu'une fois. Aucune page ni aucun export
ne relie une réponse à un étudiant, et les résultats ne s'affichent qu'à partir d'un nombre
minimal de réponses. En base, l'ordre des enregistrements permettrait un rapprochement
approximatif : l'accès direct à la base doit rester restreint.

## Tests

```bash
vendor/bin/phpunit --testsuite mod_syllabus_testsuite
vendor/bin/phpunit --testsuite mod_syllabusstaff_testsuite
vendor/bin/phpunit --testsuite mod_syllabusstudent_testsuite
```

Banc de bout en bout : `docs/audit/banc/syllabus_setup.php` et `syllabus_interactions.py`
(rapport `docs/audit/test-syllabus.pdf`).
