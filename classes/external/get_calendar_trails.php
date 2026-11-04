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
 * Theme Dimensions - The trails of a month, or of up to three months, for the calendar's Trilhas view
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
use theme_dimensions\local\calendar\refusal;
use theme_dimensions\local\calendar\scope;
use theme_dimensions\local\calendar\trails;
use theme_dimensions\local\calendar\window;

/**
 * The calendar's trails over one month, or up to {@see trails::MONTHS} months in a row, in one answer.
 *
 * The order of the checks is {@see get_calendar_range}'s:
 *
 * 1. validate_parameters();
 * 2. the shape of the request, which says nothing about the site: a real month, YYYY-MM, of the years the page reads
 *    (1900 to 2999), and a count of months from 1 to {@see trails::MONTHS};
 * 3. require_login(), then the guest account refused with the same refusal as every other miss;
 * 4. validate_context() with the SYSTEM context: the answer spans the viewer's trails, so there is no one course to
 *    validate, and a course's would run require_login() against it, which throws for anybody not enrolled;
 * 5. the viewer's calendar type (Gregorian only), the months in the viewer's timezone, the category narrowing
 *    ({@see scope::for_viewer()}, which refuses a category the viewer may not narrow to), then {@see trails::answer()},
 *    which decides every trail and step for this account.
 *
 * A trail id the viewer may not discover is no refusal: the answer names nothing of it, as for one that does not exist.
 * A read raises no event; the session is read-only.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_calendar_trails extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'month' => new external_value(PARAM_RAW, 'The month, YYYY-MM, in the viewer\'s timezone.'),
            'trail' => new external_value(PARAM_INT, 'One trail course to keep, 0 for every trail.', VALUE_DEFAULT, 0),
            'mine' => new external_value(PARAM_BOOL, 'Only the trails the viewer is enrolled in.', VALUE_DEFAULT, false),
            'categoryid' => new external_value(PARAM_INT, 'Narrow to a category, 0 for none.', VALUE_DEFAULT, 0),
            'recursive' => new external_value(
                PARAM_BOOL,
                'With a category: its whole subtree (true) or its direct children only, as core.',
                VALUE_DEFAULT,
                true
            ),
            'months' => new external_value(
                PARAM_INT,
                'How many months from the month: 1 to 3.',
                VALUE_DEFAULT,
                1
            ),
        ]);
    }

    /**
     * The trails of a month, or of up to three months in a row.
     *
     * @param string $month The month.
     * @param int $trail One trail to keep, 0 for every trail.
     * @param bool $mine Whether only the viewer's own trails are kept.
     * @param int $categoryid The category narrowing.
     * @param bool $recursive Whether the category's whole subtree counts.
     * @param int $months How many months from the month, 1 to trails::MONTHS.
     * @return array The answer of trails::answer().
     * @throws refusal On every miss, the same for each.
     */
    public static function execute(
        string $month,
        int $trail = 0,
        bool $mine = false,
        int $categoryid = 0,
        bool $recursive = true,
        int $months = 1
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'month' => $month,
            'trail' => $trail,
            'mine' => $mine,
            'categoryid' => $categoryid,
            'recursive' => $recursive,
            'months' => $months,
        ]);

        // The shape, before login: it says nothing about the site.
        if (
            !preg_match('/^(\d{4})-(\d{2})$/D', $params['month'], $parts)
            || (int) $parts[2] < 1 || (int) $parts[2] > 12
            || (int) $parts[1] < 1900 || (int) $parts[1] > 2999
            || $params['months'] < 1 || $params['months'] > trails::MONTHS
        ) {
            throw new refusal();
        }

        require_login(null, false);
        if (isguestuser()) {
            throw new refusal();
        }
        self::validate_context(\core\context\system::instance());

        window::require_gregorian();
        $scope = scope::for_viewer($params['categoryid'], $params['recursive']);

        return trails::answer(
            trails::month_window($params['month'], $params['months']),
            $scope,
            $params['trail'],
            $params['mine'],
            time()
        );
    }

    /**
     * What the function returns: an allowlist, key for key.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'from' => new external_value(PARAM_ALPHANUMEXT, 'The first month\'s first day.'),
            'to' => new external_value(PARAM_ALPHANUMEXT, 'The last month\'s last day.'),
            'truncated' => new external_value(PARAM_BOOL, 'Whether trail courses were left out past the cap.'),
            'options' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'A trail course the viewer may discover.'),
                'name' => new external_value(PARAM_RAW, 'Its name, plain spelling.'),
            ]), 'Every trail the "Trilha" choice offers, whatever its period.'),
            'trails' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The trail course.'),
                'name' => new external_value(PARAM_RAW, 'Its name, plain spelling.'),
                'url' => new external_value(PARAM_URL, 'Its landing page.'),
                'categoryid' => new external_value(PARAM_INT, 'Its category, 0 when the viewer may not be told.'),
                'enrolled' => new external_value(PARAM_BOOL, 'Whether the viewer is actively enrolled: the steps carry states.'),
                'done' => new external_value(PARAM_INT, 'How many steps the viewer has done, 0 when not enrolled.'),
                'first' => new external_value(PARAM_INT, 'The period\'s first start, 0 for none.'),
                'last' => new external_value(PARAM_INT, 'The period\'s last end, 0 for an open period.'),
                'steps' => new external_multiple_structure(new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'The step\'s activity.'),
                    'position' => new external_value(PARAM_INT, 'Its place among the steps drawn, from 1.'),
                    'name' => new external_value(PARAM_RAW, 'The activity\'s name, plain spelling.'),
                    'courseid' => new external_value(PARAM_INT, 'The referenced course.'),
                    'coursename' => new external_value(PARAM_RAW, 'Its full name, plain spelling.'),
                    'categoryid' => new external_value(PARAM_INT, 'Its category, 0 when the viewer may not be told.'),
                    'start' => new external_value(PARAM_INT, 'When the step opens, 0 when unset.'),
                    'end' => new external_value(PARAM_INT, 'When it closes, 0 for an open window.'),
                    'expected' => new external_value(PARAM_BOOL, 'Whether the end is the activity\'s expected date.'),
                    'state' => new external_value(PARAM_ALPHA, 'done, current, late, locked or upcoming; empty when not enrolled.'),
                    'reason' => new external_value(PARAM_RAW, 'Core\'s availability text of a locked step, plain; else empty.'),
                    'completed' => new external_value(PARAM_INT, 'When core recorded it done, 0 otherwise.'),
                    'url' => new external_value(PARAM_URL, 'The referenced course\'s page.'),
                    'next' => new external_value(PARAM_BOOL, 'Whether it is the learner\'s next step.'),
                ])),
            ])),
            'categories' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The category id.'),
                'name' => new external_value(PARAM_RAW, 'The name, plain spelling.'),
                'bg' => new external_value(PARAM_RAW, 'The branding background, #rrggbb.'),
                'ink' => new external_value(PARAM_RAW, 'The branding ink, #rrggbb, always beside its background.'),
            ])),
        ]);
    }
}
