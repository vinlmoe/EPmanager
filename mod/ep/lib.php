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
 * Library of interface functions and constants for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Crédit annulé (désinscription de l'étudiant, retrait par la DEVE) : état terminal, conservé. */
define('EP_STATUS_CANCELLED', -2);
/** Crédit refusé par le responsable de l'EP ou l'enseignant référent, avec motif. */
define('EP_STATUS_REJECTED', -1);
/** Crédit demandé (inscription ou déclaration), en attente de validation. */
define('EP_STATUS_PENDING', 0);
/** Crédit validé : ses ECTS entrent dans le décompte, sous réserve des plafonds par type. */
define('EP_STATUS_VALIDATED', 1);

/** Nombre de lignes par page pour les listes paginées (DEVE / enseignants). */
define('EP_LIST_PERPAGE', 40);

/** EP académique interne : inscription à un EP du catalogue, validée par son responsable. */
define('EP_TYPE_ACADEMIC', 'academic');
/** EP de type stage : attribué automatiquement d'après les stages complémentaires (EP) de mod_stage. */
define('EP_TYPE_STAGE', 'stage');
/** Engagement étudiant (associatif, représentation, tutorat...). */
define('EP_TYPE_ENGAGEMENT', 'engagement');
/** Expérience professionnelle. */
define('EP_TYPE_PROFESSIONAL', 'professional');
/** Sport (haut niveau, encadrement...). */
define('EP_TYPE_SPORT', 'sport');
/** EP académique suivi hors de l'établissement. */
define('EP_TYPE_EXTERNAL', 'external');

/** Crédit issu d'une inscription ou d'une déclaration de l'étudiant. */
define('EP_SOURCE_STUDENT', 'student');
/** Crédit attribué automatiquement depuis un stage complémentaire (EP) validé par la DEVE. */
define('EP_SOURCE_STAGE', 'stage');
/** Crédit saisi directement par la DEVE pour le compte d'un étudiant. */
define('EP_SOURCE_DEVE', 'deve');

/**
 * Zone de fichiers des justificatifs déposés par l'étudiant à l'appui d'une déclaration d'EP
 * (engagement, expérience professionnelle, sport, académique externe), l'itemid étant
 * l'identifiant du crédit (ep_credit.id).
 */
define('EP_EVIDENCE_FILEAREA', 'evidence');

/**
 * Délai minimal entre deux synchronisations des EP de type stage déclenchées par l'affichage
 * d'une page (voir ep_sync_stage_credits_if_due()) : la tâche planifiée quotidienne fait foi,
 * cette synchronisation d'opportunité ne sert qu'à ne pas afficher un décompte visiblement
 * périmé juste après une validation DEVE.
 */
define('EP_STAGE_SYNC_INTERVAL', 300);

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function ep_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : 'administration';
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_ep into the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_ep_mod_form|null $mform
 * @return int New instance id.
 */
function ep_add_instance($moduleinstance, $mform = null) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/ep/locallib.php');

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    $epid = $DB->insert_record('ep', $moduleinstance);

    // Les six types d'enseignement personnalisé sont créés avec l'instance : la DEVE n'a plus
    // qu'à les paramétrer (plafonds, ECTS par jour de stage), sans avoir à deviner quels codes de
    // type le reste du module attend.
    ep_create_default_types($epid);

    return $epid;
}

/**
 * Updates an instance of mod_ep in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_ep_mod_form|null $mform
 * @return bool True on success.
 */
function ep_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('ep', $moduleinstance);
}

/**
 * Removes an instance of mod_ep from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function ep_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('ep', ['id' => $id])) {
        return false;
    }

    $activityids = $DB->get_fieldset_select('ep_activity', 'id', 'epid = ?', [$id]);
    if ($activityids) {
        [$insql, $inparams] = $DB->get_in_or_equal($activityids);
        $DB->delete_records_select('ep_activity_teacher', "activityid $insql", $inparams);
    }
    $DB->delete_records('ep_credit', ['epid' => $id]);
    $DB->delete_records('ep_activity', ['epid' => $id]);
    $DB->delete_records('ep_year_requirement', ['epid' => $id]);
    $DB->delete_records('ep_type', ['epid' => $id]);
    $DB->delete_records('ep', ['id' => $id]);

    return true;
}

/**
 * Returns a small object with summary information about what a user has done with a given
 * particular instance of this module.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info|stdClass $mod
 * @param stdClass $ep
 * @return stdClass|null
 */
function ep_user_outline($course, $user, $mod, $ep) {
    global $DB;

    $count = $DB->count_records('ep_credit', ['epid' => $ep->id, 'userid' => $user->id]);
    if (!$count) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('numcredits', 'mod_ep', $count);
    $result->time = time();
    return $result;
}
