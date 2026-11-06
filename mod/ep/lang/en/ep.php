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
$string['decision'] = 'Decision';
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
