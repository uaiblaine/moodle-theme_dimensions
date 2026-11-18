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
 * Theme Dimensions - Tests for the theme's copy of core's iCal feed
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\source\core_events;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The feed withholds what the theme's calendar withholds, and core's read of the same request does not.
 *
 * serve() reads the request, sends headers and ends it, so its parts are driven here: the token check, the feed of an
 * account and the withholding. The control of every withholding is core's own read with the arguments core's script
 * hands it for `preset_what=all` (calendar_get_legacy_events() over the user, the groups, the site and every category),
 * which still holds the event the theme leaves out.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(ical::class)]
#[CoversClass(core_events::class)]
final class ical_test extends \advanced_testcase {
    use fixture;

    /**
     * A student in no course, and an event in a listed and in an unlisted category, a few days ahead.
     *
     * @return array [student, event ids by name]
     */
    private function seed(): array {
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = $generator->create_category();
        $ids = [];
        foreach (['listed', 'unlisted'] as $name) {
            $category = $generator->create_category(['parent' => $top->id]);
            $ids[$name] = (int) $generator->create_event([
                'name' => "Fair of the {$name} category",
                'eventtype' => 'category',
                'categoryid' => $category->id,
                'courseid' => 0,
                'timestart' => time() + 3 * DAYSECS,
            ])->id;
            $ids[$name . 'category'] = (int) $category->id;
        }
        $this->set_category_state($ids['unlistedcategory'], category_discoverability::STATE_UNLISTED);
        $student = $generator->create_user();
        $this->as_viewer($student);
        return [$student, $ids];
    }

    /**
     * The ids core's script reads for `preset_what=all` over the next sixty days: the control.
     *
     * @param \stdClass $user The account.
     * @return int[]
     */
    private static function core_ids(\stdClass $user): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $events = calendar_get_legacy_events(
            time() - 432000,
            time() + 5184000,
            $user->id,
            false,
            [SITEID],
            false,
            true,
            true,
            0
        );
        return array_map('intval', array_column($events, 'id'));
    }

    /**
     * The event ids a serialised feed carries, read from its UID lines.
     *
     * @param string $feed The iCalendar text.
     * @return int[]
     */
    private static function feed_ids(string $feed): array {
        preg_match_all('~^UID:(\d+)@~m', $feed, $found);
        return array_map('intval', $found[1]);
    }

    /**
     * A withheld category's event is in core's read and not in the theme's feed; the admitted one is in both.
     *
     * @return void
     */
    public function test_a_withheld_categorys_event_is_left_out_of_the_feed(): void {
        $this->resetAfterTest();
        [$student, $ids] = $this->seed();

        $core = self::core_ids($student);
        $this->assertContains($ids['unlisted'], $core, 'The control: core\'s feed hands out the withheld category\'s event.');
        $this->assertContains($ids['listed'], $core);

        $feed = (string) ical::feed($student, (int) $student->id, 'all', 'recentupcoming', 0);
        $this->assertStringStartsWith('BEGIN:VCALENDAR', $feed);
        $this->assertContains($ids['listed'], self::feed_ids($feed), 'The admitted category\'s event stays.');
        $this->assertNotContains($ids['unlisted'], self::feed_ids($feed));
        $this->assertStringContainsString('SUMMARY:Fair of the listed category', $feed);
        $this->assertStringNotContainsString('unlisted', $feed);

        $categories = (string) ical::feed($student, (int) $student->id, 'categories', 'recentupcoming', 0);
        $this->assertSame([$ids['listed']], self::feed_ids($categories), 'The categories set withholds the same.');

        $this->set_category_state($ids['unlistedcategory'], category_discoverability::STATE_DEFAULT);
        $this->as_viewer($student);
        $again = self::feed_ids((string) ical::feed($student, (int) $student->id, 'all', 'recentupcoming', 0));
        $this->assertContains($ids['unlisted'], $again, 'Listed again, the event is back.');
    }

    /**
     * A hidden category's event is left out for a viewer who may not see the category, in the feed and in the filter.
     *
     * Core's own feed already drops it (its event factory asks the requesting user), so the filter is asserted on its
     * own too: the page applies core's visibility beside the unlisted rule ({@see core_events::shown_categories()}), and
     * the feed must not depend on core's factory for it. The controls: an admin and a holder of
     * moodle/category:viewhiddencategories keep the event.
     *
     * @return void
     */
    public function test_a_hidden_categorys_event_is_left_out_of_the_feed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $hidden = $generator->create_category(['visible' => 0]);
        $event = (int) $generator->create_event([
            'name' => 'Fair of the hidden category',
            'eventtype' => 'category',
            'categoryid' => $hidden->id,
            'courseid' => 0,
            'timestart' => time() + 3 * DAYSECS,
        ])->id;
        $student = $generator->create_user();
        $manager = $generator->create_user();
        $role = (int) $generator->create_role();
        $systemid = \core\context\system::instance()->id;
        assign_capability('moodle/category:viewhiddencategories', CAP_ALLOW, $role, $systemid, true);
        role_assign($role, $manager->id, $systemid);
        $events = [$event => (object) ['id' => $event, 'categoryid' => (int) $hidden->id]];

        $this->as_viewer($student);
        $this->assertSame([], ical::admitted($events), 'The filter, as the student.');
        foreach (['all', 'categories'] as $what) {
            $feed = (string) ical::feed($student, (int) $student->id, $what, 'recentupcoming', 0);
            $this->assertNotContains($event, self::feed_ids($feed), "preset_what={$what}");
        }

        foreach ([get_admin(), $manager] as $viewer) {
            $this->as_viewer($viewer);
            $this->assertSame([$event], array_keys(ical::admitted($events)), 'The filter keeps it for ' . $viewer->id);
            $feed = (string) ical::feed($viewer, (int) $viewer->id, 'all', 'recentupcoming', 0);
            $this->assertContains($event, self::feed_ids($feed), 'The feed keeps it for ' . $viewer->id);
        }
    }

    /**
     * The feed's filter runs as the token's account, not as whoever the session held before.
     *
     * feed_as() sets the user first. The category is unlisted: core's feed does not withhold it (the control), the
     * theme's filter reads $USER, so a feed run while the admin holds the session must still withhold it from the
     * student the token named, and the reverse run exports it to the admin the token named.
     *
     * @return void
     */
    public function test_the_feed_runs_as_the_tokens_user(): void {
        global $USER;
        $this->resetAfterTest();
        [$student, $ids] = $this->seed();
        $admin = get_admin();
        $event = $ids['unlisted'];
        $this->assertContains($event, self::core_ids($student), 'The control: core hands the unlisted event out.');

        $this->as_viewer($admin);
        $feed = (string) ical::feed_as($student, (int) $student->id, 'all', 'recentupcoming', 0);
        $this->assertSame((int) $student->id, (int) $USER->id, 'The session is the token\'s account.');
        $this->assertNotContains($event, self::feed_ids($feed), 'Read as the student, though the admin held the session.');
        $this->assertContains($ids['listed'], self::feed_ids($feed));

        $this->as_viewer($student);
        $feed = (string) ical::feed_as($admin, (int) $admin->id, 'all', 'recentupcoming', 0);
        $this->assertSame((int) $admin->id, (int) $USER->id);
        $this->assertContains($event, self::feed_ids($feed), 'The control: read as the admin, who sees it, the event is there.');
    }

    /**
     * The withholding keeps the events of no category and drops those of a category that is gone; the keys are kept.
     *
     * @return void
     */
    public function test_the_withholding_keeps_what_names_no_category_and_drops_a_category_that_is_gone(): void {
        global $DB;
        $this->resetAfterTest();
        [$student, $ids] = $this->seed();
        $events = [
            11 => (object) ['id' => 11, 'categoryid' => 0],
            12 => (object) ['id' => 12, 'categoryid' => $ids['listedcategory']],
            13 => (object) ['id' => 13, 'categoryid' => $ids['unlistedcategory']],
            14 => (object) ['id' => 14, 'categoryid' => $ids['unlistedcategory'] + 1000],
        ];
        $this->assertSame([11, 12], array_keys(ical::admitted($events)));
        $none = [21 => (object) ['id' => 21, 'categoryid' => 0]];
        $before = (int) $DB->perf_get_reads();
        $kept = ical::admitted($none);
        $reads = (int) $DB->perf_get_reads() - $before;
        $this->assertSame($none, $kept, 'Nothing of a category: the events are kept.');
        $this->assertSame(0, $reads, 'Nothing of a category: no statement is run to ask about one.');
    }

    /**
     * A set or a period core does not accept is no feed: the script sends it to the export page.
     *
     * @return void
     */
    public function test_an_unknown_preset_is_no_feed(): void {
        $this->resetAfterTest();
        [$student] = $this->seed();
        $this->assertNull(ical::feed($student, (int) $student->id, 'everything', 'recentupcoming', 0));
        $this->assertNull(ical::feed($student, (int) $student->id, 'all', 'forever', 0));
        $this->assertNull(ical::feed($student, (int) $student->id, '', 'recentupcoming', 0));
        $this->assertNotNull(ical::feed($student, (int) $student->id, 'all', 'custom', 0), 'The control.');
    }

    /**
     * The token is core's: the user id with core's export token, or the old address's username with its hash.
     *
     * @return void
     */
    public function test_the_token_is_checked_as_core_checks_it(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $other = self::getDataGenerator()->create_user();
        $token = calendar_get_export_token($user);

        $this->assertSame((int) $user->id, (int) ical::authenticated((int) $user->id, '', $token)?->id);
        $this->assertNull(ical::authenticated((int) $user->id, '', calendar_get_export_token($other)), 'Another\'s token.');
        $this->assertNull(ical::authenticated((int) $user->id, '', 'abc'));
        $this->assertNull(ical::authenticated(0, '', $token), 'No account named.');
        $this->assertNull(ical::authenticated((int) $other->id + 1000, '', $token), 'No such account.');

        $record = \core_user::get_user((int) $user->id);
        $old = sha1($record->username . $record->password . $CFG->calendar_exportsalt);
        $this->assertSame((int) $user->id, (int) ical::authenticated(0, $record->username, $old)?->id, 'The old address.');
        $this->assertNull(ical::authenticated(0, $record->username, $token), 'The username takes its own hash.');
    }

    /**
     * A token that reads as zero in scientific notation is not every token that reads as zero.
     *
     * No account can be made to hold such a token (its SHA-1 would have to be found by search), so the comparison is
     * driven directly: core's `==` holds for every pair below and the theme's comparison for none of them.
     *
     * @return void
     */
    public function test_a_token_is_compared_as_a_string_never_as_a_number(): void {
        $issued = '0e' . str_repeat('1', 38);

        foreach (['0', '00', '0e0', '0e' . str_repeat('2', 38)] as $given) {
            $this->assertTrue($given == $issued, 'The control: PHP reads the pair as two zeros.');
            $this->assertFalse(ical::token_matches($issued, $given), $given);
        }
        $this->assertTrue(ical::token_matches($issued, $issued), 'The issued token itself.');
    }

    /**
     * Both of authenticated()'s checks go through token_matches(), and neither compares the token itself.
     *
     * The comparison's behaviour is the test above; this one reads the source, because no account can be given a token
     * that tells the two comparisons apart, so a call site put back to core's `==` would pass every request test.
     *
     * @return void
     */
    public function test_both_token_checks_go_through_the_strict_comparison(): void {
        $method = new \ReflectionMethod(ical::class, 'authenticated');
        $lines = file((string) $method->getFileName());
        $body = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertSame(2, substr_count($body, 'self::token_matches('), 'The user id check and the username check.');
        $this->assertStringContainsString(
            'self::token_matches(calendar_get_export_token($user), $authtoken)',
            $body
        );
        $this->assertStringContainsString(
            'self::token_matches(sha1($username . $user->password . $CFG->calendar_exportsalt), $authtoken)',
            $body
        );
        $this->assertDoesNotMatchRegularExpression('/\$authtoken\s*!?==?|!?==?\s*\$authtoken/', $body);
    }
}
