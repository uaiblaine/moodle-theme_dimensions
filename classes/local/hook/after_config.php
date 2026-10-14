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

use theme_dimensions\local\category\request;
use theme_dimensions\local\guesthome;
use theme_dimensions\local\hotsite\redirector;

/**
 * Ghosts what this user must not discover, forwards the enrolment page to the hotsite, and points the guest home.
 *
 * after_config is the only moment early enough: enrol/index.php redirects on
 * its own several lines in (not-logged-in users to the login page, enrolled
 * users to the course), so anything later never sees the request. The
 * callback reads the request shape and delegates every real decision to
 * {@see redirector}, which is where the unit tests live.
 *
 * Three request shapes are read. /enrol/index.php and /course/info.php are
 * ghosted over the COURSE they name, the first of them then forwarded to the
 * hotsite; /course/index.php?categoryid=N is forwarded for a VISITOR to the
 * public page of a category the site serves anonymously, and otherwise ghosted
 * over the CATEGORY, which is what makes a guessed category URL answer the
 * same as a guessed course one. Without it the theme's renderer would withhold
 * the category from every listing and the page itself would still name it in
 * the heading, the breadcrumb and the title.
 *
 * The two answers of the category branch cannot collide: the forward is only
 * ever taken by somebody who is not logged in, and the ghost only by somebody
 * who is, so the order between them is a matter of cost and not of precedence.
 *
 * Before any of that, and on every web request whatever its script, the guest account's home
 * page is decided ({@see guesthome}): an in-memory override of defaulthomepage, which has to be
 * in place before index.php or any navigation reads it. after_config is dispatched by
 * lib/setup.php after the session has started, so isguestuser() reads the real session, and the
 * router's requests load lib/setup.php too (moodle_bootstrap_middleware::load_full_moodle()),
 * where $SCRIPT is /r.php - which is why that branch reads no $SCRIPT at all.
 *
 * The category branch has no unit test, and cannot have one: the callback
 * reads $SCRIPT and ends the request with redirect(). Its guards are Behat
 * scenarios - "Guessing the URL of an unlisted category reveals nothing about
 * it" in tests/behat/theme_dimensions_unlisted.feature, with the
 * listed category beside it as the control, and the visitor forward in
 * tests/behat/theme_dimensions_category.feature, with the public
 * category as the case and a listed and an unlisted one as its controls.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_config {
    /**
     * Point the guest home, ghost an unreachable course or category, and forward /enrol/index.php to the hotsite.
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
            /* The id 0 is the root of the course index and is decided by the same two calls as
               any other id: the forward asks category_page_target(0), which asks whether the
               site serves the root anonymously, and the ghost below answers false for it by its
               own guard - the pseudo-category holds no row, so it can be withheld from nobody.

               Decide inside the try and redirect outside it: redirect() ends the request, and
               running it inside a catch-all would put the whole of Moodle's shutdown path under
               this handler. The course branch below follows the same rule. */
            try {
                // Only a visitor is ever forwarded, and the request's own parameters are read
                // only once there is a forward to carry them: nobody else pays for them.
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
        if ($script !== '/enrol/index.php' && $script !== '/course/info.php') {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        if (!$courseid && $script === '/course/info.php') {
            /* course/info.php also accepts ?name=<shortname>, which makes the page
               enumerable by guessing shortnames rather than ids. Resolve it here or
               that route would walk straight past the ghost. */
            $shortname = optional_param('name', '', PARAM_TEXT);
            if ($shortname !== '') {
                $courseid = (int) $GLOBALS['DB']->get_field('course', 'id', ['shortname' => $shortname]);
            }
        }
        if (!$courseid) {
            return;
        }

        // Decide inside the try and redirect outside it, as in the category branch above.
        try {
            $ghosted = redirector::is_ghosted($courseid);
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
