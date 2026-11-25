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
 * Theme Dimensions - Core's table of the class report, read without rendering it
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use core_reportbuilder\table\system_report_table;
use core_reportbuilder\table\system_report_table_filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;

/**
 * Core's system report table for the class report ({@see class_ratings}), read without rendering it.
 *
 * The table core's dynamic table and its download build, so the rows carry the report's base conditions (the
 * gradable learners in the viewer's groups, the search), the reader's stored filters, the columns the reader chose and
 * the report's own formatters: the web services ({@see \theme_dimensions\local\competency\classreportengine}) cannot
 * widen what the report shows. Four additions only, copied from local_evidencehub's headless table and adapted to a
 * row that is one user: that WHERE rebuilt without one filter, for the facet counts; a page read in two statements
 * whose cost does not grow with the page number; a count the page read can be given instead of counting again; and no
 * write of the table's preferences into the session, which the read-only services must leave as they found it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class class_ratings_table extends system_report_table {
    /** @var array|null The reader's stored filter values, read once for every WHERE rebuilt. */
    protected $filtervalues = null;

    /** @var int|null The rows' total when the caller already knows it ({@see know_total()}). */
    protected $knowntotal = null;

    /**
     * A table that holds its report whatever the request carries.
     *
     * Core's constructor leaves the report unloaded when the request names the dynamic table service
     * (`info=core_table_get_dynamic_table_content`), expecting its filterset to load it; a web service of the theme has
     * no filterset, so it would find no report. Loading it through the filterset is what core's own exporter does.
     *
     * @param int $reportid The report's persistent id.
     * @param array $parameters The report's parameters.
     * @return self
     */
    public static function create(int $reportid, array $parameters): self {
        $table = parent::create($reportid, $parameters);
        if ($table->report === null) {
            $filterset = new system_report_table_filterset();
            $filterset->add_filter(new integer_filter('reportid', null, [$reportid]));
            $filterset->add_filter(new string_filter('parameters', null, [json_encode($parameters)]));
            $table->set_filterset($filterset);
        }

        return $table;
    }

    /**
     * The report this table was built from.
     *
     * @return system_report
     */
    public function get_report(): system_report {
        return $this->report;
    }

    /**
     * The reader's stored filter values, read from core's store once per table.
     *
     * @return array
     */
    public function filter_values(): array {
        $this->filtervalues ??= $this->report->get_filter_values();

        return $this->filtervalues;
    }

    /**
     * The table's WHERE clause without one filter's condition: what the rows would be under every other
     * condition, for the panel's facet counts.
     *
     * Rebuilt as core's base_report_table::init_sql() builds the table's own (the report's base condition, then each
     * active filter's SQL for the reader's stored values, each with parameters of its own); the filter named is left
     * out, and with a name no filter has it is the table's own WHERE.
     *
     * @param string $exclude The unique identifier of the filter to leave out.
     * @return array [the WHERE clause, its parameters].
     */
    public function where_without(string $exclude): array {
        [$base, $params] = $this->report->get_base_condition();
        $wheres = $base !== '' ? ["({$base})"] : [];
        $values = $this->filter_values();
        $index = 0;
        foreach ($this->report->get_active_filters() as $identifier => $filter) {
            $index++;
            if ($identifier === $exclude) {
                continue;
            }
            $class = $filter->get_filter_class();
            [$sql, $filterparams] = $class::create($filter)->get_sql_filter($values);
            if ($sql === '') {
                continue;
            }
            [$sql, $filterparams] = database::sql_replace_parameters(
                $sql,
                $filterparams,
                static fn(string $name): string => "{$name}_fw{$index}"
            );
            $wheres[] = "({$sql})";
            $params = array_merge($params, $filterparams);
        }

        return [$wheres ? implode(' AND ', $wheres) : '1=1', $params];
    }

    /**
     * Give the rows' total, so the next page read sends no count. Null counts again.
     *
     * @param int|null $total The total, or null.
     * @return void
     */
    public function know_total(?int $total): void {
        $this->knowntotal = $total;
    }

    /**
     * How many rows the table lists: one count over the table's FROM and WHERE, with no column, no order and no
     * window.
     *
     * @return int
     */
    public function count_rows(): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {$this->sql->from} WHERE {$this->sql->where}",
            $this->sql->params
        );
    }

    /**
     * Read one page in two statements: the page's user ids and the total, then those rows whole.
     *
     * Core's own read ({@see \core_reportbuilder\table\base_report_table::query_db()}) selects every column under
     * COUNT(1) OVER() with LIMIT and OFFSET, so the database computes each competency's two subqueries for every
     * learner before the page. The first statement here selects only the user id and the sort keys over the same SQL;
     * the second reads the page's learners whole, with the table's own conditions as well, and puts them in the first
     * statement's order, leaving out a learner who went away between the two. A table that is downloading reads its
     * rows as core's does.
     *
     * @param int $pagesize Rows per page.
     * @param bool $useinitialsbar Unused, as in core's report table.
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true): void {
        global $DB;

        if ($this->is_downloading()) {
            parent::query_db($pagesize, $useinitialsbar);
            return;
        }

        $this->pagesize($pagesize, 0);
        $tablesql = $this->get_table_sql(false);
        $countalias = database::generate_alias();
        $count = '';
        if ($this->knowntotal === null) {
            $count = $DB->is_count_window_function_supported()
                ? 'COUNT(1) OVER()'
                : (string) $DB->count_records_sql("SELECT COUNT(1) FROM ({$tablesql}) results", $this->sql->params);
            $count = ", {$count} AS {$countalias}";
        }
        $ids = [];
        $total = 0;
        // A recordset with a limit: one statement on every family.
        $recordset = $DB->get_recordset_sql(
            "SELECT results.id{$count}
               FROM ({$tablesql}) results
           ORDER BY {$this->page_order()}",
            $this->sql->params,
            (int) $this->get_page_start(),
            (int) $this->get_page_size()
        );
        foreach ($recordset as $record) {
            $ids[] = (int) $record->id;
            if ($this->knowntotal === null) {
                $total = (int) $record->{$countalias};
            }
        }
        $recordset->close();
        $total = $this->knowntotal ?? $total;
        $this->pagesize($pagesize, $total);

        $rows = $ids ? $this->read_rows($ids) : [];
        $this->rawdata = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $this->rawdata[] = $rows[$id];
            }
        }
    }

    /**
     * The second statement: the page's learners whole, under the table's own conditions.
     *
     * @param array $ids The page's user ids; at most a page (100).
     * @return array User id => the row.
     */
    protected function read_rows(array $ids): array {
        global $DB;

        /** @var class_ratings $report */
        $report = $this->report;
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, database::generate_param_name() . '_');
        // With a limit, as core's own read has: PostgreSQL's driver reads a recordset with no limit through a cursor,
        // three statements where this is one.
        $recordset = $DB->get_recordset_sql(
            "SELECT {$this->sql->fields} FROM {$this->sql->from}
              WHERE ({$this->sql->where}) AND {$report->user_alias()}.id {$insql} {$this->groupbysql}",
            $this->sql->params + $inparams,
            0,
            count($ids)
        );
        $rows = [];
        foreach ($recordset as $record) {
            $rows[(int) $record->id] = $record;
        }
        $recordset->close();

        return $rows;
    }

    /**
     * The ORDER BY of the page's learners: the reader's sort, then the user id.
     *
     * Each page is a statement of its own, and learners tied on the sort (many share a rating) could otherwise land
     * on two pages or on none: the user id is unique.
     *
     * @return string The ORDER BY list, over the aliases of the table's SQL wrapped as results.
     */
    public function page_order(): string {
        $sort = $this->get_sql_sort();

        return ($sort !== '' ? "{$sort}, " : '') . 'results.id';
    }

    /**
     * Keep the table's preferences out of the session.
     *
     * Core's table writes the sort it set up into `$SESSION->flextable` (or a user preference); the theme's services
     * send their sort on every call and declare a read-only session, which must leave `$SESSION` as it found it.
     *
     * @param array|null $oldprefs The preferences before setup, unused.
     * @return void
     */
    protected function save_preferences($oldprefs): void {
    }
}
