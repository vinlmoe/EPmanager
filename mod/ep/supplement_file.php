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
 * Téléchargement des fichiers du supplément au diplôme : preuves d'un élément demandé par un
 * étudiant (area=proof), ou supplément émis (area=issue). Comme pour les justificatifs des EP
 * (evidence_file.php), le contrôle d'accès est fait ici plutôt que par un callback pluginfile :
 * le droit dépend de l'élément ou de l'émission concernés (l'étudiant lui-même, la DEVE, ou pour
 * les preuves un enseignant référent de l'étudiant).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$area = required_param('area', PARAM_ALPHA);
$itemid = required_param('itemid', PARAM_INT);
$pathnamehash = optional_param('pathnamehash', '', PARAM_ALPHANUM);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$file = null;
if ($area === 'proof') {
    // L'élément est relu dans cette instance : un identifiant d'une autre activité ne doit pas
    // ouvrir ses preuves à qui a des droits ici.
    $item = $DB->get_record('ep_supplement_item', ['id' => $itemid, 'epid' => $ep->id], '*', MUST_EXIST);
    if ((int) $item->userid !== (int) $USER->id) {
        $rights = ep_get_user_rights($ep, $context);
        if (!$rights->viewall && !in_array((int) $item->userid, $rights->referentids, true)) {
            throw new moodle_exception('nopermissions', 'error', '', get_string('supplementproofs', 'mod_ep'));
        }
    }
    // Le fichier est cherché parmi ceux de l'élément plutôt que par son seul hachage : un hachage
    // valide pointant vers un autre élément ou une autre zone (justificatif d'EP, supplément d'un
    // autre étudiant) ne doit rien renvoyer.
    $files = get_file_storage()->get_area_files(
        $context->id,
        'mod_ep',
        EP_SUPPLEMENT_EVIDENCE_FILEAREA,
        $item->id,
        'filename',
        false
    );
    foreach ($files as $candidate) {
        if ($pathnamehash !== '' && $candidate->get_pathnamehash() === $pathnamehash) {
            $file = $candidate;
            break;
        }
    }
} else if ($area === 'issue') {
    $issue = $DB->get_record('ep_supplement_issue', ['id' => $itemid, 'epid' => $ep->id], '*', MUST_EXIST);
    // Un supplément émis est un document personnel : l'étudiant et la DEVE seulement, pas les
    // référents, qui n'en ont pas l'usage.
    if ((int) $issue->userid !== (int) $USER->id && !has_capability('mod/ep:viewall', $context)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('supplementissues', 'mod_ep'));
    }
    $file = ep_get_supplement_issue_file($context, $issue->id);
} else {
    throw new moodle_exception('invalidparameter', 'debug');
}

if (!$file) {
    send_file_not_found();
}

send_stored_file($file, 0, 0, true);
