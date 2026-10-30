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
 * Theme Dimensions - The choice page: the person's look, without JavaScript
 *
 * A Look item of the user menu opens this page with its flavour preselected when the menu's client
 * module is not running; the module itself writes through the choose_flavour web service and never
 * comes here. Showing the page writes nothing: a GET that switched the look is the shape a link
 * prefetcher walks into, so the choice is made by the form's POST, which carries the session key.
 * Every decision is local\flavourchoice's; this file is guards, the form and a redirect.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_dimensions\local\flavourchoice;

$preselect = optional_param('flavour', null, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_login(null, false);
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$back = flavourchoice::back_url($returnurl);
$returnurl = $returnurl !== '' ? $back->out_as_local_url(false) : '';
$params = $returnurl !== '' ? ['returnurl' => $returnurl] : [];
$url = new moodle_url('/theme/dimensions/flavour.php', $params);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$title = get_string('lookmenu', 'theme_dimensions');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$menu = flavourchoice::menu($back);
if ($menu === null) {
    throw new \moodle_exception('flavourchoice_unavailable', 'theme_dimensions');
}

$options = [];
$marks = [];
$current = flavourchoice::SITE_DEFAULT;
foreach ($menu['items'] as $item) {
    $options[$item['id']] = $item['text'];
    if ($item['mark'] !== null) {
        $marks[$item['id']] = $OUTPUT->render_from_template('theme_dimensions/look_mark', $item['mark']);
    }
    if ($item['iscurrent']) {
        $current = $item['id'];
    }
}

$form = new \theme_dimensions\form\flavourchoice($url, ['options' => $options, 'marks' => $marks]);
if ($form->is_cancelled()) {
    redirect($back);
}
$recorded = flavourchoice::submitted($form);
if ($recorded) {
    redirect($back, get_string('flavourchoice_saved', 'theme_dimensions'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($recorded === false) {
    // The look left the list between the form and its submission.
    redirect($back, get_string('flavourchoice_unavailable', 'theme_dimensions'), null, \core\output\notification::NOTIFY_ERROR);
}

$selected = ($preselect !== null && array_key_exists($preselect, $options)) ? $preselect : $current;
$form->set_data(['flavour' => $selected, 'returnurl' => $returnurl]);

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
