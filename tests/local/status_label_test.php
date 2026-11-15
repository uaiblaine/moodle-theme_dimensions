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
 * The course card's state label (.dim-badge) carries a Font Awesome icon in the label's own size, 4px from its words;
 * its edges are tokens per state, light and dark, measured here like the derived ones.
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
        'category.scss' => [
            "\n    .dim-badge {",
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
        'category.scss' => [
            "\n    .dim-badge--enrolled {",
            "\n    .dim-badge--pending {",
            "\n    .dim-badge--scheduled {",
            "\n    .dim-badge--open {",
            "\n    .dim-badge--free {",
            "\n    .dim-badge--none {",
            "\n    .dim-badge--held {",
            "\n    .dim-badge--public {",
            "\n    .dim-badge--hidden {",
            "\n    .dim-badge--waitlisted {",
            "\n    .dim-badge--suspended {",
            "\n    .dim-badge--expired {",
            "\n    .dim-badge--key {",
            "\n    .dim-badge--conditional {",
            "\n    .dim-badge--done {",
        ],
    ];

    /** @var string The course card's light token roots, which declare every state label's triple. */
    private const BADGE_ROOTS = "\n    .dim-vitrine,\n    .dim-cmodal {";

    /** @var string The opening of every dark block of the showcase's roots: the badge pairs and their edges. */
    private const BADGE_DARK_ROOTS = "\n:root[data-bs-theme=\"dark\"] body.dim-category .dim-vitrine,\n"
        . ":root[data-bs-theme=\"dark\"] body.dim-category .dim-cmodal {";

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
        preg_match_all('~^(\$dim-(?:ds|dark)-[a-z0-9-]+): (#[0-9a-f]{3}(?:[0-9a-f]{3})?);~m', $post, $found, PREG_SET_ORDER);
        $this->assertGreaterThan(30, count($found), 'Precondition: post.scss writes its colours as Sass variables.');

        return array_map([$this, 'six'], array_column($found, 2, 1));
    }

    /**
     * A hex colour in its six-digit spelling.
     *
     * @param string $hex A colour as #rgb or #rrggbb.
     * @return string The colour as #rrggbb.
     */
    private function six(string $hex): string {
        return strlen($hex) === 4 ? '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3] : $hex;
    }

    /**
     * A colour value as #rrggbb: a literal, a Sass colour, a palette token, the literal fallback of a var(), or Sass's
     * mix() of two Sass colours or literals.
     *
     * @param string $value The declared value.
     * @param array $colours The Sass colours, from sass_colours().
     * @return array ['hex' => string, 'ink' => ?string, 'ground' => ?string], the last two set for a mix only.
     */
    private function colour(string $value, array $colours): array {
        $operand = '(\$[a-z0-9-]+|#[0-9a-f]{6}|#[0-9a-f]{3})';
        if (preg_match('~^#\{mix\(' . $operand . ', ' . $operand . ', (\d+)%\)\}$~', $value, $mix)) {
            $read = fn(string $name): string => $name[0] === '$' ? $colours[$name] : $this->six($name);
            [$ink, $ground, $weight] = [$read($mix[1]), $read($mix[2]), (int) $mix[3] / 100];
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
        if (preg_match('~^#[0-9a-f]{3}(?:[0-9a-f]{3})?$~', $value)) {
            return ['hex' => $this->six($value), 'ink' => null, 'ground' => null];
        }
        if (preg_match('~^var\(--(dim-ds-[a-z0-9-]+)\)$~', $value, $palette)) {
            $this->assertArrayHasKey('$' . $palette[1], $colours, "{$value} is a palette colour.");
            return ['hex' => $colours['$' . $palette[1]], 'ink' => null, 'ground' => null];
        }
        $this->assertSame(
            1,
            preg_match('~(#[0-9a-f]{3}(?:[0-9a-f]{3})?)\)+$~', $value, $fallback),
            "{$value} ends in a literal fallback."
        );

        return ['hex' => $this->six($fallback[1]), 'ink' => null, 'ground' => null];
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
        $this->assertSame(13, $checked, 'Control: every label in scope was read.');
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
        $this->assertSame(32, $checked, 'Control: every tone in scope was read.');

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

    /**
     * The custom properties every rule opening with the given text declares, merged in sheet order.
     *
     * The body is read by counting braces, so a Sass interpolation's own braces (#{mix(...)}) stay inside it.
     *
     * @param string $scss The stylesheet, comments removed.
     * @param string $opening The rules' opening text, from the start of a line to the brace.
     * @return array Name => value.
     */
    private function declared(string $scss, string $opening): array {
        $tokens = [];
        $offset = 0;
        while (($at = strpos($scss, $opening, $offset)) !== false) {
            $start = $at + strlen($opening);
            $depth = 1;
            for ($i = $start; $depth > 0 && $i < strlen($scss); $i++) {
                $depth += ['{' => 1, '}' => -1][$scss[$i]] ?? 0;
            }
            preg_match_all('~(--[a-z0-9-]+):\s*([^;]+);~', substr($scss, $start, $i - $start), $found, PREG_SET_ORDER);
            $tokens = array_merge($tokens, array_column($found, 2, 1));
            $offset = $i;
        }
        $this->assertNotEmpty($tokens, 'A rule opens with: ' . trim($opening));

        return $tokens;
    }

    /**
     * Every state label of the course card has an edge of its own in both modes, which shows against its fill.
     *
     * The edge is darker than the fill on the light page and lighter on the dark one, at the theme's floor for an edge;
     * a mixed edge is its own state's ink in its own fill. Where the family's state tokens (post.scss) carry the same
     * fill and ink, the label's edge is the family's edge, so the two vocabularies draw one line.
     *
     * Changes that must make it fail: a state's edge missing in either mode; a light edge mixed at 5%; a dark edge mixed
     * from another state's pair; an edge that differs from the family's edge of the same pair.
     *
     * @return void
     */
    public function test_every_card_state_label_shows_its_edge_in_both_modes(): void {
        $colours = $this->sass_colours();
        $scss = $this->stylesheet('category.scss');
        $split = strpos($scss, ':root[data-bs-theme="dark"]');
        $this->assertNotFalse($split, 'Precondition: the showcase has a dark half.');
        $modes = [
            'light' => $this->declared(substr($scss, 0, $split), self::BADGE_ROOTS),
            'dark' => $this->declared(substr($scss, $split - 1), self::BADGE_DARK_ROOTS),
        ];
        preg_match_all('~^--dim-badge-([a-z]+)-bg$~m', implode("\n", array_keys($modes['light'])), $states);
        $this->assertCount(14, $states[1], 'Precondition: eleven states, held, public and hidden.');

        $post = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss'));
        preg_match_all('~--dimensions-state-([a-z]+)-(bg|ink|edge):\s*([^;]+);~', $post, $found, PREG_SET_ORDER);
        $family = [];
        foreach ($found as [, $state, $part, $value]) {
            $mode = isset($family['light'][$state][$part]) ? 'dark' : 'light';
            $family[$mode][$state][$part] = $this->colour(trim($value), $colours)['hex'];
        }
        $this->assertCount(6, $family['dark'] ?? [], 'Precondition: the family\'s six states, light and dark.');

        $measured = 0;
        $reused = 0;
        foreach ($modes as $mode => $tokens) {
            foreach ($states[1] as $state) {
                $label = "The {$mode} {$state} label's edge";
                $this->assertArrayHasKey("--dim-badge-{$state}-edge", $tokens, "{$label} is declared.");
                $ground = $this->colour($tokens["--dim-badge-{$state}-bg"], $colours)['hex'];
                $ink = $this->colour($tokens["--dim-badge-{$state}-fg"], $colours)['hex'];
                $edge = $this->colour($tokens["--dim-badge-{$state}-edge"], $colours);
                if ($edge['ink'] !== null) {
                    $this->assertSame([$ink, $ground], [$edge['ink'], $edge['ground']], "{$label} is mixed from its own pair.");
                }
                $contrast = branding::contrast($edge['hex'], $ground);
                $this->assertGreaterThanOrEqual(self::EDGE_FLOOR, $contrast, "{$label} ({$edge['hex']}) shows on {$ground}.");
                $lighter = branding::contrast($edge['hex'], '#ffffff') < branding::contrast($ground, '#ffffff');
                $this->assertSame($mode === 'dark', $lighter, "{$label} is darker in light and lighter in dark.");
                foreach ($family[$mode] as $name => $triple) {
                    if ([$triple['bg'], $triple['ink']] === [$ground, $ink]) {
                        $this->assertSame($triple['edge'], $edge['hex'], "{$label} is the family's {$name} edge.");
                        $reused++;
                    }
                }
                $measured++;
            }
        }
        $this->assertSame(28, $measured, 'Control: fourteen states in two modes.');
        $this->assertSame(26, $reused, 'Control: every state but hidden shares its pair with a family state, in both modes.');
    }

    /**
     * The completed seal is edged by its own rim, which shows against its fill in both modes.
     *
     * The rim and the fill are one Bootstrap triple, which follows the colour mode by itself: its light fallbacks are
     * Bootstrap 5.3's tints of the success colour (60% and 80% toward white), and its dark values its shades (40% and
     * 80% toward black), both measured here from the card's success fallback. --dim-card-done-edge keeps its meaning,
     * the cover base's height, which is why the line is the rim.
     *
     * Changes that must make it fail: the rim on the fill; the seal's rule before the label's base, whose border
     * shorthand would put the none state's edge back; the cover base's height token re-pointed.
     *
     * @return void
     */
    public function test_the_completed_seal_shows_its_rim(): void {
        $scss = $this->stylesheet('category.scss');
        $card = $this->declared($scss, "\n    .dim-card {");
        $this->assertSame('var(--dimensions-card-done-edge, 4px)', $card['--dim-card-done-edge'] ?? '', 'The base\'s height.');
        $this->assertStringContainsString('--bs-success-border-subtle', $card['--dim-card-done-rim'] ?? '', 'Bootstrap\'s line.');
        $this->assertStringContainsString('--bs-success-bg-subtle', $card['--dim-card-done-bg'], 'Bootstrap\'s fill.');
        $colours = $this->sass_colours();
        $rim = $this->colour($card['--dim-card-done-rim'], $colours)['hex'];
        $fill = $this->colour($card['--dim-card-done-bg'], $colours)['hex'];
        $success = $this->colour($card['--dim-card-done'], $colours)['hex'];
        $toward = function (string $towards, float $weight) use ($success): string {
            $hex = '#';
            foreach ([1, 3, 5] as $at) {
                $hex .= sprintf('%02x', (int) round(
                    hexdec(substr($towards, $at, 2)) * $weight + hexdec(substr($success, $at, 2)) * (1 - $weight)
                ));
            }
            return $hex;
        };
        $this->assertSame(
            [$toward('#ffffff', .6), $toward('#ffffff', .8)],
            [$rim, $fill],
            'The light fallbacks are Bootstrap\'s.'
        );
        $modes = ['light' => [$rim, $fill], 'dark' => [$toward('#000000', .4), $toward('#000000', .8)]];
        foreach ($modes as $mode => [$line, $ground]) {
            $this->assertGreaterThanOrEqual(self::EDGE_FLOOR, branding::contrast($line, $ground), "The {$mode} rim shows.");
            $lighter = branding::contrast($line, '#ffffff') < branding::contrast($ground, '#ffffff');
            $this->assertSame($mode === 'dark', $lighter, "The {$mode} rim is darker in light and lighter in dark.");
        }

        $this->assertSame(
            [
                'border-color' => 'var(--dim-card-done-rim)',
                'background' => 'var(--dim-card-done-bg)',
                'color' => 'var(--dim-card-done-ink)',
            ],
            $this->own_declarations($scss, "\n    .dim-badge--done {"),
            'The seal re-points the edge, the fill and the ink together.'
        );
        $this->assertGreaterThan(
            strpos($scss, "\n    .dim-badge {"),
            strpos($scss, "\n    .dim-badge--done {"),
            'The seal follows the label\'s base, at the same weight.'
        );
    }

    /**
     * A label's icon is the words' size and four pixels from them; the kind on a card stays a pill on the same scales.
     *
     * Changes that must make it fail: the gap back to 6px; the icon sized above 1em; the dialogue's copy of the label
     * given a size or a padding of its own again; the kind label rounded into a rectangle, or off the space scale.
     *
     * @return void
     */
    public function test_a_label_icon_is_the_words_size_four_pixels_away(): void {
        $scss = $this->stylesheet('category.scss');
        $badge = $this->own_declarations($scss, "\n    .dim-badge {");
        $this->assertSame('.25rem', $badge['gap'] ?? '', 'The icon is 4px from the words.');
        $this->assertSame('.125rem .5rem', $badge['padding'] ?? '', 'The label\'s padding.');
        $this->assertSame('1em', $this->own_declarations($scss, "\n    .dim-badge .fa {")['font-size'] ?? '', 'The icon\'s size.');

        $modal = $this->own_declarations($scss, "\n    .dim-cmodal__pill {");
        $this->assertArrayNotHasKey('font-size', $modal, 'The dialogue\'s label is the card\'s, in size.');
        $this->assertArrayNotHasKey('padding', $modal, 'The dialogue\'s label is the card\'s, in padding.');

        foreach (["\n    .dim-card__kind {", "\n    .dim-card__topkind {"] as $opening) {
            $kind = $this->own_declarations($scss, $opening);
            $label = trim($opening);
            $this->assertSame('var(--dim-radius-pill)', $kind['border-radius'] ?? '', "{$label}: the kind stays a pill.");
            $this->assertSame('.25rem', $kind['gap'] ?? '', "{$label}: the icon is 4px from the words.");
            $this->assertSame('.125rem .5rem', $kind['padding'] ?? '', "{$label}: on the space scale.");
            $this->assertSame(
                ['.75rem', '600'],
                [$kind['font-size'] ?? '', $kind['font-weight'] ?? ''],
                "{$label}: 0.75rem / 600."
            );
        }
        $glyph = $this->own_declarations($scss, "\n    .dim-card__kind .fa,\n    .dim-card__topkind .fa {");
        $this->assertSame('1em', $glyph['font-size'] ?? '', 'The kind\'s glyph is the words\' size.');

        preg_match_all('~([^{};]+)\{([^{}]*)\}~', $scss, $rules, PREG_SET_ORDER);
        $icons = 0;
        foreach ($rules as [, $selector, $body]) {
            if (preg_match('~(?:dim-badge|dim-card__badge|dim-cmodal__pill)[^,]*\.fa\b~', $selector)) {
                $icons++;
                if (preg_match('~(?:^|;)\s*font-size:\s*([^;]+)~', $body, $size)) {
                    $this->assertSame('1em', trim($size[1]), trim($selector) . ' sizes the icon above the words.');
                }
            }
        }
        $this->assertSame(1, $icons, 'Control: the label\'s one icon rule was read.');
    }
}
