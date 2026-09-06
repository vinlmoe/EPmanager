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
 * @param int $oldversion Version actuellement installée.
 * @return bool
 */
function xmldb_ep_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090600) {

        // Un EP du catalogue peut désormais être défini dans une activité « Suivi de
        // l'enseignement personnalisé » plutôt que dans une promotion : des étudiants de
        // promotions différentes s'inscrivent alors au même EP. Les EP existants restent propres
        // à leur promotion (epid inchangé, synthesiscmid à 0).
        $table = new xmldb_table('ep_activity');

        $field = new xmldb_field('synthesiscmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'epid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // epid = 0 (EP partagé) et typeid = 0 (le type académique de l'instance de l'étudiant est
        // retenu à l'inscription) deviennent des valeurs légitimes : les deux colonnes prennent
        // une valeur par défaut, et les clés étrangères qui les déclaraient laissent place à de
        // simples index.
        $field = new xmldb_field('epid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $dbman->change_field_default($table, $field);
        $field = new xmldb_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $dbman->change_field_default($table, $field);

        foreach ([
            new xmldb_key('epid', XMLDB_KEY_FOREIGN, ['epid'], 'ep', ['id']),
            new xmldb_key('typeid', XMLDB_KEY_FOREIGN, ['typeid'], 'ep_type', ['id']),
        ] as $key) {
            // La clé peut déjà avoir disparu (base restaurée, mise à jour rejouée) : la chercher
            // avant de la retirer évite de faire échouer toute la mise à jour pour rien.
            if ($dbman->find_key_name($table, $key)) {
                $dbman->drop_key($table, $key);
            }
        }

        $index = new xmldb_index('epid', XMLDB_INDEX_NOTUNIQUE, ['epid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $index = new xmldb_index('synthesiscmid', XMLDB_INDEX_NOTUNIQUE, ['synthesiscmid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, 2026090600, 'ep');
    }

    if ($oldversion < 2026090601) {

        // Un type déclaré par l'étudiant peut désormais fixer lui-même le nombre d'ECTS d'une
        // déclaration : un forfait (1 ECTS par déclaration de sport, par exemple) ou un barème par
        // semaine déclarée. Les types existants gardent la règle d'origine, où l'étudiant propose
        // lui-même un nombre d'ECTS (ectsmode = 'free').
        $table = new xmldb_table('ep_type');

        $field = new xmldb_field('ectsmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'free',
            'ectsperday');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('ectsvalue', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0',
            'ectsmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Les semaines déclarées, pour les types comptés à la semaine : 0 partout ailleurs.
        $table = new xmldb_table('ep_credit');

        $field = new xmldb_field('weeks', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0',
            'claimedects');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026090601, 'ep');
    }

    return true;
}
