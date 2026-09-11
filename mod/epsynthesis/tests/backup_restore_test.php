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

namespace mod_epsynthesis;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/epsynthesis/locallib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests de la sauvegarde et de la restauration de cours (backup/moodle2/) pour mod_epsynthesis.
 *
 * Les liens de l'activité désignent des activités mod_ep par leur identifiant de course-module :
 * c'est le seul point délicat de la restauration, puisque ces identifiants changent et que
 * l'activité visée peut n'être restaurée qu'après la synthèse elle-même.
 *
 * @package    mod_epsynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_epsynthesis_activity_structure_step
 * @covers     \restore_epsynthesis_activity_structure_step
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
        } catch (\Throwable $e) {
            $this->drop_leftover_temp_tables();
            throw $e;
        } finally {
            $bc->destroy();
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
        } catch (\Throwable $e) {
            $this->drop_leftover_temp_tables();
            throw $e;
        } finally {
            $rc->destroy();
        }

        return get_course($newcourseid);
    }

    /**
     * Supprime les tables temporaires qu'un plan de sauvegarde ou de restauration interrompu
     * aurait laissées derrière lui.
     *
     * Un plan mené à son terme s'en charge lui-même — d'où le nettoyage sur le seul chemin
     * d'échec, et l'absence de bruit si les tables ont déjà disparu. Sans cela, le test suivant
     * du fichier tombe sur une erreur DDL sans rapport, qui masque la vraie cause.
     */
    protected function drop_leftover_temp_tables(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach (['backup_ids_temp', 'backup_files_temp'] as $name) {
            try {
                $dbman->drop_table(new \xmldb_table($name));
            } catch (\moodle_exception $e) {
                continue;
            }
        }
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
     * Un lien vers une activité sauvegardée avec la synthèse suit la copie de cette activité ;
     * un lien vers une activité restée en dehors de la sauvegarde continue de désigner l'original,
     * qui existe toujours sur le même site.
     */
    public function test_links_follow_the_restored_ep_activities(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();

        $ep = $generator->create_module('ep', ['course' => $course->id, 'name' => 'EP A3']);
        $epcm = get_coursemodule_from_instance('ep', $ep->id, $course->id, false, MUST_EXIST);

        $otherep = $generator->create_module('ep', ['course' => $othercourse->id, 'name' => 'EP A4']);
        $otherepcm = get_coursemodule_from_instance('ep', $otherep->id, $othercourse->id, false, MUST_EXIST);

        $synthesis = $generator->create_module('epsynthesis', [
            'course' => $course->id,
            'name' => 'Suivi de l\'enseignement personnalisé',
        ]);
        epsynthesis_set_links($synthesis->id, [$epcm->id, $otherepcm->id]);

        $newcourse = $this->backup_and_restore($course);
        $newsynthesiscm = $this->single_instance($newcourse->id, 'epsynthesis');
        $newepcm = $this->single_instance($newcourse->id, 'ep');
        $newsynthesisid = $newsynthesiscm->instance;

        $this->assertNotEquals($synthesis->id, $newsynthesisid);
        $this->assertEquals(2, $DB->count_records('epsynthesis_link', ['synthesisid' => $newsynthesisid]));

        // Le lien vers l'activité du cours sauvegardé pointe désormais vers sa copie.
        $this->assertTrue($DB->record_exists(
            'epsynthesis_link',
            ['synthesisid' => $newsynthesisid, 'epcmid' => $newepcm->id]
        ));
        $this->assertFalse($DB->record_exists(
            'epsynthesis_link',
            ['synthesisid' => $newsynthesisid, 'epcmid' => $epcm->id]
        ));

        // Celui vers l'activité d'un autre cours, absente de la sauvegarde, est conservé tel quel.
        $this->assertTrue($DB->record_exists(
            'epsynthesis_link',
            ['synthesisid' => $newsynthesisid, 'epcmid' => $otherepcm->id]
        ));

        // Les liens de la synthèse d'origine ne sont pas touchés.
        $this->assertEquals(2, $DB->count_records('epsynthesis_link', ['synthesisid' => $synthesis->id]));
        $this->assertTrue($DB->record_exists(
            'epsynthesis_link',
            ['synthesisid' => $synthesis->id, 'epcmid' => $epcm->id]
        ));
    }
}
