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
 * Theme Dimensions - The shared guards of the showcase's web services
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for the one refusal every web service of the showcase answers a miss with.
 *
 * The property the guard exists for cannot be tested one case at a time: a
 * nonexistent id, a category core hides from this viewer and one
 * local_unlistedcourses withholds from them must be indistinguishable from
 * outside. So each matrix test collects the refusals of every case and
 * compares them with each other, rather than asserting a message per case,
 * and carries the control that an admitted id is returned, without which a
 * guard that refused everything would pass.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(guard::class)]
final class guard_test extends \advanced_testcase {
    use fixture;

    /**
     * What a call refused with, or a failure when it refused nothing.
     *
     * @param callable $fn The call.
     * @return \Throwable What it threw.
     */
    private function refusal(callable $fn): \Throwable {
        try {
            $fn();
        } catch (\Throwable $thrown) {
            return $thrown;
        }
        $this->fail('The guard admitted what it was asked to refuse.');
    }

    /**
     * Assert that every refusal of a matrix is the same exception, class, code and message alike.
     *
     * @param array $refusals case name => the throwable it raised.
     * @param string $errorcode The one error code they must all carry.
     * @return void
     */
    private function assert_one_refusal(array $refusals, string $errorcode): void {
        $first = reset($refusals);
        foreach ($refusals as $case => $thrown) {
            $this->assertInstanceOf(\moodle_exception::class, $thrown, $case);
            $this->assertSame($errorcode, $thrown->errorcode, $case);
            $this->assertSame(get_class($first), get_class($thrown), $case);
            $this->assertSame($first->getMessage(), $thrown->getMessage(), $case);
        }
    }

    /**
     * A category that is not there, one core hides and one the plugin withholds answer alike.
     */
    public function test_every_category_the_guard_withholds_raises_the_one_refusal(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $admitted = $generator->create_category();
        $hidden = $generator->create_category(['visible' => 0]);
        $unlisted = $generator->create_category();
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $missing = (int) $unlisted->id + 1000;

        $this->become($generator->create_user());

        $this->assert_one_refusal([
            'a category id nothing holds' => $this->refusal(fn() => guard::category($missing)),
            'a category core hides' => $this->refusal(fn() => guard::category((int) $hidden->id)),
            'a category the plugin withholds' => $this->refusal(fn() => guard::category((int) $unlisted->id)),
        ], guard::ERROR_CATEGORY);

        // The control: the same call on a category this viewer may list returns it.
        $this->assertSame((int) $admitted->id, (int) guard::category((int) $admitted->id)->id);
    }

    /**
     * A course that is not there, one core hides, one the plugin withholds and one under a
     * withheld category answer alike.
     */
    public function test_every_course_the_guard_withholds_raises_the_one_refusal(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $admitted = $generator->create_course(['category' => $category->id]);
        $hidden = $generator->create_course(['category' => $category->id, 'visible' => 0]);
        $unlisted = $generator->create_course(['category' => $category->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $withheldcategory = $generator->create_category();
        $incategory = $generator->create_course(['category' => $withheldcategory->id, 'visible' => 1]);
        $this->set_category_state((int) $withheldcategory->id, category_discoverability::STATE_UNLISTED);
        $missing = (int) $incategory->id + 1000;

        $this->become($generator->create_user());

        $this->assert_one_refusal([
            'a course id nothing holds' => $this->refusal(fn() => guard::course($missing)),
            'a course core hides' => $this->refusal(fn() => guard::course((int) $hidden->id)),
            'a course the plugin withholds' => $this->refusal(fn() => guard::course((int) $unlisted->id)),
            'a course under a withheld category' => $this->refusal(fn() => guard::course((int) $incategory->id)),
            // The site course, which both gates admit on their own: see the test below.
            'the site course' => $this->refusal(fn() => guard::course(SITEID)),
        ], guard::ERROR_COURSE);

        // The control: the same call on a course this viewer may discover returns its record.
        $this->assertSame((int) $admitted->id, (int) guard::course((int) $admitted->id)->id);
    }

    /**
     * The site course is refused on a site where nothing is unlisted, which is where it matters.
     *
     * Both gates admit the front page unaided - can_view_course_info() returns true outright for
     * SITEID and its category is 0, which holds no row - so the explicit refusal at the head of
     * guard::course() is the only thing that stops the showcase's one write being aimed at it.
     *
     * The matrix case above cannot prove that. It seeds an unlisted category, and
     * local_unlistedcourses evaluates its predicates only when something on the site is unlisted:
     * with nothing unlisted category_access answers true wholesale, and with something unlisted it
     * finds no path for the pseudo-category 0 and fails closed. So in the matrix the plugin refuses
     * the site course first and the explicit check is never reached; removing that check must make
     * this test fail.
     *
     * The precondition is asserted rather than assumed, because which layer answers is the point.
     */
    public function test_the_site_course_is_refused_where_the_plugin_admits_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $admitted = $generator->create_course(['category' => $category->id]);
        $this->become($generator->create_user());

        // The precondition: nothing is unlisted, so every gate below the guard admits the site course.
        $this->assertTrue(
            \local_unlistedcourses\category_access::is_category_discoverable(0),
            'The plugin is withholding the pseudo-category, so this test proves nothing about the guard.'
        );
        $site = $DB->get_record('course', ['id' => SITEID], 'id, category, visible, fullname, shortname');
        $this->assertTrue(
            \core_course_category::can_view_course_info($site),
            'Core is withholding the site course, so this test proves nothing about the guard.'
        );
        $this->assertNotEmpty(
            \local_unlistedcourses\access::filter_courses([SITEID => $site]),
            'The plugin is dropping the site course, so this test proves nothing about the guard.'
        );

        $thrown = $this->refusal(fn() => guard::course(SITEID));
        $this->assertInstanceOf(\moodle_exception::class, $thrown);
        $this->assertSame(guard::ERROR_COURSE, $thrown->errorcode);

        // The control: on the same site an ordinary course is still admitted.
        $this->assertSame((int) $admitted->id, (int) guard::course((int) $admitted->id)->id);
    }

    /**
     * The course guard gates on discoverability, never on enrolment.
     *
     * The showcase is mostly courses the viewer is NOT enrolled in - that is
     * what a category page is - so a guard that asked for an enrolment would
     * refuse nearly every card on the page it serves. This is also the test
     * that measures the context the guard validates: validating the COURSE
     * context runs require_login() against that course, which throws for
     * anybody not enrolled in it.
     */
    public function test_a_discoverable_course_is_admitted_without_any_enrolment(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);

        $record = guard::course((int) $course->id);

        $this->assertSame((int) $course->id, (int) $record->id);
        $this->assertFalse(
            is_enrolled(\core\context\course::instance((int) $course->id), $viewer),
            'The precondition: the viewer holds no enrolment in the course the guard admitted.'
        );
    }

    /**
     * A course a viewer is enrolled in, under a category the plugin withholds, may be starred.
     *
     * What the page shows is what the write accepts: the listing keeps this
     * course for anybody related to it (filter_courses() exempts an enrolment
     * from the category term) and draws its star, so the course gate asks the
     * course term alone. {@see guard} explains the asymmetry with
     * {@see guard::category()}.
     *
     * The control is an unrelated viewer, refused the same course because
     * filter_courses() withholds it from them.
     */
    public function test_an_enrolment_opens_the_star_of_a_course_under_a_withheld_category(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $withheld = $generator->create_category();
        $course = $generator->create_course(['category' => $withheld->id, 'visible' => 1]);
        $viewer = $generator->create_user();
        $stranger = $generator->create_user();
        $generator->enrol_user($viewer->id, $course->id);
        $this->set_category_state((int) $withheld->id, category_discoverability::STATE_UNLISTED);
        $this->become($viewer);

        // The precondition: the plugin's CATEGORY predicate withholds the category from this viewer,
        // so what admits the course below is the course term and nothing else.
        $this->assertFalse(
            \local_unlistedcourses\category_access::is_category_discoverable((int) $withheld->id),
            'The category must be withheld, or this test proves nothing about the dropped term.'
        );

        $this->assertSame((int) $course->id, (int) guard::course((int) $course->id)->id);

        // The control: the same course, the same withheld category, a viewer with no relationship to it.
        $this->become($stranger);
        $thrown = $this->refusal(fn() => guard::course((int) $course->id));
        $this->assertSame(guard::ERROR_COURSE, $thrown->errorcode);
    }
}
