<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Import du catalogue des EP (kind=catalog) ou des séances de leurs syllabus (kind=sessions)
 * depuis un fichier CSV, en deux temps : prévisualisation, puis application sur confirmation.
 *
 * Le fichier déposé est conservé tel quel dans un fichier temporaire propre à l'utilisateur, à
 * l'activité et au type d'import, et relu à la confirmation : ce qui est appliqué est donc
 * recalculé sur l'état du catalogue à ce moment-là, sans faire confiance à une prévisualisation
 * qui aurait pu vieillir.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\import_file_form;
use mod_ep\local\catalog_importer;
use mod_ep\local\csv_reader;

$id = required_param('id', PARAM_INT);
$kind = optional_param('kind', 'catalog', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
if (!in_array($kind, ['catalog', 'sessions'], true)) {
    $kind = 'catalog';
}

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$title = get_string($kind === 'catalog' ? 'importcatalog' : 'importsessions', 'mod_ep');
$pageurl = new moodle_url('/mod/ep/import_catalog.php', ['id' => $cm->id, 'kind' => $kind]);
$backurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$columns = $kind === 'catalog' ? catalog_importer::columns() : catalog_importer::session_columns();
if (optional_param('template', 0, PARAM_BOOL)) {
    csv_reader::send_template($columns, $kind === 'catalog' ? 'catalogue_ep.csv' : 'seances_ep.csv');
}

$previewpath = make_temp_directory('mod_ep/import') . '/' . (int) $USER->id . '-' . (int) $ep->id . '-' . $kind . '.csv';

if ($action === 'confirm' && data_submitted() && confirm_sesskey()) {
    $content = null;
    if (is_readable($previewpath) && filemtime($previewpath) >= time() - DAYSECS) {
        $content = (string) file_get_contents($previewpath);
    }
    if (file_exists($previewpath)) {
        unlink($previewpath);
    }
    if ($content === null) {
        redirect($pageurl, get_string('importexpired', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $nexturl = new moodle_url('/mod/ep/activities.php', ['id' => $cm->id]);
    if ($kind === 'catalog') {
        $read = catalog_importer::read($ep, $content);
        $result = catalog_importer::apply($ep, $read->rows, $USER->id);
        foreach ($result->warnings as $warning) {
            \core\notification::add($warning, \core\output\notification::NOTIFY_WARNING);
        }
        redirect($nexturl, get_string('importcatalogdone', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $read = catalog_importer::read_sessions($ep, $content);
    $count = catalog_importer::apply_sessions($ep, $read->byactivity, $USER->id);
    redirect($nexturl, get_string('importsessionsdone', 'mod_ep', $count), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = new import_file_form($pageurl, [
    'filelabel' => get_string('importfile', 'mod_ep'),
    'hidden' => ['kind' => PARAM_ALPHA],
]);
$mform->set_data(['id' => $cm->id, 'kind' => $kind]);
if ($mform->is_cancelled()) {
    redirect($backurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::link($backurl, get_string('back'));

$showform = function () use ($mform, $columns, $pageurl, $OUTPUT) {
    $mform->display();
    echo $OUTPUT->heading(get_string('importcolumns', 'mod_ep'), 4);
    echo html_writer::tag('p', get_string('importcsvintro', 'mod_ep'));
    echo html_writer::tag('p', html_writer::link(
        new moodle_url($pageurl, ['template' => 1]),
        get_string('importtemplate', 'mod_ep'),
        ['class' => 'btn btn-secondary']
    ));
    echo csv_reader::columns_table($columns);
};

if (!$mform->get_data()) {
    $showform();
    echo $OUTPUT->footer();
    exit;
}

$content = (string) $mform->get_file_content('importfile');
$read = $kind === 'catalog' ? catalog_importer::read($ep, $content) : catalog_importer::read_sessions($ep, $content);
$usable = $kind === 'catalog' ? !empty($read->rows) : !empty($read->byactivity);

echo $OUTPUT->heading(get_string('importpreview', 'mod_ep'), 4);
// Les messages échappent déjà les valeurs lues dans le fichier.
if ($read->errors) {
    echo $OUTPUT->notification(html_writer::alist($read->errors), 'error');
}
if ($read->warnings) {
    echo $OUTPUT->notification(html_writer::alist($read->warnings), 'warning');
}
if (!$usable) {
    // Une prévisualisation antérieure ne doit pas pouvoir être confirmée depuis un autre onglet.
    if (file_exists($previewpath)) {
        unlink($previewpath);
    }
    echo $OUTPUT->notification(get_string('importnone', 'mod_ep'), 'warning');
    $showform();
    echo $OUTPUT->footer();
    exit;
}

$tmppath = $previewpath . '.' . random_string(8);
file_put_contents($tmppath, $content);
rename($tmppath, $previewpath);

$types = ep_get_types($ep->id);
$activities = ep_get_activities($ep->id);
if ($kind === 'catalog') {
    if ($read->syllabus) {
        echo $OUTPUT->notification(get_string('importsyllabusnotice', 'mod_ep'), 'info');
    }
    $states = ep_activity_state_options();
    $head = [get_string('line', 'debug'), get_string('activitycode', 'mod_ep'), get_string('catalogactivity', 'mod_ep'),
        get_string('type', 'mod_ep'), 'ECTS', get_string('activitystate', 'mod_ep'), get_string('decision', 'mod_ep')];
    $rows = [];
    foreach ($read->rows as $row) {
        $data = $row->data;
        $rows[] = [
            $row->line,
            s($data->code ?? ''),
            s($data->name),
            s($types[$data->typeid]->name ?? ''),
            ep_format_ects($data->ects),
            isset($data->state) ? s($states[$data->state] ?? $data->state) : '-',
            get_string($row->existingid ? 'importwillupdate' : 'importwillcreate', 'mod_ep'),
        ];
    }
} else {
    echo $OUTPUT->notification(get_string('importsessionsnotice', 'mod_ep'), 'info');
    $head = [get_string('activitycode', 'mod_ep'), get_string('catalogactivity', 'mod_ep'),
        get_string('sessions', 'mod_ep'), get_string('sessionduration', 'mod_ep')];
    $rows = [];
    foreach ($read->byactivity as $activityid => $sessions) {
        $activity = $activities[$activityid];
        $rows[] = [s($activity->code), s($activity->name), count($sessions),
            ep_format_ects(array_sum(array_column($sessions, 'duration')))];
    }
}
echo ep_render_paged_table($head, $rows);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('importconfirm', 'mod_ep'), 'class' => 'btn btn-primary mr-2',
]);
echo html_writer::link($backurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
