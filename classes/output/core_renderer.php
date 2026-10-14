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

use theme_dimensions\local\coursenotice\builder as coursenotice_builder;
use theme_dimensions\local\login;

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
     * Two things happen here. The AMD module which turns the username field into a CPF field is
     * added whenever the CPF handling applies, whichever page is shown; its messages are handed
     * over as init arguments rather than fetched with a web service call, because the login page
     * is served to users who are not logged in yet and a message which arrives late is a message
     * nobody reads. Then the page itself: with the theme's login page switched off, Boost Union
     * renders its form exactly as configured there; switched on, the form comes from this theme's
     * own template, and every Boost Union login setting that shapes the form stops applying.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        // Read the bypass, which gives an account whose username is not a CPF - the site admin
        // above all - a plain login form to use, and which sticks for the rest of the session so a
        // wrong password does not bounce them back into the CPF form.
        $bypass = login::bypass_requested();

        // If the CPF handling applies, add the AMD module which applies it to the username field.
        if (login::cpf_validation_applies($bypass)) {
            $this->page->requires->js_call_amd(
                'theme_dimensions/logincpf',
                'init',
                [
                    [
                        'invalid' => get_string('logincpfinvalid', 'theme_dimensions'),
                        'digitsonly' => get_string('logincpfdigitsonly', 'theme_dimensions'),
                        'maxdigits' => get_string('logincpfmaxdigits', 'theme_dimensions'),
                        'placeholder' => get_string('logincpfplaceholder', 'theme_dimensions'),
                    ],
                ]
            );
        }

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
     * user_get_user_navigation_info() through core\navigation\output\primary::get_user_menu() - so
     * the navbar shows whatever the site's fullnamedisplay composes. The guard is the context's
     * shape rather than the template's name on purpose: it survives Boost Union renaming or
     * reorganising its layout templates, and it costs one isset() per render.
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

        /* The category showcase's hero draws the category's tabs itself, so the band the layout
           would render under the header is switched off while a hero is armed. Guarded by the
           SHAPE of the context - the key Boost Union's layout writes from the secondary
           navigation - and not by the template's name, for the reason the docblock gives. If
           Boost Union ever renames the key the band comes back beside the hero's tabs, which
           the drift test in core_renderer_test reads as two tab strips on one page. */
        if (is_array($context) && array_key_exists('secondarymoremenu', $context) && category_hero::current() !== null) {
            $context['secondarymoremenu'] = false;
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
     * Make the navbar the institutional navy, through Boost Union's own mechanism.
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
     * @param array $context The layout template context.
     * @return array
     */
    protected function apply_name_in_usermenu(array $context): array {
        if (empty($context['showfullnameinusermenu'])) {
            $context['showfullnameinusermenu'] = true;
        }

        return $context;
    }

    /**
     * Returns HTML attributes to use within the body tag, adding the focus mode classes.
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

        return parent::body_attributes($additionalclasses);
    }

    /**
     * The navbar's plugin output, with the way into focus mode spliced into it.
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
     * @return string
     */
    public function navbar_plugin_output() {
        $output = parent::navbar_plugin_output();

        if (!\theme_dimensions\local\focusmode::offers_entry($this->page)) {
            return $output;
        }

        $button = $this->render_from_template('theme_dimensions/focusmode_entry', [
            'actionurl' => (new \moodle_url('/theme/dimensions/focus.php'))->out(false),
            'sesskey' => sesskey(),
            'cmid' => (int) $this->page->cm->id,
            'label' => get_string('focusmode_enter', 'theme_dimensions'),
            'title' => get_string('focusmode_enter_title', 'theme_dimensions'),
        ]);

        return $this->splice_focus_entry($output, $button);
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
     * @return string
     */
    public function focusmode_activity_navigation(): string {
        $usescourseindex = $this->page->theme->usescourseindex;
        try {
            $this->page->theme->usescourseindex = false;
            return parent::activity_navigation();
        } finally {
            $this->page->theme->usescourseindex = $usescourseindex;
        }
    }
}
