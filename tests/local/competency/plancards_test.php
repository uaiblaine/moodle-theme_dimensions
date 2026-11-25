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
 * Theme Dimensions - Tests for the plan cards of the learning plans page
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use block_dimensions\plan_cards;
use core_competency\plan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for {@see plancards}: the layout setting, the shape of the cards, and the decoration of core's export.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(plancards::class)]
final class plancards_test extends \advanced_testcase {
    /**
     * Stored values of the setting and the layout each reads as.
     *
     * @return array Case => [stored value or null for unset, expected layout].
     */
    public static function layout_provider(): array {
        return [
            'unset' => [null, plan_cards::LAYOUT_HORIZONTAL],
            'horizontal' => [plan_cards::LAYOUT_HORIZONTAL, plan_cards::LAYOUT_HORIZONTAL],
            'vertical' => [plan_cards::LAYOUT_VERTICAL, plan_cards::LAYOUT_VERTICAL],
            'unknown' => ['diagonal', plan_cards::LAYOUT_HORIZONTAL],
            'empty' => ['', plan_cards::LAYOUT_HORIZONTAL],
        ];
    }

    /**
     * The layout reads the theme setting, and anything but vertical reads as horizontal.
     *
     * @param string|null $stored The stored value, null to leave it unset.
     * @param string $expected The layout.
     * @return void
     */
    #[DataProvider('layout_provider')]
    public function test_the_layout_reads_the_setting(?string $stored, string $expected): void {
        $this->resetAfterTest();
        if ($stored === null) {
            unset_config(plancards::SETTING, 'theme_dimensions');
        } else {
            set_config(plancards::SETTING, $stored, 'theme_dimensions');
        }

        $this->assertSame($expected, plancards::layout());
    }

    /**
     * The block's own layout setting does not move the theme's.
     *
     * @return void
     */
    public function test_the_blocks_setting_is_not_the_themes(): void {
        $this->resetAfterTest();
        set_config('plancard_layout', plan_cards::LAYOUT_VERTICAL, 'block_dimensions');
        set_config(plancards::SETTING, plan_cards::LAYOUT_HORIZONTAL, 'theme_dimensions');

        $this->assertSame(plan_cards::LAYOUT_HORIZONTAL, plancards::layout());
    }

    /**
     * for_user() answers the layout, the block's item classes for it, and each plan's rendered card in core's order.
     *
     * @return void
     */
    public function test_for_user_shape(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $owner = $generator->create_user();
        $second = $lpg->create_plan(['userid' => $owner->id, 'name' => 'B plan', 'status' => plan::STATUS_ACTIVE]);
        $first = $lpg->create_plan(['userid' => $owner->id, 'name' => 'A plan', 'status' => plan::STATUS_COMPLETE]);
        $this->setUser($owner);
        set_config(plancards::SETTING, plan_cards::LAYOUT_VERTICAL, 'theme_dimensions');

        $extras = plancards::for_user((int) $owner->id, $PAGE->get_renderer('core'));

        $this->assertSame(['layout', 'itemclasses', 'cards'], array_keys($extras));
        $this->assertSame(plan_cards::LAYOUT_VERTICAL, $extras['layout']);
        $this->assertSame(plan_cards::item_classes(plan_cards::LAYOUT_VERTICAL), $extras['itemclasses']);
        $this->assertSame([(int) $first->get('id'), (int) $second->get('id')], array_keys($extras['cards']));
        $this->assertStringContainsString('A plan', $extras['cards'][(int) $first->get('id')]);
        $this->assertStringContainsString('plan-card', $extras['cards'][(int) $first->get('id')]);
    }

    /**
     * decorate() writes the theme's keys, a card where there is one, and leaves every key of core's as it was.
     *
     * @return void
     */
    public function test_decorate_writes_only_the_themes_keys(): void {
        $data = (object) [
            'userid' => 3,
            'pluginbaseurl' => 'https://example.com/admin/tool/lp',
            'canreaduserevidence' => true,
            'navigation' => [],
            'plans' => [
                (object) ['id' => 4, 'userid' => 3, 'name' => 'Kitchen plan', 'isactive' => true],
                (object) ['id' => 5, 'userid' => 3, 'name' => 'Garden plan', 'isdraft' => true],
            ],
        ];
        $core = json_decode(json_encode($data), true);
        $extras = ['layout' => 'vertical', 'itemclasses' => 'col-12', 'cards' => [4 => '<article>card</article>']];

        $decorated = plancards::decorate($data, $extras);

        $this->assertTrue($decorated->dimfirstpaint);
        $this->assertSame('vertical', $decorated->dimlayout);
        $this->assertSame('col-12', $decorated->dimitemclasses);
        $this->assertTrue($decorated->plans[0]->hasdimcard);
        $this->assertSame('<article>card</article>', $decorated->plans[0]->dimcard);
        $this->assertFalse($decorated->plans[1]->hasdimcard);
        $this->assertSame('', $decorated->plans[1]->dimcard);

        $after = json_decode(json_encode($decorated), true);
        foreach ($core as $key => $value) {
            if ($key === 'plans') {
                continue;
            }
            $this->assertSame($value, $after[$key], "Core's {$key} changed.");
        }
        foreach ($core['plans'] as $index => $plan) {
            foreach ($plan as $key => $value) {
                $this->assertSame($value, $after['plans'][$index][$key], "Core's plans[{$index}].{$key} changed.");
            }
            $added = array_diff(array_keys($after['plans'][$index]), array_keys($plan));
            sort($added);
            $this->assertSame(['dimcard', 'hasdimcard'], $added);
        }
        $added = array_diff(array_keys($after), array_keys($core));
        sort($added);
        $this->assertSame(['dimfirstpaint', 'dimitemclasses', 'dimlayout'], $added);
    }
}
