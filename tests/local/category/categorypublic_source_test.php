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
 * Theme Dimensions - Tests for the visitor's live listing, by its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;
use theme_dimensions\output\category_panel;

/**
 * Pins what the visitor's live listing may reach and say, by reading its sources and their builds.
 *
 * Since stage R4b2 the page arms amd/src/listingvisitor.js, which hands it to the visitor's shell
 * (js/esm/src/listing/visitor.ts) over the shared store (store.ts): the walk starts at the entry, and the
 * shell's own assertions read those two sources ({@see SHELL}).
 *
 * The module runs on a page served to the internet and asks a web service that answers without a
 * session, so the properties pinned here are the ones whose loss is a leak or a cache poisoned for
 * every visitor rather than a screenshot: what it imports, all the way down; the one service it
 * calls, and how; the mark it writes on the body; and that a failure never reaches the page in the
 * server's words. moodle-plugin-ci has no JavaScript test runner, so they are assertions about
 * spelling, each scoped to the function it is about.
 *
 * Since stage R4a its calendar, its filter line and its posters with their panel are the React
 * components the logged-in shell draws (js/esm/src/daterange, filterline and areas), mounted through
 * the shared listing. What is pinned here is the visitor's half of them: the components reached by
 * the walk and naming nothing of the logged-in shell; the request a panel asks, which keeps a slice
 * flat and alone; the poster that stays a link; a question asked once, after a quiet; and a failure
 * that loads the poster's link. The component's behaviour in both modes is Jest's (js/esm/tests/areas).
 *
 * The walk crosses into the theme's ES modules (js/esm) and back: an AMD module reaches one through
 * core's `core/esm!` loader or by naming it for a React mount, an ES module reaches its siblings by
 * relative import and an AMD module only through the bridge, js/esm/src/amd.ts, by its quoted name.
 * An ES module is a node named `esm:` and its path under js/esm/src, and its build is both files
 * Moodle may serve, the production one and the development one.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categorypublic_source_test extends \basic_testcase {
    /** @var string The entry the page arms, where the walk starts. */
    private const MODULE = 'listingvisitor';

    /** @var string The visitor's shell and the store it drives, read together: the default of every reader below. */
    private const SHELL = 'shell';

    /** @var string[] The ES modules {@see SHELL} names. */
    private const SHELL_MODULES = ['esm:listing/visitor', 'esm:listing/store'];

    /** @var string The one service it may call. */
    private const METHOD = 'theme_dimensions_get_public_category_rows';

    /** @var string The posters' component's directory, whose visitor's options are pinned here. */
    private const AREAS = 'js/esm/src/areas/';

    /** @var string The prefix of an ES module's node in the walk, before its path under js/esm/src. */
    private const ESM = 'esm:';

    /** @var string The bridge every ES module of the theme reaches AMD through, shared by both shells. */
    private const BRIDGE = self::ESM . 'amd';

    /** @var string[] The React components both shells mount, as nodes of the walk. */
    private const COMPONENTS = ['esm:daterange/DateRange', 'esm:filterline/FilterLine', 'esm:areas/Areas'];

    /** @var string The shared listing's barrel, which the AMD clients load through core/esm! (R4b1). */
    private const LISTING = self::ESM . 'listing/listing';

    /** @var string[] The logged-in modules nothing a visitor's page loads may name, in code or in a comment. */
    private const LOGGED_IN_NAMES = [
        'listing/star',
        'listingaccount',
        'listing/account',
    ];

    /** @var string[] Theme modules a visitor's page may never load: the session's star and the logged-in clients. */
    private const FORBIDDEN_MODULES = [
        self::ESM . 'listing/star',
        'listingaccount',
        self::ESM . 'listing/account',
    ];

    /**
     * @var string[] What no module of its graph may carry, in its source or its build: the logged-in
     * services, the session's preference writer and core's own favourite service.
     */
    private const FORBIDDEN_STRINGS = [
        'core_user/repository',
        'setUserPreference',
        'theme_dimensions_get_category_rows',
        'theme_dimensions_get_card_details',
        'theme_dimensions_toggle_wishlist',
        'theme_dimensions_remember_category_state',
        'core_course_set_favourite_courses',
    ];

    /** @var array|null Files by their path under the theme root, read instead of the tree when a test sets them. */
    private ?array $fixture = null;

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * Whether a file of the theme exists, in the fixture while a test has set one.
     *
     * @param string $path The path under the theme root.
     * @return bool
     */
    private function exists(string $path): bool {
        if ($this->fixture !== null) {
            return array_key_exists($path, $this->fixture);
        }
        return is_file($this->plugin_root() . '/' . $path);
    }

    /**
     * One file of the theme, from the fixture while a test has set one.
     *
     * @param string $path The path under the theme root.
     * @param string $missing What a missing file means.
     * @return string
     */
    private function read(string $path, string $missing): string {
        $this->assertTrue($this->exists($path), $path . ': ' . $missing);
        if ($this->fixture !== null) {
            return $this->fixture[$path];
        }
        return (string) file_get_contents($this->plugin_root() . '/' . $path);
    }

    /**
     * The path of an ES module's node under js/esm, without its extension.
     *
     * @param string $module An ES module's node.
     * @param string $tree src or build.
     * @return string
     */
    private function esm_path(string $module, string $tree): string {
        return 'js/esm/' . $tree . '/' . substr($module, strlen(self::ESM));
    }

    /**
     * One module's source.
     *
     * @param string $module The module's name, or an ES module's node.
     * @return string
     */
    private function source(string $module = self::SHELL): string {
        if ($module === self::SHELL) {
            return implode("\n", array_map(fn(string $one): string => $this->source($one), self::SHELL_MODULES));
        }
        if (!str_starts_with($module, self::ESM)) {
            return $this->read('amd/src/' . $module . '.js', 'The source is missing.');
        }
        $base = $this->esm_path($module, 'src');
        return $this->read($this->exists($base . '.tsx') ? $base . '.tsx' : $base . '.ts', 'The source is missing.');
    }

    /**
     * One module as the browser actually receives it: for an ES module, both builds Moodle may serve.
     *
     * @param string $module The module's name, or an ES module's node.
     * @return string
     */
    private function build(string $module = self::SHELL): string {
        if ($module === self::SHELL) {
            return implode("\n", array_map(fn(string $one): string => $this->build($one), self::SHELL_MODULES));
        }
        if (!str_starts_with($module, self::ESM)) {
            $missing = 'The build is missing: Moodle serves amd/build, never amd/src.';
            return $this->read('amd/build/' . $module . '.min.js', $missing);
        }
        $base = $this->esm_path($module, 'build');
        $production = $this->read($base . '.js', 'The build is missing: Moodle serves js/esm/build, never js/esm/src.');
        $development = $this->read($base . '.dev.js', 'The development build is missing: it is served while JS is not cached.');
        return $production . "\n" . $development;
    }

    /**
     * The source one module's committed build was actually made from.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function built_from(string $module): string {
        $path = $this->plugin_root() . '/amd/build/' . $module . '.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * One module's source without its comments: what runs, for an assertion that a word is never used.
     *
     * The docblocks name what the module refuses to do, so a word that may not be used in the code is
     * looked for here and not in the source.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function code(string $module = self::SHELL): string {
        return $this->uncommented($this->source($module));
    }

    /**
     * A text without its block comments and its whole-line comments.
     *
     * @param string $text Source or build text.
     * @return string
     */
    private function uncommented(string $text): string {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $text);
        return (string) preg_replace('~^\s*//.*$~m', '', $code);
    }

    /**
     * The body of one top-level arrow function of a module.
     *
     * @param string $name The function's name.
     * @param string $module The module's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = self::SHELL): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source($module), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The body of one top-level function of a module of the posters' component.
     *
     * @param string $name The function's name.
     * @param string $file The file under js/esm/src/areas.
     * @return string The body, without the signature or the closing brace.
     */
    private function area_body(string $name, string $file): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read(self::AREAS . $file, 'The source is missing.'), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$file}.");
        return $matches[1];
    }

    /**
     * The body of one function of the component's panel, declared inside createPanel() at four spaces.
     *
     * @param string $name The function's name.
     * @return string
     */
    private function inner(string $name): string {
        $pattern = '~\n    const ' . preg_quote($name, '~') . ' = (?:[^\n]*\n)*?[^\n]*\{\n(.*?)\n    \}\)?;~s';
        $found = preg_match($pattern, $this->read(self::AREAS . 'panel.ts', 'The source is missing.'), $matches);
        $this->assertSame(1, $found, "No function named {$name} in the component's panel.");
        return $matches[1];
    }

    /**
     * The logged-in modules one module of the walk names, anywhere in its source or its builds.
     *
     * Read from the raw text, comments included: a comment is no import, but nothing a visitor's page
     * loads may say what the logged-in shell is made of, which is one edit away from importing it.
     *
     * @param string $module The module's name, or an ES module's node.
     * @return string[]
     */
    private function logged_in_names(string $module): array {
        $text = $this->source($module) . "\n" . $this->build($module);
        return array_values(array_filter(self::LOGGED_IN_NAMES, fn(string $name): bool => str_contains($text, $name)));
    }

    /**
     * Every module a module imports, core's and the theme's: its import lines, then its dynamic imports.
     *
     * A module imported on first use (`import('...')`) ships to the visitor's page as surely as one in an
     * import line - only later - so the walk follows both, or a dynamic import would take a module out of
     * the walk in silence. An ES module's are read from its source and from both builds.
     *
     * @param string $module The module's name, or an ES module's node.
     * @return string[]
     */
    private function imports(string $module): array {
        if (str_starts_with($module, self::ESM)) {
            // A build is minified, so the specifier is matched wherever an import or a from names one.
            $text = $this->uncommented($this->source($module) . "\n" . $this->build($module));
            preg_match_all('~\\b(?:from|import)\\s*\\(?\\s*(["\'])([^"\']+)\\1~', $text, $found);
            return array_values(array_unique($found[2]));
        }
        $source = $this->source($module);
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $source, $static);
        preg_match_all("~\\bimport\\('([^']+)'\\)~", $source, $dynamic);
        return array_merge($static[1], $dynamic[1]);
    }

    /**
     * The theme modules one module loads, as nodes of the walk.
     *
     * Its imports, read as a theme AMD module (`theme_dimensions/x`), a theme ES module
     * (`@moodle/lms/theme_dimensions/x`, through `core/esm!` from AMD) or, from an ES module, a
     * sibling by relative path; then every ES module it names in a string, which is how a React
     * mount is asked for; and from an ES module every theme AMD module it names in a string, which
     * is how the bridge is asked for one. A quoted `theme_dimensions/x` with no amd/src/x.js is a
     * template's name and no edge.
     *
     * @param string $module The module's name, or an ES module's node.
     * @return string[]
     */
    private function edges(string $module): array {
        $esm = str_starts_with($module, self::ESM);
        $text = $this->uncommented($this->source($module) . ($esm ? "\n" . $this->build($module) : ''));
        $specifiers = $this->imports($module);
        preg_match_all('~["\'\x60](?:core/esm!)?(@moodle/lms/theme_dimensions/[A-Za-z0-9_/.-]+)["\'\x60]~', $text, $mounts);
        $specifiers = array_merge($specifiers, $mounts[1]);
        if ($esm) {
            preg_match_all('~["\'\x60](theme_dimensions/[a-z0-9_/]+)["\'\x60]~', $text, $quoted);
            foreach ($quoted[1] as $name) {
                if ($this->exists('amd/src/' . substr($name, strlen('theme_dimensions/')) . '.js')) {
                    $specifiers[] = $name;
                }
            }
        }
        $edges = [];
        foreach ($specifiers as $specifier) {
            $specifier = (string) preg_replace('~^core/esm!~', '', $specifier);
            if (str_starts_with($specifier, '@moodle/lms/theme_dimensions/')) {
                $path = substr($specifier, strlen('@moodle/lms/theme_dimensions/'));
                $edges[] = self::ESM . preg_replace('~\.(?:tsx?|jsx?)$~', '', $path);
            } else if (str_starts_with($specifier, 'theme_dimensions/')) {
                $edges[] = substr($specifier, strlen('theme_dimensions/'));
            } else if ($esm && preg_match('~^\.\.?/~', $specifier)) {
                $edges[] = self::ESM . $this->relative(substr($module, strlen(self::ESM)), $specifier);
            }
        }
        return array_values(array_unique($edges));
    }

    /**
     * The path under js/esm/src a relative specifier of an ES module names, without its extension.
     *
     * @param string $from The importing module's path under js/esm/src.
     * @param string $specifier The relative specifier.
     * @return string
     */
    private function relative(string $from, string $specifier): string {
        $parts = explode('/', $from);
        array_pop($parts);
        foreach (explode('/', (string) preg_replace('~\.(?:tsx?|jsx?)$~', '', $specifier)) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } else if ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    }

    /**
     * The theme modules a module loads, itself included, followed through every edge.
     *
     * @param string $module The module's name, or an ES module's node.
     * @return string[]
     */
    private function graph(string $module): array {
        $seen = [];
        $queue = [$module];
        while ($queue) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($this->edges($current) as $edge) {
                $queue[] = $edge;
            }
        }
        return array_keys($seen);
    }

    /**
     * The walk follows imports all the way down, which every assertion below depends on.
     *
     * A walk that read the entry file alone would pass a session module pulled in through a shared
     * one. The shared listing (js/esm/src/listing since R4b1, reached from AMD through `core/esm!`) reaches
     * the period's reader and the title marker, and names the three components it mounts, whose relative
     * imports the walk follows down to the bridge; the visitor's graph holds all of it.
     *
     * Changes that must make it fail: the visitor's entry loading an AMD module instead of its shell (gate
     * js_public_listing_through_esm).
     */
    public function test_the_import_walk_is_transitive(): void {
        $listing = $this->graph(self::LISTING);
        $this->assertContains(self::ESM . 'areas/mark', $listing);
        $this->assertContains(self::ESM . 'daterange/calendar', $listing);
        foreach (self::COMPONENTS as $component) {
            $this->assertContains($component, $listing, 'The shared listing mounts ' . $component . '.');
        }
        $this->assertContains(self::ESM . 'areas/panel', $listing, 'The component\'s relative imports are followed.');
        $this->assertContains(self::BRIDGE, $listing, 'Down to the bridge.');
        $graph = $this->graph(self::MODULE);
        foreach ($listing as $module) {
            $this->assertContains($module, $graph, $module . ' is reached through a shared module and the walk lost it.');
        }
        foreach (self::SHELL_MODULES as $shell) {
            $this->assertContains($shell, $graph, 'The control: the entry\'s core/esm! import is followed to ' . $shell . '.');
        }
    }

    /**
     * The walk crosses from AMD into the ES modules and back, which the visitor's rule depends on.
     *
     * Read over a fixture, because no visitor's module reaches an ES module yet: an AMD entry loads an
     * ES module through `core/esm!`, a second one names it for a React mount, the ES module imports the
     * bridge by relative path from a subdirectory, and the bridge asks RequireJS for a theme AMD
     * module by its quoted name, in its build alone. A template's name beside it is no edge.
     */
    public function test_the_walk_crosses_into_the_es_modules_and_back(): void {
        $this->fixture = [
            'amd/src/entry.js' => "import Island from 'core/esm!@moodle/lms/theme_dimensions/local/Island';\n",
            'amd/src/mounter.js' => "root.dataset.reactComponent = '@moodle/lms/theme_dimensions/local/Island';\n",
            'amd/src/fixtureamd.js' => "export const init = () => null;\n",
            'js/esm/src/local/Island.tsx' => "import {load} from '../amd';\nconst card = 'theme_dimensions/category_card';\n",
            'js/esm/build/local/Island.js' => 'import{load as o}from"../amd";const c="theme_dimensions/category_card";',
            'js/esm/build/local/Island.dev.js' => "import { load } from \"../amd\";\n",
            'js/esm/src/amd.ts' => "import {requireAsync} from '@moodle/lms/core/amd';\n",
            'js/esm/build/amd.js' => 'import{requireAsync as o}from"@moodle/lms/core/amd";'
                . 'const s=()=>o("theme_dimensions/fixtureamd");',
            'js/esm/build/amd.dev.js' => "import { requireAsync } from \"@moodle/lms/core/amd\";\n",
        ];
        $this->assertSame(['entry', self::ESM . 'local/Island', self::BRIDGE, 'fixtureamd'], $this->graph('entry'));
        $this->assertSame(['mounter', self::ESM . 'local/Island', self::BRIDGE, 'fixtureamd'], $this->graph('mounter'));
        $this->assertSame([self::BRIDGE], $this->edges(self::ESM . 'local/Island'), 'A template\'s name is no edge.');
    }

    /**
     * The visitor's page reaches each component the logged-in shell draws, through the one place each is named.
     *
     * Stage R4a of docs/vitrine-react/PLAN.md moved the visitor onto the components on purpose: they are
     * visitor-safe by rule ({@see test_nothing_the_walk_reaches_names_the_logged_in_shell()}). Each
     * specifier is named once, in the shared listing, so both shells mount the same component and no
     * client names one the walk would have to be taught. The preview the line imports on first use and
     * the panel's modules are reached too, down to the bridge.
     *
     * Changes that must make it fail: a specifier dropped from the shared listing (gate
     * js_public_graph_reaches_areas); a client naming a component itself.
     */
    public function test_the_visitors_graph_reaches_each_component_named_once(): void {
        $graph = $this->graph(self::MODULE);
        $logged = $this->graph('listingaccount');
        $reached = array_merge(self::COMPONENTS, [
            self::ESM . 'filterline/preview',
            self::ESM . 'areas/panel',
            self::ESM . 'areas/posters',
            self::BRIDGE,
        ]);
        foreach ($reached as $module) {
            $this->assertContains($module, $graph, 'The visitor\'s page reaches ' . $module . '.');
            $this->assertContains($module, $logged, 'And the logged-in shell\'s reaches the same one.');
        }
        $specifiers = ['@moodle/lms/theme_dimensions/daterange/DateRange', '@moodle/lms/theme_dimensions/filterline/FilterLine',
            '@moodle/lms/theme_dimensions/areas/Areas'];
        $files = array_merge(
            glob($this->plugin_root() . '/amd/src/*.js'),
            glob($this->plugin_root() . '/js/esm/src/*.ts'),
            glob($this->plugin_root() . '/js/esm/src/*/*.{ts,tsx}', GLOB_BRACE)
        );
        foreach ($files as $file) {
            $name = substr($file, strlen($this->plugin_root()) + 1);
            $text = $this->uncommented((string) file_get_contents($file));
            $count = 0;
            foreach ($specifiers as $specifier) {
                $count += substr_count($text, $specifier);
            }
            $this->assertSame($name === 'js/esm/src/listing/mounts.ts' ? 3 : 0, $count, "{$name} names a component.");
        }
        $this->assertMatchesRegularExpression(
            "~^const COMPONENTS = \{\n    calendar: '@moodle/lms/theme_dimensions/daterange/DateRange',\n"
                . "    line: '@moodle/lms/theme_dimensions/filterline/FilterLine',\n"
                . "    areas: '@moodle/lms/theme_dimensions/areas/Areas',\n\};$~m",
            $this->source(self::ESM . 'listing/mounts')
        );
    }

    /**
     * Nothing a visitor's page loads names the logged-in shell's modules, not even in a comment.
     *
     * The walk starts from the visitor's module and from the bridge and reads every module's source and
     * both builds as they are, comments included. A name in a comment is no import, but the rule of R4a
     * is that the visitor's graph says nothing of what the logged-in shell is made of: a component that
     * names the logged-in module answering it is one edit from importing it. The logged-in services are
     * the next test's. The controls: the account's entry names the star, and a comment alone is found.
     *
     * Changes that must make it fail: a comment naming the account's entry in the shared listing (gate
     * js_public_graph_names_vitrine), the account's shell in the component's panel (gate
     * js_public_component_names_panel) or in the store (gate js_public_graph_names_account).
     */
    public function test_nothing_the_walk_reaches_names_the_logged_in_shell(): void {
        $graph = array_values(array_unique(array_merge($this->graph(self::MODULE), $this->graph(self::BRIDGE))));
        $this->assertGreaterThan(20, count($graph), 'The control: the walk reaches the components.');
        foreach ($graph as $module) {
            $this->assertSame([], $this->logged_in_names($module), "{$module} names the logged-in shell.");
        }
        $this->assertContains('listing/account', $this->logged_in_names('listingaccount'), 'The control: a name is found.');
        $this->fixture = [
            'amd/src/entry.js' => "/** Answered by listingaccount. */\nexport const init = () => null;\n",
            'amd/build/entry.min.js' => 'define([],(function(){}));',
        ];
        $this->assertSame(['listingaccount'], $this->logged_in_names('entry'), 'A comment alone is found.');
    }



    /**
     * Nothing a visitor's page loads reads the session, stars a course or asks a logged-in service.
     *
     * The walk covers every theme module the visitor's module loads, and for each one its imports,
     * its source and its build: a logged-in import reached through the shared listing ships to the
     * internet as surely as one in the entry file. It starts from the bridge as well: every ES module
     * reaches AMD through it, a visitor's component included, so it may reach nothing of a session
     * itself, whether or not a visitor's module imports it yet.
     *
     * Changes that must make it fail: importing the star or the account's entry anywhere in the graph (gates
     * js_public_graph_no_star, js_public_graph_panel_no_star); naming a logged-in service in any module of it; the
     * bridge, in its source or either build, naming core_user/repository.
     */
    public function test_its_whole_import_graph_reaches_nothing_of_a_session(): void {
        $bridge = $this->graph(self::BRIDGE);
        $this->assertContains(self::BRIDGE, $bridge, 'The control: the bridge is read.');
        $graph = array_values(array_unique(array_merge($this->graph(self::MODULE), $bridge)));
        foreach (self::FORBIDDEN_MODULES as $module) {
            $this->assertNotContains($module, $graph, $module . ' is loaded on a visitor\'s page.');
        }
        foreach ($graph as $module) {
            $this->assertNotContains(
                'core_user/repository',
                $this->imports($module),
                $module . ' imports the session\'s preferences.'
            );
            foreach (self::FORBIDDEN_STRINGS as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $this->source($module), "{$module} names {$forbidden}.");
                $this->assertStringNotContainsString($forbidden, $this->build($module), "{$module}'s build names {$forbidden}.");
            }
            foreach (self::FORBIDDEN_MODULES as $forbidden) {
                $this->assertStringNotContainsString(
                    'theme_dimensions/' . $forbidden,
                    $this->build($module),
                    "{$module}'s build loads {$forbidden}."
                );
            }
        }
    }

    /**
     * It calls the visitor's service and no other, posting with login not required and no cachekey.
     *
     * core/ajax switches to a GET carrying the cachekey only when one is passed as the sixth
     * argument; service.php then answers with ninety days of public caching, which would keep a
     * withdrawn course in shared caches. Three arguments - async, and login not required - is a POST
     * to service-nologin.php and nothing else.
     *
     * Changes that must make it fail: naming get_category_rows; a module of its graph calling a service;
     * passing a cachekey (M.cfg.langrev or any other) to fetchOne, or login required (gate js_visitor_nologin).
     */
    public function test_it_calls_only_the_visitors_service_and_never_with_a_cachekey(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression("~^const METHOD = '" . self::METHOD . "';$~m", $source);
        $this->assertSame(1, substr_count($source, 'methodname:'), 'One call site.');
        preg_match_all("~'(theme_dimensions_[a-z_]+)'~", $source, $services);
        $this->assertSame([self::METHOD], array_values(array_unique($services[1])), 'No other service is named.');
        $this->assertMatchesRegularExpression(
            '~return fetchOne<Rows>\(\{\s*methodname: METHOD,\s*args: args,\s*\}, \{loginrequired: false\}\);~',
            $this->body('ask'),
            'One call, async, login not required, and nothing after it: no cachekey.'
        );
        // Both questions go through it: a page of the listing and a poster's panel.
        $this->assertStringContainsString('const response = await shell.rows(page);', $this->body('requestRows'));
        $this->assertStringContainsString(
            'const rows = (page: number): Promise<Rows> => ask(rowsArgs(page));',
            $this->source(self::ESM . 'listing/visitor')
        );
        $this->assertStringContainsString('return ask(panelArgs(poster, psort));', $this->body('askPanel'));
        $code = $this->code();
        $this->assertStringContainsString('fetchOne<Rows>({', $code, 'The control: the code is read.');
        foreach (['cachekey', 'langrev', 'templaterev'] as $never) {
            $this->assertStringNotContainsString($never, $code, 'The module never builds a cachekey: ' . $never);
        }
        $this->assertStringContainsString(self::METHOD, $this->build());
        $this->assertStringContainsString("import {fetchOne} from '@moodle/lms/core/ajax';", $source);

        /* And nothing else the module loads calls a service of its own: a shared module asking some
           other theme service would pass the checks above, which read the entry file. The calendar
           reaches core's own string and template services through core/str and core/templates,
           which are core's to call. */
        foreach ($this->graph(self::MODULE) as $module) {
            // The shell is the one call site, read above.
            if ($module === self::ESM . 'listing/visitor') {
                continue;
            }
            foreach (['core/ajax', '@moodle/lms/core/ajax'] as $ajax) {
                $this->assertNotContains($ajax, $this->imports($module), $module . ' calls a web service of its own.');
            }
            $this->assertStringNotContainsString('methodname:', $this->code($module), $module . ' names a method.');
        }
    }

    /**
     * It sends the page's own language, and the root its term alone.
     *
     * The request carries no session, so the reader's language - a choice from the language menu is
     * kept in the session - would be lost, and the answer would speak the site's or the browser's.
     * The service declares no state chip and no favourites toggle (a visitor has no favourites) and
     * refuses an undeclared key, and refuses the root anything but its term and the language.
     */
    public function test_it_sends_the_pages_language_and_the_root_its_term_alone(): void {
        $this->assertStringContainsString("import Config from '@moodle/lms/core/config';", $this->source());
        $args = $this->body('rowsArgs');
        $this->assertMatchesRegularExpression(
            '~if \(Listing\.isRoot\(state\)\) \{\s*return \{categoryid: 0, query: state\.query, lang: Config\.language\};~',
            $args
        );
        $this->assertSame(2, substr_count($args, 'lang: Config.language'), 'Both shapes carry the language.');
        $this->assertDoesNotMatchRegularExpression('~\bchip\b~', $args, 'The visitor\'s service declares no state chip.');
        $this->assertStringContainsString("state.chip = '';", $this->body('publicOnly'), 'And the state never holds one.');
        $this->assertDoesNotMatchRegularExpression('~\bfav\b~', $args, 'Nor the favourites toggle.');
        $this->assertStringContainsString('state.fav = false;', $this->body('publicOnly'), 'Which the state never holds either.');
        $this->assertStringContainsString(
            "    lastpage = state.page;\n    shell.normalise(state);\n",
            $this->body('init'),
            'Not even at load: the payload\'s are dropped before anything reads the state.'
        );
    }

    /**
     * It writes its own mark on the body, and never the logged-in client's.
     *
     * dim-js hides the no-JavaScript submit and is the logged-in client's presence; a second module
     * writing it would stand for a module that is not there. The quoted literal is asserted because
     * the name is a prefix of dim-js-only and of this module's own mark.
     *
     * Changes that must make it fail: marking the body dim-js.
     */
    public function test_it_writes_its_own_mark_and_never_dim_js(): void {
        $this->assertMatchesRegularExpression("~^const BODY_CLASS = 'dim-public-js';$~m", $this->source());
        $this->assertStringContainsString('document.body.classList.add(shell.mark);', $this->body('init'));
        $this->assertStringContainsString("\n    mark: BODY_CLASS,\n", $this->source(), 'The shell hands the store its mark.');
        foreach ($this->graph(self::MODULE) as $module) {
            $this->assertStringNotContainsString(
                "'dim-js'",
                $this->source($module),
                $module . ' writes the logged-in client\'s mark.'
            );
        }
    }

    /**
     * A failure is told in the page's own words, and the page asked for is loaded; init never fetches.
     *
     * The service's message speaks the request's language and not the page's, so nothing of a
     * failure is read: no message, no code, no dialogue. The sentence is the page's own string,
     * preloaded by request.php. Only the latest request falls back, or an answer overtaken while it
     * failed would load a page nobody is asking for. And init fetches nothing: the page on screen
     * is the server's answer to its own address, so a failure can only ever load a page, never loop.
     *
     * Changes that must make it fail: showing the failure's message; letting an overtaken request
     * fall back; fetching from init.
     */
    public function test_a_failure_is_told_in_the_pages_words_and_loads_the_page(): void {
        $source = $this->source();
        $code = $this->code();
        $this->assertStringContainsString('getString(FALLBACK, COMPONENT)', $code, 'The control: the code is read.');
        $this->assertStringNotContainsString('core/notification', $code, 'No dialogue of the failure.');
        foreach (['.message', 'errorcode', 'debuginfo', 'backtrace', 'exception'] as $never) {
            $this->assertStringNotContainsString($never, $code, 'Nothing of a failure is read: ' . $never);
        }
        $this->assertMatchesRegularExpression("~^const FALLBACK = '" . request::STRING_FALLBACK . "';$~m", $source);
        $this->assertTrue(
            get_string_manager()->string_exists(request::STRING_FALLBACK, 'theme_dimensions'),
            'The sentence exists in the language pack.'
        );
        $fall = $this->body('fallBack');
        $this->assertStringContainsString('const text = await getString(FALLBACK, COMPONENT);', $fall);
        $this->assertStringContainsString('notice.textContent = text;', $fall);
        $this->assertMatchesRegularExpression('~\n    load\(url\);$~', $fall);
        $this->assertMatchesRegularExpression(
            '~^const fallBack = async\(url: URL\): Promise<void> => \{$~m',
            $source,
            'It is handed the address alone.'
        );

        $request = $this->body('requestRows');
        $this->assertMatchesRegularExpression(
            '~\} catch \(error\) \{\s*if \(ticket === seq\) \{\s*shell\.failed\(page, append, error\);~',
            $request,
            'Only the latest request falls back.'
        );
        $failed = $this->body('failed');
        $this->assertStringContainsString('    fallBack(fallbackUrl(page, append));', $failed, 'With the address alone.');
        $init = $this->body('init');
        $this->assertStringNotContainsString('requestRows(', $init, 'init never fetches.');
        $this->assertStringNotContainsString('shell.rows(', $init);
        $this->assertStringNotContainsString('ask(', $init);
        $this->assertStringNotContainsString('ask(', $this->inner('adopt'), 'Nor does the component taking the page over.');
    }

    /**
     * A panel that cannot be answered in place loads the poster's own link, in the page's words.
     *
     * An answer carrying no panel, or another area's, is a failure like any other: a slice the
     * server no longer draws as one (the area became public, or left the page, since the page
     * loaded) is answered with the parent's flat listing, which painted as a panel would be a lie.
     * The visitor's module takes every failure over before the inline error: the page's sentence,
     * then the poster's link with the panel's order, which the server answers with the panel - or
     * with what the area now is. Only the latest request gets there.
     *
     * Changes that must make it fail: painting an answer without a panel; the visitor's module
     * leaving a failure to the inline error; the fallback losing the panel's order or its fragment.
     */
    public function test_a_panel_it_cannot_answer_loads_the_posters_link(): void {
        $merge = $this->area_body('answerContext', 'model.ts');
        $this->assertMatchesRegularExpression(
            '~if \(!response \|\| !response\.panel \|\| Number\(response\.panel\.id\) !== id\) \{\s*throw new Error\(~',
            $merge,
            'An answer with no panel of the area asked about is a failure.'
        );
        $request = $this->inner('request');
        $found = preg_match('~\} catch \(error\) \{(.*?)\} finally \{~s', $request, $matches);
        $this->assertSame(1, $found, 'The request has no catch block.');
        $catch = $matches[1];
        $this->assertMatchesRegularExpression('~if \(ticket !== st\.ticket\) \{\s*return;~', $catch, 'Only the latest request.');
        $handed = strpos($catch, 'if (unanswered(poster, st.psort)) {');
        $this->assertIsInt($handed, 'A failure is offered to the client.');
        $this->assertLessThan((int) strpos($catch, '{iserror: true}'), $handed, 'Before the inline error is drawn.');
        $offer = $this->inner('unanswered');
        $this->assertStringContainsString('areas.dispatchEvent(new CustomEvent(EVENTS.unanswered, {detail: detail}));', $offer);
        $this->assertStringContainsString('return detail.taken === true;', $offer);
        $this->assertStringContainsString(
            'detail.taken = unanswered(detail.poster, detail.psort);',
            $this->body('mountAreas', self::ESM . 'listing/mounts'),
            'The shared mount hands the client\'s word back.'
        );

        $failed = $this->body('panelFailed');
        $this->assertStringContainsString('fallBack(panelAddress(Number(poster.dataset.categoryid), psort));', $failed);
        $this->assertStringContainsString('return true;', $failed, 'The visitor never gets the inline error.');
        $address = $this->body('panelAddress');
        $this->assertStringContainsString('const url = Listing.buildUrl(current());', $address);
        $this->assertStringContainsString('url.searchParams.set(PARAM.open, String(id));', $address);
        $this->assertStringContainsString('url.searchParams.set(PARAM.psort, PSORT_NAME);', $address);
        $this->assertStringContainsString('url.hash = PANEL_ANCHOR;', $address, 'The page lands on the panel.');
        $this->assertStringContainsString('unanswered: panelFailed,', $this->source());
        /* The panel module wrote the open parameter into the address when the panel opened, so the
           poster's link differs from the address on screen by its fragment alone - which assign()
           answers with a scroll inside the document, not a page. Such an address is loaded again. */
        $load = $this->body('load');
        $this->assertMatchesRegularExpression(
            "~if \(bare\.toString\(\) === here\.toString\(\)\) \{\s*"
                . "replaceKeeping\(target\);\s*"
                . 'window\.location\.reload\(\);\s*return;\s*\}\s*window\.location\.assign\(target\);~',
            $load
        );
        $this->assertStringContainsString("bare.hash = '';", $load);
        $this->assertStringContainsString("here.hash = '';", $load);
    }

    /**
     * The address is the shared listing's, and every poster's link follows it.
     *
     * The address an applied change writes, the one a failure loads and the one each poster's link
     * carries are built by the shared listing's buildUrl(), the logged-in client's own, so an address
     * round-trips between the page and the client. A poster's link is the server's
     * category_panel::page_address() for that area: the page's parameters, the area open, no panel
     * order, the panel's fragment. A change applied with a panel open is fetched like any other: the
     * answer is painted through the shared panel module, which asks the open panel again.
     *
     * Changes that must make it fail: loading the page for a change while a panel is open; not
     * asking the open panel again after an answer; not pointing the posters' links after an answer.
     */
    public function test_the_address_is_the_shared_listings_and_the_posters_follow_it(): void {
        $source = $this->source();
        $this->assertStringNotContainsString('history.pushState', $source, 'The address is written by the shared listing alone.');
        $apply = $this->body('apply');
        $this->assertStringNotContainsString('navigate(', $apply, 'A change with a panel open is fetched, never loaded.');
        $this->assertMatchesRegularExpression(
            '~^\s*state\.page = 0;\s*shell\.changed\(kind\);\s*Listing\.writeUrl\(state, true\);\s*shell\.written\(\);'
                . '\s*return requestRows\(0, false\);\s*$~',
            $apply
        );
        $link = $this->body('linkPosters');
        $this->assertStringContainsString("control.href = panelAddress(Number(poster.dataset.categoryid), '').toString();", $link);
        $this->assertMatchesRegularExpression(
            "~^const PANEL_ANCHOR = '" . category_panel::ANCHOR_PANEL . "';$~m",
            $source,
            'The server\'s own fragment for a panel.'
        );
        $this->assertMatchesRegularExpression(
            '~shell\.answered\(response\);\s*document\.dispatchEvent\(new CustomEvent\(LISTING\.criteria, \{detail: \{\s*'
                . 'accordion: response\.accordion \|\| \[\],\s*areas: word,.*?\}\}\)\);\s*$~s',
            $this->body('paintAreas'),
            'Every answer is told to the shell, then to the component, which asks the open panel again.'
        );
        $this->assertStringContainsString("\n    answered: linkPosters,\n", $this->source(), 'The posters are pointed again.');
        $this->assertMatchesRegularExpression(
            "~if \(st\.open\) \{\s*place\(\);\s*request\(\);~",
            $this->inner('criteria'),
            'The component asks the open panel again.'
        );
        // The shell a panel shows before its rows arrive carries the criteria: the component writes it.
        $this->assertStringNotContainsString('dataset.ctaurl', $this->code(), 'Nothing here writes into a poster but its link.');
        $posters = $this->read(self::AREAS . 'posters.ts', 'The source is missing.');
        $this->assertStringContainsString("poster.dataset.ctaurl = entry.ctaurl || '';", $posters);
        $this->assertStringContainsString("poster.dataset.ctalabel = entry.ctalabel || '';", $posters);
        $this->assertStringContainsString('const url = Listing.buildUrl(current());', $this->body('fallbackUrl'));
        $this->assertStringContainsString('shell.written();', $this->body('init'), 'The open poster links to its own panel too.');
    }

    /**
     * The search waits 300 ms, as the logged-in client's does, and never for a panel.
     *
     * A panel open in place is asked again with the answer, so a typed term is applied by the timer
     * whatever is open, and Enter submits nothing: the term is in place already, as the logged-in
     * client's form. Nothing in the module loads the page for an open panel any more. The quiet
     * period and the request it leads to are held as one pending token, resolved when the answer is
     * painted or the term dropped: no request covers the 300 ms, so without it Behat moves on from a
     * typed term before anything has been asked.
     *
     * Changes that must make it fail: holding the term while a panel is open; letting Enter submit;
     * releasing the token before the term has been dealt with, or never releasing a dropped one.
     */
    public function test_the_search_is_debounced_and_never_waits_for_a_panel(): void {
        $this->assertMatchesRegularExpression('~^const SEARCH_DEBOUNCE = 300;$~m', $this->source());
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~form\.addEventListener\(\'input\', \(event\) => \{\s*const search = Listing\.closestOf\(event, SELECTORS\.search\);'
                . '\s*if \(search\) \{\s*onSearchInput\(search as HTMLInputElement\);~',
            $listen,
            'Delegated on the form: the line\'s component replaces the server\'s field with its own.'
        );
        $this->assertStringContainsString("form.addEventListener('submit', (event) => event.preventDefault());", $listen);
        $this->assertMatchesRegularExpression(
            '~^    cancelSearch\(\);\n    Listing\.paintEraser\(root\);\n'
                . '    const pending = new Pending\(\'theme_dimensions/listing:search\'\);\n'
                . '    searchpending = pending;\n    searchtimer = window\.setTimeout\(async\(\) => \{\n'
                . '        searchpending = null;\n        try \{\n            await onSearch\(search\.value\);\n'
                . '        \} finally \{\n            pending\.resolve\(\);\n        \}\n    \}, SEARCH_DEBOUNCE\);$~',
            $this->body('onSearchInput'),
            'One token from the keystroke until the term it leads to has been dealt with.'
        );
        $this->assertMatchesRegularExpression(
            '~window\.clearTimeout\(searchtimer\);\s*if \(searchpending\) \{\s*searchpending\.resolve\(\);~',
            $this->body('cancelSearch'),
            'A term dropped before it was sent releases its token.'
        );
        $this->assertMatchesRegularExpression('~^    cancelSearch\(\);$~m', $this->body('onClear'));
        $this->assertMatchesRegularExpression('~^    return requestRows\(0, false\);$~m', $this->body('apply'));
        $this->assertMatchesRegularExpression(
            "~notice\\.hidden = true;\\s*\\}\\s*state\\.query = term;\\s*await apply\\('term'\\);~",
            $this->body('onSearch'),
            'The timer applies the term, a panel open or not.'
        );
        $code = $this->code();
        $this->assertStringContainsString("apply('term');", $code, 'The control: the code is read.');
        foreach (['panelOpen', 'onSubmit'] as $gone) {
            $this->assertStringNotContainsString($gone, $code, 'An open panel no longer turns a change into a page load: ' . $gone);
        }
        // The one reload left is load()'s, for an address that differs from this one by its fragment alone.
        $this->assertSame(1, substr_count($code, 'location.reload'), 'No reload but load()\'s.');
        $this->assertStringContainsString('window.location.reload();', $this->body('load'));
        $this->assertStringNotContainsString('reload', $this->body('listen'), 'Back and forward fetch, whatever is open.');
    }

    /**
     * A poster's panel is asked of the one service: a public area by its id, a merely listed one as a flat slice.
     *
     * The service answers a public area as the panel of its own page (`withcategories`, the area's
     * id) and a merely listed one - its poster has no page to link its title to - only as a slice of
     * this page's listing, which is refused unless flat, on the first page and alone: never with the
     * tree's category, a page, or a panel's request beside it, and never at the root (rows::slice(),
     * rows::require_wellformed_slice()). The root's posters are public areas asked with the term
     * alone. The criteria come off the address through the shared listing's reading of it.
     *
     * Changes that must make it fail: a slice sent with the grouped or an empty order, with a cat,
     * a page or withcategories; every poster asked as a slice, or none; a public area asked by the page.
     */
    public function test_a_poster_asks_for_its_panel_as_the_service_answers_it(): void {
        $args = $this->body('panelArgs');
        $this->assertStringContainsString('const sort = psort === PSORT_NAME ? SORT.name : SORT.recent;', $args);
        $this->assertMatchesRegularExpression(
            "~^const SORT = \{\n    recent: 'recent',\n    name: 'name',\n\};$~m",
            $this->source()
        );
        $this->assertSame(population::PANEL_SIZE, 8, 'The module\'s PANEL_SIZE is the server\'s.');
        $this->assertMatchesRegularExpression('~^const PANEL_SIZE = 8;$~m', $this->source());
        $this->assertStringContainsString('Listing.readUrl(asked);', $args, 'The criteria are the address\'s.');

        // The slice: this page's category, the poster's area, a flat order, the narrowing and nothing else.
        $this->assertMatchesRegularExpression(
            "~if \(poster\.dataset\.titleurl === ''\) \{\s*"
                . 'return Object\.assign\(\{categoryid: state\.categoryid, slice: id, sort: sort\}, narrowing\);~',
            $args
        );
        $found = preg_match('~const narrowing = \{\n(.*?)\n    \};~s', $args, $matches);
        $this->assertSame(1, $found, 'The narrowing a slice carries is one object.');
        preg_match_all('~^\s+([a-z]+):~m', $matches[1], $keys);
        $this->assertSame(
            ['query', 'filters', 'datefrom', 'dateto', 'lang'],
            $keys[1],
            'A slice carries no cat, page or panel request.'
        );
        foreach (['cat:', 'chip:', 'fav:'] as $never) {
            $this->assertStringNotContainsString($never, $args, 'A panel never sends ' . $never);
        }

        // A public area: its own id, a panel's request, eight courses from the first.
        $this->assertMatchesRegularExpression(
            '~return Object\.assign\(\{categoryid: id, page: 0, perpage: PANEL_SIZE, sort: sort, withcategories: true\}, '
                . 'narrowing\);\s*$~',
            $args
        );
        // The root: a public area too, with its term alone.
        $this->assertMatchesRegularExpression(
            '~if \(Listing\.isRoot\(asked\)\) \{\s*return \{\s*categoryid: id,\s*page: 0,\s*perpage: PANEL_SIZE,\s*'
                . 'sort: sort,\s*query: asked\.query,\s*withcategories: true,\s*lang: Config\.language,\s*\};~',
            $args
        );
        // What tells the two apart is the poster's title address, which the server leaves empty for a merely listed area.
        $card = (string) file_get_contents($this->plugin_root() . '/templates/category_directory_card.mustache');
        $this->assertStringContainsString('data-titleurl="{{shell.titleurl}}"', $card);
    }

    /**
     * A visitor's poster stays its link: a plain press opens the panel in place, any other press follows it.
     *
     * The posters' component turns the logged-in client's posters into buttons; mounted with the visitor's
     * options (links), it keeps them links, has each announced as the button it acts as - role, state, the
     * panel it controls - and answers Enter (a click) and Space on it, while a press meant for another tab
     * or window, with a modifier or already dealt with, is left to the link. A middle or secondary press
     * is an event of its own and never reaches the click listener. The behaviour is Jest's; this is the
     * wiring and the spelling.
     *
     * Changes that must make it fail: mounting the visitor's posters without links (gate
     * js_public_posters_stay_links: buttons with no address); the component ignoring the option (gate
     * js_panel_links_announced); intercepting a modified press (js_panel_modified_press); Space doing
     * nothing on a poster (js_panel_space_toggles).
     */
    public function test_a_visitors_poster_stays_its_link_and_a_plain_press_opens_it(): void {
        $this->assertMatchesRegularExpression(
            "~^const AREAS_PROPS = \{\n    links: true,\n    quiet: PANEL_QUIET,\n    autoopen: false,\n\};$~m",
            $this->source()
        );
        $this->assertStringContainsString('Listing.mountAreas(areas, shell.areas.props, shell.areas.client);', $this->body('init'));
        $this->assertStringContainsString("\n        props: AREAS_PROPS,\n", $this->source(), 'The visitor\'s options.');
        $this->assertStringContainsString(
            'node.dataset.reactProps = JSON.stringify(props);',
            $this->body('mountAreas', self::ESM . 'listing/mounts')
        );
        $this->assertStringContainsString(
            "    areas: {\n        props: {},\n",
            $this->read('js/esm/src/listing/account.ts', 'The source is missing.'),
            'The logged-in shell keeps its buttons: its props are none.'
        );
        $panel = $this->read(self::AREAS . 'panel.ts', 'The source is missing.');
        $this->assertStringContainsString("    links: props.links === true,\n", $panel);
        $this->assertStringContainsString("    autoopen: props.autoopen !== false,\n", $panel);
        $this->assertStringContainsString(
            '}, optionsOf(props || {})) : null));',
            $this->read(self::AREAS . 'Areas.tsx', 'The source is missing.'),
            'The component builds its panel with the mount\'s props.'
        );

        $convert = $this->area_body('convertPoster', 'posters.ts');
        $this->assertMatchesRegularExpression(
            "~if \(links\) \{\s*attributes\.role = 'button';\s*"
                . 'Object\.keys\(attributes\)\.forEach\(\(name\) => setAttr\(control, name, attributes\[name\]\)\);\s*return;~',
            $convert,
            'The link is kept, announced as a button with its state and the panel it controls.'
        );
        $this->assertLessThan((int) strpos($convert, 'toButton('), (int) strpos($convert, 'if (links) {'));
        $this->assertStringContainsString("'aria-controls': PANEL_ID,", $convert);
        $this->assertStringContainsString(
            "return links && control.tagName === 'A' && isExpandable(poster) ? control : null;",
            $this->area_body('driven', 'posters.ts')
        );
        $setopen = $this->area_body('setPosterOpen', 'posters.ts');
        $this->assertStringContainsString('const control = driven(poster, links);', $setopen);

        $click = $this->inner('onAreasClick');
        $this->assertStringContainsString('driven(poster, mode.links) !== control', $click);
        $this->assertMatchesRegularExpression(
            "~if \(control\.tagName === 'A' && elsewhere\(event as MouseEvent\)\) \{\s*return;\s*\}\s*"
                . 'event\.preventDefault\(\);\s*toggleArea\(poster\);~',
            $click,
            'A press for another tab or window is the link\'s.'
        );
        $this->assertMatchesRegularExpression(
            '~export const elsewhere = \(event: MouseEvent\): boolean => event\.defaultPrevented \|\| event\.button !== 0\s*'
                . '\|\| event\.ctrlKey \|\| event\.metaKey \|\| event\.shiftKey \|\| event\.altKey;~',
            $panel
        );
        $space = $this->inner('onSpace');
        $this->assertStringContainsString("if (event.key !== ' ' || event.repeat || !mode.links) {", $space);
        $this->assertMatchesRegularExpression('~event\.preventDefault\(\);\s*toggleArea\(poster\);\s*return true;~', $space);
        $this->assertMatchesRegularExpression('~if \(onSpace\(event\)\) \{\s*return;~', $this->inner('onKeydown'));
    }

    /**
     * A panel's question is asked once, and only after the reader stopped pressing.
     *
     * Every question costs the site a slice or a panel's reads, on the open internet. The open panel
     * is asked again with every answer to new criteria; an answer to criteria that did not change -
     * the back button over a change of view - finds the same question already asked for the same
     * opening, and a panel the server drew counts its address's question as asked. A request waits
     * PANEL_QUIET for the reader to stop pressing, and goes out only if it is still the latest. There is
     * no prefetch: nothing asks on hover. The logged-in client names no question, and is asked every
     * time as it always was.
     *
     * Changes that must make it fail: the visitor's question not handed to the component (gates
     * js_public_question_named, js_areas_question_answered); asking an answered question again
     * (js_panel_question_once); asking with no quiet (js_public_panel_quiet); dropping the ticket check
     * after the quiet (js_panel_quiet_latest).
     */
    public function test_a_panels_question_is_asked_once_after_a_quiet(): void {
        $this->assertStringContainsString('question: panelQuestion,', $this->source());
        $this->assertMatchesRegularExpression('~^const PANEL_QUIET = [1-9][0-9]*;$~m', $this->source());
        $this->assertStringContainsString("\n    quiet: PANEL_QUIET,\n", $this->source());
        $this->assertStringContainsString('return JSON.stringify(panelArgs(poster, psort));', $this->body('panelQuestion'));
        $this->assertStringContainsString(
            'detail.question = question(detail.poster, detail.psort);',
            $this->body('mountAreas', self::ESM . 'listing/mounts')
        );

        $panel = $this->read(self::AREAS . 'panel.ts', 'The source is missing.');
        $this->assertMatchesRegularExpression(
            "~const answered = \(asking: string\): boolean => asking !== '' && asking === st\.asked "
                . '&& st\.askedticket === st\.ticket;~',
            $panel
        );
        $this->assertStringContainsString(
            'areas.dispatchEvent(new CustomEvent(EVENTS.question, {detail: detail}));',
            $this->inner('question')
        );
        $request = $this->inner('request');
        $asked = strpos($request, "if (answered(asking)) {\n            return;\n        }");
        $ticket = strpos($request, 'const ticket = ++st.ticket;');
        $this->assertIsInt($asked, 'An answered question is not asked again.');
        $this->assertIsInt($ticket);
        $this->assertLessThan($ticket, $asked, 'Before a ticket is taken, which would supersede the answer on screen.');
        $this->assertMatchesRegularExpression('~st\.asked = asking;\s*st\.askedticket = ticket;~', $request);
        $quiet = strpos($request, "if (!(await quiet(ticket))) {\n                return;\n            }");
        $this->assertIsInt($quiet, 'A request waits for the quiet and stays the latest.');
        $this->assertLessThan((int) strpos($request, 'await ask(poster, st.psort);'), $quiet);
        $this->assertStringContainsString('SKELETON_DELAY + mode.quiet', $request, 'The skeleton waits for the quiet too.');
        $this->assertMatchesRegularExpression(
            '~await new Promise\(\(resolve\) => window\.setTimeout\(resolve, mode\.quiet\)\);\s*\}\s*'
                . 'return ticket === st\.ticket;~',
            $this->inner('quiet')
        );
        $this->assertStringContainsString("st.asked = '';", $request, 'A failed question may be asked again.');
        $this->assertMatchesRegularExpression(
            '~st\.asked = question\(posterOf\(areas, id\) as HTMLElement, st\.psort\);\s*st\.askedticket = st\.ticket;~',
            $this->inner('adopt'),
            'A panel the server drew is the answer to its address.'
        );
        $sources = [
            self::MODULE => $this->code(),
            'panel.ts' => $this->uncommented($panel),
            'posters.ts' => $this->uncommented($this->read(self::AREAS . 'posters.ts', 'The source is missing.')),
        ];
        foreach ($sources as $module => $code) {
            foreach (['mouseenter', 'mouseover', 'pointerover', 'pointerenter'] as $hover) {
                $this->assertStringNotContainsString("'{$hover}'", $code, "{$module} asks nothing on hover.");
            }
        }
    }

    /**
     * Back and forward read the address again and ask for it; a request out makes the listing busy.
     */
    public function test_the_history_and_the_pending_state(): void {
        // The history's listener goes on before the posters are handed over: Back reaches the state first.
        $init = $this->body('init');
        $listens = strpos($init, "        window.addEventListener('popstate', onPopState);\n");
        $this->assertIsInt($listens, 'Back is heard.');
        $this->assertLessThan(
            (int) strpos($init, 'Listing.mountAreas(areas, shell.areas.props, shell.areas.client);'),
            $listens,
            'Before the posters\' component, which hears Back for the panel\'s half of the address.'
        );
        $this->assertStringNotContainsString("'popstate'", $this->body('listen'));
        $this->assertMatchesRegularExpression(
            "~^    Listing\.readUrl\(state\);\s*shell\.normalise\(state\);\s*"
                . 'Listing\.paintControls\(root, state\);\s*Listing\.syncFold\(\);\s*shell\.written\(\);'
                . '\s*shell\.changed\(\'history\'\);\s*requestRows\(0, false\);$~',
            $this->body('onPopState')
        );
        $request = $this->body('requestRows');
        // One pending from the call to the painted rows: the template load between core's own tokens waits on a timer.
        $this->assertMatchesRegularExpression(
            "~^    const ticket = \+\+seq;\n(?:    (?:/\*|  )[^\n]*\n)*"
                . "    const pending = new Pending\('theme_dimensions/listing:rows'\);\n~",
            $request,
            'The token is taken with the ticket, before anything is asked (gate js_store_rows_pending).'
        );
        $this->assertMatchesRegularExpression(
            '~\} finally \{\s*if \(ticket === seq\) \{\s*root\.removeAttribute\(\'aria-busy\'\);\s*\}\s*'
                . 'Listing\.restoreMore\(more\);\s*pending\.resolve\(\);\s*\}$~',
            $request,
            'And released once, whatever happened: painted, failed or overtaken.'
        );
        $this->assertStringContainsString("root.setAttribute('aria-busy', 'true');", $request);
        $this->assertStringContainsString(
            'document.dispatchEvent(new CustomEvent(LISTING.pending, {detail: {filtered: Listing.narrows(state)}}));',
            $request,
            'The posters\' component holds their numbers until the answer.'
        );
        $this->assertMatchesRegularExpression(
            "~finally \{\s*if \(ticket === seq\) \{\s*root\.removeAttribute\('aria-busy'\);~",
            $request
        );
    }

    /**
     * The committed build of the module was made from the source beside it, and loads the TypeScript listing.
     *
     * The listing's and the components' builds are esm_source_test's. The entry's build loads its shell alone.
     */
    public function test_the_committed_builds_were_made_from_the_committed_sources(): void {
        $this->assertSame(
            $this->source(self::MODULE),
            $this->built_from(self::MODULE),
            'amd/build/' . self::MODULE . ' is not this source: rebuild it with grunt amd and commit the build.'
        );
        $this->assertStringContainsString('"core/esm!@moodle/lms/theme_dimensions/listing/visitor"', $this->build(self::MODULE));
        $this->assertStringContainsString(
            '["exports","core/esm!@moodle/lms/theme_dimensions/listing/visitor"]',
            $this->build(self::MODULE),
            'Its shell and nothing else.'
        );
        $this->assertStringContainsString('@moodle/lms/theme_dimensions/areas/Areas', $this->build(self::ESM . 'listing/mounts'));
    }

    /**
     * The visitor's module hands its line, its calendar and its posters to the components, as the logged-in one does.
     *
     * The line goes first: the line put back where it stands is seen by core's observer as leaving and
     * coming back, which would unmount a calendar already inside it. The posters are handed over before
     * the root without a search line returns, because its directory has posters too. After every answer
     * the line is told its numbers, after the painters; the field the component draws is read delegated
     * on the form and never written; a client's focus on the search goes to the field or, folded, to its
     * icon. No area opens by itself on this shell.
     *
     * Changes that must make it fail: the line or the calendar not handed over (gates js_public_line_armed,
     * js_public_calendar_mounted); the line handed over after the calendar (js_public_line_before_calendar);
     * the answer not told (js_public_line_answer_told); the field read where the component replaced it
     * (js_public_line_input_delegated) or written into (js_public_erase_guard); the focus put on a folded
     * field (js_public_focus_target); the automatic opening (js_public_no_autoopen).
     */
    public function test_the_visitor_hands_its_controls_to_the_components(): void {
        $init = $this->body('init');
        $line = strpos($init, "\n    Listing.mountLine(root, dropTerm);\n");
        $calendar = strpos($init, "\n    Listing.mountCalendar(root, state, setRange).catch(shell.unmounted);\n");
        $areas = strpos($init, "\n    Listing.mountAreas(areas, shell.areas.props, shell.areas.client);\n");
        $root = strpos($init, "\n    if (!live) {\n        return false;\n    }\n");
        $this->assertStringContainsString(
            'const live = !(Listing.isRoot(state) && !root.querySelector(SELECTORS.toolbar));',
            $init,
            'A root with nothing to search draws no line: its posters alone are armed there.'
        );
        foreach (['line' => $line, 'calendar' => $calendar, 'posters' => $areas, 'root' => $root] as $what => $at) {
            $this->assertIsInt($at, "The {$what} is not handed over as it should be.");
        }
        $this->assertLessThan($calendar, $line, 'The line before the calendar.');
        $this->assertLessThan($root, $areas, 'The posters wherever there are posters, the root\'s directory included.');
        $this->assertLessThan($line, $root, 'The line where there is a toolbar.');
        $this->assertMatchesRegularExpression("~^    autoopen: false,$~m", $this->source());
        $this->assertStringContainsString('if (!mode.autoopen || !id || ', $this->inner('autoOpen'), 'Which the component obeys.');

        $apply = $this->body('applyResponse');
        $told = strpos($apply, 'Listing.tellAnswer(root, response);');
        $this->assertIsInt($told, 'The line is told every answer.');
        foreach (['Listing.paintFilterBadge(', 'Listing.paintChips(', 'Listing.paintGroups(', 'Listing.paintPager('] as $painter) {
            $this->assertLessThan($told, (int) strpos($apply, $painter), $painter . ' paints before the line is told.');
        }
        $this->assertMatchesRegularExpression(
            '~form\.addEventListener\(\'input\', \(event\) => \{\s*const search = Listing\.closestOf\(event, SELECTORS\.search\);~',
            $this->body('listen')
        );
        $this->assertStringContainsString('if (search && !search.closest(SELECTORS.drawnline)) {', $this->body('onErase'));
        $this->assertStringContainsString(
            "drawnline: '[data-region=\"dim-line\"][data-line-drawn]',",
            $this->read('js/esm/src/listing/events.ts', 'The source is missing.')
        );
        $this->assertStringContainsString('const search = Listing.focusTarget(root);', $this->body('focusSearch'));
        foreach (['onClear', 'onChipRemove'] as $handler) {
            $this->assertStringContainsString("\n    focusSearch();", $this->body($handler), $handler);
        }
    }

    /**
     * The visitor's client paints the row under the line from the answer and drops a chip's criterion in place.
     *
     * The same shared painter as the logged-in client's, so a chip on one shell is the chip on the
     * other; the press and the eraser are intercepted as the logged-in client intercepts them, and
     * the clear link's visibility is the answer's clearall, painted there.
     *
     * Changes that must make it fail: the painter not called; the chip press or the eraser left to
     * the browser; the clear link shown on hasfilters again.
     */
    public function test_the_row_under_the_line_is_the_shared_painters(): void {
        $apply = $this->body('applyResponse');
        $this->assertStringContainsString('Listing.paintChips(root, response);', $apply);
        $this->assertStringNotContainsString('response.hasfilters', $apply);
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~const chip = Listing\.closestOf\(event, SELECTORS\.chipremove\);\s*if \(chip && onChipRemove\(chip\)\) \{\s*'
                . 'event\.preventDefault\(\);~',
            $listen
        );
        $this->assertMatchesRegularExpression(
            '~if \(Listing\.closestOf\(event, SELECTORS\.eraser\)\) \{\s*event\.preventDefault\(\);~',
            $listen
        );
        $remove = $this->body('onChipRemove');
        $this->assertStringContainsString("if (!Listing.dropCriterion(state, chip.dataset.param || '')) {", $remove);
        $this->assertMatchesRegularExpression(
            "~shell\\.normalise\\(state\\);\\s*Listing\\.paintControls\\(root, state\\);\\s*apply\\('filter'\\);~",
            $remove
        );
        $this->assertStringContainsString("await onSearch('');", $this->body('onErase'));
    }
}
