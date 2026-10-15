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
 * Theme Dimensions - Whether a landing page may be served to visitors who are not logged in
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

/**
 * Whether a course's landing page may be served to a visitor who is not logged in.
 *
 * A thin adapter over local_unlistedcourses' discoverability::is_public(),
 * which is the gate for an anonymous page on a site that keeps forcelogin on:
 * it requires the PUBLIC state, a visible course and a visible category path,
 * and it is a property of the course, not of the viewer.
 *
 * Fails closed. When the predicate class is missing - the dependency
 * uninstalled, a broken deploy - the answer is "not public", and the visitor
 * gets the login page. {@see redirector::is_ghosted()}, which
 * {@see request::handle()} asks for a logged-in user, deliberately fails
 * open: a missing predicate there means "do not ghost anybody", because
 * ghosting strands a logged-in user who is entitled to the course. One
 * withholds a page from the internet, the other from somebody who may be
 * entitled to it, so the asymmetry is intended and must not be harmonised.
 * Both are pinned by tests that hand the adapter a class that does not exist.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publicaccess {
    /** @var string The predicate this adapter delegates to. */
    public const PREDICATE = \local_unlistedcourses\discoverability::class;

    /**
     * Whether the course's landing page may be served anonymously.
     *
     * @param int $courseid The course id from the request, not yet validated.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-closed answer.
     * @return bool True only when the predicate exists and says the course is public.
     */
    public static function is_public(int $courseid, ?string $predicate = null): bool {
        if ($courseid <= 0 || $courseid == SITEID) {
            return false;
        }
        $predicate = $predicate ?? self::PREDICATE;
        if (!class_exists($predicate)) {
            // Fail closed: no predicate, no public page.
            return false;
        }
        return (bool) $predicate::is_public($courseid);
    }
}
