<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * French strings for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Enseignement personnalisé';
$string['modulename'] = 'Enseignement personnalisé';
$string['modulenameplural'] = 'Enseignements personnalisés';
$string['modulename_help'] = "Gère les ECTS d'enseignement personnalisé (EP) d'une promotion : le catalogue des EP "
    . "académiques internes auxquels les étudiants s'inscrivent, les EP déclarés hors catalogue (engagement "
    . "étudiant, expérience professionnelle, sport, académique externe) et les EP de type stage, attribués "
    . "automatiquement d'après les stages complémentaires déjà validés par la DEVE dans l'activité « Gestion des "
    . "stages » du même cours.\n\nChaque type a son propre plafond d'ECTS retenus (par année et sur le cursus), et "
    . "des minimums sont exigés par année d'étude et sur l'ensemble du cursus. Les inscriptions au catalogue sont "
    . "validées par le responsable de l'EP concerné, les déclarations hors catalogue par l'enseignant référent de "
    . "l'étudiant.";
$string['modulename_link'] = 'mod/ep/view';
$string['pluginadministration'] = 'Administration de l\'enseignement personnalisé';
$string['epname'] = 'Nom de l\'activité';

// Capacités.
$string['ep:addinstance'] = 'Ajouter une activité Enseignement personnalisé';
$string['ep:view'] = 'Voir l\'activité Enseignement personnalisé';
$string['ep:submit'] = 'S\'inscrire à un EP et déclarer ses propres EP';
$string['ep:evaluateteacher'] = 'Valider les EP dont on a la charge (responsable d\'EP, enseignant référent)';
$string['ep:validatedeve'] = 'Valider et retirer les EP de tous les étudiants (DEVE)';
$string['ep:manage'] = 'Paramétrer les types, le catalogue et les minimums d\'ECTS';
$string['ep:viewall'] = 'Voir les EP de tous les étudiants';

// Navigation et pages.
$string['administration'] = 'Administration';
$string['catalog'] = 'Catalogue des EP';
$string['declarecredit'] = 'Déclarer un EP';
$string['validation'] = 'Validation';
$string['pilotage'] = 'Tableau de pilotage';
$string['exportcsv'] = 'Export CSV';
$string['mycredits'] = 'Mes enseignements personnalisés';
$string['creditdetail'] = 'Détail de l\'enseignement personnalisé';
$string['managetypes'] = 'Types d\'EP et plafonds';
$string['managecatalog'] = 'Catalogue des EP internes';
$string['manageyearrequirements'] = 'Minimums d\'ECTS';
$string['actions'] = 'Actions';
$string['viewdetails'] = 'Détail';
$string['searchstudent'] = 'Nom de l\'étudiant';
$string['resetfilters'] = 'Réinitialiser les filtres';
$string['student'] = 'Étudiant';
$string['teacher'] = 'Enseignant';
$string['status'] = 'Statut';
$string['hidden'] = 'masqué';
$string['enabled'] = 'Activé';

// Paramètres de l'instance.
$string['currentstudyyear'] = 'Année d\'étude courante';
$string['currentstudyyear_help'] = "Année d'étude dans laquelle se trouve la promotion. Elle sert de référence : "
    . "seuls les minimums de cette année et des années précédentes sont présentés comme exigibles, et l'étudiant "
    . "peut rattacher un EP à l'année précédente (rattrapage) ou suivante (anticipation).";
$string['mincursusects'] = 'Minimum sur l\'ensemble du cursus';
$string['mincursusects_help'] = "Nombre total d'ECTS d'enseignement personnalisé à valider sur l'ensemble du "
    . "cursus, tous types et toutes années confondus (0 = aucune obligation). Ce minimum s'ajoute aux minimums "
    . "annuels : les atteindre chaque année ne suffit pas nécessairement à atteindre celui du cursus.";
$string['stagesource'] = 'Activité « Gestion des stages » d\'origine';
$string['stagesource_help'] = "Activité dont les stages complémentaires (EP) validés par la DEVE donnent lieu à une "
    . "attribution automatique d'ECTS. « Toutes celles du cours » convient tant que le cours n'en porte qu'une ; "
    . "désigner une activité précise sert aux cours qui en portent plusieurs, pour ne pas recompter les stages "
    . "d'une activité d'archive.";
$string['stagesourceall'] = 'Toutes celles du cours';
$string['typesnotice'] = "Les six types d'enseignement personnalisé (académique interne, stage, engagement "
    . "étudiant, expérience professionnelle, sport, académique externe) sont créés avec l'activité. Leurs plafonds "
    . "d'ECTS et le barème des stages se règlent ensuite depuis l'administration de l'activité.";

// Types d'EP.
$string['type'] = 'Type';
$string['type_help'] = "Le type détermine le plafond d'ECTS applicable et qui valide la demande. Les EP "
    . "académiques internes se prennent au catalogue, et les EP de type stage sont attribués automatiquement : ni "
    . "les uns ni les autres ne se déclarent ici.";
$string['type_academic'] = 'Académique interne';
$string['type_stage'] = 'Stage';
$string['type_engagement'] = 'Engagement étudiant';
$string['type_professional'] = 'Expérience professionnelle';
$string['type_sport'] = 'Sport';
$string['type_external'] = 'Académique externe';
$string['typeattribution'] = 'Attribution';
$string['attributionautomatic'] = 'Automatique';
$string['attributioncatalog'] = 'Inscription au catalogue';
$string['attributiondeclared'] = 'Déclaration de l\'étudiant';
$string['typeinstruction'] = 'Consigne affichée à l\'étudiant';
$string['typessaved'] = 'Types d\'EP enregistrés.';
$string['managetypes_desc'] = "Libellé, consigne, activation, maximum d'ECTS retenus (par année et sur le cursus) "
    . "et, pour le type stage, nombre d'ECTS par jour de stage complémentaire validé.";
$string['maxects'] = 'Maximum sur le cursus';
$string['maxectsperyear'] = 'Maximum par année';
$string['maxectsshort'] = 'max. {$a} ECTS';
$string['ectsperday'] = 'ECTS par jour de stage';
$string['syncstagecredits'] = 'Resynchroniser les EP de type stage';
$string['syncstagecredits_help'] = "Recalcule immédiatement les ECTS attribués automatiquement d'après les stages "
    . "complémentaires validés par la DEVE. Ce recalcul est de toute façon fait dès qu'un stage ou le barème change "
    . "(à l'ouverture suivante d'une page de l'activité, avant la validation des années et l'émission d'un "
    . "supplément) et chaque nuit : ce bouton n'est utile que pour vérifier le résultat.";
$string['syncdone'] = 'Synchronisation terminée : {$a->created} crédit(s) créé(s), {$a->updated} mis à jour, '
    . '{$a->deleted} retiré(s).';
$string['tasksyncstagecredits'] = 'Attribution des ECTS d\'EP issus des stages complémentaires';

// Catalogue.
$string['catalogactivity'] = 'EP';
$string['catalogactivitytype'] = 'Type';
$string['catalogactivitytype_help'] = "Type auquel rattacher cet EP : c'est lui qui détermine le plafond d'ECTS "
    . "applicable à l'étudiant. Il s'agit normalement du type académique interne.";
$string['catalogactivityects'] = 'Crédits (ECTS)';
$string['catalogactivityects_help'] = "Nombre d'ECTS attribués à l'étudiant lorsque le responsable valide son "
    . "inscription. Il est propre à cet EP : l'étudiant ne le choisit pas.";
$string['catalogactivityvisible_help'] = "Un EP masqué n'accepte plus de nouvelle inscription mais conserve celles "
    . "déjà prises. C'est ainsi qu'on ferme un EP qui n'est plus proposé, sans effacer les ECTS déjà accordés.";
$string['ects'] = 'ECTS';
$string['capacity'] = 'Nombre de places';
$string['capacity_help'] = "0 signifie « places illimitées ». Les inscriptions en attente de validation occupent "
    . "une place : ouvrir plus de places que ce que le responsable pourra valider reviendrait à en promettre une "
    . "qui n'existe pas.";
$string['places'] = 'Places';
$string['placesleft'] = '{$a->left} sur {$a->total}';
$string['unlimitedplaces'] = 'Illimitées';
$string['registrations'] = 'Inscrits';
$string['openforregistration'] = 'Ouvert';
$string['addactivity'] = 'Ajouter un EP au catalogue';
$string['activitysaved'] = 'EP enregistré. Désignez maintenant son ou ses responsables.';
$string['activitydeleted'] = 'EP supprimé du catalogue.';
$string['nocatalogactivities'] = 'Aucun EP n\'est proposé au catalogue pour l\'instant.';
$string['managecatalog_desc'] = "Les EP académiques internes proposés aux étudiants : intitulé, ECTS, années "
    . "d'étude concernées, nombre de places et responsables qui valideront les inscriptions.";
$string['activityteachers'] = 'Responsables';
$string['activityteachersfor'] = 'Responsables de : {$a}';
$string['activityteacherscount'] = '{$a} responsable(s)';
$string['activityteacherssaved'] = 'Responsables enregistrés.';
$string['activityteachers_help'] = "Cochez les enseignants responsables de cet EP. Ce sont eux, et eux seuls, qui "
    . "valident les inscriptions à cet EP — l'enseignant référent de l'étudiant n'a pas la main dessus.";
$string['responsible'] = 'Responsable';
$string['noteacherwarning'] = 'aucun responsable';
$string['nopotentialteachers'] = "Aucun enseignant du cours n'a le droit de valider des EP : attribuez d'abord un "
    . "rôle d'enseignant dans ce cours.";
$string['studyyearrange'] = 'Années concernées';
$string['minstudyyear'] = 'Année minimale';
$string['maxstudyyear'] = 'Année maximale';
$string['sortorder'] = 'Ordre d\'affichage';

// Inscription au catalogue.
$string['register'] = 'S\'inscrire';
$string['registertoactivity'] = 'Inscription à : {$a}';
$string['registerects'] = 'Cet EP donne droit à {$a} ECTS.';
$string['registerpendingnotice'] = "Votre inscription sera transmise au responsable de l'EP. Les ECTS ne vous "
    . "seront comptés qu'une fois qu'il l'aura validée.";
$string['confirmregistration'] = 'Confirmer mon inscription';
$string['registered'] = 'Inscription enregistrée, en attente de validation par le responsable.';
$string['registerclosed'] = 'Inscriptions fermées';
$string['nofreeplace'] = 'Complet';
$string['notopentoyear'] = 'Hors de votre année d\'étude';

// Déclaration hors catalogue.
$string['creditname'] = 'Intitulé';
$string['creditdescription'] = 'Description et justification';
$string['creditdescription_help'] = "Décrivez ce que vous avez fait, quand, et à quel titre. C'est sur cette "
    . "description et sur vos justificatifs que votre enseignant référent se prononcera.";
$string['claimedects'] = 'ECTS demandés';
$string['claimedects_help'] = "Nombre d'ECTS que vous demandez pour cet EP. Le validateur peut n'en retenir qu'une "
    . "partie sans refuser pour autant toute votre demande.";
$string['evidencefiles'] = 'Justificatifs';
$string['evidencefiles_help'] = "Attestations, conventions, diplômes... tout document permettant de vérifier ce "
    . "que vous déclarez.";
$string['noevidencefiles'] = 'Aucun justificatif déposé.';
$string['creditsubmitted'] = 'Demande enregistrée, en attente de validation.';
$string['cancelrequest'] = 'Retirer ma demande';
$string['requestcancelled'] = 'Demande retirée.';
$string['cancelledbystudent'] = 'Demande retirée par l\'étudiant.';
$string['declarereferent'] = 'Votre demande sera transmise à votre enseignant référent : {$a}.';
$string['declarenoreferent'] = "Aucun enseignant référent ne vous est attribué dans l'activité « Gestion des "
    . "stages » de ce cours : votre demande sera traitée par la DEVE.";
$string['studentreferents'] = 'Enseignant(s) référent(s) : {$a}';

// Validation.
$string['awaitingmydecision'] = 'En attente de votre décision';
$string['allcreditsinscope'] = 'Tous les EP de votre périmètre';
$string['nopendingcredits'] = 'Aucune demande n\'attend votre décision.';
$string['nocredits'] = 'Aucun enseignement personnalisé.';
$string['validatecredit'] = 'Valider';
$string['validate'] = 'Valider';
$string['reject'] = 'Refuser';
$string['decision'] = 'Décision';
$string['validatorcomment'] = 'Commentaire';
$string['decidedby'] = 'Décision prise par';
$string['decidedon'] = 'Date de la décision';
$string['creditvalidated'] = 'EP validé.';
$string['creditrejected'] = 'EP refusé.';
$string['creditcancelled'] = 'EP retiré.';
$string['creditalreadydecided'] = 'Cette demande a déjà été traitée.';
$string['cancelcredit'] = 'Retirer cet EP';
$string['cancelreason'] = 'Motif du retrait';
$string['fromcatalogactivity'] = 'Inscription à l\'EP « {$a->name} » — responsable(s) : {$a->teachers}';
$string['automatic'] = 'automatique';
$string['automaticattribution'] = 'Attribution automatique';
$string['automaticcreditnotice'] = "Cet EP est attribué automatiquement d'après un stage complémentaire déjà "
    . "validé par la DEVE : il n'y a rien à valider ici. Pour le corriger ou le retirer, c'est le stage d'origine "
    . "qu'il faut reprendre dans l'activité « Gestion des stages ».";
$string['submittedon'] = 'Demandé le';
$string['pendingrequests'] = 'Demandes en attente';
$string['origin'] = 'Origine';
$string['source_student'] = 'Étudiant';
$string['source_stage'] = 'Stage complémentaire';
$string['source_deve'] = 'DEVE';

// Statuts.
$string['status_cancelled'] = 'Retiré';
$string['status_rejected'] = 'Refusé';
$string['status_pending'] = 'En attente';
$string['status_validated'] = 'Validé';
$string['allstatuses'] = 'Tous les statuts';
$string['alltypes'] = 'Tous les types';
$string['allyears'] = 'Toutes les années';

// Années d'étude.
$string['studyyear'] = 'Année d\'étude';
$string['studyyear_help'] = "Année d'étude à laquelle rattacher cet EP : c'est elle qui détermine le minimum "
    . "annuel auquel il comptera.";
$string['studyyear_unspecified'] = 'Non précisée';
$string['studyyear_n'] = 'A{$a}';

// Bilans.
$string['summary'] = 'Synthèse';
$string['summaryitem'] = 'Élément';
$string['summaryvalue'] = 'Valeur';
$string['summarytotalretained'] = 'Total d\'ECTS retenus';
$string['summarycursusminimum'] = 'Minimum de cursus';
$string['summaryyearsdone'] = 'Années validées';
$string['summarypending'] = 'ECTS en attente de validation';
$string['summarycapped'] = 'ECTS validés non retenus (plafond de type atteint)';
$string['yeartotals'] = 'Bilan par année d\'étude';
$string['typetotals'] = 'Bilan par type';
$string['allmycredits'] = 'Détail des enseignements personnalisés';
$string['objective'] = 'Objectif';
$string['yearminimum'] = 'Minimum de l\'année';
$string['objectivedone'] = 'Atteint';
$string['objectivetodo'] = 'À compléter';
$string['requiredects'] = 'ECTS requis';
$string['retainedects'] = 'ECTS retenus';
$string['remainingects'] = 'Reste à valider';
$string['validatedects'] = 'ECTS validés';
$string['cappedects'] = 'Non retenus';
$string['cappedshort'] = '{$a} non retenus';
$string['pendingects'] = 'En attente';
$string['ectsvalue'] = '{$a} ECTS';
$string['progressofects'] = '{$a->retained} / {$a->required} ECTS';
$string['totalretainedshort'] = 'ECTS retenus';
$string['cursusminimumshort'] = 'Minimum de cursus';
$string['yearsdoneshort'] = 'Années validées';
$string['noyearminimum'] = 'Aucun minimum annuel';
$string['nostudents'] = 'Aucun étudiant à afficher.';
$string['numcredits'] = '{$a} enseignement(s) personnalisé(s)';
$string['cursusminimum'] = 'Minimum sur l\'ensemble du cursus';
$string['cursusminimum_help'] = "S'ajoute aux minimums annuels ci-dessus : un étudiant peut avoir atteint chacun "
    . "de ses minimums annuels sans avoir encore atteint celui du cursus.";
$string['manageyearrequirements_help'] = "Nombre minimal d'ECTS d'enseignement personnalisé à valider pour chaque "
    . "année d'étude, tous types confondus. Une année laissée à 0 n'impose rien et n'apparaît pas comme un "
    . "objectif dans les bilans.";
$string['manageyearrequirements_desc'] = "Minimum d'ECTS à valider par année d'étude et sur l'ensemble du cursus.";
$string['requirementssaved'] = 'Minimums d\'ECTS enregistrés.';

// Attribution automatique depuis les stages.
$string['stagecreditname'] = 'Stage complémentaire — {$a->theme} ({$a->structure})';
$string['stagecreditdescription'] = 'Attribué automatiquement d\'après {$a->days} jour(s) de stage retenus par la '
    . 'DEVE, à raison de {$a->rate} ECTS par jour (activité « {$a->activity} »).';
$string['stagelinkheading'] = 'Origine des EP de type stage';
$string['stagelinklist'] = 'Les stages complémentaires (EP) validés par la DEVE sont lus dans : {$a}.';
$string['stagelinknone'] = "Aucune activité « Gestion des stages » n'est associée à cette activité : aucun EP de "
    . "type stage ne peut être attribué automatiquement. Ajoutez une activité « Gestion des stages » dans ce "
    . "cours, ou désignez-en une dans les paramètres de cette activité.";
$string['stagelinkrate'] = 'Barème actuel : {$a} ECTS par jour de stage retenu.';
$string['stagelinknorate'] = "Aucun barème n'est défini pour le type stage : aucun ECTS n'est attribué "
    . "automatiquement tant que le nombre d'ECTS par jour reste à 0.";

// Administration.
$string['adminsectionrules'] = 'Règles d\'attribution';
$string['adminsectioncatalog'] = 'Catalogue';
$string['adminsectionfollowup'] = 'Suivi';
$string['adminsectionpage'] = 'Page';
$string['adminsectionpurpose'] = 'À quoi elle sert';
$string['exportcsv_desc'] = "Bilan par étudiant ou détail de tous les EP, au format CSV.";
$string['exportstudents'] = 'Bilan par étudiant';
$string['exportstudents_desc'] = "Une ligne par étudiant : ECTS retenus par type, avancement des minimums annuels "
    . "et du minimum de cursus.";
$string['exportcredits'] = 'Détail des EP';
$string['exportcredits_desc'] = "Une ligne par EP porté au crédit d'un étudiant, avec son statut, sa décision et "
    . "son auteur.";

// Erreurs.
$string['errorpositiveects'] = 'Le nombre d\'ECTS doit être supérieur à 0.';
$string['errornegativeects'] = 'Le nombre d\'ECTS ne peut pas être négatif.';
$string['errornegativecapacity'] = 'Le nombre de places ne peut pas être négatif.';
$string['errorstudyyearrange'] = 'L\'année minimale ne peut pas être postérieure à l\'année maximale.';
$string['erroractivityinuse'] = "Cet EP ne peut pas être supprimé : des étudiants y sont inscrits. Fermez-le aux "
    . "inscriptions à la place.";
$string['errornotypes'] = "Aucun type d'EP n'est défini : paramétrez d'abord les types.";
$string['errorinvalidtype'] = 'Type d\'EP invalide.';
$string['errornodeclarabletype'] = "Aucun type d'EP ne peut être déclaré librement dans cette activité : les EP "
    . "académiques se prennent au catalogue et les EP de type stage sont attribués automatiquement.";
$string['errorcreditnoteditable'] = "Une inscription à un EP du catalogue ne se modifie pas : son intitulé "
    . "et ses ECTS sont ceux de l'EP. Vous pouvez seulement la retirer.";
$string['errorcreditdecided'] = "Cette demande a déjà été traitée : elle ne peut plus être modifiée ni retirée.";
$string['erroralreadyregistered'] = 'Vous êtes déjà inscrit à cet EP.';
$string['errorregisterclosed'] = 'Cet EP n\'accepte plus d\'inscription.';
$string['errornoplaceleft'] = 'Il n\'y a plus de place disponible sur cet EP.';
$string['errorwrongyear'] = 'Cet EP n\'est pas ouvert à cette année d\'étude.';
$string['errorevidencemissing'] = 'Ce justificatif est introuvable.';

// Confidentialité.
$string['privacy:path:credits'] = 'Enseignements personnalisés';

// Accès simultanés et confirmations.
$string['erroractivitybusy'] = "Une autre opération est en cours sur cette activité : réessayez dans quelques secondes.";
$string['errorcreditchanged'] = "Cette demande a changé entre-temps (décision prise, demande retirée) : rien n'a été enregistré. Vérifiez son état actuel.";
$string['errorconfirmcancel'] = 'Cochez la case de confirmation pour retirer cet EP.';
$string['confirmdeleteactivity'] = 'Supprimer définitivement l\'EP « {$a} » du catalogue ?';
$string['confirmcancelcredit'] = 'Je confirme le retrait de cet EP du dossier de l\'étudiant.';

// Événements.
$string['eventcreditcreated'] = 'Enseignement personnalisé demandé';
$string['eventcreditupdated'] = 'Enseignement personnalisé modifié';
$string['eventcreditvalidated'] = 'Enseignement personnalisé validé';
$string['eventcreditrejected'] = 'Enseignement personnalisé refusé';
$string['eventcreditcancelled'] = 'Enseignement personnalisé annulé';
$string['eventcreditsynced'] = 'Enseignement personnalisé synchronisé depuis un stage';

// Types paramétrés (lot 2).
$string['addtype'] = 'Ajouter un type';
$string['edittype'] = 'Modifier le type';
$string['typecode'] = 'Code court';
$string['typecode_help'] = "Identifiant du type dans les imports et les exports : lettres et chiffres sans accent, 20 caractères au plus, unique dans l'activité. Laissé vide, il est déduit du libellé.";
$string['typename'] = 'Libellé';
$string['acquisition'] = "Mode d'acquisition";
$string['acquisition_help'] = "Comment un EP de ce type arrive au dossier de l'étudiant : par inscription à un EP du catalogue (qui porte ses ECTS et ses responsables), par déclaration de l'étudiant (avec justificatifs), automatiquement d'après les stages complémentaires validés par la DEVE dans « Gestion des stages », ou par saisie de la DEVE seule (à la main ou par import).";
$string['acquisition_catalog'] = 'Inscription à un EP du catalogue';
$string['acquisition_declaration'] = "Déclaration par l'étudiant";
$string['acquisition_stage'] = 'Automatique, d\'après les stages complémentaires';
$string['acquisition_deve'] = 'Saisie par la DEVE';
$string['validationflow'] = 'Circuit de validation';
$string['validationflow_help'] = "Qui doit valider, et dans quel ordre. Chaque étape doit être validée pour passer à la suivante ; un refus à n'importe quelle étape est final. La DEVE peut toujours statuer, à n'importe quelle étape, et sa décision est finale. « Aucune » : l'EP est validé d'office dès la demande.";
$string['flow_none'] = 'Aucune : validé d\'office';
$string['role'] = 'Rôle';
$string['role_owner'] = 'Responsable de l\'EP';
$string['role_referent'] = 'Enseignant référent';
$string['role_deve'] = 'DEVE';
$string['role_student'] = 'Étudiant';
$string['awaitingrole'] = 'en attente : {$a}';
$string['grading'] = 'Évaluation';
$string['grading_help'] = "Sans note, le validateur valide ou non. Avec note, il saisit une note : l'EP est validé si elle atteint le seuil, non validé sinon ; la note est conservée dans les deux cas et figure au supplément au diplôme.";
$string['grading_none'] = 'Sans note (validé / non validé)';
$string['grading_grade'] = 'Avec note';
$string['grademax'] = 'Note maximale';
$string['gradepass'] = 'Seuil de validation';
$string['grade'] = 'Note';
$string['gradeoutof'] = '{$a->grade} / {$a->max}';
$string['errorgradeoutofrange'] = 'La note doit être comprise entre 0 et {$a}.';
$string['errorgradepass'] = 'Le seuil ne peut pas dépasser la note maximale.';
$string['evidence'] = 'Justificatifs';
$string['evidence_help'] = "Obligatoires : l'étudiant doit joindre au moins un fichier pour déposer sa demande.";
$string['evidence_none'] = 'Sans objet';
$string['evidence_optional'] = 'Facultatifs';
$string['evidence_required'] = 'Obligatoires';
$string['evidencetypes'] = 'Formats admis';
$string['evidencetypes_pdf'] = 'PDF seulement';
$string['evidencetypes_documents'] = 'Documents, images et archives';
$string['errorevidencerequired'] = 'Au moins un justificatif est obligatoire pour ce type d\'EP.';
$string['maxclaimedects'] = 'ECTS demandables au plus';
$string['maxclaimedects_help'] = "Types déclarés : nombre d'ECTS maximal qu'un étudiant peut demander pour un seul EP (0 = sans limite).";
$string['errormaxclaimedects'] = 'Pour ce type, un EP ne peut pas demander plus de {$a} ECTS.';
$string['stagescope'] = 'Stages pris en compte';
$string['stagescope_help'] = "Plusieurs types peuvent être alimentés par les stages, chacun avec son barème : un stage va au premier type, dans l'ordre d'affichage, dont la portée le couvre.";
$string['stagescope_all'] = 'Tous les stages complémentaires';
$string['stagescope_abroad'] = 'Stages à l\'étranger seulement';
$string['stagescope_domestic'] = 'Stages en France seulement';
$string['insupplement'] = 'Figure au supplément au diplôme';
$string['insupplement_help'] = 'Les EP validés de ce type sont portés en rubrique 4.3 du supplément au diplôme.';
$string['typesaved'] = 'Type enregistré.';
$string['typedeleted'] = 'Type supprimé.';
$string['confirmdeletetype'] = 'Supprimer définitivement le type « {$a} » ?';
$string['errortypeinuse'] = 'Ce type porte des EP ou des crédits : désactivez-le plutôt que de le supprimer.';
$string['typeenabled'] = 'Type activé.';
$string['typedisabled'] = 'Type désactivé.';
$string['moveup'] = 'Monter';
$string['movedown'] = 'Descendre';
$string['managetypes_help'] = "Chaque type décrit comment un EP arrive au dossier de l'étudiant, qui le valide, s'il est noté, quels justificatifs sont attendus et quels plafonds d'ECTS s'appliquent. Vous pouvez ajouter des types, les réordonner, les désactiver ; un type qui porte des EP ou des crédits ne se supprime pas.";
$string['decision_validated'] = 'Validé';
$string['decision_rejected'] = 'Refusé';
$string['decision_cancelled'] = 'Retiré';
$string['decisionhistory'] = 'Historique des décisions';
$string['validatestep'] = 'Valider cette étape';
$string['validatefinal'] = 'Valider définitivement';
$string['gradeandvalidate'] = 'Enregistrer la note';
$string['nextstepnotice'] = 'Votre validation transmettra la demande à l\'étape suivante : {$a}.';
$string['devefinalnotice'] = 'Votre décision est finale, quelle que soit l\'étape en cours.';
$string['stagelinkrates'] = 'Barèmes : {$a}';
$string['stagescoperate'] = '{$a->name} ({$a->scope}) : {$a->rate} ECTS par jour';

// Catalogue et participants (lot 3).
$string['activitystate'] = 'État';
$string['activitystate_help'] = "Publié : au catalogue, inscriptions ouvertes pendant la période d'inscription. Clos : toujours au catalogue, inscriptions closes, notes encore saisissables. Archivé : retiré du catalogue, les inscriptions restent au dossier des étudiants. Brouillon et proposé : proposition d'un enseignant, pas encore publiée.";
$string['activitystate_draft'] = 'Brouillon';
$string['activitystate_proposed'] = 'Proposé';
$string['activitystate_published'] = 'Publié';
$string['activitystate_closed'] = 'Clos';
$string['activitystate_archived'] = 'Archivé';
$string['activitycode'] = 'Code';
$string['activitycode_help'] = "Code court de l'EP (par exemple EP-ECHO-3), unique dans l'activité : il identifie l'EP dans les imports et les exports.";
$string['erroractivitycodeused'] = 'Ce code est déjà utilisé par un autre EP de cette activité.';
$string['registrationstart'] = 'Ouverture des inscriptions';
$string['registrationend'] = 'Fermeture des inscriptions';
$string['registrationperiod'] = 'Inscriptions du {$a->start} au {$a->end}';
$string['registrationfrom'] = 'Inscriptions à partir du {$a}';
$string['registrationuntil'] = 'Inscriptions jusqu\'au {$a}';
$string['errorregistrationperiod'] = 'La fermeture des inscriptions doit suivre leur ouverture.';
$string['activitydatestart'] = 'Début de l\'EP';
$string['activitydateend'] = 'Fin de l\'EP';
$string['activitygrading'] = 'Évaluation de cet EP';
$string['activitygrading_help'] = "Par défaut, l'EP suit l'évaluation de son type. Il peut fixer la sienne : sans note, ou avec une note maximale et un seuil propres.";
$string['activitygrading_type'] = 'Celle du type ({$a})';
$string['prerequisites'] = 'Prérequis';
$string['prereqactivities'] = 'EP à avoir validés';
$string['prereqactivities_help'] = "L'étudiant doit avoir une inscription validée à chacun de ces EP du catalogue avant de s'inscrire.";
$string['prereqminects'] = 'ECTS d\'EP déjà retenus au minimum';
$string['prereqblocking'] = 'Prérequis bloquants';
$string['prereqblocking_help'] = "Coché : un prérequis manquant empêche l'étudiant de s'inscrire. Décoché : il est seulement signalé, à l'étudiant et au responsable de l'EP.";
$string['prereqmissingactivity'] = 'EP « {$a} » non validé';
$string['prereqmissingects'] = '{$a->required} ECTS d\'EP retenus exigés ({$a->retained} actuellement)';
$string['prereqmissing'] = 'Prérequis manquants : {$a}';
$string['errorprerequisites'] = 'Vous ne remplissez pas les prérequis de cet EP.';
$string['registrationnotopen'] = 'Inscriptions fermées';
$string['participants'] = 'Participants';
$string['participantsof'] = 'Participants : {$a}';
$string['participantscount'] = '{$a} participant(s)';
$string['participants_help'] = "Liste des inscriptions à cet EP. Vous pouvez inscrire des étudiants (y compris après la période d'inscription), retirer un participant dont l'inscription est en attente, saisir les notes ou les validations de toute la liste en une fois, les importer depuis un fichier CSV, et clore l'EP.";
$string['addparticipants'] = 'Inscrire des étudiants';
$string['addparticipants_help'] = "Les étudiants choisis sont inscrits à cet EP, en attente de validation. Une inscription déjà en cours n'est pas doublée. Les prérequis ne bloquent pas un ajout : ils sont rappelés dans la liste.";
$string['addparticipantsforce'] = 'Dépasser le nombre de places si nécessaire (DEVE)';
$string['participantsadded'] = '{$a} étudiant(s) inscrit(s).';
$string['participantsskipped'] = '{$a} étudiant(s) non inscrit(s) : inscription déjà en cours ou plus de place.';
$string['nostudentstoadd'] = 'Tous les étudiants du cours sont déjà inscrits à cet EP.';
$string['withdrawparticipant'] = 'Retirer';
$string['withdrawreason'] = 'Motif du retrait';
$string['confirmwithdraw'] = 'Retirer {$a} de cet EP ? Son inscription est conservée, à l\'état « annulé ».';
$string['participantwithdrawn'] = 'Participant retiré.';
$string['savedecisions'] = 'Enregistrer les décisions';
$string['decisionssaved'] = 'Décisions enregistrées : {$a->validated} validé(s), {$a->rejected} non validé(s), {$a->corrected} note(s) corrigée(s).';
$string['decisionschanged'] = '{$a} inscription(s) avaient changé entre-temps et n\'ont pas été modifiées.';
$string['decisionvalidate'] = 'Validé';
$string['decisionreject'] = 'Non validé';
$string['decisionnone'] = '—';
$string['decision_corrected'] = 'Note corrigée';
$string['gradecorrectionnotice'] = 'En tant que DEVE, vous pouvez corriger la note d\'une inscription déjà tranchée : la note décide de nouveau de la validation.';
$string['awaitingotherrole'] = 'Attend : {$a}';
$string['addedby'] = 'Inscrit par {$a}';
$string['closeactivity'] = 'Clore l\'EP';
$string['closeactivity_help'] = "L'EP n'accepte plus d'inscription mais reste au catalogue ; ses notes restent saisissables. Choisissez le sort des inscriptions encore en attente.";
$string['closependingkeep'] = 'Les laisser en attente';
$string['closependingvalidate'] = 'Les valider d\'office (EP sans note)';
$string['closependingreject'] = 'Les déclarer non validées';
$string['activityclosed'] = 'EP clos : {$a->validated} validée(s), {$a->rejected} non validée(s), {$a->kept} laissée(s) en attente.';
$string['closedactivitycomment'] = 'Décision prise à la clôture de l\'EP.';
$string['reopenactivity'] = 'Rouvrir l\'EP';
$string['activityreopened'] = 'EP rouvert aux inscriptions.';
$string['exportparticipants'] = 'Exporter la liste (CSV)';
$string['attendancesheet'] = 'Feuille d\'émargement (PDF)';
$string['signature'] = 'Signature';
$string['importgrades'] = 'Importer les notes (CSV)';
$string['importgrades_help'] = "Fichier CSV (séparateur point-virgule, virgule ou tabulation), une ligne par étudiant, avec une ligne d'en-tête. Colonnes reconnues par leur intitulé : « Email » ou « Numéro d'identification » pour l'étudiant ; « Note » pour un EP noté (virgule ou point décimal) ; « Décision » pour un EP sans note (« validé », « oui », « 1 » ou « non validé », « non », « 0 », « absent ») ; « Commentaire », facultatif. La liste exportée de l'EP peut servir de modèle.";
$string['importgradesfile'] = 'Fichier CSV';
$string['importgradespreview'] = 'Prévisualisation de l\'import';
$string['importgradesconfirm'] = 'Confirmer l\'import';
$string['importgradesnone'] = 'Aucune ligne exploitable dans ce fichier.';
$string['importgradesexpired'] = 'La prévisualisation a expiré : déposez le fichier de nouveau.';
$string['importgradesnostudentcolumn'] = 'Colonne de l\'étudiant introuvable : ajoutez une colonne « Email » ou « Numéro d\'identification ».';
$string['importgradesnovaluecolumn'] = 'Colonne de résultat introuvable : ajoutez une colonne « Note » ou « Décision ».';
$string['importgradesunknown'] = 'Ligne {$a->line} : « {$a->who} » n\'est pas inscrit à cet EP.';
$string['importgradesduplicate'] = 'Ligne {$a->line} : « {$a->who} » figure déjà ligne {$a->first}.';
$string['importgradesmissing'] = 'Ligne {$a->line} : note manquante pour « {$a->who} ».';
$string['importgradesinvalid'] = 'Ligne {$a->line} : « {$a->value} » n\'est pas une note entre 0 et {$a->max}.';
$string['importgradesbaddecision'] = 'Ligne {$a->line} : décision « {$a->value} » non reconnue.';
$string['myresponsibleactivities'] = 'EP dont je suis responsable';
$string['pendingregistrations'] = 'En attente';
$string['catalogmanage'] = 'Gérer';

// Propositions d'EP, syllabus, éditions PDF, courriels (lot 4).
$string['ep:propose'] = 'Proposer un EP et rédiger le syllabus des EP dont on est responsable';
$string['proposals'] = 'Propositions et syllabus';
$string['proposals_help'] = "Un enseignant propose un nouvel EP en rédigeant sa fiche (syllabus) puis en la soumettant à la DEVE, qui la publie au catalogue, la lui renvoie avec un commentaire ou la refuse. Le responsable d'un EP publié peut aussi en proposer une mise à jour : la version en vigueur reste au catalogue jusqu'à la publication de la nouvelle.";
$string['proposenew'] = 'Proposer un nouvel EP';
$string['myproposals'] = 'Mes propositions et révisions en cours';
$string['proposalstoreview'] = 'Propositions et mises à jour à examiner';
$string['noproposals'] = 'Aucune proposition ni révision en cours.';
$string['syllabus'] = 'Syllabus';
$string['syllabusof'] = 'Syllabus : {$a}';
$string['syllabusversion'] = 'Version {$a}';
$string['syllabusstatus'] = 'État du syllabus';
$string['syllabusstatus_draft'] = 'En rédaction';
$string['syllabusstatus_submitted'] = 'Soumis à la DEVE';
$string['syllabusstatus_returned'] = 'Renvoyé à son auteur';
$string['syllabusstatus_published'] = 'Publié';
$string['syllabusstatus_superseded'] = 'Remplacé';
$string['syllabusstatus_rejected'] = 'Refusé';
$string['nosyllabus'] = 'Cet EP n\'a pas encore de syllabus publié.';
$string['editsyllabus'] = 'Rédiger le syllabus';
$string['proposeupdate'] = 'Proposer une mise à jour';
$string['syllabussaved'] = 'Syllabus enregistré.';
$string['syllabussubmitted'] = 'Syllabus soumis à la DEVE.';
$string['syllabuspublisheddirect'] = 'Mise à jour publiée.';
$string['errorsyllabusnoteditable'] = 'Cette version n\'est plus modifiable : elle a été soumise ou tranchée entre-temps.';
$string['submitsyllabus'] = 'Soumettre à la DEVE';
$string['savedraft'] = 'Enregistrer le brouillon';
$string['syllabusdecision_publish'] = 'publiée';
$string['syllabusdecision_return'] = 'renvoyée pour modification';
$string['syllabusdecision_reject'] = 'refusée';
$string['reviewpublish'] = 'Publier';
$string['reviewreturn'] = 'Renvoyer à l\'auteur';
$string['reviewreject'] = 'Refuser';
$string['reviewcomment'] = 'Commentaire à l\'auteur';
$string['reviewdone'] = 'Décision enregistrée : version {$a}.';
$string['pendingrevision'] = 'Une mise à jour de ce syllabus est en cours (version {$a->version}, {$a->status}).';
$string['comparepublished'] = 'Version en vigueur';
$string['comparepending'] = 'Version proposée';
$string['proposedby'] = 'Proposé par {$a}';
$string['proposalsdirectpublish'] = 'Publication directe des mises à jour par les responsables';
$string['proposalsdirectpublish_help'] = "Coché : la mise à jour du syllabus d'un EP déjà publié, soumise par son responsable, est publiée aussitôt (la DEVE est seulement tenue au courant par le journal). Une proposition de nouvel EP passe toujours par la DEVE.";
$string['syllabusobjectives'] = 'Objectifs';
$string['syllabusoutcomes'] = 'Acquis d\'apprentissage visés';
$string['syllabusoutcomes_help'] = "Ce que l'étudiant saura ou saura faire à l'issue de l'EP, formulé avec des verbes d'action (identifier, réaliser, interpréter...).";
$string['syllabusprerequisites'] = 'Prérequis';
$string['syllabuscontent'] = 'Contenu';
$string['syllabusmethods'] = 'Méthodes d\'enseignement';
$string['syllabusassessment'] = 'Conditions d\'examen';
$string['syllabusassessment_help'] = "Modalités d'évaluation (écrit, oral, pratique, contrôle continu...), critères, pondération et seuil de validation.";
$string['syllabusresit'] = 'Seconde session';
$string['syllabusbibliography'] = 'Bibliographie et ressources';
$string['syllabuslanguage'] = 'Langue d\'enseignement';
$string['syllabuspersonalhours'] = 'Travail personnel (heures)';
$string['sessions'] = 'Programme des séances';
$string['sessions_help'] = "Une ligne par séance : intitulé, nature, durée en heures et intervenant (un enseignant du cours, ou le nom d'un intervenant extérieur). Les lignes sans intitulé sont ignorées. Utilisez « Ajouter des séances » pour obtenir des lignes vides supplémentaires.";
$string['sessiontitle'] = 'Séance';
$string['sessionkind'] = 'Nature';
$string['sessionkind_lecture'] = 'Cours magistral';
$string['sessionkind_tutorial'] = 'Travaux dirigés';
$string['sessionkind_practical'] = 'Travaux pratiques';
$string['sessionkind_clinical'] = 'Clinique';
$string['sessionkind_fieldwork'] = 'Terrain';
$string['sessionkind_project'] = 'Projet';
$string['sessionkind_other'] = 'Autre';
$string['sessionduration'] = 'Durée (h)';
$string['sessionteacher'] = 'Intervenant';
$string['sessionteachername'] = 'Intervenant extérieur';
$string['addsessions'] = 'Ajouter des séances';
$string['hourstotal'] = 'Volume horaire';
$string['hourssessions'] = 'Séances : {$a} h';
$string['hourspersonal'] = 'Travail personnel : {$a} h';
$string['hoursworkload'] = 'Charge de travail totale : {$a} h';
$string['hoursperects'] = 'Soit {$a} h par ECTS';
$string['downloadsyllabus'] = 'Fiche PDF';
$string['booklet'] = 'Livret des EP (PDF)';
$string['booklet_help'] = 'Toutes les fiches des EP publiés ou clos du catalogue, précédées d\'un sommaire.';
$string['booklettitle'] = 'Livret des enseignements personnalisés';
$string['bookletcontents'] = 'Sommaire';
$string['pdfsettings'] = 'Éditions PDF et propositions';
$string['pdfsettings_desc'] = 'En-tête, pied de page et logo des fiches, du livret et des feuilles d\'émargement ; publication des mises à jour de syllabus.';
$string['pdfheader'] = 'En-tête des documents PDF';
$string['pdffooter'] = 'Pied de page des documents PDF';
$string['pdflogo'] = 'Logo';
$string['pdfsettingssaved'] = 'Réglages enregistrés.';
$string['notifications'] = 'Courriels';
$string['notifications_desc'] = 'Texte des courriels envoyés par l\'activité (décision sur une demande, proposition à examiner, décision sur une proposition, relance hebdomadaire), et leur activation.';
$string['notificationenabled'] = 'Envoyer ce courriel';
$string['notificationsubject'] = 'Objet';
$string['notificationbody'] = 'Message';
$string['notificationvariables'] = 'Variables disponibles : {$a}';
$string['notificationreset'] = 'Laisser l\'objet et le message vides rétablit le texte par défaut.';
$string['notificationssaved'] = 'Courriels enregistrés.';
$string['notifykey_creditdecided'] = 'Décision sur une demande d\'EP (à l\'étudiant)';
$string['notifykey_proposalsubmitted'] = 'Proposition ou mise à jour à examiner (à la DEVE)';
$string['notifykey_proposalreviewed'] = 'Décision sur une proposition (à son auteur)';
$string['notifykey_pendingreminder'] = 'Relance des demandes en attente (aux validateurs)';
$string['notify_creditdecided_subject'] = 'Enseignement personnalisé : {ep} — {decision}';
$string['notify_creditdecided_body'] = "Bonjour {student},\n\nVotre demande d'enseignement personnalisé « {ep} » a été {decision}.\nECTS retenus : {ects}\nNote : {grade}\nCommentaire : {comment}\n\nDétail : {link}";
$string['notify_proposalsubmitted_subject'] = 'Proposition d\'EP à examiner : {ep}';
$string['notify_proposalsubmitted_body'] = "{author} a soumis le syllabus de l'EP « {ep} ».\n\nPour le publier, le renvoyer ou le refuser : {link}";
$string['notify_proposalreviewed_subject'] = 'Votre proposition d\'EP « {ep} » : {decision}';
$string['notify_proposalreviewed_body'] = "Bonjour {author},\n\nLe syllabus de l'EP « {ep} » a été {decision} par la DEVE.\nCommentaire : {comment}\n\n{link}";
$string['notify_pendingreminder_subject'] = 'Enseignement personnalisé : {count} demande(s) en attente de votre décision';
$string['notify_pendingreminder_body'] = "Bonjour {recipient},\n\n{count} demande(s) d'enseignement personnalisé attendent votre décision depuis plus d'une semaine.\n\n{link}";
$string['messageprovider:creditdecided'] = 'Décision sur une demande d\'enseignement personnalisé';
$string['messageprovider:proposalsubmitted'] = 'Proposition d\'EP à examiner';
$string['messageprovider:proposalreviewed'] = 'Décision sur une proposition d\'EP';
$string['messageprovider:pendingreminder'] = 'Relance des demandes d\'EP en attente';
$string['tasksendpendingreminders'] = 'Relance hebdomadaire des demandes d\'EP en attente';
$string['eventsyllabussubmitted'] = 'Syllabus d\'EP soumis';
$string['eventsyllabusreviewed'] = 'Syllabus d\'EP examiné';
$string['syllabusauthor'] = 'Auteur';
$string['proposedbylabel'] = 'Proposé par';
$string['syllabusversionlabel'] = 'Version';
$string['errornegativeduration'] = 'Une durée ne peut pas être négative.';

// Supplément au diplôme, validation d'année, rentrée, transfert (lot 5).
$string['supplement'] = 'Supplément au diplôme';
$string['mysupplement'] = 'Mon supplément au diplôme';
$string['supplement_help'] = "Le supplément au diplôme (modèle européen Europass) décrit le diplôme et le parcours de l'étudiant. Les enseignements personnalisés validés y figurent d'office (rubrique 4.3) ; l'étudiant peut demander à y ajouter d'autres éléments (engagement, distinction, certification, mobilité...), avec leurs preuves, qui y figureront une fois validés (rubrique 6.1). La DEVE l'émet, numéroté et certifié.";
$string['supplementpreview'] = 'Aperçu (non certifié)';
$string['supplementpreviewwatermark'] = 'APERÇU — NON CERTIFIÉ';
$string['supplementitems'] = 'Éléments ajoutés au supplément';
$string['supplementitem'] = 'Élément';
$string['supplementitemrequest'] = 'Demander l\'ajout d\'un élément';
$string['supplementitemedit'] = 'Modifier l\'élément';
$string['supplementitemsaved'] = 'Demande enregistrée.';
$string['supplementitemcancelled'] = 'Demande retirée.';
$string['supplementitemdecided'] = 'Décision enregistrée.';
$string['supplementitemchanged'] = 'Cet élément a changé entre-temps : rien n\'a été enregistré.';
$string['supplementitemstodecide'] = 'Éléments de supplément à examiner';
$string['supplementitemtitle'] = 'Intitulé';
$string['supplementitemtitleen'] = 'Intitulé en anglais';
$string['supplementitemtitleen_help'] = 'Facultatif : utilisé dans un supplément édité en anglais.';
$string['supplementitemdescription'] = 'Description';
$string['supplementitemperiodstart'] = 'Début';
$string['supplementitemperiodend'] = 'Fin';
$string['supplementproofs'] = 'Preuves (PDF)';
$string['supplementproofs_help'] = 'Attestations, diplômes, certificats, au format PDF.';
$string['errorsupplementproofrequired'] = 'Au moins une preuve en PDF est obligatoire.';
$string['supplementcategory'] = 'Catégorie';
$string['supplementcategories'] = 'Catégories d\'éléments';
$string['supplementcategorynameen'] = 'Libellé anglais';
$string['supplementcategorysaved'] = 'Catégorie enregistrée.';
$string['suppcat_engagement'] = 'Engagement étudiant';
$string['suppcat_representation'] = 'Représentation étudiante';
$string['suppcat_award'] = 'Prix ou distinction';
$string['suppcat_certification'] = 'Certification';
$string['suppcat_mobility'] = 'Mobilité';
$string['suppcat_publication'] = 'Publication ou communication';
$string['supplementsettings'] = 'Supplément au diplôme : paramétrage';
$string['supplementsettings_desc'] = 'Textes des rubriques fixes (en français et en anglais), signataire et cachet, catégories d\'éléments, circuit de validation et preuves exigées.';
$string['supplementsettingssaved'] = 'Paramétrage du supplément enregistré.';
$string['supplementflow'] = 'Validation des éléments ajoutés';
$string['supplementflow_help'] = "Qui valide les éléments que l'étudiant demande à ajouter : personne (ajout direct), son enseignant référent, la DEVE, ou le référent puis la DEVE.";
$string['supplementevidence'] = 'Preuves des éléments ajoutés';
$string['supplementprefix'] = 'Préfixe des numéros';
$string['supplementprefix_help'] = 'Début du numéro imprimé sur chaque supplément émis (par exemple ENVT-SD). Par défaut : SD.';
$string['birthdatefield'] = 'Champ de profil de la date de naissance';
$string['birthdatefield_help'] = "Champ de profil personnalisé qui porte la date de naissance (rubrique 1.3). À défaut, la date saisie dans une demande de convention de stage est utilisée.";
$string['birthdatefieldnone'] = 'Aucun (date des conventions de stage)';
$string['supplementstamp'] = 'Cachet (image)';
$string['supplementsectiontexts'] = 'Textes des rubriques';
$string['supplementsection_qualification'] = 'Intitulé du diplôme et titre conféré';
$string['supplementsection_fieldofstudy'] = 'Principaux domaines d\'étude';
$string['supplementsection_institution'] = 'Nom et statut de l\'établissement qui délivre le diplôme';
$string['supplementsection_institutionadmin'] = 'Nom et statut de l\'établissement qui dispense les cours';
$string['supplementsection_languages'] = 'Langues d\'enseignement et d\'examen';
$string['supplementsection_level'] = 'Niveau du diplôme';
$string['supplementsection_length'] = 'Durée officielle du programme';
$string['supplementsection_access'] = 'Conditions d\'accès';
$string['supplementsection_studymode'] = 'Organisation des études';
$string['supplementsection_programmeoutcomes'] = 'Acquis d\'apprentissage du programme';
$string['supplementsection_gradingscheme'] = 'Système de notation';
$string['supplementsection_classification'] = 'Classification générale du diplôme';
$string['supplementsection_furtherstudy'] = 'Accès à un niveau d\'études supérieur';
$string['supplementsection_professional'] = 'Statut professionnel conféré';
$string['supplementsection_additionalinfo'] = 'Informations complémentaires (texte d\'introduction)';
$string['supplementsection_othersources'] = 'Autres sources d\'information';
$string['supplementsection_signatoryname'] = 'Nom du signataire';
$string['supplementsection_signatorytitle'] = 'Qualité du signataire';
$string['supplementsection_national'] = 'Description du système national d\'enseignement supérieur';
$string['supplementtitle'] = 'Supplément au diplôme';
$string['supplementtitleen'] = 'Diploma Supplement';
$string['supplementpreamble'] = "Ce supplément au diplôme suit le modèle élaboré par la Commission européenne, le Conseil de l'Europe et l'UNESCO/CEPES. Il vise à fournir des données indépendantes et suffisantes pour améliorer la transparence internationale et permettre une reconnaissance académique et professionnelle équitable des qualifications. Il décrit la nature, le niveau, le contexte, le contenu et le statut des études accomplies avec succès par la personne désignée sur le diplôme original auquel ce supplément est annexé. Il est dépourvu de tout jugement de valeur, déclaration d'équivalence ou suggestion de reconnaissance.";
$string['supplementpart1'] = 'Informations sur le titulaire du diplôme';
$string['supplementpart2'] = 'Informations sur le diplôme';
$string['supplementpart3'] = 'Informations sur le niveau et la durée du diplôme';
$string['supplementpart4'] = 'Informations sur le contenu du programme et les résultats obtenus';
$string['supplementpart5'] = 'Informations sur la fonction du diplôme';
$string['supplementpart6'] = 'Informations complémentaires';
$string['supplementpart7'] = 'Certification du supplément';
$string['supplementpart8'] = 'Informations sur le système national d\'enseignement supérieur';
$string['supplementlastname'] = 'Nom(s) de famille';
$string['supplementfirstname'] = 'Prénom(s)';
$string['supplementbirthdate'] = 'Date de naissance';
$string['supplementidnumber'] = 'Numéro d\'identification de l\'étudiant';
$string['supplementprogrammedetails'] = 'Détail du programme : enseignements personnalisés validés, crédits obtenus et résultats';
$string['supplementtotalects'] = 'Total des crédits d\'enseignement personnalisé : {$a} ECTS';
$string['supplementyears'] = 'Années d\'étude validées au titre de l\'enseignement personnalisé';
$string['supplementyearvalidated'] = 'Année {$a->year} validée le {$a->date} ({$a->ects} ECTS)';
$string['supplementyearnotvalidated'] = 'Année {$a->year} non validée';
$string['supplementvalidatedgrade'] = 'Validé';
$string['supplementcertdate'] = 'Date';
$string['supplementcertsignature'] = 'Signature';
$string['supplementcertstamp'] = 'Cachet officiel';
$string['supplementnumber'] = 'Numéro : {$a}';
$string['supplementverifycode'] = 'Code de vérification : {$a}';
$string['supplementverifyinfo'] = 'Vérifiable sur {$a}';
$string['supplementmissingbirthdate'] = 'date de naissance inconnue';
$string['supplementmissingidnumber'] = 'numéro d\'identification non renseigné';
$string['supplementmissingsignatory'] = 'signataire non renseigné (paramétrage du supplément)';
$string['supplementmissing'] = 'Informations manquantes : {$a}';
$string['supplementissue'] = 'Émettre le supplément';
$string['supplementissueforce'] = 'Émettre malgré les informations manquantes';
$string['supplementissued'] = 'Supplément émis : {$a}.';
$string['supplementissues'] = 'Suppléments émis';
$string['supplementissuesadmin'] = 'Émission des suppléments';
$string['supplementissuesadmin_desc'] = 'Aperçu et émission du supplément de chaque étudiant, émission pour toute la promotion, archive ZIP des suppléments émis.';
$string['supplementissueall'] = 'Émettre pour toute la promotion';
$string['supplementissueallqueued'] = 'Émission de {$a} supplément(s) programmée : vous serez prévenu par message à la fin.';
$string['supplementissueallreport'] = 'Émission des suppléments terminée : {$a->issued} émis, {$a->skipped} non émis (informations manquantes).';
$string['supplementzip'] = 'Télécharger les suppléments en vigueur (ZIP)';
$string['supplementreplaced'] = 'Remplacé';
$string['supplementcurrent'] = 'En vigueur';
$string['supplementlanguage'] = 'Langue';
$string['supplementverify'] = 'Vérifier un supplément';
$string['supplementverify_help'] = 'Saisissez le numéro et le code de vérification imprimés sur le document.';
$string['supplementverifyok'] = 'Ce supplément est authentique : émis le {$a->date} pour {$a->name}.';
$string['supplementverifyreplaced'] = 'Ce supplément a été remplacé par une émission plus récente.';
$string['supplementverifyko'] = 'Aucun supplément ne correspond à ce numéro et à ce code.';
$string['supplementnoissue'] = 'Aucun supplément émis pour le moment.';
$string['yearvalidation'] = 'Validation des années';
$string['yearvalidation_desc'] = 'Décision de la DEVE sur l\'année d\'étude de chaque étudiant, au regard de ses minimums d\'ECTS ; validation en lot des années atteintes.';
$string['yearvalidation_help'] = "Pour l'année choisie, la liste indique si chaque étudiant a atteint le minimum d'ECTS. « Valider les années atteintes » enregistre la validation de tous ceux qui l'ont atteint ; une décision individuelle (validée ou non, avec commentaire) reste possible.";
$string['yearvalidated'] = 'Année validée';
$string['yearnotvalidated'] = 'Année non validée';
$string['yearnodecision'] = 'Pas de décision';
$string['yearvalidatecomplete'] = 'Valider les années atteintes';
$string['yearrejectincomplete'] = 'Déclarer non validées les années non atteintes';
$string['yearvalidationdone'] = '{$a->validated} année(s) validée(s), {$a->rejected} non validée(s), {$a->skipped} laissée(s) sans décision.';
$string['yeardecisionsaved'] = 'Décision enregistrée.';
$string['yearminimumnotreached'] = 'Minimum d\'ECTS de l\'année non atteint.';
$string['yearreached'] = 'Minimum atteint';
$string['yearnotreached'] = 'Minimum non atteint';
$string['rollover'] = 'Rentrée';
$string['rollover_desc'] = 'Passage à l\'année d\'étude suivante, bilan des étudiants dont l\'année n\'est pas validée, transfert d\'un étudiant vers une autre promotion.';
$string['rollover_help'] = "La rentrée fait passer l'année d'étude courante de l'activité à la suivante : les objectifs de la nouvelle année deviennent exigibles. Les crédits, décisions et suppléments restent en place. Vérifiez d'abord la validation des années.";
$string['rolloverconfirm'] = 'Passer l\'année courante de {$a->from} à {$a->to} ?';
$string['rolloverdone'] = 'Année courante : {$a}.';
$string['rolloverpending'] = '{$a} étudiant(s) sans décision ou avec une année non validée.';
$string['rolloverstale'] = 'L\'année d\'étude avait déjà changé (autre onglet ou envoi répété) : rien n\'a été modifié.';
$string['transferstudent'] = 'Transférer un étudiant';
$string['transferstudent_help'] = "Déplace le dossier d'un étudiant vers une autre activité « Enseignement personnalisé » du site (redoublement, changement de promotion) : ses EP, leurs décisions et justificatifs, ses éléments de supplément et ses décisions d'année. Les types sont rapprochés par leur code, les EP du catalogue par leur code ou leur intitulé. Les suppléments déjà émis restent dans l'activité d'origine.";
$string['transfertarget'] = 'Activité de destination';
$string['transferdone'] = 'Transfert effectué : {$a->credits} EP, {$a->items} élément(s) de supplément, {$a->years} décision(s) d\'année.';
$string['transfernotarget'] = 'Aucune autre activité « Enseignement personnalisé » que vous gérez sur ce site.';
$string['transferconfirm'] = 'Transférer le dossier de {$a->student} vers « {$a->target} » ?';
$string['eventyeardecided'] = 'Décision sur une année d\'étude';
$string['eventsupplementissued'] = 'Supplément au diplôme émis';
$string['taskissuesupplements'] = 'Émission groupée des suppléments au diplôme';
$string['messageprovider:supplementissued'] = 'Émission des suppléments au diplôme terminée';
$string['supplementmissingheader'] = 'Informations manquantes';
$string['supplementcategoryadd'] = 'Ajouter une catégorie';
$string['supplementcategoryedit'] = 'Modifier la catégorie';
$string['supplementcategoryenabled'] = 'Catégorie activée.';
$string['supplementcategorydisabled'] = 'Catégorie désactivée : elle n\'est plus proposée aux étudiants ; les éléments qui y sont déjà rangés sont conservés.';
$string['supplementcategorydeleted'] = 'Catégorie supprimée.';
$string['confirmdeletesupplementcategory'] = 'Supprimer la catégorie « {$a} » ? Aucun élément ne l\'utilise.';
$string['supplementverify_desc'] = 'Vérifier qu\'un supplément au diplôme présenté par un étudiant a bien été émis par cette activité et n\'a pas été altéré.';
$string['supplementitemstodecide_desc'] = 'Examiner les activités complémentaires demandées par les étudiants pour la rubrique 6.1 de leur supplément.';
$string['supplementnoitems'] = 'Aucun élément.';
$string['nopendingsupplementitems'] = 'Aucun élément du supplément n\'attend votre décision.';
$string['supplementperiod'] = 'Période';
$string['supplementnumberheader'] = 'Numéro';
$string['supplementverifycodelabel'] = 'Code de vérification';
$string['errorsupplementperiod'] = 'La fin de la période ne peut pas précéder son début.';
$string['errornosupplementcategory'] = 'Aucune catégorie d\'élément du supplément n\'est ouverte : adressez-vous à la DEVE.';

// Lot 6 : imports, exports, transfert du paramétrage.
$string['adminsectiontransfer'] = 'Imports, exports et transfert';
$string['importcatalog'] = 'Importer le catalogue (CSV)';
$string['importcatalog_desc'] = 'Créer ou mettre à jour des EP du catalogue depuis un fichier CSV, avec leurs responsables, leurs prérequis et, au besoin, leur syllabus.';
$string['importsessions'] = 'Importer les séances des syllabus (CSV)';
$string['importsessions_desc'] = 'Remplacer le programme (séances, heures, intervenants) d\'EP du catalogue depuis un fichier CSV.';
$string['importcredits'] = 'Importer des crédits (CSV)';
$string['importcredits_desc'] = 'Porter des crédits au dossier des étudiants depuis un fichier CSV : EP suivis ailleurs, historique d\'un outil précédent.';
$string['importcsvintro'] = 'Fichier CSV avec une ligne d\'en-tête, séparé par des points-virgules, des virgules ou des tabulations, enregistré en UTF-8 ou par Excel. Les colonnes sont reconnues par leur intitulé, dans n\'importe quel ordre, sans tenir compte des accents ni de la casse. Les colonnes inconnues sont ignorées. Rien n\'est écrit avant la confirmation de la prévisualisation.';
$string['importfile'] = 'Fichier CSV';
$string['importcolumns'] = 'Colonnes reconnues';
$string['importcolumnheadings'] = 'Intitulés acceptés';
$string['importcolumnrequired'] = 'Obligatoire';
$string['importtemplate'] = 'Télécharger un modèle';
$string['importpreview'] = 'Prévisualisation de l\'import';
$string['importconfirm'] = 'Confirmer l\'import';
$string['importexpired'] = 'La prévisualisation a expiré : déposez de nouveau le fichier.';
$string['importnone'] = 'Aucune ligne exploitable dans ce fichier.';
$string['importwillcreate'] = 'Créé';
$string['importwillupdate'] = 'Mis à jour';
$string['importsyllabusnotice'] = 'Le fichier contient des colonnes du syllabus : pour chaque EP, une nouvelle version du syllabus est publiée avec ces textes (les autres rubriques sont reprises de la version en vigueur).';
$string['importsessionsnotice'] = 'Pour chaque EP cité, le programme du syllabus en vigueur est remplacé par les séances du fichier, dans une nouvelle version publiée.';
$string['importcatalogdone'] = 'Catalogue importé : {$a->created} EP créés, {$a->updated} mis à jour, {$a->syllabi} syllabus publiés.';
$string['importsessionsdone'] = 'Programme remplacé pour {$a} EP.';
$string['importcreditsdone'] = '{$a->created} crédits ajoutés, {$a->skipped} écartés.';
$string['importcolumnmissing'] = 'Colonne obligatoire « {$a} » absente de la ligne d\'en-tête.';
$string['importcolumnignored'] = 'Colonne « {$a} » non reconnue : ignorée.';
$string['importline'] = 'Ligne {$a->line} : {$a->error}';
$string['importvaluemissing'] = 'valeur manquante dans la colonne « {$a} ».';
$string['importduplicatecode'] = 'le code « {$a->code} » figure déjà ligne {$a->line}.';
$string['importunknowntype'] = 'type « {$a} » inconnu.';
$string['importbadnumber'] = 'colonne « {$a->column} » : « {$a->value} » n\'est pas un nombre valide.';
$string['importbadvalue'] = 'colonne « {$a->column} » : valeur « {$a->value} » non reconnue.';
$string['importbaddate'] = 'colonne « {$a->column} » : « {$a->value} » n\'est pas une date (jj/mm/aaaa, éventuellement suivie de hh:mm).';
$string['importunknownuser'] = 'Ligne {$a->line} : aucun compte unique ne correspond à « {$a->value} » : ignoré.';
$string['importunknownprerequisite'] = 'Ligne {$a->line} : prérequis « {$a->value} » introuvable dans le catalogue : ignoré.';
$string['importunknownactivity'] = 'aucun EP du catalogue n\'a le code « {$a} ».';
$string['importunknownstudent'] = 'aucun étudiant du cours ne correspond à « {$a} ».';
$string['importduplicatecredit'] = 'Ligne {$a->line} : « {$a->name} » est déjà porté au dossier de cet étudiant pour cette année : écarté.';
$string['importsettingsunknowntype'] = 'EP « {$a->name} » : le type « {$a->type} » n\'existe pas dans cette activité : ignoré.';
$string['importsettingsmissingteachers'] = 'EP « {$a} » : certains responsables n\'ont pas de compte sur ce site et n\'ont pas été ajoutés.';
$string['importlog'] = 'Journal des imports';
$string['importlog_desc'] = 'Imports, restaurations et transferts de paramétrage effectués dans cette activité.';
$string['importlogempty'] = 'Aucun import pour l\'instant.';
$string['importlogkind'] = 'Import';
$string['importlogitems'] = 'Éléments';
$string['importlogsummary'] = 'Résultat';
$string['importlogkind_catalog'] = 'Catalogue';
$string['importlogkind_sessions'] = 'Séances des syllabus';
$string['importlogkind_credits'] = 'Crédits';
$string['importlogkind_settings'] = 'Paramétrage';
$string['importlogkind_archive'] = 'Restauration complète';
$string['archive'] = 'Export complet et restauration';
$string['archive_desc'] = 'Télécharger tout le module (paramétrage, catalogue, dossiers des étudiants, fichiers) dans une archive ZIP, ou restaurer une telle archive.';
$string['archiveexport'] = 'Télécharger l\'export complet (ZIP)';
$string['archiveexport_help'] = 'L\'archive contient un classeur lisible dans un tableur (classeur.xlsx), le paramétrage et le catalogue (parametrage.json), les données des étudiants (donnees.json) et les fichiers (justificatifs, preuves, suppléments émis). Seuls les deux fichiers JSON sont relus à la restauration : retoucher le classeur n\'a pas d\'effet.';
$string['archiverestore'] = 'Restaurer un export';
$string['archiverestore_help'] = 'La restauration ajoute à cette activité ce que contient l\'archive, sans rien supprimer : ce qui existe déjà est écarté, si bien que restaurer deux fois la même archive ne double rien. Les étudiants sont retrouvés par leur numéro d\'identification ou leur adresse parmi les étudiants du cours ; les données des étudiants non inscrits ne sont pas restaurées.';
$string['archivefile'] = 'Archive d\'export (ZIP)';
$string['archivecontents'] = 'Contenu de l\'archive';
$string['archiveparts'] = 'Parties à restaurer';
$string['archivepartdata'] = 'Données des étudiants (crédits, éléments du supplément, décisions d\'année, suppléments émis)';
$string['archiverestoredone'] = 'Restauration terminée : {$a->credits} crédits, {$a->items} éléments du supplément, {$a->years} décisions d\'année, {$a->issues} suppléments émis ; {$a->skipped} éléments écartés.';
$string['archivenoparts'] = 'Choisissez au moins une partie à restaurer.';
$string['archivecount_credits'] = 'Crédits';
$string['archivecount_items'] = 'Éléments du supplément';
$string['archivecount_years'] = 'Décisions d\'année';
$string['archivecount_issues'] = 'Suppléments émis';
$string['restoremissingstudent'] = 'Pas étudiant de ce cours : {$a}. Ses données n\'ont pas été restaurées.';
$string['errorarchiveinvalid'] = 'Ce fichier n\'est pas une archive d\'export d\'EP Manager, ou provient d\'une version incompatible.';
$string['errorsettingsformat'] = 'Ce fichier n\'est pas un export du paramétrage d\'EP Manager, ou provient d\'une version incompatible.';
$string['settingstransfer'] = 'Transférer le paramétrage';
$string['settingstransfer_desc'] = 'Exporter les règles de cette activité (types, minimums, supplément, courriels, catalogue) dans un fichier JSON, importer un tel fichier, ou copier les règles d\'une autre activité.';
$string['settingsexport'] = 'Exporter le paramétrage (JSON)';
$string['settingsexportwithcatalog'] = 'Inclure le catalogue et les syllabus';
$string['settingsimport'] = 'Importer un paramétrage';
$string['settingsfile'] = 'Fichier de paramétrage (JSON)';
$string['settingscopyfrom'] = 'Copier depuis une autre activité';
$string['settingssource'] = 'Activité source';
$string['settingsnosource'] = 'Aucune autre activité EP Manager que vous gérez.';
$string['settingsparts'] = 'Parties à importer';
$string['settingsparts_help'] = 'Les types sont rapprochés par leur code, les catégories par leur libellé, les EP du catalogue par leur code puis leur intitulé : mis à jour s\'ils existent, créés sinon. Rien n\'est supprimé. Les responsables et intervenants sont retrouvés par leur adresse parmi les comptes du site.';
$string['settingspart_instance'] = 'Réglages généraux (minimum de crédits, en-tête et pied des PDF, circuit du supplément)';
$string['settingspart_types'] = 'Types d\'EP';
$string['settingspart_yearrequirements'] = 'Minimums annuels';
$string['settingspart_supplement'] = 'Catégories et textes du supplément';
$string['settingspart_notifications'] = 'Modèles de courriels';
$string['settingspart_catalog'] = 'Catalogue, syllabus et prérequis';
$string['settingsimportdone'] = 'Paramétrage importé. Créés : {$a->created}. Mis à jour : {$a->updated}.';
$string['settingsnone'] = 'aucun';
$string['settingssourcename'] = 'Exporté depuis « {$a->source} » le {$a->date}.';

// Confidentialité (lot 7).
$string['privacy:metadata:ep_credit'] = 'Les EP portés au crédit d\'un étudiant : inscriptions au catalogue, déclarations, attributions automatiques, imports.';
$string['privacy:metadata:ep_credit:userid'] = 'Étudiant à qui l\'EP est porté au crédit.';
$string['privacy:metadata:ep_credit:name'] = 'Intitulé de l\'EP.';
$string['privacy:metadata:ep_credit:description'] = 'Description et justification saisies par l\'étudiant.';
$string['privacy:metadata:ep_credit:studyyear'] = 'Année d\'étude au titre de laquelle l\'EP est compté.';
$string['privacy:metadata:ep_credit:claimedects'] = 'Nombre d\'ECTS demandés.';
$string['privacy:metadata:ep_credit:retainedects'] = 'Nombre d\'ECTS retenus après validation.';
$string['privacy:metadata:ep_credit:grade'] = 'Note obtenue.';
$string['privacy:metadata:ep_credit:status'] = 'État de la demande (en attente, validée, refusée, retirée).';
$string['privacy:metadata:ep_credit:validatedby'] = 'Utilisateur ayant pris la décision finale.';
$string['privacy:metadata:ep_credit:addedby'] = 'Utilisateur ayant porté l\'EP au dossier (enseignant ou DEVE).';
$string['privacy:metadata:ep_credit:validatorcomment'] = 'Commentaire du validateur.';
$string['privacy:metadata:ep_credit:timecreated'] = 'Date de la demande.';
$string['privacy:metadata:ep_credit_decision'] = 'Historique des décisions prises sur un EP porté au crédit, une par étape du circuit de validation.';
$string['privacy:metadata:ep_credit_decision:userid'] = 'Utilisateur ayant pris la décision.';
$string['privacy:metadata:ep_credit_decision:role'] = 'Rôle au titre duquel la décision a été prise.';
$string['privacy:metadata:ep_credit_decision:decision'] = 'Décision (validée, refusée, retirée).';
$string['privacy:metadata:ep_credit_decision:grade'] = 'Note saisie.';
$string['privacy:metadata:ep_credit_decision:retainedects'] = 'ECTS retenus.';
$string['privacy:metadata:ep_credit_decision:comment'] = 'Commentaire.';
$string['privacy:metadata:ep_credit_decision:timecreated'] = 'Date de la décision.';
$string['privacy:metadata:ep_activity'] = 'EP du catalogue.';
$string['privacy:metadata:ep_activity:proposedby'] = 'Enseignant ayant proposé l\'EP.';
$string['privacy:metadata:ep_activity_teacher'] = 'Enseignants désignés responsables d\'un EP du catalogue.';
$string['privacy:metadata:ep_activity_teacher:teacherid'] = 'Enseignant responsable.';
$string['privacy:metadata:ep_syllabus'] = 'Versions du syllabus d\'un EP du catalogue.';
$string['privacy:metadata:ep_syllabus:authorid'] = 'Auteur de la version.';
$string['privacy:metadata:ep_syllabus:reviewerid'] = 'Membre de la DEVE l\'ayant examinée.';
$string['privacy:metadata:ep_syllabus:reviewcomment'] = 'Commentaire de l\'examen.';
$string['privacy:metadata:ep_syllabus:timesubmitted'] = 'Date de soumission.';
$string['privacy:metadata:ep_syllabus:timereviewed'] = 'Date de l\'examen.';
$string['privacy:metadata:ep_session'] = 'Séances du programme d\'un EP.';
$string['privacy:metadata:ep_session:teacherid'] = 'Intervenant de la séance.';
$string['privacy:metadata:ep_year_validation'] = 'Décisions sur les années d\'étude d\'un étudiant.';
$string['privacy:metadata:ep_year_validation:userid'] = 'Étudiant.';
$string['privacy:metadata:ep_year_validation:studyyear'] = 'Année d\'étude.';
$string['privacy:metadata:ep_year_validation:decision'] = 'Année validée ou non.';
$string['privacy:metadata:ep_year_validation:retainedects'] = 'ECTS retenus pour l\'année au moment de la décision.';
$string['privacy:metadata:ep_year_validation:comment'] = 'Commentaire.';
$string['privacy:metadata:ep_year_validation:decidedby'] = 'Utilisateur ayant pris la décision.';
$string['privacy:metadata:ep_year_validation:timedecided'] = 'Date de la décision.';
$string['privacy:metadata:ep_supplement_item'] = 'Activités complémentaires demandées par un étudiant pour son supplément au diplôme.';
$string['privacy:metadata:ep_supplement_item:userid'] = 'Étudiant.';
$string['privacy:metadata:ep_supplement_item:title'] = 'Intitulé.';
$string['privacy:metadata:ep_supplement_item:titleen'] = 'Intitulé en anglais.';
$string['privacy:metadata:ep_supplement_item:description'] = 'Description.';
$string['privacy:metadata:ep_supplement_item:periodstart'] = 'Début de la période.';
$string['privacy:metadata:ep_supplement_item:periodend'] = 'Fin de la période.';
$string['privacy:metadata:ep_supplement_item:status'] = 'État de la demande.';
$string['privacy:metadata:ep_supplement_item:validatedby'] = 'Utilisateur ayant pris la décision finale.';
$string['privacy:metadata:ep_supplement_item:validatorcomment'] = 'Commentaire du validateur.';
$string['privacy:metadata:ep_supplement_item:timecreated'] = 'Date de la demande.';
$string['privacy:metadata:ep_supplement_issue'] = 'Suppléments au diplôme émis au nom d\'un étudiant.';
$string['privacy:metadata:ep_supplement_issue:userid'] = 'Étudiant.';
$string['privacy:metadata:ep_supplement_issue:number'] = 'Numéro du document.';
$string['privacy:metadata:ep_supplement_issue:lang'] = 'Langue.';
$string['privacy:metadata:ep_supplement_issue:verifycode'] = 'Code de vérification imprimé sur le document.';
$string['privacy:metadata:ep_supplement_issue:issuedby'] = 'Membre de la DEVE l\'ayant émis.';
$string['privacy:metadata:ep_supplement_issue:timeissued'] = 'Date d\'émission.';
$string['privacy:metadata:ep_import_log'] = 'Journal des imports faits dans l\'activité.';
$string['privacy:metadata:ep_import_log:userid'] = 'Auteur de l\'import.';
$string['privacy:metadata:ep_import_log:kind'] = 'Nature de l\'import.';
$string['privacy:metadata:ep_import_log:itemcount'] = 'Nombre d\'éléments traités.';
$string['privacy:metadata:ep_import_log:timecreated'] = 'Date de l\'import.';
$string['privacy:metadata:core_files'] = 'Justificatifs des demandes, preuves des éléments du supplément et suppléments au diplôme émis (PDF).';
$string['privacy:metadata:core_message'] = 'Notifications envoyées sur les décisions, les propositions, les relances et les suppléments émis.';
$string['privacy:path:supplementitems'] = 'Éléments du supplément au diplôme';
$string['privacy:path:years'] = 'Années d\'étude';
$string['privacy:path:supplements'] = 'Suppléments au diplôme émis';
$string['privacy:path:staff'] = 'Actions en tant que personnel';
$string['resetcredits'] = 'Supprimer les dossiers des étudiants (inscriptions, demandes, EP portés au crédit, décisions, justificatifs)';
$string['resetyears'] = 'Supprimer les décisions sur les années d\'étude';
$string['resetsupplement'] = 'Supprimer les éléments du supplément au diplôme et les suppléments émis';
$string['resetsupplement_help'] = 'Les suppléments émis sont des documents certifiés : une fois supprimés, leur code n\'est plus reconnu par la page de vérification. Téléchargez d\'abord l\'export complet de l\'activité s\'ils doivent être conservés.';
$string['resetimportlog'] = 'Supprimer le journal des imports';
$string['supplementunsealed'] = 'Copie restaurée : non reconnue par la page de vérification';
$string['errorarchivetoolarge'] = 'Cette archive est trop volumineuse pour être restaurée depuis cette page (plus de 200 000 fichiers ou 4 Go une fois décompressée).';
$string['transfernotenrolled'] = '{$a} n\'est pas étudiant du cours de destination : inscrivez-le d\'abord dans ce cours.';
$string['hoursshort'] = '{$a} h';
$string['sendrequest'] = 'Envoyer la demande';
$string['addparticipantsrefine'] = 'Seuls les 200 premiers étudiants sont listés : recherchez par nom pour trouver les autres.';
$string['downloadworkingsyllabus'] = 'PDF de la version en cours de rédaction';
$string['syllabushistory'] = 'Historique des versions';
$string['syllabusreviewer'] = 'Examinée par';
$string['syllabussection'] = 'Fiches des EP';
$string['nosyllabusyet'] = 'Cet EP n\'a pas encore de fiche publiée.';
$string['review'] = 'Relire';
$string['proposalcontinue'] = 'Continuer vers la fiche';
$string['proposalcreated'] = 'EP proposé. Rédigez maintenant sa fiche, puis soumettez-la à la DEVE.';
$string['proposalsteps'] = 'Deux étapes : décrivez ici l\'EP (intitulé, type, ECTS, places, années), puis rédigez sa fiche (objectifs, programme...) et soumettez-la à la DEVE, qui la publie au catalogue.';
$string['taskmigratesyllabi'] = 'Reprise des fiches d\'EP dans l\'activité syllabus';
$string['registeryear'] = 'Année d\'étude de vos inscriptions';
$string['registrationwindow'] = 'Période d\'inscription du catalogue';
$string['registrationwindow_help'] = 'Les étudiants s\'inscrivent aux EP du catalogue pendant cette période, sauf pour un EP qui a la sienne. En dehors, seuls le responsable de l\'EP ou la DEVE peuvent ajouter un étudiant (page Participants). Une date laissée vide : sans limite.';
$string['registrationwindowreset'] = 'Appliquer aussi aux {$a} EP qui ont leur propre période';
$string['registrationwindowsaved'] = 'Période d\'inscription enregistrée.';
$string['errorregistrationrange'] = 'La fin de la période d\'inscription doit suivre son début.';
