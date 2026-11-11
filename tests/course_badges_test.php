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
 * Theme Dimensions - Tests for the course crests callback other plugins' cards ask
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions;

use core_course\customfield\course_handler;
use local_unlistedcourses\access;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversFunction;
use theme_dimensions\local\landing\publicfiles;
use theme_dimensions\local\landingfields;

/**
 * Tests for theme_dimensions_course_badges(), the answer block_compass's course_badges hook carries.
 *
 * Every rule has its control in the same test: four badges come back as three beside a course holding one, a course
 * whose model was cleared returns none beside the same files under a model, a course gets crests exactly when the
 * badge route would serve its badge to the reader, and the cost of the call is the cost of the values it reads, with
 * no statement of its own per course.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('theme_dimensions_course_badges')]
final class course_badges_test extends \advanced_testcase {
    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Provision the landing page fields and load the theme's library, as the hook callback does.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * Forget local_unlistedcourses' memos, which are keyed by user and course id.
     *
     * @return void
     */
    protected function tearDown(): void {
        access::reset_caches();
        parent::tearDown();
    }

    /**
     * A course with a landing page model (or none) and badge files in some of its four picture fields.
     *
     * @param int $model The landing page model, 0 for none.
     * @param string[] $files File names, one per picture field, in grid order.
     * @param array $record Extra course fields.
     * @return int The course id.
     */
    private function course_with_badges(int $model, array $files, array $record = []): int {
        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $generator = self::getDataGenerator();
        /** @var \core_customfield_generator $customfields */
        $customfields = $generator->get_plugin_generator('core_customfield');
        $course = $generator->create_course($record);
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => $model,
        ]);
        foreach (array_values($files) as $i => $filename) {
            $data = $customfields->add_instance_data(
                landingfields::get_field(landingfields::CREST_SHORTNAMES[$i]),
                (int) $course->id,
                1
            );
            get_file_storage()->create_file_from_string([
                'contextid' => \core\context\course::instance((int) $course->id)->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $filename,
            ], base64_decode(self::PNG));
        }
        return (int) $course->id;
    }

    /**
     * At most three crests, in grid order, each named through the theme's own badge route with its file's words.
     *
     * The same answer through component_callback(): the function keeps its frankenstyle name, so a caller of that door
     * reads what the hook carries.
     */
    public function test_a_course_gets_its_first_three_crests_in_grid_order(): void {
        $four = $this->course_with_badges(landingfields::MODEL_COURSE, ['crest-one.png', 'dois.png', 'tres_c.png', 'quatro.png']);
        $one = $this->course_with_badges(landingfields::MODEL_EVENT, ['so-um.png']);

        $badges = theme_dimensions_course_badges([$four, $one]);

        $this->assertCount(3, $badges[$four], 'Four uploaded, three drawn.');
        $this->assertSame(['crest one', 'dois', 'tres c'], array_column($badges[$four], 'alt'), 'In grid order, the fourth cut.');
        foreach ($badges[$four] as $badge) {
            $this->assertStringContainsString('/theme_dimensions/badge/' . $four . '/', $badge['url']);
        }
        $this->assertCount(1, $badges[$one], 'Control: a course holding one gets one.');
        $this->assertSame('so um', $badges[$one][0]['alt']);
        $this->assertSame(
            $badges,
            component_callback('theme_dimensions', 'course_badges', [[$four, $one]], []),
            'component_callback() answers the same.'
        );
    }

    /**
     * A course with no landing page model gets no crest, even with badge files left from a model that was cleared.
     *
     * The theme's route refuses a badge of a course with no model, so an address the callback named would be one nobody
     * may open. The same files under a model are the control; a course with a model and no badge, and an id with no
     * course, both answer an empty list rather than nothing.
     */
    public function test_a_course_with_no_model_gets_no_crest(): void {
        $cleared = $this->course_with_badges(0, ['crest.png']);
        $modelled = $this->course_with_badges(landingfields::MODEL_COURSE, ['crest.png']);
        $bare = $this->course_with_badges(landingfields::MODEL_COURSE, []);
        $this->assertCount(1, landingfields::badges($cleared), 'Precondition: the file is there.');

        $badges = theme_dimensions_course_badges([$cleared, $modelled, $bare, 999999, 0, -3]);

        $this->assertSame([], $badges[$cleared], 'No model, no crest.');
        $this->assertCount(1, $badges[$modelled], 'Control: the same file under a model.');
        $this->assertSame([], $badges[$bare], 'A model and no badge.');
        $this->assertSame([], $badges[999999], 'An id with no course.');
        $this->assertSame([$cleared, $modelled, $bare, 999999], array_keys($badges), 'Every positive id, in the order given.');
        $this->assertSame([], theme_dimensions_course_badges([]));
    }

    /**
     * The callback's own statements are a fixed few whatever the number of courses; the rest is what the values read.
     *
     * Courses with no crest read the same at two and at eight. Courses with crests pay one statement for every badge
     * file of the call inside landingfields::values_many(); the callback's own reads are measured against
     * values_many() over the same courses with their contexts preloaded, as the callback preloads them.
     */
    public function test_the_callback_reads_nothing_per_course_of_its_own(): void {
        global $DB;

        $bare = [];
        $crested = [];
        for ($i = 0; $i < 8; $i++) {
            $bare[] = $this->course_with_badges(landingfields::MODEL_COURSE, []);
            $crested[] = $this->course_with_badges(landingfields::MODEL_COURSE, ['crest.png']);
        }
        $callback = function (array $ids) use ($DB): int {
            \core\context_helper::reset_caches();
            access::reset_caches();
            $before = (int) $DB->perf_get_reads();
            theme_dimensions_course_badges($ids);
            return (int) $DB->perf_get_reads() - $before;
        };
        $valuesonly = function (array $ids) use ($DB): int {
            \core\context_helper::reset_caches();
            foreach ($ids as $id) {
                // Warm outside the meter: the callback brings the contexts with its own statement.
                \core\context\course::instance($id);
            }
            $before = (int) $DB->perf_get_reads();
            landingfields::values_many($ids, false);
            return (int) $DB->perf_get_reads() - $before;
        };
        // Warm the field definitions, which a second request finds warm.
        theme_dimensions_course_badges(array_merge($bare, $crested));

        $two = $callback(array_slice($bare, 0, 2));
        $eight = $callback($bare);
        $this->assertSame($two, $eight, "{$two} reads for two courses with no crest against {$eight} for eight.");

        $this->assertSame(
            $valuesonly(array_slice($crested, 0, 2)),
            $valuesonly($crested),
            'The badge files of every course are read at once, whatever the number of courses.'
        );
        $owntwo = $callback(array_slice($crested, 0, 2)) - $valuesonly(array_slice($crested, 0, 2));
        $owneight = $callback($crested) - $valuesonly($crested);
        $this->assertSame($owntwo, $owneight, 'The callback\'s own reads beyond the values do not grow with the courses.');
        $this->assertLessThanOrEqual(3, $owneight, 'The courses with their contexts, the course states, the category\'s context.');
    }

    /**
     * A course gets crests exactly when the badge route would serve its badge to the reader, and a visitor gets none.
     *
     * A crest's address and its alternative text name the file, so the callback answers only for courses the route
     * serves: each course below is asked of publicfiles::resolve() itself with its own badge, and the two answers must
     * agree. The student may see the listed course and the unlisted one they are enrolled in; not the hidden ones,
     * enrolled or not, the unlisted one they have no tie to, nor one in a category whose listing they may not view -
     * beside which the course of that same category they are enrolled in is the control.
     */
    public function test_a_course_gets_crests_exactly_when_the_route_serves_its_badge(): void {
        global $CFG;

        $generator = self::getDataGenerator();
        $closed = $generator->create_category();
        assign_capability(
            'moodle/category:viewcourselist',
            CAP_PROHIBIT,
            (int) $CFG->defaultuserroleid,
            \core\context\coursecat::instance((int) $closed->id)->id,
            true
        );
        $courses = [
            'listed' => $this->course_with_badges(landingfields::MODEL_COURSE, ['a.png']),
            'hidden' => $this->course_with_badges(landingfields::MODEL_COURSE, ['b.png'], ['visible' => 0]),
            'hiddenmine' => $this->course_with_badges(landingfields::MODEL_COURSE, ['g.png'], ['visible' => 0]),
            'unlisted' => $this->course_with_badges(landingfields::MODEL_COURSE, ['c.png']),
            'unlistedmine' => $this->course_with_badges(landingfields::MODEL_COURSE, ['d.png']),
            'unviewable' => $this->course_with_badges(landingfields::MODEL_COURSE, ['e.png'], ['category' => $closed->id]),
            'unviewablemine' => $this->course_with_badges(landingfields::MODEL_COURSE, ['f.png'], ['category' => $closed->id]),
        ];
        discoverability::set_state($courses['unlisted'], discoverability::STATE_UNLISTED);
        discoverability::set_state($courses['unlistedmine'], discoverability::STATE_UNLISTED);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $courses['unlistedmine']);
        $generator->enrol_user($student->id, $courses['hiddenmine']);
        $generator->enrol_user($student->id, $courses['unviewablemine']);

        $this->setUser($student);
        access::reset_caches();
        $badges = theme_dimensions_course_badges(array_values($courses));
        foreach ($courses as $label => $courseid) {
            $file = landingfields::badges($courseid)[0];
            access::reset_caches();
            $served = publicfiles::resolve(
                \core\context\course::instance($courseid),
                publicfiles::AREA_BADGE,
                [$courseid, $file->get_contenthash(), $file->get_filename()]
            ) !== null;
            $this->assertSame($served, $badges[$courseid] !== [], "{$label}: the callback and the route agree.");
        }
        $kept = array_keys(array_filter($badges));
        $this->assertEqualsCanonicalizing(
            [$courses['listed'], $courses['unlistedmine'], $courses['unviewablemine']],
            $kept,
            'The three the student may see, and none of the other four.'
        );

        /* A visitor, on a site that does not force the login: core lets them view the listed course's
           listing (the precondition), so the login test is the one refusing them here. */
        set_config('forcelogin', 0);
        $this->setUser(null);
        access::reset_caches();
        $this->assertTrue(\core_course_category::can_view_course_info(get_course($courses['listed'])), 'Precondition.');
        $visitor = theme_dimensions_course_badges(array_values($courses));
        $this->assertSame([], array_filter($visitor), 'A visitor gets none.');
    }
}
