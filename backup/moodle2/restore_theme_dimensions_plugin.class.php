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
 * Theme Dimensions - Course restore plugin
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_dimensions\local\hotsitefields;

/**
 * Puts the hotsite's pictures - the badges and the share picture - back on the rows core restored.
 *
 * The picture rows themselves are restored by core (the handler maps every
 * editable field), but the picture type's own file callback never runs for a
 * field visible to nobody, so the files stay in the archive. The backup side
 * recorded each picture row's old id with its shortname; once the whole course
 * structure is in - the theme's element precedes the custom fields in the
 * XML, so nothing can be mapped while it is being read - the new row of the
 * same field is looked up, the old id mapped onto it, and the files of that
 * mapping pulled from the archive. Files a visible picture field's callback
 * already restored are left alone: core skips a file that exists.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_theme_dimensions_plugin extends restore_theme_plugin {
    /** @var string The mapping the archived files are restored through. */
    private const MAPPING = 'theme_dimensions_badge';

    /** @var int[] Old data row id keyed by field shortname, collected while the XML is read. */
    private array $oldids = [];

    /**
     * The paths this plugin reads from the course XML.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [new restore_path_element('badge', $this->get_pathfor('/badges/badge'))];
    }

    /**
     * Remember one picture row of the archived course (the element is named "badge" for older archives' sake).
     *
     * @param array $data The element: id and shortname.
     * @return void
     */
    public function process_badge($data) {
        $this->oldids[(string) $data['shortname']] = (int) $data['id'];
    }

    /**
     * Map every remembered row onto the one core recreated and restore its files.
     *
     * @return void
     */
    public function after_restore_course() {
        global $DB;

        $courseid = (int) $this->task->get_courseid();
        foreach ($this->oldids as $shortname => $oldid) {
            if (!in_array($shortname, hotsitefields::PICTURE_SHORTNAMES, true)) {
                continue;
            }
            $field = hotsitefields::get_field($shortname);
            if ($field === null) {
                continue;
            }
            $newid = $DB->get_field('customfield_data', 'id', ['fieldid' => $field->get('id'), 'instanceid' => $courseid]);
            if (!$newid) {
                continue;
            }
            $this->set_mapping(self::MAPPING, $oldid, (int) $newid, true);
        }
        if ($this->oldids) {
            $this->add_related_files('customfield_picture', 'file', self::MAPPING);
        }
    }
}
