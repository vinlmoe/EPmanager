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
 * Version details for mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_epsynthesis';
$plugin->version   = 2026090602;
$plugin->requires  = 2022041900; // Moodle 4.0+.
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.2.0';
$plugin->dependencies = [
    // La synthèse est lue dans les activités mod_ep liées. Elle y écrit une seule chose : les EP
    // académiques partagés qu'elle définit, enregistrés dans le catalogue de mod_ep (ep_activity)
    // et rattachés à cette activité, parce que des étudiants de plusieurs promotions s'y
    // inscrivent (voir ep_save_shared_activity()).
    'mod_ep' => 2026090602,
];
