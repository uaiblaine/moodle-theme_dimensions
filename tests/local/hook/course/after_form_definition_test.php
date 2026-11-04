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
 * Theme Dimensions - Tests for the course form hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use theme_dimensions\local\focusmode;
use theme_dimensions\local\focusmodefields;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\login;
use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course form hook callback.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(after_form_definition::class)]
final class after_form_definition_test extends \advanced_testcase {
    /** @var string Form element name of the page model select. */
    private const MODEL = 'customfield_' . landingfields::SHORTNAME_MODEL;

    /**
     * Leave no router built under this test's setting behind.
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Declare the site's router configured or not, and let the next reader build the router against it.
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
     * Build the hook around a minimal form.
     *
     * The '_static' sibling mirrors what core adds for a field that carries a
     * description ({@see \core_customfield\handler::instance_form_definition()}).
     *
     * @param \stdClass|null $course The course being edited, if any.
     * @return \core_course\hook\after_form_definition The hook.
     */
    private function make_hook(?\stdClass $course = null): \core_course\hook\after_form_definition {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/course/edit_form.php');

        $mform = new \MoodleQuickForm('testform', 'post', '/');
        $mform->addElement('header', 'category_landing', 'Course landing page');
        $mform->addElement('select', self::MODEL, 'Enrolment page model', [0 => '', 1 => 'Course', 2 => 'Event']);
        $mform->addElement('static', self::MODEL . '_static', '', 'Which model the landing page uses.');
        $mform->addElement('text', 'shortname', 'Short name');

        $formwrapper = $this->createMock(\course_edit_form::class);
        $formwrapper->method('get_course')->willReturn($course ?? new \stdClass());
        return new \core_course\hook\after_form_definition($formwrapper, $mform);
    }

    /**
     * Create a course, optionally opted into a landing page model.
     *
     * @param int $model The page model select value (0 = none).
     * @return \stdClass The course record.
     */
    private function create_course_with_model(int $model): \stdClass {
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        if ($model) {
            $handler = \core_course\customfield\course_handler::create();
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => $model,
            ]);
        }
        return $course;
    }

    /**
     * The names of a form's elements, in the order they will be rendered.
     *
     * @param \MoodleQuickForm $mform The form.
     * @return array List of element names.
     */
    private function element_order(\MoodleQuickForm $mform): array {
        $names = [];
        foreach ($mform->_elements as $element) {
            $names[] = $element->getName();
        }
        return $names;
    }

    /**
     * A course with a page model gets its public address and a link to its landing page, right under the model field.
     *
     * With the router configured the address is the short one, minted by this very visit to the
     * form, shown as text with a copy button, and the link opens it.
     */
    public function test_link_is_added_for_a_course_with_a_model(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);
        $this->assertNull(links::existing_code((int) $course->id), 'Nothing minted before the form is built.');

        $hook = $this->make_hook($course);
        after_form_definition::callback($hook);

        $this->assertTrue($hook->mform->elementExists(after_form_definition::LINK_ELEMENT));

        $code = links::existing_code((int) $course->id);
        $this->assertNotNull($code, 'Building the form minted the address.');
        $address = links::share((int) $course->id)->out(false);
        $this->assertStringContainsString('/p/' . $code, $address);

        $html = $hook->mform->getElement(after_form_definition::LINK_ELEMENT)->toHtml();
        $this->assertStringContainsString('href="' . $address . '"', $html);
        $this->assertStringContainsString('>' . $address . '</code>', $html);
        $this->assertStringContainsString('data-action="copytoclipboard"', $html);
        $this->assertStringNotContainsString('landing.php', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString(get_string('landing_viewlink', landingfields::COMPONENT), $html);
        $this->assertStringContainsString(get_string('landing_address', landingfields::COMPONENT), $html);
        $this->assertStringNotContainsString(s(get_string('landing_addressrouteroff', landingfields::COMPONENT)), $html);

        // It lands after the model select and after the sentence describing it, not before either.
        $order = $this->element_order($hook->mform);
        $this->assertSame(
            [self::MODEL, self::MODEL . '_static', after_form_definition::LINK_ELEMENT],
            array_slice($order, array_search(self::MODEL, $order, true), 3)
        );
    }

    /**
     * Without the router the form shows the page's own address, says why, and mints nothing.
     */
    public function test_link_falls_back_to_the_page_address_while_the_router_is_off(): void {
        global $DB;
        $this->resetAfterTest();
        $this->set_router(false);
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);

        $hook = $this->make_hook($course);
        after_form_definition::callback($hook);

        $html = $hook->mform->getElement(after_form_definition::LINK_ELEMENT)->toHtml();
        $this->assertStringContainsString('href="' . links::page((int) $course->id)->out(false) . '"', $html);
        $this->assertStringNotContainsString('/p/', $html);
        // The double stash escapes the apostrophe, so the sentence is compared in its escaped spelling.
        $this->assertStringContainsString(s(get_string('landing_addressrouteroff', landingfields::COMPONENT)), $html);
        $this->assertSame(0, $DB->count_records('shortlink'));
    }

    /**
     * A course whose guest access needs no password gets the guest entry's row, with a code of its own.
     *
     * The course is not public, so the row says the address takes in logged-in readers only. The
     * control is the same course with its guest access instance disabled: no row, no second code.
     */
    public function test_the_guest_entry_row_is_added_where_the_course_offers_it(): void {
        global $DB;
        $this->resetAfterTest();
        // The site must admit the guest account, or the entry is withdrawn (guestaccess::available()).
        set_config('autologinguests', 1);
        $this->set_router(true);
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);
        $guestlabel = get_string('landing_guestaddress', landingfields::COMPONENT);

        // Control first: the generator leaves guest access disabled.
        $hook = $this->make_hook($course);
        after_form_definition::callback($hook);
        $html = $hook->mform->getElement(after_form_definition::LINK_ELEMENT)->toHtml();
        $this->assertStringNotContainsString($guestlabel, $html);
        $this->assertNull(links::existing_code((int) $course->id, links::LINKTYPE_GUEST), 'Nothing minted for it.');

        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $course->id, 'enrol' => 'guest']);
        $hook = $this->make_hook($course);
        after_form_definition::callback($hook);
        $html = $hook->mform->getElement(after_form_definition::LINK_ELEMENT)->toHtml();

        $code = links::existing_code((int) $course->id, links::LINKTYPE_GUEST);
        $this->assertNotNull($code, 'Building the form minted the guest entry\'s code beside the landing page\'s.');
        $this->assertNotSame(links::existing_code((int) $course->id), $code);
        $address = links::guest_share((int) $course->id)->out(false);
        $this->assertStringContainsString('/p/' . $code, $address);
        $this->assertStringContainsString($guestlabel, $html);
        $this->assertStringContainsString(get_string('landing_guestaddresssub', landingfields::COMPONENT), $html);
        $this->assertStringContainsString('>' . $address . '</code>', $html);
        $this->assertStringContainsString('href="' . $address . '"', $html);
        $this->assertStringContainsString('data-clipboard-target="#theme_dimensions-landingguestaddress-', $html);
        $this->assertStringContainsString(get_string('landing_guestaddressnotpublic', landingfields::COMPONENT), $html);
    }

    /**
     * A course which never opted into a landing page gets no link.
     *
     * The control is the test above: the same form, the same course generator and
     * the same callback do produce the link once a model is stored, so a pass here
     * is the model check answering and not the callback failing to run.
     */
    public function test_link_is_absent_without_a_model(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(0);

        $hook = $this->make_hook($course);
        after_form_definition::callback($hook);

        $this->assertFalse($hook->mform->elementExists(after_form_definition::LINK_ELEMENT));
    }

    /**
     * A course being created has no saved landing page to look at.
     */
    public function test_link_is_absent_while_the_course_is_being_created(): void {
        $this->resetAfterTest();
        $this->create_course_with_model(landingfields::MODEL_COURSE);

        $hook = $this->make_hook();
        after_form_definition::callback($hook);

        $this->assertFalse($hook->mform->elementExists(after_form_definition::LINK_ELEMENT));
    }

    /**
     * Without the model select there is no anchor to place the link against.
     *
     * Reached by an editor who may fill the course form but not its custom fields:
     * core leaves the whole category out of the form for them.
     */
    public function test_link_is_absent_when_the_model_field_is_not_in_the_form(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);

        $hook = $this->make_hook($course);
        $hook->mform->removeElement(self::MODEL);
        $hook->mform->removeElement(self::MODEL . '_static');
        after_form_definition::callback($hook);

        $this->assertFalse($hook->mform->elementExists(after_form_definition::LINK_ELEMENT));
    }

    /**
     * The link is placed even when the model field carries no description.
     */
    public function test_link_is_placed_without_a_field_description(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(landingfields::MODEL_COURSE);

        $hook = $this->make_hook($course);
        $hook->mform->removeElement(self::MODEL . '_static');
        after_form_definition::callback($hook);

        $order = $this->element_order($hook->mform);
        $this->assertSame(
            [self::MODEL, after_form_definition::LINK_ELEMENT],
            array_slice($order, array_search(self::MODEL, $order, true), 2)
        );
    }
    /**
     * Add the format and hybrid controls, each with its description, to a hook's form.
     *
     * @param \MoodleQuickForm $mform The form built by make_hook().
     * @return void
     */
    private static function add_round6_elements(\MoodleQuickForm $mform): void {
        $mform->addElement('select', 'customfield_' . landingfields::SHORTNAME_MODALITY, 'Modality', [0 => '', 1 => 'Online']);
        foreach (landingfields::ROUND6_SHORTNAMES as $shortname) {
            $mform->addElement('text', 'customfield_' . $shortname, $shortname);
            $mform->addElement('static', 'customfield_' . $shortname . '_static', '', 'Description.');
        }
    }

    /**
     * The elements a value of a control hides, as the form hands them to its script.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $dependenton The control.
     * @param string $condition The condition.
     * @param string $value The value.
     * @return string[] The hidden element names, sorted.
     */
    private static function hidden_by(\MoodleQuickForm $mform, string $dependenton, string $condition, string $value): array {
        // The second member is the dependency map; the first is the form's id.
        $names = $mform->getLockOptionObject()[1][$dependenton][$condition][$value][\MoodleQuickForm::DEP_HIDE] ?? [];
        sort($names);
        return $names;
    }

    /**
     * Each format and hybrid field and its description are hidden while the rest of the form says it does not apply.
     */
    public function test_round6_fields_are_hidden_where_they_do_not_apply(): void {
        $this->resetAfterTest();
        $hook = $this->make_hook();
        self::add_round6_elements($hook->mform);
        after_form_definition::callback($hook);

        $pair = function (string ...$shortnames): array {
            $names = [];
            foreach ($shortnames as $shortname) {
                $names[] = 'customfield_' . $shortname;
                $names[] = 'customfield_' . $shortname . '_static';
            }
            sort($names);
            return $names;
        };

        $this->assertSame(
            $pair(
                landingfields::SHORTNAME_FORMAT,
                landingfields::SHORTNAME_LIVECLASSES,
                landingfields::SHORTNAME_LIVECLASSESWHEN
            ),
            self::hidden_by($hook->mform, self::MODEL, 'eq', (string) landingfields::MODEL_EVENT)
        );
        $this->assertSame(
            $pair(landingfields::SHORTNAME_LIVECLASSES, landingfields::SHORTNAME_LIVECLASSESWHEN),
            self::hidden_by(
                $hook->mform,
                'customfield_' . landingfields::SHORTNAME_FORMAT,
                'eq',
                (string) landingfields::FORMAT_SELFPACED
            )
        );
        $this->assertSame(
            $pair(landingfields::SHORTNAME_LIVECLASSESWHEN),
            self::hidden_by($hook->mform, 'customfield_' . landingfields::SHORTNAME_LIVECLASSES, 'notchecked', '1')
        );
        // The in-person workload is never hidden: a hideIf on its float element breaks the save.
        $this->assertSame(
            $pair(landingfields::SHORTNAME_MEETINGS),
            self::hidden_by(
                $hook->mform,
                'customfield_' . landingfields::SHORTNAME_MODALITY,
                'neq',
                (string) landingfields::MODALITY_HYBRID
            )
        );
    }

    /**
     * A form without the format and hybrid fields (an editor who may not edit them) gains no rule at all.
     */
    public function test_no_rule_is_set_without_the_fields(): void {
        $this->resetAfterTest();
        $hook = $this->make_hook();
        after_form_definition::callback($hook);
        $this->assertSame([], self::hidden_by($hook->mform, self::MODEL, 'eq', (string) landingfields::MODEL_EVENT));
    }
    /**
     * A course's self enrolment instance, enabled, with the given fields.
     *
     * @param \stdClass $course The course.
     * @param array $fields The fields to set on the instance.
     * @return \stdClass The {enrol} record.
     */
    private static function self_instance(\stdClass $course, array $fields): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        $DB->update_record('enrol', (object) (['id' => $instance->id, 'status' => ENROL_INSTANCE_ENABLED] + $fields));
        return $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * The hook of a course form that carries the modality field and its description.
     *
     * @param \stdClass|null $course The course being edited, if any.
     * @return \core_course\hook\after_form_definition The hook.
     */
    private function hook_with_modality(?\stdClass $course): \core_course\hook\after_form_definition {
        $hook = $this->make_hook($course);
        $modality = 'customfield_' . landingfields::SHORTNAME_MODALITY;
        $hook->mform->addElement('select', $modality, 'Modality', [0 => '', 1 => 'Online']);
        $hook->mform->addElement('static', $modality . '_static', '', 'Whether the offering is online.');
        return $hook;
    }

    /**
     * An event whose enrolment method sets no deadline is warned, by the method's name, under the modality.
     */
    public function test_an_event_with_an_undated_method_is_warned(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_model(landingfields::MODEL_EVENT);
        self::self_instance($course, ['name' => 'Presencial', 'enrolenddate' => 0, 'customint6' => 1]);

        $hook = $this->hook_with_modality($course);
        after_form_definition::callback($hook);

        $this->assertTrue($hook->mform->elementExists(after_form_definition::DEADLINE_ELEMENT));
        $html = $hook->mform->getElement(after_form_definition::DEADLINE_ELEMENT)->toHtml();
        $this->assertStringContainsString(
            s(get_string('landing_nodeadline', 'theme_dimensions', 'Presencial')),
            $html
        );
        $modality = 'customfield_' . landingfields::SHORTNAME_MODALITY;
        $order = $this->element_order($hook->mform);
        $this->assertSame(
            [$modality, $modality . '_static', after_form_definition::DEADLINE_ELEMENT],
            array_values(array_intersect($order, [$modality, $modality . '_static', after_form_definition::DEADLINE_ELEMENT]))
        );
    }

    /**
     * No warning for a dated method, a method closed to new enrolments, a course, or a course being created.
     */
    public function test_no_warning_where_a_deadline_is_set_or_none_is_needed(): void {
        $this->resetAfterTest();
        $event = $this->create_course_with_model(landingfields::MODEL_EVENT);
        $instance = self::self_instance($event, ['enrolenddate' => time() + WEEKSECS, 'customint6' => 1]);
        $hook = $this->hook_with_modality($event);
        after_form_definition::callback($hook);
        $this->assertFalse($hook->mform->elementExists(after_form_definition::DEADLINE_ELEMENT));

        self::self_instance($event, ['id' => $instance->id, 'enrolenddate' => 0, 'customint6' => 0]);
        $hook = $this->hook_with_modality($event);
        after_form_definition::callback($hook);
        $this->assertFalse($hook->mform->elementExists(after_form_definition::DEADLINE_ELEMENT));

        $plaincourse = $this->create_course_with_model(landingfields::MODEL_COURSE);
        self::self_instance($plaincourse, ['enrolenddate' => 0, 'customint6' => 1]);
        $hook = $this->hook_with_modality($plaincourse);
        after_form_definition::callback($hook);
        $this->assertFalse($hook->mform->elementExists(after_form_definition::DEADLINE_ELEMENT));

        $hook = $this->hook_with_modality(null);
        after_form_definition::callback($hook);
        $this->assertFalse($hook->mform->elementExists(after_form_definition::DEADLINE_ELEMENT));
    }

    /**
     * The course form as core builds its custom fields, after this hook ran.
     *
     * @param \stdClass $course The course being edited.
     * @return \MoodleQuickForm The form.
     */
    private function course_form(\stdClass $course): \MoodleQuickForm {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/course/edit_form.php');

        $mform = new \MoodleQuickForm('testform', 'post', '/');
        \core_course\customfield\course_handler::create()->instance_form_definition($mform, (int) $course->id);
        $formwrapper = $this->createMock(\course_edit_form::class);
        $formwrapper->method('get_course')->willReturn($course);
        after_form_definition::callback(new \core_course\hook\after_form_definition($formwrapper, $mform));
        return $mform;
    }

    /**
     * Without mod_subcourse enabled the model select offers no trail, unless the course already is one; with it, it does.
     *
     * The course's own choice stays so the form shows what the course holds.
     */
    public function test_the_trail_choice_needs_the_subcourse_module(): void {
        $this->resetAfterTest();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $plain = $this->create_course_with_model(landingfields::MODEL_COURSE);
        $saved = $this->create_course_with_model(landingfields::MODEL_TRAIL);
        $offered = static function (\MoodleQuickForm $mform): array {
            $options = $mform->getElement(self::MODEL)->_options;
            return array_map(static fn(array $option): int => (int) $option['attr']['value'], $options);
        };

        $this->assertContains(landingfields::MODEL_TRAIL, $offered($this->course_form($plain)), 'Precondition: offered.');

        \core\plugininfo\mod::enable_plugin('subcourse', 0);

        $options = $offered($this->course_form($plain));
        $this->assertNotContains(landingfields::MODEL_TRAIL, $options);
        $this->assertContains(landingfields::MODEL_EVENT, $options, 'The other lines stay.');
        $created = $offered($this->course_form((object) ['id' => 0]));
        $this->assertNotContains(landingfields::MODEL_TRAIL, $created, 'Nor to a new course.');
        $this->assertContains(
            landingfields::MODEL_TRAIL,
            $offered($this->course_form($saved)),
            'A course saved as a trail keeps its choice.'
        );
    }

    /**
     * While focus mode's master switch is off its box, its description and its category's header leave the form.
     *
     * The control is the same form with the switch on, which offers all three.
     */
    public function test_the_focus_mode_box_leaves_the_form_while_the_switch_is_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        \core_course\customfield\course_handler::reset_caches();
        focusmodefields::ensure_fields();
        focusmodefields::reset_field_cache();
        $course = self::getDataGenerator()->create_course();
        $element = 'customfield_' . focusmodefields::SHORTNAME;
        $header = 'category_' . focusmodefields::get_field()->get_category()->get('id');

        set_config('focusmode', login::SELECT_YES, focusmode::COMPONENT);
        $mform = $this->course_form($course);
        $this->assertTrue($mform->elementExists($element), 'Control: on, the box is offered.');
        $this->assertTrue($mform->elementExists($element . '_static'));
        $this->assertTrue($mform->elementExists($header));

        set_config('focusmode', login::SELECT_NO, focusmode::COMPONENT);
        $mform = $this->course_form($course);
        $this->assertFalse($mform->elementExists($element));
        $this->assertFalse($mform->elementExists($element . '_static'));
        $this->assertFalse($mform->elementExists($header));
    }
}
