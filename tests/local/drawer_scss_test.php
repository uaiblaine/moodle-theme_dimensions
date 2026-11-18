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
 * Theme Dimensions - Tests for the mobile drawer's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing else in the pipeline reads about scss/drawer.scss: where its rules reach, which
 * colours they take, and that the theme serves it at all.
 *
 * The file is concatenated into the theme's main SCSS and reaches every page of the site, so a rule
 * escaping the drawer's class would restyle pages the drawer is not on; and a literal colour would ignore
 * the colour mode and a flavour's brand colour, which the family tokens carry.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class drawer_scss_test extends \basic_testcase {
    /**
     * @var string[] The only top-level selector the file may have: the drawer. Its dark mode is the family tokens' own,
     *      which every token of the drawer reads.
     */
    private const SCOPES = ['.dimensions-drawer'];

    /**
     * The stylesheet, comments removed.
     *
     * @return string
     */
    private function stylesheet(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/drawer.scss');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Every selector at the root of the file.
     *
     * @param string $scss The stylesheet, comments removed.
     * @return array The selectors, whitespace collapsed.
     */
    private function top_level_selectors(string $scss): array {
        $selectors = [];
        $depth = 0;
        $buffer = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                if ($depth === 0) {
                    $selectors[] = trim((string) preg_replace('~\s+~', ' ', $buffer));
                }
                $depth++;
                $buffer = '';
            } else if ($char === '}') {
                $depth--;
                $buffer = '';
            } else if ($char === ';') {
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }

        return $selectors;
    }

    /**
     * Every rule is nested under the drawer's own class.
     *
     * @return void
     */
    public function test_every_rule_is_scoped_to_the_drawer(): void {
        $selectors = $this->top_level_selectors($this->stylesheet());

        $this->assertNotEmpty($selectors);
        $this->assertSame(self::SCOPES, array_values(array_unique($selectors)));
    }

    /**
     * No literal colour: every colour is a token, so the colour mode and a flavour move it.
     *
     * @return void
     */
    public function test_no_literal_colour(): void {
        $scss = $this->stylesheet();

        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,8}\b~', $scss);
        $this->assertDoesNotMatchRegularExpression('~\b(rgba?|hsla?)\(~', $scss);
        // The colour names alone: white-space is a property.
        $this->assertDoesNotMatchRegularExpression('~(?<![-\w])(white|black)(?![-\w])~', $scss);
        $this->assertStringContainsString('var(--dimensions-surface, var(--bs-body-bg))', $scss, 'Control: tokens are read.');
    }

    /**
     * The motion exists only for a reader who did not ask for less, and the ring is the theme's.
     *
     * @return void
     */
    public function test_motion_is_reduced_motion_aware_and_the_ring_is_the_themes(): void {
        $scss = $this->stylesheet();

        $media = strpos($scss, '@media (prefers-reduced-motion: no-preference)');
        $this->assertNotFalse($media);
        $this->assertGreaterThan($media, strpos($scss, 'transition:'), 'No transition before the media query.');
        $this->assertGreaterThan($media, strpos($scss, 'animation:'));
        $this->assertStringContainsString('outline: var(--dim-ds-focus-ring-width) solid var(--dim-drawer-ring);', $scss);
        $this->assertStringContainsString('outline-offset: 2px;', $scss);
        $this->assertStringContainsString('--dim-drawer-ring: var(--dim-ds-focus-ring);', $scss);
    }

    /**
     * A hidden panel is hidden and moved away; the card is shown only once the module arms the drawer.
     *
     * @return void
     */
    public function test_the_layers_and_the_card_follow_the_module(): void {
        $scss = $this->stylesheet();

        $this->assertMatchesRegularExpression(
            '~&\.dimensions-drawer-js \{.*?\.dimensions-drawer-panel\[data-state="before"\],\s*'
                . '\.dimensions-drawer-panel\[data-state="after"\] \{[^}]*visibility: hidden;~s',
            $scss
        );
        $this->assertMatchesRegularExpression('~\n    \.dimensions-drawer-resume \{\s*display: none;~', $scss);
        $this->assertMatchesRegularExpression(
            '~&\.dimensions-drawer-js \{.*?\.dimensions-drawer-resume \{\s*display: block;~s',
            $scss
        );
    }

    /**
     * The body of one rule nested one level under the drawer, by its exact selector.
     *
     * @param string $scss The stylesheet, comments removed.
     * @param string $selector The selector.
     * @return string The declarations up to the rule's first nested block or its end.
     */
    private function rule(string $scss, string $selector): string {
        $found = preg_match('~\n    ' . preg_quote($selector, '~') . ' \{([^}]*)~', $scss, $matches);
        $this->assertSame(1, $found, $selector);

        return $matches[1];
    }

    /**
     * A row is the side layer's (D69): 40px, 44px under a coarse pointer, at 0.875rem, 12px between its parts, padding
     * 4px 8px, its glyph in the muted ink; the "Go to" link of a sub-panel stays a 44px target.
     *
     * @return void
     */
    public function test_a_row_is_the_side_layers_row(): void {
        $scss = $this->stylesheet();

        $row = $this->rule($scss, '.dimensions-drawer-item');
        $this->assertStringContainsString('min-height: 2.5rem;', $row);
        $this->assertStringContainsString('font-size: .875rem;', $row);
        $this->assertStringContainsString('gap: var(--dim-ds-space-sm);', $row);
        $this->assertStringContainsString('padding: var(--dim-ds-space-xxs) var(--dim-ds-space-xs);', $row);
        $this->assertMatchesRegularExpression(
            '~\n    \.dimensions-drawer-item \{[^}]*@media \(pointer: coarse\) \{\s*min-height: 2\.75rem;~',
            $scss
        );
        $this->assertStringContainsString('color: var(--dim-drawer-muted);', $this->rule($scss, '.dimensions-drawer-icon'));
        $this->assertStringContainsString('--dim-drawer-muted: var(--dimensions-ink-muted, var(--bs-secondary-color));', $scss);
        $this->assertStringContainsString('min-height: 2.75rem;', $this->rule($scss, '.dimensions-drawer-openparent'));
    }

    /**
     * The current row is the brand's tint and ink at 600, its glyph in the ink, with no 3px mark on its edge (D72).
     *
     * @return void
     */
    public function test_the_current_row_is_tinted_without_a_mark(): void {
        $scss = $this->stylesheet();

        $current = $this->rule($scss, '.dimensions-drawer-item.is-current');
        $this->assertStringContainsString('background-color: var(--dim-drawer-current);', $current);
        $this->assertStringContainsString('color: var(--dim-drawer-current-ink);', $current);
        $this->assertStringContainsString('font-weight: 600;', $current);
        $this->assertStringContainsString(
            '--dim-drawer-current: var(--dimensions-brand-tint, var(--bs-primary-bg-subtle));',
            $scss
        );
        $this->assertStringContainsString(
            '--dim-drawer-current-ink: var(--dimensions-brand-ink, var(--bs-primary-text-emphasis));',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~\.dimensions-drawer-item\.is-current \{[^}]*\.dimensions-drawer-icon,[^{]*\{'
                . '\s*color: var\(--dim-drawer-current-ink\);~',
            $scss
        );
        // The mark was an inset shadow on the row; the resume card's hover has an elevation of its own.
        $this->assertStringNotContainsString('box-shadow', $current, 'No inset mark, in either colour mode.');
        $this->assertStringNotContainsString('--dim-drawer-mark', $scss);
    }

    /**
     * A sub-panel's head is the side layer's sub-view header: a 32px back icon button in the muted ink and the 1rem / 600
     * title, a line under them; no close button of its own, Boost's band carries one.
     *
     * @return void
     */
    public function test_a_sub_panels_head_is_the_sub_view_header(): void {
        $scss = $this->stylesheet();

        $head = $this->rule($scss, '.dimensions-drawer-sub');
        $this->assertStringContainsString('display: flex;', $head);
        $this->assertStringContainsString('gap: var(--dim-ds-space-xs);', $head);
        $this->assertStringContainsString('padding: var(--dim-ds-space-sm) var(--dim-ds-space-xs);', $head);
        $this->assertStringContainsString('border-bottom: 1px solid var(--dim-drawer-line);', $head);

        $back = $this->rule($scss, '.dimensions-drawer-back');
        $this->assertStringContainsString('width: 2rem;', $back);
        $this->assertStringContainsString('height: 2rem;', $back);
        $this->assertStringContainsString('color: var(--dim-drawer-muted);', $back);

        $title = $this->rule($scss, '.dimensions-drawer-title');
        $this->assertStringContainsString('font-size: 1rem;', $title);
        $this->assertStringContainsString('font-weight: 600;', $title);
    }

    /**
     * The drawer draws nothing about the person (D72): no identity, no colour mode switch.
     *
     * @return void
     */
    public function test_nothing_about_the_person_is_styled(): void {
        $scss = $this->stylesheet();
        $this->assertStringContainsString('.dimensions-drawer-resume', $scss, 'Control: the card is styled.');
        $gone = ['.dimensions-drawer-who', '.dimensions-drawer-name', '.dimensions-drawer-role', '.dimensions-drawer-mode'];
        foreach ($gone as $selector) {
            $this->assertStringNotContainsString($selector, $scss, $selector);
        }
    }

    /**
     * The theme serves the file: lib.php concatenates it into the main SCSS.
     *
     * @return void
     */
    public function test_the_theme_serves_the_file(): void {
        $this->assertStringContainsString(
            "file_get_contents(\$CFG->dirroot . '/theme/dimensions/scss/drawer.scss')",
            (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php')
        );
    }

    /**
     * The whole body of the first rule nested one level under the drawer with this exact selector, its nested blocks
     * included: the rule that draws the part, which the focus ring's shared rule at the foot follows.
     *
     * @param string $scss The stylesheet, comments removed.
     * @param string $selector The selector, as the line of the rule spells it.
     * @return string The body, between its braces.
     */
    private function block(string $scss, string $selector): string {
        $needle = "\n    " . $selector . ' {';
        $at = strpos($scss, $needle);
        $this->assertNotFalse($at, 'A rule ' . $selector);
        $start = $at + strlen($needle);
        $depth = 1;
        $length = strlen($scss);
        for ($i = $start; $i < $length; $i++) {
            if ($scss[$i] === '{') {
                $depth++;
            } else if ($scss[$i] === '}' && --$depth === 0) {
                return substr($scss, $start, $i - $start);
            }
        }
        $this->fail('The rule ' . $selector . ' never closes.');
    }

    /**
     * A row answers with the family's hover (hover.md, D103): the hover ground and the brand's ink, the leading icon
     * moved 2px and a sub-panel's chevron 3px towards the end, the pressed ground on :active; the transitions name
     * background-color, color and transform, never all, at the base step at rest and the fast step hovered.
     *
     * @return void
     */
    public function test_a_row_answers_with_the_familys_hover(): void {
        $scss = $this->stylesheet();
        $row = $this->block($scss, '.dimensions-drawer-item');
        $base = 'var\(--dim-drawer-motion-base\) var\(--dim-drawer-motion-ease\)';

        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: no-preference\) \{\s*transition:\s*background-color ' . $base . ',\s*color '
                . $base . ';\s*\.dimensions-drawer-icon,\s*\.dimensions-drawer-chevron \{\s*transition:\s*color ' . $base
                . ',\s*transform ' . $base . ';\s*\}\s*&:hover,\s*&:hover \.dimensions-drawer-icon,\s*'
                . '&:hover \.dimensions-drawer-chevron \{\s*transition-duration: var\(--dim-drawer-motion-fast\);~',
            $row,
            'Out at the base step, in at the fast one, on the ground, the ink and the move.'
        );
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*background-color: var\(--dim-drawer-hover\);\s*color: var\(--dim-drawer-current-ink\);~',
            $row
        );
        $this->assertMatchesRegularExpression(
            '~&:hover \.dimensions-drawer-icon \{\s*color: var\(--dim-drawer-current-ink\);\s*'
                . 'transform: translateX\(var\(--dim-drawer-nudge-icon\)\);~',
            $row
        );
        $this->assertMatchesRegularExpression(
            '~&:hover \.dimensions-drawer-chevron \{\s*transform: translateX\(var\(--dim-drawer-nudge-arrow\)\);~',
            $row
        );
        $this->assertMatchesRegularExpression('~&:active \{\s*background-color: var\(--dim-drawer-pressed\);~', $row);
        // The current page stays as it is.
        $this->assertMatchesRegularExpression(
            '~&:hover,\s*&:active \{\s*background-color: var\(--dim-drawer-current\);\s*\}\s*'
                . '&:hover \.dimensions-drawer-icon \{\s*transform: none;~',
            $this->block($scss, '.dimensions-drawer-item.is-current')
        );
        foreach (['.dimensions-drawer-back', '.dimensions-drawer-openparent'] as $control) {
            $body = $this->block($scss, $control);
            $this->assertMatchesRegularExpression('~&:hover \{\s*background-color: var\(--dim-drawer-hover\);~', $body, $control);
            $this->assertMatchesRegularExpression(
                '~&:active \{\s*background-color: var\(--dim-drawer-pressed\);~',
                $body,
                $control
            );
            $this->assertMatchesRegularExpression(
                '~&:hover \{\s*transition-duration: var\(--dim-drawer-motion-fast\);~',
                $body,
                $control . ' arrives at the fast step.'
            );
        }

        preg_match_all('~transition:\s*([^;]+);~', $scss, $transitions);
        $this->assertGreaterThan(5, count($transitions[1]), 'Precondition: the transitions were read.');
        foreach ($transitions[1] as $transition) {
            $this->assertDoesNotMatchRegularExpression('~\ball\b~', $transition, 'A transition names its properties.');
        }
        $this->assertStringContainsString('--dim-drawer-hover: var(--dimensions-hover, var(--bs-tertiary-bg));', $scss);
        $this->assertStringContainsString('--dim-drawer-pressed: var(--dimensions-pressed, var(--bs-secondary-bg));', $scss);
        $this->assertStringNotContainsString('surface-alt', $scss, 'A hover is never the tertiary ground: it may be the surface.');
        $this->assertStringContainsString('--dim-drawer-nudge-icon: .125rem;', $scss);
        $this->assertStringContainsString('--dim-drawer-nudge-arrow: .1875rem;', $scss);
        $this->assertMatchesRegularExpression(
            '~\n    \.dir-rtl & \{\s*--dim-drawer-nudge-icon: -\.125rem;\s*--dim-drawer-nudge-arrow: -\.1875rem;\s*\}~',
            $scss,
            'A right-to-left page moves them towards its end, the left.'
        );
    }

    /**
     * Under prefers-reduced-motion: reduce the colours change at once and nothing moves: the reduced-motion block drops
     * every transition of the hover and the moves, at the weight of the rules that move and after them.
     *
     * @return void
     */
    public function test_reduced_motion_drops_the_transitions_and_the_moves(): void {
        $scss = $this->stylesheet();
        $this->assertSame(
            1,
            preg_match('~\n    @media \(prefers-reduced-motion: reduce\) \{(.*?)\n    \}~s', $scss, $reduce, PREG_OFFSET_CAPTURE)
        );
        [$block, $at] = $reduce[1];
        $this->assertMatchesRegularExpression(
            '~\.dimensions-drawer-item,\s*\.dimensions-drawer-icon,\s*\.dimensions-drawer-chevron,\s*\.dimensions-drawer-back,\s*'
                . '\.dimensions-drawer-openparent,\s*\.dimensions-drawer-resume-link,\s*\.dimensions-drawer-resume-go \{'
                . '\s*transition: none;~',
            $block
        );
        $this->assertMatchesRegularExpression(
            '~\.dimensions-drawer-item:hover \.dimensions-drawer-icon,\s*'
                . '\.dimensions-drawer-item:hover \.dimensions-drawer-chevron,'
                . '\s*\.dimensions-drawer-resume-link:hover \.dimensions-drawer-resume-go \{\s*transform: none;~',
            $block
        );
        $this->assertGreaterThan(strrpos($scss, 'transform: translateX('), $at, 'The block comes after every move.');
    }

    /**
     * "Continue where you left off" is the sparkle ground as a card (D104, D106): the gradient from the warm stop
     * through the surface to the brand's tint inside a 1px edge of the tint at the lg radius, a 36px tile of the brand's
     * fill holding the glyph of the course's kind, the kicker in the brand's ink, the title at 1rem / 600, the detail
     * muted, an arrow at the end, and the rail
     * on the inset ground under the brand's fill; hovered, its edge and its elevation answer and its arrow moves.
     *
     * @return void
     */
    public function test_the_resume_card_is_the_sparkle_ground(): void {
        $scss = $this->stylesheet();
        $card = $this->block($scss, '.dimensions-drawer-resume-link');
        $this->assertMatchesRegularExpression(
            '~background:\s*linear-gradient\(\s*135deg,\s*var\(--dim-drawer-ground-warm\) 0%,\s*var\(--dim-drawer-surface\) 60%,'
                . '\s*var\(--dim-drawer-tint\) 100%\s*\);~',
            $card
        );
        $this->assertStringContainsString('border: 1px solid var(--dim-drawer-tint);', $card);
        $this->assertStringContainsString('border-radius: var(--dimensions-radius-lg, var(--dim-ds-radius-lg));', $card);
        $this->assertStringContainsString('overflow: hidden;', $card);
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*border-color: var\(--dim-drawer-brand\);\s*box-shadow: var\(--dim-drawer-elevation-sm\);\s*\}~',
            $card
        );
        $this->assertMatchesRegularExpression(
            '~&:hover \.dimensions-drawer-resume-go \{\s*transform: translateX\(var\(--dim-drawer-nudge-arrow\)\);~',
            $card
        );
        $this->assertStringContainsString('--dim-drawer-tint: var(--dimensions-brand-tint, var(--bs-primary-bg-subtle));', $scss);
        $this->assertStringContainsString('--dim-drawer-ground-warm: var(--dimensions-ground-warm,', $scss);
        $this->assertStringContainsString('--dim-drawer-spark: var(--dimensions-ground-spark,', $scss);

        $tile = $this->rule($scss, '.dimensions-drawer-resume-tile');
        $this->assertMatchesRegularExpression('~width: 2\.25rem;\s*height: 2\.25rem;~', $tile);
        $this->assertStringContainsString('background-color: var(--dim-drawer-brand);', $tile);
        $this->assertStringContainsString('color: var(--dim-drawer-on-brand);', $tile);
        $kicker = $this->rule($scss, '.dimensions-drawer-resume-kicker');
        $this->assertStringContainsString('color: var(--dim-drawer-glyph);', $kicker);
        $this->assertMatchesRegularExpression(
            '~font-size: 1rem;\s*font-weight: 600;~',
            $this->rule($scss, '.dimensions-drawer-resume-title')
        );
        $this->assertMatchesRegularExpression(
            '~\n    \.dimensions-drawer-resume-sub \{\s*color: var\(--dim-drawer-muted\);~',
            $scss
        );
        $this->assertStringContainsString('color: var(--dim-drawer-glyph);', $this->rule($scss, '.dimensions-drawer-resume-go'));
        $rail = $this->rule($scss, '.dimensions-drawer-resume-rail');
        $this->assertStringContainsString('height: .25rem;', $rail);
        $this->assertStringContainsString('background-color: var(--dim-drawer-inset);', $rail);
        $this->assertStringContainsString(
            'background-color: var(--dim-drawer-brand);',
            $this->rule($scss, '.dimensions-drawer-resume-bar')
        );
        $spark = $this->rule($scss, '.dimensions-drawer-spark');
        $this->assertMatchesRegularExpression('~position: absolute;\s*opacity: \.35;~', $spark);
        $this->assertMatchesRegularExpression('~color: var\(--dim-drawer-spark\);\s*pointer-events: none;~', $spark);
        $this->assertDoesNotMatchRegularExpression('~svg|url\(|data:~i', $scss, 'The sheet carries no SVG data.');

        // The template: the sparkles first, hidden and never focused, the tile and the arrow hidden, and every hook the
        // module fills.
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/drawer_nav.mustache');
        preg_match_all('~<svg\b[^>]*>~', $template, $svgs);
        $this->assertCount(3, $svgs[0], 'Three sparkles.');
        foreach (['a', 'b', 'c'] as $i => $place) {
            $this->assertMatchesRegularExpression(
                '~^<svg class="dimensions-drawer-spark dimensions-drawer-spark--' . $place
                    . '"[^>]* aria-hidden="true" focusable="false">$~',
                $svgs[0][$i]
            );
        }
        $this->assertMatchesRegularExpression(
            '~<a class="dimensions-drawer-resume-link" data-region="dimensions-drawer-resume-link">\s*'
                . '(<svg [^>]*><path [^>]*/></svg>\s*){3}<span class="dimensions-drawer-resume-tile" aria-hidden="true">~',
            $template
        );
        $this->assertStringContainsString(
            '<i class="icon fa fa-arrow-right fa-fw dimensions-drawer-resume-go" aria-hidden="true"></i>',
            $template
        );
        foreach (['resume', 'resume-link', 'resume-title', 'resume-sub', 'resume-bar'] as $region) {
            $this->assertSame(1, substr_count($template, 'data-region="dimensions-drawer-' . $region . '"'), $region);
        }
    }

    /**
     * The drawer is a navigation landmark with a name, and keeps the id and the region Behat and the module find it by.
     *
     * @return void
     */
    public function test_the_drawer_is_a_named_navigation_landmark(): void {
        $template = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/drawer_nav.mustache');
        $this->assertMatchesRegularExpression(
            '~\n<nav class="dimensions-drawer" id="dimensions-drawer" data-region="dimensions-drawer"'
                . ' aria-label="\{\{#str\}\} drawer_nav, theme_dimensions \{\{/str\}\}">\n~',
            $template
        );
        $this->assertSame(1, substr_count($template, '<nav'), 'One landmark.');
        $this->assertMatchesRegularExpression('~\n</nav>\n\{\{#js\}\}~', $template, 'The landmark closes around the panels.');
        $this->assertStringContainsString(
            "\$string['drawer_nav'] = 'Site navigation';",
            (string) file_get_contents(dirname(__DIR__, 2) . '/lang/en/theme_dimensions.php')
        );
    }
    /**
     * The card's tile draws the glyph of the course's kind (the card's D57 glyphs): one glyph per kind is printed,
     * each hidden from assistive technology, and the card's data-kind shows its own; with no kind, or one the sheet
     * does not know, the course's glyph stands. The module passes the service's word on and names no kind.
     */
    public function test_the_resume_tile_draws_the_kinds_glyph(): void {
        $root = dirname(__DIR__, 2);
        $template = (string) file_get_contents($root . '/templates/drawer_nav.mustache');
        $glyphs = ['course' => 'fa-graduation-cap', 'event' => 'fa-calendar-day', 'trail' => 'fa-route'];
        $this->assertSame(array_values(\theme_dimensions\local\drawersummary::KINDS), array_keys($glyphs), 'Control.');
        foreach ($glyphs as $kind => $glyph) {
            $this->assertStringContainsString(
                '<i class="icon fa ' . $glyph . ' fa-fw dimensions-drawer-resume-kind dimensions-drawer-resume-kind--'
                    . $kind . '" aria-hidden="true"></i>',
                $template
            );
        }
        $this->assertSame(3, substr_count($template, 'dimensions-drawer-resume-kind '), 'One glyph per kind.');

        $scss = (string) file_get_contents($root . '/scss/drawer.scss');
        $this->assertMatchesRegularExpression(
            '~\.dimensions-drawer-resume-kind--event,\s*\.dimensions-drawer-resume-kind--trail \{\s*display: none;~',
            $scss,
            'At rest the course\'s glyph stands alone.'
        );
        foreach (['event', 'trail'] as $kind) {
            $this->assertStringContainsString(
                '.dimensions-drawer-resume[data-kind="' . $kind . '"] .dimensions-drawer-resume-kind--course',
                $scss
            );
            $this->assertStringContainsString(
                '.dimensions-drawer-resume[data-kind="' . $kind . '"] .dimensions-drawer-resume-kind--' . $kind,
                $scss
            );
        }

        $module = (string) file_get_contents($root . '/amd/src/drawer.js');
        $this->assertStringContainsString('card.dataset.kind = resume.kind;', $module);
        $this->assertDoesNotMatchRegularExpression('~fa-graduation-cap|fa-calendar-day|fa-route~', $module);
    }
}
