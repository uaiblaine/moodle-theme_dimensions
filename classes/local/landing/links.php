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
 * Theme Dimensions - The addresses of a landing page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\landingfields;

/**
 * The one place a landing page's addresses are spelled.
 *
 * Three addresses:
 *
 * - legacy(): the script, /theme/dimensions/landing.php?id=N. It always answers.
 * - page(): the page's canonical address, the one $PAGE, wantsurl, the no-JS form transports
 *   and the Open Graph tags carry: the route /theme_dimensions/landing/N while the
 *   site's router is configured, the script otherwise.
 * - share(): the public address, /p/<code>, minted into core's shortlink table and resolved by
 *   core's /p/{shortcode} route into page() through the theme's shortlink_handler. Only while
 *   the site's router is configured: without it core would spell the address /r.php/p/<code>,
 *   longer than the legacy one, so share() answers page() and mints nothing.
 *
 * And the guest entry's pair, the "enter as guest" address of a course whose guest access needs
 * no password ({@see guestaccess}): guest_page() is page() with guest=1, and guest_share() is a
 * second public code of its own link type, LINKTYPE_GUEST, which the handler resolves into
 * guest_page(). It needs a code of its own because core's /p/{shortcode} route drops the query
 * string: /p/<code>?guest=1 would arrive at the page without the parameter.
 *
 * The category showcase's own addresses live here too, for the same reason - one place spells
 * them - even though the class is named after the landing page: category_legacy() and
 * category_page() are the script and the route of the anonymous shell, and showcase() and
 * showcase_form() pick between that shell and core's /course/index.php for whichever viewer
 * is asking, so the listing, the pager, the toolbar and the hero never decide it themselves.
 *
 * The router gate is $CFG->routerconfigured, the same assertion core's own URL builders read
 * (\core\url::routed_path(), \core\router::guess_basepath()): the administrator's word that
 * the web server rewrites unknown paths to r.php. The check which proves it
 * (\core\check\environment\router) makes five HTTP requests to the site itself and belongs to
 * the administration's checks, never to a page render.
 *
 * Codes are drawn by {@see shortcode} rather than by core's generator, whose alphabet cannot be
 * dictated; the row is written to core's table with core's own columns, so the route, the
 * manager and the handler contract stay core's. Uniqueness is global, as core demands of a
 * public code: no row of any user may hold the same code. The table's (userid, shortcode)
 * unique index is the arbiter when two editors open the same course form or course page at once: the loser
 * draws again, both then read back the row with the lowest id, and a course has one public
 * address however many editors it has.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class links {
    /** @var string The link type this theme mints, as stored in the shortlink table and answered by the handler. */
    public const LINKTYPE = 'landing';

    /** @var string The link type of the guest entry's public code, resolved by the handler into guest_page(). */
    public const LINKTYPE_GUEST = 'landingguest';

    /** @var string The query parameter that asks the landing page for its guest entry. */
    public const GUEST_PARAM = 'guest';

    /** @var int How many codes mint() draws before giving up on a site whose codes are nearly all taken. */
    private const MINT_ATTEMPTS = 40;

    /**
     * Whether the site's router is configured, so that routed addresses are short ones.
     *
     * @return bool True when $CFG->routerconfigured is set.
     */
    public static function routing_enabled(): bool {
        global $CFG;
        return !empty($CFG->routerconfigured);
    }

    /**
     * The address of the script serving the landing page.
     *
     * @param int $courseid The course id.
     * @return \core\url The address.
     */
    public static function legacy(int $courseid): \core\url {
        return new \core\url('/theme/dimensions/landing.php', ['id' => $courseid]);
    }

    /**
     * The canonical address of the landing page: what the page, wantsurl and the head tags carry.
     *
     * The route while the router is configured, the script otherwise; never the /r.php/
     * spelling, which works but is longer than the script's own address.
     *
     * @param int $courseid The course id.
     * @return \core\url The address.
     */
    public static function page(int $courseid): \core\url {
        if (!self::routing_enabled()) {
            return self::legacy($courseid);
        }
        return \core\router\util::get_path_for_callable(
            [\theme_dimensions\route\controller\landing::class, 'view'],
            ['course' => $courseid]
        );
    }

    /**
     * The landing page's guest entry: page() asking for the guest entry ({@see request::handle()}).
     *
     * @param int $courseid The course id.
     * @return \core\url The address.
     */
    public static function guest_page(int $courseid): \core\url {
        $url = self::page($courseid);
        $url->param(self::GUEST_PARAM, 1);
        return $url;
    }

    /**
     * The address of the script serving a category's public page.
     *
     * @param int $categoryid The category id.
     * @param array $params Extra query parameters, name => value; the id always wins over one of its own.
     * @return \core\url The address.
     */
    public static function category_legacy(int $categoryid, array $params = []): \core\url {
        return new \core\url('/theme/dimensions/category.php', ['id' => $categoryid] + $params);
    }

    /**
     * The canonical address of a category's public page: what $PAGE, wantsurl and the head tags carry.
     *
     * The route while the router is configured, the script otherwise; never the /r.php/
     * spelling, which works but is longer than the script's own address.
     *
     * @param int $categoryid The category id.
     * @param array $params Extra query parameters, name => value.
     * @return \core\url The address.
     */
    public static function category_page(int $categoryid, array $params = []): \core\url {
        if (!self::routing_enabled()) {
            return self::category_legacy($categoryid, $params);
        }
        $url = \core\router\util::get_path_for_callable(
            [\theme_dimensions\route\controller\category::class, 'view'],
            ['category' => $categoryid]
        );
        $url->params($params);
        return $url;
    }

    /**
     * The address of one page of the showcase, for whichever shell is drawing it.
     *
     * The logged-in shell is core's own category page, which this theme's course renderer
     * takes over; the anonymous shell is the public page. Everything the listing links -
     * the navigation tree, the area posters and their panel, the pager, the quick date options
     * and the "clear filters" control - goes through here, so neither shell can hand a reader
     * an address the other shell answers.
     *
     * @param int $categoryid The category id.
     * @param array $params Extra query parameters, name => value.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @return \core\url The address.
     */
    public static function showcase(int $categoryid, array $params, bool $anonymous): \core\url {
        if ($anonymous) {
            return self::category_page($categoryid, $params);
        }
        if ($categoryid === 0) {
            // The root of the course index is the bare script: core's own user_top() answers it.
            return new \core\url('/course/index.php', $params);
        }
        return new \core\url('/course/index.php', ['categoryid' => $categoryid] + $params);
    }

    /**
     * What a GET form of the showcase needs: where to post, and the fields that carry the category.
     *
     * A GET submission REPLACES the action's query string with the form's own fields, so the
     * category id may never sit in the action: it travels as a hidden field, or - on the route,
     * whose id is a path segment rather than a parameter - in the path, and the form then needs
     * no hidden field at all. Both the toolbar and the hero's fold control take their shape from
     * here, so the two forms of one page always agree.
     *
     * @param int $categoryid The category id.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @return array ['action' => string, 'hidden' => [['name' => string, 'value' => string|int], ...]].
     */
    public static function showcase_form(int $categoryid, bool $anonymous): array {
        if (!$anonymous) {
            return [
                'action' => (new \core\url('/course/index.php'))->out(false),
                // The root has no id to carry: /course/index.php with no categoryid is the root.
                'hidden' => $categoryid === 0 ? [] : [['name' => 'categoryid', 'value' => $categoryid]],
            ];
        }
        if (self::routing_enabled()) {
            // The id is a path segment of the route, so the form carries nothing of its own.
            return ['action' => self::category_page($categoryid)->out(false), 'hidden' => []];
        }
        return [
            'action' => (new \core\url('/theme/dimensions/category.php'))->out(false),
            'hidden' => [['name' => 'id', 'value' => $categoryid]],
        ];
    }

    /**
     * The public address of the landing page: the one to share.
     *
     * The course's short address, minted on the first call; the page's own address while the
     * router is not configured, with nothing minted. Minting is a write, so the caller decides who
     * may cause one: the course form hook and the course notice both ask only on an editor's
     * request, and nothing a learner or a visitor opens calls this.
     *
     * @param int $courseid The course id.
     * @return \core\url The address.
     */
    public static function share(int $courseid): \core\url {
        if (!self::routing_enabled()) {
            return self::page($courseid);
        }
        $code = self::existing_code($courseid) ?? self::mint($courseid, self::LINKTYPE);
        return self::public_url($code);
    }

    /**
     * The public address of the guest entry: the one to share for "enter as guest".
     *
     * The course's second short code, of its own link type, minted on the first call; guest_page()
     * while the router is not configured, with nothing minted. Whether the course offers the entry
     * at all is the caller's question ({@see guestaccess::available()}): a code minted for a course
     * that later loses passwordless guest access resolves to the landing page, which then ignores the
     * parameter.
     *
     * @param int $courseid The course id.
     * @return \core\url The address.
     */
    public static function guest_share(int $courseid): \core\url {
        if (!self::routing_enabled()) {
            return self::guest_page($courseid);
        }
        $code = self::existing_code($courseid, self::LINKTYPE_GUEST) ?? self::mint($courseid, self::LINKTYPE_GUEST);
        return self::public_url($code);
    }

    /**
     * The course's short code of one link type, when one has been minted.
     *
     * The oldest row wins, so that two rows left by a race still spell one address.
     *
     * @param int $courseid The course id.
     * @param string $linktype LINKTYPE (the landing page) or LINKTYPE_GUEST (the guest entry).
     * @return string|null The code, or null when none was minted.
     */
    public static function existing_code(int $courseid, string $linktype = self::LINKTYPE): ?string {
        global $DB;
        $rows = $DB->get_records('shortlink', [
            'component' => landingfields::COMPONENT,
            'linktype' => $linktype,
            'identifier' => (string) $courseid,
            'userid' => 0,
        ], 'id ASC', 'id, shortcode', 0, 1);
        $row = reset($rows);
        return $row ? (string) $row->shortcode : null;
    }

    /**
     * The address core's public shortlink route answers for a code.
     *
     * @param string $code The short code.
     * @return \core\url The address, /p/<code> under the site's root.
     */
    public static function public_url(string $code): \core\url {
        return \core\router\util::get_path_for_callable(
            [\core\route\shortlink::class, 'handle_public_shortlink'],
            ['shortcode' => $code]
        );
    }

    /**
     * Forget every short code of a course: its rows of both link types, and no other course's.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function forget(int $courseid): void {
        global $DB;
        [$typesql, $params] = $DB->get_in_or_equal([self::LINKTYPE, self::LINKTYPE_GUEST], SQL_PARAMS_NAMED, 'linktype');
        $params['component'] = landingfields::COMPONENT;
        $params['identifier'] = (string) $courseid;
        $DB->delete_records_select(
            'shortlink',
            "component = :component AND identifier = :identifier AND linktype $typesql",
            $params
        );
    }

    /**
     * Mint a short code for a course.
     *
     * Draws until a code no row holds is written. A code taken between the check and the
     * insert fails on the table's unique index, which is the signal to draw again; once a row
     * is in, the oldest row of the course is what every caller reads, so a race between two
     * editors leaves both with the same address.
     *
     * @param int $courseid The course id.
     * @param string $linktype LINKTYPE or LINKTYPE_GUEST: each type is one address of its own.
     * @return string The code now on record for the course.
     * @throws \moodle_exception When no free code was found in MINT_ATTEMPTS draws.
     */
    private static function mint(int $courseid, string $linktype): string {
        global $DB;
        for ($attempt = 0; $attempt < self::MINT_ATTEMPTS; $attempt++) {
            $code = shortcode::generate();
            if ($DB->record_exists('shortlink', ['shortcode' => $code])) {
                continue;
            }
            try {
                $DB->insert_record('shortlink', (object) [
                    'shortcode' => $code,
                    'userid' => 0,
                    'component' => landingfields::COMPONENT,
                    'linktype' => $linktype,
                    'identifier' => (string) $courseid,
                ]);
            } catch (\dml_write_exception $exception) {
                // Somebody took this code between the check and the insert; draw another.
                continue;
            }
            return self::existing_code($courseid, $linktype) ?? $code;
        }
        throw new \moodle_exception('landing_shortcodeexhausted', landingfields::COMPONENT);
    }
}
