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
 * Theme Dimensions - Standard enrolment page forwarding decision
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\category\publicaccess as categorypublicaccess;
use theme_dimensions\local\landingfields;

/**
 * Decides whether a request to the standard enrolment page forwards to the landing page.
 *
 * The decisions live here, unit-testable; the after_config hook callback
 * ({@see \theme_dimensions\local\hook\after_config}) only reads the request
 * (script, parameters) and performs the redirect. Old links, the course
 * listings' enrolment icons and require_login()'s bounce of a non-enrolled user
 * all go through enrol/index.php, so forwarding it makes the landing page the
 * entry of a course that opted in.
 *
 * The ghost decisions live here too: {@see is_ghosted()} and its address twins
 * answer over a course, {@see is_category_ghosted()} over a category. On a
 * course address the callback asks the ghost before the enrolment forward.
 *
 * {@see category_page_target()} is the category's counterpart of the enrolment
 * forward: a visitor who reaches the showcase's own address is sent to the
 * public page of a public category rather than to core's forcelogin wall.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class redirector {
    /**
     * @var string Query parameter that keeps a request on the standard page when set to 0.
     *
     * The landing page's own guest-access link carries it: guest access with a
     * password lives in enrol_guest's widget on the standard page, and
     * forwarding that request would bounce the visitor straight back.
     */
    public const BYPASS_PARAM = 'landing';

    /**
     * Whether this request must be sent to the generic "not available" page.
     *
     * True when the course is unlisted and the current user is none of enrolled,
     * awaiting a decision on an application, staff of the course, or able to
     * enrol: local_unlistedcourses' access::is_course_discoverable(). Checked
     * before the landing page forward, so an unlisted course with a landing page
     * model does not show it to somebody who must not know the course exists.
     *
     * Visitors and the guest are not ghosted: the predicate can only answer "no"
     * for them, and ghosting would strand somebody who would be eligible once
     * signed in. The standard pages send them through the login, and this is
     * decided again on the way back.
     *
     * Fails open when the predicate class is missing: nobody is ghosted. The
     * opposite of {@see publicaccess::is_public()} on purpose; that class says why.
     *
     * @param int $courseid The course id from the request.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-open answer.
     * @return bool True when the request must be ghosted.
     */
    public static function is_ghosted(int $courseid, ?string $predicate = null): bool {
        if ($courseid <= 0 || $courseid == SITEID) {
            return false;
        }
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        $predicate = $predicate ?? \local_unlistedcourses\access::class;
        if (!class_exists($predicate)) {
            // The plugin is a declared dependency, but never fail a page over it.
            return false;
        }
        return !$predicate::is_course_discoverable($courseid);
    }

    /**
     * Whether a course address (/enrol/index.php, /course/info.php) must be sent to the "not available" page.
     *
     * {@see is_ghosted()}, plus a course that does not exist: whoever would be ghosted off an unlisted course
     * is ghosted off a missing id as well, so the ghost page cannot be told apart from core's "invalid course"
     * error and an id cannot be probed for an unlisted course behind it. A missing id is ghosted only where
     * {@see is_ghosted()} could ghost at all (logged in, not the guest, the predicate present), so a visitor
     * and a site without the plugin still get core's answer.
     *
     * @param int $courseid The course id from the request.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-open answer.
     * @return bool True when the request must be ghosted.
     */
    public static function is_address_ghosted(int $courseid, ?string $predicate = null): bool {
        global $DB;

        if ($courseid <= 0 || $courseid == SITEID) {
            return false;
        }
        if (!self::may_ghost($predicate ?? \local_unlistedcourses\access::class)) {
            return false;
        }
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return true;
        }
        return self::is_ghosted($courseid, $predicate);
    }

    /**
     * Whether /course/info.php?name=<shortname> must be sent to the "not available" page.
     *
     * The shortname twin of {@see is_address_ghosted()}: an unknown shortname answers as a missing id does,
     * or shortnames could be probed where ids cannot.
     *
     * @param string $shortname The shortname from the request.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-open answer.
     * @return bool True when the request must be ghosted.
     */
    public static function is_shortname_ghosted(string $shortname, ?string $predicate = null): bool {
        global $DB;

        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname]);
        if ($courseid) {
            return self::is_address_ghosted($courseid, $predicate);
        }
        return self::may_ghost($predicate ?? \local_unlistedcourses\access::class);
    }

    /**
     * Whether anybody in this session can be ghosted: logged in, not the guest, and the predicate present.
     *
     * @param string $predicate The predicate class.
     * @return bool
     */
    private static function may_ghost(string $predicate): bool {
        return isloggedin() && !isguestuser() && class_exists($predicate);
    }

    /**
     * Whether this request must be sent to the generic "not available" page over its category.
     *
     * The shape of {@see is_ghosted()}, one level up: true when the category is unlisted and the
     * current user holds none of the relationships local_unlistedcourses' category_access
     * recognises with it (a cohort at the category's own context, a role at it or above it, or
     * the capability to see hidden categories there). The id 0 is the top-level pseudo-category
     * and is never ghosted: it holds no row of its own, and the predicate would fail closed on it.
     *
     * Visitors and the guest are not ghosted, for the reason {@see is_ghosted()} gives.
     *
     * Fails open when the predicate class is missing: nobody is ghosted, mirroring
     * {@see is_ghosted()} rather than {@see publicaccess::is_public()}.
     *
     * @param int $categoryid The category id from the request.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-open answer.
     * @return bool True when the request must be ghosted.
     */
    public static function is_category_ghosted(int $categoryid, ?string $predicate = null): bool {
        if ($categoryid <= 0) {
            return false;
        }
        if (!isloggedin() || isguestuser()) {
            return false;
        }
        $predicate = $predicate ?? \local_unlistedcourses\category_access::class;
        if (!class_exists($predicate)) {
            // The plugin is a declared dependency, but never fail a page over it.
            return false;
        }
        return !$predicate::is_category_discoverable($categoryid);
    }

    /**
     * The URL of the generic "not available" page.
     *
     * @return \moodle_url The ghost page URL.
     */
    public static function ghost_url(): \moodle_url {
        return new \moodle_url('/theme/dimensions/unavailable.php');
    }

    /**
     * Where an anonymous hit on /course/index.php?categoryid=N goes, if anywhere.
     *
     * Null means "leave the request alone", and core's forcelogin wall answers: the id is no
     * category's, somebody is logged in (the guest account included, which gets the showcase like
     * any other session), or the site does not serve the category anonymously. Otherwise the public
     * page carrying the showcase parameters, so a shared address keeps its search and its page.
     *
     * The id 0 is the root of the course index and is a real answer here:
     * {@see categorypublicaccess::is_public()} decides whether the site serves it, so only a
     * negative id is refused below. {@see is_category_ghosted()} keeps `<= 0`, because the
     * pseudo-category is never ghosted.
     *
     * Never throws: the predicate behind {@see categorypublicaccess::is_public()} answers false
     * for an id that is not there, so this can run in after_config over a parameter a visitor typed.
     *
     * @param int $categoryid The category id from the request.
     * @param array $params The showcase parameters of the request to carry over.
     * @return \core\url|null The public page's address, or null to leave the request alone.
     */
    public static function category_page_target(int $categoryid, array $params = []): ?\core\url {
        if ($categoryid < 0) {
            return null;
        }
        if (isloggedin()) {
            return null;
        }
        if (!categorypublicaccess::is_public($categoryid)) {
            return null;
        }
        return links::category_page($categoryid, $params);
    }

    /**
     * The standard enrolment page of a course, kept there rather than forwarded to the landing page.
     *
     * Where guest access behind a key is entered: the key form is enrol_guest's widget on the
     * standard page, which the landing page does not draw, so both the landing page's guest button and a
     * card's "Enter with key" send the reader here with {@see BYPASS_PARAM} set to 0.
     *
     * @param int $courseid The course.
     * @return \moodle_url The address.
     */
    public static function standard_page(int $courseid): \moodle_url {
        return new \moodle_url('/enrol/index.php', ['id' => $courseid, self::BYPASS_PARAM => 0]);
    }

    /**
     * The landing page URL a standard-enrolment-page request should forward to, if any.
     *
     * Null means "leave the request alone": the course did not opt into a
     * landing page model, the request carries an action the standard page must
     * process (enrol_self's action=enrol flow), the caller asked to stay
     * (BYPASS_PARAM=0), or the current user is actively enrolled - core sends
     * those to the course, which is the better destination; only the landing page's
     * own address keeps serving enrolled users.
     *
     * @param int $courseid The course id from the request.
     * @param string $action The action parameter of the request ('' when absent).
     * @param bool $bypass Whether the request asked to stay on the standard page.
     * @return \moodle_url|null The landing page URL, or null to leave the request alone.
     */
    public static function enrol_page_target(int $courseid, string $action, bool $bypass): ?\moodle_url {
        global $DB, $USER;

        if ($bypass || $action !== '' || $courseid <= 0 || $courseid == SITEID) {
            return null;
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            // Let the standard page raise its own invalid-course error.
            return null;
        }

        // The model alone: this runs from after_config, before any page context exists, and values()
        // would format the textareas through the text filters.
        if (!landingfields::model((int) $course->id)) {
            return null;
        }

        $context = \core\context\course::instance($course->id);
        if (is_enrolled($context, $USER, '', true)) {
            return null;
        }

        return links::page((int) $course->id);
    }
}
