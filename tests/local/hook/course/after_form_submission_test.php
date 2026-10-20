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
 * Theme Dimensions - Tests for the course form submission hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use core\hook\manager;
use core_course\customfield\course_handler;
use theme_dimensions\local\landingdefaults;
use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course form submission hook callback.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(after_form_submission::class)]
final class after_form_submission_test extends \advanced_testcase {
    /**
     * Provision the fields, as an administrator, with the theme's hooks alone.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $this->theme_hooks();
    }

    /**
     * Put back the hook manager and forget the fields this test resolved.
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        landingfields::reset_field_cache();
        course_handler::reset_caches();
        parent::tearDown();
    }

    /**
     * A hook manager reading the theme's db/hooks.php alone.
     *
     * @return manager The manager, installed in the container.
     */
    private function theme_hooks(): manager {
        global $CFG;
        $manager = manager::phpunit_get_instance(['theme_dimensions' => $CFG->dirroot . '/theme/dimensions/db/hooks.php']);
        \core\di::set(manager::class, $manager);
        return $manager;
    }

    /**
     * The page model row of a course, if any.
     *
     * @param int $courseid The course id.
     * @return \stdClass|null The row.
     */
    private static function model_row(int $courseid): ?\stdClass {
        global $DB;
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_MODEL)->get('id');
        return $DB->get_record('customfield_data', ['instanceid' => $courseid, 'fieldid' => $fieldid]) ?: null;
    }

    /**
     * The theme's db/hooks.php registers callback() for core's submission hook, and core resolves it.
     */
    public function test_the_registration_resolves_to_the_callback(): void {
        $manager = $this->theme_hooks();

        $definitions = $manager->get_callbacks_for_hook(\core_course\hook\after_form_submission::class);

        $this->assertSame([after_form_submission::class . '::callback'], array_column($definitions, 'callback'));
        $this->assertSame(['theme_dimensions'], array_column($definitions, 'component'));
        $this->assertSame([false], array_column($definitions, 'disabled'), 'Not switched off by a callback override.');
        $listeners = iterator_to_array(
            $manager->getListenersForEvent(new \core_course\hook\after_form_submission(new \stdClass(), true)),
            false
        );
        $this->assertSame([after_form_submission::class . '::callback'], $listeners, 'Core finds the class and its static method.');
    }

    /**
     * Dispatched for an update, the callback writes nothing; dispatched for a creation, it writes the default.
     */
    public function test_an_update_writes_nothing_and_a_creation_writes_the_default(): void {
        $courseid = (int) self::getDataGenerator()->create_course()->id;
        $this->assertNull(self::model_row($courseid), 'Created at None: no row.');
        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);

        after_form_submission::callback(new \core_course\hook\after_form_submission((object) ['id' => $courseid], false));
        $this->assertNull(self::model_row($courseid), 'An update never writes the default.');

        after_form_submission::callback(new \core_course\hook\after_form_submission((object) ['id' => $courseid], true));
        $row = self::model_row($courseid);
        $this->assertNotNull($row, 'The same course announced as new gets the default.');
        $this->assertSame(landingfields::MODEL_EVENT, (int) $row->intvalue);
    }

    /**
     * Data without an id is skipped rather than written against course 0.
     */
    public function test_data_without_an_id_writes_nothing(): void {
        global $DB;
        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);
        $before = $DB->count_records('customfield_data');

        after_form_submission::callback(new \core_course\hook\after_form_submission(new \stdClass(), true));

        $this->assertSame($before, $DB->count_records('customfield_data'));
        $courseid = (int) self::getDataGenerator()->create_course()->id;
        $this->assertNotNull(self::model_row($courseid), 'With an id the same setting writes.');
    }
}
