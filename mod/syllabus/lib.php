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
 * Fonctions de l'API Moodle pour mod_syllabus : une instance par cours de module, qui porte la
 * fiche du module (syllabus) et son évaluation par les étudiants.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Version en cours de rédaction. */
define('SYLLABUS_STATUS_DRAFT', 'draft');
/** Version soumise à la DEVE. */
define('SYLLABUS_STATUS_SUBMITTED', 'submitted');
/** Version publiée : celle que lisent les étudiants. */
define('SYLLABUS_STATUS_PUBLISHED', 'published');
/** Version renvoyée à son auteur par la DEVE, à reprendre. */
define('SYLLABUS_STATUS_RETURNED', 'returned');
/** Version refusée par la DEVE. */
define('SYLLABUS_STATUS_REJECTED', 'rejected');
/** Ancienne version publiée, remplacée par une plus récente. */
define('SYLLABUS_STATUS_SUPERSEDED', 'superseded');

/** Évaluation : aucune fenêtre fixée. */
define('SYLLABUS_EVAL_NONE', 'none');
/** Évaluation : fenêtre fixée, pas encore ouverte. */
define('SYLLABUS_EVAL_UPCOMING', 'upcoming');
/** Évaluation ouverte. */
define('SYLLABUS_EVAL_OPEN', 'open');
/** Évaluation close. */
define('SYLLABUS_EVAL_CLOSED', 'closed');

/**
 * Fonctionnalités prises en charge.
 *
 * @param string $feature FEATURE_xx
 * @return mixed
 */
function syllabus_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Valeurs de l'instance tirées du formulaire de réglages, dans les bornes attendues.
 *
 * @param stdClass $data
 * @return stdClass
 */
function syllabus_clean_instance(stdClass $data): stdClass {
    $data->code = core_text::substr(trim((string) ($data->code ?? '')), 0, 30);
    $data->semester = core_text::substr(trim((string) ($data->semester ?? '')), 0, 50);
    $data->ects = max(0, round((float) ($data->ects ?? 0), 2));
    $data->studyyear = max(0, min(6, (int) ($data->studyyear ?? 0)));
    $data->typeid = (int) ($data->typeid ?? 0);
    $data->evalopen = (int) ($data->evalopen ?? 0);
    $data->evalclose = (int) ($data->evalclose ?? 0);
    return $data;
}

/**
 * Crée une instance. L'enseignant qui l'ajoute en devient responsable s'il peut l'être : un
 * module sans responsable ne serait rédigé par personne.
 *
 * @param stdClass $data
 * @param mod_syllabus_mod_form|null $mform
 * @return int
 */
function syllabus_add_instance($data, $mform = null) {
    global $DB, $USER;

    $data = syllabus_clean_instance($data);
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->id = $DB->insert_record('syllabus', $data);

    $context = context_module::instance($data->coursemodule);
    if (isloggedin() && !isguestuser() && has_capability('mod/syllabus:edit', $context)) {
        $DB->insert_record('syllabus_teacher', (object) [
            'syllabusid' => $data->id, 'userid' => $USER->id, 'timecreated' => time(),
        ]);
    }
    return $data->id;
}

/**
 * Met à jour une instance. Une nouvelle date d'ouverture de l'évaluation sera annoncée de
 * nouveau aux étudiants.
 *
 * @param stdClass $data
 * @param mod_syllabus_mod_form|null $mform
 * @return bool
 */
function syllabus_update_instance($data, $mform = null) {
    global $DB;

    $data = syllabus_clean_instance($data);
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('syllabus', $data);
    return true;
}

/**
 * Supprime une instance et tout ce qui en dépend.
 *
 * @param int $id
 * @return bool
 */
function syllabus_delete_instance($id) {
    global $DB;

    if (!$DB->record_exists('syllabus', ['id' => $id])) {
        return false;
    }
    $versionids = $DB->get_fieldset_select('syllabus_version', 'id', 'syllabusid = ?', [$id]);
    if ($versionids) {
        [$insql, $params] = $DB->get_in_or_equal($versionids);
        $DB->delete_records_select('syllabus_session', "versionid $insql", $params);
    }
    $responseids = $DB->get_fieldset_select('syllabus_response', 'id', 'syllabusid = ?', [$id]);
    if ($responseids) {
        [$insql, $params] = $DB->get_in_or_equal($responseids);
        $DB->delete_records_select('syllabus_answer', "responseid $insql", $params);
    }
    foreach (['syllabus_version', 'syllabus_teacher', 'syllabus_question', 'syllabus_response', 'syllabus_participant'] as $table) {
        $DB->delete_records($table, ['syllabusid' => $id]);
    }
    $DB->delete_records('syllabus', ['id' => $id]);
    return true;
}

/**
 * Navigation secondaire de l'activité : fiche, rédaction, responsables, évaluation, résultats.
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $node
 * @return void
 */
function syllabus_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $node) {
    global $CFG, $DB, $PAGE;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }
    require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
    $syllabus = $DB->get_record('syllabus', ['id' => $cm->instance]);
    if (!$syllabus) {
        return;
    }
    $rights = syllabus_get_rights($syllabus, $cm->context);
    if ($rights->canedit) {
        $node->add(
            get_string('editsyllabus', 'mod_syllabus'),
            new moodle_url('/mod/syllabus/edit.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'syllabusedit'
        );
    }
    if ($rights->canedit || $rights->canmanage) {
        $node->add(
            get_string('teachers', 'mod_syllabus'),
            new moodle_url('/mod/syllabus/teachers.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'syllabusteachers'
        );
        $node->add(
            get_string('evaluation', 'mod_syllabus'),
            new moodle_url('/mod/syllabus/evaluation.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'syllabusevaluation'
        );
    }
    if ($rights->canseeresults) {
        $node->add(
            get_string('results', 'mod_syllabus'),
            new moodle_url('/mod/syllabus/results.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'syllabusresults'
        );
    }
}

/**
 * Options de la réinitialisation du cours : les réponses à l'évaluation peuvent être effacées
 * (la fiche et les questions restent).
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function syllabus_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'syllabusheader', get_string('modulenameplural', 'mod_syllabus'));
    $mform->addElement('advcheckbox', 'reset_syllabus_responses', get_string('resetresponses', 'mod_syllabus'));
}

/**
 * Valeurs par défaut de la réinitialisation.
 *
 * @param stdClass $course
 * @return array
 */
function syllabus_reset_course_form_defaults($course) {
    return ['reset_syllabus_responses' => 1];
}

/**
 * Réinitialisation du cours : réponses et participations effacées, questions de nouveau
 * modifiables et évaluation de nouveau annonçable.
 *
 * @param stdClass $data
 * @return array
 */
function syllabus_reset_userdata($data) {
    global $DB;

    $status = [];
    if (!empty($data->reset_syllabus_responses)) {
        foreach ($DB->get_records('syllabus', ['course' => $data->courseid], '', 'id') as $syllabus) {
            $responseids = $DB->get_fieldset_select('syllabus_response', 'id', 'syllabusid = ?', [$syllabus->id]);
            if ($responseids) {
                [$insql, $params] = $DB->get_in_or_equal($responseids);
                $DB->delete_records_select('syllabus_answer', "responseid $insql", $params);
            }
            $DB->delete_records('syllabus_response', ['syllabusid' => $syllabus->id]);
            $DB->delete_records('syllabus_participant', ['syllabusid' => $syllabus->id]);
            // Les copies figées des questions communes sont retirées : la prochaine évaluation
            // reprendra les questions communes du moment.
            $DB->delete_records_select('syllabus_question', "syllabusid = ? AND origin <> 'teacher'", [$syllabus->id]);
            $DB->update_record('syllabus', (object) ['id' => $syllabus->id, 'evalfrozen' => 0, 'evalnotified' => 0]);
        }
        $status[] = ['component' => get_string('modulenameplural', 'mod_syllabus'),
            'item' => get_string('resetresponses', 'mod_syllabus'), 'error' => false];
    }
    return $status;
}
