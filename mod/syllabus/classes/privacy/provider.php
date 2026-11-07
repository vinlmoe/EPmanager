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

namespace mod_syllabus\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * RGPD de mod_syllabus.
 *
 * Les réponses aux évaluations (syllabus_response, syllabus_answer) ne portent aucun identifiant :
 * ce ne sont pas des données personnelles et elles ne peuvent être ni exportées ni effacées pour
 * un étudiant. Les résultats archivés des années passées (syllabus_evalarchive) sont agrégés et
 * anonymes, comme ces réponses. Est personnel le fait d'avoir répondu (syllabus_participant), d'être responsable
 * d'un module, d'avoir rédigé ou relu une version de sa fiche, d'intervenir dans une séance.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Données stockées.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'syllabus_teacher',
            ['userid' => 'privacy:metadata:teacher:userid'],
            'privacy:metadata:teacher'
        );
        $collection->add_database_table(
            'syllabus_version',
            ['authorid' => 'privacy:metadata:version:authorid',
            'reviewerid' => 'privacy:metadata:version:reviewerid', 'reviewcomment' => 'privacy:metadata:version:reviewcomment'],
            'privacy:metadata:version'
        );
        $collection->add_database_table(
            'syllabus_session',
            ['teacherid' => 'privacy:metadata:session:teacherid'],
            'privacy:metadata:session'
        );
        $collection->add_database_table(
            'syllabus_participant',
            ['userid' => 'privacy:metadata:participant:userid'],
            'privacy:metadata:participant'
        );
        $collection->add_database_table(
            'syllabus_response',
            ['daysubmitted' => 'privacy:metadata:response:daysubmitted'],
            'privacy:metadata:response'
        );
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:messages');
        return $collection;
    }

    /**
     * Requêtes : instance du module (s.id) pour un utilisateur.
     *
     * @return array table => [jointure vers s, champ utilisateur]
     */
    private static function sources(): array {
        return [
            'teacher' => ['JOIN {syllabus_teacher} x ON x.syllabusid = s.id', 'x.userid'],
            'participant' => ['JOIN {syllabus_participant} x ON x.syllabusid = s.id', 'x.userid'],
            'author' => ['JOIN {syllabus_version} x ON x.syllabusid = s.id', 'x.authorid'],
            'reviewer' => ['JOIN {syllabus_version} x ON x.syllabusid = s.id', 'x.reviewerid'],
            'session' => ['JOIN {syllabus_version} v ON v.syllabusid = s.id JOIN {syllabus_session} x ON x.versionid = v.id',
                'x.teacherid'],
        ];
    }

    /**
     * Contextes où l'utilisateur a des données.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::sources() as $key => [$join, $field]) {
            $contextlist->add_from_sql("SELECT ctx.id
                  FROM {syllabus} s
                  JOIN {modules} m ON m.name = 'syllabus'
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                  $join
                 WHERE $field = :userid", ['level' => CONTEXT_MODULE, 'userid' => $userid]);
        }
        return $contextlist;
    }

    /**
     * Utilisateurs qui ont des données dans un contexte.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module || !($cm = get_coursemodule_from_id('syllabus', $context->instanceid))) {
            return;
        }
        foreach (self::sources() as [$join, $field]) {
            $userlist->add_from_sql(
                'userid',
                "SELECT $field AS userid FROM {syllabus} s $join WHERE s.id = :id AND $field IS NOT NULL",
                ['id' => $cm->instance]
            );
        }
    }

    /**
     * Export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('syllabus', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $data = (object) [
                'teacher' => transform::yesno($DB->record_exists('syllabus_teacher', ['syllabusid' => $cm->instance,
                    'userid' => $userid])),
                'evaluated' => transform::yesno($DB->record_exists('syllabus_participant', ['syllabusid' => $cm->instance,
                    'userid' => $userid])),
                'versions' => [],
                'sessions' => [],
            ];
            foreach (
                $DB->get_records_select(
                    'syllabus_version',
                    'syllabusid = ? AND (authorid = ? OR reviewerid = ?)',
                    [$cm->instance, $userid, $userid],
                    'version'
                ) as $version
            ) {
                $data->versions[] = (object) ['version' => $version->version, 'status' => $version->status,
                    'author' => transform::yesno((int) $version->authorid === $userid),
                    'reviewer' => transform::yesno((int) $version->reviewerid === $userid),
                    'reviewcomment' => (int) $version->reviewerid === $userid ? $version->reviewcomment : '',
                    'timemodified' => transform::datetime($version->timemodified)];
            }
            foreach (
                $DB->get_records_sql(
                    'SELECT x.id, x.title, x.duration, v.version FROM {syllabus_session} x
                    JOIN {syllabus_version} v ON v.id = x.versionid WHERE v.syllabusid = ? AND x.teacherid = ?',
                    [$cm->instance, $userid]
                ) as $session
            ) {
                $data->sessions[] = (object) ['version' => $session->version, 'title' => $session->title,
                    'duration' => $session->duration];
            }
            writer::with_context($context)->export_data([get_string('pluginname', 'mod_syllabus')], $data);
        }
    }

    /**
     * Efface les données d'un module pour tous : y compris les réponses anonymes.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if (!$context instanceof \context_module || !($cm = get_coursemodule_from_id('syllabus', $context->instanceid))) {
            return;
        }
        $id = (int) $cm->instance;
        $responseids = $DB->get_fieldset_select('syllabus_response', 'id', 'syllabusid = ?', [$id]);
        if ($responseids) {
            [$insql, $params] = $DB->get_in_or_equal($responseids);
            $DB->delete_records_select('syllabus_answer', "responseid $insql", $params);
        }
        $DB->delete_records('syllabus_response', ['syllabusid' => $id]);
        $DB->delete_records('syllabus_participant', ['syllabusid' => $id]);
        $DB->delete_records('syllabus_teacher', ['syllabusid' => $id]);
        self::anonymise($id, null);
    }

    /**
     * Efface les données d'un utilisateur dans des contextes.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users($context, [(int) $contextlist->get_user()->id]);
        }
    }

    /**
     * Efface les données de plusieurs utilisateurs dans un contexte.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Participation et responsabilités retirées, nom effacé des versions et séances. La réponse
     * anonyme reste : rien ne la rattache à l'utilisateur.
     *
     * @param \context $context
     * @param int[] $userids
     */
    private static function delete_users(\context $context, array $userids): void {
        global $DB;
        if (
            !$userids || !$context instanceof \context_module
                || !($cm = get_coursemodule_from_id('syllabus', $context->instanceid))
        ) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $DB->delete_records_select(
            'syllabus_participant',
            "syllabusid = ? AND userid $insql",
            array_merge([$cm->instance], $params)
        );
        $DB->delete_records_select('syllabus_teacher', "syllabusid = ? AND userid $insql", array_merge([$cm->instance], $params));
        self::anonymise((int) $cm->instance, $userids);
    }

    /**
     * Retire les utilisateurs (tous si null) des versions et des séances d'un module.
     *
     * @param int $syllabusid
     * @param int[]|null $userids
     */
    private static function anonymise(int $syllabusid, ?array $userids): void {
        global $DB;
        $versionids = $DB->get_fieldset_select('syllabus_version', 'id', 'syllabusid = ?', [$syllabusid]);
        if (!$versionids) {
            return;
        }
        [$vsql, $vparams] = $DB->get_in_or_equal($versionids);
        $usersql = '';
        $userparams = [];
        if ($userids !== null) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids);
        }
        foreach (['authorid', 'reviewerid'] as $field) {
            $DB->set_field_select(
                'syllabus_version',
                $field,
                null,
                "id $vsql" . ($userids !== null ? " AND $field $usersql" : ''),
                array_merge($vparams, $userparams)
            );
        }
        $DB->set_field_select('syllabus_session', 'teacherid', null, "versionid $vsql"
            . ($userids !== null ? " AND teacherid $usersql" : ''), array_merge($vparams, $userparams));
    }
}
