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
 * Theme Dimensions - One step of a trail.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

/**
 * One step of a trail: a Subcourse activity of the trail course and the course it refers to.
 *
 * The structure every reader shares, read once ({@see trail::many()}): nothing here depends on who is
 * looking. Whether a reader may see the referenced course, and how far a learner has gone, are decided
 * by the page that draws the step.
 *
 * mod_subcourse stores no dates, so the window is core's: it opens
 * with the referenced course's start date and closes on the activity's "Expect completed on" date when
 * one is set and the trail course tracks completion (core drops that date's calendar event otherwise,
 * {@see \core_completion\api::update_completion_date_event()}), else on the referenced course's end date.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class step {
    /**
     * Build a step; {@see trail::many()} is the one maker.
     *
     * @param int $cmid The Subcourse activity's course module id.
     * @param string $name The activity's name as stored, not formatted.
     * @param int $refcourseid The referenced course.
     * @param int $refcategoryid The referenced course's category.
     * @param bool $refvisible Whether the referenced course is visible (its own flag, not the reader's view of it).
     * @param int $start When the step opens: the referenced course's start date, 0 when unset.
     * @param int $end When the step closes: the expected completion date or the course's end date, 0 when neither.
     * @param bool $expected Whether $end is the activity's expected completion date.
     */
    public function __construct(
        /** @var int The Subcourse activity's course module id. */
        public readonly int $cmid,
        /** @var string The activity's name as stored, not formatted. */
        public readonly string $name,
        /** @var int The referenced course. */
        public readonly int $refcourseid,
        /** @var int The referenced course's category. */
        public readonly int $refcategoryid,
        /** @var bool Whether the referenced course is visible. */
        public readonly bool $refvisible,
        /** @var int When the step opens, 0 when unset. */
        public readonly int $start,
        /** @var int When the step closes, 0 when the window is open-ended. */
        public readonly int $end,
        /** @var bool Whether $end is the activity's expected completion date. */
        public readonly bool $expected
    ) {
    }
}
