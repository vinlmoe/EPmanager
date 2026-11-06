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
 * Demande par l'étudiant d'ajout d'un élément à son supplément au diplôme, sa modification tant
 * qu'aucune décision n'a été prise, et son retrait. L'élément suit ensuite le circuit choisi par
 * la DEVE (voir ep_create_supplement_item()) et, une fois validé, figure en rubrique 6.1.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/ep/classes/form/supplement_item_form.php');

use mod_ep\form\supplement_item_form;

$id = required_param('id', PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:submit', $context);

$returnurl = new moodle_url('/mod/ep/supplement.php', ['id' => $cm->id]);
$baseurl = new moodle_url('/mod/ep/supplement_item.php', ['id' => $cm->id]);
$PAGE->set_url(new moodle_url($baseurl, $itemid ? ['itemid' => $itemid] : []));
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('supplement', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// L'étudiant ne manipule que ses propres éléments : la propriété est une condition de la requête
// elle-même, si bien qu'un identifiant d'un autre étudiant ou d'une autre instance n'ouvre rien.
$item = null;
if ($itemid) {
    $item = $DB->get_record('ep_supplement_item', ['id' => $itemid, 'epid' => $ep->id, 'userid' => $USER->id], '*', MUST_EXIST);
}

// Retrait tant que l'élément est en attente ; l'écriture conditionnelle de
// ep_cancel_supplement_item() refuse un élément tranché entre-temps.
if ($action === 'cancel' && $item) {
    require_sesskey();
    if (!ep_cancel_supplement_item($item, $USER->id, EP_ROLE_STUDENT)) {
        redirect($returnurl, get_string('supplementitemchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($returnurl, get_string('supplementitemcancelled', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Un élément tranché ne se modifie plus : la décision porte sur ce qui a été examiné.
if ($item && (int) $item->status !== EP_STATUS_PENDING) {
    redirect($returnurl, get_string('supplementitemchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
}

$categories = ep_get_supplement_categories($ep->id, true);
// Un élément demandé dans une catégorie depuis désactivée reste modifiable dans cette catégorie :
// la retirer de la liste ferait basculer l'élément sur une autre à l'enregistrement.
if ($item && !isset($categories[$item->categoryid])) {
    $existing = $DB->get_record('ep_supplement_category', ['id' => $item->categoryid, 'epid' => $ep->id]);
    if ($existing) {
        $categories[$existing->id] = $existing;
    }
}
if (empty($categories)) {
    // Sans catégorie active, aucune demande ne peut être rattachée : retour au supplément, qui
    // n'affiche alors pas le bouton de demande.
    redirect($returnurl, get_string('errornosupplementcategory', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
}

$evidence = (int) ($ep->supplementevidence ?? EP_EVIDENCE_REQUIRED);
$fileoptions = [
    'subdirs' => 0,
    'maxfiles' => 5,
    'maxbytes' => (int) $course->maxbytes,
    'accepted_types' => ['.pdf'],
];

$mform = new supplement_item_form(new moodle_url($baseurl, ['itemid' => $itemid]), [
    'categories' => $categories,
    'evidence' => $evidence,
    'fileoptions' => $fileoptions,
]);

$formdata = ['id' => $cm->id, 'itemid' => $itemid];
if ($evidence !== EP_EVIDENCE_NONE) {
    $draftitemid = file_get_submitted_draft_itemid('proofs');
    file_prepare_draft_area(
        $draftitemid,
        $context->id,
        'mod_ep',
        EP_SUPPLEMENT_EVIDENCE_FILEAREA,
        $item ? $item->id : null,
        $fileoptions
    );
    $formdata['proofs'] = $draftitemid;
}
if ($item) {
    $formdata += [
        'categoryid' => $item->categoryid,
        'title' => $item->title,
        'titleen' => $item->titleen,
        'description' => $item->description,
        'periodstart' => (int) $item->periodstart,
        'periodend' => (int) $item->periodend,
    ];
}
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // La catégorie soumise est revérifiée contre la liste proposée : une valeur forgée ne doit pas
    // rattacher l'élément à une catégorie désactivée ou d'une autre instance.
    if (!isset($categories[$data->categoryid])) {
        throw new moodle_exception('invalidrecord', 'error', $returnurl->out(false), 'ep_supplement_category');
    }
    // Un champ texte laissé vide peut manquer dans les données reçues : valeurs par défaut.
    $values = (object) [
        'title' => (string) $data->title,
        'titleen' => (string) ($data->titleen ?? ''),
        'description' => (string) ($data->description ?? ''),
        'periodstart' => (int) ($data->periodstart ?? 0),
        'periodend' => (int) ($data->periodend ?? 0),
    ];

    if ($item) {
        $item->categoryid = (int) $data->categoryid;
        $item->title = core_text::substr(trim($values->title), 0, 255);
        $item->titleen = core_text::substr(trim($values->titleen), 0, 255);
        $item->description = $values->description;
        $item->periodstart = $values->periodstart;
        $item->periodend = $values->periodend;
        // L'élément a pu être tranché ou retiré pendant la saisie : rien n'est alors réécrit, pas
        // même les preuves, qui doivent rester celles sur lesquelles on a statué.
        if (!ep_update_supplement_item($item)) {
            redirect($returnurl, get_string('supplementitemchanged', 'mod_ep'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $savedid = (int) $item->id;
    } else {
        $savedid = ep_create_supplement_item($ep, $USER->id, $categories[$data->categoryid], $values);
    }

    if ($evidence !== EP_EVIDENCE_NONE) {
        file_save_draft_area_files(
            $data->proofs,
            $context->id,
            'mod_ep',
            EP_SUPPLEMENT_EVIDENCE_FILEAREA,
            $savedid,
            $fileoptions
        );
    }

    redirect($returnurl, get_string('supplementitemsaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($item ? 'supplementitemedit' : 'supplementitemrequest', 'mod_ep'));
echo html_writer::link($returnurl, get_string('back'));
$mform->display();
echo $OUTPUT->footer();
