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
 * Theme Dimensions - Tests for how the logged-in shell hands its posters and their panel to the component
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the shells' half of the posters' component by reading the source.
 *
 * The component's behaviour is Jest's (js/esm/tests/areas), in both shells' modes. What Jest cannot see is how
 * the shells hand the posters and their panel over and keep out of them: the shared listing's mountAreas()
 * appends the mount, listens first and hands the component the page's client's answers; the account's shell
 * (js/esm/src/listing/account.ts) answers with the logged-in service and paints the progress it is told about,
 * and the visitor's with its own service, its question and its failure (categorypublic_source_test); the store
 * tells the component an answer and writes nothing into the posters; the events are spelled alike on every side;
 * the component is named once, in the shared listing; every hook the component reads is written by the templates; and the
 * component reads layout only where its docblocks say, never at mount.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class areas_component_source_test extends \basic_testcase {
    /** @var string The component's specifier, resolved by core's import map to js/esm/build/areas/Areas.js. */
    private const SPECIFIER = '@moodle/lms/theme_dimensions/areas/Areas';

    /** @var string[] The component's modules under js/esm/src/areas, each built. */
    private const MODULES = ['Areas', 'panel', 'posters', 'rail', 'model', 'mark'];

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
     * @param string $module The AMD module's name, or a path under js/esm/src without its extension.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = 'listing/account'): string {
        $path = str_contains($module, '/') ? 'js/esm/src/' . $module . '.ts' : 'amd/src/' . $module . '.js';
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read($path), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
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
        $found = preg_match($pattern, $this->read('js/esm/src/areas/panel.ts'), $matches);
        $this->assertSame(1, $found, "No function named {$name} in the component's panel.");
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
     * The names of an object literal of a source, as name => value.
     *
     * @param string $source The source.
     * @param string $declaration The literal's opening line, up to its brace.
     * @return array
     */
    private function names(string $source, string $declaration): array {
        $found = preg_match('~\n' . preg_quote($declaration, '~') . '\n(.*?)\n\};~s', $source, $matches);
        $this->assertSame(1, $found, "No {$declaration}.");
        preg_match_all("~(\\w+): '([^']+)'~", $matches[1], $pairs, PREG_SET_ORDER);
        return array_column($pairs, 2, 1);
    }

    /**
     * The extractors return one function and not the module around it.
     */
    public function test_the_extractors_return_one_function_and_not_the_file(): void {
        $mount = $this->body('mountAreas', 'listing/mounts');
        $this->assertStringNotContainsString('appendCalendar', $mount);
        $settle = $this->inner('settle');
        $this->assertStringNotContainsString('const openArea', $settle);
        $this->assertStringContainsString('panel.classList.remove(CLASSES.shut, CLASSES.still, CLASSES.fading);', $settle);
    }

    /**
     * The events are spelled once, in the shared listing's events.ts, and every other module imports them.
     *
     * A name spelled differently on one side errors nowhere: the component never hears an answer, or the page's
     * client never answers a question and every panel opens into its error. The component, the store and the
     * listing's mounts import the names from that one file.
     *
     * Changes that must make it fail: a name of events.ts's AREAS or LISTING spelled otherwise, or a module of
     * js/esm/src spelling an event of its own instead of importing it.
     */
    public function test_the_two_sides_spell_the_events_alike(): void {
        $events = $this->read('js/esm/src/listing/events.ts');
        $areas = $this->names($events, 'export const AREAS = {');
        $this->assertSame([
            'mounted' => 'dim-areas:mounted',
            'ask' => 'dim-areas:ask',
            'question' => 'dim-areas:question',
            'unanswered' => 'dim-areas:unanswered',
            'painted' => 'dim-areas:painted',
        ], $areas);
        $listing = $this->names($events, 'export const LISTING = {');
        $this->assertSame([
            'view' => 'dim-vitrine:view',
            'pending' => 'dim-vitrine:pending',
            'criteria' => 'dim-vitrine:criteria',
            'failed' => 'dim-vitrine:failed',
            'autoopened' => 'dim-panel:autoopened',
        ], $listing);
        $panel = $this->read('js/esm/src/areas/panel.ts');
        $this->assertStringContainsString("import {AREAS, LISTING} from '../listing/events';", $panel);
        $this->assertStringContainsString('export const EVENTS = {...AREAS, ...LISTING};', $panel);
        // The store, both shells', sends the listing's own names.
        $store = $this->read('js/esm/src/listing/store.ts');
        $this->assertStringContainsString("import {LISTING, SELECTORS} from './events';", $store);
        $this->assertSame(2, preg_match_all('~new CustomEvent\(LISTING\.(?:pending|criteria),~', $store));
        $this->assertStringContainsString('export {EVENTS};', $this->read('js/esm/src/areas/Areas.tsx'));
        // One spelling: no other module of js/esm/src writes an event name of its own.
        $files = array_merge(
            glob($this->plugin_root() . '/js/esm/src/*.ts'),
            glob($this->plugin_root() . '/js/esm/src/*/*.{ts,tsx}', GLOB_BRACE)
        );
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $name = substr($file, strlen($this->plugin_root()) + 1);
            if ($name === 'js/esm/src/listing/events.ts') {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                "~['\"\\x60]dim-(?:areas|vitrine|panel|line|daterange):[a-z]+['\"\\x60]~",
                $this->uncommented((string) file_get_contents($file)),
                "{$name} spells an event of its own instead of importing it from listing/events.ts."
            );
        }
    }

    /**
     * The mount is an empty, hidden child of the areas section, carrying the props object itself, named after
     * the listeners are on, and only on a page with a panel.
     *
     * Changes that must make it fail: mounting on a page without a panel (the component would draw nothing
     * and the pending would never end); the listeners after the node (a component already imported asks before
     * anybody answers); the node shown (a box the server never drew); the props nested under a key; the
     * logged-in progress no longer painted.
     */
    public function test_the_mount_is_a_hidden_child_named_after_the_listeners(): void {
        $this->assertStringContainsString(
            'Listing.mountAreas(areas, shell.areas.props, shell.areas.client);',
            $this->body('init', 'listing/store')
        );
        $this->assertStringContainsString(
            "    areas: {\n        props: {},\n        client: {\n"
                . "            ask: ask,\n            painted: paintPanelProgress,\n",
            $this->read('js/esm/src/listing/account.ts'),
            'The logged-in shell: no options, its service and its progress.'
        );
        $mount = $this->body('mountAreas', 'listing/mounts');
        $this->assertMatchesRegularExpression(
            '~^    if \(!areas \|\| !areas\.querySelector\(SELECTORS\.panel\)\) \{\s*return false;\s*\}~',
            $mount
        );
        $this->assertStringContainsString("panel: '[data-region=\"dim-panel\"]',", $this->read('js/esm/src/listing/events.ts'));
        $this->assertStringContainsString("const pending = new Pending('theme_dimensions/listing:areas');", $mount);
        $this->assertStringContainsString('node.hidden = true;', $mount);
        $this->assertStringContainsString('node.dataset.reactComponent = COMPONENTS.areas;', $mount);
        $this->assertStringContainsString('node.dataset.reactProps = JSON.stringify(props);', $mount, 'The props object itself.');
        $appended = strpos($mount, 'areas.append(node);');
        $this->assertNotFalse($appended);
        $listeners = [
            'areas.addEventListener(AREAS.ask, (event) => {',
            'areas.addEventListener(AREAS.question, (event) => {',
            'areas.addEventListener(AREAS.unanswered, (event) => {',
            'areas.addEventListener(AREAS.painted, (event) => painted((event as CustomEvent).detail.panel));',
            'areas.addEventListener(AREAS.mounted, () => pending.resolve(), {once: true});',
        ];
        foreach ($listeners as $listener) {
            $at = strpos($mount, $listener);
            $this->assertNotFalse($at, $listener . ' is missing.');
            $this->assertLessThan($appended, $at, $listener . ' comes after the node is appended.');
        }
        $this->assertStringContainsString(
            'areas.dispatchEvent(new CustomEvent(EVENTS.mounted, {bubbles: true}));',
            $this->read('js/esm/src/areas/Areas.tsx')
        );
    }

    /**
     * The component asks its rows through the page's client and calls no service itself; on the logged-in shell
     * that is the account's shell's service, and the progress of the rows it paints is that shell's.
     *
     * Changes that must make it fail: the question left unanswered (every panel opens into its error); the
     * progress never painted; the component calling a service or reading the session.
     */
    public function test_the_component_asks_through_the_pages_client_and_calls_no_service(): void {
        $mount = $this->body('mountAreas', 'listing/mounts');
        $this->assertStringContainsString('detail.answer = client.ask(detail.poster, detail.psort);', $mount);
        $this->assertStringContainsString('methodname: ROWS_METHOD,', $this->body('ask'));
        $this->assertStringContainsString(
            "const ROWS_METHOD = 'theme_dimensions_get_category_rows';",
            $this->read('js/esm/src/listing/account.ts')
        );
        $ask = $this->inner('ask');
        $this->assertStringContainsString('areas.dispatchEvent(new CustomEvent(EVENTS.ask, {detail: detail}));', $ask);
        $this->assertStringContainsString("return detail.answer || Promise.reject(", $ask);
        $this->assertStringContainsString(
            'areas.dispatchEvent(new CustomEvent(EVENTS.painted, {detail: {panel: panel}}));',
            $this->inner('painted')
        );
        foreach (self::MODULES as $module) {
            $source = $this->read('js/esm/src/areas/' . $module . (str_starts_with($module, 'A') ? '.tsx' : '.ts'));
            foreach (['theme_dimensions_', 'core/ajax', "load('core/str'", 'M.cfg', 'sesskey'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $this->uncommented($source), "{$module} names {$forbidden}.");
            }
        }
    }

    /**
     * The shared listing names the component, once, and the store mounts it for both shells.
     *
     * Changes that must make it fail: an AMD module naming the component itself; a module not built.
     */
    public function test_the_component_is_named_once_and_no_shell_arms_the_amd_panel(): void {
        $this->assertStringContainsString("    areas: '" . self::SPECIFIER . "',\n", $this->read('js/esm/src/listing/mounts.ts'));
        foreach (glob($this->plugin_root() . '/amd/src/*.js') as $file) {
            $this->assertStringNotContainsString(
                '@moodle/lms/theme_dimensions/areas',
                (string) file_get_contents($file),
                basename($file) . ' names the component.'
            );
        }
        $store = $this->read('js/esm/src/listing/store.ts');
        $this->assertStringContainsString('Listing.mountAreas(areas, ', $store, 'The store mounts it.');
        foreach (self::MODULES as $module) {
            foreach (['js', 'dev.js', 'dev.js.map'] as $build) {
                $this->assertFileExists($this->plugin_root() . "/js/esm/build/areas/{$module}.{$build}");
            }
        }
    }

    /**
     * The listing writes nothing into the posters: the store tells the component an answer and writes none of
     * them, and the component writes them.
     *
     * Changes that must make it fail: paintAreas() writing into a poster.
     */
    public function test_the_listing_writes_nothing_into_the_posters(): void {
        $shells = $this->body('paintAreas', 'listing/store');
        $this->assertStringNotContainsString('poster.', $shells);
        $this->assertStringNotContainsString('SELECTORS.area', $shells);
        $this->assertMatchesRegularExpression(
            '~document\.dispatchEvent\(new CustomEvent\(LISTING\.criteria, \{detail: \{\s*'
                . 'accordion: response\.accordion \|\| \[\],~',
            $shells
        );
        $account = $this->uncommented($this->read('js/esm/src/listing/account.ts'));
        $this->assertStringNotContainsString('dataset.ctaurl', $account);
        $this->assertSame(
            1,
            substr_count($account, 'HOOKS.areapanel'),
            'The panel is read once: its cards are never attached.'
        );
        $posters = $this->read('js/esm/src/areas/posters.ts');
        $this->assertStringContainsString("poster.dataset.ctaurl = entry.ctaurl || '';", $posters);
        $this->assertStringContainsString("poster.dataset.ctalabel = entry.ctalabel || '';", $posters);
    }

    /**
     * Every hook the component reads is written by the showcase's templates.
     *
     * The component addresses data attributes alone; a hook a template no longer writes would disarm it in
     * silence (the posters would stay links, a panel would never be found).
     */
    public function test_every_hook_the_component_reads_is_written_by_the_templates(): void {
        $templates = '';
        foreach (glob($this->plugin_root() . '/templates/*.mustache') as $file) {
            $templates .= (string) file_get_contents($file);
        }
        // The panel's two hidden fields are written by the toolbar's and the hero's builders, as a region value.
        foreach (['category_toolbar', 'category_hero'] as $builder) {
            preg_match_all("~'(dim-(?:open|psort)-field)'~", $this->read("classes/output/{$builder}.php"), $fields);
            foreach ($fields[1] as $field) {
                $templates .= 'data-region="' . $field . '"';
            }
        }
        $model = $this->read('js/esm/src/areas/model.ts');
        $found = preg_match('~\nexport const SELECTORS = \{\n(.*?)\n\};~s', $model, $matches);
        $this->assertSame(1, $found);
        preg_match_all('~\[data-(region|action)="([a-z-]+)"\]~', $matches[1], $hooks, PREG_SET_ORDER);
        $this->assertGreaterThan(40, count($hooks));
        foreach ($hooks as [, $kind, $value]) {
            $hook = "data-{$kind}=\"{$value}\"";
            $this->assertStringContainsString($hook, $templates, "No template writes {$hook}.");
        }
    }

    /**
     * The breakpoints the panel counts its tracks by are the stylesheet's.
     */
    public function test_the_breakpoints_are_the_stylesheets_own(): void {
        $model = $this->read('js/esm/src/areas/model.ts');
        $this->assertStringContainsString("export const WIDE = '(max-width: 1199.98px)';", $model);
        $this->assertStringContainsString("export const PHONE = '(max-width: 575.98px)';", $model);
        $scss = $this->read('scss/category.scss');
        $this->assertMatchesRegularExpression('~@media \(max-width: 1199\.98px\) \{\s*\.dim-panel \{\s*--dim-panel-n: 2;~', $scss);
        $this->assertMatchesRegularExpression('~@media \(max-width: 575\.98px\) \{\s*\.dim-panel \{\s*--dim-panel-n: 1;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-vitrine--list \.dim-panel \{\s*--dim-panel-n: 1;~', $scss);
        $this->assertStringContainsString("list: 'dim-vitrine--list',", $model);
    }

    /**
     * The panel's three transition classes are set only through settle(), which clears all three first.
     */
    public function test_the_transition_classes_are_set_only_through_settle(): void {
        $panel = $this->uncommented($this->read('js/esm/src/areas/panel.ts'));
        $this->assertSame(1, preg_match_all('~panel\.classList\.add\(~', $panel), 'Only settle() adds a class to the panel.');
        $this->assertSame(2, preg_match_all('~classList\.add\(~', $panel), 'And arm() the body\'s mark.');
        $this->assertStringContainsString('panel.classList.add(...classes);', $this->inner('settle'));
        $this->assertDoesNotMatchRegularExpression('~setClass\(panel, CLASSES\.(?:shut|still|fading), true\)~', $panel);
        $this->assertStringContainsString('document.body.classList.add(BODY_CLASS);', $this->inner('arm'));
    }

    /**
     * Layout is read only where the component says, and never at mount.
     *
     * The row is the five integers and the DOM order is counted from the posters shown and the grid's tracks
     * (matchMedia), never read off a box. The reads left: the pressed poster's top around a move between two
     * open areas, the panel's box once the opening has settled, a style read before the grow, the navbar's
     * height, and the rail's scroll (from a ResizeObserver entry and its own scroll).
     */
    public function test_layout_is_read_only_where_the_component_says(): void {
        $panel = $this->uncommented($this->read('js/esm/src/areas/panel.ts'));
        $this->assertSame(3, substr_count($panel, 'getBoundingClientRect()'));
        $this->assertSame(1, substr_count($this->inner('ensureVisible'), 'getBoundingClientRect()'));
        $this->assertSame(1, substr_count($this->inner('openArea'), 'getBoundingClientRect()'));
        $this->assertSame(1, substr_count($this->inner('moveTo'), 'getBoundingClientRect()'));
        $this->assertStringContainsString(
            'const before = prev ? poster.getBoundingClientRect().top : 0;',
            $this->inner('openArea')
        );
        $this->assertSame(2, substr_count($panel, 'getComputedStyle('));
        $reads = ['offsetTop', 'offsetLeft', 'offsetHeight', 'offsetWidth', 'clientWidth', 'scrollWidth', 'getClientRects'];
        foreach ($reads as $read) {
            $this->assertStringNotContainsString($read, $panel, "The panel reads {$read}.");
        }
        foreach (['place', 'adopt', 'relayout', 'sharesRow'] as $function) {
            $this->assertDoesNotMatchRegularExpression(
                '~getBoundingClientRect|getComputedStyle|railState|ensureVisible~',
                $this->inner($function),
                "{$function} reads layout."
            );
        }
        foreach (['posters', 'model', 'mark'] as $module) {
            $source = $this->uncommented($this->read("js/esm/src/areas/{$module}.ts"));
            $this->assertDoesNotMatchRegularExpression(
                '~getBoundingClientRect|getComputedStyle|offset(?:Top|Left|Width|Height)|scroll(?:Width|Left)|clientWidth~',
                $source,
                "{$module} reads layout."
            );
        }
        $rail = $this->uncommented($this->read('js/esm/src/areas/rail.ts'));
        $this->assertStringNotContainsString('getBoundingClientRect', $rail);
        $this->assertStringContainsString('paintRail(panel, railState(target));', $this->inner('observeRail'));
        $this->assertStringContainsString('new ResizeObserver(', $this->inner('observeRail'));
        $this->assertStringContainsString(
            'const columns = (list: boolean, phone: boolean, wide: boolean): number => {',
            $this->read('js/esm/src/areas/model.ts')
        );
        $this->assertStringContainsString('window.matchMedia(PHONE).matches,', $this->inner('tracks'));
    }
}
