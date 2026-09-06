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
 * Choix des activités « Enseignement personnalisé » qui doivent remonter dans cette synthèse :
 * cochée = l'enseignant y retrouve ses étudiants et ses EP ; décochée = l'activité (par exemple
 * une promotion sortie, ou pas encore concernée) n'apparaît pas, sans que rien ne soit modifié
 * dans l'activité d'origine.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:managelinks', $context);

$baseurl = new moodle_url('/mod/epsynthesis/administration.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('managelinks', 'mod_epsynthesis'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$available = epsynthesis_get_available_ep_activities($USER->id);

if (optional_param('save', 0, PARAM_INT) && confirm_sesskey()) {
    // On ne retient, parmi les cases cochées, que les cmid effectivement proposés à cet
    // utilisateur : une valeur ajoutée à la main dans la requête ne doit pas permettre de lier
    // une activité à laquelle il n'a pas accès (mod/ep:manage), même si elle existe ailleurs sur
    // la plateforme. Les liens déjà en place vers des activités hors de sa portée (ajoutés par un
    // autre gestionnaire) sont conservés tels quels plutôt que silencieusement supprimés.
    $selected = array_intersect(optional_param_array('epcmid', [], PARAM_INT), array_keys($available));
    $outofscope = array_diff(array_keys(epsynthesis_get_links($epsynthesis->id)), array_keys($available));
    epsynthesis_set_links($epsynthesis->id, array_merge($selected, $outofscope));
    redirect($baseurl, get_string('linkssaved', 'mod_epsynthesis'), null, \core\output\notification::NOTIFY_SUCCESS);
}
$linked = epsynthesis_get_links($epsynthesis->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($epsynthesis->name));
echo epsynthesis_render_navlinks($epsynthesis, $cm, $context);
echo $OUTPUT->heading(get_string('managelinks', 'mod_epsynthesis'), 3);
echo html_writer::tag('p', get_string('managelinks_help', 'mod_epsynthesis'));

if (empty($available)) {
    echo $OUTPUT->notification(get_string('noactivities', 'mod_epsynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

$table = new html_table();
$table->head = [get_string('linked', 'mod_epsynthesis'), get_string('course'), get_string('activity', 'moodle')];

foreach ($available as $activity) {
    $checked = isset($linked[(int) $activity->epcmid]);
    $checkbox = html_writer::checkbox('epcmid[]', $activity->epcmid, $checked, '',
        ['id' => 'epcmid_' . $activity->epcmid]);

    $coursename = format_string($activity->coursename);
    if (!$activity->coursevisible) {
        $coursename .= ' ' . html_writer::span(get_string('hiddencourse', 'mod_epsynthesis'), 'badge badge-secondary');
    }

    $activityname = format_string($activity->epname);
    if (!$activity->visible) {
        $activityname .= ' ' . html_writer::span(get_string('hiddenactivity', 'mod_epsynthesis'),
            'badge badge-secondary');
    }

    $table->data[] = [$checkbox, $coursename, $activityname];
}

echo html_writer::table($table);
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
