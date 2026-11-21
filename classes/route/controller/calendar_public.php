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
 * Theme Dimensions - One half of the public calendar, as JSON a shared cache may keep
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\param;
use core\router\route;
use core\router\schema\parameters\path_parameter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\publiccache;
use theme_dimensions\local\calendar\refusal;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\calendar\window;

/**
 * The public calendar's one endpoint: /theme_dimensions/calendar/public/{half}/{token}/{lang}/{from}/{to}.
 *
 * The visitor's calendar asks it for each half its keys turn on ({@see publiccache}). Every part of the answer is in the
 * address, nothing is read from a session or a cookie, and the answer is the same for whoever asks, so a CDN, a reverse
 * proxy or a browser may keep it: `Cache-Control: public` with the half's lifetime for a shared cache
 * ({@see publiccache::lifetime()}) and {@see publiccache::BROWSER_LIFETIME} for a browser. The route is GET only and
 * declared `cookies: false`, core's own pattern for content a cache keeps (its ES module and template routes): no
 * session is started, so no Set-Cookie travels, which would make a CDN pass the answer by. Core's
 * `Vary: Accept-Language` is taken off it too: the language is in the address.
 *
 * The order, every refusal the same 404 sent `no-store` (never kept, and one answer for every reason, as the visitor's
 * service it replaces gave one exception for every refusal):
 *
 * 1. The half is one of {@see publiccache::HALVES}; the language is installed and enabled; both days are real days in
 *    order, at most {@see window::MAX_DAYS} apart. These read nothing.
 * 2. The language is spoken for the rest of the request, given back in a finally.
 * 3. The switch ({@see visitor::enabled()}), then the Gregorian calendar.
 * 4. The window is a shape the client asks for ({@see publiccache::canonical()}): a day, a week or a month's grid. One
 *    starting further than {@see publiccache::REACH_MONTHS} from this month is answered but kept nowhere (`no-store`,
 *    and not in the shared cache), so what the caches can be made to hold is bounded.
 * 5. The token is the half's current one ({@see publiccache::token()}); any other is sent to the current address with
 *    a 302 sent `no-store`, so an evicted entry is never refilled under an old token and an old page gets fresh data.
 * 6. The half, from the shared cache ({@see publiccache::half()}).
 *
 * A logged-in browser asking is answered the same public half: with no session read, nobody is logged in here, and
 * nothing in the answer belongs to anybody.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_public {
    use \core\router\route_controller;

    /** @var string The placeholder days {@see prefix()} builds the address with, to cut them off again. */
    private const PLACEHOLDER = '0000-00-00';

    /**
     * Answer one half of the public calendar for one window.
     *
     * @param ServerRequestInterface $request The request.
     * @param ResponseInterface $response The response to fill.
     * @param string $half The half.
     * @param string $token The half's token, as the page handed it.
     * @param string $lang The language.
     * @param string $from The first day.
     * @param string $to The last day.
     * @return ResponseInterface The half as JSON, a redirect to the current token, or the one refusal.
     */
    #[route(
        path: '/calendar/public/{half}/{token}/{lang}/{from}/{to}',
        method: ['GET'],
        cookies: false,
        pathtypes: [
            new path_parameter(name: 'half', type: param::ALPHANUMEXT),
            new path_parameter(name: 'token', type: param::ALPHANUMEXT),
            new path_parameter(name: 'lang', type: param::ALPHANUMEXT),
            new path_parameter(name: 'from', type: param::ALPHANUMEXT),
            new path_parameter(name: 'to', type: param::ALPHANUMEXT),
        ],
    )]
    public function half(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $half,
        string $token,
        string $lang,
        string $from,
        string $to,
    ): ResponseInterface {
        global $CFG, $PAGE;

        if (!in_array($half, publiccache::HALVES, true) || !get_string_manager()->translation_exists($lang, false)) {
            return self::refused($response);
        }
        try {
            window::from_iso($from, $to, new \DateTimeZone('UTC'));
        } catch (refusal $malformed) {
            return self::refused($response);
        }

        $previous = force_current_language($lang);
        try {
            if (!visitor::enabled()) {
                return self::refused($response);
            }
            try {
                window::require_gregorian();
            } catch (refusal $other) {
                return self::refused($response);
            }
            require_once($CFG->dirroot . '/calendar/lib.php');
            if (!publiccache::canonical($from, $to, (int) calendar_get_starting_weekday())) {
                return self::refused($response);
            }
            $current = publiccache::token($half);
            if ($token !== $current) {
                return self::uncached(self::redirect($response, self::url($half, $current, $lang, $from, $to)));
            }

            $PAGE->set_context(\core\context\system::instance());
            $near = publiccache::near($from);
            $answer = publiccache::half($half, window::from_iso($from, $to), $near);
        } finally {
            force_current_language($previous);
        }

        $response->getBody()->write(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        // Core's setup sends `Vary: Accept-Language` on a site with several languages (setup_vary_headers(), a raw
        // header() call no PSR response can take back). This answer is the address's language whatever the browser
        // asks, and Google Cloud CDN refuses to cache a response that varies on a header outside its key.
        if (!headers_sent()) {
            header_remove('Vary');
        }
        if (!$near) {
            return self::uncached($response);
        }
        $shared = publiccache::lifetime($half);
        return $response
            ->withHeader('Cache-Control', 'public, max-age=' . min(publiccache::BROWSER_LIFETIME, $shared)
                . ', s-maxage=' . $shared)
            ->withHeader('Pragma', '');
    }

    /**
     * The one refusal: an empty 404 that no cache keeps.
     *
     * @param ResponseInterface $response The response.
     * @return ResponseInterface
     */
    private static function refused(ResponseInterface $response): ResponseInterface {
        $response->getBody()->write('{}');
        return self::uncached($response->withStatus(404)->withHeader('Content-Type', 'application/json; charset=utf-8'));
    }

    /**
     * A response no cache may keep.
     *
     * @param ResponseInterface $response The response.
     * @return ResponseInterface
     */
    private static function uncached(ResponseInterface $response): ResponseInterface {
        return $response->withHeader('Cache-Control', 'no-store')->withHeader('Pragma', '');
    }

    /**
     * The address of one half for one window.
     *
     * @param string $half The half.
     * @param string $token Its token.
     * @param string $lang The language.
     * @param string $from The first day.
     * @param string $to The last day.
     * @return \core\url
     */
    public static function url(string $half, string $token, string $lang, string $from, string $to): \core\url {
        return \core\router\util::get_path_for_callable([self::class, 'half'], [
            'half' => $half,
            'token' => $token,
            'lang' => $lang,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * The address of a half without its window, which the client completes with `/{from}/{to}`.
     *
     * Built through the router like every other route address (the /r.php/ spelling while the router is not
     * configured), with placeholder days cut off.
     *
     * @param string $half The half.
     * @param string $token Its current token.
     * @param string $lang The language.
     * @return string
     */
    public static function prefix(string $half, string $token, string $lang): string {
        $url = self::url($half, $token, $lang, self::PLACEHOLDER, self::PLACEHOLDER)->out(false);
        return substr($url, 0, -strlen('/' . self::PLACEHOLDER . '/' . self::PLACEHOLDER));
    }
}
