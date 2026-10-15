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
 * Theme Dimensions - Generic "not available" page
 *
 * Where a viewer who must not learn that a course or a category exists is sent
 * ({@see \theme_dimensions\local\landing\redirector::ghost_url()}).
 * It takes no parameters on purpose: an id in the URL would carry exactly the
 * fact the redirect exists to withhold, and would let the page be probed by
 * changing it. Nothing on this page is derived from the request.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = \core\context\system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/dimensions/unavailable.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_secondary_navigation(false);
$PAGE->add_body_class('theme_dimensions-unavailable');
$PAGE->set_title(get_string('unavailabletitle', 'theme_dimensions'));
$PAGE->set_heading('');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_dimensions/unavailable', [
    'heading' => get_string('unavailableheading', 'theme_dimensions'),
    'message' => get_string('unavailablemessage', 'theme_dimensions'),
    'homeurl' => (new moodle_url('/'))->out(false),
    'homelabel' => get_string('home'),
]);
echo $OUTPUT->footer();
