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
 * Theme Dimensions - The panel of courses drawn between the rows of posters
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\page_result;
use theme_dimensions\local\category\population;
use theme_dimensions\local\hotsite\links;

/**
 * The context of one area's panel: its header, the strip of its subareas, its rail and its footer.
 *
 * One builder for the server and the client. The page draws the open panel itself for an
 * address carrying `open`, which is how a reader without JavaScript - every anonymous visitor
 * among them - opens one; the client asks the listing's web service for the area and renders the
 * same template from {@see export_for_ws()}, which is this context without its cards (the service
 * returns those once, at the top level). So the two can only differ where they are meant to: the
 * no-JavaScript addresses (the order links and Close) are empty in the web service's copy,
 * because the client draws buttons there and the service does not know the page the panel sits
 * on.
 *
 * Every string that carries a number is composed here, with the number formatted by
 * {@see number()}, and every string that carries only the area's name is a string helper of the
 * template with the name as its parameter, which renders identically on both sides: the client
 * formats no number and composes no sentence.
 *
 * It is built from primitives because its two callers hold different things: the page has the
 * poster entry and its own request; the web service has the area and the panel request, whose
 * narrowing parameters are the page's because the client sent them.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_panel implements \core\output\named_templatable, \core\output\renderable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var int How many subareas the strip names before its "+N". */
    public const STRIP_SIZE = 6;

    /** @var int The most courses a rail holds and still counts as "few": laid out, not scrolled. */
    public const FEW = 3;

    /** @var string The fragment of the panel, where an order link lands. */
    public const ANCHOR_PANEL = 'dim-panel';

    /** @var string The fragment prefix of a poster, where Close and the open poster land. */
    public const ANCHOR_AREA = 'dim-area-';

    /**
     * @var string The fragment of the "nothing in N areas" summary, where Close lands for a zeroed area.
     *
     * Under a criterion an area with no result leaves the grid once its panel closes, so its own
     * fragment would land on nothing; the summary it collapses into is where it went.
     */
    public const ANCHOR_NOMATCH = 'dim-nomatch';

    /**
     * Constructor.
     *
     * @param int $areaid The area whose courses the panel lists.
     * @param string $name Its name, plain: every sink escapes it.
     * @param string $titleurl The area's own page, '' where there is none to open (a visitor's merely listed child).
     * @param criteria $criteria The request whose narrowing parameters the call to action carries.
     * @param string $psort The panel's order: '' (the newest first) or criteria::SORT_NAME.
     * @param page_result $result The panel's own listing.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @param bool $isd20 Whether the area is a visitor's merely listed child, listed as a slice of its parent.
     * @param int $pageid The page's own category (the parent an $isd20 slice is cut from; 0 at the root).
     * @param array|null $pageparams The page's own address parameters, open and psort excluded, or
     *   null where the panel is not drawn by the page (the web service): its no-JavaScript
     *   addresses are then empty.
     * @param array $siblings The page's posters, whose other areas with a result the zero state
     *   suggests; empty where the panel is not drawn by the page.
     * @param bool $zeroed Whether the area is a poster the page's criterion zeroed, whose Close
     *   lands on the summary it collapses into.
     */
    public function __construct(
        /** @var int The area. */
        private readonly int $areaid,
        /** @var string The area's plain name. */
        private readonly string $name,
        /** @var string The area's own page, or ''. */
        private readonly string $titleurl,
        /** @var criteria The request carrying the narrowing parameters. */
        private readonly criteria $criteria,
        /** @var string The panel's order. */
        private readonly string $psort,
        /** @var page_result The panel's listing. */
        private readonly page_result $result,
        /** @var bool Whether the anonymous shell is drawing. */
        private readonly bool $anonymous,
        /** @var bool Whether the area is a visitor's merely listed child, a slice of its parent. */
        private readonly bool $isd20 = false,
        /** @var int The page's own category. */
        private readonly int $pageid = 0,
        /** @var array|null The page's own address parameters, or null. */
        private readonly ?array $pageparams = null,
        /** @var array The page's posters, for the zero state's suggestions. */
        private readonly array $siblings = [],
        /** @var bool Whether the page's criterion zeroed the area. */
        private readonly bool $zeroed = false,
    ) {
    }

    /**
     * The panel the page draws for its open poster.
     *
     * @param array $entry The open poster ({@see population::posters()}, or a root directory entry).
     * @param criteria $page The page's request, its panel already admitted.
     * @param page_result $panel The panel's listing, resolved by the population.
     * @param int $pageid The page's own category, 0 at the root.
     * @param bool $isroot Whether the page is the root.
     * @param array $pageparams The page's own address parameters ({@see page_params()}).
     * @param array $siblings The page's posters ({@see population::posters()}): the zero state
     *   suggests the other ones holding a result.
     * @return self
     */
    public static function for_page(
        array $entry,
        criteria $page,
        page_result $panel,
        int $pageid,
        bool $isroot,
        array $pageparams,
        array $siblings = []
    ): self {
        $isd20 = !$isroot && !empty($entry['istext']);
        // The root under a search is in result mode too: its entries carry their matches.
        $zeroed = $page->has_filters() && (int) ($entry['count'] ?? 0) === 0;
        return new self(
            (int) $entry['id'],
            (string) $entry['name'],
            $isd20 ? '' : (string) $entry['url'],
            $page,
            $page->psort,
            $panel,
            $page->anonymous,
            $isd20,
            $pageid,
            $pageparams,
            $siblings,
            $zeroed
        );
    }

    /**
     * The page's own address parameters, without the panel's two.
     *
     * What every no-JavaScript address of a panel starts from - the order links, Close, and the
     * posters' own links - so that none of them loses the reader's filters, organisation, page
     * size or page of the direct courses while opening, reordering or closing a panel.
     *
     * @param criteria $page The page's request.
     * @param int|null $pagenumber The page of the direct courses being drawn, or null for none.
     * @return array name => value.
     */
    public static function page_params(criteria $page, ?int $pagenumber): array {
        $params = $page->query_params();
        unset($params[criteria::PARAM_OPEN], $params[criteria::PARAM_PSORT]);
        if ($pagenumber !== null && $pagenumber > 0) {
            $params[criteria::PARAM_PAGE] = $pagenumber;
        }
        return $params;
    }

    /**
     * One address of the page that draws the panel, with a fragment: what a no-JavaScript control links.
     *
     * @param int $pageid The page's own category, 0 at the root.
     * @param array $params The query parameters.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @param string $anchor The fragment, without the hash.
     * @return string The address.
     */
    public static function page_address(int $pageid, array $params, bool $anonymous, string $anchor): string {
        $url = links::showcase($pageid, $params, $anonymous);
        $url->set_anchor($anchor);
        return $url->out(false);
    }

    /**
     * What the client needs of a poster BEFORE the panel's rows arrive: where its title and its call to action go.
     *
     * Written on every poster by the page and handed back for every accordion entry by the web
     * service after a filter change, so the skeleton and the error state can draw a working
     * footer and the client composes nothing. The call to action carries the request's narrowing
     * parameters and the newest-first order (the client swaps the one `sort` parameter when the
     * panel is in A-Z order, in the server's own vocabulary), and its label carries the POSTER's
     * count - except at the root at rest, which counts nothing. A visitor's merely listed child has no
     * page of its own, so its call to action is the "see all as a list" address instead.
     *
     * Under a criterion the poster also wears its number as a pill ("5 results"), and this is the
     * one builder of those words, for the page and for the web service alike; the call to action
     * counts results rather than courses then, because before the rows arrive the number it shows
     * is the pill's. At rest the pill is empty; the root is in result mode under a search, the one
     * criterion it reads.
     *
     * @param array $entry The poster or accordion entry: id, name, url, and count and linkable where known.
     * @param criteria $page The request.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @param bool $isroot Whether the page is the root.
     * @return array ['titleurl' => string, 'ctaurl' => string, 'ctalabel' => string, 'resultlabel' => string
     *   (the pill, '' at rest), 'resultcount' => string (its number alone, formatted, '' at rest)].
     */
    public static function shell(array $entry, criteria $page, bool $anonymous, bool $isroot): array {
        $areaid = (int) $entry['id'];
        $count = (int) ($entry['count'] ?? 0);
        // At the root the one criterion is the search, and under it the root counts.
        $filtered = $page->has_filters();
        $results = [
            'resultlabel' => $filtered ? self::results_label($count) : '',
            'resultcount' => $filtered ? self::number($count) : '',
        ];
        if (!$isroot && empty($entry['linkable'])) {
            return [
                'titleurl' => '',
                'ctaurl' => self::seeaslist_url($page->categoryid, $areaid, $page, '', $anonymous),
                'ctalabel' => get_string('category_seeaslist', self::COMPONENT),
            ] + $results;
        }
        return [
            'titleurl' => (string) $entry['url'],
            'ctaurl' => self::cta_url($areaid, $page, '', $anonymous),
            'ctalabel' => self::cta_label((string) $entry['name'], $isroot && !$filtered ? null : $count, $filtered),
        ] + $results;
    }

    /**
     * A poster's pill under a criterion: how many results its area holds.
     *
     * @param int $count The area's post-filter count.
     * @return string "5 results", "1 result", "0 results".
     */
    public static function results_label(int $count): string {
        if ($count === 1) {
            return get_string('category_results_one', self::COMPONENT);
        }
        return get_string('category_results', self::COMPONENT, self::number($count));
    }

    /**
     * A number as a sentence prints it, with the thousands separator of the reader's language.
     *
     * core's format_float() applies no thousands separator, so "2392" would read as a year; the
     * separator is the language pack's own ("2.392" in Portuguese, "2,392" in English).
     *
     * @param int $number The number.
     * @return string
     */
    public static function number(int $number): string {
        return number_format($number, 0, '', get_string('thousandssep', 'langconfig'));
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'theme_dimensions/category_panel';
    }

    /**
     * The server's context: the whole panel, its cards included, and the zero state's suggestions.
     *
     * The suggestions are PAGE data, like the cards: the page's other posters holding a result,
     * which the web service's copy does not know. The client builds the same list from the posters
     * it holds and merges it into the service's context, as it does the cards.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return array The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): array {
        $context = $this->context();
        $context['cards'] = category_listing::export_cards(
            $this->result,
            $this->isd20 ? $this->pageid : $this->areaid,
            true,
            $output
        );
        $context['suggestions'] = $context['iszero'] ? $this->suggestions() : [];
        $context['hassuggestions'] = $context['suggestions'] !== [];
        return $context;
    }

    /**
     * The zero state's way on: the page's other areas holding a result, in the posters' order.
     *
     * Without JavaScript each is an address of this page with that area's panel open, landing on
     * the panel; the client draws buttons from the same entries. The count travels twice: the digit
     * the chip shows, and the pill's words, which are what a screen reader is read in its place.
     *
     * @return array Entries of ['id' => int, 'name' => plain string, 'count' => formatted string,
     *   'countlabel' => plain string ("3 results"), 'url' => string]; empty where the panel is not
     *   drawn by the page.
     */
    private function suggestions(): array {
        if ($this->pageparams === null) {
            return [];
        }
        $suggestions = [];
        foreach ($this->siblings as $sibling) {
            $id = (int) $sibling['id'];
            $count = (int) ($sibling['count'] ?? 0);
            if ($id === $this->areaid || $count <= 0) {
                continue;
            }
            $suggestions[] = [
                'id' => $id,
                'name' => (string) $sibling['name'],
                'count' => self::number($count),
                'countlabel' => self::results_label($count),
                'url' => self::page_address(
                    $this->pageid,
                    $this->pageparams + [criteria::PARAM_OPEN => $id],
                    $this->anonymous,
                    self::ANCHOR_PANEL
                ),
            ];
        }
        return $suggestions;
    }

    /**
     * The web service's copy: the same context without its cards, which the service returns at the top level.
     *
     * @return array Exactly the keys {@see \theme_dimensions\external\get_category_rows::panel_structure()} declares.
     */
    public function export_for_ws(): array {
        return $this->context();
    }

    /**
     * Everything but the cards.
     *
     * @return array
     */
    private function context(): array {
        $total = $this->result->total;
        $shown = count($this->result->rows);
        $filtered = $this->criteria->narrowing_params() !== [];
        $hasrows = $shown > 0;
        $ctaurl = $this->isd20 ? '' : self::cta_url($this->areaid, $this->criteria, $this->psort, $this->anonymous);
        $extra = max(0, $total - $shown);
        if ($filtered) {
            $tilelabel = $extra === 1 ? 'category_panel_moretileresults_one' : 'category_panel_moretileresults';
        } else {
            $tilelabel = $extra === 1 ? 'category_panel_moretile_one' : 'category_panel_moretile';
        }
        return [
            'id' => $this->areaid,
            'name' => $this->name,
            'titleurl' => $this->titleurl,
            'haslink' => $this->titleurl !== '',
            'isd20' => $this->isd20,
            'subtitle' => $hasrows ? $this->subtitle($total, $shown, $filtered) : '',
            'psortname' => $this->psort === criteria::SORT_NAME,
            'sorturlrecent' => $this->nojs([criteria::PARAM_OPEN => $this->areaid], self::ANCHOR_PANEL),
            'sorturlname' => $this->nojs(
                [criteria::PARAM_OPEN => $this->areaid, criteria::PARAM_PSORT => criteria::SORT_NAME],
                self::ANCHOR_PANEL
            ),
            'closeurl' => $this->nojs([], $this->zeroed ? self::ANCHOR_NOMATCH : self::ANCHOR_AREA . $this->areaid),
            'also' => $this->also(),
            'hascards' => $hasrows,
            'few' => $hasrows && $shown <= self::FEW,
            // Where the rail is scrolled to, for a phone; the client moves it and never formats it.
            'counter' => ($hasrows && $shown > self::FEW) ? '1 / ' . self::number($shown) : '',
            'tile' => [
                'has' => !$this->isd20 && $hasrows && $extra > 0,
                'count' => self::number($extra),
                'label' => get_string($tilelabel, self::COMPONENT),
                'url' => $ctaurl,
            ],
            'isrows' => $hasrows,
            'isempty' => !$hasrows && !$filtered,
            'iszero' => !$hasrows && $filtered,
            'zero' => !$hasrows && $filtered ? $this->zero() : '',
            'cta' => [
                'has' => !$this->isd20,
                'url' => $ctaurl,
                'label' => $this->isd20 ? '' : self::cta_label($this->name, $total, $filtered),
            ],
            'seeaslist' => [
                'has' => $this->isd20,
                'url' => $this->isd20
                    ? self::seeaslist_url($this->pageid, $this->areaid, $this->criteria, $this->psort, $this->anonymous)
                    : '',
            ],
            'jsmode' => false,
            'isloading' => false,
            'isskeleton' => false,
            'iserror' => false,
        ];
    }

    /**
     * The line under the title: how many of how many, or how many there are.
     *
     * Under a criterion it counts RESULTS and names what they answer - the search term, or "the
     * filters" when only filters narrow the page - so the reader can tell a narrowed rail from the
     * area's whole listing.
     *
     * @param int $total The panel listing's total, every narrowing applied.
     * @param int $shown How many the rail holds.
     * @param bool $filtered Whether anything narrows the listing.
     * @return string
     */
    private function subtitle(int $total, int $shown, bool $filtered): string {
        if ($filtered) {
            return $this->subtitle_results($total, $shown);
        }
        if ($total > $shown) {
            return get_string('category_panel_showing', self::COMPONENT, (object) [
                'shown' => self::number($shown),
                'total' => self::number($total),
            ]);
        }
        if ($total === 1) {
            return get_string('category_panel_count_one', self::COMPONENT);
        }
        return get_string('category_panel_count', self::COMPONENT, self::number($total));
    }

    /**
     * The line under the title under a criterion: results, and what they answer.
     *
     * @param int $total The panel listing's total, every narrowing applied.
     * @param int $shown How many the rail holds.
     * @return string
     */
    private function subtitle_results(int $total, int $shown): string {
        $term = $this->criteria->search;
        if ($total > $shown) {
            $a = (object) ['shown' => self::number($shown), 'total' => self::number($total), 'term' => $term];
            return get_string(
                $term !== '' ? 'category_panel_showingresults' : 'category_panel_showingresultsfilters',
                self::COMPONENT,
                $a
            );
        }
        if ($term !== '') {
            if ($total === 1) {
                return get_string('category_panel_countresults_one', self::COMPONENT, $term);
            }
            return get_string('category_panel_countresults', self::COMPONENT, (object) [
                'count' => self::number($total),
                'term' => $term,
            ]);
        }
        if ($total === 1) {
            return get_string('category_panel_countresultsfilters_one', self::COMPONENT);
        }
        return get_string('category_panel_countresultsfilters', self::COMPONENT, self::number($total));
    }

    /**
     * The zero state's sentence: nothing here answers the search, or the filters.
     *
     * @return string
     */
    private function zero(): string {
        if ($this->criteria->search !== '') {
            return get_string('category_panel_zero', self::COMPONENT, (object) [
                'term' => $this->criteria->search,
                'name' => $this->name,
            ]);
        }
        return get_string('category_panel_zerofilters', self::COMPONENT, $this->name);
    }

    /**
     * The strip of subareas: up to six pills with the subarea's own colour as a dot, then "+N".
     *
     * At rest every admitted subarea, without any parameter. Under a criterion only the subareas
     * holding a result, each pill carrying the criterion ({@see population::subareas()} decides
     * both), and the "+N" counts only the matching ones past six and carries the criterion to the
     * area's page too. A visitor's merely listed subarea is named and not linked; the panel of a
     * merely listed area ($isd20) has no strip at all, since it is a slice of the parent's listing
     * rather than the area's own.
     *
     * @return array ['has' => bool, 'pills' => [...], 'hasmore' => bool, 'morecount' => string, 'moreurl' => string].
     */
    private function also(): array {
        $subareas = $this->isd20 ? [] : $this->result->subareas;
        $pills = [];
        foreach (array_slice($subareas, 0, self::STRIP_SIZE) as $subarea) {
            $pills[] = [
                'id' => (int) $subarea['id'],
                'name' => (string) $subarea['name'],
                'url' => (string) $subarea['url'],
                'haslink' => (string) $subarea['url'] !== '',
                'style' => (string) $subarea['style'],
            ];
        }
        $more = count($subareas) - count($pills);
        $narrowing = $this->criteria->narrowing_params();
        $moreurl = ($this->titleurl !== '' && $narrowing !== [])
            ? self::address($this->areaid, $narrowing, $this->anonymous)
            : $this->titleurl;
        return [
            'has' => $pills !== [],
            'pills' => $pills,
            'hasmore' => $more > 0 && $this->titleurl !== '',
            'morecount' => self::number(max(0, $more)),
            'moreurl' => $moreurl,
        ];
    }

    /**
     * One no-JavaScript address of the page drawing the panel, or '' for the web service's copy.
     *
     * @param array $params The panel's own parameters to add to the page's.
     * @param string $anchor The fragment.
     * @return string
     */
    private function nojs(array $params, string $anchor): string {
        if ($this->pageparams === null) {
            return '';
        }
        return self::page_address($this->pageid, $this->pageparams + $params, $this->anonymous, $anchor);
    }

    /**
     * The call to action's address: the area's own page, flat, in the panel's order, narrowed as the page is.
     *
     * The flat order is what puts the ninth course where the reader expects it, right after the
     * eight the rail showed; the page size is left to the area's page.
     *
     * @param int $areaid The area.
     * @param criteria $criteria The request whose narrowing parameters it carries.
     * @param string $psort The panel's order.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @return string
     */
    private static function cta_url(int $areaid, criteria $criteria, string $psort, bool $anonymous): string {
        $params = $criteria->narrowing_params()
            + [criteria::PARAM_SORT => $psort === criteria::SORT_NAME ? criteria::SORT_NAME : criteria::SORT_RECENT];
        return self::address($areaid, $params, $anonymous);
    }

    /**
     * The "see all as a list" address of a merely listed area: the parent's own listing, flat, narrowed to the area.
     *
     * The same request the panel is a slice of, without its page size, so the reader reaches
     * every public course of the area on a page a visitor may open.
     *
     * @param int $pageid The parent, whose page is drawing.
     * @param int $areaid The area.
     * @param criteria $criteria The request whose narrowing parameters it carries.
     * @param string $psort The panel's order.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @return string
     */
    private static function seeaslist_url(int $pageid, int $areaid, criteria $criteria, string $psort, bool $anonymous): string {
        $params = $criteria->narrowing_params() + [
            criteria::PARAM_SORT => $psort === criteria::SORT_NAME ? criteria::SORT_NAME : criteria::SORT_RECENT,
            criteria::PARAM_CAT => $areaid,
        ];
        return self::address($pageid, $params, $anonymous);
    }

    /**
     * The one spelling of the panel's two ways out, for the shell that is drawing.
     *
     * @param int $categoryid The category whose page it opens.
     * @param array $params The query parameters.
     * @param bool $anonymous Whether the anonymous shell is drawing.
     * @return string
     */
    private static function address(int $categoryid, array $params, bool $anonymous): string {
        return links::showcase($categoryid, $params, $anonymous)->out(false);
    }

    /**
     * The call to action's label: the area's name, and how many more courses its page holds.
     *
     * @param string $name The area's plain name.
     * @param int|null $total How many courses the area holds, or null where nothing was counted (the root).
     * @param bool $filtered Whether a criterion narrows them, which makes them results rather than courses.
     * @return string
     */
    private static function cta_label(string $name, ?int $total, bool $filtered): string {
        $extra = $total === null ? 0 : $total - population::PANEL_SIZE;
        if ($extra <= 0) {
            return get_string('category_panel_cta', self::COMPONENT, $name);
        }
        if ($filtered) {
            if ($extra === 1) {
                return get_string('category_panel_cta_moreresults_one', self::COMPONENT, $name);
            }
            return get_string('category_panel_cta_moreresults', self::COMPONENT, (object) [
                'name' => $name,
                'count' => self::number($extra),
            ]);
        }
        if ($extra === 1) {
            return get_string('category_panel_cta_more_one', self::COMPONENT, $name);
        }
        return get_string('category_panel_cta_more', self::COMPONENT, (object) [
            'name' => $name,
            'count' => self::number($extra),
        ]);
    }
}
