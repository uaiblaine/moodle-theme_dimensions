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
 * Theme Dimensions - Tests for the invariants of the showcase's calendar module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/daterange.js by reading its source.
 *
 * Same shape and reasoning as {@see amd_source_test}: plugin CI runs no JavaScript unit tests, so
 * the properties of this module whose loss costs a wrong page rather than a screenshot are pinned
 * by reading its source, each assertion scoped to the function it is about. The build-currency
 * assertion at the foot is the only one that reads what a browser is actually served; without it
 * every other assertion here could be green over a module no browser runs.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class daterange_source_test extends \basic_testcase {
    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = $this->plugin_root() . '/amd/src/daterange.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The source the committed build was actually made from.
     *
     * @return string
     */
    private function built_from(): string {
        $path = $this->plugin_root() . '/amd/build/daterange.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $source = $this->source();
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $source, $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in the module.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The extractor really extracts one function and not the file around it.
     *
     * Without this, every scoped assertion below would also pass if body() returned the whole
     * module.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $press = $this->body('press');
        $close = $this->body('close');
        $this->assertStringContainsString('mode', $press);
        $this->assertStringNotContainsString('aria-expanded', $press, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('aria-expanded', $close);
    }

    /**
     * A press of a day sets the bound of the box the calendar is in the mode of, and never runs backwards.
     *
     * Either bound is a period on its own (criteria), so a press is never "half" of anything. In the
     * start's mode the day is the start, an end before it is dropped, and the mode moves on to the
     * end, which is what makes two presses a range. In the end's mode the day is the end, unless it
     * is before the start: then it begins the period again from that day and the end is still asked
     * for - the slip of a reader who pressed a start and then an earlier day. A press applies nothing
     * and keeps the popover open; the reader ends the choice.
     *
     * Changes that must make it fail: the mode left on the start after a start (the second press
     * would move the start again); the end kept when a later start is pressed (a period running
     * backwards); the earlier day taken as an end (a period running backwards); a press closing the
     * popover or applying the period.
     */
    public function test_a_press_sets_the_bound_of_the_mode_and_never_runs_backwards(): void {
        $press = $this->body('press');
        $this->assertMatchesRegularExpression(
            "~if \(mode === FROM\) \{\s*draftfrom = iso;\s*"
                . "if \(draftto !== '' && draftto < iso\) \{\s*draftto = '';\s*\}\s*mode = TO;~",
            $press,
            'In the start\'s mode the day is the start, an earlier end goes, and the end is asked for next.'
        );
        $this->assertMatchesRegularExpression(
            "~\} else if \(draftfrom !== '' && iso < draftfrom\) \{\s*draftfrom = iso;\s*draftto = '';\s*"
                . "\} else \{\s*draftto = iso;\s*\}~",
            $press,
            'In the end\'s mode the day is the end, unless it falls before the start, which it then replaces.'
        );
        /* Every press keeps the focus inside the popover, which is what makes a second press
           possible. Repainting the grids replaces every cell, so the day the reader stands on
           leaves the document and the focus falls to the body - and this control closes itself
           when the focus leaves it, so without focusDay() the first press would shut it. */
        $this->assertMatchesRegularExpression('~focused = iso;\s*repaint\(\);\s*$~', $press);
        $this->assertMatchesRegularExpression(
            '~paintGrid\(\);\s*focusDay\(\);\s*$~',
            $this->body('repaint'),
            'A repaint that does not put the focus back closes the popover it just redrew.'
        );
        foreach (['close(', 'apply(', 'commit(', 'onpick('] as $call) {
            $this->assertStringNotContainsString(
                $call,
                $press,
                'A press describes a period and ends nothing: ' . $call . ' belongs to the ways out.'
            );
        }
    }

    /**
     * Both boxes open the one popover, each in its own mode, and a box never closes it.
     *
     * The two boxes carry one aria-controls and both say they are expanded while it is open; which
     * bound the next press serves is the mode, and the box of the mode is where the focus goes back.
     * A box pressed while the popover is open switches the mode rather than closing it: after a
     * start the end is the mode, and a reader pressing the end box to say so must not lose the start.
     *
     * Changes that must make it fail: the open popover closed by a box (`close` in onBox); the mode
     * not taken from the box pressed.
     */
    public function test_both_boxes_open_one_popover_each_in_its_own_mode(): void {
        $box = $this->body('onBox');
        $this->assertStringContainsString("const bound = box.dataset.bound === TO ? TO : FROM;", $box);
        $this->assertMatchesRegularExpression(
            '~if \(isOpen\(\)\) \{\s*setMode\(bound\);\s*\} else \{\s*open\(bound\);\s*\}~',
            $box
        );
        $this->assertStringNotContainsString('close(', $box, 'A box never shuts the popover.');

        $open = $this->body('open');
        $this->assertStringContainsString(
            "[FROM, TO].forEach((name) => boxes[name].setAttribute('aria-expanded', 'true'));",
            $open,
            'Both boxes control the one popover, so both say it is open.'
        );
        $this->assertMatchesRegularExpression('~setMode\(bound\);\s*$~', $open);
        $mode = $this->body('setMode');
        $this->assertStringContainsString('mode = bound === TO ? TO : FROM;', $mode);
        $this->assertStringContainsString('field = boxes[mode];', $mode, 'The focus goes back to the box of the mode.');
        $this->assertStringContainsString('focused = own || other || today;', $mode);
        $this->assertStringContainsString('bringIntoView(focused);', $mode, 'The day stood on is always on screen.');

        $click = $this->body('onClick');
        $this->assertMatchesRegularExpression(
            '~const box = target\.closest\(SELECTORS\.box\);\s*if \(box\) \{\s*event\.preventDefault\(\);\s*onBox\(box\);~',
            $click
        );
        $this->assertStringContainsString("box: '[data-action=\"dim-daterange-open\"]',", $this->source());
    }

    /**
     * "Done" and a press outside apply the draft, Escape discards it, and one function applies.
     *
     * Three ways out of an open calendar, and they are not the same answer: the button and a press
     * outside end a choice ("that will do"), while Escape and the keyboard walking out dismiss the
     * control ("never mind") and put back the period that was in force. Any draft is applied as it
     * stands, a start or an end alone included, because each is a period the server answers.
     *
     * Every change of the period in force goes through apply(), which is the one place the listing
     * is told and which tells it only of a change, so clearing a bound that is not set does not
     * fetch the page again. A press outside applies only while the popover is open: the period's
     * chip under the filter line is outside the control too, and applying a draft on every such
     * press would fetch a page between the chip's pointerdown and its click.
     *
     * Changes that must make it fail: the change guard in apply() made always false ("Done" would
     * close the popover and apply nothing); the draft reset deleted from close() (a period dismissed
     * with Escape would reappear in the boxes); the open check dropped from the outside press.
     */
    public function test_done_and_a_press_outside_apply_the_draft_and_escape_discards_it(): void {
        $commit = $this->body('commit');
        $this->assertMatchesRegularExpression(
            '~const start = draftfrom;\s*const end = draftto;\s*close\(restore\);\s*apply\(start, end\);~',
            $commit,
            'The draft is what is applied, after the popover has closed.'
        );

        $apply = $this->body('apply');
        $this->assertStringContainsString('const changed = start !== from || end !== to;', $apply);
        $this->assertMatchesRegularExpression('~from = start;\s*to = end;\s*draftfrom = start;\s*draftto = end;~', $apply);
        $this->assertMatchesRegularExpression(
            '~if \(!changed\) \{\s*return;\s*\}\s*if \(onpick\) \{\s*onpick\(from, to\);~',
            $apply,
            'The listing is told, once and only of a change, by the one place a period leaves this module.'
        );
        $this->assertSame(
            1,
            substr_count($this->source(), 'onpick(from, to)'),
            'There is exactly one place a period leaves this module.'
        );

        $close = $this->body('close');
        $this->assertMatchesRegularExpression(
            '~pop\.hidden = true;.*?draftfrom = from;\s*draftto = to;\s*paintBoxes\(\);~s',
            $close,
            'A draft dies with the popover: that is what Escape discarding it means.'
        );
        $this->assertStringNotContainsString('apply(', $close, 'Closing applies nothing.');

        $click = $this->body('onClick');
        $this->assertMatchesRegularExpression(
            '~if \(target\.closest\(SELECTORS\.done\)\) \{\s*event\.preventDefault\(\);\s*commit\(true\);~',
            $click,
            'The footer\'s own control is what ends the choice.'
        );

        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~pointerdown.*?if \(isOpen\(\) && event\.target instanceof Node && !root\.contains\(event\.target\)\) \{\s*'
                . 'commit\(false\);~s',
            $listen,
            'A press outside the OPEN control applies the draft, which is "that will do" without the button.'
        );
        $this->assertMatchesRegularExpression(
            '~focusout.*?close\(false\);~s',
            $listen,
            'While the keyboard walking out of it discards, exactly as Escape does.'
        );
    }

    /**
     * Each box's clearing control drops its own bound; the footer's drops the period.
     *
     * One action and one handler for all of them, told apart by data-bound. A box's control applies
     * at once - it is a one-press intent, like a quick option - and leaves the other bound as the
     * boxes show it, so clearing the start of a range leaves an end alone, which is a period. The
     * focus goes to the box, because the control hides itself once its bound is gone and would
     * otherwise drop the focus to the body and close the popover with it.
     *
     * Changes that must make it fail: a box's control dropping the whole period (the other bound
     * lost); the focus not handed to the box.
     */
    public function test_each_box_clears_its_own_bound_and_the_footer_the_whole_period(): void {
        $drop = $this->body('drop');
        $this->assertStringContainsString("const start = bound === TO ? draftfrom : '';", $drop);
        $this->assertStringContainsString("const end = bound === FROM ? draftto : '';", $drop);
        $this->assertMatchesRegularExpression('~apply\(start, end\);\s*$~', $drop, 'A clearing control applies at once.');

        $clear = $this->body('onClear');
        $this->assertStringContainsString(
            "const bound = control.dataset.bound === FROM || control.dataset.bound === TO ? control.dataset.bound : '';",
            $clear,
            'No data-bound, or one this module does not know, is the whole period.'
        );
        $this->assertMatchesRegularExpression("~drop\(bound\);\s*if \(bound !== ''\) \{\s*boxes\[bound\]\.focus\(\);~", $clear);

        $click = $this->body('onClick');
        $this->assertMatchesRegularExpression(
            '~const clear = target\.closest\(SELECTORS\.clear\);\s*if \(clear\) \{\s*'
                . 'event\.preventDefault\(\);\s*onClear\(clear\);~',
            $click,
            'Clearing is matched by the ACTION, so the boxes\' controls and the footer\'s are one rule.'
        );
        /* The period's chip under the filter line is the server's (category_toolbar::chips()) and the
           listing's to paint from every answer: this module draws none, so no second writer of that
           region composes a sentence the server did not. */
        $this->assertStringNotContainsString('paintPill', $this->source());
        $this->assertStringNotContainsString('dim-daterange-pill', $this->source());
    }

    /**
     * A period that arrives from outside is read the way the server reads it, and each shape has its sentence.
     *
     * period() is criteria's rule in the client: a bound that is not a calendar day is dropped and
     * the other stands alone, and a pair given backwards is the same period in order. The listing's
     * module and the panel's read the address through it, because the web service refuses a pair
     * given backwards. The sentence naming a period is the server's chip's, never this module's.
     *
     * Changes that must make it fail: the swap deleted from period(); the month-length check dropped
     * from parse() (the 31st of September reaching a service that refuses it).
     */
    public function test_a_period_from_outside_is_read_as_the_server_reads_it(): void {
        $period = $this->body('period');
        $this->assertStringContainsString("const first = parse(start) ? String(start) : '';", $period);
        $this->assertStringContainsString("const last = parse(end) ? String(end) : '';", $period);
        $this->assertMatchesRegularExpression(
            "~if \(first !== '' && last !== '' && last < first\) \{\s*return \{from: last, to: first\};~",
            $period,
            'A pair given backwards is put in order, as criteria puts it.'
        );
        $this->assertStringContainsString(
            'if (parts.m < 1 || parts.m > 12 || parts.d < 1 || parts.d > monthLength(parts.y, parts.m)) {',
            $this->body('parse'),
            'A day the month does not have is no date, as checkdate() says on the server.'
        );
        foreach (['set', 'mount'] as $entry) {
            $this->assertStringContainsString('period(', $this->body($entry), $entry . '() reads its period through period().');
        }

        $this->assertStringNotContainsString(
            'category_daterangepill',
            $this->source(),
            'The period\'s sentence is written by the server, in the site timezone, and nowhere here.'
        );
    }

    /**
     * The marking: each bound is a filled end, and the days between are tinted only when both are set.
     *
     * A start alone or an end alone marks its one day: the period it keeps runs off the calendar, so
     * tinting the rest of a month would claim an end that is not there.
     */
    public function test_one_bound_marks_its_day_and_two_tint_the_days_between(): void {
        $cell = $this->body('dayCell');
        $this->assertStringContainsString("const both = draftfrom !== '' && draftto !== '';", $cell);
        $this->assertStringContainsString('const edge = iso === draftfrom || iso === draftto;', $cell);
        $this->assertStringContainsString('const inside = both && iso > draftfrom && iso < draftto;', $cell);
        $this->assertStringContainsString("cell.setAttribute('aria-selected', edge || inside ? 'true' : 'false');", $cell);
        $this->assertStringContainsString("cell.classList.toggle('dim-daterange__day--in', inside);", $cell);
        $this->assertStringContainsString("cell.classList.toggle('dim-daterange__day--edge', edge);", $cell);
    }

    /**
     * Escape closes the popover and returns the focus to the box; the arrows walk the days.
     *
     * A disclosure the keyboard can open and not close is a trap: the grids hold a roving tabindex,
     * so Tab leaves the whole control rather than walking its days, and a reader who opened it has
     * exactly one way back to where they were. The arrows answer on a day alone, so a box keeps its
     * own keys, and a step off the months on screen brings its month on.
     *
     * Changes that must make it fail: `close(true)` changed to `close(false)` in onKey(), which
     * leaves the focus on a day cell inside a hidden popover.
     */
    public function test_escape_closes_the_popover_and_gives_the_box_its_focus_back(): void {
        $key = $this->body('onKey');
        $this->assertMatchesRegularExpression(
            "~if \(event\.key === 'Escape'\) \{\s*event\.preventDefault\(\);\s*close\(true\);~",
            $key,
            'Escape closes, and closes with the focus restored.'
        );
        $this->assertMatchesRegularExpression('~if \(restore && field\) \{\s*field\.focus\(\);~', $this->body('close'));
        $steps = [
            'ArrowLeft: () => shift(focused, -1)',
            'ArrowRight: () => shift(focused, 1)',
            'ArrowUp: () => shift(focused, -WEEK)',
            'ArrowDown: () => shift(focused, WEEK)',
            'PageUp: () => shiftMonth(focused, -1)',
            'PageDown: () => shiftMonth(focused, 1)',
        ];
        foreach ($steps as $step) {
            $this->assertStringContainsString($step, $key, 'The grid moves with ' . $step . '.');
        }
        $this->assertStringContainsString('if (!step || !isOpen() || !onday) {', $key, 'Only on a day of the open popover.');
        $this->assertStringContainsString('moveTo(step());', $key);
        $this->assertMatchesRegularExpression('~bringIntoView\(iso\);\s*paintGrid\(\);~', $this->body('moveTo'));
    }

    /**
     * A day is a real button carrying the date as its name, and each month is a real grid named by its caption.
     *
     * Enter and Space are the two keys the grid pattern asks a cell to answer, and a button
     * answers both without a handler - one that re-implemented them would also have to suppress
     * the button's own activation to keep from picking the same day twice. The accessible name
     * is the DATE and not the number, because "18" read out of its month names nothing; the
     * roving tabindex is what keeps the two grids one tab stop. Below the breakpoint only one
     * month is drawn, and the second one's block is hidden, so the focus can never walk into a
     * month that is not on screen.
     */
    public function test_a_day_is_a_button_named_by_its_date_inside_a_grid_named_by_its_month(): void {
        $cell = $this->body('dayCell');
        $this->assertStringContainsString("cell.type = 'button';", $cell);
        $this->assertStringContainsString("cell.setAttribute('role', 'gridcell');", $cell);
        $this->assertMatchesRegularExpression(
            "~cell\.setAttribute\('aria-label', say\(iso, \{dateStyle: 'long'\}\)\);~",
            $cell,
            'A day cell names the date it picks.'
        );
        $this->assertStringContainsString('cell.tabIndex = iso === focused ? 0 : -1;', $cell);
        $this->assertStringContainsString("cell.setAttribute('aria-current', 'date');", $cell);

        $grid = $this->body('paintGrid');
        $this->assertStringContainsString('block.hidden = index >= monthCount();', $grid);
        $this->assertMatchesRegularExpression(
            '~if \(block\.hidden\) \{.*?grid\.replaceChildren\(\);\s*return;~s',
            $grid,
            'A month hidden at this width keeps no day the focus could be sent to.'
        );
        $this->assertStringContainsString("grid.setAttribute('role', 'grid');", $grid);
        $this->assertStringContainsString("grid.setAttribute('aria-labelledby', caption.id);", $grid);
        $this->assertStringContainsString('const monthCount = () => (wide && wide.matches ? 2 : 1);', $this->source());
        $rows = $this->body('dayRows');
        $this->assertStringContainsString("row.setAttribute('role', 'row');", $rows);
        $head = $this->body('headRow');
        $this->assertStringContainsString("cell.setAttribute('role', 'columnheader');", $head);
        // The weekday names begin on the day the SITE begins its weeks on, not on Sunday.
        $this->assertStringContainsString('shift(NAME_WEEK, (firstday + index) % WEEK)', $head);
    }

    /**
     * The module computes no timezone and asks no clock: both facts arrive from the server.
     *
     * The quick options, the predicate and the word "today" are all resolved by PHP in the site
     * timezone; a browser's own clock is the reader's, and for part of every day the two name
     * different days. A calendar that asked it would mark a cell the "Today" link beside it does
     * not filter by - a one-cell error nobody would read as one.
     *
     * The consequence in code is narrow and checkable: no `new Date()` without arguments and no
     * local-time getter anywhere in the module, every Date built through Date.UTC, and a mount
     * that refuses rather than guessing when the server's date is missing.
     *
     * Changes that must make it fail: reading today off a `new Date()` of the browser's own in
     * place of `options.today`.
     */
    public function test_the_calendar_does_no_timezone_arithmetic_of_its_own(): void {
        $source = $this->source();
        $this->assertDoesNotMatchRegularExpression(
            '~new Date\(\)~',
            $source,
            "The browser's clock is in the reader's timezone and the filter is in the site's."
        );
        foreach (['getFullYear()', 'getMonth()', 'getDate()', 'getDay()', 'setDate(', 'setMonth('] as $local) {
            $this->assertStringNotContainsString(
                '.' . $local,
                $source,
                'A local-time accessor shifts a calendar square by the reader\'s own offset: ' . $local . '.'
            );
        }
        /* Every Date is built through Date.UTC: utc() for a day, monthLength() for a month's
           length. utc() is asserted from the whole source rather than through the extractor,
           because it is a single expression with no body to extract; the assertion above, that
           the module holds no bare `new Date()` anywhere, is what makes reading the file enough. */
        $this->assertMatchesRegularExpression(
            '~^const utc = \(parts\) => new Date\(Date\.UTC\(parts\.y, parts\.m - 1, parts\.d\)\);$~m',
            $source
        );
        $this->assertSame(2, substr_count($source, 'new Date(Date.UTC('), 'One for a day, one for a month length.');

        $mount = $this->body('mount');
        $this->assertMatchesRegularExpression(
            '~if \(!region \|\| !parse\(options\.today\)\) \{~',
            $mount,
            'Without the site\'s own date the control does not mount at all.'
        );
        $this->assertStringContainsString('today = options.today;', $mount);
        $this->assertStringContainsString('firstday = Number.isInteger(day)', $mount);
    }

    /**
     * The committed build was made from the source that is committed beside it - exactly.
     *
     * Moodle serves amd/build and never amd/src, so a source edit that was never rebuilt is
     * invisible in a browser, and every other assertion in this file reads amd/src.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $this->assertSame(
            $this->source(),
            $this->built_from(),
            'amd/build is not this source: rebuild it with grunt amd and commit both files.'
        );
        $build = (string) file_get_contents($this->plugin_root() . '/amd/build/daterange.min.js');
        $this->assertStringContainsString('theme_dimensions/category_daterange', $build);
        $this->assertStringNotContainsString('category_daterangepill', $build, 'The build composes no period sentence.');
    }
}
