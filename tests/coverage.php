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
 * Theme Dimensions - Which files of this theme a coverage run measures
 *
 * Core's default include list ({@see \core\test\phpunit\coverage_info::get_includelists()}) is
 * classes/, tests/generator/ and the top-level externallib.php, lib.php, locallib.php,
 * renderer.php and rsslib.php - a list shaped for an activity module. A file outside it is not
 * counted as uncovered: it is absent from the clover report, so leaving it out makes the
 * percentage look better, not worse.
 *
 * What the default misses here is every page script of the theme - the two entries of the
 * category showcase, the hotsite, focus mode, the refusal page - plus the settings page and the
 * two database scripts. Those hold guards: the appearance editor's capability check, focus
 * mode's enter and leave, the anonymous shell's forward. They are added below; a figure measured
 * with them is not comparable with one measured without.
 *
 * The list names files, not folders: config.php and version.php are declarations a coverage run
 * would only ever report as executed or not executed as a block, and they say nothing about
 * whether this theme is tested.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The files this theme adds to the coverage include list.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_dimensions_coverage extends \core\test\phpunit\coverage_info {
    /**
     * The page scripts, the settings page and the database scripts. lib.php and locallib.php are
     * already in core's default list and are deliberately not repeated here.
     *
     * @var array Paths relative to the theme root.
     */
    protected $includelistfiles = [
        'category.php',
        'categoryhero.php',
        'db/install.php',
        'db/upgrade.php',
        'focus.php',
        'hotsite.php',
        'settings.php',
        'unavailable.php',
    ];
}

return new theme_dimensions_coverage();
