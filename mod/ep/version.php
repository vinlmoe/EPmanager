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
 * Version details for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_ep';
$plugin->version   = 2026090600;
$plugin->requires  = 2022041900; // Moodle 4.0+.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.2.0';
$plugin->dependencies = [
    // Les EP de type « stage » sont attribués automatiquement à partir des stages complémentaires
    // (EP) validés par la DEVE dans l'activité « Gestion des stages » du même cours : leur
    // attribution lit directement les tables de mod_stage, tout comme l'identification des
    // enseignants référents (stage_entry_teacher), qui n'est pas redemandée ici.
    'mod_stage' => 2026090300,
];
