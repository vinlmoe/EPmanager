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
 * English strings for mod_ep.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Personalised learning';
$string['modulename'] = 'Personalised learning';
$string['modulenameplural'] = 'Personalised learning activities';
$string['modulename_help'] = 'Manages the ECTS credits awarded for personalised learning: the catalogue of '
    . 'internal academic units students enrol in, learning declared outside the catalogue (student engagement, '
    . 'professional experience, sport, external academic units) and internship credits, awarded automatically '
    . 'from the complementary internships already validated by the academic office in the "Internship '
    . "management\" activity of the same course.\n\nEach type has its own cap on retained credits (per year and "
    . 'over the whole programme), and minimums are required both per study year and over the whole programme. '
    . 'Catalogue enrolments are validated by the person in charge of the unit, declarations outside the '
    . "catalogue by the student's referent teacher.";
$string['modulename_link'] = 'mod/ep/view';
$string['pluginadministration'] = 'Personalised learning administration';
$string['epname'] = 'Activity name';

// Capabilities.
$string['ep:addinstance'] = 'Add a new personalised learning activity';
$string['ep:view'] = 'View the personalised learning activity';
$string['ep:submit'] = 'Enrol in a unit and declare own personalised learning';
$string['ep:evaluateteacher'] = 'Validate the personalised learning one is in charge of';
$string['ep:validatedeve'] = 'Validate and withdraw personalised learning for all students (academic office)';
$string['ep:manage'] = 'Manage types, catalogue and credit minimums';
$string['ep:viewall'] = 'View the personalised learning of all students';

// Navigation and pages.
$string['administration'] = 'Administration';
$string['catalog'] = 'Unit catalogue';
$string['declarecredit'] = 'Declare personalised learning';
$string['validation'] = 'Validation';
$string['pilotage'] = 'Overview';
$string['exportcsv'] = 'CSV export';
$string['mycredits'] = 'My personalised learning';
$string['creditdetail'] = 'Personalised learning details';
$string['managetypes'] = 'Types and caps';
$string['managecatalog'] = 'Internal unit catalogue';
$string['manageyearrequirements'] = 'Credit minimums';
$string['actions'] = 'Actions';
$string['viewdetails'] = 'Details';
$string['searchstudent'] = 'Student name';
$string['resetfilters'] = 'Reset filters';
$string['student'] = 'Student';
$string['teacher'] = 'Teacher';
$string['status'] = 'Status';
$string['hidden'] = 'hidden';
$string['enabled'] = 'Enabled';

// Instance settings.
$string['currentstudyyear'] = 'Current study year (fallback)';
$string['currentstudyyear_help'] = 'Study year the cohort is currently in. It is used as the reference: it says '
    . 'which catalogue units students may enrol in, which yearly minimums are already due, and which year a unit '
    . 'may be attached to — theirs, the previous one (catch-up) or the next one (early completion).'
    . "\n\nIt is normally read from the \"Internship management\" activity of the course, where it is already "
    . 'kept up to date from year to year: this setting is only used when no "Internship management" activity of '
    . 'the course provides it.';
$string['mincursusects'] = 'Minimum over the whole programme';
$string['mincursusects_help'] = 'Total number of personalised learning credits to be validated over the whole '
    . 'programme, all types and years together (0 = no requirement). This minimum adds to the yearly ones: '
    . 'meeting each yearly minimum does not necessarily meet the programme one.';
$string['stagesource'] = 'Source "Internship management" activity';
$string['stagesource_help'] = 'Activity whose complementary internships validated by the academic office lead to '
    . 'an automatic credit award. "All of them in this course" is fine as long as the course only holds one; '
    . 'naming a specific activity is for courses holding several, so that internships from an archived activity '
    . 'are not counted again.';
$string['stagesourceall'] = 'All of them in this course';
$string['typesnotice'] = 'The six personalised learning types (internal academic, internship, student '
    . 'engagement, professional experience, sport, external academic) are created together with the activity. '
    . 'Their credit caps and the internship rate are then set from the activity administration.';

// Types.
$string['type'] = 'Type';
$string['type_help'] = 'The type determines the applicable credit cap and who validates the request. Internal '
    . 'academic units are taken from the catalogue and internship credits are awarded automatically: neither is '
    . 'declared here.';
$string['type_academic'] = 'Internal academic';
$string['type_stage'] = 'Internship';
$string['type_engagement'] = 'Student engagement';
$string['type_professional'] = 'Professional experience';
$string['type_sport'] = 'Sport';
$string['type_external'] = 'External academic';
$string['typeattribution'] = 'Award';
$string['attributionautomatic'] = 'Automatic';
$string['attributioncatalog'] = 'Catalogue enrolment';
$string['attributiondeclared'] = 'Student declaration';
$string['typeinstruction'] = 'Guidance shown to the student';
$string['typessaved'] = 'Types saved.';
$string['managetypes_help'] = 'The six types are fixed: they can be configured and disabled, but not added or '
    . 'removed. A cap of 0 means "no cap". Credits validated beyond a cap remain awarded: they simply stop being '
    . "counted, and come back into the count if the cap is raised.\n\nThe \"How credits are set\" column says, "
    . 'for each type the student declares, where the number of credits requested comes from: proposed by the '
    . 'student case by case, a flat rate (1 credit per sport declaration, say), or counted by the week. The rate '
    . 'under the rule is that flat amount, or the number of credits per week.';
$string['managetypes_desc'] = 'Label, guidance, activation, maximum retained credits (per year and over the '
    . 'programme), how a declaration\'s credits are set (proposed by the student, flat rate, or per week) and, '
    . 'for the internship type, credits per validated complementary internship day.';
$string['maxects'] = 'Maximum over the programme';
$string['maxectsperyear'] = 'Maximum per year';
$string['maxectsshort'] = 'max. {$a} credits';
$string['ectsperday'] = 'Credits per internship day';
$string['ectsrule'] = 'How credits are set';
$string['ectsvalue'] = 'Rate';
$string['ectsvalueunset'] = 'rate not set';
$string['ectsmode_free'] = 'Proposed by the student';
$string['ectsmode_flat'] = 'Flat rate per declaration';
$string['ectsmode_weekly'] = 'Per declared week';
$string['ectsrulefree'] = 'Credits proposed by the student';
$string['ectsruleflat'] = 'Flat rate of {$a} credits per declaration';
$string['ectsruleweekly'] = '{$a} credits per declared week';
$string['ectsrulecatalog'] = 'Each catalogue unit carries its own credits.';
$string['weeks'] = 'Number of weeks';
$string['weeks_help'] = 'Number of weeks this personalised learning amounted to. The credits requested follow '
    . 'from it: this type is counted by the week, so there is no number of credits for you to propose.';
$string['syncstagecredits'] = 'Resynchronise internship credits';
$string['syncstagecredits_help'] = 'Immediately recomputes the credits awarded automatically from the '
    . 'complementary internships validated by the academic office. This is done nightly and whenever a page of '
    . 'the activity is opened anyway: this button is there to check the effect right after changing the rate.';
$string['syncdone'] = 'Synchronisation complete: {$a->created} created, {$a->updated} updated, '
    . '{$a->deleted} removed.';
$string['tasksyncstagecredits'] = 'Award personalised learning credits from complementary internships';

// Catalogue.
$string['catalogactivity'] = 'Unit';
$string['catalogactivitytype_help'] = 'Type this unit belongs to: it determines the credit cap that applies to '
    . 'the student. This is normally the internal academic type.';
$string['catalogactivityects_help'] = 'Number of credits awarded to the student once the person in charge '
    . 'validates their participation, at the end of the unit. It belongs to the unit: the student does not '
    . 'choose it.';
$string['catalogactivityvisible_help'] = 'A hidden unit accepts no new enrolment but keeps the ones already '
    . 'taken. This is how a unit that is no longer offered is closed, without erasing credits already awarded.';
$string['ects'] = 'Credits';
$string['capacity'] = 'Places';
$string['capacity_help'] = '0 means "unlimited places". This number does not block enrolments: students enrol '
    . 'freely and the person in charge decides which enrolments to accept, going beyond the number of places if '
    . 'they see fit. Only accepted enrolments take up a place.';
$string['places'] = 'Places';
$string['placestaken'] = '{$a->taken} of {$a->total}';
$string['activityfull'] = 'full';
$string['unlimitedplaces'] = 'Unlimited';
$string['registrations'] = 'Enrolments';
$string['countpending'] = '{$a} pending';
$string['countenrolled'] = '{$a} accepted';
$string['countvalidated'] = '{$a} validated';
$string['pendingregistrationscount'] = '{$a} enrolment(s) pending';
$string['catalogactivitytype'] = 'Type';
$string['catalogactivityects'] = 'Credits for this unit';
$string['catalogactivityvisible'] = 'Open for enrolment';
$string['sharedactivity'] = 'shared';
$string['sharedactivities'] = 'Shared units offered to this cohort';
$string['sharedactivities_help'] = 'These units are defined in a "Personalised learning follow-up" activity, '
    . 'because students from several cohorts enrol in them. They appear in this cohort\'s catalogue like any '
    . 'other, but can only be edited where they are defined.';
$string['sharedactivityorphan'] = 'follow-up activity deleted';
$string['definedin'] = 'Defined in';
$string['noownactivities'] = 'No unit of this cohort\'s own yet.';
$string['openforregistration'] = 'Open';
$string['addactivity'] = 'Add a unit to the catalogue';
$string['activitysaved'] = 'Unit saved. Now name the person or people in charge.';
$string['activitydeleted'] = 'Unit removed from the catalogue.';
$string['confirmdeleteactivity'] = 'Permanently delete this unit from the catalogue?';
$string['nocatalogactivities'] = 'No unit is offered in the catalogue yet.';
$string['managecatalog_desc'] = 'The internal academic units offered to students: title, credits, study years '
    . 'concerned, number of places and the people who will validate enrolments.';
$string['activityteachers'] = 'People in charge';
$string['activityteachersfor'] = 'People in charge of: {$a}';
$string['activityteacherscount'] = '{$a} in charge';
$string['activityteacherssaved'] = 'People in charge saved.';
$string['activityteachers_help'] = 'Tick the teachers in charge of this unit. They, and only they, validate '
    . "enrolments in it — the student's referent teacher has no say over it.";
$string['responsible'] = 'In charge';
$string['noteacherwarning'] = 'nobody in charge';
$string['nopotentialteachers'] = 'No teacher in this course is allowed to validate personalised learning: assign '
    . 'a teacher role in this course first.';
$string['studyyearrange'] = 'Study years';
$string['minstudyyear'] = 'Minimum study year';
$string['maxstudyyear'] = 'Maximum study year';
$string['sortorder'] = 'Display order';

// Catalogue enrolment.
$string['register'] = 'Enrol';
$string['registertoactivity'] = 'Enrolling in: {$a}';
$string['registerects'] = 'This unit is worth {$a} credits.';
$string['registerpendingnotice'] = 'Your enrolment will be sent to the person in charge of the unit, who will '
    . 'decide whether to accept it. The credits will only count at the end of the unit, once they validate them.';
$string['confirmregistration'] = 'Confirm my enrolment';
$string['registered'] = 'Enrolment saved, awaiting the decision of the person in charge.';
$string['registerclosed'] = 'Enrolment closed';
$string['registerfullnotice'] = 'All places in this unit are already taken. You may still enrol: the person in '
    . 'charge will decide whether to accept your enrolment.';
$string['registerfullshort'] = 'Full: enrolment subject to acceptance.';
$string['notopentoyear'] = 'Not open to your study year';
$string['motivation'] = 'Motivation';
$string['motivation_help'] = 'Explain in a few words what motivates your enrolment in this unit (optional). This '
    . 'is what the person in charge reads to decide, especially if there are more requests than places.';
$string['catalogprocessnotice'] = 'Enrolling takes up no place and awards no credits: the person in charge of '
    . 'the unit first accepts enrolments, then validates the credits at the end of the unit.';

// Declaration outside the catalogue.
$string['creditname'] = 'Title';
$string['creditdescription'] = 'Description and justification';
$string['creditdescription_help'] = 'Describe what you did, when, and in what capacity. Your referent teacher '
    . 'will decide on the basis of this description and of your supporting documents.';
$string['claimedects'] = 'Credits requested';
$string['claimedects_help'] = 'Number of credits you are requesting for this. The validator may retain only part '
    . "of them without rejecting your whole request.\n\nThis field only appears for types that let you propose "
    . 'a number: the others award a flat rate per declaration, or are counted by the week.';
$string['evidencefiles'] = 'Supporting documents';
$string['evidencefiles_help'] = 'Certificates, agreements, diplomas — any document allowing what you declare to '
    . 'be checked.';
$string['noevidencefiles'] = 'No supporting document submitted.';
$string['creditsubmitted'] = 'Request saved, awaiting validation.';
$string['cancelrequest'] = 'Withdraw my request';
$string['requestcancelled'] = 'Request withdrawn.';
$string['cancelledbystudent'] = 'Request withdrawn by the student.';
$string['declarereferent'] = 'Your request will be sent to your referent teacher: {$a}.';
$string['declarenoreferent'] = 'You have no referent teacher assigned in the "Internship management" activity of '
    . 'this course: your request will be handled by the academic office.';
$string['studentreferents'] = 'Referent teacher(s): {$a}';

// Validation.
$string['awaitingmydecision'] = 'Awaiting your decision';
$string['awaitingectsvalidation'] = 'Units under way whose credits are still to be validated';
$string['noenrolledcredits'] = 'No unit under way is awaiting credit validation.';
$string['acceptregistration'] = 'Accept the enrolment';
$string['validateects'] = 'Validate the credits';
$string['registrationaccepted'] = 'Enrolment accepted.';
$string['decisionregistrationnotice'] = 'Accepting the enrolment gives the student their place in this unit. No '
    . 'credit is awarded yet: you will validate those at the end of the unit, in the light of what they did.';
$string['decisionectsnotice'] = 'The unit is over: set the number of credits actually retained. You may retain '
    . 'only part of them without rejecting the whole request.';
$string['acceptbeyondcapacity'] = 'All places in this unit are already taken. You may still accept this '
    . 'enrolment if you see fit: the number of places is a guide, not a limit.';
$string['activityoccupancy'] = 'Places: {$a->places} — enrolments pending: {$a->pending}';
$string['gradebulk'] = 'Grade in bulk';
$string['gradeactivity'] = 'Grade: {$a}';
$string['gradingnotice'] = 'For each student, enter a grade out of 20 — it alone decides validation (from 10/20, '
    . 'the full credits requested are retained) or rejection —, or tick "Validate" to retain the full credits '
    . 'directly with no grade. A row left blank is not processed: you can come back to it later.';
$string['grade'] = 'Grade';
$string['gradevalue'] = '{$a->grade} / {$a->max}';
$string['validatedirectly'] = 'Validate';
$string['nogradableregistrations'] = 'No enrolment on this unit is awaiting credit validation for now.';
$string['gradingdone'] = '{$a} decision(s) recorded.';
$string['allcreditsinscope'] = 'Everything within your scope';
$string['nopendingcredits'] = 'No request is awaiting your decision.';
$string['nocredits'] = 'No personalised learning.';
$string['validatecredit'] = 'Validate';
$string['reject'] = 'Reject';
$string['decision'] = 'Decision';
$string['validatorcomment'] = 'Comment';
$string['decidedby'] = 'Decision made by';
$string['decidedon'] = 'Decision date';
$string['creditvalidated'] = 'Personalised learning validated.';
$string['creditrejected'] = 'Personalised learning rejected.';
$string['creditcancelled'] = 'Personalised learning withdrawn.';
$string['creditalreadydecided'] = 'This request has already been dealt with.';
$string['cancelcredit'] = 'Withdraw';
$string['cancelreason'] = 'Reason for withdrawal';
$string['confirmcancelcredit'] = "Withdraw this from the student's record?";
$string['fromcatalogactivity'] = 'Enrolment in unit "{$a->name}" — in charge: {$a->teachers}';
$string['automatic'] = 'automatic';
$string['automaticattribution'] = 'Automatic award';
$string['automaticcreditnotice'] = 'This is awarded automatically from a complementary internship already '
    . 'validated by the academic office: there is nothing to validate here. To correct or withdraw it, the '
    . 'source internship must be revisited in the "Internship management" activity.';
$string['submittedon'] = 'Requested on';
$string['pendingrequests'] = 'Pending requests';
$string['origin'] = 'Origin';
$string['source_student'] = 'Student';
$string['source_stage'] = 'Complementary internship';
$string['source_deve'] = 'Academic office';

// Statuses.
$string['status_cancelled'] = 'Withdrawn';
$string['status_rejected'] = 'Rejected';
$string['status_pending'] = 'Pending';
$string['status_enrolled'] = 'Enrolment accepted';
$string['status_validated'] = 'Validated';
$string['allstatuses'] = 'All statuses';
$string['alltypes'] = 'All types';
$string['allyears'] = 'All years';

// Study years.
$string['studyyear'] = 'Study year';
$string['studyyear_help'] = 'Study year this is attached to: it determines which yearly minimum it counts '
    . 'towards.';
$string['studyyear_unspecified'] = 'Unspecified';
$string['studyyear_n'] = 'Y{$a}';

// Progress.
$string['summary'] = 'Summary';
$string['summaryitem'] = 'Item';
$string['summaryvalue'] = 'Value';
$string['summarytotalretained'] = 'Total credits retained';
$string['summarycursusminimum'] = 'Programme minimum';
$string['summaryyearsdone'] = 'Years completed';
$string['summarypending'] = 'Credits awaiting validation';
$string['summarycapped'] = 'Validated credits not retained (type cap reached)';
$string['yeartotals'] = 'Progress by study year';
$string['typetotals'] = 'Progress by type';
$string['allmycredits'] = 'Personalised learning details';
$string['objective'] = 'Objective';
$string['yearminimum'] = 'Yearly minimum';
$string['objectivedone'] = 'Met';
$string['objectivetodo'] = 'To complete';
$string['requiredects'] = 'Credits required';
$string['retainedects'] = 'Credits retained';
$string['remainingects'] = 'Still to validate';
$string['validatedects'] = 'Credits validated';
$string['cappedects'] = 'Not retained';
$string['cappedshort'] = '{$a} not retained';
$string['pendingects'] = 'Pending';
$string['ectsvalue'] = '{$a} credits';
$string['progressofects'] = '{$a->retained} / {$a->required} credits';
$string['totalretainedshort'] = 'Credits retained';
$string['cursusminimumshort'] = 'Programme minimum';
$string['yearsdoneshort'] = 'Years completed';
$string['noyearminimum'] = 'No yearly minimum';
$string['nostudents'] = 'No student to display.';
$string['numcredits'] = '{$a} personalised learning item(s)';
$string['cursusminimum'] = 'Minimum over the whole programme';
$string['cursusminimum_help'] = 'Adds to the yearly minimums above: a student may have met every yearly minimum '
    . 'without having met the programme one yet.';
$string['manageyearrequirements_help'] = 'Minimum number of personalised learning credits to validate for each '
    . 'study year, all types together. A year left at 0 requires nothing and does not appear as an objective.';
$string['manageyearrequirements_desc'] = 'Minimum credits to validate per study year and over the whole '
    . 'programme.';
$string['requirementssaved'] = 'Credit minimums saved.';

// Automatic award from internships.
$string['stagecreditname'] = 'Complementary internship — {$a->theme} ({$a->structure})';
$string['stagecreditdescription'] = 'Awarded automatically from {$a->days} internship day(s) retained by the '
    . 'academic office, at {$a->rate} credits per day (activity "{$a->activity}").';
$string['stagelinkheading'] = 'Where internship credits come from';
$string['stagelinklist'] = 'Complementary internships validated by the academic office are read from: {$a}.';
$string['stagelinknone'] = 'No "Internship management" activity is associated with this activity: no internship '
    . 'credit can be awarded automatically. Add an "Internship management" activity to this course, or name one '
    . 'in this activity\'s settings.';
$string['stagelinkrate'] = 'Current rate: {$a} credits per retained internship day.';
$string['stagelinknorate'] = 'No rate is set for the internship type: nothing is awarded automatically while the '
    . 'credits per day remain at 0.';

// Administration.
$string['adminsectionrules'] = 'Award rules';
$string['adminsectioncatalog'] = 'Catalogue';
$string['adminsectionimport'] = 'Import';
$string['adminsectionfollowup'] = 'Follow-up';
$string['adminsectionpage'] = 'Page';
$string['adminsectionpurpose'] = 'What it is for';
$string['exportcsv_desc'] = 'Progress per student, or every item in detail, as CSV.';
$string['exportstudents'] = 'Progress per student';
$string['exportstudents_desc'] = 'One row per student: credits retained per type, progress against the yearly '
    . 'and programme minimums.';
$string['exportcredits'] = 'Detailed list';
$string['exportcredits_desc'] = "One row per item credited to a student, with its status, decision and who made "
    . 'it.';

// Excel/CSV import.
$string['importexcel'] = 'Excel import';
$string['import'] = 'Import';
$string['importactivities'] = 'Import the catalogue';
$string['importactivities_desc'] = 'Create several catalogue units of this cohort at once, from a spreadsheet.';
$string['importactivities_help'] = 'Import a CSV file (saved from Excel via "Save As > CSV"), with the following '
    . 'columns in this order, separated by semicolons or commas, with a header row: '
    . '<code>name;type;ects;minstudyyear;maxstudyyear;capacity;sortorder;visible</code>.'
    . '<ul>'
    . '<li><em>name</em>: title of the unit (required)</li>'
    . '<li><em>type</em>: code or label of the type to attach the unit to (optional, internal academic by '
    . 'default)</li>'
    . '<li><em>ects</em>: number of credits the unit carries (required, greater than 0)</li>'
    . '<li><em>minstudyyear</em>, <em>maxstudyyear</em>: study years concerned, as numbers (optional, 0 = '
    . 'unspecified)</li>'
    . '<li><em>capacity</em>: number of places (optional, 0 = unlimited)</li>'
    . '<li><em>sortorder</em>: display order (optional)</li>'
    . '<li><em>visible</em>: 1 (or blank) for open to enrolment, 0/no for hidden</li>'
    . '</ul>'
    . 'A unit with the same title as one already in this cohort\'s catalogue is rejected rather than '
    . "duplicated. No one is assigned in charge by the import: remember to name them afterwards from the "
    . 'catalogue page, or enrolments will stay pending indefinitely.';
$string['importcredits'] = 'Import enrolments and declarations';
$string['importcredits_desc'] = "Credit several students at once with an enrolment in a catalogue unit or a "
    . "declaration outside the catalogue, decided elsewhere (paper record, end-of-year regularisation...).";
$string['importcredits_help'] = 'Import a CSV file (saved from Excel via "Save As > CSV"), with the following '
    . 'columns in this order, separated by semicolons or commas, with a header row: '
    . '<code>email;ep;name;studyyear;claimedects;weeks;retainedects;status;comment</code>.'
    . '<ul>'
    . '<li><em>email</em>: the student\'s address (must be enrolled in the course)</li>'
    . '<li><em>ep</em>: the exact title of a catalogue unit (enrolment), or the code/label of a declarable '
    . 'type — engagement, professional experience, sport, external academic (declaration outside the '
    . 'catalogue)</li>'
    . '<li><em>name</em>: title of the declaration (ignored for a catalogue enrolment, which takes the '
    . "unit's name)</li>"
    . '<li><em>studyyear</em>: study year to attach it to, as a number (optional, the cohort\'s current year '
    . 'by default)</li>'
    . '<li><em>claimedects</em>: credits requested (ignored for a catalogue enrolment and for a flat-rate or '
    . 'weekly type, which set it themselves)</li>'
    . '<li><em>weeks</em>: number of weeks declared (weekly types only)</li>'
    . '<li><em>retainedects</em>: credits to retain if the status is "validated" (optional, equal to the '
    . 'credits requested by default)</li>'
    . '<li><em>status</em>: pending (default, or if the "Validate directly" option below is left unticked), '
    . 'accepted (catalogue enrolments only), validated or rejected</li>'
    . '<li><em>comment</em>: the validator\'s comment, if there is a decision to record</li>'
    . '</ul>'
    . 'Imported credits are recorded as entered by the academic office. A row whose student already has an '
    . 'active enrolment in the same unit, or an identical declaration already on record, is rejected rather '
    . 'than duplicated.';
$string['importdirectvalidate'] = 'Validate directly the rows with no status';
$string['importdirectvalidate_help'] = 'Without this option, a row whose status column is blank stays pending, '
    . 'just like an online enrolment or declaration. Tick it to validate such rows directly instead — handy for '
    . 'importing a file that is already entirely decided, without writing "validated" on every row. A value '
    . 'written in the status column still takes precedence over this option, row by row.';
$string['importresult'] = '{$a} personalised learning item(s) imported successfully.';
$string['importerrorupload'] = 'The file could not be uploaded. Check its size and try again.';
$string['importerrorline'] = 'Line {$a->line}: {$a->error}';
$string['importerrorincomplete'] = 'Line {$a}: both the address and the unit/type are required.';
$string['importerrormissingname'] = 'Line {$a}: missing title.';
$string['importerrorunknownemail'] = 'Line {$a->line}: no enrolled student with the address "{$a->email}".';
$string['importerrorunknowntype'] = 'Line {$a->line}: type "{$a->type}" not found.';
$string['importerrorunknowntarget'] = 'Line {$a->line}: neither a catalogue unit nor a declarable type matches '
    . '"{$a->target}".';
$string['importerrorunknownstatus'] = 'Line {$a->line}: status "{$a->status}" not recognised (pending, accepted, '
    . 'validated or rejected).';
$string['importerrorenrolledwithoutactivity'] = 'Line {$a}: the "accepted" status only exists for a catalogue '
    . 'unit enrolment.';
$string['importerroractivityduplicate'] = 'Line {$a->line}: a unit named "{$a->name}" already exists in this '
    . 'catalogue.';
$string['importerrorexistingregistration'] = 'Line {$a->line}: this student already has an active enrolment in '
    . '"{$a->ep}".';
$string['importerrorduplicate'] = 'Line {$a}: an identical declaration already exists for this student.';
$string['importerrorduplicateinfile'] = 'Line {$a}: duplicate of an earlier line in the same file.';

// Errors.
$string['errorpositiveects'] = 'The number of credits must be greater than 0.';
$string['errorpositiveweeks'] = 'The number of weeks must be greater than 0.';
$string['errortypeectsunset'] = 'The rate for this type has not been set yet: tell the academic office, who must '
    . 'fill it in before you can declare this type.';
$string['errornegativeects'] = 'The number of credits cannot be negative.';
$string['errornegativecapacity'] = 'The number of places cannot be negative.';
$string['errorstudyyearrange'] = 'The minimum study year cannot be after the maximum one.';
$string['erroractivityinuse'] = 'This unit cannot be deleted: students are enrolled in it. Close it to '
    . 'enrolments instead.';
$string['errornotypes'] = 'No type is defined: configure the types first.';
$string['errorinvalidtype'] = 'Invalid type.';
$string['errornodeclarabletype'] = 'No type can be freely declared in this activity: academic units are taken '
    . 'from the catalogue and internship credits are awarded automatically.';
$string['errorcreditnoteditable'] = 'An enrolment in a catalogue unit cannot be edited: its title and credits '
    . 'are those of the unit. You can only withdraw it.';
$string['errorcreditdecided'] = 'This request has already been dealt with: it can no longer be edited or '
    . 'withdrawn.';
$string['erroralreadyregistered'] = 'You are already enrolled in this unit.';
$string['errorregisterclosed'] = 'This unit accepts no further enrolment.';
$string['errorunknownactivity'] = 'This unit does not exist, or is not offered here.';
$string['errorwrongyear'] = 'This unit is not open to that study year.';
$string['errorevidencemissing'] = 'This supporting document cannot be found.';

// Privacy.
$string['privacy:metadata:ep_credit'] = 'The personalised learning credited to a student: catalogue enrolments, '
    . 'declarations and automatic awards.';
$string['privacy:metadata:ep_credit:userid'] = 'Student the personalised learning is credited to.';
$string['privacy:metadata:ep_credit:name'] = 'Title of the personalised learning.';
$string['privacy:metadata:ep_credit:description'] = 'Description and justification entered by the student.';
$string['privacy:metadata:ep_credit:weeks'] = 'Number of weeks declared, for types counted by the week.';
$string['privacy:metadata:ep_credit:claimedects'] = 'Number of credits requested.';
$string['privacy:metadata:ep_credit:retainedects'] = 'Number of credits retained after validation.';
$string['privacy:metadata:ep_credit:grade'] = 'Grade out of 20 awarded during a bulk grading.';
$string['privacy:metadata:ep_credit:status'] = 'State of the request (pending, validated, rejected, withdrawn).';
$string['privacy:metadata:ep_credit:validatedby'] = 'User who validated, rejected or withdrew the request.';
$string['privacy:metadata:ep_credit:validatorcomment'] = "Validator's comment.";
$string['privacy:metadata:ep_credit:timecreated'] = 'Date of the request.';
$string['privacy:metadata:ep_activity_teacher'] = 'Teachers named in charge of a catalogue unit.';
$string['privacy:metadata:ep_activity_teacher:teacherid'] = 'Teacher in charge.';
$string['privacy:metadata:core_files'] = 'Supporting documents submitted with a request.';
$string['privacy:path:credits'] = 'Personalised learning';
