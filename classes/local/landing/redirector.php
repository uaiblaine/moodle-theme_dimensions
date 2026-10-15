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
 * The decision logic lives here, pure and unit-testable; the after_config
 * hook callback only extracts the request shape (script, parameters) and
 * performs the redirect. Old links, the course listings' enrolment icons and
 * Moodle's own require_login() bounce all funnel through enrol/index.php, so
 * forwarding it is what makes the landing page the natural landing page of a
 * course that opted in.
 *
 * The ghost decisions live here too, for the same reason: {@see is_ghosted()}
 * answers over a COURSE and {@see is_category_ghosted()} over a CATEGORY, and
 * the same after_config callback asks both before any forwarding.
 *
 * {@see category_page_target()} is the category's counterpart of the enrolment
 * forward: a VISITOR who reaches the showcase's own address is sent to the
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
     * True when the course is unlisted and the CURRENT user is none of
     * enrolled, awaiting a decision on an application, staff of the course, or
     * able to enrol - the predicate local_unlistedcourses owns. Checked before
     * the landing page forward so that an unlisted course which also opted into a
     * landing page does not show that landing page to somebody who must not know the
     * course exists.
     *
     * Visitors are deliberately NOT ghosted: they are not logged in, so the
     * predicate can only answer "no" for them, and ghosting would strand
     * somebody who would be eligible the moment they signed in. The standard
     * pages send them through the login and this decision is taken again on
     * the way back.
     *
     * Fails open when the predicate class is missing: nobody is ghosted. The
     * opposite of {@see publicaccess::is_public()}, which fails closed, and on
     * purpose - see that class for why the two must differ.
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
     * Whether this request must be sent to the generic "not available" page over its CATEGORY.
     *
     * The exact shape of {@see is_ghosted()}, one level up: true when the category is unlisted
     * and the CURRENT user holds none of the relationships local_unlistedcourses recognises
     * with it - a cohort at the category's own context, a role at it or above it, or the
     * capability to see hidden categories there. The category id 0 is the top-level
     * pseudo-category and is never ghosted: it holds no row of its own, so asking the
     * predicate about it would fail closed on a path that does not exist.
     *
     * Visitors are deliberately NOT ghosted, for the reason {@see is_ghosted()} gives: they
     * are not logged in, so the predicate can only answer "no" for them, and ghosting would
     * strand somebody who would be eligible the moment they signed in.
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
     * Null means "leave the request alone", and core's forcelogin wall then answers as it
     * always did: the id is no category's, somebody is logged in - the guest account
     * included, which lands on the showcase like any other session - or the category is
     * not one the site serves anonymously. Otherwise the public page, carrying whatever
     * the request asked the showcase for, so a shared address keeps its search and its page.
     *
     * The id 0 is the root of the course index and is a real answer here:
     * {@see categorypublicaccess::is_public()} owns whether the site serves it, so the guard
     * below refuses a negative id and nothing else. Contrast {@see is_category_ghosted()},
     * whose `<= 0` stays: the pseudo-category is never GHOSTED, because it holds no row and
     * asking the predicate about it would fail closed on a path no category has.
     *
     * Never throws: the predicate behind {@see categorypublicaccess::is_public()} answers
     * false for an id that is not there rather than raising, which is what lets this run in
     * an after_config callback over a parameter a visitor typed.
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
