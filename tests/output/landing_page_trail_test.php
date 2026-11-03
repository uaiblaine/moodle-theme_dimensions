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
 * Theme Dimensions - Tests for a trail's landing page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course\customfield\course_handler;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\landing\resolution;
use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * A trail's landing page: its courses in the hero and in order after the summary, and a ghost for a visitor.
 *
 * Every test needs mod_subcourse and skips without it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landing_page::class)]
final class landing_page_trail_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var \core_course_category The category every course of these tests sits in. */
    private \core_course_category $category;

    /**
     * Skip without the module; provision the fields as an administrator.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        self::forget();
        $this->category = self::getDataGenerator()->create_category(['name' => 'Gestão & Governo']);
    }

    /**
     * A course with landing fields stored.
     *
     * @param array $record Course record fields.
     * @param array $fields Field shortname => form value.
     * @return \stdClass The course.
     */
    private function course(array $record, array $fields = []): \stdClass {
        $course = self::getDataGenerator()->create_course($record + ['category' => $this->category->id]);
        if ($fields) {
            $data = ['id' => $course->id];
            foreach ($fields as $shortname => $value) {
                $data['customfield_' . $shortname] = $value;
            }
            course_handler::create()->instance_form_save((object) $data);
        }
        return $course;
    }

    /**
     * A trail course whose steps refer to the given courses, in that order.
     *
     * @param \stdClass[] $refs The referenced courses.
     * @param array $names The activities' names, by position; the referenced course's name otherwise.
     * @param int $model The page model, the trail's unless a test asks for another.
     * @return \stdClass The trail course.
     */
    private function trail(array $refs, array $names = [], int $model = landingfields::MODEL_TRAIL): \stdClass {
        // Hybrid, with an in-person share: a course of this kind gets the composition bar in its hero.
        $trail = $this->course(['fullname' => 'Trilha de Gestão', 'enablecompletion' => 1], [
            landingfields::SHORTNAME_MODEL => $model,
            landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_HYBRID,
            landingfields::SHORTNAME_WORKLOAD => 96,
            landingfields::SHORTNAME_INPERSONWORKLOAD => 24,
        ]);
        foreach (array_values($refs) as $i => $ref) {
            self::getDataGenerator()->create_module('subcourse', [
                'course' => $trail->id,
                'refcourse' => $ref->id,
                'name' => $names[$i] ?? $ref->fullname,
            ]);
        }
        return $trail;
    }

    /**
     * Give a course a course image, a real JPEG in its overview files.
     *
     * @param \stdClass $course The course.
     * @return void
     */
    private function cover(\stdClass $course): void {
        $image = imagecreatetruecolor(320, 180);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.jpg',
        ], $jpeg);
    }

    /**
     * The page's context for the current reader.
     *
     * @param \stdClass $course The course.
     * @param resolution $resolution The enrolment state.
     * @param bool $ispublic Whether the page is served to visitors.
     * @return \stdClass The context.
     */
    private function export(\stdClass $course, resolution $resolution, bool $ispublic): \stdClass {
        global $PAGE;
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $page = new landing_page(get_course($course->id), landingfields::values($course->id), $resolution, [], $ispublic);
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * A visitor of a public trail: the public course in full, the listed one as a ghost that names nothing.
     *
     * The hero says the courses drawn and their period, read from the full cards only; the card adds the count and
     * the period to the facts; the course scenarios' hero blocks stay out.
     */
    public function test_a_visitor_sees_the_courses_and_a_ghost(): void {
        $start = make_timestamp(2026, 11, 2, 12);
        $public = $this->course(
            ['fullname' => 'Curso Público', 'startdate' => $start, 'enddate' => $start + 6 * DAYSECS],
            [landingfields::SHORTNAME_WORKLOAD => 16]
        );
        $listed = $this->course(['fullname' => 'Curso Listado Secreto', 'startdate' => $start, 'enddate' => $start + 30 * DAYSECS]);
        $this->cover($public);
        $trail = $this->trail([$public, $listed], ['Fundamentos & Gestão', 'Etapa Listada Secreta']);
        $this->set_course_state((int) $public->id, discoverability::STATE_PUBLIC);
        $this->set_course_state((int) $trail->id, discoverability::STATE_PUBLIC);
        $this->become(null);

        $context = $this->export($trail, new resolution(resolution::STATE_VISITOR), true);

        $part = $context->trail;
        $this->assertSame(2, $part['count']);
        $this->assertSame(['n' => 2, 'isghost' => true], $part['steps'][1], 'A ghost carries its place and nothing else.');
        $full = $part['steps'][0];
        $this->assertSame('Fundamentos & Gestão', $full['name'], 'The activity\'s name, in the plain spelling.');
        $this->assertSame('Gestão & Governo', $full['category']);
        $this->assertStringContainsString(
            "/theme_dimensions/cover/{$public->id}/",
            $full['imageurl'],
            'A visitor\'s cover goes through the theme\'s route, which needs no session.'
        );
        $this->assertSame(get_string('landing_hours', 'theme_dimensions', 16), $full['hours']);
        $format = get_string('strftimedatemonthabbr', 'langconfig');
        $this->assertSame(get_string('landing_trail_period', 'theme_dimensions', (object) [
            'from' => userdate($start, $format),
            'to' => userdate($start + 6 * DAYSECS, $format),
        ]), $full['when']);
        $this->assertSame(
            get_string('landing_trail_period', 'theme_dimensions', (object) [
                'from' => userdate($start, get_string('strftimedatefullshort', 'langconfig')),
                'to' => userdate($start + 6 * DAYSECS, get_string('strftimedatefullshort', 'langconfig')),
            ]),
            $part['periodshort'],
            'The period reads the full cards only: the ghost\'s course runs a month and says nothing.'
        );
        $this->assertSame(
            [get_string('landing_trail_kind', 'theme_dimensions'), get_string('landing_trail_coursecount', 'theme_dimensions', 2)],
            array_slice($context->chips, 0, 2)
        );
        $facts = array_column($context->facts, 'value', 'label');
        $this->assertSame('2', $facts[get_string('landing_trail_factcourses', 'theme_dimensions')]);
        $this->assertNull($context->herocomp, 'A trail\'s hero says its courses, not the hybrid composition.');

        global $PAGE;
        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString('Fundamentos &amp; Gestão', $html);
        $this->assertStringNotContainsString('Secreta', $html, 'Neither the activity\'s name nor its course\'s reaches a ghost.');
        $this->assertStringNotContainsString('Secreto', $html);
        $this->assertStringContainsString(get_string('landing_trail_ghost', 'theme_dimensions'), $html);
    }

    /**
     * An account's covers are core's own, the URL Boost Union's cards and the Dashboard read for the same course.
     */
    public function test_an_account_gets_cores_cover(): void {
        $ref = $this->course(['fullname' => 'Curso com capa']);
        $this->cover($ref);
        $trail = $this->trail([$ref]);
        $this->become(self::getDataGenerator()->create_user());

        $step = $this->export($trail, new resolution(resolution::STATE_CLOSED), false)->trail['steps'][0];
        $this->assertStringContainsString('/course/overviewfiles/', $step['imageurl']);
        $this->assertStringNotContainsString('/theme_dimensions/cover/', $step['imageurl']);
    }

    /**
     * A course saved as a trail without mod_subcourse reads as a course, and so does a page of any other model.
     */
    public function test_no_trail_part_without_the_module_or_the_model(): void {
        $ref = $this->course(['fullname' => 'Curso']);
        $trail = $this->trail([$ref]);
        // A course page with a Subcourse activity of its own is still a course page.
        $course = $this->trail([$ref], [], landingfields::MODEL_COURSE);

        $this->assertNotNull($this->export($trail, new resolution(resolution::STATE_CLOSED), false)->trail, 'Precondition.');
        $coursepage = $this->export($course, new resolution(resolution::STATE_CLOSED), false);
        $this->assertNull($coursepage->trail);
        $this->assertNotNull($coursepage->herocomp, 'Precondition: the same course as a course page has its composition bar.');

        \core\plugininfo\mod::enable_plugin('subcourse', 0);
        $this->assertNull($this->export($trail, new resolution(resolution::STATE_CLOSED), false)->trail);
    }

    /**
     * A trail of eight courses costs an account the statements one of two does.
     */
    public function test_the_page_costs_the_same_whatever_the_number_of_courses(): void {
        global $DB;
        $learner = self::getDataGenerator()->create_user();
        $refs = [];
        for ($i = 0; $i < 11; $i++) {
            $refs[] = $this->course(['fullname' => "Curso {$i}"], [landingfields::SHORTNAME_WORKLOAD => 10 + $i]);
        }
        $small = $this->trail(array_slice($refs, 0, 2));
        $big = $this->trail(array_slice($refs, 2, 8));
        $warm = $this->trail(array_slice($refs, 10, 1));
        $this->become($learner);
        // The modinfo and the category records are core's caches, warm on a page that draws a trail. A third trail's
        // export warms what every page shares, and nothing either measured trail or its courses own.
        get_fast_modinfo($small->id);
        get_fast_modinfo($big->id);
        $this->export($warm, new resolution(resolution::STATE_CLOSED), false);

        $reads = [];
        foreach (['small' => $small, 'big' => $big] as $name => $trail) {
            self::forget();
            $before = $DB->perf_get_reads();
            $context = $this->export($trail, new resolution(resolution::STATE_CLOSED), false);
            $reads[$name] = $DB->perf_get_reads() - $before;
            $this->assertCount($name === 'small' ? 2 : 8, $context->trail['steps'], "Precondition: {$name} draws its courses.");
        }
        $this->assertSame($reads['small'], $reads['big'], 'Six more courses add no statement.');
    }
}
