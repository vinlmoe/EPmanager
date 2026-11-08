<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Copie du paramétrage d'une autre activité : choix de l'activité source, parmi celles que
 * l'utilisateur gère, et des parties à copier.
 *
 * Données personnalisées : sources (cmid => libellé), parts (clé => libellé).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_copy_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'copy');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement(
            'select',
            'sourcecmid',
            get_string('settingssource', 'mod_ep'),
            ['' => get_string('choosedots')] + $this->_customdata['sources']
        );
        $mform->setType('sourcecmid', PARAM_INT);
        $mform->addRule('sourcecmid', null, 'required', null, 'client');

        $group = [];
        foreach ($this->_customdata['parts'] as $key => $label) {
            $group[] = $mform->createElement('advcheckbox', $key, '', $label, ['group' => 2]);
            $mform->setDefault('parts[' . $key . ']', 1);
        }
        $mform->addGroup($group, 'parts', get_string('settingsparts', 'mod_ep'), \html_writer::empty_tag('br'));
        $mform->addHelpButton('parts', 'settingsparts', 'mod_ep');

        $this->add_action_buttons(false, get_string('settingscopyfrom', 'mod_ep'));
    }

    /**
     * Source connue et au moins une partie cochée.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!isset($this->_customdata['sources'][(int) ($data['sourcecmid'] ?? 0)])) {
            $errors['sourcecmid'] = get_string('required');
        }
        if (!array_filter($data['parts'] ?? [])) {
            $errors['parts'] = get_string('archivenoparts', 'mod_ep');
        }
        return $errors;
    }
}
