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
 * Library of interface functions and constants for mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function epsynthesis_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : MOD_PURPOSE_OTHER;
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_epsynthesis in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_epsynthesis_mod_form|null $mform
 * @return int The id of the newly inserted record.
 */
function epsynthesis_add_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    return $DB->insert_record('epsynthesis', $moduleinstance);
}

/**
 * Updates an instance of mod_epsynthesis in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_epsynthesis_mod_form|null $mform
 * @return bool True on success.
 */
function epsynthesis_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('epsynthesis', $moduleinstance);
}

/**
 * Removes an instance of mod_epsynthesis from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function epsynthesis_delete_instance($id) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/ep/locallib.php');

    if (!$DB->get_record('epsynthesis', ['id' => $id])) {
        return false;
    }

    // Les EP partagés qu'elle définissait ne sont plus proposés nulle part, les activités liées
    // ayant disparu avec elle. Ceux auxquels personne ne s'est inscrit sont supprimés ; ceux qui
    // portent des inscriptions sont conservés tels quels — les ECTS déjà accordés aux étudiants
    // s'y rattachent, et les effacer les laisserait sans origine (voir
    // ep_render_shared_activity_origin(), qui signale un EP dont la synthèse a disparu).
    $cm = get_coursemodule_from_instance('epsynthesis', $id, 0, false, IGNORE_MISSING);
    if ($cm) {
        foreach ($DB->get_fieldset_select('ep_activity', 'id', 'synthesiscmid = ?', [$cm->id]) as $activityid) {
            ep_delete_activity($activityid);
        }
    }

    $DB->delete_records('epsynthesis_link', ['synthesisid' => $id]);
    $DB->delete_records('epsynthesis', ['id' => $id]);

    return true;
}
