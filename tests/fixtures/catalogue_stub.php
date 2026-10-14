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
 * Theme Dimensions - A stand-in for local_page's catalogue
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Answers for_viewer() with whatever the test set, and remembers every category it was asked about.
 *
 * The shape is local_page's catalogue::for_viewer() (1.0.10+uai.13): id, name, url and description
 * per page, the name and the description in the plain spelling. The theme is coupled to the plugin
 * by NAME only, so its own rules are tested through this stand-in - on a site with the plugin and on
 * one without it alike - and the real class has its own test, which runs where it is installed.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_dimensions_catalogue_stub {
    /** @var array[] What for_viewer() answers. */
    public static array $pages = [];

    /** @var int[] The category ids asked about, in order. */
    public static array $asked = [];

    /**
     * The pages the test set, whatever the category.
     *
     * @param int $categoryid Course category id
     * @return array[]
     */
    public static function for_viewer(int $categoryid): array {
        self::$asked[] = $categoryid;
        return self::$pages;
    }
}
