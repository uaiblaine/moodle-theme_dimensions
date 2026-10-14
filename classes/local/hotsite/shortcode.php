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
 * Theme Dimensions - Short codes that can be read out loud
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

/**
 * Draws the short code of a hotsite's public address.
 *
 * Core's own generator (\core\shortlink) draws from a mixed-case alphabet with
 * underscore and hyphen: fine for a link that is clicked, useless for one that
 * is dictated over the phone or copied from a slide. A hotsite address is both,
 * so its code is a pronounceable six-letter word of three consonant-vowel
 * syllables ("batepo"): lower case only, because case cannot be spoken; no
 * digits and no l, so nothing looks like 1, I or 0; and no c, g, q, x, h or z,
 * whose sound in Portuguese depends on the letter after them. Twelve consonants
 * by five vowels, three times over, is 216,000 codes: plenty for a site's
 * hotsites, and one a person can repeat after hearing it once.
 *
 * The generator only draws. Uniqueness against the shortlink table is
 * {@see links}'s business. A blocklist of roots keeps a draw from spelling a
 * Portuguese or English profanity on an institution's printed material; it is
 * hygiene, not a guarantee, so extend it the day a code embarrasses somebody.
 *
 * Pure: no database, no globals. Tests hand in their own picker.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcode {
    /** @var int Length of a code, in letters. Even, so that a code is whole syllables. */
    public const LENGTH = 6;

    /** @var string The consonants a code may use: no l, and none whose sound depends on the next letter. */
    public const CONSONANTS = 'bdfjkmnprstv';

    /** @var string The vowels a code may use. */
    public const VOWELS = 'aeiou';

    /** @var int How many draws generate() makes before concluding that the picker is broken. */
    private const MAX_DRAWS = 50;

    /**
     * Roots no code may contain: Portuguese and English words that must not reach a printed address.
     *
     * Only roots the syllable shape can spell are listed; "merda" or "bunda" carry two consonants
     * in a row and cannot come out of a consonant-vowel draw.
     *
     * @var string[]
     */
    private const BLOCKED = [
        'bebad', 'boket', 'buset', 'dik', 'fod', 'fud', 'fuk', 'jeb', 'kak', 'kok', 'kum', 'mamad',
        'mij', 'peni', 'pik', 'piru', 'put', 'rab', 'safad', 'sakan', 'sifu', 'surub', 'tarad',
        'tesud', 'teta', 'tit',
    ];

    /**
     * Draw a code.
     *
     * @param callable|null $pick Picks one character from the alphabet it is given; random when null.
     * @return string A well-formed code containing no blocked root.
     * @throws \coding_exception When the picker yields nothing acceptable in MAX_DRAWS draws.
     */
    public static function generate(?callable $pick = null): string {
        $pick = $pick ?? static function (string $alphabet): string {
            return $alphabet[random_int(0, strlen($alphabet) - 1)];
        };
        for ($draw = 0; $draw < self::MAX_DRAWS; $draw++) {
            $code = '';
            for ($position = 0; $position < self::LENGTH; $position++) {
                $code .= $pick(($position % 2 === 0) ? self::CONSONANTS : self::VOWELS);
            }
            if (self::is_acceptable($code)) {
                return $code;
            }
        }
        throw new \coding_exception('The short code picker yielded nothing acceptable in ' . self::MAX_DRAWS . ' draws.');
    }

    /**
     * Whether a code has the shape generate() draws: LENGTH letters in consonant-vowel syllables from the two alphabets.
     *
     * @param string $code The code.
     * @return bool True when well formed.
     */
    public static function is_well_formed(string $code): bool {
        $syllables = intdiv(self::LENGTH, 2);
        return (bool) preg_match('/^([' . self::CONSONANTS . '][' . self::VOWELS . ']){' . $syllables . '}$/', $code);
    }

    /**
     * Whether a code contains a blocked root.
     *
     * @param string $code The code.
     * @return bool True when blocked.
     */
    public static function is_blocked(string $code): bool {
        foreach (self::BLOCKED as $root) {
            if (str_contains($code, $root)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a code may be used: well formed and not blocked.
     *
     * @param string $code The code.
     * @return bool True when acceptable.
     */
    public static function is_acceptable(string $code): bool {
        return self::is_well_formed($code) && !self::is_blocked($code);
    }
}
