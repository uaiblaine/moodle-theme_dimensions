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
 * Theme Dimensions - Tests for the landing page summary's cut
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what Behat cannot see of amd/src/landingsummary.js and the markup it drives.
 *
 * The Behat scenario proves a five-paragraph summary opens cut and a two-paragraph one does not; these hold the rest:
 * the count is three, a <br> ends a paragraph as a block does, the page always opens cut and stores nothing, and the
 * toggle is hidden until the module finds something under the cut, so a page without JavaScript reads the whole text.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class landingsummary_source_test extends \basic_testcase {
    /**
     * The module's source, comments removed.
     *
     * @return string
     */
    private function source(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/amd/src/landingsummary.js');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Three paragraphs, ended by a leaf block or a line break, and cut only when text is left under the third.
     *
     * @return void
     */
    public function test_the_cut_falls_after_the_third_paragraph_end(): void {
        $js = $this->source();

        $this->assertStringContainsString('export const PARAGRAPHS = 3;', $js);
        $this->assertMatchesRegularExpression("~const ENDS = '[^']*\\bp\\b[^']*\\bli\\b[^']*\\bbr';~", $js);
        $this->assertStringContainsString("if (element.tagName !== 'BR' && element.querySelector(ENDS)) {", $js);
        $this->assertStringContainsString('if (ends.length < PARAGRAPHS) {', $js);
        $this->assertStringContainsString('const cut = ends[PARAGRAPHS - 1];', $js);
        $this->assertStringContainsString('return text.scrollHeight - cut > slack ? cut : null;', $js);

        // The slack is the stylesheet's fade under the cut, in the same rem.
        $this->assertStringContainsString('const SLACK_REM = 3;', $js);
        $scss = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/landing.scss');
        $this->assertStringContainsString('max-height: calc(var(--dim-landing-summary-cut) + 3rem);', $scss);
    }

    /**
     * Every load opens cut, and nothing of the choice is written anywhere.
     *
     * @return void
     */
    public function test_it_always_opens_cut_and_stores_nothing(): void {
        $js = $this->source();

        $this->assertStringContainsString('let expanded = false;', $js);
        $this->assertStringContainsString('root.classList.toggle(CUT_CLASS, !expanded);', $js);
        foreach (['setUserPreference', 'core_user/repository', 'localStorage', 'sessionStorage', 'core/ajax'] as $store) {
            $this->assertStringNotContainsString($store, $js, "The summary's state must not be stored ({$store}).");
        }
    }

    /**
     * The toggle shows only with something under the cut, and its state is told to assistive technology.
     *
     * @return void
     */
    public function test_the_toggle_shows_only_over_a_cut_and_says_its_state(): void {
        $js = $this->source();

        $this->assertMatchesRegularExpression(
            '~if \(cut === null\) \{\s*root\.classList\.remove\(CUT_CLASS\);\s*toggle\.hidden = true;~',
            $js
        );
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');", $js);

        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/landing_page.mustache');
        $found = preg_match('~<button[^>]*data-region="dimensions-landing-summarytoggle"[^>]*>~s', $template, $matches);
        $this->assertSame(1, $found);
        $this->assertStringContainsString(' hidden>', $matches[0], 'Without JavaScript the toggle stays hidden.');
        $this->assertStringContainsString('aria-expanded="false"', $matches[0]);
        $this->assertStringContainsString('aria-controls="dimensions-landing-summarytext"', $matches[0]);
        $this->assertStringContainsString("require(['theme_dimensions/landingsummary']", $template);
    }
}
