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
 * Événements du circuit d'un enseignement personnalisé : chaque transition laisse dans les journaux
 * Moodle une trace rattachée au module, au crédit et à l'étudiant, avec le statut résultant. Les
 * événements sont construits directement ici, indépendamment des fonctions qui les déclenchent.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_ep\event\credit_event_base
 * @covers     \mod_ep\event\credit_created
 * @covers     \mod_ep\event\credit_updated
 * @covers     \mod_ep\event\credit_validated
 * @covers     \mod_ep\event\credit_rejected
 * @covers     \mod_ep\event\credit_cancelled
 * @covers     \mod_ep\event\credit_synced
 * @covers     \mod_ep\event\course_module_viewed
 */
final class events_test extends \advanced_testcase {
    /**
     * Prépare une activité, un étudiant inscrit et un crédit validé de cet étudiant.
     *
     * @return array Instance, module, contexte, étudiant, crédit.
     */
    private function prepare(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $ep = $this->getDataGenerator()->create_module('ep', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('ep', $ep->id);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $credit = $this->getDataGenerator()->get_plugin_generator('mod_ep')
            ->create_validated_credit($ep, $student->id, EP_TYPE_ENGAGEMENT, 2);
        return [$ep, $cm, $context, $student, $credit];
    }

    /**
     * Les événements du circuit, chacun avec les données propres qu'il attend dans « other »
     * (en plus du statut et du type, communs à tous).
     *
     * @return array Nom de classe => données propres.
     */
    private static function credit_event_specs(): array {
        return [
            event\credit_created::class => ['origin' => 'declaration'],
            event\credit_updated::class => [],
            event\credit_validated::class => ['retainedects' => 1.5],
            event\credit_rejected::class => ['role' => 'teacher'],
            event\credit_cancelled::class => ['role' => 'student'],
            event\credit_synced::class => ['action' => 'updated', 'sourceref' => 17],
        ];
    }

    /**
     * Fournit à PHPUnit la liste des classes d'événements du circuit.
     *
     * @return array
     */
    public static function credit_event_classes(): array {
        $cases = [];
        foreach (self::credit_event_specs() as $class => $extra) {
            $cases[$class] = [$class, $extra];
        }
        return $cases;
    }

    /**
     * Construit les données de création d'un événement du circuit pour un crédit donné.
     *
     * @param \stdClass $credit
     * @param \context_module $context
     * @param array $extra Données propres à l'événement, fusionnées dans « other ».
     * @return array
     */
    private function event_data(\stdClass $credit, \context_module $context, array $extra = []): array {
        return [
            'objectid' => $credit->id,
            'context' => $context,
            'relateduserid' => $credit->userid,
            'other' => array_merge([
                'status' => (int) $credit->status,
                'typeid' => (int) $credit->typeid,
            ], $extra),
        ];
    }

    /**
     * Chaque événement du circuit porte le crédit, l'étudiant, le module, un nom, une description
     * et une URL exploitables, et le statut et le type du crédit dans « other ».
     */
    public function test_credit_events_carry_credit_student_and_module(): void {
        [, $cm, $context, $student, $credit] = $this->prepare();

        $sink = $this->redirectEvents();
        foreach (self::credit_event_specs() as $class => $extra) {
            $event = $class::create($this->event_data($credit, $context, $extra));
            $event->trigger();
        }
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame(array_keys(self::credit_event_specs()), array_map('get_class', $events));
        foreach ($events as $event) {
            $this->assertInstanceOf(event\credit_event_base::class, $event);
            $this->assertSame((int) $credit->id, (int) $event->objectid);
            $this->assertSame('ep_credit', $event->objecttable);
            $this->assertSame((int) $student->id, (int) $event->relateduserid);
            $this->assertSame((int) $cm->id, (int) $event->contextinstanceid);
            $this->assertNotEmpty($event->get_name());
            $this->assertStringContainsString("#{$credit->id}", $event->get_description());
            $this->assertStringContainsString("#{$student->id}", $event->get_description());
            $this->assertSame((int) $cm->id, (int) $event->get_url()->get_param('id'));
            $this->assertSame((int) $credit->id, (int) $event->get_url()->get_param('creditid'));
            $this->assertSame(EP_STATUS_VALIDATED, $event->other['status']);
            $this->assertSame((int) $credit->typeid, $event->other['typeid']);
        }

        $this->assertSame('c', $events[0]->crud);
        $this->assertSame('u', $events[1]->crud);
        $this->assertSame('u', $events[2]->crud);
        $this->assertSame('u', $events[3]->crud);
        $this->assertSame('u', $events[4]->crud);
        $this->assertSame('u', $events[5]->crud);
    }

    /**
     * La description de chaque événement reflète les données propres portées par « other » :
     * origine de la demande, ECTS retenus, rôle de celui qui refuse ou annule, action de la
     * synchronisation.
     */
    public function test_descriptions_reflect_other_data(): void {
        [, , $context, , $credit] = $this->prepare();

        $describe = function (string $class, array $extra) use ($credit, $context): string {
            return $class::create($this->event_data($credit, $context, $extra))->get_description();
        };

        $this->assertStringContainsString(
            'inscription à un EP du catalogue',
            $describe(event\credit_created::class, ['origin' => 'registration'])
        );
        $this->assertStringContainsString(
            'déclaration hors catalogue',
            $describe(event\credit_created::class, ['origin' => 'declaration'])
        );
        $this->assertStringContainsString(
            'en tant que DEVE',
            $describe(event\credit_created::class, ['origin' => 'deve'])
        );
        $this->assertStringContainsString(
            'attribué automatiquement',
            $describe(event\credit_created::class, ['origin' => 'stage'])
        );
        $this->assertStringContainsString(
            'a demandé',
            $describe(event\credit_created::class, [])
        );

        $this->assertStringContainsString('a modifié', $describe(event\credit_updated::class, []));

        $this->assertStringContainsString(
            '1,5 ECTS',
            $describe(event\credit_validated::class, ['retainedects' => 1.5])
        );
        $this->assertStringContainsString(
            '2 ECTS',
            $describe(event\credit_validated::class, ['retainedects' => 2.0])
        );

        $this->assertStringContainsString(
            "en tant qu'enseignant",
            $describe(event\credit_rejected::class, ['role' => 'teacher'])
        );
        $this->assertStringContainsString(
            "en tant que responsable de l'EP",
            $describe(event\credit_rejected::class, ['role' => 'owner'])
        );
        $this->assertStringContainsString(
            "en tant qu'enseignant référent",
            $describe(event\credit_validated::class, ['role' => 'referent', 'final' => false, 'retainedects' => 1.0])
        );
        $this->assertStringContainsString(
            "inscrit, depuis la liste des participants",
            $describe(event\credit_created::class, ['origin' => 'added'])
        );
        $this->assertStringContainsString(
            'en tant que DEVE',
            $describe(event\credit_rejected::class, ['role' => 'deve'])
        );

        $this->assertStringContainsString(
            "en tant qu'étudiant",
            $describe(event\credit_cancelled::class, ['role' => 'student'])
        );
        $this->assertStringContainsString(
            'en tant que DEVE',
            $describe(event\credit_cancelled::class, ['role' => 'deve'])
        );
        $this->assertStringContainsString(
            "en tant que responsable de l'EP",
            $describe(event\credit_cancelled::class, ['role' => 'owner'])
        );

        $this->assertStringContainsString(
            'a créé, par synchronisation du stage #17,',
            $describe(event\credit_synced::class, ['action' => 'created', 'sourceref' => 17])
        );
        $this->assertStringContainsString(
            'a mis à jour, par synchronisation du stage #17,',
            $describe(event\credit_synced::class, ['action' => 'updated', 'sourceref' => 17])
        );
        $this->assertStringContainsString(
            'a retiré, par synchronisation du stage #17,',
            $describe(event\credit_synced::class, ['action' => 'deleted', 'sourceref' => 17])
        );
    }

    /**
     * Un crédit retiré par la synchronisation n'existe plus en base : l'événement porte quand même
     * son ancien identifiant et le déclenchement passe.
     */
    public function test_synced_deletion_keeps_former_credit_id(): void {
        global $DB;
        [, $cm, $context, $student, $credit] = $this->prepare();
        $data = $this->event_data($credit, $context, ['action' => 'deleted', 'sourceref' => 17]);
        $DB->delete_records('ep_credit', ['id' => $credit->id]);

        $sink = $this->redirectEvents();
        $event = event\credit_synced::create($data);
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertSame((int) $credit->id, (int) $events[0]->objectid);
        $this->assertSame((int) $student->id, (int) $events[0]->relateduserid);
        $this->assertSame((int) $cm->id, (int) $events[0]->contextinstanceid);
        $this->assertStringContainsString("a retiré, par synchronisation du stage #17, l'enseignement personnalisé"
            . " #{$credit->id}", $events[0]->get_description());
    }

    /**
     * Sans étudiant, l'événement ne peut pas être construit.
     *
     * @dataProvider credit_event_classes
     * @param string $class
     * @param array $extra
     */
    public function test_missing_relateduserid_throws(string $class, array $extra): void {
        [, , $context, , $credit] = $this->prepare();
        $data = $this->event_data($credit, $context, $extra);
        unset($data['relateduserid']);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('relateduserid');
        $class::create($data);
    }

    /**
     * Sans statut résultant dans « other », l'événement ne peut pas être construit.
     *
     * @dataProvider credit_event_classes
     * @param string $class
     * @param array $extra
     */
    public function test_missing_status_throws(string $class, array $extra): void {
        [, , $context, , $credit] = $this->prepare();
        $data = $this->event_data($credit, $context, $extra);
        unset($data['other']['status']);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('status');
        $class::create($data);
    }

    /**
     * Sans type dans « other », l'événement ne peut pas être construit.
     *
     * @dataProvider credit_event_classes
     * @param string $class
     * @param array $extra
     */
    public function test_missing_typeid_throws(string $class, array $extra): void {
        [, , $context, , $credit] = $this->prepare();
        $data = $this->event_data($credit, $context, $extra);
        unset($data['other']['typeid']);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('typeid');
        $class::create($data);
    }

    /**
     * Les journaux restaurés retrouvent le crédit, son type et l'EP du catalogue.
     */
    public function test_restore_mappings(): void {
        $this->assertSame(
            ['db' => 'ep_credit', 'restore' => 'ep_credit'],
            event\credit_event_base::get_objectid_mapping()
        );
        $mapping = event\credit_event_base::get_other_mapping();
        $this->assertSame(['db' => 'ep_type', 'restore' => 'ep_type'], $mapping['typeid']);
        $this->assertSame(['db' => 'ep_activity', 'restore' => 'ep_activity'], $mapping['activityid']);
    }

    /**
     * La consultation de l'activité est l'événement standard de Moodle, rattaché à l'instance.
     */
    public function test_course_module_viewed_event(): void {
        [$ep, $cm, $context] = $this->prepare();

        $sink = $this->redirectEvents();
        $event = event\course_module_viewed::create(['objectid' => $ep->id, 'context' => $context]);
        $event->trigger();
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\core\event\course_module_viewed::class, $events[0]);
        $this->assertSame('ep', $events[0]->objecttable);
        $this->assertSame((int) $ep->id, (int) $events[0]->objectid);
        $this->assertSame((int) $cm->id, (int) $events[0]->contextinstanceid);
        $this->assertNotEmpty($events[0]->get_name());
        $this->assertSame(['db' => 'ep', 'restore' => 'ep'], event\course_module_viewed::get_objectid_mapping());
    }
}
