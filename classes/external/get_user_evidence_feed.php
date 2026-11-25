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
 * Theme Dimensions - The platform's records of a user, for the evidence page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core\context\user as context_user;
use core_competency\api;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use theme_dimensions\local\competency\evidencelist;

/**
 * One page of what the platform recorded of a user's competencies, for amd/src/competencyevidence.js on the evidence
 * page (admin/tool/lp/user_evidence_list.php), which core draws with the prior-learning evidence alone.
 *
 * Every decision is {@see evidencelist}'s. The checks come first and in this order: the parameters, a session, the
 * guest account refused (as the theme's other account services), competencies enabled ({@see api::require_enabled()}),
 * the user's own context validated, then core's rule for a user competency's evidence
 * ({@see evidencelist::require_platform()}, the rule of {@see api::list_evidence()}), refused with core's exception
 * before anything is read. The cursor and the period are validated next, refused with invalid_parameter_exception.
 *
 * The first page (no cursor) also carries the total and the facets of the Filters panel; the others carry -1 and empty
 * facets, so a page of a long list costs the same whatever its position.
 *
 * Read-only, so it raises no event.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_evidence_feed extends external_api {
    /**
     * The parameters of the function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'The user whose records are listed.'),
            'competencyid' => new external_value(PARAM_INT, 'One competency, or 0.', VALUE_DEFAULT, 0),
            'planid' => new external_value(PARAM_INT, 'The competencies of one of the user\'s plans, or 0.', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'One course and its activities, or 0.', VALUE_DEFAULT, 0),
            'datefrom' => new external_value(PARAM_ALPHANUMEXT, 'The first day, YYYY-MM-DD, or empty.', VALUE_DEFAULT, ''),
            'dateto' => new external_value(PARAM_ALPHANUMEXT, 'The last day, YYYY-MM-DD, or empty.', VALUE_DEFAULT, ''),
            'beforetime' => new external_value(PARAM_INT, 'The cursor\'s time, 0 for the first page.', VALUE_DEFAULT, 0),
            'beforeid' => new external_value(PARAM_INT, 'The cursor\'s id, 0 for the first page.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * One page of a user's records.
     *
     * @param int $userid The user whose records are listed.
     * @param int $competencyid A competency, or 0.
     * @param int $planid A plan of the user, or 0.
     * @param int $courseid A course, or 0.
     * @param string $datefrom The period's first day, or ''.
     * @param string $dateto The period's last day, or ''.
     * @param int $beforetime The cursor's time, or 0.
     * @param int $beforeid The cursor's id, or 0.
     * @return array See execute_returns().
     * @throws \require_login_exception Without a session.
     * @throws \moodle_exception For the guest account, or with competencies disabled.
     * @throws \core\exception\required_capability_exception When the viewer may not read the user's competencies.
     * @throws \invalid_parameter_exception For a malformed cursor or day.
     */
    public static function execute(
        int $userid,
        int $competencyid = 0,
        int $planid = 0,
        int $courseid = 0,
        string $datefrom = '',
        string $dateto = '',
        int $beforetime = 0,
        int $beforeid = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'competencyid' => $competencyid,
            'planid' => $planid,
            'courseid' => $courseid,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
            'beforetime' => $beforetime,
            'beforeid' => $beforeid,
        ]);

        require_login(null, false, null, false, true);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        api::require_enabled();
        $context = context_user::instance($params['userid']);
        self::validate_context($context);
        evidencelist::require_platform($params['userid']);

        [$from, $to] = evidencelist::period($params['datefrom'], $params['dateto']);
        $filters = [
            'competencyid' => $params['competencyid'],
            'planid' => $params['planid'],
            'courseid' => $params['courseid'],
            'from' => $from,
            'to' => $to,
        ];
        $page = evidencelist::page($params['userid'], $filters, $params['beforetime'], $params['beforeid']);
        $facets = ['competencies' => [], 'courses' => [], 'plans' => []];
        if ($params['beforeid'] === 0) {
            $facets = evidencelist::facets($params['userid']);
        }

        return [
            'total' => $page['total'],
            'hasmore' => $page['hasmore'],
            'nexttime' => $page['nexttime'],
            'nextid' => $page['nextid'],
            'periodfrom' => $from,
            'periodto' => $to,
            'records' => $page['records'],
            'folds' => $page['folds'],
            'facets' => $facets,
        ];
    }

    /**
     * What the function returns: an allowlist.
     *
     * Every string is plain text (format_string() without escaping, then its own PARAM_TEXT cleaning, in
     * {@see evidencelist}), written by the module as text or through a template's double stash. Of an evidence row it
     * carries what core's evidence_exporter shows the same viewer on the user competency's page: the description, the
     * rating's name, the acting user's full name, the note and the address (http and https only). It never carries the
     * row's context, its raw description parameters, the grade's number or any file.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $named = static fn(string $what): external_single_structure => new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The ' . $what . '.'),
            'name' => new external_value(PARAM_TEXT, 'Its name, plain text.'),
        ]);

        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'The records matching the filters on the first page; -1 on the others.'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether older records follow.'),
            'nexttime' => new external_value(PARAM_INT, 'The next page\'s cursor time, 0 without one.'),
            'nextid' => new external_value(PARAM_INT, 'The next page\'s cursor id, 0 without one.'),
            'periodfrom' => new external_value(PARAM_INT, 'The period\'s first instant, 0 when open.'),
            'periodto' => new external_value(PARAM_INT, 'The instant after the period\'s last day, 0 when open.'),
            'records' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'The evidence row.'),
                    'time' => new external_value(PARAM_INT, 'When it was recorded.'),
                    'month' => new external_value(PARAM_ALPHANUMEXT, 'Its month, YYYY-MM, in the reader\'s timezone.'),
                    'monthlabel' => new external_value(PARAM_TEXT, 'Its month, in words.'),
                    'date' => new external_value(PARAM_TEXT, 'Its day, short, in words.'),
                    'kind' => new external_value(PARAM_ALPHA, 'activity, course, rule, rating, restore, unlinked or record.'),
                    'title' => new external_value(PARAM_TEXT, 'Core\'s description of it, plain text.'),
                    'competencyid' => new external_value(PARAM_INT, 'The competency it feeds.'),
                    'competency' => new external_value(PARAM_TEXT, 'The competency\'s name, plain text.'),
                    'competencyurl' => new external_value(PARAM_URL, 'The user competency\'s page.'),
                    'gradename' => new external_value(PARAM_TEXT, 'The rating\'s name on the scale, or empty.'),
                    'actor' => new external_value(PARAM_TEXT, 'Who acted, as core names them, or empty.'),
                    'note' => new external_value(PARAM_TEXT, 'The note, plain text, or empty.'),
                    'url' => new external_value(PARAM_URL, 'Its address, http or https, or empty.'),
                ]),
                'The records, newest first.'
            ),
            'folds' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_TEXT, 'Component and month, or "earlier": one line per key.'),
                    'component' => new external_value(PARAM_COMPONENT, 'The component that wrote the rows.'),
                    'label' => new external_value(PARAM_TEXT, 'The component\'s name, plain text.'),
                    'month' => new external_value(PARAM_ALPHANUMEXT, 'The month, YYYY-MM (the newest row\'s, on an earlier line).'),
                    'monthlabel' => new external_value(PARAM_TEXT, 'The month, in words.'),
                    'count' => new external_value(PARAM_INT, 'The rows of that component in that month, on this page.'),
                    'time' => new external_value(PARAM_INT, 'The newest of them.'),
                ]),
                'The other components\' rows of this page\'s stretch of time, counted per month.'
            ),
            'facets' => new external_single_structure([
                'competencies' => new external_multiple_structure($named('competency'), 'The user\'s competencies.'),
                'courses' => new external_multiple_structure($named('course'), 'The courses the records came from.'),
                'plans' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'The plan.'),
                        'name' => new external_value(PARAM_TEXT, 'Its name, plain text.'),
                        'competencyids' => new external_multiple_structure(
                            new external_value(PARAM_INT, 'A competency of the plan.'),
                            'The plan\'s competencies.'
                        ),
                    ]),
                    'The user\'s plans the viewer may read.'
                ),
            ], 'The Filters panel\'s options, on the first page only.'),
        ]);
    }
}
