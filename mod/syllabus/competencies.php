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
 * Compétences visées par le module : liens au référentiel de compétences tenu par la DEVE dans le
 * suivi des modules, avec le niveau auquel le module les porte.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init(
    $cm,
    '/mod/syllabus/competencies.php',
    $frameworkid ? ['frameworkid' => $frameworkid] : []
);

$canlink = syllabus_can_link_competencies($rights);
$frameworks = syllabus_get_frameworks();
if ($frameworkid && !isset($frameworks[$frameworkid])) {
    $frameworkid = 0;
}
if (!$frameworkid && count($frameworks) === 1) {
    $frameworkid = (int) array_key_first($frameworks);
}

if ($canlink && $frameworkid && optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    $result = syllabus_set_links(
        (int) $course->id,
        (int) $syllabus->id,
        $frameworkid,
        optional_param_array('levels', [], PARAM_INT)
    );
    redirect(new moodle_url($PAGE->url, ['frameworkid' => $frameworkid]), get_string(
        'competenciessaved',
        'mod_syllabus',
        $result
    ), null, \core\output\notification::NOTIFY_SUCCESS);
}

$linked = syllabus_get_module_competencies($syllabus);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('competencies', 'mod_syllabus'));
echo html_writer::tag('p', get_string('competencies_help', 'mod_syllabus'));
// Qui peut lier voit les niveaux dans le formulaire : le tableau ne se répète pas au-dessus.
if (!$canlink || !$frameworkid) {
    echo $OUTPUT->heading(get_string('targetedcompetencies', 'mod_syllabus', count($linked)), 4);
    echo syllabus_render_competencies($linked);
}

if ($canlink) {
    if (!$frameworks) {
        echo $OUTPUT->notification(get_string('noframeworks', 'mod_syllabus'), 'info');
    } else {
        echo $OUTPUT->heading(get_string('linkcompetencies', 'mod_syllabus') . ' (' . get_string(
            'competenciesn',
            'mod_syllabus',
            count($linked)
        ) . ')', 4);
        $options = [0 => get_string('chooseframework', 'mod_syllabus')] + array_map(
            fn($f) => format_string($f->name),
            $frameworks
        );
        echo $OUTPUT->single_select(
            new moodle_url('/mod/syllabus/competencies.php', ['id' => $cm->id]),
            'frameworkid',
            $options,
            $frameworkid,
            null,
            null,
            ['label' => get_string('framework', 'mod_syllabus')]
        );
        if ($frameworkid) {
            echo syllabus_render_link_form(new moodle_url($PAGE->url, ['frameworkid' => $frameworkid]), $frameworkid, $linked);
        }
    }
}
echo $OUTPUT->footer();
