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
 * Theme Dimensions - Core's answers about editing the calendar, passed through to the page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_external\external_api;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\external\get_calendar_range;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * "New event", the export, and each event's Edit, Delete and drag are what core's month view decides for the viewer.
 *
 * Every "not offered" case stands beside its control: the same viewer or the same event offered once the one reason is
 * gone. The parity case compares every event of core's own month export with the answer's entries, per role.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(editing::class)]
#[CoversClass(draglimits::class)]
final class editing_test extends \advanced_testcase {
    use fixture;

    /** @var array Users and ids by name. */
    private array $ids = [];

    /**
     * A course starting on the 5th of next month, a teacher and a student in it and in its group, and next month's
     * events of every kind: the student's own, the course's, the group's, the site's, the category's, an assignment's
     * due date (submissions from the 15th, cut off on the 28th), another's due date the teacher has an override of, and a
     * series of three of the student's.
     *
     * @return void
     */
    private function seed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id, 'startdate' => self::at(5, 0)]);
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teacher->id]);
        $assign = $generator->create_module('assign', [
            'course' => $course->id,
            'allowsubmissionsfromdate' => self::at(15, 0),
            'duedate' => self::at(20, 23),
            'gradingduedate' => 0,
            'cutoffdate' => self::at(28, 23),
        ]);
        // A second assignment whose due date the teacher has an override of: core lets no override be dragged.
        $overridden = $generator->create_module('assign', [
            'course' => $course->id,
            'duedate' => self::at(18, 23),
            'gradingduedate' => 0,
        ]);
        $generator->get_plugin_generator('mod_assign')->create_override([
            'assignid' => $overridden->id,
            'userid' => $teacher->id,
            'duedate' => self::at(19, 23),
        ]);
        $first = $generator->create_event(['eventtype' => 'user', 'userid' => $student->id, 'timestart' => self::at(22)]);
        $DB->set_field('event', 'repeatid', $first->id, ['id' => $first->id]);
        foreach ([23, 24] as $day) {
            $generator->create_event([
                'eventtype' => 'user',
                'userid' => $student->id,
                'timestart' => self::at($day),
                'repeatid' => $first->id,
            ]);
        }
        $this->ids = [
            'course' => $course,
            'teacher' => $teacher,
            'student' => $student,
            'user' => (int) $generator->create_event([
                'eventtype' => 'user',
                'userid' => $student->id,
                'timestart' => self::at(10),
            ])->id,
            'courseevent' => (int) $generator->create_event([
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(12),
            ])->id,
            'group' => (int) $generator->create_event([
                'eventtype' => 'group',
                'courseid' => $course->id,
                'groupid' => $group->id,
                'timestart' => self::at(13),
            ])->id,
            'site' => (int) $generator->create_event(['eventtype' => 'site', 'timestart' => self::at(14)])->id,
            'category' => (int) $generator->create_event([
                'eventtype' => 'category',
                'categoryid' => $category->id,
                'timestart' => self::at(16),
            ])->id,
            'due' => (int) $DB->get_field('event', 'id', [
                'modulename' => 'assign',
                'instance' => $assign->id,
                'eventtype' => 'due',
            ]),
            'series' => (int) $first->id,
            'override' => (int) $DB->get_field('event', 'id', [
                'modulename' => 'assign',
                'instance' => $overridden->id,
                'userid' => $teacher->id,
            ]),
        ];
    }

    /**
     * The answer's entries for next month, by event id, as the viewer reads them.
     *
     * @param \stdClass $user The viewer.
     * @param filters|null $filters The "More" filters.
     * @return array[] Event id => entry.
     */
    private function entries_for(\stdClass $user, ?filters $filters = null): array {
        $this->as_viewer($user);
        $answer = range::answer(self::month_window(), scope::for_viewer(), keys::ALL, $filters ?? new filters());
        return array_column($answer['editing'], null, 'id');
    }

    /**
     * Prohibit the default role's right to add one's own events, everywhere.
     *
     * @return void
     */
    private static function prohibit_own_entries(): void {
        global $CFG;
        assign_capability(
            'moodle/calendar:manageownentries',
            CAP_PROHIBIT,
            $CFG->defaultuserroleid,
            \core\context\system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
    }

    /**
     * "New event" is calendar_user_can_add_event() for the site: an account with its own entries has it, the same
     * account without the right does not, nor do the guest and a visitor; the site administrator does.
     *
     * @return void
     */
    public function test_new_event_is_cores_check_for_the_site(): void {
        $this->seed();
        $this->as_viewer($this->ids['student']);
        $this->assertTrue(editing::create(), 'Control: the default role manages its own entries.');
        $this->assertSame((int) \core\context\course::instance(SITEID)->id, editing::form_context());

        self::prohibit_own_entries();
        $this->assertFalse(editing::create(), 'The same account without the right.');

        $this->setAdminUser();
        $this->assertTrue(editing::create(), 'Control: site entries, whatever the default role says.');
        $this->setGuestUser();
        $this->assertFalse(editing::create(), 'The guest.');
        $this->setUser(null);
        $this->assertFalse(editing::create(), 'A visitor.');
    }

    /**
     * The export is offered where core's calendar offers it: the switch on and the account may add an event.
     *
     * @return void
     */
    public function test_the_export_is_offered_where_cores_calendar_offers_it(): void {
        $this->seed();
        set_config('enablecalendarexport', 1);
        $this->as_viewer($this->ids['student']);
        $this->assertSame((new \core\url('/calendar/export.php'))->out(false), editing::export_url(), 'Control: offered.');

        set_config('enablecalendarexport', 0);
        $this->assertSame('', editing::export_url(), 'The switch off.');

        set_config('enablecalendarexport', 1);
        self::prohibit_own_entries();
        $this->assertSame('', editing::export_url(), 'An account core\'s footer offers nothing to.');
    }

    /**
     * Each viewer's entries are core's: the student edits, deletes and drags their own event and nothing of the course;
     * the teacher edits the course's events, with the course's start as the earliest day, and drags the assignment's
     * date without an Edit or a Delete; nobody else's own event is the teacher's.
     *
     * @return void
     */
    public function test_each_viewer_gets_cores_answers(): void {
        $this->seed();
        $student = $this->entries_for($this->ids['student']);
        $this->assertSame([
            'id' => $this->ids['user'],
            'edit' => true,
            'delete' => true,
            'drag' => true,
            'count' => 1,
            'minday' => '',
            'maxday' => '',
            'minerror' => '',
            'maxerror' => '',
        ], $student[$this->ids['user']]);
        $this->assertArrayNotHasKey($this->ids['courseevent'], $student, 'Not the student\'s to edit.');
        $this->assertArrayNotHasKey($this->ids['due'], $student);

        $teacher = $this->entries_for($this->ids['teacher']);
        $this->assertArrayNotHasKey($this->ids['user'], $teacher, 'Another account\'s own event.');
        $course = $teacher[$this->ids['courseevent']] ?? null;
        $this->assertNotNull($course, 'Control: the course event is the teacher\'s.');
        $this->assertTrue($course['edit'] && $course['delete'] && $course['drag']);
        $this->assertSame(self::month_start()->modify('+4 days')->format('Y-m-d'), $course['minday'], 'Not before the start.');
        $this->assertSame(get_string('errorbeforecoursestart', 'calendar'), $course['minerror']);
        $this->assertSame('', $course['maxday']);
        $due = $teacher[$this->ids['due']] ?? null;
        $this->assertNotNull($due, 'The assignment\'s date may be moved by its teacher.');
        $this->assertFalse($due['edit'], 'Core\'s form never edits an activity\'s event.');
        $this->assertFalse($due['delete'], 'Nor deletes it.');
        $this->assertTrue($due['drag']);
        $this->assertSame(self::month_start()->modify('+14 days')->format('Y-m-d'), $due['minday'], 'Not before submissions.');
        $this->assertSame(self::month_start()->modify('+27 days')->format('Y-m-d'), $due['maxday'], 'Not after the cut-off.');
        $this->assertSame(get_string('cutoffdatevalidation', 'assign'), $due['maxerror']);
        $override = $teacher[$this->ids['override']] ?? null;
        $this->assertNotNull($override, 'The teacher may edit the override\'s activity.');
        $this->assertFalse($override['drag'], 'Core lets no override be dragged; the due date beside it is the control.');
    }

    /**
     * Every event of core's own month view has an entry exactly when core's export says canedit, and the entry's
     * Delete, drag, limits and count are core's candelete, draggable, mindaytimestamp, maxdaytimestamp and eventcount.
     *
     * @return void
     */
    public function test_the_entries_are_cores_month_export(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $this->seed();
        $admin = get_admin();
        $dayof = static fn(?int $at): string => $at ? userdate($at, '%Y-%m-%d', 99, false) : '';
        // The administrator is in no course: core's month shows them the site's and the categories' events.
        $viewers = ['admin' => [$admin, 1], 'teacher' => [$this->ids['teacher'], 5], 'student' => [$this->ids['student'], 5]];
        foreach ($viewers as $who => [$user, $least]) {
            $entries = $this->entries_for($user);
            self::forget_calendar();
            $calendar = \calendar_information::create(self::at(2), SITEID, null);
            [$data] = calendar_get_view($calendar, 'month');
            $checked = 0;
            $editable = 0;
            foreach ($data->weeks as $week) {
                foreach ($week->days as $day) {
                    foreach ($day->events as $event) {
                        $checked++;
                        $entry = $entries[(int) $event->id] ?? null;
                        $this->assertSame((bool) $event->canedit, $entry !== null, "{$who}: {$event->id} canedit");
                        if ($entry === null) {
                            continue;
                        }
                        $editable++;
                        $this->assertSame($event->canedit && !$event->isactionevent, $entry['edit'], "{$who}: {$event->id} edit");
                        $this->assertSame((bool) $event->candelete, $entry['delete'], "{$who}: {$event->id} delete");
                        $this->assertSame((bool) $event->draggable, $entry['drag'], "{$who}: {$event->id} drag");
                        $this->assertSame($dayof($event->mindaytimestamp ?? null), $entry['minday'], "{$who}: {$event->id} min");
                        $this->assertSame($dayof($event->maxdaytimestamp ?? null), $entry['maxday'], "{$who}: {$event->id} max");
                        $this->assertSame((int) ($event->eventcount ?? 1), $entry['count'], "{$who}: {$event->id} count");
                    }
                }
            }
            $this->assertGreaterThanOrEqual($least, $checked, "{$who}: core's month was read.");
            $this->assertGreaterThan(0, $editable, "{$who}: something was editable.");
        }
    }

    /**
     * A series answers its own count for core's delete confirmation; a single event counts one.
     *
     * @return void
     */
    public function test_a_series_counts_its_events(): void {
        global $DB;
        $this->seed();
        $entries = $this->entries_for($this->ids['student']);
        foreach ($DB->get_fieldset_select('event', 'id', 'repeatid = ?', [$this->ids['series']]) as $id) {
            $this->assertSame(3, $entries[(int) $id]['count']);
        }
        $this->assertSame(1, $entries[$this->ids['user']]['count'], 'Control: a single event.');
    }

    /**
     * An event a calendar subscription keeps up to date from a URL is never editable, as core says; the same event
     * imported once from a file is.
     *
     * @return void
     */
    public function test_an_event_of_a_url_subscription_is_never_editable(): void {
        global $DB;
        $this->seed();
        $studentid = $this->ids['student']->id;
        $subscription = static fn(string $url): int => (int) $DB->insert_record('event_subscriptions', (object) [
            'url' => $url,
            'categoryid' => 0,
            'courseid' => 0,
            'groupid' => 0,
            'userid' => $studentid,
            'eventtype' => 'user',
            'pollinterval' => 0,
            'lastupdated' => 0,
            'name' => 'Feed',
        ]);
        $generator = self::getDataGenerator();
        $fed = $generator->create_event([
            'eventtype' => 'user',
            'userid' => $this->ids['student']->id,
            'timestart' => self::at(26),
            'subscriptionid' => $subscription('https://example.com/feed.ics'),
        ]);
        $imported = $generator->create_event([
            'eventtype' => 'user',
            'userid' => $this->ids['student']->id,
            'timestart' => self::at(27),
            'subscriptionid' => $subscription(''),
        ]);
        $entries = $this->entries_for($this->ids['student']);
        $this->assertArrayNotHasKey((int) $fed->id, $entries);
        $this->assertArrayHasKey((int) $imported->id, $entries, 'Control: imported from a file.');
    }

    /**
     * The answer's entries name only the items it keeps, and the blocks' answer carries none.
     *
     * @return void
     */
    public function test_the_answer_lists_only_kept_items_and_the_blocks_none(): void {
        $this->seed();
        $this->assertArrayHasKey($this->ids['user'], $this->entries_for($this->ids['student']), 'Control: kept.');
        $filtered = $this->entries_for($this->ids['student'], new filters([], [], '', true));
        $this->assertArrayNotHasKey($this->ids['user'], $filtered, '"Only things to do" drops the event and its entry.');

        $this->as_viewer($this->ids['student']);
        $window = self::month_window();
        $this->assertNotSame([], range::answer($window, scope::for_viewer(), keys::ALL, new filters(), true)['editing']);
        self::forget_calendar();
        $this->assertSame([], range::answer($window, scope::for_viewer(), keys::ALL, new filters(), false)['editing']);
        $nocore = range::answer($window, scope::for_viewer(), [keys::COURSE], new filters());
        $this->assertSame([], $nocore['editing'], 'No key of core\'s, no entry.');
    }

    /**
     * The service returns the entries through its allowlist, key for key.
     *
     * @return void
     */
    public function test_the_service_returns_the_entries(): void {
        $this->seed();
        $this->as_viewer($this->ids['student']);
        $window = self::month_window();
        $expected = range::answer($window, scope::for_viewer(), keys::ALL, new filters())['editing'];
        self::forget_calendar();
        $answer = external_api::clean_returnvalue(
            get_calendar_range::execute_returns(),
            get_calendar_range::execute($window->from, $window->to)
        );
        $this->assertSame($expected, $answer['editing']);
        $this->assertSame(editing::ENTRY_KEYS, array_keys($answer['editing'][0]));
    }
}
