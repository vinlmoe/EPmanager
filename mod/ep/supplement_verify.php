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
 * Vérification d'un supplément au diplôme présenté par son titulaire (à un employeur, un autre
 * établissement) : le numéro et le code de vérification imprimés sur le document doivent
 * correspondre à une émission du site. L'adresse de cette page est imprimée sur chaque
 * supplément ; elle ne dépend d'aucun cours.
 *
 * La réponse se limite à « authentique, émis le ... pour ... », « remplacé » ou « inconnu » :
 * rien d'autre du dossier n'est révélé, et un code d'au moins seize caractères est exigé (voir
 * ep_verify_supplement()), si bien qu'on ne peut pas énumérer les suppléments par leur numéro.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Page publique, sans connexion : voir plus bas.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$number = trim(optional_param('number', '', PARAM_TEXT));
$code = trim(optional_param('code', '', PARAM_ALPHANUM));

// Page publique : elle sert à des tiers sans compte sur le site (employeur, autre établissement).
// Aucune connexion n'est demandée ; rien n'y est écrit et rien du dossier n'y est révélé.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/ep/supplement_verify.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('supplementverify', 'mod_ep'));
$PAGE->set_heading(get_string('supplementverify', 'mod_ep'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('supplementverify', 'mod_ep'));
echo html_writer::tag('p', get_string('supplementverify_help', 'mod_ep'));

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'mb-4']);
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('supplementnumberheader', 'mod_ep'), 'ep-verify-number');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'number', 'id' => 'ep-verify-number', 'value' => $number,
    'class' => 'form-control', 'size' => 30, 'maxlength' => 64, 'required' => 'required', 'autocomplete' => 'off']);
echo html_writer::end_div();
echo html_writer::start_div('form-group');
echo html_writer::label(get_string('supplementverifycodelabel', 'mod_ep'), 'ep-verify-code');
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'code', 'id' => 'ep-verify-code', 'value' => $code,
    'class' => 'form-control', 'size' => 70, 'maxlength' => 64, 'minlength' => 16, 'required' => 'required',
    'autocomplete' => 'off', 'spellcheck' => 'false']);
echo html_writer::end_div();
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
    'value' => get_string('supplementverify', 'mod_ep')]);
echo html_writer::end_tag('form');

if ($number !== '' || $code !== '') {
    $issue = ep_verify_supplement($number, $code);
    if (!$issue) {
        echo $OUTPUT->notification(get_string('supplementverifyko', 'mod_ep'), 'error');
    } else {
        $holder = $DB->get_record('user', ['id' => $issue->userid]);
        echo $OUTPUT->notification(get_string('supplementverifyok', 'mod_ep', (object) [
            'date' => userdate($issue->timeissued, get_string('strftimedate', 'langconfig')),
            'name' => $holder ? fullname($holder) : '-',
        ]), 'success');
        if (!empty($issue->replacedby)) {
            echo $OUTPUT->notification(get_string('supplementverifyreplaced', 'mod_ep'), 'warning');
        }
    }
}

echo $OUTPUT->footer();
