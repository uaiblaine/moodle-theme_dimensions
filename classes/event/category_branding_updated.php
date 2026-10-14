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
 * Theme Dimensions - Category branding updated event
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\event;

/**
 * A manager changed the appearance of a course category on the category showcase.
 *
 * The object is the branding row; the category is the event's context. Fired by
 * {@see \theme_dimensions\local\category\branding::save()} on every save, because
 * what a category's hero says to everyone who opens it is something an administrator wants
 * in the log.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_branding_updated extends \core\event\base {
    /**
     * Set the basic properties.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'theme_dimensions_cat';
    }

    /**
     * The localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_category_branding_updated', 'theme_dimensions');
    }

    /**
     * A non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' changed the appearance of the course category with id " .
            "'{$this->contextinstanceid}' on the category showcase.";
    }

    /**
     * The URL related to the event: the editor that produced it.
     *
     * @return \core\url
     */
    public function get_url() {
        return new \core\url('/theme/dimensions/categoryhero.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Mapping of the object id for backup and restore of logs.
     *
     * Category branding is not carried by any backup, so the id has no restore mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'theme_dimensions_cat', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Mapping of the other fields for backup and restore of logs: there are none.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
