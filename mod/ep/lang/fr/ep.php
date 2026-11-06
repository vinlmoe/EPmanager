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
    . "complémentaires validés par la DEVE. Ce recalcul est de toute façon fait chaque nuit et à l'ouverture des "
    . "pages de l'activité : ce bouton sert à en vérifier l'effet tout de suite après avoir changé le barème.";
$string['syncdone'] = 'Synchronisation terminée : {$a->created} crédit(s) créé(s), {$a->updated} mis à jour, '
    . '{$a->deleted} retiré(s).';
$string['tasksyncstagecredits'] = 'Attribution des ECTS d\'EP issus des stages complémentaires';

// Catalogue.
$string['catalogactivity'] = 'EP';
$string['catalogactivitytype_help'] = "Type auquel rattacher cet EP : c'est lui qui détermine le plafond d'ECTS "
    . "applicable à l'étudiant. Il s'agit normalement du type académique interne.";
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
$string['privacy:metadata:ep_credit'] = "Les enseignements personnalisés portés au crédit d'un étudiant : "
    . "inscriptions au catalogue, déclarations et attributions automatiques.";
$string['privacy:metadata:ep_credit:userid'] = 'Étudiant à qui l\'enseignement personnalisé est porté au crédit.';
$string['privacy:metadata:ep_credit:name'] = 'Intitulé de l\'enseignement personnalisé.';
$string['privacy:metadata:ep_credit:description'] = 'Description et justification saisies par l\'étudiant.';
$string['privacy:metadata:ep_credit:claimedects'] = 'Nombre d\'ECTS demandés.';
$string['privacy:metadata:ep_credit:retainedects'] = 'Nombre d\'ECTS retenus après validation.';
$string['privacy:metadata:ep_credit:status'] = 'État de la demande (en attente, validée, refusée, retirée).';
$string['privacy:metadata:ep_credit:validatedby'] = 'Utilisateur ayant validé, refusé ou retiré la demande.';
$string['privacy:metadata:ep_credit:validatorcomment'] = 'Commentaire du validateur.';
$string['privacy:metadata:ep_credit:timecreated'] = 'Date de la demande.';
$string['privacy:metadata:ep_activity_teacher'] = "Enseignants désignés responsables d'un EP du catalogue.";
$string['privacy:metadata:ep_activity_teacher:teacherid'] = 'Enseignant responsable.';
$string['privacy:metadata:core_files'] = 'Justificatifs déposés à l\'appui d\'une demande.';
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
$string['sessiondate'] = 'Date';
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
