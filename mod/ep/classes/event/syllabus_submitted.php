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
 * Syllabus soumis à la DEVE : proposition d'un nouvel EP ou mise à jour d'un EP publié.
 *
 * @package mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syllabus_submitted extends syllabus_event_base {
    /**
     * Description pour les journaux.
     *
     * @return string
     */
    public function get_description() {
        return "L'utilisateur #{$this->userid} a soumis la version #{$this->objectid} du syllabus de l'EP du catalogue "
            . "#{$this->other['activityid']}.";
    }
}
