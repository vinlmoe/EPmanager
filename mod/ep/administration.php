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
 * Page d'administration de l'activité (DEVE) : regroupe les pages de paramétrage utilisées
 * ponctuellement — types d'EP et leurs plafonds, catalogue des EP internes, minimums d'ECTS,
 * éditions PDF et courriels, supplément au diplôme, validation des années et rentrée.
 * Chaque page est accompagnée de ce à quoi elle sert : elles sont visitées rarement, et leurs
 * seuls intitulés ne suffisent pas à savoir laquelle ouvrir.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/ep/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('ep', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$ep = $DB->get_record('ep', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/ep:manage', $context);

$baseurl = new moodle_url('/mod/ep/administration.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($ep->name) . ' - ' . get_string('administration', 'mod_ep'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('administration', 'mod_ep'));
echo html_writer::link(new moodle_url('/mod/ep/dashboard.php', ['id' => $cm->id]), get_string('back'));

$sections = [
    [get_string('adminsectionrules', 'mod_ep'), [
        [
            get_string('managetypes', 'mod_ep'),
            get_string('managetypes_desc', 'mod_ep'),
            new moodle_url('/mod/ep/types.php', ['id' => $cm->id]),
        ],
        [
            get_string('manageyearrequirements', 'mod_ep'),
            get_string('manageyearrequirements_desc', 'mod_ep'),
            new moodle_url('/mod/ep/year_requirements.php', ['id' => $cm->id]),
        ],
    ]],
    [get_string('adminsectioncatalog', 'mod_ep'), [
        [
            get_string('managecatalog', 'mod_ep'),
            get_string('managecatalog_desc', 'mod_ep'),
            new moodle_url('/mod/ep/activities.php', ['id' => $cm->id]),
        ],
    ]],
    [get_string('pdfsettings', 'mod_ep'), [
        [
            get_string('pdfsettings', 'mod_ep'),
            get_string('pdfsettings_desc', 'mod_ep'),
            new moodle_url('/mod/ep/pdf_settings.php', ['id' => $cm->id]),
        ],
        [
            get_string('notifications', 'mod_ep'),
            get_string('notifications_desc', 'mod_ep'),
            new moodle_url('/mod/ep/notifications.php', ['id' => $cm->id]),
        ],
        [
            get_string('booklet', 'mod_ep'),
            get_string('booklet_help', 'mod_ep'),
            new moodle_url('/mod/ep/booklet.php', ['id' => $cm->id]),
        ],
    ]],
];

// Supplément au diplôme et fin d'année : la validation des années et les éléments à examiner
// relèvent de la DEVE (mod/ep:validatedeve), que la gestion de l'activité n'implique pas.
$supplemententries = [
    [
        get_string('supplementsettings', 'mod_ep'),
        get_string('supplementsettings_desc', 'mod_ep'),
        new moodle_url('/mod/ep/supplement_settings.php', ['id' => $cm->id]),
    ],
    [
        get_string('supplementissuesadmin', 'mod_ep'),
        get_string('supplementissuesadmin_desc', 'mod_ep'),
        new moodle_url('/mod/ep/supplement_issue.php', ['id' => $cm->id]),
    ],
];
if (has_capability('mod/ep:validatedeve', $context)) {
    $supplemententries[] = [
        get_string('supplementitemstodecide', 'mod_ep'),
        get_string('supplementitemstodecide_desc', 'mod_ep'),
        new moodle_url('/mod/ep/supplement_items.php', ['id' => $cm->id]),
    ];
    $supplemententries[] = [
        get_string('yearvalidation', 'mod_ep'),
        get_string('yearvalidation_desc', 'mod_ep'),
        new moodle_url('/mod/ep/year_validation.php', ['id' => $cm->id]),
    ];
}
$supplemententries[] = [
    get_string('rollover', 'mod_ep'),
    get_string('rollover_desc', 'mod_ep'),
    new moodle_url('/mod/ep/rollover.php', ['id' => $cm->id]),
];
$supplemententries[] = [
    get_string('supplementverify', 'mod_ep'),
    get_string('supplementverify_desc', 'mod_ep'),
    new moodle_url('/mod/ep/supplement_verify.php', ['id' => $cm->id]),
];
$sections[] = [get_string('supplement', 'mod_ep'), $supplemententries];

// Imports, exports et transfert : l'import de crédits et la restauration portent des décisions
// aux dossiers (validation DEVE), l'export complet contient tous les dossiers (consultation).
$transferentries = [
    [
        get_string('settingstransfer', 'mod_ep'),
        get_string('settingstransfer_desc', 'mod_ep'),
        new moodle_url('/mod/ep/settings_transfer.php', ['id' => $cm->id]),
    ],
    [
        get_string('importcatalog', 'mod_ep'),
        get_string('importcatalog_desc', 'mod_ep'),
        new moodle_url('/mod/ep/import_catalog.php', ['id' => $cm->id, 'kind' => 'catalog']),
    ],
    [
        get_string('importsessions', 'mod_ep'),
        get_string('importsessions_desc', 'mod_ep'),
        new moodle_url('/mod/ep/import_catalog.php', ['id' => $cm->id, 'kind' => 'sessions']),
    ],
];
if (has_capability('mod/ep:validatedeve', $context)) {
    $transferentries[] = [
        get_string('importcredits', 'mod_ep'),
        get_string('importcredits_desc', 'mod_ep'),
        new moodle_url('/mod/ep/import_credits.php', ['id' => $cm->id]),
    ];
}
if (has_any_capability(['mod/ep:viewall', 'mod/ep:validatedeve'], $context)) {
    $transferentries[] = [
        get_string('archive', 'mod_ep'),
        get_string('archive_desc', 'mod_ep'),
        new moodle_url('/mod/ep/archive.php', ['id' => $cm->id]),
    ];
}
$sections[] = [get_string('adminsectiontransfer', 'mod_ep'), $transferentries];

if (has_capability('mod/ep:viewall', $context)) {
    $sections[] = [get_string('adminsectionfollowup', 'mod_ep'), [
        [
            get_string('exportcsv', 'mod_ep'),
            get_string('exportcsv_desc', 'mod_ep'),
            new moodle_url('/mod/ep/export.php', ['id' => $cm->id]),
        ],
    ]];
}

foreach ($sections as [$sectiontitle, $entries]) {
    echo $OUTPUT->heading($sectiontitle, 4);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('adminsectionpage', 'mod_ep'), get_string('adminsectionpurpose', 'mod_ep')];
    foreach ($entries as [$label, $description, $url]) {
        $table->data[] = [html_writer::link($url, $label, ['class' => 'btn btn-secondary']), $description];
    }
    echo html_writer::table($table);
}

// Rappel de l'origine des EP de type stage : c'est la question la plus posée à la mise en route,
// et la réponse ne se trouve nulle part ailleurs dans l'interface.
$linked = ep_get_linked_stage_instances($ep);
$stagetypes = ep_get_stage_types($ep->id);
echo $OUTPUT->heading(get_string('stagelinkheading', 'mod_ep'), 4);
if (empty($linked)) {
    echo $OUTPUT->notification(get_string('stagelinknone', 'mod_ep'), 'warning');
} else {
    $names = [];
    foreach ($linked as $instance) {
        $names[] = format_string($instance->stage->name);
    }
    echo html_writer::tag('p', get_string('stagelinklist', 'mod_ep', implode(', ', $names)));
    // Plusieurs types peuvent être alimentés par les stages, chacun avec sa portée et son barème.
    $rates = [];
    foreach ($stagetypes as $stagetype) {
        $rates[] = get_string('stagescoperate', 'mod_ep', (object) [
            'name' => format_string($stagetype->name),
            'scope' => get_string('stagescope_' . $stagetype->stagescope, 'mod_ep'),
            'rate' => ep_format_ects($stagetype->ectsperday),
        ]);
    }
    echo html_writer::tag(
        'p',
        $rates ? get_string('stagelinkrates', 'mod_ep', implode(' ; ', $rates)) : get_string('stagelinknorate', 'mod_ep'),
        ['class' => 'text-muted']
    );
}

echo $OUTPUT->footer();
