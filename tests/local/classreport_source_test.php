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
 * Theme Dimensions - Tests for the class competency report's React view, read from its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins js/esm/src/classreport by reading its sources, as plancards_source_test does for an AMD module: the sweep's
 * only runner is PHPUnit, so the view's guards that no server test reaches are held here. The behaviour itself is the
 * Jest suite's (js/esm/tests/classreport) and the Behat scenario's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class classreport_source_test extends \basic_testcase {
    /**
     * A source of the view.
     *
     * @param string $name The file, relative to js/esm/src/classreport.
     * @return string
     */
    private function source(string $name): string {
        $path = dirname(__DIR__, 2) . '/js/esm/src/classreport/' . $name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * A rating given in core's dialogue is heard on the shell's root, and it reads the rows and the band again.
     *
     * @return void
     */
    public function test_a_rating_reads_the_rows_and_the_band_again(): void {
        $source = $this->source('ClassReportView.tsx');
        $this->assertSame(1, substr_count($source, 'addEventListener(RATED, rated)'), 'The view does not listen.');
        $this->assertStringContainsString('removeEventListener(RATED, rated)', $source);
        $this->assertMatchesRegularExpression(
            '~const rated = \(\): void => \{\s*run\(\{\}, true\);\s*\};~',
            $source,
            'The handler reads both regions: the second argument of run() is the band.'
        );
        $this->assertMatchesRegularExpression(
            '~const run = useCallback\(.*?loadRows\(query\.current, readband\);\s*if \(readband\) \{\s*' .
            'loadBand\(query\.current\);~s',
            $source
        );
    }

    /**
     * Only the newest request paints: each answer's number is compared with the newest before anything is written, in
     * the rows and in the band.
     *
     * @return void
     */
    public function test_only_the_newest_answer_is_painted(): void {
        $source = $this->source('ClassReportView.tsx');
        $this->assertSame(1, preg_match(
            '~getRows\(target, asked, facets\)\.then\(\(answer\) => \{\s*if \(seq !== rowsseq\.current\) \{\s*return;\s*\}' .
            '.*?setRows\(answer\);~s',
            $source
        ), 'The rows are painted without the request number compared first.');
        $this->assertSame(1, preg_match(
            '~getSummary\(target, asked\.search\)\.then\(\(answer\) => \{\s*if \(seq === bandseq\.current\) \{\s*' .
            'setSummary\(answer\);~',
            $source
        ), 'The band is painted without the request number compared first.');
        $this->assertStringContainsString('const seq = ++rowsseq.current;', $source);
        $this->assertStringContainsString('const seq = ++bandseq.current;', $source);
    }

    /**
     * The Columns items stop at the page's limit, the props' maxcolumns: the bar hands it on, and the view refuses
     * one more column past it.
     *
     * @return void
     */
    public function test_the_columns_stop_at_the_limit(): void {
        $bar = $this->source('Bar.tsx');
        $this->assertMatchesRegularExpression('~limit=\{\{most: props\.maxcolumns, note: ~', $bar);
        $view = $this->source('ClassReportView.tsx');
        $this->assertMatchesRegularExpression('~if \(on && shown > props\.maxcolumns\) \{\s*return;\s*\}~', $view);
        $this->assertStringContainsString('maxcolumns={props.maxcolumns}', $view);
    }
}
