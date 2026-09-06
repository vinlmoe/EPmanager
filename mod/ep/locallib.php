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
// ---------------------------------------------------------------------------------------------

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
        case EP_STATUS_ENROLLED:
            return get_string('status_enrolled', 'mod_ep');
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
        case EP_STATUS_ENROLLED:
            return 'badge-primary';
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
    foreach ([EP_STATUS_CANCELLED, EP_STATUS_REJECTED, EP_STATUS_PENDING, EP_STATUS_ENROLLED,
            EP_STATUS_VALIDATED] as $status) {
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
 * Année d'étude courante de la promotion. Elle est lue dans l'activité « Gestion des stages » du
 * même cours (stage->currentstudyyear), où elle est déjà tenue à jour d'une année sur l'autre :
 * la ressaisir ici ferait diverger les deux. Le paramètre de l'activité (ep->currentstudyyear) ne
 * sert que de repli, quand aucune activité « Gestion des stages » liée ne la renseigne.
 *
 * C'est cette année qui dit si un étudiant peut s'inscrire à un EP du catalogue (voir
 * ep_activity_open_to_year()) et quels minimums annuels lui sont déjà exigibles.
 *
 * @param stdClass $ep
 * @return int Année d'étude courante, 0 si elle n'est renseignée nulle part.
 */
function ep_get_current_studyyear(stdClass $ep) {
    $year = 0;
    foreach (ep_get_linked_stage_instances($ep) as $instance) {
        // Un cours peut porter plusieurs activités « Gestion des stages » (une d'archive, par
        // exemple) : la promotion reste la même, on retient l'année la plus avancée qu'elles
        // annoncent plutôt que celle de la première rencontrée.
        $year = max($year, (int) ($instance->stage->currentstudyyear ?? 0));
    }

    return $year ?: (int) $ep->currentstudyyear;
}

/**
 * Années d'étude qu'un étudiant peut choisir en rattachement d'un EP : l'année courante de
 * la promotion, la précédente (rattrapage) et la suivante (anticipation), comme dans mod_stage.
 * Tant que l'année courante n'est pas renseignée, toutes les années sont proposées.
 *
 * @param stdClass $ep
 * @return array int => libellé
 */
function ep_studyyear_selectable_options(stdClass $ep) {
    $currentyear = ep_get_current_studyyear($ep);
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
// ---------------------------------------------------------------------------------------------

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
            'ectsmode' => EP_ECTS_MODE_FREE,
            'ectsvalue' => 0,
            'sortorder' => $definition['sortorder'],
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}

/**
 * Règles de calcul du nombre d'ECTS demandés par une déclaration, proposées à la DEVE pour les
 * types que l'étudiant déclare lui-même.
 *
 * @return array code => libellé
 */
function ep_ects_mode_options() {
    return [
        EP_ECTS_MODE_FREE => get_string('ectsmode_free', 'mod_ep'),
        EP_ECTS_MODE_FLAT => get_string('ectsmode_flat', 'mod_ep'),
        EP_ECTS_MODE_WEEKLY => get_string('ectsmode_weekly', 'mod_ep'),
    ];
}

/**
 * Règle de calcul applicable à un type. Elle ne concerne que les types déclarés par l'étudiant :
 * un EP du catalogue porte ses propres ECTS et un type attribué automatiquement suit son barème
 * par jour de stage — quelle que soit la valeur enregistrée, ils s'en tiennent là.
 *
 * @param stdClass $type
 * @return string Un des EP_ECTS_MODE_*.
 */
function ep_type_ects_mode(stdClass $type) {
    if (!empty($type->catalog) || !empty($type->autovalidate)) {
        return EP_ECTS_MODE_FREE;
    }

    $mode = (string) ($type->ectsmode ?? EP_ECTS_MODE_FREE);

    return array_key_exists($mode, ep_ects_mode_options()) ? $mode : EP_ECTS_MODE_FREE;
}

/**
 * Nombre d'ECTS demandés par une déclaration, d'après la règle de son type : le nombre proposé
 * par l'étudiant, le forfait du type, ou les semaines déclarées multipliées par le barème.
 *
 * C'est la seule source du nombre demandé : ce que le formulaire a pu envoyer dans les champs qui
 * ne servent pas au mode retenu est ignoré, plutôt que d'accorder son forfait à qui aurait changé
 * la valeur d'un champ masqué.
 *
 * @param stdClass $type
 * @param float $claimedects Nombre proposé par l'étudiant (mode libre uniquement).
 * @param float $weeks Semaines déclarées (mode par semaine uniquement).
 * @return float
 */
function ep_type_claimed_ects(stdClass $type, $claimedects = 0, $weeks = 0) {
    switch (ep_type_ects_mode($type)) {
        case EP_ECTS_MODE_FLAT:
            return round(max(0, (float) $type->ectsvalue), 2);
        case EP_ECTS_MODE_WEEKLY:
            return round(max(0, (float) $weeks) * max(0, (float) $type->ectsvalue), 2);
        default:
            return round(max(0, (float) $claimedects), 2);
    }
}

/**
 * Nombre de semaines à conserver sur une déclaration : celui que l'étudiant a déclaré si son type
 * se compte à la semaine, zéro sinon — une durée sans effet sur le décompte n'a rien à faire dans
 * le dossier.
 *
 * @param stdClass $type
 * @param float $weeks
 * @return float
 */
function ep_type_declared_weeks(stdClass $type, $weeks) {
    if (ep_type_ects_mode($type) !== EP_ECTS_MODE_WEEKLY) {
        return 0;
    }
    return round(max(0, (float) $weeks), 2);
}

/**
 * Rappel de la règle d'un type, à afficher à l'étudiant qui déclare comme à la DEVE qui
 * paramètre : « forfait de 1 ECTS par déclaration », « 0,5 ECTS par semaine déclarée », ou
 * l'indication que le nombre reste à proposer.
 *
 * @param stdClass $type
 * @return string
 */
function ep_type_ects_rule_label(stdClass $type) {
    switch (ep_type_ects_mode($type)) {
        case EP_ECTS_MODE_FLAT:
            return get_string('ectsruleflat', 'mod_ep', ep_format_ects($type->ectsvalue));
        case EP_ECTS_MODE_WEEKLY:
            return get_string('ectsruleweekly', 'mod_ep', ep_format_ects($type->ectsvalue));
        default:
            return get_string('ectsrulefree', 'mod_ep');
    }
}

/**
 * Indique si la règle d'un type est utilisable en l'état : un forfait ou un barème par semaine
 * laissé à 0 ne donnerait que des déclarations à 0 ECTS, refusées à la saisie. La DEVE doit le
 * voir, et l'étudiant ne doit pas se heurter à un type qu'elle a oublié de régler.
 *
 * @param stdClass $type
 * @return bool
 */
function ep_type_ects_rule_is_set(stdClass $type) {
    if (ep_type_ects_mode($type) === EP_ECTS_MODE_FREE) {
        return true;
    }
    return (float) $type->ectsvalue > 0;
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
    return array_filter(ep_get_types($epid, true), function($type) {
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
    return array_filter(ep_get_types($epid, true), function($type) {
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

    // Ce qui change d'un type à l'autre au moment de choisir : ce qu'il rapporte, et le plafond
    // au-delà duquel il cesse de compter.
    $notes = [];
    if (ep_type_ects_mode($type) !== EP_ECTS_MODE_FREE) {
        $notes[] = ep_type_ects_rule_label($type);
    }
    if ($type->maxects > 0) {
        $notes[] = get_string('maxectsshort', 'mod_ep', ep_format_ects($type->maxects));
    }
    if (!empty($notes)) {
        $label .= ' (' . implode(' — ', $notes) . ')';
    }

    return $label;
}

// ---------------------------------------------------------------------------------------------
// Catalogue des EP internes et leurs responsables.
// ---------------------------------------------------------------------------------------------

/**
 * EP du catalogue propres à une instance (par opposition aux EP partagés, définis dans une
 * activité « Suivi de l'enseignement personnalisé » — voir ep_get_shared_activities()).
 *
 * @param int $epid
 * @param bool $onlyvisible
 * @return array id => stdClass
 */
function ep_get_activities($epid, $onlyvisible = false) {
    global $DB;

    $conditions = ['epid' => $epid, 'synthesiscmid' => 0];
    if ($onlyvisible) {
        $conditions['visible'] = 1;
    }
    return $DB->get_records('ep_activity', $conditions, 'sortorder ASC, name ASC');
}

/**
 * Indique si un EP du catalogue est partagé, c'est-à-dire défini dans une activité « Suivi de
 * l'enseignement personnalisé » et ouvert à toutes les promotions qu'elle suit, plutôt que propre
 * à une seule promotion.
 *
 * @param stdClass $activity
 * @return bool
 */
function ep_activity_is_shared(stdClass $activity) {
    return !empty($activity->synthesiscmid);
}

/**
 * Course-module de l'instance mod_ep donnée : les EP partagés sont rattachés aux synthèses par
 * course-module, pas par identifiant d'instance.
 *
 * @param stdClass $ep
 * @return stdClass|false
 */
function ep_get_cm(stdClass $ep) {
    return get_coursemodule_from_instance('ep', $ep->id, 0, false, IGNORE_MISSING);
}

/**
 * Activités « Suivi de l'enseignement personnalisé » qui suivent cette instance, identifiées par
 * leur course-module : ce sont elles qui peuvent lui proposer des EP partagés. La liaison est
 * celle que la synthèse a elle-même déclarée (epsynthesis_link) — l'instance mod_ep n'a rien à
 * paramétrer de son côté, et rien n'est écrit dans les tables de la synthèse.
 *
 * @param stdClass $ep
 * @return int[] Course-modules d'activités mod_epsynthesis, sans doublon.
 */
function ep_get_synthesis_cmids(stdClass $ep) {
    global $DB;

    // mod_epsynthesis est facultatif : sans lui, il n'y a pas d'EP partagé, et le catalogue se
    // limite aux EP propres à la promotion.
    if (!$DB->get_manager()->table_exists('epsynthesis_link')) {
        return [];
    }

    $cm = ep_get_cm($ep);
    if (!$cm) {
        return [];
    }

    $sql = "SELECT DISTINCT scm.id
              FROM {epsynthesis_link} l
              JOIN {epsynthesis} s ON s.id = l.synthesisid
              JOIN {course_modules} scm ON scm.instance = s.id
              JOIN {modules} m ON m.id = scm.module AND m.name = 'epsynthesis'
             WHERE l.epcmid = :epcmid";

    return array_map('intval', array_keys($DB->get_records_sql($sql, ['epcmid' => $cm->id])));
}

/**
 * EP partagés définis dans les synthèses données.
 *
 * @param int[] $synthesiscmids Voir ep_get_synthesis_cmids().
 * @param bool $onlyvisible
 * @return array id => stdClass
 */
function ep_get_shared_activities(array $synthesiscmids, $onlyvisible = false) {
    global $DB;

    if (empty($synthesiscmids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($synthesiscmids, SQL_PARAMS_NAMED, 'sc');
    $where = "synthesiscmid $insql";
    if ($onlyvisible) {
        $where .= ' AND visible = 1';
    }

    return $DB->get_records_select('ep_activity', $where, $params, 'sortorder ASC, name ASC');
}

/**
 * Catalogue complet proposé aux étudiants d'une instance : les EP propres à leur promotion et les
 * EP partagés des synthèses qui la suivent, présentés ensemble — l'étudiant n'a pas à savoir où
 * chaque EP a été défini.
 *
 * @param stdClass $ep
 * @param bool $onlyvisible
 * @return array id => stdClass
 */
function ep_get_catalog_activities(stdClass $ep, $onlyvisible = false) {
    $activities = ep_get_activities($ep->id, $onlyvisible)
        + ep_get_shared_activities(ep_get_synthesis_cmids($ep), $onlyvisible);

    uasort($activities, function($a, $b) {
        return [(int) $a->sortorder, core_text::strtolower($a->name)]
            <=> [(int) $b->sortorder, core_text::strtolower($b->name)];
    });

    return $activities;
}

/**
 * Charge un EP du catalogue en vérifiant qu'il est bien proposé à l'instance donnée : soit il lui
 * appartient, soit il est partagé par une synthèse qui la suit. Un identifiant forgé ne doit pas
 * permettre d'inscrire un étudiant à un EP d'une autre promotion.
 *
 * @param stdClass $ep
 * @param int $activityid
 * @return stdClass|null
 */
function ep_get_catalog_activity(stdClass $ep, $activityid) {
    global $DB;

    $activity = $DB->get_record('ep_activity', ['id' => $activityid]);
    if (!$activity) {
        return null;
    }
    if (!ep_activity_is_shared($activity)) {
        return (int) $activity->epid === (int) $ep->id ? $activity : null;
    }

    return in_array((int) $activity->synthesiscmid, ep_get_synthesis_cmids($ep), true) ? $activity : null;
}

/**
 * Tous les EP partagés de la plateforme, associés à la synthèse qui les définit
 * (id => synthesiscmid). Sert aux listes qui doivent distinguer, ligne à ligne, une inscription à
 * un EP partagé d'une inscription à un EP de promotion, et savoir où la traiter, sans une requête
 * par ligne. La liste est courte et ne change pas en cours de page : elle n'est chargée qu'une
 * fois.
 *
 * @return array activityid => synthesiscmid
 */
function ep_get_shared_activity_owners() {
    global $DB;

    static $owners = null;

    // Le cache ne vaut que pour la page en cours ; sous PHPUnit, où les données sont réinitialisées
    // entre deux tests sans que le processus change, il serait faux dès le second test.
    if ($owners === null || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
        $owners = array_map('intval', $DB->get_records_select_menu('ep_activity',
            'synthesiscmid > 0', null, '', 'id, synthesiscmid'));
    }
    return $owners;
}

/**
 * Charge un EP partagé en vérifiant qu'il est bien défini dans la synthèse donnée : sans cette
 * vérification, un identifiant forgé permettrait de modifier depuis une synthèse un EP défini
 * dans une autre.
 *
 * @param int $synthesiscmid Course-module de l'activité mod_epsynthesis.
 * @param int $activityid
 * @return stdClass|null
 */
function ep_get_shared_activity($synthesiscmid, $activityid) {
    global $DB;

    $activity = $DB->get_record('ep_activity',
        ['id' => $activityid, 'synthesiscmid' => $synthesiscmid]);

    return $activity ?: null;
}

/**
 * Crée ou met à jour un EP partagé défini dans une synthèse. Un EP partagé n'appartient à aucune
 * promotion (epid = 0) et ne désigne aucun type (typeid = 0) : c'est le type académique interne
 * de l'instance de chaque étudiant qui s'applique, avec les plafonds de sa promotion.
 *
 * @param int $synthesiscmid Course-module de l'activité mod_epsynthesis.
 * @param stdClass $data name, description, ects, minstudyyear, maxstudyyear, capacity, sortorder,
 *                      visible, et activityid pour une modification.
 * @return int Identifiant de l'EP enregistré.
 */
function ep_save_shared_activity($synthesiscmid, stdClass $data) {
    global $DB;

    $record = (object) [
        'epid' => 0,
        'synthesiscmid' => (int) $synthesiscmid,
        'typeid' => 0,
        'name' => $data->name,
        'description' => (string) ($data->description ?? ''),
        'ects' => round((float) $data->ects, 2),
        'minstudyyear' => (int) $data->minstudyyear,
        'maxstudyyear' => (int) $data->maxstudyyear,
        'capacity' => max(0, (int) $data->capacity),
        'sortorder' => (int) $data->sortorder,
        'visible' => !empty($data->visible) ? 1 : 0,
        'timemodified' => time(),
    ];

    if (!empty($data->activityid)) {
        $record->id = (int) $data->activityid;
        $DB->update_record('ep_activity', $record);
        return $record->id;
    }

    $record->timecreated = time();
    return $DB->insert_record('ep_activity', $record);
}

/**
 * Supprime un EP du catalogue et l'affectation de ses responsables. La suppression est refusée
 * dès qu'une inscription y porte : les crédits déjà accordés perdraient l'EP auquel ils se
 * rattachent. Fermer l'EP aux inscriptions (visible = 0) est ce qu'il faut faire dans ce cas.
 *
 * @param int $activityid
 * @return bool Faux si l'EP est utilisé et n'a donc pas été supprimé.
 */
function ep_delete_activity($activityid) {
    global $DB;

    if ($DB->record_exists('ep_credit', ['activityid' => $activityid])) {
        return false;
    }

    $DB->delete_records('ep_activity_teacher', ['activityid' => $activityid]);
    $DB->delete_records('ep_activity', ['id' => $activityid]);

    return true;
}

/**
 * Type d'EP sous lequel un EP du catalogue est porté au crédit d'un étudiant de l'instance
 * donnée. Un EP propre à la promotion désigne lui-même son type ; un EP partagé n'en désigne
 * aucun — les types sont propres à chaque instance — et relève du type académique interne de
 * l'instance de l'étudiant, c'est-à-dire de ses plafonds à lui.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @return stdClass|null Le type, ou null s'il n'existe pas (ou plus) dans cette instance.
 */
function ep_get_activity_type(stdClass $ep, stdClass $activity) {
    global $DB;

    if (ep_activity_is_shared($activity)) {
        return ep_get_type_by_code($ep->id, EP_TYPE_ACADEMIC) ?: null;
    }

    return $DB->get_record('ep_type', ['id' => $activity->typeid, 'epid' => $ep->id]) ?: null;
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
 * EP dont un utilisateur est responsable parmi ceux que propose une instance donnée : les EP
 * propres à cette promotion comme les EP partagés des synthèses qui la suivent.
 *
 * @param stdClass $ep
 * @param int $userid
 * @return array id => stdClass
 */
function ep_get_responsible_activities(stdClass $ep, $userid) {
    global $DB;

    $activities = ep_get_catalog_activities($ep);
    if (empty($activities)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal(array_keys($activities), SQL_PARAMS_NAMED, 'a');
    $params['userid'] = $userid;
    $responsible = $DB->get_fieldset_select('ep_activity_teacher', 'activityid',
        "activityid $insql AND teacherid = :userid", $params);

    return array_intersect_key($activities, array_flip(array_map('intval', $responsible)));
}

/**
 * Décompte des inscriptions d'un EP du catalogue, par état du circuit : demandées (en attente de
 * la décision du responsable), acceptées (l'étudiant suit l'EP, ses ECTS ne sont pas encore
 * acquis), validées (ECTS acquis), refusées et retirées.
 *
 * @param int $activityid
 * @return stdClass {pending, enrolled, validated, rejected, cancelled, taken}
 *                  'taken' : places occupées, c'est-à-dire inscriptions acceptées puis validées.
 */
function ep_get_activity_registration_counts($activityid) {
    global $DB;

    $sql = "SELECT status, COUNT(1) AS nb
              FROM {ep_credit}
             WHERE activityid = :activityid
          GROUP BY status";
    $rows = $DB->get_records_sql($sql, ['activityid' => $activityid]);

    $count = function($status) use ($rows) {
        return isset($rows[$status]) ? (int) $rows[$status]->nb : 0;
    };

    $counts = (object) [
        'pending' => $count(EP_STATUS_PENDING),
        'enrolled' => $count(EP_STATUS_ENROLLED),
        'validated' => $count(EP_STATUS_VALIDATED),
        'rejected' => $count(EP_STATUS_REJECTED),
        'cancelled' => $count(EP_STATUS_CANCELLED),
    ];
    $counts->taken = $counts->enrolled + $counts->validated;

    return $counts;
}

/**
 * Nombre de places occupées sur un EP du catalogue : les inscriptions que le responsable a
 * acceptées, et celles dont il a déjà validé les ECTS. Les inscriptions encore en attente n'en
 * occupent aucune — s'inscrire n'est pas limité au nombre de places, c'est le responsable qui
 * arbitre ensuite qui garde la sienne.
 *
 * @param int $activityid
 * @return int
 */
function ep_get_activity_taken_places($activityid) {
    return ep_get_activity_registration_counts($activityid)->taken;
}

/**
 * Places restantes sur un EP du catalogue, ou null s'il n'a pas de limite. Une valeur nulle ou
 * négative n'interdit pas d'accepter une inscription de plus : le nombre de places est un repère
 * donné au responsable, pas un verrou (voir ep_accept_registration()).
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
 * (demandée, acceptée ou validée). Une inscription annulée ou refusée n'en est pas une : elle
 * n'empêche pas de se réinscrire.
 *
 * @param int $activityid
 * @param int $userid
 * @return stdClass|false
 */
function ep_get_active_registration($activityid, $userid) {
    global $DB;

    [$insql, $inparams] = $DB->get_in_or_equal(
        [EP_STATUS_PENDING, EP_STATUS_ENROLLED, EP_STATUS_VALIDATED], SQL_PARAMS_NAMED, 'st');
    $records = $DB->get_records_select('ep_credit',
        "activityid = :activityid AND userid = :userid AND status $insql",
        ['activityid' => $activityid, 'userid' => $userid] + $inparams, 'timecreated DESC', '*', 0, 1);

    return $records ? reset($records) : false;
}

// ---------------------------------------------------------------------------------------------
// Minimums d'ECTS par année d'étude.
// ---------------------------------------------------------------------------------------------

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

    $value = $DB->get_field('ep_year_requirement', 'requiredects',
        ['epid' => $epid, 'studyyear' => (int) $studyyear]);

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
// ---------------------------------------------------------------------------------------------

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
 * @param array $data name, description, claimedects, weeks, studyyear, activityid, source,
 *                     sourceref. Le nombre d'ECTS demandés est celui que l'appelant a arrêté :
 *                     pour une déclaration, il vient de la règle du type (voir
 *                     ep_type_claimed_ects()).
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
        'weeks' => round((float) ($data['weeks'] ?? 0), 2),
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
 * L'inscription n'est pas limitée au nombre de places : tout étudiant à qui l'EP est ouvert peut
 * la demander, et c'est le responsable qui arbitre ensuite lesquelles il retient (voir
 * ep_accept_registration()). Elle ne donne aucun ECTS — ils sont validés à la fin de l'EP.
 *
 * @param stdClass $ep
 * @param stdClass $activity
 * @param int $userid
 * @param int $studyyear
 * @return int Identifiant du crédit créé.
 */
function ep_register_to_activity(stdClass $ep, stdClass $activity, $userid, $studyyear) {
    $type = ep_get_activity_type($ep, $activity);
    if (!$type) {
        throw new moodle_exception('errorinvalidtype', 'mod_ep');
    }

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
 * Accepte l'inscription d'un étudiant à un EP du catalogue : il y a sa place et le suit, sans
 * qu'aucun ECTS ne lui soit encore acquis — ils le seront à la fin de l'EP, quand le responsable
 * validera ce qu'il y a fait (voir ep_validate_credit()).
 *
 * Le nombre de places n'est pas vérifié ici : il est donné au responsable comme repère, à lui de
 * décider s'il accepte un étudiant de plus. Le refuser d'office l'obligerait à fermer l'EP ou à
 * en relever la capacité pour un cas particulier.
 *
 * @param stdClass $credit
 * @param int $byuserid
 * @param string $comment
 * @return void
 */
function ep_accept_registration(stdClass $credit, $byuserid, $comment = '') {
    global $DB;

    $credit->status = EP_STATUS_ENROLLED;
    $credit->retainedects = 0;
    $credit->validatedby = $byuserid;
    $credit->validatetime = time();
    $credit->validatorcomment = $comment;
    $credit->timemodified = time();

    $DB->update_record('ep_credit', $credit);
}

/**
 * Indique si un crédit vient d'une inscription à un EP du catalogue — par opposition à une
 * déclaration hors catalogue ou à une attribution automatique. Ce sont les seuls crédits à passer
 * par l'étape d'acceptation de l'inscription avant la validation des ECTS.
 *
 * @param stdClass $credit
 * @return bool
 */
function ep_credit_is_registration(stdClass $credit) {
    return !empty($credit->activityid);
}

/**
 * Indique si la décision attendue sur un crédit est l'acceptation de l'inscription — la première
 * étape du circuit académique — plutôt que la validation des ECTS.
 *
 * @param stdClass $credit
 * @return bool
 */
function ep_credit_is_registration_step(stdClass $credit) {
    return ep_credit_is_registration($credit) && (int) $credit->status === EP_STATUS_PENDING;
}

/**
 * Statuts sur lesquels une décision reste à prendre : une inscription demandée (l'accepter ou la
 * refuser) et une inscription acceptée (valider ses ECTS à la fin de l'EP, ou la refuser).
 *
 * @return int[]
 */
function ep_credit_open_statuses() {
    return [EP_STATUS_PENDING, EP_STATUS_ENROLLED];
}

/**
 * Indique si un crédit attend encore une décision.
 *
 * @param stdClass $credit
 * @return bool
 */
function ep_credit_awaits_decision(stdClass $credit) {
    return in_array((int) $credit->status, ep_credit_open_statuses(), true);
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
 * Un crédit issu du catalogue relève de son responsable, et de lui seul : c'est lui qui accepte
 * l'inscription, puis qui sait, à la fin de l'EP, si l'étudiant l'a suivi. Un crédit déclaré hors
 * catalogue (engagement, expérience professionnelle, sport, académique externe) relève de
 * l'enseignant référent de l'étudiant, tel qu'il est déjà défini dans l'activité « Gestion des
 * stages » du même cours. Dans les deux cas, la DEVE (mod/ep:validatedeve) peut statuer,
 * notamment quand aucun référent n'est attribué.
 *
 * Un EP partagé fait exception au rôle du cours : son responsable statue sur ses inscriptions
 * quelle que soit la promotion de l'étudiant, sans avoir de rôle dans le cours de celle-ci —
 * c'est ce que la DEVE lui délègue en le désignant responsable d'un EP ouvert à plusieurs
 * promotions.
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
    global $DB, $USER;

    $userid = $userid ?: $USER->id;

    if ($credit->source === EP_SOURCE_STAGE) {
        return false;
    }
    if (has_capability('mod/ep:validatedeve', $context, $userid)) {
        return true;
    }

    if (ep_credit_is_registration($credit)) {
        $activity = $DB->get_record('ep_activity', ['id' => $credit->activityid]);
        if (!$activity || !ep_is_activity_teacher($activity->id, $userid)) {
            return false;
        }
        // Un EP partagé s'adresse à plusieurs promotions : son responsable statue sur toutes ses
        // inscriptions, y compris celles d'étudiants d'un cours où il n'a lui-même aucun rôle —
        // c'est précisément ce que la DEVE lui délègue en le désignant responsable. Un EP propre
        // à une promotion reste soumis au droit de valider dans cette promotion.
        return ep_activity_is_shared($activity)
            || has_capability('mod/ep:evaluateteacher', $context, $userid);
    }

    if (!has_capability('mod/ep:evaluateteacher', $context, $userid)) {
        return false;
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
// ---------------------------------------------------------------------------------------------

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
    $studentids = $DB->get_fieldset_select('stage_entry_teacher', 'DISTINCT studentid',
        "stageid $insql AND teacherid = :teacherid", $inparams + ['teacherid' => $teacherid]);

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
            if ((float) $credit->retainedects === $ects && (int) $credit->studyyear === (int) $entry->studyyear
                    && $credit->name === $name && (int) $credit->status === EP_STATUS_VALIDATED
                    && (int) $credit->typeid === (int) $type->id) {
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
// ---------------------------------------------------------------------------------------------

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
        } else if (ep_credit_awaits_decision($credit)) {
            // Une inscription acceptée par le responsable compte ici, avec les demandes encore en
            // attente : l'étudiant suit l'EP, mais ses ECTS ne seront acquis qu'à la validation
            // de fin d'EP.
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
    $yearsdone = count(array_filter($dueyears, function($row) {
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
 * de la promotion (voir ep_get_current_studyyear()) et les précédentes. Les objectifs des années
 * à venir ne sont pas encore dus — les compter ferait apparaître en retard toute une promotion
 * qui est parfaitement à jour. Tant que l'année courante n'est pas renseignée, toutes les années
 * sont retenues.
 *
 * @param stdClass $ep
 * @param array $yearrows Bilans annuels de ep_get_student_progress().
 * @return array Sous-ensemble de $yearrows.
 */
function ep_filter_due_years(stdClass $ep, array $yearrows) {
    $currentyear = ep_get_current_studyyear($ep);
    if (empty($currentyear)) {
        return $yearrows;
    }
    return array_filter($yearrows, function($row) use ($currentyear) {
        return $row->studyyear <= $currentyear;
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
        $students = array_filter($students, function($student) use ($restrictuserids) {
            return in_array((int) $student->id, $restrictuserids, true);
        });
    }

    $rows = [];
    foreach ($students as $student) {
        $credits = ep_get_student_credits($ep->id, $student->id);
        $pending = 0;
        foreach ($credits as $credit) {
            if (ep_credit_awaits_decision($credit)) {
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
// ---------------------------------------------------------------------------------------------

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
function ep_get_filtered_credits($epid, array $filters = [], $sort = 'timecreated', $dir = 'DESC',
        ?array $restrictuserids = null, ?array $restrictactivityids = null) {
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
 * Inscriptions portant sur un EP du catalogue, toutes promotions confondues : un EP partagé est
 * défini une fois et suivi par des étudiants de plusieurs instances mod_ep, chacun avec son
 * dossier d'ECTS. Chaque ligne porte donc, en plus du crédit, l'étudiant, la promotion d'origine
 * et le course-module de son activité — nécessaire pour ouvrir sa fiche de validation.
 *
 * @param int $activityid
 * @param array $filters ['search' => nom étudiant, 'status' => int|'', 'studyyear' => int|'']
 * @param string $sort 'student', 'course', 'studyyear', 'status' ou 'timecreated'.
 * @param string $dir 'ASC' ou 'DESC'.
 * @return array id => stdClass Crédit enrichi de studentfullname, epname, coursename, cmid.
 */
function ep_get_activity_registrations($activityid, array $filters = [], $sort = 'student', $dir = 'ASC') {
    global $DB;

    $params = ['activityid' => $activityid];
    $where = ['c.activityid = :activityid'];

    if (!empty($filters['search'])) {
        $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
        $where[] = $DB->sql_like($fullname, ':search', false, false);
        $params['search'] = '%' . $DB->sql_like_escape($filters['search']) . '%';
    }
    if (isset($filters['status']) && $filters['status'] !== '') {
        $where[] = 'c.status = :status';
        $params['status'] = (int) $filters['status'];
    }
    if (isset($filters['studyyear']) && $filters['studyyear'] !== '') {
        $where[] = 'c.studyyear = :studyyear';
        $params['studyyear'] = (int) $filters['studyyear'];
    }

    $sortmap = [
        'student' => 'u.lastname, u.firstname',
        'course' => 'co.fullname',
        'studyyear' => 'c.studyyear',
        'status' => 'c.status',
        'timecreated' => 'c.timecreated',
    ];
    $sortcolumn = $sortmap[$sort] ?? $sortmap['student'];
    $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

    $sql = "SELECT c.*, e.name AS epname, co.id AS courseid, co.fullname AS coursename, cm.id AS cmid
              FROM {ep_credit} c
              JOIN {user} u ON u.id = c.userid
              JOIN {ep} e ON e.id = c.epid
              JOIN {course} co ON co.id = e.course
              JOIN {modules} m ON m.name = 'ep'
              JOIN {course_modules} cm ON cm.instance = e.id AND cm.module = m.id
             WHERE " . implode(' AND ', $where) . "
          ORDER BY $sortcolumn $dir, c.id ASC";

    $rows = $DB->get_records_sql($sql, $params);

    $students = ep_get_credit_users($rows);
    foreach ($rows as $row) {
        $student = $students[$row->userid] ?? null;
        $row->studentfullname = $student ? fullname($student) : '-';
    }

    return $rows;
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
 * @return stdClass {validatedeve, viewall, manage, evaluateteacher, referentids, responsibleids,
 *                   responsibleactivities}
 */
function ep_get_user_rights(stdClass $ep, context $context, $userid = null) {
    global $DB, $USER;

    $userid = $userid ?: $USER->id;

    $rights = (object) [
        'userid' => $userid,
        'validatedeve' => has_capability('mod/ep:validatedeve', $context, $userid),
        'viewall' => has_capability('mod/ep:viewall', $context, $userid),
        'manage' => has_capability('mod/ep:manage', $context, $userid),
        'evaluateteacher' => has_capability('mod/ep:evaluateteacher', $context, $userid),
        'referentids' => [],
        'responsibleids' => [],
        'responsibleactivities' => [],
    ];

    if ($rights->evaluateteacher) {
        $rights->referentids = ep_get_referent_students($ep, $userid);
    }

    // La responsabilité d'un EP partagé ne suppose pas de rôle enseignant dans cette promotion
    // (voir ep_can_validate_credit()) : elle est donc cherchée pour tout le monde, mais seulement
    // après avoir vérifié d'un coup que l'utilisateur est responsable de quelque chose — la table
    // est courte, et cela évite trois requêtes à chaque page ouverte par un étudiant.
    if ($rights->evaluateteacher || $DB->record_exists('ep_activity_teacher', ['teacherid' => $userid])) {
        $rights->responsibleactivities = ep_get_responsible_activities($ep, $userid);
        $rights->responsibleids = array_map('intval', array_keys($rights->responsibleactivities));
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
    if (ep_credit_is_registration($credit)) {
        $activity = $rights->responsibleactivities[(int) $credit->activityid] ?? null;
        if (!$activity) {
            return false;
        }
        return ep_activity_is_shared($activity) || $rights->evaluateteacher;
    }

    if (!$rights->evaluateteacher) {
        return false;
    }
    return in_array((int) $credit->userid, $rights->referentids, true);
}

/**
 * Indique si l'utilisateur est responsable d'au moins un EP partagé dans cette instance. Cette
 * responsabilité vaut par elle-même, sans rôle enseignant dans la promotion de l'étudiant : c'est
 * ce qui permet à un EP ouvert à plusieurs promotions d'avoir un seul responsable (voir
 * ep_can_validate_credit()).
 *
 * @param stdClass $rights Voir ep_get_user_rights().
 * @return bool
 */
function ep_rights_has_shared_responsibility(stdClass $rights) {
    foreach ($rights->responsibleactivities as $activity) {
        if (ep_activity_is_shared($activity)) {
            return true;
        }
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
 * Crédits en attente de la décision de l'utilisateur : les inscriptions aux EP dont il est
 * responsable — celles à accepter, puis celles dont il reste à valider les ECTS en fin d'EP — et
 * les déclarations de ses étudiants à valider comme enseignant référent. La DEVE voit toutes les
 * demandes en attente.
 *
 * @param stdClass $ep
 * @param stdClass $rights Voir ep_get_user_rights().
 * @param string $sort
 * @param string $dir
 * @param int $status Étape attendue : EP_STATUS_PENDING (décision à prendre sur la demande) ou
 *                    EP_STATUS_ENROLLED (ECTS à valider en fin d'EP).
 * @return array
 */
function ep_get_credits_awaiting($ep, stdClass $rights, $sort = 'timecreated', $dir = 'ASC',
        $status = EP_STATUS_PENDING) {
    $filters = ['status' => $status];

    if ($rights->validatedeve) {
        return ep_get_filtered_credits($ep->id, $filters, $sort, $dir);
    }

    $credits = ep_get_filtered_credits($ep->id, $filters, $sort, $dir,
        $rights->referentids, $rights->responsibleids);

    // Un enseignant référent n'a pas à statuer sur une inscription à un EP du catalogue dont il
    // n'est pas responsable, même s'il est référent de l'étudiant : c'est le responsable de l'EP
    // qui sait si l'étudiant l'a suivi.
    return array_filter($credits, function($credit) use ($rights) {
        return ep_rights_can_validate($rights, $credit);
    });
}

// ---------------------------------------------------------------------------------------------
// Rendu commun aux vues.
// ---------------------------------------------------------------------------------------------

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
 * Rend la cellule « Places » d'un EP du catalogue : les places occupées rapportées à la capacité,
 * et la mention « complet » quand elles le sont toutes — un repère pour le responsable, pas un
 * verrou : il reste libre d'accepter une inscription de plus.
 *
 * @param stdClass $activity
 * @param stdClass|null $counts Voir ep_get_activity_registration_counts() ; calculé si absent.
 * @return string HTML
 */
function ep_render_places_cell(stdClass $activity, ?stdClass $counts = null) {
    if (empty($activity->capacity)) {
        return get_string('unlimitedplaces', 'mod_ep');
    }

    $counts = $counts ?: ep_get_activity_registration_counts($activity->id);
    $cell = get_string('placestaken', 'mod_ep',
        (object) ['taken' => $counts->taken, 'total' => $activity->capacity]);

    if ($counts->taken >= (int) $activity->capacity) {
        $cell .= ' ' . html_writer::span(get_string('activityfull', 'mod_ep'), 'badge badge-warning');
    }
    return $cell;
}

/**
 * Rend le décompte des inscriptions d'un EP du catalogue, étape par étape : ce qui attend une
 * décision, ce qui est accepté et suivi, ce dont les ECTS sont acquis. Un total unique masquerait
 * précisément ce que le responsable a encore à faire.
 *
 * @param stdClass $counts Voir ep_get_activity_registration_counts().
 * @return string HTML
 */
function ep_render_registration_counts(stdClass $counts) {
    $badges = [];
    if ($counts->pending > 0) {
        $badges[] = html_writer::span(get_string('countpending', 'mod_ep', $counts->pending), 'badge badge-info');
    }
    if ($counts->enrolled > 0) {
        $badges[] = html_writer::span(get_string('countenrolled', 'mod_ep', $counts->enrolled),
            'badge badge-primary');
    }
    if ($counts->validated > 0) {
        $badges[] = html_writer::span(get_string('countvalidated', 'mod_ep', $counts->validated),
            'badge badge-success');
    }

    return empty($badges) ? '-' : implode(' ', $badges);
}

/**
 * Rend l'origine d'un EP partagé : l'activité « Suivi de l'enseignement personnalisé » où il est
 * défini, avec le lien vers sa gestion. C'est là, et nulle part ailleurs, qu'il se modifie.
 *
 * @param stdClass $activity
 * @return string HTML
 */
function ep_render_shared_activity_origin(stdClass $activity) {
    if (!ep_activity_is_shared($activity)) {
        return '-';
    }

    $cm = get_coursemodule_from_id('epsynthesis', $activity->synthesiscmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        // Synthèse supprimée : l'EP reste au catalogue des promotions qui l'ont déjà pris, mais
        // plus personne ne peut le modifier — mieux vaut le dire que d'afficher un lien mort.
        return html_writer::span(get_string('sharedactivityorphan', 'mod_ep'), 'text-muted');
    }

    $url = new moodle_url('/mod/epsynthesis/activities.php', ['id' => $cm->id]);
    return html_writer::link($url, format_string($cm->name));
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
    $out .= html_writer::select($yearoptions, 'studyyear', $values['studyyear'] ?? '', false,
        ['class' => 'form-control mr-2']);

    if ($showstatus) {
        $statusoptions = ['' => get_string('allstatuses', 'mod_ep')] + ep_status_options();
        $out .= html_writer::select($statusoptions, 'status', $values['status'] ?? '', false,
            ['class' => 'form-control mr-2']);
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
    if ((float) $credit->weeks > 0) {
        // Sur un type compté à la semaine, le nombre d'ECTS ne se comprend qu'avec la durée dont
        // il découle : les séparer obligerait le validateur à refaire le calcul pour vérifier.
        $rows[] = [get_string('weeks', 'mod_ep'), format_float((float) $credit->weeks, 2, true, true)];
    }
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
        $url = new moodle_url('/mod/ep/evidence_file.php',
            ['id' => $cm->id, 'creditid' => $credit->id, 'pathnamehash' => $pathnamehash]);
        $items[] = html_writer::link($url, s($file->get_filename()));
    }
    return html_writer::alist($items);
}

/**
 * Libellé de la décision attendue sur un crédit : accepter l'inscription, pour une inscription au
 * catalogue encore en attente ; valider les ECTS, pour une inscription acceptée dont l'EP est
 * terminé comme pour une déclaration hors catalogue.
 *
 * @param stdClass $credit
 * @return string
 */
function ep_credit_decision_label(stdClass $credit) {
    if (ep_credit_is_registration($credit) && (int) $credit->status === EP_STATUS_PENDING) {
        return get_string('acceptregistration', 'mod_ep');
    }
    return get_string('validatecredit', 'mod_ep');
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
    $canvalidate = ep_rights_can_validate($rights, $credit) && ep_credit_awaits_decision($credit);
    $canview = $isowner || $rights->viewall || ep_rights_can_validate($rights, $credit);

    $detailurl = new moodle_url('/mod/ep/validate.php', ['id' => $cm->id, 'creditid' => $credit->id]);
    if ($returnurl !== null) {
        $detailurl->param('returnurl', $returnurl);
    }

    $actions = [];
    if ($canvalidate) {
        $actions[ep_credit_decision_label($credit)] = $detailurl;
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
        $actions[get_string('cancelrequest', 'mod_ep')] = new moodle_url('/mod/ep/declare.php',
            ['id' => $cm->id, 'creditid' => $credit->id, 'action' => 'cancel', 'sesskey' => sesskey()]);
    }

    return ep_render_actions($actions);
}

/**
 * Traite la décision soumise sur un crédit : refus motivé, acceptation de l'inscription ou
 * validation des ECTS, selon l'étape où il en est. Redirige vers $backurl dès qu'une décision est
 * prise, et ne fait rien si le formulaire n'a pas été soumis.
 *
 * L'appelant a déjà vérifié que l'utilisateur a le droit de statuer et que le crédit attend une
 * décision : ce sont deux questions de contexte (activité d'origine ou synthèse), pas de
 * formulaire. Factorisé pour que l'écran de mod_ep et celui de la synthèse — d'où le responsable
 * d'un EP partagé statue sans avoir de rôle dans la promotion de l'étudiant — restent identiques.
 *
 * @param stdClass $credit
 * @param moodle_url $backurl Écran de retour après la décision.
 * @param int|null $byuserid Utilisateur courant par défaut.
 * @return void
 */
function ep_handle_credit_decision(stdClass $credit, moodle_url $backurl, $byuserid = null) {
    global $USER;

    if (!data_submitted() || !confirm_sesskey()) {
        return;
    }

    $byuserid = $byuserid ?: $USER->id;
    $comment = optional_param('validatorcomment', '', PARAM_TEXT);

    if (optional_param('rejectcredit', '', PARAM_RAW) !== '') {
        ep_reject_credit($credit, $byuserid, $comment);
        redirect($backurl, get_string('creditrejected', 'mod_ep'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if (ep_credit_is_registration_step($credit)) {
        if (optional_param('acceptregistration', '', PARAM_RAW) !== '') {
            // Le nombre de places n'est pas vérifié : c'est au responsable d'arbitrer, l'écran lui
            // dit seulement où il en est.
            ep_accept_registration($credit, $byuserid, $comment);
            redirect($backurl, get_string('registrationaccepted', 'mod_ep'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        return;
    }

    if (optional_param('validatecredit', '', PARAM_RAW) !== '') {
        ep_validate_credit($credit, $byuserid, optional_param('retainedects', 0, PARAM_FLOAT), $comment);
        redirect($backurl, get_string('creditvalidated', 'mod_ep'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

/**
 * Rend le formulaire de décision correspondant à l'étape où en est le crédit : accepter ou
 * refuser l'inscription, ou bien arrêter les ECTS retenus en fin d'EP.
 *
 * @param moodle_url $pageurl URL de la page, à laquelle le formulaire se soumet.
 * @param stdClass $credit
 * @return string HTML
 */
function ep_render_credit_decision_form(moodle_url $pageurl, stdClass $credit) {
    $isregistrationstep = ep_credit_is_registration_step($credit);

    $out = html_writer::tag('p', $isregistrationstep
        ? get_string('decisionregistrationnotice', 'mod_ep')
        : get_string('decisionectsnotice', 'mod_ep'), ['class' => 'text-muted']);

    $out .= html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    // Le nombre d'ECTS ne se fixe qu'à la seconde étape : à l'inscription, l'EP n'a pas encore eu
    // lieu, il n'y a rien à mesurer. Il est proposé à la valeur demandée — celle de l'EP du
    // catalogue, ou celle avancée par l'étudiant — et reste modifiable : le validateur peut n'en
    // retenir qu'une partie sans avoir à refuser toute la demande.
    if (!$isregistrationstep) {
        $out .= html_writer::tag('label', get_string('retainedects', 'mod_ep'), ['for' => 'retainedects']);
        $out .= html_writer::empty_tag('input', [
            'type' => 'number', 'step' => '0.25', 'min' => 0, 'name' => 'retainedects', 'id' => 'retainedects',
            'value' => ep_format_ects_input($credit->claimedects), 'class' => 'form-control',
        ]);
    }

    $out .= html_writer::tag('label', get_string('validatorcomment', 'mod_ep'), ['for' => 'validatorcomment']);
    $out .= html_writer::tag('textarea', '',
        ['name' => 'validatorcomment', 'id' => 'validatorcomment', 'rows' => 4, 'class' => 'form-control']);

    $out .= html_writer::empty_tag('input', $isregistrationstep
        ? [
            'type' => 'submit', 'name' => 'acceptregistration',
            'value' => get_string('acceptregistration', 'mod_ep'), 'class' => 'btn btn-primary mt-2 mr-2',
        ]
        : [
            'type' => 'submit', 'name' => 'validatecredit', 'value' => get_string('validateects', 'mod_ep'),
            'class' => 'btn btn-primary mt-2 mr-2',
        ]);
    $out .= html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'rejectcredit', 'value' => get_string('reject', 'mod_ep'),
        'class' => 'btn btn-danger mt-2',
    ]);
    $out .= html_writer::end_tag('form');

    return $out;
}

/**
 * Rend l'état des inscriptions d'un EP du catalogue à l'intention de celui qui doit statuer :
 * places occupées et demandes encore en attente. C'est ce qui lui manque pour décider s'il
 * accepte une inscription de plus, et notamment s'il dépasse le nombre de places.
 *
 * @param stdClass $activity
 * @param stdClass|null $counts Voir ep_get_activity_registration_counts() ; calculé si absent.
 * @return string HTML
 */
function ep_render_activity_occupancy(stdClass $activity, ?stdClass $counts = null) {
    $counts = $counts ?: ep_get_activity_registration_counts($activity->id);

    return html_writer::div(get_string('activityoccupancy', 'mod_ep', (object) [
        'places' => empty($activity->capacity)
            ? get_string('unlimitedplaces', 'mod_ep')
            : get_string('placestaken', 'mod_ep',
                (object) ['taken' => $counts->taken, 'total' => $activity->capacity]),
        'pending' => $counts->pending,
    ]), 'text-muted mb-3');
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
                        'badge badge-secondary');
                }
                $yeartable->data[] = array_merge(['', $label],
                    ep_render_progress_cells($typerow->retained, 0, true));
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

// ---------------------------------------------------------------------------------------------
// Import Excel/CSV par la DEVE : catalogue des EP et inscriptions/EP portés au crédit.
// ---------------------------------------------------------------------------------------------

/**
 * Colonnes attendues, dans l'ordre, pour l'import du catalogue des EP.
 *
 * @return string[]
 */
function ep_import_activity_columns() {
    return ['name', 'type', 'ects', 'minstudyyear', 'maxstudyyear', 'capacity', 'sortorder', 'visible'];
}

/**
 * Colonnes attendues, dans l'ordre, pour l'import d'EP portés au crédit des étudiants
 * (inscriptions au catalogue et déclarations hors catalogue).
 *
 * @return string[]
 */
function ep_import_credit_columns() {
    return ['email', 'ep', 'name', 'studyyear', 'claimedects', 'weeks', 'retainedects', 'status', 'comment'];
}

/**
 * Lit le contenu d'un fichier CSV (export Excel) et le découpe en lignes associatives selon les
 * colonnes attendues. Prend uniquement le contenu déjà lu, pas le tableau $_FILES : c'est ce qui
 * permet de tester cette fonction sans simuler un téléversement HTTP.
 *
 * @param string $content Contenu brut du fichier.
 * @param string[] $columns Noms de colonnes, dans l'ordre des colonnes du fichier (voir
 *                          ep_import_activity_columns(), ep_import_credit_columns()).
 * @return stdClass {rows: array, error: string|null} 'rows' : numéro de ligne (1-based, en-tête
 *                  compris) => tableau associatif colonne => valeur. Lignes vides ignorées.
 *                  'error' non nul si le fichier n'a pas pu être lu comme un CSV.
 */
function ep_parse_import_csv($content, array $columns) {
    global $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');

    // Excel francophone exporte en points-virgules ; on accepte aussi la virgule.
    $delimiter = (strpos($content, ';') !== false) ? 'semicolon' : 'comma';

    $cir = new csv_import_reader(csv_import_reader::get_new_iid('ep'), 'ep');
    if ($cir->load_csv_content($content, 'UTF-8', $delimiter) === false) {
        $error = $cir->get_error();
        $cir->cleanup(true);
        return (object) ['rows' => [], 'error' => $error];
    }

    $rows = [];
    $cir->init();
    $linenum = 1; // La première ligne est consommée comme en-tête par load_csv_content().
    while ($csvrow = $cir->next()) {
        $linenum++;
        $row = [];
        foreach ($columns as $index => $key) {
            $row[$key] = isset($csvrow[$index]) ? trim($csvrow[$index]) : '';
        }
        // Ignore les lignes entièrement vides, dont une éventuelle ligne vide en fin de fichier.
        if (implode('', $row) !== '') {
            $rows[$linenum] = $row;
        }
    }
    $cir->cleanup(true);

    return (object) ['rows' => $rows, 'error' => null];
}

/**
 * EP du catalogue proposé à une instance (propre ou partagé), repéré par son intitulé exact
 * (insensible à la casse et aux espaces de part et d'autre).
 *
 * @param stdClass $ep
 * @param string $name
 * @return stdClass|null
 */
function ep_get_catalog_activity_by_name(stdClass $ep, $name) {
    $needle = core_text::strtolower(trim($name));
    if ($needle === '') {
        return null;
    }
    foreach (ep_get_catalog_activities($ep) as $activity) {
        if (core_text::strtolower(trim($activity->name)) === $needle) {
            return $activity;
        }
    }
    return null;
}

/**
 * Type déclarable d'une instance, repéré par son code (EP_TYPE_*) ou par son libellé (insensible
 * à la casse et aux espaces de part et d'autre) : les deux graphies sont admises, une DEVE
 * remplissant un fichier à la main écrira plus volontiers « Sport » que « sport ».
 *
 * @param int $epid
 * @param string $label
 * @return stdClass|null
 */
function ep_get_declarable_type_by_label($epid, $label) {
    $needle = core_text::strtolower(trim($label));
    if ($needle === '') {
        return null;
    }
    foreach (ep_get_declarable_types($epid) as $type) {
        if (core_text::strtolower($type->code) === $needle
                || core_text::strtolower(trim($type->name)) === $needle) {
            return $type;
        }
    }
    return null;
}

/**
 * Décode la colonne de statut d'une ligne d'import : les graphies française et anglaise, à
 * l'orthographe et aux accents près, sont admises. Une valeur vide reprend le statut par défaut de
 * l'import — « en attente » normalement, ou « validé » si la DEVE a coché l'option qui valide
 * directement les lignes sans statut explicite (voir ep_import_credits()) : cette option ne fait
 * que déplacer ce défaut, une valeur inscrite dans le fichier reste toujours prioritaire.
 *
 * @param string $raw
 * @param int $defaultstatus Statut à renvoyer pour une colonne vide.
 * @return int|null Un des EP_STATUS_*, ou null si la valeur n'est pas reconnue.
 */
function ep_import_resolve_status($raw, $defaultstatus = EP_STATUS_PENDING) {
    $normalized = core_text::strtolower(trim($raw));
    if ($normalized === '') {
        return $defaultstatus;
    }
    $normalized = str_replace(['é', 'è', 'ê'], 'e', $normalized);

    $map = [
        'attente' => EP_STATUS_PENDING,
        'en attente' => EP_STATUS_PENDING,
        'pending' => EP_STATUS_PENDING,
        'accepte' => EP_STATUS_ENROLLED,
        'accepte l\'inscription' => EP_STATUS_ENROLLED,
        'enrolled' => EP_STATUS_ENROLLED,
        'valide' => EP_STATUS_VALIDATED,
        'validee' => EP_STATUS_VALIDATED,
        'validated' => EP_STATUS_VALIDATED,
        'refuse' => EP_STATUS_REJECTED,
        'refusee' => EP_STATUS_REJECTED,
        'rejete' => EP_STATUS_REJECTED,
        'rejected' => EP_STATUS_REJECTED,
    ];

    return $map[$normalized] ?? null;
}

/**
 * Résout une ligne d'import du catalogue, sans rien écrire en base : ep_import_activities()
 * traite chaque ligne indépendamment des autres, pour qu'une ligne fautive n'empêche pas
 * d'importer le reste d'un fichier par ailleurs valide.
 *
 * @param array $catalogabletypes Voir ep_get_catalogable_types(), la DEVE peut rattacher un EP à
 *                                n'importe lequel d'entre eux, pas seulement au type académique.
 * @param int $linenum Numéro de ligne, pour les messages d'erreur.
 * @param array $row Voir ep_import_activity_columns().
 * @return stdClass {ok: bool, error: string|null, plan: stdClass|null}
 *                  'plan' : {name, typeid, ects, minstudyyear, maxstudyyear, capacity, sortorder,
 *                            visible, namekey}
 */
function ep_parse_import_activity_row(array $catalogabletypes, $linenum, array $row) {
    $name = trim($row['name'] ?? '');
    if ($name === '') {
        return (object) ['ok' => false, 'error' => get_string('importerrormissingname', 'mod_ep', $linenum)];
    }

    $ects = (float) trim($row['ects'] ?? '');
    if ($ects <= 0) {
        return (object) ['ok' => false,
            'error' => get_string('importerrorline', 'mod_ep', (object) [
                'line' => $linenum, 'error' => get_string('errorpositiveects', 'mod_ep'),
            ]),
        ];
    }

    $typelabel = core_text::strtolower(trim($row['type'] ?? ''));
    $type = null;
    foreach ($catalogabletypes as $candidate) {
        if ($typelabel === '' && $candidate->code === EP_TYPE_ACADEMIC) {
            $type = $candidate;
            break;
        }
        if ($typelabel !== '' && (core_text::strtolower($candidate->code) === $typelabel
                || core_text::strtolower(trim($candidate->name)) === $typelabel)) {
            $type = $candidate;
            break;
        }
    }
    if (!$type) {
        return (object) ['ok' => false, 'error' => get_string('importerrorunknowntype', 'mod_ep', (object) [
            'line' => $linenum, 'type' => $row['type'] ?? '',
        ])];
    }

    $minyear = ($row['minstudyyear'] ?? '') !== '' ? (int) $row['minstudyyear'] : 0;
    $maxyear = ($row['maxstudyyear'] ?? '') !== '' ? (int) $row['maxstudyyear'] : 0;
    if ($minyear && $maxyear && $minyear > $maxyear) {
        return (object) ['ok' => false,
            'error' => get_string('importerrorline', 'mod_ep', (object) [
                'line' => $linenum, 'error' => get_string('errorstudyyearrange', 'mod_ep'),
            ]),
        ];
    }

    $capacity = max(0, (int) ($row['capacity'] ?? 0));
    $sortorder = (int) ($row['sortorder'] ?? 0);

    $visibleraw = core_text::strtolower(trim($row['visible'] ?? ''));
    $visible = !in_array($visibleraw, ['0', 'non', 'no', 'false'], true) ? 1 : 0;

    return (object) ['ok' => true, 'error' => null, 'plan' => (object) [
        'name' => $name,
        'typeid' => $type->id,
        'ects' => round($ects, 2),
        'minstudyyear' => $minyear,
        'maxstudyyear' => $maxyear,
        'capacity' => $capacity,
        'sortorder' => $sortorder,
        'visible' => $visible,
        'namekey' => core_text::strtolower($name),
    ]];
}

/**
 * Importe en masse le catalogue des EP propres à une promotion, depuis les lignes lues par
 * ep_parse_import_csv() (voir ep_import_activity_columns() pour les colonnes attendues).
 *
 * N'affecte aucun responsable : sans lui, une inscription reste en attente indéfiniment, la DEVE
 * doit encore désigner les responsables de chaque EP importé depuis la page du catalogue.
 *
 * @param stdClass $ep
 * @param array $rows Voir ep_parse_import_csv().
 * @return stdClass {created: int, errors: string[]}
 */
function ep_import_activities(stdClass $ep, array $rows) {
    global $DB;

    $catalogabletypes = ep_get_catalogable_types($ep->id);

    $existing = [];
    foreach (ep_get_activities($ep->id) as $activity) {
        $existing[core_text::strtolower(trim($activity->name))] = true;
    }

    $errors = [];
    $plans = [];
    $seen = [];
    foreach ($rows as $linenum => $row) {
        $result = ep_parse_import_activity_row($catalogabletypes, $linenum, $row);
        if (!$result->ok) {
            $errors[] = $result->error;
            continue;
        }
        $plan = $result->plan;
        if (isset($seen[$plan->namekey]) || isset($existing[$plan->namekey])) {
            $errors[] = get_string('importerroractivityduplicate', 'mod_ep', (object) [
                'line' => $linenum, 'name' => $plan->name,
            ]);
            continue;
        }
        $seen[$plan->namekey] = true;
        $plans[] = $plan;
    }

    $now = time();
    $records = [];
    foreach ($plans as $plan) {
        $records[] = (object) [
            'epid' => $ep->id,
            'synthesiscmid' => 0,
            'typeid' => $plan->typeid,
            'name' => $plan->name,
            'description' => '',
            'ects' => $plan->ects,
            'minstudyyear' => $plan->minstudyyear,
            'maxstudyyear' => $plan->maxstudyyear,
            'capacity' => $plan->capacity,
            'visible' => $plan->visible,
            'sortorder' => $plan->sortorder,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
    }
    // Insertion groupée : un import de plusieurs dizaines d'EP ne doit pas déclencher autant de
    // requêtes individuelles.
    if ($records) {
        $DB->insert_records('ep_activity', $records);
    }

    return (object) ['created' => count($records), 'errors' => $errors];
}

/**
 * Résout une ligne d'import de crédit, sans rien écrire en base : la colonne « EP » désigne soit
 * un EP du catalogue par son intitulé exact (inscription), soit un type déclarable par son code
 * ou son libellé (déclaration hors catalogue) — c'est ce qui permet d'importer les deux à la même
 * colonne, sans en faire deux fichiers distincts.
 *
 * @param stdClass $ep
 * @param array $studentsbyemail Étudiants inscrits, indexés par email en minuscules (voir
 *                               ep_get_enrolled_students()).
 * @param int $linenum Numéro de ligne, pour les messages d'erreur.
 * @param array $row Voir ep_import_credit_columns().
 * @param int $defaultstatus Statut à appliquer aux lignes dont la colonne status est vide (voir
 *                           ep_import_credits()).
 * @return stdClass {ok: bool, error: string|null, plan: stdClass|null}
 *                  'plan' : {studentid, activity, type, name, studyyear, claimedects, weeks,
 *                            retainedects, status, comment, fingerprint}
 */
function ep_parse_import_credit_row(stdClass $ep, array $studentsbyemail, $linenum, array $row,
        $defaultstatus = EP_STATUS_PENDING) {
    $email = trim($row['email'] ?? '');
    $targetlabel = trim($row['ep'] ?? '');
    if ($email === '' || $targetlabel === '') {
        return (object) ['ok' => false, 'error' => get_string('importerrorincomplete', 'mod_ep', $linenum)];
    }

    $student = $studentsbyemail[core_text::strtolower($email)] ?? null;
    if (!$student) {
        return (object) ['ok' => false, 'error' => get_string('importerrorunknownemail', 'mod_ep', (object) [
            'line' => $linenum, 'email' => $email,
        ])];
    }

    $activity = ep_get_catalog_activity_by_name($ep, $targetlabel);
    $type = $activity ? ep_get_activity_type($ep, $activity) : ep_get_declarable_type_by_label($ep->id, $targetlabel);
    if (!$type) {
        return (object) ['ok' => false, 'error' => get_string('importerrorunknowntarget', 'mod_ep', (object) [
            'line' => $linenum, 'target' => $targetlabel,
        ])];
    }

    $status = ep_import_resolve_status($row['status'] ?? '', $defaultstatus);
    if ($status === null) {
        return (object) ['ok' => false, 'error' => get_string('importerrorunknownstatus', 'mod_ep', (object) [
            'line' => $linenum, 'status' => $row['status'] ?? '',
        ])];
    }
    // L'acceptation d'une inscription est une étape propre au catalogue : une déclaration hors
    // catalogue n'en connaît pas, elle passe directement de « en attente » à « validée ».
    if ($status === EP_STATUS_ENROLLED && !$activity) {
        return (object) ['ok' => false,
            'error' => get_string('importerrorenrolledwithoutactivity', 'mod_ep', $linenum),
        ];
    }

    $studyyear = ($row['studyyear'] ?? '') !== '' ? (int) $row['studyyear'] : ep_get_current_studyyear($ep);

    if ($activity) {
        // L'EP porte son propre nombre d'ECTS : ce que la ligne aurait pu indiquer par ailleurs
        // pour les ECTS demandés ou les semaines n'a pas cours ici.
        $name = $activity->name;
        $claimedects = round((float) $activity->ects, 2);
        $weeks = 0.0;
    } else {
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            return (object) ['ok' => false, 'error' => get_string('importerrormissingname', 'mod_ep', $linenum)];
        }
        if (!ep_type_ects_rule_is_set($type)) {
            return (object) ['ok' => false,
                'error' => get_string('importerrorline', 'mod_ep', (object) [
                    'line' => $linenum, 'error' => get_string('errortypeectsunset', 'mod_ep'),
                ]),
            ];
        }
        $rawclaimed = ($row['claimedects'] ?? '') !== '' ? (float) $row['claimedects'] : 0;
        $rawweeks = ($row['weeks'] ?? '') !== '' ? (float) $row['weeks'] : 0;
        $claimedects = ep_type_claimed_ects($type, $rawclaimed, $rawweeks);
        $weeks = ep_type_declared_weeks($type, $rawweeks);
        if ($claimedects <= 0) {
            return (object) ['ok' => false,
                'error' => get_string('importerrorline', 'mod_ep', (object) [
                    'line' => $linenum, 'error' => get_string('errorpositiveects', 'mod_ep'),
                ]),
            ];
        }
    }

    $retainedects = ($row['retainedects'] ?? '') !== '' ? max(0, (float) $row['retainedects']) : $claimedects;

    $fingerprint = $activity
        ? 'reg:' . $student->id . ':' . $activity->id
        : 'dec:' . $student->id . ':' . $type->id . ':' . core_text::strtolower($name) . ':' . $studyyear;

    return (object) ['ok' => true, 'error' => null, 'plan' => (object) [
        'studentid' => (int) $student->id,
        'activity' => $activity,
        'type' => $type,
        'name' => $name,
        'studyyear' => $studyyear,
        'claimedects' => round($claimedects, 2),
        'weeks' => round($weeks, 2),
        'retainedects' => round($retainedects, 2),
        'status' => $status,
        'comment' => trim($row['comment'] ?? ''),
        'fingerprint' => $fingerprint,
    ]];
}

/**
 * Applique à un crédit fraîchement créé (toujours en attente à sa création, voir
 * ep_create_credit()) le statut visé par une ligne d'import.
 *
 * @param stdClass $credit
 * @param int $status
 * @param float $retainedects
 * @param int $byuserid
 * @param string $comment
 * @return void
 */
function ep_import_apply_status(stdClass $credit, $status, $retainedects, $byuserid, $comment) {
    switch ($status) {
        case EP_STATUS_ENROLLED:
            ep_accept_registration($credit, $byuserid, $comment);
            break;
        case EP_STATUS_VALIDATED:
            ep_validate_credit($credit, $byuserid, $retainedects, $comment);
            break;
        case EP_STATUS_REJECTED:
            ep_reject_credit($credit, $byuserid, $comment);
            break;
        default:
            // En attente : c'est déjà l'état dans lequel le crédit vient d'être créé.
    }
}

/**
 * Importe en masse des EP portés au crédit d'étudiants — inscriptions au catalogue et
 * déclarations hors catalogue confondues —, saisis par la DEVE pour leur compte (source
 * EP_SOURCE_DEVE) : reprise de dossiers papier, régularisation en fin d'année, etc.
 *
 * Chaque ligne est résolue et vérifiée indépendamment des autres, comme pour
 * ep_import_activities() : une ligne fautive est signalée sans empêcher l'import des lignes
 * valides du même fichier.
 *
 * @param stdClass $ep
 * @param context $context
 * @param array $rows Voir ep_parse_import_csv() et ep_import_credit_columns().
 * @param int $byuserid Utilisateur DEVE à l'origine de l'import, consigné comme validateur des
 *                      décisions qu'il contient (acceptation, validation, refus).
 * @param bool $directvalidate Valide directement les lignes dont la colonne status est vide, au
 *                             lieu de les laisser en attente — l'option proposée sur la page
 *                             d'import pour dispenser la DEVE de remplir cette colonne quand tout
 *                             le fichier est déjà décidé. Une valeur explicite dans le fichier
 *                             reste toujours prioritaire (voir ep_import_resolve_status()).
 * @return stdClass {created: int, errors: string[]}
 */
function ep_import_credits(stdClass $ep, context $context, array $rows, $byuserid, $directvalidate = false) {
    global $DB;

    $studentsbyemail = [];
    foreach (ep_get_enrolled_students($context) as $student) {
        $studentsbyemail[core_text::strtolower(trim($student->email))] = $student;
    }
    $defaultstatus = $directvalidate ? EP_STATUS_VALIDATED : EP_STATUS_PENDING;

    $errors = [];
    $plans = [];
    $seen = [];
    foreach ($rows as $linenum => $row) {
        $result = ep_parse_import_credit_row($ep, $studentsbyemail, $linenum, $row, $defaultstatus);
        if (!$result->ok) {
            $errors[] = $result->error;
            continue;
        }
        $plan = $result->plan;

        if (isset($seen[$plan->fingerprint])) {
            $errors[] = get_string('importerrorduplicateinfile', 'mod_ep', $linenum);
            continue;
        }
        // Une inscription active existante sur le même EP bloque l'import de celle-ci, exactement
        // comme elle bloquerait une inscription en ligne (voir ep_get_active_registration()) :
        // l'étudiant a déjà un dossier sur cet EP, l'écraser silencieusement le ferait disparaître.
        if ($plan->activity && ep_get_active_registration($plan->activity->id, $plan->studentid)) {
            $errors[] = get_string('importerrorexistingregistration', 'mod_ep', (object) [
                'line' => $linenum, 'ep' => $plan->activity->name,
            ]);
            continue;
        }
        if (!$plan->activity && $DB->record_exists('ep_credit', [
                'epid' => $ep->id, 'userid' => $plan->studentid, 'typeid' => $plan->type->id,
                'name' => $plan->name, 'studyyear' => $plan->studyyear,
            ])) {
            $errors[] = get_string('importerrorduplicate', 'mod_ep', $linenum);
            continue;
        }

        $seen[$plan->fingerprint] = true;
        $plans[] = $plan;
    }

    $created = 0;
    foreach ($plans as $plan) {
        $creditid = ep_create_credit($ep, $plan->studentid, $plan->type, [
            'activityid' => $plan->activity ? $plan->activity->id : null,
            'name' => $plan->name,
            'studyyear' => $plan->studyyear,
            'claimedects' => $plan->claimedects,
            'weeks' => $plan->weeks,
            'source' => EP_SOURCE_DEVE,
        ]);
        $credit = $DB->get_record('ep_credit', ['id' => $creditid], '*', MUST_EXIST);
        ep_import_apply_status($credit, $plan->status, $plan->retainedects, $byuserid, $plan->comment);
        $created++;
    }

    return (object) ['created' => $created, 'errors' => $errors];
}
