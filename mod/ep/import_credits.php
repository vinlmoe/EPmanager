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
 * Import de crédits depuis un fichier CSV (EP suivis ailleurs, historique d'un outil précédent),
 * en deux temps : prévisualisation, puis application sur confirmation. Le fichier déposé est
 * conservé dans un fichier temporaire propre à l'utilisateur et à l'activité, et relu à la
 * confirmation : les doublons sont donc recalculés sur l'état des dossiers à ce moment-là.
 *
 * Porter un crédit déjà tranché au dossier d'un étudiant est une décision de la DEVE : la page
 * demande la gestion de l'activité et la validation DEVE.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\import_file_form;
use mod_ep\local\credits_importer;
use mod_ep\local\csv_reader;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);
require_capability('mod/ep:validatedeve', $context);

$title = get_string('importcredits', 'mod_ep');
$pageurl = new moodle_url('/mod/ep/import_credits.php', ['id' => $cm->id]);
$backurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$columns = credits_importer::columns();
if (optional_param('template', 0, PARAM_BOOL)) {
    csv_reader::send_template($columns, 'credits_ep.csv');
}

$previewpath = make_temp_directory('mod_ep/import') . '/' . (int) $USER->id . '-' . (int) $ep->id . '-credits.csv';

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
    $read = credits_importer::read($ep, $context, $content);
    $result = credits_importer::apply($ep, $read->rows, $USER->id);
    redirect(
        new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]),
        get_string('importcreditsdone', 'mod_ep', $result),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$mform = new import_file_form($pageurl, ['filelabel' => get_string('importfile', 'mod_ep')]);
$mform->set_data(['id' => $cm->id]);
if ($mform->is_cancelled()) {
    redirect($backurl);
}

ep_set_secondary_page_header();
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
$read = credits_importer::read($ep, $context, $content);

echo $OUTPUT->heading(get_string('importpreview', 'mod_ep'), 4);
// Les messages échappent déjà les valeurs lues dans le fichier.
if ($read->errors) {
    echo $OUTPUT->notification(html_writer::alist($read->errors), 'error');
}
if ($read->warnings) {
    echo $OUTPUT->notification(html_writer::alist($read->warnings), 'warning');
}
if (!$read->rows) {
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
$userids = array_unique(array_map(fn($row) => $row->userid, $read->rows));
$users = $DB->get_records_list('user', 'id', $userids, '', 'id, ' . implode(', ', \core_user\fields::get_name_fields()));
$head = [get_string('line', 'debug'), get_string('student', 'mod_ep'), get_string('type', 'mod_ep'),
    get_string('creditname', 'mod_ep'), get_string('studyyear', 'mod_ep'), get_string('retainedects', 'mod_ep'),
    get_string('grade', 'mod_ep'), get_string('status', 'mod_ep')];
$rows = [];
foreach ($read->rows as $row) {
    $rows[] = [
        $row->line,
        isset($users[$row->userid]) ? s(fullname($users[$row->userid])) : '-',
        s($types[$row->typeid]->name ?? ''),
        s($row->name),
        $row->studyyear,
        ep_format_ects($row->retainedects),
        $row->grade === null ? '-' : ep_format_ects($row->grade),
        ep_render_credit_status($row->status),
    ];
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
