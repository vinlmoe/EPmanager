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
 * Demande par l'étudiant d'ajout d'un élément à son supplément au diplôme (engagement, distinction,
 * certification, mobilité...), ou sa modification tant qu'elle est en attente : catégorie,
 * intitulé (en français et, pour un supplément édité en anglais, en anglais), période et preuves
 * en PDF, selon ce que la DEVE exige pour l'instance.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplement_item_form extends \moodleform {
    /**
     * Champs du formulaire.
     */
    public function definition() {
        $mform = $this->_form;
        $categories = $this->_customdata['categories'];
        $evidence = (int) $this->_customdata['evidence'];
        $fileoptions = $this->_customdata['fileoptions'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'itemid');
        $mform->setType('itemid', PARAM_INT);

        $options = [];
        $descriptions = [];
        foreach ($categories as $category) {
            $options[$category->id] = format_string($category->name);
            if (trim((string) $category->description) !== '') {
                $descriptions[] = \html_writer::tag('dt', format_string($category->name))
                    . \html_writer::tag('dd', format_text($category->description, FORMAT_PLAIN));
            }
        }
        $mform->addElement('select', 'categoryid', get_string('supplementcategory', 'mod_ep'), $options);
        $mform->addRule('categoryid', null, 'required', null, 'client');

        // Consigne de chaque catégorie, telle que la DEVE l'a rédigée : affichée d'un bloc, comme
        // celle des types dans la déclaration d'un EP, plutôt qu'au fil du choix (ce qui
        // demanderait du JavaScript pour quelques lignes).
        if ($descriptions) {
            $mform->addElement('static', 'categorydescriptions', '', \html_writer::tag('dl', implode('', $descriptions)));
        }

        $mform->addElement('text', 'title', get_string('supplementitemtitle', 'mod_ep'), ['size' => '64']);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'titleen', get_string('supplementitemtitleen', 'mod_ep'), ['size' => '64']);
        $mform->setType('titleen', PARAM_TEXT);
        $mform->addRule('titleen', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('titleen', 'supplementitemtitleen', 'mod_ep');

        $mform->addElement(
            'textarea',
            'description',
            get_string('supplementitemdescription', 'mod_ep'),
            ['rows' => 5, 'cols' => 60]
        );
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('date_selector', 'periodstart', get_string('supplementitemperiodstart', 'mod_ep'), ['optional' => true]);
        $mform->addElement('date_selector', 'periodend', get_string('supplementitemperiodend', 'mod_ep'), ['optional' => true]);

        // Sans preuves exigées ni admises, le champ de dépôt n'aurait aucun lecteur : il est omis.
        if ($evidence !== EP_EVIDENCE_NONE) {
            $mform->addElement('filemanager', 'proofs', get_string('supplementproofs', 'mod_ep'), null, $fileoptions);
            $mform->addHelpButton('proofs', 'supplementproofs', 'mod_ep');
        }

        $this->add_action_buttons(true, get_string(
            empty($this->_customdata['editing']) ? 'sendrequest' : 'savechanges',
            empty($this->_customdata['editing']) ? 'mod_ep' : 'moodle'
        ));
    }

    /**
     * Contrôles côté serveur : un intitulé qui ne serait fait que d'espaces, une période à
     * l'envers, des preuves absentes alors que la DEVE les exige. La règle « requis » de QuickForm
     * ne voit pas les fichiers (le champ ne porte que l'identifiant du brouillon) : c'est le
     * contenu de la zone de brouillon qui est compté.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim((string) ($data['title'] ?? '')) === '') {
            $errors['title'] = get_string('required');
        }
        $start = (int) ($data['periodstart'] ?? 0);
        $end = (int) ($data['periodend'] ?? 0);
        if ($start && $end && $end < $start) {
            // Chaîne de mod_stage, dépendance déclarée du module.
            $errors['periodend'] = get_string('errorsupplementperiod', 'mod_ep');
        }
        if ((int) $this->_customdata['evidence'] === EP_EVIDENCE_REQUIRED) {
            $info = empty($data['proofs']) ? null : file_get_draft_area_info($data['proofs']);
            if (empty($info['filecount'])) {
                $errors['proofs'] = get_string('errorsupplementproofrequired', 'mod_ep');
            }
        }
        return $errors;
    }
}
