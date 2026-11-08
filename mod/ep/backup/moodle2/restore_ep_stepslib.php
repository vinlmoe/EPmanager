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
 * Étape de restauration de la structure d'une instance de mod_ep.
 *
 * @package   mod_ep
 * @category  backup
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restaure l'arbre décrit par backup_ep_activity_structure_step.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ep_activity_structure_step extends restore_activity_structure_step {
    /** @var array Prérequis des EP du catalogue, appliqués une fois tous les EP restaurés. */
    protected $prereqs = [];

    /** @var array Ancienne émission de supplément => ancienne émission qui la remplace. */
    protected $replacedby = [];

    /**
     * Déclare les chemins à restaurer.
     *
     * @return array
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('ep', '/activity/ep');
        $paths[] = new restore_path_element('ep_type', '/activity/ep/types/type');
        $paths[] = new restore_path_element('ep_activity', '/activity/ep/epactivities/epactivity');
        $paths[] = new restore_path_element(
            'ep_activity_teacher',
            '/activity/ep/epactivities/epactivity/activityteachers/activityteacher'
        );
        $paths[] = new restore_path_element(
            'ep_activity_prereq',
            '/activity/ep/epactivities/epactivity/prereqs/prereq'
        );
        $paths[] = new restore_path_element('ep_syllabus', '/activity/ep/epactivities/epactivity/syllabi/syllabus');
        $paths[] = new restore_path_element(
            'ep_session',
            '/activity/ep/epactivities/epactivity/syllabi/syllabus/sessions/session'
        );
        $paths[] = new restore_path_element('ep_notification', '/activity/ep/notifications/notification');
        $paths[] = new restore_path_element('ep_supplement_category', '/activity/ep/suppcategories/suppcategory');
        $paths[] = new restore_path_element('ep_supplement_text', '/activity/ep/supptexts/supptext');
        $paths[] = new restore_path_element(
            'ep_year_requirement',
            '/activity/ep/yearrequirements/yearrequirement'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element('ep_credit', '/activity/ep/credits/credit');
            $paths[] = new restore_path_element('ep_supplement_item', '/activity/ep/suppitems/suppitem');
            $paths[] = new restore_path_element('ep_supplement_issue', '/activity/ep/suppissues/suppissue');
            $paths[] = new restore_path_element('ep_year_validation', '/activity/ep/yearvalidations/yearvalidation');
            $paths[] = new restore_path_element(
                'ep_credit_decision',
                '/activity/ep/credits/credit/decisions/decision'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restaure l'instance elle-même.
     *
     * Le course-module mod_stage lié (stagecmid) est conservé tel quel à ce stade : l'activité
     * visée peut n'être restaurée qu'après celle-ci. La correspondance est appliquée par
     * after_restore(), une fois toutes les activités du cours restaurées. La date de dernière
     * synchronisation est remise à zéro : la copie resynchronise ses EP de type stage à sa
     * première consultation.
     *
     * @param array $data
     */
    protected function process_ep($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timesynced = 0;
        // Période d'inscription du catalogue : décalée avec les dates du cours copié.
        foreach (['registrationstart', 'registrationend'] as $field) {
            $data->$field = empty($data->$field) ? 0 : $this->apply_date_offset($data->$field);
        }

        $newitemid = $DB->insert_record('ep', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restaure un type d'EP.
     *
     * @param array $data
     */
    protected function process_ep_type($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->epid = $this->get_new_parentid('ep');

        // Sauvegarde antérieure aux types paramétrés (drapeaux catalog et autovalidate) : le type
        // est converti à l'identique de son comportement d'alors.
        if (!isset($data->acquisition)) {
            require_once(__DIR__ . '/../../db/upgrade.php');
            foreach (ep_legacy_type_parameters($data) as $field => $value) {
                $data->$field = $value;
            }
        }
        unset($data->catalog, $data->autovalidate);
        $data = ep_type_record($data->epid, (array) $data);
        unset($data->id);

        $newitemid = $DB->insert_record('ep_type', $data);
        $this->set_mapping('ep_type', $oldid, $newitemid);
    }

    /**
     * Restaure un EP du catalogue. La ligne est écartée, avec ses responsables, si son type n'a
     * pas été restauré : un EP du catalogue sans type ne pourrait ni être proposé ni être compté.
     *
     * @param array $data
     * @return int|void SKIP_ALL_CHILDREN si l'EP est écarté.
     */
    protected function process_ep_activity($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $typeid = $this->get_mappingid('ep_type', $data->typeid);
        if (!$typeid) {
            return self::SKIP_ALL_CHILDREN;
        }

        $data->epid = $this->get_new_parentid('ep');
        $data->typeid = $typeid;

        // Sauvegarde antérieure aux états : un EP masqué n'était plus montré aux étudiants.
        if (!isset($data->state)) {
            $data->state = empty($data->visible) ? EP_ACTIVITY_ARCHIVED : EP_ACTIVITY_PUBLISHED;
        }
        unset($data->visible);
        // L'auteur d'une proposition absent de l'archive est dissocié, pas rattaché à un autre compte.
        $data->proposedby = empty($data->proposedby) ? null : ($this->get_mappingid('user', $data->proposedby) ?: null);

        // Fiche de l'EP (activité « Syllabus du module ») : rattachée après la restauration du
        // cours entier, si elle en fait partie (voir after_restore()).
        $oldsyllabuscmid = (int) ($data->syllabuscmid ?? 0);
        $data->syllabuscmid = null;

        $newitemid = $DB->insert_record('ep_activity', $data);
        $this->set_mapping('ep_activity', $oldid, $newitemid);
        if ($oldsyllabuscmid) {
            $this->set_mapping('ep_activity_syllabuscmid', $newitemid, $oldsyllabuscmid);
        }
    }

    /**
     * Restaure une version du syllabus d'un EP du catalogue ; auteur et validateur absents de
     * l'archive sont dissociés.
     *
     * @param array $data
     */
    protected function process_ep_syllabus($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->activityid = $this->get_new_parentid('ep_activity');
        foreach (['authorid', 'reviewerid'] as $field) {
            $data->$field = empty($data->$field) ? null : ($this->get_mappingid('user', $data->$field) ?: null);
        }
        $newitemid = $DB->insert_record('ep_syllabus', $data);
        $this->set_mapping('ep_syllabus', $oldid, $newitemid);
    }

    /**
     * Restaure une séance du programme ; un intervenant dont le compte n'est pas dans l'archive
     * garde son nom, en intervenant extérieur.
     *
     * @param array $data
     */
    protected function process_ep_session($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->syllabusid = $this->get_new_parentid('ep_syllabus');
        if (!empty($data->teacherid)) {
            $teacherid = $this->get_mappingid('user', $data->teacherid);
            if (!$teacherid && $data->teachername === '') {
                $user = $DB->get_record('user', ['id' => $data->teacherid]);
                $data->teachername = $user && $this->task->is_samesite() ? fullname($user) : '';
            }
            $data->teacherid = $teacherid ?: null;
        }
        $DB->insert_record('ep_session', $data);
    }

    /**
     * Restaure une catégorie d'éléments du supplément.
     *
     * @param array $data
     */
    protected function process_ep_supplement_category($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->epid = $this->get_new_parentid('ep');
        $newitemid = $DB->insert_record('ep_supplement_category', $data);
        $this->set_mapping('ep_supplement_category', $oldid, $newitemid);
    }

    /**
     * Restaure le texte d'une rubrique fixe du supplément.
     *
     * @param array $data
     */
    protected function process_ep_supplement_text($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->epid = $this->get_new_parentid('ep');
        $DB->insert_record('ep_supplement_text', $data);
    }

    /**
     * Restaure un élément du supplément demandé par un étudiant ; écarté si l'étudiant ou sa
     * catégorie n'ont pas été restaurés.
     *
     * @param array $data
     */
    protected function process_ep_supplement_item($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $userid = $this->get_mappingid('user', $data->userid);
        $categoryid = $this->get_mappingid('ep_supplement_category', $data->categoryid);
        if (!$userid || !$categoryid) {
            return;
        }
        $data->epid = $this->get_new_parentid('ep');
        $data->userid = $userid;
        $data->categoryid = $categoryid;
        $data->validatedby = empty($data->validatedby) ? null : ($this->get_mappingid('user', $data->validatedby) ?: null);
        $newitemid = $DB->insert_record('ep_supplement_item', $data);
        $this->set_mapping('ep_supplement_item', $oldid, $newitemid, true);
    }

    /**
     * Restaure un supplément émis. Son numéro est unique sur le site : une copie sur le même site
     * reçoit un numéro suffixé, le document conservé gardant le numéro d'origine qu'il porte.
     *
     * @param array $data
     */
    protected function process_ep_supplement_issue($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }
        // Le sceau d'origine n'est reconnu que s'il a été produit par ce site pour cet
        // enregistrement : il est vérifié avec les identifiants d'avant la restauration.
        $original = clone $data;
        $original->epid = $this->get_old_parentid('ep');
        $authentic = $this->task->is_samesite() && !empty($data->seal)
            && hash_equals(ep_supplement_seal($original), (string) $data->seal);
        $data->epid = $this->get_new_parentid('ep');
        $data->userid = $userid;
        $data->issuedby = empty($data->issuedby) ? null : ($this->get_mappingid('user', $data->issuedby) ?: null);
        $this->replacedby[$oldid] = (int) ($data->replacedby ?? 0);
        $data->replacedby = null;
        // Activité ou cours supprimé puis restauré sur le même site : le document remis à
        // l'étudiant n'existe plus ailleurs (son numéro est libre) et reste vérifiable, scellé
        // avec les nouveaux identifiants. Toute autre copie (cours dupliqué, autre site) est
        // conservée mais non reconnue par la vérification (voir ep_supplement_seal()).
        $keep = $authentic && !$DB->record_exists('ep_supplement_issue', ['number' => $data->number]);
        $data->seal = $keep ? ep_supplement_seal($data) : null;
        $number = $data->number;
        $suffix = 1;
        while ($DB->record_exists('ep_supplement_issue', ['number' => $data->number])) {
            $data->number = core_text::substr($number, 0, 34) . '-C' . $suffix++;
        }
        $newitemid = $DB->insert_record('ep_supplement_issue', $data);
        $this->set_mapping('ep_supplement_issue', $oldid, $newitemid, true);
    }

    /**
     * Restaure une décision d'année ; écartée si l'étudiant n'a pas été restauré.
     *
     * @param array $data
     */
    protected function process_ep_year_validation($data) {
        global $DB;

        $data = (object) $data;
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }
        unset($data->id);
        $data->epid = $this->get_new_parentid('ep');
        $data->userid = $userid;
        $data->decidedby = empty($data->decidedby) ? null : ($this->get_mappingid('user', $data->decidedby) ?: null);
        $DB->insert_record('ep_year_validation', $data);
    }

    /**
     * Restaure la personnalisation d'un courriel.
     *
     * @param array $data
     */
    protected function process_ep_notification($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->epid = $this->get_new_parentid('ep');
        $DB->insert_record('ep_notification', $data);
    }

    /**
     * Met de côté un prérequis d'EP du catalogue : l'EP exigé peut n'être restauré qu'après celui-ci,
     * la correspondance est donc appliquée dans after_execute().
     *
     * @param array $data
     */
    protected function process_ep_activity_prereq($data) {
        $data = (object) $data;
        $this->prereqs[] = [
            'activityid' => $this->get_new_parentid('ep_activity'),
            'requiredid' => (int) $data->requiredid,
            'timecreated' => (int) ($data->timecreated ?? time()),
        ];
    }

    /**
     * Restaure un responsable d'EP du catalogue. La ligne est écartée si le compte n'est pas
     * présent dans la sauvegarde (restauration sans les utilisateurs).
     *
     * @param array $data
     */
    protected function process_ep_activity_teacher($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);

        $teacherid = $this->get_mappingid('user', $data->teacherid);
        if (!$teacherid) {
            return;
        }

        $data->activityid = $this->get_new_parentid('ep_activity');
        $data->teacherid = $teacherid;

        // L'index (activityid, teacherid) est unique : deux comptes distincts de la sauvegarde
        // peuvent se retrouver fusionnés sur un même compte du site cible.
        if ($DB->record_exists('ep_activity_teacher', ['activityid' => $data->activityid, 'teacherid' => $teacherid])) {
            return;
        }

        $DB->insert_record('ep_activity_teacher', $data);
    }

    /**
     * Restaure le minimum d'ECTS d'une année d'étude.
     *
     * @param array $data
     */
    protected function process_ep_year_requirement($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->epid = $this->get_new_parentid('ep');

        $DB->insert_record('ep_year_requirement', $data);
    }

    /**
     * Restaure un crédit d'étudiant.
     *
     * Les dates du crédit (dépôt, validation) ne sont pas décalées par apply_date_offset() :
     * elles constatent une démarche réellement effectuée, et non une échéance du cours à
     * replacer dans le calendrier.
     *
     * @param array $data
     */
    protected function process_ep_credit($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        // Sans étudiant, le crédit n'a plus de sens ; sans type, il ne pourrait pas être compté.
        $userid = $this->get_mappingid('user', $data->userid);
        $typeid = $this->get_mappingid('ep_type', $data->typeid);
        if (!$userid || !$typeid) {
            // Ses décisions ne doivent pas être rattachées au crédit restauré juste avant.
            return self::SKIP_ALL_CHILDREN;
        }

        $data->epid = $this->get_new_parentid('ep');
        $data->userid = $userid;
        $data->typeid = $typeid;
        $data->activityid = empty($data->activityid) ? null
            : ($this->get_mappingid('ep_activity', $data->activityid) ?: null);
        $data->validatedby = empty($data->validatedby) ? null
            : ($this->get_mappingid('user', $data->validatedby) ?: null);
        $data->addedby = empty($data->addedby) ? null
            : ($this->get_mappingid('user', $data->addedby) ?: null);

        // Sauvegarde antérieure aux circuits à étapes : une demande en attente attend le
        // responsable (inscription) ou le référent (déclaration).
        if (!isset($data->pendingrole)) {
            $data->step = 0;
            $data->pendingrole = (int) $data->status === EP_STATUS_PENDING
                ? ($data->activityid ? EP_ROLE_OWNER : EP_ROLE_REFERENT) : '';
        }

        // Le crédit porte des fichiers (les justificatifs) : la correspondance doit le déclarer
        // pour qu'after_execute() puisse les rattacher.
        $newitemid = $DB->insert_record('ep_credit', $data);
        $this->set_mapping('ep_credit', $oldid, $newitemid, true);
    }

    /**
     * Restaure une décision de l'historique d'un crédit ; l'auteur est dissocié s'il n'est pas
     * dans l'archive.
     *
     * @param array $data
     */
    protected function process_ep_credit_decision($data) {
        global $DB;

        $data = (object) $data;
        unset($data->id);
        $data->creditid = $this->get_new_parentid('ep_credit');
        $data->userid = empty($data->userid) ? null : ($this->get_mappingid('user', $data->userid) ?: null);
        $DB->insert_record('ep_credit_decision', $data);
    }

    /**
     * Rattache les fichiers une fois toutes les correspondances établies.
     */
    protected function after_execute() {
        global $DB;

        // Prérequis, une fois tous les EP du catalogue restaurés ; un EP exigé absent de la
        // sauvegarde est écarté.
        foreach ($this->prereqs as $prereq) {
            $requiredid = $this->get_mappingid('ep_activity', $prereq['requiredid']);
            if ($requiredid && $prereq['activityid']) {
                $prereq['requiredid'] = $requiredid;
                $DB->insert_record('ep_activity_prereq', (object) $prereq);
            }
        }

        // Zones sans itemid, rattachées au contexte du module.
        $this->add_related_files('mod_ep', 'intro', null);
        $this->add_related_files('mod_ep', EP_PDFLOGO_FILEAREA, null);
        $this->add_related_files('mod_ep', EP_SUPPLEMENT_STAMP_FILEAREA, null);

        // Chaîne des remplacements de suppléments émis, une fois toutes les émissions restaurées.
        foreach ($this->replacedby as $oldid => $oldreplacement) {
            $newid = $this->get_mappingid('ep_supplement_issue', $oldid);
            $newreplacement = $oldreplacement ? $this->get_mappingid('ep_supplement_issue', $oldreplacement) : 0;
            if ($newid && $newreplacement) {
                $DB->set_field('ep_supplement_issue', 'replacedby', $newreplacement, ['id' => $newid]);
            }
        }

        // Justificatifs : un ou plusieurs fichiers par crédit.
        if ($this->get_setting_value('userinfo')) {
            $this->add_related_files('mod_ep', EP_EVIDENCE_FILEAREA, 'ep_credit');
            $this->add_related_files('mod_ep', EP_SUPPLEMENT_EVIDENCE_FILEAREA, 'ep_supplement_item');
            $this->add_related_files('mod_ep', EP_SUPPLEMENT_ISSUED_FILEAREA, 'ep_supplement_issue');
        }
    }

    /**
     * Fait pointer l'activité « Gestion des stages » liée vers sa copie réellement restaurée.
     *
     * Un lien dont l'activité d'origine n'a pas été restaurée n'est conservé que sur le même site,
     * où l'identifiant de course-module d'origine désigne toujours la bonne activité ; ailleurs il
     * désignerait une activité sans rapport et est donc remis à zéro (toutes les activités
     * mod_stage du même cours).
     */
    protected function after_restore() {
        global $DB;

        $epid = $this->task->get_activityid();
        if (!$epid) {
            return;
        }

        // Fiches des EP : l'activité « Syllabus du module » restaurée avec le cours redevient
        // celle de l'EP restauré ; absente de la sauvegarde, elle sera recréée à la demande.
        foreach ($DB->get_records('ep_activity', ['epid' => $epid], '', 'id') as $activity) {
            $oldcmid = $this->get_mappingid('ep_activity_syllabuscmid', $activity->id);
            $newcmid = $oldcmid ? $this->get_mappingid('course_module', $oldcmid) : 0;
            $cm = $newcmid ? get_coursemodule_from_id('syllabus', $newcmid, 0, false, IGNORE_MISSING) : false;
            if ($cm) {
                $DB->set_field('ep_activity', 'syllabuscmid', $cm->id, ['id' => $activity->id]);
                $DB->update_record('syllabus', (object) ['id' => $cm->instance, 'component' => 'mod_ep',
                    'itemid' => $activity->id]);
            }
        }

        $stagecmid = (int) $DB->get_field('ep', 'stagecmid', ['id' => $epid]);
        if (!$stagecmid) {
            return;
        }

        $newcmid = $this->get_mappingid('course_module', $stagecmid);

        if (!$newcmid) {
            // Sur le même site, le lien vers une activité d'un autre cours n'est gardé que si celui
            // qui restaure peut voir tous les stages de cette activité : sinon une sauvegarde
            // forgée lui ouvrirait les référents et les stages d'étudiants qu'il ne gère pas.
            $stagecm = $this->task->is_samesite()
                ? get_coursemodule_from_id('stage', $stagecmid, 0, false, IGNORE_MISSING) : false;
            $stillvalid = $stagecm && has_capability(
                'mod/stage:viewall',
                \context_module::instance($stagecm->id),
                $this->task->get_userid()
            );
            if (!$stillvalid) {
                $DB->set_field('ep', 'stagecmid', 0, ['id' => $epid]);
            }
            return;
        }

        if ((int) $newcmid !== $stagecmid) {
            $DB->set_field('ep', 'stagecmid', $newcmid, ['id' => $epid]);
        }
    }
}
