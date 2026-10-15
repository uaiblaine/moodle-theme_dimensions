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
 * Theme Dimensions - Brand neutrality test
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * No file of the plugin names the institution the theme was first written for.
 *
 * The theme is a community theme: its code, templates, styles, tests and scripts must stay free of
 * any one organisation's name, abbreviations and CSS prefixes.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class brand_neutrality_test extends \basic_testcase {
    /**
     * Every text file of the plugin is free of the forbidden names.
     *
     * The needles are built from parts so that this file does not match itself; it is skipped as well.
     * The scan is case-insensitive and lists every offending file:line.
     */
    public function test_no_file_names_the_original_institution(): void {
        $needles = [
            'fund' . 'aseg',
            'f' . 'sg-',
            'f' . 'sn-',
            '--f' . 's-',
            'cbm' . 'pr',
            'pm' . 'pr',
        ];
        $root = dirname(__DIR__);
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $current): bool {
                return !$current->isDir() || !in_array($current->getFilename(), ['.git', 'node_modules'], true);
            }
        ));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getRealPath() === __FILE__) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            if ($content === false || strpos(substr($content, 0, 8000), "\0") !== false) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            foreach (explode("\n", $content) as $number => $line) {
                foreach ($needles as $needle) {
                    if (stripos($line, $needle) !== false) {
                        $found[] = $relative . ':' . ($number + 1) . ' (' . $needle . ')';
                    }
                }
            }
        }
        $this->assertSame([], $found, "The plugin names its original institution:\n" . implode("\n", $found));
    }
}
