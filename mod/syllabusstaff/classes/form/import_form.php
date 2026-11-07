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

namespace mod_syllabusstaff\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Import CSV des compétences d'un référentiel.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_form extends \moodleform {
    /**
     * Définition.
     */
    public function definition() {
        $mform = $this->_form;
        foreach (['id' => PARAM_INT, 'frameworkid' => PARAM_INT, 'action' => PARAM_ALPHA] as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('csvfile', 'mod_syllabusstaff'),
            null,
            ['accepted_types' => ['.csv', '.txt'], 'maxfiles' => 1]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addHelpButton('csvfile', 'csvfile', 'mod_syllabusstaff');
        $this->add_action_buttons(false, get_string('import'));
    }
}
