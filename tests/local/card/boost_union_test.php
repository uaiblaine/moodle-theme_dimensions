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
 * Theme Dimensions - Tests for the reader of Boost Union's course overview settings
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\card;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the reader of Boost Union's three course overview settings.
 *
 * Every answer is asked of a page whose theme is forced, so a test never depends on the PHPUnit site's own theme:
 * this theme, a child of Boost Union, for the settings to apply, and Boost, which names no Boost Union, for the
 * card's own rule.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(boost_union::class)]
final class boost_union_test extends \advanced_testcase {
    /**
     * A page drawn in a theme.
     *
     * @param string $theme The theme's name.
     * @return \moodle_page
     */
    private static function page(string $theme): \moodle_page {
        $page = new \moodle_page();
        $page->force_theme($theme);
        return $page;
    }

    /**
     * The settings apply under Boost Union and its children, and not under a theme outside its family.
     */
    public function test_boost_union_applies_to_itself_and_its_children_only(): void {
        $this->resetAfterTest();

        $this->assertTrue(boost_union::active(self::page('dimensions')), 'A child of Boost Union.');
        $this->assertTrue(boost_union::active(self::page('boost_union')), 'Boost Union itself.');
        $this->assertFalse(boost_union::active(self::page('boost')), 'Boost names no Boost Union.');
    }

    /**
     * Each value of the progress setting, and a missing one, read as Boost Union reads them.
     *
     * @param string|null $value The stored value, null for none.
     * @param bool $expected Whether the progress is shown.
     */
    #[DataProvider('progress_values')]
    public function test_the_progress_setting(?string $value, bool $expected): void {
        $this->resetAfterTest();
        set_config(boost_union::SETTING_PROGRESS, $value, boost_union::COMPONENT);

        $this->assertSame($expected, boost_union::show_progress(self::page('dimensions')));
        $this->assertTrue(boost_union::show_progress(self::page('boost')), 'Outside Boost Union the row is drawn.');
    }

    /**
     * The values of the progress setting.
     *
     * @return array
     */
    public static function progress_values(): array {
        return [
            'yes' => ['yes', true],
            'no' => ['no', false],
            'missing, Boost Union\'s default' => [null, true],
        ];
    }

    /**
     * Each value of the image source, and a missing, empty or unknown one, read as Boost Union's renderer reads them.
     *
     * @param string|null $value The stored value, null for none.
     * @param string $expected The source.
     */
    #[DataProvider('source_values')]
    public function test_the_image_source_setting(?string $value, string $expected): void {
        $this->resetAfterTest();
        set_config(boost_union::SETTING_IMAGESOURCE, $value, boost_union::COMPONENT);

        $this->assertSame($expected, boost_union::image_source(self::page('dimensions')));
        $this->assertSame(boost_union::SOURCE_PATTERN, boost_union::image_source(self::page('boost')));
    }

    /**
     * The values of the image source setting.
     *
     * @return array
     */
    public static function source_values(): array {
        return [
            'the pattern' => ['coursepluspattern', boost_union::SOURCE_PATTERN],
            'the fallback image' => ['courseplusfallback', boost_union::SOURCE_FALLBACK],
            'missing, Boost Union\'s default' => [null, boost_union::SOURCE_PATTERN],
            'empty' => ['', boost_union::SOURCE_PATTERN],
            'a value Boost Union does not know' => ['somethingelse', boost_union::SOURCE_PATTERN],
        ];
    }

    /**
     * Each combination of the images setting, a missing one (all three) and an empty one (none).
     *
     * @param string|null $value The stored value, null for none.
     * @param array $expected view => whether its images are shown.
     */
    #[DataProvider('images_values')]
    public function test_the_images_setting(?string $value, array $expected): void {
        $this->resetAfterTest();
        set_config(boost_union::SETTING_IMAGES, $value, boost_union::COMPONENT);

        $page = self::page('dimensions');
        $boost = self::page('boost');
        foreach ($expected as $view => $shown) {
            $this->assertSame($shown, boost_union::show_images($view, $page), "The {$view} view.");
            $this->assertTrue(boost_union::show_images($view, $boost), "The {$view} view outside Boost Union.");
        }
    }

    /**
     * The values of the images setting.
     *
     * @return array
     */
    public static function images_values(): array {
        return [
            'all three' => ['card,list,summary', ['card' => true, 'list' => true, 'summary' => true]],
            'the list unchecked' => ['card,summary', ['card' => true, 'list' => false, 'summary' => true]],
            'the card unchecked' => ['list,summary', ['card' => false, 'list' => true, 'summary' => true]],
            'missing, Boost Union\'s default' => [null, ['card' => true, 'list' => true, 'summary' => true]],
            'every box unchecked' => ['', ['card' => false, 'list' => false, 'summary' => false]],
        ];
    }

    /**
     * A view the setting does not have is a programming error, not a silent false.
     */
    public function test_an_unknown_view_is_refused(): void {
        $this->expectException(\coding_exception::class);
        boost_union::show_images('grid', self::page('dimensions'));
    }

    /**
     * Without a page the request's own is asked: the default argument reaches $PAGE.
     */
    public function test_the_request_page_is_asked_by_default(): void {
        global $PAGE;
        $this->resetAfterTest();
        set_config(boost_union::SETTING_PROGRESS, 'no', boost_union::COMPONENT);

        $PAGE->force_theme('dimensions');
        $this->assertFalse(boost_union::show_progress());
    }
}
