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
 * Progression d'une compétence le long du cursus : période par période, les modules qui la visent,
 * à quel niveau, et ce que leur fiche en dit (acquis visés, méthodes, évaluation) ; constats sur
 * la progression (introduite, approfondie, maîtrisée) ; cours sans module qui la visent.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabusstaff/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$id = required_param('id', PARAM_INT);
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$compid = optional_param('compid', 0, PARAM_INT);
$withsub = optional_param('sub', 1, PARAM_BOOL);
$download = optional_param('download', '', PARAM_ALPHA);
$cm = get_coursemodule_from_id('syllabusstaff', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$course, $instance, $context, $canmanageall] = syllabusstaff_page_init(
    $cm,
    '/mod/syllabusstaff/progression.php',
    ['frameworkid' => $frameworkid, 'compid' => $compid, 'sub' => (int) $withsub]
);

$frameworks = syllabus_get_frameworks();
$comp = $compid ? $DB->get_record('syllabus_comp', ['id' => $compid]) : null;
if ($comp) {
    $frameworkid = (int) $comp->frameworkid;
}
if (!isset($frameworks[$frameworkid])) {
    $frameworkid = $frameworks ? (int) array_key_first($frameworks) : 0;
    $comp = null;
}
$tree = $frameworkid ? syllabus_get_framework_tree($frameworkid) : [];
$levels = syllabus_competency_levels();

// Modules du périmètre dans l'ordre du cursus, et liens de la compétence avec leurs cours.
$modules = [];
$periods = [];
$coursewide = [];
$findings = null;
if ($comp) {
    [$rows] = syllabus_list_instances(['categoryid' => $instance->categoryid]);
    $rows = syllabus_sort_by_period($rows);
    foreach ($rows as $row) {
        [$key, $label] = syllabus_period_of($row);
        $periods[$key] = $periods[$key] ?? (object) ['label' => $label, 'modules' => [], 'levels' => []];
    }
    $courses = syllabusstaff_scope_courses($instance);
    $bymodule = [];
    foreach (syllabus_competency_links((int) $comp->id, $withsub, array_keys($courses)) as $link) {
        if ((int) $link->syllabusid) {
            $bymodule[(int) $link->syllabusid][] = $link;
        } else {
            $coursewide[(int) $link->courseid][] = $link;
        }
    }
    foreach ($rows as $row) {
        if (empty($bymodule[(int) $row->id])) {
            continue;
        }
        $row->links = $bymodule[(int) $row->id];
        $row->published = syllabus_get_published((int) $row->id);
        [$key] = syllabus_period_of($row);
        $periods[$key]->modules[] = $row;
        foreach ($row->links as $link) {
            $periods[$key]->levels[] = (int) $link->level;
        }
        $modules[(int) $row->id] = $row;
    }
    $findings = syllabus_progression_summary($periods);
}

// Libellé d'un lien : niveau, et la sous-compétence visée si ce n'est pas la compétence choisie.
$linklabel = function (stdClass $link) use ($tree, $comp, $levels): string {
    $text = $levels[(int) $link->level] ?? '-';
    if ((int) $link->compid !== (int) $comp->id && isset($tree[$link->compid])) {
        $text = $tree[$link->compid]->idnumber . ' ' . $text;
    }
    return $text;
};

if ($download === 'csv' && $comp) {
    $csv = new csv_export_writer('semicolon');
    $csv->set_filename('progression-' . clean_filename($comp->idnumber));
    $csv->add_data([get_string('semester', 'mod_syllabus'), get_string('code', 'mod_syllabus'),
        get_string('module', 'mod_syllabus'), get_string('competency', 'mod_syllabus'), get_string('level', 'mod_syllabus'),
        get_string('fiche', 'mod_syllabus')]);
    foreach ($periods as $period) {
        foreach ($period->modules as $module) {
            foreach ($module->links as $link) {
                $csv->add_data([$period->label, $module->code !== '' ? $module->code : $module->courseshortname,
                    $module->name, $tree[$link->compid]->idnumber ?? '', $levels[(int) $link->level] ?? '',
                    $module->published ? get_string('versionshort', 'mod_syllabus', $module->published->version)
                        : get_string('notpublishedshort', 'mod_syllabus')]);
            }
        }
    }
    foreach ($coursewide as $courseid => $links) {
        foreach ($links as $link) {
            $csv->add_data([get_string('coursewithoutmodule', 'mod_syllabusstaff'), $courses[$courseid]->shortname,
                $courses[$courseid]->fullname, $tree[$link->compid]->idnumber ?? '', $levels[(int) $link->level] ?? '', '']);
        }
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo syllabusstaff_navlinks($cm, 'progression', $canmanageall);
echo $OUTPUT->heading(get_string('progression', 'mod_syllabusstaff'), 3);
echo html_writer::tag('p', get_string('progression_help', 'mod_syllabusstaff'));
if (!$frameworks) {
    echo $OUTPUT->notification(get_string($canmanageall ? 'noframeworksdeve' : 'noframeworks', $canmanageall
        ? 'mod_syllabusstaff' : 'mod_syllabus'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Choix du référentiel et de la compétence.
$url = new moodle_url('/mod/syllabusstaff/progression.php', ['id' => $cm->id]);
if (count($frameworks) > 1) {
    echo $OUTPUT->single_select(
        $url,
        'frameworkid',
        array_map(fn($f) => format_string($f->name), $frameworks),
        $frameworkid,
        null,
        null,
        ['label' => get_string('framework', 'mod_syllabus')]
    );
}
$choices = [];
foreach ($tree as $node) {
    $choices[$node->id] = str_repeat("\u{2003}", $node->depth) . $node->idnumber . ' ' . format_string($node->name);
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring(), 'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'frameworkid', 'value' => $frameworkid]);
echo html_writer::tag('label', get_string('competency', 'mod_syllabus'), ['for' => 'compid', 'class' => 'mr-2']);
echo html_writer::select(
    $choices,
    'compid',
    $comp->id ?? '',
    ['' => get_string('choosecompetency', 'mod_syllabusstaff')],
    ['id' => 'compid', 'class' => 'custom-select mr-2']
);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sub', 'value' => 0]);
echo html_writer::checkbox(
    'sub',
    1,
    $withsub,
    get_string('withsubcompetencies', 'mod_syllabusstaff'),
    ['class' => 'mr-1'],
    ['class' => 'mr-3']
);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('show', 'mod_syllabusstaff'),
    'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
if (!$comp) {
    echo $OUTPUT->footer();
    exit;
}

// La compétence : ses parents, son intitulé, sa description.
$ancestors = [];
for ($parent = (int) $comp->parentid; $parent && isset($tree[$parent]); $parent = (int) $tree[$parent]->parentid) {
    array_unshift($ancestors, $tree[$parent]->idnumber . ' ' . format_string($tree[$parent]->name));
}
if ($ancestors) {
    echo html_writer::div(s(implode(' › ', $ancestors)), 'small text-muted');
}
echo $OUTPUT->heading(s($comp->idnumber) . ' ' . format_string($comp->name), 4);
if (trim((string) $comp->description) !== '') {
    echo html_writer::tag('p', format_text($comp->description, FORMAT_PLAIN, ['para' => false]));
}

// Frise : une colonne par période du cursus, le niveau atteint dans chacune.
$colours = [SYLLABUS_LEVEL_INTRODUCED => 'background:#d6eaf8', SYLLABUS_LEVEL_DEVELOPED => 'background:#85c1e9',
    SYLLABUS_LEVEL_MASTERED => 'background:#1f618d;color:#fff'];
$frieze = new html_table();
$frieze->attributes['class'] = 'generaltable syllabusstaff-frieze text-center';
$frieze->head = array_map(fn($p) => s($p->label), array_values($periods));
$cells = [];
foreach ($periods as $period) {
    $cell = new html_table_cell('');
    if ($period->levels) {
        $max = max($period->levels);
        $short = array_map(fn($l) => get_string('levelshort_' . $l, 'mod_syllabus'), array_unique($period->levels));
        sort($short);
        $cell->text = html_writer::tag('strong', implode(' ', $short)) . html_writer::div(
            get_string('modulesn', 'mod_syllabusstaff', count($period->modules)),
            'small'
        );
        $cell->style = $colours[$max];
        $cell->attributes['title'] = $levels[$max];
    }
    $cells[] = $cell;
}
$frieze->data[] = new html_table_row($cells);
echo $OUTPUT->heading(get_string('alongcursus', 'mod_syllabusstaff'), 5);
echo html_writer::div(html_writer::table($frieze), 'table-responsive');
echo html_writer::tag('p', get_string('friezelegend', 'mod_syllabusstaff'), ['class' => 'small text-muted']);

// Constats.
$items = [];
foreach ($levels as $level => $label) {
    $items[] = get_string('levelreached', 'mod_syllabusstaff', (object) ['level' => html_writer::tag('strong', $label),
        'period' => s($findings->first[$level] ?? get_string('notreached', 'mod_syllabusstaff'))]);
}
echo $OUTPUT->heading(get_string('findings', 'mod_syllabusstaff'), 5);
echo html_writer::alist($items);
foreach ($findings->warnings as $warning) {
    echo $OUTPUT->notification(get_string('warning_' . $warning, 'mod_syllabusstaff'), 'warning');
}

// Détail, période par période.
$excerpt = ['outcomes', 'methods', 'assessment'];
$fields = syllabus_text_fields();
foreach ($periods as $period) {
    if (!$period->modules) {
        continue;
    }
    echo $OUTPUT->heading(s($period->label), 5, 'mt-4');
    foreach ($period->modules as $module) {
        $title = html_writer::link(new moodle_url('/mod/syllabus/view.php', ['id' => $module->cmid]), format_string($module->name))
            . ' ' . html_writer::span(s($module->code !== '' ? $module->code : $module->courseshortname), 'small text-muted');
        $badges = '';
        foreach ($module->links as $link) {
            $badges .= html_writer::span(s($linklabel($link)), 'badge mr-1', ['style' => $colours[(int) $link->level]]);
        }
        $body = html_writer::div($title . ' ' . $badges, 'font-weight-bold mb-1')
            . html_writer::div(get_string('ectsn', 'mod_syllabusstaff', syllabus_format_number($module->ects))
                . ' · ' . s(implode(', ', array_map(
                    'fullname',
                    syllabus_get_teachers((int) $module->id)
                ))), 'small text-muted mb-2');
        if ($module->published) {
            $text = '';
            foreach ($excerpt as $field) {
                $value = trim((string) ($module->published->$field ?? ''));
                if ($value !== '') {
                    $text .= html_writer::tag('dt', $fields[$field]) . html_writer::tag('dd', format_text(
                        $value,
                        FORMAT_PLAIN,
                        ['para' => false]
                    ));
                }
            }
            $body .= $text !== '' ? html_writer::tag('dl', $text, ['class' => 'mb-0 small']) : '';
        } else {
            $body .= html_writer::div(get_string('nofiche', 'mod_syllabus'), 'small text-muted');
        }
        echo html_writer::div($body, 'border rounded p-2 mb-2 syllabusstaff-progression-module');
    }
}

// Cours sans module (stages...) : hors des périodes du cursus.
if ($coursewide) {
    echo $OUTPUT->heading(get_string('coursewithoutmodule', 'mod_syllabusstaff'), 5, 'mt-4');
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('course'), get_string('level', 'mod_syllabus')];
    foreach ($coursewide as $courseid => $links) {
        $table->data[] = [html_writer::link(
            new moodle_url('/course/view.php', ['id' => $courseid]),
            format_string($courses[$courseid]->fullname)
        ), implode(' ', array_map(fn($l) => html_writer::span(
            s($linklabel($l)),
            'badge mr-1',
            ['style' => $colours[(int) $l->level]]
        ), $links))];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url($PAGE->url, ['download' => 'csv']),
    get_string('downloadcsv', 'mod_syllabus'),
    ['class' => 'btn btn-secondary mt-3']
);
echo $OUTPUT->footer();
