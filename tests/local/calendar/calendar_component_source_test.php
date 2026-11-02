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
 * Theme Dimensions - Tests for the calendar page's client, by its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversNothing;
use theme_dimensions\output\calendar_page;

/**
 * What PHPUnit can hold of js/esm/src/calendar: its doors, its seams, its pending tokens and its measuring.
 *
 * The behaviour is Jest's (js/esm/tests/calendar); these are the contracts a reading of the source decides, each
 * with a control that the files were read.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class calendar_component_source_test extends \basic_testcase {
    /**
     * Every source of the calendar's tree, by path under it, comments out.
     *
     * @return array path => source.
     */
    private function sources(): array {
        $root = dirname(__DIR__, 3) . '/js/esm/src/calendar';
        $files = glob($root . '/*.{ts,tsx}', GLOB_BRACE);
        $sources = [];
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $sources[basename($file)] = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $source);
        }
        $this->assertArrayHasKey('Calendar.tsx', $sources, 'The control: the tree is read.');
        $this->assertArrayHasKey('MonthGrid.tsx', $sources);
        return $sources;
    }

    /**
     * The page mounts the component the tree exports by default.
     *
     * @return void
     */
    public function test_the_page_names_the_component_the_tree_exports(): void {
        $this->assertSame('@moodle/lms/theme_dimensions/calendar/Calendar', calendar_page::COMPONENT);
        $this->assertStringContainsString('export default Calendar;', $this->sources()['Calendar.tsx']);
    }

    /**
     * AMD is reached through the bridge alone: no module of the calendar imports core's loader or calls RequireJS, and
     * the one AMD module asked for is a name of the bridge's.
     *
     * @return void
     */
    public function test_amd_only_through_the_bridge(): void {
        $bridged = 0;
        foreach ($this->sources() as $path => $source) {
            $this->assertStringNotContainsString('@moodle/lms/core/amd', $source, $path);
            $this->assertDoesNotMatchRegularExpression('~\brequire(?:js)?\s*\(~', $source, $path);
            preg_match_all("~^import .* from '([^']+)';~m", $source, $imports);
            foreach ($imports[1] as $module) {
                $allowed = str_starts_with($module, './') || in_array($module, ['../amd', '../daterange/calendar', 'react'], true)
                    || str_starts_with($module, '@moodle/lms/core/');
                $this->assertTrue($allowed, "{$path} imports {$module}.");
                $bridged += $module === '../amd' ? 1 : 0;
            }
        }
        $this->assertSame(2, $bridged, 'The page reports a failure through the bridge, the blocks\' client paints through it.');
        $this->assertStringContainsString("load('core/notification')", $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString("load('core/templates')", $this->sources()['block.ts']);
    }

    /**
     * No layout is read on the mount path: the one read of the tree is the popover's, when it opens, and the grid is
     * measured by one ResizeObserver, which watches the rows (the density) and the frame the drawer shares (the phone).
     *
     * @return void
     */
    public function test_no_layout_read_and_one_observer(): void {
        $observers = 0;
        $reads = '~getBoundingClientRect|getClientRects|offset(?:Height|Width|Top|Left)|client(?:Height|Width)'
            . '|scroll(?:Height|Width|Top|Left)|getComputedStyle~';
        foreach ($this->sources() as $path => $source) {
            if ($path !== 'EventPopover.tsx') {
                $this->assertDoesNotMatchRegularExpression($reads, $source, "{$path} reads the layout.");
            }
            $observers += substr_count($source, 'new ResizeObserver(');
        }
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertSame(2, preg_match_all($reads, $popover), 'The opener\'s box and the popover\'s own.');
        $this->assertMatchesRegularExpression(
            '~useLayoutEffect\(\(\) => \{\s*if \(sheet \|\| !dialog\.current\) \{.*?getBoundingClientRect\(\);.*?'
                . 'getBoundingClientRect\(\);.*?\}, \[item\.source, item\.id, opener, sheet\]\);~s',
            $popover,
            'Both reads are in the effect of an opening, never of the page\'s mount.'
        );
        $this->assertSame(1, $observers);
        $grid = $this->sources()['MonthGrid.tsx'];
        $this->assertStringContainsString('new ResizeObserver(', $grid);
        $this->assertStringContainsString('observer.observe(element);', $grid);
        $this->assertStringContainsString('const frame = grid.current?.parentElement || null;', $grid);
        $this->assertStringContainsString('observer.observe(frame);', $grid);
        $this->assertStringContainsString(
            'onWidth={(width) => setPhone(width < PHONE)}',
            $this->sources()['Calendar.tsx'],
            'The phone is the frame\'s width, never the viewport\'s.'
        );
    }

    /**
     * A Pending token spans the mount, every request to its painted answer, and the remembering's quiet.
     *
     * @return void
     */
    public function test_the_pending_tokens(): void {
        $source = $this->sources()['Calendar.tsx'];
        foreach (['mount', 'range', 'remember'] as $name) {
            $this->assertStringContainsString("new Pending('theme_dimensions/calendar:{$name}')", $source);
        }
        $this->assertMatchesRegularExpression(
            "~const pending = new Pending\('theme_dimensions/calendar:description'\);"
                . '.*?\.finally\(\(\) => pending\.resolve\(\)\);~s',
            $this->sources()['EventPopover.tsx'],
            'The description\'s token spans the request, whichever popover it was for.'
        );
        $this->assertMatchesRegularExpression(
            '~useEffect\(\(\) => \{\s*painting\.current\?\.resolve\(\);~',
            $source,
            'The request\'s token is released once the answer is painted.'
        );
    }

    /**
     * The latest request wins: the ticket is checked after the await, before anything is shown.
     *
     * @return void
     */
    public function test_the_latest_request_wins(): void {
        $source = $this->sources()['Calendar.tsx'];
        $this->assertMatchesRegularExpression(
            '~const seq = \+\+ticket\.current;.*?await fetchOne.*?if \(seq !== ticket\.current\) \{'
                . '\s*pending\.resolve\(\);\s*return;\s*\}.*?setShown\(~s',
            $source
        );
    }

    /**
     * C2's two seams are C3's fallbacks, one function each: an item's href (and the popover's "Open"), and the
     * drawer's link to core's day view. Nothing redirects: a day opens the drawer, an item the popover.
     *
     * @return void
     */
    public function test_the_seams_are_the_fallbacks(): void {
        $definitions = ['itemHref' => 0, 'dayHref' => 0, 'primaryOf' => 0];
        foreach ($this->sources() as $path => $source) {
            foreach (array_keys($definitions) as $name) {
                $definitions[$name] += substr_count($source, "export const {$name} = ");
            }
            if ($path === 'block.ts') {
                // The month block's client leaves only where its arrow already leads, when it cannot paint.
                continue;
            }
            if ($path === 'PublicCalendar.tsx') {
                // The visitor's shell leaves only for the address it asked for, when its service fails.
                $this->assertSame(1, substr_count($source, 'redirect('), $path);
                $this->assertMatchesRegularExpression(
                    '~\} catch \{\s*pending\.resolve\(\);\s*if \(seq === ticket\.current\) \{\s*redirect\(target\);~',
                    $source
                );
                continue;
            }
            $this->assertStringNotContainsString('redirect(', $source, $path);
            $this->assertStringNotContainsString('core/location', $source, $path);
        }
        $this->assertSame(['itemHref' => 1, 'dayHref' => 1, 'primaryOf' => 1], $definitions);
        foreach (['Bar.tsx', 'DayCell.tsx', 'DayDrawer.tsx'] as $path) {
            $this->assertStringContainsString('href={itemHref(', $this->sources()[$path], $path);
            $this->assertStringContainsString('data-cal-pop=""', $this->sources()[$path], $path);
        }
        $this->assertStringContainsString('href={dayHref(calendar, iso)}', $this->sources()['DayDrawer.tsx']);
        $this->assertStringContainsString('onClick={onPress}', $this->sources()['Calendar.tsx']);
    }

    /**
     * A day is opened, moved and closed in the address with replaceState, through the calendar's one writer.
     *
     * @return void
     */
    public function test_the_open_day_is_replaced_in_the_address(): void {
        $calendar = $this->sources()['Calendar.tsx'];
        $this->assertSame(2, substr_count($calendar, 'writeAddress(withDay(window.location.href, iso), false);'));
        $this->assertSame(1, substr_count($calendar, "writeAddress(withDay(window.location.href, ''), false);"));
        $this->assertSame(0, preg_match('~writeAddress\(withDay\([^;]*, true\)~', $calendar), 'A day never pushes.');
        $this->assertStringContainsString(
            "writeAddress(address(props.urls.page, month, next, moved ? '' : opened.current || ''), write === 'push');",
            $calendar,
            'A month change drops the day; a change of state keeps it.'
        );
    }

    /**
     * The description: one request per event per page life (the cache), the latest opening's answer alone shown.
     *
     * @return void
     */
    public function test_the_description_is_cached_and_the_latest_wins(): void {
        $description = $this->sources()['description.ts'];
        $this->assertMatchesRegularExpression(
            '~let known = cache\.get\(id\);\s*if \(known === undefined\) \{\s*known = fetchOne<EventAnswer>\('
                . '\{methodname: EVENT_METHOD, args: \{eventid: id\}\}\).*?cache\.set\(id, known\);~s',
            $description
        );
        $this->assertStringContainsString(".catch(() => '');", $description, 'A refusal draws nothing.');
        $this->assertStringContainsString(
            "export const EVENT_METHOD = 'core_calendar_get_calendar_event_by_id';",
            $description,
            'Core\'s service is named where it is asked, never in a module the visitor\'s page shares.'
        );
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertMatchesRegularExpression(
            '~const turn = \+\+ticket\.current;.*?describe\(item\.id\)\s*\.then\(\(html\) => \{\s*'
                . 'if \(turn === ticket\.current\) \{\s*setText\(html\);~s',
            $popover
        );
        $this->assertMatchesRegularExpression('~return \(\) => \{\s*ticket\.current\+\+;\s*\};~', $popover, 'Closing drops it.');
        $this->assertStringContainsString(
            "if (item.source !== 'core' || describe === null) {",
            $popover,
            'An event course has no {event} row, and the visitor\'s page asks core nothing.'
        );
    }

    /**
     * The overlap rule's boundaries: touching ends, point items, the clusters, the all-day and multi-day strip.
     *
     * @return void
     */
    public function test_the_overlap_rule(): void {
        $overlap = $this->sources()['overlap.ts'];
        $this->assertStringContainsString(
            'export const intersects = (a: Item, b: Item): boolean => a.timeduration > 0 && b.timeduration > 0'
                . "\n    && a.timestart < b.timestart + b.timeduration && b.timestart < a.timestart + a.timeduration;",
            $overlap
        );
        $this->assertStringContainsString("if (entry.item.timeduration <= 0) {\n            laid.push(", $overlap);
        $this->assertStringContainsString('if (start >= clusterend) {', $overlap);
        $this->assertStringContainsString('let column = ends.findIndex((last) => last <= start);', $overlap);
        $this->assertStringContainsString('if (entry.bar || isAllDay(entry.item, zone)) {', $overlap);
    }

    /**
     * The popover takes the focus when it is shown, once per opened item, and the drawer's hour column spans the
     * day's busy hours with no scroll box of its own.
     *
     * @return void
     */
    public function test_the_popover_focus_waits_for_it_to_be_shown_and_the_hours_are_windowed(): void {
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertStringContainsString('if ((sheet || at !== null) && focused.current !== key) {', $popover);
        $this->assertStringContainsString('focused.current = key;', $popover);
        $overlap = $this->sources()['overlap.ts'];
        $this->assertStringContainsString('let from = Math.max(0, Math.floor(earliest / 60) - 1);', $overlap);
        $this->assertStringContainsString('let to = Math.min(24, Math.ceil(latest / 60) + 1);', $overlap);
        $drawer = $this->sources()['DayDrawer.tsx'];
        $this->assertStringNotContainsString('scrollIntoView', $drawer);
        $this->assertStringContainsString('hourWindow(plan.hours, look.zone)', $drawer);
    }

    /**
     * The popover's primary action is the item's own: core's action only when actionable, the way in only for an event
     * course with one, else "Open".
     *
     * @return void
     */
    public function test_the_primary_action_choice(): void {
        $model = $this->sources()['model.ts'];
        $this->assertStringContainsString(
            "if (item.action !== null && item.action.actionable && actionUrl !== '') {",
            $model
        );
        $this->assertStringContainsString(
            "if (item.source === 'course' && course && (course.join === 'open' || course.join === 'free' "
                . "|| course.join === 'key')) {",
            $model
        );
        $this->assertStringContainsString("return {kind: 'open', url: itemHref(item, courses, home)};", $model);
        $this->assertStringContainsString(
            'const primary = primaryOf(item, look.courses, look.home);',
            $this->sources()['EventPopover.tsx']
        );
    }

    /**
     * An address the answer supplies reaches an href only as a web address (relative, http or https), never a script.
     *
     * @return void
     */
    public function test_answer_addresses_are_checked_before_an_href(): void {
        $model = $this->sources()['model.ts'];
        $this->assertStringContainsString(
            "return url !== '' && (protocol === 'http:' || protocol === 'https:') ? url : fallback;",
            $model
        );
        $this->assertStringContainsString("const own = safeHref(item.url, '');", $model);
        $this->assertStringContainsString("const actionUrl = item.action === null ? '' : safeHref(item.action.url, '');", $model);
        $this->assertStringContainsString('url: safeHref(course.landingurl, itemHref(item, courses, home))', $model);
        $this->assertStringContainsString(
            'href={safeHref(course.url, look.home)}',
            $this->sources()['EventPopover.tsx']
        );
    }

    /**
     * The one trusted-HTML sink is the popover's description, fed by description() alone.
     *
     * @return void
     */
    public function test_the_one_html_sink_is_the_description(): void {
        $sinks = [];
        foreach ($this->sources() as $name => $source) {
            if (substr_count($source, 'dangerouslySetInnerHTML') > 0) {
                $sinks[$name] = substr_count($source, 'dangerouslySetInnerHTML');
            }
        }
        $this->assertSame(['EventPopover.tsx' => 1], $sinks, 'One sink, in the popover.');
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertStringContainsString('dangerouslySetInnerHTML={{__html: text}}', $popover);
        $this->assertStringContainsString('const [text, setText] = useState<string | null>(null);', $popover);
        // The state behind the sink is set to nothing, to null, or to description()'s answer: three calls, no more.
        $this->assertSame(3, substr_count($popover, 'setText('));
        $this->assertSame(1, substr_count($popover, 'setText(html);'));
        $this->assertMatchesRegularExpression('~describe\(item\.id\)\s*\.then\(\(html\) => \{\s*'
            . 'if \(turn === ticket\.current\) \{\s*setText\(html\);~s', $popover);
        // What describe() is: core's description on the logged-in page, nothing on the visitor's.
        $this->assertStringContainsString('describe={description}', $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString("import {description} from './description';", $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString('describe={null}', $this->sources()['PublicCalendar.tsx']);
        $feeders = [];
        foreach ($this->sources() as $name => $source) {
            if (preg_match('~describe=\{(?!null\})~', $source)) {
                $feeders[] = $name;
            }
        }
        $this->assertSame(['Calendar.tsx'], $feeders, 'One page hands the sink a feeder.');
        // The visitor's text is drawn as text.
        $this->assertStringContainsString('data-region="dim-cal-description">{plain}</p>', $popover);
    }

    /**
     * The visitor's page links nothing of core's calendar, which asks a visitor to log in: the drawer draws its link
     * only when it is handed core's address, and the visitor's shell hands none and draws no classic link of its own.
     *
     * @return void
     */
    public function test_the_visitors_page_links_nothing_of_cores_calendar(): void {
        $drawer = $this->sources()['DayDrawer.tsx'];
        $this->assertMatchesRegularExpression(
            '~\{calendar !== null \? \(\s*<a className="dim-cal__dayclassic" href=\{dayHref\(calendar, iso\)\}>~',
            $drawer
        );
        $public = $this->sources()['PublicCalendar.tsx'];
        $this->assertStringContainsString('calendar={null}', $public);
        $this->assertStringNotContainsString('dim-cal__classic', $public);
        $this->assertStringNotContainsString('monthHref', $public);
        $this->assertStringContainsString('calendar={props.urls.calendar}', $this->sources()['Calendar.tsx'], 'Control.');
    }

    /**
     * The calendar's one history writer pushes a month moved and replaces a change of state.
     *
     * @return void
     */
    public function test_the_history_writer(): void {
        $history = $this->sources()['history.ts'];
        $this->assertMatchesRegularExpression(
            "~if \(push\) \{\s*window\.history\.pushState\(\{\}, '', url\);\s*\} else \{\s*"
                . "window\.history\.replaceState\(\{\}, '', url\);~",
            $history
        );
        $calendar = $this->sources()['Calendar.tsx'];
        $this->assertStringContainsString(
            "writeAddress(address(props.urls.page, month, next, moved ? '' : opened.current || ''), write === 'push');",
            $calendar
        );
        $this->assertStringContainsString("request(iso.slice(0, 7), state, 'push', iso, focus);", $calendar);
        $this->assertStringContainsString("request(shown.month, next, 'replace', selected, false);", $calendar);
    }

    /**
     * The month block's client: the latest press wins after each await, a Pending token spans the press to the paint,
     * a failure follows the arrow's own link and nothing else redirects, the page's links are bare, the dots are the
     * page's, and the strings a day's label reads are the ones the renderer hands over.
     *
     * @return void
     */
    public function test_the_month_blocks_client(): void {
        $block = $this->sources()['block.ts'];
        $this->assertMatchesRegularExpression(
            '~const seq = \(tickets\.get\(root\) \?\? 0\) \+ 1;\s*tickets\.set\(root, seq\);.*?'
                . 'await fetchOne<Answer>.*?if \(seq !== tickets\.get\(root\)\) \{\s*return;\s*\}.*?'
                . 'await templates\.renderForPromise\(TEMPLATE, made\);\s*if \(seq !== tickets\.get\(root\)\) \{\s*return;\s*\}'
                . '\s*templates\.replaceNodeContents\(body, html, js\);~s',
            $block
        );
        $this->assertStringContainsString("const pending = new Pending('theme_dimensions/calendar:block');", $block);
        $this->assertMatchesRegularExpression('~\} finally \{\s*pending\.resolve\(\);\s*\}~', $block);
        $this->assertSame(2, substr_count($block, 'redirect('), 'No root to paint, and a failure of the latest press.');
        $this->assertMatchesRegularExpression(
            '~\} catch \(failure\) \{\s*if \(seq === tickets\.get\(root\)\) \{\s*redirect\(href\);~',
            $block
        );
        $this->assertMatchesRegularExpression(
            "~url\.search = '';\s*url\.searchParams\.set\('month', month\);\s*if \(day !== ''\) \{\s*"
                . 'url\.searchParams\.set\(DAY_PARAM, day\);~',
            $block,
            'The page\'s links name the month and the day alone: the page reads the saved state.'
        );
        $this->assertStringContainsString('const dots = dayDots(dayCounts);', $block);
        $this->assertStringNotContainsString('history', $block);
        preg_match('~const STRING_KEYS = \[(.*?)\] as const;~s', $block, $list);
        preg_match_all("~'([a-z_]+)'~", $list[1] ?? '', $names);
        $this->assertSame(
            array_values(array_diff(blocks::STRINGS, ['calendar_nextmonth', 'calendar_prevmonth'])),
            $names[1],
            'The renderer hands over what the label reads, and the two the partial prints.'
        );
    }
}
