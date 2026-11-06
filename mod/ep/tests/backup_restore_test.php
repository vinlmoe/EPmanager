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
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests de la sauvegarde et de la restauration de cours (backup/moodle2/) pour mod_ep.
 *
 * L'activité mêle du paramétrage (types d'EP, catalogue interne, minimums annuels) et des données
 * d'étudiants reliées à ce paramétrage par identifiants (crédits vers types et EP du catalogue) :
 * ces tests vérifient que la copie restaurée pointe bien vers ses propres lignes et non vers
 * celles de l'activité d'origine, et que le lien vers l'activité « Gestion des stages » suit la
 * copie de celle-ci.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_ep_activity_structure_step
 * @covers     \restore_ep_activity_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Sauvegarde un cours puis le restaure dans un nouveau cours.
     *
     * @param \stdClass $course
     * @param bool $users Inclure les données utilisateur (false : paramétrage seul).
     * @return \stdClass Le cours restauré.
     */
    protected function backup_and_restore(\stdClass $course, bool $users = true): \stdClass {
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
        if (!$users) {
            // Le réglage racine « users » commande le « userinfo » de chaque activité.
            $bc->get_plan()->get_setting('users')->set_value(0);
        }
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
     * Une activité complète (paramétrage, crédits, justificatif) survit à l'aller-retour, et la
     * copie est refermée sur elle-même : ses crédits désignent ses propres types et son propre
     * catalogue, et son lien vers l'activité « Gestion des stages » désigne la copie de celle-ci.
     */
    public function test_course_backup_restore_keeps_configuration_and_credits(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $stage = $generator->create_module('stage', ['course' => $course->id, 'name' => 'Stages A3']);
        $stagecm = get_coursemodule_from_instance('stage', $stage->id, $course->id, false, MUST_EXIST);

        $ep = $generator->create_module('ep', [
            'course' => $course->id,
            'name' => 'Enseignement personnalisé A3',
            'currentstudyyear' => 3,
            'mincursusects' => 10,
            'stagecmid' => $stagecm->id,
        ]);
        $cm = get_coursemodule_from_instance('ep', $ep->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $DB->set_field('ep', 'timesynced', time(), ['id' => $ep->id]);

        /** @var \mod_ep_generator $epgen */
        $epgen = $generator->get_plugin_generator('mod_ep');
        $epgen->configure_type($ep, EP_TYPE_ENGAGEMENT, ['maxects' => 4, 'maxectsperyear' => 2]);
        $epgen->configure_type($ep, EP_TYPE_STAGE, ['ectsperday' => 0.25]);
        $academic = ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC);
        $engagement = ep_get_type_by_code($ep->id, EP_TYPE_ENGAGEMENT);
        $DB->set_field('ep_type', 'description', 'Inscription depuis le catalogue.', ['id' => $academic->id]);
        $oldtypeids = array_keys(ep_get_types($ep->id));
        $this->assertCount(6, $oldtypeids);

        $activity = $epgen->create_activity($ep, ['name' => 'Atelier chirurgie', 'ects' => 3, 'capacity' => 12,
            'code' => 'EP-CHIR', 'grading' => EP_GRADING_GRADE, 'gradepass' => 12, 'prereqblocking' => 0]);
        // Un second EP, archivé, exigé par le premier : le prérequis doit suivre la copie.
        $required = $epgen->create_activity($ep, ['name' => 'Anatomie', 'state' => EP_ACTIVITY_ARCHIVED]);
        ep_set_activity_prerequisites($activity, [$required->id]);
        ep_set_activity_teachers($activity->id, [$teacher->id]);
        // Syllabus publié avec ses séances, et une version de travail en cours : tous deux suivent
        // la copie, de même que les courriels personnalisés.
        $syllabus = ep_start_syllabus_revision($activity, $teacher->id);
        ep_save_syllabus($syllabus, (object) ['objectives' => 'Maîtriser les sutures.', 'personalhours' => 6], [
            ['title' => 'Nœuds', 'kind' => 'practical', 'duration' => 2, 'teacherid' => $teacher->id],
            ['title' => 'Conférence', 'kind' => 'lecture', 'duration' => 1, 'teachername' => 'Dr Invité'],
        ]);
        $DB->set_field('ep_syllabus', 'status', 'published', ['id' => $syllabus->id]);
        ep_start_syllabus_revision($activity, $teacher->id);
        ep_set_notification_template($ep->id, 'creditdecided', false, 'Objet personnalisé', 'Corps');
        ep_set_year_requirement($ep->id, 3, 6);

        // Crédit issu d'une inscription au catalogue, validé par le responsable de l'EP.
        $result = ep_register_to_activity($ep, $activity, $student->id, 3);
        $this->assertNull($result->problem);
        $registrationid = $result->creditid;
        $registration = $DB->get_record('ep_credit', ['id' => $registrationid], '*', MUST_EXIST);
        ep_validate_credit($registration, $teacher->id, 3, 'Très bien.');
        $registration = $DB->get_record('ep_credit', ['id' => $registrationid], '*', MUST_EXIST);

        // Crédit déclaré hors catalogue, en attente, avec un justificatif déposé.
        $declaredid = ep_create_credit($ep, $student->id, $engagement, [
            'studyyear' => 3,
            'name' => 'Bureau des élèves',
            'description' => 'Trésorier pendant un an.',
            'claimedects' => 2,
        ]);

        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_ep',
            'filearea' => EP_EVIDENCE_FILEAREA, 'itemid' => $declaredid,
            'filepath' => '/', 'filename' => 'attestation.pdf',
        ], 'attestation');

        // Supplément : une décision d'année, un élément validé et un supplément émis.
        ep_set_year_validation($ep, $student->id, 3, true, 'Année validée.', get_admin()->id);
        $DB->set_field('ep', 'supplementflow', '', ['id' => $ep->id]);
        $ep = $DB->get_record('ep', ['id' => $ep->id]);
        $categories = ep_get_supplement_categories($ep->id);
        ep_create_supplement_item($ep, $student->id, reset($categories), (object) ['title' => 'Prix de thèse']);
        $epcontext = \context_module::instance(get_coursemodule_from_instance('ep', $ep->id)->id);
        $issue = ep_issue_supplement($ep, $epcontext, $student->id, get_admin()->id, 'fr', true)->issue;

        // L'EP est clos après les inscriptions : l'état doit suivre la copie.
        $DB->set_field('ep_activity', 'state', EP_ACTIVITY_CLOSED, ['id' => $activity->id]);

        $newcourse = $this->backup_and_restore($course);
        $newcm = $this->single_instance($newcourse->id, 'ep');
        $newstagecm = $this->single_instance($newcourse->id, 'stage');
        $newep = $DB->get_record('ep', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertNotEquals($ep->id, $newep->id);

        // Paramétrage de l'instance.
        $this->assertSame('Enseignement personnalisé A3', $newep->name);
        $this->assertEquals(3, $newep->currentstudyyear);
        $this->assertEquals(10, $newep->mincursusects);
        $this->assertEquals(6, $DB->get_field(
            'ep_year_requirement',
            'requiredects',
            ['epid' => $newep->id, 'studyyear' => 3]
        ));

        // Lien vers l'activité « Gestion des stages » : il suit la copie de celle-ci, et la
        // copie repart d'une synchronisation à zéro.
        $this->assertNotEquals($stagecm->id, $newstagecm->id);
        $this->assertEquals($newstagecm->id, $newep->stagecmid);
        $this->assertEquals(0, $newep->timesynced);
        $this->assertEquals($stagecm->id, $DB->get_field('ep', 'stagecmid', ['id' => $ep->id]));

        // Types : les six, propres à la copie, avec leurs plafonds et consignes.
        $newtypes = ep_get_types($newep->id);
        $this->assertCount(6, $newtypes);
        $this->assertEmpty(array_intersect($oldtypeids, array_keys($newtypes)));
        $this->assertEqualsCanonicalizing(
            array_keys(ep_default_type_definitions()),
            array_values(array_map(fn($type) => $type->code, $newtypes))
        );
        $newacademic = ep_get_type_by_code($newep->id, EP_TYPE_ACADEMIC);
        $newengagement = ep_get_type_by_code($newep->id, EP_TYPE_ENGAGEMENT);
        $newstagetype = ep_get_type_by_code($newep->id, EP_TYPE_STAGE);
        $this->assertSame('Inscription depuis le catalogue.', $newacademic->description);
        $this->assertEquals(4, $newengagement->maxects);
        $this->assertEquals(2, $newengagement->maxectsperyear);
        $this->assertEquals(0.25, $newstagetype->ectsperday);

        // Catalogue : rattaché au type de la copie, avec son responsable.
        $newactivities = $DB->get_records('ep_activity', ['epid' => $newep->id], 'id');
        $this->assertCount(2, $newactivities);
        $newactivity = reset($newactivities);
        $newrequired = end($newactivities);
        $this->assertNotEquals($activity->id, $newactivity->id);
        $this->assertSame('Atelier chirurgie', $newactivity->name);
        $this->assertSame('EP-CHIR', $newactivity->code);
        $this->assertSame(EP_ACTIVITY_CLOSED, $newactivity->state);
        $this->assertSame(EP_GRADING_GRADE, $newactivity->grading);
        $this->assertEquals(12, $newactivity->gradepass);
        $this->assertSame(EP_ACTIVITY_ARCHIVED, $newrequired->state);
        // Le prérequis désigne l'EP de la copie, pas celui d'origine.
        $this->assertSame([(int) $newrequired->id], ep_get_activity_prerequisites($newactivity->id));
        $this->assertEquals(0, $newactivity->prereqblocking);

        // Syllabus : la version publiée et la version de travail, avec leurs séances.
        $newpublished = ep_get_syllabus($newactivity->id);
        $this->assertSame('Maîtriser les sutures.', $newpublished->objectives);
        $this->assertEquals(6, $newpublished->personalhours);
        $newsessions = array_values(ep_get_syllabus_sessions($newpublished->id));
        $this->assertCount(2, $newsessions);
        $this->assertEquals($teacher->id, $newsessions[0]->teacherid);
        $this->assertSame('Dr Invité', $newsessions[1]->teachername);
        $this->assertSame(2, (int) ep_get_working_syllabus($newactivity->id)->version);
        $this->assertCount(2, ep_get_syllabus_sessions(ep_get_working_syllabus($newactivity->id)->id));
        $this->assertCount(6, ep_get_supplement_categories($newep->id));
        $newitems = ep_get_supplement_items($newep->id);
        $this->assertCount(1, $newitems);
        $this->assertSame('Prix de thèse', reset($newitems)->title);
        $this->assertSame(1, (int) ep_get_year_validations($newep->id)[$student->id][3]->decision);
        $newissues = ep_get_supplement_issues($newep->id);
        $this->assertCount(1, $newissues);
        $newissue = reset($newissues);
        // Le numéro est unique sur le site : la copie en reçoit un dérivé, le code de vérification est conservé.
        $this->assertNotSame($issue->number, $newissue->number);
        $this->assertSame($issue->verifycode, $newissue->verifycode);
        $newcontext = \context_module::instance(get_coursemodule_from_instance('ep', $newep->id)->id);
        $this->assertNotNull(ep_get_supplement_issue_file($newcontext, $newissue->id));
        $template = ep_get_notification_template($newep->id, 'creditdecided');
        $this->assertSame(0, $template->enabled);
        $this->assertSame('Objet personnalisé', $template->subject);
        $this->assertEquals(3, $newactivity->ects);
        $this->assertEquals(12, $newactivity->capacity);
        $this->assertEquals($newacademic->id, $newactivity->typeid);
        $this->assertEquals([$teacher->id], array_keys(ep_get_activity_teachers($newactivity->id)));

        // Crédits : rattachés aux types et à l'EP du catalogue de la copie, pas à ceux de
        // l'original ; dates et validateur conservés.
        $newcredits = $DB->get_records('ep_credit', ['epid' => $newep->id], 'id');
        $this->assertCount(2, $newcredits);
        foreach ($newcredits as $newcredit) {
            $this->assertEquals($student->id, $newcredit->userid);
            $this->assertArrayHasKey($newcredit->typeid, $newtypes);
        }
        [$newregistration, $newdeclared] = array_values($newcredits);

        $this->assertSame('Atelier chirurgie', $newregistration->name);
        $this->assertEquals($newacademic->id, $newregistration->typeid);
        $this->assertEquals($newactivity->id, $newregistration->activityid);
        $this->assertEquals(EP_STATUS_VALIDATED, $newregistration->status);
        $this->assertEquals(3, $newregistration->retainedects);
        $this->assertEquals($teacher->id, $newregistration->validatedby);
        $this->assertEquals($registration->validatetime, $newregistration->validatetime);
        $this->assertEquals($registration->timecreated, $newregistration->timecreated);
        $this->assertSame('Très bien.', $newregistration->validatorcomment);

        $this->assertSame('Bureau des élèves', $newdeclared->name);
        $this->assertEquals($newengagement->id, $newdeclared->typeid);
        $this->assertNull($newdeclared->activityid);
        $this->assertEquals(EP_STATUS_PENDING, $newdeclared->status);
        $this->assertNull($newdeclared->validatedby);
        $this->assertSame('Trésorier pendant un an.', $newdeclared->description);

        // Justificatif : dans le contexte de la copie, rattaché au crédit de la copie.
        $newcontext = \context_module::instance($newcm->id);
        $newfiles = $fs->get_area_files($newcontext->id, 'mod_ep', EP_EVIDENCE_FILEAREA, $newdeclared->id, 'itemid', false);
        $this->assertCount(1, $newfiles);
        $this->assertSame('attestation.pdf', reset($newfiles)->get_filename());
        $this->assertCount(1, ep_get_evidence_files($newcontext, $newdeclared->id));

        // L'original n'est pas touché.
        $this->assertEquals(2, $DB->count_records('ep_credit', ['epid' => $ep->id]));
        $this->assertCount(1, ep_get_evidence_files($context, $declaredid));
    }

    /**
     * Sans les données utilisateur, la copie reçoit tout le paramétrage mais aucun crédit ni
     * justificatif ; les responsables du catalogue, dont les comptes ne sont pas dans l'archive,
     * sont écartés.
     */
    public function test_course_backup_without_user_data_restores_configuration_only(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $ep = $generator->create_module('ep', [
            'course' => $course->id,
            'name' => 'Enseignement personnalisé A4',
            'currentstudyyear' => 4,
        ]);
        $cm = get_coursemodule_from_instance('ep', $ep->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        /** @var \mod_ep_generator $epgen */
        $epgen = $generator->get_plugin_generator('mod_ep');
        $epgen->configure_type($ep, EP_TYPE_SPORT, ['maxects' => 3]);
        $activity = $epgen->create_activity($ep, ['name' => 'Atelier imagerie', 'ects' => 1]);
        ep_set_activity_teachers($activity->id, [$teacher->id]);
        ep_set_year_requirement($ep->id, 4, 5);

        $credit = $epgen->create_validated_credit($ep, $student->id, EP_TYPE_SPORT, 2, 4);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_ep',
            'filearea' => EP_EVIDENCE_FILEAREA, 'itemid' => $credit->id,
            'filepath' => '/', 'filename' => 'licence.pdf',
        ], 'licence');

        $newcourse = $this->backup_and_restore($course, false);
        $newcm = $this->single_instance($newcourse->id, 'ep');
        $newep = $DB->get_record('ep', ['id' => $newcm->instance], '*', MUST_EXIST);

        // Paramétrage.
        $this->assertSame('Enseignement personnalisé A4', $newep->name);
        $this->assertEquals(4, $newep->currentstudyyear);
        $this->assertCount(6, ep_get_types($newep->id));
        $this->assertEquals(3, ep_get_type_by_code($newep->id, EP_TYPE_SPORT)->maxects);
        $this->assertEquals(5, $DB->get_field(
            'ep_year_requirement',
            'requiredects',
            ['epid' => $newep->id, 'studyyear' => 4]
        ));

        $newactivities = $DB->get_records('ep_activity', ['epid' => $newep->id]);
        $this->assertCount(1, $newactivities);
        $newactivity = reset($newactivities);
        $this->assertSame('Atelier imagerie', $newactivity->name);
        $this->assertEquals(ep_get_type_by_code($newep->id, EP_TYPE_ACADEMIC)->id, $newactivity->typeid);
        $this->assertEquals(0, $DB->count_records('ep_activity_teacher', ['activityid' => $newactivity->id]));

        // Aucune donnée d'étudiant.
        $this->assertEquals(0, $DB->count_records('ep_credit', ['epid' => $newep->id]));
        $newcontext = \context_module::instance($newcm->id);
        $this->assertCount(
            0,
            get_file_storage()->get_area_files($newcontext->id, 'mod_ep', EP_EVIDENCE_FILEAREA, false, 'itemid', false)
        );

        // L'original n'est pas touché.
        $this->assertEquals(1, $DB->count_records('ep_credit', ['epid' => $ep->id]));
        $this->assertEquals(1, $DB->count_records('ep_activity_teacher', ['activityid' => $activity->id]));
    }

    /**
     * Un lien vers une activité « Gestion des stages » restée en dehors de la sauvegarde continue
     * de désigner l'original, qui existe toujours sur le même site.
     */
    public function test_stage_link_outside_the_backup_is_kept_on_the_same_site(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $othercourse = $generator->create_course();

        $otherstage = $generator->create_module('stage', ['course' => $othercourse->id, 'name' => 'Stages A4']);
        $otherstagecm = get_coursemodule_from_instance('stage', $otherstage->id, $othercourse->id, false, MUST_EXIST);

        $ep = $generator->create_module('ep', [
            'course' => $course->id,
            'name' => 'Enseignement personnalisé A4',
            'stagecmid' => $otherstagecm->id,
        ]);

        $newcourse = $this->backup_and_restore($course);
        $newcm = $this->single_instance($newcourse->id, 'ep');
        $newep = $DB->get_record('ep', ['id' => $newcm->instance], '*', MUST_EXIST);

        $this->assertNotEquals($ep->id, $newep->id);
        $this->assertEquals($otherstagecm->id, $newep->stagecmid);
        $this->assertEmpty(get_fast_modinfo($newcourse->id)->get_instances_of('stage'));
    }
}
