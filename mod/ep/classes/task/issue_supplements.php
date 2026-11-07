<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\task;

/**
 * Émission groupée des suppléments au diplôme d'une promotion, lancée par la DEVE : la
 * génération d'un PDF par étudiant dépasserait le temps d'une requête web. Données : epid, userids,
 * issuerid, lang, force. À la fin, l'auteur reçoit un message récapitulatif.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_supplements extends \core\task\adhoc_task {
    /**
     * Nom de la tâche.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskissuesupplements', 'mod_ep');
    }

    /**
     * Émet les suppléments demandés.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $data = $this->get_custom_data();
        $ep = $DB->get_record('ep', ['id' => $data->epid]);
        $cm = $ep ? ep_get_cm_of_instance($ep->id) : null;
        if (!$ep || !$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);
        $issued = 0;
        $skipped = 0;
        $failed = 0;
        // Reprise après un échec, ou seconde demande (double clic) : un étudiant dont le supplément
        // a déjà été émis depuis la demande n'en reçoit pas un nouveau numéro.
        $done = [];
        if (!empty($data->queued)) {
            $done = array_flip($DB->get_fieldset_select(
                'ep_supplement_issue',
                'userid',
                'epid = :epid AND lang = :lang AND timeissued >= :queued',
                ['epid' => $ep->id, 'lang' => $data->lang, 'queued' => (int) $data->queued]
            ));
        }
        foreach ($data->userids as $userid) {
            if (isset($done[(int) $userid])) {
                $issued++;
                continue;
            }
            // Un étudiant en échec (verrou occupé, document illisible) n'arrête pas les autres.
            try {
                $result = ep_issue_supplement(
                    $ep,
                    $context,
                    (int) $userid,
                    (int) $data->issuerid,
                    $data->lang,
                    !empty($data->force)
                );
            } catch (\moodle_exception $e) {
                mtrace("mod_ep: supplément de l'utilisateur {$userid} non émis : " . $e->getMessage());
                $failed++;
                continue;
            }
            if ($result->issue) {
                $issued++;
            } else {
                $skipped++;
            }
        }
        $skipped += $failed;
        mtrace("mod_ep: instance {$ep->id} — {$issued} supplément(s) émis, {$skipped} non émis.");

        $issuer = $DB->get_record('user', ['id' => $data->issuerid]);
        if ($issuer) {
            $message = new \core\message\message();
            $message->component = 'mod_ep';
            $message->name = 'supplementissued';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $issuer;
            $message->subject = get_string('supplementissueallreport', 'mod_ep', (object) [
                'issued' => $issued, 'skipped' => $skipped,
            ]);
            $message->fullmessage = $message->subject;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = s($message->subject);
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $url = new \moodle_url('/mod/ep/supplement_issue.php', ['id' => $cm->id]);
            $message->contexturl = $url->out(false);
            $message->contexturlname = format_string($ep->name);
            $message->courseid = $ep->course;
            message_send($message);
        }
    }
}
