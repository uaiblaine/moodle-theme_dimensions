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
 * Theme Dimensions - Core renderer
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\calendar\blocks;
use theme_dimensions\local\card\boost_union;
use theme_dimensions\local\card\cover;
use theme_dimensions\local\calendar\coreview;
use theme_dimensions\local\compactnav;
use theme_dimensions\local\coursecategory;
use theme_dimensions\local\coursenotice\builder as coursenotice_builder;
use theme_dimensions\local\darklogo;
use theme_dimensions\local\drawernav;
use theme_dimensions\local\flavourchoice;
use theme_dimensions\local\guestnav;
use theme_dimensions\local\identity;
use theme_dimensions\local\phonenav;
use theme_dimensions\local\profilelayer;
use theme_dimensions\local\readingbar;

/**
 * Core renderer of this theme, extending the one of Boost Union.
 *
 * The renderer factory resolves 'theme_' . $theme->name . '\output\core_renderer' before it looks at
 * the parent themes, so this class is picked up as soon as this theme is the active one.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost_union\output\core_renderer {
    /**
     * @var string[] The layout templates that print Boost Union's course related hints, under the key
     *      {@see self::HINTS_KEY}: BU/templates/theme_boost/drawers.mustache and columns2.mustache.
     */
    public const HINT_LAYOUTS = ['theme_boost/drawers', 'theme_boost/columns2'];

    /** @var string The layout context key Boost Union's layout/includes/courserelatedhints.php fills. */
    public const HINTS_KEY = 'courserelatedhints';

    /**
     * @var string The brand's classes in Boost Union's navbar partial (BU/templates/theme_boost/navbar.mustache, included
     *      by both layouts of {@see self::HINT_LAYOUTS}): hidden below lg, where only the hamburger is drawn.
     */
    public const BRAND_HIDDEN = 'class="navbar-brand d-none d-lg-flex ';

    /** @var string The same brand shown at every width, beside the hamburger on a phone. */
    public const BRAND_SHOWN = 'class="navbar-brand d-flex ';

    /**
     * @var array|null What focus mode adds to the page being rendered, {@see focusmode::shell()}'s answer.
     *
     * Set while the drawers layout renders on a page that can switch in place, and read by the three
     * methods its template calls afterwards in the same render: standard_top_of_body_html() prints the
     * bar, full_header() leaves out the header actions the bar took, activity_navigation() prints the
     * completion line and the previous and next row. Null on every other page, where all three are the
     * parent's.
     */
    protected ?array $focusshell = null;

    /**
     * @var array|null The profile layer's template data for the page being rendered, {@see profilelayer::context()}.
     *
     * Set while a layout carrying a logged-in account's user menu renders and {@see profilelayer::applies()}, and
     * read once, by {@see self::standard_after_main_region_html()}, which the same layout calls later in the same
     * render. Null on every other page, and again once printed.
     */
    protected ?array $profilelayer = null;

    /** @var bool Whether {@see self::look_menu()} has asked flavourchoice for this page's Look yet. */
    private bool $lookasked = false;

    /** @var array|null flavourchoice::menu()'s answer for this page, once asked. */
    private ?array $lookmenu = null;

    /** @var bool Whether {@see boost_union_fallback()} has asked Boost Union for its fallback image yet. */
    private bool $fallbackasked = false;

    /** @var string|null Boost Union's answer while its fallback image is chosen and exists, else null. */
    private ?string $fallbackanswer = null;

    /** @var string|null The primary a category with no branding pair is painted in, once per renderer. */
    private ?string $coverprimary = null;

    /** @var array The category of each course asked about so far: courseid => categoryid, 0 for none. */
    private array $coursecategories = [];

    /**
     * @var category_navpills|null The category navigation's row of a page without a hero, drawn after its header.
     *
     * Set by {@see self::render_from_template()} on the layout that switches Boost Union's band off for it, and read
     * once by {@see self::full_header()}, which that layout calls later in the same render.
     */
    private ?category_navpills $pagenavpills = null;

    /**
     * Renders a template, rewriting the layout contexts this theme draws differently.
     *
     * A layout carrying the user menu gets the navbar's colour and the name block in the menu, because Boost
     * Union's layouts build that context and render it in one call, and this is the only place a child theme
     * sees it without owning a copy of every layout file. The name itself is left as core wrote it
     * (fullname($USER), through core\navigation\output\primary::get_user_menu()), so the navbar shows
     * whatever the site's fullnamedisplay composes. The user-menu, visitor, drawer and compact-navbar guards
     * read the context's shape rather than the template's name, so they survive Boost Union renaming its
     * layout templates, for one isset() a render; the course-notice and focus-mode guards, below, key on
     * the template's name instead.
     *
     * A layout drawn for a visitor - a session that is not logged in, or the guest account - loses the
     * user menu while {@see guestnav::applies()}: core prints nothing in it for them but its "Log in"
     * link and, for the guest account, "You are currently using guest access", which the theme's own
     * actions replace ({@see self::navbar_plugin_output()}). The same data goes into the context as
     * dimensionsguest, for this theme's override of theme_boost/primary-drawer-mobile, which the navbar
     * includes with the layout context.
     *
     * A layout drawn for a logged-in real account gets the theme's mobile drawer while
     * {@see drawernav::applies()}: its template data goes into the context as dimensionsdrawer, from the
     * primary navigation core already put there, and the same override of theme_boost/primary-drawer-mobile
     * draws it in place of Boost Union's list. Guarded by the shape core writes for a logged-in account:
     * mobileprimarynav beside usermenu.avatardata (the drawer reads nothing of the user menu itself).
     *
     * The same shape, usermenu.avatardata beside usermenu.items, builds the profile layer's template data while
     * {@see profilelayer::applies()}, read from the user menu before the theme adds its Look to it, and keeps it in
     * {@see self::$profilelayer} for {@see self::standard_after_main_region_html()}; the context itself is not
     * rewritten for it.
     *
     * A layout whose primary navigation can overflow by more than {@see compactnav::MAXHIDDEN} items gets
     * {@see compactnav::BODY_ARMED} spliced into its body attributes and the compact navbar's module armed, while
     * {@see compactnav::arms()} says so. Guarded by the shape bodyattributes beside primarymoremenu: Boost
     * Union's layouts build both before the one call that renders them, and the count is only in the latter.
     *
     * The two layouts that print Boost Union's course related hints get this theme's course notice
     * in their place, under the same key, set whatever Boost Union's include did ('' when the notice
     * says nothing). Guarded by the template's name, not the shape: the include writes the key only
     * when Boost Union has a hint, and the notice may have something to say when it has none.
     *
     * The drawers layout of a page that can switch focus mode in place is read, never rewritten,
     * for what focus mode adds to it ({@see focusmode::shell()}); Boost Union's template renders it
     * exactly as it would have.
     *
     * @param string $templatename The template to render.
     * @param mixed $context The template context.
     * @return string
     */
    public function render_from_template($templatename, $context) {
        /* The navbar style keys on the navbar's own context key: core's get_user_menu() never sets
           usermenu.userfullname for a visitor who is not logged in, and the public pages still need
           the navbar colours. */
        if (is_array($context) && array_key_exists('navbarcolorclasses', $context)) {
            $context = $this->apply_navbar_style($context);
        }
        if (is_array($context) && isset($context['usermenu']['userfullname'])) {
            $context = $this->apply_name_in_usermenu($context);
        }
        /* The profile layer, keyed on the same shape as the Look submenu below and built before it, from core's and
           Boost Union's menu as they wrote it: kept for standard_after_main_region_html(), which prints it after the
           page's content. Boost Union's dropdown is left as it is, the layer's fallback. */
        if (is_array($context) && isset($context['usermenu']['avatardata'], $context['usermenu']['items'])) {
            $this->profilelayer = profilelayer::applies($this->page)
                ? profilelayer::context((array) $context['usermenu'], $this->page, $this, $this->look_menu())
                : null;
        }
        // The Look submenu, keyed on the shape only a logged-in account's user menu has.
        if (is_array($context) && isset($context['usermenu']['avatardata'], $context['usermenu']['items'])) {
            $context = $this->apply_look_menu($context);
        }

        /* The visitor's navbar: core's "Log in" link and its guest sentence go, the theme's actions
           (appended by navbar_plugin_output()) replace them, and the mobile drawer's card reads the
           same data from the layout context. Guarded by the shape core writes for exactly these
           visitors: usermenu.unauthenticateduser. */
        if (is_array($context) && isset($context['usermenu']['unauthenticateduser']) && guestnav::applies($this->page)) {
            $context['usermenu'] = false;
            $context['dimensionsguest'] = guestnav::context();
        }

        /* The drawer of a logged-in account, keyed on the shape of the context that carries both of its
           sources: a visitor's user menu has no avatars, so this and the block above never meet. */
        if (
            is_array($context)
            && isset($context['mobileprimarynav'], $context['usermenu']['avatardata'])
            && drawernav::applies($this->page)
        ) {
            $context['dimensionsdrawer'] = drawernav::context((array) $context['mobileprimarynav'], $this->page);
        }

        /* The category's navigation is the theme's row of nav pills wherever core would draw its own (D97): the hero
           draws it on the showcase, and a destination page gets it after its header ({@see self::full_header()}).
           Boost Union's band is switched off, keyed on the key its layout writes for it, while a hero is armed (it
           replaces the header, row or no row) and on any other page exactly when a row is drawn: the builder answers
           null for a page that keeps its band (a plugin's own navigation). If Boost Union renames the key the band
           comes back beside the row, which core_renderer_test's drift test catches. */
        if (is_array($context) && array_key_exists('secondarymoremenu', $context)) {
            $heroarmed = category_hero::current() !== null;
            $this->pagenavpills = $heroarmed ? null : category_navpills::for_page($this->page);
            if ($heroarmed || $this->pagenavpills !== null) {
                $context['secondarymoremenu'] = false;
            }
        }

        // The compact navbar on tablets: see the docblock. The module is armed where the decision is made.
        if (
            is_array($context)
            && isset($context['bodyattributes'], $context['primarymoremenu'])
            && is_string($context['bodyattributes'])
            && is_array($context['primarymoremenu'])
            && compactnav::arms($this->page, $context['primarymoremenu'])
        ) {
            $context['bodyattributes'] = compactnav::body_attributes($context['bodyattributes']);
            $this->page->requires->js_call_amd('theme_dimensions/compactnav', 'init', [compactnav::client_config()]);
        }

        if (is_array($context) && in_array($templatename, self::HINT_LAYOUTS, true)) {
            $context[self::HINTS_KEY] = $this->course_notice();
        }

        /* Focus mode reads the layout, after the guards above so the bar inherits the navbar colours
           they resolved, and renders nothing in its place: the bar is printed by
           standard_top_of_body_html(), which this template evaluates later in the same call. Boost
           Union's layout/drawers.php renders theme_boost/drawers, except on Moodle Workplace (its MWP
           extension), where it renders local_boost_union_mwp/drawers and focus mode does not apply. */
        if (
            $templatename === 'theme_boost/drawers'
            && is_array($context)
            && \theme_dimensions\local\focusmode::available($this->page)
        ) {
            $this->focusshell = \theme_dimensions\local\focusmode::shell($context, $this->page, $this);
        }

        $html = parent::render_from_template($templatename, $context);
        if (in_array($templatename, self::HINT_LAYOUTS, true)) {
            $html = $this->brand_on_phones($html);
        }

        return $html;
    }

    /**
     * Show the navbar's brand on a phone too, beside the hamburger.
     *
     * Boost Union's navbar partial hides the logo below lg with Bootstrap's d-none, which is display: none !important,
     * so no rule of this theme's stylesheet can bring it back (stylelint forbids !important) and the partial is a piece of
     * the layout, never rendered through this method on its own. The layout's output is therefore edited in its one
     * place: the brand's first class list, replaced once. Should Boost Union change that list, the page simply keeps its
     * phone navbar without the logo; core_renderer_test reads Boost Union's partial for the exact string so that a
     * change there reddens rather than goes unseen.
     *
     * @param string $html A layout of {@see self::HINT_LAYOUTS}, rendered.
     * @return string
     */
    protected function brand_on_phones(string $html): string {
        $at = strpos($html, self::BRAND_HIDDEN);
        if ($at === false) {
            return $html;
        }

        return substr_replace($html, self::BRAND_SHOWN, $at, strlen(self::BRAND_HIDDEN));
    }

    /**
     * The course notice of this page, rendered, or '' when it says nothing.
     *
     * Every decision is {@see coursenotice_builder::build()}'s, including "nothing outside a course";
     * the fold is the person's ({@see course_notice::for_current_user()}).
     *
     * @return string HTML.
     */
    public function course_notice(): string {
        $notice = coursenotice_builder::build($this->page, $this);
        if ($notice === null) {
            return '';
        }
        return $this->render(course_notice::for_current_user($notice));
    }

    /**
     * The page header: the category showcase's hero on its page, Boost Union's header everywhere else.
     *
     * A course landing page whose banner is full width ({@see landing_hero}) has that banner as its header, the
     * header actions handed over the same way; its page header is otherwise empty (no heading, no breadcrumb).
     *
     * Core calls the course renderer's course_category() before it renders the header, and
     * that method arms a {@see category_hero} for the page when the showcase renders; here
     * the hero replaces the whole of Boost Union's header - the context header with its h1,
     * the breadcrumb, the header actions - so the page has exactly one h1, the hero's. The
     * header actions are core's own, handed to the hero's template as the parent hands them
     * to core's; the renderable knows nothing about them, because they are the renderer's
     * concern. The breadcrumb is not handed over: the hero draws its own trail from the
     * category's path ({@see category_hero::trail()}), which also serves the public shell. With no hero
     * armed the parent draws its header unchanged, but for the header actions of a page that opens in
     * focus mode ({@see self::focus_full_header()}).
     *
     * A page without a hero whose layout found it a row of the category's navigation ({@see self::$pagenavpills})
     * gets that row right after the header, where Boost Union's band was: on the page's ground, start-aligned, in a
     * track of its own, which the client measures (D98).
     *
     * Either row is armed here, where it is drawn ({@see self::arm_navpills()}).
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        $landinghero = landing_hero::current();
        if ($landinghero !== null) {
            return $this->render($landinghero);
        }
        $hero = category_hero::current();
        if ($hero === null) {
            return $this->focus_full_header() . $this->page_navpills();
        }
        $context = $hero->export_for_template($this);
        $this->arm_navpills($context->navpills);
        $context->headeractions = $this->page->get_header_actions();
        $context->headerextras = $this->page->get_header_extras();
        $context->hasheaderextras = !empty($context->headerextras);
        return $this->render_from_template('theme_dimensions/category_hero', $context);
    }

    /**
     * The row of nav pills a page without a hero draws after its header, in its own track, or '' when it has none.
     *
     * Drawn once: the row is forgotten as it is printed.
     *
     * @return string HTML.
     */
    private function page_navpills(): string {
        $row = $this->pagenavpills;
        $this->pagenavpills = null;
        if ($row === null) {
            return '';
        }
        $context = $row->export_for_template($this);
        $this->arm_navpills($context);
        return $this->render_from_template('theme_dimensions/category_navpills_page', $context);
    }

    /**
     * Arm the client of a row of nav pills, the hero's or a destination page's, when the row has a menu to open.
     *
     * A row with a group, or with "More" holding nodes of its own, gets the module: it claims the
     * menu roles with their keys, keeps one menu open at a time and moves what does not fit into
     * "More". A row of plain pills is not armed. It has no menu, so no role or key to add, and its
     * only overflow is the wrapping capsule every row has without a script (it holds at most one
     * pill per group, each a destination of its own, which wraps cleanly on a phone): arming it would
     * load the module for every viewer with a right or two in the category, for nothing a reader
     * could not already reach. No row (the anonymous shell, a learner) arms nothing.
     *
     * @param array|null $navpills The row's exported context ({@see category_navpills::export_for_template()}), or null.
     * @return void
     */
    private function arm_navpills(?array $navpills): void {
        if ($navpills === null || empty($navpills['hasgroups'])) {
            return;
        }
        $this->page->requires->js_call_amd('theme_dimensions/navpills', 'init', [$navpills['uniqid']]);
    }

    /**
     * Boost Union's page header, without the header actions the focus bar already drew.
     *
     * A page that opens in focus mode draws the manual completion button (or its status) in the
     * bar, and the page header - hidden under the body class, there for the switch back - must not
     * draw it a second time: a hidden copy would go stale the moment the visible one was pressed,
     * and the module moves the one node between the two places instead. moodle_page offers no way
     * to withdraw a header action, so the list is narrowed around the parent's call and put back
     * after it: the first actions are the bar's (it read them before this template ran), and what
     * the parent appended itself - the region main settings menu - is kept, in the header, where
     * focus mode hides it with the rest of the chrome.
     *
     * @return string HTML to display the main header.
     */
    protected function focus_full_header(): string {
        $taken = count((array) ($this->focusshell['bar']['headeractions'] ?? []));
        if ($taken === 0) {
            return parent::full_header();
        }

        $actions = new \ReflectionProperty(\moodle_page::class, '_headeractions');
        $before = $this->page->get_header_actions();
        $actions->setValue($this->page, array_slice($before, $taken));
        try {
            return parent::full_header();
        } finally {
            $actions->setValue($this->page, array_merge(array_slice($before, 0, $taken), $this->page->get_header_actions()));
        }
    }

    /**
     * The top of the body, and on a page that can switch focus mode in place the bar after it.
     *
     * Boost Union's layout template prints this first inside the page wrapper, so the bar lands
     * after core's skip link, which this output carries, and before Boost Union's navbar. The bar is
     * always in the page and the stylesheet shows it only under the focus mode body class; the status
     * line beside it is not hidden with it, because it is what announces the switch in either direction.
     *
     * Every layout prints this once, so it is also where the help dialogue's module (amd/src/helpmodal.js) is armed: any
     * page can carry core's help icons, logged in or not, and the module only listens until one is used.
     *
     * @return string HTML.
     */
    public function standard_top_of_body_html() {
        $output = parent::standard_top_of_body_html();
        $this->page->requires->js_call_amd('theme_dimensions/helpmodal', 'init');
        if ($this->focusshell === null) {
            return $output;
        }

        return $output . $this->render_from_template('theme_dimensions/focusmode_top', [
            'bar' => $this->focusshell['bar'],
        ]);
    }

    /**
     * Core's activity navigation, with focus mode's foot of the content after it where the page can switch.
     *
     * The foot is the completion line (the automatic conditions as one discreet line,
     * core's own partial, while the activity information box keeps them for the ordinary skin) and
     * the previous and next row core suppresses on a format with a course index
     * ({@see self::focus_activity_navigation()}). Both sit in a container the stylesheet shows only
     * under the focus mode body class. Where core already drew its row the foot carries none of its
     * own, so no skin ever shows two.
     *
     * @return string HTML.
     */
    public function activity_navigation() {
        $ordinary = parent::activity_navigation();
        if ($this->focusshell === null) {
            return $ordinary;
        }

        $navigation = $ordinary === '' ? $this->focus_activity_navigation() : '';
        if ($navigation === '' && $this->focusshell['completion'] === false) {
            return $ordinary;
        }
        $foot = $this->render_from_template('theme_dimensions/focusmode_foot', [
            'completion' => $this->focusshell['completion'],
            'activitynav' => $navigation,
            'hasactivitynav' => $navigation !== '',
        ]);

        return $foot . $ordinary;
    }

    /**
     * Make the navbar dark in the brand colour, through Boost Union's own mechanism.
     *
     * Boost Union's navbar include writes this key from its navbarcolor setting and from a
     * flavour's look_navbarcolor; 'navbar-dark bg-primary' is exactly its "coloured, light font"
     * choice. Setting it here rather than recolouring the navbar with CSS is what keeps every
     * component inside it coherent: Boost Union maps the pills, the user menu toggle, the edit
     * switch, the divider and the dropdown hovers onto that pair of classes, and in the dark colour
     * mode it repaints the bar itself. The navbar carries no data-bs-theme of its own, so the
     * dropdowns and popovers inside it keep the page's colour mode.
     *
     * Only the untouched default is filled in. An admin who picked a navbar colour, and a flavour
     * which sets one for its organisation, both already produced something other than 'bg-body' -
     * and theirs must stand.
     *
     * @param array $context The layout template context.
     * @return array
     */
    protected function apply_navbar_style(array $context): array {
        $navbarstyle = get_config('theme_dimensions', 'navbarstyle');
        if ($navbarstyle == THEME_DIMENSIONS_SETTING_NAVBARSTYLE_INHERIT) {
            return $context;
        }
        if (($context['navbarcolorclasses'] ?? '') !== 'bg-body') {
            return $context;
        }
        $context['navbarcolorclasses'] = 'navbar-dark bg-primary';

        return $context;
    }

    /**
     * Make sure the user menu carries the name, for the screens where the navbar cannot.
     *
     * Below the md breakpoint the navbar collapses and there is no room beside the avatar. Boost
     * Union already renders exactly the block that is needed - the 'logged in as' line at the top
     * of the user menu - behind its own showfullnameinusermenu setting, which ships off. Switching
     * that flag on here reuses its markup and its label instead of inventing a second block, and
     * scss/post.scss then hides it from md up, where the navbar carries the name instead.
     *
     * The result is that the name appears exactly once at any width. An admin who deliberately
     * turned Boost Union's setting off still gets nothing on desktop, where the navbar shows the
     * name anyway; on a phone the menu is the only place left for it.
     *
     * Core 5.3 adds a name of its own to the toggle: the first name, in a span beside the avatar
     * from xl up (lib/templates/user_menu.mustache, Boost Union's copy alike). This theme's
     * core/user_menu_metadata override already shows the full name there from md up, so the key
     * core fills it from is dropped here, and the template's section around it draws nothing.
     * Dropped rather than hidden by the stylesheet, because the span carries d-xl-block, a
     * Bootstrap utility whose display is !important and which no rule of this theme may outrank.
     * The guard that called this method is the full name being in the context, which is exactly
     * when the override draws it.
     *
     * The name the override draws beside the avatar is the theme's own key, dimensionsnavname: the field the
     * administrator chose, masked ({@see identity::navbar()}), of the session's user, which under "log in as" is
     * the account logged in as. Core's userfullname stays as core wrote it (the real user's name there), because
     * Boost Union's name block in the open menu reads it.
     *
     * @param array $context The layout template context.
     * @return array
     */
    protected function apply_name_in_usermenu(array $context): array {
        global $USER;

        if (empty($context['showfullnameinusermenu'])) {
            $context['showfullnameinusermenu'] = true;
        }
        unset($context['usermenu']['userfirstname']);
        $context['usermenu']['dimensionsnavname'] = identity::navbar($USER);

        return $context;
    }

    /**
     * Add the Look submenu to the user menu, built like core's language submenu.
     *
     * The link goes just before core's Language link, or after Preferences where there is no language
     * menu, or before the last item. Each submenu item is a link to the choice page with its flavour
     * preselected and carries the flavour id for theme_dimensions/flavourchoice, which records the
     * choice in place. The current item points at '#', as core's current language does. Core's submenu
     * template prints an item's text with a triple stash, so the text goes in escaped; the title is
     * printed double-stashed and goes in plain. A context already carrying the submenu is left alone.
     *
     * @param array $context The layout template context.
     * @return array
     */
    protected function apply_look_menu(array $context): array {
        if (!empty($context['usermenu']['dimensionslook'])) {
            return $context;
        }
        $menu = $this->look_menu();
        if ($menu === null) {
            return $context;
        }

        $items = [];
        foreach ($menu['items'] as $item) {
            // Boost Union's submenu item prints the text raw, so the mark's markup goes in front of the escaped title.
            $mark = $item['mark'] !== null ? $this->render_from_template('theme_dimensions/look_mark', $item['mark']) : '';
            $items[] = [
                'title' => $item['text'],
                'text' => $mark . s($item['text']),
                'link' => true,
                'isactive' => $item['iscurrent'],
                'url' => $item['iscurrent'] ? new \moodle_url('#') : $item['url'],
                'attributes' => [['name' => 'data-dimensions-flavour', 'value' => $item['id']]],
            ];
        }
        $submenuid = 'dimensions-look';
        $link = (object) [
            'itemtype' => 'submenu-link',
            'submenuid' => $submenuid,
            'title' => get_string('lookmenu', 'theme_dimensions'),
            'divider' => false,
            'submenulink' => true,
        ];

        $navitems = array_values((array) $context['usermenu']['items']);
        array_splice($navitems, self::look_position($navitems, (array) ($context['usermenu']['submenus'] ?? [])), 0, [$link]);
        $context['usermenu']['items'] = $navitems;
        $context['usermenu']['submenus'] = array_merge(
            array_values((array) ($context['usermenu']['submenus'] ?? [])),
            [(object) ['id' => $submenuid, 'title' => get_string('lookmenu', 'theme_dimensions'), 'items' => $items]]
        );
        $context['usermenu']['dimensionslook'] = true;
        $this->page->requires->js_call_amd('theme_dimensions/flavourchoice', 'init');

        return $context;
    }

    /**
     * flavourchoice::menu()'s answer for this page, asked once per renderer: the user menu's Look submenu and the
     * profile layer's Look sub-view both read it.
     *
     * @return array|null
     */
    protected function look_menu(): ?array {
        if (!$this->lookasked) {
            $this->lookasked = true;
            $this->lookmenu = flavourchoice::menu($this->page->url);
        }

        return $this->lookmenu;
    }

    /**
     * What core prints after the main region, with the profile layer after it on a page that carries one.
     *
     * Boost Union's drawers and columns2 layouts, the only ones that build the user menu, call this exactly once each,
     * after the page's content and outside the navbar (BU/templates/theme_boost/drawers.mustache after the footer,
     * columns2.mustache before it), so the layer is never inside the nav, whose auto-hide transform would become the
     * fixed layer's containing block. Not the end-of-body token: Boost Union's footer partial prints that inside its
     * footer popover's content (data-region="footer-content-popover") whenever its footer button is on, which core's
     * stylesheet hides from sm up and core's popover copies by innerHTML on every opening. The stored context is
     * dropped as it is printed, so a second call prints core's output alone.
     *
     * The layer's client module (amd/src/profilelayer.js), which takes the user button over from Boost Union's
     * dropdown, is armed here and nowhere else: only a page that prints the layer arms it, so a visitor, the guest
     * account and a site that switched the layer off keep the dropdown untouched.
     *
     * @return string HTML fragment.
     */
    public function standard_after_main_region_html() {
        $output = parent::standard_after_main_region_html();
        $layer = $this->profilelayer;
        $this->profilelayer = null;
        if ($layer === null) {
            return $output;
        }
        $this->page->requires->js_call_amd('theme_dimensions/profilelayer', 'init');

        return $output . $this->render_from_template('theme_dimensions/profile_layer', $layer);
    }

    /**
     * Where the Look link goes among the user menu's items.
     *
     * Core's language link is found through its submenu, whose title is core's languageselector string, as
     * Boost Union finds it; Preferences by its address.
     *
     * @param array $navitems The user menu's items, as core built them.
     * @param array $submenus The user menu's submenus.
     * @return int The index to insert at.
     */
    protected static function look_position(array $navitems, array $submenus): int {
        $languageid = null;
        foreach ($submenus as $submenu) {
            $submenu = (object) $submenu;
            if (($submenu->title ?? '') === get_string('languageselector')) {
                $languageid = $submenu->id ?? null;
            }
        }
        $preferences = null;
        foreach ($navitems as $index => $item) {
            $item = (object) $item;
            if ($languageid !== null && ($item->submenuid ?? null) === $languageid) {
                return $index;
            }
            $url = $item->url ?? null;
            if ($url instanceof \moodle_url && $url->compare(new \moodle_url('/user/preferences.php'), URL_MATCH_BASE)) {
                $preferences = $index + 1;
            }
        }

        return $preferences ?? max(0, count($navitems) - 1);
    }

    /**
     * Returns HTML attributes to use within the body tag, adding the focus mode, dark logo and reading classes.
     *
     * Boost Union calls this from inside its layout file, with the page's cm already set,
     * which is why focus mode needs no hook for the class and never meets
     * moodle_page::add_body_class()'s state guard. The classes are appended to
     * $additionalclasses before delegating on purpose: Boost Union's own override does not
     * call core's, it rebuilds the attribute string itself, so a child theme which
     * post-processed that string would be editing finished HTML.
     *
     * @param string|array $additionalclasses Any additional classes to give the body tag.
     * @return string
     */
    public function body_attributes($additionalclasses = []) {
        if (!is_array($additionalclasses)) {
            $additionalclasses = explode(' ', $additionalclasses);
        }

        /* Two classes, because the page carries both skins: dimensions-focusable on every page that can
           switch in place, which scopes the rules that hold in either skin (the focus-only parts hidden,
           the entry button's pill), and dimensions-focusmode on the one that opens focused, which the
           amd module then moves. */
        if (\theme_dimensions\local\focusmode::available($this->page)) {
            $additionalclasses[] = 'dimensions-focusable';
            if (\theme_dimensions\local\focusmode::applies($this->page)) {
                $additionalclasses[] = 'dimensions-focusmode';
                if (\theme_dimensions\local\focusmode::wants_expanded()) {
                    $additionalclasses[] = 'dimensions-focusmode-expanded';
                }
            }
        }

        // The logo variants for dark surfaces, armed only while the logo in force is the site's own.
        // Boost Union's layout/login.php calls this method too, so the login layout carries them.
        $additionalclasses = array_merge(
            $additionalclasses,
            darklogo::body_classes($this->page->theme, $this->darklogo_flavour())
        );

        // The navbar that follows the reading: the band's track, and auto-hide's offsets where it is on.
        $additionalclasses = array_merge($additionalclasses, readingbar::body_classes($this->page));

        return parent::body_attributes($additionalclasses);
    }

    /**
     * Prints a block, without core's "Go to calendar..." under the upcoming block whose content the theme answers.
     *
     * The decision is {@see blocks::drops_footer()}, over the predicate the calendar renderer answers the block's content
     * by ({@see core_calendar_renderer::render_from_template()}), so the footer goes exactly when the theme's agenda,
     * which ends with its own "Full calendar", stands in the block. The block_contents is cloned first, as Boost Union's
     * method clones it, so the caller's object is untouched.
     *
     * @param \core_block\output\block_contents $bc The block's contents.
     * @param string $region The region the block is in.
     * @return string
     */
    public function block(\core_block\output\block_contents $bc, $region) {
        $block = (string) ($bc->attributes['data-block'] ?? '');
        // Asked of the upcoming block alone, so no other block pays the predicate's reads.
        $applies = $block === blocks::BLOCK_UPCOMING
            && blocks::applies(blocks::enabled(), (int) $this->page->course->id, coreview::account(), coreview::gregorian());
        if (blocks::drops_footer($block, $applies)) {
            $bc = clone($bc);
            $bc->footer = '';
        }
        return parent::block($bc, $region);
    }

    /**
     * The Boost Union flavour that applies to this page, as {@see darklogo::applied_flavour()} answers it.
     *
     * A seam, so a test can hand in a flavour: Boost Union memoises its lookup in a function static for the
     * whole process, so the first page a PHPUnit run renders decides the answer for every later test.
     *
     * @return \stdClass|null The flavour's record.
     */
    protected function darklogo_flavour(): ?\stdClass {
        return darklogo::applied_flavour();
    }

    /**
     * The navbar's plugin output, with the way into focus mode spliced into it and the visitor's actions after it.
     *
     * The focus button must sit left of message_popup's notification bell. Core appends the plugins'
     * render_navbar_output() callbacks in plugin type order, message before theme, so a callback in this
     * theme's lib.php would land to the right of the bell, and copying Boost Union's navbar template would
     * mean re-diffing it on every update; so the rendered button is spliced in front of the bell's
     * container. With the bell absent (notifications disabled, or the processor switched off) the button
     * is prepended instead.
     *
     * The visitor's actions ({@see guestnav}) are appended: Boost Union's navbar prints this output
     * immediately before the user menu, which render_from_template() empties for the same visitors,
     * so the end of this string is the right end of the navbar.
     *
     * The context bar ({@see readingbar}) is appended after them, and its place in the string does not
     * matter: scss/readingbar.scss lays it over the whole nav, the nearest positioned ancestor (nothing
     * between #usernavigation and the nav is positioned, in core or in Boost Union). The client module
     * that runs the band, the swap and auto-hide is armed here too, on every reading page, with or
     * without the layer: on the course page it is what paints the reader's completion on the band. A reader core
     * lets edit the page ({@see readingbar::edit_applies()}) gets core's edit mode switch in the layer, rendered
     * again by core's own edit_switch(): the navbar's switch is hidden under the layer while it is shown. The
     * layer carries the way into focus mode too, the navbar's own form drawn a second time, on the pages it applies to.
     *
     * Last, the user menu entries that stand in for the navbar's controls on a phone ({@see phonenav}), decided from the
     * parent's output alone, so that the theme's own focus button never counts as a control of a plugin's.
     * And where core adds its linear navigation footer, the module that shows that footer again near the end of the page
     * on a phone (amd/src/linearfooter.js): Boost hides it on the way down, which is how a reader reaches "Next".
     *
     * @return string
     */
    public function navbar_plugin_output() {
        $output = parent::navbar_plugin_output();
        // The phone's user menu entries are decided from what the subsystems and plugins printed, before anything of
        // the theme's is added to it.
        $phoneentries = phonenav::entries($output, $this->page);

        // On every page that can switch in place: the navbar is hidden in focus mode, and this is the way back in.
        $focusentry = null;
        if (\theme_dimensions\local\focusmode::available($this->page)) {
            $focusentry = [
                'actionurl' => (new \moodle_url('/theme/dimensions/focus.php'))->out(false),
                'sesskey' => sesskey(),
                'cmid' => (int) $this->page->cm->id,
                'label' => get_string('focusmode_enter', 'theme_dimensions'),
                'title' => get_string('focusmode_enter_title', 'theme_dimensions'),
            ];
            $button = $this->render_from_template('theme_dimensions/focusmode_entry', $focusentry);
            $output = $this->splice_focus_entry($output, $button);
        }

        if (guestnav::applies($this->page)) {
            $output .= $this->render_from_template('theme_dimensions/navbar_guest', guestnav::context());
        }

        if (readingbar::applies($this->page)) {
            if (readingbar::context_applies($this->page)) {
                $layer = readingbar::context($this->page);
                if ($layer['canedit']) {
                    // Core's own switch, rendered again: a second id, its own form and its own module.
                    $layer['editswitch'] = (string) $this->edit_switch();
                }
                // The way into focus mode, the navbar's own form again: focusmode.js intercepts any submit of it.
                $layer['hasfocus'] = $focusentry !== null;
                $layer['focus'] = $focusentry;
                $output .= $this->render_from_template('theme_dimensions/navbar_readingbar', $layer);
            }
            $this->page->requires->js_call_amd('theme_dimensions/readingbar', 'init', [readingbar::client_config($this->page)]);
        }

        if ($phoneentries) {
            $output .= $this->render_from_template('theme_dimensions/navbar_phonemenu', ['entries' => $phoneentries]);
            $this->page->requires->js_call_amd('theme_dimensions/phonemenu', 'init');
        }

        // Core's linear navigation footer, shown again near the end of the page on a phone. Core's own predicate, the one
        // its hook asks before it adds the footer; the module still looks for the footer on the page before it arms.
        if (\core_courseformat\local\linearnavigationsettings::show_navigation_footer($this->page)) {
            $this->page->requires->js_call_amd('theme_dimensions/linearfooter', 'init');
        }

        return $output;
    }

    /**
     * Put the entry button immediately before the notifications container.
     *
     * The anchor is message_popup's own root element id (nav-notification-popover-container,
     * in its notification_popover template). The insertion point is the start of the element that
     * carries it, not the id itself, so the button lands as a sibling rather than inside
     * somebody else's div.
     *
     * @param string $output The navbar plugin output the parent produced.
     * @param string $button The rendered entry button.
     * @return string
     */
    protected function splice_focus_entry(string $output, string $button): string {
        $anchor = strpos($output, 'nav-notification-popover-container');
        if ($anchor === false) {
            return $button . $output;
        }

        $opening = strrpos(substr($output, 0, $anchor), '<div');
        if ($opening === false) {
            return $button . $output;
        }

        return substr($output, 0, $opening) . $button . substr($output, $opening);
    }

    /**
     * Core's activity navigation, produced on the formats which normally suppress it, for focus mode's foot.
     *
     * Core returns the empty string whenever the theme has a course index and the format
     * uses one - topics and weeks, that is - because the drawer is then the way forward.
     * In focus mode the drawer is still there, but a reader working through a page wants
     * the next activity under the text, so the flag is flipped for exactly one call and
     * restored in a finally. Never set for the whole request: a second reader of the same
     * property lives in the course format's own content class.
     *
     * Where core draws its linear navigation, this draws nothing: from 5.3 on a format which uses
     * it (topics and weeks, switched on by default) gets previous and next, and the manual
     * completion button, in the sticky footer core adds from before_footer_html_generation
     * ({@see \core_courseformat\hook_listener::add_course_navigation_sticky_footer()}). The
     * footer is printed by footer() into the page in either skin, so a second row of our own
     * would put two navigations under the text. The question is core's own predicate,
     * asked while the layout renders: a page that later prints a sticky footer of its own loses
     * core's linear navigation and this one alike, exactly as the ordinary page does.
     *
     * @return string
     */
    protected function focus_activity_navigation(): string {
        if (\core_courseformat\local\linearnavigationsettings::show_navigation_footer($this->page)) {
            return '';
        }

        $usescourseindex = $this->page->theme->usescourseindex;
        try {
            $this->page->theme->usescourseindex = false;
            return parent::activity_navigation();
        } finally {
            $this->page->theme->usescourseindex = $usescourseindex;
        }
    }

    /**
     * The image of a course with none of its own: its category's cover.
     *
     * Core asks this for every course whose overview files hold no image (course_summary_exporter, so the
     * Dashboard, My courses and Boost Union's cards among others), and the plugins of the card family ask it as
     * well. When Boost Union's image source is the fallback image and one is uploaded, the answer is Boost Union's
     * (the parent's); otherwise an SVG of the course category's cover ({@see cover}), and for an id that names no
     * course, or the site course, the parent's pattern.
     *
     * One call costs, cold, a statement for the course's category (none for the page's own course, and none once the
     * shared cache {@see coursecategory} holds the course) plus the branding cache's miss for that category. A grid asks
     * {@see course_covers()} instead, with the categories its rows hold.
     *
     * @param int $id The course id.
     * @return string A data URI, or the URL of Boost Union's fallback image.
     */
    public function get_generated_image_for_id($id) {
        $courseid = (int) $id;
        return $this->course_covers([$courseid => $this->course_category($courseid)])[$courseid];
    }

    /**
     * The image of each of several courses with none of their own, with the categories already known.
     *
     * {@see get_generated_image_for_id()} for a page of cards: no statement per course, the branding of all the
     * categories in one cache read ({@see cover::colours()}), and Boost Union's fallback asked once.
     *
     * @param array $categories courseid => the course's category id, 0 where it has none (the site course).
     * @return array courseid => a data URI, or the URL of Boost Union's fallback image.
     */
    public function course_covers(array $categories): array {
        $covers = [];
        if (!$categories) {
            return $covers;
        }
        $fallback = $this->boost_union_fallback((int) array_key_first($categories));
        $colours = $fallback === null ? cover::colours(array_values($categories), $this->cover_primary()) : [];
        foreach ($categories as $courseid => $categoryid) {
            $courseid = (int) $courseid;
            $colour = $colours[(int) $categoryid] ?? null;
            if ($fallback !== null) {
                $covers[$courseid] = $fallback;
            } else if ($colour !== null) {
                $covers[$courseid] = cover::data_uri($colour);
            } else {
                $covers[$courseid] = parent::get_generated_image_for_id($courseid);
            }
        }
        return $covers;
    }

    /**
     * The band colour of each of several categories: what a card without its cover edges itself with.
     *
     * The colour of the cover a course with no image gets ({@see course_covers()}), whatever the image source: its
     * category's pair, inherited, else the stylesheet's primary.
     *
     * @param int[] $categoryids The categories.
     * @return array categoryid => lower-case #rrggbb.
     */
    public function category_bands(array $categoryids): array {
        return cover::colours($categoryids, $this->cover_primary());
    }

    /**
     * Boost Union's answer while its image source is the fallback image and an administrator uploaded one, else null.
     *
     * Boost Union's renderer answers that image's URL for every course alike (its get_generated_image_for_id()), and
     * reads the file area to find it on every call, so it is asked once per renderer and the answer is reused.
     * Re-read that method on every Boost Union update: an answer that ever depends on the course breaks the reuse.
     *
     * @param int $courseid A course of the batch, handed to the parent.
     * @return string|null
     */
    protected function boost_union_fallback(int $courseid): ?string {
        global $CFG;

        if (boost_union::image_source($this->page) !== boost_union::SOURCE_FALLBACK) {
            return null;
        }
        if (!$this->fallbackasked) {
            $this->fallbackasked = true;
            require_once($CFG->dirroot . '/theme/boost_union/locallib.php');
            if (theme_boost_union_get_course_overview_fallback_image_url() !== null) {
                $this->fallbackanswer = parent::get_generated_image_for_id($courseid);
            }
        }
        return $this->fallbackanswer;
    }

    /**
     * The primary a category with no branding pair on its path is painted in: the stylesheet's ({@see cover::primary()}).
     *
     * @return string
     */
    protected function cover_primary(): string {
        return $this->coverprimary ??= cover::primary($this->cover_flavour());
    }

    /**
     * The Boost Union flavour whose brand colour the covers take, or null.
     *
     * Only for a page that set its URL: Boost Union's lookup reads it, and a web service (core's AJAX entry sets
     * none) would print a debugging notice; such a request paints the site's brand colour. A seam as well, so a
     * test can hand in a flavour ({@see darklogo_flavour()} says why).
     *
     * @return \stdClass|null
     */
    protected function cover_flavour(): ?\stdClass {
        return $this->page->has_set_url() ? darklogo::applied_flavour() : null;
    }

    /**
     * The category of a course, once per renderer: the page's own course from the page, another from {@see coursecategory}.
     *
     * @param int $courseid The course.
     * @return int The category id, 0 for a course that does not exist or for the site course.
     */
    protected function course_category(int $courseid): int {
        if (!array_key_exists($courseid, $this->coursecategories)) {
            $pagecourse = $this->page->course;
            $category = (int) $pagecourse->id === $courseid
                ? $pagecourse->category
                : coursecategory::of($courseid);
            $this->coursecategories[$courseid] = $courseid === SITEID ? 0 : (int) $category;
        }
        return $this->coursecategories[$courseid];
    }
}
