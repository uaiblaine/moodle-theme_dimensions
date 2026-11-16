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
];
