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
     * every AMD module asked for is a name of the bridge's type: the page's notification, the blocks' templates, and
     * core's calendar editing, which the editing door adds to the type itself.
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
        $this->assertSame(3, $bridged, 'The page\'s failures, the blocks\' painting and core\'s editing go through the bridge.');
        $this->assertStringContainsString("load('core/notification')", $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString("load('core/templates')", $this->sources()['block.ts']);
        $this->assertStringContainsString("load('core_calendar/repository')", $this->sources()['editing.ts']);
    }

    /**
     * Editing is core's: core's calendar modules are named in the editing door alone, which adds them to the
     * bridge's type; the page refreshes through its own request, writing nothing; "New event", Edit, Delete and the drag
     * follow core's answers; a drop outside core's limits asks nothing; the visitor's shell has none of it.
     *
     * @return void
     */
    public function test_editing_is_cores(): void {
        $sources = $this->sources();
        foreach ($sources as $path => $source) {
            if ($path !== 'editing.ts') {
                $this->assertStringNotContainsString('core_calendar/', $source, "{$path} names one of core's calendar modules.");
            }
        }
        $bridge = (string) file_get_contents(dirname(__DIR__, 3) . '/js/esm/src/amd.ts');
        $this->assertStringContainsString('export interface Modules {', $bridge);
        $this->assertStringNotContainsString('core_calendar', $bridge, 'The bridge names none of core\'s calendar modules.');
        $editing = $sources['editing.ts'];
        $this->assertStringContainsString("declare module '../amd' {", $editing);
        foreach (['modal_event_form', 'crud', 'repository', 'events'] as $module) {
            $this->assertStringContainsString("'core_calendar/{$module}'", $editing);
        }
        $this->assertStringContainsString('crud.registerRemove(jquery(element));', $editing);
        $this->assertStringContainsString('repository.updateEventStartDay(eventid, daytimestamp)', $editing);
        $this->assertMatchesRegularExpression(
            '~modal\.setCourseId\(request\.course\);\s*if \(request\.event !== null\) \{\s*'
                . 'modal\.setEventId\(request\.event\);~',
            $editing
        );

        $page = $sources['Calendar.tsx'];
        $this->assertStringContainsString("return request(showing.current, state, 'none', selected, false);", $page);
        $this->assertStringContainsString('onChange((change) => changes.current(change));', $page);
        $this->assertStringContainsString('(edits?.create ? (', $page);
        $this->assertStringContainsString('if (!entry || !edit || !edit.drag) {', $page);
        $this->assertMatchesRegularExpression(
            "~const refusal = dropRefusal\(move\.edit, day\);\s*if \(refusal\) \{.*?return;\s*\}\s*"
                . "if \(day === move\.from\) \{\s*return;\s*\}\s*"
                . "const pending = new Pending\('theme_dimensions/calendar:move'\);~s",
            $page
        );
        $this->assertStringContainsString(".then(() => refresh('updated'))", $page);
        $this->assertStringContainsString('key={itemKey(poppededit.item)}', $page, 'jQuery\'s cache: one wrapper per event.');
        $this->assertStringContainsString('movable: movable,', $page);
        $this->assertStringContainsString(
            'form(poppededit.item.id, null, poppededit.item.courseid)',
            $page,
            'Core\'s edit: the event\'s course.'
        );

        $actions = $sources['EditActions.tsx'];
        $this->assertStringContainsString('data-type="event"', $actions);
        $this->assertStringContainsString('if (!edit.edit && !edit.delete) {', $actions);
        $this->assertStringContainsString(
            'data-event-title={escapeHtml(item.name)}',
            $actions,
            'Core shows the attribute as HTML.'
        );
        $this->assertStringContainsString('{edit.edit ? (', $actions);
        $this->assertStringContainsString('{edit.delete ? (', $actions);
        $this->assertStringContainsString('if (!edit.delete || !root.current) {', $actions);

        $moves = $sources['moves.ts'];
        $this->assertStringContainsString("(item.source === 'core' ? edits.get(item.id) || null : null)", $moves);
        $this->assertStringContainsString("if (edit.minday !== '' && day < edit.minday) {", $moves);
        $this->assertStringContainsString("if (edit.maxday !== '' && day > edit.maxday) {", $moves);
        $this->assertStringContainsString('(own.includes(today) || !own.length ? null', $moves);

        $this->assertStringContainsString('draggable={look.movable?.(item) ? true : undefined}', $sources['Bar.tsx']);
        $this->assertSame(2, substr_count($sources['DayCell.tsx'], 'draggable={look.movable?.('), 'A line and a card.');
        foreach (['DayCell.tsx', 'WeekGrid.tsx'] as $path) {
            $this->assertStringContainsString('data-cal-drop={look.movable ? iso : undefined}', $sources[$path], $path);
        }
        $this->assertStringNotContainsString('draggable', $sources['WeekGrid.tsx'], 'The hour body is no drag surface.');
        $this->assertStringContainsString(
            "const over = target instanceof Element && target.closest('[role=\"dialog\"]') !== null;",
            $sources['EventPopover.tsx']
        );

        $public = $sources['PublicCalendar.tsx'];
        foreach (['movable', 'EditActions', './editing', 'edits', 'actions='] as $absent) {
            $this->assertStringNotContainsString($absent, $public, "The visitor's shell has no {$absent}.");
        }
    }

    /**
     * No layout is read on the mount path: the reads of the tree are the popover's, when it opens, and the hour box's,
     * when a day or a week opens it and while it scrolls; each grid is measured by one ResizeObserver: the month's watches
     * the rows (the density) and the frame the drawer shares (the phone), the week's the frame alone, and the phone's Day
     * view, drawn with no grid beside it, has one of its own on the same frame, as the Trilhas view does; the step's
     * popover reads as the event popover does, on an opening; and the Trilhas view's days scroller (TrailScroll.tsx) reads
     * itself once per opening (today into view) and on a scroll, an arrow, a target the view asks for, a new zoom (T3c:
     * after placing the same day by arithmetic, through the same measure, so the count is unchanged) or the map, never on
     * another render, and writes every position through one place.
     *
     * @return void
     */
    public function test_no_layout_read_and_one_observer(): void {
        $observers = 0;
        $reads = '~getBoundingClientRect|getClientRects|offset(?:Height|Width|Top|Left)|client(?:Height|Width)'
            . '|scroll(?:Height|Width|Top|Left)|getComputedStyle~';
        foreach ($this->sources() as $path => $source) {
            if (!in_array($path, ['EventPopover.tsx', 'HourScroll.tsx', 'TrailPopover.tsx', 'TrailScroll.tsx'], true)) {
                $this->assertDoesNotMatchRegularExpression($reads, $source, "{$path} reads the layout.");
            }
            $observers += substr_count($source, 'new ResizeObserver(');
        }
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertSame(3, preg_match_all($reads, $popover), 'The opener\'s box, the popover\'s own and the navbar\'s.');
        $this->assertMatchesRegularExpression(
            '~useLayoutEffect\(\(\) => \{\s*if \(sheet \|\| !dialog\.current\) \{.*?getBoundingClientRect\(\);.*?'
                . 'getBoundingClientRect\(\);.*?\}, \[item\.source, item\.id, opener, sheet\]\);~s',
            $popover,
            'Both reads are in the effect of an opening, never of the page\'s mount.'
        );
        $steppop = $this->sources()['TrailPopover.tsx'];
        $this->assertSame(3, preg_match_all($reads, $steppop), 'The step popover: the opener\'s box, its own and the navbar\'s.');
        $this->assertMatchesRegularExpression(
            '~useLayoutEffect\(\(\) => \{\s*if \(sheet \|\| !dialog\.current\) \{.*?getBoundingClientRect\(\);.*?'
                . 'getBoundingClientRect\(\);.*?\}, \[step\.cmid, opener, sheet\]\);~s',
            $steppop,
            'The step popover\'s reads are in the effect of an opening.'
        );
        $box = $this->sources()['HourScroll.tsx'];
        $this->assertSame(9, preg_match_all($reads, $box), 'The opening\'s write and five reads, and the scroll\'s three.');
        $this->assertMatchesRegularExpression(
            '~useLayoutEffect\(\(\) => \{\s*const element = box\.current;.*?scrollHeight.*?\}, \[opening, height\]\);~s',
            $box,
            'The box is measured when a day or a week opens it or the grid hands a new height, not on every render.'
        );
        $scroll = $this->sources()['TrailScroll.tsx'];
        $this->assertSame(
            7,
            preg_match_all($reads, $scroll),
            'The days scroller: its measure (three), the opening\'s two reads, the one fallback write every position goes '
                . 'through (place()), and the map\'s box.'
        );
        $this->assertSame(1, substr_count($scroll, 'element.scrollLeft = left;'), 'One place writes a position.');
        $this->assertSame(
            1,
            substr_count($scroll, 'place(element, keptLeft(was.left, shift.days, was.pitch), false);'),
            'A month added before the days moves the position by arithmetic over the last measure; only a target on its '
                . 'way is measured again, to aim at it from there.'
        );
        $this->assertMatchesRegularExpression(
            '~useLayoutEffect\(\(\) => \{\s*const element = box\.current;.*?scrollWidth.*?\}, \[opening\]\);~s',
            $scroll,
            'The scroller is placed once per opening, not on every render.'
        );
        $this->assertSame(0, substr_count($scroll, 'new ResizeObserver('), 'The scroller observes nothing.');
        $this->assertSame(4, $observers, 'One per grid, one for the Day view and one for the Trilhas view, never per cell.');
        $this->assertSame(1, substr_count($this->sources()['TrailView.tsx'], 'new ResizeObserver('));
        $this->assertStringContainsString('const frame = root.current?.parentElement || null;', $this->sources()['TrailView.tsx']);
        $this->assertStringContainsString(
            'if (entry.target === frame && entry.contentRect.width > 0) {',
            $this->sources()['TrailView.tsx'],
            'The Trilhas view reads the frame\'s width alone.'
        );
        $this->assertSame(1, substr_count($this->sources()['DayView.tsx'], 'new ResizeObserver('));
        $this->assertStringContainsString('const frame = root.current?.parentElement || null;', $this->sources()['DayView.tsx']);
        $this->assertSame(1, substr_count($this->sources()['WeekGrid.tsx'], 'new ResizeObserver('));
        $this->assertStringContainsString('const frame = root.current?.parentElement || null;', $this->sources()['WeekGrid.tsx']);
        $grid = $this->sources()['MonthGrid.tsx'];
        $this->assertStringContainsString('new ResizeObserver(', $grid);
        $this->assertStringContainsString('observer.observe(element);', $grid);
        $this->assertStringContainsString('const frame = grid.current?.parentElement || null;', $grid);
        $this->assertStringContainsString('observer.observe(frame);', $grid);
        foreach (['Calendar.tsx', 'PublicCalendar.tsx'] as $shell) {
            $source = $this->sources()[$shell];
            $this->assertStringContainsString('const narrow = width < PHONE;', $source, 'The phone is the frame\'s width.');
            $this->assertSame(
                $shell === 'Calendar.tsx' ? 4 : 3,
                substr_count($source, 'onWidth={onWidth}'),
                $shell . ': the grids, the Day view and, on the account\'s, the Trilhas view report it.'
            );
        }
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
     * The two seams are fallbacks, one function each: an item's href (and the popover's "Open"), and the
     * drawer's link to core's day view. Nothing redirects: a day opens the drawer, an item the popover.
     *
     * @return void
     */
    public function test_the_seams_are_the_fallbacks(): void {
        $definitions = ['itemHref' => 0, 'primaryOf' => 0];
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
        $this->assertSame(['itemHref' => 1, 'primaryOf' => 1], $definitions);
        foreach (['Bar.tsx', 'DayCell.tsx', 'DayDrawer.tsx'] as $path) {
            $this->assertStringContainsString('href={itemHref(', $this->sources()[$path], $path);
            $this->assertStringContainsString('data-cal-pop=""', $this->sources()[$path], $path);
        }
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
            "writeAddress(inSpan(address(props.urls.page, monthOfSpan(span, select), next, kept), span), write === 'push');",
            $calendar,
            'A move drops the day; a change of state keeps it.'
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
     * The popover takes the focus when it is shown, once per opened item, and the day's busy band (where the drawer's
     * hour box opens) runs from an hour before its first item to an hour after its last; the page itself is never
     * scrolled to show an hour.
     *
     * @return void
     */
    public function test_the_popover_focus_waits_for_it_to_be_shown_and_the_busy_band_is_padded(): void {
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
        // An item with no page of its own has no primary: never the site's home.
        $this->assertMatchesRegularExpression("~if \\(!opens\\(item, courses\\)\\) \\{\\s*return \\{kind: 'none'\\};~", $model);
        $this->assertStringContainsString(
            "safeHref(item.url, '') !== '' || safeHref(courses.get(item.courseid)?.url ?? '', '') !== ''",
            $model
        );
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertStringContainsString(
            "const open = opens(item, look.courses) ? itemHref(item, look.courses, look.home) : '';",
            $popover
        );
        $this->assertStringContainsString("{primary.kind !== 'none' ? (", $popover);
        $this->assertStringContainsString("{primary.kind !== 'open' && open !== '' ? (", $popover);
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
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertStringContainsString(
            "const coursepage = course && course.url !== course.landingurl ? safeHref(course.url, '') : '';",
            $popover
        );
        $this->assertStringContainsString("const image = safeHref(course.image, '');", $popover);
        $this->assertStringNotContainsString('look.home', str_replace(
            ['primaryOf(item, look.courses, look.home)', 'itemHref(item, look.courses, look.home)'],
            '',
            $popover
        ), 'The popover leads to the home through nothing of its own.');
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
        $this->assertSame(['EventPopover.tsx' => 1, 'TrailView.tsx' => 3], $sinks, 'The popover\'s, and core\'s three chevrons.');
        // The Trilhas view's three are core's pix icons the server renders (calendar_page::fold_icons()), handed down as
        // the props' trails.icons and nothing else.
        $view = $this->sources()['TrailView.tsx'];
        foreach (['expanded', 'collapsed', 'collapsedrtl'] as $icon) {
            $this->assertSame(1, substr_count($view, "dangerouslySetInnerHTML={{__html: icons.{$icon}}}"), $icon);
        }
        $this->assertStringContainsString('icons={iconsOf(props)}', $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString(
            'const iconsOf = (props: Props): FoldIcons | undefined => props.trails?.icons;',
            $this->sources()['Calendar.tsx']
        );
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
     * The calendar's review points, read where PHPUnit can see it; Jest is the
     * behaviour gate (js/esm/tests/calendar). A weekend is the date's own weekday; a double-click creates only while
     * core would show "New event"; "Clear filters" is drawn only while a filter is applied.
     *
     * @return void
     */
    public function test_the_owners_review_guards(): void {
        $sources = $this->sources();
        $this->assertStringContainsString('return weekday === 0 || weekday === 6;', $sources['days.ts']);
        $this->assertStringContainsString(').getUTCDay();', $sources['days.ts']);
        $this->assertStringContainsString(
            'create: Create): Create | undefined => (edits?.create ? create : undefined);',
            $sources['Calendar.tsx']
        );
        $this->assertSame(2, substr_count($sources['Calendar.tsx'], 'onCreate={createOnGround}'), 'The month and the week.');
        $this->assertStringContainsString('onCreate={atHourOf(createAt, drawn)}', $sources['Calendar.tsx']);
        $this->assertSame(2, substr_count($sources['Calendar.tsx'], ' = creator(props.edits, '));
        $this->assertStringContainsString('{...groundHandlers(iso, onGround, onCreate)}', $sources['DayCell.tsx']);
        $this->assertMatchesRegularExpression(
            '~\.\.\.\(onCreate \? \{\s*onDoubleClick: \(event: MouseEvent\): void => \{~',
            $sources['DayCell.tsx']
        );
        $this->assertStringContainsString("target.closest('a, button, [data-cal-item]') === null", $sources['DayCell.tsx']);
        // A press on the ground opens the day; the second click of a double-click opens nothing, and the double-click
        // creates on the day the first one pressed.
        $this->assertMatchesRegularExpression(
            '~const pressGround = \(iso: string, hour: number \| null, clicks: number\): void => \{\s*if \(clicks > 1\) \{\s*'
                . 'return;\s*\}\s*pressed\.current = \{iso: iso, hour: hour\};\s*openDay\(iso\);~',
            $sources['Calendar.tsx']
        );
        $this->assertStringContainsString('const at = pressed.current || {iso: iso, hour: hour};', $sources['Calendar.tsx']);
        $this->assertStringContainsString("if (event.pointerType !== 'mouse') {", $sources['DayCell.tsx']);
        $this->assertStringContainsString('<ClearFilters shown={filtered(state, props.options)}', $sources['Calendar.tsx']);
        $this->assertStringNotContainsString('dim-cal__clear', $sources['MoreFilters.tsx'], 'Clear filters left the panel.');
        $this->assertStringContainsString(
            '<ClearFilters shown={on.length !== VISITOR_KEYS.length}',
            $sources['PublicCalendar.tsx']
        );
        $this->assertStringNotContainsString('PublicCalendar', implode('', array_keys(array_filter(
            $sources,
            static fn(string $source): bool => str_contains($source, 'onCreate=')
        ))), 'The visitor\'s page creates nothing.');
    }

    /**
     * Both shells mark the page's root mounted in the frame they hide the server's layer in: the
     * class retires the placeholder and cancels the stylesheet's fail-safe. Jest holds the behaviour.
     *
     * @return void
     */
    public function test_the_mounted_mark_is_set_with_the_server_layer_hidden(): void {
        $sources = $this->sources();
        foreach (['Calendar.tsx', 'PublicCalendar.tsx'] as $file) {
            $this->assertMatchesRegularExpression(
                '~page\?\.classList\.add\(\'dim-cal--mounted\'\);\s*const server = page\?\.querySelector<HTMLElement>'
                . '\(\'\[data-region="dim-cal-server"\]\'\);\s*if \(server\) \{\s*server\.hidden = true;~',
                $sources[$file],
                $file
            );
        }
    }

    /**
     * The event popover keeps below the fixed navbar, read from its box when it opens, and the event types' dropdown
     * closes on Escape through a document listener that exists only while it is open (the list re-renders under a press
     * of "Select all", and the focus falls outside the dropdown). Jest holds the behaviour.
     *
     * @return void
     */
    public function test_the_popover_clears_the_navbar_and_escape_is_the_documents(): void {
        $sources = $this->sources();
        $popover = $sources['EventPopover.tsx'];
        $this->assertStringContainsString("document.querySelector<HTMLElement>('.navbar.fixed-top')", $popover);
        $this->assertStringContainsString('navbarBottom()', $popover);
        $this->assertStringContainsString('const least = reserved + GAP;', $popover);
        $this->assertStringContainsString('Math.max(least, Math.min(top, most - height))', $popover);
        $types = $sources['EventTypes.tsx'];
        $this->assertMatchesRegularExpression(
            "~document\\.addEventListener\\('keydown', escape\\);"
                . "\\s*return \\(\\) => document\\.removeEventListener\\('keydown', escape\\);"
                . "\\s*\\}, \\[open, shut\\]\\);~",
            $types
        );
        $this->assertStringContainsString("if (event.key === 'Escape') {\n                shut(true);", $types);
    }

    /**
     * No page links a view of core's calendar any more: core's calendar/view.php leads to the page, so a link to it
     * would only come back; the drawer and the toolbar draw none, and neither shell is handed core's address.
     *
     * @return void
     */
    public function test_no_page_links_cores_calendar_view(): void {
        $sources = $this->sources();
        foreach ($sources as $path => $source) {
            $this->assertStringNotContainsString('calendar/view.php', $source, $path);
            $this->assertStringNotContainsString('classic', $source, $path);
            $this->assertStringNotContainsString('urls.calendar', $source, $path);
        }
        $this->assertStringContainsString('urls: {page: string, home: string},', $sources['model.ts'], 'The control.');
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
            "writeAddress(inSpan(address(props.urls.page, monthOfSpan(span, select), next, kept), span), write === 'push');",
            $calendar
        );
        $this->assertStringContainsString("request(spanOf(shown.span.view, iso, firstday), state, 'push', iso, focus);", $calendar);
        $this->assertStringContainsString("request(shown.span, next, 'replace', selected, false);", $calendar);
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
    /**
     * The week view: what each gate of the "C4 week view" block breaks, read where PHPUnit can see it. The
     * behaviour is Jest's (js/esm/tests/calendar/weeks.test.ts and WeekGrid.test.tsx).
     *
     * - The week's days start on the viewer's first weekday, and the address names the view and the week as the server
     *   reads them back (exactly `view=week`, the month dropped from a week's address).
     * - The strip holds the bars and the whole-day items; the hour window is the whole week's; the "now" line is drawn
     *   only inside it, and moves with the time elapsed since the paint.
     * - Each shell asks for the span's own days, once; a view switch pushes an entry and keeps the open day its span
     *   holds; the visitor's address carries the span too.
     * - The week grid's one Tab stop roves, a header past the week's ends fetches the adjacent week, and the phone draws
     *   the agenda.
     *
     * @return void
     */
    public function test_the_week_view(): void {
        $weeks = $this->sources()['weeks.ts'];
        $this->assertStringContainsString('return shift(iso, -((utc(parts).getUTCDay() - firstday + WEEK) % WEEK));', $weeks);
        $this->assertStringContainsString(
            ".filter(([name]) => name !== 'month' && name !== VIEW_PARAM && name !== WEEK_PARAM);",
            $weeks
        );
        $this->assertStringContainsString("if (params.get(VIEW_PARAM) !== 'week') {", $weeks);
        $this->assertStringContainsString('.filter((entry) => entry.bar || isAllDay(entry.item, zone))', $weeks);
        $this->assertStringContainsString('window: timed.length ? hourWindow(timed, zone) : null,', $weeks);
        $this->assertStringContainsString(
            'if (column < 0 || minutes < window.from * 60 || minutes >= window.to * 60) {',
            $weeks
        );

        $grid = $this->sources()['WeekGrid.tsx'];
        $this->assertStringContainsString(
            'const line = nowLine(days, (Number(now) || 0) + elapsed, look.zone, body);',
            $grid
        );
        $this->assertStringContainsString('tabIndex={roving && iso !== selected ? -1 : 0}', $grid);
        $this->assertMatchesRegularExpression(
            '~if \(days\.includes\(day\)\) \{\s*onSelect\(day\);\s*\} else \{\s*onWeek\(day\);\s*\}~',
            $grid
        );
        $this->assertStringContainsString(
            "if (phone) {\n        return (\n            <div ref={root} className=\"dim-cal__weekview\"",
            $grid
        );

        foreach (['Calendar.tsx', 'PublicCalendar.tsx'] as $shell) {
            $source = $this->sources()[$shell];
            $this->assertStringContainsString('const days = spanDays(span, firstday);', $source, $shell . ': its own days.');
            $this->assertStringContainsString(
                'const kept = opened.current !== null && (!moved || (switched && days.includes(opened.current)))'
                    . " ? opened.current : '';",
                $source,
                $shell
            );
            $this->assertMatchesRegularExpression(
                "~request\(spanOf\(view, selected, firstday\), (?:state|on), 'push', selected, false\);~",
                $source
            );
            $this->assertStringContainsString('<WeekGrid', $source);
            // The account's switch also offers Trilhas where the page does.
            $this->assertStringContainsString(
                '<ViewSwitch strings={strings} view={shown.span.view} phone={phone} onView={toView}'
                    . ($shell === 'Calendar.tsx' ? ' trails={offered} />' : ' />'),
                $source
            );
        }
        $this->assertStringContainsString('rangeArgs(days[0], days[days.length - 1], next)', $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString(
            'const target = inSpan(publicAddress(props.urls.page, monthOfSpan(span, select), keys, kept), span);',
            $this->sources()['PublicCalendar.tsx']
        );
        $this->assertStringNotContainsString('remember', $this->sources()['weeks.ts'], 'The view is never remembered.');
        // The address's week falls back and is bounded as page::week_from_request() does it (security read F2).
        $this->assertStringContainsString('const valid = parse(week) !== null && year >= 1900 && year <= 2999;', $weeks);
        $this->assertStringContainsString(
            "weekStart(valid ? week : openingOf({view: 'month', month: month}, today), firstday)",
            $weeks
        );
    }

    /**
     * The drawer beside the grid is capped by the grid's height, which each grid's one
     * observer only reports (onHeight, from an entry's contentRect: no layout read, no second observer) and which the
     * calendar renders on the frame as a style prop it owns, so a re-render never drops it; the frame's own entry carries
     * the width alone, since its height includes the drawer. Jest holds the behaviour.
     *
     * @return void
     */
    public function test_the_grids_hand_their_height_to_the_drawer(): void {
        $month = $this->sources()['MonthGrid.tsx'];
        $this->assertStringContainsString(
            'if (entry.target === grid.current && frame && reported > 0 && reported !== lastheight.current) {',
            $month
        );
        $week = $this->sources()['WeekGrid.tsx'];
        // One element's height, reported only on a change; the phone decision reads the frame's width alone (30.7).
        $this->assertStringContainsString(
            'if (entry.target === root.current && reported > 0 && reported !== lastheight.current) {',
            $week
        );
        $this->assertStringContainsString('if (entry.target === frame && entry.contentRect.width > 0) {', $week);
        foreach (['MonthGrid.tsx', 'WeekGrid.tsx'] as $file) {
            $this->assertStringNotContainsString('setProperty', $this->sources()[$file], $file . ' never writes the frame.');
        }
        foreach (['Calendar.tsx', 'PublicCalendar.tsx'] as $file) {
            $this->assertStringContainsString('style={frameStyle(gridheight)}', $this->sources()[$file], $file);
        }
        $this->assertStringContainsString(
            '{[GRID_HEIGHT]: height > 0 ? ' . chr(96) . '${height}px' . chr(96) . ' : undefined} as CSSProperties',
            $this->sources()['days.ts']
        );
    }

    /**
     * The drawer's and the week's hour column is the whole day in a scroll box of its own,
     * which opens on the busy band's first hour (the earliest item a row under it, never cut) or centres the working
     * hours of an empty day without cutting their start; a fade marks an end only while the box can scroll to it; the
     * box is a Tab stop while it holds no focusable item. Jest holds the behaviour (overlap.test.ts, Calendar.test.tsx,
     * WeekGrid.test.tsx).
     *
     * @return void
     */
    public function test_the_hour_column_is_the_whole_day_in_a_scroll_box(): void {
        $overlap = $this->sources()['overlap.ts'];
        $this->assertStringContainsString('export const FULL_DAY: HourWindow = {from: 0, to: 24};', $overlap);
        $this->assertMatchesRegularExpression('~if \(busy\) \{\s*return first;\s*\}~', $overlap);
        $this->assertStringContainsString('return Math.max(0, Math.min(centred, first));', $overlap);
        $this->assertStringContainsString('up: top > 1,', $overlap);
        $this->assertStringContainsString('down: top + viewport < total - 1,', $overlap);

        $box = $this->sources()['HourScroll.tsx'];
        $this->assertStringContainsString(
            'element.scrollTop = boxTopOf(band, busy, element.scrollHeight / hours, element.clientHeight);',
            $box
        );
        $this->assertStringContainsString('}, [opening, height]);', $box);
        $this->assertMatchesRegularExpression(
            '~placed\.current !== opening\) \{\s*placed\.current = opening;\s*touched\.current = false;'
                . '\s*\} else if \(touched\.current\) \{\s*return;~',
            $box,
            'A new opening starts the re-placing again; the reader\'s first wheel, touch or key ends it.'
        );
        foreach (['onWheel={take}', 'onTouchStart={take}', 'onKeyDown={take}'] as $handler) {
            $this->assertStringContainsString($handler, $box);
        }
        foreach (['MonthGrid.tsx', 'WeekGrid.tsx'] as $file) {
            $this->assertStringContainsString('gridheight.current?.(reported);', $this->sources()[$file], $file);
        }
        foreach (['DayDrawer.tsx' => 'height={gridheight}'] as $file => $needle) {
            $this->assertStringContainsString($needle, $this->sources()[$file]);
        }
        $this->assertStringContainsString('tabIndex={focusable ? 0 : undefined}', $box);
        $this->assertStringContainsString(
            '{edges.up ? <span className="dim-cal__hourfade dim-cal__hourfade--up" aria-hidden="true"></span> : null}',
            $box
        );
        $this->assertStringNotContainsString('scrollIntoView', $box, 'The page never scrolls to show an hour.');

        $drawer = $this->sources()['DayDrawer.tsx'];
        $this->assertStringContainsString('const span = FULL_DAY;', $drawer);
        $this->assertStringContainsString('focusable={!busy}', $drawer);
        $week = $this->sources()['WeekGrid.tsx'];
        $this->assertStringContainsString('const body = plan.window || onCreate ? FULL_DAY : null;', $week);
        $this->assertStringContainsString('const box = hourBox(laid, look.zone, FULL_DAY);', $week);
        $this->assertStringContainsString('role="rowgroup"', $week);
    }

    /**
     * "More filters" draws an empty list as every box on, writes the others when one is
     * unchecked and the empty list again when every one is back, never writes "none", and counts a group only when it
     * narrows; "Clear filters" asks the same. Jest holds the behaviour (model.test.ts, Calendar.test.tsx).
     *
     * @return void
     */
    public function test_more_filters_start_with_every_option_on(): void {
        $model = $this->sources()['model.ts'];
        $this->assertStringContainsString(
            'export const shownOn = (kept: number[], value: number): boolean => kept.length === 0 || kept.includes(value);',
            $model
        );
        $this->assertStringContainsString('return next.length === choices.length ? [] : next;', $model);
        $this->assertMatchesRegularExpression('~if \(!next\.length\) \{\s*return kept;\s*\}~', $model);
        $this->assertStringContainsString('&& choices.some((option) => !kept.includes(option.value));', $model);
        $this->assertStringContainsString('|| (options ? narrows(state.model, options.model) : state.model.length > 0)', $model);

        $more = $this->sources()['MoreFilters.tsx'];
        $this->assertStringContainsString('(narrows(state.model, options.model) ? 1 : 0)', $more);
        $this->assertStringContainsString('const checked = shownOn(kept, option.value);', $more);
        $this->assertStringContainsString('disabled={checked && on === 1}', $more);
        $this->assertStringContainsString("data-on={checked ? '1' : '0'}", $more);
        $this->assertStringContainsString('[name]: keptAfter(kept, choices, option.value, event.target.checked),', $more);
        $this->assertStringContainsString('filtered(state, props.options)', $this->sources()['Calendar.tsx']);
    }

    /**
     * The course's button is a button whose text follows the course's model: a string per
     * known model, the generic one over any other model's own option label, "Course page" otherwise.
     *
     * @return void
     */
    public function test_the_course_button_follows_the_model(): void {
        $popover = $this->sources()['EventPopover.tsx'];
        $this->assertMatchesRegularExpression(
            '~if \(course\.model === MODEL_EVENT\) \{\s*return strings\.calendar_eventpage;\s*\}~',
            $popover
        );
        $this->assertStringContainsString(
            'return option ? fill(strings.calendar_modelpage, option.label) : strings.calendar_coursepage;',
            $popover
        );
        $this->assertStringContainsString('className="btn btn-outline-secondary dim-cal__coursebtn"', $popover);
        $this->assertStringContainsString('models={props.options.model}', $this->sources()['Calendar.tsx']);
        $this->assertStringContainsString('models={props.models || []}', $this->sources()['PublicCalendar.tsx']);
        $this->assertSame(\theme_dimensions\local\landingfields::MODEL_EVENT, 2, 'MODEL_EVENT in the client is the server\'s.');
        $this->assertStringContainsString('export const MODEL_EVENT = 2;', $popover);
        $this->assertStringContainsString('export const MODEL_COURSE = 1;', $popover);
        $this->assertSame(\theme_dimensions\local\landingfields::MODEL_COURSE, 1);
    }

    /**
     * The Day view is a phone's. The switch offers it only on a phone; a day span asks for its
     * one day; a day span on a desktop is asked for, in each shell's one request path, as its month with the day opened,
     * and the observer's entry at or above the phone's width asks again; the Day view reads the frame's width alone and
     * draws the drawer's own body; a held touch on an hour creates, a mouse never holds, and a move or a cancel drops
     * it. Jest holds the behaviour (weeks.test.ts, Calendar.test.tsx, PublicCalendar.test.tsx).
     *
     * @return void
     */
    public function test_the_day_view_is_a_phones(): void {
        $weeks = $this->sources()['weeks.ts'];
        $needles = [
            "export const viewsOf = (phone: boolean): View[] => (phone ? ['month', 'week', 'day'] : ['month', 'week']);",
            "export const gridOf = (span: Span, phone: boolean): Grid => (span.view === 'day' && !phone ? 'month' : span.view);",
            "if (span.view === 'day' && !phone) {\n        return {span: spanOf('month', span.day, firstday), open: span.day};",
            "(span.view === 'day' ? null : dayIn(day, days));",
            'return phone ? null : span.day;',
            "if (params.get(VIEW_PARAM) === 'day') {",
            "return parse(day) !== null && year >= 1900 && year <= 2999 ? day : openingOf({view: 'month', month: month}, today);",
            "url.searchParams.append(DAY_PARAM, span.day);\n        rest.filter(([name]) => name !== DAY_PARAM)",
            "if (span.view === 'day') {\n        return [span.day];",
            'return shift(span.day, offset);',
        ];
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $weeks);
        }
        $this->assertStringContainsString('{viewsOf(phone).map((one) => (', $this->sources()['ViewSwitch.tsx']);

        foreach (['Calendar.tsx', 'PublicCalendar.tsx'] as $shell) {
            $source = $this->sources()[$shell];
            $this->assertStringContainsString('const desk = deskOf(want, phoned.current, firstday);', $source, $shell);
            $this->assertMatchesRegularExpression(
                '~if \(desk\.open !== null\) \{\s*opened\.current = desk\.open;\s*setOpenday\(desk\.open\);~',
                $source,
                $shell . ': the day is opened before the address is written.'
            );
            $this->assertMatchesRegularExpression(
                '~const narrow = width < PHONE;\s*phoned\.current = narrow;\s*setPhone\(narrow\);~',
                $source,
                $shell . ': the request path reads the measure before the render that follows it.'
            );
            $this->assertStringContainsString("if (!narrow && span.view === 'day') {", $source, $shell);
            $this->assertStringContainsString('const grid = gridOf(shown.span, phone);', $source, $shell);
            $this->assertStringContainsString('const drawn = drawerOf(shown.span, openday, days, phone);', $source, $shell);
            $this->assertStringContainsString("if (grid === 'day') {", $source, $shell);
            // The first render's guess: the observer's measure replaces it and nothing re-reads it.
            $this->assertStringContainsString('useState<boolean>(guessPhone);', $source, $shell);
            $this->assertStringContainsString('const phoned = useRef<boolean>(phone);', $source, $shell);
            $this->assertStringNotContainsString('guessPhone(', $source, $shell . ': the guess is the initialiser alone.');
        }

        $grid = $this->sources()['MonthGrid.tsx'];
        $tick = "\x60";
        $this->assertStringContainsString(
            'window.matchMedia(' . $tick . '(max-width: ${PHONE + PHONE_GUTTERS - 1}px)' . $tick . ').matches',
            $grid
        );
        $this->assertStringContainsString("typeof window.matchMedia === 'function'", $grid);

        $view = $this->sources()['DayView.tsx'];
        $this->assertStringContainsString('if (entry.target === frame && entry.contentRect.width > 0) {', $view);
        $this->assertStringContainsString(
            '<DayBody look={look} iso={iso} placed={placed} list={false} onCreate={onCreate} />',
            $view
        );
        $this->assertStringContainsString('aria-labelledby={captionid}', $view);

        $drawer = $this->sources()['DayDrawer.tsx'];
        $this->assertStringContainsString('export const DayBody = (', $drawer);
        $this->assertStringContainsString("if (!onCreate || !row || event.pointerType === 'mouse') {", $drawer);
        $this->assertStringContainsString(
            'Math.abs(x - hold.x) > HOLD_SLOP || Math.abs(y - hold.y) > HOLD_SLOP',
            $drawer
        );
        $this->assertStringContainsString('onPointerCancel={onCreate ? drop : undefined}', $drawer);
        $this->assertStringContainsString('list={variant === \'sheet\'}', $drawer);
    }

    /**
     * The popover names the course once: its place never falls back to the course's name, which the course row below
     * already draws; the grid's labels keep the fallback.
     *
     * @return void
     */
    public function test_the_popover_names_the_course_once(): void {
        $this->assertStringContainsString('place: placeOf(item, look, false),', $this->sources()['EventPopover.tsx']);
        $look = $this->sources()['look.ts'];
        $this->assertStringContainsString(
            'export const placeOf = (item: Item, look: Look, fallback: boolean = true): string => {',
            $look
        );
        $this->assertStringContainsString("return place !== '' || !fallback ? place : course.fullname;", $look);
        foreach (['Bar.tsx' => 'placeOf(item, look)', 'DayDrawer.tsx' => 'placeOf(entry.item, look)'] as $file => $call) {
            $this->assertStringContainsString($call, $this->sources()[$file], $file . ' keeps the fallback.');
        }
    }

    /**
     * A visitor is told the answer was cut in a sentence of its own: the logged-in page's names open events alone, and
     * a visitor's cut may be of the category events.
     *
     * @return void
     */
    public function test_the_public_cut_is_told_as_the_visitors(): void {
        $this->assertStringContainsString(
            '{shown.answer.truncated ? <p className="dim-cal__status">{strings.calendar_truncatedpublic}</p> : null}',
            $this->sources()['PublicCalendar.tsx']
        );
        $this->assertStringContainsString(
            '{shown.answer.truncated ? <p className="dim-cal__status">{strings.calendar_truncated}</p> : null}',
            $this->sources()['Calendar.tsx']
        );
    }

    /**
     * The Trilhas view's days scroll in a scroller of block_compass's shape.
     * Its arithmetic reads the measures the stylesheet declares (trails.ts TRACK: the trail column, a day, a row and a
     * bar), so the two must agree; every bar and agenda row is a button named by its step, and no control repeats one
     * out of the tab order (the last column's chip went with the column in T3b); the map is aria-hidden and the arrows are
     * named; a folded trail is the view's own state.
     *
     * @return void
     */
    public function test_the_trail_scroller_and_its_columns(): void {
        $trails = $this->sources()['trails.ts'];
        $scss = (string) file_get_contents(dirname(__DIR__, 3) . '/scss/calendar.scss');
        $pattern = '~export const TRACK = \{label: ([\d.]+), day: ([\d.]+), week: ([\d.]+), month: ([\d.]+), '
            . 'row: ([\d.]+), bar: ([\d.]+)\};~';
        $this->assertSame(1, preg_match($pattern, $trails, $track));
        // The stylesheet spells a fraction without its leading zero (.25rem).
        [, $label, $day, $week, $month, $row, $bar] = preg_replace('~^0\.~', '.', $track);
        $this->assertStringContainsString("--dim-cal-trail-label: {$label}rem;", $scss);
        $this->assertStringContainsString("--dim-cal-trail-day: {$day}rem;", $scss);
        // T3c: the zoom's day widths, each on its modifier of the section, which every column and line reads.
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__trails--week \{\s*--dim-cal-trail-day: ' . preg_quote($week, '~') . 'rem;\s*\}~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__trails--month \{\s*--dim-cal-trail-day: ' . preg_quote($month, '~') . 'rem;\s*\}~',
            $scss
        );
        $this->assertStringContainsString("--dim-cal-trail-row: {$row}rem;", $scss);
        $this->assertStringContainsString("--dim-cal-trail-bar: {$bar}rem;", $scss);
        $this->assertStringNotContainsString('--dim-cal-trail-spill', $scss, 'The last column is gone.');

        $view = $this->sources()['TrailView.tsx'];
        $this->assertStringContainsString('aria-label={stepLabel(step, look, now)}', $view);
        $this->assertStringNotContainsString('tabIndex={', $view, 'No control of the view leaves the tab order.');
        $this->assertStringNotContainsString('dim-cal-trailchip', $view);
        $this->assertStringContainsString('<TrailScroll', $view);
        $this->assertStringNotContainsString('remember', $view, 'A folded trail is never saved.');
        $this->assertStringNotContainsString('history', $view);

        $scroll = $this->sources()['TrailScroll.tsx'];
        $this->assertMatchesRegularExpression('~data-region="dim-cal-trailmap"\s*aria-hidden="true"~', $scroll);
        $this->assertStringContainsString('aria-label={strings.calendar_trailearlier}', $scroll);
        $this->assertStringContainsString('aria-label={strings.calendar_traillater}', $scroll);
    }

    /**
     * T3b: the Trilhas view's continuous months, its courses' rows and its connectors, read where PHPUnit can see them;
     * Jest is the behaviour gate (js/esm/tests/calendar: trails.test.ts, TrailView.test.tsx).
     *
     * - The months held are contiguous and capped, the far side dropped with the first day's move told; the answers
     *   merge by trail id and keep a trail while its period meets what is held (a period with no last end is open).
     * - The first paint's answer and the page's own request are the month and the next (trails::OPENING); a load beside
     *   what is held is one request of one month, or by months a batch of three (trails::MONTHS) the cap leaves room for,
     *   appended or prepended with every prepended day told to the scroller.
     * - A month prepended moves the position by arithmetic over the last measure; the start of what is held asks for the
     *   month before only while scrolling back to it; a target is reached at once under reduced motion, animated
     *   otherwise; a course's popover opens once the scroll has ended.
     * - A settled scroll replaces the address (navigation, never an entry); the rows light their bars for a mouse only.
     * - The connectors are cut at the edges of what is held, broken by a course with no date, none for a folded trail.
     *
     * @return void
     */
    public function test_the_trilhas_months_rows_and_connectors(): void {
        $trails = $this->sources()['trails.ts'];
        $this->assertStringContainsString('export const LOADED_CAP = 6;', $trails);
        $this->assertStringContainsString('export const MONTH_CAP = 12;', $trails);
        $this->assertStringContainsString(
            "export const capOf = (zoom: Zoom): number => (zoom === 'month' ? MONTH_CAP : LOADED_CAP);",
            $trails
        );
        $this->assertStringContainsString('cap: number = capOf(\'day\')', $trails);
        $this->assertSame(1, preg_match('~export const OPENING_MONTHS = (\d+);~', $trails, $opening));
        $this->assertSame(trails::OPENING, (int) $opening[1], 'The first paint asks for the months the page opens on.');
        $this->assertSame(1, preg_match('~export const BATCH_MONTHS = (\d+);~', $trails, $batch));
        $this->assertSame(trails::MONTHS, (int) $batch[1], 'A batch is the most months the service answers.');
        $this->assertStringContainsString(
            "(zoom === 'month' ? Math.max(1, Math.min(BATCH_MONTHS, capOf(zoom) - held)) : 1);",
            $trails,
            'By months a batch, never more than the cap leaves room for; one month otherwise.'
        );
        $this->assertStringContainsString(
            'shift = added.reduce((days, month) => days + monthDays(month).length, 0);',
            $trails,
            'Every month prepended moves the first day.'
        );
        $this->assertStringContainsString('shift -= monthDays(String(months.shift())).length;', $trails);
        $this->assertMatchesRegularExpression(
            '~\} else \{\s*return null;\s*\}\s*return \{loaded: \{months: months, answers: answers\}, shift: shift\};~',
            $trails,
            'A month that is not beside what is held is refused.'
        );
        $this->assertStringContainsString(
            'answer.trails.forEach((trail) => trails.set(trail.id, trails.get(trail.id) ?? trail));',
            $trails
        );
        $this->assertStringContainsString('.filter((trail) => meets(trail, from, to, zone))', $trails);
        $this->assertStringContainsString(
            '(trail.first <= 0 || zone.day(trail.first) <= to) && (trail.last <= 0 || zone.day(trail.last) >= from);',
            $trails
        );
        $this->assertStringContainsString('if (!shown || days.length === 0) {', $trails);
        $this->assertMatchesRegularExpression(
            '~if \(start !== null && end !== null && end < start\) \{\s*end = start;\s*\}~',
            $trails,
            'Dates out of order are righted before the edges are read.'
        );
        $this->assertMatchesRegularExpression(
            '~const at = days\.indexOf\(String\(end\)\);\s*if \(at < 0\) \{\s*return null;~',
            $trails,
            'An end alone past the days held is no milestone at index -1.'
        );
        $this->assertStringContainsString('if (reach === null || next === null) {', $trails);
        $this->assertStringContainsString(
            'return piece.left >= 0 && piece.left + piece.width <= width ? piece : null;',
            $trails
        );

        $scroll = $this->sources()['TrailScroll.tsx'];
        $this->assertStringContainsString("behavior: smooth && !still() ? 'smooth' : 'auto'", $scroll);
        $this->assertStringContainsString('if (Math.abs(left - track.left) < 1 || still()) {', $scroll);
        $this->assertStringContainsString('} else if (moved < 0 && !track.back) {', $scroll);
        $this->assertMatchesRegularExpression(
            '~place\(element, keptLeft\(was\.left, shift\.days, was\.pitch\), false\);.*?\}, \[shift\.key\]\);~s',
            $scroll,
            'The position follows a prepended month once per shift.'
        );
        $this->assertStringContainsString("element.addEventListener('scrollend', settle);", $scroll);
        $this->assertMatchesRegularExpression(
            '~const go = \(target: ScrollTarget\): Promise<boolean> => \{\s*const element = box\.current;\s*arrive\(false\);~',
            $scroll,
            'A target still on its way is superseded, not reached.'
        );
        $this->assertMatchesRegularExpression(
            '~const arrival = arriving\.current;\s*if \(arrival\) \{\s*'
                . 'arrival\.target = shiftedTarget\(arrival\.target, shift\.days\);~',
            $scroll
        );
        $this->assertMatchesRegularExpression('~quiet\.current = setTimeout\(settle, SETTLE_MS\);\s*wait\(\);~', $scroll);
        $this->assertStringContainsString('place(element, openingLeft(openat, ', $scroll);

        $view = $this->sources()['TrailView.tsx'];
        $this->assertMatchesRegularExpression(
            "~scroller\.go\(\{kind: 'step', index: bar\.from\}\)\s*\.then\(\(reached\) => \{\s*"
                . "if \(ticket === asked\.current\) \{\s*arrived\(want\.trail, want\.cmid, reached\);~",
            $view,
            'A course\'s popover opens once the scroll to its bar has ended, for the action that asked for it.'
        );
        $this->assertMatchesRegularExpression('~const aim = \(next: Want\): void => \{\s*asked\.current\+\+;~', $view);
        $this->assertMatchesRegularExpression(
            '~const find = \(trail: Trail, step: TrailStep, row: HTMLElement\): void => \{\s*asked\.current\+\+;~',
            $view
        );
        $this->assertSame(
            2,
            preg_match_all('~pending\.resolve\(\);\s*setWin\(\(was\) => \(\{\.\.\.was, loading: false\}\)\);~', $view),
            'Every load, whatever came of it, pursues what is wanted again.'
        );
        $this->assertMatchesRegularExpression("~ranged\.current = '';\s*onRange\?\.\(null\);~", $view);
        $this->assertStringContainsString("if (target !== '' && target !== failed.current && !full) {", $view);
        $this->assertStringContainsString('const openat = today.startsWith(win.loaded.months[0]) ? todayat : -1;', $view);
        $this->assertStringContainsString('if (was.generation !== set || scoped.current !== askedwith) {', $view);
        // A template literal's quote, spelled by its code: the coding style reads a literal one as a shell command.
        $tick = chr(96);
        $this->assertStringContainsString(
            $tick . '[data-trail="${trailid}"] [data-region="dim-cal-trailstep"][data-cmid="${cmid}"]' . $tick,
            $view,
            'A course\'s popover opens on a bar of its own trail.'
        );
        $this->assertStringContainsString(
            "root.current?.querySelectorAll<HTMLElement>('[data-lit]').forEach((element) => element.removeAttribute('data-lit'));",
            $view
        );
        $this->assertStringContainsString("'--dim-cal-link-start': {$tick}" . '${piece.left}rem' . "{$tick},", $view);
        $this->assertStringNotContainsString("left: {$tick}" . '${piece.left}rem', $view, 'A piece is placed from the start.');
        $this->assertMatchesRegularExpression(
            "~if \(event\.pointerType !== 'mouse'\) \{\s*return;\s*\}\s*root\.current\?\.querySelectorAll~",
            $view,
            'A row and its bar light each other for a mouse only.'
        );
        $this->assertStringContainsString('const links = linksOf(trail.steps, days, look.zone, open, zoom);', $view);
        // Behat waits for a month asked for and for a course's popover to open once its scroll has ended.
        $this->assertSame(2, substr_count($view, "new Pending('theme_dimensions/calendar:trailmonth')"));
        $this->assertSame(1, substr_count($view, "finding.current = new Pending('theme_dimensions/calendar:trailfind');"));
        $this->assertStringContainsString('data-region="dim-cal-trailrow"', $view);
        $this->assertStringNotContainsString('history', $view, 'The page\'s history module writes the address.');

        $calendar = $this->sources()['Calendar.tsx'];
        $this->assertStringContainsString(
            'args: trailArgs(span.month, filtering.current, next.categoryid, next.recursive, OPENING_MONTHS),',
            $calendar
        );
        $this->assertStringContainsString(
            'writeAddress(withTrail(inSpan(address(props.urls.page, month, state), next), filtering.current, zooming.current), '
                . 'false);',
            $calendar,
            'A settled scroll replaces the address.'
        );
        $this->assertStringContainsString(
            "if (span.view !== 'trail' || span.month === month || answered.current !== ticket.current) {",
            $calendar,
            'Nor while the page\'s own request is on its way.'
        );
    }

    /**
     * T3c: the Trilhas timeline's zoom and the courses' dates, read where PHPUnit can see them; Jest is the behaviour gate
     * (js/esm/tests/calendar: trails.test.ts, TrailView.test.tsx).
     *
     * - The zoom is read as the server reads it (week or month exactly), days never written; it travels with the filters
     *   in every write of a Trilhas address (the page's request, a settled scroll, the zoom's own replaced write) and is
     *   read back on Back and Forward; the control is a desktop's.
     * - A day's width is TRACK's of the zoom in every measure, connector and scroll position; a switch keeps the day at
     *   the start of the view by arithmetic over the last measure, once per new zoom.
     * - The weeks start on the viewer's first weekday; no weekend is shaded off the day zoom; a bar too narrow draws no
     *   name and keeps its accessible name; the elbow and the drop narrow with a day, never under their least nor into a
     *   negative length.
     * - A course's row says its dates, a range one month name in a month, and its accessible name carries them.
     *
     * @return void
     */
    public function test_the_trilhas_zoom_and_dates(): void {
        // A template literal's quote, spelled by its code: the coding style reads a literal one as a shell command.
        $tick = chr(96);
        $trails = $this->sources()['trails.ts'];
        $this->assertStringContainsString(
            "export const zoomOf = (value: unknown): Zoom => (value === 'week' || value === 'month' ? value : 'day');",
            $trails
        );
        $this->assertMatchesRegularExpression(
            "~if \(filter && zoom !== 'day'\) \{\s*url\.searchParams\.set\(ZOOM_PARAM, zoom\);~",
            $trails,
            'Days, the zoom a bare address reads as, is never written.'
        );
        $this->assertStringContainsString('Math.max(0, Math.round(left * dayWidth(to) / dayWidth(from)));', $trails);
        $this->assertStringContainsString('const pitch = days > 0 ? total / (days + TRACK.label / day) : 0;', $trails);
        $this->assertStringContainsString('const view = Math.max(0, width - pitch * TRACK.label / day);', $trails);
        $this->assertMatchesRegularExpression(
            "~const least = zoom === 'day' \? TRACK\.day : NAMED_BAR;\s*"
                . "return \(bar\.to - bar\.from \+ 1\) \* dayWidth\(zoom\) >= least;~",
            $trails,
            'By days a one-day bar keeps its name; off them the least is NAMED_BAR.'
        );
        $this->assertStringContainsString('export const NAMED_BAR = 3.5;', $trails);
        $this->assertStringContainsString('if (index === 0 || weekdayOf(iso) === firstday) {', $trails);
        $this->assertStringContainsString(
            'const elbow = start + Math.max(LINK.tight, Math.min(LINK.stub, (end - start) / 2));',
            $trails,
            'Over a narrow gap the elbow comes half way, never under its least.'
        );
        $this->assertStringContainsString('across(bmid, elbow, Math.max(elbow, end - LINK.head)),', $trails);
        $this->assertStringContainsString('right(Math.max(end, elbow + LINK.head), bmid),', $trails);
        $this->assertStringContainsString(
            'const x = b.from * day + Math.min(LINK.stub, Math.max(LINK.least, day / 2));',
            $trails,
            'The drop\'s offset into its day scales with the day.'
        );
        $this->assertMatchesRegularExpression(
            '~export const linksOf = \(steps: TrailStep\[\], days: string\[\], zone: Zone, shown: boolean, zoom: Zoom = \'day\'\)'
                . ': LinkPiece\[\] => \{\s*if \(!shown \|\| days\.length === 0\) \{\s*return \[\];\s*\}\s*'
                . 'const day = dayWidth\(zoom\);\s*const width = days\.length \* day;~',
            $trails
        );
        $this->assertStringContainsString("return tight ? '–' : part.value;", $trails);
        $this->assertMatchesRegularExpression(
            "~if \(separates\(parts, index\)\) \{.*?\}\s*return /.p\{L\}/u\.test\(part\.value\) \? ' ' : part\.value;~s",
            $trails,
            'Only a date\'s own words are compacted, never the range\'s separator.'
        );
        $this->assertStringContainsString(
            "parts[index - 1]?.source === 'startRange' && parts[index + 1]?.source === 'endRange';",
            $trails
        );
        $this->assertStringContainsString('const crosses = cell.first.slice(0, 7) !== cell.last.slice(0, 7);', $trails);
        $this->assertMatchesRegularExpression('~days: crosses \? ~', $trails, 'A week across a month names the end\'s month.');
        $this->assertStringContainsString(
            'const turn = end - Math.max(LINK.tight, Math.min(LINK.detour, (start - end) / 2, end - LINK.line / 2));',
            $trails,
            'The detour narrows with the gap and stays inside the days held.'
        );
        $this->assertStringContainsString('across(bmid, turn, Math.max(turn, end - LINK.head)),', $trails);
        $this->assertStringContainsString('const last = to < from ? from : to;', $trails, 'Dates out of order: the start day.');
        $this->assertStringContainsString('export const OPEN_DAYS = 30;', $trails);
        $this->assertStringContainsString(
            'until !== null && until > start ? until : daysAfter(start, OPEN_DAYS - 1);',
            $trails,
            'A start with no end is its first 30 days: the start day and 29 more.'
        );
        $this->assertStringContainsString(
            'const open = start !== null && end === null;',
            $trails,
            'An expected completion date is an end: such a course is not open.'
        );
        $this->assertStringContainsString('milestone: !open && expected && end !== null && end <= last,', $trails);
        $this->assertStringContainsString(
            'to: step.end <= 0 ? from + OPEN_DAYS - 1 : Math.max(from, daysBetween(first, zone.day(step.end)))',
            $trails,
            'The connectors leave the end of an open course\'s 30 days, as its bar ends there.'
        );
        $this->assertStringContainsString(
            'trail.first > 0 && trail.last <= 0 ? openTrailEnd(trail, zone) : null',
            $trails,
            'The line of an open trail ends where its open step\'s 30 days end.'
        );
        $this->assertStringContainsString('if (day !== null && (latest === null || day > latest)) {', $trails);
        $this->assertStringContainsString('export const CHIP_GAP = 0.375;', $trails);
        $this->assertStringContainsString(
            'bar.open && bar.to + 1 < count ? Math.round(((bar.to + 1) * dayWidth(zoom) + CHIP_GAP) * 10000) / 10000 : null;',
            $trails,
            'The chip follows an open bar, a gap after its last day, only while that day is not the last held.'
        );
        $this->assertStringContainsString(
            'step.start > 0 && step.end <= 0 ? look.strings.calendar_trailopenfull',
            $this->sources()['TrailPopover.tsx'],
            'The full phrase is an open step\'s alone.'
        );
        $this->assertStringContainsString(
            '[stepWhen(step, look), stepOpenEnd(step, look)].filter((part) => part !== \'\').join(\' · \')',
            $this->sources()['TrailPopover.tsx'],
            'The popover\'s dates line says it in full.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~getBoundingClientRect|offset(?:Height|Width)|client(?:Height|Width)|scroll(?:Width|Left)|getComputedStyle~',
            $trails,
            'Every zoom\'s position is arithmetic.'
        );

        $scroll = $this->sources()['TrailScroll.tsx'];
        $this->assertStringContainsString(
            'trackOf(element.scrollLeft, element.scrollWidth, element.clientWidth, days, dayWidth(zoom));',
            $scroll,
            'Every measure reads a day at the zoom\'s width.'
        );
        $this->assertStringContainsString(
            'place(element, openingLeft(openat, trackOf(0, element.scrollWidth, element.clientWidth, days, dayWidth(zoom))), '
                . 'false);',
            $scroll
        );
        $this->assertMatchesRegularExpression(
            '~const from = zoomed\.current;\s*if \(!element \|\| !was \|\| from === zoom\) \{\s*return;\s*\}\s*arrive\(false\);\s*'
                . 'place\(element, zoomedLeft\(was\.left, from, zoom\), false\);\s*paint\(measure\(element\), 0\);\s*'
                . '\}, \[zoom\]\);~',
            $scroll,
            'A new zoom keeps the day at the start by arithmetic over the last measure, once per zoom, at once.'
        );
        $this->assertMatchesRegularExpression(
            '~const paint = \(track: Track, moved: number\): void => \{\s*last\.current = track;\s*zoomed\.current = zoom;~',
            $scroll,
            'The zoom of a measure is recorded with it: an opening at the new zoom is not rescaled.'
        );
        $this->assertSame(0, substr_count($scroll, "zoomed.current = zoom;\n        if (!element"), 'Nor by the zoom effect.');
        $this->assertMatchesRegularExpression(
            '~if \(!element \|\| !last\.current\) \{\s*return;\s*\}\s*paint\(measure\(element\), 0\);\s*\}, \[days\]\);~',
            $scroll,
            'More days held are measured again, so the next month is asked for while they fit the scroller.'
        );

        $view = $this->sources()['TrailView.tsx'];
        $this->assertStringContainsString('namedBar(bar, zoom) ? step.name : null);', $view);
        $this->assertStringContainsString('aria-label={stepLabel(step, look, now)}', $view, 'A bar with no name is still named.');
        $this->assertStringContainsString(
            "aria-label={dated(step) ? fill(look.strings.calendar_trailshow, {$tick}" . '${step.name}, ${dates}'
                . "{$tick}) : undefined}",
            $view
        );
        $this->assertStringContainsString('data-region="dim-cal-traildates"', $view);
        $this->assertStringContainsString("const bydays = zoom === 'day';", $view);
        $this->assertStringContainsString(
            '{bydays ? days.map((iso, index) => (isWeekend(iso) ? (',
            $view,
            'No weekend is shaded off the days.'
        );
        $this->assertStringContainsString('const cells = bydays ? [] : weekCells(days, firstday);', $view);
        $this->assertStringContainsString(
            "+ (!phone && zoom !== 'day' ? {$tick} dim-cal__trails--" . '${zoom}' . "{$tick} : '')}",
            $view,
            'The zoom is a desktop\'s: the phone\'s agenda keeps no day width.'
        );
        $this->assertStringContainsString('{onZoom && !phone ? (', $view);
        $this->assertStringContainsString('aria-pressed={one === zoom}', $view);
        $this->assertStringContainsString('data-region="dim-cal-trailnowtag"', $view);
        $this->assertStringContainsString(
            "+ (bar.after ? ' dim-cal__trailbar--after' : '')",
            $view,
            'An open bar is cut as any is.'
        );
        $this->assertStringContainsString('{span && !span.after && !span.open ? (', $view, 'An open trail has no end diamond.');
        $this->assertMatchesRegularExpression(
            '~className="dim-cal__trailnoend"\s*style=\{\{\'--dim-cal-chip-start\': .*?\} as CSSProperties\}\s*'
                . 'aria-hidden="true"\s*>\{look\.strings\.calendar_trailopenchip\}~',
            $view,
            'The chip is decorative, in the bar\'s row.'
        );
        $this->assertStringContainsString(
            '[step.name, stepWhen(step, look), stepOpenEnd(step, look), stateLine(step, look, now)]',
            $view,
            'The bar\'s accessible name says it in full.'
        );
        $this->assertStringContainsString("const full = near === 'end' && months.length >= capOf(zoom) && first === 0;", $view);
        $this->assertStringContainsString('extendLoaded(was.loaded, got, capOf(zooming.current))', $view);
        $this->assertStringContainsString('const count = batchOf(zooming.current, win.loaded.months.length);', $view);
        $this->assertStringContainsString('fetch(ahead ? target : monthAfter(target, 1 - count), count)', $view);
        $this->assertStringContainsString('fetch(target, OPENING_MONTHS)', $view);
        $this->assertStringContainsString('{weekLabelled(cell, zoom) ? (', $view);
        $trailsts = $this->sources()['trails.ts'];
        $this->assertStringContainsString("zoom === 'week' && cell.length * dayWidth(zoom) >= WEEK_LABEL_LEAST;", $trailsts);
        $this->assertStringContainsString("if (target !== '' && target !== failed.current && !full) {", $view);
        $this->assertStringNotContainsString('history', $view, 'The page\'s history module writes the address.');

        $calendar = $this->sources()['Calendar.tsx'];
        $this->assertMatchesRegularExpression(
            '~const changeZoom = \(next: Zoom\): void => \{.*?writeAddress\(withTrail\(window\.location\.href, '
                . 'filtering\.current, next\), false\);~s',
            $calendar,
            'A zoom replaces the address: navigation, never an entry.'
        );
        $this->assertStringContainsString(
            'withTrail(inSpan(address(props.urls.page, span.month, next), span), filtering.current, zooming.current),',
            $calendar,
            'The page\'s request keeps the zoom.'
        );
        $this->assertStringContainsString('zooming.current = readZoom(window.location.href);', $calendar, 'Back and Forward.');
        $this->assertStringContainsString('const [zoom, setZoom] = useState<Zoom>(() => zoomOf(props.trails?.zoom));', $calendar);
        $this->assertStringNotContainsString('remember(zoom', $calendar, 'Never remembered.');
    }

    /**
     * T3d: the Trilhas timeline's folds, read where PHPUnit can see them; Jest is the behaviour gate
     * (js/esm/tests/calendar/TrailView.test.tsx, "the folds (T3d)").
     *
     * - A trail takes its fold when it is first drawn: open alone, folded beside another; it keeps it while held, and a
     *   new answer of the page starts again from none. The reader's own fold wins over it and is kept across every load,
     *   except that a new answer holding one trail opens it. A course's popover never opens on a folded trail.
     * - Beside the heading, a toggle expands every trail when any is folded and folds every one when all are open, its
     *   aria-expanded saying "all open"; drawn for two trails or more, and outside the head row's decorative cells.
     * - Folds are the view's own: never saved, no request, no new layout read.
     *
     * @return void
     */
    public function test_the_trilhas_folds(): void {
        $trails = $this->sources()['trails.ts'];
        $this->assertMatchesRegularExpression(
            '~const fresh = ids\.filter\(\(id\) => !\(id in was\)\);\s*if \(fresh\.length === 0\) \{\s*return was;~',
            $trails,
            'A trail held already keeps the fold it arrived with.'
        );
        $this->assertStringContainsString('const alone = ids.length <= 1;', $trails, 'Open alone, folded beside another.');
        $this->assertStringContainsString(
            'export const openOf = (id: number, chosen: Folds, arrived: Folds): boolean => chosen[id] ?? arrived[id] ?? true;',
            $trails,
            'The reader\'s own fold first.'
        );
        $this->assertStringContainsString('next[id] = !allopen;', $trails);
        $this->assertStringContainsString(
            'if (trails.length !== 1 || !(trails[0].id in chosen)) {',
            $trails,
            'A new answer holding one trail drops the reader\'s fold of it.'
        );

        $view = $this->sources()['TrailView.tsx'];
        $this->assertStringContainsString(
            'const arriving = fresh ? {} : arrivedOf(arrivals, ids);',
            $view,
            'A new answer starts again.'
        );
        $this->assertStringContainsString('const isOpen = (id: number): boolean => openOf(id, folds, arriving);', $view);
        $this->assertStringContainsString('const allopen = ids.every(isOpen);', $view);
        $this->assertStringContainsString('setFolds((was) => freshFolds(was, answer));', $view);
        $this->assertStringContainsString(
            "if (bar && !bar.closest('[hidden]')) {",
            $view,
            'A popover never opens on a bar its trail folded away while the scroll ran.'
        );
        $this->assertStringContainsString('setFolds((was) => foldedAll(was, ids, allopen));', $view);
        $this->assertStringContainsString('{ids.length > 1 ? (', $view, 'No toggle for one trail or none.');
        $this->assertStringContainsString('data-action="dim-cal-trailfoldall"', $view);
        $this->assertStringContainsString('aria-expanded={allopen}', $view);
        $this->assertMatchesRegularExpression(
            '~aria-label=\{allopen \? look\.strings\.calendar_trailcollapseall\s*: look\.strings\.calendar_trailexpandall\}~',
            $view
        );
        $this->assertStringContainsString('<div className="dim-cal__trailrow dim-cal__trailrow--head">', $view);
        $this->assertStringContainsString('<span aria-hidden="true">{look.strings.calendar_trailheading}</span>', $view);
        $this->assertSame(2, substr_count($view, '{chevrons()}'), 'Both folds wear core\'s chevrons, the same three icons.');
        $this->assertStringNotContainsString('remember', $view, 'A fold is never saved.');
    }
}
