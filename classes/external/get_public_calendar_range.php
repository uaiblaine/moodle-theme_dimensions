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
 * Theme Dimensions - One month grid of the public calendar, for a visitor
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\calendar\publicrange;
use theme_dimensions\local\calendar\refusal;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\calendar\window;

/**
 * The visitor's twin of {@see get_calendar_range}: the category events of public categories and the public event
 * courses over a window, for a visitor who is not logged in.
 *
 * Registered with `loginrequired => false`, so it answers through lib/ajax/service-nologin.php, which defines
 * NO_MOODLE_COOKIES and reads no session. A function of its own rather than a flag on the logged-in one, because
 * `loginrequired` belongs to the function. It is the showcase visitor's twin
 * ({@see get_public_category_rows}), step for step where the two share a step:
 *
 * 1. validate_parameters().
 * 2. One call per HTTP request: service.php runs every call of a batch with no cap when there is no session, so one
 *    POST could otherwise run any number of answers past a per-request rate limit. Counted before anything can
 *    refuse, so a batch of refused calls is cut to one too.
 * 3. A request carrying a `cachekey` is refused: service.php answers such a GET with ninety days of
 *    `Cache-Control: public, immutable`, which would keep an event a category has since withdrawn in browsers and
 *    shared caches. An error is never given those headers.
 * 4. Any session that is logged in, the guest's included (isloggedin()), is refused with the one refusal: this answer
 *    is a visitor's alone, and the logged-in page has its own service.
 * 5. The cheap shape checks, which read nothing: the window parsed in UTC ({@see window::from_iso()}: a real
 *    YYYY-MM-DD pair, in order, at most window::MAX_DAYS days) and the keys against the visitor's two
 *    ({@see visitor::KEYS}).
 * 6. The language the client names, when it is an installed and enabled pack, given back in a finally.
 * 7. The switch ({@see visitor::enabled()}, off unless a stored '1' opens it), then the site's calendar type (the
 *    Gregorian alone, as the page), each with the one refusal.
 * 8. The page's context, the system's, set as tool_policy's get_policy_version sets one; validate_context() is never
 *    called, because it ends in require_login(), which throws for every visitor.
 * 9. The window in the site's timezone and {@see publicrange::answer()}.
 *
 * Nothing reads $USER. Nothing is cached across requests. The one refusal is the range's own ({@see refusal}): the
 * same exception whatever was refused, identical while debugging is below DEVELOPER (at that level the framework
 * adds a backtrace naming the line that refused). A malformed request is an invalid parameter, for every visitor
 * alike.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_public_calendar_range extends external_api {
    /** @var int How many times this function has been called in this request. */
    private static int $calls = 0;

    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'from' => new external_value(PARAM_RAW, 'The first day, YYYY-MM-DD, in the site\'s timezone.'),
            'to' => new external_value(PARAM_RAW, 'The last day, YYYY-MM-DD, inclusive; at most 42 days after from.'),
            'keys' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'A key: category or open.'),
                'The keys that are on; empty for both.',
                VALUE_DEFAULT,
                []
            ),
            'lang' => new external_value(
                PARAM_SAFEDIR,
                'The language of the page that asks; an empty or unknown one answers in the request\'s own.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * One window of the public calendar, for a visitor.
     *
     * @param string $from The first day.
     * @param string $to The last day.
     * @param array $keys The keys that are on, empty for both.
     * @param string $lang The language of the page that asks, '' for the request's own.
     * @return array The answer of publicrange::answer().
     * @throws \invalid_parameter_exception On a second call, a cachekey or a malformed request.
     * @throws refusal The one refusal, for every request that may not be answered.
     */
    public static function execute(string $from, string $to, array $keys = [], string $lang = ''): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'from' => $from,
            'to' => $to,
            'keys' => $keys,
            'lang' => $lang,
        ]);

        self::$calls++;
        if (self::$calls > 1) {
            throw new \invalid_parameter_exception('One call of this function per request.');
        }
        // The key service.php reads to send its immutable public caching headers.
        if (optional_param('cachekey', 0, PARAM_INT) > 0) {
            throw new \invalid_parameter_exception('This function is not answered with a cachekey.');
        }
        // The guest included: core counts it as logged in, and this answer is a visitor's alone.
        if (isloggedin()) {
            throw new refusal();
        }

        try {
            window::from_iso($params['from'], $params['to'], new \DateTimeZone('UTC'));
        } catch (refusal $malformed) {
            $message = 'The window must be two days, YYYY-MM-DD, in order, at most ' . window::MAX_DAYS . ' days.';
            throw new \invalid_parameter_exception($message);
        }
        foreach ($params['keys'] as $key) {
            if (!in_array($key, visitor::KEYS, true)) {
                throw new \invalid_parameter_exception('Unknown key: ' . $key);
            }
        }
        $on = $params['keys'] ? visitor::on($params['keys']) : visitor::KEYS;

        $previous = null;
        if ($params['lang'] !== '' && get_string_manager()->translation_exists($params['lang'], false)) {
            $previous = force_current_language($params['lang']);
        }
        try {
            if (!visitor::enabled()) {
                throw new refusal();
            }
            window::require_gregorian();

            $PAGE->reset_theme_and_output();
            $PAGE->set_context(\core\context\system::instance());

            return publicrange::answer(window::from_iso($params['from'], $params['to']), $on);
        } finally {
            if ($previous !== null) {
                force_current_language($previous);
            }
        }
    }

    /**
     * Forget the calls counted in this process, as a new request would.
     *
     * PHPUnit runs every call of a test in one process; the tests' fixture calls this between them.
     *
     * @return void
     */
    public static function reset(): void {
        self::$calls = 0;
    }

    /**
     * What the function returns: the logged-in range's allowlist, key for key, and the category events' descriptions.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $keys = get_calendar_range::execute_returns()->keys;
        $keys['descriptions'] = new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The category event the text describes.'),
            'text' => new external_value(PARAM_RAW, 'Its description as plain text, no markup, no image.'),
        ]), 'The descriptions of the category events answered, for those that have one.');
        return new external_single_structure($keys);
    }
}
