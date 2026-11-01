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
 * Theme Dimensions - The one refusal of the calendar range.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * The one exception the calendar range raises, whatever it refuses.
 *
 * A malformed or oversized window, a key the vocabulary does not hold, a course that is not one of the
 * viewer's calendar courses, a category that does not exist or that the viewer may not see, the guest
 * account and a calendar type other than the Gregorian all raise this class with the same message. A
 * guessed course or category id therefore cannot tell "missing" from "withheld": anything else would
 * be an existence oracle. Final and argument-free, so it cannot become two distinguishable refusals.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class refusal extends \moodle_exception {
    /**
     * Build the refusal. It takes no argument on purpose.
     */
    public function __construct() {
        parent::__construct('calendarrangeunavailable', 'theme_dimensions');
    }
}
