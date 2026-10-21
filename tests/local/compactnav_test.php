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
 * Theme Dimensions - Tests for the navbar's compact mode on tablets
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Every clause of {@see compactnav}, each "no" beside the control that makes it a "yes".
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(compactnav::class)]
final class compactnav_test extends \advanced_testcase {
    /**
     * Nothing of a previous test's focus mode.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        focusmode::reset_caches();
    }

    /**
     * Put the user agent and the focus mode caches back: both are process-wide.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core_useragent::instance(true);
        focusmode::reset_caches();
        parent::tearDown();
    }

    /**
     * An activity's page of a new course, as require_login() leaves it, for a student of the course.
     *
     * @return array The page and the course.
     */
    private function activity_page(): array {
        $course = self::getDataGenerator()->create_course();
        $module = self::getDataGenerator()->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($module->cmid);
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        $page = new \moodle_page();
        $page->set_context(\core\context\module::instance($cm->id));
        $page->set_course($course);
        $page->set_cm($cm, $course);
        $page->set_url('/mod/page/view.php', ['id' => $cm->id]);
        $page->set_pagelayout('incourse');

        return [$page, $course];
    }

    /**
     * A more menu export whose React props carry the given items.
     *
     * @param array $items The items, each a partial node.
     * @return array
     */
    private function moremenu(array $items): array {
        $nodes = [];
        foreach ($items as $index => $item) {
            $nodes[] = $item + ['key' => 'item' . $index, 'text' => 'Item ' . $index, 'forceintomoremenu' => false];
        }

        return ['reactprops' => json_encode(['items' => $nodes, 'morelabel' => 'More'])];
    }

    /**
     * The feature ships on, and only an explicit '0' switches it off.
     *
     * @return void
     */
    public function test_only_an_explicit_zero_switches_it_off(): void {
        [$page] = $this->activity_page();

        unset_config(compactnav::SETTING, compactnav::COMPONENT);
        $this->assertTrue(compactnav::enabled(), 'Never saved: on.');
        $this->assertTrue(compactnav::applies($page));

        set_config(compactnav::SETTING, '', compactnav::COMPONENT);
        $this->assertTrue(compactnav::applies($page), 'An empty value is not an explicit zero.');

        set_config(compactnav::SETTING, '0', compactnav::COMPONENT);
        $this->assertFalse(compactnav::enabled());
        $this->assertFalse(compactnav::applies($page));
        $this->assertFalse(compactnav::arms($page, $this->moremenu([[], [], [], []])));

        set_config(compactnav::SETTING, '1', compactnav::COMPONENT);
        $this->assertTrue(compactnav::applies($page));
    }

    /**
     * The Moodle app is left alone.
     *
     * @return void
     */
    public function test_the_moodle_app_is_left_alone(): void {
        [$page] = $this->activity_page();
        $this->assertTrue(compactnav::applies($page), 'Control: a browser.');

        \core_useragent::instance(true, 'Mozilla/5.0 (Linux; Android 10) MoodleMobile 4.5.0');

        $this->assertTrue(\core_useragent::is_moodle_app(), 'Precondition: the user agent is the app.');
        $this->assertFalse(compactnav::applies($page));
        $this->assertFalse(compactnav::arms($page, $this->moremenu([[], [], [], []])));
    }

    /**
     * Focus mode keeps its own bar: the navbar is not drawn there.
     *
     * @return void
     */
    public function test_focus_mode_keeps_its_own_bar(): void {
        [$page, $course] = $this->activity_page();
        $this->assertTrue(compactnav::applies($page), 'Control: the ordinary activity page.');

        set_config('focusmode', \theme_dimensions\local\login::SELECT_YES, focusmode::COMPONENT);
        focusmode::choose((int) $course->id, true);

        $this->assertTrue(focusmode::applies($page), 'Precondition: focus mode applies.');
        $this->assertFalse(compactnav::applies($page));
    }

    /**
     * A layout that draws no navbar gets nothing.
     *
     * @return void
     */
    public function test_a_layout_without_the_navbar_gets_nothing(): void {
        $page = new \moodle_page();
        $page->set_context(\core\context\system::instance());
        $page->set_url('/my/');
        $page->set_pagelayout('mydashboard');
        $this->assertTrue(compactnav::applies($page), 'Control: the dashboard draws the navbar.');

        foreach (['login', 'embedded', 'maintenance', 'popup'] as $layout) {
            $other = new \moodle_page();
            $other->set_context(\core\context\system::instance());
            $other->set_url('/my/');
            $other->set_pagelayout($layout);
            $this->assertFalse(compactnav::applies($other), "The {$layout} layout.");
        }
    }

    /**
     * The candidates are the top-level items the width can send into "More": no divider, nothing forced.
     *
     * @return void
     */
    public function test_the_candidates_leave_out_dividers_and_forced_items(): void {
        $this->assertSame(3, compactnav::candidates($this->moremenu([[], [], []])));
        $this->assertSame(2, compactnav::candidates($this->moremenu([[], [], ['forceintomoremenu' => true]])));
        $this->assertSame(2, compactnav::candidates($this->moremenu([[], ['divider' => true], []])));
        $this->assertSame(0, compactnav::candidates([]), 'No props.');
        $this->assertSame(0, compactnav::candidates(['reactprops' => '{not json']), 'Unreadable props.');
    }

    /**
     * The mode is armed only where the navigation can overflow by more than MAXHIDDEN items at all.
     *
     * @return void
     */
    public function test_it_is_armed_only_where_the_navigation_can_overflow_enough(): void {
        [$page] = $this->activity_page();
        $items = array_fill(0, compactnav::MAXHIDDEN, []);

        $this->assertFalse(compactnav::arms($page, $this->moremenu($items)), 'MAXHIDDEN items can never go past it.');

        $items[] = [];
        $this->assertTrue(compactnav::arms($page, $this->moremenu($items)), 'One more can.');

        $items[] = ['forceintomoremenu' => true];
        $items[0]['forceintomoremenu'] = true;
        $this->assertFalse(compactnav::arms($page, $this->moremenu($items)), 'Forced items are in "More" at every width.');
    }

    /**
     * The armed class is spliced into the body's class attribute, once, keeping what was there.
     *
     * @return void
     */
    public function test_the_armed_class_is_spliced_into_the_body(): void {
        $this->assertSame(
            'id="page-mod-page-view" class="path-mod limitedwidth ' . compactnav::BODY_ARMED . '"',
            compactnav::body_attributes('id="page-mod-page-view" class="path-mod limitedwidth"')
        );
        $this->assertSame(
            'id="x" class="' . compactnav::BODY_ARMED . '"',
            compactnav::body_attributes('id="x"'),
            'No class attribute yet.'
        );
        $this->assertSame(['maxhidden' => compactnav::MAXHIDDEN], compactnav::client_config());
    }
}
