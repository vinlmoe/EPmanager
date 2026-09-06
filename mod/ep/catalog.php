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
 * Catalogue des EP académiques et inscription de l'étudiant : les EP propres à sa promotion et
 * ceux, partagés, qu'une activité « Suivi de l'enseignement personnalisé » définit pour plusieurs
 * promotions à la fois. Chaque EP porte son propre nombre d'ECTS.
 *
 * S'inscrire n'est pas limité au nombre de places et ne donne aucun ECTS : le responsable de l'EP
 * accepte d'abord l'inscription (il peut dépasser le nombre de places s'il le juge utile), puis
 * valide les ECTS à la fin de l'EP. La DEVE et les enseignants peuvent consulter la même page
 * pour voir l'état des inscriptions.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);
$activityid = optional_param('activityid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:view', $context);

$baseurl = new moodle_url('/mod/ep/catalog.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('catalog', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$cansubmit = has_capability('mod/ep:submit', $context);

// L'année d'étude courante de la promotion vient de l'activité « Gestion des stages » du cours,
// où elle est déjà tenue à jour : c'est elle qui dit à quels EP l'étudiant peut s'inscrire.
$currentyear = ep_get_current_studyyear($ep);

// Inscription : confirmée sur une page dédiée, où l'étudiant choisit l'année d'étude de
// rattachement (elle détermine à quel minimum annuel l'EP comptera) et voit ce à quoi il s'engage.
if ($action === 'register' && $activityid) {
    require_capability('mod/ep:submit', $context);
    $activity = ep_get_catalog_activity($ep, $activityid);
    if (!$activity) {
        throw new moodle_exception('errorunknownactivity', 'mod_ep', $baseurl->out(false));
    }
    $type = ep_get_activity_type($ep, $activity);

    // Le formulaire de confirmation doit se soumettre à lui-même : sans action ni activityid,
    // le POST retomberait sur la liste et l'inscription serait silencieusement perdue.
    $registerurl = new moodle_url($baseurl, ['action' => 'register', 'activityid' => $activity->id]);
    $PAGE->set_url($registerurl);

    // Chaque condition est revérifiée ici : la page de liste a pu être affichée avant que l'EP ne
    // soit fermé, ou l'URL forgée à la main. Le nombre de places n'en fait pas partie — il ne
    // limite pas les inscriptions, il n'éclaire que la décision du responsable.
    $problem = null;
    if (!$activity->visible || !$type || empty($type->enabled)) {
        $problem = get_string('errorregisterclosed', 'mod_ep');
    } else if (ep_get_active_registration($activity->id, $USER->id)) {
        $problem = get_string('erroralreadyregistered', 'mod_ep');
    } else if (!ep_activity_open_to_year($activity, $currentyear)) {
        $problem = get_string('errorwrongyear', 'mod_ep');
    }

    if ($problem === null && data_submitted() && confirm_sesskey()) {
        $studyyear = optional_param('studyyear', 0, PARAM_INT);
        $yearoptions = ep_studyyear_selectable_options($ep);
        if (!array_key_exists($studyyear, $yearoptions)) {
            $studyyear = $currentyear;
        }
        ep_register_to_activity($ep, $activity, $USER->id, $studyyear);
        redirect(new moodle_url('/mod/ep/view.php', ['id' => $cm->id]),
            get_string('registered', 'mod_ep'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('registertoactivity', 'mod_ep', format_string($activity->name)));
    echo html_writer::link($baseurl, get_string('back'));

    if ($problem !== null) {
        echo $OUTPUT->notification($problem, 'error');
        echo $OUTPUT->footer();
        exit;
    }

    if (trim((string) $activity->description) !== '') {
        echo $OUTPUT->box(format_text($activity->description, FORMAT_PLAIN));
    }
    echo html_writer::tag('p', get_string('registerects', 'mod_ep', ep_format_ects($activity->ects)));
    echo $OUTPUT->notification(get_string('registerpendingnotice', 'mod_ep'), 'info');

    // Un EP complet reste ouvert aux inscriptions : le dire clairement évite que l'étudiant
    // compte sur une place que le responsable ne lui accordera peut-être pas.
    $remaining = ep_get_activity_remaining_places($activity);
    if ($remaining !== null && $remaining <= 0) {
        echo $OUTPUT->notification(get_string('registerfullnotice', 'mod_ep'), 'warning');
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $registerurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('studyyear', 'mod_ep'), ['for' => 'studyyear']);
    echo html_writer::select(ep_studyyear_selectable_options($ep), 'studyyear', $currentyear,
        false, ['class' => 'form-control mb-2', 'id' => 'studyyear']);
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('confirmregistration', 'mod_ep'), 'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('catalog', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/view.php', ['id' => $cm->id]), get_string('back'));

// Les EP masqués n'apparaissent qu'à ceux qui gèrent le catalogue : pour eux, la page sert aussi
// de contrôle de ce que voient les étudiants.
$canmanage = has_capability('mod/ep:manage', $context);
$activities = ep_get_catalog_activities($ep, !$canmanage);

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('nocatalogactivities', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::div(get_string('catalogprocessnotice', 'mod_ep'), 'text-muted mb-3');

$table = new html_table();
$table->head = [
    get_string('catalogactivity', 'mod_ep'),
    get_string('type', 'mod_ep'),
    get_string('ects', 'mod_ep'),
    get_string('studyyearrange', 'mod_ep'),
    get_string('places', 'mod_ep'),
    get_string('activityteachers', 'mod_ep'),
    get_string('actions', 'mod_ep'),
];

foreach ($activities as $activity) {
    $type = ep_get_activity_type($ep, $activity);

    $name = format_string($activity->name);
    if (!$activity->visible) {
        $name .= ' ' . html_writer::span(get_string('hidden', 'mod_ep'), 'badge badge-secondary');
    }
    if ($canmanage && ep_activity_is_shared($activity)) {
        // Utile à la seule DEVE : un EP partagé ne se modifie pas depuis cette promotion, mais
        // depuis l'activité de suivi où il est défini.
        $name .= ' ' . html_writer::span(get_string('sharedactivity', 'mod_ep'), 'badge badge-light');
    }
    if (trim((string) $activity->description) !== '') {
        $name .= html_writer::div(format_text($activity->description, FORMAT_PLAIN), 'text-muted small');
    }

    $counts = ep_get_activity_registration_counts($activity->id);
    $placescell = empty($activity->capacity)
        ? get_string('unlimitedplaces', 'mod_ep')
        : get_string('placestaken', 'mod_ep',
            (object) ['taken' => $counts->taken, 'total' => $activity->capacity]);
    if ($counts->pending > 0) {
        $placescell .= html_writer::div(
            get_string('pendingregistrationscount', 'mod_ep', $counts->pending), 'text-muted small');
    }

    $teachers = ep_get_activity_teachers($activity->id);
    $teacherscell = empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers));

    // Une seule action est proposée à l'étudiant, celle qui correspond à sa situation : déjà
    // inscrit, EP fermé, année d'étude hors de la plage, ou inscription possible. Un EP complet
    // reste ouvert : c'est le responsable qui arbitrera.
    $actioncell = '-';
    if ($cansubmit) {
        $registration = ep_get_active_registration($activity->id, $USER->id);
        if ($registration) {
            $actioncell = html_writer::span(ep_status_label($registration->status),
                'badge ' . ep_status_badgeclass($registration->status));
        } else if (!$activity->visible || !$type || empty($type->enabled)) {
            $actioncell = html_writer::span(get_string('registerclosed', 'mod_ep'), 'text-muted');
        } else if (!ep_activity_open_to_year($activity, $currentyear)) {
            $actioncell = html_writer::span(get_string('notopentoyear', 'mod_ep'), 'text-muted');
        } else {
            $actioncell = ep_render_actions([
                get_string('register', 'mod_ep') =>
                    new moodle_url($baseurl, ['action' => 'register', 'activityid' => $activity->id]),
            ], 'btn btn-sm btn-primary mr-1 mb-1');
            if (!empty($activity->capacity) && $counts->taken >= $activity->capacity) {
                $actioncell .= html_writer::div(get_string('registerfullshort', 'mod_ep'), 'text-muted small');
            }
        }
    } else if ($canmanage && !ep_activity_is_shared($activity)) {
        $actioncell = ep_render_actions([
            get_string('edit') =>
                new moodle_url('/mod/ep/activities.php', ['id' => $cm->id, 'action' => 'edit',
                    'activityid' => $activity->id]),
        ]);
    }

    $table->data[] = [
        $name,
        $type ? format_string($type->name) : '-',
        ep_format_ects($activity->ects),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
        $placescell,
        $teacherscell,
        $actioncell,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
