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
 * Theme Dimensions - The tool_lp renderer: the learning plans page's cards and the course competencies page's extras
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\competency\coursecompetencies;
use theme_dimensions\local\competency\plancards;
use tool_lp\output\course_competencies_page;
use tool_lp\output\plans_page;

/**
 * Adds the theme's plan cards to the first paint of the learning plans page, and passes every other call through.
 *
 * The class name is derived by the renderer factory for `$PAGE->get_renderer('tool_lp')` (theme_overridden_renderer_factory:
 * `theme_<name>\output\<component>_renderer`); neither Boost nor Boost Union has one. Two methods are overridden:
 * render_plans_page() renders core's own export, decorated by {@see plancards::decorate()}, through the theme's copy of
 * tool_lp/plans_page. The template renders from core's context alone as well, which is what core's tool_lp/planactions
 * gives it after each row action; amd/src/plancards.js then puts the cards back. render_course_competencies_page()
 * does the same for the course competencies page with {@see coursecompetencies::decorate()}, the copy of
 * tool_lp/course_competencies_page and amd/src/coursecompetencies.js.
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

    /**
     * The course competencies page: core's data, the theme's extras on top.
     *
     * Core's export first (it runs core's access rule and, for a learner, inserts core's own rows), then the extras for
     * the competencies it exported, written by {@see coursecompetencies::decorate()}. The site course gets none.
     *
     * @param course_competencies_page $page The page.
     * @return string The page's HTML.
     */
    public function render_course_competencies_page(course_competencies_page $page) {
        $data = $page->export_for_template($this);
        $courseid = (int) $data->courseid;
        if ($courseid !== (int) SITEID) {
            $ids = [];
            foreach ($data->competencies as $row) {
                $ids[] = (int) $row['competency']->id;
            }
            coursecompetencies::decorate($data, coursecompetencies::for_viewer(get_course($courseid), $ids));
        }

        return parent::render_from_template('tool_lp/course_competencies_page', $data);
    }
}
