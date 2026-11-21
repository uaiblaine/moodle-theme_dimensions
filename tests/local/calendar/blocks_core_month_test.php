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
 * Theme Dimensions - Tests for the month block counted from core's month context
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The month block's counts from core's month context equal the range's, and cost the same however many events.
 *
 * The month is fixed, September 2027 with Monday as the first weekday, so the grid has days of August before it and
 * of October after it whatever day the suite runs on: the days the core context does not hold.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(blocks::class)]
#[CoversClass(keys::class)]
final class blocks_core_month_test extends \advanced_testcase {
    use fixture;

    /** @var string The month every test draws. */
    private const MONTH = '2027-09';

    /**
     * An instant of a day, in the current user's timezone.
     *
     * @param string $date YYYY-MM-DD.
     * @param int $hour The hour.
     * @return int
     */
    private static function on(string $date, int $hour = 9): int {
        return (new \DateTimeImmutable($date . ' ' . $hour . ':00', \core_date::get_user_timezone_object()))->getTimestamp();
    }

    /**
     * Core's month context for the block, as block_calendar_month hands it to the renderer.
     *
     * @param int $now The current time.
     * @return mixed
     */
    private static function core_context(int $now) {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        [$data] = calendar_get_view(\calendar_information::create($now, SITEID, null), 'monthblock', true);
        // The flag block_calendar_month adds before it calls the renderer.
        $data->iscalendarblock = true;
        return $data;
    }

    /**
     * The ids of the events in a core month context.
     *
     * @param mixed $context The context.
     * @return int[]
     */
    private static function context_ids($context): array {
        $ids = [];
        foreach (json_decode(json_encode($context), true)['weeks'] as $week) {
            foreach ($week['days'] as $day) {
                foreach ($day['events'] ?? [] as $event) {
                    $ids[] = (int) $event['id'];
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Each key's total over the grid.
     *
     * @param array $days The answer's days.
     * @return int[] key => count.
     */
    private static function totals(array $days): array {
        $totals = array_fill_keys(keys::ALL, 0);
        foreach ($days as $day) {
            foreach ($day['counts'] as $key => $count) {
                $totals[$key] += $count;
            }
        }
        return $totals;
    }

    /**
     * A copy of a context with one more site event on a day of the month: what only the context would count.
     *
     * @param mixed $context The context.
     * @param string $date The day, YYYY-MM-DD, of the month.
     * @return array
     */
    private static function poisoned($context, string $date): array {
        $data = json_decode(json_encode($context), true);
        foreach ($data['weeks'] as $w => $week) {
            foreach ($week['days'] as $d => $day) {
                if (userdate((int) $day['timestamp'], '%Y-%m-%d', 99, false) === $date) {
                    $data['weeks'][$w]['days'][$d]['events'][] = [
                        'id' => 999999, 'eventtype' => 'site', 'component' => '', 'modulename' => '', 'instance' => 0,
                        'categoryid' => null, 'course' => ['id' => SITEID], 'timestart' => self::on($date),
                        'timeduration' => 0,
                    ];
                }
            }
        }
        return $data;
    }

    /**
     * A learner with an event of every key, a withheld category's event, a hidden activity, and events on the grid's
     * days outside the month, one of them running into it and one starting before the grid.
     *
     * @return array [learner, event ids by name, top category id]
     */
    private function seed(): array {
        global $DB;
        $this->setAdminUser();
        set_config('calendar_startwday', 1);
        $generator = self::getDataGenerator();
        $top = $generator->create_category();
        $listed = $generator->create_category(['parent' => $top->id]);
        $unlisted = $generator->create_category(['parent' => $top->id]);
        $learner = $generator->create_user();
        $course = $generator->create_course(['category' => $listed->id]);
        $generator->enrol_user($learner->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $learner->id]);
        // A course in the unlisted category: core shows that category's events to its learner, the range withholds them
        // (enrolment is no term of local_unlistedcourses' category rule).
        $generator->enrol_user($learner->id, $generator->create_course(['category' => $unlisted->id])->id, 'student');
        $dated = $generator->create_course([
            'category' => $listed->id,
            'startdate' => self::on('2027-09-05', 0),
            'enddate' => self::on('2027-09-20', 23),
        ]);
        self::set_model((int) $dated->id, landingfields::MODEL_EVENT);
        $generator->enrol_user($learner->id, $dated->id, 'student');

        $event = fn(array $record): int => (int) $generator->create_event($record + ['name' => 'Event'])->id;
        $ids = [
            'site' => $event(['eventtype' => 'site', 'timestart' => self::on('2027-09-12')]),
            'listed' => $event(['eventtype' => 'category', 'categoryid' => $listed->id, 'timestart' => self::on('2027-09-13')]),
            'unlisted' => $event(['eventtype' => 'category', 'categoryid' => $unlisted->id, 'timestart' => self::on('2027-09-14')]),
            'course' => $event(['eventtype' => 'course', 'courseid' => $course->id, 'timestart' => self::on('2027-09-15')]),
            'group' => $event([
                'eventtype' => 'group', 'courseid' => $course->id, 'groupid' => $group->id,
                'timestart' => self::on('2027-09-16'),
            ]),
            'user' => $event(['eventtype' => 'user', 'userid' => $learner->id, 'timestart' => self::on('2027-09-17')]),
            'other' => $event([
                'eventtype' => 'course', 'courseid' => $course->id, 'component' => 'enrol_self',
                'timestart' => self::on('2027-09-21'),
            ]),
            'lead' => $event(['eventtype' => 'site', 'timestart' => self::on('2027-08-31')]),
            'trail' => $event(['eventtype' => 'site', 'timestart' => self::on('2027-10-02')]),
            'across' => $event([
                'eventtype' => 'course', 'courseid' => $course->id, 'timestart' => self::on('2027-08-30', 10),
                'timeduration' => 5 * DAYSECS,
            ]),
            'before' => $event(['eventtype' => 'site', 'timestart' => self::on('2027-08-20'), 'timeduration' => 15 * DAYSECS]),
        ];
        $generator->create_module('assign', ['course' => $course->id, 'duedate' => self::on('2027-09-18')]);
        $hidden = $generator->create_module('assign', ['course' => $course->id, 'duedate' => self::on('2027-09-19')]);
        $DB->set_field('course_modules', 'visible', 0, ['id' => $hidden->cmid]);
        rebuild_course_cache($course->id, true);
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        return [$learner, $ids, (int) $top->id];
    }

    /**
     * The counts from core's context are the range's, day by day and key by key, with every key on and with one off.
     *
     * The controls: core's context holds the withheld category's event (so the withholding is exercised), and every
     * key core can carry has a count.
     *
     * @return void
     */
    public function test_the_counts_from_cores_context_are_the_ranges(): void {
        $this->resetAfterTest();
        [$learner, $ids] = $this->seed();
        $this->as_viewer($learner);
        $now = self::on('2027-09-10');
        $context = self::core_context($now);
        $this->assertContains($ids['unlisted'], self::context_ids($context), 'Control: core shows the withheld event.');

        self::forget_calendar();
        $range = blocks::month($now)['answer']['days'];
        self::forget_calendar();
        $fromcore = blocks::month($now, $context)['answer'];
        $this->assertArrayNotHasKey('events', $fromcore, 'Precondition: the core context was used.');
        $this->assertSame($range, $fromcore['days']);
        $totals = self::totals($fromcore['days']);
        foreach (keys::CORE as $key) {
            $this->assertGreaterThan(0, $totals[$key], "Control: the fixture has a $key item.");
        }

        (new state([keys::USER]))->save();
        self::forget_calendar();
        $range = blocks::month($now)['answer']['days'];
        self::forget_calendar();
        $this->assertSame($range, blocks::month($now, $context)['answer']['days'], 'A key off.');
        $this->assertSame(0, self::totals($range)[keys::USER]);
    }

    /**
     * A state core's question does not answer, or a context of other days, is answered by the range alone.
     *
     * A poisoned context (one more site event on the 22nd) shows which source counted: with the default state it is
     * counted (the control); with the state narrowed to a category, or with a day missing from the context, it is not.
     *
     * @return void
     */
    public function test_a_narrowed_state_or_a_partial_context_falls_back_to_the_range(): void {
        $this->resetAfterTest();
        [$learner, , $top] = $this->seed();
        $this->as_viewer($learner);
        $now = self::on('2027-09-10');
        $poisoned = self::poisoned(self::core_context($now), '2027-09-22');
        $site = fn(array $days): int => self::totals($days)[keys::SITE];

        self::forget_calendar();
        $range = blocks::month($now)['answer']['days'];
        self::forget_calendar();
        $this->assertSame($site($range) + 1, $site(blocks::month($now, $poisoned)['answer']['days']), 'Control.');

        $partial = $poisoned;
        array_pop($partial['weeks']);
        self::forget_calendar();
        $this->assertSame($range, blocks::month($now, $partial)['answer']['days'], 'A context missing days.');

        (new state([], $top))->save();
        self::forget_calendar();
        $narrowed = blocks::month($now)['answer']['days'];
        self::forget_calendar();
        $this->assertSame($narrowed, blocks::month($now, $poisoned)['answer']['days'], 'A state narrowed to a category.');
    }

    /**
     * From core's context, the month costs the same statements for three activity deadlines and for nine.
     *
     * The range fetched the month's events again, each assignment's callbacks included; from the context only the
     * grid's outside days are fetched. The control: the range's own path grows.
     *
     * @return void
     */
    public function test_the_month_from_cores_context_costs_the_same_for_three_deadlines_and_nine(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('calendar_startwday', 1);
        $generator = self::getDataGenerator();
        $now = self::on('2027-09-10');
        $reads = [];
        foreach ([3, 9] as $n) {
            $this->setAdminUser();
            $learner = $generator->create_user();
            $course = $generator->create_course();
            $generator->enrol_user($learner->id, $course->id, 'student');
            for ($i = 0; $i < $n; $i++) {
                $generator->create_module('assign', ['course' => $course->id, 'duedate' => self::on('2027-09-' . (5 + 2 * $i))]);
            }
            $this->as_viewer($learner);
            $context = self::core_context($now);
            foreach (['core' => $context, 'range' => null] as $path => $given) {
                self::forget_calendar();
                blocks::month($now, $given);
                self::forget_calendar();
                $before = (int) $DB->perf_get_reads();
                blocks::month($now, $given);
                $reads[$path][$n] = (int) $DB->perf_get_reads() - $before;
            }
        }
        $this->assertGreaterThan($reads['range'][3], $reads['range'][9], 'Control: the range\'s path grows.');
        $this->assertSame($reads['core'][3], $reads['core'][9], 'From core\'s context it does not.');
    }

    /**
     * The renderer hands core's context to the month block: a poisoned context draws another month.
     *
     * The renderer draws the month of now, as core's block computes it, so this test runs on the real current month:
     * the context and the poisoned day are this month's.
     *
     * @return void
     */
    public function test_the_renderer_counts_from_the_context_it_is_handed(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $learner = self::getDataGenerator()->create_user();
        $this->as_viewer($learner);
        $PAGE->set_url('/my/');
        $PAGE->force_theme('dimensions');
        $renderer = $PAGE->get_renderer('core_calendar');
        $now = time();
        $context = self::core_context($now);
        $kind = blocks::kind('core_calendar/calendar_month', $context);
        $this->assertSame(blocks::MONTH, $kind, 'Precondition: the block\'s call.');
        $day = userdate($now, '%Y-%m-15', 99, false);

        self::forget_calendar();
        $html = $renderer->render_from_template('core_calendar/calendar_month', $context);
        self::forget_calendar();
        $other = $renderer->render_from_template('core_calendar/calendar_month', (object) self::poisoned($context, $day));
        $this->assertStringContainsString('data-region="dim-calblock-month"', $html, 'Control: the theme drew the block.');
        $this->assertStringNotContainsString('dim-cal-key--site', $html, 'Control: no site event this month.');
        $this->assertStringContainsString('dim-cal-key--site', $other);
    }
}
