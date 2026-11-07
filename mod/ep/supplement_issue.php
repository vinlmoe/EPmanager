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
 * Émission des suppléments au diplôme (DEVE) : une ligne par étudiant inscrit, avec ce qui manque
 * pour émettre son supplément, le supplément en vigueur, l'aperçu et l'émission ; en tête,
 * l'émission pour toute la promotion (tâche de fond) et l'archive ZIP des suppléments en vigueur.
 *
 * L'émission groupée passe par une tâche ad hoc : produire un PDF par étudiant dépasserait le
 * temps d'une requête web. L'archive, elle, ne fait que rassembler des fichiers déjà produits.
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
require_capability('mod/ep:manage', $context);
// EP de type stage à jour avant tout décompte (voir ep_sync_stage_credits_if_due()).
ep_sync_stage_credits_if_due($ep);

$baseurl = new moodle_url('/mod/ep/supplement_issue.php', ['id' => $cm->id]);
$listurl = new moodle_url($baseurl, ['search' => $search, 'page' => $page]);
$PAGE->set_url($listurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('supplementissuesadmin', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Langues d'édition du supplément, nommées par Moodle dans la langue de l'interface.
$languages = get_string_manager()->get_list_of_languages();
$langoptions = [];
foreach (['fr', 'en'] as $code) {
    $langoptions[$code] = $languages[$code] ?? $code;
}

/**
 * Identifiants de tous les étudiants inscrits, sans charger leurs fiches : l'émission groupée
 * n'a besoin que de la liste.
 *
 * @param context $context
 * @return int[]
 */
function ep_supplement_issue_all_userids(context $context): array {
    global $DB;

    $built = ep_enrolled_students_sql($context);
    if ($built === null) {
        return [];
    }
    [$sql, $params] = $built;
    return array_map('intval', $DB->get_fieldset_sql("SELECT u.id $sql ORDER BY u.id", $params));
}

if ($action === 'issue') {
    require_sesskey();
    $userid = required_param('userid', PARAM_INT);
    $lang = optional_param('lang', 'fr', PARAM_ALPHA);
    $force = optional_param('force', 0, PARAM_BOOL);
    // Seul un étudiant inscrit à l'activité peut recevoir un supplément de cette activité.
    if (ep_count_enrolled_students($context, '', [$userid]) !== 1) {
        throw new moodle_exception('invaliduserid', 'error');
    }
    $result = ep_issue_supplement($ep, $context, $userid, $USER->id, array_key_exists($lang, $langoptions) ? $lang : 'fr', $force);
    if (!$result->issue) {
        redirect(
            $listurl,
            get_string('supplementmissing', 'mod_ep', implode(', ', $result->missing)),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    redirect(
        $listurl,
        get_string('supplementissued', 'mod_ep', s($result->issue->number)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'issueall') {
    require_sesskey();
    $lang = optional_param('lang', 'fr', PARAM_ALPHA);
    $userids = ep_supplement_issue_all_userids($context);
    if (empty($userids)) {
        redirect($listurl, get_string('nostudents', 'mod_ep'), null, \core\output\notification::NOTIFY_INFO);
    }
    $task = new \mod_ep\task\issue_supplements();
    $task->set_custom_data((object) [
        'epid' => (int) $ep->id,
        'userids' => $userids,
        'issuerid' => (int) $USER->id,
        'lang' => array_key_exists($lang, $langoptions) ? $lang : 'fr',
        'force' => optional_param('force', 0, PARAM_BOOL) ? 1 : 0,
        'queued' => time(),
    ]);
    $task->set_userid($USER->id);
    \core\task\manager::queue_adhoc_task($task);
    redirect(
        $listurl,
        get_string('supplementissueallqueued', 'mod_ep', count($userids)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($action === 'zip') {
    require_sesskey();
    $issues = ep_get_supplement_issues($ep->id, null, true);
    if (empty($issues)) {
        redirect($listurl, get_string('supplementnoissue', 'mod_ep'), null, \core\output\notification::NOTIFY_INFO);
    }
    // Une promotion entière : plusieurs centaines de PDF à lire et à compresser.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_HUGE);

    $userids = array_unique(array_map(function ($issue) {
        return (int) $issue->userid;
    }, $issues));
    [$insql, $inparams] = $DB->get_in_or_equal($userids);
    $users = $DB->get_records_select('user', "id $insql", $inparams, '', 'id, firstname, lastname');
    $files = [];
    foreach ($issues as $issue) {
        $file = ep_get_supplement_issue_file($context, $issue->id);
        $user = $users[$issue->userid] ?? null;
        if (!$file || !$user) {
            continue;
        }
        // Nom, prénom puis numéro : l'archive se trie par étudiant, et le numéro, unique, évite
        // qu'un homonyme écrase un autre fichier.
        $name = clean_filename(str_replace(' ', '_', $user->lastname . '_' . $user->firstname . '_' . $issue->number) . '.pdf');
        $files[$name] = $file;
    }
    if (empty($files)) {
        redirect($listurl, get_string('supplementnoissue', 'mod_ep'), null, \core\output\notification::NOTIFY_INFO);
    }
    $zippath = make_request_directory() . '/supplements.zip';
    if (!get_file_packer('application/zip')->archive_to_pathname($files, $zippath)) {
        throw new moodle_exception('cannotsavezipfile', 'error');
    }
    $zipname = clean_filename(format_string($ep->name, true, ['context' => $context]) . ' - '
        . get_string('supplementissues', 'mod_ep') . '.zip');
    send_temp_file($zippath, $zipname);
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('supplementissuesadmin', 'mod_ep'), 'supplement', 'mod_ep');
echo html_writer::link(new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]), get_string('back'));
echo html_writer::tag('p', get_string('supplementissuesadmin_desc', 'mod_ep'), ['class' => 'text-muted mt-2']);

/**
 * Formulaire POST d'une action de la page (émission, émission groupée, archive), avec la clé de
 * session et, au besoin, le choix de la langue et la case « émettre malgré les informations
 * manquantes ».
 *
 * @param moodle_url $url
 * @param array $hidden Champs cachés, en plus de la clé de session.
 * @param string $submitlabel
 * @param array|null $langoptions Langues proposées, ou null sans choix de langue ni case à cocher.
 * @param string $class Classe CSS du formulaire.
 * @return string HTML
 */
function ep_supplement_issue_form(moodle_url $url, array $hidden, $submitlabel, ?array $langoptions, $class) {
    static $counter = 0;
    $counter++;

    $html = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => $class]);
    foreach (['sesskey' => sesskey()] + $hidden as $name => $value) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    if ($langoptions !== null) {
        $langid = 'ep-supplement-lang-' . $counter;
        $html .= html_writer::label(get_string('supplementlanguage', 'mod_ep'), $langid, false, ['class' => 'sr-only']);
        $html .= html_writer::select($langoptions, 'lang', 'fr', false, [
            'id' => $langid, 'class' => 'custom-select custom-select-sm mr-2 mb-1',
        ]);
        $html .= html_writer::div(
            html_writer::checkbox('force', 1, false, get_string('supplementissueforce', 'mod_ep'), [
                'id' => 'ep-supplement-force-' . $counter, 'class' => 'mr-1',
            ]),
            'mr-3 mb-1 small'
        );
    }
    $html .= html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => $submitlabel, 'class' => 'btn btn-sm btn-primary mb-1',
    ]);
    $html .= html_writer::end_tag('form');
    return $html;
}

// Actions sur toute la promotion.
echo html_writer::start_div('d-flex flex-wrap align-items-start mt-3 mb-3');
echo ep_supplement_issue_form(
    $listurl,
    ['action' => 'issueall'],
    get_string('supplementissueall', 'mod_ep'),
    $langoptions,
    'form-inline mr-4 mb-2'
);
echo ep_supplement_issue_form($listurl, ['action' => 'zip'], get_string('supplementzip', 'mod_ep'), null, 'form-inline mb-2');
echo html_writer::end_div();

echo $OUTPUT->render(new \mod_ep\output\list_filters($listurl, ['search', 'page'], $search));

$total = ep_count_enrolled_students($context, $search);
$students = ep_get_enrolled_students($context, $search, null, $page * EP_LIST_PERPAGE, EP_LIST_PERPAGE);

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudents', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Suppléments en vigueur des seuls étudiants de la page.
[$insql, $inparams] = $DB->get_in_or_equal(array_keys($students), SQL_PARAMS_NAMED, 'u');
$current = [];
$records = $DB->get_records_select(
    'ep_supplement_issue',
    "epid = :epid AND replacedby IS NULL AND userid $insql",
    ['epid' => $ep->id] + $inparams,
    'timeissued DESC, id DESC'
);
foreach ($records as $issue) {
    $current[(int) $issue->userid] = $current[(int) $issue->userid] ?? $issue;
}

// Ce qui manque n'est calculé que pour la page affichée : le contenu complet du supplément est
// rassemblé pour chaque étudiant, et les bilans de la page sont chargés d'un coup (voir
// mod_ep\local\progress_cache) plutôt qu'étudiant par étudiant.
$missing = [];
\mod_ep\local\progress_cache::prime((int) $ep->id, array_keys($students));
try {
    foreach ($students as $student) {
        $missing[(int) $student->id] = ep_build_supplement($ep, $student, 'fr')->missing;
    }
} finally {
    \mod_ep\local\progress_cache::clear();
}

// L'en-tête « Informations manquantes » reprend le libellé de la notification d'émission refusée,
// débarrassé de sa variable.
$missinghead = get_string('supplementmissingheader', 'mod_ep');
$head = [
    get_string('student', 'mod_ep'),
    $missinghead,
    get_string('supplementcurrent', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];
$rows = [];
foreach ($students as $student) {
    $userid = (int) $student->id;
    $missingcell = $missing[$userid]
        ? html_writer::span(s(implode(', ', $missing[$userid])), 'text-danger')
        : html_writer::span('-', 'text-muted');

    $issuecell = html_writer::span(get_string('supplementnoissue', 'mod_ep'), 'text-muted');
    if (isset($current[$userid])) {
        $issue = $current[$userid];
        $issuecell = s(get_string('supplementnumber', 'mod_ep', $issue->number)) . html_writer::empty_tag('br')
            . userdate($issue->timeissued, get_string('strftimedatetimeshort', 'langconfig'))
            . ' (' . s(strtoupper($issue->lang)) . ')' . html_writer::empty_tag('br')
            . html_writer::link(
                new moodle_url('/mod/ep/supplement_file.php', ['id' => $cm->id, 'area' => 'issue', 'itemid' => $issue->id]),
                get_string('download')
            );
    }

    $actions = ep_render_actions([
        get_string('supplementpreview', 'mod_ep') => new moodle_url('/mod/ep/supplement.php', [
            'id' => $cm->id, 'userid' => $userid,
        ]),
    ]) . ep_supplement_issue_form(
        $listurl,
        ['action' => 'issue', 'userid' => $userid],
        get_string('supplementissue', 'mod_ep'),
        $langoptions,
        'form-inline mt-1'
    );

    $rows[] = [fullname($student), $missingcell, $issuecell, $actions];
}

echo ep_render_paged_table($head, $rows, ep_render_paging_bar($total, $page, new moodle_url($baseurl, ['search' => $search])));

echo $OUTPUT->footer();
