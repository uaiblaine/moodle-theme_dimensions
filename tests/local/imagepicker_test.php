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
 * Theme Dimensions - Tests of the image fields' element, with tool_imagepicker and without it
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\form\categoryhero;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\hook\course\after_form_definition;
use theme_dimensions\local\landing\publicfiles;

/**
 * Tests of the image fields' element: tool_imagepicker's where it is installed, core's file manager where it is not.
 *
 * The "without" half hands in a class that does not exist, so it runs on every site; the "with" half needs the
 * real plugin and skips without it (it is a CI dependency of this theme, never a declared one).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(imagepicker::class)]
#[CoversClass(after_form_definition::class)]
#[CoversClass(categoryhero::class)]
final class imagepicker_test extends \advanced_testcase {
    /** @var string A picker helper that does not exist: the site without the plugin. */
    private const MISSING = '\\theme_dimensions\\local\\no_such_imagepicker';

    /** @var string The picker's form element class. */
    private const PICKER = '\\tool_imagepicker\\formelement\\imagepicker';

    /**
     * Skip a test that needs the real plugin where it is not installed.
     *
     * @return void
     */
    private function require_plugin(): void {
        if (!imagepicker::available()) {
            $this->markTestSkipped('tool_imagepicker is not installed.');
        }
    }

    /**
     * A form shaped as customfield_picture and core's handler leave the course form: each picture field a file
     * manager followed by its description, and an element after the last one.
     *
     * @return \MoodleQuickForm
     */
    private static function course_form(): \MoodleQuickForm {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        $mform = new \MoodleQuickForm('testform', 'post', '/');
        $mform->addElement('header', 'category_landing', 'Course landing page');
        foreach (landingfields::PICTURE_SHORTNAMES as $i => $shortname) {
            $name = 'customfield_' . $shortname;
            $mform->addElement('filemanager', $name, 'Picture ' . $i, null, [
                'maxbytes' => 1000 + $i,
                'maxfiles' => 1,
                'subdirs' => 0,
                'accepted_types' => 'web_image',
            ]);
            $mform->addElement('static', $name . '_static', '', 'Description ' . $i);
        }
        $mform->addElement('text', 'shortname', 'Short name');
        return $mform;
    }

    /**
     * The names of a form's elements, in order.
     *
     * @param \MoodleQuickForm $mform The form.
     * @return string[]
     */
    private static function names(\MoodleQuickForm $mform): array {
        // In order, without QuickForm's keys: a removal leaves a hole there.
        return array_values(array_map(static fn($element): string => (string) $element->getName(), $mform->_elements));
    }

    /**
     * The picker's own options of an element, read off it.
     *
     * @param object $element A picker element.
     * @return array The options keyed by name.
     */
    private static function picker_options(object $element): array {
        $property = new \ReflectionProperty(self::PICKER, 'imagepickeroptions');
        return $property->getValue($element);
    }

    /**
     * Whether the installed picker's dialog offers a choice of aspect ratios.
     *
     * @return bool
     */
    private static function picker_offers_choices(): bool {
        return class_exists(self::PICKER) && method_exists(self::PICKER, 'aspect_ratio_choices');
    }

    /**
     * The QuickForm behind a moodleform.
     *
     * @param \moodleform $form The form.
     * @return \MoodleQuickForm
     */
    private static function quickform(\moodleform $form): \MoodleQuickForm {
        $property = new \ReflectionProperty(\moodleform::class, '_form');
        return $property->getValue($form);
    }

    /**
     * Without the plugin an image field is core's file manager with exactly the options it was given.
     */
    public function test_without_the_plugin_the_field_is_cores_file_manager_unchanged(): void {
        $options = ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['web_image']];

        $this->assertFalse(imagepicker::available(self::MISSING));
        $this->assertSame('filemanager', imagepicker::element_type(self::MISSING));
        $this->assertSame($options, imagepicker::options($options, imagepicker::PROFILE_SHARE, self::MISSING));
    }

    /**
     * A class that exists but is not the picker's helper is not taken for it.
     */
    public function test_a_class_without_the_helpers_shape_is_not_the_picker(): void {
        $this->assertFalse(imagepicker::available(\stdClass::class));
        $this->assertSame('filemanager', imagepicker::element_type(\stdClass::class));
    }

    /**
     * A profile the helper does not know is a programming error, with the plugin or without it.
     */
    public function test_an_unknown_profile_is_refused(): void {
        $this->expectException(\coding_exception::class);
        imagepicker::options([], 'banner', self::MISSING);
    }

    /**
     * The share picture is cropped to the og:image canvas and stored at its size; a caller's own choice wins.
     */
    public function test_the_share_picture_is_cropped_to_the_og_canvas(): void {
        $this->require_plugin();

        $options = imagepicker::options(['maxfiles' => 1], imagepicker::PROFILE_SHARE);
        $this->assertSame(1, $options['maxfiles']);
        $this->assertSame(publicfiles::OG_WIDTH / publicfiles::OG_HEIGHT, $options['cropaspectratio']);
        $this->assertSame(publicfiles::OG_WIDTH, $options['cropmaxwidth']);
        $this->assertSame(publicfiles::OG_HEIGHT, $options['cropmaxheight']);

        $kept = imagepicker::options(['cropmaxwidth' => 600], imagepicker::PROFILE_SHARE);
        $this->assertSame(600, $kept['cropmaxwidth']);
        $this->assertSame(\tool_imagepicker\element::TYPE, imagepicker::element_type());
    }

    /**
     * A stand-in picker, which has no element class beside it, is never handed the choice of ratios or the recommended ratio
     * it would read as a fixed one.
     */
    public function test_a_picker_without_ratio_choices_gets_no_ratio(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/dimensions/tests/fixtures/imagepicker_stub.php');
        $stub = \theme_dimensions\tests\fixtures\imagepicker_stub::class;
        $this->assertTrue(imagepicker::available($stub), 'The stand-in has the helper\'s shape.');

        $options = imagepicker::options([], imagepicker::PROFILE_COVER, $stub);

        $this->assertArrayNotHasKey('cropaspectratios', $options);
        $this->assertArrayNotHasKey('cropaspectratio', $options);
        $this->assertSame(1280, $options['cropmaxwidth']);
    }

    /**
     * Without the plugin the course form keeps customfield_picture's file managers, in place.
     */
    public function test_without_the_plugin_the_course_form_is_left_alone(): void {
        $mform = self::course_form();
        $before = self::names($mform);

        after_form_definition::offer_image_picker($mform, self::MISSING);

        $this->assertSame($before, self::names($mform));
        foreach (landingfields::PICTURE_SHORTNAMES as $shortname) {
            $this->assertSame(\MoodleQuickForm_filemanager::class, get_class($mform->getElement('customfield_' . $shortname)));
        }
    }

    /**
     * With the plugin every picture field is the picker, in its own place, with the field's own limit and types.
     */
    public function test_with_the_plugin_the_course_form_draws_every_picture_field_with_the_picker(): void {
        $this->require_plugin();
        $mform = self::course_form();
        // A hole before the fields, as an earlier edit of the form leaves one: the place is found past it.
        $mform->addElement('static', 'earlier', '', 'Removed below');
        $mform->insertElementBefore($mform->removeElement('earlier', false), 'category_landing');
        $mform->removeElement('earlier');
        $before = self::names($mform);

        after_form_definition::offer_image_picker($mform);

        $this->assertSame($before, self::names($mform), 'Every picker took its file manager\'s place.');
        foreach (landingfields::PICTURE_SHORTNAMES as $i => $shortname) {
            $element = $mform->getElement('customfield_' . $shortname);
            $this->assertInstanceOf(self::PICKER, $element);
            $this->assertSame(1000 + $i, $element->getMaxbytes());
            $this->assertSame('Picture ' . $i, $element->getLabel());
        }

        $share = self::picker_options($mform->getElement('customfield_' . landingfields::SHORTNAME_SHAREIMAGE));
        $this->assertSame(publicfiles::OG_WIDTH / publicfiles::OG_HEIGHT, $share['cropaspectratio']);
        $this->assertSame(publicfiles::OG_WIDTH, $share['cropmaxwidth']);
        $crest = self::picker_options($mform->getElement('customfield_' . landingfields::SHORTNAME_CREST1));
        $this->assertSame(400, $crest['cropmaxwidth']);
        if (self::picker_offers_choices()) {
            $this->assertSame(1, $crest['cropaspectratio']);
            $this->assertSame(['1:1', 'free'], $crest['cropaspectratios']);
        } else {
            $this->assertNull($crest['cropaspectratio'], 'An older picker would lock the crest to its recommended ratio.');
        }
        $this->assertEmpty($share['cropaspectratios'] ?? [], 'The share picture keeps its one fixed shape.');
    }

    /**
     * The course form's hook is what makes the swap: its callback, not only the method, draws the picker.
     */
    public function test_the_course_form_hook_offers_the_picker(): void {
        $this->resetAfterTest();
        $this->require_plugin();
        global $CFG;
        require_once($CFG->dirroot . '/course/edit_form.php');
        require_once($CFG->dirroot . '/course/lib.php');
        $mform = self::course_form();
        $formwrapper = $this->createMock(\course_edit_form::class);
        $formwrapper->method('get_course')->willReturn(new \stdClass());

        $imageoptions = course_overviewfiles_options(new \stdClass());
        $mform->addElement('filemanager', 'overviewfiles_filemanager', 'Course image', null, $imageoptions);

        after_form_definition::callback(new \core_course\hook\after_form_definition($formwrapper, $mform));

        $this->assertInstanceOf(self::PICKER, $mform->getElement('customfield_' . landingfields::SHORTNAME_SHAREIMAGE));
        $this->assertInstanceOf(self::PICKER, $mform->getElement('overviewfiles_filemanager'), 'The course image too.');
    }

    /**
     * The course form as core draws its course image: core's own options, then the summary field after it.
     *
     * @param \stdClass $course The course.
     * @return \MoodleQuickForm
     */
    private static function course_image_form(\stdClass $course): \MoodleQuickForm {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $mform = new \MoodleQuickForm('testform', 'post', '/');
        $options = course_overviewfiles_options($course);
        $mform->addElement('filemanager', 'overviewfiles_filemanager', 'Course image', null, $options);
        $mform->addElement('text', 'summary', 'Summary');
        return $mform;
    }

    /**
     * Only one web image fits the picker: more files, any type, or a type that is not an image keep core's field.
     */
    public function test_only_one_web_image_fits_the_picker(): void {
        $this->assertTrue(imagepicker::fits(['maxfiles' => 1, 'accepted_types' => ['.jpg', '.gif', '.png']]));
        $this->assertTrue(imagepicker::fits(['maxfiles' => '1', 'accepted_types' => 'web_image']));
        $this->assertFalse(imagepicker::fits(['maxfiles' => 2, 'accepted_types' => ['.jpg']]));
        $this->assertFalse(imagepicker::fits(['maxfiles' => 1, 'accepted_types' => '*']));
        $this->assertFalse(imagepicker::fits(['maxfiles' => 1, 'accepted_types' => ['.jpg', '.pdf']]));
        $this->assertFalse(imagepicker::fits(null));
    }

    /**
     * Without the plugin core's course image stays core's file manager.
     */
    public function test_without_the_plugin_the_course_image_is_cores(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $mform = self::course_image_form($course);

        after_form_definition::offer_course_image_picker($mform, $course, self::MISSING);

        $this->assertSame(\MoodleQuickForm_filemanager::class, get_class($mform->getElement('overviewfiles_filemanager')));
    }

    /**
     * With the plugin core's course image is the picker, in its place, with core's own types and the course image's ratios.
     */
    public function test_with_the_plugin_the_course_image_is_the_picker(): void {
        $this->resetAfterTest();
        $this->require_plugin();
        $course = self::getDataGenerator()->create_course();
        $mform = self::course_image_form($course);

        after_form_definition::offer_course_image_picker($mform, $course);

        $element = $mform->getElement('overviewfiles_filemanager');
        $this->assertInstanceOf(self::PICKER, $element);
        $this->assertSame(['overviewfiles_filemanager', 'summary'], self::names($mform));
        $types = (new \ReflectionProperty(\MoodleQuickForm_filemanager::class, '_options'))->getValue($element)['accepted_types'];
        $typesutil = new \core_form\filetypes_util();
        $this->assertEqualsCanonicalizing(
            $typesutil->expand(course_overviewfiles_options($course)['accepted_types']),
            $typesutil->expand($types),
            'Core\'s course image types are kept.'
        );
        $options = self::picker_options($element);
        $this->assertSame(1600, $options['cropmaxwidth']);
        if (self::picker_offers_choices()) {
            $this->assertSame(2, $options['cropaspectratio']);
            $this->assertSame(['2:1', '16:9', '4:3', 'free'], $options['cropaspectratios']);
        }
    }

    /**
     * A site that allows more than one course image, or any file type, keeps core's file manager.
     */
    public function test_a_course_image_the_picker_cannot_hold_stays_cores(): void {
        $this->resetAfterTest();
        $this->require_plugin();
        $course = self::getDataGenerator()->create_course();

        set_config('courseoverviewfileslimit', 2);
        $many = self::course_image_form($course);
        after_form_definition::offer_course_image_picker($many, $course);
        $this->assertSame(\MoodleQuickForm_filemanager::class, get_class($many->getElement('overviewfiles_filemanager')));

        set_config('courseoverviewfileslimit', 1);
        set_config('courseoverviewfilesext', '*');
        $any = self::course_image_form($course);
        after_form_definition::offer_course_image_picker($any, $course);
        $this->assertSame(\MoodleQuickForm_filemanager::class, get_class($any->getElement('overviewfiles_filemanager')));

        set_config('courseoverviewfilesext', '.jpg,.png');
        $control = self::course_image_form($course);
        after_form_definition::offer_course_image_picker($control, $course);
        $this->assertInstanceOf(self::PICKER, $control->getElement('overviewfiles_filemanager'), 'The control: images only.');
    }

    /**
     * Only a plain file manager is replaced, and a field that is the last element stays the last.
     */
    public function test_only_a_plain_file_manager_is_replaced(): void {
        $this->require_plugin();
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $mform = new \MoodleQuickForm('testform', 'post', '/');
        $mform->addElement('text', 'customfield_' . landingfields::SHORTNAME_CREST1, 'Not a picture');
        $mform->addElement('filemanager', 'customfield_' . landingfields::SHORTNAME_SHAREIMAGE, 'Share', null, []);

        after_form_definition::offer_image_picker($mform);

        $this->assertSame('text', $mform->getElement('customfield_' . landingfields::SHORTNAME_CREST1)->getType());
        $this->assertInstanceOf(self::PICKER, $mform->getElement('customfield_' . landingfields::SHORTNAME_SHAREIMAGE));
        $this->assertSame(
            ['customfield_' . landingfields::SHORTNAME_CREST1, 'customfield_' . landingfields::SHORTNAME_SHAREIMAGE],
            self::names($mform)
        );
        $this->assertFalse(imagepicker::replace_filemanager(
            $mform,
            'customfield_' . landingfields::SHORTNAME_SHAREIMAGE,
            imagepicker::PROFILE_SHARE
        ), 'A picker is never replaced again.');
    }

    /**
     * The category editor's image areas follow the plugin both ways; the cover video is a file manager either way.
     */
    public function test_the_category_editor_follows_the_plugin(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        // Not an administrator: the limits are resolved for the user, and an administrator's read as -1.
        $this->setUser(self::getDataGenerator()->create_user());
        $images = array_merge(['heroimage', branding::AREA_COVER], branding::AREA_BADGES);

        $without = self::quickform(new categoryhero(null, ['categoryid' => $categoryid, 'imagepicker' => self::MISSING]));
        foreach (array_merge($images, [branding::AREA_COVERVIDEO]) as $name) {
            $this->assertSame(\MoodleQuickForm_filemanager::class, get_class($without->getElement($name)), $name);
        }

        if (!imagepicker::available()) {
            $this->markTestSkipped('tool_imagepicker is not installed: the half with it is not run.');
        }
        $with = self::quickform(new categoryhero(null, ['categoryid' => $categoryid]));
        foreach ($images as $name) {
            $this->assertInstanceOf(self::PICKER, $with->getElement($name), $name);
        }
        $this->assertSame(\MoodleQuickForm_filemanager::class, get_class($with->getElement(branding::AREA_COVERVIDEO)));
        $hero = self::picker_options($with->getElement('heroimage'));
        $cover = self::picker_options($with->getElement(branding::AREA_COVER));
        $this->assertSame(1920, $hero['cropmaxwidth']);
        $this->assertSame(1280, $cover['cropmaxwidth']);
        if (self::picker_offers_choices()) {
            $this->assertSame(16 / 9, $hero['cropaspectratio']);
            $this->assertSame(['16:9', '21:9', '4:3', 'free'], $hero['cropaspectratios']);
            $this->assertSame(16 / 9, $cover['cropaspectratio']);
            $this->assertSame(['16:9', '2:1', '4:3', 'free'], $cover['cropaspectratios']);
        } else {
            $this->assertNull($hero['cropaspectratio']);
            $this->assertNull($cover['cropaspectratio']);
        }
        $crestbytes = $with->getElement(branding::AREA_BADGES[0])->getMaxbytes();
        $this->assertSame(
            $without->getElement(branding::AREA_BADGES[0])->getMaxbytes(),
            $crestbytes,
            'The crest\'s limit is kept.'
        );
        $this->assertGreaterThan(0, $crestbytes);
        $this->assertLessThanOrEqual(landingfields::CREST_MAXBYTES, $crestbytes);
    }
}
