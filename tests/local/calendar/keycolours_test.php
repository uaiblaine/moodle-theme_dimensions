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
 * Theme Dimensions - Tests for the event types' colour settings
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The colour settings of the calendar's keys (docs/calendar/PROPOSAL.md section 30.4): one hexadecimal picker per key
 * on the Calendar tab, defaulting to the stylesheet's own edge, refusing anything but a hex, and handing the pre SCSS
 * callback only a hex that differs from its default. What Sass derives from a colour is calendar_scss_test's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(keycolours::class)]
#[CoversClass(keycolour_setting::class)]
final class keycolours_test extends \advanced_testcase {
    /**
     * The admin settings' library: a setting reads its default through the admin tree.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/adminlib.php');
    }

    /**
     * The Calendar tab carries one picker per key, in the keys' order, each with its default and the cache purge.
     *
     * @return void
     */
    public function test_the_calendar_tab_offers_a_hex_picker_per_key_with_its_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // The full tree, as admin/settings.php asks for it: without it the page is built without its tabs.
        $page = admin_get_root(true, true)->locate('theme_dimensions');
        $this->assertNotNull($page, 'Precondition: the theme has its settings page.');
        $pickers = [];
        $heading = false;
        foreach ($page->get_tabs() as $tab) {
            if ($tab->name !== 'theme_dimensions_calendar') {
                continue;
            }
            foreach ($tab->settings as $setting) {
                if ($setting->plugin !== 'theme_dimensions') {
                    continue;
                }
                if ($setting->name === keycolours::HEADING) {
                    $heading = $setting instanceof \admin_setting_heading;
                }
                if (str_starts_with($setting->name, keycolours::PREFIX) && $setting->name !== keycolours::HEADING) {
                    $pickers[$setting->name] = $setting;
                }
            }
        }
        $this->assertTrue($heading, 'The pickers stand under their heading.');
        $expected = array_map([keycolours::class, 'setting'], array_keys(keycolours::DEFAULTS));
        $this->assertSame($expected, array_keys($pickers));
        foreach (keycolours::DEFAULTS as $key => $default) {
            $setting = $pickers[keycolours::setting($key)];
            $this->assertInstanceOf(keycolour_setting::class, $setting, $key);
            $this->assertSame($default, $setting->get_defaultsetting(), $key);
            $this->assertSame('theme_reset_all_caches', $setting->updatedcallback, $key);
            $this->assertSame(keycolours::labels($key)[0], $setting->visiblename, $key);
        }
    }

    /**
     * The setting drawn for an administrator: a hex field with core's id, name and value, the swatch label around a
     * native colour input that has no name, its accessible label, core's row with the default and the description.
     *
     * @return void
     */
    public function test_the_setting_draws_a_hex_field_behind_a_swatch(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/settings.php');
        $PAGE->set_context(\context_system::instance());

        $name = 'theme_dimensions/' . keycolours::setting('course');
        $setting = new keycolour_setting($name, 'Course events', 'The description of it.', '#2a31c9');
        $html = $setting->output_html('#7B1FA2');

        $field = '~<input type="text" name="' . preg_quote($setting->get_full_name(), '~') . '" id="'
            . preg_quote($setting->get_id(), '~') . '" value="#7B1FA2"~';
        $this->assertSame(1, preg_match($field, $html), 'The field carries core\'s name, id and value.');
        $this->assertSame(1, preg_match('~<label class="dim-keycolour__swatch">\s*<span class="dim-keycolour__preview"'
            . ' style="background-color: #7b1fa2;"></span>\s*<input type="color"[^>]*>\s*</label>~', $html), 'The swatch label.');
        preg_match('~<input type="color"[^>]*>~', $html, $native);
        $this->assertStringNotContainsString(' name=', $native[0], 'Only the text field is submitted.');
        $this->assertStringContainsString('value="#7b1fa2"', $native[0]);
        $label = get_string('calendarcolourpicker', 'theme_dimensions');
        $this->assertStringContainsString('aria-label="' . $label . '"', $native[0]);
        $this->assertStringContainsString('The description of it.', $html);
        $this->assertStringContainsString('#2a31c9', $html, 'The default is shown beside the setting.');
        $this->assertStringNotContainsString('admin_colourpicker', $html, 'Core\'s gradient picker is gone.');
        $this->assertSame(1, substr_count($html, 'type="color"'));
    }

    /**
     * A stored value that is not a hex (forced in config.php) draws the empty swatch and no colour; the field still
     * holds what is stored, escaped.
     *
     * @return void
     */
    public function test_an_invalid_value_draws_the_empty_swatch(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/admin/settings.php');
        $PAGE->set_context(\context_system::instance());

        $name = 'theme_dimensions/' . keycolours::setting('course');
        $setting = new keycolour_setting($name, 'Course events', 'The description of it.', '#2a31c9');
        $html = $setting->output_html('red"><b>');

        $this->assertStringContainsString('dim-keycolour__preview dim-keycolour__preview--empty"', $html);
        $this->assertStringNotContainsString('background-color', $html);
        $this->assertStringNotContainsString('<b>', $html, 'The value is escaped.');
        $this->assertStringContainsString('value="red&quot;&gt;&lt;b&gt;"', $html);
    }

    /**
     * Each default is the light edge scss/calendar.scss names for its key, read from the design system's primitive.
     *
     * @return void
     */
    public function test_the_defaults_are_the_edges_the_stylesheet_names(): void {
        $root = dirname(__DIR__, 3);
        $scss = (string) file_get_contents($root . '/scss/calendar.scss');
        $post = (string) file_get_contents($root . '/scss/post.scss');
        foreach (keycolours::DEFAULTS as $key => $default) {
            $pattern = '~--dim-cal-' . $key . '-edge: #\{dim-cal-light\(' . $key . ', edge, (\$dim-ds-[a-z]+-\d+)\)\};~';
            $this->assertSame(1, preg_match($pattern, $scss, $edge), "{$key}: the light edge reads its chosen colour.");
            $this->assertSame(1, preg_match('~^' . preg_quote($edge[1], '~') . ': (#[0-9a-f]{3,6});$~m', $post, $value), $edge[1]);
            $this->assertSame(\theme_dimensions\local\category\branding::hex($value[1]), $default, "{$key}: {$edge[1]}.");
        }
    }

    /**
     * What the picker stores: a hex, with or without its hash, lower-case and six digits; the default for nothing.
     *
     * @return array[]
     */
    public static function accepted(): array {
        return [
            'six digits' => ['#7B1FA2', '#7b1fa2'],
            'three digits' => ['#abc', '#aabbcc'],
            'no hash' => ['7b1fa2', '#7b1fa2'],
            'padded' => [' #7b1fa2 ', '#7b1fa2'],
            'nothing is the default' => ['', '#2a31c9'],
        ];
    }

    /**
     * The picker stores a hex alone.
     *
     * @param string $typed What was typed.
     * @param string $stored What is stored.
     * @return void
     */
    #[DataProvider('accepted')]
    public function test_the_picker_stores_a_hex(string $typed, string $stored): void {
        $this->resetAfterTest();
        $setting = new keycolour_setting('theme_dimensions/' . keycolours::setting('course'), 'Course', '', '#2a31c9');
        $this->assertSame('', $setting->write_setting($typed));
        $this->assertSame($stored, get_config('theme_dimensions', keycolours::setting('course')));
    }

    /**
     * What the picker refuses, every one of which core's own picker stores.
     *
     * @return array[]
     */
    public static function refused(): array {
        return [
            'a colour name' => ['red'],
            'rgb()' => ['rgb(1, 2, 3)'],
            'hsl()' => ['hsl(120, 50%, 50%)'],
            'transparent' => ['transparent'],
            'inherit' => ['inherit'],
        ];
    }

    /**
     * The picker refuses anything but a hex, and keeps what was stored; core's picker, the control, takes it.
     *
     * @param string $typed What was typed.
     * @return void
     */
    #[DataProvider('refused')]
    public function test_the_picker_refuses_anything_but_a_hex(string $typed): void {
        $this->resetAfterTest();
        $name = 'theme_dimensions/' . keycolours::setting('course');
        set_config(keycolours::setting('course'), '#123456', 'theme_dimensions');
        $setting = new keycolour_setting($name, 'Course', '', '#2a31c9');
        $this->assertNotSame('', $setting->write_setting($typed));
        $this->assertSame('#123456', get_config('theme_dimensions', keycolours::setting('course')));
        foreach (['#12345', '#1234567', '#12345g', '#123;}body{x:y', 'url(x)'] as $malformed) {
            $this->assertNotSame('', $setting->write_setting($malformed), $malformed);
        }
        $this->assertSame('#123456', get_config('theme_dimensions', keycolours::setting('course')));

        $core = new \admin_setting_configcolourpicker($name, 'Course', '', '#2a31c9');
        $this->assertSame('', $core->write_setting($typed), 'Control: core\'s picker stores it.');
    }

    /**
     * Only a stored hex other than its key's default reaches the Sass, in the keys' order; a value no validation saw
     * (forced in config.php) is read as unset; the pre SCSS callback writes the map.
     *
     * @return void
     */
    public function test_only_a_changed_hex_reaches_the_sass(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->assertSame('', keycolours::scss(), 'An untouched site hands Sass nothing.');
        $this->assertSame([], keycolours::chosen());

        set_config(keycolours::setting('activity'), '#FFF', 'theme_dimensions');
        set_config(keycolours::setting('course'), '#7b1fa2', 'theme_dimensions');
        set_config(keycolours::setting('site'), '#6a737b', 'theme_dimensions');
        set_config(keycolours::setting('overdue'), '#CA3120', 'theme_dimensions');
        set_config(keycolours::setting('user'), 'red', 'theme_dimensions');
        set_config(keycolours::setting('group'), '#123;} body {x: y', 'theme_dimensions');
        set_config(keycolours::setting('other'), '7b1fa2', 'theme_dimensions');

        $this->assertSame(['course' => '#7b1fa2', 'activity' => '#ffffff'], keycolours::chosen());
        $sass = "\n\$dim-cal-colours: (course: #7b1fa2, activity: #ffffff) !default;\n";
        $this->assertSame($sass, keycolours::scss());

        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $pre = theme_dimensions_get_pre_scss(\core\output\theme_config::load('dimensions'));
        $this->assertStringContainsString($sass, $pre);
        $this->assertStringNotContainsString('red', substr($pre, (int) strpos($pre, '$dim-cal-colours')));
    }

    /**
     * Every key has its title and description, and a key outside the list is a coding error.
     *
     * @return void
     */
    public function test_every_key_has_its_strings(): void {
        foreach (array_keys(keycolours::DEFAULTS) as $key) {
            [$title, $description] = keycolours::labels($key);
            $this->assertNotSame('', $title);
            $this->assertNotSame('', $description);
            $this->assertNotSame($title, $description);
        }
        $this->expectException(\coding_exception::class);
        keycolours::labels('holiday');
    }
}
