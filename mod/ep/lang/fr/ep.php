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
$string['currentstudyyear'] = 'Année d\'étude courante (à défaut)';
$string['currentstudyyear_help'] = "Année d'étude dans laquelle se trouve la promotion. Elle sert de référence : "
    . "elle dit à quels EP du catalogue les étudiants peuvent s'inscrire, quels minimums annuels leur sont déjà "
    . "exigibles, et à quelle année ils peuvent rattacher un EP — la leur, la précédente (rattrapage) ou la "
    . "suivante (anticipation).\n\nElle est normalement lue dans l'activité « Gestion des stages » du cours, où "
    . "elle est déjà tenue à jour d'une année sur l'autre : ce paramètre ne sert que si aucune activité « Gestion "
    . "des stages » du cours ne la renseigne.";
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
$string['managetypes_help'] = "Les six types sont fixes : ils se paramètrent et se désactivent, ils ne s'ajoutent "
    . "ni ne se suppriment. Un plafond à 0 signifie « pas de plafond ». Les ECTS validés au-delà d'un plafond "
    . "restent acquis : ils cessent seulement d'être comptés, et reviennent au décompte si le plafond est "
    . "relevé.\n\nLa colonne « Calcul des ECTS » dit, pour chaque type déclaré par l'étudiant, d'où vient le "
    . "nombre d'ECTS demandés : proposé par l'étudiant au cas par cas, forfaitaire (par exemple 1 ECTS par "
    . "déclaration de sport), ou compté à la semaine. Le barème saisi sous la règle est le forfait, ou le nombre "
    . "d'ECTS par semaine.";
$string['managetypes_desc'] = "Libellé, consigne, activation, maximum d'ECTS retenus (par année et sur le cursus), "
    . "calcul des ECTS d'une déclaration (proposés par l'étudiant, forfait, ou par semaine) et, pour le type "
    . "stage, nombre d'ECTS par jour de stage complémentaire validé.";
$string['maxects'] = 'Maximum sur le cursus';
$string['maxectsperyear'] = 'Maximum par année';
$string['maxectsshort'] = 'max. {$a} ECTS';
$string['ectsperday'] = 'ECTS par jour de stage';
$string['ectsrule'] = 'Calcul des ECTS';
$string['ectsvalue'] = 'Barème';
$string['ectsvalueunset'] = 'barème à définir';
$string['ectsmode_free'] = 'Proposés par l\'étudiant';
$string['ectsmode_flat'] = 'Forfait par déclaration';
$string['ectsmode_weekly'] = 'Par semaine déclarée';
$string['ectsrulefree'] = 'ECTS proposés par l\'étudiant';
$string['ectsruleflat'] = 'Forfait de {$a} ECTS par déclaration';
$string['ectsruleweekly'] = '{$a} ECTS par semaine déclarée';
$string['ectsrulecatalog'] = 'Chaque EP du catalogue porte ses propres ECTS.';
$string['weeks'] = 'Nombre de semaines';
$string['weeks_help'] = "Nombre de semaines que cet enseignement personnalisé a représenté. Les ECTS demandés en "
    . "découlent : ce type se compte à la semaine, vous n'avez pas de nombre d'ECTS à proposer.";
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
$string['catalogactivityects_help'] = "Nombre d'ECTS attribués à l'étudiant lorsque le responsable valide sa "
    . "participation, à la fin de l'EP. Il est propre à cet EP : l'étudiant ne le choisit pas.";
$string['catalogactivityvisible_help'] = "Un EP masqué n'accepte plus de nouvelle inscription mais conserve celles "
    . "déjà prises. C'est ainsi qu'on ferme un EP qui n'est plus proposé, sans effacer les ECTS déjà accordés.";
$string['ects'] = 'ECTS';
$string['capacity'] = 'Nombre de places';
$string['capacity_help'] = "0 signifie « places illimitées ». Ce nombre ne bloque pas les inscriptions : les "
    . "étudiants s'inscrivent librement et c'est le responsable qui arbitre lesquelles il accepte, quitte à "
    . "dépasser le nombre de places s'il le juge utile. Seules les inscriptions acceptées occupent une place.";
$string['places'] = 'Places';
$string['placestaken'] = '{$a->taken} sur {$a->total}';
$string['activityfull'] = 'complet';
$string['unlimitedplaces'] = 'Illimitées';
$string['registrations'] = 'Inscriptions';
$string['countpending'] = '{$a} en attente';
$string['countenrolled'] = '{$a} acceptée(s)';
$string['countvalidated'] = '{$a} validée(s)';
$string['pendingregistrationscount'] = '{$a} inscription(s) en attente';
$string['catalogactivitytype'] = 'Type d\'EP';
$string['catalogactivityects'] = 'ECTS de cet EP';
$string['catalogactivityvisible'] = 'Ouvert aux inscriptions';
$string['sharedactivity'] = 'partagé';
$string['sharedactivities'] = 'EP partagés proposés à cette promotion';
$string['sharedactivities_help'] = "Ces EP sont définis dans une activité « Suivi de l'enseignement "
    . "personnalisé », parce que des étudiants de plusieurs promotions s'y inscrivent. Ils figurent au catalogue "
    . "de cette promotion au même titre que les autres, mais ne se modifient que là où ils sont définis.";
$string['sharedactivityorphan'] = 'activité de suivi supprimée';
$string['definedin'] = 'Défini dans';
$string['noownactivities'] = "Aucun EP propre à cette promotion pour l'instant.";
$string['openforregistration'] = 'Ouvert';
$string['addactivity'] = 'Ajouter un EP au catalogue';
$string['activitysaved'] = 'EP enregistré. Désignez maintenant son ou ses responsables.';
$string['activitydeleted'] = 'EP supprimé du catalogue.';
$string['confirmdeleteactivity'] = 'Supprimer définitivement cet EP du catalogue ?';
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
$string['registerpendingnotice'] = "Votre inscription sera transmise au responsable de l'EP, qui décidera de la "
    . "retenir ou non. Les ECTS ne vous seront comptés qu'à la fin de l'EP, lorsqu'il les validera.";
$string['confirmregistration'] = 'Confirmer mon inscription';
$string['registered'] = 'Inscription enregistrée, en attente de la décision du responsable.';
$string['registerclosed'] = 'Inscriptions fermées';
$string['registerfullnotice'] = "Toutes les places de cet EP sont déjà prises. Vous pouvez tout de même vous "
    . "inscrire : c'est le responsable qui décidera s'il retient votre inscription.";
$string['registerfullshort'] = 'Complet : inscription soumise à l\'accord du responsable.';
$string['notopentoyear'] = 'Hors de votre année d\'étude';
$string['catalogprocessnotice'] = "S'inscrire n'occupe pas de place et ne donne aucun ECTS : le responsable de "
    . "l'EP accepte d'abord les inscriptions, puis valide les ECTS à la fin de l'EP.";

// Déclaration hors catalogue.
$string['creditname'] = 'Intitulé';
$string['creditdescription'] = 'Description et justification';
$string['creditdescription_help'] = "Décrivez ce que vous avez fait, quand, et à quel titre. C'est sur cette "
    . "description et sur vos justificatifs que votre enseignant référent se prononcera.";
$string['claimedects'] = 'ECTS demandés';
$string['claimedects_help'] = "Nombre d'ECTS que vous demandez pour cet EP. Le validateur peut n'en retenir qu'une "
    . "partie sans refuser pour autant toute votre demande.\n\nCe champ ne s'affiche que pour les types qui vous "
    . "laissent proposer un nombre : les autres accordent un forfait par déclaration, ou se comptent à la "
    . "semaine.";
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
$string['awaitingectsvalidation'] = 'EP suivis dont les ECTS restent à valider';
$string['noenrolledcredits'] = "Aucun EP en cours n'attend de validation d'ECTS.";
$string['acceptregistration'] = 'Accepter l\'inscription';
$string['validateects'] = 'Valider les ECTS';
$string['registrationaccepted'] = 'Inscription acceptée.';
$string['decisionregistrationnotice'] = "Accepter l'inscription donne sa place à l'étudiant sur cet EP. Aucun "
    . "ECTS ne lui est encore compté : vous les validerez à la fin de l'EP, au vu de ce qu'il y aura fait.";
$string['decisionectsnotice'] = "L'EP est terminé : arrêtez le nombre d'ECTS effectivement retenus. Vous pouvez "
    . "n'en retenir qu'une partie sans avoir à refuser toute la demande.";
$string['acceptbeyondcapacity'] = "Toutes les places de cet EP sont déjà prises. Vous pouvez accepter cette "
    . "inscription malgré tout si vous le jugez utile : le nombre de places est un repère, pas une limite.";
$string['activityoccupancy'] = 'Places : {$a->places} — inscriptions en attente : {$a->pending}';
$string['allcreditsinscope'] = 'Tous les EP de votre périmètre';
$string['nopendingcredits'] = 'Aucune demande n\'attend votre décision.';
$string['nocredits'] = 'Aucun enseignement personnalisé.';
$string['validatecredit'] = 'Valider';
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
$string['confirmcancelcredit'] = 'Retirer cet EP du dossier de l\'étudiant ?';
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
$string['status_enrolled'] = 'Inscription acceptée';
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
$string['adminsectionimport'] = 'Import';
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

// Import Excel/CSV.
$string['importexcel'] = 'Import Excel';
$string['import'] = 'Importer';
$string['importactivities'] = 'Import du catalogue';
$string['importactivities_desc'] = "Créer en une fois plusieurs EP du catalogue propres à cette promotion, depuis "
    . "un tableau Excel.";
$string['importactivities_help'] = "Importez un fichier CSV (enregistré depuis Excel via « Enregistrer sous > "
    . "CSV »), avec les colonnes suivantes, dans cet ordre, séparées par des points-virgules ou des virgules, et "
    . "une ligne d'en-tête : <code>name;type;ects;minstudyyear;maxstudyyear;capacity;sortorder;visible</code>."
    . "<ul>"
    . "<li><em>name</em> : intitulé de l'EP (obligatoire)</li>"
    . "<li><em>type</em> : code ou libellé du type auquel rattacher l'EP (facultatif, académique interne par "
    . "défaut)</li>"
    . "<li><em>ects</em> : nombre d'ECTS que l'EP porte (obligatoire, supérieur à 0)</li>"
    . "<li><em>minstudyyear</em>, <em>maxstudyyear</em> : années d'étude concernées, en chiffres (facultatif, "
    . "0 = non précisée)</li>"
    . "<li><em>capacity</em> : nombre de places (facultatif, 0 = illimité)</li>"
    . "<li><em>sortorder</em> : ordre d'affichage (facultatif)</li>"
    . "<li><em>visible</em> : 1 (ou vide) pour ouvert aux inscriptions, 0/non pour masqué</li>"
    . "</ul>"
    . "Un EP portant le même intitulé qu'un EP déjà au catalogue de cette promotion est refusé, plutôt que "
    . "dupliqué. Aucun responsable n'est affecté par l'import : pensez à les désigner ensuite depuis la page du "
    . "catalogue, sans quoi les inscriptions resteront en attente indéfiniment.";
$string['importcredits'] = 'Import d\'inscriptions et de déclarations';
$string['importcredits_desc'] = "Porter en une fois au crédit de plusieurs étudiants une inscription à un EP du "
    . "catalogue ou une déclaration hors catalogue, décidée par ailleurs (dossier papier, régularisation de fin "
    . "d'année...).";
$string['importcredits_help'] = "Importez un fichier CSV (enregistré depuis Excel via « Enregistrer sous > "
    . "CSV »), avec les colonnes suivantes, dans cet ordre, séparées par des points-virgules ou des virgules, et "
    . "une ligne d'en-tête : "
    . "<code>email;ep;name;studyyear;claimedects;weeks;retainedects;status;comment</code>."
    . "<ul>"
    . "<li><em>email</em> : adresse de l'étudiant (doit être inscrit au cours)</li>"
    . "<li><em>ep</em> : intitulé exact d'un EP du catalogue (inscription), ou code/libellé d'un type déclarable "
    . "— engagement, expérience professionnelle, sport, académique externe (déclaration hors catalogue)</li>"
    . "<li><em>name</em> : intitulé de la déclaration (ignoré pour une inscription au catalogue, où c'est le nom "
    . "de l'EP qui est repris)</li>"
    . "<li><em>studyyear</em> : année d'étude de rattachement, en chiffres (facultatif, année courante de la "
    . "promotion par défaut)</li>"
    . "<li><em>claimedects</em> : ECTS demandés (ignoré pour une inscription au catalogue et pour un type à "
    . "forfait ou compté à la semaine, qui l'établissent eux-mêmes)</li>"
    . "<li><em>weeks</em> : nombre de semaines déclarées (types comptés à la semaine uniquement)</li>"
    . "<li><em>retainedects</em> : ECTS à retenir si le statut est « validé » (facultatif, égal aux ECTS "
    . "demandés par défaut)</li>"
    . "<li><em>status</em> : attente (par défaut), accepté (inscriptions au catalogue uniquement), validé ou "
    . "refusé</li>"
    . "<li><em>comment</em> : commentaire du validateur, s'il y a une décision à consigner</li>"
    . "</ul>"
    . "Les crédits importés sont enregistrés comme saisis par la DEVE. Une ligne dont l'étudiant a déjà une "
    . "inscription active sur le même EP, ou une déclaration identique déjà enregistrée, est refusée plutôt que "
    . "dupliquée.";
$string['importresult'] = '{$a} enseignement(s) personnalisé(s) importé(s) avec succès.';
$string['importerrorupload'] = "Le fichier n'a pas pu être téléversé. Vérifiez sa taille et réessayez.";
$string['importerrorline'] = 'Ligne {$a->line} : {$a->error}';
$string['importerrorincomplete'] = 'Ligne {$a} : adresse et EP/type sont tous deux obligatoires.';
$string['importerrormissingname'] = 'Ligne {$a} : intitulé manquant.';
$string['importerrorunknownemail'] = 'Ligne {$a->line} : aucun étudiant inscrit avec l\'adresse « {$a->email} ».';
$string['importerrorunknowntype'] = 'Ligne {$a->line} : type d\'EP « {$a->type} » introuvable.';
$string['importerrorunknowntarget'] = 'Ligne {$a->line} : ni EP du catalogue ni type déclarable ne correspond à '
    . '« {$a->target} ».';
$string['importerrorunknownstatus'] = 'Ligne {$a->line} : statut « {$a->status} » non reconnu (attente, accepté, '
    . 'validé ou refusé).';
$string['importerrorenrolledwithoutactivity'] = 'Ligne {$a} : le statut « accepté » n\'existe que pour une '
    . 'inscription à un EP du catalogue.';
$string['importerroractivityduplicate'] = 'Ligne {$a->line} : un EP « {$a->name} » existe déjà dans ce catalogue.';
$string['importerrorexistingregistration'] = 'Ligne {$a->line} : cet étudiant a déjà une inscription active sur '
    . '« {$a->ep} ».';
$string['importerrorduplicate'] = 'Ligne {$a} : une déclaration identique existe déjà pour cet étudiant.';
$string['importerrorduplicateinfile'] = 'Ligne {$a} : doublon avec une ligne précédente du même fichier.';

// Erreurs.
$string['errorpositiveects'] = 'Le nombre d\'ECTS doit être supérieur à 0.';
$string['errorpositiveweeks'] = 'Le nombre de semaines doit être supérieur à 0.';
$string['errortypeectsunset'] = "Le barème de ce type d'EP n'a pas encore été défini : signalez-le à la DEVE, qui "
    . "doit le renseigner avant que vous puissiez déclarer un EP de ce type.";
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
$string['errorunknownactivity'] = "Cet EP n'existe pas, ou n'est pas proposé ici.";
$string['errorwrongyear'] = 'Cet EP n\'est pas ouvert à cette année d\'étude.';
$string['errorevidencemissing'] = 'Ce justificatif est introuvable.';

// Confidentialité.
$string['privacy:metadata:ep_credit'] = "Les enseignements personnalisés portés au crédit d'un étudiant : "
    . "inscriptions au catalogue, déclarations et attributions automatiques.";
$string['privacy:metadata:ep_credit:userid'] = 'Étudiant à qui l\'enseignement personnalisé est porté au crédit.';
$string['privacy:metadata:ep_credit:name'] = 'Intitulé de l\'enseignement personnalisé.';
$string['privacy:metadata:ep_credit:description'] = 'Description et justification saisies par l\'étudiant.';
$string['privacy:metadata:ep_credit:weeks'] = 'Nombre de semaines déclarées, pour les types comptés à la '
    . 'semaine.';
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
