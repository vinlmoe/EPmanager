<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\local;

/**
 * Données de bilan préchargées pour toute une promotion, le temps d'un calcul groupé.
 *
 * Le bilan d'un étudiant (ep_get_student_progress()) lit les types, les minimums annuels et les
 * crédits de l'étudiant : appelé pour chacun des étudiants d'une promotion, il en résultait trois
 * requêtes par étudiant au tableau de pilotage et à l'export. Ce cache charge en trois requêtes
 * tout ce dont le calcul a besoin pour les étudiants demandés, et les fonctions de lecture
 * (ep_get_types(), ep_get_year_requirements(), ep_get_student_credits()) le consultent tant
 * qu'il couvre l'instance et l'étudiant demandés. Sinon, elles interrogent la base comme avant.
 *
 * Il n'est actif que pendant un calcul en lecture seule, qui l'arme puis le vide dans un bloc
 * finally : aucune écriture ne peut le rendre périmé.
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress_cache {
    /** @var int Taille des paquets d'identifiants dans les clauses IN (limite d'Oracle et de MSSQL). */
    const BATCH = 500;

    /** @var int|null Instance couverte, null si le cache est vide. */
    private static $epid = null;

    /** @var array Types de l'instance (tous), dans l'ordre de ep_get_types(). */
    private static $types = [];

    /** @var array studyyear => minimum d'ECTS. */
    private static $requirements = [];

    /** @var array userid => [creditid => crédit], du plus récent au plus ancien. */
    private static $credits = [];

    /**
     * Charge les données de bilan d'une instance pour les étudiants donnés.
     *
     * @param int $epid
     * @param int[] $userids
     * @return void
     */
    public static function prime(int $epid, array $userids): void {
        global $DB;

        self::clear();
        self::$types = $DB->get_records('ep_type', ['epid' => $epid], 'sortorder ASC, name ASC');
        self::$requirements = [];
        foreach ($DB->get_records('ep_year_requirement', ['epid' => $epid], 'studyyear ASC') as $record) {
            self::$requirements[(int) $record->studyyear] = (float) $record->requiredects;
        }

        $userids = array_values(array_unique(array_map('intval', $userids)));
        self::$credits = array_fill_keys($userids, []);
        foreach (array_chunk($userids, self::BATCH) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'u');
            $records = $DB->get_records_select(
                'ep_credit',
                "epid = :epid AND userid $insql",
                $params + ['epid' => $epid],
                'timecreated DESC, id DESC'
            );
            foreach ($records as $credit) {
                self::$credits[(int) $credit->userid][(int) $credit->id] = $credit;
            }
        }
        self::$epid = $epid;
    }

    /**
     * Vide le cache.
     *
     * @return void
     */
    public static function clear(): void {
        self::$epid = null;
        self::$types = [];
        self::$requirements = [];
        self::$credits = [];
    }

    /**
     * Indique si le cache couvre une instance.
     *
     * @param int $epid
     * @return bool
     */
    public static function covers(int $epid): bool {
        return self::$epid === $epid;
    }

    /**
     * Types de l'instance couverte, ou null si le cache ne la couvre pas.
     *
     * @param int $epid
     * @param bool $onlyenabled
     * @return array|null
     */
    public static function types(int $epid, bool $onlyenabled = false): ?array {
        if (!self::covers($epid)) {
            return null;
        }
        if (!$onlyenabled) {
            return self::$types;
        }
        return array_filter(self::$types, fn($type) => !empty($type->enabled));
    }

    /**
     * Minimums annuels de l'instance couverte, ou null si le cache ne la couvre pas.
     *
     * @param int $epid
     * @return array|null studyyear => float
     */
    public static function requirements(int $epid): ?array {
        return self::covers($epid) ? self::$requirements : null;
    }

    /**
     * Crédits d'un étudiant de l'instance couverte, ou null si le cache ne couvre pas l'instance
     * ou l'étudiant.
     *
     * @param int $epid
     * @param int $userid
     * @return array|null
     */
    public static function credits(int $epid, int $userid): ?array {
        if (!self::covers($epid) || !array_key_exists($userid, self::$credits)) {
            return null;
        }
        return self::$credits[$userid];
    }
}
