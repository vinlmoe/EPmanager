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
 * English strings for mod_syllabusstaff.
 *
 * @package   mod_syllabusstaff
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allevalstates'] = 'All evaluations';
$string['allmodules'] = 'All modules';
$string['allstatuses'] = 'All syllabi';
$string['alltypes'] = 'All types';
$string['bulkwindow'] = 'Evaluation window of the selected modules';
$string['bulkwindow_help'] = 'Sets the same opening (and closing) for the selected modules where you have the academic office role. Leaving the opening empty removes the evaluation.';
$string['commonquestions'] = 'Common evaluation questions';
$string['commonquestions_help'] = 'Asked to students before the teacher\'s questions, in all modules or in modules of a type. They are copied into each module when its evaluation opens: changing them afterwards does not change evaluations already open. A disabled question is no longer asked.';
$string['evaluation'] = 'Evaluation';
$string['modulename'] = 'Module overview (teachers and academic office)';
$string['modulename_help'] = 'Gathers, for each teacher, the modules they lead in all courses: syllabus state, evaluation state, responses. The academic office sees all modules, the syllabi to review and the evaluation overview, and maintains the common referential (module types, common questions, settings).';
$string['modulenameplural'] = 'Module overviews';
$string['modulescount'] = 'Modules';
$string['moduletypes'] = 'Module types';
$string['mymodules'] = 'My modules';
$string['mymodules_help'] = 'The modules you lead: you write their syllabus, set your evaluation questions and see the results.';
$string['mymodulestodo'] = '{$a} module(s) without a published syllabus or with a syllabus returned by the academic office.';
$string['newtype'] = 'New module type';
$string['nomodules'] = 'No modules.';
$string['nothingtoreview'] = 'No syllabus is awaiting review.';
$string['notpublished'] = 'Syllabus not published';
$string['pluginadministration'] = 'Module overview administration';
$string['pluginname'] = 'Module overview (teachers and academic office)';
$string['privacy:metadata'] = 'The module overview stores no personal data: it displays that of the "Module syllabus" activity.';
$string['referential'] = 'Common referential';
$string['referential_help'] = 'This referential applies to all modules of the site, whichever overview activity it is edited from.';
$string['responses'] = 'Responses';
$string['results'] = 'Results';
$string['resultsoverview'] = 'Evaluation results';
$string['resultsoverview_help'] = 'Mean (out of 5) of each common scale question, module by module. A module with fewer than {$a} responses has no mean, to protect anonymity.';
$string['resultsthreshold'] = 'Minimum number of responses to show results';
$string['review'] = 'Review';
$string['reviewdisabled'] = 'Review by the academic office is disabled (referential): syllabi are published as soon as they are submitted.';
$string['reviewrequired'] = 'Syllabi submitted by module leaders are reviewed by the academic office before publication';
$string['scope'] = 'Scope';
$string['scope_help'] = 'Course category whose modules are followed (subcategories included), or the whole site.';
$string['setwindow'] = 'Apply';
$string['settings'] = 'Settings';
$string['settingssaved'] = 'Settings saved.';
$string['submittedon'] = 'Submitted on';
$string['syllabusstaff:addinstance'] = 'Add a module overview';
$string['syllabusstaff:manageall'] = 'Manage all modules and the common referential (academic office)';
$string['syllabusstaff:view'] = 'See one\'s modules';
$string['teacherresults'] = 'Module leaders see the results';
$string['teacherresults_afterclose'] = 'after the evaluation closes';
$string['teacherresults_always'] = 'as soon as it opens';
$string['toreview'] = 'Syllabi to review';
$string['toreview_help'] = 'Syllabi submitted by module leaders: open them to publish, return with a comment or reject.';
$string['typedeleted'] = 'Type deleted.';
$string['typeinuse'] = 'This type is used by modules or targeted by questions: it cannot be deleted.';
$string['types_help'] = 'Each module can be linked to a type (in its settings); common questions can target a type.';
$string['versionn'] = 'Version {$a} published';
$string['windowsset'] = 'Window set for {$a->done} module(s); {$a->skipped} skipped (no academic office role in their course).';
$string['competencymatrix'] = 'Competencies';
$string['competencymatrix_help'] = 'Which courses of the scope target which competencies of the framework, and at what level. A column header opens the module\'s competency linking (or, for a course without a module, Moodle\'s course competencies page).';
$string['coverage'] = 'Courses';
$string['coveragesummary'] = '{$a->covered} competency(ies) out of {$a->total} targeted by at least one of the {$a->courses} courses; competencies targeted by no course are highlighted.';
$string['matrixlegend'] = 'I: introduced, D: developed, M: mastered (level set by the module); ✓: linked to the module without level; •: linked to the course only.';
