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
 * Tests for {@see plancards}: the layout setting, the shape of the cards, the favourites and tags passed through from
 * block_dimensions, and the decoration of core's export.
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

        $this->assertSame(['layout', 'itemclasses', 'cards', 'meta', 'favourites', 'tagfilters'], array_keys($extras));
        $this->assertSame(plan_cards::LAYOUT_VERTICAL, $extras['layout']);
        $this->assertSame(plan_cards::item_classes(plan_cards::LAYOUT_VERTICAL), $extras['itemclasses']);
        $this->assertSame([(int) $first->get('id'), (int) $second->get('id')], array_keys($extras['cards']));
        $this->assertStringContainsString('A plan', $extras['cards'][(int) $first->get('id')]);
        $this->assertStringContainsString('plan-card', $extras['cards'][(int) $first->get('id')]);
        $this->assertSame(array_keys($extras['cards']), array_keys($extras['meta']), 'One meta per card, in order.');
    }

    /**
     * Select one option of a plan template's tag field, as the admin, the way local_dimensions' form saves it.
     *
     * @param int $templateid The plan template.
     * @param string $shortname The tag field's short name.
     * @param int $option The 1-based position of the option chosen.
     * @return void
     */
    private function tag_template(int $templateid, string $shortname, int $option): void {
        global $USER;

        $previous = $USER;
        $this->setAdminUser();
        \local_dimensions\helper::ensure_custom_fields_exist(\local_dimensions\helper::AREA_LP);
        \local_dimensions\customfield\lp_handler::create()->instance_form_save((object) [
            'id' => $templateid,
            'customfield_' . $shortname => $option,
        ], true);
        $this->setUser($previous);
    }

    /**
     * A learner with a plan whose template is tagged (tag 1, first option) and a plain one, the tagged plan one of
     * the learner's favourites, favourites on and both of the block's plan tag filters offered.
     *
     * @return array [the owner, the tagged plan's id, the plain plan's id].
     */
    private function owner_with_a_favourite(): array {
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $owner = $generator->create_user();
        $template = $lpg->create_template(['shortname' => 'Garden']);
        $this->tag_template((int) $template->get('id'), \local_dimensions\constants::CFIELD_TAG1, 1);
        $tagged = $lpg->create_plan([
            'userid' => $owner->id,
            'name' => 'A tagged plan',
            'templateid' => $template->get('id'),
            'status' => plan::STATUS_ACTIVE,
        ]);
        $plain = $lpg->create_plan(['userid' => $owner->id, 'name' => 'B plain plan', 'status' => plan::STATUS_COMPLETE]);
        set_config('enable_favourites', 1, 'block_dimensions');
        set_config('enable_plan_tag1_filter', 1, 'block_dimensions');
        set_config('enable_plan_tag2_filter', 1, 'block_dimensions');
        $usercontext = \context_user::instance($owner->id);
        \core_favourites\service_factory::get_service_for_user_context($usercontext)
            ->create_favourite('block_dimensions', 'plan', (int) $tagged->get('id'), $usercontext);

        return [$owner, (int) $tagged->get('id'), (int) $plain->get('id')];
    }

    /**
     * On the owner's own list each card says whether it is a favourite, its tags are the template's, and the tag
     * filters are the block's that a listed plan has a value for.
     *
     * @return void
     */
    public function test_the_owners_list_carries_favourites_and_tags(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$owner, $taggedid, $plainid] = $this->owner_with_a_favourite();
        $this->setUser($owner);

        $extras = plancards::for_user((int) $owner->id, $PAGE->get_renderer('core'));

        $this->assertTrue($extras['favourites']);
        $this->assertSame(
            ['favourite' => true, 'tags' => [['key' => 'tag1', 'value' => 'Edit me 1']]],
            $extras['meta'][$taggedid]
        );
        $this->assertSame(['favourite' => false, 'tags' => []], $extras['meta'][$plainid]);
        $offered = plan_cards::tag_filters();
        $this->assertSame(['tag1', 'tag2'], array_column($offered, 'key'), 'Control: the block offers both filters.');
        $this->assertSame([['key' => 'tag1', 'label' => $offered[0]['label']]], $extras['tagfilters']);
    }

    /**
     * On another person's list no card carries a favourite, and nothing says favourites are offered; the tags stay.
     *
     * @return void
     */
    public function test_another_persons_list_carries_no_favourite(): void {
        global $PAGE;
        $this->resetAfterTest();
        [$owner, $taggedid, $plainid] = $this->owner_with_a_favourite();

        $this->setAdminUser();
        $extras = plancards::for_user((int) $owner->id, $PAGE->get_renderer('core'));

        $this->assertSame([$taggedid, $plainid], array_keys($extras['meta']), 'The admin must read both plans.');
        $this->assertFalse($extras['favourites']);
        foreach ($extras['meta'] as $planid => $meta) {
            $this->assertNull($meta['favourite'], "Plan {$planid} carries a favourite flag.");
        }
        $this->assertSame([['key' => 'tag1', 'value' => 'Edit me 1']], $extras['meta'][$taggedid]['tags']);
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
        $extras = [
            'layout' => 'vertical',
            'itemclasses' => 'col-12',
            'cards' => [4 => '<article>card</article>'],
            'meta' => [4 => ['favourite' => true, 'tags' => [['key' => 'tag1', 'value' => 'R&D "A"']]]],
            'favourites' => true,
            'tagfilters' => [['key' => 'tag1', 'label' => 'Year']],
        ];

        $decorated = plancards::decorate($data, $extras);

        $this->assertTrue($decorated->dimfirstpaint);
        $this->assertSame('vertical', $decorated->dimlayout);
        $this->assertSame('col-12', $decorated->dimitemclasses);
        $this->assertTrue($decorated->plans[0]->hasdimcard);
        $this->assertSame('<article>card</article>', $decorated->plans[0]->dimcard);
        $this->assertFalse($decorated->plans[1]->hasdimcard);
        $this->assertSame('', $decorated->plans[1]->dimcard);
        $this->assertTrue($decorated->dimfavourites);
        $this->assertSame([['key' => 'tag1', 'label' => 'Year']], json_decode($decorated->dimtagfilters, true));
        $this->assertSame(plan_cards::STAR_MODULE, $decorated->dimstarmodule);
        $this->assertTrue($decorated->plans[0]->hasdimfav);
        $this->assertSame('1', $decorated->plans[0]->dimfav);
        $this->assertSame([['key' => 'tag1', 'value' => 'R&D "A"']], $decorated->plans[0]->dimtags);
        $this->assertFalse($decorated->plans[1]->hasdimfav, 'A plan with no meta offers no star.');
        $this->assertSame([], $decorated->plans[1]->dimtags);

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
            $this->assertSame(['dimcard', 'dimfav', 'dimtags', 'hasdimcard', 'hasdimfav'], $added);
        }
        $added = array_diff(array_keys($after), array_keys($core));
        sort($added);
        $this->assertSame(
            ['dimfavourites', 'dimfirstpaint', 'dimitemclasses', 'dimlayout', 'dimstarmodule', 'dimtagfilters'],
            $added
        );
    }

    /**
     * A tag's text comes out as its own PARAM_TEXT cleaning, the type the web service returns it under, even
     * where the site keeps tags in format_string(); a bare ampersand and a bare less-than sign stay as typed.
     *
     * @return void
     */
    public function test_a_plain_string_survives_the_services_return_type(): void {
        global $CFG;
        $this->resetAfterTest();
        $context = \core\context\system::instance();

        $CFG->formatstringstriptags = false;
        $this->assertStringContainsString(
            '<b>',
            format_string('<b>Year</b> one', true, ['context' => $context, 'escape' => false]),
            'Control: with the setting off format_string() keeps the tag.'
        );
        $plain = plancards::plain(' <b>Year</b> one ', $context);
        $this->assertSame('Year one', $plain);
        $this->assertSame($plain, clean_param($plain, PARAM_TEXT));

        $CFG->formatstringstriptags = true;
        $this->assertSame('R & D, a < b', plancards::plain('R & D, a < b', $context));
        $this->assertSame('', plancards::plain('   ', $context));
    }
}
