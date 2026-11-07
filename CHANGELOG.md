# Historique des versions

Une section par version de module, la plus récente en tête. Les dates sont celles du commit qui
a fixé le numéro dans `version.php`. À tenir à jour à chaque modification de `version.php`.

## Modules syllabus 1.5.0 — mod_syllabus, mod_syllabusstaff, mod_syllabusstudent 2026112500 (25 novembre 2026)

Avis des étudiants, annuels et gardés en mémoire.

- « Avis des étudiants » remplace « évaluation du module » dans les trois modules (boutons,
  messages, états « Non prévu », « À venir », « Ouvert », « Terminé ») ; les « modalités
  d'évaluation » de la fiche restent les examens.
- Un recueil d'avis par année universitaire (`syllabus.evalyear`, premier mois de l'année réglé par
  la DEVE, septembre par défaut). Ouvrir le recueil dans une nouvelle année archive le précédent
  (table `syllabus_evalarchive` : résultats agrégés, rien sous le seuil d'anonymat) et repart de
  zéro, questions de l'enseignant gardées ; la réinitialisation du cours archive aussi avant
  d'effacer.
- Résultats d'un module par année, avec l'évolution des moyennes d'une année à l'autre ; résultats
  comparés de la DEVE pour une année passée ; archives dans la sauvegarde et la restauration,
  effacées avec l'activité.
- 4 tests unitaires de plus (48) ; scénario de bout en bout étendu à une nouvelle année.

## Modules syllabus 1.4.0 — mod_syllabus, mod_syllabusstaff, mod_syllabusstudent 2026112400 (24 novembre 2026)

Parcours simplifiés après un audit d'ergonomie, et tests à l'échelle d'une école
(`docs/audit/test-syllabus-charge.pdf`).

- **Étudiants** : formulaire d'évaluation compact (échelles sur une ligne), rappel « une seule
  réponse », retour à « Mes modules » après l'envoi ; « Mes modules » rangé par année et période ;
  état « Terminée » au lieu de « Close ».
- **Enseignants** : la page du module résume « Où en est ce module » (fiche, compétences,
  questions, évaluation, caractéristiques manquantes) avec un bouton par étape ; brouillon
  enregistré sans quitter la rédaction ; soumission refusée sans objectifs, acquis, contenu,
  modalités d'évaluation et séances ; séances balisées, langue préremplie, travail personnel avec
  les séances ; période choisie parmi celles de l'école ; la DEVE n'est plus proposée comme
  responsable ni intervenante ; compétences sans tableau en double, domaines visés par leurs
  sous-compétences ; message des questions figées selon l'état réel ; résultats avec taux et
  tableau des moyennes ; navigation dans l'ordre du travail ; titres d'onglet distincts.
- **DEVE** : décision de relecture sous la fiche, rubriques modifiées signalées, retour à
  « Fiches à relire » ; liste à relire avec responsables et nature de la version ; fenêtre en lot
  avec ouverture obligatoire et bouton distinct (confirmé) pour retirer l'évaluation, cases
  grisées hors droits ; suppressions confirmées ; saisie des compétences enchaînée sous le même
  parent avec code proposé ; matrice « Toutes les années » et couverture des domaines ; modules
  cliquables dans les résultats comparés.
- **Performances** : fiches publiées et responsables lus en groupe dans la progression et le
  livret (trois fois moins de requêtes).
- 12 tests unitaires de plus (44) ; banc : jeu de données d'une école (60 modules, 1 000
  étudiants), chronométrage, paliers jusqu'à 400 utilisateurs, endurance, ruée de 1 000
  évaluations simultanées, traitements lourds simultanés ; scénario de bout en bout 44/44.

## Modules syllabus 1.3.0 — mod_syllabus, mod_syllabusstaff 2026112300 (23 novembre 2026)

Livret des syllabus et progression des compétences le long du cursus.

- **mod_syllabusstaff** : page « Livret des syllabus » (enseignants et DEVE) : édition de tout le
  cursus, d'une année d'étude ou d'une période ; aperçu des modules et des crédits par période ;
  PDF avec page de titre, sommaire, tableau des modules et des crédits, puis la fiche publiée de
  chaque module dans l'ordre du cursus ; livret conservé dans l'activité tant que son contenu ne
  change pas.
- **mod_syllabusstaff** : page « Progression d'une compétence » : frise des périodes avec les
  niveaux visés, constats (première introduction, approfondissement, maîtrise ; alertes), modules
  avec leurs acquis visés, méthodes et modalités d'évaluation, cours sans module, export CSV ;
  ouverte depuis la matrice, dont les colonnes suivent désormais l'ordre du cursus.
- **mod_syllabus** : fonctions du cursus (ordre des périodes, choix d'édition, constats), livret
  PDF (`booklet_pdf`), fiche PDF réutilisable pour plusieurs fiches.
- 5 tests unitaires ; banc réparti sur trois années et six semestres, scénario étendu (42/42).

## Modules syllabus 1.2.0 — mod_syllabus, mod_syllabusstaff 2026112200 (22 novembre 2026)

Référentiels de compétences tenus dans le suivi des modules, sans l'administration du site.

- **mod_syllabus** : référentiels propres (tables `syllabus_framework`, `syllabus_comp` en arbre,
  `syllabus_complink` avec le niveau) à la place des compétences de Moodle ; liaison par les
  responsables du module ou la DEVE, sans droit de Moodle ; liens d'un cours supprimé effacés ;
  sauvegarde et restauration des niveaux (compétence retrouvée par les codes du référentiel et de
  la compétence). Mise à jour depuis la 1.1.0 : référentiels utilisés et liens recopiés, table
  `syllabus_competency` supprimée.
- **mod_syllabusstaff** : page « Référentiels de compétences » pour la DEVE (référentiels, arbre
  des compétences avec ajout, modification, déplacement et suppression, import CSV en deux passes,
  réimport pour mise à jour) ; liaison des cours sans module ; matrice étendue à ces cours
  (« * »).
- Libellés « Monter », « Descendre », « Renommer » traduits dans le module.
- Tests unitaires des compétences réécrits (5) ; scénario de bout en bout étendu (32/32).

## Modules syllabus 1.1.0 — mod_syllabus, mod_syllabusstaff 2026112100 (21 novembre 2026)

Référentiel de compétences.

- Le référentiel est celui de Moodle (core_competency : référentiels en arbre, import CSV) ;
  modules et cours y sont liés par l'API de Moodle (compétence liée au cours, puis à
  l'activité), et servent donc aussi aux plans de formation.
- **mod_syllabus** : page « Compétences » du module, liaison référentiel par référentiel avec un
  niveau de contribution (introduite, approfondie, maîtrisée ; table `syllabus_competency`) ;
  compétences visées sur la fiche et dans son PDF ; sauvegarde et restauration des niveaux
  (compétence retrouvée par les identifiants du référentiel et de la compétence).
- **mod_syllabusstaff** : matrice compétences × cours du périmètre (niveau du module, ou lien
  au cours seul), couverture du référentiel, compétences non couvertes signalées, filtre par
  année d'étude, export CSV.
- 5 tests unitaires ; scénario de bout en bout étendu (29/29).

## Modules syllabus 1.0.0 — mod_syllabus, mod_syllabusstaff, mod_syllabusstudent 2026112000 (20 novembre 2026)

Trois nouveaux modules pour les modules d'enseignement du cursus
(`docs/guides/guide-syllabus.pdf`, `docs/audit/test-syllabus.pdf`).

- **mod_syllabus**, une activité par cours de module : caractéristiques (code, ECTS, période,
  année, type de module) ; fiche au modèle ECTS reprise d'EP Manager (rubriques, séances sans
  date, volume horaire), versionnée, relue par la DEVE si le référentiel l'exige, PDF ;
  responsables ; évaluation par les étudiants à partir d'une date, sur les questions communes
  de la DEVE (tous les modules ou un type) et celles de l'enseignant, figées à l'ouverture ;
  réponses anonymes, une par étudiant ; résultats au-delà d'un seuil, visibles des
  responsables après la fermeture (réglable) ; export CSV ; message d'ouverture ; RGPD,
  sauvegarde, réinitialisation.
- **mod_syllabusstaff**, dans un cours d'équipe : mes modules ; pour la DEVE, tous les modules
  (filtres, fenêtre d'évaluation en lot), fiches à relire, résultats comparés par question
  commune, référentiel commun.
- **mod_syllabusstudent**, dans le cours de la promotion : modules de l'étudiant, fiches,
  évaluations à faire.
- 22 tests unitaires ; scénario web de bout en bout (23 contrôles) ; CI étendue.

## 1.0.5 — mod_ep 2026111900, mod_epsynthesis 2026111600 (19 novembre 2026)

Liaison avec « Gestion des stages » (mod_stage), vérifiée de bout en bout
(`docs/audit/banc/liaison_stage.py`, rapport `test-des-interactions.pdf`).

### Module
- **EP de type stage recalculés dès qu'un stage change** : une empreinte des stages
  complémentaires validés (nombre, durées retenues, années, mobilité, dates de modification) et du
  barème est comparée à celle du dernier calcul (colonne `ep.stagesyncsig`, 1 à 2 ms). Avant, le
  recalcul n'avait lieu qu'à l'ouverture de trois pages, au plus toutes les cinq minutes, et la
  nuit.
- **Validation des années et supplément au diplôme** : ils attendent ce recalcul. Avant, un stage
  validé dans « Gestion des stages » juste avant n'y comptait pas (année refusée à tort,
  supplément émis sans ce stage) tant qu'aucune page du module ne l'avait recalculé.
- Recalcul aussi à l'ouverture des pages Validation, Validation des années, Émission des
  suppléments et Rentrée.

## 1.0.4 — mod_ep 2026111800, mod_epsynthesis 2026111600 (18 novembre 2026)

### Module
- **Programme des séances d'un syllabus sans date** : le syllabus décrit l'EP, pas le calendrier
  d'une session. La date disparaît du formulaire, de la fiche, du PDF et du livret, de l'import
  CSV des séances (une colonne « Date » d'un ancien fichier est signalée et ignorée), de la
  sauvegarde et de l'export RGPD ; la mise à niveau supprime la colonne `sessiondate` de
  `ep_session`.

### Documentation
- Guide utilisateur 1.1 : qui valide un syllabus (la DEVE ; publication directe des mises à jour
  si elle l'a choisie ; imports publiés directement) ; captures du programme des séances et des
  propositions refaites. Guide enseignant 1.1. Formats des imports et exemple `seances.csv` sans
  colonne « Date ».

## 1.0.3 — mod_ep 2026111700, mod_epsynthesis 2026111600 (17 novembre 2026)

Test des interactions de bout en bout d'une promotion de 1 000 étudiants
(`docs/audit/test-des-interactions.pdf`) et guide utilisateur complet.

### Module
- **Participants d'un EP** : le barème (« Seuil de validation : 10 / 20 ») et les dates de la
  feuille d'émargement affichaient l'entité `&nbsp;` en clair ; même défaut dans la colonne de
  résultat de la prévisualisation d'un import de notes.
- **Vérifier un supplément** : le titre de la page n'est plus affiché deux fois.

### Déploiement (documenté dans `mod/ep/INSTALL.md`)
- Avec APCu, associer la définition `core/coursemodinfo` au magasin fichier : APCu n'ayant pas de
  verrouillage natif, Moodle 4.5 refusait aussitôt (« Unable to acquire a lock for caching ») une
  partie des premières connexions simultanées après un redémarrage de PHP-FPM ou une purge des
  caches (12 à 14 sur 40) ; aucune avec ce réglage, sans perte de débit.

### Documentation
- `docs/guides/guide-utilisateur.pdf` : tous les rôles en un document (principes, étudiant,
  enseignant, DEVE, administrateur, questions fréquentes), captures prises sur la promotion de
  1 000 étudiants du test ; captures des trois guides par rôle refaites.
- Banc : `interactions.py` (scénario complet, contrôles en base, phases rejouables),
  `muc_modinfo.php`.

## 1.0.2 — mod_ep 2026111600, mod_epsynthesis 2026111600 (16 novembre 2026)

Optimisations supplémentaires, mesurées sur le banc du test de stress.

### Module
- **Synthèse enseignant, liste « Validation »** : une seule requête couvrant toutes les promotions
  (droits propres à chacune), triée et paginée par la base ; seules les lignes affichées sont
  mises en forme (101 → 87 ms pour six promotions, 143 ms avant la 1.0.1).
- **Synthèse** : activités liées lues en une requête, module de cours et contexte tirés des
  informations de cours déjà en cache (calcul des promotions actives 7,6 → 3 ms).
- **Pages secondaires** : la description de l'activité n'est plus mise en forme pour être
  aussitôt masquée (4 à 6 ms par page).
- **Inscription directe depuis le catalogue** : année d'étude choisie sur la ligne de l'EP, sans
  page de confirmation ; retour à la même page de la liste. Ruée de 300 inscriptions simultanées :
  15,3 → 11,6 s (médiane par étudiant 13,0 → 9,2 s).

### Déploiement (documenté dans `mod/ep/INSTALL.md`)
- APCu comme cache applicatif : +11 à +18 % ; JIT de PHP : +5 à +8 % ; OPcache sans vérification
  des fichiers : ≈ +2 %.
- Débit d'un serveur de 4 cœurs : 59 à 61 pages par seconde de 25 à 400 utilisateurs simultanés,
  contre 37 à 44 avant le test de stress (+40 à +60 %), toujours sans erreur.

## 1.0.1 — mod_ep 2026111500, mod_epsynthesis 2026111400 (15 novembre 2026)

Test de stress et optimisations.

- **Test de stress** sur un serveur de type production (nginx, PHP-FPM, PostgreSQL réglé), six
  promotions (10 000 étudiants, 538 000 crédits) : montée en charge de 25 à 400 utilisateurs
  simultanés sans temps de réflexion, endurance de 30 minutes, traitements lourds simultanés
  (exports complets, livrets, émission groupée, restauration) sous charge. Aucune erreur, aucune
  dérive ; rapport `docs/audit/test-de-stress.pdf`, outils `docs/audit/banc/stress.py`,
  `profil.py`, `lourds.py`.
- **Index** (promotion, EP, état) et (promotion, état, date) sur les crédits : places prises et
  états par EP lus sur l'index seul (10 à 17 ms → 2,6 ms), file de la DEVE lue dans l'ordre de
  l'index (19 ms → 0,3 ms) ; total des listes sans jointure inutile (17 ms → 1,5 ms).
- Synthèse enseignant : libellés calculés une fois par type, noms seulement pour les lignes
  affichées (143 ms → 113 ms pour six promotions).
- Participants d'un EP : étudiants à ajouter lus par recherche et limite, et non plus toute la
  promotion à chaque affichage.
- Libellés d'état et d'année mis en mémoire pour la requête.
- Débit global +27 à +35 % avec les connexions persistantes à la base, recommandées dans
  `mod/ep/INSTALL.md` (section « Dimensionnement et réglages du serveur »).

## 1.0.0 — mod_ep 2026111400, mod_epsynthesis 2026111400 (14 novembre 2026)

Lots 8 et 9 : banc de charge et de concurrence, parcours de bout en bout, revue de sécurité,
documentation. Les deux modules passent en maturité stable.

### Performance (banc d'une promotion de 2 000 étudiants, 200 EP, 60 000 crédits)
- Files d'attente paginées en base : demandes à valider (la DEVE en avait 7 000 sur une page),
  éléments du supplément ; prérequis du catalogue et des participants calculés en un nombre fixe
  de requêtes ; preuves chargées en une requête.
- Export complet écrit en flux (classeur OpenSpout, données JSON en un passage) : 10 s et
  100 Mo au lieu de 34 s et plus de 1 Go ; restauration en 15 s au lieu de 7 min.
- Livret PDF du catalogue conservé tant qu'il est à jour (0,03 s au lieu de 5 s).
- Toutes les pages sous 0,2 s côté serveur, avec un nombre de requêtes indépendant de la taille
  de la promotion. Rapports : `docs/audit/test-de-charge.pdf`, `docs/audit/test-de-concurrence.pdf`.

### Sécurité
- Supplément au diplôme : sceau HMAC du site sur chaque émission et contrôle de l'intégrité du
  PDF à la vérification ; une copie restaurée ou importée n'est plus reconnue ; la page de
  vérification est publique (sans compte).
- Une déclaration ou un élément du supplément validé à une première étape ne se modifie plus
  (il se retire encore) ; le responsable d'un EP ne retire une inscription qu'à son étape ; les
  ECTS retenus par un responsable ou un référent sont bornés aux ECTS demandés.
- Synthèse : seules les promotions où l'enseignant a une inscription active sont prises en compte.
- Imports et restauration : valeurs normalisées, contrôle de l'archive avant extraction, taille
  maximale des fichiers déposés ; à la restauration, un lien vers l'activité d'un autre cours
  n'est gardé que si la personne qui restaure y a les droits ; transfert d'un étudiant seulement
  vers un cours où il est inscrit.
- Risques déclarés pour les capacités sensibles.

### Ergonomie (parcours de bout en bout)
- Catalogue : recherche, pagination, tri naturel ; message d'inscription impossible dès
  l'affichage quand un prérequis bloquant manque.
- Éléments du supplément et ajout de participants : recherche par étudiant.
- Syllabus : fiche PDF de la version en cours de rédaction, historique des versions.
- Tableau de bord de l'étudiant : décision de la DEVE sur chaque année.
- Titre et description de l'activité affichés une seule fois (en-tête d'activité de Moodle) ;
  boutons « Envoyer la demande » ; plus de mention TCPDF dans les PDF.

### Documentation
- Guides LaTeX avec captures : `docs/guides/guide-parametrage.pdf` (DEVE),
  `guide-enseignant.pdf`, `guide-etudiant.pdf` ; captures rejouables (`docs/guides/captures.js`).
- `README.md` et `mod/ep/INSTALL.md` réécrits.
- Tests : 121 tests mod_ep, 7 tests mod_epsynthesis, sous PostgreSQL et MariaDB.

## 0.8.0 — mod_ep 2026111200, mod_epsynthesis 2026111200 (12 novembre 2026)

Lot 7 : synthèse enseignant, données personnelles, réinitialisation du cours.

### mod_epsynthesis
- **Page « À traiter »** (`todo.php`), combinée sur toutes les promotions liées : EP dont
  l'enseignant est responsable avec des inscrits en attente de sa décision, propositions et
  révisions de syllabus à reprendre ou en attente de la DEVE, éléments du supplément au diplôme
  à trancher comme référent (décision directe dans la page). Compteur dans la navigation commune
  et résumé sur la page d'accueil de la synthèse. Requêtes groupées, liste paginée.
- Correction : `view.php` utilisait l'instance sans l'avoir chargée.

### mod_ep
- **Données personnelles** : le fournisseur de confidentialité couvre toutes les tables
  (crédits et historique des décisions, éléments du supplément, décisions d'année, suppléments
  émis, responsables, syllabus, séances, journal des imports) et toutes les zones de fichiers.
  L'export distingue le dossier de l'étudiant et les actions du personnel, sans nommer les autres
  personnes ; l'effacement supprime le dossier de l'étudiant et désolidarise les décisions et
  documents qu'un personnel a produits pour d'autres.
- **Réinitialisation du cours** : effacement au choix des dossiers des étudiants, des décisions
  d'année, des éléments et suppléments émis (non coché par défaut : documents certifiés) et du
  journal des imports ; le paramétrage et le catalogue sont conservés.
- Le commentaire du référent sur un élément du supplément est conservé pour la DEVE.
- Chaînes manquantes des aides « Type » et « ECTS » du formulaire d'EP.
- Schéma : plus de valeur par défaut vide sur les champs texte obligatoires (sans effet sur les
  données).
- Tests : confidentialité (métadonnées, contextes, export, effacements), réinitialisation
  (116 tests mod_ep, 6 tests mod_epsynthesis).

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
