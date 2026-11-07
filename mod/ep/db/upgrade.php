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
 * Paramètres d'un type tel qu'il existait avant les types paramétrés (drapeaux catalog et
 * autovalidate, code fixe), exprimés avec les paramètres actuels, à l'identique de son
 * comportement d'alors. Isolée pour être testable sans rejouer la migration.
 *
 * @param stdClass $type Ligne de ep_type avec les anciens champs catalog et autovalidate.
 * @return array Champs à écrire.
 */
function ep_legacy_type_parameters(stdClass $type) {
    if (!empty($type->autovalidate) || $type->code === 'stage') {
        return [
            'acquisition' => 'stage',
            'validationflow' => '',
            'evidence' => 0,
            'stagescope' => 'all',
        ];
    }
    if (!empty($type->catalog)) {
        return ['acquisition' => 'catalog', 'validationflow' => 'owner', 'evidence' => 0];
    }
    return ['acquisition' => 'declaration', 'validationflow' => 'referent', 'evidence' => 1];
}

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

    if ($oldversion < 2026110700) {
        // Types paramétrés : le comportement d'un type n'est plus déduit de son code mais de ses
        // paramètres (mode d'acquisition, circuit de validation, notation, justificatifs). Les
        // six types existants sont convertis à l'identique de leur comportement.
        $table = new xmldb_table('ep_type');
        $fields = [
            new xmldb_field('acquisition', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'declaration', 'enabled'),
            new xmldb_field('validationflow', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, 'acquisition'),
            new xmldb_field('grading', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'none', 'validationflow'),
            new xmldb_field('grademax', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '20', 'grading'),
            new xmldb_field('gradepass', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '10', 'grademax'),
            new xmldb_field('evidence', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'gradepass'),
            new xmldb_field('evidencetypes', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'documents', 'evidence'),
            new xmldb_field('maxclaimedects', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0', 'evidencetypes'),
            new xmldb_field('stagescope', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'all', 'maxclaimedects'),
            new xmldb_field('insupplement', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'stagescope'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $catalogfield = new xmldb_field('catalog');
        $autofield = new xmldb_field('autovalidate');
        if ($dbman->field_exists($table, $catalogfield) && $dbman->field_exists($table, $autofield)) {
            foreach ($DB->get_records('ep_type') as $type) {
                $DB->update_record('ep_type', (object) (['id' => $type->id] + ep_legacy_type_parameters($type)));
            }
            $dbman->drop_field($table, $catalogfield);
            $dbman->drop_field($table, $autofield);
        }

        // Crédits : étape courante, rôle attendu, note.
        $table = new xmldb_table('ep_credit');
        $fields = [
            new xmldb_field('step', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'status'),
            new xmldb_field('pendingrole', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'step'),
            new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'pendingrole'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $index = new xmldb_index('epid-pendingrole', XMLDB_INDEX_NOTUNIQUE, ['epid', 'pendingrole']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        // Les demandes en attente attendent le responsable (inscription au catalogue) ou le
        // référent (déclaration), comme avant la migration.
        $DB->execute(
            "UPDATE {ep_credit} SET pendingrole = :owner WHERE status = :pending AND activityid IS NOT NULL",
            ['owner' => 'owner', 'pending' => 0]
        );
        $DB->execute(
            "UPDATE {ep_credit} SET pendingrole = :referent WHERE status = :pending AND activityid IS NULL",
            ['referent' => 'referent', 'pending' => 0]
        );

        // Historique des décisions, reconstitué pour les crédits déjà tranchés.
        $table = new xmldb_table('ep_credit_decision');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('creditid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('step', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('role', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('decision', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('retainedects', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('creditid', XMLDB_KEY_FOREIGN, ['creditid'], 'ep_credit', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            $decisions = [-2 => 'cancelled', -1 => 'rejected', 1 => 'validated'];
            $rs = $DB->get_recordset_select(
                'ep_credit',
                'status <> 0 AND validatetime IS NOT NULL AND source <> :stage',
                ['stage' => 'stage']
            );
            foreach ($rs as $credit) {
                $DB->insert_record('ep_credit_decision', (object) [
                    'creditid' => $credit->id,
                    'step' => 0,
                    'role' => $credit->activityid ? 'owner' : 'referent',
                    'decision' => $decisions[(int) $credit->status] ?? 'validated',
                    'userid' => $credit->validatedby ?: null,
                    'grade' => null,
                    'retainedects' => $credit->retainedects,
                    'comment' => $credit->validatorcomment,
                    'timecreated' => $credit->validatetime,
                ]);
            }
            $rs->close();
        }

        upgrade_mod_savepoint(true, 2026110700, 'ep');
    }

    if ($oldversion < 2026110800) {
        // Catalogue : état et périodes de l'EP, barème propre, prérequis. L'ancien drapeau
        // « visible » devient l'état : un EP masqué n'était plus montré aux étudiants, il est
        // archivé (ses inscriptions restent au dossier).
        $table = new xmldb_table('ep_activity');
        $fields = [
            new xmldb_field('code', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, 'capacity'),
            new xmldb_field('state', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'published', 'code'),
            new xmldb_field('registrationstart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'state'),
            new xmldb_field('registrationend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'registrationstart'),
            new xmldb_field('datestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'registrationend'),
            new xmldb_field('dateend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'datestart'),
            new xmldb_field('grading', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'dateend'),
            new xmldb_field('grademax', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '20', 'grading'),
            new xmldb_field('gradepass', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '10', 'grademax'),
            new xmldb_field('minects', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0', 'gradepass'),
            new xmldb_field('prereqblocking', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'minects'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $visible = new xmldb_field('visible');
        if ($dbman->field_exists($table, $visible)) {
            $DB->execute("UPDATE {ep_activity} SET state = 'archived' WHERE visible = 0");
            $dbman->drop_field($table, $visible);
        }
        $index = new xmldb_index('epid-state', XMLDB_INDEX_NOTUNIQUE, ['epid', 'state']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('ep_activity_prereq');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('requiredid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activityid', XMLDB_KEY_FOREIGN, ['activityid'], 'ep_activity', ['id']);
        $table->add_key('requiredid', XMLDB_KEY_FOREIGN, ['requiredid'], 'ep_activity', ['id']);
        $table->add_index('activityid-requiredid', XMLDB_INDEX_UNIQUE, ['activityid', 'requiredid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('ep_credit');
        $field = new xmldb_field('addedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'validatedby');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $key = new xmldb_key('addedby', XMLDB_KEY_FOREIGN, ['addedby'], 'user', ['id']);
        $dbman->add_key($table, $key);

        upgrade_mod_savepoint(true, 2026110800, 'ep');
    }

    if ($oldversion < 2026110900) {
        // Propositions d'EP et syllabus, éditions PDF, courriels personnalisables.
        $table = new xmldb_table('ep');
        $fields = [
            new xmldb_field('proposalsdirectpublish', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'stagecmid'),
            new xmldb_field('pdfheader', XMLDB_TYPE_TEXT, null, null, null, null, null, 'proposalsdirectpublish'),
            new xmldb_field('pdffooter', XMLDB_TYPE_TEXT, null, null, null, null, null, 'pdfheader'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('ep_activity');
        $fields = [
            new xmldb_field('proposedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'minects'),
            new xmldb_field('timepublished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'proposedby'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $dbman->add_key($table, new xmldb_key('proposedby', XMLDB_KEY_FOREIGN, ['proposedby'], 'user', ['id']));

        $table = new xmldb_table('ep_syllabus');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('activityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'draft');
        $textfields = ['objectives', 'outcomes', 'prerequisitestext', 'content', 'methods', 'assessment', 'resit', 'bibliography'];
        foreach ($textfields as $name) {
            $table->add_field($name, XMLDB_TYPE_TEXT, null, null, null, null, null);
        }
        $table->add_field('language', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('personalhours', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('reviewerid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('reviewcomment', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timesubmitted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timereviewed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('activityid', XMLDB_KEY_FOREIGN, ['activityid'], 'ep_activity', ['id']);
        $table->add_key('authorid', XMLDB_KEY_FOREIGN, ['authorid'], 'user', ['id']);
        $table->add_key('reviewerid', XMLDB_KEY_FOREIGN, ['reviewerid'], 'user', ['id']);
        $table->add_index('activityid-status', XMLDB_INDEX_NOTUNIQUE, ['activityid', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('ep_session');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('syllabusid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kind', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'lecture');
        $table->add_field('duration', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('teachername', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sessiondate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('syllabusid', XMLDB_KEY_FOREIGN, ['syllabusid'], 'ep_syllabus', ['id']);
        $table->add_key('teacherid', XMLDB_KEY_FOREIGN, ['teacherid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('ep_notification');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('epid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('msgkey', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('epid', XMLDB_KEY_FOREIGN, ['epid'], 'ep', ['id']);
        $table->add_index('epid-msgkey', XMLDB_INDEX_UNIQUE, ['epid', 'msgkey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026110900, 'ep');
    }

    if ($oldversion < 2026111000) {
        // Supplément au diplôme et validation des années.
        $table = new xmldb_table('ep');
        $fields = [
            new xmldb_field('supplementflow', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'deve', 'proposalsdirectpublish'),
            new xmldb_field('supplementevidence', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'supplementflow'),
            new xmldb_field('supplementprefix', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null, 'supplementevidence'),
            new xmldb_field('birthdatefield', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null, 'supplementprefix'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        // Les tables nouvelles sont créées d'après install.xml : les étapes suivantes vérifient
        // l'existence de chaque champ avant de l'ajouter, une table créée ici avec des champs plus
        // récents ne les fait donc pas échouer.
        $tables = ['ep_year_validation', 'ep_supplement_category', 'ep_supplement_item', 'ep_supplement_text',
            'ep_supplement_issue'];
        foreach ($tables as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        // Catégories d'éléments par défaut pour les instances existantes.
        global $CFG;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');
        foreach ($DB->get_fieldset_select('ep', 'id', '1 = 1') as $epid) {
            ep_create_default_supplement_categories($epid);
        }

        upgrade_mod_savepoint(true, 2026111000, 'ep');
    }

    if ($oldversion < 2026111100) {
        // Journal des imports.
        if (!$dbman->table_exists('ep_import_log')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'ep_import_log');
        }
        upgrade_mod_savepoint(true, 2026111100, 'ep');
    }

    if ($oldversion < 2026111300) {
        // Sceau des suppléments émis : les émissions déjà en base sont scellées, la vérification
        // ne reconnaîtra plus que des émissions scellées par ce site.
        $table = new xmldb_table('ep_supplement_issue');
        $field = new xmldb_field('seal', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'filehash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        global $CFG;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');
        $rs = $DB->get_recordset('ep_supplement_issue', ['seal' => null]);
        foreach ($rs as $issue) {
            $DB->set_field('ep_supplement_issue', 'seal', ep_supplement_seal($issue), ['id' => $issue->id]);
        }
        $rs->close();
        upgrade_mod_savepoint(true, 2026111300, 'ep');
    }

    if ($oldversion < 2026111500) {
        // Index du test de stress : places prises et états par EP lus sur l'index seul, files
        // d'attente paginées par date sans trier toute la file.
        $table = new xmldb_table('ep_credit');
        $indexes = [
            new xmldb_index('epid-activityid-status', XMLDB_INDEX_NOTUNIQUE, ['epid', 'activityid', 'status']),
            new xmldb_index('epid-status-timecreated', XMLDB_INDEX_NOTUNIQUE, ['epid', 'status', 'timecreated']),
        ];
        foreach ($indexes as $index) {
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_mod_savepoint(true, 2026111500, 'ep');
    }

    return true;
}
