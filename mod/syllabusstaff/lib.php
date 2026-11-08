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
 * API Moodle de mod_syllabusstaff.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fonctionnalités prises en charge.
 *
 * @param string $feature
 * @return mixed
 */
function syllabusstaff_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ADMINISTRATION;
        default:
            return null;
    }
}

/**
 * Crée une instance.
 *
 * @param stdClass $data
 * @param mixed $mform
 * @return int
 */
function syllabusstaff_add_instance($data, $mform = null) {
    global $DB;
    $data->categoryid = (int) ($data->categoryid ?? 0);
    $data->timecreated = $data->timemodified = time();
    return $DB->insert_record('syllabusstaff', $data);
}

/**
 * Met à jour une instance.
 *
 * @param stdClass $data
 * @param mixed $mform
 * @return bool
 */
function syllabusstaff_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->categoryid = (int) ($data->categoryid ?? 0);
    $data->timemodified = time();
    return $DB->update_record('syllabusstaff', $data);
}

/**
 * Supprime une instance (elle ne porte aucune donnée propre).
 *
 * @param int $id
 * @return bool
 */
function syllabusstaff_delete_instance($id) {
    global $DB;
    return $DB->delete_records('syllabusstaff', ['id' => $id]);
}
