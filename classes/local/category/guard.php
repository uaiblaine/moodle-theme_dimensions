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
 * Theme Dimensions - The shared guard of the showcase's web services
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_external\external_api;
use local_unlistedcourses\access;
use local_unlistedcourses\category_access;

/**
 * The context, the core gate and the plugin gate of every web service of the showcase, in one place.
 *
 * Shared by the three external functions so they cannot drift apart. The
 * order is a security property:
 *
 * 1. The context is derived from the id server-side, with a non-throwing
 *    lookup. A bare instance() defaults to MUST_EXIST and forwards that to
 *    get_record(), so an id nothing holds would throw
 *    dml_missing_record_exception - a different class and message from the
 *    deliberate refusal below, and so an enumeration oracle over every id.
 * 2. validate_context(), which also sets the page's context for anything
 *    that renders downstream.
 * 3. Core's visibility question, asked the way the page asks it.
 * 4. The plugin's, after it. local_unlistedcourses is a layer on top of core
 *    visibility, never a replacement: its predicates answer true wholesale
 *    when nothing is unlisted and for a site admin, so a core-hidden
 *    category or course passes them.
 * 5. One refusal for every miss: a nonexistent id, one core hides and one
 *    the plugin withholds raise the same exception with the same message.
 *
 * The context validated for a course is its category's, not the course's:
 * validate_context() ends in require_login($course, false, $cm, false, true),
 * which throws require_login_exception for anybody not enrolled in the course,
 * and the showcase exists to show courses the viewer is not enrolled in. The
 * right to see a course is held at the category
 * (moodle/category:viewcourselist, which can_view_course_info() asks for).
 *
 * The course gate asks the plugin's course predicate alone, the category gate
 * the category one, and the asymmetry is deliberate. filter_courses() already
 * withholds a course whose category is unlisted from anybody unrelated to it,
 * and keeps it for anybody related (an enrolment, an application, staff
 * standing); is_category_discoverable() knows nothing of enrolments, so asking
 * it as well would refuse a course the listing draws a star on. What the page
 * shows is what the write accepts; the read paths and {@see category()} still
 * ask the category term.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guard {
    /** @var string Component of both refusals. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * The one refusal for every category the showcase will not serve.
     *
     * The same key the page's own {@see unavailable_exception} uses, so the page and the web
     * services refuse a category alike.
     *
     * @var string
     */
    public const ERROR_CATEGORY = 'categoryunavailable';

    /** @var string The one refusal for every course it will not serve. */
    public const ERROR_COURSE = 'invalidcourseid';

    /**
     * The category this request may be served, or the one refusal.
     *
     * @param int $categoryid The category id the request named.
     * @return \core_course_category The category, admitted by both gates.
     * @throws \moodle_exception The one refusal, whichever step refused.
     */
    public static function category(int $categoryid): \core_course_category {
        if ($categoryid === 0) {
            /* The root of the course index, whose search is live. It is no category to withhold -
               every logged-in account opens it, and the caller has already refused the guest - so
               answering for it reveals nothing. Its context is the system one, which is what
               core_course_category::top() answers. What the root may list is decided by the
               population's own root gate, which a caller cannot skip. */
            external_api::validate_context(\core\context\system::instance());
            return \core_course_category::top();
        }
        $context = \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
        if ($context === false) {
            // An id nothing holds: refused exactly as a withheld one is, so it is not an oracle.
            throw self::refusal(self::ERROR_CATEGORY);
        }
        external_api::validate_context($context);

        try {
            // No alwaysreturnhidden: this is can_view_category(), the question course/index.php asks.
            $category = \core_course_category::get($categoryid, MUST_EXIST);
        } catch (\Throwable) {
            throw self::refusal(self::ERROR_CATEGORY);
        }

        if (!category_access::is_category_discoverable($categoryid)) {
            throw self::refusal(self::ERROR_CATEGORY);
        }

        return $category;
    }

    /**
     * The course this request may be served, or the one refusal.
     *
     * @param int $courseid The course id the request named.
     * @return \stdClass The course record, admitted by core's gate and the plugin's course term.
     * @throws \moodle_exception The one refusal, whichever step refused.
     */
    public static function course(int $courseid): \stdClass {
        global $DB;

        /* The site course is never on the showcase, and every gate below lets it through:
           can_view_course_info() returns true outright for SITEID, and its category is 0, which
           holds no row. Refused here, so the one write of this feature cannot target the front
           page. On a site with nothing unlisted this is the only refusal, because the plugin then
           answers true wholesale; guard_test pins that case. */
        if ($courseid == SITEID) {
            throw self::refusal(self::ERROR_COURSE);
        }

        $context = \core\context\course::instance($courseid, IGNORE_MISSING);
        if ($context === false) {
            throw self::refusal(self::ERROR_COURSE);
        }
        // The category context, never the course one: see the class docblock.
        external_api::validate_context($context->get_parent_context());

        $course = $DB->get_record('course', ['id' => $courseid], 'id, category, visible, fullname, shortname');
        if (!$course) {
            throw self::refusal(self::ERROR_COURSE);
        }
        if (!\core_course_category::can_view_course_info($course)) {
            throw self::refusal(self::ERROR_COURSE);
        }

        // The course term alone, never is_category_discoverable() as well: see the class docblock.
        if (!access::filter_courses([$courseid => $course])) {
            throw self::refusal(self::ERROR_COURSE);
        }

        return $course;
    }

    /**
     * The one refusal.
     *
     * Built rather than thrown so every call site reads as a throw and the
     * message has exactly one spelling.
     *
     * @param string $errorcode ERROR_CATEGORY or ERROR_COURSE.
     * @return \moodle_exception The refusal to throw.
     */
    private static function refusal(string $errorcode): \moodle_exception {
        return new \moodle_exception($errorcode, self::COMPONENT);
    }
}
