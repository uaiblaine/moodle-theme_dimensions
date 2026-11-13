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
 * Theme Dimensions - Tests for the public category route
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\route\controller;

use core\router\route_loader_interface;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\category\request;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_pages;
use theme_dimensions\output\opengraph;

/**
 * The anonymous shell of the showcase, end to end: core's router, this controller, the shared request.
 *
 * A router built before the request - by spelling a route address first - registers every route
 * twice and the request fails; a second request in the same test fails too, because the first left
 * $PAGE's theme set up ("The theme has already been set up for this page"). So each test makes
 * exactly one request, and the expected addresses are built afterwards, through the harness's router.
 *
 * Each refusal is asserted against the page that is served in another test of the same class: a
 * controller answering the login page to everybody would fail the first test, which is what
 * keeps the refusals from being vacuous.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category::class)]
#[CoversClass(request::class)]
final class category_test extends \core\tests\router\route_testcase {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string A script tag stored in a public text, to prove the page cleans what it renders. */
    private const SCRIPT_PROBE = '<script>alert(1)</script>';

    /**
     * Forget what local_unlistedcourses memoised for the last test.
     *
     * Its predicates memoise per id in static arrays and PHPUnit restores the sequences
     * between tests, so without this the state of one test's category answers for the next.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->forget();
    }

    /**
     * Both static registries the page arms are process-wide: leave nothing for the next test.
     *
     * @return void
     */
    protected function tearDown(): void {
        opengraph::set(null);
        category_hero::reset();
        parent::tearDown();
    }

    /**
     * Drop every per-request memo the plugin's predicates keep.
     *
     * @return void
     */
    private function forget(): void {
        discoverability::reset_caches();
        category_discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * Declare the router configured and force login site-wide, the production shape.
     *
     * @return void
     */
    private function production_shape(): void {
        global $CFG;
        $CFG->routerconfigured = true;
        $CFG->forcelogin = 1;
    }

    /**
     * A category in a discoverability state, holding courses each in a state of its own.
     *
     * Every course carries the landing page model, the way landing_test's make_course does,
     * so that the anonymous card has the call to action the public shell offers.
     *
     * @param string $state The category's own state: 'public', 'unlisted' or 'listed'.
     * @param array $courses Full name => state ('public', 'unlisted' or 'listed'), one per course.
     * @param string $name The category's name.
     * @return array The category id first, then the course ids keyed by full name.
     */
    private function make_category(string $state, array $courses = [], string $name = 'Brigada & Resgate'): array {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $adminid = (int) get_admin()->id;
        $categoryid = (int) $generator->create_category(['name' => $name])->id;
        if ($state !== 'listed') {
            category_discoverability::set_state($categoryid, self::category_state($state), $adminid);
        }

        $handler = \core_course\customfield\course_handler::create();
        $ids = [];
        foreach ($courses as $fullname => $coursestate) {
            $course = $generator->create_course(['category' => $categoryid, 'fullname' => $fullname]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            ]);
            if ($coursestate !== 'listed') {
                discoverability::set_state((int) $course->id, self::course_state($coursestate), $adminid);
            }
            $ids[$fullname] = (int) $course->id;
        }
        return [$categoryid, $ids];
    }

    /**
     * The plugin's constant for a category state named in a fixture.
     *
     * @param string $state 'public' or 'unlisted'.
     * @return int The constant.
     */
    private static function category_state(string $state): int {
        return match ($state) {
            'public' => category_discoverability::STATE_PUBLIC,
            'unlisted' => category_discoverability::STATE_UNLISTED,
            default => category_discoverability::STATE_DEFAULT,
        };
    }

    /**
     * The plugin's constant for a course state named in a fixture.
     *
     * @param string $state 'public' or 'unlisted'.
     * @return int The constant.
     */
    private static function course_state(string $state): int {
        return match ($state) {
            'public' => discoverability::STATE_PUBLIC,
            'unlisted' => discoverability::STATE_UNLISTED,
            default => discoverability::STATE_DEFAULT,
        };
    }

    /**
     * Request the public page of a category id through the router.
     *
     * The showcase's own parameters arrive the way a browser sends them - in the query string
     * optional_param() reads - and PHPUnit empties $_GET again between tests.
     *
     * @param string $category The path segment: a category id, or anything else.
     * @param array $query Query parameters of the request, name => value.
     * @param string $method The HTTP method, GET unless a test asks otherwise.
     * @return ResponseInterface The response.
     */
    private function request(string $category, array $query = [], string $method = 'GET'): ResponseInterface {
        foreach ($query as $name => $value) {
            $_GET[$name] = $value;
        }
        // Whoever the fixture was built as, the request is decided with a clean memo.
        $this->forget();
        return $this->process_request(
            $method,
            'theme_dimensions/category/' . $category,
            route_loader_interface::ROUTE_GROUP_PAGE,
            ['HTTP_ACCEPT' => 'text/html'],
            null,
            null
        );
    }

    /**
     * A renderer for exporting the head tags.
     *
     * @return \renderer_base The renderer.
     */
    private function renderer(): \renderer_base {
        global $PAGE;
        return $PAGE->get_renderer('core');
    }

    /**
     * A visitor gets the public page: the public course, its landing page call to action, and no star.
     *
     * The listed course in the same category is the control that the shell really withholds
     * rather than the fixture being empty, and the head tags name the route as canonical.
     */
    public function test_a_visitor_gets_the_public_category_with_its_public_course_alone(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', [
            'Curso Publico' => 'public',
            'Curso Interno' => 'listed',
        ]);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('dim-vitrine', $body, 'The showcase listing, not Boost Union\'s page.');
        $this->assertStringContainsString('Curso Publico', $body);
        $this->assertStringNotContainsString('Curso Interno', $body, 'A listed course has no place on the open internet.');
        $this->assertStringContainsString(get_string('category_state_public', self::COMPONENT), $body);
        $this->assertStringContainsString(get_string('category_cta_landing', self::COMPONENT), $body);
        $this->assertStringNotContainsString('data-region="dim-card-more"', $body, 'A visitor\'s card has one button.');
        $this->assertStringNotContainsString('dim-card__star', $body, 'A visitor has no favourites.');

        $canonical = links::category_page($categoryid)->out(false);
        $this->assertSame(
            $CFG->wwwroot . '/theme_dimensions/category/' . $categoryid,
            $canonical,
            'The route, spelled without r.php.'
        );
        $tags = opengraph::current();
        $this->assertNotNull($tags, 'A public category arms its head tags.');
        $this->assertSame($canonical, $tags->get_url());
        $this->assertSame(
            'Brigada & Resgate',
            $tags->export_for_template($this->renderer())->title,
            'The plain spelling: the template escapes it exactly once.'
        );
    }

    /**
     * With local_page installed, a visitor of a public category meets its pages open to the internet, and no other.
     *
     * The anonymous entry builds the strip itself (request::handle()), so this is the wiring the
     * renderer's test cannot see. The page for logged-in readers is the control that the plugin's
     * read rule, not the theme, decides what a visitor is told.
     */
    public function test_with_local_page_a_visitor_meets_the_public_categorys_open_pages(): void {
        if (!class_exists(category_pages::CATALOGUE)) {
            $this->markTestSkipped('local_page is not installed here, or predates its catalogue.');
        }
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $pages = $this->getDataGenerator()->get_plugin_generator('local_page');
        $pages->create_category_page($categoryid, ['pagename' => 'Edital aberto', 'menuname' => 'edital']);
        $pages->create_category_page($categoryid, [
            'pagename' => 'Somente para logados', 'menuname' => 'interna', 'onlyloggedin' => 1,
        ]);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $strip = strpos($body, 'data-region="dim-pages"');
        $this->assertNotFalse($strip, 'The strip is drawn for a visitor.');
        $this->assertLessThan(strpos($body, 'data-region="dim-vitrine-toolbar"'), $strip, 'Above the toolbar.');
        $this->assertStringContainsString('<span class="dim-pages__name">Edital aberto</span>', $body);
        $this->assertStringNotContainsString('Somente para logados', $body, 'Not named to the open internet.');
    }

    /**
     * A visitor asking for a listed category is sent to the login page, with the page to come back to.
     */
    public function test_a_visitor_is_refused_a_listed_category(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('listed', ['Curso Interno' => 'listed']);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::category_page($categoryid)->out(false), $SESSION->wantsurl);
        $this->assertNull(opengraph::current(), 'Nothing about the category reaches the head.');
    }

    /**
     * An unlisted category answers a visitor exactly as a listed one does.
     */
    public function test_a_visitor_is_refused_an_unlisted_category_the_same_way(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('unlisted', ['Curso Interno' => 'listed']);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::category_page($categoryid)->out(false), $SESSION->wantsurl);
        $this->assertNull(opengraph::current());
    }

    /**
     * A category that does not exist answers the same again: nothing tells an anonymous client which ids exist.
     */
    public function test_a_visitor_is_refused_a_category_that_does_not_exist_the_same_way(): void {
        global $DB, $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $this->setUser(null);

        $response = $this->request((string) $missing);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::category_page($missing)->out(false), $SESSION->wantsurl);
        $this->assertSame('', (string) $response->getBody(), 'A refusal is a redirect and no document.');
        $this->assertNull(opengraph::current(), 'Nothing about the category reaches the head of a refusal.');
    }

    /**
     * The root answers a visitor with the same refusal as a nonexistent id while the site is closed.
     *
     * The address exists - the route's plain integer parameter matches a 0 path segment and the
     * controller runs - and what a visitor gets is nevertheless the login page, because
     * publicaccess::is_public(0) is false until an administrator says otherwise. One request per
     * test means the two responses cannot be compared directly, so the status, Location,
     * wantsurl, empty body and absent head tags asserted here are the ones
     * test_a_visitor_is_refused_a_category_that_does_not_exist_the_same_way asserts.
     */
    public function test_a_visitor_is_refused_the_root_while_the_site_keeps_it_closed(): void {
        global $CFG, $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser(null);

        $response = $this->request('0');

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame('', (string) $response->getBody(), 'A refusal is a redirect and no document.');
        $this->assertNull(opengraph::current(), 'Nothing about the site reaches the head of a refusal.');

        $root = links::category_page(0)->out(false);
        $this->assertSame(
            $CFG->wwwroot . '/theme_dimensions/category/0',
            $root,
            'The root has a route of its own, spelled without r.php.'
        );
        $this->assertSame($root, $SESSION->wantsurl, 'And the way back after logging in is this page.');
    }

    /**
     * With the root opened, a visitor gets the directory of the public top-level categories.
     *
     * The listed sibling is the control, and it is what an assertion naming only the public
     * category could not tell apart from a directory drawing everything: a visitor has no page
     * for it, so it is not a card. The head tags are the site's own and carry no image.
     */
    public function test_a_visitor_gets_the_root_directory_once_the_site_opens_it(): void {
        $this->resetAfterTest();
        $this->production_shape();
        set_config(publicaccess::SETTING_ROOT, '1', self::COMPONENT);
        $this->make_category('public', ['Curso Publico' => 'public'], 'Brigada & Resgate');
        $this->make_category('listed', ['Curso Interno' => 'listed'], 'Categoria Interna');
        $this->setUser(null);

        $response = $this->request('0');

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('dim-vitrine--directory', $body, 'The directory, not a listing.');
        $this->assertStringContainsString('Brigada &amp; Resgate', $body, 'The public category is a card.');
        $this->assertStringNotContainsString('Categoria Interna', $body, 'A listed one has no card on the internet.');
        $this->assertStringNotContainsString('Curso Publico', $body, 'And a directory names no course at all.');
        // Its search line, which a visitor submits: with the client switch off, no module makes it live.
        $this->assertStringContainsString('dim-toolbar dim-toolbar--searchonly', $body, 'The search line.');
        $this->assertStringContainsString('dim-toolbar__submit dim-nojs-only', $body, 'With a button to submit it.');
        $this->assertStringNotContainsString('data-region="dim-filters"', $body, 'And no filter.');

        $tags = opengraph::current();
        $this->assertNotNull($tags, 'The public root arms its head tags.');
        $exported = $tags->export_for_template($this->renderer());
        $this->assertSame(request::site_name(), $exported->title, 'The site\'s own name, in the plain spelling.');
        $this->assertSame(links::category_page(0)->out(false), $tags->get_url());
        $this->assertFalse($exported->hasimage, 'The root ships no og:image.');
    }

    /**
     * The names of the hidden fields the hero's fold form carries, read off the hero the request armed.
     *
     * The hero is drawn inside `$OUTPUT->header()`, which renders nothing under PHPUnit, so the fold
     * form is read from its export and not from the response body.
     *
     * @return array Field name => value.
     */
    private function fold_fields(): array {
        $hero = category_hero::current();
        $this->assertNotNull($hero, 'The request armed no hero.');
        $context = $hero->export_for_template($this->renderer());
        $this->assertTrue($context->hasfold, 'The root\'s hero draws its fold form, so what it carries can be asserted.');
        $fields = [];
        foreach ($context->fold['params'] as $field) {
            $fields[$field['name']] = $field['value'];
        }
        return $fields;
    }

    /**
     * A visitor's root opens the panel of a public top-level category, and it lists that category's public courses alone.
     *
     * The visitor's root builds its request from the search and the open panel of the address and
     * nothing else ({@see request::handle()}), as the logged-in root does. What this holds end to end is the part
     * no unit test reaches: the root's request asks for posters, so the population admits the panel
     * at all. The listed course of the same category is the control that the panel is a visitor's
     * listing, and the fold form carrying the open panel is the control for the refusals below it,
     * which assert that same form carries no panel.
     */
    public function test_a_visitor_opens_the_panel_of_a_public_area_at_the_root(): void {
        $this->resetAfterTest();
        $this->production_shape();
        set_config(publicaccess::SETTING_ROOT, '1', self::COMPONENT);
        [$categoryid] = $this->make_category('public', [
            'Curso Publico' => 'public',
            'Curso Interno' => 'listed',
        ]);
        $this->setUser(null);

        $response = $this->request('0', [criteria::PARAM_OPEN => (string) $categoryid]);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertMatchesRegularExpression('~<li class="dim-panel" id="dim-panel" data-region="dim-panel" style="~', $body);
        $this->assertStringNotContainsString('data-region="dim-panel" hidden', $body, 'The panel is drawn, not hidden.');
        $section = '~data-region="dim-panel-section" data-categoryid="' . $categoryid . '">(.*?)</section>~s';
        $this->assertSame(1, preg_match($section, $body, $matches), 'The panel is the public area\'s.');
        $this->assertStringContainsString('Curso Publico', $matches[1], 'Its public course is in it.');
        $this->assertStringNotContainsString('Curso Interno', $body, 'A listed course has no place on the open internet.');
        $this->assertSame(
            (string) $categoryid,
            (string) ($this->fold_fields()[criteria::PARAM_OPEN] ?? ''),
            'Folding the hero keeps the panel that is open.'
        );
    }

    /**
     * The three ids a visitor's root has no poster for, each sent with a search and a sort.
     *
     * @return array Case name => the id's kind.
     */
    public static function root_open_refused_provider(): array {
        return [
            'a listed top-level category' => ['listed'],
            'a grandchild of the root' => ['grandchild'],
            'an id that does not exist' => ['bogus'],
        ];
    }

    /**
     * An open panel the visitor's root has no poster for is dropped, and the root reads no sort either.
     *
     * Each answers what the bare root answers - the same posters, no panel - so the parameter tells a
     * visitor nothing about which ids exist or what they hold. The root builds its request from its
     * search and its open panel alone ({@see criteria::root_from_request()}): it draws no control that
     * could clear anything else, so a value read whole from the address would ride the hero's fold form
     * with nothing on the page admitting it. The last assertion catches the sort carried there; the
     * search is read, and travels with the fold.
     *
     * @param string $kind What the open parameter names: 'listed', 'grandchild' or 'bogus'.
     */
    #[DataProvider('root_open_refused_provider')]
    public function test_a_visitor_root_drops_an_open_panel_it_has_no_poster_for(string $kind): void {
        global $DB;
        $this->resetAfterTest();
        $this->production_shape();
        set_config(publicaccess::SETTING_ROOT, '1', self::COMPONENT);
        [$publicid] = $this->make_category('public', ['Curso Publico' => 'public']);
        [$listedid] = $this->make_category('listed', ['Curso Interno' => 'listed'], 'Categoria Interna');
        $childid = (int) $this->getDataGenerator()->create_category(['name' => 'Subarea Aberta', 'parent' => $publicid])->id;
        category_discoverability::set_state($childid, category_discoverability::STATE_PUBLIC, (int) get_admin()->id);
        $open = match ($kind) {
            'listed' => $listedid,
            'grandchild' => $childid,
            default => (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000,
        };
        $this->setUser(null);

        $response = $this->request('0', [
            criteria::PARAM_OPEN => (string) $open,
            criteria::PARAM_SEARCH => 'Publico',
            criteria::PARAM_SORT => criteria::SORT_NAME,
        ]);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('Brigada &amp; Resgate', $body, 'The root\'s posters are drawn as ever.');
        $this->assertStringContainsString('<li class="dim-panel" id="dim-panel" data-region="dim-panel" hidden></li>', $body);
        $this->assertStringNotContainsString('data-region="dim-panel-section"', $body, 'No panel.');
        $this->assertStringNotContainsString('Categoria Interna', $body);
        $this->assertStringNotContainsString('Subarea Aberta', $body);
        $this->assertStringNotContainsString('Curso Publico', $body, 'And a root with no panel names no course.');
        $fields = $this->fold_fields();
        $this->assertArrayNotHasKey(criteria::PARAM_OPEN, $fields, 'A dropped panel is not carried by the fold.');
        $this->assertSame('Publico', $fields[criteria::PARAM_SEARCH] ?? null, 'The root reads its search, and the fold keeps it.');
        $this->assertArrayNotHasKey(criteria::PARAM_SORT, $fields, 'The root reads no organisation, so its fold carries none.');
    }

    /**
     * With the surface switched off a public category answers the refusal too, its state still stored.
     */
    public function test_a_visitor_is_refused_while_the_public_surface_is_switched_off(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        set_config(publicaccess::SETTING_ENABLED, '0', self::COMPONENT);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::category_page($categoryid)->out(false), $SESSION->wantsurl);
        $this->assertNull(opengraph::current());
        $this->assertSame(
            category_discoverability::STATE_PUBLIC,
            category_discoverability::get_state($categoryid),
            'The state stays stored: only the serving is off.'
        );
    }

    /**
     * The showcase's own kill switch closes this shell too: off, both shells are what the site rendered before.
     */
    public function test_a_visitor_is_refused_while_the_showcase_itself_is_switched_off(): void {
        global $SESSION;
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        set_config(population::SETTING_ENABLED, '0', self::COMPONENT);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(get_login_url(), $response->getHeaderLine('Location'));
        $this->assertSame(links::category_page($categoryid)->out(false), $SESSION->wantsurl);
        $this->assertNull(opengraph::current());
    }

    /**
     * A POST is refused: every control of the page is a GET form or a link, and the route accepts nothing else.
     */
    public function test_a_post_is_refused(): void {
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser(null);

        $response = $this->request((string) $categoryid, [], 'POST');

        $this->assertSame(405, $response->getStatusCode(), substr((string) $response->getBody(), 0, 500));
    }

    /**
     * A logged-in user is sent to the showcase: one surface per viewer class.
     */
    public function test_a_logged_in_user_is_sent_to_the_showcase(): void {
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->request((string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(
            (new \core\url('/course/index.php', ['categoryid' => $categoryid]))->out(false),
            $response->getHeaderLine('Location')
        );
        $this->assertNull(opengraph::current(), 'The logged-in shell arms no head tags.');
    }

    /**
     * The guest account is logged in to core, so it lands on the showcase like anybody else.
     */
    public function test_the_guest_account_is_sent_to_the_showcase_too(): void {
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setGuestUser();

        $response = $this->request((string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(
            (new \core\url('/course/index.php', ['categoryid' => $categoryid]))->out(false),
            $response->getHeaderLine('Location')
        );
        $this->assertNull(opengraph::current());
    }

    /**
     * A path that is not a category id is not found before the controller runs.
     */
    public function test_a_non_numeric_id_is_not_found(): void {
        $this->resetAfterTest();
        $this->production_shape();

        $response = $this->request('abc');

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * The visitor's search narrows the public listing, and its parameters travel in the query string.
     */
    public function test_a_visitor_can_search_the_public_listing(): void {
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', [
            'Curso Publico' => 'public',
            'Evento Aberto' => 'public',
        ]);
        $this->setUser(null);

        $response = $this->request((string) $categoryid, [criteria::PARAM_SEARCH => 'Publico']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('Curso Publico', $body);
        $this->assertStringNotContainsString('Evento Aberto', $body, 'The search really narrowed the page.');
    }

    /**
     * A YouTube address in the public page text reaches a visitor as core's player, never as script.
     *
     * Nothing in the theme turns a link into a player - core's mediaplugin filter does - so what
     * this pins is that the filters really run on this text on the anonymous page.
     * {@see request::handle()} formats it with format_text()'s defaults, where filter is true and
     * noclean is false: the filters run and HTMLPurifier cleans, the rule for every public
     * surface of the theme (a manager holds moodle/category:manage, which is RISK_XSS).
     *
     * The filter is switched on explicitly so the test does not depend on the site's filter
     * settings: core installs it on, but an administrator may switch it off.
     */
    public function test_a_youtube_address_in_the_public_text_reaches_a_visitor_as_a_player(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->production_shape();
        require_once($CFG->libdir . '/filterlib.php');
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $address = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
        branding::save($categoryid, (object) [
            'publicdescription_editor' => [
                'text' => '<p>Veja <a href="' . $address . '">o vídeo</a>.</p>'
                    . '<p>' . self::SCRIPT_PROBE . '</p>',
                'format' => FORMAT_HTML,
            ],
        ]);
        filter_set_global_state('mediaplugin', TEXTFILTER_ON);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        /* The public text is drawn by the hero, which core renders inside $OUTPUT->header() -
           and under PHPUnit that renders nothing, so the response body cannot be the sink here.
           The hero the request armed is, and it holds the very string the header would print. */
        $hero = category_hero::current();
        $this->assertNotNull($hero, 'The public request armed no hero at all.');
        $rendered = (string) $hero->export_for_template($this->renderer())->descriptionhtml;

        $this->assertStringContainsString(
            'mediaplugin',
            $rendered,
            'The public text reached the page without the media filter: a link stayed a link. '
                . $rendered
        );
        $this->assertStringContainsString($address, $rendered, 'The player names the address that was pasted.');
        $this->assertStringNotContainsString(
            '<script',
            $rendered,
            'The public text is not cleaned: a manager with RISK_XSS could run script for every visitor.'
        );
        $this->assertStringNotContainsString('alert(1)', $rendered, 'The probe survived HTMLPurifier.');
        $this->assertStringContainsString('o vídeo', $rendered, 'Control: the text itself reached the page.');
    }

    /**
     * What the multimedia dialogue inserts reaches a visitor as a player, and the purifier keeps its anchor.
     *
     * The test above pins a link a manager pasted; this one pins what the editor's multimedia
     * dialogue writes for a YouTube page address and for a direct .mp4 address alike:
     * `<a class="external-media-provider" href="...">`.
     *
     * The `external-media-provider` class does not reach the page, and the purifier is not what
     * removes it: `filter_mediaplugin` replaces the whole anchor with a videojs player, so only the
     * address survives. The third link is the control that tells the two explanations apart: it is
     * not a media address, the filter leaves it alone, and its class is on the page. HTMLPurifier
     * keeps an anchor, its https `href` and its `class` (an attribute of XHTML 1.0 Transitional, the
     * doctype `purify_html()` sets); what it drops is every URI scheme outside its list, so a pasted
     * `data:` or `blob:` image would vanish here while the stored row kept it - which is why the
     * form refuses such a text at the save ({@see branding::holds_unservable_address()}).
     */
    public function test_what_the_multimedia_dialogue_inserts_reaches_a_visitor_as_a_player(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->production_shape();
        require_once($CFG->libdir . '/filterlib.php');
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $youtube = 'https://www.youtube.com/watch?v=aqz-KE-bpKQ';
        $film = 'https://example.org/sample-5s.mp4';
        $page = 'https://example.org/programa';
        branding::save($categoryid, (object) [
            'publicdescription_editor' => [
                'text' => '<p><a class="external-media-provider" href="' . $youtube . '"> ' . $youtube . ' </a></p>'
                    . '<p><a class="external-media-provider" href="' . $film . '"> sample-5s </a></p>'
                    . '<p><a class="dim-probe" href="' . $page . '">o programa</a></p>',
                'format' => FORMAT_HTML,
            ],
        ]);
        filter_set_global_state('mediaplugin', TEXTFILTER_ON);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $this->assertSame(200, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $hero = category_hero::current();
        $this->assertNotNull($hero, 'The public request armed no hero at all.');
        $rendered = (string) $hero->export_for_template($this->renderer())->descriptionhtml;

        $this->assertStringContainsString(
            'mediaplugin',
            $rendered,
            'What the dialogue inserts stayed a link on the public page: ' . $rendered
        );
        $this->assertStringContainsString($youtube, $rendered, 'The player names the address the dialogue wrote.');
        $this->assertStringContainsString($film, $rendered, 'And so does the one for the direct film.');
        $this->assertSame(
            2,
            substr_count($rendered, 'mediaplugin_videojs'),
            'Both of what the dialogue inserted became a player, and only those two: ' . $rendered
        );
        $this->assertStringNotContainsString(
            'external-media-provider',
            $rendered,
            'The filter replaces the anchor outright, so its editor-side class reaches no page.'
        );
        $this->assertStringContainsString(
            'class="dim-probe"',
            $rendered,
            'Control: an anchor the filter leaves alone keeps its class, so the purifier is not what removed the other.'
        );
        $this->assertStringContainsString($page, $rendered, 'Control: and its own address.');
    }

    /**
     * A search matching nothing is still the public page, with the empty state that blames the filters.
     */
    public function test_a_visitor_search_that_matches_nothing_gets_the_empty_state(): void {
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser(null);

        $response = $this->request((string) $categoryid, [criteria::PARAM_SEARCH => 'zzz']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString(get_string('category_empty', self::COMPONENT), $body);
        $this->assertStringNotContainsString('Curso Publico', $body);
    }
    /**
     * The two ways a visitor's form can send a period of one bound, and what each must keep.
     *
     * @return array Case => [the parameter sent, the course it keeps, the course it drops].
     */
    public static function one_bound_provider(): array {
        return [
            'a start alone' => [criteria::PARAM_DATEFROM, 'Curso Novo', 'Curso Antigo'],
            'an end alone' => [criteria::PARAM_DATETO, 'Curso Antigo', 'Curso Novo'],
        ];
    }

    /**
     * A visitor's form narrows by one date alone, and the page draws it back into its own native field.
     *
     * The anonymous shell arms no listing module, so its date filter is the toolbar's two native
     * date fields in a GET form with a visible submit, and a reader may fill either one: a start
     * alone keeps the courses starting on that day or later, an end alone those starting on that
     * day or earlier (criteria). The page must answer the bound it was sent and draw it back into
     * the one field that sent it, with the other left empty - a field that came back holding a
     * second date would narrow the next submission by a bound nobody set. Both fields carry the
     * labels the calendar's boxes carry, and the calendar itself is not drawn: it is the client's.
     *
     * @param string $param The parameter the form sends.
     * @param string $kept The course that bound keeps.
     * @param string $dropped The course on the other side of it, the control that it narrowed.
     */
    #[DataProvider('one_bound_provider')]
    public function test_a_visitor_narrows_by_one_date_alone_and_gets_it_back_in_its_field(
        string $param,
        string $kept,
        string $dropped
    ): void {
        global $DB;
        $this->resetAfterTest();
        $this->production_shape();
        [$categoryid, $ids] = $this->make_category('public', [
            'Curso Antigo' => 'public',
            'Curso Novo' => 'public',
        ]);
        $DB->set_field('course', 'startdate', criteria::day_start('2026-03-10'), ['id' => $ids['Curso Antigo']]);
        $DB->set_field('course', 'startdate', criteria::day_start('2026-09-20'), ['id' => $ids['Curso Novo']]);
        $this->setUser(null);

        $response = $this->request((string) $categoryid, [$param => '2026-06-01']);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString($kept, $body);
        $this->assertStringNotContainsString($dropped, $body, 'The one bound really narrowed the page.');

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $body);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $form = '//form[@data-region="dim-vitrine-toolbar"][@method="get"]';
        $this->assertSame(1, $xpath->query($form)->length, 'The visitor\'s toolbar is a GET form.');
        $values = [];
        foreach ([criteria::PARAM_DATEFROM => 'category_datefrom', criteria::PARAM_DATETO => 'category_dateto'] as $name => $key) {
            $input = $xpath->query($form . '//*[@data-region="dim-dateinputs"]//input[@type="date"][@name="' . $name . '"]');
            $this->assertSame(1, $input->length, 'The native ' . $name . ' field is drawn for a visitor.');
            $values[$name] = $input->item(0)->getAttribute('value');
            $label = $xpath->query($form . '//label[@for="' . $input->item(0)->getAttribute('id') . '"]');
            $this->assertSame(1, $label->length, 'The ' . $name . ' field has a label of its own.');
            $this->assertSame(get_string($key, self::COMPONENT), trim($label->item(0)->textContent));
        }
        $expected = [criteria::PARAM_DATEFROM => '', criteria::PARAM_DATETO => ''];
        $expected[$param] = '2026-06-01';
        $this->assertSame($expected, $values, 'The bound sent comes back in its own field, and the other stays empty.');
        $this->assertSame(
            1,
            $xpath->query($form . '//button[@type="submit"][' . self::has_class('dim-toolbar__submit') . ']')->length,
            'With no module to apply it, the form keeps its own submit.'
        );
        $this->assertSame(
            0,
            $xpath->query('//*[@data-region="dim-daterange-pop"]')->length,
            'The calendar is the client\'s and is never drawn by the server.'
        );
    }

    /**
     * The AMD modules the request asked for, each with the arguments its init() was called with.
     *
     * The page is rendered by the route, whose footer is never printed under PHPUnit, so the calls
     * are read off the requirements manager itself: every js_call_amd() leaves one snippet there.
     *
     * @return array Module name => the argument list its init() was called with, e.g. '48' or ''.
     */
    private function amd_calls(): array {
        global $PAGE;
        $snippets = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
        $calls = [];
        foreach ($snippets as $snippet) {
            if (preg_match("~require\(\['([^']+)'\], function\(amd\) \{amd\.init\(([^)]*)\);~", (string) $snippet, $matches)) {
                $calls[$matches[1]] = $matches[2];
            }
        }
        return $calls;
    }

    /**
     * The strings the request handed to the page's JavaScript, by component.
     *
     * @return array Component => list of string identifiers.
     */
    private function js_strings(): array {
        global $PAGE;
        $strings = (new \ReflectionProperty($PAGE->requires, 'stringsforjs'))->getValue($PAGE->requires);
        return array_map('array_keys', $strings);
    }

    /**
     * With its web service open, a visitor's public page arms the live listing for that category.
     *
     * The cover film's module, armed on the same page by the same request, is the control that the
     * calls really are read from this request: a reader that found nothing would fail on it first.
     * The listing is told the category alone - its language is the page's own, read off M.cfg - and
     * the page's own sentence for a failure travels with the page.
     */
    public function test_the_live_listing_is_armed_for_a_visitor_while_its_service_is_open(): void {
        $this->resetAfterTest();
        $this->production_shape();
        set_config(publicaccess::SETTING_CLIENT, '1', self::COMPONENT);
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $calls = $this->amd_calls();
        $this->assertArrayHasKey('theme_dimensions/covervideo', $calls, 'The control: this request\'s calls are read.');
        $this->assertArrayHasKey('theme_dimensions/listingvisitor', $calls, 'The live listing is armed.');
        $this->assertSame(
            (string) $categoryid,
            $calls['theme_dimensions/listingvisitor'],
            'It is told the category and nothing else.'
        );
        $this->assertArrayNotHasKey('theme_dimensions/listingaccount', $calls, 'Never the logged-in client.');
        $this->assertContains(
            request::STRING_FALLBACK,
            $this->js_strings()[self::COMPONENT] ?? [],
            'The failure sentence is the page\'s, preloaded: it needs no network.'
        );
        // The page is still the whole page without the module: the form keeps its own submit.
        $this->assertStringContainsString('dim-toolbar__submit dim-nojs-only', $body);
    }

    /**
     * With its web service closed - never opened, or closed again - a visitor's page arms no live listing.
     *
     * The page itself is served exactly as before: the same document, the cover film's module, the
     * form's own submit. The switch is beside publicaccess::is_public(), never inside it.
     *
     * @param string|null $stored What the switch holds; null for a site that never saved it.
     */
    #[DataProvider('closed_switch_provider')]
    public function test_the_live_listing_is_not_armed_while_its_service_is_closed(?string $stored): void {
        $this->resetAfterTest();
        $this->production_shape();
        if ($stored !== null) {
            set_config(publicaccess::SETTING_CLIENT, $stored, self::COMPONENT);
        }
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser(null);

        $response = $this->request((string) $categoryid);

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('Curso Publico', $body, 'The public page is served whatever the switch says.');
        $calls = $this->amd_calls();
        $this->assertArrayHasKey('theme_dimensions/covervideo', $calls, 'The control: this request\'s calls are read.');
        $this->assertArrayNotHasKey('theme_dimensions/listingvisitor', $calls);
        $this->assertNotContains(request::STRING_FALLBACK, $this->js_strings()[self::COMPONENT] ?? []);
        $this->assertStringContainsString('dim-toolbar__submit dim-nojs-only', $body, 'The form keeps its own submit.');
    }

    /**
     * The two ways a site keeps the visitor's web service closed.
     *
     * @return array
     */
    public static function closed_switch_provider(): array {
        return [
            'never saved' => [null],
            'switched off' => ['0'],
        ];
    }

    /**
     * The root's search line is live for a visitor too, where the site opened both the root and the service.
     */
    public function test_the_live_listing_follows_a_visitor_to_a_root_the_site_opened(): void {
        $this->resetAfterTest();
        $this->production_shape();
        set_config(publicaccess::SETTING_ROOT, '1', self::COMPONENT);
        set_config(publicaccess::SETTING_CLIENT, '1', self::COMPONENT);
        $this->make_category('public', ['Curso Publico' => 'public']);
        $this->setUser(null);

        $response = $this->request('0');

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('dim-toolbar dim-toolbar--searchonly', $body, 'The root\'s search line.');
        $calls = $this->amd_calls();
        $this->assertArrayHasKey('theme_dimensions/listingvisitor', $calls);
        $this->assertSame('0', $calls['theme_dimensions/listingvisitor'], 'Told the root, category 0.');
    }

    /**
     * A refused request arms nothing: a closed root, a logged-in user and the guest, the service open for each.
     *
     * Every one of them leaves request::handle() before the page is wired, so the live listing is
     * armed for a visitor of a public page and for nobody else.
     *
     * @param string $who Who asks: 'closedroot', 'user' or 'guest'.
     */
    #[DataProvider('unarmed_provider')]
    public function test_a_request_that_is_not_a_visitors_public_page_arms_no_live_listing(string $who): void {
        $this->resetAfterTest();
        $this->production_shape();
        set_config(publicaccess::SETTING_CLIENT, '1', self::COMPONENT);
        [$categoryid] = $this->make_category('public', ['Curso Publico' => 'public']);
        if ($who === 'user') {
            $this->setUser($this->getDataGenerator()->create_user());
        } else if ($who === 'guest') {
            $this->setGuestUser();
        } else {
            $this->setUser(null);
        }

        $response = $this->request($who === 'closedroot' ? '0' : (string) $categoryid);

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertArrayNotHasKey('theme_dimensions/listingvisitor', $this->amd_calls());
    }

    /**
     * The three requests the live listing is never armed for.
     *
     * @return array
     */
    public static function unarmed_provider(): array {
        return [
            'a visitor of a closed root' => ['closedroot'],
            'a logged-in user' => ['user'],
            'the guest' => ['guest'],
        ];
    }

    /**
     * An XPath predicate matching one class among several.
     *
     * @param string $class The class name.
     * @return string
     */
    private static function has_class(string $class): string {
        return 'contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")';
    }
}
