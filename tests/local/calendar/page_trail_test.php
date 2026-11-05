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
 * Theme Dimensions - Tests for the calendar page's Trilhas view
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_course\customfield\course_handler;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\trail;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The Trilhas view in the page's vocabulary: an account's only, its trail and "Só as
 * minhas" as navigation, its first paint the month's trails.
 *
 * Every test needs mod_subcourse and skips without it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(page::class)]
final class page_trail_test extends \advanced_testcase {
    use fixture;

    /**
     * Skip without the module.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
    }

    /**
     * Forget the request parameters a test set, and the router a test built.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        \core\di::reset_container();
        self::forget_calendar();
        parent::tearDown();
    }

    /**
     * A trail course of one step running through March 2027.
     *
     * @return \stdClass The trail course.
     */
    private function trail(): \stdClass {
        global $DB;

        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $generator = self::getDataGenerator();
        $ref = $generator->create_course([
            'fullname' => 'Alfa',
            'startdate' => make_timestamp(2027, 3, 2, 12),
            'enddate' => make_timestamp(2027, 3, 20, 12),
        ]);
        $trail = $generator->create_course(['fullname' => 'Trilha']);
        course_handler::create()->instance_form_save((object) [
            'id' => $trail->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL,
        ]);
        $generator->create_module('subcourse', ['course' => $trail->id, 'refcourse' => $ref->id, 'name' => 'Alfa']);
        // Open to self enrolment: the view shows a trail to an account with no tie to it only where it may enrol.
        $instance = $DB->get_record('enrol', ['courseid' => $trail->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->set_field('enrol', 'customint6', 1, ['id' => $instance->id]);
        enrol_get_plugin('self')->update_status($instance, ENROL_INSTANCE_ENABLED);
        return $trail;
    }

    /**
     * The view is the Trilhas view for exactly `trail`, for an account, while trails are available; for the guest,
     * nobody, or without the module, the address reads as the month.
     *
     * @return void
     */
    public function test_the_trail_view_is_an_accounts_only(): void {
        $this->setUser(self::getDataGenerator()->create_user());
        $_GET = [page::PARAM_VIEW => 'trail'];
        $this->assertTrue(page::trails_offered());
        $this->assertSame(page::VIEW_TRAIL, page::view_from_request());
        foreach (['Trail', 'trails', "trail\n"] as $other) {
            $_GET = [page::PARAM_VIEW => $other];
            $this->assertSame(page::VIEW_MONTH, page::view_from_request(), $other);
        }

        $_GET = [page::PARAM_VIEW => 'trail'];
        $this->setGuestUser();
        $this->assertFalse(page::trails_offered());
        $this->assertSame(page::VIEW_MONTH, page::view_from_request(), 'The guest.');
        $this->setUser(null);
        $this->assertSame(page::VIEW_MONTH, page::view_from_request(), 'Nobody.');

        $this->setUser(self::getDataGenerator()->create_user());
        \core\plugininfo\mod::enable_plugin(trail::MODNAME, 0);
        $this->assertFalse(page::trails_offered());
        $this->assertSame(page::VIEW_MONTH, page::view_from_request(), 'Without the module.');
    }

    /**
     * The trail and "Só as minhas" are read as written, anything else dropped; the address spells them after the view
     * and the month, and the forward carries them.
     *
     * @return void
     */
    public function test_the_trail_and_mine_are_navigation(): void {
        $this->setUser(self::getDataGenerator()->create_user());
        $cases = ['12' => 12, '0' => 0, '-3' => 0, '1.5' => 0, 'abc' => 0, '' => 0, '12345678901' => 0];
        foreach ($cases as $raw => $read) {
            $_GET = [page::PARAM_TRAIL => (string) $raw];
            $this->assertSame($read, page::trail_from_request(), "trail={$raw}");
        }
        $_GET = [page::PARAM_TRAIL => ['12']];
        $this->assertSame(0, page::trail_from_request(), 'A list is dropped.');
        foreach (['1' => true, '0' => false, 'yes' => false, '' => false] as $raw => $read) {
            $_GET = [page::PARAM_MINE => (string) $raw];
            $this->assertSame($read, page::mine_from_request(), "mine={$raw}");
        }

        $this->assertSame(
            [page::PARAM_VIEW => page::VIEW_TRAIL, state::PARAM_MONTH => '2027-03', page::PARAM_TRAIL => 12, page::PARAM_MINE => 1],
            page::trail_params('2027-03', 12, true)
        );
        $this->assertSame([page::PARAM_VIEW => page::VIEW_TRAIL, state::PARAM_MONTH => '2027-03'], page::trail_params('2027-03'));

        $_GET = [page::PARAM_VIEW => 'trail', state::PARAM_MONTH => '2027-03', page::PARAM_TRAIL => '12', page::PARAM_MINE => '1'];
        $forwarded = page::forwarded_params();
        $this->assertEquals(page::trail_params('2027-03', 12, true), array_intersect_key(
            $forwarded,
            array_flip([page::PARAM_VIEW, state::PARAM_MONTH, page::PARAM_TRAIL, page::PARAM_MINE])
        ));
        $this->assertSame(
            page::trail_params('2027-03', 12, true),
            page::span_params(page::VIEW_TRAIL, '2027-03', '', '')
        );
        $this->assertArrayNotHasKey(page::PARAM_TRAIL, (new state())->query(), 'Not state: never remembered.');
    }

    /**
     * T3c: the timeline's zoom is navigation like the trail: read as written (week or month exactly), dropped otherwise,
     * a list included; the address spells it after the trail, the forward carries it, and it is never state.
     *
     * @return void
     */
    public function test_the_zoom_is_navigation(): void {
        $this->setUser(self::getDataGenerator()->create_user());
        $cases = ['week' => 'week', 'month' => 'month', 'day' => '', 'Week' => '', 'year' => '', '' => '', ' week' => ''];
        foreach ($cases as $raw => $read) {
            $_GET = [page::PARAM_ZOOM => (string) $raw];
            $this->assertSame($read, page::zoom_from_request(), "zoom={$raw}");
        }
        $_GET = [page::PARAM_ZOOM => ['week']];
        $this->assertSame('', page::zoom_from_request(), 'A list is dropped.');
        $_GET = [];
        $this->assertSame('', page::zoom_from_request(), 'Absent: days.');

        $this->assertSame(
            [
                page::PARAM_VIEW => page::VIEW_TRAIL,
                state::PARAM_MONTH => '2027-03',
                page::PARAM_TRAIL => 12,
                page::PARAM_ZOOM => 'week',
            ],
            page::trail_params('2027-03', 12, false, 'week')
        );
        $this->assertSame(
            [page::PARAM_VIEW => page::VIEW_TRAIL, state::PARAM_MONTH => '2027-03', page::PARAM_ZOOM => 'month'],
            page::trail_params('2027-03', 0, false, 'month')
        );
        foreach (['', 'day', 'year'] as $unknown) {
            $this->assertSame(
                [page::PARAM_VIEW => page::VIEW_TRAIL, state::PARAM_MONTH => '2027-03'],
                page::trail_params('2027-03', 0, false, $unknown),
                "Days, or a zoom the page does not know, is never written: {$unknown}."
            );
        }

        $_GET = [page::PARAM_VIEW => 'trail', state::PARAM_MONTH => '2027-03', page::PARAM_ZOOM => 'month'];
        $this->assertSame(
            page::trail_params('2027-03', 0, false, 'month'),
            page::span_params(page::VIEW_TRAIL, '2027-03', '', '')
        );
        $forwarded = page::forwarded_params();
        $this->assertSame('month', $forwarded[page::PARAM_ZOOM] ?? null, 'The forward to the route carries it.');
        $_GET = [page::PARAM_VIEW => 'trail', state::PARAM_MONTH => '2027-03', page::PARAM_ZOOM => 'year'];
        $this->assertArrayNotHasKey(page::PARAM_ZOOM, page::forwarded_params(), 'A zoom it does not know is not forwarded.');
        $this->assertArrayNotHasKey(page::PARAM_ZOOM, page::span_params(page::VIEW_TRAIL, '2027-03', '', ''));

        // Never state: the state reads none of it and writes none of it.
        $_GET = [state::PARAM_MONTH => '2027-03', page::PARAM_ZOOM => 'week'];
        $this->assertNotContains(page::PARAM_ZOOM, state::STATE_PARAMS);
        $this->assertNull(state::from_request(), 'A zoom alone names no state.');
        $this->assertArrayNotHasKey(page::PARAM_ZOOM, (new state())->query());
    }

    /**
     * The Trilhas view's first paint embeds the trails of the month and the next (the two months its timeline opens on),
     * the answer the service would give for both, and the range's answer with every key off; the page's address keeps
     * the view, the month and the trail; the server's layer lists the trails.
     *
     * @return void
     */
    public function test_the_first_paint_embeds_the_months_trails(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/calendar/lib.php');
        $CFG->routerconfigured = false;
        $trail = $this->trail();
        $this->as_viewer(self::getDataGenerator()->create_user());

        $_GET = [page::PARAM_VIEW => 'trail', state::PARAM_MONTH => '2027-03', page::PARAM_TRAIL => (string) $trail->id];
        $PAGE = new \moodle_page();
        $answer = page::handle();
        $this->assertNull($answer->redirect);
        $props = $answer->page->props();
        $this->assertSame([page::VIEW_TRAIL, '2027-03'], [$props['view'], $props['month']]);
        $this->assertTrue($props['trails']['offered']);
        $this->assertSame([(int) $trail->id, false], [$props['trails']['trail'], $props['trails']['mine']]);
        $this->assertSame([(int) $trail->id], array_column($props['trails']['answer']['trails'], 'id'));
        $this->assertSame([], $props['answer']['events'], 'The Trilhas view draws no event.');
        $url = $PAGE->url;
        $this->assertSame(
            [page::VIEW_TRAIL, '2027-03', (string) $trail->id],
            [$url->param('view'), $url->param('month'), $url->param('trail')]
        );

        self::forget_calendar();
        $expected = trails::answer(
            trails::month_window('2027-03', trails::OPENING),
            scope::for_viewer(),
            (int) $trail->id,
            false,
            time()
        );
        $this->assertSame($expected, $props['trails']['answer'], 'The answer the service would give for both months.');
        $this->assertSame(['2027-03-01', '2027-04-30'], [$expected['from'], $expected['to']]);

        $context = $answer->page->export_for_template($PAGE->get_renderer('core'));
        $this->assertTrue($context['trailview']);
        $this->assertFalse($context['monthview']);
        $this->assertSame(['Trilha'], array_column($context['trails'], 'name'));
        $this->assertSame(['Alfa'], array_column($context['trails'][0]['steps'], 'name'));
        $this->assertStringContainsString('view=trail', $context['nexturl']);
        $this->assertStringContainsString('month=2027-04', $context['nexturl']);
        $this->assertStringContainsString('trail=' . $trail->id, $context['nexturl']);
        $this->assertSame('', $props['trails']['zoom'], 'Days, as the address names none.');

        // T3c: the zoom the address names reaches the client, the page's own address and the arrows' links.
        self::forget_calendar();
        $_GET = [page::PARAM_VIEW => 'trail', state::PARAM_MONTH => '2027-03', page::PARAM_ZOOM => 'week'];
        $PAGE = new \moodle_page();
        $zoomed = page::handle()->page;
        $this->assertSame('week', $zoomed->props()['trails']['zoom']);
        $this->assertSame('week', $PAGE->url->param('zoom'));
        $context = $zoomed->export_for_template($PAGE->get_renderer('core'));
        foreach (['prevurl', 'nexturl', 'todayurl'] as $link) {
            $this->assertStringContainsString('zoom=week', $context[$link], $link);
        }
        self::forget_calendar();
        $_GET = [page::PARAM_VIEW => 'trail', state::PARAM_MONTH => '2027-03', page::PARAM_ZOOM => 'year'];
        $PAGE = new \moodle_page();
        $this->assertSame('', page::handle()->page->props()['trails']['zoom'], 'A zoom it does not know is days.');
        $this->assertNull($PAGE->url->param('zoom'));

        // The control: the month view embeds no trails, and offers the view's link.
        self::forget_calendar();
        $_GET = [state::PARAM_MONTH => '2027-03'];
        $PAGE = new \moodle_page();
        $month = page::handle()->page;
        $this->assertNull($month->props()['trails']['answer']);
        $context = $month->export_for_template($PAGE->get_renderer('core'));
        $this->assertTrue($context['trailsoffered']);
        $this->assertFalse($context['trailview']);
        $this->assertStringContainsString('view=trail', $context['trailurl']);
    }
}
