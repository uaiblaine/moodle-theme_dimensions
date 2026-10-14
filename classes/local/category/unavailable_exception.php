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
 * Theme Dimensions - The one refusal of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

/**
 * The one exception the showcase's population raises for a category it will not list.
 *
 * A category that does not exist, one core hides from this viewer, one
 * local_unlistedcourses withholds from them and one that is not public when
 * the viewer is a visitor all raise this class with the same message, so a
 * guessed id cannot tell "missing" from "withheld": anything else would be an
 * existence oracle over every category on the site. The web services refuse
 * with the same lang string ({@see guard::ERROR_CATEGORY}).
 *
 * The message is a lang string so a page can show it; the class is final so
 * it cannot be subclassed into two distinguishable refusals.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unavailable_exception extends \moodle_exception {
    /**
     * Build the refusal. It takes no argument on purpose.
     */
    public function __construct() {
        parent::__construct('categoryunavailable', 'theme_dimensions');
    }
}
