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
 * Theme Dimensions - Tests for the language strings built from a key
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Pins the language strings whose ids the theme builds by concatenation.
 *
 * settings.php reads the description of each landing default as its key plus '_desc', and the two
 * custom field classes read the name and the description of each provisioned field as its string key
 * plus '_desc'. A search for literal string ids cannot see those ids, and a missing string only shows as the
 * [[id]] placeholder on the admin page or in the course field form.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landingdefaults::class)]
#[CoversClass(landingfields::class)]
#[CoversClass(focusmodefields::class)]
final class lang_keys_test extends \advanced_testcase {
    /**
     * Assert that each string id exists in the theme's English pack.
     *
     * @param array $ids String ids to look up.
     * @param string $what What the ids stand for, named in the failure message.
     * @return void
     */
    private function assert_strings_exist(array $ids, string $what): void {
        $manager = get_string_manager();
        foreach ($ids as $id) {
            $this->assertTrue($manager->string_exists($id, 'theme_dimensions'), "Missing string '$id' ($what).");
        }
    }

    /**
     * Every landing default setting has its title string and its description string, as settings.php
     * reads them.
     *
     * @return void
     */
    public function test_landing_default_settings_have_title_and_description_strings(): void {
        $keys = array_values(landingdefaults::settings());
        $this->assertNotEmpty($keys, 'The landing defaults map is empty, so this test would pass vacuously.');

        $ids = [];
        foreach ($keys as $key) {
            $ids[] = $key;
            $ids[] = $key . '_desc';
        }
        $this->assert_strings_exist($ids, 'landing default setting');
    }

    /**
     * Every provisioned landing page field has its name string and its description string.
     *
     * @return void
     */
    public function test_landing_fields_have_name_and_description_strings(): void {
        $definitions = landingfields::definitions();
        $this->assertNotEmpty($definitions, 'The landing field definitions are empty, so this test would pass vacuously.');

        $ids = [];
        foreach ($definitions as $definition) {
            $ids[] = $definition['stringkey'];
            $ids[] = $definition['stringkey'] . '_desc';
        }
        $this->assert_strings_exist($ids, 'landing page field');
    }

    /**
     * Every provisioned focus mode field has its name string and its description string.
     *
     * @return void
     */
    public function test_focus_mode_fields_have_name_and_description_strings(): void {
        $definitions = focusmodefields::definitions();
        $this->assertNotEmpty($definitions, 'The focus mode field definitions are empty, so this test would pass vacuously.');

        $ids = [];
        foreach ($definitions as $definition) {
            $ids[] = $definition['stringkey'];
            $ids[] = $definition['stringkey'] . '_desc';
        }
        $this->assert_strings_exist($ids, 'focus mode field');
    }

    /**
     * The lookup used by this test reports a string that does not exist, so a green run proves presence.
     *
     * @return void
     */
    public function test_lookup_reports_a_missing_string(): void {
        $this->assertFalse(get_string_manager()->string_exists('landingdefaultmodality_nosuchsuffix', 'theme_dimensions'));
    }
}
