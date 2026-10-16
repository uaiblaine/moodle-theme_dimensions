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
 * Theme Dimensions - One page of a public category's showcase, for a visitor
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
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\category\rows;

/**
 * The visitor's twin of {@see get_category_rows}: one page of a PUBLIC category's listing.
 *
 * Registered with `loginrequired => false`, so it answers through lib/ajax/service-nologin.php,
 * which defines NO_MOODLE_COOKIES and reads no session, and nothing on that path reads the site's
 * forced login. It is a function of its own rather than a flag on the logged-in one because
 * `loginrequired` belongs to the function: a flag would put the logged-in listing on the internet.
 * The answer is {@see rows::fetch()}'s with `anonymous` on, which builds what the anonymous page
 * ({@see \theme_dimensions\local\category\request}) builds, and the returns are exactly
 * {@see get_category_rows::execute_returns()}, so both clients paint one shape.
 *
 * validate_context() is never called: it ends in require_login(), which throws for every
 * visitor whatever the forced login says. The context is set here, as tool_policy's
 * get_policy_version does, after visibility has already been decided. Nothing reads $USER; the
 * session check is isloggedin(), which a guest and a real account both answer true to.
 *
 * The order of the checks is a security property:
 *
 * 1. validate_parameters().
 * 2. One call per HTTP request: service.php runs every call of a batch, with no cap and without
 *    stopping on an error when there is no session, so one POST could otherwise run any number
 *    of listings past a per-request rate limit.
 * 3. A request carrying a `cachekey` is refused: service.php answers such a GET with 90 days of
 *    `Cache-Control: public, immutable` for any past integer, which would keep a since-withdrawn
 *    course in browsers and shared caches. An error is never given those headers.
 * 4. Any session that is logged in, the guest's included, is refused with the one refusal: the
 *    anonymous population may only be built for a visitor ({@see population::scope()}).
 * 5. The cheap shape checks, identical for every id: the page, the organisation, the dates, the
 *    root's term alone, the number of filters and their fields' uniqueness, the length of the
 *    term, and a slice's shape ({@see rows::require_wellformed_slice()}).
 * 6. The language the client names, when it is an installed and enabled pack, restored in a
 *    finally because one process may serve several calls.
 * 7. This function's own switch ({@see publicaccess::client_enabled()}, off unless a stored '1'
 *    opens it) and {@see publicaccess::is_public()}, before any lookup: one refusal for a
 *    category that is missing, listed, unlisted, hidden, under a hidden one, or behind a switch.
 * 8. The context, with a non-throwing lookup, and the page set to it.
 * 9. The category, read with alwaysreturnhidden because its visibility was decided in step 7.
 * 10. The filters' fields against the site's allowlist, which every public page prints.
 * 11. The delegate. A population that refuses answers the same refusal ({@see rows::fetch()}).
 *
 * The one refusal is identical only while debugging is below DEVELOPER: at that level the
 * framework adds a backtrace naming the line that refused.
 *
 * The tree's `cat` is never refused, as the logged-in function's is not: the rule is
 * {@see population::cat_ids()}'s, so the parameter tells the internet nothing about which ids exist.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_public_category_rows extends external_api {
    /** @var string Component name for the refusal. */
    private const COMPONENT = 'theme_dimensions';

    /** @var int The longest term accepted, in bytes; a real term is cut to 100 characters anyway. */
    public const MAX_QUERY_BYTES = 1000;

    /** @var int How many times this function has been called in this request. */
    private static int $calls = 0;

    /**
     * The parameters of the function.
     *
     * The logged-in function's, less the state chip and the favourites toggle, which a visitor's
     * listing never draws (a visitor has no favourites), plus
     * the slice of a merely listed child and the language of the page that asks.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'The public category whose subtree is listed; 0 for the root.'),
            'page' => new external_value(PARAM_INT, 'The zero-based page of the listing.', VALUE_DEFAULT, 0),
            'query' => new external_value(
                PARAM_RAW,
                'The search term as typed, at most 1000 bytes. The server normalises and truncates it.',
                VALUE_DEFAULT,
                ''
            ),
            'perpage' => new external_value(
                PARAM_INT,
                'The page size the address asked for, zero for the site default.',
                VALUE_DEFAULT,
                0
            ),
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    'fieldid' => new external_value(PARAM_INT, 'The course custom field, from the site allowlist.'),
                    'value' => new external_value(PARAM_INT, 'The option of that field to keep.'),
                ]),
                'One entry per custom field filter, at most one per field and at most four.',
                VALUE_DEFAULT,
                []
            ),
            'sort' => new external_value(
                PARAM_ALPHA,
                'How the listing is organised: empty or category for the grouped default, name, or recent.',
                VALUE_DEFAULT,
                ''
            ),
            'datefrom' => new external_value(
                PARAM_RAW_TRIMMED,
                'The first start date kept, as YYYY-MM-DD in the site timezone; empty for no lower bound.',
                VALUE_DEFAULT,
                ''
            ),
            'dateto' => new external_value(
                PARAM_RAW_TRIMMED,
                'The last start date kept, as YYYY-MM-DD in the site timezone, inclusive; empty for no upper bound.',
                VALUE_DEFAULT,
                ''
            ),
            'cat' => new external_value(
                PARAM_INT,
                'The category of the navigation tree the listing is narrowed to; one the page does not offer '
                    . 'is ignored. Zero for the whole subtree.',
                VALUE_DEFAULT,
                0
            ),
            'withcategories' => new external_value(
                PARAM_BOOL,
                'Whether this is a public area\'s panel: the answer then carries the panel\'s context.',
                VALUE_DEFAULT,
                false
            ),
            'slice' => new external_value(
                PARAM_INT,
                'A merely listed child of the category whose panel is asked for, as its slice of the '
                    . 'category\'s listing; 0 for none. Flat order, first page only.',
                VALUE_DEFAULT,
                0
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
     * One page of a public category's showcase, for a visitor.
     *
     * @param int $categoryid The public category whose subtree is listed, 0 for the root.
     * @param int $page The zero-based page.
     * @param string $query The search term as typed.
     * @param int $perpage The page size the address asked for, 0 for the site default.
     * @param array $filters One entry of fieldid and value per custom field filter.
     * @param string $sort How the listing is organised, '' for the grouped default.
     * @param string $datefrom The first start date kept, as YYYY-MM-DD, '' for no bound.
     * @param string $dateto The last start date kept, as YYYY-MM-DD, '' for no bound.
     * @param int $cat The category of the tree the listing is narrowed to, 0 for the whole subtree.
     * @param bool $withcategories Whether this is a public area's panel.
     * @param int $slice The merely listed child whose slice of the listing is the panel, 0 for none.
     * @param string $lang The language of the page that asks, '' for the request's own.
     * @return array The page, in {@see get_category_rows::execute_returns()}'s shape.
     * @throws \invalid_parameter_exception On a malformed request, whatever the id.
     * @throws \moodle_exception The one refusal, for every request that may not be answered.
     */
    public static function execute(
        int $categoryid,
        int $page = 0,
        string $query = '',
        int $perpage = 0,
        array $filters = [],
        string $sort = '',
        string $datefrom = '',
        string $dateto = '',
        int $cat = 0,
        bool $withcategories = false,
        int $slice = 0,
        string $lang = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'page' => $page,
            'query' => $query,
            'perpage' => $perpage,
            'filters' => $filters,
            'sort' => $sort,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
            'cat' => $cat,
            'withcategories' => $withcategories,
            'slice' => $slice,
            'lang' => $lang,
        ]);

        // Counted before anything can refuse, so a batch of refused calls is cut to one too.
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
            throw self::refusal();
        }

        $categoryid = (int) $params['categoryid'];
        $args = self::wellformed($categoryid, $params);

        $previous = null;
        if ($params['lang'] !== '' && get_string_manager()->translation_exists($params['lang'], false)) {
            $previous = force_current_language($params['lang']);
        }
        try {
            return self::answer($categoryid, $args);
        } finally {
            if ($previous !== null) {
                force_current_language($previous);
            }
        }
    }

    /**
     * The cheap shape checks: a malformed request is malformed for every id, and none of them reads anything.
     *
     * @param int $categoryid The category the request names.
     * @param array $params The validated parameters.
     * @return array The request as {@see rows::fetch()} takes it, anonymous.
     * @throws \invalid_parameter_exception On a malformed request.
     */
    private static function wellformed(int $categoryid, array $params): array {
        // Bytes, before anything counts characters: the cap bounds the work, a real term is far shorter.
        if (strlen($params['query']) > self::MAX_QUERY_BYTES) {
            throw new \invalid_parameter_exception('The term is longer than ' . self::MAX_QUERY_BYTES . ' bytes.');
        }
        if ($params['page'] < 0) {
            throw new \invalid_parameter_exception('The page must not be negative.');
        }
        if (!in_array($params['sort'], criteria::SORTS, true)) {
            throw new \invalid_parameter_exception('Unknown sort: ' . $params['sort']);
        }
        foreach (['datefrom', 'dateto'] as $bound) {
            if ($params[$bound] !== '' && criteria::parse_date((string) $params[$bound]) === '') {
                throw new \invalid_parameter_exception('The ' . $bound . ' must be a date as YYYY-MM-DD.');
            }
        }
        if ($params['datefrom'] !== '' && $params['dateto'] !== '' && strcmp($params['datefrom'], $params['dateto']) > 0) {
            throw new \invalid_parameter_exception('The datefrom must not be after the dateto.');
        }
        if (count($params['filters']) > facets::MAX_FIELDS) {
            throw new \invalid_parameter_exception('At most ' . facets::MAX_FIELDS . ' filters.');
        }
        $fields = [];
        foreach ($params['filters'] as $filter) {
            $fieldid = (int) $filter['fieldid'];
            if (isset($fields[$fieldid])) {
                throw new \invalid_parameter_exception('One filter per field: ' . $fieldid);
            }
            $fields[$fieldid] = (int) $filter['value'];
        }
        // The root reads its term alone, as its page does; the language is the request's, not the root's.
        if ($categoryid === 0) {
            $unused = $params['page'] !== 0
                || $params['perpage'] !== 0
                || $params['filters'] !== []
                || $params['sort'] !== ''
                || $params['datefrom'] !== ''
                || $params['dateto'] !== ''
                || $params['cat'] !== 0
                || $params['withcategories']
                || $params['slice'] !== 0;
            if ($unused) {
                throw new \invalid_parameter_exception('The root of the course index reads its search alone.');
            }
        }

        // Clamped exactly as criteria::from_request() clamps the anonymous page's own perpage.
        $perpage = (int) $params['perpage'];
        $args = [
            'page' => (int) $params['page'],
            'query' => (string) $params['query'],
            'pagesize' => $perpage > 0 ? min($perpage, population::MAX_PAGESIZE) : null,
            'filters' => $fields,
            'sort' => (string) $params['sort'],
            'datefrom' => (string) $params['datefrom'],
            'dateto' => (string) $params['dateto'],
            'cat' => (int) $params['cat'],
            'withcategories' => (bool) $params['withcategories'],
            'slice' => (int) $params['slice'],
            'anonymous' => true,
        ];
        if ($args['slice'] !== 0) {
            $args = rows::require_wellformed_slice($categoryid, $args);
        }
        return $args;
    }

    /**
     * The gates, the lookups and the delegate: steps 7 to 11 of the class docblock.
     *
     * @param int $categoryid The category the request names.
     * @param array $args The well-formed request.
     * @return array The page.
     * @throws \invalid_parameter_exception For a filter field outside the allowlist.
     * @throws \moodle_exception The one refusal.
     */
    private static function answer(int $categoryid, array $args): array {
        global $PAGE;

        // Off unless a stored '1' opens it: read beside is_public(), never inside it (see client_enabled()).
        if (!publicaccess::client_enabled()) {
            throw self::refusal();
        }
        if (!publicaccess::is_public($categoryid)) {
            throw self::refusal();
        }

        $context = $categoryid === 0
            ? \core\context\system::instance()
            : \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
        if ($context === false) {
            // The category went between the predicate's read and this one.
            throw self::refusal();
        }
        $PAGE->reset_theme_and_output();
        $PAGE->set_context($context);

        $category = \core_course_category::get($categoryid, IGNORE_MISSING, true);
        if (!$category) {
            throw self::refusal();
        }

        $allowed = facets::fields();
        foreach (array_keys($args['filters']) as $fieldid) {
            if (!isset($allowed[$fieldid])) {
                throw new \invalid_parameter_exception('Unknown filter field: ' . $fieldid);
            }
        }

        return rows::fetch($category, $args);
    }

    /**
     * The one refusal, with the spelling every surface of the showcase uses for a category.
     *
     * @return \moodle_exception The refusal to throw.
     */
    private static function refusal(): \moodle_exception {
        return new \moodle_exception(guard::ERROR_CATEGORY, self::COMPONENT);
    }

    /**
     * Forget the calls counted in this process, as a new request would.
     *
     * PHPUnit runs every call of a test in one process; the test fixture calls this between them.
     *
     * @return void
     */
    public static function reset(): void {
        self::$calls = 0;
    }

    /**
     * What the function returns: the logged-in function's shape, key for key.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_category_rows::execute_returns();
    }
}
