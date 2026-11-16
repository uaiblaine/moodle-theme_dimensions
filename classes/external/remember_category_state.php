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
 * Theme Dimensions - Remember the state a reader left a category's listing in
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
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\guard;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\category\rows;

/**
 * Store the organisation, the view and the filters the reader left one category's listing in.
 *
 * A web service rather than a declaration in the theme's user_preferences callback, which is how
 * the view's own preference is written: core's preference route refuses any value its
 * clean callback changes (`user/classes/route/api/preferences.php`, `$cleanvalue !== $value`), so a
 * declared map would have to arrive already merged, bounded and validated, byte for byte as the
 * server would store it. That puts the merge of one category into the reader's other categories,
 * the bound and the validation against the site's filter fields into the client, and lets two open
 * pages overwrite each other's categories. Here the client names ONE category's state and
 * {@see remembered::record()} does the rest, against the vocabularies it reads back with. And
 * because the preference is not declared, core's own route refuses a write of it altogether.
 *
 * The order of the checks is the showcase's: validate_parameters(), the shape (the root remembers
 * nothing), require_login() and the guest account's refusal - the same refusal every miss carries -
 * then {@see guard::category()}, which validates the category's context and asks both gates, so a
 * category this reader may not discover is refused exactly like one that does not exist and never
 * enters the map. Every value is then checked again by the delegate, which drops what is outside
 * its vocabulary rather than storing it.
 *
 * No event: the write is the reader's own display preference, a row of the kind core's preference
 * service writes without one, and it happens on every press of the toolbar; a log row per filter
 * would be noise in every report that reads the log.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remember_category_state extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid' => new external_value(PARAM_INT, 'The category whose listing the state is of.'),
            'sort' => new external_value(
                PARAM_ALPHA,
                'With a group, the order in force: name or recent. Without one, the organisation in force: category, '
                    . 'name or recent; empty for the theme\'s default, the grouped one.',
                VALUE_DEFAULT,
                ''
            ),
            'view' => new external_value(
                PARAM_ALPHA,
                'The view: cards or list; empty to keep the one remembered.',
                VALUE_DEFAULT,
                ''
            ),
            'chip' => new external_value(PARAM_ALPHA, 'The state chip, empty for none.', VALUE_DEFAULT, ''),
            'filters' => new external_multiple_structure(
                new external_single_structure([
                    'fieldid' => new external_value(PARAM_INT, 'The course custom field, from the site allowlist.'),
                    'value' => new external_value(PARAM_INT, 'The option of that field kept.'),
                ]),
                'One entry per custom field filter; none clears the remembered ones.',
                VALUE_DEFAULT,
                []
            ),
            // Last, because the framework hands the parameters to execute() in this order.
            'fav' => new external_value(
                PARAM_BOOL,
                'Whether the favourites toggle is on, combined with the state chip.',
                VALUE_DEFAULT,
                false
            ),
            // After fav, for the same reason: a client that names no group is read as before it existed.
            'group' => new external_value(
                PARAM_ALPHA,
                'How the listing is grouped: category or none; empty when sort names the organisation alone.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Remember a category's state for the reader of the session.
     *
     * @param int $categoryid The category.
     * @param string $sort The order (a group named) or the organisation (none named), '' for the default.
     * @param string $view The view, '' to keep the remembered one.
     * @param string $chip The state chip, '' for none.
     * @param array $filters The custom field filters, as fieldid and value pairs.
     * @param bool $fav Whether the favourites toggle is on.
     * @param string $group The grouping, one of criteria::GROUPS, '' when sort names the organisation alone.
     * @return array Whether the category is remembered now.
     * @throws \invalid_parameter_exception For the root, which remembers nothing, and for a named grouping or its order
     *         outside the vocabulary.
     * @throws \moodle_exception The one refusal, for the guest and for every category this reader may not discover.
     */
    public static function execute(
        int $categoryid,
        string $sort = '',
        string $view = '',
        string $chip = '',
        array $filters = [],
        bool $fav = false,
        string $group = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid' => $categoryid,
            'sort' => $sort,
            'view' => $view,
            'chip' => $chip,
            'filters' => $filters,
            'fav' => $fav,
            'group' => $group,
        ]);
        if ($params['categoryid'] <= 0) {
            // A shape check, alike for every reader: the root reads its search alone and remembers nothing.
            throw new \invalid_parameter_exception('Only a category remembers a state.');
        }

        require_login();
        if (isguestuser()) {
            throw new \moodle_exception(guard::ERROR_CATEGORY, 'theme_dimensions');
        }
        $category = guard::category((int) $params['categoryid']);
        $categoryid = (int) $category->id;

        $fields = [];
        foreach ($params['filters'] as $filter) {
            $fields[(int) $filter['fieldid']] = (int) $filter['value'];
        }
        /* The pair "Group by" and "Sort by" chose, in the spelling the entry keeps (criteria::organisation()).
           Without a group the sort is the organisation as sent, so one outside the vocabulary is refused by
           the delegate and the entry keeps what it held, as before the group existed. */
        $organisation = (string) $params['sort'];
        $psort = '';
        if ($params['group'] !== '') {
            // A named grouping is held to the listing services' vocabulary: nothing is remembered from a guess.
            rows::require_known_organisation((string) $params['group'], $organisation);
        }
        if (in_array($params['group'], criteria::GROUPS, true)) {
            [$organisation, $psort] = criteria::organisation((string) $params['group'], $organisation, '');
        }
        $changes = [
            // The empty organisation is the grouped one, and is remembered as such.
            remembered::KEY_SORT => $organisation === '' ? criteria::SORT_CATEGORY : $organisation,
            // The newest first is the grouped default, remembered as no key; a flat organisation keeps none either.
            remembered::KEY_PSORT => $psort === criteria::SORT_NAME ? criteria::SORT_NAME : null,
            remembered::KEY_STATE => $params['chip'] === '' ? null : $params['chip'],
            // The toggle's one stored value is true; off is the key forgotten.
            remembered::KEY_FAVOURITES => $params['fav'] ? true : null,
            remembered::KEY_FIELDS => $fields ?: null,
        ];
        if ($params['view'] !== '') {
            $changes[remembered::KEY_VIEW] = $params['view'];
        }
        remembered::record($categoryid, $changes);

        return ['remembered' => remembered::for_category($categoryid) !== null];
    }

    /**
     * What the function returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'remembered' => new external_value(PARAM_BOOL, 'Whether the category holds a remembered state now.'),
        ]);
    }
}
