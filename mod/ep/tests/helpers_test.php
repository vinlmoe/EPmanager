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

namespace mod_ep;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Petites fonctions utilitaires sans dépendance à la base de données : formatage des ECTS,
 * libellés d'années d'étude, et surtout la règle qui décide qui peut statuer sur une demande,
 * appliquée ici à des droits déjà calculés.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::ep_format_ects
 * @covers     ::ep_studyyear_range_label
 * @covers     ::ep_rights_can_validate
 * @covers     ::ep_rights_has_validation_scope
 * @covers     ::ep_render_progress_cells
 */
final class helpers_test extends \advanced_testcase {
    /**
     * Construit un jeu de droits pré-calculés, tel que le renvoie ep_get_user_rights().
     *
     * @param array $overrides
     * @return \stdClass
     */
    protected function rights(array $overrides = []) {
        return (object) array_merge([
            'userid' => 7,
            'validatedeve' => false,
            'viewall' => false,
            'manage' => false,
            'evaluateteacher' => false,
            'referentids' => [],
            'responsibleids' => [],
        ], $overrides);
    }

    /**
     * Construit un crédit minimal.
     *
     * @param array $overrides
     * @return \stdClass
     */
    protected function credit(array $overrides = []) {
        return (object) array_merge([
            'userid' => 42,
            'activityid' => null,
            'source' => EP_SOURCE_STUDENT,
            'status' => EP_STATUS_PENDING,
        ], $overrides);
    }

    /**
     * Les ECTS sont fractionnaires, mais afficher « 2,00 » là où « 2 » suffit rend les tableaux
     * illisibles : les décimales inutiles sont supprimées.
     */
    public function test_format_ects_drops_useless_decimals(): void {
        $this->assertSame('2', ep_format_ects(2));
        $this->assertSame('2', ep_format_ects(2.0));
        $this->assertSame('0', ep_format_ects(0));
        $this->assertNotSame(ep_format_ects(2), ep_format_ects(2.5));
    }

    /**
     * Le libellé de plage d'années se réduit à une seule année quand les deux bornes sont
     * identiques ou qu'une seule est renseignée.
     */
    public function test_studyyear_range_label(): void {
        $this->assertSame(ep_studyyear_label(3), ep_studyyear_range_label(3, 3));
        $this->assertSame(ep_studyyear_label(4), ep_studyyear_range_label(0, 4));
        $this->assertSame(ep_studyyear_label(2), ep_studyyear_range_label(2, 0));
        $this->assertSame(
            ep_studyyear_label(2) . ' - ' . ep_studyyear_label(4),
            ep_studyyear_range_label(2, 4)
        );
    }

    /**
     * Un crédit attribué automatiquement n'est validable par personne, pas même par la DEVE : il
     * n'y a rien à vérifier, et une décision manuelle serait écrasée à la synchronisation
     * suivante.
     */
    public function test_automatic_credits_are_never_validated_by_hand(): void {
        $credit = $this->credit(['source' => EP_SOURCE_STAGE]);

        $this->assertFalse(ep_rights_can_validate($this->rights(['validatedeve' => true]), $credit));
    }

    /**
     * Une inscription au catalogue relève du responsable de l'EP concerné, et de lui seul : être
     * référent de l'étudiant n'y donne pas la main.
     */
    public function test_catalog_credit_belongs_to_the_activity_owner(): void {
        $credit = $this->credit(['activityid' => 5, 'userid' => 42]);

        $referent = $this->rights(['evaluateteacher' => true, 'referentids' => [42]]);
        $this->assertFalse(ep_rights_can_validate($referent, $credit));

        $owner = $this->rights(['evaluateteacher' => true, 'responsibleids' => [5]]);
        $this->assertTrue(ep_rights_can_validate($owner, $credit));

        $otherowner = $this->rights(['evaluateteacher' => true, 'responsibleids' => [6]]);
        $this->assertFalse(ep_rights_can_validate($otherowner, $credit));
    }

    /**
     * Une déclaration hors catalogue relève de l'enseignant référent de l'étudiant ; la DEVE peut
     * statuer sur n'importe laquelle, notamment quand aucun référent n'est attribué.
     */
    public function test_declared_credit_belongs_to_the_referent_or_the_deve(): void {
        $credit = $this->credit(['userid' => 42]);

        $referent = $this->rights(['evaluateteacher' => true, 'referentids' => [42, 43]]);
        $this->assertTrue(ep_rights_can_validate($referent, $credit));

        $otherreferent = $this->rights(['evaluateteacher' => true, 'referentids' => [43]]);
        $this->assertFalse(ep_rights_can_validate($otherreferent, $credit));

        $this->assertTrue(ep_rights_can_validate($this->rights(['validatedeve' => true]), $credit));

        // Un simple droit de lecture ne vaut pas droit de décision.
        $this->assertFalse(ep_rights_can_validate($this->rights(['viewall' => true]), $credit));
    }

    /**
     * L'écran de validation n'est ouvert qu'à ceux qui ont effectivement quelque chose à y faire.
     */
    public function test_validation_scope(): void {
        $this->assertFalse(ep_rights_has_validation_scope($this->rights(['evaluateteacher' => true])));
        $this->assertTrue(ep_rights_has_validation_scope($this->rights(['validatedeve' => true])));
        $this->assertTrue(ep_rights_has_validation_scope(
            $this->rights(['evaluateteacher' => true, 'referentids' => [42]])
        ));
        $this->assertTrue(ep_rights_has_validation_scope(
            $this->rights(['evaluateteacher' => true, 'responsibleids' => [5]])
        ));
    }

    /**
     * Sans exigence chiffrée, les colonnes « requis » et « reste à faire » n'ont pas de sens :
     * seule la durée retenue est affichée.
     */
    public function test_progress_cells_without_requirement(): void {
        $cells = ep_render_progress_cells(3, 0, true);
        $this->assertSame(['-', '3', '-', '-'], $cells);

        $cells = ep_render_progress_cells(3, 5, false);
        $this->assertSame('5', $cells[0]);
        $this->assertSame('3', $cells[1]);
        $this->assertSame('2', $cells[2]);
    }
}
