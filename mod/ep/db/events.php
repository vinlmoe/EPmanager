<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Observateurs de mod_ep : la fiche d'un EP est une activité mod_syllabus, dont les soumissions et
 * les décisions de la DEVE font avancer l'état de l'EP proposé.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    ['eventname' => '\mod_syllabus\event\version_submitted', 'callback' => '\mod_ep\observer::syllabus_version_submitted'],
    ['eventname' => '\mod_syllabus\event\version_reviewed', 'callback' => '\mod_ep\observer::syllabus_version_reviewed'],
];
