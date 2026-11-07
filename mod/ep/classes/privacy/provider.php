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
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Le module conserve deux sortes de données personnelles.
 *
 * - Celles de l'étudiant : les EP portés à son crédit et l'historique de leurs décisions, leurs
 *   justificatifs, les éléments qu'il a demandés pour son supplément au diplôme et leurs preuves,
 *   les décisions sur ses années d'étude, les suppléments émis à son nom. Elles sont exportées et
 *   supprimées avec lui.
 * - Celles des personnels : les décisions qu'ils ont prises, les EP dont ils sont responsables,
 *   les syllabus qu'ils ont rédigés ou revus, les séances qu'ils assurent, les suppléments qu'ils
 *   ont émis, les imports qu'ils ont faits. Ces enregistrements appartiennent aussi au dossier
 *   d'autres personnes ou au catalogue : à l'effacement, ils sont conservés mais désolidarisés de
 *   la personne (référence vidée), sauf les affectations de responsable et le journal des
 *   imports, qui sont supprimés.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Références à un utilisateur, par table : jointure depuis l'instance (alias e) jusqu'à la
     * table (alias t), et champs qui désignent un utilisateur.
     *
     * @return array table => [jointure, champs]
     */
    protected static function references(): array {
        $activity = 'JOIN {ep_activity} a ON a.epid = e.id';
        return [
            'ep_credit' => ['JOIN {ep_credit} t ON t.epid = e.id', ['userid', 'validatedby', 'addedby']],
            'ep_credit_decision' => ['JOIN {ep_credit} c ON c.epid = e.id JOIN {ep_credit_decision} t ON t.creditid = c.id',
                ['userid']],
            'ep_activity' => ['JOIN {ep_activity} t ON t.epid = e.id', ['proposedby']],
            'ep_activity_teacher' => [$activity . ' JOIN {ep_activity_teacher} t ON t.activityid = a.id', ['teacherid']],
            'ep_syllabus' => [$activity . ' JOIN {ep_syllabus} t ON t.activityid = a.id', ['authorid', 'reviewerid']],
            'ep_session' => [$activity . ' JOIN {ep_syllabus} s ON s.activityid = a.id JOIN {ep_session} t ON t.syllabusid = s.id',
                ['teacherid']],
            'ep_year_validation' => ['JOIN {ep_year_validation} t ON t.epid = e.id', ['userid', 'decidedby']],
            'ep_supplement_item' => ['JOIN {ep_supplement_item} t ON t.epid = e.id', ['userid', 'validatedby']],
            'ep_supplement_issue' => ['JOIN {ep_supplement_issue} t ON t.epid = e.id', ['userid', 'issuedby']],
            'ep_import_log' => ['JOIN {ep_import_log} t ON t.epid = e.id', ['userid']],
        ];
    }

    /**
     * Décrit les données personnelles conservées par le plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $tables = [
            'ep_credit' => ['userid', 'name', 'description', 'studyyear', 'claimedects', 'retainedects', 'grade', 'status',
                'validatedby', 'addedby', 'validatorcomment', 'timecreated'],
            'ep_credit_decision' => ['userid', 'role', 'decision', 'grade', 'retainedects', 'comment', 'timecreated'],
            'ep_activity' => ['proposedby'],
            'ep_activity_teacher' => ['teacherid'],
            'ep_syllabus' => ['authorid', 'reviewerid', 'reviewcomment', 'timesubmitted', 'timereviewed'],
            'ep_session' => ['teacherid'],
            'ep_year_validation' => ['userid', 'studyyear', 'decision', 'retainedects', 'comment', 'decidedby', 'timedecided'],
            'ep_supplement_item' => ['userid', 'title', 'titleen', 'description', 'periodstart', 'periodend', 'status',
                'validatedby', 'validatorcomment', 'timecreated'],
            'ep_supplement_issue' => ['userid', 'number', 'lang', 'verifycode', 'issuedby', 'timeissued'],
            'ep_import_log' => ['userid', 'kind', 'itemcount', 'timecreated'],
        ];
        foreach ($tables as $table => $fields) {
            $described = [];
            foreach ($fields as $field) {
                $described[$field] = 'privacy:metadata:' . $table . ':' . $field;
            }
            $collection->add_database_table($table, $described, 'privacy:metadata:' . $table);
        }
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
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
        foreach (self::references() as [$join, $fields]) {
            foreach ($fields as $field) {
                $sql = "SELECT ctx.id
                          FROM {context} ctx
                          JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                          JOIN {modules} m ON m.id = cm.module AND m.name = 'ep'
                          JOIN {ep} e ON e.id = cm.instance
                          $join
                         WHERE t.$field = :userid";
                $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
            }
        }
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
        foreach (self::references() as [$join, $fields]) {
            foreach ($fields as $field) {
                $sql = "SELECT t.$field
                          FROM {course_modules} cm
                          JOIN {modules} m ON m.id = cm.module AND m.name = 'ep'
                          JOIN {ep} e ON e.id = cm.instance
                          $join
                         WHERE cm.id = :cmid AND t.$field IS NOT NULL";
                $userlist->add_from_sql($field, $sql, ['cmid' => $context->instanceid]);
            }
        }
    }

    /**
     * Exporte les données de l'utilisateur pour les contextes approuvés : son dossier d'étudiant,
     * puis ce qu'il a fait comme personnel. Les autres personnes n'y sont pas nommées.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $CFG, $DB;

        // Le fournisseur est chargé par l'autoload, sans passer par lib.php : les constantes du
        // module (zones de fichiers) doivent donc être demandées explicitement.
        require_once($CFG->dirroot . '/mod/ep/lib.php');

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('ep', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $epid = (int) $cm->instance;
            $writer = writer::with_context($context);
            $date = fn($time) => $time ? transform::datetime($time) : null;

            // Dossier de l'étudiant.
            $credits = $DB->get_records('ep_credit', ['epid' => $epid, 'userid' => $userid], 'timecreated, id');
            $decisions = self::decisions_by_credit(array_keys($credits));
            $path = [get_string('privacy:path:credits', 'mod_ep')];
            $data = [];
            foreach ($credits as $credit) {
                $data[] = (object) [
                    'name' => $credit->name,
                    'description' => $credit->description,
                    'studyyear' => $credit->studyyear,
                    'claimedects' => $credit->claimedects,
                    'retainedects' => $credit->retainedects,
                    'grade' => $credit->grade,
                    'status' => $credit->status,
                    'source' => $credit->source,
                    'validatorcomment' => $credit->validatorcomment,
                    'timecreated' => $date($credit->timecreated),
                    'timedecided' => $date($credit->validatetime),
                    'decisions' => array_map(fn($decision) => (object) [
                        'role' => $decision->role,
                        'decision' => $decision->decision,
                        'grade' => $decision->grade,
                        'retainedects' => $decision->retainedects,
                        'comment' => $decision->comment,
                        'time' => $date($decision->timecreated),
                    ], $decisions[$credit->id] ?? []),
                ];
                $writer->export_area_files(array_merge($path, [$credit->id]), 'mod_ep', EP_EVIDENCE_FILEAREA, $credit->id);
            }
            if ($data) {
                $writer->export_data($path, (object) ['credits' => $data]);
            }

            $items = $DB->get_records('ep_supplement_item', ['epid' => $epid, 'userid' => $userid], 'timecreated, id');
            $path = [get_string('privacy:path:supplementitems', 'mod_ep')];
            $data = [];
            foreach ($items as $item) {
                $data[] = (object) [
                    'title' => $item->title,
                    'titleen' => $item->titleen,
                    'description' => $item->description,
                    'periodstart' => $date($item->periodstart),
                    'periodend' => $date($item->periodend),
                    'status' => $item->status,
                    'validatorcomment' => $item->validatorcomment,
                    'timecreated' => $date($item->timecreated),
                    'timedecided' => $date($item->validatetime),
                ];
                $itempath = array_merge($path, [$item->id]);
                $writer->export_area_files($itempath, 'mod_ep', EP_SUPPLEMENT_EVIDENCE_FILEAREA, $item->id);
            }
            if ($data) {
                $writer->export_data($path, (object) ['items' => $data]);
            }

            $years = $DB->get_records('ep_year_validation', ['epid' => $epid, 'userid' => $userid], 'studyyear');
            if ($years) {
                $writer->export_data([get_string('privacy:path:years', 'mod_ep')], (object) ['years' => array_values(
                    array_map(fn($year) => (object) [
                        'studyyear' => $year->studyyear,
                        'decision' => $year->decision,
                        'retainedects' => $year->retainedects,
                        'comment' => $year->comment,
                        'timedecided' => $date($year->timedecided),
                    ], $years)
                )]);
            }

            $issues = $DB->get_records('ep_supplement_issue', ['epid' => $epid, 'userid' => $userid], 'timeissued, id');
            $path = [get_string('privacy:path:supplements', 'mod_ep')];
            $data = [];
            foreach ($issues as $issue) {
                $data[] = (object) [
                    'number' => $issue->number,
                    'lang' => $issue->lang,
                    'verifycode' => $issue->verifycode,
                    'timeissued' => $date($issue->timeissued),
                    'replaced' => transform::yesno(!empty($issue->replacedby)),
                ];
                $issuepath = array_merge($path, [$issue->number]);
                $writer->export_area_files($issuepath, 'mod_ep', EP_SUPPLEMENT_ISSUED_FILEAREA, $issue->id);
            }
            if ($data) {
                $writer->export_data($path, (object) ['supplements' => $data]);
            }

            self::export_staff_data($writer, $epid, $userid);
        }
    }

    /**
     * Partie « personnel » de l'export : décisions prises, responsabilités, syllabus, séances,
     * émissions, imports. Les étudiants concernés ne sont pas nommés.
     *
     * @param \core_privacy\local\request\content_writer $writer
     * @param int $epid
     * @param int $userid
     * @return void
     */
    protected static function export_staff_data($writer, int $epid, int $userid) {
        global $DB;

        $date = fn($time) => $time ? transform::datetime($time) : null;
        $staff = [];

        $sql = "SELECT d.id, c.name, d.role, d.decision, d.grade, d.retainedects, d.comment, d.timecreated
                  FROM {ep_credit_decision} d
                  JOIN {ep_credit} c ON c.id = d.creditid
                 WHERE c.epid = :epid AND d.userid = :userid AND c.userid <> :self
              ORDER BY d.timecreated, d.id";
        foreach ($DB->get_records_sql($sql, ['epid' => $epid, 'userid' => $userid, 'self' => $userid]) as $decision) {
            $staff['creditdecisions'][] = (object) [
                'credit' => $decision->name,
                'role' => $decision->role,
                'decision' => $decision->decision,
                'grade' => $decision->grade,
                'retainedects' => $decision->retainedects,
                'comment' => $decision->comment,
                'time' => $date($decision->timecreated),
            ];
        }
        $sql = "SELECT c.id, c.name, c.timecreated
                  FROM {ep_credit} c
                 WHERE c.epid = :epid AND c.addedby = :userid AND c.userid <> :self";
        foreach ($DB->get_records_sql($sql, ['epid' => $epid, 'userid' => $userid, 'self' => $userid]) as $credit) {
            $staff['creditsadded'][] = (object) ['credit' => $credit->name, 'time' => $date($credit->timecreated)];
        }

        $sql = "SELECT a.id, a.name, a.code
                  FROM {ep_activity} a
                  JOIN {ep_activity_teacher} t ON t.activityid = a.id
                 WHERE a.epid = :epid AND t.teacherid = :userid
              ORDER BY a.name";
        foreach ($DB->get_records_sql($sql, ['epid' => $epid, 'userid' => $userid]) as $activity) {
            $staff['activitiesincharge'][] = (object) ['code' => $activity->code, 'name' => $activity->name];
        }
        $proposed = $DB->get_records('ep_activity', ['epid' => $epid, 'proposedby' => $userid], 'name', 'id, code, name');
        foreach ($proposed as $activity) {
            $staff['activitiesproposed'][] = (object) ['code' => $activity->code, 'name' => $activity->name];
        }

        $sql = "SELECT s.id, a.name, s.version, s.status, s.authorid, s.reviewerid, s.reviewcomment, s.timesubmitted,
                       s.timereviewed
                  FROM {ep_syllabus} s
                  JOIN {ep_activity} a ON a.id = s.activityid
                 WHERE a.epid = :epid AND (s.authorid = :author OR s.reviewerid = :reviewer)
              ORDER BY a.name, s.version";
        foreach ($DB->get_records_sql($sql, ['epid' => $epid, 'author' => $userid, 'reviewer' => $userid]) as $syllabus) {
            $staff['syllabi'][] = (object) [
                'activity' => $syllabus->name,
                'version' => $syllabus->version,
                'status' => $syllabus->status,
                'author' => transform::yesno((int) $syllabus->authorid === $userid),
                'reviewer' => transform::yesno((int) $syllabus->reviewerid === $userid),
                'reviewcomment' => $syllabus->reviewcomment,
                'timesubmitted' => $date($syllabus->timesubmitted),
                'timereviewed' => $date($syllabus->timereviewed),
            ];
        }
        $sql = "SELECT se.id, a.name, s.version, se.title, se.duration
                  FROM {ep_session} se
                  JOIN {ep_syllabus} s ON s.id = se.syllabusid
                  JOIN {ep_activity} a ON a.id = s.activityid
                 WHERE a.epid = :epid AND se.teacherid = :userid
              ORDER BY a.name, s.version, se.sortorder";
        foreach ($DB->get_records_sql($sql, ['epid' => $epid, 'userid' => $userid]) as $session) {
            $staff['sessions'][] = (object) [
                'activity' => $session->name,
                'version' => $session->version,
                'title' => $session->title,
                'duration' => $session->duration,
            ];
        }

        $select = 'epid = :epid AND decidedby = :userid AND userid <> :self';
        $params = ['epid' => $epid, 'userid' => $userid, 'self' => $userid];
        foreach ($DB->get_records_select('ep_year_validation', $select, $params, 'timedecided') as $year) {
            $staff['yeardecisions'][] = (object) [
                'studyyear' => $year->studyyear,
                'decision' => $year->decision,
                'comment' => $year->comment,
                'time' => $date($year->timedecided),
            ];
        }
        $select = 'epid = :epid AND validatedby = :userid AND userid <> :self';
        foreach ($DB->get_records_select('ep_supplement_item', $select, $params, 'validatetime') as $item) {
            $staff['supplementitemdecisions'][] = (object) [
                'title' => $item->title,
                'status' => $item->status,
                'comment' => $item->validatorcomment,
                'time' => $date($item->validatetime),
            ];
        }
        $select = 'epid = :epid AND issuedby = :userid';
        $issued = $DB->get_records_select('ep_supplement_issue', $select, ['epid' => $epid, 'userid' => $userid], 'timeissued');
        foreach ($issued as $issue) {
            $staff['supplementsissued'][] = (object) ['number' => $issue->number, 'time' => $date($issue->timeissued)];
        }
        foreach ($DB->get_records('ep_import_log', ['epid' => $epid, 'userid' => $userid], 'timecreated') as $log) {
            $staff['imports'][] = (object) ['kind' => $log->kind, 'items' => $log->itemcount,
                'time' => $date($log->timecreated)];
        }

        if ($staff) {
            $writer->export_data([get_string('privacy:path:staff', 'mod_ep')], (object) $staff);
        }
    }

    /**
     * Décisions des crédits donnés, par crédit.
     *
     * @param array $creditids
     * @return array creditid => décisions
     */
    protected static function decisions_by_credit(array $creditids): array {
        global $DB;

        $result = [];
        foreach (array_chunk($creditids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            foreach ($DB->get_records_select('ep_credit_decision', "creditid $insql", $params, 'timecreated, id') as $decision) {
                $result[$decision->creditid][] = $decision;
            }
        }
        return $result;
    }

    /**
     * Supprime les données de tous les utilisateurs d'un contexte : dossiers des étudiants,
     * fichiers, journal des imports et affectations de responsable. Le catalogue et ses syllabus,
     * contenu pédagogique de l'activité, restent, désolidarisés de leurs auteurs.
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
        $epid = (int) $cm->instance;
        $fs = get_file_storage();
        foreach ([EP_EVIDENCE_FILEAREA, EP_SUPPLEMENT_EVIDENCE_FILEAREA, EP_SUPPLEMENT_ISSUED_FILEAREA] as $area) {
            $fs->delete_area_files($context->id, 'mod_ep', $area);
        }
        $DB->delete_records_select('ep_credit_decision', 'creditid IN (SELECT id FROM {ep_credit} WHERE epid = ?)', [$epid]);
        foreach (['ep_credit', 'ep_supplement_item', 'ep_year_validation', 'ep_supplement_issue', 'ep_import_log'] as $table) {
            $DB->delete_records($table, ['epid' => $epid]);
        }
        $activities = 'SELECT id FROM {ep_activity} WHERE epid = ?';
        $DB->delete_records_select('ep_activity_teacher', "activityid IN ($activities)", [$epid]);
        $DB->set_field('ep_activity', 'proposedby', null, ['epid' => $epid]);
        $DB->set_field_select('ep_syllabus', 'authorid', null, "activityid IN ($activities)", [$epid]);
        $DB->set_field_select('ep_syllabus', 'reviewerid', null, "activityid IN ($activities)", [$epid]);
        $syllabi = 'SELECT s.id FROM {ep_syllabus} s JOIN {ep_activity} a ON a.id = s.activityid WHERE a.epid = ?';
        $DB->set_field_select('ep_session', 'teacherid', null, "syllabusid IN ($syllabi)", [$epid]);
    }

    /**
     * Supprime les données d'un utilisateur dans les contextes approuvés.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userids = [(int) $contextlist->get_user()->id];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                self::delete_users_data($context, $userids);
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
            self::delete_users_data($context, $userlist->get_userids());
        }
    }

    /**
     * Supprime le dossier d'étudiant des utilisateurs donnés (crédits et décisions, éléments du
     * supplément, décisions d'année, suppléments émis, fichiers), leurs affectations de
     * responsable et leurs entrées du journal des imports ; vide leurs autres références
     * (décisions prises, syllabus, séances, émissions), qui appartiennent au dossier d'autres
     * étudiants ou au catalogue.
     *
     * @param \context_module $context
     * @param array $userids
     * @return void
     */
    protected static function delete_users_data(\context_module $context, array $userids) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/lib.php');

        if (empty($userids)) {
            return;
        }
        $cm = get_coursemodule_from_id('ep', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $epid = (int) $cm->instance;
        $fs = get_file_storage();
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params = $inparams + ['epid' => $epid];

        // Dossier d'étudiant.
        $owned = [
            'ep_credit' => EP_EVIDENCE_FILEAREA,
            'ep_supplement_item' => EP_SUPPLEMENT_EVIDENCE_FILEAREA,
            'ep_supplement_issue' => EP_SUPPLEMENT_ISSUED_FILEAREA,
        ];
        foreach ($owned as $table => $area) {
            $ids = $DB->get_fieldset_select($table, 'id', "epid = :epid AND userid $insql", $params);
            foreach ($ids as $id) {
                $fs->delete_area_files($context->id, 'mod_ep', $area, $id);
            }
            if ($table === 'ep_credit' && $ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    [$creditsql, $creditparams] = $DB->get_in_or_equal($chunk);
                    $DB->delete_records_select('ep_credit_decision', "creditid $creditsql", $creditparams);
                }
            }
            if ($table === 'ep_supplement_issue' && $ids) {
                // Une émission remplacée pointe vers celle qui la remplace : le lien est rompu.
                [$issuesql, $issueparams] = $DB->get_in_or_equal($ids);
                $DB->set_field_select($table, 'replacedby', null, "replacedby $issuesql", $issueparams);
            }
            $DB->delete_records_select($table, "epid = :epid AND userid $insql", $params);
        }
        $DB->delete_records_select('ep_year_validation', "epid = :epid AND userid $insql", $params);
        $DB->delete_records_select('ep_import_log', "epid = :epid AND userid $insql", $params);

        // Références de personnel, conservées mais désolidarisées.
        $references = [
            'ep_credit' => ['validatedby', 'addedby'],
            'ep_year_validation' => ['decidedby'],
            'ep_supplement_item' => ['validatedby'],
            'ep_supplement_issue' => ['issuedby'],
            'ep_activity' => ['proposedby'],
        ];
        foreach ($references as $table => $fields) {
            foreach ($fields as $field) {
                $DB->set_field_select($table, $field, null, "epid = :epid AND $field $insql", $params);
            }
        }
        $credits = 'SELECT id FROM {ep_credit} WHERE epid = :epid';
        $DB->set_field_select('ep_credit_decision', 'userid', null, "userid $insql AND creditid IN ($credits)", $params);
        $activities = 'SELECT id FROM {ep_activity} WHERE epid = :epid';
        $DB->delete_records_select('ep_activity_teacher', "teacherid $insql AND activityid IN ($activities)", $params);
        foreach (['authorid', 'reviewerid'] as $field) {
            $DB->set_field_select('ep_syllabus', $field, null, "$field $insql AND activityid IN ($activities)", $params);
        }
        $DB->set_field_select('ep_session', 'teacherid', null, "teacherid $insql AND syllabusid IN (SELECT s.id
            FROM {ep_syllabus} s JOIN {ep_activity} a ON a.id = s.activityid WHERE a.epid = :epid)", $params);
    }
}
