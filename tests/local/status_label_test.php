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
 * Theme Dimensions - The theme's static status labels, held to the fleet Design System's form
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;
use theme_dimensions\local\category\branding;

/**
 * The theme's static status labels, pinned by reading their sheets.
 *
 * A status label (moodle-dev/docs/design-system/components/status-label.md) states something about an item in a
 * tone: a 6px rectangle, never a pill, at 0.75rem, with a 1px edge in the tone's edge, which is never the label's own
 * ground. A label's base rule draws the geometry and its default tone; a tone rule re-points the edge, the ground and
 * the ink together. Where a tone had no edge of its own the sheet derives one, the ink mixed into the ground at 30%
 * (the theme's rule for a state's edge, post.scss), measured here against the ground at the theme's floor for an edge
 * (family_tokens_test::EDGE_FLOOR). The calendar's current step and the landing page's current step, whose edges are
 * compiled from Sass functions, are measured where their sheets are compiled: calendar_scss_test and landing_scss_test.
 *
 * Each rule is found by its exact text, indentation included, and must occur once: a second copy of a selector at
 * another depth would otherwise answer for it.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class status_label_test extends \basic_testcase {
    /** @var float The least contrast a 1px edge keeps against its own ground, as family_tokens_test::EDGE_FLOOR. */
    private const EDGE_FLOOR = 1.25;

    /** @var array The radius values of the 6px step, as the sheets spell them. */
    private const SIX_PX = ['var(--dim-ds-radius-sm)', 'var(--dim-ds-radius-sm, 6px)', 'var(--dim-ch-radius-sm)', '6px'];

    /** @var array Every label's base rule: sheet => the rule's opening text, from the start of its line. */
    private const LABELS = [
        'competency.scss' => [
            "\n.dim-comp-pill {",
            "\nbody.path-admin-tool-lp [data-region=\"competency-summary\"] .badge.bg-success,\n"
                . "body.path-admin-tool-lp [data-region=\"competency-summary\"] .badge.bg-danger,\n"
                . "body.path-admin-tool-lp [data-region=\"coursecompetencies\"] .badge.bg-success,\n"
                . "body.path-admin-tool-lp [data-region=\"coursecompetencies\"] .badge.bg-danger,\n"
                . "body.path-report-competency [data-region=\"competency-summary\"] .badge.bg-success,\n"
                . "body.path-report-competency [data-region=\"competency-summary\"] .badge.bg-danger {",
        ],
        'coursenotice.scss' => [
            "\n.dim-coursenotice .dim-coursenotice__chip {",
        ],
        'calendar.scss' => [
            "\n    .dim-cal__kind {",
            "\n    .dim-cal__trailstate {",
            "\n    .dim-cal__overlapmark {",
        ],
        'landing.scss' => [
            "\n    .dimensions-landing__stephours {",
            "\n    .dimensions-landing__stepstate {",
            "\n    .dimensions-landing__urgent {",
        ],
        'categoryhero.scss' => [
            "\nbody.dim-categoryhero .dim-contrast__standard {",
            "\nbody.dim-categoryhero .dim-contrast__verdict {",
        ],
        'readingbar.scss' => [
            "\n.navbar.fixed-top .dimensions-ctxbar-status {",
        ],
    ];

    /** @var array Every tone rule that paints a label's ground: sheet => the rule's opening text. */
    private const TONES = [
        'competency.scss' => [
            "\n.dim-comp-pill--proficient {",
            "\n.dim-comp-pill--notproficient {",
            "\n.dim-comp-pill--rated {",
            "\n.dim-comp-pill--status {",
        ],
        'coursenotice.scss' => [
            "\n.dim-coursenotice .dim-coursenotice__chip--ok {",
            "\n.dim-coursenotice .dim-coursenotice__chip--warn {",
            "\n.dim-coursenotice .dim-coursenotice__chip--info {",
        ],
        'calendar.scss' => [
            "\n    .dim-cal__trailpop .dim-cal__trailseal {",
        ],
        'landing.scss' => [
            "\n    .dimensions-landing__step--done .dimensions-landing__stepstate {",
            "\n    .dimensions-landing__step--current .dimensions-landing__stepstate {",
            "\n    .dimensions-landing__step--late .dimensions-landing__stepstate {",
            "\n    .dimensions-landing__step--locked .dimensions-landing__stepstate {",
        ],
        'categoryhero.scss' => [
            "\nbody.dim-categoryhero .dim-contrast .is-success {",
            "\nbody.dim-categoryhero .dim-contrast .is-warning {",
            "\nbody.dim-categoryhero .dim-contrast .is-danger {",
            "\nbody.dim-categoryhero .dim-contrast .is-muted {",
        ],
        'readingbar.scss' => [
            "\n    &[data-state=\"complete\"] {",
        ],
    ];

    /**
     * A stylesheet of the theme with its comments removed.
     *
     * @param string $file The file name under scss/.
     * @return string The source.
     */
    private function stylesheet(string $file): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/' . $file);
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|[ \t])//[^\n]*~m', '$1', $source);
    }

    /**
     * The declarations a rule makes itself, its nested rules left out.
     *
     * @param string $scss The stylesheet, comments removed.
     * @param string $opening The rule's opening text, from the start of its line to its brace.
     * @return array Property => value.
     */
    private function own_declarations(string $scss, string $opening): array {
        $this->assertSame(1, substr_count($scss, $opening), 'Exactly one rule opens with: ' . trim($opening));
        $start = strpos($scss, $opening) + strlen($opening);
        $depth = 1;
        $body = '';
        for ($i = $start; $depth > 0 && $i < strlen($scss); $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $depth++;
            } else if ($char === '}') {
                $depth--;
            }
            if ($depth > 0) {
                $body .= $char;
            }
        }
        do {
            $body = (string) preg_replace('~[^;{}]*\{[^{}]*\}~', '', $body, -1, $nested);
        } while ($nested > 0);
        $declarations = [];
        foreach (explode(';', $body) as $statement) {
            if (str_contains($statement, ':')) {
                [$property, $value] = explode(':', $statement, 2);
                $declarations[trim($property)] = trim((string) preg_replace('~\s+~', ' ', $value));
            }
        }

        return $declarations;
    }

    /**
     * The custom properties a top-level block declares, the block ending at the first brace that closes a line.
     *
     * @param string $scss The stylesheet, comments removed.
     * @param string $opening The block's opening text.
     * @return array Name => value.
     */
    private function tokens(string $scss, string $opening): array {
        $this->assertSame(1, substr_count($scss, $opening), 'Exactly one block opens with: ' . trim($opening));
        $start = strpos($scss, $opening) + strlen($opening);
        $block = substr($scss, $start, strpos($scss, "\n}", $start) - $start);
        preg_match_all('~(--[a-z0-9-]+):\s*([^;]+);~', $block, $found, PREG_SET_ORDER);

        return array_column($found, 2, 1);
    }

    /**
     * The Sass colours post.scss writes: the palette's primitives and the dark pairs.
     *
     * @return array Variable name with its $ => #rrggbb.
     */
    private function sass_colours(): array {
        $post = (string) file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        preg_match_all('~^(\$dim-(?:ds|dark)-[a-z0-9-]+): (#[0-9a-f]{6});~m', $post, $found, PREG_SET_ORDER);
        $this->assertGreaterThan(30, count($found), 'Precondition: post.scss writes its colours as Sass variables.');

        return array_column($found, 2, 1);
    }

    /**
     * A colour value as #rrggbb: a Sass colour, a literal fallback of a var(), or Sass's mix() of two Sass colours.
     *
     * @param string $value The declared value.
     * @param array $colours The Sass colours, from sass_colours().
     * @return array ['hex' => string, 'ink' => ?string, 'ground' => ?string], the last two set for a mix only.
     */
    private function colour(string $value, array $colours): array {
        if (preg_match('~^#\{mix\((\$[a-z0-9-]+), (\$[a-z0-9-]+), (\d+)%\)\}$~', $value, $mix)) {
            [$ink, $ground, $weight] = [$colours[$mix[1]], $colours[$mix[2]], (int) $mix[3] / 100];
            $hex = '#';
            foreach ([1, 3, 5] as $at) {
                $hex .= sprintf('%02x', (int) round(
                    hexdec(substr($ink, $at, 2)) * $weight + hexdec(substr($ground, $at, 2)) * (1 - $weight)
                ));
            }
            return ['hex' => $hex, 'ink' => $ink, 'ground' => $ground];
        }
        if (preg_match('~^#\{(\$[a-z0-9-]+)\}$~', $value, $variable)) {
            return ['hex' => $colours[$variable[1]], 'ink' => null, 'ground' => null];
        }
        $this->assertSame(1, preg_match('~(#[0-9a-f]{6})\)$~', $value, $fallback), "{$value} ends in a literal fallback.");

        return ['hex' => $fallback[1], 'ink' => null, 'ground' => null];
    }

    /**
     * Every label's base rule is a 6px rectangle at 0.75rem with a 1px border that is not its ground.
     *
     * Changes that must make it fail: a label's radius back on the pill token; its border dropped or 2px; its size
     * off 0.75rem; its border read from the token its background reads.
     *
     * @return void
     */
    public function test_every_status_label_is_a_six_pixel_rectangle_with_an_edge(): void {
        $checked = 0;
        foreach (self::LABELS as $file => $openings) {
            $scss = $this->stylesheet($file);
            foreach ($openings as $opening) {
                $label = $file . ' ' . trim(strtok(trim($opening), "\n"));
                $declarations = $this->own_declarations($scss, $opening);
                $this->assertContains($declarations['border-radius'] ?? '', self::SIX_PX, "{$label}: the 6px step, no pill.");
                $this->assertSame(
                    1,
                    preg_match('~^1px solid (\S.*)$~', $declarations['border'] ?? '', $edge),
                    "{$label}: a 1px edge."
                );
                $this->assertNotSame('transparent', $edge[1], "{$label}: an edge that shows.");
                $ground = $declarations['background'] ?? $declarations['background-color'] ?? null;
                $this->assertNotSame($ground, $edge[1], "{$label}: the edge is not the ground.");
                $this->assertContains($declarations['font-size'] ?? '', ['.75rem', '0.75rem'], "{$label}: 0.75rem.");
                $this->assertSame('600', $declarations['font-weight'] ?? '', "{$label}: 600.");
                $checked++;
            }
        }
        $this->assertSame(12, $checked, 'Control: every label in scope was read.');
    }

    /**
     * Every tone re-points the edge with the ground, never to the ground.
     *
     * Core's proficiency badges keep Bootstrap's own !important fill, so their tone rules re-point the edge alone: the
     * success and danger families' edges.
     *
     * Changes that must make it fail: a tone that sets its ground and keeps the base edge; a tone whose edge reads the
     * token its ground reads; a badge's edge read from the other state's family.
     *
     * @return void
     */
    public function test_every_tone_edges_its_label_apart_from_its_ground(): void {
        $checked = 0;
        foreach (self::TONES as $file => $openings) {
            $scss = $this->stylesheet($file);
            foreach ($openings as $opening) {
                $label = $file . ' ' . trim($opening);
                $declarations = $this->own_declarations($scss, $opening);
                $ground = $declarations['background'] ?? $declarations['background-color'] ?? null;
                $this->assertNotNull($ground, "{$label}: a tone paints a ground.");
                $this->assertArrayHasKey('color', $declarations, "{$label}: a ground states its ink.");
                $this->assertArrayHasKey('border-color', $declarations, "{$label}: a tone re-points the edge.");
                $this->assertNotSame($ground, $declarations['border-color'], "{$label}: the edge is not the ground.");
                $checked++;
            }
        }
        $this->assertSame(17, $checked, 'Control: every tone in scope was read.');

        $scss = $this->stylesheet('competency.scss');
        foreach (['success', 'danger'] as $state) {
            $opening = "\nbody.path-admin-tool-lp [data-region=\"competency-summary\"] .badge.bg-{$state},\n"
                . "body.path-admin-tool-lp [data-region=\"coursecompetencies\"] .badge.bg-{$state},\n"
                . "body.path-report-competency [data-region=\"competency-summary\"] .badge.bg-{$state} {";
            $this->assertSame(
                ['border-color' => "var(--dimensions-{$state}-edge)"],
                $this->own_declarations($scss, $opening),
                "Core's {$state} badge is edged in its own family's edge."
            );
        }
    }

    /**
     * The course notice's chip edges are each tone's ink mixed into its ground, and show against it in both modes.
     *
     * Each edge is darker than its ground on the light page and lighter on the dark one, at the theme's floor for an
     * edge. The neutral triple is Bootstrap's, which flips with the colour mode on its own: its light values, the
     * fallbacks, are measured; so is the contrast panel's neutral tone, whose edge was the page's line (1.10:1 on its
     * ground, no edge at all).
     *
     * Changes that must make it fail: a chip's edge mixed at 5%, or from another tone's pair; a dark edge missing; the
     * contrast panel's neutral edge back on the page's line.
     *
     * @return void
     */
    public function test_the_derived_edges_show_against_their_grounds(): void {
        $colours = $this->sass_colours();
        $scss = $this->stylesheet('coursenotice.scss');
        $modes = [
            'light' => $this->tokens($scss, "\n.dim-coursenotice {"),
            'dark' => $this->tokens($scss, "\n:root[data-bs-theme=\"dark\"] .dim-coursenotice {"),
        ];
        $measured = 0;
        foreach ($modes as $mode => $tokens) {
            foreach (['ok', 'warn', 'info'] as $tone) {
                $label = "The {$mode} {$tone} chip's edge";
                $this->assertArrayHasKey("--dim-notice-{$tone}-edge", $tokens, "{$label} is declared.");
                $ground = $this->colour($tokens["--dim-notice-{$tone}-bg"], $colours)['hex'];
                $ink = $this->colour($tokens["--dim-notice-{$tone}-fg"], $colours)['hex'];
                $edge = $this->colour($tokens["--dim-notice-{$tone}-edge"], $colours);
                $this->assertSame([$ink, $ground], [$edge['ink'], $edge['ground']], "{$label} is mixed from its own pair.");
                $contrast = branding::contrast($edge['hex'], $ground);
                $this->assertGreaterThanOrEqual(self::EDGE_FLOOR, $contrast, "{$label} ({$edge['hex']}) shows on {$ground}.");
                $lighter = branding::contrast($edge['hex'], '#ffffff') < branding::contrast($ground, '#ffffff');
                $this->assertSame($mode === 'dark', $lighter, "{$label} is darker in light and lighter in dark.");
                $measured++;
            }
        }
        $this->assertSame(6, $measured);

        $neutral = $modes['light'];
        $this->assertGreaterThanOrEqual(self::EDGE_FLOOR, branding::contrast(
            $this->colour($neutral['--dim-notice-neutral-edge'], $colours)['hex'],
            $this->colour($neutral['--dim-notice-neutral-bg'], $colours)['hex']
        ), "The notice's neutral edge shows on its ground.");

        $hero = $this->tokens($this->stylesheet('categoryhero.scss'), "\nbody.dim-categoryhero {");
        $this->assertGreaterThanOrEqual(self::EDGE_FLOOR, branding::contrast(
            $this->colour($hero['--dim-ch-neutral-edge'], $colours)['hex'],
            $this->colour($hero['--dim-ch-neutral-tint'], $colours)['hex']
        ), "The contrast panel's neutral edge shows on its tint.");
    }
}
