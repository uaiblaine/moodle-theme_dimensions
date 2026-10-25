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
 * Theme Dimensions - Tests for the navbar's controls that move into the user menu on phones
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Which user menu entries a page gets: one per control the navbar really carries, in a fixed order.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(phonenav::class)]
final class phonenav_test extends \advanced_testcase {
    /** @var string message_popup's messaging region, as its template writes it. */
    private const MESSAGES = '<div class="popover-region collapsed" data-region="popover-region-messages"><a href="#"></a></div>';

    /** @var string local_mail's navbar region, as its render_navbar_output() writes it. */
    private const MAIL = '<div class="popover-region" id="local-mail-navbar"><a href="#"></a></div>';

    /**
     * Nothing of a previous test's focus mode.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        focusmode::reset_caches();
    }

    /**
     * Drop the focus mode caches: they are process-wide.
     *
     * @return void
     */
    protected function tearDown(): void {
        focusmode::reset_caches();
        parent::tearDown();
    }

    /**
     * A course page, as a reader opens it.
     *
     * @param \stdClass $course The course.
     * @return \moodle_page
     */
    private function course_page(\stdClass $course): \moodle_page {
        $page = new \moodle_page();
        $page->set_context(\core\context\course::instance((int) $course->id));
        $page->set_course($course);
        $page->set_url('/course/view.php', ['id' => $course->id]);
        $page->set_pagelayout('course');

        return $page;
    }

    /**
     * The keys of the entries, in order.
     *
     * @param array $entries phonenav::entries()' answer.
     * @return string[]
     */
    private function keys(array $entries): array {
        return array_column($entries, 'key');
    }

    /**
     * A navbar that carries none of the controls gets no entry: a student on a course page, nothing printed.
     *
     * @return void
     */
    public function test_nothing_printed_and_nothing_to_edit_gives_no_entry(): void {
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        $this->assertSame([], phonenav::entries('', $this->course_page($course)));
    }

    /**
     * The messaging drawer has its entry only where message_popup printed its region, and the entry opens the messages
     * page without the module and is counted.
     *
     * @return void
     */
    public function test_the_messages_entry_follows_the_printed_region(): void {
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));
        $page = $this->course_page($course);

        $this->assertSame([], phonenav::entries('<div data-region="popover-region-notifications"></div>', $page));

        $entries = phonenav::entries(self::MESSAGES, $page);
        $this->assertSame([phonenav::MESSAGES], $this->keys($entries));
        $this->assertSame((new \moodle_url('/message/index.php'))->out(false), $entries[0]['url']);
        $this->assertFalse($entries[0]['isaction']);
        $this->assertTrue($entries[0]['counted']);
        $this->assertSame(get_string('messages', 'message'), $entries[0]['label']);
    }

    /**
     * Mail has its entry only where local_mail printed its region, and opens the inbox.
     *
     * @return void
     */
    public function test_the_mail_entry_follows_the_printed_region(): void {
        if (\core_component::get_component_directory('local_mail') === null) {
            $this->markTestSkipped('local_mail is not installed: its region is never printed on such a site.');
        }
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));

        $entries = phonenav::entries(self::MAIL, $this->course_page($course));

        $this->assertSame([phonenav::MAIL], $this->keys($entries));
        $this->assertSame((new \moodle_url('/local/mail/view.php', ['t' => 'inbox']))->out(false), $entries[0]['url']);
        $this->assertTrue($entries[0]['counted']);
    }

    /**
     * Edit mode has its entry for a reader core lets edit the page, an action named by what pressing it will do; a
     * student gets none, as core draws no switch for them.
     *
     * @return void
     */
    public function test_the_edit_entry_is_cores_predicate_and_names_the_next_state(): void {
        global $USER;
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'student'));
        $this->assertSame([], phonenav::entries('', $this->course_page($course)), 'Control: a student edits nothing.');

        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $page = $this->course_page($course);
        $this->assertTrue($page->user_allowed_editing(), 'Precondition: core lets the teacher edit.');

        $entries = phonenav::entries('', $page);
        $this->assertSame([phonenav::EDIT], $this->keys($entries));
        $this->assertTrue($entries[0]['isaction']);
        $this->assertSame('#', $entries[0]['url']);
        $this->assertFalse($entries[0]['counted']);
        $this->assertSame(get_string('turneditingon'), $entries[0]['label']);

        $USER->editing = 1;
        $this->assertSame(get_string('turneditingoff'), phonenav::entries('', $page)[0]['label']);
    }

    /**
     * The entries keep one order whatever the output's: messages, mail, focus mode, edit mode.
     *
     * @return void
     */
    public function test_the_entries_keep_their_order(): void {
        $course = self::getDataGenerator()->create_course();
        $this->setUser(self::getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $entries = phonenav::entries(self::MAIL . self::MESSAGES, $this->course_page($course));
        $expected = \core_component::get_component_directory('local_mail') === null
            ? [phonenav::MESSAGES, phonenav::EDIT]
            : [phonenav::MESSAGES, phonenav::MAIL, phonenav::EDIT];

        $this->assertSame($expected, $this->keys($entries));
    }
}
