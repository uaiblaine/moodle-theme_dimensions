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
use core_reportbuilder\system_report;
use core_reportbuilder\system_report_factory;
use core_user\fields;
use moodle_url;
use stdClass;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\reportbuilder\local\entities\coursecompetency;

/**
 * One row per learner of a course, one column per course competency: the rating of each.
 *
 * The rows are the course's gradable learners ({@see classreport::learners()}), in the viewer's groups
 * ({@see classreport::groups()}); the columns the course's competencies in its order, at most
 * {@see classreport::MAX_COLUMNS}, each keyed `comp:<competencyid>` ({@see active_keys()}). Every rule is applied
 * in {@see initialise()} from the report's context and the current user, so the page, every sort, filter and page
 * turn (core_table_get_dynamic_table_content builds the report again) and the download (core's download.php builds
 * it again) list the same rows. The one parameter, the activity, is a client's to set and only narrows the columns.
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

    /** @var array Catalogue key => column unique identifier, in display order. */
    protected $activekeys = [];

    /** @var int How many competencies the course (or the activity) has, the columns being the first of them. */
    protected $total = 0;

    /** @var bool Whether the group rule leaves this viewer no row. */
    protected $nogroup = false;

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
     * @param context_course $context The course's context.
     * @param int $moduleid A course module whose competencies are the columns, or 0.
     * @return self The report.
     */
    public static function for_course(context_course $context, int $moduleid = 0): self {
        return system_report_factory::create(
            self::class,
            $context,
            'theme_dimensions',
            self::AREA,
            0,
            [self::PARAM_MODULE => max(0, $moduleid)]
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
        $this->set_main_table('user', $useralias);
        $this->add_entity($user);
        $ratings = new coursecompetency();
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
        $this->nogroup = classreport::groups(get_course($courseid)) === [];
        [$where, $params] = classreport::learners($context, "{$useralias}.id");
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

        [$competencies, $this->total] = classreport::competencies($courseid, $this->moduleid());
        $scales = classreport::scales($competencies, $context);
        $hooks = has_capability(classreport::GRADE, $context);
        $this->add_filter_from_entity('user:fullname');
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
     * How many competencies the course (or the activity) has; the columns are the first {@see classreport::MAX_COLUMNS}.
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
