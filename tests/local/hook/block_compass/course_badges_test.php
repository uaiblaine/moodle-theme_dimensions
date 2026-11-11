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
 * Theme Dimensions - Tests for the answer to block_compass's course_badges hook
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use block_compass\hook\course_badges as compass_hook;
use core\hook\manager;
use core_course\customfield\course_handler;
use local_unlistedcourses\access;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

/**
 * Tests for course_badges::answer(), the theme's listener on block_compass's crests hook.
 *
 * block_compass is optional (not declared in version.php), so every test skips without the hook
 * class; the tests that store a crest skip without customfield_picture as well, as tests/course_badges_test.php
 * does. The hook is dispatched through a hook manager that knows only this theme's own db/hooks.php, so what
 * answers is the registration this repository ships.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(course_badges::class)]
final class course_badges_test extends \advanced_testcase {
    /** @var string A valid 1x1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Skip without block_compass, then provision the landing page fields and load the theme's library.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        if (!class_exists(compass_hook::class)) {
            $this->markTestSkipped('block_compass, with its course_badges hook, is not installed.');
        }
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * Forget local_unlistedcourses' memos and the hook manager a test put in the container.
     *
     * @return void
     */
    protected function tearDown(): void {
        access::reset_caches();
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * Use a hook manager that knows only this theme's db/hooks.php.
     *
     * @return manager The manager now in the container.
     */
    private function theme_hooks(): manager {
        global $CFG;

        $manager = manager::phpunit_get_instance(['theme_dimensions' => $CFG->dirroot . '/theme/dimensions/db/hooks.php']);
        \core\di::set(manager::class, $manager);
        return $manager;
    }

    /**
     * Ask the hook about some courses, as block_compass does.
     *
     * @param int[] $courseids The ids the block is about to draw.
     * @return array What the listeners added, as the hook's get_badges() gives it.
     */
    private function ask(array $courseids): array {
        $hook = new compass_hook($courseids);
        \core\di::get(manager::class)->dispatch($hook);
        return $hook->get_badges();
    }

    /**
     * A course with a landing page model (or none) and badge files in some of its four picture fields.
     *
     * The same fixture as tests/course_badges_test.php.
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
     * The theme's db/hooks.php registers answer() for the block's hook, and nothing else of the theme listens to it.
     *
     * Read through core's own manager, so a callback string core would not resolve - a typo, a moved class, an
     * instance method - fails here rather than leaving the block without crests and saying nothing.
     */
    public function test_the_registration_resolves_to_the_callback(): void {
        $manager = $this->theme_hooks();

        $definitions = $manager->get_callbacks_for_hook(compass_hook::class);

        $this->assertSame([course_badges::class . '::answer'], array_column($definitions, 'callback'));
        $this->assertSame(['theme_dimensions'], array_column($definitions, 'component'));
        $this->assertSame([false], array_column($definitions, 'disabled'), 'Not switched off by a callback override.');
        $listeners = iterator_to_array($manager->getListenersForEvent(new compass_hook([1])), false);
        $this->assertSame([course_badges::class . '::answer'], $listeners, 'Core finds the class and its static method.');
    }

    /**
     * The hook carries crests only for the courses the reader may see: a hidden course and an unlisted one get none.
     *
     * The control is the administrator, who may see all three and is answered for all three, so the two that a
     * student is not answered for are withheld by the viewer's rule and not by a missing file. The student's one
     * answer is the theme's function's own, byte for byte.
     */
    public function test_the_hook_carries_crests_only_for_courses_the_reader_may_see(): void {
        $this->theme_hooks();
        $listed = $this->course_with_badges(landingfields::MODEL_COURSE, ['a.png']);
        $hidden = $this->course_with_badges(landingfields::MODEL_COURSE, ['b.png'], ['visible' => 0]);
        $unlisted = $this->course_with_badges(landingfields::MODEL_COURSE, ['c.png']);
        discoverability::set_state($unlisted, discoverability::STATE_UNLISTED);
        $courses = [$listed, $hidden, $unlisted];

        access::reset_caches();
        $this->assertEqualsCanonicalizing($courses, array_keys($this->ask($courses)), 'Control: the administrator sees all three.');

        $this->setUser(self::getDataGenerator()->create_user());
        access::reset_caches();
        $badges = $this->ask($courses);

        $this->assertSame([$listed], array_keys($badges), 'The student is answered for the listed course alone.');
        access::reset_caches();
        $this->assertSame(theme_dimensions_course_badges([$listed])[$listed], $badges[$listed]);
        $this->assertCount(1, $badges[$listed]);
    }

    /**
     * An id with no crest is not answered at all: not a course with a model and no badge, not a course whose model
     * was cleared over a surviving badge file, not an id with no course.
     *
     * The crested course beside them is the control, so an empty answer is not a listener that never ran.
     */
    public function test_an_id_with_no_crest_adds_nothing(): void {
        $this->theme_hooks();
        $crested = $this->course_with_badges(landingfields::MODEL_COURSE, ['crest.png']);
        $bare = $this->course_with_badges(landingfields::MODEL_COURSE, []);
        $cleared = $this->course_with_badges(0, ['crest.png']);

        $badges = $this->ask([$bare, $crested, 999999, $cleared]);

        $this->assertSame([$crested], array_keys($badges));
        $this->assertSame([], $this->ask([$bare, 999999, $cleared]), 'Nothing at all when no asked course has a crest.');
    }

    /**
     * A fault behind the answer is reported to developers and answers nothing, and the block's response goes on.
     *
     * The control is a source that answers, through the same seam: its crests reach the hook, and an id the block
     * did not ask about is dropped by the hook itself.
     */
    public function test_a_fault_is_reported_and_answers_nothing(): void {
        $hook = new compass_hook([7]);
        course_badges::answer($hook, static function (array $ids): array {
            throw new \coding_exception('the crests are broken');
        });

        $this->assertDebuggingCalled();
        $this->assertSame([], $hook->get_badges());

        $control = new compass_hook([7]);
        $crest = ['url' => 'https://example.com/crest.png', 'alt' => 'Crest'];
        course_badges::answer($control, static fn(array $ids): array => [7 => [$crest], 8 => [$crest]]);
        $this->assertSame([7 => [$crest]], $control->get_badges());
    }
}
