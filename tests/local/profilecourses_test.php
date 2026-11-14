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
 * Theme Dimensions - Tests for the course list of a user profile
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_user\output\myprofile\category;
use core_user\output\myprofile\manager;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use local_unlistedcourses\access;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\output\core_user\myprofile_renderer;

/**
 * Tests for the course list of a user profile, through the tree core builds and the theme's renderer.
 *
 * Every test that says a course is not named carries its control: core's own node names that course,
 * and the plugin's predicate refuses it to that viewer.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(profilecourses::class)]
#[CoversClass(myprofile_renderer::class)]
final class profilecourses_test extends \advanced_testcase {
    /**
     * Forget the plugin's per-request answers: PHPUnit restores the sequences, so one test's
     * course id is the next test's.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        access::reset_caches();
    }

    /**
     * Drop the request parameter a test set.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_GET['showallcourses']);
        access::reset_caches();
        parent::tearDown();
    }

    /**
     * Put the page on the theme, once: building a tree initialises the page's theme (core's badge
     * callback asks for a renderer), and a theme cannot be forced after that.
     *
     * @return void
     */
    private function on_the_theme(): void {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/user/profile.php');
        $PAGE->force_theme('dimensions');
    }

    /**
     * The theme's profile renderer.
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        global $PAGE;

        $renderer = $PAGE->get_renderer('core_user', 'myprofile');
        $this->assertInstanceOf(myprofile_renderer::class, $renderer);
        return $renderer;
    }

    /**
     * A course with a name no other course's name contains, unlisted on request.
     *
     * @param string $name The full name.
     * @param bool $unlisted Whether to unlist it.
     * @param array $record More course fields.
     * @return \stdClass The course.
     */
    private function course(string $name, bool $unlisted = false, array $record = []): \stdClass {
        global $USER;

        $course = $this->getDataGenerator()->create_course(['fullname' => $name] + $record);
        if ($unlisted) {
            $viewer = $USER;
            $this->setAdminUser();
            discoverability::set_state((int) $course->id, discoverability::STATE_UNLISTED);
            $this->setUser($viewer);
            access::reset_caches();
        }
        return $course;
    }

    /**
     * Core's own content of the course list node, for the current viewer.
     *
     * @param tree $tree The tree.
     * @return string|null The node's HTML, null when core added no such node.
     */
    private function core_content(tree $tree): ?string {
        $nodes = $tree->nodes;
        return isset($nodes[profilecourses::NODE]) ? (string) $nodes[profilecourses::NODE]->content : null;
    }

    /**
     * The items of a course list: one [li class, link address, link class, link title, text] per item.
     *
     * @param string $html A list.
     * @return array The items.
     */
    private function items(string $html): array {
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        $items = [];
        foreach ($dom->getElementsByTagName('li') as $li) {
            $link = $li->getElementsByTagName('a')->item(0);
            $items[] = [
                $li->getAttribute('class'),
                $link ? $link->getAttribute('href') : null,
                $link ? $link->getAttribute('class') : null,
                $link ? $link->getAttribute('title') : null,
                trim($li->textContent),
            ];
        }
        return $items;
    }

    /**
     * The course list the theme prints for a tree, taken out of the rendered profile.
     *
     * @param tree $tree The tree.
     * @return string The list's HTML, '' when the profile prints none.
     */
    private function rendered_list(tree $tree): string {
        $html = $this->renderer()->render($tree);
        if (!preg_match('~<dd class="dimensions-profile-fact-value">(<ul>.*?</ul>)</dd>~s', $html, $matches)) {
            return '';
        }
        return $matches[1];
    }

    /**
     * A viewer the plugin excludes from an unlisted course is not told the profile's owner is in it.
     *
     * @return void
     */
    public function test_an_unlisted_course_is_not_named_to_a_viewer_the_plugin_excludes(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $listed = $this->course('Listed geography');
        $unlisted = $this->course('Unlisted cartography', true);
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $generator->enrol_user($owner->id, $listed->id);
        $generator->enrol_user($owner->id, $unlisted->id);
        $generator->enrol_user($viewer->id, $listed->id);
        $this->setUser($viewer);

        $tree = manager::build_tree($owner, false);

        // The control: core names the course, and the plugin refuses it to this viewer.
        $this->assertStringContainsString('Unlisted cartography', (string) $this->core_content($tree));
        $this->assertFalse(access::is_course_discoverable((int) $unlisted->id));

        $html = $this->renderer()->render($tree);

        $this->assertStringNotContainsString('Unlisted cartography', $html);
        $this->assertStringNotContainsString('course=' . $unlisted->id, $html);
        $this->assertStringContainsString('Listed geography', $html);
        $this->assertStringContainsString('course=' . $listed->id, $html);
    }

    /**
     * A viewer the plugin admits (enrolled in the unlisted course too) reads core's list, byte for byte.
     *
     * @return void
     */
    public function test_an_unlisted_course_is_named_to_a_viewer_the_plugin_admits(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $listed = $this->course('Listed geography');
        $unlisted = $this->course('Unlisted cartography', true);
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $outsider = $generator->create_user();
        $generator->enrol_user($owner->id, $listed->id);
        $generator->enrol_user($owner->id, $unlisted->id);
        $generator->enrol_user($viewer->id, $unlisted->id);

        // The control: the course really is withheld from somebody else.
        $this->setUser($outsider);
        $this->assertFalse(access::is_course_discoverable((int) $unlisted->id));

        $this->setUser($viewer);
        $tree = manager::build_tree($owner, false);
        $html = $this->renderer()->render($tree);

        $this->assertStringContainsString('Unlisted cartography', $html);
        $this->assertStringContainsString((string) $this->core_content($tree), $html);
    }

    /**
     * The profile's owner reads their own unlisted enrolment on their own profile.
     *
     * @return void
     */
    public function test_the_owner_reads_their_own_unlisted_course(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $unlisted = $this->course('Unlisted cartography', true);
        $owner = $generator->create_user();
        $outsider = $generator->create_user();
        $generator->enrol_user($owner->id, $unlisted->id);

        $this->setUser($outsider);
        $this->assertFalse(access::is_course_discoverable((int) $unlisted->id));

        $this->setUser($owner);
        $tree = manager::build_tree($owner, true);
        $html = $this->renderer()->render($tree);

        $this->assertStringContainsString('Unlisted cartography', $html);
        $this->assertStringContainsString((string) $this->core_content($tree), $html);
    }

    /**
     * With no unlisted course on the profile, the list printed is core's own string.
     *
     * @return void
     */
    public function test_a_profile_of_listed_courses_prints_cores_list(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $first = $this->course('Listed geography');
        $second = $this->course('Listed geology');
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $generator->enrol_user($owner->id, $first->id);
        $generator->enrol_user($owner->id, $second->id);
        $this->setUser($viewer);

        $tree = manager::build_tree($owner, false);

        $this->assertNull(profilecourses::replacement($tree));
        $this->assertSame($this->core_content($tree), $this->rendered_list($tree));
    }

    /**
     * Without the plugin's predicate class core's list stands, unlisted course included.
     *
     * @return void
     */
    public function test_a_missing_predicate_leaves_cores_list(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $unlisted = $this->course('Unlisted cartography', true);
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $generator->enrol_user($owner->id, $unlisted->id);
        $this->setUser($viewer);

        $tree = manager::build_tree($owner, false);

        // The control: with the predicate the same tree is answered a replacement, an empty one.
        $this->assertSame(['courses' => [], 'viewmore' => false], profilecourses::replacement($tree));
        $this->assertNull(profilecourses::replacement($tree, '\\nonexistent\\cls'));
        // An unrecorded tree as well: nothing is withheld on a site without the plugin.
        $this->assertNull(profilecourses::replacement(null, '\\nonexistent\\cls'));
    }

    /**
     * A list with no course left takes its node, and a category it was alone in, off the page.
     *
     * @return void
     */
    public function test_an_emptied_list_prints_no_heading(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $unlisted = $this->course('Unlisted cartography', true);
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $mate = $generator->create_user();
        $generator->enrol_user($owner->id, $unlisted->id);
        $generator->enrol_user($mate->id, $unlisted->id);

        // The control: somebody enrolled in the course is shown the node and its category.
        $this->setUser($mate);
        $html = $this->renderer()->render(manager::build_tree($owner, false));
        $this->assertStringContainsString(get_string('courseprofiles'), $html);
        $this->assertStringContainsString(get_string('coursedetails'), $html);
        $this->assertStringContainsString('Unlisted cartography', $html);

        $this->setUser($viewer);
        $tree = manager::build_tree($owner, false);
        $this->assertStringContainsString('Unlisted cartography', (string) $this->core_content($tree));
        $html = $this->renderer()->render($tree);

        $this->assertStringNotContainsString('Unlisted cartography', $html);
        $this->assertStringNotContainsString(get_string('courseprofiles'), $html);
        $this->assertStringNotContainsString(get_string('coursedetails'), $html);
    }

    /**
     * The cut at navcourselimit counts the courses left, so a withheld course leaves no gap in the list.
     *
     * @return void
     */
    public function test_the_view_more_cut_counts_what_is_left(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $CFG->navcourselimit = 2;
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $courses = [];
        foreach (['Alpha survey', 'Bravo survey', 'Charlie survey', 'Delta survey'] as $name) {
            $course = $this->course($name);
            $generator->enrol_user($owner->id, $course->id);
            $courses[(int) $course->id] = $name;
        }
        // Unlist the course core lists first, so core's cut list is one of the two it shows.
        $firstid = (int) array_key_first(enrol_get_all_users_courses($owner->id, true, null));
        $this->setAdminUser();
        discoverability::set_state($firstid, discoverability::STATE_UNLISTED);
        access::reset_caches();
        $this->setUser($viewer);

        $tree = manager::build_tree($owner, false);
        $core = $this->items((string) $this->core_content($tree));
        $this->assertCount(3, $core);
        $this->assertSame($courses[$firstid], $core[0][4], 'The control: core names the unlisted course first.');
        $this->assertFalse(access::is_course_discoverable($firstid));

        $items = $this->items($this->rendered_list($tree));

        $this->assertCount(3, $items, 'Two courses and the link to the rest.');
        $this->assertSame('viewmore', $items[2][0]);
        $this->assertStringContainsString('/user/profile.php?id=' . $owner->id . '&showallcourses=1', $items[2][1]);
        $this->assertNotContains($courses[$firstid], array_column($items, 4));

        // The whole list: the three courses left, each link carrying the parameter, and no cut.
        $_GET['showallcourses'] = 1;
        $tree = manager::build_tree($owner, false);
        $items = $this->items($this->rendered_list($tree));

        $this->assertCount(3, $items);
        $this->assertNotContains('viewmore', array_column($items, 0));
        $this->assertNotContains($courses[$firstid], array_column($items, 4));
        foreach ($items as $item) {
            $this->assertStringContainsString('showallcourses=1', $item[1]);
        }
    }

    /**
     * The rebuilt list is core's list of the same profile without the withheld course, item for item.
     *
     * The list the theme prints in place of core's is a copy of core's loop. This compares it with
     * what core itself built for the same owner and viewer before the owner joined the unlisted
     * course, on the site profile, cut and whole, and inside a course: a core release that changes
     * the list's markup, its links or its cut fails here, and the copy follows it.
     *
     * @return void
     */
    public function test_the_rebuilt_list_is_cores_list_without_the_withheld_course(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->on_the_theme();
        $generator = $this->getDataGenerator();
        $CFG->navcourselimit = 3;
        $owner = $generator->create_user();
        $viewer = $generator->create_user();
        $inside = $this->course('Alpha survey');
        $generator->enrol_user($owner->id, $inside->id);
        $generator->enrol_user($viewer->id, $inside->id);
        foreach (['Bravo survey', 'Charlie survey', 'Delta survey'] as $name) {
            $generator->enrol_user($owner->id, $this->course($name)->id);
        }
        // A hidden course the viewer teaches: core prints it dimmed, and so must the copy.
        $hidden = $this->course('Echo survey', false, ['visible' => 0]);
        $generator->enrol_user($owner->id, $hidden->id);
        $generator->enrol_user($viewer->id, $hidden->id, 'editingteacher');
        // And one the viewer may not see at all.
        $generator->enrol_user($owner->id, $this->course('Foxtrot survey', false, ['visible' => 0])->id);
        $this->setUser($viewer);

        $modes = [
            'profile, cut' => [null, 0],
            'profile, whole' => [null, 1],
            'in a course, cut' => [$inside, 0],
            'in a course, whole' => [$inside, 1],
        ];
        $expected = [];
        foreach ($modes as $label => [$course, $showall]) {
            $_GET['showallcourses'] = $showall;
            $expected[$label] = $this->items((string) $this->core_content(manager::build_tree($owner, false, $course)));
        }
        $this->assertContains('dimmed', array_column($expected['profile, whole'], 2), 'The control: a dimmed course.');
        $this->assertContains('viewmore', array_column($expected['profile, cut'], 0), 'The control: a cut list.');

        $unlisted = $this->course('Unlisted cartography', true, ['sortorder' => 1]);
        $generator->enrol_user($owner->id, $unlisted->id);
        access::reset_caches();

        foreach ($modes as $label => [$course, $showall]) {
            $_GET['showallcourses'] = $showall;
            $tree = manager::build_tree($owner, false, $course);
            $this->assertStringContainsString('Unlisted cartography', (string) $this->core_content($tree), $label);
            $this->assertNotNull(profilecourses::replacement($tree), $label);
            $this->assertSame($expected[$label], $this->items($this->rendered_list($tree)), $label);
        }
    }

    /**
     * A course list whose tree nobody recorded is withheld: nothing says whose courses it names.
     *
     * @return void
     */
    public function test_an_unrecorded_course_list_is_withheld(): void {
        $this->resetAfterTest();
        $this->on_the_theme();
        $this->setUser($this->getDataGenerator()->create_user());

        $tree = new tree();
        $tree->add_category(new category('coursedetails', 'Course details'));
        $tree->add_node(new node('coursedetails', profilecourses::NODE, 'Course profiles', null, null, '<ul><li>Any</li></ul>'));
        $tree->add_node(new node('coursedetails', 'roles', 'Roles', null, null, 'Student'));
        $tree->sort_categories();

        $html = $this->renderer()->render($tree);

        $this->assertStringNotContainsString('<li>Any</li>', $html);
        $this->assertStringNotContainsString('Course profiles', $html);
        // The control: the node beside it prints.
        $this->assertStringContainsString('Student', $html);

        // On its own, outside any tree, the node is answered the same way.
        $this->assertSame('', $this->renderer()->render($tree->nodes[profilecourses::NODE]));
    }
}
