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

/**
 * Tests for which login-layout pages stand on auth_loginsteps' shell.
 *
 * Class-level covers docblock (not the PHP attribute), as the rest of this suite.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \theme_dimensions\local\loginshell
 */
final class loginshell_test extends \advanced_testcase {
    /**
     * A page of the given type.
     *
     * @param string $url The page's address.
     * @return \moodle_page
     */
    private function page(string $url): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url($url);

        return $page;
    }

    /**
     * The second factor and the privileged session stand on the shell while it is the site's door.
     *
     * The control is the same two pages with the plugin's switch off, and core's other login-layout
     * pages, which never do.
     *
     * @return void
     */
    public function test_the_shell_is_for_the_two_steps_while_it_is_the_door(): void {
        $this->resetAfterTest();
        if (!class_exists(\auth_loginsteps\output\core_page_shell::class)) {
            $this->markTestSkipped('auth_loginsteps is not installed.');
        }
        set_config('auth', 'loginsteps');
        set_config('loginshell', '1', 'auth_loginsteps');

        $mfa = loginshell::for_page($this->page('/admin/tool/mfa/auth.php'));
        $this->assertSame('mfa', $mfa->screen);
        $this->assertSame(get_string('loginshell_mfa_title', 'theme_dimensions'), $mfa->title);
        $musudo = loginshell::for_page($this->page('/admin/tool/musudo/sudo_start.php'));
        $this->assertSame('musudo', $musudo->screen);
        $this->assertNull(loginshell::for_page($this->page('/login/forgot_password.php')));

        set_config('loginshell', '0', 'auth_loginsteps');
        $this->assertNull(loginshell::for_page($this->page('/admin/tool/mfa/auth.php')));
    }

    /**
     * The pagetypes are exactly the two steps.
     *
     * @return void
     */
    public function test_the_pagetypes_are_the_two_steps(): void {
        $this->assertSame(['admin-tool-mfa-auth', 'admin-tool-musudo-sudo_start'], loginshell::pagetypes());
    }
}
