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
 * Theme Dimensions - Tests for the help dialogue's module as the page arms it
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Renders a real page header and reads the AMD calls it queued: a page under PHPUnit prints no footer JavaScript, so
 * the calls are read off the requirements manager, where every js_call_amd() leaves one snippet.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(core_renderer::class)]
final class helpmodal_render_test extends \advanced_testcase {
    /** @var string The module, as the page's AMD calls name it. */
    private const MODULE = 'theme_dimensions/helpmodal';

    /**
     * This theme's renderer on a fresh page of a layout.
     *
     * @param string $url The page's url.
     * @param string $layout The page layout.
     * @return core_renderer
     */
    private function renderer(string $url, string $layout): core_renderer {
        global $PAGE, $OUTPUT, $SITE;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_course($SITE);
        $PAGE->set_url($url);
        $PAGE->set_pagelayout($layout);
        $PAGE->force_theme('dimensions');
        $PAGE->initialise_theme_and_output();
        $OUTPUT = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertInstanceOf(core_renderer::class, $OUTPUT);
        $PAGE->set_title('Page');
        $PAGE->set_heading('Page');

        return $OUTPUT;
    }

    /**
     * The AMD calls the page has queued that name the module, each as its snippet.
     *
     * @return string[]
     */
    private function calls(): array {
        global $PAGE;

        $snippets = (array) (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);

        return array_values(array_filter(
            $snippets,
            static fn($code): bool => str_contains((string) $code, "'" . self::MODULE . "'")
        ));
    }

    /**
     * The pages a help icon can be on: a logged-in account's ordinary page, and a visitor's login and embedded pages.
     *
     * @return array
     */
    public static function pages(): array {
        return [
            'an account on the dashboard' => ['/my/', 'standard', true],
            'a visitor on the login page' => ['/login/index.php', 'login', false],
            'a visitor on an embedded page' => ['/login/index.php', 'embedded', false],
        ];
    }

    /**
     * Every layout arms the module once, with no argument, logged in or not.
     *
     * The control is the empty queue before the header: the call is the header's, not a leftover of another page.
     *
     * @param string $url The page's url.
     * @param string $layout The page layout.
     * @param bool $loggedin Whether a real account is logged in.
     * @return void
     */
    #[DataProvider('pages')]
    public function test_every_layout_arms_the_module_once(string $url, string $layout, bool $loggedin): void {
        $this->resetAfterTest();
        if ($loggedin) {
            $this->setUser(self::getDataGenerator()->create_user());
        }
        $renderer = $this->renderer($url, $layout);
        $this->assertSame([], $this->calls(), 'Control: nothing is armed before the header.');

        $renderer->header();

        $calls = $this->calls();
        $this->assertCount(1, $calls);
        $this->assertMatchesRegularExpression(
            "~require\\(\\['" . preg_quote(self::MODULE, '~') . "'\\], function\\(amd\\) \\{amd\\.init\\(\\);~",
            $calls[0],
            'init() is called with no argument.'
        );
    }
}
