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
 * The trails of a month (docs/trail/PLAN.md section 7): which trails, which steps, their states, the filters, the cost.
 *
 * Every test needs mod_subcourse and skips without it. The months are March and June 2027, in the viewer's timezone.
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
     * @param string $name The trail's name.
     * @param \stdClass[] $refs The referenced courses.
     * @param array $record Further course fields.
     * @return \stdClass The trail course.
     */
    private function trail(string $name, array $refs, array $record = []): \stdClass {
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
        return $trail;
    }

    /**
     * The answer for a month, as the current user, in a new request's state.
     *
     * @param string $month The month, YYYY-MM.
     * @param int $trailid One trail, 0 for every trail.
     * @param bool $mine Only the viewer's own.
     * @param int $categoryid The category narrowing.
     * @param int|null $now The moment, now by default.
     * @return array
     */
    private function answer(string $month, int $trailid = 0, bool $mine = false, int $categoryid = 0, ?int $now = null): array {
        self::forget();
        get_fast_modinfo(0, 0, true);
        return trails::answer(trails::month_window($month), scope::for_viewer($categoryid), $trailid, $mine, $now ?? time());
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
     * course, and an unlisted one to an account with no tie to it; the same trail is offered once the account is
     * enrolled in it.
     */
    public function test_a_trail_the_account_may_not_discover_is_never_named(): void {
        $listed = $this->trail('Listada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $hidden = $this->trail('Oculta', [$this->ref('Beta', self::at(3, 2), self::at(3, 20))], ['visible' => 0]);
        $unlisted = $this->trail('Não listada', [$this->ref('Gama', self::at(3, 2), self::at(3, 20))]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $reader = self::getDataGenerator()->create_user();
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
     * A visitor is answered nothing, whatever the trails: the view is an account's.
     */
    public function test_a_visitor_is_answered_nothing(): void {
        $this->trail('Listada', [$this->ref('Alfa', self::at(3, 2), self::at(3, 20))]);
        $this->become(self::getDataGenerator()->create_user());
        $this->assertCount(1, $this->answer('2027-03')['trails'], 'Precondition: an account sees the trail.');

        $this->setGuestUser();
        $answer = $this->answer('2027-03');
        $this->assertSame([], $answer['trails']);
        $this->assertSame([], $answer['options']);
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
        $this->become(self::getDataGenerator()->create_user());
        $this->assertSame([(int) $trail->id], self::ids($this->answer('2027-03')));

        \core\plugininfo\mod::enable_plugin(trail::MODNAME, 0);
        $answer = $this->answer('2027-03');
        $this->assertSame([], $answer['trails']);
        $this->assertSame([], $answer['options']);
    }

    /**
     * Six trails cost the statements two do, for a learner of every one of them (progress included) and for an account
     * enrolled in none: the answer reads the trails and their steps in batches. Core's caches (each trail's modinfo, its
     * contexts and its completion data) are warm on a second answer, which is what is measured.
     */
    public function test_six_trails_cost_what_two_do(): void {
        global $DB;
        $learner = self::getDataGenerator()->create_user();
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
                $trail = $this->trail("{$name} {$i}", $refs, ['category' => $category->id]);
                self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
            }
        }

        foreach (['learner' => $learner, 'reader' => $reader] as $who => $user) {
            $this->become($user);
            $reads = [];
            foreach ($categories as $name => $categoryid) {
                $scope = scope::for_viewer($categoryid);
                $window = trails::month_window('2027-03');
                trails::answer($window, $scope, 0, false, self::at(3, 15));
                self::forget();
                $before = $DB->perf_get_reads();
                $answer = trails::answer($window, $scope, 0, false, self::at(3, 15));
                $reads[$name] = $DB->perf_get_reads() - $before;
                $this->assertCount($name === 'small' ? 2 : 6, $answer['trails'], "Precondition: {$who} {$name}.");
                $this->assertSame(
                    $who === 'learner',
                    $answer['trails'][0]['enrolled'],
                    "Precondition: {$who} reads progress or not."
                );
            }
            $this->assertSame($reads['small'], $reads['big'], "{$who}: four more trails add no statement.");
        }
    }
}
