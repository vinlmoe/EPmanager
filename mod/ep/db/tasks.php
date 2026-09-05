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
 * Scheduled tasks for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        // Une fois par nuit : les stages complémentaires (EP) sont validés par la DEVE au fil de
        // l'eau, et les pages du module resynchronisent déjà à l'affichage (voir
        // ep_sync_stage_credits_if_due()). Cette tâche garantit que le décompte est juste même
        // pour une instance que personne n'ouvre, et rattrape les stages dévalidés.
        'classname' => 'mod_ep\task\sync_stage_credits',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '5',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
