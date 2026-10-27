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
 * Theme Dimensions - Tests for the ES module tree, by its source and its build
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins what js/esm must be for the browser to run the committed source, and the one door to AMD.
 *
 * Moodle serves js/esm/build, never js/esm/src. Core 5.3's `grunt esm` writes, per source, a
 * minified `X.js` with no map and a development `X.dev.js` with its `X.dev.js.map`, served while
 * JavaScript is not cached. The development map carries the source it was made from, so its
 * currency is asserted here byte for byte, on every CI that runs PHPUnit. The minified file has no
 * map: the gate for its bytes is `mdl ci`'s esm_tree_check (`moodle-dev/bin/mdl-ci`), which rebuilds
 * the installed copy and compares the whole js/esm with the committed one. What this test can say of
 * it is that it exists and loads the same modules as the development build beside it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class esm_source_test extends \basic_testcase {
    /**
     * Absolute path to the theme's js/esm.
     *
     * @return string
     */
    private function root(): string {
        return dirname(__DIR__, 2) . '/js/esm';
    }

    /**
     * Every file under one tree of js/esm, by its path under that tree.
     *
     * @param string $tree src or build.
     * @return string[]
     */
    private function files(string $tree): array {
        $base = $this->root() . '/' . $tree;
        $this->assertDirectoryExists($base);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), strlen($base) + 1);
        }
        sort($files);
        return $files;
    }

    /**
     * Every module of js/esm/src, by its path without the extension, mapped to its source file.
     *
     * @return array Module path => file path under js/esm/src.
     */
    private function modules(): array {
        $modules = [];
        foreach ($this->files('src') as $file) {
            if (preg_match('~^(.*)\.tsx?$~', $file, $matches) && !str_ends_with($file, '.d.ts')) {
                $modules[$matches[1]] = $file;
            }
        }
        return $modules;
    }

    /**
     * The module specifiers a text imports, in a minified build or a readable one.
     *
     * @param string $text Source or build text.
     * @return string[]
     */
    private function specifiers(string $text): array {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $text);
        $code = (string) preg_replace('~^\s*//.*$~m', '', $code);
        // A keyword inside a string literal ("from") is no import, nor is a "specifier" across lines.
        preg_match_all('~(?<!["\'])\b(?:from|import)\s*\(?\s*(["\'])([^"\'\n]+)\1~', $code, $found);
        $specifiers = array_values(array_unique($found[2]));
        sort($specifiers);
        return $specifiers;
    }

    /**
     * Each source has its three outputs, and the build holds nothing else.
     *
     * Core's build empties js/esm/build before writing, so an output with no source is a module that
     * was deleted and still ships; a production map is something core's build does not write.
     *
     * Changes that must make it fail: a source added without its build; a build left behind by a
     * deleted source.
     */
    public function test_each_source_has_its_three_outputs_and_the_build_nothing_else(): void {
        $modules = $this->modules();
        $this->assertArrayHasKey('amd', $modules, 'The control: the bridge is a module of the tree.');
        $expected = [];
        foreach (array_keys($modules) as $module) {
            array_push($expected, $module . '.dev.js', $module . '.dev.js.map', $module . '.js');
        }
        sort($expected);
        $this->assertSame($expected, $this->files('build'), 'js/esm/build is not what grunt esm writes for js/esm/src.');
    }

    /**
     * The development build was made from the committed source, byte for byte.
     *
     * Changes that must make it fail: any edit of js/esm/src without a rebuild.
     */
    public function test_the_development_build_was_made_from_the_committed_source(): void {
        foreach ($this->modules() as $module => $file) {
            $map = json_decode((string) file_get_contents($this->root() . '/build/' . $module . '.dev.js.map'), true);
            $this->assertIsArray($map, "js/esm/build/{$module}.dev.js.map is not readable JSON.");
            $this->assertSame([basename($file)], array_map('basename', $map['sources']), "{$module}'s map names another source.");
            $this->assertSame(
                (string) file_get_contents($this->root() . '/src/' . $file),
                (string) ($map['sourcesContent'][0] ?? ''),
                "js/esm/build/{$module}.dev.js is not this source: rebuild with core 5.3's grunt esm and commit js/esm/build."
            );
        }
    }

    /**
     * The production build loads what the development build beside it loads.
     *
     * Its bytes are mdl ci's to judge; a production file left from an older source would load another
     * set of modules, which is also what the visitor's import walk reads. The one difference core's
     * build makes on purpose is JSX's runtime: a development build of a component imports
     * `react/jsx-dev-runtime` where the production one imports `react/jsx-runtime`, so the development
     * name is read as the production one.
     *
     * Changes that must make it fail: a production build of an older source importing another module.
     */
    public function test_the_production_build_loads_what_the_development_build_loads(): void {
        foreach (array_keys($this->modules()) as $module) {
            $development = $this->specifiers((string) file_get_contents($this->root() . '/build/' . $module . '.dev.js'));
            $development = array_map(
                fn(string $name): string => $name === 'react/jsx-dev-runtime' ? 'react/jsx-runtime' : $name,
                $development
            );
            sort($development);
            $this->assertSame(
                $development,
                $this->specifiers((string) file_get_contents($this->root() . '/build/' . $module . '.js')),
                "js/esm/build/{$module}.js does not load what its development build loads: it is stale."
            );
        }
    }

    /**
     * The bridge is the one module that reaches RequireJS, and it reaches it through core's loader.
     *
     * A component that imported `@moodle/lms/core/amd` itself, or called RequireJS, would load an AMD
     * module no list names and the visitor's walk does not read as the bridge's.
     *
     * Changes that must make it fail: a second module importing core's loader or calling RequireJS;
     * the bridge calling RequireJS directly.
     */
    public function test_only_the_bridge_reaches_amd(): void {
        foreach ($this->modules() as $module => $file) {
            $source = (string) file_get_contents($this->root() . '/src/' . $file);
            $code = (string) preg_replace('~/\*.*?\*/~s', '', $source);
            $code = (string) preg_replace('~^\s*//.*$~m', '', $code);
            $this->assertDoesNotMatchRegularExpression(
                '~\brequirejs\b|\brequire\s*\(|window\.require\b~',
                $code,
                "{$module} calls RequireJS."
            );
            $this->assertStringNotContainsString('core/esm!', $code, "{$module} names the AMD loader of ES modules.");
            if ($module === 'amd') {
                $this->assertContains('@moodle/lms/core/amd', $this->specifiers($source), 'The bridge uses core\'s loader.');
            } else {
                $this->assertNotContains('@moodle/lms/core/amd', $this->specifiers($source), "{$module} goes past the bridge.");
            }
        }
    }
}
