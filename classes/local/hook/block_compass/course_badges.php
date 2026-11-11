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
 * Theme Dimensions - The answer to block_compass's course_badges hook
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

/**
 * Gives the courses block_compass draws their crests, the ones the category showcase's own card draws.
 *
 * Registered in db/hooks.php for \block_compass\hook\course_badges. block_compass is optional: core lists
 * db/hooks.php from disk, and a registration naming a hook class that does not exist is kept as a string
 * and never dispatched, because nothing can construct the hook. Without the block nothing calls this.
 *
 * The rule is theme_dimensions_course_badges()'s, in lib.php, and the hook's contract is that function's
 * own: only the courses the current reader may see, whatever ids the block passes
 * ({@see \theme_dimensions\local\landing\publicfiles::viewer_may_see_many()}), one batch for every id,
 * nothing written to $SESSION. This class adds no rule of its own, so the filter stays in the one place
 * every caller goes through.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_badges {
    /**
     * Answers the hook: the crests of each asked course the reader may see, none for the others.
     *
     * A course with no crest is not answered, so the block treats it as one nobody answered. Whatever
     * throws is reported to developers and answered as nothing, as the block's own dispatch does: a crest
     * decorates a card, and a fault here must not take the block's response with it.
     *
     * @param \block_compass\hook\course_badges $hook The question, with the ids the block is about to draw.
     * @param callable|null $source Stands in for theme_dimensions_course_badges() in tests: course ids in,
     *     course id => crests out; null for the function itself.
     * @return void
     */
    public static function answer(\block_compass\hook\course_badges $hook, ?callable $source = null): void {
        global $CFG;

        try {
            // Core reads db/hooks.php from disk, so this runs on a site where the theme is not installed yet.
            if (!get_config('theme_dimensions', 'version')) {
                return;
            }
            require_once($CFG->dirroot . '/theme/dimensions/lib.php');
            $ids = $hook->get_courseids();
            $answers = $source ? $source($ids) : theme_dimensions_course_badges($ids);
            foreach ($answers as $courseid => $badges) {
                if ($badges) {
                    $hook->add_badges((int) $courseid, $badges);
                }
            }
        } catch (\Throwable $exception) {
            debugging(
                'theme_dimensions: the crests for block_compass could not be read: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }
}
