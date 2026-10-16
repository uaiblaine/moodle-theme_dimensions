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
 * Theme Dimensions - The toolbar of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\page_result;
use theme_dimensions\local\landing\links;

/**
 * Builds the template context of the toolbar: the filter line, its panel, and the row of what narrows.
 *
 * The toolbar is a real GET form and every control in it works with
 * JavaScript off: the same parameter names serve the form, the address bar
 * and the web services. The scope cells and the panel's options are radio
 * inputs with labels drawn as cells or pills - a native radio group already
 * moves with the arrow keys and announces itself, so no roving tabindex is
 * needed. A "Filter" submit at the foot of the panel and an "Apply" submit in
 * the Organise menu are drawn for the no-JavaScript reader and hidden once a
 * listing client marks the body; the view toggle is a pair of submit buttons
 * carrying the view parameter, so a choice made without JavaScript still
 * reaches the server and is remembered.
 *
 * ONE line, in the filter line's slot order (moodle-dev/docs/filter-line/CONTRACT.md):
 * the scope (the state chip's values {@see criteria::SCOPE}, counted, for a
 * logged-in reader with the state sort on), the search, the favourites star,
 * the Filters disclosure, the Organise menu, the view toggle and the managers'
 * menu. Under it a second row exists only while something narrows: the count
 * sentence, then one chip per criterion the line does not show ({@see chips()}),
 * "Clear all" from two criteria up, "See all as a list" and the automatic
 * opening's note. At rest the row draws nothing but the count sentence, kept
 * in the document visually hidden because it is the page's live region.
 *
 * The Filters disclosure is a native `details`, closed on load: the chips row
 * says what narrows, so a narrowed page never needs the panel open to say so.
 * Its badge counts what the panel holds ({@see criteria::filter_count()}). The
 * panel holds "More statuses" (the state chip's values that are not the
 * scope, the same radio name), one group per allowlisted custom field and the
 * start-date range.
 *
 * The quick date options are ordinary links, computed here from the site
 * timezone's own calendar, carrying the same `from` and `to` a reader could
 * have typed into the two date fields. Either field may be left empty: each
 * bound stands alone ({@see criteria} says what each keeps). The calendar - a
 * start box and an end box opening one popover - is the client's, and it
 * speaks the same two parameters: that is the whole seam between server and
 * client. The server side of that seam is three pieces of data and no markup:
 * the two bounds of each quick option as attributes, the site's first weekday
 * on the date fieldset, and an EMPTY region for the client to mount its boxes
 * in - empty because a page without JavaScript must show the two native inputs
 * and nothing else, and a skeleton drawn here would be a control that never
 * becomes one.
 *
 * The favourites toggle is a submit button of its own on the line, because it
 * combines with the state rather than being one of its values: pressing it
 * posts `fav` with the value it would turn the toggle to, and while it is on a
 * hidden field earlier in the form carries `fav=1`, so every other submission
 * keeps it. A press that turns it off posts both, and the button's comes last,
 * which is the one PHP reads. It is drawn where the scope is and only for a
 * reader who has favourites ({@see criteria::can_favourite()}).
 *
 * Every number printed here is post-filter, read from the page result and
 * never recomputed. A state option whose count is zero is not offered, save
 * the one currently picked, so the line does not lengthen with states the
 * viewer has no course in. A facet option whose count the SQL-cut mode could
 * not produce prints its label alone, never a zero.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_toolbar implements \core\output\renderable, \core\output\templatable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string Query parameter: the view a submit button asks for. */
    public const PARAM_VIEW = 'view';

    /** @var string The card view. */
    public const VIEW_CARDS = 'cards';

    /** @var string The dense list view. */
    public const VIEW_LIST = 'list';

    /**
     * @var array The organisation chips in display order, sort value => label string key.
     *
     * The grouped default is named 'category' rather than '' so that the chip the reader
     * presses posts a value: a radio with an empty value and a radio with no value at all
     * are the same thing to a form, and the default has to be pressable like the others.
     */
    private const SORTS = [
        criteria::SORT_CATEGORY => 'category_sort_category',
        criteria::SORT_NAME => 'category_sort_name',
        criteria::SORT_RECENT => 'category_sort_recent',
    ];

    /**
     * @var array The state chips in display order, chip value => label string key: the scope's
     *   ({@see criteria::SCOPE}) first, in the line's order, then the panel's "More statuses".
     *
     * A chip value is not a state: {@see classifier::chip()} says which states each one counts and keeps.
     */
    private const CHIPS = [
        classifier::STATE_ENROLLED => 'category_chip_enrolled',
        classifier::STATE_OPEN => 'category_chip_open',
        classifier::STATE_SCHEDULED => 'category_chip_scheduled',
        classifier::STATE_PENDING => 'category_chip_pending',
        classifier::STATE_FREE => 'category_chip_free',
    ];

    /** @var string The chip's data-param for the period, whose two bounds go together. */
    public const CHIP_PERIOD = 'period';

    /**
     * Constructor.
     *
     * @param int $categoryid The listed category.
     * @param criteria $criteria The request.
     * @param page_result $result The resolved page: its counts and facets feed the chips.
     * @param string $view The view in force, VIEW_CARDS or VIEW_LIST.
     * @param bool $showstates Whether the state group is offered (a logged-in viewer with the state sort on),
     *   and with it the favourites toggle for a reader who has favourites.
     * @param string $manageactions Core's rendered action menu for the category's managers, or ''.
     * @param bool $anonymous Whether the anonymous shell is drawing, which posts the form elsewhere.
     */
    public function __construct(
        /** @var int The listed category. */
        private readonly int $categoryid,
        /** @var criteria The request. */
        private readonly criteria $criteria,
        /** @var page_result The resolved page. */
        private readonly page_result $result,
        /** @var string The view in force. */
        private readonly string $view,
        /** @var bool Whether the state group is offered. */
        private readonly bool $showstates = true,
        /** @var string Core's rendered action menu, or ''. */
        private readonly string $manageactions = '',
        /** @var bool Whether the anonymous shell is drawing. */
        private readonly bool $anonymous = false,
    ) {
    }

    /**
     * Export the toolbar context.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        $statusgroup = $this->showstates ? $this->status_group() : null;
        [$scope, $more] = self::split_status($statusgroup);
        $scope = $this->scope_cells($scope);
        $favourites = $this->favourites_toggle();
        $facets = $this->facet_groups();
        $chips = $this->chips();
        /* "Clear all" keeps the ORGANISATION and the open panel, and drops everything that
           narrows: reordering hides no course and neither does a panel, and a control that
           silently put the page back into the grouped default, or shut the panel the reader was
           reading, would send them looking for what they had just pressed. */
        $clearurl = links::showcase($this->categoryid, $this->criteria->keep_params(), $this->anonymous);
        /* Where the form posts and what carries the category are one decision, taken in
           links::showcase_form(): a GET submission replaces the action's query string, so the
           id travels as a hidden field or in the route's own path, never in the action. The
           category id only reaches the ids of the controls below. */
        $form = links::showcase_form($this->categoryid, $this->anonymous);
        $sortgroup = $this->sort_group();
        return (object) [
            'action' => $form['action'],
            'hidden' => $form['hidden'],
            'formid' => 'dim-vitrine-form-' . $this->categoryid,
            'searchid' => 'dim-vitrine-q-' . $this->categoryid,
            'searchname' => criteria::PARAM_SEARCH,
            'searchlabel' => get_string('category_search', self::COMPONENT),
            'searchplaceholder' => get_string('category_searchplaceholder', self::COMPONENT),
            'searchclearlabel' => get_string('category_searchclear', self::COMPONENT),
            'searchcloselabel' => get_string('category_searchclose', self::COMPONENT),
            'query' => $this->criteria->rawsearch,
            'hasquery' => $this->criteria->rawsearch !== '',
            'searchtooshort' => $this->criteria->rawsearch !== '' && $this->criteria->search === '',
            'searchtooshortlabel' => get_string('category_searchtooshort', self::COMPONENT, criteria::MIN_SEARCH_LENGTH),
            /* The suggestions under the line are the client's (categoryline), drawn from the
               controls already on the page: the server gives them an id for the combobox to point
               at and their two headings, so the browser composes no word of them. */
            'suggestid' => 'dim-vitrine-suggest-' . $this->categoryid,
            'suggestfilterslabel' => get_string('category_suggestfilters', self::COMPONENT),
            'suggeststartlabel' => get_string('category_suggeststart', self::COMPONENT),
            /* The whole state group, which the web services hand back for the client to split by
               criteria::SCOPE exactly as the two halves below are split here. */
            'statusgroup' => $statusgroup,
            'hasstatusgroup' => $statusgroup !== null,
            'scope' => $scope,
            'hasscope' => $scope !== null,
            'more' => $more,
            'hasmorestatuses' => $more !== null,
            'favourites' => $favourites,
            'hasfavourites' => $favourites !== null,
            'facets' => $facets,
            'hasfacets' => !empty($facets),
            'pagesizename' => criteria::PARAM_PAGESIZE,
            'pagesize' => $this->criteria->pagesize,
            'haspagesize' => $this->criteria->pagesize !== null,
            'openfields' => self::open_fields($this->criteria),
            'sortgroup' => $sortgroup,
            'sortlabel' => $sortgroup['legend'],
            'sortapplylabel' => get_string('category_apply', self::COMPONENT),
            'datefromid' => 'dim-datefrom-' . $this->categoryid,
            'datetoid' => 'dim-dateto-' . $this->categoryid,
            'datefromname' => criteria::PARAM_DATEFROM,
            'datetoname' => criteria::PARAM_DATETO,
            'datefrom' => $this->criteria->datefrom,
            'dateto' => $this->criteria->dateto,
            'datelegend' => get_string('category_dategroup', self::COMPONENT),
            'datefromlabel' => get_string('category_datefrom', self::COMPONENT),
            'datetolabel' => get_string('category_dateto', self::COMPONENT),
            /* The one calendar fact the client is given, so the popover's grid begins its weeks on
               the day the quick option "this week" begins them on and the day every other calendar
               of the site begins them on. The client does no calendar configuration and no
               timezone arithmetic of its own: what it reads here and on the quick options is
               already resolved in the SITE timezone. */
            'firstday' => criteria::week_start_day(),
            /* The other calendar fact, and the reason the client never asks its own clock what
               day it is: a browser reads its own timezone, the site resolves every bound of this
               filter in the SITE's, and the two differ by a day for part of every day. It decides
               which month the popover opens on and which cell is marked as today - both cosmetic,
               and both wrong on the same page whose quick options say otherwise. */
            'today' => criteria::iso(time()),
            'datequick' => $this->quick_dates(),
            'filterslabel' => get_string('category_filters', self::COMPONENT),
            'filtercount' => $this->criteria->filter_count(),
            'hasfiltercount' => $this->criteria->filter_count() > 0,
            'filtercountlabel' => get_string('category_filtercount', self::COMPONENT, $this->criteria->filter_count()),
            'viewname' => self::PARAM_VIEW,
            'viewlabel' => get_string('category_view', self::COMPONENT),
            'showaslabel' => get_string('category_showas', self::COMPONENT),
            // List first, as the filter line's contract draws it.
            'views' => [
                [
                    'value' => self::VIEW_LIST,
                    'label' => get_string('category_view_list', self::COMPONENT),
                    'current' => $this->view === self::VIEW_LIST,
                    'icon' => 'fa-list',
                ],
                [
                    'value' => self::VIEW_CARDS,
                    'label' => get_string('category_view_cards', self::COMPONENT),
                    'current' => $this->view === self::VIEW_CARDS,
                    'icon' => 'fa-border-all',
                ],
            ],
            'submitlabel' => get_string('category_filter', self::COMPONENT),
            // Whether the row under the line is drawn: something narrows, the search included.
            'hasfilters' => $this->criteria->has_filters(),
            'chips' => $chips,
            'haschips' => $chips !== [],
            'clearall' => $this->criteria->narrowing_count() >= 2,
            'clearurl' => $clearurl->out(false),
            'clearlabel' => get_string('category_clearall', self::COMPONENT),
            'manageactions' => $this->manageactions,
            'hasmanageactions' => $this->manageactions !== '',
        ];
    }

    /**
     * The state group cut in two by {@see criteria::SCOPE}: the line's scope cells and the panel's "More statuses".
     *
     * Both halves keep the group's radio name, so one choice holds across them. The scope is drawn
     * whenever the group is; "More statuses" too, hidden while it has no option to offer, because
     * a client repaints its options from every answer and needs the group to paint them into.
     *
     * @param array|null $statusgroup The whole group ({@see status_group()}), or null where none is drawn.
     * @return array [scope|null, more|null], each ['legend', 'name', 'options', 'hasoptions'].
     */
    private static function split_status(?array $statusgroup): array {
        if ($statusgroup === null) {
            return [null, null];
        }
        $scope = [];
        $more = [];
        foreach ($statusgroup['options'] as $option) {
            if (criteria::is_scope((string) $option['value'])) {
                $scope[] = $option;
            } else {
                $more[] = $option;
            }
        }
        return [
            [
                'legend' => $statusgroup['legend'],
                'name' => $statusgroup['name'],
                'options' => $scope,
                'hasoptions' => $scope !== [],
            ],
            [
                'legend' => get_string('category_morestatuses', self::COMPONENT),
                'name' => $statusgroup['name'],
                'options' => $more,
                'hasoptions' => $more !== [],
            ],
        ];
    }

    /**
     * The scope with every one of its cells drawn: a cell with nothing to offer is drawn hidden.
     *
     * A zero cell is not shown unless it is the chosen one ({@see status_group()}), but it is
     * still WRITTEN, carrying `absent`, which the template turns into the hidden attribute: the
     * client's folding (categoryline) measures the line with every cell of the scope drawn, so
     * the step a line takes follows its width and its language and never what the counts happen
     * to be, and a cell an answer brings back is found in place by its value
     * (categorylisting's paintGroup()). The cells are in {@see criteria::SCOPE}'s order. Every
     * option carries the key, so the template never reads it off an outer context.
     *
     * @param array|null $scope The scope half of {@see split_status()}, or null where none is drawn.
     * @return array|null The same group with one option per value of {@see criteria::SCOPE}.
     */
    private function scope_cells(?array $scope): ?array {
        if ($scope === null) {
            return null;
        }
        $drawn = [];
        foreach ($scope['options'] as $option) {
            $drawn[(string) $option['value']] = $option + ['absent' => false];
        }
        $cells = [];
        foreach (criteria::SCOPE as $value) {
            if (isset($drawn[$value])) {
                $cells[] = $drawn[$value];
            } else if (isset(self::CHIPS[$value])) {
                $cells[] = [
                    'id' => 'dim-chip-' . $this->categoryid . '-' . $value,
                    'value' => $value,
                    'label' => get_string(self::CHIPS[$value], self::COMPONENT),
                    'count' => 0,
                    'hascount' => true,
                    'checked' => false,
                    'absent' => true,
                ];
            }
        }
        $scope['options'] = $cells;
        $scope['hasoptions'] = $cells !== [];
        return $scope;
    }

    /**
     * The chips of the row under the line: one per criterion in force that the line does not show.
     *
     * The one builder of them: the page draws what this answers and both web services hand it
     * back ({@see \theme_dimensions\local\category\rows}), so the client composes no chip. In
     * order: the search in force, then each custom field (the option's label as its facet carries it, or as the field
     * defines it when the facet no longer offers it), the period (its
     * sentence in the site timezone, as {@see criteria} resolves it), a state chosen among "More
     * statuses" (a logged-in reader's only: a visitor's page honours no state chip) and the
     * tree's category (named as the tree names it). The scope and the favourites are drawn on the
     * line itself and get none.
     *
     * The search's chip, labelled with the term in force, is the one the stylesheet keeps out of
     * the picture until the client folds the search into an icon and the field is not open over
     * the line: a term left in force must still say so where the field no longer shows it
     * (moodle-dev/docs/filter-line/CONTRACT.md, section 3). It is built here, like every other
     * chip, so the browser composes none; without the client the field always shows, and so
     * the chip never does.
     *
     * Each chip is a link to this listing with that one criterion dropped and every other part of
     * the request kept - the other criteria, the organisation, the page size and the open panel,
     * as "Clear all" keeps them - so it works with no JavaScript; `param` names what it drops:
     * {@see criteria::PARAM_SEARCH}, a field's parameter, {@see criteria::PARAM_STATE},
     * {@see criteria::PARAM_CAT} or {@see CHIP_PERIOD} for both bounds of the period.
     *
     * @return array Entries of ['param' => string, 'label' => plain string, 'url' => string,
     *   'removelabel' => plain string, 'isperiod' => bool].
     */
    public function chips(): array {
        $chips = [];
        if ($this->criteria->search !== '') {
            $chips[] = $this->chip(criteria::PARAM_SEARCH, $this->criteria->search, [criteria::PARAM_SEARCH]);
        }
        $fields = null;
        foreach ($this->criteria->fields as $fieldid => $value) {
            $facet = $this->result->facets[$fieldid] ?? null;
            $label = $facet['options'][$value]['label'] ?? null;
            if ($label === null) {
                /* The facet offers only the options the other criteria leave a course in, so the
                   picked one may be missing from it; its label is then the field's own. */
                $fields ??= facets::fields();
                $label = isset($fields[$fieldid]) ? (facets::option_labels($fields[$fieldid])[$value] ?? null) : null;
            }
            if ($label === null) {
                continue;
            }
            $name = criteria::PARAM_FIELD_PREFIX . $fieldid;
            $chips[] = $this->chip($name, (string) $label, [$name]);
        }
        if ($this->criteria->datefrom !== '' || $this->criteria->dateto !== '') {
            $chips[] = $this->chip(
                self::CHIP_PERIOD,
                self::period_label($this->criteria),
                [criteria::PARAM_DATEFROM, criteria::PARAM_DATETO]
            );
        }
        $state = $this->criteria->state;
        if ($this->showstates && $state !== null && !criteria::is_scope($state) && isset(self::CHIPS[$state])) {
            $chips[] = $this->chip(
                criteria::PARAM_STATE,
                get_string(self::CHIPS[$state], self::COMPONENT),
                [criteria::PARAM_STATE]
            );
        }
        if ($this->criteria->cat > 0) {
            foreach ($this->result->subcategories as $entry) {
                if ((int) $entry['id'] === $this->criteria->cat) {
                    $chips[] = $this->chip(criteria::PARAM_CAT, (string) $entry['name'], [criteria::PARAM_CAT]);
                    break;
                }
            }
        }
        return $chips;
    }

    /**
     * One chip: its label and the address of this listing without the parameters it names.
     *
     * @param string $param What the chip drops, as its data-param says it.
     * @param string $label The chip's label, plain.
     * @param string[] $drop The query parameters the address leaves out.
     * @return array ['param', 'label', 'url', 'removelabel', 'isperiod'].
     */
    private function chip(string $param, string $label, array $drop): array {
        $params = $this->criteria->query_params();
        foreach ($drop as $name) {
            unset($params[$name]);
        }
        return [
            'param' => $param,
            'label' => $label,
            'url' => links::showcase($this->categoryid, $params, $this->anonymous)->out(false),
            'removelabel' => get_string('category_removefilter', self::COMPONENT),
            // The period's chip is drawn in the calendar's own region of the row (dim-daterange-pill).
            'isperiod' => $param === self::CHIP_PERIOD,
        ];
    }

    /**
     * The period as one sentence, its days written in the site timezone, as criteria resolved them.
     *
     * The three shapes a period takes have a language string each: a range, a start alone and an
     * end alone. The days are the instants {@see criteria::day_start()} computed, printed in the
     * SITE timezone, so the chip names the days the predicate keeps whoever reads it.
     *
     * @param criteria $criteria The request, with a period.
     * @return string Plain text.
     */
    private static function period_label(criteria $criteria): string {
        $timezone = \core_date::get_server_timezone();
        $format = get_string('strftimedate', 'langconfig');
        $from = $criteria->datefrom === '' ? '' : userdate((int) criteria::day_start($criteria->datefrom), $format, $timezone);
        $to = $criteria->dateto === '' ? '' : userdate((int) criteria::day_start($criteria->dateto), $format, $timezone);
        if ($from !== '' && $to !== '') {
            return get_string('category_daterangepill', self::COMPONENT, (object) ['from' => $from, 'to' => $to]);
        }
        if ($from !== '') {
            return get_string('category_daterangepillfrom', self::COMPONENT, $from);
        }
        return get_string('category_daterangepillto', self::COMPONENT, $to);
    }

    /**
     * The ROOT's toolbar: its search line and nothing else.
     *
     * The root reads the search and no filter ({@see criteria::root_from_request()}), so its
     * toolbar is the first line of this one - the same form, the same box, the same name-less
     * default submit first, the open panel's two fields and the too-short notice - with no filter
     * disclosure, no organisation, no view toggle and no count row. A reader whose page arms the
     * listing's module searches as they type, as on a category page; everybody else submits, so a
     * visible "Search" button stands beside the box for a reader without that module (the
     * anonymous root arms none).
     *
     * @param criteria $criteria The root's request.
     * @param bool $anonymous Whether the anonymous shell is drawing, which posts the form elsewhere.
     * @return array The context of category_searchline.mustache.
     */
    public static function search_line(criteria $criteria, bool $anonymous): array {
        $form = links::showcase_form(0, $anonymous);
        return [
            'action' => $form['action'],
            'hidden' => $form['hidden'],
            'formid' => 'dim-vitrine-form-0',
            'searchid' => 'dim-vitrine-q-0',
            'searchname' => criteria::PARAM_SEARCH,
            'searchlabel' => get_string('category_search', self::COMPONENT),
            'searchplaceholder' => get_string('category_searchplaceholder', self::COMPONENT),
            'searchclearlabel' => get_string('category_searchclear', self::COMPONENT),
            'query' => $criteria->rawsearch,
            'hasquery' => $criteria->rawsearch !== '',
            'searchtooshort' => $criteria->rawsearch !== '' && $criteria->search === '',
            'searchtooshortlabel' => get_string('category_searchtooshort', self::COMPONENT, criteria::MIN_SEARCH_LENGTH),
            'openfields' => self::open_fields($criteria),
            'submitlabel' => get_string('search'),
        ];
    }

    /**
     * The open panel as hidden fields of the form, so a submission without JavaScript keeps it open.
     *
     * Drawn only while a panel is open, and each field carries a region of its own, because the
     * panel module writes the panel into the address after the server drew this form and has to
     * keep the two in step - creating the fields, changing them or taking them out.
     *
     * @param criteria $criteria The request whose panel it is.
     * @return array Entries of ['name' => string, 'value' => int|string, 'region' => string], empty
     *   when no panel is open.
     */
    private static function open_fields(criteria $criteria): array {
        $fields = [];
        foreach ($criteria->panel_params() as $name => $value) {
            $fields[] = [
                'name' => $name,
                'value' => $value,
                'region' => $name === criteria::PARAM_OPEN ? 'dim-open-field' : 'dim-psort-field',
            ];
        }
        return $fields;
    }

    /**
     * The organisation group: three chips, exactly one of them pressed.
     *
     * A radio group rather than three links, because it is one question with
     * three answers and a native radio group already says so to a screen
     * reader and moves with the arrow keys. The visible label IS the legend:
     * one element names the group for assistive technology and for the eye
     * alike, rather than a hidden legend beside an aria-hidden copy of it.
     *
     * @return array ['legend' => plain, 'name' => string, 'options' => [...]] with one radio per option.
     */
    private function sort_group(): array {
        $options = [];
        foreach (self::SORTS as $value => $key) {
            $options[] = [
                'id' => 'dim-sort-' . $this->categoryid . '-' . $value,
                'value' => $value,
                'label' => get_string($key, self::COMPONENT),
                // An empty sort and 'category' are one request, and the default chip is pressed for both.
                'checked' => $value === criteria::SORT_CATEGORY
                    ? $this->criteria->is_grouped()
                    : $this->criteria->sort === $value,
            ];
        }
        return [
            'legend' => get_string('category_sortgroup', self::COMPONENT),
            'name' => criteria::PARAM_SORT,
            'options' => $options,
        ];
    }

    /**
     * The four quick date ranges, as ordinary addresses carrying this request's other filters.
     *
     * Computed HERE and not in the client, which is what keeps the whole
     * control working with JavaScript off: each one is a link to this listing
     * with `from` and `to` set, and a reader who follows one can read the two
     * dates back out of the address bar and out of the two date fields.
     *
     * Every boundary comes from the site timezone's own calendar
     * ({@see criteria::iso()}, {@see criteria::week_start()}), and the week
     * begins on the day every other calendar of the site begins on, so "this
     * week" means the same week in the showcase and in the calendar block.
     *
     * Each option also carries its two bounds as plain data, which is what lets the
     * client's calendar reuse them without recomputing a single boundary: the popover
     * reads them, sets its own range and fetches, while a reader with no JavaScript
     * follows the very same link.
     *
     * @return array Entries of ['label' => string, 'url' => string, 'from' => string,
     *   'to' => string, 'current' => bool], the two dates as YYYY-MM-DD.
     */
    private function quick_dates(): array {
        $now = time();
        $today = criteria::iso($now);
        $tomorrow = criteria::iso($now, 1);
        $thisweek = criteria::week_start($now);
        $nextweek = criteria::week_start($now, 1);
        $ranges = [
            'category_datetoday' => [$today, $today],
            'category_datetomorrow' => [$tomorrow, $tomorrow],
            'category_datethisweek' => [$thisweek, criteria::shift($thisweek, 6)],
            'category_datenextweek' => [$nextweek, criteria::shift($nextweek, 6)],
        ];
        $base = $this->criteria->query_params();
        unset($base[criteria::PARAM_DATEFROM], $base[criteria::PARAM_DATETO]);
        $quick = [];
        foreach ($ranges as $key => [$from, $to]) {
            $params = $base + [criteria::PARAM_DATEFROM => $from, criteria::PARAM_DATETO => $to];
            $quick[] = [
                'label' => get_string($key, self::COMPONENT),
                'url' => links::showcase($this->categoryid, $params, $this->anonymous)->out(false),
                'from' => $from,
                'to' => $to,
                'current' => $this->criteria->datefrom === $from && $this->criteria->dateto === $to,
            ];
        }
        return $quick;
    }

    /**
     * The favourites toggle, or null where none is drawn.
     *
     * Drawn beside the state group and on the same condition, and only for a reader who has
     * favourites at all: the guest account's and a visitor's would narrow to nothing, and the
     * population drops the criterion for them anyway. Its number is counted under the state chip in
     * force ({@see page_result::$counts}), and it is part of the button's accessible name.
     *
     * @return array|null ['name', 'value' (what a press posts: 1 to turn it on, 0 to turn it off),
     *   'pressed' (bool), 'count' (int), 'label' (plain)], or null.
     */
    private function favourites_toggle(): ?array {
        if (!$this->showstates || !criteria::can_favourite($this->anonymous)) {
            return null;
        }
        $pressed = $this->criteria->favourites;
        return [
            'name' => criteria::PARAM_FAVOURITES,
            'value' => $pressed ? 0 : 1,
            'pressed' => $pressed,
            'count' => (int) ($this->result->counts[classifier::CHIP_FAVOURITES] ?? 0),
            'label' => get_string('category_favourites', self::COMPONENT),
        ];
    }

    /**
     * The state group: "all", then every state the viewer holds a course in.
     *
     * @return array ['legend' => plain, 'name' => string, 'options' => [...]] with one radio per option.
     */
    private function status_group(): array {
        $counts = $this->result->counts;
        $current = $this->criteria->state;
        $options = [[
            'id' => 'dim-chip-' . $this->categoryid . '-all',
            'value' => '',
            'label' => get_string('category_chip_all', self::COMPONENT),
            'count' => (int) ($counts['total'] ?? 0),
            'hascount' => isset($counts['total']),
            'checked' => $current === null,
        ]];
        foreach (self::CHIPS as $value => $key) {
            $count = isset($counts[$value]) ? (int) $counts[$value] : null;
            if (($count === null || $count === 0) && $current !== $value) {
                continue;
            }
            $options[] = [
                'id' => 'dim-chip-' . $this->categoryid . '-' . $value,
                'value' => $value,
                'label' => get_string($key, self::COMPONENT),
                'count' => $count,
                'hascount' => $count !== null,
                'checked' => $current === $value,
            ];
        }
        return [
            'legend' => get_string('category_statusgroup', self::COMPONENT),
            'name' => criteria::PARAM_STATE,
            'options' => $options,
        ];
    }

    /**
     * One radio group per allowlisted custom field, its options counted by the population.
     *
     * @return array Groups of ['legend', 'name', 'anyid', 'anylabel', 'anychecked', 'options' => [...]].
     */
    private function facet_groups(): array {
        $groups = [];
        foreach ($this->result->facets as $fieldid => $facet) {
            $fieldid = (int) $fieldid;
            $picked = $this->criteria->fields[$fieldid] ?? null;
            $options = [];
            foreach ($facet['options'] as $value => $option) {
                $value = (int) $value;
                $count = $option['count'] ?? null;
                $options[] = [
                    'id' => 'dim-cf-' . $fieldid . '-' . $value,
                    'value' => $value,
                    'label' => $option['label'],
                    'count' => $count === null ? null : (int) $count,
                    'hascount' => $count !== null,
                    'checked' => $picked === $value,
                ];
            }
            if (!$options && $picked === null) {
                continue;
            }
            $groups[] = [
                'legend' => $facet['name'],
                'name' => criteria::PARAM_FIELD_PREFIX . $fieldid,
                'anyid' => 'dim-cf-' . $fieldid . '-any',
                'anylabel' => get_string('category_chip_all', self::COMPONENT),
                'anychecked' => $picked === null,
                'options' => $options,
            ];
        }
        return $groups;
    }
}
