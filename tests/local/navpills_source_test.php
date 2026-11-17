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
 * Theme Dimensions - Tests for the client of the category navigation's row, by its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;
use theme_dimensions\local\category\navgroups;
use theme_dimensions\output\category_navpills;

/**
 * Pins what PHPUnit can see of the row's client: js/esm/src/navpills, its AMD entry and the builds.
 *
 * The behaviour is Jest's (js/esm/tests/navpills): the fit arithmetic, the roles once armed, one menu open at a
 * time, Escape, the outside press, the moves into More. This test holds, by spelling and scoped to the function
 * each property is about, what a mutation sweep can break and PHPUnit must see redden: the roles claimed only by
 * the arming, Escape giving the focus back to the pill, the outside press closing without moving it, the fonts
 * awaited, the units moved and never cloned, every unit put back before a measure and no measure while a menu is
 * open, layout read in the measuring pass and at an opening alone, and the Pending tokens over the two waits.
 * And what the row may reach: an account's navigation, it imports core's pending counter and its own arithmetic,
 * names nothing of the logged-in listing or a service, and no module of the visitor's page names it (the walk of
 * categorypublic_source_test forbids it too). The build-currency assertion is the AMD entry's; the ES tree's is
 * esm_source_test's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class navpills_source_test extends \basic_testcase {
    /** @var string The row's module, under the theme root. */
    private const ROW = 'js/esm/src/navpills/row.ts';

    /** @var string The fit arithmetic, under the theme root. */
    private const FIT = 'js/esm/src/navpills/fit.ts';

    /** @var string The AMD entry the core renderer arms, under the theme root. */
    private const ENTRY = 'amd/src/navpills.js';

    /** @var string[] The functions of the row allowed to read layout: the measuring pass and a menu's placing. */
    private const READERS = ['measure', 'place'];

    /** @var string What reads layout in a browser, forcing it when the page has changed since. */
    private const LAYOUT_READ = '~getBoundingClientRect|getComputedStyle|getClientRects|client(?:Width|Height)'
        . '|offset(?:Width|Height|Top|Left)|scroll(?:Width|Height)~';

    /**
     * One file of the theme.
     *
     * @param string $path The path under the theme root.
     * @return string
     */
    private function read(string $path): string {
        $file = dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($file);
        return (string) file_get_contents($file);
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
     * The body of one top-level arrow function of a module.
     *
     * Every function of the two modules is a top-level `const <name> = (…) => {` on one line, closed by a `};` in
     * the first column, which is what makes the extraction unambiguous.
     *
     * @param string $name The function's name.
     * @param string $path The module, under the theme root.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $path = self::ROW): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read($path), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$path}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The names of every top-level function of a module.
     *
     * @param string $path The module, under the theme root.
     * @return string[]
     */
    private function functions(string $path): array {
        preg_match_all('~^(?:export )?const (\w+) = (?:async)?\(~m', $this->read($path), $found);
        return $found[1];
    }

    /**
     * The top-level declarations of a module's code in which a pattern matches, by the name each declares.
     *
     * Each match is given to the last top-level `const` before it, so a function whose body is one expression
     * is read as surely as one with a block.
     *
     * @param string $pattern The pattern.
     * @param string $path The module, under the theme root.
     * @return string[] The names, sorted, each once.
     */
    private function declarations_matching(string $pattern, string $path): array {
        $code = $this->uncommented($this->read($path));
        preg_match_all('~^(?:export )?const (\w+) = ~m', $code, $declarations, PREG_OFFSET_CAPTURE);
        preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
        $names = [];
        foreach ($matches[0] as [, $offset]) {
            $owner = '(top level)';
            foreach ($declarations[1] as [$name, $at]) {
                if ($at < $offset) {
                    $owner = $name;
                }
            }
            $names[$owner] = true;
        }
        $names = array_keys($names);
        sort($names);
        return $names;
    }

    /**
     * The module specifiers a module imports.
     *
     * @param string $path The module, under the theme root.
     * @return string[]
     */
    private function imports(string $path): array {
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $this->read($path), $found);
        return $found[1];
    }

    /**
     * The extractor returns one function and not the file around it.
     *
     * @return void
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $measure = $this->body('measure');
        $this->assertStringContainsString('restore(row);', $measure);
        $this->assertStringNotContainsString('addEventListener', $measure, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('addEventListener', $this->body('listen'));
        $this->assertContains('arm', $this->functions(self::ROW));
        $this->assertContains('fit', $this->functions(self::FIT));
    }

    /**
     * The row claims the menu roles at its arming and nowhere else, after the live class, with the keys' listeners.
     *
     * The server draws no role (category_navpills_test): a menu the page calls a menu must answer the keys a menu
     * promises, which only the armed module does.
     *
     * Changes that must make it fail: a role set outside addRoles(); addRoles() called anywhere but arm(); the
     * summary losing aria-haspopup, aria-controls or aria-expanded; the links losing role="menuitem".
     *
     * @return void
     */
    public function test_the_menu_roles_are_claimed_only_by_the_arming(): void {
        $roles = $this->body('addRoles');
        $this->assertStringContainsString("summary.setAttribute('aria-haspopup', 'menu');", $roles);
        $this->assertStringContainsString("summary.setAttribute('aria-controls', menu.id);", $roles);
        $this->assertStringContainsString("summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');", $roles);
        $this->assertStringContainsString("menu.setAttribute('role', 'menu');", $roles);
        $this->assertStringContainsString('itemise(link, true, false)', $roles);
        $this->assertStringContainsString("link.setAttribute('role', 'menuitem');", $this->body('itemise'));

        $code = $this->uncommented($this->read(self::ROW));
        $this->assertSame(1, substr_count($code, "setAttribute('role', 'menu')"), 'The menu role is set in one place.');
        $this->assertSame(1, substr_count($code, 'addRoles('), 'addRoles() has one caller.');
        $arm = $this->body('arm');
        $this->assertMatchesRegularExpression(
            '~nav\.classList\.add\(CLASSES\.live\);\s*addRoles\(row\);\s*listen\(row\);~',
            $arm,
            'The arming claims the roles beside the listeners that keep them.'
        );
        $this->assertMatchesRegularExpression("~^    live: 'dim-navpills--live',$~m", $code, 'The class the stylesheet keys on.');
    }

    /**
     * Escape inside a menu closes it and gives the focus back to its pill, and is not handed on.
     *
     * @return void
     */
    public function test_escape_closes_the_menu_and_returns_the_focus_to_its_pill(): void {
        $key = $this->body('menuKey');
        $this->assertMatchesRegularExpression(
            "~if \(event\.key === 'Escape'\) \{\s*event\.preventDefault\(\);\s*event\.stopPropagation\(\);\s*"
                . 'close\(row, details, true\);\s*return;~',
            $key
        );
        $close = $this->body('close');
        $this->assertMatchesRegularExpression(
            '~if \(focus\) \{.*?const pill = details\.hidden \? summaryOf\(row\.more\) : summaryOf\(details\);'
                . '\s*pill\?\.focus\(\);~s',
            $close
        );
        $this->assertStringContainsString('details.open = false;', $close);
    }

    /**
     * A press outside the open menu closes it without moving the focus; Tab out of it closes it too.
     *
     * The press is heard on the document in capture, before anything under the pointer answers it.
     *
     * @return void
     */
    public function test_an_outside_press_and_a_tab_out_close_without_moving_the_focus(): void {
        $this->assertStringContainsString(
            "document.addEventListener('pointerdown', (event) => onPress(row, event), true);",
            $this->body('listen')
        );
        $press = $this->body('onPress');
        $this->assertStringContainsString('!row.open.contains(target)', $press);
        $this->assertStringContainsString('close(row, row.open, false);', $press);
        $out = $this->body('onFocusOut');
        $this->assertStringContainsString('!row.open.contains(next)', $out);
        $this->assertStringContainsString('close(row, row.open, false);', $out);
    }

    /**
     * One menu is open at a time, whether this module or the browser opened it.
     *
     * A native opening (a press on a pill) is heard as the toggle event, in capture because it does not bubble.
     *
     * @return void
     */
    public function test_one_menu_is_open_at_a_time(): void {
        $opened = $this->body('opened');
        $this->assertMatchesRegularExpression(
            '~if \(other !== details && other\.open\) \{\s*close\(row, other, false\);~',
            $opened
        );
        $this->assertStringContainsString(
            "row.nav.addEventListener('toggle', (event) => onToggle(row, event), true);",
            $this->body('listen')
        );
        $this->assertStringContainsString('opened(row, details);', $this->body('onToggle'));
        $this->assertStringContainsString('opened(row, details);', $this->body('open'));
        // The new menu is recorded before the others close: their closing may run a deferred pass.
        $this->assertLessThan(strpos($opened, 'disclosures(row).forEach'), strpos($opened, 'row.open = details;'));
    }

    /**
     * The fonts are awaited before a second pass, under a Pending token released whatever happens.
     *
     * @return void
     */
    public function test_the_fonts_are_awaited_under_a_pending_token(): void {
        $fonts = $this->body('afterFonts');
        $this->assertMatchesRegularExpression(
            "~const pending = new Pending\('theme_dimensions/navpills:fonts'\);\s*try \{\s*await document\.fonts\.ready;\s*"
                . 'measure\(row\);\s*\} finally \{\s*pending\.resolve\(\);~',
            $fonts
        );
        $this->assertStringContainsString('afterFonts(row);', $this->body('arm'));
    }

    /**
     * The arming's token spans the wait for the first pass, the ResizeObserver's first entry, and is released after it.
     *
     * Changes that must make it fail: the token released before the pass, or only when the pass does not throw.
     *
     * @return void
     */
    public function test_the_arming_token_spans_the_first_pass(): void {
        $this->assertStringContainsString("const pending = new Pending('theme_dimensions/navpills:arm');", $this->body('arm'));
        $this->assertStringContainsString('observe(row, pending);', $this->body('arm'));
        $observe = $this->body('observe');
        $this->assertMatchesRegularExpression(
            '~measure\(row\);\s*\}\s*\} finally \{\s*if \(first\) \{\s*first = false;\s*pending\.resolve\(\);~',
            $observe,
            'Released after the observer\'s first pass.'
        );
        $this->assertStringContainsString('observer.observe(row.track);', $observe, 'The track is observed, never the capsule.');
        $this->assertSame(2, substr_count($observe, 'pending.resolve();'), 'And after the pass run at once without an observer.');
    }

    /**
     * Each pass puts every unit back in the server's order before it measures, and never runs while a menu is open.
     *
     * Changes that must make it fail: measuring before restore(); measuring with a menu open; a deferred pass never
     * run when the menu closes.
     *
     * @return void
     */
    public function test_every_pass_puts_the_units_back_and_waits_for_an_open_menu(): void {
        $measure = $this->body('measure');
        $this->assertMatchesRegularExpression(
            '~^    if \(row\.open\) \{\s*row\.stale = true;\s*return;\s*\}\s*row\.stale = false;\s*restore\(row\);~',
            $measure,
            'An open menu defers the pass; otherwise the units go back first.'
        );
        $this->assertLessThan(strpos($measure, 'getBoundingClientRect'), strpos($measure, 'restore(row);'));
        $this->assertLessThan(strpos($measure, 'apply(row, fit('), strpos($measure, 'restore(row);'));
        $closed = $this->body('closed');
        $this->assertMatchesRegularExpression('~row\.open = null;\s*if \(row\.stale\) \{\s*measure\(row\);~', $closed);

        $restore = $this->body('restore');
        $this->assertStringContainsString('unit.menu.append(...unit.items);', $restore, 'A group\'s links go back to its menu.');
        $this->assertStringContainsString('row.added.forEach((element) => element.remove());', $restore);
        $this->assertMatchesRegularExpression(
            '~for \(let index = row\.units\.length - 1; index >= 0; index--\) \{.*?row\.nav\.insertBefore\(element, anchor\);~s',
            $restore,
            'Every unit back on the row, in the server\'s order, before More.'
        );
    }

    /**
     * The units are moved into More, never cloned, and no markup is written from a string.
     *
     * One link per destination: a clone would put a second link, and a second id, in the page.
     *
     * @return void
     */
    public function test_the_units_are_moved_never_cloned(): void {
        $code = $this->uncommented($this->read(self::ROW));
        foreach (['cloneNode', 'importNode', 'innerHTML', 'outerHTML', 'insertAdjacentHTML'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $code, "The row's client uses {$forbidden}.");
        }
        $apply = $this->body('apply');
        $this->assertStringContainsString('section.append(...unit.items);', $apply, 'A moved group\'s own links.');
        $this->assertStringContainsString('row.moremenu.append(unit.element);', $apply, 'A moved plain pill itself.');
        $this->assertStringContainsString('unit.group.hidden = true;', $apply);
        $this->assertStringContainsString("section.setAttribute('role', 'group');", $apply);
        $this->assertStringContainsString("section.setAttribute('aria-labelledby', head.id);", $apply);
        $this->assertSame(2, substr_count($apply, "make(row, 'div', CLASSES.separator).setAttribute('role', 'separator');"));
    }

    /**
     * Layout is read in the measuring pass and as a menu opens, and nowhere else; the arithmetic reads none.
     *
     * @return void
     */
    public function test_layout_is_read_only_by_the_measuring_pass_and_the_placing(): void {
        $this->assertSame(self::READERS, $this->declarations_matching(self::LAYOUT_READ, self::ROW));
        $this->assertSame(['measure'], $this->declarations_matching('~\bfit\(~', self::ROW), 'The pass alone asks fit().');

        $fit = $this->uncommented($this->read(self::FIT));
        $this->assertDoesNotMatchRegularExpression(self::LAYOUT_READ, $fit);
        $this->assertDoesNotMatchRegularExpression('~\b(?:document|window)\b~', $fit, 'The arithmetic is pure.');
        $this->assertSame([], $this->imports(self::FIT), 'And imports nothing.');
    }

    /**
     * The row reads the server's hooks: the region the renderable names and the page itself by navgroups' key.
     *
     * @return void
     */
    public function test_it_reads_the_servers_names(): void {
        $code = $this->read(self::ROW);
        $this->assertMatchesRegularExpression("~^export const MAIN = '" . navgroups::MAIN . "';$~m", $code);
        $this->assertMatchesRegularExpression(
            "~^    row: '\\[data-region=\"" . category_navpills::REGION . "\"\\]',$~m",
            $code
        );
        $template = $this->read('templates/category_navpills.mustache');
        $hooks = ['data-region="dim-navpills-more"', 'class="dim-navpill-menu"', 'dim-navpill-menu__item', 'dim-navpill__label'];
        foreach ($hooks as $hook) {
            $this->assertStringContainsString($hook, $template, "The template draws {$hook}.");
        }
    }

    /**
     * An account's navigation: it imports the pending counter and its arithmetic only, and nothing of the visitor's
     * page names it.
     *
     * The visitor's shell draws no row, and the walk of categorypublic_source_test forbids the row's modules; here,
     * the row's own imports are pinned, and so is its silence about the logged-in listing and every service.
     *
     * @return void
     */
    public function test_it_is_an_accounts_and_the_visitors_page_never_names_it(): void {
        $this->assertSame(['@moodle/lms/core/pending', './fit', './fit'], $this->imports(self::ROW));
        $this->assertSame(
            ['core/esm!@moodle/lms/theme_dimensions/navpills/row'],
            $this->imports(self::ENTRY),
            'The entry hands over to the row and imports nothing else.'
        );
        $code = $this->uncommented($this->read(self::ROW) . $this->read(self::FIT) . $this->read(self::ENTRY));
        foreach (['listing/', 'listingaccount', 'listingvisitor', '../amd', 'core/ajax', 'core/fetch', 'methodname'] as $name) {
            $this->assertStringNotContainsString($name, $code, "The row names {$name}.");
        }
        $visitor = [$this->read('amd/src/listingvisitor.js')];
        foreach (glob(dirname(__DIR__, 2) . '/js/esm/src/listing/*.ts') as $file) {
            $visitor[] = (string) file_get_contents($file);
        }
        $visitor[] = $this->read('js/esm/src/amd.ts');
        foreach ($visitor as $text) {
            $this->assertStringNotContainsString('navpills', $text, 'A module of the listing or the bridge names the row.');
        }
    }

    /**
     * The committed AMD build was made from the committed source: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_entry_build_was_made_from_the_committed_source(): void {
        $this->assertFileExists(dirname(__DIR__, 2) . '/amd/build/navpills.min.js', 'The build is missing.');
        $map = json_decode($this->read('amd/build/navpills.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertSame($this->read(self::ENTRY), (string) ($map['sourcesContent'][0] ?? ''));
        foreach (['navpills/row', 'navpills/fit'] as $module) {
            foreach (['js', 'dev.js', 'dev.js.map'] as $build) {
                $this->assertFileExists(dirname(__DIR__, 2) . "/js/esm/build/{$module}.{$build}");
            }
        }
    }
}
