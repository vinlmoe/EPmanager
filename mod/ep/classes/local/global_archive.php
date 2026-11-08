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
 * Export global d'une activité et sa restauration.
 *
 * L'export est une archive ZIP qui contient tout le module :
 * - classeur.xlsx : un classeur lisible par la DEVE (bilan par étudiant, EP portés au crédit,
 *   catalogue, éléments du supplément, décisions d'année, suppléments émis) ;
 * - parametrage.json : le paramétrage (voir ep_export_settings()), catalogue et syllabus compris ;
 * - donnees.json : les données des étudiants, désignés par leur adresse et leur numéro
 *   d'identification (crédits et leurs décisions, éléments du supplément, décisions d'année,
 *   suppléments émis), avec l'index des fichiers ;
 * - fichiers/ : justificatifs, preuves et suppléments émis.
 *
 * La restauration relit les deux fichiers JSON, et non le classeur : un classeur retouché à la
 * main ne doit pas pouvoir corrompre une activité. Elle complète l'activité cible sans rien
 * supprimer, et écarte ce qui y figure déjà, si bien qu'une archive restaurée deux fois ne double
 * rien.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class global_archive {
    /** @var int Version du format de donnees.json. */
    const FORMAT = 1;

    /** @var int Nombre maximal de fichiers dans une archive restaurée. */
    const MAX_ENTRIES = 200000;

    /** @var int Taille décompressée maximale d'une archive restaurée (4 Go). */
    const MAX_UNCOMPRESSED = 4294967296;

    /**
     * Construit l'archive d'une activité.
     *
     * L'export d'une grande promotion compte des dizaines de milliers de crédits : il est écrit en
     * un seul passage sur les crédits, lus par paquets, chaque ligne allant aussitôt dans
     * donnees.json et dans la feuille des crédits du classeur. Seuls les comptes cités, les
     * éléments du supplément, les décisions d'année et les suppléments émis, bien moins nombreux,
     * sont tenus en mémoire.
     *
     * @param \stdClass $ep
     * @param \context_module $context
     * @return string Chemin de l'archive, dans un dossier temporaire de la requête.
     */
    public static function export(\stdClass $ep, \context_module $context): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $dir = make_request_directory();
        $fs = get_file_storage();
        $types = ep_get_types($ep->id);
        $activities = ep_get_activities($ep->id);
        $categories = ep_get_supplement_categories($ep->id);

        // Fichiers lus par zone en une requête chacune, puis rangés par élément.
        $areafiles = [];
        foreach ([EP_EVIDENCE_FILEAREA, EP_SUPPLEMENT_EVIDENCE_FILEAREA, EP_SUPPLEMENT_ISSUED_FILEAREA] as $area) {
            foreach ($fs->get_area_files($context->id, 'mod_ep', $area, false, 'itemid, filename', false) as $file) {
                $areafiles[$area][(int) $file->get_itemid()][] = $file;
            }
        }
        $files = [];
        $addfiles = function ($area, $itemid) use ($areafiles, &$files) {
            $paths = [];
            foreach ($areafiles[$area][(int) $itemid] ?? [] as $file) {
                $path = 'fichiers/' . $area . '/' . $itemid . '/' . clean_filename($file->get_filename());
                $files[$path] = $file;
                $paths[] = $path;
            }
            return $paths;
        };

        // Comptes cités, lus d'avance en une requête par table : les noms servent au classeur,
        // l'adresse et le numéro à retrouver les personnes à la restauration.
        $sources = [
            'ep_credit' => ['userid', 'validatedby', 'addedby'],
            'ep_supplement_item' => ['userid', 'validatedby'],
            'ep_year_validation' => ['userid', 'decidedby'],
            'ep_supplement_issue' => ['userid', 'issuedby'],
        ];
        $userids = [];
        foreach ($sources as $table => $fields) {
            foreach ($fields as $field) {
                $sql = "SELECT DISTINCT $field FROM {{$table}} WHERE epid = :epid AND $field IS NOT NULL";
                foreach ($DB->get_fieldset_sql($sql, ['epid' => $ep->id]) as $id) {
                    $userids[(int) $id] = true;
                }
            }
        }
        $sql = 'SELECT DISTINCT d.userid FROM {ep_credit_decision} d JOIN {ep_credit} c ON c.id = d.creditid
                 WHERE c.epid = :epid AND d.userid IS NOT NULL';
        foreach ($DB->get_fieldset_sql($sql, ['epid' => $ep->id]) as $id) {
            $userids[(int) $id] = true;
        }
        $users = [];
        foreach (array_chunk(array_keys($userids), 500) as $chunk) {
            $users += $DB->get_records_list('user', 'id', $chunk, '', 'id, email, idnumber, firstname, lastname');
        }
        $userdata = [];
        foreach ($users as $user) {
            $userdata[(int) $user->id] = ['email' => $user->email, 'idnumber' => (string) $user->idnumber,
                'firstname' => $user->firstname, 'lastname' => $user->lastname];
        }

        $items = [];
        foreach ($DB->get_records('ep_supplement_item', ['epid' => $ep->id], 'userid, id') as $item) {
            $row = (array) $item;
            unset($row['id'], $row['epid'], $row['categoryid']);
            $row['category'] = $categories[$item->categoryid]->name ?? '';
            $row['files'] = $addfiles(EP_SUPPLEMENT_EVIDENCE_FILEAREA, $item->id);
            $items[] = $row;
        }
        $years = [];
        foreach ($DB->get_records('ep_year_validation', ['epid' => $ep->id], 'userid, studyyear') as $year) {
            $row = (array) $year;
            unset($row['id'], $row['epid']);
            $years[] = $row;
        }
        $issues = [];
        $issuerecords = $DB->get_records('ep_supplement_issue', ['epid' => $ep->id], 'id');
        foreach ($issuerecords as $issue) {
            $row = (array) $issue;
            unset($row['id'], $row['epid'], $row['replacedby'], $row['seal']);
            $row['replacedbynumber'] = $issue->replacedby ? ($issuerecords[$issue->replacedby]->number ?? '') : '';
            $row['files'] = $addfiles(EP_SUPPLEMENT_ISSUED_FILEAREA, $issue->id);
            $issues[] = $row;
        }

        file_put_contents($dir . '/parametrage.json', json_encode(
            ep_export_settings($ep, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));

        // Classeur et données : feuilles d'en-tête, puis les crédits en un passage.
        $workbook = new workbook_writer($dir . '/classeur.xlsx');
        $name = fn($userid) => isset($users[$userid]) ? $users[$userid]->lastname . ' ' . $users[$userid]->firstname : '';
        $email = fn($userid) => $users[$userid]->email ?? '';
        $date = fn($time) => $time ? userdate($time, '%d/%m/%Y') : '';

        $rows = [[get_string('lastname'), get_string('firstname'), get_string('email'), get_string('idnumber'),
            get_string('totalretainedshort', 'mod_ep'), get_string('pendingects', 'mod_ep'), get_string('status', 'mod_ep')]];
        $workbook->sheet(get_string('pilotage', 'mod_ep'), $rows);
        // Bilans par paquets de 500 étudiants : le calcul charge tous les crédits des étudiants
        // demandés, ce qu'une promotion entière rendrait coûteux en mémoire.
        foreach (array_chunk(ep_get_enrolled_students($context), 500, true) as $students) {
            foreach (ep_build_pilotage_rows($ep, $students) as $row) {
                $workbook->row([$row->user->lastname, $row->user->firstname, $row->user->email, (string) $row->user->idnumber,
                    $row->progress->totalretained, $row->progress->totalpending,
                    get_string($row->progress->complete ? 'objectivedone' : 'objectivetodo', 'mod_ep')]);
            }
        }
        $rows = null;

        $workbook->sheet(get_string('exportcredits', 'mod_ep'), [[get_string('student', 'mod_ep'), get_string('email'),
            get_string('type', 'mod_ep'), get_string('activitycode', 'mod_ep'), get_string('creditname', 'mod_ep'),
            get_string('studyyear', 'mod_ep'), get_string('claimedects', 'mod_ep'), get_string('retainedects', 'mod_ep'),
            get_string('grade', 'mod_ep'), get_string('status', 'mod_ep'), get_string('decidedby', 'mod_ep'),
            get_string('decidedon', 'mod_ep'), get_string('validatorcomment', 'mod_ep')]]);

        $json = fopen($dir . '/donnees.json', 'w');
        fwrite($json, '{"format":' . self::FORMAT . ',"exported":' . time() . ',"credits":[');
        $firstcredit = true;
        $flush = function (array $credits) use (
            $DB,
            $types,
            $activities,
            $addfiles,
            $json,
            &$firstcredit,
            $workbook,
            $name,
            $email,
            $date
) {
            $decisions = [];
            [$insql, $params] = $DB->get_in_or_equal(array_keys($credits));
            foreach ($DB->get_records_select('ep_credit_decision', "creditid $insql", $params, 'timecreated, id') as $decision) {
                $entry = (array) $decision;
                unset($entry['id'], $entry['creditid']);
                $decisions[$decision->creditid][] = $entry;
            }
            foreach ($credits as $credit) {
                $row = (array) $credit;
                unset($row['id'], $row['epid'], $row['typeid'], $row['activityid']);
                $row['typecode'] = $types[$credit->typeid]->code ?? '';
                $row['activitycode'] = $credit->activityid ? ($activities[$credit->activityid]->code ?? '') : '';
                $row['activityname'] = $credit->activityid ? ($activities[$credit->activityid]->name ?? '') : '';
                $row['decisions'] = $decisions[$credit->id] ?? [];
                $row['files'] = $addfiles(EP_EVIDENCE_FILEAREA, $credit->id);
                fwrite($json, ($firstcredit ? '' : ',') . json_encode($row, JSON_UNESCAPED_UNICODE));
                $firstcredit = false;
                $workbook->row([$name($credit->userid), $email($credit->userid), $row['typecode'], $row['activitycode'],
                    $credit->name, (int) $credit->studyyear, (float) $credit->claimedects, (float) $credit->retainedects,
                    $credit->grade === null ? '' : (float) $credit->grade, ep_status_label($credit->status),
                    $name($credit->validatedby), $date($credit->validatetime), (string) $credit->validatorcomment]);
            }
        };
        $buffer = [];
        $rs = $DB->get_recordset('ep_credit', ['epid' => $ep->id], 'userid, id');
        foreach ($rs as $credit) {
            $buffer[$credit->id] = $credit;
            if (count($buffer) >= 500) {
                $flush($buffer);
                $buffer = [];
            }
        }
        $rs->close();
        if ($buffer) {
            $flush($buffer);
        }
        fwrite($json, '],"items":' . json_encode($items, JSON_UNESCAPED_UNICODE)
            . ',"years":' . json_encode($years, JSON_UNESCAPED_UNICODE)
            . ',"issues":' . json_encode($issues, JSON_UNESCAPED_UNICODE)
            . ',"users":' . json_encode((object) $userdata, JSON_UNESCAPED_UNICODE) . '}');
        fclose($json);

        $rows = [[get_string('activitycode', 'mod_ep'), get_string('catalogactivity', 'mod_ep'), get_string('type', 'mod_ep'),
            'ECTS', get_string('activitystate', 'mod_ep'), get_string('places', 'mod_ep'),
            get_string('activityteachers', 'mod_ep')]];
        $teachers = ep_get_activities_teachers($ep->id);
        foreach ($activities as $activity) {
            $rows[] = [$activity->code, $activity->name, $types[$activity->typeid]->name ?? '', (float) $activity->ects,
                ep_activity_state_options()[$activity->state] ?? $activity->state, (int) $activity->capacity,
                implode(', ', array_map('fullname', $teachers[$activity->id] ?? []))];
        }
        $workbook->sheet(get_string('catalog', 'mod_ep'), $rows);

        $rows = [[get_string('student', 'mod_ep'), get_string('supplementcategory', 'mod_ep'),
            get_string('supplementitemtitle', 'mod_ep'), get_string('status', 'mod_ep'), get_string('decidedon', 'mod_ep')]];
        foreach ($items as $item) {
            $rows[] = [$name($item['userid']), $item['category'], $item['title'], ep_status_label($item['status']),
                $date($item['validatetime'])];
        }
        $workbook->sheet(get_string('supplementitems', 'mod_ep'), $rows);

        $rows = [[get_string('student', 'mod_ep'), get_string('studyyear', 'mod_ep'), get_string('decision', 'mod_ep'),
            get_string('retainedects', 'mod_ep'), get_string('decidedon', 'mod_ep'), get_string('validatorcomment', 'mod_ep')]];
        foreach ($years as $year) {
            $rows[] = [$name($year['userid']), (int) $year['studyyear'],
                get_string($year['decision'] ? 'yearvalidated' : 'yearnotvalidated', 'mod_ep'), (float) $year['retainedects'],
                $date($year['timedecided']), (string) $year['comment']];
        }
        $workbook->sheet(get_string('yearvalidation', 'mod_ep'), $rows);

        $rows = [[get_string('student', 'mod_ep'), get_string('supplementnumberheader', 'mod_ep'),
            get_string('supplementlanguage', 'mod_ep'), get_string('decidedon', 'mod_ep'), get_string('status', 'mod_ep')]];
        foreach ($issues as $issue) {
            $rows[] = [$name($issue['userid']), $issue['number'], $issue['lang'], $date($issue['timeissued']),
                get_string($issue['replacedbynumber'] !== '' ? 'supplementreplaced' : 'supplementcurrent', 'mod_ep')];
        }
        $workbook->sheet(get_string('supplementissues', 'mod_ep'), $rows);
        $workbook->close();

        $entries = [
            'parametrage.json' => $dir . '/parametrage.json',
            'donnees.json' => $dir . '/donnees.json',
            'classeur.xlsx' => $dir . '/classeur.xlsx',
        ];
        foreach ($files as $path => $file) {
            $entries[$path] = $file;
        }
        $zippath = $dir . '/export.zip';
        get_file_packer('application/zip')->archive_to_pathname($entries, $zippath);
        return $zippath;
    }

    /**
     * Ouvre une archive d'export : vérifie son format et en tire de quoi la prévisualiser.
     *
     * @param string $zippath
     * @return \stdClass {dir: dossier d'extraction, settings: array, data: array, counts: array}
     */
    public static function open(string $zippath): \stdClass {
        // Contrôle avant extraction : une archive forgée (bombe ZIP) ne doit pas remplir le disque.
        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            throw new \moodle_exception('errorarchiveinvalid', 'mod_ep');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $total += (int) ($stat['size'] ?? 0);
        }
        $entries = $zip->numFiles;
        $zip->close();
        if ($entries > self::MAX_ENTRIES || $total > self::MAX_UNCOMPRESSED) {
            throw new \moodle_exception('errorarchivetoolarge', 'mod_ep');
        }
        $dir = make_request_directory();
        $extracted = get_file_packer('application/zip')->extract_to_pathname($zippath, $dir);
        if (!$extracted || !is_readable($dir . '/parametrage.json') || !is_readable($dir . '/donnees.json')) {
            throw new \moodle_exception('errorarchiveinvalid', 'mod_ep');
        }
        $settings = json_decode(file_get_contents($dir . '/parametrage.json'), true);
        $data = json_decode(file_get_contents($dir . '/donnees.json'), true);
        if (!is_array($settings) || !is_array($data) || (int) ($data['format'] ?? 0) !== self::FORMAT) {
            throw new \moodle_exception('errorarchiveinvalid', 'mod_ep');
        }
        return (object) [
            'dir' => $dir,
            'settings' => $settings,
            'data' => $data,
            'counts' => [
                'types' => count($settings['types'] ?? []),
                'activities' => count($settings['activities'] ?? []),
                'credits' => count($data['credits'] ?? []),
                'items' => count($data['items'] ?? []),
                'years' => count($data['years'] ?? []),
                'issues' => count($data['issues'] ?? []),
            ],
        ];
    }

    /**
     * Restaure une archive ouverte par open() dans une activité : le paramétrage (parties
     * demandées), puis les données des étudiants inscrits au cours, retrouvés par leur adresse ou
     * leur numéro d'identification. Ce qui figure déjà dans l'activité est écarté.
     *
     * @param \stdClass $ep
     * @param \context_module $context
     * @param \stdClass $archive Voir open().
     * @param array $parts Parties du paramétrage (voir ep_import_settings()), plus 'data' pour les
     *                     données des étudiants.
     * @param int $userid Auteur de la restauration.
     * @return \stdClass Compte rendu : settings (celui de ep_import_settings()), credits, items, years,
     *                   issues, skipped, warnings.
     */
    public static function restore(\stdClass $ep, \context_module $context, \stdClass $archive, array $parts, $userid): \stdClass {
        global $DB;

        $report = (object) ['settings' => null, 'credits' => 0, 'items' => 0, 'years' => 0, 'issues' => 0, 'skipped' => 0,
            'warnings' => []];
        $settingsparts = array_values(array_diff($parts, ['data']));
        if ($settingsparts) {
            $report->settings = ep_import_settings($ep, $archive->settings, $settingsparts, $userid);
        }
        if (!in_array('data', $parts, true)) {
            return $report;
        }

        $data = $archive->data;
        $byemail = [];
        $byidnumber = [];
        foreach (ep_get_enrolled_students($context) as $student) {
            $byemail[\core_text::strtolower($student->email)] = (int) $student->id;
            if (trim((string) $student->idnumber) !== '') {
                $byidnumber[trim($student->idnumber)] = (int) $student->id;
            }
        }
        $sitecache = [];
        $mapstudent = function ($oldid) use ($data, $byemail, $byidnumber) {
            $user = $data['users'][$oldid] ?? null;
            if (!$user) {
                return 0;
            }
            return $byidnumber[$user['idnumber']] ?? $byemail[\core_text::strtolower($user['email'])] ?? 0;
        };
        $mapstaff = function ($oldid) use ($data, &$sitecache, $DB) {
            $user = $data['users'][$oldid] ?? null;
            if (!$user || $user['email'] === '') {
                return null;
            }
            $email = \core_text::strtolower($user['email']);
            if (!array_key_exists($email, $sitecache)) {
                $found = $DB->get_records_select(
                    'user',
                    'LOWER(email) = :email AND deleted = 0',
                    ['email' => $email],
                    '',
                    'id',
                    0,
                    2
                );
                $sitecache[$email] = count($found) === 1 ? (int) reset($found)->id : null;
            }
            return $sitecache[$email];
        };
        $fs = get_file_storage();
        $copyfiles = function (array $paths, $area, $itemid) use ($fs, $context, $archive) {
            foreach ($paths as $path) {
                $source = $archive->dir . '/' . $path;
                if (strpos($path, '..') !== false || !is_readable($source)) {
                    continue;
                }
                $record = ['contextid' => $context->id, 'component' => 'mod_ep', 'filearea' => $area, 'itemid' => $itemid,
                    'filepath' => '/', 'filename' => clean_filename(basename($path))];
                if (!$fs->file_exists($context->id, 'mod_ep', $area, $itemid, '/', $record['filename'])) {
                    $fs->create_file_from_pathname($record, $source);
                }
            }
        };

        $lock = ep_get_activity_lock($ep->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            $types = [];
            foreach (ep_get_types($ep->id) as $type) {
                $types[$type->code] = (int) $type->id;
            }
            $bycode = [];
            $byname = [];
            foreach (ep_get_activities($ep->id) as $activity) {
                if ($activity->code !== '') {
                    $bycode[$activity->code] = (int) $activity->id;
                }
                $byname[\core_text::strtolower($activity->name)] = (int) $activity->id;
            }
            $missingusers = [];

            // Crédits déjà présents, lus une fois : un contrôle par ligne dans la table qui grossit
            // à chaque insertion ralentirait la restauration d'une grande archive à mesure.
            $creditkey = fn($userid, $typeid, $year, $created, $name) => $userid . '|' . $typeid . '|' . (int) $year
                . '|' . (int) $created . '|' . \core_text::strtolower(trim((string) $name));
            $existing = [];
            $rs = $DB->get_recordset('ep_credit', ['epid' => $ep->id], '', 'id, userid, typeid, studyyear, timecreated, name');
            foreach ($rs as $credit) {
                $existing[$creditkey(
                    $credit->userid,
                    $credit->typeid,
                    $credit->studyyear,
                    $credit->timecreated,
                    $credit->name
                )] = true;
            }
            $rs->close();
            $pendingdecisions = [];
            foreach ($data['credits'] ?? [] as $row) {
                $studentid = $mapstudent($row['userid']);
                $typeid = $types[$row['typecode']] ?? 0;
                if (!$studentid || !$typeid) {
                    $report->skipped++;
                    if (!$studentid) {
                        $missingusers[$row['userid']] = true;
                    }
                    continue;
                }
                $key = $creditkey($studentid, $typeid, $row['studyyear'], $row['timecreated'], $row['name']);
                if (isset($existing[$key])) {
                    $report->skipped++;
                    continue;
                }
                $existing[$key] = true;
                $credit = (object) array_diff_key($row, array_flip(['typecode', 'activitycode', 'activityname', 'decisions',
                    'files']));
                $credit->epid = $ep->id;
                $credit->userid = $studentid;
                $credit->typeid = $typeid;
                $credit->activityid = ($row['activitycode'] !== '' ? ($bycode[$row['activitycode']] ?? 0) : 0)
                    ?: ($row['activityname'] !== '' ? ($byname[\core_text::strtolower($row['activityname'])] ?? 0) : 0) ?: null;
                $credit->validatedby = $credit->validatedby ? $mapstaff($credit->validatedby) : null;
                $credit->addedby = $credit->addedby ? $mapstaff($credit->addedby) : null;
                // Valeurs relues d'un fichier : ramenées à ce que le module sait écrire.
                $statuses = [EP_STATUS_CANCELLED, EP_STATUS_REJECTED, EP_STATUS_PENDING, EP_STATUS_VALIDATED];
                $credit->status = in_array((int) $credit->status, $statuses, true) ? (int) $credit->status : EP_STATUS_PENDING;
                $credit->source = in_array($credit->source, [EP_SOURCE_STUDENT, EP_SOURCE_STAGE, EP_SOURCE_DEVE], true)
                    ? $credit->source : EP_SOURCE_DEVE;
                $credit->studyyear = max(0, min(6, (int) $credit->studyyear));
                $credit->step = max(0, (int) ($credit->step ?? 0));
                $credit->claimedects = max(0, round((float) $credit->claimedects, 2));
                $credit->retainedects = $credit->status === EP_STATUS_VALIDATED
                    ? max(0, round((float) $credit->retainedects, 2)) : 0;
                $credit->grade = isset($credit->grade) && $credit->grade !== null ? round((float) $credit->grade, 2) : null;
                $credit->pendingrole = $credit->status === EP_STATUS_PENDING
                    && in_array($credit->pendingrole ?? '', [EP_ROLE_OWNER, EP_ROLE_REFERENT, EP_ROLE_DEVE], true)
                    ? $credit->pendingrole : ($credit->status === EP_STATUS_PENDING ? EP_ROLE_DEVE : '');
                // Un crédit automatique ne garde pas son lien au stage d'origine : la saisie de
                // mod_stage de l'autre activité n'est pas celle-ci. Il devient une saisie DEVE.
                if ($credit->source === EP_SOURCE_STAGE) {
                    $credit->source = EP_SOURCE_DEVE;
                    $credit->sourceref = 0;
                }
                $credit->id = $DB->insert_record('ep_credit', $credit);
                foreach ($row['decisions'] as $decision) {
                    $decision = (object) $decision;
                    $decision->creditid = $credit->id;
                    $decision->userid = $decision->userid ? $mapstaff($decision->userid) : null;
                    $pendingdecisions[] = $decision;
                }
                // Décisions insérées par lots.
                if (count($pendingdecisions) >= 1000) {
                    $DB->insert_records('ep_credit_decision', $pendingdecisions);
                    $pendingdecisions = [];
                }
                $copyfiles($row['files'], EP_EVIDENCE_FILEAREA, $credit->id);
                $report->credits++;
            }
            if ($pendingdecisions) {
                $DB->insert_records('ep_credit_decision', $pendingdecisions);
            }

            $categories = [];
            foreach (ep_get_supplement_categories($ep->id) as $category) {
                $categories[\core_text::strtolower($category->name)] = (int) $category->id;
            }
            $existingitems = [];
            foreach ($DB->get_records('ep_supplement_item', ['epid' => $ep->id], '', 'id, userid, timecreated, title') as $item) {
                $existingitems[$item->userid . '|' . $item->timecreated . '|' . $item->title] = true;
            }
            foreach ($data['items'] ?? [] as $row) {
                $studentid = $mapstudent($row['userid']);
                $key = \core_text::strtolower($row['category']);
                if (!$studentid) {
                    $report->skipped++;
                    continue;
                }
                if (!isset($categories[$key])) {
                    $categories[$key] = ep_save_supplement_category($ep->id, (object) ['name' => $row['category'], 'enabled' => 1]);
                }
                $itemkey = $studentid . '|' . $row['timecreated'] . '|' . $row['title'];
                if (isset($existingitems[$itemkey])) {
                    $report->skipped++;
                    continue;
                }
                $existingitems[$itemkey] = true;
                $item = (object) array_diff_key($row, array_flip(['category', 'files']));
                $item->epid = $ep->id;
                $item->userid = $studentid;
                $item->categoryid = $categories[$key];
                $item->validatedby = $item->validatedby ? $mapstaff($item->validatedby) : null;
                $itemstatuses = [EP_STATUS_CANCELLED, EP_STATUS_REJECTED, EP_STATUS_PENDING, EP_STATUS_VALIDATED];
                $item->status = in_array((int) $item->status, $itemstatuses, true) ? (int) $item->status : EP_STATUS_PENDING;
                $item->step = max(0, (int) ($item->step ?? 0));
                $item->pendingrole = $item->status === EP_STATUS_PENDING
                    && in_array($item->pendingrole ?? '', [EP_ROLE_REFERENT, EP_ROLE_DEVE], true)
                    ? $item->pendingrole : ($item->status === EP_STATUS_PENDING ? EP_ROLE_DEVE : '');
                $item->id = $DB->insert_record('ep_supplement_item', $item);
                $copyfiles($row['files'], EP_SUPPLEMENT_EVIDENCE_FILEAREA, $item->id);
                $report->items++;
            }

            $existingyears = [];
            foreach ($DB->get_records('ep_year_validation', ['epid' => $ep->id], '', 'id, userid, studyyear') as $year) {
                $existingyears[$year->userid . '|' . $year->studyyear] = true;
            }
            foreach ($data['years'] ?? [] as $row) {
                $studentid = $mapstudent($row['userid']);
                if (!$studentid) {
                    $report->skipped++;
                    continue;
                }
                if (isset($existingyears[$studentid . '|' . $row['studyyear']])) {
                    $report->skipped++;
                    continue;
                }
                $existingyears[$studentid . '|' . $row['studyyear']] = true;
                $year = (object) $row;
                $year->epid = $ep->id;
                $year->userid = $studentid;
                $year->decidedby = $year->decidedby ? $mapstaff($year->decidedby) : null;
                $year->studyyear = max(1, min(6, (int) $year->studyyear));
                $year->decision = empty($year->decision) ? 0 : 1;
                $year->retainedects = max(0, round((float) $year->retainedects, 2));
                $DB->insert_record('ep_year_validation', $year);
                $report->years++;
            }

            $newissues = [];
            foreach ($data['issues'] ?? [] as $row) {
                $studentid = $mapstudent($row['userid']);
                if (
                    !$studentid || $DB->record_exists('ep_supplement_issue', ['verifycode' => $row['verifycode'],
                        'epid' => $ep->id])
                ) {
                    $report->skipped++;
                    continue;
                }
                $issue = (object) array_diff_key($row, array_flip(['files', 'replacedbynumber']));
                $issue->epid = $ep->id;
                $issue->userid = $studentid;
                $issue->issuedby = $issue->issuedby ? $mapstaff($issue->issuedby) : null;
                $issue->replacedby = null;
                // Copie d'un document émis ailleurs : conservée, non reconnue par la vérification.
                $issue->seal = null;
                $number = $issue->number;
                $suffix = 1;
                while ($DB->record_exists('ep_supplement_issue', ['number' => $issue->number])) {
                    $issue->number = \core_text::substr($number, 0, 34) . '-R' . $suffix++;
                }
                $issue->id = $DB->insert_record('ep_supplement_issue', $issue);
                $copyfiles($row['files'], EP_SUPPLEMENT_ISSUED_FILEAREA, $issue->id);
                $newissues[$number] = [$issue->id, $row['replacedbynumber']];
                $report->issues++;
            }
            foreach ($newissues as [$id, $replacedby]) {
                if ($replacedby !== '' && isset($newissues[$replacedby])) {
                    $DB->set_field('ep_supplement_issue', 'replacedby', $newissues[$replacedby][0], ['id' => $id]);
                }
            }

            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        foreach (array_keys($missingusers) as $oldid) {
            $user = $data['users'][$oldid] ?? ['email' => '?', 'firstname' => '', 'lastname' => ''];
            $report->warnings[] = get_string('restoremissingstudent', 'mod_ep', s(trim($user['firstname'] . ' '
                . $user['lastname']) . ' <' . $user['email'] . '>'));
        }
        ep_log_import($ep, 'archive', $userid, $report->credits + $report->items + $report->years + $report->issues, $report);
        return $report;
    }
}
