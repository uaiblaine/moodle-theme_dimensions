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
 * Theme Dimensions - Tests for the focus mode course custom field
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_course\customfield\course_handler;
use core_customfield\api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\task\materialise_focusmode_default;

/**
 * Tests for the focus mode course custom field and its default for new courses.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(focusmodefields::class)]
final class focusmodefields_test extends \advanced_testcase {
    /**
     * Start every test with empty request caches.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        focusmode::reset_caches();
        course_handler::reset_caches();
    }

    /**
     * Leave no request cache behind for the next test in this process.
     *
     * @return void
     */
    protected function tearDown(): void {
        focusmode::reset_caches();
        parent::tearDown();
    }

    /**
     * Remove the field and category the PHPUnit site install already provisioned.
     *
     * The test site runs db/install.php, so every test of the creation path starts against
     * a baseline which already holds the field. The request cache is reset around the
     * deletions because it caches the negative result too, and it persists across tests in
     * one process.
     *
     * @return void
     */
    private static function clear_field(): void {
        focusmodefields::reset_field_cache();
        $field = focusmodefields::get_field();
        if ($field) {
            api::delete_field_configuration($field);
        }
        focusmodefields::reset_field_cache();

        $storedid = (int) get_config(focusmodefields::COMPONENT, focusmodefields::CATEGORYID_CONFIG);
        if ($storedid) {
            foreach (api::get_categories_with_fields('core_course', 'course', 0) as $category) {
                if ((int) $category->get('id') === $storedid && !$category->get_fields()) {
                    api::delete_category($category);
                }
            }
            unset_config(focusmodefields::CATEGORYID_CONFIG, focusmodefields::COMPONENT);
        }
    }

    /**
     * Provision the field and hand back its id.
     *
     * @return int The field id.
     */
    private function provision(): int {
        focusmodefields::reset_field_cache();
        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();
        $field = focusmodefields::get_field();
        $this->assertNotNull($field);
        return (int) $field->get('id');
    }

    /**
     * Save the focus mode answer of one course through the course custom field handler.
     *
     * Saved as the administrator, because instance_form_save() silently drops the fields
     * the current user may not edit.
     *
     * @param \stdClass $course The course.
     * @param bool $on The answer to store.
     * @return void
     */
    private function set_course_switch(\stdClass $course, bool $on): void {
        $this->setAdminUser();
        course_handler::reset_caches();
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . focusmodefields::SHORTNAME => $on ? 1 : 0,
        ]);
        focusmode::reset_caches();
    }

    /**
     * Provisioning creates the field, in one category, configured as {@see focusmodefields::definitions()} declares it.
     *
     * @return void
     */
    public function test_ensure_fields_creates_the_field(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_field();

        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();

        $field = focusmodefields::get_field();
        $this->assertNotNull($field);
        $this->assertSame('checkbox', $field->get('type'));
        $this->assertSame(
            get_string('focusmodefield_enabled', focusmodefields::COMPONENT),
            $field->get('name')
        );
        // Invisible on course listings, unlocked, and unticked for a new course.
        $this->assertEquals(course_handler::NOTVISIBLE, $field->get_configdata_property('visibility'));
        $this->assertEquals(0, $field->get_configdata_property('locked'));
        $this->assertEquals(0, $field->get_configdata_property('checkbydefault'));

        // One category, remembered in config, holding exactly this field.
        $categoryid = (int) get_config(focusmodefields::COMPONENT, focusmodefields::CATEGORYID_CONFIG);
        $this->assertGreaterThan(0, $categoryid);
        $this->assertSame($categoryid, (int) $field->get('categoryid'));
        $this->assertSame(1, $DB->count_records('customfield_field', ['categoryid' => $categoryid]));
    }

    /**
     * Running the provisioning again creates nothing new.
     *
     * @return void
     */
    public function test_ensure_fields_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_field();

        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();
        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();

        $this->assertSame(
            1,
            $DB->count_records('customfield_field', ['shortname' => focusmodefields::SHORTNAME])
        );
    }

    /**
     * A deleted field is recreated by the next run, inside the same category.
     *
     * @return void
     */
    public function test_ensure_fields_heals_a_deleted_field(): void {
        $this->resetAfterTest();
        $this->provision();

        $categoryid = (int) focusmodefields::get_field()->get('categoryid');
        api::delete_field_configuration(focusmodefields::get_field());
        focusmodefields::reset_field_cache();
        $this->assertNull(focusmodefields::get_field());

        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();

        $restored = focusmodefields::get_field();
        $this->assertNotNull($restored);
        $this->assertSame($categoryid, (int) $restored->get('categoryid'));
    }

    /**
     * A pre-existing foreign field with our shortname is neither adopted nor duplicated.
     *
     * @return void
     */
    public function test_a_foreign_shortname_is_not_adopted(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_field();

        // An administrator created a course field with our shortname but another type.
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['name' => 'Handmade']);
        $generator->create_field([
            'categoryid' => $category->get('id'),
            'shortname' => focusmodefields::SHORTNAME,
            'type' => 'text',
        ]);
        focusmodefields::reset_field_cache();

        focusmodefields::ensure_fields();
        // The type mismatch and the skipped creation are surfaced through debugging().
        $this->assertNotEmpty($this->getDebuggingMessages());
        $this->resetDebugging();

        $rows = $DB->get_records('customfield_field', ['shortname' => focusmodefields::SHORTNAME]);
        $this->assertCount(1, $rows);
        $this->assertSame('text', reset($rows)->type);
        focusmodefields::reset_field_cache();
        $this->assertNull(focusmodefields::get_field());
        $this->resetDebugging();
    }

    /**
     * A course saved with the box ticked reads back as switched on, and its neighbour does not.
     *
     * @return void
     */
    public function test_a_ticked_course_reads_back_true(): void {
        $this->resetAfterTest();
        $this->provision();

        $ticked = self::getDataGenerator()->create_course();
        $untouched = self::getDataGenerator()->create_course();
        $this->set_course_switch($ticked, true);

        $this->assertTrue(focusmode::enabled_for_course((int) $ticked->id));
        $this->assertFalse(focusmode::enabled_for_course((int) $untouched->id));
    }

    /**
     * Moving the default writes the previous answer into the courses which never stored one.
     *
     * The stored intvalue is what is asserted, not the effective answer: reading the answer
     * back through get_value() would be green under either order of the two steps, and the
     * order is the whole mechanism.
     *
     * @return void
     */
    public function test_sync_default_writes_the_previous_answer_into_rowless_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();

        // The previous default was "on".
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();
        focusmode::reset_caches();

        $answered = self::getDataGenerator()->create_course();
        $this->set_course_switch($answered, false);
        $rowless = self::getDataGenerator()->create_course();

        focusmodefields::sync_default(false);

        // The course which never answered now carries the old default, on the record.
        $stored = $DB->get_record('customfield_data', [
            'fieldid' => $fieldid,
            'instanceid' => $rowless->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(1, $stored->intvalue);
        $this->assertSame('1', (string) $stored->value);

        // The course which had answered kept its own answer.
        $this->assertEquals(0, $DB->get_field('customfield_data', 'intvalue', [
            'fieldid' => $fieldid,
            'instanceid' => $answered->id,
        ]));

        // The site course is never written to.
        $this->assertFalse($DB->record_exists('customfield_data', [
            'fieldid' => $fieldid,
            'instanceid' => SITEID,
        ]));

        // And only then does the default for new courses move.
        focusmodefields::reset_field_cache();
        $this->assertEquals(0, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
    }

    /**
     * No existing course changes its answer when the default for new courses moves.
     *
     * Changes that must make it fail: reordering the two steps of sync_default(), or removing
     * the materialising one.
     *
     * @return void
     */
    public function test_sync_default_changes_no_existing_courses_answer(): void {
        $this->resetAfterTest();
        $this->provision();
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();

        $answered = self::getDataGenerator()->create_course();
        $this->set_course_switch($answered, false);
        $rowless = self::getDataGenerator()->create_course();

        focusmode::reset_caches();
        $before = [
            focusmode::enabled_for_course((int) $answered->id),
            focusmode::enabled_for_course((int) $rowless->id),
        ];
        $this->assertSame([false, true], $before);

        focusmodefields::sync_default(false);

        focusmode::reset_caches();
        $after = [
            focusmode::enabled_for_course((int) $answered->id),
            focusmode::enabled_for_course((int) $rowless->id),
        ];
        $this->assertSame($before, $after);
    }

    /**
     * A course created after the move follows the new default, with nothing else changing.
     *
     * @return void
     */
    public function test_a_course_created_after_the_move_follows_the_new_default(): void {
        $this->resetAfterTest();
        $this->provision();

        focusmodefields::sync_default(true);
        focusmodefields::reset_field_cache();
        focusmode::reset_caches();

        $this->assertEquals(1, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
        $fresh = self::getDataGenerator()->create_course();
        $this->assertTrue(focusmode::enabled_for_course((int) $fresh->id));
    }

    /**
     * Above the inline limit the write is queued, and the task finishes the sequence.
     *
     * @return void
     */
    public function test_sync_default_queues_the_task_above_the_limit(): void {
        global $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        focusmode::reset_caches();

        // One course without an answer is already above a limit of zero.
        focusmodefields::sync_default(false, 0);

        // Nothing has been written and nothing has moved yet.
        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $fieldid]));
        focusmodefields::reset_field_cache();
        $this->assertEquals(1, focusmodefields::get_field()->get_configdata_property('checkbydefault'));

        $tasks = \core\task\manager::get_adhoc_tasks(materialise_focusmode_default::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        ob_start();
        $task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('1 course', $output);

        focusmodefields::reset_field_cache();
        focusmode::reset_caches();
        $this->assertEquals(1, $DB->get_field('customfield_data', 'intvalue', [
            'fieldid' => $fieldid,
            'instanceid' => $course->id,
        ]));
        $this->assertEquals(0, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
        $this->assertTrue(focusmode::enabled_for_course((int) $course->id));
    }

    /**
     * Set the focus mode setting the way the admin form stores it.
     *
     * @param bool $on The value.
     * @return void
     */
    private function set_setting(bool $on): void {
        set_config('focusmodenewcourses', $on ? yesno::YES : yesno::NO, focusmodefields::COMPONENT);
    }

    /**
     * A setting flipped back before cron leaves the queued task nothing to write.
     *
     * The control is the first half: while the setting reads "off" the same task does move
     * the field, so the end state is the setting's and not the field's own.
     *
     * @return void
     */
    public function test_a_setting_reverted_before_cron_leaves_the_field_alone(): void {
        global $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();
        $this->set_setting(true);
        $course = self::getDataGenerator()->create_course();

        // The admin turns it off: queued above the limit.
        $this->set_setting(false);
        focusmodefields::sync_default(false, 0);
        $tasks = \core\task\manager::get_adhoc_tasks(materialise_focusmode_default::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);

        // And back on before cron.
        $this->set_setting(true);
        focusmodefields::sync_default(true, 0);

        ob_start();
        $task->execute();
        ob_end_clean();

        focusmodefields::reset_field_cache();
        $this->assertEquals(1, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
        $this->assertFalse($DB->record_exists('customfield_data', [
            'fieldid' => $fieldid,
            'instanceid' => $course->id,
        ]));

        // Control: with the setting off the same task does move the field.
        $this->set_setting(false);
        ob_start();
        $task->execute();
        ob_end_clean();
        focusmodefields::reset_field_cache();
        $this->assertEquals(0, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
        $this->assertEquals(1, $DB->get_field('customfield_data', 'intvalue', [
            'fieldid' => $fieldid,
            'instanceid' => $course->id,
        ]));
    }

    /**
     * A refused lock queues the task instead of dropping the change, and the task retries.
     *
     * @return void
     */
    public function test_a_refused_lock_queues_a_task_which_brings_the_field_in_line(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();
        $this->set_setting(true);
        $course = self::getDataGenerator()->create_course();
        $this->set_setting(false);

        require_once($CFG->dirroot . '/theme/dimensions/tests/fixtures/refusing_lock_factory.php');
        $CFG->lock_factory = \theme_dimensions\tests\refusing_lock_factory::class;
        try {
            focusmodefields::sync_default(false);
            $tasks = \core\task\manager::get_adhoc_tasks(materialise_focusmode_default::class);
            $this->assertCount(1, $tasks);
            $task = reset($tasks);

            // Still refused: the task throws, so core retries it.
            try {
                $task->execute();
                $this->fail('A refused lock must make the task fail.');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        } finally {
            unset($CFG->lock_factory);
        }

        // Nothing moved while the lock was refused.
        focusmodefields::reset_field_cache();
        $this->assertEquals(1, focusmodefields::get_field()->get_configdata_property('checkbydefault'));

        ob_start();
        $task->execute();
        ob_end_clean();

        focusmodefields::reset_field_cache();
        $this->assertEquals(0, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
        $this->assertEquals(1, $DB->get_field('customfield_data', 'intvalue', [
            'fieldid' => $fieldid,
            'instanceid' => $course->id,
        ]));
    }

    /**
     * The task agrees with the setting and writes nothing when the field already does.
     *
     * @return void
     */
    public function test_the_task_does_nothing_when_field_and_setting_agree(): void {
        global $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();
        self::getDataGenerator()->create_course();
        $this->set_setting(false);

        $task = new materialise_focusmode_default();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $fieldid]));

        // Control: with the field on and the setting off the same task does write the rowless course.
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();
        ob_start();
        $task->execute();
        ob_end_clean();
        $this->assertTrue($DB->record_exists('customfield_data', ['fieldid' => $fieldid]), 'Disagreeing, the task writes.');
    }

    /**
     * Moving the default writes nothing while an upgrade is running.
     *
     * Without the guard admin_apply_default_settings() would run this during an install,
     * against half-built tables. The control is
     * {@see test_sync_default_writes_the_previous_answer_into_rowless_courses()}, where the
     * same fixture does write a row.
     *
     * @return void
     */
    public function test_sync_default_is_inert_during_an_upgrade(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();
        self::getDataGenerator()->create_course();

        $CFG->upgraderunning = 1;
        focusmodefields::sync_default(false);
        unset($CFG->upgraderunning);

        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $fieldid]));
        focusmodefields::reset_field_cache();
        $this->assertEquals(1, focusmodefields::get_field()->get_configdata_property('checkbydefault'));
    }

    /**
     * Moving the default to where it already is writes nothing at all.
     *
     * @return void
     */
    public function test_sync_default_is_inert_when_nothing_moves(): void {
        global $DB;
        $this->resetAfterTest();
        $fieldid = $this->provision();
        self::getDataGenerator()->create_course();

        // The field ships with the default off, and the setting says off.
        focusmodefields::sync_default(false);

        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $fieldid]));

        // Control: moving it from on to off does write the rowless course.
        focusmodefields::set_default(true);
        focusmodefields::reset_field_cache();
        focusmodefields::sync_default(false);
        $this->assertTrue($DB->record_exists('customfield_data', ['fieldid' => $fieldid]), 'A default that moves writes.');
    }
}
