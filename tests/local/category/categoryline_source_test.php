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
 * Theme Dimensions - Tests for the filter line's client, by its source
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the rules of amd/src/categoryline.js, the filter line's behaviour before the React component.
 *
 * Since stage R4a no shell arms the module (both draw js/esm/src/filterline/FilterLine.tsx); it stays in
 * the tree, unimported, until the clean-up of R5 removes it with these assertions.
 *
 * moodle-plugin-ci has no JavaScript test runner, so these are assertions about spelling, each scoped
 * to the function it is about, as amd_source_test's are; the Behat feature of the client is what runs
 * the module. The rules are the fleet contract's (moodle-dev/docs/filter-line/CONTRACT.md, sections 2
 * to 4 and 8): the line measured on its own box, the steps tried in order with every scope cell drawn
 * and no transition, the search growing over the line and closing back to its opener without opening
 * again, the suggestions matched accent- and case-insensitively and applied through the control they
 * name, every floating layer placed under the line's measured bottom, the bottom sheet, the rows that
 * scroll sideways, and the slash key.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categoryline_source_test extends \basic_testcase {
    /** @var string The module under test. */
    private const MODULE = 'categoryline';

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * One module's source.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function source(string $module = self::MODULE): string {
        // A name with a slash is a module of js/esm/src: the store, the visitor's client since R4b2.
        $path = str_contains($module, '/')
            ? $this->plugin_root() . '/js/esm/src/' . $module . '.ts'
            : $this->plugin_root() . '/amd/src/' . $module . '.js';
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
     * The line is measured on its own box, never the viewport's, and refolds after the frame.
     *
     * A drawer opening changes the line's width and not the window's, so a viewport query or a
     * window resize listener would leave the line at a step that no longer fits it.
     *
     * Changes that must make it fail: the observer on the window, the document or the body; the
     * width read off innerWidth; a resize listener on the window.
     */
    public function test_the_line_measures_its_own_width(): void {
        $arm = $this->body('arm');
        $this->assertMatchesRegularExpression(
            '~new ResizeObserver\(\(entries\) => \{.*?\}\)\.observe\(line\);~s',
            $arm,
            'The observer watches the line itself.'
        );
        $this->assertStringContainsString('const width = Math.round(entries[0].contentRect.width);', $arm);
        $this->assertStringContainsString('if (width !== state.width) {', $arm, 'A height change alone refolds nothing.');
        $code = $this->code();
        $this->assertStringNotContainsString('innerWidth', $code);
        $this->assertStringNotContainsString("addEventListener('resize'", $code);
        $this->assertStringNotContainsString('.observe(document', $code);
        $this->assertStringContainsString('window.requestAnimationFrame(', $this->body('refit'));
        $this->assertStringContainsString("new Pending('theme_dimensions/categoryline:fit')", $this->body('refit'));
    }

    /**
     * The steps are tried in order, measured with every cell drawn and no transition, and written as classes.
     *
     * Changes that must make it fail: the measuring class not set or not taken off; the absent cells
     * not drawn for the measure or not hidden again; a step tried out of order; the last step not
     * taken whatever it measures; the step written anywhere but as a class on the line.
     */
    public function test_the_line_folds_at_the_first_step_that_fits(): void {
        $source = $this->source();
        $this->assertStringContainsString("const STEPS = ['s1', 's2', 's3', 's4'];", $source);
        $this->assertStringContainsString(
            "const TRIES = [['s1', false], ['s2', false], ['s3', true], ['s4', false], ['s4', true]];",
            $source
        );
        $this->assertStringContainsString("step: 'dim-line--',", $source);
        $this->assertStringContainsString("measuring: 'dim-line--measuring',", $source);
        $this->assertStringContainsString("folded: 'dim-line--folded',", $source);
        $fit = $this->body('fit');
        $this->assertMatchesRegularExpression(
            '~line\.classList\.add\(CLASSES\.measuring\);.*?TRIES\.some\(.*?line\.classList\.remove\(CLASSES\.measuring\);~s',
            $fit,
            'Measured under the measuring class, taken off afterwards.'
        );
        $this->assertMatchesRegularExpression(
            "~querySelectorAll\('label\[hidden\]'\).*?label\.hidden = false;.*?TRIES\.some.*?label\.hidden = true;~s",
            $fit,
            'Every cell of the scope is drawn for the measure and hidden again after it.'
        );
        $this->assertStringContainsString('return index === TRIES.length - 1 || !overflows();', $fit);
        $set = $this->body('setStep');
        $this->assertStringContainsString(
            'STEPS.forEach((name) => line.classList.toggle(CLASSES.step + name, name === step));',
            $set
        );
        $this->assertStringContainsString('placeScope(step === TWO_LINES);', $set);
        // The scope moves in the document, so the focus order is the reading order, and keeps its focus.
        $scope = $this->body('placeScope');
        $this->assertStringContainsString('line.insertBefore(scope, parts.listbox);', $scope);
        $this->assertStringContainsString('quietly(focused);', $scope);
        $over = $this->body('overflows');
        $this->assertStringContainsString('rect.right > right || rect.left < left', $over);
        $this->assertStringContainsString('if (child === parts.listbox) {', $over, 'A layer never counts.');
    }

    /**
     * The search grows over the line at the third and fourth steps, and closes back to its opener quietly.
     *
     * Changes that must make it fail: the morph at the first two steps; the close giving the focus to
     * nothing or opening the search again; the field's focus listener without its quiet guard or
     * opening on a focus the reader did not give; Escape left to the browser while the search is open.
     */
    public function test_the_search_grows_over_the_line_and_closes_back_quietly(): void {
        $this->assertStringContainsString("const MORPH_STEPS = ['s3', 's4'];", $this->source());
        $this->assertStringContainsString("searching: 'dim-line--searching',", $this->source());
        $open = $this->body('openSearch');
        $this->assertMatchesRegularExpression(
            '~if \(morphs\(\) && !state\.searching\) \{\s*state\.searching = true;\s*state\.from = from \|\| field;'
                . '\s*line\.classList\.add\(CLASSES\.searching\);~',
            $open
        );
        $close = $this->body('closeSearch');
        $this->assertStringContainsString('line.classList.remove(CLASSES.searching);', $close);
        $this->assertStringContainsString(
            'quietly([from, field, parts.opener].find((control) => shown(control)) || null);',
            $close,
            'The focus goes back to the control that opened it, or the one drawn in its place.'
        );
        $quietly = $this->body('quietly');
        $this->assertMatchesRegularExpression(
            '~state\.quiet = true;\s*try \{\s*element\.focus\(\{preventScroll: true\}\);\s*\} finally \{\s*state\.quiet = false;~',
            $quietly
        );
        $listen = $this->body('listenSearch');
        $this->assertMatchesRegularExpression(
            "~field\.addEventListener\('focusin', \(\) => \{.*?if \(state\.quiet \|\| !intent\) \{\s*return;\s*\}\s*"
                . "openSearch\(field\);~s",
            $listen,
            'A quiet focus, or one the reader did not give, opens nothing.'
        );
        $this->assertStringContainsString('const intent = state.intent || Date.now() - state.tabat < TAB_INTENT;', $listen);
        $this->assertStringContainsString("parts.closer.addEventListener('click', () => closeSearch(true));", $listen);
        $keys = $this->body('onFieldKey');
        $this->assertMatchesRegularExpression(
            "~event\.key === 'Escape' && \(state\.open \|\| state\.searching\)\) \{.*?"
                . "event\.preventDefault\(\);\s*closeSearch\(true\);~s",
            $keys
        );
        $this->assertStringContainsString('closeSearch(true);', $this->body('onLineKey'), 'Escape from the close button too.');
    }

    /**
     * Matching is accent- and case-insensitive, word starts first, then by count; the empty field offers five.
     *
     * Changes that must make it fail: the accents kept; the case kept; the label matched alone and the
     * group never; the count ranked before the word start; the start list not ordered by count.
     */
    public function test_the_suggestions_match_by_the_normalise_rule_and_rank_word_starts_first(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const fold = (text) => String(text).normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase();",
            $source
        );
        $this->assertStringContainsString("export const normalise = (text) => fold(text || '').trim();", $source);
        $this->assertStringContainsString('const MIN_TERM = 2;', $source);
        $this->assertStringContainsString('const START_COUNT = 5;', $source);
        $rank = $this->body('rankOf');
        $this->assertStringContainsString('const label = matchOf(normalise(item.label), term);', $rank);
        $this->assertStringContainsString('const group = matchOf(normalise(item.group), term);', $rank);
        $this->assertLessThan(strpos($rank, 'if (group === 0)'), strpos($rank, 'if (label === 0)'), 'The label first.');
        $this->assertStringContainsString("if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {", $this->body('matchOf'));
        $suggest = $this->body('suggest');
        $this->assertStringContainsString('const term = normalise(text);', $suggest);
        $this->assertStringContainsString(
            '.sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);',
            $suggest,
            'With less than the floor, the highest counts.'
        );
        $this->assertStringContainsString(
            '.sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order)',
            $suggest,
            'Word starts first, then the count.'
        );
        // What may be suggested: the filters already on the page, never the one in force or a dead one.
        $filters = $this->body('filters');
        $this->assertStringContainsString("input.checked || input.value === '' || input.value === '0'", $filters);
        $this->assertStringContainsString(
            'const known = rest.get(input) || {count: countOf(label), hidden: input.hidden || label.hidden};',
            $filters,
            'The counts and the hidden options are the last answer\'s without a typed term.'
        );
        $remember = $this->body('remember');
        $this->assertMatchesRegularExpression("~^\s*if \(normalise\(field\.value\) !== ''\) \{\s*return;~", $remember);
        $this->assertStringContainsString('remember();', $this->body('repaint'));
        $this->assertStringContainsString("if (label !== '' && count !== 0) {", $filters);
        $this->assertStringContainsString("star.getAttribute('aria-pressed') !== 'true'", $filters);
        $this->assertStringContainsString("date.querySelectorAll(SELECTORS.quick)", $filters);
        // The headings are the server's, written on the listbox: the browser composes no word.
        $this->assertStringContainsString(
            '(typed ? listbox.dataset.labelFilters : listbox.dataset.labelStart)',
            $this->body('render')
        );
        $this->assertStringNotContainsString('getString', $this->code());
    }

    /**
     * A suggestion applies its filter by pressing the control it names, the term dropped first.
     *
     * The client's own handler of that control makes the one request; the client's dropTerm takes the
     * term out of its state before the press, so that request carries none, and the field is emptied
     * and the search stays open. Since R4a both clients hand their line to the React component instead
     * (filterline_component_source_test holds that half), and the visitor's still drops the term first.
     *
     * Changes that must make it fail: the term dropped after the press (a request with the term, then
     * one without); a request of the line's own; the search closed after a pick; the visitor's client not
     * handing its line over or not telling it the answer.
     */
    public function test_a_suggestion_presses_its_control_after_the_term_is_dropped(): void {
        $choose = $this->body('choose');
        $this->assertMatchesRegularExpression(
            "~field\.value = '';\s*hooks\.dropTerm\(\);\s*item\.control\.click\(\);\s*render\(\);~",
            $choose
        );
        $code = $this->code();
        $this->assertStringNotContainsString('core/ajax', $code, 'The line makes no request of its own.');
        $this->assertStringNotContainsString('methodname', $code);
        /* Since R4a no shell arms this module: the visitor's client (the store since R4b2) hands its line to
           the component, as the logged-in client does (filterline_component_source_test), and still drops the
           term first. */
        foreach (['listing/store' => 'Listing.paintEraser(root);'] as $client => $eraser) {
            $source = $this->source($client);
            $this->assertStringNotContainsString('theme_dimensions/categoryline', $source);
            $this->assertStringContainsString('Listing.mountLine(root, dropTerm);', $source);
            $this->assertStringContainsString('Listing.tellAnswer(root, response);', $this->body('applyResponse', $client));
            $drop = $this->body('dropTerm', $client);
            $this->assertMatchesRegularExpression("~cancelSearch\(\);\s*state\.query = '';~", $drop, $client . ' drops the term.');
            $this->assertStringContainsString($eraser, $drop);
            $this->assertStringContainsString('const search = Listing.focusTarget(root);', $this->body('focusSearch', $client));
            foreach (['onClear', 'onChipRemove'] as $handler) {
                $this->assertStringContainsString(
                    "\n    focusSearch();",
                    $this->body($handler, $client),
                    "{$client}'s {$handler} gives the focus to the field or, folded, to its icon."
                );
            }
        }
        // The listbox keeps the focus in the field, which is the combobox.
        $listen = $this->body('listenSearch');
        $this->assertStringContainsString(
            "parts.listbox.addEventListener('mousedown', (event) => event.preventDefault());",
            $listen
        );
        $keys = $this->body('onFieldKey');
        $this->assertStringContainsString("(event.key === 'Enter' || event.key === ' ') && state.active >= 0", $keys);
        $arm = $this->body('arm');
        foreach (
            [
                "parts.listbox.setAttribute('role', 'listbox');",
                "field.setAttribute('role', 'combobox');",
                "field.setAttribute('aria-autocomplete', 'list');",
                "field.setAttribute('aria-controls', parts.listbox.id);",
            ] as $line
        ) {
            $this->assertStringContainsString($line, $arm);
        }
        $this->assertStringContainsString(
            "field.setAttribute('aria-activedescendant', options[state.active].id);",
            $this->body('activate')
        );
    }

    /**
     * Every floating layer is placed under the line's real bottom, measured; on the two-line step the panel is a sheet.
     *
     * Changes that must make it fail: a fixed offset; the top measured from the line's top; the
     * sheet given a top; the sheet's class not written while it is open at the two-line step.
     */
    public function test_every_layer_is_placed_under_the_lines_measured_bottom(): void {
        $bottom = $this->body('lineBottom');
        $this->assertStringContainsString('bottom = Math.max(bottom, rect.bottom);', $bottom);
        $place = $this->body('place');
        $this->assertStringContainsString("layer.style.top = Math.round(lineBottom() - top + GAP) + 'px';", $place);
        $this->assertStringContainsString('const top = parent.getBoundingClientRect().top + parent.clientTop;', $place);
        $this->assertMatchesRegularExpression(
            "~if \(layer === parts\.panel && state\.step === TWO_LINES\) \{\s*layer\.style\.top = '';~",
            $place,
            'The sheet is bottom-anchored and takes no top.'
        );
        $this->assertStringContainsString('place(listbox);', $this->body('placeList'));
        $fit = $this->body('fit');
        $this->assertStringContainsString('place(parts.panel);', $fit);
        $this->assertStringContainsString('place(parts.menu);', $fit);
        $layers = $this->body('listenLayers');
        $this->assertStringContainsString(
            'form.classList.toggle(CLASSES.sheet, state.step === TWO_LINES && parts.filters.open);',
            $layers
        );
        $this->assertStringContainsString("sheet: 'dim-toolbar--sheet',", $this->source());
        $this->assertStringContainsString('}, true);', $layers, 'The toggle event is heard in the capture phase.');
    }

    /**
     * A half-hidden item of a row that scrolls sideways is revealed when pressed or focused, and a hidden side fades.
     *
     * Changes that must make it fail: the reveal on pointerdown (the press would land on the next item);
     * the reveal missing on focus; the fades not following the scroll; the chips row left out.
     */
    public function test_the_rows_that_scroll_reveal_and_fade(): void {
        $rows = $this->body('listenRows');
        $this->assertStringContainsString('[parts.scope, parts.chips].filter(Boolean).forEach((scroller) => {', $rows);
        $this->assertStringContainsString("scroller.addEventListener('scroll', () => fades(scroller), {passive: true});", $rows);
        foreach (['focusin', 'click'] as $event) {
            $this->assertStringContainsString(
                "scroller.addEventListener('{$event}', (event) => reveal(scroller, itemOf(event.target)));",
                $rows
            );
        }
        $this->assertStringNotContainsString('pointerdown', $rows);
        $reveal = $this->body('reveal');
        $this->assertStringContainsString("scroller.scrollBy({left: dx, behavior: still() ? 'auto' : 'smooth'});", $reveal);
        $this->assertStringContainsString('const REVEAL_PAD = 16;', $this->source());
        $fades = $this->body('fades');
        $this->assertStringContainsString('scroller.classList.toggle(CLASSES.fadeleft, max > 1 && left > 2);', $fades);
        $this->assertStringContainsString('scroller.classList.toggle(CLASSES.faderight, max > 1 && right > 2);', $fades);
        $item = $this->body('itemOf');
        $this->assertMatchesRegularExpression(
            "~if \\(target\\.matches\\('input\\[type=\"radio\"\\]'\\)\\) \\{\\s*"
                . "return form\\.querySelector\\('label\\[for=\"' \\+ target\\.id~",
            $item,
            'A radio is revealed by its label, which is what the eye sees of it.'
        );
    }

    /**
     * The slash key opens the search while the line is on screen and the focus is in no field.
     *
     * Changes that must make it fail: the key taken inside a field; taken while the line is off screen;
     * the field opened directly where it is folded into its icon.
     */
    public function test_the_slash_key_opens_the_search(): void {
        $layers = $this->body('listenLayers');
        $this->assertStringContainsString(
            "event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !event.defaultPrevented",
            $layers
        );
        $slash = $this->body('onSlash');
        $this->assertStringContainsString("active.closest('input, textarea, select')", $slash);
        $this->assertStringContainsString('if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {', $slash);
        $this->assertStringContainsString('openSearch(state.folded && !state.searching ? parts.opener : field);', $slash);
    }

    /**
     * The committed build was made from the committed source, and the module names nothing of a session.
     */
    public function test_the_build_is_current_and_reaches_nothing_of_a_session(): void {
        $map = $this->plugin_root() . '/amd/build/' . self::MODULE . '.min.js.map';
        $this->assertFileExists($map);
        $decoded = json_decode((string) file_get_contents($map), true);
        $this->assertSame(
            $this->source(),
            (string) ($decoded['sourcesContent'][0] ?? ''),
            'amd/build/categoryline is not this source: rebuild it with grunt amd and commit the build.'
        );
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $this->source(), $imports);
        $this->assertSame(['core/pending'], $imports[1], 'It imports core\'s pending tracker with the page, and nothing else.');
        preg_match_all("~\\bimport\\('([^']+)'\\)~", $this->source(), $dynamic);
        $this->assertSame(
            ['theme_dimensions/categorypreview'],
            $dynamic[1],
            'The preview is imported on first use, and nothing else is.'
        );
    }

    /**
     * The line arms the preview over its form, previews the active suggestion's control and refreshes after an answer.
     *
     * The active suggestion is the keyboard's focus on the control it names (the focus itself stays in the field,
     * the combobox), so walking the list with the arrows previews each filter in turn, and taking the list down or
     * rebuilding it under a typed text takes the preview its option gave. A pointer on an option is the preview
     * module's own listener, told the option's control by the hook armed here.
     *
     * Changes that must make it fail: the preview not armed; the active option not previewed; the list's preview
     * left on the cards once the list goes; no refresh after a repaint, which leaves a mark on cards the answer
     * replaced.
     */
    public function test_the_line_previews_the_active_suggestion_and_refreshes_after_an_answer(): void {
        $this->assertMatchesRegularExpression(
            '~Module\.arm\(listing, form, \{\s*controlOf: \(option\) => \{\s*'
                . 'const item = state\.items\[Number\(option\.dataset\.index\)\];\s*return item \? item\.control : null;~',
            $this->body('loadPreview')
        );
        $this->assertStringContainsString(
            'Preview.show(state.active >= 0 ? state.items[state.active].control : null);',
            $this->body('activate')
        );
        $this->assertMatchesRegularExpression('~if \(state\.active >= 0\) \{\s*Preview\.clear\(\);~', $this->body('closeList'));
        $this->assertMatchesRegularExpression(
            '~if \(state\.active >= 0\) \{\s*// [^\n]*\n\s*Preview\.clear\(\);~',
            $this->body('render')
        );
        $this->assertMatchesRegularExpression('~render\(\);\s*Preview\.refresh\(\);~', $this->body('repaint'));
    }

    /**
     * The preview is imported on first use: the first pointer or focus on the toolbar, or a suggestion walked to.
     *
     * Changes that must make it fail: the preview armed with the page (it would load for every reader again); the
     * first-use listeners left on once it is armed; the event that loaded it not given again, so the first filter
     * pointed at previews nothing; a replay under a pointer that has already left; nothing pending while it loads.
     */
    public function test_the_preview_is_imported_on_first_use(): void {
        $arm = $this->body('arm');
        $this->assertStringContainsString("form.addEventListener('pointerover', onPreviewIntent, true);", $arm);
        $this->assertStringContainsString("form.addEventListener('focusin', onPreviewIntent, true);", $arm);
        $this->assertStringNotContainsString('.arm(', $arm, 'The preview is not armed with the page.');

        $load = $this->body('loadPreview');
        $this->assertStringContainsString("new Pending('theme_dimensions/categoryline:preview')", $load);
        $this->assertStringContainsString("import('theme_dimensions/categorypreview')", $load);
        $this->assertStringContainsString("form.removeEventListener('pointerover', onPreviewIntent, true);", $load);
        $this->assertStringContainsString("form.removeEventListener('focusin', onPreviewIntent, true);", $load);
        $this->assertStringContainsString('.finally(() => pending.resolve());', $load);

        $intent = $this->body('onPreviewIntent');
        $this->assertStringContainsString("event.type === 'pointerover' && target.matches(':hover')", $intent);
        $this->assertStringContainsString("event.type === 'focusin' && document.activeElement === target", $intent);
        $this->assertStringContainsString("new PointerEvent('pointerover', {", $intent);
    }
}
