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
 * Theme Dimensions - Tests for the hotsite course custom field provisioning
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
 * Tests for the hotsite course custom field provisioning.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hotsitefields::class)]
final class hotsitefields_test extends \advanced_testcase {
    /**
     * The definitions this site provisions: all of them, less the picture fields where their type is not available.
     *
     * @return array Subset of {@see hotsitefields::definitions()}, keyed by shortname.
     */
    private static function provisioned(): array {
        return array_filter(
            hotsitefields::definitions(),
            static fn(array $definition): bool => $definition['type'] !== hotsitefields::BRASAO_TYPE
                || hotsitefields::picture_type_available()
        );
    }

    /**
     * Skip a test that needs customfield_picture where the optional plugin is not installed and enabled.
     *
     * @return void
     */
    private function require_picture_type(): void {
        if (!hotsitefields::picture_type_available()) {
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
    private static function clear_hotsite_fields(): void {
        hotsitefields::reset_field_cache();
        foreach (array_keys(hotsitefields::definitions()) as $shortname) {
            $field = hotsitefields::get_field($shortname);
            if ($field) {
                api::delete_field_configuration($field);
            }
        }
        hotsitefields::reset_field_cache();

        $storedid = (int) get_config(hotsitefields::COMPONENT, hotsitefields::CATEGORYID_CONFIG);
        if ($storedid) {
            foreach (api::get_categories_with_fields('core_course', 'course', 0) as $category) {
                if ((int) $category->get('id') === $storedid && !$category->get_fields()) {
                    api::delete_category($category);
                }
            }
            unset_config(hotsitefields::CATEGORYID_CONFIG, hotsitefields::COMPONENT);
        }
    }

    /**
     * Provisioning creates every defined field, in one category, with the declared types.
     */
    public function test_ensure_fields_creates_every_field(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_hotsite_fields();

        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $definitions = self::provisioned();
        foreach ($definitions as $shortname => $definition) {
            $field = hotsitefields::get_field($shortname);
            $this->assertNotNull($field, "Field '{$shortname}' was not created.");
            $this->assertSame($definition['type'], $field->get('type'), $shortname);
            $this->assertSame(
                get_string($definition['stringkey'], hotsitefields::COMPONENT),
                $field->get('name'),
                $shortname
            );
        }

        // One category, remembered in config, holding exactly the defined fields.
        $categoryid = (int) get_config(hotsitefields::COMPONENT, hotsitefields::CATEGORYID_CONFIG);
        $this->assertGreaterThan(0, $categoryid);
        $this->assertSame(count($definitions), $DB->count_records('customfield_field', ['categoryid' => $categoryid]));

        // The public description is configured invisible and capped; the page model stays visible to all.
        $descricao = hotsitefields::get_field(hotsitefields::SHORTNAME_DESCRICAOPUBLICA);
        $this->assertEquals(course_handler::NOTVISIBLE, $descricao->get_configdata_property('visibility'));
        $this->assertEquals(200, $descricao->get_configdata_property('maxlength'));
        $modelo = hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO);
        $this->assertEquals(course_handler::VISIBLETOALL, $modelo->get_configdata_property('visibility'));
    }

    /**
     * Running the provisioning again creates nothing new.
     */
    public function test_ensure_fields_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        hotsitefields::reset_field_cache();

        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();

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
        hotsitefields::reset_field_cache();

        $categoryid = (int) get_config(hotsitefields::COMPONENT, hotsitefields::CATEGORYID_CONFIG);
        $field = hotsitefields::get_field(hotsitefields::SHORTNAME_PROGRAMA);
        $this->assertNotNull($field);

        api::delete_field_configuration($field);
        hotsitefields::reset_field_cache();
        $this->assertNull(hotsitefields::get_field(hotsitefields::SHORTNAME_PROGRAMA));

        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $restored = hotsitefields::get_field(hotsitefields::SHORTNAME_PROGRAMA);
        $this->assertNotNull($restored);
        $this->assertSame($categoryid, (int) $restored->get('categoryid'));
    }

    /**
     * A stored category id pointing nowhere falls back to creating a fresh category.
     */
    public function test_stale_category_config_recovers(): void {
        $this->resetAfterTest();
        self::clear_hotsite_fields();
        set_config(hotsitefields::CATEGORYID_CONFIG, 999999, hotsitefields::COMPONENT);

        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $this->assertNotNull(hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO));
        $newid = (int) get_config(hotsitefields::COMPONENT, hotsitefields::CATEGORYID_CONFIG);
        $this->assertGreaterThan(0, $newid);
        $this->assertNotSame(999999, $newid);
    }

    /**
     * A pre-existing foreign field with one of our shortnames is neither adopted nor duplicated.
     */
    public function test_foreign_shortname_is_not_adopted_or_duplicated(): void {
        global $DB;
        $this->resetAfterTest();
        self::clear_hotsite_fields();

        // An admin created a course field with our shortname but another type before the theme arrived.
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['name' => 'Handmade']);
        $generator->create_field([
            'categoryid' => $category->get('id'),
            'shortname' => hotsitefields::SHORTNAME_MODALIDADE,
            'type' => 'text',
        ]);

        hotsitefields::ensure_fields();
        // The type mismatch and the skipped creation are surfaced through debugging().
        $this->assertNotEmpty($this->getDebuggingMessages());
        $this->resetDebugging();

        // Still exactly one field with that shortname, and it is the foreign text one.
        $rows = $DB->get_records('customfield_field', ['shortname' => hotsitefields::SHORTNAME_MODALIDADE]);
        $this->assertCount(1, $rows);
        $this->assertSame('text', reset($rows)->type);

        // Every other field was provisioned normally.
        foreach (self::provisioned() as $shortname => $definition) {
            if ($shortname === hotsitefields::SHORTNAME_MODALIDADE) {
                continue;
            }
            $rows = $DB->get_records('customfield_field', ['shortname' => $shortname]);
            $this->assertCount(1, $rows, $shortname);
            $this->assertSame($definition['type'], reset($rows)->type, $shortname);
        }
    }

    /**
     * The MODELO and MODALIDADE constants match the provisioned option order.
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
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $handler = course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => hotsitefields::MODELO_EVENTO,
            'customfield_' . hotsitefields::SHORTNAME_MODALIDADE => hotsitefields::MODALIDADE_PRESENCIAL,
        ]);

        $modelofield = hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO);
        $stored = $DB->get_record('customfield_data', [
            'fieldid' => $modelofield->get('id'),
            'instanceid' => $course->id,
        ], '*', MUST_EXIST);
        $this->assertSame(hotsitefields::MODELO_EVENTO, (int) $stored->intvalue);

        $exported = $handler->export_instance_data_object($course->id, true);
        $this->assertSame(
            get_string('hotsitefield_modelo_evento', hotsitefields::COMPONENT),
            $exported->{hotsitefields::SHORTNAME_MODELO}
        );
        $this->assertSame(
            get_string('hotsitefield_modalidade_presencial', hotsitefields::COMPONENT),
            $exported->{hotsitefields::SHORTNAME_MODALIDADE}
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
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $handler = course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => hotsitefields::MODELO_EVENTO,
            'customfield_' . hotsitefields::SHORTNAME_MODALIDADE => hotsitefields::MODALIDADE_PRESENCIAL,
            'customfield_' . hotsitefields::SHORTNAME_CARGAHORARIA => 36,
            'customfield_' . hotsitefields::SHORTNAME_PUBLICOALVO . '_editor' => [
                'text' => '<p>Brigadistas voluntários</p>',
                'format' => FORMAT_HTML,
            ],
            'customfield_' . hotsitefields::SHORTNAME_LOCALCIDADE => 'São Paulo & Região',
            'customfield_' . hotsitefields::SHORTNAME_DESCRICAOPUBLICA => 'Curso aberto & gratuito',
        ]);

        $values = hotsitefields::values($course->id);
        $this->assertSame(hotsitefields::MODELO_EVENTO, $values->modelo);
        $this->assertSame(hotsitefields::MODALIDADE_PRESENCIAL, $values->modalidade);
        $this->assertSame(36.0, $values->cargahoraria);
        // The textarea arrives as final HTML for a triple-stash sink.
        $this->assertStringContainsString('<p>', $values->publicoalvo);
        $this->assertStringContainsString('Brigadistas voluntários', $values->publicoalvo);
        // The text field arrives in the plain spelling: the bare ampersand survives unescaped.
        $this->assertSame('São Paulo & Região', $values->cidade);
        $this->assertSame('Curso aberto & gratuito', $values->descricaopublica);
        // Untouched fields read as absent, not as empty strings.
        $this->assertNull($values->programa);
        $this->assertNull($values->localnome);
        // Field names travel along for the page's labels.
        $this->assertSame(
            get_string('hotsitefield_localcidade', hotsitefields::COMPONENT),
            $values->names[hotsitefields::SHORTNAME_LOCALCIDADE]
        );
    }

    /**
     * option_label() maps stored select values back through the configured option order.
     */
    public function test_option_label_follows_configured_options(): void {
        $this->resetAfterTest();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $this->assertSame(
            get_string('hotsitefield_modelo_curso', hotsitefields::COMPONENT),
            hotsitefields::option_label(hotsitefields::SHORTNAME_MODELO, hotsitefields::MODELO_CURSO)
        );
        $this->assertSame(
            get_string('hotsitefield_modalidade_presencial', hotsitefields::COMPONENT),
            hotsitefields::option_label(hotsitefields::SHORTNAME_MODALIDADE, hotsitefields::MODALIDADE_PRESENCIAL)
        );
        $this->assertNull(hotsitefields::option_label(hotsitefields::SHORTNAME_MODELO, 0));
        $this->assertNull(hotsitefields::option_label(hotsitefields::SHORTNAME_MODELO, 99));
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
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $handler = course_handler::create();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_DESCRICAOPUBLICA => 'Para o público externo',
        ]);

        $visible = [];
        foreach ($handler->get_instance_data($course->id) as $data) {
            $visible[] = $data->get_field()->get('shortname');
        }
        $this->assertNotContains(hotsitefields::SHORTNAME_DESCRICAOPUBLICA, $visible);
        $this->assertSame('Para o público externo', hotsitefields::values($course->id)->descricaopublica);
    }

    /**
     * Retiring the old checkbox deletes it and its data, and nothing else.
     *
     * @return void
     */
    public function test_retire_public_field_deletes_the_checkbox_and_nothing_else(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        // Seed the retired checkbox the way the previous version provisioned it: in the theme's category.
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $categoryid = (int) get_config(hotsitefields::COMPONENT, hotsitefields::CATEGORYID_CONFIG);
        $retired = $generator->create_field([
            'categoryid' => $categoryid,
            'shortname' => hotsitefields::SHORTNAME_RETIRED_PUBLICO,
            'name' => 'Public enrolment page',
            'type' => 'checkbox',
        ]);
        $course = self::getDataGenerator()->create_course();
        $generator->add_instance_data($retired, (int) $course->id, 1);
        $modelo = hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO);
        $generator->add_instance_data($modelo, (int) $course->id, hotsitefields::MODELO_CURSO);

        hotsitefields::retire_public_field();

        $this->assertFalse($DB->record_exists('customfield_field', ['id' => $retired->get('id')]));
        $this->assertFalse($DB->record_exists('customfield_data', ['fieldid' => $retired->get('id')]));
        // Control: the other fields, their data and the category are untouched.
        $this->assertTrue($DB->record_exists('customfield_field', ['id' => $modelo->get('id')]));
        $this->assertTrue($DB->record_exists('customfield_data', ['fieldid' => $modelo->get('id')]));
        $this->assertTrue($DB->record_exists('customfield_category', ['id' => $categoryid]));
        $this->assertSame(hotsitefields::MODELO_CURSO, hotsitefields::values($course->id)->modelo);

        // A second call finds nothing and is harmless.
        hotsitefields::retire_public_field();
        $this->assertNotNull(hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO));
    }

    /**
     * The badge fields are pictures visible to nobody with a size cap; the video address is a hidden text field.
     */
    public function test_badge_and_video_fields_are_hidden_and_bounded(): void {
        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        foreach (hotsitefields::BRASAO_SHORTNAMES as $shortname) {
            $field = hotsitefields::get_field($shortname);
            $this->assertNotNull($field, $shortname);
            $this->assertSame(hotsitefields::BRASAO_TYPE, $field->get('type'), $shortname);
            $this->assertEquals(course_handler::NOTVISIBLE, $field->get_configdata_property('visibility'), $shortname);
            $this->assertEquals(hotsitefields::BRASAO_MAXBYTES, $field->get_configdata_property('maximumbytes'), $shortname);
        }
        $video = hotsitefields::get_field(hotsitefields::SHORTNAME_VIDEOURL);
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
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_VIDEOURL => 'https://www.youtube.com/watch?v=Fs7Aph2027x&t=3',
        ]);

        /* A save that does not carry a badge element leaves that field without a row (the
           picture type ignores an absent element rather than emptying the area), so the two
           badge rows are written here, positions 3 and 1, and the files go under them. */
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        $hashes = [];
        $uploads = [hotsitefields::SHORTNAME_BRASAO3 => 'gd-logo.png', hotsitefields::SHORTNAME_BRASAO1 => 'testimage.svg'];
        foreach ($uploads as $shortname => $fixture) {
            $data = $generator->add_instance_data(hotsitefields::get_field($shortname), (int) $course->id, 1);
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

        $values = hotsitefields::values($course->id);
        // The plain spelling: the ampersand of the query string survives as typed.
        $this->assertSame('https://www.youtube.com/watch?v=Fs7Aph2027x&t=3', $values->videourl);
        $this->assertCount(2, $values->brasoes);
        $this->assertContainsOnlyInstancesOf(\stored_file::class, $values->brasoes);
        $this->assertSame($hashes[hotsitefields::SHORTNAME_BRASAO1], $values->brasoes[0]->get_contenthash());
        $this->assertSame($hashes[hotsitefields::SHORTNAME_BRASAO3], $values->brasoes[1]->get_contenthash());

        // A course with nothing uploaded reads as an empty grid and no address.
        $other = self::getDataGenerator()->create_course();
        $this->assertSame([], hotsitefields::values($other->id)->brasoes);
        $this->assertNull(hotsitefields::values($other->id)->videourl);
    }

    /**
     * badges() and model() read the same data as values(), without formatting anything.
     *
     * They are what a badge file request costs; the control is values() itself,
     * which must agree with them file for file.
     */
    public function test_badges_and_model_read_narrowly(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->require_picture_type();
        $this->setAdminUser();
        course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $course = self::getDataGenerator()->create_course();
        $this->assertSame(0, hotsitefields::model($course->id));
        $this->assertSame([], hotsitefields::badges($course->id));

        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => hotsitefields::MODELO_EVENTO,
        ]);
        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        foreach ([hotsitefields::SHORTNAME_BRASAO4, hotsitefields::SHORTNAME_BRASAO2] as $shortname) {
            $data = $generator->add_instance_data(hotsitefields::get_field($shortname), (int) $course->id, 1);
            get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $shortname . '.png',
            ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        }

        $this->assertSame(hotsitefields::MODELO_EVENTO, hotsitefields::model($course->id));
        $badges = hotsitefields::badges($course->id);
        $this->assertSame(['brasao_2.png', 'brasao_4.png'], array_map(fn($f) => $f->get_filename(), $badges));
        $this->assertSame(
            array_map(fn($f) => $f->get_id(), hotsitefields::values($course->id)->brasoes),
            array_map(fn($f) => $f->get_id(), $badges)
        );
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
        self::clear_hotsite_fields();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();

        $field = hotsitefields::get_field(hotsitefields::SHORTNAME_IMAGEMPUBLICA);
        $this->assertNotNull($field);
        $this->assertSame(hotsitefields::BRASAO_TYPE, $field->get('type'));
        $this->assertEquals(course_handler::NOTVISIBLE, $field->get_configdata_property('visibility'));
        $this->assertEquals(hotsitefields::IMAGEMPUBLICA_MAXBYTES, $field->get_configdata_property('maximumbytes'));
        $this->assertSame(
            array_merge(hotsitefields::BRASAO_SHORTNAMES, [hotsitefields::SHORTNAME_IMAGEMPUBLICA]),
            hotsitefields::PICTURE_SHORTNAMES,
            'The backup carries every picture field, and only those.'
        );

        $course = self::getDataGenerator()->create_course();
        $this->assertNull(hotsitefields::share_picture($course->id), 'Nothing uploaded, nothing read.');

        /** @var \core_customfield_generator $generator */
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $context = \core\context\course::instance($course->id);
        $uploads = [hotsitefields::SHORTNAME_BRASAO1 => 'brasao.png', hotsitefields::SHORTNAME_IMAGEMPUBLICA => 'partilha.png'];
        foreach ($uploads as $shortname => $filename) {
            $data = $generator->add_instance_data(hotsitefields::get_field($shortname), (int) $course->id, 1);
            get_file_storage()->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $filename,
            ], $CFG->dirroot . '/lib/tests/fixtures/gd-logo.png');
        }

        $this->assertSame('partilha.png', hotsitefields::share_picture($course->id)?->get_filename());
        $this->assertSame(['brasao.png'], array_map(fn($f) => $f->get_filename(), hotsitefields::badges($course->id)));
        $this->assertSame(['brasao.png'], array_map(fn($f) => $f->get_filename(), hotsitefields::values($course->id)->brasoes));
    }

    /**
     * The bulk reader answers exactly what values() answers, for many courses, in one statement.
     */
    public function test_values_many_matches_values_and_costs_one_statement(): void {
        global $DB;

        $this->resetAfterTest();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();
        $handler = course_handler::create();
        $courses = [];
        foreach ([hotsitefields::MODELO_CURSO, hotsitefields::MODELO_EVENTO, 0] as $i => $modelo) {
            $course = self::getDataGenerator()->create_course();
            $data = ['id' => $course->id, 'customfield_' . hotsitefields::SHORTNAME_LOCALCIDADE => "Town & {$i}"];
            if ($modelo) {
                $data['customfield_' . hotsitefields::SHORTNAME_MODELO] = $modelo;
            }
            $handler->instance_form_save((object) $data);
            $courses[] = (int) $course->id;
        }
        $unset = (int) self::getDataGenerator()->create_course()->id;
        $courses[] = $unset;

        foreach ($courses as $courseid) {
            \core\context\course::instance($courseid);
        }
        $many = hotsitefields::values_many($courses);
        $reads = $DB->perf_get_reads();
        $many = hotsitefields::values_many($courses);
        $this->assertSame(1, $DB->perf_get_reads() - $reads, 'Four courses, one statement.');

        foreach ($courses as $courseid) {
            $this->assertEquals(hotsitefields::values($courseid), $many[$courseid], "Course {$courseid}");
        }
        $this->assertSame(hotsitefields::MODELO_EVENTO, $many[$courses[1]]->modelo);
        $this->assertSame('Town & 1', $many[$courses[1]]->cidade);
        $this->assertSame(0, $many[$unset]->modelo);
        $this->assertNull($many[$unset]->cidade);
        $this->assertSame([], $many[$unset]->brasoes);
        $this->assertArrayHasKey(hotsitefields::SHORTNAME_MODELO, $many[$unset]->names, 'Names come with defaults too.');
        $this->assertSame([], hotsitefields::values_many([]));
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
        self::clear_hotsite_fields();

        \core\plugininfo\customfield::enable_plugin(hotsitefields::BRASAO_TYPE, 0);
        $this->assertFalse(hotsitefields::picture_type_available(), 'Precondition: the type is unavailable.');
        hotsitefields::ensure_fields();
        $this->assertDebuggingNotCalled();
        hotsitefields::reset_field_cache();

        foreach (hotsitefields::PICTURE_SHORTNAMES as $shortname) {
            $this->assertNull(hotsitefields::get_field($shortname), $shortname);
        }
        $this->assertNotNull(hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO), 'Everything else is provisioned.');
        $this->assertSame([], hotsitefields::missing_definitions(), 'Nothing the site can hold is missing.');

        \core\plugininfo\customfield::enable_plugin(hotsitefields::BRASAO_TYPE, 1);
        $this->assertTrue(hotsitefields::picture_type_available());
        hotsitefields::reset_field_cache();
        $this->assertEqualsCanonicalizing(
            hotsitefields::PICTURE_SHORTNAMES,
            array_keys(hotsitefields::missing_definitions()),
            'The control: with the type back, the five picture fields are what is missing.'
        );
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();
        foreach (hotsitefields::PICTURE_SHORTNAMES as $shortname) {
            $this->assertSame(hotsitefields::BRASAO_TYPE, hotsitefields::get_field($shortname)?->get('type'), $shortname);
        }
    }
}
