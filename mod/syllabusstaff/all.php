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
 * Tous les modules du périmètre (DEVE) : filtres, fenêtre d'évaluation en lot.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$filters = [
    'search' => trim(optional_param('search', '', PARAM_TEXT)),
    'typeid' => optional_param('typeid', 0, PARAM_INT),
    'status' => optional_param('status', '', PARAM_ALPHA),
    'evalstate' => optional_param('evalstate', '', PARAM_ALPHA),
];
$page = optional_param('page', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/all.php',
    array_filter($filters) + ['page' => $page]
);
require_capability('mod/syllabusstaff:manageall', $context);

// Fenêtre d'évaluation en lot.
if (optional_param('setwindow', 0, PARAM_BOOL) && confirm_sesskey()) {
    $ids = optional_param_array('syllabusids', [], PARAM_INT);
    $open = optional_param('evalopen', '', PARAM_RAW);
    $close = optional_param('evalclose', '', PARAM_RAW);
    $open = syllabusstaff_parse_datetime($open);
    $close = syllabusstaff_parse_datetime($close);
    if ($close && (!$open || $close <= $open)) {
        redirect($PAGE->url, get_string('errorcloseafteropen', 'mod_syllabus'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $result = syllabusstaff_set_windows($ids, $open, $close);
    redirect(
        $PAGE->url,
        get_string('windowsset', 'mod_syllabusstaff', $result),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

[$rows, $total] = syllabus_list_instances(
    ['categoryid' => $instance->categoryid] + array_filter($filters),
    $page * SYLLABUSSTAFF_PERPAGE,
    SYLLABUSSTAFF_PERPAGE
);

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'all', $canmanageall);
echo $OUTPUT->heading(get_string('allmodules', 'mod_syllabusstaff') . ' (' . $total . ')', 3);
echo syllabusstaff_render_filters($PAGE->url, $filters);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'setwindow', 'value' => 1]);
echo syllabusstaff_render_modules($rows, true);
echo $OUTPUT->paging_bar($total, $page, SYLLABUSSTAFF_PERPAGE, $PAGE->url);
if ($rows) {
    $fields = html_writer::tag('label', get_string('evalopen', 'mod_syllabus'), ['for' => 'bulkopen', 'class' => 'mr-2'])
        . html_writer::empty_tag('input', ['type' => 'datetime-local', 'name' => 'evalopen', 'id' => 'bulkopen',
            'class' => 'form-control mr-3'])
        . html_writer::tag('label', get_string('evalclose', 'mod_syllabus'), ['for' => 'bulkclose', 'class' => 'mr-2'])
        . html_writer::empty_tag('input', ['type' => 'datetime-local', 'name' => 'evalclose', 'id' => 'bulkclose',
            'class' => 'form-control mr-3'])
        . html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('setwindow', 'mod_syllabusstaff'),
            'class' => 'btn btn-primary']);
    echo $OUTPUT->box(html_writer::tag('h5', get_string('bulkwindow', 'mod_syllabusstaff'))
        . html_writer::tag('p', get_string('bulkwindow_help', 'mod_syllabusstaff'), ['class' => 'small text-muted'])
        . html_writer::div($fields, 'form-inline'), 'generalbox');
}
echo html_writer::end_tag('form');
$PAGE->requires->js_amd_inline("document.getElementById('syllabusstaff-selectall')?.addEventListener('change', e => "
    . "document.querySelectorAll('.syllabusstaff-select').forEach(c => { c.checked = e.target.checked; }));");
echo $OUTPUT->footer();
