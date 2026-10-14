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
 * Theme Dimensions - The resolved enrolment state of the hotsite
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

/**
 * What the enrolment area of the hotsite shows the current user.
 *
 * One of six states, plus the usable methods where applicable. STATE_OPEN
 * with only TYPE_FALLBACK methods is provisional: the page calls each
 * fallback's enrol_page_hook() at render time and downgrades to STATE_CLOSED
 * when every hook returns falsy.
 *
 * The state says what the area offers; the relationship says what the
 * viewer's own rows are, in local_unlistedcourses' vocabulary, so the page can
 * add the note that goes with it - a waiting list under the pending state, a
 * suspension or an ended enrolment above whatever methods are open or closed -
 * without a state of its own for each, which would take the methods and the
 * mobile bar away from them. The prerequisite is the course a conditional
 * enrolment waits for, when no method is open. Whether a visitor has a login
 * way in decides whether the visitor's page leads with the login button at all.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolution {
    /** @var string Not logged in (or browsing as the guest account): the CTA is the login. */
    public const STATE_VISITOR = 'visitor';

    /** @var string Actively enrolled: the CTA is the course itself. */
    public const STATE_ENROLLED = 'enrolled';

    /** @var string Enrolled from a later date: the course opens on that date; another method may be offered meanwhile. */
    public const STATE_SCHEDULED = 'scheduled';

    /** @var string An enrol_apply application is waiting for a decision. */
    public const STATE_PENDING = 'pending';

    /** @var string At least one method is usable right now. */
    public const STATE_OPEN = 'open';

    /** @var string No method is usable: the area shows the contact fallback. */
    public const STATE_CLOSED = 'closed';

    /**
     * Constructor.
     *
     * @param string $state One of the STATE_* constants.
     * @param method[] $methods The usable methods, in instance sortorder.
     * @param int|null $appliedtime When the pending application was submitted (STATE_PENDING only).
     * @param int|null $startsat When the enrolment starts (STATE_SCHEDULED only).
     * @param int|null $endsat When the enrolment ends, 0 or null for no end (STATE_SCHEDULED), or ended (an expired relationship).
     * @param string $relationship The viewer's relationship, a RELATIONSHIP_* value of local_unlistedcourses.
     * @param int|null $prerequisiteid The course a conditional enrolment waits for, when no method is open.
     * @param \stdClass[] $offers The instances a visitor is told the seats and deadline of (STATE_VISITOR only).
     * @param bool $loginwayin Whether logging in could lead a visitor to an enrolment, an application, a payment or a
     *   prerequisite (STATE_VISITOR only; true elsewhere, where nobody is asked to log in).
     */
    public function __construct(
        /** @var string One of the STATE_* constants. */
        public readonly string $state,
        /** @var method[] The usable methods, in instance sortorder. */
        public readonly array $methods = [],
        /** @var int|null When the pending application was submitted (STATE_PENDING only). */
        public readonly ?int $appliedtime = null,
        /** @var int|null When the enrolment starts (STATE_SCHEDULED only). */
        public readonly ?int $startsat = null,
        /** @var int|null When the enrolment ends (STATE_SCHEDULED), or ended (an expired relationship). */
        public readonly ?int $endsat = null,
        /** @var string The viewer's relationship, a RELATIONSHIP_* value of local_unlistedcourses. */
        public readonly string $relationship = 'none',
        /** @var int|null The course a conditional enrolment waits for, when no method is open. */
        public readonly ?int $prerequisiteid = null,
        /** @var \stdClass[] The instances a visitor is told the seats and deadline of (STATE_VISITOR only). */
        public readonly array $offers = [],
        /** @var bool Whether logging in could lead a visitor anywhere on the course (STATE_VISITOR only). */
        public readonly bool $loginwayin = true,
    ) {
    }
}
