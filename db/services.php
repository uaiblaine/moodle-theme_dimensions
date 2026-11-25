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
 * Theme Dimensions - External functions of the category showcase, the mobile drawer and focus mode.
 *
 * The account services (the category rows, the card details, the course dialogue, the wishlist toggle, the remembered
 * category state, the drawer summary, the plan cards, the calendar range, the calendar trails, the remembered calendar
 * state, focus mode and the flavour choice) are loginrequired and ajax, with no capability of their own: every refusal
 * is decided inside the function. Those that only read declare readonlysession; the writers keep a writable session,
 * and the remembered states write the reader's own preference and nothing else. The drawer summary answers about the
 * session's own user only. The plan cards repeat core's access rule for the learning plans page before building. The
 * calendar range gives its order of checks in its class docblock. Focus mode records a switch made in place, in the
 * session alone.
 *
 * The visitor service get_public_category_rows answers over lib/ajax/service-nologin.php and so is not
 * loginrequired: it serves only the public categories' listing, refuses every logged-in session, and stays closed until
 * the site opens it (publicaccess::client_enabled()). The public calendar has no service: its visitor reads the route
 * of route\controller\calendar_public, which a CDN may cache.
 *
 * Registered on upgrade, so any change here ships with a version bump.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'theme_dimensions_get_category_rows' => [
        'classname' => 'theme_dimensions\external\get_category_rows',
        'description' => 'One page of the category showcase: the cards, the counts and the facets, post-filter.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_card_details' => [
        'classname' => 'theme_dimensions\external\get_card_details',
        'description' => 'Viewport-batched enrichment of the cards the viewer can see: completion progress.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_course_dialogue' => [
        'classname' => 'theme_dimensions\external\get_course_dialogue',
        'description' => 'The content of one course\'s "More information" dialogue: excerpt, facts and programme.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_toggle_wishlist' => [
        'classname' => 'theme_dimensions\external\toggle_wishlist',
        'description' => 'Star or unstar a course of the showcase: core\'s own favourite, written through the theme\'s gates.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_remember_category_state' => [
        'classname' => 'theme_dimensions\external\remember_category_state',
        'description' => 'Remember the organisation, the view and the filters the reader left one category\'s listing in.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_drawer_summary' => [
        'classname' => 'theme_dimensions\external\get_drawer_summary',
        'description' => 'The mobile drawer\'s deferred summary: what to continue.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_plan_cards' => [
        'classname' => 'theme_dimensions\external\get_plan_cards',
        'description' => 'The plan cards of one user\'s learning plans page, after core re-renders it.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_course_competency_extras' => [
        'classname' => 'theme_dimensions\external\get_course_competency_extras',
        'description' => 'The extras of a course\'s competencies page (activity outcomes, the class distribution for '
            . 'staff, the learner\'s own figures), after core re-renders it.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_calendar_range' => [
        'classname' => 'theme_dimensions\external\get_calendar_range',
        'description' => 'One month grid of the calendar: the events once each, the event courses, the courses, '
            . 'the categories and the day counts.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_calendar_trails' => [
        'classname' => 'theme_dimensions\external\get_calendar_trails',
        'description' => 'The trails of a month, or of the month and the next, for the calendar\'s Trails view: each trail '
            . 'the viewer may discover, its steps and, for a learner, their states.',
        'type' => 'read',
        'ajax' => true,
        'readonlysession' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_remember_calendar_state' => [
        'classname' => 'theme_dimensions\external\remember_calendar_state',
        'description' => 'Remember the keys, the category and the filters the reader left the calendar page in.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_get_public_category_rows' => [
        'classname' => 'theme_dimensions\external\get_public_category_rows',
        'description' => 'One page of a public category\'s showcase, for a visitor: the cards, the counts and the facets.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => false,
        'capabilities' => '',
    ],
    'theme_dimensions_set_focus_mode' => [
        'classname' => 'theme_dimensions\external\set_focus_mode',
        'description' => 'Record that the reader switched focus mode on or off in an activity\'s course, for the session.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
    'theme_dimensions_choose_flavour' => [
        'classname' => 'theme_dimensions\external\choose_flavour',
        'description' => 'Record the look the caller chose among the offered Boost Union flavours.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '',
    ],
];
