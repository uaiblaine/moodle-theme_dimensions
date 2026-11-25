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
 * Theme Dimensions - The class report of competency ratings
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\reportbuilder\local\systemreports;

use context_course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use core_user\fields;
use moodle_url;
use stdClass;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\local\competency\classreportpreference;
use theme_dimensions\local\competency\reportpersistents;
use theme_dimensions\reportbuilder\local\entities\coursecompetency;

/**
 * One row per learner of a course, one column per course competency: the rating of each.
 *
 * The rows are the course's gradable learners ({@see classreport::learners()}), in the viewer's groups
 * ({@see classreport::groups()}); the columns the course's competencies the reader chose
 * ({@see classreportpreference}), else the first {@see classreport::MAX_COLUMNS} in the course's order, each keyed
 * `comp:<competencyid>` ({@see active_keys()}), with a filter for each shown column only. Every rule is applied in
 * {@see initialise()} from the report's context and the current user, so the page, every sort, filter and page turn
 * (core_table_get_dynamic_table_content builds the report again), the web services and the download (core's
 * download.php builds it again) list the same rows. Three parameters are a client's to set, and each can only narrow:
 * the activity ({@see PARAM_MODULE}, the columns), the group ({@see PARAM_GROUP}, through the group rule itself) and
 * the search ({@see PARAM_SEARCH}, through {@see search_condition()}, the one definition the web services use).
 *
 * Reading the report writes nothing: the ratings are read in SQL from `competency_usercompcourse`, where core's own
 * export inserts a row for every competency a learner has none for.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class class_ratings extends system_report {
    /** @var string The report persistent's area. */
    public const AREA = 'classratings';

    /** @var string The parameter naming an activity whose competencies are the columns. */
    public const PARAM_MODULE = 'moduleid';

    /** @var string The parameter naming the group a client asks for, in place of the session's choice. */
    public const PARAM_GROUP = 'groupid';

    /** @var string The parameter holding a search of the learner's full name. */
    public const PARAM_SEARCH = 'search';

    /** @var string The filter whose field the search reads: the learner's full name. */
    public const SEARCH_FILTER = 'user:fullname';

    /** @var array Catalogue key => column unique identifier, in display order. */
    protected $activekeys = [];

    /** @var int How many competencies the course (or the activity) has, the columns being the first of them. */
    protected $total = 0;

    /** @var bool Whether the group rule leaves this viewer no row. */
    protected $nogroup = false;

    /** @var array Competency id => record, every competency the reader may choose as a column, in the course's order. */
    protected $catalogue = [];

    /** @var array Competency id => record (with its escaped `title`), the shown columns, in display order. */
    protected $shown = [];

    /** @var array The reader's choice, as {@see classreportpreference::read()} returned it. */
    protected $preference = ['columns' => null, 'widths' => []];

    /** @var string The user table's alias. */
    protected $useralias = '';

    /** @var coursecompetency|null The ratings entity. */
    protected $ratings = null;

    /** @var string The search applied, cleaned, '' for none. */
    protected $search = '';

    /**
     * Whether the current user may read this report: {@see classreport::may_view()} on the report's context.
     *
     * The whole gate, self-sufficient: the table's own requests and core's download.php build the report from its
     * persistent, whose context core derives server-side, and ask this before anything is read.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return classreport::may_view($this->get_context());
    }

    /**
     * The report of a course, created or found through core's factory.
     *
     * A duplicate persistent of the course (two first views racing) is removed first, keeping the oldest
     * ({@see reportpersistents::keep_oldest()}).
     *
     * The page names the activity alone, so its group is the session's and it searches nothing; the web services
     * name the group and the search as well, and the parameters travel with the report to its table and its download.
     *
     * @param context_course $context The course's context.
     * @param int $moduleid A course module whose competencies are the columns, or 0.
     * @param int|null $groupid A group a client asked for (0 for all participants), or null for the session's choice.
     * @param string $search A search of the learner's name, '' for none.
     * @return self The report.
     */
    public static function for_course(
        context_course $context,
        int $moduleid = 0,
        ?int $groupid = null,
        string $search = ''
    ): self {
        reportpersistents::keep_oldest((int) $context->id, self::class, 'theme_dimensions', self::AREA, 0);
        $parameters = [self::PARAM_MODULE => max(0, $moduleid)];
        if ($groupid !== null && $groupid >= 0) {
            $parameters[self::PARAM_GROUP] = $groupid;
        }
        $search = classreport::search($search);
        if ($search !== '') {
            $parameters[self::PARAM_SEARCH] = $search;
        }

        return system_report_factory::create(
            self::class,
            $context,
            'theme_dimensions',
            self::AREA,
            0,
            $parameters
        );
    }

    /**
     * Build the report.
     *
     * Runs before {@see can_view()} (core initialises, then validates), so a context that is not a course's builds
     * an empty report and leaves the refusal to can_view().
     *
     * @return void
     */
    protected function initialise(): void {
        $user = new user();
        $useralias = $user->get_table_alias('user');
        $this->useralias = $useralias;
        $this->set_main_table('user', $useralias);
        $this->add_entity($user);
        $ratings = new coursecompetency();
        $this->ratings = $ratings;
        $this->add_entity($ratings);
        $this->add_base_fields("{$useralias}.id");

        $context = $this->get_context();
        $learner = $this->add_column_from_entity('user:fullnamewithlink');
        $this->activekeys = ['learner' => $learner->get_unique_identifier()];
        if (!$context instanceof context_course) {
            $this->add_base_condition_sql('1 = 0');
            return;
        }
        $courseid = (int) $context->instanceid;

        /* The group rule and the gradable capability, as one base condition, so every door of the report
           applies them. The no-group case is decided before the join is built: see classreport::groups(). */
        $this->nogroup = classreport::groups(get_course($courseid), $this->requested_group()) === [];
        [$where, $params] = classreport::learners($context, "{$useralias}.id", $this->requested_group());
        $this->add_base_condition_sql($where, $params);

        $learner->set_callback([self::class, 'learner_link'], [
            'courseid' => $courseid,
            'moduleid' => $this->moduleid(),
            'viewfullnames' => has_capability('moodle/site:viewfullnames', $context),
        ]);

        /* Identity fields from core's helper, which returns none outside moodle/site:viewuseridentity; the filters
           answer the same question, so a filter never narrows on a field whose column is withheld. */
        foreach ($user->get_identity_columns($context) as $column) {
            $added = $this->add_column($column);
            $this->activekeys['identity:' . $added->get_name()] = $added->get_unique_identifier();
        }

        // The columns: the reader's choice among the catalogue, read again on every build, so a forged or stale id
        // matches nothing; filters exist for the shown columns only.
        $this->catalogue = classreport::catalogue($courseid, $this->moduleid());
        $this->total = count($this->catalogue);
        $this->preference = classreportpreference::read($courseid);
        $competencies = classreport::chosen($this->catalogue, $this->preference['columns']);
        $scales = classreport::scales($competencies, $context);
        $hooks = has_capability(classreport::GRADE, $context);
        $this->add_filter_from_entity(self::SEARCH_FILTER);
        foreach ($user->get_identity_filters($context) as $filter) {
            $this->add_filter($filter);
        }
        foreach ($competencies as $competency) {
            $competency->title = format_string($competency->shortname, true, ['context' => $context]);
            $scale = $scales[$competency->scaleid] ?? ['plain' => [], 'escaped' => []];
            $column = $this->add_column(
                $ratings->rating_column("{$useralias}.id", $courseid, $competency, $scale['plain'], $hooks)
            );
            $this->activekeys['comp:' . $competency->id] = $column->get_unique_identifier();
            $this->add_filter($ratings->rating_filter("{$useralias}.id", $courseid, $competency, $scale['escaped']));
            $this->shown[(int) $competency->id] = $competency;
        }

        // The search, from the report's own parameter, through the one definition the web services use.
        $this->search = classreport::search((string) $this->get_parameter(self::PARAM_SEARCH, '', PARAM_TEXT));
        if ($this->search !== '') {
            [$searchsql, $searchparams] = $this->search_condition($this->search);
            $this->add_base_condition_sql("({$searchsql})", $searchparams);
        }

        $this->add_attributes(['data-dim-classreport' => $courseid]);
        $this->set_initial_sort_column('user:fullnamewithlink', SORT_ASC);
        $this->set_default_per_page(30);
        $this->set_downloadable(true, get_string('report:class_ratings', 'theme_dimensions'));
    }

    /**
     * The report's name, as core's report lists would show it.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('report:class_ratings', 'theme_dimensions');
    }

    /**
     * The activity whose competencies are the columns, from the report's parameters, or 0.
     *
     * @return int
     */
    public function moduleid(): int {
        return max(0, (int) $this->get_parameter(self::PARAM_MODULE, 0, PARAM_INT));
    }

    /**
     * The group a client asked for through the report's parameters, or null for the session's choice.
     *
     * @return int|null
     */
    public function requested_group(): ?int {
        $group = $this->get_parameter(self::PARAM_GROUP, null, PARAM_INT);

        return $group === null || (int) $group < 0 ? null : (int) $group;
    }

    /**
     * The search applied to this report's rows, cleaned, '' for none.
     *
     * @return string
     */
    public function search(): string {
        return $this->search;
    }

    /**
     * The condition a search adds: the learner's full name as the report's own full-name filter reads it, ignoring
     * case and accents, the search a substring.
     *
     * The one definition of the search, added as a base condition in {@see initialise()} from the report's own
     * parameter, so the page's table, the web services and the download read the same rows. The full name is a field
     * every reader of the report is shown; the identity fields are not searched. The filter's parameters are renamed
     * to names core generates, so they cannot meet the same filter's own when the reader also applied it.
     *
     * @param string $search A cleaned search ({@see classreport::search()}).
     * @return array [the condition, its named parameters].
     */
    public function search_condition(string $search): array {
        global $DB;

        $filter = $this->get_filter(self::SEARCH_FILTER);
        // Core calls the callback twice per name (the SQL, then the parameters): one new name for each.
        $names = [];
        [$fieldsql, $fieldparams] = database::sql_replace_parameters(
            $filter->get_field_sql(),
            $filter->get_field_params(),
            static function (string $name) use (&$names): string {
                return $names[$name] ??= database::generate_param_name('_cs');
            }
        );
        $param = database::generate_param_name();

        return [
            $DB->sql_like($fieldsql, ":{$param}", false, false),
            $fieldparams + [$param => '%' . $DB->sql_like_escape($search) . '%'],
        ];
    }

    /**
     * The SQL of one learner's rating and proficiency in one competency of this report's course, with parameters of
     * their own on every call: the summary's figures read the cells with them.
     *
     * @param int $competencyid The competency.
     * @return array [the rating SQL, 0 when none; the proficiency SQL, null when none; their named parameters].
     */
    public function rating_sql(int $competencyid): array {
        $courseid = (int) $this->get_context()->instanceid;
        [$grade, $gradeparams] = $this->ratings->grade_sql("{$this->useralias}.id", $courseid, $competencyid);
        [$proficiency, $proficiencyparams] = $this->ratings->rating_sql(
            "{$this->useralias}.id",
            $courseid,
            $competencyid,
            'proficiency'
        );

        return [$grade, $proficiency, $gradeparams + $proficiencyparams];
    }

    /**
     * The alias of the report's main table, the user.
     *
     * @return string
     */
    public function user_alias(): string {
        return $this->useralias;
    }

    /**
     * Every competency the reader may choose as a column, in the course's order (the activity's, with one).
     *
     * @return array Competency id => record (id, shortname, idnumber, scaleid).
     */
    public function catalogue(): array {
        return $this->catalogue;
    }

    /**
     * The competencies shown as columns, in display order.
     *
     * @return array Competency id => record (id, shortname, idnumber, scaleid, and the escaped `title`).
     */
    public function shown(): array {
        return $this->shown;
    }

    /**
     * The reader's choice this report was built from.
     *
     * @return array As {@see classreportpreference::read()} returns it.
     */
    public function preference(): array {
        return $this->preference;
    }

    /**
     * The columns this report shows, by catalogue key: `learner`, `identity:<name>` and `comp:<competencyid>`.
     *
     * The keys are the same on every instance of the report, so a later reader (part two's client) finds a column
     * by its competency.
     *
     * @return array Key => column unique identifier, in display order.
     */
    public function active_keys(): array {
        return $this->activekeys;
    }

    /**
     * How many competencies the course (or the activity) has; the columns are at most {@see classreport::MAX_COLUMNS}
     * of them.
     *
     * @return int
     */
    public function total_competencies(): int {
        return $this->total;
    }

    /**
     * How many competency columns the report shows.
     *
     * @return int
     */
    public function shown_competencies(): int {
        return count(array_filter(
            array_keys($this->activekeys),
            static fn(string $key): bool => str_starts_with($key, 'comp:')
        ));
    }

    /**
     * Whether the group rule leaves this viewer no row: separate groups, no `accessallgroups`, in no group.
     *
     * @return bool
     */
    public function no_group(): bool {
        return $this->nogroup;
    }

    /**
     * The learner's name, linked to their breakdown on core's page (`index.php?id=&user=`).
     *
     * @param mixed $value The column's first field.
     * @param stdClass $row The name fields and the user's id.
     * @param mixed $arguments courseid, moduleid, viewfullnames.
     * @return string The cell's markup.
     */
    public static function learner_link($value, stdClass $row, $arguments = null): string {
        global $OUTPUT;

        if (empty($row->id)) {
            return '';
        }
        foreach (fields::get_name_fields() as $namefield) {
            $row->{$namefield} = $row->{$namefield} ?? '';
        }
        $params = ['id' => (int) $arguments['courseid'], classreport::PARAM_USER => (int) $row->id];
        if ((int) $arguments['moduleid'] > 0) {
            $params['mod'] = (int) $arguments['moduleid'];
        }

        return $OUTPUT->render_from_template('theme_dimensions/competency_classreport_learner', [
            'url' => (new moodle_url('/report/competency/index.php', $params))->out(false),
            'name' => fullname($row, !empty($arguments['viewfullnames'])),
        ]);
    }
}
