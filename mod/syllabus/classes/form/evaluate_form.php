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
 * Évaluation d'un module par un étudiant : une question par champ (q{id}), dans l'ordre de
 * passation. Les questions communes (DEVE) et celles de l'enseignant sont présentées en deux
 * parties.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluate_form extends \moodleform {
    /**
     * Définition du formulaire.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('static', 'anonymity', '', get_string('anonymitynotice', 'mod_syllabus'));

        $section = null;
        $number = 0;
        foreach ($this->_customdata['questions'] as $question) {
            $part = $question->origin === 'teacher' ? 'teacher' : 'common';
            if ($part !== $section) {
                $mform->addElement('header', 'part' . $part, get_string('part_' . $part, 'mod_syllabus'));
                $mform->setExpanded('part' . $part);
                $section = $part;
            }
            $number++;
            $name = 'q' . $question->id;
            $label = $number . '. ' . format_string($question->text) . ($question->required ? ' *' : '');
            if ($question->kind === 'text') {
                $mform->addElement('textarea', $name, $label, ['rows' => 4, 'cols' => 60]);
                $mform->setType($name, PARAM_TEXT);
                continue;
            }
            if ($question->kind === 'scale') {
                $choices = [];
                for ($value = 1; $value <= 5; $value++) {
                    $choices[$value] = get_string('scale_' . $value, 'mod_syllabus');
                }
            } else if ($question->kind === 'yesno') {
                $choices = [1 => get_string('yes'), 0 => get_string('no')];
            } else {
                $choices = syllabus_question_options($question);
            }
            $radios = [];
            foreach ($choices as $value => $choicelabel) {
                $radios[] = $mform->createElement('radio', $name, '', s($choicelabel), $value);
            }
            $mform->addGroup($radios, $name . 'group', $label, \html_writer::empty_tag('br'), false);
            $mform->setType($name, PARAM_INT);
        }
        $this->add_action_buttons(true, get_string('submitevaluation', 'mod_syllabus'));
    }

    /**
     * Questions obligatoires renseignées.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach ($this->_customdata['questions'] as $question) {
            $name = 'q' . $question->id;
            $value = $data[$name] ?? null;
            if ($question->required && ($value === null || trim((string) $value) === '')) {
                $errors[$question->kind === 'text' ? $name : $name . 'group'] = get_string('required');
            }
        }
        return $errors;
    }
}
