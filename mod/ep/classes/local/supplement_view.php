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

namespace mod_ep\local;

use context;
use html_writer;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Rendu des éléments du supplément au diplôme commun au supplément d'un étudiant
 * (supplement.php) et à la file d'examen des validateurs (supplement_items.php) : les deux écrans
 * doivent montrer un élément exactement de la même manière, pour que l'étudiant voie ce que son
 * validateur a vu.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplement_view {
    /**
     * Période d'un élément : début et fin, l'une ou l'autre pouvant manquer (une distinction a une
     * date, un mandat électif une durée).
     *
     * @param stdClass $item
     * @return string
     */
    public static function period(stdClass $item): string {
        $format = get_string('strftimedate', 'langconfig');
        $start = (int) $item->periodstart ? userdate((int) $item->periodstart, $format) : '';
        $end = (int) $item->periodend ? userdate((int) $item->periodend, $format) : '';
        if ($start === '' && $end === '') {
            return '-';
        }
        if ($start === '' || $end === '' || $start === $end) {
            return $start !== '' ? $start : $end;
        }
        return $start . ' – ' . $end;
    }

    /**
     * Libellé de la catégorie d'un élément.
     *
     * @param array $categories id => catégorie (voir ep_get_supplement_categories()).
     * @param stdClass $item
     * @return string HTML
     */
    public static function category(array $categories, stdClass $item): string {
        $category = $categories[$item->categoryid] ?? null;
        return $category ? format_string($category->name) : '-';
    }

    /**
     * Intitulé d'un élément, suivi de son intitulé anglais et de sa description s'ils existent.
     *
     * @param stdClass $item
     * @return string HTML
     */
    public static function title(stdClass $item): string {
        $html = format_string($item->title);
        if (trim((string) $item->titleen) !== '') {
            $html .= html_writer::div(format_string($item->titleen), 'text-muted small');
        }
        if (trim((string) $item->description) !== '') {
            $html .= html_writer::div(format_text($item->description, FORMAT_PLAIN), 'small');
        }
        return $html;
    }

    /**
     * Statut d'un élément ; en attente, le rôle dont la décision est attendue, pour que l'étudiant
     * sache qui relancer.
     *
     * @param stdClass $item
     * @return string HTML
     */
    public static function status(stdClass $item): string {
        $html = ep_render_credit_status((int) $item->status);
        if ((int) $item->status === EP_STATUS_PENDING && $item->pendingrole !== '') {
            $html .= html_writer::div(get_string('awaitingrole', 'mod_ep', ep_role_label($item->pendingrole)), 'small');
        }
        return $html;
    }

    /**
     * Preuves d'un élément, en liens vers supplement_file.php qui en contrôle l'accès.
     *
     * @param stdClass $cm
     * @param context $context
     * @param stdClass $item
     * @return string HTML, « - » sans preuve.
     */
    public static function proofs(stdClass $cm, context $context, stdClass $item): string {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_ep',
            EP_SUPPLEMENT_EVIDENCE_FILEAREA,
            $item->id,
            'filename',
            false
        );
        if (empty($files)) {
            return '-';
        }
        $links = [];
        foreach ($files as $file) {
            $url = new moodle_url('/mod/ep/supplement_file.php', [
                'id' => $cm->id,
                'area' => 'proof',
                'itemid' => $item->id,
                'pathnamehash' => $file->get_pathnamehash(),
            ]);
            $links[] = html_writer::link($url, s($file->get_filename()));
        }
        return implode(html_writer::empty_tag('br'), $links);
    }
}
