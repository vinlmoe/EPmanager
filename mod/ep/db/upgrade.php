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
 * Database upgrade steps for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Applique les évolutions de schéma successives depuis la version installée.
 *
 * Le plugin n'a pas encore connu de version publiée nécessitant une migration : la fonction est
 * là pour que la première évolution de schéma s'ajoute au bon endroit, plutôt que d'obliger à
 * réinstaller le plugin ce jour-là.
 *
 * @param int $oldversion Version actuellement installée.
 * @return bool
 */
function xmldb_ep_upgrade($oldversion) {
    return true;
}
