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
 * Le paramétrage (types d'EP, catalogue et ses responsables, minimums annuels) est toujours
 * sauvegardé ; les crédits des étudiants ne le sont que si les données utilisateur sont demandées.
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

        // « stagecmid » désigne une activité mod_stage : la restauration le remappe (voir
        // restore_ep_activity_structure_step::after_restore()). « timesynced » est remis à zéro à
        // la restauration, la copie n'ayant encore rien synchronisé.
        $ep = new backup_nested_element('ep', ['id'], [
            'name', 'intro', 'introformat', 'currentstudyyear', 'mincursusects', 'stagecmid',
            'timecreated', 'timemodified',
        ]);

        $types = new backup_nested_element('types');
        $type = new backup_nested_element('type', ['id'], [
            'code', 'name', 'description', 'enabled', 'catalog', 'autovalidate',
            'maxects', 'maxectsperyear', 'ectsperday', 'sortorder', 'timecreated', 'timemodified',
        ]);

        // « activity » est le nom de l'élément racine dans lequel prepare_activity_structure()
        // enveloppe toute la structure : le réutiliser pour les EP du catalogue ferait échouer la
        // construction de l'arbre (baseelementexisting).
        $catalogactivities = new backup_nested_element('catalogactivities');
        $catalogactivity = new backup_nested_element('catalogactivity', ['id'], [
            'typeid', 'name', 'description', 'ects', 'minstudyyear', 'maxstudyyear',
            'capacity', 'visible', 'sortorder', 'timecreated', 'timemodified',
        ]);

        $activityteachers = new backup_nested_element('activityteachers');
        $activityteacher = new backup_nested_element('activityteacher', ['id'], ['teacherid', 'timecreated']);

        $yearrequirements = new backup_nested_element('yearrequirements');
        $yearrequirement = new backup_nested_element('yearrequirement', ['id'], [
            'studyyear', 'requiredects', 'timecreated', 'timemodified',
        ]);

        $credits = new backup_nested_element('credits');
        $credit = new backup_nested_element('credit', ['id'], [
            'userid', 'typeid', 'activityid', 'studyyear', 'name', 'description',
            'claimedects', 'retainedects', 'status', 'source', 'sourceref',
            'validatedby', 'validatetime', 'validatorcomment', 'timecreated', 'timemodified',
        ]);

        // Arbre : les types précèdent le catalogue puis les crédits, qui s'y rattachent.
        $ep->add_child($types);
        $types->add_child($type);

        $ep->add_child($catalogactivities);
        $catalogactivities->add_child($catalogactivity);
        $catalogactivity->add_child($activityteachers);
        $activityteachers->add_child($activityteacher);

        $ep->add_child($yearrequirements);
        $yearrequirements->add_child($yearrequirement);

        $ep->add_child($credits);
        $credits->add_child($credit);

        // Sources.
        $ep->set_source_table('ep', ['id' => backup::VAR_ACTIVITYID]);
        $type->set_source_table('ep_type', ['epid' => backup::VAR_PARENTID], 'sortorder, id');
        $catalogactivity->set_source_table('ep_activity', ['epid' => backup::VAR_PARENTID], 'sortorder, id');
        $yearrequirement->set_source_table('ep_year_requirement', ['epid' => backup::VAR_PARENTID], 'studyyear');

        // Les responsables d'un EP du catalogue relèvent du paramétrage de l'activité : ils sont
        // sauvegardés dans tous les cas, et la restauration ignore ceux dont le compte n'est pas
        // présent dans l'archive.
        $activityteacher->set_source_table('ep_activity_teacher', ['activityid' => backup::VAR_PARENTID], 'id');

        if ($userinfo) {
            // Les crédits de source « stage » sont dérivés des stages complémentaires validés dans
            // mod_stage : ep_sync_stage_credits() les recrée et les supprime à partir de ceux-ci,
            // et leur « sourceref » désignerait des saisies du site d'origine. Les sauvegarder
            // n'apporterait donc rien et prêterait à confusion.
            $credit->set_source_sql(
                '
                SELECT *
                  FROM {ep_credit}
                 WHERE epid = ?
                   AND source <> ?
              ORDER BY id',
                [backup::VAR_PARENTID, backup_helper::is_sqlparam(EP_SOURCE_STAGE)]
            );
        }

        // Annotations d'identifiants.
        $activityteacher->annotate_ids('user', 'teacherid');
        $credit->annotate_ids('user', 'userid');
        $credit->annotate_ids('user', 'validatedby');

        // Zones de fichiers.
        $ep->annotate_files('mod_ep', 'intro', null);
        if ($userinfo) {
            $credit->annotate_files('mod_ep', EP_EVIDENCE_FILEAREA, 'id');
        }

        return $this->prepare_activity_structure($ep);
    }
}
