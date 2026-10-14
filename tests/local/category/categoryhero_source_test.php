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
 * Theme Dimensions - Tests for the invariants of the category appearance form's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/categoryhero.js by reading its source.
 *
 * Plugin CI runs no JavaScript unit tests, so the properties of the appearance form's module whose
 * loss would cost more than a screenshot are pinned the way category_scss_test pins class names
 * and amd_source_test pins the showcase module. Three of them concern the pair of colours a
 * manager publishes:
 *
 * - The panel grades against the WCAG thresholds for normal text, and its AA is the server's own
 *   refusal floor: a panel saying "Pass" over a pair branding::save() refuses would send the
 *   manager from a green verdict to a form error.
 * - The maths are the server's maths. The ratio is computed in PHP and in JavaScript, and
 *   tests/fixtures/contrast_pairs.json is the fixture both are measured against. The module
 *   cannot be executed here, so its WCAG constants are read out of the source and the fixture's
 *   pairs computed with them. That proves the numbers, not the code path using them (a changed
 *   constant fails, a line deleted from luminance() does not), so the shape of those expressions
 *   is pinned separately.
 * - No form row is relocated: the module reads the two rows only as anchors and inserts beside
 *   them, and a test holds every line that names one to that.
 *
 * As for every module of this theme, the committed build must have been made from the committed
 * source, because Moodle serves amd/build and never amd/src.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categoryhero_source_test extends \basic_testcase {
    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * The module's source, as committed.
     *
     * @return string
     */
    private function source(): string {
        return file_get_contents($this->plugin_root() . '/amd/src/categoryhero.js');
    }

    /**
     * The committed build, which is the file a browser actually runs.
     *
     * @return string
     */
    private function build(): string {
        $path = $this->plugin_root() . '/amd/build/categoryhero.min.js';
        $this->assertFileExists($path, 'The build is missing: Moodle serves amd/build, never amd/src.');
        return file_get_contents($path);
    }

    /**
     * The source the committed build was actually made from.
     *
     * @return string
     */
    private function built_from(): string {
        $path = $this->plugin_root() . '/amd/build/categoryhero.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode(file_get_contents($path), true);
        $this->assertIsArray($map);
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * The body of one top-level arrow function of the module.
     *
     * Every function of the module is a top-level `const <name> = (…) => {` closed by a `};` in
     * the first column, so the body can be cut out and an assertion scoped to it: a bare grep
     * over the whole file would stay green with a call moved somewhere it never runs.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $source = $this->source();
        $pattern = '~\nconst ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $source, $matches);
        $this->assertSame(1, $found, 'No top-level function named ' . $name . ' in the module.');
        return $matches[1];
    }

    /**
     * The shared contrast fixture: the pairs both implementations of the maths are measured on.
     *
     * @return array A list of ['bg' => hex, 'ink' => hex, 'ratio' => float].
     */
    private function fixture(): array {
        $path = $this->plugin_root() . '/tests/fixtures/contrast_pairs.json';
        $this->assertFileExists($path, 'The shared contrast fixture is missing.');
        $fixture = json_decode(file_get_contents($path), true);
        $this->assertIsArray($fixture);
        $this->assertArrayHasKey('pairs', $fixture);
        $this->assertNotEmpty($fixture['pairs'], 'An empty fixture would measure nothing.');
        return $fixture['pairs'];
    }

    /**
     * The tolerance the fixture itself states, so both readers of it compare the same way.
     *
     * @return float
     */
    private function tolerance(): float {
        $path = $this->plugin_root() . '/tests/fixtures/contrast_pairs.json';
        $fixture = json_decode(file_get_contents($path), true);
        return (float) ($fixture['tolerance'] ?? 0.01);
    }

    /**
     * One float captured out of the module's source, so the maths below are the module's.
     *
     * @param string $pattern A regular expression with exactly one capturing group.
     * @param string $what What is being read, for the failure message.
     * @return float
     */
    private function constant_in_source(string $pattern, string $what): float {
        $found = preg_match($pattern, $this->source(), $matches);
        $this->assertSame(1, $found, 'The module no longer spells ' . $what . ' the way this test reads it.');
        return (float) $matches[1];
    }

    /**
     * The extractor really returns one function's body and not the whole file.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $body = $this->body('writeColour');
        $this->assertStringContainsString('input.value = value;', $body);
        $this->assertStringNotContainsString('export const init', $body);
        $this->assertStringNotContainsString('const suggest', $body);
    }

    /**
     * The panel grades against the WCAG thresholds for normal text, and its AA is the server's floor.
     *
     * Changes that must make it fail: raising the module's aa to 3, which would make a pair the
     * server refuses read "Pass" in the panel.
     */
    public function test_the_thresholds_are_the_wcag_ones_and_aa_is_the_servers_floor(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            '~^const THRESHOLDS = \{aa: 4\.5, aaa: 7, attention: 3\};$~m',
            $source,
            'The panel must grade normal text: AA 4.5:1, AAA 7:1.'
        );
        $aa = $this->constant_in_source('~const THRESHOLDS = \{aa: ([0-9.]+),~', 'its AA threshold');
        $this->assertSame(
            (float) branding::FLOOR,
            $aa,
            'The panel would grade a pair the server refuses, or refuse one it grades as passing.'
        );
    }

    /**
     * The module's own WCAG constants reproduce the shared fixture, and so does the server.
     *
     * The maths are read out of the module's source and run here over the same pairs
     * branding_test measures branding::contrast() on, so the two implementations cannot drift:
     * a coefficient changed on either side leaves the fixture unmatched.
     */
    public function test_the_contrast_maths_agree_with_the_shared_fixture(): void {
        $red = $this->constant_in_source('~luminance = \(rgb\) => ([0-9.]+) \* linearise~', 'its red coefficient');
        $green = $this->constant_in_source('~linearise\(rgb\.r\) \+ ([0-9.]+) \* linearise\(rgb\.g\)~', 'its green coefficient');
        $blue = $this->constant_in_source('~linearise\(rgb\.g\) \+ ([0-9.]+) \* linearise\(rgb\.b\)~', 'its blue coefficient');
        $knee = $this->constant_in_source('~return c <= ([0-9.]+) \? c / 12\.92~', 'the linearisation knee');
        $slope = $this->constant_in_source('~\? c / ([0-9.]+) :~', 'the linear segment slope');
        $offset = $this->constant_in_source('~Math\.pow\(\(c \+ ([0-9.]+)\) /~', 'the gamma offset');
        $scale = $this->constant_in_source('~Math\.pow\(\(c \+ 0\.055\) / ([0-9.]+),~', 'the gamma scale');
        $gamma = $this->constant_in_source('~Math\.pow\(\(c \+ 0\.055\) / 1\.055, ([0-9.]+)\)~', 'the gamma exponent');
        $lift = $this->constant_in_source(
            '~return \(Math\.max\(l1, l2\) \+ ([0-9.]+)\) / \(Math\.min\(l1, l2\) \+ 0\.05\);~',
            'the ratio lift'
        );

        $linearise = function (int $channel) use ($knee, $slope, $offset, $scale, $gamma): float {
            $c = $channel / 255;
            return $c <= $knee ? $c / $slope : pow(($c + $offset) / $scale, $gamma);
        };
        $luminance = function (string $hex) use ($linearise, $red, $green, $blue): float {
            $channels = sscanf($hex, '#%2x%2x%2x');
            return $red * $linearise((int) $channels[0])
                + $green * $linearise((int) $channels[1])
                + $blue * $linearise((int) $channels[2]);
        };

        foreach ($this->fixture() as $pair) {
            $this->assertArrayHasKey('bg', $pair);
            $this->assertArrayHasKey('ink', $pair);
            $this->assertArrayHasKey('ratio', $pair);
            $first = $luminance($pair['bg']);
            $second = $luminance($pair['ink']);
            $ratio = (max($first, $second) + $lift) / (min($first, $second) + $lift);
            $this->assertEqualsWithDelta(
                (float) $pair['ratio'],
                $ratio,
                $this->tolerance(),
                'The module\'s constants no longer grade ' . $pair['bg'] . ' on ' . $pair['ink'] . ' as the fixture says.'
            );
            $this->assertEqualsWithDelta(
                (float) $pair['ratio'],
                branding::contrast($pair['bg'], $pair['ink']),
                $this->tolerance(),
                'The server no longer grades ' . $pair['bg'] . ' on ' . $pair['ink'] . ' as the fixture says.'
            );
        }
    }

    /**
     * The shape of the maths, which reading the constants out of the source cannot prove.
     *
     * The relative luminance is a weighted sum of the three LINEARISED channels, each coefficient
     * on its own channel, and the ratio puts the lighter colour on top. A module that weighted
     * the raw channels, paired a coefficient with the wrong one, or divided the darker by the
     * lighter would keep every constant the test above reads and grade every pair wrongly.
     */
    public function test_the_maths_are_spelled_the_way_wcag_states_them(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            '~^const luminance = \(rgb\) => 0\.2126 \* linearise\(rgb\.r\)'
                . ' \+ 0\.7152 \* linearise\(rgb\.g\) \+ 0\.0722 \* linearise\(rgb\.b\);$~m',
            $source,
            'Each WCAG coefficient must weight its own linearised channel.'
        );
        $this->assertStringContainsString(
            'return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);',
            $this->body('contrast'),
            'The ratio is the lighter luminance over the darker one, both lifted by 0.05.'
        );
    }

    /**
     * Every colour the module writes into a field is announced with both events.
     *
     * The swatch, the live validity mark and the panel listen for input; a value assigned without
     * it leaves the swatch and the panel showing the previous colour and verdict. Dropping change
     * breaks nothing visible, because the panel listens for input too, but core's form change
     * checker listens for change alone, so a colour written from a suggestion would leave the page
     * without the unsaved-changes warning.
     */
    public function test_a_written_colour_is_announced_with_input_and_change(): void {
        $body = $this->body('writeColour');
        $this->assertMatchesRegularExpression(
            "~input\\.dispatchEvent\\(new Event\\('input', \\{bubbles: true\\}\\)\\);~",
            $body
        );
        $this->assertMatchesRegularExpression(
            "~input\\.dispatchEvent\\(new Event\\('change', \\{bubbles: true\\}\\)\\);~",
            $body
        );
        // And nothing else in the module writes a field's value behind the page's back.
        $writes = preg_match_all('~^\s*\w+\.value = ~m', $this->source());
        $this->assertSame(
            2,
            $writes,
            'A field value is written outside writeColour(), or the swatch no longer mirrors the field.'
        );
    }

    /**
     * A value that is not a hex colour is flagged at the field, on input and on blur.
     *
     * The server refuses it on save; this says so before the save, on both colour fields and with
     * the string the form's validation answers with, so a manager typing `12484f` reads
     * "Enter a colour as #rrggbb." under the field instead of after a round trip.
     */
    public function test_a_value_that_is_not_a_colour_is_flagged_while_it_is_typed(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            "~^\\s*input\\.addEventListener\\('input', mark\\);$~m",
            $source,
            'The field is not re-checked while it is being typed.'
        );
        $this->assertMatchesRegularExpression(
            "~^\\s*input\\.addEventListener\\('blur', mark\\);$~m",
            $source,
            'A half-typed value left behind is never re-checked.'
        );
        $body = $this->body('markValidity');
        $this->assertStringContainsString("const invalid = raw !== '' && !HEXRE.test(raw);", $body);
        $this->assertStringContainsString("input.classList.toggle('is-invalid', invalid);", $body);
        $this->assertStringContainsString("feedback.className = 'invalid-feedback';", $body);
        $this->assertStringContainsString(
            "feedback.classList.toggle('d-block', invalid);",
            $body,
            'Bootstrap hides .invalid-feedback by default and the sibling rule cannot match here, '
                . 'so d-block is the only thing that shows the message at all.'
        );
        $this->assertStringContainsString('feedback.hidden = !invalid;', $body);
        $this->assertStringContainsString(
            'feedback.textContent = labels.invalidcolour;',
            $body,
            'The message is the server\'s own string, so the two can never say different things.'
        );
        $this->assertStringContainsString(
            "input.setAttribute('aria-describedby', described.join(' '));",
            $body,
            'The message is not named by the field it belongs to.'
        );
        $this->assertStringContainsString(
            "input.removeAttribute('aria-describedby');",
            $body,
            'A field that is valid again keeps pointing at a message about a value it no longer holds.'
        );
        $this->assertMatchesRegularExpression(
            "~^\\s*invalidcolour: 'categoryhero_invalidcolour',$~m",
            $source,
            'The message does not come from the string the server answers with.'
        );
        $this->assertTrue(
            get_string_manager()->string_exists('categoryhero_invalidcolour', 'theme_dimensions'),
            'The module asks for a string that does not exist.'
        );
    }

    /**
     * The quick colours stay removed.
     *
     * Asserted because the module is where they would come back: the swatch, the panel and the
     * two free fields are the whole picker, and init() takes no argument, so nothing can hand it
     * a palette to draw.
     */
    public function test_the_quick_colours_are_gone(): void {
        $source = $this->source();
        foreach (['buildPresets', 'preset', 'dim-colour-preset'] as $trace) {
            $this->assertStringNotContainsString(
                $trace,
                $source,
                'A trace of the quick colours is back in the module: ' . $trace
            );
        }
        $this->assertMatchesRegularExpression(
            '~^export const init = \(\) => \{$~m',
            $source,
            'init() takes an argument again, which is how a palette would be handed back to it.'
        );
    }

    /**
     * The module binds to the form's own field names, which is how the server reads them back.
     */
    public function test_it_binds_to_the_forms_own_field_names(): void {
        $source = $this->source();
        $this->assertStringContainsString("bg: 'input[name=\"bgcolour\"]'", $source);
        $this->assertStringContainsString("ink: 'input[name=\"inkcolour\"]'", $source);
    }

    /**
     * No form row is ever relocated: the two rows are read as anchors and nothing else.
     *
     * Every line of the module naming one of the rows has to be an insertion beside it. A line
     * that moved a row somewhere else is not in the allowlist and fails here: a relocated row
     * can leave its field focusable and announced while it is painted nowhere.
     */
    public function test_no_form_row_is_relocated(): void {
        $allowed = [
            '~^const rowOf = ~',
            '~^\s*const \w+row = rowOf\(\w+\);$~',
            '~^\s*fieldrow\.appendChild\(feedback\);$~',
        ];
        $lines = preg_grep(
            '~\browOf\b|\bbgrow\b|\binkrow\b|\bfieldrow\b~',
            explode("\n", $this->source())
        );
        $this->assertNotEmpty($lines, 'The module no longer names the rows at all; this test proves nothing.');
        foreach ($lines as $number => $line) {
            if (str_starts_with(ltrim($line), '*') || str_starts_with(ltrim($line), '//')) {
                continue;
            }
            $matched = false;
            foreach ($allowed as $pattern) {
                if (preg_match($pattern, $line)) {
                    $matched = true;
                    break;
                }
            }
            $this->assertTrue($matched, 'Line ' . ($number + 1) . ' does something to a form row: ' . trim($line));
        }
    }

    /**
     * The pair preview is painted from the valid-hex path and from nowhere else.
     *
     * The band is rendered by the form with the stored pair and is correct without this module;
     * the module only keeps it in step. What makes that safe is where the write sits: inside the
     * branch the swatch already takes for a value that parsed. One line outside it, the band would
     * be repainted from every half-typed value and lose its colours, on the one element whose job
     * is to show what is about to be published. So the write is read out of that block, not
     * merely found in the file.
     */
    public function test_the_preview_is_painted_from_the_valid_hex_path_alone(): void {
        $source = $this->source();
        $body = $this->body('decorate');

        $found = preg_match('~\n        if \(full\) \{\n(.*?)\n        \}~s', $body, $matches);
        $this->assertSame(1, $found, 'The swatch no longer has a branch for a value that parsed.');
        $this->assertStringContainsString(
            'paintPreview(property, full);',
            $matches[1],
            'The preview is painted outside the branch the swatch takes for a valid hex.'
        );
        $this->assertStringContainsString(
            'colourinput.value = full;',
            $matches[1],
            'The branch read above is not the swatch\'s own, so this test is measuring something else.'
        );

        // Exactly one call site: two would be a second, unguarded writer of the same element.
        $this->assertSame(
            1,
            preg_match_all('~^\s*paintPreview\(~m', $source),
            'The preview is written from more than one place in the module.'
        );
        $this->assertStringContainsString(
            "preview: '[data-region=\"dim-pair-preview\"]'",
            $source,
            'The module no longer addresses the preview the form renders.'
        );
        $this->assertStringContainsString(
            'preview.style[property] = colour;',
            $this->body('paintPreview'),
            'The preview is painted by something other than the two style properties.'
        );
    }

    /**
     * The panel is appended as the last child of the colours fieldset, and turns the grid on.
     *
     * The two columns are a CSS grid over the colours fieldset's container, so the panel goes
     * inside that container, after the preview (the reading order is fields, pair, panel). The
     * class the stylesheet gates the grid on is added by the module, not written by the form:
     * with JavaScript off there is no panel, and a grid switched on by the server would leave an
     * empty right-hand track.
     */
    public function test_the_panel_is_mounted_last_in_the_colours_fieldset_and_turns_the_grid_on(): void {
        $source = $this->source();

        $this->assertMatchesRegularExpression(
            "~^const SPLIT_CLASS = 'dim-colours--split';\$~m",
            $source,
            'The class the stylesheet gates the two-column grid on is not the one the module adds.'
        );
        $this->assertMatchesRegularExpression(
            "~^\\s*const fieldset = inkinput\\.closest\\('fieldset'\\);\$~m",
            $source,
            'The module no longer looks the colours fieldset up from the field inside it.'
        );
        $this->assertMatchesRegularExpression(
            '~^\s*container\.appendChild\(aside\);$~m',
            $source,
            'The panel is no longer appended as the last child of the fieldset\'s container.'
        );
        $this->assertMatchesRegularExpression(
            '~^\s*fieldset\.classList\.add\(SPLIT_CLASS\);$~m',
            $source,
            'The fieldset is never marked as split, so the grid never applies and the panel sits under the rows.'
        );
        // And nothing places the panel by hand, or before the rows.
        $this->assertStringNotContainsString(
            'insertAdjacentElement',
            $source,
            'Something is inserted beside an element again: the panel is appended, not placed by hand.'
        );
        $this->assertStringNotContainsString(
            'prepend(',
            $source,
            'The panel would come before the rows, which is not the reading order the design asks for.'
        );
    }

    /**
     * A suggestion is applied to the field it names, and the panel offers one for each.
     */
    public function test_a_suggestion_is_applied_to_the_field_it_names(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const input = option.apply === 'ink' ? inkinput : bginput;",
            $source
        );
        $this->assertStringContainsString("apply: 'ink'", $source);
        $this->assertStringContainsString("apply: 'bg'", $source);
    }

    /**
     * The port carries none of local_dimensions with it: the theme does not depend on that plugin at runtime.
     */
    public function test_the_port_left_the_original_plugin_behind(): void {
        $source = $this->source();
        $this->assertStringNotContainsString('local_dimensions/', $source);
        $this->assertStringNotContainsString('local-dimensions', $source);
        $this->assertStringContainsString("const COMPONENT = 'theme_dimensions';", $source);
        $this->assertStringContainsString(
            "const PANEL_TEMPLATE = 'theme_dimensions/categoryhero_contrast';",
            $source
        );
    }

    /**
     * The committed build was made from the source that is committed beside it - exactly.
     *
     * Moodle serves amd/build and never amd/src, whatever cachejs says, so a source edit that
     * ships without its rebuild changes nothing on the page.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $this->assertSame(
            $this->source(),
            $this->built_from(),
            'amd/build is not this source: rebuild the module and commit the build beside it.'
        );
        // And the build, whose bytes are what a browser runs, names this theme's own panel.
        $build = $this->build();
        $this->assertStringContainsString('theme_dimensions/categoryhero_contrast', $build);
        $this->assertStringContainsString('categoryhero_contrast_suggest_heading', $build);
        // The port's credit survives in the header comment; what must not survive is a
        // dependency - a template, a string or a module of the plugin it was ported from.
        $this->assertStringNotContainsString('local_dimensions/', $build);
    }

    /**
     * The module and the server accept and refuse exactly the same spellings of a colour.
     *
     * The fixture's pairs measure the maths; this measures the vocabulary, which they cannot,
     * because every pair is written the one way both sides accept. The module's HEXRE is read out
     * of the source and run over the fixture's spellings table, and branding::hex() over the same
     * table: a hash made optional on one side alone is a panel calling a value invalid over a
     * field that saves it, or a panel grading a pair the form is about to refuse.
     */
    public function test_the_two_implementations_accept_the_same_spellings(): void {
        $found = preg_match('~^const HEXRE = /(.+)/([a-z]*);$~m', $this->source(), $matches);
        $this->assertSame(1, $found, 'The module no longer spells its hex pattern the way this test reads it.');
        $pattern = '~' . str_replace('~', '\~', $matches[1]) . '~' . $matches[2];

        $path = $this->plugin_root() . '/tests/fixtures/contrast_pairs.json';
        $fixture = json_decode(file_get_contents($path), true);
        $this->assertIsArray($fixture['spellings'] ?? null, 'The shared fixture carries the spellings table.');
        foreach ($fixture['spellings'] as $case) {
            $value = trim((string) $case['value']);
            $module = preg_match($pattern, $value) === 1;
            $server = branding::hex((string) $case['value']) !== null;
            $this->assertSame(
                (bool) $case['accepted'],
                $module,
                'The module no longer agrees with the fixture about "' . $case['value'] . '": ' . $case['why']
            );
            $this->assertSame(
                $module,
                $server,
                'The panel and the server disagree about "' . $case['value'] . '", which the manager meets as a '
                    . 'field that saves what the panel called invalid, or the reverse.'
            );
        }
    }

    /**
     * Every control the module adds says what it is: two named swatches and one named group.
     *
     * The two hex fields carry the form's own labels; the swatches beside them are the only
     * controls on the page whose name the form does not give, so the module has to. One shared
     * "Colour picker" would put two identically named controls in the tab order with nothing to
     * say which paints the band and which the text.
     */
    public function test_the_controls_the_module_adds_are_named(): void {
        $source = $this->source();
        $found = preg_match_all("~^\\s*decorate\\((\\w+), (labels\\.\\w+), '(\\w+)'\\);\$~m", $source, $matches);
        $this->assertSame(2, $found, 'The module decorates exactly the two colour fields.');
        $this->assertCount(
            2,
            array_unique($matches[2]),
            'Both swatches are given the same accessible name: ' . implode(', ', $matches[2])
        );
        /* And each field paints its OWN half of the pair preview. One property for both is a
           band that takes the ink colour as its background the moment either field is typed. */
        $this->assertSame(
            ['backgroundColor', 'color'],
            $matches[3],
            'The two colour fields no longer paint one half each of the preview.'
        );
        foreach ($matches[2] as $label) {
            $name = substr($label, strlen('labels.'));
            $this->assertMatchesRegularExpression(
                '~^\s*' . preg_quote($name, '~') . ": '(categoryhero_colourpicker_\\w+)',\$~m",
                $source,
                $label . ' is not fetched from a string of its own.'
            );
        }
        foreach (['categoryhero_colourpicker_bg', 'categoryhero_colourpicker_ink'] as $key) {
            $this->assertStringContainsString("'" . $key . "'", $source, 'The module asks for ' . $key . '.');
            $this->assertTrue(
                get_string_manager()->string_exists($key, 'theme_dimensions'),
                $key . ' is asked for and does not exist.'
            );
        }
    }
}
