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
 * Theme Dimensions - The answer to block_compass's category_link hook
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\links;

/**
 * Sends the open-courses line's groups to the category showcase, filtered to the courses open in the band.
 *
 * Registered in db/hooks.php for \block_compass\hook\category_link. block_compass is optional: a
 * registration naming a hook class that does not exist is never dispatched, so without the block nothing
 * calls this.
 *
 * The answer is `?categoryid=N&chip=open&from=Y-m-d&to=Y-m-d` on the showcase, and the bare course index
 * for the root (0), the address {@see links::showcase()} gives. This class adds no visibility rule of its
 * own: the URL only filters a page that runs its own checks (who may see the category, which courses the
 * reader may see), so a category the reader cannot see gets that page's refusal, not a leak. It answers
 * nothing when the showcase is off, when the state chip is off (the page would drop it, so the block's
 * default address is the right one) and for a visitor or the guest account (their page draws no chips).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_link {
    /**
     * Answers the hook: the filtered showcase address of each asked category.
     *
     * Whatever throws is reported to developers and answered as nothing, as the block's own dispatch does:
     * a link's filter must not take the block's response with it.
     *
     * @param \block_compass\hook\category_link $hook The question, with the categories the block draws.
     * @param callable|null $source Stands in for the address builder in tests: category id and the query parameters in,
     *     \core\url out; null for {@see links::showcase()}.
     * @return void
     */
    public static function answer(\block_compass\hook\category_link $hook, ?callable $source = null): void {
        try {
            // Core reads db/hooks.php from disk, so this runs on a site where the theme is not installed yet.
            if (!get_config('theme_dimensions', 'version')) {
                return;
            }
            if (!population::enabled() || !population::state_sort()) {
                return;
            }
            $userid = $hook->get_userid();
            if ($userid <= 0 || isguestuser($userid)) {
                return;
            }
            foreach ($hook->get_categoryids() as $categoryid) {
                $params = ['chip' => 'open', 'from' => $hook->get_from(), 'to' => $hook->get_to()];
                $hook->set_url($categoryid, $source ? $source($categoryid, $params) : links::showcase($categoryid, $params, false));
            }
        } catch (\Throwable $exception) {
            debugging(
                'theme_dimensions: the category links for block_compass could not be built: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
