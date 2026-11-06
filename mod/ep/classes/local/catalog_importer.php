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
 * Import du catalogue des EP par la DEVE depuis un fichier CSV, une ligne par EP, et de leur
 * programme de séances depuis un second fichier, une ligne par séance.
 *
 * Un EP est rapproché d'un EP existant par son code, à défaut par son intitulé : il est alors mis
 * à jour, sinon créé. Les colonnes absentes du fichier laissent les champs correspondants
 * inchangés (mise à jour) ou à leur valeur par défaut (création). Les colonnes du syllabus, si le
 * fichier en contient, publient une nouvelle version du syllabus, qui remplace la précédente.
 * Les colonnes reconnues sont décrites par columns() ; la page d'import les affiche.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog_importer {
    /**
     * Colonnes du fichier des EP : clé => [intitulés reconnus, obligatoire].
     *
     * @return array
     */
    public static function columns(): array {
        return [
            'code' => [['Code', 'Code EP'], false],
            'name' => [['Intitulé', 'Nom', 'Name', 'Title'], true],
            'type' => [['Type', 'Code type', 'Type code'], true],
            'ects' => [['ECTS', 'Crédits'], true],
            'minstudyyear' => [['Année min', 'Année minimale', 'Min year'], false],
            'maxstudyyear' => [['Année max', 'Année maximale', 'Max year'], false],
            'capacity' => [['Places', 'Capacité', 'Capacity'], false],
            'state' => [['État', 'Etat', 'State'], false],
            'registrationstart' => [['Ouverture des inscriptions', 'Registration start'], false],
            'registrationend' => [['Fermeture des inscriptions', 'Registration end'], false],
            'datestart' => [['Début', 'Start'], false],
            'dateend' => [['Fin', 'End'], false],
            'teachers' => [['Responsables', 'Owners', 'Teachers'], false],
            'description' => [['Description'], false],
            'grading' => [['Évaluation', 'Evaluation', 'Grading'], false],
            'grademax' => [['Note maximale', 'Max grade'], false],
            'gradepass' => [['Seuil', 'Seuil de validation', 'Pass grade'], false],
            'prerequisites' => [['Prérequis', 'Prerequisites'], false],
            'minects' => [['ECTS minimum', 'Min ECTS'], false],
            'prereqblocking' => [['Prérequis bloquants', 'Blocking prerequisites'], false],
            'objectives' => [['Objectifs', 'Objectives'], false],
            'outcomes' => [['Acquis d\'apprentissage', 'Learning outcomes'], false],
            'prerequisitestext' => [['Prérequis (texte)', 'Prerequisites text'], false],
            'content' => [['Contenu', 'Content'], false],
            'methods' => [['Méthodes', 'Méthodes d\'enseignement', 'Teaching methods'], false],
            'assessment' => [['Conditions d\'examen', 'Évaluation (modalités)', 'Assessment'], false],
            'resit' => [['Seconde session', 'Resit'], false],
            'bibliography' => [['Bibliographie', 'Bibliography'], false],
            'language' => [['Langue', 'Language'], false],
            'personalhours' => [['Travail personnel', 'Self-study'], false],
        ];
    }

    /**
     * Colonnes du fichier des séances : clé => [intitulés reconnus, obligatoire].
     *
     * @return array
     */
    public static function session_columns(): array {
        return [
            'code' => [['Code', 'Code EP'], true],
            'title' => [['Séance', 'Intitulé', 'Session', 'Title'], true],
            'kind' => [['Nature', 'Kind'], false],
            'duration' => [['Durée', 'Duration', 'Heures'], false],
            'teacher' => [['Intervenant', 'Lecturer', 'Teacher'], false],
            'date' => [['Date'], false],
        ];
    }

    /**
     * Intitulés reconnus par colonne, pour csv_reader::read().
     *
     * @param array $columns
     * @return array
     */
    protected static function aliases(array $columns): array {
        return array_map(fn($definition) => $definition[0], $columns);
    }

    /**
     * Comptes du site, par adresse et par numéro d'identification, pour les responsables et
     * intervenants désignés dans un fichier : ceux du cours d'abord, puis tout le site (un
     * intervenant n'est pas forcément inscrit au cours de la promotion).
     *
     * @param array $values Adresses ou numéros cités.
     * @return array [adresse => userid, numéro => userid]
     */
    protected static function lookup_users(array $values): array {
        global $DB;

        $values = array_values(array_unique(array_filter(array_map('trim', $values))));
        $byemail = [];
        $byidnumber = [];
        foreach (array_chunk($values, 500) as $chunk) {
            $lower = array_map('core_text::strtolower', $chunk);
            [$insql, $params] = $DB->get_in_or_equal($lower, SQL_PARAMS_NAMED, 'e');
            foreach ($DB->get_records_select('user', "LOWER(email) $insql AND deleted = 0", $params, '', 'id, email') as $user) {
                $key = \core_text::strtolower($user->email);
                // Une adresse portée par deux comptes est ambiguë : elle n'est rapprochée d'aucun.
                $byemail[$key] = isset($byemail[$key]) ? 0 : (int) $user->id;
            }
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'i');
            foreach ($DB->get_records_select('user', "idnumber $insql AND deleted = 0", $params, '', 'id, idnumber') as $user) {
                $byidnumber[$user->idnumber] = isset($byidnumber[$user->idnumber]) ? 0 : (int) $user->id;
            }
        }
        return [array_filter($byemail), array_filter($byidnumber)];
    }

    /**
     * Valeur d'état reconnue, ou null.
     *
     * @param string $value
     * @return string|null
     */
    protected static function state($value): ?string {
        $value = csv_reader::normalise($value);
        $map = ['publie' => EP_ACTIVITY_PUBLISHED, 'published' => EP_ACTIVITY_PUBLISHED, 'ouvert' => EP_ACTIVITY_PUBLISHED,
            'clos' => EP_ACTIVITY_CLOSED, 'closed' => EP_ACTIVITY_CLOSED, 'ferme' => EP_ACTIVITY_CLOSED,
            'archive' => EP_ACTIVITY_ARCHIVED, 'archived' => EP_ACTIVITY_ARCHIVED,
            'brouillon' => EP_ACTIVITY_DRAFT, 'draft' => EP_ACTIVITY_DRAFT,
            'propose' => EP_ACTIVITY_PROPOSED, 'proposed' => EP_ACTIVITY_PROPOSED];
        return $map[$value] ?? null;
    }

    /**
     * Lit le fichier des EP et prépare chaque ligne, sans rien écrire.
     *
     * @param \stdClass $ep
     * @param string $content
     * @return \stdClass {rows: array, errors: string[], warnings: string[], syllabus: bool}
     */
    public static function read(\stdClass $ep, $content): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $columns = self::columns();
        $read = csv_reader::read($content, self::aliases($columns));
        $result = (object) ['rows' => [], 'errors' => [], 'warnings' => [], 'syllabus' => false];
        foreach ($columns as $key => [$names, $required]) {
            if ($required && !isset($read->columns[$key])) {
                $result->errors[] = get_string('importcolumnmissing', 'mod_ep', $names[0]);
            }
        }
        if ($result->errors) {
            return $result;
        }
        foreach ($read->unknown as $header) {
            $result->warnings[] = get_string('importcolumnignored', 'mod_ep', s($header));
        }
        $result->syllabus = (bool) array_intersect(ep_syllabus_text_fields(), array_keys($read->columns));

        $types = [];
        foreach (ep_get_types($ep->id) as $type) {
            $types[csv_reader::normalise($type->code)] = $type;
        }
        $bycode = [];
        $byname = [];
        foreach (ep_get_activities($ep->id) as $activity) {
            if ($activity->code !== '') {
                $bycode[$activity->code] = (int) $activity->id;
            }
            $byname[\core_text::strtolower($activity->name)] = (int) $activity->id;
        }
        $teachervalues = [];
        foreach ($read->rows as $cells) {
            foreach (preg_split('/[,;\s]+/', csv_reader::value($read, $cells, 'teachers')) as $value) {
                $teachervalues[] = $value;
            }
        }
        [$byemail, $byidnumber] = self::lookup_users($teachervalues);

        $seencodes = [];
        foreach ($read->rows as $lineno => $cells) {
            $value = fn($column) => csv_reader::value($read, $cells, $column);
            $errors = [];
            $data = new \stdClass();
            $data->name = \core_text::substr($value('name'), 0, 255);
            if ($data->name === '') {
                $errors[] = get_string('importvaluemissing', 'mod_ep', get_string('catalogactivity', 'mod_ep'));
            }
            $data->code = \core_text::substr($value('code'), 0, 30);
            if ($data->code !== '' && isset($seencodes[$data->code])) {
                $errors[] = get_string('importduplicatecode', 'mod_ep', (object) ['code' => s($data->code),
                    'line' => $seencodes[$data->code]]);
            }
            $type = $types[csv_reader::normalise($value('type'))] ?? null;
            if (!$type || !ep_type_is_catalog($type)) {
                $errors[] = get_string('importunknowntype', 'mod_ep', s($value('type')));
            } else {
                $data->typeid = (int) $type->id;
            }
            $ects = csv_reader::number($value('ects'));
            if ($ects === null || $ects <= 0) {
                $errors[] = get_string('importbadnumber', 'mod_ep', (object) ['column' => 'ECTS', 'value' => s($value('ects'))]);
            } else {
                $data->ects = $ects;
            }
            foreach (['minstudyyear', 'maxstudyyear', 'capacity'] as $field) {
                if ($value($field) !== '') {
                    $number = csv_reader::number($value($field));
                    if ($number === null || $number < 0) {
                        $errors[] = get_string('importbadnumber', 'mod_ep', (object) ['column' => $columns[$field][0][0],
                            'value' => s($value($field))]);
                    } else {
                        $data->$field = (int) $number;
                    }
                }
            }
            foreach (['grademax', 'gradepass', 'minects', 'personalhours'] as $field) {
                if ($value($field) !== '') {
                    $number = csv_reader::number($value($field));
                    if ($number === null || $number < 0) {
                        $errors[] = get_string('importbadnumber', 'mod_ep', (object) ['column' => $columns[$field][0][0],
                            'value' => s($value($field))]);
                    } else {
                        $data->$field = $number;
                    }
                }
            }
            if ($value('state') !== '') {
                $state = self::state($value('state'));
                if ($state === null) {
                    $errors[] = get_string('importbadvalue', 'mod_ep', (object) ['column' => $columns['state'][0][0],
                        'value' => s($value('state'))]);
                } else {
                    $data->state = $state;
                }
            }
            foreach (['registrationstart', 'registrationend', 'datestart', 'dateend'] as $field) {
                if ($value($field) !== '') {
                    $date = csv_reader::date($value($field));
                    if ($date === null) {
                        $errors[] = get_string('importbaddate', 'mod_ep', (object) ['column' => $columns[$field][0][0],
                            'value' => s($value($field))]);
                    } else {
                        $data->$field = $date;
                    }
                }
            }
            if ($value('grading') !== '') {
                $grading = csv_reader::normalise($value('grading'));
                $map = ['type' => '', 'sansnote' => EP_GRADING_NONE, 'none' => EP_GRADING_NONE, 'avecnote' => EP_GRADING_GRADE,
                    'note' => EP_GRADING_GRADE, 'grade' => EP_GRADING_GRADE, 'graded' => EP_GRADING_GRADE];
                if (!array_key_exists($grading, $map)) {
                    $errors[] = get_string('importbadvalue', 'mod_ep', (object) ['column' => $columns['grading'][0][0],
                        'value' => s($value('grading'))]);
                } else {
                    $data->grading = $map[$grading];
                }
            }
            if ($value('prereqblocking') !== '') {
                $blocking = csv_reader::boolean($value('prereqblocking'));
                if ($blocking === null) {
                    $errors[] = get_string('importbadvalue', 'mod_ep', (object) ['column' => $columns['prereqblocking'][0][0],
                        'value' => s($value('prereqblocking'))]);
                } else {
                    $data->prereqblocking = $blocking ? 1 : 0;
                }
            }
            if (isset($read->columns['description'])) {
                $data->description = $value('description');
            }

            $teacherids = [];
            foreach (array_filter(preg_split('/[,;\s]+/', $value('teachers'))) as $teacher) {
                $userid = csv_reader::user($teacher, $byemail, $byidnumber);
                if ($userid) {
                    $teacherids[] = $userid;
                } else {
                    $result->warnings[] = get_string('importunknownuser', 'mod_ep', (object) ['line' => $lineno,
                        'value' => s($teacher)]);
                }
            }

            // Rubriques du syllabus : seules les cellules remplies remplacent le texte en vigueur,
            // une cellule vide le laisse tel quel.
            $syllabus = new \stdClass();
            foreach (array_merge(ep_syllabus_text_fields(), ['language']) as $field) {
                if ($value($field) !== '') {
                    $syllabus->$field = $value($field);
                }
            }
            if (isset($data->personalhours)) {
                $syllabus->personalhours = $data->personalhours;
            }
            $syllabus = (array) $syllabus ? $syllabus : null;
            unset($data->personalhours);

            $existingid = ($data->code !== '' ? ($bycode[$data->code] ?? 0) : 0)
                ?: ($byname[\core_text::strtolower($data->name)] ?? 0);
            if ($errors) {
                foreach ($errors as $error) {
                    $result->errors[] = get_string('importline', 'mod_ep', (object) ['line' => $lineno, 'error' => $error]);
                }
                continue;
            }
            if ($data->code !== '') {
                $seencodes[$data->code] = $lineno;
            } else {
                // Sans code dans le fichier, un EP rapproché par son intitulé garde le sien.
                unset($data->code);
            }
            $prereqs = array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value('prerequisites')))));
            $result->rows[] = (object) [
                'line' => $lineno,
                'existingid' => $existingid,
                'data' => $data,
                'teacherids' => array_values(array_unique($teacherids)),
                'prerequisites' => isset($read->columns['prerequisites']) ? $prereqs : null,
                'syllabus' => $syllabus,
            ];
        }
        return $result;
    }

    /**
     * Applique les lignes préparées par read(), sous le verrou de l'activité et dans une
     * transaction ; les prérequis sont résolus une fois tous les EP du fichier enregistrés, pour
     * qu'un EP puisse exiger un EP qui le suit dans le fichier.
     *
     * @param \stdClass $ep
     * @param array $rows
     * @param int $userid Auteur de l'import.
     * @return \stdClass {created, updated, syllabi, warnings}
     */
    public static function apply(\stdClass $ep, array $rows, $userid): \stdClass {
        global $DB;

        $result = (object) ['created' => 0, 'updated' => 0, 'syllabi' => 0, 'warnings' => []];
        $lock = ep_get_activity_lock($ep->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            $saved = [];
            foreach ($rows as $row) {
                $row = (object) $row;
                $data = (object) $row->data;
                // L'EP est relu : un import confirmé après une modification du catalogue (ou deux
                // fois) met à jour l'EP plutôt que de le dupliquer.
                $existingid = $row->existingid;
                if (!empty($data->code)) {
                    $bycodeid = (int) $DB->get_field('ep_activity', 'id', ['epid' => $ep->id, 'code' => $data->code]);
                    $existingid = $bycodeid ?: $existingid;
                }
                if (!$existingid) {
                    $existingid = (int) $DB->get_field_select('ep_activity', 'id', 'epid = :epid AND ' .
                        $DB->sql_equal('name', ':name', false), ['epid' => $ep->id, 'name' => $data->name], IGNORE_MULTIPLE);
                }
                $data->id = $existingid;
                $activityid = ep_save_activity($ep, $data);
                $existingid ? $result->updated++ : $result->created++;
                if (!empty($row->teacherids)) {
                    $current = array_keys(ep_get_activity_teachers($activityid));
                    ep_set_activity_teachers($activityid, array_merge($current, (array) $row->teacherids));
                }
                if (!empty($row->syllabus) && self::syllabus_changes($activityid, (array) $row->syllabus)) {
                    $activity = $DB->get_record('ep_activity', ['id' => $activityid], '*', MUST_EXIST);
                    ep_publish_syllabus_directly($activity, (object) $row->syllabus, null, $userid);
                    $result->syllabi++;
                }
                $saved[] = [$activityid, $row->prerequisites, $row->line];
            }

            $bycode = [];
            $byname = [];
            foreach (ep_get_activities($ep->id) as $activity) {
                if ($activity->code !== '') {
                    $bycode[$activity->code] = (int) $activity->id;
                }
                $byname[\core_text::strtolower($activity->name)] = (int) $activity->id;
            }
            foreach ($saved as [$activityid, $prerequisites, $line]) {
                if ($prerequisites === null) {
                    continue;
                }
                $requiredids = [];
                foreach ($prerequisites as $required) {
                    $id = $bycode[$required] ?? $byname[\core_text::strtolower($required)] ?? 0;
                    if ($id) {
                        $requiredids[] = $id;
                    } else {
                        $result->warnings[] = get_string('importunknownprerequisite', 'mod_ep', (object) ['line' => $line,
                            'value' => s($required)]);
                    }
                }
                ep_set_activity_prerequisites($DB->get_record('ep_activity', ['id' => $activityid]), $requiredids);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        ep_log_import($ep, 'catalog', $userid, count($rows), $result);
        return $result;
    }

    /**
     * Les rubriques importées changent-elles le syllabus en vigueur ? Un import rejoué à
     * l'identique ne publie pas de nouvelle version.
     *
     * @param int $activityid
     * @param array $fields rubrique => texte
     * @return bool
     */
    protected static function syllabus_changes(int $activityid, array $fields): bool {
        $current = ep_get_syllabus($activityid);
        if (!$current) {
            return true;
        }
        foreach ($fields as $field => $text) {
            if (
                (string) ($current->$field ?? '') !== (string) $text
                    && !(is_numeric($text) && (float) ($current->$field ?? 0) === (float) $text)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lit le fichier des séances : séances regroupées par EP (désigné par son code).
     *
     * @param \stdClass $ep
     * @param string $content
     * @return \stdClass {byactivity: array activityid => séances, errors, warnings}
     */
    public static function read_sessions(\stdClass $ep, $content): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');

        $columns = self::session_columns();
        $read = csv_reader::read($content, self::aliases($columns));
        $result = (object) ['byactivity' => [], 'errors' => [], 'warnings' => []];
        foreach ($columns as $key => [$names, $required]) {
            if ($required && !isset($read->columns[$key])) {
                $result->errors[] = get_string('importcolumnmissing', 'mod_ep', $names[0]);
            }
        }
        if ($result->errors) {
            return $result;
        }
        $bycode = [];
        foreach (ep_get_activities($ep->id) as $activity) {
            if ($activity->code !== '') {
                $bycode[$activity->code] = (int) $activity->id;
            }
        }
        // Nature reconnue par son code, son libellé dans la langue de la personne qui importe ou
        // les appellations usuelles en français et en anglais (CM, TD, TP...).
        $kinds = [];
        $usual = [
            'lecture' => ['cm', 'cours', 'coursmagistral', 'lecture'],
            'tutorial' => ['td', 'travauxdiriges', 'tutorial', 'seminar', 'seminaire'],
            'practical' => ['tp', 'travauxpratiques', 'practical', 'lab'],
            'clinical' => ['clinique', 'clinical', 'rotationclinique'],
            'fieldwork' => ['terrain', 'sortie', 'sortiedeterrain', 'fieldwork', 'visite'],
            'project' => ['projet', 'project'],
            'other' => ['autre', 'other'],
        ];
        foreach (ep_session_kind_options() as $kind => $label) {
            $kinds[csv_reader::normalise($kind)] = $kind;
            $kinds[csv_reader::normalise($label)] = $kind;
            foreach ($usual[$kind] ?? [] as $name) {
                $kinds[$name] = $kind;
            }
        }
        $teachervalues = [];
        foreach ($read->rows as $cells) {
            $teachervalues[] = csv_reader::value($read, $cells, 'teacher');
        }
        [$byemail, $byidnumber] = self::lookup_users($teachervalues);

        foreach ($read->rows as $lineno => $cells) {
            $value = fn($column) => csv_reader::value($read, $cells, $column);
            $activityid = $bycode[$value('code')] ?? 0;
            if (!$activityid) {
                $result->errors[] = get_string('importline', 'mod_ep', (object) ['line' => $lineno,
                    'error' => get_string('importunknownactivity', 'mod_ep', s($value('code')))]);
                continue;
            }
            $duration = $value('duration') === '' ? 0.0 : csv_reader::number($value('duration'));
            $date = csv_reader::date($value('date'));
            if ($duration === null || $duration < 0 || $date === null) {
                $result->errors[] = get_string('importline', 'mod_ep', (object) ['line' => $lineno,
                    'error' => get_string('importbadvalue', 'mod_ep', (object) ['column' => $columns['duration'][0][0] . ' / '
                        . $columns['date'][0][0], 'value' => s($value('duration') . ' ' . $value('date'))])]);
                continue;
            }
            $teacher = $value('teacher');
            $teacherid = csv_reader::user($teacher, $byemail, $byidnumber);
            $result->byactivity[$activityid][] = [
                'title' => $value('title'),
                'kind' => $kinds[csv_reader::normalise($value('kind'))] ?? 'other',
                'duration' => $duration,
                'teacherid' => $teacherid ?: 0,
                'teachername' => $teacherid ? '' : $teacher,
                'sessiondate' => $date,
            ];
        }
        return $result;
    }

    /**
     * Remplace le programme des EP par les séances lues : une nouvelle version publiée du
     * syllabus, reprise de la précédente avec le nouveau programme.
     *
     * @param \stdClass $ep
     * @param array $byactivity activityid => séances
     * @param int $userid
     * @return int Nombre d'EP mis à jour.
     */
    public static function apply_sessions(\stdClass $ep, array $byactivity, $userid): int {
        global $DB;

        $lock = ep_get_activity_lock($ep->id);
        try {
            $transaction = $DB->start_delegated_transaction();
            foreach ($byactivity as $activityid => $sessions) {
                $activity = $DB->get_record('ep_activity', ['id' => $activityid, 'epid' => $ep->id], '*', MUST_EXIST);
                ep_publish_syllabus_directly($activity, new \stdClass(), array_values((array) $sessions), $userid);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
        ep_log_import($ep, 'sessions', $userid, count($byactivity), (object) ['updated' => count($byactivity)]);
        return count($byactivity);
    }
}
