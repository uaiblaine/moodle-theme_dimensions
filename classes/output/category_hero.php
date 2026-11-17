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
 * Theme Dimensions - The hero of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use core_course_category;
use theme_dimensions\local\calendar\coreview;
use theme_dimensions\local\calendar\page as calendarpage;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\navgroups;
use theme_dimensions\local\category\page_result;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\category\request;
use theme_dimensions\local\landing\links;

/**
 * The page header of the category showcase: branding, counts, the category's navigation, the fold control.
 *
 * Built by the page's two entries - the course renderer's course_category(), which core calls
 * before the page header is rendered, and request::handle() for the anonymous shell - and armed
 * in a static registry the core renderer's full_header() reads while the layout renders. A hero
 * built where the listing is built knows the listing's post-filter counts at no extra statement.
 * A test that renders the showcase resets the registry.
 *
 * What the hero prints about the category comes from {@see branding} (cached across requests,
 * viewer-independent); what it prints about the viewer - the counts, the pencil, the navigation -
 * is computed per request and never cached. The crests on the title's line are the category's
 * own, from that same payload, never a course's.
 * The navigation is core's own secondary navigation, regrouped by {@see navgroups} into a row of
 * nav pills ({@see category_navpills}) that straddles the band's lower edge; the band Boost Union's
 * layout would render under the header is switched off by the core renderer while a hero is armed.
 * The trail above the title is the hero's own ({@see trail()}).
 *
 * The fold is the one thing the hero remembers per person: folded, the hero keeps its title row
 * and its navigation and drops the tagline, the description and the counts. It is written from the
 * hero's own GET form, so the page without JavaScript remembers it too, and the preference
 * belongs to the person rather than to the category - which is why the root's hero keeps it and
 * submits to /course/index.php with no category of its own.
 *
 * The root of the course index is the one category drawn without a row behind it: the system
 * context, the branding defaults (no statement) over the site-wide fallback image, no pencil (the
 * appearance editor brands one real category), no trail, and a title other than top()'s own name,
 * "Top" ({@see root_title()}). Its navigation is whatever secondary navigation core gave that page,
 * and a visitor has none.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_hero implements renderable, templatable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string The user preference holding the fold state. Declared in lib.php. */
    public const PREFERENCE_FOLDED = 'theme_dimensions_categoryherofolded';

    /** @var string The request parameter the fold form submits: 1 to fold, 0 to unfold. */
    public const PARAM_FOLD = 'herofold';

    /** @var self|null The hero armed for the page being rendered, if any. */
    private static ?self $current = null;

    /**
     * Constructor.
     *
     * @param core_course_category $category The category, already admitted by both gates.
     * @param page_result $result The resolved page: its counts feed the hero's numbers.
     * @param criteria $criteria The request: the fold form carries its filters.
     * @param bool $folded Whether the hero is folded for this viewer.
     * @param string $descriptionhtml The category's formatted description (final HTML), or ''.
     * @param bool $withtabs Whether to draw the page's secondary navigation; the anonymous shell draws none.
     * @param \moodle_page|null $page The page whose secondary navigation the row draws, the global page when null.
     */
    public function __construct(
        /** @var core_course_category The category. */
        private readonly core_course_category $category,
        /** @var page_result The resolved page. */
        private readonly page_result $result,
        /** @var criteria The request. */
        private readonly criteria $criteria,
        /** @var bool Whether the hero is folded. */
        private readonly bool $folded = false,
        /** @var string The category description as final HTML, or ''. */
        private readonly string $descriptionhtml = '',
        /** @var bool Whether to draw the page's secondary navigation. */
        private readonly bool $withtabs = true,
        /** @var \moodle_page|null The page whose secondary navigation the row draws. */
        private readonly ?\moodle_page $page = null,
    ) {
    }

    /**
     * Arm a hero for the page being rendered.
     *
     * @param self $hero The hero.
     * @return void
     */
    public static function arm(self $hero): void {
        self::$current = $hero;
    }

    /**
     * The hero armed for the page being rendered, or null.
     *
     * @return self|null
     */
    public static function current(): ?self {
        return self::$current;
    }

    /**
     * Disarm the registry.
     *
     * @return void
     */
    public static function reset(): void {
        self::$current = null;
    }

    /**
     * The category this hero is about.
     *
     * @return int
     */
    public function category_id(): int {
        return (int) $this->category->id;
    }

    /**
     * Whether this hero paints a picture: the page's largest paint, which is then not one of the listing's covers.
     *
     * The same answer {@see export_for_template()} draws `hasimage` from, read from the same cached branding
     * payload: the listing asks it to decide which image of the first paint is fetched first
     * ({@see category_listing::priority()}).
     *
     * @return bool
     */
    public function draws_image(): bool {
        $categoryid = (int) $this->category->id;
        return self::image_address($categoryid, branding::for_category($categoryid)) !== null;
    }

    /**
     * The address of the picture behind the band, or null when the layout paints none or has none to paint.
     *
     * @param int $categoryid The category, 0 for the root.
     * @param array $branding The payload of {@see branding::for_category()}.
     * @return string|null
     */
    private static function image_address(int $categoryid, array $branding): ?string {
        if ($branding['layout'] !== branding::LAYOUT_IMAGE) {
            return null;
        }
        if ($branding['image'] !== null) {
            return branding::image_url($categoryid, $branding['image'])->out(false);
        }
        return branding::fallback_url();
    }

    /**
     * The fold state to draw: what the request asked for, else what the person chose before, else open.
     *
     * A requested state is remembered for a logged-in person who is not the guest account;
     * anybody else gets it for this page only.
     *
     * @param bool|null $requested The fold parameter of the request, null for none.
     * @return bool Whether the hero is folded.
     */
    public static function fold(?bool $requested): bool {
        $remembers = isloggedin() && !isguestuser();
        if ($requested !== null) {
            if ($remembers) {
                set_user_preference(self::PREFERENCE_FOLDED, $requested ? 1 : 0);
            }
            return $requested;
        }
        return $remembers && (bool) get_user_preferences(self::PREFERENCE_FOLDED, 0);
    }

    /**
     * The hero's one inline style attribute: the custom properties the band paints itself from.
     *
     * This is a CSS-context sink, which Mustache's HTML escaping does not protect, so the guard is
     * the vocabulary: every value comes out of {@see branding::stops()}, {@see branding::rgba()} or
     * {@see branding::hex()}, each of which parses its input into integer channels and prints them
     * back, so the string can only match ^(--dim-hero-[a-z-]+:\s*(#[0-9a-f]{6}|rgba\([0-9, .]+\));\s*)+$
     * (asserted by category_hero_test on a real export and on a poisoned row).
     *
     * The stops turn translucent under a photograph, at the overlay the manager chose; the strip
     * stays the opaque mid colour at every layout, though no rule of the stylesheet paints with it
     * (the row of nav pills is on the page's own surface): it is part of the vocabulary the tests pin.
     * The ring is the ink, not white: a white focus ring on a pale band is invisible.
     *
     * @param array $branding The payload of {@see branding::for_category()}.
     * @param string $layout The layout actually drawn, which is not the stored one when an image
     *   layout has no image to paint.
     * @return string A declaration list for a style attribute.
     */
    public static function style(array $branding, string $layout): string {
        $bg = branding::hex((string) $branding['bgcolour']) ?? branding::DEFAULT_BG;
        $ink = branding::hex((string) $branding['inkcolour']) ?? branding::DEFAULT_INK;
        $stops = branding::stops($bg);
        $isimage = ($layout === branding::LAYOUT_IMAGE);
        $alpha = ((int) $branding['overlay']) / 100;
        $declarations = [
            '--dim-hero-from' => $isimage ? branding::rgba($stops['from'], $alpha) : $stops['from'],
            '--dim-hero-mid' => $isimage ? branding::rgba($stops['mid'], $alpha) : $stops['mid'],
            '--dim-hero-to' => $isimage ? branding::rgba($stops['to'], $alpha) : $stops['to'],
            '--dim-hero-strip' => $stops['mid'],
            '--dim-hero-ink' => $ink,
            '--dim-hero-ink-muted' => branding::rgba($ink, $isimage ? 0.92 : 0.86),
            '--dim-hero-ring' => $ink,
        ];
        $style = [];
        foreach ($declarations as $property => $value) {
            $style[] = $property . ': ' . $value . ';';
        }
        return implode(' ', $style);
    }

    /**
     * Export the hero context.
     *
     * @param renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(renderer_base $output): \stdClass {
        $categoryid = (int) $this->category->id;
        $isroot = $categoryid === 0;
        /* The root of the course index has no context of its own: coursecat::instance(0) does
           not exist, and the page core builds for it sets the system context, so every capability
           below is asked there. */
        $context = $isroot ? \core\context\system::instance() : \core\context\coursecat::instance($categoryid);
        $branding = branding::for_category($categoryid);

        $layout = $branding['layout'];
        $imageurl = self::image_address($categoryid, $branding);
        if ($layout === branding::LAYOUT_IMAGE && $imageurl === null) {
            // Nothing to paint behind the overlay: the solid band, in the same colour.
            $layout = branding::LAYOUT_SOLID;
        }
        $compact = ($layout === branding::LAYOUT_COMPACT) || $this->folded;

        /* The layout, and nothing about the colour: style() bakes the chosen overlay percent into
           the alpha of the three stops, so no class names it. */
        $classes = ['dim-hero--' . $layout];
        // The compact layout's own class already says so; a folded hero of another layout adds it.
        if ($compact && $layout !== branding::LAYOUT_COMPACT) {
            $classes[] = 'dim-hero--compact';
        }
        if ($this->folded) {
            $classes[] = 'dim-hero--folded';
        }
        // The class keeps its name: a hero without a row of navigation ends plainly, its chamfer on the band.
        $navpills = $this->navpills();
        if ($navpills === null) {
            $classes[] = 'dim-hero--notabs';
        }

        /* No pencil at the root: the appearance editor brands one category, and the
           pseudo-category has no row to brand. Its hero is the default band. */
        $pencil = null;
        if (!$isroot && has_capability('moodle/category:manage', $context)) {
            $pencil = [
                'url' => (new \core\url('/theme/dimensions/categoryhero.php', ['id' => $categoryid]))->out(false),
                'label' => get_string('categoryhero_pencil', self::COMPONENT),
            ];
        }
        /* The category's own crests, nothing inherited: a category that has none shows none,
           however its ancestors are branded. The colour pair is the one thing a category inherits
           (branding::colours()), already resolved in the payload. A compact or folded band keeps
           the crests, smaller, on the title's line; the size is the stylesheet's alone, and the
           context is the same in both bands. */
        $badges = branding::badges_for_output($categoryid, $branding['badges']);
        $stats = $this->stats($context);
        $fold = ($layout === branding::LAYOUT_COMPACT) ? null : $this->fold_form();
        $calendar = $this->calendar_link();
        $trail = $this->trail();
        $style = self::style($branding, $layout);

        return (object) [
            'categoryid' => $categoryid,
            // The root's own formatted name is the word "Top", which names nothing: see root_title().
            'title' => $isroot ? $this->root_title() : $this->category->get_formatted_name(['escape' => false]),
            'classes' => implode(' ', $classes),
            'style' => $style,
            'hasimage' => $imageurl !== null,
            'imageurl' => $imageurl,
            'trail' => $trail,
            'hastrail' => !empty($trail),
            'traillabel' => get_string('breadcrumb', 'access'),
            'haspencil' => $pencil !== null,
            'pencil' => $pencil,
            'compact' => $compact,
            'tagline' => $branding['tagline'],
            'hastagline' => $branding['tagline'] !== '',
            'descriptionhtml' => $this->descriptionhtml,
            'hasdescription' => trim($this->descriptionhtml) !== '',
            'badges' => $badges,
            'hasbadges' => !empty($badges),
            'badgeslabel' => get_string('category_badges', self::COMPONENT),
            'stats' => $stats,
            'hasstats' => !empty($stats),
            'fold' => $fold,
            'hasfold' => $fold !== null,
            'calendar' => $calendar,
            'hascalendar' => $calendar !== null,
            'navpills' => $navpills?->export_for_template($output),
            'hasnavpills' => $navpills !== null,
        ];
    }

    /**
     * The visitor's way to the public calendar or null.
     *
     * Drawn for a visitor alone, on a page the anonymous shell serves, while the calendar page would serve that visitor:
     * the public calendar is on ({@see visitor::enabled()}) and the calendar type is the Gregorian
     * ({@see coreview::gregorian()}), the two refusals page::handle() makes before it draws a visitor anything. A
     * logged-in reader has the drawer's calendar row and gets none.
     *
     * @return array|null ['url', 'label'].
     */
    private function calendar_link(): ?array {
        if (!$this->result->anonymous || !visitor::enabled() || !coreview::gregorian()) {
            return null;
        }
        return [
            'url' => calendarpage::url()->out(false),
            'label' => get_string('calendar_visitorlink', self::COMPONENT),
        ];
    }

    /**
     * The page's secondary navigation as a row of nav pills, or null when the hero draws none.
     *
     * Three refusals: the anonymous shell (a visitor has no secondary navigation, and the shell
     * says so through $withtabs), a page without a secondary navigation, and a viewer whose only
     * destination is the page itself (a learner): a pill is never alone on a row
     * ({@see navgroups::has_row()}). The nodes are read, never changed: the same tree feeds
     * other readers of the page.
     *
     * @return category_navpills|null
     */
    private function navpills(): ?category_navpills {
        global $PAGE;

        $page = $this->page ?? $PAGE;
        if (!$this->withtabs || !$page->has_secondary_navigation()) {
            return null;
        }
        $units = navgroups::rows($page->secondarynav);
        if (!navgroups::has_row($units)) {
            return null;
        }
        return new category_navpills($units);
    }

    /**
     * What names the root of the course index, which has no name of its own.
     *
     * The trail is empty at the root in both shells (see {@see trail()}), so this title is the
     * whole of what names the page: "Courses" over core's course index for a logged-in reader,
     * and the site's full name on the anonymous directory, which has no course index above it to
     * be named after. The string comes from
     * {@see request::site_name()}, which also titles the document, so the two cannot drift apart.
     *
     * @return string The title, plain spelling.
     */
    private function root_title(): string {
        return $this->result->anonymous ? request::site_name() : get_string('courses');
    }

    /**
     * The trail above the title: the category's ancestors, root first, each a link to its own page.
     *
     * Built from the category's path, not from core's breadcrumb, so that one rule names the ancestors on both shells:
     * core's breadcrumb knows nothing of which categories are public, and the hero replaces the header that would
     * print it. A logged-in viewer gets the course index as the root and then every ancestor core lets
     * them see. A visitor of the public shell gets the ancestors that are themselves public and
     * nothing else, not even a name (a category is on the internet when, and only when, its own
     * state says so), and no root, because the visitor has no course index.
     *
     * @return array Entries of ['name' => plain string, 'url' => string].
     */
    private function trail(): array {
        $anonymous = $this->result->anonymous;
        if ((int) $this->category->id === 0) {
            // The root is the course index: its trail would name only the page it is on.
            return [];
        }
        $trail = [];
        if (!$anonymous) {
            $trail[] = ['name' => get_string('courses'), 'url' => (new \core\url('/course/index.php'))->out(false)];
        }
        foreach ($this->category->get_parents() as $parentid) {
            $parentid = (int) $parentid;
            if ($anonymous) {
                if (!publicaccess::is_public($parentid)) {
                    continue;
                }
                $parent = core_course_category::get($parentid, IGNORE_MISSING, true);
            } else {
                $parent = core_course_category::get($parentid, IGNORE_MISSING);
            }
            if ($parent === null) {
                continue;
            }
            $trail[] = [
                'name' => $parent->get_formatted_name(['escape' => false]),
                'url' => links::showcase($parentid, [], $anonymous)->out(false),
            ];
        }
        return $trail;
    }

    /**
     * The numbers of the panel, every one post-filter: the viewer's enrolments, the hidden courses, and at the root the total.
     *
     * A category page carries no total: the filter bar below the hero already shows the list's
     * count, and a level shows one count. The root of the course index keeps it, because its
     * toolbar is the search line alone and the hero's number is the only one on that page.
     * A category page of a viewer with no enrolment and no hidden course has no entry, and no
     * panel is drawn.
     *
     * The enrolment count is offered when the viewer has one. The hidden count is a staff
     * number about the category and is shown behind the capability that names it, in the
     * category's context: the population admits a hidden course on that capability in the
     * course's context, which a teacher holds in their own hidden course, and "hidden from
     * students" is not a line for that teacher's hero. In the SQL-cut mode (categorystatesort
     * off) the population holds the total alone.
     *
     * @param \core\context $context The category's context.
     * @return array Entries of ['value' => int, 'label' => string].
     */
    private function stats(\core\context $context): array {
        $counts = $this->result->counts;
        $stats = [];
        if ((int) $this->category->id === 0) {
            $stats[] = [
                'value' => (int) ($counts['total'] ?? $this->result->total),
                'label' => get_string('categoryhero_stat_total', self::COMPONENT),
            ];
        }
        if (!$this->result->anonymous && (int) ($counts['enrolled'] ?? 0) > 0) {
            $stats[] = [
                'value' => (int) $counts['enrolled'],
                'label' => get_string('categoryhero_stat_enrolled', self::COMPONENT),
            ];
        }
        if ($this->result->hidden > 0 && has_capability('moodle/course:viewhiddencourses', $context)) {
            $stats[] = [
                'value' => $this->result->hidden,
                'label' => get_string('categoryhero_stat_hidden', self::COMPONENT),
            ];
        }
        return $stats;
    }

    /**
     * The fold control: a GET form to the page's own address, carrying the request's filters.
     *
     * The address and the field carrying the category come from {@see links::showcase_form()},
     * the same source the toolbar's form takes them from, so the two forms of one page always
     * post to the shell that drew them. The open panel travels with the filters (folding the
     * hero must not close it), and its two fields carry a region each, because the
     * panel module writes the panel into the address after this form was drawn and keeps the
     * fields in step with it.
     *
     * @return array
     */
    private function fold_form(): array {
        $form = links::showcase_form((int) $this->category->id, $this->result->anonymous);
        $params = array_map(static fn(array $field): array => $field + ['region' => ''], $form['hidden']);
        foreach ($this->criteria->query_params() as $name => $value) {
            $field = ['name' => $name, 'value' => $value, 'region' => ''];
            if ($name === criteria::PARAM_OPEN) {
                $field['region'] = 'dim-open-field';
            } else if ($name === criteria::PARAM_PSORT) {
                $field['region'] = 'dim-psort-field';
            }
            $params[] = $field;
        }
        return [
            'action' => $form['action'],
            'name' => self::PARAM_FOLD,
            'value' => $this->folded ? 0 : 1,
            'label' => get_string($this->folded ? 'categoryhero_unfold' : 'categoryhero_fold', self::COMPONENT),
            'icon' => $this->folded ? 'fa-chevron-down' : 'fa-chevron-up',
            'params' => $params,
        ];
    }
}
