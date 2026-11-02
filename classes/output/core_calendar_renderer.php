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
 * Theme Dimensions - The core_calendar renderer: core's calendar blocks drawn with the theme's calendar
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\calendar\blocks;
use theme_dimensions\local\calendar\coreview;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/renderer.php');

/**
 * Replaces the content of block_calendar_month and block_calendar_upcoming, and passes every other call through.
 *
 * The class name is derived by the renderer factory for `$PAGE->get_renderer('core_calendar')`; neither Boost nor
 * Boost Union has one. Only render_from_template() is overridden: both blocks hand it their template and
 * calendar_get_view()'s data, which core has already computed and the theme discards (a renderer is called after
 * the data, so it cannot spare that cost). The decision is {@see blocks}'s; any refusal is core's own output, untouched.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_calendar_renderer extends \core_calendar_renderer {
    /**
     * Render a template: a calendar block's own content when the theme answers it, else core's.
     *
     * @param string $templatename The template.
     * @param array|\stdClass $context Its context.
     * @return string|bool
     */
    public function render_from_template($templatename, $context) {
        $kind = blocks::kind((string) $templatename, $context);
        if (
            $kind === null
            || !blocks::applies(blocks::enabled(), (int) $this->page->course->id, coreview::account(), coreview::gregorian())
        ) {
            return parent::render_from_template($templatename, $context);
        }

        $now = time();
        if ($kind === blocks::UPCOMING) {
            $upcoming = blocks::upcoming($now);
            $block = new calendar_block_upcoming($upcoming['items'], $upcoming['courses'], $now);
            return parent::render_from_template(calendar_block_upcoming::TEMPLATE, $block->export_for_template($this));
        }

        $month = blocks::month($now);
        $block = new calendar_block_month($month['month'], $month['today'], $month['firstday'], $month['state'], $month['answer']);
        $this->page->requires->strings_for_js(blocks::STRINGS, 'theme_dimensions');
        $this->page->requires->js_call_amd('theme_dimensions/calendarblock', 'init');
        return parent::render_from_template(calendar_block_month::TEMPLATE, $block->export_for_template($this));
    }
}
