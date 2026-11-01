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
 * Theme Dimensions - Remember what the reader left the calendar page in
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\refusal;
use theme_dimensions\local\calendar\state;

/**
 * Store the keys that are off, the category, the subtree flag, the "More" filters and the colouring of the calendar.
 *
 * A web service rather than a declared preference, for the showcase's reason
 * ({@see remember_category_state}): core's preference route refuses any value its clean callback changes, so a
 * declared preference would need the client to send the value exactly as the server stores it. Here the client
 * names the state and {@see state} validates it, dropping what is outside its vocabularies: a key the range does
 * not know, a model or modality past its field's lines, an overlong city, a colouring other than the two. And
 * because the preference is not declared, core's own route refuses a write of it altogether.
 *
 * The order of the checks is the range's: validate_parameters(), require_login() and the guest account's
 * refusal (the range's one refusal), validate_context() with the system context (the calendar spans the
 * viewer's courses and categories). A category is kept only when the range would narrow to it
 * ({@see page::scope_for()}): a stale one (gone, hidden, withheld) is stored as no category, never refused, so a
 * reader whose category disappeared is not stuck with an error on every change.
 *
 * No event: the write is the reader's own display preference, made after a quiet on every change of a chip or a
 * filter; a log row per press would be noise in every report that reads the log.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remember_calendar_state extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'off' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'A key that is off.'),
                'The keys that are off; empty for every key on.',
                VALUE_DEFAULT,
                []
            ),
            'categoryid' => new external_value(PARAM_INT, 'The category narrowed to, 0 for none.', VALUE_DEFAULT, 0),
            'recursive' => new external_value(PARAM_BOOL, 'Whether the category\'s whole subtree counts.', VALUE_DEFAULT, true),
            'model' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A course model option.'),
                'The models kept; empty for every model.',
                VALUE_DEFAULT,
                []
            ),
            'modality' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A modality option.'),
                'The modalities kept; empty for every modality.',
                VALUE_DEFAULT,
                []
            ),
            'city' => new external_value(PARAM_TEXT, 'The city kept, empty for any.', VALUE_DEFAULT, ''),
            'actiononly' => new external_value(PARAM_BOOL, 'Whether only things to do stay.', VALUE_DEFAULT, false),
            'colour' => new external_value(PARAM_ALPHA, 'The colouring: key or category.', VALUE_DEFAULT, state::COLOUR_KEY),
        ]);
    }

    /**
     * Remember the calendar's state for the reader of the session.
     *
     * @param array $off The keys that are off.
     * @param int $categoryid The category, 0 for none.
     * @param bool $recursive Whether its whole subtree counts.
     * @param array $model The models kept.
     * @param array $modality The modalities kept.
     * @param string $city The city kept.
     * @param bool $actiononly Whether only things to do stay.
     * @param string $colour The colouring.
     * @return array What is stored now, in the state's canonical shape, and whether a row holds it.
     * @throws refusal For the guest account.
     */
    public static function execute(
        array $off = [],
        int $categoryid = 0,
        bool $recursive = true,
        array $model = [],
        array $modality = [],
        string $city = '',
        bool $actiononly = false,
        string $colour = state::COLOUR_KEY
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'off' => $off,
            'categoryid' => $categoryid,
            'recursive' => $recursive,
            'model' => $model,
            'modality' => $modality,
            'city' => $city,
            'actiononly' => $actiononly,
            'colour' => $colour,
        ]);

        require_login(null, false);
        if (isguestuser()) {
            throw new refusal();
        }
        self::validate_context(\core\context\system::instance());

        $state = state::from_array($params);
        [, $state] = page::scope_for($state);
        $state->save();

        $stored = state::saved();
        return $stored->to_array() + ['remembered' => get_user_preferences(state::PREFERENCE) !== null];
    }

    /**
     * What the function returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'off' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'A key that is off.')),
            'categoryid' => new external_value(PARAM_INT, 'The category kept, 0 for none.'),
            'recursive' => new external_value(PARAM_BOOL, 'Whether its whole subtree counts.'),
            'model' => new external_multiple_structure(new external_value(PARAM_INT, 'A model kept.')),
            'modality' => new external_multiple_structure(new external_value(PARAM_INT, 'A modality kept.')),
            'city' => new external_value(PARAM_TEXT, 'The city kept.'),
            'actiononly' => new external_value(PARAM_BOOL, 'Whether only things to do stay.'),
            'colour' => new external_value(PARAM_ALPHA, 'The colouring.'),
            'remembered' => new external_value(PARAM_BOOL, 'Whether a stored row holds a state now.'),
        ]);
    }
}
