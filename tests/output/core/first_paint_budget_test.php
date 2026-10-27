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
 * Theme Dimensions - The first paint's deterministic budget: bytes, AMD modules and strings at load
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output\core;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\fixture;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\category\request;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../local/category/fixture.php');

/**
 * The counts of a first paint that hold without a browser, as hard gates (R0 of docs/vitrine-react/PLAN.md).
 *
 * The browser probe (tests/behat/theme_dimensions_perf.feature) measures paint and blocking time,
 * which are a trend until the hosts' noise is known; these are the parts of the same first paint a
 * render in process can count exactly, so they gate every stage from R0 on: the HTML bytes a card
 * and the rest of a listing cost, the hero's bytes, the AMD modules the page asks the browser to
 * load at once, and the strings it ships to JavaScript. Each byte ceiling is today's largest
 * measurement plus 3 %: two runs on one site differed by up to 1.3 %, for a reason not yet pinned
 * down, and the bytes depend on the site's plugins and ids. The module lists and string counts are
 * exact. A stage that lowers a number lowers its ceiling in the same change, and one that raises it
 * says why in the same change.
 *
 * The bytes are measured as a slope, a page of four cards against a page of twenty-four: the slope
 * is what one more card costs and the intercept what the listing costs without cards, so neither
 * moves with the fixture's size. The fixture's start dates are fixed, so no date string changes
 * length with the day the test runs.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_renderer::class)]
#[CoversClass(request::class)]
final class first_paint_budget_test extends \advanced_testcase {
    use fixture;

    /** @var int A fixed start date for every course (2025-03-03 12:00 UTC), so no card's date changes with the day. */
    private const STARTDATE = 1741003200;

    /** @var string A valid 1x1 PNG, the cover. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var int HTML bytes one more card adds to a logged-in flat listing: measured 4,666 and 4,693 (2026-10-27), + 3 %. */
    private const CARD_BYTES = 4834;

    /** @var int HTML bytes of a logged-in flat listing without its cards: measured 17,081 and 17,305, + 3 %. */
    private const LISTING_BYTES = 17825;

    /** @var int HTML bytes one more card adds to a visitor's listing: measured 4,438 (2026-10-27), + 3 %. */
    private const PUBLIC_CARD_BYTES = 4572;

    /** @var int HTML bytes of the hero of a category with no branding row, logged in: measured 5,540 and 5,550, + 3 %. */
    private const HERO_BYTES = 5717;

    /** @var string[] The theme's AMD modules the logged-in flat page asks for at load, in order: exactly these. */
    private const MODULES = [
        'theme_dimensions/categoryvitrine',
        'theme_dimensions/categorypanel',
        'theme_dimensions/covervideo',
        'theme_dimensions/categorycoursemodal',
    ];

    /** @var string[] The theme's AMD modules the visitor's page asks for at load, the live listing open: exactly these. */
    private const PUBLIC_MODULES = [
        'theme_dimensions/covervideo',
        'theme_dimensions/categorycoursemodal',
        'theme_dimensions/categorypublic',
    ];

    /** @var int Strings the logged-in shell adds to strings_for_js: none, its sentences come with the answers. */
    private const STRINGS = 0;

    /** @var int Strings the visitor's shell adds to strings_for_js, the live listing open: its fallback sentence alone. */
    private const PUBLIC_STRINGS = 1;

    /**
     * Seed a public category of courses with the landing page model and a cover each, and a viewer.
     *
     * @param int $courses How many courses.
     * @return array [category id, viewer]
     */
    private function seed(int $courses): array {
        global $CFG;
        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');

        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'First paint']);
        for ($i = 0; $i < $courses; $i++) {
            $course = $generator->create_course([
                'category' => $category->id,
                'fullname' => sprintf('First paint course %02d', $i),
                'shortname' => sprintf('FP%02d', $i),
                'startdate' => self::STARTDATE,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            ]);
            get_file_storage()->create_file_from_string([
                'contextid' => \core\context\course::instance((int) $course->id)->id,
                'component' => 'course',
                'filearea' => 'overviewfiles',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'cover.png',
            ], base64_decode(self::PNG));
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $this->set_category_state((int) $category->id, category_discoverability::STATE_PUBLIC);
        return [(int) $category->id, $generator->create_user()];
    }

    /**
     * Render the logged-in shell's page of a category as course/index.php does, on a fresh page.
     *
     * @param int $categoryid The category.
     * @param array $query The query string.
     * @return array [listing html, hero html, AMD modules, strings_for_js added]
     */
    private function logged_in(int $categoryid, array $query): array {
        global $PAGE, $SITE;
        self::forget();
        $_GET = $query;
        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_course($SITE);
        $PAGE->set_url('/course/index.php', ['categoryid' => $categoryid] + $query);
        $PAGE->force_theme('dimensions');
        $strings = $this->strings();
        /** @var course_renderer $renderer */
        $renderer = $PAGE->get_renderer('core', 'course', RENDERER_TARGET_GENERAL);
        $listing = $renderer->course_category($categoryid);
        $hero = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL)->full_header();
        $_GET = [];
        return [$listing, $hero, $this->modules(), $this->strings() - $strings];
    }

    /**
     * Render the visitor's page of a category as category.php does, on a fresh page.
     *
     * @param int $categoryid The category.
     * @param array $query The query string.
     * @return array [listing html, AMD modules, strings_for_js added]
     */
    private function visitor(int $categoryid, array $query): array {
        global $PAGE;
        $_GET = $query;
        $PAGE = new \moodle_page();
        $this->become(null);
        $strings = $this->strings();
        $answer = request::handle($categoryid);
        $this->assertNull($answer->redirect, 'Precondition: the visitor is served the page.');
        $listing = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL)->render($answer->page);
        $_GET = [];
        return [$listing, $this->modules(), $this->strings() - $strings];
    }

    /**
     * A protected property of the page's requirements manager.
     *
     * @param string $name The property.
     * @return mixed
     */
    private function requirement(string $name) {
        global $PAGE;
        return (new \ReflectionProperty($PAGE->requires, $name))->getValue($PAGE->requires);
    }

    /**
     * The AMD modules the page has asked the browser to load so far, one per require() call, in order.
     *
     * @return string[]
     */
    private function modules(): array {
        $modules = [];
        foreach ((array) $this->requirement('amdjscode') as $code) {
            if (preg_match_all("/require\\(\\['([^']+)'\\]/", (string) $code, $matches)) {
                array_push($modules, ...$matches[1]);
            }
        }
        return $modules;
    }

    /**
     * How many strings the page ships to JavaScript so far.
     *
     * @return int
     */
    private function strings(): int {
        $count = 0;
        foreach ((array) $this->requirement('stringsforjs') as $identifiers) {
            $count += count($identifiers);
        }
        return $count;
    }

    /**
     * Record a measurement beside budget_test's own, in the system temp directory.
     *
     * @param string $label What was measured.
     * @param array $numbers The numbers.
     * @return void
     */
    private function record(string $label, array $numbers): void {
        $path = sys_get_temp_dir() . '/theme_dimensions-vitrine-budget.json';
        $all = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $all[$label] = $numbers + ['measured' => date('c')];
        file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT));
    }

    /**
     * A logged-in flat listing: what a card costs, what the rest costs, and what the hero costs, in bytes.
     */
    public function test_the_logged_in_listing_stays_within_its_bytes(): void {
        [$categoryid, $viewer] = $this->seed(24);
        $this->become($viewer);
        $flat = [criteria::PARAM_SORT => criteria::SORT_NAME];
        // A warm-up render, so the first one metered pays no theme or template initialisation.
        $this->logged_in($categoryid, $flat + ['perpage' => 4]);

        [$four, $hero] = $this->logged_in($categoryid, $flat + ['perpage' => 4]);
        [$twentyfour] = $this->logged_in($categoryid, $flat + ['perpage' => 24]);
        $this->assertSame(4, substr_count($four, 'data-region="dim-card"'), 'Precondition: four cards.');
        $this->assertSame(24, substr_count($twentyfour, 'data-region="dim-card"'), 'Precondition: twenty-four cards.');
        $percard = intdiv(strlen($twentyfour) - strlen($four), 20);
        $rest = strlen($four) - 4 * $percard;
        $this->record('first paint bytes', [
            'bytes per card' => $percard,
            'listing bytes without cards' => $rest,
            'hero bytes' => strlen($hero),
            'listing bytes at P=24' => strlen($twentyfour),
        ]);
        $this->assertStringContainsString('dim-hero', $hero, 'Precondition: the hero is the theme\'s.');
        $this->assertLessThanOrEqual(self::CARD_BYTES, $percard, "A card costs {$percard} bytes.");
        $this->assertLessThanOrEqual(self::LISTING_BYTES, $rest, "The listing without cards costs {$rest} bytes.");
        $this->assertLessThanOrEqual(self::HERO_BYTES, strlen($hero), 'The hero costs ' . strlen($hero) . ' bytes.');
    }

    /**
     * A visitor's listing: what a card costs in bytes.
     */
    public function test_the_visitors_listing_stays_within_its_bytes(): void {
        [$categoryid] = $this->seed(24);
        $flat = [criteria::PARAM_SORT => criteria::SORT_NAME];
        $this->visitor($categoryid, $flat + ['perpage' => 4]);

        [$four] = $this->visitor($categoryid, $flat + ['perpage' => 4]);
        [$twentyfour] = $this->visitor($categoryid, $flat + ['perpage' => 24]);
        $this->assertSame(24, substr_count($twentyfour, 'data-region="dim-card"'), 'Precondition: twenty-four cards.');
        $percard = intdiv(strlen($twentyfour) - strlen($four), 20);
        $this->record('first paint public bytes', ['bytes per card' => $percard, 'listing bytes at P=24' => strlen($twentyfour)]);
        $this->assertLessThanOrEqual(self::PUBLIC_CARD_BYTES, $percard, "A visitor's card costs {$percard} bytes.");
    }

    /**
     * The theme's own modules among those a page asked for, in order.
     *
     * A filter of the site (MathJax) asks for its own loader from the text a page formats, which is
     * the site's configuration and not the showcase's, so only the theme's modules are compared.
     *
     * @param string[] $modules Every module the page asked for.
     * @return string[]
     */
    private static function theirs(array $modules): array {
        return array_values(array_filter($modules, fn(string $module): bool => str_starts_with($module, 'theme_dimensions/')));
    }

    /**
     * The logged-in flat page asks for exactly these AMD modules at load, and adds exactly this many strings.
     *
     * A module added to the first paint is a request and a parse on every showcase page, so it is
     * a decision of the stage that adds it, written into this list in the same change; a module
     * moved to first use (P0's dialogue, preview and film) leaves the list.
     */
    public function test_the_logged_in_page_asks_for_exactly_its_modules_at_load(): void {
        [$categoryid, $viewer] = $this->seed(6);
        $this->become($viewer);
        [, , $modules, $strings] = $this->logged_in($categoryid, [criteria::PARAM_SORT => criteria::SORT_NAME]);
        $this->record('first paint modules', ['modules' => $modules, 'strings' => $strings]);
        $this->assertSame(self::MODULES, self::theirs($modules));
        $this->assertSame(self::STRINGS, $strings);
    }

    /**
     * The visitor's page asks for exactly these theme modules at load, the live listing open, and this many strings.
     *
     * Only the theme's modules are compared ({@see theirs()}). The switch closed is the control:
     * the live listing and its one string leave the page with it.
     */
    public function test_the_visitors_page_asks_for_exactly_its_modules_at_load(): void {
        [$categoryid] = $this->seed(6);
        set_config(publicaccess::SETTING_CLIENT, '1', 'theme_dimensions');
        [, $modules, $strings] = $this->visitor($categoryid, []);
        $this->record('first paint public modules', ['modules' => $modules, 'strings' => $strings]);
        $this->assertSame(self::PUBLIC_MODULES, self::theirs($modules));
        $this->assertSame(self::PUBLIC_STRINGS, $strings);

        set_config(publicaccess::SETTING_CLIENT, '0', 'theme_dimensions');
        self::forget();
        [, $modules, $strings] = $this->visitor($categoryid, []);
        $this->assertNotContains('theme_dimensions/categorypublic', $modules, 'Control: the switch closed, no live listing.');
        $this->assertSame(self::PUBLIC_STRINGS - 1, $strings, 'Control: and no fallback sentence.');
    }
}
