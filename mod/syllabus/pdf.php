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
 * Fiche du module en PDF : la version publiée, ou une autre version pour ceux qui rédigent.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

$id = required_param('id', PARAM_INT);
$versionid = optional_param('versionid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('syllabus', $id, 0, false, MUST_EXIST);
require_login($cm->course, true, $cm);
[$cm, $course, $syllabus, $context, $rights] = syllabus_page_init($cm, '/mod/syllabus/pdf.php');

$published = syllabus_get_published((int) $syllabus->id);
$version = $published;
if ($versionid && (!$published || (int) $published->id !== $versionid)) {
    // Une version non publiée n'est lisible que de ceux qui rédigent ou relisent.
    if (!$rights->canedit && !$rights->canmanage) {
        throw new required_capability_exception($context, 'mod/syllabus:edit', 'nopermissions', '');
    }
    $version = $DB->get_record('syllabus_version', ['id' => $versionid, 'syllabusid' => $syllabus->id], '*', MUST_EXIST);
}

$pdf = new \mod_syllabus\pdf\fiche_pdf($syllabus, $version, format_string($course->fullname, true, ['escape' => false]));
$filename = clean_filename(($syllabus->code !== '' ? $syllabus->code : $course->shortname) . '-syllabus.pdf');
$pdf->Output($filename, 'I');
