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
 * Theme Dimensions - Tests for the chosen look's guards: the choice page, the return address, log in as, other themes
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the chosen look's security guards.
 *
 * The choice page's write is {@see flavourchoice::submitted()}, the one call flavour.php makes with its
 * form, so the page's rule (only the form's POST, with the session key and an offered option, writes)
 * is tested on the form the page builds rather than on the script, which ends in a redirect.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(flavourchoice::class)]
#[CoversClass(\theme_dimensions\form\flavourchoice::class)]
final class flavourchoice_guards_test extends \advanced_testcase {
    /**
     * A Boost Union flavour, made by Boost Union's own generator.
     *
     * @param string $title The flavour's title.
     * @return \stdClass The flavour's record.
     */
    private function flavour(string $title): \stdClass {
        global $DB;

        $count = $DB->count_records('theme_boost_union_flavours');
        $flavour = $this->getDataGenerator()->get_plugin_generator('theme_boost_union')
            ->create_flavour(['title' => $title, 'sort' => $count + 1]);

        return $DB->get_record('theme_boost_union_flavours', ['id' => $flavour->id], '*', MUST_EXIST);
    }

    /**
     * Offer exactly these flavours in the setting.
     *
     * @param \stdClass ...$flavours The flavours.
     * @return void
     */
    private function offer(\stdClass ...$flavours): void {
        set_config(flavourchoice::SETTING, implode(',', array_map(fn($f) => $f->id, $flavours)), 'theme_dimensions');
    }

    /**
     * The choice page's form, as flavour.php builds it.
     *
     * @param \stdClass ...$flavours The flavours it offers beside the site default.
     * @return \theme_dimensions\form\flavourchoice
     */
    private function form(\stdClass ...$flavours): \theme_dimensions\form\flavourchoice {
        $options = [flavourchoice::SITE_DEFAULT => 'Site default'];
        foreach ($flavours as $flavour) {
            $options[(int) $flavour->id] = $flavour->title;
        }

        return new \theme_dimensions\form\flavourchoice(
            new \moodle_url('/theme/dimensions/flavour.php'),
            ['options' => $options]
        );
    }

    /**
     * The person's stored look, read from the table.
     *
     * @param \stdClass $user The person.
     * @return string|false
     */
    private function stored(\stdClass $user): string|false {
        global $DB;

        return $DB->get_field('user_preferences', 'value', ['userid' => $user->id, 'name' => flavourchoice::PREFERENCE]);
    }

    /**
     * A page drawn in a theme, in a category.
     *
     * @param int $categoryid The page's category.
     * @param string $theme The theme the page is drawn in.
     * @return \moodle_page
     */
    private function page(int $categoryid, string $theme): \moodle_page {
        $page = new \moodle_page();
        $page->set_category_by_id($categoryid);
        $page->set_url('/course/index.php', ['categoryid' => $categoryid]);
        $page->force_theme($theme);

        return $page;
    }

    /**
     * Showing the choice page writes nothing, the preselected look of the menu's link included; only the form's POST does.
     *
     * @return void
     */
    public function test_the_choice_page_writes_only_from_its_form_post(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $_GET['flavour'] = (string) $harbour->id;
        $this->assertNull(flavourchoice::submitted($this->form($harbour)), 'The GET of the menu link only shows the form.');
        $this->assertFalse($this->stored($user));
        unset($_GET['flavour']);

        \theme_dimensions\form\flavourchoice::mock_submit(['flavour' => (string) $harbour->id, 'returnurl' => '']);
        $this->assertTrue(flavourchoice::submitted($this->form($harbour)), 'Control: the form\'s POST writes.');
        $this->assertSame((string) $harbour->id, $this->stored($user));
    }

    /**
     * A POST with a forged session key is refused before anything is written.
     *
     * @return void
     */
    public function test_a_post_with_a_forged_session_key_writes_nothing(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        \theme_dimensions\form\flavourchoice::mock_submit(['flavour' => (string) $harbour->id, 'returnurl' => '']);
        $_POST['sesskey'] = 'forged';
        try {
            flavourchoice::submitted($this->form($harbour));
            $this->fail('A POST with a forged session key is accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsesskey', $e->errorcode);
        }
        $this->assertFalse($this->stored($user));
    }

    /**
     * A POST naming a look the form did not offer is refused by the form, and nothing is written.
     *
     * @return void
     */
    public function test_a_post_of_a_look_the_form_did_not_offer_writes_nothing(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour, $campus);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        \theme_dimensions\form\flavourchoice::mock_submit(['flavour' => (string) $campus->id, 'returnurl' => '']);
        $this->assertNull(flavourchoice::submitted($this->form($harbour)));
        $this->assertFalse($this->stored($user));
    }

    /**
     * The return address stays on this site and loses its session key; without one it is the preferences page.
     *
     * @return void
     */
    public function test_the_return_address_is_local_and_carries_no_session_key(): void {
        $this->resetAfterTest();

        $preferences = (new \moodle_url('/user/preferences.php'))->out(false);
        $this->assertSame($preferences, flavourchoice::back_url('')->out(false));
        $this->assertSame($preferences, flavourchoice::back_url('https://elsewhere.example/steal')->out(false));
        $this->assertSame(
            (new \moodle_url('/course/view.php', ['id' => 2]))->out(false),
            flavourchoice::back_url('/course/view.php?id=2&sesskey=abc123')->out(false),
            'A local address keeps its parameters and loses the session key.'
        );

        $link = flavourchoice::page_url(5, new \moodle_url('/course/view.php', ['id' => 2, 'sesskey' => 'abc123']));
        $this->assertSame('/course/view.php?id=2', $link->get_param('returnurl'), 'The menu link carries no session key.');
    }

    /**
     * Somebody logged in as another user is offered no look and cannot choose one for that person.
     *
     * @return void
     */
    public function test_logged_in_as_another_user_no_look_is_offered_or_written(): void {
        $this->resetAfterTest();
        $harbour = $this->flavour('Harbour');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $here = new \moodle_url('/my/');

        $this->setUser($user);
        $this->assertNotNull(flavourchoice::menu($here), 'Control: the person themself is offered the looks.');

        $this->setAdminUser();
        \core\session\manager::loginas((int) $user->id, \context_system::instance());
        $this->assertTrue(\core\session\manager::is_loggedinas(), 'Precondition: logged in as the person.');

        $this->assertNull(flavourchoice::menu($here));
        $this->assertFalse(flavourchoice::choose((int) $harbour->id));
        $this->assertFalse($this->stored($user));
    }

    /**
     * A page another theme draws drops the look this session was primed with; Boost Union's own answer is left alone.
     *
     * @return void
     */
    public function test_a_page_of_another_theme_drops_the_primed_look(): void {
        $this->resetAfterTest();
        $category = $this->getDataGenerator()->create_category();
        $harbour = $this->flavour('Harbour');
        $campus = $this->flavour('Campus');
        $this->offer($harbour);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        set_user_preference(flavourchoice::PREFERENCE, $harbour->id);
        $flavours = \cache::make('theme_boost_union', 'flavours');

        flavourchoice::prime($this->page((int) $category->id, 'dimensions'));
        $this->assertSame((int) $harbour->id, (int) $flavours->get((int) $category->id)->id, 'Precondition: primed.');

        flavourchoice::prime($this->page((int) $category->id, 'boost_union'));
        $this->assertFalse($flavours->get((int) $category->id), 'Boost Union computes its own answer for its page.');

        $flavours->set((int) $category->id, $campus);
        flavourchoice::prime($this->page((int) $category->id, 'boost_union'));
        $this->assertSame(
            (int) $campus->id,
            (int) $flavours->get((int) $category->id)->id,
            'Control: with nothing primed, Boost Union\'s own entry stays.'
        );
    }
}
