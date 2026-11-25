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
 * Theme Dimensions - The evidence page's dated list of what the platform recorded
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core\context\system as context_system;
use core\context\user as context_user;
use core\exception\required_capability_exception;
use core_competency\api;
use core_competency\evidence;
use core_competency\plan;
use core_competency\url;
use core_competency\user_competency;
use core_competency\user_evidence;

/**
 * What the evidence page (admin/tool/lp/user_evidence_list.php) adds to core's list of prior-learning evidence: the
 * records the platform wrote in competency_evidence for the same user, across every competency, newest first.
 *
 * Core lists a user's evidence one competency at a time ({@see api::list_evidence()}, a user competency's records)
 * and has no API across competencies, so this class reads competency_evidence itself, joined to competency_usercomp
 * (an evidence row hangs off a user competency, and the user is the user competency's). It mirrors what core
 * decides around that list:
 *
 * - the access rule of {@see api::list_evidence()}: {@see user_competency::can_read_user()}, asked ONCE for the user
 *   because every row is the same user's, refused with the same exception ({@see require_platform()});
 * - the access rule of {@see api::list_user_evidence()} for the prior-learning half, {@see user_evidence::can_read_user()},
 *   which the page itself enforces through core's renderable and which {@see access()} reports for the client;
 * - what core's {@see \core_competency\external\evidence_exporter} shows of a record on the user competency's page to
 *   the same viewer: the description core composes, the rating's name on the competency's scale, the acting user's
 *   full name, the note and the address. Nothing else (no file: an evidence row has none).
 *
 * Two kinds of row never become records. The rows core writes when a prior-learning evidence is linked to a
 * competency ({@see api::create_user_evidence_competency()}: core_competency, {@see LINKED}) only repeat what the page
 * already lists as that evidence's "Feeds" line. The rows of any other component (local_evidencehub's validity log is
 * most of them on a live site) are folded into one quiet line per component and month, counted, never listed.
 *
 * Paging is a keyset on (timecreated, id) descending: a cursor is the last record's pair, so a row written while the
 * reader pages shifts nothing. The folded rows of one page are those between the cursor and the page's last record,
 * so across the pages each is counted once; one aggregate statement counts them and no row is read.
 *
 * Nothing is cached and nothing is written: every input is in the database, and a cold node answers as a warm one.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class evidencelist {
    /** @var int The records of one page. */
    public const PAGE_SIZE = 30;

    /** @var string The component of every record core writes; any other component's rows are folded. */
    public const COMPONENT = 'core_competency';

    /** @var string The description core writes when a prior-learning evidence is linked: the row left out. */
    public const LINKED = 'evidence_evidenceofpriorlearninglinked';

    /** @var int The calendar months the fold of one page counts apart; the rows older than the last are one more line. */
    public const FOLD_MONTHS = 24;

    /** @var string The month of the fold's line for the rows older than the last month window. */
    public const EARLIER = 'earlier';

    /** @var string The shape of a calendar day the period is given in. */
    private const DAY = '/^(\d{4})-(\d{2})-(\d{2})$/';

    /**
     * Which halves of the page the current user may read, each by core's own rule.
     *
     * @param int $userid The user whose evidence is listed.
     * @return array ['prior' => the prior-learning evidence, 'platform' => the platform's records].
     */
    public static function access(int $userid): array {
        return [
            'prior' => user_evidence::can_read_user($userid),
            'platform' => user_competency::can_read_user($userid),
        ];
    }

    /**
     * Write the first paint's extras onto core's exported evidence list page: newest first, a month heading on the
     * first evidence of each month, and whether the viewer may read the platform's records.
     *
     * Only keys of the theme's own (`dim` first) are written and core's order is the only thing changed, so the
     * template still renders from core's context alone when the client re-renders it. Nothing is read: the dates are
     * core's export's, and the access rule is a capability check on the context core's renderable already loaded.
     *
     * @param \stdClass $data What {@see \tool_lp\output\user_evidence_list_page::export_for_template()} returned.
     * @return \stdClass The same object, decorated.
     */
    public static function decorate(\stdClass $data): \stdClass {
        $data->dimfirstpaint = true;
        $data->dimplatform = self::access((int) $data->userid)['platform'];
        $evidence = array_values((array) $data->evidence);
        usort($evidence, static fn($a, $b): int => [(int) $b->timecreated, (int) $b->id] <=> [(int) $a->timecreated, (int) $a->id]);
        $month = '';
        foreach ($evidence as $item) {
            $current = userdate((int) $item->timecreated, '%Y-%m');
            $item->dimmonthhead = $current === $month ? '' : userdate(
                (int) $item->timecreated,
                get_string('strftimemonthyear', 'langconfig')
            );
            $month = $current;
        }
        $data->evidence = $evidence;

        return $data;
    }

    /**
     * Refuse a viewer core would not list a user competency's evidence to, with the exception core throws.
     *
     * @param int $userid The user whose records are asked for.
     * @return void
     * @throws required_capability_exception When the viewer may not read the user's competencies.
     */
    public static function require_platform(int $userid): void {
        if (!user_competency::can_read_user($userid)) {
            throw new required_capability_exception(
                context_user::instance($userid),
                'moodle/competency:usercompetencyview',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * The filters as integers, each zero when absent: competencyid, planid, courseid, from and to (timestamps, to
     * exclusive).
     *
     * @param array $raw The filters as given.
     * @return array The five filters.
     */
    public static function filters(array $raw): array {
        $filters = [];
        foreach (['competencyid', 'planid', 'courseid', 'from', 'to'] as $key) {
            $filters[$key] = max(0, (int) ($raw[$key] ?? 0));
        }
        return $filters;
    }

    /**
     * Two calendar days of the reader's timezone as a half-open range of timestamps; an empty day leaves its end open.
     *
     * @param string $from The first day, YYYY-MM-DD, or ''.
     * @param string $to The last day, YYYY-MM-DD, or ''; the whole day is in the range.
     * @return array [from, to], 0 for an open end.
     * @throws \invalid_parameter_exception For a value that is not a calendar day.
     */
    public static function period(string $from, string $to): array {
        $zone = \core_date::get_user_timezone_object();
        $bounds = [];
        foreach (['from' => $from, 'to' => $to] as $end => $value) {
            if ($value === '') {
                $bounds[] = 0;
                continue;
            }
            if (!preg_match(self::DAY, $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                throw new \invalid_parameter_exception('Not a calendar day: ' . $value);
            }
            $day = new \DateTimeImmutable($value . ' 00:00:00', $zone);
            // The last day is whole: the range ends at the next midnight, excluded.
            $bounds[] = $end === 'from' ? $day->getTimestamp() : $day->modify('+1 day')->getTimestamp();
        }
        return $bounds;
    }

    /**
     * One page of the platform's records of a user, with the folded lines of the same stretch of time.
     *
     * @param int $userid The user whose records are listed.
     * @param array $filters See {@see filters()}.
     * @param int $beforetime The cursor's time: the previous page's last record, 0 for the first page.
     * @param int $beforeid The cursor's id, 0 for the first page.
     * @param int $limit The records of a page.
     * @return array ['records' => list of records ({@see record()}), 'folds' => list of folded lines ({@see folds()}),
     *     'hasmore' => bool, 'nexttime' => int, 'nextid' => int, 'total' => the records matching the filters on the
     *     first page, -1 on the others].
     * @throws required_capability_exception When the viewer may not read the user's competencies.
     * @throws \invalid_parameter_exception For a cursor that is not two positive integers or nothing.
     */
    public static function page(
        int $userid,
        array $filters,
        int $beforetime = 0,
        int $beforeid = 0,
        int $limit = self::PAGE_SIZE
    ): array {
        global $DB;

        self::require_platform($userid);
        if ($beforetime < 0 || $beforeid < 0 || ($beforetime === 0) !== ($beforeid === 0)) {
            throw new \invalid_parameter_exception('The cursor is a time and an id, both or neither.');
        }
        $filters = self::filters($filters);
        $scope = self::scope($userid, $filters);
        if ($scope === null) {
            return ['records' => [], 'folds' => [], 'hasmore' => false, 'nexttime' => 0, 'nextid' => 0,
                'total' => $beforeid === 0 ? 0 : -1];
        }
        [$from, $where, $params] = $scope;
        $cursor = '';
        if ($beforeid > 0) {
            $cursor = ' AND (e.timecreated < :beforetime OR (e.timecreated = :beforetimeeq AND e.id < :beforeid))';
            $params += ['beforetime' => $beforetime, 'beforetimeeq' => $beforetime, 'beforeid' => $beforeid];
        }
        $own = ' AND e.desccomponent = :component AND e.descidentifier <> :linked';
        $params += ['component' => self::COMPONENT, 'linked' => self::LINKED];

        $rows = array_values($DB->get_records_sql(
            "SELECT e.id, e.usercompetencyid, e.action, e.actionuserid, e.descidentifier, e.desca, e.url, e.grade, e.note,
                    e.timecreated, uc.competencyid
               {$from}
              WHERE {$where}{$own}{$cursor}
           ORDER BY e.timecreated DESC, e.id DESC",
            $params,
            0,
            $limit + 1
        ));
        $hasmore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $last = $hasmore ? end($rows) : null;

        $total = -1;
        if ($beforeid === 0) {
            $total = (int) $DB->count_records_sql("SELECT COUNT(1) {$from} WHERE {$where}{$own}", $params);
        }

        return [
            'records' => self::records($rows),
            'folds' => self::folds($from, $where, $cursor, $params, $last, $beforetime > 0 ? $beforetime : time()),
            'hasmore' => $hasmore,
            'nexttime' => $last ? (int) $last->timecreated : 0,
            'nextid' => $last ? (int) $last->id : 0,
            'total' => $total,
        ];
    }

    /**
     * The FROM and WHERE of the user's rows under the filters, or null when the filters can match nothing.
     *
     * The user is matched on competency_usercomp, and a competency through the same table, whose unique index leads
     * with (userid, competencyid). The course filter is the evidence's context: the course's own or one under it (an
     * activity), so {context} is joined only while that filter is on. Each named placeholder is used once.
     *
     * @param int $userid The user.
     * @param array $filters The normalised filters.
     * @return array|null [from, where, params].
     */
    private static function scope(int $userid, array $filters): ?array {
        global $DB;

        $from = 'FROM {competency_evidence} e JOIN {competency_usercomp} uc ON uc.id = e.usercompetencyid';
        $where = 'uc.userid = :userid';
        $params = ['userid' => $userid];

        $competencies = null;
        if ($filters['competencyid'] > 0) {
            $competencies = [$filters['competencyid']];
        }
        if ($filters['planid'] > 0) {
            $planned = self::plan_competencies($userid, $filters['planid']);
            $competencies = $competencies === null ? $planned : array_values(array_intersect($competencies, $planned));
        }
        if ($competencies !== null) {
            if (!$competencies) {
                return null;
            }
            [$insql, $inparams] = $DB->get_in_or_equal($competencies, SQL_PARAMS_NAMED, 'comp');
            $where .= " AND uc.competencyid {$insql}";
            $params += $inparams;
        }

        if ($filters['courseid'] > 0) {
            $course = \core\context\course::instance($filters['courseid'], IGNORE_MISSING);
            if (!$course) {
                return null;
            }
            $from .= ' JOIN {context} ctx ON ctx.id = e.contextid';
            $where .= ' AND (ctx.id = :coursecontextid OR ' . $DB->sql_like('ctx.path', ':coursepath') . ')';
            $params += ['coursecontextid' => $course->id, 'coursepath' => $course->path . '/%'];
        }
        if ($filters['from'] > 0) {
            $where .= ' AND e.timecreated >= :periodfrom';
            $params['periodfrom'] = $filters['from'];
        }
        if ($filters['to'] > 0) {
            $where .= ' AND e.timecreated < :periodto';
            $params['periodto'] = $filters['to'];
        }

        return [$from, $where, $params];
    }

    /**
     * The competencies of one of the user's plans the viewer may read, or none.
     *
     * A plan of another user, a plan the viewer may not read (a draft without core's draft capability) and a missing
     * plan all match nothing, with one answer.
     *
     * @param int $userid The user.
     * @param int $planid The plan.
     * @return array The competency ids.
     */
    private static function plan_competencies(int $userid, int $planid): array {
        $plan = plan::get_record(['id' => $planid, 'userid' => $userid]);
        if (!$plan || !$plan->can_read()) {
            return [];
        }
        return array_map(static fn($competency): int => (int) $competency->get('id'), $plan->get_competencies());
    }

    /**
     * The records of a page, every name read in bulk: competencies and their scales, then the acting users.
     *
     * @param array $rows The rows, in order.
     * @return array List of ['id', 'time', 'month', 'monthlabel', 'date', 'kind', 'title', 'competencyid',
     *     'competency', 'competencyurl', 'gradename', 'actor', 'note', 'url'], every string plain.
     */
    private static function records(array $rows): array {
        global $DB;

        if (!$rows) {
            return [];
        }
        $system = context_system::instance();
        [$insql, $params] = $DB->get_in_or_equal(array_unique(array_column($rows, 'competencyid')), SQL_PARAMS_NAMED);
        $competencies = $DB->get_records_sql(
            "SELECT c.id, c.shortname, c.scaleid, f.scaleid AS frameworkscaleid
               FROM {competency} c
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE c.id {$insql}",
            $params
        );

        $scaleids = [];
        foreach ($rows as $row) {
            $competency = $competencies[$row->competencyid] ?? null;
            if ($row->grade !== null && $competency) {
                $scaleids[] = (int) ($competency->scaleid ?: $competency->frameworkscaleid);
            }
        }
        $scales = [];
        if ($scaleids) {
            foreach ($DB->get_records_list('scale', 'id', array_unique($scaleids), '', 'id, scale') as $scale) {
                // As grade_scale::load_items() splits them.
                $scales[(int) $scale->id] = array_map('trim', explode(',', $scale->scale));
            }
        }

        $actorids = array_unique(array_filter(array_map('intval', array_column($rows, 'actionuserid'))));
        $actors = [];
        if ($actorids) {
            $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
            foreach ($DB->get_records_list('user', 'id', $actorids, '', $fields) as $actor) {
                $actors[(int) $actor->id] = plancards::plain(fullname($actor), $system);
            }
        }

        $records = [];
        foreach ($rows as $row) {
            $competency = $competencies[$row->competencyid] ?? null;
            $gradename = '';
            if ($row->grade !== null && $competency) {
                $items = $scales[(int) ($competency->scaleid ?: $competency->frameworkscaleid)] ?? [];
                $gradename = plancards::plain((string) ($items[(int) $row->grade - 1] ?? ''), $system);
            }
            $address = trim((string) $row->url);
            $time = (int) $row->timecreated;
            $records[] = [
                'id' => (int) $row->id,
                'time' => $time,
                'month' => userdate($time, '%Y-%m'),
                'monthlabel' => userdate($time, get_string('strftimemonthyear', 'langconfig')),
                'date' => userdate($time, get_string('strftimedateshort', 'langconfig')),
                'kind' => self::kind((string) $row->descidentifier),
                'title' => plancards::plain(self::description($row), $system),
                'competencyid' => (int) $row->competencyid,
                'competency' => $competency ? plancards::plain((string) $competency->shortname, $system) : '',
                'competencyurl' => url::user_competency((int) $row->usercompetencyid)->out(false),
                'gradename' => $gradename,
                'actor' => $actors[(int) $row->actionuserid] ?? '',
                'note' => plancards::plain((string) $row->note, $system),
                'url' => preg_match('~^https?://~i', $address) ? clean_param($address, PARAM_URL) : '',
            ];
        }
        return $records;
    }

    /**
     * Core's description of a record of its own, as {@see evidence::get_description()} composes it.
     *
     * @param \stdClass $row The row.
     * @return string The description, or '' when core has no such string.
     */
    private static function description(\stdClass $row): string {
        $identifier = (string) $row->descidentifier;
        if (!get_string_manager()->string_exists($identifier, self::COMPONENT)) {
            return '';
        }
        $desca = $row->desca === null ? null : json_decode((string) $row->desca);
        return get_string($identifier, self::COMPONENT, $desca);
    }

    /**
     * The kind of a record of core, from the description core wrote: what the source line and the icon say.
     *
     * @param string $identifier The description's string identifier.
     * @return string activity, course, rule, rating, restore, unlinked or record.
     */
    public static function kind(string $identifier): string {
        return match ($identifier) {
            'evidence_coursemodulecompleted' => 'activity',
            'evidence_coursecompleted' => 'course',
            'evidence_competencyrule' => 'rule',
            'evidence_manualoverride', 'evidence_manualoverrideincourse', 'evidence_manualoverrideinplan' => 'rating',
            'evidence_courserestored' => 'restore',
            'evidence_evidenceofpriorlearningunlinked' => 'unlinked',
            default => 'record',
        };
    }

    /**
     * The folded lines of a page: the other components' rows between the cursor and the page's last record (all of
     * them below the cursor on the last page), one line per component and calendar month.
     *
     * One aggregate statement and no row is read: the months are windows of the reader's timezone computed here
     * ({@see fold_windows()}), each counted by a conditional SUM and dated by a conditional MAX, grouped by component
     * alone so the statement is the same on PostgreSQL and MariaDB. The keyset bounds are applied once, in the WHERE,
     * around every window, so a row is counted on exactly one page. Rows older than the last window are one more
     * line per component, month {@see EARLIER}, so the cap hides no count.
     *
     * @param string $from The FROM of the user's rows.
     * @param string $where The WHERE of the user's rows under the filters.
     * @param string $cursor The cursor's condition, or ''.
     * @param array $params The parameters of the three, and of the page's own condition.
     * @param \stdClass|null $last The page's last record when more pages follow.
     * @param int $anchor The instant the newest window is counted from: the cursor's time, or now.
     * @return array List of ['key' => component|month, 'component', 'label', 'month', 'monthlabel', 'count',
     *     'time' => the newest row's time], newest first.
     */
    private static function folds(
        string $from,
        string $where,
        string $cursor,
        array $params,
        ?\stdClass $last,
        int $anchor
    ): array {
        global $DB;

        $lower = '';
        // The fold reads every other component's rows: the link rule of the page's own condition is not its.
        unset($params['linked']);
        if ($last) {
            $lower = ' AND (e.timecreated > :lasttime OR (e.timecreated = :lasttimeeq AND e.id > :lastid))';
            $params += ['lasttime' => (int) $last->timecreated, 'lasttimeeq' => (int) $last->timecreated,
                'lastid' => (int) $last->id];
        }

        $windows = self::fold_windows($anchor, $last ? (int) $last->timecreated : null);
        $buckets = [];
        $columns = [];
        foreach ($windows as $index => $window) {
            // The newest window has no end: a row dated after now is still the newest month's. Each placeholder once.
            $count = "e.timecreated >= :lo{$index}";
            $newest = "e.timecreated >= :tlo{$index}";
            $params["lo{$index}"] = $params["tlo{$index}"] = $window['start'];
            if ($index > 0) {
                $count .= " AND e.timecreated < :hi{$index}";
                $newest .= " AND e.timecreated < :thi{$index}";
                $params["hi{$index}"] = $params["thi{$index}"] = $windows[$index - 1]['start'];
            }
            $columns[] = "SUM(CASE WHEN {$count} THEN 1 ELSE 0 END) AS b{$index}";
            $columns[] = "MAX(CASE WHEN {$newest} THEN e.timecreated ELSE NULL END) AS t{$index}";
            $buckets[$index] = ['month' => $window['month'], 'monthlabel' => $window['monthlabel'], 'earlier' => false];
        }
        if (!$last || end($windows)['start'] > (int) $last->timecreated) {
            // The rows below the last window: the cap's remainder, counted whatever the page's lower bound is.
            $index = count($windows);
            $params["lo{$index}"] = $params["tlo{$index}"] = end($windows)['start'];
            $columns[] = "SUM(CASE WHEN e.timecreated < :lo{$index} THEN 1 ELSE 0 END) AS b{$index}";
            $columns[] = "MAX(CASE WHEN e.timecreated < :tlo{$index} THEN e.timecreated ELSE NULL END) AS t{$index}";
            $buckets[$index] = ['month' => self::EARLIER, 'monthlabel' => '', 'earlier' => true];
        }

        $columnsql = implode(', ', $columns);
        $rows = $DB->get_records_sql(
            "SELECT e.desccomponent, {$columnsql}
               {$from}
              WHERE {$where} AND e.desccomponent <> :component{$cursor}{$lower}
           GROUP BY e.desccomponent",
            $params
        );

        $lines = [];
        $names = [];
        foreach ($rows as $row) {
            $component = clean_param((string) $row->desccomponent, PARAM_COMPONENT);
            $names[$component] ??= self::component_name($component);
            foreach ($buckets as $index => $bucket) {
                $count = (int) $row->{"b{$index}"};
                if ($count === 0) {
                    continue;
                }
                $time = (int) $row->{"t{$index}"};
                $month = $bucket['earlier'] ? userdate($time, '%Y-%m') : $bucket['month'];
                $monthlabel = $bucket['earlier'] ? userdate($time, get_string('strftimemonthyear', 'langconfig'))
                    : $bucket['monthlabel'];
                $key = $component . '|' . ($bucket['earlier'] ? self::EARLIER : $month);
                $lines[$key] = [
                    'key' => $key,
                    'component' => $component,
                    'label' => $names[$component],
                    'month' => $month,
                    'monthlabel' => $monthlabel,
                    'count' => $count,
                    'time' => $time,
                ];
            }
        }
        usort($lines, static fn(array $a, array $b): int => [$b['time'], $b['key']] <=> [$a['time'], $a['key']]);

        return $lines;
    }

    /**
     * The calendar months of the reader's timezone a page's fold is counted in, newest first.
     *
     * The same month boundaries as {@see records()} headings: userdate() reads the same timezone. The windows run
     * from the anchor's month down to the month holding the page's last record, never more than FOLD_MONTHS.
     *
     * @param int $anchor The instant the newest window holds.
     * @param int|null $lasttime The page's last record's time, null on the last page.
     * @return array List of ['start' => the month's first instant, 'month' => YYYY-MM, 'monthlabel'].
     */
    private static function fold_windows(int $anchor, ?int $lasttime): array {
        $zone = \core_date::get_user_timezone_object();
        $start = (new \DateTimeImmutable('@' . $anchor))->setTimezone($zone)->modify('first day of this month')->setTime(0, 0);
        $format = get_string('strftimemonthyear', 'langconfig');
        $windows = [];
        for ($i = 0; $i < self::FOLD_MONTHS; $i++) {
            $stamp = $start->getTimestamp();
            $windows[] = ['start' => $stamp, 'month' => userdate($stamp, '%Y-%m'), 'monthlabel' => userdate($stamp, $format)];
            if ($lasttime !== null && $stamp <= $lasttime) {
                break;
            }
            $start = $start->modify('first day of previous month');
        }
        return $windows;
    }

    /**
     * A component's name for a folded line: its plugin name where the site has it, else the component itself.
     *
     * @param string $component The component.
     * @return string Plain text.
     */
    private static function component_name(string $component): string {
        if ($component !== '' && get_string_manager()->string_exists('pluginname', $component)) {
            return plancards::plain(get_string('pluginname', $component), context_system::instance());
        }
        return $component;
    }

    /**
     * What the Filters panel offers: the user's competencies, the courses their records came from that the viewer may
     * see listed, and the plans core lists to the viewer with each plan's competencies.
     *
     * @param int $userid The user.
     * @return array ['competencies' => list of [id, name], 'courses' => list of [id, name], 'plans' => list of
     *     [id, name, competencyids]], each by name.
     * @throws required_capability_exception When the viewer may not read the user's competencies.
     */
    public static function facets(int $userid): array {
        global $DB;

        self::require_platform($userid);
        $system = context_system::instance();

        $competencies = [];
        $rows = $DB->get_records_sql(
            'SELECT c.id, c.shortname
               FROM {competency} c
               JOIN {competency_usercomp} uc ON uc.competencyid = c.id
              WHERE uc.userid = :userid',
            ['userid' => $userid]
        );
        foreach ($rows as $row) {
            $competencies[] = ['id' => (int) $row->id, 'name' => plancards::plain((string) $row->shortname, $system)];
        }

        $courses = [];
        $paths = $DB->get_fieldset_sql(
            'SELECT DISTINCT ctx.path
               FROM {competency_evidence} e
               JOIN {competency_usercomp} uc ON uc.id = e.usercompetencyid
               JOIN {context} ctx ON ctx.id = e.contextid
              WHERE uc.userid = :userid AND ctx.contextlevel IN (:courselevel, :modulelevel)',
            ['userid' => $userid, 'courselevel' => CONTEXT_COURSE, 'modulelevel' => CONTEXT_MODULE]
        );
        $candidates = [];
        foreach ($paths as $path) {
            // A course's context is the last of its path, or the parent of an activity's.
            $ids = array_slice(explode('/', trim((string) $path, '/')), -2);
            foreach ($ids as $id) {
                $candidates[(int) $id] = true;
            }
        }
        if ($candidates) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($candidates), SQL_PARAMS_NAMED);
            $select = \context_helper::get_preload_record_columns_sql('ctx');
            $rows = $DB->get_records_sql(
                "SELECT c.id, c.fullname, c.visible, {$select}
                   FROM {course} c
                   JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :level
                  WHERE ctx.id {$insql}",
                $params + ['level' => CONTEXT_COURSE]
            );
            foreach ($rows as $row) {
                \context_helper::preload_from_record($row);
                $context = \core\context\course::instance((int) $row->id);
                if (!$row->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
                    continue;
                }
                $courses[] = ['id' => (int) $row->id, 'name' => plancards::plain((string) $row->fullname, $context)];
            }
        }

        $plans = [];
        try {
            $listed = api::list_user_plans($userid);
        } catch (required_capability_exception $exception) {
            $listed = [];
        }
        $idof = static fn($competency): int => (int) $competency->get('id');
        foreach ($listed as $plan) {
            $plans[] = [
                'id' => (int) $plan->get('id'),
                'name' => plancards::plain((string) $plan->get('name'), $system),
                'competencyids' => array_map($idof, array_values($plan->get_competencies())),
            ];
        }

        $byname = static fn(array $a, array $b): int => \core_text::strtolower($a['name']) <=> \core_text::strtolower($b['name']);
        usort($competencies, $byname);
        usort($courses, $byname);
        usort($plans, $byname);

        return ['competencies' => $competencies, 'courses' => $courses, 'plans' => $plans];
    }
}
