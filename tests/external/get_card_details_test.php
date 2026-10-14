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
 * Theme Dimensions - The viewport enrichment web service of the showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\enrichment;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests for the batch the client asks as cards come into view.
 *
 * The refusal shape of this function is deliberately different from the other
 * two and is what most of this file pins. An id the viewer may not see is
 * dropped, never reported: telling a caller "you may not see 41" answers the
 * question "does 41 exist". So every withholding test here asserts an absence
 * beside a presence - a course that does come back in the same call - because
 * a function returning nothing at all would satisfy the absence alone.
 *
 * The gate is discoverability, not enrolment: a category page is mostly
 * courses the viewer is not enrolled in, and a batch gated on enrolment would
 * answer for almost none of the cards it is asked about.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_card_details::class)]
#[CoversClass(enrichment::class)]
final class get_card_details_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_get_card_details';

    /**
     * Call the function the way the client does.
     *
     * @param array $courseids The ids to ask about.
     * @return array The framework's response.
     */
    private function call(array $courseids): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::FUNCTION, ['courseids' => $courseids], false);
    }

    /**
     * The details the function answered with, keyed by course id.
     *
     * @param array $courseids The ids to ask about.
     * @return array courseid => the detail row.
     */
    private function details(array $courseids): array {
        $response = $this->call($courseids);
        $this->assertFalse(
            $response['error'],
            'The function refused: ' . ($response['exception']->message ?? '')
        );
        $keyed = [];
        foreach ($response['data']['details'] as $row) {
            $keyed[(int) $row['id']] = $row;
        }
        return $keyed;
    }

    /**
     * An id the viewer may not see is dropped, and an id they may see in the same call is not.
     */
    public function test_an_id_the_viewer_may_not_see_is_dropped_and_never_reported(): void {
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

        $details = $this->details([
            (int) $admitted->id,
            (int) $hidden->id,
            (int) $unlisted->id,
            (int) $incategory->id,
            $missing,
        ]);

        /* The control first: without it every assertion below passes against a function
           that answers for nothing at all. */
        $this->assertArrayHasKey((int) $admitted->id, $details);
        $this->assertCount(1, $details);
        $this->assertArrayNotHasKey((int) $hidden->id, $details, 'A course core hides.');
        $this->assertArrayNotHasKey((int) $unlisted->id, $details, 'A course the plugin withholds.');
        $this->assertArrayNotHasKey((int) $incategory->id, $details, 'A course under a withheld category.');
        $this->assertArrayNotHasKey($missing, $details, 'An id nothing holds.');
    }

    /**
     * The gate is discoverability, not enrolment.
     */
    public function test_a_course_the_viewer_is_not_enrolled_in_is_answered_for(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);

        // The precondition: the viewer holds no enrolment at all in that course.
        $this->assertFalse(is_enrolled(\core\context\course::instance((int) $course->id), $viewer));

        $details = $this->details([(int) $course->id]);
        $this->assertArrayHasKey((int) $course->id, $details);
        $this->assertFalse($details[(int) $course->id]['hascompletion']);
    }

    /**
     * The batch cap is refused above twenty-four, and answered at twenty-four.
     */
    public function test_the_batch_is_capped_at_twenty_four(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $ids = [];
        for ($i = 0; $i < enrichment::MAX_COURSES + 1; $i++) {
            $ids[] = (int) $generator->create_course(['category' => $category->id])->id;
        }
        $this->become($generator->create_user());

        $this->assertSame(24, enrichment::MAX_COURSES, 'The cap the client batches to.');

        $overflow = $this->call($ids);
        $this->assertTrue($overflow['error'], 'Twenty-five ids are refused.');
        $this->assertSame('invalidparameter', $overflow['exception']->errorcode);

        $full = $this->details(array_slice($ids, 0, enrichment::MAX_COURSES));
        $this->assertCount(enrichment::MAX_COURSES, $full, 'Twenty-four ids are answered.');

        $empty = $this->call([]);
        $this->assertTrue($empty['error'], 'An empty batch is a malformed request, not an empty answer.');
    }

    /**
     * A tracked learner's progress travels with a sentence to paint beside the badge.
     */
    public function test_progress_is_a_number_and_a_sentence_for_a_tracked_learner(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $tracked = $generator->create_course(['category' => $category->id, 'enablecompletion' => 1]);
        $plain = $generator->create_course(['category' => $category->id, 'enablecompletion' => 0]);
        $page = $generator->create_module('page', [
            'course' => $tracked->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $tracked->id);
        $this->become($viewer);

        $details = $this->details([(int) $tracked->id, (int) $plain->id]);

        $this->assertTrue($details[(int) $tracked->id]['hascompletion']);
        $this->assertSame(0, $details[(int) $tracked->id]['progress']);
        $this->assertSame(
            get_string('category_progress', 'theme_dimensions', 0),
            $details[(int) $tracked->id]['progresslabel']
        );

        // The course without completion: the flag is false and no number is invented.
        $this->assertFalse($details[(int) $plain->id]['hascompletion']);
        $this->assertNull($details[(int) $plain->id]['progress']);
        $this->assertArrayNotHasKey('progresslabel', $details[(int) $plain->id]);

        // And the number moves when the learner completes something.
        $completion = new \completion_info(get_course((int) $tracked->id));
        $cm = get_coursemodule_from_id('page', $page->cmid, 0, false, MUST_EXIST);
        $completion->update_state($cm, COMPLETION_COMPLETE, $viewer->id);
        $this->become($viewer);
        $moved = $this->details([(int) $tracked->id]);
        $this->assertSame(100, $moved[(int) $tracked->id]['progress']);
    }

    /**
     * The guest account is refused outright, with the course refusal every other miss carries.
     */
    public function test_the_guest_account_is_refused(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $this->setGuestUser();
        self::forget();

        $response = $this->call([(int) $course->id]);
        $this->assertTrue($response['error']);
        $this->assertSame(
            \theme_dimensions\local\category\guard::ERROR_COURSE,
            $response['exception']->errorcode
        );
    }

    /**
     * The loop costs no more per course than the measured slope, whatever the batch's fixed cost.
     *
     * Completion is per course by construction - there is no bulk API for it -
     * so this budget bounds the slope and not the total. Measuring the
     * difference between a small batch and a full one removes the fixed cost -
     * the one get_records_list for the batch, the filter load of the first
     * context - from the number, so one extra read per course moves the slope
     * by twenty and fails; a ceiling on the total would have room for it.
     *
     * Twenty more courses measured 54 statements, about 2.7 each: the course's
     * modinfo and its completion data. The ceiling is three per course (60),
     * which one more read in the loop (74) exceeds.
     */
    public function test_the_per_course_cost_of_a_batch_stays_on_the_measured_slope(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = 1;
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $viewer = $generator->create_user();
        $ids = [];
        for ($i = 0; $i < enrichment::MAX_COURSES; $i++) {
            $course = $generator->create_course(['category' => $category->id, 'enablecompletion' => 1]);
            $generator->create_module('page', ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
            $generator->enrol_user($viewer->id, $course->id);
            $ids[] = (int) $course->id;
        }
        $this->become($viewer);
        $small = array_slice($ids, 0, 4);
        enrichment::details($ids);

        $smallreads = $this->reads(function () use ($small) {
            $this->assertCount(4, enrichment::details($small));
        });
        $fullreads = $this->reads(function () use ($ids) {
            $this->assertCount(enrichment::MAX_COURSES, enrichment::details($ids));
        });

        $extra = enrichment::MAX_COURSES - 4;
        $slope = $fullreads - $smallreads;
        $this->assertLessThanOrEqual(
            3 * $extra,
            $slope,
            "Twenty more courses cost {$slope} statements ({$smallreads} for four, {$fullreads} "
                . 'for twenty-four): more than three each.'
        );
        $this->assertGreaterThan(
            0,
            $slope,
            'A batch that costs the same for four courses as for twenty-four is not being measured.'
        );
    }
    /**
     * The delegate requires the legacy completion library itself: a web-service request never loads it.
     *
     * completion_info lives in lib/completionlib.php and is not autoloadable, and lib/ajax/service.php
     * bootstraps nothing that includes it, while a test process has usually loaded it already (the
     * data generator requires course/lib.php, which requires it). A class cannot be unloaded inside
     * a test, so the require is pinned as source. {@see enrichment::details()}
     *
     * @return void
     */
    public function test_the_delegate_requires_the_legacy_completion_library_itself(): void {
        $source = file_get_contents(__DIR__ . '/../../classes/local/category/enrichment.php');
        $this->assertStringContainsString("require_once(\$CFG->libdir . '/completionlib.php');", $source);
    }
}
