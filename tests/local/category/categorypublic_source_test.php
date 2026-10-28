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
 * Pins what amd/src/categorypublic.js may reach and say, by reading its source and its build.
 *
 * The module runs on a page served to the internet and asks a web service that answers without a
 * session, so the properties pinned here are the ones whose loss is a leak or a cache poisoned for
 * every visitor rather than a screenshot: what it imports, all the way down; the one service it
 * calls, and how; the mark it writes on the body; and that a failure never reaches the page in the
 * server's words. moodle-plugin-ci has no JavaScript test runner, so they are assertions about
 * spelling, each scoped to the function it is about.
 *
 * Its panels open in place through categoryareapanel, the logged-in client's own panel module
 * (categorypanel_source_test pins the panel itself); what is pinned here is the visitor's half of
 * it: the request a panel asks, which keeps a slice flat and alone; the poster that stays a link;
 * a question asked once, after a quiet; and a failure that loads the poster's link.
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
    /** @var string The module under test. */
    private const MODULE = 'categorypublic';

    /** @var string The one service it may call. */
    private const METHOD = 'theme_dimensions_get_public_category_rows';

    /** @var string The panel module both clients arm, whose visitor's half is pinned here. */
    private const PANEL = 'categoryareapanel';

    /** @var string The prefix of an ES module's node in the walk, before its path under js/esm/src. */
    private const ESM = 'esm:';

    /** @var string The bridge every ES module of the theme reaches AMD through, shared by both shells. */
    private const BRIDGE = self::ESM . 'amd';

    /** @var string[] Theme modules a visitor's page may never load: the session's star and the logged-in clients. */
    private const FORBIDDEN_MODULES = ['categorystar', 'categoryvitrine', 'categorypanel'];

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
    private function source(string $module = self::MODULE): string {
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
    private function build(string $module = self::MODULE): string {
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
    private function code(string $module = self::MODULE): string {
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
    private function body(string $name, string $module = self::MODULE): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source($module), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
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
     * The walk follows imports all the way down, which the two assertions below depend on.
     *
     * A walk that read the entry file alone would pass a session module pulled in through a shared
     * one. The shared listing reaches the calendar and the title marker, and the visitor's graph holds
     * the shared listing's.
     */
    public function test_the_import_walk_is_transitive(): void {
        $listing = $this->graph('categorylisting');
        $this->assertContains('categorymark', $listing);
        $this->assertContains('daterange', $listing);
        $this->assertContains('categoryposters', $this->graph(self::PANEL), 'The panel module paints the posters too.');
        $graph = $this->graph(self::MODULE);
        $this->assertContains(self::PANEL, $graph, 'The panels are opened through the shared panel module.');
        $this->assertContains('categoryline', $graph, 'The filter line\'s behaviour is the shared line module\'s.');
        foreach (array_merge($listing, $this->graph('categoryposters'), $this->graph(self::PANEL)) as $module) {
            $this->assertContains($module, $graph, $module . ' is reached through a shared module and the walk lost it.');
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
            'amd/src/categorystar.js' => "export const init = () => null;\n",
            'js/esm/src/local/Island.tsx' => "import {load} from '../amd';\nconst card = 'theme_dimensions/category_card';\n",
            'js/esm/build/local/Island.js' => 'import{load as o}from"../amd";const c="theme_dimensions/category_card";',
            'js/esm/build/local/Island.dev.js' => "import { load } from \"../amd\";\n",
            'js/esm/src/amd.ts' => "import {requireAsync} from '@moodle/lms/core/amd';\n",
            'js/esm/build/amd.js' => 'import{requireAsync as o}from"@moodle/lms/core/amd";'
                . 'const s=()=>o("theme_dimensions/categorystar");',
            'js/esm/build/amd.dev.js' => "import { requireAsync } from \"@moodle/lms/core/amd\";\n",
        ];
        $this->assertSame(['entry', self::ESM . 'local/Island', self::BRIDGE, 'categorystar'], $this->graph('entry'));
        $this->assertSame(['mounter', self::ESM . 'local/Island', self::BRIDGE, 'categorystar'], $this->graph('mounter'));
        $this->assertSame([self::BRIDGE], $this->edges(self::ESM . 'local/Island'), 'A template\'s name is no edge.');
    }

    /**
     * The visitor's page never reaches the logged-in shell's calendar component, which the walk does see.
     *
     * Two calendars until stage R4 of docs/vitrine-react/PLAN.md: the logged-in module names the React
     * component and hands it to the shared mountCalendar(), the visitor's keeps the AMD module. The
     * component reaches nothing of a session either, but it is a change of the visitor's page the owner
     * has not decided, so the graph stays as it was. The control walks from the logged-in module, so a
     * walk that cannot see a component named for a mount does not pass this test by being blind.
     *
     * Changes that must make it fail: the specifier named in a shared module (categorylisting); the
     * visitor's module handing mountCalendar a component.
     */
    public function test_the_visitors_graph_never_reaches_the_calendar_component(): void {
        $component = self::ESM . 'daterange/DateRange';
        $this->assertContains($component, $this->graph('categoryvitrine'), 'The control: the walk sees the component.');
        $graph = $this->graph(self::MODULE);
        $this->assertContains('daterange', $graph, 'The visitor keeps the AMD calendar.');
        $this->assertNotContains($component, $graph, 'The visitor\'s page reaches the logged-in calendar component.');
        foreach ($graph as $module) {
            $this->assertStringNotContainsString(
                '@moodle/lms/theme_dimensions/daterange',
                $this->code($module),
                $module . ' names the logged-in calendar component.'
            );
        }
        $this->assertMatchesRegularExpression(
            '~Listing\.mountCalendar\(root, state, setRange\)\.catch\(~',
            $this->code(),
            'The visitor mounts the AMD calendar, naming no component.'
        );
    }

    /**
     * The visitor's page never reaches the logged-in shell's filter line component, which the walk does see.
     *
     * Two filter lines until stage R4 of docs/vitrine-react/PLAN.md: the logged-in module names the React
     * component and hands it to the shared mountLine(), the visitor's arms categoryline (with categorypreview
     * and categorymark), exactly as before. The control walks from the logged-in module, through the
     * component's relative imports and its preview imported on first use, so a walk blind to a component
     * named for a mount does not pass this test.
     *
     * Changes that must make it fail: the specifier named in a shared module (categorylisting); the
     * visitor's module handing its line to the component.
     */
    public function test_the_visitors_graph_never_reaches_the_filter_line_component(): void {
        $component = self::ESM . 'filterline/FilterLine';
        $logged = $this->graph('categoryvitrine');
        $this->assertContains($component, $logged, 'The control: the walk sees the component.');
        $this->assertContains(self::ESM . 'filterline/preview', $logged, 'And the preview it imports on first use.');
        $this->assertNotContains('categoryline', $logged, 'The logged-in shell no longer arms the AMD line.');
        $graph = $this->graph(self::MODULE);
        foreach (['categoryline', 'categorypreview', 'categorymark'] as $module) {
            $this->assertContains($module, $graph, "The visitor keeps {$module}.");
        }
        foreach ($graph as $module) {
            $this->assertStringStartsNotWith(self::ESM . 'filterline/', $module, 'The visitor\'s page reaches ' . $module . '.');
            $this->assertStringNotContainsString(
                '@moodle/lms/theme_dimensions/filterline',
                $this->code($module),
                $module . ' names the logged-in filter line component.'
            );
        }
        $this->assertStringContainsString('Line.arm(root, {dropTerm: dropTerm});', $this->code());
        $this->assertStringNotContainsString('Listing.mountLine(', $this->code(), 'The visitor hands its line to nothing.');
    }

    /**
     * The visitor's page never reaches the logged-in shell's posters and panel component, which the walk does see.
     *
     * Two implementations of the panel until stage R4 of docs/vitrine-react/PLAN.md: categorypanel (logged in)
     * appends the React component, the visitor's module arms categoryareapanel and categoryposters exactly as
     * before. The control walks from the logged-in module, through the component's relative imports, so a walk
     * blind to a component named for a mount does not pass this test.
     *
     * Changes that must make it fail: the specifier named in a shared module (gate js_areas_visitor_no_component);
     * the visitor's module dropping the shared panel.
     */
    public function test_the_visitors_graph_never_reaches_the_panel_component(): void {
        $component = self::ESM . 'areas/Areas';
        $logged = $this->graph('categorypanel');
        $this->assertContains($component, $logged, 'The control: the walk sees the component.');
        $this->assertContains(self::ESM . 'areas/panel', $logged, 'And the panel it imports.');
        $this->assertNotContains(self::PANEL, $logged, 'The logged-in shell no longer arms the AMD panel.');
        $this->assertNotContains('categoryposters', $logged, 'Nor the AMD posters\' painters.');
        $graph = $this->graph(self::MODULE);
        foreach ([self::PANEL, 'categoryposters'] as $module) {
            $this->assertContains($module, $graph, "The visitor keeps {$module}.");
        }
        foreach ($graph as $module) {
            $this->assertStringStartsNotWith(self::ESM . 'areas/', $module, 'The visitor\'s page reaches ' . $module . '.');
            $this->assertStringNotContainsString(
                '@moodle/lms/theme_dimensions/areas',
                $this->code($module),
                $module . ' names the logged-in posters and panel component.'
            );
        }
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
     * Changes that must make it fail: importing categorystar anywhere in the graph (the shared
     * listing's marker import swapped for it); naming a logged-in service in any module of it; the
     * bridge, in its source or either build, naming categorystar or core_user/repository.
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
     * passing a cachekey (M.cfg.langrev or any other) to Ajax.call.
     */
    public function test_it_calls_only_the_visitors_service_and_never_with_a_cachekey(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression("~^const METHOD = '" . self::METHOD . "';$~m", $source);
        $this->assertSame(1, substr_count($source, 'methodname:'), 'One call site.');
        preg_match_all("~'(theme_dimensions_[a-z_]+)'~", $source, $services);
        $this->assertSame([self::METHOD], array_values(array_unique($services[1])), 'No other service is named.');
        $this->assertMatchesRegularExpression(
            '~return Ajax\.call\(\[\{\s*methodname: METHOD,\s*args: args,\s*\}\], true, false\)\[0\];~',
            $this->body('ask'),
            'One call, async, login not required, and nothing after it: no cachekey.'
        );
        // Both questions go through it: a page of the listing and a poster's panel.
        $this->assertStringContainsString('const response = await ask(rowsArgs(page));', $this->body('requestRows'));
        $this->assertStringContainsString('return ask(panelArgs(poster, psort));', $this->body('askPanel'));
        $code = $this->code();
        $this->assertStringContainsString('Ajax.call([{', $code, 'The control: the code is read.');
        foreach (['cachekey', 'langrev', 'templaterev'] as $never) {
            $this->assertStringNotContainsString($never, $code, 'The module never builds a cachekey: ' . $never);
        }
        $this->assertStringContainsString(self::METHOD, $this->build());
        $this->assertStringContainsString("import Ajax from 'core/ajax';", $source);

        /* And nothing else the module loads calls a service of its own: a shared module asking some
           other theme service would pass the checks above, which read the entry file. The calendar
           reaches core's own string and template services through core/str and core/templates,
           which are core's to call. */
        foreach ($this->graph(self::MODULE) as $module) {
            if ($module === self::MODULE) {
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
        $this->assertStringContainsString("import Config from 'core/config';", $this->source());
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
        $this->assertMatchesRegularExpression('~chip: \'\',\s*fav: false,~', $this->source(), 'Not even at load.');
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
        $this->assertStringContainsString('document.body.classList.add(BODY_CLASS);', $this->body('init'));
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
            '~^const fallBack = async\(url\) => \{$~m',
            $source,
            'It is handed the address alone.'
        );

        $request = $this->body('requestRows');
        $this->assertMatchesRegularExpression(
            '~\} catch \(error\) \{\s*if \(ticket === seq\) \{\s*fallBack\(fallbackUrl\(page, append\)\);~',
            $request,
            'Only the latest request falls back, and with the address alone.'
        );
        $init = $this->body('init');
        $this->assertStringNotContainsString('requestRows(', $init, 'init never fetches.');
        $this->assertStringNotContainsString('Ajax.call(', $init);
        $this->assertStringNotContainsString('ask(', $init);
        $this->assertStringNotContainsString('client.ask(', $this->body('arm', self::PANEL), 'Nor does arming the panels.');
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
        $merge = $this->body('answerContext', self::PANEL);
        $this->assertMatchesRegularExpression(
            '~if \(!response\.panel \|\| Number\(response\.panel\.id\) !== id\) \{\s*throw new Error\(~',
            $merge,
            'An answer with no panel of the area asked about is a failure.'
        );
        $request = $this->body('request', self::PANEL);
        $found = preg_match('~\} catch \(error\) \{(.*?)\} finally \{~s', $request, $matches);
        $this->assertSame(1, $found, 'The request has no catch block.');
        $catch = $matches[1];
        $this->assertMatchesRegularExpression('~if \(ticket !== state\.ticket\) \{\s*return;~', $catch, 'Only the latest request.');
        $handed = strpos($catch, 'if (client.unanswered(poster, state.psort)) {');
        $this->assertIsInt($handed, 'A failure is offered to the client.');
        $this->assertLessThan(
            (int) strpos($catch, 'transplant(shellContext(poster, {iserror: true}), ticket)'),
            $handed,
            'Before the inline error is drawn.'
        );

        $failed = $this->body('panelFailed');
        $this->assertStringContainsString('fallBack(panelAddress(Number(poster.dataset.categoryid), psort));', $failed);
        $this->assertStringContainsString('return true;', $failed, 'The visitor never gets the inline error.');
        $address = $this->body('panelAddress');
        $this->assertStringContainsString('const url = Listing.buildUrl(state);', $address);
        $this->assertStringContainsString('url.searchParams.set(PARAM.open, String(id));', $address);
        $this->assertStringContainsString('url.searchParams.set(PARAM.psort, PSORT_NAME);', $address);
        $this->assertStringContainsString('url.hash = PANEL_ANCHOR;', $address, 'The page lands on the panel.');
        $this->assertStringContainsString('unanswered: panelFailed,', $this->body('init'));
        /* The panel module wrote the open parameter into the address when the panel opened, so the
           poster's link differs from the address on screen by its fragment alone - which assign()
           answers with a scroll inside the document, not a page. Such an address is loaded again. */
        $load = $this->body('load');
        $this->assertMatchesRegularExpression(
            "~if \(bare\.toString\(\) === here\.toString\(\)\) \{\s*"
                . "window\.history\.replaceState\(window\.history\.state, '', target\);\s*"
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
     * carries are built by categorylisting's buildUrl(), the logged-in client's own, so an address
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
            '~^\s*state\.page = 0;\s*Listing\.writeUrl\(state, true\);\s*linkPosters\(\);\s*return requestRows\(0, false\);\s*$~',
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
            "~AreaPanel\.criteria\(\{accordion: accordion, areas: response\.areas \|\| \{\}\}\);\s*linkPosters\(\);~",
            $this->body('paintAreas'),
            'Every answer is painted with the open panel asked again, and points the posters again.'
        );
        $this->assertMatchesRegularExpression(
            "~if \(state\.open\) \{\s*place\(\);\s*request\(\);~",
            $this->body('criteria', self::PANEL),
            'The shared painting asks the open panel again.'
        );
        // The shell a panel shows before its rows arrive carries the criteria, so it follows them too.
        $paint = $this->body('paintAreas');
        $this->assertStringContainsString("poster.dataset.ctaurl = entry.ctaurl || '';", $paint);
        $this->assertStringContainsString("poster.dataset.ctalabel = entry.ctalabel || '';", $paint);
        $this->assertStringContainsString('const url = Listing.buildUrl(state);', $this->body('fallbackUrl'));
        $this->assertStringContainsString('linkPosters();', $this->body('init'), 'The open poster links to its own panel too.');
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
        $this->assertMatchesRegularExpression('~^const SEARCH_DEBOUNCE = 300;$~m', $this->source('categoryvitrine'));
        $listen = $this->body('listen');
        $this->assertStringContainsString("search.addEventListener('input', () => onSearchInput(search));", $listen);
        $this->assertStringContainsString("form.addEventListener('submit', (event) => event.preventDefault());", $listen);
        $this->assertMatchesRegularExpression(
            '~^    cancelSearch\(\);\n    Listing\.paintEraser\(root\);\n'
                . '    const pending = new Pending\(\'theme_dimensions/categorypublic:search\'\);\n'
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
            '~notice\.hidden = true;\s*\}\s*state\.query = term;\s*await apply\(\);~',
            $this->body('onSearch'),
            'The timer applies the term, a panel open or not.'
        );
        $code = $this->code();
        $this->assertStringContainsString('apply();', $code, 'The control: the code is read.');
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
     * The shared panel module turns the logged-in client's posters into buttons; a client that keeps
     * them links (this one) has each announced as the button it acts as - role, state, the panel it
     * controls - and answers Enter (a click) and Space on it, while a press meant for another tab or
     * window, with a modifier or already dealt with, is left to the link. A middle or secondary
     * press is an event of its own and never reaches the click listener.
     *
     * Changes that must make it fail: arming the panels without links (the posters become buttons
     * with no address); intercepting a modified press; Space doing nothing on a poster.
     */
    public function test_a_visitors_poster_stays_its_link_and_a_plain_press_opens_it(): void {
        $this->assertMatchesRegularExpression('~AreaPanel\.arm\(\{[^}]*\blinks: true,~s', $this->body('init'));
        $this->assertDoesNotMatchRegularExpression(
            '~\blinks:~',
            (string) file_get_contents($this->plugin_root() . '/amd/src/categorypanel.js'),
            'The logged-in client keeps its buttons.'
        );
        $convert = $this->body('convertPoster', self::PANEL);
        $this->assertMatchesRegularExpression(
            "~if \(client\.links\) \{\s*attributes\.role = 'button';\s*"
                . 'Object\.keys\(attributes\)\.forEach\(\(name\) => '
                . 'control\.setAttribute\(name, attributes\[name\]\)\);\s*return;~',
            $convert,
            'The link is kept, announced as a button with its state and the panel it controls.'
        );
        $this->assertLessThan((int) strpos($convert, 'toButton('), (int) strpos($convert, 'if (client.links) {'));
        $this->assertStringContainsString("'aria-controls': PANEL_ID,", $convert);

        $click = $this->body('onAreasClick', self::PANEL);
        $this->assertMatchesRegularExpression(
            "~if \(control\.tagName === 'A' && elsewhere\(event\)\) \{\s*return;\s*\}\s*"
                . 'event\.preventDefault\(\);\s*toggleArea\(poster\);~',
            $click,
            'A press for another tab or window is the link\'s.'
        );
        $this->assertMatchesRegularExpression(
            '~const elsewhere = \(event\) => event\.defaultPrevented \|\| event\.button !== 0\s*'
                . '\|\| event\.ctrlKey \|\| event\.metaKey \|\| event\.shiftKey \|\| event\.altKey;~',
            $this->source(self::PANEL)
        );
        $driven = $this->body('driven', self::PANEL);
        $this->assertStringContainsString(
            "return client.links && control.tagName === 'A' && isExpandable(poster) ? control : null;",
            $driven
        );

        $space = $this->body('onSpace', self::PANEL);
        $this->assertStringContainsString("if (event.key !== ' ' || event.repeat || !client.links) {", $space);
        $this->assertMatchesRegularExpression('~event\.preventDefault\(\);\s*toggleArea\(poster\);\s*return true;~', $space);
        $this->assertMatchesRegularExpression('~if \(onSpace\(event\)\) \{\s*return;~', $this->body('onKeydown', self::PANEL));
        $this->assertStringContainsString('const control = driven(poster);', $this->body('setPosterOpen', self::PANEL));
    }

    /**
     * A panel's question is asked once, and only after the reader stopped pressing.
     *
     * Every question costs the site a slice or a panel's reads, on the open internet. The open
     * panel is asked again with every answer to new criteria; an answer to criteria that did not
     * change - the back button over a change of view - finds the same question already asked for
     * the same opening, and a panel the server drew counts its address's question as asked. A
     * request waits PANEL_QUIET for the reader to stop pressing, and goes out only if it is still the
     * latest. There is no prefetch: nothing asks on hover. The logged-in client names neither, and is
     * asked every time as it always was.
     *
     * Changes that must make it fail: asking an answered question again; asking with no quiet;
     * dropping the ticket check after the quiet.
     */
    public function test_a_panels_question_is_asked_once_after_a_quiet(): void {
        $init = $this->body('init');
        $this->assertStringContainsString('question: panelQuestion,', $init);
        $this->assertStringContainsString('quiet: PANEL_QUIET,', $init);
        $this->assertMatchesRegularExpression('~^const PANEL_QUIET = [1-9][0-9]*;$~m', $this->source());
        $this->assertStringContainsString('return JSON.stringify(panelArgs(poster, psort));', $this->body('panelQuestion'));

        $this->assertMatchesRegularExpression(
            "~const answered = \(question\) => question !== '' && question === state\.asked "
                . '&& state\.askedticket === state\.ticket;~',
            $this->source(self::PANEL)
        );
        $request = $this->body('request', self::PANEL);
        $asked = strpos($request, "if (answered(question)) {\n        return;\n    }");
        $ticket = strpos($request, 'const ticket = ++state.ticket;');
        $this->assertIsInt($asked, 'An answered question is not asked again.');
        $this->assertIsInt($ticket);
        $this->assertLessThan($ticket, $asked, 'Before a ticket is taken, which would supersede the answer on screen.');
        $this->assertMatchesRegularExpression('~state\.asked = question;\s*state\.askedticket = ticket;~', $request);
        $quiet = strpos($request, "if (!(await quiet(ticket))) {\n            return;\n        }");
        $this->assertIsInt($quiet, 'A request waits for the quiet and stays the latest.');
        $this->assertLessThan((int) strpos($request, 'await client.ask(poster, state.psort);'), $quiet);
        $this->assertMatchesRegularExpression(
            '~await new Promise\(\(resolve\) => window\.setTimeout\(resolve, client\.quiet\)\);\s*\}\s*'
                . 'return ticket === state\.ticket;~',
            $this->body('quiet', self::PANEL)
        );
        $this->assertStringContainsString("state.asked = '';", $request, 'A failed question may be asked again.');
        $adopt = $this->body('adopt', self::PANEL);
        $this->assertMatchesRegularExpression(
            '~state\.asked = client\.question\(poster, state\.psort\);\s*state\.askedticket = state\.ticket;~',
            $adopt,
            'A panel the server drew is the answer to its address.'
        );
        foreach ([self::MODULE, self::PANEL] as $module) {
            foreach (['mouseenter', 'mouseover', 'pointerover', 'pointerenter'] as $hover) {
                $this->assertStringNotContainsString("'{$hover}'", $this->code($module), "{$module} asks nothing on hover.");
            }
        }
    }

    /**
     * Back and forward read the address again and ask for it; a request out makes the listing busy.
     */
    public function test_the_history_and_the_pending_state(): void {
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            "~window\.addEventListener\('popstate', \(\) => \{.*?Listing\.readUrl\(state\);\s*publicOnly\(\);\s*"
                . 'Listing\.paintControls\(root, state\);\s*Listing\.syncFold\(\);\s*linkPosters\(\);\s*requestRows\(0, false\);~s',
            $listen
        );
        $request = $this->body('requestRows');
        $this->assertStringContainsString("root.setAttribute('aria-busy', 'true');", $request);
        $this->assertStringContainsString('Posters.pending(areas, {filtered: Listing.narrows(state)});', $request);
        $this->assertMatchesRegularExpression(
            "~finally \{\s*if \(ticket === seq\) \{\s*root\.removeAttribute\('aria-busy'\);~",
            $request
        );
    }

    /**
     * The committed builds of the module and of the three it paints with were made from the sources beside them.
     */
    public function test_the_committed_builds_were_made_from_the_committed_sources(): void {
        foreach ([self::MODULE, 'categorylisting', 'categoryposters', self::PANEL, 'categoryline'] as $module) {
            $this->assertSame(
                $this->source($module),
                $this->built_from($module),
                "amd/build/{$module} is not this source: rebuild it with grunt amd and commit the build."
            );
        }
        $this->assertStringContainsString('theme_dimensions/categorylisting', $this->build());
        $this->assertStringContainsString('theme_dimensions/categoryposters', $this->build());
        $this->assertStringContainsString('theme_dimensions/' . self::PANEL, $this->build());
        $this->assertStringContainsString('theme_dimensions/categoryline', $this->build());
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
        $this->assertMatchesRegularExpression('~publicOnly\(\);\s*Listing\.paintControls\(root, state\);\s*apply\(\);~', $remove);
        $this->assertStringContainsString("await onSearch('');", $this->body('onErase'));
    }
}
