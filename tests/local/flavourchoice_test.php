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
 * Theme Dimensions - Tests for the chosen look
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use theme_dimensions\local\hook\output\before_http_headers;

/**
 * Tests for the look a person chose and how it reaches Boost Union's flavour cache.
 *
 * Most tests read Boost Union's session cache after flavourchoice::prime(), because that entry is the
 * whole contract: Boost Union's resolver answers every consumer from it. The two tests that render a
 * real page header go through the resolver itself and run in a process of their own, because the
 * resolver keeps its first answer in a function static for the life of the process.
 *
 * The first prime of a session purges Boost Union's cache (the marker has nothing to compare with),
 * so a test that seeds that cache with what Boost Union "resolved" primes once first.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(flavourchoice::class)]
#[CoversClass(before_http_headers::class)]
final class flavourchoice_test extends \advanced_testcase {
    /**
     * A Boost Union flavour, made by Boost Union's own generator.
     *
     * @param string $title The flavour's title.
     * @param array $data Further fields for the generator.
     * @return \stdClass The flavour's record as stored.
     */
    private function flavour(string $title, array $data = []): \stdClass {
        global $DB;

        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')
            ->create_flavour(['title' => $title] + $data);

        return $DB->get_record('theme_boost_union_flavours', ['id' => $flavour->id], '*', MUST_EXIST);
    }

    /**
     * Offer exactly these flavours in the setting.
     *
     * @param \stdClass ...$flavours The flavours.
     * @return void
     */
    private function offer(\stdClass ...$flavours): void {
        set_config(flavourchoice::SETTING, implode(',', array_map(fn($f) => $f->id, $flavours)), 'theme_dimensions');
    }

    /**
     * A page as a header would draw it: its category (or none), its url and its theme.
     *
     * @param int|null $categoryid The page's category, or null for a page outside any category.
     * @param string $theme The theme the page is drawn in.
     * @param string $url The page's url.
     * @return \moodle_page
     */
    private function page(?int $categoryid, string $theme = 'dimensions', string $url = '/my/'): \moodle_page {
        global $SITE;

        $page = new \moodle_page();
        if ($categoryid === null) {
            // A real page has its course set before its header, the site's when it has no other.
            $page->set_course($SITE);
            $page->set_context(\context_system::instance());
        } else {
            $page->set_category_by_id($categoryid);
        }
        $page->set_url($url);
        $page->force_theme($theme);

        return $page;
    }

    /**
     * Boost Union's session cache of flavours, the one its resolver reads first.
     *
     * @return \cache
     */
    private function boost_union_cache(): \cache {
        return \cache::make('theme_boost_union', 'flavours');
    }

    /**
     * The id Boost Union's cache holds for a category key, or a marker for no entry and for null.
     *
     * @param int $key The page's category id, 0 outside a category.
     * @return int|string The flavour id, 'none' for no entry, 'null' for an entry saying no flavour applies.
     */
    private function cached(int $key): int|string {
        $entry = $this->boost_union_cache()->get($key);
        if ($entry === false) {
            return 'none';
        }

        return $entry === null ? 'null' : (int) $entry->id;
    }

    /**
     * A chosen flavour is written under the page's category id, and only there.
     *
     * @return void
     */
    public function test_a_chosen_flavour_is_written_under_the_pages_category_key(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);

        flavourchoice::prime($this->page((int) $category->id));

        $this->assertSame((int) $harbour->id, $this->cached((int) $category->id));
        $this->assertSame('none', $this->cached(0), 'A page outside any category is another key.');

        flavourchoice::prime($this->page(null));
        $this->assertSame((int) $harbour->id, $this->cached(0), 'Outside a category the key is 0.');
    }

    /**
     * The choice replaces whatever Boost Union had cached for the category, its own rules' answer included.
     *
     * @return void
     */
    public function test_the_choice_wins_over_what_boost_union_resolved(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        flavourchoice::prime($this->page(null));
        $this->boost_union_cache()->set((int) $category->id, $campus);

        flavourchoice::prime($this->page((int) $category->id));

        $this->assertSame((int) $harbour->id, $this->cached((int) $category->id));
    }

    /**
     * The site default writes nothing: Boost Union's own answer, a flavour or none, stays.
     *
     * @return void
     */
    public function test_the_site_default_leaves_boost_unions_answer_alone(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_category();
        $second = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        $cache = $this->boost_union_cache();
        flavourchoice::prime($this->page(null));

        // Nothing chosen at all, then the site default chosen explicitly.
        foreach ([null, flavourchoice::SITE_DEFAULT] as $choice) {
            if ($choice !== null) {
                set_user_preference(flavourchoice::PREFERENCE, $choice);
            }
            $cache->set((int) $first->id, $campus);
            $cache->set((int) $second->id, null);
            flavourchoice::prime($this->page((int) $first->id));
            flavourchoice::prime($this->page((int) $second->id));
            $this->assertSame((int) $campus->id, $this->cached((int) $first->id));
            $this->assertSame('null', $this->cached((int) $second->id));
        }

        // Control: a real choice does reach the same keys.
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        flavourchoice::prime($this->page((int) $first->id));
        $this->assertSame((int) $harbour->id, $this->cached((int) $first->id));
    }

    /**
     * A flavour the setting no longer offers, or one deleted since, is the site default.
     *
     * @return void
     */
    public function test_a_flavour_off_the_list_or_deleted_is_the_site_default(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);

        $this->offer($campus);
        $this->assertSame(flavourchoice::SITE_DEFAULT, flavourchoice::chosen_id());
        flavourchoice::prime($this->page(null));
        $this->boost_union_cache()->set((int) $category->id, $campus);
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame((int) $campus->id, $this->cached((int) $category->id), 'Off the list: nothing written.');

        // Control: the same choice on the list is written.
        $this->offer($harbour, $campus);
        $this->assertSame((int) $harbour->id, flavourchoice::chosen_id());
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame((int) $harbour->id, $this->cached((int) $category->id));

        // Deleted while still listed and still chosen: Boost Union's event purges both caches, and the
        // record read again is gone, so nothing is written and Boost Union resolves the page afresh.
        $DB->delete_records('theme_boost_union_flavours', ['id' => $harbour->id]);
        \core_cache\helper::purge_by_event('theme_boost_union_flavours_deleted');
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame('none', $this->cached((int) $category->id), 'Deleted: nothing written.');
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame('none', $this->cached((int) $category->id), 'And not on the next page either.');
    }

    /**
     * A visitor and the guest are never written for; a real account is, as the control.
     *
     * @return void
     */
    public function test_visitors_and_the_guest_are_left_to_boost_union(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id, $user);

        $this->setGuestUser();
        // The guest's preferences live in memory only, but they exist; the choice must still be refused.
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        $this->assertSame(flavourchoice::SITE_DEFAULT, flavourchoice::chosen_id());
        $this->boost_union_cache()->set((int) $category->id, $campus);
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame((int) $campus->id, $this->cached((int) $category->id), 'The guest.');

        // Session caches are emptied when the session's user changes, so the seed is laid again.
        $this->setUser(null);
        $this->boost_union_cache()->set((int) $category->id, $campus);
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame((int) $campus->id, $this->cached((int) $category->id), 'A visitor.');

        $this->setUser($user);
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame((int) $harbour->id, $this->cached((int) $category->id), 'Control: the account itself.');
    }

    /**
     * A page drawn in another theme, and Boost Union's own flavour preview, are left alone.
     *
     * @return void
     */
    public function test_another_theme_and_the_flavour_preview_are_left_alone(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        $this->boost_union_cache()->set((int) $category->id, $campus);

        flavourchoice::prime($this->page((int) $category->id, 'boost'));
        $this->assertSame((int) $campus->id, $this->cached((int) $category->id), 'A page drawn in Boost.');

        flavourchoice::prime(
            $this->page((int) $category->id, 'dimensions', '/theme/boost_union/flavours/preview.php?id=' . $campus->id)
        );
        $this->assertSame((int) $campus->id, $this->cached((int) $category->id), 'The flavour preview.');

        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame((int) $harbour->id, $this->cached((int) $category->id), 'Control: an ordinary page.');
    }

    /**
     * A choice made in another session purges every category this session was primed for.
     *
     * Each session keeps its own copy of Boost Union's cache, so another session's change is seen
     * only through the preference, which the marker compares on every page.
     *
     * @return void
     */
    public function test_a_new_choice_purges_what_the_session_was_primed_with(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_category();
        $second = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour, $campus);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        flavourchoice::prime($this->page((int) $first->id));
        flavourchoice::prime($this->page((int) $second->id));

        // Control: the same choice on the next page purges nothing.
        flavourchoice::prime($this->page((int) $first->id));
        $this->assertSame((int) $harbour->id, $this->cached((int) $second->id));

        set_user_preference(flavourchoice::PREFERENCE, $campus->id);
        flavourchoice::prime($this->page((int) $first->id));
        $this->assertSame((int) $campus->id, $this->cached((int) $first->id));
        $this->assertSame('none', $this->cached((int) $second->id), 'The other category is resolved again.');
    }

    /**
     * A flavour taken off the list mid-session purges what the session was primed with.
     *
     * @return void
     */
    public function test_a_flavour_leaving_the_list_purges_the_session(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_category();
        $second = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour, $campus);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        flavourchoice::prime($this->page((int) $first->id));
        flavourchoice::prime($this->page((int) $second->id));

        $this->offer($campus);
        flavourchoice::prime($this->page((int) $first->id));

        $this->assertSame('none', $this->cached((int) $first->id));
        $this->assertSame('none', $this->cached((int) $second->id));
    }

    /**
     * An edited flavour is read again once Boost Union's edit event purges the caches.
     *
     * @return void
     */
    public function test_an_edited_flavour_is_read_again(): void {
        global $DB;
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        flavourchoice::prime($this->page((int) $category->id));

        $DB->set_field('theme_boost_union_flavours', 'title', 'Quay', ['id' => $harbour->id]);
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame(
            'Harbour',
            $this->boost_union_cache()->get((int) $category->id)->title,
            'Precondition: the session keeps the record it read until something purges it.'
        );

        \core_cache\helper::purge_by_event('theme_boost_union_flavours_edited');
        flavourchoice::prime($this->page((int) $category->id));
        $this->assertSame('Quay', $this->boost_union_cache()->get((int) $category->id)->title);
    }

    /**
     * Boost Union's flavour purge flag is honoured once and cleared, so it cannot erase the choice later.
     *
     * @return void
     */
    public function test_boost_unions_purge_flag_is_honoured_once_and_cleared(): void {
        $this->resetAfterTest();
        $first = $this->getDataGenerator()->create_category();
        $second = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        flavourchoice::prime($this->page((int) $first->id));
        $this->boost_union_cache()->set((int) $second->id, $campus);
        set_user_preference('theme_boost_union_flavours_purgesessioncache', true);

        flavourchoice::prime($this->page((int) $first->id));

        $this->assertFalse((bool) get_user_preferences('theme_boost_union_flavours_purgesessioncache', false));
        $this->assertSame('none', $this->cached((int) $second->id), 'The purge the flag asked for was done.');
        $this->assertSame((int) $harbour->id, $this->cached((int) $first->id));
    }

    /**
     * Boost Union still never clears its flavour purge flag; when it does, absorb_purge_flag() goes.
     *
     * Boost Union's code is read for every use of the preference's name: it may be set and read, and the
     * day a call clears it this test reddens, saying the theme's own clearing is no longer needed.
     *
     * @return void
     */
    public function test_boost_union_still_never_clears_its_flavour_purge_flag(): void {
        global $CFG;

        $name = 'theme_boost_union_flavours_purgesessioncache';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($CFG->dirroot . '/theme/boost_union'));
        $sets = 0;
        $reads = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/tests/')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            if (!str_contains($source, $name)) {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                "~unset_user_preference\\(\\s*'{$name}'|set_user_preference\\(\\s*'{$name}'\\s*,\\s*(false|0|'')~",
                $source,
                'Boost Union clears its flag now: drop flavourchoice::absorb_purge_flag() and this test.'
            );
            $sets += preg_match_all("~set_user_preference\\(\\s*'{$name}'\\s*,\\s*true~", $source);
            $reads += preg_match_all("~get_user_preferences\\(\\s*'{$name}'~", $source);
        }
        $this->assertGreaterThan(0, $sets, 'Precondition: Boost Union sets the flag.');
        $this->assertGreaterThan(0, $reads, 'Precondition: Boost Union reads the flag.');
    }

    /**
     * The setting's choices are every flavour in Boost Union's order, and its stored list is parsed to ids.
     *
     * @return void
     */
    public function test_the_choices_follow_boost_unions_order(): void {
        global $DB;
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus & Co');
        $DB->set_field('theme_boost_union_flavours', 'sort', 2, ['id' => $harbour->id]);
        $DB->set_field('theme_boost_union_flavours', 'sort', 1, ['id' => $campus->id]);

        $this->assertSame(
            [(int) $campus->id => 'Campus &amp; Co', (int) $harbour->id => 'Harbour'],
            flavourchoice::choices()
        );

        set_config(flavourchoice::SETTING, '', 'theme_dimensions');
        $this->assertSame([], flavourchoice::selectable_ids());
        $this->offer($harbour, $campus);
        $this->assertSame([(int) $harbour->id, (int) $campus->id], flavourchoice::selectable_ids());
    }

    /**
     * Core's preference service cannot write the look, which only choose() records; it still writes the theme's others.
     *
     * The control is the focus mode width, a preference of the same theme through the same service call.
     *
     * @return void
     */
    public function test_core_preference_service_cannot_write_the_look(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $_POST['sesskey'] = sesskey();

        $response = \core_external\external_api::call_external_function('core_user_set_user_preferences', [
            'preferences' => [
                ['name' => flavourchoice::PREFERENCE, 'value' => (string) $harbour->id, 'userid' => (int) $user->id],
                ['name' => focusmode::PREFERENCE_EXPANDED, 'value' => '1', 'userid' => (int) $user->id],
            ],
        ]);

        $this->assertFalse($response['error']);
        $this->assertSame(
            [focusmode::PREFERENCE_EXPANDED],
            array_column($response['data']['saved'], 'name'),
            'Control: the same call saves the theme\'s other preference.'
        );
        $this->assertFalse($DB->record_exists('user_preferences', ['userid' => $user->id, 'name' => flavourchoice::PREFERENCE]));
        $this->assertTrue(flavourchoice::choose((int) $harbour->id), 'choose() still records it.');
        $this->assertSame((string) $harbour->id, get_user_preferences(flavourchoice::PREFERENCE, null, $user));
    }

    /**
     * choose() records the site default or an offered flavour, refuses anything else, and forgets the session's prime.
     *
     * @return void
     */
    public function test_choose_records_only_an_offered_flavour(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        flavourchoice::prime($this->page(null));

        $this->assertTrue(flavourchoice::choose((int) $harbour->id));
        $this->assertSame((int) $harbour->id, (int) get_user_preferences(flavourchoice::PREFERENCE));
        $this->assertFalse(\cache::make('theme_dimensions', 'flavourchoice')->get('choice'), 'The prime is forgotten.');

        $this->assertFalse(flavourchoice::choose((int) $campus->id), 'A flavour the setting does not offer.');
        $this->assertFalse(flavourchoice::choose(987654), 'No such flavour.');
        $this->assertSame((int) $harbour->id, (int) get_user_preferences(flavourchoice::PREFERENCE), 'Nothing was written.');

        $this->assertTrue(flavourchoice::choose(flavourchoice::SITE_DEFAULT));
        $this->assertSame(flavourchoice::SITE_DEFAULT, (int) get_user_preferences(flavourchoice::PREFERENCE));

        $this->setGuestUser();
        $this->assertFalse(flavourchoice::choose((int) $harbour->id), 'The guest chooses nothing.');
    }

    /**
     * offered() lists the offered flavours that exist, plain, in Boost Union's order, and follows a setting change.
     *
     * @return void
     */
    public function test_offered_flavours_are_plain_ordered_and_follow_the_setting(): void {
        global $DB;
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour & Co');
        $campus = $this->flavour('Campus');
        $quay = $this->flavour('Quay');
        // Every flavour gets a sort, as Boost Union's own form gives one: the generator leaves it null, and
        // MariaDB sorts a null first where PostgreSQL sorts it last.
        $DB->set_field('theme_boost_union_flavours', 'sort', 2, ['id' => $harbour->id]);
        $DB->set_field('theme_boost_union_flavours', 'sort', 1, ['id' => $campus->id]);
        $DB->set_field('theme_boost_union_flavours', 'sort', 3, ['id' => $quay->id]);
        $this->offer($harbour, $campus);

        $this->assertSame([(int) $campus->id => 'Campus', (int) $harbour->id => 'Harbour & Co'], flavourchoice::offered());

        // A setting change is a new key, so the cached list of the old one is never read.
        $this->offer($harbour, $quay);
        $this->assertSame([(int) $harbour->id => 'Harbour & Co', (int) $quay->id => 'Quay'], flavourchoice::offered());

        // A deleted flavour drops out once Boost Union's event has emptied the cache.
        $DB->delete_records('theme_boost_union_flavours', ['id' => $quay->id]);
        $this->assertArrayHasKey((int) $quay->id, flavourchoice::offered(), 'Precondition: the list is cached.');
        \core_cache\helper::purge_by_event('theme_boost_union_flavours_deleted');
        $this->assertSame([(int) $harbour->id => 'Harbour & Co'], flavourchoice::offered());

        set_config(flavourchoice::SETTING, '', 'theme_dimensions');
        $this->assertSame([], flavourchoice::offered());
    }

    /**
     * menu() offers the site default first, marks the choice in force, and is nothing for visitors or an empty list.
     *
     * @return void
     */
    public function test_the_menu_marks_the_choice_in_force(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $return = new \moodle_url('/course/index.php', ['categoryid' => 7]);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertNull(flavourchoice::menu($return), 'Nothing offered.');

        $this->offer($harbour, $campus);
        $menu = flavourchoice::menu($return);
        $this->assertSame([flavourchoice::SITE_DEFAULT, (int) $harbour->id, (int) $campus->id], array_column($menu['items'], 'id'));
        $this->assertSame(get_string('flavoursitedefault', 'theme_dimensions'), $menu['current']);
        $this->assertSame([true, false, false], array_column($menu['items'], 'iscurrent'));
        $url = $menu['items'][1]['url'];
        $this->assertStringEndsWith('/theme/dimensions/flavour.php', $url->get_path(false));
        $this->assertSame((string) $harbour->id, $url->get_param('flavour'));
        $this->assertSame('/course/index.php?categoryid=7', $url->get_param('returnurl'));

        set_user_preference(flavourchoice::PREFERENCE, $campus->id);
        $menu = flavourchoice::menu($return);
        $this->assertSame('Campus', $menu['current']);
        $this->assertSame([false, false, true], array_column($menu['items'], 'iscurrent'));

        // A choice the setting no longer offers reads as the site default here too.
        $this->offer($harbour);
        $this->assertSame([true, false], array_column(flavourchoice::menu($return)['items'], 'iscurrent'));

        $this->setGuestUser();
        $this->assertNull(flavourchoice::menu($return), 'The guest.');
        $this->setUser(null);
        $this->assertNull(flavourchoice::menu($return), 'A visitor.');
    }

    /**
     * Render the real header of a page in a category, for the current user, through Boost Union's layout.
     *
     * @param int $categoryid The page's category.
     * @return string The rendered header.
     */
    private function category_page_header(int $categoryid): string {
        global $PAGE, $OUTPUT;

        $PAGE->set_category_by_id($categoryid);
        $PAGE->set_url(new \moodle_url('/course/index.php', ['categoryid' => $categoryid]));
        $PAGE->set_pagelayout('standard');
        $PAGE->set_title('Category');
        $PAGE->set_heading('Category');
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        return $OUTPUT->header();
    }

    /**
     * Through a real page header, the chosen flavour is the one Boost Union applies, over a category flavour.
     *
     * Boost Union's resolver memoises its answer for the process, so this runs in a process of its own;
     * its twin below is the control.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function test_a_real_page_follows_the_choice_over_a_category_flavour(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category(['idnumber' => 'LOOKCAT']);
        $campus = $this->flavour('Campus', ['applytocategories_ids' => 'LOOKCAT']);
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);

        $html = $this->category_page_header((int) $category->id);

        $this->assertMatchesRegularExpression('~<body[^>]*class="[^"]*\bflavour-' . $harbour->id . '\b~', $html);
        $this->assertDoesNotMatchRegularExpression('~<body[^>]*class="[^"]*\bflavour-' . $campus->id . '\b~', $html);
    }

    /**
     * Control of the test above: with the site default, the same page gets the category's flavour.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function test_a_real_page_with_the_site_default_keeps_the_category_flavour(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category(['idnumber' => 'LOOKCAT']);
        $campus = $this->flavour('Campus', ['applytocategories_ids' => 'LOOKCAT']);
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $this->setUser($this->getDataGenerator()->create_user());
        set_user_preference(flavourchoice::PREFERENCE, flavourchoice::SITE_DEFAULT);

        $html = $this->category_page_header((int) $category->id);

        $this->assertMatchesRegularExpression('~<body[^>]*class="[^"]*\bflavour-' . $campus->id . '\b~', $html);
        $this->assertDoesNotMatchRegularExpression('~<body[^>]*class="[^"]*\bflavour-' . $harbour->id . '\b~', $html);
    }
}
