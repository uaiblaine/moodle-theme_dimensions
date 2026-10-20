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
 * Theme Dimensions - Tests for the course form's after-data hook callback
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\course;

use core\hook\manager;
use core_course\customfield\course_handler;
use theme_dimensions\local\landingdefaults;
use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the course form's after-data hook callback.
 *
 * The form is built as after_form_definition_test builds it: a plain QuickForm with the
 * three selects and a mocked wrapper answering get_course(). What course_edit_form's
 * set_data() would have put in it comes from core's own instance_form_before_set_data(),
 * as course/edit_form.php does before set_data().
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(after_form_definition_after_data::class)]
final class after_form_definition_after_data_test extends \advanced_testcase {
    /** @var string Form element name of the page model select. */
    private const MODEL = 'customfield_' . landingfields::SHORTNAME_MODEL;

    /**
     * Provision the fields, as an administrator, with the theme's hooks alone.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $this->theme_hooks();
    }

    /**
     * Put back the hook manager and forget the fields this test resolved.
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        landingfields::reset_field_cache();
        course_handler::reset_caches();
        parent::tearDown();
    }

    /**
     * A hook manager reading the theme's db/hooks.php alone.
     *
     * @return manager The manager, installed in the container.
     */
    private function theme_hooks(): manager {
        global $CFG;
        $manager = manager::phpunit_get_instance(['theme_dimensions' => $CFG->dirroot . '/theme/dimensions/db/hooks.php']);
        \core\di::set(manager::class, $manager);
        return $manager;
    }

    /**
     * Build the hook around a minimal course form holding what set_data() would have put in it.
     *
     * @param \stdClass|null $course The course being edited, or null for a new one.
     * @param array $submitted A submission, as the browser would post it; empty for a form being shown.
     * @param array $shortnames The landing page selects the form carries.
     * @return \core_course\hook\after_form_definition_after_data The hook.
     */
    private function make_hook(
        ?\stdClass $course,
        array $submitted = [],
        array $shortnames = [
            landingfields::SHORTNAME_MODEL,
            landingfields::SHORTNAME_MODALITY,
            landingfields::SHORTNAME_FORMAT,
        ]
    ): \core_course\hook\after_form_definition_after_data {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/course/edit_form.php');

        $mform = new \MoodleQuickForm('testform', 'post', '/');
        foreach ($shortnames as $shortname) {
            $mform->addElement('select', 'customfield_' . $shortname, $shortname, [0 => '', 1 => 'A', 2 => 'B', 3 => 'C']);
        }

        $record = $course ? clone $course : new \stdClass();
        course_handler::create()->instance_form_before_set_data($record);
        $defaults = [];
        foreach ($shortnames as $shortname) {
            $defaults['customfield_' . $shortname] = $record->{'customfield_' . $shortname};
        }
        $mform->setDefaults($defaults);
        if ($submitted) {
            $mform->updateSubmission($submitted, []);
        }

        $formwrapper = $this->createMock(\course_edit_form::class);
        $formwrapper->method('get_course')->willReturn($course ?? new \stdClass());
        return new \core_course\hook\after_form_definition_after_data($formwrapper, $mform);
    }

    /**
     * The value a select of the form shows.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param string $element The element name.
     * @return int The selected value.
     */
    private static function shown(\MoodleQuickForm $mform, string $element): int {
        $value = $mform->getElementValue($element);
        return (int) (is_array($value) ? reset($value) : $value);
    }

    /**
     * The theme's db/hooks.php registers callback() for core's after-data hook, and core resolves it.
     */
    public function test_the_registration_resolves_to_the_callback(): void {
        $manager = $this->theme_hooks();

        $definitions = $manager->get_callbacks_for_hook(\core_course\hook\after_form_definition_after_data::class);

        $this->assertSame([after_form_definition_after_data::class . '::callback'], array_column($definitions, 'callback'));
        $this->assertSame(['theme_dimensions'], array_column($definitions, 'component'));
        $this->assertSame([false], array_column($definitions, 'disabled'), 'Not switched off by a callback override.');
        $listeners = iterator_to_array($manager->getListenersForEvent($this->make_hook(null)), false);
        $this->assertSame(
            [after_form_definition_after_data::class . '::callback'],
            $listeners,
            'Core finds the class and its static method.'
        );
    }

    /**
     * The form of a new course shows each default preselected, where core showed the empty choice.
     */
    public function test_a_new_course_form_shows_the_defaults_preselected(): void {
        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);
        set_config(landingdefaults::SETTING_MODALITY, landingfields::MODALITY_HYBRID, landingfields::COMPONENT);
        set_config(landingdefaults::SETTING_FORMAT, landingfields::FORMAT_TUTORED, landingfields::COMPONENT);
        $hook = $this->make_hook(null);
        $this->assertSame(0, self::shown($hook->mform, self::MODEL), 'Core shows the empty choice before the callback.');

        after_form_definition_after_data::callback($hook);

        $this->assertSame(landingfields::MODEL_EVENT, self::shown($hook->mform, self::MODEL));
        $this->assertSame(
            landingfields::MODALITY_HYBRID,
            self::shown($hook->mform, 'customfield_' . landingfields::SHORTNAME_MODALITY)
        );
        $this->assertSame(
            landingfields::FORMAT_TUTORED,
            self::shown($hook->mform, 'customfield_' . landingfields::SHORTNAME_FORMAT)
        );
    }

    /**
     * An existing course's form shows what the course holds, a stored answer or nothing, never the default.
     */
    public function test_an_existing_course_form_shows_its_stored_value(): void {
        $explicit = self::getDataGenerator()->create_course([self::MODEL => landingfields::MODEL_COURSE]);
        $rowless = self::getDataGenerator()->create_course();
        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);

        $hook = $this->make_hook($explicit);
        after_form_definition_after_data::callback($hook);
        $this->assertSame(landingfields::MODEL_COURSE, self::shown($hook->mform, self::MODEL));

        $hook = $this->make_hook($rowless);
        after_form_definition_after_data::callback($hook);
        $this->assertSame(0, self::shown($hook->mform, self::MODEL), 'A course nobody answered shows the empty choice.');

        $control = $this->make_hook(null);
        after_form_definition_after_data::callback($control);
        $this->assertSame(landingfields::MODEL_EVENT, self::shown($control->mform, self::MODEL), 'A new course gets the default.');
    }

    /**
     * At None the form keeps core's choice; under a default the same form shows the default.
     */
    public function test_none_leaves_cores_default(): void {
        $hook = $this->make_hook(null);
        after_form_definition_after_data::callback($hook);
        $this->assertSame(0, self::shown($hook->mform, self::MODEL));

        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);
        $control = $this->make_hook(null);
        after_form_definition_after_data::callback($control);
        $this->assertSame(landingfields::MODEL_EVENT, self::shown($control->mform, self::MODEL));
    }

    /**
     * A choice the creator submitted survives the default, the empty one included; unsubmitted, the default shows.
     */
    public function test_a_submitted_choice_survives_the_default(): void {
        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);

        $hook = $this->make_hook(null, [self::MODEL => '0']);
        after_form_definition_after_data::callback($hook);
        $this->assertSame(0, self::shown($hook->mform, self::MODEL), 'The empty choice submitted is kept.');

        $hook = $this->make_hook(null, [self::MODEL => (string) landingfields::MODEL_COURSE]);
        after_form_definition_after_data::callback($hook);
        $this->assertSame(landingfields::MODEL_COURSE, self::shown($hook->mform, self::MODEL));

        $control = $this->make_hook(null);
        after_form_definition_after_data::callback($control);
        $this->assertSame(landingfields::MODEL_EVENT, self::shown($control->mform, self::MODEL));
    }

    /**
     * A field missing from the form is skipped, and the others still get their default.
     */
    public function test_a_field_missing_from_the_form_is_skipped(): void {
        set_config(landingdefaults::SETTING_MODEL, landingfields::MODEL_EVENT, landingfields::COMPONENT);
        set_config(landingdefaults::SETTING_FORMAT, landingfields::FORMAT_TUTORED, landingfields::COMPONENT);
        $hook = $this->make_hook(null, [], [landingfields::SHORTNAME_MODEL]);

        after_form_definition_after_data::callback($hook);

        $this->assertFalse($hook->mform->elementExists('customfield_' . landingfields::SHORTNAME_FORMAT));
        $this->assertSame(landingfields::MODEL_EVENT, self::shown($hook->mform, self::MODEL));
    }
}
