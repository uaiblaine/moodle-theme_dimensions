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
 * Theme Dimensions - Tests for the calendar page's stylesheet
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\calendar\keycolours;
use theme_dimensions\local\category\branding;

/**
 * scss/calendar.scss: scoped under the page's body class or the calendar blocks' root, and every key colour pair
 * computed in both colour modes.
 *
 * The key tokens are compiled from the stylesheet itself (the two token blocks, over the design system's primitives
 * from post.scss and core's colour-mode helpers, as dark_tokens_test compiles the theme's dark half), and each pair
 * is measured: a key's ink on its tint at 4.5:1 (text), its edge on the card surface and on the quiet surface of the
 * neighbouring days at 3:1 (a mark: a border, a dot). The matrix counts the pairs it measured, so a token block the
 * parser stopped finding fails here rather than passing on nothing.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class calendar_scss_test extends \advanced_testcase {
    /** @var string The page's body class. */
    private const SCOPE = 'body.dim-calendar';

    /** @var string The dark token block's selector. */
    private const DARK = ':root[data-bs-theme="dark"] body.dim-calendar';

    /** @var string The calendar blocks' root (local\calendar\blocks), on any page. */
    private const BLOCK = '.dim-calblock';

    /** @var string The dark colour mode over the blocks' root. */
    private const DARK_BLOCK = ':root[data-bs-theme="dark"] .dim-calblock';

    /** @var string The light token block's selector, as written: the page and the blocks declare the same tokens. */
    private const TOKENS = self::SCOPE . ",\n" . self::BLOCK;

    /** @var string The dark token block's selector, as written. */
    private const DARK_TOKENS = self::DARK . ",\n" . self::DARK_BLOCK;

    /** @var string[] The keys that carry a colour triple: the eight keys and the overdue warning. */
    private const KEYS = ['site', 'category', 'course', 'group', 'user', 'activity', 'other', 'open', 'overdue'];

    /** @var string[] The Trilhas view's own colour triples: the schedule's and each step state's, never a key's setting. */
    private const TRAIL_KEYS = ['trail', 'trailcurrent', 'traildone', 'traillate', 'traillocked', 'trailupcoming'];

    /**
     * The stylesheet, comments out.
     *
     * @return string
     */
    private function scss(): string {
        $scss = file_get_contents(dirname(__DIR__, 2) . '/scss/calendar.scss');
        $this->assertNotFalse($scss);
        return (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $scss);
    }

    /**
     * The selectors of the top-level rules (and of rules directly under an at-rule).
     *
     * @param string $scss The stylesheet.
     * @return string[]
     */
    private function top_level_selectors(string $scss): array {
        $selectors = [];
        $stack = [];
        $buffer = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $selector = trim((string) preg_replace('~\s+~', ' ', $buffer));
                $depth = count($stack);
                if (!str_starts_with($selector, '@') && ($depth === 0 || ($depth === 1 && str_starts_with($stack[0], '@')))) {
                    $selectors[] = $selector;
                }
                $stack[] = $selector;
                $buffer = '';
            } else if ($char === '}') {
                array_pop($stack);
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
     * Every top-level rule is the page's or the blocks': the body class or the blocks' root, or the dark mode over one.
     *
     * @return void
     */
    public function test_every_rule_is_scoped_to_the_calendar_page(): void {
        $selectors = $this->top_level_selectors($this->scss());
        $this->assertGreaterThanOrEqual(4, count($selectors), 'The control: the rules are read.');
        foreach ($selectors as $selector) {
            foreach (explode(',', $selector) as $part) {
                $this->assertContains(
                    trim($part),
                    [self::SCOPE, self::DARK, self::BLOCK, self::DARK_BLOCK],
                    "Unscoped: {$selector}"
                );
            }
        }
        $this->assertContains(self::BLOCK, $selectors, 'The blocks have rules of their own.');
    }

    /**
     * No :has(), no !important, no literal colour outside the two token blocks.
     *
     * @return void
     */
    public function test_no_has_no_important_no_literal_colour(): void {
        $scss = $this->scss();
        $this->assertStringNotContainsString(':has(', $scss);
        $this->assertStringNotContainsString('!important', $scss);
        $rules = substr($scss, strpos($scss, '@each'));
        $this->assertDoesNotMatchRegularExpression('~#[0-9a-fA-F]{3,6}\b~', $rules, 'A colour outside the tokens.');
        $this->assertDoesNotMatchRegularExpression('~\brgba?\(~', $rules);
    }

    /**
     * The body of a rule of the stylesheet, by its selector.
     *
     * @param string $selector The selector.
     * @return string
     */
    private function block(string $selector): string {
        $scss = $this->scss();
        $start = strpos($scss, $selector . ' {');
        $this->assertNotFalse($start, "No rule {$selector}.");
        $end = strpos($scss, "\n}", $start);
        return substr($scss, $start + strlen($selector) + 2, $end - $start - strlen($selector) - 2);
    }

    /**
     * The stylesheet's head: the chosen colours' default and the functions the token blocks call (R1c), everything
     * before the light token block.
     *
     * @return string
     */
    private function head(): string {
        $scss = $this->scss();
        $start = strpos($scss, '$dim-cal-colours:');
        $end = strpos($scss, self::TOKENS . ' {');
        $this->assertNotFalse($start, 'The chosen colours\' default is declared.');
        $this->assertNotFalse($end);
        $this->assertLessThan($end, $start, 'The head comes before the token blocks.');
        return substr($scss, $start, $end - $start);
    }

    /**
     * The two token blocks compiled, as name => #rrggbb per mode.
     *
     * @param string $colours The chosen colours, as the pre SCSS callback writes them (keycolours::scss()), or ''.
     * @return array ['light' => [...], 'dark' => [...]].
     */
    private function compiled(string $colours = ''): array {
        global $CFG;
        $post = file_get_contents(dirname(__DIR__, 2) . '/scss/post.scss');
        preg_match_all('/^\$dim-ds-[a-z]+(?:-\d+)?: #[0-9a-f]{3,6};$/m', $post, $primitives);
        $this->assertGreaterThan(20, count($primitives[0]), 'Precondition: the primitives are Sass variables.');

        $compiler = new \core_scss();
        $compiler->setImportPaths([$CFG->dirroot . '/theme/boost/scss']);
        $compiler->append_raw_scss(
            '@import "design-system-tokens"; @import "' . $CFG->root . '/lib/bundles/bootstrap/scss/functions";'
            . "\n\$prefix: bs-;\n\$white: \$mds-color-gray-white;\n\$gray-800: \$mds-color-gray-800;\n"
            . "\$gray-900: \$mds-color-gray-900;\n@import \"moodle/colour-mode\";\n"
            . implode("\n", $primitives[0])
            . $colours . "\n" . $this->head()
            . "\n.light {" . $this->block(self::TOKENS) . "\n}\n.dark {" . $this->block(self::DARK_TOKENS) . "\n}"
        );
        $css = $compiler->to_css();
        $modes = [];
        foreach (['light', 'dark'] as $mode) {
            $this->assertSame(1, preg_match('~\.' . $mode . ' \{(.*?)\}~s', $css, $body), "The {$mode} block compiles.");
            preg_match_all('/(--dim-cal-[a-z-]+):\s*([^;]+);/', $body[1], $found, PREG_SET_ORDER);
            foreach ($found as [, $name, $value]) {
                $modes[$mode][$name] = self::hex(trim($value));
            }
        }
        return $modes;
    }

    /**
     * A compiled colour as #rrggbb.
     *
     * @param string $value The compiled value.
     * @return string
     */
    private static function hex(string $value): string {
        $value = strtolower($value);
        $named = ['white' => '#ffffff', 'black' => '#000000'];
        if (isset($named[$value])) {
            return $named[$value];
        }
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $short)) {
            return '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
        }
        return $value;
    }

    /**
     * Every key's pairs read, and the Trilhas view's own, in both colour modes: ink on tint at 4.5:1, edge on both surfaces at 3:1.
     *
     * @return void
     */
    public function test_every_key_pair_reads_in_both_modes(): void {
        $this->resetAfterTest();
        $modes = $this->compiled();
        $measured = 0;
        foreach ($modes as $mode => $tokens) {
            foreach (array_merge(self::KEYS, self::TRAIL_KEYS) as $key) {
                foreach (['tint', 'edge', 'ink'] as $part) {
                    $this->assertMatchesRegularExpression(
                        '/^#[0-9a-f]{6}$/',
                        $tokens["--dim-cal-{$key}-{$part}"] ?? '',
                        "{$mode} {$key}-{$part} compiles to a colour."
                    );
                }
                $tint = $tokens["--dim-cal-{$key}-tint"];
                $ink = $tokens["--dim-cal-{$key}-ink"];
                $edge = $tokens["--dim-cal-{$key}-edge"];
                $this->assertGreaterThanOrEqual(4.5, branding::contrast($tint, $ink), "{$mode} {$key}: ink on tint.");
                foreach (['--dim-cal-surface', '--dim-cal-surface-quiet'] as $surface) {
                    $this->assertGreaterThanOrEqual(
                        3.0,
                        branding::contrast($tokens[$surface], $edge),
                        "{$mode} {$key}: edge on {$surface}."
                    );
                }
                $measured++;
            }
        }
        $this->assertSame(2 * (count(self::KEYS) + count(self::TRAIL_KEYS)), $measured);
    }

    /**
     * The light and the dark block declare the same names: a token missing from one half reads nothing there.
     *
     * @return void
     */
    public function test_both_modes_declare_every_token(): void {
        $this->resetAfterTest();
        $modes = $this->compiled();
        $this->assertSame(array_keys($modes['light']), array_keys($modes['dark']));
        $this->assertCount((count(self::KEYS) + count(self::TRAIL_KEYS)) * 3 + 4, $modes['light']);
    }

    /**
     * The weekend's hatch is a token of both modes, faint over the quiet surface, and text keeps its contrast on it.
     *
     * The plain item's ink (the `other` key's) stands for every text drawn over a weekend's ground, the day numbers
     * included, which are darker in the light mode and lighter in the dark one.
     *
     * @return void
     */
    public function test_the_weekend_hatch_is_faint_and_keeps_the_text_readable(): void {
        $this->resetAfterTest();
        $measured = 0;
        foreach ($this->compiled() as $mode => $tokens) {
            $hatch = $tokens['--dim-cal-weekend-hatch'] ?? '';
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hatch, "{$mode}: the hatch compiles to a colour.");
            $this->assertNotSame($tokens['--dim-cal-surface-quiet'], $hatch, "{$mode}: the hatch shows.");
            $this->assertLessThan(1.5, branding::contrast($tokens['--dim-cal-surface-quiet'], $hatch), "{$mode}: faint.");
            $this->assertGreaterThanOrEqual(4.5, branding::contrast($hatch, $tokens['--dim-cal-other-ink']), "{$mode}: text.");
            $measured++;
        }
        $this->assertSame(2, $measured);
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__ground--weekend,\s*\.dim-cal__weekcol--weekend \{\s*background-color: var\(--dim-cal-surface-quiet\);'
                . '\s*background-image:\s*repeating-linear-gradient\(\s*135deg,\s*var\(--dim-cal-weekend-hatch\) 0 1px,~',
            $this->scss()
        );
    }

    /**
     * A day under the mouse is told apart from the card surface, and text and every key's mark keep their contrast on it;
     * the hover is drawn only where the pointer can hover, on the mark the client sets for a mouse alone.
     *
     * @return void
     */
    public function test_the_day_hover_reads_in_both_modes(): void {
        $this->resetAfterTest();
        $measured = 0;
        foreach ($this->compiled() as $mode => $tokens) {
            $hover = $tokens['--dim-cal-surface-hover'] ?? '';
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hover, "{$mode}: the hover compiles to a colour.");
            $this->assertNotSame($tokens['--dim-cal-surface'], $hover, "{$mode}: the hover shows.");
            $this->assertGreaterThanOrEqual(4.5, branding::contrast($hover, $tokens['--dim-cal-other-ink']), "{$mode}: text.");
            foreach (self::KEYS as $key) {
                $this->assertGreaterThanOrEqual(
                    3.0,
                    branding::contrast($hover, $tokens["--dim-cal-{$key}-edge"]),
                    "{$mode} {$key}: edge on the hover."
                );
            }
            $measured++;
        }
        $this->assertSame(2, $measured);
        $this->assertMatchesRegularExpression(
            '~@media \(hover: hover\) \{\s*\.dim-cal__day\[data-cal-hover\] > \.dim-cal__ground,\s*'
                . '\.dim-cal__weekcol\[data-cal-hover\] \{\s*background-color: var\(--dim-cal-surface-hover\);~',
            $this->scss()
        );
    }

    /**
     * The page takes the whole column: Boost's cap of the standard layout's .main-inner is dropped from md up, under
     * the page's body class, which outranks Boost's .pagelayout-standard selector.
     *
     * @return void
     */
    public function test_the_page_takes_the_whole_width(): void {
        $this->assertMatchesRegularExpression(
            '~^body\.dim-calendar \{\s*@media \(min-width: 768px\) \{\s*#page\.drawers \.main-inner \{\s*max-width: none;\s*\}~m',
            $this->scss()
        );
    }

    /**
     * The space between the navbar and the calendar's title is halved (PROPOSAL section 29.3): .main-inner pays 12px
     * above and 10px inside instead of Boost's 40px and 24px, under the body class and with no !important. Measured at
     * 1440x900 on m503b: 83px of gap before, 41px after.
     *
     * @return void
     */
    public function test_the_gap_under_the_navbar_is_halved(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression(
            '~^body\.dim-calendar \{.*?^    #page\.drawers \.main-inner \{\s*margin-top: 12px;\s*padding-top: 10px;\s*\}~ms',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~^    #page-header \{\s*margin-block: 0;\s*padding-block: 0;\s*min-height: 0;\s*\}~m',
            $scss
        );
    }

    /**
     * The day drawer sits beside the grid in a 24.5rem column and holds still under the navbar while the month scrolls.
     *
     * @return void
     */
    public function test_the_drawer_is_a_sticky_side_column(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__body--drawer \{\s*grid-template-columns: minmax\(0, 1fr\) 24\.5rem;~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__drawer--drawer \{[^;]*;\s*position: sticky;\s*top: calc\(#\{\$navbar-height\} \+ 1rem\);~',
            $scss
        );
    }

    /**
     * R1c (PROPOSAL section 30.7): beside the grid the drawer is never taller than the grid or than the viewport under the
     * navbar. The cap is the smaller of the two, in a custom property (stylelint allows min() only there), read by the
     * drawer's max-height; the grids' observer writes --dim-cal-gridheight on the frame. The hour box shrinks and scrolls
     * because the chain down to it (drawer overflow, box min-height) lets it.
     *
     * @return void
     */
    public function test_the_drawer_is_never_taller_than_the_grid_or_the_viewport(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__drawer--drawer \{\s*--dim-cal-drawercap: min\(calc\(100vh - #\{\$navbar-height\} - 2rem\), '
                . 'var\(--dim-cal-gridheight, 100vh\)\);\s*position: sticky;[^;]*;\s*max-height: var\(--dim-cal-drawercap\);~',
            $scss,
            'The viewport cap and the grid\'s height, the smaller of the two.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__hourbox \{[^}]*min-height: 0;~',
            $scss,
            'The flex chain lets the box shrink below its content.'
        );
        $this->assertMatchesRegularExpression('~\.dim-cal__hourscroll \{\s*flex: 1 1 auto;\s*min-height: 0;~', $scss);
        $this->assertStringContainsString(
            "GRID_HEIGHT = '--dim-cal-gridheight'",
            file_get_contents(__DIR__ . '/../../js/esm/src/calendar/days.ts'),
            'The property the stylesheet reads is the one the grids write.'
        );
    }

    /**
     * R1c (PROPOSAL section 30.1): the hour column scrolls in a box of its own, drawn as block_compass's calendar strip:
     * no native scrollbar in either engine, its overscroll kept from the page, the fades taking no pointer; the drawer's
     * box takes what the head leaves, and the week's is capped under the viewport.
     *
     * @return void
     */
    public function test_the_hour_column_scrolls_in_a_box_of_its_own(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__hourscroll \{[^}]*overflow-y: auto;[^}]*overscroll-behavior: contain;[^}]*scrollbar-width: none;~',
            $scss
        );
        $this->assertMatchesRegularExpression('~\.dim-cal__hourscroll::-webkit-scrollbar \{\s*display: none;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__hourfade \{[^}]*position: absolute;[^}]*pointer-events: none;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__hourbox \{\s*position: relative;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__hourbox--day \{\s*flex: 1 1 auto;~', $scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__hourbox--week \.dim-cal__hourscroll \{[^}]*max-height: calc\(100vh - #\{\$navbar-height\} - 14rem\);~',
            $scss
        );
        // R1d (PROPOSAL section 31): the phone's Day view is not sticky and its box is capped under the viewport as well.
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__drawer--dayview \.dim-cal__hourscroll \{[^}]*max-height: calc\(100vh - #\{\$navbar-height\} - 12rem\);~',
            $scss
        );
        $this->assertStringNotContainsString('.dim-cal__drawer--dayview {', $scss, 'Only the drawer beside a grid sticks.');
    }

    /**
     * The first paint (PROPOSAL section 29): under core's jsenabled the server's layer is held out and the placeholder
     * stands in, a fail-safe animation brings the server back and retires the placeholder after a delay, the mounted
     * mark lifts the lot, and without jsenabled the placeholder is never shown.
     *
     * @return void
     */
    public function test_the_first_paint_shows_a_placeholder_and_the_server_comes_back_if_the_client_never_draws(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression('~\.dim-cal__placeholder \{\s*display: none;\s*\}~', $scss);
        $this->assertMatchesRegularExpression(
            '~&\.jsenabled \.dim-cal:not\(\.dim-cal--mounted\) \{\s*\.dim-cal__server \{[^}]*max-height: 0;[^}]*'
            . 'visibility: hidden;[^}]*animation: dim-cal-reveal [^;]*\b4s forwards;~',
            $scss,
            'The server is held out from the first paint and revealed after a delay.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__placeholder \{\s*display: block;\s*animation: dim-cal-retire [^;]*\b4s forwards;~',
            $scss,
            'The placeholder is shown under jsenabled and retired by the same delay.'
        );
        $this->assertMatchesRegularExpression('~@keyframes dim-cal-reveal \{\s*to \{[^}]*visibility: visible;~', $scss);
        $this->assertMatchesRegularExpression('~@keyframes dim-cal-retire \{\s*to \{[^}]*visibility: hidden;~', $scss);
        // The control: the hold is only under jsenabled, so the page without JavaScript keeps the server's layer whole.
        $this->assertStringNotContainsString('.dim-cal__server', substr($scss, 0, (int) strpos($scss, '&.jsenabled')));
        $this->assertSame(1, substr_count($scss, '&.jsenabled'));
    }

    /**
     * The week's all-day strip spans the seven day tracks of its row: one grid item, it would otherwise take the first
     * track alone and squeeze every bar into a day's width.
     *
     * @return void
     */
    public function test_the_week_strip_spans_the_day_columns(): void {
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__weekstripgrid\s*\{[^}]*grid-column:\s*2\s*/\s*-1;~',
            $this->scss()
        );
    }

    /**
     * Without a chosen colour every key keeps its hand-picked triple: the light edge is the setting's default, so an
     * untouched site, and one that chose a key's default, compiles what it compiled before the settings existed.
     *
     * @return void
     */
    public function test_an_untouched_site_keeps_every_hand_picked_edge(): void {
        $this->resetAfterTest();
        $modes = $this->compiled();
        foreach (keycolours::DEFAULTS as $key => $default) {
            $this->assertSame($default, $modes['light']["--dim-cal-{$key}-edge"], "{$key}: the default is the edge.");
        }
        $this->assertSame($modes, $this->compiled("\n\$dim-cal-colours: () !default;\n"), 'An empty map changes nothing.');
    }

    /**
     * Colours at the extremes and in between: each chosen key derives a triple that keeps the floors in both modes
     * (ink on tint 4.5:1, edge on both surfaces 3:1), a colour that already marks the surface is the light edge as it
     * is, and the keys nobody chose are untouched.
     *
     * @return array[]
     */
    public static function chosen_colours(): array {
        return [
            'white' => ['#ffffff'],
            'black' => ['#000000'],
            'a light yellow' => ['#ffe600'],
            'a dark navy' => ['#1a1a40'],
            'a mid purple' => ['#7b1fa2'],
            'a light cyan' => ['#7fe3ff'],
        ];
    }

    /**
     * A chosen colour reaches the tokens and the floors hold whatever it is.
     *
     * @param string $colour The colour chosen for two keys.
     * @return void
     */
    #[DataProvider('chosen_colours')]
    public function test_a_chosen_colour_derives_a_readable_triple_in_both_modes(string $colour): void {
        $this->resetAfterTest();
        $untouched = $this->compiled();
        $modes = $this->compiled("\n\$dim-cal-colours: (course: {$colour}, overdue: {$colour}) !default;\n");
        foreach (['light', 'dark'] as $mode) {
            foreach (['course', 'overdue'] as $key) {
                $tint = $modes[$mode]["--dim-cal-{$key}-tint"];
                $ink = $modes[$mode]["--dim-cal-{$key}-ink"];
                $edge = $modes[$mode]["--dim-cal-{$key}-edge"];
                $this->assertGreaterThanOrEqual(4.5, branding::contrast($tint, $ink), "{$mode} {$key} {$colour}: ink on tint.");
                foreach (['--dim-cal-surface', '--dim-cal-surface-quiet'] as $surface) {
                    $this->assertGreaterThanOrEqual(
                        3.0,
                        branding::contrast($modes[$mode][$surface], $edge),
                        "{$mode} {$key} {$colour}: edge on {$surface}."
                    );
                }
                $this->assertNotSame(
                    $untouched[$mode]["--dim-cal-{$key}-tint"],
                    $tint,
                    "{$mode} {$key}: the chosen colour reached the fill."
                );
            }
            foreach (array_diff(array_keys(keycolours::DEFAULTS), ['course', 'overdue']) as $key) {
                foreach (['tint', 'edge', 'ink'] as $part) {
                    $this->assertSame(
                        $untouched[$mode]["--dim-cal-{$key}-{$part}"],
                        $modes[$mode]["--dim-cal-{$key}-{$part}"],
                        "{$mode} {$key}-{$part} was not chosen."
                    );
                }
            }
        }
        if (branding::contrast('#f8f9fa', $colour) >= 3.0) {
            $this->assertSame($colour, $modes['light']['--dim-cal-course-edge'], 'A colour that marks the surface is the edge.');
        } else {
            $this->assertNotSame($colour, $modes['light']['--dim-cal-course-edge'], 'A colour too light is shaded.');
        }
    }

    /**
     * A Trilhas view's state pill keeps its own width in the popover's flex column, where justify-self does nothing,
     * and a trail's title stacks its name over its progress wherever it is drawn: the phone's agenda heading carries
     * the title class alone, without the desktop label's flex.
     *
     * @return void
     */
    public function test_the_trail_state_and_title_keep_their_shape(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression('~\.dim-cal__trailstate \{\s*align-self: flex-start;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__trailtitle \{\s*display: flex;\s*flex-direction: column;~', $scss);
    }

    /**
     * The file is compiled into the theme's main SCSS.
     *
     * @return void
     */
    public function test_the_sheet_is_part_of_the_theme(): void {
        $lib = (string) file_get_contents(dirname(__DIR__, 2) . '/lib.php');
        $this->assertStringContainsString("/theme/dimensions/scss/calendar.scss'", $lib);
    }

    /**
     * A selector's specificity as [ids, classes, elements]: an attribute and a pseudo-class count as a class, a :not()
     * counts as its argument, and the universal selector and the combinators count nothing.
     *
     * @param string $selector One complex selector, no list.
     * @return int[]
     */
    private static function specificity(string $selector): array {
        $total = [0, 0, 0];
        while (preg_match('~:not\(([^()]*)\)~', $selector, $not)) {
            $inner = self::specificity($not[1]);
            foreach ($inner as $i => $count) {
                $total[$i] += $count;
            }
            $selector = str_replace($not[0], ' ', $selector);
        }
        $selector = (string) preg_replace('~\[[^\]]*\]~', ' .attr ', $selector);
        $total[0] += preg_match_all('~#[\w-]+~', $selector);
        $total[1] += preg_match_all('~\.[\w-]+|:(?!:)[\w-]+~', $selector);
        $total[2] += preg_match_all('/(?:^|[\s>+~])([a-z][\w-]*)/i', $selector);
        return $total;
    }

    /**
     * The calendar's own links keep no underline in dark mode: the rule's selector outweighs core's dark-mode underline
     * of #region-main's links, read from core's stylesheet (PROPOSAL.md section 32.2), and spares the prose.
     *
     * @return void
     */
    public function test_the_calendars_links_outrank_cores_dark_underline(): void {
        global $CFG;
        $core = file_get_contents($CFG->dirroot . '/theme/boost/scss/moodle/dark.scss');
        $link = 'a:not(.btn):not(.nav-link):not(.dropdown-item):not(.list-group-item):not(.page-link)';
        $this->assertStringContainsString(
            $link . " {\n            text-decoration: underline;",
            (string) $core,
            "Precondition: core's rule, as read on 5.3."
        );
        $this->assertMatchesRegularExpression('~#region-main,\s*#page-footer,~', (string) $core);
        $cores = self::specificity('[data-bs-theme="dark"] #region-main ' . $link);
        $this->assertSame([1, 6, 1], $cores, 'The helper reads core\'s weight as a browser does.');

        $inner = '#region-main .dim-cal a[href]:not(.dim-cal__popdesc a):not(.dim-cal__footer a)';
        $this->assertMatchesRegularExpression(
            '~^' . preg_quote(self::DARK, '~') . ' \{\s*' . preg_quote($inner, '~') . ' \{\s*text-decoration: none;\s*\}~m',
            $this->scss()
        );
        $ours = self::specificity(self::DARK . ' ' . $inner);
        $this->assertGreaterThan(0, $ours <=> $cores, 'The calendar\'s rule must outweigh core\'s: ' . implode(',', $ours));
    }

    /**
     * R3 (docs/calendar/PROPOSAL.md section 33): the Trilhas view's days scroll in a scroller drawn as block_compass's
     * strip (no native scrollbar in either engine, its overscroll kept from the page, snapped by the day, snap off while
     * the map drags), the trail column and the last column of every row held still over the days, the fades taking no
     * pointer; and the calendar's buttons are the mockup's, re-pointed through Bootstrap's own properties, the disabled
     * trio included.
     *
     * @return void
     */
    public function test_the_trail_scroller_and_the_buttons_follow_the_mockup(): void {
        $scss = $this->scss();
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__trailscroll \{[^}]*overflow-x: auto;[^}]*overscroll-behavior-x: contain;[^}]*'
                . 'scroll-snap-type: x proximity;[^}]*scrollbar-width: none;~',
            $scss
        );
        $this->assertMatchesRegularExpression('~\.dim-cal__trailscroll::-webkit-scrollbar \{\s*display: none;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__trailscroll--free \{\s*scroll-snap-type: none;~', $scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__traillabel,\s*\.dim-cal__trailspill \{\s*position: sticky;\s*z-index: 3;~',
            $scss,
            'The two still columns stand over every bar (1) and diamond (2).'
        );
        $this->assertMatchesRegularExpression('~\.dim-cal__traillabel \{\s*inset-inline-start: 0;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__trailspill \{\s*inset-inline-end: 0;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__trailfade \{[^}]*position: absolute;[^}]*pointer-events: none;~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-cal__trailgrid \{[^}]*width: max-content;~', $scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-cal__trailrow \{[^}]*grid-template-columns:\s*var\(--dim-cal-trail-label\)\s*'
                . 'repeat\(var\(--dim-cal-trail-days, 31\), var\(--dim-cal-trail-day\)\)\s*var\(--dim-cal-trail-spill\);~',
            $scss,
            'The days have the fixed width the client\'s arithmetic reads.'
        );
        foreach (['.dim-cal .btn-secondary', '.dim-cal .btn-primary'] as $variant) {
            $start = strpos($scss, $variant);
            $this->assertNotFalse($start, "No rule for {$variant}.");
            $body = substr($scss, $start, strpos($scss, '}', $start) - $start);
            foreach (['bg', 'color', 'border-color', 'disabled-bg', 'disabled-color', 'disabled-border-color'] as $part) {
                $this->assertStringContainsString("--bs-btn-{$part}:", $body, "{$variant} re-points --bs-btn-{$part}.");
            }
        }
        $this->assertMatchesRegularExpression(
            '~\.dim-cal \.btn \{[^}]*min-height: 2\.25rem;[^}]*border-radius: var\(--dim-ds-radius-md, 8px\);[^}]*'
                . 'font-size: \.875rem;[^}]*font-weight: 500;~',
            $scss
        );
    }
}
