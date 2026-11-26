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
 * Theme Dimensions - The class report read headlessly, for its web services
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use context_course;
use core_competency\api;
use core_reportbuilder\exception\report_access_exception;
use core_reportbuilder\local\report\filter;
use theme_dimensions\reportbuilder\local\filters\rating;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings_table;

/**
 * The class report of competency ratings read headlessly: its rows, its facet counts and its summary.
 *
 * The system report ({@see class_ratings}) stays the one definition of rows, columns, access, the group rule, the
 * search and filters; this class only reads it, copied from local_evidencehub's `reportengine` and adapted (the
 * data-report contract, `moodle-dev/docs/design-system/components/data-report.md`, "Server contract"). Every answer
 * starts from the table core itself builds for the report ({@see class_ratings_table}), so it carries the report's base
 * conditions, the reader's stored filters and the columns the reader chose; the group and the search are the report's
 * own parameters, so the page's table, these answers and the download share one definition.
 *
 * Every count is SQL: conditional sums over the report's FROM and WHERE, one row back, so no learner reaches PHP and no
 * statement runs per row or per option. Nothing is node-local and nothing is cached: the figures are computed per
 * request from the database.
 *
 * Core keeps one instance of a report per reader for the whole request
 * (\core_reportbuilder\manager::get_report_from_persistent()), so every call is meant to be a request of its own; the
 * preference's writer never builds the report.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classreportengine {
    /** @var array The page sizes a reader may choose; anything else reads as DEFAULT_PERPAGE. */
    public const PERPAGES = [30, 50, 100];

    /** @var int The page size of a request that names none of PERPAGES: the report's own. */
    public const DEFAULT_PERPAGE = 30;

    /** @var int The highest page read: a larger request is read as this one, and answers the last page. */
    public const MAX_PAGE = 1000000;

    /** @var int The most conditional sums one statement of facet counts selects. */
    public const SUMS_PER_STATEMENT = 250;

    /**
     * The course a client names, after the class report's whole access rule; one refusal for every miss.
     *
     * In order: the course from the id, with one refusal for an id nobody holds and a course the caller cannot enter
     * (so an id answers nothing about whether it exists); validate_context() on the course's context; guests refused;
     * competencies enabled; {@see classreport::may_view()}. The report built afterwards asks its can_view() again.
     *
     * @param int $courseid The course.
     * @return context_course The course's context.
     * @throws report_access_exception For a course that does not exist, cannot be entered or whose class report the
     *     caller may not read.
     * @throws \moodle_exception For a guest, and with competencies disabled.
     */
    public static function locate(int $courseid): context_course {
        global $DB;

        $course = $courseid > 0 ? $DB->get_record('course', ['id' => $courseid]) : false;
        if (!$course || !can_access_course($course, null, '', true)) {
            throw new report_access_exception();
        }
        $context = context_course::instance((int) $course->id);
        \core_external\external_api::validate_context($context);
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        api::require_enabled();
        if (!classreport::may_view($context)) {
            throw new report_access_exception();
        }

        return $context;
    }

    /**
     * The class report of a course for the current reader, refused to a reader it would refuse.
     *
     * @param context_course $context The course's context.
     * @param int $moduleid An activity whose competencies are the columns, or 0.
     * @param int $groupid A group asked for, 0 for every group the group rule admits. Never negative: a service has no
     *     session to read the page's choice from, and the session's group is not read here.
     * @param string $search A search of the learner's name.
     * @return class_ratings
     * @throws report_access_exception When the report's can_view() refuses the current user.
     * @throws \core\exception\invalid_parameter_exception For a negative group, and for a second call in one request
     *     that asks for another activity, group or search than the report core already built for this reader.
     */
    public static function report(context_course $context, int $moduleid, int $groupid, string $search): class_ratings {
        if ($groupid < 0) {
            throw new \core\exception\invalid_parameter_exception('The group is 0 for every group, or the id of one.');
        }
        $report = class_ratings::for_course($context, max(0, $moduleid), $groupid, $search);
        /* Core keeps one instance of a report per reader and request and ignores the parameters of a later call
           (\core_reportbuilder\manager::get_report_from_persistent(); its reset_caches() is for tests only), so a
           batch's second call would be answered from the first one's report. Answer only from a report that holds
           what this call asked for. */
        if (
            $report->moduleid() !== max(0, $moduleid)
            || $report->requested_group() !== $groupid
            || $report->search() !== classreport::search($search)
        ) {
            throw new \core\exception\invalid_parameter_exception(
                'Each call of the class report needs its own request: the report of this request was built for another '
                . 'activity, group or search.'
            );
        }

        return $report;
    }

    /**
     * A page of the class report's learners, with what a client needs to draw them.
     *
     * The cells are the report's own formatted values (format_row(), its formatters, the grading dialogue's hooks for
     * a grader) for the columns it shows this reader; beside them the learner's id, the breakdown's address and, per
     * shown competency, the rating and the proficiency. The sort names a column key; one the report does not show,
     * or cannot sort by, leaves the report's own sort and comes back empty. A page past the last answers the last one.
     *
     * @param class_ratings $report The report, as {@see report()} returned it.
     * @param array $options 'page' (from 0), 'perpage', 'sort' (a column key), 'direction' ('asc' or 'desc'),
     *     'countfacets' (bool, default true).
     * @return array See \theme_dimensions\external\class_report_rows::execute_returns().
     */
    public static function rows(class_ratings $report, array $options): array {
        $perpage = (int) ($options['perpage'] ?? 0);
        $perpage = in_array($perpage, self::PERPAGES, true) ? $perpage : self::DEFAULT_PERPAGE;
        $page = min(self::MAX_PAGE, max(0, (int) ($options['page'] ?? 0)));
        $direction = ($options['direction'] ?? '') === 'desc' ? SORT_DESC : SORT_ASC;
        $counted = (bool) ($options['countfacets'] ?? true);

        $table = self::table($report);
        /** @var class_ratings $tablereport */
        $tablereport = $table->get_report();
        $keys = $tablereport->active_keys();
        $columns = $tablereport->get_active_columns();
        $sort = (string) ($options['sort'] ?? '');
        $sorted = isset($keys[$sort]) && $columns[$keys[$sort]]->get_is_sortable() ? $sort : '';
        // An explicit sort, never one core's table kept in the session from an earlier request.
        $table->set_sortdata($sorted === '' ? [] : [
            ['sortby' => $columns[$keys[$sorted]]->get_column_alias(), 'sortorder' => $direction],
        ]);
        $table->define_baseurl(new \moodle_url('/report/competency/index.php', ['id' => $tablereport->get_context()->instanceid]));
        $table->setup();

        $counts = null;
        $total = null;
        if ($counted) {
            [$counts, $total] = self::facet_counts($table);
        }
        $table->know_total($total);
        $records = self::page($table, $page, $perpage);
        $total ??= (int) $table->totalrows;
        if ($records === [] && $page > 0) {
            // Past the last page, or rows gone since the count: count again, then read the last page.
            $total = $table->count_rows();
            $table->know_total($total);
            $page = $total > 0 ? intdiv($total - 1, $perpage) : 0;
            if ($total > 0) {
                $records = self::page($table, $page, $perpage);
            }
        }

        $aliases = [];
        $ratings = [];
        foreach ($keys as $key => $identifier) {
            $aliases[$key] = $columns[$identifier]->get_column_alias();
        }
        foreach (array_keys($tablereport->shown()) as $competencyid) {
            $grade = $aliases['comp:' . $competencyid];
            $ratings[$competencyid] = [$grade, preg_replace('/_grade$/', '_proficiency', $grade)];
        }
        $courseid = (int) $tablereport->get_context()->instanceid;
        $rows = [];
        foreach ($records as $record) {
            $formatted = $table->format_row($record);
            $cells = [];
            foreach ($aliases as $key => $alias) {
                $cells[] = ['key' => $key, 'html' => (string) ($formatted[$alias] ?? '')];
            }
            $values = [];
            foreach ($ratings as $competencyid => [$gradealias, $proficiencyalias]) {
                $grade = (int) ($record->{$gradealias} ?? 0);
                $values[] = [
                    'key' => 'comp:' . $competencyid,
                    'competencyid' => $competencyid,
                    'grade' => $grade > 0 ? $grade : null,
                    'proficient' => $grade > 0 && (int) ($record->{$proficiencyalias} ?? 0) === 1,
                ];
            }
            $params = ['id' => $courseid, classreport::PARAM_USER => (int) $record->id];
            if ($tablereport->moduleid() > 0) {
                $params['mod'] = $tablereport->moduleid();
            }
            $rows[] = [
                'userid' => (int) $record->id,
                'url' => (new \moodle_url('/report/competency/index.php', $params))->out(false),
                'ratings' => $values,
                'cells' => $cells,
            ];
        }

        return self::request($tablereport) + [
            'sort' => $sorted,
            'direction' => $sorted === '' ? '' : ($direction === SORT_ASC ? 'asc' : 'desc'),
            'page' => $page,
            'perpage' => $perpage,
            'total' => (int) $total,
            'competencies' => $tablereport->total_competencies(),
            'columns' => self::columns($tablereport),
            'rows' => $rows,
            'counted' => $counted,
            'filters' => self::filters($table, $counts),
            'preference' => classreportpreference::export($tablereport->preference()),
        ];
    }

    /**
     * The band's figures over the rows the rows call would list.
     *
     * One statement over the report table's FROM and WHERE (the gradable learners in the viewer's groups, the search,
     * the reader's stored filters), so a group-limited grader's figures are their rows' and the learners add up to the
     * rows' total. Over the SHOWN competency columns: rated cells, proficient cells, learners with no rating in any of
     * them; and per shown competency, proficient, rated not proficient and not rated, which add up to the learners.
     *
     * @param class_ratings $report The report, as {@see report()} returned it.
     * @return array See \theme_dimensions\external\class_report_summary::execute_returns().
     */
    public static function summary(class_ratings $report): array {
        global $DB;

        $table = self::table($report);
        /** @var class_ratings $tablereport */
        $tablereport = $table->get_report();
        $context = $tablereport->get_context();
        $shown = array_values($tablereport->shown());

        $inner = [];
        $sums = ['COUNT(1) AS learners'];
        $params = $table->sql->params;
        $none = [];
        foreach ($shown as $index => $competency) {
            [$gradesql, $proficiencysql, $ratingparams] = $tablereport->rating_sql((int) $competency->id);
            $params += $ratingparams;
            $inner[] = "{$gradesql} AS g{$index}, {$proficiencysql} AS p{$index}";
            $sums[] = "SUM(CASE WHEN x.g{$index} > 0 AND x.p{$index} = 1 THEN 1 ELSE 0 END) AS proficient{$index}";
            $sums[] = "SUM(CASE WHEN x.g{$index} > 0 THEN 1 ELSE 0 END) AS rated{$index}";
            $none[] = "x.g{$index} = 0";
        }
        $inner[] = "{$tablereport->user_alias()}.id AS learnerid";
        $sums[] = 'SUM(CASE WHEN ' . ($none ? implode(' AND ', $none) : '1 = 1') . ' THEN 1 ELSE 0 END) AS unrated';
        $record = $DB->get_record_sql(
            'SELECT ' . implode(', ', $sums) . '
               FROM (SELECT ' . implode(', ', $inner) . " FROM {$table->sql->from} WHERE {$table->sql->where}) x",
            $params
        );

        $learners = (int) ($record->learners ?? 0);
        $competencies = [];
        $rated = 0;
        $proficient = 0;
        foreach ($shown as $index => $competency) {
            $competencyproficient = (int) ($record->{'proficient' . $index} ?? 0);
            $competencyrated = (int) ($record->{'rated' . $index} ?? 0);
            $rated += $competencyrated;
            $proficient += $competencyproficient;
            $competencies[] = [
                'key' => 'comp:' . (int) $competency->id,
                'competencyid' => (int) $competency->id,
                'title' => plancards::plain((string) $competency->shortname, $context),
                'proficient' => $competencyproficient,
                'notproficient' => $competencyrated - $competencyproficient,
                'notrated' => $learners - $competencyrated,
            ];
        }

        return self::request($tablereport) + [
            'learners' => $learners,
            'cells' => $learners * count($shown),
            'rated' => $rated,
            'proficient' => $proficient,
            'unrated' => (int) ($record->unrated ?? 0),
            'competencies' => $competencies,
        ];
    }

    /**
     * The request as the report applied it: the course, the activity, the group asked for, the search, and whether
     * the group rule leaves the reader no row.
     *
     * @param class_ratings $report The report.
     * @return array courseid, moduleid, groupid (0 for every group the rule admits), search, nogroup.
     */
    private static function request(class_ratings $report): array {
        return [
            'courseid' => (int) $report->get_context()->instanceid,
            'moduleid' => $report->moduleid(),
            'groupid' => $report->requested_group() ?? 0,
            'search' => $report->search(),
            'nogroup' => $report->no_group(),
        ];
    }

    /**
     * The report's table, built from its persistent and parameters as core builds it for a page turn.
     *
     * @param class_ratings $report The report.
     * @return class_ratings_table The table, not yet set up.
     */
    private static function table(class_ratings $report): class_ratings_table {
        return class_ratings_table::create((int) $report->get_report_persistent()->get('id'), $report->get_parameters());
    }

    /**
     * Read one page of a set-up table.
     *
     * @param class_ratings_table $table The table, set up.
     * @param int $page From 0.
     * @param int $perpage Rows per page.
     * @return array The records.
     */
    private static function page(class_ratings_table $table, int $page, int $perpage): array {
        $table->set_page_number($page + 1);
        $table->query_db($perpage, false);

        return $table->rawdata;
    }

    /**
     * The columns the reader sees and those they may choose, in display order: the learner and the identity fields
     * (fixed), then every competency of the catalogue. Titles in the plain spelling; the learner column carries the
     * theme's own title, not core's "Full name with link".
     *
     * @param class_ratings $report The table's report.
     * @return array Each: key, title, sortable, default, active, fixed.
     */
    private static function columns(class_ratings $report): array {
        $active = $report->get_active_columns();
        $keys = $report->active_keys();
        $context = $report->get_context();
        $columns = [];
        foreach ($keys as $key => $identifier) {
            if (str_starts_with($key, 'comp:')) {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'title' => $key === 'learner'
                    ? get_string('competency_classreport_learner', 'theme_dimensions')
                    : html_entity_decode((string) $active[$identifier]->get_title(), ENT_QUOTES | ENT_HTML401, 'UTF-8'),
                'sortable' => $active[$identifier]->get_is_sortable(),
                'default' => true,
                'active' => true,
                'fixed' => true,
            ];
        }
        $position = 0;
        foreach ($report->catalogue() as $id => $competency) {
            $key = 'comp:' . $id;
            $columns[] = [
                'key' => $key,
                'title' => plancards::plain((string) $competency->shortname, $context),
                'sortable' => true,
                'default' => $position++ < classreport::MAX_COLUMNS,
                'active' => isset($keys[$key]),
                'fixed' => false,
            ];
        }

        return $columns;
    }

    /**
     * The report's filters as a client draws them: what each is, what it offers, whether it applies, and each
     * option's count.
     *
     * Built from the report's own active filters, so a panel cannot offer a filter the report would not apply. Option
     * labels come back plain: the report's own options are the escaped spelling its form wants.
     *
     * @param class_ratings_table $table The rows' table.
     * @param array|null $counts The facets' counts ({@see facet_counts()}), null when not counted.
     * @return array Each: name, title, type, facet, options (value, label, count), applied, values (JSON).
     */
    private static function filters(class_ratings_table $table, ?array $counts): array {
        /** @var class_ratings $report */
        $report = $table->get_report();
        $values = $table->filter_values();
        $filters = [];
        foreach ($report->get_active_filters() as $identifier => $filter) {
            $class = $filter->get_filter_class();
            $offered = self::offered($filter);
            $facet = self::is_facet($filter) ? 'choice' : '';
            $own = array_filter(
                $values,
                static fn(string $name): bool => str_starts_with($name, $identifier . '_'),
                ARRAY_FILTER_USE_KEY
            );
            $facetcounts = $counts !== null && $facet !== '' ? ($counts[$identifier] ?? []) : null;
            $options = [];
            foreach ($offered as $value => $label) {
                $options[] = [
                    'value' => (string) $value,
                    'label' => $label,
                    'count' => $facetcounts === null ? -1 : (int) ($facetcounts[(string) $value] ?? 0),
                ];
            }
            $filters[] = [
                'name' => $identifier,
                'title' => html_entity_decode((string) $filter->get_header(), ENT_QUOTES | ENT_HTML401, 'UTF-8'),
                'type' => (new \ReflectionClass($class))->getShortName(),
                'facet' => $facet,
                'options' => $options,
                'applied' => self::applies($filter, $values),
                'values' => json_encode((object) $own),
            ];
        }

        return $filters;
    }

    /**
     * A filter's options in the plain spelling, by value.
     *
     * @param filter $filter The filter.
     * @return array Value (string) => plain label; empty for a filter without options.
     */
    private static function offered(filter $filter): array {
        $offered = [];
        if (is_array($filter->get_options())) {
            foreach ($filter->get_options() as $value => $label) {
                $offered[(string) $value] = html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML401, 'UTF-8');
            }
        }

        return $offered;
    }

    /**
     * Whether a filter is a facet the panel counts: a competency's rating, whose options are its scale and "Not rated".
     *
     * The identity fields' filters are text, or a choice among every country, and are not counted.
     *
     * @param filter $filter The filter.
     * @return bool
     */
    private static function is_facet(filter $filter): bool {
        return $filter->get_filter_class() === rating::class;
    }

    /**
     * Whether a filter is in force for the reader's stored values: its class builds a condition from them.
     *
     * @param filter $filter The filter.
     * @param array $values The reader's stored filter values.
     * @return bool
     */
    private static function applies(filter $filter, array $values): bool {
        $class = $filter->get_filter_class();
        [$sql] = $class::create($filter)->get_sql_filter($values);

        return $sql !== '';
    }

    /**
     * Every rating facet's counts, and the rows' total.
     *
     * A facet's counts are what the rows' total would be with each of its options chosen in place of its own value:
     * counted under the table's WHERE without that facet's own condition (base conditions, the group rule, the search
     * and every other stored filter hold). The facets whose own filter is not in force share the table's own WHERE,
     * so they are counted together, beside the total; a facet in force counts under the WHERE without it. Each
     * WHERE's facets are conditional sums of one statement, at most {@see SUMS_PER_STATEMENT} of them: one row back,
     * whatever the learners. So the statements are bounded by the shown columns, never by the learners or the options.
     *
     * @param class_ratings_table $table The rows' table.
     * @return array [facet identifier => (option value => learners), the rows' total].
     */
    private static function facet_counts(class_ratings_table $table): array {
        /** @var class_ratings $report */
        $report = $table->get_report();
        $values = $table->filter_values();
        $shared = [];
        $counts = [];
        foreach ($report->get_active_filters() as $identifier => $filter) {
            if (!self::is_facet($filter)) {
                continue;
            }
            if (!self::applies($filter, $values)) {
                $shared[$identifier] = $filter;
                continue;
            }
            $counts += self::count_options($table, $identifier, [$identifier => $filter])[0];
        }
        [$sharedcounts, $total] = self::count_options($table, '', $shared);

        return [$sharedcounts + $counts, $total];
    }

    /**
     * How many learners each option of some facets would list, under the table's WHERE without one filter.
     *
     * @param class_ratings_table $table The rows' table.
     * @param string $exclude The filter whose own condition is left out, '' for none.
     * @param array $filters Facet identifier => filter.
     * @return array [facet identifier => (option value => learners), the learners under that WHERE].
     */
    private static function count_options(class_ratings_table $table, string $exclude, array $filters): array {
        global $DB;

        [$where, $whereparams] = $table->where_without($exclude);
        $from = $table->sql->from;
        $counts = array_fill_keys(array_keys($filters), []);
        // Each chunk is one statement: the facets' fields computed once per learner, then summed per option.
        $chunks = [[]];
        $size = 0;
        foreach ($filters as $identifier => $filter) {
            $options = count(self::offered($filter));
            if ($size > 0 && $size + $options > self::SUMS_PER_STATEMENT) {
                $chunks[] = [];
                $size = 0;
            }
            $chunks[count($chunks) - 1][$identifier] = $filter;
            $size += $options;
        }

        $total = 0;
        foreach ($chunks as $chunk) {
            $params = array_merge($table->sql->params, $whereparams);
            $inner = ['1 AS one'];
            $sums = ['COUNT(1) AS learners'];
            $names = [];
            $position = 0;
            foreach ($chunk as $identifier => $filter) {
                $field = 'f' . $position++;
                $inner[] = $filter->get_field_sql() . " AS {$field}";
                $params += $filter->get_field_params();
                foreach (array_keys(self::offered($filter)) as $value) {
                    // The options are a scale's ratings and 0: integers, written into the statement as integers.
                    $value = (int) $value;
                    $sums[] = "SUM(CASE WHEN x.{$field} = {$value} THEN 1 ELSE 0 END) AS {$field}v{$value}";
                    $names[$identifier][(string) $value] = "{$field}v{$value}";
                }
            }
            $record = $DB->get_record_sql(
                'SELECT ' . implode(', ', $sums) . '
                   FROM (SELECT ' . implode(', ', $inner) . " FROM {$from} WHERE {$where}) x",
                $params
            );
            $total = (int) ($record->learners ?? 0);
            foreach ($names as $identifier => $columns) {
                foreach ($columns as $value => $column) {
                    $counts[$identifier][$value] = (int) ($record->{$column} ?? 0);
                }
            }
        }

        return [$counts, $total];
    }
}
