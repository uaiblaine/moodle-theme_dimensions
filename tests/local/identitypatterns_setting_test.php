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
 * Theme Dimensions - Tests for the identity settings as the settings page holds them
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the identity settings: what the patterns setting refuses, and what the page offers and stores.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(identitypatterns_setting::class)]
final class identitypatterns_setting_test extends \advanced_testcase {
    /**
     * One of the theme's settings, from the admin tree an administrator builds.
     *
     * @param string $name The setting's name, without the component.
     * @return \admin_setting
     */
    private function setting(string $name): \admin_setting {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $page = admin_get_root(true, true)->locate('theme_dimensions');
        $this->assertNotNull($page, 'The theme settings page is in the admin tree.');
        foreach ($page->get_tabs() as $tab) {
            foreach ($tab->settings as $setting) {
                if ($setting->plugin === identity::COMPONENT && $setting->name === $name) {
                    return $setting;
                }
            }
        }
        $this->fail('The settings page holds no setting named ' . $name);
    }

    /**
     * A value the mask can apply is stored; each refused line is named with its number.
     *
     * @return void
     */
    public function test_the_patterns_setting_refuses_a_line_it_names(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        self::getDataGenerator()->create_custom_profile_field(['shortname' => 'rank', 'name' => 'Rank', 'datatype' => 'text']);
        $setting = $this->setting(identity::SETTING_PATTERNS);
        $this->assertInstanceOf(identitypatterns_setting::class, $setting);

        $valid = "username=***.###.###-**\n\nprofile_field_rank=#*";
        $this->assertSame('', $setting->write_setting($valid));
        $this->assertSame($valid, get_config(identity::COMPONENT, identity::SETTING_PATTERNS));
        $this->assertSame('', $setting->write_setting(''), 'An empty value is the default.');

        $refused = [
            "username=###\nbroken" => 'Line 2: write the field, an equals sign and the pattern (broken).',
            "\nlastname=###" => 'Line 2: this is not one of the fields listed below (lastname=###).',
            'profile_field_gone=###' => 'Line 1: this is not one of the fields listed below (profile_field_gone=###).',
            'username=abc' => 'Line 1: the pattern needs at least one # or * (username=abc).',
            "email=#\r\nemail=*" => 'Line 2: this field already has a pattern on an earlier line (email=*).',
            // Plain text, which core's setting template escapes once.
            'x=<b>#' => 'Line 1: this is not one of the fields listed below (x=<b>#).',
        ];
        foreach ($refused as $value => $message) {
            $this->assertSame($message, $setting->write_setting($value));
        }
        $this->assertSame('', get_config(identity::COMPONENT, identity::SETTING_PATTERNS), 'A refused value is not stored.');
    }

    /**
     * The three field settings offer the vocabulary and default to the full name and to no line.
     *
     * @return void
     */
    public function test_the_field_settings_offer_the_vocabulary_and_default_to_the_full_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        self::getDataGenerator()->create_custom_profile_field(['shortname' => 'rank', 'name' => 'R&D', 'datatype' => 'text']);

        foreach ([identity::SETTING_NAVBAR, identity::SETTING_TITLE] as $name) {
            $setting = $this->setting($name);
            $this->assertSame(identity::FULLNAME, $setting->get_defaultsetting());
            $this->assertTrue($setting->load_choices());
            $this->assertSame(identity::fields(), array_keys($setting->choices));
            // Core's select escapes its options, so the label is plain.
            $this->assertSame('Profile field: R&D', $setting->choices['profile_field_rank']);
            $this->assertStringContainsString('>Profile field: R&amp;D</option>', $setting->output_html('fullname'));
            $this->assertSame('', $setting->write_setting('profile_field_rank'));
            $this->assertSame('profile_field_rank', get_config(identity::COMPONENT, $name));
            // Core's select ignores a value outside its choices without a word.
            $setting->write_setting('lastname');
            $this->assertSame('profile_field_rank', get_config(identity::COMPONENT, $name));
        }

        $lines = $this->setting(identity::SETTING_LINES);
        $this->assertSame([], $lines->get_defaultsetting());
        // Core's checkboxes print their labels as HTML, so the label is escaped.
        $this->assertTrue($lines->load_choices());
        $this->assertSame('Profile field: R&amp;D', $lines->choices['profile_field_rank']);
        $this->assertStringContainsString('>Profile field: R&amp;D</label>', $lines->output_html([]));
        $this->assertSame('', $lines->write_setting(['username' => 1, 'profile_field_rank' => 1, 'idnumber' => 0]));
        $this->assertSame('username,profile_field_rank', get_config(identity::COMPONENT, identity::SETTING_LINES));
    }
}
