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
 * Transfert du paramétrage d'une activité : export dans un fichier JSON, import d'un tel fichier,
 * copie depuis une autre activité gérée par l'utilisateur, et journal des imports. Aucune donnée
 * d'étudiant ne passe par cette page (voir archive.php pour l'export complet).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\form\import_file_form;
use mod_ep\form\settings_copy_form;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$title = get_string('settingstransfer', 'mod_ep');
$pageurl = new moodle_url('/mod/ep/settings_transfer.php', ['id' => $cm->id]);
$backurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . $title);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'export' && confirm_sesskey()) {
    $settings = ep_export_settings($ep, optional_param('withcatalog', 0, PARAM_BOOL));
    $filename = clean_filename('parametrage-' . $course->shortname . '-' . userdate(time(), '%Y%m%d', 99, false, false) . '.json');
    send_file(
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        $filename,
        0,
        0,
        true,
        true,
        'application/json'
    );
}

$parts = [];
foreach (['instance', 'types', 'yearrequirements', 'supplement', 'notifications', 'catalog'] as $part) {
    $parts[$part] = get_string('settingspart_' . $part, 'mod_ep');
}

/**
 * Message de compte rendu d'un import du paramétrage.
 *
 * @param stdClass $report Voir ep_import_settings().
 * @param array $labels partie => libellé
 * @return string
 */
function ep_settings_transfer_summary(stdClass $report, array $labels): string {
    $describe = function (array $counts) use ($labels) {
        $list = [];
        foreach ($counts as $kind => $count) {
            $list[] = ($labels[$kind] ?? $kind) . ' (' . $count . ')';
        }
        return $list ? implode(', ', $list) : get_string('settingsnone', 'mod_ep');
    };
    return get_string('settingsimportdone', 'mod_ep', (object) [
        'created' => $describe($report->created),
        'updated' => $describe($report->updated),
    ]);
}
$labels = [
    'instance' => get_string('settingspart_instance', 'mod_ep'),
    'types' => get_string('settingspart_types', 'mod_ep'),
    'yearrequirements' => get_string('settingspart_yearrequirements', 'mod_ep'),
    'supplementcategories' => get_string('supplementcategories', 'mod_ep'),
    'supplementtexts' => get_string('settingspart_supplement', 'mod_ep'),
    'notifications' => get_string('settingspart_notifications', 'mod_ep'),
    'activities' => get_string('catalog', 'mod_ep'),
    'syllabi' => get_string('syllabus', 'mod_ep'),
];
$apply = function (array $settings, array $chosen) use ($ep, $labels, $pageurl, $USER) {
    $report = ep_import_settings($ep, $settings, $chosen, $USER->id);
    ep_log_import($ep, 'settings', $USER->id, array_sum($report->created) + array_sum($report->updated), $report);
    foreach ($report->warnings as $warning) {
        \core\notification::add($warning, \core\output\notification::NOTIFY_WARNING);
    }
    redirect($pageurl, ep_settings_transfer_summary($report, $labels), null, \core\output\notification::NOTIFY_SUCCESS);
};

$importform = new import_file_form($pageurl, [
    'filelabel' => get_string('settingsfile', 'mod_ep'),
    'types' => ['.json'],
    'hidden' => ['action' => PARAM_ALPHA],
    'parts' => $parts,
    'partslabel' => get_string('settingsparts', 'mod_ep'),
    'partshelp' => ['settingsparts', 'mod_ep'],
    'submit' => get_string('settingsimport', 'mod_ep'),
]);
$importform->set_data(['id' => $cm->id, 'action' => 'import']);
if ($importform->is_cancelled()) {
    redirect($pageurl);
}

$sources = ep_get_managed_instances((int) $cm->id);
$copyform = $sources ? new settings_copy_form($pageurl, ['sources' => $sources, 'parts' => $parts]) : null;
if ($copyform) {
    $copyform->set_data(['id' => $cm->id]);
}

if ($action === 'import' && ($data = $importform->get_data())) {
    $settings = json_decode((string) $importform->get_file_content('importfile'), true);
    if (!is_array($settings) || (int) ($settings['format'] ?? 0) !== EP_SETTINGS_FORMAT) {
        redirect($pageurl, get_string('errorsettingsformat', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $apply($settings, array_keys(array_filter($data->parts)));
}
if ($action === 'copy' && $copyform && ($data = $copyform->get_data())) {
    $sourcecm = get_coursemodule_from_id('ep', (int) $data->sourcecmid, 0, false, MUST_EXIST);
    // La liste des sources ne contient que des activités gérées ; la capacité est revérifiée.
    require_capability('mod/ep:manage', context_module::instance($sourcecm->id));
    $source = $DB->get_record('ep', ['id' => $sourcecm->instance], '*', MUST_EXIST);
    $apply(ep_export_settings($source, true), array_keys(array_filter($data->parts)));
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::link($backurl, get_string('back'));
echo html_writer::tag('p', get_string('settingstransfer_desc', 'mod_ep'));

echo $OUTPUT->heading(get_string('settingsexport', 'mod_ep'), 4);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'export']);
echo html_writer::div(
    html_writer::checkbox('withcatalog', 1, true, get_string('settingsexportwithcatalog', 'mod_ep')),
    'mb-2'
);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('settingsexport', 'mod_ep'),
    'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->heading(get_string('settingsimport', 'mod_ep'), 4);
$importform->display();

echo $OUTPUT->heading(get_string('settingscopyfrom', 'mod_ep'), 4);
if ($copyform) {
    $copyform->display();
} else {
    echo html_writer::tag('p', get_string('settingsnosource', 'mod_ep'), ['class' => 'text-muted']);
}

echo $OUTPUT->heading(get_string('importlog', 'mod_ep'), 4);
$log = ep_get_import_log($ep->id);
if (!$log) {
    echo html_writer::tag('p', get_string('importlogempty', 'mod_ep'), ['class' => 'text-muted']);
} else {
    $users = $DB->get_records_list(
        'user',
        'id',
        array_unique(array_column($log, 'userid')),
        '',
        'id, ' . implode(', ', \core_user\fields::get_name_fields())
    );
    $rows = [];
    foreach ($log as $entry) {
        $summary = [];
        foreach ((array) json_decode($entry->summary, true) as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map(fn($k, $v) => $k . ' ' . $v, array_keys($value), $value));
            }
            if ($value !== '' && $value !== 0 && $value !== null) {
                $summary[] = s($key . ' : ' . $value);
            }
        }
        $rows[] = [
            userdate($entry->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            get_string_manager()->string_exists('importlogkind_' . $entry->kind, 'mod_ep')
                ? get_string('importlogkind_' . $entry->kind, 'mod_ep') : s($entry->kind),
            isset($users[$entry->userid]) ? s(fullname($users[$entry->userid])) : '-',
            (int) $entry->itemcount,
            implode(html_writer::empty_tag('br'), $summary),
        ];
    }
    echo ep_render_paged_table([get_string('date'), get_string('importlogkind', 'mod_ep'), get_string('user'),
        get_string('importlogitems', 'mod_ep'), get_string('importlogsummary', 'mod_ep')], $rows);
}

echo $OUTPUT->footer();
