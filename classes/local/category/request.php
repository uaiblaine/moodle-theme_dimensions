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
 * Theme Dimensions - One request for a category's public page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use theme_dimensions\local\landing\links;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\category_pages;
use theme_dimensions\output\category_toolbar;
use theme_dimensions\output\opengraph;

/**
 * One request for a category's public page, decided once for both entry points.
 *
 * The anonymous shell of the category showcase. The page answers from two addresses: the
 * route (route\controller\category, the canonical while the site's router is configured) and
 * the script (category.php, the canonical otherwise, a redirect to the route the rest of the
 * time). Both call {@see handle()}, which runs the whole decision in one order and answers
 * either a redirect target or a listing ready to render; each entry point only translates
 * that answer into its own way of redirecting and writing.
 *
 * The order is a security property and moves as one block:
 *
 * 1. A logged-in viewer, the guest account included (core counts it as logged in), is sent
 *    to the showcase before anything else is asked, so each viewer class has one surface: a
 *    visitor gets the public page, everybody else /course/index.php and the shell their
 *    rights earn them.
 * 2. A visitor is refused before the category is looked up unless it is public: one
 *    refusal, the login page, for a category that is listed, unlisted, hidden, not there at
 *    all or behind a switched-off surface, so an anonymous client cannot tell which ids
 *    exist. wantsurl brings somebody entitled back here after logging in, where step 1 then
 *    forwards them to the showcase.
 * 3. Only then the lookup, the page wiring and the population. {@see population::scope()}
 *    throws a coding_exception if an anonymous scope is built in a session logged in as a
 *    real account, which is why step 1 precedes it.
 *
 * No require_login() anywhere: forcelogin is not an ambient gate but a set of explicit reads
 * in core, none of which is made here, and this page serves people who are not logged in at
 * all by design. The category is read with alwaysreturnhidden, because the visibility half
 * of the question was already answered inside {@see publicaccess::is_public()} and user 0
 * holds no capability under forcelogin.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /** @var string The sentence the visitor's live listing shows when its web service cannot answer. */
    public const STRING_FALLBACK = 'category_publicfallback';

    /**
     * Constructor: exactly one of the two answers.
     *
     * @param \core\url|null $redirect Where to send the client, when the answer is a redirect.
     * @param category_listing|null $page The listing to render, when the answer is the public page.
     */
    private function __construct(
        /** @var \core\url|null Where to send the client, when the answer is a redirect. */
        public readonly ?\core\url $redirect,
        /** @var category_listing|null The listing to render, when the answer is the public page. */
        public readonly ?category_listing $page,
    ) {
    }

    /**
     * Decide a request for a category's public page.
     *
     * Sets up $PAGE, arms the hero and, for a category that is really public, the head tags;
     * the caller renders the header right after.
     *
     * @param int $categoryid The category id from the request.
     * @return self A redirect target or a listing.
     * @throws \moodle_exception When a category the predicate held public has gone between the two reads.
     */
    public static function handle(int $categoryid): self {
        global $PAGE, $SESSION, $SITE;

        $pageurl = links::category_page($categoryid);

        /* One surface per viewer class: the guest account counts as logged in here and is sent
           to the logged-in showcase. */
        if (isloggedin()) {
            return self::redirect_to(links::showcase($categoryid, [], false));
        }

        // The visitor's one refusal, before any lookup: every id that is not public answers alike.
        if (!publicaccess::is_public($categoryid)) {
            $SESSION->wantsurl = $pageurl->out(false);
            return self::redirect_to(new \core\url(get_login_url()));
        }

        $isroot = $categoryid === 0;
        /* core_course_category::get(0) answers top() rather than throwing, so the root walks the
           same lookup as any other category; its CONTEXT is the one thing it cannot have. */
        $category = \core_course_category::get($categoryid, MUST_EXIST, true);
        $context = $isroot
            ? \core\context\system::instance()
            : \core\context\coursecat::instance($categoryid);

        if ($isroot) {
            /* $PAGE->set_category_by_id(0) throws: load_category() does a bare get_record() on
               category 0 and raises unknowncategory, and there is no coursecat context for 0
               either. The system context is what course/index.php sets for its root, and Boost
               Union's flavour resolver reads $PAGE->category with a fallback of 0, so nothing
               downstream is lost. */
            $PAGE->set_context($context);
        } else {
            /* set_category_by_id() first: it throws once a course or the categories are set. */
            $PAGE->set_category_by_id($categoryid);
        }
        // The page type is spelled out because under the router core would derive it from r.php.
        $PAGE->set_pagetype('theme-dimensions-category');
        $PAGE->set_pagelayout('coursecategory');
        $PAGE->set_secondary_navigation(false);
        $PAGE->set_url($pageurl);
        /* Plain: set_title() formats and escapes for itself. top()'s own formatted name is the
           literal word "Top", so the root is titled with the site's name. */
        $PAGE->set_title($isroot ? self::site_name() : $category->get_formatted_name(['escape' => false]));
        $PAGE->set_heading('');
        $PAGE->add_body_class(category_listing::BODY_CLASS);
        $PAGE->add_body_class('dim-category-public');
        $PAGE->add_body_class('limitedwidth');

        $fields = facets::fields();
        /* The root honours its search and no filter (population::resolve_root()) and draws no
           control that could clear a filter, so, as on the logged-in root, it reads its term and
           its open panel off the address and nothing else: a filter read here would narrow the
           root's panels and ride the hero's fold form with nothing on the page admitting it. A
           visitor's search is a submission of the search line's form, or live where the site has
           opened the visitor's web service (listingvisitor, armed below). */
        $criteria = $isroot
            ? criteria::root_from_request(true)
            : criteria::from_request($categoryid, array_keys($fields), true);
        try {
            $result = population::resolve($criteria);
        } catch (unavailable_exception $exception) {
            // The same refusal as the visitor's, for a category the population will not draw.
            $SESSION->wantsurl = $pageurl->out(false);
            return self::redirect_to(new \core\url(get_login_url()));
        }

        $view = category_listing::view(optional_param(category_toolbar::PARAM_VIEW, '', PARAM_ALPHA));
        $folded = category_hero::fold(optional_param(category_hero::PARAM_FOLD, null, PARAM_BOOL));

        /* The public description, never the category's own description field: that one is
           written for the staff who manage the category, and this page is the open internet.
           The root has no branding row to hold one, so its hero reads the site summary, the one
           text about the whole site written for readers rather than for staff. */
        $branding = branding::for_category($categoryid);
        if ($isroot) {
            $description = format_text($SITE->summary ?? '', $SITE->summaryformat ?? FORMAT_HTML, ['context' => $context]);
        } else {
            $description = format_text(
                $branding['publicdescription'],
                $branding['publicdescriptionformat'],
                ['context' => $context]
            );
        }

        // The pages strip, as the logged-in shell builds it: local_page answers what a visitor may read.
        $listing = new category_listing($category, $criteria, $result, $view, '', category_pages::export($categoryid), true);

        /* The hero is drawn by the core renderer's full_header(), which runs after this; no tabs,
           because a visitor has no secondary navigation, and the pencil suppresses itself - user 0
           holds no capability. */
        category_hero::reset();
        category_hero::arm(new category_hero($category, $result, $criteria, $folded, $description, false, $PAGE));

        /* Registered last, right before the render: the head hook fires from the error page as
           well, and a throw above would otherwise put this category's tags on it. The root is
           never handed to for_category(): top() carries no context and no branding row, so
           for_root() reads the site instead, with no og:image. */
        opengraph::set($isroot ? opengraph::for_root($pageurl) : opengraph::for_category($category, $branding, $pageurl));

        /* The cover film's module imports nothing and calls no web service. A visitor's category
           cards carry cover films like any other, and their controls stay hidden until this module
           marks the body; without it a visitor sees the still alone. */
        $PAGE->requires->js_call_amd('theme_dimensions/covervideo', 'init');

        /* No course dialogue for a visitor (decision V1 of docs/vitrine-ratings/PROPOSAL.md): its content is
           fetched from a service a session alone may call, and a visitor's "More information" is the link to
           the course's public landing page, which already says everything about it. */

        /* The visitor's live listing, only while the site has opened its web service: off, the page
           is the plain form it always was. Every session left at step 1, so this page is a visitor's
           by now and the switch is the whole condition. The module shows its one sentence of its own
           when the service cannot answer, so the sentence travels with the page rather than over a
           network that may be what failed. */
        if (publicaccess::client_enabled()) {
            $PAGE->requires->strings_for_js([self::STRING_FALLBACK], 'theme_dimensions');
            $PAGE->requires->js_call_amd('theme_dimensions/listingvisitor', 'init', [$categoryid]);
        }

        return self::render($listing);
    }

    /**
     * The site's full name in the plain spelling, formatted in the system context.
     *
     * The root's own name, on a page that has no category to take one from. Public so that the
     * hero can print exactly what the title carries: two spellings of one name is how the two
     * drift apart.
     *
     * @return string The site's full name, unescaped.
     */
    public static function site_name(): string {
        global $SITE;

        return format_string($SITE->fullname, true, [
            'context' => \core\context\system::instance(),
            'escape' => false,
        ]);
    }

    /**
     * The showcase parameters of the current request that a forward must carry over.
     *
     * A visitor who typed /course/index.php?categoryid=N with a search and a page in the query
     * string keeps both when the after_config callback forwards them here, and so does a reader
     * whom category.php redirects to the route. The page is added only when it is not the first
     * one, because {@see criteria::query_params()} leaves it out on purpose: a filter change
     * starts over at the first page.
     *
     * @param int $categoryid The category id from the request.
     * @return array name => value.
     */
    public static function forwarded_params(int $categoryid): array {
        $criteria = criteria::from_request($categoryid, array_keys(facets::fields()), true);
        $params = $criteria->query_params();
        $page = optional_param(criteria::PARAM_PAGE, 0, PARAM_INT);
        if ($page > 0) {
            $params[criteria::PARAM_PAGE] = $page;
        }
        // The two the page itself mints: the view toggle and the hero's fold, when they were asked for.
        $view = optional_param(category_toolbar::PARAM_VIEW, '', PARAM_ALPHA);
        if ($view !== '') {
            $params[category_toolbar::PARAM_VIEW] = $view;
        }
        $fold = optional_param(category_hero::PARAM_FOLD, null, PARAM_BOOL);
        if ($fold !== null) {
            $params[category_hero::PARAM_FOLD] = $fold ? 1 : 0;
        }
        return $params;
    }

    /**
     * The answer "send the client there".
     *
     * @param \core\url $url The target.
     * @return self The answer.
     */
    private static function redirect_to(\core\url $url): self {
        return new self($url, null);
    }

    /**
     * The answer "render this".
     *
     * @param category_listing $page The listing.
     * @return self The answer.
     */
    private static function render(category_listing $page): self {
        return new self(null, $page);
    }
}
