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

namespace mod_ep;

/**
 * Suite donnée dans le catalogue aux soumissions et décisions sur la fiche d'un EP (voir
 * ep_apply_syllabus_decision()).
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Fiche d'un EP soumise à la DEVE : une proposition en brouillon devient « proposée ».
     *
     * @param \mod_syllabus\event\version_submitted $event
     */
    public static function syllabus_version_submitted(\mod_syllabus\event\version_submitted $event): void {
        self::apply($event->other, 'submit');
    }

    /**
     * Décision de la DEVE sur la fiche d'un EP.
     *
     * @param \mod_syllabus\event\version_reviewed $event
     */
    public static function syllabus_version_reviewed(\mod_syllabus\event\version_reviewed $event): void {
        self::apply($event->other, (string) ($event->other['decision'] ?? ''));
    }

    /**
     * Applique la décision si la fiche est celle d'un EP.
     *
     * @param array $other Données de l'événement.
     * @param string $decision
     */
    protected static function apply(array $other, string $decision): void {
        global $CFG;

        if (($other['component'] ?? '') !== 'mod_ep' || empty($other['itemid'])) {
            return;
        }
        require_once($CFG->dirroot . '/mod/ep/locallib.php');
        ep_apply_syllabus_decision((int) $other['itemid'], $decision);
    }
}
