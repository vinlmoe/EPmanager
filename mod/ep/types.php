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
 * Paramétrage par la DEVE des types d'enseignement personnalisé : leur libellé, la consigne
 * affichée à l'étudiant, le maximum d'ECTS retenu pour chacun (sur le cursus et, si besoin, par
 * année) et, pour le type stage, le barème d'ECTS par jour de stage complémentaire validé.
 *
 * Les six types sont fixes (ce sont eux que le module sait traiter) : ils se paramètrent et se
 * désactivent, ils ne s'ajoutent ni ne se suppriment.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
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

if ($action === 'save' && data_submitted() && confirm_sesskey()) {
    foreach (ep_get_types($ep->id) as $type) {
        $type->name = trim(optional_param('name_' . $type->id, $type->name, PARAM_TEXT));
        if ($type->name === '') {
            $type->name = get_string('type_' . $type->code, 'mod_ep');
        }
        $type->description = optional_param('description_' . $type->id, '', PARAM_TEXT);
        $type->enabled = optional_param('enabled_' . $type->id, 0, PARAM_INT) ? 1 : 0;
        $type->maxects = max(0, round(optional_param('maxects_' . $type->id, 0, PARAM_FLOAT), 2));
        $type->maxectsperyear = max(0, round(optional_param('maxectsperyear_' . $type->id, 0, PARAM_FLOAT), 2));
        if ($type->code === EP_TYPE_STAGE) {
            $type->ectsperday = max(0, round(optional_param('ectsperday_' . $type->id, 0, PARAM_FLOAT), 3));
        }
        $type->timemodified = time();
        $DB->update_record('ep_type', $type);
    }

    // Le barème et l'activation du type stage viennent de changer : les crédits automatiques
    // sont recalculés dans la foulée, sans quoi la page de pilotage afficherait encore l'ancien
    // décompte jusqu'à la nuit suivante.
    ep_sync_stage_credits($ep);

    redirect($baseurl, get_string('typessaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managetypes', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));

echo $OUTPUT->notification(get_string('managetypes_help', 'mod_ep'), 'info');

$types = ep_get_types($ep->id);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);

$table = new html_table();
$table->head = [
    get_string('type', 'mod_ep'),
    get_string('typeattribution', 'mod_ep'),
    get_string('enabled', 'mod_ep'),
    get_string('maxects', 'mod_ep'),
    get_string('maxectsperyear', 'mod_ep'),
    get_string('ectsperday', 'mod_ep'),
    get_string('typeinstruction', 'mod_ep'),
];

foreach ($types as $type) {
    // Comment les ECTS de ce type arrivent : c'est ce qui distingue les types les uns des autres
    // et ce qu'il faut avoir en tête avant de fixer un plafond.
    if (!empty($type->autovalidate)) {
        $attribution = html_writer::span(get_string('attributionautomatic', 'mod_ep'), 'badge badge-success');
    } else if (!empty($type->catalog)) {
        $attribution = html_writer::span(get_string('attributioncatalog', 'mod_ep'), 'badge badge-primary');
    } else {
        $attribution = html_writer::span(get_string('attributiondeclared', 'mod_ep'), 'badge badge-info');
    }

    $ectsperdaycell = '-';
    if ($type->code === EP_TYPE_STAGE) {
        $ectsperdaycell = html_writer::empty_tag('input', [
            'type' => 'number', 'step' => '0.001', 'min' => 0, 'name' => 'ectsperday_' . $type->id,
            'value' => ep_format_ects_input($type->ectsperday),
            'class' => 'form-control',
        ]);
    }

    $table->data[] = [
        html_writer::empty_tag('input', [
            'type' => 'text', 'name' => 'name_' . $type->id, 'value' => s($type->name), 'class' => 'form-control',
        ]),
        $attribution,
        html_writer::checkbox('enabled_' . $type->id, 1, (bool) $type->enabled, ''),
        html_writer::empty_tag('input', [
            'type' => 'number', 'step' => '0.25', 'min' => 0, 'name' => 'maxects_' . $type->id,
            'value' => ep_format_ects_input($type->maxects), 'class' => 'form-control',
        ]),
        html_writer::empty_tag('input', [
            'type' => 'number', 'step' => '0.25', 'min' => 0, 'name' => 'maxectsperyear_' . $type->id,
            'value' => ep_format_ects_input($type->maxectsperyear), 'class' => 'form-control',
        ]),
        $ectsperdaycell,
        html_writer::tag('textarea', s($type->description), [
            'name' => 'description_' . $type->id, 'rows' => 2, 'class' => 'form-control',
        ]),
    ];
}

echo html_writer::table($table);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary mt-2',
]);
echo html_writer::end_tag('form');

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
