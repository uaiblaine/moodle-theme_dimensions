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
 * Theme Dimensions - Tests for the related courses' manual choice in the course form
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use core_competency\course_competency;
use core_course\customfield\course_handler;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\related\choice;

/**
 * The course form offers an autocomplete of the course's competencies in the place of the pairs field, and its
 * submission stores the choice as pairs.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(after_form_definition::class)]
#[CoversClass(after_form_submission::class)]
final class related_choice_test extends \advanced_testcase {
    /** @var string Form element name of the related select. */
    private const SELECT = 'customfield_' . landingfields::SHORTNAME_RELATED;

    /** @var string Form element name of the pairs field. */
    private const PAIRS = 'customfield_' . landingfields::SHORTNAME_RELATEDPAIRS;

    /** @var int[] Competency ids by idnumber, in framework FW1. */
    private array $comps = [];

    /**
     * Competencies on, the fields provisioned, as an administrator, with one framework of three competencies.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $generator = self::getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $generator->create_framework(['idnumber' => 'FW1']);
        foreach (['A', 'B', 'C'] as $idnumber) {
            $this->comps[$idnumber] = (int) $generator->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'idnumber' => $idnumber,
            ])->get('id');
        }
    }

    /**
     * Forget the fields this test resolved.
     */
    protected function tearDown(): void {
        landingfields::reset_field_cache();
        course_handler::reset_caches();
        parent::tearDown();
    }

    /**
     * A course linked to competencies.
     *
     * @param string[] $idnumbers The competencies.
     * @return \stdClass The course record.
     */
    private function course(array $idnumbers): \stdClass {
        $course = self::getDataGenerator()->create_course();
        foreach ($idnumbers as $idnumber) {
            self::getDataGenerator()->get_plugin_generator('core_competency')->create_course_competency([
                'courseid' => $course->id,
                'competencyid' => $this->comps[$idnumber],
                'ruleoutcome' => course_competency::OUTCOME_EVIDENCE,
            ]);
        }
        return $course;
    }

    /**
     * The course form's part this hook edits: the select and the pairs field, each with core's description element.
     *
     * @param \stdClass $course The course being edited.
     * @return \MoodleQuickForm The form, after the hook.
     */
    private function form(\stdClass $course): \MoodleQuickForm {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/course/edit_form.php');

        $mform = new \MoodleQuickForm('testform', 'post', '/');
        $mform->addElement('select', self::SELECT, 'Related courses', [0 => '', 1 => 'Automatic', 2 => 'Off', 3 => 'Manual']);
        $mform->addElement('static', self::SELECT . '_static', '', 'Which courses the band relates.');
        $mform->addElement('text', self::PAIRS, 'Competencies for related courses');
        $mform->addElement('static', self::PAIRS . '_static', '', 'The stored pairs.');
        $mform->addElement('text', 'shortname', 'Short name');

        $formwrapper = $this->createMock(\course_edit_form::class);
        $formwrapper->method('get_course')->willReturn($course);
        after_form_definition::callback(new \core_course\hook\after_form_definition($formwrapper, $mform));
        return $mform;
    }

    /**
     * The names of a form's elements, in order; QuickForm leaves a removed element's key empty.
     *
     * @param \MoodleQuickForm $mform The form.
     * @return string[]
     */
    private static function names(\MoodleQuickForm $mform): array {
        return array_values(array_map(static fn($element): string => (string) $element->getName(), $mform->_elements));
    }

    /**
     * The autocomplete stands under the select in the pairs field's place, offers the course's competencies, preselects
     * the stored choice and shows only for Manual.
     */
    public function test_the_form_offers_the_courses_competencies_for_manual(): void {
        $course = $this->course(['A', 'B']);
        choice::store((int) $course->id, [['FW1', 'B'], ['FW1', 'C']]);

        $mform = $this->form($course);

        $this->assertSame(
            [self::SELECT, self::SELECT . '_static', choice::ELEMENT, 'shortname', self::PAIRS, choice::MARKER],
            self::names($mform)
        );
        $element = $mform->getElement(choice::ELEMENT);
        $this->assertInstanceOf(\MoodleQuickForm_autocomplete::class, $element);
        $this->assertEqualsCanonicalizing(
            [$this->comps['A'], $this->comps['B']],
            array_map(static fn(array $option): int => (int) $option['attr']['value'], $element->_options)
        );
        $this->assertSame([$this->comps['B']], $mform->_defaultValues[choice::ELEMENT]);
        // The rules are protected, and a `??` over a protected property reads null: reflection reads them.
        $rules = (new \ReflectionProperty($mform, '_hideifs'))->getValue($mform);
        $this->assertSame([choice::ELEMENT], $rules[self::SELECT]['neq'][(string) landingfields::RELATED_MANUAL]);
    }

    /**
     * While competencies are off the pairs field leaves the form and nothing takes its place.
     */
    public function test_no_choice_while_competencies_are_off(): void {
        $course = $this->course(['A']);
        set_config('enabled', 0, 'core_competency');

        $this->assertSame([self::SELECT, self::SELECT . '_static', 'shortname', self::PAIRS], self::names($this->form($course)));
    }

    /**
     * A course being created offers no competency, and the field is still the hidden element.
     */
    public function test_a_new_course_offers_none(): void {
        $mform = $this->form(new \stdClass());

        $this->assertSame('hidden', $mform->getElement(self::PAIRS)->getType());
        $this->assertSame([], $mform->getElement(choice::ELEMENT)->_options);
    }

    /**
     * The submission still carries the pairs field, which core's text field validation reads by name.
     */
    public function test_the_submission_carries_what_core_validates(): void {
        $course = $this->course(['A']);
        $mform = $this->form($course);

        $this->assertSame('hidden', $mform->getElement(self::PAIRS)->getType());
        $controllers = course_handler::create()->get_instance_data((int) $course->id, true);
        $pairs = array_values(array_filter(
            $controllers,
            static fn($data): bool => $data->get_field()->get('shortname') === landingfields::SHORTNAME_RELATEDPAIRS
        ))[0];
        $submitted = array_fill_keys(self::names($mform), '') + ['id' => $course->id];
        $this->assertSame([], $pairs->instance_form_validation($submitted, []));
    }

    /**
     * The submission of a Manual course stores the chosen pairs, linked competencies only, on an update too.
     */
    public function test_the_submission_stores_the_choice_for_manual(): void {
        $course = $this->course(['A', 'B']);
        $data = (object) [
            'id' => $course->id,
            choice::MARKER => 1,
            self::SELECT => landingfields::RELATED_MANUAL,
            choice::ELEMENT => [$this->comps['B'], $this->comps['C'], $this->comps['A']],
        ];

        after_form_submission::callback(new \core_course\hook\after_form_submission($data, false));
        $this->assertSame([['FW1', 'B'], ['FW1', 'A']], choice::decode(landingfields::values((int) $course->id)->relatedpairs));

        unset($data->{choice::ELEMENT});
        after_form_submission::callback(new \core_course\hook\after_form_submission($data, false));
        $this->assertSame('', landingfields::values((int) $course->id)->relatedpairs, 'Nothing chosen is an answer.');
    }

    /**
     * Without the form's marker, or with the select on another line, the stored choice is left as it is.
     */
    public function test_the_choice_is_kept_without_the_form_or_outside_manual(): void {
        $course = $this->course(['A', 'B']);
        choice::store((int) $course->id, [['FW1', 'A']]);
        $stored = landingfields::values((int) $course->id)->relatedpairs;
        $manual = [
            'id' => $course->id,
            choice::MARKER => 1,
            self::SELECT => landingfields::RELATED_MANUAL,
            choice::ELEMENT => [$this->comps['B']],
        ];

        foreach ([[choice::MARKER], [self::SELECT]] as $dropped) {
            $data = (object) array_diff_key($manual, array_flip($dropped));
            after_form_submission::callback(new \core_course\hook\after_form_submission($data, false));
            $this->assertSame($stored, landingfields::values((int) $course->id)->relatedpairs, implode(',', $dropped));
        }
        $data = (object) ([self::SELECT => landingfields::RELATED_AUTO] + $manual);
        after_form_submission::callback(new \core_course\hook\after_form_submission($data, false));
        $this->assertSame($stored, landingfields::values((int) $course->id)->relatedpairs, 'Automatic keeps the choice.');

        after_form_submission::callback(new \core_course\hook\after_form_submission((object) $manual, false));
        $this->assertSame([['FW1', 'B']], choice::decode(landingfields::values((int) $course->id)->relatedpairs), 'Control.');
    }
}
