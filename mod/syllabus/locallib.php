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
 * Fonctions internes de mod_syllabus : droits, versions de la fiche, référentiel commun (types de
 * module, questions communes, réglages), évaluation par les étudiants et ses résultats, listes
 * pour les activités de synthèse (mod_syllabusstaff, mod_syllabusstudent).
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/syllabus/lib.php');

// Réglages communs au site, tenus par la DEVE depuis mod_syllabusstaff.

/**
 * Réglages communs, avec leur valeur par défaut.
 *
 * @return array nom => valeur par défaut
 */
function syllabus_setting_defaults(): array {
    return [
        // Les versions soumises par les responsables passent par la DEVE avant d'être publiées.
        'reviewrequired' => 1,
        // Nombre minimal de réponses pour afficher les résultats d'une évaluation (anonymat).
        'resultsthreshold' => 3,
        // Les responsables voient les résultats : après la fermeture (afterclose) ou dès l'ouverture.
        'teacherresults' => 'afterclose',
    ];
}

/**
 * Valeur d'un réglage commun.
 *
 * @param string $name
 * @return mixed
 */
function syllabus_setting(string $name) {
    $value = get_config('mod_syllabus', $name);
    return $value === false ? (syllabus_setting_defaults()[$name] ?? null) : $value;
}

/**
 * Enregistre les réglages communs.
 *
 * @param stdClass $data
 * @return void
 */
function syllabus_save_settings(stdClass $data): void {
    set_config('reviewrequired', empty($data->reviewrequired) ? 0 : 1, 'mod_syllabus');
    set_config('resultsthreshold', max(1, min(50, (int) ($data->resultsthreshold ?? 3))), 'mod_syllabus');
    set_config('teacherresults', ($data->teacherresults ?? '') === 'always' ? 'always' : 'afterclose', 'mod_syllabus');
}

// Mise en forme.

/**
 * Nombre décimal à la française, sans zéros inutiles (2,5 ; 3).
 *
 * @param float|string|null $value
 * @return string
 */
function syllabus_format_number($value): string {
    return format_float((float) $value, 2, true, true);
}

/**
 * Libellé d'une année d'étude.
 *
 * @param int $year
 * @return string
 */
function syllabus_studyyear_label($year): string {
    return (int) $year > 0 ? get_string('studyyear_n', 'mod_syllabus', (int) $year) : '-';
}

/**
 * Années d'étude proposées.
 *
 * @return array
 */
function syllabus_studyyear_options(): array {
    $options = [0 => get_string('studyyear_none', 'mod_syllabus')];
    for ($year = 1; $year <= 6; $year++) {
        $options[$year] = get_string('studyyear_n', 'mod_syllabus', $year);
    }
    return $options;
}

/**
 * Rubriques de la fiche, dans l'ordre du modèle ECTS.
 *
 * @return array champ => libellé
 */
function syllabus_text_fields(): array {
    $fields = [];
    foreach (['objectives', 'outcomes', 'prerequisitestext', 'content', 'methods', 'assessment', 'resit', 'bibliography'] as $f) {
        $fields[$f] = get_string('field_' . $f, 'mod_syllabus');
    }
    return $fields;
}

/**
 * Natures de séance.
 *
 * @return array code => libellé
 */
function syllabus_session_kind_options(): array {
    $kinds = [];
    foreach (['lecture', 'tutorial', 'practical', 'clinical', 'fieldwork', 'project', 'other'] as $kind) {
        $kinds[$kind] = get_string('sessionkind_' . $kind, 'mod_syllabus');
    }
    return $kinds;
}

/**
 * Libellé d'un statut de version.
 *
 * @param string $status
 * @return string
 */
function syllabus_status_label(string $status): string {
    return get_string('status_' . $status, 'mod_syllabus');
}

// Droits.

/**
 * Droits de l'utilisateur sur un module.
 *
 * Rédiger la fiche, poser les questions et voir les résultats revient aux responsables désignés
 * (syllabus_teacher) qui ont mod/syllabus:edit, et à la DEVE (mod/syllabus:manage). Tant qu'aucun
 * responsable n'est désigné, tout titulaire de mod/syllabus:edit peut rédiger : le module ne
 * reste pas orphelin.
 *
 * @param stdClass $syllabus
 * @param context $context
 * @param int|null $userid
 * @return stdClass {canview, isteacher, canedit, canmanage, canevaluate, canseeresults}
 */
function syllabus_get_rights(stdClass $syllabus, context $context, ?int $userid = null): stdClass {
    global $DB, $USER;

    $userid = $userid ?? (int) $USER->id;
    $canmanage = has_capability('mod/syllabus:manage', $context, $userid);
    $haseditcap = has_capability('mod/syllabus:edit', $context, $userid);
    $isteacher = $DB->record_exists('syllabus_teacher', ['syllabusid' => $syllabus->id, 'userid' => $userid]);
    $orphan = !$isteacher && !$DB->record_exists('syllabus_teacher', ['syllabusid' => $syllabus->id]);
    $canedit = $canmanage || ($haseditcap && ($isteacher || $orphan));

    $state = syllabus_eval_state($syllabus);
    $teacherresults = $state === SYLLABUS_EVAL_CLOSED
        || ($state === SYLLABUS_EVAL_OPEN && syllabus_setting('teacherresults') === 'always');
    return (object) [
        'canview' => has_capability('mod/syllabus:view', $context, $userid),
        'isteacher' => $isteacher && $haseditcap,
        'canedit' => $canedit,
        'canmanage' => $canmanage,
        'canevaluate' => has_capability('mod/syllabus:evaluate', $context, $userid, false),
        'canseeresults' => $canmanage || ($isteacher && $haseditcap && $teacherresults),
    ];
}

/**
 * Verrou d'une instance : écritures concurrentes sur ses versions, ses questions et ses réponses.
 *
 * @param int $syllabusid
 * @return \core\lock\lock
 */
function syllabus_get_lock(int $syllabusid) {
    $lock = \core\lock\lock_config::get_lock_factory('mod_syllabus')->get_lock('syllabus-' . $syllabusid, 30);
    if (!$lock) {
        throw new moodle_exception('errorbusy', 'mod_syllabus');
    }
    return $lock;
}

// Responsables.

/**
 * Responsables d'un module.
 *
 * @param int $syllabusid
 * @return array userid => stdClass user
 */
function syllabus_get_teachers(int $syllabusid): array {
    global $DB;

    $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    return $DB->get_records_sql(
        "SELECT u.id, u.email, $fields
           FROM {syllabus_teacher} t
           JOIN {user} u ON u.id = t.userid AND u.deleted = 0
          WHERE t.syllabusid = :syllabusid
       ORDER BY u.lastname, u.firstname",
        ['syllabusid' => $syllabusid]
    );
}

/**
 * Responsables de plusieurs modules, en une requête.
 *
 * @param int[] $syllabusids
 * @return array syllabusid => userid => stdClass user
 */
function syllabus_get_teachers_bulk(array $syllabusids): array {
    global $DB;

    $result = array_fill_keys($syllabusids, []);
    if (!$syllabusids) {
        return $result;
    }
    $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    [$insql, $params] = $DB->get_in_or_equal($syllabusids, SQL_PARAMS_NAMED);
    $rs = $DB->get_recordset_sql(
        "SELECT t.id AS linkid, t.syllabusid, u.id, $fields
           FROM {syllabus_teacher} t
           JOIN {user} u ON u.id = t.userid AND u.deleted = 0
          WHERE t.syllabusid $insql
       ORDER BY u.lastname, u.firstname",
        $params
    );
    foreach ($rs as $row) {
        $result[(int) $row->syllabusid][(int) $row->id] = $row;
    }
    $rs->close();
    return $result;
}

/**
 * Personnes qui peuvent être désignées responsables : inscrits au cours avec mod/syllabus:edit.
 *
 * @param context_module $context
 * @return array userid => stdClass user
 */
function syllabus_potential_teachers(context_module $context): array {
    return get_enrolled_users($context, 'mod/syllabus:edit', 0, 'u.*', 'u.lastname, u.firstname', 0, 0, true);
}

/**
 * Remplace la liste des responsables. Seuls ceux qui peuvent l'être sont retenus.
 *
 * @param stdClass $syllabus
 * @param context_module $context
 * @param int[] $userids
 * @return int Nombre de responsables enregistrés.
 */
function syllabus_set_teachers(stdClass $syllabus, context_module $context, array $userids): int {
    global $DB;

    $allowed = syllabus_potential_teachers($context);
    $wanted = array_values(array_intersect(array_unique(array_map('intval', $userids)), array_keys($allowed)));
    $current = $DB->get_records_menu('syllabus_teacher', ['syllabusid' => $syllabus->id], '', 'userid, id');
    $transaction = $DB->start_delegated_transaction();
    foreach ($current as $userid => $id) {
        if (!in_array((int) $userid, $wanted, true)) {
            $DB->delete_records('syllabus_teacher', ['id' => $id]);
        }
    }
    foreach ($wanted as $userid) {
        if (!isset($current[$userid])) {
            $DB->insert_record('syllabus_teacher', ['syllabusid' => $syllabus->id, 'userid' => $userid, 'timecreated' => time()]);
        }
    }
    $transaction->allow_commit();
    return count($wanted);
}

// Versions de la fiche.

/**
 * Version publiée (celle que lisent les étudiants), ou null.
 *
 * @param int $syllabusid
 * @return stdClass|null
 */
function syllabus_get_published(int $syllabusid): ?stdClass {
    global $DB;
    $records = $DB->get_records(
        'syllabus_version',
        ['syllabusid' => $syllabusid, 'status' => SYLLABUS_STATUS_PUBLISHED],
        'version DESC',
        '*',
        0,
        1
    );
    return $records ? reset($records) : null;
}

/**
 * Version de travail : brouillon, soumise ou renvoyée. Une seule à la fois.
 *
 * @param int $syllabusid
 * @return stdClass|null
 */
function syllabus_get_working(int $syllabusid): ?stdClass {
    global $DB;
    [$insql, $params] = $DB->get_in_or_equal(
        [SYLLABUS_STATUS_DRAFT, SYLLABUS_STATUS_SUBMITTED, SYLLABUS_STATUS_RETURNED],
        SQL_PARAMS_NAMED
    );
    $records = $DB->get_records_select(
        'syllabus_version',
        "syllabusid = :syllabusid AND status $insql",
        $params + ['syllabusid' => $syllabusid],
        'version DESC',
        '*',
        0,
        1
    );
    return $records ? reset($records) : null;
}

/**
 * Toutes les versions d'un module, la plus récente en tête.
 *
 * @param int $syllabusid
 * @return array
 */
function syllabus_get_versions(int $syllabusid): array {
    global $DB;
    return $DB->get_records('syllabus_version', ['syllabusid' => $syllabusid], 'version DESC');
}

/**
 * Séances d'une version, dans l'ordre.
 *
 * @param int $versionid
 * @return array
 */
function syllabus_get_sessions(int $versionid): array {
    global $DB;
    return $DB->get_records('syllabus_session', ['versionid' => $versionid], 'sortorder ASC, id ASC');
}

/**
 * Remplace les séances d'une version : une séance sans intitulé est ignorée, une durée négative
 * ramenée à zéro, un intervenant désigné par son compte l'emporte sur un nom libre.
 *
 * @param int $versionid
 * @param array $sessions Tableaux {title, kind, duration, teacherid, teachername}.
 * @return int Nombre de séances enregistrées.
 */
function syllabus_set_sessions(int $versionid, array $sessions): int {
    global $DB;

    $DB->delete_records('syllabus_session', ['versionid' => $versionid]);
    $kinds = syllabus_session_kind_options();
    $count = 0;
    foreach ($sessions as $session) {
        $session = (array) $session;
        $title = trim((string) ($session['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $teacherid = !empty($session['teacherid']) ? (int) $session['teacherid'] : null;
        $count++;
        $DB->insert_record('syllabus_session', (object) [
            'versionid' => $versionid,
            'sortorder' => $count * 10,
            'title' => core_text::substr($title, 0, 255),
            'kind' => isset($kinds[$session['kind'] ?? '']) ? $session['kind'] : 'other',
            'duration' => max(0, round((float) ($session['duration'] ?? 0), 2)),
            'teacherid' => $teacherid,
            'teachername' => $teacherid ? '' : core_text::substr(trim((string) ($session['teachername'] ?? '')), 0, 255),
            'timecreated' => time(),
        ]);
    }
    return $count;
}

/**
 * Volume horaire : par nature de séance, séances, travail personnel, total.
 *
 * @param array $sessions
 * @param float|string $personalhours
 * @return stdClass {bykind, sessions, personal, total}
 */
function syllabus_hours(array $sessions, $personalhours = 0.0): stdClass {
    $bykind = [];
    $total = 0.0;
    foreach ($sessions as $session) {
        $bykind[$session->kind] = ($bykind[$session->kind] ?? 0) + (float) $session->duration;
        $total += (float) $session->duration;
    }
    $personal = max(0.0, (float) $personalhours);
    return (object) ['bykind' => $bykind, 'sessions' => round($total, 2), 'personal' => round($personal, 2),
        'total' => round($total + $personal, 2)];
}

/**
 * Version de travail, créée au besoin : copie de la version publiée (rubriques et séances), ou
 * fiche vide pour un module qui n'en a pas encore.
 *
 * @param stdClass $syllabus
 * @param int $userid Auteur.
 * @return stdClass
 */
function syllabus_start_working(stdClass $syllabus, int $userid): stdClass {
    global $DB;

    $lock = syllabus_get_lock((int) $syllabus->id);
    try {
        $working = syllabus_get_working((int) $syllabus->id);
        if ($working) {
            return $working;
        }
        $published = syllabus_get_published((int) $syllabus->id);
        $last = (int) $DB->get_field('syllabus_version', 'MAX(version)', ['syllabusid' => $syllabus->id]);
        $now = time();
        $record = (object) [
            'syllabusid' => $syllabus->id, 'version' => $last + 1, 'status' => SYLLABUS_STATUS_DRAFT,
            'language' => '', 'personalhours' => 0, 'authorid' => $userid, 'timecreated' => $now, 'timemodified' => $now,
        ];
        foreach (array_keys(syllabus_text_fields()) as $field) {
            $record->$field = $published ? $published->$field : '';
        }
        if ($published) {
            $record->language = $published->language;
            $record->personalhours = $published->personalhours;
        }
        $transaction = $DB->start_delegated_transaction();
        $record->id = $DB->insert_record('syllabus_version', $record);
        if ($published) {
            syllabus_set_sessions((int) $record->id, array_map(fn($s) => (array) $s, syllabus_get_sessions((int) $published->id)));
        }
        $transaction->allow_commit();
        return $DB->get_record('syllabus_version', ['id' => $record->id], '*', MUST_EXIST);
    } finally {
        $lock->release();
    }
}

/**
 * Enregistre la version de travail (rubriques, langue, travail personnel, séances). Une version
 * soumise ne se modifie plus : elle attend la DEVE.
 *
 * @param stdClass $version
 * @param stdClass $data
 * @param array|null $sessions null pour garder les séances.
 * @param int $userid
 * @return void
 */
function syllabus_save_working(stdClass $version, stdClass $data, ?array $sessions, int $userid): void {
    global $DB;

    if (!in_array($version->status, [SYLLABUS_STATUS_DRAFT, SYLLABUS_STATUS_RETURNED], true)) {
        throw new moodle_exception('errornotdraft', 'mod_syllabus');
    }
    $record = (object) ['id' => $version->id, 'authorid' => $userid, 'timemodified' => time(),
        'status' => SYLLABUS_STATUS_DRAFT];
    foreach (array_keys(syllabus_text_fields()) as $field) {
        if (property_exists($data, $field)) {
            $record->$field = trim((string) $data->$field);
        }
    }
    if (property_exists($data, 'language')) {
        $record->language = core_text::substr(trim((string) $data->language), 0, 50);
    }
    if (property_exists($data, 'personalhours')) {
        $record->personalhours = max(0, round((float) $data->personalhours, 2));
    }
    $transaction = $DB->start_delegated_transaction();
    $DB->update_record('syllabus_version', $record);
    if ($sessions !== null) {
        syllabus_set_sessions((int) $version->id, $sessions);
    }
    $transaction->allow_commit();
}

/**
 * Soumet la version de travail : publiée aussitôt si la DEVE ne relit pas les syllabus
 * (réglage commun « reviewrequired »), sinon transmise à la DEVE.
 *
 * @param stdClass $syllabus
 * @param stdClass $cm
 * @param stdClass $version
 * @param int $userid
 * @return string 'published' ou 'submitted'
 */
function syllabus_submit(stdClass $syllabus, $cm, stdClass $version, int $userid): string {
    global $DB;

    if (!in_array($version->status, [SYLLABUS_STATUS_DRAFT, SYLLABUS_STATUS_RETURNED], true)) {
        throw new moodle_exception('errornotdraft', 'mod_syllabus');
    }
    if (!syllabus_setting('reviewrequired')) {
        syllabus_publish($syllabus, $version, $userid, '');
        return 'published';
    }
    $DB->update_record('syllabus_version', (object) ['id' => $version->id, 'status' => SYLLABUS_STATUS_SUBMITTED,
        'timesubmitted' => time(), 'timemodified' => time()]);
    syllabus_notify_submitted($syllabus, $cm, $userid);
    return 'submitted';
}

/**
 * Publie une version : elle remplace la version publiée, qui reste dans l'historique.
 *
 * @param stdClass $syllabus
 * @param stdClass $version
 * @param int $reviewerid
 * @param string $comment
 * @return void
 */
function syllabus_publish(stdClass $syllabus, stdClass $version, int $reviewerid, string $comment): void {
    global $DB;

    $lock = syllabus_get_lock((int) $syllabus->id);
    try {
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field_select(
            'syllabus_version',
            'status',
            SYLLABUS_STATUS_SUPERSEDED,
            'syllabusid = ? AND status = ? AND id <> ?',
            [$syllabus->id, SYLLABUS_STATUS_PUBLISHED, $version->id]
        );
        $DB->update_record('syllabus_version', (object) [
            'id' => $version->id, 'status' => SYLLABUS_STATUS_PUBLISHED, 'reviewerid' => $reviewerid,
            'reviewcomment' => $comment, 'timereviewed' => time(), 'timemodified' => time(),
            'timesubmitted' => !empty($version->timesubmitted) ? $version->timesubmitted : time(),
        ]);
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
}

/**
 * Décision de la DEVE sur une version soumise : publier, renvoyer à l'auteur (commentaire
 * obligatoire), refuser.
 *
 * @param stdClass $syllabus
 * @param stdClass $cm
 * @param stdClass $version
 * @param int $reviewerid
 * @param string $decision publish, return, reject
 * @param string $comment
 * @return bool False si la version n'était plus soumise (décidée entre-temps).
 */
function syllabus_review(stdClass $syllabus, $cm, stdClass $version, int $reviewerid, string $decision, string $comment): bool {
    global $DB;

    $current = $DB->get_field('syllabus_version', 'status', ['id' => $version->id]);
    if ($current !== SYLLABUS_STATUS_SUBMITTED) {
        return false;
    }
    if ($decision === 'publish') {
        syllabus_publish($syllabus, $version, $reviewerid, $comment);
    } else {
        $status = $decision === 'return' ? SYLLABUS_STATUS_RETURNED : SYLLABUS_STATUS_REJECTED;
        $DB->update_record('syllabus_version', (object) ['id' => $version->id, 'status' => $status,
            'reviewerid' => $reviewerid, 'reviewcomment' => $comment, 'timereviewed' => time(), 'timemodified' => time()]);
    }
    syllabus_notify_reviewed($syllabus, $cm, $version, $decision, $comment);
    return true;
}

// Référentiel commun : types de module.

/**
 * Types de module.
 *
 * @return array id => stdClass
 */
function syllabus_get_types(): array {
    global $DB;
    return $DB->get_records('syllabus_type', null, 'sortorder ASC, name ASC');
}

/**
 * Types en liste de choix, avec « aucun ».
 *
 * @return array
 */
function syllabus_type_options(): array {
    $options = [0 => get_string('notype', 'mod_syllabus')];
    foreach (syllabus_get_types() as $type) {
        $options[$type->id] = format_string($type->name);
    }
    return $options;
}

/**
 * Crée ou renomme un type.
 *
 * @param string $name
 * @param int $id 0 pour créer.
 * @return int
 */
function syllabus_save_type(string $name, int $id = 0): int {
    global $DB;

    $name = core_text::substr(trim($name), 0, 255);
    if ($name === '') {
        throw new moodle_exception('errorname', 'mod_syllabus');
    }
    if ($id) {
        $DB->update_record('syllabus_type', (object) ['id' => $id, 'name' => $name, 'timemodified' => time()]);
        return $id;
    }
    $order = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {syllabus_type}');
    return (int) $DB->insert_record('syllabus_type', (object) ['name' => $name, 'sortorder' => $order + 10,
        'timecreated' => time(), 'timemodified' => time()]);
}

/**
 * Supprime un type : refusé s'il est porté par un module ou visé par des questions communes.
 *
 * @param int $id
 * @return bool
 */
function syllabus_delete_type(int $id): bool {
    global $DB;

    if (
        $DB->record_exists('syllabus', ['typeid' => $id])
            || $DB->record_exists('syllabus_question', ['syllabusid' => 0, 'typeid' => $id])
    ) {
        return false;
    }
    $DB->delete_records('syllabus_type', ['id' => $id]);
    return true;
}

// Questions d'évaluation.

/**
 * Formes de question.
 *
 * @return array code => libellé
 */
function syllabus_question_kinds(): array {
    $kinds = [];
    foreach (['scale', 'yesno', 'choice', 'text'] as $kind) {
        $kinds[$kind] = get_string('questionkind_' . $kind, 'mod_syllabus');
    }
    return $kinds;
}

/**
 * Choix d'une question à choix, un par ligne non vide.
 *
 * @param stdClass $question
 * @return string[]
 */
function syllabus_question_options(stdClass $question): array {
    $lines = preg_split('/\R/', (string) ($question->options ?? ''));
    return array_values(array_filter(array_map('trim', $lines), fn($line) => $line !== ''));
}

/**
 * Questions communes définies par la DEVE.
 *
 * @param bool $onlyenabled
 * @return array id => stdClass
 */
function syllabus_get_common_questions(bool $onlyenabled = false): array {
    global $DB;
    $conditions = ['syllabusid' => 0];
    if ($onlyenabled) {
        $conditions['enabled'] = 1;
    }
    return $DB->get_records('syllabus_question', $conditions, 'origin ASC, typeid ASC, sortorder ASC, id ASC');
}

/**
 * Questions communes qui s'appliquent à un module : celles de tous les modules, puis celles de
 * son type.
 *
 * @param stdClass $syllabus
 * @return array
 */
function syllabus_applicable_common_questions(stdClass $syllabus): array {
    $all = [];
    $bytype = [];
    foreach (syllabus_get_common_questions(true) as $question) {
        if ($question->origin === 'common') {
            $all[] = $question;
        } else if ($question->origin === 'type' && (int) $question->typeid === (int) $syllabus->typeid && $syllabus->typeid) {
            $bytype[] = $question;
        }
    }
    return array_merge($all, $bytype);
}

/**
 * Questions propres à un module : celles de l'enseignant et, une fois l'évaluation figée, les
 * copies des questions communes.
 *
 * @param int $syllabusid
 * @param string|null $origin Pour ne lire qu'une origine.
 * @return array id => stdClass
 */
function syllabus_get_module_questions(int $syllabusid, ?string $origin = null): array {
    global $DB;
    $conditions = ['syllabusid' => $syllabusid];
    if ($origin !== null) {
        $conditions['origin'] = $origin;
    }
    return $DB->get_records('syllabus_question', $conditions, 'sortorder ASC, id ASC');
}

/**
 * Questions de l'évaluation d'un module, dans l'ordre de passation : communes, de type, puis de
 * l'enseignant. Avant que l'évaluation soit figée, les questions communes sont lues en direct.
 *
 * @param stdClass $syllabus
 * @return array Liste de stdClass (id, origin, kind, text, options, required). Les questions
 *     communes non figées ont un id négatif : elles ne peuvent pas recevoir de réponse.
 */
function syllabus_evaluation_questions(stdClass $syllabus): array {
    if (!empty($syllabus->evalfrozen)) {
        return array_values(syllabus_get_module_questions((int) $syllabus->id));
    }
    $questions = [];
    foreach (syllabus_applicable_common_questions($syllabus) as $common) {
        $copy = clone $common;
        $copy->id = -$common->id;
        $questions[] = $copy;
    }
    return array_merge($questions, array_values(syllabus_get_module_questions((int) $syllabus->id, 'teacher')));
}

/**
 * Enregistre une question. Une question commune (syllabusid 0) a pour origine « common » ou
 * « type » ; une question de module, « teacher ». Les questions d'un module figé ne changent
 * plus.
 *
 * @param stdClass $data {id?, syllabusid, origin, typeid, kind, text, options, required, enabled}
 * @return int
 */
function syllabus_save_question(stdClass $data): int {
    global $DB;

    $syllabusid = (int) ($data->syllabusid ?? 0);
    if ($syllabusid && $DB->get_field('syllabus', 'evalfrozen', ['id' => $syllabusid])) {
        throw new moodle_exception('errorfrozen', 'mod_syllabus');
    }
    $kinds = syllabus_question_kinds();
    $origin = $syllabusid ? 'teacher' : (($data->origin ?? 'common') === 'type' ? 'type' : 'common');
    $record = (object) [
        'syllabusid' => $syllabusid,
        'origin' => $origin,
        'typeid' => $origin === 'type' ? (int) ($data->typeid ?? 0) : 0,
        'kind' => isset($kinds[$data->kind ?? '']) ? $data->kind : 'scale',
        'text' => trim((string) ($data->text ?? '')),
        'options' => trim((string) ($data->options ?? '')),
        'required' => empty($data->required) ? 0 : 1,
        'enabled' => isset($data->enabled) ? (empty($data->enabled) ? 0 : 1) : 1,
        'timemodified' => time(),
    ];
    if ($record->text === '') {
        throw new moodle_exception('errorquestiontext', 'mod_syllabus');
    }
    if ($record->kind === 'choice' && count(syllabus_question_options($record)) < 2) {
        throw new moodle_exception('errorchoices', 'mod_syllabus');
    }
    if ($origin === 'type' && !$record->typeid) {
        throw new moodle_exception('errortype', 'mod_syllabus');
    }
    if (!empty($data->id)) {
        $existing = $DB->get_record('syllabus_question', ['id' => $data->id], '*', MUST_EXIST);
        if ((int) $existing->syllabusid !== $syllabusid) {
            throw new moodle_exception('invalidrecord', 'error');
        }
        $record->id = $existing->id;
        $DB->update_record('syllabus_question', $record);
        return (int) $existing->id;
    }
    $record->sortorder = (int) $DB->get_field_sql(
        'SELECT MAX(sortorder) FROM {syllabus_question} WHERE syllabusid = ?',
        [$syllabusid]
    ) + 10;
    $record->timecreated = time();
    return (int) $DB->insert_record('syllabus_question', $record);
}

/**
 * Supprime une question (d'un module non figé, ou commune : les copies figées restent).
 *
 * @param int $id
 * @param int $syllabusid 0 pour une question commune.
 * @return void
 */
function syllabus_delete_question(int $id, int $syllabusid): void {
    global $DB;
    if ($syllabusid && $DB->get_field('syllabus', 'evalfrozen', ['id' => $syllabusid])) {
        throw new moodle_exception('errorfrozen', 'mod_syllabus');
    }
    $DB->delete_records('syllabus_question', ['id' => $id, 'syllabusid' => $syllabusid]);
}

/**
 * Monte ou descend une question parmi celles de même rattachement.
 *
 * @param int $id
 * @param int $syllabusid
 * @param int $direction -1 pour monter, 1 pour descendre.
 * @return void
 */
function syllabus_move_question(int $id, int $syllabusid, int $direction): void {
    global $DB;
    if ($syllabusid && $DB->get_field('syllabus', 'evalfrozen', ['id' => $syllabusid])) {
        throw new moodle_exception('errorfrozen', 'mod_syllabus');
    }
    $question = $DB->get_record('syllabus_question', ['id' => $id, 'syllabusid' => $syllabusid], '*', MUST_EXIST);
    $siblings = array_values($DB->get_records('syllabus_question', ['syllabusid' => $syllabusid, 'origin' => $question->origin,
        'typeid' => $question->typeid], 'sortorder ASC, id ASC'));
    $index = array_search((int) $id, array_map(fn($q) => (int) $q->id, $siblings), true);
    $other = $siblings[$index + $direction] ?? null;
    if ($index === false || !$other) {
        return;
    }
    $transaction = $DB->start_delegated_transaction();
    $DB->set_field('syllabus_question', 'sortorder', $other->sortorder, ['id' => $question->id]);
    $DB->set_field('syllabus_question', 'sortorder', $question->sortorder, ['id' => $other->id]);
    if ((int) $other->sortorder === (int) $question->sortorder) {
        $DB->set_field('syllabus_question', 'sortorder', $question->sortorder + $direction, ['id' => $question->id]);
    }
    $transaction->allow_commit();
}

// Évaluation.

/**
 * État de l'évaluation d'un module.
 *
 * @param stdClass $syllabus
 * @param int|null $now
 * @return string SYLLABUS_EVAL_*
 */
function syllabus_eval_state(stdClass $syllabus, ?int $now = null): string {
    $now = $now ?? time();
    if (empty($syllabus->evalopen)) {
        return SYLLABUS_EVAL_NONE;
    }
    if ($now < (int) $syllabus->evalopen) {
        return SYLLABUS_EVAL_UPCOMING;
    }
    if (!empty($syllabus->evalclose) && $now >= (int) $syllabus->evalclose) {
        return SYLLABUS_EVAL_CLOSED;
    }
    return SYLLABUS_EVAL_OPEN;
}

/**
 * Fige les questions d'une évaluation ouverte : les questions communes du moment sont copiées
 * dans le module (avec leur origine), et plus aucune question ne se modifie. Les réponses portent
 * ainsi sur des questions qui ne changeront plus, même si la DEVE modifie ensuite les siennes.
 *
 * @param stdClass $syllabus Mis à jour (evalfrozen).
 * @return bool Vrai si les questions viennent d'être figées.
 */
function syllabus_freeze_questions(stdClass $syllabus): bool {
    global $DB;

    if (!empty($syllabus->evalfrozen)) {
        return false;
    }
    $lock = syllabus_get_lock((int) $syllabus->id);
    try {
        $current = $DB->get_record('syllabus', ['id' => $syllabus->id], '*', MUST_EXIST);
        if (!empty($current->evalfrozen)) {
            $syllabus->evalfrozen = 1;
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        $order = 0;
        foreach (syllabus_applicable_common_questions($current) as $common) {
            $order += 10;
            $copy = clone $common;
            unset($copy->id);
            $copy->syllabusid = $syllabus->id;
            $copy->sourceid = $common->id;
            $copy->sortorder = $order;
            $copy->timecreated = time();
            $DB->insert_record('syllabus_question', $copy);
        }
        // Les questions de l'enseignant passent après les questions communes.
        foreach (syllabus_get_module_questions((int) $syllabus->id, 'teacher') as $question) {
            $order += 10;
            $DB->set_field('syllabus_question', 'sortorder', $order, ['id' => $question->id]);
        }
        $DB->set_field('syllabus', 'evalfrozen', 1, ['id' => $syllabus->id]);
        $transaction->allow_commit();
        $syllabus->evalfrozen = 1;
        return true;
    } finally {
        $lock->release();
    }
}

/**
 * L'étudiant a-t-il déjà répondu ?
 *
 * @param int $syllabusid
 * @param int $userid
 * @return bool
 */
function syllabus_has_responded(int $syllabusid, int $userid): bool {
    global $DB;
    return $DB->record_exists('syllabus_participant', ['syllabusid' => $syllabusid, 'userid' => $userid]);
}

/**
 * Raison pour laquelle l'étudiant ne peut pas évaluer le module maintenant, ou null s'il le peut.
 *
 * @param stdClass $syllabus
 * @param context_module $context
 * @param int $userid
 * @return string|null Identifiant de chaîne.
 */
function syllabus_evaluation_problem(stdClass $syllabus, context_module $context, int $userid): ?string {
    if (!has_capability('mod/syllabus:evaluate', $context, $userid, false)) {
        return 'evalnotallowed';
    }
    $state = syllabus_eval_state($syllabus);
    if ($state === SYLLABUS_EVAL_NONE) {
        return 'evalnone';
    }
    if ($state === SYLLABUS_EVAL_UPCOMING) {
        return 'evalupcoming';
    }
    if ($state === SYLLABUS_EVAL_CLOSED) {
        return 'evalclosed';
    }
    if (syllabus_has_responded((int) $syllabus->id, $userid)) {
        return 'evaldone';
    }
    return null;
}

/**
 * Enregistre la réponse d'un étudiant, anonyme : la réponse ne porte aucun identifiant ni heure ;
 * seule sa participation est notée, pour qu'il ne réponde pas deux fois. Participation et
 * réponse sont écrites ensemble, sous le verrou du module.
 *
 * @param stdClass $syllabus
 * @param context_module $context
 * @param int $userid
 * @param array $answers questionid => valeur (int ou texte)
 * @return array Erreurs par questionid (vide si la réponse est enregistrée).
 */
function syllabus_submit_response(stdClass $syllabus, context_module $context, int $userid, array $answers): array {
    global $DB;

    $problem = syllabus_evaluation_problem($syllabus, $context, $userid);
    if ($problem !== null) {
        throw new moodle_exception($problem, 'mod_syllabus');
    }
    syllabus_freeze_questions($syllabus);
    $questions = syllabus_get_module_questions((int) $syllabus->id);

    $rows = [];
    $errors = [];
    foreach ($questions as $question) {
        $raw = $answers[$question->id] ?? null;
        $raw = is_string($raw) ? trim($raw) : $raw;
        $empty = $raw === null || $raw === '';
        if ($empty) {
            if ($question->required) {
                $errors[$question->id] = get_string('required');
            }
            continue;
        }
        $row = (object) ['questionid' => $question->id, 'value' => null, 'textvalue' => null];
        if ($question->kind === 'text') {
            $row->textvalue = core_text::substr((string) $raw, 0, 5000);
        } else {
            $value = (int) $raw;
            $max = match ($question->kind) {
                'scale' => 5,
                'yesno' => 1,
                default => count(syllabus_question_options($question)) - 1,
            };
            $min = $question->kind === 'scale' ? 1 : 0;
            if ((string) $value !== (string) $raw || $value < $min || $value > $max) {
                $errors[$question->id] = get_string('errorvalue', 'mod_syllabus');
                continue;
            }
            $row->value = $value;
        }
        $rows[] = $row;
    }
    if ($errors) {
        return $errors;
    }

    $lock = syllabus_get_lock((int) $syllabus->id);
    try {
        if (syllabus_has_responded((int) $syllabus->id, $userid)) {
            throw new moodle_exception('evaldone', 'mod_syllabus');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record('syllabus_participant', (object) ['syllabusid' => $syllabus->id, 'userid' => $userid]);
        $responseid = $DB->insert_record('syllabus_response', (object) ['syllabusid' => $syllabus->id,
            'daysubmitted' => usergetmidnight(time(), core_date::get_server_timezone())]);
        foreach ($rows as $row) {
            $row->responseid = $responseid;
            $DB->insert_record('syllabus_answer', $row);
        }
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    \mod_syllabus\event\evaluation_submitted::create(['objectid' => $syllabus->id, 'context' => $context])->trigger();
    return [];
}

/**
 * Nombre de réponses.
 *
 * @param int $syllabusid
 * @return int
 */
function syllabus_count_responses(int $syllabusid): int {
    global $DB;
    return $DB->count_records('syllabus_participant', ['syllabusid' => $syllabusid]);
}

/**
 * Nombre d'étudiants appelés à répondre : inscrits actifs du cours qui peuvent évaluer.
 *
 * @param context_module $context
 * @return int
 */
function syllabus_count_eligible(context_module $context): int {
    return count_enrolled_users($context, 'mod/syllabus:evaluate', 0, true);
}

/**
 * Résultats agrégés d'une évaluation. Sous le seuil d'anonymat (réglage commun), seul le nombre
 * de réponses est donné. Les réponses libres sont triées par ordre alphabétique : rien ne
 * rappelle l'ordre, donc le moment, des réponses.
 *
 * @param stdClass $syllabus
 * @return stdClass {responses, threshold, shown, questions: [{question, count, mean, distribution, labels, texts}]}
 */
function syllabus_get_results(stdClass $syllabus): stdClass {
    global $DB;

    $responses = syllabus_count_responses((int) $syllabus->id);
    $threshold = (int) syllabus_setting('resultsthreshold');
    $result = (object) ['responses' => $responses, 'threshold' => $threshold, 'shown' => $responses >= $threshold,
        'questions' => []];
    $questions = syllabus_evaluation_questions($syllabus);
    if (!$result->shown) {
        foreach ($questions as $question) {
            $result->questions[] = (object) ['question' => $question, 'count' => 0];
        }
        return $result;
    }
    $answers = [];
    $rs = $DB->get_recordset_sql(
        "SELECT a.id, a.questionid, a.value, a.textvalue
           FROM {syllabus_answer} a
           JOIN {syllabus_response} r ON r.id = a.responseid
          WHERE r.syllabusid = ?",
        [$syllabus->id]
    );
    foreach ($rs as $answer) {
        $answers[(int) $answer->questionid][] = $answer;
    }
    $rs->close();

    foreach ($questions as $question) {
        $list = $answers[(int) $question->id] ?? [];
        $entry = (object) ['question' => $question, 'count' => count($list), 'mean' => null, 'distribution' => [],
            'labels' => [], 'texts' => []];
        if ($question->kind === 'text') {
            $entry->texts = array_map(fn($a) => (string) $a->textvalue, $list);
            core_collator::asort($entry->texts);
            $entry->texts = array_values($entry->texts);
        } else {
            if ($question->kind === 'scale') {
                $entry->labels = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'];
            } else if ($question->kind === 'yesno') {
                $entry->labels = [1 => get_string('yes'), 0 => get_string('no')];
            } else {
                $entry->labels = syllabus_question_options($question);
            }
            $entry->distribution = array_fill_keys(array_keys($entry->labels), 0);
            $sum = 0;
            foreach ($list as $answer) {
                $value = (int) $answer->value;
                if (array_key_exists($value, $entry->distribution)) {
                    $entry->distribution[$value]++;
                }
                $sum += $value;
            }
            if ($question->kind === 'scale' && $list) {
                $entry->mean = round($sum / count($list), 2);
            }
        }
        $result->questions[] = $entry;
    }
    return $result;
}

// Messages.

/**
 * Prévient la DEVE (titulaires de mod/syllabus:manage dans le module) qu'un syllabus attend sa
 * revue.
 *
 * @param stdClass $syllabus
 * @param stdClass $cm
 * @param int $authorid
 * @return void
 */
function syllabus_notify_submitted(stdClass $syllabus, $cm, int $authorid): void {
    $context = context_module::instance($cm->id);
    $url = new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id, 'show' => 'working']);
    $a = (object) ['module' => format_string($syllabus->name), 'url' => $url->out(false)];
    foreach (get_users_by_capability($context, 'mod/syllabus:manage', 'u.*') as $recipient) {
        syllabus_send_message(
            'syllabussubmitted',
            $recipient,
            get_string('msgsubmitted_subject', 'mod_syllabus', $a),
            get_string('msgsubmitted_body', 'mod_syllabus', $a),
            $url,
            $syllabus->course
        );
    }
}

/**
 * Prévient les responsables de la décision de la DEVE.
 *
 * @param stdClass $syllabus
 * @param stdClass $cm
 * @param stdClass $version
 * @param string $decision
 * @param string $comment
 * @return void
 */
function syllabus_notify_reviewed(stdClass $syllabus, $cm, stdClass $version, string $decision, string $comment): void {
    $url = new moodle_url('/mod/syllabus/view.php', ['id' => $cm->id]);
    $a = (object) ['module' => format_string($syllabus->name), 'decision' => get_string('decision_' . $decision, 'mod_syllabus'),
        'comment' => $comment, 'url' => $url->out(false)];
    foreach (array_keys(syllabus_get_teachers((int) $syllabus->id)) as $teacherid) {
        $teacher = core_user::get_user($teacherid);
        syllabus_send_message(
            'syllabusreviewed',
            $teacher,
            get_string('msgreviewed_subject', 'mod_syllabus', $a),
            get_string('msgreviewed_body', 'mod_syllabus', $a),
            $url,
            $syllabus->course
        );
    }
}

/**
 * Annonce l'ouverture de l'évaluation aux étudiants qui peuvent y répondre.
 *
 * @param stdClass $syllabus
 * @param stdClass $cm
 * @return int Nombre de messages envoyés.
 */
function syllabus_notify_evaluation_open(stdClass $syllabus, $cm): int {
    $context = context_module::instance($cm->id);
    $url = new moodle_url('/mod/syllabus/evaluate.php', ['id' => $cm->id]);
    $a = (object) ['module' => format_string($syllabus->name), 'url' => $url->out(false),
        'close' => $syllabus->evalclose ? userdate($syllabus->evalclose) : get_string('noclose', 'mod_syllabus')];
    $count = 0;
    foreach (get_enrolled_users($context, 'mod/syllabus:evaluate', 0, 'u.*', null, 0, 0, true) as $student) {
        if (syllabus_has_responded((int) $syllabus->id, (int) $student->id)) {
            continue;
        }
        syllabus_send_message(
            'evaluationopen',
            $student,
            get_string('msgopen_subject', 'mod_syllabus', $a),
            get_string('msgopen_body', 'mod_syllabus', $a),
            $url,
            $syllabus->course
        );
        $count++;
    }
    return $count;
}

/**
 * Envoi d'un message du module.
 *
 * @param string $provider
 * @param stdClass $recipient
 * @param string $subject
 * @param string $body Texte brut.
 * @param moodle_url $url
 * @param int $courseid
 * @return void
 */
function syllabus_send_message(
    string $provider,
    stdClass $recipient,
    string $subject,
    string $body,
    moodle_url $url,
    int $courseid
): void {
    $message = new \core\message\message();
    $message->component = 'mod_syllabus';
    $message->name = $provider;
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $recipient;
    $message->subject = $subject;
    $message->fullmessage = $body;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = format_text($body, FORMAT_PLAIN);
    $message->smallmessage = $subject;
    $message->notification = 1;
    $message->contexturl = $url->out(false);
    $message->contexturlname = $subject;
    $message->courseid = $courseid;
    message_send($message);
}

// Listes pour les activités de synthèse.

/**
 * Modules (instances avec leur module de cours et leur cours), filtrés. Les modules supprimés ou
 * en cours de suppression sont écartés.
 *
 * @param array $filters categoryid (avec sous-catégories), teacherid, courseids, typeid, search, status (de version), syllabusids
 * @param int $limitfrom
 * @param int $limitnum
 * @return array [lignes, total]
 */
function syllabus_list_instances(array $filters = [], int $limitfrom = 0, int $limitnum = 0): array {
    global $DB;

    $where = ['cm.deletioninprogress = 0'];
    $params = ['modname' => 'syllabus'];
    if (!empty($filters['categoryid'])) {
        $category = core_course_category::get((int) $filters['categoryid'], IGNORE_MISSING, true);
        if ($category) {
            $where[] = '(cc.id = :catid OR ' . $DB->sql_like('cc.path', ':catpath') . ')';
            $params['catid'] = $category->id;
            $params['catpath'] = $category->path . '/%';
        }
    }
    if (!empty($filters['teacherid'])) {
        $where[] = 'EXISTS (SELECT 1 FROM {syllabus_teacher} st WHERE st.syllabusid = s.id AND st.userid = :teacherid)';
        $params['teacherid'] = (int) $filters['teacherid'];
    }
    if (isset($filters['courseids'])) {
        if (!$filters['courseids']) {
            return [[], 0];
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_values($filters['courseids']), SQL_PARAMS_NAMED, 'crs');
        $where[] = "s.course $insql";
        $params += $inparams;
    }
    if (!empty($filters['syllabusids'])) {
        [$insql, $inparams] = $DB->get_in_or_equal(array_values($filters['syllabusids']), SQL_PARAMS_NAMED, 'syl');
        $where[] = "s.id $insql";
        $params += $inparams;
    }
    if (!empty($filters['typeid'])) {
        $where[] = 's.typeid = :typeid';
        $params['typeid'] = (int) $filters['typeid'];
    }
    if (!empty($filters['search'])) {
        $like = [];
        foreach (['s.name', 's.code', 'c.fullname', 'c.shortname'] as $i => $field) {
            $like[] = $DB->sql_like($field, ':search' . $i, false, false);
            $params['search' . $i] = '%' . $DB->sql_like_escape($filters['search']) . '%';
        }
        $where[] = '(' . implode(' OR ', $like) . ')';
    }
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'nopublished') {
            $where[] = "NOT EXISTS (SELECT 1 FROM {syllabus_version} v WHERE v.syllabusid = s.id AND v.status = 'published')";
        } else {
            $where[] = 'EXISTS (SELECT 1 FROM {syllabus_version} v WHERE v.syllabusid = s.id AND v.status = :vstatus)';
            $params['vstatus'] = $filters['status'];
        }
    }
    $from = "FROM {syllabus} s
             JOIN {modules} m ON m.name = :modname
             JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id
             JOIN {course} c ON c.id = s.course
             JOIN {course_categories} cc ON cc.id = c.category
            WHERE " . implode(' AND ', $where);
    $total = $DB->count_records_sql("SELECT COUNT(1) $from", $params);
    $rows = $DB->get_records_sql(
        "SELECT s.*, cm.id AS cmid, cm.visible AS cmvisible, c.fullname AS coursename, c.shortname AS courseshortname,
                cc.name AS categoryname
         $from
         ORDER BY c.fullname, s.name, s.id",
        $params,
        $limitfrom,
        $limitnum
    );
    return [$rows, $total];
}

/**
 * Indicateurs de plusieurs modules, en quelques requêtes : versions (publiée, de travail),
 * réponses à l'évaluation.
 *
 * @param int[] $syllabusids
 * @return array syllabusid => stdClass {published, working, responses}
 */
function syllabus_get_overview(array $syllabusids): array {
    global $DB;

    $result = [];
    foreach ($syllabusids as $id) {
        $result[$id] = (object) ['published' => null, 'working' => null, 'responses' => 0];
    }
    if (!$syllabusids) {
        return $result;
    }
    [$insql, $params] = $DB->get_in_or_equal($syllabusids, SQL_PARAMS_NAMED);
    $rs = $DB->get_recordset_select('syllabus_version', "syllabusid $insql AND status IN ('published', 'draft', 'submitted', "
        . "'returned')", $params, 'version ASC', 'id, syllabusid, version, status, timesubmitted, timemodified');
    foreach ($rs as $version) {
        $key = $version->status === SYLLABUS_STATUS_PUBLISHED ? 'published' : 'working';
        $result[(int) $version->syllabusid]->$key = $version;
    }
    $rs->close();
    $counts = $DB->get_records_sql_menu("SELECT syllabusid, COUNT(1) FROM {syllabus_participant} WHERE syllabusid $insql "
        . 'GROUP BY syllabusid', $params);
    foreach ($counts as $id => $count) {
        $result[(int) $id]->responses = (int) $count;
    }
    return $result;
}

// Pages.

/**
 * Amorce d'une page de l'activité, après require_login() : cours, instance, contexte, droits.
 *
 * @param stdClass $cm Module de cours.
 * @param string $path Chemin de la page (/mod/syllabus/x.php).
 * @param array $params Paramètres de l'adresse de la page, en plus de id.
 * @return array [cm, course, syllabus, context, rights]
 */
function syllabus_page_init($cm, string $path, array $params = []): array {
    global $DB, $PAGE;

    $course = get_course($cm->course);
    $syllabus = $DB->get_record('syllabus', ['id' => $cm->instance], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);
    require_capability('mod/syllabus:view', $context);
    $PAGE->set_url(new moodle_url($path, ['id' => $cm->id] + $params));
    $PAGE->set_title(format_string($syllabus->name));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);
    return [$cm, $course, $syllabus, $context, syllabus_get_rights($syllabus, $context)];
}

/**
 * Badge d'état de l'évaluation.
 *
 * @param stdClass $syllabus
 * @return string HTML
 */
function syllabus_eval_badge(stdClass $syllabus): string {
    $state = syllabus_eval_state($syllabus);
    $classes = [SYLLABUS_EVAL_NONE => 'badge-secondary', SYLLABUS_EVAL_UPCOMING => 'badge-info',
        SYLLABUS_EVAL_OPEN => 'badge-success', SYLLABUS_EVAL_CLOSED => 'badge-dark'];
    return html_writer::span(get_string('evalstate_' . $state, 'mod_syllabus'), 'badge ' . $classes[$state]);
}

/**
 * Fenêtre d'évaluation en clair.
 *
 * @param stdClass $syllabus
 * @return string
 */
function syllabus_eval_window_label(stdClass $syllabus): string {
    if (empty($syllabus->evalopen)) {
        return get_string('evalnowindow', 'mod_syllabus');
    }
    $format = get_string('strftimedatetimeshort', 'langconfig');
    $a = (object) ['open' => userdate($syllabus->evalopen, $format),
        'close' => $syllabus->evalclose ? userdate($syllabus->evalclose, $format) : get_string('noclose', 'mod_syllabus')];
    return get_string('evalwindow', 'mod_syllabus', $a);
}

/**
 * Fixe la fenêtre d'évaluation d'un module.
 *
 * @param stdClass $syllabus Mis à jour.
 * @param int $open 0 pour aucune évaluation.
 * @param int $close 0 pour sans fin.
 * @return void
 */
function syllabus_set_window(stdClass $syllabus, int $open, int $close): void {
    global $DB;

    if ($close && (!$open || $close <= $open)) {
        throw new moodle_exception('errorcloseafteropen', 'mod_syllabus');
    }
    $DB->update_record('syllabus', (object) ['id' => $syllabus->id, 'evalopen' => $open, 'evalclose' => $close,
        'timemodified' => time()]);
    $syllabus->evalopen = $open;
    $syllabus->evalclose = $close;
}

/**
 * Tableau de questions d'évaluation.
 *
 * @param array $questions
 * @param callable|null $actions function(stdClass $question, int $index, int $count): string
 * @param bool $withscope Affiche à quoi s'applique la question (questions communes).
 * @return string HTML
 */
function syllabus_render_questions(array $questions, ?callable $actions = null, bool $withscope = false): string {
    if (!$questions) {
        return html_writer::tag('p', get_string('noquestions', 'mod_syllabus'), ['class' => 'text-muted']);
    }
    $kinds = syllabus_question_kinds();
    $types = $withscope ? syllabus_get_types() : [];
    $table = new html_table();
    $table->head = ['#', get_string('questiontext', 'mod_syllabus'), get_string('questionkind', 'mod_syllabus'),
        get_string('questionrequired', 'mod_syllabus')];
    if ($withscope) {
        $table->head[] = get_string('questionscope', 'mod_syllabus');
        $table->head[] = get_string('questionenabled', 'mod_syllabus');
    }
    if ($actions) {
        $table->head[] = get_string('actions');
    }
    $questions = array_values($questions);
    foreach ($questions as $index => $question) {
        $text = format_string($question->text);
        if ($question->kind === 'choice') {
            $text .= html_writer::div(s(implode(' / ', syllabus_question_options($question))), 'small text-muted');
        }
        $row = [$index + 1, $text, $kinds[$question->kind] ?? s($question->kind),
            $question->required ? get_string('yes') : get_string('no')];
        if ($withscope) {
            $row[] = $question->origin === 'type'
                ? get_string('scope_typeof', 'mod_syllabus', format_string($types[$question->typeid]->name ?? '?'))
                : get_string('scope_common', 'mod_syllabus');
            $row[] = $question->enabled ? get_string('yes') : get_string('no');
        }
        if ($actions) {
            $row[] = $actions($question, $index, count($questions));
        }
        $table->data[] = $row;
    }
    return html_writer::table($table);
}
