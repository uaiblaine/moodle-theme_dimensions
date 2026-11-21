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
 * Theme Dimensions - Cache definitions
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // The branding of a course category on the category showcase: its row and the names of its
    // files, keyed by category id. Viewer-independent: no count and no course name
    // (branding::CACHED_KEYS is the whole payload). An entry holds the category's resolved colour
    // pair, which may be an ancestor's, so the whole cache is dropped when the editor saves and when
    // a category is deleted or updated (a move included). The block_compass category_badges listener
    // keeps its own entries here under compasscrest_<id>, so they go with every one of those drops
    // (local\hook\block_compass\category_badges).
    'categorybranding' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],
    // Which flavour this session last wrote into Boost Union's flavour cache, and that flavour's
    // record (local\flavourchoice). A session cache because Boost Union's is one: the two are
    // purged together, and Boost Union's invalidation events are listed here so that a flavour
    // edited, re-sorted or deleted is read again by every session, not only by the editor's.
    // The category of a course, for the cover of a card without an image; db/events.php deletes an entry.
    'coursecategory' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 30,
    ],
    'flavourchoice' => [
        'mode' => \core_cache\store::MODE_SESSION,
        'simplekeys' => true,
        'simpledata' => false,
        'invalidationevents' => [
            'theme_boost_union_flavours_resorted',
            'theme_boost_union_flavours_created',
            'theme_boost_union_flavours_edited',
            'theme_boost_union_flavours_deleted',
            'theme_boost_union_cohort_deleted',
        ],
    ],
    // The offered looks with their plain titles, for the user menu every logged-in page draws
    // (local\flavourchoice::offered()). Site-wide, keyed by the language and the offered ids, so a
    // change of the setting is a new key; Boost Union's flavour events empty it.
    'flavourmenu' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 4,
        'invalidationevents' => [
            'theme_boost_union_flavours_resorted',
            'theme_boost_union_flavours_created',
            'theme_boost_union_flavours_edited',
            'theme_boost_union_flavours_deleted',
        ],
    ],
    // The definitions of the custom profile fields the identity settings offer (local\identity::definitions()):
    // one entry, read only while a setting names a custom field. Site-wide and the same for everybody; the
    // profile field and category events delete it (db/events.php), which every node sees.
    'identityfields' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
    ],
    // The public calendar's two halves (local\calendar\publiccache::half()): what a visitor is shown, the same for
    // everybody, keyed by the half, its token, the language and the window. A bump of a token makes the entries under
    // the old one unreachable and purges them.
    'publiccalendar' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
    ],
    // The public calendar's revision tokens, one per half (local\calendar\publiccache::token()): shared by every node,
    // so a change observed on one is the token every node hands its next page.
    'publiccalendarrev' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
    ],
];
