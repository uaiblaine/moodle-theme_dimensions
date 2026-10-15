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
 * Theme Dimensions - Shortlink handler
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions;

use theme_dimensions\local\landing\links;

/**
 * Resolves this theme's short codes for core's /p/{shortcode} route.
 *
 * Core finds this class by name (\core\shortlink::get_shortlink_handler() asks the
 * dependency injection container for "<component>\shortlink_handler"), so nothing
 * registers it. It answers the two link types this theme mints: the landing page's, with the
 * page's canonical address, and the guest entry's, with that address asking for the guest
 * entry (links::guest_page()). Rows hold a course id, never an address, so a code keeps
 * resolving whether the page is served by landing.php or by the route.
 *
 * A code whose course is gone answers null, which the route turns into a not-found page.
 * This is the fallback behind the course deletion hook, which forgets a course's codes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortlink_handler implements \core\shortlink_handler_interface {
    #[\Override]
    public function get_valid_linktypes(): array {
        return [links::LINKTYPE, links::LINKTYPE_GUEST];
    }

    #[\Override]
    public function process_shortlink(string $type, string $identifier): ?\core\url {
        global $DB;

        if ($type !== links::LINKTYPE && $type !== links::LINKTYPE_GUEST) {
            return null;
        }
        if (!ctype_digit($identifier) || (int) $identifier <= 0) {
            return null;
        }
        if (!$DB->record_exists('course', ['id' => (int) $identifier])) {
            return null;
        }
        if ($type === links::LINKTYPE_GUEST) {
            return links::guest_page((int) $identifier);
        }
        return links::page((int) $identifier);
    }
}
