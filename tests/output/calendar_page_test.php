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
 * Theme Dimensions - Tests for the calendar page's renderable and template
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\filters;
use theme_dimensions\local\calendar\fixture;
use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\scope;
use theme_dimensions\local\calendar\state;
use theme_dimensions\local\calendar\tree;
use theme_dimensions\local\calendar\window;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/calendar/fixture.php');

/**
 * The page embeds the range's own answer, draws the month without JavaScript, and hands the client its strings.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(calendar_page::class)]
#[CoversClass(tree::class)]
final class calendar_page_test extends \advanced_testcase {
    use fixture;

    /** @var array The fixture's ids. */
    private array $ids = [];

    /**
     * A student in two courses of a category tree, with a ten-day event, a timed event and a user event next month.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = $generator->create_category(['name' => 'School & Government']);
        $child = $generator->create_category(['name' => 'Management', 'parent' => $parent->id]);
        $student = $generator->create_user();
        $course = $generator->create_course(['category' => $child->id, 'fullname' => 'Risk']);
        $generator->enrol_user($student->id, $course->id, 'student');
        $this->ids = [
            'student' => $student,
            'parent' => (int) $parent->id,
            'child' => (int) $child->id,
            'long' => (int) $generator->create_event([
                'name' => 'Enrolment week',
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(3, 0),
                'timeduration' => 10 * DAYSECS,
            ])->id,
            'timed' => (int) $generator->create_event([
                'name' => 'Mentoring <b>B</b>',
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(9, 14),
            ])->id,
        ];
    }

    /**
     * The renderable of next month, as page::handle() builds it.
     *
     * @param state $state The state.
     * @return array [calendar_page, window, answer].
     */
    private function build(state $state): array {
        $this->as_viewer($this->ids['student']);
        $month = self::month_start()->format('Y-m');
        [$from, $to] = page::grid($month, 0);
        $window = window::from_iso($from, $to);
        $scope = scope::for_viewer();
        $answer = range::answer($window, $scope, $state->on(), $state->filters());
        $renderable = new calendar_page(
            $month,
            self::month_start()->format('Y-m-d'),
            0,
            \core_date::get_user_timezone(),
            $window,
            $answer,
            $state,
            tree::for_scope($scope)
        );
        return [$renderable, $window, $answer];
    }

    /**
     * The props embed range::answer() itself for the grid's window, and the state and the tree beside it.
     *
     * @return void
     */
    public function test_the_props_embed_the_ranges_own_answer(): void {
        [$renderable, $window, $answer] = $this->build(new state(['user']));
        $props = $renderable->props();
        $this->assertSame($answer, $props['answer']);
        self::forget_calendar();
        $again = range::answer($window, scope::for_viewer(), (new state(['user']))->on(), new filters());
        $this->assertSame($again, $props['answer'], 'The same answer the service would give for the window.');
        $this->assertSame(['user'], $props['state']['off']);
        $this->assertSame($window->from, $props['answer']['from']);
        $this->assertEqualsCanonicalizing(
            [$this->ids['long'], $this->ids['timed']],
            array_column($props['answer']['events'], 'id')
        );
        $this->assertSame([$this->ids['parent'], $this->ids['child']], array_column($props['tree'], 'id'));
        $this->assertSame([0, $this->ids['parent']], array_column($props['tree'], 'parent'));
        $this->assertSame([0, 1], array_column($props['tree'], 'courses'));
    }

    /**
     * The template draws the mount, the GET form and the month as an agenda, each item once, escaped once.
     *
     * @return void
     */
    public function test_the_template_draws_the_month_without_javascript(): void {
        global $PAGE;
        [$renderable] = $this->build(new state(['group']));
        $html = $PAGE->get_renderer('core')->render($renderable);

        $this->assertSame(1, substr_count($html, 'data-react-component="' . calendar_page::COMPONENT . '"'));
        $this->assertSame(1, substr_count($html, '>Enrolment week</a>'), 'The ten-day event is listed once.');
        $this->assertStringContainsString('>Mentoring B</a>', $html, 'Plain on the way in, escaped once.');
        $this->assertStringNotContainsString('<b>B</b>', $html);
        $this->assertStringContainsString('<input type="hidden" name="keys[]" value="">', $html);
        $this->assertMatchesRegularExpression('~<input type="checkbox" name="keys\[\]" value="site" checked>~', $html);
        $this->assertMatchesRegularExpression('~<input type="checkbox" name="keys\[\]" value="group">~', $html, 'A key off.');
        $this->assertStringContainsString('&nbsp;&nbsp;Management', str_replace("\u{00A0}", '&nbsp;', $html));
    }

    /**
     * Every string the client reads exists in both languages, and is the list the client names.
     *
     * @return void
     */
    public function test_the_clients_strings(): void {
        $manager = get_string_manager();
        foreach (calendar_page::STRINGS as $key) {
            $this->assertTrue($manager->string_exists($key, 'theme_dimensions'), $key);
        }
        $source = file_get_contents(dirname(__DIR__, 2) . '/js/esm/src/calendar/strings.ts');
        preg_match('~export const KEYS = \[(.*?)\] as const;~s', $source, $list);
        preg_match_all("~'([a-z_]+)'~", $list[1], $names);
        $this->assertSame(calendar_page::STRINGS, $names[1]);
    }

    /**
     * A key's name is one literal string per key.
     *
     * @return void
     */
    public function test_every_key_has_its_name(): void {
        $names = array_map([calendar_page::class, 'key_label'], keys::ALL);
        $this->assertCount(count(keys::ALL), array_unique($names));
    }

    /**
     * The tree names no category core hides from the viewer, nor one local_unlistedcourses withholds, even where
     * one of the viewer's courses sits; the visible categories beside them are the control.
     *
     * @return void
     */
    public function test_the_tree_names_only_what_the_viewer_may_see(): void {
        global $DB;
        $generator = self::getDataGenerator();
        $hidden = $generator->create_category(['name' => 'Hidden area']);
        $unlisted = $generator->create_category(['name' => 'Unlisted area']);
        foreach ([$hidden, $unlisted] as $category) {
            $course = $generator->create_course(['category' => $category->id]);
            $generator->enrol_user($this->ids['student']->id, $course->id, 'student');
        }
        $DB->set_field('course_categories', 'visible', 0, ['id' => $hidden->id]);
        \cache_helper::purge_by_event('changesincoursecat');
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $unlisted->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED,
            (int) get_admin()->id
        );

        $this->as_viewer($this->ids['student']);
        $ids = array_column(tree::for_scope(scope::for_viewer()), 'id');
        $this->assertContains($this->ids['child'], $ids, 'The control: the visible tree is named.');
        $this->assertNotContains((int) $hidden->id, $ids);
        $this->assertNotContains((int) $unlisted->id, $ids);
    }
}
