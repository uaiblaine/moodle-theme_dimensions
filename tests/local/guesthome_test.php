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
 * Theme Dimensions - Tests for the guest's home page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the guest's home page.
 *
 * Every rule has its control in the same test: the guest against a real account, a URL home page
 * against a numeric one, the custom URL against the site home page, a valid URL against an invalid
 * one. And what core makes of the result is asserted through core's own get_home_page(), because
 * that is the reader the override exists for.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(guesthome::class)]
final class guesthome_test extends \advanced_testcase {
    /** @var string A URL home page written for accounts, as the site's is. */
    private const ACCOUNT_HOME = '/blocks/compass/index.php';

    /**
     * Set the site's home page in memory and in the database, as an administrator would.
     *
     * @param int|string $value A HOMEPAGE_* constant or a root-relative URL.
     * @return void
     */
    private function site_home(int|string $value): void {
        set_config('defaulthomepage', $value);
    }

    /**
     * The guest gets the site home page in place of a URL home page; a real account keeps the URL.
     */
    public function test_the_guest_gets_the_site_home_page_where_the_home_page_is_a_url(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablemyhome = 1;
        $this->site_home(self::ACCOUNT_HOME);

        $this->setGuestUser();
        $this->assertSame(HOMEPAGE_SITE, guesthome::replacement());
        guesthome::apply();
        $this->assertSame(HOMEPAGE_SITE, $CFG->defaulthomepage);
        $this->assertSame(HOMEPAGE_SITE, get_home_page(), 'Core now sends the guest to the site home page.');
        $this->assertSame(self::ACCOUNT_HOME, get_config('core', 'defaulthomepage'), 'Nothing is written.');

        // Control: a real account keeps the URL home page.
        $CFG->defaulthomepage = self::ACCOUNT_HOME;
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertNull(guesthome::replacement());
        guesthome::apply();
        $this->assertSame(self::ACCOUNT_HOME, $CFG->defaulthomepage);
        $this->assertSame(HOMEPAGE_URL, get_home_page());
    }

    /**
     * A visitor who is not logged in is not the guest account, and is left alone.
     */
    public function test_a_visitor_who_is_not_logged_in_is_left_alone(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->site_home(self::ACCOUNT_HOME);
        $this->setUser(null);

        $this->assertNull(guesthome::replacement());
        guesthome::apply();
        $this->assertSame(self::ACCOUNT_HOME, $CFG->defaulthomepage);
    }

    /**
     * A numeric home page is core's to resolve for the guest, and is left alone.
     *
     * Core already keeps the guest away from the dashboard and My courses; the override is for the URL
     * home page alone, which core does not.
     */
    public function test_a_numeric_home_page_is_left_alone(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config(guesthome::SETTING, guesthome::OPTION_URL, 'theme_dimensions');
        set_config(guesthome::SETTING_URL, '/course/index.php', 'theme_dimensions');
        $this->setGuestUser();

        foreach ([HOMEPAGE_SITE, HOMEPAGE_MY, HOMEPAGE_MYCOURSES, HOMEPAGE_USER] as $home) {
            $this->site_home($home);
            $this->assertNull(guesthome::replacement(), 'Home page ' . $home . ' is left alone.');
            guesthome::apply();
            $this->assertEquals($home, $CFG->defaulthomepage);
        }

        // Control: the same settings over a URL home page do apply.
        $this->site_home(self::ACCOUNT_HOME);
        $this->assertSame('/course/index.php', guesthome::replacement());
    }

    /**
     * The custom URL replaces the home page when it is chosen, and only then.
     */
    public function test_the_custom_url_is_used_only_when_it_is_chosen(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->site_home(self::ACCOUNT_HOME);
        set_config(guesthome::SETTING_URL, '/course/index.php', 'theme_dimensions');
        $this->setGuestUser();

        // A URL typed but the site home page chosen: the URL is not used.
        set_config(guesthome::SETTING, guesthome::OPTION_SITE, 'theme_dimensions');
        $this->assertSame(HOMEPAGE_SITE, guesthome::replacement());

        set_config(guesthome::SETTING, guesthome::OPTION_URL, 'theme_dimensions');
        $this->assertSame('/course/index.php', guesthome::replacement());
        guesthome::apply();
        $this->assertSame(HOMEPAGE_URL, get_home_page());
        $this->assertSame(
            (new \core\url('/course/index.php'))->out(false),
            get_default_home_page_url()->out(false),
            'Core turns the replacement back into the address.'
        );
    }

    /**
     * A custom URL core would not read as a home page falls back to the site home page.
     */
    public function test_an_invalid_custom_url_falls_back_to_the_site_home_page(): void {
        $this->resetAfterTest();
        $this->site_home(self::ACCOUNT_HOME);
        set_config(guesthome::SETTING, guesthome::OPTION_URL, 'theme_dimensions');
        $this->setGuestUser();

        $invalids = [
            '', '   ', 'course/index.php', 'https://elsewhere.example/', '/', '/index.php', '/?redirect=0',
            // A path PARAM_LOCALURL refuses: the only entry that reaches that check.
            '/bad url',
        ];
        foreach ($invalids as $invalid) {
            set_config(guesthome::SETTING_URL, $invalid, 'theme_dimensions');
            $this->assertSame(HOMEPAGE_SITE, guesthome::replacement(), 'Refused: "' . $invalid . '".');
        }

        // Control: a valid one, padded the way a pasted value arrives.
        set_config(guesthome::SETTING_URL, ' /course/index.php ', 'theme_dimensions');
        $this->assertSame('/course/index.php', guesthome::replacement());
    }
}
