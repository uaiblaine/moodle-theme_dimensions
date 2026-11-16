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
 * Theme Dimensions - The display patterns of the identity fields
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The display patterns: the setting's lines, and the mask one pattern applies to one value.
 *
 * The setting holds one "field=pattern" per line. In a pattern, {@see SHOW} prints the value's next
 * character, {@see HIDE} consumes one and prints {@see HIDDEN}, and any other character is printed as
 * written. Only the value's letters and digits fill the slots, so separators already stored with the
 * value ("123.456.789-09") change nothing. A value whose count of letters and digits is not the
 * pattern's count of slots is returned as stored: a pattern is a format for values of one length, not
 * a rule that withholds a field.
 *
 * Pure: no database, no configuration, no session. {@see identity} decides which field a pattern
 * belongs to and applies it on the server, so a hidden character never reaches the page;
 * {@see identitypatterns_setting} refuses a line {@see errors()} reports.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class identitypattern {
    /** @var string The slot that shows the value's next character. */
    public const SHOW = '#';

    /** @var string The slot that hides the value's next character. */
    public const HIDE = '*';

    /** @var string What a hidden character is printed as: a bullet, U+2022. */
    public const HIDDEN = "\u{2022}";

    /** @var string A line without "=". */
    public const ERROR_NOEQUALS = 'noequals';

    /** @var string A line naming a field outside the vocabulary. */
    public const ERROR_UNKNOWNFIELD = 'unknownfield';

    /** @var string A pattern with no slot, which would format nothing. */
    public const ERROR_NOSLOT = 'noslot';

    /** @var string A field an earlier line already named. */
    public const ERROR_DUPLICATE = 'duplicate';

    /**
     * The setting's lines as they were typed, numbered from 1, without the empty ones.
     *
     * Each is the field (null for a line without "="), the pattern, both trimmed, and the line's text.
     *
     * @param string $setting The setting's value.
     * @return array Line number => ['field' => string|null, 'pattern' => string, 'text' => string].
     */
    private static function lines(string $setting): array {
        $lines = [];
        foreach (preg_split('/\R/u', $setting) ?: [] as $index => $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $cut = strpos($text, '=');
            $lines[$index + 1] = [
                'field' => $cut === false ? null : trim(substr($text, 0, $cut)),
                'pattern' => $cut === false ? '' : trim(substr($text, $cut + 1)),
                'text' => $text,
            ];
        }

        return $lines;
    }

    /**
     * How many slots a pattern has.
     *
     * @param string $pattern The pattern.
     * @return int
     */
    public static function slots(string $pattern): int {
        return substr_count($pattern, self::SHOW) + substr_count($pattern, self::HIDE);
    }

    /**
     * The patterns of the setting, by field.
     *
     * Lenient, because it reads a stored value on every page: a line the setting's validation would
     * refuse is skipped, and of two lines naming one field the first stands. Whether a field exists is
     * the caller's question; an unknown one is simply never asked for.
     *
     * @param string $setting The setting's value.
     * @return array Field => pattern.
     */
    public static function parse(string $setting): array {
        $patterns = [];
        foreach (self::lines($setting) as $line) {
            if ($line['field'] === null || $line['field'] === '' || self::slots($line['pattern']) === 0) {
                continue;
            }
            if (!array_key_exists($line['field'], $patterns)) {
                $patterns[$line['field']] = $line['pattern'];
            }
        }

        return $patterns;
    }

    /**
     * What the setting's validation refuses, line by line.
     *
     * @param string $setting The value being saved.
     * @param array $fields The field vocabulary, as a list of keys.
     * @return array A list of ['line' => int, 'error' => one of the ERROR_ constants, 'text' => the line].
     */
    public static function errors(string $setting, array $fields): array {
        $errors = [];
        $seen = [];
        foreach (self::lines($setting) as $number => $line) {
            $error = null;
            if ($line['field'] === null) {
                $error = self::ERROR_NOEQUALS;
            } else if (!in_array($line['field'], $fields, true)) {
                $error = self::ERROR_UNKNOWNFIELD;
            } else if (self::slots($line['pattern']) === 0) {
                $error = self::ERROR_NOSLOT;
            } else if (isset($seen[$line['field']])) {
                $error = self::ERROR_DUPLICATE;
            }
            if ($error !== null) {
                $errors[] = ['line' => $number, 'error' => $error, 'text' => $line['text']];
                continue;
            }
            $seen[$line['field']] = true;
        }

        return $errors;
    }

    /**
     * A value as its pattern shows it, in plain text.
     *
     * The value is normalised to NFC first, so a letter stored as a base and a combining accent fills
     * one slot. The result is not escaped: that is the template's.
     *
     * @param string $value The stored value.
     * @param string $pattern The field's pattern.
     * @return string The masked value, or the value as stored when the pattern has no slot or does not fit it.
     */
    public static function mask(string $value, string $pattern): string {
        $slots = self::slots($pattern);
        if ($slots === 0) {
            return $value;
        }
        $normal = \Normalizer::normalize($value, \Normalizer::FORM_C);
        if (!is_string($normal)) {
            return $value;
        }
        // Separators, spaces and the slot characters themselves are not part of what a pattern formats.
        if (preg_match_all('/[\p{L}\p{N}]/u', $normal, $found) === false) {
            return $value;
        }
        $characters = $found[0];
        if (count($characters) !== $slots) {
            return $value;
        }

        $masked = '';
        $next = 0;
        foreach (mb_str_split($pattern) as $symbol) {
            if ($symbol === self::SHOW) {
                $masked .= $characters[$next++];
            } else if ($symbol === self::HIDE) {
                $next++;
                $masked .= self::HIDDEN;
            } else {
                $masked .= $symbol;
            }
        }

        return $masked;
    }
}
