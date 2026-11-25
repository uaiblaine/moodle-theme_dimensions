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
 * Theme Dimensions - The plan cards of the learning plans page, after core re-renders it
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core\context\user as context_user;
use core\exception\required_capability_exception;
use core_competency\api;
use core_competency\plan;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\competency\plancards;

/**
 * The plan cards of one user, for amd/src/plancards.js after core's tool_lp/planactions re-rendered the page.
 *
 * Core re-renders tool_lp/plans_page on the client from tool_lp_data_for_plans_page after every row action, which
 * carries none of the theme's cards; this door gives them back. Every decision is {@see plancards}'s.
 *
 * The access rule is the one core's page applies, checked here before anything is built rather than left to the
 * block's builder, which answers a refused viewer with an empty list: competencies enabled
 * ({@see api::require_enabled()}), the context the user's own, and a viewer who may read the user's plans or their
 * drafts, refused otherwise with the exception {@see api::list_user_plans()} throws. Which plans then appear (drafts
 * only to a viewer who may read drafts) is core's list, taken inside the builder. The guest account is refused after
 * login, as the theme's other account services do.
 *
 * Read-only, so it raises no event.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_plan_cards extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'The user whose plans are listed.'),
        ]);
    }

    /**
     * The cards of a user's plans.
     *
     * @param int $userid The user whose plans are listed.
     * @return array layout, itemclasses and cards.
     * @throws \require_login_exception Without a session.
     * @throws \moodle_exception For the guest account, or with competencies disabled.
     * @throws required_capability_exception When the viewer may read neither the user's plans nor their drafts.
     */
    public static function execute(int $userid): array {
        global $PAGE;

        ['userid' => $userid] = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);

        require_login(null, false, null, false, true);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        api::require_enabled();
        $context = context_user::instance($userid);
        self::validate_context($context);
        if (!plan::can_read_user($userid) && !plan::can_read_user_draft($userid)) {
            throw new required_capability_exception($context, 'moodle/competency:planview', 'nopermissions', '');
        }

        $extras = plancards::for_user($userid, $PAGE->get_renderer('core'));
        $cards = [];
        foreach ($extras['cards'] as $planid => $html) {
            $cards[] = ['id' => $planid, 'html' => $html];
        }

        return [
            'layout' => $extras['layout'],
            'itemclasses' => $extras['itemclasses'],
            'cards' => $cards,
        ];
    }

    /**
     * What the function returns: an allowlist.
     *
     * The HTML is the block's card template rendered on this server from the block's own builder, every value in it
     * escaped by that template: trusted server markup, put in place as markup by the module.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'layout' => new external_value(PARAM_ALPHA, 'The layout the cards were drawn for: horizontal or vertical.'),
            'itemclasses' => new external_value(PARAM_TEXT, 'The class list of a card\'s list item for that layout.'),
            'cards' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The plan.'),
                    'html' => new external_value(PARAM_RAW, 'The card, server-rendered markup.'),
                ]),
                'The cards in core\'s order of the plans.'
            ),
        ]);
    }
}
