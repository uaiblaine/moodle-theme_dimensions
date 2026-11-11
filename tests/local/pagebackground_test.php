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

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Boost Union's page background image reaches this theme's stylesheet under 'Inherit', and only once under 'Duplicate'.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pagebackground::class)]
final class pagebackground_test extends \advanced_testcase {
    /**
     * Store a page background image in Boost Union's setting.
     *
     * @return void
     */
    private function set_image(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');

        $context = \context_system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'theme_boost_union',
            'filearea' => 'backgroundimage',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'pageground.png',
        ], 'not really a png');
        set_config('backgroundimage', '/pageground.png', 'theme_boost_union');
    }

    /**
     * With an image the rule names Boost Union's file from md up; without one nothing is printed (the control).
     *
     * @return void
     */
    public function test_the_rule_names_boost_unions_image(): void {
        $this->resetAfterTest();
        $this->assertSame('', pagebackground::scss(), 'No image, no rule.');

        $this->set_image();
        $scss = pagebackground::scss();
        $this->assertMatchesRegularExpression(
            "~@media \\(min-width: 768px\\) \\{\\s*body \\{\\s*background-image: url\\('[^']*/theme_boost_union/backgroundimage/"
            . "[^']*/pageground\\.png'\\);\\s*background-size: cover;~",
            $scss
        );
    }

    /**
     * Under 'Inherit' the theme's extra SCSS carries the rule; under 'Duplicate' Boost Union's own extra SCSS does, so the
     * theme does not print it a second time.
     *
     * @return void
     */
    public function test_the_extra_scss_prints_it_once_under_either_inheritance(): void {
        $this->resetAfterTest();
        $this->set_image();
        $theme = \core\output\theme_config::load('dimensions');
        $rule = pagebackground::scss();
        $this->assertNotSame('', $rule);

        set_config('extrascssinheritance', THEME_DIMENSIONS_SETTING_INHERITANCE_INHERIT, 'theme_dimensions');
        $this->assertStringContainsString($rule, theme_dimensions_get_extra_scss($theme));

        set_config('extrascssinheritance', THEME_DIMENSIONS_SETTING_INHERITANCE_DUPLICATE, 'theme_dimensions');
        $duplicate = theme_dimensions_get_extra_scss($theme);
        $this->assertStringNotContainsString($rule, $duplicate);
        $this->assertStringContainsString('pageground.png', $duplicate, 'Control: Boost Union\'s copy carries the image.');
    }
}
