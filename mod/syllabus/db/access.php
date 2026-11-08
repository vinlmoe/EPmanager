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

/**
 * Capacités de mod_syllabus.
 *
 * Les responsables d'un module (syllabus_teacher) rédigent son syllabus, posent leurs questions
 * d'évaluation et en voient les résultats : ce droit tient à la désignation, la capacité
 * mod/syllabus:edit n'en est que la condition. La DEVE (mod/syllabus:manage) peut tout, dans
 * tous les modules où elle a ce rôle.
 *
 * @package   mod_syllabus
 * @copyright 2026 Sébastien Lefebvre
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Ajouter l'activité à un cours.
    'mod/syllabus:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => ['editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],
    // Lire la fiche publiée du module.
    'mod/syllabus:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'guest' => CAP_ALLOW, 'student' => CAP_ALLOW, 'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW,
        ],
    ],
    // Être désigné responsable : rédiger le syllabus, poser des questions, voir les résultats.
    'mod/syllabus:edit' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['teacher' => CAP_ALLOW, 'editingteacher' => CAP_ALLOW, 'manager' => CAP_ALLOW],
    ],
    // DEVE : responsables, revue et publication, fenêtre d'évaluation, tous les résultats.
    'mod/syllabus:manage' => [
        'riskbitmask' => RISK_XSS | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    // Étudiant : évaluer le module pendant la fenêtre d'évaluation.
    'mod/syllabus:evaluate' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => ['student' => CAP_ALLOW],
    ],
];
