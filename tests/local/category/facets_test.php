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
 * Theme Dimensions - Tests for the custom field facets
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for the facet allowlist, the one-statement value map, the filter and the counts.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(facets::class)]
final class facets_test extends \advanced_testcase {
    use fixture;

    /**
     * Provision the landing page fields and return the two select ones.
     *
     * @return array The model field at 0 and the modality field at 1.
     */
    private function landing_selects(): array {
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        return [
            landingfields::get_field(landingfields::SHORTNAME_MODEL),
            landingfields::get_field(landingfields::SHORTNAME_MODALITY),
        ];
    }

    /**
     * Store select values on a course.
     *
     * @param int $courseid The course.
     * @param array $values shortname => option value.
     * @return void
     */
    private function store(int $courseid, array $values): void {
        $data = ['id' => $courseid];
        foreach ($values as $shortname => $value) {
            $data['customfield_' . $shortname] = $value;
        }
        course_handler::create()->instance_form_save((object) $data);
    }

    /**
     * The allowlist reads shortnames in order, keeps select fields only, skips the unknown and stops at four.
     */
    public function test_fields_reads_the_allowlist_in_order_capped_at_four_select_fields(): void {
        $this->resetAfterTest();
        [$model, $modality] = $this->landing_selects();
        $categoryid = (int) $model->get('categoryid');
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $extra = [];
        for ($i = 1; $i <= 3; $i++) {
            $extra[] = $generator->create_field([
                'categoryid' => $categoryid,
                'type' => 'select',
                'shortname' => "extra{$i}",
                'name' => "Extra {$i}",
                'configdata' => ['options' => "One\nTwo", 'defaultvalue' => ''],
            ]);
        }
        landingfields::reset_field_cache();

        set_config(facets::SETTING, '', 'theme_dimensions');
        $this->assertSame([], facets::fields());

        set_config(
            facets::SETTING,
            implode(',', [
                landingfields::SHORTNAME_MODALITY,
                landingfields::SHORTNAME_WORKLOAD,
                'nosuchfield',
                landingfields::SHORTNAME_MODEL,
                'extra1',
                'extra2',
                'extra3',
            ]),
            'theme_dimensions'
        );
        $fields = facets::fields();
        $this->assertSame(
            [(int) $modality->get('id'), (int) $model->get('id'), (int) $extra[0]->get('id'), (int) $extra[1]->get('id')],
            array_keys($fields),
            'Configured order, number field and unknown shortname skipped, capped at four.'
        );
        $this->assertSame(landingfields::SHORTNAME_MODALITY, reset($fields)->get('shortname'));
    }

    /**
     * The value map costs one statement, spans the admitted categories only, and omits unset values.
     */
    public function test_values_is_one_statement_over_the_admitted_categories(): void {
        $this->resetAfterTest();
        [$model, $modality] = $this->landing_selects();
        $generator = self::getDataGenerator();
        $inside = $generator->create_category();
        $also = $generator->create_category();
        $outside = $generator->create_category();
        $a = $generator->create_course(['category' => $inside->id]);
        $b = $generator->create_course(['category' => $also->id]);
        $c = $generator->create_course(['category' => $inside->id]);
        $out = $generator->create_course(['category' => $outside->id]);
        $this->store((int) $a->id, [
            landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
            landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_ONLINE,
        ]);
        $this->store((int) $b->id, [landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE]);
        $this->store((int) $out->id, [landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE]);
        $this->assertNotEmpty($c);

        $fieldids = [(int) $model->get('id'), (int) $modality->get('id')];
        $values = [];
        $reads = $this->reads(function () use ($fieldids, $inside, $also, &$values) {
            $values = facets::values($fieldids, [(int) $inside->id, (int) $also->id]);
        });
        $this->assertSame(1, $reads);
        // A keyed map: the statement carries no ORDER BY, so the engine picks the row order.
        $this->assertEquals(
            [(int) $a->id => landingfields::MODEL_EVENT, (int) $b->id => landingfields::MODEL_COURSE],
            $values[$fieldids[0]]
        );
        $this->assertSame([(int) $a->id => landingfields::MODALITY_ONLINE], $values[$fieldids[1]]);
        $this->assertArrayNotHasKey((int) $out->id, $values[$fieldids[0]], 'Another category is not read.');

        $this->assertSame([], facets::values([], [(int) $inside->id]));
        $this->assertSame([$fieldids[0] => []], facets::values([$fieldids[0]], []));
    }

    /**
     * Each group is counted over the given list with the OTHER groups' filters applied; an id outside the list is never counted.
     */
    public function test_groups_count_over_the_post_filter_list_with_the_other_groups_applied(): void {
        $this->resetAfterTest();
        [$model, $modality] = $this->landing_selects();
        $m = (int) $model->get('id');
        $d = (int) $modality->get('id');
        $fields = [$m => $model, $d => $modality];
        $values = [
            $m => [1 => 1, 2 => 2, 3 => 2, 4 => 1, 9 => 2],
            $d => [1 => 1, 2 => 1, 3 => 2, 9 => 1],
        ];
        // Course 9 carries values but is not in the list: withheld upstream, so it never counts.
        $ids = [1, 2, 3, 4];

        $groups = facets::groups($fields, $values, $ids, []);
        $this->assertSame([1 => 2, 2 => 2], array_map(fn($o) => $o['count'], $groups[$m]['options']));
        $this->assertSame([1 => 2, 2 => 1], array_map(fn($o) => $o['count'], $groups[$d]['options']));
        $this->assertSame(
            get_string('landingfield_model_course', 'theme_dimensions'),
            $groups[$m]['options'][1]['label']
        );
        $this->assertSame(get_string('landingfield_model', 'theme_dimensions'), $groups[$m]['name']);

        // Modality online picked: the model group counts online courses only; the modality group ignores its own filter.
        $groups = facets::groups($fields, $values, $ids, [$d => 1]);
        $this->assertSame([1 => 1, 2 => 1], array_map(fn($o) => $o['count'], $groups[$m]['options']));
        $this->assertSame([1 => 2, 2 => 1], array_map(fn($o) => $o['count'], $groups[$d]['options']));

        $this->assertSame([2, 3], facets::apply($values, $ids, [$m => 2]));
        $this->assertSame([2], facets::apply($values, $ids, [$m => 2, $d => 1]));
        $this->assertSame($ids, facets::apply($values, $ids, []));
        $this->assertSame([], facets::apply($values, $ids, [$m => 7]));
    }

    /**
     * Option labels are keyed by the 1-based value the data table stores and spelled plain.
     */
    public function test_option_labels_are_one_based_and_plain(): void {
        $this->resetAfterTest();
        [$model] = $this->landing_selects();
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $field = $generator->create_field([
            'categoryid' => (int) $model->get('categoryid'),
            'type' => 'select',
            'shortname' => 'ampersand',
            'name' => 'Ampersand',
            'configdata' => ['options' => "Fire & water\n\nAir", 'defaultvalue' => ''],
        ]);
        // A blank line is no option, as in core: the values stay contiguous.
        $this->assertSame([1 => 'Fire & water', 2 => 'Air'], facets::option_labels($field));
    }
}
