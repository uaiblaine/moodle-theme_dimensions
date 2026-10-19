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
 * Theme Dimensions - Tests for the logo variants for dark surfaces
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the logo variants for dark surfaces: the address, the logo in force, the stylesheet and the file route.
 *
 * Every refusal is asserted beside a control that passes in the same test, so a method answering null, false or ''
 * for everything reddens as surely as one answering for everything.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(darklogo::class)]
final class darklogo_test extends \advanced_testcase {
    /** @var string A 1x1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * This theme's configuration as the SCSS callback and the renderer get it, read after the settings were written.
     *
     * @return \core\output\theme_config
     */
    private function theme(): \core\output\theme_config {
        return \core\output\theme_config::load('dimensions');
    }

    /**
     * Give the site both of Boost Union's light logos and both dark files.
     *
     * The settings hold what an admin_setting_configstoredfile writes: the file's path and name.
     *
     * @return void
     */
    private function all_logos(): void {
        set_config('logo', '/light.png', 'theme_boost_union');
        set_config('logocompact', '/lightcompact.png', 'theme_boost_union');
        set_config(darklogo::FULL, '/dark.png', 'theme_dimensions');
        set_config(darklogo::COMPACT, '/darkcompact.png', 'theme_dimensions');
    }

    /**
     * A Boost Union flavour, made the way Boost Union's own tests make one, with the logo fields given.
     *
     * The generator writes no logo field, so they are set on the row and the record is read back as Boost Union's
     * lookup would hand it over.
     *
     * @param array $logos The look_logo and look_logocompact values to store.
     * @return \stdClass
     */
    private function flavour(array $logos): \stdClass {
        global $DB;

        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')->create_flavour(['title' => 'Org']);
        foreach ($logos as $field => $value) {
            $DB->set_field('theme_boost_union_flavours', $field, $value, ['id' => $flavour->id]);
        }

        return $DB->get_record('theme_boost_union_flavours', ['id' => $flavour->id], '*', MUST_EXIST);
    }

    /**
     * No dark file, no address; the dark file with its light partner, an absolute one on the theme's own route.
     *
     * @return void
     */
    public function test_the_address_needs_the_dark_file(): void {
        $this->resetAfterTest();
        set_config('logocompact', '/lightcompact.png', 'theme_boost_union');

        $this->assertNull(darklogo::url(darklogo::COMPACT, $this->theme()), 'No dark file.');

        set_config(darklogo::COMPACT, '/darkcompact.png', 'theme_dimensions');
        $url = darklogo::url(darklogo::COMPACT, $this->theme());
        $this->assertNotNull($url, 'Control: the dark file with its light partner.');
        $this->assertStringStartsWith('https://www.example.com/moodle/pluginfile.php/', $url);
        $this->assertStringContainsString('/theme_dimensions/' . darklogo::COMPACT . '/', $url);
        $this->assertStringEndsWith('/darkcompact.png', $url);
        $this->assertSame(1, substr_count($url, 'www.example.com'), 'Absolute, and the host once.');
    }

    /**
     * A dark file without Boost Union's light partner is not used: the page shows what Boost Union shows.
     *
     * Each variant is paired with its own partner, so the other variant is the control in the same site.
     *
     * @return void
     */
    public function test_the_address_needs_the_light_partner(): void {
        $this->resetAfterTest();
        set_config('logo', '/light.png', 'theme_boost_union');
        unset_config('logocompact', 'theme_boost_union');
        set_config(darklogo::FULL, '/dark.png', 'theme_dimensions');
        set_config(darklogo::COMPACT, '/darkcompact.png', 'theme_dimensions');

        $this->assertNull(darklogo::url(darklogo::COMPACT, $this->theme()), 'The compact logo is not set.');
        $this->assertNotNull(darklogo::url(darklogo::FULL, $this->theme()), 'Control: the full logo is.');
    }

    /**
     * The address is asked of styleurl, and one it refuses is not painted.
     *
     * Core encodes every path segment, so only the site's own address can carry what styleurl refuses; a quote and
     * a parenthesis in it would close url("...") in the stylesheet.
     *
     * @return void
     */
    public function test_an_address_styleurl_refuses_is_not_painted(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->all_logos();

        $this->assertNotNull(darklogo::url(darklogo::COMPACT, $this->theme()), 'Control: the ordinary site address.');

        $CFG->wwwroot = "https://www.example.com/a')x";
        $this->assertNull(darklogo::url(darklogo::COMPACT, $this->theme()));
        $this->assertNull(darklogo::url(darklogo::FULL, $this->theme()));
        $this->assertSame('', darklogo::scss($this->theme()));
    }

    /**
     * A flavour's own logo is in force where it applies, variant by variant; without a flavour the site's is.
     *
     * @return void
     */
    public function test_a_flavours_own_logo_wins_variant_by_variant(): void {
        $this->resetAfterTest();

        $this->assertTrue(darklogo::site_logo_in_force(darklogo::COMPACT, null), 'No flavour applies.');
        $this->assertTrue(darklogo::site_logo_in_force(darklogo::FULL, null));

        $plain = $this->flavour([]);
        $this->assertTrue(darklogo::site_logo_in_force(darklogo::COMPACT, $plain), 'A flavour without logos.');
        $this->assertTrue(darklogo::site_logo_in_force(darklogo::FULL, $plain));

        $compact = $this->flavour(['look_logocompact' => 'org.png']);
        $this->assertFalse(darklogo::site_logo_in_force(darklogo::COMPACT, $compact));
        $this->assertTrue(darklogo::site_logo_in_force(darklogo::FULL, $compact), 'Control: its full logo is the site\'s.');

        $full = $this->flavour(['look_logo' => 'org.png']);
        $this->assertFalse(darklogo::site_logo_in_force(darklogo::FULL, $full));
        $this->assertTrue(darklogo::site_logo_in_force(darklogo::COMPACT, $full), 'Control: its compact logo is the site\'s.');
    }

    /**
     * The body classes join the two facts: an address, and the site's logo in force.
     *
     * @return void
     */
    public function test_the_body_classes_need_the_file_and_the_sites_logo(): void {
        $this->resetAfterTest();
        set_config('logo', '/light.png', 'theme_boost_union');
        set_config('logocompact', '/lightcompact.png', 'theme_boost_union');

        $this->assertSame([], darklogo::body_classes($this->theme(), null), 'No dark file.');

        set_config(darklogo::FULL, '/dark.png', 'theme_dimensions');
        set_config(darklogo::COMPACT, '/darkcompact.png', 'theme_dimensions');
        $this->assertSame(
            ['dimensions-darklogo-full', 'dimensions-darklogo-compact'],
            darklogo::body_classes($this->theme(), null)
        );

        $flavour = $this->flavour(['look_logocompact' => 'org.png']);
        $this->assertSame(['dimensions-darklogo-full'], darklogo::body_classes($this->theme(), $flavour));
    }

    /**
     * The extra SCSS declares each available variant's address on the root, and nothing without one.
     *
     * Asked through the theme's own callback, which is what the compiler calls.
     *
     * @return void
     */
    public function test_the_extra_scss_carries_the_addresses(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->resetAfterTest();

        $this->assertStringNotContainsString('--dimensions-logo', theme_dimensions_get_extra_scss($this->theme()));

        $this->all_logos();
        $scss = theme_dimensions_get_extra_scss($this->theme());
        $compact = darklogo::url(darklogo::COMPACT, $this->theme());
        $full = darklogo::url(darklogo::FULL, $this->theme());
        $this->assertStringContainsString('--dimensions-logo-compact-dark-src: url("' . $compact . '");', $scss);
        $this->assertStringContainsString('--dimensions-logo-compact-dark-shift: ', $scss);
        $this->assertStringContainsString('--dimensions-logo-dark-src: url("' . $full . '");', $scss);
        $this->assertStringContainsString('--dimensions-logo-dark-shift: ', $scss);
        $this->assertStringContainsString(':root {', $scss);

        // One variant alone: the other one's two properties are absent.
        unset_config('logo', 'theme_boost_union');
        $scss = theme_dimensions_get_extra_scss($this->theme());
        $this->assertStringContainsString('--dimensions-logo-compact-dark-src', $scss);
        $this->assertStringNotContainsString('--dimensions-logo-dark-src', $scss);
        $this->assertStringNotContainsString('--dimensions-logo-dark-shift', $scss);
    }

    /**
     * The file route takes both areas from the system context, and nothing else as a dark logo.
     *
     * @return void
     */
    public function test_the_route_takes_both_areas_and_no_other(): void {
        $this->resetAfterTest();
        $system = \core\context\system::instance();
        $category = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);

        $this->assertSame([darklogo::FULL, darklogo::COMPACT], darklogo::AREAS);
        foreach (darklogo::AREAS as $area) {
            $this->assertTrue(darklogo::serves($system, $area), "The {$area} area.");
            $this->assertFalse(darklogo::serves($category, $area), "The {$area} area under a category is not a setting file.");
        }
        $this->assertFalse(darklogo::serves($system, 'logo'), 'Boost Union\'s own area is not this theme\'s.');
        $this->assertFalse(darklogo::serves($system, darklogo::COMPACT . 'x'));
        // What the route hands such an area instead refuses it.
        $this->assertNull(landing\publicfiles::resolve($system, 'logo', [theme_get_revision(), 'dark.png']));
    }

    /**
     * The route reaches the theme's setting files for both areas: the address names the stored file.
     *
     * A send cannot be read in a PHPUnit process (core closes the output buffers and writes headers, which warn), so
     * this is the lookup theme_config::setting_file_serve() makes, on the path the address carries, beside the
     * wiring of the branch that hands the request to it.
     *
     * @return void
     */
    public function test_the_route_reaches_the_setting_files(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->resetAfterTest();
        $this->all_logos();
        $system = \core\context\system::instance();

        // Wired: without it the request would fall to the landing page's route, which ends the process.
        $source = file_get_contents($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->assertStringContainsString('darklogo::serves($context, $filearea)', $source);

        $names = [darklogo::FULL => 'dark.png', darklogo::COMPACT => 'darkcompact.png'];
        foreach ($names as $area => $filename) {
            $file = get_file_storage()->create_file_from_string([
                'contextid' => $system->id,
                'component' => 'theme_dimensions',
                'filearea' => $area,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $filename,
            ], base64_decode(self::PNG));

            // The address, as theme_config::setting_file_serve() reads it: the revision dropped, item 0.
            $path = substr(darklogo::url($area, $this->theme()), strlen($CFG->wwwroot . '/pluginfile.php'));
            [, $contextid, $component, $filearea, , $name] = explode('/', $path);
            $hash = sha1("/{$contextid}/{$component}/{$filearea}/0/{$name}");
            $this->assertSame($file->get_pathnamehash(), $hash, "The {$area} address names its stored file.");

            $this->assertTrue(darklogo::serves($system, $area), "The route takes the {$area} area.");
        }
    }
}
