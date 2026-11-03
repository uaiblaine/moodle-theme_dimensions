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
 * Theme Dimensions - Tests for the landing page defaults for new courses
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core\hook\manager;
use core_course\customfield\course_handler;
use core_customfield\api;
use theme_dimensions\local\trail\trail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the landing page defaults for new courses.
 *
 * Courses are made by the data generator, which calls create_course(), so the theme's
 * after_form_submission callback runs exactly as it does for the course form. The hook
 * manager is the theme's own registrations alone, so no other plugin's callback writes
 * anything into the courses these tests read.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landingdefaults::class)]
final class landingdefaults_test extends \advanced_testcase {
    /**
     * Provision the fields, as an administrator, with the theme's hooks alone.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $manager = manager::phpunit_get_instance(['theme_dimensions' => $CFG->dirroot . '/theme/dimensions/db/hooks.php']);
        \core\di::set(manager::class, $manager);
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
     * Each field with a default, an option that is not its first and another option.
     *
     * @return array Cases of [shortname, default value, other value].
     */
    public static function field_provider(): array {
        return [
            'model' => [landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT, landingfields::MODEL_COURSE],
            'modality' => [landingfields::SHORTNAME_MODALITY, landingfields::MODALITY_HYBRID, landingfields::MODALITY_ONLINE],
            'format' => [landingfields::SHORTNAME_FORMAT, landingfields::FORMAT_TUTORED, landingfields::FORMAT_SELFPACED],
        ];
    }

    /**
     * Store a field's default for new courses.
     *
     * @param string $shortname The field shortname.
     * @param int|string $value The stored setting.
     * @return void
     */
    private static function set_default(string $shortname, $value): void {
        set_config(landingdefaults::settings()[$shortname], $value, landingfields::COMPONENT);
    }

    /**
     * The id of one of the provisioned fields.
     *
     * @param string $shortname The field shortname.
     * @return int The field id.
     */
    private static function field_id(string $shortname): int {
        return (int) landingfields::get_field($shortname)->get('id');
    }

    /**
     * The data row a course holds for a field, if any.
     *
     * @param int $courseid The course id.
     * @param string $shortname The field shortname.
     * @return \stdClass|null The row, or null when the course has none.
     */
    private static function row(int $courseid, string $shortname): ?\stdClass {
        global $DB;
        $row = $DB->get_record('customfield_data', ['instanceid' => $courseid, 'fieldid' => self::field_id($shortname)]);
        return $row ?: null;
    }

    /**
     * What core reads for a course's field: the stored value, or the field default without a row.
     *
     * @param int $courseid The course id.
     * @param string $shortname The field shortname.
     * @return int The select value core answers.
     */
    private function reads(int $courseid, string $shortname): int {
        foreach (course_handler::create()->get_instance_data($courseid, true) as $data) {
            if ($data->get_field()->get('shortname') === $shortname) {
                return (int) $data->get_value();
            }
        }
        $this->fail("The course has no data controller for '{$shortname}'.");
    }

    /**
     * Create a course through create_course().
     *
     * @param array $record Extra course properties, custom fields included.
     * @return int The course id.
     */
    private static function course(array $record = []): int {
        return (int) self::getDataGenerator()->create_course($record)->id;
    }

    /**
     * A new course stores the setting's value as its own row; at None the same creation stores nothing.
     *
     * @param string $shortname The field shortname.
     * @param int $value The default.
     * @param int $other Another option of the field.
     */
    #[DataProvider('field_provider')]
    public function test_a_new_course_gets_the_default(string $shortname, int $value, int $other): void {
        self::set_default($shortname, landingdefaults::NONE);
        $control = self::course();
        $this->assertNull(self::row($control, $shortname), 'At None the creation stores nothing.');

        self::set_default($shortname, $value);
        $courseid = self::course();

        $row = self::row($courseid, $shortname);
        $this->assertNotNull($row, 'The default is written as a data row.');
        $this->assertSame($value, (int) $row->intvalue);
        $this->assertSame((string) $value, $row->value);
        $this->assertSame($value, $this->reads($courseid, $shortname));
    }

    /**
     * The row is core's shape: the course context, and the course handler's component, area and item.
     */
    public function test_the_row_is_written_in_cores_shape(): void {
        self::set_default(landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT);
        $courseid = self::course();

        $row = self::row($courseid, landingfields::SHORTNAME_MODEL);
        $this->assertNotNull($row);
        $this->assertSame((int) \core\context\course::instance($courseid)->id, (int) $row->contextid);
        $this->assertSame('core_course', $row->component);
        $this->assertSame('course', $row->area);
        $this->assertSame(0, (int) $row->itemid);
    }

    /**
     * Moving the setting changes no existing course, with or without a row; a course created after it gets the new value.
     *
     * @param string $shortname The field shortname.
     * @param int $value The default.
     * @param int $other Another option of the field.
     */
    #[DataProvider('field_provider')]
    public function test_an_existing_course_never_changes_when_the_setting_moves(string $shortname, int $value, int $other): void {
        self::set_default($shortname, landingdefaults::NONE);
        $rowless = self::course();
        $explicit = self::course(['customfield_' . $shortname => $other]);
        $this->assertNull(self::row($rowless, $shortname));
        $this->assertSame(0, $this->reads($rowless, $shortname));
        $this->assertSame($other, $this->reads($explicit, $shortname));

        self::set_default($shortname, $value);

        $this->assertNull(self::row($rowless, $shortname), 'Moving the setting writes no row.');
        $this->assertSame(0, $this->reads($rowless, $shortname), 'A course nobody answered still reads empty.');
        $this->assertSame($other, $this->reads($explicit, $shortname), 'A stored answer is kept.');

        $after = self::course();
        $this->assertSame($value, $this->reads($after, $shortname), 'A course created after the move gets the new value.');
    }

    /**
     * Back at None after a value, a new course gets no row and reads empty; under the value it got the row.
     *
     * @param string $shortname The field shortname.
     * @param int $value The default.
     * @param int $other Another option of the field.
     */
    #[DataProvider('field_provider')]
    public function test_none_leaves_the_field_empty(string $shortname, int $value, int $other): void {
        self::set_default($shortname, $value);
        $control = self::course();
        $this->assertNotNull(self::row($control, $shortname), 'A default other than None writes a row.');

        self::set_default($shortname, landingdefaults::NONE);
        $courseid = self::course();

        $this->assertNull(self::row($courseid, $shortname));
        $this->assertSame(0, $this->reads($courseid, $shortname));
    }

    /**
     * An answer given at creation wins, the explicit empty one included; with no answer the default is written.
     */
    public function test_an_explicit_answer_at_creation_wins(): void {
        $element = 'customfield_' . landingfields::SHORTNAME_MODEL;
        self::set_default(landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT);

        $control = self::course();
        $this->assertSame(landingfields::MODEL_EVENT, $this->reads($control, landingfields::SHORTNAME_MODEL));

        $other = self::course([$element => landingfields::MODEL_COURSE]);
        $this->assertSame(landingfields::MODEL_COURSE, $this->reads($other, landingfields::SHORTNAME_MODEL));

        $empty = self::course([$element => 0]);
        $row = self::row($empty, landingfields::SHORTNAME_MODEL);
        $this->assertNotNull($row, 'Core stores the empty choice as a row.');
        $this->assertSame(0, (int) $row->intvalue);
        $this->assertSame(0, $this->reads($empty, landingfields::SHORTNAME_MODEL));
    }

    /**
     * Saving an existing course never writes the default; creating one under the same setting does.
     */
    public function test_an_update_never_writes_the_default(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        self::set_default(landingfields::SHORTNAME_MODEL, landingdefaults::NONE);
        $courseid = self::course();
        self::set_default(landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT);

        update_course((object) ['id' => $courseid, 'fullname' => 'Renamed course']);

        $this->assertNull(self::row($courseid, landingfields::SHORTNAME_MODEL));
        $this->assertSame(0, $this->reads($courseid, landingfields::SHORTNAME_MODEL));

        $control = self::course();
        $this->assertSame(landingfields::MODEL_EVENT, $this->reads($control, landingfields::SHORTNAME_MODEL));
    }

    /**
     * A setting past the field's options is None and writes nothing; an option inside them writes.
     */
    public function test_an_out_of_range_setting_writes_nothing(): void {
        self::set_default(landingfields::SHORTNAME_MODEL, 9);
        $this->assertSame(landingdefaults::NONE, landingdefaults::value(landingfields::SHORTNAME_MODEL));
        $courseid = self::course();
        $this->assertNull(self::row($courseid, landingfields::SHORTNAME_MODEL));

        self::set_default(landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT);
        $control = self::course();
        $this->assertNotNull(self::row($control, landingfields::SHORTNAME_MODEL));
    }

    /**
     * A field that does not resolve takes no default: None, and nothing written.
     */
    public function test_an_unprovisioned_field_writes_nothing(): void {
        global $DB;
        self::set_default(landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT);
        $control = self::course();
        $this->assertNotNull(self::row($control, landingfields::SHORTNAME_MODEL), 'In range and provisioned, the default writes.');

        api::delete_field_configuration(landingfields::get_field(landingfields::SHORTNAME_MODEL));
        landingfields::reset_field_cache();
        course_handler::reset_caches();

        $this->assertSame(landingdefaults::NONE, landingdefaults::value(landingfields::SHORTNAME_MODEL));
        $courseid = self::course();
        $this->assertSame(0, $DB->count_records('customfield_data', ['instanceid' => $courseid]));
    }

    /**
     * The value is clamped to the options as configured now: a removed line turns a setting past it into None.
     */
    public function test_the_value_follows_the_options_as_configured(): void {
        self::set_default(landingfields::SHORTNAME_MODALITY, landingfields::MODALITY_HYBRID);
        $this->assertSame(landingfields::MODALITY_HYBRID, landingdefaults::value(landingfields::SHORTNAME_MODALITY));

        $field = landingfields::get_field(landingfields::SHORTNAME_MODALITY);
        $config = (array) $field->get('configdata');
        $config['options'] = "Online\nIn-person";
        api::save_field_configuration($field, (object) ['configdata' => $config]);
        landingfields::reset_field_cache();

        $this->assertSame(landingdefaults::NONE, landingdefaults::value(landingfields::SHORTNAME_MODALITY));

        self::set_default(landingfields::SHORTNAME_MODALITY, 'not a number');
        $this->assertSame(landingdefaults::NONE, landingdefaults::value(landingfields::SHORTNAME_MODALITY));
    }

    /**
     * The choices are the field's option lines as configured, after None.
     */
    public function test_the_choices_read_the_field_as_configured(): void {
        $none = get_string('landingdefault_none', landingfields::COMPONENT);
        $expected = [
            0 => $none,
            1 => get_string('landingfield_model_course', landingfields::COMPONENT),
            2 => get_string('landingfield_model_event', landingfields::COMPONENT),
        ];
        if (trail::available()) {
            $expected[3] = get_string('landingfield_model_trail', landingfields::COMPONENT);
        }
        $this->assertSame($expected, landingdefaults::choices(landingfields::SHORTNAME_MODEL));

        $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        $config = (array) $field->get('configdata');
        $config['options'] = "Open course\nLive event & workshop";
        api::save_field_configuration($field, (object) ['configdata' => $config]);
        landingfields::reset_field_cache();

        $this->assertSame(
            [0 => $none, 1 => 'Open course', 2 => 'Live event & workshop'],
            landingdefaults::choices(landingfields::SHORTNAME_MODEL),
            'A renamed option line is offered as the field now spells it, in the plain spelling.'
        );
    }

    /**
     * Without mod_subcourse enabled the trail is neither offered nor written into a new course; with it, both.
     *
     * The stored setting is left alone: it comes back into force with the module.
     */
    public function test_the_trail_default_needs_the_subcourse_module(): void {
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        self::set_default(landingfields::SHORTNAME_MODEL, landingfields::MODEL_TRAIL);

        $this->assertArrayHasKey(landingfields::MODEL_TRAIL, landingdefaults::choices(landingfields::SHORTNAME_MODEL));
        $this->assertSame(landingfields::MODEL_TRAIL, landingdefaults::value(landingfields::SHORTNAME_MODEL));
        $made = self::getDataGenerator()->create_course();
        $this->assertSame(
            landingfields::MODEL_TRAIL,
            landingfields::model((int) $made->id),
            'Precondition: the default is written.'
        );

        \core\plugininfo\mod::enable_plugin(trail::MODNAME, 0);

        $choices = landingdefaults::choices(landingfields::SHORTNAME_MODEL);
        $this->assertArrayNotHasKey(landingfields::MODEL_TRAIL, $choices);
        $this->assertArrayHasKey(landingfields::MODEL_EVENT, $choices, 'The other lines stay offered.');
        $this->assertSame(landingdefaults::NONE, landingdefaults::value(landingfields::SHORTNAME_MODEL));
        $course = self::getDataGenerator()->create_course();
        $this->assertSame(0, landingfields::model((int) $course->id), 'A new course is not made a trail.');
        $this->assertSame(
            (string) landingfields::MODEL_TRAIL,
            get_config(landingfields::COMPONENT, landingdefaults::SETTING_MODEL),
            'The setting itself is kept.'
        );
    }

    /**
     * Without the field the choices are the lines the theme provisions it with.
     */
    public function test_the_choices_fall_back_to_the_provisioned_options(): void {
        api::delete_field_configuration(landingfields::get_field(landingfields::SHORTNAME_FORMAT));
        landingfields::reset_field_cache();
        $this->assertNull(landingfields::get_field(landingfields::SHORTNAME_FORMAT));

        $this->assertSame(
            [
                0 => get_string('landingdefault_none', landingfields::COMPONENT),
                1 => get_string('landingfield_format_selfpaced', landingfields::COMPONENT),
                2 => get_string('landingfield_format_tutored', landingfields::COMPONENT),
            ],
            landingdefaults::choices(landingfields::SHORTNAME_FORMAT)
        );
    }

    /**
     * A field this class does not cover is refused, not answered with None.
     */
    public function test_an_unknown_field_is_refused(): void {
        $this->expectException(\coding_exception::class);
        landingdefaults::value(landingfields::SHORTNAME_WORKLOAD);
    }
}
