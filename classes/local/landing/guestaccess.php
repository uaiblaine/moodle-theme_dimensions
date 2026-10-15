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
 * Theme Dimensions - The landing page's guest entry
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

/**
 * The guest entry: "enter as guest" straight from a shared link, skipping the landing page.
 *
 * A course offers it when it has an enabled guest access instance, of an enabled enrol_guest,
 * whose password is empty: exactly the instance enrol_guest::try_guestaccess() lets the guest
 * account through without asking anything, so the course page that follows the entry opens.
 * Everything else - a password, a disabled instance, guest access switched off for the site -
 * leaves the landing page as it was and draws no second address.
 *
 * The entry logs an anonymous visitor in as the site's guest account. It is only ever done for
 * a POST carrying the session key, from the small form on the interstitial page
 * {@see request::handle()} serves: a link preview robot or a prefetcher fetching the shared
 * address gets that page and its Open Graph tags, and never a guest session. The login mirrors
 * the one core performs for $CFG->autologinguests inside require_login(): the complete user
 * record of $CFG->siteguest through complete_user_login(), keeping the language the visitor
 * was browsing in.
 *
 * Under forcelogin the course page still opens for the guest account: require_login() never
 * reads $CFG->forcelogin, has_capability() refuses only user id 0 under it, and the guest's
 * temporary role comes from enrol_guest::try_guestaccess().
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guestaccess {
    /** @var string The field of the interstitial's form that asks for the entry, posted with the session key. */
    public const ENTER_PARAM = 'guestenter';

    /**
     * Whether the course offers the guest entry: an enabled, passwordless guest access instance.
     *
     * Fails closed: anything that throws answers false, and the landing page stays as it was.
     *
     * The site must also let visitors become the guest account at all: core admits one through the
     * login page only with $CFG->guestloginbutton, and through {@see require_login()} only with
     * $CFG->autologinguests. A site with both off has closed guest login, and the guest session this
     * entry starts is not limited to the course that offered it, so the entry is withdrawn there.
     *
     * @param \stdClass $course The course.
     * @return bool Whether the entry is offered.
     */
    public static function available(\stdClass $course): bool {
        global $CFG;

        if (empty($course->id) || (int) $course->id === (int) SITEID) {
            return false;
        }
        if (empty($CFG->guestloginbutton) && empty($CFG->autologinguests)) {
            return false;
        }
        try {
            // Enabled instances of enabled plugins only, so a site without enrol_guest offers nothing.
            foreach (enrol_get_instances((int) $course->id, true) as $instance) {
                // The strict comparison try_guestaccess() makes: a null password is not an empty one.
                if ($instance->enrol === 'guest' && $instance->password === '') {
                    return true;
                }
            }
        } catch (\Throwable $exception) {
            return false;
        }
        return false;
    }

    /**
     * Where the entry leads: the course page, which takes the guest account in through enrol_guest.
     *
     * @param int $courseid The course id.
     * @return \core\url The address.
     */
    public static function target(int $courseid): \core\url {
        return new \core\url('/course/view.php', ['id' => $courseid]);
    }

    /**
     * Log an anonymous visitor in as the site's guest account.
     *
     * Never replaces a session somebody is logged into, guest or not.
     *
     * @return bool Whether a login happened.
     */
    public static function log_in(): bool {
        global $CFG, $SESSION;

        if (isloggedin()) {
            return false;
        }
        $guest = get_complete_user_data('id', $CFG->siteguest);
        if (!$guest) {
            return false;
        }
        // The guest account has no language of its own: keep the one the visitor was browsing in.
        $lang = $SESSION->lang ?? $CFG->lang;
        complete_user_login($guest);
        $SESSION->lang = $lang;
        return true;
    }
}
