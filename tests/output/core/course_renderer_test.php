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
 * Theme Dimensions - Tests for the course renderer: category withholding and the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output\core;

use core_course\customfield\course_handler;
use core_course_category;
use coursecat_helper;
use local_unlistedcourses\access;
use local_unlistedcourses\category_access;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\fixture;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_toolbar;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\category_pages;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../local/category/fixture.php');

/**
 * Tests for the course renderer's overrides: category withholding, and the category showcase page.
 *
 * These render the real thing - core's coursecat_helper, Boost Union's course
 * renderer underneath, this theme's overrides on top - because what matters (a
 * category node disappearing from the tree, an expand arrow that stops
 * promising content nobody may open, the showcase's markup and its statement
 * count) is only visible in the output. Every test that asserts a category is
 * withheld also asserts that some control (a listed sibling, a cohort member,
 * an admin) still sees it in the same run, so an assertion cannot pass against
 * a renderer that dropped everything.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_renderer::class)]
final class course_renderer_test extends \advanced_testcase {
    use fixture;

    /** @var int The most reads a warm first render of the showcase page may cost, set to the measured count. */
    private const RENDER_CEILING = 18;

    /**
     * @var int What the pages strip adds where local_page is installed: its catalogue's one statement.
     *
     * Flat in the population like the rest: the category's context is already preloaded when the
     * catalogue asks for it, so the statement reads that context's pages alone.
     */
    private const PAGES_READS = 1;

    /** @var int Peak memory a first render of two hundred courses may add, in bytes (it measures about 600 KB). */
    private const RENDER_MEMORY = 8 * 1024 * 1024;

    /** @var float Wall time a first render of two hundred courses may take, in seconds (it measures about 90 ms). */
    private const RENDER_SECONDS = 1.0;

    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Build this theme's course renderer on a page of the category listing.
     *
     * @return course_renderer
     */
    private function renderer(): course_renderer {
        global $PAGE, $SITE;

        $PAGE->set_context(\context_system::instance());
        // The course is set before the theme is forced, mirroring core_renderer_test.php's
        // helper: initialise_theme_and_output() would set it to the site on its own, and
        // set_course() refuses to run once a theme is already in place.
        $PAGE->set_course($SITE);
        $PAGE->set_url('/course/index.php');
        $PAGE->force_theme('dimensions');

        /** @var course_renderer $renderer */
        $renderer = $PAGE->get_renderer('core', 'course', RENDERER_TARGET_GENERAL);
        return $renderer;
    }

    /**
     * Render core's tree on the category page: switch the showcase off for this test.
     *
     * The tree overrides below stay in force for the front page listings, the AJAX expander
     * and a site that switched the showcase off; on the category page itself the showcase
     * has replaced the tree, so the tests of the tree ask for the page as it was.
     *
     * @return void
     */
    private function core_tree(): void {
        set_config(population::SETTING_ENABLED, '0', 'theme_dimensions');
    }

    /**
     * Add a user to a cohort defined at a category's own context.
     *
     * @param int $categoryid The category the cohort is defined at.
     * @param int $userid The user to add.
     * @return void
     */
    private function make_cohort_member(int $categoryid, int $userid): void {
        global $CFG;

        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = self::getDataGenerator()->create_cohort([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
        ]);
        cohort_add_member($cohort->id, $userid);
    }

    /**
     * Mark a category unlisted, as admin, whoever is currently logged in.
     *
     * @param int $categoryid The category id.
     * @return void
     */
    private function mark_unlisted(int $categoryid): void {
        $current = $GLOBALS['USER'];
        $this->setAdminUser();
        category_discoverability::set_state($categoryid, category_discoverability::STATE_UNLISTED);
        $this->setUser($current);
        category_access::reset_caches();
    }

    /**
     * Extract the class attribute of one category node from a rendered tree.
     *
     * A targeted extraction rather than a whole-page search: the tree can hold
     * several category divs, and a bare substring search for "with_children"
     * would match whichever node happens to carry it first.
     *
     * @param string $html The rendered tree.
     * @param int $categoryid The category whose node to find.
     * @return string The node's class attribute value.
     */
    private function category_node_classes(string $html, int $categoryid): string {
        $found = preg_match('/<div class="([^"]*)" data-categoryid="' . $categoryid . '"/', $html, $matches);
        $this->assertSame(1, $found, "The category node for id {$categoryid} was not found in the rendered tree.");
        return $matches[1];
    }

    /**
     * Call the protected coursecat_category_content(), the AJAX expansion's own entry point.
     *
     * @param course_renderer $renderer The renderer.
     * @param coursecat_helper $chelper Display options.
     * @param mixed $coursecat The category.
     * @param int $depth Nesting depth.
     * @return string
     */
    private function invoke_category_content(course_renderer $renderer, coursecat_helper $chelper, $coursecat, int $depth): string {
        $method = new \ReflectionMethod($renderer, 'coursecat_category_content');
        return (string) $method->invoke($renderer, $chelper, $coursecat, $depth);
    }

    /**
     * Call the protected coursecat_category(), the node builder every other caller reaches.
     *
     * @param course_renderer $renderer The renderer.
     * @param coursecat_helper $chelper Display options.
     * @param mixed $coursecat The category.
     * @param int $depth Nesting depth.
     * @return string
     */
    private function invoke_category_node(
        course_renderer $renderer,
        coursecat_helper $chelper,
        $coursecat,
        int $depth
    ): string {
        $method = new \ReflectionMethod($renderer, 'coursecat_category');
        return (string) $method->invoke($renderer, $chelper, $coursecat, $depth);
    }

    /**
     * The factory really does hand over this theme's renderer.
     *
     * The class name a theme override must carry is derived, not declared, so
     * a file in the wrong directory fails by silently doing nothing: core's
     * own renderer answers and every guard in this class is simply absent.
     */
    public function test_the_theme_renderer_is_the_one_the_factory_returns(): void {
        $this->resetAfterTest();

        $this->assertInstanceOf(course_renderer::class, $this->renderer());
    }

    /**
     * The category tree names a listed child and withholds an unlisted one, per viewer.
     *
     * The listed sibling is the control that the tree rendered at all and that
     * the filter is selective, not a renderer that dropped every child.
     */
    public function test_the_tree_names_only_discoverable_categories(): void {
        $this->resetAfterTest();
        $this->core_tree();
        $generator = self::getDataGenerator();

        $parent = $generator->create_category(['name' => 'Parent Category']);
        $generator->create_category(['parent' => $parent->id, 'name' => 'Listed Child']);
        $unlisted = $generator->create_category(['parent' => $parent->id, 'name' => 'Unlisted Child']);
        $this->mark_unlisted((int) $unlisted->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $unlisted->id, (int) $member->id);
        $outsider = $generator->create_user();

        $renderer = $this->renderer();

        $this->setUser($outsider);
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString('Listed Child', $html);
        $this->assertStringNotContainsString('Unlisted Child', $html, 'An outsider must not learn the child exists.');

        $this->setUser($member);
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString('Listed Child', $html);
        $this->assertStringContainsString('Unlisted Child', $html, 'Control: the cohort member must see it.');

        $this->setAdminUser();
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString('Listed Child', $html);
        $this->assertStringContainsString('Unlisted Child', $html, 'Control: an admin must see it.');
    }

    /**
     * The paging bar counts what the viewer will actually see, never the raw total.
     *
     * Three listed and two unlisted children, two per page: an outsider's
     * filtered total is three (two pages), a cohort member eligible for both
     * unlisted children sees the real five (three pages). Left to core's own
     * get_children_count(), every viewer would be offered a third page that
     * renders one item short or entirely empty.
     */
    public function test_the_tree_paginates_over_the_filtered_count_not_the_raw_one(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->core_tree();
        $CFG->coursesperpage = 2;
        $generator = self::getDataGenerator();

        $parent = $generator->create_category(['name' => 'Parent With Many Children']);
        for ($i = 1; $i <= 3; $i++) {
            $generator->create_category(['parent' => $parent->id, 'name' => "Listed Child {$i}"]);
        }
        $unlistedids = [];
        for ($i = 1; $i <= 2; $i++) {
            $child = $generator->create_category(['parent' => $parent->id, 'name' => "Unlisted Child {$i}"]);
            $unlistedids[] = (int) $child->id;
        }
        $member = $generator->create_user();
        foreach ($unlistedids as $unlistedid) {
            $this->mark_unlisted($unlistedid);
            $this->make_cohort_member($unlistedid, (int) $member->id);
        }
        $outsider = $generator->create_user();

        $renderer = $this->renderer();

        $this->setUser($outsider);
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString(
            'data-page-number="2"',
            $html,
            'Three visible children over two per page make two pages.'
        );
        $this->assertStringNotContainsString(
            'data-page-number="3"',
            $html,
            'The raw count of five must never leak into an outsider\'s pagination.'
        );

        $this->setUser($member);
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString(
            'data-page-number="3"',
            $html,
            'Control: the cohort member is eligible for every child, so the announced total is the real five.'
        );
    }

    /**
     * A discoverable category whose only child is unlisted carries no live expand arrow for an outsider.
     *
     * Left to core's raw get_children_count(), the "with_children"/"collapsed"
     * classes would stay on the node whatever the viewer can reach, which is an
     * existence signal in itself: an arrow that opens onto nothing still says
     * something is there. The parent is rendered at depth 1 of a grandparent's
     * tree, exactly where core's default subcat_depth (1) leaves it unloaded.
     */
    public function test_the_expand_affordance_reflects_the_filtered_child_count(): void {
        $this->resetAfterTest();
        $this->core_tree();
        $generator = self::getDataGenerator();

        $grandparent = $generator->create_category(['name' => 'Grandparent']);
        $parent = $generator->create_category(['parent' => $grandparent->id, 'name' => 'Parent Category']);
        $child = $generator->create_category(['parent' => $parent->id, 'name' => 'Hidden Only Child']);
        $this->mark_unlisted((int) $child->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $child->id, (int) $member->id);
        $outsider = $generator->create_user();

        $renderer = $this->renderer();

        $this->setUser($outsider);
        category_access::reset_caches();
        $html = $renderer->course_category((int) $grandparent->id);
        $this->assertStringNotContainsString(
            'with_children',
            $this->category_node_classes($html, (int) $parent->id),
            'The only child is unlisted: an outsider must see no expand affordance.'
        );

        $this->setUser($member);
        category_access::reset_caches();
        $html = $renderer->course_category((int) $grandparent->id);
        $this->assertStringContainsString(
            'with_children',
            $this->category_node_classes($html, (int) $parent->id),
            'Control: the cohort member is eligible for the child, so the affordance must stay.'
        );
    }

    /**
     * A category whose only course is unlisted carries no live expand arrow for an outsider.
     *
     * The course half of core's condition is get_courses_count(), which counts the unlisted
     * course; the arrow would open onto an empty box and say a course is there. The enrolled
     * user is the control: access::filter_courses() keeps the course for them, so the arrow stays.
     */
    public function test_the_expand_affordance_reflects_the_filtered_course_count(): void {
        $this->resetAfterTest();
        $this->core_tree();
        $generator = self::getDataGenerator();

        $grandparent = $generator->create_category(['name' => 'Grandparent']);
        $parent = $generator->create_category(['parent' => $grandparent->id, 'name' => 'Parent Category']);
        $course = $generator->create_course(['category' => $parent->id]);
        discoverability::set_state((int) $course->id, discoverability::STATE_UNLISTED);
        $enrolled = $generator->create_user();
        $generator->enrol_user($enrolled->id, $course->id, 'student');
        $outsider = $generator->create_user();

        $renderer = $this->renderer();

        $this->setUser($outsider);
        access::reset_caches();
        category_access::reset_caches();
        $html = $renderer->course_category((int) $grandparent->id);
        $this->assertStringNotContainsString(
            'with_children',
            $this->category_node_classes($html, (int) $parent->id),
            'The only course is unlisted: an outsider must see no expand affordance.'
        );

        $this->setUser($enrolled);
        access::reset_caches();
        category_access::reset_caches();
        $html = $renderer->course_category((int) $grandparent->id);
        $this->assertStringContainsString(
            'with_children',
            $this->category_node_classes($html, (int) $parent->id),
            'Control: the enrolled user may discover the course, so the affordance must stay.'
        );
    }

    /**
     * In COUNT mode the number beside a category name counts only the courses this user may discover.
     *
     * Core appends " (N)" from the raw get_courses_count() (course/renderer.php:886-890), and Boost Union a
     * bare badge under its categorylistingpresentation setting; both include unlisted courses, and the
     * front page's category list prints them, so the test reads both spellings. Two courses, one of
     * them unlisted: the outsider reads 1, the enrolled user (the control) reads the real 2, and
     * a category whose every course is withheld prints no span at all, as core prints none at zero.
     */
    public function test_the_course_count_beside_a_category_name_is_the_filtered_one(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/boost_union/lib.php');
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        $mixed = $generator->create_category(['name' => 'Mixed Courses']);
        $generator->create_course(['category' => $mixed->id]);
        $hidden = $generator->create_course(['category' => $mixed->id]);
        discoverability::set_state((int) $hidden->id, discoverability::STATE_UNLISTED);
        $allhidden = $generator->create_category(['name' => 'All Withheld']);
        $only = $generator->create_course(['category' => $allhidden->id]);
        discoverability::set_state((int) $only->id, discoverability::STATE_UNLISTED);
        $alllisted = $generator->create_category(['name' => 'All Listed']);
        $generator->create_course(['category' => $alllisted->id]);
        $generator->create_course(['category' => $alllisted->id]);

        $enrolled = $generator->create_user();
        $generator->enrol_user($enrolled->id, $hidden->id, 'student');
        $generator->enrol_user($enrolled->id, $only->id, 'student');
        $outsider = $generator->create_user();

        $renderer = $this->renderer();
        $chelper = new coursecat_helper();
        $chelper->set_show_courses(course_renderer::COURSECAT_SHOW_COURSES_COUNT);
        $this->setAdminUser();
        $mixedobject = core_course_category::get((int) $mixed->id);
        $allhiddenobject = core_course_category::get((int) $allhidden->id);
        $alllistedobject = core_course_category::get((int) $alllisted->id);
        // Core's " (N)" and Boost Union's bare badge, the number captured from either.
        $pattern = '~<span[^>]*class="numberofcourse[^"]*"[^>]*>\D*(\d+)\D*</span>~';
        $spellings = [
            THEME_BOOST_UNION_SETTING_CATLISTPRES_NOCHANGE => '> (',
            THEME_BOOST_UNION_SETTING_CATLISTPRES_BOXLIST => 'numberofcourse badge',
        ];

        foreach ($spellings as $presentation => $spelling) {
            set_config('categorylistingpresentation', $presentation, 'theme_boost_union');

            $this->setUser($outsider);
            access::reset_caches();
            category_access::reset_caches();
            $html = $this->invoke_category_node($renderer, $chelper, $mixedobject, 0);
            $this->assertSame(1, preg_match($pattern, $html, $matches), "Precondition ($presentation): the span is there.");
            $this->assertStringContainsString($spelling, $matches[0], "Precondition: $presentation draws its own spelling.");
            $this->assertSame('1', $matches[1], "Two courses, one unlisted: the outsider reads one ($presentation).");
            $this->assertStringNotContainsString(
                'numberofcourse',
                $this->invoke_category_node($renderer, $chelper, $allhiddenobject, 0),
                "Every course withheld: no span, as core prints none at zero ($presentation)."
            );
            $html = $this->invoke_category_node($renderer, $chelper, $alllistedobject, 0);
            $this->assertSame(1, preg_match($pattern, $html, $matches));
            $this->assertSame('2', $matches[1], "Control: with everything listed the raw number shows ($presentation).");

            $this->setUser($enrolled);
            access::reset_caches();
            category_access::reset_caches();
            $this->assertSame(1, preg_match($pattern, $this->invoke_category_node($renderer, $chelper, $mixedobject, 0), $matches));
            $this->assertSame('2', $matches[1], "Control: the enrolled user may discover both ($presentation).");
            $this->assertStringContainsString(
                'numberofcourse',
                $this->invoke_category_node($renderer, $chelper, $allhiddenobject, 0),
                "Control: the enrolled user keeps the span of the category whose course is withheld from others ($presentation)."
            );
        }
    }

    /**
     * A tree whose every child is unlisted offers an outsider no expand-all control.
     *
     * The companion of the test above, one level up: core decides the "Expand all" link from the
     * category's RAW child count (course/renderer.php:925), so the link would survive over a tree
     * that holds nothing expandable for this viewer. The parent's own course is the control that
     * the tree rendered at all - without it coursecat_tree() returns the empty string for
     * everybody and the assertion would pass over nothing.
     */
    public function test_the_expand_all_control_goes_with_the_children_it_would_expand(): void {
        $this->resetAfterTest();
        $this->core_tree();
        $generator = self::getDataGenerator();

        $parent = $generator->create_category(['name' => 'Parent With One Hidden Child']);
        $generator->create_course(['fullname' => 'Course Of The Parent', 'category' => $parent->id]);
        $child = $generator->create_category(['parent' => $parent->id, 'name' => 'Only Unlisted Child']);
        $this->mark_unlisted((int) $child->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $child->id, (int) $member->id);
        $outsider = $generator->create_user();

        $renderer = $this->renderer();

        $this->setUser($outsider);
        access::reset_caches();
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString('Course Of The Parent', $html, 'Control: the tree itself must render.');
        $this->assertStringNotContainsString(
            'collapsible-actions',
            $html,
            'Nothing in this tree can be expanded by an outsider, so nothing may offer to expand it.'
        );

        $this->setUser($member);
        access::reset_caches();
        category_access::reset_caches();
        $html = $renderer->course_category((int) $parent->id);
        $this->assertStringContainsString(
            'collapsible-actions',
            $html,
            'Control: the cohort member has a child to expand, so the control must stay.'
        );
    }

    /**
     * Boost Union's course card names the category only to someone eligible for it.
     *
     * The card and list presentations never reach core's course_category_name(): they name the
     * category from theme_boost_union\util\course::get_category(), with no predicate anywhere.
     * With Boost Union's courselistingpresentation set to cards this is the path that runs, and
     * the guard on course_category_name() (tested below) would be dead on it.
     */
    public function test_the_card_category_badge_is_shown_only_to_the_eligible(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        set_config('courselistingpresentation', 'cards', 'theme_boost_union');
        set_config('courselistinghowcategory', 'yes', 'theme_boost_union');

        $parent = $generator->create_category(['name' => 'Parent For Cards']);
        $unlisted = $generator->create_category(['parent' => $parent->id, 'name' => 'Carded Restricted Category']);
        $this->mark_unlisted((int) $unlisted->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $unlisted->id, (int) $member->id);

        $course = $generator->create_course(['fullname' => 'Carded Restricted Course', 'category' => $unlisted->id]);
        $outsider = $generator->create_user();
        $generator->enrol_user($outsider->id, $course->id, 'student');
        $generator->enrol_user($member->id, $course->id, 'student');

        $renderer = $this->renderer();

        $this->setUser($outsider);
        access::reset_caches();
        category_access::reset_caches();
        $html = $renderer->search_courses(['search' => 'Carded Restricted Course']);
        $this->assertStringContainsString('categoryname', $html, 'Precondition: the card presentation really rendered.');
        $this->assertStringContainsString('Carded Restricted Course', $html, 'Control: the course itself must still show.');
        $this->assertStringNotContainsString('Carded Restricted Category', $html);

        $this->setUser($member);
        access::reset_caches();
        category_access::reset_caches();
        $html = $renderer->search_courses(['search' => 'Carded Restricted Course']);
        $this->assertStringContainsString('Carded Restricted Course', $html);
        $this->assertStringContainsString(
            'Carded Restricted Category',
            $html,
            'Control: the cohort member must read the category on the same card.'
        );
    }

    /**
     * The AJAX expansion of an unlisted category's own content reveals nothing to an outsider.
     *
     * coursecat_category_content() is the whole of course/category.ajax.php's
     * output for a not-yet-loaded node, a path the theme's after_config guard
     * never sees because it returns early for AJAX_SCRIPT. The inner listed
     * child is the control: without it, an unlisted category with nothing
     * inside would read "empty" for every viewer regardless of the guard. The
     * top-level pseudo-category (id 0) is never gated, whoever asks.
     */
    public function test_the_ajax_expansion_of_an_unlisted_category_reveals_nothing(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        $category = $generator->create_category(['name' => 'Unlisted With Content']);
        $generator->create_category(['parent' => $category->id, 'name' => 'Inner Listed Child']);
        $this->mark_unlisted((int) $category->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $category->id, (int) $member->id);
        $outsider = $generator->create_user();

        $renderer = $this->renderer();
        $chelper = new coursecat_helper();
        $chelper->set_show_courses(course_renderer::COURSECAT_SHOW_COURSES_AUTO);
        // Fetched as admin: core_course_category::get() gates on moodle/category:viewcourselist
        // regardless of the category's own visible flag, and that is not what this test is about.
        $this->setAdminUser();
        $coursecatobject = core_course_category::get((int) $category->id);

        $this->setUser($outsider);
        category_access::reset_caches();
        $this->assertSame('', $this->invoke_category_content($renderer, $chelper, $coursecatobject, 0));

        $this->setUser($member);
        category_access::reset_caches();
        $memberoutput = $this->invoke_category_content($renderer, $chelper, $coursecatobject, 0);
        $this->assertStringContainsString(
            'Inner Listed Child',
            $memberoutput,
            'Control: the cohort member must see what is actually inside.'
        );

        // The pseudo-root has id 0: the guard must never fail closed on it.
        $this->setUser($outsider);
        category_access::reset_caches();
        $top = core_course_category::top();
        $topoutput = $this->invoke_category_content($renderer, $chelper, $top, 0);
        $this->assertNotSame('', $topoutput, 'The top-level pseudo-category must always render, whoever is asking.');
    }

    /**
     * The "Category: name" line beside a course is shown only to someone eligible for that category.
     *
     * filter_courses() already keeps the course itself on both viewers' listings
     * (both are enrolled, which rescues it from the category term), so the
     * course's own presence is not what is under test - only whether the
     * category name printed beside it leaks to somebody not eligible for the
     * category. Exercised through search_courses(), the simplest public route
     * to COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT.
     */
    public function test_course_category_name_is_shown_only_to_the_eligible(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        $parent = $generator->create_category(['name' => 'Parent For Search']);
        $unlisted = $generator->create_category(['parent' => $parent->id, 'name' => 'Restricted Category']);
        $this->mark_unlisted((int) $unlisted->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $unlisted->id, (int) $member->id);

        $course = $generator->create_course(['fullname' => 'Findable Restricted Course', 'category' => $unlisted->id]);
        $outsider = $generator->create_user();
        $generator->enrol_user($outsider->id, $course->id, 'student');
        $generator->enrol_user($member->id, $course->id, 'student');

        $renderer = $this->renderer();
        $categorylabel = get_string('category') . ':';

        $this->setUser($outsider);
        access::reset_caches();
        $html = $renderer->search_courses(['search' => 'Findable Restricted Course']);
        $this->assertStringContainsString('Findable Restricted Course', $html, 'Control: the course itself must still show.');
        $this->assertStringNotContainsString($categorylabel, $html);

        $this->setUser($member);
        access::reset_caches();
        $html = $renderer->search_courses(['search' => 'Findable Restricted Course']);
        $this->assertStringContainsString('Findable Restricted Course', $html);
        $this->assertStringContainsString($categorylabel, $html, 'Control: the cohort member must see the category line.');
    }

    /**
     * The tagged_courses() non-exclusive branch withholds its own "Category: name" detail too.
     *
     * This branch builds its own core_tag\output\tagfeed and never reaches
     * coursecat_courses() or course_category_name() at all, which is why it
     * needs its own guard rather than inheriting the one above.
     */
    public function test_tagged_courses_category_line_is_shown_only_to_the_eligible(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        $parent = $generator->create_category(['name' => 'Parent For Tags']);
        $unlisted = $generator->create_category(['parent' => $parent->id, 'name' => 'Tag Restricted Category']);
        $this->mark_unlisted((int) $unlisted->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $unlisted->id, (int) $member->id);

        $course = $generator->create_course(['fullname' => 'Tagged Restricted Course', 'category' => $unlisted->id]);
        $this->assertTrue(
            \core_tag_area::is_enabled('core', 'course'),
            'Precondition: course tagging must be enabled or set_item_tags() silently no-ops.'
        );
        \core_tag_tag::set_item_tags(
            'core',
            'course',
            (int) $course->id,
            \core\context\course::instance($course->id),
            ['dimensionstag']
        );
        $tag = \core_tag_tag::get_by_name(0, 'dimensionstag', 'id, name, rawname, tagcollid', MUST_EXIST);

        $outsider = $generator->create_user();
        $generator->enrol_user($outsider->id, $course->id, 'student');
        $generator->enrol_user($member->id, $course->id, 'student');

        $renderer = $this->renderer();
        $categorylabel = get_string('category') . ':';

        $this->setUser($outsider);
        access::reset_caches();
        $html = $renderer->tagged_courses((int) $tag->id, false);
        $this->assertStringContainsString('Tagged Restricted Course', $html, 'Control: the course itself must still show.');
        $this->assertStringNotContainsString($categorylabel, $html);

        $this->setUser($member);
        access::reset_caches();
        $html = $renderer->tagged_courses((int) $tag->id, false);
        $this->assertStringContainsString('Tagged Restricted Course', $html);
        $this->assertStringContainsString($categorylabel, $html, 'Control: the cohort member must see the category line.');
    }

    /**
     * A category node handed over unfiltered is withheld all the same.
     *
     * The listing above filters the children before this method ever sees
     * them, so on the tree's own path the guard here withholds nothing - which
     * is precisely why it needs a test that does not go through the tree. It is
     * the net under every OTHER caller: core's coursecat_tree(), the two front
     * page methods, and whatever core adds next, each of which may hand this
     * method a category it chose somewhere else. Driven by reflection for that
     * reason - a public entry point would filter first and test the filter
     * again.
     */
    public function test_a_category_node_handed_over_unfiltered_is_still_withheld(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        $listed = $generator->create_category(['name' => 'Listed Sibling']);
        $unlisted = $generator->create_category(['name' => 'Unlisted Node']);
        $this->mark_unlisted((int) $unlisted->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $unlisted->id, (int) $member->id);
        $outsider = $generator->create_user();

        $renderer = $this->renderer();
        $chelper = new coursecat_helper();
        $chelper->set_show_courses(course_renderer::COURSECAT_SHOW_COURSES_AUTO);
        // Fetched as admin for the reason the AJAX test gives: core_course_category::get()
        // gates on a capability that is not what this test is about.
        $this->setAdminUser();
        $unlistedobject = core_course_category::get((int) $unlisted->id);
        $listedobject = core_course_category::get((int) $listed->id);

        $this->setUser($outsider);
        category_access::reset_caches();
        $this->assertSame('', $this->invoke_category_node($renderer, $chelper, $unlistedobject, 0));
        $this->assertStringContainsString(
            'Listed Sibling',
            $this->invoke_category_node($renderer, $chelper, $listedobject, 0),
            'Control: a listed category handed over exactly the same way is rendered.'
        );

        $this->setUser($member);
        category_access::reset_caches();
        $this->assertStringContainsString(
            'Unlisted Node',
            $this->invoke_category_node($renderer, $chelper, $unlistedobject, 0),
            'Control: the cohort member must see the node.'
        );
    }

    /**
     * The AJAX expander refuses an unlisted category even to somebody enrolled inside it.
     *
     * This is the case that shows the guard is not redundant with the filters
     * under it. Everything nested inside an unlisted category is itself
     * undiscoverable - the state is a property of the PATH - so a subcategory
     * is dropped by the listing filter whatever this guard does. A COURSE is
     * not: access::filter_courses() deliberately rescues one for the people who
     * hold a relationship with the course itself - enrolled, applying, staff -
     * so without the guard the expander would answer a request naming THIS
     * category with that person's own course box, which confirms both that the
     * category exists and that it is the one their course sits in.
     * redirector::is_category_ghosted() gives the same person the same answer
     * on /course/index.php?categoryid=N, and this is the AJAX path agreeing
     * with it.
     */
    public function test_the_ajax_expander_refuses_an_unlisted_category_to_an_enrolled_outsider(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();

        $category = $generator->create_category(['name' => 'Unlisted With A Course']);
        $course = $generator->create_course([
            'fullname' => 'Course Inside The Unlisted Category',
            'category' => $category->id,
        ]);
        $this->mark_unlisted((int) $category->id);
        $enrolled = $generator->create_user();
        $generator->enrol_user($enrolled->id, $course->id, 'student');
        $member = $generator->create_user();
        $this->make_cohort_member((int) $category->id, (int) $member->id);

        $renderer = $this->renderer();
        $chelper = new coursecat_helper();
        $chelper->set_show_courses(course_renderer::COURSECAT_SHOW_COURSES_EXPANDED);
        // Fetched as admin for the reason the test above gives.
        $this->setAdminUser();
        $coursecatobject = core_course_category::get((int) $category->id);

        $this->setUser($enrolled);
        access::reset_caches();
        category_access::reset_caches();
        $this->assertSame(
            '',
            $this->invoke_category_content($renderer, $chelper, $coursecatobject, 0),
            'The expander must not confirm the category even to somebody enrolled inside it.'
        );

        $this->setUser($member);
        access::reset_caches();
        category_access::reset_caches();
        $this->assertStringContainsString(
            'Course Inside The Unlisted Category',
            $this->invoke_category_content($renderer, $chelper, $coursecatobject, 0),
            'Control: the cohort member sees the course, so the refusal above is the guard answering.'
        );
    }

    /**
     * The common shape of every showcase test: the fields, the settings, a viewer.
     *
     * @return \stdClass The viewer.
     */
    private function prepare_showcase(): \stdClass {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $CFG->coursecontact = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODEL, 'theme_dimensions');
        return self::getDataGenerator()->create_user();
    }

    /**
     * Seed a root with a population of a given size and the same shape whatever the size.
     *
     * The budget test's fixture: the model and a programme on every course, an image on the
     * first three, one unlisted course nobody is related to, one hidden course, one course the
     * viewer is enrolled in, one favourite.
     *
     * @param int $courses How many courses.
     * @param \stdClass $viewer The viewer.
     * @return int The root category id.
     */
    private function seed_showcase(int $courses, \stdClass $viewer): int {
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $categories = [];
        for ($c = 0; $c < max(1, min(5, intdiv($courses, 40))); $c++) {
            $categories[] = $generator->create_category(['parent' => $root->id]);
        }
        $handler = course_handler::create();
        $ids = [];
        for ($i = 0; $i < $courses; $i++) {
            $course = $generator->create_course(['category' => $categories[$i % count($categories)]->id]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => 1 + ($i % 2),
                'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                    'text' => "<p>Programme {$i}</p>", 'format' => FORMAT_HTML,
                ],
            ]);
            $ids[] = (int) $course->id;
            if ($i < 3) {
                get_file_storage()->create_file_from_string([
                    'contextid' => \core\context\course::instance((int) $course->id)->id,
                    'component' => 'course',
                    'filearea' => 'overviewfiles',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => 'card.png',
                ], base64_decode(self::PNG));
            }
        }
        $unlisted = $generator->create_course(['category' => $root->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $generator->create_course(['category' => $root->id, 'visible' => 0]);
        $generator->enrol_user($viewer->id, $ids[0]);
        \core_favourites\service_factory::get_service_for_user_context(\core\context\user::instance($viewer->id))
            ->create_favourite('core_course', 'courses', $ids[1], \core\context\course::instance($ids[1]));
        return (int) $root->id;
    }

    /**
     * Record one measurement in the budget file in the system temp directory, beside budget_test's own.
     *
     * @param string $label What was measured.
     * @param array $numbers The numbers.
     * @return void
     */
    private function record_showcase(string $label, array $numbers): void {
        $path = sys_get_temp_dir() . '/theme_dimensions-vitrine-budget.json';
        $all = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $all[$label] = $numbers + ['measured' => date('c')];
        file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT));
    }

    /**
     * The showcase renders for an admitted category, and the kill switch reverts the page to core's.
     *
     * Both halves carry the course name as the control: the switch changes the
     * page, never what a viewer may see.
     */
    public function test_the_showcase_renders_and_the_kill_switch_reverts_to_core(): void {
        $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $course = $generator->create_course(['category' => $category->id, 'fullname' => 'Visible Course']);
        $this->become($generator->create_user());
        $renderer = $this->renderer();

        $html = $renderer->course_category((int) $category->id);
        $this->assertStringContainsString('dim-vitrine', $html);
        $this->assertStringContainsString('Visible Course', $html);
        $this->assertStringContainsString('data-courseid="' . $course->id . '"', $html);
        $this->assertStringContainsString(get_string('category_state_none', 'theme_dimensions'), $html);
        $this->assertSame((int) $category->id, category_hero::current()?->category_id(), 'The hero is armed for the header.');

        set_config(population::SETTING_ENABLED, '0', 'theme_dimensions');
        $html = $renderer->course_category((int) $category->id);
        $this->assertStringNotContainsString('dim-vitrine', $html, 'Switched off, the page is core\'s.');
        $this->assertStringContainsString('Visible Course', $html, 'Control: core\'s page lists the course.');
        $this->assertNull(category_hero::current(), 'And no hero of the earlier decision reaches the header.');

        set_config(population::SETTING_ENABLED, '1', 'theme_dimensions');
        $this->assertStringContainsString('dim-vitrine', $renderer->course_category((int) $category->id));
    }

    /**
     * With local_page installed, the showcase names the category's readable pages above the toolbar.
     *
     * The real plugin, where it is installed. The reader is the site administrator on purpose: the
     * page address lets them preview a draft, so the draft's absence is the list's own rule and not
     * the read rule's. The other category is the control that the strip belongs to the category
     * asked about, and a category with no page draws no strip at all. Without the plugin the
     * strip's own test holds the page unchanged, with a stand-in.
     */
    public function test_with_local_page_the_showcase_names_the_categorys_pages_above_the_toolbar(): void {
        if (!class_exists(category_pages::CATALOGUE)) {
            $this->markTestSkipped('local_page is not installed here, or predates its catalogue.');
        }
        $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Showcase Category']);
        $other = $generator->create_category(['name' => 'Other Category']);
        $bare = $generator->create_category(['name' => 'Bare Category']);
        foreach ([$category, $other, $bare] as $each) {
            $generator->create_course(['category' => $each->id, 'fullname' => 'Course of ' . $each->name]);
        }
        $pages = $generator->get_plugin_generator('local_page');
        $pages->create_category_page((int) $category->id, ['pagename' => 'Edital 2026', 'menuname' => 'edital']);
        $pages->create_category_page((int) $category->id, [
            'pagename' => 'Rascunho do edital', 'menuname' => 'rascunho', 'status' => 'draft',
        ]);
        $pages->create_category_page((int) $other->id, ['pagename' => 'Agenda alheia', 'menuname' => 'agenda']);
        $this->become(get_admin());
        $renderer = $this->renderer();

        $html = $renderer->course_category((int) $category->id);
        $strip = strpos($html, 'data-region="dim-pages"');
        $this->assertNotFalse($strip, 'The strip is drawn.');
        $this->assertLessThan(strpos($html, 'data-region="dim-vitrine-toolbar"'), $strip, 'Under the hero, above the toolbar.');
        $this->assertStringContainsString('<span class="dim-pages__name">Edital 2026</span>', $html);
        $this->assertStringNotContainsString('Rascunho do edital', $html, 'A list is what readers see: no draft.');
        $this->assertStringNotContainsString('Agenda alheia', $html, 'Another category\'s page is not this one\'s.');

        $html = $renderer->course_category((int) $other->id);
        $this->assertStringContainsString('<span class="dim-pages__name">Agenda alheia</span>', $html, 'Control.');
        $this->assertStringNotContainsString('Edital 2026', $html);

        $html = $renderer->course_category((int) $bare->id);
        $this->assertStringContainsString('Course of Bare Category', $html, 'Control: the page is drawn.');
        $this->assertStringNotContainsString('dim-pages', $html, 'No page to name, no strip.');
    }

    /**
     * The view toggle's parameter reaches the page: a list view is drawn and remembered; an unknown value is ignored.
     */
    public function test_the_view_parameter_switches_and_remembers_the_view(): void {
        $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);
        $renderer = $this->renderer();

        $_GET[category_toolbar::PARAM_VIEW] = category_toolbar::VIEW_LIST;
        $html = $renderer->course_category((int) $category->id);
        $this->assertStringContainsString('dim-vitrine--list', $html);
        $this->assertSame(category_toolbar::VIEW_LIST, get_user_preferences(category_listing::PREFERENCE_VIEW, null, $viewer));

        $_GET[category_toolbar::PARAM_VIEW] = 'bogus';
        $html = $renderer->course_category((int) $category->id);
        unset($_GET[category_toolbar::PARAM_VIEW]);
        $this->assertStringContainsString('dim-vitrine--list', $html, 'Control: the remembered view stands.');
        $this->assertStringNotContainsString('dim-vitrine--cards', $html);
    }

    /**
     * A category the plugin withholds falls back to core's page for the stranger, and shows the showcase to the member.
     *
     * Core's page is what the site rendered before this method existed, and
     * its own listing filter withholds the course from the stranger too; the
     * cohort member is the control that the fallback is the guard answering.
     */
    public function test_a_withheld_category_falls_back_to_core_for_the_stranger(): void {
        $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Withheld Category']);
        $generator->create_course(['category' => $category->id, 'fullname' => 'Withheld Course']);
        $this->mark_unlisted((int) $category->id);
        $member = $generator->create_user();
        $this->make_cohort_member((int) $category->id, (int) $member->id);
        $renderer = $this->renderer();

        $this->become($generator->create_user());
        $html = $renderer->course_category((int) $category->id);
        $this->assertStringNotContainsString('dim-vitrine', $html, 'The stranger gets core\'s page.');
        $this->assertStringNotContainsString('Withheld Course', $html, 'And core\'s page withholds the course too.');

        $this->become($member);
        $html = $renderer->course_category((int) $category->id);
        $this->assertStringContainsString('dim-vitrine', $html, 'Control: the member gets the showcase.');
        $this->assertStringContainsString('Withheld Course', $html);
    }

    /**
     * A hidden category is refused with core's own exception, never with the theme's.
     *
     * The message is captured from core itself rather than typed, so the
     * assertion says "the same refusal as core" whatever core's wording is;
     * the theme's own exception carries a different message.
     */
    public function test_a_hidden_category_keeps_cores_own_refusal(): void {
        $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Hidden Category', 'visible' => 0]);
        $generator->create_course(['category' => $category->id, 'visible' => 1]);
        $this->become($generator->create_user());
        $renderer = $this->renderer();

        $expected = null;
        try {
            core_course_category::get((int) $category->id);
        } catch (\moodle_exception $e) {
            $expected = $e->getMessage();
        }
        $this->assertNotNull($expected, 'Core refuses a hidden category to a student.');
        $this->assertNotSame(get_string('categoryunavailable', 'theme_dimensions'), $expected);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage($expected);
        $renderer->course_category((int) $category->id);
    }

    /**
     * Statement slope zero in the population, for the rendered page: two courses cost what two hundred cost.
     *
     * The showcase's heaviest real request - page one of the logged-in shell
     * through the renderer and the template engine - metered like
     * budget_test's own budget: a warmed page and renderer, every
     * per-request memo and cache reset, the shared MUC layers hot.
     */
    public function test_the_page_costs_the_same_statements_for_two_courses_and_for_two_hundred(): void {
        global $CFG;
        $viewer = $this->prepare_showcase();
        // The number below holds with the site default: with filterall on, the first format_string() in each
        // course's context loads that context's filters, one recordset per card.
        $this->assertEmpty($CFG->filterall, 'The render budget is the one of a site without filterall.');
        $small = $this->seed_showcase(2, $viewer);
        $large = $this->seed_showcase(200, $viewer);
        $this->become($viewer);
        /* The flat organisation, which the page reads off the address like any other: seed_showcase()
           spreads its courses over the root's children and the grouped page lists only the courses
           of the root itself - none - so the slope below would be a slope over an empty grid. What
           is metered is the cost of a page of cards, and this is the address that draws one. */
        $_GET[criteria::PARAM_SORT] = criteria::SORT_NAME;
        $renderer = $this->renderer();
        $renderer->course_category($small);
        $renderer->course_category($large);

        $smallreads = $this->reads(fn() => $renderer->course_category($small));
        $html = '';
        $seconds = 0.0;
        $memory = 0;
        $largereads = $this->reads(function () use ($renderer, $large, &$html, &$seconds, &$memory) {
            memory_reset_peak_usage();
            $before = memory_get_peak_usage();
            $start = microtime(true);
            $html = $renderer->course_category($large);
            $seconds = microtime(true) - $start;
            $memory = memory_get_peak_usage() - $before;
        });
        unset($_GET[criteria::PARAM_SORT]);
        // The page metered is the flat listing of all two hundred: the count line counts them.
        $name = format_string(\core_course_category::get($large)->name, true, ['escape' => false]);
        $this->assertStringContainsString('data-region="dim-vitrine-countnumber">200</b>', $html);
        $this->assertStringContainsString(s(get_string('category_countline', 'theme_dimensions', $name)), $html);
        $this->record_showcase('render at N', [
            'reads at N=2' => $smallreads,
            'reads at N=200' => $largereads,
            'ms at N=200' => round($seconds * 1000, 1),
            'peak KB at N=200' => intdiv($memory, 1024),
        ]);
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads at N=2 against {$largereads} at N=200.");
        // The strip is optional: its statement is counted only where local_page answers.
        $ceiling = self::RENDER_CEILING + (class_exists(category_pages::CATALOGUE) ? self::PAGES_READS : 0);
        $this->assertLessThanOrEqual($ceiling, $largereads);
        $this->assertLessThan(self::RENDER_SECONDS, $seconds);
        $this->assertLessThan(self::RENDER_MEMORY, $memory);
    }

    /**
     * Statement slope zero in the page size, for the rendered page: a page of four costs what a page of forty-eight costs.
     */
    public function test_the_page_costs_the_same_statements_for_a_page_of_four_and_of_forty_eight(): void {
        global $CFG;
        $viewer = $this->prepare_showcase();
        $this->assertEmpty($CFG->filterall, 'The render budget is the one of a site without filterall.');
        $root = $this->seed_showcase(100, $viewer);
        $this->become($viewer);
        // The flat organisation, for the reason the test above it gives.
        $_GET[criteria::PARAM_SORT] = criteria::SORT_NAME;
        $renderer = $this->renderer();
        $renderer->course_category($root);

        $_GET['perpage'] = 4;
        $fourreads = $this->reads(fn() => $renderer->course_category($root));
        $_GET['perpage'] = 48;
        $html = '';
        $fortyeightreads = $this->reads(function () use ($renderer, $root, &$html) {
            $html = $renderer->course_category($root);
        });
        unset($_GET['perpage'], $_GET[criteria::PARAM_SORT]);
        $this->assertSame(48, substr_count($html, 'data-region="dim-card"'), 'Forty-eight cards rendered.');
        $this->record_showcase('render at P', ['reads at P=4' => $fourreads, 'reads at P=48' => $fortyeightreads]);
        $this->assertSame($fourreads, $fortyeightreads, "{$fourreads} reads at P=4 against {$fortyeightreads} at P=48.");
    }

    /**
     * Seed a category of areas for the grouped page's render budget: one course each, the first holding nine.
     *
     * Every area holds a course, so every poster opens a panel; the first holds nine, so its panel
     * draws the rail's eight cards and the tile whatever the number of areas beside it. The areas'
     * descriptions are blanked: a described poster costs its description's reads by design, which
     * budget_test meters on its own (its DESCRIPTION_READS_PER_CARD).
     *
     * @param int $areas How many areas.
     * @return array The category id, then the id of the area whose panel is opened.
     */
    private function seed_areas(int $areas): array {
        global $DB;
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $openid = 0;
        for ($a = 0; $a < $areas; $a++) {
            $area = $generator->create_category(['parent' => $root->id]);
            $openid = $openid ?: (int) $area->id;
            for ($c = 0; $c < ($a === 0 ? 9 : 1); $c++) {
                $generator->create_course(['category' => $area->id]);
            }
        }
        $DB->set_field('course_categories', 'description', '', ['parent' => $root->id]);
        return [(int) $root->id, $openid];
    }

    /**
     * Statement slope zero in the posters, for the rendered grouped page with a panel open: three areas cost what twelve cost.
     *
     * The grouped page is the default, and what it renders past the population is a
     * poster per area (its visual keys and its shell, through the template) and, with a valid open
     * parameter, the panel's context and its cards. budget_test meters population::resolve() alone,
     * and the two tests above render the flat order; this is the page a reader gets. The panel is
     * the same eight cards in both runs, so a statement added per poster at render time - in the
     * poster's export, its shell or a template helper - is the one thing that can tell the two apart.
     */
    public function test_the_grouped_page_with_a_panel_open_costs_the_same_for_three_areas_and_for_twelve(): void {
        global $CFG;
        $viewer = $this->prepare_showcase();
        $this->assertEmpty($CFG->filterall, 'The render budget is the one of a site without filterall.');
        [$small, $smallopen] = $this->seed_areas(3);
        [$large, $largeopen] = $this->seed_areas(12);
        $this->become($viewer);
        $renderer = $this->renderer();
        // Both shapes warmed before either is metered, or the first one pays every cold cache.
        foreach ([$small => $smallopen, $large => $largeopen] as $categoryid => $openid) {
            $_GET[criteria::PARAM_OPEN] = $openid;
            $renderer->course_category($categoryid);
        }

        $_GET[criteria::PARAM_OPEN] = $smallopen;
        $smallhtml = '';
        $smallreads = $this->reads(function () use ($renderer, $small, &$smallhtml) {
            $smallhtml = $renderer->course_category($small);
        });
        $_GET[criteria::PARAM_OPEN] = $largeopen;
        $largehtml = '';
        $largereads = $this->reads(function () use ($renderer, $large, &$largehtml) {
            $largehtml = $renderer->course_category($large);
        });
        unset($_GET[criteria::PARAM_OPEN]);

        // The page metered is the one described: the posters of each size, and the same open panel on both.
        foreach ([[$smallhtml, 3, $smallopen], [$largehtml, 12, $largeopen]] as [$html, $count, $openid]) {
            $this->assertSame($count, substr_count($html, 'data-region="dim-area"'), "{$count} posters rendered.");
            $this->assertStringContainsString('data-region="dim-panel-section" data-categoryid="' . $openid . '"', $html);
            $this->assertStringContainsString('data-region="dim-panel-rail"', $html, 'The panel drew its rail.');
            $this->assertSame(8, substr_count($html, 'data-region="dim-card"'), 'Eight cards in the panel, and no other.');
        }
        $this->record_showcase('render grouped at K', ['reads at K=3' => $smallreads, 'reads at K=12' => $largereads]);
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads at K=3 against {$largereads} at K=12.");
    }

    /**
     * Seed a category of areas for the narrowed page's render budget: every third area misses the search.
     *
     * Each area holds one course, named for the search to match or miss, and one course matching
     * it sits in the category itself, so the section of the direct courses is drawn in both runs.
     * The areas' descriptions are blanked, as in {@see seed_areas()}.
     *
     * @param int $areas How many areas.
     * @return int The category id.
     */
    private function seed_searched_areas(int $areas): int {
        global $DB;
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        for ($a = 0; $a < $areas; $a++) {
            $area = $generator->create_category(['parent' => $root->id]);
            $generator->create_course(['category' => $area->id, 'fullname' => ($a % 3 === 2 ? 'Transito ' : 'Incendio ') . $a]);
        }
        $generator->create_course(['category' => $root->id, 'fullname' => 'Incendio direto']);
        $DB->set_field('course_categories', 'description', '', ['parent' => $root->id]);
        return (int) $root->id;
    }

    /**
     * Statement slope zero in the posters, for the rendered grouped page under a search: three areas cost what twelve cost.
     *
     * A narrowed page draws its posters with their pills, hides the zero ones and names them in
     * the summary after the grids, and composes the count line from the accordion - all of it
     * from numbers already in hand. So a statement added per poster at render time, in the
     * pill's words, the summary's entries or the count line, is the one thing that can tell the
     * two runs apart. The assertions on each rendered page keep it the page described.
     */
    public function test_the_grouped_page_under_a_search_costs_the_same_for_three_areas_and_for_twelve(): void {
        global $CFG;
        $viewer = $this->prepare_showcase();
        $this->assertEmpty($CFG->filterall, 'The render budget is the one of a site without filterall.');
        $small = $this->seed_searched_areas(3);
        $large = $this->seed_searched_areas(12);
        $this->become($viewer);
        $renderer = $this->renderer();
        $_GET[criteria::PARAM_SEARCH] = 'Incendio';
        $renderer->course_category($small);
        $renderer->course_category($large);

        $smallhtml = '';
        $smallreads = $this->reads(function () use ($renderer, $small, &$smallhtml) {
            $smallhtml = $renderer->course_category($small);
        });
        $largehtml = '';
        $largereads = $this->reads(function () use ($renderer, $large, &$largehtml) {
            $largehtml = $renderer->course_category($large);
        });
        unset($_GET[criteria::PARAM_SEARCH]);

        foreach ([[$smallhtml, 3, 1], [$largehtml, 12, 4]] as [$html, $count, $zero]) {
            $this->assertSame($count, substr_count($html, 'data-region="dim-area"'), "{$count} posters rendered.");
            $this->assertSame($count, substr_count($html, 'data-region="dim-poster-pill">'), 'Each wearing its pill.');
            $this->assertSame(
                $zero,
                substr_count($html, '<li class="dim-areas__item" hidden>'),
                "{$zero} zero posters left the grid."
            );
            $this->assertStringContainsString('data-region="dim-areas-nomatch"', $html, 'Named in the summary.');
            $this->assertDoesNotMatchRegularExpression('/data-region="dim-areas-nomatch"[^>]* hidden>/', $html);
            $this->assertStringContainsString('data-region="dim-vitrine-seeall">', $html, 'The count line offers the list.');
        }
        $this->record_showcase('render grouped under a search at K', [
            'reads at K=3' => $smallreads,
            'reads at K=12' => $largereads,
        ]);
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads at K=3 against {$largereads} at K=12.");
    }

    /**
     * The root renders its search line and the directory as posters, and arms the account's client for a real account.
     *
     * Core calls course_category(0) whenever the viewer may see more than one top-level category,
     * and a course can never sit directly in the top-level pseudo-category - so at the root the
     * page is a directory of categories. The withheld branch is the withholding control and the
     * open one the rendering control, so a directory that had rendered nothing could not pass.
     *
     * Its posters open panels like any other page's and its search line is live, so a real account
     * gets the account's client there (listingaccount, which replaced the panel's and the listing's
     * modules). The guest gets none, anywhere: the showcase's web services refuse it, and it
     * opens a panel through the address instead. The client is asserted through the page's own
     * requirements (`$PAGE->requires->get_end_code()` names every AMD call of the page), and a real
     * category page rendered beside it is the control: the same viewer, the same client.
     */
    public function test_the_root_renders_its_search_line_and_the_directory_and_arms_the_accounts_client(): void {
        global $PAGE;
        $viewer = $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $open = $generator->create_category(['name' => 'Open category']);
        $withheld = $generator->create_category(['name' => 'Restricted category']);
        $generator->create_course(['category' => $open->id, 'fullname' => 'Visible Course']);
        $generator->create_course(['category' => $withheld->id]);
        $this->mark_unlisted((int) $withheld->id);
        $this->become($viewer);
        $renderer = $this->renderer();

        $html = $renderer->course_category(0);

        $this->assertStringContainsString('dim-vitrine--directory', $html);
        $this->assertStringContainsString('data-region="dim-areas"', $html, 'The directory is the areas.');
        $this->assertStringContainsString('Open category', $html);
        $this->assertStringNotContainsString('Restricted category', $html, 'A withheld branch is no poster.');
        $this->assertStringContainsString('dim-toolbar dim-toolbar--searchonly', $html, 'The root\'s search line.');
        $this->assertStringNotContainsString('data-region="dim-filters"', $html, 'The directory offers no filters.');
        $this->assertStringNotContainsString('data-region="dim-card"', $html, 'No course card at the root.');
        $this->assertSame(0, category_hero::current()?->category_id(), 'The hero is armed for the root.');

        $requirements = $this->page_requirements();
        $this->assertStringContainsString(
            'theme_dimensions/listingaccount',
            $requirements,
            'The root\'s posters open panels and its search line is live: the account\'s client is armed for a real account.'
        );

        // The control: the same viewer on a real category gets the listing and its client.
        $PAGE = new \moodle_page();
        $PAGE->set_url('/course/index.php', ['categoryid' => (int) $open->id]);
        $this->renderer()->course_category((int) $open->id);
        $this->assertStringContainsString('theme_dimensions/listingaccount', $this->page_requirements());

        // The guest, on the same category page: no client, and the page still renders.
        $this->setGuestUser();
        self::forget();
        $PAGE = new \moodle_page();
        $PAGE->set_url('/course/index.php', ['categoryid' => (int) $open->id]);
        $guest = $this->renderer()->course_category((int) $open->id);
        $this->assertStringContainsString('data-region="dim-vitrine"', $guest, 'Precondition: the guest gets the showcase.');
        $this->assertStringNotContainsString('theme_dimensions/listingaccount', $this->page_requirements());
    }

    /**
     * An open panel at the root is drawn by the server, for the reader without the module.
     *
     * The root reads its panel and its search off the address and nothing else, so the panel is
     * the open poster's, narrowed by the term - and by nothing else beside it.
     */
    public function test_the_root_draws_the_panel_its_address_names(): void {
        $viewer = $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $open = $generator->create_category(['name' => 'Open category']);
        $generator->create_course(['category' => $open->id, 'fullname' => 'Visible Course']);
        $generator->create_course(['category' => $open->id, 'fullname' => 'Another Course']);
        $this->become($viewer);

        $_GET[criteria::PARAM_OPEN] = (int) $open->id;
        $_GET[criteria::PARAM_SEARCH] = 'Visible';
        $_GET[criteria::PARAM_SORT] = criteria::SORT_NAME;
        $html = $this->renderer()->course_category(0);
        unset($_GET[criteria::PARAM_OPEN], $_GET[criteria::PARAM_SEARCH], $_GET[criteria::PARAM_SORT]);

        $this->assertStringContainsString('data-region="dim-panel-section"', $html, 'The panel is drawn.');
        $this->assertStringContainsString('Visible Course', $html);
        $this->assertStringNotContainsString('Another Course', $html, 'The root\'s search term narrows its panel.');
    }

    /**
     * A filter on the address of the root changes nothing and travels no further; its search does both.
     *
     * The root draws a search line and no other control that narrows, so a chip or a field filter
     * arriving on the query string would change which cards are drawn with nothing on the page
     * admitting it or undoing it. population::resolve() ignores such criteria at the root; this is the
     * renderer's half of the same rule, and it is the half that keeps the phantom filter out of the
     * hero, whose fold form replays the request's own parameters - which is why the assertion is on
     * the fold and not only on the cards. The term is the one criterion the root reads: it travels
     * with the fold, and a term that matches nothing sends every poster of the directory to the
     * summary, each still in the document.
     *
     * The control is at the end: the same filter IS a real one, and empties the category page
     * below, where there is a control for it and a link to clear it.
     */
    public function test_a_filter_on_the_root_narrows_nothing_and_its_search_reaches_the_fold(): void {
        global $PAGE;
        $viewer = $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $open = $generator->create_category(['name' => 'Open category']);
        $generator->create_course(['category' => $open->id, 'fullname' => 'Visible Course']);
        $generator->create_course(['category' => $open->id, 'fullname' => 'Another Course']);
        $this->become($viewer);
        $renderer = $this->renderer();
        $PAGE->set_secondary_navigation(false);

        $plain = $renderer->course_category(0);

        // A chip and an organisation, spelled the way the category toolbar spells them.
        $_GET[criteria::PARAM_STATE] = classifier::STATE_ENROLLED;
        $_GET[criteria::PARAM_SORT] = criteria::SORT_NAME;
        $filtered = $renderer->course_category(0);
        $fold = category_hero::current()?->export_for_template($PAGE->get_renderer('core'))->fold;

        $this->assertStringContainsString('Open category', $filtered, 'The card is still drawn.');
        $drawn = $this->directory_cards($plain);
        $this->assertNotEmpty($drawn, 'There are cards to compare: an empty directory would compare equal to anything.');
        $this->assertSame(
            $drawn,
            $this->directory_cards($filtered),
            'The root draws the cards it draws without a query string, in the order it draws them.'
        );
        $this->assertNotNull($fold, 'The root draws the fold control, so there is a form to inspect.');
        $this->assertSame([], $fold['params'], 'No phantom filter travels with the root\'s fold form.');

        // The term, beside them, is read: it travels with the fold alone, and empties every poster.
        $_GET[criteria::PARAM_SEARCH] = 'zzzznothing';
        $searched = $renderer->course_category(0);
        $fold = category_hero::current()?->export_for_template($PAGE->get_renderer('core'))->fold;
        $this->assertSame(
            [criteria::PARAM_SEARCH => 'zzzznothing'],
            array_column($fold['params'], 'value', 'name'),
            'The fold keeps the root\'s search, and nothing else of the address.'
        );
        $this->assertSame($drawn, $this->directory_cards($searched), 'Every poster is still in the document.');
        $this->assertStringContainsString('data-filtered="1"', $searched, 'In result mode.');
        unset($_GET[criteria::PARAM_SEARCH], $_GET[criteria::PARAM_STATE], $_GET[criteria::PARAM_SORT]);

        // The control: the same organisation and search ARE real where the page offers controls for them.
        $_GET[criteria::PARAM_SEARCH] = 'zzzznothing';
        $below = $renderer->course_category((int) $open->id);
        unset($_GET[criteria::PARAM_SEARCH]);
        $this->assertStringNotContainsString('Visible Course', $below, 'The search term narrows a category page.');
    }

    /**
     * The category ids of a rendered directory's cards, in document order.
     *
     * The poster's OWN attribute, never the section's: the regions wrapping the
     * whole directory carry `data-categoryid="0"` too, and a query that
     * collected them would report entries on a page with no poster at all.
     *
     * @param string $html The rendered page.
     * @return array One entry per card.
     */
    private function directory_cards(string $html): array {
        preg_match_all('~data-region="dim-area" data-categoryid="(\d+)"~', $html, $matches);
        return $matches[1];
    }

    /**
     * A visitor and the guest account are refused the root's directory on core's course index.
     *
     * This renderer draws the root for a real account only; the two viewer classes without a
     * logged-in showcase fall back to whatever core would have drawn (a visitor's public root,
     * where the site opens one, is served by category.php through request::handle()). The
     * visitor's half is asked of the decision rather than of a render, because core's own
     * course_category(0) throws a TypeError for a viewer whose user_top() is null - the state
     * /course/index.php answers with cannotviewcategory before the renderer is reached - so
     * rendering it would test core's error handling and not this guard. The guest's half is a
     * real render, and the logged-in render is the control of both.
     */
    public function test_the_root_directory_is_not_drawn_for_a_visitor_or_the_guest(): void {
        $viewer = $this->prepare_showcase();
        $generator = self::getDataGenerator();
        $category = $generator->create_category(['name' => 'Open category']);
        $generator->create_category();
        $generator->create_course(['category' => $category->id, 'fullname' => 'Visible Course']);

        $renderer = $this->renderer();
        $decision = new \ReflectionMethod($renderer, 'showcase_category');

        $this->become(null);
        $this->assertNull($decision->invoke($renderer, 0), 'A visitor is refused the root.');

        $this->setGuestUser();
        self::forget();
        $this->assertNull($decision->invoke($renderer, 0), 'The guest account is refused the root.');
        $this->assertStringNotContainsString('dim-vitrine--directory', $renderer->course_category(0));

        // The control: a real account on the same site gets the directory.
        $this->become($viewer);
        $this->assertNotNull($decision->invoke($renderer, 0));
        $this->assertStringContainsString('dim-vitrine--directory', $renderer->course_category(0));
    }

    /**
     * The visitor's live listing is never armed on the logged-in shell, for a real account or the guest.
     *
     * listingvisitor is armed by the anonymous shell alone (request::handle()), under its own switch.
     * The switch is open here, so the only thing keeping it off this shell is that nothing here arms it:
     * a real account gets the logged-in client, which is the control that the page's calls are read,
     * and the guest gets neither client.
     */
    public function test_the_visitors_live_listing_is_never_armed_on_the_logged_in_shell(): void {
        global $PAGE;
        $viewer = $this->prepare_showcase();
        set_config(\theme_dimensions\local\category\publicaccess::SETTING_CLIENT, '1', 'theme_dimensions');
        $open = self::getDataGenerator()->create_category(['name' => 'Open category']);
        self::getDataGenerator()->create_course(['category' => $open->id, 'fullname' => 'Visible Course']);

        $this->become($viewer);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/course/index.php', ['categoryid' => (int) $open->id]);
        $this->renderer()->course_category((int) $open->id);
        $requirements = $this->page_requirements();
        $this->assertStringContainsString('theme_dimensions/listingaccount', $requirements, 'The control.');
        $this->assertStringNotContainsString('theme_dimensions/listingvisitor', $requirements);

        $this->setGuestUser();
        self::forget();
        $PAGE = new \moodle_page();
        $PAGE->set_url('/course/index.php', ['categoryid' => (int) $open->id]);
        $guest = $this->renderer()->course_category((int) $open->id);
        $this->assertStringContainsString('data-region="dim-vitrine"', $guest, 'Precondition: the guest gets the showcase.');
        $this->assertStringNotContainsString('theme_dimensions/listingvisitor', $this->page_requirements());
        $this->assertStringNotContainsString('theme_dimensions/listingaccount', $this->page_requirements());
    }

    /**
     * Every AMD module the page has asked for so far, as core would write it into the document.
     *
     * @return string
     */
    private function page_requirements(): string {
        global $PAGE;
        return (string) $PAGE->requires->get_end_code();
    }
}
