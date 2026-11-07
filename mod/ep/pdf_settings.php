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
 * Réglages des éditions PDF et des propositions (DEVE) : en-tête, pied de page et logo des fiches
 * et du livret, publication directe des mises à jour de syllabus par les responsables d'EP.
 *
 * Ces réglages sont propres à l'instance plutôt qu'au site : deux activités du même site peuvent
 * relever de formations différentes, chacune avec son en-tête.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_ep\form\pdf_settings_form;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$returnurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/ep/pdf_settings.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('pdfsettings', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Le logo est édité dans une zone de brouillon de l'utilisateur, recopiée dans celle du module à
// l'enregistrement : l'abandon du formulaire ne touche pas au logo en place.
$draftitemid = file_get_submitted_draft_itemid('pdflogo');
file_prepare_draft_area($draftitemid, $context->id, 'mod_ep', EP_PDFLOGO_FILEAREA, 0, pdf_settings_form::logo_options());

$mform = new pdf_settings_form($pageurl);
$mform->set_data([
    'id' => $cm->id,
    'pdfheader' => (string) $ep->pdfheader,
    'pdffooter' => (string) $ep->pdffooter,
    'pdflogo' => $draftitemid,
    'proposalsdirectpublish' => (int) $ep->proposalsdirectpublish,
]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // La clé de session est vérifiée par get_data() (moodleform) avant tout enregistrement.
    $DB->update_record('ep', (object) [
        'id' => $ep->id,
        'pdfheader' => trim((string) $data->pdfheader),
        'pdffooter' => trim((string) $data->pdffooter),
        'proposalsdirectpublish' => empty($data->proposalsdirectpublish) ? 0 : 1,
        'timemodified' => time(),
    ]);
    file_save_draft_area_files(
        $data->pdflogo,
        $context->id,
        'mod_ep',
        EP_PDFLOGO_FILEAREA,
        0,
        pdf_settings_form::logo_options()
    );
    redirect($pageurl, get_string('pdfsettingssaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pdfsettings', 'mod_ep'));
echo html_writer::link($returnurl, get_string('back'));
echo html_writer::tag('p', get_string('pdfsettings_desc', 'mod_ep'), ['class' => 'text-muted mt-2']);

$mform->display();

// L'aperçu se fait sur le livret réel : c'est le moyen le plus sûr de juger de l'en-tête et du
// logo une fois imprimés.
echo html_writer::link(
    new moodle_url('/mod/ep/booklet.php', ['id' => $cm->id]),
    get_string('booklet', 'mod_ep'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();
