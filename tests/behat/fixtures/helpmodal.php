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
 * Theme Dimensions - Behat fixture: one long and one short help icon, drawn by core's own template
 *
 * The icons are core's help_icon template rendered with a hand-made context, so the page depends on no language
 * string and on no other plugin: the long text is a list no window can show inside the popover's cap.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');

defined('BEHAT_SITE_RUNNING') || die();

global $PAGE, $OUTPUT;

$PAGE->set_url('/theme/dimensions/tests/behat/fixtures/helpmodal.php');
require_login();
$PAGE->set_context(\core\context\system::instance());
$PAGE->set_title('Help dialogue test page');

$items = '';
for ($i = 1; $i <= 40; $i++) {
    $items .= '<li>Item ' . $i . ' of the long help</li>';
}
$icons = [
    'dimensions-help-long' => [
        'label' => 'Long setting',
        'text' => '<p>A help text longer than the popover can show.</p><ul>' . $items . '</ul>',
    ],
    'dimensions-help-short' => [
        'label' => 'Short setting',
        'text' => '<p>A short help text.</p>',
    ],
];

echo $OUTPUT->header();
foreach ($icons as $id => $icon) {
    echo '<p id="' . $id . '">' . $icon['label'] . ' ' . $OUTPUT->render_from_template('core/help_icon', [
        'text' => $icon['text'],
        'alt' => 'Help with ' . $icon['label'],
        'ltr' => !right_to_left(),
        'completedoclink' => '',
    ]) . '</p>';
}
echo $OUTPUT->footer();
