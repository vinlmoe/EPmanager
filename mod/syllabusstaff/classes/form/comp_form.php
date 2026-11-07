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
 * Compétence : parent, code, intitulé, description. Données attendues : parents (id => libellé).
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comp_form extends \moodleform {
    /**
     * Définition.
     */
    public function definition() {
        $mform = $this->_form;
        $hidden = ['id' => PARAM_INT, 'frameworkid' => PARAM_INT, 'compid' => PARAM_INT, 'action' => PARAM_ALPHA];
        foreach ($hidden as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }
        $mform->addElement(
            'select',
            'parentid',
            get_string('parentcompetency', 'mod_syllabusstaff'),
            [0 => get_string('toplevel', 'mod_syllabusstaff')] + ($this->_customdata['parents'] ?? [])
        );
        $mform->addElement('text', 'idnumber', get_string('code', 'mod_syllabusstaff'), ['size' => 20]);
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addRule('idnumber', null, 'required', null, 'client');
        $mform->addElement('text', 'name', get_string('competency', 'mod_syllabus'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
