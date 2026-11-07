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

namespace mod_syllabus\output;

use html_table;
use html_writer;
use stdClass;

/**
 * Fiche d'un module : caractéristiques, rubriques, programme des séances, volume horaire.
 *
 * HTML volontairement simple (titres, paragraphes, tableaux à bordure) : la même fiche sert à
 * l'écran et au PDF, que TCPDF compose à partir d'un HTML sans mise en page élaborée.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fiche {
    /**
     * Fiche complète.
     *
     * @param stdClass $syllabus Instance.
     * @param stdClass|null $version Version à afficher.
     * @return string HTML
     */
    public static function render(stdClass $syllabus, ?stdClass $version): string {
        return self::characteristics($syllabus) . self::version($syllabus, $version);
    }

    /**
     * Caractéristiques du module, en tableau à deux colonnes.
     *
     * @param stdClass $syllabus
     * @return string HTML
     */
    public static function characteristics(stdClass $syllabus): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

        $course = $DB->get_record('course', ['id' => $syllabus->course], 'id, fullname, shortname');
        $teachers = syllabus_get_teachers((int) $syllabus->id);
        $type = $syllabus->typeid ? $DB->get_field('syllabus_type', 'name', ['id' => $syllabus->typeid]) : null;
        $rows = [
            get_string('module', 'mod_syllabus') => format_string($syllabus->name),
            get_string('code', 'mod_syllabus') => s($syllabus->code !== '' ? $syllabus->code : ($course->shortname ?? '-')),
            get_string('moduletype', 'mod_syllabus') => $type ? format_string($type) : '-',
            get_string('ects', 'mod_syllabus') => syllabus_format_number($syllabus->ects),
            get_string('semester', 'mod_syllabus') => s($syllabus->semester !== '' ? $syllabus->semester : '-'),
            get_string('studyyear', 'mod_syllabus') => syllabus_studyyear_label($syllabus->studyyear),
            get_string('teachers', 'mod_syllabus') => $teachers ? s(implode(', ', array_map('fullname', $teachers))) : '-',
        ];
        $table = new html_table();
        $table->attributes = ['class' => 'generaltable syllabus-characteristics', 'border' => '1', 'cellpadding' => '4'];
        foreach ($rows as $label => $value) {
            $table->data[] = [html_writer::tag('strong', $label), $value];
        }
        return html_writer::table($table);
    }

    /**
     * Une version : rubriques renseignées, langue, séances, volume horaire.
     *
     * @param stdClass $syllabus
     * @param stdClass|null $version
     * @return string HTML
     */
    public static function version(stdClass $syllabus, ?stdClass $version): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

        if (!$version) {
            return html_writer::tag('p', get_string('nofiche', 'mod_syllabus'), ['class' => 'syllabus-empty']);
        }
        $labelsep = get_string('labelsep', 'langconfig');
        $html = '';
        foreach (syllabus_text_fields() as $field => $label) {
            $text = trim((string) ($version->$field ?? ''));
            if ($text !== '') {
                $html .= html_writer::tag('h4', $label) . format_text($text, FORMAT_PLAIN);
            }
        }
        if (trim((string) $version->language) !== '') {
            $html .= html_writer::tag('p', html_writer::tag('strong', get_string('language', 'mod_syllabus') . $labelsep)
                . s($version->language));
        }

        $sessions = syllabus_get_sessions((int) $version->id);
        if ($sessions) {
            $ids = array_unique(array_filter(array_map(fn($s) => (int) $s->teacherid, $sessions)));
            $users = $ids ? $DB->get_records_list('user', 'id', $ids) : [];
            $kinds = syllabus_session_kind_options();
            $table = new html_table();
            $table->attributes = ['class' => 'generaltable syllabus-sessions', 'border' => '1', 'cellpadding' => '4'];
            $table->head = [get_string('sessiontitle', 'mod_syllabus'), get_string('sessionkind', 'mod_syllabus'),
                get_string('sessionduration', 'mod_syllabus'), get_string('sessionteacher', 'mod_syllabus')];
            foreach ($sessions as $session) {
                $teacher = !empty($session->teacherid) && isset($users[$session->teacherid])
                    ? fullname($users[$session->teacherid]) : trim((string) $session->teachername);
                $table->data[] = [s($session->title), $kinds[$session->kind] ?? s($session->kind),
                    syllabus_format_number($session->duration), $teacher !== '' ? s($teacher) : '-'];
            }
            $html .= html_writer::tag('h4', get_string('sessions', 'mod_syllabus')) . html_writer::table($table);
        }

        $hours = syllabus_hours($sessions, $version->personalhours);
        if ($hours->total > 0) {
            $kinds = syllabus_session_kind_options();
            $items = [];
            foreach ($hours->bykind as $kind => $duration) {
                $items[] = ($kinds[$kind] ?? s($kind)) . $labelsep . get_string(
                    'hoursn',
                    'mod_syllabus',
                    syllabus_format_number($duration)
                );
            }
            $items[] = get_string('hourssessions', 'mod_syllabus', syllabus_format_number($hours->sessions));
            $items[] = get_string('hourspersonal', 'mod_syllabus', syllabus_format_number($hours->personal));
            $total = get_string('hoursworkload', 'mod_syllabus', syllabus_format_number($hours->total));
            if ((float) $syllabus->ects > 0) {
                $total .= ' (' . get_string('hoursperects', 'mod_syllabus', syllabus_format_number($hours->total
                    / (float) $syllabus->ects)) . ')';
            }
            $items[] = html_writer::tag('strong', $total);
            $html .= html_writer::tag('h4', get_string('hourstotal', 'mod_syllabus')) . html_writer::alist($items);
        }
        return html_writer::div($html, 'syllabus-fiche');
    }
}
