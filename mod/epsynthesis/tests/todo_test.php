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

/**
 * Tests de « À traiter » (todo.php) : participants des EP dont l'enseignant est responsable,
 * propositions et révisions de syllabus, éléments du supplément au diplôme à trancher comme
 * référent, combinés sur les activités liées à la synthèse.
 *
 * @package    mod_epsynthesis
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::epsynthesis_count_todo
 * @covers     ::epsynthesis_get_todo_participants
 * @covers     ::epsynthesis_get_todo_syllabi
 * @covers     ::epsynthesis_get_todo_supplement_items
 * @covers     ::epsynthesis_get_supplement_proofs
 * @covers     ::epsynthesis_decide_supplement_item
 * @covers     ::epsynthesis_teacher_rights
 * @covers     ::epsynthesis_render_navlinks
 * @covers     ::epsynthesis_render_todo_summary
 */
final class todo_test extends \advanced_testcase {
    /** @var \stdClass Instance mod_ep de la promotion. */
    private $ep;

    /** @var int Course-module de l'instance mod_ep. */
    private $epcmid;

    /** @var \stdClass Synthèse, dans le cours d'équipe. */
    private $synthesis;

    /** @var \stdClass Enseignant : responsable d'un EP, référent de l'étudiant, auteur d'une proposition. */
    private $teacher;

    /** @var \stdClass Autre enseignant : responsable d'un autre EP, référent d'un autre étudiant. */
    private $other;

    /** @var \stdClass Étudiant dont l'enseignant est référent. */
    private $student;

    /** @var \stdClass EP du catalogue dont l'enseignant est responsable. */
    private $activity;

    /** @var \stdClass Proposition de l'enseignant, renvoyée par la DEVE. */
    private $proposal;

    /** @var int Élément du supplément de l'étudiant, à l'étape « référent ». */
    private $itemid;

    /**
     * Une promotion (« Gestion des stages » pour les référents, « Enseignement personnalisé » avec
     * le circuit « référent puis DEVE » pour le supplément), une synthèse liée dans un cours
     * d'équipe ; l'enseignant a un inscrit en attente, une proposition renvoyée, une révision
     * soumise et un élément du supplément à examiner.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Promotion A3']);
        $stage = $gen->create_module('stage', ['course' => $course->id]);
        $ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 3]);
        $DB->set_field('ep', 'supplementflow', 'referent,deve', ['id' => $ep->id]);
        $this->ep = $DB->get_record('ep', ['id' => $ep->id], '*', MUST_EXIST);
        $this->epcmid = (int) $ep->cmid;

        $this->student = $gen->create_and_enrol($course, 'student', ['firstname' => 'Zoé', 'lastname' => 'Martin']);
        $otherstudent = $gen->create_and_enrol($course, 'student', ['firstname' => 'Yann', 'lastname' => 'Petit']);
        $this->teacher = $gen->create_and_enrol($course, 'teacher');
        $this->other = $gen->create_and_enrol($course, 'teacher');
        $stagegen = $gen->get_plugin_generator('mod_stage');
        $stagegen->assign_teacher($stage, $this->student->id, $this->teacher->id);
        $stagegen->assign_teacher($stage, $otherstudent->id, $this->other->id);

        $teamcourse = $gen->create_course(['fullname' => 'Équipe pédagogique']);
        $this->synthesis = $gen->create_module('epsynthesis', ['course' => $teamcourse->id]);
        epsynthesis_set_links($this->synthesis->id, [$this->epcmid]);

        // Participants : un inscrit en attente de la décision du responsable.
        $epgen = $gen->get_plugin_generator('mod_ep');
        $this->activity = $epgen->create_activity($this->ep, ['name' => 'Échographie', 'code' => 'EP-ECHO']);
        ep_set_activity_teachers($this->activity->id, [$this->teacher->id]);
        $otheractivity = $epgen->create_activity($this->ep, ['name' => 'Anesthésie']);
        ep_set_activity_teachers($otheractivity->id, [$this->other->id]);
        $registration = ep_register_to_activity($this->ep, $this->activity, $this->student->id, 3);
        $this->assertNull($registration->problem);

        // Syllabus : une proposition renvoyée avec un commentaire, une révision soumise.
        $sink = $this->redirectMessages();
        $academic = ep_get_type_by_code($this->ep->id, EP_TYPE_ACADEMIC);
        $created = ep_create_proposal($this->ep, (object) [
            'name' => 'Imagerie avancée', 'typeid' => $academic->id, 'ects' => 2,
        ], $this->teacher->id);
        $this->proposal = $DB->get_record('ep_activity', ['id' => $created->activityid], '*', MUST_EXIST);
        $epgen->write_syllabus($this->proposal, (int) $this->teacher->id, ['objectives' => 'Lire un scanner']);
        $this->assertSame('submitted', $epgen->submit_syllabus($this->proposal, (int) $this->teacher->id));
        $this->assertTrue($epgen->review_syllabus($this->proposal, (int) get_admin()->id, 'return', 'Préciser les objectifs.'));
        $epgen->write_syllabus($this->activity, (int) $this->teacher->id, ['objectives' => 'Mise à jour']);
        $this->assertSame('submitted', $epgen->submit_syllabus($this->activity, (int) $this->teacher->id));
        $sink->close();

        // Supplément : un élément de l'étudiant, à l'étape « référent ».
        ep_create_default_supplement_categories($this->ep->id);
        $categories = ep_get_supplement_categories($this->ep->id);
        $this->itemid = ep_create_supplement_item($this->ep, $this->student->id, reset($categories), (object) [
            'title' => 'Présidente du BDE', 'periodstart' => 1700000000,
        ]);
        $this->assertSame(
            EP_ROLE_REFERENT,
            $DB->get_field('ep_supplement_item', 'pendingrole', ['id' => $this->itemid])
        );
    }

    /**
     * Activités actives de la synthèse pour un utilisateur.
     *
     * @param \stdClass $user
     * @return array
     */
    private function active_links(\stdClass $user): array {
        return epsynthesis_get_active_links($this->synthesis->id, $user->id);
    }

    /**
     * L'enseignant retrouve dans chaque section ce qui l'attend, avec son activité d'origine, et
     * le total (sans la révision soumise, donnée pour information) dans la navigation.
     */
    public function test_teacher_sees_what_awaits_him(): void {
        $links = $this->active_links($this->teacher);
        $this->assertCount(1, $links);

        $counts = epsynthesis_count_todo($links, $this->teacher->id);
        $this->assertSame(
            ['participants' => 1, 'syllabi' => 1, 'submitted' => 1, 'supplement' => 1, 'total' => 3],
            (array) $counts
        );

        $participants = epsynthesis_get_todo_participants($links);
        $this->assertCount(1, $participants);
        $this->assertSame((int) $this->activity->id, (int) $participants[0]->id);
        $this->assertSame(1, $participants[0]->pendingcount);
        $this->assertSame($this->epcmid, $participants[0]->cmid);
        $this->assertSame('Promotion A3', $participants[0]->coursename);

        $syllabi = epsynthesis_get_todo_syllabi($links, $this->teacher->id);
        $this->assertCount(2, $syllabi);
        $bystatus = [];
        foreach ($syllabi as $row) {
            $bystatus[$row->status] = $row;
        }
        ksort($bystatus);
        $this->assertSame(['returned', 'submitted'], array_keys($bystatus));
        $returned = $bystatus['returned'];
        $this->assertSame((int) $this->proposal->id, (int) $returned->activityid);
        $this->assertSame('Préciser les objectifs.', $returned->reviewcomment);
        $this->assertTrue($returned->canedit);
        $this->assertTrue($returned->canview);
        $this->assertSame($this->epcmid, $returned->cmid);
        $this->assertSame((int) $this->activity->id, (int) $bystatus['submitted']->activityid);
        // Droit de rédaction calculé sans requête par ligne : même résultat que mod_ep.
        $context = \context_module::instance($this->epcmid);
        foreach ($syllabi as $row) {
            $activity = $row->activityid == $this->activity->id ? $this->activity : $this->proposal;
            $this->assertSame(ep_can_edit_syllabus($activity, $context, $this->teacher->id), $row->canedit);
        }

        $items = epsynthesis_get_todo_supplement_items($links, 0, 10);
        $this->assertSame([$this->itemid], array_map('intval', array_keys($items)));
        $this->assertSame($this->epcmid, $items[$this->itemid]->cmid);
        $this->assertSame([], epsynthesis_get_supplement_proofs($items, $links));

        $cm = get_coursemodule_from_instance('epsynthesis', $this->synthesis->id, 0, false, MUST_EXIST);
        $nav = epsynthesis_render_navlinks($cm, 'entries', $counts);
        $this->assertStringContainsString(get_string('todowithcount', 'mod_epsynthesis', 3), $nav);
        $summary = epsynthesis_render_todo_summary($cm, $counts);
        $this->assertStringContainsString(get_string('todosummary_supplement', 'mod_epsynthesis', 1), $summary);
    }

    /**
     * Un autre enseignant, responsable d'un autre EP et référent d'un autre étudiant, n'y voit
     * rien de ce qui revient au premier, et ne peut pas trancher son élément.
     */
    public function test_other_teacher_sees_nothing(): void {
        global $DB;

        $links = $this->active_links($this->other);
        $this->assertCount(1, $links, 'L\'autre enseignant a bien un périmètre dans l\'activité liée.');

        $counts = epsynthesis_count_todo($links, $this->other->id);
        $this->assertSame(
            ['participants' => 0, 'syllabi' => 0, 'submitted' => 0, 'supplement' => 0, 'total' => 0],
            (array) $counts
        );
        $this->assertSame([], epsynthesis_get_todo_participants($links));
        $this->assertSame([], epsynthesis_get_todo_syllabi($links, $this->other->id));
        $this->assertSame([], epsynthesis_get_todo_supplement_items($links));

        $cm = get_coursemodule_from_instance('epsynthesis', $this->synthesis->id, 0, false, MUST_EXIST);
        $this->assertSame('', epsynthesis_render_todo_summary($cm, $counts));
        $this->assertStringNotContainsString('(0)', epsynthesis_render_navlinks($cm, 'todo', $counts));

        $this->assertFalse(epsynthesis_decide_supplement_item($links, $this->itemid, $this->other->id, true, ''));
        $item = $DB->get_record('ep_supplement_item', ['id' => $this->itemid]);
        $this->assertSame(EP_ROLE_REFERENT, $item->pendingrole);
        $this->assertSame(EP_STATUS_PENDING, (int) $item->status);

        // Hors de toute activité liée, rien non plus.
        $stranger = $this->getDataGenerator()->create_user();
        $this->assertSame([], $this->active_links($stranger));
        $this->assertSame(0, epsynthesis_count_todo([], $stranger->id)->total);
    }

    /**
     * Décision directe du référent : l'élément passe à l'étape de la DEVE et sort de sa liste ; une
     * seconde décision sur le formulaire dépassé n'écrit rien.
     */
    public function test_referent_decides_supplement_item(): void {
        global $DB;

        $links = $this->active_links($this->teacher);
        $this->assertTrue(epsynthesis_decide_supplement_item($links, $this->itemid, $this->teacher->id, true, 'Vu.'));
        $item = $DB->get_record('ep_supplement_item', ['id' => $this->itemid]);
        $this->assertSame(EP_ROLE_DEVE, $item->pendingrole);
        $this->assertSame(EP_STATUS_PENDING, (int) $item->status);

        $this->assertSame(0, epsynthesis_count_todo($links, $this->teacher->id)->supplement);
        $this->assertSame([], epsynthesis_get_todo_supplement_items($links));
        $this->assertFalse(epsynthesis_decide_supplement_item($links, $this->itemid, $this->teacher->id, false, 'Non.'));
        $this->assertSame(EP_ROLE_DEVE, $DB->get_field('ep_supplement_item', 'pendingrole', ['id' => $this->itemid]));

        // Élément inexistant : refusé sans erreur.
        $this->assertFalse(epsynthesis_decide_supplement_item($links, $this->itemid + 1000, $this->teacher->id, true, ''));
    }

    /**
     * Un référent qui a aussi les capacités de la DEVE ne voit ici que ce qui lui revient comme
     * référent : un élément de son étudiant arrivé à l'étape de la DEVE n'y figure pas et ne peut
     * pas y être tranché.
     */
    public function test_deve_capabilities_are_ignored(): void {
        global $DB;

        $course = get_course($this->ep->course);
        $deve = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $stage = $DB->get_record('stage', ['course' => $course->id], '*', MUST_EXIST);
        $this->getDataGenerator()->get_plugin_generator('mod_stage')->assign_teacher($stage, $student->id, $deve->id);

        $categories = ep_get_supplement_categories($this->ep->id);
        $itemid = ep_create_supplement_item($this->ep, $student->id, reset($categories), (object) ['title' => 'Tutorat']);
        $DB->update_record('ep_supplement_item', (object) ['id' => $itemid, 'step' => 1, 'pendingrole' => EP_ROLE_DEVE]);

        $links = $this->active_links($deve);
        $this->assertCount(1, $links);
        $link = reset($links);
        $item = $DB->get_record('ep_supplement_item', ['id' => $itemid]);
        $this->assertTrue(ep_rights_can_decide_supplement_item($link->rights, $item), 'Décidable comme DEVE dans mod_ep.');
        $this->assertFalse(ep_rights_can_decide_supplement_item(epsynthesis_teacher_rights($link->rights), $item));

        $counts = epsynthesis_count_todo($links, $deve->id);
        $this->assertSame(0, $counts->supplement);
        $this->assertSame(0, $counts->total);
        $this->assertFalse(epsynthesis_decide_supplement_item($links, $itemid, $deve->id, true, ''));
        $this->assertSame(EP_STATUS_PENDING, (int) $DB->get_field('ep_supplement_item', 'status', ['id' => $itemid]));
    }

    /**
     * Les preuves de la page affichée sont lues en une fois, rattachées à leur élément, et une
     * preuve déposée dans le contexte d'une autre activité n'est pas retenue.
     */
    public function test_supplement_proofs(): void {
        $links = $this->active_links($this->teacher);
        $context = \context_module::instance($this->epcmid);
        $fs = get_file_storage();
        $record = [
            'contextid' => $context->id, 'component' => 'mod_ep', 'filearea' => EP_SUPPLEMENT_EVIDENCE_FILEAREA,
            'itemid' => $this->itemid, 'filepath' => '/',
        ];
        $fs->create_file_from_string($record + ['filename' => 'b-attestation.pdf'], '%PDF-1.4');
        $fs->create_file_from_string($record + ['filename' => 'a-mandat.pdf'], '%PDF-1.4');
        $elsewhere = \context_course::instance($this->ep->course);
        $fs->create_file_from_string(['contextid' => $elsewhere->id, 'filename' => 'intrus.pdf'] + $record, '%PDF-1.4');

        $items = epsynthesis_get_todo_supplement_items($links);
        $proofs = epsynthesis_get_supplement_proofs($items, $links);
        $this->assertSame([$this->itemid], array_keys($proofs));
        $this->assertSame(
            ['a-mandat.pdf', 'b-attestation.pdf'],
            array_map(fn($proof) => $proof->filename, $proofs[$this->itemid])
        );
    }

    /**
     * Un enseignant dont l'inscription au cours de la promotion est suspendue garde son rôle, mais
     * la synthèse ne lui montre plus rien de cette promotion.
     */
    public function test_suspended_enrolment_hides_promotion(): void {
        global $DB;
        $this->assertArrayHasKey($this->epcmid, epsynthesis_get_active_links($this->synthesis->id, $this->teacher->id));

        $courseid = $DB->get_field('ep', 'course', ['id' => $this->ep->id]);
        $enrol = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->update_user_enrol($enrol, $this->teacher->id, ENROL_USER_SUSPENDED);
        $this->assertSame([], epsynthesis_get_active_links($this->synthesis->id, $this->teacher->id));
    }
}
