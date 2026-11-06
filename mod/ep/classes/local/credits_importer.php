<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_ep\local;

/**
 * Import d'EP portés au crédit des étudiants depuis un fichier CSV, une ligne par EP : reprise de
 * l'historique d'une promotion, ou saisie en masse par la DEVE. Les crédits importés sont
 * enregistrés tels que décrits (validés, en attente, refusés ou annulés), sans circuit de
 * validation : c'est la DEVE qui les saisit. Une ligne déjà présente (même étudiant, même type,
 * même intitulé, même année) est écartée et signalée, pour qu'un fichier importé deux fois ne
 * double rien.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credits_importer {
    /**
     * Colonnes reconnues : clé => [intitulés reconnus, obligatoire].
     *
     * @return array
     */
    public static function columns(): array {
        return [
            'student' => [['Étudiant', 'Email', 'Courriel', 'Adresse de courriel', 'Numéro d\'identification', 'Student'], true],
            'type' => [['Type', 'Code type', 'Type code'], true],
            'activity' => [['Code EP', 'EP du catalogue', 'Activity code'], false],
            'name' => [['Intitulé', 'Name', 'Title'], false],
            'studyyear' => [['Année', 'Année d\'étude', 'Study year'], false],
            'claimedects' => [['ECTS demandés', 'ECTS', 'Claimed ECTS'], true],
            'retainedects' => [['ECTS retenus', 'Retained ECTS'], false],
            'grade' => [['Note', 'Grade'], false],
            'status' => [['Statut', 'Status'], false],
            'date' => [['Date de décision', 'Date', 'Decision date'], false],
            'validator' => [['Validé par', 'Validateur', 'Decided by'], false],
            'comment' => [['Commentaire', 'Comment'], false],
            'description' => [['Description'], false],
        ];
    }

    /**
     * Statut reconnu, ou null.
     *
     * @param string $value
     * @return int|null
     */
    protected static function status($value): ?int {
        $value = csv_reader::normalise($value);
        if ($value === '') {
            return EP_STATUS_VALIDATED;
        }
        $map = ['valide' => EP_STATUS_VALIDATED, 'validated' => EP_STATUS_VALIDATED, 'acquis' => EP_STATUS_VALIDATED,
            'enattente' => EP_STATUS_PENDING, 'pending' => EP_STATUS_PENDING, 'attente' => EP_STATUS_PENDING,
            'refuse' => EP_STATUS_REJECTED, 'rejected' => EP_STATUS_REJECTED, 'nonvalide' => EP_STATUS_REJECTED,
            'annule' => EP_STATUS_CANCELLED, 'cancelled' => EP_STATUS_CANCELLED, 'retire' => EP_STATUS_CANCELLED];
        return $map[$value] ?? null;
    }

    /**
     * Lit le fichier et prépare chaque ligne, sans rien écrire.
     *
     * @param \stdClass $ep
     * @param \context $context
     * @param string $content
     * @return \stdClass {rows, errors, warnings}
     */
    public static function read(\stdClass $ep, \context $context, $content): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $columns = self::columns();
        $read = csv_reader::read($content, array_map(fn($d) => $d[0], $columns));
        $result = (object) ['rows' => [], 'errors' => [], 'warnings' => []];
        foreach ($columns as $key => [$names, $required]) {
            if ($required && !isset($read->columns[$key])) {
                $result->errors[] = get_string('importcolumnmissing', 'mod_ep', $names[0]);
            }
        }
        if ($result->errors) {
            return $result;
        }

        // Étudiants : ceux du cours seulement, un crédit ne se porte pas au dossier d'un inconnu.
        $byemail = [];
        $byidnumber = [];
        foreach (ep_get_enrolled_students($context) as $student) {
            $byemail[\core_text::strtolower($student->email)] = (int) $student->id;
            if (trim((string) $student->idnumber) !== '') {
                $byidnumber[trim($student->idnumber)] = (int) $student->id;
            }
        }
        // Validateurs : parmi tous les comptes du site.
        $validators = [];
        foreach ($read->rows as $cells) {
            $validators[] = csv_reader::value($read, $cells, 'validator');
        }
        $validators = array_values(array_unique(array_filter($validators)));
        $validatorids = [];
        foreach (array_chunk($validators, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal(array_map('core_text::strtolower', $chunk), SQL_PARAMS_NAMED, 'v');
            foreach ($DB->get_records_select('user', "LOWER(email) $insql AND deleted = 0", $params, '', 'id, email') as $user) {
                $validatorids[\core_text::strtolower($user->email)] = (int) $user->id;
            }
        }
        $types = [];
        foreach (ep_get_types($ep->id) as $type) {
            $types[csv_reader::normalise($type->code)] = $type;
        }
        $activities = [];
        foreach (ep_get_activities($ep->id) as $activity) {
            if ($activity->code !== '') {
                $activities[$activity->code] = $activity;
            }
        }
        // Crédits déjà présents, pour écarter les doublons : clé étudiant|type|intitulé|année.
        $existing = [];
        $rs = $DB->get_recordset_select(
            'ep_credit',
            'epid = :epid AND status <> :cancelled',
            ['epid' => $ep->id, 'cancelled' => EP_STATUS_CANCELLED],
            '',
            'id, userid, typeid, name, studyyear'
        );
        foreach ($rs as $credit) {
            $existing[$credit->userid . '|' . $credit->typeid . '|' . \core_text::strtolower(trim($credit->name)) . '|'
                . $credit->studyyear] = true;
        }
        $rs->close();

        foreach ($read->rows as $lineno => $cells) {
            $value = fn($column) => csv_reader::value($read, $cells, $column);
            $errors = [];
            $userid = csv_reader::user($value('student'), $byemail, $byidnumber);
            if (!$userid) {
                $errors[] = get_string('importunknownstudent', 'mod_ep', s($value('student')));
            }
            $type = $types[csv_reader::normalise($value('type'))] ?? null;
            if (!$type || ep_type_is_stage($type)) {
                $errors[] = get_string('importunknowntype', 'mod_ep', s($value('type')));
            }
            $activity = null;
            if ($value('activity') !== '') {
                $activity = $activities[$value('activity')] ?? null;
                if (!$activity) {
                    $errors[] = get_string('importunknownactivity', 'mod_ep', s($value('activity')));
                }
            }
            $name = $value('name') !== '' ? $value('name') : ($activity->name ?? '');
            if ($name === '') {
                $errors[] = get_string('importvaluemissing', 'mod_ep', $columns['name'][0][0]);
            }
            $claimed = csv_reader::number($value('claimedects'));
            if ($claimed === null || $claimed < 0) {
                $errors[] = get_string('importbadnumber', 'mod_ep', (object) ['column' => 'ECTS',
                    'value' => s($value('claimedects'))]);
            }
            $status = self::status($value('status'));
            if ($status === null) {
                $errors[] = get_string('importbadvalue', 'mod_ep', (object) ['column' => $columns['status'][0][0],
                    'value' => s($value('status'))]);
            }
            $retained = $value('retainedects') === '' ? $claimed : csv_reader::number($value('retainedects'));
            $grade = $value('grade') === '' ? null : csv_reader::number($value('grade'));
            $year = $value('studyyear') === '' ? (int) $ep->currentstudyyear : (int) csv_reader::number($value('studyyear'));
            $date = csv_reader::date($value('date'));
            if ($retained === null || ($value('grade') !== '' && $grade === null) || $date === null || $year < 0 || $year > 6) {
                $errors[] = get_string('importbadvalue', 'mod_ep', (object) ['column' => '', 'value' => s(implode(' ', [
                    $value('retainedects'), $value('grade'), $value('date'), $value('studyyear')]))]);
            }
            if ($errors) {
                foreach ($errors as $error) {
                    $result->errors[] = get_string('importline', 'mod_ep', (object) ['line' => $lineno, 'error' => $error]);
                }
                continue;
            }
            $key = $userid . '|' . $type->id . '|' . \core_text::strtolower(trim($name)) . '|' . $year;
            if (isset($existing[$key]) && $status !== EP_STATUS_CANCELLED) {
                $result->warnings[] = get_string('importduplicatecredit', 'mod_ep', (object) ['line' => $lineno,
                    'name' => s($name)]);
                continue;
            }
            $existing[$key] = true;
            $validator = $value('validator');
            $result->rows[] = (object) [
                'line' => $lineno,
                'userid' => $userid,
                'typeid' => (int) $type->id,
                'activityid' => $activity ? (int) $activity->id : null,
                'name' => \core_text::substr($name, 0, 255),
                'description' => $value('description'),
                'studyyear' => $year,
                'claimedects' => $claimed,
                'retainedects' => $status === EP_STATUS_VALIDATED ? $retained : 0,
                'grade' => $grade,
                'status' => $status,
                'timedecided' => $date ?: time(),
                'validatedby' => $validator !== '' ? ($validatorids[\core_text::strtolower($validator)] ?? null) : null,
                'comment' => $value('comment'),
            ];
            if ($validator !== '' && empty($validatorids[\core_text::strtolower($validator)])) {
                $result->warnings[] = get_string('importunknownuser', 'mod_ep', (object) ['line' => $lineno,
                    'value' => s($validator)]);
            }
        }
        return $result;
    }

    /**
     * Enregistre les crédits préparés par read(), sous le verrou de l'activité et dans une
     * transaction ; les doublons apparus depuis la prévisualisation (import confirmé deux fois)
     * sont relus et écartés.
     *
     * @param \stdClass $ep
     * @param array $rows
     * @param int $userid Auteur de l'import.
     * @return \stdClass {created, skipped}
     */
    public static function apply(\stdClass $ep, array $rows, $userid): \stdClass {
        global $DB;

        $result = (object) ['created' => 0, 'skipped' => 0];
        $types = ep_get_types($ep->id);
        $lock = ep_get_activity_lock($ep->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($rows as $row) {
                $row = (object) $row;
                $duplicate = (int) $row->status !== EP_STATUS_CANCELLED && $DB->record_exists_select(
                    'ep_credit',
                    'epid = :epid AND userid = :userid AND typeid = :typeid AND studyyear = :year AND status <> :cancelled AND '
                        . $DB->sql_equal('name', ':name', false),
                    ['epid' => $ep->id, 'userid' => $row->userid, 'typeid' => $row->typeid, 'year' => $row->studyyear,
                        'cancelled' => EP_STATUS_CANCELLED, 'name' => $row->name]
                );
                if ($duplicate || !isset($types[$row->typeid])) {
                    $result->skipped++;
                    continue;
                }
                $decided = (int) $row->status !== EP_STATUS_PENDING;
                $flow = ep_flow_steps($types[$row->typeid]->validationflow);
                $credit = (object) [
                    'epid' => $ep->id,
                    'userid' => $row->userid,
                    'typeid' => $row->typeid,
                    'activityid' => $row->activityid,
                    'studyyear' => $row->studyyear,
                    'name' => $row->name,
                    'description' => (string) $row->description,
                    'claimedects' => $row->claimedects,
                    'retainedects' => $row->retainedects,
                    'status' => (int) $row->status,
                    'step' => 0,
                    'pendingrole' => $decided ? '' : ($flow[0] ?? EP_ROLE_DEVE),
                    'grade' => $row->grade,
                    'source' => EP_SOURCE_DEVE,
                    'sourceref' => 0,
                    'validatedby' => $decided ? ($row->validatedby ?: $userid) : null,
                    'addedby' => $userid,
                    'validatetime' => $decided ? $row->timedecided : null,
                    'validatorcomment' => $decided ? (string) $row->comment : null,
                    'timecreated' => $decided ? $row->timedecided : time(),
                    'timemodified' => time(),
                ];
                $credit->id = $DB->insert_record('ep_credit', $credit);
                if ($decided) {
                    $decisions = [EP_STATUS_VALIDATED => 'validated', EP_STATUS_REJECTED => 'rejected',
                        EP_STATUS_CANCELLED => 'cancelled'];
                    $DB->insert_record('ep_credit_decision', (object) [
                        'creditid' => $credit->id, 'step' => 0, 'role' => EP_ROLE_DEVE,
                        'decision' => $decisions[(int) $row->status], 'userid' => $credit->validatedby,
                        'grade' => $row->grade, 'retainedects' => $row->retainedects, 'comment' => (string) $row->comment,
                        'timecreated' => $row->timedecided,
                    ]);
                }
                ep_trigger_credit_event(\mod_ep\event\credit_created::class, $credit, ['origin' => 'deve']);
                $result->created++;
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        ep_log_import($ep, 'credits', $userid, count($rows), $result);
        return $result;
    }
}
