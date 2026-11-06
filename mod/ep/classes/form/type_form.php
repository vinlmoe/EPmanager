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
 * Création / édition d'un type d'enseignement personnalisé (DEVE) : son libellé et sa consigne,
 * son mode d'acquisition et son circuit de validation, son évaluation, ses justificatifs, ses
 * plafonds d'ECTS et, pour un type stage, son barème et sa portée.
 *
 * Les champs sans objet pour le mode d'acquisition ou l'évaluation choisis sont masqués
 * (hideIf) : ils restent transmis, et ep_save_type() les remet à leur valeur neutre.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class type_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'typeid');
        $mform->setType('typeid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('typename', 'mod_ep'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'code', get_string('typecode', 'mod_ep'), ['size' => '20']);
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addRule('code', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');
        $mform->addHelpButton('code', 'typecode', 'mod_ep');

        $mform->addElement(
            'textarea',
            'description',
            get_string('typeinstruction', 'mod_ep'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'mod_ep'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('select', 'acquisition', get_string('acquisition', 'mod_ep'), ep_acquisition_options());
        $mform->setDefault('acquisition', EP_ACQ_DECLARATION);
        $mform->addHelpButton('acquisition', 'acquisition', 'mod_ep');

        // Toutes les étapes possibles sont proposées, quel que soit le mode d'acquisition : les
        // restreindre selon le mode demanderait du JavaScript. C'est ep_save_type() qui met le
        // circuit en accord avec le mode à l'enregistrement (voir ep_normalise_flow()).
        $flowoptions = [];
        foreach (array_keys(ep_acquisition_options()) as $acquisition) {
            $flowoptions += ep_flow_options($acquisition);
        }
        $mform->addElement('select', 'validationflow', get_string('validationflow', 'mod_ep'), $flowoptions);
        $mform->setDefault('validationflow', EP_ROLE_REFERENT);
        $mform->addHelpButton('validationflow', 'validationflow', 'mod_ep');

        $mform->addElement('select', 'grading', get_string('grading', 'mod_ep'), [
            EP_GRADING_NONE => get_string('grading_none', 'mod_ep'),
            EP_GRADING_GRADE => get_string('grading_grade', 'mod_ep'),
        ]);
        $mform->setDefault('grading', EP_GRADING_NONE);
        $mform->addHelpButton('grading', 'grading', 'mod_ep');

        $mform->addElement('text', 'grademax', get_string('grademax', 'mod_ep'), ['size' => '8']);
        $mform->setType('grademax', PARAM_FLOAT);
        $mform->setDefault('grademax', 20);
        $mform->hideIf('grademax', 'grading', 'neq', EP_GRADING_GRADE);

        $mform->addElement('text', 'gradepass', get_string('gradepass', 'mod_ep'), ['size' => '8']);
        $mform->setType('gradepass', PARAM_FLOAT);
        $mform->setDefault('gradepass', 10);
        $mform->hideIf('gradepass', 'grading', 'neq', EP_GRADING_GRADE);

        $mform->addElement('select', 'evidence', get_string('evidence', 'mod_ep'), ep_evidence_options());
        $mform->setDefault('evidence', EP_EVIDENCE_OPTIONAL);
        $mform->addHelpButton('evidence', 'evidence', 'mod_ep');
        $mform->hideIf('evidence', 'acquisition', 'neq', EP_ACQ_DECLARATION);

        $mform->addElement('select', 'evidencetypes', get_string('evidencetypes', 'mod_ep'), [
            'documents' => get_string('evidencetypes_documents', 'mod_ep'),
            'pdf' => get_string('evidencetypes_pdf', 'mod_ep'),
        ]);
        $mform->setDefault('evidencetypes', 'documents');
        $mform->hideIf('evidencetypes', 'acquisition', 'neq', EP_ACQ_DECLARATION);

        $mform->addElement('text', 'maxclaimedects', get_string('maxclaimedects', 'mod_ep'), ['size' => '8']);
        $mform->setType('maxclaimedects', PARAM_FLOAT);
        $mform->setDefault('maxclaimedects', 0);
        $mform->addHelpButton('maxclaimedects', 'maxclaimedects', 'mod_ep');
        $mform->hideIf('maxclaimedects', 'acquisition', 'neq', EP_ACQ_DECLARATION);

        $mform->addElement('text', 'maxects', get_string('maxects', 'mod_ep'), ['size' => '8']);
        $mform->setType('maxects', PARAM_FLOAT);
        $mform->setDefault('maxects', 0);

        $mform->addElement('text', 'maxectsperyear', get_string('maxectsperyear', 'mod_ep'), ['size' => '8']);
        $mform->setType('maxectsperyear', PARAM_FLOAT);
        $mform->setDefault('maxectsperyear', 0);

        $mform->addElement('text', 'ectsperday', get_string('ectsperday', 'mod_ep'), ['size' => '8']);
        $mform->setType('ectsperday', PARAM_FLOAT);
        $mform->setDefault('ectsperday', 0);
        $mform->hideIf('ectsperday', 'acquisition', 'neq', EP_ACQ_STAGE);

        $mform->addElement('select', 'stagescope', get_string('stagescope', 'mod_ep'), [
            'all' => get_string('stagescope_all', 'mod_ep'),
            'abroad' => get_string('stagescope_abroad', 'mod_ep'),
            'domestic' => get_string('stagescope_domestic', 'mod_ep'),
        ]);
        $mform->setDefault('stagescope', 'all');
        $mform->addHelpButton('stagescope', 'stagescope', 'mod_ep');
        $mform->hideIf('stagescope', 'acquisition', 'neq', EP_ACQ_STAGE);

        $mform->addElement('advcheckbox', 'insupplement', get_string('insupplement', 'mod_ep'));
        $mform->setDefault('insupplement', 1);
        $mform->addHelpButton('insupplement', 'insupplement', 'mod_ep');

        $this->add_action_buttons();
    }

    /**
     * Vérifie le seuil de validation et le signe des valeurs chiffrées. Les champs masqués sont
     * vérifiés aussi : une valeur négative restée dans un champ masqué serait enregistrée telle
     * quelle si l'on changeait plus tard de mode d'acquisition.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim((string) $data['name']) === '') {
            $errors['name'] = get_string('required');
        }
        if ((float) $data['gradepass'] > (float) $data['grademax']) {
            $errors['gradepass'] = get_string('errorgradepass', 'mod_ep');
        } else if ((float) $data['gradepass'] < 0) {
            $errors['gradepass'] = get_string('errorgradeoutofrange', 'mod_ep', (float) $data['grademax']);
        }
        foreach (['maxclaimedects', 'maxects', 'maxectsperyear', 'ectsperday'] as $field) {
            if ((float) $data[$field] < 0) {
                $errors[$field] = get_string('errornegativeects', 'mod_ep');
            }
        }

        return $errors;
    }
}
