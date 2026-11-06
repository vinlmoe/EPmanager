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
 * Personnalisation des courriels de l'activité (DEVE) : pour chacun, son activation, son objet et
 * son message, avec les variables qu'il accepte.
 *
 * Un objet ou un message laissé vide reprend le texte par défaut de la langue : c'est ce qui
 * permet de revenir au texte d'origine sans le recopier, et de profiter des corrections du texte
 * par défaut lors d'une mise à jour du module. Le texte par défaut est donc affiché en
 * indication (placeholder) et non comme valeur du champ, sans quoi un simple enregistrement de la
 * page le figerait en personnalisation.
 *
 * Les champs sont nommés <champ>_<clé> (enabled_creditdecided, subject_creditdecided...), une
 * série par courriel de ep_notification_definitions().
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('static', 'resetinfo', '', get_string('notificationreset', 'mod_ep'));

        foreach (ep_notification_definitions() as $key => $variables) {
            $mform->addElement('header', 'hdr_' . $key, get_string('notifykey_' . $key, 'mod_ep'));
            $mform->setExpanded('hdr_' . $key, true);

            $mform->addElement('advcheckbox', 'enabled_' . $key, get_string('notificationenabled', 'mod_ep'));
            $mform->setDefault('enabled_' . $key, 1);

            $mform->addElement('text', 'subject_' . $key, get_string('notificationsubject', 'mod_ep'), [
                'size' => 80,
                'placeholder' => get_string('notify_' . $key . '_subject', 'mod_ep'),
            ]);
            // L'objet est un texte brut (objet de courriel, résumé de notification) : les balises
            // n'y auraient aucun sens.
            $mform->setType('subject_' . $key, PARAM_TEXT);
            $mform->addRule('subject_' . $key, get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

            $mform->addElement('textarea', 'body_' . $key, get_string('notificationbody', 'mod_ep'), [
                'rows' => 8,
                'cols' => 80,
                'placeholder' => get_string('notify_' . $key . '_body', 'mod_ep'),
            ]);
            // Le message est envoyé en texte brut et échappé par s() pour sa version HTML (voir
            // ep_send_notification()) : il est conservé tel quel, pour qu'un « < » ou un « & »
            // écrit par la DEVE ne soit pas amputé à l'enregistrement.
            $mform->setType('body_' . $key, PARAM_RAW);

            $placeholders = array_map(function ($variable) {
                return '{' . $variable . '}';
            }, $variables);
            $mform->addElement(
                'static',
                'variables_' . $key,
                '',
                get_string('notificationvariables', 'mod_ep', s(implode(', ', $placeholders)))
            );
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
