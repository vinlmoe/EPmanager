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
 * Import des notes ou des validations des participants d'un EP du catalogue depuis un fichier CSV.
 *
 * Colonnes reconnues par leur intitulé, en français ou en anglais, dans n'importe quel ordre :
 * - l'étudiant : « Email » / « Courriel » / « Adresse électronique », ou « Numéro d'identification »
 *   / « Identifiant » / « idnumber » / « N° étudiant » ;
 * - la note : « Note » / « Grade » ;
 * - la décision, pour un EP sans note : « Décision » / « Résultat » / « Validé » / « Decision »,
 *   valeurs « validé », « oui », « 1 », « V », « admis » d'une part, « non validé », « non », « 0 »,
 *   « NV », « ajourné », « refusé », « absent » d'autre part ;
 * - un commentaire facultatif : « Commentaire » / « Comment ».
 *
 * Le séparateur (point-virgule, virgule, tabulation) est détecté sur la ligne d'en-tête ; un
 * fichier enregistré par Excel en Windows-1252 est converti. La lecture ne modifie rien : elle
 * produit des lignes que la page présente en prévisualisation, et que ep_apply_participant_decisions()
 * applique une fois confirmées.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades_importer {
    /** @var array Intitulés reconnus, normalisés (voir normalise()), par colonne. */
    const HEADERS = [
        'email' => ['email', 'courriel', 'adresseelectronique', 'adressecourriel', 'adressedecourriel', 'mail', 'emailaddress',
            'adressemail', 'adresseemail'],
        'idnumber' => ['idnumber', 'identifiant', 'numerodidentification', 'numeroetudiant', 'netudiant',
            'noetudiant', 'studentnumber', 'idnumberetudiant'],
        'grade' => ['note', 'grade', 'notesur20', 'mark'],
        'decision' => ['decision', 'resultat', 'valide', 'validation', 'result'],
        'comment' => ['commentaire', 'comment', 'remarque', 'observations'],
    ];

    /** @var string[] Valeurs de décision qui valident, normalisées. */
    const VALIDATE = ['valide', 'oui', 'yes', '1', 'v', 'admis', 'acquis', 'validated', 'pass', 'ok'];

    /** @var string[] Valeurs de décision qui ne valident pas, normalisées. */
    const REJECT = ['nonvalide', 'non', 'no', '0', 'nv', 'ajourne', 'refuse', 'absent', 'abs', 'notvalidated',
        'fail', 'nonacquis'];

    /**
     * Ramène un intitulé ou une valeur à une forme comparable : minuscules, sans accent, sans
     * espace ni ponctuation.
     *
     * @param string $value
     * @return string
     */
    public static function normalise($value) {
        $value = \core_text::strtolower(\core_text::specialtoascii(trim((string) $value)));
        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    /**
     * Lit le contenu d'un fichier CSV et le rapproche des participants de l'EP.
     *
     * @param string $content Contenu brut du fichier.
     * @param array $participants Inscriptions de l'EP (voir ep_get_participants()), par identifiant.
     * @param array $users Comptes des participants, par identifiant d'utilisateur.
     * @param \stdClass $grading Barème de l'EP (voir ep_activity_grading()).
     * @return \stdClass {rows: array, errors: string[]} Les lignes reconnues (creditid, userid, grade,
     *                   decision, comment, line) et les erreurs, ligne par ligne.
     */
    public static function read($content, array $participants, array $users, \stdClass $grading) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $result = (object) ['rows' => [], 'errors' => []];

        $content = (string) $content;
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = \core_text::convert($content, 'windows-1252', 'utf-8');
        }
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $headerline = '';
        while ($lines && trim($headerline) === '') {
            $headerline = array_shift($lines);
        }
        $lineno = 1;
        $delimiter = self::detect_delimiter($headerline);
        $columns = self::columns(str_getcsv($headerline, $delimiter, '"', ''));
        if (!isset($columns['email']) && !isset($columns['idnumber'])) {
            $result->errors[] = get_string('importgradesnostudentcolumn', 'mod_ep');
            return $result;
        }
        if (!isset($columns['grade']) && !isset($columns['decision'])) {
            $result->errors[] = get_string('importgradesnovaluecolumn', 'mod_ep');
            return $result;
        }

        // Inscriptions en cours de chaque participant, retrouvées par adresse ou par identifiant.
        $byemail = [];
        $byidnumber = [];
        foreach ($participants as $credit) {
            if (!in_array((int) $credit->status, [EP_STATUS_PENDING, EP_STATUS_VALIDATED, EP_STATUS_REJECTED], true)) {
                continue;
            }
            $user = $users[$credit->userid] ?? null;
            if (!$user) {
                continue;
            }
            // L'inscription la plus récente l'emporte : la liste est triée de la plus récente à la
            // plus ancienne pour un même étudiant.
            $byemail[\core_text::strtolower(trim($user->email))] ??= $credit;
            if (trim((string) $user->idnumber) !== '') {
                $byidnumber[trim($user->idnumber)] ??= $credit;
            }
        }

        $seen = [];
        foreach ($lines as $line) {
            $lineno++;
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $value = fn($column) => isset($columns[$column]) ? trim((string) ($cells[$columns[$column]] ?? '')) : '';

            $email = \core_text::strtolower($value('email'));
            $idnumber = $value('idnumber');
            $credit = ($email !== '' ? ($byemail[$email] ?? null) : null)
                ?? ($idnumber !== '' ? ($byidnumber[$idnumber] ?? null) : null);
            $who = $email !== '' ? $email : $idnumber;
            if (!$credit) {
                $result->errors[] = get_string('importgradesunknown', 'mod_ep', (object) ['line' => $lineno, 'who' => s($who)]);
                continue;
            }
            if (isset($seen[$credit->id])) {
                $result->errors[] = get_string('importgradesduplicate', 'mod_ep', (object) [
                    'line' => $lineno, 'who' => s($who), 'first' => $seen[$credit->id],
                ]);
                continue;
            }

            $grade = null;
            $decision = '';
            $rawgrade = $value('grade');
            $rawdecision = $value('decision');
            if ($grading->grading === EP_GRADING_GRADE) {
                if ($rawgrade === '') {
                    $result->errors[] = get_string('importgradesmissing', 'mod_ep', (object) ['line' => $lineno, 'who' => s($who)]);
                    continue;
                }
                $grade = self::parse_number($rawgrade);
                if ($grade === null || $grade < 0 || $grade > $grading->grademax) {
                    $result->errors[] = get_string('importgradesinvalid', 'mod_ep', (object) [
                        'line' => $lineno, 'value' => s($rawgrade), 'max' => ep_format_ects($grading->grademax),
                    ]);
                    continue;
                }
            } else {
                $normalised = self::normalise($rawdecision !== '' ? $rawdecision : $rawgrade);
                if (in_array($normalised, self::VALIDATE, true)) {
                    $decision = 'validate';
                } else if (in_array($normalised, self::REJECT, true)) {
                    $decision = 'reject';
                } else {
                    $result->errors[] = get_string('importgradesbaddecision', 'mod_ep', (object) [
                        'line' => $lineno, 'value' => s($rawdecision !== '' ? $rawdecision : $rawgrade),
                    ]);
                    continue;
                }
            }

            $seen[$credit->id] = $lineno;
            $result->rows[] = (object) [
                'line' => $lineno,
                'creditid' => (int) $credit->id,
                'userid' => (int) $credit->userid,
                'status' => (int) $credit->status,
                'grade' => $grade,
                'decision' => $decision,
                'comment' => \core_text::substr($value('comment'), 0, 1000),
            ];
        }
        return $result;
    }

    /**
     * Séparateur d'une ligne d'en-tête : celui des trois candidats qui y apparaît le plus.
     *
     * @param string $line
     * @return string
     */
    protected static function detect_delimiter($line) {
        $counts = [];
        foreach ([';', ',', "\t"] as $candidate) {
            $counts[$candidate] = substr_count($line, $candidate);
        }
        arsort($counts);
        return (string) array_key_first($counts);
    }

    /**
     * Position de chaque colonne reconnue dans la ligne d'en-tête.
     *
     * @param array $headers
     * @return array colonne => index
     */
    protected static function columns(array $headers) {
        $columns = [];
        foreach ($headers as $index => $header) {
            $normalised = self::normalise($header);
            foreach (self::HEADERS as $column => $aliases) {
                if (!isset($columns[$column]) && in_array($normalised, $aliases, true)) {
                    $columns[$column] = $index;
                }
            }
        }
        return $columns;
    }

    /**
     * Lit un nombre écrit à la française ou à l'anglaise (« 12,5 », « 12.5 »), ou null.
     *
     * @param string $value
     * @return float|null
     */
    public static function parse_number($value) {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) $value));
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
