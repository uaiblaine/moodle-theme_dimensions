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
use core_privacy\local\metadata\types\subsystem_link;
use core_privacy\local\metadata\types\user_preference;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\state as calendar_state;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\flavourchoice;
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
     * The metadata names the seven preferences, the one table (its authorship stamp and when it was saved),
     * the cohort subsystem, which keeps the look cohorts' memberships, core's table of the filter values
     * stored on the theme's class report, and the class report's columns, one preference per course.
     *
     * @return void
     */
    public function test_the_metadata_names_the_seven_preferences_and_the_branding_stamp(): void {
        $collection = provider::get_metadata(new collection('theme_dimensions'));

        $items = $collection->get_collection();
        $this->assertCount(11, $items);
        $this->assertInstanceOf(user_preference::class, $items[10]);
        $this->assertEquals('theme_dimensions_classreport', $items[10]->get_name());
        $this->assertEquals('privacy:metadata:preference:classreport', $items[10]->get_summary());
        $this->assertInstanceOf(database_table::class, $items[9]);
        $this->assertEquals('reportbuilder_user_filter', $items[9]->get_name());
        $this->assertEquals('privacy:metadata:reportbuilder_user_filter', $items[9]->get_summary());
        $this->assertInstanceOf(subsystem_link::class, $items[8]);
        $this->assertEquals('core_cohort', $items[8]->get_name());
        $this->assertEquals('privacy:metadata:core_cohort', $items[8]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[7]);
        $this->assertEquals(calendar_state::PREFERENCE, $items[7]->get_name());
        $this->assertEquals('privacy:metadata:preference:calendar_state', $items[7]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[6]);
        $this->assertEquals(flavourchoice::PREFERENCE, $items[6]->get_name());
        $this->assertEquals('privacy:metadata:preference:flavour', $items[6]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[5]);
        $this->assertEquals(course_notice::PREFERENCE_COLLAPSED, $items[5]->get_name());
        $this->assertEquals('privacy:metadata:preference:coursenotice_collapsed', $items[5]->get_summary());
        $this->assertInstanceOf(user_preference::class, $items[4]);
        $this->assertEquals(remembered::PREFERENCE, $items[4]->get_name());
        $this->assertEquals('privacy:metadata:preference:category_state', $items[4]->get_summary());
        $this->assertInstanceOf(database_table::class, $items[0]);
        $this->assertEquals('theme_dimensions_cat', $items[0]->get_name());
        $this->assertSame([
            'usermodified' => 'privacy:metadata:cat:usermodified',
            'timemodified' => 'privacy:metadata:cat:timemodified',
        ], $items[0]->get_privacy_fields());
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
     * Every user column of the plugin's tables is declared in the privacy metadata, and the other way round.
     *
     * The columns are read from db/install.xml: a field that a foreign key points at the user table,
     * or named userid, usermodified, anything ending in userid, or anything ending in by. A new column
     * of that kind fails the comparison, so it cannot be added without revisiting the metadata.
     *
     * @return void
     */
    public function test_every_user_column_of_the_branding_table_is_declared(): void {
        $declared = [];
        foreach (provider::get_metadata(new collection('theme_dimensions'))->get_collection() as $item) {
            if ($item instanceof database_table) {
                $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
            }
        }

        $xml = simplexml_load_file(dirname(__DIR__, 2) . '/db/install.xml');
        $this->assertNotFalse($xml, 'Precondition: install.xml parses.');
        $byname = static fn(string $name): bool => preg_match('~^(usermodified|.*userid|.*by)$~', $name) === 1;
        $found = [];
        foreach ($xml->TABLES->TABLE as $table) {
            $users = [];
            foreach ($table->KEYS->KEY ?? [] as $key) {
                if ((string) $key['REFTABLE'] === 'user') {
                    $users = array_merge($users, explode(',', (string) $key['FIELDS']));
                }
            }
            foreach ($table->FIELDS->FIELD as $field) {
                if ($byname((string) $field['NAME'])) {
                    $users[] = (string) $field['NAME'];
                }
            }
            $found[(string) $table['NAME']] = array_values(array_unique($users));
        }

        $this->assertSame(['usermodified'], $found['theme_dimensions_cat'], 'Precondition: the table holds one user column.');
        foreach ($found as $name => $users) {
            $this->assertArrayHasKey($name, $declared, "The table {$name} is declared.");
            foreach ($users as $user) {
                $this->assertContains($user, $declared[$name], "{$name}.{$user} is declared.");
            }
            $this->assertEqualsCanonicalizing(
                array_values(array_filter($declared[$name], $byname)),
                array_values(array_intersect($users, array_filter($declared[$name], $byname))),
                "Every declared user column of {$name} is in install.xml."
            );
        }
    }

    /**
     * A stored view choice is exported by its own name, and the width and fold never chosen are not.
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
     * The calendar page's stored state is exported whole, by its own name; a person who never left one exports nothing.
     *
     * @return void
     */
    public function test_the_calendar_state_is_exported_when_there_is_one(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        (new calendar_state(['group'], 0, true, [], [], 'Natal'))->save();
        $stored = get_user_preferences(calendar_state::PREFERENCE, null, $user);
        $this->assertNotNull($stored, 'Precondition: the state is stored.');

        provider::export_user_preferences((int) $user->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertSame($stored, $preferences->{calendar_state::PREFERENCE}->value);
        $this->assertStringContainsString('Natal', $preferences->{calendar_state::PREFERENCE}->value);
        $this->assertSame(
            get_string('privacy:metadata:preference:calendar_state', 'theme_dimensions'),
            $preferences->{calendar_state::PREFERENCE}->description
        );

        writer::reset();
        $other = $this->getDataGenerator()->create_user();
        provider::export_user_preferences((int) $other->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertObjectNotHasProperty(calendar_state::PREFERENCE, $preferences, 'Nothing was ever left by this person.');
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
     * A chosen look is exported by the flavour's title and id, the site default by its label.
     *
     * A flavour deleted after it was chosen is exported by the id the preference still holds, which
     * is all that is left of it; nothing chosen exports nothing.
     *
     * @return void
     */
    public function test_the_chosen_look_is_exported_by_title_and_id(): void {
        global $DB;
        $this->resetAfterTest();

        // A bare ampersand: the export is plain text and must carry it as typed, never as &amp;.
        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')
            ->create_flavour(['title' => 'Harbour & Co']);
        $chooser = $this->getDataGenerator()->create_user();
        $default = $this->getDataGenerator()->create_user();
        $orphan = $this->getDataGenerator()->create_user();
        $silent = $this->getDataGenerator()->create_user();
        set_user_preference(flavourchoice::PREFERENCE, $flavour->id, $chooser);
        set_user_preference(flavourchoice::PREFERENCE, flavourchoice::SITE_DEFAULT, $default);
        set_user_preference(flavourchoice::PREFERENCE, 987654, $orphan);

        provider::export_user_preferences((int) $chooser->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertSame('Harbour & Co (' . $flavour->id . ')', $preferences->{flavourchoice::PREFERENCE}->value);

        writer::reset();
        provider::export_user_preferences((int) $default->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertEquals(
            get_string('flavoursitedefault', 'theme_dimensions'),
            $preferences->{flavourchoice::PREFERENCE}->value
        );

        writer::reset();
        $this->assertFalse($DB->record_exists('theme_boost_union_flavours', ['id' => 987654]), 'Precondition: no such flavour.');
        provider::export_user_preferences((int) $orphan->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertEquals('987654', $preferences->{flavourchoice::PREFERENCE}->value);

        writer::reset();
        provider::export_user_preferences((int) $silent->id);
        $preferences = writer::with_context(\context_system::instance())->get_user_preferences('theme_dimensions');
        $this->assertObjectNotHasProperty(flavourchoice::PREFERENCE, $preferences, 'Nothing was chosen.');
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

    /**
     * Save the branding of a new category as the given person.
     *
     * @param \stdClass $user The person saving it.
     * @param string $tagline The tagline saved, so a test can tell the row survived a detach.
     * @return \core\context\coursecat The category's context.
     */
    private function branded_by(\stdClass $user, string $tagline): \core\context\coursecat {
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->setUser($user);
        branding::save($categoryid, (object) ['tagline' => $tagline]);
        $this->setUser(null);
        return \core\context\coursecat::instance($categoryid);
    }

    /**
     * The stamp of a category's branding, read from the table.
     *
     * @param \context $context A category context.
     * @return int The stored usermodified.
     */
    private function stamp(\context $context): int {
        global $DB;
        return (int) $DB->get_field(branding::TABLE, 'usermodified', ['categoryid' => $context->instanceid], MUST_EXIST);
    }

    /**
     * The categories a person last saved are their contexts, and only those.
     *
     * The other person's category is the control: a lookup that returned every branded category
     * would name it for the subject as well.
     *
     * @return void
     */
    public function test_the_categories_a_person_last_saved_are_their_contexts(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->branded_by($subject, 'Mine');
        $theirs = $this->branded_by($other, 'Theirs');

        $contexts = static fn(\stdClass $user): array => array_map(
            'intval',
            provider::get_contexts_for_userid((int) $user->id)->get_contextids()
        );
        $this->assertSame([(int) $mine->id], $contexts($subject));
        $this->assertSame([(int) $theirs->id], $contexts($other));

        $userlist = new userlist($mine, 'theme_dimensions');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $subject->id], array_map('intval', $userlist->get_userids()));

        $system = new userlist(\context_system::instance(), 'theme_dimensions');
        provider::get_users_in_context($system);
        $this->assertSame([], $system->get_userids(), 'Only a category context holds a stamp.');
    }

    /**
     * The export names the categories the request's subject saved, whoever runs it.
     *
     * The export runs while another person, who saved a category of their own, is the session
     * user: the privacy manager and tool_dataprivacy's task never switch $USER to the subject,
     * so a provider reading $USER would export that person's stamp instead.
     *
     * @return void
     */
    public function test_the_export_is_the_subjects_stamp_run_as_another_user(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->branded_by($subject, 'Mine');
        $theirs = $this->branded_by($other, 'Theirs');
        $this->setUser($other);

        $contextids = [(int) $mine->id, (int) $theirs->id];
        provider::export_user_data(new approved_contextlist($subject, 'theme_dimensions', $contextids));

        $path = [get_string('privacy:path:branding', 'theme_dimensions')];
        $exported = writer::with_context($mine)->get_data($path);
        $this->assertNotEmpty($exported, 'The category the subject saved is exported.');
        $this->assertEquals((int) $subject->id, $exported->usermodified);
        $this->assertNotEmpty($exported->timemodified);
        $this->assertFalse(
            writer::with_context($theirs)->has_any_data(),
            'Nothing of the session user is exported as the subject\'s.'
        );
    }

    /**
     * A deletion for one person clears their stamp, keeps the branding, and leaves another's stamp.
     *
     * @return void
     */
    public function test_a_deletion_for_a_person_detaches_them_and_keeps_the_branding(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->branded_by($subject, 'Mine');
        $theirs = $this->branded_by($other, 'Theirs');
        $this->assertSame((int) $subject->id, $this->stamp($mine), 'Precondition: the stamp names the subject.');

        $contextids = [(int) $mine->id, (int) $theirs->id];
        provider::delete_data_for_user(new approved_contextlist($subject, 'theme_dimensions', $contextids));

        $this->assertSame(0, $this->stamp($mine));
        $this->assertSame('Mine', $DB->get_field(branding::TABLE, 'tagline', ['categoryid' => $mine->instanceid]));
        $this->assertSame((int) $other->id, $this->stamp($theirs), 'Another person\'s stamp is not the subject\'s to clear.');
    }

    /**
     * A deletion for a list of people clears theirs in that category and nobody else's.
     *
     * @return void
     */
    public function test_a_deletion_for_users_detaches_only_the_approved_ones(): void {
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->branded_by($subject, 'Mine');
        $theirs = $this->branded_by($other, 'Theirs');

        provider::delete_data_for_users(new approved_userlist($mine, 'theme_dimensions', [(int) $subject->id]));
        provider::delete_data_for_users(new approved_userlist($theirs, 'theme_dimensions', [(int) $subject->id]));

        $this->assertSame(0, $this->stamp($mine));
        $this->assertSame((int) $other->id, $this->stamp($theirs), 'The subject never saved this one.');
    }

    /**
     * A deletion for a whole category clears its stamp and no other category's.
     *
     * @return void
     */
    public function test_a_deletion_for_a_category_detaches_everyone_there_only(): void {
        global $DB;
        $this->resetAfterTest();
        $subject = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $mine = $this->branded_by($subject, 'Mine');
        $theirs = $this->branded_by($other, 'Theirs');

        provider::delete_data_for_all_users_in_context($mine);
        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(0, $this->stamp($mine));
        $this->assertSame('Mine', $DB->get_field(branding::TABLE, 'tagline', ['categoryid' => $mine->instanceid]));
        $this->assertSame((int) $other->id, $this->stamp($theirs), 'Another category keeps its stamp.');
    }

    /**
     * A course context that shares a branded category's instance id touches nothing of its stamp.
     *
     * The table is keyed by category id alone, so every method must first ask that the context is
     * a category's: a course whose id equals a branded category's would otherwise export, list or
     * clear that category's stamp. The category is imported under the course's id, which the
     * precondition checks. The control is the same deletion through the category's own context,
     * which does clear the stamp.
     *
     * @return void
     */
    public function test_a_course_context_with_a_branded_categorys_id_touches_nothing(): void {
        $this->resetAfterTest();
        global $DB;

        $subject = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        /* PHPUnit starts every table's sequence at its own offset, so a category never shares a
           course's id by chance: import one under the course's id, then brand it. */
        $DB->import_record('course_categories', (object) [
            'id' => (int) $course->id,
            'name' => 'Same id as the course',
            'parent' => 0,
            'sortorder' => 0,
            'depth' => 1,
            'path' => '/' . (int) $course->id,
            'visible' => 1,
            'timemodified' => time(),
        ]);
        $this->setUser($subject);
        branding::save((int) $course->id, (object) ['tagline' => 'Mine']);
        $this->setUser(null);
        $category = \core\context\coursecat::instance((int) $course->id);
        $this->assertSame((int) $course->id, (int) $category->instanceid, 'Precondition: the two ids coincide.');
        $coursecontext = \core\context\course::instance((int) $course->id);

        $userlist = new userlist($coursecontext, 'theme_dimensions');
        provider::get_users_in_context($userlist);
        $this->assertSame([], $userlist->get_userids());
        provider::export_user_data(new approved_contextlist($subject, 'theme_dimensions', [(int) $coursecontext->id]));
        $this->assertFalse(writer::with_context($coursecontext)->has_any_data());
        provider::delete_data_for_user(new approved_contextlist($subject, 'theme_dimensions', [(int) $coursecontext->id]));
        $this->assertSame((int) $subject->id, $this->stamp($category), 'delete_data_for_user() read a course as a category.');
        provider::delete_data_for_users(new approved_userlist($coursecontext, 'theme_dimensions', [(int) $subject->id]));
        $this->assertSame((int) $subject->id, $this->stamp($category), 'delete_data_for_users() read a course as a category.');
        provider::delete_data_for_all_users_in_context($coursecontext);
        $this->assertSame((int) $subject->id, $this->stamp($category), 'The context deletion read a course as a category.');

        provider::delete_data_for_all_users_in_context($category);
        $this->assertSame(0, $this->stamp($category), 'Control: the category\'s own context clears it.');
    }
}
