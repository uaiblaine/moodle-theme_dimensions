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
use theme_dimensions\local\darklogo;
use theme_dimensions\local\guestnav;
use theme_dimensions\local\identity;
use theme_dimensions\output\category_hero;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for what this theme's core renderer draws: the login layout, the navbar's name and the category page's header.
 *
 * These render the real thing - core's renderable, this theme's renderer, the templates and,
 * through header(), Boost Union's layout files - because the rules that matter are only visible
 * in the output: that the login page is Boost Union's, that the second factor stands on
 * auth_loginsteps' shell while that is the door, and that the category hero replaces the secondary
 * navigation band rather than adding to it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
final class core_renderer_test extends \advanced_testcase {
    /**
     * @var string The primary navigation as 5.3 draws it: core/primarymoremenu, a React mount around a no-JS
     *      fallback whose nav is "moremenu observed navigation" and so never counted as a plain more menu.
     */
    private const PRIMARY_NAV = 'data-react-component="@moodle/lms/core/nav/PrimaryNav"';

    /**
     * @var string The band as 5.3 draws it: core/secondarymoremenu, a React mount of core's tab bar around a
     *      no-JS fallback that is a plain "moremenu navigation" nav.
     */
    private const BAND_NAV = 'data-react-component="@moodle/lms/core/nav/Nav"';

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
     * The login page is Boost Union's: the theme draws no login form and rewrites nothing of the login layout.
     *
     * The form comes from Boost Union's renderer, and the page from Boost Union's layout/login.php, which the theme's
     * own layout file requires for every page it does not put on auth_loginsteps' shell. Boost Union's legacy
     * arrangement is the control: it reaches the body only while nothing of the theme stands between the setting and
     * the page.
     *
     * @return void
     */
    public function test_the_login_page_is_boost_unions(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config('loginarrangement', 'legacy', 'theme_boost_union');

        $method = new \ReflectionMethod(core_renderer::class, 'render_login');
        $this->assertNotSame(core_renderer::class, $method->getDeclaringClass()->getName());

        $renderer = $this->renderer();
        $PAGE->set_title('Log in');
        $PAGE->set_heading('Log in');
        $form = $renderer->render_login(new \core_auth\output\login(['manual'], ''));
        $this->assertStringContainsString('<div id="theme_boost_union-loginform">', $form);

        $body = $this->body_tag($renderer->header());
        $this->assertStringContainsString('pagelayout-login', $body);
        $this->assertStringContainsString('theme_boost_union-loginarrangement-legacy', $body);
        $this->assertStringNotContainsString('theme_dimensions-login', $body);
    }

    /**
     * A real page header for the current user: the dashboard's, navbar and user menu included.
     *
     * @return string
     */
    private function header_html(): string {
        global $PAGE;

        $GLOBALS['PAGE'] = new \moodle_page();
        $renderer = $this->renderer('/my/', 'standard');
        $PAGE->set_title('Dashboard');
        $PAGE->set_heading('Dashboard');

        return $renderer->header();
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
        $html = $this->header_html();

        preg_match_all('~<span class="dimensions-usermenu-name[^"]*">(.*?)</span>~s', $html, $matches);

        return array_values(array_unique(array_map('trim', $matches[1])));
    }

    /**
     * The navbar shows the name core composed: fullname() under the site's fullnamedisplay.
     *
     * With the identity settings at their defaults the theme shows fullname(), as core's own
     * usermenu.userfullname does, so with fullnamedisplay at the alternate name the navbar shows
     * that column whatever the civil name.
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
     * The toggle carries the theme's full name and not core's first name beside it.
     *
     * Core 5.3 draws userfirstname in a span next to the avatar from xl up, where this theme's
     * core/user_menu_metadata override already shows the full name from md up: two names side by
     * side. The span's display is a Bootstrap utility (d-xl-block, !important), so the stylesheet
     * cannot hide it and the renderer drops the key instead. The control renders core's user menu
     * with that key and no layout around it, which the renderer's guard does not touch: the span is
     * drawn there, so its absence from the page is the theme's doing and not a markup change.
     *
     * @return void
     */
    public function test_the_toggle_shows_the_full_name_and_not_cores_first_name(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Ribeiro']));

        $GLOBALS['PAGE'] = new \moodle_page();
        $renderer = $this->renderer('/my/', 'standard');
        $PAGE->set_title('Dashboard');
        $PAGE->set_heading('Dashboard');
        $html = $renderer->header();

        $this->assertStringContainsString('id="user-menu-toggle"', $html, 'Precondition: the page draws the user menu.');
        $this->assertMatchesRegularExpression('~<span class="dimensions-usermenu-name[^"]*">\s*Ana Ribeiro\s*</span>~', $html);
        $this->assertStringNotContainsString('class="userfirstname', $html);

        $menu = $renderer->render_from_template('core/user_menu', [
            'userfullname' => 'Ana Ribeiro',
            'userfirstname' => 'Ana',
            'avatardata' => [],
            'metadata' => [],
            'items' => [],
            'submenus' => [],
        ]);
        $this->assertStringContainsString('class="userfirstname', $menu, 'Control: core draws the first name from this key.');
    }

    /**
     * The navbar shows the field the administrator chose, and the open menu keeps core's name.
     *
     * The control is the default: with nothing stored the same account shows fullname(), whatever its alternate
     * name. Boost Union's name block reads core's own key, which the theme leaves alone.
     *
     * @return void
     */
    public function test_the_navbar_shows_the_chosen_identity_field(): void {
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $perovano = self::getDataGenerator()->create_user(
            ['firstname' => 'Antônio', 'lastname' => 'Perovano', 'alternatename' => 'Cel. Perovano']
        );
        $cardoso = self::getDataGenerator()->create_user(['firstname' => 'Bruno', 'lastname' => 'Cardoso', 'alternatename' => '']);
        $sink->close();

        $this->setUser($perovano);
        $this->assertSame(['Antônio Perovano'], $this->navbar_names(), 'Control: the default is the full name.');

        set_config(identity::SETTING_NAVBAR, 'alternatename', 'theme_dimensions');
        $this->assertSame(['Cel. Perovano'], $this->navbar_names());
        $this->assertMatchesRegularExpression(
            '~class="[^"]*loggedinas[^"]*"[^>]*>.*?Antônio Perovano~s',
            $this->header_html(),
            'The name block of the open menu is still core\'s full name.'
        );

        // An account without the field shows its full name.
        $this->setUser($cardoso);
        $this->assertSame(['Bruno Cardoso'], $this->navbar_names());
    }

    /**
     * A display pattern is applied on the server: the page never carries the characters it hides.
     *
     * @return void
     */
    public function test_a_masked_navbar_name_never_reaches_the_page_whole(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user(
            ['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'username' => '12345678909']
        ));
        set_config(identity::SETTING_NAVBAR, 'username', 'theme_dimensions');

        $this->assertSame(['12345678909'], $this->navbar_names(), 'Control: without a pattern the username is on the page.');
        $this->assertStringContainsString('12345678909', $this->header_html());

        set_config(identity::SETTING_PATTERNS, 'username=***.###.###-**', 'theme_dimensions');
        $html = $this->header_html();
        $this->assertSame(['•••.456.789-••'], $this->navbar_names());
        $this->assertStringNotContainsString('12345678909', $html);
        $this->assertStringNotContainsString('123.456', $html);
    }

    /**
     * A pattern's literal characters are escaped once by the template, never twice.
     *
     * @return void
     */
    public function test_a_patterns_literals_are_escaped_once_in_the_navbar(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Ribeiro', 'idnumber' => 'ab']));
        set_config(identity::SETTING_NAVBAR, 'idnumber', 'theme_dimensions');
        set_config(identity::SETTING_PATTERNS, 'idnumber=<#&#>', 'theme_dimensions');

        $this->assertSame(['&lt;a&amp;b&gt;'], $this->navbar_names());
    }

    /**
     * Under "log in as" the navbar names the account logged in as, and core's key still names the real user.
     *
     * @return void
     */
    public function test_under_log_in_as_the_navbar_names_the_account_logged_in_as(): void {
        global $USER;
        $this->resetAfterTest();
        $sink = $this->redirectEvents();
        $student = self::getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Ribeiro']);
        $sink->close();

        $this->setAdminUser();
        $admin = fullname($USER);
        $this->assertSame([s($admin)], $this->navbar_names(), 'Control: the administrator sees their own name.');

        \core\session\manager::loginas($student->id, \context_system::instance(), false);
        $this->assertSame(['Ana Ribeiro'], $this->navbar_names());
        $this->assertStringContainsString(s($admin), $this->header_html(), 'Core still names the real user in the menu.');
    }

    /**
     * A visitor who is not logged in gets the institutional navbar too.
     *
     * Core never sets usermenu.userfullname for a visitor, so the navbar style must not be guarded on
     * that key. The control is the logged-in context, which carries the same style.
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
        $this->assertStringContainsString('navbar fixed-top navbar-dark bg-primary', $anonymous);

        $loggedin = $this->navbar_html($renderer, ['userfullname' => 'Ana Souza']);
        $this->assertStringContainsString('navbar fixed-top navbar-dark bg-primary', $loggedin);
    }

    /**
     * The dark navbar is Boost Union's coloured-dark pair, and the bar carries no colour mode of its own.
     *
     * Boost Union 5.3 colours the bar with Bootstrap's navbar schemes (navbar-dark bg-primary and
     * friends), maps core's own navbar elements onto the --bs-navbar-* properties, and keeps the
     * dropdowns in the page's colour mode, which a data-bs-theme on the nav would switch to dark.
     *
     * @return void
     */
    public function test_the_dark_navbar_is_boost_unions_pair_and_sets_no_colour_mode(): void {
        $this->resetAfterTest();
        set_config('navbarstyle', THEME_DIMENSIONS_SETTING_NAVBARSTYLE_NAVY, 'theme_dimensions');

        $html = $this->navbar_html($this->renderer('/my/', 'standard'), ['userfullname' => 'Ana Souza']);
        $this->assertStringContainsString(' navbar-dark bg-primary ', $html);
        $this->assertStringNotContainsString('data-bs-theme', $html);
    }

    /**
     * A navbar colour somebody chose stands: only Boost Union's untouched light default is filled in.
     *
     * The control is the default, which the same renderer turns into the dark pair.
     *
     * @return void
     */
    public function test_a_chosen_navbar_colour_is_left_alone(): void {
        $this->resetAfterTest();
        set_config('navbarstyle', THEME_DIMENSIONS_SETTING_NAVBARSTYLE_NAVY, 'theme_dimensions');
        $renderer = $this->renderer('/my/', 'standard');

        $this->assertStringContainsString(' navbar-dark bg-primary ', $this->navbar_html($renderer, [], 'bg-body'));
        $chosen = $this->navbar_html($renderer, [], 'navbar-dark bg-dark');
        $this->assertStringContainsString(' navbar-dark bg-dark ', $chosen);
        $this->assertStringNotContainsString('bg-primary', $chosen);
        $this->assertStringContainsString(' bg-primary navbar-expand', $this->navbar_html($renderer, [], 'bg-primary'));
    }

    /**
     * The navbar's brand is drawn at every width, beside the hamburger on a phone, by editing the rendered layout.
     *
     * Boost Union's navbar partial hides it below lg with d-none (display: none !important); the control reads that
     * partial, so a Boost Union release that changes the brand's classes reddens here instead of the phone navbar
     * silently losing its logo.
     *
     * @return void
     */
    public function test_the_brand_shows_beside_the_hamburger_on_a_phone(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();

        $partial = (string) file_get_contents($CFG->dirroot . '/theme/boost_union/templates/theme_boost/navbar.mustache');
        $this->assertStringContainsString(core_renderer::BRAND_HIDDEN, $partial, 'Control: Boost Union hides the brand below lg.');

        $html = $this->visitor_header();
        $this->assertStringContainsString(core_renderer::BRAND_SHOWN, $html);
        $this->assertStringNotContainsString(core_renderer::BRAND_HIDDEN, $html);
        $this->assertSame(1, substr_count($html, 'class="navbar-brand '), 'One brand, edited once.');
    }

    /**
     * The real page header of the course index for the current session, through Boost Union's layout.
     *
     * @return string
     */
    private function visitor_header(): string {
        global $PAGE;

        $GLOBALS['PAGE'] = new \moodle_page();
        $renderer = $this->renderer('/course/index.php', 'standard');
        $PAGE->set_title('Courses');
        $PAGE->set_heading('Courses');

        return $renderer->header();
    }

    /**
     * A visitor who is not logged in gets the theme's two actions and the drawer's card, and not core's link.
     *
     * Core's "Log in" link is Boost Union's user menu's span.login; the theme empties the user menu for this
     * visitor. The control is the setting at '0', where core's link is back and none of the theme's is drawn.
     *
     * @return void
     */
    public function test_a_visitor_gets_the_theme_actions_in_place_of_cores_link(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->registerauth = 'email';
        $this->setUser(null);

        $html = $this->visitor_header();
        $this->assertMatchesRegularExpression(
            '~<a class="btn dimensions-guestnav-login" href="' . preg_quote(get_login_url(), '~') . '"~',
            $html
        );
        $this->assertStringContainsString('data-region="dimensions-guestnav-signup"', $html);
        $this->assertStringContainsString('href="' . $CFG->wwwroot . '/login/signup.php"', $html);
        $this->assertStringContainsString('data-region="dimensions-guestcard"', $html, 'The drawer carries the card.');
        $this->assertStringNotContainsString('<span class="login ps-2">', $html);
        $this->assertStringNotContainsString('dimensions-guestnav-chip', $html, 'The chip is the guest account\'s alone.');

        set_config(guestnav::SETTING, '0', guestnav::COMPONENT);
        $off = $this->visitor_header();
        $this->assertStringContainsString('<span class="login ps-2">', $off, 'Control: core\'s link with the feature off.');
        $this->assertStringNotContainsString('data-region="dimensions-guestnav"', $off);
        $this->assertStringNotContainsString('data-region="dimensions-guestcard"', $off);
    }

    /**
     * "Create account" exists exactly while core's sign-up page would answer, in the navbar and in the card.
     *
     * @return void
     */
    public function test_the_signup_action_follows_core(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setUser(null);

        $CFG->registerauth = '';
        $html = $this->visitor_header();
        $this->assertStringContainsString('data-region="dimensions-guestnav-login"', $html, 'Precondition: the actions are drawn.');
        $this->assertStringNotContainsString('/login/signup.php', $html);

        $CFG->registerauth = 'email';
        $html = $this->visitor_header();
        $this->assertSame(1, substr_count($html, 'data-region="dimensions-guestnav-signup"'));
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-guestcard"[^>]*>.*?href="'
                . preg_quote($CFG->wwwroot . '/login/signup.php', '~') . '".*?</div>~s',
            $html
        );
    }

    /**
     * The guest account gets the chip, with core's sentence as the title of its menu, and not core's own text.
     *
     * @return void
     */
    public function test_the_guest_account_gets_the_chip_with_cores_sentence_inside(): void {
        $this->resetAfterTest();
        $this->setGuestUser();
        $sentence = get_string('loggedinasguest');

        $html = $this->visitor_header();
        $this->assertStringContainsString('class="btn dimensions-guestnav-chip"', $html);
        $this->assertMatchesRegularExpression(
            '~<div class="dropdown-menu dropdown-menu-end dimensions-guestnav-menu"[^>]*>\s*'
                . '<p class="dimensions-guestnav-menu-title">\s*' . preg_quote($sentence, '~') . '\s*</p>~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~<p class="dimensions-guestcard-note">\s*' . preg_quote($sentence, '~') . '\s*</p>~',
            $html,
            'Below md the chip is hidden, so the card carries the sentence.'
        );
        $this->assertStringNotContainsString('<span class="login ps-2">', $html);

        set_config(guestnav::SETTING, '0', guestnav::COMPONENT);
        $off = $this->visitor_header();
        $this->assertMatchesRegularExpression(
            '~<span class="login ps-2">\s*' . preg_quote($sentence, '~') . '~',
            $off,
            'Control: core prints the sentence in its user menu with the feature off.'
        );
        $this->assertStringNotContainsString('dimensions-guestnav-chip', $off);
    }

    /**
     * A real account keeps its user menu and gets none of the visitor's controls.
     *
     * @return void
     */
    public function test_a_real_account_gets_none_of_it(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->registerauth = 'email';
        $this->setUser(self::getDataGenerator()->create_user());

        $html = $this->visitor_header();
        $this->assertStringContainsString('id="user-menu-toggle"', $html, 'Control: the user menu is drawn.');
        $this->assertStringNotContainsString('dimensions-guestnav', $html);
        $this->assertStringNotContainsString('dimensions-guestcard', $html);
    }

    /**
     * The opening tag of the navbar, rendered with Boost Union's navbar colour key.
     *
     * @param core_renderer $renderer The theme's renderer.
     * @param array $usermenu The user menu part of the layout context.
     * @param string $colourclasses What Boost Union's navbar include wrote, its light default unless given.
     * @return string
     */
    private function navbar_html(core_renderer $renderer, array $usermenu, string $colourclasses = 'bg-body'): string {
        $html = $renderer->render_from_template('theme_boost/navbar', [
            'navbarcolorclasses' => $colourclasses,
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
     * @param callable|null $before What a page's own script would do to the page before its header, given the page.
     * @return string The rendered header (the whole start of the page, through Boost Union's layout).
     */
    private function category_header(int $categoryid, ?callable $before = null): string {
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
        if ($before !== null) {
            $before($PAGE);
        }

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
     * Core counts the guest as logged in, so {@see \theme_dimensions\local\category\request::handle()}
     * sends it to the logged-in shell. Every service the module calls refuses the guest, and the module
     * hides the no-JavaScript submits and the numbered pager, the only controls a guest can use: armed
     * for the guest, every chip, term and "load more" would end in an exception dialogue.
     *
     * The guest is kept out at the arming, never by loosening the services' guest refusal, which is what
     * keeps the guest account from writing a favourite onto a shared login.
     */
    public function test_the_client_module_is_armed_for_a_real_account_and_not_for_the_guest(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);

        $this->setUser($generator->create_user());
        $this->run_course_category((int) $category->id);
        $this->assertStringContainsString(
            'theme_dimensions/listingaccount',
            $this->collected_amd(),
            'A real account gets the client module.'
        );

        $this->resetAfterTest();
        category_hero::reset();
        $this->setGuestUser();
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->run_course_category((int) $category->id);
        $this->assertStringNotContainsString(
            'theme_dimensions/listingaccount',
            $this->collected_amd(),
            'The guest is served the page it can actually use: no module, no hidden controls.'
        );
    }

    /**
     * The category page renders the hero with the category's row of nav pills on its edge, one h1, and no band below.
     *
     * The band is the drift test: Boost Union's layout writes the secondary navigation under
     * the key this theme switches off (secondarymoremenu, read by BU's theme_boost/drawers
     * section around core/secondarymoremenu), and if that key is ever renamed the band comes
     * back under the hero's own row - two navigations on one page, which this reads as the
     * band's wrapper, core's React tab bar and its plain more menu.
     *
     * @return void
     */
    public function test_the_category_page_renders_the_hero_with_the_row_and_no_band(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        $this->setAdminUser();
        branding::save((int) $category->id, (object) ['tagline' => 'A tagline for the header']);

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
        // One navigation, the hero's: the row of nav pills on its track, exactly once.
        $this->assertSame(1, substr_count($html, self::PRIMARY_NAV), 'Precondition: the primary navigation is drawn.');
        $this->assertMatchesRegularExpression(
            '~<div class="dim-hero__nav">\s*<div class="dim-hero__navtrack">\s*<nav class="dim-navpills"[^>]*'
                . 'data-region="dim-navpills">~',
            $html
        );
        $this->assertSame(1, substr_count($html, '<nav class="dim-navpills"'), 'The hero\'s row, once.');
        // And never the band: neither its wrapper nor core's React tab bar, nor its no-JS fallback, nor core's more menu.
        $this->assertStringNotContainsString('class="secondary-navigation', $html);
        $this->assertStringNotContainsString(self::BAND_NAV, $html);
        $this->assertSame(0, substr_count($html, '<nav class="moremenu navigation"'), 'No plain more menu on the page.');
        // The destinations by their node keys: the category's own page, current, and in the Settings group its
        // settings and this theme's editor.
        $this->assertMatchesRegularExpression(
            '~<a class="dim-navpill" href="[^"]*" data-key="categorymain" aria-current="page">~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~<details class="dim-navpill-group" data-group="settings">.*?</details>~s',
            $html,
            'The Settings group is a native disclosure.'
        );
        preg_match('~<details class="dim-navpill-group" data-group="settings">.*?</details>~s', $html, $settings);
        $this->assertStringContainsString('data-key="edit"', $settings[0]);
        $this->assertStringContainsString('data-key="categoryappearance"', $settings[0]);
        $this->assertStringContainsString(get_string('categoryhero_nav', 'theme_dimensions'), $html);
        $this->assertStringContainsString('name="' . category_hero::PARAM_FOLD . '"', $html);
    }

    /**
     * With the showcase off the category page keeps Boost Union's header, and the row takes the band's place after it.
     *
     * The page draws no hero, so the core renderer finds the row itself (category_navpills::for_page()), switches the
     * band off and prints the row in its own track right after the header (D97, D98): one navigation, the row, armed
     * as the hero's is. The track is outside the header, on the page's ground.
     *
     * @return void
     */
    public function test_a_switched_off_showcase_draws_the_row_after_boost_unions_header(): void {
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
        $this->assertSame(1, substr_count($html, self::PRIMARY_NAV), 'The primary navigation.');
        // The row, once, in its track after the header.
        $this->assertSame(1, substr_count($html, '<nav class="dim-navpills"'), 'One row.');
        $this->assertMatchesRegularExpression(
            '~</header>\s*<div class="dim-navpills-page d-print-none">\s*<nav class="dim-navpills" id="([^"]+)"~',
            $html,
            'The row\'s track follows the header.'
        );
        $this->assertMatchesRegularExpression(
            '~<a class="dim-navpill" href="[^"]*" data-key="categorymain" aria-current="page">~',
            $html,
            'Category is the current page.'
        );
        // And never the band.
        $this->assertStringNotContainsString('class="secondary-navigation', $html);
        $this->assertStringNotContainsString(self::BAND_NAV, $html);
        $this->assertSame(0, substr_count($html, '<nav class="moremenu navigation"'), 'No plain more menu on the page.');
        // The row has groups, so its client is armed once, under its id.
        preg_match('~<nav class="dim-navpills" id="([^"]+)"~', $html, $nav);
        $amd = $this->collected_amd();
        $this->assertSame(1, substr_count($amd, "require(['theme_dimensions/navpills']"), 'The row\'s client, once.');
        $this->assertStringContainsString('amd.init(' . json_encode($nav[1]) . ');', $amd);
    }

    /**
     * A page whose plugin installed a secondary navigation of its own keeps Boost Union's band, and draws no row.
     *
     * The control of the drift test and of the band's switch: the band is live on a category page exactly when no row
     * is drawn. A plugin's subclass of core's navigation is that page (D97); the showcase is off, so no hero is armed.
     *
     * @return void
     */
    public function test_a_plugins_own_navigation_keeps_its_band_and_draws_no_row(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        $this->setAdminUser();
        set_config(population::SETTING_ENABLED, '0', 'theme_dimensions');

        $html = $this->category_header((int) $category->id, function (\moodle_page $page): void {
            $own = new class ($page) extends \core\navigation\views\secondary {
            };
            $own->initialise();
            $page->set_secondarynav($own);
        });

        $this->assertNull(category_hero::current());
        $this->assertStringContainsString('class="secondary-navigation', $html, 'The band is live.');
        $this->assertSame(1, substr_count($html, self::BAND_NAV), 'The band, as core\'s React tab bar.');
        $this->assertSame(1, substr_count($html, '<nav class="moremenu navigation"'), 'The band\'s no-JS fallback, once.');
        $this->assertStringNotContainsString('dim-navpills', $html, 'And no row beside it.');
        $this->assertStringNotContainsString('theme_dimensions/navpills', $this->collected_amd(), 'No row, no client.');
    }

    /**
     * A learner's hero draws no row - "Category" would be alone on it - and no pencil.
     *
     * Competencies are off: with them on, every authenticated user reads the category's competency
     * frameworks (moodle/competency:competencyview, archetype user), and Category is not alone
     * (category_hero_test holds both halves).
     *
     * @return void
     */
    public function test_a_learners_hero_draws_no_row_and_no_pencil(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        set_config('enabled', 0, 'core_competency');
        $this->setUser($generator->create_user());

        $html = $this->category_header((int) $category->id);

        $this->assertStringContainsString('<h1 class="dim-hero__title">Showcase Category</h1>', $html, 'Control: the hero.');
        $this->assertStringContainsString('dim-hero--notabs', $html);
        $this->assertStringNotContainsString('dim-navpills', $html);
        $this->assertStringNotContainsString('dim-hero__nav', $html);
        $this->assertStringNotContainsString('data-key="categorymain"', $html);
        $this->assertStringNotContainsString('data-key="edit"', $html);
        $this->assertStringNotContainsString('data-key="categoryappearance"', $html);
        $this->assertStringNotContainsString(get_string('categoryhero_pencil', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString('class="secondary-navigation', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('theme_dimensions/navpills', $this->collected_amd(), 'No row, no client.');
    }

    /**
     * The row's client is armed where the row is drawn, once, under the row's own id, for a row with a group.
     *
     * @return void
     */
    public function test_a_row_with_a_group_arms_its_client_once_under_its_id(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        $this->setAdminUser();

        $html = $this->category_header((int) $category->id);

        $this->assertSame(1, preg_match('~<nav class="dim-navpills" id="([^"]+)"~', $html, $nav), 'Precondition: the row.');
        $group = '~<details class="dim-navpill-group" data-group="~';
        $this->assertMatchesRegularExpression($group, $html, 'Precondition: a group.');
        $amd = $this->collected_amd();
        $this->assertSame(1, substr_count($amd, "require(['theme_dimensions/navpills']"), 'The row\'s client, once.');
        $this->assertStringContainsString('amd.init(' . json_encode($nav[1]) . ');', $amd, 'Told the row\'s own id.');
    }

    /**
     * A row of plain pills is drawn and not armed: it has no menu whose roles and keys the client would add.
     *
     * Competencies on, a learner reads Category and the competency frameworks: two plain pills, the row
     * drawn (the control), the client not asked for ({@see core_renderer::arm_navpills()}).
     *
     * @return void
     */
    public function test_a_row_of_plain_pills_is_not_armed(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $generator->create_course(['category' => $category->id]);
        set_config('enabled', 1, 'core_competency');
        $this->setUser($generator->create_user());

        $html = $this->category_header((int) $category->id);

        $this->assertMatchesRegularExpression(
            '~<a class="dim-navpill" href="[^"]*" data-key="competencyframeworks">~',
            $html,
            'Precondition: the learner\'s row holds a second plain pill.'
        );
        $this->assertDoesNotMatchRegularExpression('~<details class="dim-navpill-group" data-group="~', $html, 'No group.');
        $this->assertMatchesRegularExpression(
            '~<details class="dim-navpill-group dim-navpill-group--more" data-group="other" '
                . 'data-region="dim-navpills-more" hidden>~',
            $html,
            'And More empty, hidden.'
        );
        $this->assertStringNotContainsString('theme_dimensions/navpills', $this->collected_amd(), 'Plain pills, no client.');
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
     * The second factor is drawn on auth_loginsteps' shell, not on the login page, while that is the door.
     *
     * Through the real layout (header() runs layout/login.php). The control is the same page with
     * the plugin's switch off, which keeps Boost Union's login layout.
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
        $this->assertStringNotContainsString('theme_boost_union-loginarrangement-', $html);
    }

    /**
     * With the plugin's shell switched off, the second factor keeps Boost Union's login layout.
     *
     * Everything else is the shell test's (the plugin installed and the door), so the switch is the only difference.
     *
     * @return void
     */
    public function test_the_second_factor_keeps_boost_unions_layout_without_the_shell(): void {
        global $PAGE;
        $this->resetAfterTest();
        if (!class_exists(\auth_loginsteps\output\core_page_shell::class)) {
            $this->markTestSkipped('auth_loginsteps is not installed.');
        }
        set_config('auth', 'loginsteps');
        set_config('loginshell', '0', 'auth_loginsteps');

        $renderer = $this->renderer('/admin/tool/mfa/auth.php');
        $PAGE->set_title('Multi-factor authentication');
        $html = $renderer->header();

        $this->assertStringContainsString('theme_boost_union-loginarrangement-', $this->body_tag($html));
        $this->assertStringNotContainsString('theme-dimensions-loginshell', $html);
    }

    /**
     * Give the site both of Boost Union's light logos, and both dark files unless told otherwise.
     *
     * @param bool $dark Whether to store the dark files' settings too.
     * @return void
     */
    private function logos(bool $dark = true): void {
        set_config('logo', '/light.png', 'theme_boost_union');
        set_config('logocompact', '/lightcompact.png', 'theme_boost_union');
        if ($dark) {
            set_config(darklogo::FULL, '/dark.png', 'theme_dimensions');
            set_config(darklogo::COMPACT, '/darkcompact.png', 'theme_dimensions');
        }
    }

    /**
     * The dark logo classes reach the body of an ordinary page and of the login layout while the site's logos are in force.
     *
     * The login half goes through Boost Union's layout/login.php, which asks body_attributes() itself; nothing else
     * would show that the class reaches the page Boost Union's login logo is on.
     *
     * @return void
     */
    public function test_the_dark_logo_classes_reach_the_body_with_a_dark_file(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->logos();

        $attributes = $this->renderer('/my/', 'standard')->body_attributes();
        $this->assertStringContainsString('dimensions-darklogo-compact', $attributes);
        $this->assertStringContainsString('dimensions-darklogo-full', $attributes);

        $GLOBALS['PAGE'] = new \moodle_page();
        $renderer = $this->renderer();
        $PAGE->set_title('Sign in');
        $PAGE->set_heading('Sign in');
        $body = $this->body_tag($renderer->header());
        $this->assertStringContainsString('dimensions-darklogo-compact', $body);
        $this->assertStringContainsString('pagelayout-login', $body, 'Control: the login layout drew this body.');
    }

    /**
     * Without a dark file the body carries no dark logo class.
     *
     * The control of the test above, on the same light logos: a renderer that never wrote the class would pass
     * this alone.
     *
     * @return void
     */
    public function test_without_a_dark_file_the_body_carries_no_dark_logo_class(): void {
        $this->resetAfterTest();
        $this->logos(false);

        $attributes = $this->renderer('/my/', 'standard')->body_attributes();
        $this->assertStringNotContainsString('dimensions-darklogo', $attributes);
        $this->assertStringContainsString('class="', $attributes, 'Control: the body attributes were drawn.');
    }

    /**
     * A flavour with a compact logo of its own disarms the compact class and leaves the full one.
     *
     * The flavour is handed to the renderer through its seam: Boost Union memoises its own lookup for the process.
     *
     * @return void
     */
    public function test_a_flavours_own_logo_disarms_its_dark_class(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->logos();
        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')->create_flavour(['title' => 'Org']);
        $DB->set_field('theme_boost_union_flavours', 'look_logocompact', 'org.png', ['id' => $flavour->id]);
        $flavour = $DB->get_record('theme_boost_union_flavours', ['id' => $flavour->id], '*', MUST_EXIST);

        $this->renderer('/my/', 'standard');
        $renderer = new class ($PAGE, RENDERER_TARGET_GENERAL) extends core_renderer {
            /** @var \stdClass|null The flavour this page is told applies. */
            public ?\stdClass $flavour = null;

            /**
             * The flavour the test set.
             *
             * @return \stdClass|null
             */
            protected function darklogo_flavour(): ?\stdClass {
                return $this->flavour;
            }
        };
        $this->assertStringContainsString('dimensions-darklogo-compact', $renderer->body_attributes(), 'Control: no flavour.');

        $renderer->flavour = $flavour;
        $attributes = $renderer->body_attributes();
        $this->assertStringNotContainsString('dimensions-darklogo-compact', $attributes);
        $this->assertStringContainsString('dimensions-darklogo-full', $attributes, 'The flavour keeps the site\'s full logo.');
    }
}
