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
 * Theme Dimensions - Event observers
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // A deleted category takes its branding row with it, or the table accumulates rows keyed to
    // ids that will be reissued. Core fires this from both of its deletion paths (delete_full()
    // and delete_move()) after it has deleted the category's context - and with it, every file
    // of the hero area - so the row is all that is left to drop.
    [
        'eventname' => '\core\event\course_category_deleted',
        'callback' => '\theme_dimensions\local\category\branding::category_deleted',
    ],
    // The cached branding holds each category's RESOLVED colour pair, which a move changes for the
    // category and every descendant without a pair of its own. Core fires this event for a move
    // (change_parent(), and delete_move() for each child it moves) as well as for every other edit
    // of a category; the callback drops the whole cache.
    [
        'eventname' => '\core\event\course_category_updated',
        'callback' => '\theme_dimensions\local\category\branding::category_updated',
    ],
    // The shared cache of each course's category (the cover of a card without an image) forgets a course on every
    // event that can change its category or end it: a move fires course_updated.
    [
        'eventname' => '\\core\\event\\course_created',
        'callback' => '\\theme_dimensions\\local\\coursecategory::course_changed',
    ],
    [
        'eventname' => '\\core\\event\\course_updated',
        'callback' => '\\theme_dimensions\\local\\coursecategory::course_changed',
    ],
    [
        'eventname' => '\\core\\event\\course_deleted',
        'callback' => '\\theme_dimensions\\local\\coursecategory::course_changed',
    ],
    [
        'eventname' => '\\core\\event\\course_restored',
        'callback' => '\\theme_dimensions\\local\\coursecategory::course_changed',
    ],
    // Boost Union's own observer of this event (priority 0) strips the deleted cohort from every smart
    // menu rule, and a rule left with no cohort shows its menu to everyone. Running first, the theme
    // hides the menus and items a deleted look cohort restricted while their rules still name it.
    [
        'eventname' => '\core\event\cohort_deleted',
        'callback' => '\theme_dimensions\local\lookcohorts::cohort_deleted',
        'priority' => 1000,
    ],
    // The identity settings read the custom profile fields' definitions from a cache; any change to a field or to
    // a category (whose order is the fields' order) empties it.
    [
        'eventname' => '\\core\\event\\user_info_field_created',
        'callback' => '\\theme_dimensions\\local\\identity::definitions_changed',
    ],
    [
        'eventname' => '\\core\\event\\user_info_field_updated',
        'callback' => '\\theme_dimensions\\local\\identity::definitions_changed',
    ],
    [
        'eventname' => '\\core\\event\\user_info_field_deleted',
        'callback' => '\\theme_dimensions\\local\\identity::definitions_changed',
    ],
    [
        'eventname' => '\\core\\event\\user_info_category_created',
        'callback' => '\\theme_dimensions\\local\\identity::definitions_changed',
    ],
    [
        'eventname' => '\\core\\event\\user_info_category_updated',
        'callback' => '\\theme_dimensions\\local\\identity::definitions_changed',
    ],
    [
        'eventname' => '\\core\\event\\user_info_category_deleted',
        'callback' => '\\theme_dimensions\\local\\identity::definitions_changed',
    ],
    // The public calendar's halves are kept under a revision token each (local\calendar\publiccache): every change a
    // half can show mints a new token. A category event is the events' half; a course or its discoverability state
    // the courses' half; a category (its name, visibility, place, state or deletion) both, which both name categories.
    // Each runs after the write commits (internal false): run inside an open transaction, a new token would be minted
    // before the change is visible, and a visitor asking in between would keep the old answer under the new token.
    [
        'eventname' => '\\core\\event\\calendar_event_created',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::calendar_event_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\calendar_event_updated',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::calendar_event_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\calendar_event_deleted',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::calendar_event_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_created',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_updated',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_deleted',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_restored',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\local_unlistedcourses\\event\\course_state_updated',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_category_updated',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::category_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_category_deleted',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::category_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\local_unlistedcourses\\event\\category_state_updated',
        'callback' => '\\theme_dimensions\\local\\calendar\\publiccache::category_changed',
        'internal' => false,
    ],
    // The course competencies page's class distribution is kept under a revision per course and one for the site
    // (local\competency\courseclass): each event that can change who is counted or how they are rated mints a new one,
    // after the write commits, so a value built in between is never taken for the new state. A course competency added
    // or removed fires no event; the value carries the competency ids instead.
    [
        'eventname' => '\\core\\event\\competency_user_competency_rated_in_course',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\competency_evidence_created',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\user_enrolment_created',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\user_enrolment_deleted',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\course_reset_ended',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::course_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\role_assigned',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::role_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\role_unassigned',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::role_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\capability_assigned',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::role_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\capability_unassigned',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::role_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\role_deleted',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::site_changed',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\user_deleted',
        'callback' => '\\theme_dimensions\\local\\competency\\courseclass::site_changed',
        'internal' => false,
    ],
    // A deleted account's filter values on the class report of competency ratings: core keeps them in its own table,
    // and the privacy finder reads the user context, which is gone by the time this fires.
    [
        'eventname' => '\\core\\event\\user_deleted',
        'callback' => '\\theme_dimensions\\local\\competency\\reportpersistents::user_deleted',
    ],
];
