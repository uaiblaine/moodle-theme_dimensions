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
 * Theme Dimensions - Tests for what this theme's core renderer draws
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\population;
use theme_dimensions\local\login;
use theme_dimensions\output\category_hero;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for what this theme's core renderer draws: the login page, the navbar's name and the category page's header.
 *
 * These render the real thing - core's renderable, this theme's renderer, the templates and,
 * through header(), Boost Union's layout files - because the rules that matter are only visible
 * in the output: that no identity provider's image is ever fetched from the provider, that
 * switching the login page off gives Boost Union's page back exactly, image and all, and that the
 * category hero replaces the secondary navigation band rather than adding to it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
final class core_renderer_test extends \advanced_testcase {
    /** @var array The Google issuer as auth_loginsteps hands it to the page: a remote image, and the servicetype beside it. */
    private const GOOGLE = [
        'url' => 'https://www.example.com/moodle/auth/loginsteps/login.php?id=1',
        'iconurl' => 'https://accounts.google.com/favicon.ico',
        'name' => 'Google',
        'servicetype' => 'google',
    ];

    /** @var array A provider this theme has no mark for, with an image on its own servers, as auth_oauth2 would list it. */
    private const OTHER = [
        'url' => 'https://www.example.com/moodle/auth/oauth2/login.php?id=2',
        'iconurl' => 'https://idp.example.org/mark.png',
        'name' => 'Other IdP',
    ];

    /**
     * Build this theme's core renderer on a page of the given layout.
     *
     * @param string $url The page's url; the login page unless a test says otherwise.
     * @param string $layout The page layout; the login layout unless a test says otherwise.
     * @return core_renderer
     */
    private function renderer(string $url = '/login/index.php', string $layout = 'login'): core_renderer {
        global $PAGE, $OUTPUT, $SITE;

        $PAGE->set_context(\context_system::instance());
        // The course is set before the theme is forced: initialise_theme_and_output() sets it to the
        // site otherwise, and set_course() refuses to run once a theme is in place.
        $PAGE->set_course($SITE);
        $PAGE->set_url($url);
        $PAGE->set_pagelayout($layout);
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        // The target is explicit because PHPUnit runs as a CLI script, and without it the factory
        // answers with core's CLI renderer rather than the theme's. It also becomes the global,
        // because Boost Union's layout file - which header() includes - renders through $OUTPUT.
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);

        return $OUTPUT;
    }

    /**
     * Core's login renderable, with the identity providers a site would list.
     *
     * @param array $identityproviders The providers, as loginpage_idp_list() returns them.
     * @return \core_auth\output\login
     */
    private function form(array $identityproviders = []): \core_auth\output\login {
        $form = new \core_auth\output\login(['manual'], '');
        $form->identityproviders = $identityproviders;

        return $form;
    }

    /**
     * The provider's mark is drawn locally: the glyph is there, the provider's image is not.
     *
     * The image url is Google's favicon on Google's servers. Rendering it would make every
     * visitor's browser announce itself to Google before the person has chosen anything.
     *
     * @return void
     */
    public function test_the_provider_mark_is_drawn_locally(): void {
        $this->resetAfterTest();

        $html = $this->renderer()->render_login($this->form([self::GOOGLE]));

        $this->assertStringContainsString('fa-brands fa-google', $html);
        $this->assertStringContainsString('Sign in with Google Workspace', $html);
        $this->assertStringContainsString('href="https://www.example.com/moodle/auth/loginsteps/login.php?id=1"', $html);
        $this->assertStringNotContainsString('accounts.google.com', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    /**
     * A provider this theme has no mark for gets its name and nothing from its servers.
     *
     * @return void
     */
    public function test_an_unknown_provider_shows_no_remote_image(): void {
        $this->resetAfterTest();

        $html = $this->renderer()->render_login($this->form([self::OTHER]));

        $this->assertStringContainsString('Sign in with Other IdP', $html);
        $this->assertStringNotContainsString('idp.example.org', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('fa-brands', $html);
    }

    /**
     * An icon this site serves itself is rendered - the control for the two tests above.
     *
     * @return void
     */
    public function test_an_icon_this_site_serves_is_rendered(): void {
        global $CFG;
        $this->resetAfterTest();

        $local = self::OTHER;
        $local['iconurl'] = $CFG->wwwroot . '/theme/image.php/boost/auth_other/1/icon';

        $html = $this->renderer()->render_login($this->form([$local]));

        $this->assertStringContainsString('<img class="dimensions-login-icon" src="' . $local['iconurl'] . '"', $html);
    }

    /**
     * Switching the page off hands the form back to Boost Union, exactly as it is.
     *
     * Exactly includes the provider's image: Boost Union's page renders core's iconurl, which is
     * the whole reason the custom login page exists. Asserting it here is what proves the page rendered
     * is Boost Union's and not a variant of ours.
     *
     * @return void
     */
    public function test_switching_the_page_off_hands_the_form_back_to_boost_union(): void {
        $this->resetAfterTest();

        set_config('loginpage', login::SELECT_NO, 'theme_dimensions');

        $html = $this->renderer()->render_login($this->form([self::GOOGLE]));

        $this->assertStringContainsString('theme_boost_union-loginform', $html);
        $this->assertStringContainsString('accounts.google.com/favicon.ico', $html);
        $this->assertStringNotContainsString('dimensions-login', $html);
    }

    /**
     * The form keeps every hook core's Behat step and this theme's CPF module rely on.
     *
     * @return void
     */
    public function test_the_form_keeps_the_hooks_core_and_the_cpf_module_rely_on(): void {
        $this->resetAfterTest();

        $html = $this->renderer()->render_login($this->form());

        $this->assertStringContainsString('id="login"', $html);
        $this->assertStringContainsString('id="anchor"', $html);
        $this->assertStringContainsString('name="logintoken"', $html);
        $this->assertStringContainsString('id="username"', $html);
        $this->assertStringContainsString('id="password"', $html);
        $this->assertStringContainsString('id="loginbtn"', $html);
        $this->assertStringContainsString('action="https://www.example.com/moodle/login/index.php"', $html);
        // No provider, no separator: the local form stands alone.
        $this->assertStringNotContainsString('dimensions-login-separator', $html);
        $this->assertStringNotContainsString('dimensions-login-route-lead', $html);
    }

    /**
     * The sign-up link follows core's own answer on self registration.
     *
     * @return void
     */
    public function test_the_sign_up_link_follows_core(): void {
        $this->resetAfterTest();

        // One renderer for both renders: the page can be set up once per test, and the
        // renderable reads registerauth when it is constructed.
        $renderer = $this->renderer();

        set_config('registerauth', 'email');
        $html = $renderer->render_login($this->form());
        $this->assertStringContainsString('Create my account', $html);
        $this->assertStringContainsString('dimensions-login-signup', $html);
        $button = '<a class="btn btn-outline-primary w-100" href="https://www.example.com/moodle/login/signup.php">';
        $this->assertStringContainsString($button, $html);
        // A block of its own, after an "or": with no provider the form still precedes it.
        $this->assertSame(1, substr_count($html, 'dimensions-login-separator'));

        set_config('registerauth', '');
        $html = $renderer->render_login($this->form());
        $this->assertStringNotContainsString('Create my account', $html);
        $this->assertStringNotContainsString('dimensions-login-signup', $html);
        $this->assertStringNotContainsString('dimensions-login-separator', $html);
    }

    /**
     * The hero above the form carries the brand and the kicker for the screens that hide the aside.
     *
     * The kicker is the panel's, so a kicker typed into the settings reaches the band too.
     *
     * @return void
     */
    public function test_the_hero_carries_the_brand_and_the_kicker_for_small_screens(): void {
        $this->resetAfterTest();

        set_config('loginpanelkicker', 'Ensino a distância', 'theme_dimensions');

        $html = $this->renderer()->render_login($this->form());

        $this->assertStringContainsString('<div class="dimensions-login-hero">', $html);
        $this->assertStringContainsString('<div class="dimensions-login-brand d-lg-none">', $html);
        $this->assertStringContainsString('<span class="dimensions-login-wordmark">', $html);
        $this->assertStringContainsString('<p class="dimensions-login-kicker d-lg-none">Ensino a distância</p>', $html);
        $heading = '<h1 class="dimensions-login-heading">Sign in to the learning platform</h1>';
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-login-hero">.*?' . preg_quote($heading, '~') . '\s*</div>~s',
            $html
        );
    }

    /**
     * The second wrong password in a row suggests resetting it; the first does not.
     *
     * Each render with core's invalid-credentials error is one attempt, which is how the real page
     * behaves: core redirects after a failed post and renders the error once. A render without an
     * error - the page reloaded - shows no hint whatever the count.
     *
     * @return void
     */
    public function test_the_second_wrong_password_suggests_a_reset(): void {
        $this->resetAfterTest();

        $renderer = $this->renderer();

        $first = $this->form();
        $first->set_error('x', AUTH_LOGIN_FAILED);
        $html = $renderer->render_login($first);
        $this->assertStringContainsString('id="loginerrormessage"', $html);
        $this->assertStringNotContainsString('dimensions-login-resethint', $html);

        $second = $this->form();
        $second->set_error('x', AUTH_LOGIN_FAILED);
        $html = $renderer->render_login($second);
        $this->assertStringContainsString('dimensions-login-resethint', $html);
        $this->assertStringContainsString('Reset my password', $html);
        $this->assertStringContainsString('href="https://www.example.com/moodle/login/forgot_password.php"', $html);

        $html = $renderer->render_login($this->form());
        $this->assertStringNotContainsString('dimensions-login-resethint', $html);
    }

    /**
     * Only what core reports as an invalid login counts - the control for the test above.
     *
     * On the real page core folds every credential failure - a wrong password, an unknown
     * username, a suspended account, a lockout - into AUTH_LOGIN_FAILED before set_error(), and
     * only two codes keep their own: a failed captcha and an unauthorised login. Neither carries a
     * title, so two failed captchas must neither count nor show the hint - a missing captcha says
     * nothing about a password the person may have forgotten. The captcha code is used because it
     * is one login/index.php really delivers; a lockout is not, it arrives as an invalid login.
     *
     * @return void
     */
    public function test_only_an_invalid_login_counts_towards_the_reset_hint(): void {
        global $SESSION;
        $this->resetAfterTest();

        $renderer = $this->renderer();
        unset($SESSION->{login::FAILURES_SESSION_KEY});

        foreach ([1, 2] as $attempt) {
            $form = $this->form();
            $form->set_error('Captcha missing', AUTH_LOGIN_FAILED_RECAPTCHA);
            $html = $renderer->render_login($form);
            $this->assertStringContainsString('Captcha missing', $html, "attempt $attempt");
            $this->assertStringNotContainsString('dimensions-login-resethint', $html, "attempt $attempt");
        }
        $this->assertFalse(isset($SESSION->{login::FAILURES_SESSION_KEY}));
    }

    /**
     * The separator between the provider and the form needs both of them.
     *
     * With core's showloginform off the provider stands alone: no form, and no "or" leading to
     * nothing. The positive half first, so the assertion is about the conjunction and not about a
     * separator that never renders.
     *
     * @return void
     */
    public function test_the_separator_needs_both_a_provider_and_the_local_form(): void {
        $this->resetAfterTest();

        $renderer = $this->renderer();

        $html = $renderer->render_login($this->form([self::GOOGLE]));
        $this->assertStringContainsString('id="login"', $html);
        $this->assertSame(1, substr_count($html, 'dimensions-login-separator'));

        set_config('showloginform', 0);
        $html = $renderer->render_login($this->form([self::GOOGLE]));
        $this->assertStringContainsString('fa-brands fa-google', $html);
        $this->assertStringNotContainsString('id="login"', $html);
        $this->assertStringNotContainsString('dimensions-login-separator', $html);
    }

    /**
     * Every page on the login layout carries the panel, not only the login page.
     *
     * Core designed the split layout for the whole login flow - password recovery, sign-up, the
     * MFA challenge - and Boost Union's layout file renders the same template for all of them, so
     * the panel belongs to the whole flow, by design. This pins that the hook fires on the password
     * recovery page too; if the decision is ever reversed, this is the test to invert.
     *
     * @return void
     */
    public function test_every_page_of_the_login_flow_carries_the_panel(): void {
        global $PAGE;
        $this->resetAfterTest();

        $renderer = $this->renderer('/login/forgot_password.php');
        $PAGE->set_title('Forgotten password');
        $PAGE->set_heading('Forgotten password');
        $this->assertSame('login-forgot_password', $PAGE->pagetype);

        $html = $renderer->header();

        $this->assertStringContainsString('dimensions-login-panel', $html);
        $this->assertStringContainsString(login::BODY_CLASS, $html);
    }

    /**
     * With the page on, the panel is the institutional one.
     *
     * No logo is stored on the test site, so the wordmark - the site's short name - is what shows.
     *
     * @return void
     */
    public function test_the_panel_is_the_institutional_one_when_the_page_is_on(): void {
        $this->resetAfterTest();

        // The renderer first: the panel's notice goes through format_text(), whose filters touch
        // $PAGE->theme, and the page cannot be set up after that.
        $renderer = $this->renderer();
        $context = login::layout_context(['bodyattributes' => 'class="pagelayout-login"', 'sitename' => 'Acme']);
        $html = $renderer->render_from_template('core/login_panel', $context);

        $this->assertStringContainsString('dimensions-login-panel', $html);
        $this->assertStringContainsString('<p class="dimensions-login-wordmark">Acme</p>', $html);
        $this->assertStringContainsString(get_string('login_panel_kicker', 'theme_dimensions'), $html);
        $this->assertStringContainsString(get_string('login_panel_title', 'theme_dimensions'), $html);
        $this->assertStringContainsString(get_string('login_panel_foot', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString('login-layout-left-content', $html);
    }

    /**
     * Without the flag, the panel is Boost Union's, copied verbatim.
     *
     * The setting is on here - it is the flag in the context which decides, so that the copy of
     * Boost Union's partial is exercised and not just carried.
     *
     * @return void
     */
    public function test_the_panel_stays_boost_unions_without_the_flag(): void {
        $this->resetAfterTest();

        $html = $this->renderer()->render_from_template('core/login_panel', [
            'hasleftinstructions' => true,
            'leftinstructionsupper' => '<p>Welcome to our platform.</p>',
            'leftinstructionslower' => '',
        ]);

        $this->assertStringContainsString('login-layout-left-instructions-upper', $html);
        $this->assertStringContainsString('<p>Welcome to our platform.</p>', $html);
        $this->assertStringNotContainsString('dimensions-login-panel', $html);
    }

    /**
     * The whole login layout is forced side by side, and carries the panel, while the page is on.
     *
     * This goes through the real layout: Boost Union's layout/login.php, set to its LEGACY
     * arrangement, rendered by header(). It pins the one line that wires the layout hook up in
     * render_from_template(), which no test of the pure context function can reach.
     *
     * @return void
     */
    public function test_the_login_layout_is_forced_side_by_side_when_the_page_is_on(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('loginarrangement', 'legacy', 'theme_boost_union');
        $renderer = $this->renderer();
        $PAGE->set_title('Log in');
        $PAGE->set_heading('Log in');

        $html = $renderer->header();

        $this->assertStringContainsString(login::BODY_CLASS, $html);
        $this->assertStringContainsString('theme_boost_union-loginarrangement-sidebyside', $html);
        $this->assertStringNotContainsString('theme_boost_union-loginarrangement-legacy', $html);
        $this->assertStringContainsString('login-layout-left', $html);
        $this->assertStringContainsString('dimensions-login-panel', $html);
    }

    /**
     * With the page off, the layout is whatever Boost Union was told - the control for the above.
     *
     * @return void
     */
    public function test_the_login_layout_is_boost_unions_when_the_page_is_off(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('loginpage', login::SELECT_NO, 'theme_dimensions');
        set_config('loginarrangement', 'legacy', 'theme_boost_union');
        $renderer = $this->renderer();
        $PAGE->set_title('Log in');
        $PAGE->set_heading('Log in');

        $html = $renderer->header();

        $this->assertStringContainsString('theme_boost_union-loginarrangement-legacy', $html);
        $this->assertStringNotContainsString(login::BODY_CLASS, $html);
        $this->assertStringNotContainsString('dimensions-login-panel', $html);
    }

    /**
     * The names in the navbar's name block, for the current user, through a real page header.
     *
     * Every occurrence is returned, de-duplicated, so a test asserting one name also proves no
     * other name is drawn there. The block is scoped by its class because the same name is also in
     * the user menu's "logged in as" block, which this theme switches on.
     *
     * @return array The distinct names, in the order they appear.
     */
    private function navbar_names(): array {
        global $PAGE;

        $GLOBALS['PAGE'] = new \moodle_page();
        $renderer = $this->renderer('/my/', 'standard');
        $PAGE->set_title('Dashboard');
        $PAGE->set_heading('Dashboard');
        $html = $renderer->header();

        preg_match_all('~<span class="dimensions-usermenu-name[^"]*">(.*?)</span>~s', $html, $matches);

        return array_values(array_unique(array_map('trim', $matches[1])));
    }

    /**
     * The navbar shows the name core composed: fullname() under the site's fullnamedisplay.
     *
     * The theme composes nothing. Beside the avatar is usermenu.userfullname as core wrote it, so
     * with fullnamedisplay at the alternate name the navbar shows that column whatever the civil
     * name - which is how the service name local_rank keeps there reaches it.
     *
     * @return void
     */
    public function test_the_navbar_shows_the_name_core_composed(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        // Created under a sink, so no user_created observer rewrites the alternate names set here.
        $sink = $this->redirectEvents();
        $perovano = $generator->create_user(
            ['firstname' => 'Antônio', 'lastname' => 'Perovano', 'alternatename' => 'Cel. Perovano']
        );
        $ribeiro = $generator->create_user(
            ['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'alternatename' => 'Maj. Ribeiro']
        );
        $cardoso = $generator->create_user(
            ['firstname' => 'Bruno', 'lastname' => 'Cardoso', 'alternatename' => '']
        );
        $sink->close();

        // Precondition: under core's default format the same account shows its civil name.
        set_config('fullnamedisplay', 'language');
        $this->setUser($perovano);
        $this->assertSame(['Antônio Perovano'], $this->navbar_names());

        set_config('fullnamedisplay', 'alternatename');
        $this->assertSame(['Cel. Perovano'], $this->navbar_names());

        // Control: another account shows its own alternate name, so the line above cannot pass
        // against a fixed string.
        $this->setUser($ribeiro);
        $this->assertSame(['Maj. Ribeiro'], $this->navbar_names());

        // An empty alternate name leaves core's first name alone, which is what every account
        // without a service name shows under this format.
        $this->setUser($cardoso);
        $this->assertSame(['Bruno'], $this->navbar_names());
    }

    /**
     * A visitor who is not logged in gets the institutional navbar too.
     *
     * Core never sets usermenu.userfullname for a visitor, so a guard on that key skipped the navbar
     * style on every public page. The control is the logged-in context, which carries the same style.
     *
     * The navbar template is rendered with the layout's colour keys rather than through header():
     * Boost Union's layouts load includes/navbar.php with require_once, so only the first header()
     * of a PHPUnit process ever writes those keys.
     *
     * @return void
     */
    public function test_the_navbar_style_reaches_a_visitor_who_is_not_logged_in(): void {
        $this->resetAfterTest();
        set_config('navbarstyle', THEME_DIMENSIONS_SETTING_NAVBARSTYLE_NAVY, 'theme_dimensions');

        $renderer = $this->renderer('/my/', 'standard');
        $anonymous = $this->navbar_html($renderer, ['unauthenticateduser' => ['content' => 'You are not logged in.']]);
        $this->assertStringContainsString('navbar fixed-top bg-primary', $anonymous);
        $this->assertStringContainsString('data-bs-theme="dark"', $anonymous);

        $loggedin = $this->navbar_html($renderer, ['userfullname' => 'Ana Souza']);
        $this->assertStringContainsString('navbar fixed-top bg-primary', $loggedin);
    }

    /**
     * The opening tag of the navbar, rendered with Boost Union's light navbar keys.
     *
     * @param core_renderer $renderer The theme's renderer.
     * @param array $usermenu The user menu part of the layout context.
     * @return string
     */
    private function navbar_html(core_renderer $renderer, array $usermenu): string {
        $html = $renderer->render_from_template('theme_boost/navbar', [
            'navbarcolorclasses' => 'bg-body',
            'databstheme' => '',
            'usermenu' => $usermenu,
        ]);
        $this->assertSame(1, preg_match('~<nav class="navbar [^>]*>~', $html, $matches), 'The navbar is drawn.');

        return $matches[0];
    }

    /**
     * Leave no hero armed behind: the registry is static.
     */
    protected function tearDown(): void {
        category_hero::reset();
        parent::tearDown();
    }

    /**
     * Render the real page header of a category page for the current user, wired as course/index.php wires it.
     *
     * The course renderer's course_category() runs first, as core runs it, so that the hero
     * is armed - or not - by the same decision the page makes.
     *
     * @param int $categoryid The category.
     * @return string The rendered header (the whole start of the page, through Boost Union's layout).
     */
    private function category_header(int $categoryid): string {
        global $PAGE, $OUTPUT;

        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_url(new \moodle_url('/course/index.php', ['categoryid' => $categoryid]));
        $PAGE->set_pagetype('course-index-category');
        $PAGE->set_pagelayout('coursecategory');
        $PAGE->add_body_class('limitedwidth');
        $PAGE->set_heading(\core_course_category::get($categoryid)->get_formatted_name());
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);
        $courserenderer = $PAGE->get_renderer('core', 'course', RENDERER_TARGET_GENERAL);
        $courserenderer->course_category($categoryid);
        $PAGE->set_secondary_active_tab('categorymain');

        return $OUTPUT->header();
    }

    /**
     * The AMD requirements the page has collected, as strings.
     *
     * page_requirements_manager keeps them in a protected array until get_end_code() renders
     * them, and get_end_code() has side effects a test should not pay for; reflection reads the
     * same list without them.
     *
     * @return string Every queued AMD call, concatenated.
     */
    private function collected_amd(): string {
        global $PAGE;
        $property = new \ReflectionProperty($PAGE->requires, 'amdjscode');
        return implode("\n", (array) $property->getValue($PAGE->requires));
    }

    /**
     * Run the showcase's own renderer method, as course/index.php runs it, and collect nothing else.
     *
     * @param int $categoryid The category.
     * @return void
     */
    private function run_course_category(int $categoryid): void {
        global $PAGE;

        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_url(new \moodle_url('/course/index.php', ['categoryid' => $categoryid]));
        $PAGE->set_pagetype('course-index-category');
        $PAGE->set_pagelayout('coursecategory');
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $PAGE->get_renderer('core', 'course', RENDERER_TARGET_GENERAL)->course_category($categoryid);
    }

    /**
     * The client module is armed for a real account and never for the guest.
     *
     * The guest is routed to this shell on purpose: core counts it as logged in, so request.php
     * sends it to the logged-in showcase rather than the anonymous one. All three of the showcase's web
     * services refuse the guest, and the module's first act is to add the body class that hides
     * the no-JavaScript "Filter" submit and to hide the numbered pager: the only two controls
     * that work for a guest at all. Armed there, it answers every chip, every typed term and
     * every "load more" with an exception dialogue over a page that used to page and filter.
     *
     * The fix belongs here and never in the services' guest refusal, which is what keeps the
     * guest account from writing a favourite onto a shared login.
     */
    public function test_the_client_module_is_armed_for_a_real_account_and_not_for_the_guest(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);

        $this->setUser($generator->create_user());
        $this->run_course_category((int) $category->id);
        $this->assertStringContainsString(
            'theme_dimensions/categoryvitrine',
            $this->collected_amd(),
            'A real account gets the client module.'
        );

        $this->resetAfterTest();
        category_hero::reset();
        $this->setGuestUser();
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->run_course_category((int) $category->id);
        $this->assertStringNotContainsString(
            'theme_dimensions/categoryvitrine',
            $this->collected_amd(),
            'The guest is served the page it can actually use: no module, no hidden controls.'
        );
    }

    /**
     * The category page renders the hero with the category's tabs inside it, one h1, and no band below.
     *
     * The band is the drift test: Boost Union's layout writes the secondary navigation under
     * the key this theme switches off, and if that key is ever renamed the band comes back
     * under the hero's own tab strip - two tab strips on one page, which this reads as more
     * than one more menu.
     *
     * @return void
     */
    public function test_the_category_page_renders_the_hero_with_the_tabs_and_no_band(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        $this->setAdminUser();
        branding::save((int) $category->id, (object) ['chamada' => 'A tagline for the header']);

        $html = $this->category_header((int) $category->id);

        /* The class attribute carries the layout and nothing about the colour: the band paints
           itself from the inline custom properties beside it, a CSS-context sink pinned here as it
           is rendered. */
        $this->assertStringContainsString('class="header-maxwidth d-print-none dim-hero dim-hero--solid"', $html);
        $this->assertStringNotContainsString('dim-hero--navy', $html, 'The palette class is retired.');
        $this->assertMatchesRegularExpression(
            '/<header[^>]*class="[^"]*dim-hero dim-hero--solid"[^>]*'
                . 'style="(--dim-hero-[a-z-]+: (#[0-9a-f]{6}|rgba\([0-9, .]+\));\s*)+"/',
            $html,
            'The hero renders its style attribute, and it holds nothing but sanitised colours.'
        );
        $this->assertStringContainsString('<h1 class="dim-hero__title">Showcase Category</h1>', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'Exactly one h1: the hero\'s.');
        $this->assertStringContainsString('A tagline for the header', $html);
        $this->assertStringContainsString(get_string('categoryhero_pencil', 'theme_dimensions'), $html);
        $this->assertStringContainsString('dim-hero__tabs', $html);
        // Two more menus on the page: the primary navigation's and the hero's - never a third, the band's.
        $this->assertSame(2, substr_count($html, '<nav class="moremenu navigation">'));
        $this->assertStringNotContainsString('class="secondary-navigation', $html);
        // The tabs by their node keys: the category's own page and its settings, and this theme's editor.
        $this->assertStringContainsString('data-key="categorymain"', $html);
        $this->assertStringContainsString('data-key="edit"', $html);
        $this->assertStringContainsString('data-key="categoryappearance"', $html);
        $this->assertStringContainsString(get_string('categoryhero_nav', 'theme_dimensions'), $html);
        $this->assertStringContainsString('name="' . category_hero::PARAM_FOLD . '"', $html);
    }

    /**
     * With the showcase off the category page keeps Boost Union's header and its band - the control of the drift test.
     *
     * @return void
     */
    public function test_a_switched_off_showcase_keeps_boost_unions_header_and_band(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        $this->setAdminUser();
        set_config(population::SETTING_ENABLED, '0', 'theme_dimensions');

        $html = $this->category_header((int) $category->id);

        $this->assertNull(category_hero::current());
        $this->assertStringNotContainsString('dim-hero', $html);
        $this->assertStringContainsString('id="page-header"', $html);
        $this->assertStringContainsString('class="secondary-navigation', $html, 'Control: the band the hero switches off is live.');
        $this->assertSame(2, substr_count($html, '<nav class="moremenu navigation">'), 'The primary navigation and the band.');
    }

    /**
     * A learner's hero carries the single category tab and no pencil.
     *
     * @return void
     */
    public function test_a_learners_hero_carries_the_category_tab_and_no_pencil(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        $this->setUser($generator->create_user());

        $html = $this->category_header((int) $category->id);

        $this->assertStringContainsString('dim-hero__tabs', $html);
        $this->assertStringContainsString('data-key="categorymain"', $html);
        $this->assertStringNotContainsString('data-key="edit"', $html);
        $this->assertStringNotContainsString('data-key="categoryappearance"', $html);
        $this->assertStringNotContainsString(get_string('categoryhero_pencil', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString('class="secondary-navigation', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    /**
     * The opening tag of the body in a rendered page.
     *
     * @param string $html A whole page, as header() renders it.
     * @return string The tag, attributes and all.
     */
    private function body_tag(string $html): string {
        $this->assertSame(1, preg_match('~<body\b[^>]*>~', $html, $matches), 'The page has a body tag.');

        return $matches[0];
    }

    /**
     * Boost Union's compact logo reaches the panel and the form's band, and nothing else.
     *
     * End to end: the same url travels two ways - the panel's img and the band's img in this
     * theme's own form column - and the body carries no style attribute for it.
     *
     * @return void
     */
    public function test_the_compact_logo_reaches_the_panel_and_the_band(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('logocompact', 'brand.png', 'theme_boost_union');

        $renderer = $this->renderer();
        $PAGE->set_title('Sign in');
        $PAGE->set_heading('Sign in');

        // Asked of the renderer rather than spelled out: the assertion is that ONE url reaches both
        // places, and re-typing Boost Union's own url builder here would only pin a copy of it.
        $expected = $renderer->get_compact_logo_url()->out(false);
        $this->assertStringContainsString('brand.png', $expected);

        $form = $renderer->render_login($this->form());
        $this->assertStringContainsString('<img class="dimensions-login-logo" src="' . $expected . '"', $form);
        $this->assertStringNotContainsString('dimensions-login-wordmark', $form);

        $html = $renderer->header();
        $this->assertStringContainsString('<img class="dimensions-login-logo" src="' . $expected . '"', $html);
        $this->assertStringNotContainsString('style=', $this->body_tag($html));
    }

    /**
     * Without a compact logo the panel shows the wordmark.
     *
     * The control for the test above: without it, a chain that resolved nothing at all would pass
     * every assertion about the wordmark that a working chain passes.
     *
     * @return void
     */
    public function test_without_a_compact_logo_the_wordmark_stands(): void {
        global $PAGE;
        $this->resetAfterTest();
        unset_config('logocompact', 'theme_boost_union');

        $renderer = $this->renderer();
        $PAGE->set_title('Sign in');
        $PAGE->set_heading('Sign in');
        $html = $renderer->header();

        $this->assertStringContainsString('dimensions-login-wordmark', $html);
    }

    /**
     * A page of auth_loginsteps left on the login layout gets the flow's panel and nothing of its own.
     *
     * That plugin draws its access screens on its own shell, on the embedded layout (the test
     * below). A page of it still rendered on the login layout - an older release, or a screen it
     * has not moved - is any page of the login flow to this theme (decision 10): the class, the
     * panel with its copy and its logo, and no class, attribute or property of its own. The panel
     * and the logo inside it are the control: the bare body tag belongs to a page the hook really
     * rewrote.
     *
     * @return void
     */
    public function test_a_plugin_page_on_the_login_layout_gets_the_flows_panel_and_nothing_of_its_own(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('logocompact', 'brand.png', 'theme_boost_union');

        $renderer = $this->renderer('/auth/loginsteps/register.php');
        $PAGE->set_title('Registration');
        $PAGE->set_heading('Registration');
        $this->assertSame('auth-loginsteps-register', $PAGE->pagetype);
        $expected = $renderer->get_compact_logo_url()->out(false);

        $html = $renderer->header();

        $this->assertStringContainsString('<img class="dimensions-login-logo" src="' . $expected . '"', $html);
        $this->assertStringContainsString(get_string('login_panel_title', 'theme_dimensions'), $html);
        $body = $this->body_tag($html);
        $this->assertStringContainsString(login::BODY_CLASS, $body);
        $this->assertSame(1, substr_count($body, 'theme_dimensions-'), 'The login class is the theme\'s one mark.');
        $this->assertStringNotContainsString('style=', $body);
    }

    /**
     * The plugin's own shell, on the embedded layout, carries nothing of the login page.
     *
     * auth_loginsteps draws its reset, its registration, its identity door and its own sign-in page
     * on the embedded layout, and the hook is keyed on the login layout's template, so none of
     * them gets the class, the panel or the forced arrangement. The custom login page is on, which is
     * the precondition, and the test above is the control: the same plugin on the login layout.
     *
     * @return void
     */
    public function test_the_plugins_shell_on_the_embedded_layout_carries_nothing_of_the_login_page(): void {
        global $PAGE;
        $this->resetAfterTest();

        $this->assertTrue(login::page_enabled());
        $renderer = $this->renderer('/auth/loginsteps/signin.php', 'embedded');
        $PAGE->set_title('Sign in');
        $PAGE->set_heading('Sign in');
        $this->assertSame('auth-loginsteps-signin', $PAGE->pagetype);

        $html = $renderer->header();

        $this->assertStringContainsString('pagelayout-embedded', $this->body_tag($html));
        $this->assertStringNotContainsString(login::BODY_CLASS, $html);
        $this->assertStringNotContainsString('dimensions-login-panel', $html);
    }

    /**
     * The second factor is drawn on auth_loginsteps' shell, not on the login page, while that is the door.
     *
     * Through the real layout (header() runs layout/login.php). The control is the same page with
     * the plugin's switch off, which keeps the flow's panel (decision 10, narrowed for this page).
     *
     * @return void
     */
    public function test_the_second_factor_stands_on_the_loginsteps_shell(): void {
        global $PAGE;
        $this->resetAfterTest();
        if (!class_exists(\auth_loginsteps\output\core_page_shell::class)) {
            $this->markTestSkipped('auth_loginsteps is not installed.');
        }
        set_config('auth', 'loginsteps');
        set_config('loginshell', '1', 'auth_loginsteps');

        $renderer = $this->renderer('/admin/tool/mfa/auth.php');
        $PAGE->set_title('Multi-factor authentication');
        $this->assertSame('admin-tool-mfa-auth', $PAGE->pagetype);
        $html = $renderer->header();

        $this->assertStringContainsString('<div id="page" class="theme-dimensions-loginshell">', $html);
        $this->assertStringContainsString('data-screen="mfa"', $html);
        $this->assertStringContainsString(s(get_string('loginshell_mfa_title', 'theme_dimensions')), $html);
        $this->assertStringNotContainsString('dimensions-login-panel', $html);
    }

    /**
     * With the plugin's shell switched off, the second factor keeps the login flow's panel.
     *
     * @return void
     */
    public function test_the_second_factor_keeps_the_panel_without_the_shell(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('loginshell', '0', 'auth_loginsteps');

        $renderer = $this->renderer('/admin/tool/mfa/auth.php');
        $PAGE->set_title('Multi-factor authentication');
        $html = $renderer->header();

        $this->assertStringContainsString('dimensions-login-panel', $html);
        $this->assertStringNotContainsString('theme-dimensions-loginshell', $html);
    }
}
