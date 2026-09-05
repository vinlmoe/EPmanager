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

$string['noscope'] = "Aucun étudiant ne vous est attribué comme enseignant référent et vous n'êtes responsable "
    . "d'aucun EP sur les activités liées.";

$string['privacy:metadata'] = "Le plugin Suivi de l'enseignement personnalisé ne stocke aucune donnée "
    . "personnelle : il affiche uniquement des données déjà présentes dans les activités « Enseignement "
    . "personnalisé » liées.";
