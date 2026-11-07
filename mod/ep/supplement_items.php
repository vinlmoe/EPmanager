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
 * File d'examen des éléments que les étudiants demandent à ajouter à leur supplément au diplôme :
 * la DEVE y voit toutes les demandes en attente, un enseignant celles de ses étudiants arrivées à
 * l'étape « référent ». Chaque demande se valide ou se refuse sur place, avec un commentaire. La
 * DEVE dispose en outre, en dessous, de la liste de tous les éléments, filtrable par statut.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

use mod_ep\local\supplement_view;

$id = required_param('id', PARAM_INT);
// Statut du filtre de la liste complète : chaîne vide pour « tous », sinon l'un des statuts.
$status = optional_param('status', '', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$qpage = optional_param('qpage', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$rights = ep_get_user_rights($ep, $context);
if (!$rights->validatedeve && !($rights->evaluateteacher && $rights->referentids)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('supplementitemstodecide', 'mod_ep'));
}
$role = $rights->validatedeve ? EP_ROLE_DEVE : EP_ROLE_REFERENT;

$statusoptions = ep_status_options();
if ($status !== '' && !array_key_exists((int) $status, $statusoptions)) {
    $status = '';
}

$baseurl = new moodle_url('/mod/ep/supplement_items.php', ['id' => $cm->id]);
$filterurl = new moodle_url($baseurl, array_filter(['status' => $status, 'search' => $search], fn($v) => $v !== ''));
$pageurl = $page ? new moodle_url($filterurl, ['page' => $page]) : $filterurl;
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('supplementitemstodecide', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Décision sur un élément. L'élément est relu dans cette instance et le droit de statuer
// réévalué sur son état courant : le formulaire affiché a pu être dépassé par une autre décision.
if (data_submitted()) {
    require_sesskey();
    $itemid = required_param('itemid', PARAM_INT);
    $comment = optional_param('validatorcomment', '', PARAM_TEXT);
    $validate = optional_param('validate', '', PARAM_TEXT) !== '';
    $reject = optional_param('reject', '', PARAM_TEXT) !== '';
    $item = $DB->get_record('ep_supplement_item', ['id' => $itemid, 'epid' => $ep->id], '*', MUST_EXIST);

    if ($validate === $reject) {
        // Ni l'un ni l'autre (ou les deux, forgé) : aucune décision lisible.
        redirect($pageurl);
    }
    // Tranché, retiré, ou passé à une étape qui n'est plus celle de l'utilisateur (un référent qui
    // a validé son étape avant la DEVE) depuis l'affichage : il en est averti comme d'un
    // changement. Un élément hors de son périmètre tombe ici aussi, sans rien révéler de plus.
    if (
        !ep_rights_can_decide_supplement_item($rights, $item)
            || !ep_decide_supplement_item($ep, $item, $USER->id, $validate, $comment, $role)
    ) {
        redirect($pageurl, get_string('supplementitemchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($pageurl, get_string('supplementitemdecided', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$categories = ep_get_supplement_categories($ep->id);

// Demandes qui attendent la décision de l'utilisateur. Pour un référent, le périmètre (ses
// étudiants) est posé dans la requête, l'étape attendue est vérifiée ligne à ligne.
// La file est paginée en SQL : pour un référent, l'étape attendue fait partie du filtre.
$filters = ['status' => EP_STATUS_PENDING, 'search' => $search];
if (!$rights->validatedeve) {
    $filters['userids'] = $rights->referentids;
    $filters['pendingrole'] = EP_ROLE_REFERENT;
}
$pendingtotal = ep_count_supplement_items($ep->id, $filters);
$pending = array_filter(
    ep_get_supplement_items($ep->id, $filters, $qpage * EP_LIST_PERPAGE, EP_LIST_PERPAGE),
    function ($item) use ($rights) {
        return ep_rights_can_decide_supplement_item($rights, $item);
    }
);

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('supplementitemstodecide', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/view.php', ['id' => $cm->id]), get_string('back'));
// Recherche par nom d'étudiant, sur la file comme sur la liste complète.
echo $OUTPUT->render(new \mod_ep\output\list_filters($filterurl, ['search', 'page', 'qpage'], $search));

if (empty($pending)) {
    echo $OUTPUT->notification(get_string('nopendingsupplementitems', 'mod_ep'), 'info');
} else {
    $students = ep_get_credit_users($pending);
    supplement_view::preload_proofs($context, array_keys($pending));
    $rows = [];
    foreach ($pending as $item) {
        $student = $students[$item->userid] ?? null;
        $commentid = 'ep-supplement-comment-' . $item->id;
        // Un petit formulaire par ligne : la décision porte sur un seul élément, le commentaire
        // n'a de sens que pour lui.
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
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
            $student ? html_writer::link(
                new moodle_url('/mod/ep/supplement.php', ['id' => $cm->id, 'userid' => $item->userid]),
                fullname($student)
            ) : '-',
            supplement_view::category($categories, $item),
            supplement_view::title($item) . ($rights->validatedeve ? supplement_view::status($item) : ''),
            supplement_view::period($item),
            supplement_view::proofs($cm, $context, $item),
            userdate($item->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $form,
        ];
    }
    echo ep_render_paged_table([
        get_string('student', 'mod_ep'),
        get_string('supplementcategory', 'mod_ep'),
        get_string('supplementitemtitle', 'mod_ep'),
        get_string('supplementperiod', 'mod_ep'),
        get_string('supplementproofs', 'mod_ep'),
        get_string('submittedon', 'mod_ep'),
        get_string('decision', 'mod_ep'),
    ], $rows, ep_render_paging_bar($pendingtotal, $qpage, $pageurl, EP_LIST_PERPAGE, 'qpage'));
}

// Pour la DEVE seulement : l'ensemble des éléments de l'instance, quel que soit leur statut, pour
// retrouver une décision passée ou ouvrir le supplément d'un étudiant.
if ($rights->validatedeve) {
    echo $OUTPUT->heading(get_string('supplementitems', 'mod_ep'), 3, 'mt-4');
    echo $OUTPUT->single_select(
        $baseurl,
        'status',
        ['' => get_string('allstatuses', 'mod_ep')] + $statusoptions,
        $status,
        null,
        'ep-supplement-status'
    );

    $allfilters = ($status === '' ? [] : ['status' => (int) $status]) + ['search' => $search];
    $total = ep_count_supplement_items($ep->id, $allfilters);
    $all = ep_get_supplement_items($ep->id, $allfilters, $page * EP_LIST_PERPAGE, EP_LIST_PERPAGE);
    if (empty($all)) {
        echo $OUTPUT->notification(get_string('supplementnoitems', 'mod_ep'), 'info');
    } else {
        $students = ep_get_credit_users($all);
        supplement_view::preload_proofs($context, array_keys($all));
        $rows = [];
        foreach ($all as $item) {
            $student = $students[$item->userid] ?? null;
            $rows[] = [
                $student ? html_writer::link(
                    new moodle_url('/mod/ep/supplement.php', ['id' => $cm->id, 'userid' => $item->userid]),
                    fullname($student)
                ) : '-',
                supplement_view::category($categories, $item),
                supplement_view::title($item),
                supplement_view::period($item),
                supplement_view::status($item),
                trim((string) $item->validatorcomment) !== '' ? format_text($item->validatorcomment, FORMAT_PLAIN) : '-',
                supplement_view::proofs($cm, $context, $item),
            ];
        }
        echo ep_render_paged_table([
            get_string('student', 'mod_ep'),
            get_string('supplementcategory', 'mod_ep'),
            get_string('supplementitemtitle', 'mod_ep'),
            get_string('supplementperiod', 'mod_ep'),
            get_string('status', 'mod_ep'),
            get_string('validatorcomment', 'mod_ep'),
            get_string('supplementproofs', 'mod_ep'),
        ], $rows, ep_render_paging_bar($total, $page, $filterurl));
    }
}

echo $OUTPUT->footer();
