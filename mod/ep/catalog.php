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
 * Catalogue des EP académiques internes et inscription de l'étudiant : chaque EP porte son propre
 * nombre d'ECTS, et son responsable valide ensuite l'inscription (l'inscription seule ne donne
 * aucun ECTS). La DEVE et les enseignants peuvent consulter la même page pour voir l'état des
 * places.
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
$search = trim(optional_param('search', '', PARAM_TEXT));
$page = optional_param('page', 0, PARAM_INT);

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
$types = ep_get_types($ep->id);

// Inscription : confirmée sur une page dédiée, où l'étudiant choisit l'année d'étude de
// rattachement (elle détermine à quel minimum annuel l'EP comptera) et voit ce à quoi il s'engage.
if ($action === 'register' && $activityid) {
    require_capability('mod/ep:submit', $context);
    $activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
    $type = $types[$activity->typeid] ?? null;

    // Le formulaire de confirmation doit se soumettre à lui-même : sans action ni activityid,
    // le POST retomberait sur la liste et l'inscription serait silencieusement perdue.
    $registerurl = new moodle_url($baseurl, ['action' => 'register', 'activityid' => $activity->id]);
    $PAGE->set_url($registerurl);

    // Chaque condition est revérifiée ici : la page de liste a pu être affichée avant qu'une
    // place ne soit prise, ou l'URL forgée à la main. L'inscription elle-même les revérifie une
    // dernière fois sous le verrou de l'activité.
    $problem = ep_registration_problem($activity, $type, $USER->id, null, $ep);
    if ($problem !== null) {
        $problem = get_string($problem, 'mod_ep');
    }

    if ($problem === null && data_submitted() && confirm_sesskey()) {
        $studyyear = optional_param('studyyear', 0, PARAM_INT);
        $yearoptions = ep_studyyear_selectable_options($ep);
        if (!array_key_exists($studyyear, $yearoptions)) {
            $studyyear = (int) $ep->currentstudyyear;
        }
        $result = ep_register_to_activity($ep, $activity, $USER->id, $studyyear);
        // Inscription faite depuis la liste : retour à la même page de la liste, pour enchaîner.
        $fromlist = optional_param('fromlist', 0, PARAM_BOOL);
        $listreturn = new moodle_url($baseurl, ['page' => $page, 'search' => $search]);
        if ($result->problem !== null) {
            redirect(
                $fromlist ? $listreturn : $baseurl,
                get_string($result->problem, 'mod_ep'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
        redirect(
            $fromlist ? $listreturn : new moodle_url('/mod/ep/view.php', ['id' => $cm->id]),
            get_string('registered', 'mod_ep'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    ep_set_secondary_page_header();
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

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $registerurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('label', get_string('studyyear', 'mod_ep'), ['for' => 'studyyear']);
    echo html_writer::select(
        ep_studyyear_selectable_options($ep),
        'studyyear',
        (int) $ep->currentstudyyear,
        false,
        ['class' => 'form-control mb-2', 'id' => 'studyyear']
    );
    echo html_writer::empty_tag('input', [
        'type' => 'submit', 'value' => get_string('confirmregistration', 'mod_ep'), 'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');

    echo $OUTPUT->footer();
    exit;
}

ep_set_secondary_page_header();
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('catalog', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/view.php', ['id' => $cm->id]), get_string('back'));

// Les EP archivés, en brouillon ou proposés n'apparaissent qu'à ceux qui gèrent le catalogue :
// pour eux, la page sert aussi de contrôle de ce que voient les étudiants.
$canmanage = has_capability('mod/ep:manage', $context);
$activities = ep_get_activities($ep->id, !$canmanage);
$rights = ep_get_user_rights($ep, $context);

// Recherche sur l'intitulé, le code et la description, puis tri naturel des intitulés (« n° 3 »
// avant « n° 29 ») et pagination : un grand catalogue reste lisible.
$listurl = new moodle_url($baseurl, ['search' => $search]);
echo $OUTPUT->render(new \mod_ep\output\list_filters($listurl, ['search', 'page'], $search, [], get_string('search')));
if ($search !== '') {
    $needle = core_text::strtolower($search);
    $activities = array_filter($activities, fn($a) => core_text::strpos(core_text::strtolower(
        $a->name . ' ' . $a->code . ' ' . $a->description
    ), $needle) !== false);
}
uasort($activities, fn($a, $b) => [(int) $a->sortorder, 0] <=> [(int) $b->sortorder, 0]
    ?: strnatcasecmp($a->name, $b->name));
$totalactivities = count($activities);
$activities = array_slice($activities, $page * EP_LIST_PERPAGE, EP_LIST_PERPAGE, true);

// Places prises, responsables et inscriptions de l'étudiant : une requête chacun pour tout le
// catalogue, et non une par EP.
$takenplaces = ep_get_activities_taken_places($ep->id);
$allteachers = ep_get_activities_teachers($ep->id);
$myregistrations = $cansubmit ? ep_get_user_active_registrations($ep->id, $USER->id) : [];

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('nocatalogactivities', 'mod_ep'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// EP qui ont une fiche publiée (activité « Syllabus du module »), en une requête pour tout le
// catalogue : seuls ceux-là ont une fiche à consulter, ouverte directement.
$withsyllabus = ep_get_published_syllabi(array_keys($activities));

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

$yearoptions = $cansubmit ? ep_studyyear_selectable_options($ep) : [];

// Prérequis manquants de l'étudiant pour les EP où il n'est pas inscrit, calculés une fois.
$missingall = $cansubmit ? ep_get_missing_prerequisites_bulk($ep, array_diff_key($activities, $myregistrations), $USER->id) : [];

foreach ($activities as $activity) {
    $type = $types[$activity->typeid] ?? null;

    $name = format_string($activity->name);
    if ($activity->code !== '') {
        $name .= ' ' . html_writer::span(s($activity->code), 'text-muted small');
    }
    if ($activity->state !== EP_ACTIVITY_PUBLISHED) {
        $name .= ' ' . html_writer::span(ep_activity_state_options()[$activity->state] ?? '', 'badge badge-secondary');
    }
    // Description résumée : le catalogue se parcourt d'un coup d'œil, le détail est dans la fiche.
    $description = trim((string) $activity->description);
    if ($description !== '') {
        $short = shorten_text($description, 100, true);
        $name .= html_writer::div(s($short), 'text-muted small', $short !== $description ? ['title' => $description] : []);
    }
    if (isset($withsyllabus[$activity->id])) {
        $name .= html_writer::div(html_writer::link(
            new moodle_url('/mod/syllabus/view.php', ['id' => $withsyllabus[$activity->id]->cmid]),
            get_string('syllabus', 'mod_ep')
        ), 'small');
    }
    $period = ep_activity_registration_label($activity);
    if ($period !== '') {
        $name .= html_writer::div($period, 'text-muted small');
    }
    // Prérequis : rappelés à l'étudiant (ce qui lui manque) et à ceux qui gèrent le catalogue.
    if ($cansubmit && empty($myregistrations[$activity->id])) {
        $missing = $missingall[$activity->id] ?? [];
        if ($missing) {
            $name .= html_writer::div(
                get_string('prereqmissing', 'mod_ep', implode(' ; ', $missing)),
                $activity->prereqblocking ? 'text-danger small' : 'text-warning small'
            );
        }
    }

    $remaining = empty($activity->capacity)
        ? null
        : max(0, (int) $activity->capacity - ($takenplaces[$activity->id] ?? 0));
    $placescell = $remaining === null
        ? get_string('unlimitedplaces', 'mod_ep')
        : get_string('placesleft', 'mod_ep', (object) ['left' => $remaining, 'total' => $activity->capacity]);

    $teachers = $allteachers[$activity->id] ?? [];
    $teacherscell = empty($teachers) ? '-' : implode(', ', array_map('fullname', $teachers));

    // Une seule action est proposée à l'étudiant, celle qui correspond à sa situation : déjà
    // inscrit, plus de place, année d'étude hors de la plage, ou inscription possible.
    $actioncell = '-';
    if ($cansubmit) {
        $registration = $myregistrations[$activity->id] ?? null;
        if ($registration) {
            $actioncell = ep_render_credit_status($registration->status);
        } else if (!ep_activity_registration_open($activity) || !$type || empty($type->enabled)) {
            $actioncell = html_writer::span(get_string('registrationnotopen', 'mod_ep'), 'text-muted');
        } else if ($remaining !== null && $remaining <= 0) {
            $actioncell = html_writer::span(get_string('nofreeplace', 'mod_ep'), 'text-muted');
        } else if (!ep_activity_open_to_year($activity, (int) $ep->currentstudyyear)) {
            $actioncell = html_writer::span(get_string('notopentoyear', 'mod_ep'), 'text-muted');
        } else if ($activity->prereqblocking && !empty($missingall[$activity->id])) {
            $actioncell = html_writer::span(get_string('errorprerequisites', 'mod_ep'), 'text-muted');
        } else {
            // Inscription directe depuis la liste, un bouton par EP (l'année d'étude est choisie
            // une fois, en tête du catalogue) : une page de moins par inscription, ce qui compte
            // quand toute une promotion s'inscrit à l'ouverture des inscriptions.
            $actioncell = html_writer::tag('button', get_string('register', 'mod_ep'), [
                'type' => 'submit', 'name' => 'activityid', 'value' => $activity->id, 'class' => 'btn btn-sm btn-primary mb-1',
            ]);
            $registerable = true;
        }
    } else {
        // Ceux qui gèrent le catalogue ou les participants de cet EP y accèdent d'ici.
        $actioncell = ep_render_actions([
            get_string('edit') => $canmanage
                ? new moodle_url('/mod/ep/activities.php', ['id' => $cm->id, 'action' => 'edit', 'activityid' => $activity->id])
                : null,
            get_string('participants', 'mod_ep') => ep_rights_can_manage_participants($rights, $activity)
                ? new moodle_url('/mod/ep/participants.php', ['id' => $cm->id, 'activityid' => $activity->id])
                : null,
        ]);
    }

    // Ancre de la ligne : la fiche de l'EP (mod_syllabus) y ramène.
    $row = new html_table_row([
        $name,
        $type ? format_string($type->name) : '-',
        ep_format_ects($activity->ects),
        ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear),
        $placescell,
        $teacherscell,
        $actioncell,
    ]);
    $row->id = 'ep-activity-' . (int) $activity->id;
    $table->data[] = $row;
}

if (!empty($registerable)) {
    // Un seul formulaire d'inscription pour la page : l'année d'étude, choisie une fois, vaut
    // pour le bouton « S'inscrire » de chaque ligne.
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
    foreach (
        ['sesskey' => sesskey(), 'action' => 'register', 'fromlist' => 1, 'page' => (int) $page,
            'search' => $search] as $field => $value
    ) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $field, 'value' => $value]);
    }
    if (count($yearoptions) > 1) {
        echo html_writer::div(
            html_writer::label(get_string('registeryear', 'mod_ep'), 'ep-register-year', false, ['class' => 'mr-2'])
            . html_writer::select(
                $yearoptions,
                'studyyear',
                array_key_exists((int) $ep->currentstudyyear, $yearoptions)
                ? (int) $ep->currentstudyyear : array_key_first($yearoptions),
                false,
                ['id' => 'ep-register-year', 'class' => 'custom-select']
            ),
            'form-inline mb-2'
        );
    } else {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'studyyear',
            'value' => (int) array_key_first($yearoptions)]);
    }
}
echo html_writer::div(html_writer::table($table), 'table-responsive');
if (!empty($registerable)) {
    echo html_writer::end_tag('form');
}
echo ep_render_paging_bar($totalactivities, $page, $listurl);
echo $OUTPUT->footer();
