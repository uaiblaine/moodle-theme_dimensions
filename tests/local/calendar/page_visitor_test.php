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
 * Theme Dimensions - Tests for the calendar page in its visitor mode
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\hook\output\before_standard_head_html_generation;
use theme_dimensions\output\calendar_page;
use theme_dimensions\output\opengraph;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The public calendar's page: the switch decides before anything is looked up, and on, a visitor is served the page
 * without require_login(), with the visitor's component, keys and head tags.
 *
 * Every refusal is asserted beside the same request served once the one reason is gone.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(page::class)]
#[CoversClass(visitor::class)]
#[CoversClass(calendar_page::class)]
final class page_visitor_test extends \advanced_testcase {
    use fixture;

    /** @var int The public category. */
    private int $categoryid = 0;

    /**
     * Forget the request, the head tags and the router.
     *
     * @return void
     */
    protected function tearDown(): void {
        $_GET = [];
        opengraph::set(null);
        \core\di::reset_container();
        self::forget_calendar();
        parent::tearDown();
    }

    /**
     * A public category with an event next month; the site forces login; the switch as asked.
     *
     * @param string|null $switch The stored switch, null for never saved.
     * @return void
     */
    private function seed(?string $switch): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->forcelogin = 1;
        $CFG->routerconfigured = false;
        $this->categoryid = (int) self::getDataGenerator()->create_category(['name' => 'Agenda'])->id;
        category_discoverability::set_state($this->categoryid, category_discoverability::STATE_PUBLIC, (int) get_admin()->id);
        self::getDataGenerator()->create_event([
            'name' => 'Feira publica',
            'eventtype' => 'category',
            'categoryid' => $this->categoryid,
            'timestart' => self::at(10),
        ]);
        if ($switch !== null) {
            set_config(visitor::SETTING, $switch, 'theme_dimensions');
        }
        $this->setUser(null);
        self::forget_calendar();
        $GLOBALS['PAGE'] = new \moodle_page();
    }

    /**
     * The page's answer for a request, as a new request.
     *
     * @param array $query The query string.
     * @return page
     */
    private function handle(array $query = []): page {
        $_GET = $query + ['month' => self::month_start()->format('Y-m')];
        $GLOBALS['PAGE'] = new \moodle_page();
        return page::handle();
    }

    /**
     * The template context of a page served.
     *
     * @param page $answer The answer.
     * @return array
     */
    private function context(page $answer): array {
        $this->assertNull($answer->redirect, 'The page is served, not a redirect.');
        return $answer->page->export_for_template($GLOBALS['PAGE']->get_renderer('core'));
    }

    /**
     * The switch off sends a visitor to the login page before anything is read; on, the same request is the page.
     *
     * @return void
     */
    public function test_the_switch_decides_before_any_lookup(): void {
        global $SESSION;
        $this->seed(null);
        foreach (['never saved' => null, 'a stored 0' => '0', 'anything but 1' => 'yes'] as $case => $switch) {
            if ($switch === null) {
                unset_config(visitor::SETTING, 'theme_dimensions');
            } else {
                set_config(visitor::SETTING, $switch, 'theme_dimensions');
            }
            visitor::enabled();
            $SESSION->wantsurl = null;
            $answer = null;
            $reads = $this->reads(function () use (&$answer): void {
                $answer = $this->handle();
            });
            $this->assertSame(get_login_url(), $answer->redirect->out(false), $case);
            $this->assertNull($answer->page);
            $this->assertSame(0, $reads, $case . ': refused before any statement.');
            $this->assertStringContainsString('/theme/dimensions/calendar.php', $SESSION->wantsurl, 'Back here after.');
        }

        set_config(visitor::SETTING, '1', 'theme_dimensions');
        $context = $this->context($this->handle());
        $this->assertTrue($context['visitor'], 'Control: a stored 1 serves the page.');
    }

    /**
     * A visitor is served the visitor's page under forced login: its component, its two keys, nothing of a session.
     *
     * @return void
     */
    public function test_a_visitor_gets_the_public_page_without_logging_in(): void {
        $this->seed('1');
        $context = $this->context($this->handle());
        $this->assertSame(calendar_page::COMPONENT_PUBLIC, $context['component']);
        $this->assertSame(visitor::KEYS, array_column($context['keys'], 'key'));
        foreach (['categories', 'models', 'modalities', 'cities', 'classicurl', 'clearurl'] as $absent) {
            $this->assertArrayNotHasKey($absent, $context, $absent . ' is the logged-in page\'s.');
        }
        $props = json_decode($context['props'], true);
        $expected = ['month', 'view', 'week', 'day', 'today', 'now', 'firstday', 'timezone', 'answer', 'keys', 'modalities'];
        $expected[] = 'urls';
        $this->assertSame($expected, array_keys($props));
        $this->assertSame(['page', 'home'], array_keys($props['urls']), 'No address of core\'s calendar.');
        $this->assertSame(['Feira publica'], array_column($props['answer']['events'], 'name'));
        $this->assertArrayHasKey('descriptions', $props['answer']);
        $this->assertSame(page::PAGETYPE, $GLOBALS['PAGE']->pagetype);
        $this->assertFalse(isloggedin(), 'Nobody was logged in to serve it.');
    }

    /**
     * The guest account is core's calendar's still, and a real account still gets the logged-in page.
     *
     * @return void
     */
    public function test_the_guest_and_an_account_keep_their_pages(): void {
        $this->seed('1');
        $this->setGuestUser();
        $this->assertSame(page::core_calendar()->out(false), $this->handle()->redirect->out(false));
        $this->setUser(self::getDataGenerator()->create_user());
        self::forget_calendar();
        $context = $this->context($this->handle());
        $this->assertSame(calendar_page::COMPONENT, $context['component'], 'Control: an account\'s page.');
        $this->setUser(null);
        $this->assertSame(calendar_page::COMPONENT_PUBLIC, $this->context($this->handle())['component']);
    }

    /**
     * The keys come from the address: the two by default, the ones named, none for an empty member; others dropped.
     *
     * @return void
     */
    public function test_the_keys_come_from_the_address(): void {
        $this->seed('1');
        $props = fn(array $query): array => json_decode($this->context($this->handle($query))['props'], true);
        $events = fn(array $query): array => array_column($props($query)['answer']['events'], 'key');
        $on = fn(array $query): array => $props($query)['keys'];
        $this->assertSame(visitor::KEYS, $on([]));
        $this->assertSame(['category'], $events([]), 'Control: the category event.');
        $this->assertSame(['open'], $on(['keys' => ['open']]));
        $this->assertSame([], $events(['keys' => ['open']]), 'The category key off.');
        $this->assertSame([], $on(['keys' => ['']]), 'An empty member is none.');
        $this->assertSame([], $on(['keys' => ['site', 'user']]), 'A key of the logged-in page is dropped.');
        $this->assertSame([], $on(['keys' => 'category']), 'A scalar is no list.');
        $this->assertSame([], $on(['keys' => [['category']]]), 'A nested list is dropped, never a 500.');
        $this->assertSame(['open'], $on(['keys' => [['category'], 'open']]), 'Its scalar neighbour is kept.');
        $this->assertSame(['keys' => ['open']], visitor::query(['open', 'site']));
        $this->assertSame([], visitor::query(visitor::KEYS), 'Both on: the address names nothing.');
        $this->assertSame(['keys' => ['']], visitor::query([]));
    }

    /**
     * The head tags are armed: the canonical address, and noindex unless the site is open to search engines.
     *
     * @return void
     */
    public function test_the_page_is_noindex_unless_the_site_is_open_to_search_engines(): void {
        global $CFG;
        $this->seed('1');
        $this->assertNull(opengraph::current(), 'Precondition: no tags yet.');
        $this->context($this->handle());
        $tags = opengraph::current();
        $this->assertNotNull($tags, 'The page arms its head tags.');
        $this->assertSame(page::url()->out(false), $tags->get_url());

        $renderer = $GLOBALS['PAGE']->get_renderer('core');
        $CFG->opentowebcrawlers = 0;
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $hook->get_output());
        $CFG->opentowebcrawlers = 1;
        $hook = new \core\hook\output\before_standard_head_html_generation($renderer);
        before_standard_head_html_generation::callback($hook);
        $this->assertStringNotContainsString('name="robots"', $hook->get_output(), 'Control: an open site is indexed.');
    }

    /**
     * A calendar type other than the Gregorian is the one refusal, the login page, beside the Gregorian served.
     *
     * @return void
     */
    public function test_another_calendar_type_is_sent_to_the_login_page(): void {
        global $CFG;
        require_once($CFG->dirroot . '/calendar/tests/calendartype_test_example.php');
        $this->seed('1');
        $CFG->calendartype = 'test_example';
        \core_calendar\type_factory::get_calendar_instance();
        $this->assertSame(get_login_url(), $this->handle()->redirect->out(false));
        $CFG->calendartype = 'gregorian';
        $this->assertTrue($this->context($this->handle())['visitor'], 'Control: the Gregorian type.');
    }

    /**
     * The no-JavaScript layer draws the two chips and the agenda, and nothing of the logged-in page.
     *
     * @return void
     */
    public function test_the_server_layer_draws_the_visitors_page(): void {
        $this->seed('1');
        $answer = $this->handle();
        $html = $GLOBALS['PAGE']->get_renderer('core')->render($answer->page);
        $this->assertStringContainsString('data-react-component="' . calendar_page::COMPONENT_PUBLIC . '"', $html);
        $this->assertStringContainsString('Feira publica', $html);
        $this->assertSame(2, substr_count($html, 'type="checkbox" name="keys[]"'), 'Two chips and no third.');
        foreach (['name="categoryid"', 'name="model[]"', 'calendar/view.php', 'dim-cal__classic'] as $absent) {
            $this->assertStringNotContainsString($absent, $html, $absent . ' is the logged-in page\'s.');
        }
        $this->assertStringContainsString(get_string('calendar_publicnoscript', 'theme_dimensions'), $html);
    }
    /**
     * A list in the address is dropped for a visitor, never an error page: the bare month, the month view, no open day,
     * the keys of a list that holds a list read as none.
     *
     * @return void
     */
    public function test_a_list_in_the_address_is_dropped_for_a_visitor(): void {
        $this->seed('1');
        $month = self::month_start()->format('Y-m');
        $day = self::month_start()->modify('+9 days')->format('Y-m-d');
        $context = $this->context($this->handle([page::PARAM_DAY => $day, page::PARAM_VIEW => 'week', page::PARAM_WEEK => $day]));
        $props = json_decode($context['props'], true);
        $this->assertSame([page::VIEW_WEEK, $month, $day], [$props['view'], $props['month'], $props['day']], 'The control.');

        $props = json_decode($this->context($this->handle([
            'month' => [$month],
            page::PARAM_DAY => [$day],
            page::PARAM_VIEW => ['week'],
            page::PARAM_WEEK => [$day],
            'keys' => [['category']],
        ]))['props'], true);
        $this->assertSame(
            [page::VIEW_MONTH, page::today()->format('Y-m'), '', []],
            [$props['view'], $props['month'], $props['day'], $props['keys']]
        );
    }

    /**
     * A visitor gets the week view too, under the same rules: the visitor's answer for the week's seven days, the
     * visitor's keys in its address; a week that does not hold the event is the control.
     *
     * @return void
     */
    public function test_a_visitor_gets_the_week(): void {
        $this->seed('1');
        $day = self::month_start()->modify('+9 days')->format('Y-m-d');
        $context = $this->context($this->handle([page::PARAM_VIEW => 'week', page::PARAM_WEEK => $day, 'keys' => ['category']]));
        $week = page::week_start($day, (int) calendar_get_starting_weekday());
        [$from, $to] = page::week($week);
        $this->assertTrue($context['weekview']);
        $this->assertSame(calendar_page::COMPONENT_PUBLIC, $context['component']);
        $props = json_decode($context['props'], true);
        $this->assertSame([page::VIEW_WEEK, $week, ['category']], [$props['view'], $props['week'], $props['keys']]);
        $this->assertSame([$from, $to], [$props['answer']['from'], $props['answer']['to']]);
        $this->assertSame(['Feira publica'], array_column($props['answer']['events'], 'name'));
        $url = $GLOBALS['PAGE']->url;
        $this->assertSame([page::VIEW_WEEK, $week, null], [$url->param('view'), $url->param('week'), $url->param('month')]);
        self::forget_calendar();
        $this->assertSame(
            json_decode(json_encode(publicrange::answer(window::from_iso($from, $to), ['category'])), true),
            $props['answer'],
            'The visitor\'s answer for the week.'
        );

        self::forget_calendar();
        $later = self::month_start()->modify('+20 days')->format('Y-m-d');
        $context = $this->context($this->handle([page::PARAM_VIEW => 'week', page::PARAM_WEEK => $later]));
        $props = json_decode($context['props'], true);
        $this->assertSame([], $props['answer']['events'], 'The control: another week holds no event.');
    }
}
