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

    /** @var array The tag keys of block_dimensions' plan cards the page filters by, in the block's order. */
    public const TAG_KEYS = ['tag1', 'tag2'];

    /**
     * The layout the theme setting asks for; an unset or unknown value reads as horizontal.
     *
     * @return string {@see HORIZONTAL} or {@see VERTICAL}.
     */
    public static function layout(): string {
        return get_config('theme_dimensions', self::SETTING) === self::VERTICAL ? self::VERTICAL : self::HORIZONTAL;
    }

    /**
     * The cards of one user's plans, rendered, with what the bar filters them by.
     *
     * One call to the block's builder, asking for the favourite star: the block offers it only on the session user's
     * own list with its favourites on, so on anybody else's list no card carries a favourite flag and `favourites` is
     * false; the theme passes the block's answer through and decides nothing about favourites itself. A viewer core
     * refuses gets no card from the builder; the web service refuses such a viewer before calling this, and the page
     * never gets this far (core's renderable throws first).
     *
     * Every value is plain text: the tag values and labels are run through format_string() without escaping here, so
     * the first paint and the web service carry the same strings and each sink escapes once.
     *
     * @param int $userid The user whose plans are listed.
     * @param \renderer_base $output The renderer that draws the block's card template.
     * @return array ['layout' => string, 'itemclasses' => string, 'cards' => plan id => the card's HTML,
     *     'meta' => plan id => ['favourite' => bool or null when the card offers no star, 'tags' => list of
     *     ['key' => 'tag1' or 'tag2', 'value' => string]], 'favourites' => whether any card offers the star,
     *     'tagfilters' => list of ['key', 'label'], the block's tag filters a listed plan has a value for], the cards
     *     in core's order.
     */
    public static function for_user(int $userid, \renderer_base $output): array {
        $layout = self::layout();
        $context = \core\context\system::instance();
        $cards = [];
        $meta = [];
        $favourites = false;
        $valued = [];
        foreach (plan_cards::for_user($userid, $layout, true) as $planid => $card) {
            $planid = (int) $planid;
            $cards[$planid] = $output->render_from_template(plan_cards::TEMPLATE, $card);
            $offered = !empty($card['showfavourite']);
            $favourites = $favourites || $offered;
            $tags = [];
            foreach (self::TAG_KEYS as $key) {
                $value = self::plain((string) ($card[$key] ?? ''), $context);
                if (!empty($card['has' . $key]) && $value !== '') {
                    $tags[] = ['key' => $key, 'value' => $value];
                    $valued[$key] = true;
                }
            }
            $meta[$planid] = ['favourite' => $offered ? !empty($card['isfavourite']) : null, 'tags' => $tags];
        }

        $tagfilters = [];
        foreach (plan_cards::tag_filters() as $filter) {
            $key = (string) ($filter['key'] ?? '');
            if (in_array($key, self::TAG_KEYS, true) && isset($valued[$key])) {
                $tagfilters[] = ['key' => $key, 'label' => self::plain((string) ($filter['label'] ?? ''), $context)];
            }
        }

        return [
            'layout' => $layout,
            'itemclasses' => plan_cards::item_classes($layout),
            'cards' => $cards,
            'meta' => $meta,
            'favourites' => $favourites,
            'tagfilters' => $tagfilters,
        ];
    }

    /**
     * A plain string from an admin-set one: filtered, tags stripped, not escaped, trimmed.
     *
     * The result is its own PARAM_TEXT cleaning, which is what the web service's return types apply: with
     * formatstringstriptags off, or behind a filter that adds markup, format_string() leaves tags in, and a
     * value that differs from its cleaning fails the whole response.
     *
     * @param string $text The string.
     * @param \core\context $context The context to filter it in.
     * @return string
     */
    public static function plain(string $text, \core\context $context): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $text = format_string($text, true, ['context' => $context, 'escape' => false]);

        return trim(clean_param($text, PARAM_TEXT));
    }

    /**
     * Write the first paint's extras onto core's exported plans page.
     *
     * Only keys of the theme's own (`dim` first) are written; core's keys are left as they were, so the template
     * still renders from core's context alone when the client re-renders it without them. The tag filters travel as
     * one JSON string, printed double-stashed into one attribute of the region and parsed by the module.
     *
     * @param \stdClass $data What {@see \tool_lp\output\plans_page::export_for_template()} returned.
     * @param array $extras What {@see for_user()} returned.
     * @return \stdClass The same object, decorated.
     */
    public static function decorate(\stdClass $data, array $extras): \stdClass {
        $data->dimfirstpaint = true;
        $data->dimlayout = $extras['layout'];
        $data->dimitemclasses = $extras['itemclasses'];
        $data->dimfavourites = !empty($extras['favourites']);
        $data->dimtagfilters = json_encode($extras['tagfilters'] ?? []);
        $data->dimstarmodule = plan_cards::STAR_MODULE;
        foreach ($data->plans as $plan) {
            $card = $extras['cards'][(int) $plan->id] ?? null;
            $plan->hasdimcard = $card !== null;
            $plan->dimcard = $card ?? '';
            $meta = $extras['meta'][(int) $plan->id] ?? ['favourite' => null, 'tags' => []];
            $plan->hasdimfav = $meta['favourite'] !== null;
            $plan->dimfav = $meta['favourite'] ? '1' : '0';
            $plan->dimtags = $meta['tags'];
        }

        return $data;
    }
}
