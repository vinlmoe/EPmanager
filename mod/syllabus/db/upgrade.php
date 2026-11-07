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

    if ($oldversion < 2026112200) {
        // Référentiel de compétences propre au module, tenu par la DEVE dans le suivi des modules,
        // au lieu de celui de l'administration du site.
        $table = new xmldb_table('syllabus_framework');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('idnumber', XMLDB_INDEX_UNIQUE, ['idnumber']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $table = new xmldb_table('syllabus_comp');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('frameworkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('description', XMLDB_TYPE_TEXT);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('frameworkid', XMLDB_KEY_FOREIGN, ['frameworkid'], 'syllabus_framework', ['id']);
        $table->add_index('frameworkid-idnumber', XMLDB_INDEX_UNIQUE, ['frameworkid', 'idnumber']);
        $table->add_index('parentid', XMLDB_INDEX_NOTUNIQUE, ['parentid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $table = new xmldb_table('syllabus_complink');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('syllabusid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('compid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('level', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('compid', XMLDB_KEY_FOREIGN, ['compid'], 'syllabus_comp', ['id']);
        $table->add_index('courseid-syllabusid-compid', XMLDB_INDEX_UNIQUE, ['courseid', 'syllabusid', 'compid']);
        $table->add_index('syllabusid', XMLDB_INDEX_NOTUNIQUE, ['syllabusid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Les liens de la version 1.1.0 visaient le référentiel de Moodle : les référentiels qu'ils
        // utilisent sont copiés (arbre et codes compris), puis les liens repris avec leur niveau.
        $old = new xmldb_table('syllabus_competency');
        if ($dbman->table_exists($old)) {
            $now = time();
            $rows = $DB->get_records_sql('SELECT sc.id, sc.syllabusid, sc.competencyid, sc.level, s.course,
                                                 c.competencyframeworkid
                                            FROM {syllabus_competency} sc
                                            JOIN {syllabus} s ON s.id = sc.syllabusid
                                            JOIN {competency} c ON c.id = sc.competencyid');
            $map = [];
            foreach (array_unique(array_map(fn($r) => (int) $r->competencyframeworkid, $rows)) as $coreframeworkid) {
                $core = $DB->get_record('competency_framework', ['id' => $coreframeworkid]);
                $idnumber = core_text::substr($core->idnumber !== '' ? $core->idnumber : 'FW' . $core->id, 0, 100);
                while ($DB->record_exists('syllabus_framework', ['idnumber' => $idnumber])) {
                    $idnumber .= '+';
                }
                $frameworkid = $DB->insert_record('syllabus_framework', (object) ['name' => $core->shortname,
                    'idnumber' => $idnumber, 'description' => $core->description, 'sortorder' => 0,
                    'timecreated' => $now, 'timemodified' => $now]);
                // Parents avant enfants : tri sur le chemin.
                $corecompetencies = $DB->get_records(
                    'competency',
                    ['competencyframeworkid' => $coreframeworkid],
                    'path ASC, sortorder ASC'
                );
                foreach ($corecompetencies as $competency) {
                    $map[(int) $competency->id] = $DB->insert_record('syllabus_comp', (object) ['frameworkid' => $frameworkid,
                        'parentid' => $map[(int) $competency->parentid] ?? 0,
                        'idnumber' => core_text::substr($competency->idnumber !== '' ? $competency->idnumber
                            : 'C' . $competency->id, 0, 100),
                        'name' => core_text::substr($competency->shortname, 0, 255), 'description' => $competency->description,
                        'sortorder' => (int) $competency->sortorder, 'timecreated' => $now, 'timemodified' => $now]);
                }
            }
            foreach ($rows as $row) {
                if (isset($map[(int) $row->competencyid])) {
                    $DB->insert_record('syllabus_complink', (object) ['courseid' => $row->course, 'syllabusid' => $row->syllabusid,
                        'compid' => $map[(int) $row->competencyid], 'level' => max(1, min(3, (int) $row->level)),
                        'timecreated' => $now, 'timemodified' => $now]);
                }
            }
            $dbman->drop_table($old);
        }
        upgrade_mod_savepoint(true, 2026112200, 'syllabus');
    }

    return true;
}
