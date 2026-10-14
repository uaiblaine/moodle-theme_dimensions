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
 * Theme Dimensions - Tests for the short code generator
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the short code generator.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shortcode::class)]
final class shortcode_test extends \basic_testcase {
    /**
     * A picker that hands out the given letters in order, whatever alphabet it is shown.
     *
     * @param string $letters The letters to hand out.
     * @return callable The picker.
     */
    private static function scripted_picker(string $letters): callable {
        $queue = str_split($letters);
        return static function (string $alphabet) use (&$queue): string {
            $letter = array_shift($queue);
            if ($letter === null) {
                throw new \RuntimeException('The scripted picker ran dry.');
            }
            return $letter;
        };
    }

    /**
     * Every draw is three spoken syllables from the safe alphabet, lower case, six letters.
     */
    public function test_a_code_is_three_spoken_syllables_from_the_safe_alphabet(): void {
        for ($draw = 0; $draw < 300; $draw++) {
            $code = shortcode::generate();
            $this->assertSame(shortcode::LENGTH, strlen($code));
            $this->assertMatchesRegularExpression('/^([bdfjkmnprstv][aeiou]){3}$/', $code);
            $this->assertTrue(shortcode::is_acceptable($code));
        }
    }

    /**
     * The alphabet holds no look-alike, no digit, no upper case and no letter whose sound depends on the next.
     */
    public function test_the_alphabet_holds_no_look_alike_and_no_context_dependent_letter(): void {
        $alphabet = shortcode::CONSONANTS . shortcode::VOWELS;
        foreach (str_split('lcgqxhz0123456789IO_-') as $letter) {
            $this->assertStringNotContainsString($letter, $alphabet, "The alphabet must not hold '$letter'.");
        }
        $this->assertSame(strtolower($alphabet), $alphabet);
        $this->assertSame(strlen($alphabet), count(array_unique(str_split($alphabet))), 'No letter twice.');
    }

    /**
     * A draw that spells a blocked word is thrown away and the next one used.
     */
    public function test_a_blocked_word_is_drawn_again(): void {
        $this->assertSame('batepo', shortcode::generate(self::scripted_picker('putaka' . 'batepo')));
        $this->assertSame('tamebo', shortcode::generate(self::scripted_picker('safada' . 'tamebo')));
    }

    /**
     * What is acceptable: the exact shape, and none of the blocked roots.
     */
    public function test_is_acceptable_rejects_the_wrong_shape_and_the_blocklist(): void {
        $this->assertTrue(shortcode::is_acceptable('batepo'));
        $this->assertTrue(shortcode::is_acceptable('tamebo'));
        $this->assertFalse(shortcode::is_acceptable('putaka'), 'A blocked root.');
        $this->assertFalse(shortcode::is_acceptable('safada'), 'A blocked word the shape spells exactly.');
        $this->assertFalse(shortcode::is_acceptable('Batepo'), 'Upper case.');
        $this->assertFalse(shortcode::is_acceptable('batep'), 'Too short.');
        $this->assertFalse(shortcode::is_acceptable('batepos'), 'Too long.');
        $this->assertFalse(shortcode::is_acceptable('latepo'), 'A letter outside the alphabet.');
        $this->assertFalse(shortcode::is_acceptable('bbtepo'), 'Two consonants in a row.');
        $this->assertFalse(shortcode::is_acceptable('ba1epo'), 'A digit.');
        $this->assertFalse(shortcode::is_acceptable(''), 'Nothing.');
        $this->assertTrue(shortcode::is_blocked('kumade'));
        $this->assertFalse(shortcode::is_blocked('batepo'));
    }

    /**
     * A picker that never yields anything acceptable is reported, not looped over for ever.
     */
    public function test_a_broken_picker_is_reported_rather_than_looped_for_ever(): void {
        $this->expectException(\coding_exception::class);
        shortcode::generate(static function (string $alphabet): string {
            return 'l';
        });
    }
}
