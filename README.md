# EP Manager

[![Moodle 4.3+](https://img.shields.io/badge/Moodle-4.3%2B-f98012?logo=moodle&logoColor=white)](https://moodle.org)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![Licence GPL v3+](https://img.shields.io/badge/licence-GPL%20v3%2B-blue)](https://www.gnu.org/licenses/gpl-3.0)

Gestion de l'**enseignement personnalisé** (EP) sous Moodle, en deux modules d'activité
complémentaires, sur le même modèle que `mod_stage` / `mod_stagesynthesis` du dépôt
StageCompagnon :

| Module | Pour qui | Rôle | Documentation |
| --- | --- | --- | --- |
| [`mod/ep`](mod/ep) | DEVE, enseignants et étudiants, dans le cours de la promotion | Types d'EP et circuits de validation, catalogue et syllabus, inscriptions et déclarations, notes, décompte des ECTS, validation des années, supplément au diplôme | [`mod/ep/INSTALL.md`](mod/ep/INSTALL.md) |
| [`mod/epsynthesis`](mod/epsynthesis) | Enseignants, dans un cours d'équipe | Vue unique de ce qu'ils ont à valider, à noter et à reprendre, toutes promotions confondues | [`mod/epsynthesis/INSTALL.md`](mod/epsynthesis/INSTALL.md) |

## Ce que le système gère

- **Types d'EP définis par la DEVE**, sans programmation : mode d'acquisition (inscription au
  catalogue, déclaration de l'étudiant, attribution automatique d'après les stages
  complémentaires, saisie de la DEVE), **circuit de validation** en une à trois étapes
  (responsable de l'EP, enseignant référent, DEVE) ou validation d'office, EP **notés** ou
  **sans note**, justificatifs, plafonds d'ECTS par année et sur le cursus.
- **Catalogue** : EP avec places, périodes d'inscription, années concernées, prérequis
  (bloquants ou non), responsables ; inscription des étudiants, ajout et retrait en cours de
  session par le responsable ou la DEVE ; notes et validations une par une, en lot ou par
  import CSV ; clôture.
- **Propositions et syllabus** : un enseignant propose un EP ou met à jour le sien (objectifs,
  acquis d'apprentissage, contenu, méthodes, conditions d'examen, séances et intervenants,
  bibliographie, au modèle de la fiche ECTS) ; la DEVE publie, renvoie ou refuse ; fiche PDF de
  chaque EP et livret PDF du catalogue.
- **Suivi** : tableau de bord de l'étudiant (ECTS par année et par type face aux minimums),
  pilotage de la promotion, relance hebdomadaire des validateurs, courriels personnalisables.
- **Fin d'année** : validation des années d'étude (une à une ou en lot), rentrée, transfert d'un
  étudiant vers une autre promotion.
- **Supplément au diplôme** au modèle Europass : EP suivis, éléments complémentaires demandés
  par l'étudiant et validés selon le circuit choisi par la DEVE (preuves PDF conservées),
  émission certifiée (numéro, empreinte SHA-256, cachet), émission groupée pour une promotion,
  vérification publique.
- **Transfert et sauvegarde** : paramétrage exportable en JSON ou copiable d'une activité à
  l'autre, imports CSV documentés (catalogue, séances, crédits), export complet (classeur Excel,
  données, fichiers) et restauration, sauvegarde Moodle complète, réinitialisation du cours.

Toute l'administration se fait dans l'activité, depuis son panneau **Administration** : aucun
réglage d'administration du site n'est demandé.

## Prérequis

Moodle 4.3 ou plus récent ; `mod_stage` (dépôt StageCompagnon) installé : les EP de type stage
et l'identification des enseignants référents y sont lus. Rien n'y est écrit.

## Installation

Voir [`mod/ep/INSTALL.md`](mod/ep/INSTALL.md) puis
[`mod/epsynthesis/INSTALL.md`](mod/epsynthesis/INSTALL.md).

## Documentation

| Document | Pour qui |
| --- | --- |
| `docs/guides/guide-parametrage.pdf` | DEVE : mise en place, paramétrage, fin d'année, supplément |
| `docs/guides/guide-enseignant.pdf` | Enseignants : responsables d'EP, référents, propositions |
| `docs/guides/guide-etudiant.pdf` | Étudiants |
| `docs/formats-import.pdf` | Formats des imports et des exports |
| `docs/audit/test-de-charge.pdf`, `docs/audit/test-de-concurrence.pdf` | Mesures sur une promotion de 2 000 étudiants |
| `docs/audit/test-de-stress.pdf` | Montée en charge jusqu'à saturation, endurance et traitements lourds sur six promotions |
| `docs/plan-mise-a-niveau.pdf` | Plan de la mise à niveau 1.0 |
| `CHANGELOG.md` | Historique des versions |

## Tests

```bash
vendor/bin/phpunit --testsuite mod_ep_testsuite
vendor/bin/phpunit --testsuite mod_epsynthesis_testsuite
```

Intégration continue : `.github/workflows/ci.yml` (moodle-plugin-ci : phpcs, phpdoc,
validation, sauvegarde, PHPUnit sur PostgreSQL et MariaDB).
