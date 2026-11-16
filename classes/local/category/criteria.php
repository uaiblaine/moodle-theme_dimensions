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
 * Theme Dimensions - What a listing request asks for
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

/**
 * What one request to the category showcase asks for: the category, the page, the filters.
 *
 * Plain data, validated once in the constructor so that every consumer of
 * {@see population::resolve()} - the page, the web services and the tests -
 * hands over the same shape. Nothing here is about the viewer: the population
 * reads who is asking from the session, because its capability checks are the
 * session user's, and resolving for somebody else would answer with the wrong rights.
 *
 * The search term is normalised here and nowhere else: whitespace collapsed,
 * and anything shorter than {@see MIN_SEARCH_LENGTH} characters treated as no
 * search at all, so a one-letter query costs no statement on any surface.
 *
 * The period is the authority for what the start-date filter keeps; the
 * predicate, the web service and the client point here. It compares a course's
 * `startdate`, and each of its two bounds is optional and independent:
 *
 * - `from` alone keeps every course starting on that day or later:
 *   startdate >= midnight at the start of `from`.
 * - `to` alone keeps every course starting on that day or earlier:
 *   startdate < midnight at the start of the day AFTER `to`, so a course
 *   starting at any hour of the last day is kept.
 * - Both keep the courses that satisfy both comparisons, the two days included.
 * - A course with no start date (0) is outside every period with a bound, a
 *   `to` alone included ({@see population::date_sql()}).
 * - A pair given backwards (`from` after `to`) is swapped here, so every address
 *   minted from this request carries them in order. The web service refuses such
 *   a pair instead, because its client orders them before it asks.
 * - A malformed bound is dropped and the other one stands alone.
 *
 * A bound arrives as YYYY-MM-DD (what the date input and the client's calendar
 * use) and is resolved to a timestamp here, in the site timezone
 * ({@see \core_date::get_server_timezone()}), never the viewer's: the logged-in
 * and anonymous shells must agree about what "today" is, and a visitor has no
 * timezone. Both instants come from core's calendar arithmetic, never from adding
 * 86400 to a timestamp, which shifts a boundary by an hour across a daylight
 * saving transition.
 *
 * A malformed sort or date is dropped, never an error: these arrive from a query
 * string, as the state chip does, and the page must still render an address
 * somebody edited.
 *
 * The favourites filter is a criterion of its own beside the state chip, and the two combine (a
 * reader's favourites among the courses they are enrolled in). Only a real account reads it off an
 * address ({@see can_favourite()}), and an older address that spelled it as a value of the state
 * chip is read as the favourites with no state ({@see from_request()}).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class criteria {
    /** @var int The shortest search term that is searched at all, in characters. */
    public const MIN_SEARCH_LENGTH = 2;

    /** @var int The longest search term kept, in characters: what is echoed back and bound to the LIKE. */
    public const MAX_SEARCH_LENGTH = 100;

    /** @var string Query parameter: the zero-based page. */
    public const PARAM_PAGE = 'page';

    /** @var string Query parameter: a page size overriding the setting, clamped to the largest allowed. */
    public const PARAM_PAGESIZE = 'perpage';

    /** @var string Query parameter: the search term. */
    public const PARAM_SEARCH = 'q';

    /** @var string Query parameter: the state chip. */
    public const PARAM_STATE = 'chip';

    /** @var string Query parameter: the favourites toggle, 1 when on and absent otherwise. */
    public const PARAM_FAVOURITES = 'fav';

    /** @var string Query parameter: how the listing is organised. */
    public const PARAM_SORT = 'sort';

    /** @var string Query parameter: the first start date kept, as YYYY-MM-DD. */
    public const PARAM_DATEFROM = 'from';

    /** @var string Query parameter: the last start date kept, as YYYY-MM-DD, inclusive to the reader. */
    public const PARAM_DATETO = 'to';

    /** @var string Query parameter: the category of the navigation tree the listing is narrowed to. */
    public const PARAM_CAT = 'cat';

    /** @var string Query parameter: the child category whose panel of courses is drawn under its poster. */
    public const PARAM_OPEN = 'open';

    /** @var string Query parameter: the order of that panel, '' for the newest first or 'name' for A-Z. */
    public const PARAM_PSORT = 'psort';

    /**
     * @var string[] Every order a panel may be asked for. '' is the default (the newest first) and
     *   names itself with no parameter at all, as the grouped organisation does in {@see SORTS}.
     */
    public const PSORTS = ['', self::SORT_NAME];

    /** @var string The grouped organisation: the posters of this category's children. */
    public const SORT_CATEGORY = 'category';

    /** @var string The flat organisation by course name. */
    public const SORT_NAME = 'name';

    /** @var string The flat organisation by course start date, the newest first. */
    public const SORT_RECENT = 'recent';

    /**
     * @var string[] Every organisation a request may ask for. '' is the same as SORT_CATEGORY -
     *   the default - and is kept in the vocabulary because an address that names no sort at
     *   all has to round-trip through {@see query_params()} as an address that names none.
     */
    public const SORTS = ['', self::SORT_CATEGORY, self::SORT_NAME, self::SORT_RECENT];

    /**
     * @var string[] The state chip's values the filter line draws as its scope, in the line's order; '' is "all".
     *
     * The reader's relationship with the courses: every other value of {@see classifier::filters()} is
     * a "More statuses" option of the filter panel, the same radio group by name, so one choice holds
     * across both. The client splits the web service's status group by the same list, mirrored as
     * SCOPE in js/esm/src/listing/address.ts; category_listing_markup_test pins the two equal.
     */
    public const SCOPE = ['', classifier::STATE_ENROLLED, classifier::STATE_OPEN, classifier::STATE_SCHEDULED];

    /** @var string Prefix of the query parameters carrying one custom field filter each, followed by the field id. */
    public const PARAM_FIELD_PREFIX = 'cf_';

    /** @var int The category whose subtree is listed. */
    public int $categoryid;

    /** @var int The zero-based page. */
    public int $page = 0;

    /** @var int|null The page size, or null for the site setting. */
    public ?int $pagesize = null;

    /** @var string The normalised search term, '' when there is none. */
    public string $search = '';

    /** @var string What the request typed, trimmed but not normalised: the form echoes it, the search never reads it. */
    public string $rawsearch = '';

    /** @var string|null A state chip to keep ({@see classifier::filters()}), null for all. */
    public ?string $state = null;

    /**
     * @var bool Whether only the reader's favourite courses are kept, ANDed with the state chip.
     *
     * One of the filters in every respect - {@see has_filters()}, {@see narrowing_count()},
     * {@see query_params()}, {@see narrowing_params()} and "clear filters" all count it; the Filters
     * badge does not ({@see filter_count()}), because the star on the line shows it. It is
     * honoured where the state chip is, on a logged-in page cut in PHP, and dropped on the request
     * object everywhere else ({@see population::resolve()}); the address only carries it for a real
     * account ({@see from_request()}).
     */
    public bool $favourites = false;

    /** @var array The custom field filters, fieldid => option value (int). */
    public array $fields = [];

    /**
     * @var string How the listing is organised: one of {@see SORTS}, '' for the grouped default.
     *
     * The organisation in force: the one the request named, else {@see $defaultsort}. Everything
     * that orders or shapes the page reads this; only {@see sort_params()} compares it with the
     * default, to leave out of an address what an address naming nothing would get anyway.
     */
    public string $sort = '';

    /**
     * @var string The organisation an address naming none gets on this category: one of {@see SORTS}.
     *
     * '' is the theme's default (the grouped organisation). Given by the page's entries only
     * ({@see from_request()}, the listing's web services through {@see rows::fetch()}), from
     * {@see default_sort()}; every request the code builds for itself - a panel, a slice, the
     * root - names its organisation and leaves this ''.
     */
    public string $defaultsort = '';

    /**
     * @var bool Whether the reader has a remembered state on this category ({@see remembered}).
     *
     * Set by the page's entry ({@see from_request()}) and by the listing's web service for the
     * reader's own requests, never by a request the code builds for itself. While it is on, the
     * bare address of the category means that state to this reader and the category's default to
     * everybody else, so {@see sort_params()} names the organisation in every address minted from
     * this request - the category's default and the grouped organisation included - and an address
     * that names its organisation is read as written ({@see with_remembered()}). It says the reader
     * has a state, not that the state filled anything in.
     */
    public bool $remembered = false;

    /** @var string The first start date kept, as YYYY-MM-DD in the site timezone, '' for no lower bound. */
    public string $datefrom = '';

    /** @var string The last start date kept, as YYYY-MM-DD in the site timezone, '' for no upper bound. */
    public string $dateto = '';

    /**
     * @var int The category of the tree the listing is narrowed to, 0 for the whole subtree.
     *
     * The tree is a filter, not a navigation: pressing a category beside a flat listing narrows
     * that listing to the subtree below it, and the page stays where it is. {@see has_filters()},
     * {@see narrowing_count()}, {@see query_params()} and "clear filters" all count it. Where it
     * does not apply it is dropped, never refused: in the constructor under the grouped
     * organisation, which draws no tree; and in {@see population::resolve()} when
     * {@see population::cat_ids()} finds no admitted category for it below the page's own.
     */
    public int $cat = 0;

    /** @var int|null The first instant kept: midnight of datefrom in the site timezone, null for no bound. */
    public ?int $fromtime = null;

    /** @var int|null The first instant not kept: midnight after dateto in the site timezone, null for no bound. */
    public ?int $totime = null;

    /**
     * @var bool Whether this is a panel's request: the answer carries the strip of subareas.
     *
     * A panel names its area's admitted direct subareas in a strip above its rail, each a link
     * to that subarea's own page, and nothing nests ({@see population::subareas()}). Off by
     * default, because the page's own listing draws no strip.
     */
    public bool $withcategories = false;

    /** @var bool Whether the request comes from the anonymous shell, which lists public courses only. */
    public bool $anonymous = false;

    /**
     * @var int The child category whose panel is drawn between the rows of posters, 0 for none.
     *
     * Not a filter: opening a panel hides no course, so {@see has_filters()} and
     * {@see filter_count()} ignore it and "clear filters" keeps it ({@see keep_params()}). It is
     * dropped at construction under a flat organisation, whose page draws no poster, and it is
     * admitted by the population rather than here: {@see population::resolve()} sets it back to 0
     * unless it names an expandable poster of the very page being drawn, on this object, so that an
     * invalid value - a withheld child, a grandchild, a stale id - answers exactly what the address
     * without it answers and the parameter is never an oracle.
     */
    public int $open = 0;

    /** @var string The panel's order: '' (the newest first) or {@see SORT_NAME}; always '' while no panel is open. */
    public string $psort = '';

    /**
     * @var bool Whether the answer carries the posters of the grouped page.
     *
     * A second switch beside {@see $withcategories}, set by the page's own requests
     * ({@see from_request()}, {@see root_from_request()}) and by the visitor's panel slice, which
     * needs the page's posters to admit the slice ({@see rows::fetch()}). A filter fetch never sets
     * it: it repaints the accordion's numbers and not the posters, so it pays nothing for a visual
     * it would throw away.
     */
    public bool $withposters = false;

    /**
     * Build a request.
     *
     * @param int $categoryid The category to list.
     * @param array $options Any of page (int), pagesize (int|null), search (string), state (string|null),
     *   favourites (bool), fields (array fieldid => value), sort (string), datefrom (string, YYYY-MM-DD), dateto (string,
     *   YYYY-MM-DD), cat (int, the tree's category), withcategories (bool),
     *   anonymous (bool), open (int, the child whose panel is drawn), psort (string, the panel's
     *   order), withposters (bool), defaultsort (string, the organisation in force when sort is ''
     *   or unknown), remembered (bool, see {@see $remembered}). Unknown keys are refused; an
     *   unknown state, an unknown sort or default, an unknown panel order, a field that is not one of
     *   the site's filter fields ({@see facets::fields()}) or a value none of its options has, and a
     *   malformed date are dropped, and a pair of dates given backwards is put in order, because they arrive from a
     *   query string. The booleans arrive from a web service or from the page, never from an
     *   address.
     * @throws \coding_exception On an unknown option key.
     */
    public function __construct(int $categoryid, array $options = []) {
        $known = [
            'page',
            'pagesize',
            'search',
            'state',
            'favourites',
            'fields',
            'sort',
            'datefrom',
            'dateto',
            'cat',
            'withcategories',
            'anonymous',
            'open',
            'psort',
            'withposters',
            'defaultsort',
            'remembered',
        ];
        $unknown = array_diff(array_keys($options), $known);
        if ($unknown) {
            throw new \coding_exception('Unknown listing option(s): ' . implode(', ', $unknown));
        }

        $this->categoryid = $categoryid;
        $this->page = max(0, (int) ($options['page'] ?? 0));
        $pagesize = $options['pagesize'] ?? null;
        $this->pagesize = ($pagesize === null) ? null : max(1, (int) $pagesize);
        $this->rawsearch = trim((string) ($options['search'] ?? ''));
        $this->search = self::normalise_search($this->rawsearch);

        // The chip comes from a query string: an unknown value is no chip, never a developer error.
        $state = $options['state'] ?? null;
        $this->state = ($state !== null && in_array((string) $state, classifier::filters(), true)) ? (string) $state : null;
        $this->favourites = (bool) ($options['favourites'] ?? false);

        /* Only the site's filter fields, each with a value one of its options has, whoever builds the request:
           population::fields_sql() narrows by any field id it is handed, so a caller that forgot the allowlist the
           listing services apply would answer what a custom field the reader is not shown holds. */
        $fields = [];
        $requested = (array) ($options['fields'] ?? []);
        $counts = $requested ? remembered::field_options() : [];
        foreach ($requested as $fieldid => $value) {
            $fieldid = (int) $fieldid;
            $value = (int) $value;
            if ($value > 0 && $value <= ($counts[$fieldid] ?? 0)) {
                $fields[$fieldid] = $value;
            }
        }
        $this->fields = $fields;

        /* The organisation and the two bounds come from a query string: unknown is none. An
           address naming no organisation gets the category's default, resolved here so that every
           reader below - the grouped switch, the order, the tree's criterion, the panel - agrees
           about the organisation in force. */
        $default = (string) ($options['defaultsort'] ?? '');
        $this->defaultsort = in_array($default, self::SORTS, true) ? $default : '';
        $sort = (string) ($options['sort'] ?? '');
        $sort = in_array($sort, self::SORTS, true) ? $sort : '';
        $this->sort = $sort !== '' ? $sort : $this->defaultsort;
        $datefrom = self::parse_date((string) ($options['datefrom'] ?? ''));
        $dateto = self::parse_date((string) ($options['dateto'] ?? ''));
        // A pair given backwards is the same period in order (see the class note); ISO dates sort as strings.
        if ($datefrom !== '' && $dateto !== '' && strcmp($datefrom, $dateto) > 0) {
            [$datefrom, $dateto] = [$dateto, $datefrom];
        }
        $this->datefrom = $datefrom;
        $this->dateto = $dateto;
        $this->fromtime = $this->datefrom === '' ? null : self::day_start($this->datefrom);
        // Midnight after the chosen day, so the predicate keeps every hour of it (see the class note).
        $this->totime = $this->dateto === '' ? null : self::day_start($this->dateto, 1);

        /* The grouped organisation draws no tree, so a cat arriving with it is a filter nothing on
           screen could have set or can clear. Dropped here rather than at the predicate, so the
           summary's count, the clear link, the pager's parameters and the statement all agree. */
        $this->cat = $this->is_grouped() ? 0 : max(0, (int) ($options['cat'] ?? 0));

        /* A panel's switch. It is not read from the query string: the page has no address that
           asks for it, and a reader who typed one would get a listing whose count line, pager and
           hero disagree with every other address of the same category. */
        $this->withcategories = (bool) ($options['withcategories'] ?? false);

        $this->anonymous = (bool) ($options['anonymous'] ?? false);

        /* The panel, dropped exactly as the tree's criterion is dropped above: a flat organisation
           draws no poster, so an open arriving with it names nothing on the page. Its order means
           nothing without it, and an order outside the vocabulary is the default one. */
        $this->open = $this->is_grouped() ? max(0, (int) ($options['open'] ?? 0)) : 0;
        $psort = (string) ($options['psort'] ?? '');
        $this->psort = ($this->open > 0 && in_array($psort, self::PSORTS, true)) ? $psort : '';
        $this->withposters = (bool) ($options['withposters'] ?? false);
        $this->remembered = (bool) ($options['remembered'] ?? false);
    }

    /**
     * The panel's two parameters of the current request, and nothing else.
     *
     * Also half of what the root of the course index reads ({@see root_from_request()} adds its
     * search): its posters open panels like any other page's, so it takes from the address which
     * panel is open and in what order.
     *
     * @return array ['open' => int, 'psort' => string], for {@see __construct()}.
     */
    public static function panel_options_from_request(): array {
        return [
            'open' => optional_param(self::PARAM_OPEN, 0, PARAM_INT),
            'psort' => optional_param(self::PARAM_PSORT, '', PARAM_ALPHA),
        ];
    }

    /**
     * What the root of the course index reads off the request: its search and its panel, and nothing else.
     *
     * The root draws a search box and no other control that narrows, so the term is the one
     * criterion it honours ({@see population::resolve_root()}); a chip, a field filter, a period,
     * a tree category, an organisation or a page size arriving on the address is dropped here,
     * because nothing on the page could show or clear it. The panel's two are
     * {@see panel_options_from_request()}'s.
     *
     * @param bool $anonymous Whether the anonymous shell is asking.
     * @return self
     */
    public static function root_from_request(bool $anonymous = false): self {
        return new self(0, [
            'search' => optional_param(self::PARAM_SEARCH, '', PARAM_RAW_TRIMMED),
            'anonymous' => $anonymous,
            // The root draws its posters, and a panel may be open under one of them.
            'withposters' => true,
        ] + self::panel_options_from_request());
    }

    /**
     * The organisation an address naming none gets on a category: the one seam every page entry reads.
     *
     * The category's own default ({@see branding::sort_choice()}'s vocabulary, read from the cached
     * branding, so it costs no statement on a page that draws the hero), and '' - the theme's
     * default, the grouped organisation - for a category that set none and at the root. It is the
     * same for every reader: what this answers is what {@see sort_params()} leaves out of the
     * addresses the page mints, so a per-person answer would make each such address mean something
     * different to each reader. A reader's remembered organisation is filled in by
     * {@see with_remembered()} instead. The precedence is the address, then the reader's remembered
     * state, then this, then the theme's default.
     *
     * @param int $categoryid The category, 0 for the root.
     * @return string One of {@see SORTS}.
     */
    public static function default_sort(int $categoryid): string {
        if ($categoryid <= 0) {
            return '';
        }
        return (string) branding::for_category($categoryid)['defaultsort'];
    }

    /**
     * Build a request from the query string of the current page.
     *
     * Reads the parameters named by this class's own PARAM_* address constants
     * ({@see self::PARAM_SEARCH} and the rest), which the form and the pager
     * write with too. A field filter is read only for the fields the
     * caller allowlisted: an unknown cf_ parameter is ignored, never an error.
     *
     * @param int $categoryid The category to list.
     * @param int[] $fieldids The allowlisted custom field ids (see facets::fields()).
     * @param bool $anonymous Whether the anonymous shell is asking.
     * @return self
     */
    public static function from_request(int $categoryid, array $fieldids, bool $anonymous = false): self {
        $fields = [];
        // A value no option has is dropped by the constructor, as the remembered path drops it.
        foreach ($fieldids as $fieldid) {
            $value = optional_param(self::PARAM_FIELD_PREFIX . (int) $fieldid, 0, PARAM_INT);
            if ($value > 0) {
                $fields[(int) $fieldid] = $value;
            }
        }
        $pagesize = optional_param(self::PARAM_PAGESIZE, 0, PARAM_INT);
        $state = optional_param(self::PARAM_STATE, '', PARAM_ALPHA);
        $favourites = (bool) optional_param(self::PARAM_FAVOURITES, 0, PARAM_BOOL);
        /* An address written while the favourites were a value of the state chip - a bookmark, a
           shared link - still opens on the reader's favourites, with no state chip. */
        if ($state === classifier::CHIP_FAVOURITES) {
            $state = '';
            $favourites = true;
        }
        $options = [
            'page' => optional_param(self::PARAM_PAGE, 0, PARAM_INT),
            'pagesize' => $pagesize > 0 ? min($pagesize, population::MAX_PAGESIZE) : null,
            'search' => optional_param(self::PARAM_SEARCH, '', PARAM_RAW_TRIMMED),
            'state' => $state === '' ? null : $state,
            // A reader's own rows: never a visitor's nor the guest account's, whatever the address says.
            'favourites' => $favourites && self::can_favourite($anonymous),
            'fields' => $fields,
            'sort' => optional_param(self::PARAM_SORT, '', PARAM_ALPHA),
            'defaultsort' => self::default_sort($categoryid),
            /* PARAM_RAW_TRIMMED and then the strict shape below, rather than a cleaner that would
               turn a typed date into a different, plausible date: what is not YYYY-MM-DD is no
               bound at all. */
            'datefrom' => optional_param(self::PARAM_DATEFROM, '', PARAM_RAW_TRIMMED),
            'dateto' => optional_param(self::PARAM_DATETO, '', PARAM_RAW_TRIMMED),
            'cat' => optional_param(self::PARAM_CAT, 0, PARAM_INT),
            'anonymous' => $anonymous,
            // The page draws its posters, and a panel may be open under one of them.
            'withposters' => true,
        ] + self::panel_options_from_request();
        // A visitor remembers nothing, and neither does the guest account (remembered::can_remember()).
        $entry = $anonymous ? null : remembered::for_category($categoryid);
        return new self($categoryid, $entry === null ? $options : self::with_remembered($options, $entry));
    }

    /**
     * Whether the session's reader has favourites a listing can be narrowed to: a real account, never the guest.
     *
     * Favourites are rows of the reader's own, so a visitor (the anonymous shell) and the guest
     * account - whose favourites core does not keep - have none, and an address asking for them is
     * read as an address that does not. The toolbar draws the toggle on the same answer.
     *
     * @param bool $anonymous Whether the anonymous shell is asking.
     * @return bool
     */
    public static function can_favourite(bool $anonymous = false): bool {
        return !$anonymous && isloggedin() && !isguestuser();
    }

    /**
     * A request's options with the reader's remembered state filled in where the address names none of it.
     *
     * The state is one whole: it fills in a bare address - one that names no organisation, no panel
     * and nothing that narrows (a term, a state, the favourites, a field, a bound of the period, the
     * tree's category) - and nothing else, so an address that names any of these is read as written,
     * with the category's default for whatever it leaves out. That keeps a shared address meaning the
     * same to whoever opens it: every address the page mints for a reader with a remembered state
     * names its organisation ({@see $remembered}, set here whether or not anything was filled in), so
     * it is never bare. "Clear filters" names the organisation and no filter, so it clears. The page,
     * the panel and the page size are never filled in. The view is not a criterion:
     * {@see \theme_dimensions\output\category_listing::view()} reads the remembered one beside the
     * address's own.
     *
     * @param array $options The options the address gave ({@see from_request()}).
     * @param array $entry The reader's entry for the category ({@see remembered::for_category()}).
     * @return array The options to build the request with.
     */
    public static function with_remembered(array $options, array $entry): array {
        $options['remembered'] = true;
        $sort = (string) ($options['sort'] ?? '');
        $named = ($sort !== '' && in_array($sort, self::SORTS, true))
            || (int) ($options['open'] ?? 0) > 0
            || trim((string) ($options['search'] ?? '')) !== ''
            || ($options['state'] ?? null) !== null
            || !empty($options['favourites'])
            || !empty($options['fields'])
            || trim((string) ($options['datefrom'] ?? '')) !== ''
            || trim((string) ($options['dateto'] ?? '')) !== ''
            || (int) ($options['cat'] ?? 0) > 0;
        if ($named) {
            return $options;
        }
        $options['sort'] = (string) ($entry[remembered::KEY_SORT] ?? '');
        $options['state'] = $entry[remembered::KEY_STATE] ?? null;
        $options['favourites'] = !empty($entry[remembered::KEY_FAVOURITES]);
        $options['fields'] = (array) ($entry[remembered::KEY_FIELDS] ?? []);
        return $options;
    }

    /**
     * The query parameters that reproduce this request's filters, for the pager and the form.
     *
     * The page is deliberately absent: a filter change starts over at the
     * first page, and the pager adds its own.
     *
     * @return array name => value.
     */
    public function query_params(): array {
        $params = [];
        if ($this->search !== '') {
            $params[self::PARAM_SEARCH] = $this->search;
        }
        if ($this->state !== null) {
            $params[self::PARAM_STATE] = $this->state;
        }
        if ($this->favourites) {
            $params[self::PARAM_FAVOURITES] = 1;
        }
        foreach ($this->fields as $fieldid => $value) {
            $params[self::PARAM_FIELD_PREFIX . $fieldid] = $value;
        }
        if ($this->datefrom !== '') {
            $params[self::PARAM_DATEFROM] = $this->datefrom;
        }
        if ($this->dateto !== '') {
            $params[self::PARAM_DATETO] = $this->dateto;
        }
        if ($this->cat > 0) {
            $params[self::PARAM_CAT] = $this->cat;
        }
        $params += $this->sort_params();
        if ($this->pagesize !== null) {
            $params[self::PARAM_PAGESIZE] = $this->pagesize;
        }
        /* The open panel travels with every address the page mints from here - the pager, the
           quick dates, the hero's fold form, the forward - so none of them closes it. */
        $params += $this->panel_params();
        return $params;
    }

    /**
     * The query parameters naming the open panel and its order, empty when no panel is open.
     *
     * @return array name => value.
     */
    public function panel_params(): array {
        if ($this->open <= 0) {
            return [];
        }
        $params = [self::PARAM_OPEN => $this->open];
        if ($this->psort === self::SORT_NAME) {
            $params[self::PARAM_PSORT] = $this->psort;
        }
        return $params;
    }

    /**
     * What "clear filters" carries: the organisation and the open panel, and nothing that narrows.
     *
     * Opening a panel hides nothing, so clearing the filters leaves it open, for the reason
     * {@see sort_params()} gives about the organisation.
     *
     * @return array name => value.
     */
    public function keep_params(): array {
        return $this->sort_params() + $this->panel_params();
    }

    /**
     * The query parameters that narrow the listing: the search, the state chip, the favourites, the field filters, the period.
     *
     * What a panel's call to action carries to the area's own page, so that page opens on the
     * same question. Never the tree's category (it names a category of this page's tree, which the
     * area's page drops unless it lies below that area), never the organisation nor the page size
     * (the panel's own order replaces them), never the page and never the panel.
     *
     * @return array name => value.
     */
    public function narrowing_params(): array {
        $params = [];
        if ($this->search !== '') {
            $params[self::PARAM_SEARCH] = $this->search;
        }
        if ($this->state !== null) {
            $params[self::PARAM_STATE] = $this->state;
        }
        if ($this->favourites) {
            $params[self::PARAM_FAVOURITES] = 1;
        }
        foreach ($this->fields as $fieldid => $value) {
            $params[self::PARAM_FIELD_PREFIX . $fieldid] = $value;
        }
        if ($this->datefrom !== '') {
            $params[self::PARAM_DATEFROM] = $this->datefrom;
        }
        if ($this->dateto !== '') {
            $params[self::PARAM_DATETO] = $this->dateto;
        }
        return $params;
    }

    /**
     * The query parameter naming the organisation, for an address that keeps it while dropping the filters.
     *
     * The default organisation names itself with no parameter at all, so "clear filters" over an
     * unsorted listing is the bare address, and over a sorted one that address plus the sort:
     * clearing the filters must not silently reorganise the page.
     *
     * The default is the category's ({@see $defaultsort}), not the theme's: only the category's own
     * default may be left out, because the bare address gets it, and anything else is named - the
     * grouped organisation included, as `sort=category`, on a category whose default is a flat
     * order, or pressing "By area" would lead back to the flat order.
     *
     * For a reader with a remembered state on the category ({@see $remembered}) the organisation is
     * always named, the default included and the theme's default as `sort=category`: the bare
     * address gets that reader's remembered state, so an address that left the organisation out
     * would be read back as that state rather than as what is on screen, and a reader it was shared
     * with would get theirs.
     *
     * @return array name => value, empty for the default.
     */
    public function sort_params(): array {
        if ($this->remembered) {
            return [self::PARAM_SORT => $this->sort === '' ? self::SORT_CATEGORY : $this->sort];
        }
        if ($this->sort === '' || $this->sort === $this->defaultsort) {
            return [];
        }
        return [self::PARAM_SORT => $this->sort];
    }

    /**
     * Whether the listing is the grouped one: the posters of this category's children.
     *
     * '' and 'category' are the same request. The empty value is what an
     * address that names no organisation carries, and the named one is what
     * the chip posts when the reader presses the default chip itself.
     *
     * @return bool
     */
    public function is_grouped(): bool {
        return $this->sort === '' || $this->sort === self::SORT_CATEGORY;
    }

    /**
     * Whether anything narrows the listing: a search, a chip, the favourites, a field filter, a date bound or the tree.
     *
     * The organisation is not one of them: reordering a listing hides nothing,
     * so "clear filters" leaves it alone and the count line it sits beside
     * counts the same courses before and after a chip is pressed.
     *
     * @return bool
     */
    public function has_filters(): bool {
        return $this->search !== ''
            || $this->state !== null
            || $this->favourites
            || !empty($this->fields)
            || $this->datefrom !== ''
            || $this->dateto !== ''
            || $this->cat > 0;
    }

    /**
     * Whether a state chip is one the filter line draws as its scope rather than one the panel holds.
     *
     * @param string|null $state A state chip, null for none (which is the scope's "all").
     * @return bool
     */
    public static function is_scope(?string $state): bool {
        return in_array($state ?? '', self::SCOPE, true);
    }

    /**
     * How many filters the panel holds, for the number the Filters control prints on itself.
     *
     * The badge counts what is behind it and nothing the line already shows: each custom field
     * and a state chosen among "More statuses". The search (in its own field), the scope (its
     * cells), the favourites (the star), the period (the date button's dot, and its chip) and the
     * tree's category (the tree, and its chip) are all visible where they are set, so counting them
     * here would announce twice what the reader can already see. {@see narrowing_count()} counts
     * everything.
     *
     * @return int
     */
    public function filter_count(): int {
        return count($this->fields)
            + ($this->state !== null && !self::is_scope($this->state) ? 1 : 0);
    }

    /**
     * How many criteria narrow the listing, wherever they are drawn: what "Clear all" undoes.
     *
     * A term, a state chip, the favourites, each custom field, the period as one and the tree's
     * category as one: one control and one press to undo each. "Clear all" is offered from two of
     * them up ({@see \theme_dimensions\output\category_toolbar}); a single criterion is undone by
     * its own control.
     *
     * @return int
     */
    public function narrowing_count(): int {
        $count = ($this->search !== '' ? 1 : 0)
            + ($this->state !== null ? 1 : 0)
            + ($this->favourites ? 1 : 0)
            + ($this->cat > 0 ? 1 : 0)
            + count($this->fields);
        if ($this->datefrom !== '' || $this->dateto !== '') {
            $count++;
        }
        return $count;
    }

    /**
     * The strict ISO shape, or '' for anything else.
     *
     * Deliberately not strtotime() and not a cleaner: "31/09" and "next
     * tuesday" are both things a person may type, and both would be resolved
     * into a date the reader never asked for and cannot see on the control. A
     * calendar day that does not exist (31 September, 29 February of a common
     * year) is refused by checkdate() for the same reason.
     *
     * @param string $raw What the request carried.
     * @return string The date as YYYY-MM-DD, or '' when it is not one.
     */
    public static function parse_date(string $raw): string {
        $raw = trim($raw);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts)) {
            return '';
        }
        if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return '';
        }
        return $raw;
    }

    /**
     * The first instant of a calendar day in the site timezone, optionally some days later.
     *
     * Core's own arithmetic ({@see make_timestamp()}, which {@see usergetmidnight()}
     * uses) rather than seconds added to a timestamp: a day is not always 86400
     * seconds long, and the day after a daylight saving transition would otherwise
     * begin an hour early or late. make_timestamp() hands the day offset to
     * DateTime::setDate(), which rolls the month and the year over, so the last day
     * of December plus one is the first of January.
     *
     * @param string $iso A date as YYYY-MM-DD, already through {@see parse_date()}.
     * @param int $offsetdays Whole days to add to that date.
     * @return int|null The timestamp, or null when the date is not a date.
     */
    public static function day_start(string $iso, int $offsetdays = 0): ?int {
        if (self::parse_date($iso) === '') {
            return null;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $iso));
        return (int) make_timestamp($year, $month, $day + $offsetdays, 0, 0, 0, \core_date::get_server_timezone());
    }

    /**
     * The calendar day an instant falls in, in the site timezone, as YYYY-MM-DD.
     *
     * The inverse of {@see day_start()}, and what the quick options of the
     * filter panel are built from: they are ordinary links carrying the same
     * two parameters a reader could have typed.
     *
     * @param int $timestamp The instant.
     * @param int $offsetdays Whole days to add to the day it falls in.
     * @return string The date as YYYY-MM-DD.
     */
    public static function iso(int $timestamp, int $offsetdays = 0): string {
        $timezone = \core_date::get_server_timezone();
        $parts = usergetdate($timestamp, $timezone);
        if ($offsetdays !== 0) {
            /* Noon rather than midnight as the anchor: a timezone whose daylight saving
               transition happens AT midnight has no midnight on that date at all, and the
               hour the arithmetic lands on must not be able to decide the day. */
            $moved = make_timestamp(
                (int) $parts['year'],
                (int) $parts['mon'],
                (int) $parts['mday'] + $offsetdays,
                12,
                0,
                0,
                $timezone
            );
            $parts = usergetdate($moved, $timezone);
        }
        return sprintf('%04d-%02d-%02d', (int) $parts['year'], (int) $parts['mon'], (int) $parts['mday']);
    }

    /**
     * A calendar day some whole days after another one, both as YYYY-MM-DD in the site timezone.
     *
     * Goes through {@see day_start()} rather than through strtotime(), which
     * would read the string in PHP's default timezone: the site timezone decides
     * every boundary of the period.
     *
     * @param string $iso A date as YYYY-MM-DD.
     * @param int $offsetdays Whole days to add.
     * @return string The date as YYYY-MM-DD, '' when the input is not a date.
     */
    public static function shift(string $iso, int $offsetdays): string {
        $start = self::day_start($iso, $offsetdays);
        return $start === null ? '' : self::iso($start);
    }

    /**
     * The date the civil week the given instant falls in begins on, in the site timezone.
     *
     * The first weekday is the site's own ({@see \core_calendar\type_factory}),
     * which is what every calendar on the site already starts its weeks with.
     *
     * @param int $timestamp The instant.
     * @param int $offsetweeks Whole weeks to add.
     * @return string The date as YYYY-MM-DD.
     */
    public static function week_start(int $timestamp, int $offsetweeks = 0): string {
        $timezone = \core_date::get_server_timezone();
        $parts = usergetdate($timestamp, $timezone);
        $startday = self::week_start_day();
        // The wday core hands back is 0 for Sunday; below is the distance back to the site's first weekday.
        $back = ((int) $parts['wday'] - $startday + 7) % 7;
        return self::iso($timestamp, ($offsetweeks * 7) - $back);
    }

    /**
     * The weekday the site starts its weeks on, 0 for Sunday, as core's calendar reports it.
     *
     * Read here rather than in each place that wants it, so the "this week"
     * quick option and the first column of the client's calendar cannot disagree
     * about which day a week begins on: the client is handed this number on the
     * date fieldset (data-firstday) and configures no week start of its own.
     *
     * @return int 0 (Sunday) to 6 (Saturday).
     */
    public static function week_start_day(): int {
        return (int) \core_calendar\type_factory::get_calendar_instance()->get_starting_weekday();
    }

    /**
     * Collapse whitespace and drop a term too short to search.
     *
     * @param string $raw What the request carried.
     * @return string The term to search, or '' when there is nothing to search.
     */
    public static function normalise_search(string $raw): string {
        $term = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        $term = \core_text::substr($term, 0, self::MAX_SEARCH_LENGTH);
        if (\core_text::strlen($term) < self::MIN_SEARCH_LENGTH) {
            return '';
        }
        return $term;
    }
}
