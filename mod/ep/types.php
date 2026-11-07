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
 * Liste des types d'enseignement personnalisé d'une instance (DEVE) : chaque type décrit comment
 * un EP arrive au dossier de l'étudiant, qui le valide, s'il est noté, quels justificatifs sont
 * attendus et quels plafonds d'ECTS s'appliquent. Depuis cette page, la DEVE ajoute, réordonne,
 * active, désactive et supprime les types ; leur paramétrage se fait dans type_edit.php.
 *
 * Un type qui porte des EP du catalogue ou des crédits ne se supprime pas : il se désactive, sans
 * quoi les crédits déjà accordés perdraient le type auquel ils se rattachent.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$typeid = optional_param('typeid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$baseurl = new moodle_url('/mod/ep/types.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('managetypes', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Une instance restaurée depuis une sauvegarde peut arriver sans ses types : les recréer ici
// évite d'avoir à supprimer et recréer l'activité pour la remettre en état.
ep_create_default_types($ep->id);

// Resynchronisation immédiate des EP de type stage, pour vérifier l'effet d'un barème que l'on
// vient de changer sans attendre la tâche planifiée de la nuit.
if ($action === 'sync') {
    require_sesskey();
    $result = ep_sync_stage_credits($ep);
    redirect($baseurl, get_string('syncdone', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Les actions sur un type : le type est relu avec l'instance en condition, pour qu'un
// identifiant d'une autre instance ne puisse pas être manipulé depuis cette page.
$type = null;
if ($typeid && in_array($action, ['move', 'toggle', 'delete'], true)) {
    $type = $DB->get_record('ep_type', ['id' => $typeid, 'epid' => $ep->id], '*', MUST_EXIST);
}

if ($action === 'move' && $type) {
    require_sesskey();
    $dir = optional_param('dir', 'down', PARAM_ALPHA);
    ep_move_type($type, $dir === 'up' ? -1 : 1);
    redirect($baseurl);
}

if ($action === 'toggle' && $type) {
    require_sesskey();
    $type->enabled = $type->enabled ? 0 : 1;
    $type->timemodified = time();
    $DB->update_record('ep_type', $type);
    // Activer ou désactiver un type stage change les crédits automatiques : les recalculer dans la
    // foulée, sans quoi la page de pilotage afficherait encore l'ancien décompte jusqu'à la nuit.
    if (ep_type_is_stage($type)) {
        ep_sync_stage_credits($ep);
    }
    redirect(
        $baseurl,
        get_string($type->enabled ? 'typeenabled' : 'typedisabled', 'mod_ep'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'delete' && $type) {
    if (!ep_type_is_deletable($type->id)) {
        redirect($baseurl, get_string('errortypeinuse', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $DB->delete_records('ep_type', ['id' => $type->id]);
        redirect($baseurl, get_string('typedeleted', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // La suppression est définitive : elle passe par une page de confirmation plutôt que par un
    // simple lien, même protégé par la clé de session.
    $confirmurl = new moodle_url($baseurl, [
        'action' => 'delete', 'typeid' => $type->id, 'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    ep_set_secondary_page_header();
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managetypes', 'mod_ep'));
    echo $OUTPUT->confirm(
        get_string('confirmdeletetype', 'mod_ep', format_string($type->name)),
        new single_button($confirmurl, get_string('delete'), 'post', single_button::BUTTON_DANGER),
        $baseurl
    );
    echo $OUTPUT->footer();
    exit;
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetypes', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));

echo $OUTPUT->notification(get_string('managetypes_help', 'mod_ep'), 'info');

echo html_writer::link(
    new moodle_url('/mod/ep/type_edit.php', ['id' => $cm->id]),
    get_string('addtype', 'mod_ep'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']
);

$types = array_values(ep_get_types($ep->id));
$acquisitions = ep_acquisition_options();
$evidences = ep_evidence_options();

$head = [
    get_string('typename', 'mod_ep'),
    get_string('typecode', 'mod_ep'),
    get_string('acquisition', 'mod_ep'),
    get_string('validationflow', 'mod_ep'),
    get_string('grading', 'mod_ep'),
    get_string('evidence', 'mod_ep'),
    get_string('maxects', 'mod_ep'),
    get_string('maxectsperyear', 'mod_ep'),
    get_string('ectsperday', 'mod_ep'),
    get_string('insupplement', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];

$rows = [];
$last = count($types) - 1;
foreach ($types as $index => $type) {
    $name = format_string($type->name);
    if (empty($type->enabled)) {
        // Un type désactivé reste listé : il porte peut-être encore des crédits, et la DEVE doit
        // pouvoir le réactiver ; le badge le distingue au premier coup d'œil.
        $name .= ' ' . html_writer::span(get_string('hidden', 'mod_ep'), 'badge badge-secondary');
    }

    $grading = get_string('grading_none', 'mod_ep');
    if (ep_type_is_graded($type)) {
        $grading = get_string('grading_grade', 'mod_ep') . ' ('
            . get_string('gradeoutof', 'mod_ep', (object) [
                'grade' => format_float((float) $type->gradepass, 2, true, true),
                'max' => format_float((float) $type->grademax, 2, true, true),
            ]) . ')';
    }

    // Les justificatifs n'ont de sens que pour un type déclaré ; le barème et sa portée, que pour
    // un type stage : ailleurs, la cellule reste vide plutôt que d'afficher une valeur sans effet.
    $evidence = '-';
    if (ep_type_is_declarable($type)) {
        $evidence = $evidences[(int) $type->evidence] ?? '-';
    }
    $ectsperday = '-';
    if (ep_type_is_stage($type)) {
        $ectsperday = ep_format_ects($type->ectsperday) . ' ('
            . get_string('stagescope_' . $type->stagescope, 'mod_ep') . ')';
    }

    $actionparams = ['typeid' => $type->id, 'sesskey' => sesskey()];
    $actions = ep_render_actions([
        get_string('edit') => new moodle_url('/mod/ep/type_edit.php', ['id' => $cm->id, 'typeid' => $type->id]),
        get_string('moveup', 'mod_ep') => $index > 0
            ? new moodle_url($baseurl, $actionparams + ['action' => 'move', 'dir' => 'up']) : null,
        get_string('movedown', 'mod_ep') => $index < $last
            ? new moodle_url($baseurl, $actionparams + ['action' => 'move', 'dir' => 'down']) : null,
        get_string($type->enabled ? 'disable' : 'enable') => new moodle_url($baseurl, $actionparams + ['action' => 'toggle']),
    ]) . ep_render_actions([
        get_string('delete') => new moodle_url($baseurl, ['action' => 'delete', 'typeid' => $type->id]),
    ], 'btn btn-sm btn-outline-danger mr-1 mb-1', '');

    $rows[] = [
        $name,
        s($type->code),
        $acquisitions[$type->acquisition] ?? '-',
        ep_flow_label($type->validationflow),
        $grading,
        $evidence,
        $type->maxects > 0 ? ep_format_ects($type->maxects) : '-',
        $type->maxectsperyear > 0 ? ep_format_ects($type->maxectsperyear) : '-',
        $ectsperday,
        $type->insupplement ? get_string('yes') : get_string('no'),
        $actions,
    ];
}

echo ep_render_paged_table($head, $rows);

echo html_writer::div(
    html_writer::link(
        new moodle_url($baseurl, ['action' => 'sync', 'sesskey' => sesskey()]),
        get_string('syncstagecredits', 'mod_ep'),
        ['class' => 'btn btn-secondary']
    ),
    'mt-4'
);
echo html_writer::tag('p', get_string('syncstagecredits_help', 'mod_ep'), ['class' => 'text-muted']);

echo $OUTPUT->footer();
