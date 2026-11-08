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
 * Lecture des fichiers CSV des imports du module : encodage (UTF-8 avec ou sans BOM, ou
 * Windows-1252 d'un fichier enregistré par Excel), séparateur (point-virgule, virgule ou
 * tabulation, détecté sur la ligne d'en-tête), colonnes reconnues par leur intitulé en français ou
 * en anglais, dans n'importe quel ordre, sans tenir compte des accents ni de la casse.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_reader {
    /**
     * Ramène un intitulé ou une valeur à une forme comparable : minuscules, sans accent, sans
     * espace ni ponctuation.
     *
     * @param string $value
     * @return string
     */
    public static function normalise($value): string {
        $value = \core_text::strtolower(\core_text::specialtoascii(trim((string) $value)));
        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    /**
     * Lit un contenu CSV.
     *
     * @param string $content Contenu brut.
     * @param array $aliases colonne => intitulés reconnus (bruts : ils sont normalisés ici).
     * @return \stdClass {columns: array colonne => index, rows: array numéro de ligne => cellules,
     *                   unknown: string[] intitulés non reconnus}
     */
    public static function read($content, array $aliases): \stdClass {
        $content = (string) $content;
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = \core_text::convert($content, 'windows-1252', 'utf-8');
        }
        // Un champ entre guillemets peut contenir des retours à la ligne (une rubrique de
        // syllabus, par exemple) : la lecture se fait donc par flux, pas ligne par ligne.
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $first = '';
        while (($line = fgets($stream)) !== false) {
            if (trim($line) !== '') {
                $first = $line;
                break;
            }
        }
        $delimiter = self::detect_delimiter($first);
        $headers = str_getcsv(rtrim($first, "\r\n"), $delimiter, '"', '');

        $normalised = [];
        foreach ($aliases as $column => $names) {
            foreach ($names as $name) {
                $normalised[self::normalise($name)] = $column;
            }
        }
        $result = (object) ['columns' => [], 'rows' => [], 'unknown' => []];
        foreach ($headers as $index => $header) {
            $key = self::normalise($header);
            if ($key === '') {
                continue;
            }
            if (isset($normalised[$key]) && !isset($result->columns[$normalised[$key]])) {
                $result->columns[$normalised[$key]] = $index;
            } else {
                $result->unknown[] = trim($header);
            }
        }

        $lineno = 1;
        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $lineno++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;
            }
            $result->rows[$lineno] = $cells;
        }
        fclose($stream);
        return $result;
    }

    /**
     * Valeur d'une colonne dans une ligne lue, ou chaîne vide si la colonne est absente.
     *
     * @param \stdClass $read Résultat de read().
     * @param array $cells
     * @param string $column
     * @return string
     */
    public static function value(\stdClass $read, array $cells, $column): string {
        if (!isset($read->columns[$column])) {
            return '';
        }
        return trim((string) ($cells[$read->columns[$column]] ?? ''));
    }

    /**
     * Séparateur d'une ligne d'en-tête : celui des trois candidats qui y apparaît le plus.
     *
     * @param string $line
     * @return string
     */
    protected static function detect_delimiter($line): string {
        $counts = [];
        foreach ([';', ',', "\t"] as $candidate) {
            $counts[$candidate] = substr_count($line, $candidate);
        }
        arsort($counts);
        return (string) array_key_first($counts);
    }

    /**
     * Nombre écrit à la française ou à l'anglaise, ou null.
     *
     * @param string $value
     * @return float|null
     */
    public static function number($value): ?float {
        $value = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim((string) $value));
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /**
     * Date au format jj/mm/aaaa (ou aaaa-mm-jj), éventuellement suivie d'une heure hh:mm, ou null.
     *
     * @param string $value
     * @return int|null Horodatage, 0 pour une valeur vide.
     */
    public static function date($value): ?int {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})(?:\s+(\d{1,2})[:h](\d{2}))?$#', $value, $m)) {
            return make_timestamp((int) $m[3], (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0));
        }
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2}))?#', $value, $m)) {
            return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0));
        }
        return null;
    }

    /**
     * Valeur oui/non, ou null si elle n'est pas reconnue (vide : $default).
     *
     * @param string $value
     * @param bool $default
     * @return bool|null
     */
    public static function boolean($value, $default = false): ?bool {
        $value = self::normalise($value);
        if ($value === '') {
            return $default;
        }
        if (in_array($value, ['1', 'oui', 'yes', 'o', 'y', 'vrai', 'true', 'x'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'non', 'no', 'n', 'faux', 'false'], true)) {
            return false;
        }
        return null;
    }

    /**
     * Compte d'utilisateur désigné par son adresse ou son numéro d'identification, parmi les
     * comptes donnés, ou null s'il n'y en a pas un et un seul.
     *
     * @param string $value
     * @param array $byemail adresse en minuscules => userid
     * @param array $byidnumber numéro => userid
     * @return int|null
     */
    public static function user($value, array $byemail, array $byidnumber): ?int {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        return $byemail[\core_text::strtolower($value)] ?? $byidnumber[$value] ?? null;
    }

    /**
     * Tableau des colonnes reconnues d'un import, pour la page de dépôt : c'est la documentation
     * du format, tirée de la définition même des colonnes, si bien qu'elle ne peut pas diverger.
     *
     * @param array $columns clé => [intitulés reconnus, obligatoire]
     * @return string HTML
     */
    public static function columns_table(array $columns): string {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable table-sm';
        $table->head = [get_string('importcolumnheadings', 'mod_ep'), get_string('importcolumnrequired', 'mod_ep')];
        foreach ($columns as [$names, $required]) {
            $others = array_slice($names, 1);
            $table->data[] = [
                \html_writer::tag('strong', s($names[0])) . ($others ? ' ; ' . s(implode(' ; ', $others)) : ''),
                $required ? get_string('yes') : '',
            ];
        }
        return \html_writer::table($table);
    }

    /**
     * Envoie un modèle de fichier : la ligne d'en-tête seule, séparée par des points-virgules, en
     * UTF-8 avec BOM pour qu'Excel l'ouvre avec ses accents.
     *
     * @param array $columns clé => [intitulés reconnus, obligatoire]
     * @param string $filename
     * @return void
     */
    public static function send_template(array $columns, string $filename) {
        $headers = array_map(fn($definition) => $definition[0][0], array_values($columns));
        $line = implode(';', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers));
        send_file("\xEF\xBB\xBF" . $line . "\r\n", $filename, 0, 0, true, true, 'text/csv');
    }
}
