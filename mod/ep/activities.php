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
 * Gestion du catalogue des EP internes par la DEVE : ajout, édition, ouverture/fermeture aux
 * inscriptions, suppression, et accès à l'affectation des responsables de chaque EP.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/ep/classes/form/activity_form.php');

use mod_ep\form\activity_form;

$id = required_param('id', PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$baseurl = new moodle_url('/mod/ep/activities.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('managecatalog', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Seuls les types non automatiques peuvent porter un EP du catalogue (voir
// ep_get_catalogable_types()). Les libellés de tous les types restent chargés à part, pour
// afficher correctement la ligne d'un EP créé sur un type depuis désactivé.
$types = ep_get_catalogable_types($ep->id);
$alltypes = ep_get_types($ep->id);

// Suppression : refusée dès qu'un étudiant s'y est inscrit, sinon les crédits déjà accordés
// perdraient l'EP auquel ils se rattachent. Clore ou archiver l'EP est ce
// qu'il faut faire dans ce cas.
if ($action === 'delete' && $activityid) {
    $activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
    if ($DB->record_exists('ep_credit', ['activityid' => $activity->id])) {
        redirect(
            $baseurl,
            get_string('erroractivityinuse', 'mod_ep'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        if (!ep_delete_activity($ep, $activity)) {
            redirect($baseurl, get_string('erroractivityinuse', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($baseurl, get_string('activitydeleted', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    // Suppression définitive : une page de confirmation, comme pour les types.
    $confirmurl = new moodle_url($baseurl, [
        'action' => 'delete', 'activityid' => $activity->id, 'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    ep_set_secondary_page_header();
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managecatalog', 'mod_ep'));
    echo $OUTPUT->confirm(
        get_string('confirmdeleteactivity', 'mod_ep', format_string($activity->name)),
        new single_button($confirmurl, get_string('delete'), 'post', single_button::BUTTON_DANGER),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Changement d'état rapide depuis la liste : publier, clore, archiver.
if ($action === 'setstate' && $activityid) {
    require_sesskey();
    $activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
    $state = required_param('state', PARAM_ALPHA);
    if (array_key_exists($state, ep_activity_state_options())) {
        $DB->set_field('ep_activity', 'state', $state, ['id' => $activity->id]);
        $DB->set_field('ep_activity', 'timemodified', time(), ['id' => $activity->id]);
    }
    redirect($baseurl);
}

if ($action === 'edit') {
    if (empty($types)) {
        redirect(
            new moodle_url('/mod/ep/types.php', ['id' => $cm->id]),
            get_string('errornotypes', 'mod_ep'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $formurl = new moodle_url($baseurl, ['action' => 'edit', 'activityid' => $activityid]);
    $activity = null;
    if ($activityid) {
        $activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
        // Un EP créé sur un type depuis désactivé reste modifiable sur ce type : le retirer de la
        // liste ferait basculer silencieusement l'EP sur un autre type à l'enregistrement.
        if (!isset($types[$activity->typeid]) && isset($alltypes[$activity->typeid])) {
            $types[$activity->typeid] = $alltypes[$activity->typeid];
        }
    }

    $others = array_filter(ep_get_activities($ep->id), fn($other) => (int) $other->id !== (int) $activityid);
    $mform = new activity_form($formurl, ['types' => $types, 'otheractivities' => $others, 'epid' => $ep->id]);

    if ($activity) {
        $mform->set_data([
            'id' => $cm->id,
            'activityid' => $activity->id,
            'name' => $activity->name,
            'description' => $activity->description,
            'typeid' => $activity->typeid,
            'ects' => (float) $activity->ects,
            'minstudyyear' => $activity->minstudyyear,
            'maxstudyyear' => $activity->maxstudyyear,
            'capacity' => $activity->capacity,
            'sortorder' => $activity->sortorder,
            'code' => $activity->code,
            'state' => $activity->state,
            'registrationstart' => $activity->registrationstart,
            'registrationend' => $activity->registrationend,
            'datestart' => $activity->datestart,
            'dateend' => $activity->dateend,
            'grading' => $activity->grading,
            'grademax' => ep_format_ects_input($activity->grademax),
            'gradepass' => ep_format_ects_input($activity->gradepass),
            'prerequisites' => ep_get_activity_prerequisites($activity->id),
            'minects' => ep_format_ects_input($activity->minects),
            'prereqblocking' => $activity->prereqblocking,
        ]);
    } else {
        // Premier type qui s'acquiert par le catalogue, dans l'ordre choisi par la DEVE.
        $defaulttype = reset($types) ?: null;
        $mform->set_data([
            'id' => $cm->id,
            'activityid' => 0,
            'typeid' => $defaulttype ? $defaulttype->id : 0,
        ]);
    }

    if ($mform->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $mform->get_data()) {
        if (!isset($types[$data->typeid])) {
            throw new moodle_exception('errorinvalidtype', 'mod_ep', $baseurl->out(false));
        }

        $record = clone $data;
        $record->id = (int) $data->activityid;
        $record->prerequisites = $data->prerequisites ?? [];
        $savedid = ep_save_activity($ep, $record);

        // Un EP du catalogue sans responsable n'a personne pour valider ses inscriptions :
        // l'écran d'affectation suit immédiatement la création, plutôt que d'attendre que
        // quelqu'un s'aperçoive que des demandes stagnent.
        redirect(
            new moodle_url('/mod/ep/activity_teachers.php', ['id' => $cm->id, 'activityid' => $savedid]),
            get_string('activitysaved', 'mod_ep'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    ep_set_secondary_page_header();
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managecatalog', 'mod_ep'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecatalog', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));
echo html_writer::link(
    new moodle_url($baseurl, ['action' => 'edit']),
    get_string('addactivity', 'mod_ep'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']
);

$activities = ep_get_activities($ep->id);
$takenplaces = ep_get_activities_taken_places($ep->id);
$allteachers = ep_get_activities_teachers($ep->id);

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('nocatalogactivities', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('catalogactivity', 'mod_ep'),
    get_string('type', 'mod_ep'),
    get_string('ects', 'mod_ep'),
    get_string('studyyearrange', 'mod_ep'),
    get_string('places', 'mod_ep'),
    get_string('registrations', 'mod_ep'),
    get_string('activityteachers', 'mod_ep'),
    get_string('activitystate', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];

foreach ($activities as $activity) {
    $type = $alltypes[$activity->typeid] ?? null;
    $taken = $takenplaces[$activity->id] ?? 0;
    $remaining = empty($activity->capacity) ? null : max(0, (int) $activity->capacity - $taken);

    // État, et les passages utiles depuis la liste : publier un EP clos ou archivé, clore un EP
    // publié, archiver un EP clos.
    $statecell = html_writer::span(
        ep_activity_state_options()[$activity->state] ?? s($activity->state),
        $activity->state === EP_ACTIVITY_PUBLISHED ? 'badge badge-success' : 'badge badge-secondary'
    );
    $period = ep_activity_registration_label($activity);
    if ($period !== '') {
        $statecell .= html_writer::div($period, 'text-muted small');
    }
    $transitions = [
        EP_ACTIVITY_PUBLISHED => [EP_ACTIVITY_CLOSED],
        EP_ACTIVITY_CLOSED => [EP_ACTIVITY_PUBLISHED, EP_ACTIVITY_ARCHIVED],
        EP_ACTIVITY_ARCHIVED => [EP_ACTIVITY_PUBLISHED],
    ];
    $statelinks = [];
    foreach ($transitions[$activity->state] ?? [] as $target) {
        $statelinks[get_string('activitystate_' . $target, 'mod_ep')] = new moodle_url($baseurl, [
            'action' => 'setstate', 'activityid' => $activity->id, 'state' => $target, 'sesskey' => sesskey(),
        ]);
    }
    if ($statelinks) {
        $statecell .= html_writer::div(ep_render_actions($statelinks, 'btn btn-sm btn-link p-0 mr-2'), 'small');
    }

    $teachersurl = new moodle_url(
        '/mod/ep/activity_teachers.php',
        ['id' => $cm->id, 'activityid' => $activity->id]
    );
    $teachercount = count($allteachers[$activity->id] ?? []);
    $teacherscell = html_writer::link(
        $teachersurl,
        get_string('activityteacherscount', 'mod_ep', $teachercount)
    );
    if ($teachercount === 0) {
        // Sans responsable, les inscriptions restent en attente indéfiniment : le signaler ici
        // est le seul endroit où la DEVE le verra avant que des étudiants ne s'en plaignent.
        $teacherscell .= ' ' . html_writer::span(get_string('noteacherwarning', 'mod_ep'), 'badge badge-warning');
    }

    // Une action principale ; les autres dans le menu de la ligne.
    $actions = ep_render_action_menu([
        get_string('edit') => new moodle_url($baseurl, ['action' => 'edit', 'activityid' => $activity->id]),
    ], [
        get_string('participants', 'mod_ep') => new moodle_url('/mod/ep/participants.php', [
            'id' => $cm->id, 'activityid' => $activity->id,
        ]),
        get_string('activityteachers', 'mod_ep') => $teachersurl,
        get_string('syllabus', 'mod_ep') => new moodle_url('/mod/ep/syllabus.php', [
            'id' => $cm->id, 'activityid' => $activity->id,
        ]),
        get_string('delete') => new moodle_url($baseurl, ['action' => 'delete', 'activityid' => $activity->id]),
    ], [get_string('delete')]);

    $table->data[] = [
        format_string($activity->name),
        $type ? format_string($type->name) : '-',
        ep_format_ects($activity->ects),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
        $remaining === null
            ? get_string('unlimitedplaces', 'mod_ep')
            : get_string('placesleft', 'mod_ep', (object) ['left' => $remaining, 'total' => $activity->capacity]),
        $taken,
        $teacherscell,
        $statecell,
        $actions,
    ];
}

echo html_writer::div(html_writer::table($table), 'table-responsive');
echo $OUTPUT->footer();
