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
 * Theme Dimensions - The one write of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\event\wishlist_updated;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\wishlist;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests for the star: core's own favourite, written through the showcase's gates.
 *
 * The row this writes is byte-identical to the one core's star writes, so a
 * wishlisted course becomes a starred course the moment the person enrols,
 * and the assertions read core's service back rather than the theme's own
 * return value. The idempotence tests exist because core's
 * delete_favourite() throws when the row is not there: an unstar of something
 * that was never starred would surface to the reader as a failed request over
 * a state they already had.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(toggle_wishlist::class)]
#[CoversClass(wishlist::class)]
#[CoversClass(wishlist_updated::class)]
final class toggle_wishlist_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_toggle_wishlist';

    /**
     * Call the function the way the client does.
     *
     * @param int $courseid The course.
     * @param bool $state What the star was flipped to.
     * @return array The framework's response.
     */
    private function call(int $courseid, bool $state): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(
            self::FUNCTION,
            ['courseid' => $courseid, 'state' => $state],
            false
        );
    }

    /**
     * Call it and assert it answered.
     *
     * @param int $courseid The course.
     * @param bool $state What the star was flipped to.
     * @return array The cleaned payload.
     */
    private function data(int $courseid, bool $state): array {
        $response = $this->call($courseid, $state);
        $this->assertFalse(
            $response['error'],
            'The function refused: ' . ($response['exception']->message ?? '')
        );
        return $response['data'];
    }

    /**
     * Whether core holds a favourite of this course for this user.
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @return bool
     */
    private function starred(int $courseid, int $userid): bool {
        return \core_favourites\service_factory::get_service_for_user_context(
            \core\context\user::instance($userid)
        )->favourite_exists('core_course', 'courses', $courseid, \core\context\course::instance($courseid));
    }

    /**
     * The refusal matrix: six ways to miss, one answer, and an admitted course as the control.
     */
    public function test_every_course_it_withholds_answers_alike(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $admitted = $generator->create_course(['category' => $category->id]);
        $hidden = $generator->create_course(['category' => $category->id, 'visible' => 0]);
        $unlisted = $generator->create_course(['category' => $category->id]);
        $this->set_course_state((int) $unlisted->id, discoverability::STATE_UNLISTED);
        $withheldcategory = $generator->create_category();
        $incategory = $generator->create_course(['category' => $withheldcategory->id, 'visible' => 1]);
        $this->set_category_state((int) $withheldcategory->id, category_discoverability::STATE_UNLISTED);
        $missing = (int) $incategory->id + 1000;

        $viewer = $generator->create_user();
        $this->become($viewer);

        $refusals = [
            'a course id nothing holds' => $this->call($missing, true),
            'a course core hides' => $this->call((int) $hidden->id, true),
            'a course the plugin withholds' => $this->call((int) $unlisted->id, true),
            'a course under a withheld category' => $this->call((int) $incategory->id, true),
            // The site course is on no showcase and passes both gates unaided; see guard::course().
            'the site course' => $this->call(SITEID, true),
        ];
        $this->setGuestUser();
        self::forget();
        $refusals['the guest account'] = $this->call((int) $admitted->id, true);

        $first = reset($refusals)['exception'];
        foreach ($refusals as $case => $response) {
            $this->assertTrue($response['error'], $case);
            $this->assertSame(guard::ERROR_COURSE, $response['exception']->errorcode, $case);
            $this->assertSame($first->message, $response['exception']->message, $case);
        }

        // The control: the same call by the same viewer on a course they may discover writes.
        $this->become($viewer);
        $data = $this->data((int) $admitted->id, true);
        $this->assertTrue($data['favourite']);
        $this->assertSame((int) $admitted->id, $data['courseid']);
        $this->assertTrue($this->starred((int) $admitted->id, (int) $viewer->id));
    }

    /**
     * A course the viewer may discover but is not enrolled in is exactly what the star is for.
     */
    public function test_a_course_the_viewer_is_not_enrolled_in_can_be_starred(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);

        // The precondition: no enrolment of any kind, which is the ordinary case on a category page.
        $this->assertFalse(is_enrolled(\core\context\course::instance((int) $course->id), $viewer));

        $this->assertTrue($this->data((int) $course->id, true)['favourite']);
        $this->assertTrue($this->starred((int) $course->id, (int) $viewer->id));
    }

    /**
     * Starring twice is one row and unstarring what was never starred is a no-op.
     */
    public function test_the_write_is_idempotent_in_both_directions(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);
        $courseid = (int) $course->id;

        $this->data($courseid, true);
        $this->data($courseid, true);
        $this->assertSame(
            1,
            $DB->count_records('favourite', ['userid' => $viewer->id, 'itemtype' => 'courses', 'itemid' => $courseid]),
            'Two stars are one row.'
        );

        $this->assertFalse($this->data($courseid, false)['favourite']);
        $this->assertFalse($this->starred($courseid, (int) $viewer->id));

        // Core's delete_favourite() throws on a row that is not there; this must not.
        $this->assertFalse($this->data($courseid, false)['favourite']);
        $this->assertSame(
            0,
            $DB->count_records('favourite', ['userid' => $viewer->id, 'itemtype' => 'courses', 'itemid' => $courseid])
        );
    }

    /**
     * The theme's events of a sink, newest last.
     *
     * @param \core\event\base[] $events Everything the sink caught.
     * @return wishlist_updated[]
     */
    private function mine(array $events): array {
        return array_values(array_filter($events, static function ($event): bool {
            return $event instanceof wishlist_updated;
        }));
    }

    /**
     * A real change fires the theme's event, with the object and the new state on it; a repeat fires none.
     *
     * One log line per real change: a log line means a favourite moved, not
     * that somebody asked. A client that flips a star twice, a second tab, a
     * reload replaying a POST leaves one line, not three.
     *
     * The repeat is asserted with the same sink still open and a count taken
     * before and after, so a test that simply never reached the second call
     * would fail: the first half proves the sink catches this event at all.
     */
    public function test_the_write_fires_the_event_once_per_real_change(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);

        $sink = $this->redirectEvents();
        $this->data((int) $course->id, true);
        $mine = $this->mine($sink->get_events());
        $this->assertCount(1, $mine, 'The write that moved a row logged once.');

        // The same state asked for again: the answer is the same, the log does not move.
        $this->assertTrue($this->data((int) $course->id, true)['favourite']);
        $this->assertCount(1, $this->mine($sink->get_events()), 'A no-op call logs nothing.');

        // The other direction, twice: one line for the deletion, none for the repeat.
        $this->assertFalse($this->data((int) $course->id, false)['favourite']);
        $this->assertCount(2, $this->mine($sink->get_events()), 'The unstar that moved a row logged once.');
        $this->assertFalse($this->data((int) $course->id, false)['favourite']);
        $unstarred = $this->mine($sink->get_events());
        $this->assertCount(2, $unstarred, 'A no-op unstar logs nothing.');
        $this->assertFalse($unstarred[1]->other['state'], 'The second line carries the state it wrote.');
        $sink->close();

        $event = $mine[0];
        $this->assertSame((int) $course->id, (int) $event->objectid);
        $this->assertSame('course', $event->objecttable);
        $this->assertSame(CONTEXT_COURSE, $event->contextlevel);
        $this->assertSame((int) $course->id, (int) $event->contextinstanceid);
        $this->assertTrue($event->other['state']);
        $this->assertNotEmpty($event->get_description());
        $this->assertSame(
            get_string('event_wishlist_updated', 'theme_dimensions'),
            wishlist_updated::get_name()
        );
    }

    /**
     * The event is read back out of a real logstore: the stored row names the course, the viewer and the context.
     *
     * The test above catches the event with redirectEvents(), which hands back the
     * object the theme built and asks it about itself - so it can only ever agree
     * with the builder. Everything an administrator actually reads is written by
     * the store, out of get_data(), into columns whose names are core's: objectid
     * against objecttable, contextlevel against contextinstanceid. A course id put
     * in the wrong column, or a context's own id put where the object's belongs,
     * passes the sink and is still the wrong row in the report.
     *
     * The store is enabled and its buffer dropped to zero, because the default
     * flushes at fifty entries or at the end of the request and a test that
     * reads the table in between finds nothing. get_log_manager(true) comes
     * after the settings because a store reads part of its configuration in
     * its constructor. The first count is the non-vacuity control: a store that
     * never wrote fails there rather than passing the no-op half by finding
     * nothing twice.
     *
     * Without preventResetByRollback() this test reads an empty table however
     * right the code is: PHPUnit resets by keeping one transaction open for the
     * whole test, and core's event manager holds an event back from external
     * observers (the log store's is one) until that transaction commits. Core's
     * own logstore_standard test opens with the same line for the same reason.
     */
    public function test_the_event_reaches_a_real_logstore_naming_the_course_it_moved(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $viewer = $generator->create_user();
        $this->become($viewer);
        $courseid = (int) $course->id;

        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $this->assertTrue($this->data($courseid, true)['favourite']);

        $stored = $DB->get_records('logstore_standard_log', ['eventname' => '\\' . wishlist_updated::class], 'id ASC');
        $this->assertCount(1, $stored, 'The star that moved a row reached the store.');
        $row = reset($stored);
        $this->assertSame($courseid, (int) $row->objectid, 'The object of the line is the COURSE that was starred.');
        $this->assertSame('course', $row->objecttable);
        $this->assertSame(CONTEXT_COURSE, (int) $row->contextlevel);
        $this->assertSame($courseid, (int) $row->contextinstanceid);
        $this->assertSame((int) $viewer->id, (int) $row->userid, 'The line names the person who starred it.');

        // The no-op rule off the stored rows rather than off a sink: asking for the state already held writes nothing.
        $this->assertTrue($this->data($courseid, true)['favourite']);
        $this->assertCount(
            1,
            $DB->get_records('logstore_standard_log', ['eventname' => '\\' . wishlist_updated::class]),
            'A no-op call leaves the log where it was.'
        );
    }

    /**
     * The theme's PHP and AMD modules never name core's own favourite web service.
     *
     * core_course_set_favourite_courses instantiates a course context and checks
     * nothing else: no visibility question, no discoverability question. Reaching
     * it from this feature's client would be a way around every gate in this file.
     * The sweep reads lib.php, classes/, amd/src and amd/build; the ES star module
     * is held by {@see \theme_dimensions\local\category\listing_star_source_test}.
     *
     * amd/build is swept as well as amd/src because Moodle serves the build, so a
     * source that was cleaned and never rebuilt still ships the old call.
     */
    public function test_core_s_own_favourite_service_is_named_nowhere_in_the_theme(): void {
        $root = dirname(__DIR__, 2);
        $haystacks = [$root . '/lib.php'];
        foreach (['/classes', '/amd/src', '/amd/build'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . $directory));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php', 'js'], true)) {
                    $haystacks[] = $file->getPathname();
                }
            }
        }
        $this->assertGreaterThan(50, count($haystacks), 'The sweep must actually have files to read.');

        $offenders = [];
        foreach ($haystacks as $path) {
            if (str_contains((string) file_get_contents($path), 'core_course_set_favourite_courses')) {
                $offenders[] = substr($path, strlen($root));
            }
        }
        $this->assertSame([], $offenders, 'The theme writes favourites through its own guarded function.');
    }
}
