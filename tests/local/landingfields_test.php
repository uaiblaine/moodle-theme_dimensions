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
 * Theme Dimensions - Tests for the landing page course custom field provisioning
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
 * Tests for the landing page course custom field provisioning.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landingfields::class)]
final class landingfields_test extends \advanced_testcase {
    /**
     * The definitions this site provisions: all of them, less the picture fields where their type is not available.
     *
     * @return array Subset of {@see landingfields::definitions()}, keyed by shortname.
     */
    private static function provisioned(): array {
        return array_filter(
            landingfields::definitions(),
            static fn(array $definition): bool => $definition['type'] !== landingfields::PICTURE_TYPE
                || landingfields::picture_type_available()
        );
    }

    /**
     * Skip a test that needs customfield_picture where the optional plugin is not installed and enabled.
     *
     * @return void
     */
    private function require_picture_type(): void {
        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
    }

    /**
     * Remove the fields and category the PHPUnit site install already provisioned.
     *
     * The test site runs db/install.php, so every test of the creation path
     * starts against a baseline that already holds the fields. The request
     * cache is reset around the deletions because it caches negative results
     * too, and it persists across tests in one process.
     *
     * @return void
     */
    private static function clear_landing_fields(): void {
        landingfields::reset_field_cache();
        foreach (array_keys(landingfields::definitions()) as $shortname) {
            $field = landingfields::get_field($shortname);
            if ($field) {
                api::delete_field_configuration($field);
            }
        }
        landingfields::reset_field_cache();

        $storedid = (int) get_config(landingfields::COMPONENT, landingfields::CATEGORYID_CONFIG);
        if ($storedid) {
            foreach (api::get_categories_with_fields('core_course', 'course', 0) as $category) {
                if ((int) $category->get('id') === $storedid && !$category->get_fields()) {
                    api::delete_category($category);
                }
            }
            unset_config(landingfields::CATEGORYID_CONFIG, landingfields::COMPONENT);
        }
    }

    /**
     * Provisioning creates every defined field, in one category, with the declared types.
     */
    public function test_ensure_fields_creates_every_field(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_landing_fields();

        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $definitions = self::provisioned();
        foreach ($definitions as $shortname => $definition) {
            $field = landingfields::get_field($shortname);
            $this->assertNotNull($field, "Field '{$shortname}' was not created.");
            $this->assertSame($definition['type'], $field->get('type'), $shortname);
            $this->assertSame(
                get_string($definition['stringkey'], landingfields::COMPONENT),
                $field->get('name'),
                $shortname
            );
        }

        // One category, remembered in config, holding exactly the defined fields.
        $categoryid = (int) get_config(landingfields::COMPONENT, landingfields::CATEGORYID_CONFIG);
        $this->assertGreaterThan(0, $categoryid);
        $this->assertSame(count($definitions), $DB->count_records('customfield_field', ['categoryid' => $categoryid]));

        // The public description is configured invisible and capped; the page model stays visible to all.
        $description = landingfields::get_field(landingfields::SHORTNAME_PUBLICDESCRIPTION);
        $this->assertEquals(course_handler::NOTVISIBLE, $description->get_configdata_property('visibility'));
        $this->assertEquals(200, $description->get_configdata_property('maxlength'));
        $model = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        $this->assertEquals(course_handler::VISIBLETOALL, $model->get_configdata_property('visibility'));
    }

    /**
     * Running the provisioning again creates nothing new.
     */
    public function test_ensure_fields_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        landingfields::reset_field_cache();

        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();

        $shortnames = array_keys(self::provisioned());
        [$insql, $params] = $DB->get_in_or_equal($shortnames);
        $this->assertSame(
            count($shortnames),
            (int) $DB->count_records_select('customfield_field', "shortname {$insql}", $params)
        );
    }

    /**
     * A deleted field is recreated by the next run, inside the same category.
     */
    public function test_ensure_fields_heals_a_deleted_field(): void {
        $this->resetAfterTest();
        landingfields::reset_field_cache();

        $categoryid = (int) get_config(landingfields::COMPONENT, landingfields::CATEGORYID_CONFIG);
        $field = landingfields::get_field(landingfields::SHORTNAME_PROGRAMME);
        $this->assertNotNull($field);

        api::delete_field_configuration($field);
        landingfields::reset_field_cache();
        $this->assertNull(landingfields::get_field(landingfields::SHORTNAME_PROGRAMME));

        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $restored = landingfields::get_field(landingfields::SHORTNAME_PROGRAMME);
        $this->assertNotNull($restored);
        $this->assertSame($categoryid, (int) $restored->get('categoryid'));
    }

    /**
     * A stored category id pointing nowhere falls back to creating a fresh category.
     */
    public function test_stale_category_config_recovers(): void {
        $this->resetAfterTest();
        self::clear_landing_fields();
        set_config(landingfields::CATEGORYID_CONFIG, 999999, landingfields::COMPONENT);

        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->assertNotNull(landingfields::get_field(landingfields::SHORTNAME_MODEL));
        $newid = (int) get_config(landingfields::COMPONENT, landingfields::CATEGORYID_CONFIG);
        $this->assertGreaterThan(0, $newid);
        $this->assertNotSame(999999, $newid);
    }

    /**
     * A pre-existing foreign field with one of our shortnames is neither adopted nor duplicated.
     */
    public function test_foreign_shortname_is_not_adopted_or_duplicated(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_landing_fields();

        // An admin created a course field with our shortname but another type before the theme arrived.
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['name' => 'Handmade']);
        $generator->create_field([
            'categoryid' => $category->get('id'),
            'shortname' => landingfields::SHORTNAME_MODALITY,
            'type' => 'text',
        ]);

        landingfields::ensure_fields();
        // The type mismatch and the skipped creation are surfaced through debugging().
        $this->assertNotEmpty($this->getDebuggingMessages());
        $this->resetDebugging();

        // Still exactly one field with that shortname, and it is the foreign text one.
        $rows = $DB->get_records('customfield_field', ['shortname' => landingfields::SHORTNAME_MODALITY]);
        $this->assertCount(1, $rows);
        $this->assertSame('text', reset($rows)->type);

        // Every other field was provisioned normally.
        foreach (self::provisioned() as $shortname => $definition) {
            if ($shortname === landingfields::SHORTNAME_MODALITY) {
                continue;
            }
            $rows = $DB->get_records('customfield_field', ['shortname' => $shortname]);
            $this->assertCount(1, $rows, $shortname);
            $this->assertSame($definition['type'], reset($rows)->type, $shortname);
        }
    }

    /**
     * The MODEL and MODALITY constants match the provisioned option order.
     *
     * Select values are stored as a 1-based index into the options list, so a
     * reordering of the options strings would silently change what every
     * stored value means. This pins constant => option text both on the raw
     * stored value and through the export path.
     */
    public function test_select_constants_follow_option_order(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $handler = course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
        ]);

        $modelfield = landingfields::get_field(landingfields::SHORTNAME_MODEL);
        $stored = $DB->get_record('customfield_data', [
            'fieldid' => $modelfield->get('id'),
            'instanceid' => $course->id,
        ], '*', MUST_EXIST);
        $this->assertSame(landingfields::MODEL_EVENT, (int) $stored->intvalue);

        $exported = $handler->export_instance_data_object($course->id, true);
        $this->assertSame(
            get_string('landingfield_model_event', landingfields::COMPONENT),
            $exported->{landingfields::SHORTNAME_MODEL}
        );
        $this->assertSame(
            get_string('landingfield_modality_inperson', landingfields::COMPONENT),
            $exported->{landingfields::SHORTNAME_MODALITY}
        );
    }

    /**
     * values() reads each field the way its sink needs it.
     *
     * The city carries a bare ampersand on purpose: it is the one fixture that
     * tells the plain spelling apart from the escaped one (a tag-shaped fixture
     * is stripped identically in both modes and proves nothing).
     */
    public function test_values_reads_types_correctly(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $handler = course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 36,
            'customfield_' . landingfields::SHORTNAME_AUDIENCE . '_editor' => [
                'text' => '<p>Brigadistas voluntários</p>',
                'format' => FORMAT_HTML,
            ],
            'customfield_' . landingfields::SHORTNAME_CITY => 'São Paulo & Região',
            'customfield_' . landingfields::SHORTNAME_PUBLICDESCRIPTION => 'Curso aberto & gratuito',
        ]);

        $values = landingfields::values($course->id);
        $this->assertSame(landingfields::MODEL_EVENT, $values->model);
        $this->assertSame(landingfields::MODALITY_INPERSON, $values->modality);
        $this->assertSame(36.0, $values->workload);
        // The textarea arrives as final HTML for a triple-stash sink.
        $this->assertStringContainsString('<p>', $values->audience);
        $this->assertStringContainsString('Brigadistas voluntários', $values->audience);
        // The text field arrives in the plain spelling: the bare ampersand survives unescaped.
        $this->assertSame('São Paulo & Região', $values->city);
        $this->assertSame('Curso aberto & gratuito', $values->publicdescription);
        // Untouched fields read as absent, not as empty strings.
        $this->assertNull($values->programme);
        $this->assertNull($values->venue);
        // Field names travel along for the page's labels.
        $this->assertSame(
            get_string('landingfield_city', landingfields::COMPONENT),
            $values->names[landingfields::SHORTNAME_CITY]
        );
    }

    /**
     * option_label() maps stored select values back through the configured option order.
     */
    public function test_option_label_follows_configured_options(): void {
        $this->resetAfterTest();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $this->assertSame(
            get_string('landingfield_model_course', landingfields::COMPONENT),
            landingfields::option_label(landingfields::SHORTNAME_MODEL, landingfields::MODEL_COURSE)
        );
        $this->assertSame(
            get_string('landingfield_modality_inperson', landingfields::COMPONENT),
            landingfields::option_label(landingfields::SHORTNAME_MODALITY, landingfields::MODALITY_INPERSON)
        );
        $this->assertNull(landingfields::option_label(landingfields::SHORTNAME_MODEL, 0));
        $this->assertNull(landingfields::option_label(landingfields::SHORTNAME_MODEL, 99));
    }

    /**
     * The public description is never displayed, but the page reads it past the handler's filter.
     *
     * @return void
     */
    public function test_public_description_is_hidden_from_display(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $handler = course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_PUBLICDESCRIPTION => 'Para o público externo',
        ]);

        $visible = [];
        foreach ($handler->get_instance_data($course->id) as $data) {
            $visible[] = $data->get_field()->get('shortname');
        }
        $this->assertNotContains(landingfields::SHORTNAME_PUBLICDESCRIPTION, $visible);
        $this->assertSame('Para o público externo', landingfields::values($course->id)->publicdescription);
    }

    /**
     * The badge fields are pictures visible to nobody with a size cap; the video address is a hidden text field.
     */
    public function test_badge_and_video_fields_are_hidden_and_bounded(): void {
        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        foreach (landingfields::CREST_SHORTNAMES as $shortname) {
            $field = landingfields::get_field($shortname);
            $this->assertNotNull($field, $shortname);
            $this->assertSame(landingfields::PICTURE_TYPE, $field->get('type'), $shortname);
            $this->assertEquals(course_handler::NOTVISIBLE, $field->get_configdata_property('visibility'), $shortname);
            $this->assertEquals(landingfields::CREST_MAXBYTES, $field->get_configdata_property('maximumbytes'), $shortname);
        }
        $video = landingfields::get_field(landingfields::SHORTNAME_VIDEOURL);
        $this->assertNotNull($video);
        $this->assertSame('text', $video->get('type'));
        $this->assertEquals(course_handler::NOTVISIBLE, $video->get_configdata_property('visibility'));
        $this->assertEquals(255, $video->get_configdata_property('maxlength'));
    }

    /**
     * values() returns the badge files in grid order, closing gaps, and the video address as typed.
     *
     * The picture rows are written for positions 3 and 1 and read back as [1, 3]:
     * the order is the grid's, not the order the files were uploaded in, and the
     * empty positions 2 and 4 leave no hole.
     */
    public function test_values_reads_badges_in_grid_order_and_the_video_url(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_VIDEOURL => 'https://www.youtube.com/watch?v=Fs7Aph2027x&t=3',
        ]);

        /* A save that does not carry a badge element leaves that field without a row (the
           picture type ignores an absent element rather than emptying the area), so the two
           badge rows are written here, positions 3 and 1, and the files go under them. */
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        $hashes = [];
        $uploads = [landingfields::SHORTNAME_CREST3 => 'gd-logo.png', landingfields::SHORTNAME_CREST1 => 'testimage.svg'];
        foreach ($uploads as $shortname => $fixture) {
            $data = $generator->add_instance_data(landingfields::get_field($shortname), (int) $course->id, 1);
            $path = $fixture === 'gd-logo.png'
                ? $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png'
                : $CFG->dirroot . '/lib/filestorage/tests/fixtures/testimage_viewbox.svg';
            $file = get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $fixture,
            ], $path);
            $hashes[$shortname] = $file->get_contenthash();
        }

        $values = landingfields::values($course->id);
        // The plain spelling: the ampersand of the query string survives as typed.
        $this->assertSame('https://www.youtube.com/watch?v=Fs7Aph2027x&t=3', $values->videourl);
        $this->assertCount(2, $values->crests);
        $this->assertContainsOnlyInstancesOf(\stored_file::class, $values->crests);
        $this->assertSame($hashes[landingfields::SHORTNAME_CREST1], $values->crests[0]->get_contenthash());
        $this->assertSame($hashes[landingfields::SHORTNAME_CREST3], $values->crests[1]->get_contenthash());

        // A course with nothing uploaded reads as an empty grid and no address.
        $other = self::getDataGenerator()->create_course();
        $this->assertSame([], landingfields::values($other->id)->crests);
        $this->assertNull(landingfields::values($other->id)->videourl);
    }

    /**
     * badges() and model() read the same data as values(), without formatting anything.
     *
     * They are what a badge file request costs; the control is values() itself,
     * which must agree with them file for file.
     */
    public function test_badges_and_model_read_narrowly(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $this->assertSame(0, landingfields::model($course->id));
        $this->assertSame([], landingfields::badges($course->id));

        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
        ]);
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        foreach ([landingfields::SHORTNAME_CREST4, landingfields::SHORTNAME_CREST2] as $shortname) {
            $data = $generator->add_instance_data(landingfields::get_field($shortname), (int) $course->id, 1);
            get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $shortname . '.png',
            ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        }

        $this->assertSame(landingfields::MODEL_EVENT, landingfields::model($course->id));
        $badges = landingfields::badges($course->id);
        $this->assertSame(
            [landingfields::SHORTNAME_CREST2 . '.png', landingfields::SHORTNAME_CREST4 . '.png'],
            array_map(fn($f) => $f->get_filename(), $badges)
        );
        $this->assertSame(
            array_map(fn($f) => $f->get_id(), landingfields::values($course->id)->crests),
            array_map(fn($f) => $f->get_id(), $badges)
        );

        /* The narrow read, counted in statements once the caches the calls above filled are warm: one bulk read of
           the course's field data (core's get_instance_data(), a recordset, which counts three statements on
           PostgreSQL and one elsewhere), plus two file reads for badges(). Changes that must make it fail: badges()
           or model() answered through values(), which reads the whole record another way. */
        $recordset = $DB->get_dbfamily() === 'postgres' ? 3 : 1;
        $reads = $DB->perf_get_reads();
        landingfields::badges($course->id);
        $badgereads = $DB->perf_get_reads() - $reads;
        $this->assertSame($recordset + 2, $badgereads, 'badges() reads the picture fields and their files alone.');
        $reads = $DB->perf_get_reads();
        landingfields::model($course->id);
        $this->assertSame($recordset, $DB->perf_get_reads() - $reads, 'model() reads the course data alone.');
        $reads = $DB->perf_get_reads();
        landingfields::values($course->id);
        $this->assertNotSame($badgereads, $DB->perf_get_reads() - $reads, 'The control: values() is not the narrow read.');
    }

    /**
     * The share picture is a hidden picture field of its own, and neither reader answers with the other's file.
     *
     * The badge beside it is the control: share_picture() must not pick up a badge, and the badge grid
     * must not grow a fifth tile out of the share picture - both are files of the same area of the
     * same course, told apart only by the data row they hang from.
     */
    public function test_the_share_picture_is_hidden_bounded_and_read_apart_from_the_badges(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        // Provisioned here, from the definitions as they stand: the site install made the field
        // already, and a test reading that one would pin the install's definition, not this one.
        self::clear_landing_fields();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        $field = landingfields::get_field(landingfields::SHORTNAME_SHAREIMAGE);
        $this->assertNotNull($field);
        $this->assertSame(landingfields::PICTURE_TYPE, $field->get('type'));
        $this->assertEquals(course_handler::NOTVISIBLE, $field->get_configdata_property('visibility'));
        $this->assertEquals(landingfields::SHAREIMAGE_MAXBYTES, $field->get_configdata_property('maximumbytes'));
        $this->assertSame(
            array_merge(landingfields::CREST_SHORTNAMES, [landingfields::SHORTNAME_SHAREIMAGE]),
            landingfields::PICTURE_SHORTNAMES,
            'The backup carries every picture field, and only those.'
        );

        $course = self::getDataGenerator()->create_course();
        $this->assertNull(landingfields::share_picture($course->id), 'Nothing uploaded, nothing read.');

        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        $uploads = [landingfields::SHORTNAME_CREST1 => 'crest.png', landingfields::SHORTNAME_SHAREIMAGE => 'partilha.png'];
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

        $this->assertSame('partilha.png', landingfields::share_picture($course->id)?->get_filename());
        $this->assertSame(['crest.png'], array_map(fn($f) => $f->get_filename(), landingfields::badges($course->id)));
        $this->assertSame(['crest.png'], array_map(fn($f) => $f->get_filename(), landingfields::values($course->id)->crests));
    }

    /**
     * The bulk reader answers exactly what values() answers, for many courses, in one statement.
     */
    public function test_values_many_matches_values_and_costs_one_statement(): void {
        global $DB;

        $this->resetAfterTest();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $handler = course_handler::create();
        $courses = [];
        foreach ([landingfields::MODEL_COURSE, landingfields::MODEL_EVENT, 0] as $i => $model) {
            $course = self::getDataGenerator()->create_course();
            $data = ['id' => $course->id, 'customfield_' . landingfields::SHORTNAME_CITY => "Town & {$i}"];
            if ($model) {
                $data['customfield_' . landingfields::SHORTNAME_MODEL] = $model;
            }
            $handler->instance_form_save((object) $data);
            $courses[] = (int) $course->id;
        }
        $unset = (int) self::getDataGenerator()->create_course()->id;
        $courses[] = $unset;

        foreach ($courses as $courseid) {
            \core\context\course::instance($courseid);
        }
        $many = landingfields::values_many($courses);
        $reads = $DB->perf_get_reads();
        $many = landingfields::values_many($courses);
        $this->assertSame(1, $DB->perf_get_reads() - $reads, 'Four courses, one statement.');

        foreach ($courses as $courseid) {
            $this->assertEquals(landingfields::values($courseid), $many[$courseid], "Course {$courseid}");
        }
        $this->assertSame(landingfields::MODEL_EVENT, $many[$courses[1]]->model);
        $this->assertSame('Town & 1', $many[$courses[1]]->city);
        $this->assertSame(0, $many[$unset]->model);
        $this->assertNull($many[$unset]->city);
        $this->assertSame([], $many[$unset]->crests);
        $this->assertArrayHasKey(landingfields::SHORTNAME_MODEL, $many[$unset]->names, 'Names come with defaults too.');
        $this->assertSame([], landingfields::values_many([]));
    }

    /**
     * Without the picture type the five picture fields are skipped quietly, and enabling it provisions them.
     *
     * customfield_picture is optional. A disabled type stands in for an absent one here, because the
     * suite runs where the plugin is installed: core hides a disabled type's fields, and the provisioner
     * must neither try to create them (a debugging line and a lock on every call) nor miss them once the
     * type is back. The control is the same call after the type is enabled again.
     */
    public function test_the_picture_fields_are_skipped_while_the_type_is_unavailable(): void {
        $this->resetAfterTest();
        $this->require_picture_type();
        self::clear_landing_fields();

        \core\plugininfo\customfield::enable_plugin(landingfields::PICTURE_TYPE, 0);
        $this->assertFalse(landingfields::picture_type_available(), 'Precondition: the type is unavailable.');
        landingfields::ensure_fields();
        $this->assertDebuggingNotCalled();
        landingfields::reset_field_cache();

        foreach (landingfields::PICTURE_SHORTNAMES as $shortname) {
            $this->assertNull(landingfields::get_field($shortname), $shortname);
        }
        $this->assertNotNull(landingfields::get_field(landingfields::SHORTNAME_MODEL), 'Everything else is provisioned.');
        $this->assertSame([], landingfields::missing_definitions(), 'Nothing the site can hold is missing.');

        \core\plugininfo\customfield::enable_plugin(landingfields::PICTURE_TYPE, 1);
        $this->assertTrue(landingfields::picture_type_available());
        landingfields::reset_field_cache();
        $this->assertEqualsCanonicalizing(
            landingfields::PICTURE_SHORTNAMES,
            array_keys(landingfields::missing_definitions()),
            'The control: with the type back, the five picture fields are what is missing.'
        );
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        foreach (landingfields::PICTURE_SHORTNAMES as $shortname) {
            $this->assertSame(landingfields::PICTURE_TYPE, landingfields::get_field($shortname)?->get('type'), $shortname);
        }
    }

    /**
     * The list of field types the cheap readers check is the definitions' own, order included.
     *
     * get_field(), the field cache and values_many() read FIELD_TYPES so as not to build the
     * definitions, which call get_string() for every select option: a field added to one and not the
     * other, or a type changed in one, would make a provisioned field unreadable or a foreign one adopted.
     */
    public function test_the_field_types_are_the_definitions_types(): void {
        $this->assertSame(
            array_map(static fn(array $definition): string => $definition['type'], landingfields::definitions()),
            landingfields::FIELD_TYPES
        );
        $this->expectException(\coding_exception::class);
        landingfields::get_field('dimensions_nosuchfield');
    }

    /**
     * The badges read in bulk are the files customfield_picture's get_file() answers, row by row, in one statement.
     *
     * get_file() reads the row's own context, directories left out, and takes the first file by item id, path and
     * name. The fixture holds what each of those rules decides: a row with two files (the first by name wins), a
     * row with no file at all, a course with no row (a default, no file), and a decoy - a file of the same area
     * and the same item id as a badge row, under ANOTHER course on the page - which sorts first by name and must
     * be left out because it is not the row's context.
     */
    public function test_the_badge_files_read_in_one_statement_are_the_files_get_file_answers(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $fs = get_file_storage();
        $logo = $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png';
        $put = function (int $contextid, int $itemid, string $filename) use ($fs, $logo): void {
            $fs->create_file_from_pathname([
                'contextid' => $contextid,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => $filename,
            ], $logo);
        };
        $row = fn(string $shortname, int $courseid): int => (int) $generator->add_instance_data(
            landingfields::get_field($shortname),
            $courseid,
            1
        )->get('id');

        $x = (int) self::getDataGenerator()->create_course()->id;
        $y = (int) self::getDataGenerator()->create_course()->id;
        $z = (int) self::getDataGenerator()->create_course()->id;
        $xcontext = \core\context\course::instance($x)->id;
        $ycontext = \core\context\course::instance($y)->id;

        $xfirst = $row(landingfields::SHORTNAME_CREST1, $x);
        $put($xcontext, $xfirst, 'crest-x.png');
        $xsecond = $row(landingfields::SHORTNAME_CREST2, $x);
        $put($xcontext, $xsecond, 'b-second.png');
        $put($xcontext, $xsecond, 'a-first.png');
        $row(landingfields::SHORTNAME_CREST3, $x);
        $yfirst = $row(landingfields::SHORTNAME_CREST4, $y);
        $put($ycontext, $yfirst, 'crest-y.png');
        // The decoy: X's badge row's item id, under Y's context, first by name.
        $put($ycontext, $xfirst, 'a-decoy.png');

        $ids = fn(array $files): array => array_map(static fn(\stored_file $file): int => (int) $file->get_id(), $files);
        $courses = [$x, $y, $z];
        foreach ($courses as $courseid) {
            \core\context\course::instance($courseid);
        }
        $many = landingfields::values_many($courses, false);
        foreach ($courses as $courseid) {
            $this->assertSame(
                $ids(landingfields::badges($courseid)),
                $ids($many[$courseid]->crests),
                "Course {$courseid}: the bulk read is get_file()'s answer, badge by badge."
            );
        }
        $this->assertSame(['crest-x.png', 'a-first.png'], array_map(fn($f) => $f->get_filename(), $many[$x]->crests));
        $this->assertSame(['crest-y.png'], array_map(fn($f) => $f->get_filename(), $many[$y]->crests));
        $this->assertSame([], $many[$z]->crests);

        $reads = $DB->perf_get_reads();
        landingfields::values_many($courses, false);
        $this->assertSame(2, $DB->perf_get_reads() - $reads, 'The rows, then every badge file of the three courses.');
    }

    /**
     * A field's name is formatted once for many courses only where the string filters do not run.
     *
     * With filterall on, format_string() runs the filters active in each course's context, so the same name can read
     * differently on two courses: here the multilang filter is switched off in one of them. Each course must then get
     * the name values() gives it alone; the course with the filter on is the control that the filter runs at all.
     */
    public function test_a_field_name_is_formatted_in_each_courses_context_while_the_string_filters_run(): void {
        global $CFG, $FILTERLIB_PRIVATE;

        $this->resetAfterTest();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $field = landingfields::get_field(landingfields::SHORTNAME_WORKLOAD);
        $field->set('name', '<span lang="en" class="multilang">Hours</span><span lang="xx" class="multilang">Horas</span>');
        $field->save();
        course_handler::reset_caches();
        landingfields::reset_field_cache();

        $on = (int) self::getDataGenerator()->create_course()->id;
        $off = (int) self::getDataGenerator()->create_course()->id;
        filter_set_global_state('multilang', TEXTFILTER_ON);
        // A filter runs over format_string() only where it is set to apply to headings as well as content.
        filter_set_applies_to_strings('multilang', true);
        filter_set_local_state('multilang', \core\context\course::instance($off)->id, TEXTFILTER_OFF);
        $CFG->filterall = 1;
        $FILTERLIB_PRIVATE = null;
        \filter_manager::reset_caches();

        $alone = [
            $on => landingfields::values($on)->names[landingfields::SHORTNAME_WORKLOAD],
            $off => landingfields::values($off)->names[landingfields::SHORTNAME_WORKLOAD],
        ];
        $this->assertSame('Hours', $alone[$on], 'Precondition: the filter runs where it is on.');
        $this->assertNotSame($alone[$on], $alone[$off], 'Precondition: and the name reads otherwise where it is off.');

        $many = landingfields::values_many([$on, $off], false);
        foreach ($alone as $courseid => $name) {
            $this->assertSame($name, $many[$courseid]->names[landingfields::SHORTNAME_WORKLOAD], "Course {$courseid}.");
        }
    }

    /**
     * The upgrade step appends the trail line to a model select that holds the two lines it was created with, and only there.
     *
     * A fresh install provisions all three lines; a site provisioned before the trail holds two. A third line an
     * administrator added keeps its position and its words, and a site without the field gets nothing.
     */
    public function test_the_trail_line_is_appended_to_a_two_line_model_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        self::clear_landing_fields();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $trail = get_string('landingfield_model_trail', landingfields::COMPONENT);
        $configure = function (string $options): void {
            $field = landingfields::get_field(landingfields::SHORTNAME_MODEL);
            $config = (array) $field->get('configdata');
            $config['options'] = $options;
            api::save_field_configuration($field, (object) ['configdata' => $config]);
            landingfields::reset_field_cache();
        };

        $this->assertSame($trail, landingfields::option_label(landingfields::SHORTNAME_MODEL, landingfields::MODEL_TRAIL));
        $this->assertFalse(landingfields::add_trail_option(), 'A fresh install already has the line.');

        $configure("Course\nEvent");
        $this->assertNull(
            landingfields::option_label(landingfields::SHORTNAME_MODEL, landingfields::MODEL_TRAIL),
            'Precondition: a site provisioned before the trail.'
        );
        $this->assertTrue(landingfields::add_trail_option());
        $this->assertSame(['Course', 'Event', $trail], landingfields::option_lines(landingfields::SHORTNAME_MODEL));
        $this->assertFalse(landingfields::add_trail_option(), 'Run again, the step appends nothing.');

        $configure("Course\nEvent\nWorkshop");
        $this->assertFalse(landingfields::add_trail_option());
        $this->assertSame(['Course', 'Event', 'Workshop'], landingfields::option_lines(landingfields::SHORTNAME_MODEL));

        api::delete_field_configuration(landingfields::get_field(landingfields::SHORTNAME_MODEL));
        landingfields::reset_field_cache();
        $this->assertFalse(landingfields::add_trail_option());
    }
}
