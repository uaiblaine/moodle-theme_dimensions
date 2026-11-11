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
 * Only a theme can override a core renderer (theme_config::renderer_prefixes() returns the
 * theme_<name> prefixes of the theme and its parents; Moodle has no db/renderers.php), so the
 * decision lives in local_unlistedcourses and only the rendering lives here.
 *
 * Courses, in two layers:
 *
 * 1. coursecat_courses() filters whatever it is handed. Almost every listing
 *    surface in the course renderer funnels through it - the category page,
 *    the frontpage, search results, the exclusive tagged-courses page and the
 *    AJAX category expansion - so it catches the surfaces not enumerated below,
 *    and any that core adds later. It cannot fix the paging bar, because the
 *    $totalcount it receives was counted by somebody else.
 *
 * 2. coursecat_category_content(), search_courses(), tagged_courses() and
 *    frontpage_available_courses() fetch, filter and paginate themselves, so a
 *    printed count or paging bar agrees with what is shown: otherwise a viewer
 *    who can see two of four courses is offered a second page that renders
 *    empty, and a search that withheld one result still says how many there were.
 *
 * Categories, the same two layers over category_access:
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
 * Fetching unpaginated costs what core already pays: core_course_category::get_courses(),
 * search_courses() and get_children() load and sort the whole list, cache the ids in the
 * coursecat cache and only then array_slice() in PHP.
 *
 * This extends Boost Union's renderer rather than core's, so its card and
 * list-of-boxes presentations (courselistingpresentation,
 * categorylistingpresentation) keep working. Those presentations name a course's
 * category from their own helper and never reach core's "Category: name" line, so
 * coursecat_coursebox_content() repeats the category guard over the badge they print.
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
     * The root of the course index (category 0) is served too, and as a
     * directory rather than a listing: the hero, the search line, then a
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
        /* The view first: a view named in the address is remembered for this category
           (category_listing::view()), and the request below then knows the reader has a state here.
           The root reads its term and its open panel and no filter (criteria::root_from_request()):
           a hand-typed filter would otherwise change every poster's number and ride along in the
           hero's fold form. */
        $view = category_listing::view(optional_param(category_toolbar::PARAM_VIEW, '', PARAM_ALPHA), $categoryid);
        $criteria = $isroot
            ? criteria::root_from_request()
            : criteria::from_request($categoryid, array_keys($fields));
        $result = population::resolve($criteria);
        $folded = category_hero::fold(optional_param(category_hero::PARAM_FOLD, null, PARAM_BOOL));

        // Plain: set_title() formats and escapes for itself. top()'s own name is the word "Top".
        $this->page->set_title($isroot ? get_string('courses') : $coursecat->get_formatted_name(['escape' => false]));
        $this->page->add_body_class(self::BODY_CLASS);

        /* The root has no manage menu: category_actions builds the menu of one category, and the
           pseudo-category is not one. */
        $manageactions = $isroot ? '' : (new category_actions($this->page, $coursecat))->render_actions($this);
        // The pages strip is built by the page's two entries, never by the listing's export: see category_pages.
        $pages = category_pages::export($categoryid);
        $listing = new category_listing($coursecat, $criteria, $result, $view, $manageactions, $pages, true);

        /* The account's client is armed here rather than in the listing, because the anonymous
           shell draws the same listing with its own client (request.php arms listingvisitor).
           Not for the guest account, which core counts as logged in: every service this client
           calls refuses the guest, so an armed module would hide the no-JavaScript "Filter" submit
           and the numbered pager - the guest's only working controls - and answer every click with
           an exception dialogue. The card's star follows the same rule (showstar). Keep the guest
           out here; never loosen the services' refusal instead. */
        if (isloggedin() && !isguestuser()) {
            /* The root included: its search line and its posters' panels are live as on a category page
               (js/esm/src/listing/account.ts). */
            $this->page->requires->js_call_amd('theme_dimensions/listingaccount', 'init');
            /* The course dialogue's module, under the same rule: it fetches a course's content from a service
               that refuses the guest. Without it "More information" is the link to the card's own page, which
               is what the guest and the visitor follow. */
            $this->page->requires->js_call_amd('theme_dimensions/categorycoursemodal', 'init');
        }

        /* The cover film's module is armed for everybody, the root included (its panels draw
           cards too): it calls no web service (it imports core/pending alone) and hides no control,
           its body class only reveals the film. request.php arms it for the anonymous shell. */
        $this->page->requires->js_call_amd('theme_dimensions/covervideo', 'init');

        // The hero is drawn by the core renderer's full_header(), which core calls after this
        // method returns; it is armed here because this is where the counts are known.
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
            /* The root of the course index: core's course/index.php hands 0 over through
               user_top() unless the viewer may see exactly one top-level category, so core's own
               question is already answered. A visitor and the guest account get core's page;
               the anonymous root, when the site opens it, is served by request::handle(). */
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
     * what this page dropped, which is exact for the surfaces overridden below
     * (their lists arrive already filtered, so the reduction is zero) and a best
     * effort for any other caller: a global total cannot be corrected from one
     * page of it. Names never leak either way; only a count could.
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
     * The tree-wide twin of the expand affordance {@see coursecat_category()} strips from a node.
     * Core's coursecat_tree() and Boost Union's copy of it print the "Expand all" / "Collapse all"
     * control whenever the raw get_children_count() of the tree's category is non-zero, so a
     * category whose children are all unlisted would keep the control over a tree with nothing to
     * expand - an existence signal. The tree's contents are already filtered by
     * coursecat_subcategories().
     *
     * Both upstreams write the control as one self-contained div.collapsible-actions holding only
     * the link, so removing it from the rendered string is unambiguous and costs no copy of either
     * method. Re-check that shape on every core or Boost Union update.
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
     * A copy of core_course_renderer::coursecat_subcategories(), which Boost Union does not
     * override; it keeps core's html_writer calls because it is core's method. Its snapshot is
     * course_renderer.php.coursecat_subcategories.upstream ({@see \theme_dimensions\local\upstream}):
     * bring the copy back to parity when core changes it.
     *
     * Modifications:
     * - The children are fetched unpaginated, run through category_access::filter_categories(),
     *   counted, and only then sliced: filtering after core paginated would overstate the total
     *   and leave pages short or empty.
     * - Core reads the total from get_children_count() and skips the fetch under 'nodisplay'; a
     *   filtered total needs the fetch, so 'nodisplay' only empties the rendered list. That keeps
     *   course_category()'s "View all subcategories" link on its browse=courses branch, which sets
     *   nodisplay.
     * - $pagingbar and $morelink start as '' and are tested against '' where core leaves them
     *   undefined and tests empty(); the two agree in every case.
     *
     * Every surface that lists categories reaches the children through it: the category page
     * tree, coursecat_tree() and both front page category methods.
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
     * The node of an undiscoverable category is not rendered at all, subtree included, which
     * catches a category handed over from anywhere but the filtered listing above. The top-level
     * pseudo-category (id 0) is always discoverable, while is_category_discoverable(0) would fail
     * closed, so the id is tested first.
     *
     * Core's not-loaded branch, and Boost Union's copy of it, decide the with_children and
     * collapsed classes from the raw get_children_count() and, at COURSECAT_SHOW_COURSES_COLLAPSED or
     * above, the raw get_courses_count(), so a category whose children or courses are all withheld
     * would keep an expand arrow opening onto nothing - an existence signal. When the filtered child
     * count is zero and, at COURSECAT_SHOW_COURSES_COLLAPSED or above, the filtered course count is
     * zero too, the two classes are removed from the node's opening tag. Both upstreams write the class
     * attribute first in that tag, so one replacement of the first class attribute is unambiguous.
     *
     * The number printed in COUNT mode (" (N)" in core's front page category list, a bare "N"
     * badge under Boost Union's categorylistingpresentation) is rewritten to the filtered direct
     * courses, and the span is dropped at zero as core drops it. The count is taken once per node,
     * only when one of those two branches needs it. The node's own span is the one inside its first
     * name heading: a child's span sits further down and was rewritten when that child rendered.
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
     * The guard covers course/category.ajax.php, whose coursecat_ajax() calls straight into this
     * method with a category id from the request: the after_config guard returns early for
     * AJAX_SCRIPT, and the node override above is never reached because the request asks for the
     * contents of a category, not its node. The id 0 is tested first, as in coursecat_category().
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
     * Defence in depth: access::filter_courses() already drops a course inside an unlisted category
     * for everyone but those related to the course itself (enrolled, awaiting a decision, or staff),
     * who know its category already. This keeps any surface rendering such a course box from naming
     * the category as a side effect.
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
     * The rule of {@see course_category_name()}, on Boost Union's card and list presentations: with
     * courselistingpresentation set to anything but "nochange", coursecat_coursebox_content() takes
     * its own branch and, while courselistinghowcategory is on, names the category through
     * theme_boost_union\util\course::get_category(), which asks core_course_category::get() and no
     * predicate. course_category_name() is never reached there.
     *
     * Both of Boost Union's templates (courselistingcard, courselistinglist) wrap the value in a
     * span.categoryname.text-truncate, and nothing else in a course box carries that class (core's
     * own category line is a div.coursecat), so emptying it is one unambiguous replacement and
     * costs no copy of the upstream method. Only the name goes: the reader was rescued into this
     * listing by access::filter_courses() through their own relationship with the course.
     *
     * Boost Union's sticky category headers need no guard: they print only while
     * get_show_courses() <= COURSECAT_SHOW_COURSES_COLLAPSED, and every listing that mixes
     * categories asks for more (search_courses(), frontpage_available_courses() and
     * tagged_courses() set EXPANDED_WITH_CAT or EXPANDED). In the per-category tree a header can
     * only name the category whose node and content are withheld above. Re-check on each Boost
     * Union release.
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
            // The category guard of course_category_name(), for the same reason.
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
     * obtained separately through search_courses_count(), which would announce
     * that a withheld course exists.
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

        // Search everything, filter, then paginate: see the class docblock for the cost.
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
