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
 * Theme Dimensions - Tests for the answer to block_compass's course_kinds hook
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use block_compass\hook\course_kinds as compass_hook;
use core\hook\manager;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

/**
 * Tests for course_kinds::answer(), the theme's listener on block_compass's course kinds hook.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_kinds::class)]
final class course_kinds_test extends \advanced_testcase {
    /**
     * Skip without block_compass, then use the theme-only hook manager.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        if (!class_exists(compass_hook::class)) {
            $this->markTestSkipped('block_compass, with its course_kinds hook, is not installed.');
        }
        $this->resetAfterTest();
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
     * The listener names the model field and its three values with this theme's nouns, reading nothing.
     *
     * @return void
     */
    public function test_names_the_model_field_and_its_three_kinds(): void {
        global $DB;

        // Warm what the block's own requests have warm: the configuration and the theme's strings.
        \core\di::get(manager::class)->dispatch(new compass_hook());
        $hook = new compass_hook();
        $before = $DB->perf_get_reads();
        \core\di::get(manager::class)->dispatch($hook);

        $this->assertSame(0, $DB->perf_get_reads() - $before, 'the block asks on every response');
        $this->assertSame(landingfields::SHORTNAME_MODEL, $hook->get_field());
        $this->assertSame([
            landingfields::MODEL_COURSE => [
                'key' => 'course',
                'one' => get_string('compass_kind_course', 'theme_dimensions'),
                'many' => get_string('compass_kind_courses', 'theme_dimensions'),
            ],
            landingfields::MODEL_EVENT => [
                'key' => 'event',
                'one' => get_string('compass_kind_event', 'theme_dimensions'),
                'many' => get_string('compass_kind_events', 'theme_dimensions'),
            ],
            landingfields::MODEL_TRAIL => [
                'key' => 'trail',
                'one' => get_string('compass_kind_trail', 'theme_dimensions'),
                'many' => get_string('compass_kind_trails', 'theme_dimensions'),
            ],
        ], $hook->get_kinds());
        $this->assertSame([1, 2, 3], array_keys($hook->get_kinds()), 'the block keeps this order, course first');
        $this->assertSame(['trail', 'trails'], [
            get_string('compass_kind_trail', 'theme_dimensions'),
            get_string('compass_kind_trails', 'theme_dimensions'),
        ]);
        $this->assertArrayNotHasKey(0, $hook->get_kinds(), 'a course with no model stays unnamed');
        $this->assertArrayNotHasKey(4, $hook->get_kinds(), 'a value beyond the options stays unnamed');
    }

    /**
     * Through the block's own door, the theme's model field is the classifier while it is visible to everyone, as
     * the theme creates it, and no classifier once it is made teachers-only.
     *
     * @return void
     */
    public function test_the_block_takes_the_model_field_only_while_visible_to_everyone(): void {
        // The block's observers of custom field events run after the commit ('internal' => false), and the purge of
        // its field cache is one of them: under the default rollback reset they would never fire.
        $this->preventResetByRollback();
        landingfields::ensure_fields();
        \block_compass\local\filter_fields::purge();
        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        $this->assertNotNull($field);

        $this->assertSame((int) $field->get('id'), \block_compass\local\course_kinds::field_id(), 'as the theme creates it');

        $record = $field->to_record();
        $config = json_decode($record->configdata, true);
        $config['visibility'] = \core_course\customfield\course_handler::VISIBLETOTEACHERS;
        $record->configdata = json_encode($config);
        \core_course\customfield\course_handler::create()->save_field_configuration($field, $record);

        $this->assertSame(0, \block_compass\local\course_kinds::field_id(), 'teachers only: no classifier');
    }
}
