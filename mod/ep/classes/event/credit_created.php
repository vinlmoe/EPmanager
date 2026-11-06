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

namespace mod_ep\event;

/**
 * Enseignement personnalisé demandé : création d'un crédit. « other » porte l'origine (origin) :
 * registration (inscription à un EP du catalogue), declaration (déclaration hors catalogue),
 * deve (saisi par la DEVE) ou stage (attribution automatique depuis un stage complémentaire).
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_created extends credit_event_base {
    /** @var string Lettre CRUD de l'événement. */
    const CRUD = 'c';

    /** @var int Niveau éducatif de l'événement. */
    const EDULEVEL = self::LEVEL_OTHER;

    /**
     * Description pour les journaux, adaptée à l'origine du crédit.
     *
     * @return string
     */
    public function get_description() {
        switch ($this->other['origin'] ?? '') {
            case 'registration':
                return $this->describe("demandé, par inscription à un EP du catalogue,");
            case 'declaration':
                return $this->describe("demandé, par déclaration hors catalogue,");
            case 'deve':
                return $this->describe("saisi, en tant que DEVE,");
            case 'added':
                return $this->describe("inscrit, depuis la liste des participants de l'EP,");
            case 'stage':
                return $this->describe("attribué automatiquement, d'après un stage complémentaire validé,");
            default:
                return $this->describe('demandé');
        }
    }
}
