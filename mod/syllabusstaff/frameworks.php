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
 * Référentiels de compétences, tenus par la DEVE : référentiels, compétences en arbre (domaines,
 * compétences), import CSV. Communs à tout le site, comme les types de module et les questions
 * communes.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');

$id = required_param('id', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$compid = optional_param('compid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/frameworks.php',
    $frameworkid ? ['frameworkid' => $frameworkid] : []
);
require_capability('mod/syllabusstaff:manageall', $context);
$baseurl = new moodle_url('/mod/syllabusstaff/frameworks.php', ['id' => $cm->id]);
$fwurl = new moodle_url($baseurl, ['frameworkid' => $frameworkid]);
$frameworks = syllabus_get_frameworks();
if ($frameworkid && !isset($frameworks[$frameworkid])) {
    throw new moodle_exception('invalidrecord', 'error');
}
$notify = fn($key, $a = null, $ok = true) => [get_string($key, 'mod_syllabusstaff', $a), $ok
    ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR];

// Suppressions et déplacements.
// Les suppressions passent par une page de confirmation du serveur (voir
// syllabus_require_confirmation()).
if ($action === 'deleteframework') {
    $target = required_param('target', PARAM_INT);
    if (!isset($frameworks[$target])) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    syllabus_require_confirmation(
        new moodle_url($baseurl, ['action' => 'deleteframework', 'target' => $target]),
        $baseurl,
        get_string('confirmdeleteframework', 'mod_syllabusstaff', format_string($frameworks[$target]->name))
    );
    $done = syllabus_delete_framework($target);
    [$message, $type] = $notify($done ? 'frameworkdeleted' : 'frameworkinuse', null, $done);
    redirect($baseurl, $message, null, $type);
}
if ($action === 'deletecomp') {
    $comp = $DB->get_record('syllabus_comp', ['id' => $compid], '*', MUST_EXIST);
    syllabus_require_confirmation(
        new moodle_url($fwurl, ['action' => 'deletecomp', 'compid' => $compid]),
        $fwurl,
        get_string('confirmdeletecomp', 'mod_syllabusstaff', format_string($comp->name))
    );
    $done = syllabus_delete_comp($compid);
    [$message, $type] = $notify($done ? 'compdeleted' : 'compinuse', null, $done);
    redirect($fwurl, $message, null, $type);
}
if (in_array($action, ['up', 'down'], true) && confirm_sesskey()) {
    syllabus_move_comp($compid, $action === 'up' ? -1 : 1);
    redirect($fwurl);
}

// Référentiel : création, modification.
$fwform = new \mod_syllabusstaff\form\framework_form(new moodle_url($baseurl, ['action' => 'editframework']));
if ($fwform->is_cancelled()) {
    redirect($frameworkid ? $fwurl : $baseurl);
} else if ($action === 'editframework' && ($data = $fwform->get_data())) {
    try {
        $saved = syllabus_save_framework((object) ['id' => $data->frameworkid, 'name' => $data->name,
            'idnumber' => $data->idnumber, 'description' => $data->description]);
        redirect(
            new moodle_url($baseurl, ['frameworkid' => $saved]),
            get_string('changessaved'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}
$fwform->set_data(['id' => $cm->id, 'frameworkid' => $frameworkid, 'action' => 'editframework']
    + ($frameworkid ? (array) $frameworks[$frameworkid] : []) + ['id' => $cm->id]);

$tree = $frameworkid ? syllabus_get_framework_tree($frameworkid) : [];
$compform = null;
$importform = null;
if ($frameworkid) {
    // Compétence : création (au besoin sous un parent), modification.
    $parents = [];
    $excluded = $compid ? syllabus_comp_subtree($compid) : [];
    foreach ($tree as $comp) {
        if (!in_array((int) $comp->id, $excluded, true)) {
            $parents[$comp->id] = str_repeat('— ', $comp->depth) . $comp->idnumber . ' ' . $comp->name;
        }
    }
    $compform = new \mod_syllabusstaff\form\comp_form(new moodle_url($fwurl, ['action' => 'editcomp']), ['parents' => $parents]);
    if ($compform->is_cancelled()) {
        redirect($fwurl);
    } else if ($action === 'editcomp' && ($data = $compform->get_data())) {
        try {
            syllabus_save_comp((object) ['id' => $data->compid, 'frameworkid' => $frameworkid, 'parentid' => $data->parentid,
                'idnumber' => $data->idnumber, 'name' => $data->name, 'description' => $data->description]);
            // Après un ajout, le formulaire reste ouvert sous le même parent pour enchaîner les sœurs.
            $next = $data->compid ? $fwurl : new moodle_url($fwurl, ['parentid' => $data->parentid], 'compform');
            redirect($next, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            \core\notification::error($e->getMessage());
        }
    }
    $parentid = optional_param('parentid', 0, PARAM_INT);
    $defaults = ['id' => $cm->id, 'frameworkid' => $frameworkid, 'compid' => 0, 'action' => 'editcomp',
        'parentid' => $parentid];
    // Code proposé pour une nouvelle sous-compétence : celui du parent, suivi du rang suivant (C1.4).
    if ($parentid && isset($tree[$parentid])) {
        $children = count(array_filter($tree, fn($c) => (int) $c->parentid === $parentid));
        $defaults['idnumber'] = $tree[$parentid]->idnumber . '.' . ($children + 1);
    }
    if ($compid && isset($tree[$compid])) {
        $defaults = array_merge($defaults, ['compid' => $compid, 'parentid' => $tree[$compid]->parentid,
            'idnumber' => $tree[$compid]->idnumber, 'name' => $tree[$compid]->name, 'description' => $tree[$compid]->description]);
    }
    $compform->set_data($defaults);

    // Import CSV.
    $importform = new \mod_syllabusstaff\form\import_form(new moodle_url($fwurl, ['action' => 'import']));
    if ($action === 'import' && ($data = $importform->get_data())) {
        $result = syllabus_import_comps($frameworkid, (string) $importform->get_file_content('csvfile'));
        foreach ($result->errors as $error) {
            \core\notification::warning($error);
        }
        redirect(
            $fwurl,
            get_string('importdone', 'mod_syllabusstaff', $result),
            null,
            $result->errors ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $importform->set_data(['id' => $cm->id, 'frameworkid' => $frameworkid, 'action' => 'import']);
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'frameworks', $canmanageall);
echo $OUTPUT->heading(get_string('competencyframeworks', 'mod_syllabusstaff'), 3);
echo $OUTPUT->notification(get_string('competencyframeworks_help', 'mod_syllabusstaff'), 'info');

if (!$frameworkid) {
    if ($frameworks) {
        $table = new html_table();
        $table->head = [get_string('name'), get_string('code', 'mod_syllabusstaff'), get_string('competencies', 'mod_syllabus'),
            get_string('links', 'mod_syllabusstaff'), get_string('actions')];
        foreach ($frameworks as $framework) {
            $count = $DB->count_records('syllabus_comp', ['frameworkid' => $framework->id]);
            $links = $DB->count_records_sql('SELECT COUNT(1) FROM {syllabus_complink} l JOIN {syllabus_comp} c ON c.id = l.compid
                                              WHERE c.frameworkid = ?', [$framework->id]);
            $table->data[] = [
                html_writer::link(new moodle_url($baseurl, ['frameworkid' => $framework->id]), format_string($framework->name)),
                s($framework->idnumber), $count, $links,
                html_writer::link(new moodle_url($baseurl, ['frameworkid' => $framework->id]), get_string(
                    'open',
                    'mod_syllabusstaff'
                )) . ' · ' . html_writer::link(new moodle_url($baseurl, ['action' => 'deleteframework',
                    'target' => $framework->id]), get_string('delete'), ['class' => 'text-danger']),
            ];
        }
        echo html_writer::table($table);
    }
    echo $OUTPUT->heading(get_string('addframework', 'mod_syllabusstaff'), 4);
    $fwform->display();
    echo $OUTPUT->footer();
    exit;
}

$framework = $frameworks[$frameworkid];
echo html_writer::link($baseurl, get_string('allframeworks', 'mod_syllabusstaff'));
echo $OUTPUT->heading(format_string($framework->name) . ' (' . s($framework->idnumber) . ')', 4);
if (trim((string) $framework->description) !== '') {
    echo html_writer::tag('p', format_text($framework->description, FORMAT_PLAIN));
}

// Arbre.
if ($tree) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable syllabusstaff-tree';
    $table->head = [get_string('code', 'mod_syllabusstaff'), get_string('competency', 'mod_syllabus'), get_string('actions')];
    $siblings = [];
    foreach ($tree as $comp) {
        $siblings[(int) $comp->parentid][] = (int) $comp->id;
    }
    foreach ($tree as $comp) {
        $act = fn($a, $label, $extra = []) => html_writer::link(new moodle_url($fwurl, ['action' => $a, 'compid' => $comp->id,
            'sesskey' => sesskey()] + $extra), $label);
        $rank = array_search((int) $comp->id, $siblings[(int) $comp->parentid], true);
        $links = array_filter([
            html_writer::link(new moodle_url($fwurl, ['compid' => $comp->id]) . '#compform', get_string('edit')),
            html_writer::link(new moodle_url($fwurl, ['parentid' => $comp->id]) . '#compform', get_string(
                'addchild',
                'mod_syllabusstaff'
            )),
            $rank > 0 ? $act('up', get_string('moveup', 'syllabusstaff')) : '',
            $rank < count($siblings[(int) $comp->parentid]) - 1 ? $act('down', get_string('movedown', 'syllabusstaff')) : '',
            html_writer::link(
                new moodle_url($fwurl, ['action' => 'deletecomp', 'compid' => $comp->id]),
                get_string('delete'),
                ['class' => 'text-danger']
            ),
        ]);
        $name = html_writer::span(format_string($comp->name), $comp->isleaf ? '' : 'font-weight-bold');
        $table->data[] = [s($comp->idnumber), html_writer::div($name, '', ['style' => 'padding-left:'
            . (1.5 * $comp->depth) . 'rem']), implode(' · ', $links)];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocompetenciesyet', 'mod_syllabusstaff'), 'info');
}

echo html_writer::tag('h5', get_string($compid ? 'editcompetency' : 'addcompetency', 'mod_syllabusstaff'), ['id' => 'compform']);
$compform->display();
// Import et réglages du référentiel, repliés : ils servent moins souvent que l'arbre.
echo html_writer::start_tag('details', ['class' => 'mb-3', 'open' => $tree ? null : 'open']);
echo html_writer::tag('summary', html_writer::tag('strong', get_string('importcompetencies', 'mod_syllabusstaff')));
echo html_writer::tag('p', get_string('importcompetencies_help', 'mod_syllabusstaff'), ['class' => 'small text-muted']);
$importform->display();
echo html_writer::end_tag('details');
echo html_writer::start_tag('details', ['class' => 'mb-3']);
echo html_writer::tag('summary', html_writer::tag('strong', get_string('editframework', 'mod_syllabusstaff')));
$fwform->display();
echo html_writer::end_tag('details');
echo $OUTPUT->footer();
