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
 * Theme Dimensions - The fleet Design System's scale ratchet over the theme's SCSS
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The fleet Design System's scale ratchet, pinned by reading every sheet of scss/.
 *
 * Type, weight, space, radius and motion are literals held to the scales of
 * moodle-dev/docs/design-system/tokens.md by this test, and two of the system's three rules with them: no pill
 * on a control (rule 2) and sentence case (rule 3). Today's off-scale uses are recorded per sheet in the
 * OFF_SCALE_* constants, sheet => value => uses, and the comparison is exact: one more use fails, and so does one
 * fewer, so the change that brings a use onto the scale lowers its count in the same commit and the constants
 * never hold slack a regression could hide in. The scale constants at the top are block_compass's, unchanged
 * ({@see \block_compass\local\scale_ratchet_test}), so the two files can become one fleet template.
 *
 * What is judged is a CSS property's literal value. A Sass variable assignment ($dim-ds-*, Bootstrap's
 * $border-radius*) and a custom property declaration (--dim-ds-radius-*, --dim-ds-motion-*) are not properties
 * the regular expressions name, so the scale's own declarations are never counted as uses of it. A token that
 * is not a literal is not counted either: a var() whatever its fallback (the theme declares the token it reads,
 * so the fallback never paints; the rule is block_compass's), a Sass variable, an interpolation, any function
 * call (calc(), math.div(), a mixin's helper). A value with Sass maths in it (a standalone +, -, * or %, or a
 * / outside a radius) is a computation as a whole and is not counted. Numbers are normalised before they are
 * keyed (.5rem and 0.50rem are 0.5rem); a negative length is on the scale when its absolute value is, and is
 * keyed with its sign when it is not.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class scale_ratchet_test extends \basic_testcase {
    /** @var array The font sizes in rem: the type scale and the headings. */
    private const FONT_SIZES = ['0.75', '0.8125', '0.875', '1', '1.25', '2.5', '2', '1.75', '1.5'];

    /** @var array The font-size keywords that are not a size of their own. */
    private const FONT_SIZE_WORDS = ['inherit', '1em'];

    /** @var array The font weights. */
    private const WEIGHTS = ['400', '500', '600', '700', 'inherit', 'normal', 'bold'];

    /** @var array The space steps in rem: 2, 4, 6, 8, 12, 16, 20 and 32 px, and the 3 px exception. */
    private const SPACE_REM = ['0', '0.125', '0.25', '0.375', '0.5', '0.75', '1', '1.25', '2', '0.1875'];

    /** @var array The space steps in px, the same steps as SPACE_REM. */
    private const SPACE_PX = ['0', '2', '4', '6', '8', '12', '16', '20', '32', '3'];

    /** @var array The space keywords that are on the scale. */
    private const SPACE_WORDS = ['auto', 'inherit', 'initial', 'unset'];

    /** @var array The radius steps in px. */
    private const RADIUS_PX = ['0', '4', '6', '8', '12', '16', '999'];

    /** @var array The radius steps in rem, and the round one (50rem). */
    private const RADIUS_REM = ['0', '0.25', '0.375', '0.5', '0.75', '1', '50'];

    /** @var array The radius values that are not a length of the steps. */
    private const RADIUS_WORDS = ['50%', 'inherit'];

    /** @var array The durations of the motion scale: fast and base. */
    private const DURATIONS = ['150ms', '250ms'];

    /** @var array Every stylesheet under scss/, by its path there: a sheet not named here fails the test. */
    private const SHEETS = [
        'calendar.scss', 'category.scss', 'categoryhero.scss', 'compactnav.scss', 'competency.scss',
        'coursenotice.scss', 'drawer.scss', 'focusmode.scss', 'keycolour.scss', 'landing.scss', 'loginshell.scss',
        'look.scss', 'phonenav.scss', 'post.scss', 'pre.scss', 'profilelayer.scss', 'readingbar.scss', 'sheets.scss',
    ];

    /**
     * @var array Off-scale font sizes today, sheet => value => uses.
     *
     * Lower a count when a use is brought onto the scale; never raise it.
     */
    private const OFF_SCALE_FONT_SIZE = [
        'calendar.scss' => [
            '0.625rem' => 5,
            '0.6875rem' => 8,
            '1.125rem' => 1,
            '1.625rem' => 1,
        ],
        'category.scss' => [
            '0.5625rem' => 1,
            '0.6875rem' => 1,
            '0.68rem' => 1,
            '0.72rem' => 1,
            '0.74rem' => 1,
            '0.75em' => 1,
            '0.78125rem' => 1,
            '0.78rem' => 4,
            '0.7rem' => 1,
            '0.82rem' => 4,
            '0.85rem' => 7,
            '0.86rem' => 2,
            '0.87rem' => 1,
            '0.88rem' => 4,
            '0.8rem' => 2,
            '0.92rem' => 3,
            '0.9375rem' => 5,
            '0.95rem' => 1,
            '0.9rem' => 1,
            '1.02rem' => 1,
            '1.05rem' => 1,
            '1.0625rem' => 1,
            '1.08rem' => 1,
            '1.125rem' => 1,
            '1.12rem' => 1,
            '1.18rem' => 1,
            '1.32rem' => 1,
            '1.35rem' => 3,
            '1.375rem' => 1,
            '1.6rem' => 1,
            '18px' => 1,
        ],
        'categoryhero.scss' => [
            '0.625rem' => 3,
            '0.6875rem' => 2,
            '1.0625rem' => 1,
        ],
        'competency.scss' => [
            '0.875em' => 2,
            '0.9375rem' => 1,
        ],
        'coursenotice.scss' => [
            '0.72rem' => 1,
            '0.82rem' => 1,
            '0.8rem' => 3,
            '0.9375rem' => 1,
            '0.9rem' => 1,
        ],
        'drawer.scss' => [
            '0.9375rem' => 1,
        ],
        'landing.scss' => [
            '0.7rem' => 1,
            '0.9375rem' => 3,
            '1.0625rem' => 2,
            '1.125rem' => 2,
            '1.375rem' => 1,
            '1.3rem' => 1,
            '2.25rem' => 1,
        ],
        'loginshell.scss' => [
            '1.125rem' => 1,
        ],
        'post.scss' => [
            '0.9375rem' => 1,
        ],
        'readingbar.scss' => [
            '0.9375rem' => 1,
        ],
    ];

    /**
     * @var array Off-scale font weights today, sheet => value => uses.
     *
     * Lower a count when a use is brought onto the scale; never raise it.
     */
    private const OFF_SCALE_FONT_WEIGHT = [
        'category.scss' => [
            800 => 6,
        ],
        'landing.scss' => [
            800 => 4,
        ],
    ];

    /**
     * @var array Off-scale space values today, sheet => value => uses.
     *
     * Lower a count when a use is brought onto the scale; never raise it.
     */
    private const OFF_SCALE_SPACE = [
        'calendar.scss' => [
            '-0.625rem' => 1,
            '-1px' => 1,
            '0.4375rem' => 2,
            '0.5625rem' => 1,
            '0.625rem' => 10,
            '0.875rem' => 6,
            '1.125rem' => 1,
            '10px' => 1,
            '1px' => 5,
            '2.125rem' => 1,
            '2.25rem' => 1,
            '2.375rem' => 1,
        ],
        'category.scss' => [
            '-1px' => 1,
            '-5px' => 1,
            '10px' => 37,
            '118px' => 1,
            '128px' => 1,
            '13px' => 1,
            '14px' => 19,
            '164px' => 2,
            '18px' => 12,
            '1px' => 1,
            '22px' => 2,
            '24px' => 3,
            '26px' => 3,
            '28px' => 2,
            '30px' => 1,
            '36px' => 1,
            '40px' => 2,
            '48px' => 1,
            '5px' => 7,
            '7px' => 2,
            '9px' => 2,
        ],
        'categoryhero.scss' => [
            '0.4375rem' => 1,
            '0.5625rem' => 1,
            '0.625rem' => 1,
            '0.6875rem' => 1,
            '0.875rem' => 1,
            '0.9375rem' => 1,
            '1.5rem' => 1,
        ],
        'competency.scss' => [
            '0.2em' => 1,
        ],
        'coursenotice.scss' => [
            '1.75rem' => 1,
            '10px' => 5,
            '14px' => 2,
            '18px' => 2,
            '7px' => 3,
        ],
        'landing.scss' => [
            '-3.5rem' => 2,
            '0.0625rem' => 1,
            '0.35rem' => 1,
            '0.625rem' => 11,
            '0.875rem' => 3,
            '1.125rem' => 2,
            '1.5rem' => 2,
            '1.625rem' => 1,
            '1.75rem' => 6,
            '10px' => 1,
            '2.125rem' => 1,
            '2.5rem' => 2,
            '3.25rem' => 2,
            '4.5rem' => 1,
            '4rem' => 1,
            '56.25%' => 1,
        ],
        'loginshell.scss' => [
            '0.625rem' => 2,
        ],
    ];

    /**
     * @var array Off-scale radius values today, sheet => value => uses.
     *
     * Lower a count when a use is brought onto the scale; never raise it.
     */
    private const OFF_SCALE_RADIUS = [
        'calendar.scss' => [
            '1px' => 1,
            '2px' => 3,
            '3px' => 3,
        ],
        'category.scss' => [
            '10px' => 1,
            '2px' => 2,
        ],
        'keycolour.scss' => [
            '0.4rem' => 1,
        ],
        'landing.scss' => [
            '2px' => 2,
            '5px' => 2,
        ],
        'loginshell.scss' => [
            '0.625rem' => 5,
        ],
    ];

    /**
     * @var array Off-scale durations today, sheet => value => uses.
     *
     * Lower a count when a use is brought onto the scale; never raise it.
     */
    private const OFF_SCALE_MOTION = [
        'calendar.scss' => [
            '0.1s' => 2,
            '4s' => 2,
        ],
        'category.scss' => [
            '0s' => 1,
            // The filter bar's tooltip delay, decided off the motion scale (the Design System's D51, tooltip.md).
            '100ms' => 1,
            '1.3s' => 1,
            '120ms' => 1,
            '12s' => 1,
            '160ms' => 2,
            '180ms' => 1,
            '220ms' => 6,
            '300ms' => 1,
            '400ms' => 1,
            '600ms' => 1,
            '780ms' => 1,
            '900ms' => 1,
        ],
        'compactnav.scss' => [
            '100ms' => 1,
            '2s' => 1,
        ],
        'drawer.scss' => [
            '0s' => 2,
            '1.2s' => 1,
        ],
        'landing.scss' => [
            '1.4s' => 1,
        ],
    ];

    /**
     * @var array Capital-letter transforms, sheet => value => uses (rule 3, sentence case): none, and none may come back.
     *
     * A label is written in sentence case in its language string; no stylesheet changes its case.
     */
    private const OFF_SCALE_UPPERCASE = [];

    /**
     * @var array Pill radii today, sheet => the pill token => declarations (rule 2: only counters are round).
     *
     * Lower a count when a control takes a rounded rectangle, or name the counter it is; never raise it.
     */
    private const OFF_SCALE_PILL = [
        'calendar.scss' => [
            'var(--dim-ds-radius-pill, 50rem)' => 4,
        ],
        'category.scss' => [
            'var(--dim-ds-radius-pill, 50rem)' => 7,
            'var(--dim-radius-pill)' => 7,
        ],
        'competency.scss' => [
            'var(--dim-ds-radius-pill)' => 6,
        ],
        'landing.scss' => [
            'var(--dim-ds-radius-pill)' => 3,
        ],
        'phonenav.scss' => [
            'var(--dim-ds-radius-pill)' => 1,
        ],
        'post.scss' => [
            'var(--dim-ds-radius-pill)' => 3,
        ],
        'sheets.scss' => [
            'var(--dim-ds-radius-pill)' => 2,
        ],
    ];

    /**
     * The theme's root directory.
     *
     * @return string An absolute path.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every stylesheet under scss/, at any depth, so a partial in a subdirectory is read too.
     *
     * @return array Path relative to scss/ => source, sorted by path.
     */
    private function sheets(): array {
        $root = $this->plugin_root() . '/scss/';
        $sheets = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'scss') {
                $path = substr($file->getPathname(), strlen($root));
                $sheets[$path] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($sheets, SORT_STRING);

        return $sheets;
    }

    /**
     * A sheet's statements, with comments removed and the nesting that holds each one.
     *
     * The reader walks the source once: a { outside parentheses, quotes and #{...} opens a block whose header
     * (a selector, an at-rule, a mixin call) joins the nesting; a ; or } closes the statement before it. A
     * comment becomes as many newlines as it held, so every line number still points at the source.
     *
     * @param string $source The SCSS source.
     * @return array A list of [line, innermost block header, statement on one line].
     */
    private function statements(string $source): array {
        $source = preg_replace_callback('~/\*.*?\*/~s', function (array $match): string {
            return str_repeat("\n", substr_count($match[0], "\n"));
        }, $source);
        // A // only after whitespace or at a line's start: a url() keeps its scheme.
        $source = preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
        $statements = [];
        $stack = [];
        $buffer = '';
        $line = 1;
        $start = 1;
        $parens = 0;
        $interpolation = 0;
        $quote = '';
        $length = strlen($source);
        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            if ($quote === '' && trim($buffer) === '' && !ctype_space($char)) {
                $start = $line;
            }
            if ($char === "\n") {
                $line++;
            }
            if ($quote !== '') {
                if ($char === $quote && $source[$i - 1] !== '\\') {
                    $quote = '';
                }
            } else if ($char === '"' || $char === "'") {
                $quote = $char;
            } else if ($char === '#' && ($source[$i + 1] ?? '') === '{') {
                $interpolation++;
                $buffer .= '#{';
                $i++;
                continue;
            } else if ($interpolation > 0) {
                if ($char === '}') {
                    $interpolation--;
                }
            } else if ($char === '(') {
                $parens++;
            } else if ($char === ')') {
                $parens--;
            } else if ($parens === 0 && ($char === '{' || $char === ';' || $char === '}')) {
                $text = trim((string) preg_replace('/\s+/', ' ', $buffer));
                if ($char === '{') {
                    $stack[] = $text;
                } else if ($text !== '') {
                    $statements[] = [$start, (string) end($stack), $text];
                }
                if ($char === '}') {
                    array_pop($stack);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }

        return $statements;
    }

    /**
     * Every declaration whose property matches, with the value trimmed of its !important and !default.
     *
     * @param string $property A regular expression body matched against the whole property name.
     * @param array|null $sheets Path => source to read; null reads every sheet of scss/.
     * @return array A list of [sheet, "sheet:line selector { property: value }", property, value, innermost selector].
     */
    private function declarations(string $property, ?array $sheets = null): array {
        $found = [];
        foreach ($sheets ?? $this->sheets() as $sheet => $source) {
            foreach ($this->statements($source) as [$line, $selector, $statement]) {
                $colon = strpos($statement, ':');
                if ($colon === false) {
                    continue;
                }
                $name = strtolower(trim(substr($statement, 0, $colon)));
                if (!preg_match('/^(?:' . $property . ')$/', $name)) {
                    continue;
                }
                $value = trim(substr($statement, $colon + 1));
                $value = trim((string) preg_replace('/(?:\s*!(?:important|default)\b)+$/i', '', $value));
                $found[] = [$sheet, "{$sheet}:{$line} {$selector} { {$name}: {$value} }", $name, $value, $selector];
            }
        }

        return $found;
    }

    /**
     * A value split on whitespace outside parentheses and interpolations.
     *
     * @param string $value A declaration value.
     * @return array The tokens, in order.
     */
    private function tokens(string $value): array {
        $tokens = [];
        $depth = 0;
        $current = '';
        foreach (str_split($value) as $char) {
            if ($char === '(' || $char === '{') {
                $depth++;
            } else if ($char === ')' || $char === '}') {
                $depth--;
            }
            if ($depth === 0 && ctype_space($char)) {
                if ($current !== '') {
                    $tokens[] = $current;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * Whether a token is not a literal: a Sass variable, an interpolation or a function call (var() among them).
     *
     * @param string $token One token of a value.
     * @return bool True when it is not judged.
     */
    private function is_computed(string $token): bool {
        return (bool) preg_match('/\$|#\{|^-?[a-z_][\w.-]*\(|[*+]/i', $token);
    }

    /**
     * Whether a value is Sass maths: an operator standing alone between its operands.
     *
     * @param array $tokens The value's tokens.
     * @param bool $slash Whether a lone / counts as division; a radius reads it as the elliptical separator.
     * @return bool True when the value is computed as a whole.
     */
    private function is_maths(array $tokens, bool $slash): bool {
        $operators = $slash ? ['+', '-', '*', '%', '/'] : ['+', '-', '*', '%'];

        return count(array_intersect($tokens, $operators)) > 0;
    }

    /**
     * A literal number with its unit, spelled one way: a leading zero, no trailing zeros, the unit lowercase.
     *
     * @param string $token One token of a value.
     * @return string The normalised token; a token that is not a number with a unit comes back lowercase.
     */
    private function normalise(string $token): string {
        $token = strtolower($token);
        if (!preg_match('/^(-?)(\d*\.?\d+)([a-z%]*)$/', $token, $m)) {
            return $token;
        }
        $number = $m[2];
        if (str_starts_with($number, '.')) {
            $number = '0' . $number;
        }
        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }
        $number = ltrim($number, '0');
        if ($number === '' || str_starts_with($number, '.')) {
            $number = '0' . $number;
        }

        return $m[1] . $number . $m[3];
    }

    /**
     * Whether a length is one of the steps, in the unit it is written in.
     *
     * @param string $token One normalised token of a value.
     * @param array $rem The steps in rem.
     * @param array $px The steps in px.
     * @return bool True when the length is a step; a negative length counts by its absolute value.
     */
    private function on_length_scale(string $token, array $rem, array $px): bool {
        if (!preg_match('/^-?(\d*\.?\d+)(rem|px)?$/i', $token, $m)) {
            return false;
        }
        $number = (float) $m[1];
        $unit = strtolower($m[2] ?? '');
        if ($unit === '') {
            return $number == 0;
        }
        foreach ($unit === 'rem' ? $rem : $px as $step) {
            if ($number == (float) $step) {
                return true;
            }
        }

        return false;
    }

    /**
     * The off-scale font sizes among some declarations.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_font_sizes(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, , $value]) {
            $token = $this->normalise($value);
            $on = $this->is_computed($token) || in_array($token, self::FONT_SIZE_WORDS, true);
            if (!$on && preg_match('/^(\d*\.?\d+)rem$/', $token, $m)) {
                $on = in_array((float) $m[1], array_map('floatval', self::FONT_SIZES), true);
            }
            if (!$on && !$this->is_maths($this->tokens($value), true)) {
                $off[] = [$sheet, $token, $use];
            }
        }

        return $off;
    }

    /**
     * The off-scale font weights among some declarations.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_font_weights(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, , $value]) {
            $token = strtolower($value);
            if (
                !$this->is_computed($token)
                && !in_array($token, self::WEIGHTS, true)
                && !$this->is_maths($this->tokens($value), true)
            ) {
                $off[] = [$sheet, $token, $use];
            }
        }

        return $off;
    }

    /**
     * The off-scale space lengths among some declarations, each length of a shorthand on its own.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_space(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, , $value]) {
            $tokens = $this->tokens($value);
            if ($this->is_maths($tokens, true)) {
                continue;
            }
            foreach ($tokens as $token) {
                $token = $this->normalise($token);
                if (
                    $this->is_computed($token)
                    || in_array($token, self::SPACE_WORDS, true)
                    || $this->on_length_scale($token, self::SPACE_REM, self::SPACE_PX)
                ) {
                    continue;
                }
                $off[] = [$sheet, $token, $use];
            }
        }

        return $off;
    }

    /**
     * The off-scale radius lengths among some declarations, each length of a shorthand on its own.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_radii(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, , $value]) {
            $tokens = $this->tokens($value);
            if ($this->is_maths($tokens, false)) {
                continue;
            }
            foreach ($tokens as $token) {
                $token = $this->normalise($token);
                if (
                    $token === '/'
                    || $this->is_computed($token)
                    || in_array($token, self::RADIUS_WORDS, true)
                    || $this->on_length_scale($token, self::RADIUS_REM, self::RADIUS_PX)
                ) {
                    continue;
                }
                $off[] = [$sheet, $token, $use];
            }
        }

        return $off;
    }

    /**
     * The off-scale durations among some declarations; a duration inside a var() is the token's fallback.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_durations(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, , $value]) {
            $bare = $value;
            do {
                $bare = preg_replace('/(?:var\(|#\{)[^(){}]*[)}]/i', '', $bare, -1, $replaced);
            } while ($replaced > 0);
            $bare = preg_replace('/\$[\w-]+/', '', $bare);
            preg_match_all('/(?<![\w.-])(\d+ms|\d*\.?\d+s)(?![\w-])/i', $bare, $found);
            foreach ($found[1] as $duration) {
                $duration = $this->normalise($duration);
                if (!in_array($duration, self::DURATIONS, true)) {
                    $off[] = [$sheet, $duration, $use];
                }
            }
        }

        return $off;
    }

    /**
     * The capital-letter transforms among some declarations: uppercase, and the small capitals that read as one.
     *
     * A capital initial is sentence case, not a capital-letter label: rule 3 forbids setting a label in capitals, and a
     * text that arrives in lower case (Intl's month names in Portuguese) needs its first letter raised to read as a
     * sentence. So an uppercase or capitalize transform in a rule whose innermost selector ends in ::first-letter, in
     * every part of its list, is not counted; the same declaration on the element itself, or in a list where one part
     * is the element, is.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_case(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, $property, $value, $selector]) {
            $value = strtolower($value);
            $initial = $property === 'text-transform' && in_array($value, ['uppercase', 'capitalize'], true)
                && $this->first_letter_only($selector);
            if (
                !$initial
                && (($property === 'text-transform' && $value === 'uppercase')
                    || ($property !== 'text-transform' && preg_match('/^(?:all-)?small-caps$/', $value)))
            ) {
                $off[] = [$sheet, $value, $use];
            }
        }

        return $off;
    }

    /**
     * Whether every selector of a list ends in the ::first-letter pseudo-element, so a rule paints a first letter only.
     *
     * @param string $selector An innermost block header, as statements() keeps it.
     * @return bool True when no part of the list is the element itself.
     */
    private function first_letter_only(string $selector): bool {
        foreach (explode(',', $selector) as $part) {
            if (!preg_match('/::first-letter$/i', trim($part))) {
                return false;
            }
        }

        return $selector !== '';
    }

    /**
     * The pill radii among some declarations, one per declaration, keyed by its first pill token.
     *
     * A pill is a token named for one (var(--dim-ds-radius-pill), var(--dim-radius-pill), a $...pill variable,
     * with or without a fallback), or a literal of 50rem, 999px or more: past half a control's height every radius
     * draws the same capsule. 50% is a circle or an ellipse, not a pill, and is not counted.
     *
     * @param array $declarations From declarations().
     * @return array A list of [sheet, value, use].
     */
    private function off_pills(array $declarations): array {
        $off = [];
        foreach ($declarations as [$sheet, $use, , $value]) {
            foreach ($this->tokens($value) as $token) {
                $token = $this->normalise($token);
                $literal = preg_match('/^(\d*\.?\d+)(rem|px)$/', $token, $m)
                    && (float) $m[1] >= ($m[2] === 'rem' ? 50 : 999);
                if ($literal || preg_match('/^var\(\s*--[\w-]*pill|^\$[\w-]*pill/', $token)) {
                    $off[] = [$sheet, $token, $use];
                    break;
                }
            }
        }

        return $off;
    }

    /**
     * A ratchet constant, or the uses found, sorted by sheet and then by value, with no empty sheet.
     *
     * @param array $counts Sheet => value => uses.
     * @return array The same counts in one order.
     */
    private function sorted(array $counts): array {
        $counts = array_filter($counts);
        ksort($counts, SORT_STRING);
        foreach ($counts as $sheet => $values) {
            ksort($values, SORT_STRING);
            $counts[$sheet] = $values;
        }

        return $counts;
    }

    /**
     * Compare the off-scale uses found with the recorded ones, and say for each difference what to do.
     *
     * @param array $off A list of [sheet, value, use] for every off-scale use.
     * @param array $expected The constant, sheet => value => uses.
     * @param string $name The constant's name, for the message.
     * @return void
     */
    private function assert_ratchet(array $off, array $expected, string $name): void {
        $counts = [];
        $where = [];
        foreach ($off as [$sheet, $value, $use]) {
            $counts[$sheet][$value] = ($counts[$sheet][$value] ?? 0) + 1;
            $where[$sheet][$value][] = $use;
        }
        $counts = $this->sorted($counts);
        $expected = $this->sorted($expected);
        $lines = [];
        $sheets = array_unique(array_merge(array_keys($counts), array_keys($expected)));
        sort($sheets, SORT_STRING);
        foreach ($sheets as $sheet) {
            $values = array_unique(array_merge(array_keys($counts[$sheet] ?? []), array_keys($expected[$sheet] ?? [])));
            sort($values, SORT_STRING);
            foreach ($values as $value) {
                $found = $counts[$sheet][$value] ?? 0;
                $allowed = $expected[$sheet][$value] ?? 0;
                if ($found === $allowed) {
                    continue;
                }
                $lines[] = "{$sheet}: {$value} found {$found} times, {$name} allows {$allowed}: " . ($found > $allowed
                    ? 'bring the new use onto the scale; never raise the count.'
                    : "a use came onto the scale: lower {$name}['{$sheet}']['{$value}'] to {$found} (drop it at 0)"
                        . ' in the same change.');
                foreach ($where[$sheet][$value] ?? [] as $use) {
                    $lines[] = '    ' . $use;
                }
            }
        }
        $this->assertSame($expected, $counts, implode("\n", $lines) . "\nFound: " . var_export($counts, true));
    }

    /**
     * Every stylesheet is named, so a new one cannot escape the ratchet, and every constant names known sheets only.
     *
     * Changes that must make it fail: a new file under scss/, or a sheet renamed.
     *
     * @return void
     */
    public function test_every_sheet_is_named(): void {
        $this->assertSame(self::SHEETS, array_keys($this->sheets()), 'A stylesheet was added, renamed or removed under'
            . ' scss/: name it in SHEETS, and record its off-scale uses in the OFF_SCALE_* constants.');
        $constants = [
            'OFF_SCALE_FONT_SIZE' => self::OFF_SCALE_FONT_SIZE,
            'OFF_SCALE_FONT_WEIGHT' => self::OFF_SCALE_FONT_WEIGHT,
            'OFF_SCALE_SPACE' => self::OFF_SCALE_SPACE,
            'OFF_SCALE_RADIUS' => self::OFF_SCALE_RADIUS,
            'OFF_SCALE_MOTION' => self::OFF_SCALE_MOTION,
            'OFF_SCALE_UPPERCASE' => self::OFF_SCALE_UPPERCASE,
            'OFF_SCALE_PILL' => self::OFF_SCALE_PILL,
        ];
        foreach ($constants as $name => $constant) {
            $this->assertSame([], array_diff(array_keys($constant), self::SHEETS), "{$name} names a sheet SHEETS does not.");
        }
    }

    /**
     * The reader keeps what the ratchets must judge and drops what they must not, on a sheet written for it.
     *
     * Changes that must make it fail: comments read as code, a Sass variable or a token declaration judged, a
     * var() fallback or Sass maths counted, a nested rule lost, .5rem keyed apart from 0.5rem, a ::first-letter
     * capital initial counted as a capital-letter label, or the same transform on the element (alone or in a list
     * with its first letter) let through.
     *
     * @return void
     */
    public function test_the_reader_judges_literals_only(): void {
        $source = implode("\n", [
            '$dim-ds-gap: 10px !default;',
            '// padding: 10px;',
            '/* margin: 10px;',
            '   gap: 10px; */',
            '.a-#{$b} {',
            '    --dim-ds-radius-md: 10px;',
            '    padding: 6px .7rem 0.70rem -5px;',
            '    margin: -8px auto $dim-ds-gap calc(1px + 2px);',
            '    gap: var(--dim-gap, 10px);',
            '    margin-inline: $a * 2 #{$c}px;',
            '    border-radius: 10px / 50% var(--dim-ds-radius-pill, 50rem);',
            '    @media (min-width: 768px) {',
            '        &:hover { padding-top: 1px; font-size: .9rem; font-weight: 650; text-transform: uppercase }',
            '    }',
            '    transition: opacity .3s, color var(--dim-ds-motion-fast, 120ms) $ease, top 150ms;',
            '    background: url("data:image/svg+xml;utf8,<svg></svg>");',
            '}',
            '.b::first-letter { text-transform: uppercase; }',
            '.b { text-transform: uppercase; }',
            '.c::first-letter, .c { text-transform: uppercase; }',
        ]);
        $sheets = ['fixture.scss' => $source];
        $keys = function (array $off): array {
            return array_map(function (array $use): string {
                return $use[1] . ' @ ' . strtok($use[2], ' ');
            }, $off);
        };
        $this->assertSame(
            ['0.7rem @ fixture.scss:7', '0.7rem @ fixture.scss:7', '-5px @ fixture.scss:7', '1px @ fixture.scss:13'],
            $keys($this->off_space($this->declarations('(?:row-|column-)?gap|(?:padding|margin)(?:-[a-z-]+)?', $sheets)))
        );
        $this->assertSame(['10px @ fixture.scss:11'], $keys($this->off_radii($this->declarations('border-radius', $sheets))));
        $this->assertSame(
            ['var(--dim-ds-radius-pill, 50rem) @ fixture.scss:11'],
            $keys($this->off_pills($this->declarations('border-radius', $sheets)))
        );
        $this->assertSame(['0.9rem @ fixture.scss:13'], $keys($this->off_font_sizes($this->declarations('font-size', $sheets))));
        $this->assertSame(['650 @ fixture.scss:13'], $keys($this->off_font_weights($this->declarations('font-weight', $sheets))));
        $this->assertSame(
            ['uppercase @ fixture.scss:13', 'uppercase @ fixture.scss:19', 'uppercase @ fixture.scss:20'],
            $keys($this->off_case($this->declarations('text-transform', $sheets))),
            'A first letter raised is sentence case (line 18); the element in capitals is not (13, 19, 20).'
        );
        $this->assertSame(
            ['0.3s @ fixture.scss:15'],
            $keys($this->off_durations($this->declarations('(?:transition|animation)(?:-[a-z-]+)?', $sheets)))
        );
    }

    /**
     * Font sizes sit on the type scale, and the ones that do not are only ever fewer.
     *
     * Changes that must make it fail: a font-size of 0.83rem, or one use fewer or more of a listed size.
     *
     * @return void
     */
    public function test_font_sizes_are_on_the_type_scale(): void {
        $declarations = $this->declarations('font-size');
        $this->assertGreaterThanOrEqual(300, count($declarations), 'fewer than 300 font-size declarations read');
        $this->assert_ratchet($this->off_font_sizes($declarations), self::OFF_SCALE_FONT_SIZE, 'OFF_SCALE_FONT_SIZE');
    }

    /**
     * Font weights sit on the weight scale, and the ones that do not are only ever fewer.
     *
     * Changes that must make it fail: a font-weight of 800, or one use fewer or more of a listed weight.
     *
     * @return void
     */
    public function test_font_weights_are_on_the_weight_scale(): void {
        $declarations = $this->declarations('font-weight');
        $this->assertGreaterThanOrEqual(200, count($declarations), 'fewer than 200 font-weight declarations read');
        $this->assert_ratchet($this->off_font_weights($declarations), self::OFF_SCALE_FONT_WEIGHT, 'OFF_SCALE_FONT_WEIGHT');
    }

    /**
     * Gaps, paddings and margins sit on the space scale, each length of a shorthand on its own.
     *
     * Changes that must make it fail: a padding of 0.7rem, or one use fewer or more of a listed value.
     *
     * @return void
     */
    public function test_space_is_on_the_space_scale(): void {
        $declarations = $this->declarations('(?:row-|column-)?gap|(?:padding|margin)(?:-[a-z-]+)?');
        $this->assertGreaterThanOrEqual(900, count($declarations), 'fewer than 900 space declarations read');
        $this->assert_ratchet($this->off_space($declarations), self::OFF_SCALE_SPACE, 'OFF_SCALE_SPACE');
    }

    /**
     * Radii sit on the radius scale, each length of a shorthand on its own.
     *
     * Changes that must make it fail: a border-radius of 10px, or one use fewer or more of a listed value.
     *
     * @return void
     */
    public function test_radii_are_on_the_radius_scale(): void {
        $declarations = $this->declarations('border-(?:(?:top|bottom|start|end)-(?:left|right|start|end)-)?radius');
        $this->assertGreaterThanOrEqual(250, count($declarations), 'fewer than 250 radius declarations read');
        $this->assert_ratchet($this->off_radii($declarations), self::OFF_SCALE_RADIUS, 'OFF_SCALE_RADIUS');
    }

    /**
     * Transition and animation durations are the fast or the base step, or come from a var().
     *
     * Changes that must make it fail: a transition of 0.3s, or one use fewer or more of a listed duration.
     *
     * @return void
     */
    public function test_durations_are_on_the_motion_scale(): void {
        $declarations = $this->declarations('(?:transition|animation)(?:-[a-z-]+)?');
        $this->assertGreaterThanOrEqual(60, count($declarations), 'fewer than 60 transition and animation declarations read');
        $this->assert_ratchet($this->off_durations($declarations), self::OFF_SCALE_MOTION, 'OFF_SCALE_MOTION');
    }

    /**
     * Labels are in sentence case: no uppercase transform and no small capitals anywhere (rule 3).
     *
     * Rule 3 forbids a label set in capitals, not a capital initial: the calendar's month names arrive from Intl in the
     * language's own case (lower case in Portuguese), and the one transform the sheets keep raises only their first
     * letter (::first-letter), which off_case() does not count. That rule is also the control that the reader reaches
     * text-transform declarations, beside the theme's tabular figures for the font variants: an empty list of uses then
     * means no capitals, not a reader that read nothing.
     *
     * Changes that must make it fail: one text-transform: uppercase on an element, or a small-caps font variant, in any
     * sheet; the month name's uppercase moved from its first letter to the element itself.
     *
     * @return void
     */
    public function test_labels_are_in_sentence_case(): void {
        $declarations = $this->declarations('text-transform|font-variant(?:-caps)?');
        $this->assertGreaterThanOrEqual(
            1,
            count($this->declarations('text-transform')),
            'no text-transform declaration read: the month name\'s first letter is one'
        );
        $this->assertGreaterThanOrEqual(
            40,
            count($this->declarations('font-variant-numeric')),
            'fewer than 40 font-variant-numeric declarations read'
        );
        $this->assert_ratchet($this->off_case($declarations), self::OFF_SCALE_UPPERCASE, 'OFF_SCALE_UPPERCASE');
    }

    /**
     * No control is a pill (rule 2), and the pills there are only ever fewer.
     *
     * Changes that must make it fail: one more border-radius of var(--dim-ds-radius-pill) or 50rem, or one fewer.
     *
     * @return void
     */
    public function test_no_new_pill(): void {
        $declarations = $this->declarations('border-(?:(?:top|bottom|start|end)-(?:left|right|start|end)-)?radius');
        $this->assertGreaterThanOrEqual(250, count($declarations), 'fewer than 250 radius declarations read');
        $this->assert_ratchet($this->off_pills($declarations), self::OFF_SCALE_PILL, 'OFF_SCALE_PILL');
    }
}
