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
 * Import en masse par la DEVE, depuis un fichier CSV (export Excel) : le catalogue des EP propres
 * à cette promotion, ou des EP portés au crédit d'étudiants (inscriptions au catalogue et
 * déclarations hors catalogue confondues, saisies pour leur compte).
 *
 * Chaque ligne est résolue et vérifiée indépendamment des autres : une ligne fautive (email
 * inconnu, EP ou type introuvable, doublon...) est signalée et ignorée, sans empêcher l'import des
 * lignes valides du même fichier — corriger et réimporter seulement ce qui a échoué, plutôt que de
 * tout reprendre depuis le début.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$baseurl = new moodle_url('/mod/ep/import.php', ['id' => $cm->id]);

// Chaque mode a sa propre capacité, à l'image de ce qu'il importe : le catalogue relève de la
// gestion de l'activité, les crédits de la saisie DEVE pour le compte d'un étudiant.
$modes = [
    'activities' => 'mod/ep:manage',
    'credits' => 'mod/ep:validatedeve',
];
if ($mode !== '' && isset($modes[$mode])) {
    require_capability($modes[$mode], $context);
    $pageurl = new moodle_url($baseurl, ['mode' => $mode]);
} else {
    $mode = '';
    $pageurl = $baseurl;
}

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('importexcel', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$results = null;
$uploaderror = null;

if ($mode !== '' && data_submitted() && confirm_sesskey()) {
    $upload = $_FILES['importfile'] ?? null;
    if (empty($upload) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        $uploaderror = get_string('importerrorupload', 'mod_ep');
    } else {
        $columns = $mode === 'activities' ? ep_import_activity_columns() : ep_import_credit_columns();
        $parsed = ep_parse_import_csv(file_get_contents($upload['tmp_name']), $columns);

        if ($parsed->error !== null) {
            $uploaderror = $parsed->error;
        } else if ($mode === 'activities') {
            $results = ep_import_activities($ep, $parsed->rows);
        } else {
            $directvalidate = optional_param('directvalidate', 0, PARAM_BOOL);
            $results = ep_import_credits($ep, $context, $parsed->rows, $USER->id, $directvalidate);
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importexcel', 'mod_ep'));

if ($mode === '') {
    echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));

    $table = new html_table();
    $table->head = [get_string('adminsectionpage', 'mod_ep'), get_string('adminsectionpurpose', 'mod_ep')];
    if (has_capability('mod/ep:manage', $context)) {
        $table->data[] = [
            html_writer::link(new moodle_url($baseurl, ['mode' => 'activities']),
                get_string('importactivities', 'mod_ep'), ['class' => 'btn btn-secondary']),
            get_string('importactivities_desc', 'mod_ep'),
        ];
    }
    if (has_capability('mod/ep:validatedeve', $context)) {
        $table->data[] = [
            html_writer::link(new moodle_url($baseurl, ['mode' => 'credits']),
                get_string('importcredits', 'mod_ep'), ['class' => 'btn btn-secondary']),
            get_string('importcredits_desc', 'mod_ep'),
        ];
    }

    if (empty($table->data)) {
        echo $OUTPUT->notification(get_string('nopermissions', 'error', '', get_string('importexcel', 'mod_ep')),
            'error');
    } else {
        echo html_writer::table($table);
    }

    echo $OUTPUT->footer();
    exit;
}

echo html_writer::link($baseurl, get_string('back'));

if ($uploaderror !== null) {
    echo $OUTPUT->notification($uploaderror, \core\output\notification::NOTIFY_ERROR);
}

if ($results) {
    echo $OUTPUT->notification(get_string('importresult', 'mod_ep', $results->created),
        \core\output\notification::NOTIFY_SUCCESS);
    if (!empty($results->errors)) {
        echo $OUTPUT->notification(implode(html_writer::empty_tag('br'), array_map('s', $results->errors)),
            \core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->box(get_string($mode === 'activities' ? 'importactivities_help' : 'importcredits_help', 'mod_ep'),
    'generalbox mb-3');

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $pageurl->out(false), 'enctype' => 'multipart/form-data',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'file', 'name' => 'importfile', 'accept' => '.csv', 'required' => 'required',
]);

// Coché, valide directement les lignes dont la colonne status est vide plutôt que de les laisser
// en attente : la DEVE n'a alors rien à remplir dans cette colonne quand tout le fichier est déjà
// décidé. Une valeur explicite dans le fichier reste toujours prioritaire sur cette option.
if ($mode === 'credits') {
    echo html_writer::div(
        html_writer::checkbox('directvalidate', 1, false, get_string('importdirectvalidate', 'mod_ep'),
            ['id' => 'importdirectvalidate']),
        'mt-2');
    echo html_writer::div(get_string('importdirectvalidate_help', 'mod_ep'), 'text-muted small mb-2');
}

echo html_writer::tag('button', get_string('import', 'mod_ep'),
    ['type' => 'submit', 'class' => 'btn btn-primary ml-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
