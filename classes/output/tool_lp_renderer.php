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
 * Theme Dimensions - The tool_lp renderer: the learning plans page with block_dimensions' plan cards
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\competency\plancards;
use tool_lp\output\plans_page;

/**
 * Adds the theme's plan cards to the first paint of the learning plans page, and passes every other call through.
 *
 * The class name is derived by the renderer factory for `$PAGE->get_renderer('tool_lp')` (theme_overridden_renderer_factory:
 * `theme_<name>\output\<component>_renderer`); neither Boost nor Boost Union has one. Only render_plans_page() is
 * overridden: it renders core's own export, decorated by {@see plancards::decorate()}, through the theme's copy of
 * tool_lp/plans_page. The template renders from core's context alone as well, which is what core's tool_lp/planactions
 * gives it after each row action; amd/src/plancards.js then puts the cards back.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_lp_renderer extends \tool_lp\output\renderer {
    /**
     * The learning plans page: core's data, the theme's cards on top.
     *
     * @param plans_page $page The page.
     * @return string The page's HTML.
     */
    public function render_plans_page(plans_page $page) {
        $data = $page->export_for_template($this);
        plancards::decorate($data, plancards::for_user((int) $data->userid, $this));

        return parent::render_from_template('tool_lp/plans_page', $data);
    }
}
