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
 * Theme Dimensions - Wishlist updated event
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\event;

/**
 * Somebody starred or unstarred a course on the category showcase.
 *
 * The object is the course, not the favourite row: the row is core's, its id
 * means nothing outside core_favourites, and what an administrator reading
 * the log wants to know is which course a person put on their list. The
 * course is also the event's context.
 *
 * Fired by {@see \theme_dimensions\local\category\wishlist::toggle()}
 * only when a favourite was actually created or deleted; a call asking for the
 * state the course already holds logs nothing.
 *
 * The favourite row itself is core's, under component core_course, so
 * core_favourites owns its privacy and this theme claims none of it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wishlist_updated extends \core\event\base {
    /**
     * Set the basic properties.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'course';
    }

    /**
     * The localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_wishlist_updated', 'theme_dimensions');
    }

    /**
     * A non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $verb = empty($this->other['state']) ? 'removed from' : 'added to';
        return "The user with id '{$this->userid}' {$verb} their favourites the course with id " .
            "'{$this->objectid}' on the category showcase.";
    }

    /**
     * The URL related to the event: the course that was starred.
     *
     * @return \core\url
     */
    public function get_url() {
        return new \core\url('/course/view.php', ['id' => $this->objectid]);
    }

    /**
     * Validate the data this event is created with.
     *
     * @return void
     * @throws \coding_exception When the object or the state is missing.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->objectid)) {
            throw new \coding_exception('The objectid must be set: it is the course that was starred.');
        }
        if (!isset($this->other['state'])) {
            throw new \coding_exception('The other[state] must be set: it is what the star was flipped to.');
        }
    }

    /**
     * Mapping of the object id for backup and restore of logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'course', 'restore' => 'course'];
    }

    /**
     * Mapping of the other fields for backup and restore of logs: the state needs none.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
