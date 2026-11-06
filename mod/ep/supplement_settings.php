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
 * Paramétrage du supplément au diplôme (DEVE) : circuit et preuves des éléments ajoutés par les
 * étudiants, préfixe des numéros, champ de la date de naissance, cachet, textes des rubriques
 * fixes en français et en anglais ; sous le formulaire, les catégories d'éléments.
 *
 * Les catégories sont gérées sur la même page parce qu'elles relèvent du même paramétrage, fait
 * une fois par la même personne. Une catégorie qui porte des éléments ne se supprime pas : elle
 * se désactive, sans quoi ces éléments perdraient la rubrique sous laquelle ils sont imprimés.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_ep\form\supplement_category_form;
use mod_ep\form\supplement_settings_form;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$categoryid = optional_param('categoryid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$returnurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/ep/supplement_settings.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('supplementsettings', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Les actions sur une catégorie : la catégorie est relue avec l'instance en condition, pour
// qu'un identifiant d'une autre instance ne puisse pas être manipulé depuis cette page.
$category = null;
if ($categoryid && in_array($action, ['editcategory', 'toggle', 'delete'], true)) {
    $category = $DB->get_record('ep_supplement_category', ['id' => $categoryid, 'epid' => $ep->id], '*', MUST_EXIST);
}

if ($action === 'toggle' && $category) {
    require_sesskey();
    $category->enabled = $category->enabled ? 0 : 1;
    ep_save_supplement_category($ep->id, $category);
    $message = get_string($category->enabled ? 'supplementcategoryenabled' : 'supplementcategorydisabled', 'mod_ep');
    redirect($pageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'delete' && $category) {
    // Une catégorie qui porte des éléments, même retirés ou refusés, reste en base : ces
    // éléments y sont rattachés et le transfert d'un étudiant s'appuie sur son libellé.
    if ($DB->record_exists('ep_supplement_item', ['categoryid' => $category->id])) {
        redirect($pageurl);
    }
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        $DB->delete_records('ep_supplement_category', ['id' => $category->id, 'epid' => $ep->id]);
        redirect($pageurl, get_string('supplementcategorydeleted', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $confirmurl = new moodle_url($pageurl, [
        'action' => 'delete', 'categoryid' => $category->id, 'confirm' => 1, 'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('supplementcategories', 'mod_ep'));
    echo $OUTPUT->confirm(
        get_string('confirmdeletesupplementcategory', 'mod_ep', format_string($category->name)),
        new single_button($confirmurl, get_string('delete'), 'post', single_button::BUTTON_DANGER),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Création (sans identifiant) ou modification d'une catégorie, sur une page à part : le
// formulaire principal est long, et l'y imbriquer obligerait à le valider en entier.
if ($action === 'editcategory') {
    $editurl = new moodle_url($pageurl, ['action' => 'editcategory', 'categoryid' => $categoryid]);
    $PAGE->set_url($editurl);
    $catform = new supplement_category_form($editurl);
    $defaults = ['id' => $cm->id, 'categoryid' => $categoryid, 'action' => 'editcategory', 'enabled' => 1];
    if ($category) {
        $defaults += [
            'name' => $category->name,
            'nameen' => $category->nameen,
            'description' => (string) $category->description,
            'sortorder' => (int) $category->sortorder,
        ];
        $defaults['enabled'] = (int) $category->enabled;
    }
    $catform->set_data($defaults);

    if ($catform->is_cancelled()) {
        redirect($pageurl);
    } else if ($data = $catform->get_data()) {
        // La clé de session est vérifiée par get_data() (moodleform) avant tout enregistrement.
        ep_save_supplement_category($ep->id, (object) [
            'id' => $category ? $category->id : 0,
            'name' => $data->name,
            'nameen' => $data->nameen,
            'description' => trim((string) $data->description),
            'enabled' => $data->enabled,
            'sortorder' => $data->sortorder,
        ]);
        redirect($pageurl, get_string('supplementcategorysaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string($category ? 'supplementcategoryedit' : 'supplementcategoryadd', 'mod_ep'));
    echo html_writer::link($pageurl, get_string('back'));
    $catform->display();
    echo $OUTPUT->footer();
    exit;
}

// Le cachet est édité dans une zone de brouillon de l'utilisateur, recopiée dans celle du module
// à l'enregistrement : l'abandon du formulaire ne touche pas au cachet en place.
$draftitemid = file_get_submitted_draft_itemid('supplementstamp');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'mod_ep',
    EP_SUPPLEMENT_STAMP_FILEAREA,
    0,
    supplement_settings_form::stamp_options()
);

$flowoptions = supplement_settings_form::flow_options();
// Un circuit enregistré sous une autre écriture (« deve,referent », espaces) est ramené à sa
// forme canonique, pour que la liste affiche l'option effectivement appliquée.
$currentflow = implode(',', ep_normalise_flow((string) $ep->supplementflow, EP_ACQ_DECLARATION));

$defaults = [
    'id' => $cm->id,
    'supplementflow' => array_key_exists($currentflow, $flowoptions) ? $currentflow : EP_ROLE_DEVE,
    'supplementevidence' => (int) $ep->supplementevidence,
    'supplementprefix' => (string) $ep->supplementprefix,
    'birthdatefield' => (string) $ep->birthdatefield,
    'supplementstamp' => $draftitemid,
];
foreach (supplement_settings_form::LANGS as $lang) {
    foreach (ep_get_supplement_texts($ep->id, $lang) as $key => $text) {
        $defaults['text_' . $lang . '_' . $key] = $text;
    }
}

$mform = new supplement_settings_form($pageurl);
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // La clé de session est vérifiée par get_data() (moodleform) avant tout enregistrement. Les
    // valeurs des listes sont recontrôlées : une valeur forgée hors des options serait sinon
    // enregistrée telle quelle.
    $evidence = (int) $data->supplementevidence;
    $birthfields = supplement_settings_form::birthdate_field_options();
    $transaction = $DB->start_delegated_transaction();
    $DB->update_record('ep', (object) [
        'id' => $ep->id,
        'supplementflow' => array_key_exists($data->supplementflow, $flowoptions) ? $data->supplementflow : EP_ROLE_DEVE,
        'supplementevidence' => array_key_exists($evidence, ep_evidence_options()) ? $evidence : EP_EVIDENCE_REQUIRED,
        'supplementprefix' => core_text::substr(trim((string) $data->supplementprefix), 0, 20),
        'birthdatefield' => array_key_exists($data->birthdatefield, $birthfields) ? $data->birthdatefield : '',
        'timemodified' => time(),
    ]);
    foreach (supplement_settings_form::LANGS as $lang) {
        foreach (array_keys(ep_supplement_sections()) as $key) {
            ep_set_supplement_text($ep->id, $key, $lang, (string) ($data->{'text_' . $lang . '_' . $key} ?? ''));
        }
    }
    $transaction->allow_commit();
    file_save_draft_area_files(
        $data->supplementstamp,
        $context->id,
        'mod_ep',
        EP_SUPPLEMENT_STAMP_FILEAREA,
        0,
        supplement_settings_form::stamp_options()
    );
    redirect($pageurl, get_string('supplementsettingssaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('supplementsettings', 'mod_ep'), 'supplement', 'mod_ep');
echo html_writer::link($returnurl, get_string('back'));
echo html_writer::tag('p', get_string('supplementsettings_desc', 'mod_ep'), ['class' => 'text-muted mt-2']);

$mform->display();

// Catégories d'éléments que l'étudiant peut demander à faire figurer au supplément (rubrique 6.1).
echo $OUTPUT->heading(get_string('supplementcategories', 'mod_ep'), 3, 'mt-5');
echo html_writer::link(
    new moodle_url($pageurl, ['action' => 'editcategory']),
    get_string('supplementcategoryadd', 'mod_ep'),
    ['class' => 'btn btn-primary d-block mt-2 mb-3', 'style' => 'width:fit-content']
);

$categories = ep_get_supplement_categories($ep->id);
$used = [];
if ($categories) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($categories));
    $used = array_flip($DB->get_fieldset_select('ep_supplement_item', 'DISTINCT categoryid', "categoryid $insql", $inparams));
}

$head = [
    get_string('typename', 'mod_ep'),
    get_string('supplementcategorynameen', 'mod_ep'),
    get_string('typeinstruction', 'mod_ep'),
    get_string('enabled', 'mod_ep'),
    get_string('sortorder', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];
$rows = [];
foreach ($categories as $cat) {
    $actionparams = ['categoryid' => $cat->id, 'sesskey' => sesskey()];
    $actions = ep_render_actions([
        get_string('edit') => new moodle_url($pageurl, ['action' => 'editcategory', 'categoryid' => $cat->id]),
        get_string($cat->enabled ? 'disable' : 'enable') => new moodle_url($pageurl, $actionparams + ['action' => 'toggle']),
    ]);
    if (!isset($used[$cat->id])) {
        $actions .= ep_render_actions([
            get_string('delete') => new moodle_url($pageurl, ['action' => 'delete', 'categoryid' => $cat->id]),
        ], 'btn btn-sm btn-outline-danger mr-1 mb-1', '');
    }
    $rows[] = [
        format_string($cat->name),
        $cat->nameen !== '' ? s($cat->nameen) : '-',
        trim((string) $cat->description) !== '' ? nl2br(s($cat->description)) : '-',
        $cat->enabled ? get_string('yes') : get_string('no'),
        (int) $cat->sortorder,
        $actions,
    ];
}
echo ep_render_paged_table($head, $rows);

echo $OUTPUT->footer();
