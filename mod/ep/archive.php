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
 * Export complet de l'activité (archive ZIP : classeur, paramétrage, données des étudiants,
 * fichiers) et restauration d'une telle archive, en deux temps : le dépôt présente le contenu de
 * l'archive, la restauration se fait sur confirmation.
 *
 * L'export contient les dossiers de tous les étudiants : il demande la gestion de l'activité et
 * la consultation de tous les dossiers. La restauration porte des décisions aux dossiers : elle
 * demande la gestion de l'activité et la validation DEVE.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\import_file_form;
use mod_ep\local\global_archive;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);
$canexport = has_capability('mod/ep:viewall', $context);
$canrestore = has_capability('mod/ep:validatedeve', $context);
if (!$canexport && !$canrestore) {
    throw new required_capability_exception($context, 'mod/ep:viewall', 'nopermissions', '');
}

$title = get_string('archive', 'mod_ep');
$pageurl = new moodle_url('/mod/ep/archive.php', ['id' => $cm->id]);
$backurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'export' && $canexport && confirm_sesskey()) {
    core_php_time_limit::raise(600);
    raise_memory_limit(MEMORY_HUGE);
    $zippath = global_archive::export($ep, $context);
    $filename = clean_filename('export-' . $course->shortname . '-' . format_string($ep->name) . '-'
        . userdate(time(), '%Y%m%d-%H%M', 99, false, false) . '.zip');
    send_temp_file($zippath, $filename);
}

$settingsparts = [];
foreach (['instance', 'types', 'yearrequirements', 'supplement', 'notifications', 'catalog'] as $part) {
    $settingsparts[$part] = get_string('settingspart_' . $part, 'mod_ep');
}
$parts = $settingsparts + ['data' => get_string('archivepartdata', 'mod_ep')];
$previewpath = make_temp_directory('mod_ep/import') . '/' . (int) $USER->id . '-' . (int) $ep->id . '-archive.zip';

if ($action === 'confirm' && $canrestore && data_submitted() && confirm_sesskey()) {
    $chosen = array_values(array_intersect(array_keys($parts), optional_param_array('parts', [], PARAM_ALPHA)));
    if (!is_readable($previewpath) || filemtime($previewpath) < time() - DAYSECS) {
        redirect($pageurl, get_string('importexpired', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (!$chosen) {
        redirect($pageurl, get_string('archivenoparts', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    core_php_time_limit::raise(600);
    raise_memory_limit(MEMORY_HUGE);
    try {
        $archive = global_archive::open($previewpath);
        $report = global_archive::restore($ep, $context, $archive, $chosen, $USER->id);
    } finally {
        unlink($previewpath);
    }
    foreach (array_merge($report->settings->warnings ?? [], $report->warnings) as $warning) {
        \core\notification::add($warning, \core\output\notification::NOTIFY_WARNING);
    }
    redirect($backurl, get_string('archiverestoredone', 'mod_ep', $report), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform = null;
if ($canrestore) {
    $mform = new import_file_form($pageurl, [
        'filelabel' => get_string('archivefile', 'mod_ep'),
        'types' => ['.zip'],
        'help' => ['archiverestore', 'mod_ep'],
        'parts' => $parts,
        'partslabel' => get_string('archiveparts', 'mod_ep'),
    ]);
    $mform->set_data(['id' => $cm->id]);
    if ($mform->is_cancelled()) {
        redirect($backurl);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::link($backurl, get_string('back'));

$data = $mform ? $mform->get_data() : null;
if ($data) {
    $chosen = array_keys(array_filter($data->parts ?? []));
    $mform->save_file('importfile', $previewpath, true);
    try {
        $archive = global_archive::open($previewpath);
    } catch (moodle_exception $e) {
        unlink($previewpath);
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $mform->display();
        echo $OUTPUT->footer();
        exit;
    }
    echo $OUTPUT->heading(get_string('archivecontents', 'mod_ep'), 4);
    echo html_writer::tag('p', get_string('settingssourcename', 'mod_ep', (object) [
        'source' => s($archive->settings['source'] ?? ''),
        'date' => userdate((int) ($archive->data['exported'] ?? 0)),
    ]));
    $labels = ['types' => get_string('settingspart_types', 'mod_ep'), 'activities' => get_string('catalog', 'mod_ep')];
    foreach (['credits', 'items', 'years', 'issues'] as $key) {
        $labels[$key] = get_string('archivecount_' . $key, 'mod_ep');
    }
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    foreach ($archive->counts as $key => $count) {
        $table->data[] = [$labels[$key], $count];
    }
    echo html_writer::table($table);
    echo $OUTPUT->heading(get_string('archiveparts', 'mod_ep'), 5);
    echo html_writer::alist(array_map(fn($part) => $parts[$part], $chosen));

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
    foreach ($chosen as $part) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'parts[]', 'value' => $part]);
    }
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('archiverestore', 'mod_ep'), 'class' => 'btn btn-primary mr-2',
    ]);
    echo html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

if ($canexport) {
    echo $OUTPUT->heading(get_string('archiveexport', 'mod_ep'), 4);
    echo html_writer::tag('p', get_string('archiveexport_help', 'mod_ep'));
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'export', 'sesskey' => sesskey()]),
        get_string('archiveexport', 'mod_ep'),
        'post',
        ['type' => single_button::BUTTON_PRIMARY]
    );
}
if ($mform) {
    echo $OUTPUT->heading(get_string('archiverestore', 'mod_ep'), 4);
    $mform->display();
}

echo $OUTPUT->footer();
