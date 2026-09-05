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
 * Minimums d'ECTS d'enseignement personnalisé à valider : par année d'étude d'une part, sur
 * l'ensemble du cursus d'autre part. Les deux se cumulent — une année peut être atteinte sans que
 * le cursus le soit, et réciproquement.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$baseurl = new moodle_url('/mod/ep/year_requirements.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('manageyearrequirements', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if (optional_param('save', 0, PARAM_INT) && confirm_sesskey()) {
    foreach (array_keys(ep_studyyear_options()) as $year) {
        if ($year === 0) {
            // L'année « non précisée » n'est pas une année d'étude : elle sert de rattachement
            // par défaut, aucun minimum ne peut y être exigé.
            continue;
        }
        ep_set_year_requirement($ep->id, $year, optional_param('requiredects_' . $year, 0, PARAM_FLOAT));
    }

    $mincursus = max(0, round(optional_param('mincursusects', 0, PARAM_FLOAT), 2));
    $DB->set_field('ep', 'mincursusects', $mincursus, ['id' => $ep->id]);
    $DB->set_field('ep', 'timemodified', time(), ['id' => $ep->id]);

    redirect($baseurl, get_string('requirementssaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$requirements = ep_get_year_requirements($ep->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageyearrequirements', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));
echo $OUTPUT->notification(get_string('manageyearrequirements_help', 'mod_ep'), 'info');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

$table = new html_table();
$table->head = [get_string('studyyear', 'mod_ep'), get_string('requiredects', 'mod_ep')];
foreach (ep_studyyear_options() as $year => $label) {
    if ($year === 0) {
        continue;
    }
    $table->data[] = [
        $label,
        html_writer::empty_tag('input', [
            'type' => 'number', 'step' => '0.25', 'min' => 0, 'name' => 'requiredects_' . $year,
            'value' => ep_format_ects_input($requirements[$year] ?? 0), 'class' => 'form-control',
        ]),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->heading(get_string('cursusminimum', 'mod_ep'), 4);
echo html_writer::tag('p', get_string('cursusminimum_help', 'mod_ep'), ['class' => 'text-muted']);
echo html_writer::tag('label', get_string('mincursusects', 'mod_ep'), ['for' => 'mincursusects']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'step' => '0.25', 'min' => 0, 'name' => 'mincursusects', 'id' => 'mincursusects',
    'value' => ep_format_ects_input($ep->mincursusects), 'class' => 'form-control',
]);

echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-3',
]);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
