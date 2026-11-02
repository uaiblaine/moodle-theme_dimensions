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
 * Theme Dimensions - Tests for the trail reader
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the trail reader: which activities are steps, in which order, with which window.
 *
 * Every test needs mod_subcourse and skips without it; GitHub's CI and `mdl ci` install it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(trail::class)]
#[CoversClass(step::class)]
final class trail_test extends \advanced_testcase {
    /**
     * Skip without the module; every test writes.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A course for a step to refer to.
     *
     * @param int $start Its start date.
     * @param int $end Its end date, 0 for none.
     * @param array $extra More course fields.
     * @return \stdClass The course.
     */
    private function refcourse(int $start, int $end, array $extra = []): \stdClass {
        return self::getDataGenerator()->create_course(['startdate' => $start, 'enddate' => $end] + $extra);
    }

    /**
     * A Subcourse activity of a trail.
     *
     * @param \stdClass $trail The trail course.
     * @param int $refcourseid The course it refers to.
     * @param int $section The section it goes in.
     * @param array $extra More activity settings.
     * @return \stdClass The activity, with its cmid.
     */
    private function activity(\stdClass $trail, int $refcourseid, int $section, array $extra = []): \stdClass {
        return self::getDataGenerator()->create_module('subcourse', [
            'course' => $trail->id,
            'refcourse' => $refcourseid,
            'section' => $section,
        ] + $extra);
    }

    /**
     * Steps are the shown Subcourse activities in course order, each with its course's window or its expected date.
     *
     * The page sits between two steps; the hidden, stealth, deleting and orphaned activities are Subcourse activities
     * that are no step, each a neighbour of a step that stays.
     */
    public function test_steps_follow_the_course_and_skip_what_is_no_step(): void {
        global $DB;

        // Core keeps an activity off the course page only where the site allows stealth activities.
        set_config('allowstealth', 1);
        $day = 86400;
        $t0 = make_timestamp(2026, 11, 2);
        $trail = self::getDataGenerator()->create_course(['enablecompletion' => 1, 'numsections' => 3]);
        $first = $this->refcourse($t0, $t0 + 6 * $day);
        $second = $this->refcourse($t0 + 7 * $day, $t0 + 13 * $day);
        $third = $this->refcourse($t0 + 14 * $day, 0, ['visible' => 0]);
        $gone = $this->refcourse($t0, $t0 + $day);

        $later = $this->activity($trail, (int) $first->id, 2, ['name' => 'Fundamentos']);
        $expected = $t0 + 10 * $day;
        $earlier = $this->activity($trail, (int) $second->id, 1, [
            'name' => 'Orçamento & finanças',
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionexpected' => $expected,
        ]);
        self::getDataGenerator()->create_module('page', ['course' => $trail->id, 'section' => 1]);
        $hidden = $this->activity($trail, (int) $first->id, 1, ['name' => 'Escondida']);
        set_coursemodule_visible((int) $hidden->cmid, 0);
        $stealth = $this->activity($trail, (int) $first->id, 2, ['name' => 'Furtiva']);
        $DB->set_field('course_modules', 'visibleoncoursepage', 0, ['id' => $stealth->cmid]);
        $deleting = $this->activity($trail, (int) $first->id, 2, ['name' => 'Em exclusão']);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $deleting->cmid]);
        $orphan = $this->activity($trail, (int) $gone->id, 3, ['name' => 'Sem curso']);
        delete_course($gone, false);
        $last = $this->activity($trail, (int) $third->id, 3, ['name' => 'Projeto final']);
        rebuild_course_cache((int) $trail->id, true);

        $cms = get_fast_modinfo($trail->id)->get_cms();
        foreach ([$hidden, $stealth, $deleting, $orphan] as $activity) {
            $this->assertArrayHasKey((int) $activity->cmid, $cms, 'Precondition: an activity of the course all the same.');
        }

        $result = trail::of(get_course($trail->id));

        $this->assertSame((int) $trail->id, $result->courseid);
        $this->assertSame(
            [(int) $earlier->cmid, (int) $later->cmid, (int) $last->cmid],
            array_map(static fn(step $step): int => $step->cmid, $result->steps),
            'Section 1 before section 2; the page and the hidden, stealth, deleting and orphaned activities are no steps.'
        );
        [$one, $two, $three] = $result->steps;

        $this->assertSame('Orçamento & finanças', $one->name, 'The name as stored, unformatted.');
        $this->assertSame((int) $second->id, $one->refcourseid);
        $this->assertSame((int) $second->category, $one->refcategoryid);
        $this->assertSame($t0 + 7 * $day, $one->start);
        $this->assertSame($expected, $one->end, 'The expected completion date closes the step.');
        $this->assertTrue($one->expected);

        $this->assertSame($t0 + 6 * $day, $two->end, 'Without an expected date, the course closes the step.');
        $this->assertFalse($two->expected);
        $this->assertTrue($two->refvisible);

        $this->assertSame(0, $three->end, 'No end date and no expected date: an open window.');
        $this->assertFalse($three->expected);
        $this->assertFalse($three->refvisible, 'A hidden referenced course is still a step; who may see it is the page\'s.');
    }

    /**
     * An expected date counts only while the trail course tracks completion, as core's own calendar event does.
     */
    public function test_the_expected_date_needs_completion_on_the_trail(): void {
        global $DB;

        $start = make_timestamp(2026, 11, 2);
        $trail = self::getDataGenerator()->create_course(['enablecompletion' => 1]);
        $ref = $this->refcourse($start, $start + 20 * 86400);
        $this->activity($trail, (int) $ref->id, 0, [
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionexpected' => $start + 5 * 86400,
        ]);

        $tracked = trail::of(get_course($trail->id))->steps[0];
        $this->assertTrue($tracked->expected, 'Precondition: with completion on, the expected date closes the step.');
        $this->assertSame($start + 5 * 86400, $tracked->end);

        $DB->set_field('course', 'enablecompletion', 0, ['id' => $trail->id]);
        rebuild_course_cache((int) $trail->id, true);

        $untracked = trail::of(get_course($trail->id))->steps[0];
        $this->assertFalse($untracked->expected);
        $this->assertSame($start + 20 * 86400, $untracked->end, 'The course\'s own end date closes it instead.');
    }

    /**
     * Without mod_subcourse enabled there are no trails, and the trail line is withheld; with it, neither.
     */
    public function test_a_disabled_module_leaves_no_trail(): void {

        $trail = self::getDataGenerator()->create_course();
        $this->activity($trail, (int) $this->refcourse(time(), 0)->id, 0);

        $this->assertTrue(trail::available(), 'Precondition: the module is installed and enabled.');
        $this->assertCount(1, trail::of(get_course($trail->id))->steps);
        $this->assertFalse(trail::hides_option(landingfields::SHORTNAME_MODEL, landingfields::MODEL_TRAIL));

        \core\plugininfo\mod::enable_plugin(trail::MODNAME, 0);

        $this->assertFalse(trail::available());
        $this->assertNull(trail::of(get_course($trail->id)));
        $this->assertSame([], trail::many([get_course($trail->id)]));
        $this->assertTrue(trail::hides_option(landingfields::SHORTNAME_MODEL, landingfields::MODEL_TRAIL));
        $this->assertFalse(
            trail::hides_option(landingfields::SHORTNAME_MODEL, landingfields::MODEL_EVENT),
            'Only the trail line is withheld.'
        );
        $this->assertFalse(
            trail::hides_option(landingfields::SHORTNAME_MODALITY, landingfields::MODEL_TRAIL),
            'Only on the page model: the third modality is the hybrid one.'
        );
    }

    /**
     * Two trails with seven steps cost the statements one trail of two does: the references come in one statement.
     */
    public function test_many_trails_cost_one_statement_for_their_references(): void {
        global $DB;

        $small = self::getDataGenerator()->create_course();
        $big = self::getDataGenerator()->create_course();
        foreach ([[$small, 2], [$big, 5]] as [$trail, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $this->activity($trail, (int) $this->refcourse(time(), 0)->id, 0);
            }
        }
        $small = get_course($small->id);
        $big = get_course($big->id);
        // The modinfo is core's cache; warm it, as a page has by the time it draws the trail.
        get_fast_modinfo($small);
        get_fast_modinfo($big);

        $before = $DB->perf_get_reads();
        $one = trail::many([$small]);
        $alone = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $both = trail::many([$small, $big]);
        $together = $DB->perf_get_reads() - $before;

        $this->assertCount(2, $one[(int) $small->id]->steps);
        $this->assertSame([(int) $small->id, (int) $big->id], array_keys($both));
        $this->assertCount(5, $both[(int) $big->id]->steps);
        $this->assertSame($alone, $together, 'Five more steps and a second trail add no statement.');
        $this->assertLessThanOrEqual(2, $together, 'The enabled-modules read and the references.');
    }
}
