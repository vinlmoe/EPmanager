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

namespace mod_ep\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');


/**
 * Caractéristiques d'un EP proposé par un enseignant (ou encore en brouillon) : intitulé, type,
 * ECTS, places, années, description. Sa fiche (objectifs, programme...) se rédige ensuite dans
 * son activité « Syllabus du module » (mod_syllabus).
 *
 * Données attendues ($customdata) :
 *  - types (array id => stdClass) : types auxquels l'EP peut être rattaché.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syllabus_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'activityid');
        $mform->setType('activityid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('catalogactivity', 'mod_ep'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $typeoptions = [];
        foreach ($this->_customdata['types'] ?? [] as $type) {
            $typeoptions[$type->id] = format_string($type->name);
        }
        $mform->addElement('select', 'typeid', get_string('type', 'mod_ep'), $typeoptions);
        $mform->addHelpButton('typeid', 'catalogactivitytype', 'mod_ep');

        $mform->addElement('float', 'ects', get_string('ects', 'mod_ep'), ['size' => '8']);
        $mform->setDefault('ects', 0);
        $mform->addHelpButton('ects', 'catalogactivityects', 'mod_ep');

        $mform->addElement('text', 'capacity', get_string('capacity', 'mod_ep'), ['size' => '8']);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'capacity', 'mod_ep');

        $mform->addElement('select', 'minstudyyear', get_string('minstudyyear', 'mod_ep'), ep_studyyear_options());
        $mform->setDefault('minstudyyear', 0);
        $mform->addElement('select', 'maxstudyyear', get_string('maxstudyyear', 'mod_ep'), ep_studyyear_options());
        $mform->setDefault('maxstudyyear', 0);

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('proposalcontinue', 'mod_ep'));
    }

    /**
     * Vérifie les caractéristiques de l'EP proposé : un EP sans ECTS n'a rien à donner à
     * l'étudiant, une plage d'années à l'envers n'ouvrirait l'EP à personne.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((float) ($data['ects'] ?? 0) <= 0) {
            $errors['ects'] = get_string('errorpositiveects', 'mod_ep');
        }
        if ((int) ($data['capacity'] ?? 0) < 0) {
            $errors['capacity'] = get_string('errornegativecapacity', 'mod_ep');
        }
        if (
            !empty($data['minstudyyear']) && !empty($data['maxstudyyear'])
                && (int) $data['minstudyyear'] > (int) $data['maxstudyyear']
        ) {
            $errors['maxstudyyear'] = get_string('errorstudyyearrange', 'mod_ep');
        }
        return $errors;
    }
}
