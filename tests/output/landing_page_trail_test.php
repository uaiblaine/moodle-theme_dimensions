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

    /**
     * Mark a course complete for a user, as core's course completion records it.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user.
     * @param int $time When.
     * @return void
     */
    private function completed(\stdClass $course, \stdClass $user, int $time): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        (new \completion_completion(['course' => $course->id, 'userid' => $user->id]))->mark_complete($time);
    }

    /**
     * A learner of the trail reads each card's state, the hero counts what is done, and the card's button and the
     * phone's bar continue at the next step's activity.
     */
    public function test_a_learner_reads_the_states_and_continues_at_the_next_step(): void {
        $start = time() - WEEKSECS;
        $first = $this->course(['fullname' => 'Primeiro', 'startdate' => $start, 'enddate' => $start + 4 * WEEKSECS]);
        $second = $this->course(['fullname' => 'Segundo', 'startdate' => $start, 'enddate' => $start + 4 * WEEKSECS]);
        $third = $this->course(['fullname' => 'Terceiro', 'startdate' => $start, 'enddate' => $start + 4 * WEEKSECS]);
        $trail = $this->trail([$first, $second], ['Primeiro passo', 'Liderança & Pessoas']);
        $secondcmid = \theme_dimensions\local\trail\trail::of(get_course($trail->id))->steps[1]->cmid;
        // The third step opens once the second is complete, and core says so.
        self::getDataGenerator()->create_module('subcourse', [
            'course' => $trail->id,
            'refcourse' => $third->id,
            'name' => 'Projeto final',
            'availability' => json_encode([
                'op' => '&',
                'c' => [['type' => 'completion', 'cm' => $secondcmid, 'e' => COMPLETION_COMPLETE]],
                'showc' => [true],
            ]),
        ]);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $this->completed($first, $learner, make_timestamp(2026, 11, 8, 12));
        $this->become($learner);

        $context = $this->export($trail, new resolution(resolution::STATE_ENROLLED), false);

        $part = $context->trail;
        $progress = get_string('landing_trail_progress', 'theme_dimensions', (object) ['done' => 1, 'count' => 3]);
        $this->assertSame($progress, $part['sequence']);
        $this->assertSame($part['sequence'], $part['segmentslabel'], 'The segments say what the hero says.');
        [$done, $next, $locked] = $part['steps'];
        $this->assertSame('done', $done['state']);
        $this->assertTrue($done['isdone']);
        $doneon = userdate(make_timestamp(2026, 11, 8, 12), get_string('strftimedatemonthabbr', 'langconfig'));
        $this->assertSame(get_string('landing_trail_donedate', 'theme_dimensions', $doneon), $done['when']);
        $this->assertSame('current', $next['state']);
        $this->assertTrue($next['isnext']);
        $this->assertStringEndsWith(get_string('landing_trail_daysleft', 'theme_dimensions', 21), $next['when']);
        $this->assertSame('locked', $locked['state']);
        $this->assertTrue($locked['islocked']);
        $this->assertStringContainsString('Liderança & Pessoas', $locked['when'], 'Core\'s reason leads the locked step\'s line.');

        $this->assertSame([
            'url' => (new \core\url('/mod/subcourse/view.php', ['id' => $secondcmid]))->out(false),
            'label' => get_string('landing_trail_continue', 'theme_dimensions', 'Liderança & Pessoas'),
        ], $context->trailcontinue);
        $this->assertSame($context->trailcontinue['url'], $context->bar['url']);
        $this->assertSame(get_string('landing_trail_continueshort', 'theme_dimensions'), $context->bar['label']);

        global $PAGE;
        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString('data-state="done"', $html);
        $this->assertStringContainsString('data-state="current"', $html);
        $this->assertStringContainsString('data-state="locked"', $html);
        $this->assertStringContainsString('dimensions-landing__trailseg--done', $html);
        $this->assertStringContainsString('Liderança &amp; Pessoas', $html);
        $this->assertStringContainsString(get_string('landing_trail_enrolled', 'theme_dimensions'), $html);
        $this->assertStringNotContainsString(get_string('landing_alreadyenrolled', 'theme_dimensions'), $html);
    }

    /**
     * The same reader not enrolled in the trail course reads the trail as anybody does: no state, no count of what
     * is done, no way on.
     */
    public function test_progress_is_only_for_a_learner_of_the_trail(): void {
        $start = time() - WEEKSECS;
        $first = $this->course(['fullname' => 'Primeiro', 'startdate' => $start, 'enddate' => $start + 4 * WEEKSECS]);
        $trail = $this->trail([$first]);
        $reader = self::getDataGenerator()->create_user();
        $this->completed($first, $reader, time());
        $this->become($reader);

        $context = $this->export($trail, new resolution(resolution::STATE_CLOSED), false);
        $this->assertArrayNotHasKey('state', $context->trail['steps'][0]);
        $this->assertSame(get_string('landing_trail_onecourse', 'theme_dimensions'), $context->trail['sequence']);
        $this->assertNull($context->trailcontinue);

        // The control: the reader enrolled, whose completion of the course makes the step done.
        self::getDataGenerator()->enrol_user($reader->id, $trail->id, 'student');
        $enrolled = $this->export($trail, new resolution(resolution::STATE_ENROLLED), false);
        $this->assertSame('done', $enrolled->trail['steps'][0]['state']);
    }

    /**
     * A step that opens and never closes leaves the trail's period open: no earlier step's end may close it.
     */
    public function test_a_step_without_an_end_leaves_the_period_open(): void {
        $start = make_timestamp(2026, 11, 2, 12);
        $first = $this->course(['fullname' => 'Fechado', 'startdate' => $start, 'enddate' => $start + 6 * DAYSECS]);
        $last = $this->course(['fullname' => 'Aberto', 'startdate' => $start + 30 * DAYSECS, 'enddate' => 0]);
        $trail = $this->trail([$first, $last]);
        $this->become(self::getDataGenerator()->create_user());

        $part = $this->export($trail, new resolution(resolution::STATE_CLOSED), false)->trail;
        $this->assertSame(
            get_string('landing_trail_periodfrom', 'theme_dimensions', userdate($start, get_string('strftimedate', 'langconfig'))),
            $part['period']
        );
        $fromshort = userdate($start, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertSame(get_string('landing_trail_periodfrom', 'theme_dimensions', $fromshort), $part['periodshort']);

        // The control: the same last step with an end closes the period there.
        $closed = $this->course([
            'fullname' => 'Com fim',
            'startdate' => $start + 30 * DAYSECS,
            'enddate' => $start + 40 * DAYSECS,
        ]);
        $other = $this->trail([$first, $closed]);
        $expected = get_string('landing_trail_period', 'theme_dimensions', (object) [
            'from' => userdate($start, get_string('strftimedate', 'langconfig')),
            'to' => userdate($start + 40 * DAYSECS, get_string('strftimedate', 'langconfig')),
        ]);
        $this->assertSame($expected, $this->export($other, new resolution(resolution::STATE_CLOSED), false)->trail['period']);
    }

    /**
     * A learner's hero says when the trail is due.
     */
    public function test_a_learners_hero_says_when_the_trail_is_due(): void {
        $start = make_timestamp(2026, 11, 2, 12);
        $ref = $this->course(['fullname' => 'Curso', 'startdate' => $start, 'enddate' => $start + 46 * DAYSECS]);
        $trail = $this->trail([$ref]);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $this->become($learner);

        $part = $this->export($trail, new resolution(resolution::STATE_ENROLLED), false)->trail;
        $due = userdate($start + 46 * DAYSECS, get_string('strftimedate', 'langconfig'));
        $this->assertSame(get_string('landing_trail_until', 'theme_dimensions', $due), $part['period']);
    }

    /**
     * A learner's open trail says when it began, and its current step with no end counts no days.
     */
    public function test_a_learners_open_trail_has_no_due_date(): void {
        $start = time() - WEEKSECS;
        $ref = $this->course(['fullname' => 'Sem fim', 'startdate' => $start, 'enddate' => 0]);
        $trail = $this->trail([$ref]);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $this->become($learner);

        $part = $this->export($trail, new resolution(resolution::STATE_ENROLLED), false)->trail;
        $began = userdate($start, get_string('strftimedate', 'langconfig'));
        $this->assertSame(get_string('landing_trail_periodfrom', 'theme_dimensions', $began), $part['period']);
        $this->assertSame('current', $part['steps'][0]['state'], 'Precondition.');
        $from = userdate($start, get_string('strftimedatemonthabbr', 'langconfig'));
        $this->assertSame(get_string('landing_trail_periodfrom', 'theme_dimensions', $from), $part['steps'][0]['when']);
    }

    /**
     * A learner's trail of eight courses costs the statements one of two does, progress included.
     */
    public function test_a_learners_page_costs_the_same_whatever_the_number_of_courses(): void {
        global $DB;
        $learner = self::getDataGenerator()->create_user();
        $refs = [];
        for ($i = 0; $i < 11; $i++) {
            $refs[] = $this->course(['fullname' => "Curso {$i}"], [landingfields::SHORTNAME_WORKLOAD => 10 + $i]);
        }
        $small = $this->trail(array_slice($refs, 0, 2));
        $big = $this->trail(array_slice($refs, 2, 8));
        $warm = $this->trail(array_slice($refs, 10, 1));
        foreach ([$small, $big, $warm] as $trail) {
            self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        }
        $this->become($learner);
        $this->export($warm, new resolution(resolution::STATE_ENROLLED), false);

        $reads = [];
        foreach (['small' => $small, 'big' => $big] as $name => $trail) {
            // Core's caches warm on the trail's own first page: its modinfo, its contexts and its completion data,
            // whose fill asks each activity's own rules (mod_subcourse reads its row per activity), core's cost,
            // shared with the course page. The second page is what the page itself costs.
            $this->export($trail, new resolution(resolution::STATE_ENROLLED), false);
            self::forget();
            $before = $DB->perf_get_reads();
            $context = $this->export($trail, new resolution(resolution::STATE_ENROLLED), false);
            $reads[$name] = $DB->perf_get_reads() - $before;
            $this->assertArrayHasKey('state', $context->trail['steps'][0], "Precondition: {$name} reads progress.");
        }
        $this->assertSame($reads['small'], $reads['big'], 'Six more courses add no statement.');
    }

    /**
     * The next course of a learner that has not begun says when it starts, and counts no day yet; once begun, it is in
     * progress with its days left.
     */
    public function test_a_next_course_not_begun_says_when_it_starts(): void {
        $start = time() + 10 * DAYSECS;
        $ref = $this->course(['fullname' => 'Depois', 'startdate' => $start, 'enddate' => $start + 4 * WEEKSECS]);
        $trail = $this->trail([$ref]);
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $this->become($learner);

        $step = $this->export($trail, new resolution(resolution::STATE_ENROLLED), false)->trail['steps'][0];
        $format = get_string('strftimedatemonthabbr', 'langconfig');
        $this->assertSame('current', $step['state'], 'It is still the one to go on with.');
        $this->assertSame(get_string('landing_trail_startson', 'theme_dimensions', userdate($start, $format)), $step['statelabel']);
        $this->assertStringNotContainsString(get_string('landing_trail_daysleft', 'theme_dimensions', 38), $step['when']);

        // The control: the same course begun yesterday is in progress, with its days left.
        $begun = $this->course(['fullname' => 'Agora', 'startdate' => time() - DAYSECS, 'enddate' => time() + 4 * WEEKSECS]);
        $other = $this->trail([$begun]);
        self::getDataGenerator()->enrol_user($learner->id, $other->id, 'student');
        $now = $this->export($other, new resolution(resolution::STATE_ENROLLED), false)->trail['steps'][0];
        $this->assertSame(get_string('landing_trail_state_current', 'theme_dimensions'), $now['statelabel']);
        $this->assertStringEndsWith(get_string('landing_trail_daysleft', 'theme_dimensions', 28), $now['when']);
    }

    /**
     * An account's card leads to the calendar's Trilhas view with this trail kept, on the month the trail is in: its
     * first month before it starts, today's while it runs, its last once it has ended. A visitor's card does not.
     */
    public function test_an_account_is_led_to_the_trail_in_the_calendar(): void {
        global $CFG;
        $CFG->routerconfigured = false;
        $month = static fn(int $time): string => userdate($time, '%Y-%m');
        $soon = make_timestamp((int) date('Y') + 1, 3, 2, 12);
        $later = $this->course(['fullname' => 'Depois', 'startdate' => $soon, 'enddate' => $soon + 2 * WEEKSECS]);
        $running = $this->course(['fullname' => 'Agora', 'startdate' => time() - WEEKSECS, 'enddate' => time() + WEEKSECS]);
        $ended = $this->course(['fullname' => 'Antes', 'startdate' => time() - 20 * WEEKSECS, 'enddate' => time() - 10 * WEEKSECS]);
        $cases = [
            'not begun' => [$this->trail([$later]), $month($soon)],
            'running' => [$this->trail([$running]), $month(time())],
            'ended' => [$this->trail([$ended]), $month(time() - 10 * WEEKSECS)],
        ];
        // Open to self enrolment: the link follows the view's rule, which shows such a trail to an account with no tie.
        foreach ($cases as [$trail]) {
            $this->enable_instance((int) $trail->id, 'self', ['customint6' => 1]);
        }
        $this->become(self::getDataGenerator()->create_user());

        foreach ($cases as $name => [$trail, $expected]) {
            $context = $this->export($trail, new resolution(resolution::STATE_CLOSED), false);
            $this->assertNotNull($context->trailcalendarurl, $name);
            $url = new \core\url($context->trailcalendarurl);
            $this->assertSame(
                ['trail', $expected, (string) $trail->id],
                [$url->param('view'), $url->param('month'), $url->param('trail')],
                $name
            );
        }

        // The control: a visitor of the same trail, made public, sees its courses and no way to the calendar.
        [$trail] = $cases['running'];
        $this->set_course_state((int) $running->id, discoverability::STATE_PUBLIC);
        $this->set_course_state((int) $trail->id, discoverability::STATE_PUBLIC);
        $this->become(null);
        $context = $this->export($trail, new resolution(resolution::STATE_VISITOR), true);
        $this->assertNotNull($context->trail, 'Precondition: the visitor sees the trail.');
        $this->assertNull($context->trailcalendarurl);
    }

    /**
     * "Ver no calendário" follows the calendar's own rule ({@see \theme_dimensions\local\calendar\trails::offers()})
     * both ways: an account with no tie to a closed trail is not offered it; a learner of it, an editing teacher, a way
     * in and the trail made public each offer it, and the account with no tie is answered nothing by the view either.
     */
    public function test_the_calendar_link_follows_the_views_rule(): void {
        global $CFG, $DB;
        $CFG->routerconfigured = false;
        $ref = $this->course(['fullname' => 'Agora', 'startdate' => time() - WEEKSECS, 'enddate' => time() + WEEKSECS]);
        $trail = $this->trail([$ref]);
        $context = \core\context\course::instance($trail->id);
        $reader = self::getDataGenerator()->create_user();
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $trail->id, 'student');
        $editor = self::getDataGenerator()->create_user();
        role_assign((int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']), $editor->id, $context->id);
        $link = function (\stdClass $user, string $state) use ($trail): ?string {
            $this->become($user);
            return $this->export($trail, new resolution($state), false)->trailcalendarurl;
        };

        $this->assertNull($link($reader, resolution::STATE_CLOSED), 'No tie and no way in: no link.');
        $this->assertFalse(\theme_dimensions\local\calendar\trails::offers((int) $trail->id), 'Nor a trail.');
        $this->assertNotNull($link($learner, resolution::STATE_ENROLLED), 'A learner of the trail.');
        $this->assertNotNull($link($editor, resolution::STATE_CLOSED), 'An editing teacher with no enrolment.');

        $this->setAdminUser();
        $this->enable_instance((int) $trail->id, 'self', ['customint6' => 1]);
        $this->assertNotNull($link($reader, resolution::STATE_CLOSED), 'A way in.');

        $this->setAdminUser();
        enrol_get_plugin('self')->update_status(
            $DB->get_record('enrol', ['courseid' => $trail->id, 'enrol' => 'self'], '*', MUST_EXIST),
            ENROL_INSTANCE_DISABLED
        );
        $this->assertNull($link($reader, resolution::STATE_CLOSED), 'Closed again: no link.');
        $this->set_course_state((int) $trail->id, discoverability::STATE_PUBLIC);
        $this->assertNotNull($link($reader, resolution::STATE_CLOSED), 'Public: every account.');
    }

    /**
     * The link follows the calendar's gate as well as its rule: a page served to a reader the listing withholds the trail
     * from offers no link, though a way in is open, for a trail in an unlisted category and for a hidden trail; the
     * calendar lists neither to the same reader. The control: a listed trail with the same way in.
     */
    public function test_the_calendar_link_follows_the_views_gate(): void {
        global $CFG, $DB;
        $CFG->routerconfigured = false;
        $ref = $this->course(['fullname' => 'Agora', 'startdate' => time() - WEEKSECS, 'enddate' => time() + WEEKSECS]);
        $listed = $this->trail([$ref]);
        $hidden = $this->trail([$ref]);
        $DB->set_field('course', 'visible', 0, ['id' => $hidden->id]);
        rebuild_course_cache((int) $hidden->id, true);
        $this->category = self::getDataGenerator()->create_category(['name' => 'Reservada']);
        $unlisted = $this->trail([$ref]);
        $this->set_category_state((int) $this->category->id, \local_unlistedcourses\category_discoverability::STATE_UNLISTED);
        foreach ([$listed, $unlisted, $hidden] as $trail) {
            $this->enable_instance((int) $trail->id, 'self', ['customint6' => 1]);
        }
        $reader = self::getDataGenerator()->create_user();

        $month = userdate(time(), '%Y-%m');
        $cases = ['unlisted category' => [$unlisted, false], 'hidden' => [$hidden, false], 'listed' => [$listed, true]];
        foreach ($cases as $name => [$trail, $linked]) {
            $this->become($reader);
            $link = $this->export($trail, new resolution(resolution::STATE_CLOSED), false)->trailcalendarurl;
            $this->assertSame($linked, $link !== null, $name);
            $this->become($reader);
            $answer = \theme_dimensions\local\calendar\trails::answer(
                \theme_dimensions\local\calendar\trails::month_window($month),
                \theme_dimensions\local\calendar\scope::for_viewer(0),
                0,
                false,
                time()
            );
            $inanswer = in_array((int) $trail->id, array_column($answer['options'], 'id'), true);
            $this->assertSame($linked, $inanswer, "{$name}: the calendar agrees.");
        }
    }
}
