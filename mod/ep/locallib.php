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
 * Fonctions métier internes pour mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ep/lib.php');

// Libellés et formats.

/**
 * Retourne le libellé lisible d'un statut de crédit.
 *
 * @param int $status
 * @return string
 */
function ep_status_label($status) {
    return ep_request_memo('statuslabel' . (int) $status, fn() => ep_status_label_compute($status));
}

/**
 * Libellé d'un état, sans mise en mémoire (voir ep_status_label()).
 *
 * @param int $status
 * @return string
 */
function ep_status_label_compute($status) {
    switch ((int) $status) {
        case EP_STATUS_CANCELLED:
            return get_string('status_cancelled', 'mod_ep');
        case EP_STATUS_REJECTED:
            return get_string('status_rejected', 'mod_ep');
        case EP_STATUS_PENDING:
            return get_string('status_pending', 'mod_ep');
        case EP_STATUS_VALIDATED:
            return get_string('status_validated', 'mod_ep');
        default:
            return '';
    }
}

/**
 * Retourne une classe CSS de badge selon le statut du crédit.
 *
 * @param int $status
 * @return string
 */
function ep_status_badgeclass($status) {
    switch ((int) $status) {
        case EP_STATUS_CANCELLED:
            return 'badge-dark';
        case EP_STATUS_REJECTED:
            return 'badge-danger';
        case EP_STATUS_PENDING:
            return 'badge-info';
        case EP_STATUS_VALIDATED:
            return 'badge-success';
        default:
            return 'badge-secondary';
    }
}

/**
 * Statuts proposés dans les filtres de liste, dans l'ordre du circuit de validation.
 *
 * @return array int => libellé
 */
function ep_status_options() {
    $options = [];
    foreach ([EP_STATUS_CANCELLED, EP_STATUS_REJECTED, EP_STATUS_PENDING, EP_STATUS_VALIDATED] as $status) {
        $options[$status] = ep_status_label($status);
    }
    return $options;
}

/**
 * Formate un nombre d'ECTS pour l'affichage : les ECTS sont fractionnaires (un stage EP peut en
 * valoir 0,25 par jour), mais les afficher systématiquement avec deux décimales rendrait
 * illisibles les valeurs entières, qui sont la majorité.
 *
 * @param float $ects
 * @return string
 */
function ep_format_ects($ects) {
    return format_float((float) $ects, 2, true, true);
}

/**
 * Formate un nombre d'ECTS pour la valeur d'un champ de saisie numérique : sans séparateur
 * localisé, contrairement à ep_format_ects(). Un « 2,5 » à la française serait refusé par un
 * champ <input type="number">, qui n'accepte que le point décimal.
 *
 * @param float $ects
 * @return string
 */
function ep_format_ects_input($ects) {
    $formatted = rtrim(rtrim(number_format((float) $ects, 3, '.', ''), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
}

/**
 * Formate un nombre d'ECTS suivi de son unité, pour les cellules où le chiffre seul serait
 * ambigu (synthèse, totaux).
 *
 * @param float $ects
 * @return string
 */
function ep_format_ects_unit($ects) {
    return get_string('ectsvalue', 'mod_ep', ep_format_ects($ects));
}

/**
 * Options d'année d'étude, alignées sur celles de mod_stage (0 = non spécifiée).
 *
 * @return array int => libellé
 */
function ep_studyyear_options() {
    // Appelée pour chaque ligne des listes (via ep_studyyear_label()) : calculée une fois par
    // requête et par langue.
    return ep_request_memo('studyyears', function () {
        $options = [0 => get_string('studyyear_unspecified', 'mod_ep')];
        for ($year = 1; $year <= 6; $year++) {
            $options[$year] = get_string('studyyear_n', 'mod_ep', $year);
        }
        return $options;
    });
}

/**
 * Valeur calculée une fois par requête et par langue : libellés et fragments HTML répétés sur
 * chaque ligne des listes. Cache de requête plutôt que variable statique : il est vidé entre deux
 * tests unitaires.
 *
 * @param string $key
 * @param callable $compute
 * @return mixed
 */
function ep_request_memo(string $key, callable $compute) {
    $cache = cache::make_from_params(cache_store::MODE_REQUEST, 'mod_ep', 'memo');
    $key = $key . '_' . current_language();
    $value = $cache->get($key);
    if ($value === false) {
        $value = $compute();
        $cache->set($key, $value);
    }
    return $value;
}

/**
 * Libellé lisible d'une année d'étude.
 *
 * @param int $studyyear
 * @return string
 */
function ep_studyyear_label($studyyear) {
    $options = ep_studyyear_options();
    return $options[(int) $studyyear] ?? $options[0];
}

/**
 * Libellé lisible d'une plage d'années d'étude (année minimale - année maximale). Si les deux
 * bornes sont identiques ou que l'une d'elles n'est pas spécifiée, un libellé simple est renvoyé.
 *
 * @param int $minstudyyear
 * @param int $maxstudyyear
 * @return string
 */
function ep_studyyear_range_label($minstudyyear, $maxstudyyear) {
    $minstudyyear = (int) $minstudyyear;
    $maxstudyyear = (int) $maxstudyyear;
    if ($minstudyyear == $maxstudyyear || empty($minstudyyear) || empty($maxstudyyear)) {
        return ep_studyyear_label($minstudyyear ?: $maxstudyyear);
    }
    return ep_studyyear_label($minstudyyear) . ' - ' . ep_studyyear_label($maxstudyyear);
}

/**
 * Années d'étude qu'un étudiant peut choisir en rattachement d'un EP : l'année courante de
 * l'activité, la précédente (rattrapage) et la suivante (anticipation), comme dans mod_stage.
 * Tant que l'année courante n'est pas renseignée, toutes les années sont proposées.
 *
 * @param stdClass $ep
 * @return array int => libellé
 */
function ep_studyyear_selectable_options(stdClass $ep) {
    $currentyear = (int) $ep->currentstudyyear;
    if (empty($currentyear)) {
        return ep_studyyear_options();
    }
    $alloptions = ep_studyyear_options();
    $options = [];
    foreach ([$currentyear - 1, $currentyear, $currentyear + 1] as $year) {
        if (isset($alloptions[$year])) {
            $options[$year] = $alloptions[$year];
        }
    }
    return $options;
}

// Types d'enseignement personnalisé.

/**
 * Définition des six types d'enseignement personnalisé créés avec chaque instance, exprimée avec
 * les mêmes paramètres que ceux que la DEVE règle ensuite (voir types.php) : rien ne distingue un
 * type par défaut d'un type ajouté, sinon son code et son libellé initial.
 *
 * @return array code => paramètres (acquisition, validationflow, evidence, sortorder...)
 */
function ep_default_type_definitions() {
    $declared = [
        'acquisition' => EP_ACQ_DECLARATION,
        'validationflow' => EP_ROLE_REFERENT,
        'evidence' => EP_EVIDENCE_OPTIONAL,
    ];
    return [
        EP_TYPE_ACADEMIC => [
            'acquisition' => EP_ACQ_CATALOG,
            'validationflow' => EP_ROLE_OWNER,
            'evidence' => EP_EVIDENCE_NONE,
            'sortorder' => 10,
        ],
        EP_TYPE_STAGE => [
            'acquisition' => EP_ACQ_STAGE,
            'validationflow' => '',
            'evidence' => EP_EVIDENCE_NONE,
            'sortorder' => 20,
        ],
        EP_TYPE_ENGAGEMENT => $declared + ['sortorder' => 30],
        EP_TYPE_PROFESSIONAL => $declared + ['sortorder' => 40],
        EP_TYPE_SPORT => $declared + ['sortorder' => 50],
        EP_TYPE_EXTERNAL => $declared + ['sortorder' => 60],
    ];
}

/**
 * Crée les types manquants d'une instance. Appelée à la création de l'instance, et de nouveau à
 * l'ouverture de la page de paramétrage : une instance restaurée depuis une sauvegarde, ou créée
 * par une version antérieure, retrouve ainsi ses types sans intervention. Une instance qui a
 * déjà des types n'en reçoit aucun : la DEVE a pu en supprimer volontairement.
 *
 * @param int $epid
 * @return void
 */
function ep_create_default_types($epid) {
    global $DB;

    if ($DB->record_exists('ep_type', ['epid' => $epid])) {
        return;
    }
    foreach (ep_default_type_definitions() as $code => $definition) {
        $DB->insert_record('ep_type', ep_type_record($epid, $definition + [
            'code' => $code,
            'name' => get_string('type_' . $code, 'mod_ep'),
        ]));
    }
}

/**
 * Types d'une instance, dans l'ordre d'affichage choisi par la DEVE. Pendant un calcul de
 * promotion (voir mod_ep\local\progress_cache), la liste vient du cache.
 *
 * @param int $epid
 * @param bool $onlyenabled
 * @return array id => stdClass
 */
function ep_get_types($epid, $onlyenabled = false) {
    global $DB;

    $cached = \mod_ep\local\progress_cache::types((int) $epid, (bool) $onlyenabled);
    if ($cached !== null) {
        return $cached;
    }
    $conditions = ['epid' => $epid];
    if ($onlyenabled) {
        $conditions['enabled'] = 1;
    }
    return $DB->get_records('ep_type', $conditions, 'sortorder ASC, name ASC');
}

/**
 * Places occupées sur chaque EP du catalogue d'une instance, en une requête : les inscriptions
 * validées et celles encore en attente (voir ep_get_activity_taken_places()).
 *
 * @param int $epid
 * @return array activityid => int (absent = 0)
 */
function ep_get_activities_taken_places($epid) {
    global $DB;

    [$insql, $inparams] = $DB->get_in_or_equal([EP_STATUS_PENDING, EP_STATUS_VALIDATED], SQL_PARAMS_NAMED, 'st');
    $sql = "SELECT c.activityid, COUNT(1) AS taken
              FROM {ep_credit} c
             WHERE c.epid = :epid AND c.activityid IS NOT NULL AND c.status $insql
          GROUP BY c.activityid";
    $counts = [];
    foreach ($DB->get_records_sql($sql, ['epid' => $epid] + $inparams) as $row) {
        $counts[(int) $row->activityid] = (int) $row->taken;
    }
    return $counts;
}

/**
 * Nombre d'inscriptions de chaque EP du catalogue d'une instance, par statut, en une requête.
 *
 * @param int $epid
 * @return array activityid => [status => int]
 */
function ep_get_activities_status_counts($epid) {
    global $DB;

    $sql = "SELECT " . $DB->sql_concat('c.activityid', "'-'", 'c.status') . " AS k, c.activityid, c.status, COUNT(1) AS n
              FROM {ep_credit} c
             WHERE c.epid = :epid AND c.activityid IS NOT NULL
          GROUP BY c.activityid, c.status";
    $counts = [];
    foreach ($DB->get_records_sql($sql, ['epid' => $epid]) as $row) {
        $counts[(int) $row->activityid][(int) $row->status] = (int) $row->n;
    }
    return $counts;
}

/**
 * Responsables de tous les EP du catalogue d'une instance, en une requête.
 *
 * @param int $epid
 * @return array activityid => [userid => stdClass user]
 */
function ep_get_activities_teachers($epid) {
    global $DB;

    $sql = "SELECT at.id AS atid, at.activityid, u.*
              FROM {ep_activity_teacher} at
              JOIN {ep_activity} a ON a.id = at.activityid
              JOIN {user} u ON u.id = at.teacherid
             WHERE a.epid = :epid
          ORDER BY u.lastname ASC, u.firstname ASC";
    $teachers = [];
    foreach ($DB->get_records_sql($sql, ['epid' => $epid]) as $row) {
        $activityid = (int) $row->activityid;
        unset($row->atid, $row->activityid);
        $teachers[$activityid][(int) $row->id] = $row;
    }
    return $teachers;
}

/**
 * Inscriptions en cours (en attente ou validées) d'un étudiant sur les EP du catalogue d'une
 * instance, en une requête.
 *
 * @param int $epid
 * @param int $userid
 * @return array activityid => stdClass crédit
 */
function ep_get_user_active_registrations($epid, $userid) {
    global $DB;

    [$insql, $inparams] = $DB->get_in_or_equal([EP_STATUS_PENDING, EP_STATUS_VALIDATED], SQL_PARAMS_NAMED, 'st');
    $records = $DB->get_records_select(
        'ep_credit',
        "epid = :epid AND userid = :userid AND activityid IS NOT NULL AND status $insql",
        ['epid' => $epid, 'userid' => $userid] + $inparams,
        'timecreated DESC, id DESC'
    );
    $registrations = [];
    foreach ($records as $credit) {
        if (!isset($registrations[(int) $credit->activityid])) {
            $registrations[(int) $credit->activityid] = $credit;
        }
    }
    return $registrations;
}

/**
 * Type d'une instance repéré par son code.
 *
 * @param int $epid
 * @param string $code
 * @return stdClass|false
 */
function ep_get_type_by_code($epid, $code) {
    global $DB;

    return $DB->get_record('ep_type', ['epid' => $epid, 'code' => $code]);
}

/**
 * Types dans lesquels un étudiant peut déclarer lui-même un EP : ceux qui sont activés et qui
 * s'acquièrent par déclaration.
 *
 * @param int $epid
 * @return array id => stdClass
 */
function ep_get_declarable_types($epid) {
    return array_filter(ep_get_types($epid, true), 'ep_type_is_declarable');
}

/**
 * Types auxquels un EP du catalogue peut être rattaché : ceux qui sont activés et qui
 * s'acquièrent par inscription au catalogue.
 *
 * @param int $epid
 * @return array id => stdClass
 */
function ep_get_catalogable_types($epid) {
    return array_filter(ep_get_types($epid, true), 'ep_type_is_catalog');
}

/**
 * Libellé d'un type pour une liste déroulante : son nom, complété du plafond d'ECTS applicable,
 * qui est l'information dont l'étudiant a besoin au moment de choisir.
 *
 * @param stdClass $type
 * @return string
 */
function ep_type_option_label(stdClass $type) {
    $label = format_string($type->name);
    if ($type->maxects > 0) {
        $label .= ' (' . get_string('maxectsshort', 'mod_ep', ep_format_ects($type->maxects)) . ')';
    }
    return $label;
}

// Catalogue des EP internes et leurs responsables.

/**
 * EP du catalogue d'une instance.
 *
 * @param int $epid
 * @param bool $onlylisted Seulement ceux qui figurent au catalogue des étudiants (publiés ou clos).
 * @return array id => stdClass
 */
function ep_get_activities($epid, $onlylisted = false) {
    global $DB;

    if (!$onlylisted) {
        return $DB->get_records('ep_activity', ['epid' => $epid], 'sortorder ASC, name ASC');
    }
    [$insql, $params] = $DB->get_in_or_equal([EP_ACTIVITY_PUBLISHED, EP_ACTIVITY_CLOSED], SQL_PARAMS_NAMED, 'st');
    return $DB->get_records_select(
        'ep_activity',
        "epid = :epid AND state $insql",
        $params + ['epid' => $epid],
        'sortorder ASC, name ASC'
    );
}

/**
 * Indique si un code d'EP du catalogue est déjà pris dans une instance, par un autre EP que
 * celui donné.
 *
 * @param int $epid
 * @param string $code
 * @param int $excludeid
 * @return bool
 */
function ep_activity_code_used($epid, $code, $excludeid = 0) {
    global $DB;

    return $DB->record_exists_select(
        'ep_activity',
        'epid = :epid AND code = :code AND id <> :excludeid',
        ['epid' => $epid, 'code' => trim($code), 'excludeid' => (int) $excludeid]
    );
}

/**
 * Enregistre un EP du catalogue (création ou modification) avec ses prérequis, à partir des
 * données d'un formulaire ou d'un import. Les champs absents gardent leur valeur (modification)
 * ou prennent leur valeur par défaut (création).
 *
 * @param stdClass $ep
 * @param stdClass $data Champs de ep_activity, plus 'prerequisites' (identifiants d'EP) ; id
 *                       présent et non nul pour une modification.
 * @return int Identifiant de l'EP.
 */
function ep_save_activity(stdClass $ep, stdClass $data) {
    global $DB;

    $existing = !empty($data->id) ? $DB->get_record('ep_activity', ['id' => $data->id, 'epid' => $ep->id], '*', MUST_EXIST) : null;
    $now = time();
    $defaults = [
        'epid' => $ep->id, 'typeid' => 0, 'name' => '', 'description' => '', 'ects' => 0,
        'minstudyyear' => 0, 'maxstudyyear' => 0, 'capacity' => 0, 'code' => '', 'state' => EP_ACTIVITY_PUBLISHED,
        'registrationstart' => 0, 'registrationend' => 0, 'datestart' => 0, 'dateend' => 0,
        'grading' => '', 'grademax' => 20, 'gradepass' => 10, 'minects' => 0, 'prereqblocking' => 1,
        'sortorder' => 0, 'timecreated' => $now,
    ];
    $record = (object) array_merge($defaults, $existing ? (array) $existing : [], array_intersect_key((array) $data, $defaults));
    $record->epid = $ep->id;
    $record->name = trim((string) $record->name);
    $record->code = core_text::substr(trim((string) $record->code), 0, 30);
    $record->ects = round((float) $record->ects, 2);
    $record->capacity = max(0, (int) $record->capacity);
    $record->state = array_key_exists($record->state, ep_activity_state_options()) ? $record->state : EP_ACTIVITY_PUBLISHED;
    $intfields = ['registrationstart', 'registrationend', 'datestart', 'dateend', 'minstudyyear', 'maxstudyyear', 'sortorder'];
    foreach ($intfields as $field) {
        $record->$field = (int) $record->$field;
    }
    $record->grading = in_array($record->grading, [EP_GRADING_NONE, EP_GRADING_GRADE], true) ? $record->grading : '';
    $record->grademax = max(1, round((float) $record->grademax, 2));
    $record->gradepass = min($record->grademax, max(0, round((float) $record->gradepass, 2)));
    $record->minects = max(0, round((float) $record->minects, 2));
    $record->prereqblocking = empty($record->prereqblocking) ? 0 : 1;
    $record->timemodified = $now;

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('ep_activity', $record);
    } else {
        unset($record->id);
        $record->id = $DB->insert_record('ep_activity', $record);
    }
    if (property_exists($data, 'prerequisites')) {
        ep_set_activity_prerequisites($record, (array) $data->prerequisites);
    }
    if ($existing) {
        ep_sync_syllabus_details($record);
    }
    return (int) $record->id;
}

/**
 * Enseignants responsables d'un EP du catalogue.
 *
 * @param int $activityid
 * @return array userid => stdClass user
 */
function ep_get_activity_teachers($activityid) {
    global $DB;

    $sql = "SELECT u.*
              FROM {ep_activity_teacher} at
              JOIN {user} u ON u.id = at.teacherid
             WHERE at.activityid = :activityid
          ORDER BY u.lastname ASC, u.firstname ASC";

    return $DB->get_records_sql($sql, ['activityid' => $activityid]);
}

/**
 * Remplace la liste des responsables d'un EP du catalogue.
 *
 * @param int $activityid
 * @param array $teacherids
 * @return void
 */
function ep_set_activity_teachers($activityid, array $teacherids) {
    global $DB;

    $teacherids = array_filter(array_unique(array_map('intval', $teacherids)));
    $existing = $DB->get_records_menu('ep_activity_teacher', ['activityid' => $activityid], '', 'teacherid, id');

    // Ne réécrit que la différence : conserve la date d'affectation des responsables inchangés et
    // évite un delete + N inserts quand la DEVE réenregistre la page sans avoir rien modifié.
    foreach (array_diff(array_keys($existing), $teacherids) as $removed) {
        $DB->delete_records('ep_activity_teacher', ['id' => $existing[$removed]]);
    }
    foreach (array_diff($teacherids, array_keys($existing)) as $added) {
        $DB->insert_record('ep_activity_teacher', (object) [
            'activityid' => $activityid,
            'teacherid' => $added,
            'timecreated' => time(),
        ]);
    }
    // Les responsables de l'EP sont ceux de sa fiche.
    ep_sync_syllabus_teachers((object) ['id' => $activityid]);
}

/**
 * Indique si un utilisateur est responsable d'un EP du catalogue donné.
 *
 * @param int $activityid
 * @param int $userid
 * @return bool
 */
function ep_is_activity_teacher($activityid, $userid) {
    global $DB;

    return $DB->record_exists('ep_activity_teacher', ['activityid' => $activityid, 'teacherid' => $userid]);
}

/**
 * EP du catalogue dont un utilisateur est responsable, dans une instance donnée.
 *
 * @param int $epid
 * @param int $userid
 * @return array id => stdClass
 */
function ep_get_responsible_activities($epid, $userid) {
    global $DB;

    $sql = "SELECT a.*
              FROM {ep_activity} a
              JOIN {ep_activity_teacher} at ON at.activityid = a.id
             WHERE a.epid = :epid AND at.teacherid = :userid
          ORDER BY a.sortorder ASC, a.name ASC";

    return $DB->get_records_sql($sql, ['epid' => $epid, 'userid' => $userid]);
}

/**
 * Nombre de places occupées sur un EP du catalogue : les inscriptions validées et celles encore
 * en attente. Les inscriptions en attente sont comptées à dessein — ouvrir plus de places que ce
 * que le responsable pourra valider reviendrait à promettre une place qui n'existe pas.
 *
 * @param int $activityid
 * @return int
 */
function ep_get_activity_taken_places($activityid) {
    global $DB;

    [$insql, $inparams] = $DB->get_in_or_equal([EP_STATUS_PENDING, EP_STATUS_VALIDATED], SQL_PARAMS_NAMED, 'st');
    return $DB->count_records_select(
        'ep_credit',
        "activityid = :activityid AND status $insql",
        ['activityid' => $activityid] + $inparams
    );
}

/**
 * Places restantes sur un EP du catalogue, ou null s'il n'a pas de limite.
 *
 * @param stdClass $activity
 * @return int|null
 */
function ep_get_activity_remaining_places(stdClass $activity) {
    if (empty($activity->capacity)) {
        return null;
    }
    return max(0, (int) $activity->capacity - ep_get_activity_taken_places($activity->id));
}

/**
 * Indique si un EP du catalogue est ouvert à une année d'étude donnée (0 = année non renseignée,
 * auquel cas aucune restriction n'est appliquée).
 *
 * @param stdClass $activity
 * @param int $studyyear
 * @return bool
 */
function ep_activity_open_to_year(stdClass $activity, $studyyear) {
    $studyyear = (int) $studyyear;
    if (empty($studyyear)) {
        return true;
    }
    if (!empty($activity->minstudyyear) && $studyyear < $activity->minstudyyear) {
        return false;
    }
    if (!empty($activity->maxstudyyear) && $studyyear > $activity->maxstudyyear) {
        return false;
    }
    return true;
}

/**
 * Inscription en cours d'un étudiant sur un EP du catalogue, s'il en a une qui compte encore
 * (en attente ou validée). Une inscription annulée ou refusée n'en est pas une : elle n'empêche
 * pas de se réinscrire.
 *
 * @param int $activityid
 * @param int $userid
 * @return stdClass|false
 */
function ep_get_active_registration($activityid, $userid) {
    global $DB;

    [$insql, $inparams] = $DB->get_in_or_equal([EP_STATUS_PENDING, EP_STATUS_VALIDATED], SQL_PARAMS_NAMED, 'st');
    $records = $DB->get_records_select(
        'ep_credit',
        "activityid = :activityid AND userid = :userid AND status $insql",
        ['activityid' => $activityid, 'userid' => $userid] + $inparams,
        'timecreated DESC',
        '*',
        0,
        1
    );

    return $records ? reset($records) : false;
}

// Minimums d'ECTS par année d'étude.

/**
 * Minimums d'ECTS définis par année d'étude pour une instance. Pendant un calcul de promotion
 * (voir mod_ep\local\progress_cache), la liste vient du cache.
 *
 * @param int $epid
 * @return array studyyear => float
 */
function ep_get_year_requirements($epid) {
    global $DB;

    $cached = \mod_ep\local\progress_cache::requirements((int) $epid);
    if ($cached !== null) {
        return $cached;
    }

    $records = $DB->get_records('ep_year_requirement', ['epid' => $epid], 'studyyear ASC');

    $requirements = [];
    foreach ($records as $record) {
        $requirements[(int) $record->studyyear] = (float) $record->requiredects;
    }
    return $requirements;
}

/**
 * Minimum d'ECTS requis pour une année d'étude donnée (0 = aucune obligation cette année-là).
 *
 * @param int $epid
 * @param int $studyyear
 * @return float
 */
function ep_get_year_requirement($epid, $studyyear) {
    global $DB;

    $value = $DB->get_field(
        'ep_year_requirement',
        'requiredects',
        ['epid' => $epid, 'studyyear' => (int) $studyyear]
    );

    return $value === false ? 0.0 : (float) $value;
}

/**
 * Définit le minimum d'ECTS requis pour une année d'étude. Une valeur nulle supprime la ligne
 * plutôt que d'enregistrer un zéro : une année sans obligation ne doit pas apparaître comme un
 * objectif à part entière dans les bilans.
 *
 * @param int $epid
 * @param int $studyyear
 * @param float $requiredects
 * @return void
 */
function ep_set_year_requirement($epid, $studyyear, $requiredects) {
    global $DB;

    $studyyear = (int) $studyyear;
    $requiredects = round((float) $requiredects, 2);
    $existing = $DB->get_record('ep_year_requirement', ['epid' => $epid, 'studyyear' => $studyyear]);

    if ($requiredects <= 0) {
        if ($existing) {
            $DB->delete_records('ep_year_requirement', ['id' => $existing->id]);
        }
        return;
    }

    if ($existing) {
        $existing->requiredects = $requiredects;
        $existing->timemodified = time();
        $DB->update_record('ep_year_requirement', $existing);
    } else {
        $DB->insert_record('ep_year_requirement', (object) [
            'epid' => $epid,
            'studyyear' => $studyyear,
            'requiredects' => $requiredects,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}

// Crédits : création, inscription, validation.

/**
 * Crédits d'un étudiant dans une instance, du plus récent au plus ancien. Pendant un calcul de
 * promotion (voir mod_ep\local\progress_cache), la liste vient du cache.
 *
 * @param int $epid
 * @param int $userid
 * @return array id => stdClass
 */
function ep_get_student_credits($epid, $userid) {
    global $DB;

    $cached = \mod_ep\local\progress_cache::credits($epid, $userid);
    if ($cached !== null) {
        return $cached;
    }
    return $DB->get_records('ep_credit', ['epid' => $epid, 'userid' => $userid], 'timecreated DESC');
}

/**
 * Crée un crédit. Un crédit dont le type n'a aucune étape de validation naît validé, sans
 * validateur (sauf si la DEVE le saisit elle-même) ; les autres naissent en attente de la
 * première étape du circuit.
 *
 * @param stdClass $ep
 * @param int $userid
 * @param stdClass $type
 * @param array $data name, description, claimedects, studyyear, activityid, source, sourceref,
 *                    validatedby (saisie DEVE), grade.
 * @return int Identifiant du crédit créé.
 */
function ep_create_credit(stdClass $ep, $userid, stdClass $type, array $data) {
    global $DB;

    $now = time();
    $claimed = round((float) ($data['claimedects'] ?? 0), 2);
    $steps = ep_flow_steps($type->validationflow);
    $auto = empty($steps);
    $source = (string) ($data['source'] ?? EP_SOURCE_STUDENT);

    $record = (object) [
        'epid' => $ep->id,
        'userid' => $userid,
        'typeid' => $type->id,
        'activityid' => !empty($data['activityid']) ? (int) $data['activityid'] : null,
        'studyyear' => (int) ($data['studyyear'] ?? 0),
        'name' => (string) ($data['name'] ?? ''),
        'description' => (string) ($data['description'] ?? ''),
        'claimedects' => $claimed,
        'retainedects' => $auto ? $claimed : 0,
        'status' => $auto ? EP_STATUS_VALIDATED : EP_STATUS_PENDING,
        'step' => 0,
        'pendingrole' => $auto ? '' : $steps[0],
        'grade' => isset($data['grade']) ? round((float) $data['grade'], 2) : null,
        'source' => $source,
        'sourceref' => (int) ($data['sourceref'] ?? 0),
        'validatedby' => $auto && !empty($data['validatedby']) ? (int) $data['validatedby'] : null,
        'addedby' => !empty($data['addedby']) ? (int) $data['addedby'] : null,
        'validatetime' => $auto ? $now : null,
        'validatorcomment' => null,
        'timecreated' => $now,
        'timemodified' => $now,
    ];

    $record->id = $DB->insert_record('ep_credit', $record);

    if ($source === EP_SOURCE_STAGE) {
        $origin = 'stage';
    } else if ($source === EP_SOURCE_DEVE) {
        $origin = 'deve';
    } else {
        $origin = $record->activityid ? 'registration' : 'declaration';
    }
    ep_trigger_credit_event(\mod_ep\event\credit_created::class, $record, ['origin' => $origin]);

    return $record->id;
}

/**
 * Inscrit un étudiant à un EP du catalogue. Le nombre d'ECTS demandé est celui de l'EP : il n'est
 * pas au choix de l'étudiant, c'est l'EP qui le porte.
 *
 * Les places restantes et l'absence d'inscription en cours sont revérifiées sous le verrou de
 * l'activité : deux étudiants qui confirment ensemble sur la dernière place ne sont plus inscrits
 * tous les deux, et un double envoi du formulaire ne crée plus deux inscriptions.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param int $userid
 * @param int $studyyear
 * @return stdClass {creditid: int, problem: string|null} L'identifiant du crédit créé, ou la clé
 *                  du message qui explique pourquoi l'inscription a été refusée.
 */
function ep_register_to_activity(stdClass $ep, stdClass $activity, $userid, $studyyear) {
    global $DB;

    $result = (object) ['creditid' => 0, 'problem' => null];
    $lock = ep_get_activity_lock($ep->id);
    try {
        // L'EP et son type sont relus sous le verrou : la DEVE a pu fermer l'EP ou réduire ses
        // places pendant que l'étudiant confirmait.
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id, 'epid' => $ep->id], '*', MUST_EXIST);
        $type = $DB->get_record('ep_type', ['id' => $activity->typeid], '*', MUST_EXIST);
        $result->problem = ep_registration_problem($activity, $type, $userid, $studyyear, $ep);
        if ($result->problem === null) {
            $result->creditid = ep_create_credit($ep, $userid, $type, [
                'activityid' => $activity->id,
                'studyyear' => $studyyear,
                'name' => $activity->name,
                'description' => '',
                'claimedects' => $activity->ects,
                'source' => EP_SOURCE_STUDENT,
            ]);
        }
    } finally {
        $lock->release();
    }
    return $result;
}

/**
 * Valide un crédit à l'étape courante de son circuit. Le responsable de l'EP ou l'enseignant
 * référent ne peuvent valider qu'à leur étape ; leur validation fait passer le crédit à l'étape
 * suivante, ou le valide s'il n'y en a pas. La DEVE valide à n'importe quelle étape, et sa
 * validation est toujours finale.
 *
 * Les plafonds par type ne sont pas appliqués ici : ce qui est validé reste acquis, c'est au
 * décompte (voir ep_get_student_progress()) de ne compter que ce que le plafond autorise, de
 * façon à ce qu'un relèvement ultérieur du plafond fasse ressortir des ECTS déjà validés sans
 * avoir à les revalider un par un.
 *
 * La décision n'est écrite que si le crédit est toujours en attente, à l'étape attendue (voir
 * ep_update_credit_if()) : un refus, un retrait ou une décision enregistrés entre-temps par un
 * autre intervenant ne sont pas écrasés.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param float $retainedects
 * @param string $comment
 * @param string|null $role Rôle de l'auteur (owner, referent, deve) ; par défaut, le rôle attendu
 *                          à l'étape courante.
 * @param float|null $grade Note attribuée, pour un crédit noté.
 * @return bool False si le crédit n'était plus en attente à cette étape : rien n'a été écrit.
 */
function ep_validate_credit(stdClass $credit, $byuserid, $retainedects, $comment = '', $role = null, $grade = null) {
    $role = $role ?: ($credit->pendingrole ?: EP_ROLE_DEVE);
    $decidedstep = (int) $credit->step;
    $type = ep_get_credit_type($credit);
    $steps = $type ? ep_flow_steps($type->validationflow) : [];
    $next = (int) $credit->step + 1;
    $final = $role === EP_ROLE_DEVE || $next >= count($steps);

    $credit->retainedects = max(0, round((float) $retainedects, 2));
    // Un responsable ou un référent ne retient pas plus que ce qui est demandé ; seule la DEVE
    // peut aller au-delà (correction d'une demande mal saisie).
    if ($role !== EP_ROLE_DEVE) {
        $credit->retainedects = min($credit->retainedects, round((float) $credit->claimedects, 2));
    }
    $credit->timemodified = time();
    if ($grade !== null) {
        $credit->grade = round((float) $grade, 2);
    }
    if ($final) {
        $credit->status = EP_STATUS_VALIDATED;
        $credit->step = max($next, count($steps));
        $credit->pendingrole = '';
        $credit->validatedby = $byuserid;
        $credit->validatetime = time();
        $credit->validatorcomment = $comment;
    } else {
        $credit->step = $next;
        $credit->pendingrole = $steps[$next];
    }

    $where = 'status = :pending AND source <> :stage';
    $params = ['pending' => EP_STATUS_PENDING, 'stage' => EP_SOURCE_STAGE];
    if ($role !== EP_ROLE_DEVE) {
        $where .= ' AND pendingrole = :role';
        $params['role'] = $role;
    }
    $applied = ep_update_credit_if(
        $credit,
        [
            'status', 'step', 'pendingrole', 'grade', 'retainedects', 'validatedby', 'validatetime',
            'validatorcomment', 'timemodified',
        ],
        $where,
        $params
    );
    if ($applied) {
        // L'étape décidée est celle d'avant l'écriture (le crédit porte désormais la suivante).
        $decision = clone $credit;
        $decision->step = $decidedstep;
        ep_add_credit_decision($decision, $role, 'validated', $byuserid, $grade, $credit->retainedects, $comment);
        if ($final) {
            ep_notify_credit_decided($credit);
        }
        ep_trigger_credit_event(\mod_ep\event\credit_validated::class, $credit, [
            'retainedects' => (float) $credit->retainedects,
            'role' => $role,
            'final' => $final,
            'step' => $decision->step,
        ]);
    }
    return $applied;
}

/**
 * Refuse un crédit, avec le motif communiqué à l'étudiant. Comme la validation, le refus n'est
 * écrit que si le crédit est toujours en attente à l'étape attendue ; un refus est toujours
 * final, quelle que soit l'étape.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param string $comment
 * @param string|null $role Rôle de l'auteur ; par défaut, le rôle attendu à l'étape courante.
 * @param float|null $grade Note attribuée, pour un crédit noté non validé.
 * @return bool False si le crédit n'était plus en attente : rien n'a été écrit.
 */
function ep_reject_credit(stdClass $credit, $byuserid, $comment, $role = null, $grade = null) {
    $role = $role ?: ($credit->pendingrole ?: EP_ROLE_DEVE);
    // L'ancien appel passait 'teacher' : c'est le rôle attendu à l'étape qui est retenu.
    if ($role === 'teacher') {
        $role = $credit->pendingrole ?: EP_ROLE_REFERENT;
    }
    $step = (int) $credit->step;

    $credit->status = EP_STATUS_REJECTED;
    $credit->pendingrole = '';
    $credit->retainedects = 0;
    if ($grade !== null) {
        $credit->grade = round((float) $grade, 2);
    }
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    $where = 'status = :pending AND source <> :stage';
    $params = ['pending' => EP_STATUS_PENDING, 'stage' => EP_SOURCE_STAGE];
    if ($role !== EP_ROLE_DEVE) {
        $where .= ' AND pendingrole = :role';
        $params['role'] = $role;
    }
    $applied = ep_update_credit_if(
        $credit,
        ['status', 'pendingrole', 'grade', 'retainedects', 'validatedby', 'validatetime', 'validatorcomment', 'timemodified'],
        $where,
        $params
    );
    if ($applied) {
        $decision = clone $credit;
        $decision->step = $step;
        ep_add_credit_decision($decision, $role, 'rejected', $byuserid, $grade, 0, $comment);
        ep_notify_credit_decided($credit);
        ep_trigger_credit_event(\mod_ep\event\credit_rejected::class, $credit, ['role' => $role]);
    }
    return $applied;
}

/**
 * Annule un crédit : désinscription de l'étudiant tant que son inscription n'est pas validée, ou
 * retrait par la DEVE. La ligne est conservée (état terminal) plutôt que supprimée, pour garder
 * la trace de ce qui a été demandé, et sa place sur l'EP du catalogue est libérée.
 *
 * L'étudiant (ou le responsable de l'EP, pour un participant) ne peut retirer qu'une demande
 * encore en attente ; la DEVE peut retirer tout crédit qui n'est pas déjà annulé. Dans les deux
 * cas, un crédit attribué automatiquement est hors de portée : il suit son stage d'origine.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param string $comment
 * @param string $role 'student', 'owner' ou 'deve'.
 * @return bool False si le crédit n'était plus dans l'état attendu : rien n'a été écrit.
 */
function ep_cancel_credit(stdClass $credit, $byuserid, $comment = '', $role = EP_ROLE_DEVE) {
    $credit->status = EP_STATUS_CANCELLED;
    $credit->pendingrole = '';
    $credit->retainedects = 0;
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    if ($role === EP_ROLE_STUDENT) {
        // L'étudiant retire sa demande tant que rien n'est tranché. Revenir sur une décision
        // appartient à la DEVE.
        $where = 'status = :pending AND source <> :stage';
        $params = ['pending' => EP_STATUS_PENDING, 'stage' => EP_SOURCE_STAGE];
    } else if ($role === EP_ROLE_OWNER) {
        // Le responsable retire un participant de son EP tant que l'inscription attend sa propre
        // décision : une fois son étape franchie, elle appartient à l'étape suivante.
        $where = 'status = :pending AND pendingrole = :owner AND source <> :stage';
        $params = ['pending' => EP_STATUS_PENDING, 'owner' => EP_ROLE_OWNER, 'stage' => EP_SOURCE_STAGE];
    } else {
        $where = 'status <> :cancelled AND source <> :stage';
        $params = ['cancelled' => EP_STATUS_CANCELLED, 'stage' => EP_SOURCE_STAGE];
    }
    $applied = ep_update_credit_if(
        $credit,
        ['status', 'pendingrole', 'retainedects', 'validatedby', 'validatetime', 'validatorcomment', 'timemodified'],
        $where,
        $params
    );
    if ($applied) {
        ep_add_credit_decision($credit, $role, 'cancelled', $byuserid, null, 0, $comment);
        ep_trigger_credit_event(\mod_ep\event\credit_cancelled::class, $credit, ['role' => $role]);
    }
    return $applied;
}

/**
 * Verrou d'écriture d'une instance, à prendre avant toute écriture en lot ou toute écriture dont
 * la validité dépend d'une lecture qui précède (places restantes avant une inscription,
 * crédits existants avant une synchronisation). Le verrou est rendu par l'appelant
 * (`$lock->release()`), dans un bloc finally.
 *
 * @param int $epid
 * @return \core\lock\lock
 */
function ep_get_activity_lock($epid) {
    global $PAGE;

    $factory = \core\lock\lock_config::get_lock_factory('mod_ep_activity');
    $lock = $factory->get_lock('ep-' . (int) $epid, 30);
    if (!$lock) {
        // La page d'erreur propose de revenir à la page en cours, pour réessayer.
        throw new moodle_exception('erroractivitybusy', 'mod_ep', $PAGE->has_set_url() ? $PAGE->url : '');
    }
    return $lock;
}

/**
 * Supprime un EP du catalogue et tout ce qui s'y rattache (responsables, prérequis dans les deux
 * sens, sa fiche), sous le verrou de l'activité : refusé dès qu'un
 * crédit s'y rattache, vérification faite sous ce verrou pour qu'une inscription simultanée ne
 * se retrouve pas sans EP.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @return bool False si un crédit s'y rattache.
 */
function ep_delete_activity(stdClass $ep, stdClass $activity) {
    global $DB;

    $lock = ep_get_activity_lock($ep->id);
    try {
        if ($DB->record_exists('ep_credit', ['activityid' => $activity->id])) {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('ep_activity_teacher', ['activityid' => $activity->id]);
        $DB->delete_records_select(
            'ep_activity_prereq',
            'activityid = :a OR requiredid = :r',
            ['a' => $activity->id, 'r' => $activity->id]
        );
        $syllabusids = $DB->get_fieldset_select('ep_syllabus', 'id', 'activityid = ?', [$activity->id]);
        if ($syllabusids) {
            [$insql, $params] = $DB->get_in_or_equal($syllabusids);
            $DB->delete_records_select('ep_session', "syllabusid $insql", $params);
        }
        $DB->delete_records('ep_syllabus', ['activityid' => $activity->id]);
        $DB->delete_records('ep_activity', ['id' => $activity->id, 'epid' => $ep->id]);
        $transaction->allow_commit();
        // Sa fiche (activité « Syllabus du module ») part avec lui.
        $syllabus = ep_activity_syllabus_record($activity->id);
        if ($syllabus) {
            global $CFG;
            require_once($CFG->dirroot . '/course/lib.php');
            course_delete_module($syllabus->cmid);
        }
        return true;
    } finally {
        $lock->release();
    }
}

/**
 * Module de cours d'une instance, lu une fois par requête : les événements et les notifications
 * en ont besoin à chaque écriture, et une synchronisation en déclenche un par crédit.
 *
 * @param int $epid
 * @return stdClass|null
 */
function ep_get_cm_of_instance($epid) {
    // Cache de requête MUC plutôt qu'une variable statique : il est vidé entre deux tests, alors
    // que les identifiants d'instance y sont réutilisés d'un test à l'autre.
    $cache = cache::make_from_params(cache_store::MODE_REQUEST, 'mod_ep', 'cm');
    $epid = (int) $epid;
    $cm = $cache->get($epid);
    if ($cm === false) {
        $cm = get_coursemodule_from_instance('ep', $epid, 0, false, IGNORE_MISSING) ?: null;
        $cache->set($epid, $cm);
    }
    return $cm;
}

/**
 * Déclenche un événement du circuit d'un crédit (voir mod_ep\event\credit_event_base), une fois la
 * base mise à jour. Les journaux Moodle gardent ainsi qui a fait quoi et quand sur chaque EP.
 *
 * @param string $eventclass Classe de l'événement.
 * @param stdClass|int $credit Crédit (enregistrement complet) ou son identifiant.
 * @param array $other Données propres à l'événement, complétées du statut, du type et de l'EP.
 * @return void
 */
function ep_trigger_credit_event($eventclass, $credit, array $other = []) {
    global $DB;

    if (!is_object($credit)) {
        $credit = $DB->get_record('ep_credit', ['id' => (int) $credit], '*', MUST_EXIST);
    }
    $cm = ep_get_cm_of_instance($credit->epid);
    if (!$cm) {
        return;
    }
    $event = $eventclass::create([
        'objectid' => $credit->id,
        'context' => context_module::instance($cm->id),
        'relateduserid' => $credit->userid,
        'other' => $other + [
            'status' => (int) $credit->status,
            'typeid' => (int) $credit->typeid,
            'activityid' => (int) ($credit->activityid ?? 0),
        ],
    ]);
    $event->trigger();
}

/**
 * Écrit des champs d'un crédit à condition qu'il soit encore dans l'état attendu.
 *
 * Les pages lisent le crédit au début de la requête et en déduisent ce qui est permis (valider,
 * refuser, retirer). Écrire ensuite sans condition laissait une décision prise entre-temps par un
 * autre intervenant (la DEVE et le responsable de l'EP, l'étudiant qui retire sa demande) être
 * écrasée par un statut calculé sur une lecture périmée.
 *
 * La ligne est d'abord verrouillée par une mise à jour sans effet, jusqu'à la fin de la
 * transaction : toute autre écriture du crédit attend. La condition est ensuite vérifiée sur une
 * relecture, qui voit donc la dernière écriture validée, et les champs ne sont écrits que si elle
 * est remplie. Rien n'est écrit sinon : la transaction englobante, s'il y en a une, peut continuer.
 *
 * @param stdClass $credit Crédit, avec les nouvelles valeurs des champs à écrire.
 * @param string[] $fields Champs à écrire (un champ absent de l'objet n'est pas écrit).
 * @param string $where Condition SQL sur la ligne de ep_credit, paramètres nommés.
 * @param array $whereparams Paramètres de la condition.
 * @return bool False si le crédit n'était plus dans l'état attendu : rien n'a été écrit.
 */
function ep_update_credit_if(stdClass $credit, array $fields, $where, array $whereparams) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $DB->execute('UPDATE {ep_credit} SET timemodified = timemodified WHERE id = ?', [$credit->id]);
    $applied = $DB->record_exists_select(
        'ep_credit',
        "id = :creditid AND ($where)",
        ['creditid' => $credit->id] + $whereparams
    );
    if ($applied) {
        $update = (object) ['id' => $credit->id];
        foreach ($fields as $field) {
            if (property_exists($credit, $field)) {
                $update->$field = $credit->$field;
            }
        }
        $DB->update_record('ep_credit', $update);
    }
    $transaction->allow_commit();
    return $applied;
}

/**
 * Ce qui empêche un étudiant de s'inscrire lui-même à un EP du catalogue, ou null s'il le peut :
 * EP non publié ou hors période, type désactivé, inscription déjà en cours, plus de place, année
 * d'étude hors de la plage, prérequis bloquants non remplis. Chaque condition est revérifiée à
 * l'inscription elle-même, sous le verrou de l'activité (voir ep_register_to_activity()) : la
 * page a pu être affichée avant qu'une place ne soit prise.
 *
 * @param stdClass $activity
 * @param stdClass|null $type Type de l'EP.
 * @param int $userid
 * @param int|null $studyyear Année de rattachement choisie ; null pour ne pas la contrôler.
 * @param stdClass|null $ep Instance, pour contrôler les prérequis ; null pour ne pas les contrôler.
 * @return string|null Clé du message d'erreur, ou null.
 */
function ep_registration_problem(stdClass $activity, $type, $userid, $studyyear = null, $ep = null) {
    if (!ep_activity_registration_open($activity) || !$type || empty($type->enabled)) {
        return 'errorregisterclosed';
    }
    if (ep_get_active_registration($activity->id, $userid)) {
        return 'erroralreadyregistered';
    }
    $remaining = ep_get_activity_remaining_places($activity);
    if ($remaining !== null && $remaining <= 0) {
        return 'errornoplaceleft';
    }
    if ($studyyear !== null && !ep_activity_open_to_year($activity, $studyyear)) {
        return 'errorwrongyear';
    }
    if ($ep && !empty($activity->prereqblocking) && ep_get_missing_prerequisites($ep, $activity, $userid)) {
        return 'errorprerequisites';
    }
    return null;
}

/**
 * Enregistre les modifications apportées par l'étudiant à une déclaration encore en attente
 * (type, intitulé, description, année, ECTS demandés), l'étape en attente suivant le circuit du
 * type. Rien n'est écrit si la demande a été tranchée entre-temps.
 *
 * @param stdClass $credit Crédit, avec les nouvelles valeurs.
 * @return bool False si la demande n'était plus en attente.
 */
function ep_update_declaration(stdClass $credit) {
    global $DB;

    $credit->timemodified = time();
    // L'étape en attente suit le circuit du type choisi : la garder après un changement de type
    // laisserait statuer le référent sur un type que seule la DEVE valide.
    $flow = $DB->get_field('ep_type', 'validationflow', ['id' => $credit->typeid, 'epid' => $credit->epid], MUST_EXIST);
    $credit->pendingrole = ep_flow_steps($flow)[0] ?? EP_ROLE_DEVE;
    $applied = ep_update_credit_if(
        $credit,
        ['typeid', 'studyyear', 'name', 'description', 'claimedects', 'pendingrole', 'timemodified'],
        // Modifiable seulement avant toute décision : une demande déjà validée à une première étape
        // (par le référent, avant la DEVE) ne doit plus changer sous les yeux de l'étape suivante.
        'status = :pending AND step = 0 AND source = :student AND activityid IS NULL',
        ['pending' => EP_STATUS_PENDING, 'student' => EP_SOURCE_STUDENT]
    );
    if ($applied) {
        ep_trigger_credit_event(\mod_ep\event\credit_updated::class, $credit);
    }
    return $applied;
}

/**
 * Type d'un crédit, lu dans le cache de promotion s'il est armé, sinon en base.
 *
 * @param stdClass $credit
 * @return stdClass|false
 */
function ep_get_credit_type(stdClass $credit) {
    global $DB;

    $types = \mod_ep\local\progress_cache::types((int) $credit->epid);
    if ($types !== null && isset($types[$credit->typeid])) {
        return $types[$credit->typeid];
    }
    return $DB->get_record('ep_type', ['id' => $credit->typeid]);
}

/**
 * Barème applicable à un crédit : celui de son EP du catalogue s'il en fixe un, sinon celui de
 * son type.
 *
 * @param stdClass $credit
 * @param stdClass|null $type Type du crédit (lu si absent).
 * @return stdClass {grading, grademax, gradepass}
 */
function ep_credit_grading(stdClass $credit, $type = null) {
    global $DB;

    $type = $type ?: ep_get_credit_type($credit);
    if (!empty($credit->activityid)) {
        $activity = $DB->get_record('ep_activity', ['id' => $credit->activityid]);
        if ($activity) {
            return ep_activity_grading($activity, $type ?: null);
        }
    }
    return (object) [
        'grading' => $type ? $type->grading : EP_GRADING_NONE,
        'grademax' => $type ? (float) $type->grademax : 20.0,
        'gradepass' => $type ? (float) $type->gradepass : 10.0,
    ];
}

/**
 * Enregistre une décision dans l'historique d'un crédit (une ligne par étape du circuit).
 *
 * @param stdClass $credit
 * @param string $role Rôle de l'auteur (owner, referent, deve, student).
 * @param string $decision validated, rejected ou cancelled.
 * @param int|null $userid
 * @param float|null $grade
 * @param float $retainedects
 * @param string $comment
 * @return int Identifiant de la décision.
 */
function ep_add_credit_decision(stdClass $credit, $role, $decision, $userid, $grade, $retainedects, $comment) {
    global $DB;

    return $DB->insert_record('ep_credit_decision', (object) [
        'creditid' => $credit->id,
        'step' => (int) $credit->step,
        'role' => $role,
        'decision' => $decision,
        'userid' => $userid ?: null,
        'grade' => $grade === null ? null : round((float) $grade, 2),
        'retainedects' => round((float) $retainedects, 2),
        'comment' => (string) $comment,
        'timecreated' => time(),
    ]);
}

/**
 * Historique des décisions d'un crédit, de la plus ancienne à la plus récente.
 *
 * @param int $creditid
 * @return array id => stdClass
 */
function ep_get_credit_decisions($creditid) {
    global $DB;

    return $DB->get_records('ep_credit_decision', ['creditid' => $creditid], 'timecreated ASC, id ASC');
}

/**
 * Note un crédit : la note décide de la validation, selon le barème applicable (celui de l'EP du
 * catalogue, sinon celui du type). Une note au moins égale au seuil valide le crédit pour les
 * ECTS demandés ; en dessous, le crédit est non validé, la note étant conservée dans les deux cas.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param float $grade
 * @param string $comment
 * @param string|null $role Voir ep_validate_credit().
 * @return bool False si le crédit n'était plus en attente à cette étape, ou si la note est hors
 *              barème.
 */
function ep_grade_credit(stdClass $credit, $byuserid, $grade, $comment = '', $role = null) {
    $grading = ep_credit_grading($credit);
    $grade = round((float) $grade, 2);
    if ($grade < 0 || $grade > $grading->grademax) {
        return false;
    }
    if ($grade >= $grading->gradepass) {
        return ep_validate_credit($credit, $byuserid, $credit->claimedects, $comment, $role, $grade);
    }
    return ep_reject_credit($credit, $byuserid, $comment, $role, $grade);
}

/**
 * Rôle sous lequel un utilisateur statue sur un crédit : la DEVE si elle en a la capacité, sinon
 * le rôle attendu à l'étape courante (qu'il détient, voir ep_rights_can_validate()).
 *
 * @param stdClass $rights
 * @param stdClass $credit
 * @return string
 */
function ep_rights_deciding_role(stdClass $rights, stdClass $credit) {
    return $rights->validatedeve ? EP_ROLE_DEVE : ep_credit_pending_role($credit);
}

/**
 * Rôle attendu pour statuer sur un crédit. Un crédit lu avant les circuits à étapes, ou construit
 * à la main, n'a pas de rôle enregistré : il relève alors du responsable de son EP du catalogue,
 * ou du référent de l'étudiant pour une déclaration, comme avant les types paramétrés.
 *
 * @param stdClass $credit
 * @return string
 */
function ep_credit_pending_role(stdClass $credit) {
    if (!empty($credit->pendingrole)) {
        return $credit->pendingrole;
    }
    if (isset($credit->status) && (int) $credit->status === EP_STATUS_PENDING && property_exists($credit, 'pendingrole')) {
        return '';
    }
    return !empty($credit->activityid) ? EP_ROLE_OWNER : EP_ROLE_REFERENT;
}

/**
 * Détermine qui a le droit de statuer sur un crédit donné, à l'étape où il en est.
 *
 * À l'étape « responsable », seul un responsable de l'EP du catalogue concerné peut statuer :
 * c'est lui qui sait si l'étudiant a suivi son EP. À l'étape « référent », seul un enseignant
 * référent de l'étudiant, tel qu'il est défini dans l'activité « Gestion des stages » du même
 * cours. La DEVE (mod/ep:validatedeve) peut statuer à toute étape, notamment quand aucun référent
 * n'est attribué.
 *
 * Un crédit attribué automatiquement n'est validable par personne : il n'y a rien à vérifier, et
 * une validation manuelle serait de toute façon écrasée à la synchronisation suivante.
 *
 * @param stdClass $ep
 * @param stdClass $credit
 * @param context $context
 * @param int|null $userid Utilisateur courant par défaut.
 * @return bool
 */
function ep_can_validate_credit(stdClass $ep, stdClass $credit, context $context, $userid = null) {
    global $USER;

    $userid = $userid ?: $USER->id;

    if ($credit->source === EP_SOURCE_STAGE) {
        return false;
    }
    if (has_capability('mod/ep:validatedeve', $context, $userid)) {
        return true;
    }
    if (!has_capability('mod/ep:evaluateteacher', $context, $userid)) {
        return false;
    }

    $pendingrole = ep_credit_pending_role($credit);
    if ($pendingrole === EP_ROLE_OWNER) {
        return !empty($credit->activityid) && ep_is_activity_teacher($credit->activityid, $userid);
    }
    if ($pendingrole === EP_ROLE_REFERENT) {
        return in_array((int) $credit->userid, ep_get_referent_students($ep, $userid), true);
    }
    return false;
}

/**
 * Indique si un étudiant peut encore annuler lui-même une demande : tant qu'elle est en attente
 * de validation. Une fois validée ou refusée, seule la DEVE peut revenir dessus.
 *
 * @param stdClass $credit
 * @return bool
 */
function ep_student_can_cancel(stdClass $credit) {
    return (int) $credit->status === EP_STATUS_PENDING && $credit->source !== EP_SOURCE_STAGE;
}

/**
 * Indique si l'étudiant peut encore modifier sa déclaration : en attente et sans aucune
 * décision, même intermédiaire. Une demande validée par le référent et qui attend la DEVE se
 * retire encore, mais ne se modifie plus : la DEVE doit trancher ce que le référent a examiné.
 *
 * @param stdClass $credit
 * @return bool
 */
function ep_student_can_edit(stdClass $credit) {
    return (int) $credit->status === EP_STATUS_PENDING && (int) $credit->step === 0
        && $credit->source === EP_SOURCE_STUDENT && empty($credit->activityid);
}

/**
 * Justificatifs déposés par l'étudiant à l'appui d'un crédit, indexés par hachage de chemin (la
 * clé attendue par evidence_file.php pour servir le fichier).
 *
 * @param context $context
 * @param int $creditid
 * @return array pathnamehash => stored_file
 */
function ep_get_evidence_files(context $context, $creditid) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_ep', EP_EVIDENCE_FILEAREA, $creditid, 'filename', false);

    $indexed = [];
    foreach ($files as $file) {
        $indexed[$file->get_pathnamehash()] = $file;
    }
    return $indexed;
}

// Lien avec mod_stage : enseignants référents et attribution automatique des EP de type stage.

/**
 * Activités « Gestion des stages » dont dépend cette instance : celle explicitement désignée dans
 * les paramètres de l'activité (ep->stagecmid), ou à défaut toutes celles du même cours. Les
 * enseignants référents et les stages complémentaires (EP) en sont lus ; rien n'y est écrit.
 *
 * @param stdClass $ep
 * @return array stagecmid => stdClass{cm, stage, context}
 */
function ep_get_linked_stage_instances(stdClass $ep) {
    global $DB;

    // mod_stage est une dépendance déclarée, mais une instance peut être consultée pendant une
    // désinstallation ou une restauration partielle : mieux vaut un décompte sans stage qu'une
    // page en erreur.
    if (!$DB->get_manager()->table_exists('stage')) {
        return [];
    }

    $cms = [];
    if (!empty($ep->stagecmid)) {
        $cm = get_coursemodule_from_id('stage', $ep->stagecmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            $cms[] = $cm;
        }
    } else {
        $modinfo = get_fast_modinfo($ep->course);
        foreach ($modinfo->get_instances_of('stage') as $cminfo) {
            $cms[] = $cminfo;
        }
    }

    $instances = [];
    foreach ($cms as $cm) {
        $stage = $DB->get_record('stage', ['id' => $cm->instance]);
        if (!$stage) {
            continue;
        }
        $instances[(int) $cm->id] = (object) [
            'cm' => $cm,
            'stage' => $stage,
            'context' => context_module::instance($cm->id),
        ];
    }
    return $instances;
}

/**
 * Étudiants dont un enseignant est référent, au sens des activités « Gestion des stages » liées
 * (stage_entry_teacher). L'attribution n'est pas redemandée ici : elle est déjà tenue à jour dans
 * mod_stage, la redoubler dans ce module ferait diverger les deux listes.
 *
 * @param stdClass $ep
 * @param int $teacherid
 * @return int[] Identifiants d'étudiants, sans doublon.
 */
function ep_get_referent_students(stdClass $ep, $teacherid) {
    global $DB;

    $instances = ep_get_linked_stage_instances($ep);
    if (empty($instances)) {
        return [];
    }

    $stageids = [];
    foreach ($instances as $instance) {
        $stageids[] = (int) $instance->stage->id;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($stageids, SQL_PARAMS_NAMED, 'st');
    $studentids = $DB->get_fieldset_select(
        'stage_entry_teacher',
        'DISTINCT studentid',
        "stageid $insql AND teacherid = :teacherid",
        $inparams + ['teacherid' => $teacherid]
    );

    return array_map('intval', $studentids);
}

/**
 * Enseignants référents d'un étudiant, tels que définis dans les activités « Gestion des stages »
 * liées : affichés au validateur et à l'étudiant pour savoir qui doit statuer sur une déclaration.
 *
 * @param stdClass $ep
 * @param int $studentid
 * @return array userid => stdClass user
 */
function ep_get_student_referents(stdClass $ep, $studentid) {
    global $DB;

    $instances = ep_get_linked_stage_instances($ep);
    if (empty($instances)) {
        return [];
    }

    $stageids = [];
    foreach ($instances as $instance) {
        $stageids[] = (int) $instance->stage->id;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($stageids, SQL_PARAMS_NAMED, 'st');
    // Sous-requête plutôt que DISTINCT : la fiche utilisateur contient des champs texte (CLOB sous
    // Oracle), qu'un DISTINCT ne peut pas comparer.
    $sql = "SELECT u.*
              FROM {user} u
             WHERE u.id IN (SELECT et.teacherid
                              FROM {stage_entry_teacher} et
                             WHERE et.stageid $insql AND et.studentid = :studentid)
          ORDER BY u.lastname ASC, u.firstname ASC";

    return $DB->get_records_sql($sql, $inparams + ['studentid' => $studentid]);
}

/**
 * Stages complémentaires (EP) éligibles à une attribution automatique d'ECTS : les saisies des
 * activités « Gestion des stages » liées qui sont marquées « complémentaire (EP) » et déjà
 * validées par la DEVE. Un stage obligatoire ne donne pas d'ECTS d'enseignement personnalisé : il
 * relève du cursus, pas de l'EP.
 *
 * @param stdClass $ep
 * @return array Saisies mod_stage enrichies de themename et stagename.
 */
function ep_get_eligible_stage_entries(stdClass $ep) {
    global $CFG, $DB;

    $instances = ep_get_linked_stage_instances($ep);
    if (empty($instances)) {
        return [];
    }

    require_once($CFG->dirroot . '/mod/stage/lib.php');
    require_once($CFG->dirroot . '/mod/stage/locallib.php');

    $stageids = [];
    foreach ($instances as $instance) {
        $stageids[] = (int) $instance->stage->id;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($stageids, SQL_PARAMS_NAMED, 'st');
    $sql = "SELECT e.*, t.name AS themename, s.name AS stagename
              FROM {stage_entry} e
              JOIN {stage} s ON s.id = e.stageid
              JOIN {stage_convention_detail} d ON d.entryid = e.id
         LEFT JOIN {stage_theme} t ON t.id = e.themeid
             WHERE e.stageid $insql
               AND e.status = :validated
               AND d.stagetype = :stagetype
          ORDER BY e.id ASC";

    return $DB->get_records_sql($sql, $inparams + [
        'validated' => STAGE_STATUS_VALIDE_DEVE,
        'stagetype' => 'complementaire',
    ]);
}

/**
 * Recalcule les crédits de type stage d'une instance à partir des stages complémentaires (EP)
 * validés par la DEVE : un crédit par saisie, à raison du barème d'ECTS par jour retenu défini
 * pour le type (voir types.php).
 *
 * Ces crédits ne sont pas modifiables à la main : ils sont créés, mis à jour (le nombre de jours
 * retenus peut changer après coup) et retirés au gré des stages d'origine. Un stage dévalidé,
 * annulé ou repassé en obligatoire voit donc son crédit disparaître, plutôt que de laisser des
 * ECTS acquis sans contrepartie. Sans barème (ectsperday = 0) ou type désactivé, aucun crédit
 * n'est créé et les crédits existants sont retirés : c'est la façon de désactiver l'attribution
 * automatique sans avoir à faire le ménage soi-même.
 *
 * Le calcul se fait sous le verrou de l'activité : deux synchronisations lancées ensemble (deux
 * pages ouvertes au même moment, la tâche planifiée pendant un enregistrement de la page des
 * types) créaient chacune les crédits manquants, en double.
 *
 * @param stdClass $ep
 * @return stdClass {created, updated, deleted}
 */
function ep_sync_stage_credits(stdClass $ep) {
    $lock = ep_get_activity_lock($ep->id);
    try {
        return ep_sync_stage_credits_locked($ep);
    } finally {
        $lock->release();
    }
}

/**
 * Empreinte de ce dont dépendent les EP de type stage d'une instance : les activités « Gestion
 * des stages » liées, les types stage et leur barème, et les stages complémentaires validés par
 * la DEVE (nombre, identifiants, durées retenues, années, mobilité, dates de modification). Une
 * requête d'agrégat sur les saisies : assez légère pour être calculée à chaque page.
 *
 * mod_stage ne déclenche pas d'événement pour toutes ces modifications (une durée retenue
 * corrigée par la DEVE, par exemple) : c'est en comparant cette empreinte à celle de la dernière
 * synchronisation qu'on sait qu'il faut recalculer.
 *
 * @param stdClass $ep
 * @return string sha1
 */
function ep_stage_sync_signature(stdClass $ep) {
    global $CFG, $DB;

    $types = [];
    foreach (ep_get_stage_types($ep->id) as $type) {
        $types[] = [(int) $type->id, (float) $type->ectsperday, (string) $type->stagescope];
    }
    $stageids = array_values(array_map(fn($instance) => (int) $instance->stage->id, ep_get_linked_stage_instances($ep)));
    $entries = null;
    if ($types && $stageids) {
        require_once($CFG->dirroot . '/mod/stage/lib.php');
        [$insql, $inparams] = $DB->get_in_or_equal($stageids, SQL_PARAMS_NAMED, 'st');
        $entries = $DB->get_record_sql(
            "SELECT COUNT(e.id) AS n, COALESCE(SUM(e.id), 0) AS ids, COALESCE(SUM(e.retainedduration), 0) AS days,
                    COALESCE(SUM(e.studyyear), 0) AS years, COALESCE(SUM(e.abroad), 0) AS abroad,
                    COALESCE(MAX(e.timemodified), 0) AS entrytime, COALESCE(MAX(d.timemodified), 0) AS detailtime
               FROM {stage_entry} e
               JOIN {stage_convention_detail} d ON d.entryid = e.id
              WHERE e.stageid $insql AND e.status = :validated AND d.stagetype = :stagetype",
            $inparams + ['validated' => STAGE_STATUS_VALIDE_DEVE, 'stagetype' => 'complementaire']
        );
    }
    return sha1(json_encode([$stageids, $types, $entries ? array_values((array) $entries) : null]));
}

/**
 * Resynchronise les crédits de type stage si les stages ou le barème ont changé depuis la
 * dernière synchronisation (voir ep_stage_sync_signature()), ou si elle date de plus de
 * EP_STAGE_SYNC_INTERVAL. Appelée à l'ouverture des pages qui affichent un décompte et avant ce
 * qui en dépend (validation des années, supplément au diplôme), pour qu'un stage que la DEVE
 * vient de valider ou de corriger dans « Gestion des stages » y compte aussitôt.
 *
 * Sans $wait, si une autre requête synchronise déjà (verrou pris), la page n'attend pas : elle
 * affiche le décompte tel qu'il est, l'autre requête le mettant à jour. Avec $wait (validation
 * des années, émission d'un supplément), elle attend cette synchronisation : la décision ou le
 * document ne doit pas reposer sur un décompte en cours de mise à jour. L'empreinte est relue
 * sous le verrou, pour que deux pages ouvertes au même moment n'enchaînent pas deux
 * synchronisations.
 *
 * @param stdClass $ep
 * @param bool $wait Attendre le verrou de l'activité plutôt que d'y renoncer.
 * @return bool Vrai si une synchronisation a eu lieu.
 */
function ep_sync_stage_credits_if_due(stdClass $ep, bool $wait = false) {
    global $DB;

    $signature = ep_stage_sync_signature($ep);
    // L'état relu en base est reporté sur l'instance : la page travaille ensuite sur la dernière
    // synchronisation connue, pas sur celle qu'elle avait chargée.
    $due = function () use ($DB, $ep, $signature) {
        $stored = $DB->get_record('ep', ['id' => $ep->id], 'id, timesynced, stagesyncsig', MUST_EXIST);
        $ep->timesynced = (int) $stored->timesynced;
        $ep->stagesyncsig = $stored->stagesyncsig;
        return $stored->stagesyncsig !== $signature || time() - $ep->timesynced >= EP_STAGE_SYNC_INTERVAL;
    };
    if (!$due()) {
        return false;
    }
    if ($wait) {
        $lock = ep_get_activity_lock($ep->id);
    } else {
        $lock = \core\lock\lock_config::get_lock_factory('mod_ep_activity')->get_lock('ep-' . (int) $ep->id, 0);
        if (!$lock) {
            return false;
        }
    }
    try {
        if (!$due()) {
            return false;
        }
        ep_sync_stage_credits_locked($ep);
        return true;
    } finally {
        $lock->release();
    }
}

// Décompte des ECTS : plafonds par type, minimums par année et de cursus.

/**
 * Calcule le bilan d'ECTS d'un étudiant : ce qui est validé, ce qui est effectivement retenu une
 * fois les plafonds du type appliqués, et la comparaison aux minimums exigés.
 *
 * Application des plafonds, pour chaque type, par années croissantes : le plafond annuel du type
 * s'applique d'abord à l'année, puis le plafond de cursus à ce qui a déjà été retenu les années
 * précédentes. Prendre les années dans l'ordre chronologique est ce qui rend le résultat stable :
 * les ECTS acquis en premier sont retenus en premier, et une année déjà validée ne peut pas être
 * remise en cause par un EP saisi plus tard.
 *
 * Ce qui dépasse un plafond n'est pas perdu pour autant : le crédit reste validé, seul son
 * décompte est écrêté (colonne « non retenus »). Relever un plafond suffit donc à le faire
 * ressortir, sans revalider quoi que ce soit.
 *
 * @param stdClass $ep
 * @param int $userid
 * @return stdClass {types, years, totalvalidated, totalretained, totalcapped, totalpending,
 *                   mincursus, cursusdone, complete}
 *                  'types' : typeid => {type, validated, retained, capped, pending}
 *                  'years' : studyyear => {studyyear, required, retained, validated, capped,
 *                                          done, bytype}
 */
function ep_get_student_progress(stdClass $ep, $userid) {
    $types = ep_get_types($ep->id);
    $credits = ep_get_student_credits($ep->id, $userid);
    $requirements = ep_get_year_requirements($ep->id);

    // ECTS validés et en attente, ventilés par type et par année.
    $validated = [];
    $pending = [];
    $years = [];
    foreach ($requirements as $year => $required) {
        if ($required > 0) {
            $years[$year] = true;
        }
    }
    foreach ($credits as $credit) {
        $typeid = (int) $credit->typeid;
        $year = (int) $credit->studyyear;
        if ((int) $credit->status === EP_STATUS_VALIDATED) {
            $validated[$typeid][$year] = ($validated[$typeid][$year] ?? 0) + (float) $credit->retainedects;
            $years[$year] = true;
        } else if ((int) $credit->status === EP_STATUS_PENDING) {
            $pending[$typeid] = ($pending[$typeid] ?? 0) + (float) $credit->claimedects;
            $years[$year] = true;
        }
    }

    $yearkeys = array_keys($years);
    sort($yearkeys);

    // Application des plafonds, type par type, par années croissantes.
    $retained = [];
    $typetotals = [];
    foreach ($types as $typeid => $type) {
        $cursusretained = 0.0;
        $typevalidated = 0.0;
        foreach ($yearkeys as $year) {
            $raw = (float) ($validated[$typeid][$year] ?? 0);
            $typevalidated += $raw;

            $kept = $raw;
            if ($type->maxectsperyear > 0) {
                $kept = min($kept, (float) $type->maxectsperyear);
            }
            if ($type->maxects > 0) {
                $kept = min($kept, max(0.0, (float) $type->maxects - $cursusretained));
            }
            $cursusretained += $kept;
            $retained[$typeid][$year] = round($kept, 2);
        }

        $typetotals[$typeid] = (object) [
            'type' => $type,
            'validated' => round($typevalidated, 2),
            'retained' => round($cursusretained, 2),
            'capped' => round($typevalidated - $cursusretained, 2),
            'pending' => round((float) ($pending[$typeid] ?? 0), 2),
        ];
    }

    // Bilan par année : le minimum annuel porte sur le total retenu, tous types confondus.
    $yearrows = [];
    foreach ($yearkeys as $year) {
        $yearretained = 0.0;
        $yearvalidated = 0.0;
        $bytype = [];
        foreach ($types as $typeid => $type) {
            $raw = round((float) ($validated[$typeid][$year] ?? 0), 2);
            $kept = (float) ($retained[$typeid][$year] ?? 0);
            if ($raw <= 0 && $kept <= 0) {
                continue;
            }
            $bytype[$typeid] = (object) [
                'type' => $type,
                'validated' => $raw,
                'retained' => $kept,
                'capped' => round($raw - $kept, 2),
            ];
            $yearretained += $kept;
            $yearvalidated += $raw;
        }

        $required = (float) ($requirements[$year] ?? 0);
        $yearrows[$year] = (object) [
            'studyyear' => $year,
            'required' => $required,
            'retained' => round($yearretained, 2),
            'validated' => round($yearvalidated, 2),
            'capped' => round($yearvalidated - $yearretained, 2),
            'done' => $required <= 0 || $yearretained >= $required,
            'bytype' => $bytype,
        ];
    }

    $totalretained = 0.0;
    $totalvalidated = 0.0;
    $totalpending = 0.0;
    foreach ($typetotals as $total) {
        $totalretained += $total->retained;
        $totalvalidated += $total->validated;
        $totalpending += $total->pending;
    }

    $mincursus = (float) $ep->mincursusects;
    $dueyears = ep_filter_due_years($ep, $yearrows);
    $yearsdone = count(array_filter($dueyears, function ($row) {
        return $row->done;
    }));

    return (object) [
        'types' => $typetotals,
        'years' => $yearrows,
        'totalvalidated' => round($totalvalidated, 2),
        'totalretained' => round($totalretained, 2),
        'totalcapped' => round($totalvalidated - $totalretained, 2),
        'totalpending' => round($totalpending, 2),
        'mincursus' => $mincursus,
        'cursusdone' => $mincursus <= 0 || $totalretained >= $mincursus,
        'yearstotal' => count($dueyears),
        'yearsdone' => $yearsdone,
        'complete' => $yearsdone === count($dueyears)
            && ($mincursus <= 0 || $totalretained >= $mincursus),
    ];
}

/**
 * Ne retient, parmi les bilans annuels, que les années déjà exigibles : l'année d'étude courante
 * de l'activité et les précédentes. Les objectifs des années à venir ne sont pas encore dus —
 * les compter ferait apparaître en retard toute une promotion qui est parfaitement à jour. Tant
 * que l'année courante n'est pas renseignée, toutes les années sont retenues.
 *
 * @param stdClass $ep
 * @param array $yearrows Bilans annuels de ep_get_student_progress().
 * @return array Sous-ensemble de $yearrows.
 */
function ep_filter_due_years(stdClass $ep, array $yearrows) {
    if (empty($ep->currentstudyyear)) {
        return $yearrows;
    }
    return array_filter($yearrows, function ($row) use ($ep) {
        return $row->studyyear <= $ep->currentstudyyear;
    });
}

/**
 * Vue de pilotage : pour chaque étudiant inscrit, son bilan d'ECTS et le nombre de demandes
 * encore en attente de validation.
 *
 * @param stdClass $ep
 * @param context $context
 * @param array|null $restrictuserids Si fourni, limite aux étudiants de cette liste (enseignant référent).
 * @return array Liste d'objets {user, progress, creditcount, pendingcount}
 */
function ep_get_pilotage_overview(stdClass $ep, context $context, ?array $restrictuserids = null) {
    $students = ep_get_enrolled_students($context, '', $restrictuserids);
    return ep_build_pilotage_rows($ep, $students);
}

/**
 * Retourne les étudiants inscrits au cours (capacité de soumission) dans le contexte du module,
 * triés par nom, avec recherche, restriction et pagination en base.
 *
 * @param context $context
 * @param string $search Recherche sur le nom complet.
 * @param array|null $restrictids Si fourni, seuls ces étudiants.
 * @param int $limitfrom
 * @param int $limitnum 0 = sans limite.
 * @return array userid => stdClass user
 */
function ep_get_enrolled_students(context $context, $search = '', ?array $restrictids = null, $limitfrom = 0, $limitnum = 0) {
    global $DB;

    $built = ep_enrolled_students_sql($context, $search, $restrictids);
    if ($built === null) {
        return [];
    }
    [$sql, $params] = $built;
    return $DB->get_records_sql(
        "SELECT u.* $sql ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC",
        $params,
        $limitfrom,
        $limitnum
    );
}

/**
 * Retourne les enseignants pouvant être responsables d'un EP du catalogue.
 *
 * @param context $context
 * @return array
 */
function ep_get_potential_teachers(context $context) {
    return get_enrolled_users($context, 'mod/ep:evaluateteacher', 0, 'u.*', 'u.lastname, u.firstname');
}

// Listes de crédits : recherche, tri, pagination.

/**
 * Clés de tri proposées sur les listes de crédits.
 *
 * @return array clé => libellé
 */
function ep_credit_sort_options() {
    return [
        'student' => get_string('student', 'mod_ep'),
        'type' => get_string('type', 'mod_ep'),
        'name' => get_string('creditname', 'mod_ep'),
        'ects' => get_string('claimedects', 'mod_ep'),
        'status' => get_string('status', 'mod_ep'),
        'timecreated' => get_string('submittedon', 'mod_ep'),
    ];
}

/**
 * Recherche / tri des crédits, pour les listes de la DEVE et des enseignants, paginés en base.
 *
 * @param int $epid
 * @param array $filters ['search' => nom étudiant, 'typeid' => int, 'activityid' => int,
 *                        'status' => int, 'studyyear' => int|'' (0 = année non précisée),
 *                        'userid' => int]
 * @param string $sort Une des clés de ep_credit_sort_options().
 * @param string $dir 'ASC' ou 'DESC'.
 * @param array|null $restrictuserids Si fourni, limite aux crédits de ces étudiants.
 * @param array|null $restrictactivityids Si fourni, ajoute aux crédits ci-dessus ceux portant sur
 *                                        ces EP du catalogue (responsable d'un EP mais pas
 *                                        référent de l'étudiant, cas courant).
 * @param int $limitfrom
 * @param int $limitnum 0 = sans limite.
 * @param string $scopemode 'view' ou 'decide' (voir ep_filtered_credits_sql()).
 * @return array id => stdClass
 */
function ep_get_filtered_credits(
    $epid,
    array $filters = [],
    $sort = 'timecreated',
    $dir = 'DESC',
    ?array $restrictuserids = null,
    ?array $restrictactivityids = null,
    $limitfrom = 0,
    $limitnum = 0,
    $scopemode = 'view'
) {
    global $DB;

    $built = ep_filtered_credits_sql($epid, $filters, $restrictuserids, $restrictactivityids, $scopemode);
    if ($built === null) {
        return [];
    }
    [$where, $params] = $built;

    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
    $sortmap = [
        'student' => "u.lastname $dir, u.firstname $dir",
        'type' => "t.sortorder $dir, t.name $dir",
        'name' => "c.name $dir",
        'ects' => "c.claimedects $dir",
        'status' => "c.status $dir",
        'timecreated' => "c.timecreated $dir",
    ];
    $sortcolumns = $sortmap[$sort] ?? $sortmap['timecreated'];

    $sql = "SELECT c.*
              FROM {ep_credit} c
              JOIN {user} u ON u.id = c.userid
         LEFT JOIN {ep_type} t ON t.id = c.typeid
             WHERE $where
          ORDER BY $sortcolumns, c.id $dir";

    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Charge en une requête les étudiants concernés par une liste de crédits.
 *
 * @param array $credits
 * @return array userid => stdClass user
 */
function ep_get_credit_users(array $credits) {
    global $DB;

    $userids = [];
    foreach ($credits as $credit) {
        $userids[(int) $credit->userid] = true;
    }
    if (empty($userids)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($userids), SQL_PARAMS_NAMED, 'u');
    return $DB->get_records_select('user', "id $insql", $inparams);
}

/**
 * Droits de l'utilisateur courant sur les crédits d'une instance, calculés une seule fois par
 * page : l'appartenance au périmètre d'un enseignant (étudiants dont il est référent, EP dont il
 * est responsable) demande des requêtes qu'il serait absurde de rejouer à chaque ligne d'un
 * tableau.
 *
 * @param stdClass $ep
 * @param context $context
 * @param int|null $userid Utilisateur courant par défaut.
 * @return stdClass {validatedeve, viewall, manage, evaluateteacher, referentids, responsibleids}
 */
function ep_get_user_rights(stdClass $ep, context $context, $userid = null) {
    global $USER;

    $userid = $userid ?: $USER->id;

    $rights = (object) [
        'userid' => $userid,
        'validatedeve' => has_capability('mod/ep:validatedeve', $context, $userid),
        'viewall' => has_capability('mod/ep:viewall', $context, $userid),
        'manage' => has_capability('mod/ep:manage', $context, $userid),
        'evaluateteacher' => has_capability('mod/ep:evaluateteacher', $context, $userid),
        'referentids' => [],
        'responsibleids' => [],
    ];

    if ($rights->evaluateteacher) {
        $rights->referentids = ep_get_referent_students($ep, $userid);
        $rights->responsibleids = array_map('intval', array_keys(ep_get_responsible_activities($ep->id, $userid)));
    }

    return $rights;
}

/**
 * Applique les droits pré-calculés par ep_get_user_rights() à un crédit donné : même règle que
 * ep_can_validate_credit(), sans requête supplémentaire.
 *
 * @param stdClass $rights
 * @param stdClass $credit
 * @return bool
 */
function ep_rights_can_validate(stdClass $rights, stdClass $credit) {
    if ($credit->source === EP_SOURCE_STAGE) {
        return false;
    }
    if ($rights->validatedeve) {
        return true;
    }
    if (!$rights->evaluateteacher) {
        return false;
    }
    $pendingrole = ep_credit_pending_role($credit);
    if ($pendingrole === EP_ROLE_OWNER) {
        return !empty($credit->activityid) && in_array((int) $credit->activityid, $rights->responsibleids, true);
    }
    if ($pendingrole === EP_ROLE_REFERENT) {
        return in_array((int) $credit->userid, $rights->referentids, true);
    }
    return false;
}

/**
 * Indique si un utilisateur a un périmètre de validation non vide : il est DEVE, ou bien
 * référent d'au moins un étudiant ou responsable d'au moins un EP du catalogue. Sert à n'afficher
 * l'écran de validation qu'à ceux qui ont effectivement quelque chose à y faire.
 *
 * @param stdClass $rights
 * @return bool
 */
function ep_rights_has_validation_scope(stdClass $rights) {
    return $rights->validatedeve
        || !empty($rights->referentids)
        || !empty($rights->responsibleids);
}

/**
 * Crédits en attente de la décision de l'utilisateur : ses inscriptions à valider comme
 * responsable d'EP, et les déclarations de ses étudiants à valider comme enseignant référent. La
 * DEVE voit toutes les demandes en attente.
 *
 * @param stdClass $ep
 * @param stdClass $rights Voir ep_get_user_rights().
 * @param string $sort
 * @param string $dir
 * @param int $limitfrom
 * @param int $limitnum
 * @return array
 */
function ep_get_credits_awaiting($ep, stdClass $rights, $sort = 'timecreated', $dir = 'ASC', $limitfrom = 0, $limitnum = 0) {
    $filters = ['status' => EP_STATUS_PENDING];

    if ($rights->validatedeve) {
        return ep_get_filtered_credits($ep->id, $filters, $sort, $dir, null, null, $limitfrom, $limitnum);
    }
    return ep_get_filtered_credits(
        $ep->id,
        $filters,
        $sort,
        $dir,
        $rights->referentids,
        $rights->responsibleids,
        $limitfrom,
        $limitnum,
        'decide'
    );
}

// Rendu commun aux vues.

/**
 * Rend une série d'actions en petits boutons (gabarit mod_ep/actions). Une entrée dont l'URL est
 * nulle est ignorée, ce qui permet aux appelants de décrire toutes les actions possibles et de
 * laisser la condition d'affichage à l'endroit où elle se lit.
 *
 * @param array $links libellé => moodle_url|null
 * @param string $class
 * @param string $empty Rendu si aucune action n'est disponible.
 * @return string
 */
function ep_render_actions(array $links, $class = 'btn btn-sm btn-secondary mr-1 mb-1', $empty = '-') {
    global $OUTPUT;

    $actions = [];
    foreach ($links as $label => $url) {
        if ($url === null) {
            continue;
        }
        $actions[] = ['label' => $label, 'url' => $url instanceof moodle_url ? $url->out(false) : (string) $url, 'class' => $class];
    }
    return $OUTPUT->render_from_template('mod_ep/actions', ['actions' => $actions, 'empty' => $empty]);
}

/**
 * Actions d'une ligne de tableau, allégées : l'action principale en bouton, les autres dans un
 * menu (les trois points de Moodle), pour qu'une liste de plusieurs dizaines de lignes ne soit
 * pas une colonne de boutons.
 *
 * @param array $primary Libellé => moodle_url (null pour l'omettre), affichés en boutons.
 * @param array $secondary Libellé => moodle_url (null pour l'omettre), dans le menu.
 * @param string[] $danger Libellés du menu à afficher en rouge (suppression).
 * @return string HTML
 */
function ep_render_action_menu(array $primary, array $secondary, array $danger = []) {
    global $OUTPUT;

    $html = ep_render_actions($primary, 'btn btn-sm btn-secondary mr-1 mb-1', '');
    $secondary = array_filter($secondary, fn($url) => $url !== null);
    if ($secondary) {
        $menu = new action_menu();
        $menu->set_kebab_trigger(get_string('actions'));
        $menu->set_additional_classes('d-inline-block');
        foreach ($secondary as $label => $url) {
            $menu->add(new action_menu_link_secondary(
                $url,
                null,
                $label,
                in_array($label, $danger, true) ? ['class' => 'text-danger'] : []
            ));
        }
        $html .= $OUTPUT->render($menu);
    }
    return $html !== '' ? $html : '-';
}

/**
 * Badge du statut d'un crédit (gabarit mod_ep/credit_status) : en attente, validé, refusé,
 * annulé.
 *
 * @param int $status
 * @return string HTML
 */
function ep_render_credit_status($status) {
    // Même fragment pour toutes les lignes d'un même état : rendu une fois par requête.
    return ep_request_memo('creditstatus' . (int) $status, function () use ($status) {
        global $OUTPUT;
        return $OUTPUT->render_from_template('mod_ep/credit_status', [
            'label' => ep_status_label($status),
            'class' => ep_status_badgeclass($status),
        ]);
    });
}

/**
 * Rend un tableau de liste (gabarit mod_ep/paged_table) : en-têtes et cellules déjà rendus,
 * suivis d'une barre de pagination.
 *
 * @param array $head En-têtes (HTML), dans l'ordre des colonnes.
 * @param array $rows Lignes : tableaux de cellules (HTML).
 * @param string $pagingbar Barre de pagination (HTML), ou vide.
 * @param string $class Classe CSS du tableau.
 * @return string
 */
function ep_render_paged_table(array $head, array $rows, $pagingbar = '', $class = 'generaltable ep-table') {
    global $OUTPUT;

    $data = (object) ['head' => [], 'rows' => [], 'pagingbar' => $pagingbar, 'class' => $class];
    foreach ($head as $cell) {
        $data->head[] = ['html' => $cell];
    }
    foreach ($rows as $row) {
        $cells = [];
        foreach ($row as $cell) {
            $cells[] = ['html' => $cell];
        }
        $data->rows[] = ['cells' => $cells];
    }
    return $OUTPUT->render_from_template('mod_ep/paged_table', $data);
}

/**
 * Barre de pagination d'une liste paginée en base.
 *
 * @param int $total Nombre total de lignes.
 * @param int $page Page courante (0-indexée).
 * @param moodle_url $baseurl
 * @param int $perpage
 * @param string $pagevar Paramètre de page, quand une page porte deux listes.
 * @return string HTML, vide s'il n'y a qu'une page.
 */
function ep_render_paging_bar($total, $page, moodle_url $baseurl, $perpage = EP_LIST_PERPAGE, $pagevar = 'page') {
    global $OUTPUT;

    return $total > $perpage ? $OUTPUT->render(new paging_bar($total, $page, $perpage, $baseurl, $pagevar)) : '';
}

/**
 * Construit l'URL de tri (nouvelle colonne ou inversion du sens) pour un en-tête de tableau.
 *
 * @param moodle_url $baseurl
 * @param string $key
 * @param string $currentsort
 * @param string $currentdir
 * @return moodle_url
 */
function ep_sort_url(moodle_url $baseurl, $key, $currentsort, $currentdir) {
    $newdir = ($currentsort === $key && strtoupper($currentdir) === 'ASC') ? 'DESC' : 'ASC';
    $url = new moodle_url($baseurl);
    $url->params(['tsort' => $key, 'tdir' => $newdir]);
    return $url;
}

/**
 * Rend un lien d'en-tête de colonne triable, avec indicateur de sens si c'est la colonne active.
 *
 * @param string $label
 * @param string $key
 * @param moodle_url $baseurl
 * @param string $currentsort
 * @param string $currentdir
 * @return string
 */
function ep_sort_header($label, $key, moodle_url $baseurl, $currentsort, $currentdir) {
    $indicator = '';
    if ($currentsort === $key) {
        $indicator = ' ' . (strtoupper($currentdir) === 'ASC' ? '▲' : '▼');
    }
    return html_writer::link(ep_sort_url($baseurl, $key, $currentsort, $currentdir), $label . $indicator);
}

/**
 * Découpe un tableau pour l'affichage d'une page, et rend la barre de pagination correspondante.
 *
 * @param array $items Déjà filtrés et triés.
 * @param int $page Page courante (0-indexée).
 * @param moodle_url $baseurl
 * @param int $perpage
 * @return array [page d'éléments à afficher, html de la barre de pagination]
 */
function ep_paginate(array $items, $page, moodle_url $baseurl, $perpage = EP_LIST_PERPAGE) {
    global $OUTPUT;

    $items = array_values($items);
    $total = count($items);
    $pageitems = array_slice($items, $page * $perpage, $perpage);
    $pagingbar = $total > $perpage ? $OUTPUT->render(new paging_bar($total, $page, $perpage, $baseurl)) : '';

    return [$pageitems, $pagingbar];
}

/**
 * Badge « Atteint » / « À compléter » (gabarit mod_ep/status_badge), utilisé dans tous les bilans
 * de l'étudiant.
 *
 * @param bool $done
 * @return string HTML
 */
function ep_render_status_badge($done) {
    global $OUTPUT;

    return $OUTPUT->render_from_template('mod_ep/status_badge', [
        'done' => (bool) $done,
        'label' => get_string($done ? 'objectivedone' : 'objectivetodo', 'mod_ep'),
    ]);
}

/**
 * Construit les cellules « requis / retenu / reste à faire / statut » communes aux bilans.
 * Le reste à faire évite d'avoir à soustraire mentalement le retenu du requis sur chaque ligne ;
 * sans exigence chiffrée, seul le retenu a un sens.
 *
 * @param float $retained
 * @param float $required 0 si aucune exigence.
 * @param bool $done
 * @return array Quatre cellules : requis, retenu, reste, statut.
 */
function ep_render_progress_cells($retained, $required, $done) {
    if ($required <= 0) {
        return ['-', ep_format_ects($retained), '-', '-'];
    }

    return [
        ep_format_ects($required),
        ep_format_ects($retained),
        $done ? '-' : ep_format_ects($required - $retained),
        ep_render_status_badge($done),
    ];
}

/**
 * En-têtes de colonnes communs aux tableaux de bilan, à faire suivre des cellules construites par
 * ep_render_progress_cells().
 *
 * @return array
 */
function ep_progress_table_head() {
    return [
        get_string('requiredects', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('remainingects', 'mod_ep'),
        get_string('status', 'mod_ep'),
    ];
}

/**
 * En-tête d'activité des pages secondaires : Moodle y affiche déjà le nom de l'activité, et sa
 * description n'a sa place que sur la page d'accueil (view.php). À appeler avant l'en-tête et
 * avant tout accès à $PAGE->activityheader.
 *
 * @return void
 */
function ep_set_secondary_page_header() {
    global $PAGE;

    // La description est vidée sur l'enregistrement de l'activité avant que l'en-tête ne soit
    // construit : celui-ci la mettrait sinon en forme (filtres de texte compris) pour rien, soit
    // plusieurs millisecondes par page.
    $record = $PAGE->activityrecord;
    if ($record) {
        $record->intro = '';
    }
}

/**
 * Barre de navigation de l'activité : les destinations utiles à l'utilisateur, dans l'ordre du
 * travail courant (s'inscrire, déclarer, valider, piloter) puis les usages ponctuels (export,
 * administration) en fin de barre.
 *
 * @param stdClass $ep
 * @param stdClass $cm
 * @param context $context
 * @param stdClass|null $rights Voir ep_get_user_rights() ; calculé ici s'il n'est pas fourni.
 * @return string
 */
function ep_render_navlinks(stdClass $ep, stdClass $cm, context $context, ?stdClass $rights = null) {
    $rights = $rights ?: ep_get_user_rights($ep, $context);

    $links = [];
    // Le catalogue sert à l'étudiant pour s'inscrire, et à la DEVE pour contrôler ce qu'il y voit
    // (places restantes, responsables, EP fermés) : il figure dans les deux barres.
    if (has_capability('mod/ep:submit', $context) || $rights->manage) {
        $links[get_string('catalog', 'mod_ep')] = new moodle_url('/mod/ep/catalog.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/ep:submit', $context)) {
        if (ep_get_declarable_types($ep->id)) {
            $links[get_string('declarecredit', 'mod_ep')] =
                new moodle_url('/mod/ep/declare.php', ['id' => $cm->id]);
        }
        $links[get_string('mysupplement', 'mod_ep')] = new moodle_url('/mod/ep/supplement.php', ['id' => $cm->id]);
    }
    if (ep_rights_has_validation_scope($rights)) {
        $links[get_string('validation', 'mod_ep')] = new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]);
    }
    // Les éléments de supplément attendent la DEVE ou le référent de l'étudiant : le lien n'est
    // utile qu'à eux.
    if ($rights->validatedeve || !empty($rights->referentids)) {
        $links[get_string('supplementitemstodecide', 'mod_ep')] =
            new moodle_url('/mod/ep/supplement_items.php', ['id' => $cm->id]);
    }
    if ($rights->viewall || !empty($rights->referentids)) {
        $links[get_string('pilotage', 'mod_ep')] = new moodle_url('/mod/ep/dashboard.php', ['id' => $cm->id]);
    }
    if (has_capability('mod/ep:propose', $context) || $rights->manage) {
        $links[get_string('proposals', 'mod_ep')] = new moodle_url('/mod/ep/proposals.php', ['id' => $cm->id]);
    }
    if ($rights->viewall) {
        $links[get_string('exportcsv', 'mod_ep')] = new moodle_url('/mod/ep/export.php', ['id' => $cm->id]);
    }
    if ($rights->manage) {
        $links[get_string('administration', 'mod_ep')] =
            new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
    }

    if (empty($links)) {
        return '';
    }
    return html_writer::div(ep_render_actions($links, 'btn btn-secondary mr-1 mb-1'), 'ep-navlinks mb-3');
}

/**
 * Formulaire de recherche / filtre au-dessus des listes de crédits (gabarit mod_ep/list_filters).
 * Les autres paramètres présents dans $baseurl (id, mode...) sont préservés en champs cachés.
 *
 * @param moodle_url $baseurl URL courante, avec les valeurs de filtre déjà appliquées.
 * @param array $types Types proposés dans le filtre.
 * @param array $values ['search' => string, 'typeid' => int, 'status' => string, 'studyyear' => int]
 * @param bool $showstatus Affiche le filtre de statut (inutile sur une liste déjà restreinte).
 * @return string
 */
function ep_render_list_filters(moodle_url $baseurl, array $types, array $values, $showstatus = true) {
    global $OUTPUT;

    $typeoptions = [0 => get_string('alltypes', 'mod_ep')];
    foreach ($types as $type) {
        $typeoptions[$type->id] = format_string($type->name);
    }
    $selects = ['typeid' => ['options' => $typeoptions, 'selected' => $values['typeid'] ?? 0]];

    // L'année non précisée (0) est une valeur de rattachement à part entière : le « toutes les
    // années » du filtre est donc la chaîne vide, pas 0.
    $selects['studyyear'] = [
        'options' => ['' => get_string('allyears', 'mod_ep')] + ep_studyyear_options(),
        'selected' => $values['studyyear'] ?? '',
    ];
    if ($showstatus) {
        $selects['status'] = [
            'options' => ['' => get_string('allstatuses', 'mod_ep')] + ep_status_options(),
            'selected' => $values['status'] ?? '',
        ];
    }

    $filters = new \mod_ep\output\list_filters(
        $baseurl,
        ['search', 'typeid', 'status', 'studyyear', 'tsort', 'tdir', 'page'],
        $values['search'] ?? '',
        $selects
    );
    return $OUTPUT->render($filters);
}

/**
 * Rend le rappel d'un crédit (type, EP, année, ECTS, statut, décision) en tableau : utilisé en
 * tête de l'écran de validation et du détail consulté par l'étudiant, pour que les deux
 * présentent exactement les mêmes informations.
 *
 * @param stdClass $credit
 * @param stdClass|null $type
 * @param stdClass|null $student
 * @return string HTML
 */
function ep_render_credit_summary(stdClass $credit, $type = null, $student = null) {
    global $DB;

    $rows = [];
    if ($student) {
        $rows[] = [get_string('student', 'mod_ep'), fullname($student)];
    }
    $rows[] = [get_string('type', 'mod_ep'), $type ? format_string($type->name) : '-'];
    $rows[] = [get_string('creditname', 'mod_ep'), format_string($credit->name)];
    $rows[] = [get_string('studyyear', 'mod_ep'), ep_studyyear_label($credit->studyyear)];
    $rows[] = [get_string('claimedects', 'mod_ep'), ep_format_ects($credit->claimedects)];
    $statuscell = ep_render_credit_status($credit->status);
    if ((int) $credit->status === EP_STATUS_PENDING && $credit->pendingrole !== '') {
        $statuscell .= ' ' . html_writer::span(
            get_string('awaitingrole', 'mod_ep', ep_role_label($credit->pendingrole)),
            'text-muted small'
        );
    }
    $rows[] = [get_string('status', 'mod_ep'), $statuscell];
    if ($type && !empty($type->validationflow)) {
        $rows[] = [get_string('validationflow', 'mod_ep'), ep_flow_label($type->validationflow)];
    }
    if ($credit->grade !== null) {
        $grading = ep_credit_grading($credit, $type ?: null);
        $rows[] = [
            get_string('grade', 'mod_ep'),
            get_string('gradeoutof', 'mod_ep', (object) [
                'grade' => ep_format_ects($credit->grade),
                'max' => ep_format_ects($grading->grademax),
            ]),
        ];
    }
    if ((int) $credit->status === EP_STATUS_VALIDATED) {
        $rows[] = [get_string('retainedects', 'mod_ep'), ep_format_ects($credit->retainedects)];
    }
    if ($credit->description !== null && trim($credit->description) !== '') {
        $rows[] = [get_string('creditdescription', 'mod_ep'), format_text($credit->description, FORMAT_PLAIN)];
    }
    if ($credit->validatetime) {
        $decidedby = '-';
        if (!empty($credit->validatedby)) {
            $user = $DB->get_record('user', ['id' => $credit->validatedby]);
            $decidedby = $user ? fullname($user) : '-';
        } else if ($credit->source === EP_SOURCE_STAGE) {
            // Une attribution automatique n'a pas d'auteur : le dire explicitement évite de
            // laisser croire à un oubli de traçabilité.
            $decidedby = get_string('automaticattribution', 'mod_ep');
        }
        $rows[] = [get_string('decidedby', 'mod_ep'), $decidedby];
        $rows[] = [
            get_string('decidedon', 'mod_ep'),
            userdate($credit->validatetime, get_string('strftimedatetimeshort')),
        ];
    }
    if ($credit->validatorcomment !== null && trim($credit->validatorcomment) !== '') {
        $rows[] = [get_string('validatorcomment', 'mod_ep'), format_text($credit->validatorcomment, FORMAT_PLAIN)];
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable ep-credit-summary';
    $table->data = $rows;

    return html_writer::table($table) . ep_render_credit_decisions($credit);
}

/**
 * Rend l'historique des décisions d'un crédit (une ligne par étape : qui, quand, quoi, note,
 * ECTS, commentaire), ou rien s'il n'y en a pas encore.
 *
 * @param stdClass $credit
 * @return string HTML
 */
function ep_render_credit_decisions(stdClass $credit) {
    global $DB, $OUTPUT;

    $decisions = ep_get_credit_decisions($credit->id);
    if (empty($decisions)) {
        return '';
    }
    $userids = array_filter(array_map(fn($d) => (int) $d->userid, $decisions));
    $users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

    $rows = [];
    foreach ($decisions as $decision) {
        $user = $users[$decision->userid] ?? null;
        $rows[] = [
            userdate($decision->timecreated, get_string('strftimedatetimeshort')),
            ep_role_label($decision->role),
            $user ? fullname($user) : '-',
            get_string('decision_' . $decision->decision, 'mod_ep'),
            $decision->grade !== null ? ep_format_ects($decision->grade) : '-',
            $decision->decision === 'validated' ? ep_format_ects($decision->retainedects) : '-',
            $decision->comment !== null && trim($decision->comment) !== ''
                ? format_text($decision->comment, FORMAT_PLAIN) : '-',
        ];
    }
    return $OUTPUT->heading(get_string('decisionhistory', 'mod_ep'), 5) . ep_render_paged_table([
        get_string('decidedon', 'mod_ep'),
        get_string('role', 'mod_ep'),
        get_string('decidedby', 'mod_ep'),
        get_string('decision', 'mod_ep'),
        get_string('grade', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('validatorcomment', 'mod_ep'),
    ], $rows);
}

/**
 * Rend la liste des justificatifs d'un crédit, en liens de téléchargement contrôlés par
 * evidence_file.php.
 *
 * @param stdClass $cm
 * @param context $context
 * @param stdClass $credit
 * @return string HTML, ou un message si aucun justificatif n'a été déposé.
 */
function ep_render_evidence_files(stdClass $cm, context $context, stdClass $credit) {
    global $OUTPUT;

    $files = ep_get_evidence_files($context, $credit->id);
    if (empty($files)) {
        return $OUTPUT->notification(get_string('noevidencefiles', 'mod_ep'), 'info');
    }

    $items = [];
    foreach ($files as $pathnamehash => $file) {
        $url = new moodle_url(
            '/mod/ep/evidence_file.php',
            ['id' => $cm->id, 'creditid' => $credit->id, 'pathnamehash' => $pathnamehash]
        );
        $items[] = html_writer::link($url, s($file->get_filename()));
    }
    return html_writer::alist($items);
}

/**
 * Actions proposées sur un crédit dans la liste des EP d'un étudiant. Chaque action n'apparaît que
 * si l'utilisateur y a droit ET que le crédit est dans un état où elle a un sens : un bouton qui
 * mène à un refus est pire que pas de bouton du tout.
 *
 * @param stdClass $credit
 * @param stdClass $cm
 * @param stdClass $rights Voir ep_get_user_rights().
 * @param bool $isowner L'utilisateur courant est l'étudiant concerné.
 * @return string HTML
 */
function ep_render_credit_actions(stdClass $credit, stdClass $cm, stdClass $rights, $isowner = false) {
    global $PAGE;

    $returnurl = $PAGE->url ? $PAGE->url->out_as_local_url(false) : null;
    $canvalidate = ep_rights_can_validate($rights, $credit) && (int) $credit->status === EP_STATUS_PENDING;
    $canview = $isowner || $rights->viewall || ep_rights_can_validate($rights, $credit);

    $detailurl = new moodle_url('/mod/ep/validate.php', ['id' => $cm->id, 'creditid' => $credit->id]);
    if ($returnurl !== null) {
        $detailurl->param('returnurl', $returnurl);
    }

    $actions = [];
    if ($canvalidate) {
        $actions[get_string('validatecredit', 'mod_ep')] = $detailurl;
    } else if ($canview) {
        $actions[get_string('viewdetails', 'mod_ep')] = $detailurl;
    }

    // L'étudiant peut revenir sur sa demande tant que personne ne s'est prononcé : la modifier
    // (déclaration hors catalogue) ou la retirer (inscription au catalogue comme déclaration).
    if ($isowner && ep_student_can_cancel($credit)) {
        if (ep_student_can_edit($credit)) {
            $actions[get_string('edit')] =
                new moodle_url('/mod/ep/declare.php', ['id' => $cm->id, 'creditid' => $credit->id]);
        }
        $actions[get_string('cancelrequest', 'mod_ep')] = new moodle_url(
            '/mod/ep/declare.php',
            ['id' => $cm->id, 'creditid' => $credit->id, 'action' => 'cancel', 'sesskey' => sesskey()]
        );
    }

    return ep_render_actions($actions);
}

/**
 * Affiche le bilan complet d'un étudiant : la synthèse en tête, le bilan par année d'étude, le
 * bilan par type (avec les plafonds et ce qu'ils écrêtent) puis le détail de chaque EP porté à
 * son crédit. Sert à la fois de tableau de bord à l'étudiant (view.php) et de fiche détaillée à
 * la DEVE et aux enseignants (dashboard.php), les actions proposées différant seules.
 *
 * @param stdClass $ep
 * @param int $userid
 * @param stdClass $cm
 * @param stdClass $rights Voir ep_get_user_rights().
 * @param bool $isowner L'utilisateur courant est l'étudiant concerné.
 * @return void
 */
function ep_print_student_dashboard(stdClass $ep, $userid, stdClass $cm, stdClass $rights, $isowner = false) {
    global $OUTPUT;

    $progress = ep_get_student_progress($ep, $userid);
    // Seules l'année courante et les précédentes sont présentées : les objectifs des années à
    // venir ne sont pas encore exigibles, les afficher comme « à compléter » ne ferait qu'alarmer
    // inutilement. Tant que l'année courante n'est pas renseignée, toutes les années restent là.
    $yearrows = ep_filter_due_years($ep, $progress->years);

    // 1. Synthèse : les chiffres clés en tête, pour situer l'avancement avant le détail.
    echo $OUTPUT->heading(get_string('summary', 'mod_ep'), 4);
    $summary = new html_table();
    $summary->head = [get_string('summaryitem', 'mod_ep'), get_string('summaryvalue', 'mod_ep')];
    $summary->data[] = [
        get_string('summarytotalretained', 'mod_ep'),
        ep_format_ects_unit($progress->totalretained),
    ];
    if ($progress->mincursus > 0) {
        $summary->data[] = [
            get_string('summarycursusminimum', 'mod_ep'),
            get_string('progressofects', 'mod_ep', (object) [
                'retained' => ep_format_ects($progress->totalretained),
                'required' => ep_format_ects($progress->mincursus),
            ]) . ' ' . ep_render_status_badge($progress->cursusdone),
        ];
    }
    if ($progress->yearstotal > 0) {
        $summary->data[] = [
            get_string('summaryyearsdone', 'mod_ep'),
            $progress->yearsdone . ' / ' . $progress->yearstotal . ' '
                . ep_render_status_badge($progress->yearsdone === $progress->yearstotal),
        ];
    }
    if ($progress->totalpending > 0) {
        $summary->data[] = [
            get_string('summarypending', 'mod_ep'),
            ep_format_ects_unit($progress->totalpending),
        ];
    }
    if ($progress->totalcapped > 0) {
        // Les ECTS écrêtés par un plafond de type : dits explicitement, sans quoi la différence
        // entre ce qui est validé et ce qui est compté passerait pour une erreur de calcul.
        $summary->data[] = [
            get_string('summarycapped', 'mod_ep'),
            ep_format_ects_unit($progress->totalcapped),
        ];
    }
    echo html_writer::table($summary);

    // 2. Bilan par année d'étude, avec le détail des types qui y contribuent : l'année n'est
    // rappelée que sur la première ligne de son groupe.
    if (!empty($yearrows)) {
        // Décision de la DEVE sur chaque année, à côté du minimum atteint ou non : c'est elle qui
        // vaut, l'atteinte du minimum n'en est que l'indice.
        $decisions = ep_get_year_validations($ep->id, [$userid])[$userid] ?? [];
        echo $OUTPUT->heading(get_string('yeartotals', 'mod_ep'), 4);
        $yeartable = new html_table();
        $yeartable->head = array_merge(
            [get_string('studyyear', 'mod_ep'), get_string('objective', 'mod_ep')],
            ep_progress_table_head()
        );
        foreach ($yearrows as $row) {
            $yearcell = html_writer::tag('strong', ep_studyyear_label($row->studyyear))
                . ' ' . ep_render_status_badge($row->done);
            $decision = $decisions[(int) $row->studyyear] ?? null;
            if ($decision) {
                $yearcell .= html_writer::div(html_writer::span(
                    get_string($decision->decision ? 'yearvalidated' : 'yearnotvalidated', 'mod_ep'),
                    'badge ' . ($decision->decision ? 'badge-success' : 'badge-danger')
                ), 'mt-1');
            }
            $yeartable->data[] = array_merge(
                [$yearcell, html_writer::tag('strong', get_string('yearminimum', 'mod_ep'))],
                ep_render_progress_cells($row->retained, $row->required, $row->done)
            );
            foreach ($row->bytype as $typerow) {
                $label = format_string($typerow->type->name);
                if ($typerow->capped > 0) {
                    $label .= ' ' . html_writer::span(
                        get_string('cappedshort', 'mod_ep', ep_format_ects($typerow->capped)),
                        'badge badge-secondary'
                    );
                }
                $yeartable->data[] = array_merge(
                    ['', $label],
                    ep_render_progress_cells($typerow->retained, 0, true)
                );
            }
        }
        echo html_writer::table($yeartable);
    }

    // 3. Bilan par type, toutes années confondues : rappelle les plafonds applicables, y compris
    // pour les types sur lesquels rien n'a encore été validé.
    echo $OUTPUT->heading(get_string('typetotals', 'mod_ep'), 4);
    $typetable = new html_table();
    $typetable->head = [
        get_string('type', 'mod_ep'),
        get_string('maxects', 'mod_ep'),
        get_string('maxectsperyear', 'mod_ep'),
        get_string('validatedects', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('cappedects', 'mod_ep'),
        get_string('pendingects', 'mod_ep'),
    ];
    foreach ($progress->types as $total) {
        if (empty($total->type->enabled) && $total->validated <= 0 && $total->pending <= 0) {
            continue;
        }
        $typetable->data[] = [
            format_string($total->type->name),
            $total->type->maxects > 0 ? ep_format_ects($total->type->maxects) : '-',
            $total->type->maxectsperyear > 0 ? ep_format_ects($total->type->maxectsperyear) : '-',
            ep_format_ects($total->validated),
            ep_format_ects($total->retained),
            $total->capped > 0 ? ep_format_ects($total->capped) : '-',
            $total->pending > 0 ? ep_format_ects($total->pending) : '-',
        ];
    }
    echo html_writer::table($typetable);

    // 4. Détail de chaque EP porté au crédit de l'étudiant.
    echo $OUTPUT->heading(get_string('allmycredits', 'mod_ep'), 4);
    $credits = ep_get_student_credits($ep->id, $userid);
    $types = ep_get_types($ep->id);

    $table = new html_table();
    $table->head = [
        get_string('type', 'mod_ep'),
        get_string('creditname', 'mod_ep'),
        get_string('studyyear', 'mod_ep'),
        get_string('claimedects', 'mod_ep'),
        get_string('retainedects', 'mod_ep'),
        get_string('status', 'mod_ep'),
        get_string('actions', 'mod_ep'),
    ];
    foreach ($credits as $credit) {
        $type = $types[$credit->typeid] ?? null;
        $name = format_string($credit->name);
        if ($credit->source === EP_SOURCE_STAGE) {
            $name .= ' ' . html_writer::span(get_string('automatic', 'mod_ep'), 'badge badge-light');
        }
        $table->data[] = [
            $type ? format_string($type->name) : '-',
            $name,
            ep_studyyear_label($credit->studyyear),
            ep_format_ects($credit->claimedects),
            (int) $credit->status === EP_STATUS_VALIDATED ? ep_format_ects($credit->retainedects) : '-',
            ep_render_credit_status($credit->status),
            ep_render_credit_actions($credit, $cm, $rights, $isowner),
        ];
    }
    if (empty($table->data)) {
        echo $OUTPUT->notification(get_string('nocredits', 'mod_ep'), 'info');
    } else {
        echo html_writer::table($table);
    }
}

/**
 * Corps de la synchronisation des crédits de type stage, à appeler sous le verrou de l'activité
 * (voir ep_sync_stage_credits()).
 *
 * Plusieurs types peuvent être alimentés par les stages, avec chacun sa portée (tous les stages,
 * ceux à l'étranger, ceux en France) et son barème : un stage va au premier type, dans l'ordre
 * d'affichage, dont la portée le couvre. Sans type actif doté d'un barème, aucun crédit n'est
 * créé et les crédits existants sont retirés.
 *
 * @param stdClass $ep
 * @return stdClass {created, updated, deleted}
 */
function ep_sync_stage_credits_locked(stdClass $ep) {
    global $CFG, $DB;

    $result = (object) ['created' => 0, 'updated' => 0, 'deleted' => 0];

    // Empreinte prise avant la lecture des stages : un stage modifié pendant le calcul change
    // l'empreinte et fera recalculer la fois suivante, au lieu d'être tenu pour vu.
    $signature = ep_stage_sync_signature($ep);
    $stagetypes = ep_get_stage_types($ep->id);
    $existing = $DB->get_records('ep_credit', ['epid' => $ep->id, 'source' => EP_SOURCE_STAGE]);
    $byref = [];
    foreach ($existing as $credit) {
        $byref[(int) $credit->sourceref] = $credit;
    }

    $entries = !empty($stagetypes) ? ep_get_eligible_stage_entries($ep) : [];

    $now = time();
    foreach ($entries as $entry) {
        $type = null;
        foreach ($stagetypes as $candidate) {
            if (
                $candidate->stagescope === 'all'
                    || ($candidate->stagescope === 'abroad' && !empty($entry->abroad))
                    || ($candidate->stagescope === 'domestic' && empty($entry->abroad))
            ) {
                $type = $candidate;
                break;
            }
        }
        if (!$type) {
            continue;
        }
        $ects = round((float) $entry->retainedduration * (float) $type->ectsperday, 2);
        // Intitulé dans la langue du site, et non dans celle de la page qui déclenche la
        // synchronisation (sinon chaque consultation dans une autre langue réécrirait tous les
        // crédits), borné à la taille du champ : thème et structure peuvent faire 255 caractères.
        $name = core_text::substr(get_string_manager()->get_string('stagecreditname', 'mod_ep', (object) [
            'theme' => $entry->themename !== null ? format_string($entry->themename) : '-',
            'structure' => $entry->structure !== null ? format_string($entry->structure) : '-',
        ], $CFG->lang), 0, 255);
        $description = get_string_manager()->get_string('stagecreditdescription', 'mod_ep', (object) [
            'days' => $entry->retainedduration,
            'rate' => ep_format_ects($type->ectsperday),
            'activity' => format_string($entry->stagename),
        ], $CFG->lang);

        $credit = $byref[(int) $entry->id] ?? null;
        if ($credit) {
            unset($byref[(int) $entry->id]);
            if (
                (float) $credit->retainedects === $ects && (int) $credit->studyyear === (int) $entry->studyyear
                    && $credit->name === $name && (int) $credit->status === EP_STATUS_VALIDATED
                    && (int) $credit->typeid === (int) $type->id
            ) {
                continue;
            }
            $credit->typeid = $type->id;
            $credit->studyyear = (int) $entry->studyyear;
            $credit->name = $name;
            $credit->description = $description;
            $credit->claimedects = $ects;
            $credit->retainedects = $ects;
            $credit->status = EP_STATUS_VALIDATED;
            $credit->pendingrole = '';
            $credit->validatetime = $now;
            $credit->timemodified = $now;
            $DB->update_record('ep_credit', $credit);
            ep_trigger_credit_event(\mod_ep\event\credit_synced::class, $credit, [
                'action' => 'updated',
                'sourceref' => (int) $entry->id,
            ]);
            $result->updated++;
        } else {
            ep_create_credit($ep, (int) $entry->userid, $type, [
                'studyyear' => (int) $entry->studyyear,
                'name' => $name,
                'description' => $description,
                'claimedects' => $ects,
                'source' => EP_SOURCE_STAGE,
                'sourceref' => (int) $entry->id,
            ]);
            $result->created++;
        }
    }

    // Ce qui reste dans $byref ne correspond plus à un stage complémentaire validé : le crédit
    // n'a plus lieu d'être.
    foreach ($byref as $orphan) {
        $DB->delete_records('ep_credit_decision', ['creditid' => $orphan->id]);
        $DB->delete_records('ep_credit', ['id' => $orphan->id]);
        ep_trigger_credit_event(\mod_ep\event\credit_synced::class, $orphan, [
            'action' => 'deleted',
            'sourceref' => (int) $orphan->sourceref,
        ]);
        $result->deleted++;
    }

    $DB->update_record('ep', (object) ['id' => $ep->id, 'timesynced' => $now, 'stagesyncsig' => $signature]);
    $ep->timesynced = $now;
    $ep->stagesyncsig = $signature;

    return $result;
}

/**
 * Lignes du tableau de pilotage pour les étudiants donnés (voir ep_get_pilotage_overview()). Les
 * données de tous ces étudiants sont chargées une fois pour toutes (voir
 * mod_ep\local\progress_cache) : trois requêtes au lieu de trois par étudiant.
 *
 * @param stdClass $ep
 * @param array $students Étudiants, indexés par identifiant.
 * @return array Liste d'objets {user, progress, creditcount, pendingcount}
 */
function ep_build_pilotage_rows(stdClass $ep, array $students) {
    \mod_ep\local\progress_cache::prime((int) $ep->id, array_keys($students));
    try {
        $rows = [];
        foreach ($students as $student) {
            $credits = ep_get_student_credits($ep->id, $student->id);
            $pending = 0;
            foreach ($credits as $credit) {
                if ((int) $credit->status === EP_STATUS_PENDING) {
                    $pending++;
                }
            }

            $rows[] = (object) [
                'user' => $student,
                'progress' => ep_get_student_progress($ep, $student->id),
                'creditcount' => count($credits),
                'pendingcount' => $pending,
            ];
        }
        return $rows;
    } finally {
        \mod_ep\local\progress_cache::clear();
    }
}

/**
 * Clause SQL des étudiants inscrits (capacité de soumission) dans le contexte du module, avec
 * recherche sur le nom et restriction à une liste d'identifiants.
 *
 * @param context $context
 * @param string $search Recherche sur le nom complet (vide : tous).
 * @param array|null $restrictids Si fourni, seuls ces étudiants (liste vide : aucun).
 * @return array{0: string, 1: array}|null [clause FROM/WHERE après « SELECT ... », paramètres], ou null si
 *                                        la restriction est une liste vide.
 */
function ep_enrolled_students_sql(context $context, $search = '', ?array $restrictids = null) {
    global $DB;

    [$esql, $params] = get_enrolled_sql($context, 'mod/ep:submit');
    $where = [];
    if ($search !== '') {
        $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $where[] = $DB->sql_like($fullname, ':search', false, false);
        $params['search'] = '%' . $DB->sql_like_escape($search) . '%';
    }
    if ($restrictids !== null) {
        $restrictids = array_values(array_unique(array_map('intval', $restrictids)));
        if (empty($restrictids)) {
            return null;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($restrictids, SQL_PARAMS_NAMED, 'ri');
        $where[] = "u.id $insql";
        $params += $inparams;
    }
    $sql = "FROM {user} u
            JOIN ($esql) je ON je.id = u.id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    return [$sql, $params];
}

/**
 * Nombre d'étudiants inscrits répondant à une recherche (voir ep_get_enrolled_students()).
 *
 * @param context $context
 * @param string $search
 * @param array|null $restrictids
 * @return int
 */
function ep_count_enrolled_students(context $context, $search = '', ?array $restrictids = null) {
    global $DB;

    $built = ep_enrolled_students_sql($context, $search, $restrictids);
    if ($built === null) {
        return 0;
    }
    [$sql, $params] = $built;
    return $DB->count_records_sql("SELECT COUNT(u.id) $sql", $params);
}

/**
 * Clauses SQL communes à la liste et au comptage des crédits filtrés (voir
 * ep_get_filtered_credits()).
 *
 * Périmètre d'un enseignant : en mode « view », les crédits de ses étudiants (comme référent) et
 * ceux des EP dont il est responsable ; en mode « decide », seulement ce sur quoi il a à statuer
 * à l'étape courante du circuit : les crédits de ses étudiants qui attendent le référent et les
 * inscriptions à ses EP qui attendent le responsable. Les deux ensembles vides signifient qu'il
 * n'a rien à voir ici, et non qu'il peut tout voir : null est alors renvoyé.
 *
 * @param int $epid
 * @param array $filters Voir ep_get_filtered_credits().
 * @param array|null $restrictuserids
 * @param array|null $restrictactivityids
 * @param string $scopemode 'view' ou 'decide'.
 * @return array{0: string, 1: array}|null [clause WHERE sans le mot-clé, paramètres], ou null.
 */
function ep_filtered_credits_sql(
    $epid,
    array $filters,
    ?array $restrictuserids,
    ?array $restrictactivityids,
    $scopemode = 'view'
) {
    global $DB;

    $params = ['epid' => $epid];
    $where = ['c.epid = :epid'];

    if (!empty($filters['search'])) {
        $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $where[] = $DB->sql_like($fullname, ':search', false, false);
        $params['search'] = '%' . $DB->sql_like_escape($filters['search']) . '%';
    }
    if (!empty($filters['typeid'])) {
        $where[] = 'c.typeid = :typeid';
        $params['typeid'] = (int) $filters['typeid'];
    }
    if (!empty($filters['activityid'])) {
        $where[] = 'c.activityid = :activityid';
        $params['activityid'] = (int) $filters['activityid'];
    }
    if (isset($filters['status']) && $filters['status'] !== '') {
        $where[] = 'c.status = :status';
        $params['status'] = (int) $filters['status'];
    }
    if (isset($filters['studyyear']) && $filters['studyyear'] !== '') {
        $where[] = 'c.studyyear = :studyyear';
        $params['studyyear'] = (int) $filters['studyyear'];
    }
    if (!empty($filters['userid'])) {
        $where[] = 'c.userid = :fuserid';
        $params['fuserid'] = (int) $filters['userid'];
    }

    if ($restrictuserids !== null || $restrictactivityids !== null) {
        $scope = [];
        if (!empty($restrictuserids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($restrictuserids, SQL_PARAMS_NAMED, 'ru');
            $scope[] = $scopemode === 'decide'
                ? "(c.pendingrole = :rolereferent AND c.userid $insql)"
                : "c.userid $insql";
            $params += $inparams;
        }
        if (!empty($restrictactivityids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($restrictactivityids, SQL_PARAMS_NAMED, 'ra');
            $scope[] = $scopemode === 'decide'
                ? "(c.pendingrole = :roleowner AND c.activityid $insql)"
                : "c.activityid $insql";
            $params += $inparams;
        }
        if ($scopemode === 'decide') {
            $params['rolereferent'] = EP_ROLE_REFERENT;
            $params['roleowner'] = EP_ROLE_OWNER;
        }
        if (empty($scope)) {
            return null;
        }
        $where[] = '(' . implode(' OR ', $scope) . ')';
        if ($scopemode === 'decide') {
            // Un crédit attribué automatiquement n'est validable par personne.
            $where[] = 'c.source <> :scopestage';
            $params['scopestage'] = EP_SOURCE_STAGE;
        }
    }

    return [implode(' AND ', $where), $params];
}

/**
 * Nombre de crédits répondant aux mêmes filtres que ep_get_filtered_credits().
 *
 * @param int $epid
 * @param array $filters
 * @param array|null $restrictuserids
 * @param array|null $restrictactivityids
 * @param string $scopemode
 * @return int
 */
function ep_count_filtered_credits(
    $epid,
    array $filters = [],
    ?array $restrictuserids = null,
    ?array $restrictactivityids = null,
    $scopemode = 'view'
) {
    global $DB;

    $built = ep_filtered_credits_sql($epid, $filters, $restrictuserids, $restrictactivityids, $scopemode);
    if ($built === null) {
        return 0;
    }
    [$where, $params] = $built;
    // La jointure aux comptes ne sert qu'à la recherche sur le nom : sans elle, le total se lit
    // sur l'index (promotion, état) seul.
    $join = preg_match('/\bu\./', $where) ? 'JOIN {user} u ON u.id = c.userid' : '';
    return $DB->count_records_sql(
        "SELECT COUNT(1)
           FROM {ep_credit} c
           $join
          WHERE $where",
        $params
    );
}

/**
 * Applique les droits pré-calculés par ep_get_user_rights() à un crédit donné, en lecture : la
 * DEVE voit tout, un enseignant voit ce sur quoi il peut statuer et le dossier complet des
 * étudiants dont il est référent.
 *
 * @param stdClass $rights
 * @param stdClass $credit
 * @return bool
 */
function ep_rights_can_view(stdClass $rights, stdClass $credit) {
    if ($rights->viewall) {
        return true;
    }
    if (in_array((int) $credit->userid, $rights->referentids, true)) {
        return true;
    }
    return ep_rights_can_validate($rights, $credit);
}

/**
 * Nombre de crédits en attente de la décision de l'utilisateur (voir ep_get_credits_awaiting()).
 *
 * @param stdClass $ep
 * @param stdClass $rights
 * @return int
 */
function ep_count_credits_awaiting($ep, stdClass $rights) {
    $filters = ['status' => EP_STATUS_PENDING];
    if ($rights->validatedeve) {
        return ep_count_filtered_credits($ep->id, $filters);
    }
    return ep_count_filtered_credits($ep->id, $filters, $rights->referentids, $rights->responsibleids, 'decide');
}

/**
 * Valeurs par défaut d'un type, complétées par les paramètres donnés : sert à la création des
 * types par défaut comme à celle d'un type ajouté par la DEVE.
 *
 * @param int $epid
 * @param array $params Paramètres connus (code, name, acquisition...).
 * @return stdClass Enregistrement prêt à insérer.
 */
function ep_type_record($epid, array $params) {
    $now = time();
    $record = (object) array_merge([
        'epid' => $epid,
        'code' => '',
        'name' => '',
        'description' => '',
        'enabled' => 1,
        'acquisition' => EP_ACQ_DECLARATION,
        'validationflow' => EP_ROLE_REFERENT,
        'grading' => EP_GRADING_NONE,
        'grademax' => 20,
        'gradepass' => 10,
        'evidence' => EP_EVIDENCE_OPTIONAL,
        'evidencetypes' => 'documents',
        'maxclaimedects' => 0,
        'stagescope' => 'all',
        'insupplement' => 1,
        'maxects' => 0,
        'maxectsperyear' => 0,
        'ectsperday' => 0,
        'sortorder' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
    ], $params);
    $record->validationflow = implode(',', ep_normalise_flow($record->validationflow, $record->acquisition));
    return $record;
}

/**
 * Modes d'acquisition proposés à la DEVE.
 *
 * @return array code => libellé
 */
function ep_acquisition_options() {
    return [
        EP_ACQ_CATALOG => get_string('acquisition_catalog', 'mod_ep'),
        EP_ACQ_DECLARATION => get_string('acquisition_declaration', 'mod_ep'),
        EP_ACQ_STAGE => get_string('acquisition_stage', 'mod_ep'),
        EP_ACQ_DEVE => get_string('acquisition_deve', 'mod_ep'),
    ];
}

/**
 * Étapes d'un circuit de validation, dans l'ordre.
 *
 * @param string $flow Étapes séparées par des virgules.
 * @return string[] Rôles (EP_ROLE_OWNER, EP_ROLE_REFERENT, EP_ROLE_DEVE), sans doublon.
 */
function ep_flow_steps($flow) {
    $steps = [];
    foreach (explode(',', (string) $flow) as $step) {
        $step = trim($step);
        if (in_array($step, [EP_ROLE_OWNER, EP_ROLE_REFERENT, EP_ROLE_DEVE], true) && !in_array($step, $steps, true)) {
            $steps[] = $step;
        }
    }
    return $steps;
}

/**
 * Met un circuit de validation en accord avec le mode d'acquisition : un type attribué
 * automatiquement ou saisi par la DEVE n'a pas de circuit (le crédit naît validé), l'étape
 * « responsable de l'EP » n'a de sens que pour le catalogue.
 *
 * @param string|array $flow
 * @param string $acquisition
 * @return string[] Étapes retenues.
 */
function ep_normalise_flow($flow, $acquisition) {
    $steps = is_array($flow) ? $flow : ep_flow_steps($flow);
    if ($acquisition === EP_ACQ_STAGE || $acquisition === EP_ACQ_DEVE) {
        return [];
    }
    if ($acquisition !== EP_ACQ_CATALOG) {
        $steps = array_values(array_diff($steps, [EP_ROLE_OWNER]));
    }
    return array_values($steps);
}

/**
 * Circuits de validation proposés à la DEVE pour un mode d'acquisition.
 *
 * @param string $acquisition
 * @return array flow => libellé
 */
function ep_flow_options($acquisition) {
    $options = ['' => get_string('flow_none', 'mod_ep')];
    if ($acquisition === EP_ACQ_STAGE || $acquisition === EP_ACQ_DEVE) {
        return $options;
    }
    $candidates = [
        EP_ROLE_OWNER,
        EP_ROLE_REFERENT,
        EP_ROLE_DEVE,
        EP_ROLE_OWNER . ',' . EP_ROLE_DEVE,
        EP_ROLE_REFERENT . ',' . EP_ROLE_DEVE,
        EP_ROLE_OWNER . ',' . EP_ROLE_REFERENT,
        EP_ROLE_OWNER . ',' . EP_ROLE_REFERENT . ',' . EP_ROLE_DEVE,
    ];
    foreach ($candidates as $flow) {
        $steps = ep_normalise_flow($flow, $acquisition);
        if (empty($steps) || implode(',', $steps) !== $flow) {
            continue;
        }
        $options[$flow] = implode(' → ', array_map('ep_role_label', $steps));
    }
    return $options;
}

/**
 * Libellé d'un rôle du circuit de validation.
 *
 * @param string $role
 * @return string
 */
function ep_role_label($role) {
    $known = [EP_ROLE_OWNER, EP_ROLE_REFERENT, EP_ROLE_DEVE, EP_ROLE_STUDENT];
    return in_array($role, $known, true) ? get_string('role_' . $role, 'mod_ep') : '-';
}

/**
 * Libellé lisible d'un circuit de validation.
 *
 * @param string $flow
 * @return string
 */
function ep_flow_label($flow) {
    $steps = ep_flow_steps($flow);
    if (empty($steps)) {
        return get_string('flow_none', 'mod_ep');
    }
    return implode(' → ', array_map('ep_role_label', $steps));
}

/**
 * Indique si les crédits d'un type naissent validés : aucune étape de validation.
 *
 * @param stdClass $type
 * @return bool
 */
function ep_type_is_automatic(stdClass $type) {
    return empty(ep_flow_steps($type->validationflow));
}

/**
 * Indique si un type se prend par inscription au catalogue.
 *
 * @param stdClass $type
 * @return bool
 */
function ep_type_is_catalog(stdClass $type) {
    return $type->acquisition === EP_ACQ_CATALOG;
}

/**
 * Indique si un type se déclare par l'étudiant.
 *
 * @param stdClass $type
 * @return bool
 */
function ep_type_is_declarable(stdClass $type) {
    return $type->acquisition === EP_ACQ_DECLARATION;
}

/**
 * Indique si un type est alimenté automatiquement par les stages de mod_stage.
 *
 * @param stdClass $type
 * @return bool
 */
function ep_type_is_stage(stdClass $type) {
    return $type->acquisition === EP_ACQ_STAGE;
}

/**
 * Indique si un type est noté (la note décide de la validation).
 *
 * @param stdClass $type
 * @return bool
 */
function ep_type_is_graded(stdClass $type) {
    return $type->grading === EP_GRADING_GRADE;
}

/**
 * Formats de fichiers admis pour les justificatifs d'un type, dans la forme attendue par le
 * gestionnaire de fichiers.
 *
 * @param stdClass $type
 * @return array
 */
function ep_type_evidence_accepted_types(stdClass $type) {
    return $type->evidencetypes === 'pdf' ? ['.pdf'] : ['document', 'image', 'archive'];
}

/**
 * Options de justificatifs proposées à la DEVE.
 *
 * @return array int => libellé
 */
function ep_evidence_options() {
    return [
        EP_EVIDENCE_NONE => get_string('evidence_none', 'mod_ep'),
        EP_EVIDENCE_OPTIONAL => get_string('evidence_optional', 'mod_ep'),
        EP_EVIDENCE_REQUIRED => get_string('evidence_required', 'mod_ep'),
    ];
}

/**
 * Types alimentés par les stages de mod_stage, activés et dotés d'un barème, dans l'ordre
 * d'affichage : un stage va au premier dont la portée (tous, à l'étranger, en France) le couvre.
 *
 * @param int $epid
 * @return array id => stdClass
 */
function ep_get_stage_types($epid) {
    return array_filter(ep_get_types($epid, true), function ($type) {
        return ep_type_is_stage($type) && $type->ectsperday > 0;
    });
}

/**
 * Types dont la DEVE peut saisir un crédit à la main : tous sauf les types automatiques.
 *
 * @param int $epid
 * @return array id => stdClass
 */
function ep_get_deve_entry_types($epid) {
    return array_filter(ep_get_types($epid, true), function ($type) {
        return !ep_type_is_stage($type);
    });
}

/**
 * Propose un code court unique dans l'instance à partir d'un libellé : lettres et chiffres
 * ASCII, 20 caractères au plus, suffixé d'un numéro si le code existe déjà.
 *
 * @param int $epid
 * @param string $name
 * @param int $excludeid Type en cours d'édition, dont le code peut être conservé.
 * @return string
 */
function ep_suggest_type_code($epid, $name, $excludeid = 0) {
    global $DB;

    $base = core_text::strtolower(core_text::specialtoascii($name));
    $base = preg_replace('/[^a-z0-9]+/', '', $base);
    // 18 caractères au plus : le code en admet 20, deux sont réservés au suffixe de dédoublonnage.
    $base = core_text::substr($base ?: 'type', 0, 18);
    $code = $base;
    $suffix = 1;
    while (true) {
        $existing = $DB->get_record('ep_type', ['epid' => $epid, 'code' => $code], 'id');
        if (!$existing || (int) $existing->id === (int) $excludeid) {
            return $code;
        }
        $suffix++;
        $code = $base . $suffix;
    }
}

/**
 * Enregistre un type (création ou modification), après mise en cohérence de ses paramètres.
 *
 * @param int $epid
 * @param stdClass $data Champs du formulaire ; id présent pour une modification.
 * @return int Identifiant du type.
 */
function ep_save_type($epid, stdClass $data) {
    global $DB;

    $existing = !empty($data->id) ? $DB->get_record('ep_type', ['id' => $data->id, 'epid' => $epid], '*', MUST_EXIST) : null;
    $record = ep_type_record($epid, (array) $data + ($existing ? (array) $existing : []));
    $record->epid = $epid;
    $record->name = trim((string) $record->name);
    $record->code = ep_suggest_type_code($epid, $record->code !== '' ? $record->code : $record->name, $existing->id ?? 0);
    $record->enabled = empty($record->enabled) ? 0 : 1;
    $record->insupplement = empty($record->insupplement) ? 0 : 1;
    $record->grading = $record->grading === EP_GRADING_GRADE ? EP_GRADING_GRADE : EP_GRADING_NONE;
    $record->grademax = max(1, round((float) $record->grademax, 2));
    $record->gradepass = min($record->grademax, max(0, round((float) $record->gradepass, 2)));
    $record->evidence = in_array((int) $record->evidence, [0, 1, 2], true) ? (int) $record->evidence : EP_EVIDENCE_OPTIONAL;
    $record->evidencetypes = $record->evidencetypes === 'pdf' ? 'pdf' : 'documents';
    $record->stagescope = in_array($record->stagescope, ['all', 'abroad', 'domestic'], true) ? $record->stagescope : 'all';
    foreach (['maxects', 'maxectsperyear', 'maxclaimedects'] as $field) {
        $record->$field = max(0, round((float) $record->$field, 2));
    }
    $record->ectsperday = ep_type_is_stage($record) ? max(0, round((float) $record->ectsperday, 3)) : 0;
    $record->timemodified = time();

    if ($existing) {
        $record->id = $existing->id;
        $record->timecreated = $existing->timecreated;
        $DB->update_record('ep_type', $record);
        return (int) $existing->id;
    }
    unset($record->id);
    if (empty($record->sortorder)) {
        $record->sortorder = 10 + (int) $DB->get_field('ep_type', 'MAX(sortorder)', ['epid' => $epid]);
    }
    return $DB->insert_record('ep_type', $record);
}

/**
 * Indique si un type peut être supprimé : aucun crédit ni EP du catalogue ne s'y réfère. Un type
 * utilisé se désactive, il ne se supprime pas.
 *
 * @param int $typeid
 * @return bool
 */
function ep_type_is_deletable($typeid) {
    global $DB;

    return !$DB->record_exists('ep_credit', ['typeid' => $typeid])
        && !$DB->record_exists('ep_activity', ['typeid' => $typeid]);
}

/**
 * Déplace un type d'un cran vers le haut ou vers le bas dans l'ordre d'affichage, en
 * renumérotant tous les types de l'instance de 10 en 10.
 *
 * @param stdClass $type
 * @param int $direction -1 vers le haut, +1 vers le bas.
 * @return void
 */
function ep_move_type(stdClass $type, $direction) {
    global $DB;

    $types = array_values(ep_get_types($type->epid));
    $index = null;
    foreach ($types as $i => $candidate) {
        if ((int) $candidate->id === (int) $type->id) {
            $index = $i;
        }
    }
    $target = $index + ($direction < 0 ? -1 : 1);
    if ($index === null || $target < 0 || $target >= count($types)) {
        return;
    }
    [$types[$index], $types[$target]] = [$types[$target], $types[$index]];
    foreach ($types as $i => $candidate) {
        $DB->set_field('ep_type', 'sortorder', ($i + 1) * 10, ['id' => $candidate->id]);
    }
}

/**
 * États d'un EP du catalogue, dans l'ordre de son cycle de vie.
 *
 * @return array état => libellé
 */
function ep_activity_state_options() {
    $options = [];
    foreach ([EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED, EP_ACTIVITY_PUBLISHED, EP_ACTIVITY_CLOSED, EP_ACTIVITY_ARCHIVED] as $state) {
        $options[$state] = get_string('activitystate_' . $state, 'mod_ep');
    }
    return $options;
}

/**
 * Indique si un EP figure au catalogue des étudiants : publié ou clos.
 *
 * @param stdClass $activity
 * @return bool
 */
function ep_activity_is_listed(stdClass $activity) {
    return in_array($activity->state, [EP_ACTIVITY_PUBLISHED, EP_ACTIVITY_CLOSED], true);
}

/**
 * Indique si un EP accepte des inscriptions à un instant donné : publié, et dans sa période
 * d'inscription si elle est fixée.
 *
 * @param stdClass $activity
 * @param int|null $now
 * @return bool
 */
function ep_activity_registration_open(stdClass $activity, $now = null) {
    $now = $now ?? time();
    if ($activity->state !== EP_ACTIVITY_PUBLISHED) {
        return false;
    }
    if (!empty($activity->registrationstart) && $now < (int) $activity->registrationstart) {
        return false;
    }
    if (!empty($activity->registrationend) && $now > (int) $activity->registrationend) {
        return false;
    }
    return true;
}

/**
 * Libellé de la période d'inscription d'un EP, ou chaîne vide si elle n'est pas fixée.
 *
 * @param stdClass $activity
 * @return string
 */
function ep_activity_registration_label(stdClass $activity) {
    $format = get_string('strftimedatetimeshort');
    $start = empty($activity->registrationstart) ? null : userdate($activity->registrationstart, $format);
    $end = empty($activity->registrationend) ? null : userdate($activity->registrationend, $format);
    if ($start && $end) {
        return get_string('registrationperiod', 'mod_ep', (object) ['start' => $start, 'end' => $end]);
    }
    if ($start) {
        return get_string('registrationfrom', 'mod_ep', $start);
    }
    if ($end) {
        return get_string('registrationuntil', 'mod_ep', $end);
    }
    return '';
}

/**
 * Barème applicable aux inscriptions d'un EP du catalogue : le sien s'il en fixe un, sinon celui
 * de son type.
 *
 * @param stdClass $activity
 * @param stdClass|null $type Type de l'EP (lu si absent).
 * @return stdClass {grading, grademax, gradepass}
 */
function ep_activity_grading(stdClass $activity, $type = null) {
    global $DB;

    if (!empty($activity->grading)) {
        return (object) [
            'grading' => $activity->grading === EP_GRADING_GRADE ? EP_GRADING_GRADE : EP_GRADING_NONE,
            'grademax' => (float) $activity->grademax,
            'gradepass' => (float) $activity->gradepass,
        ];
    }
    $type = $type ?: $DB->get_record('ep_type', ['id' => $activity->typeid]);
    return (object) [
        'grading' => $type ? $type->grading : EP_GRADING_NONE,
        'grademax' => $type ? (float) $type->grademax : 20.0,
        'gradepass' => $type ? (float) $type->gradepass : 10.0,
    ];
}

/**
 * EP du catalogue exigés avant de s'inscrire à un EP.
 *
 * @param int $activityid
 * @return int[] Identifiants des EP exigés.
 */
function ep_get_activity_prerequisites($activityid) {
    global $DB;

    return array_map('intval', $DB->get_fieldset_select('ep_activity_prereq', 'requiredid', 'activityid = ?', [$activityid]));
}

/**
 * Remplace la liste des EP exigés avant de s'inscrire à un EP. Seuls les EP de la même instance
 * sont retenus, et jamais l'EP lui-même.
 *
 * @param stdClass $activity
 * @param int[] $requiredids
 * @return void
 */
function ep_set_activity_prerequisites(stdClass $activity, array $requiredids) {
    global $DB;

    $requiredids = array_values(array_diff(array_unique(array_map('intval', $requiredids)), [(int) $activity->id, 0]));
    if ($requiredids) {
        [$insql, $params] = $DB->get_in_or_equal($requiredids, SQL_PARAMS_NAMED, 'r');
        $requiredids = array_map('intval', $DB->get_fieldset_select(
            'ep_activity',
            'id',
            "epid = :epid AND id $insql",
            $params + ['epid' => $activity->epid]
        ));
    }
    $existing = ep_get_activity_prerequisites($activity->id);
    foreach (array_diff($existing, $requiredids) as $removed) {
        $DB->delete_records('ep_activity_prereq', ['activityid' => $activity->id, 'requiredid' => $removed]);
    }
    foreach (array_diff($requiredids, $existing) as $added) {
        $DB->insert_record('ep_activity_prereq', (object) [
            'activityid' => $activity->id,
            'requiredid' => $added,
            'timecreated' => time(),
        ]);
    }
}

/**
 * Prérequis qu'un étudiant ne remplit pas pour un EP : EP exigés sans inscription validée,
 * minimum d'ECTS déjà retenus non atteint.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param int $userid
 * @return string[] Libellés des prérequis manquants (vide : tout est rempli).
 */
function ep_get_missing_prerequisites(stdClass $ep, stdClass $activity, $userid) {
    global $DB;

    $missing = [];
    $requiredids = ep_get_activity_prerequisites($activity->id);
    if ($requiredids) {
        [$insql, $params] = $DB->get_in_or_equal($requiredids, SQL_PARAMS_NAMED, 'r');
        $validated = array_map('intval', $DB->get_fieldset_select(
            'ep_credit',
            'DISTINCT activityid',
            "userid = :userid AND status = :validated AND activityid $insql",
            $params + ['userid' => $userid, 'validated' => EP_STATUS_VALIDATED]
        ));
        $names = $DB->get_records_list('ep_activity', 'id', array_diff($requiredids, $validated), 'name', 'id, name');
        foreach ($names as $required) {
            $missing[] = get_string('prereqmissingactivity', 'mod_ep', format_string($required->name));
        }
    }
    if ($activity->minects > 0) {
        $retained = ep_get_student_progress($ep, $userid)->totalretained;
        if ($retained < (float) $activity->minects) {
            $missing[] = get_string('prereqmissingects', 'mod_ep', (object) [
                'required' => ep_format_ects($activity->minects),
                'retained' => ep_format_ects($retained),
            ]);
        }
    }
    return $missing;
}

/**
 * Prérequis manquants d'un étudiant pour une liste d'EP, en un nombre fixe de requêtes : c'est
 * la version de ep_get_missing_prerequisites() pour une page qui les affiche tous (le
 * catalogue), où l'appel EP par EP coûterait plusieurs requêtes par ligne.
 *
 * @param stdClass $ep
 * @param array $activities id => stdClass EP
 * @param int $userid
 * @return array activityid => string[] prérequis manquants (les EP sans manque n'y sont pas)
 */
function ep_get_missing_prerequisites_bulk(stdClass $ep, array $activities, $userid) {
    global $DB;

    if (!$activities) {
        return [];
    }
    $required = [];
    foreach (array_chunk(array_keys($activities), 500) as $chunk) {
        [$insql, $params] = $DB->get_in_or_equal($chunk);
        $rs = $DB->get_recordset_select('ep_activity_prereq', "activityid $insql", $params, '', 'id, activityid, requiredid');
        foreach ($rs as $row) {
            $required[(int) $row->activityid][] = (int) $row->requiredid;
        }
        $rs->close();
    }
    $validated = [];
    if ($required) {
        $validated = array_flip(array_map('intval', $DB->get_fieldset_select(
            'ep_credit',
            'DISTINCT activityid',
            'epid = :epid AND userid = :userid AND status = :validated AND activityid IS NOT NULL',
            ['epid' => $ep->id, 'userid' => $userid, 'validated' => EP_STATUS_VALIDATED]
        )));
    }
    $names = [];
    foreach ($activities as $activity) {
        $names[(int) $activity->id] = $activity->name;
    }
    $unknown = array_diff(array_unique(array_merge([], ...array_values($required))), array_keys($names));
    if ($unknown) {
        foreach ($DB->get_records_list('ep_activity', 'id', $unknown, '', 'id, name') as $row) {
            $names[(int) $row->id] = $row->name;
        }
    }
    $retained = null;
    $missing = [];
    foreach ($activities as $activity) {
        $list = [];
        foreach ($required[(int) $activity->id] ?? [] as $requiredid) {
            if (!isset($validated[$requiredid])) {
                $requiredname = format_string($names[$requiredid] ?? '');
                $list[$requiredname] = get_string('prereqmissingactivity', 'mod_ep', $requiredname);
            }
        }
        ksort($list);
        $list = array_values($list);
        if ($activity->minects > 0) {
            $retained = $retained ?? ep_get_student_progress($ep, $userid)->totalretained;
            if ($retained < (float) $activity->minects) {
                $list[] = get_string('prereqmissingects', 'mod_ep', (object) [
                    'required' => ep_format_ects($activity->minects),
                    'retained' => ep_format_ects($retained),
                ]);
            }
        }
        if ($list) {
            $missing[(int) $activity->id] = $list;
        }
    }
    return $missing;
}

/**
 * Prérequis manquants de plusieurs étudiants pour un même EP, en un nombre fixe de requêtes :
 * c'est la version de ep_get_missing_prerequisites() pour la liste des participants.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param int[] $userids
 * @return array userid => string[] prérequis manquants (les étudiants sans manque n'y sont pas)
 */
function ep_get_missing_prerequisites_for_users(stdClass $ep, stdClass $activity, array $userids) {
    global $DB;

    $userids = array_values(array_unique(array_map('intval', $userids)));
    $requiredids = ep_get_activity_prerequisites($activity->id);
    if (!$userids || (!$requiredids && $activity->minects <= 0)) {
        return [];
    }
    $validated = [];
    $names = [];
    if ($requiredids) {
        $names = $DB->get_records_list('ep_activity', 'id', $requiredids, 'name', 'id, name');
        [$reqsql, $reqparams] = $DB->get_in_or_equal($requiredids, SQL_PARAMS_NAMED, 'r');
        foreach (array_chunk($userids, 500) as $chunk) {
            [$usersql, $userparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');
            $rs = $DB->get_recordset_select(
                'ep_credit',
                "userid $usersql AND status = :validated AND activityid $reqsql",
                $reqparams + $userparams + ['validated' => EP_STATUS_VALIDATED],
                '',
                'id, userid, activityid'
            );
            foreach ($rs as $row) {
                $validated[(int) $row->userid][(int) $row->activityid] = true;
            }
            $rs->close();
        }
    }
    $progress = [];
    if ($activity->minects > 0) {
        \mod_ep\local\progress_cache::prime((int) $ep->id, $userids);
        try {
            foreach ($userids as $userid) {
                $progress[$userid] = ep_get_student_progress($ep, $userid)->totalretained;
            }
        } finally {
            \mod_ep\local\progress_cache::clear();
        }
    }
    $missing = [];
    foreach ($userids as $userid) {
        $list = [];
        foreach ($names as $required) {
            if (empty($validated[$userid][(int) $required->id])) {
                $list[] = get_string('prereqmissingactivity', 'mod_ep', format_string($required->name));
            }
        }
        if ($activity->minects > 0 && $progress[$userid] < (float) $activity->minects) {
            $list[] = get_string('prereqmissingects', 'mod_ep', (object) [
                'required' => ep_format_ects($activity->minects),
                'retained' => ep_format_ects($progress[$userid]),
            ]);
        }
        if ($list) {
            $missing[$userid] = $list;
        }
    }
    return $missing;
}

/**
 * Indique si un utilisateur gère les participants d'un EP du catalogue : la DEVE, ou un
 * responsable de cet EP (avec la capacité de valider).
 *
 * @param stdClass $rights Voir ep_get_user_rights().
 * @param stdClass $activity
 * @return bool
 */
function ep_rights_can_manage_participants(stdClass $rights, stdClass $activity) {
    if ($rights->validatedeve) {
        return true;
    }
    return $rights->evaluateteacher && in_array((int) $activity->id, $rights->responsibleids, true);
}

/**
 * Inscriptions à un EP du catalogue (tous statuts, ou ceux demandés), triées par nom d'étudiant.
 *
 * @param stdClass $activity
 * @param int[]|null $statuses
 * @return array id => stdClass crédit
 */
function ep_get_participants(stdClass $activity, ?array $statuses = null) {
    global $DB;

    $params = ['activityid' => $activity->id];
    $where = 'c.activityid = :activityid';
    if ($statuses !== null) {
        if (empty($statuses)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');
        $where .= " AND c.status $insql";
        $params += $inparams;
    }
    return $DB->get_records_sql(
        "SELECT c.*
           FROM {ep_credit} c
           JOIN {user} u ON u.id = c.userid
          WHERE $where
       ORDER BY u.lastname ASC, u.firstname ASC, c.timecreated DESC, c.id DESC",
        $params
    );
}

/**
 * Inscrit des étudiants à un EP du catalogue depuis la liste des participants, après la période
 * d'inscription ou à la place de l'étudiant. Sous le verrou de l'activité, chaque étudiant est
 * inscrit s'il n'a pas déjà d'inscription en cours et, sauf si $force est vrai, s'il reste une
 * place. Seule la DEVE peut dépasser le nombre de places. Les prérequis ne bloquent pas un ajout :
 * ils sont signalés dans l'interface, la décision appartient à celui qui inscrit.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param int[] $userids
 * @param int $byuserid
 * @param bool $force Dépasser le nombre de places (DEVE seulement : l'appelant le vérifie).
 * @return stdClass {added: int[] userid => creditid, skipped: array userid => clé du motif}
 */
function ep_add_participants(stdClass $ep, stdClass $activity, array $userids, $byuserid, $force = false) {
    global $DB;

    $result = (object) ['added' => [], 'skipped' => []];
    $lock = ep_get_activity_lock($ep->id);
    try {
        $activity = $DB->get_record('ep_activity', ['id' => $activity->id, 'epid' => $ep->id], '*', MUST_EXIST);
        $type = $DB->get_record('ep_type', ['id' => $activity->typeid], '*', MUST_EXIST);
        $taken = ep_get_activity_taken_places($activity->id);
        foreach (array_unique(array_map('intval', $userids)) as $userid) {
            if (ep_get_active_registration($activity->id, $userid)) {
                $result->skipped[$userid] = 'erroralreadyregistered';
                continue;
            }
            if (!$force && !empty($activity->capacity) && $taken >= (int) $activity->capacity) {
                $result->skipped[$userid] = 'errornoplaceleft';
                continue;
            }
            $result->added[$userid] = ep_create_credit($ep, $userid, $type, [
                'activityid' => $activity->id,
                'studyyear' => (int) $ep->currentstudyyear,
                'name' => $activity->name,
                'claimedects' => $activity->ects,
                'source' => EP_SOURCE_STUDENT,
                'addedby' => $byuserid,
            ]);
            $taken++;
        }
    } finally {
        $lock->release();
    }
    return $result;
}

/**
 * Corrige la note d'un crédit déjà tranché (DEVE seulement) : la note décide de nouveau de la
 * validation, selon le barème applicable. La correction est tracée dans l'historique.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param float $grade
 * @param string $comment
 * @return bool False si le crédit n'est plus tranché, n'est pas noté, ou si la note est hors barème.
 */
function ep_correct_grade(stdClass $credit, $byuserid, $grade, $comment = '') {
    $grading = ep_credit_grading($credit);
    $grade = round((float) $grade, 2);
    if ($grading->grading !== EP_GRADING_GRADE || $grade < 0 || $grade > $grading->grademax) {
        return false;
    }
    $passed = $grade >= $grading->gradepass;
    $credit->grade = $grade;
    $credit->status = $passed ? EP_STATUS_VALIDATED : EP_STATUS_REJECTED;
    $credit->retainedects = $passed ? (float) $credit->claimedects : 0;
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    [$insql, $params] = [
        'status IN (:validated, :rejected) AND source <> :stage',
        ['validated' => EP_STATUS_VALIDATED, 'rejected' => EP_STATUS_REJECTED, 'stage' => EP_SOURCE_STAGE],
    ];
    $applied = ep_update_credit_if(
        $credit,
        ['grade', 'status', 'retainedects', 'validatedby', 'validatetime', 'validatorcomment', 'timemodified'],
        $insql,
        $params
    );
    if ($applied) {
        ep_add_credit_decision($credit, EP_ROLE_DEVE, 'corrected', $byuserid, $grade, $credit->retainedects, $comment);
        ep_notify_credit_decided($credit);
        $eventclass = $passed ? \mod_ep\event\credit_validated::class : \mod_ep\event\credit_rejected::class;
        ep_trigger_credit_event($eventclass, $credit, [
            'role' => EP_ROLE_DEVE,
            'retainedects' => (float) $credit->retainedects,
            'corrected' => true,
        ]);
    }
    return $applied;
}

/**
 * Enregistre en une fois les décisions saisies sur la liste des participants d'un EP : notes,
 * validations, refus et, pour la DEVE, corrections de notes. Le tout se fait sous le verrou de
 * l'activité et dans une transaction ; chaque écriture reste conditionnée à l'état du crédit,
 * si bien qu'une décision prise entre-temps par un autre intervenant n'est pas écrasée et est
 * comptée à part.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param array $entries creditid => ['grade' => float|null, 'decision' => 'validate'|'reject'|'',
 *                       'comment' => string]
 * @param stdClass $rights Droits de l'auteur (voir ep_get_user_rights()).
 * @return stdClass {validated, rejected, corrected, changed, ignored}
 */
function ep_apply_participant_decisions(stdClass $ep, stdClass $activity, array $entries, stdClass $rights) {
    global $DB;

    $result = (object) ['validated' => 0, 'rejected' => 0, 'corrected' => 0, 'changed' => 0, 'ignored' => 0];
    $grading = ep_activity_grading($activity);
    $lock = ep_get_activity_lock($ep->id);
    $committed = false;
    ep_hold_notifications(true);
    try {
        $transaction = $DB->start_delegated_transaction();
        foreach ($entries as $creditid => $entry) {
            $credit = $DB->get_record('ep_credit', ['id' => (int) $creditid, 'activityid' => $activity->id]);
            $grade = isset($entry['grade']) && $entry['grade'] !== '' && $entry['grade'] !== null ? (float) $entry['grade'] : null;
            $decision = $entry['decision'] ?? '';
            $comment = (string) ($entry['comment'] ?? '');
            if (!$credit || ($grade === null && $decision === '')) {
                $result->ignored++;
                continue;
            }
            $status = (int) $credit->status;

            // Correction d'une note déjà tranchée : la DEVE seule.
            if ($status === EP_STATUS_VALIDATED || $status === EP_STATUS_REJECTED) {
                if (
                    $rights->validatedeve && $grade !== null && $grading->grading === EP_GRADING_GRADE
                        && ($credit->grade === null || (float) $credit->grade !== $grade)
                ) {
                    if (ep_correct_grade($credit, $rights->userid, $grade, $comment)) {
                        $result->corrected++;
                    } else {
                        $result->changed++;
                    }
                } else {
                    $result->ignored++;
                }
                continue;
            }
            if ($status !== EP_STATUS_PENDING || !ep_rights_can_validate($rights, $credit)) {
                $result->ignored++;
                continue;
            }

            $role = ep_rights_deciding_role($rights, $credit);
            if ($grading->grading === EP_GRADING_GRADE && $grade !== null) {
                if ($grade < 0 || $grade > $grading->grademax) {
                    $result->ignored++;
                    continue;
                }
                $passed = $grade >= $grading->gradepass;
                $applied = ep_grade_credit($credit, $rights->userid, $grade, $comment, $role);
            } else if ($decision === 'validate' && $grading->grading !== EP_GRADING_GRADE) {
                $passed = true;
                $applied = ep_validate_credit($credit, $rights->userid, $credit->claimedects, $comment, $role);
            } else if ($decision === 'reject') {
                $passed = false;
                $applied = ep_reject_credit($credit, $rights->userid, $comment, $role);
            } else {
                $result->ignored++;
                continue;
            }
            if (!$applied) {
                $result->changed++;
            } else if ($passed) {
                $result->validated++;
            } else {
                $result->rejected++;
            }
        }
        $transaction->allow_commit();
        $committed = true;
    } finally {
        $lock->release();
        $messages = ep_hold_notifications(false);
    }
    // Décisions enregistrées et verrou rendu : les étudiants sont prévenus. Rien n'est envoyé si
    // le lot a échoué (transaction annulée).
    if ($committed) {
        foreach ($messages as $message) {
            message_send($message);
        }
    }
    return $result;
}

/**
 * Clôt un EP du catalogue : il n'accepte plus d'inscription, reste au catalogue, et ses notes
 * restent saisissables. Les inscriptions encore en attente sont, au choix, laissées en attente,
 * validées d'office (EP sans note seulement : une note ne s'invente pas) ou non validées, dans
 * la limite de ce sur quoi l'auteur peut statuer.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param stdClass $rights
 * @param string $pendingaction 'keep', 'validate' ou 'reject'.
 * @return stdClass {validated, rejected, kept}
 */
function ep_close_activity(stdClass $ep, stdClass $activity, stdClass $rights, $pendingaction = 'keep') {
    global $DB;

    $DB->set_field('ep_activity', 'state', EP_ACTIVITY_CLOSED, ['id' => $activity->id]);
    $DB->set_field('ep_activity', 'timemodified', time(), ['id' => $activity->id]);
    $activity->state = EP_ACTIVITY_CLOSED;

    $grading = ep_activity_grading($activity);
    if ($pendingaction === 'validate' && $grading->grading === EP_GRADING_GRADE) {
        $pendingaction = 'keep';
    }
    $entries = [];
    $pending = ep_get_participants($activity, [EP_STATUS_PENDING]);
    if ($pendingaction !== 'keep') {
        foreach ($pending as $credit) {
            $entries[$credit->id] = [
                'decision' => $pendingaction,
                'comment' => get_string('closedactivitycomment', 'mod_ep'),
            ];
        }
    }
    $applied = $entries ? ep_apply_participant_decisions($ep, $activity, $entries, $rights)
        : (object) ['validated' => 0, 'rejected' => 0];
    return (object) [
        'validated' => $applied->validated,
        'rejected' => $applied->rejected,
        'kept' => count($pending) - $applied->validated - $applied->rejected,
    ];
}

/**
 * Champs texte d'un syllabus, dans l'ordre de la fiche ECTS (guide d'utilisation ECTS 2015) :
 * objectifs, acquis d'apprentissage, prérequis, contenu, méthodes, conditions d'examen,
 * seconde session, bibliographie. Ce sont ceux de la fiche de mod_syllabus.
 *
 * @return string[]
 */
function ep_syllabus_text_fields() {
    return ['objectives', 'outcomes', 'prerequisitestext', 'content', 'methods', 'assessment', 'resit', 'bibliography'];
}

/**
 * Natures de séance proposées dans le programme d'un EP.
 *
 * @return array code => libellé
 */
function ep_session_kind_options() {
    $options = [];
    foreach (['lecture', 'tutorial', 'practical', 'clinical', 'fieldwork', 'project', 'other'] as $kind) {
        $options[$kind] = get_string('sessionkind_' . $kind, 'mod_ep');
    }
    return $options;
}

// Fiche d'un EP : une activité « Syllabus du module » (mod_syllabus) du cours, gérée par l'EP
// (syllabus.component = mod_ep, syllabus.itemid = identifiant de l'EP). Rédaction, relecture par
// la DEVE, versions, PDF, compétences et avis des étudiants sont ceux de mod_syllabus ; l'EP en
// garde le lien (ep_activity.syllabuscmid), ses responsables et son état au catalogue.

/**
 * Instance de mod_syllabus qui porte la fiche d'un EP, avec son module de cours (cmid), ou null.
 *
 * @param int $activityid
 * @return stdClass|null
 */
function ep_activity_syllabus_record($activityid) {
    global $DB;

    $record = $DB->get_record_sql(
        "SELECT s.*, cm.id AS cmid
           FROM {syllabus} s
           JOIN {modules} m ON m.name = 'syllabus'
           JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id AND cm.deletioninprogress = 0
          WHERE s.component = 'mod_ep' AND s.itemid = :itemid",
        ['itemid' => $activityid],
        IGNORE_MULTIPLE
    );
    return $record ?: null;
}

/**
 * Fiche d'un EP (instance de mod_syllabus avec son cmid), créée au besoin : une activité
 * « Syllabus du module » dans la section des fiches d'EP du cours, ses responsables étant ceux
 * de l'EP. Les versions d'une fiche rédigée avant ce fonctionnement (tables ep_syllabus et
 * ep_session) y sont reprises.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @return stdClass
 */
function ep_ensure_activity_syllabus(stdClass $ep, stdClass $activity) {
    global $CFG, $DB;

    $existing = ep_activity_syllabus_record($activity->id);
    if ($existing) {
        return $existing;
    }
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/course/modlib.php');
    require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

    // Deux pages ouvertes en même temps ne créent pas deux fiches.
    $lock = \core\lock\lock_config::get_lock_factory('mod_ep_syllabus')->get_lock('activity-' . (int) $activity->id, 30);
    if (!$lock) {
        throw new moodle_exception('erroractivitybusy', 'mod_ep');
    }
    try {
        $existing = ep_activity_syllabus_record($activity->id);
        if ($existing) {
            return $existing;
        }
        $course = get_course($ep->course);
        $moduleinfo = (object) [
            'modulename' => 'syllabus',
            'course' => $course->id,
            'section' => ep_syllabus_section($course),
            'visible' => 1,
            'name' => $activity->name,
            'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'code' => $activity->code,
            'ects' => $activity->ects,
            'semester' => '',
            'studyyear' => (int) $activity->minstudyyear,
            'typeid' => 0,
            'evalopen' => 0,
            'evalclose' => 0,
            'component' => 'mod_ep',
            'itemid' => (int) $activity->id,
        ];
        // Les fiches restent hors de la page du cours quand le site le permet : on les lit
        // depuis le catalogue des EP.
        if (!empty($CFG->allowstealth)) {
            $moduleinfo->visibleoncoursepage = 0;
        }
        // Création au nom de l'EP, sans le droit de gérer les activités du cours qu'exige
        // create_module() : un enseignant qui propose un EP n'a pas à l'avoir.
        $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'syllabus'], MUST_EXIST);
        $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
        $DB->set_field('ep_activity', 'syllabuscmid', $moduleinfo->coursemodule, ['id' => $activity->id]);
        $activity->syllabuscmid = (int) $moduleinfo->coursemodule;
        $syllabus = ep_activity_syllabus_record($activity->id);
        ep_migrate_legacy_syllabus($activity, $syllabus);
        ep_sync_syllabus_teachers($activity);
        return $syllabus;
    } finally {
        $lock->release();
    }
}

/**
 * Numéro de la section du cours qui reçoit les fiches d'EP : celle d'une fiche déjà créée, sinon
 * une nouvelle section en fin de cours, nommée « Fiches des EP ».
 *
 * @param stdClass $course
 * @return int
 */
function ep_syllabus_section(stdClass $course) {
    global $DB;

    $section = $DB->get_field_sql(
        "SELECT cs.section
           FROM {syllabus} s
           JOIN {modules} m ON m.name = 'syllabus'
           JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id AND cm.deletioninprogress = 0
           JOIN {course_sections} cs ON cs.id = cm.section
          WHERE s.component = 'mod_ep' AND s.course = :course",
        ['course' => $course->id],
        IGNORE_MULTIPLE
    );
    if ($section !== false) {
        return (int) $section;
    }
    $created = course_create_section($course);
    course_update_section($course, $created, ['name' => get_string('syllabussection', 'mod_ep')]);
    return (int) $created->section;
}

/**
 * Reprend dans la fiche mod_syllabus d'un EP les versions de sa fiche d'avant (tables ep_syllabus
 * et ep_session), avec leur statut, leurs auteurs, leurs dates et leurs séances, puis les retire
 * de ces tables.
 *
 * @param stdClass $activity
 * @param stdClass $syllabus Instance de mod_syllabus de l'EP.
 * @return int Nombre de versions reprises.
 */
function ep_migrate_legacy_syllabus(stdClass $activity, stdClass $syllabus) {
    global $DB;

    $manager = $DB->get_manager();
    if (!$manager->table_exists('ep_syllabus')) {
        return 0;
    }
    $legacy = $DB->get_records('ep_syllabus', ['activityid' => $activity->id], 'version ASC, id ASC');
    if (!$legacy) {
        return 0;
    }
    $transaction = $DB->start_delegated_transaction();
    foreach ($legacy as $old) {
        $version = clone $old;
        unset($version->id, $version->activityid);
        $version->syllabusid = $syllabus->id;
        $version->language = (string) $version->language;
        $newid = $DB->insert_record('syllabus_version', $version);
        foreach ($DB->get_records('ep_session', ['syllabusid' => $old->id], 'sortorder ASC, id ASC') as $session) {
            unset($session->id);
            $session->versionid = $newid;
            unset($session->syllabusid);
            $DB->insert_record('syllabus_session', $session);
        }
        $DB->delete_records('ep_session', ['syllabusid' => $old->id]);
    }
    $DB->delete_records('ep_syllabus', ['activityid' => $activity->id]);
    $transaction->allow_commit();
    return count($legacy);
}

/**
 * Reporte sur la fiche d'un EP ses responsables (ceux qui peuvent l'être dans le cours).
 *
 * @param stdClass $activity
 * @return void
 */
function ep_sync_syllabus_teachers(stdClass $activity) {
    global $CFG;

    $syllabus = ep_activity_syllabus_record($activity->id);
    if (!$syllabus) {
        return;
    }
    require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
    $teachers = array_keys(ep_get_activity_teachers($activity->id));
    syllabus_set_teachers($syllabus, context_module::instance($syllabus->cmid), $teachers);
}

/**
 * Reporte sur la fiche d'un EP ses caractéristiques (intitulé, code, ECTS, année minimale).
 *
 * @param stdClass $activity
 * @return void
 */
function ep_sync_syllabus_details(stdClass $activity) {
    global $DB;

    $syllabus = ep_activity_syllabus_record($activity->id);
    if (!$syllabus) {
        return;
    }
    $update = ['name' => core_text::substr((string) $activity->name, 0, 255), 'code' => (string) $activity->code,
        'ects' => round((float) $activity->ects, 2), 'studyyear' => max(0, min(6, (int) $activity->minstudyyear))];
    $changed = array_filter($update, fn($value, $field) => (string) $syllabus->$field !== (string) $value, ARRAY_FILTER_USE_BOTH);
    if (!$changed) {
        return;
    }
    $DB->update_record('syllabus', (object) (['id' => $syllabus->id, 'timemodified' => time()] + $changed));
    if (isset($changed['name'])) {
        rebuild_course_cache($syllabus->course, true);
    }
}

/**
 * Dernière version de la fiche d'un EP dans l'un des statuts donnés, avec activityid (EP) en
 * plus des champs de syllabus_version.
 *
 * @param int $activityid
 * @param string[] $statuses
 * @return stdClass|null
 */
function ep_get_syllabus($activityid, array $statuses = ['published']) {
    global $DB;

    $syllabus = ep_activity_syllabus_record($activityid);
    if (!$syllabus) {
        return null;
    }
    [$insql, $params] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');
    $records = $DB->get_records_select(
        'syllabus_version',
        "syllabusid = :syllabusid AND status $insql",
        $params + ['syllabusid' => $syllabus->id],
        'version DESC, id DESC',
        '*',
        0,
        1
    );
    if (!$records) {
        return null;
    }
    $version = reset($records);
    $version->activityid = (int) $activityid;
    $version->cmid = (int) $syllabus->cmid;
    return $version;
}

/**
 * Version de travail de la fiche d'un EP : en rédaction, soumise ou renvoyée à son auteur.
 *
 * @param int $activityid
 * @return stdClass|null
 */
function ep_get_working_syllabus($activityid) {
    return ep_get_syllabus($activityid, ['draft', 'submitted', 'returned']);
}

/**
 * Versions publiées des fiches de plusieurs EP, en une requête.
 *
 * @param int[] $activityids
 * @return array activityid => version (avec activityid et cmid)
 */
function ep_get_published_syllabi(array $activityids) {
    global $DB;

    if (!$activityids) {
        return [];
    }
    [$insql, $params] = $DB->get_in_or_equal(array_values(array_map('intval', $activityids)), SQL_PARAMS_NAMED, 'act');
    $rows = $DB->get_records_sql(
        "SELECT v.*, s.itemid AS activityid, cm.id AS cmid
           FROM {syllabus} s
           JOIN {modules} m ON m.name = 'syllabus'
           JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = s.id AND cm.deletioninprogress = 0
           JOIN {syllabus_version} v ON v.syllabusid = s.id AND v.status = :published
          WHERE s.component = 'mod_ep' AND s.itemid $insql
       ORDER BY v.version ASC",
        $params + ['published' => 'published']
    );
    $result = [];
    foreach ($rows as $row) {
        $result[(int) $row->activityid] = $row;
    }
    return $result;
}

/**
 * Séances d'une version de fiche, dans l'ordre du programme.
 *
 * @param int $versionid
 * @return array id => stdClass
 */
function ep_get_syllabus_sessions($versionid) {
    global $DB;

    return $DB->get_records('syllabus_session', ['versionid' => $versionid], 'sortorder ASC, id ASC');
}

/**
 * Volume horaire d'un programme, par nature de séance et au total, et charge de travail totale
 * (séances et travail personnel).
 *
 * @param array $sessions
 * @param float $personalhours
 * @return stdClass {bykind: array kind => heures, sessions: float, personal: float, total: float}
 */
function ep_syllabus_hours(array $sessions, $personalhours = 0.0) {
    $bykind = [];
    $total = 0.0;
    foreach ($sessions as $session) {
        $bykind[$session->kind] = ($bykind[$session->kind] ?? 0) + (float) $session->duration;
        $total += (float) $session->duration;
    }
    return (object) [
        'bykind' => $bykind,
        'sessions' => round($total, 2),
        'personal' => round((float) $personalhours, 2),
        'total' => round($total + (float) $personalhours, 2),
    ];
}

/**
 * Indique si un utilisateur peut rédiger la fiche d'un EP : la DEVE (gestion du catalogue),
 * un responsable de l'EP, ou l'auteur d'une proposition encore à l'état de brouillon ou proposée.
 *
 * @param stdClass $activity
 * @param context $context
 * @param int|null $userid
 * @return bool
 */
function ep_can_edit_syllabus(stdClass $activity, context $context, $userid = null) {
    global $USER;

    $userid = $userid ?: $USER->id;
    if (has_capability('mod/ep:manage', $context, $userid)) {
        return true;
    }
    if (!has_capability('mod/ep:propose', $context, $userid)) {
        return false;
    }
    if (ep_is_activity_teacher($activity->id, $userid)) {
        return true;
    }
    return !empty($activity->proposedby) && (int) $activity->proposedby === (int) $userid
        && in_array($activity->state, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true);
}

/**
 * Crée la proposition d'un nouvel EP par un enseignant : un EP du catalogue à l'état de
 * brouillon, dont l'auteur devient responsable, et sa fiche (activité « Syllabus du module »),
 * que l'auteur rédige ensuite et soumet à la DEVE.
 *
 * @param stdClass $ep
 * @param stdClass $data Champs de l'EP (name, typeid, ects, capacity, minstudyyear, maxstudyyear,
 *                       description), au moins name et typeid.
 * @param int $authorid
 * @return stdClass {activityid, syllabuscmid}
 */
function ep_create_proposal(stdClass $ep, stdClass $data, $authorid) {
    global $DB;

    $types = ep_get_catalogable_types($ep->id);
    if (!isset($types[$data->typeid])) {
        throw new moodle_exception('errorinvalidtype', 'mod_ep');
    }
    $transaction = $DB->start_delegated_transaction();
    $record = clone $data;
    unset($record->id, $record->prerequisites);
    $record->state = EP_ACTIVITY_DRAFT;
    $activityid = ep_save_activity($ep, $record);
    $DB->set_field('ep_activity', 'proposedby', $authorid, ['id' => $activityid]);
    $current = array_keys(ep_get_activity_teachers($activityid));
    ep_set_activity_teachers($activityid, array_merge($current, [$authorid]));
    $transaction->allow_commit();

    $activity = $DB->get_record('ep_activity', ['id' => $activityid], '*', MUST_EXIST);
    $syllabus = ep_ensure_activity_syllabus($ep, $activity);
    return (object) ['activityid' => (int) $activityid, 'syllabuscmid' => (int) $syllabus->cmid];
}

/**
 * Met à jour les caractéristiques d'un EP proposé tant qu'il n'est pas publié (intitulé, type,
 * ECTS, places, années, description). Une fois publié, ces caractéristiques relèvent de la DEVE,
 * dans la gestion du catalogue.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param stdClass $data
 * @return bool False si l'EP n'est plus en brouillon ni proposé.
 */
function ep_update_proposal_activity(stdClass $ep, stdClass $activity, stdClass $data) {
    if (!in_array($activity->state, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true)) {
        return false;
    }
    $types = ep_get_catalogable_types($ep->id);
    $record = (object) ['id' => $activity->id];
    foreach (['name', 'ects', 'capacity', 'minstudyyear', 'maxstudyyear', 'description', 'code'] as $field) {
        if (property_exists($data, $field)) {
            $record->$field = $data->$field;
        }
    }
    if (!empty($data->typeid) && isset($types[$data->typeid])) {
        $record->typeid = $data->typeid;
    }
    ep_save_activity($ep, $record);
    return true;
}

/**
 * Suite d'une version soumise ou tranchée dans la fiche d'un EP (événements de mod_syllabus) :
 * une proposition soumise passe à l'état « proposé » ; publiée, elle entre au catalogue ;
 * renvoyée, elle redevient un brouillon ; refusée, elle est archivée. Un EP déjà publié garde son
 * état : seule sa fiche change.
 *
 * @param int $activityid
 * @param string $decision submit, publish, return ou reject.
 * @return string|null Nouvel état, ou null s'il ne change pas.
 */
function ep_apply_syllabus_decision($activityid, $decision) {
    global $DB;

    $activity = $DB->get_record('ep_activity', ['id' => $activityid]);
    if (!$activity || !in_array($activity->state, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true)) {
        return null;
    }
    $states = ['submit' => EP_ACTIVITY_PROPOSED, 'publish' => EP_ACTIVITY_PUBLISHED, 'return' => EP_ACTIVITY_DRAFT,
        'reject' => EP_ACTIVITY_ARCHIVED];
    $state = $states[$decision] ?? null;
    if ($state === null || $state === $activity->state) {
        return null;
    }
    $update = (object) ['id' => $activity->id, 'state' => $state, 'timemodified' => time()];
    if ($state === EP_ACTIVITY_PUBLISHED) {
        $update->timepublished = time();
    }
    $DB->update_record('ep_activity', $update);
    return $state;
}

/**
 * EP proposés ou dont la fiche est en cours de révision, qui concernent un utilisateur : pour la
 * DEVE, ceux dont une version lui est soumise ; pour un enseignant, ses propositions et les EP
 * dont il est responsable qui ont une version de travail.
 *
 * @param stdClass $ep
 * @param context $context
 * @param int $userid
 * @return array Liste de {activity, syllabus} : l'EP et sa version de travail (ou null).
 */
function ep_get_proposals(stdClass $ep, context $context, $userid) {
    $ismanager = has_capability('mod/ep:manage', $context, $userid);
    $rows = [];
    foreach (ep_get_activities($ep->id) as $activity) {
        $isnew = in_array($activity->state, [EP_ACTIVITY_DRAFT, EP_ACTIVITY_PROPOSED], true);
        $mine = ep_is_activity_teacher($activity->id, $userid) || (int) ($activity->proposedby ?? 0) === (int) $userid;
        $working = ep_get_working_syllabus($activity->id);
        if (!$working && !($isnew && $mine)) {
            continue;
        }
        // La DEVE n'a à voir que ce qui lui est soumis ; les brouillons restent à leurs auteurs.
        $submitted = $working && $working->status === 'submitted';
        if (!$mine && !($ismanager && $submitted)) {
            continue;
        }
        $rows[] = (object) ['activity' => $activity, 'syllabus' => $working];
    }
    usort($rows, fn($a, $b) => ((int) ($b->syllabus->timesubmitted ?? 0)) <=> ((int) ($a->syllabus->timesubmitted ?? 0)));
    return $rows;
}

/**
 * Courriels du module que la DEVE peut personnaliser, avec les variables que chacun accepte.
 * Le texte par défaut vient de la langue (clés notify_<clé>_subject et notify_<clé>_body).
 *
 * @return array clé => string[] variables
 */
function ep_notification_definitions() {
    return [
        'creditdecided' => ['student', 'ep', 'decision', 'ects', 'grade', 'comment', 'link'],
        'pendingreminder' => ['recipient', 'count', 'link'],
    ];
}

/**
 * Modèle d'un courriel pour une instance : celui personnalisé par la DEVE, sinon celui de la
 * langue.
 *
 * @param int $epid
 * @param string $key
 * @return stdClass {enabled, subject, body, custom}
 */
function ep_get_notification_template($epid, $key) {
    global $DB;

    $custom = $DB->get_record('ep_notification', ['epid' => $epid, 'msgkey' => $key]);
    $template = (object) [
        'enabled' => 1,
        'subject' => get_string('notify_' . $key . '_subject', 'mod_ep'),
        'body' => get_string('notify_' . $key . '_body', 'mod_ep'),
        'custom' => false,
    ];
    if ($custom) {
        $template->enabled = (int) $custom->enabled;
        if (trim($custom->subject) !== '') {
            $template->subject = $custom->subject;
            $template->custom = true;
        }
        if (trim((string) $custom->body) !== '') {
            $template->body = $custom->body;
            $template->custom = true;
        }
    }
    return $template;
}

/**
 * Enregistre la personnalisation d'un courriel ; un sujet et un corps vides rétablissent le texte
 * par défaut.
 *
 * @param int $epid
 * @param string $key
 * @param bool $enabled
 * @param string $subject
 * @param string $body
 * @return void
 */
function ep_set_notification_template($epid, $key, $enabled, $subject, $body) {
    global $DB;

    if (!array_key_exists($key, ep_notification_definitions())) {
        throw new coding_exception('Courriel inconnu : ' . $key);
    }
    $record = $DB->get_record('ep_notification', ['epid' => $epid, 'msgkey' => $key]);
    $data = (object) [
        'epid' => $epid,
        'msgkey' => $key,
        'enabled' => $enabled ? 1 : 0,
        'subject' => core_text::substr(trim($subject), 0, 255),
        'body' => trim($body),
        'timemodified' => time(),
    ];
    if ($record) {
        $data->id = $record->id;
        $DB->update_record('ep_notification', $data);
    } else {
        $DB->insert_record('ep_notification', $data);
    }
}

/**
 * Remplace les variables {nom} d'un modèle par leurs valeurs ; une variable inconnue est laissée
 * telle quelle, pour que la DEVE voie la faute de frappe plutôt qu'un blanc.
 *
 * @param string $text
 * @param array $vars nom => valeur
 * @return string
 */
function ep_render_template($text, array $vars) {
    return preg_replace_callback('/\{([a-z]+)\}/', function ($match) use ($vars) {
        return array_key_exists($match[1], $vars) ? (string) $vars[$match[1]] : $match[0];
    }, (string) $text);
}

/**
 * Met de côté les messages du module au lieu de les envoyer, puis rend ceux mis de côté : un
 * traitement en lot les envoie après avoir rendu le verrou de l'activité, plutôt que pendant
 * (l'envoi des courriels peut prendre plusieurs secondes pour une promotion entière).
 *
 * @param bool|null $hold Vrai pour commencer à mettre de côté, faux pour arrêter ; null pour
 *     seulement savoir si les messages sont mis de côté.
 * @param \core\message\message|null $message Message à mettre de côté.
 * @return array|bool Messages mis de côté (à l'arrêt), sinon vrai si les messages sont mis de côté.
 */
function ep_hold_notifications(?bool $hold, ?\core\message\message $message = null) {
    static $held = null;

    if ($message !== null) {
        $held[] = $message;
        return true;
    }
    if ($hold === null) {
        return $held !== null;
    }
    $messages = $held ?? [];
    $held = $hold ? [] : null;
    return $messages;
}

/**
 * Envoie un courriel du module (messagerie Moodle : l'utilisateur choisit dans ses préférences
 * s'il le reçoit par courriel, en notification, ou les deux). Rien n'est envoyé si la DEVE a
 * désactivé ce courriel pour l'instance. Dans une transaction, Moodle diffère l'envoi après la
 * validation : un message n'annonce jamais une décision annulée.
 *
 * @param stdClass $ep
 * @param string $key Voir ep_notification_definitions().
 * @param stdClass $touser
 * @param array $vars
 * @param moodle_url $url Page à laquelle le message renvoie.
 * @return bool Vrai si un message a été envoyé.
 */
function ep_send_notification(stdClass $ep, $key, stdClass $touser, array $vars, moodle_url $url) {
    $template = ep_get_notification_template($ep->id, $key);
    if (empty($template->enabled) || !empty($touser->deleted) || !empty($touser->suspended)) {
        return false;
    }
    $vars['link'] = $url->out(false);
    $subject = ep_render_template($template->subject, $vars);
    $body = ep_render_template($template->body, $vars);

    $message = new \core\message\message();
    $message->component = 'mod_ep';
    $message->name = $key;
    $message->userfrom = core_user::get_noreply_user();
    $message->userto = $touser;
    $message->subject = $subject;
    $message->fullmessage = $body;
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = text_to_html(s($body), false, false, true);
    $message->smallmessage = $subject;
    $message->notification = 1;
    $message->contexturl = $url->out(false);
    $message->contexturlname = format_string($ep->name);
    $message->courseid = $ep->course;
    if (ep_hold_notifications(null)) {
        return ep_hold_notifications(null, $message);
    }
    return (bool) message_send($message);
}

/**
 * Prévient l'étudiant qu'une décision finale a été prise sur sa demande (validation, refus,
 * correction de note).
 *
 * @param stdClass $credit Crédit, avec son statut final.
 * @return void
 */
function ep_notify_credit_decided(stdClass $credit) {
    global $DB;

    $ep = $DB->get_record('ep', ['id' => $credit->epid]);
    $cm = ep_get_cm_of_instance($credit->epid);
    $student = $DB->get_record('user', ['id' => $credit->userid]);
    if (!$ep || !$cm || !$student) {
        return;
    }
    $validated = (int) $credit->status === EP_STATUS_VALIDATED;
    ep_send_notification($ep, 'creditdecided', $student, [
        'student' => fullname($student),
        'ep' => format_string($credit->name),
        'decision' => get_string($validated ? 'decision_validated' : 'decision_rejected', 'mod_ep'),
        'ects' => $validated ? ep_format_ects($credit->retainedects) : '0',
        'grade' => $credit->grade !== null ? ep_format_ects($credit->grade) : '-',
        'comment' => (string) $credit->validatorcomment,
    ], new moodle_url('/mod/ep/validate.php', ['id' => $cm->id, 'creditid' => $credit->id]));
}

/**
 * Relance hebdomadaire : pour chaque utilisateur qui a des demandes en attente de sa décision
 * depuis plus de $olderthan secondes (responsables d'EP, enseignants référents, DEVE), un message
 * récapitulatif.
 *
 * @param stdClass $ep
 * @param int $olderthan
 * @return int Nombre de messages envoyés.
 */
function ep_send_pending_reminders(stdClass $ep, $olderthan = WEEKSECS) {
    global $DB;

    $cm = ep_get_cm_of_instance($ep->id);
    if (!$cm) {
        return 0;
    }
    $context = context_module::instance($cm->id);
    $limit = time() - $olderthan;
    $counts = [];

    // Responsables d'EP : inscriptions qui attendent le responsable.
    $rows = $DB->get_records_sql(
        "SELECT t.teacherid, COUNT(c.id) AS n
           FROM {ep_credit} c
           JOIN {ep_activity_teacher} t ON t.activityid = c.activityid
          WHERE c.epid = :epid AND c.status = :pending AND c.pendingrole = :owner AND c.timecreated < :limit
       GROUP BY t.teacherid",
        ['epid' => $ep->id, 'pending' => EP_STATUS_PENDING, 'owner' => EP_ROLE_OWNER, 'limit' => $limit]
    );
    foreach ($rows as $row) {
        $counts[(int) $row->teacherid] = ($counts[(int) $row->teacherid] ?? 0) + (int) $row->n;
    }

    // Enseignants référents, lus dans les activités « Gestion des stages » liées.
    $stageids = array_map(fn($instance) => (int) $instance->stage->id, ep_get_linked_stage_instances($ep));
    if ($stageids) {
        [$insql, $inparams] = $DB->get_in_or_equal($stageids, SQL_PARAMS_NAMED, 'st');
        $rows = $DB->get_records_sql(
            "SELECT et.teacherid, COUNT(DISTINCT c.id) AS n
               FROM {ep_credit} c
               JOIN {stage_entry_teacher} et ON et.studentid = c.userid AND et.stageid $insql
              WHERE c.epid = :epid AND c.status = :pending AND c.pendingrole = :referent AND c.timecreated < :limit
           GROUP BY et.teacherid",
            $inparams + ['epid' => $ep->id, 'pending' => EP_STATUS_PENDING, 'referent' => EP_ROLE_REFERENT, 'limit' => $limit]
        );
        foreach ($rows as $row) {
            $counts[(int) $row->teacherid] = ($counts[(int) $row->teacherid] ?? 0) + (int) $row->n;
        }
    }

    // DEVE : demandes qui l'attendent elle-même.
    $devecount = $DB->count_records_select(
        'ep_credit',
        'epid = :epid AND status = :pending AND pendingrole = :deve AND timecreated < :limit',
        ['epid' => $ep->id, 'pending' => EP_STATUS_PENDING, 'deve' => EP_ROLE_DEVE, 'limit' => $limit]
    );
    if ($devecount) {
        foreach (get_enrolled_users($context, 'mod/ep:validatedeve', 0, 'u.id') as $deve) {
            $counts[(int) $deve->id] = ($counts[(int) $deve->id] ?? 0) + $devecount;
        }
    }

    $sent = 0;
    $url = new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]);
    $users = $counts ? $DB->get_records_list('user', 'id', array_keys($counts)) : [];
    foreach ($counts as $userid => $count) {
        if (!isset($users[$userid]) || !is_enrolled($context, $userid, '', true)) {
            continue;
        }
        $sent += ep_send_notification($ep, 'pendingreminder', $users[$userid], [
            'recipient' => fullname($users[$userid]),
            'count' => $count,
        ], $url) ? 1 : 0;
    }
    return $sent;
}

/**
 * Écrit des champs d'un enregistrement à condition qu'il soit encore dans l'état attendu : même
 * principe que ep_update_credit_if(), pour les autres tables du module (éléments du supplément).
 *
 * @param string $table
 * @param stdClass $record Enregistrement, avec les nouvelles valeurs.
 * @param string[] $fields Champs à écrire.
 * @param string $where Condition SQL, paramètres nommés.
 * @param array $whereparams
 * @return bool False si l'enregistrement n'était plus dans l'état attendu.
 */
function ep_update_record_if($table, stdClass $record, array $fields, $where, array $whereparams) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $DB->execute("UPDATE {{$table}} SET timemodified = timemodified WHERE id = ?", [$record->id]);
    $applied = $DB->record_exists_select($table, "id = :recordid AND ($where)", ['recordid' => $record->id] + $whereparams);
    if ($applied) {
        $update = (object) ['id' => $record->id];
        foreach ($fields as $field) {
            if (property_exists($record, $field)) {
                $update->$field = $record->$field;
            }
        }
        $DB->update_record($table, $update);
    }
    $transaction->allow_commit();
    return $applied;
}

/**
 * Décisions d'année d'une instance.
 *
 * @param int $epid
 * @param int[]|null $userids Limite aux étudiants donnés.
 * @return array userid => [studyyear => stdClass]
 */
function ep_get_year_validations($epid, ?array $userids = null) {
    global $DB;

    $params = ['epid' => $epid];
    $where = 'epid = :epid';
    if ($userids !== null) {
        if (empty($userids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED, 'u');
        $where .= " AND userid $insql";
        $params += $inparams;
    }
    $validations = [];
    foreach ($DB->get_records_select('ep_year_validation', $where, $params) as $record) {
        $validations[(int) $record->userid][(int) $record->studyyear] = $record;
    }
    return $validations;
}

/**
 * Enregistre la décision de la DEVE sur l'année d'étude d'un étudiant (validée ou non), avec
 * les ECTS retenus pour cette année au moment de la décision. Une nouvelle décision remplace la
 * précédente.
 *
 * @param stdClass $ep
 * @param int $userid
 * @param int $studyyear
 * @param bool $validated
 * @param string $comment
 * @param int $byuserid
 * @return int Identifiant de la décision.
 */
function ep_set_year_validation(stdClass $ep, $userid, $studyyear, $validated, $comment, $byuserid) {
    global $DB;

    $progress = ep_get_student_progress($ep, $userid);
    $record = (object) [
        'epid' => $ep->id,
        'userid' => $userid,
        'studyyear' => (int) $studyyear,
        'decision' => $validated ? 1 : 0,
        'retainedects' => (float) ($progress->years[(int) $studyyear]->retained ?? 0),
        'comment' => (string) $comment,
        'decidedby' => $byuserid,
        'timedecided' => time(),
    ];
    $existing = $DB->get_record('ep_year_validation', ['epid' => $ep->id, 'userid' => $userid, 'studyyear' => (int) $studyyear]);
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('ep_year_validation', $record);
    } else {
        $record->id = $DB->insert_record('ep_year_validation', $record);
    }
    $cm = ep_get_cm_of_instance($ep->id);
    if ($cm) {
        \mod_ep\event\year_decided::create([
            'objectid' => $record->id,
            'context' => context_module::instance($cm->id),
            'relateduserid' => $userid,
            'other' => ['studyyear' => (int) $studyyear, 'decision' => (int) $record->decision],
        ])->trigger();
    }
    return (int) $record->id;
}

/**
 * Valide en lot l'année d'étude des étudiants donnés qui en ont atteint le minimum d'ECTS ; ceux
 * qui ne l'ont pas atteint sont laissés sans décision, sauf si $rejectincomplete est vrai.
 *
 * @param stdClass $ep
 * @param int $studyyear
 * @param int[] $userids
 * @param int $byuserid
 * @param bool $rejectincomplete Enregistrer « année non validée » pour les autres.
 * @return stdClass {validated, rejected, skipped}
 */
function ep_validate_years_in_bulk(stdClass $ep, $studyyear, array $userids, $byuserid, $rejectincomplete = false) {
    global $DB;

    // Un stage complémentaire que la DEVE vient de valider dans « Gestion des stages » doit compter.
    ep_sync_stage_credits_if_due($ep, true);

    $result = (object) ['validated' => 0, 'rejected' => 0, 'skipped' => 0];
    \mod_ep\local\progress_cache::prime((int) $ep->id, $userids);
    try {
        $transaction = $DB->start_delegated_transaction();
        foreach ($userids as $userid) {
            $progress = ep_get_student_progress($ep, $userid);
            $row = $progress->years[(int) $studyyear] ?? null;
            $done = $row ? $row->done : (ep_get_year_requirements($ep->id)[(int) $studyyear] ?? 0) <= 0;
            if ($done) {
                ep_set_year_validation($ep, $userid, $studyyear, true, '', $byuserid);
                $result->validated++;
            } else if ($rejectincomplete) {
                ep_set_year_validation($ep, $userid, $studyyear, false, get_string('yearminimumnotreached', 'mod_ep'), $byuserid);
                $result->rejected++;
            } else {
                $result->skipped++;
            }
        }
        $transaction->allow_commit();
    } finally {
        \mod_ep\local\progress_cache::clear();
    }
    return $result;
}

/**
 * Rentrée : l'année d'étude courante de l'activité passe à la suivante (6 au plus). Les
 * crédits, décisions et suppléments restent en place.
 *
 * @param stdClass $ep
 * @param int|null $from Année de départ confirmée par la DEVE (par défaut celle de $ep) : rien
 *     n'est écrit si l'activité n'y est plus, ce qui empêche une double rentrée (deux onglets,
 *     formulaire renvoyé).
 * @return int|false Nouvelle année courante, false si l'année avait déjà changé.
 */
function ep_rollover(stdClass $ep, ?int $from = null) {
    $from = $from ?? (int) $ep->currentstudyyear;
    $next = min(6, $from + 1);
    $record = (object) ['id' => $ep->id, 'currentstudyyear' => $next, 'timemodified' => time()];
    if (
        !ep_update_record_if(
            'ep',
            $record,
            ['currentstudyyear', 'timemodified'],
            'currentstudyyear = :from',
            ['from' => $from]
        )
    ) {
        return false;
    }
    $ep->currentstudyyear = $next;
    return $next;
}

/**
 * Catégories d'éléments proposées par défaut à une nouvelle instance ; la DEVE les renomme, les
 * désactive et en ajoute.
 *
 * @return array clé de langue => libellé anglais
 */
function ep_default_supplement_categories() {
    return [
        'suppcat_engagement' => 'Student engagement',
        'suppcat_representation' => 'Student representation',
        'suppcat_award' => 'Award or distinction',
        'suppcat_certification' => 'Certification',
        'suppcat_mobility' => 'Mobility',
        'suppcat_publication' => 'Publication or communication',
    ];
}

/**
 * Crée les catégories par défaut d'une instance qui n'en a aucune.
 *
 * @param int $epid
 * @return void
 */
function ep_create_default_supplement_categories($epid) {
    global $DB;

    if ($DB->record_exists('ep_supplement_category', ['epid' => $epid])) {
        return;
    }
    $order = 0;
    foreach (ep_default_supplement_categories() as $key => $english) {
        $order += 10;
        $DB->insert_record('ep_supplement_category', (object) [
            'epid' => $epid,
            'name' => get_string($key, 'mod_ep'),
            'nameen' => $english,
            'description' => '',
            'enabled' => 1,
            'sortorder' => $order,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}

/**
 * Catégories d'éléments du supplément d'une instance.
 *
 * @param int $epid
 * @param bool $onlyenabled
 * @return array id => stdClass
 */
function ep_get_supplement_categories($epid, $onlyenabled = false) {
    global $DB;

    $conditions = ['epid' => $epid];
    if ($onlyenabled) {
        $conditions['enabled'] = 1;
    }
    return $DB->get_records('ep_supplement_category', $conditions, 'sortorder ASC, name ASC');
}

/**
 * Enregistre une catégorie (création ou modification).
 *
 * @param int $epid
 * @param stdClass $data name, nameen, description, enabled, sortorder ; id pour une modification.
 * @return int
 */
function ep_save_supplement_category($epid, stdClass $data) {
    global $DB;

    $record = (object) [
        'epid' => $epid,
        'name' => core_text::substr(trim((string) ($data->name ?? '')), 0, 255),
        'nameen' => core_text::substr(trim((string) ($data->nameen ?? '')), 0, 255),
        'description' => (string) ($data->description ?? ''),
        'enabled' => empty($data->enabled) ? 0 : 1,
        'sortorder' => (int) ($data->sortorder ?? 0),
        'timemodified' => time(),
    ];
    if (!empty($data->id)) {
        $DB->get_record('ep_supplement_category', ['id' => $data->id, 'epid' => $epid], 'id', MUST_EXIST);
        $record->id = $data->id;
        $DB->update_record('ep_supplement_category', $record);
        return (int) $data->id;
    }
    $record->timecreated = time();
    if (empty($record->sortorder)) {
        $record->sortorder = 10 + (int) $DB->get_field('ep_supplement_category', 'MAX(sortorder)', ['epid' => $epid]);
    }
    return $DB->insert_record('ep_supplement_category', $record);
}

/**
 * Crée un élément demandé par un étudiant pour son supplément. Il attend la première étape du
 * circuit choisi par la DEVE pour l'instance, ou naît validé si ce circuit est vide.
 *
 * @param stdClass $ep
 * @param int $userid
 * @param stdClass $category
 * @param stdClass $data title, titleen, description, periodstart, periodend.
 * @return int Identifiant de l'élément.
 */
function ep_create_supplement_item(stdClass $ep, $userid, stdClass $category, stdClass $data) {
    global $DB;

    $steps = ep_normalise_flow($ep->supplementflow ?? EP_ROLE_DEVE, EP_ACQ_DECLARATION);
    $now = time();
    $record = (object) [
        'epid' => $ep->id,
        'userid' => $userid,
        'categoryid' => $category->id,
        'title' => core_text::substr(trim((string) $data->title), 0, 255),
        'titleen' => core_text::substr(trim((string) ($data->titleen ?? '')), 0, 255),
        'description' => (string) ($data->description ?? ''),
        'periodstart' => (int) ($data->periodstart ?? 0),
        'periodend' => (int) ($data->periodend ?? 0),
        'status' => $steps ? EP_STATUS_PENDING : EP_STATUS_VALIDATED,
        'step' => 0,
        'pendingrole' => $steps ? $steps[0] : '',
        'validatedby' => null,
        'validatetime' => $steps ? null : $now,
        'validatorcomment' => null,
        'timecreated' => $now,
        'timemodified' => $now,
    ];
    return $DB->insert_record('ep_supplement_item', $record);
}

/**
 * Modifie un élément encore en attente (par son auteur) ; rien n'est écrit s'il a été tranché
 * entre-temps.
 *
 * @param stdClass $item Élément, avec les nouvelles valeurs.
 * @return bool
 */
function ep_update_supplement_item(stdClass $item) {
    $item->timemodified = time();
    return ep_update_record_if(
        'ep_supplement_item',
        $item,
        ['categoryid', 'title', 'titleen', 'description', 'periodstart', 'periodend', 'timemodified'],
        // Seulement avant toute décision (voir ep_update_declaration()).
        'status = :pending AND step = 0',
        ['pending' => EP_STATUS_PENDING]
    );
}

/**
 * Indique si un utilisateur peut statuer sur un élément du supplément, à l'étape où il en est :
 * la DEVE toujours, un enseignant référent de l'étudiant à l'étape « référent ».
 *
 * @param stdClass $rights Voir ep_get_user_rights().
 * @param stdClass $item
 * @return bool
 */
function ep_rights_can_decide_supplement_item(stdClass $rights, stdClass $item) {
    if ((int) $item->status !== EP_STATUS_PENDING) {
        return false;
    }
    if ($rights->validatedeve) {
        return true;
    }
    return $rights->evaluateteacher && $item->pendingrole === EP_ROLE_REFERENT
        && in_array((int) $item->userid, $rights->referentids, true);
}

/**
 * Décision sur un élément du supplément : validation (à l'étape du rôle, ou définitive pour la
 * DEVE), ou refus (toujours définitif). Écriture conditionnée à l'étape lue.
 *
 * @param stdClass $ep
 * @param stdClass $item
 * @param int $byuserid
 * @param bool $validate
 * @param string $comment
 * @param string $role Rôle de l'auteur (referent ou deve).
 * @return bool False si l'élément n'était plus en attente à cette étape.
 */
function ep_decide_supplement_item(stdClass $ep, stdClass $item, $byuserid, $validate, $comment, $role) {
    $steps = ep_normalise_flow($ep->supplementflow ?? EP_ROLE_DEVE, EP_ACQ_DECLARATION);
    $next = (int) $item->step + 1;
    $final = !$validate || $role === EP_ROLE_DEVE || $next >= count($steps);

    $item->timemodified = time();
    if ($final) {
        $item->status = $validate ? EP_STATUS_VALIDATED : EP_STATUS_REJECTED;
        $item->pendingrole = '';
        $item->validatedby = $byuserid;
        $item->validatetime = time();
        // Une décision finale sans commentaire garde celui de l'étape précédente (le référent).
        if (trim((string) $comment) !== '' || empty($item->validatorcomment)) {
            $item->validatorcomment = $comment;
        }
    } else {
        $item->step = $next;
        $item->pendingrole = $steps[$next];
        // Le commentaire d'une étape intermédiaire est gardé pour l'étape suivante.
        if (trim((string) $comment) !== '') {
            $item->validatorcomment = $comment;
        }
    }
    $where = 'status = :pending';
    $params = ['pending' => EP_STATUS_PENDING];
    if ($role !== EP_ROLE_DEVE) {
        $where .= ' AND pendingrole = :role';
        $params['role'] = $role;
    }
    $applied = ep_update_record_if(
        'ep_supplement_item',
        $item,
        ['status', 'step', 'pendingrole', 'validatedby', 'validatetime', 'validatorcomment', 'timemodified'],
        $where,
        $params
    );
    if ($applied && $final) {
        ep_notify_supplement_item_decided($ep, $item);
    }
    return $applied;
}

/**
 * Retire un élément : l'étudiant tant qu'il est en attente, la DEVE à tout moment.
 *
 * @param stdClass $item
 * @param int $byuserid
 * @param string $role student ou deve.
 * @return bool
 */
function ep_cancel_supplement_item(stdClass $item, $byuserid, $role) {
    $item->status = EP_STATUS_CANCELLED;
    $item->pendingrole = '';
    $item->validatedby = $byuserid;
    $item->validatetime = time();
    $item->timemodified = time();
    $where = $role === EP_ROLE_STUDENT ? 'status = :expected' : 'status <> :expected';
    $expected = $role === EP_ROLE_STUDENT ? EP_STATUS_PENDING : EP_STATUS_CANCELLED;
    return ep_update_record_if(
        'ep_supplement_item',
        $item,
        ['status', 'pendingrole', 'validatedby', 'validatetime', 'timemodified'],
        $where,
        ['expected' => $expected]
    );
}

/**
 * Clause de sélection des éléments du supplément d'une instance, filtrés.
 *
 * @param int $epid
 * @param array $filters userid, status, pendingrole, userids (périmètre d'un référent), search
 *                       (nom de l'étudiant ; la requête doit joindre {user} sous l'alias u).
 * @return array|null [where, params], ou null si le filtre ne peut rien retenir.
 */
function ep_supplement_items_where($epid, array $filters) {
    global $DB;

    $where = ['i.epid = :epid'];
    $params = ['epid' => $epid];
    if (!empty($filters['userid'])) {
        $where[] = 'i.userid = :userid';
        $params['userid'] = (int) $filters['userid'];
    }
    if (isset($filters['status']) && $filters['status'] !== '') {
        $where[] = 'i.status = :status';
        $params['status'] = (int) $filters['status'];
    }
    if (!empty($filters['pendingrole'])) {
        $where[] = 'i.pendingrole = :pendingrole';
        $params['pendingrole'] = $filters['pendingrole'];
    }
    if (isset($filters['search']) && trim($filters['search']) !== '') {
        $where[] = $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':search', false, false);
        $params['search'] = '%' . $DB->sql_like_escape(trim($filters['search'])) . '%';
    }
    if (isset($filters['userids'])) {
        if (empty($filters['userids'])) {
            return null;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($filters['userids'], SQL_PARAMS_NAMED, 'u');
        $where[] = "i.userid $insql";
        $params += $inparams;
    }
    return [implode(' AND ', $where), $params];
}

/**
 * Nombre d'éléments du supplément d'une instance, avec les filtres de ep_get_supplement_items().
 *
 * @param int $epid
 * @param array $filters
 * @return int
 */
function ep_count_supplement_items($epid, array $filters = []) {
    global $DB;

    $clause = ep_supplement_items_where($epid, $filters);
    if ($clause === null) {
        return 0;
    }
    [$where, $params] = $clause;
    return (int) $DB->count_records_sql("SELECT COUNT(1) FROM {ep_supplement_item} i JOIN {user} u ON u.id = i.userid
        WHERE $where", $params);
}

/**
 * Éléments du supplément d'une instance, filtrés, triés par étudiant puis par période.
 *
 * @param int $epid
 * @param array $filters userid, status, pendingrole, userids (périmètre d'un référent).
 * @param int $limitfrom
 * @param int $limitnum 0 pour tous.
 * @return array id => stdClass
 */
function ep_get_supplement_items($epid, array $filters = [], $limitfrom = 0, $limitnum = 0) {
    global $DB;

    $clause = ep_supplement_items_where($epid, $filters);
    if ($clause === null) {
        return [];
    }
    [$where, $params] = $clause;
    return $DB->get_records_sql(
        "SELECT i.*
           FROM {ep_supplement_item} i
           JOIN {user} u ON u.id = i.userid
          WHERE $where
       ORDER BY u.lastname, u.firstname, i.periodstart, i.id",
        $params,
        $limitfrom,
        $limitnum
    );
}

/**
 * Prévient l'étudiant de la décision finale sur un élément de son supplément (même modèle de
 * courriel que pour une demande d'EP).
 *
 * @param stdClass $ep
 * @param stdClass $item
 * @return void
 */
function ep_notify_supplement_item_decided(stdClass $ep, stdClass $item) {
    global $DB;

    $cm = ep_get_cm_of_instance($ep->id);
    $student = $DB->get_record('user', ['id' => $item->userid]);
    if (!$cm || !$student) {
        return;
    }
    $validated = (int) $item->status === EP_STATUS_VALIDATED;
    ep_send_notification($ep, 'creditdecided', $student, [
        'student' => fullname($student),
        'ep' => format_string($item->title),
        'decision' => get_string($validated ? 'decision_validated' : 'decision_rejected', 'mod_ep'),
        'ects' => '-',
        'grade' => '-',
        'comment' => (string) $item->validatorcomment,
    ], new moodle_url('/mod/ep/supplement.php', ['id' => $cm->id]));
}

/**
 * Rubriques fixes du supplément au diplôme dont la DEVE rédige le texte, avec leur numéro dans
 * le modèle européen.
 *
 * @return array clé => numéro
 */
function ep_supplement_sections() {
    return [
        'qualification' => '2.1', 'fieldofstudy' => '2.2', 'institution' => '2.3', 'institutionadmin' => '2.4',
        'languages' => '2.5', 'level' => '3.1', 'length' => '3.2', 'access' => '3.3', 'studymode' => '4.1',
        'programmeoutcomes' => '4.2', 'gradingscheme' => '4.4', 'classification' => '4.5', 'furtherstudy' => '5.1',
        'professional' => '5.2', 'additionalinfo' => '6.1', 'othersources' => '6.2', 'signatoryname' => '7.2',
        'signatorytitle' => '7.3', 'national' => '8',
    ];
}

/**
 * Textes des rubriques fixes d'une instance, dans une langue.
 *
 * @param int $epid
 * @param string $lang fr ou en.
 * @return array clé => texte (vide si non renseigné)
 */
function ep_get_supplement_texts($epid, $lang = 'fr') {
    global $DB;

    $texts = array_fill_keys(array_keys(ep_supplement_sections()), '');
    foreach ($DB->get_records('ep_supplement_text', ['epid' => $epid, 'lang' => $lang]) as $record) {
        if (array_key_exists($record->section, $texts)) {
            $texts[$record->section] = (string) $record->content;
        }
    }
    return $texts;
}

/**
 * Enregistre le texte d'une rubrique fixe.
 *
 * @param int $epid
 * @param string $section
 * @param string $lang
 * @param string $content
 * @return void
 */
function ep_set_supplement_text($epid, $section, $lang, $content) {
    global $DB;

    if (!array_key_exists($section, ep_supplement_sections()) || !in_array($lang, ['fr', 'en'], true)) {
        throw new coding_exception('Rubrique ou langue inconnue');
    }
    $existing = $DB->get_record('ep_supplement_text', ['epid' => $epid, 'section' => $section, 'lang' => $lang]);
    $record = (object) ['epid' => $epid, 'section' => $section, 'lang' => $lang, 'content' => trim((string) $content),
        'timemodified' => time()];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('ep_supplement_text', $record);
    } else {
        $DB->insert_record('ep_supplement_text', $record);
    }
}

/**
 * Date de naissance d'un étudiant, pour la rubrique 1.3 : le champ de profil désigné par la DEVE
 * s'il est renseigné, à défaut celle saisie dans une demande de convention de mod_stage.
 *
 * @param stdClass $ep
 * @param int $userid
 * @return int|null Horodatage, ou null si elle n'est connue nulle part.
 */
function ep_get_student_birthdate(stdClass $ep, $userid) {
    global $CFG, $DB;

    if (!empty($ep->birthdatefield)) {
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $profile = profile_user_record($userid, false);
        $value = $profile->{$ep->birthdatefield} ?? null;
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $parsed = strtotime(str_replace('/', '-', trim($value)));
            if ($parsed) {
                return $parsed;
            }
        }
    }
    if ($DB->get_manager()->table_exists('stage_convention_detail')) {
        $value = $DB->get_field_sql(
            "SELECT MAX(d.studentbirthdate)
               FROM {stage_convention_detail} d
               JOIN {stage_entry} e ON e.id = d.entryid
              WHERE e.userid = :userid AND d.studentbirthdate IS NOT NULL AND d.studentbirthdate <> 0",
            ['userid' => $userid]
        );
        if ($value) {
            return (int) $value;
        }
    }
    return null;
}

/**
 * Rassemble le contenu du supplément au diplôme d'un étudiant : identité (rubrique 1), textes
 * de l'établissement (rubriques 2 à 8), enseignements personnalisés validés des types qui
 * figurent au supplément (4.3), décisions d'année, éléments validés (6.1), et ce qui manque pour
 * l'émettre.
 *
 * @param stdClass $ep
 * @param stdClass $user
 * @param string $lang fr ou en.
 * @return stdClass
 */
function ep_build_supplement(stdClass $ep, stdClass $user, $lang = 'fr') {
    global $DB;

    // Les EP de type stage figurent au supplément : ceux d'un stage validé juste avant aussi.
    ep_sync_stage_credits_if_due($ep, true);

    $types = ep_get_types($ep->id);
    $credits = [];
    $total = 0.0;
    $sql = "SELECT c.*, a.code AS activitycode
              FROM {ep_credit} c
         LEFT JOIN {ep_activity} a ON a.id = c.activityid
             WHERE c.epid = :epid AND c.userid = :userid AND c.status = :validated
          ORDER BY c.studyyear, c.validatetime, c.id";
    foreach ($DB->get_records_sql($sql, ['epid' => $ep->id, 'userid' => $user->id, 'validated' => EP_STATUS_VALIDATED]) as $c) {
        $type = $types[$c->typeid] ?? null;
        if (!$type || empty($type->insupplement)) {
            continue;
        }
        $credits[] = (object) [
            'name' => $c->name,
            'code' => (string) $c->activitycode,
            'type' => $type->name,
            'studyyear' => (int) $c->studyyear,
            'ects' => (float) $c->retainedects,
            'grade' => $c->grade !== null ? (float) $c->grade : null,
            'grademax' => $c->grade !== null ? ep_credit_grading($c, $type)->grademax : null,
            'date' => (int) $c->validatetime,
        ];
        $total += (float) $c->retainedects;
    }
    $progress = ep_get_student_progress($ep, $user->id);

    $categories = ep_get_supplement_categories($ep->id);
    $items = [];
    foreach (ep_get_supplement_items($ep->id, ['userid' => $user->id, 'status' => EP_STATUS_VALIDATED]) as $item) {
        $category = $categories[$item->categoryid] ?? null;
        $items[] = (object) [
            'category' => $category ? ($lang === 'en' && $category->nameen !== '' ? $category->nameen : $category->name) : '-',
            'title' => $lang === 'en' && $item->titleen !== '' ? $item->titleen : $item->title,
            'description' => (string) $item->description,
            'periodstart' => (int) $item->periodstart,
            'periodend' => (int) $item->periodend,
        ];
    }

    $years = [];
    foreach (ep_get_year_validations($ep->id, [$user->id])[$user->id] ?? [] as $year => $validation) {
        $years[$year] = (object) ['decision' => (int) $validation->decision, 'ects' => (float) $validation->retainedects,
            'date' => (int) $validation->timedecided];
    }
    ksort($years);

    $birthdate = ep_get_student_birthdate($ep, $user->id);
    $missing = [];
    if (!$birthdate) {
        $missing[] = get_string('supplementmissingbirthdate', 'mod_ep');
    }
    if (trim((string) $user->idnumber) === '') {
        $missing[] = get_string('supplementmissingidnumber', 'mod_ep');
    }
    $texts = ep_get_supplement_texts($ep->id, $lang);
    if (trim($texts['signatoryname']) === '') {
        $missing[] = get_string('supplementmissingsignatory', 'mod_ep');
    }

    return (object) [
        'lang' => $lang,
        'identity' => (object) [
            'lastname' => $user->lastname,
            'firstname' => $user->firstname,
            'birthdate' => $birthdate,
            'idnumber' => (string) $user->idnumber,
        ],
        'texts' => $texts,
        'credits' => $credits,
        'totalects' => round($total, 2),
        'retainedects' => (float) $progress->totalretained,
        'years' => $years,
        'items' => $items,
        'missing' => $missing,
    ];
}

/**
 * Empreinte du contenu certifié d'un supplément : SHA-256 d'une représentation stable des
 * données, du numéro et de la date d'émission. Imprimée sur le document et conservée avec
 * l'émission, elle permet de vérifier qu'un document présenté n'a pas été altéré.
 *
 * @param stdClass $data Voir ep_build_supplement().
 * @param string $number
 * @param int $timeissued
 * @return string 64 caractères hexadécimaux.
 */
function ep_supplement_verifycode(stdClass $data, $number, $timeissued) {
    $payload = json_encode([
        'number' => (string) $number,
        'time' => (int) $timeissued,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    return hash('sha256', $payload);
}

/**
 * Clé secrète du site pour sceller les suppléments émis, créée au premier usage. Elle reste dans
 * la configuration du site : ni la sauvegarde d'un cours ni l'export d'une activité ne la
 * contiennent.
 *
 * @return string
 */
function ep_supplement_seal_secret() {
    $secret = get_config('mod_ep', 'sealsecret');
    if (empty($secret)) {
        $secret = random_string(64);
        set_config('sealsecret', $secret, 'mod_ep');
    }
    return $secret;
}

/**
 * Sceau d'une émission : HMAC, avec la clé du site, de son numéro, de l'étudiant, de l'activité,
 * de son code de vérification, de sa date et de l'empreinte du PDF. Une émission insérée par une
 * autre voie qu'ep_issue_supplement() (restauration, import, base modifiée) n'a pas de sceau
 * valable et n'est pas reconnue par la page de vérification.
 *
 * @param stdClass $issue
 * @return string
 */
function ep_supplement_seal(stdClass $issue) {
    return hash_hmac('sha256', implode('|', [(string) $issue->number, (int) $issue->userid, (int) $issue->epid,
        (string) $issue->verifycode, (int) $issue->timeissued, (string) $issue->filehash]), ep_supplement_seal_secret());
}

/**
 * Numéro du prochain supplément d'une instance : préfixe choisi par la DEVE (ou « SD »), année,
 * instance et rang, à appeler sous le verrou de l'activité.
 *
 * @param stdClass $ep
 * @param int $time
 * @return string
 */
function ep_next_supplement_number(stdClass $ep, $time) {
    global $DB;

    $prefix = trim((string) ($ep->supplementprefix ?? '')) !== '' ? trim($ep->supplementprefix) : 'SD';
    $rank = 1 + $DB->count_records('ep_supplement_issue', ['epid' => $ep->id]);
    do {
        $number = sprintf('%s-%s-%d-%05d', $prefix, date('Y', $time), $ep->id, $rank++);
    } while ($DB->record_exists('ep_supplement_issue', ['number' => $number]));
    return $number;
}

/**
 * Émet le supplément au diplôme d'un étudiant : numéro, empreinte, document PDF certifié, conservé
 * dans la zone supplementissued ; l'émission précédente est marquée remplacée. Refusé tant qu'il
 * manque des informations obligatoires, sauf si $force est vrai.
 *
 * @param stdClass $ep
 * @param context $context Contexte du module.
 * @param int $userid
 * @param int $issuerid
 * @param string $lang
 * @param bool $force
 * @return stdClass {issue: stdClass|null, missing: string[]}
 */
function ep_issue_supplement(stdClass $ep, context $context, $userid, $issuerid, $lang = 'fr', $force = false) {
    global $DB;

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $data = ep_build_supplement($ep, $user, $lang);
    if ($data->missing && !$force) {
        return (object) ['issue' => null, 'missing' => $data->missing];
    }

    $lock = ep_get_activity_lock($ep->id);
    try {
        $time = time();
        $number = ep_next_supplement_number($ep, $time);
        $verifycode = ep_supplement_verifycode($data, $number, $time);
        $pdf = new \mod_ep\pdf\supplement_pdf($ep, $context, $lang);
        $pdf->add_supplement($data, $number, $verifycode, $time, false);
        $content = $pdf->Output('', 'S');

        $transaction = $DB->start_delegated_transaction();
        $issue = (object) [
            'epid' => $ep->id,
            'userid' => $userid,
            'number' => $number,
            'lang' => $lang,
            'verifycode' => $verifycode,
            'filehash' => sha1($content),
            'issuedby' => $issuerid,
            'timeissued' => $time,
            'replacedby' => null,
        ];
        $issue->seal = ep_supplement_seal($issue);
        $issue->id = $DB->insert_record('ep_supplement_issue', $issue);
        $DB->set_field_select(
            'ep_supplement_issue',
            'replacedby',
            $issue->id,
            'epid = :epid AND userid = :userid AND id <> :id AND replacedby IS NULL',
            ['epid' => $ep->id, 'userid' => $userid, 'id' => $issue->id]
        );
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_ep',
            'filearea' => EP_SUPPLEMENT_ISSUED_FILEAREA,
            'itemid' => $issue->id,
            'filepath' => '/',
            'filename' => clean_filename('supplement-' . $number . '.pdf'),
        ], $content);
        $transaction->allow_commit();
    } finally {
        $lock->release();
    }

    \mod_ep\event\supplement_issued::create([
        'objectid' => $issue->id,
        'context' => $context,
        'relateduserid' => $userid,
        'other' => ['number' => $number],
    ])->trigger();
    return (object) ['issue' => $issue, 'missing' => $data->missing];
}

/**
 * Émissions de supplément d'une instance (toutes, ou celles d'un étudiant), de la plus récente à
 * la plus ancienne.
 *
 * @param int $epid
 * @param int|null $userid
 * @param bool $onlycurrent Seulement celles qui ne sont pas remplacées.
 * @return array id => stdClass
 */
function ep_get_supplement_issues($epid, $userid = null, $onlycurrent = false) {
    global $DB;

    $where = 'epid = :epid';
    $params = ['epid' => $epid];
    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }
    if ($onlycurrent) {
        $where .= ' AND replacedby IS NULL';
    }
    return $DB->get_records_select('ep_supplement_issue', $where, $params, 'timeissued DESC, id DESC');
}

/**
 * Fichier PDF d'une émission de supplément.
 *
 * @param context $context
 * @param int $issueid
 * @return stored_file|null
 */
function ep_get_supplement_issue_file(context $context, $issueid) {
    $files = get_file_storage()->get_area_files($context->id, 'mod_ep', EP_SUPPLEMENT_ISSUED_FILEAREA, $issueid, 'id', false);
    return $files ? reset($files) : null;
}

/**
 * Vérifie un supplément présenté : son numéro et son code de vérification doivent correspondre à
 * une émission du site.
 *
 * @param string $number
 * @param string $code Code imprimé (complet, ou ses 16 premiers caractères).
 * @return stdClass|null L'émission, si elle correspond.
 */
function ep_verify_supplement($number, $code) {
    global $DB;

    $issue = $DB->get_record('ep_supplement_issue', ['number' => trim((string) $number)]);
    $code = core_text::strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $code));
    if (!$issue || strlen($code) < 16 || strpos($issue->verifycode, $code) !== 0) {
        return null;
    }
    // Seule une émission scellée par ce site, dont le PDF conservé est intact, est reconnue.
    if (empty($issue->seal) || !hash_equals(ep_supplement_seal($issue), $issue->seal)) {
        return null;
    }
    $cm = ep_get_cm_of_instance($issue->epid);
    $file = $cm ? ep_get_supplement_issue_file(context_module::instance($cm->id), $issue->id) : null;
    if (!$file || sha1($file->get_content()) !== $issue->filehash) {
        return null;
    }
    return $issue;
}

/**
 * Transfère le dossier d'un étudiant d'une activité « Enseignement personnalisé » à une autre du
 * site (redoublement, changement de promotion) : ses crédits avec leurs décisions et justificatifs,
 * ses éléments de supplément avec leurs preuves, ses décisions d'année. Les types sont rapprochés
 * par leur code (copiés dans la cible s'ils n'y existent pas), les EP du catalogue par leur code
 * puis leur intitulé (sinon le crédit garde son intitulé, sans EP), les catégories par leur
 * libellé (créées au besoin). Les suppléments déjà émis restent dans l'activité d'origine : ce
 * sont des documents datés.
 *
 * @param stdClass $source
 * @param stdClass $target
 * @param int $userid
 * @return stdClass {credits, items, years}
 */
function ep_transfer_student(stdClass $source, stdClass $target, $userid) {
    global $DB;

    $sourcecm = ep_get_cm_of_instance($source->id);
    $targetcm = ep_get_cm_of_instance($target->id);
    $sourcecontext = context_module::instance($sourcecm->id);
    $targetcontext = context_module::instance($targetcm->id);
    $fs = get_file_storage();
    $result = (object) ['credits' => 0, 'items' => 0, 'years' => 0];

    $first = min((int) $source->id, (int) $target->id);
    $second = max((int) $source->id, (int) $target->id);
    $lock1 = ep_get_activity_lock($first);
    $lock2 = null;
    try {
        $lock2 = ep_get_activity_lock($second);
        $transaction = $DB->start_delegated_transaction();

        // Crédits issus des stages : la cible les recalcule elle-même si elle lit les mêmes
        // activités « Gestion des stages » ; sinon ils deviennent des crédits saisis par la DEVE,
        // que sa synchronisation ne supprimera pas comme orphelins.
        $samestages = array_keys(ep_get_linked_stage_instances($source)) == array_keys(ep_get_linked_stage_instances($target));

        $targettypes = [];
        foreach (ep_get_types($target->id) as $type) {
            $targettypes[$type->code] = $type;
        }
        $targetactivities = ep_get_activities($target->id);
        $typemap = [];
        foreach (ep_get_types($source->id) as $type) {
            if (!isset($targettypes[$type->code])) {
                $copy = clone $type;
                unset($copy->id);
                $copy->epid = $target->id;
                $copy->id = $DB->insert_record('ep_type', $copy);
                $targettypes[$type->code] = $copy;
            }
            $typemap[(int) $type->id] = (int) $targettypes[$type->code]->id;
        }
        $activitymap = [];
        foreach (ep_get_activities($source->id) as $activity) {
            foreach ($targetactivities as $candidate) {
                $samecode = $activity->code !== '' && $candidate->code === $activity->code;
                $samename = $activity->code === '' && $candidate->name === $activity->name;
                if ($samecode || $samename) {
                    $activitymap[(int) $activity->id] = (int) $candidate->id;
                    break;
                }
            }
        }

        foreach ($DB->get_records('ep_credit', ['epid' => $source->id, 'userid' => $userid]) as $credit) {
            if ($credit->source === EP_SOURCE_STAGE) {
                if ($samestages) {
                    continue;
                }
                $credit->source = EP_SOURCE_DEVE;
                $credit->sourceref = 0;
            }
            $credit->epid = $target->id;
            $credit->typeid = $typemap[(int) $credit->typeid] ?? $credit->typeid;
            $credit->activityid = $credit->activityid ? ($activitymap[(int) $credit->activityid] ?? null) : null;
            if ((int) $credit->status === EP_STATUS_PENDING && $credit->pendingrole === EP_ROLE_OWNER && !$credit->activityid) {
                // Sans EP correspondant dans la cible, plus de responsable pour statuer : la DEVE.
                $credit->pendingrole = EP_ROLE_DEVE;
            }
            $DB->update_record('ep_credit', $credit);
            foreach ($fs->get_area_files($sourcecontext->id, 'mod_ep', EP_EVIDENCE_FILEAREA, $credit->id, 'id', false) as $file) {
                $fs->create_file_from_storedfile(['contextid' => $targetcontext->id], $file);
                $file->delete();
            }
            $result->credits++;
        }

        $targetcategories = [];
        foreach (ep_get_supplement_categories($target->id) as $category) {
            $targetcategories[core_text::strtolower($category->name)] = (int) $category->id;
        }
        $sourcecategories = ep_get_supplement_categories($source->id);
        foreach ($DB->get_records('ep_supplement_item', ['epid' => $source->id, 'userid' => $userid]) as $item) {
            $category = $sourcecategories[$item->categoryid] ?? null;
            $key = $category ? core_text::strtolower($category->name) : '';
            if (!isset($targetcategories[$key]) && $category) {
                $copy = clone $category;
                unset($copy->id);
                $copy->epid = $target->id;
                $targetcategories[$key] = $DB->insert_record('ep_supplement_category', $copy);
            }
            $item->epid = $target->id;
            $item->categoryid = $targetcategories[$key] ?? $item->categoryid;
            $DB->update_record('ep_supplement_item', $item);
            $proofs = $fs->get_area_files($sourcecontext->id, 'mod_ep', EP_SUPPLEMENT_EVIDENCE_FILEAREA, $item->id, 'id', false);
            foreach ($proofs as $file) {
                $fs->create_file_from_storedfile(['contextid' => $targetcontext->id], $file);
                $file->delete();
            }
            $result->items++;
        }

        foreach ($DB->get_records('ep_year_validation', ['epid' => $source->id, 'userid' => $userid]) as $validation) {
            $DB->delete_records('ep_year_validation', [
                'epid' => $target->id, 'userid' => $userid, 'studyyear' => $validation->studyyear,
            ]);
            $validation->epid = $target->id;
            $DB->update_record('ep_year_validation', $validation);
            $result->years++;
        }

        $transaction->allow_commit();
    } finally {
        if ($lock2) {
            $lock2->release();
        }
        $lock1->release();
    }
    return $result;
}

/**
 * Autres activités « Enseignement personnalisé » du site que l'utilisateur gère : destinations
 * possibles d'un transfert d'étudiant, sources d'une copie du paramétrage (même règle que
 * epsynthesis_get_available_ep_activities(), recopiée pour ne pas faire dépendre ce module de
 * mod_epsynthesis).
 *
 * @param int $excludecmid Module de l'activité courante.
 * @return array cmid => libellé « cours : activité »
 */
function ep_get_managed_instances(int $excludecmid): array {
    global $DB;

    $sql = "SELECT cm.id AS cmid, e.name AS epname, c.fullname AS coursename
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'ep'
              JOIN {ep} e ON e.id = cm.instance
              JOIN {course} c ON c.id = cm.course
             WHERE cm.id <> :excludecmid AND cm.deletioninprogress = 0
          ORDER BY c.fullname ASC, e.name ASC";
    $targets = [];
    foreach ($DB->get_records_sql($sql, ['excludecmid' => $excludecmid]) as $candidate) {
        $modcontext = context_module::instance($candidate->cmid, IGNORE_MISSING);
        if ($modcontext && has_capability('mod/ep:manage', $modcontext)) {
            $targets[(int) $candidate->cmid] = format_string($candidate->coursename) . get_string('labelsep', 'langconfig')
                . format_string($candidate->epname, true, ['context' => $modcontext]);
        }
    }
    return $targets;
}

/**
 * Exporte le paramétrage d'une activité, transférable vers une autre activité du même site ou
 * d'un autre site : réglages de l'instance, types, minimums annuels, catégories et textes du
 * supplément, modèles de courriels et, au choix, le catalogue avec ses syllabus publiés et ses
 * prérequis. Aucune donnée d'étudiant n'y figure ; les responsables d'EP et les intervenants
 * sont désignés par leur adresse électronique.
 *
 * @param stdClass $ep
 * @param bool $withcatalog Inclure le catalogue.
 * @return array Structure sérialisable en JSON.
 */
function ep_export_settings(stdClass $ep, $withcatalog = true) {
    global $DB;

    $strip = function ($record, array $drop) {
        $record = (array) $record;
        foreach ($drop as $field) {
            unset($record[$field]);
        }
        return $record;
    };
    $settings = [
        'format' => EP_SETTINGS_FORMAT,
        'exported' => time(),
        'source' => format_string($ep->name),
        'instance' => [
            'currentstudyyear' => (int) $ep->currentstudyyear,
            'mincursusects' => (float) $ep->mincursusects,
            'proposalsdirectpublish' => (int) ($ep->proposalsdirectpublish ?? 0),
            'pdfheader' => (string) ($ep->pdfheader ?? ''),
            'pdffooter' => (string) ($ep->pdffooter ?? ''),
            'supplementflow' => (string) ($ep->supplementflow ?? EP_ROLE_DEVE),
            'supplementevidence' => (int) ($ep->supplementevidence ?? EP_EVIDENCE_REQUIRED),
            'supplementprefix' => (string) ($ep->supplementprefix ?? ''),
            'birthdatefield' => (string) ($ep->birthdatefield ?? ''),
        ],
        'types' => [],
        'yearrequirements' => [],
        'supplementcategories' => [],
        'supplementtexts' => [],
        'notifications' => [],
        'activities' => [],
    ];
    foreach (ep_get_types($ep->id) as $type) {
        $settings['types'][] = $strip($type, ['id', 'epid', 'timecreated', 'timemodified']);
    }
    foreach (ep_get_year_requirements($ep->id) as $year => $ects) {
        $settings['yearrequirements'][] = ['studyyear' => $year, 'requiredects' => $ects];
    }
    foreach (ep_get_supplement_categories($ep->id) as $category) {
        $settings['supplementcategories'][] = $strip($category, ['id', 'epid', 'timecreated', 'timemodified']);
    }
    foreach ($DB->get_records('ep_supplement_text', ['epid' => $ep->id]) as $text) {
        $settings['supplementtexts'][] = ['section' => $text->section, 'lang' => $text->lang, 'content' => $text->content];
    }
    foreach ($DB->get_records('ep_notification', ['epid' => $ep->id]) as $notification) {
        $settings['notifications'][] = $strip($notification, ['id', 'epid', 'timemodified']);
    }

    if ($withcatalog) {
        $types = ep_get_types($ep->id);
        $activities = ep_get_activities($ep->id);
        $teachers = ep_get_activities_teachers($ep->id);
        foreach ($activities as $activity) {
            $row = $strip($activity, ['id', 'epid', 'typeid', 'proposedby', 'timecreated', 'timemodified', 'timepublished']);
            $row['typecode'] = $types[$activity->typeid]->code ?? '';
            $row['teachers'] = array_values(array_map(fn($user) => $user->email, $teachers[$activity->id] ?? []));
            $row['prerequisites'] = [];
            foreach (ep_get_activity_prerequisites($activity->id) as $requiredid) {
                $row['prerequisites'][] = [
                    'code' => $activities[$requiredid]->code ?? '',
                    'name' => $activities[$requiredid]->name ?? '',
                ];
            }
            $syllabus = ep_get_syllabus($activity->id);
            if ($syllabus) {
                $row['syllabus'] = $strip($syllabus, ['id', 'activityid', 'version', 'status', 'authorid', 'reviewerid',
                    'reviewcomment', 'timesubmitted', 'timereviewed', 'timecreated', 'timemodified']);
                $sessions = ep_get_syllabus_sessions($syllabus->id);
                $userids = array_filter(array_map(fn($s) => (int) $s->teacherid, $sessions));
                $users = $userids ? $DB->get_records_list('user', 'id', $userids, '', 'id, email') : [];
                $row['syllabus']['sessions'] = [];
                foreach ($sessions as $session) {
                    $entry = $strip($session, ['id', 'syllabusid', 'teacherid', 'timecreated']);
                    $entry['teacheremail'] = $session->teacherid ? ($users[$session->teacherid]->email ?? '') : '';
                    $row['syllabus']['sessions'][] = $entry;
                }
            }
            $settings['activities'][] = $row;
        }
    }
    return $settings;
}

/**
 * Importe un paramétrage exporté par ep_export_settings() dans une activité : réglages de
 * l'instance, types (rapprochés par leur code : mis à jour, ou créés), minimums annuels,
 * catégories (rapprochées par leur libellé), textes du supplément, modèles de courriels et, si
 * demandé, le catalogue (rapproché par code puis par intitulé : mis à jour, ou créé avec son
 * syllabus publié). Les responsables et intervenants sont retrouvés par leur adresse parmi les
 * comptes du site ; ceux qui n'y sont pas sont ignorés (l'intervenant garde son nom s'il en a un).
 *
 * @param stdClass $ep
 * @param array $settings
 * @param array $parts Parties à importer : instance, types, yearrequirements, supplement,
 *                     notifications, catalog.
 * @param int $userid Auteur de l'import (auteur des syllabus créés).
 * @return stdClass Compte rendu : nombre d'éléments créés et mis à jour par partie, avertissements.
 */
function ep_import_settings(stdClass $ep, array $settings, array $parts, $userid) {
    global $DB;

    if ((int) ($settings['format'] ?? 0) !== EP_SETTINGS_FORMAT) {
        throw new moodle_exception('errorsettingsformat', 'mod_ep');
    }
    $report = (object) ['created' => [], 'updated' => [], 'warnings' => []];
    $count = function ($kind, $created) use ($report) {
        $key = $created ? 'created' : 'updated';
        $report->{$key}[$kind] = ($report->{$key}[$kind] ?? 0) + 1;
    };
    $lock = ep_get_activity_lock($ep->id);
    try {
        $transaction = $DB->start_delegated_transaction();

        if (in_array('instance', $parts, true) && !empty($settings['instance'])) {
            $fields = ['mincursusects', 'proposalsdirectpublish', 'pdfheader', 'pdffooter', 'supplementflow',
                'supplementevidence', 'supplementprefix', 'birthdatefield'];
            $update = (object) ['id' => $ep->id, 'timemodified' => time()];
            // Mêmes contrôles que les formulaires de réglage : un fichier forgé ne doit pas
            // pouvoir écrire une valeur qu'ils refuseraient.
            $flows = \mod_ep\form\supplement_settings_form::flow_options();
            $birthfields = \mod_ep\form\supplement_settings_form::birthdate_field_options();
            $clean = [
                'mincursusects' => fn($v) => max(0, round((float) $v, 2)),
                'proposalsdirectpublish' => fn($v) => empty($v) ? 0 : 1,
                'pdfheader' => fn($v) => clean_param((string) $v, PARAM_TEXT),
                'pdffooter' => fn($v) => clean_param((string) $v, PARAM_TEXT),
                'supplementflow' => fn($v) => array_key_exists((string) $v, $flows) ? (string) $v : EP_ROLE_DEVE,
                'supplementevidence' => fn($v) => array_key_exists((int) $v, ep_evidence_options()) ? (int) $v
                    : EP_EVIDENCE_REQUIRED,
                'supplementprefix' => fn($v) => core_text::substr(clean_param(trim((string) $v), PARAM_ALPHANUMEXT), 0, 20),
                'birthdatefield' => fn($v) => array_key_exists((string) $v, $birthfields) ? (string) $v : '',
            ];
            foreach ($fields as $field) {
                if (array_key_exists($field, $settings['instance'])) {
                    $update->$field = $clean[$field]($settings['instance'][$field]);
                    $ep->$field = $update->$field;
                }
            }
            $DB->update_record('ep', $update);
            $count('instance', false);
        }

        if (in_array('types', $parts, true)) {
            foreach ($settings['types'] ?? [] as $row) {
                $existing = ep_get_type_by_code($ep->id, (string) ($row['code'] ?? ''));
                $data = (object) $row;
                $data->id = $existing ? $existing->id : 0;
                ep_save_type($ep->id, $data);
                $count('types', !$existing);
            }
        }

        if (in_array('yearrequirements', $parts, true)) {
            foreach ($settings['yearrequirements'] ?? [] as $row) {
                ep_set_year_requirement($ep->id, (int) $row['studyyear'], (float) $row['requiredects']);
                $count('yearrequirements', false);
            }
        }

        if (in_array('supplement', $parts, true)) {
            $categories = [];
            foreach (ep_get_supplement_categories($ep->id) as $category) {
                $categories[core_text::strtolower($category->name)] = $category->id;
            }
            foreach ($settings['supplementcategories'] ?? [] as $row) {
                $key = core_text::strtolower((string) $row['name']);
                $data = (object) $row;
                $data->id = $categories[$key] ?? 0;
                ep_save_supplement_category($ep->id, $data);
                $count('supplementcategories', empty($categories[$key]));
            }
            foreach ($settings['supplementtexts'] ?? [] as $row) {
                if (array_key_exists($row['section'], ep_supplement_sections()) && in_array($row['lang'], ['fr', 'en'], true)) {
                    ep_set_supplement_text($ep->id, $row['section'], $row['lang'], (string) $row['content']);
                    $count('supplementtexts', false);
                }
            }
        }

        if (in_array('notifications', $parts, true)) {
            foreach ($settings['notifications'] ?? [] as $row) {
                if (array_key_exists($row['msgkey'], ep_notification_definitions())) {
                    ep_set_notification_template(
                        $ep->id,
                        $row['msgkey'],
                        !empty($row['enabled']),
                        (string) $row['subject'],
                        (string) $row['body']
                    );
                    $count('notifications', false);
                }
            }
        }

        if (in_array('catalog', $parts, true)) {
            ep_import_settings_catalog($ep, $settings['activities'] ?? [], $userid, $count, $report);
        }

        $transaction->allow_commit();
    } finally {
        $lock->release();
    }
    return $report;
}

/**
 * Partie « catalogue » de ep_import_settings() : EP, responsables, syllabus publié et séances,
 * puis prérequis une fois tous les EP connus.
 *
 * @param stdClass $ep
 * @param array $rows
 * @param int $userid
 * @param callable $count
 * @param stdClass $report
 * @return void
 */
function ep_import_settings_catalog(stdClass $ep, array $rows, $userid, callable $count, stdClass $report) {
    global $DB;

    $types = [];
    foreach (ep_get_types($ep->id) as $type) {
        $types[$type->code] = $type;
    }
    $bycode = [];
    $byname = [];
    foreach (ep_get_activities($ep->id) as $activity) {
        if ($activity->code !== '') {
            $bycode[$activity->code] = $activity->id;
        }
        $byname[core_text::strtolower($activity->name)] = $activity->id;
    }
    $emailcache = [];
    $finduser = function ($email) use (&$emailcache, $DB) {
        $email = core_text::strtolower(trim((string) $email));
        if ($email === '') {
            return 0;
        }
        if (!array_key_exists($email, $emailcache)) {
            $users = $DB->get_records_select(
                'user',
                $DB->sql_equal('email', ':email', false) . ' AND deleted = 0',
                ['email' => $email],
                'id',
                'id',
                0,
                2
            );
            $emailcache[$email] = count($users) === 1 ? (int) reset($users)->id : 0;
        }
        return $emailcache[$email];
    };

    $imported = [];
    foreach ($rows as $row) {
        $type = $types[$row['typecode'] ?? ''] ?? null;
        if (!$type) {
            $report->warnings[] = get_string('importsettingsunknowntype', 'mod_ep', (object) [
                'name' => s($row['name'] ?? ''), 'type' => s($row['typecode'] ?? ''),
            ]);
            continue;
        }
        $code = (string) ($row['code'] ?? '');
        $existingid = ($code !== '' ? ($bycode[$code] ?? 0) : 0) ?: ($byname[core_text::strtolower($row['name'] ?? '')] ?? 0);
        $data = (object) array_diff_key($row, array_flip(['typecode', 'teachers', 'prerequisites', 'syllabus']));
        $data->id = $existingid;
        $data->typeid = $type->id;
        $activityid = ep_save_activity($ep, $data);
        $count('activities', !$existingid);
        $imported[] = [$activityid, $row['prerequisites'] ?? []];
        if ($code !== '') {
            $bycode[$code] = $activityid;
        }
        $byname[core_text::strtolower($data->name)] = $activityid;

        $teacherids = array_filter(array_map($finduser, $row['teachers'] ?? []));
        if ($teacherids) {
            ep_set_activity_teachers($activityid, array_merge(array_keys(ep_get_activity_teachers($activityid)), $teacherids));
        }
        if (count($teacherids) < count($row['teachers'] ?? [])) {
            $report->warnings[] = get_string('importsettingsmissingteachers', 'mod_ep', s($data->name));
        }

        if (!empty($row['syllabus'])) {
            $activity = $DB->get_record('ep_activity', ['id' => $activityid], '*', MUST_EXIST);
            $sessions = [];
            foreach ($row['syllabus']['sessions'] ?? [] as $session) {
                $session['teacherid'] = $finduser($session['teacheremail'] ?? '');
                $sessions[] = $session;
            }
            // Publié d'office : c'est la version en vigueur dans l'activité d'origine.
            ep_publish_syllabus_directly(
                $activity,
                (object) array_diff_key($row['syllabus'], ['sessions' => true]),
                $sessions,
                $userid
            );
            $count('syllabi', true);
        }
    }

    foreach ($imported as [$activityid, $prerequisites]) {
        $requiredids = [];
        foreach ($prerequisites as $required) {
            $id = (!empty($required['code']) ? ($bycode[$required['code']] ?? 0) : 0)
                ?: ($byname[core_text::strtolower($required['name'] ?? '')] ?? 0);
            if ($id) {
                $requiredids[] = $id;
            }
        }
        if ($requiredids) {
            ep_set_activity_prerequisites($DB->get_record('ep_activity', ['id' => $activityid]), $requiredids);
        }
    }
}

/**
 * Publie directement une version de la fiche d'un EP, sans soumission ni relecture : c'est le
 * cas d'un catalogue importé par la DEVE. La version de travail de sa fiche mod_syllabus (créée au
 * besoin, reprise de la version publiée) reçoit les champs fournis puis est publiée ; une version
 * soumise par l'enseignant et non encore tranchée est reprise et publiée de même, car l'import
 * fait foi. L'état de l'EP n'est pas modifié.
 *
 * @param stdClass $activity
 * @param stdClass $data Champs texte (voir ep_syllabus_text_fields()), language, personalhours :
 *                       seuls ceux fournis remplacent la version précédente.
 * @param array|null $sessions Séances {title, kind, duration, teacherid, teachername}, ou null pour
 *                       les garder.
 * @param int $userid Auteur de l'import.
 * @return int Identifiant de la version publiée.
 */
function ep_publish_syllabus_directly(stdClass $activity, stdClass $data, ?array $sessions, $userid) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/syllabus/locallib.php');
    $ep = $DB->get_record('ep', ['id' => $activity->epid], '*', MUST_EXIST);
    $syllabus = ep_ensure_activity_syllabus($ep, $activity);
    $version = syllabus_start_working($syllabus, (int) $userid);
    if ($version->status === SYLLABUS_STATUS_SUBMITTED) {
        $DB->set_field('syllabus_version', 'status', SYLLABUS_STATUS_DRAFT, ['id' => $version->id]);
        $version->status = SYLLABUS_STATUS_DRAFT;
    }
    syllabus_save_working($version, $data, $sessions, (int) $userid);
    syllabus_publish($syllabus, $DB->get_record('syllabus_version', ['id' => $version->id], '*', MUST_EXIST), (int) $userid, '');
    return (int) $version->id;
}

/**
 * Inscrit un import au journal de l'activité.
 *
 * @param stdClass $ep
 * @param string $kind catalog, sessions, credits, settings ou archive.
 * @param int $userid Auteur.
 * @param int $count Nombre de lignes ou d'éléments traités.
 * @param stdClass|array $report Compte rendu, conservé en JSON (sans les messages, qui peuvent être
 *                               nombreux : seul leur nombre est gardé).
 * @return int Identifiant de l'entrée.
 */
function ep_log_import(stdClass $ep, $kind, $userid, $count, $report) {
    global $DB;

    $summary = [];
    foreach ((array) $report as $key => $value) {
        if (is_array($value) && array_keys($value) === array_keys(array_values($value))) {
            // Liste de messages : seul leur nombre est gardé.
            $summary[$key] = count($value);
        } else if (is_array($value)) {
            $summary[$key] = array_filter($value, fn($v) => is_scalar($v) || $v === null);
        } else if (is_scalar($value) || $value === null) {
            $summary[$key] = $value;
        } else {
            $summary[$key] = array_filter((array) $value, fn($v) => is_scalar($v) || $v === null);
        }
    }
    return (int) $DB->insert_record('ep_import_log', (object) [
        'epid' => $ep->id,
        'kind' => core_text::substr($kind, 0, 20),
        'userid' => $userid,
        'itemcount' => (int) $count,
        'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        'timecreated' => time(),
    ]);
}

/**
 * Journal des imports d'une activité, du plus récent au plus ancien.
 *
 * @param int $epid
 * @param int $limit
 * @return array
 */
function ep_get_import_log($epid, $limit = 50) {
    global $DB;

    return $DB->get_records('ep_import_log', ['epid' => $epid], 'timecreated DESC, id DESC', '*', 0, $limit);
}

/**
 * Ligne d'export CSV sans formule : une cellule texte qui commence par = + - @ (ou une tabulation,
 * un retour chariot) reçoit une apostrophe, pour qu'un tableur ne l'exécute pas à l'ouverture.
 * Les versions récentes de Moodle le font aussi ; pas les plus anciennes que le module accepte.
 *
 * @param array $row
 * @return array
 */
function ep_csv_safe(array $row): array {
    foreach ($row as $key => $value) {
        if (is_string($value) && !is_numeric($value) && preg_match('/^\s*[=+\-@\t\r]/', $value)) {
            $row[$key] = "'" . $value;
        }
    }
    return $row;
}
