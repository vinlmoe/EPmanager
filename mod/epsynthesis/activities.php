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
 * Définition des EP académiques partagés : ceux auxquels des étudiants de plusieurs promotions
 * s'inscrivent. Ils sont définis ici, une seule fois, plutôt que recopiés dans le catalogue de
 * chaque promotion — deux copies d'un même EP auraient chacune leurs places et leurs inscrits,
 * alors que ce sont les mêmes.
 *
 * Chaque EP partagé apparaît ensuite au catalogue de toutes les activités « Enseignement
 * personnalisé » que cette synthèse suit (voir administration.php). Les ECTS qu'il porte comptent
 * pour chaque étudiant sous le type académique interne de sa propre promotion, avec les plafonds
 * de celle-ci.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');
require_once($CFG->dirroot . '/mod/epsynthesis/classes/form/shared_activity_form.php');

use mod_epsynthesis\form\shared_activity_form;

$id = required_param('id', PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:manageactivities', $context);

$baseurl = new moodle_url('/mod/epsynthesis/activities.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('manageactivities', 'mod_epsynthesis'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Suppression : refusée dès qu'un étudiant s'y est inscrit, sinon les crédits déjà accordés
// perdraient l'EP auquel ils se rattachent. Fermer l'EP aux inscriptions est ce qu'il faut faire
// dans ce cas.
if ($action === 'delete' && $activityid) {
    require_sesskey();
    $activity = ep_get_shared_activity($cm->id, $activityid);
    if (!$activity) {
        throw new moodle_exception('errorunknownactivity', 'mod_ep', $baseurl->out(false));
    }
    if (!ep_delete_activity($activity->id)) {
        redirect($baseurl, get_string('erroractivityinuse', 'mod_ep'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    redirect($baseurl, get_string('activitydeleted', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bascule rapide ouvert / fermé aux inscriptions.
if ($action === 'togglevisible' && $activityid) {
    require_sesskey();
    $activity = ep_get_shared_activity($cm->id, $activityid);
    if (!$activity) {
        throw new moodle_exception('errorunknownactivity', 'mod_ep', $baseurl->out(false));
    }
    $activity->visible = $activity->visible ? 0 : 1;
    $activity->timemodified = time();
    $DB->update_record('ep_activity', $activity);
    redirect($baseurl);
}

if ($action === 'edit') {
    $formurl = new moodle_url($baseurl, ['action' => 'edit', 'activityid' => $activityid]);
    $activity = null;
    if ($activityid) {
        $activity = ep_get_shared_activity($cm->id, $activityid);
        if (!$activity) {
            throw new moodle_exception('errorunknownactivity', 'mod_ep', $baseurl->out(false));
        }
    }

    $mform = new shared_activity_form($formurl);
    $mform->set_data($activity ? [
        'id' => $cm->id,
        'activityid' => $activity->id,
        'name' => $activity->name,
        'description' => $activity->description,
        'ects' => (float) $activity->ects,
        'minstudyyear' => $activity->minstudyyear,
        'maxstudyyear' => $activity->maxstudyyear,
        'capacity' => $activity->capacity,
        'sortorder' => $activity->sortorder,
        'visible' => $activity->visible,
    ] : ['id' => $cm->id, 'activityid' => 0]);

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        // L'EP visé est revérifié : un identifiant forgé ne doit pas permettre de réécrire un EP
        // défini dans une autre synthèse.
        if (!empty($data->activityid) && !ep_get_shared_activity($cm->id, $data->activityid)) {
            throw new moodle_exception('errorunknownactivity', 'mod_ep', $baseurl->out(false));
        }
        $savedid = ep_save_shared_activity($cm->id, $data);

        // Un EP sans responsable n'a personne pour accepter ses inscriptions ni valider ses
        // ECTS : l'écran d'affectation suit immédiatement la création, plutôt que d'attendre que
        // quelqu'un s'aperçoive que des demandes stagnent.
        redirect(new moodle_url('/mod/epsynthesis/activity_teachers.php',
            ['id' => $cm->id, 'activityid' => $savedid]),
            get_string('activitysaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('manageactivities', 'mod_epsynthesis'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageactivities', 'mod_epsynthesis'));
echo epsynthesis_render_navlinks($epsynthesis, $cm, $context);
echo html_writer::tag('p', get_string('manageactivities_help', 'mod_epsynthesis'), ['class' => 'text-muted']);
echo html_writer::link(new moodle_url($baseurl, ['action' => 'edit']),
    get_string('addsharedactivity', 'mod_epsynthesis'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']);

$activities = ep_get_shared_activities([(int) $cm->id]);

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('nosharedactivities', 'mod_epsynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('catalogactivity', 'mod_ep'),
    get_string('ects', 'mod_ep'),
    get_string('studyyearrange', 'mod_ep'),
    get_string('places', 'mod_ep'),
    get_string('registrations', 'mod_ep'),
    get_string('activityteachers', 'mod_ep'),
    get_string('openforregistration', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];

foreach ($activities as $activity) {
    $counts = ep_get_activity_registration_counts($activity->id);

    $togglevisibleurl = new moodle_url($baseurl,
        ['action' => 'togglevisible', 'activityid' => $activity->id, 'sesskey' => sesskey()]);
    $visible = html_writer::link($togglevisibleurl,
        $activity->visible ? get_string('yes') : get_string('no'),
        ['class' => $activity->visible ? 'badge badge-success' : 'badge badge-secondary']);

    $teachersurl = new moodle_url('/mod/epsynthesis/activity_teachers.php',
        ['id' => $cm->id, 'activityid' => $activity->id]);
    $teachercount = count(ep_get_activity_teachers($activity->id));
    $teacherscell = html_writer::link($teachersurl,
        get_string('activityteacherscount', 'mod_ep', $teachercount));
    if ($teachercount === 0) {
        // Sans responsable, les inscriptions restent en attente indéfiniment : le signaler ici
        // est le seul endroit où la DEVE le verra avant que des étudiants ne s'en plaignent.
        $teacherscell .= ' ' . html_writer::span(get_string('noteacherwarning', 'mod_ep'), 'badge badge-warning');
    }

    $deleteurl = new moodle_url($baseurl,
        ['action' => 'delete', 'activityid' => $activity->id, 'sesskey' => sesskey()]);
    $actions = ep_render_actions([
        get_string('edit') => new moodle_url($baseurl, ['action' => 'edit', 'activityid' => $activity->id]),
        get_string('activityteachers', 'mod_ep') => $teachersurl,
        get_string('registrations', 'mod_ep') => new moodle_url('/mod/epsynthesis/registrations.php',
            ['id' => $cm->id, 'activityid' => $activity->id]),
    ]) . html_writer::link($deleteurl, get_string('delete'), [
        'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
        'onclick' => "return confirm('" . get_string('confirmdeleteactivity', 'mod_ep') . "');",
    ]);

    $table->data[] = [
        format_string($activity->name),
        ep_format_ects($activity->ects),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
        ep_render_places_cell($activity, $counts),
        ep_render_registration_counts($counts),
        $teacherscell,
        $visible,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
