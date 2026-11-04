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
 * Theme Dimensions - Which courses a category listing may show, and in what order
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core\context_helper;
use core_course_category;
use local_unlistedcourses\access;
use local_unlistedcourses\category_access;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\styleurl;

/**
 * The population of a category showcase: the subtree, the courses the viewer may see, the page.
 *
 * One listing, two shells, one decision. The logged-in page and the anonymous
 * page both come through {@see resolve()}, differ only in the viewer class the
 * criteria name, and get the same {@see page_result} back. Nothing renders
 * here.
 *
 * Two gates, in this order, on every entry. Core's visibility question first
 * ({@see core_course_category::can_view_category()} for the root and every
 * subtree category, {@see core_course_category::can_view_course_info()}'s two
 * halves for every course), then local_unlistedcourses' ({@see category_access}
 * for the categories, {@see access::filter_courses()} for the courses). The
 * plugin is a layer on top of core visibility and never a replacement for it:
 * its predicates answer true wholesale when nothing is unlisted and for a site
 * admin, and read no visible column and no capability, so a course hidden by
 * core passes them.
 *
 * The refusal for the root is one exception ({@see unavailable_exception})
 * whichever gate refused and whether the id exists at all, and for the
 * anonymous shell the public predicate runs before any lookup, so a guessed id
 * learns nothing.
 *
 * Statement-flat, rows O(N). Every statement is driven by the admitted
 * category ids, the viewer's id, or the page slice; none by the population's
 * course ids, because get_in_or_equal() emits one placeholder per item and
 * never chunks. The ordered id list of the whole subtree is fetched in one
 * statement carrying core's context preload columns, gated in PHP, sorted in
 * PHP and only then sliced - which is why paging is by offset: the state group
 * a course sorts into is computed, not stored. That costs rows, memory and CPU
 * proportional to the subtree on every request, affordable at five thousand
 * courses. The escape hatch for a larger subtree is the categorystatesort
 * setting: off, {@see resolve_sql()} cuts the page in SQL, orders by core's
 * sort alone, and pays O(P) rows for the loss of the state grouping, the state
 * chips and the hero's state counts.
 *
 * Three context-cache rules: category contexts arrive in the subtree
 * statement's own preload columns (never through preload_contexts_by_id(),
 * which wants context ids); a course context is preloaded only when a
 * capability is actually asked of it - a hidden course, a course under a
 * clamped category - because the cache holds
 * CONTEXT_CACHE_MAX_SIZE entries and preloading a whole subtree evicts the
 * page's own; and the page's contexts are rebuilt from the preload columns
 * the rows still carry ({@see page_contexts()}), so a per-page reader pays no
 * statement for them. Rows are preloaded from a clone, because
 * preload_from_record() strips the columns it reads.
 *
 * The one thing not flat, and its cap. An unlisted course is withheld unless
 * the viewer is enrolled, has applied, is staff, or could enrol right now, and
 * the last of those is one enrol-plugin question per course, with queries
 * inside. The enrolments the viewer holds are handed to the plugin first
 * ({@see access::prime_relationships()}), so a related unlisted course is
 * answered from memory and never counts; the unrelated unlisted courses of the
 * subtree are probed in core order up to {@see PROBE_CAP} and the remainder
 * withheld without asking. Fail-closed: a course that was not probed is not
 * shown.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class population {
    /** @var int The most unrelated unlisted courses handed to the plugin's per-course probe in one request. */
    public const PROBE_CAP = 50;

    /** @var int The most courses either exception list of the SQL-cut page may carry. */
    public const RESCUE_CAP = 50;

    /** @var int The page size when the setting is unset. */
    public const DEFAULT_PAGESIZE = 48;

    /** @var int[] The page sizes the setting offers. */
    public const PAGESIZES = [24, 48, 96];

    /** @var int The largest page a request may ask for outright. */
    public const MAX_PAGESIZE = 96;

    /** @var int How many characters of a category description a directory card prints. */
    public const EXCERPT_LENGTH = 160;

    /**
     * @var int How many posters are open before the rest are put behind the reveal.
     *
     * 24 fills whole rows at three, two and one column, and applies to the root's directory and
     * to a category's posters alike. Every admitted poster is still built in the same
     * statement-flat pass - there is no second request behind the reveal - and the slice decides
     * only which of them the browser is asked to paint at once. The rest sit inside a closed
     * <details>, and since every image and crest carries loading="lazy" a browser fetches nothing
     * for them until they are revealed.
     */
    public const DIRECTORY_SLICE = 24;

    /**
     * @var int How many courses a panel lists before its call to action takes over.
     *
     * A rail of eight, and no pagination inside a panel: the ninth course is where the reader
     * expects it on the area's own page, which the call to action opens in the panel's order.
     */
    public const PANEL_SIZE = 8;

    /** @var int The watermark's opacity before any step down, in hundredths (the design's .20). */
    public const MARK_ALPHA = 20;

    /** @var float The opacity of the ink disc behind the poster's chevron, which the watermark sits under. */
    public const CHEVRON_ALPHA = 0.18;

    /** @var float The contrast the chevron must keep over the watermark and its disc: WCAG's non-text 3:1. */
    public const CHEVRON_FLOOR = 3.0;

    /** @var string The setting holding the page size. */
    public const SETTING_PAGESIZE = 'categorypagesize';

    /** @var string The setting that switches the state grouping (and the PHP-cut page) off. */
    public const SETTING_STATESORT = 'categorystatesort';

    /** @var string The setting switching the whole showcase off: both shells revert to what core renders. */
    public const SETTING_ENABLED = 'categorypageenabled';

    /** @var string The setting bounding how deep the navigation tree goes; the accordion is always one level. */
    public const SETTING_TREEDEPTH = 'categorytreedepth';

    /** @var int How many levels below the listed category the tree offers when the setting is unset. */
    public const DEFAULT_TREEDEPTH = 2;

    /**
     * The page size to cut with.
     *
     * @param int|null $requested What the request asked for, if anything.
     * @return int
     */
    public static function page_size(?int $requested = null): int {
        if ($requested !== null) {
            return max(1, min(self::MAX_PAGESIZE, $requested));
        }
        $setting = (int) get_config('theme_dimensions', self::SETTING_PAGESIZE);
        return in_array($setting, self::PAGESIZES, true) ? $setting : self::DEFAULT_PAGESIZE;
    }

    /**
     * Whether the showcase is switched on at all: the feature's kill switch.
     *
     * Default-on, like {@see state_sort()}: only an explicitly stored '0' means
     * off, so a site that never visited the settings tab gets the page.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $stored = get_config('theme_dimensions', self::SETTING_ENABLED);
        return !($stored !== false && (string) $stored === '0');
    }

    /**
     * Whether the listing groups by state first and cuts the page in PHP (the default).
     *
     * Default-on: only an explicitly stored '0' switches it off, so an unset
     * setting never reads as off.
     *
     * @return bool
     */
    public static function state_sort(): bool {
        $stored = get_config('theme_dimensions', self::SETTING_STATESORT);
        return !($stored !== false && (string) $stored === '0');
    }

    /**
     * How many levels below the listed category the navigation offers.
     *
     * Navigation only. Truncating the tree changes which links the left-hand
     * tree offers and never which courses are listed: the listing is recursive
     * over the whole admitted subtree whatever this says, so a course four
     * levels down is on the page of a category whose tree stops at two. Which
     * courses are listed is a discoverability rule; this is only how long a
     * list of links may get. population_test pins the difference.
     *
     * @return int The number of levels, 0 for the whole subtree.
     */
    public static function tree_depth(): int {
        $stored = get_config('theme_dimensions', self::SETTING_TREEDEPTH);
        if ($stored === false || (string) $stored === '') {
            return self::DEFAULT_TREEDEPTH;
        }
        return max(0, (int) $stored);
    }

    /**
     * The ORDER BY the request asks for, for either statement that cuts a page.
     *
     * Three orderings, and the grouped default is core's own - the accordion
     * organises the page, so inside it the order is the one every other
     * listing of the site uses. 'recent' puts a course with no start date last
     * through a portable CASE rather than NULLS LAST, which only PostgreSQL
     * has: a zero start date is not a date, and sorting it as 1970 would put
     * such courses last only by accident of the epoch.
     *
     * @param criteria $criteria The request.
     * @return string The ORDER BY list, without the keyword.
     */
    public static function order_sql(criteria $criteria): string {
        return match ($criteria->sort) {
            criteria::SORT_NAME => 'c.fullname ASC, c.id ASC',
            criteria::SORT_RECENT => 'CASE WHEN c.startdate = 0 THEN 1 ELSE 0 END ASC, c.startdate DESC, c.id DESC',
            default => 'c.sortorder ASC, c.id ASC',
        };
    }

    /**
     * The start-date predicate of the request, for either statement that cuts a page.
     *
     * What a period keeps, with one bound or both, is {@see criteria}'s rule;
     * this is its SQL. Each bound is its own comparison and either may be
     * absent. Two placeholder NAMES even though one control produced both
     * values: a named placeholder may appear only once per statement
     * ({@see \moodle_database::fix_sql_params()} counts occurrences). The upper
     * bound is exclusive and is already midnight of the day after the one
     * chosen, so a course starting at any hour of the last day is inside it.
     *
     * A course with no start date (0) is outside every bounded range, the rule
     * the 'recent' ordering states from the other side: a zero is not a date.
     * It is stated explicitly rather than left to the lower bound, because an
     * upper bound on its own would otherwise keep every undated course - as if
     * "starting on or before the 19th" were true of a course that starts on no
     * day at all. The literal is a constant, never a value from the request, so
     * it needs no placeholder.
     *
     * @param criteria $criteria The request.
     * @return array The SQL fragment (possibly empty) at index 0 and its parameters at index 1.
     */
    public static function date_sql(criteria $criteria): array {
        $clauses = [];
        $params = [];
        if ($criteria->fromtime !== null || $criteria->totime !== null) {
            $clauses[] = 'c.startdate > 0';
        }
        if ($criteria->fromtime !== null) {
            $clauses[] = 'c.startdate >= :datefrom';
            $params['datefrom'] = $criteria->fromtime;
        }
        if ($criteria->totime !== null) {
            $clauses[] = 'c.startdate < :dateto';
            $params['dateto'] = $criteria->totime;
        }
        return [$clauses ? implode(' AND ', $clauses) : '', $params];
    }

    /**
     * Whether the page's own rows are the direct courses alone, the accordion holding the rest.
     *
     * The partition. In the grouped organisation the page's own rows are the
     * courses that sit directly in the listed category and nothing else, so a
     * course of a child is on the page exactly once - inside that child's
     * panel - and a number on the page's own group is the number of rows under
     * it.
     *
     * It is a late cut rather than a predicate because of what it must not
     * cut: the hero's total and hidden count, the status pills, the
     * custom-field facets and every accordion header keep reading the subtree.
     * Switching the organisation must not change what the page says is in the
     * category, and a header counting only its own direct courses would
     * contradict the panel it opens.
     *
     * @param criteria $criteria The request.
     * @return bool
     */
    public static function partitions(criteria $criteria): bool {
        return $criteria->is_grouped();
    }

    /**
     * The categories the tree's own criterion narrows a population to: the chosen one and everything below it.
     *
     * The tree is a filter, so pressing a category beside a flat listing
     * narrows that listing to its subtree instead of opening its page. What
     * comes back here is that subtree, taken from the scope's own rows - every
     * category of the admitted subtree is already in hand with its stored
     * path - so the criterion costs no statement.
     *
     * It narrows and never replaces. Both callers AND the list below with the
     * population's own category predicate, so a cat naming a category this
     * viewer may not draw from cannot widen anything: the lists do not
     * intersect and the page comes back empty. Without the guard in the middle
     * of this method a foreign id therefore empties the listing rather than
     * leaking a course.
     *
     * A criterion is dropped silently, because it arrives from a query string
     * and a page that refuses to render cannot be linked to, in four cases: no
     * category at all; the grouped organisation, which draws no tree
     * ({@see criteria::$cat} drops that one at construction); the root of the
     * course index, a directory that reads no other narrowing criterion
     * either; and the page's own category, which means "all of it" and is what
     * the first entry of the tree clears with. So is an id that is not an admitted
     * category of the subtree - a foreign one, one that does not exist, one this
     * viewer may not browse - and {@see resolve()} then resets it on the request
     * object, so the answer, every address and every count are those of a request
     * with no criterion. This is the one authority for that rule: both web services
     * ({@see \theme_dimensions\external\get_category_rows},
     * {@see \theme_dimensions\external\get_public_category_rows}) pass
     * `cat` through unchecked, because a refusal for some ids and not others would
     * tell the caller which ids exist and which are withheld.
     *
     * @param scope $scope The admitted subtree, rooted at the page's own category.
     * @param criteria $criteria The request.
     * @return int[] The chosen category and its admitted descendants, empty when nothing is chosen.
     */
    public static function cat_ids(scope $scope, criteria $criteria): array {
        $catid = $criteria->cat;
        if ($catid <= 0 || $criteria->is_grouped() || $scope->rootid === 0 || $catid === $scope->rootid) {
            return [];
        }
        /* An id that is not a category of this subtree, or one this viewer may not browse, is
           not a filter this page offered: the tree draws an entry only for a category that
           passed both, so anything else is a hand-edited address or a stale link. */
        if (!isset($scope->categories[$catid]) || !$scope->is_open($catid)) {
            return [];
        }
        $ids = [$catid];
        foreach ($scope->categories as $id => $row) {
            if ((int) $id !== $catid && in_array($catid, self::path_ids($row->path), true)) {
                $ids[] = (int) $id;
            }
        }
        return $ids;
    }

    /**
     * The predicate narrowing a population to the subtree the tree's criterion chose.
     *
     * It goes into both page-cutting statements - {@see records()} for the PHP
     * cut and {@see sql_predicate()} for the SQL cut - for the reason the date
     * range is in both: a filter honoured by one mode and ignored by the other
     * is one address answering two ways with only a setting between them. It
     * is appended BEFORE the facets are counted in either mode, because the
     * tree is a filter like the others and the numbers beside the pills count
     * what is on the page.
     *
     * The placeholders are {@see \moodle_database::get_in_or_equal()}'s own,
     * under a prefix of this criterion's alone, so each one is bound once per
     * statement and none of them collides with the population's own list.
     *
     * @param scope $scope The admitted subtree.
     * @param criteria $criteria The request.
     * @return array The SQL fragment (empty when nothing is chosen) at 0, its parameters at 1.
     */
    public static function cat_sql(scope $scope, criteria $criteria): array {
        global $DB;

        $ids = self::cat_ids($scope, $criteria);
        if (!$ids) {
            return ['', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'catf');
        return ["c.category {$insql}", $params];
    }

    /**
     * Resolve one page of the listing for the viewer of the session.
     *
     * @param criteria $criteria What the request asks for.
     * @return page_result The page, every number post-filter.
     * @throws unavailable_exception When the root category is not one this viewer may list.
     */
    public static function resolve(criteria $criteria): page_result {
        $scope = self::scope($criteria);
        /* The tree's criterion is admitted or dropped HERE, on the one object every later reader
           of this request holds: the toolbar's filter count, the clear link, the pager's
           parameters and the tree's own marking are then all describing the listing that was
           really drawn. Dropping it later - or only inside the statement - would leave the page
           saying that a filter narrows it while nothing does. */
        if ($criteria->cat > 0 && !self::cat_ids($scope, $criteria)) {
            $criteria->cat = 0;
        }
        /* The state chip and the favourites, for the same reason: only a logged-in page cut in PHP
           classifies its courses, so a visitor's page and the SQL cut narrow nothing by either
           (resolve_sql()) and draw neither control. Kept on the request, either would put the page in
           result mode over a listing nothing narrowed, with a filter no control on the page can show
           or clear. */
        if ($scope->anonymous || !self::state_sort()) {
            $criteria->state = null;
            $criteria->favourites = false;
        }
        if ($scope->rootid === 0) {
            return self::resolve_root($scope, $criteria);
        }
        $statesort = self::state_sort();
        if (!$statesort) {
            return self::resolve_sql($scope, $criteria);
        }

        $result = new page_result();
        $result->page = $criteria->page;
        $result->pagesize = self::page_size($criteria->pagesize);
        $result->anonymous = $scope->anonymous;
        $result->statesort = true;

        $records = self::records($scope, $criteria);
        $kept = self::gate($scope, $records);

        $verdicts = $scope->anonymous ? [] : classifier::classify(array_keys($kept), $scope);

        // The facets read one map for the whole population; the filter and the counts derive from it.
        $fields = facets::fields();
        $values = facets::values(array_keys($fields), $scope->population_categories());
        $ids = facets::apply($values, array_keys($kept), $criteria->fields);

        /* The state chip and the favourites are two criteria that combine, and each is counted with
           the other applied, as the facets are: a chip's number is what pressing it would leave under
           the favourites in force, and the favourites' number what pressing the toggle would leave
           under the chip in force. Both are taken before either narrows the ids, so the chips can
           print their numbers. */
        $counts = ['total' => count($ids)];
        $result->hidden = count(array_filter($ids, static function (int $courseid) use ($kept): bool {
            return empty($kept[$courseid]->visible);
        }));
        if (!$scope->anonymous) {
            // Counted and filtered by chip, not by state: several states share a chip (classifier::chip()).
            foreach (classifier::chips() as $chip) {
                $counts[$chip] = 0;
            }
            $counts['total'] = 0;
            $counts[classifier::CHIP_FAVOURITES] = 0;
            foreach ($ids as $courseid) {
                $chip = classifier::chip($verdicts[$courseid]['state']);
                $favourite = $verdicts[$courseid]['favourite'];
                if ($favourite || !$criteria->favourites) {
                    $counts['total']++;
                    $counts[$chip]++;
                }
                if ($favourite && ($criteria->state === null || $chip === $criteria->state)) {
                    $counts[classifier::CHIP_FAVOURITES]++;
                }
            }
            if ($criteria->state !== null) {
                $ids = array_values(array_filter($ids, static function (int $courseid) use ($verdicts, $criteria): bool {
                    return classifier::chip($verdicts[$courseid]['state']) === $criteria->state;
                }));
            }
            if ($criteria->favourites) {
                $ids = array_values(array_filter($ids, static function (int $courseid) use ($verdicts): bool {
                    return $verdicts[$courseid]['favourite'];
                }));
            }
        }
        $result->counts = $counts;
        $result->facets = facets::groups($fields, $values, $ids, $criteria->fields);

        /* The organisation the request asked for. The grouped default sorts the state group
           first and keeps core's own order inside it, because the accordion organises that page
           and the order inside a group is the site's ordinary one. A flat organisation replaces
           the state group rather than sorting within it: "A-Z" that put the reader's own courses
           first would not be A-Z. */
        if (!$criteria->is_grouped()) {
            self::sort_flat($ids, $kept, $criteria);
        } else if (!$scope->anonymous) {
            // Group by state, keeping core's order inside each group (usort is stable on PHP 8).
            usort($ids, static function (int $a, int $b) use ($verdicts): int {
                return classifier::rank($verdicts[$a]['state']) <=> classifier::rank($verdicts[$b]['state']);
            });
        }

        /* The accordion's numbers, free in this mode: the population is in hand, so one pass over
           the ids the page counted is the whole tally and the roll-up walks stored paths. It is
           taken over the post-filter ids rather than over $kept, so a header's number and the
           count line beside it answer the same request, and before the partition below, so a
           header counts its child's whole subtree while the page under it lists none of it. A
           panel's request under a criterion takes the same tally for its strip: only the subareas
           holding a result are offered then. */
        $tally = [];
        if ($criteria->is_grouped() || ($criteria->withcategories && $criteria->has_filters())) {
            foreach ($ids as $courseid) {
                $categoryid = (int) $kept[$courseid]->category;
                $tally[$categoryid] = ($tally[$categoryid] ?? 0) + 1;
            }
        }

        /* The partition (see partitions()). Everything counted above spans the subtree; from
           here the page is the direct courses alone, so the count line, the pager and the rows
           agree with each other and with the panels below them. */
        if (self::partitions($criteria)) {
            $ids = array_values(array_filter($ids, static function (int $courseid) use ($kept, $scope): bool {
                return (int) $kept[$courseid]->category === $scope->rootid;
            }));
        }

        $result->total = count($ids);
        $result->page = self::clamp_page($result->page, $result->total, $result->pagesize);
        $slice = array_slice($ids, $result->page * $result->pagesize, $result->pagesize);
        $result->hasmore = ($result->page + 1) * $result->pagesize < $result->total;
        foreach ($slice as $courseid) {
            $row = $kept[$courseid];
            /* Each filter field's value, 0 where the course holds none: the facts the filter line's preview
               decides a field option on. The map is already in hand, so this costs nothing; which of them a
               card may state is the card's decision (category_card::field_values()). The SQL cut reads no
               such map and its rows carry none, so its cards preview no field option. */
            $row->fieldvalues = [];
            foreach ($values as $fieldid => $map) {
                $row->fieldvalues[(int) $fieldid] = (int) ($map[$courseid] ?? 0);
            }
            if (!$scope->anonymous) {
                $row->state = $verdicts[$courseid]['state'];
                $row->favourite = $verdicts[$courseid]['favourite'];
                $row->startsat = $verdicts[$courseid]['startsat'];
                $row->endsat = $verdicts[$courseid]['endsat'];
                $row->prerequisiteid = $verdicts[$courseid]['prerequisiteid'];
                $row->opening = $verdicts[$courseid]['opening'];
            }
            $result->rows[$courseid] = $row;
        }
        if ($scope->anonymous) {
            $result->rows = self::replay_public($result->rows);
        }
        // Before the page's own tree, so the category states it reads are already memoised.
        $resttree = self::tree_at_rest($scope, $criteria);
        // A flat page's tree is a filter, so a visitor's names what it names at rest (tree_at_rest()).
        $result->subcategories = $resttree !== null && !self::partitions($criteria)
            ? $resttree
            : self::subcategory_tree($scope, $kept);
        if (self::partitions($criteria)) {
            $result->accordion = self::accordion($scope, $resttree ?? $result->subcategories, $tally);
        }
        if ($criteria->withcategories) {
            $result->subareas = self::subareas($scope, $result->subcategories, $criteria, $tally);
        }
        $result->categorynames = self::category_names($scope, $result->rows);
        self::posters_and_panel($scope, $criteria, $result);
        return $result;
    }

    /**
     * Order the page's ids by name or by start date, in the mode that cuts the page in PHP.
     *
     * The comparison is {@see \core_text}'s rather than a raw strcmp, so that a
     * name differing only in case orders the same way here as it does in the
     * statement the other mode uses. What the two modes cannot be made to agree
     * about is an accented name: the SQL side is the database's collation and
     * this side is PHP's byte order after a case fold, and the two disagree
     * about "Á" against "B" on some collations - the same cross-database
     * caveat the search LIKE carries.
     *
     * @param int[] $ids The ids to order, in place.
     * @param \stdClass[] $kept The population rows, keyed by course id.
     * @param criteria $criteria The request.
     * @return void
     */
    private static function sort_flat(array &$ids, array $kept, criteria $criteria): void {
        if ($criteria->sort === criteria::SORT_NAME) {
            usort($ids, static function (int $a, int $b) use ($kept): int {
                $first = \core_text::strtolower((string) $kept[$a]->fullname);
                $second = \core_text::strtolower((string) $kept[$b]->fullname);
                return [$first, $a] <=> [$second, $b];
            });
            return;
        }
        if ($criteria->sort === criteria::SORT_RECENT) {
            /* The same three keys the statement orders by, in the same order: a course with no
               start date last, then the newest first, then the newest id first. */
            usort($ids, static function (int $a, int $b) use ($kept): int {
                $firststart = (int) $kept[$a]->startdate;
                $secondstart = (int) $kept[$b]->startdate;
                return [$firststart === 0 ? 1 : 0, -$firststart, -$a]
                    <=> [$secondstart === 0 ? 1 : 0, -$secondstart, -$b];
            });
        }
    }

    /**
     * The accordion of the grouped organisation: one entry per direct child, with the courses below it.
     *
     * One level, and the panels partition the page. The page's own rows are
     * the courses sitting directly in this category ({@see partitions()}) and
     * each entry counts its child's whole subtree, so the numbers add up to the
     * subtree and no course is drawn twice. The entries are the set the
     * posters are drawn from ({@see posters()}), and a child's panel is its own
     * listing over its whole subtree - drawn by the server for an address
     * carrying `open`, fetched by the client on open - so the child's own gate
     * chain applies and no grouped statement exists anywhere. Two panels for
     * categories on one path would list the same course twice, which is why
     * the entries are the direct children and nothing deeper, whatever the
     * tree depth offers as navigation.
     *
     * The numbers are the page's own tally rolled up the stored paths, so a
     * child's number spans its subtree and the accordion costs no statement per
     * child in either mode. They inherit the probe cap exactly as the listing
     * does: past {@see PROBE_CAP} unrelated unlisted courses the population
     * itself is short, and every number taken from it is short with it.
     *
     * The tree is the page's own, except for a visitor under a criterion, whose accordion is drawn
     * from the tree at rest ({@see tree_at_rest()}): the entries are then the children holding a
     * public course, and a child the criterion misses is an entry counting 0.
     *
     * @param scope $scope The admitted subtree.
     * @param array $tree The navigation tree of {@see subcategory_tree()}, already depth-limited.
     * @param array $tally categoryid => how many courses of this page's population sit directly in it.
     * @return array Entries of ['id' => int, 'name' => plain string, 'count' => int, 'url' => string,
     *   'linkable' => bool]. A non-linkable entry (a merely listed child on the anonymous shell)
     *   carries an empty url: the visitor reads the name and its number, and there is no page to open.
     */
    public static function accordion(scope $scope, array $tree, array $tally): array {
        $totals = self::rollup($scope, $tree, $tally);
        if (!$totals) {
            return [];
        }
        $groups = [];
        foreach ($tree as $entry) {
            if ((int) $entry['depth'] !== 1) {
                continue;
            }
            $id = (int) $entry['id'];
            $linkable = !empty($entry['linkable']);
            $groups[] = [
                'id' => $id,
                'name' => $entry['name'],
                'count' => $totals[$id],
                'url' => $linkable ? links::showcase($id, [], $scope->anonymous)->out(false) : '',
                'linkable' => $linkable,
            ];
        }
        return $groups;
    }

    /**
     * The depth-1 entries of a tree, each with how many courses of a tally sit anywhere in its subtree.
     *
     * The roll-up the accordion's numbers and a panel's strip under a criterion share: the tally
     * holds a category's own courses, and each is added to every depth-1 ancestor its stored path
     * names. It walks rows already in hand and costs no statement.
     *
     * @param scope $scope The admitted subtree.
     * @param array $tree The navigation tree of {@see subcategory_tree()}.
     * @param array $tally categoryid => how many courses of the population sit directly in it.
     * @return int[] depth-1 category id => its subtree's count, in the tree's order; empty for a tree
     *   with no depth-1 entry.
     */
    private static function rollup(scope $scope, array $tree, array $tally): array {
        $totals = [];
        foreach ($tree as $entry) {
            if ((int) $entry['depth'] === 1) {
                $totals[(int) $entry['id']] = 0;
            }
        }
        if (!$totals) {
            return [];
        }
        foreach ($tally as $categoryid => $count) {
            $categoryid = (int) $categoryid;
            if (!isset($scope->categories[$categoryid])) {
                continue;
            }
            foreach (self::path_ids($scope->categories[$categoryid]->path) as $ancestor) {
                if (isset($totals[$ancestor])) {
                    $totals[$ancestor] += (int) $count;
                }
            }
        }
        return $totals;
    }

    /**
     * The tree a visitor's narrowed page is drawn from: the one at rest.
     *
     * A visitor's tree names a child only when a course of the population sits below it
     * ({@see subcategory_tree()}), and under a criterion that population is already narrowed. On
     * the grouped page a child the search misses would then vanish from the accordion instead of
     * collapsing into the "nothing in N areas" summary a logged-in page draws; on a flat page the
     * filter tree would shrink to the entry chosen, so a visitor who chose one child, or reloaded
     * its address, could not choose its sibling. A logged-in tree names every open child whatever
     * the criteria, so this asks once which categories hold a public course at all: one grouped
     * statement over the anonymous predicate with no criterion, which for a visitor is exactly
     * "visible, public, in an open category" in either page-cutting mode. The accordion's numbers
     * still come from the page's own post-filter tally, so a child the criterion misses is an
     * entry counting 0.
     *
     * Called before the page's own tree is built, so that the category states the smaller tree
     * reads are already memoised and the whole difference is the one statement. A flat page spends
     * it only on the page's own request ({@see criteria::$withposters}): the web service's answer
     * carries no tree, and a filter fetch is what the visitor's client sends on every keystroke.
     *
     * @param scope $scope The admitted subtree.
     * @param criteria $criteria The request.
     * @return array|null The tree at rest for a visitor's grouped page, or flat page drawn by the
     *   server, under a criterion; null everywhere else, where the page's own tree is used and
     *   nothing is spent.
     */
    private static function tree_at_rest(scope $scope, criteria $criteria): ?array {
        global $DB;

        $atrest = $scope->anonymous && $criteria->has_filters() && (self::partitions($criteria) || $criteria->withposters);
        if (!$atrest) {
            return null;
        }
        [$where, $params, $join] = self::sql_predicate($scope, new criteria($scope->rootid, ['anonymous' => true]));
        $rest = $DB->get_records_sql_menu(
            "SELECT c.category, COUNT(1) FROM {course} c {$join} WHERE {$where} GROUP BY c.category",
            $params
        );
        return self::subcategory_tree($scope, self::populated_rows($rest));
    }

    /**
     * Resolve the root of the course index: a directory of categories, and nothing of a listing.
     *
     * A course can never sit directly in the top-level pseudo-category, so the
     * root is one card per admitted top-level category. Everything a listing
     * needs is dead weight here, and the root is the busiest page of the site:
     * the classifier's enrolment map, the facet map, the sort, the page slice,
     * the subcategory tree and the card names are all skipped, because at the
     * root they would be computed over every category and every course of the
     * site and then thrown away by {@see \theme_dimensions\output\category_listing}.
     * What survives is the total and the hidden count, which the hero prints;
     * the cards print no number of their own at rest.
     *
     * The root honours the search and no filter, and the rule lives here rather than in the
     * renderer. The page draws a search box and nothing else that narrows, so a chip, a field
     * filter, a period or a tree category arriving on the query string would change every
     * poster's number with nothing on the page admitting it - and the two page-cutting modes
     * would disagree about a field filter, the PHP cut applying it after the gate and the SQL cut
     * folding it into the predicate it groups over. One request carrying the term alone is
     * resolved instead, whoever asks and in whichever mode.
     *
     * At rest (no term) the mode decides how the hero's two numbers are reached: with the state
     * sort on, the population is materialised once and counted in PHP, which is also where the
     * hidden count comes from; with it off, one COUNT over the predicate a page would have been
     * cut with, and no hidden count.
     *
     * Under a term the posters wear how many matches their area holds, so the matches are tallied
     * per category and rolled up to the top-level areas: free in the PHP cut, which holds the
     * population, and one grouped statement in the SQL cut, which replaces the COUNT (the total
     * is the tally's sum). Each directory entry then carries its `count`, and the result's
     * accordion names every poster with it - what the web service hands back and what the areas'
     * result mode reads. The two modes agree up to the probe cap because the SQL cut's two
     * exception lists read the matches as the PHP cut's probe does ({@see sql_predicate()});
     * past fifty unrelated unlisted matches both under-count, the same way and fail-closed. One
     * cap is the SQL cut's alone: it rescues at most fifty hidden matches
     * ({@see rescued_hidden()}) where the PHP cut asks of every one, so a viewer who may see more
     * than fifty hidden matches - staff, on a large site - reads fewer with the state sort off.
     *
     * The root also takes its panel from the request: every poster of the root opens one, and
     * {@see criteria::$open} is admitted against the directory's own ids, as a category page
     * admits it against its posters.
     *
     * @param scope $scope The admitted subtree, rooted at 0.
     * @param criteria $criteria The request, read for its search and its panel alone.
     * @return page_result The directory, its total and its hidden count, and under a term each
     *   poster's count and the accordion; no rows, no facets.
     */
    private static function resolve_root(scope $scope, criteria $criteria): page_result {
        global $DB;

        $result = new page_result();
        $result->statesort = self::state_sort();
        $result->pagesize = self::page_size();
        /* Which shell is drawing is not a filter and is carried over: the hero's title and its
           trail, and every address the directory mints, are decided by it, and a root that
           forgot it would hand a visitor the logged-in page's name and links. */
        $result->anonymous = $scope->anonymous;
        // The term and nothing else, page 0: see the docblock.
        $term = $criteria->search;
        $plain = new criteria(0, ['anonymous' => $scope->anonymous, 'search' => $term]);

        $tally = [];
        if ($result->statesort) {
            $kept = self::gate($scope, self::records($scope, $plain));
            $result->total = count($kept);
            $result->hidden = count(array_filter($kept, static function (\stdClass $row): bool {
                return empty($row->visible);
            }));
            if ($term !== '') {
                foreach ($kept as $row) {
                    $categoryid = (int) $row->category;
                    $tally[$categoryid] = ($tally[$categoryid] ?? 0) + 1;
                }
            }
        } else {
            [$where, $params, $join] = self::sql_predicate($scope, $plain);
            if ($term !== '') {
                $tally = $DB->get_records_sql_menu(
                    "SELECT c.category, COUNT(1) FROM {course} c {$join} WHERE {$where} GROUP BY c.category",
                    $params
                );
                $result->total = (int) array_sum($tally);
            } else {
                $result->total = (int) $DB->count_records_sql(
                    "SELECT COUNT(1) FROM {course} c {$join} WHERE {$where}",
                    $params
                );
            }
        }

        $result->counts = ['total' => $result->total];
        $result->directory = self::directory($scope);
        if ($term !== '') {
            self::root_counts($scope, $result, $tally);
        }
        self::open_panel($criteria, $result, $result->directory, true);
        return $result;
    }

    /**
     * Under a search at the root, each poster's number of matches, and the accordion that carries them.
     *
     * The directory's entries are the top-level areas, so a tally rolls up to them through the
     * stored path of each category it names - in hand, no statement. A match in a category no
     * poster shows (a top-level category this viewer may not browse, whose course was rescued by
     * an enrolment) is in the hero's total and in no poster, as on a category page.
     *
     * @param scope $scope The admitted subtree, rooted at 0.
     * @param page_result $result The root being resolved: its directory entries gain `count`.
     * @param array $tally categoryid => how many matches sit directly in it.
     * @return void
     */
    private static function root_counts(scope $scope, page_result $result, array $tally): void {
        $tree = array_map(
            static fn(array $entry): array => ['id' => (int) $entry['id'], 'depth' => 1],
            $result->directory
        );
        $totals = self::rollup($scope, $tree, $tally);
        $result->accordion = [];
        foreach ($result->directory as $index => $entry) {
            $count = (int) ($totals[(int) $entry['id']] ?? 0);
            $result->directory[$index]['count'] = $count;
            $result->accordion[] = [
                'id' => (int) $entry['id'],
                'name' => $entry['name'],
                'count' => $count,
                'url' => $entry['url'],
                'linkable' => true,
            ];
        }
    }

    /**
     * The page to cut: a request past the last page lands on the last page, never on an empty one.
     *
     * @param int $page The requested zero-based page.
     * @param int $total The post-filter total.
     * @param int $pagesize The page size.
     * @return int
     */
    private static function clamp_page(int $page, int $total, int $pagesize): int {
        $last = max(0, (int) ceil($total / $pagesize) - 1);
        return min($page, $last);
    }

    /**
     * The plain names of the categories the page's rows belong to, from rows the scope already holds.
     *
     * A card prints its own subcategory as an overline; the names
     * come from the subtree statement's rows and the contexts it preloaded,
     * so the page pays no statement for them however many categories it spans.
     *
     * @param scope $scope The admitted subtree.
     * @param \stdClass[] $rows The page slice.
     * @return string[] categoryid => plain name.
     */
    private static function category_names(scope $scope, array $rows): array {
        $names = [];
        foreach ($rows as $row) {
            $categoryid = (int) $row->category;
            // A clamped category is never named: the chips withhold it, and so must the overline.
            if (isset($names[$categoryid]) || !isset($scope->categories[$categoryid]) || !$scope->is_open($categoryid)) {
                continue;
            }
            $names[$categoryid] = format_string($scope->categories[$categoryid]->name, true, [
                'context' => \core\context\coursecat::instance($categoryid),
                'escape' => false,
            ]);
        }
        return $names;
    }

    /**
     * The subtree this viewer may draw from: both category gates, the viewer's enrolments, the clamps.
     *
     * @param criteria $criteria The request.
     * @return scope
     * @throws unavailable_exception When the root is missing, hidden, withheld or (for a visitor) not public.
     * @throws \coding_exception When the anonymous population is resolved in a logged-in session.
     */
    public static function scope(criteria $criteria): scope {
        global $DB, $USER;

        $scope = new scope();
        $scope->rootid = $criteria->categoryid;
        $scope->anonymous = $criteria->anonymous;
        $scope->viewerid = $criteria->anonymous ? 0 : (int) $USER->id;

        if ($scope->rootid < 0) {
            throw new unavailable_exception();
        }
        if ($scope->anonymous) {
            /* The category half of the anonymous predicate reads the live session (category_access
               answers as for a visitor only when nobody, or the guest, is logged in), so an
               "anonymous" page built for a real account would admit what a visitor is refused. */
            if (isloggedin() && !isguestuser()) {
                throw new \coding_exception('The anonymous population may only be resolved in a visitor session.');
            }
            if ($scope->rootid === 0) {
                /* The root of the course index is on the internet only where the site says so.
                   The pseudo-category holds no row, so the public predicate has nothing it could
                   be asked; publicaccess::is_public(0) is the whole chain - the feature, the
                   surface, the root's own setting and the predicate class existing. The guest
                   account is refused whatever that answer is: the anonymous shell never draws for
                   it. The refusal is the same exception a nonexistent id gets. */
                if (isguestuser() || !publicaccess::is_public(0)) {
                    throw new unavailable_exception();
                }
            } else if (!publicaccess::is_public($scope->rootid)) {
                /* The whole public chain, as every entry asks it - the feature, the surface, the
                   predicate class and the plugin's public state - and not the plugin alone: the
                   entries ask first, and this layer still owns the refusal when one of them does
                   not. Non-throwing, before any lookup: a missing id answers false. */
                throw new unavailable_exception();
            }
        }
        /* The root of the logged-in shell is a real account's alone, and this class owns that
           refusal rather than the renderer, which falls back to core's page for the same two
           viewer classes. Neither gate below has anything to ask about the pseudo-category - it
           holds no row, no context and no discoverability state - so without this line any caller
           resolving category 0 in a visitor or guest session would be handed a directory of
           whatever that session may list. The anonymous scope is exempt because it has just been
           through the whole public chain above. The renderer's own check stays, as defence in
           depth. */
        if ($scope->rootid === 0 && !$scope->anonymous && (!isloggedin() || isguestuser())) {
            throw new unavailable_exception();
        }

        $root = core_course_category::get($scope->rootid, IGNORE_MISSING, true);
        if (!$root) {
            throw new unavailable_exception();
        }

        /* The subtree ids come from the coursecattree cache: zero reads once warm, at any size.
           The root of the course index has no row of its own, so it is not one of them: the
           subtree is every category on the site and the gates below are asked of each of them. */
        $subtree = array_map('intval', $root->get_all_children_ids());
        if ($scope->rootid !== 0) {
            $subtree[] = $scope->rootid;
        }
        if (!$subtree) {
            // A site whose whole tree is one empty root: nothing to list and nothing to gate.
            return $scope;
        }
        [$insql, $params] = $DB->get_in_or_equal($subtree, SQL_PARAMS_NAMED, 'cat');
        $params['ctxlevel'] = CONTEXT_COURSECAT;
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        // Rides the primary key of {course_categories}; the contexts arrive in the same statement.
        $rows = $DB->get_records_sql(
            "SELECT cc.id, cc.name, cc.parent, cc.sortorder, cc.visible, cc.path, cc.depth, {$ctxselect}
               FROM {course_categories} cc
               JOIN {context} ctx ON ctx.instanceid = cc.id AND ctx.contextlevel = :ctxlevel
              WHERE cc.id {$insql}
           ORDER BY cc.depth ASC, cc.sortorder ASC",
            $params
        );
        foreach ($rows as $row) {
            $scope->categories[(int) $row->id] = $row;
            // Both shells: the tree formats its names in each child's context, and the preload is free.
            context_helper::preload_from_record(clone $row);
            if ((int) $row->parent === $scope->rootid) {
                $scope->children[] = (int) $row->id;
            }
        }
        if ($scope->rootid !== 0 && !isset($scope->categories[$scope->rootid])) {
            throw new unavailable_exception();
        }

        if ($scope->anonymous) {
            self::admit_for_visitor($scope);
            return $scope;
        }

        // Core's gate for the root, then the plugin's; one refusal for both. The root of the course
        // index has no row, no context and no state, so both gates are skipped for it: its
        // categories are gated one by one below.
        if ($scope->rootid !== 0) {
            $rootrow = $scope->categories[$scope->rootid];
            if (!core_course_category::can_view_category($rootrow)) {
                throw new unavailable_exception();
            }
            if (!category_access::filter_categories([$rootrow])) {
                throw new unavailable_exception();
            }
        }

        $scope->enrolments = classifier::enrolments($scope->viewerid);
        $active = [];
        $pending = [];
        $scheduled = [];
        foreach ($scope->enrolments as $courseid => $mine) {
            if ($mine->active) {
                $active[] = $courseid;
            } else if ($mine->scheduled) {
                $scheduled[] = $courseid;
            } else if ($mine->pending) {
                $pending[] = $courseid;
            }
        }
        // The plugin's enrolment terms: an active row, one that starts later, an application awaiting a decision.
        access::prime_relationships($active, $pending, $scheduled);

        self::admit_for_viewer($scope);
        return $scope;
    }

    /**
     * Sort the subtree into open and clamped categories for a logged-in viewer.
     *
     * Core's rule per category is {@see core_course_category::can_view_category()}
     * - hidden needs moodle/category:viewhiddencategories, all need
     * moodle/category:viewcourselist - and a category whose ancestor inside
     * the subtree fails it is clamped with it: a hidden branch is withheld
     * with everything under it, which is stricter than core's own recursive
     * listing (that one checks each course's category alone) and is the
     * reading the discoverability rules ask for. The plugin's rule walks the
     * whole path itself ({@see category_access::filter_categories()}).
     *
     * @param scope $scope The scope with its categories loaded and their contexts preloaded.
     * @return void
     */
    private static function admit_for_viewer(scope $scope): void {
        $corefailed = [];
        foreach ($scope->categories as $id => $row) {
            $failed = false;
            foreach (self::path_ids($row->path) as $ancestor) {
                if (isset($corefailed[$ancestor])) {
                    $failed = true;
                    break;
                }
            }
            if (!$failed && !core_course_category::can_view_category($row)) {
                $failed = true;
            }
            if ($failed) {
                $corefailed[$id] = true;
            }
        }

        $pluginkept = [];
        foreach (category_access::filter_categories($scope->categories) as $row) {
            $pluginkept[(int) $row->id] = true;
        }

        foreach ($scope->categories as $id => $row) {
            $clamp = 0;
            if (isset($corefailed[$id])) {
                $clamp |= scope::CLAMP_CORE;
            }
            if (!isset($pluginkept[$id])) {
                $clamp |= scope::CLAMP_PLUGIN;
            }
            if ($clamp === 0) {
                $scope->open[] = $id;
            } else {
                $scope->clamps[$id] = $clamp;
            }
        }
    }

    /**
     * Admit the categories a visitor may be shown: visible along the whole path, and nothing unlisted on it.
     *
     * No capability is asked - user id 0 holds none under forcelogin - so
     * visibility is replayed from the rows. The root's own ancestors above the
     * subtree were already checked by {@see category_discoverability::is_public()}.
     * A visitor has no rescue, so a category that fails is simply left out.
     *
     * @param scope $scope The scope with its categories loaded.
     * @return void
     */
    private static function admit_for_visitor(scope $scope): void {
        $hidden = [];
        foreach ($scope->categories as $id => $row) {
            $failed = !$row->visible;
            foreach (self::path_ids($row->path) as $ancestor) {
                if (isset($hidden[$ancestor])) {
                    $failed = true;
                    break;
                }
            }
            if ($failed) {
                $hidden[$id] = true;
            }
        }
        $pluginkept = [];
        foreach (category_access::filter_categories($scope->categories) as $row) {
            $pluginkept[(int) $row->id] = true;
        }
        foreach ($scope->categories as $id => $row) {
            if (!isset($hidden[$id]) && isset($pluginkept[$id])) {
                $scope->open[] = $id;
            }
        }
    }

    /**
     * The ordered course rows of the population, in one statement, with their context preload columns.
     *
     * Public because population_test measures this step alone: exactly one
     * statement whatever the subtree holds. Rides the category index of
     * {course}; the LEFT JOIN on the state table comes from the plugin and
     * carries no parameter. For a visitor the predicate already restricts to
     * visible courses whose own state is public; the PHP gate replays it.
     *
     * @param scope $scope The admitted subtree.
     * @param criteria $criteria The request: its search term, its date range and the tree's own.
     * @return \stdClass[] courseid => row (id, category, sortorder, visible, fullname, startdate, liststate,
     *   ctx columns), in the order the request asked for. The two sort columns are selected whichever
     *   organisation is asked for, so that the PHP-cut page can reorder the population it already holds
     *   without a second statement and the two modes read the same columns.
     */
    public static function records(scope $scope, criteria $criteria): array {
        global $DB;

        $categories = $scope->population_categories();
        if (!$categories) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        $params['contextcourse'] = CONTEXT_COURSE;
        $params['siteid'] = SITEID;
        $state = discoverability::state_sql('c');
        $where = "c.category {$insql} AND c.id <> :siteid";
        if ($scope->anonymous) {
            $where .= " AND c.visible = 1 AND {$state['column']} = :publicstate";
            $params['publicstate'] = discoverability::STATE_PUBLIC;
        }
        if ($criteria->search !== '') {
            [$searchsql, $searchparams] = self::search_sql($criteria->search);
            $where .= " AND {$searchsql}";
            $params += $searchparams;
        }
        [$datesql, $dateparams] = self::date_sql($criteria);
        if ($datesql !== '') {
            $where .= " AND {$datesql}";
            $params += $dateparams;
        }
        [$catsql, $catparams] = self::cat_sql($scope, $criteria);
        if ($catsql !== '') {
            $where .= " AND {$catsql}";
            $params += $catparams;
        }
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        return $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, c.fullname, c.startdate,
                    {$state['column']} AS liststate, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
                    {$state['join']}
              WHERE {$where}
           ORDER BY " . self::order_sql($criteria),
            $params
        );
    }

    /**
     * The rows among these that the showcase would list to this scope's viewer: {@see gate()}, for a caller outside the listing.
     *
     * The calendar's "open events" layer asks it of a handful of event courses over the root scope
     * (scope(new criteria(0))), so a course reaches the calendar exactly when the showcase would list it, by
     * the same core and local_unlistedcourses rules, never by a rule of the calendar's own. The rows carry
     * what records() selects (id, category, visible, liststate and the context preload columns); the list
     * goes to the plugin's probe as the population's does, so keep it to a page.
     *
     * An anonymous scope admits nothing here, and a row outside the scope's admitted categories is dropped.
     *
     * @param scope $scope An admitted scope whose categories hold every row's category.
     * @param \stdClass[] $records The rows, keyed by course id.
     * @return \stdClass[] The rows this viewer may see, same keys, same order.
     */
    public static function admitted(scope $scope, array $records): array {
        // A row whose category the scope never admitted (outside a narrowed root) is not this scope's to answer,
        // because gate() reads a missing clamp as no clamp.
        $known = array_flip($scope->population_categories());
        $rows = array_filter($records, static fn(\stdClass $row): bool => isset($known[(int) $row->category]));
        // The listing's statement gates an anonymous scope and gate() passes its rows through; a caller outside the
        // listing has no such statement, so it is answered nothing.
        return $scope->anonymous ? [] : self::gate($scope, $rows);
    }

    /**
     * The course-level gates over the population rows, core first, then the plugin.
     *
     * For a logged-in viewer, in order per course: core's visibility (a
     * hidden course needs viewhiddencourses at its context, enrolled or not, as
     * can_view_course_info() requires); the clamp of its category, if any (a core clamp is lifted by
     * an active enrolment, as get_course_records()'s $mycourses bypass does; a
     * plugin clamp by an active enrolment, an enrolment that starts later or an
     * application awaiting a decision - the plugin's enrolment terms - or staff standing, as
     * filter_courses()'s relationship rescue does); and the
     * plugin's own course term, asked of {@see access::filter_courses()} for
     * the unlisted courses only - the related ones all, the unrelated ones up
     * to the probe cap. A course withheld by the plugin's answer stays
     * withheld whatever its category's admission: the course term is the more
     * specific rule.
     *
     * For a visitor the statement already selected visible public courses in
     * admitted categories, so the rows pass through; {@see replay_public()}
     * runs the plugin's own predicate over the page slice afterwards.
     *
     * @param scope $scope The admitted subtree.
     * @param \stdClass[] $records The rows from {@see records()}.
     * @return \stdClass[] The rows this viewer may see, same keys, same order.
     */
    private static function gate(scope $scope, array $records): array {
        if ($scope->anonymous) {
            // The statement did the gating; the page slice is replayed through the plugin in resolve().
            return $records;
        }

        $kept = [];
        $relatedunlisted = [];
        $unrelated = [];
        foreach ($records as $courseid => $row) {
            $courseid = (int) $courseid;
            $mine = $scope->enrolments[$courseid] ?? null;
            $active = $mine && $mine->active;
            $related = $mine && ($mine->active || $mine->inactive);
            $clamp = $scope->clamps[(int) $row->category] ?? 0;

            if (!$row->visible) {
                if (!has_capability('moodle/course:viewhiddencourses', self::course_context($row))) {
                    continue;
                }
            }
            if (($clamp & scope::CLAMP_CORE) && !$active) {
                continue;
            }
            if (($clamp & scope::CLAMP_PLUGIN) && !$related) {
                $context = self::course_context($row);
                if (!is_viewing($context) && !has_capability('moodle/course:viewhiddencourses', $context)) {
                    continue;
                }
            }

            if ((int) $row->liststate === discoverability::STATE_UNLISTED) {
                if ($related) {
                    $relatedunlisted[$courseid] = $row;
                } else {
                    $unrelated[$courseid] = $row;
                }
                continue;
            }
            $kept[$courseid] = $row;
        }

        $probe = $relatedunlisted + array_slice($unrelated, 0, self::PROBE_CAP, true);
        if ($probe) {
            /* The plugin asks each probed course's context of core (its relationship check), one statement per
               course on a cold cache; the rows carry the preload columns, so the contexts are put in the cache
               first. From a clone, as course_context() does: the rows must keep their columns for page_contexts(). */
            foreach ($probe as $row) {
                context_helper::preload_from_record(clone $row);
            }
            foreach (access::filter_courses($probe) as $courseid => $row) {
                $kept[(int) $courseid] = $row;
            }
        }
        // Restore the statement's order: the probe's survivors were appended after the plain courses.
        $ordered = [];
        foreach ($records as $courseid => $row) {
            if (isset($kept[(int) $courseid])) {
                $ordered[(int) $courseid] = $row;
            }
        }
        return $ordered;
    }

    /**
     * The subcategories of the root this viewer may be offered, depth-first, each with its depth.
     *
     * Walks the whole admitted subtree, down to the requested depth: every
     * entry is followed immediately by its own descendants, and 'depth' (1 for
     * a direct child of the root) is what the navigation tree indents by. The
     * walk descends only into categories this viewer may browse, so a clamped
     * branch takes its whole subtree with it - a grandchild is never promoted
     * into the level above the parent that was withheld.
     *
     * For a visitor an entry is named only when at least one public course
     * sits somewhere below it, so a visitor is never offered a name that opens
     * onto nothing, and it is 'linkable' only when the category is itself
     * public, because that is the only page the anonymous shell has. A merely
     * listed child is still named - the card's own overline already prints it
     * - but has no link. Entries carry no count.
     *
     * @param scope $scope The admitted subtree.
     * @param \stdClass[] $population The gated rows, for the anonymous shell's non-empty rule.
     * @param int|null $maxdepth How many levels to offer, 0 for all of them, null for the setting.
     * @return array [['id' => int, 'name' => plain string, 'depth' => int, 'linkable' => bool], ...].
     */
    public static function subcategory_tree(scope $scope, array $population, ?int $maxdepth = null): array {
        // Children by parent, in the subtree statement's own order (depth, then sortorder).
        $bylevel = [];
        foreach ($scope->categories as $id => $row) {
            $bylevel[(int) $row->parent][] = $id;
        }

        $populated = $scope->anonymous ? self::populated_ancestors($scope, $population) : [];
        $admitted = [];
        self::walk_subtree(
            $scope,
            $bylevel,
            $scope->rootid,
            $populated,
            $admitted,
            $maxdepth === null ? self::tree_depth() : max(0, $maxdepth)
        );
        if (!$admitted) {
            return [];
        }

        // Bounded by the admitted subtree, never by the population.
        $public = $scope->anonymous ? category_discoverability::are_public(array_keys($admitted)) : [];
        $rootdepth = (int) ($scope->categories[$scope->rootid]->depth ?? 0);
        $tree = [];
        foreach ($admitted as $id => $unused) {
            $row = $scope->categories[$id];
            $tree[] = [
                'id' => $id,
                'name' => format_string($row->name, true, [
                    'context' => \core\context\coursecat::instance($id),
                    'escape' => false,
                ]),
                'depth' => (int) $row->depth - $rootdepth,
                'linkable' => $scope->anonymous ? (bool) ($public[$id] ?? false) : true,
            ];
        }
        return $tree;
    }

    /**
     * Depth-first walk of the admitted subtree, filling the ids in display order.
     *
     * @param scope $scope The admitted subtree.
     * @param array $bylevel parentid => [child id, ...] in tree order.
     * @param int $parentid The category whose children are being walked.
     * @param array $populated The anonymous shell's non-empty categories, id => true; empty otherwise.
     * @param array $admitted The accumulator, id => true, in display order.
     * @param int $maxdepth How many levels below the root to admit, 0 for all of them.
     * @param int $depth The level being walked, 1 for the root's own children.
     * @return void
     */
    private static function walk_subtree(
        scope $scope,
        array $bylevel,
        int $parentid,
        array $populated,
        array &$admitted,
        int $maxdepth = 0,
        int $depth = 1
    ): void {
        if ($maxdepth > 0 && $depth > $maxdepth) {
            return;
        }
        foreach ($bylevel[$parentid] ?? [] as $childid) {
            if (!$scope->is_open($childid)) {
                continue;
            }
            if (!$scope->anonymous || isset($populated[$childid])) {
                $admitted[$childid] = true;
            }
            self::walk_subtree($scope, $bylevel, $childid, $populated, $admitted, $maxdepth, $depth + 1);
        }
    }

    /**
     * Every category of the subtree holding a row of this population somewhere below it.
     *
     * @param scope $scope The admitted subtree.
     * @param \stdClass[] $population The gated rows.
     * @return array categoryid => true.
     */
    private static function populated_ancestors(scope $scope, array $population): array {
        $populated = [];
        foreach ($population as $row) {
            $categoryid = (int) $row->category;
            if (!isset($scope->categories[$categoryid])) {
                continue;
            }
            foreach (self::path_ids($scope->categories[$categoryid]->path) as $ancestor) {
                $populated[$ancestor] = true;
            }
        }
        return $populated;
    }

    /**
     * The directory of the root: one entry per top-level category this viewer may discover.
     *
     * A course can never sit directly in the top-level pseudo-category, so the
     * root is a directory rather than a listing: the hero, then a card per
     * admitted top-level category. A withheld child is not a card at all.
     *
     * No card carries a number at rest: a count per card would be tallied
     * twice, once per page-cutting mode, over material the two modes assemble
     * differently, and capped by the probe at fifty unrelated unlisted
     * courses, so a big site would print numbers that are quietly short. A
     * search at the root is the one exception: a pill of matches, taken by
     * {@see resolve_root()}, where both modes read the same window.
     *
     * The descriptions are one statement for the whole directory and the
     * branding comes from {@see branding::for_categories()}, asked once for every
     * card, cached across requests and viewer-independent, so a child costs no
     * statement beyond the text filters of its description - and a cold cache
     * resolves every card's inherited colours in one statement.
     *
     * For an anonymous scope the cards are the public children alone (see the
     * comment in the body) and the addresses are the anonymous shell's own.
     * The card itself is {@see visual()}, shared with a category page's
     * posters so the two cannot drift apart.
     *
     * @param scope $scope The admitted subtree, rooted at 0: the course index's own directory.
     * @return array Entries of {@see visual()}'s keys. The still follows a chain - the card
     *   cover, else the hero image, else the colour band - and the video is named only when the
     *   stored file's own bytes are a video this theme serves, so a card never links one the
     *   route would refuse. The badges are the child's own crests, from the same cached payload
     *   the colour comes from, so they cost no statement either. The style is the card's
     *   colours as custom properties, built through {@see branding::hex()} - the sanitiser that
     *   lets a free colour be emitted into a style attribute at all.
     */
    public static function directory(scope $scope): array {
        global $DB;

        $children = [];
        foreach ($scope->children as $childid) {
            if ($scope->is_open($childid)) {
                $children[$childid] = true;
            }
        }
        if (!$children) {
            return [];
        }
        if ($scope->anonymous) {
            /* The cards of the anonymous root are the public top-level categories and no others.
               admit_for_visitor() admits everything a visitor may be told exists - listed and
               public alike - which is the right set for the population, whose course rows the
               statement restricts to public courses, and the wrong set for a card: a merely
               listed category has no page an anonymous reader may open, so a card for it would
               be a link to the login form. One bulk call over the top-level children, the call
               subcategory_tree() makes for its own 'linkable'. */
            $public = category_discoverability::are_public(array_keys($children));
            foreach (array_keys($children) as $childid) {
                if (empty($public[$childid])) {
                    unset($children[$childid]);
                }
            }
            if (!$children) {
                return [];
            }
        }

        // One statement for every description the directory prints.
        $descriptions = $DB->get_records_list(
            'course_categories',
            'id',
            array_keys($children),
            '',
            'id, description, descriptionformat'
        );

        // One read of the branding for every card: the inherited colours are resolved together.
        $brandings = branding::for_categories(array_keys($children));
        $entries = [];
        foreach (array_keys($children) as $childid) {
            $entries[] = self::visual($scope, $childid, $descriptions[$childid] ?? null, true, $brandings[$childid]);
        }
        return $entries;
    }

    /**
     * The visual of one area's card: its colours, its still, its film, its crests, its watermark.
     *
     * The one builder of what a category looks like as a card: the root's directory and the
     * category page's posters both come through here, so a poster at the root and a poster inside
     * a category cannot drift apart. Everything is read from the cached branding payload, so it
     * costs no statement beyond the text filters of the description; the description and the
     * payload are handed in, because the callers read every description of their page in one
     * statement and every payload in one call ({@see branding::for_categories()}).
     *
     * The card's text is the category's own card text when it has one, and an excerpt of the core
     * description otherwise ({@see card_text()}); the hero keeps the description either way. The
     * colours are the resolved pair, the category's own or its nearest ancestor's.
     *
     * Without images - an anonymous visitor's merely listed child - there is no cover, no film,
     * no crest and no watermark: the public route refuses the files of a category that is not
     * public, so an address for them would be a broken picture. The colours stay.
     *
     * @param scope $scope The admitted subtree.
     * @param int $childid The area.
     * @param \stdClass|null $description A row carrying description and descriptionformat, or null for none.
     * @param bool $images Whether the card may carry the area's files at all.
     * @param array $branding The area's payload of {@see branding::for_categories()}.
     * @return array Keys id, name, url, description, hasdescription, style, imageurl, hasimage, hasvideo,
     *   videourl, videotype, badges, hasbadges, badgeslabel, hasmark and markfolder. The style is a
     *   closed vocabulary: the band and the ink through {@see branding::hex()}, and for a card with no
     *   still the watermark's opacity (two decimals) and its crest's address through
     *   {@see styleurl::safe()} ({@see mark()}).
     */
    private static function visual(
        scope $scope,
        int $childid,
        ?\stdClass $description,
        bool $images,
        array $branding
    ): array {
        $row = $scope->categories[$childid];
        $context = \core\context\coursecat::instance($childid);
        $imageurl = null;
        $videourl = null;
        $videotype = null;
        $badges = [];
        if ($images) {
            /* The card's still: the cover uploaded for the card, else the hero's background image,
               else none and the colour band alone. A 320x150 card and a page-wide band need
               different crops, hence a cover of its own; the hero image keeps a category without
               one branded. */
            if ($branding['cover'] !== null) {
                $imageurl = branding::cover_url($childid, $branding['cover'])->out(false);
            } else if ($branding['image'] !== null) {
                $imageurl = branding::image_url($childid, $branding['image'])->out(false);
            }
            /* The cover video, from the same payload. It is named only when the stored file's own
               bytes say it is one - branding::for_category() drops it otherwise - so a card never
               carries a <video> pointing at a route that would refuse it. There is no chain here
               and no fallback: a category either has a film of its own or the card is a still. */
            if ($branding['covervideo'] !== null) {
                $videourl = branding::covervideo_url($childid, $branding['covervideo'])->out(false);
                $videotype = (string) $branding['covervideo']['mimetype'];
            }
            /* The child's own crests, from the payload already read above: a card shows the
               category's badges exactly as its hero does, and never a course's, nor its parent's.
               Empty for a category nobody gave one. */
            $badges = branding::badges_for_output($childid, $branding['badges']);
        }
        /* A text-only poster (a visitor's merely listed child) withholds the description, and its
           card text with it: both say what the area holds, which is what its own page is for. */
        $excerpt = self::card_text($images ? (string) $branding['cardsummary'] : '', $description, $context);
        // The band and the ink are the editor's AA pair; each passes the one sanitiser on its way out.
        $band = branding::hex((string) $branding['bgcolour']) ?? branding::DEFAULT_BG;
        $ink = branding::hex((string) $branding['inkcolour']) ?? branding::DEFAULT_INK;
        $mark = ($images && $imageurl === null)
            ? self::mark($band, $ink, $badges)
            : ['style' => '', 'hasmark' => false, 'markfolder' => false];
        return [
            'id' => $childid,
            'name' => format_string($row->name, true, ['context' => $context, 'escape' => false]),
            'url' => links::showcase($childid, [], $scope->anonymous)->out(false),
            'description' => $excerpt,
            'hasdescription' => $excerpt !== '',
            'style' => '--dim-card-band: ' . $band . '; --dim-card-ink: ' . $ink . ';' . $mark['style'],
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== null,
            'hasvideo' => $videourl !== null,
            'videourl' => $videourl,
            'videotype' => $videotype,
            'badges' => $badges,
            'hasbadges' => !empty($badges),
            'badgeslabel' => get_string('category_badges', 'theme_dimensions'),
            'hasmark' => $mark['hasmark'],
            'markfolder' => $mark['markfolder'],
        ];
    }

    /**
     * The watermark of a card with no still: its opacity, and the crest it is cut from.
     *
     * The first crest, painted in the ink through a mask, sits under the name - so its opacity is
     * {@see mark_alpha()}'s, and a pair with no room for any mark gets none. The crest's address
     * reaches the stylesheet as a custom property in the card's style attribute, which is a CSS
     * context that Mustache's escaping does not protect: it is printed only through
     * {@see styleurl::safe()}. With no crest, or with one the guard refuses, the stylesheet draws
     * the theme's folder instead.
     *
     * @param string $band The band, already through {@see branding::hex()}.
     * @param string $ink The ink, already through {@see branding::hex()}.
     * @param array $badges The card's crests, as {@see branding::badges_for_output()} returns them.
     * @return array ['style' => string (the declarations to append, each with a leading space, or ''),
     *   'hasmark' => bool, 'markfolder' => bool].
     */
    public static function mark(string $band, string $ink, array $badges): array {
        $alpha = self::mark_alpha($band, $ink);
        if ($alpha <= 0) {
            return ['style' => '', 'hasmark' => false, 'markfolder' => false];
        }
        $style = ' --dim-mark-a: ' . sprintf('%.2f', $alpha) . ';';
        $crest = $badges ? styleurl::safe((string) $badges[0]['url']) : '';
        if ($crest === '') {
            return ['style' => $style, 'hasmark' => true, 'markfolder' => true];
        }
        return ['style' => $style . " --dim-mark: url('" . $crest . "');", 'hasmark' => true, 'markfolder' => false];
    }

    /**
     * The watermark's opacity for a band and its ink: the largest, from .20 down, the text survives.
     *
     * The name is the ink over the band with the ink laid over it again at this opacity, which is
     * the text's worst background; the chevron adds its own disc of the ink at
     * {@see CHEVRON_ALPHA} on top. So the opacity steps down by one hundredth until the name keeps
     * AA ({@see branding::FLOOR}) over the mark and the chevron keeps {@see CHEVRON_FLOOR} over the
     * mark and its disc, and a pair at the editor's own floor, which has no contrast to spare, gets
     * 0: no mark at all. The steps are whole hundredths, so no float error accumulates, and the
     * blend is {@see branding::mix()}, which fails closed on a colour it refuses. It mirrors
     * markAlpha() of the design prototype (the design's mockup).
     *
     * @param string $band The band colour.
     * @param string $ink The text colour.
     * @return float The opacity, 0 to .20 in hundredths.
     */
    public static function mark_alpha(string $band, string $ink): float {
        for ($hundredths = self::MARK_ALPHA; $hundredths > 0; $hundredths--) {
            $alpha = $hundredths / 100;
            $under = (string) branding::mix($band, $ink, $alpha);
            $disc = (string) branding::mix($band, $ink, 1 - (1 - $alpha) * (1 - self::CHEVRON_ALPHA));
            if (branding::contrast($ink, $under) >= branding::FLOOR && branding::contrast($ink, $disc) >= self::CHEVRON_FLOOR) {
                return $alpha;
            }
        }
        return 0.0;
    }

    /**
     * Cut the directory at {@see DIRECTORY_SLICE}: the open cards, and the rest for the reveal.
     *
     * A page cut, not a data cut. Every entry handed in was built by {@see directory()} in the
     * same statement-flat pass, so nothing here costs a read and nothing is fetched later: what
     * the cut decides is which cards a browser is asked to paint - and therefore which images it
     * fetches - before the reader opens the reveal. With {@see DIRECTORY_SLICE} entries or fewer
     * there is no reveal at all.
     *
     * The count is exported as a number rather than as a built string id: the reveal's summary is
     * chosen by a literal branch between two keys, singular and plural, because string ids are
     * never built from a value.
     *
     * @param array $entries What {@see directory()} returned, in order.
     * @return array ['directory' => array, 'directorymore' => array, 'hasmore' => bool, 'morecount' => int].
     */
    public static function directory_slice(array $entries): array {
        $open = array_slice($entries, 0, self::DIRECTORY_SLICE);
        $more = array_slice($entries, self::DIRECTORY_SLICE);
        return [
            'directory' => $open,
            'directorymore' => $more,
            'hasmore' => $more !== [],
            'morecount' => count($more),
        ];
    }

    /**
     * The posters of the grouped page: the accordion's entries, each with its area's visual.
     *
     * The same set, in the same order, as the accordion: every admitted direct child, empty ones
     * included for a logged-in viewer, and for a visitor the children holding a public course.
     * What a poster adds is how it looks ({@see visual()}) and what it does:
     *
     * - `istext` is an anonymous visitor's merely listed child: its colours and its name, and no
     *   file of its own, because the public route refuses them - see {@see visual()}.
     * - `expandable` says whether the poster opens a panel or is a link to the area's page
     *   ({@see expandable()}).
     *
     * One statement for every description, as {@see directory()} reads them, and nothing per
     * poster after it but the text filters of a described area (two reads each).
     *
     * @param scope $scope The admitted subtree.
     * @param array $accordion The entries of {@see accordion()}.
     * @param bool $filtered Whether anything narrows the page ({@see criteria::has_filters()}).
     * @return array One entry per accordion entry: {@see visual()}'s keys plus count, linkable, istext
     *   and expandable. The url is '' for a child with no page to open.
     */
    public static function posters(scope $scope, array $accordion, bool $filtered): array {
        global $DB;

        if (!$accordion) {
            return [];
        }
        // One statement for every description the posters print.
        $descriptions = $DB->get_records_list(
            'course_categories',
            'id',
            array_map(static fn(array $entry): int => (int) $entry['id'], $accordion),
            '',
            'id, description, descriptionformat'
        );
        // One read of the branding for every poster: the inherited colours are resolved together.
        $brandings = branding::for_categories(array_map(static fn(array $entry): int => (int) $entry['id'], $accordion));
        $posters = [];
        foreach ($accordion as $entry) {
            $areaid = (int) $entry['id'];
            $linkable = !empty($entry['linkable']);
            $istext = $scope->anonymous && !$linkable;
            $count = (int) $entry['count'];
            $visual = self::visual(
                $scope,
                $areaid,
                $istext ? null : ($descriptions[$areaid] ?? null),
                !$istext,
                $brandings[$areaid]
            );
            $posters[] = [
                'url' => $linkable ? $visual['url'] : '',
                'count' => $count,
                'linkable' => $linkable,
                'istext' => $istext,
                'expandable' => self::expandable($filtered, $count),
            ] + $visual;
        }
        return $posters;
    }

    /**
     * Whether a poster opens a panel or is a link to its area's page: the one spelling of the rule.
     *
     * A poster with no course is a link at rest, where there is nothing to open, and a panel under
     * a criterion, where "nothing matches here" is an answer worth giving. The page's
     * posters and the web service's accordion both ask this, so a filter the client applies in
     * place turns a poster into the same control the server would have drawn for that address.
     *
     * @param bool $filtered Whether anything narrows the page ({@see criteria::has_filters()}).
     * @param int $count The poster's post-filter subtree count.
     * @return bool
     */
    public static function expandable(bool $filtered, int $count): bool {
        return $filtered || $count > 0;
    }

    /**
     * The request a panel lists: the area's own listing, or - for a visitor's listed child - its slice of this one.
     *
     * Normally (a logged-in page, a visitor's public child, any poster of the root): the child's
     * own listing, through the child's own gate chain, in a flat order and over its whole
     * subtree, with the lean strip of its subareas. It carries what narrows the page and nothing
     * that organises it: the panel has its own order and its own size.
     *
     * For a visitor's merely listed child, which has no page a visitor may open: the parent's
     * listing narrowed to the child by the tree's own criterion. The anonymous shell admits that
     * slice already - {@see cat_ids()} asks only that the child be open in the parent's scope, and
     * a listed child is - so it returns exactly the public courses the parent's listing shows, and
     * nothing more. It has no strip: it is not the child's own scope.
     *
     * @param criteria $page The page's own request.
     * @param int $childid The area whose panel is listed.
     * @param bool $d20 Whether it is a visitor's merely listed child.
     * @return criteria
     */
    public static function panel_criteria(criteria $page, int $childid, bool $d20): criteria {
        $options = [
            'search' => $page->search,
            'state' => $page->state,
            'favourites' => $page->favourites,
            'fields' => $page->fields,
            'datefrom' => $page->datefrom,
            'dateto' => $page->dateto,
            'sort' => $page->psort === criteria::SORT_NAME ? criteria::SORT_NAME : criteria::SORT_RECENT,
            'pagesize' => self::PANEL_SIZE,
            'anonymous' => $page->anonymous,
        ];
        if ($d20) {
            $options['cat'] = $childid;
            // The parent's PUBLIC listing, whatever else the page is: the slice of a visitor's page.
            $options['anonymous'] = true;
            return new criteria($page->categoryid, $options);
        }
        $options['withcategories'] = true;
        return new criteria($childid, $options);
    }

    /**
     * Fill the posters of a grouped page, then admit its panel and resolve it.
     *
     * @param scope $scope The admitted subtree.
     * @param criteria $criteria The request; its panel is admitted or dropped on this object.
     * @param page_result $result The page being resolved.
     * @return void
     */
    private static function posters_and_panel(scope $scope, criteria $criteria, page_result $result): void {
        if ($criteria->withposters && self::partitions($criteria)) {
            $result->posters = self::posters($scope, $result->accordion, $criteria->has_filters());
        }
        self::open_panel($criteria, $result, $result->posters, false);
    }

    /**
     * Admit the request's open panel against the posters of this very page, and resolve it.
     *
     * The parameter is never an oracle. An open naming anything but an expandable poster this page
     * draws - a withheld child, a grandchild, a stale or foreign id, an empty poster at rest, any
     * id on a flat organisation - is set back to 0 on the request object, as the tree's criterion
     * is, so the pager, the forms and every address minted afterwards describe the page that was
     * really drawn, and the page is exactly the page the address without it draws. A refusal from
     * the panel's own resolve (a race between the two gate chains) gets the same silence.
     *
     * At most one panel is resolved per request, whatever the page holds: the others are the
     * client's, one request each, on open.
     *
     * @param criteria $criteria The request.
     * @param page_result $result The page being resolved.
     * @param array $candidates The page's posters ({@see posters()}), or the root's directory.
     * @param bool $isroot Whether the page is the root, whose every poster opens.
     * @return void
     */
    private static function open_panel(criteria $criteria, page_result $result, array $candidates, bool $isroot): void {
        $entry = null;
        if ($criteria->open > 0 && $criteria->withposters) {
            $bypage = array_column($candidates, null, 'id');
            $entry = $bypage[$criteria->open] ?? null;
            if ($entry !== null && !$isroot && empty($entry['expandable'])) {
                $entry = null;
            }
        }
        if ($entry !== null) {
            try {
                $result->panel = self::resolve(self::panel_criteria($criteria, (int) $entry['id'], !empty($entry['istext'])));
                $result->panelentry = $entry;
                return;
            } catch (unavailable_exception) {
                // A race or a disagreement between the two chains: the page renders without a panel.
                $result->panel = null;
            }
        }
        $criteria->open = 0;
        $criteria->psort = '';
    }

    /**
     * The lean strip of a panel: the listed category's admitted direct children, a name, a link and a dot each.
     *
     * What a panel draws above its rail ("Also in ..."): a pill with a name and a colour, so no
     * directory card is built for it. Everything here is in hand: the entries are the depth-1 nodes
     * of {@see subcategory_tree()}, already admitted and carrying `linkable`, and the colour is the
     * cached branding payload's, through the sanitiser. So it costs no statement, however many
     * subareas there are.
     *
     * Under a criterion the strip offers only the subareas holding a result, and a linked pill
     * carries the criterion, so it opens onto the same question and never onto an empty page.
     * Which subareas hold one is the roll-up of the panel request's own post-filter tally
     * ({@see rollup()}), free in the PHP cut and one grouped statement in the SQL cut: both modes
     * build the strip from the same numbers. At rest every admitted subarea is offered, without
     * any parameter.
     *
     * @param scope $scope The admitted subtree, rooted at the panel's area.
     * @param array $tree The navigation tree of {@see subcategory_tree()}.
     * @param criteria $criteria The panel's request.
     * @param array $tally categoryid => how many courses of the request sit directly in it; read
     *   under a criterion only.
     * @return array Entries of id, name, depth, linkable, url ('' with no page to open) and style
     *   ('--dim-dot: #rrggbb;', the subarea's own band).
     */
    private static function subareas(scope $scope, array $tree, criteria $criteria, array $tally): array {
        $filtered = $criteria->has_filters();
        $totals = $filtered ? self::rollup($scope, $tree, $tally) : [];
        $params = $filtered ? $criteria->narrowing_params() : [];
        // One read of the branding for every pill: the inherited colours are resolved together.
        $depthone = array_filter($tree, static fn(array $node): bool => (int) $node['depth'] === 1);
        $colours = branding::for_categories(array_map(static fn(array $node): int => (int) $node['id'], $depthone));
        $strip = [];
        foreach ($tree as $node) {
            if ((int) $node['depth'] !== 1) {
                continue;
            }
            $subid = (int) $node['id'];
            if ($filtered && ($totals[$subid] ?? 0) === 0) {
                continue;
            }
            $linkable = !empty($node['linkable']);
            $colour = $colours[$subid];
            $strip[] = [
                'id' => $subid,
                'name' => $node['name'],
                'depth' => 1,
                'linkable' => $linkable,
                'url' => $linkable ? links::showcase($subid, $params, $scope->anonymous)->out(false) : '',
                'style' => '--dim-dot: ' . (branding::hex((string) $colour['bgcolour']) ?? branding::DEFAULT_BG) . ';',
            ];
        }
        return $strip;
    }

    /**
     * The text of a category's card: its own card text when it has one, else an excerpt of its description.
     *
     * The card text is plain and short by construction ({@see branding::CARDSUMMARY_MAXLENGTH},
     * the length the excerpt is cut at), and it is returned in the PLAIN spelling: format_string()
     * with escape off runs the filters (multilang) and strips any markup, and the card template's
     * double stash escapes it exactly once. The description's excerpt is already plain text.
     *
     * @param string $cardsummary The category's own card text, '' for none.
     * @param \stdClass|null $description A row carrying description and descriptionformat, or null.
     * @param \core\context $context The category's context.
     * @return string The card's text, plain, '' when there is none.
     */
    private static function card_text(string $cardsummary, ?\stdClass $description, \core\context $context): string {
        if (trim($cardsummary) !== '') {
            return trim(format_string($cardsummary, true, ['context' => $context, 'escape' => false]));
        }
        return self::excerpt($description, $context);
    }

    /**
     * A category description as a short plain line, formatted the way the hero formats its own.
     *
     * @param \stdClass|null $row A row carrying description and descriptionformat, or null.
     * @param \core\context $context The category's context.
     * @return string The excerpt, '' when there is none.
     */
    private static function excerpt(?\stdClass $row, \core\context $context): string {
        if ($row === null || trim((string) $row->description) === '') {
            return '';
        }
        $html = format_text((string) $row->description, (int) $row->descriptionformat, ['context' => $context]);
        return shorten_text(trim(content_to_text($html, FORMAT_HTML)), self::EXCERPT_LENGTH);
    }

    /**
     * Run the plugin's own anonymous predicate over a page of rows the statement already admitted.
     *
     * Belt and braces, bounded by the page: {@see access::filter_courses_public()}
     * asks {@see discoverability::are_public()} about the ids it is handed, so it
     * is never given the population (that would be a population-sized IN list)
     * but always the page, and a row the statement admitted that the plugin
     * refuses is dropped. That can only happen when this class's predicate and
     * the plugin's have drifted apart, and then rendering short is the right
     * failure. No test can see this replay while the two predicates agree,
     * because the statement already withholds the same rows.
     *
     * @param \stdClass[] $rows The page slice, keyed by course id.
     * @return \stdClass[] The rows the plugin admits, same keys, same order.
     */
    private static function replay_public(array $rows): array {
        if (!$rows) {
            return $rows;
        }
        $admitted = [];
        foreach (access::filter_courses_public($rows) as $courseid => $row) {
            $admitted[(int) $courseid] = $row;
        }
        return $admitted;
    }

    /**
     * The course contexts of these rows, rebuilt from the preload columns they carry: zero statements.
     *
     * @param \stdClass[] $rows Rows from {@see records()} or a page_result.
     * @return \core\context\course[] courseid => context.
     */
    public static function page_contexts(array $rows): array {
        $contexts = [];
        foreach ($rows as $row) {
            $contexts[(int) $row->id] = self::course_context($row);
        }
        return $contexts;
    }

    /**
     * The context of one population row, preloaded from a clone so the row keeps its columns.
     *
     * @param \stdClass $row A row carrying the ctx preload columns.
     * @return \core\context\course
     */
    private static function course_context(\stdClass $row): \core\context\course {
        context_helper::preload_from_record(clone $row);
        return \core\context\course::instance((int) $row->id);
    }

    /**
     * The predicate matching the search term against three columns, with three distinct placeholders.
     *
     * A named placeholder may appear only once per statement - fix_sql_params()
     * counts occurrences - so the same escaped value is bound three times
     * under three names. Neither engine indexes a "contains" LIKE; the
     * category predicate beside it does the row-count reduction.
     *
     * @param string $term The normalised term.
     * @return array The SQL fragment at index 0 and its parameters at index 1.
     */
    public static function search_sql(string $term): array {
        global $DB;

        $like = '%' . $DB->sql_like_escape($term) . '%';
        $sql = '(' . $DB->sql_like('c.fullname', ':term1', false, false)
            . ' OR ' . $DB->sql_like('c.shortname', ':term2', false, false)
            . ' OR ' . $DB->sql_like('c.idnumber', ':term3', false, false) . ')';
        return [$sql, ['term1' => $like, 'term2' => $like, 'term3' => $like]];
    }

    /**
     * The predicate demanding one stored option per requested field, as correlated EXISTS clauses.
     *
     * @param array $filters fieldid => option value.
     * @return array The SQL fragment (possibly empty) at index 0 and its parameters at index 1.
     */
    public static function fields_sql(array $filters): array {
        $clauses = [];
        $params = [];
        $n = 0;
        foreach ($filters as $fieldid => $value) {
            $n++;
            $clauses[] = "EXISTS (SELECT 1 FROM {customfield_data} fd{$n}
                                   WHERE fd{$n}.instanceid = c.id AND fd{$n}.fieldid = :ff{$n} AND fd{$n}.intvalue = :fv{$n}
                                         AND fd{$n}.component = 'core_course' AND fd{$n}.area = 'course' AND fd{$n}.itemid = 0)";
            $params["ff{$n}"] = (int) $fieldid;
            $params["fv{$n}"] = (int) $value;
        }
        return [$clauses ? implode(' AND ', $clauses) : '', $params];
    }

    /**
     * The ids on a category path, root first.
     *
     * @param string $path The course_categories.path value.
     * @return int[]
     */
    private static function path_ids(string $path): array {
        return array_map('intval', array_filter(explode('/', $path), 'strlen'));
    }

    /**
     * Resolve the page with the state sort off: cut in SQL, rows O(P), core order alone.
     *
     * The predicate keeps the same two gates by carrying bounded exception
     * lists instead of gating rows in PHP: the hidden courses this viewer may
     * see (a term every branch is under, so a related course inside a clamped
     * category is still withheld while hidden), the unlisted courses the
     * plugin admitted after the capped probe, and the viewer's own courses
     * inside clamped categories. Each list is cut
     * at {@see RESCUE_CAP} and what falls past the cap is withheld - fail
     * closed. What this mode gives up: the state grouping, the state chips
     * (a state chip on the request is dropped by {@see resolve()}), the hero's per-state counts
     * (the result carries 'total' only) and, for the facets, the per-group
     * exclusion of its own filter. The classifier still runs, over the page
     * slice, so the cards keep their badges.
     *
     * @param scope $scope The admitted subtree.
     * @param criteria $criteria The request.
     * @return page_result
     */
    private static function resolve_sql(scope $scope, criteria $criteria): page_result {
        global $DB;

        $result = new page_result();
        $result->page = $criteria->page;
        $result->pagesize = self::page_size($criteria->pagesize);
        $result->anonymous = $scope->anonymous;
        $result->statesort = false;

        [$where, $params, $join] = self::sql_predicate($scope, $criteria);
        /* The subtree's own number, which the hero prints and the "all" pill counts. It is not
           the number of rows this page draws once the partition applies, and the two are kept
           apart here rather than at the reader's end: switching the organisation must not change
           what the page says is in the category. */
        $subtotal = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {course} c {$join} WHERE {$where}",
            $params
        );

        /* One grouped statement, whoever needs it, taken before the page's own rows so the
           partition below can read the direct number straight out of it. This mode materialises
           no population, so the anonymous tree's "holds a public course somewhere" rule, the
           accordion's per-child numbers and the partition's own total are questions about rows
           nobody holds; a tally of one row per category answers all three. A logged-in viewer of
           a flat organisation needs none of them and pays nothing - except a panel's request
           under a criterion, whose strip offers only the subareas holding a result. */
        $tally = [];
        if ($scope->anonymous || self::partitions($criteria) || ($criteria->withcategories && $criteria->has_filters())) {
            $tally = $DB->get_records_sql_menu(
                "SELECT c.category, COUNT(1) FROM {course} c {$join} WHERE {$where} GROUP BY c.category",
                $params
            );
        }

        /* The partition: the page's own rows are the direct courses, and its total is the tally's
           entry for this category - no second COUNT, because the grouped statement above already
           answered it. One placeholder, used once in this statement. */
        $rowwhere = $where;
        $rowparams = $params;
        if (self::partitions($criteria)) {
            $rowwhere .= ' AND c.category = :pagecat';
            $rowparams['pagecat'] = $scope->rootid;
            $result->total = (int) ($tally[$scope->rootid] ?? 0);
        } else {
            $result->total = $subtotal;
        }
        $result->counts = ['total' => $subtotal];
        $result->page = self::clamp_page($result->page, $result->total, $result->pagesize);
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $state = discoverability::state_sql('c');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, c.fullname, c.startdate,
                    {$state['column']} AS liststate, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
                    {$join}
              WHERE {$rowwhere}
           ORDER BY " . self::order_sql($criteria),
            $rowparams + ['contextcourse' => CONTEXT_COURSE],
            $result->page * $result->pagesize,
            $result->pagesize
        );
        $result->hasmore = ($result->page + 1) * $result->pagesize < $result->total;

        if ($scope->anonymous) {
            $rows = self::replay_public($rows);
        } else {
            $verdicts = classifier::classify(array_keys($rows), $scope);
            foreach ($rows as $courseid => $row) {
                $row->state = $verdicts[(int) $courseid]['state'];
                $row->favourite = $verdicts[(int) $courseid]['favourite'];
                $row->startsat = $verdicts[(int) $courseid]['startsat'];
                $row->endsat = $verdicts[(int) $courseid]['endsat'];
                $row->prerequisiteid = $verdicts[(int) $courseid]['prerequisiteid'];
                $row->opening = $verdicts[(int) $courseid]['opening'];
            }
        }
        foreach ($rows as $courseid => $row) {
            $result->rows[(int) $courseid] = $row;
        }

        $fields = facets::fields();
        if ($fields) {
            $result->facets = facets::groups_sql($fields, $where, $params, $join);
        }
        /* A visitor's tree names a category only when it holds a public course somewhere, a
           question about the population rather than about the page on screen: handed the page
           slice, it would name only the categories whose courses fit the page being viewed. The
           PHP-cut mode hands the whole gated population over; this mode materialises none, so
           the tally taken above stands in for it. */
        $population = $scope->anonymous ? self::populated_rows($tally) : $result->rows;
        // Before the page's own tree, so the category states it reads are already memoised.
        $resttree = self::tree_at_rest($scope, $criteria);
        // A flat page's tree is a filter, so a visitor's names what it names at rest (tree_at_rest()).
        $result->subcategories = $resttree !== null && !self::partitions($criteria)
            ? $resttree
            : self::subcategory_tree($scope, $population);
        if (self::partitions($criteria)) {
            $result->accordion = self::accordion($scope, $resttree ?? $result->subcategories, $tally);
        }
        if ($criteria->withcategories) {
            $result->subareas = self::subareas($scope, $result->subcategories, $criteria, $tally);
        }
        $result->categorynames = self::category_names($scope, $result->rows);
        self::posters_and_panel($scope, $criteria, $result);
        return $result;
    }

    /**
     * Rows standing in for a grouped tally, so a population can be described without materialising one.
     *
     * {@see populated_ancestors()} reads one column of each row, the category
     * it belongs to, so a tally of one row per category answers it exactly as
     * the population itself would.
     *
     * @param array $tally categoryid => how many courses of the population sit in it.
     * @return \stdClass[] One row per category holding at least one course.
     */
    private static function populated_rows(array $tally): array {
        $rows = [];
        foreach (array_keys($tally) as $categoryid) {
            $rows[] = (object) ['category' => (int) $categoryid];
        }
        return $rows;
    }

    /**
     * The SQL-cut predicate: both gates as bounded lists, plus every narrowing criterion of the request.
     *
     * @param scope $scope The admitted subtree.
     * @param criteria $criteria The request.
     * @return array The WHERE fragment at 0, its parameters at 1 and the joins the fragment needs at 2.
     */
    private static function sql_predicate(scope $scope, criteria $criteria): array {
        global $DB;

        $state = discoverability::state_sql('c');
        $params = ['siteid' => SITEID];
        $open = $scope->open ?: [-1];
        [$catsql, $catparams] = $DB->get_in_or_equal($open, SQL_PARAMS_NAMED, 'cat');
        $params += $catparams;

        if ($scope->anonymous) {
            $where = "c.category {$catsql} AND c.visible = 1 AND {$state['column']} = :publicstate";
            $params['publicstate'] = discoverability::STATE_PUBLIC;
        } else {
            /* Both lists read the matches of the request's term: capped at fifty in core order, a
               list built over the whole subtree would leave out a match past the fiftieth unlisted
               or hidden course of the subtree, which the PHP cut, whose probe runs over the
               matches, keeps. population_test holds the two modes to the same count. */
            $rescued = self::rescued_hidden($scope, $criteria->search);
            $admitted = self::admitted_unlisted($scope, $criteria->search);
            $related = self::related_in_clamped($scope);
            [$rsql, $rparams] = $DB->get_in_or_equal($rescued ?: [-1], SQL_PARAMS_NAMED, 'rsc');
            [$usql, $uparams] = $DB->get_in_or_equal($admitted ?: [-1], SQL_PARAMS_NAMED, 'unl');
            [$lsql, $lparams] = $DB->get_in_or_equal($related ?: [-1], SQL_PARAMS_NAMED, 'rel');
            // Core's visibility governs both branches: a related course inside a clamped category is
            // still a hidden course unless the viewer may see hidden courses.
            $where = "((c.category {$catsql} AND ({$state['column']} <> :unlistedstate OR c.id {$usql}))"
                . " OR c.id {$lsql})"
                . " AND (c.visible = 1 OR c.id {$rsql})";
            $params += $rparams + $uparams + $lparams + ['unlistedstate' => discoverability::STATE_UNLISTED];
        }
        $where .= ' AND c.id <> :siteid';

        if ($criteria->search !== '') {
            [$searchsql, $searchparams] = self::search_sql($criteria->search);
            $where .= " AND {$searchsql}";
            $params += $searchparams;
        }
        [$fieldsql, $fieldparams] = self::fields_sql($criteria->fields);
        if ($fieldsql !== '') {
            $where .= " AND {$fieldsql}";
            $params += $fieldparams;
        }
        [$datesql, $dateparams] = self::date_sql($criteria);
        if ($datesql !== '') {
            $where .= " AND {$datesql}";
            $params += $dateparams;
        }
        [$catsql, $catparams] = self::cat_sql($scope, $criteria);
        if ($catsql !== '') {
            $where .= " AND {$catsql}";
            $params += $catparams;
        }
        return [$where, $params, $state['join']];
    }

    /**
     * The hidden courses of the population's categories this viewer may nonetheless see, capped.
     *
     * One statement over visible = 0 (bounded by the hidden count, not by the
     * population), then per row viewhiddencourses at the preloaded context (an
     * enrolment does not rescue a hidden course, as in core). Clamped
     * categories are scanned too, because the relationship rescue of the clamps
     * admits a course only if this list also does. Past the cap the remainder
     * is withheld.
     *
     * Under a search it scans the matches alone, so the cap spans the courses the request can
     * draw rather than the first hidden courses of the subtree: the PHP cut asks of every hidden
     * match, and a list cut over the whole subtree would withhold a match the other mode shows.
     * The cap itself still differs from the PHP cut, which has none: past fifty hidden matches
     * this viewer may see, the SQL cut shows fewer.
     *
     * @param scope $scope The admitted subtree.
     * @param string $term The request's normalised search term, '' for none.
     * @return int[]
     */
    private static function rescued_hidden(scope $scope, string $term = ''): array {
        global $DB;

        $categories = $scope->population_categories();
        if (!$categories) {
            return [];
        }
        [$catsql, $params] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'hid');
        $where = "c.category {$catsql} AND c.visible = 0 AND c.id <> :siteid";
        if ($term !== '') {
            [$searchsql, $searchparams] = self::search_sql($term);
            $where .= " AND {$searchsql}";
            $params += $searchparams;
        }
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
              WHERE {$where}
           ORDER BY c.sortorder ASC, c.id ASC",
            $params + ['contextcourse' => CONTEXT_COURSE, 'siteid' => SITEID]
        );
        $rescued = [];
        foreach ($rows as $courseid => $row) {
            if (has_capability('moodle/course:viewhiddencourses', self::course_context($row))) {
                $rescued[] = (int) $courseid;
                if (count($rescued) >= self::RESCUE_CAP) {
                    break;
                }
            }
        }
        return $rescued;
    }

    /**
     * The unlisted courses of the open categories the plugin admits for this viewer, after the capped probe.
     *
     * Under a search it probes the matches alone, in core order - the window the PHP cut's probe
     * is ({@see gate()}, over {@see records()}, which already carries the term). Built over the
     * whole subtree, it would ask about the first fifty unrelated unlisted courses whatever they
     * were called and withhold a match past them that the PHP cut draws.
     *
     * @param scope $scope The admitted subtree.
     * @param string $term The request's normalised search term, '' for none.
     * @return int[]
     */
    private static function admitted_unlisted(scope $scope, string $term = ''): array {
        global $DB;

        if (!$scope->open) {
            return [];
        }
        [$catsql, $params] = $DB->get_in_or_equal($scope->open, SQL_PARAMS_NAMED, 'unc');
        $state = discoverability::state_sql('c');
        $where = "c.category {$catsql} AND {$state['column']} = :unlistedstate AND c.id <> :siteid";
        if ($term !== '') {
            [$searchsql, $searchparams] = self::search_sql($term);
            $where .= " AND {$searchsql}";
            $params += $searchparams;
        }
        $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.category, c.sortorder, c.visible, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
                    {$state['join']}
              WHERE {$where}
           ORDER BY c.sortorder ASC, c.id ASC",
            $params + [
                'contextcourse' => CONTEXT_COURSE,
                'siteid' => SITEID,
                'unlistedstate' => discoverability::STATE_UNLISTED,
            ]
        );
        $related = [];
        $unrelated = [];
        foreach ($rows as $courseid => $row) {
            $mine = $scope->enrolments[(int) $courseid] ?? null;
            if ($mine && ($mine->active || $mine->inactive)) {
                $related[(int) $courseid] = $row;
            } else {
                $unrelated[(int) $courseid] = $row;
            }
        }
        $probe = $related + array_slice($unrelated, 0, self::PROBE_CAP, true);
        // The plugin reads each probed course's context: in the cache first, as gate() puts them (no statement each).
        foreach ($probe as $row) {
            context_helper::preload_from_record(clone $row);
        }
        $admitted = array_map('intval', array_keys(access::filter_courses($probe)));
        return array_slice($admitted, 0, self::RESCUE_CAP);
    }

    /**
     * The viewer's own courses inside clamped categories, capped: the rescue the clamps allow.
     *
     * @param scope $scope The admitted subtree.
     * @return int[]
     */
    private static function related_in_clamped(scope $scope): array {
        $related = [];
        foreach ($scope->enrolments as $courseid => $mine) {
            $clamp = $scope->clamps[$mine->category] ?? 0;
            if ($clamp === 0) {
                continue;
            }
            if ($mine->active || (($clamp & scope::CLAMP_PLUGIN) && !($clamp & scope::CLAMP_CORE) && $mine->inactive)) {
                $related[] = (int) $courseid;
            }
        }
        return array_slice($related, 0, self::RESCUE_CAP);
    }
}
