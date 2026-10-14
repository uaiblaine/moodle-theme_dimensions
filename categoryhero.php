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
 * Theme Dimensions - The category appearance editor
 *
 * The hero of a category on the category showcase: the category's own description, layout,
 * image, colours, overlay, tagline, public description and public page text. Gated on
 * moodle/category:manage at the category (the theme declares no capability of its own), reached
 * from the pencil in the hero and from the category's own tabs.
 *
 * The two colour fields are plain text inputs that a manager can fill by hand; the module
 * loaded below decorates each with a native colour swatch, flags a value that is not a hex
 * colour while it is being typed and grades the pair live against WCAG. Nothing it does is a
 * guard - the form refuses an invalid colour and a pair under the floor whether it ran or not -
 * so the page is complete with JavaScript off.
 *
 * The category's own description is core's field and is saved through core's own API, which is
 * what fires course_category_updated and purges core's caches; everything else on this form is
 * this plugin's table.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use theme_dimensions\form\categoryhero as categoryhero_form;
use theme_dimensions\local\category\branding;

$id = required_param('id', PARAM_INT);

require_login();
$coursecat = core_course_category::get($id, MUST_EXIST, true);
$context = context_coursecat::instance($id);
require_capability('moodle/category:manage', $context);

$url = new moodle_url('/theme/dimensions/categoryhero.php', ['id' => $id]);
$categoryurl = new moodle_url('/course/index.php', ['categoryid' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url($categoryurl);
$PAGE->set_primary_active_tab('home');
$PAGE->set_secondary_active_tab('categoryappearance');
$title = get_string('categoryhero_nav', 'theme_dimensions');
$PAGE->set_title($title);
$PAGE->set_heading($coursecat->get_formatted_name());
$PAGE->navbar->add($title);

$form = new categoryhero_form($url->out(false), ['categoryid' => $id]);
if ($form->is_cancelled()) {
    redirect($categoryurl);
}
if ($data = $form->get_data()) {
    branding::save($id, $data, (int) $data->heroimage);
    /* Core's own writer for core's own field: it runs file_postupdate_standard_editor over the
       coursecat/description area, fires course_category_updated and purges the category caches.
       Only the description keys are handed over - update() acts on name, idnumber, theme,
       visible and parent whenever those keys are SET, and this form sets none of them. */
    $coursecat->update(
        (object) ['description_editor' => $data->description_editor],
        categoryhero_form::description_editor_options($id)
    );
    redirect(
        $categoryurl,
        get_string('categoryhero_saved', 'theme_dimensions'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$formdata = branding::form_data($id);
/* Core's own preparation of its own field, exactly as course/editcategory.php makes it: the
   draft area, item id 0, and the description format beside the text. The record it reads is the
   category's own, so a description saved on core's page is what this form opens with. */
$category = $DB->get_record('course_categories', ['id' => $id], '*', MUST_EXIST);
$category = file_prepare_standard_editor(
    $category,
    'description',
    categoryhero_form::description_editor_options($id),
    $context,
    'coursecat',
    'description',
    0
);
$formdata->description_editor = $category->description_editor;
$form->set_data($formdata);

$PAGE->requires->js_call_amd('theme_dimensions/categoryhero', 'init');
// The stylesheet of the swatch row and the contrast panel is scoped under this class, because
// scss/categoryhero.scss is concatenated into the theme's main sheet and reaches every page.
$PAGE->add_body_class('dim-categoryhero');

echo $OUTPUT->header();
echo $form->render();
echo $OUTPUT->footer();
