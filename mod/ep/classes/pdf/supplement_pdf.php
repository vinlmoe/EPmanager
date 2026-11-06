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

namespace mod_ep\pdf;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ep/locallib.php');

/**
 * Supplément au diplôme d'un étudiant, au format du modèle européen (Commission européenne,
 * Conseil de l'Europe, UNESCO/CEPES) : préambule, puis les huit rubriques numérotées. Les
 * enseignements personnalisés validés sont portés en rubrique 4.3, les éléments ajoutés par
 * l'étudiant et validés en rubrique 6.1 ; la rubrique 7 porte la date, le signataire et le
 * cachet ; chaque page porte le numéro du supplément et son code de vérification.
 *
 * Reprend l'en-tête, le pied de page et le logo des éditions PDF de l'activité (voir
 * syllabus_pdf). Un aperçu, avant émission, porte un filigrane « non certifié ».
 *
 * @package   mod_ep
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class supplement_pdf extends syllabus_pdf {
    /** @var string Langue du document : fr ou en. */
    protected $lang;

    /** @var string Numéro du supplément, imprimé en pied de chaque page. */
    protected $number = '';

    /** @var string Code de vérification, imprimé en pied de chaque page. */
    protected $verifycode = '';

    /** @var bool Aperçu non certifié : filigrane sur chaque page. */
    protected $preview = false;

    /**
     * Prépare le document dans la langue demandée.
     *
     * @param \stdClass $ep
     * @param \context_module $context
     * @param string $lang fr ou en.
     */
    public function __construct(\stdClass $ep, \context_module $context, $lang = 'fr') {
        parent::__construct($ep, $context);
        $this->lang = $lang === 'en' ? 'en' : 'fr';
    }

    /**
     * Chaîne de langue du module dans la langue du document, et non dans celle de l'utilisateur qui
     * l'émet : un supplément en anglais est entièrement en anglais.
     *
     * @param string $key
     * @param mixed $a
     * @return string
     */
    protected function str($key, $a = null): string {
        return get_string_manager()->get_string($key, 'mod_ep', $a, $this->lang);
    }

    /**
     * Pied de page : celui de l'activité, complété du numéro et du code de vérification.
     *
     * @return void
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        parent::Footer();
        if ($this->number === '') {
            return;
        }
        $this->SetY(-12);
        $this->SetFont(self::FONT, '', 7);
        $this->Cell(0, 4, $this->str('supplementnumber', $this->number) . '   '
            . $this->str('supplementverifycode', substr($this->verifycode, 0, 16)), 0, 0, 'C');
    }

    /**
     * Filigrane de l'aperçu, en diagonale, sous le contenu de chaque page.
     *
     * @return void
     */
    protected function watermark(): void {
        $this->StartTransform();
        $this->SetTextColor(220, 220, 220);
        $this->SetFont(self::FONT, 'B', 40);
        $this->Rotate(45, 105, 150);
        $this->Text(20, 150, $this->str('supplementpreviewwatermark'));
        $this->StopTransform();
        $this->SetTextColor(0, 0, 0);
        $this->SetFont(self::FONT, '', 10);
    }

    /**
     * En-tête de page : celui de l'activité, puis le filigrane d'un aperçu. Le dessiner ici, et non
     * après l'ajout de la page, laisse TCPDF rétablir lui-même la position d'écriture : les sauts de
     * page automatiques au milieu d'un tableau restent justes.
     *
     * @return void
     */
    public function Header() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        parent::Header();
        if ($this->preview) {
            $this->watermark();
        }
    }

    /**
     * Rubrique numérotée : titre et texte, ou ligne vide pour signaler qu'elle n'est pas renseignée.
     *
     * @param string $number
     * @param string $label
     * @param string $text Texte brut.
     * @return string HTML
     */
    protected function field($number, $label, $text): string {
        $value = trim((string) $text) !== '' ? nl2br(s(trim($text))) : '—';
        return '<tr><td width="30%" style="background-color:' . self::LABEL_BACKGROUND . '"><b>' . s($number) . '</b> '
            . s($label) . '</td><td width="70%">' . $value . '</td></tr>';
    }

    /**
     * Titre d'une des huit parties.
     *
     * @param int $part
     * @return string HTML
     */
    protected function part($part): string {
        return '<h3>' . $part . '. ' . s($this->str('supplementpart' . $part)) . '</h3>';
    }

    /**
     * Compose le supplément d'un étudiant.
     *
     * @param \stdClass $data Contenu, voir ep_build_supplement().
     * @param string $number Numéro (vide pour un aperçu).
     * @param string $verifycode Code de vérification (vide pour un aperçu).
     * @param int $time Date d'émission.
     * @param bool $preview Aperçu non certifié.
     * @return void
     */
    public function add_supplement(\stdClass $data, $number, $verifycode, $time, $preview = false): void {
        $this->number = (string) $number;
        $this->verifycode = (string) $verifycode;
        $this->preview = (bool) $preview;
        $sections = ep_supplement_sections();
        $texts = $data->texts;
        $label = fn($key) => $this->str('supplementsection_' . $key);
        $dateformat = $this->lang === 'en' ? '%d %B %Y' : '%d %B %Y';
        $date = fn($t) => $t ? userdate($t, $dateformat, 99, false) : '—';
        $table = fn($rows) => '<table cellpadding="4" border="0.3">' . $rows . '</table>';

        $this->AddPage();
        $title = $this->lang === 'en' ? $this->str('supplementtitleen') : $this->str('supplementtitle') . ' — '
            . $this->str('supplementtitleen');
        $html = '<h1 style="text-align:center">' . s($title) . '</h1>';
        $html .= '<p style="font-size:8pt;text-align:justify">' . s($this->str('supplementpreamble')) . '</p>';

        // 1. Titulaire.
        $html .= $this->part(1) . $table(
            $this->field('1.1', $this->str('supplementlastname'), $data->identity->lastname)
            . $this->field('1.2', $this->str('supplementfirstname'), $data->identity->firstname)
            . $this->field('1.3', $this->str('supplementbirthdate'), $date($data->identity->birthdate))
            . $this->field('1.4', $this->str('supplementidnumber'), $data->identity->idnumber)
        );

        // 2, 3 : textes de l'établissement.
        foreach (
            [2 => ['qualification', 'fieldofstudy', 'institution', 'institutionadmin', 'languages'],
                3 => ['level', 'length', 'access']] as $part => $keys
        ) {
            $rows = '';
            foreach ($keys as $key) {
                $rows .= $this->field($sections[$key], $label($key), $texts[$key]);
            }
            $html .= $this->part($part) . $table($rows);
        }

        // 4. Programme : organisation, acquis, puis les EP validés (4.3), notation, classification.
        $html .= $this->part(4) . $table(
            $this->field('4.1', $label('studymode'), $texts['studymode'])
            . $this->field('4.2', $label('programmeoutcomes'), $texts['programmeoutcomes'])
        );
        $html .= '<h4>4.3 ' . s($this->str('supplementprogrammedetails')) . '</h4>';
        if ($data->credits) {
            $rows = '<tr style="background-color:' . self::LABEL_BACKGROUND . '"><th width="12%">'
                . s($this->str('activitycode')) . '</th><th width="40%">' . s($this->str('catalogactivity'))
                . '</th><th width="18%">' . s($this->str('type')) . '</th><th width="10%">' . s($this->str('studyyear'))
                . '</th><th width="8%">ECTS</th><th width="12%">' . s($this->str('grade')) . '</th></tr>';
            foreach ($data->credits as $credit) {
                $grade = $credit->grade !== null
                    ? ep_format_ects($credit->grade) . ' / ' . ep_format_ects($credit->grademax)
                    : $this->str('supplementvalidatedgrade');
                $rows .= '<tr><td width="12%">' . s($credit->code) . '</td><td width="40%">' . s($credit->name)
                    . '</td><td width="18%">' . s($this->plain($credit->type)) . '</td><td width="10%">'
                    . ($credit->studyyear ?: '—') . '</td><td width="8%">' . ep_format_ects($credit->ects)
                    . '</td><td width="12%">' . s($grade) . '</td></tr>';
            }
            $html .= '<table cellpadding="3" border="0.3">' . $rows . '</table>';
        }
        $html .= '<p><b>' . s($this->str('supplementtotalects', ep_format_ects($data->totalects))) . '</b></p>';
        if ($data->years) {
            $lines = [];
            foreach ($data->years as $year => $decision) {
                $lines[] = $decision->decision
                    ? $this->str('supplementyearvalidated', (object) ['year' => $year, 'date' => $date($decision->date),
                        'ects' => ep_format_ects($decision->ects)])
                    : $this->str('supplementyearnotvalidated', (object) ['year' => $year]);
            }
            $html .= '<p>' . s($this->str('supplementyears')) . ' : ' . s(implode(' ; ', $lines)) . '</p>';
        }
        $html .= $table(
            $this->field('4.4', $label('gradingscheme'), $texts['gradingscheme'])
            . $this->field('4.5', $label('classification'), $texts['classification'])
        );

        // 5. Fonction du diplôme.
        $html .= $this->part(5) . $table(
            $this->field('5.1', $label('furtherstudy'), $texts['furtherstudy'])
            . $this->field('5.2', $label('professional'), $texts['professional'])
        );

        // 6. Informations complémentaires : texte d'introduction, puis les éléments validés.
        $html .= $this->part(6);
        $items = '';
        if (trim($texts['additionalinfo']) !== '') {
            $items .= '<p>' . nl2br(s(trim($texts['additionalinfo']))) . '</p>';
        }
        if ($data->items) {
            $items .= '<ul>';
            foreach ($data->items as $item) {
                $period = $item->periodstart
                    ? ' (' . $date($item->periodstart) . ($item->periodend ? ' – ' . $date($item->periodend) : '') . ')'
                    : '';
                $items .= '<li><b>' . s($item->category) . '</b> : ' . s($item->title) . s($period)
                    . (trim($item->description) !== '' ? '<br/>' . nl2br(s(trim($item->description))) : '') . '</li>';
            }
            $items .= '</ul>';
        }
        $html .= $table('<tr><td width="30%" style="background-color:' . self::LABEL_BACKGROUND . '"><b>6.1</b> '
            . s($label('additionalinfo')) . '</td><td width="70%">' . ($items !== '' ? $items : '—') . '</td></tr>'
            . $this->field('6.2', $label('othersources'), $texts['othersources']));

        // 7. Certification.
        $html .= $this->part(7) . $table(
            $this->field('7.1', $this->str('supplementcertdate'), $preview ? '—' : $date($time))
            . $this->field('7.2', $label('signatoryname'), $texts['signatoryname'])
            . $this->field('7.3', $label('signatorytitle'), $texts['signatorytitle'])
            . '<tr><td width="30%" style="background-color:' . self::LABEL_BACKGROUND . '"><b>7.4</b> '
            . s($this->str('supplementcertstamp')) . '</td><td width="70%" height="60">'
            . $this->stamp_html() . '</td></tr>'
        );

        // 8. Système national.
        $html .= $this->part(8) . '<p style="font-size:8pt">' . nl2br(s(trim($texts['national'] ?: '—'))) . '</p>';
        if (!$preview && $this->verifycode !== '') {
            $url = (new \moodle_url('/mod/ep/supplement_verify.php'))->out(false);
            $html .= '<p style="font-size:8pt">' . s($this->str('supplementnumber', $this->number)) . '<br/>'
                . s($this->str('supplementverifycode', $this->verifycode)) . '<br/>'
                . s($this->str('supplementverifyinfo', $url)) . '</p>';
        }

        $this->writeHTML($html, true, false, true, false, '');
    }

    /**
     * Image du cachet (zone supplementstamp), copiée dans un fichier temporaire pour TCPDF.
     *
     * @return string HTML, vide s'il n'y a pas de cachet.
     */
    protected function stamp_html(): string {
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            'mod_ep',
            EP_SUPPLEMENT_STAMP_FILEAREA,
            0,
            'itemid, filepath, filename',
            false
        );
        foreach ($files as $file) {
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
            $extension = $extensions[$file->get_mimetype()] ?? null;
            if ($extension === null) {
                continue;
            }
            $path = make_request_directory() . '/stamp.' . $extension;
            $file->copy_content_to($path);
            return '<img src="' . s($path) . '" height="55" />';
        }
        return '';
    }
}
