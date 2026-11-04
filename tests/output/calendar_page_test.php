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
use theme_dimensions\local\landingfields;

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
     * The model filter offers the trail line only while mod_subcourse is enabled.
     *
     * @return void
     */
    public function test_the_model_filter_leaves_the_trail_out_without_the_module(): void {
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        // Another test of this process may have read the select with other lines.
        landingfields::reset_field_cache();
        [$renderable] = $this->build(new state([]));
        $this->assertContains(
            landingfields::MODEL_TRAIL,
            array_column($renderable->props()['options']['model'], 'value'),
            'Precondition: offered with the module.'
        );

        \core\plugininfo\mod::enable_plugin('subcourse', 0);

        $values = array_column($renderable->props()['options']['model'], 'value');
        $this->assertNotContains(landingfields::MODEL_TRAIL, $values);
        $this->assertContains(landingfields::MODEL_EVENT, $values, 'The other lines stay.');
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
     * The first paint's placeholder: one per page, empty of text and hidden from assistive
     * technology, before the mount node and after the server's layer, in the month's shape and in the week's.
     *
     * @return void
     */
    public function test_the_template_draws_the_placeholder_between_the_server_layer_and_the_mount(): void {
        global $PAGE;
        [$renderable] = $this->build(new state(['group']));
        $html = $PAGE->get_renderer('core')->render($renderable);

        $this->assertSame(1, substr_count($html, 'data-region="dim-cal-placeholder"'));
        $this->assertMatchesRegularExpression(
            '~<div class="dim-cal__placeholder dim-cal__placeholder--month" data-region="dim-cal-placeholder" '
            . 'aria-hidden="true">\s*<div class="dim-cal__ghosttools"></div>\s*<div class="dim-cal__ghostframe">'
            . '\s*<div class="dim-cal__ghosthead"></div>'
            . '\s*<div class="dim-cal__ghostbody"></div>\s*</div>\s*</div>~',
            $html
        );
        $server = strpos($html, 'data-region="dim-cal-server"');
        $placeholder = strpos($html, 'data-region="dim-cal-placeholder"');
        $mount = strpos($html, 'data-region="dim-cal-mount"');
        $this->assertTrue($server < $placeholder && $placeholder < $mount, 'The order: server, placeholder, mount.');
    }

    /**
     * The open day travels in the props alone: the server's layer draws no drawer, open or closed, and the
     * no-JavaScript agenda is the same with the day as without it.
     *
     * @return void
     */
    public function test_the_open_day_is_the_clients_and_the_agenda_is_unchanged(): void {
        global $PAGE;
        [$renderable, $window, $answer] = $this->build(new state());
        $day = $window->days[10];
        $opened = new calendar_page(
            self::month_start()->format('Y-m'),
            self::month_start()->format('Y-m-d'),
            0,
            \core_date::get_user_timezone(),
            $window,
            $answer,
            new state(),
            tree::for_scope(scope::for_viewer()),
            $day
        );
        $this->assertSame('', $renderable->props()['day'], 'The control: no day, no open day.');
        $this->assertSame($day, $opened->props()['day']);

        $renderer = $PAGE->get_renderer('core');
        $closed = $renderer->render($renderable);
        $open = $renderer->render($opened);
        $strip = static fn(string $html): string => (string) preg_replace('~data-react-props="[^"]*"~', '', $html);
        $this->assertSame($strip($closed), $strip($open), 'Only the props differ.');
        $this->assertStringContainsString('>Enrolment week</a>', $open, 'The agenda is drawn.');
        $this->assertStringNotContainsString('dim-cal-day', $open, 'The server draws no drawer.');
    }

    /**
     * Core's answers reach the page: "New event" in the props and core's export in the footer, each only
     * where core offers it, beside its control: the export with the switch off, both for an account core's calendar
     * offers neither.
     *
     * @return void
     */
    public function test_new_event_and_the_export_follow_core(): void {
        global $CFG, $PAGE;
        set_config('enablecalendarexport', 1);
        [$renderable] = $this->build(new state());
        $renderer = $PAGE->get_renderer('core');
        $export = 'href="' . s((new \core\url('/calendar/export.php'))->out(false)) . '" data-region="dim-cal-export"';

        $this->assertSame(
            ['create' => true, 'context' => (int) \core\context\course::instance(SITEID)->id, 'course' => (int) SITEID],
            $renderable->props()['edits']
        );
        $this->assertSame(1, substr_count($renderer->render($renderable), $export), 'Control: core offers the export.');

        set_config('enablecalendarexport', 0);
        $html = $renderer->render($renderable);
        $this->assertStringNotContainsString('dim-cal-export', $html, 'The switch off.');
        $this->assertStringNotContainsString('dim-cal-footer', $html);
        $this->assertTrue($renderable->props()['edits']['create'], '"New event" is not the export\'s switch.');

        set_config('enablecalendarexport', 1);
        assign_capability(
            'moodle/calendar:manageownentries',
            CAP_PROHIBIT,
            $CFG->defaultuserroleid,
            \core\context\system::instance()->id,
            true
        );
        accesslib_clear_all_caches_for_unit_testing();
        $this->assertFalse($renderable->props()['edits']['create'], 'An account core offers no "New event".');
        $this->assertStringNotContainsString('dim-cal-export', $renderer->render($renderable), 'Nor the export.');
    }

    /**
     * The Trilhas view's fold is core's course-section toggle: the props carry core's three chevrons as the theme's core
     * renderer draws them, decorative, and the visitor's props carry none of the account's view.
     *
     * @return void
     */
    public function test_the_trilhas_fold_draws_cores_chevrons(): void {
        global $PAGE;
        [$renderable] = $this->build(new state());
        $icons = $renderable->props()['trails']['icons'];
        $output = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertSame([
            'expanded' => $output->render(new \pix_icon('t/expandedchevron', '')),
            'collapsed' => $output->render(new \pix_icon('t/collapsedchevron', '')),
            'collapsedrtl' => $output->render(new \pix_icon('t/collapsedchevron_rtl', '')),
        ], $icons);
        $this->assertCount(3, array_unique($icons), 'Three glyphs, each its own.');
        foreach ($icons as $name => $html) {
            $this->assertStringContainsString('aria-hidden="true"', $html, "{$name}: decorative, the button is named.");
        }
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
     * T3c: the Trilhas timeline's zoom reaches the client as one of its two words or as days, its control's four strings
     * among the client's, and a zoom the page does not know is days.
     *
     * @return void
     */
    public function test_the_trilhas_zoom_reaches_the_client(): void {
        [$renderable, $window, $answer] = $this->build(new state());
        $this->assertSame('', $renderable->props()['trails']['zoom'], 'Days unless named.');
        $month = self::month_start()->format('Y-m');
        $make = fn(string $zoom): calendar_page => new calendar_page(
            $month,
            self::month_start()->format('Y-m-d'),
            0,
            \core_date::get_user_timezone(),
            $window,
            $answer,
            new state(),
            [],
            '',
            false,
            page::VIEW_TRAIL,
            '',
            null,
            0,
            false,
            $zoom
        );
        $this->assertSame('week', $make(page::ZOOM_WEEK)->props()['trails']['zoom']);
        $this->assertSame('month', $make(page::ZOOM_MONTH)->props()['trails']['zoom']);
        $this->assertSame('', $make('year')->props()['trails']['zoom'], 'A zoom the page does not know is days.');
        foreach (['calendar_trailzoom', 'calendar_trailzoomday', 'calendar_trailzoommonth', 'calendar_trailzoomweek'] as $key) {
            $this->assertContains($key, calendar_page::STRINGS, $key);
        }
        $this->assertContains('calendar_today', calendar_page::STRINGS, 'The today tag reads the toolbar\'s word.');
        $this->assertContains('calendar_trailfrom', calendar_page::STRINGS, 'A start alone.');
        $this->assertContains('calendar_until', calendar_page::STRINGS, 'An end alone.');
        $this->assertContains('calendar_trailnodates', calendar_page::STRINGS, 'No dates.');
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
    /**
     * The week view's server layer, whole without JavaScript: the week's caption, the adjacent weeks' links, the switch's
     * two links (the week current), the form keeping the view and the week, and the week's own agenda; the month's render
     * is the control. The props carry the view, the week and the server's clock.
     *
     * @return void
     */
    public function test_the_template_draws_the_week_without_javascript(): void {
        global $PAGE;
        $this->as_viewer($this->ids['student']);
        $month = self::month_start()->format('Y-m');
        $week = page::week_start(self::month_start()->modify('+8 days')->format('Y-m-d'), 0);
        [$from, $to] = page::week($week);
        $window = window::from_iso($from, $to);
        $state = new state();
        $answer = range::answer($window, scope::for_viewer(), $state->on(), $state->filters());
        $before = time();
        $renderable = new calendar_page(
            $month,
            self::month_start()->format('Y-m-d'),
            0,
            \core_date::get_user_timezone(),
            $window,
            $answer,
            $state,
            tree::for_scope(scope::for_viewer()),
            '',
            false,
            page::VIEW_WEEK,
            $week
        );
        $props = $renderable->props();
        $this->assertSame([page::VIEW_WEEK, $week], [$props['view'], $props['week']]);
        $this->assertGreaterThanOrEqual($before, $props['now']);
        $this->assertLessThanOrEqual(time(), $props['now']);

        $html = $PAGE->get_renderer('core')->render($renderable);
        $timezone = \core_date::get_user_timezone_object();
        $at = static fn(string $day): int => (new \DateTimeImmutable($day, $timezone))->getTimestamp();
        $caption = get_string('calendar_span', 'theme_dimensions', (object) [
            'from' => userdate($at($from), get_string('strftimedateshort', 'langconfig')),
            'to' => userdate($at($to), get_string('strftimedate', 'langconfig')),
        ]);
        $this->assertStringContainsString('<p class="dim-cal__caption">' . s($caption) . '</p>', $html);
        $previous = (new \DateTimeImmutable($week))->modify('-7 days')->format('Y-m-d');
        $this->assertStringContainsString('view=week&amp;week=' . $previous, $html, 'Previous leads to the week before.');
        $this->assertStringContainsString('aria-label="' . get_string('calendar_prevweek', 'theme_dimensions') . '"', $html);
        $this->assertMatchesRegularExpression('~data-region="dim-cal-view-week" aria-current="page">~', $html);
        $this->assertMatchesRegularExpression('~href="[^"]*month=' . $month . '[^"]*" data-region="dim-cal-view-month">~', $html);
        $this->assertStringContainsString('<input type="hidden" name="view" value="week">', $html);
        $this->assertStringContainsString('dim-cal__placeholder--week" data-region="dim-cal-placeholder"', $html);
        $this->assertStringContainsString('<input type="hidden" name="week" value="' . $week . '">', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="month"', $html);
        $this->assertSame(1, substr_count($html, '>Mentoring B</a>'), 'The week\'s timed event, listed once.');
        $this->assertSame(1, substr_count($html, '>Enrolment week</a>'), 'The ten-day event, once, under the week\'s first day.');
        $this->assertStringContainsString(get_string('calendar_noscriptweek', 'theme_dimensions'), $html);

        [$monthpage] = $this->build($state);
        $control = $PAGE->get_renderer('core')->render($monthpage);
        $this->assertMatchesRegularExpression('~data-region="dim-cal-view-month" aria-current="page">~', $control);
        $this->assertMatchesRegularExpression(
            '~href="[^"]*view=week&amp;week=[0-9-]+[^"]*" data-region="dim-cal-view-week">~',
            $control
        );
        $this->assertStringContainsString('aria-label="' . get_string('calendar_prevmonth', 'theme_dimensions') . '"', $control);
        $this->assertStringContainsString('<input type="hidden" name="month" value="' . $month . '">', $control);
        $this->assertSame([page::VIEW_MONTH, ''], [$monthpage->props()['view'], $monthpage->props()['week']]);
    }
    /**
     * The week's agenda lists every day of the week, the days outside the month named beside it included; the month's
     * agenda, its control, lists the month's own days alone.
     *
     * @return void
     */
    public function test_the_week_agenda_lists_the_weeks_days_outside_the_month(): void {
        global $PAGE;
        $eve = self::month_start()->modify('-1 day');
        self::getDataGenerator()->create_event([
            'name' => 'Month end review',
            'eventtype' => 'site',
            'timestart' => $eve->setTime(10, 0)->getTimestamp(),
        ]);
        $this->as_viewer($this->ids['student']);
        $month = self::month_start()->format('Y-m');
        $week = page::week_start($eve->format('Y-m-d'), 0);
        [$from, $to] = page::week($week);
        $window = window::from_iso($from, $to);
        $state = new state();
        $answer = range::answer($window, scope::for_viewer(), $state->on(), $state->filters());
        $this->assertContains('Month end review', array_column($answer['events'], 'name'), 'Precondition: the week holds it.');
        $today = self::month_start()->format('Y-m-d');
        $timezone = \core_date::get_user_timezone();
        $view = page::VIEW_WEEK;
        $renderable = new calendar_page($month, $today, 0, $timezone, $window, $answer, $state, [], '', false, $view, $week);
        $html = $PAGE->get_renderer('core')->render($renderable);
        $this->assertSame(1, substr_count($html, '>Month end review</a>'));

        $monthly = new calendar_page($month, $today, 0, $timezone, $window, $answer, $state, []);
        $this->assertStringNotContainsString(
            '>Month end review</a>',
            $PAGE->get_renderer('core')->render($monthly),
            'The control: the month\'s agenda lists the month\'s own days.'
        );
    }
}
