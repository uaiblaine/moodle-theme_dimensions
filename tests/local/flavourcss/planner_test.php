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
 * Theme Dimensions - Tests for the flavour stylesheet planner
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\flavourcss;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The planner builds every missing sheet of a flavour a page can be served, one child each, and no more.
 *
 * The child is replaced by a closure that records what it was asked, except in the one test that starts the
 * real CLI: that child only reads, because the builder answers "nothing to build" for a flavour that does not
 * exist, whatever site the CLI's config.php is.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(planner::class)]
final class planner_test extends \advanced_testcase {
    /** @var int The global theme revision of every test. */
    private const REV = 2000;

    /** @var int The theme's sub-revision of every test. */
    private const SUBREV = 9;

    /** @var string The throwaway right-to-left language pack's code. */
    private const RTL_LANG = 'xx';

    /**
     * A site on this theme, at known revisions, with no flavour sheet and no failure memo.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('theme', 'dimensions');
        set_config('themerev', self::REV);
        set_config('themerev', self::SUBREV, 'theme_dimensions');
        $this->clear_sheets();
    }

    /**
     * Leave no sheet and no language pack for the next test.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $CFG;

        $this->clear_sheets();
        fulldelete("{$CFG->langotherroot}/" . self::RTL_LANG);
        get_string_manager()->reset_caches(true);
        parent::tearDown();
    }

    /**
     * Only a flavour applied to categories or cohorts, or offered for choice, is built; in Boost Union's order.
     *
     * The controls: a flavour applied to nothing, and one whose category flag is on with no category, which
     * Boost Union applies nowhere.
     *
     * @return void
     */
    public function test_only_a_flavour_a_page_can_be_served_is_buildable(): void {
        $offered = $this->flavour(['sort' => 1]);
        $nothing = $this->flavour(['sort' => 2]);
        $bycohort = $this->flavour(['sort' => 3, 'applytocohorts' => 1, 'applytocohorts_ids' => '["4"]']);
        $flagonly = $this->flavour(['sort' => 4, 'applytocategories' => 1, 'applytocategories_ids' => '[]']);
        $bycategory = $this->flavour(['sort' => 5, 'applytocategories' => 1, 'applytocategories_ids' => '["7","8"]']);
        set_config('selectableflavours', (string) $offered, 'theme_dimensions');

        $this->assertSame([$offered, $bycohort, $bycategory], planner::buildable_flavours());
        $this->assertNotContains($nothing, planner::buildable_flavours());
        $this->assertNotContains($flagonly, planner::buildable_flavours());
    }

    /**
     * A sheet is missing until its file exists for the current revisions; one at an older sub-revision does not count.
     *
     * @return void
     */
    public function test_a_sheet_is_missing_until_its_file_exists_for_the_current_revisions(): void {
        $built = $this->applied_flavour(1);
        $stale = $this->applied_flavour(2);
        $this->write_sheet(self::REV, self::SUBREV, $built);
        $this->write_sheet(self::REV, self::SUBREV - 1, $stale);

        $this->assertSame([[$stale, 'ltr']], (new planner())->missing());
    }

    /**
     * Each missing sheet is built by a child of its own, in Boost Union's order, and the run reports each exit code.
     *
     * @return void
     */
    public function test_each_missing_sheet_is_built_by_a_child_of_its_own(): void {
        $first = $this->applied_flavour(1);
        $second = $this->applied_flavour(2);
        [$planner, $calls] = $this->planner(fn(): int => builder::BUILT);

        $results = $planner->run();

        $this->assertSame(["{$first}/ltr" => builder::BUILT, "{$second}/ltr" => builder::BUILT], $results);
        $this->assertSame([[$first, 'ltr'], [$second, 'ltr']], $calls->getArrayCopy());
    }

    /**
     * A sheet the restore answers for starts no child; one it cannot give (null) is built by a child, as before.
     *
     * @return void
     */
    public function test_a_restored_sheet_starts_no_child(): void {
        $restored = $this->applied_flavour(1);
        $built = $this->applied_flavour(2);
        $restorer = fn(int $flavourid): ?int => $flavourid === $restored ? builder::RESTORED : null;
        [$planner, $calls] = $this->planner(fn(): int => builder::BUILT, $restorer);

        $results = $planner->run();

        $this->assertSame(["{$restored}/ltr" => builder::RESTORED, "{$built}/ltr" => builder::BUILT], $results);
        $this->assertSame([[$built, 'ltr']], $calls->getArrayCopy());
        $this->assertSame([], $this->memo());
    }

    /**
     * With the real restore, a new cron machine writes its file from the shared entry and starts no child.
     *
     * The control is a second flavour with no entry, which the child is asked to build.
     *
     * @return void
     */
    public function test_a_new_cron_machine_restores_from_the_shared_cache(): void {
        $shared = $this->applied_flavour(1);
        $cold = $this->applied_flavour(2);
        \cache::make('core', 'postprocessedcss')->set(
            builder::cache_key($shared, 'ltr'),
            builder::stamp(self::REV, self::SUBREV) . ':root{--dim-ds-surface-page:#fff}'
        );
        [$planner, $calls] = $this->planner(fn(): int => builder::BUILT, null, true);

        $results = $planner->run();

        $this->assertSame(builder::RESTORED, $results["{$shared}/ltr"]);
        $this->assertFileExists(builder::sheet_path(self::REV, self::SUBREV, $shared, 'ltr'));
        $this->assertSame([[$cold, 'ltr']], $calls->getArrayCopy());
    }

    /**
     * A sheet whose build failed is not tried again until a revision moves; then it is, and success forgets it.
     *
     * The control is a busy lock (4), which is not a failure and is retried on the next run.
     *
     * @param int $code The failed child's exit code: the builder's exception, or a fatal error.
     * @return void
     */
    #[DataProvider('failures')]
    public function test_a_failed_sheet_waits_for_the_next_revision(int $code): void {
        $broken = $this->applied_flavour(1);
        $busy = $this->applied_flavour(2);
        $answers = [$broken => $code, $busy => builder::BUSY];
        [$planner, $calls] = $this->planner(function (int $flavourid) use (&$answers): int {
            return $answers[$flavourid];
        });

        $planner->run();
        $planner->run();

        $this->assertSame([[$broken, 'ltr'], [$busy, 'ltr'], [$busy, 'ltr']], $calls->getArrayCopy());
        $this->assertSame(["{$broken}/ltr" => self::REV . '_' . self::SUBREV], $this->memo());

        set_config('themerev', self::REV + 1);
        $answers[$broken] = builder::BUILT;
        $calls->exchangeArray([]);
        $planner->run();

        $this->assertContains([$broken, 'ltr'], $calls->getArrayCopy());
        $this->assertSame([], $this->memo());
    }

    /**
     * The exit codes of a failed child.
     *
     * @return array
     */
    public static function failures(): array {
        return [
            "the builder's exception" => [1],
            'a fatal error' => [255],
        ];
    }

    /**
     * A dry run lists the missing sheets and builds nothing.
     *
     * @return void
     */
    public function test_a_dry_run_builds_nothing(): void {
        $id = $this->applied_flavour(1);
        [$planner, $calls] = $this->planner(fn(): int => builder::BUILT);

        $this->assertSame(["{$id}/ltr" => null], $planner->run(true));
        $this->assertSame([], $calls->getArrayCopy());
        $this->assertSame([], $this->memo());
    }

    /**
     * Nothing is started where nothing would be served.
     *
     * @param string $case Which precondition fails.
     * @return void
     */
    #[DataProvider('skips')]
    public function test_nothing_is_started_where_nothing_would_be_served(string $case): void {
        global $CFG;

        $this->applied_flavour(1);
        if ($case === 'designer') {
            $CFG->themedesignermode = 1;
        } else {
            set_config('theme', 'boost_union');
        }
        [$planner, $calls] = $this->planner(fn(): int => builder::BUILT);

        $this->assertSame([], $planner->run());
        $this->assertSame([], $calls->getArrayCopy());
    }

    /**
     * The preconditions of a run.
     *
     * @return array
     */
    public static function skips(): array {
        return [
            'designer mode' => ['designer'],
            'another site theme' => ['theme'],
        ];
    }

    /**
     * A sheet is built right to left only while an installed language reads that way.
     *
     * @return void
     */
    public function test_right_to_left_is_built_only_while_a_language_needs_it(): void {
        global $CFG;

        $id = $this->applied_flavour(1);
        $this->assertSame(['ltr'], planner::directions());
        $this->assertSame([[$id, 'ltr']], (new planner())->missing());

        make_writable_directory("{$CFG->langotherroot}/" . self::RTL_LANG);
        file_put_contents(
            "{$CFG->langotherroot}/" . self::RTL_LANG . '/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Right to left';\n\$string['thisdirection'] = 'rtl';\n"
        );
        get_string_manager()->reset_caches(true);

        $this->assertSame(['ltr', 'rtl'], planner::directions());
        $this->assertSame([[$id, 'ltr'], [$id, 'rtl']], (new planner())->missing());
    }

    /**
     * The child is the CLI, run by this process's PHP, and its exit code is the builder's answer.
     *
     * @return void
     */
    public function test_a_child_runs_the_cli_and_answers_with_the_builders_code(): void {
        $this->assertSame(PHP_BINARY, planner::php_binary());
        $lines = [];

        $code = planner::run_child(2147483000, 'ltr', function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $this->assertSame(builder::SKIPPED, $code, implode("\n", $lines));
    }

    /**
     * A planner whose child records each call and answers through the given closure.
     *
     * The restore answers null unless one is given, so every missing sheet reaches the child.
     *
     * @param \Closure $answer fn(int $flavourid, string $direction): int.
     * @param \Closure|null $restorer The restore; null for one that never restores.
     * @param bool $realrestore Use the builder's own restore instead.
     * @return array The planner, and the ArrayObject the calls are recorded in.
     */
    private function planner(\Closure $answer, ?\Closure $restorer = null, bool $realrestore = false): array {
        $calls = new \ArrayObject();
        $runner = function (int $flavourid, string $direction) use ($answer, $calls): int {
            $calls[] = [$flavourid, $direction];
            return $answer($flavourid, $direction);
        };

        $quiet = function (string $line): void {
            // The tests read the results and the calls, not the log.
        };

        if (!$realrestore) {
            $restorer ??= fn(): ?int => null;
        }

        return [new planner($runner, $quiet, $restorer), $calls];
    }

    /**
     * A Boost Union flavour applied to one category.
     *
     * @param int $sort Its place in Boost Union's order.
     * @return int The id.
     */
    private function applied_flavour(int $sort): int {
        return $this->flavour(['sort' => $sort, 'applytocategories' => 1, 'applytocategories_ids' => '["1"]']);
    }

    /**
     * A Boost Union flavour.
     *
     * @param array $fields The fields to set; the rest apply to nothing.
     * @return int The id.
     */
    private function flavour(array $fields): int {
        global $DB;

        return (int) $DB->insert_record('theme_boost_union_flavours', (object) ($fields + [
            'title' => 'Probe',
            'sort' => 1,
            'applytocohorts' => 0,
            'applytocategories' => 0,
        ]));
    }

    /**
     * Put a sheet where styles.php reads it.
     *
     * @param int $rev The global revision.
     * @param int $subrev The sub-revision.
     * @param int $id The flavour.
     * @return void
     */
    private function write_sheet(int $rev, int $subrev, int $id): void {
        $path = builder::sheet_path($rev, $subrev, $id, 'ltr');
        make_writable_directory(dirname($path));
        file_put_contents($path, 'x');
    }

    /**
     * The failure memo as stored.
     *
     * @return array
     */
    private function memo(): array {
        return json_decode((string) get_config('theme_dimensions', planner::FAILED), true) ?? [];
    }

    /**
     * Remove every flavour sheet: the localcache files, the tempdir fallbacks and the MUC entries.
     *
     * @return void
     */
    private function clear_sheets(): void {
        global $CFG;
        require_once("{$CFG->libdir}/filelib.php");

        fulldelete("{$CFG->localcachedir}/theme");
        fulldelete("{$CFG->tempdir}/theme/dimensions");
        \cache::make('core', 'postprocessedcss')->purge();
    }
}
