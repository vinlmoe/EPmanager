# Historique des versions

Une section par version de module, la plus récente en tête. Les dates sont celles du commit qui
a fixé le numéro dans `version.php`. À tenir à jour à chaque modification de `version.php`.

## 0.7.0 — mod_ep 2026111100 (11 novembre 2026)

Lot 6 : imports et exports.

### mod_ep
- **Import du catalogue** depuis un CSV (`import_catalog.php`) : EP créés ou mis à jour (par code
  puis intitulé), responsables, prérequis (même vers un EP plus loin dans le fichier) et
  rubriques du syllabus, publiées dans une nouvelle version ; **import des séances** des
  syllabus (nature CM, TD, TP..., durée, intervenant du site ou extérieur, date).
- **Import de crédits** depuis un CSV (`import_credits.php`, DEVE) : historique d'un outil
  précédent ou EP suivis ailleurs, crédits tranchés avec leur décision ou en attente dans le
  circuit de leur type ; doublons écartés.
- Lecture commune des CSV : UTF-8 ou Excel (Windows-1252), séparateur détecté, colonnes
  reconnues par leur intitulé en français ou en anglais sans tenir compte des accents ;
  prévisualisation ligne par ligne avant toute écriture, modèle à télécharger, tableau des
  colonnes reconnues sur chaque page.
- **Transfert du paramétrage** (`settings_transfer.php`) : export JSON (réglages, types,
  minimums, supplément, courriels, catalogue et syllabus), import par parties, copie directe
  depuis une autre activité gérée ; aucune donnée d'étudiant.
- **Export complet** (`archive.php`) : archive ZIP avec un classeur Excel lisible, le
  paramétrage, les données des étudiants et tous les fichiers ; **restauration** dans une
  autre activité, sans rien supprimer et sans doublon si elle est rejouée.
- **Journal des imports** (table `ep_import_log`).
- Panneau d'administration : section « Imports, exports et transfert ».
- Documentation des formats : `docs/formats-import.tex` et fichiers d'exemple
  `docs/exemples/`.
- Tests : lecture CSV, catalogue, séances, crédits, transfert du paramétrage, export et
  restauration (110 tests).

## 0.6.0 — mod_ep 2026111000 (10 novembre 2026)

Lot 5 : supplément au diplôme, validation des années, rentrée, transfert d'étudiant.

### mod_ep
- **Supplément au diplôme** au modèle Europass (huit rubriques) : les rubriques fixes (titulaire,
  qualification, niveau, système national, certification) sont rédigées par la DEVE en français
  et en anglais (`supplement_settings.php`) ; la rubrique 4.3 reprend tous les EP retenus au
  dossier de l'étudiant, avec leurs ECTS et leur note.
- **Éléments complémentaires** (rubrique 6.1) demandés par l'étudiant (`supplement.php`,
  `supplement_item.php`) : catégorie définie par la DEVE, période, intitulé en français et en
  anglais, preuves PDF facultatives ou obligatoires au choix de la DEVE ; circuit de validation
  réglable (DEVE seule, ou enseignant référent puis DEVE) ; file d'examen
  (`supplement_items.php`) ; retrait ou modification tant que l'élément est en attente.
- **Émission certifiée** par la DEVE (`supplement_issue.php`) : numéro unique, empreinte SHA-256
  du contenu imprimée sur le document, cachet et signataire, PDF conservé ; aperçu non certifié
  (filigrane) pour l'étudiant ; une nouvelle émission remplace la précédente. Émission groupée
  pour toute une promotion par une tâche ad hoc, avec message à la fin, et archive ZIP des PDF.
  Page publique de **vérification** d'un supplément par son numéro et son code
  (`supplement_verify.php`).
- **Validation des années d'étude** (`year_validation.php`) : bilan de chaque étudiant face au
  minimum annuel, décision individuelle ou en lot, historique.
- **Rentrée** (`rollover.php`) : passage à l'année suivante ; **transfert d'un étudiant** vers une
  autre activité gérée (EP, décisions d'année, éléments du supplément et preuves).
- Panneau d'administration : section « Supplément au diplôme ». Barre de navigation : « Mon
  supplément au diplôme », « Éléments du supplément à examiner ».
- Sauvegarde : catégories, textes, éléments et preuves, émissions et PDF, cachet, décisions
  d'année. Mise à niveau : catégories par défaut créées pour chaque activité existante.
- Tests : circuit des éléments, contenu du supplément, émission, remplacement, vérification,
  décisions d'année, rentrée, transfert (103 tests).

## 0.5.0 — mod_ep 2026110900 (9 novembre 2026)

Lot 4 : propositions d'EP, syllabus, éditions PDF, courriels.

### mod_ep
- **Proposition d'un EP par un enseignant** (capacité `mod/ep:propose`, `proposals.php`,
  `syllabus_edit.php`) : il crée l'EP à l'état de brouillon, en devient responsable, rédige sa
  fiche et la soumet à la DEVE ; la DEVE la publie (l'EP entre au catalogue), la renvoie avec un
  commentaire (l'EP repasse en brouillon) ou la refuse (l'EP est archivé).
- **Syllabus** aux rubriques de la fiche ECTS : objectifs, acquis d'apprentissage, prérequis,
  contenu, méthodes, conditions d'examen, seconde session, bibliographie, langue, travail
  personnel ; **programme des séances** (intitulé, nature, durée, intervenant du cours ou
  extérieur, date) ; volume horaire par nature, charge de travail totale et heures par ECTS.
- **Mise à jour d'un EP publié** par son responsable : version de travail, la version en vigueur
  restant au catalogue jusqu'à la publication de la nouvelle ; publication directe facultative,
  au choix de la DEVE. Historique des versions (publiée, remplacée, refusée).
- **Fiche du syllabus** (`syllabus.php`) pour tous les inscrits, avec la décision de la DEVE sur
  une version soumise ; **fiche PDF** et **livret PDF** de tous les EP du catalogue (page de
  titre, sommaire, signets), avec en-tête, pied de page et logo réglés par la DEVE.
- **Courriels** par la messagerie Moodle (courriel ou notification, au choix de chacun), au texte
  personnalisable par activité (`notifications.php`) : décision finale sur une demande d'EP, à
  l'étudiant ; proposition à examiner, à la DEVE ; décision sur une proposition, à son auteur ;
  relance hebdomadaire des validateurs qui ont des demandes en attente depuis plus d'une semaine
  (tâche planifiée du lundi).
- Panneau d'administration : section « Éditions PDF et propositions » (réglages PDF, courriels,
  livret). Barre de navigation : « Propositions et syllabus ». Catalogue : lien vers la fiche.
- Sauvegarde : syllabus (toutes versions) et séances, courriels personnalisés, logo, réglages.
- Tests : proposition, rédaction et volume horaire, soumission, renvoi, publication, révision,
  publication directe, refus, listes, courriels et relance (98 tests).

## 0.4.0 — mod_ep 2026110800 (8 novembre 2026)

Lot 3 : catalogue, participants, notes.

### mod_ep
- **États d'un EP du catalogue** : brouillon, proposé, publié, clos, archivé. Un EP publié accepte
  les inscriptions pendant sa période d'inscription (ouverture et fermeture facultatives) ; un EP
  clos reste au catalogue sans inscription, ses notes restant saisissables ; un EP archivé sort du
  catalogue, ses inscriptions restant au dossier. L'ancien drapeau « visible » est converti : un EP
  masqué devient archivé.
- Un EP reçoit un **code** (unique dans l'activité, pour les imports et exports), des dates de
  déroulement et, s'il le faut, une **évaluation propre** (sans note, ou note maximale et seuil),
  qui prime sur celle de son type.
- **Prérequis** : EP du catalogue à avoir validés et nombre d'ECTS déjà retenus ; bloquants ou
  seulement signalés, au choix. Le catalogue montre à l'étudiant ce qui lui manque ; l'inscription
  les revérifie sous le verrou.
- **Page Participants** d'un EP, ouverte à ses responsables et à la DEVE (`participants.php`) :
  inscrire des étudiants après la période d'inscription (la DEVE seule peut dépasser les places),
  retirer un participant en attente (motif conservé, place libérée), saisir les notes ou les
  validations de toute la liste en une fois, sous le verrou et dans une transaction, chaque écriture
  restant conditionnée à l'état du crédit ; correction d'une note déjà tranchée par la DEVE, tracée
  dans l'historique ; clôture de l'EP avec sort des inscriptions en attente (laissées, validées
  d'office pour un EP sans note, non validées) ; export CSV de la liste et feuille d'émargement PDF.
- **Import des notes** depuis un CSV (`grades_import.php`) avec prévisualisation : colonnes
  reconnues par leur intitulé en français ou en anglais, adresse ou numéro d'identification,
  virgule décimale, décision en clair pour un EP sans note, fichier Excel en Windows-1252 ;
  chaque ligne fautive est signalée. La liste exportée sert de modèle.
- L'écran de validation montre aux enseignants les **EP dont ils sont responsables**, avec le
  nombre de participants et d'inscriptions en attente, et l'accès à leur liste.
- La gestion du catalogue passe d'un état à l'autre depuis la liste (publier, clore, archiver).
- Journaux : inscription par le responsable ou la DEVE, retrait par le responsable, validation
  d'une étape intermédiaire, correction de note.
- Sauvegarde : états, périodes, barème propre, prérequis (remappés vers les EP de la copie),
  auteur d'un ajout.
- Tests : états et périodes, prérequis, enregistrement d'un EP, ajout et retrait de participants,
  notes et décisions en lot, correction, clôture, droits, import de notes (91 tests).

## 0.3.0 — mod_ep 2026110700 (7 novembre 2026)

Lot 2 : types d'EP définis par la DEVE, sans programmation.

### mod_ep
- **Types paramétrés** : le comportement d'un type n'est plus déduit de son code mais de ses
  paramètres, réglés depuis *Administration > Types d'EP et plafonds* :
  - mode d'acquisition : inscription à un EP du catalogue, déclaration de l'étudiant,
    automatique d'après les stages complémentaires, saisie par la DEVE ;
  - circuit de validation, suite ordonnée d'étapes parmi responsable de l'EP, enseignant
    référent et DEVE (par exemple « responsable puis DEVE »), ou aucune étape (validé d'office) ;
  - évaluation sans note, ou avec note (note maximale et seuil de validation) ;
  - justificatifs sans objet, facultatifs ou obligatoires, en PDF seul ou en documents ;
  - nombre d'ECTS demandable au plus, plafonds par année et sur le cursus ;
  - pour les types stage, barème par jour et portée (tous les stages, à l'étranger, en France) :
    plusieurs types peuvent se partager les stages ;
  - présence au supplément au diplôme.
- La DEVE ajoute, modifie, réordonne, active, désactive et supprime les types ; un type qui
  porte des EP ou des crédits ne se supprime pas.
- **Circuits à étapes** : chaque crédit en attente connaît son étape et le rôle attendu ; le
  responsable ou le référent valide son étape et transmet à la suivante, la DEVE tranche à toute
  étape ; un refus est final. Les files de validation des enseignants ne montrent que ce qui
  attend leur rôle.
- **Notes** : pour un type noté, le validateur saisit une note ; au seuil, le crédit est validé,
  en dessous il est non validé, la note étant conservée.
- **Historique des décisions** (`ep_credit_decision`) : qui, quand, à quelle étape, note, ECTS,
  commentaire ; affiché sur la fiche du crédit, sauvegardé avec le cours.
- Déclaration : justificatifs obligatoires et plafond demandable contrôlés à la saisie ; la
  consigne de chaque type rappelle justificatifs, plafond et circuit.
- **Migration** : les six types existants sont convertis à l'identique de leur comportement, les
  demandes en attente reçoivent leur étape, l'historique des décisions passées est reconstitué.
  Vérifiée sur un site 0.1.0 réel (`docs/audit/banc/migration_avant.php` puis
  `migration_apres.php`), schéma identique à une installation neuve. Les sauvegardes antérieures
  se restaurent avec la même conversion.
- Tests : circuits, notes, administration des types, migration, portée des types stage
  (82 tests).

## 0.2.0 — mod_ep 2026110600, mod_epsynthesis 2026110600 (6 novembre 2026)

Premier lot de la mise à niveau au niveau de StageCompagnon (voir
`docs/plan-mise-a-niveau.tex`) : les fondations sur lesquelles les lots suivants s'appuient.

### Dépôt
- Standard de codage Moodle (`phpcs.xml`, mêmes règles que StageCompagnon), `.editorconfig`,
  licence GPL v3, réglages d'attribution des commits.
- Contrôle continu GitHub Actions : style, puis `moodle-plugin-ci` (lint, validation, points de
  sauvegarde, PHPDoc, Mustache, PHPUnit, Behat) sur Moodle 4.3 / PHP 8.0 / PostgreSQL,
  Moodle 4.5 / PHP 8.2 / PostgreSQL avec couverture, et Moodle 4.5 / PHP 8.2 / MariaDB.
  `mod_stage` et `mod_stagesynthesis` sont installés depuis le dépôt StageCompagnon.
- Moodle 4.3 minimum (`requires = 2023100900`) pour les deux modules, aligné sur `mod_stage`.

### mod_ep
- **Sauvegarde Moodle** (`backup/moodle2/`) : le paramétrage (types, catalogue et responsables,
  minimums) est toujours sauvegardé ; les crédits et leurs justificatifs le sont avec les
  données utilisateur. Les identifiants de type et d'EP du catalogue sont remappés, l'activité
  « Gestion des stages » désignée aussi ; les dates ne sont pas décalées. Jusqu'ici
  `FEATURE_BACKUP_MOODLE2` était annoncé sans implémentation : une copie de cours produisait une
  activité vide.
- **Accès simultanés** :
  - validation, refus, retrait et modification d'une déclaration ne sont écrits que si le crédit
    est toujours dans l'état lu à l'ouverture de la page (`ep_update_credit_if()`) ; l'utilisateur
    dont l'écriture n'a pas eu lieu en est averti (« Cette demande a changé entre-temps ») ;
  - l'inscription à un EP du catalogue recompte les places et l'inscription en cours sous le
    verrou de l'activité (`ep_get_activity_lock()`) : la dernière place n'est plus donnée deux
    fois, un double envoi ne crée plus deux inscriptions ;
  - la synchronisation des EP de type stage se fait sous ce même verrou, et la synchronisation
    d'opportunité des pages relit la date de dernière synchronisation sous le verrou : deux
    pages ouvertes ensemble ne créaient plus les crédits manquants en double.
- **Journaux** : un événement Moodle par transition (demande d'EP par inscription, déclaration,
  saisie DEVE ou attribution automatique ; modification ; validation avec les ECTS retenus ;
  refus ; annulation ; synchronisation depuis un stage ; consultation de l'activité).
- **Charge** : listes de validation et de pilotage paginées en base (comptage séparé, recherche et
  tri en SQL) ; bilan de promotion calculé à travers un cache (`progress_cache`) chargé en trois
  requêtes au lieu de trois par étudiant ; places prises, responsables et inscriptions de
  l'étudiant lus en une requête pour tout le catalogue ; index sur `ep_credit` (instance et
  statut, EP et statut, étudiant).
- **Gabarits Mustache** (`templates/`) et classe de sortie `list_filters` : filtres de liste,
  boutons d'action, badges de statut, tableau paginé ; partagés avec `mod_epsynthesis`.
- Confirmations sans JavaScript : page de confirmation pour la suppression d'un EP du catalogue,
  case à cocher contrôlée côté serveur pour le retrait d'un crédit par la DEVE. Les boutons
  `onclick="return confirm(...)"` recevaient une chaîne de langue non échappée.
- Un enseignant référent peut consulter tout le dossier de ses étudiants (y compris les
  inscriptions aux EP dont il n'est pas responsable), sans pouvoir statuer dessus.
- Paramètres de requête lus avec leur type exact (`PARAM_TEXT` au lieu de `PARAM_RAW`).
- Tests : sauvegarde et restauration, événements, accès simultanés, listes et cache, gabarits
  (74 tests).

### mod_epsynthesis
- Sauvegarde Moodle (instance et liens, remappés à la restauration sur le même site).
- Événement de consultation de l'activité ; filtres rendus par les gabarits de `mod_ep`.
- Générateur de tests et test de sauvegarde.

## 0.1.0 — mod_ep 2026090500, mod_epsynthesis 2026090500 (5 septembre 2026)

Première version : six types d'EP fixes, catalogue des EP internes avec places et responsables,
déclarations hors catalogue avec justificatifs, attribution automatique depuis les stages
complémentaires de `mod_stage`, plafonds par type, minimums annuels et de cursus, tableau de
pilotage, exports CSV, synthèse enseignant.
