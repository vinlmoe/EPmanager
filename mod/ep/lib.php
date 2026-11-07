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
 * Library of interface functions and constants for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Crédit annulé (désinscription de l'étudiant, retrait par la DEVE) : état terminal, conservé. */
define('EP_STATUS_CANCELLED', -2);
/** Crédit refusé par le responsable de l'EP ou l'enseignant référent, avec motif. */
define('EP_STATUS_REJECTED', -1);
/** Crédit demandé (inscription ou déclaration), en attente de validation. */
define('EP_STATUS_PENDING', 0);
/** Crédit validé : ses ECTS entrent dans le décompte, sous réserve des plafonds par type. */
define('EP_STATUS_VALIDATED', 1);

/** Nombre de lignes par page pour les listes paginées (DEVE / enseignants). */
define('EP_LIST_PERPAGE', 40);

/** Acquisition par inscription à un EP du catalogue, qui porte ses ECTS. */
define('EP_ACQ_CATALOG', 'catalog');
/** Acquisition par déclaration de l'étudiant, avec justificatifs. */
define('EP_ACQ_DECLARATION', 'declaration');
/** Acquisition automatique d'après les stages complémentaires validés dans mod_stage. */
define('EP_ACQ_STAGE', 'stage');
/** Acquisition par saisie de la DEVE (à la main ou par import), sans demande de l'étudiant. */
define('EP_ACQ_DEVE', 'deve');

/** Étape de validation : le responsable de l'EP du catalogue. */
define('EP_ROLE_OWNER', 'owner');
/** Étape de validation : l'enseignant référent de l'étudiant. */
define('EP_ROLE_REFERENT', 'referent');
/** Étape de validation : la DEVE. */
define('EP_ROLE_DEVE', 'deve');
/** Auteur d'un retrait : l'étudiant lui-même. */
define('EP_ROLE_STUDENT', 'student');

/** EP du catalogue en brouillon : proposition d'un enseignant, pas encore soumise. */
define('EP_ACTIVITY_DRAFT', 'draft');
/** EP du catalogue proposé par un enseignant, en attente de la DEVE. */
define('EP_ACTIVITY_PROPOSED', 'proposed');
/** EP du catalogue publié : inscriptions ouvertes, selon sa période d'inscription. */
define('EP_ACTIVITY_PUBLISHED', 'published');
/** EP du catalogue clos : toujours au catalogue, inscriptions closes, notes encore saisissables. */
define('EP_ACTIVITY_CLOSED', 'closed');
/** EP du catalogue archivé : retiré du catalogue, inscriptions conservées au dossier. */
define('EP_ACTIVITY_ARCHIVED', 'archived');

/** Évaluation sans note : validé ou non validé. */
define('EP_GRADING_NONE', 'none');
/** Évaluation par une note, comparée au seuil du type ou de l'EP. */
define('EP_GRADING_GRADE', 'grade');

/** Justificatifs sans objet pour ce type. */
define('EP_EVIDENCE_NONE', 0);
/** Justificatifs facultatifs. */
define('EP_EVIDENCE_OPTIONAL', 1);
/** Justificatifs obligatoires : au moins un fichier. */
define('EP_EVIDENCE_REQUIRED', 2);

/** EP académique interne : inscription à un EP du catalogue, validée par son responsable. */
define('EP_TYPE_ACADEMIC', 'academic');
/** EP de type stage : attribué automatiquement d'après les stages complémentaires (EP) de mod_stage. */
define('EP_TYPE_STAGE', 'stage');
/** Engagement étudiant (associatif, représentation, tutorat...). */
define('EP_TYPE_ENGAGEMENT', 'engagement');
/** Expérience professionnelle. */
define('EP_TYPE_PROFESSIONAL', 'professional');
/** Sport (haut niveau, encadrement...). */
define('EP_TYPE_SPORT', 'sport');
/** EP académique suivi hors de l'établissement. */
define('EP_TYPE_EXTERNAL', 'external');

/** Crédit issu d'une inscription ou d'une déclaration de l'étudiant. */
define('EP_SOURCE_STUDENT', 'student');
/** Crédit attribué automatiquement depuis un stage complémentaire (EP) validé par la DEVE. */
define('EP_SOURCE_STAGE', 'stage');
/** Crédit saisi directement par la DEVE pour le compte d'un étudiant. */
define('EP_SOURCE_DEVE', 'deve');

/**
 * Zone de fichiers des justificatifs déposés par l'étudiant à l'appui d'une déclaration d'EP
 * (engagement, expérience professionnelle, sport, académique externe), l'itemid étant
 * l'identifiant du crédit (ep_credit.id).
 */
define('EP_EVIDENCE_FILEAREA', 'evidence');

/** Zone de fichiers des preuves d'un élément du supplément au diplôme (itemid = ep_supplement_item.id). */
define('EP_SUPPLEMENT_EVIDENCE_FILEAREA', 'supplementevidence');
/** Zone de fichiers des suppléments au diplôme émis (itemid = ep_supplement_issue.id). */
define('EP_SUPPLEMENT_ISSUED_FILEAREA', 'supplementissued');
/** Zone de fichiers du cachet imprimé sur les suppléments (itemid 0). */
define('EP_SUPPLEMENT_STAMP_FILEAREA', 'supplementstamp');

/**
 * Version du format d'export du paramétrage : incrémentée quand la structure change, pour
 * qu'un fichier plus ancien soit converti ou refusé à l'import plutôt que mal lu.
 */
define('EP_SETTINGS_FORMAT', 1);

/** Zone de fichiers du livret PDF du catalogue, conservé tant qu'il est à jour (itemid 0). */
define('EP_BOOKLET_FILEAREA', 'booklet');

/** Zone de fichiers du logo des documents PDF de l'activité (itemid 0). */
define('EP_PDFLOGO_FILEAREA', 'pdflogo');

/**
 * Âge maximal d'une synchronisation des EP de type stage dont l'empreinte n'a pas changé (voir
 * ep_sync_stage_credits_if_due()) : tout ce qui compte (stages complémentaires validés, durées,
 * années, mobilité, barème) est dans l'empreinte et déclenche le recalcul aussitôt ; ce délai ne
 * rattrape que ce qui n'y est pas, comme le libellé renommé d'une thématique.
 */
define('EP_STAGE_SYNC_INTERVAL', 3600);

/**
 * Returns the list of features supported by this module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True/false or null depending on the feature.
 */
function ep_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return defined('MOD_PURPOSE_ADMINISTRATION') ? MOD_PURPOSE_ADMINISTRATION : 'administration';
        default:
            return null;
    }
}

/**
 * Saves a new instance of mod_ep into the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_ep_mod_form|null $mform
 * @return int New instance id.
 */
function ep_add_instance($moduleinstance, $mform = null) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/ep/locallib.php');

    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;

    $epid = $DB->insert_record('ep', $moduleinstance);

    // Catégories d'éléments du supplément au diplôme proposées par défaut.
    ep_create_default_supplement_categories($epid);

    // Les six types d'enseignement personnalisé sont créés avec l'instance : la DEVE n'a plus
    // qu'à les paramétrer (plafonds, ECTS par jour de stage), sans avoir à deviner quels codes de
    // type le reste du module attend.
    ep_create_default_types($epid);

    return $epid;
}

/**
 * Updates an instance of mod_ep in the database.
 *
 * @param stdClass $moduleinstance
 * @param mod_ep_mod_form|null $mform
 * @return bool True on success.
 */
function ep_update_instance($moduleinstance, $mform = null) {
    global $DB;

    $moduleinstance->timemodified = time();
    $moduleinstance->id = $moduleinstance->instance;

    return $DB->update_record('ep', $moduleinstance);
}

/**
 * Removes an instance of mod_ep from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True on success.
 */
function ep_delete_instance($id) {
    global $DB;

    if (!$DB->get_record('ep', ['id' => $id])) {
        return false;
    }

    // Suppressions par paquets : une liste IN sans limite dépasse celles de MSSQL et d'Oracle.
    $activityids = $DB->get_fieldset_select('ep_activity', 'id', 'epid = ?', [$id]);
    foreach (array_chunk($activityids, 500) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
        $DB->delete_records_select('ep_activity_teacher', "activityid $insql", $inparams);
        $DB->delete_records_select('ep_activity_prereq', "activityid $insql", $inparams);
        $syllabusids = $DB->get_fieldset_select('ep_syllabus', 'id', "activityid $insql", $inparams);
        foreach (array_chunk($syllabusids, 500) as $schunk) {
            [$sinsql, $sinparams] = $DB->get_in_or_equal($schunk);
            $DB->delete_records_select('ep_session', "syllabusid $sinsql", $sinparams);
        }
        $DB->delete_records_select('ep_syllabus', "activityid $insql", $inparams);
    }
    $DB->delete_records('ep_notification', ['epid' => $id]);
    $DB->delete_records('ep_supplement_item', ['epid' => $id]);
    $DB->delete_records('ep_supplement_issue', ['epid' => $id]);
    $DB->delete_records('ep_supplement_category', ['epid' => $id]);
    $DB->delete_records('ep_supplement_text', ['epid' => $id]);
    $DB->delete_records('ep_year_validation', ['epid' => $id]);
    $DB->delete_records('ep_import_log', ['epid' => $id]);
    $creditids = $DB->get_fieldset_select('ep_credit', 'id', 'epid = ?', [$id]);
    foreach (array_chunk($creditids, 500) as $chunk) {
        [$insql, $inparams] = $DB->get_in_or_equal($chunk);
        $DB->delete_records_select('ep_credit_decision', "creditid $insql", $inparams);
    }
    $DB->delete_records('ep_credit', ['epid' => $id]);
    $DB->delete_records('ep_activity', ['epid' => $id]);
    $DB->delete_records('ep_year_requirement', ['epid' => $id]);
    $DB->delete_records('ep_type', ['epid' => $id]);
    $DB->delete_records('ep', ['id' => $id]);

    return true;
}

/**
 * Éléments du formulaire de réinitialisation du cours : ce qui peut être effacé pour accueillir
 * une nouvelle promotion. Le paramétrage (types, minimums, catalogue, syllabus, supplément,
 * courriels) est toujours conservé.
 *
 * @param MoodleQuickForm $mform
 * @return void
 */
function ep_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'epheader', get_string('modulenameplural', 'mod_ep'));
    $mform->addElement('advcheckbox', 'reset_ep_credits', get_string('resetcredits', 'mod_ep'));
    $mform->addElement('advcheckbox', 'reset_ep_years', get_string('resetyears', 'mod_ep'));
    $mform->addElement('advcheckbox', 'reset_ep_supplement', get_string('resetsupplement', 'mod_ep'));
    $mform->addHelpButton('reset_ep_supplement', 'resetsupplement', 'mod_ep');
    $mform->addElement('advcheckbox', 'reset_ep_importlog', get_string('resetimportlog', 'mod_ep'));
}

/**
 * Valeurs par défaut du formulaire de réinitialisation : les dossiers de la promotion sont
 * effacés, les suppléments au diplôme émis (documents certifiés) et le journal des imports sont
 * conservés sauf demande explicite.
 *
 * @param stdClass $course
 * @return array
 */
function ep_reset_course_form_defaults($course) {
    return ['reset_ep_credits' => 1, 'reset_ep_years' => 1, 'reset_ep_supplement' => 0, 'reset_ep_importlog' => 0];
}

/**
 * Réinitialise les données des utilisateurs des activités du cours, selon les choix du
 * formulaire de réinitialisation.
 *
 * @param stdClass $data Données du formulaire (courseid, reset_ep_*).
 * @return array Statut, une ligne par élément effacé.
 */
function ep_reset_userdata($data) {
    global $DB;

    $component = get_string('modulenameplural', 'mod_ep');
    $status = [];
    $instances = $DB->get_records('ep', ['course' => $data->courseid], '', 'id');
    if (!$instances) {
        return $status;
    }
    $fs = get_file_storage();
    $contexts = [];
    foreach ($instances as $instance) {
        $cm = get_coursemodule_from_instance('ep', $instance->id, $data->courseid, false, IGNORE_MISSING);
        if ($cm) {
            $contexts[$instance->id] = context_module::instance($cm->id);
        }
    }

    if (!empty($data->reset_ep_credits)) {
        foreach ($contexts as $epid => $context) {
            $fs->delete_area_files($context->id, 'mod_ep', EP_EVIDENCE_FILEAREA);
            $DB->delete_records_select('ep_credit_decision', 'creditid IN (SELECT id FROM {ep_credit} WHERE epid = ?)', [$epid]);
            $DB->delete_records('ep_credit', ['epid' => $epid]);
        }
        $status[] = ['component' => $component, 'item' => get_string('resetcredits', 'mod_ep'), 'error' => false];
    }
    if (!empty($data->reset_ep_years)) {
        foreach (array_keys($contexts) as $epid) {
            $DB->delete_records('ep_year_validation', ['epid' => $epid]);
        }
        $status[] = ['component' => $component, 'item' => get_string('resetyears', 'mod_ep'), 'error' => false];
    }
    if (!empty($data->reset_ep_supplement)) {
        foreach ($contexts as $epid => $context) {
            $fs->delete_area_files($context->id, 'mod_ep', EP_SUPPLEMENT_EVIDENCE_FILEAREA);
            $fs->delete_area_files($context->id, 'mod_ep', EP_SUPPLEMENT_ISSUED_FILEAREA);
            $DB->delete_records('ep_supplement_item', ['epid' => $epid]);
            $DB->delete_records('ep_supplement_issue', ['epid' => $epid]);
        }
        $status[] = ['component' => $component, 'item' => get_string('resetsupplement', 'mod_ep'), 'error' => false];
    }
    if (!empty($data->reset_ep_importlog)) {
        foreach (array_keys($contexts) as $epid) {
            $DB->delete_records('ep_import_log', ['epid' => $epid]);
        }
        $status[] = ['component' => $component, 'item' => get_string('resetimportlog', 'mod_ep'), 'error' => false];
    }
    return $status;
}

/**
 * Returns a small object with summary information about what a user has done with a given
 * particular instance of this module.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param cm_info|stdClass $mod
 * @param stdClass $ep
 * @return stdClass|null
 */
function ep_user_outline($course, $user, $mod, $ep) {
    global $DB;

    $count = $DB->count_records('ep_credit', ['epid' => $ep->id, 'userid' => $user->id]);
    if (!$count) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('numcredits', 'mod_ep', $count);
    $result->time = time();
    return $result;
}
