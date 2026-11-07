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
 * Compétences visées par un cours qui n'a pas de module « Syllabus du module » : liées par la
 * DEVE, avec un niveau. Un cours qui a un module renvoie à la page « Compétences » du module.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/courselink.php',
    ['courseid' => $courseid, 'frameworkid' => $frameworkid]
);
require_capability('mod/syllabusstaff:manageall', $context);
$target = get_course($courseid);
if (!syllabusstaff_course_in_scope($instance, $target)) {
    throw new moodle_exception('coursenotinscope', 'mod_syllabusstaff');
}
[$modules] = syllabus_list_instances(['courseids' => [$courseid]]);
if ($modules) {
    redirect(new moodle_url('/mod/syllabus/competencies.php', ['id' => reset($modules)->cmid, 'frameworkid' => $frameworkid]));
}
$frameworks = syllabus_get_frameworks();
if (!isset($frameworks[$frameworkid])) {
    $frameworkid = (int) array_key_first($frameworks);
}
$matrixurl = new moodle_url('/mod/syllabusstaff/competencies.php', ['id' => $cm->id, 'frameworkid' => $frameworkid]);

if ($frameworkid && optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    $result = syllabus_set_links($courseid, 0, $frameworkid, optional_param_array('levels', [], PARAM_INT));
    redirect(
        $matrixurl,
        get_string('competenciessaved', 'mod_syllabus', $result),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'competencies', $canmanageall);
echo html_writer::link($matrixurl, get_string('backtomatrix', 'mod_syllabusstaff'));
echo $OUTPUT->heading(get_string('courselink', 'mod_syllabusstaff', format_string($target->fullname)), 3);
echo html_writer::tag('p', get_string('courselink_help', 'mod_syllabusstaff'));
if (!$frameworks) {
    echo $OUTPUT->notification(get_string('noframeworks', 'mod_syllabus'), 'info');
} else {
    echo $OUTPUT->single_select(new moodle_url($PAGE->url), 'frameworkid', array_map(
        fn($f) => format_string($f->name),
        $frameworks
    ), $frameworkid, null, null, ['label' => get_string('framework', 'mod_syllabus')]);
    echo syllabus_render_link_form($PAGE->url, $frameworkid, syllabus_get_links($courseid, 0));
}
echo $OUTPUT->footer();
