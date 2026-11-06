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
 * Réglages des éditions PDF (en-tête, pied de page, logo) et du circuit des propositions
 * (publication directe des mises à jour) d'une instance, par la DEVE.
 *
 * Les deux sujets partagent une page parce qu'ils se règlent une fois, en début d'année, par la
 * même personne ; les séparer obligerait à chercher deux pages rarement visitées.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_settings_form extends \moodleform {
    /**
     * Options de la zone du logo : un seul fichier, dans les formats d'image que TCPDF lit sans
     * extension PHP supplémentaire.
     *
     * @return array
     */
    public static function logo_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => 2 * 1024 * 1024,
            'accepted_types' => ['.png', '.jpg', '.jpeg'],
        ];
    }

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'pdfhdr', get_string('pdfsettings', 'mod_ep'));
        $mform->setExpanded('pdfhdr', true);

        // Texte brut : il est imprimé tel quel dans l'en-tête et le pied de chaque page, sans
        // mise en forme, ligne par ligne.
        $mform->addElement('textarea', 'pdfheader', get_string('pdfheader', 'mod_ep'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('pdfheader', PARAM_TEXT);
        $mform->addElement('textarea', 'pdffooter', get_string('pdffooter', 'mod_ep'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('pdffooter', PARAM_TEXT);

        $mform->addElement('filemanager', 'pdflogo', get_string('pdflogo', 'mod_ep'), null, self::logo_options());

        $mform->addElement('header', 'proposalshdr', get_string('proposals', 'mod_ep'));
        $mform->setExpanded('proposalshdr', true);
        $mform->addElement('advcheckbox', 'proposalsdirectpublish', get_string('proposalsdirectpublish', 'mod_ep'));
        $mform->addHelpButton('proposalsdirectpublish', 'proposalsdirectpublish', 'mod_ep');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
