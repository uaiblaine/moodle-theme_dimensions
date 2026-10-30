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
 * Theme Dimensions - Tests for the showcase's shared listing in TypeScript, by its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins how the shared listing of js/esm/src/listing reaches the AMD clients, and who writes the history.
 *
 * Stage R4b1 of docs/vitrine-react/R4b.md moved the AMD listing to TypeScript, under a barrel's names, and each
 * page's AMD entry starts its shell through `core/esm!`. The painters' own rules are read by amd_source_test and
 * the component source tests, their behaviour is Jest's (js/esm/tests/listing).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class listing_source_test extends \basic_testcase {
    /** @var string The specifier the AMD entries load their shell under. */
    private const SPECIFIER = 'core/esm!@moodle/lms/theme_dimensions/listing/';

    /** @var string[] The AMD entries the pages arm, by the shell each one starts (R4b2, R4b3). */
    private const ENTRIES = ['listingaccount' => 'account', 'listingvisitor' => 'visitor'];

    /** @var string[] The TypeScript that calls the listing by the barrel's names: the store and its shells. */
    private const SHELLS = ['js/esm/src/listing/store.ts', 'js/esm/src/listing/visitor.ts', 'js/esm/src/listing/account.ts'];

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
     * Every TypeScript source of js/esm/src, by its path under the theme root.
     *
     * @return string[]
     */
    private function esm_sources(): array {
        $root = $this->plugin_root();
        $files = array_merge(glob($root . '/js/esm/src/*.ts'), glob($root . '/js/esm/src/*/*.{ts,tsx}', GLOB_BRACE));
        sort($files);
        return array_map(static fn(string $file): string => substr($file, strlen($root) + 1), $files);
    }

    /**
     * The body of one top-level arrow function of a TypeScript source, a signature on several lines included.
     *
     * @param string $name The function's name.
     * @param string $path The source's path under the theme root.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $path): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read($path), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$path}.");
        return $matches[1];
    }

    /**
     * The extractor returns one function and not the module around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $write = $this->body('writeUrl', 'js/esm/src/listing/history.ts');
        $this->assertStringContainsString('syncFold();', $write);
        $this->assertStringNotContainsString('replaceKeeping', $write);
        $this->assertStringContainsString('appendCalendar(root, fieldset, region, state, onpick);', $this->body(
            'mountCalendar',
            'js/esm/src/listing/mounts.ts'
        ));
    }

    /**
     * Each AMD entry starts its TypeScript shell through core's ES module loader, and no AMD module loads the AMD listing.
     *
     * An entry loading an AMD client again would run a second copy of the listing, with its own counters and its own
     * history writer, beside the store.
     *
     * Changes that must make it fail: the account's entry loading an AMD client instead of its shell (gate
     * js_vitrine_listing_through_esm; the visitor's entry is js_public_listing_through_esm, read by
     * categorypublic_source_test's walk).
     */
    public function test_the_amd_clients_load_the_typescript_listing(): void {
        foreach (self::ENTRIES as $entry => $shell) {
            $source = $this->read('amd/src/' . $entry . '.js');
            $this->assertStringContainsString(
                "\nimport {start} from '" . self::SPECIFIER . $shell . "';\n",
                $source,
                "{$entry} starts its shell through core/esm!."
            );
            preg_match_all("~^import [^\n]* from '([^']+)';$~m", $source, $imports);
            // Its shell and nothing else: the star is the account's shell's since R5.
            foreach ($imports[1] as $import) {
                $this->assertContains(
                    $import,
                    [self::SPECIFIER . $shell],
                    "{$entry} loads {$import}."
                );
            }
            $this->assertStringContainsString('"' . self::SPECIFIER . $shell . '"', $this->read('amd/build/' . $entry . '.min.js'));
        }
    }

    /**
     * The barrel exports every name a shell calls on the listing, nothing it does not have, and nothing no shell calls.
     *
     * A name the barrel left out is undefined at run time, in the browser alone; a name no shell calls is one the
     * barrel kept for a client that is gone (R5 dropped the AMD clients' four).
     *
     * Changes that must make it fail: a name dropped from the barrel (gate js_listing_barrel_complete).
     */
    public function test_the_barrel_exports_every_name_the_amd_clients_call(): void {
        $barrel = $this->uncommented($this->read('js/esm/src/listing/listing.ts'));
        $this->assertSame(4, preg_match_all('~\} from \'\./(?:address|history|mounts|paint)\';~', $barrel));
        preg_match_all('~export \{([^}]*)\} from~s', $barrel, $lists);
        $exported = [];
        foreach ($lists[1] as $list) {
            foreach (preg_split('~[\s,]+~', $list, -1, PREG_SPLIT_NO_EMPTY) as $name) {
                $exported[] = $name;
            }
        }
        $called = [];
        foreach (self::SHELLS as $caller) {
            preg_match_all('~\bListing\.([A-Za-z]+)~', $this->uncommented($this->read($caller)), $uses);
            $called = array_merge($called, $uses[1]);
        }
        $called = array_values(array_unique($called));
        sort($called);
        $this->assertGreaterThan(30, count($called), 'The control: the shells\' calls are read.');
        foreach ($called as $name) {
            $this->assertContains($name, $exported, "A client calls Listing.{$name}, which the barrel does not export.");
        }
        foreach ($exported as $name) {
            $this->assertContains($name, $called, "The barrel exports {$name}, which no shell calls.");
        }
        // Each exported name exists in the module the barrel names it from.
        foreach (['address', 'history', 'mounts', 'paint'] as $file) {
            preg_match('~export \{([^}]*)\} from \'\./' . $file . '\';~s', $barrel, $list);
            $source = $this->read('js/esm/src/listing/' . $file . '.ts');
            foreach (preg_split('~[\s,]+~', $list[1], -1, PREG_SPLIT_NO_EMPTY) as $name) {
                $this->assertMatchesRegularExpression(
                    '~^export const ' . preg_quote($name, '~') . ' = ~m',
                    $source,
                    "The barrel exports {$name} from {$file}.ts, which has none."
                );
            }
        }
    }

    /**
     * The store reaches no shell: each shell imports the store and hands it what it is.
     *
     * The visitor's page loads the store, so a store that imported a shell, named a service or spelled a
     * shell's mark would carry that shell to every page that loads it.
     *
     * Changes that must make it fail: the store importing a shell (gate js_store_names_no_shell), or spelling a
     * mark, a service or a method of its own.
     */
    public function test_the_store_reaches_no_shell(): void {
        $store = $this->read('js/esm/src/listing/store.ts');
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $store, $imports);
        $this->assertContains('./listing', $imports[1], 'The control: the imports are read.');
        foreach ($imports[1] as $import) {
            $this->assertDoesNotMatchRegularExpression('~^\./(?:visitor|account)$~', $import, "The store imports {$import}.");
        }
        $code = $this->uncommented($store);
        foreach (['methodname', 'theme_dimensions_', "'dim-js'", "'dim-public-js'", 'fetchOne', 'loginrequired'] as $never) {
            $this->assertStringNotContainsString($never, $code, "The store spells {$never}: that is a shell's.");
        }
        $this->assertStringContainsString('document.body.classList.add(shell.mark);', $code);
        foreach (['visitor', 'account'] as $shell) {
            $source = $this->read('js/esm/src/listing/' . $shell . '.ts');
            $this->assertStringContainsString("import {current, init} from './store';", $source);
        }
    }

    /**
     * One module of js/esm/src writes the history, and it replaces the panel's address without pushing.
     *
     * The posters' component replaces its open area and never pushes, or every opening would be a step of Back;
     * two writers spelling the calls themselves is how one pushes where the other replaces.
     *
     * Changes that must make it fail: a history call spelled outside history.ts, the panel's own included (gate
     * js_history_one_writer); the panel's write pushing (gate js_history_keeps_entry); the criteria pushed whatever
     * the caller asked (gate js_history_push_flag).
     */
    public function test_only_the_history_module_writes_the_address(): void {
        $sources = $this->esm_sources();
        $this->assertContains('js/esm/src/listing/history.ts', $sources, 'The control: the tree is read.');
        foreach ($sources as $path) {
            if ($path === 'js/esm/src/listing/history.ts') {
                continue;
            }
            $this->assertDoesNotMatchRegularExpression(
                '~\bhistory\.(?:pushState|replaceState)\(~',
                $this->uncommented($this->read($path)),
                "{$path} writes the history itself instead of through listing/history.ts."
            );
        }
        $keeping = $this->body('replaceKeeping', 'js/esm/src/listing/history.ts');
        $this->assertSame("    window.history.replaceState(window.history.state, '', url);", $keeping);
        $write = $this->body('writeUrl', 'js/esm/src/listing/history.ts');
        $this->assertMatchesRegularExpression(
            "~if \(push\) \{\s*window\.history\.pushState\(\{\}, '', url\);\s*\} else \{\s*"
                . "window\.history\.replaceState\(\{\}, '', url\);\s*\}\s*syncFold\(\);~",
            $write
        );
        $panel = $this->read('js/esm/src/areas/panel.ts');
        $this->assertStringContainsString("import {replaceKeeping} from '../listing/history';", $panel);
        $this->assertStringContainsString(
            "        replaceKeeping(addressWith(window.location.href, st.open, st.psort));\n        syncHolders();",
            $panel,
            'The panel writes its open area through the history module, replacing.'
        );
    }
}
