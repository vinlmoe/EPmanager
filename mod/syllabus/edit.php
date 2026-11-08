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
 * Rédaction de la fiche : version de travail, créée au besoin d'après la version publiée.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init($cm, '/mod/syllabus/edit.php');
if (!$rights->canedit) {
    throw new required_capability_exception($context, 'mod/syllabus:edit', 'nopermissions', '');
}
$viewurl = new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]);

$working = syllabus_get_working((int) $syllabus->id);
if ($working && $working->status === SYLLABUS_STATUS_SUBMITTED) {
    redirect($viewurl, get_string('errornotdraft', 'mod_syllabus'), null, \core\output\notification::NOTIFY_WARNING);
}
$source = $working ?: syllabus_get_published((int) $syllabus->id);
$sessions = $source ? array_values(syllabus_get_sessions((int) $source->id)) : [];

// Intervenants proposés : les enseignants du cours (pas la DEVE), et ceux déjà nommés dans les séances.
$teachers = [];
foreach (syllabus_potential_teachers($context) as $user) {
    $teachers[$user->id] = fullname($user);
}
$named = array_diff(array_filter(array_map(fn($s) => (int) $s->teacherid, $sessions)), array_keys($teachers));
foreach ($named ? $DB->get_records_list('user', 'id', $named) : [] as $user) {
    $teachers[$user->id] = fullname($user);
}
$canpublish = $rights->canmanage || !syllabus_setting('reviewrequired');
$mform = new \mod_syllabus\form\fiche_form($PAGE->url, ['teachers' => $teachers,
    'sessioncount' => max(3, count($sessions) + 1), 'canpublish' => $canpublish]);

$formdata = ['id' => $cm->id, 'language' => get_string('defaultlanguage', 'mod_syllabus'), 'personalhours' => 0];
if ($source) {
    foreach (array_keys(syllabus_text_fields()) as $field) {
        $formdata[$field] = $source->$field;
    }
    $formdata['language'] = $source->language;
    $formdata['personalhours'] = (float) $source->personalhours;
}
foreach ($sessions as $index => $session) {
    $formdata['sessionrow'][$index] = ['sessiontitle' => $session->title, 'sessionkind' => $session->kind,
        'sessionduration' => (float) $session->duration, 'sessionteacher' => (int) $session->teacherid,
        'sessionteachername' => $session->teachername];
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($viewurl);
} else if ($data = $mform->get_data()) {
    $rows = [];
    foreach ($data->sessionrow ?? [] as $row) {
        $rows[] = ['title' => $row['sessiontitle'] ?? '', 'kind' => $row['sessionkind'] ?? 'other',
            'duration' => (float) ($row['sessionduration'] ?? 0), 'teacherid' => (int) ($row['sessionteacher'] ?? 0),
            'teachername' => $row['sessionteachername'] ?? ''];
    }
    $version = syllabus_start_working($syllabus, (int) $USER->id);
    syllabus_save_working($version, $data, $rows, (int) $USER->id);
    if (!empty($data->submitforreview)) {
        $version = $DB->get_record('syllabus_version', ['id' => $version->id], '*', MUST_EXIST);
        // La DEVE publie directement, sauf une fiche qui doit être validée en commission : elle
        // la soumet, puis la valide sur la fiche en indiquant la date de la commission.
        if ($rights->canmanage && !syllabus_commission_required($syllabus)) {
            syllabus_publish($syllabus, $version, (int) $USER->id, '');
            $result = 'published';
        } else {
            $result = syllabus_submit($syllabus, $cm, $version, (int) $USER->id);
        }
        redirect($viewurl, get_string('fiche' . $result, 'mod_syllabus'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    // Brouillon enregistré : on reste sur la rédaction.
    redirect($PAGE->url, get_string('fichesaved', 'mod_syllabus'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editsyllabus', 'mod_syllabus'));
if ($working && $working->status === SYLLABUS_STATUS_RETURNED && trim((string) $working->reviewcomment) !== '') {
    echo $OUTPUT->notification(get_string('reviewcommentis', 'mod_syllabus', s($working->reviewcomment)), 'warning');
}
echo $OUTPUT->notification(get_string($canpublish ? 'editnotice_publish' : 'editnotice_review', 'mod_syllabus'), 'info');
if ($working) {
    echo html_writer::div(html_writer::link(new moodle_url($viewurl, ['show' => 'working']), get_string(
        'showworking',
        'mod_syllabus'
    ), ['class' => 'btn btn-sm btn-outline-secondary']), 'mb-3');
}
$mform->display();
echo $OUTPUT->footer();
