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
 * Dépôt du fichier CSV des notes ou des validations des participants d'un EP du catalogue.
 *
 * Le formulaire ne fait que recevoir le fichier : sa lecture (voir \mod_ep\local\grades_importer)
 * produit une prévisualisation, et rien n'est enregistré avant la confirmation qui la suit.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grades_import_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'activityid');
        $mform->setType('activityid', PARAM_INT);

        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('importgradesfile', 'mod_ep'),
            null,
            ['accepted_types' => ['.csv', '.txt'], 'maxfiles' => 1]
        );
        $mform->addRule('csvfile', null, 'required', null, 'client');
        $mform->addHelpButton('csvfile', 'importgrades', 'mod_ep');

        $this->add_action_buttons(true, get_string('preview'));
    }
}
