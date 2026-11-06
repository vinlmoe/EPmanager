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
$string['currentstudyyear'] = 'Current study year';
$string['currentstudyyear_help'] = 'Study year the cohort is currently in. It is used as the reference: only the '
    . 'minimums of this year and earlier ones are presented as due, and a student may attach a unit to the '
    . 'previous year (catch-up) or the next one (early completion).';
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
$string['managetypes_desc'] = 'Label, guidance, activation, maximum retained credits (per year and over the '
    . 'programme) and, for the internship type, credits per validated complementary internship day.';
$string['maxects'] = 'Maximum over the programme';
$string['maxectsperyear'] = 'Maximum per year';
$string['maxectsshort'] = 'max. {$a} credits';
$string['ectsperday'] = 'Credits per internship day';
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
    . 'validates their enrolment. It belongs to the unit: the student does not choose it.';
$string['catalogactivityvisible_help'] = 'A hidden unit accepts no new enrolment but keeps the ones already '
    . 'taken. This is how a unit that is no longer offered is closed, without erasing credits already awarded.';
$string['ects'] = 'Credits';
$string['capacity'] = 'Places';
$string['capacity_help'] = '0 means "unlimited places". Enrolments awaiting validation take up a place: opening '
    . 'more places than the person in charge can validate would promise a place that does not exist.';
$string['places'] = 'Places';
$string['placesleft'] = '{$a->left} of {$a->total}';
$string['unlimitedplaces'] = 'Unlimited';
$string['registrations'] = 'Enrolled';
$string['openforregistration'] = 'Open';
$string['addactivity'] = 'Add a unit to the catalogue';
$string['activitysaved'] = 'Unit saved. Now name the person or people in charge.';
$string['activitydeleted'] = 'Unit removed from the catalogue.';
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
$string['registerpendingnotice'] = 'Your enrolment will be sent to the person in charge of the unit. The credits '
    . 'will only count once they have validated it.';
$string['confirmregistration'] = 'Confirm my enrolment';
$string['registered'] = 'Enrolment saved, awaiting validation by the person in charge.';
$string['registerclosed'] = 'Enrolment closed';
$string['nofreeplace'] = 'Full';
$string['notopentoyear'] = 'Not open to your study year';

// Declaration outside the catalogue.
$string['creditname'] = 'Title';
$string['creditdescription'] = 'Description and justification';
$string['creditdescription_help'] = 'Describe what you did, when, and in what capacity. Your referent teacher '
    . 'will decide on the basis of this description and of your supporting documents.';
$string['claimedects'] = 'Credits requested';
$string['claimedects_help'] = 'Number of credits you are requesting for this. The validator may retain only part '
    . 'of them without rejecting your whole request.';
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
$string['allcreditsinscope'] = 'Everything within your scope';
$string['nopendingcredits'] = 'No request is awaiting your decision.';
$string['nocredits'] = 'No personalised learning.';
$string['validatecredit'] = 'Validate';
$string['validate'] = 'Validate';
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

// Errors.
$string['errorpositiveects'] = 'The number of credits must be greater than 0.';
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
$string['errornoplaceleft'] = 'There is no place left in this unit.';
$string['errorwrongyear'] = 'This unit is not open to that study year.';
$string['errorevidencemissing'] = 'This supporting document cannot be found.';

// Privacy.
$string['privacy:metadata:ep_credit'] = 'The personalised learning credited to a student: catalogue enrolments, '
    . 'declarations and automatic awards.';
$string['privacy:metadata:ep_credit:userid'] = 'Student the personalised learning is credited to.';
$string['privacy:metadata:ep_credit:name'] = 'Title of the personalised learning.';
$string['privacy:metadata:ep_credit:description'] = 'Description and justification entered by the student.';
$string['privacy:metadata:ep_credit:claimedects'] = 'Number of credits requested.';
$string['privacy:metadata:ep_credit:retainedects'] = 'Number of credits retained after validation.';
$string['privacy:metadata:ep_credit:status'] = 'State of the request (pending, validated, rejected, withdrawn).';
$string['privacy:metadata:ep_credit:validatedby'] = 'User who validated, rejected or withdrew the request.';
$string['privacy:metadata:ep_credit:validatorcomment'] = "Validator's comment.";
$string['privacy:metadata:ep_credit:timecreated'] = 'Date of the request.';
$string['privacy:metadata:ep_activity_teacher'] = 'Teachers named in charge of a catalogue unit.';
$string['privacy:metadata:ep_activity_teacher:teacherid'] = 'Teacher in charge.';
$string['privacy:metadata:core_files'] = 'Supporting documents submitted with a request.';
$string['privacy:path:credits'] = 'Personalised learning';

// Concurrent access and confirmations.
$string['erroractivitybusy'] = 'Another operation is in progress on this activity: try again in a few seconds.';
$string['errorcreditchanged'] = 'This request changed in the meantime (decision taken, request withdrawn): nothing was saved. Check its current state.';
$string['errorconfirmcancel'] = 'Tick the confirmation box to withdraw this unit.';
$string['confirmdeleteactivity'] = 'Permanently delete the unit "{$a}" from the catalogue?';
$string['confirmcancelcredit'] = "I confirm the withdrawal of this unit from the student's record.";

// Events.
$string['eventcreditcreated'] = 'Personalised learning requested';
$string['eventcreditupdated'] = 'Personalised learning updated';
$string['eventcreditvalidated'] = 'Personalised learning validated';
$string['eventcreditrejected'] = 'Personalised learning rejected';
$string['eventcreditcancelled'] = 'Personalised learning cancelled';
$string['eventcreditsynced'] = 'Personalised learning synchronised from an internship';

// Configurable types (lot 2).
$string['addtype'] = 'Add a type';
$string['edittype'] = 'Edit the type';
$string['typecode'] = 'Short code';
$string['typecode_help'] = 'Identifier of the type in imports and exports: unaccented letters and digits, 20 characters at most, unique in the activity. Left empty, it is derived from the label.';
$string['typename'] = 'Label';
$string['acquisition'] = 'Acquisition mode';
$string['acquisition_help'] = 'How a unit of this type reaches the student\'s record: by enrolment in a catalogue unit (which carries its credits and its owners), by a declaration from the student (with evidence), automatically from the complementary internships validated by the DEVE in "Internship management", or by DEVE entry only (by hand or by import).';
$string['acquisition_catalog'] = 'Enrolment in a catalogue unit';
$string['acquisition_declaration'] = 'Declaration by the student';
$string['acquisition_stage'] = 'Automatic, from complementary internships';
$string['acquisition_deve'] = 'Entered by the DEVE';
$string['validationflow'] = 'Validation flow';
$string['validationflow_help'] = 'Who must validate, and in which order. Each step must be validated to reach the next one; a rejection at any step is final. The DEVE can always decide, at any step, and its decision is final. "None": the unit is validated as soon as it is requested.';
$string['flow_none'] = 'None: validated outright';
$string['role'] = 'Role';
$string['role_owner'] = 'Unit owner';
$string['role_referent'] = 'Referent teacher';
$string['role_deve'] = 'DEVE';
$string['role_student'] = 'Student';
$string['awaitingrole'] = 'awaiting: {$a}';
$string['grading'] = 'Assessment';
$string['grading_help'] = 'Without a grade, the validator validates or not. With a grade, they enter one: the unit is validated if it reaches the threshold, not validated otherwise; the grade is kept in both cases and appears in the diploma supplement.';
$string['grading_none'] = 'No grade (validated / not validated)';
$string['grading_grade'] = 'With a grade';
$string['grademax'] = 'Maximum grade';
$string['gradepass'] = 'Pass threshold';
$string['grade'] = 'Grade';
$string['gradeoutof'] = '{$a->grade} / {$a->max}';
$string['errorgradeoutofrange'] = 'The grade must be between 0 and {$a}.';
$string['errorgradepass'] = 'The threshold cannot exceed the maximum grade.';
$string['evidence'] = 'Evidence';
$string['evidence_help'] = 'Required: the student must attach at least one file to submit the request.';
$string['evidence_none'] = 'Not applicable';
$string['evidence_optional'] = 'Optional';
$string['evidence_required'] = 'Required';
$string['evidencetypes'] = 'Accepted formats';
$string['evidencetypes_pdf'] = 'PDF only';
$string['evidencetypes_documents'] = 'Documents, images and archives';
$string['errorevidencerequired'] = 'At least one piece of evidence is required for this type of unit.';
$string['maxclaimedects'] = 'Maximum credits claimable';
$string['maxclaimedects_help'] = 'Declared types: maximum number of credits a student can claim for a single unit (0 = no limit).';
$string['errormaxclaimedects'] = 'For this type, a unit cannot claim more than {$a} credits.';
$string['stagescope'] = 'Internships taken into account';
$string['stagescope_help'] = 'Several types can be fed by internships, each with its own rate: an internship goes to the first type, in display order, whose scope covers it.';
$string['stagescope_all'] = 'All complementary internships';
$string['stagescope_abroad'] = 'Internships abroad only';
$string['stagescope_domestic'] = 'Internships in France only';
$string['insupplement'] = 'Appears in the diploma supplement';
$string['insupplement_help'] = 'Validated units of this type are listed in section 4.3 of the diploma supplement.';
$string['typesaved'] = 'Type saved.';
$string['typedeleted'] = 'Type deleted.';
$string['confirmdeletetype'] = 'Permanently delete the type "{$a}"?';
$string['errortypeinuse'] = 'This type carries units or credits: disable it rather than deleting it.';
$string['typeenabled'] = 'Type enabled.';
$string['typedisabled'] = 'Type disabled.';
$string['moveup'] = 'Move up';
$string['movedown'] = 'Move down';
$string['managetypes_help'] = 'Each type describes how a unit reaches the student\'s record, who validates it, whether it is graded, what evidence is expected and which credit caps apply. You can add types, reorder them, disable them; a type that carries units or credits cannot be deleted.';
$string['decision_validated'] = 'Validated';
$string['decision_rejected'] = 'Rejected';
$string['decision_cancelled'] = 'Withdrawn';
$string['decisionhistory'] = 'Decision history';
$string['validatestep'] = 'Validate this step';
$string['validatefinal'] = 'Validate definitively';
$string['gradeandvalidate'] = 'Save the grade';
$string['nextstepnotice'] = 'Your validation will pass the request on to the next step: {$a}.';
$string['devefinalnotice'] = 'Your decision is final, whatever the current step.';
$string['stagelinkrates'] = 'Rates: {$a}';
$string['stagescoperate'] = '{$a->name} ({$a->scope}): {$a->rate} credits per day';

// Catalogue and participants (lot 3).
$string['activitystate'] = 'State';
$string['activitystate_help'] = 'Published: in the catalogue, enrolment open during the enrolment period. Closed: still in the catalogue, enrolment closed, grades can still be entered. Archived: removed from the catalogue, enrolments stay in student records. Draft and proposed: a teacher\'s proposal, not yet published.';
$string['activitystate_draft'] = 'Draft';
$string['activitystate_proposed'] = 'Proposed';
$string['activitystate_published'] = 'Published';
$string['activitystate_closed'] = 'Closed';
$string['activitystate_archived'] = 'Archived';
$string['activitycode'] = 'Code';
$string['activitycode_help'] = 'Short code of the unit (for instance EP-ECHO-3), unique in the activity: it identifies the unit in imports and exports.';
$string['erroractivitycodeused'] = 'This code is already used by another unit of this activity.';
$string['registrationstart'] = 'Enrolment opens';
$string['registrationend'] = 'Enrolment closes';
$string['registrationperiod'] = 'Enrolment from {$a->start} to {$a->end}';
$string['registrationfrom'] = 'Enrolment from {$a}';
$string['registrationuntil'] = 'Enrolment until {$a}';
$string['errorregistrationperiod'] = 'Enrolment must close after it opens.';
$string['activitydatestart'] = 'Unit starts';
$string['activitydateend'] = 'Unit ends';
$string['activitygrading'] = 'Assessment of this unit';
$string['activitygrading_help'] = 'By default, the unit follows the assessment of its type. It can set its own: no grade, or its own maximum grade and threshold.';
$string['activitygrading_type'] = 'That of the type ({$a})';
$string['prerequisites'] = 'Prerequisites';
$string['prereqactivities'] = 'Units to have validated';
$string['prereqactivities_help'] = 'The student must have a validated enrolment in each of these catalogue units before enrolling.';
$string['prereqminects'] = 'Minimum credits already retained';
$string['prereqblocking'] = 'Blocking prerequisites';
$string['prereqblocking_help'] = 'Ticked: a missing prerequisite prevents the student from enrolling. Unticked: it is only pointed out, to the student and to the unit owner.';
$string['prereqmissingactivity'] = 'Unit "{$a}" not validated';
$string['prereqmissingects'] = '{$a->required} credits retained required ({$a->retained} at present)';
$string['prereqmissing'] = 'Missing prerequisites: {$a}';
$string['errorprerequisites'] = 'You do not meet the prerequisites of this unit.';
$string['registrationnotopen'] = 'Enrolment closed';
$string['participants'] = 'Participants';
$string['participantsof'] = 'Participants: {$a}';
$string['participantscount'] = '{$a} participant(s)';
$string['participants_help'] = 'Enrolments in this unit. You can enrol students (including after the enrolment period), withdraw a participant whose enrolment is pending, enter the grades or validations of the whole list at once, import them from a CSV file, and close the unit.';
$string['addparticipants'] = 'Enrol students';
$string['addparticipants_help'] = 'The chosen students are enrolled in this unit, pending validation. An enrolment already in progress is not duplicated. Prerequisites do not block an addition: they are shown in the list.';
$string['addparticipantsforce'] = 'Exceed the number of places if necessary (DEVE)';
$string['participantsadded'] = '{$a} student(s) enrolled.';
$string['participantsskipped'] = '{$a} student(s) not enrolled: enrolment already in progress or no place left.';
$string['nostudentstoadd'] = 'All the students of the course are already enrolled in this unit.';
$string['withdrawparticipant'] = 'Withdraw';
$string['withdrawreason'] = 'Reason for withdrawal';
$string['confirmwithdraw'] = 'Withdraw {$a} from this unit? The enrolment is kept, as "cancelled".';
$string['participantwithdrawn'] = 'Participant withdrawn.';
$string['savedecisions'] = 'Save decisions';
$string['decisionssaved'] = 'Decisions saved: {$a->validated} validated, {$a->rejected} not validated, {$a->corrected} grade(s) corrected.';
$string['decisionschanged'] = '{$a} enrolment(s) had changed in the meantime and were not modified.';
$string['decisionvalidate'] = 'Validated';
$string['decisionreject'] = 'Not validated';
$string['decisionnone'] = '—';
$string['decision_corrected'] = 'Grade corrected';
$string['gradecorrectionnotice'] = 'As DEVE, you can correct the grade of an enrolment already decided: the grade decides the validation again.';
$string['awaitingotherrole'] = 'Awaiting: {$a}';
$string['addedby'] = 'Enrolled by {$a}';
$string['closeactivity'] = 'Close the unit';
$string['closeactivity_help'] = 'The unit no longer accepts enrolment but stays in the catalogue; its grades can still be entered. Choose what happens to the enrolments still pending.';
$string['closependingkeep'] = 'Leave them pending';
$string['closependingvalidate'] = 'Validate them outright (ungraded unit)';
$string['closependingreject'] = 'Mark them not validated';
$string['activityclosed'] = 'Unit closed: {$a->validated} validated, {$a->rejected} not validated, {$a->kept} left pending.';
$string['closedactivitycomment'] = 'Decision taken when the unit was closed.';
$string['reopenactivity'] = 'Reopen the unit';
$string['activityreopened'] = 'Unit reopened for enrolment.';
$string['exportparticipants'] = 'Export the list (CSV)';
$string['attendancesheet'] = 'Attendance sheet (PDF)';
$string['signature'] = 'Signature';
$string['importgrades'] = 'Import grades (CSV)';
$string['importgrades_help'] = 'CSV file (semicolon, comma or tab separated), one line per student, with a header line. Columns recognised by their heading: "Email" or "ID number" for the student; "Grade" for a graded unit (comma or decimal point); "Decision" for an ungraded unit ("validated", "yes", "1" or "not validated", "no", "0", "absent"); "Comment", optional. The exported list of the unit can serve as a template.';
$string['importgradesfile'] = 'CSV file';
$string['importgradespreview'] = 'Import preview';
$string['importgradesconfirm'] = 'Confirm the import';
$string['importgradesnone'] = 'No usable line in this file.';
$string['importgradesexpired'] = 'The preview has expired: upload the file again.';
$string['importgradesnostudentcolumn'] = 'Student column not found: add an "Email" or "ID number" column.';
$string['importgradesnovaluecolumn'] = 'Result column not found: add a "Grade" or "Decision" column.';
$string['importgradesunknown'] = 'Line {$a->line}: "{$a->who}" is not enrolled in this unit.';
$string['importgradesduplicate'] = 'Line {$a->line}: "{$a->who}" already appears on line {$a->first}.';
$string['importgradesmissing'] = 'Line {$a->line}: missing grade for "{$a->who}".';
$string['importgradesinvalid'] = 'Line {$a->line}: "{$a->value}" is not a grade between 0 and {$a->max}.';
$string['importgradesbaddecision'] = 'Line {$a->line}: decision "{$a->value}" not recognised.';
$string['myresponsibleactivities'] = 'Units I am responsible for';
$string['pendingregistrations'] = 'Pending';
$string['catalogmanage'] = 'Manage';

// Unit proposals, syllabus, PDF documents, emails (lot 4).
$string['ep:propose'] = 'Propose a unit and write the syllabus of the units one is responsible for';
$string['proposals'] = 'Proposals and syllabi';
$string['proposals_help'] = 'A teacher proposes a new unit by writing its description (syllabus) and submitting it to the DEVE, which publishes it in the catalogue, returns it with a comment or rejects it. The owner of a published unit can also propose an update: the current version stays in the catalogue until the new one is published.';
$string['proposenew'] = 'Propose a new unit';
$string['myproposals'] = 'My proposals and revisions in progress';
$string['proposalstoreview'] = 'Proposals and updates to review';
$string['noproposals'] = 'No proposal or revision in progress.';
$string['syllabus'] = 'Syllabus';
$string['syllabusof'] = 'Syllabus: {$a}';
$string['syllabusversion'] = 'Version {$a}';
$string['syllabusstatus'] = 'Syllabus state';
$string['syllabusstatus_draft'] = 'Being written';
$string['syllabusstatus_submitted'] = 'Submitted to the DEVE';
$string['syllabusstatus_returned'] = 'Returned to its author';
$string['syllabusstatus_published'] = 'Published';
$string['syllabusstatus_superseded'] = 'Superseded';
$string['syllabusstatus_rejected'] = 'Rejected';
$string['nosyllabus'] = 'This unit has no published syllabus yet.';
$string['editsyllabus'] = 'Write the syllabus';
$string['proposeupdate'] = 'Propose an update';
$string['syllabussaved'] = 'Syllabus saved.';
$string['syllabussubmitted'] = 'Syllabus submitted to the DEVE.';
$string['syllabuspublisheddirect'] = 'Update published.';
$string['errorsyllabusnoteditable'] = 'This version can no longer be edited: it was submitted or decided in the meantime.';
$string['submitsyllabus'] = 'Submit to the DEVE';
$string['savedraft'] = 'Save draft';
$string['syllabusdecision_publish'] = 'published';
$string['syllabusdecision_return'] = 'returned for changes';
$string['syllabusdecision_reject'] = 'rejected';
$string['reviewpublish'] = 'Publish';
$string['reviewreturn'] = 'Return to the author';
$string['reviewreject'] = 'Reject';
$string['reviewcomment'] = 'Comment to the author';
$string['reviewdone'] = 'Decision saved: version {$a}.';
$string['pendingrevision'] = 'An update of this syllabus is in progress (version {$a->version}, {$a->status}).';
$string['comparepublished'] = 'Current version';
$string['comparepending'] = 'Proposed version';
$string['proposedby'] = 'Proposed by {$a}';
$string['proposalsdirectpublish'] = 'Owners publish updates directly';
$string['proposalsdirectpublish_help'] = 'Ticked: the update of the syllabus of a unit already published, submitted by its owner, is published at once (the DEVE is only informed through the log). A proposal for a new unit always goes through the DEVE.';
$string['syllabusobjectives'] = 'Objectives';
$string['syllabusoutcomes'] = 'Intended learning outcomes';
$string['syllabusoutcomes_help'] = 'What the student will know or be able to do at the end of the unit, written with action verbs (identify, perform, interpret...).';
$string['syllabusprerequisites'] = 'Prerequisites';
$string['syllabuscontent'] = 'Content';
$string['syllabusmethods'] = 'Teaching methods';
$string['syllabusassessment'] = 'Assessment';
$string['syllabusassessment_help'] = 'Assessment methods (written, oral, practical, continuous...), criteria, weighting and pass threshold.';
$string['syllabusresit'] = 'Resit';
$string['syllabusbibliography'] = 'Reading and resources';
$string['syllabuslanguage'] = 'Language of instruction';
$string['syllabuspersonalhours'] = 'Self-study (hours)';
$string['sessions'] = 'Session programme';
$string['sessions_help'] = 'One line per session: title, kind, duration in hours and lecturer (a teacher of the course, or the name of an external lecturer). Lines without a title are ignored. Use "Add sessions" to get more empty lines.';
$string['sessiontitle'] = 'Session';
$string['sessionkind'] = 'Kind';
$string['sessionkind_lecture'] = 'Lecture';
$string['sessionkind_tutorial'] = 'Tutorial';
$string['sessionkind_practical'] = 'Practical';
$string['sessionkind_clinical'] = 'Clinical';
$string['sessionkind_fieldwork'] = 'Fieldwork';
$string['sessionkind_project'] = 'Project';
$string['sessionkind_other'] = 'Other';
$string['sessionduration'] = 'Duration (h)';
$string['sessionteacher'] = 'Lecturer';
$string['sessionteachername'] = 'External lecturer';
$string['sessiondate'] = 'Date';
$string['addsessions'] = 'Add sessions';
$string['hourstotal'] = 'Teaching hours';
$string['hourssessions'] = 'Sessions: {$a} h';
$string['hourspersonal'] = 'Self-study: {$a} h';
$string['hoursworkload'] = 'Total workload: {$a} h';
$string['hoursperects'] = 'That is {$a} h per credit';
$string['downloadsyllabus'] = 'PDF description';
$string['booklet'] = 'Unit booklet (PDF)';
$string['booklet_help'] = 'All descriptions of the published or closed catalogue units, preceded by a table of contents.';
$string['booklettitle'] = 'Personalised learning booklet';
$string['bookletcontents'] = 'Contents';
$string['pdfsettings'] = 'PDF documents and proposals';
$string['pdfsettings_desc'] = 'Header, footer and logo of descriptions, booklet and attendance sheets; publication of syllabus updates.';
$string['pdfheader'] = 'PDF header';
$string['pdffooter'] = 'PDF footer';
$string['pdflogo'] = 'Logo';
$string['pdfsettingssaved'] = 'Settings saved.';
$string['notifications'] = 'Emails';
$string['notifications_desc'] = 'Text of the emails sent by the activity (decision on a request, proposal to review, decision on a proposal, weekly reminder), and whether they are sent.';
$string['notificationenabled'] = 'Send this email';
$string['notificationsubject'] = 'Subject';
$string['notificationbody'] = 'Message';
$string['notificationvariables'] = 'Available variables: {$a}';
$string['notificationreset'] = 'Leaving the subject and message empty restores the default text.';
$string['notificationssaved'] = 'Emails saved.';
$string['notifykey_creditdecided'] = 'Decision on a unit request (to the student)';
$string['notifykey_proposalsubmitted'] = 'Proposal or update to review (to the DEVE)';
$string['notifykey_proposalreviewed'] = 'Decision on a proposal (to its author)';
$string['notifykey_pendingreminder'] = 'Reminder of pending requests (to validators)';
$string['notify_creditdecided_subject'] = 'Personalised learning: {ep} — {decision}';
$string['notify_creditdecided_body'] = "Hello {student},\n\nYour personalised learning request \"{ep}\" has been {decision}.\nCredits retained: {ects}\nGrade: {grade}\nComment: {comment}\n\nDetails: {link}";
$string['notify_proposalsubmitted_subject'] = 'Unit proposal to review: {ep}';
$string['notify_proposalsubmitted_body'] = "{author} has submitted the syllabus of the unit \"{ep}\".\n\nTo publish, return or reject it: {link}";
$string['notify_proposalreviewed_subject'] = 'Your unit proposal "{ep}": {decision}';
$string['notify_proposalreviewed_body'] = "Hello {author},\n\nThe syllabus of the unit \"{ep}\" has been {decision} by the DEVE.\nComment: {comment}\n\n{link}";
$string['notify_pendingreminder_subject'] = 'Personalised learning: {count} request(s) awaiting your decision';
$string['notify_pendingreminder_body'] = "Hello {recipient},\n\n{count} personalised learning request(s) have been awaiting your decision for more than a week.\n\n{link}";
$string['messageprovider:creditdecided'] = 'Decision on a personalised learning request';
$string['messageprovider:proposalsubmitted'] = 'Unit proposal to review';
$string['messageprovider:proposalreviewed'] = 'Decision on a unit proposal';
$string['messageprovider:pendingreminder'] = 'Reminder of pending unit requests';
$string['tasksendpendingreminders'] = 'Weekly reminder of pending unit requests';
$string['eventsyllabussubmitted'] = 'Unit syllabus submitted';
$string['eventsyllabusreviewed'] = 'Unit syllabus reviewed';
$string['syllabusauthor'] = 'Author';
$string['proposedbylabel'] = 'Proposed by';
$string['syllabusversionlabel'] = 'Version';
$string['errornegativeduration'] = 'A duration cannot be negative.';

// Diploma supplement, year validation, new year, transfer (lot 5).
$string['supplement'] = 'Diploma supplement';
$string['mysupplement'] = 'My diploma supplement';
$string['supplement_help'] = 'The diploma supplement (European Europass model) describes the degree and the student\'s path. Validated personalised learning units appear in it automatically (section 4.3); the student can ask to add other items (engagement, award, certification, mobility...), with evidence, which appear once validated (section 6.1). The DEVE issues it, numbered and certified.';
$string['supplementpreview'] = 'Preview (not certified)';
$string['supplementpreviewwatermark'] = 'PREVIEW — NOT CERTIFIED';
$string['supplementitems'] = 'Items added to the supplement';
$string['supplementitem'] = 'Item';
$string['supplementitemrequest'] = 'Ask to add an item';
$string['supplementitemedit'] = 'Edit the item';
$string['supplementitemsaved'] = 'Request saved.';
$string['supplementitemcancelled'] = 'Request withdrawn.';
$string['supplementitemdecided'] = 'Decision saved.';
$string['supplementitemchanged'] = 'This item changed in the meantime: nothing was saved.';
$string['supplementitemstodecide'] = 'Supplement items to review';
$string['supplementitemtitle'] = 'Title';
$string['supplementitemtitleen'] = 'Title in English';
$string['supplementitemtitleen_help'] = 'Optional: used in a supplement issued in English.';
$string['supplementitemdescription'] = 'Description';
$string['supplementitemperiodstart'] = 'Start';
$string['supplementitemperiodend'] = 'End';
$string['supplementproofs'] = 'Evidence (PDF)';
$string['supplementproofs_help'] = 'Attestations, diplomas, certificates, in PDF format.';
$string['errorsupplementproofrequired'] = 'At least one PDF piece of evidence is required.';
$string['supplementcategory'] = 'Category';
$string['supplementcategories'] = 'Item categories';
$string['supplementcategorynameen'] = 'English label';
$string['supplementcategorysaved'] = 'Category saved.';
$string['suppcat_engagement'] = 'Student engagement';
$string['suppcat_representation'] = 'Student representation';
$string['suppcat_award'] = 'Award or distinction';
$string['suppcat_certification'] = 'Certification';
$string['suppcat_mobility'] = 'Mobility';
$string['suppcat_publication'] = 'Publication or communication';
$string['supplementsettings'] = 'Diploma supplement: settings';
$string['supplementsettings_desc'] = 'Text of the fixed sections (in French and English), signatory and stamp, item categories, validation flow and required evidence.';
$string['supplementsettingssaved'] = 'Supplement settings saved.';
$string['supplementflow'] = 'Validation of added items';
$string['supplementflow_help'] = 'Who validates the items the student asks to add: nobody (direct addition), their referent teacher, the DEVE, or the referent then the DEVE.';
$string['supplementevidence'] = 'Evidence for added items';
$string['supplementprefix'] = 'Number prefix';
$string['supplementprefix_help'] = 'Start of the number printed on each issued supplement (for instance ENVT-SD). Default: SD.';
$string['birthdatefield'] = 'Profile field holding the date of birth';
$string['birthdatefield_help'] = 'Custom profile field holding the date of birth (section 1.3). Otherwise, the date entered in an internship agreement request is used.';
$string['birthdatefieldnone'] = 'None (date from internship agreements)';
$string['supplementstamp'] = 'Stamp (image)';
$string['supplementsectiontexts'] = 'Section texts';
$string['supplementsection_qualification'] = 'Name of qualification and title conferred';
$string['supplementsection_fieldofstudy'] = 'Main field(s) of study';
$string['supplementsection_institution'] = 'Name and status of awarding institution';
$string['supplementsection_institutionadmin'] = 'Name and status of institution administering studies';
$string['supplementsection_languages'] = 'Language(s) of instruction and examination';
$string['supplementsection_level'] = 'Level of the qualification';
$string['supplementsection_length'] = 'Official length of programme';
$string['supplementsection_access'] = 'Access requirement(s)';
$string['supplementsection_studymode'] = 'Mode of study';
$string['supplementsection_programmeoutcomes'] = 'Programme learning outcomes';
$string['supplementsection_gradingscheme'] = 'Grading system';
$string['supplementsection_classification'] = 'Overall classification of the qualification';
$string['supplementsection_furtherstudy'] = 'Access to further study';
$string['supplementsection_professional'] = 'Professional status conferred';
$string['supplementsection_additionalinfo'] = 'Additional information (introduction)';
$string['supplementsection_othersources'] = 'Further information sources';
$string['supplementsection_signatoryname'] = 'Name of signatory';
$string['supplementsection_signatorytitle'] = 'Capacity of signatory';
$string['supplementsection_national'] = 'Information on the national higher education system';
$string['supplementtitle'] = 'Supplément au diplôme';
$string['supplementtitleen'] = 'Diploma Supplement';
$string['supplementpreamble'] = 'This Diploma Supplement model was developed by the European Commission, Council of Europe and UNESCO/CEPES. The purpose of the supplement is to provide sufficient independent data to improve the international transparency and fair academic and professional recognition of qualifications. It is designed to provide a description of the nature, level, context, content and status of the studies that were pursued and successfully completed by the individual named on the original qualification to which this supplement is appended. It should be free from any value judgements, equivalence statements or suggestions about recognition.';
$string['supplementpart1'] = 'Information identifying the holder of the qualification';
$string['supplementpart2'] = 'Information identifying the qualification';
$string['supplementpart3'] = 'Information on the level and duration of the qualification';
$string['supplementpart4'] = 'Information on the programme completed and the results obtained';
$string['supplementpart5'] = 'Information on the function of the qualification';
$string['supplementpart6'] = 'Additional information';
$string['supplementpart7'] = 'Certification of the supplement';
$string['supplementpart8'] = 'Information on the national higher education system';
$string['supplementlastname'] = 'Family name(s)';
$string['supplementfirstname'] = 'Given name(s)';
$string['supplementbirthdate'] = 'Date of birth';
$string['supplementidnumber'] = 'Student identification number';
$string['supplementprogrammedetails'] = 'Programme details: validated personalised learning units, credits gained and results';
$string['supplementtotalects'] = 'Total personalised learning credits: {$a} ECTS';
$string['supplementyears'] = 'Years of study validated for personalised learning';
$string['supplementyearvalidated'] = 'Year {$a->year} validated on {$a->date} ({$a->ects} ECTS)';
$string['supplementyearnotvalidated'] = 'Year {$a->year} not validated';
$string['supplementvalidatedgrade'] = 'Pass';
$string['supplementcertdate'] = 'Date';
$string['supplementcertsignature'] = 'Signature';
$string['supplementcertstamp'] = 'Official stamp or seal';
$string['supplementnumber'] = 'Number: {$a}';
$string['supplementverifycode'] = 'Verification code: {$a}';
$string['supplementverifyinfo'] = 'Verifiable at {$a}';
$string['supplementmissingbirthdate'] = 'date of birth unknown';
$string['supplementmissingidnumber'] = 'identification number missing';
$string['supplementmissingsignatory'] = 'signatory missing (supplement settings)';
$string['supplementmissing'] = 'Missing information: {$a}';
$string['supplementissue'] = 'Issue the supplement';
$string['supplementissueforce'] = 'Issue despite missing information';
$string['supplementissued'] = 'Supplement issued: {$a}.';
$string['supplementissues'] = 'Issued supplements';
$string['supplementissuesadmin'] = 'Issuing supplements';
$string['supplementissuesadmin_desc'] = 'Preview and issue each student\'s supplement, issue for the whole cohort, ZIP archive of issued supplements.';
$string['supplementissueall'] = 'Issue for the whole cohort';
$string['supplementissueallqueued'] = 'Issuing of {$a} supplement(s) scheduled: you will be notified by message when it is done.';
$string['supplementissueallreport'] = 'Issuing of supplements finished: {$a->issued} issued, {$a->skipped} not issued (missing information).';
$string['supplementzip'] = 'Download current supplements (ZIP)';
$string['supplementreplaced'] = 'Superseded';
$string['supplementcurrent'] = 'Current';
$string['supplementlanguage'] = 'Language';
$string['supplementverify'] = 'Verify a supplement';
$string['supplementverify_help'] = 'Enter the number and verification code printed on the document.';
$string['supplementverifyok'] = 'This supplement is authentic: issued on {$a->date} for {$a->name}.';
$string['supplementverifyreplaced'] = 'This supplement has been superseded by a more recent issue.';
$string['supplementverifyko'] = 'No supplement matches this number and code.';
$string['supplementnoissue'] = 'No supplement issued yet.';
$string['yearvalidation'] = 'Year validation';
$string['yearvalidation_desc'] = 'DEVE decision on each student\'s year of study, with regard to the minimum credits; bulk validation of years reached.';
$string['yearvalidation_help'] = 'For the chosen year, the list shows whether each student reached the minimum credits. "Validate years reached" records the validation of all who reached it; an individual decision (validated or not, with a comment) remains possible.';
$string['yearvalidated'] = 'Year validated';
$string['yearnotvalidated'] = 'Year not validated';
$string['yearnodecision'] = 'No decision';
$string['yearvalidatecomplete'] = 'Validate years reached';
$string['yearrejectincomplete'] = 'Mark years not reached as not validated';
$string['yearvalidationdone'] = '{$a->validated} year(s) validated, {$a->rejected} not validated, {$a->skipped} left without decision.';
$string['yeardecisionsaved'] = 'Decision saved.';
$string['yearminimumnotreached'] = 'Minimum credits for the year not reached.';
$string['yearreached'] = 'Minimum reached';
$string['yearnotreached'] = 'Minimum not reached';
$string['rollover'] = 'New academic year';
$string['rollover_desc'] = 'Move to the next year of study, review students whose year is not validated, transfer a student to another cohort.';
$string['rollover_help'] = 'Moving to the new year sets the activity\'s current year of study to the next one: the objectives of the new year become due. Credits, decisions and supplements stay in place. Check year validation first.';
$string['rolloverconfirm'] = 'Move the current year from {$a->from} to {$a->to}?';
$string['rolloverdone'] = 'Current year: {$a}.';
$string['rolloverpending'] = '{$a} student(s) without decision or with a year not validated.';
$string['transferstudent'] = 'Transfer a student';
$string['transferstudent_help'] = 'Moves a student\'s record to another personalised learning activity on the site (repeating a year, change of cohort): their units, decisions and evidence, supplement items and year decisions. Types are matched by code, catalogue units by code or title. Supplements already issued stay in the original activity.';
$string['transfertarget'] = 'Destination activity';
$string['transferdone'] = 'Transfer done: {$a->credits} unit(s), {$a->items} supplement item(s), {$a->years} year decision(s).';
$string['transfernotarget'] = 'No other personalised learning activity that you manage on this site.';
$string['transferconfirm'] = 'Transfer the record of {$a->student} to "{$a->target}"?';
$string['eventyeardecided'] = 'Decision on a year of study';
$string['eventsupplementissued'] = 'Diploma supplement issued';
$string['taskissuesupplements'] = 'Bulk issuing of diploma supplements';
$string['messageprovider:supplementissued'] = 'Issuing of diploma supplements finished';
$string['supplementmissingheader'] = 'Missing information';
$string['supplementcategoryadd'] = 'Add a category';
$string['supplementcategoryedit'] = 'Edit the category';
$string['supplementcategoryenabled'] = 'Category enabled.';
$string['supplementcategorydisabled'] = 'Category disabled: it is no longer offered to students; the items already filed under it are kept.';
$string['supplementcategorydeleted'] = 'Category deleted.';
$string['confirmdeletesupplementcategory'] = 'Delete the category "{$a}"? No item uses it.';
$string['supplementverify_desc'] = 'Check that a diploma supplement presented by a student was issued by this activity and has not been altered.';
$string['supplementitemstodecide_desc'] = 'Review the additional activities requested by students for section 6.1 of their supplement.';
$string['supplementnoitems'] = 'No item.';
$string['nopendingsupplementitems'] = 'No supplement item awaits your decision.';
$string['supplementperiod'] = 'Period';
$string['supplementnumberheader'] = 'Number';
$string['supplementverifycodelabel'] = 'Verification code';
$string['errorsupplementperiod'] = 'The end of the period cannot be before its start.';
$string['errornosupplementcategory'] = 'No category of supplement item is open: ask the DEVE.';

// Lot 6 : imports, exports, transfert du paramétrage.
$string['adminsectiontransfer'] = 'Imports, exports and transfer';
$string['importcatalog'] = 'Import the catalogue (CSV)';
$string['importcatalog_desc'] = 'Create or update catalogue units from a CSV file, with their owners, prerequisites and, optionally, their syllabus.';
$string['importsessions'] = 'Import syllabus sessions (CSV)';
$string['importsessions_desc'] = 'Replace the programme (sessions, hours, lecturers) of catalogue units from a CSV file.';
$string['importcredits'] = 'Import credits (CSV)';
$string['importcredits_desc'] = 'Add credits to students\' records from a CSV file: units completed elsewhere, history of a previous system.';
$string['importcsvintro'] = 'CSV file with a header line, separated by semicolons, commas or tabs, saved as UTF-8 or by Excel. Columns are recognised by their heading, in any order, regardless of accents and case. Unknown columns are ignored. Nothing is written before the preview is confirmed.';
$string['importfile'] = 'CSV file';
$string['importcolumns'] = 'Recognised columns';
$string['importcolumnheadings'] = 'Accepted headings';
$string['importcolumnrequired'] = 'Required';
$string['importtemplate'] = 'Download a template';
$string['importpreview'] = 'Import preview';
$string['importconfirm'] = 'Confirm the import';
$string['importexpired'] = 'The preview has expired: upload the file again.';
$string['importnone'] = 'No usable line in this file.';
$string['importwillcreate'] = 'Created';
$string['importwillupdate'] = 'Updated';
$string['importsyllabusnotice'] = 'The file contains syllabus columns: for each unit, a new version of the syllabus is published with these texts (the other sections are kept from the current version).';
$string['importsessionsnotice'] = 'For each unit listed, the programme of the current syllabus is replaced by the sessions of the file, in a new published version.';
$string['importcatalogdone'] = 'Catalogue imported: {$a->created} units created, {$a->updated} updated, {$a->syllabi} syllabi published.';
$string['importsessionsdone'] = 'Programme replaced for {$a} units.';
$string['importcreditsdone'] = '{$a->created} credits added, {$a->skipped} skipped.';
$string['importcolumnmissing'] = 'Required column "{$a}" not found in the header line.';
$string['importcolumnignored'] = 'Column "{$a}" not recognised: ignored.';
$string['importline'] = 'Line {$a->line}: {$a->error}';
$string['importvaluemissing'] = 'missing value in column "{$a}".';
$string['importduplicatecode'] = 'code "{$a->code}" already used on line {$a->line}.';
$string['importunknowntype'] = 'unknown type "{$a}".';
$string['importbadnumber'] = 'column "{$a->column}": "{$a->value}" is not a valid number.';
$string['importbadvalue'] = 'column "{$a->column}": value "{$a->value}" not recognised.';
$string['importbaddate'] = 'column "{$a->column}": "{$a->value}" is not a date (dd/mm/yyyy, optionally followed by hh:mm).';
$string['importunknownuser'] = 'Line {$a->line}: no single account found for "{$a->value}": ignored.';
$string['importunknownprerequisite'] = 'Line {$a->line}: prerequisite "{$a->value}" not found in the catalogue: ignored.';
$string['importunknownactivity'] = 'no catalogue unit with code "{$a}".';
$string['importunknownstudent'] = 'no student of the course matches "{$a}".';
$string['importduplicatecredit'] = 'Line {$a->line}: "{$a->name}" is already credited to this student for that year: ignored.';
$string['importsettingsunknowntype'] = 'Unit "{$a->name}": type "{$a->type}" does not exist in this activity: ignored.';
$string['importsettingsmissingteachers'] = 'Unit "{$a}": some owners have no account on this site and were not added.';
$string['importlog'] = 'Import log';
$string['importlog_desc'] = 'Imports, restorations and settings transfers carried out in this activity.';
$string['importlogempty'] = 'No import yet.';
$string['importlogkind'] = 'Import';
$string['importlogitems'] = 'Items';
$string['importlogsummary'] = 'Result';
$string['importlogkind_catalog'] = 'Catalogue';
$string['importlogkind_sessions'] = 'Syllabus sessions';
$string['importlogkind_credits'] = 'Credits';
$string['importlogkind_settings'] = 'Settings';
$string['importlogkind_archive'] = 'Full restore';
$string['archive'] = 'Full export and restore';
$string['archive_desc'] = 'Download the whole module (settings, catalogue, students\' records, files) as a ZIP archive, or restore such an archive.';
$string['archiveexport'] = 'Download the full export (ZIP)';
$string['archiveexport_help'] = 'The archive contains a workbook readable in a spreadsheet (classeur.xlsx), the settings and catalogue (parametrage.json), the students\' data (donnees.json) and the files (supporting documents, evidence, issued supplements). Only the two JSON files are read when restoring: editing the workbook has no effect.';
$string['archiverestore'] = 'Restore an export';
$string['archiverestore_help'] = 'Restoring adds to this activity what the archive contains, without deleting anything: what already exists is skipped, so restoring the same archive twice duplicates nothing. Students are found by their ID number or email among the students of the course; the data of students not enrolled is not restored.';
$string['archivefile'] = 'Export archive (ZIP)';
$string['archivecontents'] = 'Contents of the archive';
$string['archiveparts'] = 'Parts to restore';
$string['archivepartdata'] = 'Students\' data (credits, supplement items, year decisions, issued supplements)';
$string['archiverestoredone'] = 'Restore done: {$a->credits} credits, {$a->items} supplement items, {$a->years} year decisions, {$a->issues} issued supplements; {$a->skipped} items skipped.';
$string['archivenoparts'] = 'Select at least one part to restore.';
$string['archivecount_credits'] = 'Credits';
$string['archivecount_items'] = 'Supplement items';
$string['archivecount_years'] = 'Year decisions';
$string['archivecount_issues'] = 'Issued supplements';
$string['restoremissingstudent'] = 'Not a student of this course: {$a}. Their data was not restored.';
$string['errorarchiveinvalid'] = 'This file is not an export archive of EP Manager, or comes from an incompatible version.';
$string['errorsettingsformat'] = 'This file is not a settings export of EP Manager, or comes from an incompatible version.';
$string['settingstransfer'] = 'Transfer the settings';
$string['settingstransfer_desc'] = 'Export the rules of this activity (types, minimums, supplement, emails, catalogue) as a JSON file, import such a file, or copy the rules of another activity.';
$string['settingsexport'] = 'Export the settings (JSON)';
$string['settingsexportwithcatalog'] = 'Include the catalogue and syllabi';
$string['settingsimport'] = 'Import settings';
$string['settingsfile'] = 'Settings file (JSON)';
$string['settingscopyfrom'] = 'Copy from another activity';
$string['settingssource'] = 'Source activity';
$string['settingsnosource'] = 'There is no other EP Manager activity that you manage.';
$string['settingsparts'] = 'Parts to import';
$string['settingsparts_help'] = 'Types are matched by their code, categories by their label, catalogue units by their code then their title: they are updated if they exist, created otherwise. Nothing is deleted. Owners and lecturers are found by their email among the accounts of the site.';
$string['settingspart_instance'] = 'General settings (minimum credits, PDF header and footer, supplement workflow)';
$string['settingspart_types'] = 'Unit types';
$string['settingspart_yearrequirements'] = 'Yearly minimums';
$string['settingspart_supplement'] = 'Supplement categories and texts';
$string['settingspart_notifications'] = 'Email templates';
$string['settingspart_catalog'] = 'Catalogue, syllabi and prerequisites';
$string['settingsimportdone'] = 'Settings imported. Created: {$a->created}. Updated: {$a->updated}.';
$string['settingsnone'] = 'none';
$string['settingssourcename'] = 'Exported from "{$a->source}" on {$a->date}.';
