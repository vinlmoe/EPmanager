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

namespace mod_ep;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/lib.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');
require_once($CFG->dirroot . '/mod/stage/locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests de la sauvegarde et de la restauration de cours (backup/moodle2/) pour mod_ep.
 *
 * Deux points demandent une attention particulière : les crédits doivent désigner les types et les
 * EP de catalogue de la copie, et les EP de type stage, qui sont dérivés de mod_stage, ne doivent
 * pas être recopiés mais recalculés.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_ep_activity_structure_step
 * @covers     \restore_ep_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Sauvegarde un cours puis le restaure dans un nouveau cours, données utilisateur comprises.
     *
     * @param \stdClass $course
     * @return \stdClass Le cours restauré.
     */
    protected function backup_and_restore(\stdClass $course): \stdClass {
        global $CFG, $USER;

        // En mode général, le plan compresse la sauvegarde en .mbz puis efface son dossier de
        // travail, alors que la restauration lit ce dossier. Le conserver évite d'avoir à
        // réextraire l'archive sous le même identifiant.
        $CFG->keeptempdirectoriesonbackup = true;
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        try {
            $bc->execute_plan();
        } finally {
            $bc->destroy();
            // La suppression de backup_ids_temp fait partie du plan : elle ne tourne pas si
            // celui-ci échoue, et destroy() ne s'en charge pas. La table survivrait alors au
            // test, faisant échouer tous les suivants du fichier sur une erreur DDL sans rapport.
            \backup_controller_dbops::drop_backup_ids_temp_table($backupid);
        }

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_copie',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        try {
            $rc->execute_precheck();
            $rc->execute_plan();
        } finally {
            $rc->destroy();
            \restore_controller_dbops::drop_restore_temp_tables($backupid);
        }

        return get_course($newcourseid);
    }

    /**
     * Renvoie l'unique instance d'un module dans un cours.
     *
     * @param int $courseid
     * @param string $modname
     * @return \cm_info
     */
    protected function single_instance(int $courseid, string $modname): \cm_info {
        $instances = get_fast_modinfo($courseid)->get_instances_of($modname);
        $this->assertCount(1, $instances);
        return reset($instances);
    }

    /**
     * Le paramétrage, le catalogue et les crédits déclarés par les étudiants survivent à
     * l'aller-retour, et les crédits de la copie désignent ses propres types et EP de catalogue.
     */
    public function test_course_backup_restore_keeps_catalog_and_credits(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $ep = $generator->create_module('ep', [
            'course' => $course->id,
            'name' => 'Enseignement personnalisé A3',
            'currentstudyyear' => 3,
            'mincursusects' => 12,
        ]);
        $cm = get_coursemodule_from_instance('ep', $ep->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        /** @var \mod_ep_generator $epgen */
        $epgen = $generator->get_plugin_generator('mod_ep');
        $epgen->configure_type($ep, EP_TYPE_ENGAGEMENT, ['maxects' => 6]);
        $activity = $epgen->create_activity($ep, ['name' => 'Tutorat', 'ects' => 3]);

        $now = time();
        $DB->insert_record('ep_activity_teacher', (object) [
            'activityid' => $activity->id,
            'teacherid' => $teacher->id,
            'timecreated' => $now,
        ]);
        $DB->insert_record('ep_year_requirement', (object) [
            'epid' => $ep->id,
            'studyyear' => 3,
            'requiredects' => 4,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Une inscription au catalogue et une déclaration hors catalogue, avec sa pièce jointe.
        $academic = ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC);
        $enrolled = ep_create_credit($ep, $student->id, $academic, [
            'studyyear' => 3,
            'name' => 'Tutorat',
            'claimedects' => 3,
            'activityid' => $activity->id,
        ]);
        $declared = $epgen->create_validated_credit($ep, $student->id, EP_TYPE_ENGAGEMENT, 2, 3);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_ep',
            'filearea' => EP_EVIDENCE_FILEAREA, 'itemid' => $declared->id,
            'filepath' => '/', 'filename' => 'attestation.pdf',
        ], 'justificatif');

        $typecount = $DB->count_records('ep_type', ['epid' => $ep->id]);

        $newcourse = $this->backup_and_restore($course);
        $newcm = $this->single_instance($newcourse->id, 'ep');
        $newep = $DB->get_record('ep', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertNotEquals($ep->id, $newep->id);

        // Instance et minimums.
        $this->assertSame('Enseignement personnalisé A3', $newep->name);
        $this->assertEquals(12, $newep->mincursusects);
        $this->assertEquals(4, $DB->get_field(
            'ep_year_requirement',
            'requiredects',
            ['epid' => $newep->id, 'studyyear' => 3]
        ));

        // Types : ceux de la sauvegarde, sans doublon (la restauration n'appelle pas
        // ep_add_instance(), qui crée les types par défaut).
        $this->assertEquals($typecount, $DB->count_records('ep_type', ['epid' => $newep->id]));
        $newengagement = ep_get_type_by_code($newep->id, EP_TYPE_ENGAGEMENT);
        $this->assertEquals(6, $newengagement->maxects);

        // Catalogue et son responsable.
        $newactivities = $DB->get_records('ep_activity', ['epid' => $newep->id]);
        $this->assertCount(1, $newactivities);
        $newactivity = reset($newactivities);
        $this->assertSame('Tutorat', $newactivity->name);
        $this->assertEquals(ep_get_type_by_code($newep->id, EP_TYPE_ACADEMIC)->id, $newactivity->typeid);
        $this->assertTrue($DB->record_exists(
            'ep_activity_teacher',
            ['activityid' => $newactivity->id, 'teacherid' => $teacher->id]
        ));

        // Crédits : rattachés aux types et au catalogue de la copie.
        $newcredits = $DB->get_records('ep_credit', ['epid' => $newep->id], 'id');
        $this->assertCount(2, $newcredits);

        $newenrolled = null;
        $newdeclared = null;
        foreach ($newcredits as $credit) {
            $this->assertEquals($student->id, $credit->userid);
            if ($credit->activityid) {
                $newenrolled = $credit;
            } else {
                $newdeclared = $credit;
            }
        }
        $this->assertNotNull($newenrolled);
        $this->assertNotNull($newdeclared);
        $this->assertNotEquals($enrolled, $newenrolled->id);
        $this->assertEquals($newactivity->id, $newenrolled->activityid);
        $this->assertEquals($newengagement->id, $newdeclared->typeid);
        $this->assertEquals(EP_STATUS_VALIDATED, $newdeclared->status);
        $this->assertEquals(2, $newdeclared->retainedects);

        // Pièce jointe, dans le contexte de la copie.
        $newcontext = \context_module::instance($newcm->id);
        $this->assertCount(1, $fs->get_area_files(
            $newcontext->id,
            'mod_ep',
            EP_EVIDENCE_FILEAREA,
            $newdeclared->id,
            'itemid',
            false
        ));
    }

    /**
     * Les EP de type stage ne sont pas recopiés : ils sont recalculés depuis l'activité
     * « Gestion des stages » restaurée, vers laquelle le réglage de l'instance pointe désormais.
     */
    public function test_stage_credits_are_recomputed_rather_than_copied(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $stage = $generator->create_module('stage', ['course' => $course->id]);
        $stagecm = get_coursemodule_from_instance('stage', $stage->id, $course->id, false, MUST_EXIST);
        $theme = $generator->get_plugin_generator('mod_stage')
            ->create_theme($stage, ['name' => 'Animaux de compagnie']);

        $ep = $generator->create_module('ep', [
            'course' => $course->id,
            'currentstudyyear' => 3,
            'stagecmid' => $stagecm->id,
        ]);
        $generator->get_plugin_generator('mod_ep')->configure_type($ep, EP_TYPE_STAGE, ['ectsperday' => 0.25]);

        $entry = $generator->get_plugin_generator('mod_stage')->create_entry(
            $stage,
            $student->id,
            $theme,
            ['declaredduration' => 8, 'studyyear' => 3]
        );
        stage_set_entry_stagetype($entry->id, 'complementaire');
        stage_apply_deve_validation($DB->get_record('stage_entry', ['id' => $entry->id], '*', MUST_EXIST), 0, 8);

        ep_sync_stage_credits($ep);
        $this->assertEquals(1, $DB->count_records(
            'ep_credit',
            ['epid' => $ep->id, 'source' => EP_SOURCE_STAGE]
        ));

        $newcourse = $this->backup_and_restore($course);
        $newepcm = $this->single_instance($newcourse->id, 'ep');
        $newstagecm = $this->single_instance($newcourse->id, 'stage');
        $newep = $DB->get_record('ep', ['id' => $newepcm->instance], '*', MUST_EXIST);

        // Le réglage suit l'activité stage restaurée, et rien n'a encore été synchronisé.
        $this->assertEquals($newstagecm->id, $newep->stagecmid);
        $this->assertEquals(0, $newep->timesynced);
        $this->assertEquals(0, $DB->count_records(
            'ep_credit',
            ['epid' => $newep->id, 'source' => EP_SOURCE_STAGE]
        ));

        // La synchronisation les recrée à partir des stages de la copie.
        $result = ep_sync_stage_credits($newep);
        $this->assertSame(1, $result->created);

        $newcredit = $DB->get_record(
            'ep_credit',
            ['epid' => $newep->id, 'source' => EP_SOURCE_STAGE],
            '*',
            MUST_EXIST
        );
        $this->assertEquals($student->id, $newcredit->userid);
        $this->assertEquals(2, $newcredit->retainedects); // 8 jours x 0,25.
        $newstageentry = $DB->get_record(
            'stage_entry',
            ['stageid' => $newstagecm->instance],
            '*',
            MUST_EXIST
        );
        $this->assertEquals($newstageentry->id, $newcredit->sourceref);
    }
}
