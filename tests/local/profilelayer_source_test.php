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
 * Theme Dimensions - Tests for the invariants of the profile layer's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/profilelayer.js, and of the two modules that work with it, by reading their source.
 *
 * No unit test runs the AMD modules, so the properties whose loss costs a reader are pinned by spelling, each
 * assertion scoped to the one function it is about: the user button is taken over only once the layer is found,
 * Escape closes a sub-view before the layer, the focus goes back to the user button and to the row that opened a
 * sub-view, a Pending token covers the opening motion, the body class that hides the navbar's colour mode menu is
 * written only where the layer carries the control, and every hook the module addresses is in the template. The
 * Behat feature theme_dimensions_profilelayer.feature is what runs the module. The build-currency assertions at the
 * foot are the only ones about what a browser is served.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class profilelayer_source_test extends \basic_testcase {
    /**
     * A module's source.
     *
     * @param string $name The module's name under amd/src, without the extension.
     * @return string
     */
    private function source(string $name = 'profilelayer'): string {
        $path = dirname(__DIR__, 2) . '/amd/src/' . $name . '.js';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The body of one top-level arrow function of a module.
     *
     * Every function of these files is a top-level `const <name> = (…) => {` (or `export const`) closed by a `};` in
     * the first column, which is what makes the extraction unambiguous.
     *
     * @param string $name The function's name.
     * @param string $module The module's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = 'profilelayer'): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source($module), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");

        return $matches[1];
    }

    /**
     * The value of one entry of a module's SELECTORS map.
     *
     * @param string $key The entry's key.
     * @return string The selector, as the module spells it.
     */
    private function selector(string $key): string {
        $found = preg_match("~\n    {$key}: '([^']+)',~", $this->source(), $matches);
        $this->assertSame(1, $found, "No selector {$key} in the module.");

        return $matches[1];
    }

    /**
     * The extractor returns one function and not the file around it.
     *
     * @return void
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $open = $this->body('open');
        $this->assertStringContainsString('trapFocus(state.layer);', $open);
        $this->assertStringNotContainsString('untrapFocus', $open, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('untrapFocus();', $this->body('close'));
    }

    /**
     * Every hook the module addresses is one the layer's template writes, so a renamed hook reddens here.
     *
     * @return void
     */
    public function test_every_hook_it_addresses_is_in_the_template(): void {
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/profile_layer.mustache');

        foreach (['LAYER', 'BACKDROP', 'MAIN', 'OPEN', 'BACK', 'CLOSE', 'MODE'] as $key) {
            $hook = trim($this->selector($key), '[]');
            $this->assertStringContainsString($hook, $template, "The template writes the hook {$key}: {$hook}.");
        }
        $this->assertStringContainsString('data-view="{{key}}"', $template, 'Each sub-view carries data-view.');
        $this->assertStringContainsString('aria-controls="{{uniqid}}-view-{{subview}}"', $template, 'A row names its view.');
        $this->assertStringContainsString('id="{{uniqid}}-view-{{key}}"', $template, 'A view carries the id rows name.');
        $this->assertSame(
            2,
            preg_match_all('~<h2 class="dimensions-layer-(?:sub)?title" id="~', $template),
            'The main view and each sub-view are labelled by an h2 with an id: the dialog follows it.'
        );
        $this->assertStringContainsString('role="radiogroup"', $template);
        $this->assertStringContainsString('data-region="colourmode-menu"', $template);
        $this->assertStringContainsString('role="menuitemradio"', $template);
        $this->assertStringContainsString('role="menuitem"', $template);
        $this->assertSame("'#user-menu-toggle'", "'" . $this->selector('TOGGLE') . "'");
        $this->assertStringContainsString("TITLE: 'h2[id]',", $this->source());
    }

    /**
     * The user button is Boost Union's dropdown toggle until the module has found the layer, its backdrop and the
     * button; then it is taken over once, and nothing else in the module touches Bootstrap's attribute.
     *
     * Changes that must make it fail: taking over before the guard, dropping a clause of the guard, or leaving
     * Bootstrap's toggle in place.
     *
     * @return void
     */
    public function test_the_button_is_taken_over_only_once_armed(): void {
        $init = $this->body('init');
        $guard = "if (!layer || !backdrop || !toggle || layer.classList.contains(CLASSES.ARMED)) {\n        return;\n    }";
        $this->assertStringContainsString($guard, $init);
        $this->assertSame(1, substr_count($this->source(), 'takeOver(toggle, layer);'), 'Taken over in one place.');
        $this->assertGreaterThan(strpos($init, $guard), strpos($init, 'takeOver(toggle, layer);'), 'After the guard.');
        $this->assertGreaterThan(
            strpos($init, 'takeOver(toggle, layer);'),
            strpos($init, 'layer.classList.add(CLASSES.ARMED);'),
            'Marked armed once the button is the layer\'s.'
        );

        $take = $this->body('takeOver');
        $this->assertStringContainsString("toggle.removeAttribute('data-bs-toggle');", $take);
        $this->assertStringContainsString("toggle.setAttribute('aria-haspopup', 'dialog');", $take);
        $this->assertStringContainsString("toggle.setAttribute('aria-controls', layer.id);", $take);
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded', 'false');", $take);
        $this->assertStringContainsString("toggle.addEventListener('click', onToggle);", $take);
        $this->assertSame(
            1,
            substr_count($this->source(), "'data-bs-toggle'"),
            'Bootstrap\'s attribute is removed, never added.'
        );
    }

    /**
     * Opening shows the main view, holds the focus in the layer and puts it on the main view's close button; closing
     * releases the focus before it gives it back to the user button.
     *
     * @return void
     */
    public function test_the_focus_is_held_and_given_back_to_the_user_button(): void {
        $open = $this->body('open');
        $this->assertStringContainsString('state.layer.hidden = false;', $open);
        $this->assertStringContainsString("state.toggle.setAttribute('aria-expanded', 'true');", $open);
        $this->assertStringContainsString('main.querySelector(SELECTORS.CLOSE)?.focus();', $open);
        $this->assertGreaterThan(
            strpos($open, 'trapFocus(state.layer);'),
            strpos($open, 'main.querySelector(SELECTORS.CLOSE)'),
            'The focus is held, then put on the close button.'
        );

        $close = $this->body('close');
        $this->assertStringContainsString("state.toggle.setAttribute('aria-expanded', 'false');", $close);
        $this->assertMatchesRegularExpression('~if \(returnFocus\) \{\s*state\.toggle\.focus\(\);\s*\}~', $close);
        $this->assertGreaterThan(strpos($close, 'untrapFocus();'), strpos($close, 'state.toggle.focus();'), 'Released first.');
        $this->assertStringContainsString('const close = (returnFocus = true) => {', $this->source());
    }

    /**
     * A sub-view takes the focus to its back button; leaving it gives the focus back to the row that opened it.
     *
     * @return void
     */
    public function test_the_focus_follows_the_sub_views_both_ways(): void {
        $enter = $this->body('enter');
        $this->assertStringContainsString("document.getElementById(row.getAttribute('aria-controls'))", $enter);
        $this->assertStringContainsString('state.opener = row;', $enter);
        $this->assertStringContainsString('view.querySelector(SELECTORS.BACK)?.focus();', $enter);

        $leave = $this->body('leave');
        $this->assertStringContainsString('showView(state.layer.querySelector(SELECTORS.MAIN));', $leave);
        $this->assertStringContainsString('opener.focus();', $leave);

        $show = $this->body('showView');
        $this->assertStringContainsString('candidate.hidden = candidate !== view;', $show);
        $this->assertStringContainsString("state.layer.setAttribute('aria-labelledby', title.id);", $show);
    }

    /**
     * Escape leaves an open sub-view first and closes the layer only from its main view, and is not handed on.
     *
     * @return void
     */
    public function test_escape_closes_a_sub_view_before_the_layer(): void {
        $key = $this->body('onKey');

        $this->assertMatchesRegularExpression(
            "~if \(event\.key === 'Escape'\) \{\s*event\.preventDefault\(\);\s*event\.stopPropagation\(\);\s*"
                . "if \(current\(\) !== state\.layer\.querySelector\(SELECTORS\.MAIN\)\) \{\s*leave\(\);\s*\} else \{\s*"
                . "close\(\);\s*\}~",
            $key
        );
    }

    /**
     * The close buttons, the backdrop and the sub-view's back button answer a press; a Look option is left alone.
     *
     * @return void
     */
    public function test_the_presses_close_go_back_and_leave_the_look_alone(): void {
        $click = $this->body('onClick');
        foreach (['CLOSE' => 'close', 'BACK' => 'leave'] as $key => $answer) {
            $this->assertMatchesRegularExpression(
                '~if \(event\.target\.closest\(SELECTORS\.' . $key . '\)\) \{\s*event\.preventDefault\(\);\s*'
                    . $answer . '\(\);~',
                $click
            );
        }
        $this->assertStringContainsString('enter(row);', $click);
        $this->assertStringNotContainsString('dimensions-flavour', $click, 'theme_dimensions/flavourchoice answers a Look.');
        $this->assertStringContainsString("backdrop.addEventListener('click', () => close());", $this->body('init'));
    }

    /**
     * The opening motion is covered by a Pending token, resolved when the animation ends, at once when there is none,
     * and after a bound in any case.
     *
     * @return void
     */
    public function test_a_pending_token_covers_the_opening(): void {
        $settle = $this->body('settle');
        $this->assertStringContainsString("const pending = new Pending('theme_dimensions/profilelayer:open');", $settle);
        $this->assertMatchesRegularExpression(
            "~if \(window\.getComputedStyle\(layer\)\.animationName === 'none'\) \{\s*pending\.resolve\(\);\s*return;~",
            $settle
        );
        $this->assertStringContainsString("layer.addEventListener('animationend', finish, {once: true});", $settle);
        $this->assertStringContainsString('window.setTimeout(finish, SETTLE);', $settle);
        $this->assertSame(2, substr_count($settle, 'pending.resolve();'));
        $this->assertStringContainsString('settle(state.layer);', $this->body('open'));
    }

    /**
     * Core's colour mode module is initialised, and the navbar's menu hidden by the body class, only where the
     * layer carries the control; the class is the one scss/post.scss keys the rule on.
     *
     * @return void
     */
    public function test_the_colour_mode_is_cores_and_the_twin_class_follows_the_control(): void {
        $init = $this->body('init');
        $this->assertMatchesRegularExpression(
            '~const modes = layer\.querySelector\(SELECTORS\.MODES\);\s*if \(modes\) \{\s*initColourMode\(\);'
                . '.*?document\.body\.classList\.add\(CLASSES\.MODES\);\s*\}~s',
            $init
        );
        $this->assertSame(1, substr_count($this->source(), 'classList.add(CLASSES.MODES)'), 'Written in that branch alone.');
        $this->assertStringContainsString("MODES: 'dimensions-profilelayer-modes',", $this->source());
        $this->assertStringContainsString("import {init as initColourMode} from 'theme_boost/colourmode';", $this->source());

        $post = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        $this->assertStringContainsString("\nbody.dimensions-profilelayer-modes #usernavigation {", $post);
    }

    /**
     * The phone's entries follow the layer whichever module arms first, and the nav is marked only once they are in
     * the menu or the layer; both modules spell the layer's class and events alike.
     *
     * @return void
     */
    public function test_the_phone_entries_follow_the_armed_layer(): void {
        $phone = $this->source('phonemenu');
        $this->assertStringContainsString("ARMED: 'dimensions-layer-js',", $phone);
        $this->assertStringContainsString("ARMED: 'dimensions-layer-js',", $this->source());
        $this->assertStringContainsString("ARMEDEVENT: 'theme_dimensions/profilelayer:armed',", $phone);
        $this->assertStringContainsString("ARMED: 'theme_dimensions/profilelayer:armed',", $this->source());
        $this->assertStringContainsString("SHOWNEVENT: 'theme_dimensions/profilelayer:shown',", $phone);
        $this->assertStringContainsString("SHOWN: 'theme_dimensions/profilelayer:shown',", $this->source());

        $init = $this->body('init', 'phonemenu');
        $this->assertStringContainsString(
            'if (!layer || !layer.classList.contains(LAYER.ARMED) || !intoLayer(layer, entries)) {',
            $init
        );
        $this->assertStringContainsString(
            'layer.addEventListener(LAYER.ARMEDEVENT, () => intoLayer(layer, entries), {once: true});',
            $init
        );
        $this->assertStringContainsString('group.hidden = false;', $this->body('intoLayer', 'phonemenu'));
        $this->assertStringContainsString(
            'layer.dispatchEvent(new CustomEvent(EVENTS.ARMED, {bubbles: true}));',
            $this->body('init')
        );

        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/profile_layer.mustache');
        $this->assertMatchesRegularExpression(
            '~<div class="dimensions-layer-phone" data-region="dimensions-layer-phone" hidden>\s*'
                . '<ul class="dimensions-layer-list" data-region="dimensions-layer-phone-list"></ul>~',
            $template,
            'The group is printed empty and hidden.'
        );
    }

    /**
     * The Look in the layer is chosen by flavourchoice, which leaves the checked option alone as it does the current
     * item of the user menu.
     *
     * @return void
     */
    public function test_flavourchoice_leaves_the_checked_look_alone(): void {
        $this->assertStringContainsString(
            "if (item.getAttribute('aria-current') === 'true' || item.getAttribute('aria-checked') === 'true') {",
            $this->source('flavourchoice')
        );
    }

    /**
     * The committed builds were made from the committed sources: Moodle serves amd/build, never amd/src.
     *
     * @return void
     */
    public function test_the_committed_builds_were_made_from_the_committed_sources(): void {
        $root = dirname(__DIR__, 2);
        foreach (['profilelayer', 'phonemenu', 'flavourchoice'] as $module) {
            $this->assertFileExists($root . "/amd/build/{$module}.min.js", "The build of {$module} is missing.");
            $map = json_decode((string) file_get_contents($root . "/amd/build/{$module}.min.js.map"), true);
            $this->assertIsArray($map, "The source map of {$module} is not readable JSON.");
            $this->assertSame($this->source($module), (string) ($map['sourcesContent'][0] ?? ''), "{$module} is stale.");
        }
    }
}
