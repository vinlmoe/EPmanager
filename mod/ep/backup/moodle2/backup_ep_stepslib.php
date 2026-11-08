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
            'name', 'intro', 'introformat', 'currentstudyyear', 'mincursusects', 'stagecmid',
            'proposalsdirectpublish', 'supplementflow', 'supplementevidence', 'supplementprefix', 'birthdatefield',
            'pdfheader', 'pdffooter', 'timesynced', 'registrationstart', 'registrationend',
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
            'grading', 'grademax', 'gradepass', 'minects', 'proposedby', 'timepublished', 'prereqblocking',
            'sortorder', 'syllabuscmid', 'timecreated', 'timemodified',
        ]);

        $activityteachers = new backup_nested_element('activityteachers');
        $activityteacher = new backup_nested_element('activityteacher', ['id'], ['teacherid', 'timecreated']);

        $syllabi = new backup_nested_element('syllabi');
        $syllabus = new backup_nested_element('syllabus', ['id'], [
            'version', 'status', 'objectives', 'outcomes', 'prerequisitestext', 'content', 'methods', 'assessment',
            'resit', 'bibliography', 'language', 'personalhours', 'authorid', 'reviewerid', 'reviewcomment',
            'timesubmitted', 'timereviewed', 'timecreated', 'timemodified',
        ]);
        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'sortorder', 'title', 'kind', 'duration', 'teacherid', 'teachername', 'timecreated',
        ]);

        $suppcategories = new backup_nested_element('suppcategories');
        $suppcategory = new backup_nested_element('suppcategory', ['id'], [
            'name', 'nameen', 'description', 'enabled', 'sortorder', 'timecreated', 'timemodified',
        ]);
        $supptexts = new backup_nested_element('supptexts');
        $supptext = new backup_nested_element('supptext', ['id'], ['section', 'lang', 'content', 'timemodified']);
        $suppitems = new backup_nested_element('suppitems');
        $suppitem = new backup_nested_element('suppitem', ['id'], [
            'userid', 'categoryid', 'title', 'titleen', 'description', 'periodstart', 'periodend', 'status', 'step',
            'pendingrole', 'validatedby', 'validatetime', 'validatorcomment', 'timecreated', 'timemodified',
        ]);
        $suppissues = new backup_nested_element('suppissues');
        $suppissue = new backup_nested_element('suppissue', ['id'], [
            'userid', 'number', 'lang', 'verifycode', 'filehash', 'seal', 'issuedby', 'timeissued', 'replacedby',
        ]);
        $yearvalidations = new backup_nested_element('yearvalidations');
        $yearvalidation = new backup_nested_element('yearvalidation', ['id'], [
            'userid', 'studyyear', 'decision', 'retainedects', 'comment', 'decidedby', 'timedecided',
        ]);

        $notifications = new backup_nested_element('notifications');
        $notification = new backup_nested_element('notification', ['id'], [
            'msgkey', 'enabled', 'subject', 'body', 'timemodified',
        ]);

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
        $activity->add_child($syllabi);
        $syllabi->add_child($syllabus);
        $syllabus->add_child($sessions);
        $sessions->add_child($session);
        $ep->add_child($notifications);
        $notifications->add_child($notification);
        $ep->add_child($suppcategories);
        $suppcategories->add_child($suppcategory);
        $ep->add_child($supptexts);
        $supptexts->add_child($supptext);
        $ep->add_child($suppitems);
        $suppitems->add_child($suppitem);
        $ep->add_child($suppissues);
        $suppissues->add_child($suppissue);
        $ep->add_child($yearvalidations);
        $yearvalidations->add_child($yearvalidation);

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
        // Le syllabus et son programme relèvent du paramétrage du catalogue : toujours sauvegardés.
        $syllabus->set_source_table('ep_syllabus', ['activityid' => backup::VAR_PARENTID], 'version, id');
        $session->set_source_table('ep_session', ['syllabusid' => backup::VAR_PARENTID], 'sortorder, id');
        $notification->set_source_table('ep_notification', ['epid' => backup::VAR_PARENTID], 'msgkey');
        // Catégories et textes du supplément : paramétrage, toujours sauvegardés.
        $suppcategory->set_source_table('ep_supplement_category', ['epid' => backup::VAR_PARENTID], 'sortorder, id');
        $supptext->set_source_table('ep_supplement_text', ['epid' => backup::VAR_PARENTID], 'section, lang');

        if ($userinfo) {
            $credit->set_source_table('ep_credit', ['epid' => backup::VAR_PARENTID], 'id');
            $decision->set_source_table('ep_credit_decision', ['creditid' => backup::VAR_PARENTID], 'timecreated, id');
            $suppitem->set_source_table('ep_supplement_item', ['epid' => backup::VAR_PARENTID], 'id');
            $suppissue->set_source_table('ep_supplement_issue', ['epid' => backup::VAR_PARENTID], 'id');
            $yearvalidation->set_source_table('ep_year_validation', ['epid' => backup::VAR_PARENTID], 'id');
        }

        // Annotations d'identifiants.
        $activityteacher->annotate_ids('user', 'teacherid');
        $credit->annotate_ids('user', 'userid');
        $credit->annotate_ids('user', 'validatedby');
        $credit->annotate_ids('user', 'addedby');
        $activity->annotate_ids('user', 'proposedby');
        $syllabus->annotate_ids('user', 'authorid');
        $syllabus->annotate_ids('user', 'reviewerid');
        $session->annotate_ids('user', 'teacherid');
        $suppitem->annotate_ids('user', 'userid');
        $suppitem->annotate_ids('user', 'validatedby');
        $suppissue->annotate_ids('user', 'userid');
        $suppissue->annotate_ids('user', 'issuedby');
        $yearvalidation->annotate_ids('user', 'userid');
        $yearvalidation->annotate_ids('user', 'decidedby');
        $decision->annotate_ids('user', 'userid');

        // Zones de fichiers. Les justificatifs : un ou plusieurs fichiers par crédit, itemid = id
        // du crédit.
        $ep->annotate_files('mod_ep', 'intro', null);
        $ep->annotate_files('mod_ep', EP_PDFLOGO_FILEAREA, null);
        $ep->annotate_files('mod_ep', EP_SUPPLEMENT_STAMP_FILEAREA, null);
        if ($userinfo) {
            $credit->annotate_files('mod_ep', EP_EVIDENCE_FILEAREA, 'id');
            $suppitem->annotate_files('mod_ep', EP_SUPPLEMENT_EVIDENCE_FILEAREA, 'id');
            $suppissue->annotate_files('mod_ep', EP_SUPPLEMENT_ISSUED_FILEAREA, 'id');
        }

        return $this->prepare_activity_structure($ep);
    }
}
