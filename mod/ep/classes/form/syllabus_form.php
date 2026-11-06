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

use mod_ep\output\syllabus_view;

/**
 * Rédaction du syllabus d'un EP du catalogue : proposition d'un nouvel EP par un enseignant (avec
 * ses caractéristiques), ou mise à jour de la fiche d'un EP existant par son responsable ou la
 * DEVE.
 *
 * Données attendues ($customdata) :
 *  - activityfields (bool) : affiche les caractéristiques de l'EP. Seulement pour une création ou
 *    un EP encore en brouillon ou proposé : une fois l'EP publié, ses caractéristiques relèvent de
 *    la DEVE, dans la gestion du catalogue ;
 *  - types (array id => stdClass) : types auxquels l'EP peut être rattaché ;
 *  - teachers (array userid => nom) : enseignants du cours, intervenants possibles des séances ;
 *  - sessioncount (int) : nombre de lignes de séances à afficher.
 *
 * Les séances reviennent dans sessionrow[n] (sessiontitle, sessionkind, sessionduration,
 * sessionteacher, sessionteachername) et sessiondate[n].
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syllabus_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $activityfields = !empty($this->_customdata['activityfields']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'activityid');
        $mform->setType('activityid', PARAM_INT);

        // 1. Caractéristiques de l'EP proposé.
        if ($activityfields) {
            $mform->addElement('header', 'activityhdr', get_string('catalogactivity', 'mod_ep'));

            $mform->addElement('text', 'name', get_string('catalogactivity', 'mod_ep'), ['size' => '64']);
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', null, 'required', null, 'client');
            $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

            $typeoptions = [];
            foreach ($this->_customdata['types'] ?? [] as $type) {
                $typeoptions[$type->id] = format_string($type->name);
            }
            $mform->addElement('select', 'typeid', get_string('type', 'mod_ep'), $typeoptions);
            $mform->addHelpButton('typeid', 'catalogactivitytype', 'mod_ep');

            $mform->addElement('float', 'ects', get_string('ects', 'mod_ep'), ['size' => '8']);
            $mform->setDefault('ects', 0);
            $mform->addHelpButton('ects', 'catalogactivityects', 'mod_ep');

            $mform->addElement('text', 'capacity', get_string('capacity', 'mod_ep'), ['size' => '8']);
            $mform->setType('capacity', PARAM_INT);
            $mform->setDefault('capacity', 0);
            $mform->addHelpButton('capacity', 'capacity', 'mod_ep');

            $mform->addElement('select', 'minstudyyear', get_string('minstudyyear', 'mod_ep'), ep_studyyear_options());
            $mform->setDefault('minstudyyear', 0);
            $mform->addElement('select', 'maxstudyyear', get_string('maxstudyyear', 'mod_ep'), ep_studyyear_options());
            $mform->setDefault('maxstudyyear', 0);

            $mform->addElement('textarea', 'description', get_string('description'), ['rows' => 4, 'cols' => 60]);
            $mform->setType('description', PARAM_TEXT);
        }

        // 2. Rubriques du syllabus, dans l'ordre de la fiche ECTS.
        $mform->addElement('header', 'syllabushdr', get_string('syllabus', 'mod_ep'));
        $mform->setExpanded('syllabushdr');
        $helps = ['outcomes' => 'syllabusoutcomes', 'assessment' => 'syllabusassessment'];
        foreach (syllabus_view::field_labels() as $field => $label) {
            $mform->addElement('textarea', $field, $label, ['rows' => 5, 'cols' => 60]);
            $mform->setType($field, PARAM_TEXT);
            if (isset($helps[$field])) {
                $mform->addHelpButton($field, $helps[$field], 'mod_ep');
            }
        }

        $mform->addElement('text', 'language', get_string('syllabuslanguage', 'mod_ep'), ['size' => '30']);
        $mform->setType('language', PARAM_TEXT);
        $mform->addRule('language', get_string('maximumchars', '', 50), 'maxlength', 50, 'client');

        $mform->addElement('float', 'personalhours', get_string('syllabuspersonalhours', 'mod_ep'), ['size' => '8']);
        $mform->setDefault('personalhours', 0);

        // 3. Programme des séances : une ligne par séance, des lignes vides en plus à la demande.
        $mform->addElement('header', 'sessionshdr', get_string('sessions', 'mod_ep'));
        $mform->setExpanded('sessionshdr');
        $mform->addElement('static', 'sessionshelp', '', get_string('sessions_help', 'mod_ep'));

        $teacheroptions = [0 => '—'] + ($this->_customdata['teachers'] ?? []);
        // Les champs d'une ligne sont groupés sous sessionrow[n][...] : un groupe qui préfixe ses
        // éléments est numéroté par repeat_elements() d'après sa ligne, alors qu'un groupe sans
        // préfixe l'est d'après son rang parmi tous les éléments répétés. La date reste hors du
        // groupe : un date_selector est lui-même un groupe, et QuickForm ne relit pas les valeurs
        // d'un groupe imbriqué dans un autre.
        $row = [
            $mform->createElement('text', 'sessiontitle', get_string('sessiontitle', 'mod_ep'), ['size' => '30']),
            $mform->createElement('select', 'sessionkind', get_string('sessionkind', 'mod_ep'), ep_session_kind_options()),
            $mform->createElement('float', 'sessionduration', get_string('sessionduration', 'mod_ep'), ['size' => '5']),
            $mform->createElement('select', 'sessionteacher', get_string('sessionteacher', 'mod_ep'), $teacheroptions),
            $mform->createElement('text', 'sessionteachername', get_string('sessionteachername', 'mod_ep'), ['size' => '20']),
        ];
        $elements = [
            $mform->createElement('group', 'sessionrow', get_string('sessiontitle', 'mod_ep') . ' {no}', $row, ' ', true),
            $mform->createElement('date_selector', 'sessiondate', get_string('sessiondate', 'mod_ep'), ['optional' => true]),
        ];
        // Pas de valeur par défaut pour les champs du groupe : enregistrée sous son nom à plat
        // (sessionrow[n][champ]), elle l'emporterait sur les valeurs passées par set_data(). Une
        // ligne vide prend la première nature et aucun intervenant, une durée vide vaut zéro.
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
            get_string('addsessions', 'mod_ep'),
            true
        );

        // 4. Boutons : enregistrer le brouillon, le soumettre à la DEVE, ou abandonner. Groupés
        // comme ceux de add_action_buttons(), pour la même présentation.
        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savedraft', 'mod_ep')),
            $mform->createElement('submit', 'submittodeve', get_string('submitsyllabus', 'mod_ep')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Vérifie les caractéristiques de l'EP proposé (un EP sans ECTS n'a rien à donner à
     * l'étudiant, une plage d'années à l'envers n'ouvrirait l'EP à personne) et les durées, qui
     * entrent dans le volume horaire.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($this->_customdata['activityfields'])) {
            if ((float) ($data['ects'] ?? 0) <= 0) {
                $errors['ects'] = get_string('errorpositiveects', 'mod_ep');
            }
            if ((int) ($data['capacity'] ?? 0) < 0) {
                $errors['capacity'] = get_string('errornegativecapacity', 'mod_ep');
            }
            if (
                !empty($data['minstudyyear']) && !empty($data['maxstudyyear'])
                    && (int) $data['minstudyyear'] > (int) $data['maxstudyyear']
            ) {
                $errors['maxstudyyear'] = get_string('errorstudyyearrange', 'mod_ep');
            }
        }
        if ((float) ($data['personalhours'] ?? 0) < 0) {
            $errors['personalhours'] = get_string('errornegativeduration', 'mod_ep');
        }
        foreach ($data['sessionrow'] ?? [] as $index => $row) {
            if ((float) ($row['sessionduration'] ?? 0) < 0) {
                // Le champ est dans le groupe de sa ligne : l'erreur s'affiche sur le groupe.
                $errors['sessionrow[' . $index . ']'] = get_string('errornegativeduration', 'mod_ep');
            }
        }

        return $errors;
    }
}
