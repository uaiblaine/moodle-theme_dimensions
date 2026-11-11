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
 * Theme Dimensions - Tests for the calendar's Trilhas view answer
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_course\customfield\course_handler;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\card\boost_union;
use theme_dimensions\local\card\cover;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\progress;
use theme_dimensions\local\trail\trail;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../category/fixture.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * The trails of a month: which trails, which steps, their states, the filters, the cost.
 *
 * Every test needs mod_subcourse and skips without it. Days are 2027's, at noon in the viewer's timezone ({@see at()});
 * most tests read March 2027.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(trails::class)]
final class trails_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var \core_course_category The category every course of these tests sits in, unless a test says otherwise. */
    private \core_course_category $category;

    /**
     * Skip without the module; provision the fields as an administrator.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        self::forget();
        $this->category = self::getDataGenerator()->create_category(['name' => 'Gestão']);
    }

    /**
     * A day of 2027 at noon, in the viewer's timezone.
     *
     * @param int $month The month.
     * @param int $day The day.
     * @return int
     */
    private static function at(int $month, int $day): int {
        return make_timestamp(2027, $month, $day, 12);
    }

    /**
     * A course with landing fields stored.
     *
     * @param array $record Course record fields.
     * @param array $fields Field shortname => form value.
     * @return \stdClass The course.
     */
    private function course(array $record, array $fields = []): \stdClass {
        $course = self::getDataGenerator()->create_course($record + ['category' => $this->category->id]);
        if ($fields) {
            $data = ['id' => $course->id];
            foreach ($fields as $shortname => $value) {
                $data['customfield_' . $shortname] = $value;
            }
            course_handler::create()->instance_form_save((object) $data);
        }
        return $course;
    }

    /**
     * A referenced course running between two days of 2027.
     *
     * @param string $name Its name.
     * @param int $start When it starts.
     * @param int $end When it ends, 0 for no end.
     * @param array $record Further course fields.
     * @return \stdClass The course.
     */
    private function ref(string $name, int $start, int $end, array $record = []): \stdClass {
        return $this->course($record + ['fullname' => $name, 'startdate' => $start, 'enddate' => $end]);
    }

    /**
     * A trail course whose steps refer to the given courses, in that order.
     *
     * Open to self enrolment unless asked otherwise: the view shows a trail to an account with no tie to it only where
     * it may enrol (trails::shown_to()), so the tests of everything else read an open trail.
     *
     * @param string $name The trail's name.
     * @param \stdClass[] $refs The referenced courses.
     * @param array $record Further course fields.
     * @param bool $open Whether the course's self enrolment instance is enabled, taking new enrolments.
     * @return \stdClass The trail course.
     */
    private function trail(string $name, array $refs, array $record = [], bool $open = true): \stdClass {
        $trail = $this->course($record + ['fullname' => $name, 'enablecompletion' => 1], [
            landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL,
        ]);
        foreach (array_values($refs) as $ref) {
            self::getDataGenerator()->create_module('subcourse', [
                'course' => $trail->id,
                'refcourse' => $ref->id,
                'name' => $ref->fullname,
            ]);
        }
        if ($open) {
            $this->enable_instance((int) $trail->id, 'self', ['customint6' => 1]);
        }
        return $trail;
    }

    /**
     * Whether the answer names a trail, in its rows and in its choice; the two must agree.
     *
     * @param array $answer The answer.
     * @param int $trailid The trail course.
     * @return bool
     */
    private function names(array $answer, int $trailid): bool {
        $row = in_array($trailid, self::ids($answer), true);
        $option = in_array($trailid, array_column($answer['options'], 'id'), true);
        $this->assertSame($row, $option, 'The rows and the choice name the same trails.');
        return $row;
    }

    /**
     * The answer for a month, or a month and the next, as the current user, in a new request's state.
     *
     * @param string $month The month, YYYY-MM.
     * @param int $trailid One trail, 0 for every trail.
     * @param bool $mine Only the viewer's own.
     * @param int $categoryid The category narrowing.
     * @param int|null $now The moment, now by default.
     * @param int $months How many months from the month.
     * @return array
     */
    private function answer(
        string $month,
        int $trailid = 0,
        bool $mine = false,
        int $categoryid = 0,
        ?int $now = null,
        int $months = 1
    ): array {
        self::forget();
        get_fast_modinfo(0, 0, true);
        return trails::answer(
            trails::month_window($month, $months),
            scope::for_viewer($categoryid),
            $trailid,
            $mine,
            $now ?? time()
        );
    }

    /**
     * The ids of the answer's trails, in order.
     *
     * @param array $answer The answer.
     * @return int[]
     */
    private static function ids(array $answer): array {
        return array_map(static fn(array $trail): int => $trail['id'], $answer['trails']);
    }

    /**
     * The month shows the trails whose period meets it, each with its steps; the choice offers every trail.
     */
    public function test_a_month_draws_the_trails_it_meets(): void {
        $alfa = $this->ref('Alfa', self::at(3, 2), self::at(3, 20));
        $beta = $this->ref('Beta', self::at(3, 15), self::at(4, 10));
        $march = $this->trail('Trilha de março', [$alfa, $beta]);
        $june = $this->trail('Trilha de junho', [$this->ref('Gama', self::at(6, 1), self::at(6, 30))]);
        $this->become(self::getDataGenerator()->create_user());

        $answer = $this->answer('2027-03');
        $this->assertSame([(int) $march->id], self::ids($answer));
        $this->assertSame('2027-03-01', $answer['from']);
        $this->assertSame('2027-03-31', $answer['to']);
        $this->assertSame(
            [['id' => (int) $june->id, 'name' => 'Trilha de junho'], ['id' => (int) $march->id, 'name' => 'Trilha de março']],
            $answer['options'],
            'Every trail is offered, whatever its period, by name.'
        );
        $trail = $answer['trails'][0];
        $this->assertSame('Trilha de março', $trail['name']);
        $this->assertSame(links::page((int) $march->id)->out(false), $trail['url']);
        $this->assertFalse($trail['enrolled']);
        $this->assertSame(self::at(3, 2), $trail['first']);
        $this->assertSame(self::at(4, 10), $trail['last']);
        $this->assertSame([1, 2], array_column($trail['steps'], 'position'));
        $this->assertSame(['Alfa', 'Beta'], array_column($trail['steps'], 'name'));
        $this->assertSame([(int) $alfa->id, (int) $beta->id], array_column($trail['steps'], 'courseid'));
        $this->assertSame([self::at(3, 15), self::at(4, 10)], [$trail['steps'][1]['start'], $trail['steps'][1]['end']]);
        $this->assertSame(['', ''], array_column($trail['steps'], 'state'), 'Not a learner: the schedule alone.');
        $this->assertSame((int) $this->category->id, $trail['categoryid']);
        $this->assertSame([(int) $this->category->id], array_column($answer['categories'], 'id'));

        $this->assertSame([(int) $june->id], self::ids($this->answer('2027-06')));
        $this->assertSame([], self::ids($this->answer('2027-05')), 'A month between the two shows neither.');
        $this->assertCount(2, $this->answer('2027-05')['options']);
    }

    /**
     * The month and the next (the Trilhas view's first paint, trails::OPENING) draw the trails of both, a trail meeting
     * both once, over the two months' days.
     */
    public function test_two_months_draw_the_trails_of_both(): void {
        $march = $this->trail('Março', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $both = $this->trail('Ambos', [$this->ref('Beta', self::at(3, 15), self::at(4, 10))]);
        $april = $this->trail('Abril', [$this->ref('Gama', self::at(4, 12), self::at(4, 25))]);
        $this->trail('Maio', [$this->ref('Delta', self::at(5, 2), self::at(5, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $this->assertSame([(int) $march->id, (int) $both->id], self::ids($this->answer('2027-03')), 'Control: March alone.');
        $answer = $this->answer('2027-03', 0, false, 0, null, trails::OPENING);
        $this->assertSame(2, trails::OPENING);
        $this->assertSame(['2027-03-01', '2027-04-30'], [$answer['from'], $answer['to']]);
        $this->assertSame([(int) $march->id, (int) $both->id, (int) $april->id], self::ids($answer));
        $this->assertCount(4, $answer['options'], 'Every trail is offered, whatever its period.');
        $this->assertSame(
            trails::answer(trails::month_window('2027-03', 2), scope::for_viewer(), 0, false, self::at(3, 15)),
            trails::answer(window::whole_months('2027-03', 2), scope::for_viewer(), 0, false, self::at(3, 15)),
            'The window of the month and the next.'
        );
    }

    /**
     * Three months (a batch by months, trails::MONTHS) draw the trails of all three, a trail meeting several once, over
     * the three months' 92 days across a year end.
     */
    public function test_three_months_draw_the_trails_of_all_three(): void {
        $march = $this->trail('Março', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $both = $this->trail('Ambos', [$this->ref('Beta', self::at(3, 15), self::at(5, 10))]);
        $may = $this->trail('Maio', [$this->ref('Delta', self::at(5, 2), self::at(5, 20))]);
        $this->trail('Junho', [$this->ref('Épsilon', self::at(6, 2), self::at(6, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $this->assertSame(3, trails::MONTHS);
        $answer = $this->answer('2027-03', 0, false, 0, null, trails::MONTHS);
        $this->assertSame(['2027-03-01', '2027-05-31'], [$answer['from'], $answer['to']]);
        $this->assertSame([(int) $march->id, (int) $both->id, (int) $may->id], self::ids($answer));
        $this->assertSame(
            trails::answer(trails::month_window('2027-03', 3), scope::for_viewer(), 0, false, self::at(3, 15)),
            trails::answer(window::whole_months('2027-03', 3), scope::for_viewer(), 0, false, self::at(3, 15)),
            'The window of three months.'
        );
        $yearend = trails::month_window('2026-11', trails::MONTHS);
        $this->assertCount(92, $yearend->days);
        $this->assertSame(['2026-11-01', '2027-01-31'], [$yearend->from, $yearend->to]);
    }

    /**
     * A step that opens and never ends leaves the trail open: it meets every later month, never an earlier one; a trail
     * whose last end passed before the month does not meet it.
     */
    public function test_an_open_step_meets_every_later_month(): void {
        $open = $this->trail('Aberta', [$this->ref('Sem fim', self::at(1, 10), 0)]);
        $ended = $this->trail('Encerrada', [$this->ref('Fevereiro', self::at(2, 1), self::at(2, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $this->assertSame([(int) $open->id], self::ids($this->answer('2027-06')));
        $this->assertSame(0, $this->answer('2027-06')['trails'][0]['last'], 'An open period has no last end.');
        $this->assertSame([(int) $open->id, (int) $ended->id], self::ids($this->answer('2027-02')), 'By first start.');
        $this->assertSame([], self::ids($this->answer('2026-12')));
    }

    /**
     * A trail none of whose steps has a date is no row of any month, and still a choice.
     */
    public function test_a_trail_without_a_date_is_no_row(): void {
        $dateless = $this->trail('Sem datas', [$this->ref('Alfa', 0, 0)]);
        $dated = $this->trail('Com datas', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $answer = $this->answer('2027-03');
        $this->assertSame([(int) $dated->id], self::ids($answer));
        $this->assertContains((int) $dateless->id, array_column($answer['options'], 'id'));
    }

    /**
     * A learner of the trail reads each step's state and the count of steps done; anyone else reads none.
     */
    public function test_a_learner_reads_the_states(): void {
        $alfa = $this->ref('Alfa', self::at(3, 2), self::at(3, 10));
        $beta = $this->ref('Beta', self::at(3, 11), self::at(3, 31));
        $trail = $this->trail('Trilha', [$alfa, $beta]);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        (new \completion_completion(['course' => $alfa->id, 'userid' => $learner->id]))->mark_complete(self::at(3, 9));
        $this->become($learner);

        $answer = $this->answer('2027-03', 0, false, 0, self::at(3, 15));
        $steps = $answer['trails'][0]['steps'];
        $this->assertTrue($answer['trails'][0]['enrolled']);
        $this->assertSame(1, $answer['trails'][0]['done']);
        $this->assertSame([progress::DONE, progress::CURRENT], array_column($steps, 'state'));
        $this->assertSame(self::at(3, 9), $steps[0]['completed']);
        $this->assertSame([false, true], array_column($steps, 'next'));

        // Past the second step's end it is late, and still the next.
        $late = $this->answer('2027-03', 0, false, 0, self::at(4, 15))['trails'][0]['steps'][1];
        $this->assertSame(progress::LATE, $late['state']);
        $this->assertTrue($late['next'], 'A late step is still the next one.');

        // The controls: another account of the same page, and one whose enrolment is suspended, read the schedule alone.
        $suspended = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($suspended->id, $trail->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        foreach (['no tie' => self::getDataGenerator()->create_user(), 'suspended' => $suspended] as $name => $user) {
            $this->become($user);
            $other = $this->answer('2027-03', 0, false, 0, self::at(3, 15))['trails'][0];
            $this->assertFalse($other['enrolled'], $name);
            $this->assertSame(['', ''], array_column($other['steps'], 'state'), $name);
            $this->assertSame(0, $other['done'], $name);
        }
    }

    /**
     * "Trilha" keeps one trail and "Só as minhas" the viewer's own; a trail id the viewer may not see keeps nothing.
     */
    public function test_the_filters_keep_one_trail_or_the_viewers_own(): void {
        $mine = $this->trail('Minha', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $other = $this->trail('Outra', [$this->ref('Beta', self::at(3, 5), self::at(3, 25))]);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $mine->id, 'student');
        $this->become($learner);

        $this->assertSame([(int) $mine->id, (int) $other->id], self::ids($this->answer('2027-03')), 'Precondition.');
        $this->assertSame([(int) $other->id], self::ids($this->answer('2027-03', (int) $other->id)));
        $this->assertSame([(int) $mine->id], self::ids($this->answer('2027-03', 0, true)));
        $this->assertSame([], self::ids($this->answer('2027-03', (int) $other->id, true)), 'Both filters apply.');
        $this->assertSame([], self::ids($this->answer('2027-03', 999999)), 'An id that is no trail keeps nothing.');
        $this->assertCount(2, $this->answer('2027-03', 999999)['options']);
    }

    /**
     * A trail the showcase would not list to this account is never named, in the rows or the choice: a hidden trail
     * course, even to a student of it, and an unlisted one to an account with no tie to it; the same trail is offered
     * once the account is enrolled in it.
     */
    public function test_a_trail_the_account_may_not_discover_is_never_named(): void {
        $listed = $this->trail('Listada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $hidden = $this->trail('Oculta', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))], ['visible' => 0]);
        // Closed: an unlisted course an account could enrol in now is discoverable to it, which is not this test's case.
        $unlisted = $this->trail('Não listada', [$this->ref('Gama', self::at(3, 2), self::at(3, 20))], [], false);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $reader = self::getDataGenerator()->create_user();
        // Enrolled in the hidden trail: the view's rule would keep it, so only the gate withholds it.
        self::getDataGenerator()->enrol_user($reader->id, $hidden->id, 'student');
        $this->become($reader);

        $answer = $this->answer('2027-03');
        $this->assertSame([(int) $listed->id], self::ids($answer));
        $this->assertSame([(int) $listed->id], array_column($answer['options'], 'id'));
        $this->assertSame([], self::ids($this->answer('2027-03', (int) $hidden->id)));

        self::getDataGenerator()->enrol_user($reader->id, $unlisted->id, 'student');
        $this->become($reader);
        $this->assertSame([(int) $listed->id, (int) $unlisted->id], self::ids($this->answer('2027-03')));
    }

    /**
     * The cap counts the trails the account may discover: a page of trail courses it may not see takes no admitted
     * trail's place and says nothing of itself.
     */
    public function test_the_cap_counts_the_trails_the_account_may_discover(): void {
        for ($i = 0; $i < trails::CAP; $i++) {
            $this->course(['fullname' => "Oculta {$i}", 'visible' => 0], [
                landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL,
            ]);
        }
        // A later category: its courses sort after every hidden one, on the second page of candidates.
        $this->category = self::getDataGenerator()->create_category(['name' => 'Depois']);
        $listed = $this->trail('Listada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $answer = $this->answer('2027-03');
        $this->assertSame([(int) $listed->id], self::ids($answer));
        $this->assertFalse($answer['truncated'], 'Trails the account may not see are not "more".');
    }

    /**
     * The cap counts the trails the account is shown: a page of listed trails it has no tie to and no way into takes no
     * shown trail's place and says nothing of itself. The control: an administrator, shown every one, is told "more".
     */
    public function test_the_cap_counts_the_trails_the_account_is_shown(): void {
        for ($i = 0; $i < trails::CAP; $i++) {
            $this->course(['fullname' => "Fechada {$i}"], [landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL]);
        }
        $this->category = self::getDataGenerator()->create_category(['name' => 'Depois']);
        $open = $this->trail('Aberta', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $answer = $this->answer('2027-03');
        $this->assertSame([(int) $open->id], self::ids($answer));
        $this->assertFalse($answer['truncated'], 'Trails the account is not shown are not "more".');

        $this->become(get_admin());
        $this->assertTrue($this->answer('2027-03')['truncated'], 'Control: shown every trail, the administrator is.');
    }

    /**
     * A trail with no way in for an account with no tie to it is named nowhere: no row, no choice, not by its id; nor is
     * one whose only method refuses the account. The control is the same trail once its self enrolment opens.
     */
    public function test_a_trail_with_no_way_in_is_not_shown(): void {
        $trail = $this->trail('Fechada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        // A method that refuses the account (new enrolments off) is no way in either: blocked is no route.
        $blocked = $this->trail('Recusada', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))], [], false);
        $this->enable_instance((int) $blocked->id, 'self', ['customint6' => 0]);
        $reader = self::getDataGenerator()->create_user();
        $this->become($reader);

        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id));
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $blocked->id), 'Blocked.');
        $this->assertSame([], self::ids($this->answer('2027-03', (int) $trail->id)), 'Not by its id either.');

        $this->setAdminUser();
        $this->enable_instance((int) $trail->id, 'self', ['customint6' => 1]);
        $this->become($reader);
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: a way in shows it.');
    }

    /**
     * A learner of the trail is shown it with no way in left; somebody else is not.
     */
    public function test_an_enrolled_learner_is_shown_a_trail_with_no_way_in(): void {
        $trail = $this->trail('Fechada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $this->become($learner);
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id));

        $this->become(self::getDataGenerator()->create_user());
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: no tie, no way in.');
    }

    /**
     * An enrolment that starts later is shown the trail with no way in; somebody else is not.
     */
    public function test_a_scheduled_enrolment_is_shown_a_trail_with_no_way_in(): void {
        $trail = $this->trail('Fechada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $scheduled = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($scheduled->id, $trail->id, 'student', 'manual', time() + WEEKSECS);
        $this->become($scheduled);
        $answer = $this->answer('2027-03');
        $this->assertTrue($this->names($answer, (int) $trail->id));
        $this->assertFalse($answer['trails'][0]['enrolled'], 'Not enrolled yet: no progress.');

        $this->become(self::getDataGenerator()->create_user());
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: no tie, no way in.');
    }

    /**
     * A suspended enrolment keeps nothing: with no way in left the trail is hidden; with an open way in (a method the
     * account holds no row on) it is shown again.
     */
    public function test_a_suspended_enrolment_is_shown_the_trail_only_with_a_way_in(): void {
        $trail = $this->trail('Fechada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $suspended = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($suspended->id, $trail->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->become($suspended);
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id));

        $this->setAdminUser();
        $this->enable_instance((int) $trail->id, 'self', ['customint6' => 1]);
        $this->become($suspended);
        $answer = $this->answer('2027-03');
        $this->assertTrue($this->names($answer, (int) $trail->id), 'An open way in shows it.');
        $this->assertFalse($answer['trails'][0]['enrolled'], 'Shown as to anybody: no progress.');
    }

    /**
     * Give a trail an enrol_apply instance closed to new applications, or skip where enrol_apply is not installed.
     *
     * @param \stdClass $trail The trail course.
     * @return void
     */
    private function closed_applications(\stdClass $trail): void {
        global $DB;
        if (!\core_component::get_component_directory('enrol_apply')) {
            $this->markTestSkipped('enrol_apply is not installed.');
        }
        $this->enable_enrol('apply');
        enrol_get_plugin('apply')->add_instance($trail, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => (int) $DB->get_field('role', 'id', ['shortname' => 'student']),
            'customint6' => 0,
        ]);
    }

    /**
     * An application awaiting a decision keeps the trail shown though applications are closed; an account with no
     * application is not shown it. Runs only where enrol_apply is installed.
     */
    public function test_an_application_awaiting_a_decision_is_shown_the_trail(): void {
        $trail = $this->trail('Inscrições', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $this->closed_applications($trail);
        $pending = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($pending->id, $trail->id, null, 'apply', 0, 0, ENROL_USER_SUSPENDED);
        $this->become($pending);
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id));

        $this->become(self::getDataGenerator()->create_user());
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: applications are closed.');
    }

    /**
     * A waiting-list place keeps the trail shown though applications are closed; an account with none is not shown it.
     * Runs only where enrol_apply is installed.
     */
    public function test_a_waiting_list_place_is_shown_the_trail(): void {
        $trail = $this->trail('Espera', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $this->closed_applications($trail);
        $waiting = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($waiting->id, $trail->id, null, 'apply', 0, 0, 2);
        $this->become($waiting);
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id));

        $this->become(self::getDataGenerator()->create_user());
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: applications are closed.');
    }

    /**
     * Whoever may edit the trail course is shown it with no enrolment and no way in: an editing teacher by a role
     * assignment alone, and a site administrator. The controls: a non-editing teacher by the same kind of assignment,
     * enrolled nowhere, and an account that may edit another course only, are not.
     */
    public function test_staff_are_shown_a_trail_with_no_way_in(): void {
        global $DB;
        $trail = $this->trail('Fechada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $context = \core\context\course::instance($trail->id);
        $editor = self::getDataGenerator()->create_user();
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']), $editor->id, $context->id);
        $teacher = self::getDataGenerator()->create_user();
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'teacher']), $teacher->id, $context->id);
        $elsewhere = $this->user_with('moodle/course:update', \core\context\course::instance($this->course([])->id));

        foreach (['editing teacher' => $editor, 'administrator' => get_admin()] as $name => $user) {
            $this->become($user);
            $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id), $name);
        }
        foreach (['a teacher who may not edit' => $teacher, 'an editor of another course' => $elsewhere] as $name => $user) {
            $this->become($user);
            $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), "Control: {$name}.");
        }
    }

    /**
     * The rule's two lists are local_unlistedcourses' own, copied because the plugin keeps them private: the
     * relationships that keep an unlisted course discoverable and the next actions that let a viewer in. A change of
     * the plugin's reddens this before it parts the theme from the plugin.
     */
    public function test_the_rules_lists_are_the_plugins_own(): void {
        $plugin = new \ReflectionClass(\local_unlistedcourses\access::class);
        $theme = new \ReflectionClass(trails::class);
        foreach (['DISCOVERABLE_RELATIONSHIPS' => 'KEPT_RELATIONSHIPS', 'ENROLABLE_ACTIONS' => 'ROUTES'] as $theirs => $ours) {
            $this->assertEqualsCanonicalizing(
                $plugin->getReflectionConstant($theirs)->getValue(),
                $theme->getReflectionConstant($ours)->getValue(),
                $ours
            );
        }
    }

    /**
     * One viewer for the whole rule: a scope built for one account and answered while another is the current user
     * is answered nothing. The control: the current user's own scope.
     */
    public function test_a_scope_of_another_viewer_is_answered_nothing(): void {
        $trail = $this->trail('Aberta', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());
        $scope = scope::for_viewer(0);
        $window = trails::month_window('2027-03');
        $this->become(self::getDataGenerator()->create_user());
        $this->assertSame([], trails::answer($window, $scope, 0, false, time())['trails']);
        $this->assertSame(
            [(int) $trail->id],
            self::ids(trails::answer($window, scope::for_viewer(0), 0, false, time())),
            'Control: the current user\'s own scope.'
        );
    }

    /**
     * A public trail is shown to every account, with no tie and no way in; the same trail, listed, is not.
     */
    public function test_a_public_trail_is_shown_to_every_account(): void {
        global $DB;
        $trail = $this->trail('Pública', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $this->set_course_state((int) $trail->id, discoverability::STATE_PUBLIC);
        $reader = self::getDataGenerator()->create_user();
        $this->become($reader);
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id));

        // The landing page's question answers a visitor no, public or not, before anything is read.
        $this->setGuestUser();
        $before = $DB->perf_get_reads();
        $this->assertFalse(trails::offers((int) $trail->id), 'A visitor.');
        $this->assertSame(0, $DB->perf_get_reads() - $before, 'A visitor costs no statement.');
        $this->become($reader);
        $this->assertTrue(trails::offers((int) $trail->id), 'Control: an account.');

        $this->set_course_state((int) $trail->id, discoverability::STATE_DEFAULT);
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: listed, it is not.');
    }

    /**
     * A public trail is decided before the plugin's next-action batch, which it is not asked about: deciding it costs
     * fewer statements than deciding a listed trail with a way in, which the batch is asked about. The two trails differ
     * in their state alone (the same open method, one listed step each), so both pay the same reading of their steps after
     * the rule and a batch asked about the public one would cost what it costs the other. Both are measured warm (core's
     * caches and the plugin's public memo), through the landing page's question, which takes the answer's path over one
     * course.
     */
    public function test_a_public_trail_costs_no_next_action_read(): void {
        global $DB;
        $public = $this->trail('Pública', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $this->set_course_state((int) $public->id, discoverability::STATE_PUBLIC);
        $open = $this->trail('Aberta', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());

        $reads = [];
        foreach (['public' => $public, 'open' => $open] as $name => $trail) {
            $shown = trails::offers((int) $trail->id);
            $before = $DB->perf_get_reads();
            $this->assertSame($shown, trails::offers((int) $trail->id), "Precondition: {$name} answers alike.");
            $reads[$name] = $DB->perf_get_reads() - $before;
            $this->assertTrue($shown, "Precondition: {$name} is shown.");
        }
        $this->assertLessThan($reads['open'], $reads['public'], 'The batch is not asked about a public trail.');
    }

    /**
     * The landing page's question asks what the view would draw as well as whom it shows: a learner of a trail whose
     * one course is unlisted, with no tie to it, is offered no link, and the view lists the trail neither in its rows
     * nor in its choice. The control: enrolled in that course as well, the learner is offered both.
     */
    public function test_a_trail_with_no_course_to_draw_is_not_offered(): void {
        $ref = $this->ref('Alfa', self::at(3, 2), self::at(3, 20));
        $this->set_course_state((int) $ref->id, discoverability::STATE_UNLISTED);
        $trail = $this->trail('Fechada', [$ref], [], false);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $this->become($learner);
        $this->assertFalse(trails::offers((int) $trail->id), 'None of its courses is drawn: no link.');
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Nor the trail in the view.');

        self::getDataGenerator()->enrol_user($learner->id, $ref->id, 'student');
        $this->become($learner);
        $this->assertTrue(trails::offers((int) $trail->id), 'Control: its course is drawn, so is the link.');
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: and the trail.');
    }

    /**
     * Guest access is a way in, free or behind a key; the control is the same trail with guest access off.
     */
    public function test_guest_access_is_a_way_in(): void {
        $free = $this->trail('Livre', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $this->enable_instance((int) $free->id, 'guest');
        $key = $this->trail('Chave', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))], [], false);
        $this->enable_instance((int) $key->id, 'guest', ['password' => 'segredo']);
        $off = $this->trail('Sem acesso', [$this->ref('Gama', self::at(3, 2), self::at(3, 20))], [], false);
        $this->become(self::getDataGenerator()->create_user());

        $answer = $this->answer('2027-03');
        $this->assertTrue($this->names($answer, (int) $free->id), 'Free.');
        $this->assertTrue($this->names($answer, (int) $key->id), 'Behind a key.');
        $this->assertFalse($this->names($answer, (int) $off->id), 'Control: no guest access.');
    }

    /**
     * A conditional enrolment is a way in for an account actively enrolled in the course it waits for, and for nobody
     * else. Runs only where enrol_coursecompleted is installed: the plugin's batch asks the plugin.
     */
    public function test_a_conditional_enrolment_is_a_way_in(): void {
        if (!\core_component::get_component_directory('enrol_coursecompleted')) {
            $this->markTestSkipped('enrol_coursecompleted is not installed.');
        }
        $prerequisite = $this->course(['fullname' => 'Antes']);
        $trail = $this->trail('Depois', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))], [], false);
        $this->completed_instance($trail, (int) $prerequisite->id);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $prerequisite->id, 'student');
        $this->become($learner);
        $this->assertTrue($this->names($this->answer('2027-03'), (int) $trail->id));

        $this->become(self::getDataGenerator()->create_user());
        $this->assertFalse($this->names($this->answer('2027-03'), (int) $trail->id), 'Control: not working towards it.');
    }

    /**
     * A public trail does not open its steps: an unlisted step stays hidden from an account with no tie to it, beside a
     * public step drawn in full. The control: an account enrolled in the unlisted course is drawn both.
     */
    public function test_a_public_trail_keeps_its_unlisted_step_hidden(): void {
        $public = $this->ref('Aberto', self::at(3, 2), self::at(3, 10));
        $unlisted = $this->ref('Reservado', self::at(3, 11), self::at(3, 20));
        $trail = $this->trail('Pública', [$public, $unlisted], [], false);
        foreach ([$trail, $public] as $course) {
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $reader = self::getDataGenerator()->create_user();
        $this->become($reader);

        $answer = $this->answer('2027-03');
        $this->assertSame([(int) $trail->id], self::ids($answer), 'The trail is shown.');
        $this->assertSame([(int) $public->id], array_column($answer['trails'][0]['steps'], 'courseid'));

        $member = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($member->id, $unlisted->id, 'student');
        $this->become($member);
        $steps = $this->answer('2027-03')['trails'][0]['steps'];
        $this->assertSame([(int) $public->id, (int) $unlisted->id], array_column($steps, 'courseid'), 'Control.');
    }

    /**
     * A visitor is answered nothing, whatever the trails, and at no cost: the view is an account's.
     */
    public function test_a_visitor_is_answered_nothing(): void {
        global $DB;
        $this->trail('Listada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());
        $this->assertCount(1, $this->answer('2027-03')['trails'], 'Precondition: an account sees the trail.');

        $this->setGuestUser();
        $answer = $this->answer('2027-03');
        $this->assertSame([], $answer['trails']);
        $this->assertSame([], $answer['options']);
        // Answered before anything is read: the view's rule would also refuse a visitor, but only after the candidates.
        [$window, $scope] = [trails::month_window('2027-03'), scope::for_viewer(0)];
        $before = $DB->perf_get_reads();
        trails::answer($window, $scope, 0, false, time());
        $this->assertSame(0, $DB->perf_get_reads() - $before, 'A visitor costs no statement.');
    }

    /**
     * A step whose course this account may not see is not drawn, and the positions count the steps drawn.
     */
    public function test_a_step_the_account_may_not_see_is_not_drawn(): void {
        $alfa = $this->ref('Alfa', self::at(3, 2), self::at(3, 10));
        $hidden = $this->ref('Oculto', self::at(3, 11), self::at(3, 20), ['visible' => 0]);
        $gama = $this->ref('Gama', self::at(3, 21), self::at(3, 31));
        $this->trail('Trilha', [$alfa, $hidden, $gama]);
        $this->become(self::getDataGenerator()->create_user());

        $steps = $this->answer('2027-03')['trails'][0]['steps'];
        $this->assertSame(['Alfa', 'Gama'], array_column($steps, 'name'));
        $this->assertSame([1, 2], array_column($steps, 'position'));
    }

    /**
     * The calendar's category narrowing applies to the trail course, not to its steps' courses.
     */
    public function test_the_category_narrowing_applies_to_the_trail_course(): void {
        $other = self::getDataGenerator()->create_category(['name' => 'Saúde']);
        $here = $this->trail('Aqui', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $there = $this->trail('Lá', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))], ['category' => $other->id]);
        $this->become(self::getDataGenerator()->create_user());

        $this->assertSame([(int) $here->id, (int) $there->id], self::ids($this->answer('2027-03')), 'Precondition.');
        $this->assertSame([(int) $there->id], self::ids($this->answer('2027-03', 0, false, (int) $other->id)));
        $this->assertSame([(int) $there->id], array_column($this->answer('2027-03', 0, false, (int) $other->id)['options'], 'id'));
    }

    /**
     * A course of another page model is no trail, and without mod_subcourse enabled there are none.
     */
    public function test_only_trails_and_only_with_the_module(): void {
        $trail = $this->trail('Trilha', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $course = $this->course(['fullname' => 'Curso', 'startdate' => self::at(3, 1)], [
            landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        self::getDataGenerator()->create_module('subcourse', ['course' => $course->id, 'refcourse' => $trail->id]);
        // Open to the reader as the trail is, so only its model keeps it out (the view's rule would hide it otherwise).
        $this->enable_instance((int) $course->id, 'self', ['customint6' => 1]);
        $this->become(self::getDataGenerator()->create_user());
        $this->assertSame([(int) $trail->id], self::ids($this->answer('2027-03')));

        \core\plugininfo\mod::enable_plugin(trail::MODNAME, 0);
        $answer = $this->answer('2027-03');
        $this->assertSame([], $answer['trails']);
        $this->assertSame([], $answer['options']);
    }

    /**
     * Six trails cost the statements two do, for a learner of every one of them (progress included), for an account
     * enrolled in none (shown each trail by its open self enrolment: the public batch and the next-action batch, once
     * each) and for a manager enrolled in none (shown each by the capability, which reads no statement per trail), over
     * one month, the month and the next and three months (the window is no statement's): the answer reads
     * the trails and their steps in batches. Core's caches (each trail's modinfo, its
     * contexts and its completion data) are warm on a second answer, which is what is measured.
     */
    public function test_six_trails_cost_what_two_do(): void {
        global $DB;
        $learner = self::getDataGenerator()->create_user();
        $reader = self::getDataGenerator()->create_user();
        $manager = $this->user_with('moodle/course:update', \core\context\system::instance());
        $categories = [];
        foreach (['small' => 2, 'big' => 6] as $name => $count) {
            $category = self::getDataGenerator()->create_category(['name' => $name]);
            $categories[$name] = (int) $category->id;
            for ($i = 0; $i < $count; $i++) {
                $refs = [
                    $this->ref("{$name} {$i} a", self::at(3, 2), self::at(3, 10), ['category' => $category->id]),
                    $this->ref("{$name} {$i} b", self::at(3, 11), self::at(3, 20), ['category' => $category->id]),
                ];
                $trail = $this->trail("{$name} {$i}", $refs, ['category' => $category->id]);
                self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
            }
        }

        foreach (['learner' => $learner, 'reader' => $reader, 'manager' => $manager] as $who => $user) {
            $this->become($user);
            $reads = [];
            foreach ([1, trails::OPENING, trails::MONTHS] as $months) {
                foreach ($categories as $name => $categoryid) {
                    $scope = scope::for_viewer($categoryid);
                    $window = trails::month_window('2027-03', $months);
                    trails::answer($window, $scope, 0, false, self::at(3, 15));
                    self::forget();
                    $before = $DB->perf_get_reads();
                    $answer = trails::answer($window, $scope, 0, false, self::at(3, 15));
                    $reads[$months][$name] = $DB->perf_get_reads() - $before;
                    $this->assertCount($name === 'small' ? 2 : 6, $answer['trails'], "Precondition: {$who} {$name} {$months}.");
                    $this->assertSame(
                        $who === 'learner',
                        $answer['trails'][0]['enrolled'],
                        "Precondition: {$who} reads progress or not."
                    );
                }
                $this->assertSame($reads[$months]['small'], $reads[$months]['big'], "{$who}: four more trails add no statement.");
            }
            $this->assertSame($reads[1], $reads[trails::OPENING], "{$who}: the month and the next cost what one month does.");
            $this->assertSame($reads[1], $reads[trails::MONTHS], "{$who}: three months cost what one month does.");
        }
    }

    /**
     * A one-pixel PNG as a course's own image.
     *
     * @param \stdClass $course The course.
     * @return void
     */
    private static function upload_image(\stdClass $course): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.png',
        ], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    /**
     * Draw the global page in the theme, so the renderer cards::images() asks is the theme's and Boost Union's settings
     * apply, and let a course keep one image.
     *
     * @return void
     */
    private static function theme(): void {
        global $CFG, $PAGE;

        $PAGE->set_context(\context_system::instance());
        $PAGE->force_theme('dimensions');
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
    }

    /**
     * Each step carries its course's card image (the popover's cover): the course's own image, else the renderer's
     * cover for a course with none; nothing while Boost Union's card view is off.
     */
    public function test_each_step_carries_its_courses_image(): void {
        global $PAGE;
        self::theme();
        $with = $this->ref('Com imagem', self::at(3, 2), self::at(3, 10));
        $without = $this->ref('Sem imagem', self::at(3, 11), self::at(3, 20));
        self::upload_image($with);
        $this->trail('Trilha', [$with, $without]);
        $this->become(self::getDataGenerator()->create_user());

        $steps = $this->answer('2027-03')['trails'][0]['steps'];
        $this->assertSame([(int) $with->id, (int) $without->id], array_column($steps, 'courseid'));
        $this->assertStringEndsWith('/course/overviewfiles/capa.png', $steps[0]['image'], 'The course\'s own image.');
        $this->assertStringStartsWith(cover::PREFIX, $steps[1]['image'], 'The theme\'s cover for a course with none.');
        $this->assertSame(
            $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL)->get_generated_image_for_id((int) $without->id),
            $steps[1]['image'],
            'The renderer\'s own answer.'
        );

        set_config(boost_union::SETTING_IMAGES, 'list,summary', boost_union::COMPONENT);
        $this->assertSame(['', ''], array_column($this->answer('2027-03')['trails'][0]['steps'], 'image'), 'No card view.');
    }

    /**
     * The steps' images are one batch: two trails of two steps and six trails of two steps each pay one statement
     * for them (the {files} read, the renderer's covers coming from the warm branding cache), measured as the answer
     * with Boost Union's card view on less the same answer with it off, every course with an image of its own but one.
     */
    public function test_the_steps_images_cost_one_statement(): void {
        global $DB;
        self::theme();
        $reader = self::getDataGenerator()->create_user();
        $categories = [];
        foreach (['small' => 2, 'big' => 6] as $name => $count) {
            $category = self::getDataGenerator()->create_category(['name' => $name]);
            $categories[$name] = (int) $category->id;
            for ($i = 0; $i < $count; $i++) {
                $refs = [
                    $this->ref("{$name} {$i} a", self::at(3, 2), self::at(3, 10), ['category' => $category->id]),
                    $this->ref("{$name} {$i} b", self::at(3, 11), self::at(3, 20), ['category' => $category->id]),
                ];
                if ($i > 0) {
                    self::upload_image($refs[0]);
                }
                self::upload_image($refs[1]);
                $this->trail("{$name} {$i}", $refs, ['category' => $category->id]);
            }
        }
        $this->become($reader);

        $extra = [];
        foreach ($categories as $name => $categoryid) {
            $reads = [];
            foreach (['card,list,summary' => 'on', 'list,summary' => 'off'] as $value => $setting) {
                set_config(boost_union::SETTING_IMAGES, $value, boost_union::COMPONENT);
                $scope = scope::for_viewer($categoryid);
                $window = trails::month_window('2027-03');
                trails::answer($window, $scope, 0, false, self::at(3, 15));
                self::forget();
                $before = $DB->perf_get_reads();
                $answer = trails::answer($window, $scope, 0, false, self::at(3, 15));
                $reads[$setting] = $DB->perf_get_reads() - $before;
                $images = [];
                foreach ($answer['trails'] as $trail) {
                    $images = array_merge($images, array_column($trail['steps'], 'image'));
                }
                $this->assertCount($name === 'small' ? 4 : 12, $images, "Precondition: {$name} {$setting}.");
                $this->assertSame($setting === 'off', in_array('', $images, true), "Precondition: {$name} {$setting} images.");
            }
            $extra[$name] = $reads['on'] - $reads['off'];
        }
        $this->assertSame(['small' => 1, 'big' => 1], $extra, 'One statement for the images, whatever the count of steps.');
    }

    /**
     * Trails of the given number of steps each, in a category, all the learner's, and some of their steps' courses too.
     *
     * Step j of a trail runs five days from the 2nd, 9th, 16th or 23rd of March, April or May (four to a month), so the
     * steps fall across the three months' window. The learner is enrolled in every second referenced course and has
     * completed every fourth.
     *
     * @param \core_course_category $category The category of the trails and of their steps' courses.
     * @param int $trails How many trails.
     * @param int $steps How many steps each.
     * @param \stdClass $learner The account enrolled in every trail.
     */
    private function trails_across_the_window(\core_course_category $category, int $trails, int $steps, \stdClass $learner): void {
        $generator = self::getDataGenerator();
        for ($i = 0; $i < $trails; $i++) {
            $refs = [];
            for ($j = 0; $j < $steps; $j++) {
                $month = 3 + intdiv($j, 4);
                $day = 2 + ($j % 4) * 7;
                $ref = $this->ref(
                    "{$category->name} {$i} step {$j}",
                    self::at($month, $day),
                    self::at($month, $day + 5),
                    ['category' => $category->id]
                );
                if ($j % 2 === 0) {
                    $generator->enrol_user($learner->id, $ref->id, 'student');
                }
                if ($j % 4 === 0) {
                    (new \completion_completion(['course' => $ref->id, 'userid' => $learner->id]))
                        ->mark_complete(self::at($month, $day + 3));
                }
                $refs[] = $ref;
            }
            $trail = $this->trail("{$category->name} {$i}", $refs, ['category' => $category->id]);
            $generator->enrol_user($learner->id, $trail->id, 'student');
        }
    }

    /**
     * Ten trails of ten courses (a hundred steps and a hundred referenced courses) cost the statements two trails of two
     * do, for a learner of every trail who is enrolled in half the courses and has completed a quarter of them, for an
     * account enrolled in nothing (shown the trails by their open self enrolment) and for a manager enrolled in nothing
     * (shown them by the capability), over one month, the month and the next and three months. Core's caches are warm on a
     * second answer, which is what is measured. A slow test: the fixture builds 116 courses and 104 steps.
     */
    public function test_ten_trails_of_ten_courses_cost_what_two_of_two_do(): void {
        global $DB;
        $learner = self::getDataGenerator()->create_user();
        $reader = self::getDataGenerator()->create_user();
        $manager = $this->user_with('moodle/course:update', \core\context\system::instance());
        $categories = [];
        foreach (['few' => [2, 2], 'many' => [10, 10]] as $name => [$trails, $steps]) {
            $category = self::getDataGenerator()->create_category(['name' => $name]);
            $categories[$name] = $category;
            $this->trails_across_the_window($category, $trails, $steps, $learner);
        }

        foreach (['learner' => $learner, 'reader' => $reader, 'manager' => $manager] as $who => $user) {
            $this->become($user);
            $reads = [];
            foreach ([1, trails::OPENING, trails::MONTHS] as $months) {
                foreach ($categories as $name => $category) {
                    $scope = scope::for_viewer((int) $category->id);
                    $window = trails::month_window('2027-03', $months);
                    trails::answer($window, $scope, 0, false, self::at(3, 15));
                    self::forget();
                    $before = $DB->perf_get_reads();
                    $answer = trails::answer($window, $scope, 0, false, self::at(3, 15));
                    $reads[$months][$name] = $DB->perf_get_reads() - $before;
                    $expected = $name === 'few' ? [2, 2] : [10, 10];
                    $this->assertCount($expected[0], $answer['trails'], "Precondition: {$who} {$name} {$months} trails.");
                    foreach ($answer['trails'] as $trail) {
                        $this->assertCount($expected[1], $trail['steps'], "Precondition: {$who} {$name} {$months} steps.");
                        $this->assertSame($who === 'learner', $trail['enrolled'], "Precondition: {$who} reads progress or not.");
                    }
                }
                $this->assertSame(
                    $reads[$months]['few'],
                    $reads[$months]['many'],
                    "{$who}: eight more trails and ninety-six more steps add no statement."
                );
            }
            $this->assertSame($reads[1], $reads[trails::OPENING], "{$who}: the month and the next cost what one month does.");
            $this->assertSame($reads[1], $reads[trails::MONTHS], "{$who}: three months cost what one month does.");
        }
    }
}
