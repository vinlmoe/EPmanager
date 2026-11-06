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

/**
 * Courriels de l'activité (DEVE) : activation, objet et message de chaque courriel envoyé par le
 * module (décision sur une demande, proposition à examiner, décision sur une proposition, relance
 * des demandes en attente).
 *
 * Seul ce qui diffère du texte par défaut est présenté comme personnalisé : un champ resté vide
 * continue de suivre le texte de la langue (voir mod_ep\form\notifications_form).
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_ep\form\notifications_form;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$returnurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$pageurl = new moodle_url('/mod/ep/notifications.php', ['id' => $cm->id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('notifications', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$definitions = ep_notification_definitions();

// Valeurs initiales : ep_get_notification_template() renvoie le texte effectif, personnalisé ou
// par défaut. Un texte identique à celui de la langue est présenté vide, pour que l'enregistrer
// sans y toucher le laisse suivre la langue au lieu de le figer en personnalisation. C'est aussi
// le cas d'un objet par défaut accompagné d'un message personnalisé : chacun des deux champs est
// comparé à son propre texte par défaut.
$defaults = ['id' => $cm->id];
foreach (array_keys($definitions) as $key) {
    $template = ep_get_notification_template($ep->id, $key);
    $defaultsubject = get_string('notify_' . $key . '_subject', 'mod_ep');
    $defaultbody = get_string('notify_' . $key . '_body', 'mod_ep');
    $defaults['enabled_' . $key] = (int) $template->enabled;
    $defaults['subject_' . $key] = $template->subject === $defaultsubject ? '' : $template->subject;
    $defaults['body_' . $key] = $template->body === $defaultbody ? '' : $template->body;
}

$mform = new notifications_form($pageurl);
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    // La clé de session est vérifiée par get_data() (moodleform) avant tout enregistrement.
    $transaction = $DB->start_delegated_transaction();
    foreach (array_keys($definitions) as $key) {
        ep_set_notification_template(
            $ep->id,
            $key,
            !empty($data->{'enabled_' . $key}),
            (string) ($data->{'subject_' . $key} ?? ''),
            (string) ($data->{'body_' . $key} ?? '')
        );
    }
    $transaction->allow_commit();
    redirect($pageurl, get_string('notificationssaved', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notifications', 'mod_ep'));
echo html_writer::link($returnurl, get_string('back'));
echo html_writer::tag('p', get_string('notifications_desc', 'mod_ep'), ['class' => 'text-muted mt-2']);

$mform->display();

echo $OUTPUT->footer();
