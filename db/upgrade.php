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
 * Theme Dimensions - Upgrade script
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade steps.
 *
 * The plugin starts from db/install.xml and db/install.php, which provision every field as
 * {@see \theme_dimensions\local\landingfields::definitions()} has it now; a step here brings a
 * site provisioned before a change up to it.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_theme_dimensions_upgrade($oldversion) {
    if ($oldversion < 2026100569) {
        // The page model select gains its third line, the trail.
        \theme_dimensions\local\landingfields::add_trail_option();
        upgrade_plugin_savepoint(true, 2026100569, 'theme', 'dimensions');
    }
    if ($oldversion < 2026100592) {
        // A site installed with no Boost Union flavour never stored the looks a person may choose,
        // which kept upgradesettings.php showing an empty page.
        \theme_dimensions\local\flavourchoice::ensure_setting();
        upgrade_plugin_savepoint(true, 2026100592, 'theme', 'dimensions');
    }
    return true;
}
