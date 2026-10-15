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
 * Theme Dimensions - Tests for the population of the category showcase
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
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for what the showcase lists, withholds, counts and orders.
 *
 * Every withholding assertion has a control in the same test - the same
 * course shown to somebody who may see it - so an assertion cannot pass
 * against a population that dropped everything. The two gates are exercised
 * separately: core-hidden courses and categories, which local_unlistedcourses
 * answers "true" for wholesale, and the plugin's unlisted state, which core
 * knows nothing about.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(population::class)]
#[CoversClass(scope::class)]
#[CoversClass(criteria::class)]
#[CoversClass(page_result::class)]
#[CoversClass(unavailable_exception::class)]
final class population_test extends \advanced_testcase {
    use fixture;

    /**
     * The two page-cutting modes every gate must hold in.
     *
     * @return array
     */
    public static function modes_provider(): array {
        return ['state sort on (page cut in PHP)' => [true], 'state sort off (page cut in SQL)' => [false]];
    }

    /**
     * Switch the page-cutting mode.
     *
     * @param bool $statesort Whether to group by state and cut in PHP.
     * @return void
     */
    private function mode(bool $statesort): void {
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
    }

    /**
     * The ids of one page asked for as the flat listing: the whole admitted subtree.
     *
     * A gate question - which courses of the subtree may this viewer see - is
     * about the population and not about how the page is organised, and the
     * grouped default lists only the courses sitting directly in the category,
     * so asked that way a test could pass by drawing nothing. The flat
     * organisation lists the whole subtree through the same predicate in both
     * page-cutting modes; the grouped partition has tests of its own.
     *
     * @param int $categoryid The root.
     * @param array $options Further criteria options.
     * @return int[]
     */
    private function subtree_ids(int $categoryid, array $options = []): array {
        return $this->ids($categoryid, $options + ['sort' => criteria::SORT_NAME]);
    }

    /**
     * A listed course is shown to everybody; an unlisted one only to the people related to it.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_an_unlisted_course_is_shown_only_to_the_related(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $listed = $generator->create_course(['category' => $root->id]);
        $unlisted = $generator->create_course(['category' => $root->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $stranger = $generator->create_user();
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $unlisted->id);

        $this->become($stranger);
        $this->assertSame([(int) $listed->id], $this->ids($root->id));

        $this->become($member);
        $this->assertEqualsCanonicalizing([(int) $listed->id, (int) $unlisted->id], $this->ids($root->id));

        $this->setAdminUser();
        self::forget();
        $this->assertEqualsCanonicalizing([(int) $listed->id, (int) $unlisted->id], $this->ids($root->id));
    }

    /**
     * A category the viewer was clamped out of names no card: off the overline as it is off the chip.
     *
     * The enrolment rescues the course (the control that the page rendered it), and the
     * withheld subcategory's name reaches neither surface; an admin, whom nothing clamps, gets it.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_withheld_subcategory_is_not_named_by_the_courses_rescued_from_it(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id, 'name' => 'Secret Child']);
        $course = $generator->create_course(['category' => $child->id]);
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $course->id);

        // The flat organisation: the rescued course sits in a child, and the grouped page lists
        // only the courses of this category itself.
        $this->become($member);
        $result = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertSame([(int) $course->id], $result->ids(), 'The enrolment rescues the course.');
        $this->assertSame([], $result->categorynames, 'The withheld category is not named.');
        $this->assertSame([], $result->subcategories);

        $this->setAdminUser();
        self::forget();
        $result = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertSame([(int) $child->id => 'Secret Child'], $result->categorynames, 'Control: an admin gets the name.');
    }

    /**
     * A page past the end lands on the last page, never on an empty one over a full count.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_page_past_the_end_lands_on_the_last_page(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        for ($i = 0; $i < 3; $i++) {
            $generator->create_course(['category' => $root->id]);
        }
        $this->become($generator->create_user());
        $result = population::resolve(new criteria($root->id, ['page' => 9, 'pagesize' => 2]));
        $this->assertSame(1, $result->page);
        $this->assertSame(3, $result->total);
        $this->assertCount(1, $result->rows);
        $this->assertFalse($result->hasmore);
    }

    /**
     * An unlisted course inside a category the viewer may browse is still withheld: the course term is the more specific rule.
     *
     * Control in the same run: the listed sibling in the same open category is shown.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_course_term_wins_over_an_open_category(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $listed = $generator->create_course(['category' => $child->id]);
        $unlisted = $generator->create_course(['category' => $child->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);

        // The flat organisation: both courses sit in the child, which the grouped page leaves out
        // of its own listing.
        $this->become($generator->create_user());
        $result = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertSame([(int) $listed->id], $result->ids());
        $this->assertSame(
            [['id' => (int) $child->id, 'name' => $child->name, 'depth' => 1, 'linkable' => true]],
            $result->subcategories,
            'One entry.'
        );
    }

    /**
     * A course core hides needs viewhiddencourses, enrolled or not; the plugin's layer never withholds it.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_core_hidden_course_needs_the_capability(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $shown = $generator->create_course(['category' => $root->id]);
        $hidden = $generator->create_course(['category' => $root->id, 'visible' => 0]);
        $stranger = $generator->create_user();
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $hidden->id);
        $viewer = $this->user_with('moodle/course:viewhiddencourses', \core\context\coursecat::instance($root->id));

        $this->become($stranger);
        $this->assertSame([(int) $shown->id], $this->ids($root->id));

        $this->become($member);
        $this->assertSame([(int) $shown->id], $this->ids($root->id), 'An active enrolment does not open a hidden course.');

        $this->become($viewer);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $hidden->id], $this->ids($root->id));
    }

    /**
     * An active enrolment never lists a hidden course; the capability, granted in that course, does.
     *
     * Core's own answer: can_view_course_info() asks for viewhiddencourses for a hidden course, and
     * enrol_get_my_courses() drops it without. The control is the same viewer holding the capability
     * through a role in the course's context, so the absence proves the force was on.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_an_enrolled_student_is_not_shown_a_hidden_course_without_the_capability(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $shown = $generator->create_course(['category' => $root->id]);
        $hidden = $generator->create_course(['category' => $root->id, 'visible' => 0]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $hidden->id);
        $generator->enrol_user($student->id, $shown->id);

        $this->become($student);
        $this->assertSame(
            [(int) $shown->id],
            $this->subtree_ids($root->id),
            'Enrolled and active in the hidden course, the student is not shown it.'
        );

        $roleid = $generator->create_role();
        $coursecontext = \core\context\course::instance((int) $hidden->id);
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roleid, $coursecontext->id, true);
        role_assign($roleid, $student->id, $coursecontext->id);
        $this->become($student);
        $this->assertEqualsCanonicalizing(
            [(int) $shown->id, (int) $hidden->id],
            $this->subtree_ids($root->id),
            'Control: the same viewer holding the capability in that course is shown it.'
        );
    }

    /**
     * A category core hides withholds its whole branch, except the courses the viewer is enrolled in.
     *
     * Stricter than core's own recursive listing on purpose: core checks each
     * course's category alone, so a visible grandchild under a hidden child
     * would leak through. Here the hidden branch is clamped down to the
     * viewer's own courses, which is what get_course_records()'s $mycourses
     * bypass does for the course itself.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_core_hidden_category_withholds_its_branch_except_the_viewers_own_courses(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $shown = $generator->create_course(['category' => $root->id]);
        global $DB;
        $hiddencat = $generator->create_category(['parent' => $root->id, 'visible' => 0]);
        $grandchild = $generator->create_category(['parent' => $hiddencat->id]);
        // Core hides a category created under a hidden parent whatever 'visible' says, and by
        // default a course created inside a hidden category too. Both are forced visible here,
        // straight in the table, because the category clamp is what is under test: a visible
        // grandchild under a hidden child must be withheld by the branch, not by its own row, or a
        // clamp that stopped propagating down the branch would still pass.
        $DB->set_field('course_categories', 'visible', 1, ['id' => $grandchild->id]);
        $inhidden = $generator->create_course(['category' => $hiddencat->id, 'visible' => 1]);
        $ingrandchild = $generator->create_course(['category' => $grandchild->id, 'visible' => 1]);
        $DB->set_field('course', 'visible', 1, ['id' => $ingrandchild->id]);
        \cache_helper::purge_by_event('changesincoursecat');
        $stranger = $generator->create_user();
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $ingrandchild->id);
        $viewer = $this->user_with('moodle/category:viewhiddencategories', \core\context\coursecat::instance($root->id));

        $this->become($stranger);
        $this->assertSame([(int) $shown->id], $this->subtree_ids($root->id));

        $this->become($member);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $ingrandchild->id], $this->subtree_ids($root->id));

        $this->become($viewer);
        $this->assertEqualsCanonicalizing(
            [(int) $shown->id, (int) $inhidden->id, (int) $ingrandchild->id],
            $this->subtree_ids($root->id)
        );
    }

    /**
     * A category where viewcourselist is prohibited withholds its courses, except the viewer's own.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_viewcourselist_prohibited_at_a_subcategory_withholds_it(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $shown = $generator->create_course(['category' => $root->id]);
        $inchild = $generator->create_course(['category' => $child->id]);
        $other = $generator->create_course(['category' => $child->id]);
        $childcontext = \core\context\coursecat::instance($child->id);

        $roleid = $generator->create_role();
        assign_capability('moodle/category:viewcourselist', CAP_PROHIBIT, $roleid, $childcontext->id, true);
        $stranger = $generator->create_user();
        role_assign($roleid, $stranger->id, $childcontext->id);
        $member = $generator->create_user();
        role_assign($roleid, $member->id, $childcontext->id);
        $generator->enrol_user($member->id, $inchild->id);
        $free = $generator->create_user();

        $this->become($stranger);
        $this->assertSame([(int) $shown->id], $this->subtree_ids($root->id));

        $this->become($member);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $inchild->id], $this->subtree_ids($root->id));

        $this->become($free);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $inchild->id, (int) $other->id], $this->subtree_ids($root->id));
    }

    /**
     * An unlisted category withholds its listed courses from strangers and keeps them for the enrolled and the staff.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_an_unlisted_category_withholds_its_courses_from_the_unrelated(bool $statesort): void {
        global $DB;

        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $shown = $generator->create_course(['category' => $root->id]);
        $inchild = $generator->create_course(['category' => $child->id]);
        $other = $generator->create_course(['category' => $child->id]);
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);

        $stranger = $generator->create_user();
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $inchild->id);
        $manager = $generator->create_user();
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerrole, $manager->id, \core\context\coursecat::instance($child->id)->id);

        $this->become($stranger);
        $this->assertSame([(int) $shown->id], $this->subtree_ids($root->id));

        $this->become($member);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $inchild->id], $this->subtree_ids($root->id));

        $this->become($manager);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $inchild->id, (int) $other->id], $this->subtree_ids($root->id));
    }

    /**
     * A hidden course inside an unlisted category is not shown to any enrolment, only to the capability.
     *
     * An enrolment, active or not, never opens a course core hides; the visible course of the
     * same category is the control for the plugin clamp each enrolment does lift.
     *
     * The two page-cutting modes must agree here: in the SQL cut the relationship
     * rescue sits under the visibility term, not beside it, or a relationship that
     * lifts only the plugin clamp would also list a course core hides.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_hidden_course_in_an_unlisted_category_is_not_opened_by_an_enrolment(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);
        $shown = $generator->create_course(['category' => $root->id]);
        $hidden = $generator->create_course(['category' => $child->id, 'visible' => 0]);
        $visible = $generator->create_course(['category' => $child->id, 'visible' => 1]);

        $suspended = $generator->create_user();
        $generator->enrol_user($suspended->id, $hidden->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($suspended->id, $visible->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $hidden->id);
        $scheduled = $generator->create_user();
        $generator->enrol_user($scheduled->id, $hidden->id, null, 'manual', time() + DAYSECS, 0);
        $generator->enrol_user($scheduled->id, $visible->id, null, 'manual', time() + DAYSECS, 0);

        $this->become($suspended);
        $this->assertSame(
            [(int) $shown->id],
            $this->subtree_ids($root->id),
            'A suspended manual row relates to nothing: not to the plugin clamp, not to a hidden course.'
        );

        $this->become($scheduled);
        $this->assertEqualsCanonicalizing(
            [(int) $shown->id, (int) $visible->id],
            $this->subtree_ids($root->id),
            'A start date ahead is a relationship for the plugin clamp, never for a hidden course.'
        );

        $this->become($member);
        $this->assertSame(
            [(int) $shown->id],
            $this->subtree_ids($root->id),
            'An active enrolment in the hidden course lifts neither core\'s visibility nor, alone, the visible sibling.'
        );

        $this->become($generator->create_user());
        $this->assertSame([(int) $shown->id], $this->subtree_ids($root->id));
    }

    /**
     * Every number is post-filter: a withheld course moves no count by one.
     */
    public function test_counts_and_total_exclude_withheld_courses(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $courses = [];
        for ($i = 0; $i < 3; $i++) {
            $courses[] = $generator->create_course(['category' => $root->id]);
        }
        $unlisted = $generator->create_course(['category' => $root->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $hidden = $generator->create_course(['category' => $root->id, 'visible' => 0]);
        $this->assertNotEmpty($hidden);

        $this->become($generator->create_user());
        $result = population::resolve(new criteria($root->id));
        $this->assertSame(3, $result->total);
        $this->assertSame(3, $result->counts['total']);
        $this->assertSame(3, $result->counts[classifier::STATE_NONE]);
        $this->assertSame(0, $result->counts[classifier::STATE_ENROLLED]);
        $this->assertCount(3, $result->rows);
        $this->assertFalse($result->hasmore);

        // Control: the admin's numbers include both withheld courses.
        $this->setAdminUser();
        self::forget();
        $this->assertSame(5, population::resolve(new criteria($root->id))->total);
    }

    /**
     * "There is more" is decided after filtering, or a withheld course announces itself as a next page.
     */
    public function test_hasmore_is_computed_after_filtering(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $first = $generator->create_course(['category' => $root->id]);
        $second = $generator->create_course(['category' => $root->id]);
        $this->set_course_state((int) $second->id, discoverability::STATE_UNLISTED);

        $this->become($generator->create_user());
        $result = population::resolve(new criteria($root->id, ['pagesize' => 1]));
        $this->assertSame([(int) $first->id], $result->ids());
        $this->assertSame(1, $result->total);
        $this->assertFalse($result->hasmore, 'A withheld course must not promise a second page.');

        // Control: with the second course listed there IS a second page.
        $this->set_course_state((int) $second->id, discoverability::STATE_DEFAULT);
        $result = population::resolve(new criteria($root->id, ['pagesize' => 1]));
        $this->assertTrue($result->hasmore);
        $this->assertSame(2, $result->total);
    }

    /**
     * The subcategory tree names only what this viewer may browse: no hidden one, no unlisted one.
     */
    public function test_the_tree_names_only_the_subcategories_the_viewer_may_browse(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $open = $generator->create_category(['parent' => $root->id, 'name' => 'Open & shut']);
        $hidden = $generator->create_category(['parent' => $root->id, 'visible' => 0]);
        $unlisted = $generator->create_category(['parent' => $root->id]);
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        foreach ([$open, $hidden, $unlisted] as $category) {
            $generator->create_course(['category' => $category->id]);
        }

        $this->become($generator->create_user());
        $tree = population::resolve(new criteria($root->id))->subcategories;
        $expected = [['id' => (int) $open->id, 'name' => 'Open & shut', 'depth' => 1, 'linkable' => true]];
        $this->assertSame($expected, $tree, 'Plain spelling, one entry.');

        $this->setAdminUser();
        self::forget();
        $ids = array_column(population::resolve(new criteria($root->id))->subcategories, 'id');
        $this->assertEqualsCanonicalizing([(int) $open->id, (int) $hidden->id, (int) $unlisted->id], $ids);
    }

    /**
     * The tree runs deeper than the children, in tree order, each child immediately followed by its own.
     *
     * The order the navigation tree needs: a grandchild is indented one step from
     * its parent and listed directly under it, so the indentation and the sequence
     * say the same thing. A branch the viewer may not browse takes its whole
     * subtree with it: the grandchild under a withheld child is absent, not
     * promoted to the level above, which is what a flat "every admitted category"
     * listing would produce.
     */
    public function test_the_tree_is_depth_first_and_a_withheld_branch_takes_its_children_with_it(): void {
        $this->resetAfterTest();
        /* This test is about the shape of the walk, so it asks for the whole subtree: the
           default depth of two levels would cut the grandchild that the "a branch takes its
           children with it" assertion is made of. The depth itself has its own test. */
        set_config(population::SETTING_TREEDEPTH, '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $first = $generator->create_category(['parent' => $root->id, 'name' => 'Alpha', 'sortorder' => 10]);
        $under = $generator->create_category(['parent' => $first->id, 'name' => 'Alpha child']);
        $deeper = $generator->create_category(['parent' => $under->id, 'name' => 'Alpha grandchild']);
        $second = $generator->create_category(['parent' => $root->id, 'name' => 'Beta', 'sortorder' => 20]);
        $withheld = $generator->create_category(['parent' => $root->id, 'name' => 'Gamma']);
        $orphan = $generator->create_category(['parent' => $withheld->id, 'name' => 'Gamma child']);
        $this->set_category_state((int) $withheld->id, category_discoverability::STATE_UNLISTED);
        foreach ([$first, $under, $deeper, $second, $withheld, $orphan] as $category) {
            $generator->create_course(['category' => $category->id]);
        }

        $this->become($generator->create_user());
        $tree = population::resolve(new criteria($root->id))->subcategories;

        $this->assertSame(
            [
                ['Alpha', 1],
                ['Alpha child', 2],
                ['Alpha grandchild', 3],
                ['Beta', 1],
            ],
            array_map(static fn(array $entry): array => [$entry['name'], $entry['depth']], $tree)
        );
        $this->assertNotContains((int) $orphan->id, array_column($tree, 'id'), 'The grandchild of a withheld child.');

        // The control: an admin, for whom nothing is withheld, gets the whole branch in its place.
        $this->setAdminUser();
        self::forget();
        $this->assertSame(
            [['Alpha', 1], ['Alpha child', 2], ['Alpha grandchild', 3], ['Beta', 1], ['Gamma', 1], ['Gamma child', 2]],
            array_map(
                static fn(array $entry): array => [$entry['name'], $entry['depth']],
                population::resolve(new criteria($root->id))->subcategories
            )
        );
    }

    /**
     * A visitor is offered a deep entry only when something public sits below it, linkable only when it is public.
     *
     * The rule holds at every depth: a name the visitor may read is not
     * always a page the visitor may open.
     */
    #[DataProvider('modes_provider')]
    public function test_the_anonymous_tree_carries_depth_and_keeps_the_public_rule(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $this->set_category_state((int) $root->id, category_discoverability::STATE_PUBLIC);
        $branch = $generator->create_category(['parent' => $root->id, 'name' => 'Branch']);
        $leaf = $generator->create_category(['parent' => $branch->id, 'name' => 'Leaf']);
        $second = $generator->create_category(['parent' => $root->id, 'name' => 'Second']);
        $barren = $generator->create_category(['parent' => $root->id, 'name' => 'Barren']);
        $this->set_category_state((int) $leaf->id, category_discoverability::STATE_PUBLIC);
        $this->set_category_state((int) $second->id, category_discoverability::STATE_PUBLIC);
        $deep = $generator->create_course(['category' => $leaf->id]);
        $this->set_course_state((int) $deep->id, discoverability::STATE_PUBLIC);
        $alongside = $generator->create_course(['category' => $second->id]);
        $this->set_course_state((int) $alongside->id, discoverability::STATE_PUBLIC);
        $generator->create_course(['category' => $barren->id]);

        $this->become(null);
        /* A page of one, which is what lets the two modes disagree: the page cut in PHP hands
           the tree the whole gated population, while the SQL cut materialises none, and a tree
           built from the page slice would name only the categories whose public courses fit on
           the page. Both public branches must be named however small the page is. */
        $tree = population::resolve(new criteria($root->id, ['anonymous' => true, 'pagesize' => 1]))->subcategories;

        $this->assertSame(
            [
                // The branch holds nothing public itself, but the leaf below it does: named, not linkable.
                ['id' => (int) $branch->id, 'name' => 'Branch', 'depth' => 1, 'linkable' => false],
                ['id' => (int) $leaf->id, 'name' => 'Leaf', 'depth' => 2, 'linkable' => true],
                ['id' => (int) $second->id, 'name' => 'Second', 'depth' => 1, 'linkable' => true],
            ],
            $tree,
            'A barren child is left out; every branch holding a public course below it is named.'
        );
    }

    /**
     * The plugin is asked about at most fifty unrelated unlisted courses; the rest are withheld unasked.
     *
     * And the related ones never count against the cap.
     *
     * Every unlisted course here has self enrolment open, so the probe would
     * admit each one it is asked about: what the cap withholds is exactly
     * the courses past it, in core order. The course the viewer is enrolled
     * in is shown on top of the fifty, because a related course is answered
     * from the primed memo and never spends the cap.
     */
    public function test_the_probe_is_capped_at_fifty_unrelated_unlisted_courses(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $viewer = $generator->create_user();
        $courses = [];
        for ($i = 0; $i < population::PROBE_CAP + 5; $i++) {
            $course = $generator->create_course(['category' => $root->id]);
            $this->enable_instance((int) $course->id, 'self', ['customint6' => 1]);
            $courses[] = (int) $course->id;
        }
        $mine = $generator->create_course(['category' => $root->id]);
        $generator->enrol_user($viewer->id, $mine->id);
        $this->setAdminUser();
        foreach (array_merge($courses, [(int) $mine->id]) as $courseid) {
            discoverability::set_state($courseid, discoverability::STATE_UNLISTED);
        }

        $this->become($viewer);
        $ids = $this->ids($root->id, ['pagesize' => 96]);
        $this->assertCount(population::PROBE_CAP + 1, $ids);
        $this->assertContains((int) $mine->id, $ids, 'The related course is shown and never spends the cap.');
        // Core order is newest first: the probe walks the last fifty created and never reaches the first five.
        $incoreorder = array_reverse($courses);
        foreach (array_slice($incoreorder, 0, population::PROBE_CAP) as $courseid) {
            $this->assertContains($courseid, $ids);
        }
        foreach (array_slice($incoreorder, population::PROBE_CAP) as $courseid) {
            $this->assertNotContains($courseid, $ids, 'A course past the cap is withheld without being asked about.');
        }
    }

    /**
     * The ordered ids of two hundred courses cost one statement, and the page's contexts none.
     */
    public function test_the_ordered_ids_cost_one_statement_and_the_page_contexts_none(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        for ($c = 0; $c < 5; $c++) {
            $category = $generator->create_category(['parent' => $root->id]);
            for ($i = 0; $i < 40; $i++) {
                $generator->create_course(['category' => $category->id]);
            }
        }
        $this->become($generator->create_user());
        $criteria = new criteria($root->id);
        $scope = population::scope($criteria);
        $this->assertCount(6, $scope->open);

        $records = [];
        $reads = $this->reads(function () use ($scope, $criteria, &$records) {
            $records = population::records($scope, $criteria);
        });
        $this->assertSame(1, $reads);
        $this->assertCount(200, $records);

        $page = array_slice($records, 0, 48, true);
        $reads = $this->reads(function () use ($page) {
            $contexts = population::page_contexts($page);
            $this->assertCount(48, $contexts);
            foreach ($contexts as $courseid => $context) {
                $this->assertSame(CONTEXT_COURSE, $context->contextlevel);
                $this->assertSame($courseid, (int) $context->instanceid);
            }
        });
        $this->assertSame(0, $reads, 'The page contexts are rebuilt from the rows already held.');
        // The rows kept their preload columns for the next reader.
        $this->assertObjectHasProperty('ctxid', reset($page));
    }

    /**
     * The anonymous shell lists visible courses whose own state is public, under admitted categories, and nothing else.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_anonymous_shell_lists_public_courses_only(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $this->set_category_state((int) $root->id, category_discoverability::STATE_PUBLIC);
        $listedchild = $generator->create_category(['parent' => $root->id, 'name' => 'Listed child']);
        $emptychild = $generator->create_category(['parent' => $root->id, 'name' => 'Empty child']);
        $unlistedchild = $generator->create_category(['parent' => $root->id]);
        $hiddenchild = $generator->create_category(['parent' => $root->id, 'visible' => 0]);
        $this->set_category_state((int) $unlistedchild->id, category_discoverability::STATE_UNLISTED);

        $public = $generator->create_course(['category' => $root->id]);
        $listed = $generator->create_course(['category' => $root->id]);
        $unlisted = $generator->create_course(['category' => $root->id]);
        $hiddenpublic = $generator->create_course(['category' => $root->id, 'visible' => 0]);
        $inlisted = $generator->create_course(['category' => $listedchild->id]);
        $inempty = $generator->create_course(['category' => $emptychild->id]);
        $inunlisted = $generator->create_course(['category' => $unlistedchild->id]);
        $inhidden = $generator->create_course(['category' => $hiddenchild->id]);
        foreach ([$public, $hiddenpublic, $inlisted, $inunlisted, $inhidden] as $course) {
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $this->assertNotEmpty($inempty);

        $publicchild = $generator->create_category(['parent' => $root->id, 'name' => 'Public child']);
        $this->set_category_state((int) $publicchild->id, category_discoverability::STATE_PUBLIC);
        $inpublic = $generator->create_course(['category' => $publicchild->id]);
        $this->set_course_state((int) $inpublic->id, discoverability::STATE_PUBLIC);

        /* The flat organisation, so what is asserted is the whole admitted population: the
           grouped default would show this category's own public course alone, the two in
           children being inside their panels. The visitor's tree below reads the population
           either way. */
        $this->become(null);
        $result = population::resolve(new criteria($root->id, ['anonymous' => true, 'sort' => criteria::SORT_NAME]));
        $this->assertEqualsCanonicalizing([(int) $public->id, (int) $inlisted->id, (int) $inpublic->id], $result->ids());
        $this->assertSame(3, $result->total);
        $this->assertTrue($result->anonymous);
        $this->assertArrayNotHasKey('state', (array) reset($result->rows), 'No enrolment state for a visitor.');
        // An entry for every admitted child holding a public course, linkable only where the child is itself public.
        $this->assertSame(
            [
                ['id' => (int) $listedchild->id, 'name' => 'Listed child', 'depth' => 1, 'linkable' => false],
                ['id' => (int) $publicchild->id, 'name' => 'Public child', 'depth' => 1, 'linkable' => true],
            ],
            $result->subcategories,
            'Only a child holding a public course is offered to a visitor, and only a public child may be chosen.'
        );

        // The anonymous shape is refused inside a logged-in session: its category half reads the session.
        $this->become($generator->create_user());
        try {
            population::resolve(new criteria($root->id, ['anonymous' => true]));
            $this->fail('An anonymous population was resolved for a logged-in user.');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('visitor session', $e->getMessage());
        }

        // Control: a logged-in stranger on the same root sees the listed courses and the public ones, never the unlisted.
        $this->assertEqualsCanonicalizing(
            [(int) $public->id, (int) $listed->id, (int) $inlisted->id, (int) $inempty->id, (int) $inpublic->id],
            $this->subtree_ids($root->id)
        );
    }

    /**
     * The root is refused with one exception whatever the reason, and the refusal is not an oracle.
     */
    public function test_the_root_is_refused_with_one_exception_for_every_reason(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $hidden = $generator->create_category(['visible' => 0]);
        $unlisted = $generator->create_category();
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $listed = $generator->create_category();
        $missing = (int) $listed->id + 1000;

        $messages = [];
        $this->become($generator->create_user());
        foreach (['hidden' => $hidden->id, 'unlisted' => $unlisted->id, 'missing' => $missing] as $reason => $id) {
            try {
                population::resolve(new criteria((int) $id));
                $this->fail("A {$reason} root was listed.");
            } catch (unavailable_exception $e) {
                $messages[$reason] = $e->getMessage();
            }
        }
        $this->become(null);
        foreach (['listed' => $listed->id, 'missing' => $missing] as $reason => $id) {
            try {
                population::resolve(new criteria((int) $id, ['anonymous' => true]));
                $this->fail("A {$reason} root was served anonymously.");
            } catch (unavailable_exception $e) {
                $messages['visitor ' . $reason] = $e->getMessage();
            }
        }
        $this->assertCount(1, array_unique($messages), 'One message for every refusal.');

        // Controls: the same roots resolve for somebody who may list them.
        $this->setAdminUser();
        self::forget();
        $this->assertSame(0, population::resolve(new criteria((int) $hidden->id))->total);
        $this->assertSame(0, population::resolve(new criteria((int) $unlisted->id))->total);
        $this->set_category_state((int) $listed->id, category_discoverability::STATE_PUBLIC);
        $this->become(null);
        $this->assertSame(0, population::resolve(new criteria((int) $listed->id, ['anonymous' => true]))->total);
    }

    /**
     * The state chip and the favourites chip filter the whole population before the page is cut.
     */
    public function test_state_chip_and_favourites_chip_filter_before_paging(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $courses = [];
        for ($i = 0; $i < 5; $i++) {
            $courses[] = $generator->create_course(['category' => $root->id]);
        }
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $courses[3]->id);
        $usercontext = \core\context\user::instance($viewer->id);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)->create_favourite(
            'core_course',
            'courses',
            $courses[4]->id,
            \core\context\course::instance($courses[4]->id)
        );

        $this->become($viewer);
        $favourites = population::resolve(new criteria($root->id, ['pagesize' => 2, 'state' => classifier::CHIP_FAVOURITES]));
        $this->assertSame([(int) $courses[4]->id], $favourites->ids(), 'The favourite past the first page is on page one.');
        $this->assertSame(1, $favourites->total);
        $this->assertTrue($favourites->rows[(int) $courses[4]->id]->favourite);
        $this->assertSame(5, $favourites->counts['total'], 'The histogram is taken before the chip.');
        $this->assertSame(1, $favourites->counts[classifier::CHIP_FAVOURITES]);

        $enrolled = population::resolve(new criteria($root->id, ['pagesize' => 2, 'state' => classifier::STATE_ENROLLED]));
        $this->assertSame([(int) $courses[3]->id], $enrolled->ids());
        $this->assertSame(1, $enrolled->counts[classifier::STATE_ENROLLED]);
        $this->assertSame(4, $enrolled->counts[classifier::STATE_NONE]);
    }

    /**
     * A chip counts and keeps every state it covers, and the page still carries each card's own state.
     *
     * Key counts under open, suspended and ended under none, and a waiting-list application under "under review"
     * where enrol_apply is installed. The histogram holds the six chip values and the favourites and nothing else:
     * a state counted under its own name would be a key no chip reads.
     */
    public function test_the_chips_count_and_keep_the_states_they_cover(): void {
        global $DB;

        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $viewer = $generator->create_user();
        $open = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $open->id, 'self', ['customint6' => 1]);
        $key = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $key->id, 'guest', ['password' => 'chave']);
        $suspended = $generator->create_course(['category' => $root->id]);
        $generator->enrol_user($viewer->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $ended = $generator->create_course(['category' => $root->id]);
        $generator->enrol_user($viewer->id, $ended->id, 'student', 'manual', time() - 9 * DAYSECS, time() - DAYSECS);
        $nothing = $generator->create_course(['category' => $root->id]);
        $pending = [];
        if (\core_component::get_component_directory('enrol_apply')) {
            $this->enable_enrol('apply');
            $waiting = $generator->create_course(['category' => $root->id]);
            $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
            enrol_get_plugin('apply')->add_instance($waiting, [
                'status' => ENROL_INSTANCE_ENABLED,
                'roleid' => $studentrole->id,
                'customint6' => 1,
            ]);
            $generator->enrol_user($viewer->id, $waiting->id, null, 'apply', 0, 0, 2);
            $pending[(int) $waiting->id] = classifier::STATE_WAITLISTED;
        }

        $this->become($viewer);
        $all = population::resolve(new criteria((int) $root->id));
        $this->assertEqualsCanonicalizing(
            ['total', ...classifier::chips(), classifier::CHIP_FAVOURITES],
            array_keys($all->counts),
            'The histogram is the chips\' and nothing else.'
        );
        $this->assertSame(2, $all->counts[classifier::STATE_OPEN], 'Open and key.');
        $this->assertSame(3, $all->counts[classifier::STATE_NONE], 'Suspended, ended and nothing.');
        $this->assertSame(count($pending), $all->counts[classifier::STATE_PENDING], 'The waiting list, where it exists.');

        $keptopen = population::resolve(new criteria((int) $root->id, ['state' => classifier::STATE_OPEN]));
        $this->assertEqualsCanonicalizing([(int) $open->id, (int) $key->id], $keptopen->ids());
        $this->assertSame(classifier::STATE_KEY, $keptopen->rows[(int) $key->id]->state, 'The card keeps its own state.');

        $keptnone = population::resolve(new criteria((int) $root->id, ['state' => classifier::STATE_NONE]));
        $this->assertEqualsCanonicalizing([(int) $suspended->id, (int) $ended->id, (int) $nothing->id], $keptnone->ids());
        $this->assertSame(classifier::STATE_SUSPENDED, $keptnone->rows[(int) $suspended->id]->state);
        $this->assertSame(classifier::STATE_EXPIRED, $keptnone->rows[(int) $ended->id]->state);
        $this->assertSame(classifier::STATE_NONE, $keptnone->rows[(int) $nothing->id]->state, 'Control: plain none.');

        $keptpending = population::resolve(new criteria((int) $root->id, ['state' => classifier::STATE_PENDING]));
        $this->assertEqualsCanonicalizing(array_keys($pending), $keptpending->ids());
        foreach ($pending as $courseid => $state) {
            $this->assertSame($state, $keptpending->rows[$courseid]->state);
        }
    }

    /**
     * A suspended or ended enrolment rescues nothing: not from an unlisted category, not as an unlisted course.
     *
     * local_unlistedcourses keeps a course discoverable for an enrolled, scheduled, pending or waitlisted viewer
     * only; the theme's own rescue must agree, or the listing would name a course the plugin withholds. The
     * scheduled course in the same unlisted category is the control that the rescue runs at all.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_suspended_or_ended_enrolment_rescues_nothing(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id]);
        $shown = $generator->create_course(['category' => $root->id]);
        $scheduled = $generator->create_course(['category' => $child->id]);
        $suspended = $generator->create_course(['category' => $child->id]);
        $ended = $generator->create_course(['category' => $child->id]);
        $unlistedsuspended = $generator->create_course(['category' => $root->id]);
        $this->set_category_state((int) $child->id, category_discoverability::STATE_UNLISTED);
        $this->set_course_state((int) $unlistedsuspended->id, discoverability::STATE_UNLISTED);

        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $scheduled->id, 'student', 'manual', time() + 5 * DAYSECS);
        $generator->enrol_user($viewer->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($viewer->id, $ended->id, 'student', 'manual', time() - 9 * DAYSECS, time() - DAYSECS);
        $generator->enrol_user($viewer->id, $unlistedsuspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->become($viewer);
        $this->assertEqualsCanonicalizing([(int) $shown->id, (int) $scheduled->id], $this->subtree_ids($root->id));
    }

    /**
     * "A-Z" and "Recent" order the whole listing, identically in both page-cutting modes.
     *
     * The two modes reach the order by different means - one statement's
     * ORDER BY against a usort over the population the other holds - so the
     * assertion is made in both over one fixture. The names are deliberately
     * neither in creation order nor in core's own sortorder, so an unsorted
     * page cannot pass by coincidence.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_each_organisation_orders_the_whole_listing_in_both_modes(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $day = criteria::day_start('2026-09-17');
        $charlie = $generator->create_course(['category' => $root->id, 'fullname' => 'Charlie', 'startdate' => $day]);
        $alpha = $generator->create_course([
            'category' => $root->id,
            'fullname' => 'Alpha',
            'startdate' => $day - (7 * DAYSECS),
        ]);
        $bravo = $generator->create_course([
            'category' => $root->id,
            'fullname' => 'Bravo',
            'startdate' => $day + (7 * DAYSECS),
        ]);

        $this->become($generator->create_user());
        $this->assertSame(
            [(int) $alpha->id, (int) $bravo->id, (int) $charlie->id],
            $this->ids($root->id, ['sort' => criteria::SORT_NAME]),
            'A to Z by name, whatever the category order is.'
        );
        self::forget();
        $this->assertSame(
            [(int) $bravo->id, (int) $charlie->id, (int) $alpha->id],
            $this->ids($root->id, ['sort' => criteria::SORT_RECENT]),
            'The newest start date first.'
        );
    }

    /**
     * A course with no start date is last under "Recent", in both modes.
     *
     * A zero is not a date, and the epoch is not where those courses belong.
     * The ordering says so with a portable CASE rather than NULLS LAST, which
     * MariaDB does not have, so on MariaDB this test also fails if the
     * ordering ever comes to rely on it.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_course_with_no_start_date_is_last_under_recent(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $day = criteria::day_start('2026-09-17');
        $newest = $generator->create_course(['category' => $root->id, 'fullname' => 'Newest', 'startdate' => $day]);
        $undated = $generator->create_course(['category' => $root->id, 'fullname' => 'Undated', 'startdate' => 0]);
        $older = $generator->create_course([
            'category' => $root->id,
            'fullname' => 'Older',
            'startdate' => $day - (30 * DAYSECS),
        ]);
        // The precondition: the generator really did store a zero, or this test proves nothing.
        global $DB;
        $this->assertSame(0, (int) $DB->get_field('course', 'startdate', ['id' => $undated->id]));

        $this->become($generator->create_user());
        $this->assertSame(
            [(int) $newest->id, (int) $older->id, (int) $undated->id],
            $this->ids($root->id, ['sort' => criteria::SORT_RECENT])
        );
    }

    /**
     * The start-date range bounds the page in both modes, with one bound or both, and the last day is inside it.
     *
     * Two controls, one on each side, and a course starting a minute before
     * the exclusive upper bound: a range whose upper bound were the last day's
     * own midnight would silently drop every course starting during the last
     * day the reader picked. Each bound is then asked alone, against a course
     * on each side of it: a start alone has no upper bound (the course after
     * the range is kept), an end alone no lower one (the course before it is
     * kept), and neither keeps the undated course (criteria's rule). A pair
     * given backwards keeps what the pair in order keeps. The timestamps are
     * built with the same helper the predicate is built with, so the test does
     * not depend on the site's timezone.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_start_date_range_bounds_the_page_in_both_modes(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $from = '2026-09-17';
        $to = '2026-09-19';
        $first = $generator->create_course([
            'category' => $root->id,
            'startdate' => criteria::day_start($from),
        ]);
        $last = $generator->create_course([
            'category' => $root->id,
            'startdate' => criteria::day_start($to, 1) - MINSECS,
        ]);
        $before = $generator->create_course([
            'category' => $root->id,
            'startdate' => criteria::day_start($from) - MINSECS,
        ]);
        $after = $generator->create_course([
            'category' => $root->id,
            'startdate' => criteria::day_start($to, 1),
        ]);
        $undated = $generator->create_course(['category' => $root->id, 'startdate' => 0]);

        $this->become($generator->create_user());
        // The control: with no bound at all, all five are listed.
        $this->assertCount(5, $this->ids($root->id));

        self::forget();
        $kept = $this->ids($root->id, ['datefrom' => $from, 'dateto' => $to]);
        $this->assertEqualsCanonicalizing([(int) $first->id, (int) $last->id], $kept);
        $this->assertNotContains((int) $before->id, $kept, 'A course starting before the first day.');
        $this->assertNotContains((int) $after->id, $kept, 'A course starting at the exclusive upper bound.');
        $this->assertNotContains((int) $undated->id, $kept, 'A zero is not a date.');

        // One bound alone is a bound: from-only keeps everything at or after that midnight.
        self::forget();
        $this->assertEqualsCanonicalizing(
            [(int) $first->id, (int) $last->id, (int) $after->id],
            $this->ids($root->id, ['datefrom' => $from])
        );
        self::forget();
        $this->assertEqualsCanonicalizing(
            [(int) $before->id, (int) $first->id, (int) $last->id],
            $this->ids($root->id, ['dateto' => $to])
        );

        self::forget();
        $this->assertEqualsCanonicalizing(
            [(int) $first->id, (int) $last->id],
            $this->ids($root->id, ['datefrom' => $to, 'dateto' => $from]),
            'A pair given backwards is the same period.'
        );
    }

    /**
     * The facet counts are taken after the date filter, in both modes.
     *
     * With a control that the filter really removes something: the same option
     * counts two courses with no date bound and one with it, so an assertion
     * that read a count of one from a page that had never been narrowed would
     * have to be reading the wrong number twice.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_facet_counts_are_taken_after_the_date_filter(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_MODEL)->get('id');

        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $handler = \core_course\customfield\course_handler::create();
        $day = criteria::day_start('2026-09-17');
        foreach ([$day, $day + (30 * DAYSECS)] as $startdate) {
            $course = $generator->create_course(['category' => $root->id, 'startdate' => $startdate]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            ]);
        }

        $this->become($generator->create_user());
        $all = population::resolve(new criteria($root->id));
        $this->assertSame(
            2,
            $all->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count'],
            'The control: both events are counted while nothing narrows the page.'
        );

        self::forget();
        $narrowed = population::resolve(new criteria($root->id, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-17']));
        $this->assertSame(1, $narrowed->total);
        $this->assertSame(
            1,
            $narrowed->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count'],
            'The facet counts what the date filter left, not what the category holds.'
        );
    }

    /**
     * The tree depth bounds what is offered and never what is listed, in both modes.
     *
     * A depth of one must leave the grandchild's course on the page and inside
     * its parent's number, and take only the grandchild's own name out of the
     * tree. The control is the same resolve with the depth set to 0, every level.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_tree_depth_bounds_the_navigation_and_never_the_listing(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $child = $generator->create_category(['parent' => $root->id, 'name' => 'Child']);
        $grandchild = $generator->create_category(['parent' => $child->id, 'name' => 'Grandchild']);
        $near = $generator->create_course(['category' => $child->id]);
        $deep = $generator->create_course(['category' => $grandchild->id]);

        $this->become($generator->create_user());
        set_config(population::SETTING_TREEDEPTH, '0', 'theme_dimensions');
        $all = population::resolve(new criteria($root->id));
        $this->assertSame(['Child', 'Grandchild'], array_column($all->subcategories, 'name'), 'The control.');

        self::forget();
        set_config(population::SETTING_TREEDEPTH, '1', 'theme_dimensions');
        $capped = population::resolve(new criteria($root->id));
        $this->assertSame(['Child'], array_column($capped->subcategories, 'name'), 'The tree stops at one level.');
        /* The listing is read through the flat organisation, the one that lists the subtree:
           the invariant under test is that the depth setting never narrows the population, and
           the grouped page of a category whose courses all sit below it draws none of them for
           a reason that has nothing to do with depth. */
        self::forget();
        $cappedflat = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertEqualsCanonicalizing(
            [(int) $near->id, (int) $deep->id],
            $cappedflat->ids(),
            'And the listing is the whole subtree, as recursive as it ever was.'
        );
        $this->assertSame(2, $cappedflat->total);
        $this->assertSame(
            [2],
            array_column($capped->accordion, 'count'),
            'The one offered child counts its whole subtree, the deeper course included.'
        );
        $this->assertSame(
            0,
            $capped->total,
            'And the grouped page of a category whose courses all sit below it lists none of its own, '
                . 'which the header beside it counts instead.'
        );
    }

    /**
     * The accordion is the direct children, each with its own subtree count, and only when grouped.
     *
     * The numbers partition the listing: every course of the page is either in
     * this category itself or in exactly one direct child's subtree, which is
     * why a panel may list a child's whole subtree without listing anything
     * twice. A flat organisation has no accordion at all.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_accordion_is_one_entry_per_direct_child_with_its_subtree_count(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $first = $generator->create_category(['parent' => $root->id, 'name' => 'First', 'sortorder' => 10]);
        $deeper = $generator->create_category(['parent' => $first->id, 'name' => 'Deeper']);
        $second = $generator->create_category(['parent' => $root->id, 'name' => 'Second', 'sortorder' => 20]);
        $generator->create_course(['category' => $root->id]);
        $generator->create_course(['category' => $first->id]);
        $generator->create_course(['category' => $deeper->id]);
        $generator->create_course(['category' => $second->id]);

        $this->become($generator->create_user());
        $grouped = population::resolve(new criteria($root->id));
        $this->assertSame(
            [['First', 2], ['Second', 1]],
            array_map(static fn(array $g): array => [$g['name'], $g['count']], $grouped->accordion),
            'One entry per direct child, each counting its whole subtree.'
        );
        $this->assertSame(
            1,
            $grouped->total,
            'The page itself holds the course of this category alone, and the headers hold the rest.'
        );
        $this->assertSame(
            4,
            array_sum(array_column($grouped->accordion, 'count')) + $grouped->total,
            'The headers and the page partition the subtree: nothing is counted twice and nothing is lost.'
        );
        foreach ($grouped->accordion as $group) {
            $this->assertNotSame('', $group['url'], 'Every entry offers the page it names.');
            $this->assertTrue($group['linkable']);
        }

        self::forget();
        $flat = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertSame([], $flat->accordion, 'A flat organisation draws no accordion.');
        $this->assertSame(4, $flat->total, 'And it lists the whole subtree, which is what makes it flat.');
    }

    /**
     * The grouped page lists the direct courses, while every number on it keeps reading the subtree.
     *
     * The fixture: Saude with two courses of its own, its child Enfermagem with
     * two and a grandchild UTI holding one, and a second child Odontologia with
     * one. Six courses, and the question is which of them the first group holds.
     *
     * The course in the child is the control: it must be absent from the page
     * and present in the hero's total, the facet count and its child's header in
     * the same run, so an implementation that cut the whole population fails on
     * those numbers rather than passing quietly. The facet is counted over the
     * population alone, so a count that dropped to the direct courses would have
     * the pills and the page disagreeing about the same category.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_grouped_page_is_the_direct_courses_while_every_number_keeps_the_subtree(
        bool $statesort
    ): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_MODEL)->get('id');
        $handler = \core_course\customfield\course_handler::create();

        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem', 'sortorder' => 10]);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $dentistry = $generator->create_category(['parent' => $root->id, 'name' => 'Odontologia', 'sortorder' => 20]);
        $firstaid = $generator->create_course(['category' => $root->id, 'fullname' => 'Primeiros socorros']);
        $biosafety = $generator->create_course(['category' => $root->id, 'fullname' => 'Biosseguranca']);
        $intensive = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $wounds = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Feridas']);
        $ventilation = $generator->create_course(['category' => $icu->id, 'fullname' => 'Ventilacao mecanica']);
        $prosthesis = $generator->create_course(['category' => $dentistry->id, 'fullname' => 'Protese']);
        // The control wears a facet value, so its absence from the page can be told from its
        // absence from the population.
        $handler->instance_form_save((object) [
            'id' => $intensive->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
        ]);

        $this->become($generator->create_user());
        $grouped = population::resolve(new criteria($root->id));

        $this->assertEqualsCanonicalizing(
            [(int) $firstaid->id, (int) $biosafety->id],
            $grouped->ids(),
            'The first group is this category\'s own courses, and a child\'s course is not in it.'
        );
        $this->assertNotContains((int) $intensive->id, $grouped->ids(), 'The control, said the other way round.');
        $this->assertSame(2, $grouped->total, 'The count line and the pager walk the direct courses.');
        $this->assertSame(6, $grouped->counts['total'], 'The hero and the "all" pill still count the subtree.');
        $this->assertSame(
            [['Enfermagem', 3], ['Odontologia', 1]],
            array_map(static fn(array $g): array => [$g['name'], $g['count']], $grouped->accordion),
            'And each header still counts its child\'s whole subtree, the grandchild included.'
        );
        $this->assertSame(
            1,
            $grouped->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count'],
            'The facet counts the control, which sits in a child and is on no page of this listing.'
        );

        // The same request organised flat: one listing of all six, no accordion, the same numbers.
        self::forget();
        $flat = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertCount(6, $flat->ids(), 'A flat organisation lists the whole subtree.');
        $this->assertContains((int) $ventilation->id, $flat->ids());
        $this->assertContains((int) $prosthesis->id, $flat->ids());
        $this->assertContains((int) $wounds->id, $flat->ids());
        $this->assertSame(6, $flat->total);
        $this->assertSame(
            $grouped->counts['total'],
            $flat->counts['total'],
            'Changing the organisation moves no number the hero or the pills print.'
        );
    }

    /**
     * The tree's category narrows the population to its subtree, and every number follows it.
     *
     * Pressing a category in the tree beside a flat listing filters that listing
     * instead of opening the category's page, so it is a filter like the others:
     * the count line, the pager, the hero's total and the numbers beside the
     * facet pills all count what is left. That is why the predicate goes into
     * the population statement rather than cutting the page afterwards, as the
     * grouped partition does.
     *
     * Two controls, both necessary. The course of a sibling category is on the
     * page without the criterion and must be off it with one, which is the
     * narrowing itself; and the facet value only that course carries must go
     * from one to zero, which a filter applied after the counting would leave
     * at one.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_tree_criterion_narrows_the_population_to_the_chosen_subtree(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_MODEL)->get('id');
        $handler = \core_course\customfield\course_handler::create();

        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem', 'sortorder' => 10]);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $dentistry = $generator->create_category(['parent' => $root->id, 'name' => 'Odontologia', 'sortorder' => 20]);
        $firstaid = $generator->create_course(['category' => $root->id, 'fullname' => 'Primeiros socorros']);
        $intensive = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $ventilation = $generator->create_course(['category' => $icu->id, 'fullname' => 'Ventilacao mecanica']);
        $prosthesis = $generator->create_course(['category' => $dentistry->id, 'fullname' => 'Protese']);
        // The control wears the facet value, so what happens to the number beside the pill is visible.
        $handler->instance_form_save((object) [
            'id' => $prosthesis->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
        ]);

        $this->become($generator->create_user());
        $all = population::resolve(new criteria($root->id, ['sort' => criteria::SORT_NAME]));
        $this->assertCount(4, $all->ids(), 'The control: the whole subtree is on the flat listing.');
        $this->assertContains((int) $prosthesis->id, $all->ids());
        $this->assertSame(
            1,
            $all->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count'],
            'And the pill beside it counts that course.'
        );

        self::forget();
        $criteria = new criteria($root->id, ['sort' => criteria::SORT_NAME, 'cat' => (int) $nursing->id]);
        $narrowed = population::resolve($criteria);
        $this->assertEqualsCanonicalizing(
            [(int) $intensive->id, (int) $ventilation->id],
            $narrowed->ids(),
            'The listing is the chosen category and everything below it, the grandchild included.'
        );
        $this->assertNotContains((int) $prosthesis->id, $narrowed->ids(), 'The sibling\'s course is gone.');
        $this->assertNotContains((int) $firstaid->id, $narrowed->ids(), 'And so is the page category\'s own.');
        $this->assertSame(2, $narrowed->total, 'The count line and the pager walk what is left.');
        $this->assertSame(2, $narrowed->counts['total'], 'The hero and the "all" pill count what is left too.');
        $this->assertSame(
            0,
            $narrowed->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count'] ?? 0,
            'The facet was counted AFTER the criterion: the pill no longer promises a course this page cannot show.'
        );
        $this->assertSame((int) $nursing->id, $criteria->cat, 'An admitted category is kept, and is a filter.');
        $this->assertTrue($criteria->has_filters());
        $this->assertSame(1, $criteria->filter_count());
    }

    /**
     * A tree criterion this page never offered is dropped, and the listing is exactly what it was.
     *
     * The guard, from four sides: a category of another subtree, one that does
     * not exist at all, one inside this subtree that this viewer may not browse,
     * and the page's own category. None of them is an error - the value arrives
     * from a query string, and a page that refuses to render cannot be linked
     * to - and none of them may narrow anything.
     *
     * Each case asserts two things, because either alone can pass. The listing
     * must be untouched, which says the criterion did not reach the statement;
     * and the request object must carry no filter afterwards, because the
     * toolbar's count, the clear link and the tree's marking are built from it.
     * Otherwise the page either narrows to nothing or claims a filter nobody
     * can clear.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_tree_criterion_the_page_never_offered_is_dropped_and_narrows_nothing(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $open = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem']);
        $withheld = $generator->create_category(['parent' => $root->id, 'name' => 'Oculta', 'visible' => 0]);
        $elsewhere = $generator->create_category(['name' => 'Outra arvore']);
        $here = $generator->create_course(['category' => $open->id, 'fullname' => 'Cuidados intensivos']);
        $hidden = $generator->create_course(['category' => $withheld->id, 'fullname' => 'Curso oculto']);
        $foreign = $generator->create_course(['category' => $elsewhere->id, 'fullname' => 'Curso de outra arvore']);

        $this->become($generator->create_user());
        $control = $this->subtree_ids($root->id);
        $this->assertSame([(int) $here->id], $control, 'The control: one course, the other two beyond this page.');
        $this->assertNotContains((int) $hidden->id, $control);
        $this->assertNotContains((int) $foreign->id, $control);

        $missing = (int) $elsewhere->id + 10000;
        $cases = [
            'a category of another subtree' => (int) $elsewhere->id,
            'a category that does not exist' => $missing,
            'a category this viewer may not browse' => (int) $withheld->id,
            'the page\'s own category, which means all of it' => (int) $root->id,
        ];
        foreach ($cases as $what => $cat) {
            self::forget();
            $criteria = new criteria($root->id, ['sort' => criteria::SORT_NAME, 'cat' => $cat]);
            $result = population::resolve($criteria);
            $this->assertSame($control, $result->ids(), 'The listing is untouched by ' . $what . '.');
            $this->assertSame(0, $criteria->cat, 'And the request stops claiming ' . $what . ' narrows it.');
            $this->assertFalse($criteria->has_filters(), 'So nothing offers to clear a filter nobody set.');
            $this->assertSame(0, $criteria->filter_count());
        }
    }

    /**
     * A panel's request lists the area's whole subtree and carries the lean strip of its subareas.
     *
     * `withcategories` answers with the lean strip: the depth-1 subareas, each a name, a link and
     * its own colour as a dot, and no directory card. The grandchild's course is listed, because a
     * panel's population is the subtree; the plain request of the same category, which asks for
     * no panel, is the strip's control and carries none.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_panel_lists_the_subtree_and_carries_the_lean_strip(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Saude']);
        $nursing = $generator->create_category(['parent' => $root->id, 'name' => 'Enfermagem']);
        $icu = $generator->create_category(['parent' => $nursing->id, 'name' => 'UTI']);
        $deeper = $generator->create_category(['parent' => $icu->id, 'name' => 'Neonatal']);
        $intensive = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Cuidados intensivos']);
        $wounds = $generator->create_course(['category' => $nursing->id, 'fullname' => 'Feridas']);
        $ventilation = $generator->create_course(['category' => $icu->id, 'fullname' => 'Ventilacao mecanica']);
        $generator->create_course(['category' => $deeper->id, 'fullname' => 'Neonatal intensivo']);
        $this->setAdminUser();
        branding::save((int) $icu->id, (object) ['bgcolour' => '#B3261E', 'inkcolour' => '#ffffff']);

        $this->become($generator->create_user());
        $plain = population::resolve(new criteria((int) $nursing->id, ['sort' => criteria::SORT_RECENT]));
        $this->assertSame([], $plain->subareas, 'No strip unless a panel asked for one.');

        self::forget();
        $panel = population::resolve(new criteria((int) $nursing->id, [
            'sort' => criteria::SORT_RECENT,
            'withcategories' => true,
        ]));
        $this->assertContains((int) $ventilation->id, $panel->ids(), 'A panel lists the SUBTREE.');
        $this->assertContains((int) $intensive->id, $panel->ids(), 'The area\'s own courses with it.');
        $this->assertContains((int) $wounds->id, $panel->ids());
        $this->assertSame(4, $panel->total);
        $this->assertSame([], $panel->accordion, 'A panel is one poster of somebody else\'s page and has no accordion.');
        $this->assertSame([], $panel->directory, 'A panel carries no directory cards.');
        $this->assertSame(
            [[
                'id' => (int) $icu->id,
                'name' => 'UTI',
                'depth' => 1,
                'linkable' => true,
                'url' => links::showcase((int) $icu->id, [], false)->out(false),
                'style' => '--dim-dot: #b3261e;',
            ]],
            $panel->subareas,
            'One pill per DIRECT subarea - the level below it is not the strip\'s - with its own colour.'
        );
    }

    /**
     * A custom field filter narrows the rows and the numbers, and the facets say how many would remain.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_field_filter_narrows_rows_and_counts(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        $model = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        $fieldid = (int) $model->get('id');

        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $handler = \core_course\customfield\course_handler::create();
        $events = [];
        for ($i = 0; $i < 2; $i++) {
            $course = $generator->create_course(['category' => $root->id]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            ]);
            $events[] = (int) $course->id;
        }
        $plaincourse = $generator->create_course(['category' => $root->id]);
        $handler->instance_form_save((object) [
            'id' => $plaincourse->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        $unset = $generator->create_course(['category' => $root->id]);
        $withheld = $generator->create_course(['category' => $root->id]);
        $handler->instance_form_save((object) [
            'id' => $withheld->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
        ]);
        $this->set_course_state((int) $withheld->id, discoverability::STATE_UNLISTED);
        $this->assertNotEmpty($unset);

        $this->become($generator->create_user());
        $all = population::resolve(new criteria($root->id));
        $this->assertSame(4, $all->total);
        $eventcount = $all->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count'];
        $this->assertSame(2, $eventcount, 'The withheld event is not counted.');
        $this->assertSame(1, $all->facets[$fieldid]['options'][landingfields::MODEL_COURSE]['count']);

        $filtered = population::resolve(new criteria($root->id, ['fields' => [$fieldid => landingfields::MODEL_EVENT]]));
        $this->assertEqualsCanonicalizing($events, $filtered->ids());
        $this->assertSame(2, $filtered->total);
        $this->assertSame(2, $filtered->counts['total']);
        $this->assertSame(2, $filtered->facets[$fieldid]['options'][landingfields::MODEL_EVENT]['count']);
    }

    /**
     * Rows come grouped by state first - the viewer's own, then what they can join, then the rest - and in core order inside.
     */
    public function test_state_groups_come_first_then_core_order_unless_the_hatch_is_off(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $none = $generator->create_course(['category' => $root->id]);
        $enrolled = $generator->create_course(['category' => $root->id]);
        $open = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $open->id, 'self', ['customint6' => 1]);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $enrolled->id);

        $this->become($viewer);
        $result = population::resolve(new criteria($root->id));
        $this->assertSame([(int) $enrolled->id, (int) $open->id, (int) $none->id], $result->ids());
        $this->assertTrue($result->statesort);
        $this->assertSame(classifier::STATE_OPEN, $result->rows[(int) $open->id]->state);

        $this->mode(false);
        $criteria = new criteria($root->id, ['state' => classifier::STATE_OPEN]);
        $result = population::resolve($criteria);
        // Core order is newest first, so the last course created leads.
        $this->assertSame(
            [(int) $open->id, (int) $enrolled->id, (int) $none->id],
            $result->ids(),
            'Core order alone, chip ignored.'
        );
        $this->assertNull($criteria->state, 'And dropped on the request, so nothing downstream counts it as a filter.');
        $this->assertFalse($result->statesort);
        $this->assertSame(['total' => 3], $result->counts);
        $this->assertSame(classifier::STATE_ENROLLED, $result->rows[(int) $enrolled->id]->state, 'The page still classifies.');
    }

    /**
     * Pages slice the sorted list and say whether more follows.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_paging_slices_the_sorted_list(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = (int) $generator->create_course(['category' => $root->id])->id;
        }
        $this->become($generator->create_user());

        $seen = [];
        foreach ([[0, 2, true], [1, 2, true], [2, 1, false]] as [$page, $rows, $more]) {
            $result = population::resolve(new criteria($root->id, ['pagesize' => 2, 'page' => $page]));
            $this->assertCount($rows, $result->rows, "page {$page}");
            $this->assertSame($more, $result->hasmore, "page {$page}");
            $this->assertSame(5, $result->total);
            $this->assertSame($page, $result->page);
            $seen = array_merge($seen, $result->ids());
        }
        // Core lists the newest course of a category first: the sort order is reverse creation order.
        $this->assertSame(array_reverse($ids), $seen, 'Three pages walk the whole list once, in core order.');
    }

    /**
     * A guest lands on the listing like a logged-in user and sees nothing unlisted.
     */
    public function test_a_guest_sees_the_listed_courses_and_nothing_unlisted(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $listed = $generator->create_course(['category' => $root->id]);
        $unlisted = $generator->create_course(['category' => $root->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);

        $this->setGuestUser();
        self::forget();
        $result = population::resolve(new criteria($root->id));
        $this->assertSame([(int) $listed->id], $result->ids());
        $this->assertSame(classifier::STATE_NONE, $result->rows[(int) $listed->id]->state);
    }

    /**
     * The search matches the full name, the short name and the id number, and a one-character term is no search.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_search_matches_three_columns_and_a_short_term_is_ignored(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $byname = $generator->create_course(['category' => $root->id, 'fullname' => 'Alpha course', 'shortname' => 'sn-1']);
        $byshort = $generator->create_course(['category' => $root->id, 'fullname' => 'Other', 'shortname' => 'ALPHA-2']);
        $byidnumber = $generator->create_course([
            'category' => $root->id, 'fullname' => 'Third', 'shortname' => 'sn-3', 'idnumber' => 'x-alpha',
        ]);
        $none = $generator->create_course(['category' => $root->id, 'fullname' => 'Beta', 'shortname' => 'sn-4']);
        $withheld = $generator->create_course(['category' => $root->id, 'fullname' => 'Alpha unlisted', 'shortname' => 'sn-5']);
        $this->set_course_state((int) $withheld->id, discoverability::STATE_UNLISTED);
        $this->assertNotEmpty($none);

        $this->become($generator->create_user());
        $result = population::resolve(new criteria($root->id, ['search' => '  alpha ']));
        $this->assertEqualsCanonicalizing([(int) $byname->id, (int) $byshort->id, (int) $byidnumber->id], $result->ids());
        $this->assertSame(3, $result->total);

        $this->assertSame(4, population::resolve(new criteria($root->id, ['search' => 'a']))->total, 'One character: no search.');
        $this->assertSame('', (new criteria($root->id, ['search' => " a\n"]))->search);
        $this->assertSame(0, population::resolve(new criteria($root->id, ['search' => '%%']))->total, 'A wildcard is escaped.');
    }

    /**
     * The request shape refuses what it cannot mean.
     */
    public function test_criteria_refuse_unknown_options_and_states(): void {
        $this->resetAfterTest();
        $criteria = new criteria(3, ['page' => -2, 'pagesize' => 0, 'fields' => ['7' => '2', 'x' => 1, '8' => 0]]);
        $this->assertSame(0, $criteria->page);
        $this->assertSame(1, $criteria->pagesize);
        $this->assertSame([7 => 2], $criteria->fields);
        $this->assertSame(96, population::page_size(500));
        $this->assertSame(48, population::page_size());
        set_config(population::SETTING_PAGESIZE, '24', 'theme_dimensions');
        $this->assertSame(24, population::page_size());
        set_config(population::SETTING_PAGESIZE, '37', 'theme_dimensions');
        $this->assertSame(48, population::page_size(), 'A size the setting does not offer falls back.');

        // A chip value comes from the query string: an unknown one is no chip, never a developer error.
        $this->assertNull((new criteria(3, ['state' => 'bogus']))->state);
        $this->assertSame(classifier::CHIP_FAVOURITES, (new criteria(3, ['state' => 'favourites']))->state);
        // The sort and the two date bounds are known keys, and an unknown sort value is dropped.
        $carried = new criteria(3, ['sort' => 'name', 'datefrom' => '2026-09-17', 'dateto' => '2026-09-18']);
        $this->assertSame('name', $carried->sort);
        $this->assertSame('2026-09-17', $carried->datefrom);
        $this->assertSame('', (new criteria(3, ['sort' => 'bogus']))->sort);

        $this->expectException(\coding_exception::class);
        new criteria(3, ['orderby' => 'name']);
    }

    /**
     * The root of the course index resolves for a person and is refused to a visitor by default.
     *
     * The top-level pseudo-category holds no course of its own and has no row,
     * no context and no discoverability state, so the two category gates have
     * nothing to ask about it: it is always discoverable to somebody who is
     * logged in. A visitor gets it only once the site opens it
     * ({@see publicaccess::root_enabled()}, off by default and unset here);
     * test_the_anonymous_root_is_refused_unless_the_site_opened_it covers the rest.
     */
    public function test_the_root_scope_is_admitted_for_a_person_and_refused_to_a_visitor(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $first = $generator->create_category(['name' => 'First']);
        $second = $generator->create_category(['name' => 'Second']);
        $deep = $generator->create_category(['parent' => $first->id]);
        $mine = $generator->create_course(['category' => $first->id]);
        $deepcourse = $generator->create_course(['category' => $deep->id]);
        $elsewhere = $generator->create_course(['category' => $second->id]);

        $this->become($generator->create_user());
        $scope = population::scope(new criteria(0));
        $this->assertSame(0, $scope->rootid);
        $this->assertContains((int) $first->id, $scope->children, 'The children of the root are the top-level categories.');
        $this->assertContains((int) $second->id, $scope->children);
        $this->assertNotContains((int) $deep->id, $scope->children, 'A grandchild is not a child.');
        $this->assertTrue($scope->is_open((int) $deep->id), 'The subtree is the whole tree.');

        /* The population spans every category but the root slices no page of rows: it is a
           directory (see test_the_root_honours_no_filter_and_builds_no_listing). The three
           courses sit in three places of the tree and all three are in the total, the one below
           a child included, which sits directly in no top-level category. The cards carry no
           number of their own. */
        $result = population::resolve(new criteria(0));
        $this->assertSame(3, $result->total, 'The root population spans every category.');
        $ids = array_column($result->directory, 'id');
        $this->assertContains((int) $first->id, $ids, 'A top-level category is a card.');
        $this->assertContains((int) $second->id, $ids);
        $this->assertNotContains((int) $deep->id, $ids, 'A grandchild is not a card.');
        $this->assertNotEmpty($mine);
        $this->assertNotEmpty($deepcourse);
        $this->assertNotEmpty($elsewhere);

        // A visitor is refused the root outright, with the listing's one refusal.
        $this->become(null);
        $this->expectException(unavailable_exception::class);
        population::resolve(new criteria(0, ['anonymous' => true]));
    }

    /**
     * The root's own number is post-filter, and the cards name only what the viewer may discover.
     *
     * The directory cards carry no number, so what can be wrong here is the
     * total the hero prints and the set of cards. Both are asked of a population
     * every gate has already run over: a course core hides, one the plugin
     * withholds and a whole unlisted child move the total by nothing, and the
     * withheld child is not a card at all. The control is the admin in the same
     * test, who sees every one of them; without it the assertions would pass
     * just as well over a viewer who may see nothing.
     *
     * Both modes, because the total is reached through different material: the
     * page cut in PHP counts the population it materialised, while the page cut
     * in SQL materialises none and counts over the same predicate the page would
     * have been cut with. The SQL one is what a site with state sorting off prints.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_root_total_counts_only_what_the_viewer_may_discover(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $open = $generator->create_category(['name' => 'Open branch']);
        $under = $generator->create_category(['parent' => $open->id]);
        $withheld = $generator->create_category(['name' => 'Withheld branch']);
        $this->set_category_state((int) $withheld->id, category_discoverability::STATE_UNLISTED);

        $generator->create_course(['category' => $open->id]);
        $generator->create_course(['category' => $under->id]);
        $generator->create_course(['category' => $open->id, 'visible' => 0]);
        $unlisted = $generator->create_course(['category' => $open->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $generator->create_course(['category' => $withheld->id]);

        $this->become($generator->create_user());
        $result = population::resolve(new criteria(0));
        $names = array_column($result->directory, 'name');
        $this->assertSame(2, $result->total, 'The branch and its child, hidden and unlisted excluded.');
        $this->assertSame(['total' => 2], $result->counts, 'The hero reads the same number from the histogram.');
        $this->assertContains('Open branch', $names);
        $this->assertNotContains('Withheld branch', $names, 'A withheld child is not a card.');
        $entry = $result->directory[array_search('Open branch', $names, true)];
        $this->assertSame((int) $open->id, $entry['id']);
        $this->assertStringContainsString('categoryid=' . $open->id, $entry['url']);
        $this->assertArrayNotHasKey('count', $entry, 'A directory card carries no number at all.');
        $this->assertArrayNotHasKey('countlabel', $entry);
        $this->assertStringStartsWith(
            '--dim-card-band: ' . branding::DEFAULT_BG . '; --dim-card-ink: ' . branding::DEFAULT_INK . ';',
            $entry['style'],
            'An unbranded category gets the default pair.'
        );

        // The control: the admin sees both branches and every course in them.
        $this->setAdminUser();
        self::forget();
        $asadmin = population::resolve(new criteria(0));
        $this->assertSame(5, $asadmin->total, 'Every course of both branches, hidden and unlisted included.');
        $this->assertContains('Withheld branch', array_column($asadmin->directory, 'name'));
    }

    /**
     * A branded child paints its own card: the band and the ink come from the child's pair, not a constant.
     *
     * Other assertions about this entry compare against the default, which a hardcoded constant
     * would produce as well; this test reads a colour a manager chose out of the card, with the
     * unbranded sibling as the control. The entry's style attribute is a CSS context, so a stored
     * or cached value that is not a colour must read as the default, for the band and the ink
     * alike. The light branch is the one whose ink differs from the default's, the only way to
     * see the ink being read at all.
     */
    public function test_a_branded_child_paints_the_directory_card_in_its_own_colour(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mode(false);
        $generator = self::getDataGenerator();
        $branded = $generator->create_category(['name' => 'Branded branch']);
        $light = $generator->create_category(['name' => 'Light branch']);
        $plain = $generator->create_category(['name' => 'Plain branch']);
        $poisoned = $generator->create_category(['name' => 'Poisoned branch']);
        $cached = $generator->create_category(['name' => 'Cached branch']);
        foreach ([$branded, $light, $plain, $poisoned, $cached] as $category) {
            $generator->create_course(['category' => $category->id]);
        }

        $this->setAdminUser();
        branding::save((int) $branded->id, (object) ['bgcolour' => '#12484F', 'inkcolour' => '#ffffff']);
        branding::save((int) $light->id, (object) ['bgcolour' => '#f5d76e', 'inkcolour' => '#1F1F1F']);
        // Fits the column, is not a colour: what a migration, a manual write or a restore can leave.
        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => (int) $poisoned->id,
            'layout' => branding::DEFAULT_LAYOUT,
            'bgcolour' => '#12345',
            'inkcolour' => 'white',
            'overlay' => branding::DEFAULT_OVERLAY,
            'tagline' => '',
            'publicdescriptionformat' => FORMAT_HTML,
        ]);
        /* The row above is cleaned by branding::record() on its way into the payload, so it never
           reaches this builder poisoned. The CACHED payload is the builder's own input, shared by
           every viewer: poisoned there, only the builder's own sanitiser stands between it and
           the attribute. */
        $payload = branding::for_category((int) $cached->id);
        $payload['bgcolour'] = '#fff;background:url(x)';
        $payload['inkcolour'] = 'red;background:url(x)';
        \core_cache\cache::make(branding::COMPONENT, branding::CACHE)->set((int) $cached->id, $payload);
        self::forget();

        $styles = array_column(population::resolve(new criteria(0))->directory, 'style', 'name');
        $this->assertStringStartsWith(
            '--dim-card-band: #12484f; --dim-card-ink: #ffffff;',
            $styles['Branded branch'] ?? '',
            'The card carries the colour the manager chose, in the one spelling.'
        );
        $this->assertStringStartsWith(
            '--dim-card-band: #f5d76e; --dim-card-ink: #1f1f1f;',
            $styles['Light branch'] ?? '',
            'And the ink the manager chose beside it, lower-cased by the sanitiser.'
        );
        $this->assertStringStartsWith(
            '--dim-card-band: ' . branding::DEFAULT_BG . '; --dim-card-ink: ' . branding::DEFAULT_INK . ';',
            $styles['Plain branch'] ?? '',
            'The control: an unbranded sibling keeps the default pair.'
        );
        $this->assertStringStartsWith(
            '--dim-card-band: ' . branding::DEFAULT_BG . '; --dim-card-ink: ' . branding::DEFAULT_INK . ';',
            $styles['Poisoned branch'] ?? '',
            'A stored value that is not a colour reads as the default rather than reaching the attribute.'
        );
        $this->assertStringStartsWith(
            '--dim-card-band: ' . branding::DEFAULT_BG . '; --dim-card-ink: ' . branding::DEFAULT_INK . ';',
            $styles['Cached branch'] ?? '',
            'And so does a cached one: the builder sanitises the band and the ink it prints.'
        );
        foreach ($styles as $name => $style) {
            $this->assertMatchesRegularExpression(
                "~^(--dim-card-(band|ink): #[0-9a-f]{6}; ?|--dim-mark-a: [01]\\.\\d{2}; ?|--dim-mark: url\\('[^'&]*'\\); ?)+$~",
                $style,
                $name . ': the attribute is a closed vocabulary.'
            );
        }
    }

    /**
     * A directory card's still follows the chain: the card cover, else the hero image, else nothing.
     *
     * Every link has a control in the same run, because each answer is also what another mistake
     * would produce. "Both" proves the cover wins, and "Hero only" beside it proves a hero image
     * is linkable at all; without it the first assertion would pass against a builder that had
     * stopped reading the hero area. "Cover only" proves the cover is reached without a hero to
     * fall back from, and "Neither" proves the chain ends rather than inventing an address. The
     * two files are different pictures, so an assertion cannot pass by reading the wrong area: a
     * hero and a cover holding the same bytes would share a content hash.
     */
    public function test_a_directory_cards_still_follows_the_cover_then_the_hero_image_then_nothing(): void {
        $this->resetAfterTest();
        $this->mode(false);
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $ids = [];
        foreach (['Both', 'Hero only', 'Cover only', 'Neither'] as $name) {
            $category = $generator->create_category(['name' => $name]);
            $generator->create_course(['category' => $category->id]);
            $ids[$name] = (int) $category->id;
        }
        foreach (['Both', 'Hero only'] as $name) {
            self::store_file($ids[$name], branding::AREA_HERO, 'hero.png', self::png());
        }
        foreach (['Both', 'Cover only'] as $name) {
            self::store_file($ids[$name], branding::AREA_COVER, 'capa.jpg', self::jpeg());
        }

        $entries = [];
        foreach (population::resolve(new criteria(0))->directory as $entry) {
            $entries[$entry['name']] = $entry;
        }

        $this->assertStringContainsString(
            '/categorycover/' . $ids['Both'] . '/',
            (string) $entries['Both']['imageurl'],
            'With both uploaded, the card shows the COVER.'
        );
        $this->assertStringContainsString(
            '/categoryhero/' . $ids['Hero only'] . '/',
            (string) $entries['Hero only']['imageurl'],
            'Control: without a cover the card falls back to the hero image, as it always did.'
        );
        $this->assertStringContainsString(
            '/categorycover/' . $ids['Cover only'] . '/',
            (string) $entries['Cover only']['imageurl'],
            'And a cover with no hero behind it is reached on its own.'
        );
        $this->assertNull($entries['Neither']['imageurl'], 'With neither, there is no address to invent.');
        foreach (['Both', 'Hero only', 'Cover only'] as $name) {
            $this->assertTrue($entries[$name]['hasimage'], $name . ' has a still and says so.');
        }
        $this->assertFalse($entries['Neither']['hasimage'], 'And the colour band alone says so too.');
    }

    /**
     * A card names a cover video only when the stored file's own bytes are one.
     *
     * The filmed category is the control for the faked one: an assertion that only read the fake
     * would pass just as well against a builder that never names a video at all, which is the
     * state a signature check reading the wrong offset produces.
     */
    public function test_a_directory_card_names_a_cover_video_only_when_its_bytes_are_one(): void {
        $this->resetAfterTest();
        $this->mode(false);
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $ids = [];
        foreach (['Filmed', 'In WebM', 'Faked', 'Silent'] as $name) {
            $category = $generator->create_category(['name' => $name]);
            $generator->create_course(['category' => $category->id]);
            $ids[$name] = (int) $category->id;
        }
        $area = branding::AREA_COVERVIDEO;
        $film = self::store_file($ids['Filmed'], $area, 'capa.mp4', self::fixture_bytes('cover.mp4'));
        self::store_file($ids['In WebM'], $area, 'capa.webm', self::fixture_bytes('cover.webm'));
        self::store_file($ids['Faked'], $area, 'capa.mp4', 'not a video at all, it is prose');

        $entries = [];
        foreach (population::resolve(new criteria(0))->directory as $entry) {
            $entries[$entry['name']] = $entry;
        }

        $this->assertTrue($entries['Filmed']['hasvideo']);
        $this->assertSame('video/mp4', $entries['Filmed']['videotype']);
        $this->assertStringContainsString(
            '/categorycovervideo/' . $ids['Filmed'] . '/' . $film->get_contenthash() . '/capa.mp4',
            (string) $entries['Filmed']['videourl'],
            'The address names its own area, the category and the current content.'
        );
        $this->assertSame('video/webm', $entries['In WebM']['videotype'], 'And the other type is carried too.');
        $this->assertFalse(
            $entries['Faked']['hasvideo'],
            'A file named .mp4 whose bytes are not one is never named on a card.'
        );
        $this->assertNull($entries['Faked']['videourl']);
        $this->assertNull($entries['Faked']['videotype']);
        $this->assertFalse($entries['Silent']['hasvideo'], 'And a category with no film at all says so.');
        $this->assertNull($entries['Silent']['videourl']);
    }

    /**
     * A 1x1 PNG.
     *
     * @return string The bytes.
     */
    private static function png(): string {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }

    /**
     * A JPEG with real dimensions, so that a cover and a hero never share a content hash.
     *
     * @return string The bytes.
     */
    private static function jpeg(): string {
        $image = imagecreatetruecolor(64, 32);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /**
     * The bytes of one of the real videos tests/fixtures ships.
     *
     * @param string $name The file name under tests/fixtures.
     * @return string The bytes.
     */
    private static function fixture_bytes(string $name): string {
        return (string) file_get_contents(__DIR__ . '/../../fixtures/' . $name);
    }

    /**
     * Store one file in a single-file area of a category, as the appearance editor would.
     *
     * @param int $categoryid The category.
     * @param string $area The area.
     * @param string $filename The file name.
     * @param string $content The bytes.
     * @return \stored_file
     */
    private static function store_file(int $categoryid, string $area, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => branding::COMPONENT,
            'filearea' => $area,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], $content);
    }

    /**
     * The root honours its search term and no other criterion, and builds no listing.
     *
     * The root is a directory of categories with a search line and no chips,
     * field filter, period or clear link, so any other criterion arriving on the
     * query string would change the hero's total with nothing on the page
     * showing it or offering to undo it. The root therefore resolves one request
     * carrying the term alone, in both modes; the term has its own test
     * (test_a_search_at_the_root_counts_the_same_in_both_modes).
     *
     * Nor is a listing built at the root: the classifier's enrolment map, the
     * facet map, the sort and the page slice would each run over every course
     * of the site on its busiest page, only to be discarded by the directory
     * export. So the result carries no rows, facets, tree or category names.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_root_honours_no_filter_and_builds_no_listing(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_MODEL)->get('id');

        $generator = self::getDataGenerator();
        $branch = $generator->create_category(['name' => 'Branch']);
        $handler = \core_course\customfield\course_handler::create();
        foreach (['Alpha course', 'Beta course'] as $name) {
            $course = $generator->create_course(['category' => $branch->id, 'fullname' => $name]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            ]);
        }

        $this->become($generator->create_user());
        $plain = population::resolve(new criteria(0));
        $this->assertSame(2, $plain->total, 'Both courses are in the population, so there is something to narrow.');
        $this->assertContains('Branch', array_column($plain->directory, 'name'));

        // A field filter, a chip and a page that would narrow a category page leave the directory alone.
        $filtered = population::resolve(new criteria(0, [
            'fields' => [$fieldid => landingfields::MODEL_COURSE],
            'state' => classifier::CHIP_FAVOURITES,
            'page' => 7,
        ]));
        $this->assertSame($plain->total, $filtered->total, 'The root ignores a filter it offers no control for.');
        $this->assertSame(
            $plain->directory,
            $filtered->directory,
            'Every card of the directory is the card it is without a query string.'
        );

        // The control: the same field filter does narrow the category below, in both modes.
        $narrowed = population::resolve(new criteria((int) $branch->id, ['fields' => [$fieldid => landingfields::MODEL_COURSE]]));
        $this->assertSame(0, $narrowed->total, 'The filter is a real one: it empties the category page.');

        // The term is the one criterion the root reads: it narrows, and still builds no listing.
        $searched = population::resolve(new criteria(0, ['search' => 'zzzznothing']));
        $this->assertSame(0, $searched->total, 'The root reads its term.');

        // And nothing of a listing is built at the root, in either mode.
        foreach ([$plain, $filtered, $searched] as $result) {
            $this->assertSame([], $result->rows, 'The root slices no page of courses.');
            $this->assertSame([], $result->facets, 'The root reads no facet map over the whole site.');
            $this->assertSame([], $result->subcategories, 'The root builds no selector over the whole tree.');
            $this->assertSame([], $result->categorynames, 'The root names no card\'s category.');
            $this->assertSame(['total' => $result->total], $result->counts, 'The hero gets the total and nothing else.');
            $this->assertNotEmpty($result->directory, 'What it does build is the directory.');
        }
    }

    /**
     * A search at the root counts the same matches in both page-cutting modes, past the probe cap too.
     *
     * One area holds fifty-five unrelated unlisted courses a stranger may enrol in, and the five
     * created first - the last five in core order, past the fiftieth - are the only ones the term
     * matches. The PHP cut probes only the matches. The SQL cut's exception list must be narrowed
     * by the term as well: built over the whole subtree, it probes the fifty "Alpha" courses and
     * answers 0. Every number below is asserted in both modes, so a mode that dropped the term
     * from its tally or from its probe reads a different number here.
     *
     * The controls: a listed match in another area (the ordinary case), an area the term misses (a
     * poster counting 0, which is what sends it to the summary), and the root at rest, where no
     * poster carries a number at all.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_search_at_the_root_counts_the_same_in_both_modes(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $unlisted = $generator->create_category(['name' => 'Unlisted area']);
        $listed = $generator->create_category(['name' => 'Listed area']);
        $missed = $generator->create_category(['name' => 'Missed area']);
        $probed = [];
        for ($i = 0; $i < population::PROBE_CAP + 5; $i++) {
            // Created first, listed last in core order: the five Zeta courses sit past the cap.
            $course = $generator->create_course([
                'category' => $unlisted->id,
                'fullname' => ($i < 5 ? 'Zeta ' : 'Alpha ') . $i,
            ]);
            $this->enable_instance((int) $course->id, 'self', ['customint6' => 1]);
            $probed[] = (int) $course->id;
        }
        $generator->create_course(['category' => $listed->id, 'fullname' => 'Zeta listed']);
        $generator->create_course(['category' => $listed->id, 'fullname' => 'Other listed']);
        $generator->create_course(['category' => $missed->id, 'fullname' => 'Nothing here']);
        $this->setAdminUser();
        foreach ($probed as $courseid) {
            discoverability::set_state($courseid, discoverability::STATE_UNLISTED);
        }

        $this->become($generator->create_user());
        $counts = static function (page_result $result): array {
            return array_column($result->accordion, 'count', 'id');
        };

        $search = population::resolve(new criteria(0, ['search' => 'Zeta']));
        $this->assertSame(6, $search->total, 'Five unlisted matches past the cap and one listed one, in this mode.');
        $bypostercount = $counts($search);
        $this->assertSame(
            5,
            $bypostercount[(int) $unlisted->id],
            'Every unlisted match is probed, though they are the last five in core order.'
        );
        $this->assertSame(1, $bypostercount[(int) $listed->id], 'The listed match counts in its own area.');
        $this->assertSame(0, $bypostercount[(int) $missed->id], 'An area the term misses is a poster counting 0.');
        $directory = array_column($search->directory, 'count', 'id');
        $this->assertSame($bypostercount, $directory, 'Each poster wears its own count.');
        $this->assertSame(
            array_column($search->directory, 'id'),
            array_column($search->accordion, 'id'),
            'The accordion names every poster of the directory, in its order.'
        );

        // The control: at rest the root counts nothing, and the probe spends its fifty on the Alphas.
        $rest = population::resolve(new criteria(0));
        $this->assertSame([], $rest->accordion, 'At rest no poster carries a number.');
        $this->assertArrayNotHasKey('count', $rest->directory[0], 'Nor does any directory entry.');
        $this->assertSame(population::PROBE_CAP + 3, $rest->total, 'The fifty probed unlisted courses and the three listed ones.');
    }

    /**
     * With the root public a visitor gets the public top-level categories and nothing else.
     *
     * The population's admitted set and the directory's cards are two different sets, and only
     * one of them is right for the internet. admit_for_visitor() admits everything a visitor may
     * be told exists - a listed category as well as a public one - which is correct for the
     * population, whose course rows the statement has already restricted to public courses, and
     * wrong for a card: a listed category has no page a visitor may open, so its card would be a
     * link to the login form.
     *
     * So the listed sibling is the control: an assertion naming only the public category would
     * pass just as well against a directory that draws everything, and against one that draws
     * nothing. The admin beside it is the second control - the same categories, every card - so
     * "the visitor sees one" cannot pass because the site holds one.
     *
     * The card's address is asserted too: a link spelled with the logged-in shell would point a
     * visitor at /course/index.php and bounce through the forward.
     *
     * @return void
     */
    public function test_the_anonymous_root_draws_the_public_top_level_categories_alone(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        set_config(publicaccess::SETTING_ROOT, '1', 'theme_dimensions');

        $public = $generator->create_category(['name' => 'Public branch']);
        $listed = $generator->create_category(['name' => 'Listed branch']);
        $unlisted = $generator->create_category(['name' => 'Unlisted branch']);
        $this->set_category_state((int) $public->id, category_discoverability::STATE_PUBLIC);
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $course = $generator->create_course(['category' => $public->id]);
        $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        $generator->create_course(['category' => $listed->id]);

        $this->become(null);
        $result = population::resolve(new criteria(0, ['anonymous' => true]));
        $names = array_column($result->directory, 'name');

        $this->assertTrue(
            $result->anonymous,
            'The root carries which shell drew it: the hero\'s own title and trail are decided by it.'
        );
        $this->assertContains('Public branch', $names, 'A public top-level category is a card.');
        $this->assertNotContains('Listed branch', $names, 'A merely listed one is not, though the scope admits it.');
        $this->assertNotContains('Unlisted branch', $names);
        $this->assertNotContains(
            'Miscellaneous',
            $names,
            'Nor is the default category, which is listed like any other unmarked one.'
        );

        // The precondition the assertion above rests on: the scope really did admit the listed one.
        $scope = population::scope(new criteria(0, ['anonymous' => true]));
        $this->assertTrue(
            $scope->is_open((int) $listed->id),
            'Precondition: the listed sibling is in the population, so the card is withheld by directory().'
        );

        $entry = $result->directory[array_search('Public branch', $names, true)];
        $this->assertSame(
            links::showcase((int) $public->id, [], true)->out(false),
            $entry['url'],
            'The card links to the ANONYMOUS shell, which is the only one a visitor may open.'
        );
        $this->assertStringNotContainsString('/course/index.php', $entry['url']);

        // The control: an administrator on the same site gets all four cards, on the other shell.
        $this->setAdminUser();
        self::forget();
        $asadmin = population::resolve(new criteria(0));
        $adminnames = array_column($asadmin->directory, 'name');
        $this->assertContains('Public branch', $adminnames);
        $this->assertContains('Listed branch', $adminnames, 'Control: the listed branch exists and is admissible.');
        $this->assertContains('Unlisted branch', $adminnames);
        $adminentry = $asadmin->directory[array_search('Public branch', $adminnames, true)];
        $this->assertStringContainsString('/course/index.php?categoryid=' . $public->id, $adminentry['url']);
    }

    /**
     * An anonymous scope asks the whole public chain, not the plugin's predicate alone.
     *
     * Every entry asks publicaccess::is_public() before it resolves anything, and this layer asks it
     * again, so a caller that forgot to cannot serve a public category while the surface or the
     * whole showcase is switched off. The plugin still holds the category public under both
     * switches, which is the point: its predicate alone would let the page through. The category
     * resolving with both switches on is the control.
     *
     * @return void
     */
    public function test_an_anonymous_scope_is_refused_whenever_the_public_chain_is(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = $generator->create_category(['name' => 'Public branch']);
        $this->set_category_state((int) $public->id, category_discoverability::STATE_PUBLIC);
        $course = $generator->create_course(['category' => $public->id]);
        $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        $this->become(null);

        $criteria = static fn(): criteria => new criteria((int) $public->id, ['anonymous' => true]);
        $this->assertSame([(int) $course->id], population::resolve($criteria())->ids(), 'Control: the category is served.');

        foreach ([publicaccess::SETTING_ENABLED, population::SETTING_ENABLED] as $setting) {
            set_config($setting, '0', 'theme_dimensions');
            self::forget();
            $this->assertTrue(
                category_discoverability::is_public((int) $public->id),
                $setting . ': precondition, the plugin still holds the category public.'
            );
            try {
                population::resolve($criteria());
                $this->fail('The category was resolved for a visitor with ' . $setting . ' off.');
            } catch (unavailable_exception $e) {
                $this->assertInstanceOf(unavailable_exception::class, $e);
            }
            set_config($setting, '1', 'theme_dimensions');
        }
        self::forget();
        $this->assertSame([(int) $course->id], population::resolve($criteria())->ids(), 'Control: back on, served again.');
    }

    /**
     * The root's own setting is what opens it to a visitor, and the guest is never let in.
     *
     * Three refusals and one page, all through the one exception this layer raises for
     * everything: the setting off (the default), the surface off, and the guest account - which
     * core counts as logged in, which the anonymous shell never draws for, and which would
     * otherwise walk through the root's public chain since it holds no capability the chain reads.
     *
     * @return void
     */
    public function test_the_anonymous_root_is_refused_unless_the_site_opened_it(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = $generator->create_category(['name' => 'Public branch']);
        $this->set_category_state((int) $public->id, category_discoverability::STATE_PUBLIC);
        $generator->create_course(['category' => $public->id]);

        // Off by default: a site that never saved the setting keeps its root off the internet.
        $this->become(null);
        try {
            population::resolve(new criteria(0, ['anonymous' => true]));
            $this->fail('The root was served with the setting unset.');
        } catch (unavailable_exception $e) {
            $this->assertInstanceOf(unavailable_exception::class, $e);
        }

        // Control: with the setting on, the very same request answers with a directory.
        set_config(publicaccess::SETTING_ROOT, '1', 'theme_dimensions');
        self::forget();
        $this->assertNotEmpty(
            population::resolve(new criteria(0, ['anonymous' => true]))->directory,
            'Control: the fixture does hold a card, so the refusals above are not vacuous.'
        );

        // The surface's own switch closes the root with every other public page.
        set_config(publicaccess::SETTING_ENABLED, '0', 'theme_dimensions');
        self::forget();
        try {
            population::resolve(new criteria(0, ['anonymous' => true]));
            $this->fail('The root was served with the anonymous surface switched off.');
        } catch (unavailable_exception $e) {
            $this->assertInstanceOf(unavailable_exception::class, $e);
        }
        set_config(publicaccess::SETTING_ENABLED, '1', 'theme_dimensions');

        // And the guest account is not a visitor, whatever the settings say.
        $this->setGuestUser();
        self::forget();
        $this->expectException(unavailable_exception::class);
        population::resolve(new criteria(0, ['anonymous' => true]));
    }

    /**
     * Past twenty-four posters the rest are cut off into the reveal, and at twenty-four there is none.
     *
     * The cut is a page cut over entries the same pass already built, so it is asserted where
     * it is made: the boundary on synthetic entries, which is the only way to state 24 and 25
     * without seeding fifty categories, and then once over a real directory of twenty-five,
     * which proves the entries reaching it are the directory's own and in its order.
     * Twenty-four fills whole rows at three, two and one column, where twenty-five would leave
     * one poster alone on the last row of every grid.
     *
     * @return void
     */
    public function test_the_directory_is_cut_at_twenty_four_and_not_before(): void {
        $this->resetAfterTest();
        $this->assertSame(24, population::DIRECTORY_SLICE, 'The directory is cut at twenty-four posters.');

        $entries = [];
        for ($i = 1; $i <= 25; $i++) {
            $entries[] = ['id' => $i, 'name' => 'Category ' . $i];
        }

        $exact = population::directory_slice(array_slice($entries, 0, population::DIRECTORY_SLICE));
        $this->assertCount(population::DIRECTORY_SLICE, $exact['directory']);
        $this->assertSame([], $exact['directorymore'], 'Twenty-four posters are all open.');
        $this->assertFalse($exact['hasmore'], 'And there is no reveal at all.');
        $this->assertSame(0, $exact['morecount']);

        $over = population::directory_slice($entries);
        $this->assertCount(population::DIRECTORY_SLICE, $over['directory'], 'The first twenty-four stay open.');
        $this->assertCount(1, $over['directorymore'], 'The twenty-fifth is behind the reveal.');
        $this->assertTrue($over['hasmore']);
        $this->assertSame(1, $over['morecount']);
        $this->assertSame(25, $over['directorymore'][0]['id'], 'And it is the LAST one, in the directory\'s order.');
        $this->assertSame(1, $over['directory'][0]['id'], 'The order is otherwise untouched.');

        /* The real directory, cut by the same helper the export calls. The site always carries
           its own Miscellaneous, so the seeding counts the rows rather than assuming an empty
           tree - and it reads {course_categories} rather than top()'s children, whose cache is
           not warm at this point in the test. */
        global $DB;
        $generator = self::getDataGenerator();
        $wanted = population::DIRECTORY_SLICE + 1;
        $existing = $DB->count_records('course_categories', ['parent' => 0]);
        for ($i = $existing; $i < $wanted; $i++) {
            $generator->create_category(['name' => sprintf('Branch %02d', $i)]);
        }
        $this->become($generator->create_user());
        $directory = population::resolve(new criteria(0))->directory;
        $this->assertCount($wanted, $directory, 'Precondition: every top-level category is still an entry.');
        $slice = population::directory_slice($directory);
        $this->assertCount(population::DIRECTORY_SLICE, $slice['directory']);
        $this->assertCount(1, $slice['directorymore']);
        $this->assertSame(
            $directory[population::DIRECTORY_SLICE]['id'],
            $slice['directorymore'][0]['id'],
            'The reveal holds the entries the open grid did not, and no others.'
        );
    }

    /**
     * The decision layer itself refuses the root to a visitor and to the guest, not only the renderer.
     *
     * Without the anonymous flag the root is the logged-in shell's, which is a
     * real account's alone. The renderer falls back to core's own page for
     * those two viewer classes, but the refusal belongs to this class, which
     * owns every other decision of the showcase and raises the listing's one
     * exception, so any other caller (a web service, a block, a report) asking
     * for category 0 in such a session is refused rather than handed a
     * directory. The anonymous shell's root has tests of its own.
     */
    public function test_the_root_is_refused_to_a_visitor_and_to_the_guest_without_the_anonymous_flag(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $generator->create_course(['category' => $generator->create_category()->id]);

        // The control first: a real account is admitted and gets a directory.
        $this->become($generator->create_user());
        $this->assertNotEmpty(population::resolve(new criteria(0))->directory);

        $this->setGuestUser();
        self::forget();
        try {
            population::resolve(new criteria(0));
            $this->fail('The guest account was handed the root.');
        } catch (unavailable_exception $e) {
            $this->assertInstanceOf(unavailable_exception::class, $e);
        }

        $this->become(null);
        self::forget();
        $this->expectException(unavailable_exception::class);
        population::resolve(new criteria(0));
    }

    /**
     * The posters are the accordion's set in its order, and only an empty one at rest is a link.
     *
     * The empty child is the case that decides: at rest it has nothing to open, so it is a link
     * to its page; under a criterion it opens, because "nothing here matches" is an answer the
     * panel gives. Its populated siblings are the control in both states. The request
     * that does not ask for posters - the web service's - gets none, which is what keeps a filter
     * fetch from paying for them.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_posters_are_the_accordions_set_and_only_an_empty_one_at_rest_is_a_link(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $first = $generator->create_category(['parent' => $root->id, 'name' => 'First', 'sortorder' => 10]);
        $empty = $generator->create_category(['parent' => $root->id, 'name' => 'Empty', 'sortorder' => 20]);
        $second = $generator->create_category(['parent' => $root->id, 'name' => 'Second', 'sortorder' => 30]);
        $generator->create_course(['category' => $first->id, 'fullname' => 'Zeta rescue']);
        $generator->create_course(['category' => $first->id, 'fullname' => 'Alpha rescue']);
        $generator->create_course(['category' => $second->id, 'fullname' => 'Beta patrol']);
        $this->setAdminUser();
        $crest = self::store_file((int) $first->id, branding::AREA_BADGES[0], 'crest.png', self::png());

        $this->become($generator->create_user());
        $result = population::resolve(new criteria((int) $root->id, ['withposters' => true]));
        $this->assertSame(
            array_column($result->accordion, 'id'),
            array_column($result->posters, 'id'),
            'The posters are the accordion\'s entries, in its order.'
        );
        $posters = array_column($result->posters, null, 'name');
        $this->assertSame(['First', 'Empty', 'Second'], array_keys($posters));
        $this->assertSame([2, 0, 1], array_column($result->posters, 'count'));
        $this->assertTrue($posters['First']['expandable']);
        $this->assertTrue($posters['Second']['expandable'], 'The control: a populated poster opens at rest.');
        $this->assertFalse($posters['Empty']['expandable'], 'An empty poster at rest is a link to its page.');
        foreach ($posters as $name => $poster) {
            $this->assertFalse($poster['istext'], $name . ': a logged-in viewer draws no text poster.');
            $this->assertTrue($poster['linkable']);
            $this->assertSame(links::showcase((int) $poster['id'], [], false)->out(false), $poster['url']);
        }
        // The watermark: the first crest where there is one, the folder where there is none.
        $crestentry = branding::badges_for_output((int) $first->id, branding::for_category((int) $first->id)['badges'])[0];
        $this->assertStringContainsString('/' . $crest->get_contenthash() . '/', $crestentry['url'], 'Precondition.');
        $this->assertTrue($posters['First']['hasmark']);
        $this->assertFalse($posters['First']['markfolder']);
        $this->assertStringEndsWith(" --dim-mark: url('" . $crestentry['url'] . "');", $posters['First']['style']);
        $this->assertTrue($posters['Second']['hasmark']);
        $this->assertTrue($posters['Second']['markfolder'], 'No crest: the stylesheet draws the folder.');
        $this->assertStringNotContainsString('--dim-mark:', $posters['Second']['style']);

        self::forget();
        $filtered = population::resolve(new criteria((int) $root->id, ['withposters' => true, 'search' => 'rescue']));
        $posters = array_column($filtered->posters, null, 'name');
        $this->assertSame(['First', 'Empty', 'Second'], array_keys($posters), 'Every poster stays under a criterion.');
        $this->assertSame(0, $posters['Second']['count'], 'Precondition: the search misses the second area.');
        $this->assertTrue($posters['Second']['expandable'], 'Under a criterion a poster with no match still opens.');
        $this->assertTrue($posters['Empty']['expandable']);

        self::forget();
        $this->assertSame([], population::resolve(new criteria((int) $root->id))->posters, 'Not asked for, not built.');
    }

    /**
     * A visitor's merely listed child is a text poster: no still, no film, no crest and no watermark.
     *
     * The public route refuses the files of a category that is not public, so a poster naming
     * them would draw broken pictures on the internet. The public sibling, with the same files,
     * is the control: without it the assertions would pass against a builder that drew no files
     * for anybody.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_visitors_merely_listed_child_is_a_text_poster(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $this->set_category_state((int) $root->id, category_discoverability::STATE_PUBLIC);
        $listed = $generator->create_category(['parent' => $root->id, 'name' => 'Listed', 'sortorder' => 10]);
        $public = $generator->create_category(['parent' => $root->id, 'name' => 'Public', 'sortorder' => 20]);
        $this->set_category_state((int) $public->id, category_discoverability::STATE_PUBLIC);
        foreach ([$listed, $public] as $child) {
            $course = $generator->create_course(['category' => $child->id]);
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $this->setAdminUser();
        foreach ([$listed, $public] as $child) {
            self::store_file((int) $child->id, branding::AREA_COVER, 'capa.jpg', self::jpeg());
            self::store_file((int) $child->id, branding::AREA_BADGES[0], 'crest.png', self::png());
        }

        $this->become(null);
        $result = population::resolve(new criteria((int) $root->id, ['anonymous' => true, 'withposters' => true]));
        $posters = array_column($result->posters, null, 'name');
        $this->assertSame(['Listed', 'Public'], array_keys($posters), 'Both children hold a public course.');

        $this->assertTrue($posters['Listed']['istext'], 'The listed child is drawn as text.');
        $this->assertFalse($posters['Listed']['linkable']);
        $this->assertSame('', $posters['Listed']['url'], 'It has no page a visitor may open.');
        $this->assertNull($posters['Listed']['imageurl']);
        $this->assertFalse($posters['Listed']['hasimage']);
        $this->assertFalse($posters['Listed']['hasvideo']);
        $this->assertSame([], $posters['Listed']['badges']);
        $this->assertFalse($posters['Listed']['hasmark'], 'Nor a watermark cut from a crest it may not show.');
        $this->assertTrue($posters['Listed']['expandable'], 'It still opens: its panel is a slice of this page.');

        $this->assertFalse($posters['Public']['istext'], 'The control: the public child draws its files.');
        $this->assertTrue($posters['Public']['hasimage']);
        $this->assertCount(1, $posters['Public']['badges']);
        $this->assertSame(links::showcase((int) $public->id, [], true)->out(false), $posters['Public']['url']);
    }

    /**
     * An open is admitted only when it names an expandable poster of this very page; anything else is silence.
     *
     * The valid open is the control, in the same fixture and the same run, for every refusal:
     * a grandchild, a withheld child, a category of another tree, an id that does not exist, a
     * flat organisation and an empty poster at rest all answer exactly what the address without
     * the parameter answers, and set it back to 0 on the request object, so the pager, the forms
     * and every address the page mints afterwards describe the page that was really drawn. The
     * empty poster opens under a criterion. At the root, every top-level poster opens and a
     * deeper id is refused.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_an_open_is_admitted_only_for_an_expandable_poster_of_this_page(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $child = $generator->create_category(['parent' => $root->id, 'name' => 'Child']);
        $grandchild = $generator->create_category(['parent' => $child->id, 'name' => 'Grandchild']);
        $empty = $generator->create_category(['parent' => $root->id, 'name' => 'Empty']);
        $withheld = $generator->create_category(['parent' => $root->id, 'name' => 'Withheld', 'visible' => 0]);
        $foreign = $generator->create_category(['name' => 'Foreign']);
        $generator->create_course(['category' => $child->id, 'fullname' => 'Alpha']);
        $generator->create_course(['category' => $grandchild->id, 'fullname' => 'Beta']);
        $generator->create_course(['category' => $withheld->id, 'fullname' => 'Gamma', 'visible' => 1]);
        $generator->create_course(['category' => $foreign->id, 'fullname' => 'Delta']);
        $generator->create_course(['category' => $root->id, 'fullname' => 'Epsilon']);

        $this->become($generator->create_user());
        $plain = population::resolve(new criteria((int) $root->id, ['withposters' => true]));
        $this->assertNull($plain->panel);

        self::forget();
        $valid = new criteria((int) $root->id, ['withposters' => true, 'open' => (int) $child->id, 'psort' => 'name']);
        $result = population::resolve($valid);
        $this->assertNotNull($result->panel, 'The control: a child of this page opens.');
        $this->assertSame((int) $child->id, (int) $result->panelentry['id']);
        $this->assertSame((int) $child->id, $valid->open);
        $this->assertSame('name', $valid->psort);

        $refused = [
            'a grandchild' => (int) $grandchild->id,
            'a withheld child' => (int) $withheld->id,
            'a category of another tree' => (int) $foreign->id,
            'an id that does not exist' => 999999,
            'an empty poster at rest' => (int) $empty->id,
        ];
        foreach ($refused as $what => $id) {
            self::forget();
            $request = new criteria((int) $root->id, ['withposters' => true, 'open' => $id, 'psort' => 'name']);
            $answer = population::resolve($request);
            $this->assertNull($answer->panel, $what . ' opens nothing.');
            $this->assertNull($answer->panelentry);
            $this->assertSame(0, $request->open, $what . ': the request no longer names a panel.');
            $this->assertSame('', $request->psort);
            $this->assertSame($plain->ids(), $answer->ids(), $what . ': the page is the page without it.');
            $this->assertSame(array_column($plain->posters, 'id'), array_column($answer->posters, 'id'));
        }

        self::forget();
        $flat = new criteria((int) $root->id, ['withposters' => true, 'sort' => criteria::SORT_NAME, 'open' => (int) $child->id]);
        $this->assertNull(population::resolve($flat)->panel, 'A flat organisation draws no poster to open.');
        $this->assertSame(0, $flat->open);

        self::forget();
        $filtered = new criteria((int) $root->id, ['withposters' => true, 'search' => 'Alpha', 'open' => (int) $empty->id]);
        $answer = population::resolve($filtered);
        $this->assertNotNull($answer->panel, 'Under a criterion the empty poster opens.');
        $this->assertSame(0, $answer->panel->total, 'And says nothing matches.');

        // The root: every top-level poster opens, a deeper id does not.
        self::forget();
        $atroot = population::resolve(new criteria(0, ['withposters' => true, 'open' => (int) $root->id]));
        $this->assertNotNull($atroot->panel, 'A top-level poster of the root opens.');
        $this->assertSame((int) $root->id, (int) $atroot->panelentry['id']);
        self::forget();
        $deep = new criteria(0, ['withposters' => true, 'open' => (int) $child->id]);
        $this->assertNull(population::resolve($deep)->panel, 'A category below the root\'s posters does not.');
        $this->assertSame(0, $deep->open);
    }

    /**
     * The panel lists the area's subtree, cut at eight, in the panel's own order.
     *
     * Twelve courses, six of them in a grandchild, with start dates that put the grandchild's
     * newest first: the newest-first panel therefore holds grandchild courses, which a panel of
     * the direct courses alone could not. The A-Z panel of the same area is the order's own check.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_the_panel_lists_the_subtree_cut_at_eight_in_its_own_order(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $child = $generator->create_category(['parent' => $root->id, 'name' => 'Child']);
        $grandchild = $generator->create_category(['parent' => $child->id, 'name' => 'Grandchild']);
        $base = make_timestamp(2026, 1, 5, 12, 0, 0);
        $bystart = [];
        $byname = [];
        for ($i = 1; $i <= 12; $i++) {
            $deep = $i > 6;
            $course = $generator->create_course([
                'category' => $deep ? $grandchild->id : $child->id,
                'fullname' => sprintf('Course %02d', 13 - $i),
                'startdate' => $base + $i * DAYSECS,
            ]);
            $bystart[$i] = (int) $course->id;
            $byname[sprintf('Course %02d', 13 - $i)] = (int) $course->id;
        }

        $this->become($generator->create_user());
        $recent = population::resolve(new criteria((int) $root->id, ['withposters' => true, 'open' => (int) $child->id]));
        $this->assertSame(population::PANEL_SIZE, count($recent->panel->rows), 'Eight on the rail.');
        $this->assertSame(12, $recent->panel->total, 'Of the whole subtree.');
        $this->assertSame(array_reverse(array_slice($bystart, 4, 8)), $recent->panel->ids(), 'The newest first.');
        $this->assertContains($bystart[12], $recent->panel->ids(), 'A grandchild\'s course is in the panel.');
        $this->assertSame([], $recent->panel->posters, 'A panel draws no posters of its own.');

        self::forget();
        $atoz = population::resolve(new criteria((int) $root->id, [
            'withposters' => true,
            'open' => (int) $child->id,
            'psort' => criteria::SORT_NAME,
        ]));
        ksort($byname);
        $this->assertSame(array_slice(array_values($byname), 0, 8), $atoz->panel->ids(), 'A-Z, cut at eight.');
    }

    /**
     * A visitor's merely listed child opens a slice of the parent's public listing, and nothing else.
     *
     * The listed course inside the listed child is the control: it is on the logged-in panel of
     * the same child, so the anonymous panel leaving it out is the public predicate at work and
     * not a fixture that never held it. The public course in the grandchild proves the slice is
     * the child's subtree, not its direct courses.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_visitors_listed_child_opens_a_slice_of_public_courses_only(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $this->set_category_state((int) $root->id, category_discoverability::STATE_PUBLIC);
        $listed = $generator->create_category(['parent' => $root->id, 'name' => 'Listed']);
        $below = $generator->create_category(['parent' => $listed->id, 'name' => 'Below']);
        $public = $generator->create_course(['category' => $listed->id, 'fullname' => 'Public here']);
        $deep = $generator->create_course(['category' => $below->id, 'fullname' => 'Public below']);
        $notpublic = $generator->create_course(['category' => $listed->id, 'fullname' => 'Listed here']);
        foreach ([$public, $deep] as $course) {
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }

        $this->become(null);
        $request = new criteria((int) $root->id, ['anonymous' => true, 'withposters' => true, 'open' => (int) $listed->id]);
        $result = population::resolve($request);
        $this->assertNotNull($result->panel, 'The text poster opens.');
        $this->assertTrue($result->panelentry['istext']);
        $this->assertEqualsCanonicalizing([(int) $public->id, (int) $deep->id], $result->panel->ids());
        $this->assertNotContains((int) $notpublic->id, $result->panel->ids(), 'A course that is not public never.');
        $this->assertSame([], $result->panel->subareas, 'A slice of the parent has no strip.');

        // The control: a logged-in reader's panel of the same child lists the listed course.
        $this->become($generator->create_user());
        $mine = population::resolve(new criteria((int) $root->id, ['withposters' => true, 'open' => (int) $listed->id]));
        $this->assertContains((int) $notpublic->id, $mine->panel->ids());
    }

    /**
     * A panel's request: the child's own flat listing, or the parent's public one sliced by the child.
     */
    public function test_a_panels_request_is_the_childs_own_or_a_slice_of_the_parents(): void {
        $this->resetAfterTest();
        $page = new criteria(7, [
            'search' => 'resgate',
            'state' => classifier::STATE_OPEN,
            'fields' => [5 => 2],
            'datefrom' => '2026-09-17',
            'dateto' => '2026-09-19',
            'pagesize' => 24,
            'page' => 3,
            'open' => 9,
            'psort' => criteria::SORT_NAME,
            'withposters' => true,
            'anonymous' => false,
        ]);
        $normal = population::panel_criteria($page, 9, false);
        $this->assertSame(9, $normal->categoryid, 'The child\'s own gate chain.');
        $this->assertSame(criteria::SORT_NAME, $normal->sort, 'Flat, in the panel\'s order.');
        $this->assertSame(population::PANEL_SIZE, $normal->pagesize);
        $this->assertSame(0, $normal->page);
        $this->assertTrue($normal->withcategories, 'With its strip.');
        $this->assertFalse($normal->withposters);
        $this->assertSame(0, $normal->open);
        $this->assertSame(0, $normal->cat);
        $this->assertSame($page->narrowing_params(), $normal->narrowing_params(), 'Everything that narrows, nothing else.');

        $recent = population::panel_criteria(new criteria(7, ['open' => 9]), 9, false);
        $this->assertSame(criteria::SORT_RECENT, $recent->sort, 'The default order is the newest first.');

        $slice = population::panel_criteria($page, 9, true);
        $this->assertSame(7, $slice->categoryid, 'The parent\'s listing.');
        $this->assertSame(9, $slice->cat, 'Narrowed to the child.');
        $this->assertTrue($slice->anonymous, 'The public one, whatever the page is.');
        $this->assertFalse($slice->withcategories, 'No strip: it is not the child\'s own scope.');
        $this->assertSame(population::PANEL_SIZE, $slice->pagesize);
        $this->assertSame(criteria::SORT_NAME, $slice->sort);
    }

    /**
     * The strip names a visitor's listed subarea without a link, and paints each dot through the sanitiser.
     */
    public function test_the_strip_links_a_public_subarea_alone_and_sanitises_its_dot(): void {
        global $DB;
        $this->resetAfterTest();
        $this->mode(true);
        $generator = self::getDataGenerator();
        $area = $generator->create_category(['name' => 'Area']);
        $this->set_category_state((int) $area->id, category_discoverability::STATE_PUBLIC);
        $open = $generator->create_category(['parent' => $area->id, 'name' => 'Open sub', 'sortorder' => 10]);
        $this->set_category_state((int) $open->id, category_discoverability::STATE_PUBLIC);
        $listed = $generator->create_category(['parent' => $area->id, 'name' => 'Listed sub', 'sortorder' => 20]);
        foreach ([$open, $listed] as $sub) {
            $course = $generator->create_course(['category' => $sub->id]);
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $this->setAdminUser();
        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => (int) $open->id,
            'layout' => branding::DEFAULT_LAYOUT,
            'bgcolour' => '#12345',
            'inkcolour' => '#ffffff',
            'overlay' => branding::DEFAULT_OVERLAY,
            'tagline' => '',
            'publicdescriptionformat' => FORMAT_HTML,
        ]);
        branding::save((int) $listed->id, (object) ['bgcolour' => '#1E5E34', 'inkcolour' => '#ffffff']);

        $this->become(null);
        $strip = population::resolve(new criteria((int) $area->id, [
            'anonymous' => true,
            'sort' => criteria::SORT_RECENT,
            'withcategories' => true,
        ]))->subareas;
        $this->assertSame(
            [
                [
                    'id' => (int) $open->id,
                    'name' => 'Open sub',
                    'depth' => 1,
                    'linkable' => true,
                    'url' => links::showcase((int) $open->id, [], true)->out(false),
                    'style' => '--dim-dot: ' . branding::DEFAULT_BG . ';',
                ],
                [
                    'id' => (int) $listed->id,
                    'name' => 'Listed sub',
                    'depth' => 1,
                    'linkable' => false,
                    'url' => '',
                    'style' => '--dim-dot: #1e5e34;',
                ],
            ],
            $strip,
            'A public subarea is a link on the public shell; a listed one is named only; a stored non-colour is the default.'
        );
    }

    /**
     * Whether a poster opens is one rule: something to open, or a criterion under which "none here" is an answer.
     *
     * The page's posters and the web service's accordion both ask population::expandable(), so a
     * filter applied in place turns a poster into the control the server would have drawn.
     */
    public function test_a_poster_opens_with_a_course_or_under_a_criterion_and_is_a_link_otherwise(): void {
        $this->assertFalse(population::expandable(false, 0), 'An empty poster at rest is a link to its page.');
        $this->assertTrue(population::expandable(false, 3), 'A populated one opens at rest.');
        $this->assertTrue(population::expandable(true, 0), 'Under a criterion a poster with no match still opens.');
        $this->assertTrue(population::expandable(true, 2));
    }

    /**
     * A visitor's posters under a criterion are the posters at rest: the one the criterion misses counts 0.
     *
     * A visitor's tree names a child only when a public course of the population sits below it,
     * and under a criterion that population is narrowed - so without the page's one grouped
     * statement over the anonymous predicate at rest, a child the search misses would vanish
     * instead of collapsing into the summary. The fixture holds all four cases: a public child
     * the search matches, a public child it misses (a zero poster), a merely listed child with a
     * public course it misses (a zero text poster), and a public child with no public course at
     * all, which is no poster at rest either. The same page at rest is the control.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_a_visitors_posters_under_a_criterion_are_the_posters_at_rest(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root']);
        $this->set_category_state((int) $root->id, category_discoverability::STATE_PUBLIC);
        $children = [];
        foreach (['Match' => true, 'Miss' => true, 'Listed' => false, 'Closed' => true] as $name => $public) {
            $children[$name] = $generator->create_category(['parent' => $root->id, 'name' => $name]);
            if ($public) {
                $this->set_category_state((int) $children[$name]->id, category_discoverability::STATE_PUBLIC);
            }
        }
        $courses = ['Match' => 'Incendio florestal', 'Miss' => 'Transito urbano', 'Listed' => 'Salvamento aquatico'];
        foreach ($courses as $name => $fullname) {
            $course = $generator->create_course(['category' => $children[$name]->id, 'fullname' => $fullname]);
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $generator->create_course(['category' => $children['Closed']->id, 'fullname' => 'Incendio interno']);

        $this->become(null);
        $options = ['anonymous' => true, 'withposters' => true];
        $rest = population::resolve(new criteria((int) $root->id, $options));
        $this->assertSame(
            ['Match', 'Miss', 'Listed'],
            array_column($rest->posters, 'name'),
            'At rest: the children with a public course.'
        );

        self::forget();
        $searched = population::resolve(new criteria((int) $root->id, $options + ['search' => 'Incendio']));
        $posters = array_column($searched->posters, null, 'name');
        $this->assertSame(['Match', 'Miss', 'Listed'], array_keys($posters), 'Under the search: the same posters.');
        $this->assertSame([1, 0, 0], array_column($searched->posters, 'count'), 'Counted by the criterion.');
        $this->assertTrue($posters['Listed']['istext'], 'The merely listed one is still a text poster.');
        $this->assertTrue($posters['Miss']['expandable'], 'And a zero poster opens, so it can stay open.');
        $this->assertTrue($posters['Listed']['expandable']);
        $this->assertSame(
            ['Match'],
            array_column($searched->subcategories, 'name'),
            'The page\'s own tree still follows the criterion: only the accordion is drawn at rest.'
        );
    }

    /**
     * Under a criterion a panel's strip offers the subareas holding a result, carrying the criterion.
     *
     * The strip's tally is the panel request's own post-filter one, taken free in the PHP cut and
     * with one grouped statement in the SQL cut, so both modes offer the same pills. At rest every
     * subarea is offered without a parameter, which is the control; the visitor's run holds the
     * rule on the public shell too.
     *
     * @param bool $statesort The mode.
     */
    #[DataProvider('modes_provider')]
    public function test_under_a_criterion_the_strip_offers_the_matching_subareas_carrying_it(bool $statesort): void {
        $this->resetAfterTest();
        $this->mode($statesort);
        $generator = self::getDataGenerator();
        $area = $generator->create_category(['name' => 'Area']);
        $this->set_category_state((int) $area->id, category_discoverability::STATE_PUBLIC);
        $subs = [];
        foreach (['Fire' => 'Incendio urbano', 'Road' => 'Transito seguro'] as $name => $fullname) {
            $subs[$name] = $generator->create_category(['parent' => $area->id, 'name' => $name]);
            $this->set_category_state((int) $subs[$name]->id, category_discoverability::STATE_PUBLIC);
            $course = $generator->create_course(['category' => $subs[$name]->id, 'fullname' => $fullname]);
            $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $areaid = (int) $area->id;
        $strip = static function (bool $anonymous, string $search) use ($areaid): array {
            self::forget();
            $options = ['sort' => criteria::SORT_RECENT, 'withcategories' => true, 'anonymous' => $anonymous];
            if ($search !== '') {
                $options['search'] = $search;
            }
            return array_column(population::resolve(new criteria($areaid, $options))->subareas, 'url', 'name');
        };

        foreach (['a person' => false, 'a visitor' => true] as $who => $anonymous) {
            $this->become($anonymous ? null : $generator->create_user());
            $rest = $strip($anonymous, '');
            $this->assertSame(['Fire', 'Road'], array_keys($rest), $who . ': at rest every subarea.');
            foreach ($rest as $name => $url) {
                $this->assertSame(
                    links::showcase((int) $subs[$name]->id, [], $anonymous)->out(false),
                    $url,
                    $who . ': no parameter.'
                );
            }
            $searched = $strip($anonymous, 'Incendio');
            $this->assertSame(['Fire'], array_keys($searched), $who . ': under the search, the subarea holding a result.');
            $this->assertSame(
                links::showcase((int) $subs['Fire']->id, ['q' => 'Incendio'], $anonymous)->out(false),
                $searched['Fire'],
                $who . ': and its pill carries the search, so it opens onto the same question.'
            );
        }
    }

    /**
     * The watermark's opacity steps down from .20 until the name keeps 4.5:1 and the chevron 3:1.
     *
     * Every value is asserted by recomputing both contrasts, and the stepped value by the next
     * hundredth up failing: the largest opacity the text survives, not merely one it survives.
     */
    public function test_the_watermark_steps_down_until_the_name_and_the_chevron_keep_their_contrast(): void {
        $this->assertSame(0.2, population::mark_alpha('#070093', '#ffffff'), 'The navy has room for the whole .20.');
        $this->assertTrue(self::mark_holds('#070093', '#ffffff', 0.2));

        $near = population::mark_alpha('#0f6cbf', '#ffffff');
        $this->assertSame(0.09, $near, 'A pair just over the floor steps down.');
        $this->assertTrue(self::mark_holds('#0f6cbf', '#ffffff', $near), 'The text survives the value chosen.');
        $this->assertFalse(self::mark_holds('#0f6cbf', '#ffffff', $near + 0.01), 'And not the next one up.');

        $this->assertGreaterThanOrEqual(branding::FLOOR, branding::contrast('#767676', '#ffffff'), 'Precondition: AA.');
        $this->assertSame(0.0, population::mark_alpha('#767676', '#ffffff'), 'A pair at the floor gets no mark.');
        $this->assertFalse(self::mark_holds('#767676', '#ffffff', 0.01));

        $this->assertSame(0.0, population::mark_alpha('crimson', '#ffffff'), 'Not a colour: no mark, fail closed.');
    }

    /**
     * The watermark's crest reaches the style attribute only through the style url guard.
     *
     * The style attribute is a CSS context that Mustache's escaping does not protect
     * ({@see \theme_dimensions\local\styleurl::safe()}): a crest url that could close
     * the url() - here a quote and a parenthesis - is refused and the stylesheet draws the folder,
     * beside a clean crest as the control.
     */
    public function test_the_watermark_crest_is_printed_only_through_the_style_url_guard(): void {
        $clean = 'https://example.com/pluginfile.php/31/theme_dimensions/categorybadge1/7/abc/crest.png';
        $this->assertSame(
            ['style' => " --dim-mark-a: 0.20; --dim-mark: url('" . $clean . "');", 'hasmark' => true, 'markfolder' => false],
            population::mark('#070093', '#ffffff', [['url' => $clean, 'alt' => 'crest']])
        );
        $poisoned = "https://example.com/pluginfile.php/31/x.png');background:url(https://evil.example/";
        $this->assertSame(
            ['style' => ' --dim-mark-a: 0.20;', 'hasmark' => true, 'markfolder' => true],
            population::mark('#070093', '#ffffff', [['url' => $poisoned, 'alt' => 'x']]),
            'A url that could escape the declaration is refused, and the folder stands in.'
        );
        $this->assertSame(
            ['style' => ' --dim-mark-a: 0.20;', 'hasmark' => true, 'markfolder' => true],
            population::mark('#070093', '#ffffff', [])
        );
        $this->assertSame(
            ['style' => '', 'hasmark' => false, 'markfolder' => false],
            population::mark('#767676', '#ffffff', [['url' => $clean, 'alt' => 'crest']]),
            'A pair with no room for a mark has none, crest or not.'
        );
    }

    /**
     * Whether a watermark at an opacity leaves the name 4.5:1 and the chevron 3:1, recomputed here.
     *
     * @param string $band The band.
     * @param string $ink The ink.
     * @param float $alpha The mark's opacity.
     * @return bool
     */
    private static function mark_holds(string $band, string $ink, float $alpha): bool {
        $under = (string) branding::mix($band, $ink, $alpha);
        $disc = (string) branding::mix($band, $ink, 1 - (1 - $alpha) * (1 - 0.18));
        return branding::contrast($ink, $under) >= 4.5 && branding::contrast($ink, $disc) >= 3.0;
    }
}
