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
     * @param stdClass|null $compare Version de référence : les rubriques qui en diffèrent sont signalées (relecture).
     * @param bool $withname Nom du module dans le tableau (PDF) ; à l'écran, il est déjà le titre de la page.
     * @return string HTML
     */
    public static function render(
        stdClass $syllabus,
        ?stdClass $version,
        ?stdClass $compare = null,
        bool $withname = true
    ): string {
        return self::characteristics($syllabus, $version, $withname) . self::version($syllabus, $version, $compare)
            . self::competencies($syllabus);
    }

    /**
     * Compétences visées par le module (référentiel tenu dans le suivi des modules), s'il en a.
     *
     * @param stdClass $syllabus
     * @return string HTML
     */
    public static function competencies(stdClass $syllabus): string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

        $competencies = syllabus_get_module_competencies($syllabus);
        if (!$competencies) {
            return '';
        }
        return html_writer::tag('h4', get_string('targetedcompetencies', 'mod_syllabus', count($competencies)))
            . syllabus_render_competencies($competencies);
    }

    /**
     * Caractéristiques du module, en tableau à deux colonnes.
     *
     * @param stdClass $syllabus
     * @param stdClass|null $version Pour la langue d'enseignement.
     * @param bool $withname
     * @return string HTML
     */
    public static function characteristics(stdClass $syllabus, ?stdClass $version = null, bool $withname = true): string {
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
        // Fiche d'un EP : ni type de module ni période ; son code est celui de l'EP, pas le cours.
        if (!empty($syllabus->component)) {
            unset($rows[get_string('moduletype', 'mod_syllabus')], $rows[get_string('semester', 'mod_syllabus')]);
            $rows[get_string('code', 'mod_syllabus')] = $syllabus->code !== '' ? s($syllabus->code) : '-';
        }
        if (!$withname) {
            unset($rows[get_string('module', 'mod_syllabus')]);
        }
        if ($version && trim((string) $version->language) !== '') {
            $rows[get_string('language', 'mod_syllabus')] = s($version->language);
        }
        if ($version && !empty($version->commissiondate)) {
            $rows[get_string('commissionvalidated', 'mod_syllabus')] = userdate(
                $version->commissiondate,
                get_string('strftimedate', 'langconfig')
            );
        }
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
     * @param stdClass|null $compare Version de référence : rubriques et séances modifiées signalées.
     * @return string HTML
     */
    public static function version(stdClass $syllabus, ?stdClass $version, ?stdClass $compare = null): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/syllabus/locallib.php');

        if (!$version) {
            return html_writer::tag('p', get_string('nofiche', 'mod_syllabus'), ['class' => 'syllabus-empty']);
        }
        $labelsep = get_string('labelsep', 'langconfig');
        $changed = ' ' . html_writer::span(get_string('changedsincepublished', 'mod_syllabus'), 'badge badge-warning');
        $html = '';
        foreach (syllabus_text_fields() as $field => $label) {
            $text = trim((string) ($version->$field ?? ''));
            $mark = $compare && $text !== trim((string) ($compare->$field ?? '')) ? $changed : '';
            if ($text !== '') {
                $html .= html_writer::tag('h4', $label . $mark) . format_text($text, FORMAT_PLAIN);
            } else if ($mark !== '') {
                $html .= html_writer::tag('h4', $label . $mark) . html_writer::tag(
                    'p',
                    get_string('fieldremoved', 'mod_syllabus'),
                    ['class' => 'text-muted']
                );
            }
        }

        $sessions = syllabus_get_sessions((int) $version->id);
        $sessionsmark = '';
        if ($compare) {
            $signature = fn(array $list) => array_map(fn($s) => [$s->title, $s->kind, (float) $s->duration, (int) $s->teacherid,
                (string) $s->teachername], array_values($list));
            $sessionsmark = $signature($sessions) !== $signature(syllabus_get_sessions((int) $compare->id)) ? $changed : '';
        }
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
            $html .= html_writer::tag('h4', get_string('sessions', 'mod_syllabus') . $sessionsmark) . html_writer::table($table);
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
