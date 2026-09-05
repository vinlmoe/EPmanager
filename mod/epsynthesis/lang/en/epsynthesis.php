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

$string['noscope'] = 'You are not the referent teacher of any student and you are not in charge of any unit in '
    . 'the linked activities.';

$string['privacy:metadata'] = 'The Personalised learning follow-up plugin stores no personal data: it only '
    . 'displays data already held in the linked "Personalised learning" activities.';
