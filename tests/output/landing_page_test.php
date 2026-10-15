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
 * Theme Dimensions - Tests for the landing page renderable
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\method;
use theme_dimensions\local\landing\publicfiles;
use theme_dimensions\local\landing\resolution;
use theme_dimensions\local\landing\resolver;
use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the landing page renderable.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landing_page::class)]
final class landing_page_test extends \advanced_testcase {
    /**
     * The exported context carries the plain spellings, the rendered HTML and the method wiring.
     *
     * The course name carries a bare ampersand: the one fixture that tells the
     * plain spelling (double-stash sinks escape for themselves) apart from a
     * pre-escaped one.
     */
    public function test_export_builds_the_expected_context(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course([
            'fullname' => 'Resgate & Salvamento',
            'summary' => '<p>Resumo do curso.</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        $handler = \core_course\customfield\course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_ONLINE,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 36,
            'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                'text' => '<p>Módulo 1.</p>',
                'format' => FORMAT_HTML,
            ],
        ]);

        \core\plugininfo\enrol::enable_plugin('self', true);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($instance, ENROL_INSTANCE_ENABLED);
        $this->setUser(self::getDataGenerator()->create_user());

        $values = landingfields::values($course->id);
        $resolution = resolver::resolve($course);

        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page($course, $values, $resolution, []))->export_for_template($renderer);

        $this->assertSame('Resgate & Salvamento', $context->fullname);
        $this->assertStringContainsString('Resumo do curso', $context->summaryhtml);
        $this->assertTrue($context->isopen);
        $this->assertFalse($context->isclosed);

        $this->assertCount(1, $context->methods);
        $methodcontext = $context->methods[0];
        $this->assertTrue($methodcontext['isself']);
        $this->assertTrue($methodcontext['primary']);
        $this->assertFalse($methodcontext['requirespassword']);
        $this->assertStringContainsString('action=enrol', $methodcontext['enrolurl']);
        $this->assertStringContainsString('sesskey=', $methodcontext['enrolurl']);

        $this->assertContains(
            get_string('landingfield_modality_online', 'theme_dimensions'),
            $context->chips
        );
        $this->assertContains(get_string('landing_hours', 'theme_dimensions', '36'), $context->chips);

        // One section: only the non-empty textarea renders.
        $this->assertCount(1, $context->sections);
        $this->assertStringContainsString('Módulo 1', $context->sections[0]['html']);
    }

    /**
     * Provision the landing page fields and reset every cache the tests lean on.
     *
     * @return void
     */
    private function prepare_fields(): void {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * Create a course configured as a landing page offering.
     *
     * @param int $model The page model select value.
     * @param int $modality The modality select value.
     * @param array $courserecord Extra course record fields.
     * @param array $customfields Extra customfield_<shortname> form properties.
     * @return \stdClass The course record.
     */
    private function create_offering(int $model, int $modality, array $courserecord = [], array $customfields = []): \stdClass {
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course($courserecord);
        $handler = \core_course\customfield\course_handler::create();
        $handler->instance_form_save((object) ($customfields + [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => $model,
            'customfield_' . landingfields::SHORTNAME_MODALITY => $modality,
        ]));
        return $course;
    }

    /**
     * An online event puts the date first: big date block, time range, countdown.
     */
    public function test_online_event_hero_carries_the_date(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $start = time() + (10 * DAYSECS);
        $course = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_ONLINE, [
            'startdate' => $start,
            'enddate' => $start + (2 * HOURSECS),
        ]);
        $values = landingfields::values($course->id);
        $resolution = new resolution(resolution::STATE_CLOSED);

        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page($course, $values, $resolution, []))->export_for_template($renderer);

        $this->assertNotNull($context->herodate);
        $this->assertNull($context->heroplace);
        $this->assertFalse($context->isheld);
        // A one-day event: the sub line is a time range plus the live label.
        $this->assertStringContainsString('–', $context->herodate['sub']);
        $this->assertStringContainsString(get_string('landing_live', 'theme_dimensions'), $context->herodate['sub']);
        $this->assertSame(get_string('landing_daysleft', 'theme_dimensions', 10), $context->herodate['countdown']);
        // The card leads with the date fact.
        $this->assertSame(get_string('date'), $context->facts[0]['label']);
    }

    /**
     * An in-person event puts the place first; an in-person course keeps it medium.
     */
    public function test_in_person_offerings_carry_the_place(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $event = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_INPERSON, [
            'startdate' => time() + (30 * DAYSECS),
        ], [
            'customfield_' . landingfields::SHORTNAME_CITY => 'São Paulo',
            'customfield_' . landingfields::SHORTNAME_VENUE => 'Teatro da Reitoria',
        ]);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $event->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page($event, landingfields::values($event->id), new resolution(resolution::STATE_CLOSED), []))
            ->export_for_template($renderer);

        $this->assertNull($context->herodate);
        $this->assertTrue($context->heroplace['big']);
        $this->assertSame('São Paulo', $context->heroplace['city']);
        $this->assertSame('Teatro da Reitoria', $context->heroplace['venue']);
        $this->assertNotNull($context->heroplace['dateline']);
        // The city moved out of the chips into the place block.
        $this->assertNotContains('São Paulo', $context->chips);

        $plaincourse = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_INPERSON, [], [
            'customfield_' . landingfields::SHORTNAME_CITY => 'Londrina',
        ]);
        $context = (new landing_page($plaincourse, landingfields::values($plaincourse->id), new resolution(resolution::STATE_CLOSED), []))
            ->export_for_template($renderer);
        $this->assertFalse($context->heroplace['big']);
        $this->assertSame('Londrina', $context->heroplace['city']);
        $this->assertNull($context->heroplace['dateline']);
    }

    /**
     * A held event shows the seal and enrols nobody, whatever the resolver offered.
     */
    public function test_held_event_replaces_the_enrolment_area(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $course = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_ONLINE, [
            'startdate' => time() - (10 * DAYSECS),
            'enddate' => time() - (3 * DAYSECS),
        ]);
        $values = landingfields::values($course->id);
        // The resolver found a usable method (a misconfigured window): the seal still wins.
        $selfmethod = method::for_self((object) ['id' => 55, 'enrol' => 'self', 'password' => '', 'name' => ''], false);
        $resolution = new resolution(resolution::STATE_OPEN, [$selfmethod]);

        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page($course, $values, $resolution, []))->export_for_template($renderer);

        $this->assertTrue($context->isheld);
        $this->assertTrue($context->isheldonly);
        $this->assertNotNull($context->heldon);
        $this->assertSame([], $context->methods);
        $this->assertFalse($context->isopen);
        $this->assertNull($context->bar);
        // No countdown on a past event.
        $this->assertNull($context->herodate['countdown']);

        // An enrolled participant keeps the way into the course, with the seal beside it.
        $context = (new landing_page($course, $values, new resolution(resolution::STATE_ENROLLED), []))
            ->export_for_template($renderer);
        $this->assertTrue($context->isheld);
        $this->assertFalse($context->isheldonly);
        $this->assertTrue($context->isenrolled);
        $this->assertNotNull($context->bar);
    }

    /**
     * The mobile bar mirrors the card: direct links or the anchor down to it.
     */
    public function test_bar_follows_the_state(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $values = landingfields::values($course->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');

        $selfmethod = method::for_self((object) ['id' => 55, 'enrol' => 'self', 'password' => '', 'name' => ''], false);
        $open = (new landing_page($course, $values, new resolution(resolution::STATE_OPEN, [$selfmethod]), []))
            ->export_for_template($renderer);
        $this->assertSame('#dimensions-landing-card', $open->bar['url']);

        $visitor = (new landing_page($course, $values, new resolution(resolution::STATE_VISITOR), []))
            ->export_for_template($renderer);
        $this->assertSame(get_login_url(), $visitor->bar['url']);

        $closed = (new landing_page($course, $values, new resolution(resolution::STATE_CLOSED), []))
            ->export_for_template($renderer);
        $this->assertNull($closed->bar);
    }

    /**
     * A scheduled enrolment says when access starts, with the end date when there is one, and offers nothing to enrol in.
     */
    public function test_scheduled_state_names_the_start_and_end_dates(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $values = landingfields::values($course->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $start = time() + 5 * DAYSECS;
        $end = $start + 60 * DAYSECS;
        $format = get_string('strftimedatefullshort', 'langconfig');

        $scheduled = (new landing_page($course, $values, new resolution(resolution::STATE_SCHEDULED, [], null, $start, $end), []))
            ->export_for_template($renderer);
        $this->assertTrue($scheduled->isscheduled);
        $this->assertSame(userdate($start, $format), $scheduled->scheduledfrom);
        $this->assertSame(userdate($end, $format), $scheduled->scheduleduntil);
        $this->assertFalse($scheduled->isopen);
        $this->assertFalse($scheduled->isenrolled);
        $this->assertNull($scheduled->bar, 'There is nothing to press: no enrolment and no course to enter yet.');

        $open = (new landing_page($course, $values, new resolution(resolution::STATE_OPEN, []), []))
            ->export_for_template($renderer);
        $this->assertFalse($open->isscheduled, 'Control: an open state is not scheduled.');
        $this->assertNull($open->scheduledfrom);
    }

    /**
     * The viewer's own row adds its note to the enrolment area: the waiting list, a suspension, an end, a prerequisite.
     *
     * Each note is rendered as well as exported, so a note the template forgot reddens here; each has its control,
     * the same resolution without the relationship. The suspended viewer keeps the method and the mobile bar of an
     * open area; a conditional area replaces the closed sentence (which sends the reader to a focal point) with the
     * prerequisite, named in the plain spelling and escaped once by the page.
     */
    public function test_the_relationship_notes_sit_above_the_methods(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $prerequisite = self::getDataGenerator()->create_course(['fullname' => 'Base & Prática']);
        $values = landingfields::values($course->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $format = get_string('strftimedatefullshort', 'langconfig');
        $component = 'theme_dimensions';
        $page = function (resolution $resolution) use ($course, $values, $renderer): array {
            $landing = new landing_page($course, $values, $resolution, []);
            return [$landing->export_for_template($renderer), $renderer->render($landing)];
        };

        $applied = time() - DAYSECS;
        [$waiting, $html] = $page(new resolution(resolution::STATE_PENDING, [], $applied, null, null, 'waitlisted'));
        $this->assertTrue($waiting->iswaitlisted);
        $this->assertFalse($waiting->ispending, 'One heading, the waiting list\'s.');
        $this->assertStringContainsString(get_string('landing_waitlistedheading', $component), $html);
        $this->assertStringContainsString(s(get_string('landing_pendingnote', $component, userdate($applied, $format))), $html);
        [$pending, $html] = $page(new resolution(resolution::STATE_PENDING, [], $applied, null, null, 'pending'));
        $this->assertTrue($pending->ispending, 'Control: an application awaiting a decision.');
        $this->assertFalse($pending->iswaitlisted);
        $this->assertStringNotContainsString(get_string('landing_waitlistedheading', $component), $html);

        $self = method::for_self((object) ['id' => 55, 'enrol' => 'self', 'password' => '', 'name' => ''], false);
        [$suspended, $html] = $page(new resolution(resolution::STATE_OPEN, [$self], null, null, null, 'suspended'));
        $this->assertTrue($suspended->issuspended);
        $this->assertTrue($suspended->isopen, 'The method stays offered.');
        $this->assertNotNull($suspended->bar, 'And the bar with it.');
        $this->assertStringContainsString(get_string('landing_suspendednote', $component), $html);
        [$open, $html] = $page(new resolution(resolution::STATE_OPEN, [$self]));
        $this->assertFalse($open->issuspended, 'Control: no row, no note.');
        $this->assertStringNotContainsString(get_string('landing_suspendednote', $component), $html);

        $end = time() - 3 * DAYSECS;
        [$expired, $html] = $page(new resolution(resolution::STATE_CLOSED, [], null, null, $end, 'expired'));
        $this->assertTrue($expired->isexpired);
        $this->assertSame(userdate($end, $format), $expired->expiredon);
        $this->assertTrue($expired->showclosed, 'Nothing open: the closed sentence stays.');
        $this->assertStringContainsString(s(get_string('landing_expirednote', $component, userdate($end, $format))), $html);

        $waits = new resolution(resolution::STATE_CLOSED, [], null, null, null, 'none', (int) $prerequisite->id);
        [$conditional, $html] = $page($waits);
        $this->assertTrue($conditional->isconditional);
        $this->assertSame('Base & Prática', $conditional->prerequisite['name'], 'The plain spelling.');
        $this->assertSame(
            (new \moodle_url('/course/view.php', ['id' => $prerequisite->id]))->out(false),
            $conditional->prerequisite['url']
        );
        $this->assertFalse($conditional->showclosed, 'The prerequisite says what to do instead.');
        $this->assertStringContainsString('Base &amp; Prática', $html, 'Escaped once.');
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('dimensions-landing__closed', $html);
        [$closed, $html] = $page(new resolution(resolution::STATE_CLOSED, []));
        $this->assertFalse($closed->isconditional, 'Control: a plain closed area.');
        $this->assertTrue($closed->showclosed);
        $this->assertStringContainsString('dimensions-landing__closed', $html);
    }

    /**
     * A provisionally open state closes when every fallback widget rendered nothing.
     */
    public function test_open_state_downgrades_when_fallback_widgets_render_nothing(): void {
        global $PAGE;
        $this->resetAfterTest();
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $values = landingfields::values($course->id);
        $resolution = new resolution(resolution::STATE_OPEN, [
            method::for_fallback((object) ['id' => 77, 'enrol' => 'somethingelse']),
        ]);

        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page($course, $values, $resolution, []))->export_for_template($renderer);

        $this->assertFalse($context->isopen);
        $this->assertTrue($context->isclosed);
        $this->assertSame([], $context->methods);
    }

    /**
     * A public course's summary is cleaned; a non-public one keeps the trusted-author rendering.
     *
     * A script element is the fixture: noclean lets it through, HTMLPurifier
     * never does. Both halves are one test so the control proves the flag is
     * what decides, not the fixture.
     *
     * @return void
     */
    public function test_a_public_course_gets_a_cleaned_summary(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course([
            'summary' => '<p>Texto do resumo.</p><script>alert(1)</script>',
            'summaryformat' => FORMAT_HTML,
        ]);
        $values = landingfields::values($course->id);
        $resolution = resolver::resolve($course);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');

        $public = (new landing_page($course, $values, $resolution, [], true))->export_for_template($renderer);
        $this->assertStringContainsString('Texto do resumo.', $public->summaryhtml);
        $this->assertStringNotContainsString('<script', $public->summaryhtml, 'A public page is cleaned.');

        $internal = (new landing_page($course, $values, $resolution, [], false))->export_for_template($renderer);
        $this->assertStringContainsString(
            '<script',
            $internal->summaryhtml,
            'Control: the internal page keeps the trusted rendering.'
        );
    }
    /**
     * A public course's textarea sections are cleaned too, past the value's trust mark.
     *
     * The fixture is the exact hole: trusted text enabled, the value saved by an
     * admin (who holds moodle/site:trustcontent), so export_value() would ship
     * the script. The non-public page is the control.
     *
     * @return void
     */
    public function test_a_public_course_gets_cleaned_sections(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();
        $CFG->enabletrusttext = 1;
        $this->setAdminUser();
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                'text' => '<p>Módulo 1.</p><script>alert(1)</script>',
                'format' => FORMAT_HTML,
            ],
        ]);
        $values = landingfields::values($course->id);
        $this->assertStringContainsString('<script', (string) $values->programme, 'Precondition: the value is trusted.');
        $resolution = resolver::resolve($course);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');

        $public = (new landing_page($course, $values, $resolution, [], true))->export_for_template($renderer);
        $this->assertCount(1, $public->sections);
        $this->assertStringContainsString('Módulo 1.', $public->sections[0]['html']);
        $this->assertStringNotContainsString('<script', $public->sections[0]['html'], 'A public page is cleaned.');

        $internal = (new landing_page($course, $values, $resolution, [], false))->export_for_template($renderer);
        $this->assertStringContainsString('<script', $internal->sections[0]['html'], 'Control: trusted rendering behind login.');
    }

    /**
     * On a public course the course image and the summary's files go through the theme's route.
     *
     * Core's course/overviewfiles and course/summary answer a visitor with the login page
     * under forcelogin; the non-public page keeps core's URLs and is the control.
     *
     * @return void
     */
    public function test_a_public_course_routes_its_images_through_the_theme(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->prepare_fields();

        $course = self::getDataGenerator()->create_course([
            'summary' => '<p><img src="@@PLUGINFILE@@/foto.png" alt=""></p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        $contextid = \core\context\course::instance($course->id)->id;
        $fs = get_file_storage();
        $record = ['contextid' => $contextid, 'component' => 'course', 'itemid' => 0, 'filepath' => '/'];
        $image = imagecreatetruecolor(1600, 900);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);
        $hero = $fs->create_file_from_string($record + ['filearea' => 'overviewfiles', 'filename' => 'capa.jpg'], $jpeg);
        $fs->create_file_from_string($record + ['filearea' => 'summary', 'filename' => 'foto.png'], $png);

        $values = landingfields::values($course->id);
        $resolution = resolver::resolve($course);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $base = "/pluginfile.php/{$contextid}/theme_dimensions/";

        $public = (new landing_page($course, $values, $resolution, [], true))->export_for_template($renderer);
        $this->assertStringEndsWith("{$base}hero/{$course->id}/{$hero->get_contenthash()}/capa.jpg", $public->heroimageurl);
        $this->assertStringContainsString("{$base}summary/{$course->id}/foto.png", $public->summaryhtml);
        $this->assertStringNotContainsString('/course/summary/', $public->summaryhtml);

        $internal = (new landing_page($course, $values, $resolution, [], false))->export_for_template($renderer);
        $this->assertStringEndsWith("/pluginfile.php/{$contextid}/course/overviewfiles/capa.jpg", $internal->heroimageurl);
        $this->assertStringContainsString("/pluginfile.php/{$contextid}/course/summary/foto.png", $internal->summaryhtml);
    }

    /**
     * The badge grid exports in grid order, through the theme's own file route.
     *
     * Positions 3 and 1 are populated, mirroring the exact fixture of
     * landingfields_test::test_values_reads_badges_in_grid_order_and_the_video_url: the export must
     * read position 1 back before position 3, the grid order, not the order the rows were written in.
     * A course with no badges is the control, so an export that always returned an empty grid could
     * not pass both halves of this test.
     */
    public function test_badges_are_exported_in_grid_order_through_the_theme_route(): void {
        global $CFG, $PAGE;
        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $this->resetAfterTest();
        $this->prepare_fields();

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        $uploads = [
            landingfields::SHORTNAME_CREST3 => 'crest-beta.png',
            landingfields::SHORTNAME_CREST1 => 'crest-alpha.png',
        ];
        foreach ($uploads as $shortname => $filename) {
            $data = $generator->add_instance_data(landingfields::get_field($shortname), (int) $course->id, 1);
            get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $filename,
            ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        }

        $values = landingfields::values($course->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $exported = (new landing_page($course, $values, new resolution(resolution::STATE_CLOSED), []))
            ->export_for_template($renderer);

        $this->assertTrue($exported->hasbadges);
        $this->assertCount(2, $exported->badges);
        // The file name without its extension, hyphens read as spaces.
        $this->assertSame('crest alpha', $exported->badges[0]['alt']);
        $this->assertSame('crest beta', $exported->badges[1]['alt']);
        foreach ($values->crests as $i => $file) {
            $expected = publicfiles::badge_url($course, $file)->out(false);
            $this->assertStringContainsString(
                "/theme_dimensions/badge/{$course->id}/{$file->get_contenthash()}/{$file->get_filename()}",
                $expected
            );
            $this->assertSame($expected, $exported->badges[$i]['url']);
        }

        // Control: a course with no badges exports an empty grid, not the two rows above by accident.
        $other = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $othercontext = (new landing_page(
            $other,
            landingfields::values($other->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);
        $this->assertFalse($othercontext->hasbadges);
        $this->assertSame([], $othercontext->badges);
    }

    /**
     * The video embeds through core's media manager, captioned by the page model - and by "held" above it.
     *
     * The precondition every assertion below leans on: without an enabled YouTube player the address
     * would embed as nothing on every branch, and the test would pass having exercised none of them.
     */
    public function test_video_is_embedded_with_the_caption_of_the_model(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();
        /* The markup is whichever enabled player takes the address first: with core's default
           order Video.JS renders YouTube itself, as a video element. The plain YouTube player
           alone makes the iframe deterministic. */
        \core\plugininfo\media::set_enabled_plugins('youtube');
        \core_media_manager::reset_caches();
        $this->assertContains('youtube', array_keys(\core\plugininfo\media::get_enabled_plugins()));

        $url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [
            'fullname' => 'Resgate & Salvamento',
        ], [
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => $url,
        ]);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page(
            $course,
            landingfields::values($course->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);

        $this->assertNotNull($context->video);
        $this->assertStringContainsString('<iframe', $context->video['html']);
        $this->assertStringContainsString('youtube', $context->video['html']);
        $this->assertSame(get_string('landing_videocourse', 'theme_dimensions'), $context->video['caption']);
        $this->assertSame('youtube.com', $context->video['host']);
        /* The frame's title is the caption, not the course name: the YouTube player escapes the
           name with s() and prints it through a double stash, so a name holding an ampersand
           would read "&amp;" - the offering above carries one ("Resgate & Salvamento") to prove
           it stays out of the frame. */
        $title = 'title="' . get_string('landing_videocourse', 'theme_dimensions') . '"';
        $this->assertStringContainsString($title, $context->video['html']);
        $this->assertStringNotContainsString('&amp;amp;', $context->video['html']);

        // The same address on an event: a different caption, the same embed.
        $event = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => $url,
        ]);
        $eventcontext = (new landing_page(
            $event,
            landingfields::values($event->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);
        $this->assertSame(get_string('landing_videoevent', 'theme_dimensions'), $eventcontext->video['caption']);

        // The same address on an event already held: the recording caption wins over the event one.
        $held = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_ONLINE, [
            'startdate' => time() - (10 * DAYSECS),
            'enddate' => time() - (3 * DAYSECS),
        ], [
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => $url,
        ]);
        $heldcontext = (new landing_page(
            $held,
            landingfields::values($held->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);
        $this->assertTrue($heldcontext->isheld);
        $this->assertSame(get_string('landing_videorecording', 'theme_dimensions'), $heldcontext->video['caption']);

        // A course with no video address exports no video.
        $silent = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $silentcontext = (new landing_page(
            $silent,
            landingfields::values($silent->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);
        $this->assertNull($silentcontext->video);
    }

    /**
     * An address no player embeds is dropped, saved directly since the handler skips the form's
     * validation hook - the only route by which a course can end up holding one at all.
     *
     * The control is the YouTube address built the same way, in the same test: without it, a video
     * export broken for every address would pass this test by never rendering anything.
     */
    public function test_a_video_nobody_embeds_is_dropped(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();
        $this->assertContains('youtube', array_keys(\core\plugininfo\media::get_enabled_plugins()));

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => 'https://example.com/video.html',
        ]);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page(
            $course,
            landingfields::values($course->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);
        $this->assertNull($context->video);

        // Control: the same construction with a YouTube address exports a video.
        $control = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
        ]);
        $controlcontext = (new landing_page(
            $control,
            landingfields::values($control->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($renderer);
        $this->assertNotNull($controlcontext->video);
    }

    /**
     * With the site's own player order untouched, the address still embeds - through Video.JS by default.
     *
     * The page does not pick a player: whichever enabled one takes the address
     * first renders, and core's default order puts Video.JS, which plays YouTube
     * itself, ahead of the YouTube iframe.
     */
    public function test_video_uses_the_players_the_site_enabled(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();
        $this->assertSame(['videojs', 'youtube'], array_keys(\core\plugininfo\media::get_enabled_plugins()));

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
        ]);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $context = (new landing_page(
            $course,
            landingfields::values($course->id),
            new resolution(resolution::STATE_CLOSED),
            []
        ))->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotNull($context->video);
        $this->assertStringContainsString('mediaplugin_videojs', $context->video['html']);
        $this->assertStringContainsString('youtube', $context->video['html']);
        $this->assertStringNotContainsString('<iframe', $context->video['html']);
    }

    /**
     * The hero kicker withholds the category name from someone who must not discover it.
     *
     * Belt-and-braces behind redirector::is_ghosted(): a viewer who already has
     * a relationship with the course (enrolled, applying) can still reach this
     * page while the category above it is unlisted, and the kicker must not
     * name that category unless the viewer is independently eligible for it. A
     * cohort member is the control that the guard reads the real predicate
     * rather than blanking every category, and a guest in an ordinary, listed
     * category is the control that the pre-existing behaviour is untouched.
     */
    public function test_category_kicker_is_withheld_from_an_ineligible_viewer(): void {
        global $CFG, $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();
        $generator = self::getDataGenerator();

        $category = $generator->create_category(['name' => 'Restricted Category']);
        $this->setAdminUser();
        \local_unlistedcourses\category_discoverability::set_state(
            (int) $category->id,
            \local_unlistedcourses\category_discoverability::STATE_UNLISTED
        );
        require_once($CFG->dirroot . '/cohort/lib.php');
        $cohort = $generator->create_cohort([
            'contextid' => \core\context\coursecat::instance($category->id)->id,
        ]);
        $member = $generator->create_user();
        cohort_add_member($cohort->id, $member->id);
        $outsider = $generator->create_user();

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [
            'category' => $category->id,
        ]);
        $values = landingfields::values($course->id);
        $resolution = new resolution(resolution::STATE_CLOSED);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');

        $this->setUser($outsider);
        \local_unlistedcourses\category_access::reset_caches();
        $context = (new landing_page($course, $values, $resolution, []))->export_for_template($renderer);
        $this->assertNull($context->categoryname);

        $this->setUser($member);
        \local_unlistedcourses\category_access::reset_caches();
        $context = (new landing_page($course, $values, $resolution, []))->export_for_template($renderer);
        $this->assertSame('Restricted Category', $context->categoryname, 'Control: the cohort member sees the name.');

        // Control: a guest in an ordinary, listed category keeps the pre-existing behaviour.
        $listedcourse = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $listedvalues = landingfields::values($listedcourse->id);
        $this->setGuestUser();
        \local_unlistedcourses\category_access::reset_caches();
        $listedresolution = new resolution(resolution::STATE_VISITOR);
        $listedcontext = (new landing_page($listedcourse, $listedvalues, $listedresolution, [], true))
            ->export_for_template($renderer);
        $expected = \core_course_category::get((int) $listedcourse->category)->get_formatted_name();
        $this->assertSame($expected, $listedcontext->categoryname);
    }

    /**
     * The hero's style attribute is a CSS context, and the template's double stash does not protect it.
     *
     * This test asserts the sink rather than a guard: it is the reason hero_image_url() refuses a
     * url instead of trusting the template. The double stash does escape, but as HTML.
     *
     * A browser HTML-decodes an attribute value before the CSS parser reads it, so s() turning a
     * literal quote into `&#039;` hands the CSS engine a quote, not an entity. In a browser the
     * attribute rendered below applies the smuggled `background:red` and loses the hero image,
     * while the control url keeps its image and applies nothing else.
     *
     * The fixture is a url clean_param(PARAM_URL) hands back unchanged, so the test also shows
     * that PARAM_URL alone does not protect this sink.
     *
     * @return void
     */
    public function test_the_hero_style_attribute_is_a_css_context_the_double_stash_does_not_protect(): void {
        $this->resetAfterTest();

        $hostile = "https://example.com/a');background:red;x.png";
        $this->assertSame(
            $hostile,
            clean_param($hostile, PARAM_URL),
            'PARAM_URL already refused the fixture, so it says nothing about what the template does with one.'
        );

        $attribute = $this->hero_style_attribute($hostile);
        $this->assertStringContainsString('&#039;', $attribute, 'Mustache escaped it as HTML, which is the point.');
        $this->assertStringContainsString(
            "url('https://example.com/a');background:red;",
            html_entity_decode($attribute, ENT_QUOTES | ENT_HTML5),
            'The declaration survives its own url() once the attribute is decoded.'
        );

        // The control: the url the export really produces closes nothing and carries one declaration.
        $ordinary = 'https://example.com/pluginfile.php/31/course/overviewfiles/capa.jpg';
        $this->assertSame(
            "background-image: url('{$ordinary}');",
            html_entity_decode($this->hero_style_attribute($ordinary), ENT_QUOTES | ENT_HTML5)
        );
    }

    /**
     * A hero url which could escape that attribute is refused, and the hero simply has no image.
     *
     * The url is handed in through core's own course_image cache, which is the one seam a test can
     * reach: both wired sources build through the pluginfile url factory, whose path segments are
     * percent-encoded, so neither can produce such a url today. The guard is hardening rather than
     * a live hole - and it is the cache, not a source, that is poisoned here, so this test says
     * what the method does with a url it should never see rather than claiming one arrives.
     *
     * The control is not optional: without it an empty cache would answer null as well and the
     * refusal below would be proved by nothing at all.
     *
     * @return void
     */
    public function test_a_hero_url_that_could_escape_the_style_attribute_is_refused(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();

        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $contextid = \core\context\course::instance($course->id)->id;
        $values = landingfields::values($course->id);
        $resolution = resolver::resolve($course);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $cache = \cache::make('core', 'course_image');

        $cache->set($course->id, "/pluginfile.php/{$contextid}/course/overviewfiles/capa.jpg");
        $ordinary = (new landing_page($course, $values, $resolution, [], false))->export_for_template($renderer);
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/course/overviewfiles/capa.jpg",
            (string) $ordinary->heroimageurl,
            'Control: the cache really is where this branch reads the image from.'
        );

        $cache->set($course->id, "/pluginfile.php/{$contextid}/course/overviewfiles/a');background:red;x.png");
        $poisoned = (new landing_page($course, $values, $resolution, [], false))->export_for_template($renderer);
        $this->assertNull($poisoned->heroimageurl);
    }

    /**
     * The hero's style attribute as the template writes it for one url.
     *
     * Rendered from the template's own example context - the one context the mustache lint also
     * validates - with the hero url replaced, so the attribute measured here is the attribute the
     * page really emits.
     *
     * @param string $url The value of heroimageurl.
     * @return string The attribute's value, exactly as it stands in the rendered HTML.
     */
    private function hero_style_attribute(string $url): string {
        global $PAGE;

        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/landing_page.mustache');
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $example));
        $context = json_decode($example[1], true);
        $this->assertIsArray($context, 'The example context of landing_page.mustache is not valid JSON.');
        $context['heroimageurl'] = $url;

        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => 42]);
        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/landing_page', $context);
        $found = preg_match('/<div class="dimensions-landing__hero-media" style="([^"]*)"/', $html, $matches);
        $this->assertSame(1, $found, 'The hero media element is not where this test expects it.');

        return $matches[1];
    }
    /**
     * Each method carries its own seats and deadline, and the mobile bar repeats the primary one's deadline.
     */
    public function test_each_method_says_its_seats_and_deadline(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $values = landingfields::values($course->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');

        $deadline = time() + 30 * DAYSECS;
        $first = method::for_self((object) [
            'id' => 55, 'enrol' => 'self', 'password' => '', 'name' => '', 'customint3' => 40, 'enrolenddate' => $deadline,
        ], false);
        $second = method::for_self((object) [
            'id' => 56, 'enrol' => 'self', 'password' => '', 'name' => '', 'customint3' => 0, 'enrolenddate' => 0,
        ], false);
        $context = (new landing_page($course, $values, new resolution(resolution::STATE_OPEN, [$first, $second]), []))
            ->export_for_template($renderer);

        $expected = \theme_dimensions\local\landing\offer::export($first->instance, time());
        $this->assertSame($expected, $context->methods[0]['offer']);
        $seats = get_string('landing_offerseats', 'theme_dimensions', 40);
        $this->assertSame($seats, $context->methods[0]['offer']['seats']);
        $this->assertNull($context->methods[1]['offer']);
        $this->assertSame($context->methods[0]['offer']['untilshort'], $context->bar['note']);

        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString(get_string('landing_offerseats', 'theme_dimensions', 40), $html);
        $this->assertStringContainsString($context->methods[0]['offer']['until'], $html);

        // An enrolled reader's bar leads into the course and says nothing of a deadline.
        $enrolled = (new landing_page($course, $values, new resolution(resolution::STATE_ENROLLED, [$first]), []))
            ->export_for_template($renderer);
        $this->assertNull($enrolled->bar['note']);
    }

    /**
     * A visitor is told each open method's lines above the login button, named when there are several.
     */
    public function test_a_visitor_is_told_the_open_methods_by_name(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->prepare_fields();
        $course = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_HYBRID);
        $values = landingfields::values($course->id);
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');

        $deadline = time() + 30 * DAYSECS;
        $inperson = (object) [
            'id' => 61, 'enrol' => 'self', 'name' => 'Presencial', 'customint3' => 120, 'enrolenddate' => $deadline,
        ];
        $online = (object) [
            'id' => 62, 'enrol' => 'self', 'name' => 'Online, ao vivo', 'customint3' => 0, 'enrolenddate' => $deadline,
        ];

        $two = (new landing_page($course, $values, new resolution(resolution::STATE_VISITOR, offers: [$inperson, $online]), []))
            ->export_for_template($renderer);
        $this->assertTrue($two->hasoffers);
        $this->assertSame(['Presencial', 'Online, ao vivo'], array_column($two->offers, 'name'));
        $this->assertSame($two->offers[0]['untilshort'], $two->bar['note']);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $two);
        $this->assertStringContainsString('Online, ao vivo', $html);
        $this->assertStringContainsString(get_string('landing_offerseats', 'theme_dimensions', 120), $html);

        // One method needs no name: the card already says what it is.
        $one = (new landing_page($course, $values, new resolution(resolution::STATE_VISITOR, offers: [$inperson]), []))
            ->export_for_template($renderer);
        $this->assertSame([null], array_column($one->offers, 'name'));

        // A logged-in reader is never handed the visitor's list.
        $open = (new landing_page($course, $values, new resolution(resolution::STATE_OPEN, offers: [$inperson]), []))
            ->export_for_template($renderer);
        $this->assertFalse($open->hasoffers);
    }

    /**
     * A visitor whom no login would take anywhere is led in as a guest, not to the login.
     *
     * A learner already enrolled still finds the login, as a quiet link under the guest note.
     * The control is the same guest method on a course a login could open: the login button and its
     * note lead, guest access stays secondary and the bar is the login's.
     */
    public function test_a_guest_only_course_leads_a_visitor_with_access_as_guest(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $guest = method::for_guest((object) ['id' => 71, 'enrol' => 'guest', 'password' => '', 'name' => ''], false);
        $guestbutton = get_string('landing_guestbutton', 'theme_dimensions');
        $loginbutton = get_string('landing_loginbutton', 'theme_dimensions');
        $guestnote = get_string('landing_guestnote', 'theme_dimensions');
        $loginnote = get_string('landing_loginnote', 'theme_dimensions');
        $enrolledlogin = get_string('landing_enrolledlogin', 'theme_dimensions');
        $primaryguest = '/dimensions-landing__method--guest">\s*<a class="btn btn-primary dimensions-landing__btn"/';

        [$context, $renderer] = $this->export_page($course, new resolution(resolution::STATE_VISITOR, [$guest], loginwayin: false));
        $this->assertFalse($context->showlogin);
        $this->assertTrue($context->isguestaccess);
        $this->assertFalse($context->showclosed);
        $this->assertTrue($context->methods[0]['primary']);
        $this->assertTrue($context->methods[0]['guestnote']);
        $this->assertSame($context->methods[0]['guesturl'], $context->bar['url']);
        $this->assertSame($guestbutton, $context->bar['label']);
        $this->assertNull($context->bar['note']);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringNotContainsString($loginbutton, $html);
        $this->assertStringNotContainsString($loginnote, $html);
        $this->assertStringContainsString(get_string('landing_accessheading', 'theme_dimensions') . '</h2>', $html);
        $this->assertStringContainsString($guestnote, $html);
        $this->assertStringContainsString('<a href="' . s(get_login_url()) . '">' . $enrolledlogin . '</a>', $html);
        $this->assertMatchesRegularExpression($primaryguest, $html);

        [$control, $renderer] = $this->export_page($course, new resolution(resolution::STATE_VISITOR, [$guest]));
        $this->assertTrue($control->showlogin);
        $this->assertFalse($control->isguestaccess);
        $this->assertFalse($control->methods[0]['primary'], 'Guest access stays secondary beside the login.');
        $this->assertFalse($control->methods[0]['guestnote']);
        $this->assertSame(get_login_url(), $control->bar['url']);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $control);
        $this->assertStringContainsString($loginbutton, $html);
        $this->assertStringContainsString($loginnote, $html);
        $this->assertStringContainsString(get_string('landing_enrolheading', 'theme_dimensions') . '</h2>', $html);
        $this->assertStringNotContainsString($guestnote, $html);
        $this->assertStringNotContainsString($enrolledlogin, $html);
        $this->assertDoesNotMatchRegularExpression($primaryguest, $html);
        $this->assertMatchesRegularExpression('/dimensions-landing__method--guest">\s*<a class="btn btn-outline-secondary/', $html);
    }

    /**
     * A visitor whom no login would take anywhere, on a course without guest access, is told enrolment is closed.
     */
    public function test_a_visitor_with_no_way_in_is_told_enrolment_is_closed(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);

        [$context, $renderer] = $this->export_page($course, new resolution(resolution::STATE_VISITOR, loginwayin: false));
        $this->assertTrue($context->isvisitor);
        $this->assertFalse($context->showlogin);
        $this->assertFalse($context->isguestaccess);
        $this->assertTrue($context->showclosed);
        $this->assertNull($context->bar);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString('<p class="dimensions-landing__closed">', $html);
        $this->assertStringNotContainsString(get_string('landing_loginbutton', 'theme_dimensions'), $html);

        // Control: a visitor a login could take somewhere is asked to log in, and is not told it is closed.
        [$control] = $this->export_page($course, new resolution(resolution::STATE_VISITOR));
        $this->assertFalse($control->showclosed);
        $this->assertSame(get_login_url(), $control->bar['url']);
    }

    /**
     * On an open card whose only method is guest access, that access is the primary action and the bar's.
     *
     * The control is the same card with a self-enrolment beside it: the enrolment is primary, guest
     * access secondary, and the bar the anchor down to the card.
     */
    public function test_guest_access_is_primary_on_an_open_card_with_nothing_else(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $guest = method::for_guest((object) ['id' => 71, 'enrol' => 'guest', 'password' => '', 'name' => ''], false);
        $self = method::for_self((object) ['id' => 55, 'enrol' => 'self', 'password' => '', 'name' => ''], false);

        [$context, $renderer] = $this->export_page($course, new resolution(resolution::STATE_OPEN, [$guest]));
        $this->assertTrue($context->isopen);
        $this->assertTrue($context->methods[0]['primary']);
        $this->assertFalse($context->methods[0]['guestnote'], 'The note is the visitor\'s.');
        $this->assertSame($context->methods[0]['guesturl'], $context->bar['url']);
        $this->assertSame(get_string('landing_guestbutton', 'theme_dimensions'), $context->bar['label']);
        $this->assertNull($context->bar['note']);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $primaryguest = '/dimensions-landing__method--guest">\s*<a class="btn btn-primary dimensions-landing__btn"/';
        $this->assertMatchesRegularExpression($primaryguest, $html);

        [$control] = $this->export_page($course, new resolution(resolution::STATE_OPEN, [$self, $guest]));
        $this->assertTrue($control->methods[0]['primary']);
        $this->assertFalse($control->methods[1]['primary']);
        $this->assertSame('#dimensions-landing-card', $control->bar['url']);
    }

    /**
     * A visitor's guest access button leads to the guest entry where the course offers it, and to the course otherwise.
     *
     * The visitor would meet only the login page on the course page; the guest entry logs them in as
     * the guest. Two controls: a reader logged in keeps the course page, and so does a visitor of a
     * course whose guest access instance is not enabled.
     */
    public function test_a_visitors_guest_button_leads_to_the_guest_entry_where_it_is_offered(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        // The site must admit the guest account, or the entry is withdrawn (guestaccess::available()).
        set_config('autologinguests', 1);
        $CFG->routerconfigured = false;
        $this->prepare_fields();
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'guest'], '*', MUST_EXIST);
        $guest = method::for_guest($instance, false);
        $resolution = new resolution(resolution::STATE_VISITOR, [$guest], loginwayin: false);
        $courseurl = (new \core\url('/course/view.php', ['id' => $course->id]))->out(false);

        // The generator leaves the instance disabled: no guest entry is offered yet.
        $this->setUser(null);
        [$disabled] = $this->export_page($course, $resolution);
        $this->assertSame($courseurl, $disabled->methods[0]['guesturl']);

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['id' => $instance->id]);
        [$visitor] = $this->export_page($course, $resolution);
        $this->assertSame(links::guest_page((int) $course->id)->out(false), $visitor->methods[0]['guesturl']);
        $this->assertSame($visitor->methods[0]['guesturl'], $visitor->bar['url'], 'The bar follows the button.');

        $this->setUser($this->getDataGenerator()->create_user());
        [$loggedin] = $this->export_page($course, new resolution(resolution::STATE_OPEN, [$guest]));
        $this->assertSame($courseurl, $loggedin->methods[0]['guesturl']);
    }

    /**
     * Export one page of a course on a fresh renderer.
     *
     * @param \stdClass $course The course record.
     * @param resolution $resolution The resolved enrolment state.
     * @return array The exported context and the renderer, in that order.
     */
    private function export_page(\stdClass $course, resolution $resolution): array {
        global $PAGE;
        $PAGE->set_url('/theme/dimensions/landing.php', ['id' => $course->id]);
        $renderer = $PAGE->get_renderer('core');
        $context = (new landing_page($course, landingfields::values($course->id), $resolution, []))
            ->export_for_template($renderer);
        return [$context, $renderer];
    }

    /**
     * Render an exported page with four badges in its hero, as the badge grid of round 3 draws them.
     *
     * The course needs no picture: the grid is the template's, and what is proved here is that every
     * new hero block renders beside four badges, in the same hero text column.
     *
     * @param \stdClass $context The exported context.
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The page's HTML.
     */
    private function render_with_four_badges(\stdClass $context, \core\output\renderer_base $renderer): string {
        $context->badges = [];
        foreach ([1, 2, 3, 4] as $n) {
            $context->badges[] = ['url' => "https://example.com/crest-{$n}.png", 'alt' => "crest {$n}"];
        }
        $context->hasbadges = true;
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString('dimensions-landing__hero-content--badges', $html);
        $this->assertSame(4, substr_count($html, 'class="dimensions-landing__badge"'));
        return $html;
    }

    /**
     * The card's row with this label, or null.
     *
     * @param \stdClass $context The exported context.
     * @param string $label The row's label.
     * @return array|null The row.
     */
    private function fact_row(\stdClass $context, string $label): ?array {
        foreach ($context->facts as $row) {
            if ($row['label'] === $label) {
                return $row;
            }
        }
        return null;
    }

    /**
     * A hybrid event's hero is the double ticket (D1): the date, then the two ways to take part.
     *
     * The ticket replaces the online event's date block, which would call the whole event a broadcast;
     * the online event is the control, and so is a hybrid course, which has a composition instead. The
     * ticket renders beside four badges, and the card names the place as an in-person event's does.
     */
    public function test_a_hybrid_event_hero_is_the_double_ticket(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $component = 'theme_dimensions';
        $start = time() + (10 * DAYSECS);
        $place = [
            'customfield_' . landingfields::SHORTNAME_CITY => 'São Paulo',
            'customfield_' . landingfields::SHORTNAME_VENUE => 'Teatro da Reitoria',
            'customfield_' . landingfields::SHORTNAME_ADDRESS => 'Rua XV de Novembro, 1299',
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 8,
            'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD => 6,
        ];
        $event = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_HYBRID, [
            'startdate' => $start,
            'enddate' => $start + (2 * HOURSECS),
        ], $place);
        [$context, $renderer] = $this->export_page($event, new resolution(resolution::STATE_CLOSED));

        $this->assertNull($context->herodate, 'The broadcast is one of two ways, not the whole event.');
        $this->assertNull($context->heroplace);
        $this->assertNull($context->herocomp, 'An event has no composition, whatever hours it holds.');
        $ticket = $context->heroticket;
        $this->assertNotNull($ticket);
        $this->assertSame(userdate($start, get_string('landing_fmtdatebig', $component)), $ticket['datebig']);
        $this->assertStringStartsWith(userdate($start, get_string('landing_fmtweekday', $component)) . ' · ', $ticket['sub']);
        $this->assertSame(get_string('landing_daysleft', $component, 10), $ticket['countdown']);
        $this->assertSame(get_string('landing_ticketsitein', $component, 'São Paulo'), $ticket['site']);
        $this->assertSame('Teatro da Reitoria', $ticket['venue']);
        $this->assertSame(get_string('landing_ticketlive', $component), $ticket['live']);

        $names = landingfields::values($event->id)->names;
        $this->assertSame('Teatro da Reitoria', $this->fact_row($context, $names[landingfields::SHORTNAME_VENUE])['value']);
        $this->assertSame('São Paulo', $this->fact_row($context, $names[landingfields::SHORTNAME_CITY])['value']);
        $this->assertSame(
            'Rua XV de Novembro, 1299',
            $this->fact_row($context, $names[landingfields::SHORTNAME_ADDRESS])['value'],
            'An event is somewhere on one date: its address is told.'
        );

        $html = $this->render_with_four_badges($context, $renderer);
        $this->assertStringContainsString('class="dimensions-landing__ticket"', $html);
        $this->assertStringContainsString(s(get_string('landing_ticketsitein', $component, 'São Paulo')), $html);
        $this->assertStringContainsString(s(get_string('landing_ticketlivesub', $component)), $html);

        // Without a city the site way still reads, unnamed.
        $nocity = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_HYBRID, [
            'startdate' => $start,
        ]);
        [$nocitycontext] = $this->export_page($nocity, new resolution(resolution::STATE_CLOSED));
        $this->assertSame(get_string('landing_ticketsite', $component), $nocitycontext->heroticket['site']);

        // Control: an online event keeps its date block and has no ticket.
        $online = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_ONLINE, [
            'startdate' => $start,
        ]);
        [$onlinecontext] = $this->export_page($online, new resolution(resolution::STATE_CLOSED));
        $this->assertNotNull($onlinecontext->herodate);
        $this->assertNull($onlinecontext->heroticket);

        // Control: a hybrid course has no ticket.
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_HYBRID, [
            'startdate' => $start,
        ], $place);
        [$coursecontext] = $this->export_page($course, new resolution(resolution::STATE_CLOSED));
        $this->assertNull($coursecontext->heroticket);
    }

    /**
     * A hybrid course's hero is the composition (D2): the workload as one bar, online and in person.
     *
     * The bar's widths are printed into a style attribute, a CSS context, so they are integers made by
     * the page and the attribute can only read "width: N%". The composition renders beside four badges.
     */
    public function test_a_hybrid_course_hero_is_the_composition(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $component = 'theme_dimensions';
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_HYBRID, [], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 60,
            'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD => 24,
            'customfield_' . landingfields::SHORTNAME_MEETINGS => '2 encontros aos sábados',
            'customfield_' . landingfields::SHORTNAME_CITY => 'Londrina',
        ]);
        [$context, $renderer] = $this->export_page($course, new resolution(resolution::STATE_CLOSED));

        $comp = $context->herocomp;
        $this->assertNotNull($comp);
        $this->assertNull($context->heroplace, 'The composition carries the city; no place block beside it.');
        $this->assertNull($context->heroticket);
        $this->assertSame(get_string('landing_hours', $component, '60'), $comp['total']);
        $this->assertSame(get_string('landing_componline', $component, '36'), $comp['onlinelabel']);
        $this->assertSame(get_string('landing_compinperson', $component, '24'), $comp['inpersonlabel']);
        $this->assertIsInt($comp['onlinepct']);
        $this->assertIsInt($comp['inpersonpct']);
        $this->assertSame(60, $comp['onlinepct']);
        $this->assertSame(100, $comp['onlinepct'] + $comp['inpersonpct']);
        $meetings = (object) ['meetings' => '2 encontros aos sábados', 'city' => 'Londrina'];
        $this->assertSame(get_string('landing_compmeetingsin', $component, $meetings), $comp['meetings']);
        $this->assertSame(
            get_string('landing_comparia', $component, (object) ['online' => '36', 'inperson' => '24']),
            $comp['arialabel']
        );

        $html = $this->render_with_four_badges($context, $renderer);
        $this->assertStringContainsString('class="dimensions-landing__comp"', $html);
        $found = preg_match_all('/class="dimensions-landing__comp-(?:online|inperson)" style="([^"]*)"/', $html, $matches);
        $this->assertSame(2, $found, 'One style attribute per segment of the bar.');
        foreach ($matches[1] as $style) {
            $this->assertMatchesRegularExpression('/^width: \d{1,3}%$/', $style);
        }
        $this->assertSame(['width: 60%', 'width: 40%'], $matches[1]);
    }

    /**
     * Without both workloads a hybrid course has no composition, and its hero falls back to the place.
     *
     * An in-person figure above the total, which the course form refuses and a restore can still write,
     * is no split either: drawn, it would be a negative online share.
     */
    public function test_a_hybrid_course_without_its_split_falls_back_to_the_place(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $fields = [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 60,
            'customfield_' . landingfields::SHORTNAME_MEETINGS => '2 encontros aos sábados',
            'customfield_' . landingfields::SHORTNAME_CITY => 'Londrina',
            'customfield_' . landingfields::SHORTNAME_VENUE => 'Sede regional',
        ];
        $nohours = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_HYBRID, [], $fields);
        [$context] = $this->export_page($nohours, new resolution(resolution::STATE_CLOSED));
        $this->assertNull($context->herocomp);
        $this->assertNotNull($context->heroplace);
        $this->assertSame('Londrina', $context->heroplace['city']);
        $this->assertSame('Sede regional', $context->heroplace['venue']);
        $this->assertSame('2 encontros aos sábados', $context->heroplace['meetings']);
        $this->assertFalse($context->heroplace['big']);

        $toomany = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_HYBRID, [], [
            'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD => 80,
        ] + $fields);
        [$toomanycontext] = $this->export_page($toomany, new resolution(resolution::STATE_CLOSED));
        $this->assertNull($toomanycontext->herocomp, 'Eighty hours in person out of sixty is no split.');
        $this->assertNotNull($toomanycontext->heroplace);
    }

    /**
     * The chips add the study format, and the live classes as a chip of their own, drawn only when there are some.
     */
    public function test_the_chips_carry_the_format_and_the_live_classes(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $component = 'theme_dimensions';
        $tutored = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSES => 1,
        ]);
        [$context, $renderer] = $this->export_page($tutored, new resolution(resolution::STATE_CLOSED));
        $this->assertContains(get_string('landingfield_format_tutored', $component), $context->chips);
        $this->assertTrue($context->haslivechip);
        $this->assertTrue($context->haschips);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString('dimensions-landing__chip--live', $html);
        $this->assertStringContainsString(get_string('landing_livechip', $component), $html);

        // Control: a self-paced course says so, and has no live chip.
        $selfpaced = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_SELFPACED,
        ]);
        [$selfcontext] = $this->export_page($selfpaced, new resolution(resolution::STATE_CLOSED));
        $this->assertContains(get_string('landingfield_format_selfpaced', $component), $selfcontext->chips);
        $this->assertFalse($selfcontext->haslivechip);
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $selfcontext);
        $this->assertStringNotContainsString('dimensions-landing__chip--live', $html);
    }

    /**
     * The card says the format (with "at your own pace" under a self-paced one), a tutored course's start
     * and its live classes (D8).
     *
     * The self-paced course carries a start date too, so the start row is held to the tutored course by
     * the format and not by a missing date.
     */
    public function test_the_card_rows_say_the_format_the_start_and_the_live_classes(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $component = 'theme_dimensions';
        $start = time() + (20 * DAYSECS);
        $startlabel = get_string('landing_factstart', $component);

        $selfpaced = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [
            'startdate' => $start,
        ], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_SELFPACED,
        ]);
        $names = landingfields::values($selfpaced->id)->names;
        $formatlabel = $names[landingfields::SHORTNAME_FORMAT];
        $livelabel = $names[landingfields::SHORTNAME_LIVECLASSES];
        [$context, $renderer] = $this->export_page($selfpaced, new resolution(resolution::STATE_CLOSED));
        $format = $this->fact_row($context, $formatlabel);
        $this->assertSame(get_string('landingfield_format_selfpaced', $component), $format['value']);
        $this->assertSame(get_string('landing_selfpacednote', $component), $format['note']);
        $this->assertNull($this->fact_row($context, $startlabel), 'A self-paced course starts when the learner does.');
        $this->assertNull($this->fact_row($context, $livelabel));
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString(
            '<small class="dimensions-landing__factnote">' . get_string('landing_selfpacednote', $component) . '</small>',
            $html
        );

        $tutored = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [
            'startdate' => $start,
        ], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSES => 1,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSESWHEN => 'Quartas, 19h',
        ]);
        [$tutoredcontext] = $this->export_page($tutored, new resolution(resolution::STATE_CLOSED));
        $format = $this->fact_row($tutoredcontext, $formatlabel);
        $this->assertSame(get_string('landingfield_format_tutored', $component), $format['value']);
        $this->assertNull($format['note']);
        $this->assertSame(
            userdate($start, get_string('strftimedatefullshort', 'langconfig')),
            $this->fact_row($tutoredcontext, $startlabel)['value']
        );
        $this->assertSame('Quartas, 19h', $this->fact_row($tutoredcontext, $livelabel)['value']);

        // Live classes with no "when" still say there are some.
        $unsaid = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSES => 1,
        ]);
        [$unsaidcontext] = $this->export_page($unsaid, new resolution(resolution::STATE_CLOSED));
        $this->assertSame(get_string('yes'), $this->fact_row($unsaidcontext, $livelabel)['value']);
    }

    /**
     * A hybrid course's card names its meetings, its place and the workload's split, but no street address.
     *
     * The address is for whoever goes somewhere once, on a date: the in-person event of the first test
     * shows it, a hybrid course's recurring meetings do not.
     */
    public function test_a_hybrid_course_card_names_the_meetings_the_place_and_the_split(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $component = 'theme_dimensions';
        $course = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_HYBRID, [], [
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 60,
            'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD => 24,
            'customfield_' . landingfields::SHORTNAME_MEETINGS => '2 encontros aos sábados',
            'customfield_' . landingfields::SHORTNAME_CITY => 'Londrina',
            'customfield_' . landingfields::SHORTNAME_VENUE => 'Sede regional',
            'customfield_' . landingfields::SHORTNAME_ADDRESS => 'Avenida Higienópolis, 100',
        ]);
        $names = landingfields::values($course->id)->names;
        [$context] = $this->export_page($course, new resolution(resolution::STATE_CLOSED));

        $meetings = $this->fact_row($context, $names[landingfields::SHORTNAME_MEETINGS]);
        $this->assertSame('2 encontros aos sábados', $meetings['value']);
        $workload = $this->fact_row($context, $names[landingfields::SHORTNAME_WORKLOAD]);
        $this->assertSame(get_string('landing_hours', $component, '60'), $workload['value']);
        $this->assertSame(
            get_string('landing_workloadsplit', $component, (object) ['online' => '36', 'inperson' => '24']),
            $workload['note']
        );
        $this->assertSame('Sede regional', $this->fact_row($context, $names[landingfields::SHORTNAME_VENUE])['value']);
        $this->assertSame('Londrina', $this->fact_row($context, $names[landingfields::SHORTNAME_CITY])['value']);
        $this->assertNull($this->fact_row($context, $names[landingfields::SHORTNAME_ADDRESS]));

        // Control: an online course's workload has no split under it.
        $online = $this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE, [], [
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 60,
        ]);
        [$onlinecontext] = $this->export_page($online, new resolution(resolution::STATE_CLOSED));
        $this->assertNull($this->fact_row($onlinecontext, $names[landingfields::SHORTNAME_WORKLOAD])['note']);
    }

    /**
     * A hybrid event offering two methods asks how to take part, and names each track after its instance (D3).
     *
     * The controls hold each half of the rule apart: one method on the same event, two methods on an
     * online course, on an online event and on a hybrid course all keep the plain heading and no names.
     */
    public function test_a_hybrid_event_with_two_methods_offers_its_tracks(): void {
        $this->resetAfterTest();
        $this->prepare_fields();
        $component = 'theme_dimensions';
        $onsite = method::for_self((object) ['id' => 61, 'enrol' => 'self', 'password' => '', 'name' => 'Presencial'], false);
        $unnamed = method::for_self((object) ['id' => 62, 'enrol' => 'self', 'password' => '', 'name' => ''], false);
        $two = new resolution(resolution::STATE_OPEN, [$onsite, $unnamed]);

        $event = $this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_HYBRID);
        [$context, $renderer] = $this->export_page($event, $two);
        $this->assertSame(get_string('landing_tracksheading', $component), $context->tracksheading);
        $this->assertSame(
            ['Presencial', get_string('pluginname', 'enrol_self')],
            array_column($context->methods, 'trackname'),
            'The instance\'s own name, or the plugin\'s when it has none.'
        );
        $html = $renderer->render_from_template('theme_dimensions/landing_page', $context);
        $this->assertStringContainsString(get_string('landing_tracksheading', $component), $html);
        $this->assertSame(2, substr_count($html, 'dimensions-landing__methodentry dimensions-landing__track"'));
        $this->assertStringContainsString('<h3 class="dimensions-landing__trackname">Presencial</h3>', $html);

        $controls = [
            'one method' => [$event, new resolution(resolution::STATE_OPEN, [$onsite])],
            'an online course' => [$this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_ONLINE), $two],
            'an online event' => [$this->create_offering(landingfields::MODEL_EVENT, landingfields::MODALITY_ONLINE), $two],
            'a hybrid course' => [$this->create_offering(landingfields::MODEL_COURSE, landingfields::MODALITY_HYBRID), $two],
        ];
        foreach ($controls as $case => [$course, $resolution]) {
            [$control] = $this->export_page($course, $resolution);
            $this->assertNull($control->tracksheading, "Control: {$case}.");
            $this->assertSame([null], array_unique(array_column($control->methods, 'trackname')), "Control: {$case}.");
            $html = $renderer->render_from_template('theme_dimensions/landing_page', $control);
            $this->assertStringNotContainsString('dimensions-landing__trackname', $html, "Control: {$case}.");
        }
    }
}
