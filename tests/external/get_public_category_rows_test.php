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
 * Theme Dimensions - The visitor's web service of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_course\customfield\course_handler;
use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\category\request;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\opengraph;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests for the one function a visitor's client asks for a page of a public category.
 *
 * PHPUnit cannot reach what lib/ajax/service-nologin.php adds - the cookie-less session, the
 * headers, a real batch, the forced login read nowhere on that path - and the NO_MOODLE_COOKIES
 * branch of call_external_function() is skipped under PHPUNIT_TEST. Those were measured over HTTP
 * (the original design record, not part of this repository). What is pinned here: every refusal is the same bytes; nothing
 * that is not public travels, with forced login on and off and in both page-cutting modes; the
 * answer is the anonymous page's; the slice and the tree's criterion are no oracle; the language
 * is spoken and given back; a malformed request is refused before a statement is read; and a page
 * costs no statement per course or per card.
 *
 * Every refusal and every drop has an admitted case beside it in the same run, so no assertion
 * passes against a function that answers nothing.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_public_category_rows::class)]
final class get_public_category_rows_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_get_public_category_rows';

    /** @var string The theme's component. */
    private const COMPONENT = 'theme_dimensions';

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

    /** @var int The most reads a visitor's flat page may cost with the page cut in PHP: the measured value. */
    private const CEILING_PHP = 17;

    /** @var int The same with the page cut in SQL: the measured value. */
    private const CEILING_SQL = 19;

    /** @var int The most reads the refusal of a category the predicate is asked about may cost: the measured value. */
    private const CEILING_REFUSAL = 1;

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /**
     * Leave nothing for the next test: a cachekey left in $_GET refuses every later call of the process.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_GET['cachekey']);
        opengraph::set(null);
        self::forget();
        parent::tearDown();
    }

    /**
     * The four combinations the leak tests run under: the site's forced login and the page-cutting mode.
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
     * The site's forced login, on and off.
     *
     * @return array
     */
    public static function forcelogin_provider(): array {
        return [
            'forced login' => [true],
            'open site' => [false],
        ];
    }

    /**
     * The two page-cutting modes.
     *
     * @return array
     */
    public static function statesort_provider(): array {
        return [
            'page cut in PHP' => [true],
            'page cut in SQL' => [false],
        ];
    }

    /**
     * Seed a public category with every kind of neighbour a visitor must be told nothing of.
     *
     * Names of everything non-public carry a ZZ token, so one scan of a whole answer proves none of
     * them travelled. The merely listed child that holds a public course is the one non-public name
     * an answer legitimately carries (its poster, never linked), so it has no token; the listed child
     * holding no public course keeps one. The service is switched on, and so is the root.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort Whether the page is cut in PHP (the state sort on) or in SQL.
     * @return void
     */
    private function seed(bool $forcelogin, bool $statesort = true): void {
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
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', self::COMPONENT);
        set_config(publicaccess::SETTING_ROOT, '1', self::COMPONENT);
        set_config(publicaccess::SETTING_CLIENT, '1', self::COMPONENT);

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
        $underhidden = (int) $generator->create_category(['parent' => $hidden, 'name' => 'ZZB Publica sob Oculta'])->id;
        $unlisted = (int) $generator->create_category(['parent' => $root, 'name' => 'ZZ6 Fora da lista'])->id;
        $other = (int) $generator->create_category(['name' => 'Outra Vitrine'])->id;
        foreach ([$root, $child, $other, $underhidden] as $categoryid) {
            $categorystate($categoryid, category_discoverability::STATE_PUBLIC);
        }
        $categorystate($unlisted, category_discoverability::STATE_UNLISTED);

        $this->ids = ['root' => $root, 'child' => $child, 'listed' => $listed, 'grand' => $grand, 'empty' => $empty,
            'hidden' => $hidden, 'underhidden' => $underhidden, 'unlisted' => $unlisted, 'other' => $other];
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
        $make($hidden, 'ZZ5 Curso Publico em Categoria Oculta', $public, ['visible' => 1]);
        $make($underhidden, 'ZZC Curso Publico sob Categoria Oculta', $public, ['visible' => 1]);
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
     * Call the function the way service-nologin.php does, as a new request: the count of calls forgotten.
     *
     * @param array $args The arguments.
     * @return array The framework's response: error, data or exception.
     */
    private function call(array $args): array {
        get_public_category_rows::reset();
        return external_api::call_external_function(self::FUNCTION, $args, true);
    }

    /**
     * Call it as a visitor and assert it answered.
     *
     * @param array $args The arguments.
     * @return array The cleaned payload.
     */
    private function ask(array $args): array {
        $this->become(null);
        $response = $this->call($args);
        $this->assertFalse($response['error'], 'The function refused: ' . ($response['exception']->message ?? ''));
        return $response['data'];
    }

    /**
     * Call it in the session as it stands and assert it refused.
     *
     * @param array $args The arguments.
     * @return \stdClass The exception information the framework returns.
     */
    private function refusal(array $args): \stdClass {
        $response = $this->call($args);
        $this->assertTrue($response['error'], 'The function answered what it had to refuse.');
        return $response['exception'];
    }

    /**
     * Call it as a visitor and assert it refused.
     *
     * @param array $args The arguments.
     * @return \stdClass The exception information the framework returns.
     */
    private function refused(array $args): \stdClass {
        $this->become(null);
        return $this->refusal($args);
    }

    /**
     * The statements one call of execute() reads, as a visitor in a new request, and what it threw.
     *
     * execute() is called directly so that the framework's own lookup of the function's record is
     * not counted: what is metered is the function.
     *
     * @param array $args The arguments, by name.
     * @param string|null $errorcode Set to the error code of what was thrown, null when it answered.
     * @return int The reads.
     */
    private function reads_of(array $args, ?string &$errorcode = null): int {
        $this->become(null);
        $errorcode = null;
        $GLOBALS['PAGE'] = new \moodle_page();
        return $this->reads(static function () use ($args, &$errorcode): void {
            try {
                get_public_category_rows::execute(...$args);
            } catch (\moodle_exception $exception) {
                $errorcode = $exception->errorcode;
            }
        });
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
     * Whether no string of the payload is an address of the logged-in shell.
     *
     * @param array $answer The payload.
     * @param string $what What is asserted about.
     * @return void
     */
    private function assert_no_logged_in_address(array $answer, string $what): void {
        foreach (self::strings($answer) as $string) {
            foreach (self::LOGGED_IN_ONLY as $fragment) {
                $this->assertStringNotContainsString($fragment, $string, $what . ' carries an address of the logged-in shell.');
            }
        }
    }

    /**
     * Every request the function may not answer is refused with one exception, byte for byte.
     *
     * A missing id, a negative one, a merely listed category, an unlisted one, a hidden one, a
     * public one under a hidden parent, a merely listed child that holds public courses, each of
     * the four switches, the root the site keeps closed, the guest and a real account. Compared as
     * the whole exception the framework returns, with debugging below DEVELOPER, which is the
     * only level at which the refusal is one: at DEVELOPER the framework adds a backtrace naming
     * the line that refused. The public category and the root, asked by the same visitor under the
     * same settings, are the controls.
     *
     * @param bool $forcelogin Whether the site forces login.
     */
    #[DataProvider('forcelogin_provider')]
    public function test_every_request_it_may_not_answer_is_refused_with_the_same_bytes(bool $forcelogin): void {
        global $CFG, $DB;
        $this->seed($forcelogin);
        $CFG->debug = DEBUG_NONE;
        $CFG->debugdeveloper = false;
        $root = $this->ids['root'];
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;

        $refusals = [
            'a category id nothing holds' => $this->refused(['categoryid' => $missing]),
            'a negative id' => $this->refused(['categoryid' => -3]),
            'a merely listed category' => $this->refused(['categoryid' => $this->ids['empty']]),
            'a merely listed child holding public courses' => $this->refused(['categoryid' => $this->ids['listed']]),
            'an unlisted category' => $this->refused(['categoryid' => $this->ids['unlisted']]),
            'a category core hides' => $this->refused(['categoryid' => $this->ids['hidden']]),
            'a public category under a hidden one' => $this->refused(['categoryid' => $this->ids['underhidden']]),
        ];
        $switches = [
            'the public surface switched off' => [publicaccess::SETTING_ENABLED, '0', $root],
            'the showcase switched off' => [population::SETTING_ENABLED, '0', $root],
            'the service switched off' => [publicaccess::SETTING_CLIENT, '0', $root],
            'the service switched to anything but 1' => [publicaccess::SETTING_CLIENT, 'yes', $root],
            'the service never switched on' => [publicaccess::SETTING_CLIENT, null, $root],
            'the root the site keeps closed' => [publicaccess::SETTING_ROOT, null, 0],
        ];
        foreach ($switches as $case => [$setting, $value, $categoryid]) {
            $before = get_config(self::COMPONENT, $setting);
            if ($value === null) {
                unset_config($setting, self::COMPONENT);
            } else {
                set_config($setting, $value, self::COMPONENT);
            }
            $refusals[$case] = $this->refused(['categoryid' => $categoryid, 'query' => $categoryid === 0 ? 'Curso' : '']);
            if ($before === false) {
                unset_config($setting, self::COMPONENT);
            } else {
                set_config($setting, $before, self::COMPONENT);
            }
        }
        $this->setGuestUser();
        self::forget();
        $refusals['the guest account'] = $this->refusal(['categoryid' => $root]);
        $this->setUser(self::getDataGenerator()->create_user());
        self::forget();
        $refusals['a logged-in account'] = $this->refusal(['categoryid' => $root]);

        $first = reset($refusals);
        $this->assertObjectNotHasProperty('debuginfo', $first, 'Precondition: debugging is below DEVELOPER.');
        $this->assertObjectNotHasProperty('backtrace', $first, 'Precondition: debugging is below DEVELOPER.');
        foreach ($refusals as $case => $exception) {
            $this->assertSame(guard::ERROR_CATEGORY, $exception->errorcode, $case);
            $this->assertSame(json_encode($first), json_encode($exception), $case . ': the same bytes.');
        }

        // The controls: the same visitor, the same settings, a public category and the open root.
        $this->assertSame($root, $this->ask(['categoryid' => $root])['categoryid']);
        $this->assertSame(0, $this->ask(['categoryid' => 0, 'query' => 'Curso'])['categoryid']);
    }

    /**
     * Nothing that is not public travels, and no address is the logged-in shell's.
     *
     * Every non-public name carries a ZZ token and no answer carries one: the grouped page at
     * rest, a flat listing of every card, a search, a public area's panel, the merely listed
     * child's slice, the root's search. The card set is the exact public set. The merely listed
     * child holding public courses is named on its poster and never linked, with the public child
     * beside it as the control that a poster can link; the cover is the control of the address
     * scan, because it is where core's login-walled address would have leaked.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_visitor_is_told_nothing_that_is_not_public(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $answers = [
            'the grouped page at rest' => $this->ask(['categoryid' => $root]),
            'every card by name' => $this->ask(['categoryid' => $root, 'sort' => 'name', 'perpage' => 96]),
            'a search' => $this->ask(['categoryid' => $root, 'sort' => 'recent', 'query' => 'Curso']),
            'a grouped search' => $this->ask(['categoryid' => $root, 'query' => 'Curso']),
            'a public area\'s panel' => $this->ask(['categoryid' => $this->ids['child'], 'withcategories' => true]),
            'the merely listed child\'s slice' => $this->ask([
                'categoryid' => $root,
                'sort' => 'name',
                'slice' => $this->ids['listed'],
            ]),
            'the root\'s search' => $this->ask(['categoryid' => 0, 'query' => 'Curso']),
        ];
        foreach ($answers as $case => $answer) {
            $this->assertStringNotContainsString('ZZ', json_encode($answer), $case . ': nothing that is not public.');
            $this->assert_no_logged_in_address($answer, $case);
        }

        $ids = array_column($answers['every card by name']['cards'], 'id');
        sort($ids);
        $expected = [
            $this->ids['public'], $this->ids['alpha'], $this->ids['childcourse'], $this->ids['listedcourse'],
            $this->ids['zeta'], $this->ids['grandcourse'],
        ];
        sort($expected);
        $this->assertSame($expected, $ids, 'Exactly the public courses below the category.');

        $accordion = array_column($answers['the grouped page at rest']['accordion'], null, 'id');
        $this->assertSame([$this->ids['child'], $this->ids['listed']], array_keys($accordion));
        $this->assertSame('Listada', $accordion[$this->ids['listed']]['name']);
        $this->assertFalse($accordion[$this->ids['listed']]['linkable'], 'The merely listed child is never linked.');
        $this->assertSame('', $accordion[$this->ids['listed']]['url']);
        $this->assertTrue($accordion[$this->ids['child']]['linkable'], 'Control: the public child is.');

        $cover = array_column($answers['every card by name']['cards'], 'imageurl', 'id')[$this->ids['public']] ?? null;
        $this->assertNotNull($cover, 'Precondition: the card carries its cover.');
        $this->assertStringContainsString('/theme_dimensions/', $cover);
        $this->assertSame([$root, $this->ids['other']], array_column($answers['the root\'s search']['accordion'], 'id'));
    }

    /**
     * The answer is the anonymous page's for the same criteria, through the framework's cleaning.
     *
     * The cards, whole, after the returns declaration has run over the page's own as well; the
     * numbers and the sentences of the count line; the addresses; the facets; the areas' result
     * mode; and the posters, in the page's order.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_answer_is_the_anonymous_pages_for_the_same_criteria(bool $forcelogin, bool $statesort): void {
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $cases = [
            'grouped, at rest' => [[], []],
            'flat by name' => [['sort' => 'name'], ['sort' => 'name']],
            'flat by date under a search' => [['sort' => 'recent', 'query' => 'Curso'], ['sort' => 'recent', 'q' => 'Curso']],
            'grouped under a search' => [['query' => 'Curso'], ['q' => 'Curso']],
            // A chip in the row under the line: the period's, from the toolbar's one builder.
            'flat by name over a period' => [
                ['sort' => 'name', 'datefrom' => '2020-01-01'],
                ['sort' => 'name', 'from' => '2020-01-01'],
            ],
            'second page of two' => [
                ['sort' => 'name', 'perpage' => 2, 'page' => 1],
                ['sort' => 'name', 'perpage' => 2, 'page' => 1],
            ],
        ];
        // The page's parts go through the same declaration, which also fixes the order of their keys.
        $returns = get_category_rows::execute_returns()->keys;
        $clean = static fn(string $key, mixed $value): mixed => external_api::clean_returnvalue(
            $returns[$key],
            json_decode(json_encode($value), true)
        );
        foreach ($cases as $case => [$args, $query]) {
            $answer = $this->ask(['categoryid' => $root] + $args);
            $context = $this->page($root, $query);
            /* Only the page's first paint loads its first row at once (category_listing::FIRST_ROW); an answer
               never is one, so the page's cards are compared with that one key set as an answer sets it. */
            $this->assertNotContains(true, array_column($answer['cards'], 'eager'), $case . ': an answer is never a first paint.');
            $pagecards = $clean('cards', $context->cards);
            foreach ($pagecards as $index => $card) {
                $pagecards[$index]['eager'] = false;
            }
            $this->assertSame($pagecards, $answer['cards'], $case . ': the cards, whole.');
            $this->assertSame(json_decode($context->vitrine)->total, $answer['total'], $case);
            foreach (['countnumber', 'countline', 'seeallurl', 'nexturl', 'clearurl', 'hasfilters'] as $key) {
                $this->assertSame($context->$key, $answer[$key], "{$case}: {$key}.");
            }
            $this->assertSame($context->toolbar->filtercount, $answer['filtercount'], $case);
            $this->assertSame($clean('chips', $context->toolbar->chips), $answer['chips'], $case . ': the chips, one builder.');
            $this->assertSame($context->toolbar->clearall, $answer['clearall'], $case);
            $this->assertNotContains('chip', array_column($answer['chips'], 'param'), $case . ': never a state chip.');
            $this->assertSame($clean('facets', $context->toolbar->facets), $answer['facets'], $case);
            $this->assertSame($clean('areas', $context->results), $answer['areas'], $case . ': the areas.');
            $posters = $context->areas === null ? [] : array_merge($context->areas['posters'], $context->areas['postersmore']);
            $this->assertSame(
                array_map(static fn(array $poster): array => [(int) $poster['id'], (string) $poster['name']], $posters),
                array_map(static fn(array $entry): array => [$entry['id'], $entry['name']], $answer['accordion']),
                $case . ': the posters.'
            );
            $this->assertNotSame([], $answer['cards'], $case . ': precondition, the page has cards to compare.');
        }
    }

    /**
     * A merely listed child is asked for as a slice of its parent, and any other id is dropped alike.
     *
     * The slice is the child's panel, flat, never linked, with no strip and fewer courses than the
     * parent's listing. A public child, a hidden one, an unlisted one, a grandchild, a foreign
     * category, a listed child holding nothing public, the category itself and an id nothing holds
     * each answer exactly the parent's flat listing, so the parameter tells nothing about what
     * exists. Asked for by its own id, the listed child is the one refusal.
     */
    public function test_a_slice_is_a_merely_listed_childs_panel_and_any_other_id_is_dropped_alike(): void {
        global $DB;
        $this->seed(true);
        $root = $this->ids['root'];
        $listed = $this->ids['listed'];

        $answer = $this->ask(['categoryid' => $root, 'sort' => 'name', 'slice' => $listed]);
        $this->assertSame($listed, $answer['panel']['id']);
        $this->assertTrue($answer['panel']['isd20']);
        $this->assertFalse($answer['panel']['haslink']);
        $this->assertSame('', $answer['panel']['titleurl']);
        $this->assertFalse($answer['panel']['also']['has'], 'A slice has no strip of subareas.');
        $this->assertSame(
            ['Curso da Neta', 'Curso Listado Publico', 'Zeta Curso Listado'],
            array_column($answer['cards'], 'fullname')
        );
        $whole = $this->ask(['categoryid' => $root, 'sort' => 'name', 'perpage' => 96]);
        $this->assertGreaterThan($answer['total'], $whole['total'], 'The slice is smaller than the parent\'s listing.');

        $plain = json_encode($this->ask(['categoryid' => $root, 'sort' => 'name']));
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $dropped = [
            'a public child' => $this->ids['child'],
            'a hidden child' => $this->ids['hidden'],
            'an unlisted child' => $this->ids['unlisted'],
            'a grandchild' => $this->ids['grand'],
            'a foreign category' => $this->ids['other'],
            'a listed child holding nothing public' => $this->ids['empty'],
            'the category itself' => $root,
            'an id nothing holds' => $missing,
        ];
        foreach ($dropped as $case => $slice) {
            $this->assertSame(
                $plain,
                json_encode($this->ask(['categoryid' => $root, 'sort' => 'name', 'slice' => $slice])),
                $case . ': the parent\'s flat listing, byte for byte.'
            );
        }
        $this->assertNotSame($plain, json_encode($answer), 'Control: the admitted slice is another answer.');

        $this->assertSame(guard::ERROR_CATEGORY, $this->refused(['categoryid' => $listed])->errorcode);
    }

    /**
     * A request that cannot be a slice is malformed whatever the category, and costs nothing.
     *
     * Refused before the gates, so a public category, a merely listed one and an id nothing holds
     * answer alike: invalidparameter, not the category refusal, and no statement read. A slice is
     * flat, the first page, no panel request, no tree criterion, never at the root and never
     * negative; the empty organisation is read as the newest first and is the control.
     */
    public function test_a_request_that_cannot_be_a_slice_is_malformed_whatever_the_category(): void {
        global $DB;
        $this->seed(true);
        $listed = $this->ids['listed'];
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $shapes = [
            'the grouped organisation' => ['sort' => 'category', 'slice' => $listed],
            'a panel request' => ['sort' => 'name', 'withcategories' => true, 'slice' => $listed],
            'a tree criterion' => ['sort' => 'name', 'cat' => $listed, 'slice' => $listed],
            'a later page' => ['sort' => 'name', 'page' => 1, 'slice' => $listed],
            'a negative id' => ['sort' => 'name', 'slice' => -1],
        ];
        $categories = ['a public category' => $this->ids['root'], 'a listed one' => $listed, 'a missing id' => $missing];
        foreach ($categories as $where => $categoryid) {
            foreach ($shapes as $case => $args) {
                $reads = $this->reads_of(['categoryid' => $categoryid] + $args, $errorcode);
                $this->assertSame('invalidparameter', $errorcode, "{$case} on {$where}.");
                $this->assertSame(0, $reads, "{$case} on {$where}: refused before any statement.");
            }
        }
        $this->assertSame('invalidparameter', $this->refused(['categoryid' => 0, 'slice' => $listed])->errorcode);

        $answer = $this->ask(['categoryid' => $this->ids['root'], 'sort' => '', 'slice' => $listed]);
        $this->assertSame($listed, $answer['panel']['id'], 'Control: the empty organisation is a slice.');
    }

    /**
     * A tree criterion the page does not offer is dropped, alike for every id, and never refused.
     *
     * A refusal for a category that is not below the listed one would tell the internet a
     * nonexistent id from a withheld descendant ({@see get_category_rows_test} pins the same rule
     * for an account). A hidden descendant, an unlisted one, a foreign category and an id nothing
     * holds each answer exactly the unfiltered listing; the public child narrows, which is the control.
     *
     * @param bool $forcelogin Whether the site forces login.
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_tree_criterion_it_does_not_offer_is_dropped_alike(bool $forcelogin, bool $statesort): void {
        global $DB;
        $this->seed($forcelogin, $statesort);
        $root = $this->ids['root'];
        $plain = json_encode($this->ask(['categoryid' => $root, 'sort' => 'name']));
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $dropped = [
            'a hidden descendant' => $this->ids['hidden'],
            'a public descendant of a hidden one' => $this->ids['underhidden'],
            'an unlisted descendant' => $this->ids['unlisted'],
            'a foreign category' => $this->ids['other'],
            'an id nothing holds' => $missing,
        ];
        foreach ($dropped as $case => $cat) {
            $this->assertSame(
                $plain,
                json_encode($this->ask(['categoryid' => $root, 'sort' => 'name', 'cat' => $cat])),
                $case . ': the unfiltered listing, byte for byte.'
            );
        }
        $narrowed = $this->ask(['categoryid' => $root, 'sort' => 'name', 'cat' => $this->ids['child']]);
        $this->assertSame(['Curso do Filho'], array_column($narrowed['cards'], 'fullname'), 'Control: the public child narrows.');
    }

    /**
     * The language the page names is spoken, and the one the process had is given back.
     *
     * PHPUnit installs English alone, so a pack of two strings is made for the test. The answer
     * speaks it; an uninstalled code is the request's own language and no error; and after every
     * call, the answered and the refused alike, the process speaks what it spoke before. The
     * refusal is the same bytes whatever language is asked for: the framework reads its message
     * after the function has returned.
     */
    public function test_the_language_the_page_names_is_spoken_and_given_back(): void {
        global $CFG;
        $this->seed(true);
        $root = $this->ids['root'];
        $folder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($folder);
        file_put_contents(
            $folder . '/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Xx';\n\$string['parentlanguage'] = '';\n"
        );
        file_put_contents(
            $folder . '/theme_dimensions.php',
            "<?php\n\$string['category_countline'] = 'XX cursos em {\$a}';\n"
                . "\$string['categoryunavailable'] = 'XX indisponivel';\n"
        );
        get_string_manager()->reset_caches();
        $this->assertTrue(get_string_manager()->translation_exists('xx', false), 'Precondition: the pack is installed.');
        $before = current_language();
        $this->assertSame('en', $before);

        $english = $this->ask(['categoryid' => $root, 'sort' => 'name']);
        $this->assertStringStartsWith('courses in', $english['countline']);

        $spoken = $this->ask(['categoryid' => $root, 'sort' => 'name', 'lang' => 'xx']);
        $this->assertStringStartsWith('XX cursos em', $spoken['countline'], 'The page\'s language is spoken.');
        $this->assertSame($before, current_language(), 'And the process\'s own is given back.');

        $unknown = $this->ask(['categoryid' => $root, 'sort' => 'name', 'lang' => 'qq']);
        $this->assertSame($english['countline'], $unknown['countline'], 'An uninstalled pack is the request\'s own language.');

        // Below DEVELOPER, where the refusal is one: at DEVELOPER it carries the calling line.
        $CFG->debug = DEBUG_NONE;
        $CFG->debugdeveloper = false;
        $refusal = $this->refused(['categoryid' => $this->ids['listed'], 'lang' => 'xx']);
        $this->assertSame($before, current_language(), 'Given back after a refusal too.');
        $this->assertSame(
            json_encode($this->refused(['categoryid' => $this->ids['listed']])),
            json_encode($refusal),
            'The refusal does not change with the language asked for.'
        );
    }

    /**
     * A malformed request is refused as an invalid parameter, and the cheap ones before any statement.
     *
     * The cheap checks run before the gates, so they are asked on an id nothing may be served
     * as well as on a public one: a check that only a public id reached would be masked by the
     * category refusal on the other. The five filters and the duplicate field are such checks,
     * because on a public id the allowlist after the gates refuses them too. The state chip is
     * not a parameter of this function at all, and a field outside the allowlist is refused after
     * the gates. A term of exactly the cap and an ordered pair of dates are the controls.
     */
    public function test_a_malformed_request_is_refused_before_anything_is_read(): void {
        $this->seed(true);
        $root = $this->ids['root'];
        $cheap = [
            'a negative page' => ['page' => -1],
            'an organisation outside the allowlist' => ['sort' => 'sideways'],
            'a date in another spelling' => ['datefrom' => '17/09/2026'],
            'a day September does not have' => ['dateto' => '2026-09-31'],
            'a pair given backwards' => ['datefrom' => '2026-09-19', 'dateto' => '2026-09-17'],
            'a term over the cap' => ['query' => str_repeat('a', get_public_category_rows::MAX_QUERY_BYTES + 1)],
            'a term over the cap in bytes, not characters' => ['query' => str_repeat('é', 501)],
            'five filters' => ['filters' => array_map(
                static fn(int $id): array => ['fieldid' => $id, 'value' => 1],
                range(1, 5)
            )],
            'two filters on one field' => ['filters' => [['fieldid' => 7, 'value' => 1], ['fieldid' => 7, 'value' => 2]]],
        ];
        foreach (['a public category' => $root, 'a listed one' => $this->ids['empty']] as $where => $categoryid) {
            foreach ($cheap as $case => $args) {
                $reads = $this->reads_of(['categoryid' => $categoryid] + $args, $errorcode);
                $this->assertSame('invalidparameter', $errorcode, "{$case} on {$where}.");
                $this->assertSame(0, $reads, "{$case} on {$where}: refused before any statement.");
            }
        }
        $rootonly = [
            'a page' => ['page' => 1],
            'a page size' => ['perpage' => 4],
            'a filter' => ['filters' => [['fieldid' => 7, 'value' => 1]]],
            'an organisation' => ['sort' => 'name'],
            'a start date' => ['datefrom' => '2026-09-17'],
            'an end date' => ['dateto' => '2026-09-17'],
            'a tree criterion' => ['cat' => $root],
            'a panel request' => ['withcategories' => true],
            'a slice' => ['slice' => $root],
        ];
        foreach ($rootonly as $case => $args) {
            $reads = $this->reads_of(['categoryid' => 0, 'query' => 'Curso'] + $args, $errorcode);
            $this->assertSame('invalidparameter', $errorcode, 'The root with ' . $case . '.');
            $this->assertSame(0, $reads, 'The root with ' . $case . ': refused before any statement.');
        }
        $this->assertSame('invalidparameter', $this->refused(['categoryid' => $root, 'chip' => 'enrolled'])->errorcode);
        $this->assertSame(
            'invalidparameter',
            $this->refused(['categoryid' => $root, 'filters' => [['fieldid' => 999999, 'value' => 1]]])->errorcode,
            'A field outside the allowlist.'
        );

        // The controls: what is malformed is the shape, not the parameter.
        $this->ask(['categoryid' => $root, 'query' => str_repeat('a', get_public_category_rows::MAX_QUERY_BYTES)]);
        $this->ask(['categoryid' => $root, 'datefrom' => '2026-09-17', 'dateto' => '2026-09-17']);
        $this->ask(['categoryid' => 0, 'query' => 'Curso']);
    }

    /**
     * A request carrying a cachekey is refused, whatever the category, before any statement.
     *
     * service.php answers a GET carrying a past cachekey with 90 days of public, immutable caching,
     * which would keep a withdrawn course in shared caches; an error is never given them.
     */
    public function test_a_request_carrying_a_cachekey_is_refused(): void {
        $this->seed(true);
        $root = $this->ids['root'];
        $_GET['cachekey'] = time() - 1;
        foreach (['a public category' => $root, 'a listed one' => $this->ids['empty']] as $where => $categoryid) {
            $reads = $this->reads_of(['categoryid' => $categoryid], $errorcode);
            $this->assertSame('invalidparameter', $errorcode, $where);
            $this->assertSame(0, $reads, $where . ': refused before any statement.');
        }
        unset($_GET['cachekey']);
        $this->assertSame($root, $this->ask(['categoryid' => $root])['categoryid'], 'Control: the same call without it.');
    }

    /**
     * One call of the function per request: the second is refused, a refused first one counts too.
     */
    public function test_one_call_per_request(): void {
        $this->seed(true);
        $root = $this->ids['root'];
        $this->become(null);
        get_public_category_rows::reset();
        $first = external_api::call_external_function(self::FUNCTION, ['categoryid' => $root], true);
        $second = external_api::call_external_function(self::FUNCTION, ['categoryid' => $root], true);
        $this->assertFalse($first['error'], 'The first call of a request is answered.');
        $this->assertTrue($second['error'], 'The second is not.');
        $this->assertSame('invalidparameter', $second['exception']->errorcode);

        get_public_category_rows::reset();
        $refused = external_api::call_external_function(self::FUNCTION, ['categoryid' => $this->ids['empty']], true);
        $after = external_api::call_external_function(self::FUNCTION, ['categoryid' => $root], true);
        $this->assertSame(guard::ERROR_CATEGORY, $refused['exception']->errorcode);
        $this->assertSame('invalidparameter', $after['exception']->errorcode, 'A refused first call counts.');

        $this->assertSame($root, $this->ask(['categoryid' => $root])['categoryid'], 'Control: a new request is answered.');
    }

    /**
     * The service's switch closes the service and nothing else: the public page is served with it off.
     *
     * Off by default, off for anything but a stored '1'; the page itself, built by its own entry
     * point, is the control that the switch never reaches the surface's gate.
     */
    public function test_the_switch_closes_the_service_and_never_the_public_page(): void {
        $this->seed(true);
        $root = $this->ids['root'];
        foreach (['never saved' => null, 'a stored 0' => '0', 'anything but 1' => 'yes'] as $case => $value) {
            if ($value === null) {
                unset_config(publicaccess::SETTING_CLIENT, self::COMPONENT);
            } else {
                set_config(publicaccess::SETTING_CLIENT, $value, self::COMPONENT);
            }
            $this->assertSame(guard::ERROR_CATEGORY, $this->refused(['categoryid' => $root])->errorcode, $case);
            $this->assertTrue(publicaccess::is_public($root), $case . ': the category is still public.');
            $context = $this->page($root);
            $this->assertNotSame([], $context->cards, $case . ': and its page is served.');
        }
        set_config(publicaccess::SETTING_CLIENT, '1', self::COMPONENT);
        $this->assertSame($root, $this->ask(['categoryid' => $root])['categoryid'], 'A stored 1 opens it.');
    }

    /**
     * A switched-off surface is refused before the plugin's predicate is asked anything.
     *
     * Both switches above the predicate - the surface's and the showcase's own - answer the one
     * refusal with no statement read. A refusal further down (the population's own, which asks the
     * same chain) would answer the same exception, so what tells the two apart is the cost: the
     * precondition shows that asking the predicate about this category costs a statement.
     */
    public function test_a_switched_off_surface_is_refused_before_the_predicate_is_asked(): void {
        $this->seed(true);
        $root = $this->ids['root'];
        $asked = $this->reads(static function () use ($root): void {
            category_discoverability::is_public($root);
        });
        $this->assertGreaterThan(0, $asked, 'Precondition: the predicate reads the plugin\'s state.');

        foreach ([publicaccess::SETTING_ENABLED, population::SETTING_ENABLED] as $setting) {
            set_config($setting, '0', self::COMPONENT);
            // Warm the configuration, which set_config() emptied, so the meter sees the function alone.
            publicaccess::is_public($root);
            $reads = $this->reads_of(['categoryid' => $root], $errorcode);
            $this->assertSame(guard::ERROR_CATEGORY, $errorcode, $setting);
            $this->assertSame(0, $reads, $setting . ': refused before the predicate is asked.');
            set_config($setting, '1', self::COMPONENT);
        }
        $this->reads_of(['categoryid' => $root], $errorcode);
        $this->assertNull($errorcode, 'Control: switched back on, the category is answered.');
    }

    /**
     * A visitor's page costs no statement per course and none per card, and a refusal costs little.
     *
     * Two public categories of six and of two hundred courses, at a page of four, cost the same; the
     * large one at a page of four and of forty-eight costs the same. Measured on execute(), with
     * the per-request state reset as a new request would have it; the ceilings are the measured
     * values. The refusal of a category the predicate has to be asked about stays within the
     * predicate's own statements.
     *
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('statesort_provider')]
    public function test_a_visitors_page_costs_the_same_statements_whatever_its_size(bool $statesort): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->forcelogin = 1;
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', self::COMPONENT);
        set_config(publicaccess::SETTING_CLIENT, '1', self::COMPONENT);
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $make = function (int $courses) use ($generator, $adminid): int {
            $categoryid = (int) $generator->create_category()->id;
            category_discoverability::set_state($categoryid, category_discoverability::STATE_PUBLIC, $adminid);
            for ($i = 0; $i < $courses; $i++) {
                $course = $generator->create_course(['category' => $categoryid]);
                discoverability::set_state((int) $course->id, discoverability::STATE_PUBLIC, $adminid);
            }
            return $categoryid;
        };
        $small = $make(6);
        $large = $make(200);
        $listed = (int) $generator->create_category()->id;
        $flat = ['sort' => 'name'];

        // Warm the shared caches once, as any request but the first after a purge finds them.
        $this->reads_of(['categoryid' => $small, 'perpage' => 4] + $flat);
        $this->reads_of(['categoryid' => $large, 'perpage' => 48] + $flat);
        $this->reads_of(['categoryid' => $listed]);

        $smallreads = $this->reads_of(['categoryid' => $small, 'perpage' => 4] + $flat, $errorcode);
        $this->assertNull($errorcode);
        $largereads = $this->reads_of(['categoryid' => $large, 'perpage' => 4] + $flat, $errorcode);
        $this->assertNull($errorcode);
        $fortyeight = $this->reads_of(['categoryid' => $large, 'perpage' => 48] + $flat, $errorcode);
        $this->assertNull($errorcode);
        $refusal = $this->reads_of(['categoryid' => $listed], $errorcode);
        $this->assertSame(guard::ERROR_CATEGORY, $errorcode);

        $mode = $statesort ? 'php' : 'sql';
        $this->record("public web service, {$mode} cut", [
            'reads at N=6, P=4' => $smallreads,
            'reads at N=200, P=4' => $largereads,
            'reads at N=200, P=48' => $fortyeight,
            'reads of a refusal' => $refusal,
        ]);
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads over six courses against {$largereads} over two hundred.");
        $this->assertSame($largereads, $fortyeight, "{$largereads} reads for four cards against {$fortyeight} for forty-eight.");
        $this->assertLessThanOrEqual($statesort ? self::CEILING_PHP : self::CEILING_SQL, $largereads);
        $this->assertLessThanOrEqual(self::CEILING_REFUSAL, $refusal);
    }

    /**
     * Record one measurement in the budget's JSON file of numbers, beside budget_test's.
     *
     * @param string $label What was measured.
     * @param array $numbers The numbers.
     * @return void
     */
    private function record(string $label, array $numbers): void {
        // Outside the test data root, which PHPUnit empties between tests.
        $path = sys_get_temp_dir() . '/theme_dimensions-vitrine-budget.json';
        $all = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $all[$label] = $numbers + ['measured' => date('c')];
        file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT));
    }
}
