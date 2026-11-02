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
 * Theme Dimensions - The web service that fills a course's "More information" dialogue
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_course\customfield\course_handler;
use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\dialogue;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests for the dialogue's content service: its order, its one refusal, what it answers and what it costs.
 *
 * Every refusal is asserted beside a course the same call answers, because a function that answers nothing would
 * pass every refusal alone. The order is observed where the answer cannot see it: the guest's refusal costs the same
 * reads whatever the id (nothing is looked up before it), and a refused course leaves core's contacts cache as it
 * found it (no content is built before the course is admitted).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_course_dialogue::class)]
#[CoversClass(dialogue::class)]
final class get_course_dialogue_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_get_course_dialogue';

    /**
     * Call the function the way the client does.
     *
     * @param int $courseid The course.
     * @return array The framework's response.
     */
    private function call(int $courseid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::FUNCTION, ['courseid' => $courseid], false);
    }

    /**
     * The answer of an admitted course.
     *
     * @param int $courseid The course.
     * @return array The content.
     */
    private function answer(int $courseid): array {
        $response = $this->call($courseid);
        $this->assertFalse($response['error'], 'The function refused: ' . ($response['exception']->message ?? ''));
        return $response['data'];
    }

    /**
     * The settings every test shares: the landing page fields, and the editing teacher as the course contact.
     *
     * @return void
     */
    private function prepare(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->coursecontact = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * Whether core's contacts cache holds an entry for a course: something built the course's content.
     *
     * @param int $courseid The course.
     * @return bool
     */
    private static function contacts_cached(int $courseid): bool {
        return \cache::make('core', 'coursecontacts')->get($courseid) !== false;
    }

    /**
     * Every miss answers the one refusal, with one message, and builds nothing; an admitted course is answered.
     *
     * The site course (core's question admits it outright), an id nothing holds, a course core hides, a course the
     * plugin withholds and a course under a category the plugin withholds. The contacts cache is emptied before each
     * call and read after it: a refusal must find it as empty as it was, and the admitted course is the control that
     * building the content does fill it.
     */
    public function test_every_miss_is_the_one_refusal_and_builds_nothing(): void {
        $this->prepare();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $teacher = $generator->create_user();
        $make = function (array $record = []) use ($generator, $category, $teacher): int {
            $course = $generator->create_course(['category' => $category->id] + $record);
            $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
            return (int) $course->id;
        };
        $admitted = $make();
        $hidden = $make(['visible' => 0]);
        $unlisted = $make();
        $this->set_course_state($unlisted, discoverability::STATE_UNLISTED);
        $withheldcategory = $generator->create_category();
        $incategory = (int) $generator->create_course(['category' => $withheldcategory->id])->id;
        $generator->enrol_user($teacher->id, $incategory, 'editingteacher');
        $this->set_category_state((int) $withheldcategory->id, category_discoverability::STATE_UNLISTED);
        $missing = $incategory + 1000;
        $this->become($generator->create_user());

        // The control first: without it every refusal below passes against a function that answers nothing.
        $contacts = \cache::make('core', 'coursecontacts');
        $contacts->purge();
        $this->assertNotSame('', $this->answer($admitted)['learnlabel']);
        $this->assertTrue(self::contacts_cached($admitted), 'Control: building the content reads the contacts.');

        $messages = [];
        $misses = [
            'the site course' => SITEID,
            'an id nothing holds' => $missing,
            'a course core hides' => $hidden,
            'a course the plugin withholds' => $unlisted,
            'a course under a withheld category' => $incategory,
        ];
        foreach ($misses as $case => $courseid) {
            $contacts->purge();
            $response = $this->call((int) $courseid);
            $this->assertTrue($response['error'], "{$case} was answered.");
            $this->assertSame(guard::ERROR_COURSE, $response['exception']->errorcode, $case);
            $this->assertFalse(self::contacts_cached((int) $courseid), "{$case}: content was built before the refusal.");
            $messages[$response['exception']->message] = true;
        }
        $this->assertCount(1, $messages, 'One message for every miss: the service is no oracle over course ids.');
    }

    /**
     * The guest account is refused before anything is looked up: the same refusal, and the same reads, whatever the id.
     *
     * A course the guest may see and an id nothing holds would cost different reads if either were looked up first.
     * A real account asking for the same course is the control that the course is answerable.
     */
    public function test_the_guest_is_refused_before_any_lookup(): void {
        $this->prepare();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = (int) $generator->create_course(['category' => $category->id])->id;
        $missing = $course + 1000;

        $this->setGuestUser();
        $responses = [];
        $reads = [];
        foreach ([$course, $missing] as $courseid) {
            $this->call($courseid);
            $reads[$courseid] = $this->reads(function () use ($courseid, &$responses): void {
                $responses[$courseid] = $this->call($courseid);
            });
        }
        foreach ($responses as $courseid => $response) {
            $this->assertTrue($response['error'], "The guest was answered for {$courseid}.");
            $this->assertSame(guard::ERROR_COURSE, $response['exception']->errorcode);
        }
        $this->assertSame($reads[$course], $reads[$missing], 'The guest\'s refusal looked the course up first.');

        // Control: the course is one a real account is answered for.
        $this->become($generator->create_user());
        $this->assertNotSame('', $this->answer($course)['factslabel']);
    }

    /**
     * Somebody not logged in is refused by the login itself: the service is a session's alone (decision V1).
     */
    public function test_a_visitor_is_refused(): void {
        $this->prepare();
        $generator = self::getDataGenerator();
        $course = (int) $generator->create_course(['category' => $generator->create_category()->id])->id;
        $this->become(null);
        $response = $this->call($course);
        $this->assertTrue($response['error']);
        $this->become($generator->create_user());
        $this->assertFalse($this->call($course)['error'], 'Control: a session is answered.');
    }

    /**
     * The content is what the card's details showed for a course with every fact, and nothing about the page's decision.
     *
     * The summary decoded and shortened (an entity that decodes to a bare "<" included, which a PARAM_TEXT return would
     * refuse for the whole answer), the four tiles in the card's order with their icons - the workload, the modality,
     * the place (the venue then the city) and the contacts - and the programme's first five lines. An event states its
     * date line instead of the workload. No key names a state, a button or an address.
     */
    public function test_the_content_is_the_cards_details_and_nothing_of_the_pages_decision(): void {
        $this->prepare();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $teacher = $generator->create_user(['firstname' => 'Tereza', 'lastname' => 'Contato']);
        $course = $generator->create_course([
            'category' => $category->id,
            'summary' => '<p>Primeiros socorros &amp; resgate.</p><p>Eu &lt;3 brigadas.</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 36,
            'customfield_' . landingfields::SHORTNAME_CITY => 'São Paulo',
            'customfield_' . landingfields::SHORTNAME_VENUE => 'Sede & Anexo',
            'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                'text' => '<p>Um</p><p>Dois</p><ul><li>Três</li><li>Quatro</li><li>Cinco</li><li>Seis</li></ul>',
                'format' => FORMAT_HTML,
            ],
        ]);
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $event = $generator->create_course([
            'category' => $category->id,
            'startdate' => make_timestamp(2026, 11, 12, 9),
            'enddate' => make_timestamp(2026, 11, 13, 18),
        ]);
        course_handler::create()->instance_form_save((object) [
            'id' => $event->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 8,
        ]);
        $this->become($generator->create_user());

        $content = $this->answer((int) $course->id);
        $this->assertSame(
            ['excerpt', 'hasexcerpt', 'facts', 'hasfacts', 'factslabel', 'learn', 'haslearn', 'learnlabel'],
            array_keys($content),
            'Content only: no state, no button, no address.'
        );
        $this->assertSame('Primeiros socorros & resgate. Eu <3 brigadas.', $content['excerpt']);
        $this->assertTrue($content['hasexcerpt']);
        $names = landingfields::values((int) $course->id)->names;
        $context = \core\context\course::instance((int) $course->id);
        $this->assertSame([
            [
                'label' => $names[landingfields::SHORTNAME_WORKLOAD],
                'value' => get_string('landing_hours', 'theme_dimensions', '36'),
                'icon' => 'fa-clock',
            ],
            [
                'label' => $names[landingfields::SHORTNAME_MODALITY],
                'value' => landingfields::option_label(landingfields::SHORTNAME_MODALITY, landingfields::MODALITY_INPERSON),
                'icon' => 'fa-display',
            ],
            [
                'label' => $names[landingfields::SHORTNAME_CITY],
                'value' => 'Sede & Anexo · São Paulo',
                'icon' => 'fa-location-dot',
            ],
            [
                'label' => get_string('category_contact', 'theme_dimensions'),
                'value' => 'Tereza Contato (' . role_get_name($this->editingteacher(), $context, ROLENAME_ALIAS) . ')',
                'icon' => 'fa-user',
            ],
        ], $content['facts']);
        $this->assertTrue($content['hasfacts']);
        $this->assertSame(get_string('category_modal_facts', 'theme_dimensions'), $content['factslabel']);
        $this->assertSame(['Um', 'Dois', 'Três', 'Quatro', 'Cinco'], $content['learn'], 'The first five lines.');
        $this->assertSame(dialogue::LEARN_LINES, count($content['learn']));
        $this->assertSame(get_string('category_learn', 'theme_dimensions'), $content['learnlabel']);

        $eventcontent = $this->answer((int) $event->id);
        $format = get_string('strftimedatefullshort', 'langconfig');
        $this->assertSame([
            'label' => get_string('date'),
            'value' => userdate((int) $event->startdate, $format) . ' – ' . userdate((int) $event->enddate, $format),
            'icon' => 'fa-calendar-day',
        ], $eventcontent['facts'][0], 'An event states its dates, not its workload.');
        $this->assertNotContains('fa-clock', array_column($eventcontent['facts'], 'icon'));
    }

    /**
     * The editing teacher's role record, as the contacts' role names are spelled from it.
     *
     * @return \stdClass
     */
    private function editingteacher(): \stdClass {
        global $DB;
        return $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
    }

    /**
     * One course's content costs the same statements whatever it holds: three contacts or one, six lines or two.
     *
     * The reads are one course's: its landing page values in one statement and its contacts in one (core's cache is
     * emptied inside the meter, so each measurement pays for them), never one per contact or per line.
     */
    public function test_the_statements_are_flat_for_one_course(): void {
        $this->prepare();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $make = function (int $teachers, string $programme) use ($generator, $category): \stdClass {
            global $DB;
            $course = $generator->create_course(['category' => $category->id, 'summary' => '<p>Resumo.</p>']);
            course_handler::create()->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
                'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
                'customfield_' . landingfields::SHORTNAME_CITY => 'Recife',
                'customfield_' . landingfields::SHORTNAME_PROGRAMME . '_editor' => ['text' => $programme, 'format' => FORMAT_HTML],
            ]);
            for ($i = 0; $i < $teachers; $i++) {
                $generator->enrol_user($generator->create_user()->id, $course->id, 'editingteacher');
            }
            return $DB->get_record('course', ['id' => $course->id], dialogue::COURSE_FIELDS, MUST_EXIST);
        };
        $small = $make(1, '<p>Um</p><p>Dois</p>');
        $large = $make(3, '<p>1</p><p>2</p><p>3</p><p>4</p><p>5</p><p>6</p>');
        $this->become($generator->create_user());

        $meter = function (\stdClass $course): int {
            return $this->reads(function () use ($course): void {
                \cache::make('core', 'coursecontacts')->purge();
                dialogue::content($course);
            });
        };
        // Warm what a second request would find warm (the fields' definitions, the roles).
        dialogue::content($small);
        dialogue::content($large);
        $smallreads = $meter($small);
        $largereads = $meter($large);
        $facts = dialogue::content($large)['facts'];
        $this->assertCount(3, explode(', ', $facts[count($facts) - 1]['value']), 'Precondition: three contacts.');
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads for one contact against {$largereads} for three.");
        $this->assertGreaterThan(0, $smallreads, 'Precondition: the meter measures the reads.');
    }
}
