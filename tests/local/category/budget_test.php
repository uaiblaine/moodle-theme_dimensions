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
 * Theme Dimensions - The statement and cost budget of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\category_card;
use theme_dimensions\output\category_listing;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The statement and cost budgets of the category showcase: zero slopes, ceilings and a cost bound.
 *
 * A "first render" here is the whole decision layer for page one of the
 * logged-in shell: {@see population::resolve()} and the four per-page layers
 * of {@see cards}. Reads are perf_get_reads() deltas on a request that has
 * been reset the way a new one would be - every per-request memo dropped, the
 * context cache emptied - with the shared MUC layers left warm. Each run's
 * numbers are also written to a JSON file in the system temp directory
 * ({@see record()}).
 *
 * The cost bound exists because a statement count cannot see a regression in
 * per-row cost at all: it is loose on purpose, an order of magnitude above
 * the measured value.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class budget_test extends \advanced_testcase {
    use fixture;

    /** @var int The most reads a warm first render may cost, whatever the subtree holds: the measured value. */
    private const WARM_CEILING = 16;

    /**
     * @var int The same ceiling with the page cut in SQL, which pays its exception-list statements and a
     *   count the PHP cut does not: the measured value, the same on PostgreSQL and on MariaDB.
     */
    private const WARM_CEILING_SQL = 19;

    /** @var int Peak memory a first render of two hundred courses may add, in bytes; the measured peak is about 600 KB. */
    private const COST_MEMORY = 8 * 1024 * 1024;

    /** @var float Wall time a first render of two hundred courses may take, in seconds; the measured time is about 50 ms. */
    private const COST_SECONDS = 0.5;

    /** @var int Reads a directory card with a description may add: core's text-filter setup for its context. */
    private const DESCRIPTION_READS_PER_CARD = 2;

    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Seed a root with a population of a given size and the same shape whatever the size.
     *
     * Every root gets: one unlisted course nobody is related to, one hidden
     * course, one course the viewer is enrolled in, one favourite, the model
     * field set on every course, and an image on the first three.
     *
     * @param int $courses How many courses.
     * @param \stdClass $viewer The viewer.
     * @return int The root category id.
     */
    private function seed(int $courses, \stdClass $viewer): int {
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $categories = [];
        for ($c = 0; $c < max(1, min(5, intdiv($courses, 40))); $c++) {
            $categories[] = $generator->create_category(['parent' => $root->id]);
        }
        $handler = course_handler::create();
        $ids = [];
        for ($i = 0; $i < $courses; $i++) {
            $course = $generator->create_course(['category' => $categories[$i % count($categories)]->id]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => 1 + ($i % 2),
                'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                    'text' => "<p>Programme {$i}</p>", 'format' => FORMAT_HTML,
                ],
            ]);
            $ids[] = (int) $course->id;
            if ($i < 3) {
                get_file_storage()->create_file_from_string([
                    'contextid' => \core\context\course::instance((int) $course->id)->id,
                    'component' => 'course',
                    'filearea' => 'overviewfiles',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => 'card.png',
                ], base64_decode(self::PNG));
            }
        }
        $unlisted = $generator->create_course(['category' => $root->id]);
        $this->set_course_state((int) $unlisted->id, \local_unlistedcourses\discoverability::STATE_UNLISTED);
        $generator->create_course(['category' => $root->id, 'visible' => 0]);
        $generator->enrol_user($viewer->id, $ids[0]);
        \core_favourites\service_factory::get_service_for_user_context(\core\context\user::instance($viewer->id))
            ->create_favourite('core_course', 'courses', $ids[1], \core\context\course::instance($ids[1]));
        return (int) $root->id;
    }

    /**
     * One first render: the decision layer and the four per-page layers.
     *
     * The flat organisation, because what is metered here is the cost of a page
     * of cards and {@see seed()} spreads its courses across the root's children:
     * the grouped page lists only the root's direct courses, so every slope
     * below would be a slope over an empty grid - zero against zero, and
     * measuring nothing. The grouped page's own extra cost is metered in
     * {@see test_the_accordion_costs_one_statement_in_the_sql_cut_mode_and_none_in_the_php_cut}.
     *
     * @param int $rootid The root.
     * @param int|null $pagesize The page size, null for the setting.
     * @return page_result
     */
    private function render(int $rootid, ?int $pagesize = null): page_result {
        $result = population::resolve(new criteria($rootid, [
            'pagesize' => $pagesize,
            'sort' => criteria::SORT_NAME,
        ]));
        cards::records($result->ids());
        cards::values($result->rows);
        cards::images($result->rows);
        cards::contacts($result->rows);
        return $result;
    }

    /**
     * Export every card of one resolved page, the way the listing does: four bulk reads, then no read.
     *
     * @param int $rootid The category.
     * @param int $pagesize The page size.
     * @return \stdClass[] The exported card contexts, in display order.
     */
    private function export_cards(int $rootid, int $pagesize): array {
        global $PAGE;

        $result = population::resolve(new criteria($rootid, ['pagesize' => $pagesize]));
        $rows = $result->rows;
        $ids = $result->ids();
        $courses = cards::records($ids);
        $values = cards::values($rows);
        $images = cards::images($rows);
        $contacts = cards::contacts($rows);
        $renderer = $PAGE->get_renderer('core');
        $cards = [];
        foreach ($ids as $courseid) {
            $card = new category_card(
                $courses[$courseid],
                $rows[$courseid],
                $values[$courseid],
                $images[$courseid] ?? null,
                $contacts[$courseid] ?? [],
                null,
                true
            );
            $cards[] = $card->export_for_template($renderer);
        }
        return $cards;
    }

    /**
     * Record one measurement in this run's JSON file of numbers.
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

    /**
     * The common shape of every budget test: settings, a viewer, the allowlist.
     *
     * @return \stdClass The viewer.
     */
    private function prepare(): \stdClass {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $CFG->coursecontact = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        return self::getDataGenerator()->create_user();
    }

    /**
     * Statement slope zero in the population: two courses cost what two hundred cost.
     */
    public function test_a_first_render_costs_the_same_statements_for_two_courses_and_for_two_hundred(): void {
        $viewer = $this->prepare();
        $small = $this->seed(2, $viewer);
        $large = $this->seed(200, $viewer);
        $this->become($viewer);
        $this->render($small);
        $this->render($large);

        $smallreads = $this->reads(fn() => $this->render($small));
        $largereads = $this->reads(function () use ($large) {
            $result = $this->render($large);
            $this->assertSame(200, $result->total, 'Two hundred courses; not the unlisted one, not the hidden one.');
        });
        $this->record('slope in N', ['reads at N=2' => $smallreads, 'reads at N=200' => $largereads]);
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads at N=2 against {$largereads} at N=200.");
        $this->assertLessThanOrEqual(self::WARM_CEILING, $largereads);
    }

    /**
     * Statement slope zero in the page size: a page of four costs what a page of forty-eight costs.
     */
    public function test_a_first_render_costs_the_same_statements_for_a_page_of_four_and_of_forty_eight(): void {
        $viewer = $this->prepare();
        $root = $this->seed(100, $viewer);
        $this->become($viewer);
        $this->render($root, 48);

        $four = $this->reads(function () use ($root): void {
            $this->assertCount(4, $this->render($root, 4)->rows, 'There are four cards to pay for.');
        });
        $fortyeight = $this->reads(function () use ($root): void {
            $this->assertCount(48, $this->render($root, 48)->rows, 'And forty-eight here.');
        });
        $this->record('slope in P', ['reads at P=4' => $four, 'reads at P=48' => $fortyeight]);
        $this->assertSame($four, $fortyeight, "{$four} reads at P=4 against {$fortyeight} at P=48.");
    }

    /**
     * The question "may this viewer enter the course" costs no statement, however many cards ask it.
     *
     * A card sends a viewer who may already walk into the course to the course
     * itself rather than to its enrolment surface, and the question behind that
     * is core's `is_viewing()` - `moodle/course:view` at the course context, once
     * per card. Asked of a context the request has never instantiated, a
     * capability question costs a statement, and a page of ninety-six cards
     * would pay ninety-six of them for a link. It is free here because the
     * population preloads the page's course contexts out of the columns its own
     * subtree statement already carried, and the viewer's access data is loaded
     * once for the request.
     *
     * So the instrument is the slope: a page of four cards and a page of
     * twenty-four, exported through the real builder, must cost the same reads.
     * Each measured closure asserts that every card really resolved to the
     * course: a viewer who may enter nothing would take the early branch on
     * every card and measure a zero slope over a question never asked.
     */
    public function test_asking_whether_the_viewer_may_enter_costs_no_statement_per_card(): void {
        $viewer = $this->prepare();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $context = \core\context\coursecat::instance((int) $category->id);
        $roleid = $generator->create_role();
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $viewer->id, $context->id);
        for ($i = 0; $i < 24; $i++) {
            $generator->create_course(['category' => $category->id]);
        }

        $this->become($viewer);
        $categoryid = (int) $category->id;
        $entered = function (int $pagesize) use ($categoryid): void {
            $cards = $this->export_cards($categoryid, $pagesize);
            $this->assertCount($pagesize, $cards, 'The page really holds the cards being measured.');
            foreach ($cards as $card) {
                $this->assertStringContainsString(
                    '/course/view.php?id=' . $card->id,
                    (string) $card->url,
                    'This viewer may enter every course, so every card asked the question and said yes.'
                );
            }
        };
        // Warm what a second request would find warm: the subtree cache and the role definitions.
        $entered(4);
        $entered(24);

        $four = $this->reads(fn() => $entered(4));
        $twentyfour = $this->reads(fn() => $entered(24));
        $this->record('cards exported', ['reads at P=4' => $four, 'reads at P=24' => $twentyfour]);
        $this->assertSame(
            $four,
            $twentyfour,
            "{$four} reads for four cards against {$twentyfour} for twenty-four: a card is reading per card."
        );
    }

    /**
     * The crests on a card cost no statement of their own: the files were already in hand.
     *
     * What the card does with a crest is name a URL and read a file name, both off the landing page
     * values the listing reads in bulk for every row anyway - so the card's own export must
     * stay flat in the number of cards, crests or no crests. It is measured on the export alone:
     * resolving each badge file is customfield_picture's own per-row cost inside
     * landingfields::values_many(), part of the bulk reads, which sit outside the meter and are
     * recorded beside the slope rather than asserted.
     *
     * Every card measured must really carry its crest, or a fixture that quietly lost its badges
     * would measure a slope of zero over nothing.
     */
    public function test_the_crests_cost_no_statement_per_card(): void {
        global $PAGE;

        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $viewer = $this->prepare();
        $generator = self::getDataGenerator();
        /** @var \core_customfield_generator $customfields */
        $customfields = $generator->get_plugin_generator('core_customfield');
        $category = $generator->create_category();
        $handler = course_handler::create();
        // The picture field's own data controller prepares a draft area, which needs a user.
        $this->setAdminUser();
        for ($i = 0; $i < 24; $i++) {
            $course = $generator->create_course(['category' => $category->id]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            ]);
            $data = $customfields->add_instance_data(
                landingfields::get_field(landingfields::SHORTNAME_CREST1),
                (int) $course->id,
                1
            );
            get_file_storage()->create_file_from_string([
                'contextid' => \core\context\course::instance((int) $course->id)->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => 'crest-alpha.png',
            ], base64_decode(self::PNG));
        }

        $this->become($viewer);
        $categoryid = (int) $category->id;
        $renderer = $PAGE->get_renderer('core');

        // The four bulk reads, then the exports alone - which is what the meter wraps.
        $prepared = function (int $pagesize) use ($categoryid): array {
            $result = population::resolve(new criteria($categoryid, ['pagesize' => $pagesize]));
            $rows = $result->rows;
            return [
                $result->ids(),
                $rows,
                cards::records($result->ids()),
                cards::values($rows),
                cards::images($rows),
                cards::contacts($rows),
            ];
        };
        $exportall = function (array $prepared) use ($renderer): void {
            [$ids, $rows, $courses, $values, $images, $contacts] = $prepared;
            /* What a real request still holds at this point and the meter does not: the page's
               course contexts, which the three bulk readers each rebuild from the preload
               columns the population's own statement carried. Rebuilding them costs nothing and
               is what the listing does before it builds a single card; without this line the
               meter would charge each card the context statement the request had already
               paid, and read it as a per-card cost of the crests. */
            population::page_contexts($rows);
            foreach ($ids as $courseid) {
                $card = new category_card(
                    $courses[$courseid],
                    $rows[$courseid],
                    $values[$courseid],
                    $images[$courseid] ?? null,
                    $contacts[$courseid] ?? [],
                    null,
                    true
                );
                $context = $card->export_for_template($renderer);
                $this->assertTrue($context->hasbadges, 'Every card measured really carries its crest.');
            }
        };

        // Warm what a second request would find warm, then meter the exports at two sizes.
        $exportall($prepared(4));
        $exportall($prepared(24));

        $four = $prepared(4);
        $twentyfour = $prepared(24);
        $fourreads = $this->reads(fn() => $exportall($four));
        $twentyfourreads = $this->reads(fn() => $exportall($twentyfour));

        $this->assertSame(
            $fourreads,
            $twentyfourreads,
            "{$fourreads} reads to export four cards against {$twentyfourreads} for twenty-four: "
                . 'a crest is costing a statement per card.'
        );

        /* And the cost that is per row - resolving the badge files inside values_many() - recorded
           rather than asserted, because it belongs to customfield_picture and not to the card. */
        $bulkfour = $this->reads(fn() => $prepared(4));
        $bulktwentyfour = $this->reads(fn() => $prepared(24));
        $this->record('cards with crests exported', [
            'export reads at P=4' => $fourreads,
            'export reads at P=24' => $twentyfourreads,
            'bulk reads at P=4' => $bulkfour,
            'bulk reads at P=24' => $bulktwentyfour,
        ]);
    }

    /**
     * The route lines and the prerequisites cost no statement per card: two layers, read once for the page.
     *
     * Every other card of the page is a suspended enrolment beside an open self instance, whose route
     * local_unlistedcourses' batch reports, and every other one is conditional on a course of its own, whose name
     * and context arrive in one statement. The page is exported through the listing's own export_cards(), so the
     * two layers are metered where they really run, at four cards and at twenty-four; each measured closure asserts
     * that both kinds of card are on the page, or a fixture that lost them would measure a slope of zero over nothing.
     */
    public function test_the_routes_and_the_prerequisites_cost_no_statement_per_card(): void {
        global $PAGE;

        $viewer = $this->prepare();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $elsewhere = $generator->create_category();
        for ($i = 0; $i < 24; $i++) {
            $course = $generator->create_course(['category' => $category->id, 'fullname' => sprintf('Curso %02d', $i)]);
            if ($i % 2 === 0) {
                $this->enable_instance((int) $course->id, 'self', ['customint6' => 1]);
                $generator->enrol_user($viewer->id, $course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
            } else {
                $prerequisite = $generator->create_course(['category' => $elsewhere->id, 'fullname' => "Base {$i}"]);
                $this->completed_instance($course, (int) $prerequisite->id);
                $generator->enrol_user($viewer->id, $prerequisite->id);
            }
        }

        $this->become($viewer);
        $categoryid = (int) $category->id;
        $renderer = $PAGE->get_renderer('core');
        $exported = function (int $pagesize) use ($categoryid, $renderer): void {
            $result = population::resolve(new criteria($categoryid, ['pagesize' => $pagesize, 'sort' => criteria::SORT_NAME]));
            $cards = category_listing::export_cards($result, $categoryid, false, $renderer);
            $this->assertCount($pagesize, $cards, 'The page really holds the cards being measured.');
            $routes = array_filter($cards, static fn(\stdClass $card): bool => $card->hasroute);
            $conditional = array_filter($cards, static fn(\stdClass $card): bool => $card->state === classifier::STATE_CONDITIONAL);
            $this->assertCount(intdiv($pagesize, 2), $routes, 'Half the cards print a route line.');
            $this->assertCount(intdiv($pagesize, 2), $conditional, 'And half name a prerequisite.');
        };
        // Warm what a second request would find warm.
        $exported(4);
        $exported(24);

        $four = $this->reads(fn() => $exported(4));
        $twentyfour = $this->reads(fn() => $exported(24));
        $this->record('routes and prerequisites exported', ['reads at P=4' => $four, 'reads at P=24' => $twentyfour]);
        $this->assertSame(
            $four,
            $twentyfour,
            "{$four} reads for four cards against {$twentyfour} for twenty-four: a route or a prerequisite reads per card."
        );
    }

    /**
     * The opening dates cost no statement per card: the batch is asked once for the page, at four cards and at twenty-four.
     *
     * Every card has nothing on offer and a self instance whose window opens tomorrow, so every card joins the batch
     * and is dated, which is where the batch checks the self enrolment capability per instance against access data
     * loaded once per request. Each measured closure asserts that every card prints its date, or a fixture that lost
     * them would measure a slope of zero over nothing.
     */
    public function test_the_opening_dates_cost_no_statement_per_card(): void {
        global $PAGE;

        $viewer = $this->prepare();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $tomorrow = time() + DAYSECS;
        for ($i = 0; $i < 24; $i++) {
            $course = $generator->create_course(['category' => $category->id, 'fullname' => sprintf('Curso %02d', $i)]);
            $this->enable_instance((int) $course->id, 'self', ['customint6' => 1, 'enrolstartdate' => $tomorrow]);
        }

        $this->become($viewer);
        $categoryid = (int) $category->id;
        $renderer = $PAGE->get_renderer('core');
        $exported = function (int $pagesize) use ($categoryid, $renderer): void {
            $result = population::resolve(new criteria($categoryid, ['pagesize' => $pagesize, 'sort' => criteria::SORT_NAME]));
            $cards = category_listing::export_cards($result, $categoryid, false, $renderer);
            $this->assertCount($pagesize, $cards, 'The page really holds the cards being measured.');
            $dated = array_filter($cards, static fn(\stdClass $card): bool => $card->hasopens);
            $this->assertCount($pagesize, $dated, 'Every card prints its opening date.');
        };
        // Warm what a second request would find warm.
        $exported(4);
        $exported(24);

        $four = $this->reads(fn() => $exported(4));
        $twentyfour = $this->reads(fn() => $exported(24));
        $this->record('opening dates exported', ['reads at P=4' => $four, 'reads at P=24' => $twentyfour]);
        $this->assertSame(
            $four,
            $twentyfour,
            "{$four} reads for four cards against {$twentyfour} for twenty-four: an opening date reads per card."
        );
    }

    /**
     * The two page-cutting modes.
     *
     * @return array
     */
    public static function modes_provider(): array {
        return ['state sort on (page cut in PHP)' => [true], 'state sort off (page cut in SQL)' => [false]];
    }

    /**
     * The cost bound: memory and time at two hundred courses stay under loose constants, in both modes.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_first_render_of_two_hundred_courses_stays_within_the_cost_bound(bool $statesort): void {
        global $DB;

        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $root = $this->seed(200, $viewer);
        $this->become($viewer);
        $this->render($root);

        self::forget();
        \core\context_helper::reset_caches();
        gc_collect_cycles();
        // The peak is monotonic within a process, so it is reset after the warm-up or the delta reads zero.
        memory_reset_peak_usage();
        $reads = (int) $DB->perf_get_reads();
        $memory = memory_get_usage();
        $start = microtime(true);
        $rendered = $this->render($root);
        $seconds = microtime(true) - $start;
        $delta = memory_get_peak_usage() - $memory;
        $reads = (int) $DB->perf_get_reads() - $reads;
        $label = $statesort ? 'cost at N=200 (state sort on)' : 'cost at N=200 (state sort off)';
        $this->record($label, ['reads' => $reads, 'ms' => round($seconds * 1000, 1), 'peak KB' => intdiv($delta, 1024)]);

        $this->assertSame(200, $rendered->total, 'Two hundred courses were really on the page being measured.');
        $this->assertLessThanOrEqual($statesort ? self::WARM_CEILING : self::WARM_CEILING_SQL, $reads);
        $this->assertLessThan(self::COST_MEMORY, $delta, 'Peak memory grew by ' . intdiv($delta, 1024) . ' KB.');
        $this->assertLessThan(self::COST_SECONDS, $seconds, "A first render took {$seconds} s.");
    }

    /**
     * The accordion costs one statement in the mode that materialises nothing, and none in the other.
     *
     * The accordion is the per-child tally the grouped page's posters are drawn
     * from. Its cost is stated as the difference between the grouped
     * organisation and a flat one over the same fixture, so the number is a
     * property of the accordion rather than of everything around it. The
     * PHP-cut mode holds the population already and rolls the page's own tally
     * up the stored paths; the SQL-cut mode holds no row and asks for one
     * grouped count. Neither may grow with the number of children: a panel is
     * a listing of its child's whole subtree, and the obvious wrong
     * implementation resolves each child here rather than leaving it to the
     * client.
     *
     * The assertion inside the meter keeps it honest: an accordion that came
     * back empty would cost nothing for the wrong reason.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_accordion_costs_one_statement_in_the_sql_cut_mode_and_none_in_the_php_cut(bool $statesort): void {
        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $children = [];
        for ($i = 0; $i < 6; $i++) {
            $child = $generator->create_category(['parent' => $root->id]);
            $children[] = $child;
            $generator->create_course(['category' => $child->id]);
            $generator->create_course(['category' => $generator->create_category(['parent' => $child->id])->id]);
        }
        $this->become($viewer);
        $rootid = (int) $root->id;

        /* Warm both shapes before either is metered. reads() resets the per-request caches a new
           request starts without, but the first resolve of a process also fills the ones a real
           second request would still hold - the category tree, the field definitions, the config -
           and whichever shape ran first would carry all of it, which can make the difference
           negative and the assertion below vacuous. */
        population::resolve(new criteria($rootid, ['sort' => criteria::SORT_NAME]));
        population::resolve(new criteria($rootid));

        $flat = $this->reads(function () use ($rootid): void {
            $result = population::resolve(new criteria($rootid, ['sort' => criteria::SORT_NAME]));
            $this->assertSame([], $result->accordion);
            $this->assertSame(12, $result->total);
        });
        $grouped = $this->reads(function () use ($rootid): void {
            $result = population::resolve(new criteria($rootid));
            $this->assertCount(6, $result->accordion, 'The accordion really was built.');
            $this->assertSame([2, 2, 2, 2, 2, 2], array_column($result->accordion, 'count'));
        });
        $this->record(
            $statesort ? 'accordion (state sort on)' : 'accordion (state sort off)',
            ['flat' => $flat, 'grouped' => $grouped]
        );

        $this->assertGreaterThan(0, $flat, 'Both numbers are real measurements, not a meter that never ran.');
        $allowed = $statesort ? 0 : 1;
        $this->assertLessThanOrEqual(
            $allowed,
            $grouped - $flat,
            "The accordion added " . ($grouped - $flat) . " statements over six children; at most {$allowed} is the budget."
        );
    }

    /**
     * The root's directory reads nothing per card of its own: three top-level categories cost what twelve cost.
     *
     * The root's directory is the one surface of the showcase with a read per
     * card in reach - a count, a description, a branding payload - so the
     * shape it could quietly take is one statement per top-level category,
     * which on a site with forty of them is forty round trips on the busiest
     * page there is. It does not: the counts are rolled up a population the
     * resolve already paid for, the descriptions are one statement for the
     * whole directory, and the branding comes from a cache that is
     * viewer-independent and shared between requests.
     *
     * The first half measures that, with the descriptions blanked so the
     * directory's own slope is what is on the meter. The second half puts the
     * descriptions back and bounds what they add: format_text() sets up the
     * text filters of the context it is given, and each category has its own,
     * so a card with a description costs core two reads - the same two the
     * hero pays once for the category it heads, and the same two core's own
     * coursecat_helper pays per category on the page this one replaces. The
     * bound is that measured 2, so a third read per card - a get_record, an
     * uncached branding payload - fails it.
     *
     * The assertion inside each measured closure keeps the test non-vacuous: a
     * directory that came back empty would have a slope of zero for the wrong
     * reason, and would not pass the count.
     *
     * @param bool $statesort The mode: the tally is rolled up in PHP in one and grouped in SQL in the other.
     */
    #[DataProvider('modes_provider')]
    public function test_the_root_directory_reads_nothing_per_card_of_its_own(bool $statesort): void {
        global $DB;

        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        for ($i = 0; $i < 3; $i++) {
            $generator->create_course(['category' => $generator->create_category()->id]);
        }
        // The generator gives every category a description; blanked, the meter reads the directory alone.
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');

        $this->become($viewer);
        // Warm what a second request would find warm: the subtree cache and one branding payload per card.
        $small = count(population::resolve(new criteria(0))->directory);
        $this->assertGreaterThanOrEqual(3, $small, 'Three top-level categories at least, so there is a slope to measure.');
        $smallreads = $this->reads(fn() => population::resolve(new criteria(0)));

        $this->setAdminUser();
        for ($i = 0; $i < 9; $i++) {
            $generator->create_course(['category' => $generator->create_category()->id]);
        }
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');
        $this->become($viewer);
        population::resolve(new criteria(0));
        $largereads = $this->reads(function () use ($small): void {
            $directory = population::resolve(new criteria(0))->directory;
            $this->assertCount($small + 9, $directory, 'Nine more cards, so the slope is measured over cards.');
        });

        $label = $statesort ? 'root directory slope (state sort on)' : 'root directory slope (state sort off)';
        $this->record($label, ['reads at ' . $small => $smallreads, 'reads at ' . ($small + 9) => $largereads]);
        $this->assertSame(
            $smallreads,
            $largereads,
            "{$smallreads} reads for {$small} cards against {$largereads} for " . ($small + 9) . '.'
        );

        // With the descriptions back, what a card adds is core's own text-filter setup and nothing more.
        $DB->execute("UPDATE {course_categories} SET description = ? WHERE id > 0", ['<p>A description.</p>']);
        $this->become($viewer);
        population::resolve(new criteria(0));
        $described = $this->reads(function () use ($small): void {
            $directory = population::resolve(new criteria(0))->directory;
            $this->assertNotSame('', $directory[0]['description'], 'The descriptions are back on the cards.');
            $this->assertCount($small + 9, $directory);
        });
        $percard = ($described - $largereads) / ($small + 9);
        $this->record($label . ' with descriptions', ['reads' => $described, 'per card' => $percard]);
        $this->assertLessThanOrEqual(
            self::DESCRIPTION_READS_PER_CARD,
            $percard,
            "A described card costs {$percard} reads: more than core's own text-filter setup."
        );
    }

    /**
     * A search at the root costs what the root costs at rest, and nothing per poster.
     *
     * Under a term the root takes a tally of the matches and rolls it up to its posters. In the PHP
     * cut the population is already in hand, so the tally is free; in the SQL cut it is one grouped
     * statement over the predicate, which replaces the COUNT the root at rest takes (the total is the
     * tally's sum), and the two exception lists read the matches in their existing statements.
     * So the search costs no statement over the rest, in either mode - and the roll-up walks stored
     * paths, so nine more areas cost nothing either.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_search_at_the_root_costs_what_the_root_costs_at_rest(bool $statesort): void {
        global $DB;

        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        for ($i = 0; $i < 3; $i++) {
            $generator->create_course(['category' => $generator->create_category()->id, 'fullname' => 'Match ' . $i]);
        }
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');
        $search = new criteria(0, ['search' => 'Match']);

        $this->become($viewer);
        population::resolve(new criteria(0));
        population::resolve($search);
        $rest = $this->reads(fn() => population::resolve(new criteria(0)));
        $small = $this->reads(function () use ($search): void {
            $result = population::resolve($search);
            $this->assertSame(3, $result->total, 'The three matches, so the tally has something to count.');
            $this->assertSame(3, array_sum(array_column($result->accordion, 'count')), 'On the posters.');
        });
        $this->assertLessThanOrEqual($rest, $small, "A search at the root reads {$small} against {$rest} at rest.");

        $this->setAdminUser();
        for ($i = 0; $i < 9; $i++) {
            $generator->create_course(['category' => $generator->create_category()->id, 'fullname' => 'Match more ' . $i]);
        }
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');
        $this->become($viewer);
        population::resolve($search);
        $large = $this->reads(function () use ($search): void {
            $this->assertSame(12, population::resolve($search)->total, 'Nine more areas, each with a match.');
        });
        $label = $statesort ? 'root search (state sort on)' : 'root search (state sort off)';
        $this->record($label, ['rest' => $rest, 'search, few areas' => $small, 'search, nine more' => $large]);
        $this->assertSame($small, $large, "{$small} reads against {$large} with nine more areas.");
    }

    /**
     * A cover and a video on every card add no statement to the directory at all.
     *
     * They come out of the cached branding payload the directory already
     * reads, so the two areas cost two more get_area_files() on a cache miss
     * and nothing on the page that follows it. The slope is measured over
     * cards that all carry both, so a per-card read would show up as a slope
     * of one or two rather than as a constant nobody would notice.
     *
     * The assertions inside the metered closure are what keep it non-vacuous:
     * a directory that came back without covers, or without films, would have
     * a slope of zero for the wrong reason.
     */
    public function test_a_cover_and_a_video_cost_no_statement_per_card(): void {
        global $DB;

        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, '1', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $film = (string) file_get_contents(__DIR__ . '/../../fixtures/cover.mp4');
        $filmed = function (int $howmany) use ($generator, $film): void {
            for ($i = 0; $i < $howmany; $i++) {
                $category = $generator->create_category();
                $generator->create_course(['category' => $category->id]);
                $contextid = \core\context\coursecat::instance((int) $category->id)->id;
                foreach (['categorycover' => base64_decode(self::PNG), 'categorycovervideo' => $film] as $area => $bytes) {
                    get_file_storage()->create_file_from_string([
                        'contextid' => $contextid,
                        'component' => 'theme_dimensions',
                        'filearea' => $area,
                        'itemid' => 0,
                        'filepath' => '/',
                        'filename' => $area === 'categorycover' ? 'capa.png' : 'capa.mp4',
                    ], $bytes);
                }
            }
        };
        $filmed(3);
        // The generator gives every category a description; blanked, the meter reads the cards alone.
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');

        $this->become($viewer);
        // Warm what a second request would find warm: one branding payload per card.
        $small = count(population::resolve(new criteria(0))->directory);
        $this->assertGreaterThanOrEqual(3, $small, 'Three filmed categories at least, so there is a slope to measure.');
        $smallreads = $this->reads(fn() => population::resolve(new criteria(0)));

        $this->setAdminUser();
        $filmed(9);
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');
        $this->become($viewer);
        population::resolve(new criteria(0));
        $largereads = $this->reads(function () use ($small): void {
            $directory = population::resolve(new criteria(0))->directory;
            $this->assertCount($small + 9, $directory, 'Nine more cards, so the slope is measured over cards.');
            $withfilm = array_filter($directory, static fn(array $entry): bool => $entry['hasvideo']);
            $this->assertCount(12, $withfilm, 'And every one of them really carries a film.');
            $covers = array_filter($directory, static fn(array $entry): bool => $entry['hasimage']);
            $this->assertCount(12, $covers, 'And a still of its own.');
        });

        $this->record('root directory slope with covers and films', [
            'reads at ' . $small => $smallreads,
            'reads at ' . ($small + 9) => $largereads,
        ]);
        $this->assertSame(
            $smallreads,
            $largereads,
            "{$smallreads} reads for {$small} filmed cards against {$largereads} for " . ($small + 9) . '.'
        );
    }

    /**
     * The root builds no listing, and this is the half a statement count cannot see on its own.
     *
     * The root's scope is the whole site. Were it to run the listing pipeline,
     * the classifier would load every enabled enrolment instance of every
     * course in scope, the facet map would select every custom-field value of
     * every course in scope, and the sort, the slice and the subcategory tree
     * would run over all of it - each of them one statement, so the slope
     * tests stay green while the rows those statements return are the entire
     * site.
     *
     * A read count is therefore the wrong instrument by itself, and the
     * companion assertions live in population_test (no rows, no facets, no
     * tree, counts of one key). What a read count can say is the direction,
     * against the one control that makes it meaningful: the same courses,
     * resolved as a category page, which does build all of that. The root
     * must cost strictly less.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_root_costs_strictly_less_than_the_category_page_over_the_same_courses(bool $statesort): void {
        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $branch = $this->seed(60, $viewer);

        $this->become($viewer);
        // Warm what a second request would find warm: the subtree cache, the branding payloads.
        population::resolve(new criteria(0));
        population::resolve(new criteria($branch, ['sort' => criteria::SORT_NAME]));

        $rootreads = $this->reads(function (): void {
            $result = population::resolve(new criteria(0));
            $this->assertNotEmpty($result->directory, 'There is a directory to pay for.');
        });
        /* The flat organisation, for the reason render() gives: seed() puts every course in a
           child, so the grouped page of this fixture lists none and the comparison would be the
           root against an empty listing. */
        $pagereads = $this->reads(function () use ($branch): void {
            $result = population::resolve(new criteria($branch, ['sort' => criteria::SORT_NAME]));
            $this->assertNotEmpty($result->rows, 'There is a page of courses to pay for.');
        });

        $label = $statesort ? 'root against category page (state sort on)' : 'root against category page (state sort off)';
        $this->record($label, ['root reads' => $rootreads, 'category page reads' => $pagereads]);
        $this->assertLessThan(
            $pagereads,
            $rootreads,
            "The root cost {$rootreads} reads against the category page's {$pagereads}: it is building a listing again."
        );
    }

    /**
     * The posters cost one statement, nothing per poster of their own, and core's text filters per described one.
     *
     * The posters are the directory's visual over the accordion's entries: the descriptions are
     * one statement for the whole grid, and everything else - the colours, the still, the film,
     * the crests, the watermark - comes out of the branding payload the accordion's page already
     * warmed. So three posters and twelve must cost the same over the page without them, with the
     * descriptions blanked; and with them back, what a poster adds is core's text-filter setup of
     * its context, the two reads the root's directory pays.
     *
     * The assertions inside each metered closure keep it non-vacuous: a grid that came back
     * without posters would cost nothing for the wrong reason.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_posters_cost_one_statement_and_nothing_per_poster_of_their_own(bool $statesort): void {
        global $DB;

        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $rootid = (int) $root->id;
        $grow = function (int $children) use ($generator, $rootid): void {
            for ($i = 0; $i < $children; $i++) {
                $generator->create_course(['category' => $generator->create_category(['parent' => $rootid])->id]);
            }
        };
        $measure = function (int $expected) use ($rootid): array {
            population::resolve(new criteria($rootid));
            population::resolve(new criteria($rootid, ['withposters' => true]));
            $without = $this->reads(function () use ($rootid, $expected): void {
                $this->assertCount($expected, population::resolve(new criteria($rootid))->accordion);
            });
            $with = $this->reads(function () use ($rootid, $expected): void {
                $this->assertCount($expected, population::resolve(new criteria($rootid, ['withposters' => true]))->posters);
            });
            return [$without, $with];
        };

        $grow(3);
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');
        $this->become($viewer);
        [$plainsmall, $postersmall] = $measure(3);

        $this->setAdminUser();
        $grow(9);
        $DB->set_field_select('course_categories', 'description', '', 'id > 0');
        $this->become($viewer);
        [$plainlarge, $posterlarge] = $measure(12);

        $label = $statesort ? 'posters (state sort on)' : 'posters (state sort off)';
        $this->record($label, [
            'page at 3' => $plainsmall,
            'with posters at 3' => $postersmall,
            'page at 12' => $plainlarge,
            'with posters at 12' => $posterlarge,
        ]);
        $this->assertLessThanOrEqual(1, $postersmall - $plainsmall, 'The posters cost the descriptions\' one statement.');
        $this->assertSame(
            $postersmall - $plainsmall,
            $posterlarge - $plainlarge,
            'And nothing per poster: three and twelve cost the same over the page without them.'
        );

        $DB->execute("UPDATE {course_categories} SET description = ? WHERE id > 0", ['<p>A description.</p>']);
        $this->become($viewer);
        population::resolve(new criteria($rootid, ['withposters' => true]));
        $described = $this->reads(function () use ($rootid): void {
            $posters = population::resolve(new criteria($rootid, ['withposters' => true]))->posters;
            $this->assertNotSame('', $posters[0]['description'], 'The descriptions are back on the posters.');
        });
        $perposter = ($described - $posterlarge) / 12;
        $this->record($label . ' with descriptions', ['reads' => $described, 'per poster' => $perposter]);
        $this->assertLessThanOrEqual(
            self::DESCRIPTION_READS_PER_CARD,
            $perposter,
            "A described poster costs {$perposter} reads: more than core's own text-filter setup."
        );
    }

    /**
     * A page with an open panel costs the page plus ONE listing, whatever the area holds and however many posters there are.
     *
     * The server draws the open panel and no other: each of the others is the client's request,
     * on open. The shape this bounds is the obvious wrong one - resolving a panel per poster on
     * the server - whose cost grows with the posters; and the panel's listing, like every
     * listing here, is statement-flat in the courses it holds. So the difference an open panel
     * makes is measured over two posters and eight courses, then over eight posters and eighty,
     * and must be the same number.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_an_open_panel_costs_one_listing_whatever_the_page_and_the_area_hold(bool $statesort): void {
        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $rootid = (int) $root->id;
        $area = $generator->create_category(['parent' => $rootid]);
        $areaid = (int) $area->id;
        for ($i = 0; $i < 8; $i++) {
            $generator->create_course(['category' => $areaid]);
        }
        $generator->create_course(['category' => $generator->create_category(['parent' => $rootid])->id]);
        $measure = function (int $posters) use ($rootid, $areaid): int {
            population::resolve(new criteria($rootid, ['withposters' => true]));
            population::resolve(new criteria($rootid, ['withposters' => true, 'open' => $areaid]));
            $closed = $this->reads(function () use ($rootid, $posters): void {
                $result = population::resolve(new criteria($rootid, ['withposters' => true]));
                $this->assertCount($posters, $result->posters);
                $this->assertNull($result->panel);
            });
            $open = $this->reads(function () use ($rootid, $areaid, $posters): void {
                $result = population::resolve(new criteria($rootid, ['withposters' => true, 'open' => $areaid]));
                $this->assertCount($posters, $result->posters);
                $this->assertNotNull($result->panel, 'The panel really was drawn.');
                $this->assertCount(population::PANEL_SIZE, $result->panel->rows);
            });
            return $open - $closed;
        };

        $this->become($viewer);
        $small = $measure(2);

        $this->setAdminUser();
        for ($i = 0; $i < 72; $i++) {
            $generator->create_course(['category' => $areaid]);
        }
        for ($i = 0; $i < 6; $i++) {
            $generator->create_course(['category' => $generator->create_category(['parent' => $rootid])->id]);
        }
        $this->become($viewer);
        $large = $measure(8);

        $label = $statesort ? 'open panel (state sort on)' : 'open panel (state sort off)';
        $this->record($label, ['2 posters, 8 courses' => $small, '8 posters, 80 courses' => $large]);
        $this->assertGreaterThan(0, $small, 'The panel is a listing and costs one.');
        $this->assertSame($small, $large, "An open panel cost {$small} reads, then {$large}: it grew with the page.");
    }

    /**
     * A panel's strip costs no statement per subarea: two subareas cost what eight cost.
     *
     * The strip is built from rows the listing already holds and the cached branding payload; a
     * whole directory card per subarea would add its description, with the text filters, per
     * subarea. The count inside the meter keeps it non-vacuous.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_panels_strip_costs_no_statement_per_subarea(bool $statesort): void {
        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $area = $generator->create_category();
        $areaid = (int) $area->id;
        $grow = function (int $subareas) use ($generator, $areaid): void {
            for ($i = 0; $i < $subareas; $i++) {
                $generator->create_course(['category' => $generator->create_category(['parent' => $areaid])->id]);
            }
        };
        $measure = function (int $expected) use ($areaid): int {
            $request = fn() => new criteria($areaid, ['sort' => criteria::SORT_RECENT, 'withcategories' => true]);
            population::resolve($request());
            return $this->reads(function () use ($request, $expected): void {
                $this->assertCount($expected, population::resolve($request())->subareas);
            });
        };

        $grow(2);
        $this->become($viewer);
        $two = $measure(2);
        $this->setAdminUser();
        $grow(6);
        $this->become($viewer);
        $eight = $measure(8);

        $label = $statesort ? 'panel strip (state sort on)' : 'panel strip (state sort off)';
        $this->record($label, ['reads at 2' => $two, 'reads at 8' => $eight]);
        $this->assertSame($two, $eight, "{$two} reads for two subareas against {$eight} for eight.");
    }

    /**
     * A visitor's grouped page under a search costs one statement more than at rest, and a person's none.
     *
     * The one grouped statement over the anonymous predicate at rest is what keeps a visitor's
     * posters the posters at rest under a criterion; it is spent only there. The tree at rest is
     * built before the page's own, so the category states the narrowed tree reads are memoised and
     * the difference is that statement alone. The search matches a course sitting here and one in
     * a child, so both requests draw the same shape of page - a page slice, three posters - and
     * the assertions inside each metered closure keep it non-vacuous: the posters really are the
     * three at rest, one of them zeroed.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_visitors_grouped_page_under_a_search_costs_one_statement_more_than_at_rest(bool $statesort): void {
        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $rootid = (int) $root->id;
        $this->set_category_state($rootid, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);
        $public = function (int $categoryid, string $fullname) use ($generator): void {
            $course = $generator->create_course(['category' => $categoryid, 'fullname' => $fullname]);
            $this->set_course_state((int) $course->id, \local_unlistedcourses\discoverability::STATE_PUBLIC);
        };
        $public($rootid, 'Incendio direto');
        foreach (['Incendio florestal', 'Transito urbano', 'Salvamento'] as $fullname) {
            $child = $generator->create_category(['parent' => $rootid]);
            $this->set_category_state((int) $child->id, \local_unlistedcourses\category_discoverability::STATE_PUBLIC);
            $public((int) $child->id, $fullname);
        }
        $request = static fn(bool $anonymous, string $search): criteria => new criteria($rootid, array_filter([
            'anonymous' => $anonymous,
            'withposters' => true,
            'search' => $search,
        ]));
        $measure = function (bool $anonymous) use ($request): array {
            population::resolve($request($anonymous, ''));
            population::resolve($request($anonymous, 'Incendio'));
            $rest = $this->reads(function () use ($request, $anonymous): void {
                $result = population::resolve($request($anonymous, ''));
                $this->assertSame([1, 1, 1], array_column($result->posters, 'count'));
            });
            $searched = $this->reads(function () use ($request, $anonymous): void {
                $result = population::resolve($request($anonymous, 'Incendio'));
                $this->assertSame([1, 0, 0], array_column($result->posters, 'count'), 'The posters at rest, two of them zeroed.');
                $this->assertCount(1, $result->rows, 'And a page slice, as at rest.');
            });
            return [$rest, $searched];
        };

        $this->become(null);
        [$visitorrest, $visitorsearched] = $measure(true);
        $this->become($viewer);
        [$personrest, $personsearched] = $measure(false);

        $label = $statesort ? 'grouped page under a search (state sort on)' : 'grouped page under a search (state sort off)';
        $this->record($label, [
            'visitor at rest' => $visitorrest,
            'visitor under a search' => $visitorsearched,
            'person at rest' => $personrest,
            'person under a search' => $personsearched,
        ]);
        $this->assertGreaterThan(0, $visitorrest, 'A real measurement.');
        $this->assertSame(1, $visitorsearched - $visitorrest, 'The visitor pays the one statement of the posters at rest.');
        $this->assertSame(0, $personsearched - $personrest, 'A person pays nothing for it: their tree names every child.');
    }

    /**
     * A panel's request under a criterion costs nothing more in the PHP cut and one statement in the SQL cut, flat in the subareas.
     *
     * The strip under a criterion offers only the subareas holding a result, which asks for the
     * request's own tally: in hand in the PHP cut, one grouped statement in the SQL cut. Every
     * course matches the search, so the two requests draw the same rows and the same strip, and
     * two subareas must cost what eight cost under the criterion.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_panel_under_a_criterion_costs_its_strip_tally_alone(bool $statesort): void {
        $viewer = $this->prepare();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $area = $generator->create_category();
        $areaid = (int) $area->id;
        $generator->create_course(['category' => $areaid, 'fullname' => 'Incendio na area']);
        $grow = function (int $subareas) use ($generator, $areaid): void {
            for ($i = 0; $i < $subareas; $i++) {
                $sub = $generator->create_category(['parent' => $areaid]);
                $generator->create_course(['category' => $sub->id, 'fullname' => 'Incendio ' . $sub->id]);
            }
        };
        $request = static fn(string $search): criteria => new criteria($areaid, array_filter([
            'sort' => criteria::SORT_RECENT,
            'withcategories' => true,
            'search' => $search,
        ]));
        $measure = function (string $search, int $expected) use ($request): int {
            population::resolve($request($search));
            return $this->reads(function () use ($request, $search, $expected): void {
                $this->assertCount($expected, population::resolve($request($search))->subareas);
            });
        };

        $grow(2);
        $this->become($viewer);
        $rest = $measure('', 2);
        $searchedtwo = $measure('Incendio', 2);
        $this->setAdminUser();
        $grow(6);
        $this->become($viewer);
        $searchedeight = $measure('Incendio', 8);

        $label = $statesort ? 'panel under a criterion (state sort on)' : 'panel under a criterion (state sort off)';
        $this->record($label, ['at rest' => $rest, 'searched at 2' => $searchedtwo, 'searched at 8' => $searchedeight]);
        $this->assertSame(
            $statesort ? 0 : 1,
            $searchedtwo - $rest,
            'The strip\'s tally: free in the PHP cut, one statement in the SQL cut.'
        );
        $this->assertSame(
            $searchedtwo,
            $searchedeight,
            "{$searchedtwo} reads for two subareas against {$searchedeight} for eight."
        );
    }
}
