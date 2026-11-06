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

use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Classeur Excel écrit en flux (OpenSpout, fourni avec Moodle) : les lignes vont dans le fichier
 * à mesure qu'elles sont données, si bien qu'un classeur de centaines de milliers de cellules ne
 * tient jamais en mémoire.
 *
 * Les textes sont toujours écrits comme du texte : une saisie qui commence par « = » ne devient
 * pas une formule dans le tableur de celui qui ouvre le fichier.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workbook_writer {
    /** @var Writer */
    private $writer;

    /** @var Style Style de la ligne d'en-tête. */
    private $bold;

    /** @var bool Aucune feuille encore ouverte. */
    private $first = true;

    /**
     * Ouvre le fichier.
     *
     * @param string $path
     */
    public function __construct(string $path) {
        $this->writer = new Writer();
        $this->writer->openToFile($path);
        $this->bold = (new Style())->setFontBold();
    }

    /**
     * Ouvre une nouvelle feuille et y écrit des lignes, la première en gras (en-tête). D'autres
     * lignes peuvent ensuite être ajoutées à cette feuille par row().
     *
     * @param string $title Titre (tronqué à 31 caractères, sans les caractères interdits par Excel).
     * @param iterable $rows
     * @return void
     */
    public function sheet(string $title, iterable $rows): void {
        if (!$this->first) {
            $this->writer->addNewSheetAndMakeItCurrent();
        }
        $this->first = false;
        $this->writer->getCurrentSheet()->setName(
            \core_text::substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], ' ', $title), 0, 31)
        );
        $header = true;
        foreach ($rows as $values) {
            $this->row($values, $header);
            $header = false;
        }
    }

    /**
     * Ajoute une ligne à la feuille courante.
     *
     * @param array $values Nombres (int, float) ou textes.
     * @param bool $header En gras.
     * @return void
     */
    public function row(array $values, bool $header = false): void {
        $cells = [];
        foreach (array_values($values) as $value) {
            $cells[] = is_int($value) || is_float($value) ? new NumericCell($value, null) : new StringCell((string) $value, null);
        }
        $this->writer->addRow(new Row($cells, $header ? $this->bold : null));
    }

    /**
     * Termine le fichier.
     *
     * @return void
     */
    public function close(): void {
        $this->writer->close();
    }
}
