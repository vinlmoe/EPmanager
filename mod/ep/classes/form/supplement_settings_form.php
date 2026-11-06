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
 * Paramétrage du supplément au diplôme d'une instance (DEVE) : circuit de validation et preuves
 * des éléments ajoutés par les étudiants, préfixe des numéros, champ de profil de la date de
 * naissance, cachet, puis textes des rubriques fixes en français et en anglais.
 *
 * Les textes des rubriques sont nommés text_<langue>_<clé> (text_fr_qualification,
 * text_en_qualification...), une paire par rubrique de ep_supplement_sections() : les deux
 * langues se suivent, pour que la traduction se fasse en regard du texte d'origine.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplement_settings_form extends \moodleform {
    /** Langues dans lesquelles le supplément est édité. */
    public const LANGS = ['fr', 'en'];

    /** Rubriques d'une ligne (nom et qualité du signataire) : un champ court suffit. */
    public const SHORT_SECTIONS = ['signatoryname', 'signatorytitle'];

    /**
     * Options de la zone du cachet : une seule image, dans les formats que TCPDF lit sans
     * extension PHP supplémentaire (voir supplement_pdf::stamp_html()).
     *
     * @return array
     */
    public static function stamp_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => 2 * 1024 * 1024,
            'accepted_types' => ['.png', '.jpg', '.jpeg'],
        ];
    }

    /**
     * Circuits proposés pour les éléments ajoutés : la DEVE, le référent de l'étudiant, les deux
     * à la suite, ou aucun (ajout direct).
     *
     * @return array valeur stockée => libellé
     */
    public static function flow_options(): array {
        $options = [];
        foreach (['', EP_ROLE_REFERENT, EP_ROLE_DEVE, EP_ROLE_REFERENT . ',' . EP_ROLE_DEVE] as $flow) {
            $options[$flow] = ep_flow_label($flow);
        }
        return $options;
    }

    /**
     * Champs de profil personnalisés qui peuvent porter une date de naissance : les champs date,
     * et les champs texte (date saisie en clair, interprétée par ep_get_student_birthdate()).
     *
     * @return array nom court => libellé
     */
    public static function birthdate_field_options(): array {
        global $DB;

        $options = ['' => get_string('birthdatefieldnone', 'mod_ep')];
        [$insql, $params] = $DB->get_in_or_equal(['datetime', 'text']);
        $fields = $DB->get_records_select('user_info_field', "datatype $insql", $params, 'sortorder ASC', 'id, shortname, name');
        foreach ($fields as $field) {
            $options[$field->shortname] = format_string($field->name) . ' (' . s($field->shortname) . ')';
        }
        return $options;
    }

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'generalhdr', get_string('supplementsettings', 'mod_ep'));
        $mform->setExpanded('generalhdr', true);

        $mform->addElement('select', 'supplementflow', get_string('supplementflow', 'mod_ep'), self::flow_options());
        $mform->setType('supplementflow', PARAM_TEXT);
        $mform->addHelpButton('supplementflow', 'supplementflow', 'mod_ep');

        $mform->addElement('select', 'supplementevidence', get_string('supplementevidence', 'mod_ep'), ep_evidence_options());
        $mform->setType('supplementevidence', PARAM_INT);

        $mform->addElement('text', 'supplementprefix', get_string('supplementprefix', 'mod_ep'), ['size' => 20, 'maxlength' => 20]);
        // Le préfixe entre dans le numéro imprimé et dans le nom des fichiers : lettres, chiffres
        // et tirets suffisent, et évitent tout caractère qui gênerait la saisie de vérification.
        $mform->setType('supplementprefix', PARAM_ALPHANUMEXT);
        $mform->addRule('supplementprefix', get_string('maximumchars', '', 20), 'maxlength', 20, 'client');
        $mform->addHelpButton('supplementprefix', 'supplementprefix', 'mod_ep');

        $mform->addElement('select', 'birthdatefield', get_string('birthdatefield', 'mod_ep'), self::birthdate_field_options());
        $mform->setType('birthdatefield', PARAM_TEXT);
        $mform->addHelpButton('birthdatefield', 'birthdatefield', 'mod_ep');

        $mform->addElement('filemanager', 'supplementstamp', get_string('supplementstamp', 'mod_ep'), null, self::stamp_options());

        $mform->addElement('header', 'textshdr', get_string('supplementsectiontexts', 'mod_ep'));
        $mform->setExpanded('textshdr', true);
        foreach (ep_supplement_sections() as $key => $number) {
            $label = $number . ' ' . get_string('supplementsection_' . $key, 'mod_ep');
            foreach (self::LANGS as $lang) {
                $name = 'text_' . $lang . '_' . $key;
                $fieldlabel = $label . ' (' . strtoupper($lang) . ')';
                if (in_array($key, self::SHORT_SECTIONS, true)) {
                    $mform->addElement('text', $name, $fieldlabel, ['size' => 60]);
                } else {
                    $mform->addElement('textarea', $name, $fieldlabel, ['rows' => 4, 'cols' => 80]);
                }
                // Imprimés échappés par s() dans le PDF (voir supplement_pdf) : conservés tels
                // quels, pour qu'un « < » ou un « & » saisi ne soit pas amputé.
                $mform->setType($name, PARAM_RAW);
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
