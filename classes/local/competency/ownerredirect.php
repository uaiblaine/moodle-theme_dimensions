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
 * Theme Dimensions - The plan owner's redirect from core's plan pages
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

/**
 * Whether a request to core's plan page or competency-in-plan page goes to local_dimensions' plan overview.
 *
 * Only the plan's owner is sent there: a logged-in real account whose id equals the plan row's `userid`. Everybody
 * else (staff, the administrator, another learner, the guest, a visitor) keeps core's page, with core's own checks
 * and errors. The owner is decided from the ROW, never from the competency page's `userid` parameter.
 *
 * The plan row is read before core's checks, and that is acceptable because the only branch it opens is "it is the
 * viewer's own": a plan of somebody else, a missing id and a failed lookup all leave the request to core, which
 * answers them as it always did. The one read is `competency_plan.userid` by id, after every guard that needs no
 * table, so the guest and a visitor cost nothing.
 *
 * The overview must not refuse whoever is sent to it, so its own preconditions are asked first:
 * `local/dimensions:view` at the system context ({@see CAPABILITY}, local_dimensions' view-plan.php) and competencies
 * enabled (its `api::read_plan()`). A plan the owner may not read in core (a draft without planviewowndraft) is
 * refused by the overview with core's own exception, because both pages call `api::read_plan()`.
 *
 * Called from {@see \theme_dimensions\local\hook\after_config}, which redirects outside its catch. Nothing is stored.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ownerredirect {
    /** @var string The theme setting switching the redirect; on unless an explicit 0 is stored. */
    public const SETTING = 'planownerredirect';

    /** @var string The query parameter that keeps the owner on core's page; the overview's link back carries it. */
    public const PARAM_STAY = 'noredirect';

    /** @var string Core's page of one learning plan; its plan parameter is `id`. */
    public const PLAN_SCRIPT = '/admin/tool/lp/plan.php';

    /** @var string Core's page of one competency in a plan; its plan parameter is `planid`. */
    public const COMPETENCY_SCRIPT = '/admin/tool/lp/user_competency_in_plan.php';

    /** @var string The capability the overview requires at the system context (local_dimensions' view-plan.php). */
    public const CAPABILITY = 'local/dimensions:view';

    /** @var string The plan overview. */
    public const DESTINATION = '/local/dimensions/view-plan.php';

    /**
     * Whether the switch is on: only an explicitly stored 0 switches it off, so an unset setting reads as on.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $stored = get_config('theme_dimensions', self::SETTING);
        return !($stored !== false && (string) $stored === '0');
    }

    /**
     * The decision for this request, read from the request and the session.
     *
     * The plan id is the one the script itself reads (`id` on the plan page, `planid` on the competency page), and
     * the competency page's `userid` is not read at all. A parameter the script would refuse reads as 0 here, and
     * core's page answers it.
     *
     * @param string $script The request's script, as in `$SCRIPT`.
     * @return \core\url|null The overview's address, or null to leave the request to core.
     */
    public static function for_request(string $script): ?\core\url {
        global $USER;

        $planparam = $script === self::COMPETENCY_SCRIPT ? 'planid' : 'id';
        $competencyid = $script === self::COMPETENCY_SCRIPT ? (int) optional_param('competencyid', 0, PARAM_INT) : 0;
        return self::target(
            $script,
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            (int) optional_param($planparam, 0, PARAM_INT),
            $competencyid,
            (int) optional_param(self::PARAM_STAY, 0, PARAM_INT) !== 0,
            isloggedin() ? (int) $USER->id : 0,
            isguestuser(),
            self::enabled()
        );
    }

    /**
     * Where a request to one of core's two plan pages goes, if anywhere.
     *
     * The guards that need no table come first, in an order where the guest and a visitor stop before any read.
     * Null means "leave the request to core".
     *
     * @param string $script The request's script.
     * @param string $method The request's method; only a GET is redirected (both of core's pages are GET pages).
     * @param int $planid The plan id the script reads.
     * @param int $competencyid The competency id of the competency page, 0 for none; the overview ignores an id
     *     that is not one of the plan's listed competencies.
     * @param bool $stay Whether the request carries {@see PARAM_STAY}.
     * @param int $userid The session user's id, 0 when nobody is logged in.
     * @param bool $guest Whether the session is the guest account's.
     * @param bool $enabled Whether the theme's switch is on ({@see enabled()}).
     * @return \core\url|null The overview's address, or null.
     */
    public static function target(
        string $script,
        string $method,
        int $planid,
        int $competencyid,
        bool $stay,
        int $userid,
        bool $guest,
        bool $enabled
    ): ?\core\url {
        global $DB;

        if ($script !== self::PLAN_SCRIPT && $script !== self::COMPETENCY_SCRIPT) {
            return null;
        }
        if (strtoupper($method) !== 'GET') {
            return null;
        }
        if ($userid <= 0 || $guest) {
            return null;
        }
        if (!$enabled) {
            return null;
        }
        if ($stay) {
            return null;
        }
        if ($planid <= 0) {
            return null;
        }
        // The overview's read_plan() refuses with competencies disabled; core's page says so in its own words.
        if (!\core_competency\api::is_enabled()) {
            return null;
        }
        // The overview's own gate, before its read_plan(): never send somebody where they would be refused.
        if (!has_capability(self::CAPABILITY, \core\context\system::instance(), $userid)) {
            return null;
        }
        // The one read. A missing row answers false, which is nobody's id: the answer a plan of somebody else gets.
        $owner = $DB->get_field('competency_plan', 'userid', ['id' => $planid]);
        if ((int) $owner !== $userid) {
            return null;
        }

        $params = ['id' => $planid];
        if ($script === self::COMPETENCY_SCRIPT && $competencyid > 0) {
            $params['competencyid'] = $competencyid;
        }
        return new \core\url(self::DESTINATION, $params);
    }
}
