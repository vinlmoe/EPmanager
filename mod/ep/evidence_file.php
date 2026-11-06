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
 * Téléchargement d'un justificatif déposé par un étudiant à l'appui d'une demande d'EP. Comme
 * dans mod_stage, le contrôle d'accès est fait ici plutôt que par un callback pluginfile : le
 * droit dépend du crédit concerné (l'étudiant propriétaire, la DEVE, ou l'enseignant qui doit
 * statuer dessus).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$creditid = required_param('creditid', PARAM_INT);
$pathnamehash = required_param('pathnamehash', PARAM_ALPHANUM);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$credit = $DB->get_record('ep_credit', ['id' => $creditid, 'epid' => $ep->id], '*', MUST_EXIST);

$isowner = (int) $credit->userid === (int) $USER->id;
if (
    !$isowner && !has_capability('mod/ep:viewall', $context)
        && !ep_can_validate_credit($ep, $credit, $context)
) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('evidencefiles', 'mod_ep'));
}

// Le fichier est cherché parmi ceux du crédit plutôt que par son seul hachage : un hachage valide
// pointant vers un autre crédit (ou une autre zone de fichiers) ne doit rien renvoyer.
$files = ep_get_evidence_files($context, $credit->id);
$file = $files[$pathnamehash] ?? null;
if (!$file) {
    throw new moodle_exception('errorevidencemissing', 'mod_ep');
}

send_stored_file($file, 0, 0, true);
