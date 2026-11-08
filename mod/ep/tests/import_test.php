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

use mod_ep\local\catalog_importer;
use mod_ep\local\credits_importer;
use mod_ep\local\csv_reader;
use mod_ep\local\global_archive;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Imports CSV (catalogue, séances, crédits), transfert du paramétrage, export complet et
 * restauration.
 *
 * @package    mod_ep
 * @copyright  2026 Sébastien Lefebvre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_ep\local\csv_reader
 * @covers     \mod_ep\local\catalog_importer
 * @covers     \mod_ep\local\credits_importer
 * @covers     \mod_ep\local\global_archive
 * @covers     ::ep_export_settings
 * @covers     ::ep_import_settings
 * @covers     ::ep_import_settings_catalog
 * @covers     ::ep_publish_syllabus_directly
 * @covers     ::ep_log_import
 */
final class import_test extends \advanced_testcase {
    /** @var \stdClass Cours. */
    private $course;

    /** @var \stdClass Instance. */
    private $ep;

    /** @var \context_module */
    private $context;

    /** @var \stdClass Étudiante. */
    private $student;

    /** @var \stdClass Enseignant. */
    private $teacher;

    /** @var \mod_ep_generator */
    private $gen;

    /**
     * Un cours, une activité en année 3, une étudiante et un enseignant.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $this->ep = $gen->create_module('ep', ['course' => $this->course->id, 'currentstudyyear' => 3]);
        $this->context = \context_module::instance($this->ep->cmid);
        $this->ep = $DB->get_record('ep', ['id' => $this->ep->id]);
        $this->student = $gen->create_and_enrol($this->course, 'student', [
            'firstname' => 'Zoé', 'lastname' => 'Martin', 'email' => 'zoe.martin@example.com', 'idnumber' => 'VET-001',
        ]);
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher', ['email' => 'prof@example.com']);
        $this->gen = $gen->get_plugin_generator('mod_ep');
    }

    /**
     * Lecture : BOM, encodage Windows-1252, séparateur détecté, intitulés sans accent ni casse,
     * champ entre guillemets sur plusieurs lignes, lignes vides ignorées ; nombres, dates, oui/non.
     */
    public function test_csv_reader(): void {
        $aliases = ['name' => ['Intitulé'], 'ects' => ['ECTS'], 'content' => ['Contenu']];
        $content = "\xEF\xBB\xBFintitule;ects;Contenu;Inconnue\r\nAnatomie;2,5;\"Ligne 1\nLigne 2\";x\r\n\r\n;;;\r\n";
        $read = csv_reader::read($content, $aliases);
        $this->assertSame(['name' => 0, 'ects' => 1, 'content' => 2], $read->columns);
        $this->assertSame(['Inconnue'], $read->unknown);
        $this->assertCount(1, $read->rows);
        $cells = $read->rows[2];
        $this->assertSame("Ligne 1\nLigne 2", csv_reader::value($read, $cells, 'content'));
        $this->assertSame(2.5, csv_reader::number(csv_reader::value($read, $cells, 'ects')));

        $latin = mb_convert_encoding("Intitulé,ECTS\nÉthologie,3\n", 'Windows-1252', 'UTF-8');
        $read = csv_reader::read($latin, $aliases);
        $this->assertSame('Éthologie', csv_reader::value($read, reset($read->rows), 'name'));

        $tab = "Intitulé\tECTS\nA\t1\n";
        $this->assertSame(['name' => 0, 'ects' => 1], csv_reader::read($tab, $aliases)->columns);

        $this->assertSame(1234.5, csv_reader::number("1\u{202F}234,5"));
        $this->assertNull(csv_reader::number('deux'));
        $this->assertSame(make_timestamp(2026, 9, 1), csv_reader::date('01/09/2026'));
        $this->assertSame(make_timestamp(2026, 9, 1, 14, 30), csv_reader::date('2026-09-01 14:30'));
        $this->assertSame(0, csv_reader::date(''));
        $this->assertNull(csv_reader::date('1er septembre'));
        $this->assertTrue(csv_reader::boolean('Oui'));
        $this->assertFalse(csv_reader::boolean('non'));
        $this->assertNull(csv_reader::boolean('peut-être'));
    }

    /**
     * Catalogue : erreurs signalées par ligne sans bloquer les autres, création avec responsables,
     * prérequis vers un EP plus loin dans le fichier et syllabus publié ; un second import met à
     * jour sans dupliquer et garde le code d'un EP rapproché par son intitulé.
     */
    public function test_catalog_import(): void {
        global $DB;
        $csv = "Code;Intitulé;Type;ECTS;Places;État;Responsables;Prérequis;Objectifs;Langue\n"
            . "ANA2;Anatomie avancée;academic;2,5;20;publié;prof@example.com;ANA1;Approfondir;français\n"
            . "ANA1;Anatomie;academic;2;;;inconnu@example.com;;Bases;\n"
            . "X1;Sans type;nimporte;2;;;;;;\n"
            . "X2;ECTS faux;academic;zéro;;;;;;\n"
            . "ANA1;Doublon;academic;1;;;;;;\n";
        $read = catalog_importer::read($this->ep, $csv);
        $this->assertCount(2, $read->rows);
        $this->assertCount(3, $read->errors);
        $this->assertCount(1, $read->warnings);
        $this->assertTrue($read->syllabus);

        // Ce que la page écrit puis relit : la prévisualisation passe par JSON.
        $rows = json_decode(json_encode($read->rows), true);
        $result = catalog_importer::apply($this->ep, $rows, get_admin()->id);
        $this->assertSame([2, 0, 2], [$result->created, $result->updated, $result->syllabi]);
        $ana1 = $DB->get_record('ep_activity', ['epid' => $this->ep->id, 'code' => 'ANA1']);
        $ana2 = $DB->get_record('ep_activity', ['epid' => $this->ep->id, 'code' => 'ANA2']);
        $this->assertEquals(2.5, $ana2->ects);
        $this->assertEquals(20, $ana2->capacity);
        $this->assertSame([(int) $ana1->id], array_map('intval', ep_get_activity_prerequisites($ana2->id)));
        $this->assertArrayHasKey($this->teacher->id, ep_get_activity_teachers($ana2->id));
        $syllabus = ep_get_syllabus($ana2->id);
        $this->assertSame('Approfondir', $syllabus->objectives);
        $this->assertSame('français', $syllabus->language);
        $this->assertSame(1, $DB->count_records('ep_import_log', ['epid' => $this->ep->id, 'kind' => 'catalog']));

        // Second import, par intitulé, sans colonne de code : mise à jour, nouvelle version publiée.
        $read = catalog_importer::read($this->ep, "Intitulé;Type;ECTS;Objectifs\nAnatomie avancée;academic;3;Approfondir encore\n");
        $result = catalog_importer::apply($this->ep, $read->rows, get_admin()->id);
        $this->assertSame([0, 1], [$result->created, $result->updated]);
        $ana2 = $DB->get_record('ep_activity', ['id' => $ana2->id]);
        $this->assertSame('ANA2', $ana2->code);
        $this->assertEquals(3, $ana2->ects);
        $this->assertSame('Approfondir encore', ep_get_syllabus($ana2->id)->objectives);
        $syllabusid = ep_activity_syllabus_record($ana2->id)->id;
        $this->assertSame(1, $DB->count_records('syllabus_version', ['syllabusid' => $syllabusid, 'status' => 'published']));
        $this->assertSame(1, $DB->count_records('syllabus_version', ['syllabusid' => $syllabusid, 'status' => 'superseded']));

        // Rejoué à l'identique : aucune nouvelle version ; une cellule vide garde le texte.
        $this->assertSame(0, catalog_importer::apply($this->ep, $read->rows, get_admin()->id)->syllabi);
        $read = catalog_importer::read($this->ep, "Code;Intitulé;Type;ECTS;Objectifs\nANA2;Anatomie avancée;academic;3;\n");
        $this->assertNull($read->rows[0]->syllabus);
        $this->assertSame(2, $DB->count_records('ep_activity', ['epid' => $this->ep->id]));
    }

    /**
     * Séances : programme remplacé dans une nouvelle version publiée, textes repris ; intervenant
     * du site rapproché par son adresse, intervenant extérieur gardé par son nom ; colonne « Date »
     * d'un ancien fichier signalée et ignorée (les séances ne sont plus datées).
     */
    public function test_sessions_import(): void {
        $activity = $this->gen->create_activity($this->ep, ['code' => 'PHY1', 'name' => 'Physiologie']);
        ep_publish_syllabus_directly($activity, (object) ['objectives' => 'Comprendre'], [
            ['title' => 'Ancienne séance', 'kind' => 'lecture', 'duration' => 2],
        ], get_admin()->id);

        $csv = "Code EP;Séance;Nature;Durée;Intervenant;Date\n"
            . "PHY1;Cœur;Cours magistral;2;prof@example.com;02/09/2026\n"
            . "PHY1;TP cœur;practical;3,5;Dr Dupont;\n"
            . "INCONNU;Rien;;1;;\n";
        $read = catalog_importer::read_sessions($this->ep, $csv);
        $this->assertCount(1, $read->errors);
        $this->assertSame([get_string('importcolumnignored', 'mod_ep', 'Date')], $read->warnings);
        $this->assertSame(1, catalog_importer::apply_sessions($this->ep, $read->byactivity, get_admin()->id));

        $syllabus = ep_get_syllabus($activity->id);
        $this->assertSame('Comprendre', $syllabus->objectives);
        $sessions = array_values(ep_get_syllabus_sessions($syllabus->id));
        $this->assertCount(2, $sessions);
        $this->assertSame('lecture', $sessions[0]->kind);
        $this->assertEquals($this->teacher->id, $sessions[0]->teacherid);
        $this->assertFalse(property_exists($sessions[0], 'sessiondate'));
        $this->assertNull($sessions[1]->teacherid);
        $this->assertSame('Dr Dupont', $sessions[1]->teachername);
        $this->assertEquals(3.5, $sessions[1]->duration);
    }

    /**
     * Crédits : étudiant retrouvé par adresse ou numéro, crédit tranché avec sa décision, crédit
     * en attente au premier rôle du circuit ; un doublon, un inconnu et un statut faux sont
     * écartés ; un import confirmé deux fois ne double rien.
     */
    public function test_credits_import(): void {
        global $DB;
        $csv = "Étudiant;Type;Intitulé;Année;ECTS demandés;ECTS retenus;Statut;Date de décision;Validé par;Commentaire\n"
            . "zoe.martin@example.com;sport;Rugby;2;2;1,5;validé;15/06/2025;prof@example.com;Équipe A\n"
            . "VET-001;engagement;Tutorat;3;1;;en attente;;;\n"
            . "VET-001;sport;rugby;2;2;;;;;\n"
            . "personne@example.com;sport;Golf;3;1;;;;;\n"
            . "VET-001;sport;Tennis;3;1;;peut-être;;;\n";
        $read = credits_importer::read($this->ep, $this->context, $csv);
        $this->assertCount(2, $read->rows);
        $this->assertCount(2, $read->errors);
        $this->assertCount(1, $read->warnings);

        $result = credits_importer::apply($this->ep, json_decode(json_encode($read->rows), true), get_admin()->id);
        $this->assertSame([2, 0], [$result->created, $result->skipped]);
        $rugby = $DB->get_record('ep_credit', ['userid' => $this->student->id, 'name' => 'Rugby']);
        $this->assertSame(EP_STATUS_VALIDATED, (int) $rugby->status);
        $this->assertEquals(1.5, $rugby->retainedects);
        $this->assertEquals($this->teacher->id, $rugby->validatedby);
        $this->assertSame(EP_SOURCE_DEVE, $rugby->source);
        $this->assertEquals(make_timestamp(2025, 6, 15), $rugby->validatetime);
        $decision = $DB->get_record('ep_credit_decision', ['creditid' => $rugby->id]);
        $this->assertSame('validated', $decision->decision);
        $this->assertSame('Équipe A', $decision->comment);
        $tutorat = $DB->get_record('ep_credit', ['userid' => $this->student->id, 'name' => 'Tutorat']);
        $this->assertSame(EP_STATUS_PENDING, (int) $tutorat->status);
        $this->assertNotSame('', $tutorat->pendingrole);

        $again = credits_importer::apply($this->ep, $read->rows, get_admin()->id);
        $this->assertSame([0, 2], [$again->created, $again->skipped]);
        $this->assertSame(2, $DB->count_records('ep_credit', ['epid' => $this->ep->id]));
    }

    /**
     * Paramétrage : export puis import dans une autre activité (types, minimums, courriels,
     * supplément, catalogue avec syllabus, séances et prérequis) ; un second import met à jour
     * sans dupliquer ; un fichier d'un autre format est refusé.
     */
    public function test_settings_transfer(): void {
        global $DB;
        ep_set_year_requirement($this->ep->id, 3, 12);
        $type = ep_get_type_by_code($this->ep->id, EP_TYPE_SPORT);
        $type->maxects = 4;
        ep_save_type($this->ep->id, $type);
        ep_set_notification_template($this->ep->id, 'creditdecided', true, 'Décision', 'Bonjour {fullname}');
        ep_set_supplement_text($this->ep->id, 'qualification', 'fr', 'Diplôme d\'État de docteur vétérinaire');
        $first = $this->gen->create_activity($this->ep, ['code' => 'A1', 'name' => 'Premier']);
        $second = $this->gen->create_activity($this->ep, ['code' => 'A2', 'name' => 'Second', 'prerequisites' => [$first->id]]);
        ep_set_activity_teachers($second->id, [$this->teacher->id]);
        ep_publish_syllabus_directly($second, (object) ['objectives' => 'Objectif'], [
            ['title' => 'Séance', 'kind' => 'lecture', 'duration' => 2, 'teacherid' => $this->teacher->id],
        ], get_admin()->id);

        $settings = json_decode(json_encode(ep_export_settings($this->ep, true)), true);
        $this->assertSame(EP_SETTINGS_FORMAT, $settings['format']);
        $this->assertSame(['prof@example.com'], $settings['activities'][1]['teachers']);

        $othercourse = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_module('ep', ['course' => $othercourse->id]);
        $target = $DB->get_record('ep', ['id' => $target->id]);
        $parts = ['instance', 'types', 'yearrequirements', 'supplement', 'notifications', 'catalog'];
        $report = ep_import_settings($target, $settings, $parts, get_admin()->id);
        $this->assertSame(2, $report->created['activities']);
        $this->assertSame(1, $report->created['syllabi']);

        $this->assertEquals(12, ep_get_year_requirements($target->id)[3]);
        $this->assertEquals(4, ep_get_type_by_code($target->id, EP_TYPE_SPORT)->maxects);
        $this->assertSame('Décision', ep_get_notification_template($target->id, 'creditdecided')->subject);
        $this->assertSame(
            'Diplôme d\'État de docteur vétérinaire',
            $DB->get_field('ep_supplement_text', 'content', ['epid' => $target->id, 'section' => 'qualification', 'lang' => 'fr'])
        );
        $copy = $DB->get_record('ep_activity', ['epid' => $target->id, 'code' => 'A2']);
        $copyfirst = $DB->get_record('ep_activity', ['epid' => $target->id, 'code' => 'A1']);
        $this->assertSame([(int) $copyfirst->id], array_map('intval', ep_get_activity_prerequisites($copy->id)));
        $this->assertArrayHasKey($this->teacher->id, ep_get_activity_teachers($copy->id));
        $syllabus = ep_get_syllabus($copy->id);
        $this->assertSame('Objectif', $syllabus->objectives);
        $sessions = ep_get_syllabus_sessions($syllabus->id);
        $this->assertEquals($this->teacher->id, reset($sessions)->teacherid);

        $report = ep_import_settings($target, $settings, ['catalog'], get_admin()->id);
        $this->assertSame(2, $report->updated['activities']);
        $this->assertSame(2, $DB->count_records('ep_activity', ['epid' => $target->id]));

        $this->expectException(\moodle_exception::class);
        ep_import_settings($target, ['format' => 99], $parts, get_admin()->id);
    }

    /**
     * Export complet puis restauration dans l'activité d'un autre cours où l'étudiante est
     * inscrite : crédits et décisions, justificatifs, éléments du supplément et preuves, décisions
     * d'année, suppléments émis ; une seconde restauration n'ajoute rien ; l'étudiant absent du
     * cours cible est signalé.
     */
    public function test_archive_export_and_restore(): void {
        global $DB;
        $fs = get_file_storage();
        $credit = $this->gen->create_validated_credit($this->ep, $this->student->id, EP_TYPE_SPORT, 2, 3);
        $fs->create_file_from_string(
            ['contextid' => $this->context->id, 'component' => 'mod_ep',
            'filearea' => EP_EVIDENCE_FILEAREA, 'itemid' => $credit->id, 'filepath' => '/', 'filename' => 'licence.pdf'],
            '%PDF-1.4'
        );
        $absent = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['email' => 'absent@example.com']);
        $this->gen->create_validated_credit($this->ep, $absent->id, EP_TYPE_SPORT, 1, 3);
        $categories = ep_get_supplement_categories($this->ep->id);
        $itemid = ep_create_supplement_item($this->ep, $this->student->id, reset($categories), (object) ['title' => 'Tutorat']);
        $fs->create_file_from_string(
            ['contextid' => $this->context->id, 'component' => 'mod_ep',
            'filearea' => EP_SUPPLEMENT_EVIDENCE_FILEAREA, 'itemid' => $itemid, 'filepath' => '/', 'filename' => 'preuve.pdf'],
            '%PDF-1.4'
        );
        ep_set_year_validation($this->ep, $this->student->id, 3, true, 'Année validée', get_admin()->id);
        $issue = ep_issue_supplement($this->ep, $this->context, $this->student->id, get_admin()->id, 'fr', true)->issue;

        $zippath = global_archive::export($this->ep, $this->context);
        $archive = global_archive::open($zippath);
        $this->assertSame(2, $archive->counts['credits']);
        $this->assertSame(1, $archive->counts['issues']);
        $this->assertFileExists($archive->dir . '/classeur.xlsx');

        $othercourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($this->student->id, $othercourse->id, 'student');
        $target = $this->getDataGenerator()->create_module('ep', ['course' => $othercourse->id]);
        $targetcontext = \context_module::instance($target->cmid);
        $target = $DB->get_record('ep', ['id' => $target->id]);

        $report = global_archive::restore($target, $targetcontext, $archive, ['types', 'data'], get_admin()->id);
        $this->assertSame([1, 1, 1, 1, 1], [$report->credits, $report->items, $report->years, $report->issues, $report->skipped]);
        $this->assertCount(1, $report->warnings);
        $restored = $DB->get_record('ep_credit', ['epid' => $target->id, 'userid' => $this->student->id]);
        $this->assertEquals(2, $restored->retainedects);
        $this->assertTrue($fs->file_exists($targetcontext->id, 'mod_ep', EP_EVIDENCE_FILEAREA, $restored->id, '/', 'licence.pdf'));
        $item = $DB->get_record('ep_supplement_item', ['epid' => $target->id]);
        $this->assertTrue($fs->file_exists(
            $targetcontext->id,
            'mod_ep',
            EP_SUPPLEMENT_EVIDENCE_FILEAREA,
            $item->id,
            '/',
            'preuve.pdf'
        ));
        $copy = $DB->get_record('ep_supplement_issue', ['epid' => $target->id]);
        $this->assertSame($issue->verifycode, $copy->verifycode);
        $this->assertNotSame($issue->number, $copy->number);
        $this->assertNotNull(ep_get_supplement_issue_file($targetcontext, $copy->id));
        $this->assertSame('Année validée', $DB->get_field('ep_year_validation', 'comment', ['epid' => $target->id]));

        $again = global_archive::restore($target, $targetcontext, global_archive::open($zippath), ['data'], get_admin()->id);
        $this->assertSame([0, 0, 0, 0], [$again->credits, $again->items, $again->years, $again->issues]);
        $this->assertSame(1, $DB->count_records('ep_credit', ['epid' => $target->id]));
        $this->assertSame(2, $DB->count_records('ep_import_log', ['epid' => $target->id, 'kind' => 'archive']));
    }

    /**
     * Une archive sans les fichiers attendus est refusée.
     */
    public function test_archive_invalid(): void {
        $path = make_request_directory() . '/faux.zip';
        get_file_packer('application/zip')->archive_to_pathname(['lisezmoi.txt' => ['Bonjour']], $path);
        $this->expectException(\moodle_exception::class);
        global_archive::open($path);
    }
}
