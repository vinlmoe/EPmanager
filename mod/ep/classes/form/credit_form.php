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
 * Déclaration par l'étudiant d'un enseignement personnalisé hors catalogue (engagement étudiant,
 * expérience professionnelle, sport, académique externe) : ce qu'il a fait, l'année d'étude à
 * laquelle le rattacher, les ECTS demandés et les justificatifs à l'appui. La demande est
 * ensuite soumise à son enseignant référent (voir ep_can_validate_credit()).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_form extends \moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $ep = $this->_customdata['ep'];
        $types = $this->_customdata['types'];
        $fileoptions = $this->_customdata['fileoptions'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'creditid');
        $mform->setType('creditid', PARAM_INT);

        $typeoptions = [];
        foreach ($types as $type) {
            $typeoptions[$type->id] = ep_type_option_label($type);
        }
        $mform->addElement('select', 'typeid', get_string('type', 'mod_ep'), $typeoptions);
        $mform->addRule('typeid', null, 'required', null, 'client');
        $mform->addHelpButton('typeid', 'type', 'mod_ep');

        // Consigne propre à chaque type, telle que la DEVE l'a rédigée : affichée en bloc plutôt
        // que type par type, un formulaire qui change de contenu au fil des choix étant plus
        // déroutant qu'utile pour trois lignes de consigne.
        $descriptions = [];
        foreach ($types as $type) {
            if (trim((string) $type->description) !== '') {
                $descriptions[] = \html_writer::tag('dt', format_string($type->name))
                    . \html_writer::tag('dd', format_text($type->description, FORMAT_PLAIN));
            }
        }
        if (!empty($descriptions)) {
            $mform->addElement('static', 'typedescriptions', '',
                \html_writer::tag('dl', implode('', $descriptions)));
        }

        $mform->addElement('text', 'name', get_string('creditname', 'mod_ep'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('creditdescription', 'mod_ep'),
            ['rows' => 6, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'creditdescription', 'mod_ep');

        $mform->addElement('select', 'studyyear', get_string('studyyear', 'mod_ep'),
            ep_studyyear_selectable_options($ep));
        $mform->setDefault('studyyear', (int) $ep->currentstudyyear);
        $mform->addHelpButton('studyyear', 'studyyear', 'mod_ep');

        $mform->addElement('text', 'claimedects', get_string('claimedects', 'mod_ep'), ['size' => '8']);
        $mform->setType('claimedects', PARAM_FLOAT);
        $mform->setDefault('claimedects', 0);
        $mform->addHelpButton('claimedects', 'claimedects', 'mod_ep');

        $mform->addElement('filemanager', 'evidence', get_string('evidencefiles', 'mod_ep'), null, $fileoptions);
        $mform->addHelpButton('evidence', 'evidencefiles', 'mod_ep');

        $this->add_action_buttons();
    }

    /**
     * Une demande sans ECTS n'a rien à valider, et un nombre négatif retirerait des ECTS acquis
     * par ailleurs : dans les deux cas la demande est refusée à la saisie plutôt qu'enregistrée
     * puis rejetée par le validateur.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((float) $data['claimedects'] <= 0) {
            $errors['claimedects'] = get_string('errorpositiveects', 'mod_ep');
        }
        return $errors;
    }
}
