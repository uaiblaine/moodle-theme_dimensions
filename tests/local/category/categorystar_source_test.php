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
 * Theme Dimensions - Tests for the invariants of the star's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/categorystar.js, the card's star, by reading its source and its build.
 *
 * The star stays AMD until R5 (docs/vitrine-react/PLAN.md): an account's listing entry arms it
 * (listing_account_source_test holds the order), and the visitor's page never loads it
 * (categorypublic_source_test). The panel's and the posters' rules are the React component's
 * (areas_component_source_test and js/esm/tests/areas).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categorystar_source_test extends \basic_testcase {
    /**
     * One AMD module's source.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function source(string $module = 'categorystar'): string {
        $path = dirname(__DIR__, 3) . '/amd/src/' . $module . '.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * One file of the module's build.
     *
     * @param string $suffix The build file's suffix: .min.js or .min.js.map.
     * @return string
     */
    private function build(string $suffix = '.min.js'): string {
        $path = dirname(__DIR__, 3) . '/amd/build/categorystar' . $suffix;
        $this->assertFileExists($path, 'The build is missing: Moodle serves amd/build, never amd/src.');
        return (string) file_get_contents($path);
    }

    /**
     * The body of one top-level arrow function of a module.
     *
     * @param string $name The function's name.
     * @param string $module The module's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = 'categorystar'): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source($module), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        return $matches[1];
    }

    /**
     * The star calls the theme's guarded wishlist service and never core's, from one listener armed once.
     *
     * core_course_set_favourite_courses instantiates a course context and checks nothing else, so it
     * would star a course this page is not allowed to admit exists. The second half is the control:
     * a module that called no service at all would pass the first on its own.
     *
     * Changes that must make it fail: the account's entry no longer arming the star (gate js_account_arms_star).
     */
    public function test_the_star_calls_the_themes_own_service_and_is_armed_once(): void {
        $star = $this->source();
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $star);
        $this->assertStringContainsString("methodname: 'theme_dimensions_toggle_wishlist',", $this->body('toggleStar'));
        $this->assertStringContainsString('theme_dimensions_toggle_wishlist', $this->build());
        $init = $this->body('init');
        $this->assertMatchesRegularExpression(
            '~if \(armed\) \{\s*return;\s*\}\s*armed = true;~',
            $init,
            'One listener, whoever calls.'
        );
        $this->assertStringContainsString("document.addEventListener('click', (event) => {", $init, 'Delegated, for later cards.');
        $this->assertStringContainsString(
            "import * as CategoryStar from 'theme_dimensions/categorystar';",
            $this->source('listingaccount')
        );
        $entry = $this->body('init', 'listingaccount');
        $this->assertStringContainsString('CategoryStar.init();', $entry, 'The entry arms the star.');
    }

    /**
     * The star writes its own mark on the body and never the listing's; the account's entry writes none.
     */
    public function test_the_star_writes_its_own_mark(): void {
        $this->assertMatchesRegularExpression("~^const BODY_CLASS = 'dim-star-js';$~m", $this->source());
        $this->assertStringContainsString('document.body.classList.add(BODY_CLASS);', $this->body('init'));
        foreach (['categorystar', 'listingaccount'] as $module) {
            $source = $this->source($module);
            $this->assertStringNotContainsString("'dim-js'", $source, $module . ' never writes the listing\'s mark.');
        }
        // The listing's mark is the account's shell's, written by the store (listing_account_source_test).
        $this->assertStringNotContainsString('BODY_CLASS', $this->source('listingaccount'), 'The entry writes no mark of its own.');
    }

    /**
     * Nothing the star draws is assembled from a string.
     */
    public function test_nothing_is_assembled_from_a_string(): void {
        $this->assertStringNotContainsString('innerHTML', $this->source());
        $this->assertStringNotContainsString('insertAdjacentHTML', $this->source());
    }

    /**
     * The committed build was made from the source committed beside it.
     *
     * Moodle serves amd/build and never amd/src, so a source edit that was never rebuilt is invisible in the
     * browser and green in every other assertion of this file.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $map = json_decode($this->build('.min.js.map'), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertSame(
            $this->source(),
            (string) ($map['sourcesContent'][0] ?? ''),
            'amd/build/categorystar is not this source: rebuild it with grunt amd and commit the build.'
        );
    }
}
