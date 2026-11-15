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
 * Theme Dimensions - One page of the category showcase, for the client
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
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\rows;

/**
 * The paging, filtering and searching function of the showcase: one call, one page of cards.
 *
 * One function rather than three (a pager, a filterer, a searcher): the three
 * would triple the guard surface and the budget tests over one answer that is
 * always the same shape. Every refusal is the guard's
 * ({@see \theme_dimensions\local\category\guard}), the whole answer
 * is {@see rows::fetch()}'s, and this class is the order in which the two are
 * asked, which matters:
 *
 * 1. validate_parameters().
 * 2. The cheap vocabulary and shape checks, before login - a negative page, a
 *    chip or sort outside its vocabulary, a date that is not one or a pair of
 *    them given backwards, anything but the term at the root, two filters
 *    naming one field. The chip's vocabulary is the state chips alone: the
 *    favourites are `fav`, and the older spelling of them as a chip is read
 *    only off an address ({@see criteria::from_request()}), never here.
 * 3. require_login() and the guest account's refusal, which is the same
 *    refusal every miss carries, so a guest learns nothing by asking.
 * 4-7. The guard: the non-throwing context, validate_context(), core's gate,
 *    the plugin's gate.
 * 8. The filters' fields against the configured allowlist, after the gates.
 * 9. The delegate, which drops a `cat` it does not offer ({@see population::cat_ids()}).
 *
 * The tree's `cat` is never refused, whatever it names: an id that is not below the listed
 * category, one that does not exist and one withheld from this viewer are each dropped by
 * {@see population::resolve()}, and the answer is the one a request with no `cat` gets, byte for
 * byte. A refusal for some of them would tell an account which category ids exist, and which of
 * those are withheld from it, in a subtree it may not browse.
 *
 * The returns are an allowlist: clean_returnvalue() silently drops what the
 * declaration does not name. The client renders the cards through the same
 * template as the server, so {@see card_structure()} declares every key of
 * {@see \theme_dimensions\output\category_card}'s context, and a test
 * compares the two key sets in both directions, because an undeclared key never
 * reaches the payload and so can never be noticed in it.
 * {@see panel_structure()} is the same arrangement over the panel's context.
 *
 * The root of the course index is this function with `categoryid` 0: its search
 * line is live, so the root answers a term with its posters' numbers and the
 * areas' result mode. It reads the term alone - anything else sent with
 * category 0 is refused as malformed - and the guard validates the system
 * context for it.
 *
 * A panel between the rows of posters is this function with `withcategories`:
 * the area's own listing over its whole subtree in a flat order, plus the
 * panel's context, whose strip names the area's admitted direct subareas. The
 * category the client names is the panel's own, so the guard chain applies to
 * it and no grouped statement exists anywhere.
 *
 * Which strings are PARAM_RAW, and why. PARAM_TEXT is validated on the way out:
 * clean_returnvalue() throws invalid_response_exception - for the whole
 * response, not the row - whenever strip_tags() would change the value, which a
 * bare "<" followed by a non-space does. A course's name or a landing page value
 * (the meta line's city) holding "&lt;3" decodes to "<3" and would fail the page
 * for everyone, as the course dialogue's excerpt would its own answer
 * ({@see get_course_dialogue}). So every string composed from a language string
 * is PARAM_TEXT, and every string carrying a course's own name or content is
 * PARAM_RAW - as is every sentence carrying a category's name or the search
 * term (the count line, the panel's subtitle and zero state, the summary's
 * sentences), since the term arrives raw and "<3" is a search. That is not a
 * weaker guarantee than the server-rendered page has: the client renders these
 * values through the same Mustache double stashes, which escape for themselves.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_category_rows extends external_api {
    /** @var string Component name for get_string calls and refusals. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'The category whose subtree is listed.'),
            'page' => new external_value(PARAM_INT, 'The zero-based page of the listing.', VALUE_DEFAULT, 0),
            'query' => new external_value(
                PARAM_RAW,
                'The search term. The server normalises and truncates it; the client sends what was typed.',
                VALUE_DEFAULT,
                ''
            ),
            'chip' => new external_value(
                PARAM_ALPHA,
                'The state chip, empty for all of them.',
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
                'One entry per custom field filter, at most one per field.',
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
                'The first start date kept, as YYYY-MM-DD in the site timezone; empty for no lower bound. '
                    . 'It may be sent without dateto, and never after it.',
                VALUE_DEFAULT,
                ''
            ),
            'dateto' => new external_value(
                PARAM_RAW_TRIMMED,
                'The last start date kept, as YYYY-MM-DD in the site timezone, inclusive; empty for no upper bound. '
                    . 'It may be sent without datefrom.',
                VALUE_DEFAULT,
                ''
            ),
            'cat' => new external_value(
                PARAM_INT,
                'The category of the navigation tree the listing is narrowed to. One that is not an admitted '
                    . 'category below the listed one is ignored, never refused. Zero for the whole subtree.',
                VALUE_DEFAULT,
                0
            ),
            'withcategories' => new external_value(
                PARAM_BOOL,
                'Whether this is a panel\'s request: the answer then carries the panel\'s context.',
                VALUE_DEFAULT,
                false
            ),
            // Last, because the framework hands the parameters to execute() in this order.
            'fav' => new external_value(
                PARAM_BOOL,
                'Whether only the reader\'s favourite courses are kept, combined with the state chip.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * One page of the showcase.
     *
     * @param int $categoryid The category whose subtree is listed.
     * @param int $page The zero-based page.
     * @param string $query The search term as typed.
     * @param string $chip The state chip, '' for all.
     * @param int $perpage The page size the address asked for, 0 for the site default.
     * @param array $filters One entry of fieldid and value per custom field filter.
     * @param string $sort How the listing is organised, '' for the grouped default.
     * @param string $datefrom The first start date kept, as YYYY-MM-DD, '' for no bound.
     * @param string $dateto The last start date kept, as YYYY-MM-DD, '' for no bound.
     * @param int $cat The category of the tree the listing is narrowed to, 0 for the whole subtree.
     * @param bool $withcategories Whether this is a panel's request, answered with the panel's context.
     * @param bool $fav Whether only the reader's favourite courses are kept.
     * @return array The page: its numbers, its cards and the option lists of its toolbar.
     * @throws \invalid_parameter_exception On a malformed request.
     * @throws \moodle_exception The one refusal, for every category this viewer may not list.
     */
    public static function execute(
        int $categoryid,
        int $page = 0,
        string $query = '',
        string $chip = '',
        int $perpage = 0,
        array $filters = [],
        string $sort = '',
        string $datefrom = '',
        string $dateto = '',
        int $cat = 0,
        bool $withcategories = false,
        bool $fav = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'page' => $page,
            'query' => $query,
            'chip' => $chip,
            'perpage' => $perpage,
            'filters' => $filters,
            'sort' => $sort,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
            'cat' => $cat,
            'withcategories' => $withcategories,
            'fav' => $fav,
        ]);

        /* The cheap checks, before login: a malformed request is malformed for anybody, and
           none of these answers anything about what exists on the site. */
        if ($params['page'] < 0) {
            throw new \invalid_parameter_exception('The page must not be negative.');
        }
        if ($params['chip'] !== '' && !in_array($params['chip'], classifier::filters(), true)) {
            throw new \invalid_parameter_exception('Unknown chip: ' . $params['chip']);
        }
        /* The organisation, against the same allowlist the address is read with. The page drops an
           unknown value and renders - it is a query string somebody may have edited - while this
           function refuses it: its only client is this theme's own module, and a value it never
           sends is a defect worth seeing rather than a listing that silently came back in another
           order. The root is refused for the same reason; the tree's cat is not refused at all (see
           the class docblock). */
        if (!in_array($params['sort'], criteria::SORTS, true)) {
            throw new \invalid_parameter_exception('Unknown sort: ' . $params['sort']);
        }
        foreach (['datefrom', 'dateto'] as $bound) {
            if ($params[$bound] !== '' && criteria::parse_date((string) $params[$bound]) === '') {
                throw new \invalid_parameter_exception('The ' . $bound . ' must be a date as YYYY-MM-DD.');
            }
        }
        /* Either bound may come alone (criteria states what each keeps). A pair given backwards is
           put in order when it arrives on an address, and refused here: the module orders every
           pair it sends, so one that is not ordered is a defect in the client. */
        if ($params['datefrom'] !== '' && $params['dateto'] !== '' && strcmp($params['datefrom'], $params['dateto']) > 0) {
            throw new \invalid_parameter_exception('The datefrom must not be after the dateto.');
        }
        /* The root reads its search and nothing else, as its page does; anything more is refused
           rather than ignored, as an unknown sort is above. */
        if ((int) $params['categoryid'] === 0) {
            $unused = $params['page'] !== 0
                || $params['chip'] !== ''
                || $params['perpage'] !== 0
                || $params['filters'] !== []
                || $params['sort'] !== ''
                || $params['datefrom'] !== ''
                || $params['dateto'] !== ''
                || $params['cat'] !== 0
                || $params['withcategories']
                || $params['fav'];
            if ($unused) {
                throw new \invalid_parameter_exception('The root of the course index reads its search alone.');
            }
        }
        /* The page size is clamped exactly as criteria::from_request() clamps the address's own
           perpage, because it is that parameter: a listing opened at ?perpage=6 whose second page
           came back cut at the site default would append rows the reader has already seen and
           skip the ones between. Zero means the site default, which is what page_size(null) reads. */
        $perpage = (int) $params['perpage'];
        $pagesize = $perpage > 0 ? min($perpage, population::MAX_PAGESIZE) : null;
        $fields = [];
        foreach ($params['filters'] as $filter) {
            $fieldid = (int) $filter['fieldid'];
            if (isset($fields[$fieldid])) {
                throw new \invalid_parameter_exception('One filter per field: ' . $fieldid);
            }
            $fields[$fieldid] = (int) $filter['value'];
        }

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception(guard::ERROR_CATEGORY, self::COMPONENT);
        }

        $category = guard::category((int) $params['categoryid']);

        // The allowlist of the filter groups: after the gates, because a field id is site-wide.
        $allowed = facets::fields();
        foreach (array_keys($fields) as $fieldid) {
            if (!isset($allowed[$fieldid])) {
                throw new \invalid_parameter_exception('Unknown filter field: ' . $fieldid);
            }
        }

        return rows::fetch($category, [
            'page' => (int) $params['page'],
            'query' => (string) $params['query'],
            'chip' => (string) $params['chip'],
            'pagesize' => $pagesize,
            'filters' => $fields,
            'sort' => (string) $params['sort'],
            'datefrom' => (string) $params['datefrom'],
            'dateto' => (string) $params['dateto'],
            'cat' => (int) $params['cat'],
            'withcategories' => (bool) $params['withcategories'],
            'fav' => (bool) $params['fav'],
        ]);
    }

    /**
     * What the function returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'categoryid' => new external_value(PARAM_INT, 'The category this page belongs to.'),
            'page' => new external_value(PARAM_INT, 'The zero-based page this is.'),
            'pagesize' => new external_value(PARAM_INT, 'How many cards a full page holds.'),
            'total' => new external_value(PARAM_INT, 'How many courses match the request, post-filter.'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether a page follows this one.'),
            'countnumber' => new external_value(PARAM_TEXT, 'The count line\'s number, formatted, which the design sets in bold.'),
            'countline' => new external_value(
                PARAM_RAW,
                'The rest of the count line after its number, carrying a category\'s name and the term: the sink escapes it.'
            ),
            'seeallurl' => new external_value(
                PARAM_URL,
                'The flat Recent listing carrying the criterion, offered beside a narrowed grouped page; empty otherwise.'
            ),
            'nexturl' => new external_value(PARAM_URL, 'The address of the next page, empty on the last one.'),
            'clearurl' => new external_value(PARAM_URL, 'The address of the listing with no filter at all.'),
            'hasfilters' => new external_value(PARAM_BOOL, 'Whether anything narrows this listing.'),
            'filtercount' => new external_value(PARAM_INT, 'How many filters the panel holds, for the Filters badge.'),
            'filtercountlabel' => new external_value(PARAM_TEXT, 'The same number as the sentence a screen reader is read.'),
            'chips' => new external_multiple_structure(
                new external_single_structure([
                    'param' => new external_value(
                        PARAM_ALPHANUMEXT,
                        'What the chip drops: q for the search, a field\'s parameter (cf_<id>), chip, cat, '
                            . 'or period for both bounds.'
                    ),
                    'label' => new external_value(PARAM_RAW, 'The chip\'s label, plain: the sink escapes it.'),
                    'url' => new external_value(PARAM_URL, 'The listing with that one criterion dropped and the rest kept.'),
                    'removelabel' => new external_value(PARAM_TEXT, 'The words that make the chip\'s name say what a press does.'),
                ]),
                'One chip per criterion in force that the filter line does not show, in the row under it.'
            ),
            'clearall' => new external_value(PARAM_BOOL, 'Whether "Clear all" is offered: two criteria or more narrow it.'),
            'searchtooshort' => new external_value(PARAM_BOOL, 'Whether a term was sent that is too short to search.'),
            'cards' => new external_multiple_structure(self::card_structure(), 'The cards of this page, in order.'),
            'statusgroup' => new external_single_structure([
                'options' => new external_multiple_structure(
                    self::option_structure(PARAM_ALPHA, 'the state chip'),
                    'The state pills the toolbar would draw, the "all" one first.'
                ),
            ], 'The state group; its options are empty where the toolbar offers no state group.'),
            'favourites' => new external_single_structure([
                'available' => new external_value(PARAM_BOOL, 'Whether the toolbar draws the favourites toggle for this reader.'),
                'pressed' => new external_value(PARAM_BOOL, 'Whether the toggle is on: only favourites are kept.'),
                'count' => new external_value(PARAM_INT, 'How many favourites the state chip in force leaves, 0 where not drawn.'),
            ], 'The favourites toggle, which combines with the state chip.'),
            'facets' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'The form name of the group, cf_<fieldid>.'),
                    'legend' => new external_value(PARAM_RAW, 'The field name, as the site stored it.'),
                    'anyid' => new external_value(PARAM_ALPHANUMEXT, 'The element id of the group\'s "all" option.'),
                    'anylabel' => new external_value(PARAM_TEXT, 'The label of that option.'),
                    'anychecked' => new external_value(PARAM_BOOL, 'Whether no option of this group is picked.'),
                    'options' => new external_multiple_structure(
                        self::option_structure(PARAM_INT, 'the option of the custom field'),
                        'The option pills of this group.'
                    ),
                ]),
                'One group per allowlisted custom field, in the configured order.'
            ),
            'accordion' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The child category.'),
                    'name' => new external_value(PARAM_RAW, 'Its name, plain: the sink escapes it.'),
                    'count' => new external_value(PARAM_INT, 'How many courses of this request sit in its subtree.'),
                    'url' => new external_value(PARAM_URL, 'Its own page, empty where there is none to open.'),
                    'linkable' => new external_value(PARAM_BOOL, 'Whether there is a page to open.'),
                    'ctaurl' => new external_value(
                        PARAM_URL,
                        'Where its panel\'s call to action goes under this request, in the newest-first order.'
                    ),
                    'ctalabel' => new external_value(
                        PARAM_RAW,
                        'That call to action\'s label, carrying the area\'s name: the sink escapes it.'
                    ),
                    'resultlabel' => new external_value(
                        PARAM_TEXT,
                        'The poster\'s pill under a criterion ("5 results"), empty at rest.'
                    ),
                    'resultcount' => new external_value(PARAM_TEXT, 'The pill\'s number alone, formatted, empty at rest.'),
                    'expandable' => new external_value(
                        PARAM_BOOL,
                        'Whether the poster opens a panel rather than linking to its page.'
                    ),
                ]),
                'One entry per poster of the grouped organisation, so the posters can be repainted after a '
                    . 'filter change; empty on a flat organisation and on a panel\'s own request.'
            ),
            'areas' => self::areas_structure(),
            'panel' => self::panel_structure(),
        ]);
    }

    /**
     * What a grouped page in result mode says about its areas as a whole, key for key.
     *
     * Public for the reason {@see card_structure()} is: the parity test compares these keys with
     * what {@see \theme_dimensions\output\category_listing::results()} really builds.
     * Always present; off result mode (a page at rest, a flat organisation, a panel's own request)
     * every value is its default but the reveal's label at rest.
     *
     * @return external_single_structure
     */
    public static function areas_structure(): external_single_structure {
        return new external_single_structure([
            'filtered' => new external_value(PARAM_BOOL, 'Whether the page is grouped, has areas and a criterion narrows it.'),
            'nomatchlabelall' => new external_value(
                PARAM_RAW,
                'The "nothing in N areas" sentence counting every area with no result, carrying the term; empty for none.'
            ),
            'nomatchlabelless' => new external_value(
                PARAM_RAW,
                'The same sentence counting one area fewer, for while the open area is one of them; empty for none.'
            ),
            'elsewhere' => new external_value(
                PARAM_TEXT,
                'How many other areas hold a result, as a zero panel\'s announcement ends.'
            ),
            'moreresults' => new external_value(PARAM_INT, 'How many areas behind the reveal hold a result under a criterion.'),
            'morelabel' => new external_value(
                PARAM_TEXT,
                'The reveal\'s label: its results under a criterion, else its count at rest.'
            ),
            'allempty' => new external_value(PARAM_BOOL, 'Whether nothing matched anywhere, here or in any area.'),
            'autoopen' => new external_value(PARAM_INT, 'The one area holding every result when nothing sits here, else 0.'),
            'autoopenlabel' => new external_value(
                PARAM_RAW,
                'The sentence the count line appends when that area opens by itself, carrying its name.'
            ),
            'searchallurl' => new external_value(
                PARAM_URL,
                'The root carrying the search term, offered where nothing matched here; empty where there is none to offer.'
            ),
        ], 'The areas in result mode.');
    }

    /**
     * The panel's context, key for key: what category_panel.mustache renders, less its cards.
     *
     * Public for the reason {@see card_structure()} is: the parity test compares these keys with
     * what {@see \theme_dimensions\output\category_panel::export_for_ws()} really
     * builds, in both directions. Present only on a panel's own request.
     *
     * Every string carrying the area's name or the search term is PARAM_RAW (the class docblock
     * says why); the sentences composed from a language string and a formatted number are
     * PARAM_TEXT. The zero state's suggestions are page data, merged by the client, and absent. The
     * three no-JavaScript addresses are always empty here: the client draws buttons there.
     *
     * @return external_single_structure
     */
    public static function panel_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The area.'),
            'name' => new external_value(PARAM_RAW, 'Its name, plain: the sink escapes it.'),
            'titleurl' => new external_value(PARAM_URL, 'Its own page, in its default organisation.'),
            'haslink' => new external_value(PARAM_BOOL, 'Whether the title links to that page.'),
            'isd20' => new external_value(PARAM_BOOL, 'Whether the area is a visitor\'s merely listed child.'),
            'subtitle' => new external_value(
                PARAM_RAW,
                'How many of how many courses, or how many there are; under a criterion, results for the term.'
            ),
            'psortname' => new external_value(PARAM_BOOL, 'Whether the panel is in A-Z order.'),
            'sorturlrecent' => new external_value(PARAM_URL, 'The no-JavaScript address of the newest-first order: empty here.'),
            'sorturlname' => new external_value(PARAM_URL, 'The no-JavaScript address of the A-Z order: empty here.'),
            'closeurl' => new external_value(PARAM_URL, 'The no-JavaScript address that closes the panel: empty here.'),
            'also' => new external_single_structure([
                'has' => new external_value(PARAM_BOOL, 'Whether the strip is drawn.'),
                'pills' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'The subarea.'),
                        'name' => new external_value(PARAM_RAW, 'Its name, plain: the sink escapes it.'),
                        'url' => new external_value(PARAM_URL, 'Its own page, empty where there is none to open.'),
                        'haslink' => new external_value(PARAM_BOOL, 'Whether the pill is a link.'),
                        'style' => new external_value(PARAM_NOTAGS, 'Its dot\'s colour, as one custom property.'),
                    ]),
                    'Up to six subareas, in the tree\'s order.'
                ),
                'hasmore' => new external_value(PARAM_BOOL, 'Whether more subareas follow the six.'),
                'morecount' => new external_value(PARAM_TEXT, 'How many, formatted.'),
                'moreurl' => new external_value(PARAM_URL, 'Where they all are: the area\'s own page.'),
            ], 'The strip of the area\'s subareas.'),
            'hascards' => new external_value(PARAM_BOOL, 'Whether the rail holds any course.'),
            'few' => new external_value(PARAM_BOOL, 'Whether it holds three or fewer.'),
            'counter' => new external_value(PARAM_TEXT, 'Where the rail is scrolled to, for a phone; empty for a few.'),
            'tile' => new external_single_structure([
                'has' => new external_value(PARAM_BOOL, 'Whether the rail ends with the tile.'),
                'count' => new external_value(PARAM_TEXT, 'How many more courses the area holds, formatted.'),
                'label' => new external_value(PARAM_TEXT, 'The word under that number.'),
                'url' => new external_value(PARAM_URL, 'Where it goes: the call to action\'s address.'),
            ], 'The tile at the end of the rail.'),
            'isrows' => new external_value(PARAM_BOOL, 'Whether the panel lists courses.'),
            'isempty' => new external_value(PARAM_BOOL, 'Whether the area has no course at all.'),
            'iszero' => new external_value(PARAM_BOOL, 'Whether none of its courses matches the request.'),
            'zero' => new external_value(
                PARAM_RAW,
                'The zero state\'s sentence, carrying the term and the area\'s name; empty otherwise.'
            ),
            'cta' => new external_single_structure([
                'has' => new external_value(PARAM_BOOL, 'Whether the call to action is drawn.'),
                'url' => new external_value(PARAM_URL, 'The area\'s page, flat, in the panel\'s order, narrowed as asked.'),
                'label' => new external_value(PARAM_RAW, 'Its label, carrying the area\'s name: the sink escapes it.'),
            ], 'The call to action.'),
            'seeaslist' => new external_single_structure([
                'has' => new external_value(PARAM_BOOL, 'Whether the "see all as a list" link is drawn instead.'),
                'url' => new external_value(PARAM_URL, 'The parent\'s listing narrowed to the area.'),
            ], 'The way out of a visitor\'s merely listed area.'),
            'jsmode' => new external_value(PARAM_BOOL, 'Whether the controls are the client\'s buttons: the client sets it.'),
            'isloading' => new external_value(PARAM_BOOL, 'The client\'s loading state: always false here.'),
            'isskeleton' => new external_value(PARAM_BOOL, 'The client\'s skeleton state: always false here.'),
            'iserror' => new external_value(PARAM_BOOL, 'The client\'s error state: always false here.'),
        ], 'The panel, on a panel\'s own request only.', VALUE_OPTIONAL);
    }

    /**
     * One pill of a radio group, as the toolbar draws it.
     *
     * @param string $valuetype The parameter type of the option's value.
     * @param string $what What the value names, for the descriptions.
     * @return external_single_structure
     */
    private static function option_structure(string $valuetype, string $what): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_ALPHANUMEXT, 'The element id of the radio input.'),
            'value' => new external_value($valuetype, 'The value of ' . $what . ', empty or zero for all of them.'),
            'label' => new external_value(PARAM_RAW, 'The label of the pill.'),
            'count' => new external_value(
                PARAM_INT,
                'How many courses the option holds, null where the count was suppressed.',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'hascount' => new external_value(PARAM_BOOL, 'Whether there is a count to print.'),
            'checked' => new external_value(PARAM_BOOL, 'Whether this option is the one in force.'),
        ]);
    }

    /**
     * The card context, key for key: the row schema of this function.
     *
     * Public because a parity test reads the declared keys from here and
     * compares them with what the card really exports, in both directions.
     *
     * @return external_single_structure
     */
    public static function card_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The course id.'),
            'fullname' => new external_value(PARAM_RAW, 'The course name, plain: the sink escapes it.'),
            'url' => new external_value(
                PARAM_URL,
                'Where the card links, null when it links nowhere.',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'hasurl' => new external_value(PARAM_BOOL, 'Whether the card links anywhere.'),
            'overline' => new external_value(
                PARAM_RAW,
                'The course\'s own subcategory, null in the listed root.',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'hasoverline' => new external_value(PARAM_BOOL, 'Whether there is a subcategory to print.'),
            'imageurl' => new external_value(
                PARAM_RAW,
                'The card image: a URL, or a data URI for a course with none of its own; null for the flat fallback.',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether there is an image.'),
            'eager' => new external_value(PARAM_BOOL, 'Whether the cover is in the first row of the first page, loaded at once.'),
            'priority' => new external_value(PARAM_BOOL, 'Whether the cover is fetched ahead of every other image.'),
            'showcover' => new external_value(PARAM_BOOL, 'Whether the card view draws the cover (Boost Union\'s setting).'),
            'showthumb' => new external_value(PARAM_BOOL, 'Whether the list view draws the thumbnail (Boost Union\'s setting).'),
            'showmedia' => new external_value(PARAM_BOOL, 'Whether the media band is drawn: either view shows it.'),
            'showprogress' => new external_value(PARAM_BOOL, 'Whether the progress row is drawn (Boost Union\'s setting).'),
            'hasprogress' => new external_value(PARAM_BOOL, 'Whether this card draws the progress row: an enrolled one.'),
            'band' => new external_value(
                PARAM_TEXT,
                'The course category\'s colour, #rrggbb, edging a card or row drawn without its image; empty when not read.'
            ),
            'hasband' => new external_value(PARAM_BOOL, 'Whether the card carries its category\'s colour.'),
            'hastop' => new external_value(PARAM_BOOL, 'Whether the card draws the top row of its body in its cover\'s place.'),
            'badges' => new external_multiple_structure(
                new external_single_structure([
                    'url' => new external_value(PARAM_URL, 'The badge image, through the theme\'s own file route.'),
                    'alt' => new external_value(PARAM_RAW, 'Its alternative text: the file name, as the editor named it.'),
                ]),
                'The course\'s own landing page badges, in grid order; empty for a course with none.'
            ),
            'hasbadges' => new external_value(PARAM_BOOL, 'Whether there are badges to draw.'),
            'badgeslabel' => new external_value(PARAM_TEXT, 'The label of the badge list.'),
            'isevent' => new external_value(PARAM_BOOL, 'Whether the course chose the event model.'),
            'kindlabel' => new external_value(
                PARAM_TEXT,
                'The model label, null where no model was chosen.',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'kindicon' => new external_value(
                PARAM_ALPHANUMEXT,
                'The model\'s Font Awesome class (fa-graduation-cap, fa-calendar-day, fa-route), null where no model was chosen.',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'haskind' => new external_value(PARAM_BOOL, 'Whether there is a model label.'),
            'datetile' => new external_single_structure([
                'day' => new external_value(PARAM_TEXT, 'The day of the month.'),
                'month' => new external_value(PARAM_TEXT, 'The abbreviated month.'),
            ], 'The event\'s date tile, null off an event with a start date.', VALUE_REQUIRED, null, NULL_ALLOWED),
            'meta' => new external_value(PARAM_RAW, 'The line under the title: date or workload, modality, city.'),
            'rating' => new external_single_structure([
                'avg' => new external_value(PARAM_TEXT, 'The average to one decimal, in the reader\'s language.'),
                'count' => new external_value(PARAM_INT, 'How many ratings.'),
                'label' => new external_value(PARAM_TEXT, 'The sentence a screen reader is told for the average and the count.'),
            ], 'tool_courserating\'s rating opening the meta line, null where none.', VALUE_REQUIRED, null, NULL_ALLOWED),
            'hasrating' => new external_value(PARAM_BOOL, 'Whether the card carries a rating.'),
            'state' => new external_value(PARAM_ALPHA, 'The classifier state, empty on an anonymous card.'),
            'chip' => new external_value(PARAM_ALPHA, 'The state chip the course counts under, empty on an anonymous card.'),
            'fieldvalues' => new external_value(
                PARAM_TEXT,
                'The filter fields this card may state, as space-separated fieldid:value pairs (0 for no value).'
            ),
            'hasstartday' => new external_value(PARAM_BOOL, 'Whether the card states its start day.'),
            'startday' => new external_value(
                PARAM_ALPHANUMEXT,
                'The start day in the site timezone, YYYY-MM-DD; empty for a course with no start date.'
            ),
            'badge' => new external_single_structure([
                'label' => new external_value(
                    PARAM_TEXT,
                    'The badge text; a conditional one carries the prerequisite\'s name, formatted and stripped of markup.'
                ),
                'classes' => new external_value(PARAM_NOTAGS, 'The design system classes of the badge.'),
                'icon' => new external_value(PARAM_ALPHANUMEXT, 'The Font Awesome icon of the badge, decorative.'),
            ], 'The state badge.'),
            'hasroute' => new external_value(PARAM_BOOL, 'Whether the card says a method would also take the viewer now.'),
            'routelabel' => new external_value(PARAM_TEXT, 'That sentence, empty where there is none.'),
            'hasopens' => new external_value(PARAM_BOOL, 'Whether the card says when a refused method opens to the viewer.'),
            'openslabel' => new external_value(PARAM_TEXT, 'That sentence with its date, empty where there is none.'),
            'ishidden' => new external_value(PARAM_BOOL, 'Whether the course is hidden from students.'),
            'hiddenbadge' => new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'The badge text.'),
                'classes' => new external_value(PARAM_NOTAGS, 'The design system classes of the badge.'),
            ], 'The badge staff see on a hidden course.'),
            'ismuted' => new external_value(PARAM_BOOL, 'Whether the card is drawn in the muted style.'),
            'cta' => new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'The button text.'),
                'url' => new external_value(PARAM_URL, 'Where the button goes.'),
                'primary' => new external_value(PARAM_BOOL, 'Whether it is the filled button or the outline one.'),
            ], 'The call to action, null where the card draws none.', VALUE_REQUIRED, null, NULL_ALLOWED),
            'hascta' => new external_value(PARAM_BOOL, 'Whether there is a call to action.'),
            'showstar' => new external_value(PARAM_BOOL, 'Whether the favourite star is drawn.'),
            'favourite' => new external_value(PARAM_BOOL, 'Whether the course is in this person\'s favourites.'),
            'starlabel' => new external_value(PARAM_RAW, 'The star\'s label in its current state.'),
            'starlabeladd' => new external_value(PARAM_RAW, 'The label of the star once it means "add".'),
            'starlabelremove' => new external_value(PARAM_RAW, 'The label of the star once it means "remove".'),
            'hasdetails' => new external_value(PARAM_BOOL, 'Whether the card draws "More information", a signed-in reader\'s.'),
            'detailslabel' => new external_value(PARAM_TEXT, 'The text of the card\'s "More information" link.'),
            'done' => new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'The completed seal\'s text.'),
                'review' => new external_value(PARAM_TEXT, 'The main action\'s text once the course is completed.'),
            ], 'What an enrolled card becomes once completed, null on any other card.', VALUE_REQUIRED, null, NULL_ALLOWED),
            'modal' => new external_single_structure([
                'kicker' => new external_value(PARAM_RAW, 'The course\'s own category, plain; empty where it is not named.'),
                'haskicker' => new external_value(PARAM_BOOL, 'Whether the dialogue names the category.'),
                'primary' => new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'The button text; a disabled one carries its opening date.'),
                    'url' => new external_value(
                        PARAM_URL,
                        'Where the button goes, null on a disabled one.',
                        VALUE_REQUIRED,
                        null,
                        NULL_ALLOWED
                    ),
                    'disabled' => new external_value(PARAM_BOOL, 'Whether the button is a disabled statement of a later opening.'),
                    'note' => new external_value(PARAM_TEXT, 'What a disabled button tells a screen reader, empty otherwise.'),
                    'icon' => new external_value(PARAM_ALPHANUMEXT, 'The Font Awesome icon of the button, decorative.'),
                ], 'The primary button, null where the dialogue draws none.', VALUE_REQUIRED, null, NULL_ALLOWED),
                'hasprimary' => new external_value(PARAM_BOOL, 'Whether there is a primary button.'),
                'secondary' => new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'The button text: the course\'s page or the event\'s.'),
                    'url' => new external_value(PARAM_URL, 'The card\'s own link.'),
                ], 'The secondary button, null where it goes nowhere or repeats the primary.', VALUE_REQUIRED, null, NULL_ALLOWED),
                'hassecondary' => new external_value(PARAM_BOOL, 'Whether there is a secondary button.'),
            ], 'The course dialogue\'s keys the page decides, written onto the card\'s link; its content is fetched.'),
        ], 'One card, the context its own template renders.');
    }
}
