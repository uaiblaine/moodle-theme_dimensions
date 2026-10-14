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
 * Theme Dimensions - What an enrolment method says about its seats and its deadline
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

/**
 * The seats and the enrolment deadline an enrolment instance declares (round 6, decisions D4-D6).
 *
 * Read off the instance record alone, so it costs no statement and answers the same for whoever
 * looks: the page prints the CONFIGURED total, never what is left (D5) - a method whose cap is
 * reached is already hidden by the resolver, and a count of what is left would be a number that
 * changes under the reader. Only the plugins whose own checks read these fields are named here, so
 * a number on the page is one the plugin enforces:
 *
 * - enrol_self caps at customint3 and enrol_autoenrol at customint5, both counting every row of
 *   the instance (enrol/self/lib.php is_self_enrol_available(), enrol/autoenrol/lib.php
 *   enrol_allowed());
 * - the enrol_apply fork caps places at customint4 and applications at customint3
 *   (classes/local/capacity.php); places are the seats when set, otherwise the page speaks of
 *   applications, never of seats (D4);
 * - enrolenddate closes enrol_self, enrol_autoenrol, enrol_apply, enrol_fee and enrol_paypal, and
 *   nothing in core closes an enrolment at the course's own dates.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class offer {
    /** @var string Component name, for strings. */
    private const COMPONENT = 'theme_dimensions';

    /** @var string[] The enrol plugins whose own checks close enrolment at enrolenddate. */
    public const DEADLINE_PLUGINS = ['self', 'autoenrol', 'apply', 'fee', 'paypal'];

    /** @var int How many calendar days before the deadline the closing note starts: today and the two before it. */
    public const URGENT_DAYS = 2;

    /**
     * The seats an instance declares.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @return array|null ['count' => int, 'applications' => bool], or null when the instance declares none.
     */
    public static function seats(\stdClass $instance): ?array {
        switch ($instance->enrol) {
            case 'self':
                $count = (int) ($instance->customint3 ?? 0);
                return $count > 0 ? ['count' => $count, 'applications' => false] : null;
            case 'autoenrol':
                $count = (int) ($instance->customint5 ?? 0);
                return $count > 0 ? ['count' => $count, 'applications' => false] : null;
            case 'apply':
                $places = (int) ($instance->customint4 ?? 0);
                if ($places > 0) {
                    return ['count' => $places, 'applications' => false];
                }
                $applications = (int) ($instance->customint3 ?? 0);
                return $applications > 0 ? ['count' => $applications, 'applications' => true] : null;
        }
        return null;
    }

    /**
     * The moment an instance stops taking enrolments, when its plugin enforces one.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @return int|null The timestamp, or null when the instance has none.
     */
    public static function deadline(\stdClass $instance): ?int {
        if (!in_array($instance->enrol, self::DEADLINE_PLUGINS, true)) {
            return null;
        }
        $end = (int) ($instance->enrolenddate ?? 0);
        return $end > 0 ? $end : null;
    }

    /**
     * The enabled instances of a course that take new enrolments and set no deadline.
     *
     * Nothing in core closes an enrolment at the course's own dates, so an event whose methods
     * set no enrolenddate keeps taking enrolments while it is being held (decision D9). An
     * instance counts when it and its plugin are enabled, its plugin is one that closes at
     * enrolenddate, and it takes new enrolments (the same flags the visitor's list reads).
     *
     * @param int $courseid The course id.
     * @return \stdClass[] The {enrol} records, in instance sortorder.
     */
    public static function undated(int $courseid): array {
        $newenrols = ['self' => 'customint6', 'autoenrol' => 'customint4', 'apply' => 'customint6'];
        $plugins = enrol_get_plugins(true);
        $undated = [];
        foreach (enrol_get_instances($courseid, true) as $instance) {
            $type = $instance->enrol;
            if (!isset($plugins[$type]) || !in_array($type, self::DEADLINE_PLUGINS, true)) {
                continue;
            }
            if (isset($newenrols[$type]) && empty($instance->{$newenrols[$type]})) {
                continue;
            }
            if ((int) $instance->enrolenddate === 0) {
                $undated[] = $instance;
            }
        }
        return $undated;
    }

    /**
     * The lines the page prints for an instance, in the viewer's language and timezone.
     *
     * The time of the deadline is said unless it is the last minute of its day, which is what an
     * editor means by "until the 26th" (D6's reading of the date picker); a deadline at midnight
     * closes as the day BEGINS and says so, rather than reading as the whole day. The closing note
     * appears from two calendar days before the deadline, counted in the viewer's timezone.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param int $now The current time.
     * @return array|null Template context (hasseats, seats, hasuntil, until, untilshort, urgent), or
     *   null when the instance says nothing.
     */
    public static function export(\stdClass $instance, int $now): ?array {
        $seats = self::seats($instance);
        $deadline = self::deadline($instance);
        if ($seats === null && $deadline === null) {
            return null;
        }

        $context = [
            'hasseats' => false,
            'seats' => null,
            'hasuntil' => false,
            'until' => null,
            'untilshort' => null,
            'urgent' => null,
        ];
        if ($seats !== null) {
            $key = 'hotsite_offerseats';
            if ($seats['applications']) {
                $key = 'hotsite_offerapplications';
            } else if ($seats['count'] === 1) {
                $key = 'hotsite_offerseat';
            }
            $context['hasseats'] = true;
            $context['seats'] = get_string($key, self::COMPONENT, $seats['count']);
        }
        if ($deadline !== null) {
            $date = userdate($deadline, get_string('strftimedatefullshort', 'langconfig'));
            $endofday = userdate($deadline, '%H:%M') === '23:59';
            $when = $endofday ? $date : get_string('hotsite_offerdatetime', self::COMPONENT, (object) [
                'date' => $date,
                'time' => userdate($deadline, get_string('hotsite_fmttime', self::COMPONENT)),
            ]);
            $context['hasuntil'] = true;
            $context['until'] = get_string('hotsite_offeruntil', self::COMPONENT, $when);
            $context['untilshort'] = get_string(
                'hotsite_offeruntil',
                self::COMPONENT,
                userdate($deadline, get_string('hotsite_fmtdayshort', self::COMPONENT))
            );
            $context['urgent'] = self::urgency($deadline, $now);
        }
        return $context;
    }

    /**
     * The closing note in the last days of a deadline, or null outside them.
     *
     * Days are counted between the viewer's midnights, so a deadline tomorrow morning reads
     * "closes tomorrow" whatever the hour; rounding absorbs the hour a daylight-saving change
     * adds or removes between the two midnights.
     *
     * @param int $deadline The deadline.
     * @param int $now The current time.
     * @return string|null The note.
     */
    public static function urgency(int $deadline, int $now): ?string {
        if ($deadline < $now) {
            return null;
        }
        $days = (int) round((usergetmidnight($deadline) - usergetmidnight($now)) / DAYSECS);
        if ($days > self::URGENT_DAYS) {
            return null;
        }
        if ($days === 0) {
            return get_string(
                'hotsite_offerclosestoday',
                self::COMPONENT,
                userdate($deadline, get_string('hotsite_fmttime', self::COMPONENT))
            );
        }
        if ($days === 1) {
            return get_string('hotsite_offerclosestomorrow', self::COMPONENT);
        }
        return get_string('hotsite_offerclosesindays', self::COMPONENT, $days);
    }
}
