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
 * Tests for the format and hybrid landing page fields: the hybrid modality, the format and the live classes
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_course\customfield\course_handler;
use core_customfield\api;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The format and hybrid fields are provisioned in place, upgraded in place, and read without contradiction.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(landingfields::class)]
final class landingfields_round6_test extends \advanced_testcase {
    /**
     * Provision every field from scratch, the way a fresh install does.
     *
     * The PHPUnit site was installed with the fields of whatever version it was
     * initialised under, so a test of the definitions deletes them first and
     * provisions them again ({@see \theme_dimensions\local\landingfields_test}).
     *
     * @return void
     */
    private static function provision_fresh(): void {
        landingfields::reset_field_cache();
        foreach (array_keys(landingfields::definitions()) as $shortname) {
            $field = landingfields::get_field($shortname);
            if ($field) {
                api::delete_field_configuration($field);
            }
        }
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * The shortnames of the theme's category, in the order the course form lists them.
     *
     * @return string[] Shortnames, sorted by sortorder.
     */
    private static function field_order(): array {
        global $DB;
        $categoryid = (int) landingfields::get_field(landingfields::SHORTNAME_MODEL)->get('categoryid');
        return array_values($DB->get_fieldset_select(
            'customfield_field',
            'shortname',
            'categoryid = :categoryid ORDER BY sortorder, id',
            ['categoryid' => $categoryid]
        ));
    }

    /**
     * The options of a select field as stored, one line each.
     *
     * @param string $shortname The select's shortname.
     * @return string[] The option lines.
     */
    private static function options(string $shortname): array {
        landingfields::reset_field_cache();
        $options = (string) landingfields::get_field($shortname)->get_configdata_property('options');
        return preg_split("/\s*\n\s*/", trim($options));
    }

    /**
     * A fresh site gets the five fields, typed, right after the workload, and three modalities.
     */
    public function test_a_fresh_site_gets_the_round6_fields_in_place(): void {
        $this->resetAfterTest();
        self::provision_fresh();

        $types = [
            landingfields::SHORTNAME_FORMAT => 'select',
            landingfields::SHORTNAME_LIVECLASSES => 'checkbox',
            landingfields::SHORTNAME_LIVECLASSESWHEN => 'text',
            landingfields::SHORTNAME_INPERSONWORKLOAD => 'number',
            landingfields::SHORTNAME_MEETINGS => 'text',
        ];
        foreach ($types as $shortname => $type) {
            $field = landingfields::get_field($shortname);
            $this->assertNotNull($field, $shortname);
            $this->assertSame($type, $field->get('type'), $shortname);
        }

        $order = self::field_order();
        $workload = array_search(landingfields::SHORTNAME_WORKLOAD, $order, true);
        $this->assertSame(
            array_merge([landingfields::SHORTNAME_WORKLOAD], landingfields::ROUND6_SHORTNAMES),
            array_slice($order, $workload, 6)
        );
        $this->assertSame(
            [
                get_string('landingfield_modality_online', landingfields::COMPONENT),
                get_string('landingfield_modality_inperson', landingfields::COMPONENT),
                get_string('landingfield_modality_hybrid', landingfields::COMPONENT),
            ],
            self::options(landingfields::SHORTNAME_MODALITY)
        );
    }

    /**
     * The hybrid and format constants name the options at their positions, through the export path.
     */
    public function test_the_new_constants_follow_the_option_order(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        self::provision_fresh();

        $handler = course_handler::create();
        $course = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_HYBRID,
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_SELFPACED,
        ]);
        $other = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $other->id,
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
        ]);

        $exported = $handler->export_instance_data_object($course->id, true);
        $this->assertSame(
            get_string('landingfield_modality_hybrid', landingfields::COMPONENT),
            $exported->{landingfields::SHORTNAME_MODALITY}
        );
        $this->assertSame(
            get_string('landingfield_format_selfpaced', landingfields::COMPONENT),
            $exported->{landingfields::SHORTNAME_FORMAT}
        );
        $this->assertSame(
            get_string('landingfield_format_tutored', landingfields::COMPONENT),
            $handler->export_instance_data_object($other->id, true)->{landingfields::SHORTNAME_FORMAT}
        );
    }

    /**
     * An upgraded site ends where a fresh one starts: the third modality appended once, the fields in place.
     *
     * The site before the format and hybrid fields is rebuilt from a fresh one: the modality cut back to its two
     * lines, the five fields deleted and provisioned again, which appends them after the share
     * picture - where ensure_fields() puts them on a real upgrade. A course already saved as
     * in-person keeps its meaning across the step.
     */
    public function test_the_upgrade_appends_the_option_once_and_places_the_fields(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        self::provision_fresh();
        $fresh = self::field_order();

        $modality = landingfields::get_field(landingfields::SHORTNAME_MODALITY);
        $config = (array) $modality->get('configdata');
        $config['options'] = "Online\nPresencial";
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $modality->get('id')]);
        foreach (landingfields::ROUND6_SHORTNAMES as $shortname) {
            api::delete_field_configuration(landingfields::get_field($shortname));
        }
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        $course = self::getDataGenerator()->create_course();
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
        ]);

        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        // The precondition the placement exists for: appended after the share picture.
        $this->assertSame(landingfields::ROUND6_SHORTNAMES, array_slice(self::field_order(), -5));

        $this->assertTrue(landingfields::add_hybrid_option());
        $this->assertSame(
            ['Online', 'Presencial', get_string('landingfield_modality_hybrid', landingfields::COMPONENT)],
            self::options(landingfields::SHORTNAME_MODALITY)
        );
        $this->assertFalse(landingfields::add_hybrid_option());
        $this->assertCount(3, self::options(landingfields::SHORTNAME_MODALITY));

        landingfields::place_format_fields();
        $this->assertSame($fresh, self::field_order());

        $this->assertSame(landingfields::MODALITY_INPERSON, landingfields::values($course->id)->modality);
    }

    /**
     * A third line an administrator added is left alone: appending a fourth would make theirs "hybrid".
     */
    public function test_a_select_an_administrator_extended_is_left_alone(): void {
        global $DB;
        $this->resetAfterTest();
        self::provision_fresh();

        $modality = landingfields::get_field(landingfields::SHORTNAME_MODALITY);
        $config = (array) $modality->get('configdata');
        $config['options'] = "Online\nPresencial\nSemipresencial";
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $modality->get('id')]);

        $this->assertFalse(landingfields::add_hybrid_option());
        $this->assertSame(['Online', 'Presencial', 'Semipresencial'], self::options(landingfields::SHORTNAME_MODALITY));
    }

    /**
     * values() reads the format and hybrid fields of a tutored hybrid course, and reads them through coherent().
     */
    public function test_values_reads_the_round6_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        self::provision_fresh();

        $handler = course_handler::create();
        $course = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_HYBRID,
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_TUTORED,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSES => 1,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSESWHEN => 'Quartas & sábados, 19h',
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 60,
            'customfield_' . landingfields::SHORTNAME_INPERSONWORKLOAD => 16,
            'customfield_' . landingfields::SHORTNAME_MEETINGS => '2 encontros aos sábados',
        ]);
        $values = landingfields::values($course->id);
        $this->assertSame(landingfields::FORMAT_TUTORED, $values->format);
        $this->assertTrue($values->liveclasses);
        $this->assertSame('Quartas & sábados, 19h', $values->liveclasseswhen);
        $this->assertSame(16.0, $values->inpersonworkload);
        $this->assertSame('2 encontros aos sábados', $values->meetings);

        // The same live classes on a self-paced course are read away: values() goes through coherent().
        $selfpaced = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $selfpaced->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_ONLINE,
            'customfield_' . landingfields::SHORTNAME_FORMAT => landingfields::FORMAT_SELFPACED,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSES => 1,
            'customfield_' . landingfields::SHORTNAME_LIVECLASSESWHEN => 'Quartas, 19h',
        ]);
        $values = landingfields::values($selfpaced->id);
        $this->assertSame(landingfields::FORMAT_SELFPACED, $values->format);
        $this->assertFalse($values->liveclasses);
        $this->assertNull($values->liveclasseswhen);
    }

    /**
     * Stored combinations and what the page may read of them.
     *
     * @return array Name => [stored values, expected values], each over the format and hybrid keys.
     */
    public static function coherence_cases(): array {
        $c = landingfields::MODEL_COURSE;
        $e = landingfields::MODEL_EVENT;
        $on = landingfields::MODALITY_ONLINE;
        $pr = landingfields::MODALITY_INPERSON;
        $hy = landingfields::MODALITY_HYBRID;
        $sp = landingfields::FORMAT_SELFPACED;
        $tu = landingfields::FORMAT_TUTORED;
        $all = [true, 'Quartas', 16.0, '2 encontros'];
        return [
            'tutored hybrid course keeps everything' => [[$c, $hy, $tu, ...$all], [$tu, ...$all]],
            'tutored in-person course keeps its format and live classes' => [
                [$c, $pr, $tu, ...$all],
                [$tu, true, 'Quartas', null, null],
            ],
            'tutored online course drops the in-person figures' => [
                [$c, $on, $tu, ...$all],
                [$tu, true, 'Quartas', null, null],
            ],
            'self-paced online course drops the live classes' => [
                [$c, $on, $sp, ...$all],
                [$sp, false, null, null, null],
            ],
            'self-paced hybrid course drops the format and everything in person' => [
                [$c, $hy, $sp, ...$all],
                [0, false, null, null, null],
            ],
            'self-paced in-person course drops the format' => [
                [$c, $pr, $sp, false, null, null, null],
                [0, false, null, null, null],
            ],
            'an event has no format and no live classes' => [
                [$e, $hy, $tu, ...$all],
                [0, false, null, 16.0, '2 encontros'],
            ],
            'when, without live classes, is not said' => [
                [$c, $on, $tu, false, 'Quartas', null, null],
                [$tu, false, null, null, null],
            ],
        ];
    }

    /**
     * coherent() empties exactly what contradicts the rest, and nothing else.
     *
     * @param array $stored model, modality, format, liveclasses, liveclasseswhen, inpersonworkload, meetings.
     * @param array $expected format, liveclasses, liveclasseswhen, inpersonworkload, meetings as read.
     */
    #[DataProvider('coherence_cases')]
    public function test_coherent_reads_without_contradiction(array $stored, array $expected): void {
        [$model, $modality, $format, $liveclasses, $when, $inperson, $meetings] = $stored;
        $values = landingfields::coherent((object) [
            'model' => $model,
            'modality' => $modality,
            'format' => $format,
            'liveclasses' => $liveclasses,
            'liveclasseswhen' => $when,
            'inpersonworkload' => $inperson,
            'meetings' => $meetings,
        ]);
        $this->assertSame(
            $expected,
            [$values->format, $values->liveclasses, $values->liveclasseswhen, $values->inpersonworkload, $values->meetings]
        );
        // The model and the modality are facts the rule reads, never ones it rewrites.
        $this->assertSame([$model, $modality], [$values->model, $values->modality]);
    }
}
