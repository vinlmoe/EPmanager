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
 * Fiches soumises qui attendent la DEVE.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init($cm, '/mod/syllabusstaff/review.php');
require_capability('mod/syllabusstaff:manageall', $context);

[$rows] = syllabus_list_instances(['categoryid' => $instance->categoryid, 'status' => SYLLABUS_STATUS_SUBMITTED]);
$overview = syllabus_get_overview(array_keys($rows));
$teachers = syllabus_get_teachers_bulk(array_map('intval', array_keys($rows)));

// Livret PDF de toutes les fiches soumises, à remettre à la commission de validation.
if ($rows && optional_param('pdf', 0, PARAM_BOOL)) {
    syllabus_send_pending_booklet(
        array_map('intval', array_keys($rows)),
        get_string('pendingbooklettitle', 'mod_syllabus'),
        [format_string($instance->name), userdate(time(), get_string('strftimedate', 'langconfig'))],
        'fiches-a-valider.pdf'
    );
    exit;
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'review', $canmanageall);
echo $OUTPUT->heading(get_string('toreview', 'mod_syllabusstaff') . ' (' . count($rows) . ')', 3);
echo html_writer::tag('p', syllabus_setting('reviewrequired') ? get_string('toreview_help', 'mod_syllabusstaff')
    : get_string('reviewdisabled', 'mod_syllabusstaff'));
if (!$rows) {
    echo $OUTPUT->notification(get_string('nothingtoreview', 'mod_syllabusstaff'), 'info');
} else {
    echo html_writer::link(
        new moodle_url($PAGE->url, ['pdf' => 1]),
        get_string('pendingbooklet', 'mod_syllabus', count($rows)),
        ['class' => 'btn btn-secondary mb-2']
    );
    $table = new html_table();
    $table->head = [get_string('module', 'mod_syllabus'), get_string('teachers', 'mod_syllabus'),
        get_string('version'), get_string('submittedon', 'mod_syllabusstaff'), get_string('actions')];
    // Après sa décision, la DEVE revient ici.
    $returnto = $PAGE->url->out_as_local_url(false);
    foreach ($rows as $row) {
        $info = $overview[(int) $row->id];
        $working = $info->working;
        $version = $info->published ? get_string('replacesversion', 'mod_syllabusstaff', $info->published->version)
            : get_string('firstversion', 'mod_syllabusstaff');
        $action = has_capability('mod/syllabus:manage', context_module::instance($row->cmid))
            ? html_writer::link(
                new moodle_url(
                    '/mod/syllabus/view.php',
                    ['id' => $row->cmid, 'show' => 'working', 'returnto' => $returnto],
                    'review'
                ),
                get_string('review', 'mod_syllabusstaff'),
                ['class' => 'btn btn-sm btn-primary']
            ) : html_writer::span(get_string('nomanageright', 'mod_syllabusstaff'), 'small text-muted');
        $table->data[] = [
            format_string($row->name) . ($row->code !== '' ? html_writer::div(s($row->code), 'small text-muted') : ''),
            s(implode(', ', array_map('fullname', $teachers[(int) $row->id]))) ?: '-',
            $version,
            $working ? userdate($working->timesubmitted, get_string('strftimedatetimeshort', 'langconfig')) : '-',
            $action,
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
