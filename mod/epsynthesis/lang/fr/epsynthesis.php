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
 * French strings for mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Suivi de l\'enseignement personnalisé (synthèse enseignant)';
$string['modulename'] = 'Suivi de l\'enseignement personnalisé';
$string['modulenameplural'] = 'Suivis de l\'enseignement personnalisé';
$string['modulename_help'] = "Regroupe, pour chaque enseignant, tout ce qu'il a à suivre en matière "
    . "d'enseignement personnalisé sur les activités « Enseignement personnalisé » liées à cette activité — les "
    . "inscriptions aux EP dont il est responsable et les déclarations des étudiants dont il est enseignant "
    . "référent, même s'ils viennent de plusieurs promotions/cours différents.\n\nLes activités à faire remonter "
    . "se choisissent dans l'administration de cette activité ; une promotion qui n'est plus suivie peut ainsi "
    . "être retirée sans toucher à son activité d'origine. Les droits (qui est référent de qui, qui est "
    . "responsable de quel EP) restent gérés dans chaque activité « Enseignement personnalisé » : cette activité "
    . "ne fait qu'en donner une vue regroupée, elle n'accorde aucun droit supplémentaire.";
$string['modulename_link'] = 'mod/epsynthesis/view';
$string['pluginadministration'] = 'Administration de Suivi de l\'enseignement personnalisé';

$string['epsynthesis:addinstance'] = 'Ajouter une activité Suivi de l\'enseignement personnalisé';
$string['epsynthesis:view'] = 'Voir la synthèse de ses propres étudiants et EP';
$string['epsynthesis:viewall'] = 'Suivre tous les EP académiques du périmètre (DEVE)';
$string['epsynthesis:manageactivities'] = 'Définir les EP académiques partagés et leurs responsables';
$string['epsynthesis:managelinks'] = 'Gérer les activités « Enseignement personnalisé » liées';

$string['epsynthesisname'] = 'Nom de l\'activité';
$string['linksnotice'] = 'Le choix des activités « Enseignement personnalisé » à faire remonter ici se fait après '
    . 'l\'enregistrement, depuis la page de l\'activité (lien « Gérer les liens »).';

$string['managelinks'] = 'Gérer les liens';
$string['managelinks_help'] = 'Cochez les activités « Enseignement personnalisé » dont les étudiants et les EP '
    . 'doivent apparaître ici pour les enseignants concernés. Décochez une activité (par exemple une promotion '
    . 'sortie) pour la retirer de la synthèse sans la supprimer ni modifier ses données.';
$string['linkedcount'] = '{$a} activité(s) « Enseignement personnalisé » liée(s).';
$string['linkssaved'] = 'Liste des activités liées mise à jour.';
$string['linked'] = 'Lié';
$string['noactivities'] = 'Aucune activité « Enseignement personnalisé » n\'existe encore sur cette plateforme.';
$string['hiddencourse'] = 'cours masqué';
$string['hiddenactivity'] = 'activité masquée';

// EP académiques partagés.
$string['manageactivities'] = 'EP académiques partagés';
$string['manageactivities_help'] = "Les EP définis ici sont proposés au catalogue de toutes les promotions "
    . "suivies par cette activité : c'est ainsi qu'un même EP accueille des étudiants d'années différentes sans "
    . "être recopié dans chaque promotion — deux copies auraient chacune leurs places et leurs inscrits, alors "
    . "que ce sont les mêmes. Chaque EP est validé par son ou ses responsables, désignés ici.";
$string['addsharedactivity'] = 'Ajouter un EP partagé';
$string['nosharedactivities'] = "Aucun EP partagé n'est défini pour l'instant.";
$string['sharedorigin'] = 'EP partagé';
$string['sharedstudyyearrange'] = 'Années d\'étude concernées';
$string['sharedstudyyearrange_help'] = "Années d'étude auxquelles cet EP est ouvert (0 = sans restriction). "
    . "L'année d'un étudiant est celle de sa promotion, telle que la renseigne l'activité « Gestion des stages » "
    . "de son cours : un étudiant dont la promotion est hors de cette plage ne peut pas s'inscrire.";
$string['sharedactivityteachers_help'] = "Cochez les enseignants responsables de cet EP. Ce sont eux, et eux "
    . "seuls, qui acceptent les inscriptions puis valident les ECTS — pour tous les inscrits, quelle que soit "
    . "leur promotion, et sans avoir besoin d'un rôle dans le cours de chacune.";
$string['nopotentialteachers'] = "Aucun enseignant n'a accès à cette activité de suivi : inscrivez d'abord les "
    . "enseignants concernés à ce cours.";

// Suivi des inscriptions, EP par EP.
$string['registrationsfollowup'] = 'Suivi des EP académiques';
$string['registrationsfollowup_help'] = "Où en est chaque EP académique : qui a demandé à s'y inscrire, qui a "
    . "été accepté, et pour qui les ECTS restent à valider. Y figurent les EP partagés définis ici et les EP "
    . "propres à chaque promotion suivie.";
$string['viewregistrations'] = 'Voir les inscriptions';
$string['nofollowupactivities'] = "Aucun EP académique à suivre : vous n'êtes responsable d'aucun EP sur le "
    . "périmètre de cette activité.";
$string['noregistrations'] = 'Aucune inscription à afficher.';
$string['registrationorigin'] = 'Inscription à l\'EP « {$a->activity} » — promotion : {$a->course}';
$string['decisionnotyours'] = "Cette inscription attend la décision du responsable de l'EP : vous la suivez "
    . "ici, mais c'est à lui de se prononcer.";
$string['errornotaregistration'] = "Cet enseignement personnalisé n'est pas une inscription à un EP du "
    . "catalogue : il se valide dans l'activité « Enseignement personnalisé » de la promotion de l'étudiant, par "
    . "son enseignant référent.";

$string['noscope'] = "Aucun étudiant ne vous est attribué comme enseignant référent et vous n'êtes responsable "
    . "d'aucun EP sur les activités liées.";

// Confidentialité. Tout ce que la synthèse affiche est lu dans les activités « Enseignement personnalisé »
// liées ; la seule donnée qui lui soit propre est la désignation des responsables de ses EP partagés.
$string['privacy:metadata:ep_activity_teacher'] = "Enseignants désignés responsables d'un EP partagé défini dans "
    . "cette activité de suivi.";
$string['privacy:metadata:ep_activity_teacher:teacherid'] = 'Enseignant responsable.';
