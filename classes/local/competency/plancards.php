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
 * Theme Dimensions - The plan cards of the learning plans page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use block_dimensions\plan_cards;

/**
 * What the learning plans page (admin/tool/lp/plans.php) adds to core's list: block_dimensions' plan card per plan.
 *
 * The one place the theme talks to {@see plan_cards}. Both doors go through it: the first paint
 * ({@see \theme_dimensions\output\tool_lp_renderer::render_plans_page()}, through {@see decorate()}) and the
 * refill after each of core's re-renders ({@see \theme_dimensions\external\get_plan_cards}). Which plans a viewer
 * sees is core's decision, taken inside the block by {@see \core_competency\api::list_user_plans()}; the theme
 * adds the look and never widens the list.
 *
 * Nothing is cached here: the cards are built per request, and the block's own caches (template metadata, the
 * trail) are the block's. Every input is in the database, so a cold node answers the same as a warm one.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plancards {
    /** @var string The theme setting naming the layout of the cards. */
    public const SETTING = 'planscardlayout';

    /** @var string Cards drawn as a row of image beside text: the default. */
    public const HORIZONTAL = plan_cards::LAYOUT_HORIZONTAL;

    /** @var string Cards drawn as a column of image above text. */
    public const VERTICAL = plan_cards::LAYOUT_VERTICAL;

    /**
     * The layout the theme setting asks for; an unset or unknown value reads as horizontal.
     *
     * @return string {@see HORIZONTAL} or {@see VERTICAL}.
     */
    public static function layout(): string {
        return get_config('theme_dimensions', self::SETTING) === self::VERTICAL ? self::VERTICAL : self::HORIZONTAL;
    }

    /**
     * The cards of one user's plans, rendered, with the layout they were drawn for.
     *
     * One call to the block's builder. A viewer core refuses gets no card from it; the web service refuses such
     * a viewer before calling this, and the page never gets this far (core's renderable throws first).
     *
     * @param int $userid The user whose plans are listed.
     * @param \renderer_base $output The renderer that draws the block's card template.
     * @return array ['layout' => string, 'itemclasses' => string, 'cards' => plan id => the card's HTML], the
     *     cards in core's order.
     */
    public static function for_user(int $userid, \renderer_base $output): array {
        $layout = self::layout();
        $cards = [];
        foreach (plan_cards::for_user($userid, $layout) as $planid => $card) {
            $cards[(int) $planid] = $output->render_from_template(plan_cards::TEMPLATE, $card);
        }

        return [
            'layout' => $layout,
            'itemclasses' => plan_cards::item_classes($layout),
            'cards' => $cards,
        ];
    }

    /**
     * Write the first paint's extras onto core's exported plans page.
     *
     * Only keys of the theme's own (`dim` first) are written; core's keys are left as they were, so the template
     * still renders from core's context alone when the client re-renders it without them.
     *
     * @param \stdClass $data What {@see \tool_lp\output\plans_page::export_for_template()} returned.
     * @param array $extras What {@see for_user()} returned.
     * @return \stdClass The same object, decorated.
     */
    public static function decorate(\stdClass $data, array $extras): \stdClass {
        $data->dimfirstpaint = true;
        $data->dimlayout = $extras['layout'];
        $data->dimitemclasses = $extras['itemclasses'];
        foreach ($data->plans as $plan) {
            $card = $extras['cards'][(int) $plan->id] ?? null;
            $plan->hasdimcard = $card !== null;
            $plan->dimcard = $card ?? '';
        }

        return $data;
    }
}
