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
 * Theme Dimensions - The chosen flavour, written before the layout runs
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\output;

use theme_dimensions\local\flavourchoice;

/**
 * Primes Boost Union's flavour cache with the person's chosen look before the page's header is drawn.
 *
 * Core dispatches this hook at the start of core_renderer::header(), before it includes the layout
 * file, and the layout is where Boost Union first asks which flavour applies (its navbar include, then
 * the renderer's favicon, logos and body class, then the stylesheet URLs in the head). Boost Union
 * memoises that answer for the rest of the request, so the prime must come first: no earlier hook of
 * Boost Union or of this theme asks. Registered at a high priority for the same reason.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_http_headers {
    /**
     * Write the chosen flavour for the page whose header is starting.
     *
     * @param \core\hook\output\before_http_headers $hook The hook.
     * @return void
     */
    public static function callback(\core\hook\output\before_http_headers $hook): void {
        flavourchoice::prime($hook->renderer->get_page());
    }
}
