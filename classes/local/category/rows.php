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
 * Theme Dimensions - One page of the showcase, as the client asks for it
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use theme_dimensions\local\landing\links;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\category_panel;
use theme_dimensions\output\category_toolbar;

/**
 * The one method behind theme_dimensions_get_category_rows.
 *
 * The web service class holds no business logic, so this is where a client
 * request becomes a {@see criteria}, a {@see population} answer and the same
 * {@see category_listing} export the server-rendered page is built from. The
 * cards handed back are the page's card contexts, verbatim, and the client
 * renders them through the page's own template, so there is one place for a
 * card to be wrong.
 *
 * Two of the listing's constructor arguments are fixed here rather than
 * carried by the request. The view is always the card one: the card context
 * is identical in both views (the view is a class on the container and a user
 * preference). The manage actions are always empty: the web service never
 * renders a category's management menu.
 *
 * A panel between the rows of posters is this same method with
 * `withcategories` on: the area's own listing, over its whole subtree and in a
 * flat order, answered by the area's own gate chain, plus the panel's context
 * ({@see category_panel::export_for_ws()}) and the lean strip of its subareas.
 * So the panels need no second web service and no grouped SQL.
 *
 * The root of the course index, whose search line is live, is answered by
 * {@see root()}: the directory's posters with their numbers of matches and the
 * areas' result mode, and nothing of a listing.
 *
 * The same method answers a visitor when the caller says `anonymous`: the criteria are the
 * anonymous shell's and every address is the public page's, so the two shells cannot be told
 * apart card for card. A visitor's merely listed child has no page to ask for its panel by, so
 * it is asked for as a `slice` of its parent's listing ({@see slice()}).
 *
 * The listing's export carries more than the client needs, and the returns
 * declaration is an allowlist, so what is picked out below is the whole
 * contract. The next page travels as an address rather than a page number,
 * because the "continue on the next page" link needs it when the live-page
 * window closes, and it is the listing's own nexturl: the numbered pager and
 * the continue link are then the same address and cannot drift apart over a
 * filter one of them forgets to carry.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rows {
    /**
     * One page of the showcase for the viewer of the session.
     *
     * @param \core_course_category $category The category the guard already admitted and resolved:
     *   this method does not look it up again, so no MUST_EXIST lookup below the guard can raise
     *   dml_missing_record_exception in place of the guard's refusal.
     * @param array $args Any of page (int), query (string), chip (string, '' for none), fav (bool, the
     *   favourites toggle), pagesize (int|null, the address's own perpage), filters (array fieldid => option value),
     *   sort (string), datefrom (string, YYYY-MM-DD), dateto (string, YYYY-MM-DD), cat (int, the
     *   category of the navigation tree the listing is narrowed to), withcategories (bool, a panel's
     *   request: the panel's own context and the strip of the category's admitted direct
     *   subcategories), anonymous (bool, the visitor's public listing: its criteria and its
     *   addresses; the caller has already refused a logged-in session) and slice (int, a visitor's
     *   merely listed child whose slice of this category's listing is the panel, see
     *   {@see require_wellformed_slice()}).
     * @return array The payload of the web service, exactly the keys its returns declare.
     * @throws \moodle_exception The category refusal, if the population refuses what the guard admitted.
     * @throws \invalid_parameter_exception For a slice request that is not well formed.
     */
    public static function fetch(\core_course_category $category, array $args): array {
        $categoryid = (int) $category->id;
        $anonymous = (bool) ($args['anonymous'] ?? false);
        if (!empty($args['slice'])) {
            $args = self::require_wellformed_slice($categoryid, $args);
            $answer = self::slice($category, $args);
            if ($answer !== null) {
                return $answer;
            }
            /* A slice that names no merely listed child this page names is dropped, as a foreign
               tree criterion is: the answer is the category's own flat listing, which is the same
               bytes whether the id is a public child, a hidden one, a grandchild or nothing at all. */
        }
        if ($categoryid === 0) {
            return self::root($category, (string) ($args['query'] ?? ''), $anonymous);
        }
        $chip = (string) ($args['chip'] ?? '');
        $pagesize = $args['pagesize'] ?? null;
        $criteria = new criteria($categoryid, [
            'page' => (int) ($args['page'] ?? 0),
            'pagesize' => $pagesize === null ? null : (int) $pagesize,
            'search' => (string) ($args['query'] ?? ''),
            'state' => $chip === '' ? null : $chip,
            // Never a visitor's: the visitor's service declares no such parameter, and the population drops it anyway.
            'favourites' => !$anonymous && !empty($args['fav']),
            'fields' => (array) ($args['filters'] ?? []),
            'sort' => (string) ($args['sort'] ?? ''),
            /* The page's own fallback when the request names no organisation, exactly as the
               address resolves it: the client sends the organisation in force, and a request that
               names none is answered the way the bare address would be. A panel asks with its
               flat order named, so this never reorganises one. */
            'defaultsort' => criteria::default_sort($categoryid),
            'datefrom' => (string) ($args['datefrom'] ?? ''),
            'dateto' => (string) ($args['dateto'] ?? ''),
            'cat' => (int) ($args['cat'] ?? 0),
            'withcategories' => (bool) ($args['withcategories'] ?? false),
            'anonymous' => $anonymous,
            /* The reader's own listing on a category they left a state in: every address this
               answer mints (the clear link, the continue link) then names its organisation, as the
               page's own do (criteria::$remembered). Never for a panel, whose addresses name theirs,
               and never for a visitor, who remembers nothing. */
            'remembered' => !$anonymous && empty($args['withcategories']) && remembered::for_category($categoryid) !== null,
        ]);

        $result = self::resolve($criteria);
        return self::answer($category, $criteria, $result) + self::panel($category, $criteria, $result);
    }

    /**
     * The shape of a slice request, refused as malformed before anything is looked up.
     *
     * A slice is the visitor's way of asking for the panel of a merely listed child, which has no
     * page of its own to be asked for by. It is the parent's public listing narrowed to that child
     * by the tree's criterion, in a flat order and at a panel's size, so it cannot be combined with
     * anything that would organise or page the answer differently: a panel request
     * (`withcategories`), a tree criterion of its own (`cat`), a page past the first, the grouped
     * organisation (which drops the tree's criterion in silence, so the answer would be the whole
     * parent page with no error), the root, or a logged-in caller. Every check reads the request
     * and nothing else, so it answers the same for every id and reveals nothing about what exists;
     * a web service calls this before its gates.
     *
     * @param int $categoryid The category the request is made on.
     * @param array $args The request ({@see fetch()}).
     * @return array The request with its sort made flat: '' means the newest first, as in a panel.
     * @throws \invalid_parameter_exception When the request cannot be a slice.
     */
    public static function require_wellformed_slice(int $categoryid, array $args): array {
        $sort = (string) ($args['sort'] ?? '');
        $malformed = (int) $args['slice'] < 0
            || $categoryid === 0
            || empty($args['anonymous'])
            || !empty($args['withcategories'])
            || !empty($args['cat'])
            || (int) ($args['page'] ?? 0) > 0
            || $sort === criteria::SORT_CATEGORY;
        if ($malformed) {
            throw new \invalid_parameter_exception('A slice is an anonymous, flat, first-page request on a category.');
        }
        $args['sort'] = $sort === '' ? criteria::SORT_RECENT : $sort;
        return $args;
    }

    /**
     * Resolve a request, answering a refusal of the population as the guard's own.
     *
     * @param criteria $criteria The request.
     * @return page_result The page.
     * @throws \moodle_exception The category refusal.
     */
    private static function resolve(criteria $criteria): page_result {
        try {
            return population::resolve($criteria);
        } catch (unavailable_exception) {
            /* The guard admitted the category, so this is a race or a disagreement between
               the two: answer with the guard's refusal, never with a second spelling. */
            throw new \moodle_exception(guard::ERROR_CATEGORY, 'theme_dimensions');
        }
    }

    /**
     * The payload of a listing, without the panel's own context.
     *
     * @param \core_course_category $category The category whose page the cards are drawn on.
     * @param criteria $criteria The request that was resolved.
     * @param page_result $result Its answer.
     * @return array The payload of the web service but for `panel`.
     */
    private static function answer(\core_course_category $category, criteria $criteria, page_result $result): array {
        global $PAGE;

        // The renderer needs a context, and the page of a listing is the category's own.
        $PAGE->set_context($category->get_context());
        $listing = new category_listing($category, $criteria, $result, category_toolbar::VIEW_CARDS);
        $context = $listing->export_for_template($PAGE->get_renderer('core'));
        $toolbar = $context->toolbar;

        return [
            'categoryid' => (int) $category->id,
            'page' => $result->page,
            'pagesize' => $result->pagesize,
            'total' => $result->total,
            'hasmore' => $result->hasmore,
            /* The count line in its two halves, the number the design sets in bold and the
               sentence after it, and the flat list a narrowed page offers beside it: the listing's
               own, so the client prints what the page would have printed. */
            'countnumber' => $context->countnumber,
            'countline' => $context->countline,
            'seeallurl' => $context->seeallurl,
            'nexturl' => $context->nexturl,
            'clearurl' => $context->clearurl,
            'hasfilters' => $context->hasfilters,
            /* The badge on the Filter summary, the only number of the toolbar a reader who
               collapsed that panel can still see, so the client repaints it with the rows. Both
               halves are the server's: the client has no idea of what counts as one filter, and
               the label is the localised sentence a screen reader is read. */
            'filtercount' => $toolbar->filtercount,
            'filtercountlabel' => $toolbar->filtercountlabel,
            'searchtooshort' => $toolbar->searchtooshort,
            'cards' => array_map(static fn(\stdClass $card): array => (array) $card, $context->cards),
            // Always a group, even where the toolbar offers none, so the client has one shape to paint.
            'statusgroup' => ['options' => $toolbar->statusgroup['options'] ?? []],
            /* The favourites toggle as the toolbar would draw it for this request, so the client
               repaints its pressed state and the number in its name; never drawn where it is not
               available, and then neither pressed nor counted. */
            'favourites' => self::favourites($toolbar->favourites),
            'facets' => $toolbar->facets,
            /* The accordion's entries, so a client that has just narrowed the page can repaint
               what each poster knows before its panel is opened - its number, its pill, whether
               it opens at all, and the shell a panel draws while its rows are on their way -
               without asking for each panel. Empty on a flat organisation and on a panel's own
               request, which is one poster of somebody else's page and has none. */
            'accordion' => self::accordion($criteria, $result, false),
            /* What the page says about its areas as a whole in result mode - the summary's two
               sentences, the zero panel's "elsewhere", the reveal, "nothing anywhere" and the
               automatic opening - from the builder the page uses, with no panel known. */
            'areas' => $context->results,
        ];
    }

    /**
     * A visitor's panel for a merely listed child: its slice of this category's public listing.
     *
     * The decision is the page's own and is not taken a second time here. The request is resolved
     * as the visitor's grouped page of this category with the child's panel open, which is what an
     * address carrying `open` asks the page for, and the child is admitted exactly when the page
     * would draw its panel as a slice: a poster of this very page (so a direct child, holding a
     * public course, that the tree names), expandable, with no page a visitor may open. A public
     * child is the panel of its own page and is asked for by its own id; a hidden or withheld
     * child, a grandchild and an id nothing holds have no poster here. Those answer null, so the
     * caller drops the slice and none of them can be told from another.
     *
     * The panel is built with the PAGE's request, as {@see category_panel::for_page()} does, so
     * what narrows it, its order and its ways out are the page's; the listing carries the slice's
     * own request, whose address is the "see all as a list" one.
     *
     * @param \core_course_category $category The public category the slice is cut from.
     * @param array $args The request, well formed ({@see require_wellformed_slice()}).
     * @return array|null The payload, or null when the slice names no such child.
     * @throws \moodle_exception The category refusal.
     */
    private static function slice(\core_course_category $category, array $args): ?array {
        $categoryid = (int) $category->id;
        $slice = (int) $args['slice'];
        $page = new criteria($categoryid, [
            'search' => (string) ($args['query'] ?? ''),
            'fields' => (array) ($args['filters'] ?? []),
            'datefrom' => (string) ($args['datefrom'] ?? ''),
            'dateto' => (string) ($args['dateto'] ?? ''),
            // Grouped, because only a grouped page has posters: the child's poster is what admits the slice.
            'sort' => '',
            'open' => $slice,
            'psort' => (string) $args['sort'] === criteria::SORT_NAME ? criteria::SORT_NAME : '',
            'anonymous' => true,
            'withposters' => true,
            // The page's own rows are not asked for here.
            'pagesize' => 1,
        ]);
        $pageresult = self::resolve($page);
        $entry = $pageresult->panelentry;
        if ($pageresult->panel === null || $entry === null || empty($entry['istext'])) {
            return null;
        }

        $criteria = population::panel_criteria($page, $slice, true);
        $panel = new category_panel(
            areaid: $slice,
            name: (string) $entry['name'],
            titleurl: '',
            criteria: $page,
            psort: $page->psort,
            result: $pageresult->panel,
            anonymous: true,
            isd20: true,
            pageid: $categoryid,
        );
        return self::answer($category, $criteria, $pageresult->panel) + ['panel' => $panel->export_for_ws()];
    }

    /**
     * The root's answer to a live search: the posters' numbers and the areas' word, and no listing.
     *
     * The root is a directory, so the answer carries none of a listing's parts - no card, no count
     * line, no facet, no state group, no pager - and they are sent empty, so the client has one
     * shape to read. What it does carry is what the search line changes: each poster's number of
     * matches ({@see population::resolve_root()}), the areas' result mode
     * ({@see category_listing::results()}, which never opens an area by itself at the root) and
     * the too-short flag. The request is the term alone; the web service has refused anything else
     * before it got here.
     *
     * @param \core_course_category $category The root, as the guard answered it (top()).
     * @param string $query The term as typed.
     * @param bool $anonymous Whether the visitor's public root is being answered.
     * @return array The payload of the web service, exactly the keys its returns declare.
     * @throws \moodle_exception The category refusal, if the population refuses the root.
     */
    private static function root(\core_course_category $category, string $query, bool $anonymous): array {
        global $PAGE;

        $criteria = new criteria(0, ['search' => $query, 'anonymous' => $anonymous]);
        $result = self::resolve($criteria);
        $PAGE->set_context($category->get_context());
        return [
            'categoryid' => 0,
            'page' => 0,
            'pagesize' => $result->pagesize,
            'total' => $result->total,
            'hasmore' => false,
            'countnumber' => '',
            'countline' => '',
            'seeallurl' => '',
            'nexturl' => '',
            'clearurl' => links::showcase(0, [], $criteria->anonymous)->out(false),
            'hasfilters' => $criteria->has_filters(),
            'filtercount' => 0,
            'filtercountlabel' => '',
            'searchtooshort' => $criteria->rawsearch !== '' && $criteria->search === '',
            'cards' => [],
            'statusgroup' => ['options' => []],
            'favourites' => self::favourites(null),
            'facets' => [],
            'accordion' => self::accordion($criteria, $result, true),
            'areas' => category_listing::results($result, $criteria, 0),
        ];
    }

    /**
     * The favourites toggle of an answer, in the one shape the client paints.
     *
     * @param array|null $toggle The toolbar's toggle ({@see category_toolbar}), null where none is drawn.
     * @return array ['available' => bool, 'pressed' => bool, 'count' => int].
     */
    private static function favourites(?array $toggle): array {
        if ($toggle === null) {
            return ['available' => false, 'pressed' => false, 'count' => 0];
        }
        return ['available' => true, 'pressed' => (bool) $toggle['pressed'], 'count' => (int) $toggle['count']];
    }

    /**
     * The accordion's entries, each with what its poster shows before its panel is opened.
     *
     * @param criteria $criteria The request.
     * @param page_result $result Its answer.
     * @param bool $isroot Whether the request is the root's, whose every poster opens.
     * @return array One entry per poster: the accordion's keys plus ctaurl, ctalabel, resultlabel,
     *   resultcount and expandable.
     */
    private static function accordion(criteria $criteria, page_result $result, bool $isroot): array {
        return array_map(
            static function (array $entry) use ($criteria, $isroot): array {
                $shell = category_panel::shell($entry, $criteria, $criteria->anonymous, $isroot);
                return $entry + [
                    'ctaurl' => $shell['ctaurl'],
                    'ctalabel' => $shell['ctalabel'],
                    'resultlabel' => $shell['resultlabel'],
                    'resultcount' => $shell['resultcount'],
                    'expandable' => $isroot || population::expandable($criteria->has_filters(), (int) $entry['count']),
                ];
            },
            $result->accordion
        );
    }

    /**
     * The panel's own context, for a panel's request and nothing else.
     *
     * The same builder the page draws its open panel with, without the cards, which the answer
     * carries once at the top level, and without the no-JavaScript addresses, which belong to a
     * page this service does not know. The order is the panel's: a request in A-Z order is a
     * panel in A-Z order, and any other is the newest first.
     *
     * @param \core_course_category $category The panel's area.
     * @param criteria $criteria The panel's request.
     * @param page_result $result Its listing.
     * @return array ['panel' => array] for a panel's request, [] otherwise.
     */
    private static function panel(\core_course_category $category, criteria $criteria, page_result $result): array {
        if (!$criteria->withcategories) {
            return [];
        }
        $categoryid = (int) $category->id;
        $panel = new category_panel(
            areaid: $categoryid,
            name: format_string($category->name, true, ['context' => $category->get_context(), 'escape' => false]),
            titleurl: links::showcase($categoryid, [], $criteria->anonymous)->out(false),
            criteria: $criteria,
            psort: $criteria->sort === criteria::SORT_NAME ? criteria::SORT_NAME : '',
            result: $result,
            anonymous: $criteria->anonymous,
        );
        return ['panel' => $panel->export_for_ws()];
    }
}
