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
 * Theme Dimensions - Tests for the stored value of the looks a person may choose
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests that the setting of the looks a person may choose always holds a value.
 *
 * Core's multi-checkbox setting draws and saves nothing while no Boost Union flavour exists, so a setting
 * nobody stored stays new for good and upgradesettings.php shows an empty page whose Save brings it back.
 * The tests run against the theme's own settings page as an administrator builds it, because settings.php
 * adds nothing for a session that may not configure the site.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(flavourchoice::class)]
final class flavourchoice_setting_test extends \advanced_testcase {
    /**
     * The new settings the theme's settings page holds, keyed by page, as upgradesettings.php draws them.
     *
     * @return array Page name => the page's html; empty when nothing is new.
     */
    private function new_settings(): array {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $node = admin_get_root(true, true)->locate('theme_dimensions');
        $this->assertNotNull($node, 'The theme settings page is in the admin tree.');

        return admin_output_new_settings_by_page($node);
    }

    /**
     * Clear the stored value, as a site that never stored it has it.
     *
     * @return void
     */
    private function forget_setting(): void {
        unset_config(flavourchoice::SETTING, 'theme_dimensions');
        $this->assertFalse(get_config('theme_dimensions', flavourchoice::SETTING));
    }

    /**
     * On a site with no flavour, an unset setting is new until ensure_setting() stores an empty list.
     *
     * @return void
     */
    public function test_a_site_without_flavours_keeps_no_new_setting(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertSame([], flavourchoice::choices(), 'The test site has no Boost Union flavour.');

        $this->forget_setting();
        $this->assertArrayHasKey('theme_dimensions', $this->new_settings(), 'Unset, the setting counts as new.');

        flavourchoice::ensure_setting();

        $this->assertSame('', get_config('theme_dimensions', flavourchoice::SETTING));
        $this->assertSame([], $this->new_settings());
        $this->assertSame([], flavourchoice::selectable_ids());
    }

    /**
     * A stored value is kept, the empty one included.
     *
     * @return void
     */
    public function test_a_stored_value_is_kept(): void {
        $this->resetAfterTest();

        set_config(flavourchoice::SETTING, '3,5', 'theme_dimensions');
        flavourchoice::ensure_setting();
        $this->assertSame('3,5', get_config('theme_dimensions', flavourchoice::SETTING));

        set_config(flavourchoice::SETTING, '', 'theme_dimensions');
        flavourchoice::ensure_setting();
        $this->assertSame('', get_config('theme_dimensions', flavourchoice::SETTING));
    }

    /**
     * The install function stores the value.
     *
     * @return void
     */
    public function test_the_install_stores_the_value(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/db/install.php');
        $this->resetAfterTest();

        $this->forget_setting();
        xmldb_theme_dimensions_install();

        $this->assertSame('', get_config('theme_dimensions', flavourchoice::SETTING));
    }

    /**
     * The upgrade step stores the value on a site upgraded from the version before it.
     *
     * @return void
     */
    public function test_the_upgrade_stores_the_value(): void {
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/theme/dimensions/db/upgrade.php');
        $this->resetAfterTest();

        $this->forget_setting();
        set_config('version', 2026100591, 'theme_dimensions');
        xmldb_theme_dimensions_upgrade(2026100591);

        $this->assertSame('', get_config('theme_dimensions', flavourchoice::SETTING));
        // The step's savepoint, or a later step's: the upgrade runs every step after it.
        $this->assertGreaterThanOrEqual(2026100592, (int) get_config('theme_dimensions', 'version'));
    }
}
