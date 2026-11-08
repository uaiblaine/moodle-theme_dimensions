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
 * Theme Dimensions - One course related to a set of competencies.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

/**
 * A course linked to at least one competency of a set, and how it relates to that set.
 *
 * Structure, the same for every reader: whether this reader may be shown the course is {@see finder::find()}'s
 * gate, never a field here.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class candidate {
    /**
     * Build a candidate; {@see finder::rank()} is the one maker.
     *
     * @param int $courseid The course.
     * @param int $tier The automatic mode's tier, a finder::TIER_* constant.
     * @param int $shared How many competencies of the set are linked to the course.
     * @param int $total How many competencies are linked to the course in all.
     * @param bool $grants Whether completing the course completes one of the shared competencies.
     * @param float $overlap Jaccard of the two sets, shared over their union, 0 to 1.
     * @param int $startdate The course's start date, 0 when unset.
     * @param string $fullname The course's full name as stored, not formatted.
     */
    public function __construct(
        /** @var int The course. */
        public readonly int $courseid,
        /** @var int The automatic mode's tier, a finder::TIER_* constant. */
        public readonly int $tier,
        /** @var int How many competencies of the set are linked to the course. */
        public readonly int $shared,
        /** @var int How many competencies are linked to the course in all. */
        public readonly int $total,
        /** @var bool Whether completing the course completes one of the shared competencies. */
        public readonly bool $grants,
        /** @var float Jaccard of the two sets, 0 to 1. */
        public readonly float $overlap,
        /** @var int The course's start date, 0 when unset. */
        public readonly int $startdate,
        /** @var string The course's full name as stored. */
        public readonly string $fullname
    ) {
    }
}
