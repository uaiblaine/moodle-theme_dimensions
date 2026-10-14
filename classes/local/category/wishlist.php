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
 * Theme Dimensions - The star of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use theme_dimensions\event\wishlist_updated;

/**
 * The one method behind theme_dimensions_toggle_wishlist, and the feature's one write.
 *
 * The row written is core's own favourite - component core_course, itemtype
 * courses, the course context, the user's own user context - byte-identical
 * to the one core's star writes, so a wishlisted course becomes a starred
 * course the moment the person enrols and nothing has to be migrated. Core's
 * own web service for it is never called from the theme: it instantiates a
 * course context and checks nothing else, which would be a way around every
 * gate in {@see guard}.
 *
 * Both directions are idempotent. user_favourite_service::delete_favourite()
 * throws on a row that is not there, so without the existence check an
 * unstar of something never starred would surface as a failed request over a
 * state the reader already had. Such calls are ordinary: two clicks racing, a
 * second tab, a reloaded POST, a client replaying an optimistic flip.
 *
 * One log line per real change: the event fires only when a favourite was
 * actually created or deleted. A call asking for the state the course is
 * already in returns that state and logs nothing, so one person's one
 * decision is never logged several times.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wishlist {
    /** @var string The component core files a starred course under: this feature writes core's row. */
    private const FAVOURITE_COMPONENT = 'core_course';

    /** @var string The item type core files a starred course under. */
    private const FAVOURITE_ITEMTYPE = 'courses';

    /**
     * Put the course in, or out of, this person's favourites.
     *
     * @param int $courseid The course, already admitted by {@see guard::course()}.
     * @param bool $state True to star it, false to unstar it.
     * @return bool The state the course is now in.
     */
    public static function toggle(int $courseid, bool $state): bool {
        global $USER;

        $coursecontext = \core\context\course::instance($courseid);
        $service = \core_favourites\service_factory::get_service_for_user_context(
            \core\context\user::instance((int) $USER->id)
        );
        $exists = $service->favourite_exists(
            self::FAVOURITE_COMPONENT,
            self::FAVOURITE_ITEMTYPE,
            $courseid,
            $coursecontext
        );

        if ($state && !$exists) {
            $service->create_favourite(
                self::FAVOURITE_COMPONENT,
                self::FAVOURITE_ITEMTYPE,
                $courseid,
                $coursecontext
            );
        } else if (!$state && $exists) {
            $service->delete_favourite(
                self::FAVOURITE_COMPONENT,
                self::FAVOURITE_ITEMTYPE,
                $courseid,
                $coursecontext
            );
        } else {
            // The state asked for is the state held: the answer is the same and nothing moved.
            return $state;
        }

        wishlist_updated::create([
            'objectid' => $courseid,
            'context' => $coursecontext,
            'other' => ['state' => $state],
        ])->trigger();

        return $state;
    }
}
