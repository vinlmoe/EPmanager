<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Listes paginées en base, périmètre des enseignants, chargements groupés du catalogue et cache
 * de bilan de promotion.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_filtered_credits_sql
 * @covers     ::ep_get_filtered_credits
 * @covers     ::ep_count_filtered_credits
 * @covers     ::ep_get_credits_awaiting
 * @covers     ::ep_count_credits_awaiting
 * @covers     ::ep_rights_can_view
 * @covers     ::ep_enrolled_students_sql
 * @covers     ::ep_get_enrolled_students
 * @covers     ::ep_count_enrolled_students
 * @covers     ::ep_build_pilotage_rows
 * @covers     ::ep_get_pilotage_overview
 * @covers     ::ep_get_activities_taken_places
 * @covers     ::ep_get_activities_teachers
 * @covers     ::ep_get_user_active_registrations
 * @covers     \mod_ep\local\progress_cache
 */
final class lists_test extends \advanced_testcase {
    /** @var \stdClass Instance. */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass[] Étudiants, par prénom. */
    private $students = [];

    /** @var \stdClass Enseignant référent d'Alice, responsable de l'EP A. */
    private $teacher;

    /** @var \stdClass EP du catalogue A. */
    private $activitya;

    /** @var \stdClass EP du catalogue B. */
    private $activityb;

    /**
     * Trois étudiants (Alice, Bruno, Chloé), un enseignant référent d'Alice (via mod_stage) et
     * responsable de l'EP A, deux EP du catalogue, et des crédits de chaque sorte.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $stage = $gen->create_module('stage', ['course' => $course->id]);
        $this->ep = $gen->create_module('ep', ['course' => $course->id, 'currentstudyyear' => 3]);
        $this->context = \context_module::instance($this->ep->cmid);

        foreach (['Alice' => 'Durand', 'Bruno' => 'Martin', 'Chloé' => 'Petit'] as $first => $last) {
            $this->students[$first] = $gen->create_and_enrol($course, 'student', [
                'firstname' => $first, 'lastname' => $last,
            ]);
        }
        $this->teacher = $gen->create_and_enrol($course, 'teacher');
        $gen->get_plugin_generator('mod_stage')->assign_teacher($stage, $this->students['Alice']->id, $this->teacher->id);

        $epgen = $gen->get_plugin_generator('mod_ep');
        $this->activitya = $epgen->create_activity($this->ep, ['name' => 'EP A', 'ects' => 2, 'capacity' => 5]);
        $this->activityb = $epgen->create_activity($this->ep, ['name' => 'EP B', 'ects' => 1]);
        ep_set_activity_teachers($this->activitya->id, [$this->teacher->id]);

        // Alice : inscription à A (en attente), inscription à B (en attente), déclaration (en attente).
        ep_register_to_activity($this->ep, $this->activitya, $this->students['Alice']->id, 3);
        ep_register_to_activity($this->ep, $this->activityb, $this->students['Alice']->id, 3);
        $engagement = ep_get_type_by_code($this->ep->id, EP_TYPE_ENGAGEMENT);
        ep_create_credit($this->ep, $this->students['Alice']->id, $engagement, ['name' => 'Tutorat', 'claimedects' => 1]);
        // Bruno : inscription à A (en attente), déclaration validée.
        ep_register_to_activity($this->ep, $this->activitya, $this->students['Bruno']->id, 3);
        $epgen->create_validated_credit($this->ep, $this->students['Bruno']->id, EP_TYPE_SPORT, 2, 3);
        // Chloé : inscription à B (en attente), crédit automatique.
        ep_register_to_activity($this->ep, $this->activityb, $this->students['Chloé']->id, 3);
        $stagetype = ep_get_type_by_code($this->ep->id, EP_TYPE_STAGE);
        ep_create_credit($this->ep, $this->students['Chloé']->id, $stagetype, [
            'name' => 'Stage', 'claimedects' => 1, 'source' => EP_SOURCE_STAGE, 'sourceref' => 7,
        ]);
        $this->assertSame(7, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));
    }

    /**
     * Noms des étudiants d'une liste de crédits, dans l'ordre.
     *
     * @param array $credits
     * @return string[]
     */
    private function names(array $credits): array {
        $users = ep_get_credit_users($credits);
        return array_values(array_map(fn($credit) => $users[$credit->userid]->firstname, $credits));
    }

    /**
     * La liste et son comptage s'accordent, avec les filtres, le tri et la pagination en base.
     */
    public function test_filtered_credits_are_paginated_in_sql(): void {
        $all = ep_get_filtered_credits($this->ep->id, [], 'student', 'ASC');
        $this->assertCount(7, $all);
        $this->assertSame(7, ep_count_filtered_credits($this->ep->id));
        $this->assertSame(['Alice', 'Alice', 'Alice', 'Bruno', 'Bruno', 'Chloé', 'Chloé'], $this->names($all));

        $page = ep_get_filtered_credits($this->ep->id, [], 'student', 'ASC', null, null, 3, 2);
        $this->assertSame(['Bruno', 'Bruno'], $this->names($page));

        $filters = ['status' => EP_STATUS_PENDING, 'activityid' => $this->activitya->id];
        $this->assertSame(2, ep_count_filtered_credits($this->ep->id, $filters));
        $this->assertSame(['Bruno', 'Alice'], $this->names(ep_get_filtered_credits($this->ep->id, $filters, 'student', 'DESC')));

        $this->assertSame(2, ep_count_filtered_credits($this->ep->id, ['search' => 'chlo']));
        $this->assertSame(1, ep_count_filtered_credits($this->ep->id, ['search' => 'chlo', 'status' => EP_STATUS_PENDING]));
        $onlyone = ep_get_filtered_credits($this->ep->id, ['search' => 'durand'], 'name', 'ASC', null, null, 0, 1);
        $this->assertSame(['Alice'], $this->names($onlyone));
    }

    /**
     * Périmètre d'un enseignant : en lecture, tout le dossier de ses étudiants et les EP dont il
     * est responsable ; pour décider, seulement les déclarations de ses étudiants et les
     * inscriptions à ses EP, jamais un crédit automatique.
     */
    public function test_teacher_scope_in_view_and_decide_modes(): void {
        $rights = ep_get_user_rights($this->ep, $this->context, $this->teacher->id);
        $this->assertSame([(int) $this->students['Alice']->id], $rights->referentids);
        $this->assertSame([(int) $this->activitya->id], $rights->responsibleids);

        // Lecture : les 3 crédits d'Alice et l'inscription de Bruno à A.
        $view = ep_get_filtered_credits($this->ep->id, [], 'student', 'ASC', $rights->referentids, $rights->responsibleids);
        $this->assertSame(['Alice', 'Alice', 'Alice', 'Bruno'], $this->names($view));
        $this->assertSame(4, ep_count_filtered_credits($this->ep->id, [], $rights->referentids, $rights->responsibleids));

        // Décision : l'inscription d'Alice à B n'est pas à lui, même s'il est son référent.
        $awaiting = ep_get_credits_awaiting($this->ep, $rights, 'student', 'ASC');
        $this->assertSame(['Alice', 'Alice', 'Bruno'], $this->names($awaiting));
        foreach ($awaiting as $credit) {
            $this->assertTrue(ep_rights_can_validate($rights, $credit));
            $this->assertNotEquals($this->activityb->id, (int) $credit->activityid);
        }
        $this->assertSame(3, ep_count_credits_awaiting($this->ep, $rights));

        // Un enseignant sans périmètre ne voit rien, et n'est pas la DEVE.
        $other = $this->getDataGenerator()->create_and_enrol(get_course($this->ep->course), 'teacher');
        $otherrights = ep_get_user_rights($this->ep, $this->context, $other->id);
        $this->assertSame([], ep_get_credits_awaiting($this->ep, $otherrights));
        $this->assertSame(0, ep_count_filtered_credits($this->ep->id, [], [], []));

        // La DEVE voit les 5 demandes en attente (le crédit automatique et le sport sont validés).
        $deverights = ep_get_user_rights($this->ep, $this->context, get_admin()->id);
        $this->assertSame(5, ep_count_credits_awaiting($this->ep, $deverights));

        // Lecture d'un crédit : le référent voit l'inscription d'Alice à B sans pouvoir la décider.
        $aliceb = ep_get_active_registration($this->activityb->id, $this->students['Alice']->id);
        $this->assertTrue(ep_rights_can_view($rights, $aliceb));
        $this->assertFalse(ep_rights_can_validate($rights, $aliceb));
        $chloeb = ep_get_active_registration($this->activityb->id, $this->students['Chloé']->id);
        $this->assertFalse(ep_rights_can_view($rights, $chloeb));
    }

    /**
     * Les étudiants inscrits se listent avec recherche, restriction et pagination en base.
     */
    public function test_enrolled_students_search_and_pagination(): void {
        $this->assertSame(3, ep_count_enrolled_students($this->context));
        $this->assertSame(['Durand', 'Martin', 'Petit'], array_values(array_map(
            fn($u) => $u->lastname,
            ep_get_enrolled_students($this->context)
        )));
        $this->assertSame(['Martin'], array_values(array_map(
            fn($u) => $u->lastname,
            ep_get_enrolled_students($this->context, '', null, 1, 1)
        )));
        $this->assertSame(1, ep_count_enrolled_students($this->context, 'bruno mar'));
        $this->assertSame(2, ep_count_enrolled_students(
            $this->context,
            '',
            [$this->students['Alice']->id, $this->students['Chloé']->id]
        ));
        $this->assertSame(0, ep_count_enrolled_students($this->context, '', []));
        $this->assertSame([], ep_get_enrolled_students($this->context, '', []));
    }

    /**
     * Le bilan de promotion calculé à travers le cache est identique à celui calculé étudiant par
     * étudiant, et le cache est vidé après le calcul.
     */
    public function test_pilotage_rows_use_the_cache_and_match_direct_computation(): void {
        $rows = ep_get_pilotage_overview($this->ep, $this->context);
        $this->assertCount(3, $rows);
        $this->assertFalse(\mod_ep\local\progress_cache::covers((int) $this->ep->id));

        foreach ($rows as $row) {
            $direct = ep_get_student_progress($this->ep, $row->user->id);
            $this->assertEquals($direct, $row->progress, 'bilan de ' . $row->user->firstname);
        }
        $byname = [];
        foreach ($rows as $row) {
            $byname[$row->user->firstname] = $row;
        }
        $this->assertSame(3, $byname['Alice']->pendingcount);
        $this->assertSame(1, $byname['Bruno']->pendingcount);
        $this->assertSame(2.0, $byname['Bruno']->progress->totalretained);
        $this->assertSame(1.0, $byname['Chloé']->progress->totalretained);

        // Restreint aux étudiants du référent.
        $restricted = ep_get_pilotage_overview($this->ep, $this->context, [$this->students['Alice']->id]);
        $this->assertCount(1, $restricted);
        $this->assertSame('Alice', $restricted[0]->user->firstname);
    }

    /**
     * Le cache sert les lectures qu'il couvre et laisse passer les autres vers la base.
     */
    public function test_progress_cache_scope(): void {
        $alice = (int) $this->students['Alice']->id;
        $chloe = (int) $this->students['Chloé']->id;
        \mod_ep\local\progress_cache::prime((int) $this->ep->id, [$alice]);
        try {
            $this->assertCount(3, \mod_ep\local\progress_cache::credits((int) $this->ep->id, $alice));
            $this->assertNull(\mod_ep\local\progress_cache::credits((int) $this->ep->id, $chloe));
            $this->assertCount(2, ep_get_student_credits($this->ep->id, $chloe));
            $this->assertCount(6, ep_get_types($this->ep->id));
            $this->assertNull(\mod_ep\local\progress_cache::types(999));
        } finally {
            \mod_ep\local\progress_cache::clear();
        }
        $this->assertNull(\mod_ep\local\progress_cache::credits((int) $this->ep->id, $alice));
    }

    /**
     * Les chargements groupés du catalogue donnent les mêmes comptes que les lectures unitaires.
     */
    public function test_catalog_batch_loaders(): void {
        $taken = ep_get_activities_taken_places($this->ep->id);
        $this->assertSame(2, $taken[$this->activitya->id]);
        $this->assertSame(2, $taken[$this->activityb->id]);
        $this->assertSame(ep_get_activity_taken_places($this->activitya->id), $taken[$this->activitya->id]);

        $teachers = ep_get_activities_teachers($this->ep->id);
        $this->assertSame([(int) $this->teacher->id], array_keys($teachers[$this->activitya->id]));
        $this->assertArrayNotHasKey($this->activityb->id, $teachers);

        $alice = ep_get_user_active_registrations($this->ep->id, $this->students['Alice']->id);
        $this->assertSame([(int) $this->activityb->id, (int) $this->activitya->id], array_keys($alice));
        $this->assertSame([], ep_get_user_active_registrations($this->ep->id, $this->teacher->id));
    }
}
