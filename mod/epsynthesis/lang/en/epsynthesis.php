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
 * English strings for mod_epsynthesis.
 *
 * @package   mod_epsynthesis
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Personalised learning follow-up (teacher view)';
$string['modulename'] = 'Personalised learning follow-up';
$string['modulenameplural'] = 'Personalised learning follow-ups';
$string['modulename_help'] = 'Brings together, for each teacher, everything they have to follow up regarding '
    . 'personalised learning across the "Personalised learning" activities linked to this one — enrolments in the '
    . 'units they are in charge of and the declarations of the students they are referent teacher for, even when '
    . "these come from several cohorts or courses.\n\nWhich activities feed into it is chosen in this activity's "
    . 'administration, so a cohort that is no longer followed can be removed without touching its source '
    . 'activity. Rights (who is referent for whom, who is in charge of which unit) remain managed in each '
    . '"Personalised learning" activity: this activity only gives a combined view of them, it grants no '
    . 'additional right.';
$string['modulename_link'] = 'mod/epsynthesis/view';
$string['pluginadministration'] = 'Personalised learning follow-up administration';

$string['epsynthesis:addinstance'] = 'Add a new personalised learning follow-up activity';
$string['epsynthesis:view'] = 'View the follow-up of own students and units';
$string['epsynthesis:viewall'] = 'Follow up every academic unit in scope (academic office)';
$string['epsynthesis:manageactivities'] = 'Define shared academic units and the people in charge of them';
$string['epsynthesis:managelinks'] = 'Manage the linked "Personalised learning" activities';

$string['epsynthesisname'] = 'Activity name';
$string['linksnotice'] = 'Which "Personalised learning" activities feed into this one is chosen after saving, '
    . 'from the activity page ("Manage links").';

$string['managelinks'] = 'Manage links';
$string['managelinks_help'] = 'Tick the "Personalised learning" activities whose students and units should appear '
    . 'here for the teachers concerned. Untick an activity (a cohort that has graduated, for instance) to remove '
    . 'it from the follow-up without deleting it or changing its data.';
$string['linkedcount'] = '{$a} "Personalised learning" activity/activities linked.';
$string['linkssaved'] = 'List of linked activities updated.';
$string['linked'] = 'Linked';
$string['noactivities'] = 'No "Personalised learning" activity exists on this site yet.';
$string['hiddencourse'] = 'hidden course';
$string['hiddenactivity'] = 'hidden activity';

// Shared academic units.
$string['manageactivities'] = 'Shared academic units';
$string['manageactivities_help'] = 'Units defined here are offered in the catalogue of every cohort this '
    . 'activity follows: this is how one unit takes students from several years without being copied into each '
    . 'cohort — two copies would each have their own places and enrolments, when they are in fact the same. Each '
    . 'unit is validated by the person or people in charge of it, named here.';
$string['addsharedactivity'] = 'Add a shared unit';
$string['nosharedactivities'] = 'No shared unit is defined yet.';
$string['sharedorigin'] = 'Shared unit';
$string['sharedstudyyearrange'] = 'Study years concerned';
$string['sharedstudyyearrange_help'] = 'Study years this unit is open to (0 = no restriction). A student\'s year '
    . 'is that of their cohort, as set in the "Internship management" activity of their course: a student whose '
    . 'cohort falls outside this range cannot enrol.';
$string['sharedactivityteachers_help'] = 'Tick the teachers in charge of this unit. They, and only they, accept '
    . 'enrolments and then validate the credits — for every student enrolled, whatever their cohort, and without '
    . 'needing a role in each of those courses.';
$string['nopotentialteachers'] = 'No teacher has access to this follow-up activity: enrol the teachers '
    . 'concerned in this course first.';

// Unit-by-unit enrolment follow-up.
$string['registrationsfollowup'] = 'Academic unit follow-up';
$string['registrationsfollowup_help'] = 'Where each academic unit stands: who asked to enrol, who was accepted, '
    . 'and whose credits are still to be validated. It covers the shared units defined here and the units of '
    . 'each cohort followed.';
$string['viewregistrations'] = 'View enrolments';
$string['nofollowupactivities'] = 'No academic unit to follow up: you are not in charge of any unit within the '
    . 'scope of this activity.';
$string['noregistrations'] = 'No enrolment to show.';
$string['registrationorigin'] = 'Enrolment in "{$a->activity}" — cohort: {$a->course}';
$string['decisionnotyours'] = 'This enrolment awaits the decision of the person in charge of the unit: you can '
    . 'follow it here, but it is theirs to decide.';
$string['errornotaregistration'] = 'This personalised learning is not a catalogue enrolment: it is validated by '
    . "the student's referent teacher, in the \"Personalised learning\" activity of their cohort.";

$string['noscope'] = 'You are not the referent teacher of any student and you are not in charge of any unit in '
    . 'the linked activities.';

// Privacy. Everything the follow-up displays is read from the linked "Personalised learning" activities; the
// only data of its own is who is named in charge of its shared units.
$string['privacy:metadata:ep_activity_teacher'] = 'Teachers named as being in charge of a shared unit defined '
    . 'in this follow-up activity.';
$string['privacy:metadata:ep_activity_teacher:teacherid'] = 'Teacher in charge.';
