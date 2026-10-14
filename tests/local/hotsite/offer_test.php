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
 * Tests for what an enrolment method says about its seats and its deadline
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The seats and the deadline are the plugins' own fields, said in the viewer's timezone.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(offer::class)]
final class offer_test extends \advanced_testcase {
    /** @var string The component, for strings. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * An instance record of one plugin with some fields set.
     *
     * @param string $enrol The enrol plugin.
     * @param array $fields The fields.
     * @return \stdClass The record.
     */
    private static function instance(string $enrol, array $fields = []): \stdClass {
        return (object) ($fields + [
            'id' => 7,
            'enrol' => $enrol,
            'customint3' => 0,
            'customint4' => 0,
            'customint5' => 0,
            'enrolenddate' => 0,
        ]);
    }

    /**
     * Instances and the seats they declare.
     *
     * @return array Name => [instance, expected seats].
     */
    public static function seat_cases(): array {
        return [
            'self caps at customint3' => [self::instance('self', ['customint3' => 40]), ['count' => 40, 'applications' => false]],
            'self without a cap' => [self::instance('self'), null],
            'autoenrol caps at customint5' => [
                self::instance('autoenrol', ['customint5' => 25, 'customint3' => 9]),
                ['count' => 25, 'applications' => false],
            ],
            'apply speaks of places when set' => [
                self::instance('apply', ['customint4' => 40, 'customint3' => 120]),
                ['count' => 40, 'applications' => false],
            ],
            'apply speaks of applications without places' => [
                self::instance('apply', ['customint3' => 120]),
                ['count' => 120, 'applications' => true],
            ],
            'fee has no cap whatever its fields hold' => [self::instance('fee', ['customint3' => 40]), null],
            'guest has no cap' => [self::instance('guest', ['customint3' => 40]), null],
        ];
    }

    /**
     * Only the plugins that enforce a cap declare one, read off their own field.
     *
     * @param \stdClass $instance The instance.
     * @param array|null $expected The seats.
     */
    #[DataProvider('seat_cases')]
    public function test_seats_are_the_plugins_own_cap(\stdClass $instance, ?array $expected): void {
        $this->assertSame($expected, offer::seats($instance));
    }

    /**
     * The deadline is enrolenddate on the plugins that close at it, and nothing anywhere else.
     */
    public function test_the_deadline_belongs_to_the_plugins_that_close_at_it(): void {
        foreach (['self', 'autoenrol', 'apply', 'fee', 'paypal'] as $enrol) {
            $this->assertSame(1800000000, offer::deadline(self::instance($enrol, ['enrolenddate' => 1800000000])), $enrol);
            $this->assertNull(offer::deadline(self::instance($enrol)), $enrol);
        }
        foreach (['guest', 'manual', 'cohort', 'meta'] as $enrol) {
            $this->assertNull(offer::deadline(self::instance($enrol, ['enrolenddate' => 1800000000])), $enrol);
        }
    }

    /**
     * The lines say the total, the date without a time at the end of the day, and the time otherwise.
     */
    public function test_the_lines_say_the_total_and_the_deadline(): void {
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');
        $now = make_timestamp(2026, 10, 9, 12, 0);

        $endofday = make_timestamp(2026, 10, 26, 23, 59);
        $lines = offer::export(self::instance('self', ['customint3' => 40, 'enrolenddate' => $endofday]), $now);
        $this->assertSame(get_string('hotsite_offerseats', self::COMPONENT, 40), $lines['seats']);
        $date = userdate($endofday, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertSame(get_string('hotsite_offeruntil', self::COMPONENT, $date), $lines['until']);
        $this->assertNull($lines['urgent']);

        // A deadline at any other minute says its time: midnight closes as the day begins.
        $midnight = make_timestamp(2026, 10, 26, 0, 0);
        $lines = offer::export(self::instance('fee', ['enrolenddate' => $midnight]), $now);
        $this->assertFalse($lines['hasseats']);
        $this->assertStringContainsString('00h00', $lines['until']);

        $this->assertSame(
            get_string('hotsite_offerseat', self::COMPONENT, 1),
            offer::export(self::instance('self', ['customint3' => 1]), $now)['seats']
        );
        $this->assertSame(
            get_string('hotsite_offerapplications', self::COMPONENT, 120),
            offer::export(self::instance('apply', ['customint3' => 120]), $now)['seats']
        );
        $this->assertNull(offer::export(self::instance('self'), $now));
    }

    /**
     * Deadlines and the closing note of their last days.
     *
     * @return array Name => [deadline offset in days and its time, expected note key or null].
     */
    public static function urgency_cases(): array {
        return [
            'later today' => [[0, 18, 0], 'hotsite_offerclosestoday'],
            'tomorrow morning' => [[1, 8, 0], 'hotsite_offerclosestomorrow'],
            'in two days' => [[2, 23, 59], 'hotsite_offerclosesindays'],
            'in three days' => [[3, 23, 59], null],
            'a week away' => [[7, 23, 59], null],
            'already past' => [[0, 9, 0], null],
        ];
    }

    /**
     * The note shows from two calendar days before the deadline, counted between the viewer's midnights.
     *
     * @param array $when Days from now, hour and minute of the deadline.
     * @param string|null $key The expected note's string key.
     */
    #[DataProvider('urgency_cases')]
    public function test_the_closing_note_marks_the_last_days(array $when, ?string $key): void {
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');
        $now = make_timestamp(2026, 10, 9, 12, 0);
        [$days, $hour, $minute] = $when;
        $deadline = make_timestamp(2026, 10, 9 + $days, $hour, $minute);

        $note = offer::urgency($deadline, $now);
        if ($key === null) {
            $this->assertNull($note);
            return;
        }
        $expected = match ($key) {
            'hotsite_offerclosestoday' => get_string($key, self::COMPONENT, userdate($deadline, '%Hh%M')),
            'hotsite_offerclosesindays' => get_string($key, self::COMPONENT, $days),
            default => get_string($key, self::COMPONENT),
        };
        $this->assertSame($expected, $note);
    }
}
