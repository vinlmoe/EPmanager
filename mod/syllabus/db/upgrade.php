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
 * Mises à niveau de mod_syllabus.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Mise à niveau.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_syllabus_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026112100) {
        // Niveau de contribution d'un module aux compétences du référentiel.
        $table = new xmldb_table('syllabus_competency');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('syllabusid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('level', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('syllabusid', XMLDB_KEY_FOREIGN, ['syllabusid'], 'syllabus', ['id']);
        $table->add_index('syllabusid-competencyid', XMLDB_INDEX_UNIQUE, ['syllabusid', 'competencyid']);
        $table->add_index('competencyid', XMLDB_INDEX_NOTUNIQUE, ['competencyid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026112100, 'syllabus');
    }

    return true;
}
