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
 * Theme Dimensions - The category showcase listing
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course_category;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\cards;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\page_result;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\hotsite\links;

/**
 * Builds the context of one page of the showcase: the navigation tree, the toolbar, the grid, the pager.
 *
 * The one listing both shells render. It is handed a resolved
 * {@see page_result} and reads what the cards need in four bulk statements
 * through {@see cards} - the course rows, the hotsite values, the images,
 * the contacts - then builds one {@see category_card} per row without
 * touching the database again. The toolbar comes from {@see category_toolbar}
 * and the pager is core's own paging bar over real links, so every page of
 * the listing has an address and works with JavaScript off.
 *
 * A logged-in reader's organisation, view and filters are remembered PER
 * CATEGORY ({@see remembered}): a filter carried into another category is
 * how a person concludes it is empty, so an entry is only ever read on its
 * own category's page. The view is also remembered once for the whole site,
 * which is what a category with no entry of its own opens in.
 *
 * The ROOT of the course index is the one page this class draws differently:
 * a course can never sit directly in the top-level pseudo-category, so that
 * page is a DIRECTORY - a card per admitted top-level category - and
 * {@see get_template_name()} switches to the directory template while
 * {@see export_for_template()} returns before a single card of a course is
 * built. Every category page lists its whole subtree, recursively. Both draw
 * their categories as AREAS - posters, with the panel of the open one
 * between their rows ({@see areas()}) - and past
 * {@see population::DIRECTORY_SLICE} posters the rest sit inside a closed
 * native disclosure, which is a page cut and not a data cut.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_listing implements \core\output\named_templatable, \core\output\renderable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string The user preference holding the view: cards or list. Declared in lib.php. */
    public const PREFERENCE_VIEW = 'theme_dimensions_categoryview';

    /**
     * @var string The body class every rule of scss/category.scss is scoped under.
     *
     * Declared here rather than on the course renderer alone: that class extends core's
     * course renderer, which lives in course/renderer.php and is loaded by the renderer
     * factory, not the autoloader - so a page that reads the constant before any renderer
     * is built (the anonymous shell's request) would fail to load it.
     */
    public const BODY_CLASS = 'dim-category';

    /** @var int The deepest level the tree's indent is drawn for: the last `data-depth` rule in scss/category.scss. */
    public const TREE_MAX_INDENT_DEPTH = 4;

    /**
     * Constructor.
     *
     * @param core_course_category $category The listed category, already admitted by both gates.
     * @param criteria $criteria The request.
     * @param page_result $result The resolved page.
     * @param string $view The view in force ({@see category_toolbar::VIEW_CARDS} or VIEW_LIST).
     * @param string $manageactions Core's rendered action menu for the category's managers, or ''.
     * @param array|null $pages The pages strip's context ({@see category_pages::export()}), or null for none.
     */
    public function __construct(
        /** @var core_course_category The listed category. */
        private readonly core_course_category $category,
        /** @var criteria The request. */
        private readonly criteria $criteria,
        /** @var page_result The resolved page. */
        private readonly page_result $result,
        /** @var string The view in force. */
        private readonly string $view,
        /** @var string Core's rendered action menu, or ''. */
        private readonly string $manageactions = '',
        /** @var array|null The pages strip, built by the page's entries and never by the web service. */
        private readonly ?array $pages = null,
    ) {
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return $this->is_directory()
            ? 'theme_dimensions/category_directory'
            : 'theme_dimensions/category_listing';
    }

    /**
     * Whether this page is the root's directory of categories rather than a listing of courses.
     *
     * A course can never sit directly in the top-level pseudo-category, so at
     * the root "there is no course here" is always true: the page draws a card
     * per admitted top-level category, a search line and no filter, no grid of
     * courses and no pager.
     *
     * @return bool
     */
    public function is_directory(): bool {
        return (int) $this->category->id === 0;
    }

    /**
     * The view to draw: the request's, else the one left on this category, else the last one chosen anywhere, else cards.
     *
     * A requested view is remembered for a logged-in person who is not the
     * guest account - as this category's ({@see remembered}) and as the one
     * every category without a view of its own opens in - and anybody else
     * gets it for this page only. Anything but the two known values is
     * ignored, because it arrives from a query string. The view is the one
     * part of a remembered state an address that names other things still
     * leaves to the reader: it changes how the courses are drawn, never which.
     *
     * @param string $requested The view parameter of the request, '' for none.
     * @param int $categoryid The category drawn, 0 for the root, which remembers no view of its own.
     * @return string VIEW_CARDS or VIEW_LIST.
     */
    public static function view(string $requested = '', int $categoryid = 0): string {
        $known = [category_toolbar::VIEW_CARDS, category_toolbar::VIEW_LIST];
        if (in_array($requested, $known, true)) {
            if (remembered::can_remember()) {
                set_user_preference(self::PREFERENCE_VIEW, $requested);
                remembered::record($categoryid, [remembered::KEY_VIEW => $requested]);
            }
            return $requested;
        }
        if (remembered::can_remember()) {
            $entry = remembered::for_category($categoryid);
            if ($entry !== null && isset($entry[remembered::KEY_VIEW])) {
                return $entry[remembered::KEY_VIEW];
            }
            $stored = (string) get_user_preferences(self::PREFERENCE_VIEW, category_toolbar::VIEW_CARDS);
            if (in_array($stored, $known, true)) {
                return $stored;
            }
        }
        return category_toolbar::VIEW_CARDS;
    }

    /**
     * Export the listing context.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        $categoryid = (int) $this->category->id;
        if ($this->is_directory()) {
            return $this->export_directory($output);
        }
        $cardcontexts = self::export_cards($this->result, $categoryid, $this->criteria->withcategories, $output);

        $toolbar = new category_toolbar(
            $categoryid,
            $this->criteria,
            $this->result,
            $this->view,
            !$this->result->anonymous && $this->result->statesort,
            $this->manageactions,
            $this->result->anonymous
        );

        $tree = $this->tree();
        $islist = $this->view === category_toolbar::VIEW_LIST;
        $isgrouped = $this->criteria->is_grouped();
        $areas = $isgrouped && $this->result->posters ? $this->areas($this->result->posters, false, $output) : null;
        $count = $this->count_line();

        return (object) [
            'categoryid' => $categoryid,
            'tree' => $tree,
            'hastree' => count($tree) > 1,
            'treelabel' => get_string('category_tree', self::COMPONENT),
            /* The tree is wrapped in a disclosure so that a phone can collapse it: at the
               stylesheet's own breakpoint and up the summary is hidden and the content simply
               shows, and below it the client closes the disclosure at load, because a page that
               opens on forty category names is a page whose courses start below the fold. */
            'treeboxlabel' => get_string('category_treebox', self::COMPONENT),
            'isgrouped' => $isgrouped,
            /* A grouped page is two sections: the AREAS - one poster per child, the accordion's
               set, with the panel slot between the rows - and then the courses sitting DIRECTLY
               here, with the grid, empty state and pager. The second section is hidden when
               nothing sits directly here, under a criterion too (a narrowed page shows only where
               the results are, and "nothing anywhere" is the areas' own box); it stays in the
               document, hidden, because the client repaints its grid. */
            'areas' => $areas,
            'hasareas' => $areas !== null,
            'direct' => [
                'heading' => get_string('category_direct', self::COMPONENT, $this->category_name()),
                'show' => $areas === null || $this->result->total > 0,
            ],
            'isdirectory' => false,
            // The category's local_page pages, under the hero and above the toolbar: see category_pages.
            'pages' => $this->pages,
            'toolbar' => $toolbar->export_for_template($output),
            'islist' => $islist,
            'viewclass' => $islist ? 'dim-vitrine--list' : 'dim-vitrine--cards',
            'cards' => $cardcontexts,
            'hascards' => !empty($cardcontexts),
            'countnumber' => $count['number'],
            'countline' => $count['text'],
            'seeallurl' => $count['seeallurl'],
            /* The areas' result-mode data, for the web service: the page itself reads it through
               areas() with its real open panel, the service with none. */
            'results' => self::results($this->result, $this->criteria, 0),
            'emptylabel' => $this->empty_label(),
            'hasfilters' => $this->criteria->has_filters(),
            /* The empty state's way out is the toolbar's own control by another route, so it
               carries the same parameters, criteria::keep_params() and nothing else: a bare
               address would drop the organisation the reader chose, and the panel they had open,
               while claiming to clear only the filters. */
            'clearurl' => links::showcase($categoryid, $this->criteria->keep_params(), $this->result->anonymous)
                ->out(false),
            'clearlabel' => get_string('category_clearfilters', self::COMPONENT),
            'pager' => $this->pager($output),
            'hasmore' => $this->result->hasmore,
            'nexturl' => $this->nexturl(),
            'loadmorelabel' => get_string('category_loadmore', self::COMPONENT),
            'loadinglabel' => get_string('category_loading', self::COMPONENT),
            'continuelabel' => get_string('category_continuenextpage', self::COMPONENT),
            'vitrine' => json_encode([
                'categoryid' => $categoryid,
                'page' => $this->result->page,
                'pagesize' => $this->result->pagesize,
                'total' => $this->result->total,
                'hasmore' => $this->result->hasmore,
                'chip' => $this->criteria->state,
                'query' => $this->criteria->search,
                'filters' => (object) $this->criteria->fields,
                /* The organisation an address naming none gets here: the client reads the
                   address's own sort against it, as criteria does, and leaves it out of the
                   addresses it writes exactly when the server's sort_params() would. */
                'defaultsort' => $this->criteria->defaultsort,
                /* Whether this reader left a state on this category: the page was then drawn with
                   it wherever the address named none, so the client takes the organisation from
                   here rather than from the address, writes the address out in full, and names the
                   organisation in every address it writes, as criteria::sort_params() does. */
                'remembered' => $this->criteria->remembered,
                'sort' => $this->criteria->sort,
                'view' => $this->view,
                'canpersist' => isloggedin() && !isguestuser(),
            ]),
        ];
    }

    /**
     * The card contexts of a resolved listing, after its bulk reads and nothing per card.
     *
     * Shared by this listing and by a panel ({@see category_panel}), whose rail draws the same card
     * in a smaller presentation. A card names the course's own category as its overline, except
     * on the page of that very category, where the overline would repeat the heading above it -
     * a rule a PANEL lifts ($alloverlines), so that every title of one rail sits at the same
     * height and the courses of the area's own level are told from its subareas'.
     *
     * @param page_result $result The resolved listing.
     * @param int $listedid The category whose page the cards are drawn on.
     * @param bool $alloverlines Whether every card carries its overline, the listed category's included.
     * @param \core\output\renderer_base $output The renderer.
     * @return array The card contexts, in the listing's order.
     */
    public static function export_cards(
        page_result $result,
        int $listedid,
        bool $alloverlines,
        \core\output\renderer_base $output
    ): array {
        $rows = $result->rows;
        $ids = $result->ids();

        // The bulk reads of the page, one statement each (three for a visitor); nothing per card after this.
        $courses = cards::records($ids);
        $values = cards::values($rows);
        /* The public shell's covers go through the theme's own file route: core's route for the
           course image answers a visitor with the login page while the site forces login. */
        $images = cards::images($rows, $result->anonymous);
        /* A visitor's card names nobody ({@see category_card}), so the preload is not paid for them. */
        $contacts = $result->anonymous ? [] : cards::contacts($rows);
        /* The two layers only some cards need, each read for those cards alone: the routes beside a relationship
           with the opening dates, and the prerequisite of a conditional card. A visitor's card carries neither. */
        $nextactions = $result->anonymous ? [] : cards::next_actions($rows);
        $prerequisites = $result->anonymous ? [] : cards::prerequisites($rows);

        $showstar = !$result->anonymous && isloggedin() && !isguestuser();
        $cardcontexts = [];
        foreach ($ids as $courseid) {
            if (!isset($courses[$courseid])) {
                continue;
            }
            $row = $rows[$courseid];
            $overline = (!$alloverlines && (int) $row->category === $listedid)
                ? null
                : ($result->categorynames[(int) $row->category] ?? null);
            $card = new category_card(
                $courses[$courseid],
                $row,
                $values[$courseid],
                $images[$courseid] ?? null,
                $contacts[$courseid] ?? [],
                $overline,
                $showstar,
                $result->anonymous,
                $nextactions[$courseid]['routes'] ?? [],
                $prerequisites[(int) ($row->prerequisiteid ?? 0)] ?? null,
                $nextactions[$courseid]['opens'] ?? null,
                // The dialogue's kicker names the course's own category on every page, the listed one included.
                $result->categorynames[(int) $row->category] ?? null
            );
            $cardcontexts[] = $card->export_for_template($output);
        }
        return $cardcontexts;
    }

    /**
     * What the page says when the grid is empty: nothing matches the filters, or there is no course yet.
     *
     * It is drawn where the grid of courses is the page's whole listing - a flat
     * organisation, or a category with no area. Beside the posters the section of
     * the courses sitting directly here is hidden whenever it would be empty, and
     * a narrowed page that matched nothing anywhere says so in the areas' own box
     * ({@see results()}).
     *
     * @return string The message drawn in place of the grid.
     */
    private function empty_label(): string {
        return get_string(
            $this->criteria->has_filters() ? 'category_empty' : 'category_emptycategory',
            self::COMPONENT
        );
    }

    /**
     * The count line under the toolbar, as its bold number and the rest of the sentence.
     *
     * Two keys, the number first, because the design sets the number in bold. The words after the
     * number are one language string, so the number leads the sentence: the word order of both
     * languages this theme ships, Portuguese and English. A third language putting the number
     * elsewhere would need one string per sentence with the number inside it.
     *
     * - Grouped, at rest: the SUBTREE's number (the hero's, {@see page_result::$counts}) "courses in"
     *   the category. It also counts a course rescued inside a clamped child that no poster shows,
     *   where the sum of what the page draws would not.
     * - Grouped, under a criterion: what the page draws - the direct matches plus every poster's
     *   count, the partition's two halves - with the term or "these filters", and where the page has
     *   areas and anything matched, where the results are ("2 in this category and 14 in 4 areas")
     *   and the flat Recent order carrying the criterion as "See all as a list".
     *   Nothing matched: the head alone and no link. A match rescued inside a clamped child is in
     *   none of those halves, because no poster shows that child, so this number is then smaller
     *   than the list behind "See all as a list", which holds it; {@see results()} likewise opens
     *   the one area with a result by itself although that match exists. Pinned as it is by
     *   category_listing_test::test_under_a_criterion_the_count_line_leaves_out_a_course_no_poster_shows().
     * - Flat: the listing's total "courses in" the tree's chosen category (or the page's), with the
     *   term when there is one. No link: the page already is the list.
     *
     * @return array ['number' => formatted string, 'text' => plain string, 'seeallurl' => string, '' for none].
     */
    private function count_line(): array {
        $criteria = $this->criteria;
        $result = $this->result;
        if (!$criteria->is_grouped()) {
            $number = $result->total;
            $name = $this->scope_name();
            if ($criteria->search !== '') {
                $text = get_string(
                    $number === 1 ? 'category_countline_searchin_one' : 'category_countline_searchin',
                    self::COMPONENT,
                    (object) ['term' => $criteria->search, 'name' => $name]
                );
            } else {
                $text = get_string($number === 1 ? 'category_countline_one' : 'category_countline', self::COMPONENT, $name);
            }
            return ['number' => category_panel::number($number), 'text' => $text, 'seeallurl' => ''];
        }
        if (!$criteria->has_filters()) {
            $number = (int) ($result->counts['total'] ?? $result->total);
            return [
                'number' => category_panel::number($number),
                'text' => get_string(
                    $number === 1 ? 'category_countline_one' : 'category_countline',
                    self::COMPONENT,
                    $this->category_name()
                ),
                'seeallurl' => '',
            ];
        }
        $inareas = 0;
        $areas = 0;
        foreach ($result->accordion as $entry) {
            if ((int) $entry['count'] > 0) {
                $inareas += (int) $entry['count'];
                $areas++;
            }
        }
        $number = $result->total + $inareas;
        if ($criteria->search !== '') {
            $head = get_string(
                $number === 1 ? 'category_countline_search_one' : 'category_countline_search',
                self::COMPONENT,
                $criteria->search
            );
        } else {
            $head = get_string($number === 1 ? 'category_countline_filters_one' : 'category_countline_filters', self::COMPONENT);
        }
        if ($number === 0 || $result->accordion === []) {
            return ['number' => category_panel::number($number), 'text' => $head, 'seeallurl' => ''];
        }
        $parts = [];
        if ($result->total > 0) {
            $parts[] = get_string('category_countline_direct', self::COMPONENT, category_panel::number($result->total));
        }
        if ($areas === 1) {
            $parts[] = get_string('category_countline_areas_one', self::COMPONENT, category_panel::number($inareas));
        } else if ($areas > 1) {
            $parts[] = get_string('category_countline_areas', self::COMPONENT, (object) [
                'count' => category_panel::number($inareas),
                'areas' => category_panel::number($areas),
            ]);
        }
        $joined = count($parts) === 2
            ? get_string('category_countline_and', self::COMPONENT, (object) ['first' => $parts[0], 'second' => $parts[1]])
            : $parts[0];
        $seeall = links::showcase(
            (int) $this->category->id,
            $criteria->narrowing_params() + [criteria::PARAM_SORT => criteria::SORT_RECENT],
            $result->anonymous
        );
        return [
            'number' => category_panel::number($number),
            'text' => get_string('category_countline_detail', self::COMPONENT, (object) ['head' => $head, 'parts' => $joined]),
            'seeallurl' => $seeall->out(false),
        ];
    }

    /**
     * The name a flat listing counts its courses in: the tree's chosen category, else this one.
     *
     * @return string The plain name.
     */
    private function scope_name(): string {
        if ($this->criteria->cat > 0) {
            foreach ($this->result->subcategories as $entry) {
                if ((int) $entry['id'] === $this->criteria->cat) {
                    return (string) $entry['name'];
                }
            }
        }
        return $this->category_name();
    }

    /**
     * What a grouped page under a criterion says about its areas as a whole: one builder for the page and the web service.
     *
     * Result mode is a grouped page with areas and a criterion - at the root, a search, the one
     * criterion it reads - and every number here comes from the accordion already in hand: nothing
     * costs a statement. The root has no course of its own (a course never sits directly in the
     * pseudo-category, and its total is the hero's number rather than a partition), so there
     * "nothing anywhere" means no area holds a match, and no area is opened by itself.
     *
     * - The "nothing in N areas" sentence, in two counts: every zero area (`nomatchlabelall`) and
     *   one fewer (`nomatchlabelless`), the one to print while the open poster is one of them and
     *   stays in the grid.
     * - `elsewhere`, the second half of a zero panel's announcement: how many OTHER areas hold a
     *   result.
     * - The reveal past {@see population::DIRECTORY_SLICE}: how many of its posters hold a result,
     *   and its label - "N more areas with results" when any does, else the label at rest.
     * - `allempty`: nothing matched anywhere, neither here nor in any area. The page adds "and no
     *   panel is open" ({@see areas()}); the client adds it for itself.
     * - The automatic opening: the one area holding every result when nothing sits directly here,
     *   and the sentence the count line then appends. The client decides whether to open it.
     * - "Search all areas": on a category page searched for a term, the root carrying that term -
     *   offered only where this reader can open the root ({@see searchall_url()}).
     *
     * @param page_result $result The resolved page.
     * @param criteria $criteria Its request.
     * @param int $openid The open panel's area, 0 for none or unknown (the web service).
     * @return array ['filtered' => bool, 'nomatchlabelall' => string, 'nomatchlabelless' => string,
     *   'elsewhere' => string, 'moreresults' => int, 'morelabel' => string, 'allempty' => bool,
     *   'autoopen' => int, 'autoopenlabel' => string, 'searchallurl' => string]; the defaults
     *   (false, '', 0) off result mode, but for the reveal's label at rest.
     */
    public static function results(page_result $result, criteria $criteria, int $openid): array {
        $isroot = $criteria->categoryid === 0;
        // The root's total is the hero's number: no course sits directly in the pseudo-category.
        $direct = $isroot ? 0 : $result->total;
        $entries = array_values($result->accordion);
        $filtered = $criteria->has_filters() && $entries !== [];
        $more = array_slice($entries, population::DIRECTORY_SLICE);
        $moreresults = 0;
        $zero = [];
        $hits = [];
        foreach ($entries as $index => $entry) {
            $count = (int) $entry['count'];
            if ($count === 0) {
                $zero[] = $entry;
            } else {
                $hits[] = $entry;
                if ($index >= population::DIRECTORY_SLICE) {
                    $moreresults++;
                }
            }
        }
        if (!$filtered) {
            $moreresults = 0;
        }
        if ($filtered && $moreresults > 0) {
            $morelabel = $moreresults === 1
                ? get_string('category_directory_moreresults_one', self::COMPONENT)
                : get_string('category_directory_moreresults', self::COMPONENT, $moreresults);
        } else if ($more !== []) {
            $morelabel = count($more) === 1
                ? get_string('category_directory_more_one', self::COMPONENT)
                : get_string('category_directory_more', self::COMPONENT, count($more));
        } else {
            $morelabel = '';
        }
        $results = [
            'filtered' => $filtered,
            'nomatchlabelall' => '',
            'nomatchlabelless' => '',
            'elsewhere' => '',
            'moreresults' => $moreresults,
            'morelabel' => $morelabel,
            'allempty' => false,
            'autoopen' => 0,
            'autoopenlabel' => '',
            'searchallurl' => '',
        ];
        if (!$filtered) {
            return $results;
        }
        $results['searchallurl'] = self::searchall_url($criteria, $result->anonymous);
        $results['nomatchlabelall'] = self::nomatch_label(count($zero), $criteria->search);
        $results['nomatchlabelless'] = self::nomatch_label(count($zero) - 1, $criteria->search);
        $others = count(array_filter($hits, static fn(array $entry): bool => (int) $entry['id'] !== $openid));
        if ($others === 0) {
            $results['elsewhere'] = get_string('category_panel_zeronowhere', self::COMPONENT);
        } else if ($others === 1) {
            $results['elsewhere'] = get_string('category_panel_zeroelsewhere_one', self::COMPONENT);
        } else {
            $results['elsewhere'] = get_string('category_panel_zeroelsewhere', self::COMPONENT, category_panel::number($others));
        }
        $results['allempty'] = $direct === 0 && $hits === [];
        if (!$isroot && $direct === 0 && count($hits) === 1) {
            $results['autoopen'] = (int) $hits[0]['id'];
            $results['autoopenlabel'] = get_string('category_countline_autoopened', self::COMPONENT, (string) $hits[0]['name']);
        }
        return $results;
    }

    /**
     * Where "Search all areas" goes: the root, carrying the term alone, for a reader who may open it.
     *
     * Offered on a CATEGORY page searched for a term: the root reads the search and nothing else,
     * so a page narrowed by filters alone has nothing to carry there. And only where the root is a
     * page this reader may open - a real account's, or for a visitor the site's public root when
     * the site opened it ({@see publicaccess::is_public()}) - because a way out that lands on the
     * login form, or on core's course index that ignores the term (the guest's), is not one.
     *
     * @param criteria $criteria The page's request.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @return string The address, '' where there is none to offer.
     */
    private static function searchall_url(criteria $criteria, bool $anonymous): string {
        if ($criteria->categoryid === 0 || $criteria->search === '') {
            return '';
        }
        $reachable = $anonymous ? publicaccess::is_public(0) : isloggedin() && !isguestuser();
        if (!$reachable) {
            return '';
        }
        return links::showcase(0, [criteria::PARAM_SEARCH => $criteria->search], $anonymous)->out(false);
    }

    /**
     * The summary's sentence for a number of areas with no result: "Nothing for "x" in 3 other areas".
     *
     * @param int $count How many areas it names.
     * @param string $term The search term, '' when only filters narrow the page.
     * @return string The sentence, '' for no area.
     */
    private static function nomatch_label(int $count, string $term): string {
        if ($count <= 0) {
            return '';
        }
        if ($term !== '') {
            return $count === 1
                ? get_string('category_nomatch_one', self::COMPONENT, $term)
                : get_string('category_nomatch', self::COMPONENT, (object) [
                    'term' => $term,
                    'count' => category_panel::number($count),
                ]);
        }
        return $count === 1
            ? get_string('category_nomatchfilters_one', self::COMPONENT)
            : get_string('category_nomatchfilters', self::COMPONENT, category_panel::number($count));
    }

    /**
     * The real address of the page after this one, for the moment the client hands paging back.
     *
     * The client appends only so many pages into one document - the window is
     * its own constant, because it bounds a DOM this class never sees; past
     * that it stops appending and offers this link, which starts a fresh
     * document with its own page-1 baseline. It is the pager's
     * own address, so a reader who follows it lands where the numbered bar
     * would have taken them.
     *
     * @return string The address, or '' when this is the last page.
     */
    private function nexturl(): string {
        if (!$this->result->hasmore) {
            return '';
        }
        $params = $this->criteria->query_params();
        $params[criteria::PARAM_PAGE] = $this->result->page + 1;
        return links::showcase((int) $this->category->id, $params, $this->result->anonymous)->out(false);
    }

    /**
     * The tree beside the listing: this category, then every admitted one below it, as FILTERS.
     *
     * The entries are ordinary links, one of them marked as the chosen one, and
     * depth travels as a number the stylesheet indents by rather than as
     * characters inside a label. Each entry is an address of THIS page carrying
     * the tree's own parameter ({@see criteria::PARAM_CAT}) and every other
     * filter the request already holds, so following one narrows the listing to
     * that category's subtree and leaves the reader where they were; the first
     * entry is this category's own address without that parameter, which is
     * what clears it. They stay real links, so the whole control works with no
     * JavaScript at all; the client intercepts them and fetches instead.
     *
     * It is a control of the flat organisations alone. Under "by area" the
     * posters already name every child and open its courses, so a tree beside
     * them would be a second control for the same question, and a `cat` never
     * reaches that page at all ({@see criteria::$cat} drops it with the grouped
     * sort). Nothing is exported, so the shell has no column to lay out either.
     *
     * The walk is bounded by {@see population::tree_depth()}, which bounds the
     * NAVIGATION and never the listing: the page under this tree lists the
     * whole subtree whatever the tree offers.
     *
     * A non-linkable entry (a merely listed child on the anonymous shell, which
     * has no page a visitor may open) is present as TEXT: a visitor reads the
     * name and cannot choose it.
     *
     * @return array Entries of ['id' => int, 'name' => string, 'depth' => int, 'url' => string,
     *   'linkable' => bool, 'current' => bool], this category first at depth 0; empty when the
     *   organisation is the grouped one. A descendant's depth is capped at
     *   {@see self::TREE_MAX_INDENT_DEPTH}, so the setting's "every level" keeps the last inset.
     */
    private function tree(): array {
        if ($this->criteria->is_grouped()) {
            return [];
        }
        $categoryid = (int) $this->category->id;
        $chosen = $this->criteria->cat;
        /* Every other filter travels with the entry, so choosing a category keeps the search term
           and the period the reader had already set; the tree's own parameter is dropped first,
           because each entry writes its own and the first entry writes none. */
        $params = $this->criteria->query_params();
        unset($params[criteria::PARAM_CAT]);
        $entries = [[
            'id' => $categoryid,
            'name' => format_string($this->category->name, true, [
                'context' => $this->category->get_context(),
                'escape' => false,
            ]),
            'depth' => 0,
            'url' => links::showcase($categoryid, $params, $this->result->anonymous)->out(false),
            'linkable' => true,
            'current' => $chosen === 0,
        ]];
        foreach ($this->result->subcategories as $entry) {
            $linkable = !empty($entry['linkable']);
            $id = (int) $entry['id'];
            $entries[] = [
                'id' => $id,
                'name' => $entry['name'],
                'depth' => min(self::TREE_MAX_INDENT_DEPTH, max(1, (int) $entry['depth'])),
                'url' => $linkable
                    ? links::showcase(
                        $categoryid,
                        $params + [criteria::PARAM_CAT => $id],
                        $this->result->anonymous
                    )->out(false)
                    : '',
                'linkable' => $linkable,
                'current' => $chosen === $id,
            ];
        }
        return $entries;
    }

    /**
     * The areas section: the posters, cut at {@see population::DIRECTORY_SLICE}, and the slot of the one panel.
     *
     * One context for the root's directory and a category page's grouped listing, rendered by one
     * partial. A poster is a LINK without JavaScript, whose address opens its panel
     * (open, and the page of the direct courses the reader is on) or, on the open poster, closes it;
     * the client turns the expandable ones into buttons. The panel is drawn by the server when the
     * request's open survived admission ({@see population::resolve()}), right after its poster in
     * the grid that holds it - the reveal's, past the slice, which is then drawn open - and its row
     * is placed by five integers written on the panel's own element, which the stylesheet reads
     * per breakpoint (rows at three, two and one column, and the column the arrow points at).
     *
     * Under a criterion ({@see results()}) every poster wears its number as a pill, a poster with
     * no result leaves its grid - `hidden` on its item, never removed, and never the open one,
     * which stays with "0 results" - and the areas with no result are named in a closed "nothing
     * in N areas" summary after the grids. The panel's row is counted among the posters still
     * VISIBLE in its grid. The reveal opens by itself when it holds a result, and is hidden when it
     * holds none and not the open poster (it stays in the document for the client). When nothing
     * matched anywhere and no panel is open, the areas draw the "nothing matches" box with its way
     * out, since the direct courses' section is hidden then. The ROOT under a search is in result
     * mode too: its posters carry their numbers of matches ({@see population::resolve_root()}),
     * and its box speaks of the search alone.
     *
     * @param array $posters The page's posters ({@see page_result::$posters}), or the root's directory.
     * @param bool $isroot Whether the page is the root.
     * @param \core\output\renderer_base $output The renderer.
     * @return array The context of category_areas.mustache.
     */
    private function areas(array $posters, bool $isroot, \core\output\renderer_base $output): array {
        $categoryid = (int) $this->category->id;
        $pageparams = category_panel::page_params($this->criteria, $isroot ? null : $this->result->page);
        $openid = $this->result->panelentry !== null ? (int) $this->result->panelentry['id'] : 0;
        $results = self::results($this->result, $this->criteria, $openid);
        $filtered = $results['filtered'];
        $entries = [];
        $openindex = -1;
        $nomatch = [];
        $openzero = false;
        foreach (array_values($posters) as $index => $poster) {
            $isopen = $openid > 0 && (int) $poster['id'] === $openid;
            if ($isopen) {
                $openindex = $index;
            }
            $entry = $this->poster($poster, $isroot, $isopen, $pageparams, $filtered);
            $entries[] = $entry;
            if ($entry['results']['iszero']) {
                $nomatch[] = [
                    'id' => (int) $entry['id'],
                    'name' => (string) $entry['name'],
                    'url' => (string) $entry['url'],
                    'haslink' => (string) $entry['url'] !== '',
                    'isopen' => $isopen,
                ];
                $openzero = $openzero || $isopen;
            }
        }
        $slice = population::directory_slice($entries);
        $isopen = $openindex >= 0;
        $inmore = $isopen && $openindex >= population::DIRECTORY_SLICE;
        // The open poster's place among the VISIBLE posters of its grid: the row integers come from it.
        $grid = $inmore ? $slice['directorymore'] : $slice['directory'];
        $before = array_slice($grid, 0, max(0, $inmore ? $openindex - population::DIRECTORY_SLICE : $openindex));
        $position = count(array_filter($before, static fn(array $entry): bool => !$entry['hidden']));
        $panel = null;
        if ($isopen && $this->result->panel !== null) {
            $panel = category_panel::for_page(
                $this->result->panelentry,
                $this->criteria,
                $this->result->panel,
                $categoryid,
                $isroot,
                $pageparams,
                $posters
            )->export_for_template($output);
        }
        $nomatchlabel = $openzero ? $results['nomatchlabelless'] : $results['nomatchlabelall'];
        if ($results['morelabel'] !== '') {
            $morelabel = $results['morelabel'];
        } else {
            // The root's directory, which has no accordion: its reveal's label at rest.
            $morelabel = $slice['morecount'] === 1
                ? get_string('category_directory_more_one', self::COMPONENT)
                : get_string('category_directory_more', self::COMPONENT, $slice['morecount']);
        }
        /* The "nothing matches" box's way out is the toolbar's own, by another route (see
           export_for_template()); at the root, whose one criterion is the search, it clears that. */
        $clearurl = links::showcase(
            $categoryid,
            $this->criteria->keep_params(),
            $this->result->anonymous
        )->out(false);
        return [
            'categoryid' => $categoryid,
            'isroot' => $isroot,
            'heading' => $isroot
                ? get_string('category_areas_root', self::COMPONENT)
                : get_string('category_areas', self::COMPONENT, $this->category_name()),
            /* The way past the posters to the courses sitting directly here, written on every
               category page and hidden when that section is: the client shows it again. */
            'hasdirectlink' => !$isroot && $this->result->total > 0,
            'directlinklabel' => get_string('category_skiptocourses', self::COMPONENT),
            'isopen' => $isopen && $panel !== null,
            'filtered' => $filtered,
            'elsewhere' => $results['elsewhere'],
            'hasposters' => $entries !== [],
            'posters' => $slice['directory'],
            /* Past the slice, the rest of the posters go behind a native disclosure, drawn OPEN
               when the open poster is among them - or the panel would be drawn where nobody can
               see it - and, under a criterion, when a poster behind it holds a result. */
            'hasmore' => $slice['hasmore'],
            'morehidden' => $filtered && $results['moreresults'] === 0 && !$inmore,
            'morelabel' => $morelabel,
            'moreopen' => $inmore || ($filtered && $results['moreresults'] > 0),
            'postersmore' => $slice['directorymore'],
            'panelinmain' => $isopen && !$inmore,
            'panelinmore' => $inmore,
            'panelstyle' => $isopen ? self::panel_style($position, $openid) : '',
            'panel' => $panel,
            // The hint goes once a panel is open: the reader has found what it says.
            'hashint' => !$isopen && $entries !== [],
            'nomatch' => [
                'show' => $filtered && $nomatchlabel !== '',
                'label' => $nomatchlabel,
                'labelall' => $results['nomatchlabelall'],
                'labelless' => $results['nomatchlabelless'],
                'areas' => $nomatch,
            ],
            'allempty' => $filtered && $results['allempty'] && !$isopen,
            /* The root's box speaks of the search alone, since the root reads no filter; a category
               page's offers the same term in every area as well. */
            'allemptylabel' => get_string($isroot ? 'category_rootallempty' : 'category_allempty', self::COMPONENT),
            'allemptyhint' => get_string($isroot ? 'category_rootallemptyhint' : 'category_allemptyhint', self::COMPONENT),
            'clearlabel' => get_string($isroot ? 'category_clearsearch' : 'category_clearfilters', self::COMPONENT),
            'searchallurl' => $results['searchallurl'],
            'clearurl' => $clearurl,
            // A directory lists CATEGORIES, so its empty state cannot be the listing's "no course".
            'emptylabel' => get_string('category_directory_empty', self::COMPONENT),
        ];
    }

    /**
     * The panel's row, its arrow and the open area's band, as the one inline style they travel in.
     *
     * A CSS-context sink whose vocabulary is five integers and one colour: every integer is (int)
     * arithmetic printed through %d, and the colour is the open area's band - the resolved pair of
     * its branding, the one the poster itself is painted with - printed back by
     * {@see branding::hex()}, the one sanitiser, or the theme's default band when that refuses it.
     * So the attribute can only ever match ^(--dim-(row|col)-[123]: \d+; )+--dim-area-bg: #[0-9a-f]{6};$.
     * The rows are the grid's explicit lines at three, two and one column (the poster's row, plus
     * one to sit under it, plus one because grid lines count from 1); the columns are where the
     * arrow points; the band paints the panel's rail, head and glow (round I), and the panel module
     * copies it from the poster it opens under (see templates/category_areas.mustache).
     *
     * @param int $index The open poster's place among the visible posters of its grid, from 0.
     * @param int $openid The open area, whose branding is already cached by the posters' own read.
     * @return string
     */
    private static function panel_style(int $index, int $openid): string {
        $band = branding::hex((string) branding::for_category($openid)['bgcolour']) ?? branding::DEFAULT_BG;
        return sprintf(
            '--dim-row-3: %d; --dim-row-2: %d; --dim-row-1: %d; --dim-col-3: %d; --dim-col-2: %d; --dim-area-bg: %s;',
            intdiv($index, 3) + 2,
            intdiv($index, 2) + 2,
            $index + 2,
            $index % 3,
            $index % 2,
            $band
        );
    }

    /**
     * One poster's context: its visual, what its control does without JavaScript, and its shell.
     *
     * @param array $poster The poster ({@see population::posters()}) or root directory entry.
     * @param bool $isroot Whether the page is the root, whose every poster opens.
     * @param bool $isopen Whether its panel is the open one.
     * @param array $pageparams The page's own address parameters ({@see category_panel::page_params()}).
     * @param bool $filtered Whether the page is in result mode ({@see results()}).
     * @return array The context of category_directory_card.mustache, plus `hidden` (the item leaves
     *   the grid) and `results` (the pill: has, label, count, iszero).
     */
    private function poster(array $poster, bool $isroot, bool $isopen, array $pageparams, bool $filtered): array {
        $categoryid = (int) $this->category->id;
        $anonymous = $this->result->anonymous;
        $id = (int) $poster['id'];
        $count = (int) ($poster['count'] ?? 0);
        $istext = !$isroot && !empty($poster['istext']);
        $expandable = $isroot || !empty($poster['expandable']);
        $shell = category_panel::shell($poster, $this->criteria, $anonymous, $isroot);
        if (!$expandable) {
            // Nothing to open at rest: the poster is the area's own page, when it has one.
            $controlurl = (string) $poster['url'];
        } else if ($isopen) {
            // A zeroed area leaves the grid once it closes: it lands on the summary it went into.
            $controlurl = category_panel::page_address(
                $categoryid,
                $pageparams,
                $anonymous,
                $filtered && $count === 0 ? category_panel::ANCHOR_NOMATCH : category_panel::ANCHOR_AREA . $id
            );
        } else {
            $controlurl = category_panel::page_address(
                $categoryid,
                $pageparams + [criteria::PARAM_OPEN => $id],
                $anonymous,
                category_panel::ANCHOR_PANEL
            );
        }
        $variant = $istext ? 'text' : (!empty($poster['hasimage']) ? 'photo' : 'band');
        return [
            'istext' => $istext,
            'variant' => $variant,
            'expandable' => $expandable,
            'isopen' => $isopen,
            'controlurl' => $controlurl,
            'hascontrolurl' => $controlurl !== '',
            'shell' => $shell,
            'count' => $count,
            // A poster with no result leaves the grid under a criterion, except the open one.
            'hidden' => $filtered && $count === 0 && !$isopen,
            'results' => [
                'has' => $filtered,
                'label' => $shell['resultlabel'],
                'count' => $shell['resultcount'],
                'iszero' => $filtered && $count === 0,
            ],
        ] + $poster;
    }

    /**
     * The listed category's name, plain: every sink it reaches escapes it.
     *
     * @return string
     */
    private function category_name(): string {
        return format_string($this->category->name, true, [
            'context' => $this->category->get_context(),
            'escape' => false,
        ]);
    }

    /**
     * The root's directory context: the search line and the areas of the top-level categories, and nothing of a listing.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    private function export_directory(\core\output\renderer_base $output): \stdClass {
        return (object) [
            'categoryid' => 0,
            'isdirectory' => true,
            /* The search line: the one control of the root, since the search is the one criterion
               it reads. Drawn whenever there is a poster to search across. */
            'search' => $this->result->directory
                ? category_toolbar::search_line($this->criteria, $this->result->anonymous)
                : null,
            'areas' => $this->areas($this->result->directory, true, $output),
            'hasdirectory' => !empty($this->result->directory),
            /* What the listing's module reads at the root, where its search line is live: the
               term, and nothing a listing has - no page, no chip, no filter. */
            'vitrine' => json_encode([
                'categoryid' => 0,
                'page' => 0,
                'pagesize' => $this->result->pagesize,
                'total' => $this->result->total,
                'hasmore' => false,
                'chip' => null,
                'query' => $this->criteria->search,
                'filters' => (object) [],
                'view' => category_toolbar::VIEW_CARDS,
                'canpersist' => false,
            ]),
        ];
    }

    /**
     * Core's paging bar over the request's own parameters: real links, one per page.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return string The rendered bar, '' when the whole listing fits one page.
     */
    private function pager(\core\output\renderer_base $output): string {
        if ($this->result->total <= $this->result->pagesize) {
            return '';
        }
        $baseurl = links::showcase(
            (int) $this->category->id,
            $this->criteria->query_params(),
            $this->result->anonymous
        );
        $bar = new \core\output\paging_bar(
            $this->result->total,
            $this->result->page,
            $this->result->pagesize,
            $baseurl,
            criteria::PARAM_PAGE
        );
        return $output->render($bar);
    }
}
