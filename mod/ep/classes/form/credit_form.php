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
 * laquelle le rattacher, ce qu'il demande et les justificatifs à l'appui. La demande est ensuite
 * soumise à son enseignant référent (voir ep_can_validate_credit()).
 *
 * Ce qu'il y a à saisir dépend de la règle du type choisi (voir ep_type_ects_mode()) : un nombre
 * d'ECTS s'il est laissé à son appréciation, un nombre de semaines si le type se compte à la
 * semaine, et rien du tout si le type accorde un forfait par déclaration. Les champs inutiles
 * disparaissent au choix du type plutôt que d'être présentés puis ignorés.
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

        // Consigne propre à chaque type, telle que la DEVE l'a rédigée, et rappel de ce que ce
        // type rapporte : le tout en bloc, pour que l'étudiant compare les types avant d'en
        // choisir un plutôt que d'avoir à les essayer l'un après l'autre.
        $descriptions = [];
        foreach ($types as $type) {
            $consigne = [];
            if (trim((string) $type->description) !== '') {
                $consigne[] = format_text($type->description, FORMAT_PLAIN);
            }
            // La règle du type est rappelée ici, et pas seulement dans la liste déroulante : c'est
            // ce qui explique pourquoi le champ « ECTS demandés » n'est pas là pour ce type.
            if (ep_type_ects_mode($type) !== EP_ECTS_MODE_FREE) {
                $consigne[] = \html_writer::span(ep_type_ects_rule_label($type), 'text-muted');
            }
            if (!empty($consigne)) {
                $descriptions[] = \html_writer::tag('dt', format_string($type->name))
                    . \html_writer::tag('dd', implode('<br />', $consigne));
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
        $mform->setDefault('studyyear', ep_get_current_studyyear($ep));
        $mform->addHelpButton('studyyear', 'studyyear', 'mod_ep');

        // Les types sont groupés par règle : chaque champ n'apparaît que pour ceux qu'il concerne.
        $bymode = [EP_ECTS_MODE_FREE => [], EP_ECTS_MODE_FLAT => [], EP_ECTS_MODE_WEEKLY => []];
        foreach ($types as $type) {
            $bymode[ep_type_ects_mode($type)][] = $type->id;
        }

        $mform->addElement('text', 'claimedects', get_string('claimedects', 'mod_ep'), ['size' => '8']);
        $mform->setType('claimedects', PARAM_FLOAT);
        $mform->setDefault('claimedects', 0);
        $mform->addHelpButton('claimedects', 'claimedects', 'mod_ep');
        $notfree = array_merge($bymode[EP_ECTS_MODE_FLAT], $bymode[EP_ECTS_MODE_WEEKLY]);
        if (!empty($notfree)) {
            $mform->hideIf('claimedects', 'typeid', 'in', $notfree);
        }

        $mform->addElement('text', 'weeks', get_string('weeks', 'mod_ep'), ['size' => '8']);
        $mform->setType('weeks', PARAM_FLOAT);
        $mform->setDefault('weeks', 0);
        $mform->addHelpButton('weeks', 'weeks', 'mod_ep');
        $notweekly = array_merge($bymode[EP_ECTS_MODE_FREE], $bymode[EP_ECTS_MODE_FLAT]);
        if (!empty($notweekly)) {
            $mform->hideIf('weeks', 'typeid', 'in', $notweekly);
        }

        $mform->addElement('filemanager', 'evidence', get_string('evidencefiles', 'mod_ep'), null, $fileoptions);
        $mform->addHelpButton('evidence', 'evidencefiles', 'mod_ep');

        $this->add_action_buttons();
    }

    /**
     * Une demande sans ECTS n'a rien à valider, et un nombre négatif retirerait des ECTS acquis
     * par ailleurs : dans les deux cas la demande est refusée à la saisie plutôt qu'enregistrée
     * puis rejetée par le validateur. Ce qui est vérifié dépend de la règle du type — le nombre
     * proposé, la durée déclarée, ou le forfait que la DEVE a dû régler.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $types = $this->_customdata['types'];
        $type = $types[$data['typeid']] ?? null;
        if (!$type) {
            $errors['typeid'] = get_string('errorinvalidtype', 'mod_ep');
            return $errors;
        }

        switch (ep_type_ects_mode($type)) {
            case EP_ECTS_MODE_FLAT:
                // Un forfait à 0 est un type que la DEVE n'a pas fini de régler : le dire à
                // l'étudiant vaut mieux que d'enregistrer une demande vide.
                if (!ep_type_ects_rule_is_set($type)) {
                    $errors['typeid'] = get_string('errortypeectsunset', 'mod_ep');
                }
                break;
            case EP_ECTS_MODE_WEEKLY:
                if (!ep_type_ects_rule_is_set($type)) {
                    $errors['typeid'] = get_string('errortypeectsunset', 'mod_ep');
                } else if ((float) $data['weeks'] <= 0) {
                    $errors['weeks'] = get_string('errorpositiveweeks', 'mod_ep');
                }
                break;
            default:
                if ((float) $data['claimedects'] <= 0) {
                    $errors['claimedects'] = get_string('errorpositiveects', 'mod_ep');
                }
        }

        return $errors;
    }
}
