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
 * Gestion du catalogue des EP par la DEVE de la promotion : ajout, édition, ouverture/fermeture
 * aux inscriptions, suppression, et accès à l'affectation des responsables de chaque EP.
 *
 * Seuls les EP propres à cette promotion se gèrent ici. Les EP partagés — définis dans une
 * activité « Suivi de l'enseignement personnalisé » parce que des étudiants de plusieurs
 * promotions s'y inscrivent — sont rappelés en fin de page, en lecture seule : les modifier ici
 * changerait ce que voient les autres promotions.
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
// perdraient l'EP auquel ils se rattachent. Fermer l'EP aux inscriptions (visible = 0) est ce
// qu'il faut faire dans ce cas.
if ($action === 'delete' && $activityid) {
    require_sesskey();
    $activity = $DB->get_record('ep_activity',
        ['id' => $activityid, 'epid' => $ep->id, 'synthesiscmid' => 0], '*', MUST_EXIST);
    if (!ep_delete_activity($activity->id)) {
        redirect($baseurl, get_string('erroractivityinuse', 'mod_ep'), null,
            \core\output\notification::NOTIFY_ERROR);
    }
    redirect($baseurl, get_string('activitydeleted', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Bascule rapide ouvert / fermé aux inscriptions.
if ($action === 'togglevisible' && $activityid) {
    require_sesskey();
    $activity = $DB->get_record('ep_activity',
        ['id' => $activityid, 'epid' => $ep->id, 'synthesiscmid' => 0], '*', MUST_EXIST);
    $activity->visible = $activity->visible ? 0 : 1;
    $activity->timemodified = time();
    $DB->update_record('ep_activity', $activity);
    redirect($baseurl);
}

if ($action === 'edit') {
    if (empty($types)) {
        redirect(new moodle_url('/mod/ep/types.php', ['id' => $cm->id]),
            get_string('errornotypes', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $formurl = new moodle_url($baseurl, ['action' => 'edit', 'activityid' => $activityid]);
    $activity = null;
    if ($activityid) {
        $activity = $DB->get_record('ep_activity',
            ['id' => $activityid, 'epid' => $ep->id, 'synthesiscmid' => 0], '*', MUST_EXIST);
        // Un EP créé sur un type depuis désactivé reste modifiable sur ce type : le retirer de la
        // liste ferait basculer silencieusement l'EP sur un autre type à l'enregistrement.
        if (!isset($types[$activity->typeid]) && isset($alltypes[$activity->typeid])) {
            $types[$activity->typeid] = $alltypes[$activity->typeid];
        }
    }

    $mform = new activity_form($formurl, ['types' => $types]);

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
            'visible' => $activity->visible,
        ]);
    } else {
        $defaulttype = ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC);
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
        // L'EP visé est revérifié : l'identifiant vient d'un champ caché du formulaire, et une
        // valeur forgée ne doit pas permettre de réécrire l'EP d'une autre promotion, ni de
        // s'approprier un EP partagé en le rattachant à celle-ci.
        if (!empty($data->activityid) && !$DB->record_exists('ep_activity',
                ['id' => $data->activityid, 'epid' => $ep->id, 'synthesiscmid' => 0])) {
            throw new moodle_exception('errorunknownactivity', 'mod_ep', $baseurl->out(false));
        }

        $record = new stdClass();
        $record->epid = $ep->id;
        // EP propre à cette promotion : il n'est défini dans aucune synthèse (voir
        // mod/epsynthesis/activities.php pour les EP partagés).
        $record->synthesiscmid = 0;
        $record->typeid = (int) $data->typeid;
        $record->name = $data->name;
        $record->description = $data->description;
        $record->ects = round((float) $data->ects, 2);
        $record->minstudyyear = (int) $data->minstudyyear;
        $record->maxstudyyear = (int) $data->maxstudyyear;
        $record->capacity = max(0, (int) $data->capacity);
        $record->sortorder = (int) $data->sortorder;
        $record->visible = !empty($data->visible) ? 1 : 0;
        $record->timemodified = time();

        if (!empty($data->activityid)) {
            $record->id = $data->activityid;
            $DB->update_record('ep_activity', $record);
            $savedid = $record->id;
        } else {
            $record->timecreated = time();
            $savedid = $DB->insert_record('ep_activity', $record);
        }

        // Un EP du catalogue sans responsable n'a personne pour valider ses inscriptions :
        // l'écran d'affectation suit immédiatement la création, plutôt que d'attendre que
        // quelqu'un s'aperçoive que des demandes stagnent.
        redirect(new moodle_url('/mod/ep/activity_teachers.php', ['id' => $cm->id, 'activityid' => $savedid]),
            get_string('activitysaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managecatalog', 'mod_ep'));
    $mform->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecatalog', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));
echo html_writer::link(new moodle_url($baseurl, ['action' => 'edit']), get_string('addactivity', 'mod_ep'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']);

$activities = ep_get_activities($ep->id);
$shared = ep_get_shared_activities(ep_get_synthesis_cmids($ep));

if (empty($activities) && empty($shared)) {
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
    get_string('openforregistration', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];

foreach ($activities as $activity) {
    $type = $alltypes[$activity->typeid] ?? null;
    $counts = ep_get_activity_registration_counts($activity->id);

    $togglevisibleurl = new moodle_url($baseurl,
        ['action' => 'togglevisible', 'activityid' => $activity->id, 'sesskey' => sesskey()]);
    $visible = html_writer::link($togglevisibleurl,
        $activity->visible ? get_string('yes') : get_string('no'),
        ['class' => $activity->visible ? 'badge badge-success' : 'badge badge-secondary']);

    $teachersurl = new moodle_url('/mod/ep/activity_teachers.php',
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
    ]) . html_writer::link($deleteurl, get_string('delete'), [
        'class' => 'btn btn-sm btn-outline-danger mr-1 mb-1',
        'onclick' => "return confirm('" . get_string('confirmdeleteactivity', 'mod_ep') . "');",
    ]);

    $table->data[] = [
        format_string($activity->name),
        $type ? format_string($type->name) : '-',
        ep_format_ects($activity->ects),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
        ep_render_places_cell($activity, $counts),
        ep_render_registration_counts($counts),
        $teacherscell,
        $visible,
        $actions,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('noownactivities', 'mod_ep'), 'info');
} else {
    echo html_writer::table($table);
}

// Les EP partagés, en lecture seule : ils comptent dans le catalogue de cette promotion, mais
// c'est l'activité de suivi qui les définit — les corriger ici, à l'insu des autres promotions
// inscrites, serait le meilleur moyen de les faire diverger.
if (!empty($shared)) {
    echo $OUTPUT->heading(get_string('sharedactivities', 'mod_ep'), 4);
    echo html_writer::tag('p', get_string('sharedactivities_help', 'mod_ep'), ['class' => 'text-muted']);

    $sharedtable = new html_table();
    $sharedtable->head = [
        get_string('catalogactivity', 'mod_ep'),
        get_string('ects', 'mod_ep'),
        get_string('studyyearrange', 'mod_ep'),
        get_string('places', 'mod_ep'),
        get_string('registrations', 'mod_ep'),
        get_string('activityteachers', 'mod_ep'),
        get_string('openforregistration', 'mod_ep'),
        get_string('definedin', 'mod_ep'),
    ];
    foreach ($shared as $activity) {
        $counts = ep_get_activity_registration_counts($activity->id);
        $teachers = ep_get_activity_teachers($activity->id);

        $sharedtable->data[] = [
            format_string($activity->name),
            ep_format_ects($activity->ects),
            ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
            ep_render_places_cell($activity, $counts),
            ep_render_registration_counts($counts),
            empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers)),
            $activity->visible ? get_string('yes') : get_string('no'),
            ep_render_shared_activity_origin($activity),
        ];
    }
    echo html_writer::table($sharedtable);
}

echo $OUTPUT->footer();
