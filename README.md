# EPmanager

Gestion de l'**enseignement personnalisé** (EP) sous Moodle, en deux modules
d'activité complémentaires, sur le même modèle que le couple `mod_stage` /
`mod_stagesynthesis` du dépôt [Moodle-stage](https://github.com/vinlmoe/Moodle-stage) :

| Module | Pour qui | Rôle |
| --- | --- | --- |
| [`mod/ep`](mod/ep/INSTALL.md) | DEVE et étudiants, dans le cours de la promotion | Catalogue des EP, inscriptions, déclarations, validation, décompte des ECTS |
| [`mod/epsynthesis`](mod/epsynthesis/INSTALL.md) | Enseignants et DEVE, dans un cours de suivi | Vue unique de tout ce qu'ils ont à valider et à suivre, toutes promotions confondues, et définition des EP académiques ouverts à plusieurs promotions |

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

Un **EP académique** se prend en trois temps : l'étudiant s'inscrit (sans
limite de places), son responsable accepte ou non l'inscription — le nombre de
places est un repère, qu'il peut dépasser —, puis valide les ECTS à la fin de
l'EP. L'année d'étude à laquelle un étudiant peut s'inscrire est celle de sa
promotion, telle que la renseigne `mod_stage`.

Un EP académique auquel des étudiants de **plusieurs promotions** s'inscrivent
est défini une seule fois dans `mod_epsynthesis`, qui sert aussi à suivre, EP
par EP, où en sont ses inscriptions.

Chaque type a son **maximum d'ECTS retenus**, sur le cursus et/ou par année.
Ce qui dépasse reste acquis mais cesse d'être compté, et revient au décompte si
le plafond est relevé.

Des **minimums** sont exigés par année d'étude et sur l'ensemble du cursus ;
les deux se cumulent.

## Prérequis

`mod_stage` (dépôt Moodle-stage) doit être installé : les EP de type stage et
l'identification des enseignants référents y sont lus. Rien n'y est écrit.

## Installation

Voir `mod/ep/INSTALL.md` puis `mod/epsynthesis/INSTALL.md`.
