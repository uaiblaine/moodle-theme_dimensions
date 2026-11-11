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
 * Theme Dimensions - Tests for the core_calendar renderer: core's calendar blocks drawn with the theme's calendar
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\calendar\blocks;
use theme_dimensions\local\calendar\state;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/calendar/fixture.php');

/**
 * The two blocks' calls, rendered through the renderer the factory hands a page of this theme.
 *
 * Each block's data comes from calendar_get_view() as the block computes it; core's output is the same call through
 * core's own renderer, compared byte for byte once the id core draws per render (two uniqid() values and a counter, the
 * template's {{uniqid}}) is set aside.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_calendar_renderer::class)]
#[CoversClass(core_renderer::class)]
#[CoversClass(calendar_block_month::class)]
#[CoversClass(calendar_block_upcoming::class)]
final class core_calendar_renderer_test extends \advanced_testcase {
    use \theme_dimensions\local\calendar\fixture;

    /**
     * A page of this theme, on the site's course unless given another.
     *
     * @param \stdClass|null $course The page's course.
     * @return \moodle_page
     */
    private static function page(?\stdClass $course = null): \moodle_page {
        $page = new \moodle_page();
        $page->set_context($course ? \core\context\course::instance($course->id) : \core\context\system::instance());
        $page->set_url('/my/');
        if ($course) {
            $page->set_course($course);
        }
        $page->force_theme('dimensions');
        return $page;
    }

    /**
     * A block's call as the block makes it: calendar_get_view()'s template and data, the month's flag set.
     *
     * @param string $kind blocks::MONTH or blocks::UPCOMING.
     * @param int $courseid The page's course.
     * @return array [template, data]
     */
    private static function call(string $kind, int $courseid = SITEID): array {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $calendar = \calendar_information::create(time(), $courseid, null);
        if ($kind === blocks::MONTH) {
            [$data, $template] = calendar_get_view($calendar, 'monthblock', true);
            $data->iscalendarblock = true;
            return [$template, $data];
        }
        [$data, $template] = calendar_get_view($calendar, 'upcoming_mini');
        return [$template, $data];
    }

    /**
     * Core's own rendering of a call, through core's renderer.
     *
     * @param \moodle_page $page The page.
     * @param string $template The template.
     * @param \stdClass $data The data.
     * @return string
     */
    private static function core(\moodle_page $page, string $template, \stdClass $data): string {
        $renderer = new \core_calendar_renderer($page, RENDERER_TARGET_GENERAL);
        return self::normalised($renderer->render_from_template($template, $data));
    }

    /**
     * An output with core's per-render id set aside.
     *
     * @param string $html The output.
     * @return string
     */
    private static function normalised(string $html): string {
        return (string) preg_replace('/[0-9a-f]{26}\d+/', 'UNIQID', $html);
    }

    /**
     * The factory hands a page of this theme the theme's renderer.
     *
     * @return void
     */
    public function test_the_theme_renderer_is_the_one_the_factory_returns(): void {
        $this->resetAfterTest();
        $this->assertInstanceOf(core_calendar_renderer::class, self::page()->get_renderer('core_calendar'));
    }

    /**
     * Both blocks are drawn with the theme's calendar for a real account outside a course.
     *
     * @return void
     */
    public function test_both_blocks_are_replaced(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $page = self::page();
        $renderer = $page->get_renderer('core_calendar');

        [$template, $data] = self::call(blocks::MONTH);
        $month = $renderer->render_from_template($template, $data);
        $this->assertStringContainsString('data-region="dim-calblock-month"', $month);
        $this->assertStringNotContainsString('data-template="core_calendar/month_detailed"', $month);
        $this->assertStringContainsString('data-template="core_calendar/month_detailed"', self::core($page, $template, $data));

        [$template, $data] = self::call(blocks::UPCOMING);
        $upcoming = $renderer->render_from_template($template, $data);
        $this->assertStringContainsString('data-region="dim-calblock-upcoming"', $upcoming);
        $this->assertStringNotContainsString('data-template="core_calendar/upcoming_mini"', $upcoming);
    }

    /**
     * The month block's links lead to the page: each day with `day=`, the caption to the month, the arrows to the
     * adjacent months; its client is armed. The agenda ends with "Full calendar", the page.
     *
     * @return void
     */
    public function test_the_blocks_lead_to_the_page(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $page = self::page();
        $renderer = $page->get_renderer('core_calendar');
        $today = new \DateTimeImmutable('now', \core_date::get_user_timezone_object());
        $month = $today->format('Y-m');

        [$template, $data] = self::call(blocks::MONTH);
        $html = $renderer->render_from_template($template, $data);
        $dayurl = calendar_block_month::page_url($month, $today->format('Y-m-d'));
        $this->assertStringContainsString('href="' . s($dayurl) . '"', $html);
        $this->assertStringContainsString('day=' . $today->format('Y-m-d'), $dayurl);
        $this->assertStringContainsString('href="' . s(calendar_block_month::page_url($month)) . '"', $html);
        $this->assertStringContainsString('data-month="' . calendar_page::month_at($month, -1) . '"', $html);
        $this->assertStringContainsString('data-month="' . calendar_page::month_at($month, 1) . '"', $html);
        $this->assertStringContainsString('aria-current="date"', $html);
        $amd = implode("\n", (new \ReflectionProperty($page->requires, 'amdjscode'))->getValue($page->requires));
        $this->assertStringContainsString('theme_dimensions/calendarblock', $amd);

        [$template, $data] = self::call(blocks::UPCOMING);
        $html = $renderer->render_from_template($template, $data);
        $this->assertStringContainsString('href="' . s(\theme_dimensions\local\calendar\page::url()->out(false)) . '"', $html);
        $this->assertStringContainsString(get_string('calendarblock_full', 'theme_dimensions'), $html);
    }

    /**
     * Core's own month page renders the month template without the flag: it is core's, byte for byte.
     *
     * @return void
     */
    public function test_cores_month_page_is_left_alone(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $page = self::page();
        [$template, $data] = self::call(blocks::MONTH);
        unset($data->iscalendarblock);

        $html = $page->get_renderer('core_calendar')->render_from_template($template, $data);

        $this->assertStringContainsString('data-template="core_calendar/month_detailed"', $html, 'Precondition.');
        $this->assertSame(self::core($page, $template, $data), self::normalised($html));
    }

    /**
     * Each refusal of the renderer's decision, by what it changes before the call.
     *
     * @return array[]
     */
    public static function refusals(): array {
        return [
            'the setting off' => ['setting'],
            'a course page' => ['course'],
            'the guest' => ['guest'],
            'a visitor' => ['visitor'],
            'a calendar type other than the Gregorian' => ['calendartype'],
        ];
    }

    /**
     * A refusal leaves both blocks as core's output, byte for byte; the control is the same call answered by the theme.
     *
     * @param string $refusal What refuses.
     * @return void
     */
    #[DataProvider('refusals')]
    public function test_each_refusal_leaves_cores_output(string $refusal): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        foreach ([blocks::MONTH, blocks::UPCOMING] as $kind) {
            [$template, $data] = self::call($kind);
            $control = self::page()->get_renderer('core_calendar')->render_from_template($template, $data);
            $this->assertStringContainsString('dim-calblock', $control, "The control: {$kind} is the theme's.");
        }

        $page = null;
        switch ($refusal) {
            case 'setting':
                set_config(blocks::SETTING, '0', blocks::COMPONENT);
                break;
            case 'course':
                $page = self::page($course);
                break;
            case 'guest':
                $this->setGuestUser();
                break;
            case 'visitor':
                $this->setUser(0);
                break;
        }
        $page = $page ?? self::page();

        foreach ([blocks::MONTH, blocks::UPCOMING] as $kind) {
            [$template, $data] = self::call($kind, (int) $page->course->id);
            if ($refusal === 'calendartype') {
                // Core's month view cannot compute the test type's month, so the type changes after the data.
                $USER->calendartype = 'test_example';
            }
            $html = $page->get_renderer('core_calendar')->render_from_template($template, $data);
            unset($USER->calendartype);
            $this->assertStringNotContainsString('dim-calblock', $html, $kind);
            $this->assertSame(self::core($page, $template, $data), self::normalised($html), $kind);
        }
    }

    /**
     * The agenda never names what the range drops: a category event of an unlisted category, beside a listed one's,
     * the saved state narrowed to their parent.
     *
     * @return void
     */
    public function test_the_agenda_never_names_what_the_range_drops(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = $generator->create_category();
        $listed = $generator->create_category(['parent' => $top->id]);
        $unlisted = $generator->create_category(['parent' => $top->id]);
        foreach (['Listed gathering' => $listed, 'Withheld gathering' => $unlisted] as $name => $category) {
            $generator->create_event([
                'eventtype' => 'category',
                'categoryid' => $category->id,
                'name' => $name,
                'timestart' => time() + 2 * HOURSECS,
            ]);
        }
        $this->set_category_state((int) $unlisted->id, category_discoverability::STATE_UNLISTED);
        $this->as_viewer($generator->create_user());
        (new state([], (int) $top->id))->save();

        [$template, $data] = self::call(blocks::UPCOMING);
        $html = self::page()->get_renderer('core_calendar')->render_from_template($template, $data);

        $this->assertStringContainsString('Listed gathering', $html, 'The control: the listed category event is shown.');
        $this->assertStringNotContainsString('Withheld gathering', $html);
    }

    /**
     * The agenda's items: the name in the plain spelling escaped once, the time, the course, and a link to the item.
     *
     * @return void
     */
    public function test_the_agenda_draws_each_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course(['fullname' => 'Rivers & lakes']);
        $student = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        self::getDataGenerator()->create_event([
            'eventtype' => 'course',
            'courseid' => $course->id,
            'name' => 'Field trip & lunch',
            'timestart' => time() + 2 * HOURSECS,
        ]);
        $this->as_viewer($student);

        [$template, $data] = self::call(blocks::UPCOMING);
        $html = self::page()->get_renderer('core_calendar')->render_from_template($template, $data);

        $this->assertStringContainsString('>Field trip &amp; lunch</a>', $html);
        $this->assertStringContainsString('Rivers &amp; lakes', $html);
        $courseurl = (new \core\url('/course/view.php', ['id' => $course->id]))->out(false);
        $this->assertStringContainsString('href="' . s($courseurl) . '"', $html);
        $this->assertStringNotContainsString(get_string('calendarblock_upcomingempty', 'theme_dimensions'), $html);
    }

    /**
     * The page's core renderer for a web page: under PHPUnit the default target is the CLI's, core's own renderer.
     *
     * @param \moodle_page $page The page.
     * @return \core\output\core_renderer
     */
    private static function web_renderer(\moodle_page $page): \core\output\core_renderer {
        return $page->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    }

    /**
     * A block's contents as the upcoming block hands them to the page: core's "Go to calendar..." in the footer.
     *
     * @param string $block The block's `data-block` name.
     * @return \core_block\output\block_contents
     */
    private static function contents(string $block): \core_block\output\block_contents {
        $bc = new \core_block\output\block_contents(['class' => 'block block_' . $block, 'data-block' => $block]);
        $bc->blockinstanceid = 1;
        $bc->title = 'Upcoming events';
        $bc->content = '<p>content</p>';
        $bc->footer = '<div class="gotocal">Go to calendar...</div>';
        return $bc;
    }

    /**
     * Core's "Go to calendar..." is dropped from the upcoming block the theme answers, and stays where core answers the block
     * (a course page, the setting off, the guest) and under any other block; the caller's contents untouched.
     *
     * @return void
     */
    public function test_the_upcoming_footer_goes_where_the_theme_answers_the_block(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_user());

        $this->assertInstanceOf(core_renderer::class, self::web_renderer(self::page()));
        $bc = self::contents(blocks::BLOCK_UPCOMING);
        $html = self::web_renderer(self::page())->block($bc, 'side-pre');
        $this->assertStringContainsString('<p>content</p>', $html);
        $this->assertStringNotContainsString('gotocal', $html, "The theme's agenda ends with its own link.");
        $this->assertSame('<div class="gotocal">Go to calendar...</div>', $bc->footer, "The caller's object is untouched.");

        $html = self::web_renderer(self::page())->block(self::contents('calendar_month'), 'side-pre');
        $this->assertStringContainsString('gotocal', $html, 'Another block keeps its footer.');

        $html = self::web_renderer(self::page($course))->block(self::contents(blocks::BLOCK_UPCOMING), 'side-pre');
        $this->assertStringContainsString('gotocal', $html, "A course's block is core's, with core's footer.");

        set_config(blocks::SETTING, '0', blocks::COMPONENT);
        $html = self::web_renderer(self::page())->block(self::contents(blocks::BLOCK_UPCOMING), 'side-pre');
        $this->assertStringContainsString('gotocal', $html, 'The setting off: core answers the block.');
        set_config(blocks::SETTING, '1', blocks::COMPONENT);

        $this->setGuestUser();
        $html = self::web_renderer(self::page())->block(self::contents(blocks::BLOCK_UPCOMING), 'side-pre');
        $this->assertStringContainsString('gotocal', $html, 'The guest: core answers the block.');
    }
}
