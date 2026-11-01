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
        $this->assertSame(1, $bridged, 'The page reports a failure through the bridge, once.');
        $this->assertStringContainsString("load('core/notification')", $this->sources()['Calendar.tsx']);
    }

    /**
     * No layout is read anywhere in the tree, and the rows are measured by one ResizeObserver per grid.
     *
     * @return void
     */
    public function test_no_layout_read_and_one_observer(): void {
        $observers = 0;
        foreach ($this->sources() as $path => $source) {
            $this->assertDoesNotMatchRegularExpression(
                '~getBoundingClientRect|getClientRects|offset(?:Height|Width|Top|Left)|client(?:Height|Width)'
                    . '|scroll(?:Height|Width)|getComputedStyle~',
                $source,
                "{$path} reads the layout."
            );
            $observers += substr_count($source, 'new ResizeObserver(');
        }
        $this->assertSame(1, $observers);
        $this->assertStringContainsString('new ResizeObserver(', $this->sources()['MonthGrid.tsx']);
        $this->assertStringContainsString('observer.observe(element);', $this->sources()['MonthGrid.tsx']);
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
     * The two seams C3 replaces are one function each: where an item leads, where a day opens.
     *
     * @return void
     */
    public function test_the_seams_are_one_function_each(): void {
        $definitions = ['itemHref' => 0, 'dayHref' => 0];
        $redirects = 0;
        foreach ($this->sources() as $path => $source) {
            foreach (array_keys($definitions) as $name) {
                $definitions[$name] += substr_count($source, "export const {$name} = ");
            }
            $redirects += substr_count($source, 'redirect(');
        }
        $this->assertSame(['itemHref' => 1, 'dayHref' => 1], $definitions);
        $this->assertSame(1, $redirects, 'A day is opened in one place.');
        $this->assertStringContainsString('redirect(dayHref(props.urls.calendar, iso))', $this->sources()['Calendar.tsx']);
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
        $this->assertStringContainsString("writeAddress(address(props.urls.page, month, next), write === 'push');", $calendar);
        $this->assertStringContainsString("request(iso.slice(0, 7), state, 'push', iso, focus);", $calendar);
        $this->assertStringContainsString("request(shown.month, next, 'replace', selected, false);", $calendar);
    }
}
