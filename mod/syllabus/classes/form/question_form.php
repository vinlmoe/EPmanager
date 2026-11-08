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
require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

/**
 * Question d'évaluation : posée par l'enseignant pour son module, ou commune (DEVE) pour tous les
 * modules ou pour un type de module ($customdata['common']).
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_form extends \moodleform {
    /**
     * Définition du formulaire.
     */
    public function definition() {
        $mform = $this->_form;
        $common = !empty($this->_customdata['common']);

        foreach (['id', 'questionid'] as $hidden) {
            $mform->addElement('hidden', $hidden);
            $mform->setType($hidden, PARAM_INT);
        }
        $mform->addElement('hidden', 'action', 'savequestion');
        $mform->setType('action', PARAM_ALPHA);

        if ($common) {
            $mform->addElement('select', 'origin', get_string('questionscope', 'mod_syllabus'), [
                'common' => get_string('scope_common', 'mod_syllabus'),
                'type' => get_string('scope_type', 'mod_syllabus'),
            ]);
            $types = $this->_customdata['types'] ?? [];
            $mform->addElement('select', 'typeid', get_string('moduletype', 'mod_syllabus'), [0 => '—'] + $types);
            $mform->hideIf('typeid', 'origin', 'eq', 'common');
        }
        $mform->addElement('textarea', 'text', get_string('questiontext', 'mod_syllabus'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('text', PARAM_TEXT);
        $mform->addRule('text', null, 'required', null, 'client');
        $mform->addElement('select', 'kind', get_string('questionkind', 'mod_syllabus'), syllabus_question_kinds());
        $mform->addHelpButton('kind', 'questionkind', 'mod_syllabus');
        $mform->addElement('textarea', 'options', get_string('questionoptions', 'mod_syllabus'), ['rows' => 4, 'cols' => 40]);
        $mform->setType('options', PARAM_TEXT);
        $mform->hideIf('options', 'kind', 'neq', 'choice');
        $mform->addElement('advcheckbox', 'required', get_string('questionrequired', 'mod_syllabus'));
        $mform->setDefault('required', 1);
        if ($common) {
            $mform->addElement('advcheckbox', 'enabled', get_string('questionenabled', 'mod_syllabus'));
            $mform->setDefault('enabled', 1);
        }
        $this->add_action_buttons(true, get_string('savequestion', 'mod_syllabus'));
    }

    /**
     * Une question à choix a au moins deux choix ; une question de type vise un type.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['kind'] ?? '') === 'choice' && count(syllabus_question_options((object) $data)) < 2) {
            $errors['options'] = get_string('errorchoices', 'mod_syllabus');
        }
        if (!empty($this->_customdata['common']) && ($data['origin'] ?? '') === 'type' && empty($data['typeid'])) {
            $errors['typeid'] = get_string('errortype', 'mod_syllabus');
        }
        return $errors;
    }
}
