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
 * Étape de sauvegarde de la structure d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Décrit l'arbre XML (ep.xml) d'une instance de mod_ep, ses annotations d'identifiants et ses
 * zones de fichiers.
 *
 * Le paramétrage de l'activité (types d'EP et leurs plafonds, catalogue interne et ses
 * responsables, minimums annuels) est toujours sauvegardé ; les crédits des étudiants et leurs
 * justificatifs ne le sont que si les données utilisateur sont demandées.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ep_activity_structure_step extends backup_activity_structure_step {
    /**
     * Construit la structure sauvegardée.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Instance : « course » et « id » ne sont pas sauvegardés, la restauration les recalcule.
        // « stagecmid » désigne un course-module mod_stage : la restauration le fait suivre vers la
        // copie de cette activité (voir restore_ep_activity_structure_step::after_restore()).
        $ep = new backup_nested_element('ep', ['id'], [
            'name', 'intro', 'introformat', 'currentstudyyear', 'mincursusects', 'stagecmid', 'timesynced',
            'timecreated', 'timemodified',
        ]);

        $types = new backup_nested_element('types');
        $type = new backup_nested_element('type', ['id'], [
            'code', 'name', 'description', 'enabled', 'acquisition', 'validationflow', 'grading',
            'grademax', 'gradepass', 'evidence', 'evidencetypes', 'maxclaimedects', 'stagescope',
            'insupplement', 'maxects', 'maxectsperyear', 'ectsperday', 'sortorder', 'timecreated',
            'timemodified',
        ]);

        // Le nom « activity » est celui de l'élément racine que prepare_activity_structure()
        // ajoute à toute activité : les EP du catalogue sont donc nommés « epactivity ».
        $activities = new backup_nested_element('epactivities');
        $activity = new backup_nested_element('epactivity', ['id'], [
            'typeid', 'name', 'description', 'ects', 'minstudyyear', 'maxstudyyear', 'capacity',
            'code', 'state', 'registrationstart', 'registrationend', 'datestart', 'dateend',
            'grading', 'grademax', 'gradepass', 'minects', 'prereqblocking',
            'sortorder', 'timecreated', 'timemodified',
        ]);

        $activityteachers = new backup_nested_element('activityteachers');
        $activityteacher = new backup_nested_element('activityteacher', ['id'], ['teacherid', 'timecreated']);

        $prereqs = new backup_nested_element('prereqs');
        $prereq = new backup_nested_element('prereq', ['id'], ['requiredid', 'timecreated']);

        $yearrequirements = new backup_nested_element('yearrequirements');
        $yearrequirement = new backup_nested_element('yearrequirement', ['id'], [
            'studyyear', 'requiredects', 'timecreated', 'timemodified',
        ]);

        $credits = new backup_nested_element('credits');
        $credit = new backup_nested_element('credit', ['id'], [
            'userid', 'typeid', 'activityid', 'studyyear', 'name', 'description',
            'claimedects', 'retainedects', 'status', 'step', 'pendingrole', 'grade', 'source', 'sourceref',
            'validatedby', 'addedby', 'validatetime', 'validatorcomment', 'timecreated', 'timemodified',
        ]);

        $decisions = new backup_nested_element('decisions');
        $decision = new backup_nested_element('decision', ['id'], [
            'step', 'role', 'decision', 'userid', 'grade', 'retainedects', 'comment', 'timecreated',
        ]);

        // Arbre : les types précèdent le catalogue puis les crédits, de sorte que la restauration
        // dispose déjà des correspondances d'identifiants dont ils dépendent.
        $ep->add_child($types);
        $types->add_child($type);

        $ep->add_child($activities);
        $activities->add_child($activity);
        $activity->add_child($activityteachers);
        $activityteachers->add_child($activityteacher);
        $activity->add_child($prereqs);
        $prereqs->add_child($prereq);

        $ep->add_child($yearrequirements);
        $yearrequirements->add_child($yearrequirement);

        $ep->add_child($credits);
        $credits->add_child($credit);
        $credit->add_child($decisions);
        $decisions->add_child($decision);

        // Sources.
        $ep->set_source_table('ep', ['id' => backup::VAR_ACTIVITYID]);

        $type->set_source_table('ep_type', ['epid' => backup::VAR_PARENTID], 'sortorder, id');
        $activity->set_source_table('ep_activity', ['epid' => backup::VAR_PARENTID], 'sortorder, id');
        $yearrequirement->set_source_table('ep_year_requirement', ['epid' => backup::VAR_PARENTID], 'studyyear');

        // Les responsables d'un EP du catalogue relèvent du paramétrage de l'activité et non des
        // données d'un étudiant : ils sont sauvegardés dans tous les cas, et la restauration
        // ignore ceux dont le compte n'est pas présent dans l'archive.
        $activityteacher->set_source_table('ep_activity_teacher', ['activityid' => backup::VAR_PARENTID], 'id');
        $prereq->set_source_table('ep_activity_prereq', ['activityid' => backup::VAR_PARENTID], 'id');

        if ($userinfo) {
            $credit->set_source_table('ep_credit', ['epid' => backup::VAR_PARENTID], 'id');
            $decision->set_source_table('ep_credit_decision', ['creditid' => backup::VAR_PARENTID], 'timecreated, id');
        }

        // Annotations d'identifiants.
        $activityteacher->annotate_ids('user', 'teacherid');
        $credit->annotate_ids('user', 'userid');
        $credit->annotate_ids('user', 'validatedby');
        $credit->annotate_ids('user', 'addedby');
        $decision->annotate_ids('user', 'userid');

        // Zones de fichiers. Les justificatifs : un ou plusieurs fichiers par crédit, itemid = id
        // du crédit.
        $ep->annotate_files('mod_ep', 'intro', null);
        if ($userinfo) {
            $credit->annotate_files('mod_ep', EP_EVIDENCE_FILEAREA, 'id');
        }

        return $this->prepare_activity_structure($ep);
    }
}
