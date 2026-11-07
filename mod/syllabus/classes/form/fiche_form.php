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
 * Rédaction de la fiche d'un module : rubriques du modèle ECTS, langue, travail personnel et
 * programme des séances (sans date : la fiche décrit le module, pas le calendrier d'une année).
 *
 * Données attendues ($customdata) : teachers (userid => nom, intervenants possibles),
 * sessioncount (lignes de séances à afficher), canpublish (la soumission publie directement).
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fiche_form extends \moodleform {
    /**
     * Définition du formulaire.
     */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'fichehdr', get_string('fiche', 'mod_syllabus'));
        $mform->setExpanded('fichehdr');
        foreach (syllabus_text_fields() as $field => $label) {
            $mform->addElement('textarea', $field, $label, ['rows' => 5, 'cols' => 60]);
            $mform->setType($field, PARAM_TEXT);
        }
        $mform->addHelpButton('outcomes', 'field_outcomes', 'mod_syllabus');
        $mform->addElement('text', 'language', get_string('language', 'mod_syllabus'), ['size' => '30']);
        $mform->setType('language', PARAM_TEXT);
        $mform->addRule('language', get_string('maximumchars', '', 50), 'maxlength', 50, 'client');

        $mform->addElement('header', 'sessionshdr', get_string('sessions', 'mod_syllabus'));
        $mform->setExpanded('sessionshdr');
        $mform->addElement('static', 'sessionshelp', '', get_string('sessions_help', 'mod_syllabus'));
        $teacheroptions = [0 => '—'] + ($this->_customdata['teachers'] ?? []);
        // Les champs d'une ligne sont groupés sous sessionrow[n][...] : un groupe qui préfixe ses
        // éléments est numéroté par repeat_elements() d'après sa ligne.
        $row = [
            $mform->createElement('text', 'sessiontitle', get_string('sessiontitle', 'mod_syllabus'), ['size' => '30',
                'placeholder' => get_string('sessiontitle', 'mod_syllabus')]),
            $mform->createElement(
                'select',
                'sessionkind',
                get_string('sessionkind', 'mod_syllabus'),
                syllabus_session_kind_options()
            ),
            $mform->createElement('float', 'sessionduration', get_string('sessionduration', 'mod_syllabus'), ['size' => '5',
                'placeholder' => get_string('hoursshort', 'mod_syllabus')]),
            $mform->createElement('select', 'sessionteacher', get_string('sessionteacher', 'mod_syllabus'), $teacheroptions),
            $mform->createElement(
                'text',
                'sessionteachername',
                get_string('sessionteachername', 'mod_syllabus'),
                ['size' => '20', 'placeholder' => get_string('sessionteachername', 'mod_syllabus')]
            ),
        ];
        $elements = [$mform->createElement(
            'group',
            'sessionrow',
            get_string('sessionn', 'mod_syllabus', '{no}'),
            $row,
            ' ',
            true
        )];
        $options = [
            'sessionrow[sessiontitle]' => ['type' => PARAM_TEXT],
            'sessionrow[sessionkind]' => ['type' => PARAM_ALPHA],
            'sessionrow[sessionteacher]' => ['type' => PARAM_INT],
            'sessionrow[sessionteachername]' => ['type' => PARAM_TEXT],
        ];
        $this->repeat_elements(
            $elements,
            max(1, (int) ($this->_customdata['sessioncount'] ?? 3)),
            $options,
            'sessionrepeats',
            'addsessions',
            3,
            get_string('addsessions', 'mod_syllabus'),
            true
        );
        // Le travail personnel s'ajoute aux séances dans le volume horaire : il est saisi avec elles.
        $mform->addElement('float', 'personalhours', get_string('personalhours', 'mod_syllabus'), ['size' => '8']);
        $mform->setDefault('personalhours', 0);

        $submitlabel = !empty($this->_customdata['canpublish'])
            ? get_string('savepublish', 'mod_syllabus') : get_string('savesubmit', 'mod_syllabus');
        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savedraft', 'mod_syllabus')),
            $mform->createElement('submit', 'submitforreview', $submitlabel),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Durées positives ; une fiche soumise (ou publiée) a ses rubriques essentielles et au moins
     * une séance ou des heures de travail personnel. Le brouillon reste libre.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((float) ($data['personalhours'] ?? 0) < 0) {
            $errors['personalhours'] = get_string('errornegative', 'mod_syllabus');
        }
        foreach ($data['sessionrow'] ?? [] as $index => $row) {
            if ((float) ($row['sessionduration'] ?? 0) < 0) {
                $errors['sessionrow[' . $index . ']'] = get_string('errornegative', 'mod_syllabus');
            }
        }
        if (!empty($data['submitforreview'])) {
            foreach (['objectives', 'outcomes', 'content', 'assessment'] as $field) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    $errors[$field] = get_string('requiredtosubmit', 'mod_syllabus');
                }
            }
            $titled = array_filter($data['sessionrow'] ?? [], fn($row) => trim((string) ($row['sessiontitle'] ?? '')) !== '');
            if (!$titled && (float) ($data['personalhours'] ?? 0) <= 0) {
                $errors['sessionrow[0]'] = get_string('errornosessions', 'mod_syllabus');
            }
        }
        return $errors;
    }
}
