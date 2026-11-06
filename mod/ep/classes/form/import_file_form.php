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
 * Dépôt d'un fichier à importer (CSV du catalogue, des séances ou des crédits, paramétrage JSON,
 * archive d'export), avec au besoin le choix des parties à importer.
 *
 * Données personnalisées :
 * - filelabel : libellé du champ ;
 * - types : extensions acceptées ;
 * - help : [identifiant, composant] de l'aide du champ, ou null ;
 * - hidden : nom => type PARAM des champs cachés ;
 * - parts : clé => libellé des parties à cocher (toutes cochées par défaut), ou [] ;
 * - partslabel, partshelp : libellé et aide du groupe de parties ;
 * - submit : libellé du bouton.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_file_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata + ['types' => ['.csv', '.txt'], 'help' => null, 'hidden' => [], 'parts' => [],
            'partslabel' => '', 'partshelp' => null, 'submit' => get_string('preview')];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        foreach ($custom['hidden'] as $name => $type) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, $type);
        }

        $mform->addElement(
            'filepicker',
            'importfile',
            $custom['filelabel'],
            null,
            ['accepted_types' => $custom['types'], 'maxfiles' => 1]
        );
        $mform->addRule('importfile', null, 'required', null, 'client');
        if ($custom['help']) {
            $mform->addHelpButton('importfile', $custom['help'][0], $custom['help'][1]);
        }

        if ($custom['parts']) {
            $group = [];
            foreach ($custom['parts'] as $key => $label) {
                $group[] = $mform->createElement('advcheckbox', $key, '', $label, ['group' => 1]);
                $mform->setDefault('parts[' . $key . ']', 1);
            }
            $mform->addGroup($group, 'parts', $custom['partslabel'], \html_writer::empty_tag('br'));
            if ($custom['partshelp']) {
                $mform->addHelpButton('parts', $custom['partshelp'][0], $custom['partshelp'][1]);
            }
        }

        $this->add_action_buttons(true, $custom['submit']);
    }

    /**
     * Au moins une partie cochée, quand le formulaire en propose.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($this->_customdata['parts']) && !array_filter($data['parts'] ?? [])) {
            $errors['parts'] = get_string('archivenoparts', 'mod_ep');
        }
        return $errors;
    }
}
