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
 * Theme Dimensions - Tests for the invariants of the card's star
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of js/esm/src/listing/star.ts, the card's star, by reading its source and its build.
 *
 * The account's shell arms it (listing_account_source_test holds the order), the visitor's page never loads it
 * (categorypublic_source_test), and its behaviour is Jest's (js/esm/tests/listing/star.test.ts).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class listing_star_source_test extends \basic_testcase {
    /** @var string The star's source. */
    private const STAR = 'js/esm/src/listing/star.ts';

    /**
     * One file of the theme.
     *
     * @param string $path The path under the theme root.
     * @return string
     */
    private function read(string $path): string {
        $file = dirname(__DIR__, 3) . '/' . $path;
        $this->assertFileExists($file);
        return (string) file_get_contents($file);
    }

    /**
     * The body of one top-level arrow function of a source.
     *
     * @param string $name The function's name.
     * @param string $path The source's path under the theme root.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $path = self::STAR): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read($path), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$path}.");
        return $matches[1];
    }

    /**
     * The star calls the theme's guarded wishlist service and never core's, from one listener armed once.
     *
     * core_course_set_favourite_courses instantiates a course context and checks nothing else, so it would star a
     * course this page is not allowed to admit exists. The second half is the control: a module that called no
     * service at all would pass the first on its own.
     *
     * Changes that must make it fail: the shell no longer arming the star (gate js_account_arms_star); the
     * listener armed on every call.
     */
    public function test_the_star_calls_the_themes_own_service_and_is_armed_once(): void {
        $star = $this->read(self::STAR);
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $star);
        $this->assertStringContainsString("const TOGGLE_METHOD = 'theme_dimensions_toggle_wishlist';", $star);
        $this->assertStringContainsString(
            "methodname: TOGGLE_METHOD,\n            args: {courseid: courseid, state: wanted},",
            $this->body('toggleStar')
        );
        $this->assertStringContainsString('theme_dimensions_toggle_wishlist', $this->read('js/esm/build/listing/star.js'));
        $arm = $this->body('arm');
        $this->assertMatchesRegularExpression(
            '~if \(armed\) \{\s*return;\s*\}\s*armed = true;~',
            $arm,
            'One listener, whoever calls.'
        );
        $this->assertStringContainsString("document.addEventListener('click', (event) => {", $arm, 'Delegated, for later cards.');
        $this->assertStringContainsString("import {arm as armStar} from './star';", $this->read('js/esm/src/listing/account.ts'));
        $this->assertMatchesRegularExpression(
            '~^    // [^\n]*\n    armStar\(\);\n~',
            $this->body('start', 'js/esm/src/listing/account.ts'),
            'First in start(), before the listing is looked for.'
        );
    }

    /**
     * The star writes its own mark on the body and never the listing's; the account's entry writes none.
     */
    public function test_the_star_writes_its_own_mark(): void {
        $this->assertMatchesRegularExpression("~^const BODY_CLASS = 'dim-star-js';$~m", $this->read(self::STAR));
        $this->assertMatchesRegularExpression(
            '~^    document\.body\.classList\.add\(BODY_CLASS\);\n    if \(armed\)~',
            $this->body('arm')
        );
        foreach ([self::STAR, 'amd/src/listingaccount.js'] as $path) {
            $this->assertStringNotContainsString("'dim-js'", $this->read($path), $path . ' never writes the listing\'s mark.');
        }
        $this->assertStringNotContainsString('BODY_CLASS', $this->read('amd/src/listingaccount.js'), 'The entry writes no mark.');
    }

    /**
     * The star flips at once, holds one round trip at a time and puts the old state back on a failure.
     *
     * Two clicks in flight race in the server, and the last to arrive decides the stored state; a refusal leaves a
     * star the server never stored unless the old state is painted back. Its count is not refreshed: the reader
     * changed it themselves, and the next listing request brings it.
     */
    public function test_a_press_flips_at_once_one_trip_at_a_time_and_a_failure_puts_it_back(): void {
        $toggle = $this->body('toggleStar');
        $this->assertMatchesRegularExpression(
            "~^    if \(button\.dataset\.busy === '1'\) \{\n(?:        //[^\n]*\n)*        return;\n    \}\n"
                . "    button\.dataset\.busy = '1';~",
            $toggle
        );
        $this->assertMatchesRegularExpression(
            "~const wanted = button\.getAttribute\('aria-pressed'\) !== 'true';\s*paintStar\(button, wanted\);\s*try \{~",
            $toggle
        );
        $this->assertStringContainsString('paintStar(button, Boolean(response.favourite));', $toggle);
        $this->assertMatchesRegularExpression(
            "~\} catch \(error\) \{\s*paintStar\(button, !wanted\);\s*const Notification = await load\('core/notification'\);\s*"
                . 'Notification\.exception\(error\);\s*\} finally \{\s*button\.dataset\.busy = \'0\';~',
            $toggle
        );
        $paint = $this->body('paintStar');
        $this->assertStringContainsString("button.setAttribute('aria-pressed', on ? 'true' : 'false');", $paint);
        $this->assertStringContainsString("icon.classList.toggle('fa-solid', on);", $paint);
        $this->assertStringContainsString("icon.classList.toggle('fa-regular', !on);", $paint);
        $this->assertStringContainsString('const text = on ? button.dataset.starRemove : button.dataset.starAdd;', $paint);
        $this->assertStringNotContainsString('innerHTML', $this->read(self::STAR));
    }
}
