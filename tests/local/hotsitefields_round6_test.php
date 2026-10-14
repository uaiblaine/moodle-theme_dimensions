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
 * Tests for the round-6 hotsite fields: the hybrid modality, the format and the live classes
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
 * The round-6 fields are provisioned in place, upgraded in place, and read without contradiction.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hotsitefields::class)]
final class hotsitefields_round6_test extends \advanced_testcase {
    /**
     * Provision every field from scratch, the way a fresh install does.
     *
     * The PHPUnit site was installed with the fields of whatever version it was
     * initialised under, so a test of the definitions deletes them first and
     * provisions them again ({@see \theme_dimensions\local\hotsitefields_test}).
     *
     * @return void
     */
    private static function provision_fresh(): void {
        hotsitefields::reset_field_cache();
        foreach (array_keys(hotsitefields::definitions()) as $shortname) {
            $field = hotsitefields::get_field($shortname);
            if ($field) {
                api::delete_field_configuration($field);
            }
        }
        course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();
    }

    /**
     * The shortnames of the theme's category, in the order the course form lists them.
     *
     * @return string[] Shortnames, sorted by sortorder.
     */
    private static function field_order(): array {
        global $DB;
        $categoryid = (int) hotsitefields::get_field(hotsitefields::SHORTNAME_MODELO)->get('categoryid');
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
        hotsitefields::reset_field_cache();
        $options = (string) hotsitefields::get_field($shortname)->get_configdata_property('options');
        return preg_split("/\s*\n\s*/", trim($options));
    }

    /**
     * A fresh site gets the five fields, typed, right after the workload, and three modalities.
     */
    public function test_a_fresh_site_gets_the_round6_fields_in_place(): void {
        $this->resetAfterTest();
        self::provision_fresh();

        $types = [
            hotsitefields::SHORTNAME_FORMATO => 'select',
            hotsitefields::SHORTNAME_AULASAOVIVO => 'checkbox',
            hotsitefields::SHORTNAME_AULASAOVIVOQUANDO => 'text',
            hotsitefields::SHORTNAME_CARGAPRESENCIAL => 'number',
            hotsitefields::SHORTNAME_ENCONTROS => 'text',
        ];
        foreach ($types as $shortname => $type) {
            $field = hotsitefields::get_field($shortname);
            $this->assertNotNull($field, $shortname);
            $this->assertSame($type, $field->get('type'), $shortname);
        }

        $order = self::field_order();
        $workload = array_search(hotsitefields::SHORTNAME_CARGAHORARIA, $order, true);
        $this->assertSame(
            array_merge([hotsitefields::SHORTNAME_CARGAHORARIA], hotsitefields::ROUND6_SHORTNAMES),
            array_slice($order, $workload, 6)
        );
        $this->assertSame(
            [
                get_string('hotsitefield_modalidade_online', hotsitefields::COMPONENT),
                get_string('hotsitefield_modalidade_presencial', hotsitefields::COMPONENT),
                get_string('hotsitefield_modalidade_hibrido', hotsitefields::COMPONENT),
            ],
            self::options(hotsitefields::SHORTNAME_MODALIDADE)
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
            'customfield_' . hotsitefields::SHORTNAME_MODALIDADE => hotsitefields::MODALIDADE_HIBRIDO,
            'customfield_' . hotsitefields::SHORTNAME_FORMATO => hotsitefields::FORMATO_AUTOINSTRUCIONAL,
        ]);
        $other = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $other->id,
            'customfield_' . hotsitefields::SHORTNAME_FORMATO => hotsitefields::FORMATO_TUTORIA,
        ]);

        $exported = $handler->export_instance_data_object($course->id, true);
        $this->assertSame(
            get_string('hotsitefield_modalidade_hibrido', hotsitefields::COMPONENT),
            $exported->{hotsitefields::SHORTNAME_MODALIDADE}
        );
        $this->assertSame(
            get_string('hotsitefield_formato_autoinstrucional', hotsitefields::COMPONENT),
            $exported->{hotsitefields::SHORTNAME_FORMATO}
        );
        $this->assertSame(
            get_string('hotsitefield_formato_tutoria', hotsitefields::COMPONENT),
            $handler->export_instance_data_object($other->id, true)->{hotsitefields::SHORTNAME_FORMATO}
        );
    }

    /**
     * An upgraded site ends where a fresh one starts: the third modality appended once, the fields in place.
     *
     * The site before round 6 is rebuilt from a fresh one: the modality cut back to its two
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

        $modalidade = hotsitefields::get_field(hotsitefields::SHORTNAME_MODALIDADE);
        $config = (array) $modalidade->get('configdata');
        $config['options'] = "Online\nPresencial";
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $modalidade->get('id')]);
        foreach (hotsitefields::ROUND6_SHORTNAMES as $shortname) {
            api::delete_field_configuration(hotsitefields::get_field($shortname));
        }
        course_handler::reset_caches();
        hotsitefields::reset_field_cache();
        $course = self::getDataGenerator()->create_course();
        course_handler::create()->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODALIDADE => hotsitefields::MODALIDADE_PRESENCIAL,
        ]);

        hotsitefields::ensure_fields();
        hotsitefields::reset_field_cache();
        // The precondition the placement exists for: appended after the share picture.
        $this->assertSame(hotsitefields::ROUND6_SHORTNAMES, array_slice(self::field_order(), -5));

        $this->assertTrue(hotsitefields::add_hybrid_option());
        $this->assertSame(
            ['Online', 'Presencial', get_string('hotsitefield_modalidade_hibrido', hotsitefields::COMPONENT)],
            self::options(hotsitefields::SHORTNAME_MODALIDADE)
        );
        $this->assertFalse(hotsitefields::add_hybrid_option());
        $this->assertCount(3, self::options(hotsitefields::SHORTNAME_MODALIDADE));

        hotsitefields::place_format_fields();
        $this->assertSame($fresh, self::field_order());

        $this->assertSame(hotsitefields::MODALIDADE_PRESENCIAL, hotsitefields::values($course->id)->modalidade);
    }

    /**
     * A third line an administrator added is left alone: appending a fourth would make theirs "hybrid".
     */
    public function test_a_select_an_administrator_extended_is_left_alone(): void {
        global $DB;
        $this->resetAfterTest();
        self::provision_fresh();

        $modalidade = hotsitefields::get_field(hotsitefields::SHORTNAME_MODALIDADE);
        $config = (array) $modalidade->get('configdata');
        $config['options'] = "Online\nPresencial\nSemipresencial";
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $modalidade->get('id')]);

        $this->assertFalse(hotsitefields::add_hybrid_option());
        $this->assertSame(['Online', 'Presencial', 'Semipresencial'], self::options(hotsitefields::SHORTNAME_MODALIDADE));
    }

    /**
     * values() reads the round-6 fields of a tutored hybrid course, and reads them through coherent().
     */
    public function test_values_reads_the_round6_fields(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        self::provision_fresh();

        $handler = course_handler::create();
        $course = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => hotsitefields::MODELO_CURSO,
            'customfield_' . hotsitefields::SHORTNAME_MODALIDADE => hotsitefields::MODALIDADE_HIBRIDO,
            'customfield_' . hotsitefields::SHORTNAME_FORMATO => hotsitefields::FORMATO_TUTORIA,
            'customfield_' . hotsitefields::SHORTNAME_AULASAOVIVO => 1,
            'customfield_' . hotsitefields::SHORTNAME_AULASAOVIVOQUANDO => 'Quartas & sábados, 19h',
            'customfield_' . hotsitefields::SHORTNAME_CARGAHORARIA => 60,
            'customfield_' . hotsitefields::SHORTNAME_CARGAPRESENCIAL => 16,
            'customfield_' . hotsitefields::SHORTNAME_ENCONTROS => '2 encontros aos sábados',
        ]);
        $values = hotsitefields::values($course->id);
        $this->assertSame(hotsitefields::FORMATO_TUTORIA, $values->formato);
        $this->assertTrue($values->aovivo);
        $this->assertSame('Quartas & sábados, 19h', $values->aovivoquando);
        $this->assertSame(16.0, $values->cargapresencial);
        $this->assertSame('2 encontros aos sábados', $values->encontros);

        // The same live classes on a self-paced course are read away: values() goes through coherent().
        $selfpaced = self::getDataGenerator()->create_course();
        $handler->instance_form_save((object) [
            'id' => $selfpaced->id,
            'customfield_' . hotsitefields::SHORTNAME_MODELO => hotsitefields::MODELO_CURSO,
            'customfield_' . hotsitefields::SHORTNAME_MODALIDADE => hotsitefields::MODALIDADE_ONLINE,
            'customfield_' . hotsitefields::SHORTNAME_FORMATO => hotsitefields::FORMATO_AUTOINSTRUCIONAL,
            'customfield_' . hotsitefields::SHORTNAME_AULASAOVIVO => 1,
            'customfield_' . hotsitefields::SHORTNAME_AULASAOVIVOQUANDO => 'Quartas, 19h',
        ]);
        $values = hotsitefields::values($selfpaced->id);
        $this->assertSame(hotsitefields::FORMATO_AUTOINSTRUCIONAL, $values->formato);
        $this->assertFalse($values->aovivo);
        $this->assertNull($values->aovivoquando);
    }

    /**
     * Stored combinations and what the page may read of them (decision D10).
     *
     * @return array Name => [stored values, expected values], each over the round-6 keys.
     */
    public static function coherence_cases(): array {
        $c = hotsitefields::MODELO_CURSO;
        $e = hotsitefields::MODELO_EVENTO;
        $on = hotsitefields::MODALIDADE_ONLINE;
        $pr = hotsitefields::MODALIDADE_PRESENCIAL;
        $hy = hotsitefields::MODALIDADE_HIBRIDO;
        $sp = hotsitefields::FORMATO_AUTOINSTRUCIONAL;
        $tu = hotsitefields::FORMATO_TUTORIA;
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
     * @param array $stored modelo, modalidade, formato, aovivo, aovivoquando, cargapresencial, encontros.
     * @param array $expected formato, aovivo, aovivoquando, cargapresencial, encontros as read.
     */
    #[DataProvider('coherence_cases')]
    public function test_coherent_reads_without_contradiction(array $stored, array $expected): void {
        [$modelo, $modalidade, $formato, $aovivo, $quando, $presencial, $encontros] = $stored;
        $values = hotsitefields::coherent((object) [
            'modelo' => $modelo,
            'modalidade' => $modalidade,
            'formato' => $formato,
            'aovivo' => $aovivo,
            'aovivoquando' => $quando,
            'cargapresencial' => $presencial,
            'encontros' => $encontros,
        ]);
        $this->assertSame(
            $expected,
            [$values->formato, $values->aovivo, $values->aovivoquando, $values->cargapresencial, $values->encontros]
        );
        // The model and the modality are facts the rule reads, never ones it rewrites.
        $this->assertSame([$modelo, $modalidade], [$values->modelo, $values->modalidade]);
    }
}
