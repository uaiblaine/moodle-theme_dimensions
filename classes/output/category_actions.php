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
 * Theme Dimensions - The manager's actions of a category, without the category selector
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\output\action_menu;
use core\output\action_menu\link_secondary;

/**
 * Core's category action bar, asked only for the action menu it builds for the category's managers.
 *
 * Core's export builds three things: a selector naming every category on the
 * site, a search form, and the menu of management links (add a course, add a
 * subcategory, manage courses, course requests). The showcase draws its own
 * search and must never print the selector, which would name unlisted
 * categories (this theme's override of core's action bar template drops it
 * too). So it asks for the menu alone, through core's own capability checks,
 * and skips the walk over the whole category tree the selector costs.
 *
 * One item is this theme's own: the category appearance editor, also reachable
 * from the hero's pencil and from the category's settings navigation, gated on
 * the same capability as both. It comes after core's options so the menu a
 * manager knows keeps its order.
 *
 * render_actions() checks neither the kill switch nor the category's
 * visibility: its one caller,
 * {@see \theme_dimensions\output\core\course_renderer::course_category()},
 * has passed those guards already.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_actions extends \core_course\output\category_action_bar {
    /**
     * The rendered action menu, or '' when the viewer may manage nothing here.
     *
     * Mirrors the menu block of core's export_for_template() for its
     * additionaloptions key, plus the appearance editor's entry for a viewer
     * holding moodle/category:manage.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return string Final HTML.
     */
    public function render_actions(\core\output\renderer_base $output): string {
        global $CFG;

        // Core's method calls get_category_or_system_context(), a plain function of course/lib.php
        // that course/index.php happens to have required; a page rendered from anywhere else has not.
        require_once($CFG->dirroot . '/course/lib.php');
        $additionaloptions = $this->get_additional_category_options();
        $options = $additionaloptions['options'] ?? [];

        $categoryid = (int) $this->category->id;
        $context = \core\context\coursecat::instance($categoryid);
        if (has_capability('moodle/category:manage', $context)) {
            $options[] = [
                'url' => new \core\url('/theme/dimensions/categoryhero.php', ['id' => $categoryid]),
                'string' => get_string('categoryhero_nav', 'theme_dimensions'),
            ];
        }
        if (empty($options)) {
            return '';
        }
        $actionmenu = new action_menu();
        $label = get_string('moreactions');
        $actionmenu->set_kebab_trigger($label);
        /* The trigger is an icon-only control of the filter bar: it names itself in the bar's own tooltip
           (data-dim-tip, drawn by the stylesheet after its delay) and never in a title, whose tooltip the browser
           would draw beside it. Its accessible name is core's visually hidden word inside it. */
        unset($actionmenu->triggerattributes['title']);
        $actionmenu->triggerattributes['data-dim-tip'] = $label;
        foreach ($options as $option) {
            $actionmenu->add(new link_secondary($option['url'], null, $option['string']));
        }
        return $output->render($actionmenu);
    }
}
