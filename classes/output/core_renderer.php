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

use theme_dimensions\local\compactnav;
use theme_dimensions\local\coursenotice\builder as coursenotice_builder;
use theme_dimensions\local\darklogo;
use theme_dimensions\local\drawernav;
use theme_dimensions\local\guestnav;
use theme_dimensions\local\login;
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
     * Renders the login form.
     *
     * With the theme's login page switched off, Boost Union renders its form exactly as configured
     * there; switched on, the form comes from this theme's own template, and every Boost Union login
     * setting that shapes the form stops applying. A username mask is not this theme's business:
     * auth_loginsteps arms its own on core's form hooks, which the theme's template keeps.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        // Boost Union's login page, exactly as it is, when this theme's page is switched off.
        if (!login::page_enabled()) {
            return parent::render_login($form);
        }

        return $this->render_from_template('theme_dimensions/loginform', $this->login_page_context($form));
    }

    /**
     * The template context of the theme's login page, built from core's renderable.
     *
     * Core's export carries the form itself - urls, token, flags, error - and the identity
     * providers as prepare_identity_providers_for_output() left them. What is added is what this
     * page draws differently: the providers with a local mark instead of the provider's image, the
     * panel's logo for the mobile band, and the site's short name as the wordmark fallback.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return \stdClass
     */
    protected function login_page_context(\core_auth\output\login $form): \stdClass {
        global $SITE;

        $context = $form->export_for_template($this);
        $context->sitename = format_string(
            $SITE->shortname,
            true,
            ['context' => \context_course::instance(SITEID), 'escape' => false]
        );
        $context->logourl = login::logo_url($this->compact_logo_url());
        // The panel's copy reaches the form column too: below lg the aside is hidden and the
        // kicker moves into the band above the heading.
        $context->dimensionsloginpanel = login::panel();
        $context->idps = login::providers_for_output((array) $context->identityproviders);
        $context->hasidps = !empty($context->idps);
        $context->showseparator = $context->hasidps && !empty($context->showloginform);
        $context->showsignupseparator = !empty($context->cansignup) && ($context->hasidps || !empty($context->showloginform));

        // The reset hint, from the second failed login in a row. Core's set_error() fills errortitle
        // for AUTH_LOGIN_FAILED alone, and login/index.php folds every credential failure - a wrong
        // password, an unknown username, a suspended or locked-out account - into that code before
        // calling it; only a failed captcha and an unauthorised login keep their own code, carry no
        // title, and neither count nor show the hint.
        $context->suggestreset = false;
        if (!empty($context->errortitle)) {
            $context->suggestreset = login::suggest_reset(login::record_failed_attempt());
        }

        return $context;
    }

    /**
     * Renders a template, rewriting the layout contexts this theme draws differently.
     *
     * The login layout gets the custom login page's context while that page is on: the arrangement is
     * forced side by side, the body gains the class the login styles are scoped to, and the panel's
     * logo and copy are added for the core/login_panel override. That happens here because Boost
     * Union's layout/login.php builds the context and renders it in one call, and this is the only
     * place a child theme sees it without owning a copy of that file.
     *
     * A layout carrying the user menu gets the navbar's colour and the name block in the menu, for
     * the same reason: Boost Union's layouts build that context and render it in one call. The name
     * itself is left exactly as core wrote it - fullname($USER), from
     * \core\user::get_user_navigation_info() through core\navigation\output\primary::get_user_menu() - so
     * the navbar shows whatever the site's fullnamedisplay composes. The guard is the context's
     * shape rather than the template's name on purpose: it survives Boost Union renaming or
     * reorganising its layout templates, and it costs one isset() per render.
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
     * primary navigation and the user menu core already put there, and the same override of
     * theme_boost/primary-drawer-mobile draws it in place of Boost Union's list. Guarded by the SHAPE
     * core writes for a logged-in account - mobileprimarynav beside usermenu.avatardata - for the reason
     * given above; any other template's context is left as it came.
     *
     * A layout whose primary navigation can overflow by more than {@see compactnav::MAXHIDDEN} items gets
     * {@see compactnav::BODY_ARMED} spliced into its body attributes and the compact navbar's module armed, while
     * {@see compactnav::arms()} says so. Guarded by the SHAPE - bodyattributes beside primarymoremenu - because
     * Boost Union's layouts build both before the one call that renders them, and the count is only in the latter.
     *
     * The two layouts that print Boost Union's course related hints get this theme's course notice
     * in their place, under the same key: Boost Union's include writes the key only when it has a
     * hint, so the value is SET here whatever the include did, and an empty string when the notice
     * says nothing - Boost Union's cards never reach the page. Guarded by the template's name,
     * because the shape cannot be: the key is absent exactly when Boost Union had nothing to say.
     * It runs before the focus mode swap, so the focus template prints the notice too.
     *
     * @param string $templatename The template to render.
     * @param mixed $context The template context.
     * @return string
     */
    public function render_from_template($templatename, $context) {
        if ($templatename === 'theme_boost/login' && is_array($context) && login::page_enabled()) {
            $context = login::layout_context($context, $this->compact_logo_url());
        }

        /* The navbar style keys on the navbar's own context key: core's get_user_menu() never sets
           usermenu.userfullname for a visitor who is not logged in, and the public pages still need
           the navbar colours. */
        if (is_array($context) && array_key_exists('navbarcolorclasses', $context)) {
            $context = $this->apply_navbar_style($context);
        }
        if (is_array($context) && isset($context['usermenu']['userfullname'])) {
            $context = $this->apply_name_in_usermenu($context);
        }

        /* The visitor's navbar: core's "Log in" link and its guest sentence go, the theme's actions
           (appended by navbar_plugin_output()) replace them, and the mobile drawer's card reads the
           same data from the layout context. Guarded by the SHAPE core writes for exactly these
           visitors - usermenu.unauthenticateduser - for the reason the docblock gives. */
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
            $context['dimensionsdrawer'] = drawernav::context(
                (array) $context['mobileprimarynav'],
                (array) $context['usermenu'],
                $this->page,
                $this
            );
        }

        /* The category showcase's hero draws the category's tabs itself, so the band the layout
           would render under the header is switched off while a hero is armed. Guarded by the
           SHAPE of the context - the key Boost Union's layout writes from the secondary
           navigation - and not by the template's name, for the reason the docblock gives. If
           Boost Union ever renames the key the band comes back beside the hero's tabs, which
           the drift test in core_renderer_test reads as two tab strips on one page. */
        if (is_array($context) && array_key_exists('secondarymoremenu', $context) && category_hero::current() !== null) {
            $context['secondarymoremenu'] = false;
        }

        /* The compact navbar on tablets (B5), armed only where the primary navigation can overflow by more than
           compactnav::MAXHIDDEN items at all. Guarded by the SHAPE of a layout's context - its body attributes beside
           the primary navigation's more menu, both of which Boost Union's layouts build before this one call - for the
           reason the docblock gives. The module is armed here because this is where the decision is made. */
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

        /* Focus mode swaps the template, after the two guards above so the bar inherits the
           navbar colours they resolved. Boost Union's layout file ends with exactly this
           template name - core Boost's own naming, which Boost Union had to keep to stay a
           valid override target - and with Multi-tenancy installed it renders another one,
           where this guard does not match and focus mode simply does not apply. */
        if (
            $templatename === 'theme_boost/drawers'
            && is_array($context)
            && \theme_dimensions\local\focusmode::applies($this->page)
        ) {
            $context = \theme_dimensions\local\focusmode::layout_context($context, $this->page, $this);
            $templatename = 'theme_dimensions/focusmode';
        }

        return parent::render_from_template($templatename, $context);
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
     * Boost Union's compact logo as a plain url, or '' when the site has none.
     *
     * Wherever the login page prints the brand, the image is Boost Union's compact logo
     * (`theme_boost_union | logocompact`). Boost Union's own override of this core method resolves an
     * applicable flavour's compact logo first, then a Multi-tenancy tenant's logo where that is
     * installed, then its own logocompact setting - the order an organisation's branding needs - and
     * answers moodle_url|false, false when none is set. It is resolved here
     * because only a renderer has it, and handed to {@see login::logo_url()}, which stays static.
     *
     * out(false) on purpose: the raw url. The panel and the band double-stash it, which escapes it
     * once.
     *
     * @return string
     */
    protected function compact_logo_url(): string {
        $url = $this->get_compact_logo_url();

        return ($url instanceof \moodle_url) ? $url->out(false) : '';
    }

    /**
     * The page header: the category showcase's hero on its page, Boost Union's header everywhere else.
     *
     * Core calls the course renderer's course_category() before it renders the header, and
     * that method arms a {@see category_hero} for the page when the showcase renders; here
     * the hero replaces the whole of Boost Union's header - the context header with its h1,
     * the breadcrumb, the header actions - so the page has exactly one h1, the hero's. The
     * header actions are core's own, handed to the hero's template as the parent hands them
     * to core's; the renderable knows nothing about them, because they are the renderer's
     * concern. The breadcrumb is not handed over: the hero draws its own trail from the
     * category's path, because core empties the breadcrumb as soon as the page's URL carries
     * a parameter besides the category. With no hero armed the parent draws its header
     * unchanged.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        $hero = category_hero::current();
        if ($hero === null) {
            return parent::full_header();
        }
        $context = $hero->export_for_template($this);
        $context->headeractions = $this->page->get_header_actions();
        $context->headerextras = $this->page->get_header_extras();
        $context->hasheaderextras = !empty($context->headerextras);
        return $this->render_from_template('theme_dimensions/category_hero', $context);
    }

    /**
     * Make the navbar dark in the brand colour (bg-primary), through Boost Union's own mechanism.
     *
     * Boost Union's navbar include writes these two keys from its navbarcolor setting and from a
     * flavour's look_navbarcolor. Setting them here rather than recolouring the navbar with CSS is
     * what keeps every component inside it coherent: 'dark' is a full Bootstrap colour mode, so the
     * notification popovers, the edit-mode switch and the mobile toggler all get a palette that
     * reads, instead of inheriting a half-set of inverted tokens and rendering white on white.
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
        if (($context['navbarcolorclasses'] ?? '') !== 'bg-body' || ($context['databstheme'] ?? '') !== '') {
            return $context;
        }
        $context['navbarcolorclasses'] = 'bg-primary';
        $context['databstheme'] = 'dark';

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
     * @param array $context The layout template context.
     * @return array
     */
    protected function apply_name_in_usermenu(array $context): array {
        if (empty($context['showfullnameinusermenu'])) {
            $context['showfullnameinusermenu'] = true;
        }
        unset($context['usermenu']['userfirstname']);

        return $context;
    }

    /**
     * Returns HTML attributes to use within the body tag, adding the focus mode, dark logo and reading classes.
     *
     * Boost Union calls this from inside its layout file, with the page's cm already set,
     * which is why focus mode needs no hook for the class and never meets
     * moodle_page::add_body_class()'s state guard. The classes are appended to
     * $additionalclasses BEFORE delegating on purpose: Boost Union's own override does not
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

        if (\theme_dimensions\local\focusmode::applies($this->page)) {
            $additionalclasses[] = 'dimensions-focusmode';
            if (\theme_dimensions\local\focusmode::wants_expanded()) {
                $additionalclasses[] = 'dimensions-focusmode-expanded';
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
     * The position is the whole point and it is fixed by core's assembly order: the
     * subsystems' callbacks run first, so message_popup's notification bell is already in
     * the string when a plugin's callback would be appended - to the RIGHT of it, which is
     * the one place this button may not go. A theme render_navbar_output() in lib.php
     * therefore cannot reach the position, and copying Boost Union's navbar template to
     * place the button by hand would mean re-diffing 160 lines for ever. Splicing the
     * rendered button in front of the bell's own container is the only route left.
     *
     * With the id absent - notifications disabled, or the processor switched off - the
     * button is prepended instead, so the worst case is a position and never a lost
     * control.
     *
     * The visitor's actions ({@see guestnav}) are APPENDED: Boost Union's navbar prints this output
     * immediately before the user menu, which render_from_template() empties for the same visitors,
     * so the end of this string is the right end of the navbar.
     *
     * The context bar ({@see readingbar}) is appended after them, and its place in the string does not
     * matter: scss/readingbar.scss lays it over the whole nav, the nearest positioned ancestor (nothing
     * between #usernavigation and the nav is positioned, in core or in Boost Union). The client module
     * that runs the band, the swap and auto-hide is armed here too, on every reading page, with or
     * without the layer: on the course page it is what paints the reader's completion on the band. A reader core
     * lets edit the page ({@see readingbar::edit_applies()}) gets core's edit mode switch in the layer, rendered
     * again by core's own edit_switch(): the navbar's switch is hidden under the layer while it is shown (D5).
     *
     * @return string
     */
    public function navbar_plugin_output() {
        $output = parent::navbar_plugin_output();

        if (\theme_dimensions\local\focusmode::offers_entry($this->page)) {
            $button = $this->render_from_template('theme_dimensions/focusmode_entry', [
                'actionurl' => (new \moodle_url('/theme/dimensions/focus.php'))->out(false),
                'sesskey' => sesskey(),
                'cmid' => (int) $this->page->cm->id,
                'label' => get_string('focusmode_enter', 'theme_dimensions'),
                'title' => get_string('focusmode_enter_title', 'theme_dimensions'),
            ]);
            $output = $this->splice_focus_entry($output, $button);
        }

        if (guestnav::applies($this->page)) {
            $output .= $this->render_from_template('theme_dimensions/navbar_guest', guestnav::context());
        }

        if (readingbar::applies($this->page)) {
            if (readingbar::context_applies($this->page)) {
                $layer = readingbar::context($this->page);
                if ($layer['canedit']) {
                    // Core's own switch, rendered again: a second id, its own form and its own module (D5).
                    $layer['editswitch'] = (string) $this->edit_switch();
                }
                $output .= $this->render_from_template('theme_dimensions/navbar_readingbar', $layer);
            }
            $this->page->requires->js_call_amd('theme_dimensions/readingbar', 'init', [readingbar::client_config($this->page)]);
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
     * Core's activity navigation, produced on the formats which normally suppress it.
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
     * footer is printed by footer() into the focus page as into any other, so a second row of
     * our own would put two navigations under the text. The question is core's own predicate,
     * asked while the layout renders: a page that later prints a sticky footer of its own loses
     * core's linear navigation and this one alike, exactly as the ordinary page does.
     *
     * @return string
     */
    public function focusmode_activity_navigation(): string {
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
}
