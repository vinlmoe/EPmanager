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
 * The main mod_ep configuration form.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Module instance settings form for mod_ep.
 */
class mod_ep_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        global $COURSE;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('epname', 'mod_ep'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement(
            'select',
            'currentstudyyear',
            get_string('currentstudyyear', 'mod_ep'),
            ep_studyyear_options()
        );
        $mform->addHelpButton('currentstudyyear', 'currentstudyyear', 'mod_ep');
        $mform->setDefault('currentstudyyear', 0);

        $mform->addElement('text', 'mincursusects', get_string('mincursusects', 'mod_ep'), ['size' => '8']);
        $mform->setType('mincursusects', PARAM_FLOAT);
        $mform->setDefault('mincursusects', 0);
        $mform->addHelpButton('mincursusects', 'mincursusects', 'mod_ep');

        // Activité « Gestion des stages » d'où proviennent les EP de type stage. Laisser « toutes
        // celles du cours » convient tant qu'il n'y en a qu'une, ce qui est le cas courant ; le
        // choix explicite sert aux cours qui en portent plusieurs (par exemple une activité
        // d'archive à ne pas recompter).
        $stageoptions = [0 => get_string('stagesourceall', 'mod_ep')];
        $modinfo = get_fast_modinfo($COURSE->id);
        foreach ($modinfo->get_instances_of('stage') as $cminfo) {
            $stageoptions[$cminfo->id] = format_string($cminfo->name);
        }
        $mform->addElement('select', 'stagecmid', get_string('stagesource', 'mod_ep'), $stageoptions);
        $mform->addHelpButton('stagecmid', 'stagesource', 'mod_ep');
        $mform->setDefault('stagecmid', 0);

        $mform->addElement('static', 'typesnotice', '', get_string('typesnotice', 'mod_ep'));

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Le minimum de cursus est un nombre d'ECTS : un nombre négatif n'a pas de sens et ferait
     * passer tous les étudiants pour à jour sans qu'on comprenne pourquoi.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (isset($data['mincursusects']) && (float) $data['mincursusects'] < 0) {
            $errors['mincursusects'] = get_string('errornegativeects', 'mod_ep');
        }
        return $errors;
    }
}
