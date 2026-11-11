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
 * Theme Dimensions - Tests for core's calendar blocks drawn with the theme's calendar
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Every refusal of {@see blocks} beside its control, core's upcoming preferences read as core reads them, and the
 * blocks' data taken from the range alone.
 *
 * The data tests fix "now" to a day of next month ({@see fixture::at()}), so the month block's grid and the agenda's
 * window are the same whatever day the suite runs on.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(blocks::class)]
final class blocks_test extends \advanced_testcase {
    use fixture;

    /**
     * Only the month block (its template with the flag) and the upcoming block are the theme's; core's month page
     * renders the month template without the flag, and no block renders core's mini template.
     *
     * @return void
     */
    public function test_which_calls_are_the_blocks(): void {
        $this->assertSame(blocks::MONTH, blocks::kind('core_calendar/calendar_month', (object) ['iscalendarblock' => true]));
        $this->assertSame(blocks::MONTH, blocks::kind('core_calendar/calendar_month', ['iscalendarblock' => true]));
        $this->assertNull(blocks::kind('core_calendar/calendar_month', (object) []), "Core's own month page.");
        $this->assertNull(blocks::kind('core_calendar/calendar_month', (object) ['iscalendarblock' => false]));
        $this->assertSame(blocks::UPCOMING, blocks::kind('core_calendar/calendar_upcoming_mini', (object) []));
        $this->assertNull(blocks::kind('core_calendar/calendar_mini', (object) ['iscalendarblock' => true]));
        $this->assertNull(blocks::kind('core_calendar/calendar_upcoming', (object) []));
        $this->assertNull(blocks::kind('core_calendar/calendar_day', (object) ['iscalendarblock' => true]));
    }

    /**
     * The footer goes for the upcoming block alone, and only while the theme answers the page's blocks.
     *
     * @return void
     */
    public function test_only_the_answered_upcoming_block_drops_its_footer(): void {
        $this->assertTrue(blocks::drops_footer(blocks::BLOCK_UPCOMING, true));
        $this->assertFalse(blocks::drops_footer(blocks::BLOCK_UPCOMING, false), 'Core answers the block.');
        $this->assertFalse(blocks::drops_footer('calendar_month', true), "The month block's footer is core's.");
        $this->assertFalse(blocks::drops_footer('html', true));
    }

    /**
     * What the blocks really pass to the renderer, read from core: the month view's template and the upcoming one's.
     *
     * @return void
     */
    public function test_the_template_names_are_the_ones_core_passes(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        $calendar = \calendar_information::create(time(), SITEID, null);
        [, $month] = calendar_get_view($calendar, 'monthblock', true);
        $calendar = \calendar_information::create(time(), SITEID, null);
        [, $upcoming] = calendar_get_view($calendar, 'upcoming_mini');

        $this->assertSame(blocks::TEMPLATE_MONTH, $month);
        $this->assertSame(blocks::TEMPLATE_UPCOMING, $upcoming);
        $source = (string) file_get_contents($CFG->dirroot . '/blocks/calendar_month/block_calendar_month.php');
        $this->assertStringContainsString('$data->iscalendarblock = true;', $source);
    }

    /**
     * Each reason core's output stays, beside the same call answered once the one reason is gone.
     *
     * @return array[]
     */
    public static function refusals(): array {
        return [
            'the setting off' => [['enabled' => false]],
            'a course page' => [['courseid' => 2000]],
            'a visitor or the guest' => [['account' => 0]],
            'a calendar type other than the Gregorian' => [['gregorian' => false]],
        ];
    }

    /**
     * A refusal keeps core's output; the control, the same call with that one input back, is the theme's.
     *
     * @param array $with The one input that refuses.
     * @return void
     */
    #[DataProvider('refusals')]
    public function test_each_refusal_keeps_core_and_its_control_is_answered(array $with): void {
        $call = ['enabled' => true, 'courseid' => (int) SITEID, 'account' => 7, 'gregorian' => true];
        $this->assertTrue(blocks::applies(...$call), 'The control.');
        $this->assertFalse(blocks::applies(...array_merge($call, $with)));
    }

    /**
     * The setting ships on and is read as off only for an explicit '0'.
     *
     * @return void
     */
    public function test_the_setting_is_off_only_when_it_is_an_explicit_zero(): void {
        $this->resetAfterTest();
        unset_config(blocks::SETTING, blocks::COMPONENT);
        $this->assertTrue(blocks::enabled(), 'Never stored: on.');
        set_config(blocks::SETTING, '1', blocks::COMPONENT);
        $this->assertTrue(blocks::enabled());
        set_config(blocks::SETTING, '0', blocks::COMPONENT);
        $this->assertFalse(blocks::enabled());
    }

    /**
     * The lookahead and the count are read as calendar_get_view() reads them: the reader's preference, over the
     * site's setting, over core's default.
     *
     * @return void
     */
    public function test_the_lookahead_and_the_count_are_cores(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());

        unset($CFG->calendar_lookahead, $CFG->calendar_maxevents);
        $this->assertSame(CALENDAR_DEFAULT_UPCOMING_LOOKAHEAD, blocks::lookahead());
        $this->assertSame(CALENDAR_DEFAULT_UPCOMING_MAXEVENTS, blocks::maxevents());

        $CFG->calendar_lookahead = '14';
        $CFG->calendar_maxevents = '5';
        $this->assertSame(14, blocks::lookahead());
        $this->assertSame(5, blocks::maxevents());

        set_user_preference('calendar_lookahead', 60);
        set_user_preference('calendar_maxevents', 3);
        $this->assertSame(60, blocks::lookahead());
        $this->assertSame(3, blocks::maxevents());
    }

    /**
     * The window starts at the current hour in the viewer's timezone (a half-hour zone shows it is the zone's hour)
     * and ends the lookahead's days later, less a second.
     *
     * @return void
     */
    public function test_the_upcoming_window_is_cores(): void {
        $zone = new \DateTimeZone('Asia/Kolkata');
        $now = (new \DateTimeImmutable('2027-03-10 14:47:13', $zone))->getTimestamp();

        [$from, $to] = blocks::upcoming_window($now, $zone, 21);

        $this->assertSame((new \DateTimeImmutable('2027-03-10 14:00:00', $zone))->getTimestamp(), $from);
        $this->assertSame((new \DateTimeImmutable('2027-03-31 14:00:00', $zone))->getTimestamp() - 1, $to);
    }

    /**
     * An item is in the window as core's vault keeps one with its duration: started inside it, or still running.
     *
     * @return void
     */
    public function test_an_item_is_kept_as_cores_vault_keeps_it(): void {
        $item = static fn(int $start, int $duration): array => ['timestart' => $start, 'timeduration' => $duration];
        $from = 1000;
        $to = 2000;

        $this->assertTrue(blocks::upcoming_keeps($item(1000, 0), $from, $to), 'Starts at the first second.');
        $this->assertTrue(blocks::upcoming_keeps($item(2000, 0), $from, $to), 'Starts at the last second.');
        $this->assertFalse(blocks::upcoming_keeps($item(2001, 0), $from, $to), 'Starts after the window.');
        $this->assertTrue(blocks::upcoming_keeps($item(900, 101), $from, $to), 'Still running at the start.');
        $this->assertFalse(blocks::upcoming_keeps($item(900, 100), $from, $to), 'Ended as the window starts.');
        $this->assertFalse(blocks::upcoming_keeps($item(999, 0), $from, $to), 'A point before the window.');
    }

    /**
     * A site event, which every account sees.
     *
     * @param string $name Its name.
     * @param int $start Its start.
     * @param int $duration Its duration.
     * @return int Its id.
     */
    private static function site_event(string $name, int $start, int $duration = 0): int {
        return (int) self::getDataGenerator()->create_event([
            'eventtype' => 'site',
            'name' => $name,
            'timestart' => $start,
            'timeduration' => $duration,
        ])->id;
    }

    /**
     * The ids of the agenda's items.
     *
     * @param int $now The current time.
     * @return int[] In the agenda's order.
     */
    private static function agenda_ids(int $now): array {
        self::forget_calendar();
        return array_map(static fn(array $item): int => (int) $item['id'], blocks::upcoming($now)['items']);
    }

    /**
     * The reader's lookahead bounds the agenda, each side pinned by an item just inside and one just outside.
     *
     * @return void
     */
    public function test_the_agenda_follows_the_readers_lookahead(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = self::at(10, 9);
        $inside = self::site_event('Inside', $now + 3 * DAYSECS - 1);
        $outside = self::site_event('Outside', $now + 3 * DAYSECS);
        $running = self::site_event('Running', $now - HOURSECS, 2 * HOURSECS);
        $over = self::site_event('Over', $now - 2 * HOURSECS, HOURSECS);
        $this->as_viewer(self::getDataGenerator()->create_user());
        set_user_preference('calendar_lookahead', 3);

        $this->assertSame([$running, $inside], self::agenda_ids($now));

        set_user_preference('calendar_lookahead', 4);
        $this->assertSame([$running, $inside, $outside], self::agenda_ids($now), 'The control: a day more reaches it.');
        $this->assertNotContains($over, self::agenda_ids($now));
    }

    /**
     * The reader's count caps the agenda at the earliest items; its control is the agenda with a larger count.
     *
     * @return void
     */
    public function test_the_agenda_follows_the_readers_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = self::at(10, 9);
        $ids = [];
        foreach ([3, 1, 2] as $day) {
            $ids[$day] = self::site_event('Day ' . $day, $now + $day * DAYSECS);
        }
        $this->as_viewer(self::getDataGenerator()->create_user());

        set_user_preference('calendar_maxevents', 2);
        $this->assertSame([$ids[1], $ids[2]], self::agenda_ids($now));
        set_user_preference('calendar_maxevents', 10);
        $this->assertSame([$ids[1], $ids[2], $ids[3]], self::agenda_ids($now));
    }

    /**
     * A lookahead past the range's 42 days is walked in pieces: an item beyond the first piece is found, an item
     * across two pieces is answered once, and a piece that fills the count ends the walk.
     *
     * @return void
     */
    public function test_a_long_lookahead_is_walked(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = self::at(1, 9);
        $across = self::site_event('Across', $now + 40 * DAYSECS, 5 * DAYSECS);
        $far = self::site_event('Far', $now + 80 * DAYSECS);
        $this->as_viewer(self::getDataGenerator()->create_user());
        set_user_preference('calendar_lookahead', 100);

        $this->assertSame([$across, $far], self::agenda_ids($now));

        set_user_preference('calendar_maxevents', 1);
        $this->assertSame([$across], self::agenda_ids($now));
    }

    /**
     * The dots: the keys with items in the keys' order, at most four, then "+".
     *
     * @return void
     */
    public function test_the_dots(): void {
        $this->assertSame(['keys' => [], 'more' => false], blocks::dots(array_fill_keys(keys::ALL, 0)));
        $this->assertSame(
            ['keys' => ['site', 'course', 'user', 'other'], 'more' => false],
            blocks::dots(['other' => 2, 'site' => 1, 'user' => 1, 'course' => 3, 'group' => 0])
        );
        $this->assertSame(
            ['keys' => ['site', 'category', 'course', 'group'], 'more' => true],
            blocks::dots(['open' => 1, 'site' => 1, 'category' => 1, 'course' => 1, 'group' => 1])
        );
    }

    /**
     * An item leads where the page's items lead, and only to a web address.
     *
     * @return void
     */
    public function test_an_item_leads_where_the_pages_items_lead(): void {
        $this->resetAfterTest();
        $home = (new \core\url('/'))->out(false);
        $courses = [5 => ['url' => 'https://example.com/course/view.php?id=5']];
        $item = static fn(string $url, int $courseid): array => ['url' => $url, 'courseid' => $courseid];

        $this->assertSame('https://example.com/mod/x', blocks::item_url($item('https://example.com/mod/x', 5), $courses));
        $this->assertSame($courses[5]['url'], blocks::item_url($item('', 5), $courses), 'Its course page.');
        $this->assertSame($home, blocks::item_url($item('', 1), $courses), 'The site home.');
        $this->assertSame($courses[5]['url'], blocks::item_url($item('javascript:alert(1)', 5), $courses));
        $this->assertSame($courses[5]['url'], blocks::item_url($item(' javascript:alert(1)', 5), $courses));
        $this->assertSame('', blocks::safe_url('data:text/html,x', ''));
        $this->assertSame('http://example.com/a', blocks::safe_url('http://example.com/a', ''));
    }

    /**
     * The no-JavaScript agenda leads where the block does: one rule, so a non-web address never reaches its href.
     *
     * @return void
     */
    public function test_the_agenda_applies_the_blocks_url_rule(): void {
        $this->resetAfterTest();
        $courses = [5 => ['url' => 'https://example.com/course/view.php?id=5']];
        $item = static fn(string $url): array => ['url' => $url, 'courseid' => 5];

        $this->assertSame('https://example.com/mod/x', \theme_dimensions\output\calendar_page::item_url(
            $item('https://example.com/mod/x'),
            $courses
        ));
        $this->assertSame($courses[5]['url'], \theme_dimensions\output\calendar_page::item_url(
            $item('javascript:alert(1)'),
            $courses
        ), 'A non-web address falls back to the course page.');
    }

    /**
     * Neither block answers what the range drops: a category event of an unlisted category is in neither, beside a
     * listed category's event in both, the saved state narrowed to their parent (as the range's own test narrows).
     *
     * @return void
     */
    public function test_the_blocks_never_answer_what_the_range_drops(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = $generator->create_category();
        $listed = $generator->create_category(['parent' => $top->id]);
        $unlisted = $generator->create_category(['parent' => $top->id]);
        $student = $generator->create_user();
        $now = self::at(10, 9);
        $ids = [];
        foreach (['listed' => $listed, 'unlisted' => $unlisted] as $name => $category) {
            $ids[$name] = (int) $generator->create_event([
                'eventtype' => 'category',
                'categoryid' => $category->id,
                'name' => $name,
                'timestart' => self::at(12),
            ])->id;
        }
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $this->as_viewer($student);
        (new state([], (int) $top->id))->save();

        $blocks = function () use ($now): array {
            self::forget_calendar();
            $month = self::item_ids(blocks::month($now)['answer']['events']);
            self::forget_calendar();
            $agenda = self::item_ids(blocks::upcoming($now)['items']);
            return [$month, $agenda];
        };
        foreach ($blocks() as $shown) {
            $this->assertContains($ids['listed'], $shown, 'The control: the listed category event is answered.');
            $this->assertNotContains($ids['unlisted'], $shown);
        }

        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_DEFAULT);
        foreach ($blocks() as $shown) {
            $this->assertContains($ids['unlisted'], $shown, 'Listed again, it is back.');
        }
    }

    /**
     * The blocks draw the reader's saved state: a key off drops its items from both, the default shows them.
     *
     * @return void
     */
    public function test_the_blocks_read_the_saved_state(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $now = self::at(10, 9);
        $site = self::site_event('Site', self::at(12));
        $this->as_viewer(self::getDataGenerator()->create_user());

        $this->assertContains($site, self::item_ids(blocks::month($now)['answer']['events']), 'The control.');
        $this->assertContains($site, self::agenda_ids($now), 'The control.');

        (new state([keys::SITE]))->save();
        self::forget_calendar();
        $this->assertNotContains($site, self::item_ids(blocks::month($now)['answer']['events']));
        $this->assertNotContains($site, self::agenda_ids($now));
    }

    /**
     * The month block draws the month of now in the viewer's timezone, over the page's grid.
     *
     * @return void
     */
    public function test_the_month_is_the_month_of_now_on_the_pages_grid(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user(['timezone' => 'America/Sao_Paulo']));
        // One o'clock in the morning UTC on the 1st is still the last day of the month before in São Paulo.
        $now = (new \DateTimeImmutable('2027-04-01 01:00:00', new \DateTimeZone('UTC')))->getTimestamp();

        $month = blocks::month($now);

        $this->assertSame('2027-03', $month['month']);
        $this->assertSame('2027-03-31', $month['today']);
        [$from, $to] = page::grid('2027-03', $month['firstday']);
        $this->assertSame($from, $month['answer']['from']);
        $this->assertSame($to, $month['answer']['to']);
    }
}
