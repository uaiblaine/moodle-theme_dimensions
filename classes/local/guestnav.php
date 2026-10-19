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
 * Theme Dimensions - The visitor's navbar decision
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Whether a visitor's navbar carries the theme's "Log in" and "Create account" actions, and what they say.
 *
 * Every decision of the feature lives here and the renderer only asks: whether it applies on the page
 * being rendered ({@see applies()}), whether the site takes sign-ups ({@see signup_url()}) and the
 * template data both the navbar's actions and the mobile drawer's card are drawn from
 * ({@see context()}). Nothing here renders or reads a renderer, so each clause is testable alone.
 *
 * The visitor is the one core prints its "unauthenticated user" block for: a session that is not
 * logged in, and the guest account. Core says "You are currently using guest access" only to the
 * second, so only the second gets the chip ({@see context()}'s isguest).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class guestnav {
    /** @var string Component name, used for every config read. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The feature's setting, an admin_setting_configcheckbox that ships on. */
    public const SETTING = 'navbarguestactions';

    /**
     * @var string[] The page layouts whose page draws no navbar this feature could change.
     *
     * Every other layout of Boost Union's config.php is drawn by its drawers.php or columns2.php,
     * whose navbar prints navbar_plugin_output() and the user menu. These are drawn by Boost Union's
     * login.php and core Boost's columns1.php, embedded.php, maintenance.php and secure.php: no user
     * menu, and the login pages offer their own way in. Listed rather than inferred from the
     * template, so a page that calls the navbar from a layout of a plugin's still gets the actions.
     */
    public const EXCLUDED_LAYOUTS = ['login', 'maintenance', 'embedded', 'redirect', 'popup', 'frametop', 'print', 'secure'];

    /**
     * Whether the feature is switched on.
     *
     * A default-on checkbox reads only an explicit '0' as off, so a site that never saved the setting
     * (false) has the feature without an upgrade step writing it.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (string) get_config(self::COMPONENT, self::SETTING) !== '0';
    }

    /**
     * Whether the page being rendered gets the visitor's actions in place of core's login text.
     *
     * Ordered cheapest first. The Moodle app is excluded because it logs in on its own and shows a
     * plugin's web view to a session that may look logged out for minutes at a time.
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function applies(\moodle_page $page): bool {
        if (!self::enabled()) {
            return false;
        }
        if (isloggedin() && !isguestuser()) {
            return false;
        }
        if (in_array((string) $page->pagelayout, self::EXCLUDED_LAYOUTS, true)) {
            return false;
        }
        if (\core_useragent::is_moodle_app()) {
            return false;
        }

        return true;
    }

    /**
     * The address of the sign-up page, or null when the site takes no sign-ups.
     *
     * The question is core's own: signup_is_enabled() is what login/signup.php asks before it serves
     * anything, so the button exists exactly when that page would. A plugin that answers can_signup()
     * and takes the page over (auth_loginsteps' own flows) is followed through core's page, and the
     * theme names none.
     *
     * @return \moodle_url|null
     */
    public static function signup_url(): ?\moodle_url {
        global $CFG;
        require_once($CFG->libdir . '/authlib.php');

        if (!signup_is_enabled()) {
            return null;
        }

        return new \moodle_url('/login/signup.php');
    }

    /**
     * The template data of theme_dimensions/navbar_guest and theme_dimensions/navbar_guest_card.
     *
     * Urls in the plain spelling, for the templates' double stashes. The login url is core's plain
     * get_login_url(): login/index.php takes the visitor back to the page they came from by itself,
     * from the local referer.
     *
     * @return array loginurl (string), hassignup (bool), signupurl (string, '' without sign-ups) and
     *      isguest (bool, true for the guest account only).
     */
    public static function context(): array {
        $signup = self::signup_url();

        return [
            'loginurl' => get_login_url(),
            'hassignup' => $signup !== null,
            'signupurl' => $signup !== null ? $signup->out(false) : '',
            'isguest' => isguestuser(),
        ];
    }
}
