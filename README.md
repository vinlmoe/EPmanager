# EPmanager

Gestion de l'**enseignement personnalisé** (EP) sous Moodle, en deux modules
d'activité complémentaires, sur le même modèle que le couple `mod_stage` /
`mod_stagesynthesis` du dépôt [Moodle-stage](https://github.com/vinlmoe/Moodle-stage) :

| Module | Pour qui | Rôle |
| --- | --- | --- |
| [`mod/ep`](mod/ep/INSTALL.md) | DEVE et étudiants, dans le cours de la promotion | Catalogue des EP, déclarations, validation, décompte des ECTS |
| [`mod/epsynthesis`](mod/epsynthesis/INSTALL.md) | Enseignants, dans un cours de suivi | Vue unique de tout ce qu'ils ont à valider et à suivre, toutes promotions confondues |

## Ce que le système gère

Un enseignement personnalisé porté au crédit d'un étudiant relève d'un des
six **types**, qui déterminent comment ses ECTS arrivent et qui les valide :

| Type | Comment l'ECTS arrive | Qui valide |
| --- | --- | --- |
| Académique interne | Inscription à un EP du catalogue, qui porte son propre nombre d'ECTS | Le responsable de cet EP |
| Stage | Automatiquement, d'après les stages complémentaires (EP) validés par la DEVE dans `mod_stage`, à raison de N ECTS par jour retenu | Personne : aucune vérification n'est demandée |
| Engagement étudiant | Déclaration de l'étudiant, avec justificatifs | Son enseignant référent |
| Expérience professionnelle | idem | idem |
| Sport | idem | idem |
| Académique externe | idem | idem |

Chaque type a son **maximum d'ECTS retenus**, sur le cursus et/ou par année.
Ce qui dépasse reste acquis mais cesse d'être compté, et revient au décompte si
le plafond est relevé.

Des **minimums** sont exigés par année d'étude et sur l'ensemble du cursus ;
les deux se cumulent.

## Sauvegarde et restauration

Les deux modules fournissent une implémentation `backup/moodle2/` : une
sauvegarde de cours emporte leur paramétrage et, si les données utilisateur sont
demandées, les crédits des étudiants et leurs justificatifs. Deux réserves : les
EP de type stage ne sont pas recopiés mais recalculés depuis `mod_stage` à la
première consultation de la copie, et les liens d'une synthèse vers une activité
restée hors de la sauvegarde ne sont conservés que lors d'une restauration sur le
même site. Détail dans les deux `INSTALL.md`.

## Contrôle continu

Chaque poussée déclenche `.github/workflows/ci.yml`, en deux temps :

- **Style Moodle** — `phpcs` avec le standard `moodle` (`moodlehq/moodle-cs`), sur la
  configuration `phpcs.xml` du dépôt. Le contrôle échoue au premier écart, avertissements
  compris. Une seule règle est désactivée, et le fichier dit pourquoi : le sniff qui exige
  qu'un commentaire commence par `[A-Z0-9]` ne reconnaît pas les capitales accentuées, et
  s'y conformer imposerait d'écrire « Ecran » pour « Écran ».
- **Moodle** — `moodle-plugin-ci` installe un Moodle 4.5 avec PostgreSQL et les modules du
  dépôt, puis enchaîne analyse syntaxique, validation de la structure du module, points de
  sauvegarde de la mise à jour, gabarits Mustache et tests PHPUnit. Le contrôle des blocs de
  documentation (`phpdoc`) est présent mais non bloquant : son relevé reste à trier.

`mod_stage` étant une dépendance déclarée de `mod_ep`, le dépôt Moodle-stage est récupéré et
fourni en module supplémentaire. Les deux dépôts évoluant de pair, la CI prend la branche du
même nom si elle existe là-bas, et la branche par défaut sinon : un changement qui touche les
deux côtés se teste ainsi d'un bloc, avant même d'être fusionné.

Pour rejouer le contrôle de style en local :

```bash
mkdir -p /tmp/cs && composer --working-dir=/tmp/cs require moodlehq/moodle-cs
/tmp/cs/vendor/bin/phpcs --config-set installed_paths \
  /tmp/cs/vendor/moodlehq/moodle-cs/moodle,/tmp/cs/vendor/phpcsstandards/phpcsextra/Universal,\
/tmp/cs/vendor/phpcsstandards/phpcsextra/NormalizedArrays,/tmp/cs/vendor/phpcsstandards/phpcsextra/Modernize
/tmp/cs/vendor/bin/phpcs --standard=phpcs.xml -p
```

`phpcbf` (même chemin, mêmes options) corrige d'office la plus grande part des écarts.

## Prérequis

`mod_stage` (dépôt Moodle-stage) doit être installé : les EP de type stage et
l'identification des enseignants référents y sont lus. Rien n'y est écrit.

## Installation

Voir `mod/ep/INSTALL.md` puis `mod/epsynthesis/INSTALL.md`.
