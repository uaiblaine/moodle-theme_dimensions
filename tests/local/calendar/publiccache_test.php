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
 * Theme Dimensions - Tests for the public calendar's shared cache and its tokens
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\branding;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/fixture.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/theme/dimensions/lib.php');

/**
 * The tokens (stable, renewed by every change a half can show and by age), the halves kept under them, the windows kept.
 *
 * Every "renewed" has its control: an event that a half cannot show leaves the token as it was.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(publiccache::class)]
#[CoversClass(publicrange::class)]
final class publiccache_test extends \advanced_testcase {
    use fixture;

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /**
     * A public category with a category event and a public event course in it; the switch on.
     *
     * @return void
     */
    private function seed(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $public = (int) $generator->create_category(['name' => 'Agenda Publica'])->id;
        category_discoverability::set_state($public, category_discoverability::STATE_PUBLIC, $adminid);
        $event = $generator->create_event([
            'name' => 'Feira',
            'eventtype' => 'category',
            'categoryid' => $public,
            'timestart' => self::at(10),
        ]);
        $course = self::event_course($public, 3, 5, ['fullname' => 'Congresso Aberto']);
        discoverability::set_state((int) $course->id, discoverability::STATE_PUBLIC, $adminid);
        set_config(visitor::SETTING, '1', 'theme_dimensions');
        $this->ids = ['public' => $public, 'event' => (int) $event->id, 'course' => (int) $course->id];
        self::forget_calendar();
    }

    /**
     * Both tokens, as the page would hand them now.
     *
     * @return string[] half => token.
     */
    private static function tokens(): array {
        return [
            publiccache::EVENTS => publiccache::token(publiccache::EVENTS),
            publiccache::COURSES => publiccache::token(publiccache::COURSES),
        ];
    }

    /**
     * Which halves' tokens a change renewed.
     *
     * @param callable $change The change.
     * @return string[] The halves renewed, in HALVES order.
     */
    private static function renewed_by(callable $change): array {
        $before = self::tokens();
        $change();
        $after = self::tokens();
        return array_values(array_filter(publiccache::HALVES, static fn(string $half): bool => $before[$half] !== $after[$half]));
    }

    /**
     * A token is minted once and read back the same until a change; a bump renews only the half named.
     *
     * @return void
     */
    public function test_a_token_stands_until_its_half_is_bumped(): void {
        $this->resetAfterTest();
        $first = self::tokens();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $first[publiccache::EVENTS]);
        $this->assertSame($first, self::tokens(), 'Read twice, the same tokens.');
        $this->assertSame([publiccache::COURSES], self::renewed_by(static fn() => publiccache::bump(publiccache::COURSES)));
        $this->assertSame([publiccache::EVENTS], self::renewed_by(static fn() => publiccache::bump(publiccache::EVENTS)));
        $this->assertSame(publiccache::HALVES, self::renewed_by(static fn() => publiccache::bump()), 'None named: both.');
    }

    /**
     * A token older than its half's rotation is renewed when read; a younger one is not.
     *
     * @return void
     */
    public function test_a_token_is_renewed_by_age_alone(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen();
        $first = self::tokens();
        $clock->bump(publiccache::ROTATION[publiccache::COURSES] - 1);
        $this->assertSame($first, self::tokens(), 'Control: younger than the courses\' rotation, both stand.');
        $clock->bump(1);
        $now = self::tokens();
        $this->assertNotSame($first[publiccache::COURSES], $now[publiccache::COURSES], 'The courses\' token aged out.');
        $this->assertSame($first[publiccache::EVENTS], $now[publiccache::EVENTS], 'The events\' token lives a day.');
        $clock->bump(publiccache::ROTATION[publiccache::EVENTS]);
        $this->assertNotSame($first[publiccache::EVENTS], self::tokens()[publiccache::EVENTS], 'And a day later it is renewed.');
    }

    /**
     * Every change a half can show renews its token; a change of something no half shows renews nothing.
     *
     * @return void
     */
    public function test_each_change_renews_the_halves_it_can_change(): void {
        // The observers run after a commit; the rollback transaction PostgreSQL wraps a test in would hold them.
        $this->preventResetByRollback();
        $this->seed();
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $both = publiccache::HALVES;
        $events = [publiccache::EVENTS];
        $courses = [publiccache::COURSES];

        $this->assertSame($events, self::renewed_by(fn() => $generator->create_event([
            'name' => 'Outra feira', 'eventtype' => 'category', 'categoryid' => $this->ids['public'], 'timestart' => self::at(12),
        ])), 'A category event created.');
        $event = \calendar_event::load($this->ids['event']);
        $this->assertSame($events, self::renewed_by(static fn() => $event->update((object) ['name' => 'Feira nova'], false)));
        $this->assertSame($events, self::renewed_by(static fn() => $event->delete()), 'A category event deleted.');
        $this->assertSame([], self::renewed_by(fn() => $generator->create_event([
            'name' => 'Pessoal', 'eventtype' => 'user', 'userid' => $adminid, 'courseid' => 0, 'timestart' => self::at(12),
        ])), 'Control: a user event is no visitor\'s.');

        $this->assertSame($courses, self::renewed_by(fn() => update_course((object) [
            'id' => $this->ids['course'], 'fullname' => 'Congresso Renovado',
        ])), 'A course updated.');
        $this->assertSame($courses, self::renewed_by(static fn() => $generator->create_course()), 'A course created.');
        $this->assertSame($courses, self::renewed_by(fn() => discoverability::set_state(
            $this->ids['course'],
            discoverability::STATE_UNLISTED,
            $adminid
        )), 'A course\'s state changed.');

        $category = \core_course_category::get($this->ids['public']);
        $this->assertSame($both, self::renewed_by(static fn() => $category->update(['name' => 'Agenda'])), 'A category edited.');
        $this->assertSame($both, self::renewed_by(static fn() => $category->hide()), 'A category hidden.');
        $this->assertSame($both, self::renewed_by(fn() => category_discoverability::set_state(
            $this->ids['public'],
            category_discoverability::STATE_UNLISTED,
            $adminid
        )), 'A category\'s state changed.');
        $this->assertSame($both, self::renewed_by(static fn() => branding::forget_all()), 'A category\'s branding saved.');
        $this->assertSame($both, self::renewed_by(static function (): void {
            set_config(visitor::SETTING, '0', 'theme_dimensions');
            theme_dimensions_public_calendar_changed();
        }), 'A switch the visitor\'s answer goes through.');

        $doomed = $generator->create_course();
        $this->assertSame($courses, self::renewed_by(static fn() => delete_course($doomed, false)), 'A course deleted.');
        $gone = \core_course_category::get((int) $generator->create_category()->id);
        $this->assertSame($both, self::renewed_by(static fn() => $gone->delete_full(false)), 'A category deleted.');
    }

    /**
     * A change written inside a transaction renews its half only once the transaction commits.
     *
     * A token minted before the commit would be read by a visitor who still sees the old rows, and that visitor's
     * answer would be kept under the new token until the next renewal.
     */
    public function test_a_change_inside_a_transaction_renews_its_half_only_on_commit(): void {
        global $DB;
        $this->preventResetByRollback();
        $this->seed();
        $before = publiccache::token(publiccache::COURSES);

        $transaction = $DB->start_delegated_transaction();
        update_course((object) ['id' => $this->ids['course'], 'fullname' => 'Congresso Transacional']);
        $this->assertSame($before, publiccache::token(publiccache::COURSES), 'Not while the change is uncommitted.');
        $transaction->allow_commit();

        $this->assertNotSame($before, publiccache::token(publiccache::COURSES), 'The commit renews it.');
    }

    /**
     * A half is read from the cache once kept: the second read in a new request costs no statement.
     *
     * The control is the first read, which pays the sources; and a bump makes the next read pay them again.
     *
     * @return void
     */
    public function test_a_kept_half_costs_no_statement(): void {
        $this->seed();
        $this->become(null);
        $window = window::from_iso(...page::grid(self::month_start()->format('Y-m'), 1));
        foreach (publiccache::HALVES as $half) {
            $first = $this->reads(static fn() => publiccache::half($half, $window));
            $this->assertGreaterThan(0, $first, "Control: the {$half} half is computed once.");
            $this->assertSame(0, $this->reads(static fn() => publiccache::half($half, $window)), "The {$half} half is kept.");
            publiccache::bump($half);
            $this->assertGreaterThan(0, $this->reads(static fn() => publiccache::half($half, $window)), "A bump renews it.");
            $this->assertGreaterThan(
                0,
                $this->reads(static fn() => publiccache::half($half, $window, false)),
                'Asked not to keep, it is computed whatever the cache holds.'
            );
        }
    }

    /**
     * An answer written late under the token it was computed with, after a bump, is never read.
     *
     * The control is the same write under the current token, which is read.
     *
     * @return void
     */
    public function test_a_late_answer_under_an_old_token_is_never_read(): void {
        $this->seed();
        $this->become(null);
        $window = window::from_iso(...page::grid(self::month_start()->format('Y-m'), 1));
        $old = publiccache::token(publiccache::EVENTS);
        publiccache::bump(publiccache::EVENTS);
        $cache = \cache::make('theme_dimensions', 'publiccalendar');
        $stale = ['stale' => true] + publicrange::events($window);
        $cache->set(publiccache::key(publiccache::EVENTS, $old, $window), $stale);
        $this->assertArrayNotHasKey('stale', publiccache::half(publiccache::EVENTS, $window), 'The late answer is not read.');
        $cache->set(publiccache::key(publiccache::EVENTS, publiccache::token(publiccache::EVENTS), $window), $stale);
        $this->assertArrayHasKey('stale', publiccache::half(publiccache::EVENTS, $window), 'Control: under the current token.');
    }

    /**
     * The halves are the answer: each one its key's, and the merge of both the answer of both keys.
     *
     * @return void
     */
    public function test_the_halves_merge_into_the_answer_of_both_keys(): void {
        $this->seed();
        $this->become(null);
        $window = window::from_iso(...page::grid(self::month_start()->format('Y-m'), 1));
        $events = publicrange::events($window);
        $courses = publicrange::courses($window);
        $this->assertSame([$this->ids['event']], array_column($events['events'], 'id'), 'The events\' half: the event alone.');
        $this->assertSame([], $events['courses']);
        $this->assertSame([$this->ids['course']], array_column($courses['events'], 'courseid'), 'The courses\': the course.');
        $this->assertSame([], $courses['descriptions']);

        $both = publicrange::answer($window, visitor::KEYS);
        $this->assertSame(json_encode(publicrange::merge($window, [$events, $courses])), json_encode($both));
        $this->assertSame([$this->ids['course'], $this->ids['event']], array_column($both['events'], 'id'), 'By start.');
        $this->assertSame([$this->ids['public']], array_column($both['categories'], 'id'), 'One category, once.');
        $this->assertSame(json_encode($events), json_encode(publicrange::answer($window, [keys::CATEGORY])), 'One key: its half.');
        $this->assertSame(json_encode($courses), json_encode(publicrange::answer($window, [keys::OPEN])));
        $none = publicrange::answer($window, []);
        $this->assertSame([], $none['events'], 'No key, no item.');
        $this->assertCount(count($window->days), $none['days'], 'And every day of the window.');
    }

    /**
     * The windows answered: a day, a week from the week's first day, and a month's grid; nothing else.
     *
     * @return void
     */
    public function test_only_the_windows_the_client_asks_for_are_canonical(): void {
        foreach ([0, 1, 6] as $firstday) {
            [$from, $to] = page::grid('2027-02', $firstday);
            $this->assertTrue(publiccache::canonical($from, $to, $firstday), "The month's grid, first day {$firstday}.");
            [$wfrom, $wto] = page::week(page::week_start('2027-02-10', $firstday));
            $this->assertTrue(publiccache::canonical($wfrom, $wto, $firstday), 'A week.');
            $this->assertTrue(publiccache::canonical('2027-02-10', '2027-02-10', $firstday), 'A day.');
            $this->assertFalse(
                publiccache::canonical('2027-04-01', '2027-04-30', $firstday),
                'A month (from a Thursday) without its grid.'
            );
            $utc = new \DateTimeZone('UTC');
            $shifted = \DateTimeImmutable::createFromFormat('!Y-m-d', $wfrom, $utc)->modify('+1 day')->format('Y-m-d');
            $sevenon = \DateTimeImmutable::createFromFormat('!Y-m-d', $wto, $utc)->modify('+1 day')->format('Y-m-d');
            $this->assertFalse(publiccache::canonical($shifted, $sevenon, $firstday), 'Seven days from another weekday.');
            $this->assertFalse(publiccache::canonical('2027-02-10', '2027-02-11', $firstday), 'Two days.');
        }
    }

    /**
     * A window is kept within REACH_MONTHS of this month either way, and not further.
     *
     * @return void
     */
    public function test_only_windows_near_this_month_are_kept(): void {
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(gmmktime(12, 0, 0, 6, 15, 2027));
        $this->assertSame(gmmktime(12, 0, 0, 6, 15, 2027), $clock->time());
        $this->assertTrue(publiccache::near('2027-06-15'));
        $this->assertTrue(publiccache::near('2025-06-01'), 'Twenty-four months back, its first day.');
        $this->assertFalse(publiccache::near('2025-05-31'), 'One day before.');
        $this->assertTrue(publiccache::near('2029-06-30'), 'Twenty-four months ahead, its last day.');
        $this->assertFalse(publiccache::near('2029-07-01'), 'One day after.');
    }

    /**
     * A lifetime is its setting, or its default while unset; never negative.
     *
     * @return void
     */
    public function test_a_lifetime_is_its_setting_or_its_default(): void {
        $this->resetAfterTest();
        $this->assertSame(DAYSECS, publiccache::lifetime(publiccache::EVENTS));
        $this->assertSame(300, publiccache::lifetime(publiccache::COURSES));
        set_config(publiccache::SETTING_LIFETIME[publiccache::COURSES], '120', 'theme_dimensions');
        $this->assertSame(120, publiccache::lifetime(publiccache::COURSES));
        $this->assertSame(DAYSECS, publiccache::lifetime(publiccache::EVENTS), 'The other half keeps its own.');
        set_config(publiccache::SETTING_LIFETIME[publiccache::COURSES], '-5', 'theme_dimensions');
        $this->assertSame(0, publiccache::lifetime(publiccache::COURSES));
    }
}
