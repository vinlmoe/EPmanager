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
 * Chaînes françaises de mod_syllabus.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actionsnone'] = '-';
$string['addquestion'] = 'Ajouter une question';
$string['addsessions'] = 'Ajouter des séances';
$string['allcategories'] = 'Tout le site';
$string['anonymitynotice'] = 'Vos réponses sont anonymes : ni vos enseignants ni la DEVE ne peuvent savoir ce que vous avez répondu. Seul le fait que vous avez répondu est noté, pour que vous ne répondiez qu\'une fois.';
$string['answer'] = 'Réponse';
$string['answers'] = 'Réponses';
$string['answersn'] = '{$a} réponse(s) à cette question';
$string['belowthreshold'] = 'Les résultats ne s\'affichent qu\'à partir de {$a} réponses, pour préserver l\'anonymat des étudiants.';
$string['characteristics'] = 'Caractéristiques du module';
$string['code'] = 'Code';
$string['commonquestions_help'] = 'Questions posées par la DEVE à tous les modules ou aux modules de ce type. Elles seront figées à l\'ouverture de l\'évaluation, avec les vôtres.';
$string['continueediting'] = 'Reprendre la rédaction';
$string['decide_publish'] = 'Publier';
$string['decide_reject'] = 'Refuser';
$string['decide_return'] = 'Renvoyer aux responsables';
$string['decision_publish'] = 'publiée';
$string['decision_reject'] = 'refusée';
$string['decision_return'] = 'renvoyée pour corrections';
$string['distribution'] = 'Répartition';
$string['downloadcsv'] = 'Télécharger les résultats (CSV)';
$string['downloadpdf'] = 'Fiche en PDF';
$string['ects'] = 'Crédits (ECTS)';
$string['editnotice_publish'] = 'Une fois enregistrée, la fiche est publiée aussitôt : les étudiants la verront.';
$string['editnotice_review'] = 'Votre fiche sera relue par la DEVE avant d\'être publiée. Tant qu\'elle n\'est pas publiée, les étudiants voient la version précédente.';
$string['editquestion'] = 'Modifier la question';
$string['editsyllabus'] = 'Rédiger la fiche';
$string['errorbusy'] = 'Le module est en cours de modification par une autre personne : réessayez dans un instant.';
$string['errorchoices'] = 'Indiquez au moins deux choix, un par ligne.';
$string['errorcloseafteropen'] = 'La fermeture doit suivre l\'ouverture.';
$string['errorcommentrequired'] = 'Un commentaire est nécessaire pour renvoyer ou refuser une fiche.';
$string['errorfrozen'] = 'L\'évaluation est ouverte : ses questions ne se modifient plus.';
$string['errorname'] = 'Le nom est obligatoire.';
$string['errornegative'] = 'La valeur ne peut pas être négative.';
$string['errornotdraft'] = 'Cette version attend la DEVE : elle ne se modifie plus.';
$string['erroropenrequired'] = 'Indiquez aussi l\'ouverture.';
$string['errorquestiontext'] = 'Le texte de la question est obligatoire.';
$string['errortype'] = 'Choisissez le type de module visé.';
$string['errorvalue'] = 'Réponse invalide.';
$string['evalclose'] = 'Fermeture de l\'évaluation';
$string['evalclosed'] = 'L\'évaluation de ce module est close.';
$string['evalclosed_student'] = 'L\'évaluation de ce module est close ({$a}).';
$string['evaldone'] = 'Vous avez déjà évalué ce module.';
$string['evaldone_student'] = 'Vous avez évalué ce module. Merci !';
$string['evalerrors'] = 'Certaines réponses manquent ou sont invalides.';
$string['evalinvite'] = 'L\'évaluation de ce module est ouverte ({$a}). Votre avis aide les enseignants à faire évoluer le module.';
$string['evalnone'] = 'Ce module n\'a pas d\'évaluation.';
$string['evalnone_student'] = 'Ce module n\'a pas d\'évaluation.';
$string['evalnotallowed'] = 'Vous n\'êtes pas appelé à évaluer ce module.';
$string['evalnotallowed_student'] = 'Vous n\'êtes pas appelé à évaluer ce module.';
$string['evalnowindow'] = 'Aucune évaluation prévue';
$string['evalopen'] = 'Ouverture de l\'évaluation';
$string['evalopen_help'] = 'À partir de cette date, les étudiants du cours peuvent évaluer le module, une fois chacun, de façon anonyme. Ils en sont prévenus par un message. À l\'ouverture, les questions (celles de la DEVE et les vôtres) sont figées.';
$string['evalquestions'] = 'Questions de l\'évaluation';
$string['evalstate_closed'] = 'Close';
$string['evalstate_none'] = 'Non prévue';
$string['evalstate_open'] = 'Ouverte';
$string['evalstate_upcoming'] = 'À venir';
$string['evalthanks'] = 'Merci : votre évaluation est enregistrée.';
$string['evaluatemodule'] = 'Évaluer ce module';
$string['evaluation'] = 'Évaluation du module';
$string['evaluation_help'] = 'Les étudiants évaluent le module pendant la fenêtre ci-dessous. Ils répondent d\'abord aux questions de la DEVE (communes à tous les modules ou à ce type de module), puis aux vôtres.';
$string['evaluationsettings'] = 'Fenêtre et questions';
$string['evalupcoming'] = 'L\'évaluation de ce module n\'est pas encore ouverte.';
$string['evalupcoming_student'] = 'Vous pourrez évaluer ce module : {$a}.';
$string['evalwindow'] = 'du {$a->open} au {$a->close}';
$string['evalwindowheading'] = 'Fenêtre d\'évaluation';
$string['eventevaluationsubmitted'] = 'Module évalué';
$string['fiche'] = 'Fiche du module';
$string['fichepublished'] = 'Fiche publiée.';
$string['fichesaved'] = 'Brouillon enregistré.';
$string['fichestate'] = 'Rédaction de la fiche';
$string['fichesubmitted'] = 'Fiche transmise à la DEVE.';
$string['field_assessment'] = 'Modalités d\'évaluation';
$string['field_bibliography'] = 'Bibliographie et ressources';
$string['field_content'] = 'Contenu';
$string['field_methods'] = 'Méthodes d\'enseignement';
$string['field_objectives'] = 'Objectifs';
$string['field_outcomes'] = 'Acquis d\'apprentissage visés';
$string['field_outcomes_help'] = 'Ce que l\'étudiant saura faire à l\'issue du module, formulé avec des verbes d\'action (identifier, réaliser, interpréter...).';
$string['field_prerequisitestext'] = 'Prérequis';
$string['field_resit'] = 'Seconde session';
$string['frozennotice'] = 'L\'évaluation est ouverte : ses questions sont figées.';
$string['hoursn'] = '{$a} h';
$string['hoursperects'] = '{$a} h par ECTS';
$string['hourspersonal'] = 'Travail personnel : {$a} h';
$string['hourssessions'] = 'Séances : {$a} h';
$string['hourstotal'] = 'Volume horaire';
$string['hoursworkload'] = 'Charge totale : {$a} h';
$string['language'] = 'Langue d\'enseignement';
$string['mean'] = 'Moyenne';
$string['meanis'] = 'Moyenne : {$a} / 5';
$string['messageprovider:evaluationopen'] = 'Ouverture de l\'évaluation d\'un module';
$string['messageprovider:syllabusreviewed'] = 'Décision de la DEVE sur une fiche de module';
$string['messageprovider:syllabussubmitted'] = 'Fiche de module à relire';
$string['module'] = 'Module';
$string['modulename'] = 'Syllabus du module';
$string['modulename_field'] = 'Nom du module';
$string['modulename_help'] = 'La fiche du module (syllabus au modèle ECTS : objectifs, acquis, contenu, méthodes, modalités d\'évaluation, programme des séances, volume horaire), rédigée par ses responsables et, si la DEVE le demande, relue par elle ; et l\'évaluation du module par les étudiants, à partir d\'une date, sur les questions de la DEVE et celles des enseignants.';
$string['modulenameplural'] = 'Syllabus des modules';
$string['moduletype'] = 'Type de module';
$string['moduletype_help'] = 'Types définis par la DEVE. La DEVE peut poser des questions d\'évaluation propres à un type de module.';
$string['msgopen_body'] = 'L\'évaluation du module « {$a->module} » est ouverte jusqu\'au {$a->close}. Vos réponses sont anonymes : {$a->url}';
$string['msgopen_subject'] = 'Évaluez le module « {$a->module} »';
$string['msgreviewed_body'] = 'La fiche du module « {$a->module} » a été {$a->decision}. {$a->comment} {$a->url}';
$string['msgreviewed_subject'] = 'Fiche du module « {$a->module} » : {$a->decision}';
$string['msgsubmitted_body'] = 'La fiche du module « {$a->module} » attend votre relecture : {$a->url}';
$string['msgsubmitted_subject'] = 'Fiche à relire : « {$a->module} »';
$string['noclose'] = 'sans date de fin';
$string['nofiche'] = 'La fiche de ce module n\'est pas encore publiée.';
$string['noinstances'] = 'Aucun syllabus dans ce cours.';
$string['nopublished'] = 'Aucune version publiée : les étudiants ne voient pas encore de fiche.';
$string['noquestions'] = 'Aucune question.';
$string['noteachers'] = 'Aucun inscrit du cours ne peut être désigné responsable (capacité mod/syllabus:edit).';
$string['notype'] = 'Aucun type';
$string['origin_common'] = 'DEVE';
$string['origin_teacher'] = 'Enseignant';
$string['origin_type'] = 'DEVE (type)';
$string['part_common'] = 'Questions de la DEVE';
$string['part_teacher'] = 'Questions de l\'enseignant';
$string['pdfversion'] = 'Version {$a->version} du {$a->date}';
$string['personalhours'] = 'Travail personnel (heures)';
$string['pluginadministration'] = 'Administration du syllabus';
$string['pluginname'] = 'Syllabus du module';
$string['proposeupdate'] = 'Mettre à jour la fiche';
$string['publishedversion'] = 'Version publiée : {$a->version}, du {$a->date}.';
$string['question'] = 'Question';
$string['questionenabled'] = 'Active';
$string['questionkind'] = 'Forme de la réponse';
$string['questionkind_choice'] = 'Choix dans une liste';
$string['questionkind_help'] = 'Échelle de 1 (pas du tout d\'accord) à 5 (tout à fait d\'accord), oui ou non, un choix dans une liste, ou une réponse libre.';
$string['questionkind_scale'] = 'Échelle de 1 à 5';
$string['questionkind_text'] = 'Réponse libre';
$string['questionkind_yesno'] = 'Oui / non';
$string['questionoptions'] = 'Choix proposés (un par ligne)';
$string['questionrequired'] = 'Réponse obligatoire';
$string['questionsaved'] = 'Question enregistrée.';
$string['questionscope'] = 'Posée à';
$string['questiontext'] = 'Question';
$string['resetresponses'] = 'Effacer les réponses aux évaluations des modules';
$string['responsesrate'] = '{$a->responses} réponse(s) sur {$a->eligible} étudiant(s).';
$string['results'] = 'Résultats de l\'évaluation';
$string['resultsafterclose'] = 'Les résultats de l\'évaluation seront visibles après sa fermeture.';
$string['reviewchanged'] = 'Cette version a déjà été traitée par quelqu\'un d\'autre.';
$string['reviewcomment'] = 'Commentaire aux responsables';
$string['reviewcommentis'] = 'Commentaire de la DEVE : {$a}';
$string['reviewdone_publish'] = 'Fiche publiée.';
$string['reviewdone_reject'] = 'Fiche refusée.';
$string['reviewdone_return'] = 'Fiche renvoyée aux responsables.';
$string['reviewheading'] = 'Relecture par la DEVE';
$string['savedraft'] = 'Enregistrer le brouillon';
$string['savepublish'] = 'Enregistrer et publier';
$string['savequestion'] = 'Enregistrer la question';
$string['savesubmit'] = 'Enregistrer et soumettre à la DEVE';
$string['savewindow'] = 'Enregistrer la fenêtre';
$string['scale_1'] = '1 - Pas du tout d\'accord';
$string['scale_2'] = '2 - Plutôt pas d\'accord';
$string['scale_3'] = '3 - Ni d\'accord ni pas d\'accord';
$string['scale_4'] = '4 - Plutôt d\'accord';
$string['scale_5'] = '5 - Tout à fait d\'accord';
$string['scope_common'] = 'Tous les modules';
$string['scope_type'] = 'Les modules d\'un type';
$string['scope_typeof'] = 'Modules « {$a} »';
$string['semester'] = 'Période';
$string['semester_help'] = 'Semestre ou période d\'enseignement, en clair (par exemple « S5 » ou « Automne »).';
$string['sessionduration'] = 'Durée (h)';
$string['sessionkind'] = 'Nature';
$string['sessionkind_clinical'] = 'Clinique';
$string['sessionkind_fieldwork'] = 'Terrain';
$string['sessionkind_lecture'] = 'Cours magistral';
$string['sessionkind_other'] = 'Autre';
$string['sessionkind_practical'] = 'Travaux pratiques';
$string['sessionkind_project'] = 'Projet';
$string['sessionkind_tutorial'] = 'Travaux dirigés';
$string['sessions'] = 'Programme des séances';
$string['sessions_help'] = 'Une ligne par séance : intitulé, nature, durée en heures et intervenant (un enseignant du cours, ou le nom d\'un intervenant extérieur). Les lignes sans intitulé sont ignorées.';
$string['sessionteacher'] = 'Intervenant';
$string['sessionteachername'] = 'ou intervenant extérieur';
$string['sessiontitle'] = 'Séance';
$string['showpublished'] = 'Voir la version publiée';
$string['showworking'] = 'Voir la version en cours';
$string['status_draft'] = 'Brouillon';
$string['status_published'] = 'Publiée';
$string['status_rejected'] = 'Refusée';
$string['status_returned'] = 'Renvoyée par la DEVE';
$string['status_submitted'] = 'Soumise à la DEVE';
$string['status_superseded'] = 'Remplacée';
$string['studyyear'] = 'Année d\'étude';
$string['studyyear_n'] = 'A{$a}';
$string['studyyear_none'] = 'Non précisée';
$string['submitevaluation'] = 'Envoyer mon évaluation';
$string['syllabus:addinstance'] = 'Ajouter un syllabus de module';
$string['syllabus:edit'] = 'Être responsable d\'un module (rédiger sa fiche, poser des questions d\'évaluation)';
$string['syllabus:evaluate'] = 'Évaluer un module';
$string['syllabus:manage'] = 'Gérer les modules (DEVE) : responsables, relecture, évaluation, résultats';
$string['syllabus:view'] = 'Lire la fiche d\'un module';
$string['taskopenevaluations'] = 'Ouverture des évaluations des modules';
$string['teachers'] = 'Responsables';
$string['teachers_help'] = 'Les responsables rédigent la fiche du module, posent leurs questions d\'évaluation et en voient les résultats. Ils sont choisis parmi les enseignants du cours.';
$string['teacherselect'] = 'Responsable';
$string['teacherssaved'] = '{$a} responsable(s) enregistré(s).';
$string['windowsaved'] = 'Fenêtre d\'évaluation enregistrée.';
$string['workingnotice'] = 'Version en cours ({$a}) : les étudiants ne la voient pas encore.';
$string['workingversion'] = 'Version en cours : {$a->version} ({$a->status}).';
$string['writefiche'] = 'Rédiger la fiche';
$string['privacy:metadata:messages'] = 'Messages envoyés : ouverture d\'une évaluation, fiche à relire, décision de la DEVE.';
$string['privacy:metadata:participant'] = 'Étudiants qui ont évalué le module (pas leurs réponses).';
$string['privacy:metadata:participant:userid'] = 'Étudiant qui a répondu.';
$string['privacy:metadata:response'] = 'Réponses anonymes aux évaluations : sans identifiant, elles ne se rattachent à personne.';
$string['privacy:metadata:response:daysubmitted'] = 'Jour de la réponse, sans l\'heure.';
$string['privacy:metadata:session'] = 'Séances du programme.';
$string['privacy:metadata:session:teacherid'] = 'Intervenant de la séance.';
$string['privacy:metadata:teacher'] = 'Responsables du module.';
$string['privacy:metadata:teacher:userid'] = 'Responsable.';
$string['privacy:metadata:version'] = 'Versions de la fiche du module.';
$string['privacy:metadata:version:authorid'] = 'Auteur de la version.';
$string['privacy:metadata:version:reviewcomment'] = 'Commentaire de relecture.';
$string['privacy:metadata:version:reviewerid'] = 'Membre de la DEVE qui a relu la version.';
