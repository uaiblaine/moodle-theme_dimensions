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
 * Theme Dimensions - Tests for the answer to block_compass's category_badges hook
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use block_compass\hook\category_badges as compass_hook;
use core\hook\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for category_badges::answer(), the theme's listener on block_compass's category crest hook (ADR-019 of
 * block_compass).
 *
 * block_compass is optional, so every test skips without the hook class. The hook is dispatched through a hook
 * manager that knows only this theme's own db/hooks.php, so what answers is the registration this repository
 * ships.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_badges::class)]
final class category_badges_test extends \advanced_testcase {
    /** @var string A one-pixel PNG: the listener names a file, it never reads its bytes. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /**
     * Skip without block_compass, then sign in a regular user and use the theme-only hook manager.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        if (!class_exists(compass_hook::class)) {
            $this->markTestSkipped('block_compass, with its category_badges hook, is not installed.');
        }
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $manager = manager::phpunit_get_instance(['theme_dimensions' => $CFG->dirroot . '/theme/dimensions/db/hooks.php']);
        \core\di::set(manager::class, $manager);
        \core_cache\cache::make('theme_dimensions', 'categorybranding')->purge();
        \local_unlistedcourses\category_discoverability::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * Forget the hook manager a test put in the container.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * A category with a crest file in each of the given badge areas.
     *
     * @param string[] $areas Badge areas, from branding::AREA_BADGES.
     * @param array $record Extra category fields.
     * @return int The category id.
     */
    private function category_with_crests(array $areas, array $record = []): int {
        $category = self::getDataGenerator()->create_category($record);
        $context = \core\context\coursecat::instance((int) $category->id);
        foreach ($areas as $area) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'theme_dimensions',
                'filearea' => $area,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => "Escola-de_Governo-{$area}.png",
            ], base64_decode(self::PNG));
        }

        return (int) $category->id;
    }

    /**
     * Ask the hook about some categories, as block_compass does.
     *
     * @param int[] $categoryids The categories.
     * @return array Category id => list of badges, as the hook's get_badges() gives them.
     */
    private function ask(array $categoryids): array {
        $hook = new compass_hook($categoryids);
        \core\di::get(manager::class)->dispatch($hook);

        return $hook->get_badges();
    }

    /**
     * A category gets its first crest in area order, named through the theme's route, with the file name as text.
     *
     * The second area holds a crest too and is not the one answered; a category with none gets nothing.
     *
     * @return void
     */
    public function test_a_category_gets_its_first_crest(): void {
        $crested = $this->category_with_crests(['categorybadge2', 'categorybadge1']);
        $second = $this->category_with_crests(['categorybadge3']);
        $plain = $this->category_with_crests([]);

        $badges = $this->ask([$crested, $second, $plain]);

        // The answer is a map by category, in no promised order.
        $this->assertEqualsCanonicalizing([$crested, $second], array_keys($badges), 'the category with no crest gets nothing');
        $this->assertCount(1, $badges[$crested]);
        $this->assertStringContainsString('/theme_dimensions/categorybadge1/', $badges[$crested][0]['url']);
        $this->assertSame('Escola de Governo categorybadge1', $badges[$crested][0]['alt']);
        $this->assertStringContainsString('/categorybadge3/', $badges[$second][0]['url'], 'gaps close up');
    }

    /**
     * A hidden category gets no crest for a learner and gets one for an administrator; an entry cached while the
     * category was visible goes with the update that hides it. The control is a visible category.
     *
     * @return void
     */
    public function test_a_hidden_category_gets_no_crest_for_a_learner(): void {
        $hidden = $this->category_with_crests(['categorybadge1']);
        $visible = $this->category_with_crests(['categorybadge1']);
        $learner = self::getDataGenerator()->create_user();
        $this->setUser($learner);
        $this->assertArrayHasKey($hidden, $this->ask([$hidden]), 'visible first, and cached');

        \core_course_category::get($hidden, MUST_EXIST, true)->hide();

        $badges = $this->ask([$hidden, $visible]);
        $this->assertArrayNotHasKey($hidden, $badges, 'hidden: no crest for a learner');
        $this->assertArrayHasKey($visible, $badges, 'the control');

        $this->setAdminUser();
        $this->assertArrayHasKey($hidden, $this->ask([$hidden]), 'an administrator sees the hidden one');
    }

    /**
     * A category inside a parent that is then hidden gets no crest for a learner, though its entry was cached while
     * everything was visible: core hides the subtree, and the update drops the cached entries. The control is the
     * administrator, who still sees it.
     *
     * @return void
     */
    public function test_a_category_under_a_hidden_parent_gets_no_crest_for_a_learner(): void {
        $parent = self::getDataGenerator()->create_category();
        $child = $this->category_with_crests(['categorybadge1'], ['parent' => $parent->id]);
        $this->setUser(self::getDataGenerator()->create_user());
        $this->assertArrayHasKey($child, $this->ask([$child]), 'visible first, and cached');

        \core_course_category::get((int) $parent->id, MUST_EXIST, true)->hide();

        $this->assertArrayNotHasKey($child, $this->ask([$child]), 'under a hidden parent: no crest for a learner');
        $this->setAdminUser();
        $this->assertArrayHasKey($child, $this->ask([$child]), 'the control: an administrator still sees it');
    }

    /**
     * An unlisted category gets no crest for a reader the plugin withholds it from, though core shows it; an
     * administrator still gets it. The control is a listed category asked in the same call.
     *
     * @return void
     */
    public function test_an_unlisted_category_gets_no_crest_for_an_outsider(): void {
        $unlisted = $this->category_with_crests(['categorybadge1']);
        $listed = $this->category_with_crests(['categorybadge1']);
        $this->setAdminUser();
        \local_unlistedcourses\category_discoverability::set_state(
            $unlisted,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );
        \local_unlistedcourses\category_discoverability::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();

        $this->setUser(self::getDataGenerator()->create_user());
        $badges = $this->ask([$unlisted, $listed]);
        $this->assertArrayNotHasKey($unlisted, $badges, 'unlisted: no crest for an outsider');
        $this->assertArrayHasKey($listed, $badges, 'the control');

        $this->setAdminUser();
        $this->assertArrayHasKey($unlisted, $this->ask([$unlisted]), 'an administrator discovers it');
    }

    /**
     * Cold, the misses are read in one statement for every asked category; warm, nothing is read.
     *
     * Core is warmed first (the configuration, the reader's access data), as block_compass's budget tests do; then
     * the theme's cache is purged for the cold call, and each measured call starts with core's context cache reset,
     * as a new request would.
     *
     * @return void
     */
    public function test_one_statement_cold_and_none_warm(): void {
        global $DB;

        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->category_with_crests(['categorybadge1']);
        }
        $this->ask($ids);
        \core_cache\cache::make('theme_dimensions', 'categorybranding')->purge();
        \core\context_helper::reset_caches();

        $before = $DB->perf_get_reads();
        $cold = $this->ask($ids);
        $coldreads = $DB->perf_get_reads() - $before;
        \core\context_helper::reset_caches();
        $before = $DB->perf_get_reads();
        $warm = $this->ask($ids);
        $warmreads = $DB->perf_get_reads() - $before;

        $this->assertCount(5, $cold, 'the control: every category answered');
        $this->assertSame($cold, $warm);
        $this->assertSame(1, $coldreads, 'one statement for the five misses');
        $this->assertSame(0, $warmreads, 'warm: the cached entries and preloaded contexts read nothing');
    }

    /**
     * A source that throws costs the crests and tells the developer; the hook holds no answer.
     *
     * @return void
     */
    public function test_a_failure_answers_nothing_and_is_reported(): void {
        $id = $this->category_with_crests(['categorybadge1']);
        $hook = new compass_hook([$id]);

        category_badges::answer($hook, static function (): array {
            throw new \coding_exception('broken');
        });

        $this->assertDebuggingCalled();
        $this->assertSame([], $hook->get_badges());
    }
}
