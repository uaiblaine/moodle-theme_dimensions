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
 * Theme Dimensions - The landing page's related courses band: whether it is drawn and what it lists
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\trail;

/**
 * The related courses band at the end of a landing page, in automatic mode (R1 of docs/related/PLAN.md).
 *
 * The course's `dimensions_related` select decides whether the band is drawn: empty or Automatic draws it, Off and
 * any value this release does not know (a line appended later, such as manual mode) draw nothing. The set is the
 * course's competencies, and for a trail the union of the trail's and its steps' courses'; those courses are never
 * related, since the page already lists a trail's steps. {@see finder} ranks and gates the candidates for the
 * current reader. A band with nothing to list is not drawn at all, its heading included.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class band {
    /** @var string The site setting holding how many courses the band lists at most. */
    public const SETTING_COUNT = 'relatedcount';

    /** @var int What the setting reads as when it is unset or outside its choices (D8). */
    public const DEFAULT_COUNT = 4;

    /**
     * The choices the setting offers: 1 to {@see finder::MAX_LIMIT}.
     *
     * @return string[] Map of stored value => label.
     */
    public static function choices(): array {
        $choices = [];
        for ($count = 1; $count <= finder::MAX_LIMIT; $count++) {
            $choices[$count] = (string) $count;
        }
        return $choices;
    }

    /**
     * How many courses the band lists at most: the setting when it is one of its choices, else the default.
     *
     * @return int
     */
    public static function count(): int {
        $stored = get_config('theme_dimensions', self::SETTING_COUNT);
        $count = is_numeric($stored) ? (int) $stored : 0;
        return isset(self::choices()[$count]) ? $count : self::DEFAULT_COUNT;
    }

    /**
     * Whether a course's choice draws the band: its select is empty or Automatic.
     *
     * @param \stdClass $values The course's landing page values ({@see landingfields::values()}).
     * @return bool
     */
    public static function wanted(\stdClass $values): bool {
        return in_array((int) ($values->related ?? 0), [0, landingfields::RELATED_AUTO], true);
    }

    /**
     * The courses the band lists for the current reader, best first.
     *
     * @param \stdClass $course The landing page's course.
     * @param \stdClass $values Its landing page values ({@see landingfields::values()}).
     * @param trail|null $trail Its trail, when the page is a trail's ({@see trail::of()}), so the page reads it once.
     * @param int|null $now The time the start dates are compared with; now when null.
     * @return array The finder's answer ({@see finder::find()}): ['candidate' => candidate, 'row' => \stdClass] each;
     *   empty when the band is not drawn.
     */
    public static function courses(\stdClass $course, \stdClass $values, ?trail $trail = null, ?int $now = null): array {
        if (!self::wanted($values)) {
            return [];
        }
        $courseids = [(int) $course->id];
        if ($trail !== null) {
            foreach ($trail->steps as $step) {
                $courseids[] = $step->refcourseid;
            }
        }
        return finder::find(
            finder::competencies_of($courseids),
            $courseids,
            finder::MODE_AUTO,
            finder::MATCH_ANY,
            self::count(),
            $now
        );
    }
}
