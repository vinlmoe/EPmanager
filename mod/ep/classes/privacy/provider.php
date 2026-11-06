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
 * Privacy provider for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ep\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Le module conserve, par étudiant, les enseignements personnalisés portés à son crédit
 * (inscriptions, déclarations, attributions automatiques) et, par enseignant, les décisions qu'il
 * a prises et les EP du catalogue dont il est responsable.
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
        $collection->add_database_table('ep_credit', [
            'userid' => 'privacy:metadata:ep_credit:userid',
            'name' => 'privacy:metadata:ep_credit:name',
            'description' => 'privacy:metadata:ep_credit:description',
            'claimedects' => 'privacy:metadata:ep_credit:claimedects',
            'retainedects' => 'privacy:metadata:ep_credit:retainedects',
            'status' => 'privacy:metadata:ep_credit:status',
            'validatedby' => 'privacy:metadata:ep_credit:validatedby',
            'validatorcomment' => 'privacy:metadata:ep_credit:validatorcomment',
            'timecreated' => 'privacy:metadata:ep_credit:timecreated',
        ], 'privacy:metadata:ep_credit');

        $collection->add_database_table('ep_activity_teacher', [
            'teacherid' => 'privacy:metadata:ep_activity_teacher:teacherid',
        ], 'privacy:metadata:ep_activity_teacher');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $collection;
    }

    /**
     * Contextes dans lesquels l'utilisateur a des données.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'ep'
                  JOIN {ep} e ON e.id = cm.instance
                  JOIN {ep_credit} c ON c.epid = e.id
                 WHERE c.userid = :userid OR c.validatedby = :validatedby";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'validatedby' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'ep'
                  JOIN {ep} e ON e.id = cm.instance
                  JOIN {ep_activity} a ON a.epid = e.id
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

        $params = ['cmid' => $context->instanceid];

        $userlist->add_from_sql('userid', "SELECT c.userid
                  FROM {course_modules} cm
                  JOIN {ep} e ON e.id = cm.instance
                  JOIN {ep_credit} c ON c.epid = e.id
                 WHERE cm.id = :cmid", $params);

        $userlist->add_from_sql('validatedby', "SELECT c.validatedby
                  FROM {course_modules} cm
                  JOIN {ep} e ON e.id = cm.instance
                  JOIN {ep_credit} c ON c.epid = e.id
                 WHERE cm.id = :cmid AND c.validatedby IS NOT NULL", $params);

        $userlist->add_from_sql('teacherid', "SELECT at.teacherid
                  FROM {course_modules} cm
                  JOIN {ep} e ON e.id = cm.instance
                  JOIN {ep_activity} a ON a.epid = e.id
                  JOIN {ep_activity_teacher} at ON at.activityid = a.id
                 WHERE cm.id = :cmid", $params);
    }

    /**
     * Exporte les données de l'utilisateur pour les contextes approuvés.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $CFG, $DB;

        // Le fournisseur est chargé par l'autoload, sans passer par lib.php : les constantes du
        // module (zone de fichiers des justificatifs) doivent donc être demandées explicitement.
        require_once($CFG->dirroot . '/mod/ep/lib.php');

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('ep', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $credits = $DB->get_records('ep_credit', ['epid' => $cm->instance, 'userid' => $user->id]);
            if (empty($credits)) {
                continue;
            }

            $data = [];
            foreach ($credits as $credit) {
                $data[] = (object) [
                    'name' => $credit->name,
                    'description' => $credit->description,
                    'studyyear' => $credit->studyyear,
                    'claimedects' => $credit->claimedects,
                    'retainedects' => $credit->retainedects,
                    'status' => $credit->status,
                    'source' => $credit->source,
                    'validatorcomment' => $credit->validatorcomment,
                    'timecreated' => \core_privacy\local\request\transform::datetime($credit->timecreated),
                ];

                writer::with_context($context)->export_area_files(
                    [get_string('privacy:path:credits', 'mod_ep'), $credit->id],
                    'mod_ep',
                    EP_EVIDENCE_FILEAREA,
                    $credit->id
                );
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:path:credits', 'mod_ep')],
                (object) ['credits' => $data]
            );
        }
    }

    /**
     * Supprime les données de tous les utilisateurs d'un contexte.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/lib.php');

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('ep', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        get_file_storage()->delete_area_files($context->id, 'mod_ep', EP_EVIDENCE_FILEAREA);
        $DB->delete_records('ep_credit', ['epid' => $cm->instance]);

        $activityids = $DB->get_fieldset_select('ep_activity', 'id', 'epid = ?', [$cm->instance]);
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
                self::delete_credits_for_users($context, $userids);
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
            self::delete_credits_for_users($context, $userlist->get_userids());
        }
    }

    /**
     * Supprime les crédits et les affectations de responsable des utilisateurs donnés dans un
     * contexte. Les décisions prises par un enseignant sur les crédits d'autrui sont conservées
     * mais désolidarisées de lui (validatedby vidé) : le crédit d'un étudiant tiers ne doit pas
     * disparaître parce que son validateur exerce son droit à l'effacement.
     *
     * @param \context_module $context
     * @param array $userids
     * @return void
     */
    protected static function delete_credits_for_users(\context_module $context, array $userids) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/lib.php');

        if (empty($userids)) {
            return;
        }
        $cm = get_coursemodule_from_id('ep', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params = $inparams + ['epid' => $cm->instance];

        $creditids = $DB->get_fieldset_select('ep_credit', 'id', "epid = :epid AND userid $insql", $params);
        foreach ($creditids as $creditid) {
            get_file_storage()->delete_area_files($context->id, 'mod_ep', EP_EVIDENCE_FILEAREA, $creditid);
        }
        $DB->delete_records_select('ep_credit', "epid = :epid AND userid $insql", $params);
        $DB->set_field_select(
            'ep_credit',
            'validatedby',
            null,
            "epid = :epid AND validatedby $insql",
            $params
        );

        $activityids = $DB->get_fieldset_select('ep_activity', 'id', 'epid = ?', [$cm->instance]);
        if ($activityids) {
            [$actsql, $actparams] = $DB->get_in_or_equal($activityids, SQL_PARAMS_NAMED, 'a');
            $DB->delete_records_select(
                'ep_activity_teacher',
                "activityid $actsql AND teacherid $insql",
                $actparams + $inparams
            );
        }
    }
}
