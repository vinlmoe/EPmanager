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

namespace mod_syllabus\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Fenêtre d'évaluation d'un module : ouverture et fermeture.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class window_form extends \moodleform {
    /**
     * Définition du formulaire.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'savewindow');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('date_time_selector', 'evalopen', get_string('evalopen', 'mod_syllabus'), ['optional' => true]);
        $mform->addHelpButton('evalopen', 'evalopen', 'mod_syllabus');
        $mform->addElement('date_time_selector', 'evalclose', get_string('evalclose', 'mod_syllabus'), ['optional' => true]);
        $this->add_action_buttons(false, get_string('savewindow', 'mod_syllabus'));
    }

    /**
     * Fermeture après l'ouverture.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['evalclose']) && empty($data['evalopen'])) {
            $errors['evalopen'] = get_string('erroropenrequired', 'mod_syllabus');
        }
        if (!empty($data['evalopen']) && !empty($data['evalclose']) && $data['evalclose'] <= $data['evalopen']) {
            $errors['evalclose'] = get_string('errorcloseafteropen', 'mod_syllabus');
        }
        return $errors;
    }
}
