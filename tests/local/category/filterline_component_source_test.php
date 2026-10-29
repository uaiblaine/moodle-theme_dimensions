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
 * Theme Dimensions - Tests for how the logged-in shell hands its filter line to the component
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the AMD half of stage R2 (docs/vitrine-react/PLAN.md) by reading the source.
 *
 * The component's behaviour is Jest's (js/esm/tests/filterline). What Jest cannot see is how the listing
 * hands the line over and keeps out of it: the mount is the server's line itself, named for core's
 * react_autoinit and put back where it stands; the component draws the line's own controls and adopts
 * three frames of server markup (the Filters panel, the Organise menu, the managers' menu); and no
 * painter of the shared listing writes into what the component draws - it tells the component through
 * events on the line, spelled alike on both sides. Since R4a both shells hand their line over, the component
 * named once in the shared listing (the visitor's import walk, which reaches it, is categorypublic_source_test's).
 * And the Jest suites run over a copy of the template's markup, which is held equal to the template here.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class filterline_component_source_test extends \advanced_testcase {
    /** @var string The component's specifier, resolved by core's import map to js/esm/build/filterline/FilterLine.js. */
    private const SPECIFIER = '@moodle/lms/theme_dimensions/filterline/FilterLine';

    /** @var string[] The painters of the shared listing that write into the line, each guarded. */
    private const PAINTERS = [
        'paintView',
        'paintFavToggle',
        'paintEraser',
        'paintControls',
        'paintFavourites',
        'paintFilterBadge',
        'paintGroups',
    ];

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
     * The body of one top-level arrow function of an AMD module.
     *
     * @param string $name The function's name.
     * @param string $module The module's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = 'categorylisting'): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read('amd/src/' . $module . '.js'), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
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
     * An element and everything in it as a string to compare: tag, attributes sorted, text with its
     * whitespace collapsed, and the whitespace between elements dropped (every container of the line is a
     * flex box, which lays it out as nothing).
     *
     * @param \DOMNode $node The element.
     * @return string
     */
    private function shape(\DOMNode $node): string {
        if ($node instanceof \DOMText) {
            $text = (string) preg_replace('~\s+~', ' ', $node->textContent);
            return trim($text) === '' ? '' : $text;
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[] = $attribute->name . '="' . $attribute->value . '"';
        }
        sort($attributes);
        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $this->shape($child);
        }
        return '<' . implode(' ', array_merge([$node->tagName], $attributes)) . '>' . $children . '</' . $node->tagName . '>';
    }

    /**
     * The line of a toolbar's markup, as a string to compare.
     *
     * @param string $html The toolbar's markup.
     * @return string
     */
    private function line_of(string $html): string {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $lines = (new \DOMXPath($document))->query('//div[@data-region="dim-line"]');
        $this->assertSame(1, $lines->length, 'One line in the toolbar.');
        return $this->shape($lines->item(0));
    }

    /**
     * The extractor returns one function and not the module around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $mount = $this->body('mountLine');
        $this->assertStringContainsString('reactComponent', $mount);
        $this->assertStringNotContainsString('appendCalendar(', $mount, 'The extractor is returning more than one function.');
    }

    /**
     * The line is the mount: named for the component and put back where it stands, its listeners on first.
     *
     * Changes that must make it fail: the guard on the field and the listbox dropped (the component would
     * replace a line it cannot draw with nothing); the line not put back (react_autoinit hears no added
     * node and the line is never drawn); the props nested under a key (the component renders nothing); the
     * dropterm listener added after the node goes in; the pending mount never released, or released only when
     * the component draws (a line it cannot draw would hold every Behat step on the page: gate js_line_failed_released);
     * the form's id read as a property (gate js_line_form_id_attribute): the visitor's script form carries a hidden
     * field named "id", which form.id returns, so the component is handed {} and finds no line to draw or to
     * report failed on. Only the script entry has that field, so only a site without the router shows it.
     */
    public function test_the_line_is_handed_over_only_where_the_component_can_draw_it(): void {
        $mount = $this->body('mountLine');
        $listing = $this->read('amd/src/categorylisting.js');
        $this->assertStringContainsString('export const mountLine = (root, dropTerm) => {', $listing);
        $this->assertStringContainsString("const formid = form ? form.getAttribute('id') : null;", $mount);
        $this->assertDoesNotMatchRegularExpression(
            '~\bform\.id\b~',
            $this->uncommented($mount),
            'A form\'s id property is the control named "id" when the form has one.'
        );
        $this->assertMatchesRegularExpression(
            '~if \(!line \|\| !line\.querySelector\(SELECTORS\.linefield\) \|\| '
                . '!line\.querySelector\(SELECTORS\.listbox\) \|\| !formid\) \{\s*return false;\s*\}~',
            $mount,
            'A line without its field or its listbox stays the server\'s, which works without the component.'
        );
        $this->assertStringContainsString("listbox: '[data-region=\"dim-line-suggest\"]',", $listing);
        $this->assertStringContainsString('line.dataset.reactComponent = COMPONENTS.line;', $mount);
        $this->assertStringContainsString(
            'line.dataset.reactProps = JSON.stringify({form: formid});',
            $mount,
            'The props object itself.'
        );
        $this->assertStringContainsString('line.parentNode.insertBefore(line, line.nextSibling);', $mount);
        $named = strpos($mount, 'line.dataset.reactComponent');
        $listeners = [
            'line.addEventListener(LINE.dropterm, () => dropTerm());',
            'line.addEventListener(LINE.mounted, () => pending.resolve(), {once: true});',
            'line.addEventListener(LINE.failed, () => pending.resolve(), {once: true});',
        ];
        foreach ($listeners as $listener) {
            $at = strpos($mount, $listener);
            $this->assertNotFalse($at, $listener . ' is missing.');
            $this->assertLessThan($named, $at, $listener . ' comes after the line is named.');
        }
        $this->assertStringContainsString("const pending = new Pending('theme_dimensions/categorylisting:line');", $mount);
    }

    /**
     * The four event names and the drawn mark are spelled alike on both sides.
     *
     * A name spelled differently on one side errors nowhere: the line never hears an answer, or the
     * listing never drops the term before a suggestion's press, or every painter writes into the
     * component's markup because it never sees the mark.
     */
    public function test_the_two_sides_spell_the_events_and_the_mark_alike(): void {
        $amd = $this->names($this->read('amd/src/categorylisting.js'), 'export const LINE = {');
        $esm = $this->names($this->read('js/esm/src/filterline/FilterLine.tsx'), 'export const EVENTS = {');
        $this->assertSame(
            [
                'mounted' => 'dim-line:mounted',
                'failed' => 'dim-line:failed',
                'state' => 'dim-line:state',
                'answer' => 'dim-line:answer',
                'dropterm' => 'dim-line:dropterm',
            ],
            $amd
        );
        $this->assertSame($amd, $esm);
        $this->assertStringContainsString("const LINE_DRAWN = 'data-line-drawn';", $this->read('amd/src/categorylisting.js'));
        $this->assertStringContainsString(
            "export const DRAWN = 'data-line-drawn';",
            $this->read('js/esm/src/filterline/FilterLine.tsx')
        );
        $this->assertStringContainsString(
            "drawnline: '[data-region=\"dim-line\"][data-line-drawn]',",
            $this->read('amd/src/categoryvitrine.js')
        );
    }

    /**
     * No painter of the shared listing writes into what the component draws; it tells the component instead.
     *
     * The guard is one function: inside a line the component marked drawn, and outside the three frames it
     * adopts, which stay the listing's to paint. On the visitor's shell no line is marked and every painter
     * paints as it always did.
     *
     * Changes that must make it fail: any painter's guard dropped (React redraws over the write, or loses
     * its focus); the adopted frames left out of the guard (More statuses, the fields, the sort and "Show
     * as" would stop being painted on the logged-in shell); the state not told from paintControls() (Back
     * and Clear all would leave the line's controls on the old state).
     */
    public function test_no_painter_writes_into_what_the_component_draws(): void {
        $drawn = $this->body('drawnByLine');
        $this->assertStringContainsString(
            'return Boolean(line) && line.hasAttribute(LINE_DRAWN) && !element.closest(SELECTORS.adopted);',
            $drawn
        );
        $this->assertStringContainsString(
            "adopted: '[data-region=\"dim-filters-panel\"], [data-region=\"dim-organise-menu\"], "
                . "[data-region=\"dim-line-manage\"]',",
            $this->read('amd/src/categorylisting.js')
        );
        $guards = [
            'paintView' => 'if (!drawnByLine(button)) {',
            'paintFavToggle' => 'if (!star || drawnByLine(star)) {',
            'paintEraser' => "if (search && eraser && !drawnByLine(eraser)) {",
            'paintControls' => 'if (search && !drawnByLine(search)) {',
            'paintFavourites' => 'if (count && !drawnByLine(count)) {',
            'paintFilterBadge' => 'if (!badge || drawnByLine(badge)) {',
            'paintGroups' => 'if (drawnByLine(fieldset)) {',
        ];
        $this->assertSame(self::PAINTERS, array_keys($guards));
        foreach ($guards as $painter => $guard) {
            $this->assertStringContainsString($guard, $this->body($painter), "{$painter} writes into the component's markup.");
        }
        $this->assertMatchesRegularExpression(
            '~root\.querySelectorAll\(SELECTORS\.group\)\.forEach\(\(fieldset\) => \{\s*'
                . 'if \(drawnByLine\(fieldset\)\) \{\s*return;~',
            $this->body('paintControls'),
            'Back and Clear all check no radio the component draws.'
        );
        $this->assertStringContainsString(
            'tellLine(root, LINE.state, {query: state.query, chip: state.chip, fav: Boolean(state.fav), view: state.view});',
            $this->body('paintControls')
        );
        $this->assertStringContainsString("tellLine(root, LINE.state, {fav: Boolean(on)});", $this->body('paintFavToggle'));
        $this->assertStringContainsString('tellLine(root, LINE.state, {view: view});', $this->body('paintView'));
        $tell = $this->body('tellLine');
        $this->assertMatchesRegularExpression('~if \(!line \|\| !line\.hasAttribute\(LINE_DRAWN\)\) \{\s*return false;~', $tell);
        $this->assertStringContainsString('line.dispatchEvent(new CustomEvent(type, {detail: detail}));', $tell);
        // The hidden favourites field is the form's, before the line: still painted on both shells.
        $this->assertMatchesRegularExpression(
            '~const field = root\.querySelector\(SELECTORS\.favfield\);\s*'
                . 'if \(field\) \{\s*field\.disabled = !on;\s*\}\s*'
                . 'const star = root\.querySelector\(SELECTORS\.star\);~',
            $this->body('paintFavToggle')
        );
    }

    /**
     * An answer's numbers reach the component as one event, after the painters, in SCOPE's order.
     *
     * Changes that must make it fail: the scope told in the answer's order; an answer with no status option
     * telling an empty scope (every cell would hide on a page whose state sort was switched off under it);
     * the logged-in shell repainting categoryline instead of telling the component; tellAnswer() before the
     * painters (the suggestions would read the panel's old counts).
     */
    public function test_an_answer_is_told_to_the_component_after_the_painters(): void {
        $answer = $this->body('tellAnswer');
        $this->assertStringContainsString('const rank = (option) => SCOPE.indexOf(String(option.value));', $answer);
        $this->assertStringContainsString(
            'scope: options.length ? options.filter((option) => rank(option) >= 0).sort((a, b) => rank(a) - rank(b)) : null,',
            $answer
        );
        $parts = [
            'favourites: response.favourites || null,',
            'filtercount: Number(response.filtercount || 0),',
            'filtercountlabel: response.filtercountlabel || \'\',',
        ];
        foreach ($parts as $part) {
            $this->assertStringContainsString($part, $answer);
        }
        $apply = $this->body('applyResponse', 'categoryvitrine');
        $told = strpos($apply, 'tellAnswer(response);');
        $this->assertNotFalse($told);
        foreach (['paintFilterBadge(response);', 'paintGroups(response);', 'paintFavourites(response);'] as $painter) {
            $this->assertLessThan($told, (int) strpos($apply, $painter), $painter . ' comes after the line is told.');
        }
        $this->assertStringNotContainsString('Line.', $this->uncommented($this->read('amd/src/categoryvitrine.js')));
        $this->assertStringNotContainsString("'theme_dimensions/categoryline'", $this->read('amd/src/categoryvitrine.js'));
    }

    /**
     * The shared listing names the component, which is built, and both shells hand their line to it.
     *
     * Changes that must make it fail: a client arming categoryline again or naming the component itself; the
     * visitor's module handing its line to nothing (gate js_public_line_armed); the component not built.
     */
    public function test_both_shells_hand_their_line_to_the_component_named_in_the_listing(): void {
        $this->assertStringContainsString("    line: '" . self::SPECIFIER . "',\n", $this->read('amd/src/categorylisting.js'));
        $this->assertStringContainsString(
            'const mountLine = () => Listing.mountLine(root, dropTerm);',
            $this->read('amd/src/categoryvitrine.js')
        );
        foreach (['FilterLine', 'lazy', 'preview', 'model', 'measure', 'steps', 'suggest', 'mark'] as $module) {
            foreach (['js', 'dev.js'] as $build) {
                $this->assertFileExists($this->plugin_root() . "/js/esm/build/filterline/{$module}.{$build}");
            }
        }
        $public = $this->uncommented($this->read('amd/src/categorypublic.js'));
        $this->assertStringContainsString('Listing.mountLine(root, dropTerm);', $public);
        $this->assertStringContainsString('Listing.tellAnswer(root, response);', $public);
        foreach (['categorypublic', 'categoryvitrine'] as $module) {
            $source = $this->read('amd/src/' . $module . '.js');
            $this->assertStringNotContainsString("'theme_dimensions/categoryline'", $source, $module . ' arms categoryline.');
            $this->assertStringNotContainsString(
                '@moodle/lms/theme_dimensions/filterline',
                $source,
                $module . ' names the component: the listing names it, once.'
            );
        }
    }

    /**
     * The line is handed over before the calendar's node goes into it.
     *
     * The line is put back where it stands, which core's observer reads as the line leaving and coming
     * back; a calendar already mounted inside it would be unmounted and drawn again.
     *
     * Changes that must make it fail: mountLine() called after mountCalendar().
     */
    public function test_the_line_is_handed_over_before_the_calendar_goes_in(): void {
        $init = $this->body('init', 'categoryvitrine');
        $line = strpos($init, "\n    mountLine();");
        $calendar = strpos($init, "\n    mountCalendar().catch(Notification.exception);");
        $this->assertNotFalse($line);
        $this->assertNotFalse($calendar);
        $this->assertLessThan($calendar, $line);
    }

    /**
     * The logged-in module reads the component's field without holding the server's, and writes into neither.
     *
     * Changes that must make it fail: the input listener bound to the server's field (the component
     * replaces it, and typing would never search); the eraser's handler writing the component's field;
     * the focus after a clear sent to a field folded away instead of its icon; the term kept in the
     * state when the component says a suggestion is about to press its control (the one request would
     * carry it).
     */
    public function test_the_logged_in_module_reads_the_line_and_never_writes_into_it(): void {
        $drop = $this->body('dropTerm', 'categoryvitrine');
        $this->assertMatchesRegularExpression(
            "~cancelSearch\\(\\);\\s*state\\.query = '';~",
            $drop,
            'A suggestion\'s press carries no term.'
        );
        $this->assertStringContainsString('paintEraser();', $drop);
        $erase = $this->body('onErase', 'categoryvitrine');
        $this->assertMatchesRegularExpression(
            "~if \(search && !search\.closest\(SELECTORS\.drawnline\)\) \{\s*search\.value = '';~",
            $erase
        );
        $this->assertStringContainsString("await onSearch('');", $erase);
        $listen = $this->body('listen', 'categoryvitrine');
        $this->assertStringNotContainsString("search.addEventListener('input'", $listen);
        $this->assertStringContainsString("form.addEventListener('input', (event) => {", $listen);
        $target = $this->body('focusTarget');
        $this->assertStringContainsString(
            'if (!search || search.getClientRects().length > 0 || !opener || opener.getClientRects().length === 0) {',
            $target
        );
        $this->assertMatchesRegularExpression('~\}\s*return opener;\s*$~', $target);
        foreach (['onClear', 'onChipRemove'] as $handler) {
            $this->assertStringContainsString('const search = focusTarget();', $this->body($handler, 'categoryvitrine'));
        }
    }

    /**
     * The first commit reads no layout: the line's first measure is the ResizeObserver's first entry's.
     *
     * A measure inside React's commit forces the page's first full layout (PLAN 5.2.6); the observer's first entry
     * comes after the browser's own layout and before the paint, so the line is still folded before it is painted.
     * The component's behaviour around it is Jest's (FilterLine.test.tsx); this holds the shape the mutation sweep,
     * which runs PHPUnit alone, can redden.
     *
     * The line is hidden from the commit to that first fold (a fold in the callback moves nodes the browser already
     * laid out, a layout shift; hidden nodes count for none), unless it holds the focus, and shown again by the
     * measure, whether it succeeded or not, and by the effect's cleanup.
     *
     * Changes that must make it fail: the measure taken in the first commit's effect where an observer exists; the
     * observer's first entry taking no measure; the layers placed, the fonts' or a new content's remeasure taken
     * before that first measure; the line not hidden, hidden while it holds the focus, or not shown again.
     */
    public function test_the_first_measure_waits_for_the_observers_first_entry(): void {
        $component = $this->read('js/esm/src/filterline/FilterLine.tsx');
        $found = preg_match("~\n    // The first commit: (.*?)\n    // The first commit's effect, once~s", $component, $matches);
        $this->assertSame(1, $found, 'No first commit effect in FilterLine.tsx.');
        $effect = $this->uncommented($matches[1]);
        $this->assertMatchesRegularExpression(
            "~\n        if \(typeof ResizeObserver === 'undefined'\) \{\s*measureFirst\(\);\s*\} else if "
                . "\(!setup\.focused && !line\.contains\(document\.activeElement\)\) \{\s*"
                . "st\.veiled = line\.style\.visibility;\s*line\.style\.visibility = 'hidden';\s*\}\n~",
            $effect
        );
        $this->assertMatchesRegularExpression('~return \(\) => \{\s*observer\?\.disconnect\(\);\s*unveil\(\);~', $effect);
        $this->assertSame(1, substr_count($effect, 'measureFirst('), 'The first commit measures outside the fallback.');
        foreach (['stepWidths(', 'contentWidth(', 'refold(', 'remeasure()'] as $measure) {
            $this->assertStringNotContainsString($measure, $effect, "The first commit calls {$measure}.");
        }
        $this->assertMatchesRegularExpression(
            '~const width = entries\[0\]\.contentRect\.width;\s*if \(!live\.current\.measuredOnce\) \{\s*'
                . 'flushSync\(\(\) => \(api\.current\.measureFirst as \(width: number\) => void\)\(width\)\);\s*return;~',
            $effect
        );
        $this->assertStringContainsString('if (line.isConnected && live.current.measuredOnce) {', $effect);
        $this->assertMatchesRegularExpression(
            '~const measureFirst = \(width\?: number\): void => \{.*?st\.measuredOnce = true;.*?'
                . 'st\.widths = stepWidths\(line, st\.fold, st\.width\);\s*refold\(\);\s*\} catch \(error\) \{\s*'
                . 'st\.widths = \[\];\s*\}\s*unveil\(\);~s',
            $component
        );
        $this->assertMatchesRegularExpression(
            '~measured\.current = signature;\s*(//[^\n]*\s*)?if \(st\.measuredOnce\) \{\s*remeasure\(\);~',
            $component
        );
        $this->assertStringContainsString('if (!line || !drawn.current || !st.measuredOnce) {', $component);
    }

    /**
     * The component adopts exactly the three frames the painters keep painting, and calls nothing itself.
     *
     * Changes that must make it fail: a frame adopted that the guard does not exempt, or exempted and never
     * adopted; the component calling a service or composing a string.
     */
    public function test_the_component_adopts_the_three_frames_and_asks_for_nothing(): void {
        $component = $this->read('js/esm/src/filterline/FilterLine.tsx');
        foreach (['panel', 'menu', 'manage'] as $frame) {
            $this->assertStringContainsString("adopt(setup.{$frame}, {$frame}.current);", $component);
        }
        $model = $this->read('js/esm/src/filterline/model.ts');
        $frames = ['panel' => 'dim-filters-panel', 'menu' => 'dim-organise-menu', 'manage' => 'dim-line-manage'];
        foreach ($frames as $key => $region) {
            $this->assertStringContainsString("{$key}: '[data-region=\"{$region}\"]',", $model);
        }
        foreach (glob($this->plugin_root() . '/js/esm/src/filterline/*.ts*') as $file) {
            $code = $this->uncommented((string) file_get_contents($file));
            foreach (['methodname', 'core/ajax', '../amd', 'getString', 'stringUtils'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $code, basename($file) . " names {$forbidden}.");
            }
        }
    }

    /**
     * The Jest suites' toolbar is the template rendered from its example context, line for line.
     *
     * The component's first render must draw the boxes the server drew, which Jest checks against
     * js/esm/tests/filterline/toolbar.ts; this is what makes that copy the template's markup and not a
     * markup of its own.
     *
     * Changes that must make it fail: the line's markup changed in the template and not in the copy.
     */
    public function test_the_jest_fixture_is_the_template_rendered_from_its_example(): void {
        global $PAGE;
        $this->resetAfterTest();
        $source = $this->read('templates/category_toolbar.mustache');
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1], true);
        $this->assertIsArray($context);
        $PAGE->set_url('/course/index.php');
        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/category_toolbar', $context);

        $fixture = $this->read('js/esm/tests/filterline/toolbar.ts');
        $this->assertSame(1, preg_match('~export const TOOLBAR = \\x60(.*)\\x60;~s', $fixture, $copy));
        $this->assertSame($this->line_of($html), $this->line_of($copy[1]));
    }
}
