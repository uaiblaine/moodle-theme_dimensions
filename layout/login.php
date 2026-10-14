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
 * Theme Dimensions - Login layout
 *
 * Boost Union's login layout, except on the sign-in steps of other components that the theme
 * draws on auth_loginsteps' shell ({@see \theme_dimensions\local\loginshell}). There
 * the page's main content, the token core replaces once the page has run, goes into the
 * plugin's frame, and the frame into a bare page: no navbar, no Boost Union login panel.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$loginshell = \theme_dimensions\local\loginshell::for_page($PAGE);
if ($loginshell === null) {
    require($CFG->dirroot . '/theme/boost_union/layout/login.php');
    return;
}

$shell = new \auth_loginsteps\output\core_page_shell(
    $loginshell->screen,
    $loginshell->title,
    $loginshell->subtitle,
    $loginshell->glyph,
    $OUTPUT->main_content()
);
echo $OUTPUT->render_from_template('theme_dimensions/loginshell', [
    'output' => $OUTPUT,
    'shellhtml' => $OUTPUT->render_from_template('auth_loginsteps/shell/corepage', $shell->export_for_template($OUTPUT)),
]);
