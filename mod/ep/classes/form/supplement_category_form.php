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

/**
 * Création ou modification d'une catégorie d'éléments du supplément (DEVE) : libellés français
 * et anglais, consigne affichée à l'étudiant, activation et ordre d'affichage.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplement_category_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        foreach (['id', 'categoryid'] as $hidden) {
            $mform->addElement('hidden', $hidden);
            $mform->setType($hidden, PARAM_INT);
        }
        $mform->addElement('hidden', 'action', 'editcategory');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('text', 'name', get_string('typename', 'mod_ep'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Le libellé anglais n'est imprimé que dans un supplément édité en anglais ; à défaut, le
        // libellé français est repris (voir ep_build_supplement()).
        $mform->addElement('text', 'nameen', get_string('supplementcategorynameen', 'mod_ep'), ['size' => 60]);
        $mform->setType('nameen', PARAM_TEXT);
        $mform->addRule('nameen', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('typeinstruction', 'mod_ep'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'mod_ep'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('text', 'sortorder', get_string('sortorder', 'mod_ep'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Refuse un libellé fait seulement d'espaces : ep_save_supplement_category() l'enregistrerait
     * vide, et la catégorie ne serait plus reconnaissable dans la liste de l'étudiant.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = get_string('required');
        }
        return $errors;
    }
}
