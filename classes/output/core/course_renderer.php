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
 * Theme Dimensions - Course renderer
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output\core;

use context_system;
use core\url;
use core_course_category;
use core_course_list_element;
use coursecat_helper;
use lang_string;
use local_unlistedcourses\access;
use local_unlistedcourses\category_access;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\population;
use theme_dimensions\output\category_actions;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\category_pages;
use theme_dimensions\output\category_toolbar;

/**
 * Keeps unlisted courses and unlisted categories out of every listing this theme renders.
 *
 * WHY A THEME AND NOT A PLUGIN. Overriding a core renderer is a theme
 * privilege and nothing else's: theme_config::renderer_prefixes() returns only
 * theme_<name> prefixes, for the theme and its parents, and Moodle has no
 * db/renderers.php at all - that is a Totara mechanism. A local plugin cannot
 * substitute core_course_renderer, which is why the DECISION lives in
 * local_unlistedcourses and only the RENDERING lives here.
 *
 * COURSES. Two layers, on purpose:
 *
 * 1. coursecat_courses() filters whatever it is handed. Almost every listing
 *    surface in the course renderer funnels through it - the category page,
 *    the frontpage, search results, the exclusive tagged-courses page and the
 *    AJAX category expansion - so this is the net that catches the surfaces
 *    not enumerated below, and any that core adds later. It cannot fix the paging bar,
 *    because the $totalcount it receives was counted by somebody else.
 *
 * 2. coursecat_category_content(), search_courses() and
 *    frontpage_available_courses() fetch, filter and paginate themselves, so
 *    on the surfaces that print a count or a paging bar those agree with what
 *    is actually shown. By the time layer 1 sees those lists it has nothing
 *    left to drop. Without this, a viewer who can see two of four courses is
 *    offered a second page that renders empty, and a search that withheld one
 *    result still says how many there were.
 *
 * CATEGORIES, the same two layers over category_access:
 *
 * 1. coursecat_category() withholds the node of a category this user must not
 *    discover, whoever hands it over, and strips the expand affordance from a
 *    node whose children are all unlisted - an arrow opening onto nothing is
 *    an existence signal of its own. coursecat_tree() drops the tree-wide
 *    "Expand all" control on the same reasoning and from the same count.
 *
 * 2. coursecat_subcategories() fetches the children unpaginated, filters,
 *    recounts and only then slices, so the paging bar and the "view more"
 *    link of a category listing agree with the rows below them. It is the one
 *    method every category surface funnels through: the category page tree,
 *    coursecat_tree() and both front page category methods.
 *
 * coursecat_category_content() carries a category guard as well, because the
 * AJAX expander (course/category.ajax.php) enters there directly with an id
 * from the request, past both the node override and the after_config guard.
 *
 * Fetching a category unpaginated costs what core already pays: core never
 * paginates these queries in SQL. core_course_category::get_courses() loads
 * the whole category, sorts it, caches the id list in the per-session
 * coursecat cache and only then applies array_slice() in PHP - and
 * search_courses() and get_children() do the same.
 *
 * This extends Boost Union's renderer rather than core's, so its card and
 * list-of-boxes presentations (courselistingpresentation,
 * categorylistingpresentation) keep working. They are touched in exactly one
 * place: those presentations name a course's category from their own helper
 * and never reach core's "Category: name" line, so coursecat_coursebox_content()
 * repeats the same guard over the badge they print - and nothing else about
 * them changes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_renderer extends \theme_boost_union\output\core\course_renderer {
    /** @var string The body class every rule of scss/category.scss is scoped under: the listing's. */
    public const BODY_CLASS = category_listing::BODY_CLASS;

    /**
     * Render the category page: the showcase for a category both gates admit, core's page for anything else.
     *
     * The logged-in shell. The guards run cheapest first and any miss hands
     * the request to the parent - Boost Union's page, which is core's - so
     * that the kill switch, a category core would not show, a category
     * local_unlistedcourses withholds, and the root asked for by a visitor or
     * by the guest account all render exactly what Boost Union renders. The
     * core gate is asked before the plugin's: the plugin is a layer over core
     * visibility and answers "yes" wholesale for a hidden category.
     *
     * The ROOT of the course index (category 0) is served too, and as a
     * DIRECTORY rather than a listing: the hero, the search line, then a
     * poster per top-level category the viewer may discover, with no filter,
     * no course grid and no pager.
     *
     * Everything the page shows is decided by {@see population::resolve()}
     * for the viewer of the session and drawn by {@see category_listing} and,
     * from the page header, by {@see category_hero}; this method only reads
     * the request, sets the page up, arms the hero and renders the listing.
     *
     * @param int|\stdClass|core_course_category $category The category, as core hands it over.
     * @return string
     */
    public function course_category($category) {
        // Whatever this call decides, no hero of an earlier decision may reach the header.
        category_hero::reset();
        $categoryid = is_object($category) ? (int) $category->id : (int) $category;
        $coursecat = $this->showcase_category($categoryid);
        if ($coursecat === null) {
            return parent::course_category($category);
        }

        $isroot = $categoryid === 0;
        $fields = facets::fields();
        /* The root draws a search box and no other control that narrows, so it reads its term
           and no filter off the query string: a hand-typed field or state filter would otherwise
           change every poster's number, and the hero's fold form would carry the phantom filter
           forward with it. population::resolve() honours nothing else at the root either - this
           is the half that keeps the rest out of the hero. It also reads its open panel: its
           posters open panels like any other page's. */
        /* The view first: a view named in the address is remembered for this category
           (category_listing::view()), and the request below then knows the reader has a state here. */
        $view = category_listing::view(optional_param(category_toolbar::PARAM_VIEW, '', PARAM_ALPHA), $categoryid);
        $criteria = $isroot
            ? criteria::root_from_request()
            : criteria::from_request($categoryid, array_keys($fields));
        $result = population::resolve($criteria);
        $folded = category_hero::fold(optional_param(category_hero::PARAM_FOLD, null, PARAM_BOOL));

        // Plain: set_title() formats and escapes for itself. top()'s own name is the word "Top".
        $this->page->set_title($isroot ? get_string('courses') : $coursecat->get_formatted_name(['escape' => false]));
        $this->page->add_body_class(self::BODY_CLASS);

        /* The root's directory has no manage menu of its own: category_actions builds the menu of
           ONE category, and the pseudo-category is not one - core offers "add a category" from the
           management page instead. */
        $manageactions = $isroot ? '' : (new category_actions($this->page, $coursecat))->render_actions($this);
        // The pages strip is built by the page's two entries, never by the listing's export: see category_pages.
        $pages = category_pages::export($categoryid);
        $listing = new category_listing($coursecat, $criteria, $result, $view, $manageactions, $pages, true);

        /* The listing's account client belongs to THIS shell and to no other. The anonymous shell
           draws the same listing with its own client (request.php arms listingvisitor), and this one
           is a client of the showcase's login-required services; so the call that arms it is here
           rather than in the listing.

           And not for the guest account, which core counts as logged in and which request.php
           therefore routes to this shell on purpose. All three of the showcase's web services
           refuse the guest, so an armed module there would hide the no-JavaScript "Filter"
           submit and the numbered pager - the only controls that DO work for a guest - and
           answer every click with an exception dialogue. The card's star follows the same rule
           (showstar). The guest is kept out here; never loosen the services' refusal instead. */
        if (isloggedin() && !isguestuser()) {
            /* The root included: its search line is the listing's own, live as on a category page, and its
               posters open panels as a category's do (js/esm/src/listing/account.ts, with the stars). */
            $this->page->requires->js_call_amd('theme_dimensions/listingaccount', 'init');
            /* The course dialogue's module, under the same rule: it fetches a course's content from a service
               that refuses the guest. Without it "More information" is the link to the card's own page, which
               is what the guest and the visitor follow. */
            $this->page->requires->js_call_amd('theme_dimensions/categorycoursemodal', 'init');
        }

        /* The cover film's module is armed for EVERYBODY and on every one of these pages, which
           is the opposite of the rule above and is not an inconsistency. It calls no web service
           - it imports nothing at all - so none of the three refusals that shut the guest out of
           the account's client reaches it, and it hides no control: its own body class reveals the
           cluster and says nothing about the toolbar's no-JavaScript submit. And the ROOT is
           where category cards live, so an arming that skipped the root would skip the whole
           feature; the same line stands in request.php for the anonymous shell. */
        $this->page->requires->js_call_amd('theme_dimensions/covervideo', 'init');

        // The hero is drawn by the core renderer's full_header(), which core calls after this
        // method returns; it is armed here because this is where the counts are known. The root
        // is a directory of posters with a search line and no grid of courses.
        $description = '';
        if (!$isroot) {
            $chelper = new coursecat_helper();
            $description = (string) $chelper->get_category_formatted_description($coursecat);
        }
        category_hero::arm(new category_hero($coursecat, $result, $criteria, $folded, $description, true, $this->page));

        return $this->render($listing);
    }

    /**
     * The category the showcase may render, or null when core's page must render instead.
     *
     * Three guards, in this order: the kill switch, core's own visibility
     * question, then the plugin's. A miss is not a refusal - the
     * parent decides what a viewer who fails core's gate sees, with core's
     * own exception, and the after_config ghost has already answered for a
     * withheld category before this method runs.
     *
     * @param int $categoryid The category id from the request, 0 for the site root.
     * @return core_course_category|null
     */
    protected function showcase_category(int $categoryid): ?core_course_category {
        if (!population::enabled()) {
            return null;
        }
        if ($categoryid === 0) {
            /* The ROOT of the course index. Core's course/index.php hands 0 over through
               user_top() whenever the viewer may see more than one top-level category, so the
               question core would ask about the root is already answered. A visitor and the guest
               account get core's own page here; the anonymous root, when the site opens it, is
               served by request::handle() instead. */
            if (!isloggedin() || isguestuser()) {
                return null;
            }
            return core_course_category::top();
        }
        $coursecat = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($coursecat === null || !$coursecat->is_uservisible()) {
            return null;
        }
        // The row in hand carries its path, so the plugin needs no lookup to answer.
        if (!category_access::filter_categories([$coursecat])) {
            return null;
        }
        return $coursecat;
    }

    /**
     * Drop the courses this user must not discover, then render as usual.
     *
     * The safety net described in the class docblock. $totalcount is reduced by
     * what THIS page dropped, which is exact for the three surfaces overridden
     * below (their lists arrive already filtered, so the reduction is zero and
     * the count they computed is already right) and a best effort everywhere
     * else: a global total cannot be corrected from one page of it. Every
     * listing this renderer owns is now in that overridden set, so the best
     * effort applies only to a caller core might add later - which is what the
     * net is for. Names never leak either way; only a count could.
     *
     * @param coursecat_helper $chelper Display options.
     * @param array $courses The courses to display.
     * @param int|null $totalcount Total number of courses, for pagination.
     * @return string
     */
    protected function coursecat_courses(coursecat_helper $chelper, $courses, $totalcount = null) {
        if ($totalcount === null) {
            $totalcount = count($courses);
        }
        $filtered = access::filter_courses($courses);
        $totalcount = max(0, $totalcount - (count($courses) - count($filtered)));

        return parent::coursecat_courses($chelper, $filtered, $totalcount);
    }

    /**
     * Render a category tree, minus an expand-all control that would open onto nothing.
     *
     * The twin, one level up, of the expand affordance {@see coursecat_category()} strips from a
     * single node. Core prints the "Expand all" / "Collapse all" control whenever the RAW
     * get_children_count() of the tree's own category is non-zero (course/renderer.php:925, and
     * Boost Union's copy at classes/output/core/course_renderer.php:798, line for line the same),
     * so a category whose children are ALL unlisted keeps the control over a tree holding no
     * expandable node at all - the same existence signal the arrow was, printed by a different
     * method. The tree's contents are already right underneath: the children were filtered by
     * coursecat_subcategories() before they got here.
     *
     * Core writes the control as one self-contained block that holds nothing but the link
     * (html_writer::start_tag('div', ['class' => 'collapsible-actions'])), and both upstreams
     * write it identically, so removing it from the rendered string is unambiguous and costs no
     * copy of either method - the same trade this class makes for the node's own classes.
     *
     * @param coursecat_helper $chelper Display options.
     * @param core_course_category $coursecat The category the tree is rooted at.
     * @return string
     */
    protected function coursecat_tree(coursecat_helper $chelper, $coursecat) {
        $html = parent::coursecat_tree($chelper, $coursecat);
        if ($html === '' || count(category_access::filter_categories($coursecat->get_children()))) {
            return $html;
        }

        return preg_replace('~<div class="collapsible-actions">.*?</div>~s', '', $html, 1);
    }

    /**
     * Render the subcategories of one category, minus the ones this user must not discover.
     *
     * A COPY of core's method (course/renderer.php:709-772 on 5.2). Boost Union does not
     * override it, so there is no parent to call and no upstream presentation to preserve;
     * core's own html_writer calls are therefore kept exactly as core writes them, this being
     * a copy of a core renderer method rather than new plugin code. Re-diff it against that
     * range on every core upgrade.
     *
     * What changed, and only this: the children are fetched UNPAGINATED, run through
     * category_access::filter_categories(), counted, and only then sliced. Filtering after core
     * has paginated would leave the total overstated and every page short - a page can render
     * empty - which is the same reason the course half of this class fetches unpaginated, and
     * it costs what core already pays: get_children() caches the whole sorted id list in the
     * session coursecat cache and slices it in PHP (course/classes/category.php:1329).
     *
     * One departure from core's shape, deliberate. Core takes the total from
     * get_children_count(), a cached scalar it can read whether or not 'nodisplay' is set, so
     * it skips the fetch in that case. A FILTERED total cannot be had without the fetch, so
     * the fetch happens either way and 'nodisplay' only empties the list that is RENDERED -
     * which is what keeps course_category()'s "View all subcategories" link alive on its
     * browse=courses branch, where nodisplay is exactly what is set.
     *
     * One more divergence, cosmetic, disclosed here so that a re-diff against that range is not
     * a surprise: core leaves $pagingbar and $morelink undefined and tests them with empty();
     * this copy declares both as '' and tests them against ''. The two agree in every case -
     * neither ever holds the string "0" - and nothing else in those blocks changed.
     *
     * This one override covers every surface that lists categories: the category page tree,
     * coursecat_tree() and both front page category methods all reach the children through it.
     *
     * @param coursecat_helper $chelper Display options.
     * @param core_course_category $coursecat The category whose children are listed.
     * @param int $depth Nesting depth of that category in the current tree.
     * @return string
     */
    protected function coursecat_subcategories(coursecat_helper $chelper, $coursecat, $depth) {
        global $CFG;

        $options = $chelper->get_categories_display_options();
        $offset = (int) ($options['offset'] ?? 0);
        $limit = (int) ($options['limit'] ?? 0);
        unset($options['offset'], $options['limit']);

        $all = category_access::filter_categories($coursecat->get_children($options));
        $totalcount = count($all);
        if (!$totalcount) {
            return '';
        }
        $subcategories = [];
        if (!$chelper->get_categories_display_option('nodisplay')) {
            $subcategories = $limit > 0 ? array_slice($all, $offset, $limit, true) : $all;
        }

        // Prepare the paging bar or the more link, if one is needed.
        $pagingbar = '';
        $morelink = '';
        $paginationurl = $chelper->get_categories_display_option('paginationurl');
        $paginationallowall = $chelper->get_categories_display_option('paginationallowall');
        if ($totalcount > count($subcategories)) {
            if ($paginationurl) {
                // The option 'paginationurl' was given: display a paging bar.
                $perpage = $chelper->get_categories_display_option('limit', $CFG->coursesperpage);
                $page = $chelper->get_categories_display_option('offset') / $perpage;
                $pagingbar = $this->paging_bar(
                    $totalcount,
                    $page,
                    $perpage,
                    $paginationurl->out(false, ['perpage' => $perpage])
                );
                if ($paginationallowall) {
                    $pagingbar .= \html_writer::tag(
                        'div',
                        \html_writer::link(
                            $paginationurl->out(false, ['perpage' => 'all']),
                            get_string('showall', '', $totalcount)
                        ),
                        ['class' => 'paging paging-showall']
                    );
                }
            } else if ($viewmoreurl = $chelper->get_categories_display_option('viewmoreurl')) {
                // The option 'viewmoreurl' was given: display a more link, carrying the category id.
                if ($viewmoreurl->compare(new url('/course/index.php'), URL_MATCH_BASE)) {
                    $viewmoreurl->param('categoryid', $coursecat->id);
                }
                $viewmoretext = $chelper->get_categories_display_option('viewmoretext', new lang_string('viewmore'));
                $morelink = \html_writer::tag(
                    'div',
                    \html_writer::link($viewmoreurl, $viewmoretext),
                    ['class' => 'paging paging-morelink']
                );
            }
        } else if (($totalcount > $CFG->coursesperpage) && $paginationurl && $paginationallowall) {
            // More than one page of results while in "view all" mode: offer the paginated mode back.
            $pagingbar = \html_writer::tag(
                'div',
                \html_writer::link(
                    $paginationurl->out(false, ['perpage' => $CFG->coursesperpage]),
                    get_string('showperpage', '', $CFG->coursesperpage)
                ),
                ['class' => 'paging paging-showperpage']
            );
        }

        // Display the list of subcategories.
        $content = \html_writer::start_tag('div', ['class' => 'subcategories']);

        if ($pagingbar !== '') {
            $content .= $pagingbar;
        }

        foreach ($subcategories as $subcategory) {
            $content .= $this->coursecat_category($chelper, $subcategory, $depth + 1);
        }

        if ($pagingbar !== '') {
            $content .= $pagingbar;
        }
        if ($morelink !== '') {
            $content .= $morelink;
        }

        $content .= \html_writer::end_tag('div');
        return $content;
    }

    /**
     * Render one category node, unless this user must not discover the category.
     *
     * Two things happen here.
     *
     * The node of an undiscoverable category is not rendered at all - its name, its link, its
     * count and its whole subtree - which catches a category handed to this method from
     * anywhere but the filtered listing above. The top-level pseudo-category has id 0 and is
     * always discoverable: is_category_discoverable(0) would fail closed on a path no row
     * holds, so the id is tested first.
     *
     * Then the expand affordance. Core's not-loaded branch (course/renderer.php:850-858, and
     * Boost Union's copy at classes/output/core/course_renderer.php:695-703, byte for byte the
     * same) decides the with_children and collapsed classes from the RAW get_children_count(),
     * so a discoverable category whose children are ALL unlisted would keep a live expand arrow
     * opening onto nothing - an existence signal, and one the AJAX expander then answers with
     * an empty box. When that branch applies, the FILTERED child count is zero and the course
     * half of core's condition is false as well, the two classes are removed from the node's
     * own opening tag. Both upstreams write the class attribute FIRST in that tag (core
     * :873-874, Boost Union :718-719), so the first class attribute of the string is that tag's
     * and one targeted replacement is unambiguous.
     *
     * The course half of that condition, and the number core prints in COUNT mode, are both
     * read from the FILTERED direct courses: get_courses_count() counts unlisted courses, so
     * the raw scalar would keep an arrow over a category whose only course is withheld, and
     * would print the number beside a name with the withheld courses in it: " (N)" in core
     * (course/renderer.php:886-890, the front page's category list), a bare "N" badge in Boost
     * Union's copy under its categorylistingpresentation setting (:731-735). The count is taken once
     * per node and only when one of those two branches needs it. In COUNT mode the span's
     * number is rewritten to the filtered one, and the span is dropped at zero as core drops
     * it. The node's own span is the one inside its FIRST name heading: a child's span sits
     * further down and was rewritten when that child was rendered.
     *
     * @param coursecat_helper $chelper Display options.
     * @param core_course_category $coursecat The category to render.
     * @param int $depth Nesting depth of this category in the current tree.
     * @return string Empty when this user must not discover the category.
     */
    protected function coursecat_category(coursecat_helper $chelper, $coursecat, $depth) {
        if ($coursecat->id && !category_access::is_category_discoverable((int) $coursecat->id)) {
            return '';
        }

        $html = parent::coursecat_category($chelper, $coursecat, $depth);
        if ($html === '') {
            return $html;
        }

        $count = null;
        if (
            $chelper->get_show_courses() == self::COURSECAT_SHOW_COURSES_COUNT
            && strpos($html, 'numberofcourse') !== false
        ) {
            $count = $this->discoverable_courses_count($coursecat);
            $html = $this->restate_courses_count($html, $count);
        }

        $notloaded = $chelper->get_subcat_depth() > 0 && $depth >= $chelper->get_subcat_depth();
        if (!$notloaded) {
            return $html;
        }
        $withcourses = false;
        if ($chelper->get_show_courses() >= self::COURSECAT_SHOW_COURSES_COLLAPSED) {
            $withcourses = ($count ?? $this->discoverable_courses_count($coursecat)) > 0;
        }
        if ($withcourses || count(category_access::filter_categories($coursecat->get_children()))) {
            return $html;
        }

        return preg_replace_callback(
            '/class="([^"]*)"/',
            static function (array $matches): string {
                $kept = array_diff(explode(' ', $matches[1]), ['with_children', 'collapsed']);
                return 'class="' . implode(' ', $kept) . '"';
            },
            $html,
            1
        );
    }

    /**
     * How many of a category's direct courses this user may discover.
     *
     * The same scope as get_courses_count() with no options (direct courses only), so the two
     * numbers differ by exactly the courses access::are_courses_discoverable() withholds.
     *
     * @param core_course_category $coursecat The category.
     * @return int
     */
    private function discoverable_courses_count(core_course_category $coursecat): int {
        $ids = $coursecat->get_courses(['idonly' => true]);
        return count(array_filter(access::are_courses_discoverable($ids)));
    }

    /**
     * Replace the number in a node's course count span, or drop the span at zero.
     *
     * Both spellings are matched: core's span carries the class alone and " (N)", Boost Union's
     * badge carries more classes and a bare "N".
     *
     * Only the node's own name heading is touched, so a child's span is left as the child's
     * render wrote it. Core writes no span at zero, and neither does this.
     *
     * @param string $html The node as core rendered it.
     * @param int $count The filtered number of direct courses.
     * @return string
     */
    private function restate_courses_count(string $html, int $count): string {
        if (!preg_match('~<h[34] class="categoryname[^"]*">.*?</h[34]>~s', $html, $heading, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $rewritten = preg_replace_callback(
            '~(<span\b[^>]*\bclass="numberofcourse(?:\s[^"]*)?"[^>]*>)([^<]*)(</span>)~',
            static function (array $matches) use ($count): string {
                if ($count === 0) {
                    return '';
                }
                return $matches[1] . preg_replace('~\d+~', (string) $count, $matches[2], 1) . $matches[3];
            },
            $heading[0][0],
            1
        );
        return substr_replace($html, $rewritten, $heading[0][1], strlen($heading[0][0]));
    }

    /**
     * Render the contents of one category, minus what this user must not discover.
     *
     * The guard is what covers course/category.ajax.php, whose only entry is core's
     * coursecat_ajax() (course/renderer.php:1044-1071) calling straight into this method with a
     * category id taken from the request. The after_config guard cannot see that path at all -
     * it returns early for AJAX_SCRIPT - and the node override above is never reached either,
     * because the AJAX request asks for the CONTENTS of a category, not for its node. The
     * top-level pseudo-category has id 0 and is always discoverable, so the id is tested first.
     *
     * @param coursecat_helper $chelper Display options.
     * @param core_course_category $coursecat The category.
     * @param int $depth Nesting depth.
     * @return string Empty when this user must not discover the category.
     */
    protected function coursecat_category_content(coursecat_helper $chelper, $coursecat, $depth) {
        if ($coursecat->id && !category_access::is_category_discoverable((int) $coursecat->id)) {
            return '';
        }

        $content = $this->coursecat_subcategories($chelper, $coursecat, $depth);

        // AUTO show courses: collapsed when nested, exactly as core decides it.
        $showcoursesauto = $chelper->get_show_courses() == self::COURSECAT_SHOW_COURSES_AUTO;
        if ($showcoursesauto && $depth) {
            $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_COLLAPSED);
        }

        if ($chelper->get_show_courses() > self::COURSECAT_SHOW_COURSES_COUNT) {
            $options = $chelper->get_courses_display_options();
            $offset = (int) ($options['offset'] ?? 0);
            $limit = (int) ($options['limit'] ?? 0);
            unset($options['offset'], $options['limit']);

            $all = access::filter_courses($coursecat->get_courses($options));
            $totalcount = count($all);
            $courses = [];
            if (!$chelper->get_courses_display_option('nodisplay')) {
                $courses = $limit > 0 ? array_slice($all, $offset, $limit, true) : $all;
            }

            if ($viewmoreurl = $chelper->get_courses_display_option('viewmoreurl')) {
                // If it links to the category view page, carry the category id.
                if ($viewmoreurl->compare(new url('/course/index.php'), URL_MATCH_BASE)) {
                    $chelper->set_courses_display_option(
                        'viewmoreurl',
                        new url($viewmoreurl, ['categoryid' => $coursecat->id])
                    );
                }
            }
            $content .= $this->coursecat_courses($chelper, $courses, $totalcount);
        }

        if ($showcoursesauto) {
            // Restore show_courses back to AUTO.
            $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_AUTO);
        }

        return $content;
    }

    /**
     * The "Category: name" line of a course box, unless this user must not discover the category.
     *
     * Belt and braces, and cheap. access::filter_courses() already drops a course sitting inside
     * an unlisted category from every listing except for the people who hold a relationship with
     * the COURSE - enrolled, awaiting a decision, or staff - and for THEM the name of the
     * category their own course sits in is knowledge they already hold. So this withholds
     * nothing that was reachable; it exists so that a surface which renders such a course box
     * cannot name the category as a side effect.
     *
     * @param coursecat_helper $chelper Display options.
     * @param core_course_list_element $course The course whose box is being rendered.
     * @return string Empty when this user must not discover the course's category.
     */
    protected function course_category_name(coursecat_helper $chelper, core_course_list_element $course): string {
        if ($course->category && !category_access::is_category_discoverable((int) $course->category)) {
            return '';
        }
        return parent::course_category_name($chelper, $course);
    }

    /**
     * Empty the category badge of a Boost Union course card, for a category this user must not discover.
     *
     * The rule {@see course_category_name()} states, on the presentation this theme actually
     * runs. Core reaches that method only in EXPANDED_WITH_CAT mode and Boost Union's card and
     * list presentations never reach it at all: with courselistingpresentation set to anything
     * but "nochange", coursecat_coursebox_content() takes its own branch and names the category
     * from theme_boost_union\util\course::get_category() (its course_renderer.php:576, its
     * util/course.php:113-124), which asks core_course_category::get() and no predicate at all,
     * whenever courselistinghowcategory is on. So the guard above is dead on exactly the
     * configuration this theme is deployed with, and this is where it has to be repeated.
     *
     * Both of Boost Union's templates wrap the value in the same element - a span carrying the
     * classes "categoryname text-truncate", templates/courselistingcard.mustache:90-92 and
     * templates/courselistinglist.mustache:92-94 - and nothing else in a course box carries that
     * class (core's own category line is a div.coursecat, course/renderer.php:550), so emptying
     * it is one unambiguous replacement and costs no copy of a 140-line upstream method. Only the
     * NAME goes: the card, the course and its link stay, because whoever is reading it was by
     * construction rescued into this listing by access::filter_courses() through their own
     * relationship with the COURSE.
     *
     * Boost Union's sticky category headers (its course_renderer.php:250-262 and :331-343) need
     * no guard of their own. They are printed only when $showstickyheaders is true, which
     * requires get_show_courses() <= COURSECAT_SHOW_COURSES_COLLAPSED (its :198-206), while every
     * listing that mixes categories in one call asks for more than that: this theme's
     * search_courses() and frontpage_available_courses() set EXPANDED_WITH_CAT and EXPANDED, and
     * so does core's tagged_courses(). What is left is the per-category tree, where the only name
     * such a header can print is that of the category whose own node and content are withheld
     * above.
     *
     * @param coursecat_helper $chelper Display options.
     * @param \stdClass|core_course_list_element $course The course whose box is being rendered.
     * @return string
     */
    protected function coursecat_coursebox_content(coursecat_helper $chelper, $course) {
        $content = parent::coursecat_coursebox_content($chelper, $course);
        $categoryid = (int) ($course->category ?? 0);
        if ($content === '' || !$categoryid || category_access::is_category_discoverable($categoryid)) {
            return $content;
        }

        return preg_replace('~(<span class="categoryname[^"]*">).*?(</span>)~s', '$1$2', $content, 1);
    }

    /**
     * Render the courses carrying a tag, minus the undiscoverable ones.
     *
     * Overridden because one of its two branches escapes the safety net
     * entirely: with $exclusivemode false, core builds a core_tag\output\tagfeed
     * itself and never calls coursecat_courses(), so it would print
     * get_formatted_name() for every tagged course with no filter at all. The
     * exclusive branch does go through the net, but takes its count from
     * search_courses_count() and would announce a result it did not show.
     *
     * @param int $tagid Tag id.
     * @param bool $exclusivemode Whether this is the only content on the page.
     * @param int $ctx Context id to search within.
     * @param bool $rec Whether to search subcontexts.
     * @param array|null $displayoptions Display options.
     * @return string Empty string when no visible course carries the tag.
     */
    public function tagged_courses($tagid, $exclusivemode = true, $ctx = 0, $rec = true, $displayoptions = null) {
        global $CFG;

        if (empty($displayoptions)) {
            $displayoptions = [];
        }
        $showcategories = !core_course_category::is_simple_site();
        $displayoptions += ['limit' => $CFG->coursesperpage, 'offset' => 0];
        $chelper = new coursecat_helper();
        $searchcriteria = ['tagid' => $tagid, 'ctx' => $ctx, 'rec' => $rec];
        $chelper->set_show_courses($showcategories
                ? self::COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT
                : self::COURSECAT_SHOW_COURSES_EXPANDED)
            ->set_search_criteria($searchcriteria)
            ->set_courses_display_options($displayoptions)
            // The same css class as in search results by tagid.
            ->set_attributes(['class' => 'course-search-result course-search-result-tagid']);

        $unpaged = $chelper->get_courses_display_options();
        unset($unpaged['limit'], $unpaged['offset']);
        $all = access::filter_courses(core_course_category::search_courses($searchcriteria, $unpaged));
        $totalcount = count($all);
        if (!$totalcount) {
            return '';
        }
        $courses = array_slice(
            $all,
            (int) $displayoptions['offset'],
            (int) $displayoptions['limit'],
            true
        );

        if ($exclusivemode) {
            return $this->coursecat_courses($chelper, $courses, $totalcount);
        }

        $tagfeed = new \core_tag\output\tagfeed();
        $img = $this->output->pix_icon('i/course', '');
        foreach ($courses as $course) {
            $url = course_get_url($course);
            $imgwithlink = \html_writer::link($url, $img);
            $coursename = \html_writer::link($url, $course->get_formatted_name());
            $details = '';
            /* Belt and braces, exactly as course_category_name() above: filter_courses() has already
               dropped a course inside an unlisted category for everyone but the people who hold a
               relationship with the course itself, and for them the category their own course sits
               in is knowledge they already hold. */
            $showcat = $showcategories && $course->category
                && category_access::is_category_discoverable((int) $course->category);
            if ($showcat && ($cat = core_course_category::get($course->category, IGNORE_MISSING))) {
                $details = get_string('category') . ': ' .
                    \html_writer::link(
                        new url('/course/index.php', ['categoryid' => $cat->id]),
                        $cat->get_formatted_name(),
                        ['class' => $cat->visible ? '' : 'dimmed']
                    );
            }
            $tagfeed->add($imgwithlink, $coursename, $details);
        }
        return $this->output->render_from_template('core_tag/tagfeed', $tagfeed->export_for_template($this->output));
    }

    /**
     * Render course search results, minus the undiscoverable ones.
     *
     * Overridden for the count, not for the list: the safety net above already
     * drops the rows, but core prints "Search results: N" from a $totalcount it
     * obtained separately through search_courses_count(). Left alone, the page
     * says six and shows five - which does not name the withheld course but does
     * announce that one exists, and on this platform that is the fact being
     * withheld.
     *
     * @param array $searchcriteria Search criteria, as built by course/search.php.
     * @return string
     */
    public function search_courses($searchcriteria) {
        global $CFG;

        $content = $this->course_search_form($searchcriteria['search'] ?? '');
        if (empty($searchcriteria)) {
            return $content;
        }

        $displayoptions = ['sort' => ['displayname' => 1]];
        $perpage = optional_param('perpage', 0, PARAM_RAW);
        if ($perpage !== 'all') {
            $displayoptions['limit'] = ((int) $perpage <= 0) ? $CFG->coursesperpage : (int) $perpage;
            $page = optional_param('page', 0, PARAM_INT);
            $displayoptions['offset'] = $displayoptions['limit'] * $page;
        }
        // Used by coursecat_courses() only.
        $displayoptions['paginationurl'] = new url('/course/search.php', $searchcriteria);
        $displayoptions['paginationallowall'] = true;

        $class = 'course-search-result';
        foreach ($searchcriteria as $key => $value) {
            if (!empty($value)) {
                $class .= ' course-search-result-' . $key;
            }
        }
        $chelper = new coursecat_helper();
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT)
            ->set_courses_display_options($displayoptions)
            ->set_search_criteria($searchcriteria)
            ->set_attributes(['class' => $class]);

        /* Search everything, filter, then paginate - the same shape as the category
           listing, and for the same reason: core paginates this in PHP over a fully
           materialised list anyway (core_course_category::search_courses() slices with
           array_slice), so asking for all of it costs what core already pays. */
        $unpaged = $displayoptions;
        unset($unpaged['limit'], $unpaged['offset']);
        $all = access::filter_courses(core_course_category::search_courses($searchcriteria, $unpaged));
        $totalcount = count($all);
        $courses = isset($displayoptions['limit'])
            ? array_slice($all, (int) ($displayoptions['offset'] ?? 0), (int) $displayoptions['limit'], true)
            : $all;

        $courseslist = $this->coursecat_courses($chelper, $courses, $totalcount);

        if (!$totalcount) {
            if (!empty($searchcriteria['search'])) {
                $content .= $this->heading(get_string('nocoursesfound', '', $searchcriteria['search']));
            } else {
                $content .= $this->heading(get_string('novalidcourses'));
            }
        } else {
            $content .= $this->heading(get_string('searchresults') . ": $totalcount");
            $content .= $courseslist;
        }

        return $content;
    }

    /**
     * Render the frontpage list of available courses, minus the undiscoverable ones.
     *
     * The count is recomputed over the filtered set because core compares it
     * against the displayed list to decide whether to offer the "full list of
     * courses" link, and against zero to decide whether to offer the "add a
     * new course" button instead of a listing.
     *
     * @return string
     */
    public function frontpage_available_courses() {
        global $CFG;

        $chelper = new coursecat_helper();
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED)->set_courses_display_options([
            'recursive' => true,
            'limit' => $CFG->frontpagecourselimit,
            'viewmoreurl' => new url('/course/index.php'),
            'viewmoretext' => new lang_string('fulllistofcourses'),
        ]);
        $chelper->set_attributes(['class' => 'frontpage-course-list-all']);

        $options = $chelper->get_courses_display_options();
        unset($options['limit']);
        $all = access::filter_courses(core_course_category::top()->get_courses($options));
        $totalcount = count($all);
        $courses = array_slice($all, 0, (int) $CFG->frontpagecourselimit, true);

        if (
            !$totalcount && !$this->page->user_is_editing() &&
                has_capability('moodle/course:create', context_system::instance())
        ) {
            // Print link to create a new course, for the 1st available category.
            return $this->add_new_course_button();
        }
        return $this->coursecat_courses($chelper, $courses, $totalcount);
    }
}
