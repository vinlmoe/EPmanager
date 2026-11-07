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
 * « À traiter » : ce qui attend une action de l'utilisateur connecté dans les activités
 * « Enseignement personnalisé » liées, hors demandes de crédits (voir entries.php) — les
 * participants des EP dont il est responsable, ses propositions et révisions de syllabus, les
 * éléments du supplément au diplôme de ses étudiants. Chaque ligne renvoie vers la page de
 * l'activité d'origine qui permet d'agir ; les éléments du supplément se tranchent ici même.
 *
 * Même périmètre et mêmes contrôles que entries.php (voir epsynthesis_get_active_links()) ; seul
 * ce qui revient à l'utilisateur comme responsable d'EP ou enseignant référent y figure, pas ce
 * qu'il pourrait trancher en tant que DEVE.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$cm = get_coursemodule_from_id('epsynthesis', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$epsynthesis = $DB->get_record('epsynthesis', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/epsynthesis:view', $context);

$baseurl = new moodle_url('/mod/epsynthesis/todo.php', ['id' => $cm->id]);
$pageurl = $page ? new moodle_url($baseurl, ['page' => $page]) : $baseurl;
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($epsynthesis->name) . ' - ' . get_string('todo', 'mod_epsynthesis'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$activelinks = epsynthesis_get_active_links($epsynthesis->id, $USER->id);

// Décision sur un élément du supplément. Tout est revérifié sur l'état courant de l'élément (voir
// epsynthesis_decide_supplement_item()) : un élément hors périmètre, déjà tranché ou passé à une
// autre étape depuis l'affichage donne le même message, sans rien révéler de plus.
if (data_submitted()) {
    require_sesskey();
    $itemid = required_param('itemid', PARAM_INT);
    $comment = optional_param('validatorcomment', '', PARAM_TEXT);
    $validate = optional_param('validate', '', PARAM_TEXT) !== '';
    $reject = optional_param('reject', '', PARAM_TEXT) !== '';
    $returnurl = new moodle_url($pageurl, null, 'todo-supplement');

    if ($validate === $reject) {
        // Ni l'un ni l'autre (ou les deux, forgé) : aucune décision lisible.
        redirect($returnurl);
    }
    if (!epsynthesis_decide_supplement_item($activelinks, $itemid, $USER->id, $validate, trim($comment))) {
        redirect($returnurl, get_string('supplementitemchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($returnurl, get_string('supplementitemdecided', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$counts = epsynthesis_count_todo($activelinks, $USER->id);

echo $OUTPUT->header();
echo epsynthesis_render_navlinks($cm, 'todo', $counts);
echo epsynthesis_render_managelinks_notice($epsynthesis, $cm, $context);

if (empty($activelinks)) {
    echo $OUTPUT->notification(get_string('noscope', 'mod_epsynthesis'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(get_string('todointro', 'mod_epsynthesis'), 'text-muted mb-3');

/**
 * Nom du cours d'origine d'une ligne, suivi du nom de l'activité mod_ep pour distinguer deux
 * activités d'un même cours.
 *
 * @param stdClass $row Ligne portant coursename et, s'il est connu, epname.
 * @return string HTML
 */
function epsynthesis_todo_origin(stdClass $row): string {
    $html = format_string($row->coursename);
    if (!empty($row->epname)) {
        $html .= html_writer::div(format_string($row->epname), 'text-muted small');
    }
    return $html;
}

/**
 * Nom d'un EP du catalogue suivi de son code, s'il en a un.
 *
 * @param string $name
 * @param string|null $code
 * @return string HTML
 */
function epsynthesis_todo_activity_name($name, $code): string {
    $html = format_string($name);
    if (trim((string) $code) !== '') {
        $html .= ' ' . html_writer::span(s($code), 'text-muted small');
    }
    return $html;
}

$strftime = get_string('strftimedatetimeshort', 'langconfig');

// 1. EP du catalogue dont l'utilisateur est responsable et dont des participants attendent sa
// décision : une ligne par EP, la saisie se fait sur la liste des participants de l'activité.
echo html_writer::start_div('', ['id' => 'todo-participants']);
echo $OUTPUT->heading(get_string('todoparticipants', 'mod_epsynthesis', $counts->participants), 4);
$participants = epsynthesis_get_todo_participants($activelinks);
if (empty($participants)) {
    echo $OUTPUT->notification(get_string('todonoparticipants', 'mod_epsynthesis'), 'info');
} else {
    $rows = [];
    foreach ($participants as $row) {
        $rows[] = [
            epsynthesis_todo_origin($row),
            epsynthesis_todo_activity_name($row->name, $row->code),
            html_writer::span($row->pendingcount, 'badge badge-info'),
            userdate($row->oldest, $strftime),
            ep_render_actions([
                get_string('participants', 'mod_ep') => new moodle_url(
                    '/mod/ep/participants.php',
                    ['id' => $row->cmid, 'activityid' => $row->id]
                ),
            ], 'btn btn-sm btn-primary mr-1 mb-1'),
        ];
    }
    echo ep_render_paged_table([
        get_string('course'),
        get_string('catalogactivity', 'mod_ep'),
        get_string('todopendingparticipants', 'mod_epsynthesis'),
        get_string('todooldest', 'mod_epsynthesis'),
        get_string('actions', 'mod_ep'),
    ], $rows);
}
echo html_writer::end_div();

// 2. Propositions et révisions de syllabus : à reprendre (brouillon, renvoyée par la DEVE avec
// son commentaire), puis, pour information, celles qui attendent la DEVE.
$syllabi = epsynthesis_get_todo_syllabi($activelinks, $USER->id);
$torework = array_values(array_filter($syllabi, fn($row) => $row->status !== 'submitted'));
$submitted = array_values(array_filter($syllabi, fn($row) => $row->status === 'submitted'));

/**
 * Tableau d'une liste de versions de syllabus de la synthèse.
 *
 * @param array $rows Voir epsynthesis_get_todo_syllabi().
 * @param bool $withcomment Affiche le commentaire de la DEVE (versions renvoyées).
 * @return string HTML
 */
function epsynthesis_todo_syllabi_table(array $rows, bool $withcomment): string {
    $strftime = get_string('strftimedatetimeshort', 'langconfig');
    $head = [
        get_string('course'),
        get_string('catalogactivity', 'mod_ep'),
        get_string('syllabusversionlabel', 'mod_ep'),
        get_string('syllabusstatus', 'mod_ep'),
    ];
    if ($withcomment) {
        $head[] = get_string('reviewcomment', 'mod_ep');
    }
    $head[] = get_string('todolastchange', 'mod_epsynthesis');
    $head[] = get_string('actions', 'mod_ep');

    $badges = ['submitted' => 'badge badge-warning', 'returned' => 'badge badge-danger', 'draft' => 'badge badge-secondary'];
    $cells = [];
    foreach ($rows as $row) {
        $params = ['id' => $row->cmid, 'activityid' => $row->activityid];
        $editable = $row->canedit && in_array($row->status, ['draft', 'returned'], true);
        $cell = [
            epsynthesis_todo_origin($row),
            epsynthesis_todo_activity_name($row->activityname, $row->activitycode),
            (int) $row->version,
            html_writer::span(
                get_string('syllabusstatus_' . $row->status, 'mod_ep'),
                $badges[$row->status] ?? 'badge badge-secondary'
            ),
        ];
        if ($withcomment) {
            $comment = trim((string) $row->reviewcomment);
            $cell[] = ($row->status === 'returned' && $comment !== '') ? format_text($comment, FORMAT_PLAIN) : '-';
        }
        $time = $row->status === 'submitted' ? (int) $row->timesubmitted : (int) $row->timemodified;
        $cell[] = $time ? userdate($time, $strftime) : '-';
        $cell[] = ep_render_actions([
            get_string('editsyllabus', 'mod_ep') => $editable ? new moodle_url('/mod/ep/syllabus_edit.php', $params) : null,
            get_string('syllabus', 'mod_ep') => $row->canview ? new moodle_url('/mod/ep/syllabus.php', $params) : null,
        ]);
        $cells[] = $cell;
    }
    return ep_render_paged_table($head, $cells);
}

echo html_writer::start_div('mt-4', ['id' => 'todo-syllabi']);
echo $OUTPUT->heading(get_string('todosyllabi', 'mod_epsynthesis', $counts->syllabi), 4);
if (empty($torework)) {
    echo $OUTPUT->notification(get_string('todonosyllabi', 'mod_epsynthesis'), 'info');
} else {
    echo epsynthesis_todo_syllabi_table($torework, true);
}
if ($submitted) {
    echo $OUTPUT->heading(get_string('todosubmitted', 'mod_epsynthesis', $counts->submitted), 5, 'mt-3');
    echo html_writer::div(get_string('todosubmitted_help', 'mod_epsynthesis'), 'text-muted small mb-2');
    echo epsynthesis_todo_syllabi_table($submitted, false);
}
echo html_writer::end_div();

// 3. Éléments du supplément au diplôme à trancher comme référent, une page à la fois ; un petit
// formulaire par ligne, comme sur mod/ep/supplement_items.php.
echo html_writer::start_div('mt-4', ['id' => 'todo-supplement']);
echo $OUTPUT->heading(get_string('todosupplement', 'mod_epsynthesis', $counts->supplement), 4);
$perpage = EP_LIST_PERPAGE;
if ($counts->supplement > 0 && $page * $perpage >= $counts->supplement) {
    // Page devenue vide après des décisions : retour à la dernière page existante.
    $page = (int) floor(($counts->supplement - 1) / $perpage);
    $pageurl = $page ? new moodle_url($baseurl, ['page' => $page]) : $baseurl;
}
$items = $counts->supplement > 0
    ? epsynthesis_get_todo_supplement_items($activelinks, $page * $perpage, $perpage)
    : [];
if (empty($items)) {
    echo $OUTPUT->notification(get_string('todonosupplement', 'mod_epsynthesis'), 'info');
} else {
    $students = ep_get_credit_users($items);
    $categoryids = array_unique(array_map(fn($item) => (int) $item->categoryid, $items));
    $categories = $DB->get_records_list('ep_supplement_category', 'id', $categoryids, '', 'id, name');
    $proofs = epsynthesis_get_supplement_proofs($items, $activelinks);
    $formurl = new moodle_url($pageurl, null, 'todo-supplement');

    $rows = [];
    foreach ($items as $item) {
        $student = $students[$item->userid] ?? null;

        $prooflinks = [];
        foreach ($proofs[$item->id] ?? [] as $proof) {
            $prooflinks[] = html_writer::link(new moodle_url('/mod/ep/supplement_file.php', [
                'id' => $item->cmid,
                'area' => 'proof',
                'itemid' => $item->id,
                'pathnamehash' => $proof->pathnamehash,
            ]), s($proof->filename));
        }

        $commentid = 'epsynthesis-supplement-comment-' . $item->id;
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false),
            'style' => 'min-width: 14rem']);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemid', 'value' => $item->id]);
        $form .= html_writer::label(get_string('validatorcomment', 'mod_ep'), $commentid, true, ['class' => 'sr-only']);
        $form .= html_writer::tag('textarea', '', [
            'name' => 'validatorcomment', 'id' => $commentid, 'rows' => 2, 'class' => 'form-control mb-1',
            'placeholder' => get_string('validatorcomment', 'mod_ep'),
        ]);
        $form .= html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'validate',
            'value' => get_string('validate', 'mod_ep'), 'class' => 'btn btn-sm btn-primary mr-1']);
        $form .= html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'reject',
            'value' => get_string('reject', 'mod_ep'), 'class' => 'btn btn-sm btn-danger']);
        $form .= html_writer::end_tag('form');

        $rows[] = [
            html_writer::link(
                new moodle_url('/mod/ep/supplement_items.php', ['id' => $item->cmid]),
                format_string($item->coursename)
            ),
            $student ? html_writer::link(
                new moodle_url('/mod/ep/supplement.php', ['id' => $item->cmid, 'userid' => $item->userid]),
                fullname($student)
            ) : '-',
            \mod_ep\local\supplement_view::category($categories, $item),
            \mod_ep\local\supplement_view::title($item),
            \mod_ep\local\supplement_view::period($item),
            $prooflinks ? implode(html_writer::empty_tag('br'), $prooflinks) : '-',
            userdate($item->timecreated, $strftime),
            $form,
        ];
    }
    echo ep_render_paged_table([
        get_string('course'),
        get_string('student', 'mod_ep'),
        get_string('supplementcategory', 'mod_ep'),
        get_string('supplementitemtitle', 'mod_ep'),
        get_string('supplementperiod', 'mod_ep'),
        get_string('supplementproofs', 'mod_ep'),
        get_string('submittedon', 'mod_ep'),
        get_string('decision', 'mod_ep'),
    ], $rows, ep_render_paging_bar($counts->supplement, $page, $baseurl, $perpage));
}
echo html_writer::end_div();

echo $OUTPUT->footer();
