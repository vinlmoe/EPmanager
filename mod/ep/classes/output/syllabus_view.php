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

namespace mod_ep\output;

use html_table;
use html_writer;
use stdClass;

/**
 * Rendu HTML de la fiche d'un EP du catalogue : ses caractéristiques et une version de son
 * syllabus (rubriques, programme des séances, volume horaire).
 *
 * Le HTML produit reste volontairement simple (titres, paragraphes, tableaux à bordure) : la même
 * fiche sert à l'écran (syllabus.php) et aux éditions PDF, que TCPDF ne sait composer qu'à partir
 * d'un HTML sans mise en page élaborée.
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syllabus_view {
    /**
     * Fiche complète d'un EP : caractéristiques puis syllabus. Sans l'état de l'EP ni l'auteur de
     * la proposition, qui relèvent du circuit de publication et n'ont rien à faire sur une fiche
     * remise aux étudiants.
     *
     * @param stdClass $activity EP du catalogue (ep_activity).
     * @param stdClass|null $syllabus Version à afficher, ou null si l'EP n'en a pas.
     * @param stdClass|null $type Type de l'EP (ep_type), s'il est connu.
     * @return string HTML
     */
    public static function render(stdClass $activity, ?stdClass $syllabus, ?stdClass $type): string {
        return self::render_characteristics($activity, $type) . self::render_version($activity, $syllabus);
    }

    /**
     * Libellés des rubriques du syllabus, dans l'ordre de ep_syllabus_text_fields() : le
     * formulaire de rédaction et la fiche les partagent, pour qu'une rubrique porte le même nom
     * partout.
     *
     * @return array champ => libellé
     */
    public static function field_labels(): array {
        self::require_lib();
        $keys = [
            'objectives' => 'syllabusobjectives',
            'outcomes' => 'syllabusoutcomes',
            'prerequisitestext' => 'syllabusprerequisites',
            'content' => 'syllabuscontent',
            'methods' => 'syllabusmethods',
            'assessment' => 'syllabusassessment',
            'resit' => 'syllabusresit',
            'bibliography' => 'syllabusbibliography',
        ];
        $labels = [];
        foreach (ep_syllabus_text_fields() as $field) {
            $labels[$field] = get_string($keys[$field] ?? $field, 'mod_ep');
        }
        return $labels;
    }

    /**
     * Caractéristiques d'un EP, en tableau à deux colonnes : intitulé, code, type, ECTS, années,
     * places, responsables, et pour le circuit de publication l'état et l'auteur de la proposition.
     *
     * @param stdClass $activity
     * @param stdClass|null $type
     * @param bool $withworkflow Ajoute l'état de l'EP et l'auteur de la proposition.
     * @return string HTML
     */
    public static function render_characteristics(stdClass $activity, ?stdClass $type, bool $withworkflow = false): string {
        global $DB;
        self::require_lib();

        $teachers = ep_get_activity_teachers($activity->id);
        $rows = [
            get_string('catalogactivity', 'mod_ep') => format_string($activity->name),
        ];
        $code = trim((string) ($activity->code ?? ''));
        if ($code !== '') {
            $rows[get_string('activitycode', 'mod_ep')] = s($code);
        }
        $rows[get_string('type', 'mod_ep')] = $type ? format_string($type->name) : '-';
        $rows[get_string('ects', 'mod_ep')] = ep_format_ects($activity->ects);
        $years = ep_studyyear_range_label($activity->minstudyyear, $activity->maxstudyyear);
        $rows[get_string('studyyearrange', 'mod_ep')] = s($years);
        $rows[get_string('places', 'mod_ep')] = empty($activity->capacity)
            ? get_string('unlimitedplaces', 'mod_ep')
            : (int) $activity->capacity;
        $rows[get_string('activityteachers', 'mod_ep')] = $teachers
            ? s(implode(', ', array_map('fullname', $teachers)))
            : '-';
        if ($withworkflow) {
            $rows[get_string('activitystate', 'mod_ep')] = ep_activity_state_options()[$activity->state] ?? s($activity->state);
            if (!empty($activity->proposedby)) {
                $proposer = $DB->get_record('user', ['id' => $activity->proposedby]);
                if ($proposer) {
                    // La chaîne « Proposé par {$a} » sert d'intitulé de ligne, vidée de son nom.
                    $rows[get_string('proposedbylabel', 'mod_ep')] = s(fullname($proposer));
                }
            }
        }

        $table = new html_table();
        $table->attributes = ['class' => 'generaltable ep-syllabus-characteristics', 'border' => '1', 'cellpadding' => '4'];
        foreach ($rows as $label => $value) {
            $table->data[] = [html_writer::tag('strong', $label), $value];
        }
        $html = '';
        if (trim((string) ($activity->description ?? '')) !== '') {
            $html .= html_writer::div(format_text($activity->description, FORMAT_PLAIN), 'ep-syllabus-description mb-2');
        }
        return html_writer::table($table) . $html;
    }

    /**
     * Une version de syllabus : rubriques renseignées, langue, programme des séances et volume
     * horaire. Une rubrique vide n'est pas affichée : une fiche en cours de rédaction ne doit pas
     * se présenter comme une suite de titres sans contenu.
     *
     * @param stdClass $activity L'EP, pour rapporter le volume horaire à ses ECTS.
     * @param stdClass|null $syllabus
     * @return string HTML
     */
    public static function render_version(stdClass $activity, ?stdClass $syllabus): string {
        global $DB;
        self::require_lib();

        if (!$syllabus) {
            return html_writer::tag('p', get_string('nosyllabus', 'mod_ep'), ['class' => 'ep-syllabus-empty']);
        }
        $labelsep = get_string('labelsep', 'langconfig');

        $html = '';
        foreach (self::field_labels() as $field => $label) {
            $text = trim((string) ($syllabus->$field ?? ''));
            if ($text === '') {
                continue;
            }
            $html .= html_writer::tag('h4', $label) . format_text($text, FORMAT_PLAIN);
        }
        $language = trim((string) ($syllabus->language ?? ''));
        if ($language !== '') {
            $label = html_writer::tag('strong', get_string('syllabuslanguage', 'mod_ep') . $labelsep);
            $html .= html_writer::tag('p', $label . s($language));
        }

        // Programme des séances, intervenants lus en une requête.
        $sessions = ep_get_syllabus_sessions($syllabus->id);
        if ($sessions) {
            $teacherids = array_unique(array_filter(array_map(fn($session) => (int) $session->teacherid, $sessions)));
            $teachers = $teacherids ? $DB->get_records_list('user', 'id', $teacherids) : [];
            $kinds = ep_session_kind_options();

            $table = new html_table();
            $table->attributes = ['class' => 'generaltable ep-syllabus-sessions', 'border' => '1', 'cellpadding' => '4'];
            $table->head = [
                get_string('sessiontitle', 'mod_ep'),
                get_string('sessionkind', 'mod_ep'),
                get_string('sessionduration', 'mod_ep'),
                get_string('sessionteacher', 'mod_ep'),
                get_string('sessiondate', 'mod_ep'),
            ];
            foreach ($sessions as $session) {
                if (!empty($session->teacherid) && isset($teachers[$session->teacherid])) {
                    $teacher = fullname($teachers[$session->teacherid]);
                } else {
                    $teacher = trim((string) $session->teachername);
                }
                $table->data[] = [
                    s($session->title),
                    $kinds[$session->kind] ?? s($session->kind),
                    ep_format_ects($session->duration),
                    $teacher !== '' ? s($teacher) : '-',
                    !empty($session->sessiondate) ? userdate($session->sessiondate, get_string('strftimedate')) : '-',
                ];
            }
            $html .= html_writer::tag('h4', get_string('sessions', 'mod_ep')) . html_writer::table($table);
        }

        // Volume horaire : par nature de séance, puis séances, travail personnel et charge totale,
        // rapportée aux ECTS (repère du guide ECTS : 25 à 30 heures de travail par crédit).
        $hours = ep_syllabus_hours($sessions, $syllabus->personalhours ?? 0);
        if ($hours->total > 0) {
            $kinds = ep_session_kind_options();
            $items = [];
            foreach ($hours->bykind as $kind => $duration) {
                $items[] = ($kinds[$kind] ?? s($kind)) . $labelsep . get_string('numhours', 'moodle', ep_format_ects($duration));
            }
            $items[] = get_string('hourssessions', 'mod_ep', ep_format_ects($hours->sessions));
            $items[] = get_string('hourspersonal', 'mod_ep', ep_format_ects($hours->personal));
            $workload = get_string('hoursworkload', 'mod_ep', ep_format_ects($hours->total));
            if ((float) $activity->ects > 0) {
                $perects = ep_format_ects($hours->total / (float) $activity->ects);
                $workload .= ' (' . get_string('hoursperects', 'mod_ep', $perects) . ')';
            }
            $items[] = html_writer::tag('strong', $workload);
            $html .= html_writer::tag('h4', get_string('hourstotal', 'mod_ep')) . html_writer::alist($items);
        }

        return html_writer::div($html, 'ep-syllabus');
    }

    /**
     * Charge la bibliothèque du module : la classe est chargée automatiquement, mais les fonctions
     * qu'elle appelle sont dans locallib.php, qu'une page PDF ou une tâche n'a pas forcément incluse.
     *
     * @return void
     */
    private static function require_lib(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/ep/locallib.php');
    }
}
