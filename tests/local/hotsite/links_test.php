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
 * Theme Dimensions - Tests for the hotsite addresses
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hotsite;

use theme_dimensions\local\hotsitefields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the hotsite addresses.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(links::class)]
final class links_test extends \advanced_testcase {
    /**
     * Leave no router built under this test's setting behind.
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Declare the site's router configured or not.
     *
     * The router memoises its base path when it is built, so the container is
     * emptied for the next reader to build it against the new setting.
     *
     * @param bool $configured Whether the router is configured.
     * @return void
     */
    private function set_router(bool $configured): void {
        global $CFG;
        $CFG->routerconfigured = $configured;
        \core\di::reset_container();
    }

    /**
     * This theme's shortlink rows of a course, oldest first.
     *
     * @param int $courseid The course id.
     * @return array The rows.
     */
    private function rows(int $courseid): array {
        global $DB;
        return array_values($DB->get_records('shortlink', [
            'component' => hotsitefields::COMPONENT,
            'identifier' => (string) $courseid,
        ], 'id ASC'));
    }

    /**
     * Without the router the page's canonical address is the script's.
     */
    public function test_the_page_address_is_the_script_while_the_router_is_off(): void {
        $this->resetAfterTest();
        $this->set_router(false);
        $expected = (new \core\url('/theme/dimensions/hotsite.php', ['id' => 42]))->out(false);
        $this->assertSame($expected, links::page(42)->out(false));
        $this->assertSame($expected, links::legacy(42)->out(false));
    }

    /**
     * With the router the page's canonical address is the route, spelled without r.php.
     */
    public function test_the_page_address_is_the_route_while_the_router_is_on(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->set_router(true);
        $this->assertSame($CFG->wwwroot . '/theme_dimensions/hotsite/42', links::page(42)->out(false));
        $this->assertStringContainsString('hotsite.php', links::legacy(42)->out(false));
    }

    /**
     * Without the router the public address is the page's own, and nothing is minted.
     */
    public function test_share_falls_back_to_the_page_and_mints_nothing_while_the_router_is_off(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_router(false);
        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse(links::routing_enabled());
        $this->assertSame(links::page((int) $course->id)->out(false), links::share((int) $course->id)->out(false));
        $this->assertSame(0, $DB->count_records('shortlink'));
    }

    /**
     * With the router one public code is minted, under core's own columns, and every later call reuses it.
     */
    public function test_share_mints_one_public_code_and_reuses_it(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->set_router(true);
        $course = $this->getDataGenerator()->create_course();

        $this->assertTrue(links::routing_enabled());
        $url = links::share((int) $course->id);

        $rows = $this->rows((int) $course->id);
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertTrue(shortcode::is_acceptable($row->shortcode), 'A code that can be read out loud.');
        $this->assertSame(0, (int) $row->userid, 'A public code.');
        $this->assertSame(hotsitefields::COMPONENT, $row->component);
        $this->assertSame(links::LINKTYPE, $row->linktype);
        $this->assertSame((string) $course->id, $row->identifier);

        $this->assertSame($CFG->wwwroot . '/p/' . $row->shortcode, $url->out(false));
        $this->assertStringNotContainsString('r.php', $url->out(false));
        $this->assertSame($row->shortcode, links::existing_code((int) $course->id));

        $this->assertSame($url->out(false), links::share((int) $course->id)->out(false));
        $this->assertCount(1, $this->rows((int) $course->id), 'The second call minted nothing.');
    }

    /**
     * Two rows left by a race spell one address: the oldest.
     */
    public function test_share_keeps_the_oldest_of_two_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_router(true);
        $course = $this->getDataGenerator()->create_course();
        foreach (['batepo', 'tamebo'] as $code) {
            $DB->insert_record('shortlink', (object) [
                'shortcode' => $code,
                'userid' => 0,
                'component' => hotsitefields::COMPONENT,
                'linktype' => links::LINKTYPE,
                'identifier' => (string) $course->id,
            ]);
        }

        $this->assertSame('batepo', links::existing_code((int) $course->id));
        $this->assertStringEndsWith('/p/batepo', links::share((int) $course->id)->out(false));
        $this->assertCount(2, $this->rows((int) $course->id), 'Nothing minted, nothing deleted.');
    }

    /**
     * A minted code resolves, through core's own manager and this theme's handler, to the page.
     */
    public function test_a_minted_code_resolves_through_core_to_the_page(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $course = $this->getDataGenerator()->create_course();
        links::share((int) $course->id);
        $code = links::existing_code((int) $course->id);
        $this->assertNotNull($code);

        $resolved = \core\di::get(\core\shortlink::class)->fetch_url_for_shortcode(false, $code);
        $this->assertSame(links::page((int) $course->id)->out(false), $resolved->out(false));
    }

    /**
     * Forgetting a course removes its rows and no other course's.
     */
    public function test_forget_removes_only_the_courses_rows(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $kept = $this->getDataGenerator()->create_course();
        $gone = $this->getDataGenerator()->create_course();
        links::share((int) $kept->id);
        links::share((int) $gone->id);

        links::forget((int) $gone->id);

        $this->assertCount(0, $this->rows((int) $gone->id));
        $this->assertCount(1, $this->rows((int) $kept->id));
        $this->assertNull(links::existing_code((int) $gone->id));
    }

    /**
     * The guest entry's page is the hotsite's page asking for the entry, on either side of the router.
     */
    public function test_the_guest_page_is_the_page_asking_for_the_entry(): void {
        global $CFG;
        $this->resetAfterTest();

        $this->set_router(false);
        $this->assertSame(
            (new \core\url('/theme/dimensions/hotsite.php', ['id' => 42, 'guest' => 1]))->out(false),
            links::guest_page(42)->out(false)
        );

        $this->set_router(true);
        $this->assertSame($CFG->wwwroot . '/theme_dimensions/hotsite/42?guest=1', links::guest_page(42)->out(false));
        $this->assertNull(links::page(42)->param(links::GUEST_PARAM), 'The page itself does not ask.');
    }

    /**
     * Without the router the guest entry's public address is its page, and nothing is minted.
     */
    public function test_guest_share_falls_back_to_the_guest_page_while_the_router_is_off(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_router(false);
        $course = $this->getDataGenerator()->create_course();

        $this->assertSame(links::guest_page((int) $course->id)->out(false), links::guest_share((int) $course->id)->out(false));
        $this->assertSame(0, $DB->count_records('shortlink'));
    }

    /**
     * With the router the guest entry mints a code of its own type, distinct from the hotsite's, and reuses it.
     */
    public function test_guest_share_mints_a_code_of_its_own_and_reuses_it(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->set_router(true);
        $course = $this->getDataGenerator()->create_course();
        $hotsite = links::share((int) $course->id);
        $this->assertNull(links::existing_code((int) $course->id, links::LINKTYPE_GUEST), 'share() minted only its own.');

        $guest = links::guest_share((int) $course->id);

        $rows = $this->rows((int) $course->id);
        $this->assertCount(2, $rows);
        $this->assertSame([links::LINKTYPE, links::LINKTYPE_GUEST], array_column($rows, 'linktype'));
        $code = links::existing_code((int) $course->id, links::LINKTYPE_GUEST);
        $this->assertTrue(shortcode::is_acceptable($code));
        $this->assertSame(0, (int) $rows[1]->userid, 'A public code.');
        $this->assertNotSame(links::existing_code((int) $course->id), $code);
        $this->assertSame($CFG->wwwroot . '/p/' . $code, $guest->out(false));
        $this->assertNotSame($hotsite->out(false), $guest->out(false));

        $this->assertSame($guest->out(false), links::guest_share((int) $course->id)->out(false));
        $this->assertCount(2, $this->rows((int) $course->id), 'The second call minted nothing.');
    }

    /**
     * The guest entry's code resolves, through core's own manager, to the page asking for the entry.
     */
    public function test_a_minted_guest_code_resolves_through_core_to_the_guest_page(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $course = $this->getDataGenerator()->create_course();
        links::guest_share((int) $course->id);
        $code = links::existing_code((int) $course->id, links::LINKTYPE_GUEST);

        $resolved = \core\di::get(\core\shortlink::class)->fetch_url_for_shortcode(false, $code);
        $this->assertSame(links::guest_page((int) $course->id)->out(false), $resolved->out(false));
    }

    /**
     * Forgetting a course removes its codes of both types, and no other course's.
     */
    public function test_forget_removes_both_types_and_only_the_courses_rows(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $kept = $this->getDataGenerator()->create_course();
        $gone = $this->getDataGenerator()->create_course();
        foreach ([$kept, $gone] as $course) {
            links::share((int) $course->id);
            links::guest_share((int) $course->id);
        }
        $this->assertCount(2, $this->rows((int) $gone->id));

        links::forget((int) $gone->id);

        $this->assertCount(0, $this->rows((int) $gone->id));
        $this->assertNull(links::existing_code((int) $gone->id, links::LINKTYPE_GUEST));
        $this->assertCount(2, $this->rows((int) $kept->id), 'The other course keeps both of its codes.');
    }

    /**
     * Without the router a category's public page is the script, and extra parameters are kept.
     */
    public function test_the_category_page_address_is_the_script_while_the_router_is_off(): void {
        $this->resetAfterTest();
        $this->set_router(false);

        $expected = (new \core\url('/theme/dimensions/category.php', ['id' => 7]))->out(false);
        $this->assertSame($expected, links::category_legacy(7)->out(false));
        $this->assertSame($expected, links::category_page(7)->out(false));

        $carried = links::category_page(7, ['q' => 'x'])->out(false);
        $this->assertStringContainsString('id=7', $carried);
        $this->assertStringContainsString('q=x', $carried);
        $this->assertSame(links::category_legacy(7, ['q' => 'x'])->out(false), $carried);
    }

    /**
     * With the router it is the route, spelled without r.php, and extra parameters are kept there too.
     */
    public function test_the_category_page_address_is_the_route_while_the_router_is_on(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->set_router(true);

        $this->assertSame(
            $CFG->wwwroot . '/theme_dimensions/category/7',
            links::category_page(7)->out(false)
        );
        $this->assertSame(
            $CFG->wwwroot . '/theme_dimensions/category/7?q=x',
            links::category_page(7, ['q' => 'x'])->out(false)
        );
        // The script keeps answering whatever the router does; only the canonical address moves.
        $this->assertStringContainsString('category.php', links::category_legacy(7)->out(false));
    }

    /**
     * One page of the showcase has two addresses, and showcase() picks by the shell, not by the caller.
     */
    public function test_showcase_picks_the_shell_it_is_drawing_for(): void {
        $this->resetAfterTest();
        $this->set_router(false);

        $loggedin = links::showcase(7, ['q' => 'x'], false)->out(false);
        $this->assertStringContainsString('/course/index.php', $loggedin);
        $this->assertStringContainsString('categoryid=7', $loggedin);
        $this->assertStringContainsString('q=x', $loggedin);

        $anonymous = links::showcase(7, ['q' => 'x'], true)->out(false);
        $this->assertSame(links::category_page(7, ['q' => 'x'])->out(false), $anonymous);
        $this->assertStringNotContainsString('/course/index.php', $anonymous);
    }

    /**
     * A GET form of the showcase has three shapes, and the id never sits in the action.
     *
     * A GET submission replaces the action's query string with the form's own fields, so the
     * category has to travel as a hidden field - or, on the route, as a path segment, which is
     * the one shape whose form carries nothing of its own.
     */
    public function test_the_showcase_form_keeps_the_category_out_of_the_action(): void {
        $this->resetAfterTest();
        $this->set_router(false);

        $loggedin = links::showcase_form(7, false);
        $this->assertStringEndsWith('/course/index.php', $loggedin['action']);
        $this->assertStringNotContainsString('?', $loggedin['action'], 'A GET submission would drop it anyway.');
        $this->assertSame([['name' => 'categoryid', 'value' => 7]], $loggedin['hidden']);

        $script = links::showcase_form(7, true);
        $this->assertStringEndsWith('/theme/dimensions/category.php', $script['action']);
        $this->assertStringNotContainsString('?', $script['action']);
        $this->assertSame([['name' => 'id', 'value' => 7]], $script['hidden']);

        $this->set_router(true);
        $route = links::showcase_form(7, true);
        $this->assertSame(links::category_page(7)->out(false), $route['action']);
        $this->assertStringEndsWith('/theme_dimensions/category/7', $route['action']);
        $this->assertStringNotContainsString('?', $route['action'], 'The id is in the path, not the query.');
        $this->assertSame([], $route['hidden'], 'So the form carries nothing of its own.');
    }
}
