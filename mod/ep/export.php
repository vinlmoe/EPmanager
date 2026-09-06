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
 * Export CSV pour la DEVE : le bilan par étudiant (ECTS retenus par type, avancement des
 * minimums) ou le détail de tous les crédits. Le premier sert aux jurys, le second au contrôle
 * de ce qui a été validé et par qui.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:viewall', $context);

$baseurl = new moodle_url('/mod/ep/export.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('exportcsv', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$types = ep_get_types($ep->id);

if ($mode === 'students') {
    ep_sync_stage_credits_if_due($ep);

    $export = new csv_export_writer();
    $export->set_filename(clean_filename(format_string($ep->name) . '-etudiants'));

    $header = [
        get_string('lastname'),
        get_string('firstname'),
        get_string('email'),
        get_string('totalretainedshort', 'mod_ep'),
        get_string('validatedects', 'mod_ep'),
        get_string('cappedects', 'mod_ep'),
        get_string('pendingects', 'mod_ep'),
        get_string('mincursusects', 'mod_ep'),
        get_string('cursusminimumshort', 'mod_ep'),
        get_string('yearsdoneshort', 'mod_ep'),
        get_string('status', 'mod_ep'),
    ];
    foreach ($types as $type) {
        $header[] = format_string($type->name);
    }
    $export->add_data($header);

    foreach (ep_get_pilotage_overview($ep, $context) as $row) {
        $progress = $row->progress;
        $line = [
            $row->user->lastname,
            $row->user->firstname,
            $row->user->email,
            ep_format_ects($progress->totalretained),
            ep_format_ects($progress->totalvalidated),
            ep_format_ects($progress->totalcapped),
            ep_format_ects($progress->totalpending),
            ep_format_ects($progress->mincursus),
            $progress->cursusdone ? get_string('yes') : get_string('no'),
            $progress->yearsdone . '/' . $progress->yearstotal,
            $progress->complete ? get_string('objectivedone', 'mod_ep') : get_string('objectivetodo', 'mod_ep'),
        ];
        foreach ($types as $typeid => $type) {
            $line[] = isset($progress->types[$typeid]) ? ep_format_ects($progress->types[$typeid]->retained) : '0';
        }
        $export->add_data($line);
    }

    $export->download_file();
    exit;
}

if ($mode === 'credits') {
    ep_sync_stage_credits_if_due($ep);

    $export = new csv_export_writer();
    $export->set_filename(clean_filename(format_string($ep->name) . '-credits'));
    $export->add_data([
        get_string('lastname'),
        get_string('firstname'),
        get_string('email'),
        get_string('type', 'mod_ep'),
        get_string('creditname', 'mod_ep'),
        get_string('studyyear', 'mod_ep'),
        get_string('weeks', 'mod_ep'),
        get_string('claimedects', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('grade', 'mod_ep'),
        get_string('status', 'mod_ep'),
        get_string('origin', 'mod_ep'),
        get_string('decidedby', 'mod_ep'),
        get_string('decidedon', 'mod_ep'),
        get_string('validatorcomment', 'mod_ep'),
    ]);

    $credits = ep_get_filtered_credits($ep->id, [], 'student', 'ASC');
    $students = ep_get_credit_users($credits);
    $validators = [];

    foreach ($credits as $credit) {
        $student = $students[$credit->userid] ?? null;
        $type = $types[$credit->typeid] ?? null;

        $decidedby = '';
        if (!empty($credit->validatedby)) {
            if (!isset($validators[$credit->validatedby])) {
                $validators[$credit->validatedby] = $DB->get_record('user', ['id' => $credit->validatedby]);
            }
            $decidedby = $validators[$credit->validatedby] ? fullname($validators[$credit->validatedby]) : '';
        } else if ($credit->source === EP_SOURCE_STAGE) {
            $decidedby = get_string('automaticattribution', 'mod_ep');
        }

        $export->add_data([
            $student ? $student->lastname : '',
            $student ? $student->firstname : '',
            $student ? $student->email : '',
            $type ? $type->name : '',
            $credit->name,
            ep_studyyear_label($credit->studyyear),
            // Vide plutôt que 0 pour les types qui ne se comptent pas à la semaine : une colonne
            // de zéros laisserait croire à une durée nulle plutôt qu'à une durée sans objet.
            (float) $credit->weeks > 0 ? format_float((float) $credit->weeks, 2, true, true) : '',
            ep_format_ects($credit->claimedects),
            ep_format_ects($credit->retainedects),
            $credit->grade !== null ? format_float((float) $credit->grade, 2, true, true) : '',
            ep_status_label($credit->status),
            get_string('source_' . $credit->source, 'mod_ep'),
            $decidedby,
            $credit->validatetime ? userdate($credit->validatetime, get_string('strftimedatetimeshort')) : '',
            (string) $credit->validatorcomment,
        ]);
    }

    $export->download_file();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportcsv', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/dashboard.php', ['id' => $cm->id]), get_string('back'));

$table = new html_table();
$table->head = [get_string('adminsectionpage', 'mod_ep'), get_string('adminsectionpurpose', 'mod_ep')];
$table->data[] = [
    html_writer::link(new moodle_url($baseurl, ['mode' => 'students']),
        get_string('exportstudents', 'mod_ep'), ['class' => 'btn btn-secondary']),
    get_string('exportstudents_desc', 'mod_ep'),
];
$table->data[] = [
    html_writer::link(new moodle_url($baseurl, ['mode' => 'credits']),
        get_string('exportcredits', 'mod_ep'), ['class' => 'btn btn-secondary']),
    get_string('exportcredits_desc', 'mod_ep'),
];
echo html_writer::table($table);

echo $OUTPUT->footer();
