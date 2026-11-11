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
 * Theme Dimensions - Tests for how the shared listing mounts the calendar component
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the listing's half of the date range component by reading the source.
 *
 * The component's behaviour is Jest's (js/esm/tests/daterange); what Jest cannot see is how the shared
 * listing (js/esm/src/listing) mounts it and talks to it, and what the component must never compute. Core's react_autoinit
 * mounts a node added to the document and reads no attribute change, so the listing APPENDS a node
 * carrying the component and its props; it listens to the component before the node goes in; and the
 * native date fields go behind the calendar only once the component says it is drawn, which it never
 * says without the site's today. Both shells mount it, named once in the shared listing (the
 * visitor's import walk, which reaches it, is categorypublic_source_test's).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class daterange_component_source_test extends \basic_testcase {
    /** @var string[] The shared listing's sources under js/esm/src/listing, without their extension. */
    private const LISTING = ['events', 'address', 'history', 'mounts', 'paint'];

    /** @var string The component's specifier, resolved by core's import map to js/esm/build/daterange/DateRange.js. */
    private const SPECIFIER = '@moodle/lms/theme_dimensions/daterange/DateRange';

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * One file of the theme.
     *
     * @param string $path The path under the theme root.
     * @return string
     */
    private function read(string $path): string {
        $file = $this->plugin_root() . '/' . $path;
        $this->assertFileExists($file);
        return (string) file_get_contents($file);
    }

    /**
     * The body of one top-level arrow function of an AMD module or of the shared listing.
     *
     * A signature may run over several lines in the TypeScript, one parameter a line.
     *
     * @param string $name The function's name.
     * @param string $module The AMD module's name, or listing for the shared listing's TypeScript sources.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = 'listing'): string {
        $text = '';
        foreach ($module === 'listing' ? self::LISTING : [] as $file) {
            $text .= $this->read('js/esm/src/listing/' . $file . '.ts') . "\n";
        }
        if ($module !== 'listing') {
            $text = $this->read('amd/src/' . $module . '.js');
        }
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $text, $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * A text without its block comments and its whole-line comments.
     *
     * @param string $text Source text.
     * @return string
     */
    private function uncommented(string $text): string {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $text);
        return (string) preg_replace('~^\s*//.*$~m', '', $code);
    }

    /**
     * The extractor returns one function and not the module around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $append = $this->body('appendCalendar');
        $this->assertStringContainsString('reactComponent', $append);
        $this->assertStringNotContainsString('if (!region) {', $append, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('if (!fieldset || !region) {', $this->body('mountCalendar'));
    }

    /**
     * The component is the calendar of every shell, appended wherever the toolbar has the period's region.
     *
     * There is no other calendar: an address's period is read by js/esm/src/daterange/calendar.ts's
     * period(), on both shells.
     *
     * Changes that must make it fail: the AMD calendar mounted again beside the component; the specifier
     * handed in by a caller instead of named in the listing.
     */
    public function test_the_component_is_the_calendar_of_every_shell(): void {
        $mount = $this->body('mountCalendar');
        $this->assertStringContainsString(
            "export const mountCalendar = async(\n    root: HTMLElement,\n    state: ListingState,\n",
            $this->read('js/esm/src/listing/mounts.ts')
        );
        $this->assertMatchesRegularExpression(
            '~if \(!fieldset \|\| !region\) \{\s*return;\s*\}\s*appendCalendar\(root, fieldset, region, state, onpick\);\s*$~',
            $mount
        );
        $this->assertStringNotContainsString('DateRange.mount(', $this->uncommented($this->read('js/esm/src/listing/mounts.ts')));
        $this->assertStringContainsString('node.dataset.reactComponent = COMPONENTS.calendar;', $this->body('appendCalendar'));
    }

    /**
     * The mount is a NEW child carrying the component and its props, the props object itself.
     *
     * Changes that must make it fail: the props nested under a key (the component renders nothing); the
     * node's attribute set on the region instead of a child appended (react_autoinit never sees it);
     * the site's today or first weekday read from anywhere but the fieldset the server drew.
     */
    public function test_the_mount_is_an_appended_child_carrying_the_props_object_itself(): void {
        $append = $this->body('appendCalendar');
        $this->assertStringContainsString("const node = document.createElement('div');", $append);
        $this->assertStringContainsString('node.dataset.reactComponent = COMPONENTS.calendar;', $append);
        $this->assertMatchesRegularExpression(
            '~node\.dataset\.reactProps = JSON\.stringify\(\{\s*id: state\.categoryid,\s*firstday: fieldset\.dataset\.firstday,\s*'
                . 'today: fieldset\.dataset\.today,\s*from: state\.datefrom,\s*to: state\.dateto,\s*\}\);~',
            $append,
            'The props are the object itself: {id, firstday, today, from, to}.'
        );
        $this->assertMatchesRegularExpression('~region\.append\(node\);\s*$~', $append, 'The node is appended, last.');
        $this->assertStringNotContainsString('region.dataset.react', $append);
    }

    /**
     * Both listeners are on the region before the node goes in, and the fields hide only once it is drawn.
     *
     * Changes that must make it fail: the node appended before the listeners (a component already
     * imported answers before anybody hears it); the fields hidden at the append (a component that
     * never draws - no today, no strings - leaves the reader with no date control at all).
     */
    public function test_it_listens_before_appending_and_hides_the_fields_only_once_drawn(): void {
        $append = $this->body('appendCalendar');
        $appended = strpos($append, 'region.append(node);');
        $this->assertNotFalse($appended);
        foreach (['region.addEventListener(CALENDAR.pick,', 'region.addEventListener(CALENDAR.mounted,'] as $listener) {
            $at = strpos($append, $listener);
            $this->assertNotFalse($at, $listener . ' is missing.');
            $this->assertLessThan($appended, $at, $listener . ' comes after the node is appended.');
        }
        $this->assertMatchesRegularExpression(
            '~region\.addEventListener\(CALENDAR\.pick, \(event\) => \{\s*'
                . 'const detail = \(event as CustomEvent\)\.detail;\s*onpick\(detail\.from, detail\.to\);\s*\}\);~',
            $append,
            'A pick is the caller\'s onpick, with the two bounds.'
        );
        $this->assertMatchesRegularExpression(
            '~region\.addEventListener\(CALENDAR\.mounted, \(\) => \{\s*hideDateFields\(root\);\s*tellCalendar\(region, state\);\s*'
                . '\}\);~',
            $append,
            'The fields hide when the component says it is drawn, and the period is told again then - every time, since a '
                . 'calendar moved with the line is mounted again (gate js_calendar_mounted_every_time).'
        );
        $this->assertSame(1, substr_count($append, 'hideDateFields('), 'The fields hide in the mounted listener and nowhere else.');
    }

    /**
     * Every paint of the period tells the component, silently, on the region it listens on.
     *
     * Changes that must make it fail: paintDates no longer telling it (a quick option, Back or a cleared
     * chip would leave the boxes on the old period).
     */
    public function test_every_paint_of_the_period_tells_the_component(): void {
        $this->assertStringContainsString(
            'tellCalendar(root.querySelector(SELECTORS.daterange), state);',
            $this->body('paintDates')
        );
        $this->assertMatchesRegularExpression(
            '~region\.dispatchEvent\(new CustomEvent\(CALENDAR\.set, '
                . '\{detail: \{from: state\.datefrom, to: state\.dateto\}\}\)\);~',
            $this->body('tellCalendar')
        );
    }

    /**
     * The three event names are spelled alike on both sides.
     *
     * A name spelled differently on one side errors nowhere: the listing never hears the pick, or the
     * component never hears the period.
     */
    public function test_the_two_sides_spell_the_events_alike(): void {
        preg_match('~\nexport const CALENDAR = \{\n(.*?)\n\};~s', $this->read('js/esm/src/listing/events.ts'), $shared);
        $this->assertNotEmpty($shared, 'The listing\'s event names are missing.');
        preg_match_all("~(\\w+): '([^']+)'~", $shared[1], $names, PREG_SET_ORDER);
        $this->assertSame(
            ['pick' => 'dim-daterange:pick', 'set' => 'dim-daterange:set', 'mounted' => 'dim-daterange:mounted'],
            array_column($names, 2, 1)
        );
        // One spelling: the component and the listing's mounts both import it.
        $component = $this->read('js/esm/src/daterange/DateRange.tsx');
        $this->assertStringContainsString("import {CALENDAR} from '../listing/events';", $component);
        $this->assertStringContainsString('export const EVENTS = CALENDAR;', $component);
        $this->assertStringContainsString(
            "import {AREAS, CALENDAR, LINE, LINE_DRAWN, SELECTORS} from './events';",
            $this->read('js/esm/src/listing/mounts.ts')
        );
    }

    /**
     * The shared listing names the component, which is built, and both shells mount it through it.
     *
     * Changes that must make it fail: a client naming the component itself, or the store mounting no
     * calendar.
     */
    public function test_both_shells_mount_the_component_named_in_the_listing(): void {
        $this->assertStringContainsString(
            "    calendar: '" . self::SPECIFIER . "',\n",
            $this->read('js/esm/src/listing/mounts.ts')
        );
        foreach (['js', 'dev.js'] as $build) {
            $this->assertFileExists(
                $this->plugin_root() . '/js/esm/build/daterange/DateRange.' . $build,
                'The specifier resolves to js/esm/build/daterange/DateRange.js, which must be built.'
            );
        }
        $store = $this->uncommented($this->read('js/esm/src/listing/store.ts'));
        $this->assertSame(1, substr_count($store, 'Listing.mountCalendar('));
        $this->assertStringContainsString('Listing.mountCalendar(root, state, setRange).catch(', $store);
        $this->assertStringNotContainsString(
            '@moodle/lms/theme_dimensions/daterange',
            $this->read('js/esm/src/listing/store.ts'),
            'The store names the component: the listing names it, once.'
        );
    }

    /**
     * The component computes no timezone and asks no clock.
     *
     * Every boundary of the period is the site's, resolved by PHP; the browser's clock is the reader's.
     * So no bare `new Date()`, no local-time accessor, every Date built through Date.UTC, and the
     * site's today read from the props alone. The behaviour is Jest's, in a zone west of UTC; this is
     * the half a test in UTC cannot see (a local-time constructor reads like Date.UTC there).
     *
     * Changes that must make it fail: today read off a `new Date()`; a `getDate()` in the arithmetic.
     */
    public function test_the_component_does_no_timezone_arithmetic(): void {
        $dates = 0;
        foreach (['calendar.ts', 'model.ts', 'DateRange.tsx'] as $file) {
            $code = $this->uncommented($this->read('js/esm/src/daterange/' . $file));
            $this->assertDoesNotMatchRegularExpression('~new Date\(\s*\)~', $code, "{$file} reads the browser's clock.");
            $this->assertDoesNotMatchRegularExpression('~\bDate\.now\(~', $code, "{$file} reads the browser's clock.");
            foreach (['getFullYear', 'getMonth', 'getDate', 'getDay', 'setDate', 'setMonth', 'setFullYear'] as $local) {
                $this->assertDoesNotMatchRegularExpression(
                    '~\.' . $local . '\(~',
                    $code,
                    "{$file}: a local-time accessor shifts a calendar square by the reader's offset ({$local})."
                );
            }
            $this->assertSame(
                substr_count($code, 'new Date('),
                substr_count($code, 'new Date(Date.UTC('),
                "{$file} builds a Date other than through Date.UTC."
            );
            $dates += substr_count($code, 'new Date(Date.UTC(');
        }
        $this->assertSame(2, $dates, 'One for a day (utc()), one for a month\'s length.');
        $this->assertStringContainsString(
            'const DateRange = (props: DateRangeProps) => (parse(props.today) ? <Calendar {...props} /> : null);',
            $this->read('js/esm/src/daterange/DateRange.tsx'),
            'Without the site\'s own today the component draws nothing.'
        );
    }
}
