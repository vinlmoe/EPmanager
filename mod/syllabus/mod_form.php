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

/**
 * Réglages de l'activité : caractéristiques du module et fenêtre d'évaluation.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

/**
 * Formulaire des réglages de l'activité.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_syllabus_mod_form extends moodleform_mod {
    /**
     * Définition du formulaire.
     */
    public function definition() {
        global $COURSE, $DB;
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('modulename_field', 'mod_syllabus'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->setDefault('name', $COURSE->fullname);
        $this->standard_intro_elements();

        $mform->addElement('header', 'characteristicshdr', get_string('characteristics', 'mod_syllabus'));
        $mform->setExpanded('characteristicshdr');
        $mform->addElement('text', 'code', get_string('code', 'mod_syllabus'), ['size' => '20']);
        $mform->setType('code', PARAM_TEXT);
        $mform->setDefault('code', $COURSE->shortname);
        $mform->addElement('float', 'ects', get_string('ects', 'mod_syllabus'), ['size' => '6']);
        $mform->setDefault('ects', 0);
        // Période : choisie parmi celles déjà utilisées sur le site (S1, S2...), ou saisie. Une même
        // période écrite de deux façons ferait deux colonnes dans la frise, le livret et la matrice.
        $periods = $DB->get_fieldset_sql("SELECT DISTINCT semester FROM {syllabus} WHERE semester <> '' ORDER BY semester");
        natcasesort($periods);
        $mform->addElement(
            'autocomplete',
            'semester',
            get_string('semester', 'mod_syllabus'),
            ['' => ''] + array_combine($periods, $periods),
            ['tags' => true, 'multiple' => false,
            'noselectionstring' => get_string(
                'periodnone',
                'mod_syllabus'
            )]
        );
        $mform->setType('semester', PARAM_TEXT);
        $mform->addHelpButton('semester', 'semester', 'mod_syllabus');
        $mform->addElement('select', 'studyyear', get_string('studyyear', 'mod_syllabus'), syllabus_studyyear_options());
        $mform->addElement('select', 'typeid', get_string('moduletype', 'mod_syllabus'), syllabus_type_options());
        $mform->addHelpButton('typeid', 'moduletype', 'mod_syllabus');

        $mform->addElement('header', 'evaluationhdr', get_string('evaluation', 'mod_syllabus'));
        $mform->addElement('static', 'evaluationnote', '', get_string('evalwindownote', 'mod_syllabus'));
        $mform->addElement('date_time_selector', 'evalopen', get_string('evalopen', 'mod_syllabus'), ['optional' => true]);
        $mform->addHelpButton('evalopen', 'evalopen', 'mod_syllabus');
        $mform->addElement('date_time_selector', 'evalclose', get_string('evalclose', 'mod_syllabus'), ['optional' => true]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Contrôles : ECTS positifs, fermeture après l'ouverture.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((float) ($data['ects'] ?? 0) < 0) {
            $errors['ects'] = get_string('errornegative', 'mod_syllabus');
        }
        if (!empty($data['evalclose']) && empty($data['evalopen'])) {
            $errors['evalopen'] = get_string('erroropenrequired', 'mod_syllabus');
        }
        if (!empty($data['evalopen']) && !empty($data['evalclose']) && $data['evalclose'] <= $data['evalopen']) {
            $errors['evalclose'] = get_string('errorcloseafteropen', 'mod_syllabus');
        }
        return $errors;
    }
}
