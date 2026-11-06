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

/**
 * Applique les évolutions de schéma successives depuis la version installée.
 *
 * @param int $oldversion Version actuellement installée.
 * @return bool
 */
function xmldb_ep_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026110600) {
        // Index des listes paginées en base : crédits d'une instance par statut (files de
        // validation), inscriptions d'un EP du catalogue par statut (places prises), crédits
        // d'un étudiant toutes instances confondues (RGPD, synthèse).
        $table = new xmldb_table('ep_credit');
        $indexes = [
            new xmldb_index('epid-status', XMLDB_INDEX_NOTUNIQUE, ['epid', 'status']),
            new xmldb_index('activityid-status', XMLDB_INDEX_NOTUNIQUE, ['activityid', 'status']),
            new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']),
        ];
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        upgrade_mod_savepoint(true, 2026110600, 'ep');
    }

    return true;
}
