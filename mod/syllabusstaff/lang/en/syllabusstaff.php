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
$string['bulkwindow_help'] = 'Sets the same opening (and closing) for the ticked modules. Greyed boxes belong to courses where you do not have the DEVE role.';
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
$string['competencymatrix'] = 'Competency matrix';
$string['competencymatrix_help'] = 'Which courses in scope target which competencies of the framework, and at which level; columns follow the curriculum order (year, period), courses without a module last. A competency name opens its progression, a column header the course competency links.';
$string['coverage'] = 'Courses';
$string['coveragesummary'] = '{$a->covered} competency(ies) out of {$a->total} targeted by at least one of the {$a->courses} courses; competencies targeted by no course are highlighted.';
$string['matrixlegend'] = 'I: introduced, D: developed, M: mastered; *: targeted by the course itself (course without module, linked by the academic office).';
$string['addchild'] = 'Add a sub-competency';
$string['addcompetency'] = 'Add a competency';
$string['addframework'] = 'Add a framework';
$string['allframeworks'] = 'All frameworks';
$string['backtomatrix'] = 'Back to the matrix';
$string['code'] = 'Code';
$string['code_help'] = 'Short unique code (for example "REF-VET" for a framework, "C1.2" for a competency): used by the CSV import and to find the competencies of a module restored on another site.';
$string['compdeleted'] = 'Competency deleted, with its sub-competencies.';
$string['compinuse'] = 'This competency, or one of its sub-competencies, is targeted by a module or a course: it cannot be deleted.';
$string['competencyframeworks'] = 'Competency frameworks';
$string['competencyframeworks_help'] = 'Competency frameworks are common to the whole site. Module leaders link their module to them (module "Competencies" page); you can link a course without a module from the matrix.';
$string['courselink'] = 'Competencies of the course "{$a}"';
$string['courselink_help'] = 'This course has no "Module syllabus" activity: its competencies are linked here, by the academic office.';
$string['coursenotinscope'] = 'This course is not in the scope of the activity.';
$string['coursewithoutmodule'] = 'Course without module';
$string['csvfile'] = 'CSV file';
$string['csvfile_help'] = 'A header line, then one line per competency. Columns: "Code" (required), "Parent code" (empty for a domain), "Name" (required), "Description". Semicolon, comma or tab separator. A competency whose code already exists is updated.';
$string['editcompetency'] = 'Edit the competency';
$string['editframework'] = 'Edit the framework';
$string['frameworkdeleted'] = 'Framework deleted.';
$string['frameworkinuse'] = 'Competencies of this framework are targeted by modules or courses: it cannot be deleted.';
$string['importcompetencies'] = 'Import competencies (CSV)';
$string['importcompetencies_help'] = 'Code;Parent code;Name;Description — for example "C1;;Clinical reasoning;" then "C1.1;C1;Perform a clinical examination;".';
$string['importdone'] = 'Import finished: {$a->created} competency(ies) created, {$a->updated} updated.';
$string['linkcourse'] = 'Link a course without module…';
$string['links'] = 'Links';
$string['nocompetenciesyet'] = 'This framework has no competency yet: add some below, or import a CSV file.';
$string['noframeworksdeve'] = 'No competency framework: create one in "Competency frameworks".';
$string['open'] = 'Open';
$string['parentcompetency'] = 'Parent competency';
$string['toplevel'] = 'None (top-level domain)';
$string['movedown'] = 'Move down';
$string['moveup'] = 'Move up';
$string['rename'] = 'Rename';
$string['alongcursus'] = 'Along the curriculum';
$string['booklet'] = 'Syllabus booklet';
$string['booklet_help'] = 'Print to PDF the published syllabi of the modules of a semester, a year of study or the whole curriculum: title page, contents, table of modules and credits per period, then one syllabus per module. Modules whose activity is hidden are left out.';
$string['bookletcount'] = '{$a->published} published syllabus(es) out of {$a->total} module(s); only published syllabi are printed.';
$string['bookletdownload'] = 'Download the booklet (PDF)';
$string['bookletselection'] = 'Booklet scope';
$string['choosecompetency'] = 'Choose a competency…';
$string['findings'] = 'Findings';
$string['friezelegend'] = 'Levels targeted in each period (I: introduced, D: developed, M: mastered); the colour is that of the highest level.';
$string['modulesn'] = '{$a} module(s)';
$string['notreached'] = 'never';
$string['progression'] = 'Competency progression';
$string['progression_help'] = 'Choose a competency: the page shows, period by period, the modules that target it, at which level, and what their published syllabus says about it (learning outcomes, methods, assessment).';
$string['warning_introducedlate'] = 'The competency is targeted again at the "Introduced" level after having been mastered.';
$string['warning_nevermastered'] = 'No module targets this competency at the "Mastered" level.';
$string['warning_noprogression'] = 'The competency is targeted straight away beyond the "Introduced" level: no module introduces it beforehand.';
$string['warning_notcovered'] = 'No module in scope targets this competency.';
$string['withsubcompetencies'] = 'with its sub-competencies';
$string['ectsn'] = '{$a} ECTS credits';
$string['levelreached'] = '{$a->level}: {$a->period}';
$string['show'] = 'Show';
$string['confirmdeletetype'] = 'Delete the type "{$a}"?';
$string['savesettings'] = 'Save settings';
$string['confirmdeleteframework'] = 'Delete the framework "{$a}" and all its competencies?';
$string['confirmdeletecomp'] = 'Delete the competency "{$a}" and its sub-competencies?';
$string['nomanageright'] = 'No DEVE role in this course';
$string['replacesversion'] = 'Update (replaces version {$a})';
$string['firstversion'] = 'First version';
$string['errornoselection'] = 'Tick at least one module.';
$string['erroropenrequired'] = 'Enter the opening date (or use "Remove the evaluation").';
$string['removewindow'] = 'Remove the evaluation';
$string['removewindowconfirm'] = 'Remove the evaluation of the ticked modules? Their window will be cleared.';
$string['allyears'] = 'All years';
