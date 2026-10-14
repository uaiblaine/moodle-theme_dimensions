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
 * Theme Dimensions - Tests for the my profile renderer
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output\core_user;

use core_user\output\myprofile\category;
use core_user\output\myprofile\node;
use core_user\output\myprofile\tree;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the my profile renderer.
 *
 * These are contract tests, not layout tests. What they pin is what a plugin contributing a node
 * is allowed to hand over, because that is what a replacement renderer can silently break: the
 * plugin keeps working, the tree keeps building, and only the pixels are wrong.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(myprofile_renderer::class)]
final class myprofile_renderer_test extends \advanced_testcase {
    /**
     * Build a renderer for this theme.
     *
     * @return \renderer_base The theme's my profile renderer.
     */
    private function renderer() {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/user/profile.php');
        $PAGE->force_theme('dimensions');

        return $PAGE->get_renderer('core_user', 'myprofile');
    }

    /**
     * The factory really does hand over this theme's renderer.
     *
     * The class name a theme override must carry is derived, not declared, so a file in the wrong
     * directory or a namespace off by one level fails by silently doing nothing at all: core's
     * renderer answers and the page looks merely unstyled.
     *
     * @return void
     */
    public function test_the_theme_renderer_is_the_one_the_factory_returns(): void {
        $this->resetAfterTest();

        $this->assertInstanceOf(myprofile_renderer::class, $this->renderer());
    }

    /**
     * A node title carrying markup is rendered as markup, exactly as core renders it.
     *
     * Core passes titles through html_writer::link() and html_writer::tag(), neither of which
     * escapes ({@see \core_user\output\myprofile\renderer::render_node()}), so raw HTML is the
     * contract even though most titles are language strings. tool_muloginas relies on it: it
     * renders its whole "Log in as" button and passes it as the title, with no content and no url
     * (tool/muloginas/lib.php), and an escaping renderer prints that button as literal text.
     *
     * @return void
     */
    public function test_a_node_title_may_carry_markup(): void {
        $this->resetAfterTest();

        $tree = new tree();
        $tree->add_category(new category('administration', 'Administration'));
        $tree->add_node(new node('administration', 'muloginas', '<button id="loginas">Log in as</button>'));
        $tree->sort_categories();

        $html = $this->renderer()->render($tree);

        $this->assertStringContainsString('<button id="loginas">Log in as</button>', $html);
        $this->assertStringNotContainsString('&lt;button', $html);
    }

    /**
     * Node content carrying markup is rendered as markup too.
     *
     * The content half of the same contract: core prints node content unescaped as well
     * ({@see \core_user\output\myprofile\renderer::render_node()}).
     *
     * @return void
     */
    public function test_node_content_may_carry_markup(): void {
        $this->resetAfterTest();

        $tree = new tree();
        $tree->add_category(new category('coursedetails', 'Course details'));
        $tree->add_node(new node(
            'coursedetails',
            'courseprofiles',
            'Course profiles',
            null,
            null,
            '<ul><li>Human rights</li></ul>'
        ));
        $tree->sort_categories();

        $html = $this->renderer()->render($tree);

        $this->assertStringContainsString('<ul><li>Human rights</li></ul>', $html);
        $this->assertStringNotContainsString('&lt;ul&gt;', $html);
    }

    /**
     * A node with content becomes a fact, and a node with only a link becomes an action.
     *
     * This is the whole information-architecture change over core, which renders both into one
     * flat list. If the split stopped working the page would still show everything, in one
     * undifferentiated column - a regression nothing else here would notice.
     *
     * @return void
     */
    public function test_facts_and_actions_are_separated(): void {
        $this->resetAfterTest();

        $tree = new tree();
        $tree->add_category(new category('contact', 'User details'));
        $tree->add_node(new node('contact', 'email', 'Email address', null, null, 'someone@example.org'));
        $tree->add_node(new node('contact', 'editprofile', 'Edit profile', null, new \moodle_url('/user/edit.php')));
        $tree->sort_categories();

        $html = $this->renderer()->render($tree);

        $this->assertMatchesRegularExpression(
            '~<dt class="dimensions-profile-fact-label">\s*Email address~',
            $html
        );
        $this->assertMatchesRegularExpression(
            '~<li class="dimensions-profile-action[^"]*"><a href="[^"]*/user/edit\.php">Edit profile</a>~',
            $html
        );
    }

    /**
     * A category holding no node prints nothing at all.
     *
     * An empty card headed "User details" would be a statement about the person - it says there
     * is something here you are not being shown - which is the opposite of what an empty category
     * means, and the opposite of what core does with one.
     *
     * @return void
     */
    public function test_an_empty_category_prints_nothing(): void {
        $this->resetAfterTest();

        $tree = new tree();
        $tree->add_category(new category('contact', 'User details'));
        $tree->add_category(new category('reports', 'Reports', 'contact'));
        $tree->add_node(new node('contact', 'email', 'Email address', null, null, 'someone@example.org'));
        $tree->sort_categories();

        $html = $this->renderer()->render($tree);

        // The control: the category that does hold a node is printed.
        $this->assertStringContainsString('User details', $html);
        $this->assertStringNotContainsString('Reports', $html);
    }

    /**
     * auth_loginsteps' Security category is drawn before every other, wherever it was added.
     *
     * A category with no "after" is appended where its plugin's callback added it, which is after
     * core's own; the renderer is the one place that can put what the owner comes to change first.
     * The control is the order the tree itself holds.
     *
     * @return void
     */
    public function test_the_security_area_is_drawn_first(): void {
        $this->resetAfterTest();

        $tree = new tree();
        $tree->add_category(new category('contact', 'User details'));
        $tree->add_category(new category('loginsteps_security', 'Security', null, 'auth-loginsteps-security'));
        $tree->add_node(new node('contact', 'email', 'Email address', null, null, 'someone@example.org'));
        $tree->add_node(new node('loginsteps_security', 'mfa', 'Two-step verification', null, null, '<p>Active</p>'));
        $tree->sort_categories();
        $this->assertSame(['contact', 'loginsteps_security'], array_keys($tree->categories), 'the control');

        $html = $this->renderer()->render($tree);

        $this->assertLessThan(strpos($html, 'User details'), strpos($html, 'Security'));
        $this->assertStringContainsString('dimensions-profile-card node_category auth-loginsteps-security', $html);
    }
}
