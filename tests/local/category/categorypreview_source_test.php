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
 * Theme Dimensions - Tests for the filter line's preview, by its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the rules of amd/src/categorypreview.js, the filter line's preview (contract section 6).
 *
 * moodle-plugin-ci has no JavaScript test runner, so these are assertions about spelling, each scoped to the
 * function it is about, as categoryline_source_test's are; the client's Behat feature runs the module. The
 * rules: a mouse or a pen and the keyboard's focus only, never a finger; the cards' own facts and the form's
 * criteria and nothing asked of the server; a card that does not state a fact makes the preview stand down;
 * every mark taken off on leave and on blur; no motion of its own and no opacity.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categorypreview_source_test extends \basic_testcase {
    /** @var string The module under test. */
    private const MODULE = 'categorypreview';

    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = dirname(__DIR__, 3) . '/amd/src/' . self::MODULE . '.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The module's source without its comments: what runs.
     *
     * @return string
     */
    private function code(): string {
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $this->source());
        return (string) preg_replace('~^\s*//.*$~m', '', $code);
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source(), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in " . self::MODULE . '.');
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * Only a mouse or a pen previews, on both of its pointer listeners; a finger never does.
     *
     * A touch pointer brackets every tap with an over and an out, so an unguarded over would light the cards
     * under the finger that is pressing the filter, and an unguarded out would take a keyboard's preview away.
     *
     * Changes that must make it fail: the guard answering true for every pointer; the over or the out listener
     * dropping it; the listeners moved to mouse events, which a touch emulates.
     */
    public function test_only_a_mouse_or_a_pen_previews(): void {
        $this->assertMatchesRegularExpression(
            "~^const pointing = \\(event\\) => event\\.pointerType === 'mouse' \\|\\| event\\.pointerType === 'pen';$~m",
            $this->source()
        );
        foreach (['onOver', 'onOut'] as $name) {
            $this->assertMatchesRegularExpression(
                '~^    if \(!pointing\(event\)\) \{\s*return;\s*\}~',
                $this->body($name),
                "{$name} starts by refusing a finger."
            );
        }
        $arm = $this->body('arm');
        $this->assertStringContainsString("form.addEventListener('pointerover', onOver);", $arm);
        $this->assertStringContainsString("form.addEventListener('pointerout', onOut);", $arm);
        $this->assertStringNotContainsString('mouseover', $this->code());
        $this->assertStringNotContainsString('mouseenter', $this->code());
        $this->assertStringNotContainsString('touchstart', $this->code());
    }

    /**
     * The keyboard's focus previews too, and a focus a press gave does not.
     *
     * A click on a pill's label focuses its radio and applies the filter in the same press; a preview there
     * would light the cards the answer is about to replace, and stay lit on the filter now in force.
     *
     * Changes that must make it fail: the focus listener not armed; the focus-visible test dropped.
     */
    public function test_the_keyboards_focus_previews_and_a_press_does_not(): void {
        $arm = $this->body('arm');
        $this->assertStringContainsString("form.addEventListener('focusin', onFocus);", $arm);
        $this->assertStringContainsString("form.addEventListener('focusout', onBlur);", $arm);
        $this->assertStringContainsString("if (control && event.target.matches(':focus-visible')) {", $this->body('onFocus'));
        $this->assertStringContainsString('show(control);', $this->body('onFocus'));
    }

    /**
     * Every mark comes off on leave, on blur and on a press, and a repaint previews again or nothing.
     *
     * Changes that must make it fail: the out listener leaving the marks on a pointer that left the form; the
     * blur doing the same for a focus; clear() forgetting the listing's mark or a card's; a press leaving them.
     */
    public function test_the_marks_come_off_on_leave_on_blur_and_on_a_press(): void {
        $clear = $this->body('clear');
        $this->assertStringContainsString('current = null;', $clear);
        $this->assertStringContainsString('root.classList.remove(CLASSES.previewing);', $clear);
        $this->assertStringContainsString(
            "root.querySelectorAll('.' + CLASSES.lit + ', .' + CLASSES.dim).forEach((card) => {",
            $clear
        );
        $this->assertStringContainsString('card.classList.remove(CLASSES.lit, CLASSES.dim);', $clear);
        foreach (['onOut', 'onBlur'] as $name) {
            $this->assertMatchesRegularExpression(
                '~if \(!next \|\| !form\.contains\(next\)\) \{\s*clear\(\);\s*\}~',
                $this->body($name),
                "{$name} clears once it leaves the form."
            );
        }
        $this->assertMatchesRegularExpression(
            '~if \(control\) \{\s*show\(control\);\s*\} else \{\s*clear\(\);\s*\}~',
            $this->body('onOver')
        );
        $arm = $this->body('arm');
        $this->assertStringContainsString("form.addEventListener('change', clear);", $arm);
        $this->assertMatchesRegularExpression(
            "~form\\.addEventListener\\('click', \\(event\\) => \\{\\s*if \\(controlOf\\(event\\.target\\)\\) \\{\\s*clear\\(\\);~",
            $arm
        );
        $this->assertMatchesRegularExpression('~if \(current\) \{\s*show\(current\);\s*\}~', $this->body('refresh'));
    }

    /**
     * A card that does not state the fact makes the whole preview stand down; the rest light or dim.
     *
     * A visitor's card states no favourite and only the fields a visitor may see: lighting what is known and
     * dimming the rest would tell the reader that a card goes when the page cannot know.
     *
     * Changes that must make it fail: an unknown verdict read as "goes" or as "stays"; the classes written
     * without the listing's mark; a kept card not lit.
     */
    public function test_an_unknown_fact_stands_the_preview_down(): void {
        $show = $this->body('show');
        $this->assertStringContainsString('const verdicts = cards.map((card) => keeps(factsOf(card), criterion, force));', $show);
        $this->assertMatchesRegularExpression(
            '~if \(verdicts\.indexOf\(null\) >= 0\) \{\s*// [^\n]*\n\s*clear\(\);\s*return;\s*\}~',
            $show
        );
        $this->assertStringContainsString('root.classList.add(CLASSES.previewing);', $show);
        $this->assertStringContainsString('card.classList.toggle(CLASSES.lit, verdicts[index]);', $show);
        $this->assertStringContainsString('card.classList.toggle(CLASSES.dim, !verdicts[index]);', $show);
        $keeps = $this->body('keeps');
        $this->assertMatchesRegularExpression('~if \(needed === null \|\| needed === undefined\) \{\s*return null;\s*\}~', $keeps);
        $this->assertStringContainsString(
            "start: card.hasAttribute('data-start') ? card.dataset.start : null,",
            $this->body('factsOf')
        );
        $this->assertStringContainsString(
            "fav: star ? star.getAttribute('aria-pressed') === 'true' : null,",
            $this->body('factsOf'),
            'The favourite is the card\'s own star, which the star module keeps live.'
        );
    }

    /**
     * A control that would narrow nothing previews nothing.
     *
     * Changes that must make it fail: a filter in force previewed (its cards all lit, for good, once applied); an
     * "All" or a field's "Any" previewed.
     */
    public function test_a_control_that_narrows_nothing_previews_nothing(): void {
        $criterion = $this->body('criterionOf');
        $this->assertStringContainsString(
            "return control.getAttribute('aria-pressed') === 'true' ? null : {kind: 'fav'};",
            $criterion
        );
        $this->assertStringContainsString("return control.hasAttribute('aria-current') ? null", $criterion);
        $this->assertStringContainsString('if (!control.matches(SELECTORS.radio) || control.checked) {', $criterion);
        $this->assertStringContainsString("return control.value === '' ? null : {kind: 'chip', value: control.value};", $criterion);
        $this->assertStringContainsString("control.value !== '0'", $criterion);
    }

    /**
     * It asks the server nothing, imports nothing, animates nothing and never touches opacity.
     *
     * The visitor's page loads it through the filter line, so anything it imported would ship to the internet;
     * the motion is the stylesheet's, under a no-preference query, so reduced motion is instant.
     *
     * Changes that must make it fail: any import; a call to core/ajax or fetch; a timer or an animation frame; a
     * style or an opacity written from here.
     */
    public function test_it_asks_nothing_imports_nothing_and_animates_nothing(): void {
        $code = $this->code();
        $this->assertDoesNotMatchRegularExpression('~^import ~m', $this->source(), 'It imports nothing.');
        $forbidden = [
            'Ajax',
            'fetch(',
            'XMLHttpRequest',
            'sendBeacon',
            'setTimeout',
            'requestAnimationFrame',
            '.animate(',
            '.style',
            'opacity',
            'transition',
        ];
        foreach ($forbidden as $word) {
            $this->assertStringNotContainsString($word, $code, "It never uses {$word}.");
        }
    }

    /**
     * The committed build was made from the committed source, and the line's build loads it.
     */
    public function test_the_build_is_current_and_the_line_loads_it(): void {
        $root = dirname(__DIR__, 3);
        $map = json_decode((string) file_get_contents($root . '/amd/build/' . self::MODULE . '.min.js.map'), true);
        $this->assertSame(
            $this->source(),
            (string) ($map['sourcesContent'][0] ?? ''),
            'amd/build/categorypreview is not this source: rebuild it with grunt amd and commit the build.'
        );
        $this->assertStringContainsString(
            'theme_dimensions/categorypreview',
            (string) file_get_contents($root . '/amd/build/categoryline.min.js')
        );
    }
}
