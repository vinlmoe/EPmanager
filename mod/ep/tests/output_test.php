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

namespace mod_ep;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Rendu des gabarits Mustache : actions, badges, tableau paginé, filtres de liste.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_render_actions
 * @covers     ::ep_render_status_badge
 * @covers     ::ep_render_credit_status
 * @covers     ::ep_render_paged_table
 * @covers     ::ep_render_paging_bar
 * @covers     ::ep_render_list_filters
 * @covers     \mod_ep\output\list_filters
 */
final class output_test extends \advanced_testcase {
    /**
     * Les actions sont rendues en liens avec leur classe, les entrées nulles ignorées, et le texte
     * de remplacement affiché sans action.
     */
    public function test_actions(): void {
        $this->resetAfterTest();
        $html = ep_render_actions([
            'Voir' => new \moodle_url('/mod/ep/view.php', ['id' => 3]),
            'Rien' => null,
        ], 'btn btn-sm btn-primary');
        $this->assertStringContainsString('class="btn btn-sm btn-primary"', $html);
        $this->assertStringContainsString('view.php?id=3', $html);
        $this->assertStringContainsString('>Voir<', $html);
        $this->assertStringNotContainsString('Rien', $html);
        $this->assertSame('-', trim(ep_render_actions(['Rien' => null])));
        $this->assertSame('', trim(ep_render_actions([], 'btn', '')));
    }

    /**
     * Badges d'objectif et de statut de crédit.
     */
    public function test_badges(): void {
        $this->resetAfterTest();
        $this->assertStringContainsString('badge-success', ep_render_status_badge(true));
        $this->assertStringContainsString('badge-warning', ep_render_status_badge(false));
        $this->assertStringContainsString(get_string('objectivetodo', 'mod_ep'), ep_render_status_badge(false));

        $html = ep_render_credit_status(EP_STATUS_REJECTED);
        $this->assertStringContainsString('badge-danger', $html);
        $this->assertStringContainsString(get_string('status_rejected', 'mod_ep'), $html);
    }

    /**
     * Le tableau rend les cellules telles quelles (déjà échappées par l'appelant) et la barre de
     * pagination seulement quand il y a plus d'une page.
     */
    public function test_paged_table(): void {
        $this->resetAfterTest();
        $url = new \moodle_url('/mod/ep/credits.php', ['id' => 3]);
        $this->assertSame('', ep_render_paging_bar(EP_LIST_PERPAGE, 0, $url));
        $bar = ep_render_paging_bar(EP_LIST_PERPAGE + 1, 0, $url);
        $this->assertStringContainsString('page=1', $bar);

        $html = ep_render_paged_table(['<a href="#">Nom</a>'], [['<b>Alice</b>'], ['Bruno']], $bar, 'generaltable t');
        $this->assertStringContainsString('<table class="generaltable t">', $html);
        $this->assertStringContainsString('<th scope="col"><a href="#">Nom</a></th>', $html);
        $this->assertStringContainsString('<td><b>Alice</b></td>', $html);
        $this->assertStringContainsString('page=1', $html);
    }

    /**
     * Le formulaire de filtres conserve les paramètres de l'URL hors filtres en champs cachés,
     * sélectionne la valeur courante et échappe la recherche.
     */
    public function test_list_filters(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $ep = $this->getDataGenerator()->create_module('ep', ['course' => $course->id]);
        $url = new \moodle_url('/mod/ep/credits.php', [
            'id' => 3, 'search' => 'a"b', 'typeid' => 0, 'status' => '1', 'studyyear' => '', 'tsort' => 'name',
        ]);
        $html = ep_render_list_filters($url, ep_get_types($ep->id), [
            'search' => 'a"b', 'typeid' => 0, 'status' => '1', 'studyyear' => '',
        ]);
        $this->assertStringContainsString('name="id" value="3"', $html);
        $this->assertStringNotContainsString('name="tsort"', $html);
        $this->assertStringContainsString('value="a&quot;b"', $html);
        $this->assertStringContainsString('<option value="1" selected>', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringNotContainsString('name="status"', ep_render_list_filters($url, [], [], false));
    }
}
