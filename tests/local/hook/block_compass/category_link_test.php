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
 * Theme Dimensions - Tests for the answer to block_compass's category_link hook
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use block_compass\hook\category_link as compass_hook;
use block_compass\local\category_link as dispatcher;
use core\hook\manager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for category_link::answer(), the theme's listener on block_compass's category link hook.
 *
 * block_compass is optional, so every test skips without the hook class. The hook is dispatched through a
 * hook manager that knows only this theme's own db/hooks.php, so what answers is the registration this
 * repository ships.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_link::class)]
final class category_link_test extends \advanced_testcase {
    /**
     * Skip without block_compass, then sign in a regular user and use the theme-only hook manager.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        if (!class_exists(compass_hook::class)) {
            $this->markTestSkipped('block_compass, with its category_link hook, is not installed.');
        }
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $manager = manager::phpunit_get_instance(['theme_dimensions' => $CFG->dirroot . '/theme/dimensions/db/hooks.php']);
        \core\di::set(manager::class, $manager);
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
     * Ask the hook about some categories, as block_compass does.
     *
     * @param int[] $categoryids The categories, 0 for the root.
     * @param int|null $userid The viewer, null for the current user.
     * @return array Category id => answer, as the hook's get_urls() gives it.
     */
    private function ask(array $categoryids, ?int $userid = null): array {
        global $USER;

        $hook = new compass_hook($categoryids, '2026-11-01', '2026-11-30', $userid ?? (int) $USER->id);
        \core\di::get(manager::class)->dispatch($hook);
        return $hook->get_urls();
    }

    /**
     * A category gets the showcase with exactly the open chip and the band.
     *
     * @return void
     */
    public function test_category_gets_the_filtered_showcase(): void {
        $category = self::getDataGenerator()->create_category();

        $urls = $this->ask([(int) $category->id]);

        $this->assertCount(1, $urls);
        $url = $urls[(int) $category->id];
        $this->assertInstanceOf(\core\url::class, $url);
        $this->assertSame((new \core\url('/course/index.php'))->get_path(), $url->get_path());
        $this->assertSame(
            ['categoryid' => (string) $category->id, 'chip' => 'open', 'from' => '2026-11-01', 'to' => '2026-11-30'],
            array_map('strval', $url->params())
        );
    }

    /**
     * The root gets the bare course index with the same filter.
     *
     * @return void
     */
    public function test_root_gets_the_root_showcase(): void {
        $urls = $this->ask([0]);

        $url = $urls[0];
        $this->assertSame((new \core\url('/course/index.php'))->get_path(), $url->get_path());
        $this->assertSame(
            ['chip' => 'open', 'from' => '2026-11-01', 'to' => '2026-11-30'],
            array_map('strval', $url->params())
        );
    }

    /**
     * The showcase being off answers nothing.
     *
     * @return void
     */
    public function test_showcase_off_answers_nothing(): void {
        $category = self::getDataGenerator()->create_category();
        set_config('categorypageenabled', 0, 'theme_dimensions');

        $this->assertSame([], $this->ask([(int) $category->id, 0]));
    }

    /**
     * The state sort being off answers nothing: the page would drop the chip.
     *
     * @return void
     */
    public function test_state_sort_off_answers_nothing(): void {
        $category = self::getDataGenerator()->create_category();
        set_config('categorystatesort', 0, 'theme_dimensions');

        $this->assertSame([], $this->ask([(int) $category->id, 0]));
    }

    /**
     * A visitor and the guest account answer nothing: their page draws no chips.
     *
     * @return void
     */
    public function test_visitor_and_guest_answer_nothing(): void {
        $this->assertSame([], $this->ask([0], 0));
        $this->assertSame([], $this->ask([0], (int) guest_user()->id));
    }

    /**
     * Whatever throws is reported and answered as nothing.
     *
     * @return void
     */
    public function test_a_throwing_path_answers_nothing(): void {
        $hook = new compass_hook([0], '2026-11-01', '2026-11-30', (int) $GLOBALS['USER']->id);

        category_link::answer($hook, static function (int $categoryid, array $params): \core\url {
            throw new \RuntimeException('boom');
        });

        $this->assertSame([], $hook->get_urls());
        $this->assertDebuggingCalled();
    }

    /**
     * The dispatcher keeps the theme's address: it passes the local-URL test and is not replaced by core's page.
     *
     * @return void
     */
    public function test_dispatcher_keeps_the_theme_url(): void {
        global $CFG;

        $category = self::getDataGenerator()->create_category();

        $urls = dispatcher::urls([(int) $category->id, 0], '2026-11-01', '2026-11-30');

        $this->assertSame(
            $CFG->wwwroot . '/course/index.php?categoryid=' . $category->id . '&chip=open&from=2026-11-01&to=2026-11-30',
            $urls[(int) $category->id]
        );
        $this->assertSame($CFG->wwwroot . '/course/index.php?chip=open&from=2026-11-01&to=2026-11-30', $urls[0]);
    }
}
