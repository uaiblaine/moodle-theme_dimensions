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
 * Theme Dimensions - The visitor's answer of the one method behind the paging web service
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;
use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\external\get_category_rows;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\opengraph;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * What {@see rows::fetch()} answers a visitor, against what the anonymous page renders.
 *
 * The web service this method sits behind is logged-in only today, so nothing but this test
 * asks it a visitor's question. Four properties are pinned. The answer is the page's, card for
 * card, whatever the site's forced login says and in both page-cutting modes. No address in it is
 * the logged-in shell's, which a visitor would land on the login form from. The visitor's merely
 * listed child is asked for as a slice of its parent and nothing else is: the same panel the page
 * draws, and for any id that is not such a child exactly the parent's flat listing, byte for
 * byte, so the parameter is no oracle. And the export of a visitor's cards reads no course
 * contact, which a visitor's card never prints.
 *
 * Every refusal and every drop has an admitted case beside it in the same run, so no assertion
 * here passes against a method that answers nothing.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(rows::class)]
#[CoversClass(category_listing::class)]
#[CoversClass(dialogue::class)]
final class rows_anonymous_test extends \advanced_testcase {
    use fixture;

    /**
     * @var string[] Fragments of the logged-in shell's addresses: none may reach a visitor.
     *
     * The theme's own file route is a pluginfile.php address too, so what core's login-walled areas
     * spell is named, not the script.
     */
    private const LOGGED_IN_ONLY = [
        '/course/index.php',
        '/course/view.php',
        '/enrol/',
        '/course/overviewfiles/',
        '/course/summary/',
    ];

    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var array The fixture's ids and names, by role. */
    private array $ids = [];

    /**
     * Leave nothing for the next test: the page arms a head-tag registry the fixture does not know.
     *
     * @return void
     */
    protected function tearDown(): void {
        opengraph::set(null);
        self::forget();
        parent::tearDown();
    }

    /**
     * The four combinations every visitor test runs under: the site's forced login and the page-cutting mode.
     *
     * Forced login off is the case where a visitor who is NOT asked as a visitor is served the
     * logged-in listing (user 0 may see every listed course then); forced login on is the case
     * where the same mistake is refused. A flag dropped on the way is a leak in the first and
     * an exception in the second, so a test holding only one of them proves half.
     *
     * @return array
     */
    public static function modes_provider(): array {
        return [
            'forced login, page cut in PHP' => [true, true],
            'forced login, page cut in SQL' => [true, false],
            'open site, page cut in PHP' => [false, true],
            'open site, page cut in SQL' => [false, false],
        ];
    }

    /**
     * Seed a public category with every kind of neighbour a visitor must be told nothing of.
     *
     * Names of everything non-public carry a ZZ token so that one scan of the whole answer
     * proves none of them travelled. The merely listed child that holds a public course is the
     * one non-public name the answer legitimately carries (the visitor's text poster), so its
     * name has no token; the one that holds no public course keeps its.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort Whether the page is cut in PHP (the state sort on) or in SQL.
     * @return void
     */
    private function seed(bool $forcelogin, bool $statesort): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $CFG->forcelogin = $forcelogin ? 1 : 0;
        $CFG->routerconfigured = false;
        $CFG->coursecontact = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        set_config(publicaccess::SETTING_ROOT, '1', 'theme_dimensions');

        $generator = self::getDataGenerator();
        $handler = course_handler::create();
        $adminid = (int) get_admin()->id;
        $make = function (int $categoryid, string $fullname, ?int $state, array $extra = []) use ($handler, $adminid): int {
            $course = self::getDataGenerator()->create_course(['category' => $categoryid, 'fullname' => $fullname] + $extra);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            ]);
            if ($state !== null) {
                discoverability::set_state((int) $course->id, $state, $adminid);
            }
            return (int) $course->id;
        };
        $public = discoverability::STATE_PUBLIC;
        $categorystate = static function (int $categoryid, int $state) use ($adminid): void {
            category_discoverability::set_state($categoryid, $state, $adminid);
        };

        $root = (int) $generator->create_category(['name' => 'Vitrine & Cia'])->id;
        $child = (int) $generator->create_category(['parent' => $root, 'name' => 'Brigada'])->id;
        $listed = (int) $generator->create_category(['parent' => $root, 'name' => 'Listada'])->id;
        $grand = (int) $generator->create_category(['parent' => $listed, 'name' => 'Neta'])->id;
        $empty = (int) $generator->create_category(['parent' => $root, 'name' => 'ZZ7 Vazia'])->id;
        $hidden = (int) $generator->create_category(['parent' => $root, 'name' => 'ZZ4 Oculta', 'visible' => 0])->id;
        $unlisted = (int) $generator->create_category(['parent' => $root, 'name' => 'ZZ6 Fora da lista'])->id;
        $other = (int) $generator->create_category(['name' => 'Outra Vitrine'])->id;
        foreach ([$root, $child, $other] as $categoryid) {
            $categorystate($categoryid, category_discoverability::STATE_PUBLIC);
        }
        $categorystate($unlisted, category_discoverability::STATE_UNLISTED);

        $this->ids = ['root' => $root, 'child' => $child, 'listed' => $listed, 'grand' => $grand, 'empty' => $empty,
            'hidden' => $hidden, 'unlisted' => $unlisted, 'other' => $other];
        $this->ids['public'] = $make($root, 'Curso Publico', $public);
        $this->ids['alpha'] = $make($root, 'Alfa Curso Publico', $public, ['startdate' => time() - 5 * DAYSECS]);
        $this->ids['childcourse'] = $make($child, 'Curso do Filho', $public);
        $this->ids['listedcourse'] = $make($listed, 'Curso Listado Publico', $public);
        $this->ids['zeta'] = $make($listed, 'Zeta Curso Listado', $public, ['startdate' => time() - 9 * DAYSECS]);
        $this->ids['grandcourse'] = $make($grand, 'Curso da Neta', $public);
        $this->ids['othercourse'] = $make($other, 'Curso de Outra Vitrine', $public);
        // The non-public neighbours, each in a place the visitor does reach and a place it does not.
        $make($root, 'ZZ1 Curso Interno', null);
        $make($root, 'ZZ2 Curso Fora da Lista', discoverability::STATE_UNLISTED);
        $make($root, 'ZZ3 Curso Oculto', $public, ['visible' => 0]);
        $make($listed, 'ZZ8 Curso Interno da Listada', null);
        $make($empty, 'ZZ9 Curso Interno da Vazia', null);
        $make($hidden, 'ZZ5 Curso Publico em Categoria Oculta', $public);
        $make($unlisted, 'ZZA Curso Publico em Categoria Fora da Lista', $public);

        // A cover on one public course: the card's image is where core's login-walled address would leak.
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($this->ids['public'])->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'card.png',
        ], base64_decode(self::PNG));

        $teacher = $generator->create_user(['firstname' => 'Tereza', 'lastname' => 'Contato']);
        $generator->enrol_user($teacher->id, $this->ids['public'], 'editingteacher');
        self::forget();
    }

    /**
     * Ask the method a question as the framework does: a fresh page, a visitor, the anonymous flag.
     *
     * @param int $categoryid The category.
     * @param array $args The request.
     * @return array The payload.
     */
    private function ask(int $categoryid, array $args = []): array {
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->become(null);
        return rows::fetch(\core_course_category::get($categoryid, MUST_EXIST, true), $args + ['anonymous' => true]);
    }

    /**
     * What the anonymous page renders for an address: its listing's context, from the page's own entry point.
     *
     * @param int $categoryid The category.
     * @param array $query The query string, name => value.
     * @return \stdClass The listing's exported context.
     */
    private function page(int $categoryid, array $query = []): \stdClass {
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->become(null);
        foreach ($query as $name => $value) {
            $_GET[$name] = $value;
        }
        $request = request::handle($categoryid);
        foreach (array_keys($query) as $name) {
            unset($_GET[$name]);
        }
        $this->assertNull($request->redirect, 'The visitor was served the page and not sent away.');
        return $request->page->export_for_template($GLOBALS['PAGE']->get_renderer('core'));
    }

    /**
     * Every string in a payload, however deep.
     *
     * @param mixed $value The payload or a part of it.
     * @return string[]
     */
    private static function strings(mixed $value): array {
        if (is_string($value)) {
            return [$value];
        }
        if (!is_array($value) && !is_object($value)) {
            return [];
        }
        $found = [];
        foreach ((array) $value as $part) {
            $found = array_merge($found, self::strings($part));
        }
        return $found;
    }

    /**
     * The cards of a payload or an exported context, as plain arrays comparable with assertSame.
     *
     * @param array|\stdClass $source The payload (cards are arrays) or the context (cards are objects).
     * @return array
     */
    private static function cards(array|\stdClass $source): array {
        $cards = is_array($source) ? $source['cards'] : $source->cards;
        $cards = json_decode(json_encode($cards), true);
        /* The two keys a page and an answer for the same criteria tell apart on purpose: only the page's first
           paint loads its first row at once (category_listing::FIRST_ROW) and asks for its first cover first, and
           a web service answer never is one. test_a_visitors_fetch_is_the_anonymous_pages_card_for_card asserts
           the answer's side of it. */
        foreach ($cards as $index => $card) {
            $cards[$index]['eager'] = false;
            $cards[$index]['priority'] = false;
        }
        return $cards;
    }

    /**
     * Whether no string of the payload is an address of the logged-in shell.
     *
     * @param array $answer The payload.
     * @param string $what What is asserted about.
     * @return void
     */
    private function assert_no_logged_in_address(array $answer, string $what): void {
        $strings = self::strings($answer);
        foreach ($strings as $string) {
            foreach (self::LOGGED_IN_ONLY as $fragment) {
                $this->assertStringNotContainsString($fragment, $string, $what . ' carries an address of the logged-in shell.');
            }
        }
    }

    /**
     * An anonymous fetch is the anonymous page's, card for card, and the visitor is told nothing else.
     *
     * The listed course, the unlisted one, the hidden one and every course under a category the
     * visitor may not browse are the controls; the exact set of ids is asserted beside the
     * parity, so an answer that was the page's but for one course cannot pass, and with forced
     * login off the same call without the flag would be the logged-in listing and show them.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_visitors_fetch_is_the_anonymous_pages_card_for_card(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $from = date('Y-m-d', time() - 6 * DAYSECS);
        $to = date('Y-m-d', time() - 4 * DAYSECS);
        $cases = [
            'grouped, at rest' => [[], []],
            'flat by name' => [['sort' => 'name'], ['sort' => 'name']],
            'flat by date under a search' => [['sort' => 'recent', 'query' => 'Curso'], ['sort' => 'recent', 'q' => 'Curso']],
            'second page of two' => [
                ['sort' => 'name', 'pagesize' => 2, 'page' => 1],
                ['sort' => 'name', 'perpage' => 2, 'page' => 1],
            ],
            'a date window' => [
                ['sort' => 'name', 'datefrom' => $from, 'dateto' => $to],
                ['sort' => 'name', 'from' => $from, 'to' => $to],
            ],
        ];
        foreach ($cases as $case => [$args, $query]) {
            $answer = $this->ask($root, $args);
            $context = $this->page($root, $query);
            $this->assertSame(self::cards($context), self::cards($answer), $case . ': the cards, whole.');
            $this->assertNotContains(true, array_column($answer['cards'], 'eager'), $case . ': an answer is never a first paint.');
            $this->assertNotContains(true, array_column($answer['cards'], 'priority'), $case . ': nor asks for a cover first.');
            $this->assertSame(json_decode($context->vitrine)->total, $answer['total'], $case . ': the total.');
            $this->assertSame($context->countnumber, $answer['countnumber'], $case);
            $this->assertSame($context->countline, $answer['countline'], $case);
            $this->assertSame($context->nexturl, $answer['nexturl'], $case);
            $this->assertSame($context->clearurl, $answer['clearurl'], $case);
            $this->assertSame($context->hasfilters, $answer['hasfilters'], $case);
            $this->assertSame($context->toolbar->filtercount, $answer['filtercount'], $case);
            $this->assertSame(
                array_map(static fn(array $chip): array => array_diff_key($chip, ['isperiod' => true]), $context->toolbar->chips),
                $answer['chips'],
                $case . ': the chips, from the toolbar\'s one builder.'
            );
            $this->assertSame(json_decode(json_encode($context->toolbar->facets), true), $answer['facets'], $case);
            $this->assert_no_logged_in_address($answer, $case);
        }

        // The exact public set, and a control that the page is not simply empty.
        $answer = $this->ask($root, ['sort' => 'name', 'pagesize' => 96]);
        $names = array_column(self::cards($answer), 'fullname');
        $expected = [
            'Alfa Curso Publico', 'Curso da Neta', 'Curso do Filho', 'Curso Listado Publico', 'Curso Publico',
            'Zeta Curso Listado',
        ];
        $this->assertSame($expected, $names);
        $this->assertStringNotContainsString('ZZ', json_encode($answer), 'Nothing non-public travelled.');

        // The control of the scan: the course that has a cover shows it, and through the theme's route.
        $cover = array_column(self::cards($answer), 'imageurl', 'id')[$this->ids['public']] ?? null;
        $this->assertNotNull($cover, 'Precondition: the card carries its cover.');
        $this->assertStringContainsString('/theme_dimensions/', $cover);
    }

    /**
     * The same fetch without the flag is the logged-in shell's, which is what the scan is guarding against.
     *
     * Without it the scan above could pass for an answer that never had a logged-in address to
     * leak. The logged-in answer of the same category, for a student, carries them.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_logged_in_answer_of_the_same_category_carries_the_addresses_a_visitor_must_not(
        bool $forcelogin,
        bool $statesort
    ): void {
        $this->seed($forcelogin, $statesort);
        $student = self::getDataGenerator()->create_user();
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->become($student);
        $category = \core_course_category::get($this->ids['root'], MUST_EXIST, true);
        $answer = rows::fetch($category, ['sort' => 'name']);
        $strings = implode("\n", self::strings($answer));
        $this->assertStringContainsString('/course/index.php', $strings, 'The logged-in shell\'s own address.');
        $this->assertStringContainsString('/course/overviewfiles/', $strings, 'And core\'s cover, which a visitor is walled from.');

        // An explicit false is the default: the same bytes as the call that never heard of the option.
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->become($student);
        $explicit = rows::fetch($category, ['sort' => 'name', 'anonymous' => false]);
        $this->assertSame(json_encode($answer), json_encode($explicit));
        $names = array_column(self::cards($answer), 'fullname');
        $this->assertContains('ZZ1 Curso Interno', $names, 'The listed course is the control.');

        // A slice is a visitor's request and is refused for anybody else, whoever asks.
        $this->expectException(\invalid_parameter_exception::class);
        rows::fetch($category, ['sort' => 'name', 'slice' => $this->ids['listed']]);
    }

    /**
     * The grouped answer names its areas with the public page's addresses, and the root's search does too.
     *
     * The accordion's shell is built from the request, so a flag read from anywhere but the
     * request would leave its call to action on the logged-in shell; the merely listed child's
     * way out is the "see all as a list" address, built in a different branch of the same
     * builder, so both are asked for.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_areas_and_the_root_of_a_visitor_answer_with_public_addresses(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);

        $answer = $this->ask($this->ids['root'], ['query' => 'Curso']);
        $accordion = array_column($answer['accordion'], null, 'id');
        $this->assertSame(
            [$this->ids['child'], $this->ids['listed']],
            array_keys($accordion),
            'The public child and the merely listed one that holds a public course, and no other.'
        );
        $this->assertStringContainsString('category.php?id=' . $this->ids['root'], $accordion[$this->ids['listed']]['ctaurl']);
        $this->assertStringContainsString('cat=' . $this->ids['listed'], $accordion[$this->ids['listed']]['ctaurl']);
        $this->assertStringContainsString('category.php?id=' . $this->ids['child'], $accordion[$this->ids['child']]['ctaurl']);
        $this->assert_no_logged_in_address($answer, 'the grouped answer');

        $rootanswer = $this->ask(0, ['query' => 'Curso']);
        $this->assertSame(0, $rootanswer['categoryid']);
        $this->assertStringContainsString('category.php?id=0', $rootanswer['clearurl']);
        $this->assertSame(
            [$this->ids['root'], $this->ids['other']],
            array_column($rootanswer['accordion'], 'id'),
            'The root\'s posters are the public top-level categories.'
        );
        foreach ($rootanswer['accordion'] as $entry) {
            $this->assertStringContainsString('category.php?id=' . $entry['id'], $entry['ctaurl']);
        }
        $this->assert_no_logged_in_address($rootanswer, 'the root\'s answer');
        $this->assertStringNotContainsString('ZZ', json_encode($rootanswer));

        // A panel of a public area: its title and its way out are the area's public page.
        $panel = $this->ask($this->ids['child'], ['withcategories' => true, 'sort' => 'name']);
        $this->assertSame($this->ids['child'], $panel['panel']['id']);
        $this->assertStringContainsString('category.php?id=' . $this->ids['child'], $panel['panel']['titleurl']);
        $this->assertStringContainsString('category.php?id=' . $this->ids['child'], $panel['panel']['cta']['url']);
        $this->assert_no_logged_in_address($panel, 'a public area\'s panel');
    }

    /**
     * Every poster of a visitor's page opens its panel, at rest and under a criterion, on the page and in the answer.
     *
     * The visitor's live listing (js/esm/src/listing/visitor.ts) opens every poster's panel in place and
     * keeps each poster a link to its area's panel, never the link to the area's own page, because a
     * visitor's posters are the areas holding a public course - at rest each counts one - and
     * under a criterion every poster opens, its zero panel included ({@see population::expandable()}).
     * A poster that did not open would be announced as a button opening an empty panel there.
     *
     * The search that misses the public child is the control that the term really narrowed: a
     * poster counting nothing is still one that opens.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_every_poster_of_a_visitors_page_opens_its_panel(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];

        $posters = $this->page($root)->areas['posters'];
        $this->assertNotEmpty($posters, 'Precondition: the page draws posters.');
        foreach ($posters as $poster) {
            $this->assertTrue($poster['expandable'], 'Area ' . $poster['id'] . ' does not open its panel at rest.');
        }

        foreach (['' => false, 'Listado' => true] as $term => $zeroes) {
            $accordion = $this->ask($root, ['query' => $term])['accordion'];
            $this->assertNotEmpty($accordion, 'Precondition: the answer names the posters.');
            $counts = array_column($accordion, 'count', 'id');
            $this->assertSame(
                $zeroes,
                (int) $counts[$this->ids['child']] === 0,
                'The control: the term misses the public child, or the page is at rest.'
            );
            foreach ($accordion as $entry) {
                $this->assertTrue($entry['expandable'], 'Area ' . $entry['id'] . ' does not open its panel for "' . $term . '".');
            }
        }

        foreach ($this->ask(0, ['query' => 'Curso'])['accordion'] as $entry) {
            $this->assertTrue($entry['expandable'], 'A poster of the root always opens its panel.');
        }
    }

    /**
     * A visitor's merely listed child is asked for as a slice, and the panel is the page's own.
     *
     * Compared with the panel the anonymous page draws for the same address - `open` and `psort`
     * - key for key but for the three no-JavaScript addresses and the suggestions, which belong
     * to a page this answer does not know. At rest, in both orders, and under a search that the
     * child misses: its poster stays and its panel is the zero state, which a slice dropped for
     * having no result would break by answering the parent's whole listing in its place.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_slice_is_the_panel_the_page_draws_for_a_merely_listed_child(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $listed = $this->ids['listed'];
        $nojs = ['sorturlrecent', 'sorturlname', 'closeurl', 'suggestions', 'hassuggestions', 'cards'];
        $cases = [
            'at rest, newest first' => [['sort' => 'recent'], ['open' => $listed]],
            'at rest, by name' => [['sort' => 'name'], ['open' => $listed, 'psort' => 'name']],
            'the empty sort is the newest first' => [['sort' => ''], ['open' => $listed]],
            'under a search it matches' => [
                ['sort' => 'name', 'query' => 'Zeta'],
                ['open' => $listed, 'psort' => 'name', 'q' => 'Zeta'],
            ],
            'under a search it misses' => [['sort' => 'recent', 'query' => 'Alfa'], ['open' => $listed, 'q' => 'Alfa']],
        ];
        foreach ($cases as $case => [$args, $query]) {
            $answer = $this->ask($root, $args + ['slice' => $listed]);
            $pagecontext = $this->page($root, $query);
            $this->assertTrue($pagecontext->areas['isopen'], $case . ': precondition, the page draws the panel.');
            $pagepanel = $pagecontext->areas['panel'];
            $this->assertTrue($pagepanel['isd20'], $case);

            $this->assertArrayHasKey('panel', $answer, $case);
            $panel = $answer['panel'];
            $this->assertTrue($panel['isd20'], $case);
            $this->assertFalse($panel['haslink'], $case . ': it has no page of its own.');
            $this->assertSame('', $panel['titleurl'], $case);
            foreach (array_diff(array_keys($panel), $nojs) as $key) {
                $this->assertSame($pagepanel[$key], $panel[$key], "{$case}: the panel's {$key}.");
            }
            $this->assertSame(
                json_decode(json_encode($pagepanel['cards']), true),
                self::cards($answer),
                $case . ': the cards, whole, overlines included.'
            );
            $this->assertSame($root, $answer['categoryid'], $case);
            $this->assert_no_logged_in_address($answer, $case);
            $this->assertStringNotContainsString('ZZ', json_encode($answer), $case);
        }

        // Shape: eight at most, the child's public courses only, and fewer than the parent's whole listing.
        $answer = $this->ask($root, ['sort' => 'name', 'slice' => $listed]);
        $this->assertSame(
            ['Curso da Neta', 'Curso Listado Publico', 'Zeta Curso Listado'],
            array_column(self::cards($answer), 'fullname')
        );
        $this->assertSame(3, $answer['total']);
        $whole = $this->ask($root, ['sort' => 'name', 'pagesize' => 96]);
        $this->assertGreaterThan($answer['total'], $whole['total'], 'The slice is smaller than the parent\'s listing.');
        $this->assertFalse($answer['panel']['also']['has'], 'A slice has no strip of subareas.');
        $this->assertTrue($answer['panel']['seeaslist']['has']);
        $this->assertStringContainsString('cat=' . $listed, $answer['panel']['seeaslist']['url']);

        // The returns declaration is an allowlist: the answer must survive it whole.
        $cleaned = external_api::clean_returnvalue(get_category_rows::execute_returns(), $answer);
        $this->assertSame($answer['panel']['isd20'], $cleaned['panel']['isd20']);
        $this->assertSame(array_column($answer['cards'], 'id'), array_column($cleaned['cards'], 'id'));
    }

    /**
     * A public area's panel, asked by its own id, is the panel the anonymous page draws for it.
     *
     * The visitor's live client opens a merely listed area's panel as a slice (the test above) and a
     * public area's with the panel request on the area's own id, which is the path this holds:
     * compared with the panel the page draws for the same address - `open` and `psort` - key for key
     * but for the no-JavaScript addresses and the suggestions, which belong to a page the answer does
     * not know, and card for card. At rest in both orders, under a search the area matches, and under
     * one it misses, where the panel is the zero state the page draws for the area it keeps open.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_public_areas_panel_is_the_one_the_page_draws(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $child = $this->ids['child'];
        $nojs = ['sorturlrecent', 'sorturlname', 'closeurl', 'suggestions', 'hassuggestions', 'cards'];
        $cases = [
            'at rest, newest first' => [['sort' => 'recent'], ['open' => $child]],
            'at rest, by name' => [['sort' => 'name'], ['open' => $child, 'psort' => 'name']],
            'under a search it matches' => [['sort' => 'recent', 'query' => 'Filho'], ['open' => $child, 'q' => 'Filho']],
            'under a search it misses' => [
                ['sort' => 'name', 'query' => 'Zeta'],
                ['open' => $child, 'psort' => 'name', 'q' => 'Zeta'],
            ],
        ];
        $shown = [];
        foreach ($cases as $case => [$args, $query]) {
            $answer = $this->ask($child, $args + ['withcategories' => true, 'pagesize' => population::PANEL_SIZE]);
            $pagecontext = $this->page($root, $query);
            $this->assertTrue($pagecontext->areas['isopen'], $case . ': precondition, the page draws the panel.');
            $pagepanel = $pagecontext->areas['panel'];
            $this->assertFalse($pagepanel['isd20'], $case . ': precondition, the page draws it as the area\'s own.');

            $this->assertArrayHasKey('panel', $answer, $case);
            $panel = $answer['panel'];
            $this->assertSame($child, $panel['id'], $case);
            $this->assertTrue($panel['haslink'], $case . ': its title is the area\'s public page.');
            foreach (array_diff(array_keys($panel), $nojs) as $key) {
                $this->assertSame($pagepanel[$key], $panel[$key], "{$case}: the panel's {$key}.");
            }
            $this->assertSame(
                json_decode(json_encode($pagepanel['cards']), true),
                self::cards($answer),
                $case . ': the cards, whole.'
            );
            $this->assert_no_logged_in_address($answer, $case);
            $this->assertStringNotContainsString('ZZ', json_encode($answer), $case);
            $shown[$case] = [$panel['isrows'], $panel['iszero']];
        }
        // The controls: the cases really are a listing and a zero state.
        $this->assertSame([true, false], $shown['under a search it matches']);
        $this->assertSame([false, true], $shown['under a search it misses']);
    }

    /**
     * Any id that is not a merely listed child with a public course is dropped, and all of them alike.
     *
     * A public child, a core-hidden one, an unlisted one, a grandchild, a nonexistent id, a
     * foreign category and a listed child that holds no public course each answer exactly the
     * parent's flat listing - the same bytes, with no panel - so the parameter says nothing about
     * what exists. The admitted slice beside them is the control that the answer could have been
     * a panel, and the empty listed child keeps its name out of the answer.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_slice_that_names_no_such_child_is_dropped_alike_for_every_id(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $plain = json_encode($this->ask($root, ['sort' => 'name']));
        $missing = $this->ids['other'] + 1000;
        $cases = [
            'a public child' => $this->ids['child'],
            'a core-hidden child' => $this->ids['hidden'],
            'an unlisted child' => $this->ids['unlisted'],
            'a grandchild' => $this->ids['grand'],
            'a nonexistent id' => $missing,
            'a foreign category' => $this->ids['other'],
            'a listed child holding no public course' => $this->ids['empty'],
            'the category itself' => $root,
        ];
        foreach ($cases as $case => $slice) {
            $answer = $this->ask($root, ['sort' => 'name', 'slice' => $slice]);
            $this->assertArrayNotHasKey('panel', $answer, $case);
            $this->assertSame($plain, json_encode($answer), $case . ': the parent\'s flat listing, byte for byte.');
        }
        // The empty sort is the newest first for a slice that is dropped too, never the grouped page.
        $flat = json_encode($this->ask($root, ['sort' => 'recent']));
        $this->assertSame($flat, json_encode($this->ask($root, ['sort' => '', 'slice' => $this->ids['child']])));
        $this->assertNotSame($flat, json_encode($this->ask($root, ['sort' => ''])), 'The grouped page is another answer.');
        $this->assertStringNotContainsString('Vazia', $plain);
        $this->assertStringNotContainsString('Oculta', $plain);

        // The control: the admitted child answers a panel, so the equality above is not a constant.
        $admitted = $this->ask($root, ['sort' => 'name', 'slice' => $this->ids['listed']]);
        $this->assertArrayHasKey('panel', $admitted);
        $this->assertNotSame($plain, json_encode($admitted));
    }

    /**
     * A slice that cannot be one is refused as malformed, before anything is looked up.
     *
     * The grouped organisation is the one that matters: it drops the tree's criterion in silence,
     * so a slice sent with it would be answered with the parent's whole grouped page and no
     * error. The empty sort, which the server reads as the newest first, is the control that the
     * check is on the organisation asked for and not on the word.
     */
    public function test_a_slice_that_cannot_be_one_is_refused_as_malformed(): void {
        $this->seed(true, true);
        $root = $this->ids['root'];
        $listed = $this->ids['listed'];
        $refused = [
            'the grouped organisation' => [$root, ['sort' => 'category']],
            'a panel request' => [$root, ['sort' => 'name', 'withcategories' => true]],
            'a tree criterion' => [$root, ['sort' => 'name', 'cat' => $listed]],
            'a later page' => [$root, ['sort' => 'name', 'page' => 1]],
            'the root' => [0, ['sort' => 'name']],
            'a negative id' => [$root, ['sort' => 'name', 'slice' => -1]],
            'a logged-in request' => [$root, ['sort' => 'name', 'anonymous' => false]],
        ];
        foreach ($refused as $case => [$categoryid, $args]) {
            try {
                $this->ask($categoryid, $args + ['slice' => $listed]);
                $this->fail($case . ' was answered.');
            } catch (\invalid_parameter_exception) {
                $this->assertTrue(true);
            }
        }
        $answer = $this->ask($root, ['sort' => '', 'slice' => $listed]);
        $this->assertSame([$listed], [$answer['panel']['id']], 'The empty sort is the newest first, and narrows.');
        $this->assertSame(3, $answer['total']);
    }

    /**
     * No export of cards reads the course contacts, a visitor's or a person's: only the course dialogue names them.
     *
     * A statement count cannot see this on its own: the contacts are served from a cache that
     * stays warm in the meter, so the meter empties it inside the metered call. The dialogue's
     * content for the same course is the control that the contacts really cost a statement with
     * the cache empty, and the warm runs are the control that the exports' cost is not the cache's.
     */
    public function test_no_export_of_cards_reads_course_contacts(): void {
        global $DB;

        $this->seed(true, true);
        $this->become(null);
        $result = population::resolve(new criteria($this->ids['root'], ['anonymous' => true, 'sort' => 'name']));
        $this->assertContains(
            $this->ids['public'],
            $result->ids(),
            'Precondition: the course holding a contact is on the page.'
        );
        $loggedin = clone $result;
        $loggedin->anonymous = false;
        $output = $GLOBALS['PAGE']->get_renderer('core');
        $export = function (page_result $page, bool $cold) use ($output): int {
            return $this->reads(static function () use ($page, $cold, $output): void {
                if ($cold) {
                    \cache::make('core', 'coursecontacts')->purge();
                }
                category_listing::export_cards($page, 0, false, $output);
            });
        };
        $export($result, false);
        $this->assertSame($export($result, false), $export($result, true), 'A visitor\'s export: the same reads, cache empty.');
        $export($loggedin, false);
        $this->assertSame($export($loggedin, false), $export($loggedin, true), 'A person\'s export: the same reads, cache empty.');

        $course = $DB->get_record('course', ['id' => $this->ids['public']], dialogue::COURSE_FIELDS);
        $dialogue = function (bool $cold) use ($course): int {
            return $this->reads(static function () use ($course, $cold): void {
                if ($cold) {
                    \cache::make('core', 'coursecontacts')->purge();
                }
                dialogue::content($course);
            });
        };
        $dialogue(false);
        $warm = $dialogue(false);
        $this->assertGreaterThan($warm, $dialogue(true), 'Control: the dialogue reads the contacts the cache does not hold.');
    }
}
