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
 * Theme Dimensions - Tests for the flavour stylesheet builder
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
 * The builder stores a flavour's sheet where Boost Union's styles.php reads it, and never a wrong one.
 *
 * No test compiles a real theme: Boost Union keeps the first flavour record a process reads in a static
 * (docs/flavourcss/PLAN.md, section 3.3), and PHPUnit runs every test in one process, so the compiler and the
 * reader of that static are replaced. The real compile is checked end to end from the CLI (stage S2).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(builder::class)]
final class builder_test extends \advanced_testcase {
    /** @var string A compiled sheet carrying the theme's marker. */
    private const SHEET = ':root{--dim-ds-surface-page:#fff}';

    /** @var int The global theme revision of every test. */
    private const REV = 1000;

    /** @var int The theme's sub-revision of every test. */
    private const SUBREV = 7;

    /**
     * A site on this theme, at known revisions, with no flavour sheet anywhere.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('theme', 'dimensions');
        set_config('themerev', self::REV);
        set_config('themerev', self::SUBREV, 'theme_dimensions');
        $this->clear_stores();
    }

    /**
     * Leave no sheet for the next test: flavour ids restart with the sequences.
     *
     * @return void
     */
    protected function tearDown(): void {
        $this->clear_stores();
        parent::tearDown();
    }

    /**
     * A build stores the sheet in the three places, compiled with the flavour applied in the given direction.
     *
     * @param string $direction 'ltr' or 'rtl'.
     * @return void
     */
    #[DataProvider('directions')]
    public function test_a_build_fills_the_three_stores_with_the_flavour_applied(string $direction): void {
        $id = $this->flavour();
        $seen = [];
        $compiler = function (\core\output\theme_config $theme) use (&$seen): string {
            $seen[] = [$GLOBALS['themeboostunionappliedflavour'] ?? null, $theme->name, $theme->get_rtl_mode()];
            return self::SHEET;
        };

        $this->assertSame(builder::BUILT, $this->builder($compiler)->build($id, $direction));

        $this->assertSame([[$id, 'dimensions', $direction === 'rtl']], $seen);
        $this->assertArrayNotHasKey('themeboostunionappliedflavour', $GLOBALS);
        $this->assert_stored($id, $direction);
    }

    /**
     * The directions a sheet is built in.
     *
     * @return array
     */
    public static function directions(): array {
        return [
            'left to right' => ['ltr'],
            'right to left' => ['rtl'],
        ];
    }

    /**
     * The flavour global is gone even when the compile throws, so it cannot re-flavour what the process does next.
     *
     * @return void
     */
    public function test_the_flavour_global_is_unset_when_the_compile_throws(): void {
        $id = $this->flavour();
        $compiler = function (): string {
            throw new \RuntimeException('Sass error');
        };

        try {
            $this->builder($compiler)->build($id);
            $this->fail('The compile error was swallowed.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Sass error', $e->getMessage());
        }

        $this->assertArrayNotHasKey('themeboostunionappliedflavour', $GLOBALS);
        $this->assert_nothing_stored($id, self::REV, self::SUBREV);
    }

    /**
     * A sheet compiled while a reset moved a revision is discarded: the MUC key carries no revision.
     *
     * The control is the next build at the new revision, which stores.
     *
     * @param string|null $plugin The revision's plugin: null for the global one, theme_dimensions for the theme's.
     * @return void
     */
    #[DataProvider('revisions')]
    public function test_nothing_is_written_when_a_revision_moves_during_the_compile(?string $plugin): void {
        $id = $this->flavour();
        $moves = function () use ($plugin): string {
            set_config('themerev', ($plugin === null ? self::REV : self::SUBREV) + 1, $plugin);
            return self::SHEET;
        };

        $this->assertSame(builder::STALE, $this->builder($moves)->build($id));
        $this->assert_nothing_stored($id, self::REV, self::SUBREV);
        $this->assert_nothing_stored($id, self::REV + 1, self::SUBREV);
        $this->assert_nothing_stored($id, self::REV, self::SUBREV + 1);

        $this->assertSame(builder::BUILT, $this->builder()->build($id));
        $this->assertFileExists(builder::sheet_path(
            $plugin === null ? self::REV + 1 : self::REV,
            $plugin === null ? self::SUBREV : self::SUBREV + 1,
            $id,
            'ltr'
        ));
    }

    /**
     * A reset that lands while the sheet is being written is caught after the writes, and the writes are undone.
     *
     * The revisions answer as the database would to a reset that moved the revision after the compile: steady for
     * the two reads before it (before the lock, under it), moved at the third, after the writes. The control is a
     * second flavour whose revisions never move, which is stored.
     *
     * @return void
     */
    public function test_a_reset_landing_during_the_writes_is_undone(): void {
        $id = $this->flavour('Reset during the writes');
        $reads = 0;
        $moving = function () use (&$reads): array {
            $reads++;
            return [$reads <= 2 ? self::REV : self::REV + 1, self::SUBREV];
        };

        $this->assertSame(builder::STALE, $this->builder(null, null, null, $moving)->build($id));
        $this->assertSame(3, $reads);
        $this->assert_nothing_stored($id, self::REV, self::SUBREV);

        $steady = $this->flavour('No reset');
        $still = fn(): array => [self::REV, self::SUBREV];
        $this->assertSame(builder::BUILT, $this->builder(null, null, null, $still)->build($steady));
        $this->assert_stored($steady, 'ltr');
    }

    /**
     * A direction other than the two styles.php serves is refused before it reaches a cache key or a file name.
     *
     * @return void
     */
    public function test_an_unknown_direction_is_refused(): void {
        $id = $this->flavour();

        $this->expectException(\coding_exception::class);
        $this->builder(fn(): string => $this->fail('An unknown direction was compiled.'))->build($id, '../rtl');
    }

    /**
     * The two revisions a reset can move.
     *
     * @return array
     */
    public static function revisions(): array {
        return [
            'the global revision' => [null],
            'the theme sub-revision' => ['theme_dimensions'],
        ];
    }

    /**
     * A process whose Boost Union static holds another flavour compiles nothing; the same flavour passes.
     *
     * @return void
     */
    public function test_a_process_that_read_another_flavour_compiles_nothing(): void {
        $first = $this->flavour('First');
        $second = $this->flavour('Second');
        $builder = $this->builder(null, null, fn(): ?int => $first);

        $this->assertSame(builder::BUILT, $builder->build($first));
        try {
            $builder->build($second);
            $this->fail('A poisoned process compiled another flavour.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString("flavour {$first}; flavour {$second} needs its own process", $e->getMessage());
        }

        $this->assert_nothing_stored($second, self::REV, self::SUBREV);
    }

    /**
     * The reader finds the record Boost Union's static holds after a lookup.
     *
     * This is the one test that calls Boost Union's function: the record it leaves in the static stays there for
     * the rest of the PHPUnit process, which is why every other test hands the builder a reader of its own.
     *
     * @return void
     */
    public function test_the_reader_names_the_flavour_boost_unions_static_holds(): void {
        $id = $this->flavour();
        $before = builder::boost_union_static_flavourid();

        theme_boost_union_get_flavour_config_item_for_flavourid((string) $id, 'look_brandcolor');

        $this->assertSame($before ?? $id, builder::boost_union_static_flavourid());
    }

    /**
     * Without the theme's lock nothing is compiled or written, and the caller hears "busy".
     *
     * @return void
     */
    public function test_a_lock_held_elsewhere_answers_busy(): void {
        $id = $this->flavour();
        $compiles = 0;
        $compiler = function () use (&$compiles): string {
            $compiles++;
            return self::SHEET;
        };

        $this->assertSame(builder::BUSY, $this->builder($compiler, $this->lock_factory(false))->build($id));

        $this->assertSame(0, $compiles);
        $this->assert_nothing_stored($id, self::REV, self::SUBREV);
    }

    /**
     * A sheet already there is neither recompiled nor locked for.
     *
     * @return void
     */
    public function test_a_sheet_already_there_is_left_alone(): void {
        $id = $this->flavour();
        $this->write_sheet($id, 'existing');
        $factory = $this->lock_factory(true);
        $compiler = fn(): string => $this->fail('A present sheet was recompiled.');

        $this->assertSame(builder::PRESENT, $this->builder($compiler, $factory)->build($id));

        $this->assertSame(0, $factory->asked);
        $this->assertSame('existing', file_get_contents(builder::sheet_path(self::REV, self::SUBREV, $id, 'ltr')));
    }

    /**
     * A sheet a visitor's request built while the builder waited for the lock is not compiled a second time.
     *
     * @return void
     */
    public function test_a_sheet_built_while_waiting_for_the_lock_is_not_compiled_again(): void {
        $id = $this->flavour();
        $factory = $this->lock_factory(true, fn() => $this->write_sheet($id, 'built by a visitor'));
        $compiler = fn(): string => $this->fail('The sheet was compiled twice.');

        $this->assertSame(builder::PRESENT, $this->builder($compiler, $factory)->build($id));

        $this->assertSame(1, $factory->asked);
        $this->assertSame(1, $factory->released);
        $this->assertSame('built by a visitor', file_get_contents(builder::sheet_path(self::REV, self::SUBREV, $id, 'ltr')));
    }

    /**
     * A compile that fell back to the precompiled sheet, without the theme's rules, is not stored.
     *
     * @return void
     */
    public function test_a_sheet_without_the_themes_rules_is_not_stored(): void {
        $id = $this->flavour();
        $factory = $this->lock_factory(true);

        try {
            $this->builder(fn(): string => 'body{color:red}', $factory)->build($id);
            $this->fail('A fallback sheet was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('flavourcssfailed', $e->errorcode);
        }

        $this->assertSame(1, $factory->released);
        $this->assert_nothing_stored($id, self::REV, self::SUBREV);
    }

    /**
     * Nothing is built where nothing would be served.
     *
     * @param string $case Which precondition fails.
     * @return void
     */
    #[DataProvider('skips')]
    public function test_nothing_is_built_where_nothing_would_be_served(string $case): void {
        global $CFG;

        $id = $this->flavour();
        if ($case === 'designer') {
            $CFG->themedesignermode = 1;
        } else if ($case === 'theme') {
            set_config('theme', 'boost_union');
        } else {
            $id += 100;
        }
        $compiler = fn(): string => $this->fail('A sheet nobody would be served was compiled.');

        $this->assertSame(builder::SKIPPED, $this->builder($compiler)->build($id));
    }

    /**
     * The preconditions of a build.
     *
     * @return array
     */
    public static function skips(): array {
        return [
            'designer mode' => ['designer'],
            'another site theme' => ['theme'],
            'no such flavour' => ['missing'],
        ];
    }

    /**
     * The builder removes older revisions as Boost Union's styles.php does, and keeps the current ones.
     *
     * @return void
     */
    public function test_older_revisions_are_removed_and_current_ones_kept(): void {
        global $CFG;

        $id = $this->flavour('Built');
        $other = $this->flavour('Other');
        $dir = "{$CFG->localcachedir}/theme";
        $files = [
            'olderrev' => "{$dir}/" . (self::REV - 1) . "/dimensions/css/all_" . self::SUBREV . "_{$other}.css",
            'oldersubrev' => "{$dir}/" . self::REV . "/dimensions/css/all_" . (self::SUBREV - 1) . "_{$other}.css",
            'current' => "{$dir}/" . self::REV . "/dimensions/css/all_" . self::SUBREV . "_{$other}.css",
            'main' => "{$dir}/" . self::REV . "/dimensions/css/all_" . self::SUBREV . ".css",
        ];
        foreach ($files as $file) {
            make_writable_directory(dirname($file));
            file_put_contents($file, 'x');
        }

        $this->assertSame(builder::BUILT, $this->builder()->build($id));

        $this->assertFileDoesNotExist($files['olderrev']);
        $this->assertFileDoesNotExist($files['oldersubrev']);
        $this->assertFileExists($files['current']);
        $this->assertFileExists($files['main']);
    }

    /**
     * A builder with harmless collaborators: a marked sheet, the real lock and a static that holds nothing.
     *
     * @param \Closure|null $compiler The compiler.
     * @param \core\lock\lock_factory|null $lockfactory The lock factory.
     * @param \Closure|null $staticflavourid The reader of Boost Union's static.
     * @param \Closure|null $revisions The reader of the revisions; null for the database.
     * @return builder
     */
    private function builder(
        ?\Closure $compiler = null,
        ?\core\lock\lock_factory $lockfactory = null,
        ?\Closure $staticflavourid = null,
        ?\Closure $revisions = null
    ): builder {
        return new builder(
            $compiler ?? fn(): string => self::SHEET,
            $lockfactory,
            $staticflavourid ?? fn(): ?int => null,
            0,
            $revisions
        );
    }

    /**
     * A Boost Union flavour that applies nowhere.
     *
     * @param string $title The title.
     * @return int The id.
     */
    private function flavour(string $title = 'Probe'): int {
        global $DB;

        return (int) $DB->insert_record('theme_boost_union_flavours', (object) [
            'title' => $title,
            'sort' => 1,
            'applytocohorts' => 0,
            'applytocategories' => 0,
        ]);
    }

    /**
     * A lock factory that grants or refuses, counts, and can run something before it answers.
     *
     * @param bool $grant Whether get_lock() grants the lock.
     * @param \Closure|null $before Run inside get_lock(), before it answers.
     * @return \core\lock\lock_factory
     */
    private function lock_factory(bool $grant, ?\Closure $before = null): \core\lock\lock_factory {
        $factory = new class ('core_theme_get_css_content') implements \core\lock\lock_factory {
            /** @var bool Whether get_lock() grants the lock. */
            public bool $grant = true;

            /** @var \Closure|null Run inside get_lock(), before it answers. */
            public ?\Closure $before = null;

            /** @var int How many times a lock was asked for. */
            public int $asked = 0;

            /** @var int How many locks were released. */
            public int $released = 0;

            /**
             * The interface's constructor.
             *
             * @param string $type The lock type.
             */
            public function __construct($type) {
            }

            /**
             * Supported.
             *
             * @return bool
             */
            public function supports_timeout() {
                return true;
            }

            /**
             * Supported.
             *
             * @return bool
             */
            public function supports_auto_release() {
                return true;
            }

            /**
             * Available.
             *
             * @return bool
             */
            public function is_available() {
                return true;
            }

            /**
             * Grant or refuse, after running the hook.
             *
             * @param string $resource The resource.
             * @param int $timeout The timeout.
             * @param int $maxlifetime The lifetime.
             * @return \core\lock\lock|false
             */
            public function get_lock($resource, $timeout, $maxlifetime = 86400) {
                $this->asked++;
                if ($this->before) {
                    ($this->before)();
                }
                return $this->grant ? new \core\lock\lock($resource, $this) : false;
            }

            /**
             * Count the release.
             *
             * @param \core\lock\lock $lock The lock.
             * @return bool
             */
            public function release_lock(\core\lock\lock $lock) {
                $this->released++;
                return true;
            }
        };
        $factory->grant = $grant;
        $factory->before = $before;

        return $factory;
    }

    /**
     * Put a sheet for the current revisions where styles.php reads it.
     *
     * @param int $id The flavour.
     * @param string $content The sheet.
     * @return void
     */
    private function write_sheet(int $id, string $content): void {
        $path = builder::sheet_path(self::REV, self::SUBREV, $id, 'ltr');
        make_writable_directory(dirname($path));
        file_put_contents($path, $content);
    }

    /**
     * The sheet is in the MUC entry, the localcache file and the tempdir fallback.
     *
     * @param int $id The flavour.
     * @param string $direction 'ltr' or 'rtl'.
     * @return void
     */
    private function assert_stored(int $id, string $direction): void {
        global $CFG;

        $type = $direction === 'rtl' ? 'all-rtl' : 'all';
        $this->assertSame(self::SHEET, \cache::make('core', 'postprocessedcss')->get("dimensions_{$id}_{$direction}"));
        $this->assertSame(self::SHEET, file_get_contents(
            "{$CFG->localcachedir}/theme/" . self::REV . "/dimensions/css/{$type}_" . self::SUBREV . "_{$id}.css"
        ));
        $this->assertSame(self::SHEET, file_get_contents("{$CFG->tempdir}/theme/dimensions/{$type}_{$id}.css"));
    }

    /**
     * None of the three stores holds a sheet of this flavour.
     *
     * @param int $id The flavour.
     * @param int $rev The global revision to look under.
     * @param int $subrev The sub-revision to look under.
     * @return void
     */
    private function assert_nothing_stored(int $id, int $rev, int $subrev): void {
        global $CFG;

        $this->assertFalse(\cache::make('core', 'postprocessedcss')->get("dimensions_{$id}_ltr"));
        $this->assertFileDoesNotExist(builder::sheet_path($rev, $subrev, $id, 'ltr'));
        $this->assertFileDoesNotExist("{$CFG->tempdir}/theme/dimensions/all_{$id}.css");
    }

    /**
     * Empty the three stores.
     *
     * @return void
     */
    private function clear_stores(): void {
        global $CFG;
        require_once("{$CFG->libdir}/filelib.php");

        fulldelete("{$CFG->localcachedir}/theme");
        fulldelete("{$CFG->tempdir}/theme/dimensions");
        \cache::make('core', 'postprocessedcss')->purge();
    }
}
