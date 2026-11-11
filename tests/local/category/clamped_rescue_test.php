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
 * Theme Dimensions - Tests for the rescues of a clamped category in both page-cutting modes
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * What a clamped category still lets through, held to the same answer by the PHP cut and the SQL cut.
 *
 * The PHP cut gates every row of a clamped category in {@see population::gate()}; the SQL cut
 * carries the same rescues as bounded id lists. Each test runs in both modes and asserts the
 * same ids, with a viewer the rescue does not reach as the control.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(population::class)]
final class clamped_rescue_test extends \advanced_testcase {
    use fixture;

    /**
     * Start from no memo: local_unlistedcourses keeps its answers in statics keyed by course id.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        self::forget();
    }

    /**
     * The two page-cutting modes.
     *
     * @return array
     */
    public static function modes_provider(): array {
        return ['state sort on (page cut in PHP)' => [true], 'state sort off (page cut in SQL)' => [false]];
    }

    /**
     * The flat listing's ids for the request, in the mode asked for.
     *
     * @param bool $statesort Whether to group by state and cut in PHP.
     * @param int $categoryid The root.
     * @param array $options Further criteria options.
     * @return int[]
     */
    private function listed(bool $statesort, int $categoryid, array $options = []): array {
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        return $this->ids($categoryid, $options + ['sort' => criteria::SORT_NAME, 'pagesize' => 96]);
    }

    /**
     * Staff standing at a course lifts the plugin's clamp of its category, in both modes, and for that course alone.
     *
     * Neither viewer is enrolled and neither holds anything at a category, so the plugin keeps
     * the category clamped for both: viewhiddencourses at two courses (one listed, one unlisted,
     * which the plugin's own relationship admits), and moodle/course:view at a third (is_viewing()).
     * The sibling course both may not see is withheld from both, and a stranger sees the
     * category's courses in neither mode.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_course_staff_standing_lifts_a_plugin_clamp_in_both_modes(bool $statesort): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $shown = $generator->create_course(['category' => $root->id, 'fullname' => 'A shown']);
        $taught = $generator->create_course(['category' => $child->id, 'fullname' => 'B taught']);
        $unlisted = $generator->create_course(['category' => $child->id, 'fullname' => 'C unlisted']);
        $viewed = $generator->create_course(['category' => $child->id, 'fullname' => 'D viewed']);
        $other = $generator->create_course(['category' => $child->id, 'fullname' => 'E other']);
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);

        $teacher = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, \core\context\system::instance()->id, true);
        role_assign($roleid, $teacher->id, \core\context\course::instance($taught->id)->id);
        role_assign($roleid, $teacher->id, \core\context\course::instance($unlisted->id)->id);
        $viewer = $this->user_with('moodle/course:view', \core\context\course::instance($viewed->id));

        $this->become($teacher);
        $this->assertSame(
            [(int) $shown->id, (int) $taught->id, (int) $unlisted->id],
            $this->listed($statesort, (int) $root->id),
            'viewhiddencourses at a course lifts the plugin clamp for it, listed or unlisted, and for no sibling.'
        );

        $this->become($viewer);
        $this->assertSame(
            [(int) $shown->id, (int) $viewed->id],
            $this->listed($statesort, (int) $root->id),
            'moodle/course:view at a course (is_viewing()) lifts the plugin clamp for it alone.'
        );

        $this->become($generator->create_user());
        $this->assertSame(
            [(int) $shown->id],
            $this->listed($statesort, (int) $root->id),
            'Without standing the clamped category lists nothing.'
        );
        $this->assertNotEmpty($other);
    }

    /**
     * Under a search, staff standing reaches a match past the first fifty courses the viewer may see in the clamp.
     *
     * The SQL cut caps its staff list at RESCUE_CAP: built before the term, a match past the
     * cap would be withheld where the PHP cut, which gates every match, shows it.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_search_reaches_a_staff_match_past_the_rescue_cap(bool $statesort): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $teacher = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, \core\context\system::instance()->id, true);
        // The match first: core order is newest first, so the plain courses created after it come before it.
        $needle = $generator->create_course(['category' => $child->id, 'fullname' => 'Needle course']);
        role_assign($roleid, $teacher->id, \core\context\course::instance($needle->id)->id);
        for ($i = 0; $i < population::RESCUE_CAP + 1; $i++) {
            $course = $generator->create_course(['category' => $child->id, 'fullname' => "Plain {$i}"]);
            role_assign($roleid, $teacher->id, \core\context\course::instance($course->id)->id);
        }
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);

        $this->become($teacher);
        $this->assertSame([(int) $needle->id], $this->listed($statesort, (int) $root->id, ['search' => 'needle']));

        $this->become($generator->create_user());
        $this->assertSame([], $this->listed($statesort, (int) $root->id, ['search' => 'needle']));
    }

    /**
     * Under a search, an enrolment reaches a match past the first fifty of the viewer's courses in a clamped category.
     *
     * The category is hidden (core's clamp), lifted for each course by an active enrolment. The
     * SQL cut's list of those courses is capped at RESCUE_CAP: cut before the term, it would keep
     * the first fifty enrolments whatever their names and withhold the match the PHP cut shows.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_search_reaches_an_enrolled_match_past_the_rescue_cap(bool $statesort): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id, 'visible' => 0]);
        $member = $generator->create_user();
        /* The match is created first, so it is the last in core order (newest first), and enrolled
           in last, so it is the last of the viewer's enrolments: past fifty both ways. Visible
           explicitly: create_course() hides a course created in a hidden category. */
        $needle = $generator->create_course(['category' => $child->id, 'fullname' => 'Needle course', 'visible' => 1]);
        for ($i = 0; $i < population::RESCUE_CAP + 1; $i++) {
            $course = $generator->create_course(['category' => $child->id, 'fullname' => "Plain {$i}", 'visible' => 1]);
            $generator->enrol_user($member->id, $course->id);
        }
        $generator->enrol_user($member->id, $needle->id);
        $missed = $generator->create_course(['category' => $child->id, 'fullname' => 'Needle not mine', 'visible' => 1]);

        $this->become($member);
        $this->assertSame([(int) $needle->id], $this->listed($statesort, (int) $root->id, ['search' => 'needle']));

        $this->become($generator->create_user());
        $this->assertSame([], $this->listed($statesort, (int) $root->id, ['search' => 'needle']));
        $this->assertNotEmpty($missed);
    }

    /**
     * A hidden course the viewer may not see spends no place of the staff list: core's visibility is asked first.
     *
     * moodle/course:view is staff standing for the plugin clamp, but core still withholds a
     * hidden course without viewhiddencourses. The fifty-one hidden courses come first in core
     * order; the visible one, last, is listed in both modes only when the hidden ones were
     * dropped before they could fill the SQL cut's capped list.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_hidden_course_spends_no_place_of_the_staff_list(bool $statesort): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $viewer = $generator->create_user();
        $roleid = $generator->create_role();
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, \core\context\system::instance()->id, true);
        // Created first: the last in core order.
        $visible = $generator->create_course(['category' => $child->id, 'fullname' => 'Visible']);
        role_assign($roleid, $viewer->id, \core\context\course::instance($visible->id)->id);
        for ($i = 0; $i < population::RESCUE_CAP + 1; $i++) {
            $course = $generator->create_course(['category' => $child->id, 'fullname' => "Hidden {$i}", 'visible' => 0]);
            role_assign($roleid, $viewer->id, \core\context\course::instance($course->id)->id);
        }
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);

        $this->become($viewer);
        $this->assertSame([(int) $visible->id], $this->listed($statesort, (int) $root->id));

        $this->become($generator->create_user());
        $this->assertSame([], $this->listed($statesort, (int) $root->id));
    }
}
