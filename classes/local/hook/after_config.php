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
 * Theme Dimensions - after_config hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook;

use theme_dimensions\local\calendar\coreview;
use theme_dimensions\local\calendar\ical;
use theme_dimensions\local\category\request;
use theme_dimensions\local\guesthome;
use theme_dimensions\local\landing\redirector;

/**
 * Ghosts what this user must not discover, forwards the enrolment page to the landing page, and points the guest home.
 *
 * after_config is the only moment early enough: enrol/index.php redirects on its own a few
 * lines in (visitors to the login page, enrolled users to the course). The callback reads the
 * request shape and delegates every decision to {@see redirector}, where the unit tests live.
 *
 * The request shapes read:
 * - every web request: the guest account's home page ({@see guesthome}), an in-memory override
 *   of defaulthomepage that must be in place before index.php or the navigation reads it.
 *   lib/setup.php dispatches after_config once the session has started, so isguestuser() reads
 *   the real session. It reads no $SCRIPT, because under the router $SCRIPT is /r.php;
 * - /enrol/index.php and /course/info.php: ghosted over the course they name, the first then
 *   forwarded to the landing page;
 * - /course/index.php?categoryid=N: a visitor is forwarded to the public page of a category the
 *   site serves anonymously; a logged-in user other than the guest account is ghosted over the
 *   category, so a guessed category URL answers as a guessed course one does instead of naming
 *   the category in the heading, breadcrumb and title. The two never collide: one is for visitors, the other for
 *   logged-in users;
 * - /calendar/view.php: forwarded to the theme's calendar page ({@see coreview});
 * - /calendar/export_execute.php: core's iCal feed, answered by the theme's copy ({@see ical})
 *   so the feed withholds what the page withholds. That script defines NO_MOODLE_COOKIES, but
 *   lib/setup.php still dispatches after_config for it.
 *
 * The category and calendar branches read $SCRIPT and end the request with redirect(), so they
 * have no unit test. Their guards are Behat scenarios: "Guessing the URL of an unlisted category
 * reveals nothing about it" (theme_dimensions_unlisted.feature), the visitor forward
 * (theme_dimensions_category.feature) and the calendar redirect
 * (theme_dimensions_calendar.feature); the calendar decision itself is coreview_test's.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_config {
    /**
     * Point the guest home, ghost an unreachable course or category, forward /enrol/index.php to the landing page and
     * core's calendar to the theme's, and serve core's iCal feed with the theme's withholding.
     *
     * @param \core\hook\after_config $hook The hook.
     */
    public static function callback(\core\hook\after_config $hook): void {
        global $CFG, $SCRIPT;

        // Never during install/upgrade, and never off a web page request.
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }
        if (
            (defined('CLI_SCRIPT') && CLI_SCRIPT)
                || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
                || (defined('WS_SERVER') && WS_SERVER)
        ) {
            return;
        }
        // Reads two cached settings and the session: nothing here can fail a page that loaded config.
        guesthome::apply();
        $script = (string) ($SCRIPT ?? '');
        if ($script === '/course/index.php') {
            $categoryid = optional_param('categoryid', 0, PARAM_INT);
            /* The id 0 (the root of the course index) goes through the same two calls: the
               forward asks whether the site serves the root anonymously, and the ghost answers
               false for the pseudo-category, which holds no row.

               Decide inside the try and redirect outside it: redirect() ends the request, and
               inside a catch-all it would put Moodle's whole shutdown path under this handler. */
            try {
                // Only a visitor is forwarded; the request's parameters are read only then.
                $target = isloggedin() ? null : redirector::category_page_target($categoryid);
                if ($target !== null) {
                    $target->params(request::forwarded_params($categoryid));
                }
            } catch (\Throwable $exception) {
                // A forwarding convenience must never take a page down.
                return;
            }
            if ($target !== null) {
                redirect($target);
            }
            try {
                $ghosted = redirector::is_category_ghosted($categoryid);
            } catch (\Throwable $exception) {
                // A visibility convenience must never take a page down.
                return;
            }
            if ($ghosted) {
                redirect(redirector::ghost_url());
            }
            return;
        }
        if ($script === '/calendar/view.php') {
            // Decide inside the try and redirect outside it, as in the branches above.
            try {
                $target = coreview::target(
                    coreview::request_params(),
                    coreview::enabled(),
                    coreview::account(),
                    coreview::gregorian(),
                    \core_date::get_user_timezone_object(),
                    time(),
                    \Closure::fromCallable([coreview::class, 'course_category'])
                );
            } catch (\Throwable $exception) {
                // A forwarding convenience must never take core's calendar down.
                return;
            }
            if ($target !== null) {
                redirect($target);
            }
            return;
        }
        if ($script === '/calendar/export_execute.php') {
            // The feed is served outside any catch: a failure of the withholding must not fall back to core's feed,
            // which would hand out what the theme withholds.
            if (coreview::enabled()) {
                ical::serve();
                die();
            }
            return;
        }
        if ($script !== '/enrol/index.php' && $script !== '/course/info.php') {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        // Course/info.php also accepts ?name=<shortname>, which would otherwise walk past the ghost.
        $shortname = !$courseid && $script === '/course/info.php' ? optional_param('name', '', PARAM_TEXT) : '';
        if (!$courseid && $shortname === '') {
            return;
        }

        // Decide inside the try and redirect outside it, as in the category branch above.
        try {
            $ghosted = $courseid ? redirector::is_address_ghosted($courseid) : redirector::is_shortname_ghosted($shortname);
        } catch (\Throwable $exception) {
            // A visibility convenience must never take a page down.
            return;
        }
        if ($ghosted) {
            redirect(redirector::ghost_url());
        }

        if ($script !== '/enrol/index.php') {
            return;
        }

        $action = (string) optional_param('action', '', PARAM_ALPHANUMEXT);
        $bypass = optional_param(redirector::BYPASS_PARAM, 1, PARAM_INT) === 0;

        try {
            $target = redirector::enrol_page_target($courseid, $action, $bypass);
        } catch (\Throwable $exception) {
            // A forwarding convenience must never take the enrolment page down.
            return;
        }
        if ($target) {
            redirect($target);
        }
    }
}
