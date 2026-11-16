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
 * Theme Dimensions - Tests for the display patterns of the identity fields
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the display patterns: what a pattern shows of a value, and which lines of the setting stand.
 *
 * Each case stands beside its control: a hidden character is asserted next to a shown one, a refusal next to
 * a line that is accepted.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(identitypattern::class)]
final class identitypattern_test extends \advanced_testcase {
    /**
     * The documented example, with and without separators in the stored value.
     *
     * @return void
     */
    public function test_the_example_hides_and_shows_as_written(): void {
        $pattern = '***.###.###-**';

        $this->assertSame('•••.456.789-••', identitypattern::mask('12345678909', $pattern));
        // Separators already in the value fill no slot.
        $this->assertSame('•••.456.789-••', identitypattern::mask('123.456.789-09', $pattern));
        $this->assertSame('•••.456.789-••', identitypattern::mask(' 123 456 789 09 ', $pattern));
    }

    /**
     * Each slot does what it says: one shows, the other hides, and a hidden character is a bullet.
     *
     * @return void
     */
    public function test_a_slot_shows_or_hides_one_character(): void {
        $this->assertSame('abc', identitypattern::mask('abc', '###'));
        $this->assertSame('•••', identitypattern::mask('abc', '***'));
        $this->assertSame('a•c', identitypattern::mask('abc', '#*#'));
        $this->assertSame("\u{2022}", identitypattern::HIDDEN);
        // A slot character in the value is a separator like any other, never a slot.
        $this->assertSame('a-b', identitypattern::mask('a#*b', '#-#'));
    }

    /**
     * A letter of any script fills one slot, a decomposed accent included.
     *
     * @return void
     */
    public function test_multibyte_letters_fill_one_slot_each(): void {
        $this->assertSame('Ç-ã', identitypattern::mask('Çã', '#-#'));
        // The masculine ordinal is a letter (Lo).
        $this->assertSame('1.º', identitypattern::mask('1º', '#.#'));
        $this->assertSame('•ã', identitypattern::mask('Çã', '*#'));

        // The value holds "e" and a combining acute accent: two code points, one letter after NFC, printed composed.
        $decomposed = "Jose\u{301}";
        $this->assertSame(5, mb_strlen($decomposed), 'Precondition: the accent is a code point of its own.');
        $this->assertSame("Jos\u{e9}", identitypattern::mask($decomposed, '####'));
        $this->assertSame("Jo-s\u{e9}", identitypattern::mask($decomposed, '##-##'));
    }

    /**
     * A value that does not fit its pattern is returned as stored.
     *
     * @return void
     */
    public function test_a_value_of_another_length_is_shown_as_stored(): void {
        $pattern = '***.###.###-**';

        $this->assertSame('1234567890', identitypattern::mask('1234567890', $pattern), 'One short.');
        $this->assertSame('123456789012', identitypattern::mask('123456789012', $pattern), 'One over.');
        $this->assertSame('123.456.789-0', identitypattern::mask('123.456.789-0', $pattern), 'Separators kept.');
        $this->assertSame('', identitypattern::mask('', $pattern));
        // Control: the length that fits is masked.
        $this->assertSame('•••.456.789-••', identitypattern::mask('12345678909', $pattern));
    }

    /**
     * A pattern with no slot formats nothing.
     *
     * @return void
     */
    public function test_a_pattern_without_a_slot_changes_nothing(): void {
        $this->assertSame('abc', identitypattern::mask('abc', ''));
        $this->assertSame('abc', identitypattern::mask('abc', 'x-y'));
        $this->assertSame(0, identitypattern::slots('x-y'));
        $this->assertSame(3, identitypattern::slots('#-*.#'));
    }

    /**
     * The pattern's literals are printed as written: the result is plain text, and escaping is the template's.
     *
     * @return void
     */
    public function test_literals_are_printed_as_written(): void {
        $this->assertSame('<a&b>', identitypattern::mask('ab', '<#&#>'));
        $this->assertSame('<•&b>', identitypattern::mask('ab', '<*&#>'));
    }

    /**
     * The setting is read line by line, whatever the line ending and the spacing.
     *
     * @return void
     */
    public function test_the_setting_is_parsed_line_by_line(): void {
        $expected = ['username' => '***.###.###-**', 'idnumber' => '##-**'];

        $this->assertSame($expected, identitypattern::parse("username=***.###.###-**\nidnumber=##-**"));
        $this->assertSame($expected, identitypattern::parse("username=***.###.###-**\r\nidnumber=##-**\r\n"));
        $this->assertSame($expected, identitypattern::parse("  username = ***.###.###-**  \n\n\n idnumber= ##-** \n"));
        $this->assertSame([], identitypattern::parse(''));
        // A pattern may hold the separator itself: the field ends at the first one.
        $this->assertSame(['idnumber' => '#=#'], identitypattern::parse('idnumber=#=#'));
    }

    /**
     * A stored value holding lines the validation would refuse is read without them.
     *
     * @return void
     */
    public function test_a_line_that_cannot_apply_is_skipped_when_read(): void {
        $setting = "no separator\nusername=no slot\n=##\nusername=###\nusername=***\nemail=#*";

        $this->assertSame(['username' => '###', 'email' => '#*'], identitypattern::parse($setting));
    }

    /**
     * The validation names each refused line and its reason, and accepts the rest.
     *
     * @return void
     */
    public function test_the_validation_reports_each_refusal_with_its_line(): void {
        $fields = ['fullname', 'username', 'profile_field_rank'];

        $this->assertSame([], identitypattern::errors("username=***.###\n\nprofile_field_rank=#*\r\nfullname=#", $fields));
        $this->assertSame([], identitypattern::errors('', $fields));

        $errors = identitypattern::errors("username=###\n\nbroken\nemail=###\nfullname=abc\nusername=***", $fields);
        $this->assertSame(
            [
                [3, identitypattern::ERROR_NOEQUALS, 'broken'],
                [4, identitypattern::ERROR_UNKNOWNFIELD, 'email=###'],
                [5, identitypattern::ERROR_NOSLOT, 'fullname=abc'],
                [6, identitypattern::ERROR_DUPLICATE, 'username=***'],
            ],
            array_map(fn(array $error): array => [$error['line'], $error['error'], $error['text']], $errors)
        );

        // A refused line does not make a later, valid line of the same field a duplicate.
        $this->assertSame([], array_slice(identitypattern::errors("username=abc\nusername=###", $fields), 1));
    }
}
