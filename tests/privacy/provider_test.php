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
 * Theme Dimensions - Tests for the privacy provider
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\user_preference;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\focusmode;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\category_toolbar;
use theme_dimensions\output\course_notice;

/**
 * Tests for the privacy provider.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The plugin counts as compliant with the privacy API.
     *
     * Core's own compliance test sweeps every component but is not in the plugin's testsuite,
     * which is all moodle-plugin-ci runs, so the check is repeated here: a metadata provider
     * without a request data provider fails it ({@see \core_privacy\manager::component_is_compliant()}).
     *
     * @return void
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('theme_dimensions'));
    }

    /**
     * The metadata names the five preferences and the one table, whose usermodified column is the only user id in it.
     *
     * @return void
     */
    public function test_the_metadata_names_the_five_preferences_and_the_branding_stamp(): void {
        $collection = provider::get_metadata(new collection('theme_dimensions'));

        $items = $collection->get_collection();
        $this->assertCount(6, $items);
        $this->assertInstanceOf(user_preference::class, $items[5]);
        $this->assertEquals(course_notice::PREFERENCE_COLLAPSED, $items[5]->get_name());
        $this->assertEquals('privacy:metadata:preference:coursenotice_collapsed', $items[5]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[4]);
        $this->assertEquals(remembered::PREFERENCE, $items[4]->get_name());
        $this->assertEquals('privacy:metadata:preference:category_state', $items[4]->get_summary());
        $this->assertInstanceOf(database_table::class, $items[0]);
        $this->assertEquals('theme_dimensions_cat', $items[0]->get_name());
        $this->assertSame(['usermodified' => 'privacy:metadata:cat:usermodified'], $items[0]->get_privacy_fields());
        $this->assertEquals('privacy:metadata:cat', $items[0]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[3]);
        $this->assertEquals(category_hero::PREFERENCE_FOLDED, $items[3]->get_name());
        $this->assertEquals('privacy:metadata:preference:category_herofolded', $items[3]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[1]);
        $this->assertEquals(focusmode::PREFERENCE_EXPANDED, $items[1]->get_name());
        $this->assertEquals('privacy:metadata:preference:focus_expanded', $items[1]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[2]);
        $this->assertEquals(category_listing::PREFERENCE_VIEW, $items[2]->get_name());
        $this->assertEquals('privacy:metadata:preference:category_view', $items[2]->get_summary());
    }

    /**
     * Every user-linked column of the plugin's tables is declared, so a new one cannot be added unnoticed.
     *
     * The column list is read from the live table, and the declared one must be exactly the columns
     * named for a user: a second column holding a user id would fail here until it is described.
     *
     * @return void
     */
    public function test_every_user_column_of_the_branding_table_is_declared(): void {
        global $DB;

        $declared = [];
        foreach (provider::get_metadata(new collection('theme_dimensions'))->get_collection() as $item) {
            if ($item instanceof database_table) {
                $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
            }
        }

        $columns = array_filter(
            array_keys($DB->get_columns('theme_dimensions_cat')),
            static fn(string $name): bool => preg_match('~^(userid|usermodified)$~', $name) === 1
        );
        $this->assertSame(['usermodified'], array_values($columns), 'Precondition: the table holds one user column.');
        $this->assertSame(['usermodified'], $declared['theme_dimensions_cat']);
    }

    /**
     * A stored view choice is exported by its own name, beside or without the width choice.
     *
     * @return void
     */
    public function test_the_view_choice_is_exported_when_it_was_made(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;
        set_user_preference(category_listing::PREFERENCE_VIEW, category_toolbar::VIEW_LIST, $user);
        provider::export_user_preferences($userid);

        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertEquals(category_toolbar::VIEW_LIST, $preferences->{category_listing::PREFERENCE_VIEW}->value);
        $this->assertObjectNotHasProperty(focusmode::PREFERENCE_EXPANDED, $preferences, 'The width was never chosen.');
        $this->assertObjectNotHasProperty(category_hero::PREFERENCE_FOLDED, $preferences, 'The fold was never chosen.');
    }

    /**
     * The state a person left their category pages in is exported as it is stored, beside the other choices.
     *
     * The control is a person who never left one: nothing is exported for them, which is also what
     * an export that never ran would show, so the stored case comes first.
     *
     * @return void
     */
    public function test_the_remembered_category_state_is_exported_when_there_is_one(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->setUser($user);
        remembered::record($categoryid, [remembered::KEY_SORT => 'name', remembered::KEY_VIEW => 'list']);
        $stored = get_user_preferences(remembered::PREFERENCE, null, $user);
        $this->assertSame('{"' . $categoryid . '":{"s":"name","v":"list"}}', $stored, 'Precondition: the state is stored.');

        provider::export_user_preferences((int) $user->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertSame($stored, $preferences->{remembered::PREFERENCE}->value);
        $this->assertSame(
            get_string('privacy:metadata:preference:category_state', 'theme_dimensions'),
            $preferences->{remembered::PREFERENCE}->description
        );

        writer::reset();
        $other = $this->getDataGenerator()->create_user();
        provider::export_user_preferences((int) $other->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertObjectNotHasProperty(remembered::PREFERENCE, $preferences, 'Nothing was ever left by this person.');
    }

    /**
     * A stored fold choice is exported by its own name, as yes or no.
     *
     * @return void
     */
    public function test_the_fold_choice_is_exported_when_it_was_made(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;
        set_user_preference(category_hero::PREFERENCE_FOLDED, 1, $user);
        provider::export_user_preferences($userid);

        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertEquals(get_string('yes'), $preferences->{category_hero::PREFERENCE_FOLDED}->value);
        $this->assertObjectNotHasProperty(category_listing::PREFERENCE_VIEW, $preferences, 'The view was never chosen.');
    }

    /**
     * A stored fold of the course notice is exported by its own name, as yes or no.
     *
     * @return void
     */
    public function test_the_course_notice_fold_is_exported_when_it_was_made(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;
        set_user_preference(course_notice::PREFERENCE_COLLAPSED, 1, $user);
        provider::export_user_preferences($userid);

        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertEquals(get_string('yes'), $preferences->{course_notice::PREFERENCE_COLLAPSED}->value);
        $this->assertObjectNotHasProperty(category_hero::PREFERENCE_FOLDED, $preferences, 'The hero fold was never chosen.');
    }

    /**
     * Every metadata summary this provider declares exists as a string of this component.
     *
     * Core's privacy provider_test checks the same for every component at once, where a failure
     * reads as a core fault, and plugin CI does not run it; this one runs with the theme's suite.
     *
     * @return void
     */
    public function test_every_metadata_summary_and_field_is_a_string_of_this_theme(): void {
        $collection = provider::get_metadata(new collection('theme_dimensions'));

        foreach ($collection->get_collection() as $item) {
            $this->assertTrue(
                get_string_manager()->string_exists($item->get_summary(), 'theme_dimensions'),
                'Missing string ' . $item->get_summary()
            );
            foreach ($item->get_privacy_fields() ?? [] as $identifier) {
                $this->assertTrue(
                    get_string_manager()->string_exists($identifier, 'theme_dimensions'),
                    'Missing string ' . $identifier
                );
            }
        }
    }

    /**
     * A stored width choice is exported, and an absent one exports nothing.
     *
     * The two halves are one test on purpose. The second assertion is that nothing was written,
     * which passes just as well when the export never ran at all; the first half is its control,
     * so a provider whose export does nothing fails here rather than reading as correct.
     *
     * @return void
     */
    public function test_the_width_choice_is_exported_only_when_it_was_made(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $userid = (int) $user->id;

        // Control: with the preference set, the export writes it.
        set_user_preference(focusmode::PREFERENCE_EXPANDED, 1, $user);
        provider::export_user_preferences($userid);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());
        $preferences = $writer->get_user_preferences('theme_dimensions');
        $this->assertEquals(get_string('yes'), $preferences->{focusmode::PREFERENCE_EXPANDED}->value);

        // And with no preference stored, the same call writes nothing.
        writer::reset();
        unset_user_preference(focusmode::PREFERENCE_EXPANDED, $user);
        $this->assertNull(get_user_preferences(focusmode::PREFERENCE_EXPANDED, null, $userid));

        provider::export_user_preferences($userid);
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * A width choice of "contracted" is exported as such, not dropped as a falsy value.
     *
     * @return void
     */
    public function test_a_contracted_choice_is_exported_too(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        set_user_preference(focusmode::PREFERENCE_EXPANDED, 0, $user);

        provider::export_user_preferences((int) $user->id);

        $preferences = writer::with_context(\context_system::instance())
            ->get_user_preferences('theme_dimensions');
        $this->assertEquals(get_string('no'), $preferences->{focusmode::PREFERENCE_EXPANDED}->value);
    }
}
