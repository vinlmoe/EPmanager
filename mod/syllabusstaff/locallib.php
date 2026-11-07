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
 * Fonctions des pages de mod_syllabusstaff.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/syllabusstaff/lib.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

/** Modules par page dans les listes. */
define('SYLLABUSSTAFF_PERPAGE', 50);

/**
 * Amorce d'une page, après require_login().
 *
 * @param stdClass $cm
 * @param string $path
 * @param array $params
 * @return array [course, instance, context, canmanageall]
 */
function syllabusstaff_page_init($cm, string $path, array $params = []): array {
    global $DB, $PAGE;

    $course = get_course($cm->course);
    $instance = $DB->get_record('syllabusstaff', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    require_capability('mod/syllabusstaff:view', $context);
    $PAGE->set_url(new moodle_url($path, ['id' => $cm->id] + $params));
    // Onglets du navigateur distincts : le nom de la page, puis celui de l'activité.
    $titles = ['competencies' => 'competencymatrix', 'progression' => 'progression', 'booklet' => 'booklet',
        'all' => 'allmodules', 'review' => 'toreview', 'results' => 'resultsoverview', 'referential' => 'referential',
        'frameworks' => 'competencyframeworks', 'courselink' => 'coursewithoutmodule'];
    $page = basename($path, '.php');
    $PAGE->set_title(isset($titles[$page]) ? get_string($titles[$page], 'mod_syllabusstaff') . moodle_page::TITLE_SEPARATOR
        . format_string($instance->name) : format_string($instance->name));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);
    return [$course, $instance, $context, has_capability('mod/syllabusstaff:manageall', $context)];
}

/**
 * Onglets de l'activité.
 *
 * @param stdClass $cm
 * @param string $current
 * @param bool $canmanageall
 * @return string HTML
 */
function syllabusstaff_navlinks($cm, string $current, bool $canmanageall): string {
    $pages = ['view' => 'mymodules', 'competencies' => 'competencymatrix', 'progression' => 'progression',
        'booklet' => 'booklet'];
    if ($canmanageall) {
        $pages += ['all' => 'allmodules', 'review' => 'toreview', 'results' => 'resultsoverview',
            'referential' => 'referential', 'frameworks' => 'competencyframeworks'];
    }
    $html = '';
    foreach ($pages as $page => $label) {
        $class = $page === $current ? 'btn btn-primary mr-1 mb-1' : 'btn btn-secondary mr-1 mb-1';
        $html .= html_writer::link(
            new moodle_url('/mod/syllabusstaff/' . $page . '.php', ['id' => $cm->id]),
            get_string($label, 'mod_syllabusstaff'),
            ['class' => $class]
        );
    }
    return html_writer::div($html, 'syllabusstaff-navlinks mb-3');
}

/**
 * Tableau de modules : module (lien vers sa fiche), type, responsables, fiche, évaluation,
 * réponses, liens.
 *
 * @param array $rows Lignes de syllabus_list_instances().
 * @param bool $selectable Case à cocher par ligne (actions en lot de la DEVE) ; désactivée là où
 *     l'utilisateur n'a pas le rôle de la DEVE.
 * @param int[] $highlight Modules à signaler (fiche à rédiger ou renvoyée), placés en tête.
 * @return string HTML
 */
function syllabusstaff_render_modules(array $rows, bool $selectable = false, array $highlight = []): string {
    global $USER;

    if (!$rows) {
        return html_writer::tag('p', get_string('nomodules', 'mod_syllabusstaff'), ['class' => 'text-muted']);
    }
    if ($highlight) {
        // Les modules signalés en tête, chaque groupe dans son ordre d'origine.
        $first = array_filter($rows, fn($row) => in_array((int) $row->id, $highlight, true));
        $rows = $first + array_diff_key($rows, $first);
    }
    $ids = array_map(fn($row) => (int) $row->id, $rows);
    $overview = syllabus_get_overview($ids);
    $teachers = syllabus_get_teachers_bulk($ids);
    $types = syllabus_get_types();
    $teacherresults = syllabus_setting('teacherresults');

    $table = new html_table();
    $table->attributes['class'] = 'generaltable syllabusstaff-modules';
    $table->head = [get_string('module', 'mod_syllabus'), get_string('moduletype', 'mod_syllabus'),
        get_string('teachers', 'mod_syllabus'), get_string('fiche', 'mod_syllabus'), get_string('evaluation', 'mod_syllabus'),
        get_string('responses', 'mod_syllabusstaff'), get_string('actions')];
    if ($selectable) {
        array_unshift($table->head, html_writer::checkbox('selectall', 1, false, '', ['id' => 'syllabusstaff-selectall',
            'aria-label' => get_string('selectall')]));
    }
    foreach ($rows as $row) {
        $info = $overview[(int) $row->id];
        $badges = [];
        if ($info->published) {
            $badges[] = html_writer::span(
                get_string('versionn', 'mod_syllabusstaff', $info->published->version),
                'badge badge-success'
            );
        } else {
            $badges[] = html_writer::span(get_string('notpublished', 'mod_syllabusstaff'), 'badge badge-warning');
        }
        if ($info->working) {
            $badges[] = html_writer::span(syllabus_status_label($info->working->status), 'badge badge-info');
        }
        $context = context_module::instance($row->cmid);
        $state = syllabus_eval_state($row);
        $eligible = $state === SYLLABUS_EVAL_NONE ? '-' : $info->responses . ' / ' . syllabus_count_eligible($context);
        $canmanage = has_capability('mod/syllabus:manage', $context);
        // Résultats : la DEVE toujours, un responsable après la fermeture (ou dès l'ouverture si
        // la DEVE l'a réglé ainsi). Pas de lien vers une page qui le renverrait ailleurs.
        $seeresults = $canmanage || (isset($teachers[(int) $row->id][(int) $USER->id]) && ($state === SYLLABUS_EVAL_CLOSED
            || ($state === SYLLABUS_EVAL_OPEN && $teacherresults === 'always')));
        $links = [html_writer::link(
            new moodle_url('/mod/syllabus/evaluation.php', ['id' => $row->cmid]),
            get_string('evaluation', 'mod_syllabusstaff')
        )];
        if ($seeresults && $state !== SYLLABUS_EVAL_NONE) {
            $links[] = html_writer::link(
                new moodle_url('/mod/syllabus/results.php', ['id' => $row->cmid]),
                get_string('results', 'mod_syllabusstaff')
            );
        }
        $secondary = array_filter([s($row->code), format_string($row->coursename) !== format_string($row->name)
            ? html_writer::link(new moodle_url('/course/view.php', ['id' => $row->course]), format_string($row->coursename)) : '']);
        $cells = [
            html_writer::link(new moodle_url('/mod/syllabus/view.php', ['id' => $row->cmid]), format_string($row->name))
                . ($secondary ? html_writer::div(implode(' · ', $secondary), 'small text-muted') : ''),
            $row->typeid && isset($types[$row->typeid]) ? format_string($types[$row->typeid]->name) : '-',
            s(implode(', ', array_map('fullname', $teachers[(int) $row->id]))) ?: '-',
            implode(' ', $badges),
            syllabus_eval_badge($row) . html_writer::div(s(syllabus_eval_window_label($row)), 'small text-muted'),
            $eligible,
            implode(' · ', $links),
        ];
        if ($selectable) {
            $attributes = ['class' => 'syllabusstaff-select', 'aria-label' => format_string($row->name)];
            if (!$canmanage) {
                $attributes += ['disabled' => 'disabled', 'title' => get_string('nomanageright', 'mod_syllabusstaff')];
            }
            array_unshift($cells, html_writer::checkbox('syllabusids[]', $row->id, false, '', $attributes));
        }
        $tablerow = new html_table_row($cells);
        if (in_array((int) $row->id, $highlight, true)) {
            $tablerow->attributes['class'] = 'table-warning';
        }
        $table->data[] = $tablerow;
    }
    return html_writer::div(html_writer::table($table), 'table-responsive');
}

/**
 * Filtres de liste (recherche, type, fiche, évaluation) en formulaire GET.
 *
 * @param moodle_url $url
 * @param array $current
 * @return string HTML
 */
function syllabusstaff_render_filters(moodle_url $url, array $current): string {
    $html = html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring(),
        'class' => 'form-inline mb-3']);
    $html .= html_writer::input_hidden_params($url, ['search', 'typeid', 'status', 'evalstate', 'page']);
    $html .= html_writer::empty_tag('input', ['type' => 'search', 'name' => 'search', 'value' => $current['search'] ?? '',
        'class' => 'form-control mr-2 mb-1', 'placeholder' => get_string('search'), 'aria-label' => get_string('search')]);
    $html .= html_writer::select(
        syllabus_type_options(),
        'typeid',
        $current['typeid'] ?? 0,
        [0 => get_string('alltypes', 'mod_syllabusstaff')],
        ['class' => 'custom-select mr-2 mb-1',
        'aria-label' => get_string(
            'moduletype',
            'mod_syllabus'
        )]
    );
    $statuses = ['nopublished' => get_string('notpublished', 'mod_syllabusstaff')];
    foreach (['draft', 'submitted', 'returned', 'published'] as $status) {
        $statuses[$status] = syllabus_status_label($status);
    }
    $html .= html_writer::select($statuses, 'status', $current['status'] ?? '', [
        '' => get_string('allstatuses', 'mod_syllabusstaff')], ['class' => 'custom-select mr-2 mb-1',
        'aria-label' => get_string('fiche', 'mod_syllabus')]);
    $states = [];
    foreach ([SYLLABUS_EVAL_NONE, SYLLABUS_EVAL_UPCOMING, SYLLABUS_EVAL_OPEN, SYLLABUS_EVAL_CLOSED] as $state) {
        $states[$state] = get_string('evalstate_' . $state, 'mod_syllabus');
    }
    $html .= html_writer::select($states, 'evalstate', $current['evalstate'] ?? '', [
        '' => get_string('allevalstates', 'mod_syllabusstaff')], ['class' => 'custom-select mr-2 mb-1',
        'aria-label' => get_string('evaluation', 'mod_syllabus')]);
    $html .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter'),
        'class' => 'btn btn-secondary mb-1']);
    return $html . html_writer::end_tag('form');
}

/**
 * Fixe la même fenêtre d'évaluation pour des modules, là où l'utilisateur gère le module
 * (mod/syllabus:manage dans son contexte).
 *
 * @param int[] $syllabusids
 * @param int $open
 * @param int $close
 * @return stdClass {done, skipped}
 */
function syllabusstaff_set_windows(array $syllabusids, int $open, int $close): stdClass {
    global $DB;

    $result = (object) ['done' => 0, 'skipped' => 0];
    [$rows] = $syllabusids ? syllabus_list_instances(['syllabusids' => $syllabusids]) : [[]];
    foreach ($rows as $row) {
        if (!has_capability('mod/syllabus:manage', context_module::instance($row->cmid))) {
            $result->skipped++;
            continue;
        }
        $syllabus = $DB->get_record('syllabus', ['id' => $row->id], '*', MUST_EXIST);
        syllabus_set_window($syllabus, $open, $close);
        $result->done++;
    }
    return $result;
}

/**
 * Date et heure saisies dans un champ « datetime-local » (AAAA-MM-JJTHH:MM), dans le fuseau de
 * l'utilisateur.
 *
 * @param string $value
 * @return int Horodatage, 0 si vide ou illisible.
 */
function syllabusstaff_parse_datetime(string $value): int {
    if (!preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})T(\d{1,2}):(\d{2})/', trim($value), $m)) {
        return 0;
    }
    return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5]);
}

/**
 * Cours du périmètre de l'activité (catégorie et sous-catégories, ou tout le site), hors page
 * d'accueil.
 *
 * @param stdClass $instance
 * @return array id => stdClass {id, shortname, fullname, category}
 */
function syllabusstaff_scope_courses(stdClass $instance): array {
    global $DB;
    $params = ['site' => SITEID];
    $where = 'c.id <> :site';
    if ($instance->categoryid && ($category = core_course_category::get($instance->categoryid, IGNORE_MISSING, true))) {
        $where .= ' AND (cat.id = :catid OR ' . $DB->sql_like('cat.path', ':catpath') . ')';
        $params += ['catid' => $category->id, 'catpath' => $category->path . '/%'];
    }
    return $DB->get_records_sql("SELECT c.id, c.shortname, c.fullname, c.category
                                   FROM {course} c
                                   JOIN {course_categories} cat ON cat.id = c.category
                                  WHERE $where
                               ORDER BY c.fullname", $params);
}

/**
 * Le cours est-il dans le périmètre de l'activité ?
 *
 * @param stdClass $instance
 * @param stdClass $course
 * @return bool
 */
function syllabusstaff_course_in_scope(stdClass $instance, stdClass $course): bool {
    return isset(syllabusstaff_scope_courses($instance)[(int) $course->id]);
}

/**
 * Livret PDF d'un choix d'édition, conservé dans l'activité et resservi tant que rien de ce qu'il
 * contient n'a changé : modules, fiches publiées, responsables, compétences et types, langue, jour
 * (imprimé en page de titre). Un seul livret est gardé par choix d'édition.
 *
 * @param context_module $context
 * @param stdClass $instance
 * @param string $selection
 * @param array $modules Modules du livret, avec published et teachers.
 * @param callable $build Compose le PDF : fn() => contenu.
 * @return stored_file
 */
function syllabusstaff_booklet_file(
    context_module $context,
    stdClass $instance,
    string $selection,
    array $modules,
    callable $build
): stored_file {
    global $DB;

    $ids = array_map(fn($m) => (int) $m->id, $modules);
    [$insql, $params] = $DB->get_in_or_equal($ids ?: [0], SQL_PARAMS_NAMED);
    $links = $DB->get_records_sql("SELECT id, syllabusid, compid, level FROM {syllabus_complink} WHERE syllabusid $insql
                                   ORDER BY id", $params);
    $key = sha1(json_encode([
        $selection, $instance->categoryid, current_language(), userdate(time(), '%Y%m%d', 99, false, false),
        array_map(fn($m) => [$m->id, $m->timemodified, $m->coursename, $m->published->id ?? 0,
            $m->published->timemodified ?? 0, array_keys($m->teachers)], array_values($modules)),
        array_values(array_map(fn($l) => [$l->syllabusid, $l->compid, $l->level], $links)),
        $DB->get_field_sql('SELECT MAX(timemodified) FROM {syllabus_comp}'),
        $DB->get_field_sql('SELECT MAX(timemodified) FROM {syllabus_framework}'),
        $DB->get_records_menu('syllabus_type', null, 'id', 'id, timemodified'),
    ]));
    $prefix = substr(sha1($selection), 0, 12) . '-';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_syllabusstaff', 'booklet', 0, '/', $prefix . $key . '.pdf');
    if ($file) {
        return $file;
    }
    $content = $build();
    foreach ($fs->get_area_files($context->id, 'mod_syllabusstaff', 'booklet', 0, 'id', false) as $old) {
        if (str_starts_with($old->get_filename(), $prefix)) {
            $old->delete();
        }
    }
    return $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_syllabusstaff',
        'filearea' => 'booklet', 'itemid' => 0, 'filepath' => '/', 'filename' => $prefix . $key . '.pdf'], $content);
}
