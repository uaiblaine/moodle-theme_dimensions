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
 * Theme Dimensions - Tests for the course notice's rendering and its place in the layout
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\coursenotice\builder;
use theme_dimensions\local\landingfields;

/**
 * Tests for the course notice as a page draws it: in place of Boost Union's hints, folded or not.
 *
 * The replacement is asserted on the whole page header() renders - Boost Union's layout file, this
 * theme's renderer - because the line that swaps the layout key is only visible there. Its control
 * is Boost Union's own function, asked in the same state, which does produce its card.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_notice::class)]
#[CoversClass(core_renderer::class)]
final class course_notice_test extends \advanced_testcase {
    /** @var string A marker only Boost Union's own navbar puts on the page: the page was drawn. */
    private const BU_NAVBAR_MARKER = 'id="usernavigation"';

    /**
     * Start with Boost Union's hints off and a configured router.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/theme/boost_union/lib.php');
        require_once($CFG->dirroot . '/theme/boost_union/locallib.php');
        $this->resetAfterTest();
        $CFG->routerconfigured = true;
        \core\di::reset_container();
        set_config('showhintcoursehidden', THEME_BOOST_UNION_SETTING_SELECT_NO, builder::BU);
        \local_unlistedcourses\discoverability::reset_caches();
        \local_unlistedcourses\access::reset_caches();
        \local_unlistedcourses\category_access::reset_caches();
    }

    /**
     * Leave no router built under this test's setting behind.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * A course, optionally opted into a landing page model.
     *
     * @param int $model The page model (0 = none).
     * @param array $options Extra options for the course generator.
     * @return \stdClass The course record.
     */
    private function course(int $model = 0, array $options = []): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course($options);
        if ($model) {
            \core_course\customfield\course_handler::create()->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => $model,
            ]);
        }
        return get_course($course->id);
    }

    /**
     * A user enrolled in the course with a role, set as the current user.
     *
     * @param \stdClass $course The course.
     * @param string $role The role's shortname.
     * @return void
     */
    private function log_in_as(\stdClass $course, string $role): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        $this->setUser($user);
    }

    /**
     * This theme's core renderer on the course page of a course, bound to a new global page.
     *
     * The order is core_renderer_test's: the course before the theme, the explicit target, and the
     * global $OUTPUT, because Boost Union's layout file - which header() includes - renders through it.
     *
     * @param \stdClass $course The course.
     * @return core_renderer The renderer.
     */
    private function renderer(\stdClass $course): core_renderer {
        global $PAGE, $OUTPUT;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\core\context\course::instance($course->id));
        $PAGE->set_course($course);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $PAGE->set_pagelayout('course');
        $PAGE->set_title('Rendered by the course notice test');
        $PAGE->set_heading($course->fullname);
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);
        return $OUTPUT;
    }

    /**
     * The layout prints the notice where Boost Union's hints were, and Boost Union's card never.
     *
     * @return void
     */
    public function test_the_layout_prints_the_notice_in_place_of_boost_unions_hints(): void {
        set_config('showhintcoursehidden', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $course = $this->course(0, ['visible' => 0]);
        $this->log_in_as($course, 'editingteacher');
        $renderer = $this->renderer($course);

        $this->assertStringContainsString(
            'course-hint-hidden',
            theme_boost_union_get_course_related_hints(),
            'Control: Boost Union would print its card on this page.'
        );

        $html = $renderer->header();
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html);
        $this->assertStringNotContainsString('course-hint-hidden', $html);
        $this->assertStringContainsString('id="' . course_notice::ID_PREFIX . $course->id . '"', $html);
        $this->assertStringContainsString('data-key="' . builder::KEY_HIDDEN . '"', $html);
        $this->assertStringContainsString(get_string('showhintcoursehiddengeneral', builder::BU), $html);
    }

    /**
     * Where the notice says nothing the layout prints nothing - and where Boost Union would have, not its card either.
     *
     * @return void
     */
    public function test_a_page_with_nothing_to_say_prints_no_notice(): void {
        $course = $this->course();
        $this->log_in_as($course, 'student');
        $html = $this->renderer($course)->header();
        $this->assertStringContainsString(self::BU_NAVBAR_MARKER, $html, 'Control: the page was drawn.');
        $this->assertStringNotContainsString('dim-coursenotice', $html);
        $this->assertStringNotContainsString('course-hint-', $html);
    }

    /**
     * An editor's promotion section is drawn unfolded by default, with the addresses and the fold control.
     *
     * @return void
     */
    public function test_the_promotion_section_is_drawn_unfolded_by_default(): void {
        $course = $this->course(landingfields::MODEL_COURSE);
        $this->log_in_as($course, 'editingteacher');
        $html = $this->renderer($course)->course_notice();

        $uniqid = course_notice::ID_PREFIX . $course->id;
        $this->assertStringContainsString('dim-coursenotice--promote', $html);
        $this->assertStringContainsString('aria-expanded="true"', $html);
        $this->assertStringContainsString('aria-controls="' . $uniqid . '-detail"', $html);
        $this->assertMatchesRegularExpression('~id="' . $uniqid . '-detail" data-region="dim-coursenotice-detail">~', $html);
        $this->assertMatchesRegularExpression('~data-region="dim-coursenotice-quick" hidden>~', $html);
        $this->assertStringContainsString('id="' . $uniqid . '-landing"', $html);
        $this->assertStringContainsString('data-clipboard-target="#' . $uniqid . '-landing"', $html);
        $this->assertStringContainsString('data-canpersist="1"', $html);
        $this->assertStringContainsString(get_string('coursenotice_promotitle', landingfields::COMPONENT), $html);
    }

    /**
     * The fold is the person's: folded, the detail is hidden and the quick copy buttons are shown.
     *
     * @return void
     */
    public function test_a_folded_promotion_section_keeps_its_bar(): void {
        $course = $this->course(landingfields::MODEL_COURSE);
        $this->log_in_as($course, 'editingteacher');
        set_user_preference(course_notice::PREFERENCE_COLLAPSED, 1);
        $html = $this->renderer($course)->course_notice();

        $uniqid = course_notice::ID_PREFIX . $course->id;
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertMatchesRegularExpression('~id="' . $uniqid . '-detail" data-region="dim-coursenotice-detail" hidden>~', $html);
        $this->assertMatchesRegularExpression('~data-region="dim-coursenotice-quick">~', $html);
    }

    /**
     * A notice without the promotion section has no fold, whatever the preference says.
     *
     * @return void
     */
    public function test_a_learners_notice_is_never_folded(): void {
        set_config('showhintcoursehidden', THEME_BOOST_UNION_SETTING_SELECT_YES, builder::BU);
        $course = $this->course(0, ['visible' => 0]);
        $this->log_in_as($course, 'teacher');
        set_user_preference(course_notice::PREFERENCE_COLLAPSED, 1);
        $html = $this->renderer($course)->course_notice();

        $this->assertStringContainsString('data-key="' . builder::KEY_HIDDEN . '"', $html, 'Control: the notice is drawn.');
        $this->assertStringNotContainsString('dim-coursenotice-toggle', $html);
        $this->assertStringNotContainsString(' hidden>', $html);
        $this->assertStringContainsString(get_string('coursenotice_title', landingfields::COMPONENT), $html);
    }
}
