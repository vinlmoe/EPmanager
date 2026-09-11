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

// ---------------------------------------------------------------------------------------------
// Libellés et formats.

/**
 * Retourne le libellé lisible d'un statut de crédit.
 *
 * @param int $status
 * @return string
 */
function ep_status_label($status) {
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
    $options = [0 => get_string('studyyear_unspecified', 'mod_ep')];
    for ($year = 1; $year <= 6; $year++) {
        $options[$year] = get_string('studyyear_n', 'mod_ep', $year);
    }
    return $options;
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

// ---------------------------------------------------------------------------------------------
// Types d'enseignement personnalisé.

/**
 * Définition des six types d'enseignement personnalisé créés avec chaque instance. Le code est
 * l'identifiant stable auquel le reste du module se réfère (EP_TYPE_*) ; le libellé, les plafonds
 * et le barème restent modifiables par la DEVE (voir types.php).
 *
 * 'catalog' : les crédits de ce type viennent d'une inscription à un EP du catalogue interne, qui
 * porte son propre nombre d'ECTS, et sont validés par le responsable de cet EP.
 * 'autovalidate' : les crédits de ce type sont attribués sans vérification d'un enseignant — le
 * cas du type stage, alimenté par des stages que la DEVE a déjà validés dans mod_stage.
 *
 * @return array code => array{catalog: int, autovalidate: int, sortorder: int}
 */
function ep_default_type_definitions() {
    return [
        EP_TYPE_ACADEMIC => ['catalog' => 1, 'autovalidate' => 0, 'sortorder' => 10],
        EP_TYPE_STAGE => ['catalog' => 0, 'autovalidate' => 1, 'sortorder' => 20],
        EP_TYPE_ENGAGEMENT => ['catalog' => 0, 'autovalidate' => 0, 'sortorder' => 30],
        EP_TYPE_PROFESSIONAL => ['catalog' => 0, 'autovalidate' => 0, 'sortorder' => 40],
        EP_TYPE_SPORT => ['catalog' => 0, 'autovalidate' => 0, 'sortorder' => 50],
        EP_TYPE_EXTERNAL => ['catalog' => 0, 'autovalidate' => 0, 'sortorder' => 60],
    ];
}

/**
 * Crée les types manquants d'une instance. Appelée à la création de l'instance, et de nouveau à
 * l'ouverture de la page de paramétrage : une instance restaurée depuis une sauvegarde, ou créée
 * par une version antérieure, retrouve ainsi ses types sans intervention.
 *
 * @param int $epid
 * @return void
 */
function ep_create_default_types($epid) {
    global $DB;

    $existing = $DB->get_records_menu('ep_type', ['epid' => $epid], '', 'code, id');
    $now = time();

    foreach (ep_default_type_definitions() as $code => $definition) {
        if (isset($existing[$code])) {
            continue;
        }
        $DB->insert_record('ep_type', (object) [
            'epid' => $epid,
            'code' => $code,
            'name' => get_string('type_' . $code, 'mod_ep'),
            'description' => '',
            'enabled' => 1,
            'catalog' => $definition['catalog'],
            'autovalidate' => $definition['autovalidate'],
            'maxects' => 0,
            'maxectsperyear' => 0,
            'ectsperday' => 0,
            'sortorder' => $definition['sortorder'],
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}

/**
 * Types d'une instance, dans l'ordre d'affichage choisi par la DEVE.
 *
 * @param int $epid
 * @param bool $onlyenabled
 * @return array id => stdClass
 */
function ep_get_types($epid, $onlyenabled = false) {
    global $DB;

    $conditions = ['epid' => $epid];
    if ($onlyenabled) {
        $conditions['enabled'] = 1;
    }
    return $DB->get_records('ep_type', $conditions, 'sortorder ASC, name ASC');
}

/**
 * Type d'une instance repéré par son code (EP_TYPE_*).
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
 * Types dans lesquels un étudiant peut déclarer lui-même un EP : ceux qui sont activés, qui ne
 * passent pas par le catalogue (l'inscription s'y fait depuis le catalogue) et qui ne sont pas
 * attribués automatiquement (déclarer un stage à la main doublonnerait l'attribution automatique).
 *
 * @param int $epid
 * @return array id => stdClass
 */
function ep_get_declarable_types($epid) {
    return array_filter(ep_get_types($epid, true), function ($type) {
        return empty($type->catalog) && empty($type->autovalidate);
    });
}

/**
 * Types auxquels un EP du catalogue peut être rattaché : ceux qui sont activés et qui ne sont pas
 * attribués automatiquement. Rattacher un EP du catalogue à un type automatique donnerait ses
 * ECTS à l'étudiant dès son inscription, sans que le responsable ait rien à valider — exactement
 * ce que le catalogue est censé éviter.
 *
 * @param int $epid
 * @return array id => stdClass
 */
function ep_get_catalogable_types($epid) {
    return array_filter(ep_get_types($epid, true), function ($type) {
        return empty($type->autovalidate);
    });
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

// ---------------------------------------------------------------------------------------------
// Catalogue des EP internes et leurs responsables.

/**
 * EP du catalogue d'une instance.
 *
 * @param int $epid
 * @param bool $onlyvisible
 * @return array id => stdClass
 */
function ep_get_activities($epid, $onlyvisible = false) {
    global $DB;

    $conditions = ['epid' => $epid];
    if ($onlyvisible) {
        $conditions['visible'] = 1;
    }
    return $DB->get_records('ep_activity', $conditions, 'sortorder ASC, name ASC');
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

// ---------------------------------------------------------------------------------------------
// Minimums d'ECTS par année d'étude.

/**
 * Minimums d'ECTS définis par année d'étude pour une instance.
 *
 * @param int $epid
 * @return array studyyear => float
 */
function ep_get_year_requirements($epid) {
    global $DB;

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

// ---------------------------------------------------------------------------------------------
// Crédits : création, inscription, validation.

/**
 * Crédits d'un étudiant dans une instance, du plus récent au plus ancien.
 *
 * @param int $epid
 * @param int $userid
 * @return array id => stdClass
 */
function ep_get_student_credits($epid, $userid) {
    global $DB;

    return $DB->get_records('ep_credit', ['epid' => $epid, 'userid' => $userid], 'timecreated DESC');
}

/**
 * Crée un crédit. Un crédit dont le type est attribué automatiquement (autovalidate) naît validé,
 * sans validateur : c'est précisément ce qui le distingue des autres, personne n'a à le vérifier.
 *
 * @param stdClass $ep
 * @param int $userid
 * @param stdClass $type
 * @param array $data name, description, claimedects, studyyear, activityid, source, sourceref.
 * @return int Identifiant du crédit créé.
 */
function ep_create_credit(stdClass $ep, $userid, stdClass $type, array $data) {
    global $DB;

    $now = time();
    $claimed = round((float) ($data['claimedects'] ?? 0), 2);
    $auto = !empty($type->autovalidate);

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
        'source' => (string) ($data['source'] ?? EP_SOURCE_STUDENT),
        'sourceref' => (int) ($data['sourceref'] ?? 0),
        'validatedby' => null,
        'validatetime' => $auto ? $now : null,
        'validatorcomment' => null,
        'timecreated' => $now,
        'timemodified' => $now,
    ];

    return $DB->insert_record('ep_credit', $record);
}

/**
 * Inscrit un étudiant à un EP du catalogue. Le nombre d'ECTS demandé est celui de l'EP : il n'est
 * pas au choix de l'étudiant, c'est l'EP qui le porte.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param int $userid
 * @param int $studyyear
 * @return int Identifiant du crédit créé.
 */
function ep_register_to_activity(stdClass $ep, stdClass $activity, $userid, $studyyear) {
    global $DB;

    $type = $DB->get_record('ep_type', ['id' => $activity->typeid], '*', MUST_EXIST);

    return ep_create_credit($ep, $userid, $type, [
        'activityid' => $activity->id,
        'studyyear' => $studyyear,
        'name' => $activity->name,
        'description' => '',
        'claimedects' => $activity->ects,
        'source' => EP_SOURCE_STUDENT,
    ]);
}

/**
 * Valide un crédit et arrête les ECTS retenus. Les plafonds par type ne sont pas appliqués ici :
 * ce qui est validé reste acquis, c'est au décompte (voir ep_get_student_progress()) de ne
 * compter que ce que le plafond autorise, de façon à ce qu'un relèvement ultérieur du plafond
 * fasse ressortir des ECTS déjà validés sans avoir à les revalider un par un.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param float $retainedects
 * @param string $comment
 * @return void
 */
function ep_validate_credit(stdClass $credit, $byuserid, $retainedects, $comment = '') {
    global $DB;

    $credit->status = EP_STATUS_VALIDATED;
    $credit->retainedects = max(0, round((float) $retainedects, 2));
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    $DB->update_record('ep_credit', $credit);
}

/**
 * Refuse un crédit, avec le motif communiqué à l'étudiant.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param string $comment
 * @return void
 */
function ep_reject_credit(stdClass $credit, $byuserid, $comment) {
    global $DB;

    $credit->status = EP_STATUS_REJECTED;
    $credit->retainedects = 0;
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    $DB->update_record('ep_credit', $credit);
}

/**
 * Annule un crédit : désinscription de l'étudiant tant que son inscription n'est pas validée, ou
 * retrait par la DEVE. La ligne est conservée (état terminal) plutôt que supprimée, pour garder
 * la trace de ce qui a été demandé, et sa place sur l'EP du catalogue est libérée.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param string $comment
 * @return void
 */
function ep_cancel_credit(stdClass $credit, $byuserid, $comment = '') {
    global $DB;

    $credit->status = EP_STATUS_CANCELLED;
    $credit->retainedects = 0;
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    $DB->update_record('ep_credit', $credit);
}

/**
 * Détermine qui a le droit de statuer sur un crédit donné.
 *
 * Un crédit issu du catalogue relève de son responsable, et de lui seul : c'est lui qui sait si
 * l'étudiant a suivi son EP. Un crédit déclaré hors catalogue (engagement, expérience
 * professionnelle, sport, académique externe) relève de l'enseignant référent de l'étudiant, tel
 * qu'il est déjà défini dans l'activité « Gestion des stages » du même cours. Dans les deux cas,
 * la DEVE (mod/ep:validatedeve) peut statuer, notamment quand aucun référent n'est attribué.
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

    if (!empty($credit->activityid)) {
        return ep_is_activity_teacher($credit->activityid, $userid);
    }
    return in_array((int) $credit->userid, ep_get_referent_students($ep, $userid), true);
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

// ---------------------------------------------------------------------------------------------
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
    $sql = "SELECT DISTINCT u.id, u.*
              FROM {stage_entry_teacher} et
              JOIN {user} u ON u.id = et.teacherid
             WHERE et.stageid $insql AND et.studentid = :studentid
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
 * @param stdClass $ep
 * @return stdClass {created, updated, deleted}
 */
function ep_sync_stage_credits(stdClass $ep) {
    global $DB;

    $result = (object) ['created' => 0, 'updated' => 0, 'deleted' => 0];

    $type = ep_get_type_by_code($ep->id, EP_TYPE_STAGE);
    $existing = $DB->get_records('ep_credit', ['epid' => $ep->id, 'source' => EP_SOURCE_STAGE]);
    $byref = [];
    foreach ($existing as $credit) {
        $byref[(int) $credit->sourceref] = $credit;
    }

    $entries = [];
    if ($type && !empty($type->enabled) && $type->ectsperday > 0) {
        $entries = ep_get_eligible_stage_entries($ep);
    }

    $now = time();
    foreach ($entries as $entry) {
        $ects = round((float) $entry->retainedduration * (float) $type->ectsperday, 2);
        $name = get_string('stagecreditname', 'mod_ep', (object) [
            'theme' => $entry->themename !== null ? format_string($entry->themename) : '-',
            'structure' => $entry->structure !== null ? format_string($entry->structure) : '-',
        ]);
        $description = get_string('stagecreditdescription', 'mod_ep', (object) [
            'days' => $entry->retainedduration,
            'rate' => ep_format_ects($type->ectsperday),
            'activity' => format_string($entry->stagename),
        ]);

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
            $credit->validatetime = $now;
            $credit->timemodified = $now;
            $DB->update_record('ep_credit', $credit);
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
        $DB->delete_records('ep_credit', ['id' => $orphan->id]);
        $result->deleted++;
    }

    $DB->set_field('ep', 'timesynced', $now, ['id' => $ep->id]);
    $ep->timesynced = $now;

    return $result;
}

/**
 * Resynchronise les crédits de type stage si la dernière synchronisation date de plus de
 * EP_STAGE_SYNC_INTERVAL. Appelée à l'ouverture des pages qui affichent un décompte, pour qu'une
 * validation DEVE faite dans la foulée s'y voie sans attendre la tâche planifiée de la nuit, sans
 * pour autant relancer la synchronisation à chaque rafraîchissement de page.
 *
 * @param stdClass $ep
 * @return void
 */
function ep_sync_stage_credits_if_due(stdClass $ep) {
    if (time() - (int) $ep->timesynced < EP_STAGE_SYNC_INTERVAL) {
        return;
    }
    ep_sync_stage_credits($ep);
}

// ---------------------------------------------------------------------------------------------
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
    $students = ep_get_enrolled_students($context);
    if ($restrictuserids !== null) {
        $students = array_filter($students, function ($student) use ($restrictuserids) {
            return in_array((int) $student->id, $restrictuserids, true);
        });
    }

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
}

/**
 * Retourne les étudiants inscrits au cours (capacité de soumission) dans le contexte du module.
 *
 * @param context $context
 * @return array
 */
function ep_get_enrolled_students(context $context) {
    return get_enrolled_users($context, 'mod/ep:submit', 0, 'u.*', 'u.lastname, u.firstname');
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

// ---------------------------------------------------------------------------------------------
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
 * Recherche / tri des crédits, pour les listes de la DEVE et des enseignants.
 *
 * @param int $epid
 * @param array $filters ['search' => nom étudiant, 'typeid' => int, 'activityid' => int,
 *                        'status' => int, 'studyyear' => int|'' (0 = année non précisée)]
 * @param string $sort Une des clés de ep_credit_sort_options().
 * @param string $dir 'ASC' ou 'DESC'.
 * @param array|null $restrictuserids Si fourni, limite aux crédits de ces étudiants.
 * @param array|null $restrictactivityids Si fourni, ajoute aux crédits ci-dessus ceux portant sur
 *                                        ces EP du catalogue (responsable d'un EP mais pas
 *                                        référent de l'étudiant, cas courant).
 * @return array id => stdClass
 */
function ep_get_filtered_credits(
    $epid,
    array $filters = [],
    $sort = 'timecreated',
    $dir = 'DESC',
    ?array $restrictuserids = null,
    ?array $restrictactivityids = null
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

    // Périmètre d'un enseignant : ses étudiants (comme référent) et/ou les EP dont il est
    // responsable. Les deux ensembles vides signifient qu'il n'a rien à voir ici — et non qu'il
    // peut tout voir : la requête est alors court-circuitée.
    if ($restrictuserids !== null || $restrictactivityids !== null) {
        $scope = [];
        if (!empty($restrictuserids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($restrictuserids, SQL_PARAMS_NAMED, 'ru');
            $scope[] = "c.userid $insql";
            $params += $inparams;
        }
        if (!empty($restrictactivityids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($restrictactivityids, SQL_PARAMS_NAMED, 'ra');
            $scope[] = "c.activityid $insql";
            $params += $inparams;
        }
        if (empty($scope)) {
            return [];
        }
        $where[] = '(' . implode(' OR ', $scope) . ')';
    }

    $sortmap = [
        'student' => 'u.lastname, u.firstname',
        'type' => 't.sortorder, t.name',
        'name' => 'c.name',
        'ects' => 'c.claimedects',
        'status' => 'c.status',
        'timecreated' => 'c.timecreated',
    ];
    $sortcolumn = $sortmap[$sort] ?? $sortmap['timecreated'];
    $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT c.*
              FROM {ep_credit} c
              JOIN {user} u ON u.id = c.userid
         LEFT JOIN {ep_type} t ON t.id = c.typeid
             WHERE " . implode(' AND ', $where) . "
          ORDER BY $sortcolumn $dir, c.id $dir";

    return $DB->get_records_sql($sql, $params);
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
    if (!empty($credit->activityid)) {
        return in_array((int) $credit->activityid, $rights->responsibleids, true);
    }
    return in_array((int) $credit->userid, $rights->referentids, true);
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
 * @return array
 */
function ep_get_credits_awaiting($ep, stdClass $rights, $sort = 'timecreated', $dir = 'ASC') {
    $filters = ['status' => EP_STATUS_PENDING];

    if ($rights->validatedeve) {
        return ep_get_filtered_credits($ep->id, $filters, $sort, $dir);
    }

    $credits = ep_get_filtered_credits(
        $ep->id,
        $filters,
        $sort,
        $dir,
        $rights->referentids,
        $rights->responsibleids
    );

    // Un enseignant référent n'a pas à statuer sur une inscription à un EP du catalogue dont il
    // n'est pas responsable, même s'il est référent de l'étudiant : c'est le responsable de l'EP
    // qui sait si l'étudiant l'a suivi.
    return array_filter($credits, function ($credit) use ($rights) {
        return ep_rights_can_validate($rights, $credit);
    });
}

// ---------------------------------------------------------------------------------------------
// Rendu commun aux vues.

/**
 * Rend une série d'actions en petits boutons. Une entrée dont l'URL est nulle est ignorée, ce qui
 * permet aux appelants de décrire toutes les actions possibles et de laisser la condition
 * d'affichage à l'endroit où elle se lit.
 *
 * @param array $links libellé => moodle_url|null
 * @param string $class
 * @param string $empty Rendu si aucune action n'est disponible.
 * @return string
 */
function ep_render_actions(array $links, $class = 'btn btn-sm btn-secondary mr-1 mb-1', $empty = '-') {
    $out = '';
    foreach ($links as $label => $url) {
        if ($url === null) {
            continue;
        }
        $out .= html_writer::link($url, $label, ['class' => $class]);
    }
    return $out !== '' ? $out : $empty;
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
 * Badge « Atteint » / « À compléter », utilisé dans tous les bilans de l'étudiant.
 *
 * @param bool $done
 * @return string HTML
 */
function ep_render_status_badge($done) {
    return $done
        ? html_writer::span(get_string('objectivedone', 'mod_ep'), 'badge badge-success')
        : html_writer::span(get_string('objectivetodo', 'mod_ep'), 'badge badge-warning');
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
    }
    if (ep_rights_has_validation_scope($rights)) {
        $links[get_string('validation', 'mod_ep')] = new moodle_url('/mod/ep/credits.php', ['id' => $cm->id]);
    }
    if ($rights->viewall || !empty($rights->referentids)) {
        $links[get_string('pilotage', 'mod_ep')] = new moodle_url('/mod/ep/dashboard.php', ['id' => $cm->id]);
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
 * Formulaire de recherche / filtre au-dessus des listes de crédits. Les autres paramètres présents
 * dans $baseurl (id, mode...) sont préservés en champs cachés.
 *
 * @param moodle_url $baseurl URL courante, avec les valeurs de filtre déjà appliquées.
 * @param array $types Types proposés dans le filtre.
 * @param array $values ['search' => string, 'typeid' => int, 'status' => string, 'studyyear' => int]
 * @param bool $showstatus Affiche le filtre de statut (inutile sur une liste déjà restreinte).
 * @return string
 */
function ep_render_list_filters(moodle_url $baseurl, array $types, array $values, $showstatus = true) {
    $formurl = new moodle_url($baseurl);
    $formurl->remove_params('search', 'typeid', 'status', 'studyyear', 'tsort', 'tdir', 'page');

    $out = html_writer::start_tag('form', ['method' => 'get', 'action' => $formurl, 'class' => 'form-inline ep-filters mb-3']);
    foreach ($formurl->params() as $key => $value) {
        $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    $out .= html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'search', 'value' => s($values['search'] ?? ''),
        'placeholder' => get_string('searchstudent', 'mod_ep'), 'class' => 'form-control mr-2',
    ]);

    $typeoptions = [0 => get_string('alltypes', 'mod_ep')];
    foreach ($types as $type) {
        $typeoptions[$type->id] = format_string($type->name);
    }
    $out .= html_writer::select($typeoptions, 'typeid', $values['typeid'] ?? 0, false, ['class' => 'form-control mr-2']);

    // L'année non précisée (0) est une valeur de rattachement à part entière : le « toutes les
    // années » du filtre est donc la chaîne vide, pas 0.
    $yearoptions = ['' => get_string('allyears', 'mod_ep')] + ep_studyyear_options();
    $out .= html_writer::select(
        $yearoptions,
        'studyyear',
        $values['studyyear'] ?? '',
        false,
        ['class' => 'form-control mr-2']
    );

    if ($showstatus) {
        $statusoptions = ['' => get_string('allstatuses', 'mod_ep')] + ep_status_options();
        $out .= html_writer::select(
            $statusoptions,
            'status',
            $values['status'] ?? '',
            false,
            ['class' => 'form-control mr-2']
        );
    }

    $out .= html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('search'), 'class' => 'btn btn-secondary mr-2',
    ]);
    $out .= html_writer::link($formurl, get_string('resetfilters', 'mod_ep'), ['class' => 'btn btn-link']);
    $out .= html_writer::end_tag('form');

    return $out;
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
    $rows[] = [
        get_string('status', 'mod_ep'),
        html_writer::span(ep_status_label($credit->status), 'badge ' . ep_status_badgeclass($credit->status)),
    ];
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

    return html_writer::table($table);
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
        if (empty($credit->activityid)) {
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
        echo $OUTPUT->heading(get_string('yeartotals', 'mod_ep'), 4);
        $yeartable = new html_table();
        $yeartable->head = array_merge(
            [get_string('studyyear', 'mod_ep'), get_string('objective', 'mod_ep')],
            ep_progress_table_head()
        );
        foreach ($yearrows as $row) {
            $yearcell = html_writer::tag('strong', ep_studyyear_label($row->studyyear))
                . ' ' . ep_render_status_badge($row->done);
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
            html_writer::span(ep_status_label($credit->status), 'badge ' . ep_status_badgeclass($credit->status)),
            ep_render_credit_actions($credit, $cm, $rights, $isowner),
        ];
    }
    if (empty($table->data)) {
        echo $OUTPUT->notification(get_string('nocredits', 'mod_ep'), 'info');
    } else {
        echo html_writer::table($table);
    }
}
