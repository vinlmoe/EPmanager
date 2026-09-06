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
 * Privacy provider for mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_epsynthesis\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tout ce que la synthèse affiche (étudiants, crédits, décisions) est lu dans les activités
 * mod_ep liées, qui en répondent elles-mêmes. La seule donnée personnelle qui lui soit propre est
 * la désignation des enseignants responsables des EP partagés qu'elle définit : ces EP
 * n'appartiennent à aucune promotion, leur contexte est donc celui de cette activité-ci.
 *
 * La table epsynthesis_link, elle, ne conserve que des identifiants de course-modules.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Décrit les données personnelles conservées par le plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('ep_activity_teacher', [
            'teacherid' => 'privacy:metadata:ep_activity_teacher:teacherid',
        ], 'privacy:metadata:ep_activity_teacher');

        return $collection;
    }

    /**
     * Contextes dans lesquels l'utilisateur a des données : les synthèses où il est désigné
     * responsable d'un EP partagé.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'epsynthesis'
                  JOIN {ep_activity} a ON a.synthesiscmid = cm.id
                  JOIN {ep_activity_teacher} at ON at.activityid = a.id
                 WHERE at.teacherid = :teacherid";

        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'teacherid' => $userid]);

        return $contextlist;
    }

    /**
     * Utilisateurs ayant des données dans un contexte donné.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $userlist->add_from_sql('teacherid', "SELECT at.teacherid
                  FROM {ep_activity} a
                  JOIN {ep_activity_teacher} at ON at.activityid = a.id
                 WHERE a.synthesiscmid = :cmid", ['cmid' => $context->instanceid]);
    }

    /**
     * Exporte les EP partagés dont l'utilisateur est responsable.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $sql = "SELECT a.id, a.name, a.ects, a.timecreated
                      FROM {ep_activity} a
                      JOIN {ep_activity_teacher} at ON at.activityid = a.id
                     WHERE a.synthesiscmid = :cmid AND at.teacherid = :teacherid
                  ORDER BY a.name ASC";
            $activities = $DB->get_records_sql($sql,
                ['cmid' => $context->instanceid, 'teacherid' => $user->id]);
            if (empty($activities)) {
                continue;
            }

            $data = [];
            foreach ($activities as $activity) {
                $data[] = (object) [
                    'name' => $activity->name,
                    'ects' => $activity->ects,
                    'timecreated' => \core_privacy\local\request\transform::datetime($activity->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('manageactivities', 'mod_epsynthesis')],
                (object) ['activities' => $data]);
        }
    }

    /**
     * Supprime les données de tous les utilisateurs d'un contexte : les EP partagés définis par
     * cette synthèse perdent leurs responsables. Les EP eux-mêmes et les inscriptions qui y
     * portent sont conservés — ils appartiennent aux dossiers des étudiants, dont mod_ep répond.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $activityids = $DB->get_fieldset_select('ep_activity', 'id', 'synthesiscmid = ?',
            [$context->instanceid]);
        if ($activityids) {
            [$insql, $inparams] = $DB->get_in_or_equal($activityids);
            $DB->delete_records_select('ep_activity_teacher', "activityid $insql", $inparams);
        }
    }

    /**
     * Supprime les données d'un utilisateur dans les contextes approuvés.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userids = [$contextlist->get_user()->id];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                self::delete_responsibilities_for_users($context, $userids);
            }
        }
    }

    /**
     * Supprime les données des utilisateurs approuvés dans un contexte.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_module) {
            self::delete_responsibilities_for_users($context, $userlist->get_userids());
        }
    }

    /**
     * Retire les utilisateurs donnés de la liste des responsables des EP partagés d'une synthèse.
     *
     * @param \context_module $context
     * @param array $userids
     * @return void
     */
    protected static function delete_responsibilities_for_users(\context_module $context, array $userids) {
        global $DB;

        if (empty($userids)) {
            return;
        }

        $activityids = $DB->get_fieldset_select('ep_activity', 'id', 'synthesiscmid = ?',
            [$context->instanceid]);
        if (empty($activityids)) {
            return;
        }

        [$actsql, $actparams] = $DB->get_in_or_equal($activityids, SQL_PARAMS_NAMED, 'a');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $DB->delete_records_select('ep_activity_teacher',
            "activityid $actsql AND teacherid $usersql", $actparams + $userparams);
    }
}
