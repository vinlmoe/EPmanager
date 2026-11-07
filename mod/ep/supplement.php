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
 * Supplément au diplôme d'un étudiant : ce qui y figurera (enseignements personnalisés validés,
 * éléments ajoutés et validés), ce qui manque pour l'émettre, un aperçu PDF non certifié, les
 * éléments qu'il a demandé d'ajouter et les suppléments déjà émis. L'étudiant y consulte le sien ;
 * la DEVE et ses enseignants référents celui d'un étudiant donné (paramètre userid).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\local\supplement_view;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
// Langue du supplément (et non de l'interface) : par défaut celle de l'utilisateur si le
// supplément existe dans cette langue, le français sinon.
$lang = optional_param('lang', current_language() === 'en' ? 'en' : 'fr', PARAM_ALPHA);
$lang = $lang === 'en' ? 'en' : 'fr';

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Sans userid (ou avec le sien), l'étudiant consulte son propre supplément ; sinon il faut voir
// tous les dossiers (DEVE) ou être référent de cet étudiant précis.
$isself = !$userid || (int) $userid === (int) $USER->id;
if ($isself) {
    require_capability('mod/ep:submit', $context);
    $userid = (int) $USER->id;
} else {
    $rights = ep_get_user_rights($ep, $context);
    if (!$rights->viewall && !in_array((int) $userid, $rights->referentids, true)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('supplement', 'mod_ep'));
    }
}
$student = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

$pageurl = new moodle_url('/mod/ep/supplement.php', ['id' => $cm->id] + ($isself ? [] : ['userid' => $userid]));
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('supplement', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$data = ep_build_supplement($ep, $student, $lang);

// Aperçu : produit à la volée, jamais conservé, sans numéro ni code et marqué « non certifié » ;
// seule une émission par la DEVE produit un document opposable.
if ($action === 'preview') {
    $pdf = new \mod_ep\pdf\supplement_pdf($ep, $context, $lang);
    $pdf->add_supplement($data, '', '', time(), true);
    $pdf->Output(clean_filename(get_string('supplement', 'mod_ep') . ' - ' . fullname($student) . '.pdf'), 'D');
    exit;
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(
    $isself ? get_string('mysupplement', 'mod_ep') : get_string('supplement', 'mod_ep') . ' - ' . fullname($student)
);
echo html_writer::link(new moodle_url('/mod/ep/view.php', ['id' => $cm->id]), get_string('back'));
echo $OUTPUT->box(format_text(get_string('supplement_help', 'mod_ep'), FORMAT_PLAIN), 'generalbox mt-3');

// Aperçu : formulaire GET ordinaire (langue + bouton), sans JavaScript.
$languages = get_string_manager()->get_list_of_languages();
$previewform = html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out_omit_querystring(),
    'class' => 'form-inline mb-3']);
$previewform .= html_writer::input_hidden_params(new moodle_url($pageurl, ['action' => 'preview']));
$previewform .= html_writer::label(get_string('supplementlanguage', 'mod_ep'), 'ep-supplement-lang', true, ['class' => 'mr-2']);
$previewform .= html_writer::select(
    ['fr' => $languages['fr'] ?? 'fr', 'en' => $languages['en'] ?? 'en'],
    'lang',
    $lang,
    false,
    ['id' => 'ep-supplement-lang', 'class' => 'custom-select mr-2']
);
$previewform .= html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary',
    'value' => get_string('supplementpreview', 'mod_ep')]);
$previewform .= html_writer::end_tag('form');
echo $previewform;

if ($data->missing) {
    echo $OUTPUT->notification(get_string('supplementmissing', 'mod_ep', implode(', ', $data->missing)), 'warning');
}

// Rubrique 4.3 : les EP validés des types qui figurent au supplément, tels qu'ils seront imprimés.
echo $OUTPUT->heading(get_string('supplementprogrammedetails', 'mod_ep'), 3);
if (empty($data->credits)) {
    echo $OUTPUT->notification(get_string('nocredits', 'mod_ep'), 'info');
} else {
    $rows = [];
    foreach ($data->credits as $credit) {
        $rows[] = [
            format_string($credit->name),
            s($credit->code !== '' ? $credit->code : '-'),
            ep_studyyear_label($credit->studyyear),
            ep_format_ects($credit->ects),
            $credit->grade !== null
                ? ep_format_ects($credit->grade) . ' / ' . ep_format_ects($credit->grademax)
                : get_string('supplementvalidatedgrade', 'mod_ep'),
        ];
    }
    echo ep_render_paged_table([
        get_string('creditname', 'mod_ep'),
        get_string('activitycode', 'mod_ep'),
        get_string('studyyear', 'mod_ep'),
        get_string('ects', 'mod_ep'),
        get_string('grade', 'mod_ep'),
    ], $rows);
}
echo html_writer::tag(
    'p',
    get_string('supplementtotalects', 'mod_ep', ep_format_ects($data->totalects)),
    ['class' => 'font-weight-bold']
);

// Éléments ajoutés : tous ceux de l'étudiant, quel que soit leur statut, pour qu'il suive ses
// demandes et lise le motif d'un refus ; seuls les validés figureront au supplément.
echo $OUTPUT->heading(get_string('supplementitems', 'mod_ep'), 3);
$categories = ep_get_supplement_categories($ep->id);
$items = ep_get_supplement_items($ep->id, ['userid' => $student->id]);
if ($items) {
    $head = [
        get_string('supplementcategory', 'mod_ep'),
        get_string('supplementitemtitle', 'mod_ep'),
        get_string('supplementperiod', 'mod_ep'),
        get_string('status', 'mod_ep'),
        get_string('validatorcomment', 'mod_ep'),
        get_string('supplementproofs', 'mod_ep'),
    ];
    if ($isself) {
        $head[] = get_string('actions', 'mod_ep');
    }
    $rows = [];
    foreach ($items as $item) {
        $row = [
            supplement_view::category($categories, $item),
            supplement_view::title($item),
            supplement_view::period($item),
            supplement_view::status($item),
            trim((string) $item->validatorcomment) !== '' ? format_text($item->validatorcomment, FORMAT_PLAIN) : '-',
            supplement_view::proofs($cm, $context, $item),
        ];
        if ($isself) {
            // Modifier et retirer n'ont de sens que tant que personne n'a statué.
            $pending = (int) $item->status === EP_STATUS_PENDING;
            $itemurl = new moodle_url('/mod/ep/supplement_item.php', ['id' => $cm->id, 'itemid' => $item->id]);
            $row[] = ep_render_actions([
                get_string('supplementitemedit', 'mod_ep') => $pending && (int) $item->step === 0 ? $itemurl : null,
                get_string('cancelrequest', 'mod_ep') => $pending
                    ? new moodle_url($itemurl, ['action' => 'cancel', 'sesskey' => sesskey()]) : null,
            ]);
        }
        $rows[] = $row;
    }
    echo ep_render_paged_table($head, $rows);
}
// Sans catégorie active, une demande ne pourrait être rattachée à rien : pas de bouton.
if ($isself && ep_get_supplement_categories($ep->id, true)) {
    echo $OUTPUT->render(new single_button(
        new moodle_url('/mod/ep/supplement_item.php', ['id' => $cm->id]),
        get_string('supplementitemrequest', 'mod_ep'),
        'get',
        single_button::BUTTON_PRIMARY
    ));
}

// Suppléments émis : le plus récent est en vigueur, les précédents restent téléchargeables (un
// document remis à un tiers doit pouvoir être retrouvé tel quel).
echo $OUTPUT->heading(get_string('supplementissues', 'mod_ep'), 3);
$issues = ep_get_supplement_issues($ep->id, $student->id);
if (empty($issues)) {
    echo $OUTPUT->notification(get_string('supplementnoissue', 'mod_ep'), 'info');
} else {
    $rows = [];
    foreach ($issues as $issue) {
        $current = empty($issue->replacedby);
        $rows[] = [
            html_writer::link(
                new moodle_url('/mod/ep/supplement_file.php', ['id' => $cm->id, 'area' => 'issue', 'itemid' => $issue->id]),
                s($issue->number)
            ),
            userdate($issue->timeissued, get_string('strftimedate', 'langconfig')),
            s($languages[$issue->lang] ?? $issue->lang),
            html_writer::span(
                get_string($current ? 'supplementcurrent' : 'supplementreplaced', 'mod_ep'),
                'badge ' . ($current ? 'badge-success' : 'badge-secondary')
            ) . (empty($issue->seal)
                ? ' ' . html_writer::span(get_string('supplementunsealed', 'mod_ep'), 'badge badge-warning') : ''),
        ];
    }
    echo ep_render_paged_table([
        get_string('supplementnumberheader', 'mod_ep'),
        get_string('date'),
        get_string('supplementlanguage', 'mod_ep'),
        get_string('status', 'mod_ep'),
    ], $rows);
}

echo $OUTPUT->footer();
