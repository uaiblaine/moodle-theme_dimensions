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
 * Theme Dimensions - Tests for the linear navigation footer's module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing else reads about amd/src/linearfooter.js: it shows the footer the way Boost's module does, only
 * near the end, only on a phone, only while core has the footer enabled, and after core's own listener.
 *
 * Behat cannot see it: core's module never hides the footer on the Behat site, and its window is wider than 768px.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class linearfooter_source_test extends \basic_testcase {
    /**
     * The module's source, comments removed.
     *
     * @return string
     */
    private function source(): string {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/amd/src/linearfooter.js');
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * It listens on the window, where the scroll arrives after core's listener on the document, so its show wins over
     * core's hide in the same task; and it never prevents the scroll.
     *
     * @return void
     */
    public function test_it_listens_after_core_and_passively(): void {
        $js = $this->source();

        $this->assertStringContainsString("window.addEventListener('scroll', onScroll, {passive: true});", $js);
        $this->assertStringNotContainsString("document.addEventListener('scroll'", $js);
    }

    /**
     * It shows the footer only near the end, only below Boost's own 768px, only while core has it enabled, and in
     * Boost's own way: the class on the body and on #page.
     *
     * @return void
     */
    public function test_it_shows_only_near_the_end_on_a_phone_while_enabled(): void {
        $js = $this->source();

        $this->assertStringContainsString('const PHONE = 768;', $js);
        $this->assertStringContainsString('const END = 0.25;', $js);
        // The class is read before any layout: on the way up core has just shown the footer, and a read would force one.
        $this->assertStringContainsString(
            'if (!enabled || document.body.classList.contains(SHOWN) || document.body.clientWidth >= PHONE || !nearEnd()) {',
            $js
        );
        $this->assertStringContainsString(
            'const left = document.documentElement.scrollHeight - window.innerHeight - window.scrollY;',
            $js
        );
        $this->assertStringContainsString('return left <= window.innerHeight * END;', $js);
        $this->assertStringContainsString("const SHOWN = 'hasstickyfooter';", $js);
        $this->assertStringContainsString('document.body.classList.add(SHOWN);', $js);
        $this->assertStringContainsString('document.querySelector(SELECTORS.PAGE)?.classList.add(SHOWN);', $js);
        $this->assertStringNotContainsString('classList.remove', $js, 'Hiding stays core\'s.');
    }

    /**
     * It arms only on the linear navigation's footer, follows the state core announces, and stands down on Behat.
     *
     * @return void
     */
    public function test_it_arms_on_the_linear_footer_and_follows_core(): void {
        $js = $this->source();

        $this->assertStringContainsString(
            "if (!footer || !footer.querySelector(SELECTORS.LINEAR) || document.body.classList.contains('behat-site')) {",
            $js
        );
        $this->assertStringContainsString("LINEAR: '.course-linear-navigation',", $js);
        $this->assertStringContainsString('enabled = !footer.dataset.disable;', $js);
        $this->assertStringContainsString('document.addEventListener(eventTypes.stickyFooterStateChanged, (event) => {', $js);
        $this->assertStringContainsString("import {eventTypes} from 'core/sticky-footer';", $js);
    }

    /**
     * Core's thresholds are what this module mirrors: Boost's module still compares document.body.clientWidth with 768,
     * hides with the same class, and listens on the document.
     *
     * @return void
     */
    public function test_boosts_module_still_works_the_way_this_mirrors(): void {
        global $CFG;
        $boost = (string) file_get_contents($CFG->dirroot . '/theme/boost/amd/src/sticky-footer.js');

        $this->assertStringContainsString('if (document.body.clientWidth >= 768) {', $boost);
        $this->assertStringContainsString("HASSTICKYFOOTER: 'hasstickyfooter',", $boost);
        $this->assertStringContainsString('document.addEventListener("scroll", scrollSpy);', $boost);
    }
}
