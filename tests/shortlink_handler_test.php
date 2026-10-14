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
 * Theme Dimensions - Tests for the shortlink handler
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions;

use theme_dimensions\local\hotsite\links;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the shortlink handler.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shortlink_handler::class)]
final class shortlink_handler_test extends \advanced_testcase {
    /**
     * The handler answers the two link types this theme mints, and core finds it by name.
     */
    public function test_it_answers_the_theme_types_only(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $handler = \core\di::get(shortlink_handler::class);

        $this->assertInstanceOf(\core\shortlink_handler_interface::class, $handler);
        $this->assertSame([links::LINKTYPE, links::LINKTYPE_GUEST], $handler->get_valid_linktypes());
        $this->assertNull($handler->process_shortlink('view', (string) $course->id));
        $this->assertNotNull($handler->process_shortlink(links::LINKTYPE, (string) $course->id));
        $this->assertNotNull($handler->process_shortlink(links::LINKTYPE_GUEST, (string) $course->id));
    }

    /**
     * The guest entry's code is answered with the page asking for the guest entry, the hotsite's without it.
     */
    public function test_the_guest_type_answers_with_the_guest_entry(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $handler = new shortlink_handler();

        $guest = $handler->process_shortlink(links::LINKTYPE_GUEST, (string) $course->id);
        $this->assertSame(links::guest_page((int) $course->id)->out(false), $guest->out(false));
        $this->assertSame('1', $guest->param(links::GUEST_PARAM));

        $page = $handler->process_shortlink(links::LINKTYPE, (string) $course->id);
        $this->assertNull($page->param(links::GUEST_PARAM), 'The hotsite code is the plain page.');
    }

    /**
     * The guest entry's code is refused for what is not a course, like the hotsite's.
     */
    public function test_the_guest_type_refuses_what_is_not_a_course(): void {
        global $DB;
        $this->resetAfterTest();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 1000;

        $this->assertNull((new shortlink_handler())->process_shortlink(links::LINKTYPE_GUEST, (string) $missing));
        $this->assertNull((new shortlink_handler())->process_shortlink(links::LINKTYPE_GUEST, 'abc'));
    }

    /**
     * An existing course is answered with the page's canonical address.
     */
    public function test_it_answers_with_the_page_of_an_existing_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $url = (new shortlink_handler())->process_shortlink(links::LINKTYPE, (string) $course->id);
        $this->assertSame(links::page((int) $course->id)->out(false), $url->out(false));
    }

    /**
     * What is not a course is answered null, which the route turns into a not-found page.
     */
    public function test_it_refuses_what_is_not_a_course(): void {
        global $DB;
        $this->resetAfterTest();
        $handler = new shortlink_handler();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course}') + 1000;

        $this->assertNull($handler->process_shortlink(links::LINKTYPE, 'abc'));
        $this->assertNull($handler->process_shortlink(links::LINKTYPE, '0'));
        $this->assertNull($handler->process_shortlink(links::LINKTYPE, '-1'));
        $this->assertNull($handler->process_shortlink(links::LINKTYPE, '1.5'));
        $this->assertNull($handler->process_shortlink(links::LINKTYPE, (string) $missing));
    }
}
