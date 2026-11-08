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
 * Création / édition d'un EP du catalogue interne (DEVE) : son intitulé, le nombre d'ECTS qu'il
 * porte, les années d'étude auxquelles il s'adresse et son nombre de places.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $types = $this->_customdata['types'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'activityid');
        $mform->setType('activityid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('catalogactivity', 'mod_ep'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 5, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $typeoptions = [];
        foreach ($types as $type) {
            $typeoptions[$type->id] = format_string($type->name);
        }
        $mform->addElement('select', 'typeid', get_string('type', 'mod_ep'), $typeoptions);
        $mform->addHelpButton('typeid', 'catalogactivitytype', 'mod_ep');

        $mform->addElement('text', 'ects', get_string('ects', 'mod_ep'), ['size' => '8']);
        $mform->setType('ects', PARAM_FLOAT);
        $mform->setDefault('ects', 0);
        $mform->addHelpButton('ects', 'catalogactivityects', 'mod_ep');

        $mform->addElement('select', 'minstudyyear', get_string('minstudyyear', 'mod_ep'), ep_studyyear_options());
        $mform->setDefault('minstudyyear', 0);

        $mform->addElement('select', 'maxstudyyear', get_string('maxstudyyear', 'mod_ep'), ep_studyyear_options());
        $mform->setDefault('maxstudyyear', 0);

        $mform->addElement('text', 'capacity', get_string('capacity', 'mod_ep'), ['size' => '8']);
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', 0);
        $mform->addHelpButton('capacity', 'capacity', 'mod_ep');

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'mod_ep'), ['size' => '8']);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);

        $mform->addElement('text', 'code', get_string('activitycode', 'mod_ep'), ['size' => '20']);
        $mform->setType('code', PARAM_TEXT);
        $mform->addRule('code', get_string('maximumchars', '', 30), 'maxlength', 30, 'client');
        $mform->addHelpButton('code', 'activitycode', 'mod_ep');

        $mform->addElement('select', 'state', get_string('activitystate', 'mod_ep'), ep_activity_state_options());
        $mform->setDefault('state', EP_ACTIVITY_PUBLISHED);
        $mform->addHelpButton('state', 'activitystate', 'mod_ep');

        // Période d'inscription et déroulement : facultatifs, chacun peut rester sans borne.
        $mform->addElement(
            'date_time_selector',
            'registrationstart',
            get_string('registrationstart', 'mod_ep'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_time_selector',
            'registrationend',
            get_string('registrationend', 'mod_ep'),
            ['optional' => true]
        );
        $mform->addElement('date_selector', 'datestart', get_string('activitydatestart', 'mod_ep'), ['optional' => true]);
        $mform->addElement('date_selector', 'dateend', get_string('activitydateend', 'mod_ep'), ['optional' => true]);

        // Évaluation : celle du type par défaut, ou une évaluation propre à cet EP.
        $mform->addElement('header', 'gradinghdr', get_string('activitygrading', 'mod_ep'));
        $mform->addElement('select', 'grading', get_string('activitygrading', 'mod_ep'), [
            '' => get_string('activitygrading_type', 'mod_ep', get_string('type', 'mod_ep')),
            EP_GRADING_NONE => get_string('grading_none', 'mod_ep'),
            EP_GRADING_GRADE => get_string('grading_grade', 'mod_ep'),
        ]);
        $mform->addHelpButton('grading', 'activitygrading', 'mod_ep');
        $mform->addElement('text', 'grademax', get_string('grademax', 'mod_ep'), ['size' => '8']);
        $mform->setType('grademax', PARAM_FLOAT);
        $mform->setDefault('grademax', 20);
        $mform->hideIf('grademax', 'grading', 'neq', EP_GRADING_GRADE);
        $mform->addElement('text', 'gradepass', get_string('gradepass', 'mod_ep'), ['size' => '8']);
        $mform->setType('gradepass', PARAM_FLOAT);
        $mform->setDefault('gradepass', 10);
        $mform->hideIf('gradepass', 'grading', 'neq', EP_GRADING_GRADE);

        // Prérequis : EP du catalogue à avoir validés, ECTS déjà retenus.
        $mform->addElement('header', 'prereqhdr', get_string('prerequisites', 'mod_ep'));
        $others = [];
        foreach ($this->_customdata['otheractivities'] ?? [] as $other) {
            $others[$other->id] = format_string($other->name) . ($other->code !== '' ? ' (' . s($other->code) . ')' : '');
        }
        $mform->addElement(
            'autocomplete',
            'prerequisites',
            get_string('prereqactivities', 'mod_ep'),
            $others,
            ['multiple' => true]
        );
        $mform->addHelpButton('prerequisites', 'prereqactivities', 'mod_ep');
        $mform->addElement('text', 'minects', get_string('prereqminects', 'mod_ep'), ['size' => '8']);
        $mform->setType('minects', PARAM_FLOAT);
        $mform->setDefault('minects', 0);
        $mform->addElement('advcheckbox', 'prereqblocking', get_string('prereqblocking', 'mod_ep'));
        $mform->setDefault('prereqblocking', 1);
        $mform->addHelpButton('prereqblocking', 'prereqblocking', 'mod_ep');

        $this->add_action_buttons();
    }

    /**
     * Vérifie la cohérence de la plage d'années et le signe des valeurs chiffrées : un EP sans
     * ECTS n'a rien à donner à l'étudiant qui s'y inscrit.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (
            !empty($data['minstudyyear']) && !empty($data['maxstudyyear'])
                && $data['minstudyyear'] > $data['maxstudyyear']
        ) {
            $errors['maxstudyyear'] = get_string('errorstudyyearrange', 'mod_ep');
        }
        if ((float) $data['ects'] <= 0) {
            $errors['ects'] = get_string('errorpositiveects', 'mod_ep');
        }
        if ((int) $data['capacity'] < 0) {
            $errors['capacity'] = get_string('errornegativecapacity', 'mod_ep');
        }
        if (
            !empty($data['registrationstart']) && !empty($data['registrationend'])
                && $data['registrationend'] <= $data['registrationstart']
        ) {
            $errors['registrationend'] = get_string('errorregistrationperiod', 'mod_ep');
        }
        if (($data['grading'] ?? '') === EP_GRADING_GRADE && (float) $data['gradepass'] > (float) $data['grademax']) {
            $errors['gradepass'] = get_string('errorgradepass', 'mod_ep');
        }
        if ((float) ($data['minects'] ?? 0) < 0) {
            $errors['minects'] = get_string('errornegativeects', 'mod_ep');
        }
        $code = trim((string) ($data['code'] ?? ''));
        if (
            $code !== '' && !empty($this->_customdata['epid'])
                && ep_activity_code_used($this->_customdata['epid'], $code, (int) ($data['activityid'] ?? 0))
        ) {
            $errors['code'] = get_string('erroractivitycodeused', 'mod_ep');
        }

        return $errors;
    }
}
