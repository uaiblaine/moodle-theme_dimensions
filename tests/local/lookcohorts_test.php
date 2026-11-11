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
 * Theme Dimensions - Tests for the look cohorts
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use theme_boost_union\recommendation\recommendation;
use theme_boost_union\smartmenu;
use theme_dimensions\recommendation\lookcohorts_hidden;
use theme_dimensions\task\empty_look_cohort;
use theme_dimensions\task\reconcile_look_cohorts;

/**
 * Tests for the cohorts that carry each person's chosen look to Boost Union's smart menus.
 *
 * Smart menus are restricted by cohort ids stored as a JSON list of strings, as Boost Union's own form
 * stores them; its lookup of the menus a deleted cohort restricted matches that spelling.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(lookcohorts::class)]
#[CoversClass(empty_look_cohort::class)]
#[CoversClass(reconcile_look_cohorts::class)]
#[CoversClass(lookcohorts_hidden::class)]
#[CoversFunction('theme_dimensions_sync_look_cohorts')]
#[CoversFunction('xmldb_theme_dimensions_uninstall')]
final class lookcohorts_test extends \advanced_testcase {
    /**
     * Load the theme's library and the cohort library for every test.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->resetAfterTest();
    }

    /**
     * A Boost Union flavour, made by Boost Union's own generator.
     *
     * @param string $title The flavour's title.
     * @return \stdClass The flavour's record.
     */
    private function flavour(string $title): \stdClass {
        global $DB;

        $count = $DB->count_records('theme_boost_union_flavours');
        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')
            ->create_flavour(['title' => $title, 'sort' => $count + 1]);

        return $DB->get_record('theme_boost_union_flavours', ['id' => $flavour->id], '*', MUST_EXIST);
    }

    /**
     * Offer exactly these flavours in the setting, as a save of the settings page does: the callback runs.
     *
     * @param \stdClass ...$flavours The flavours.
     * @return void
     */
    private function offer(\stdClass ...$flavours): void {
        set_config(flavourchoice::SETTING, implode(',', array_map(fn($f) => $f->id, $flavours)), 'theme_dimensions');
        theme_dimensions_sync_look_cohorts();
    }

    /**
     * The look cohort of a flavour.
     *
     * @param \stdClass $flavour The flavour.
     * @return \stdClass The cohort record.
     */
    private function cohort(\stdClass $flavour): \stdClass {
        $cohorts = lookcohorts::cohorts();
        $this->assertArrayHasKey((int) $flavour->id, $cohorts, 'Precondition: the look has its cohort.');

        return $cohorts[(int) $flavour->id];
    }

    /**
     * The members of a flavour's look cohort, sorted.
     *
     * @param \stdClass $flavour The flavour.
     * @return int[]
     */
    private function members(\stdClass $flavour): array {
        global $DB;

        $ids = array_map('intval', $DB->get_fieldset_select(
            'cohort_members',
            'userid',
            'cohortid = :cohortid',
            ['cohortid' => $this->cohort($flavour)->id]
        ));
        sort($ids);

        return $ids;
    }

    /**
     * A person's choice as the menu and the choice page record it.
     *
     * @param \stdClass $user The person.
     * @param int $flavourid The flavour, or the site default.
     * @return void
     */
    private function choose_as(\stdClass $user, int $flavourid): void {
        $this->setUser($user);
        $this->assertTrue(flavourchoice::choose($flavourid), 'Precondition: the choice is recorded.');
    }

    /**
     * A main-navigation smart menu restricted by these cohorts, with one unrestricted item.
     *
     * Boost Union builds a menu without items as no menu at all, so the item is what lets a build show it.
     *
     * @param \stdClass ...$cohorts The cohorts.
     * @return \stdClass The menu record.
     */
    private function menu(\stdClass ...$cohorts): \stdClass {
        $generator = $this->getDataGenerator()->get_plugin_generator('theme_boost_union');
        $menu = $generator->create_smartmenu([
            'location' => [smartmenu::LOCATION_MAIN],
            'cohorts' => array_map(fn($c) => (string) $c->id, $cohorts),
        ]);
        $generator->create_smartmenu_item(['menu' => $menu->id, 'url' => '/my/']);

        return $menu;
    }

    /**
     * Whether a smart menu is built for the current user.
     *
     * @param \stdClass $menu The menu record.
     * @return bool
     */
    private function shown(\stdClass $menu): bool {
        return smartmenu::instance((int) $menu->id)->build() !== false;
    }

    /**
     * Each offered look gets one cohort of the theme's, and a second run makes nothing new.
     *
     * @return void
     */
    public function test_every_offered_look_gets_one_stamped_cohort(): void {
        global $DB;

        $harbour = $this->flavour('Harbour & Co');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        lookcohorts::provision();

        $cohorts = lookcohorts::cohorts();
        $this->assertSame([(int) $harbour->id, (int) $meadow->id], array_keys($cohorts));
        $this->assertSame(2, $DB->count_records('cohort'), 'A second provisioning makes no second cohort.');
        $cohort = $cohorts[(int) $harbour->id];
        $this->assertSame(lookcohorts::COMPONENT, $cohort->component);
        $this->assertSame(lookcohorts::idnumber((int) $harbour->id), $cohort->idnumber);
        $this->assertEquals(\context_system::instance()->id, $cohort->contextid);
        $this->assertSame('Look: Harbour & Co', $cohort->name, 'The name carries the plain title.');
    }

    /**
     * A renamed flavour renames its cohort on the next provisioning.
     *
     * @return void
     */
    public function test_a_renamed_flavour_renames_its_cohort(): void {
        global $DB;

        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $DB->set_field('theme_boost_union_flavours', 'title', 'Quay', ['id' => $harbour->id]);
        \cache::make('theme_dimensions', 'flavourmenu')->purge();

        lookcohorts::provision();

        $this->assertSame('Look: Quay', $this->cohort($harbour)->name);
    }

    /**
     * A person is in the cohort of the look they chose and in no other, and the site default is in none.
     *
     * @return void
     */
    public function test_a_person_is_in_the_cohort_of_their_look_alone(): void {
        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        $user = $this->getDataGenerator()->create_user();

        $this->choose_as($user, (int) $harbour->id);
        $this->assertSame([(int) $user->id], $this->members($harbour));
        $this->assertSame([], $this->members($meadow));

        $this->choose_as($user, (int) $meadow->id);
        $this->assertSame([], $this->members($harbour), 'Switching leaves the old look.');
        $this->assertSame([(int) $user->id], $this->members($meadow));

        $this->choose_as($user, flavourchoice::SITE_DEFAULT);
        $this->assertSame([], $this->members($harbour));
        $this->assertSame([], $this->members($meadow), 'The site default is in no look cohort.');
    }

    /**
     * A look offered without the settings page ever saving gets its cohort at the first choice of it.
     *
     * @return void
     */
    public function test_choosing_a_look_without_a_cohort_makes_it(): void {
        $harbour = $this->flavour('Harbour');
        set_config(flavourchoice::SETTING, (string) $harbour->id, 'theme_dimensions');
        $this->assertSame([], lookcohorts::cohorts(), 'Precondition: nothing was provisioned.');
        $user = $this->getDataGenerator()->create_user();

        $this->choose_as($user, (int) $harbour->id);

        $this->assertSame([(int) $user->id], $this->members($harbour));
    }

    /**
     * A refused choice touches no cohort: the guest, and a flavour that is not offered.
     *
     * @return void
     */
    public function test_a_refused_choice_joins_nothing(): void {
        global $DB;

        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour);

        $this->setGuestUser();
        $this->assertFalse(flavourchoice::choose((int) $harbour->id));
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertFalse(flavourchoice::choose((int) $meadow->id));

        $this->assertSame(0, $DB->count_records('cohort_members'));
        $this->assertSame([(int) $harbour->id], array_keys(lookcohorts::cohorts()), 'An unoffered look gets no cohort.');
    }

    /**
     * A stored choice of a look the setting no longer offers moves the person into no cohort.
     *
     * The list changed without a save of the settings page, so the old look's cohort still exists; the
     * control is the same person settled while that look is still offered.
     *
     * @return void
     */
    public function test_a_stored_choice_of_a_look_no_longer_offered_joins_nothing(): void {
        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(flavourchoice::PREFERENCE, $meadow->id, $user);

        lookcohorts::sync_user((int) $user->id);
        $this->assertSame([(int) $user->id], $this->members($meadow), 'Control: an offered look is joined.');

        set_config(flavourchoice::SETTING, (string) $harbour->id, 'theme_dimensions');
        lookcohorts::sync_user((int) $user->id);
        $this->assertSame([], $this->members($meadow));
        $this->assertSame([], $this->members($harbour));
    }

    /**
     * A look taken off the list keeps its cohort, emptied; the looks still offered keep their members.
     *
     * @return void
     */
    public function test_a_look_taken_off_the_list_keeps_its_cohort_emptied(): void {
        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $this->choose_as($one, (int) $harbour->id);
        $this->choose_as($two, (int) $meadow->id);
        $cohortid = (int) $this->cohort($harbour)->id;

        $this->offer($meadow);

        $this->assertSame($cohortid, (int) $this->cohort($harbour)->id, 'The cohort is kept, not made again.');
        $this->assertSame([], $this->members($harbour));
        $this->assertSame([(int) $two->id], $this->members($meadow), 'Control: a look still offered keeps its members.');
    }

    /**
     * The adhoc task empties a look cohort, and refuses a cohort that is not one.
     *
     * @return void
     */
    public function test_the_adhoc_task_empties_only_a_look_cohort(): void {
        global $DB;

        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->choose_as($user, (int) $harbour->id);
        $other = $this->getDataGenerator()->create_cohort();
        cohort_add_member($other->id, $user->id);
        // The look is taken off the list after the task was queued, with no sync: the members stay.
        set_config(flavourchoice::SETTING, '', 'theme_dimensions');

        foreach ([(int) $other->id, (int) $this->cohort($harbour)->id] as $cohortid) {
            $task = new empty_look_cohort();
            $task->set_custom_data(['cohortid' => $cohortid]);
            \core\task\manager::queue_adhoc_task($task);
        }
        $this->expectOutputRegex('/(?=.*not a look cohort)(?=.*emptied cohort)/s');
        $this->runAdhocTasks(empty_look_cohort::class);

        $this->assertSame([], $this->members($harbour));
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $other->id, 'userid' => $user->id]));
    }

    /**
     * A queued emptying does nothing when the look is offered again before the task runs.
     *
     * The control is the same task for a look still off the list, which empties the cohort.
     *
     * @return void
     */
    public function test_the_adhoc_task_keeps_the_members_of_a_look_offered_again(): void {
        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();
        $this->choose_as($one, (int) $harbour->id);
        $this->choose_as($two, (int) $meadow->id);

        // Both looks leave the list with no sync, as when the emptying was queued for each.
        set_config(flavourchoice::SETTING, '', 'theme_dimensions');
        foreach ([$harbour, $meadow] as $flavour) {
            $task = new empty_look_cohort();
            $task->set_custom_data(['cohortid' => (int) $this->cohort($flavour)->id]);
            \core\task\manager::queue_adhoc_task($task);
        }
        // The administrator offers Harbour again before cron runs the tasks.
        set_config(flavourchoice::SETTING, (string) $harbour->id, 'theme_dimensions');
        $this->expectOutputRegex('/emptied cohort/');
        $this->runAdhocTasks(empty_look_cohort::class);

        $this->assertSame([(int) $one->id], $this->members($harbour), 'An offered look keeps its members.');
        $this->assertSame([], $this->members($meadow), 'Control: a look still off the list is emptied.');
    }

    /**
     * An idnumber another owner already holds is reported and never taken over.
     *
     * @return void
     */
    public function test_an_idnumber_held_by_another_owner_is_not_claimed(): void {
        global $DB;

        $harbour = $this->flavour('Harbour');
        set_config(flavourchoice::SETTING, (string) $harbour->id, 'theme_dimensions');
        $squatter = $this->getDataGenerator()->create_cohort(['idnumber' => lookcohorts::idnumber((int) $harbour->id)]);

        $this->assertSame([(int) $harbour->id], lookcohorts::provision());

        $this->assertSame([], lookcohorts::cohorts());
        $this->assertSame('', $DB->get_field('cohort', 'component', ['id' => $squatter->id]));
        $user = $this->getDataGenerator()->create_user();
        $this->choose_as($user, (int) $harbour->id);
        $this->assertFalse($DB->record_exists('cohort_members', ['cohortid' => $squatter->id]), 'Nobody is put in it.');
    }

    /**
     * Without the lock nothing is written: provisioning makes no cohort and a choice moves no member.
     *
     * The control is the same calls with the site's own lock factory, which write.
     *
     * @return void
     */
    public function test_nothing_is_written_without_the_lock(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/theme/dimensions/tests/fixtures/refusing_lock_factory.php');
        $harbour = $this->flavour('Harbour');
        set_config(flavourchoice::SETTING, (string) $harbour->id, 'theme_dimensions');
        $user = $this->getDataGenerator()->create_user();

        $CFG->lock_factory = \theme_dimensions\tests\refusing_lock_factory::class;
        $this->assertSame([], lookcohorts::provision());
        $this->assertSame(0, $DB->count_records('cohort'), 'No cohort is made outside the lock.');
        $this->choose_as($user, (int) $harbour->id);
        $this->assertSame(0, $DB->count_records('cohort_members'));

        unset($CFG->lock_factory);
        lookcohorts::sync_user((int) $user->id);
        $this->assertSame([(int) $user->id], $this->members($harbour), 'Control: with the lock, both are written.');
    }

    /**
     * A smart menu restricted by a look shows to the people who chose it, and stops when they choose another.
     *
     * @return void
     */
    public function test_a_smart_menu_restricted_by_a_look_follows_the_choice(): void {
        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        $menu = $this->menu($this->cohort($harbour));
        $user = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->assertFalse($this->shown($menu), 'Control: nobody who chose nothing sees it.');

        $this->choose_as($user, (int) $harbour->id);
        $this->assertTrue($this->shown($menu));

        $this->choose_as($user, (int) $meadow->id);
        $this->assertFalse($this->shown($menu), 'The next build after the switch leaves it out.');
    }

    /**
     * A look cohort deleted through core's web service hides what it restricted; another cohort's deletion does not.
     *
     * Boost Union strips the deleted cohort from every rule, after the theme hid the menu: the rule then
     * names nothing and would show the menu to everyone. The ordinary cohort is the control: its menu
     * is left as Boost Union leaves it.
     *
     * @return void
     */
    public function test_deleting_a_look_cohort_hides_the_menus_and_items_it_restricted(): void {
        global $DB;

        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $look = $this->cohort($harbour);
        $other = $this->getDataGenerator()->create_cohort();
        $generator = $this->getDataGenerator()->get_plugin_generator('theme_boost_union');
        $lookmenu = $this->menu($look);
        $othermenu = $this->menu($other);
        $open = $this->menu();
        $item = $generator->create_smartmenu_item(['menu' => $open->id, 'url' => '/my/', 'cohorts' => [(string) $look->id]]);

        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();
        foreach ([$look, $other] as $cohort) {
            $response = \core_external\external_api::call_external_function(
                'core_cohort_delete_cohorts',
                ['cohortids' => [(int) $cohort->id]]
            );
            $this->assertFalse($response['error'], 'Precondition: core deletes a stamped cohort through its service.');
        }

        $this->assertSame('[]', $DB->get_field('theme_boost_union_menus', 'cohorts', ['id' => $lookmenu->id]), 'Boost Union ran.');
        $this->assertSame(0, (int) $DB->get_field('theme_boost_union_menus', 'visible', ['id' => $lookmenu->id]));
        $this->assertSame(0, (int) $DB->get_field('theme_boost_union_menuitems', 'visible', ['id' => $item->id]));
        $this->assertSame(1, (int) $DB->get_field('theme_boost_union_menus', 'visible', ['id' => $othermenu->id]));
        $this->assertSame(1, (int) $DB->get_field('theme_boost_union_menus', 'visible', ['id' => $open->id]));
        $this->assertSame(['menus' => [(int) $lookmenu->id], 'items' => [(int) $item->id]], lookcohorts::hidden());

        $recommendation = new lookcohorts_hidden();
        $this->assertSame(recommendation::WARNING, $recommendation->get_status());
        smartmenu::instance((int) $lookmenu->id)->update_visible(true);
        $DB->set_field('theme_boost_union_menuitems', 'visible', 1, ['id' => $item->id]);
        $this->assertSame(recommendation::OK, (new lookcohorts_hidden())->get_status(), 'Shown again, the warning goes.');
        $this->assertSame(['menus' => [], 'items' => []], lookcohorts::hidden());
    }

    /**
     * The reconcile task puts every look cohort back in step with the preferences, and makes a deleted one again.
     *
     * @return void
     */
    public function test_the_reconcile_task_repairs_membership_from_the_preferences(): void {
        global $CFG, $DB;

        $harbour = $this->flavour('Harbour');
        $meadow = $this->flavour('Meadow');
        $this->offer($harbour, $meadow);
        $chose = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $silent = $this->getDataGenerator()->create_user();
        $gone = $this->getDataGenerator()->create_user();
        $this->choose_as($chose, (int) $harbour->id);
        cohort_remove_member($this->cohort($harbour)->id, $chose->id);
        cohort_add_member($this->cohort($harbour)->id, $stranger->id);
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id, $silent);
        delete_user($gone);
        // Stored straight into the table: core keeps no preference of a deleted account or of the guest.
        foreach ([(int) $gone->id, (int) $CFG->siteguest] as $userid) {
            $DB->insert_record('user_preferences', (object) [
                'userid' => $userid,
                'name' => flavourchoice::PREFERENCE,
                'value' => (string) $harbour->id,
            ]);
        }
        cohort_delete_cohort($this->cohort($meadow));
        $this->assertArrayNotHasKey((int) $meadow->id, lookcohorts::cohorts(), 'Precondition: the cohort is gone.');

        (new reconcile_look_cohorts())->execute();

        $expected = [(int) $chose->id, (int) $silent->id];
        sort($expected);
        $this->assertSame($expected, $this->members($harbour));
        $this->assertSame([], $this->members($meadow), 'The deleted cohort is made again.');
    }

    /**
     * Uninstalling releases the cohorts with their members: ordinary cohorts, and every menu keeps its audience.
     *
     * @return void
     */
    public function test_uninstall_releases_the_cohorts(): void {
        global $CFG, $DB;

        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->choose_as($user, (int) $harbour->id);
        $cohort = $this->cohort($harbour);

        require_once($CFG->dirroot . '/theme/dimensions/db/uninstall.php');
        xmldb_theme_dimensions_uninstall();

        $released = $DB->get_record('cohort', ['id' => $cohort->id], '*', MUST_EXIST);
        $this->assertSame('', $released->component);
        $this->assertTrue($DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $user->id]));
        $this->assertSame([], lookcohorts::cohorts());
    }
}
