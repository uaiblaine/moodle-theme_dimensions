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
 * Theme Dimensions - Badge pictures through a course backup
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_course\customfield\course_handler;
use core_customfield\api;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The badge pictures and the share picture survive a course backup and restore although their fields are visible to nobody.
 *
 * backup_theme_dimensions_plugin carries their rows and annotates their
 * file area itself ({@see landingfields::badge_definitions()} says why); this is the
 * test that keeps it doing so. Each test fails without the annotation and fails without the
 * rows: the files come back only when both halves are in the archive.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landingfields::class)]
final class landingfields_backup_test extends \advanced_testcase {
    /**
     * A hidden badge makes the round trip into the copied course.
     */
    public function test_a_hidden_badge_survives_a_course_copy(): void {
        global $CFG;

        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        /* Precondition: core's picture callbacks annotate files only for fields the acting user
           can view (the handler reads get_visible_fields()), while the data rows travel for
           fields the user can view or edit (can_backup()). With no visible picture field the
           theme's annotation is the only route for the files, and a visible one anywhere would
           register core's own and let this test pass for the wrong reason. */
        foreach (api::get_categories_with_fields('core_course', 'course', 0) as $category) {
            foreach ($category->get_fields() as $field) {
                if ($field->get('type') === landingfields::PICTURE_TYPE) {
                    $visibility = $field->get_configdata_property('visibility');
                    $this->assertEquals(course_handler::NOTVISIBLE, $visibility, $field->get('shortname'));
                }
            }
        }

        $course = self::getDataGenerator()->create_course();
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $data = $generator->add_instance_data(landingfields::get_field(landingfields::SHORTNAME_CREST2), (int) $course->id, 1);
        $file = get_file_storage()->create_file_from_pathname([
            'contextid' => \core\context\course::instance($course->id)->id,
            'component' => 'customfield_picture',
            'filearea' => 'file',
            'itemid' => (int) $data->get('id'),
            'filepath' => '/',
            'filename' => 'crest.png',
        ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        $this->assertCount(1, landingfields::values($course->id)->crests);

        $newcourseid = $this->restore_course($this->backup_course($course), $course, '_copy');

        $badges = landingfields::values($newcourseid)->crests;
        $this->assertCount(1, $badges);
        $this->assertSame($file->get_contenthash(), $badges[0]->get_contenthash());
        $this->assertSame('crest.png', $badges[0]->get_filename());
        // The copy holds its own file, in its own context, not a pointer to the original.
        $this->assertNotEquals($file->get_contextid(), $badges[0]->get_contextid());
    }

    /**
     * The hidden share picture makes the same round trip, beside a badge, each onto its own field.
     *
     * Its row travels with the badges' (one "picture" element each in the archive), and it is
     * mapped by its own shortname: restored onto the badge's field it would come back as a fifth
     * tile of the grid and no share picture at all.
     */
    public function test_a_hidden_share_picture_survives_a_course_copy(): void {
        global $CFG;

        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        $files = [];
        $uploads = [landingfields::SHORTNAME_SHAREIMAGE => 'partilha.png', landingfields::SHORTNAME_CREST1 => 'crest.png'];
        foreach ($uploads as $shortname => $filename) {
            $data = $generator->add_instance_data(landingfields::get_field($shortname), (int) $course->id, 1);
            $files[$shortname] = get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $filename,
            ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        }
        $this->assertSame('partilha.png', landingfields::share_picture($course->id)?->get_filename(), 'Precondition.');

        $newcourseid = $this->restore_course($this->backup_course($course), $course, '_copy');

        $share = landingfields::share_picture($newcourseid);
        $this->assertNotNull($share, 'The share picture came back.');
        $this->assertSame('partilha.png', $share->get_filename());
        $this->assertSame($files[landingfields::SHORTNAME_SHAREIMAGE]->get_contenthash(), $share->get_contenthash());
        $this->assertNotEquals($context->id, $share->get_contextid(), 'In the copy, not a pointer to the original.');
        $this->assertSame(
            ['crest.png'],
            array_map(fn($f) => $f->get_filename(), landingfields::badges($newcourseid)),
            'And the badge came back onto its own field, alone in the grid.'
        );
    }

    /**
     * Back a course up to the (unzipped) backup temp directory.
     *
     * @param \stdClass $course Course object to back up.
     * @return string Backup id.
     */
    private function backup_course(\stdClass $course): string {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        // MODE_IMPORT leaves the archive as a directory, which the restore below reads directly.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restore a backup into a new course.
     *
     * @param string $backupid Backup id.
     * @param \stdClass $course Original course object.
     * @param string $suffix Suffix added to the original names.
     * @return int New course id.
     */
    private function restore_course(string $backupid, \stdClass $course, string $suffix): int {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname . $suffix,
            $course->shortname . $suffix,
            $course->category,
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE,
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
