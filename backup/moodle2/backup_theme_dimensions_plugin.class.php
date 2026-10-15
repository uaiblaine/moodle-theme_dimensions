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
 * Theme Dimensions - Course backup plugin
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Carries the hotsite's pictures - the four badges and the share picture - through course backups.
 *
 * The five picture fields are custom fields visible to nobody - the hotsite
 * and its shared link are their only display - and core's custom field handler runs the
 * picture type's backup AND restore callbacks only for fields the acting user
 * can view (customfield/classes/handler.php: backup_define_structure() and
 * restore_define_structure() both iterate get_instance_data() without
 * returnall). With every picture field hidden, the data rows still travel
 * (get_instance_data_for_backup() reads every field the user can view or edit,
 * the can_backup() test, and the restore maps the editable ones), but their
 * files would neither enter the archive nor leave it. So this plugin annotates
 * the customfield_picture area of the course, and records the id and shortname
 * of every picture data row, which is what the restore side
 * ({@see restore_theme_dimensions_plugin}) needs to map the archived
 * files onto the rows core recreated. The elements keep the name
 * "badge" they had before the share picture joined them, so an archive made
 * then restores unchanged.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_theme_dimensions_plugin extends backup_theme_plugin {
    /**
     * Define the course plugin structure: the picture rows, and the files of the picture area.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $badges = new backup_nested_element('badges');
        $badge = new backup_nested_element('badge', ['id'], ['shortname']);
        $pluginwrapper->add_child($badges);
        $badges->add_child($badge);

        // The shortnames are the theme's own constants, never user input.
        $shortnames = array_map(
            static fn(string $shortname): string => "'" . $shortname . "'",
            \theme_dimensions\local\hotsitefields::PICTURE_SHORTNAMES
        );
        $badge->set_source_sql(
            "SELECT d.id, f.shortname
               FROM {customfield_data} d
               JOIN {customfield_field} f ON f.id = d.fieldid
              WHERE d.instanceid = ?
                AND d.component = 'core_course'
                AND d.area = 'course'
                AND f.type = '" . \theme_dimensions\local\hotsitefields::PICTURE_TYPE . "'
                AND f.shortname IN (" . implode(', ', $shortnames) . ")",
            [backup::VAR_COURSEID]
        );

        $pluginwrapper->annotate_files('customfield_picture', 'file', null);

        return $plugin;
    }
}
