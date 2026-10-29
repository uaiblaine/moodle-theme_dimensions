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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Theme Dimensions - Hook callback registrations
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\after_config::class,
        'callback' => 'theme_dimensions\local\hook\after_config::callback',
        'priority' => 0,
    ],
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => 'theme_dimensions\local\hook\course\after_form_definition::callback',
        'priority' => 0,
    ],
    [
        'hook' => \core_course\hook\after_form_validation::class,
        'callback' => 'theme_dimensions\local\hook\course\after_form_validation::callback',
        'priority' => 0,
    ],
    [
        'hook' => \core_course\hook\after_form_definition_after_data::class,
        'callback' => 'theme_dimensions\local\hook\course\after_form_definition_after_data::callback',
        'priority' => 0,
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => 'theme_dimensions\local\hook\course\after_form_submission::callback',
        'priority' => 0,
    ],
    [
        'hook' => \core_course\hook\before_course_deleted::class,
        'callback' => 'theme_dimensions\local\hook\course\before_course_deleted::callback',
        'priority' => 0,
    ],
    // The chosen flavour has to reach Boost Union's cache before anything of the page asks for a
    // flavour, and Boost Union keeps its first answer for the rest of the request.
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => 'theme_dimensions\local\hook\output\before_http_headers::callback',
        'priority' => 1000,
    ],
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => 'theme_dimensions\local\hook\output\before_standard_head_html_generation::callback',
        'priority' => 0,
    ],
    // Optional: without block_compass this hook class does not exist and is never dispatched.
    [
        'hook' => \block_compass\hook\course_badges::class,
        'callback' => \theme_dimensions\local\hook\block_compass\course_badges::class . '::answer',
    ],
];
