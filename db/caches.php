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
    // files, keyed by category id. The only thing the showcase caches across requests:
    // viewer-independent, holding no count and no course name (branding::CACHED_KEYS is the
    // whole payload). An entry holds the category's RESOLVED colour pair, which may be an
    // ancestor's, so the whole cache is dropped when the editor saves, when a category is deleted
    // and when one is updated (a move among them).
    'categorybranding' => [
        'mode' => \core_cache\store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],
];
