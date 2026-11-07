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
 * Validation des années d'étude (DEVE) : pour l'année choisie, une ligne par étudiant inscrit avec
 * ses ECTS retenus, le minimum de l'année, s'il est atteint, et la décision enregistrée ; une
 * décision individuelle (validée ou non, avec commentaire) par ligne, et en tête la validation en
 * lot des années atteintes ou le refus des autres.
 *
 * La décision reste humaine : un minimum atteint ne valide rien tant que la DEVE ne l'a pas
 * enregistré, et une dérogation (année validée sans le minimum) reste possible ligne par ligne.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:validatedeve', $context);

// Années proposées : celles de mod_stage, sans « non précisée » qui n'a pas de minimum. Par
// défaut, l'année courante de la promotion, celle dont la décision se prend en fin d'année.
$yearoptions = ep_studyyear_options();
unset($yearoptions[0]);
$defaultyear = array_key_exists((int) $ep->currentstudyyear, $yearoptions) ? (int) $ep->currentstudyyear : 1;
$studyyear = optional_param('studyyear', $defaultyear, PARAM_INT);
if (!array_key_exists($studyyear, $yearoptions)) {
    $studyyear = $defaultyear;
}

$baseurl = new moodle_url('/mod/ep/year_validation.php', ['id' => $cm->id]);
$listurl = new moodle_url($baseurl, ['studyyear' => $studyyear, 'search' => $search, 'page' => $page]);
$PAGE->set_url($listurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('yearvalidation', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'decide') {
    require_sesskey();
    $userid = required_param('userid', PARAM_INT);
    $decision = required_param('decision', PARAM_INT);
    $comment = optional_param('comment', '', PARAM_TEXT);
    // Seul un étudiant inscrit à l'activité a une année à valider dans cette activité.
    if (ep_count_enrolled_students($context, '', [$userid]) !== 1) {
        throw new moodle_exception('invaliduserid', 'error');
    }
    ep_set_year_validation($ep, $userid, $studyyear, $decision === 1, trim($comment), $USER->id);
    redirect($listurl, get_string('yeardecisionsaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'bulk') {
    require_sesskey();
    $reject = optional_param('reject', 0, PARAM_BOOL);
    $built = ep_enrolled_students_sql($context);
    $userids = [];
    if ($built !== null) {
        [$sql, $params] = $built;
        $userids = array_map('intval', $DB->get_fieldset_sql("SELECT u.id $sql ORDER BY u.id", $params));
    }
    // Toute la promotion en une requête : le temps est celui du calcul des bilans.
    core_php_time_limit::raise();
    $result = ep_validate_years_in_bulk($ep, $studyyear, $userids, $USER->id, (bool) $reject);
    redirect($listurl, get_string('yearvalidationdone', 'mod_ep', $result), null, \core\output\notification::NOTIFY_SUCCESS);
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('yearvalidation', 'mod_ep'), 'yearvalidation', 'mod_ep');
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));

echo html_writer::div(
    $OUTPUT->render(new \mod_ep\output\list_filters(
        $listurl,
        ['search', 'studyyear', 'page'],
        $search,
        ['studyyear' => ['options' => $yearoptions, 'selected' => $studyyear]]
    )),
    'mt-3'
);

/**
 * Bouton POST d'une action groupée sur l'année choisie (validation ou refus en lot).
 *
 * @param moodle_url $url
 * @param int $reject 1 pour déclarer non validées les années non atteintes.
 * @param string $label
 * @param string $class Classe CSS du bouton.
 * @return string HTML
 */
function ep_year_validation_bulk_button(moodle_url $url, $reject, $label, $class) {
    $html = html_writer::start_tag('form', [
        'method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline-block mr-2 mb-2',
    ]);
    foreach (['sesskey' => sesskey(), 'action' => 'bulk', 'reject' => $reject] as $name => $value) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => $label, 'class' => $class]);
    return $html . html_writer::end_tag('form');
}

$bulkurl = new moodle_url($baseurl, ['studyyear' => $studyyear, 'search' => $search]);
echo html_writer::div(
    ep_year_validation_bulk_button($bulkurl, 0, get_string('yearvalidatecomplete', 'mod_ep'), 'btn btn-primary')
    . ep_year_validation_bulk_button($bulkurl, 1, get_string('yearrejectincomplete', 'mod_ep'), 'btn btn-outline-danger'),
    'mb-3'
);

$total = ep_count_enrolled_students($context, $search);
$students = ep_get_enrolled_students($context, $search, null, $page * EP_LIST_PERPAGE, EP_LIST_PERPAGE);
if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Bilans de la page chargés d'un coup (cache de promotion), décisions de la page en une requête.
$pilotage = ep_build_pilotage_rows($ep, $students);
$validations = ep_get_year_validations($ep->id, array_keys($students));
$required = (float) (ep_get_year_requirements($ep->id)[$studyyear] ?? 0);
$decisionoptions = [1 => get_string('yearvalidated', 'mod_ep'), 0 => get_string('yearnotvalidated', 'mod_ep')];

$head = [
    get_string('student', 'mod_ep'),
    get_string('retainedects', 'mod_ep'),
    get_string('requiredects', 'mod_ep'),
    get_string('status', 'mod_ep'),
    get_string('decision', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];
$rows = [];
foreach ($pilotage as $row) {
    $userid = (int) $row->user->id;
    $year = $row->progress->years[$studyyear] ?? null;
    $retained = $year ? (float) $year->retained : 0.0;
    // Même règle que la validation en lot : sans minimum pour l'année, elle est atteinte d'office.
    $done = $year ? (bool) $year->done : $required <= 0;
    $statuscell = html_writer::span(
        get_string($done ? 'yearreached' : 'yearnotreached', 'mod_ep'),
        $done ? 'badge badge-success' : 'badge badge-warning'
    );

    $validation = $validations[$userid][$studyyear] ?? null;
    if ($validation) {
        $decided = (int) $validation->decision === 1;
        $decisioncell = html_writer::span(
            get_string($decided ? 'yearvalidated' : 'yearnotvalidated', 'mod_ep'),
            $decided ? 'badge badge-success' : 'badge badge-danger'
        ) . html_writer::div(
            userdate($validation->timedecided, get_string('strftimedatefullshort', 'langconfig')),
            'small text-muted'
        );
        if (trim((string) $validation->comment) !== '') {
            $decisioncell .= html_writer::div(s($validation->comment), 'small');
        }
    } else {
        $decisioncell = html_writer::span(get_string('yearnodecision', 'mod_ep'), 'badge badge-secondary');
    }

    // Décision individuelle : proposée par défaut dans le sens du minimum, ou de la décision en
    // place, pour qu'un simple enregistrement confirme ce qui est attendu.
    $selected = $validation ? (int) $validation->decision : ($done ? 1 : 0);
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $listurl->out(false), 'class' => 'form-inline']);
    foreach (['sesskey' => sesskey(), 'action' => 'decide', 'userid' => $userid] as $name => $value) {
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    $form .= html_writer::label(get_string('decision', 'mod_ep'), 'ep-yeardecision-' . $userid, false, ['class' => 'sr-only']);
    $form .= html_writer::select($decisionoptions, 'decision', $selected, false, [
        'id' => 'ep-yeardecision-' . $userid, 'class' => 'custom-select custom-select-sm mr-1 mb-1',
    ]);
    $commentlabel = get_string('validatorcomment', 'mod_ep');
    $form .= html_writer::label($commentlabel, 'ep-yearcomment-' . $userid, false, ['class' => 'sr-only']);
    $form .= html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'comment', 'id' => 'ep-yearcomment-' . $userid, 'size' => 20,
        'value' => $validation ? (string) $validation->comment : '',
        'placeholder' => get_string('validatorcomment', 'mod_ep'), 'class' => 'form-control form-control-sm mr-1 mb-1',
    ]);
    $form .= html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-sm btn-secondary mb-1',
    ]);
    $form .= html_writer::end_tag('form');

    $rows[] = [
        fullname($row->user),
        ep_format_ects($retained),
        $required > 0 ? ep_format_ects($required) : '-',
        $statuscell,
        $decisioncell,
        $form,
    ];
}

$pagingurl = new moodle_url($baseurl, ['studyyear' => $studyyear, 'search' => $search]);
echo ep_render_paged_table($head, $rows, ep_render_paging_bar($total, $page, $pagingurl));

echo $OUTPUT->footer();
