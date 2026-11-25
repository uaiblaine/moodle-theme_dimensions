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
 * Theme Dimensions - The Report Builder entity of a learner's rating in a course competency
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use stdClass;
use theme_dimensions\reportbuilder\local\filters\rating;

/**
 * A learner's rating in one competency of a course, as core keeps it in `competency_usercompcourse`.
 *
 * Core has no Report Builder entity for those rows. This one offers no fixed column: which competencies exist is the
 * course's, so the report asks for one column ({@see rating_column()}) and one filter ({@see rating_filter()}) per
 * competency, each built in the request. Each reads the row by its unique index `(userid, courseid, competencyid)`
 * through a correlated scalar subquery: a join per competency would need the competency in the join's text, which a
 * Report Builder join cannot carry as a parameter, and a scalar subquery on a unique key cannot multiply the row. A
 * learner without a row (core inserts one the first time its own pages read the learner) reads as not rated.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursecompetency extends base {
    /** @var string The table the entity reads. */
    public const TABLE = 'competency_usercompcourse';

    /** @var string The template of a rating cell. */
    public const CELL_TEMPLATE = 'theme_dimensions/competency_classreport_cell';

    /**
     * The tables the entity reads.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [self::TABLE];
    }

    /**
     * The entity's title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity:coursecompetency', 'theme_dimensions');
    }

    /**
     * The SQL of one field of one learner's row for one competency of one course, with its own parameters.
     *
     * Every call names its parameters anew ({@see database::generate_param_name()}), so the same competency can be a
     * column's two fields and a filter of one statement.
     *
     * @param string $useridsql The SQL of the learner's id in the outer query.
     * @param int $courseid The course.
     * @param int $competencyid The competency.
     * @param string $field 'grade' or 'proficiency'.
     * @return array [the scalar subquery, its parameters].
     */
    public function rating_sql(string $useridsql, int $courseid, int $competencyid, string $field): array {
        $alias = $this->get_table_alias(self::TABLE);
        $field = $field === 'proficiency' ? 'proficiency' : 'grade';
        [$course, $competency] = database::generate_param_names(2);

        return [
            "(SELECT {$alias}.{$field}
                FROM {" . self::TABLE . "} {$alias}
               WHERE {$alias}.userid = {$useridsql}
                 AND {$alias}.courseid = :{$course}
                 AND {$alias}.competencyid = :{$competency})",
            [$course => $courseid, $competency => $competencyid],
        ];
    }

    /**
     * The SQL of the learner's rating in one competency, 0 where there is none.
     *
     * A rating is an index into the competency's scale, from 1; 0 stands for "not rated", both for a missing row and
     * for a row without a grade. So the column sorts the unrated together, below every rating, and the filter offers
     * them as one value, without `NULLS FIRST`, which not every database reads.
     *
     * @param string $useridsql The SQL of the learner's id in the outer query.
     * @param int $courseid The course.
     * @param int $competencyid The competency.
     * @return array [the SQL, its parameters].
     */
    public function grade_sql(string $useridsql, int $courseid, int $competencyid): array {
        [$sql, $params] = $this->rating_sql($useridsql, $courseid, $competencyid, 'grade');

        return ["COALESCE({$sql}, 0)", $params];
    }

    /**
     * The column of one competency: the learner's rating, by name on that competency's scale.
     *
     * Named `comp<id>`; the report keys it `comp:<id>` for its readers. Three fields: the rating (0 when none), the
     * proficiency, and the learner's id the cell's hooks carry. Sortable on the rating.
     *
     * @param string $useridsql The SQL of the learner's id in the outer query.
     * @param int $courseid The course.
     * @param stdClass $competency The competency: id, and its name in `title` (escaped, as a heading prints it raw).
     * @param array $items The scale's items, rating => plain name.
     * @param bool $hooks Whether the cell carries the grading dialogue's hooks (a viewer who may rate).
     * @return column The column.
     */
    public function rating_column(string $useridsql, int $courseid, stdClass $competency, array $items, bool $hooks): column {
        [$gradesql, $gradeparams] = $this->grade_sql($useridsql, $courseid, (int) $competency->id);
        [$proficiencysql, $proficiencyparams] = $this->rating_sql($useridsql, $courseid, (int) $competency->id, 'proficiency');

        return (new column(
            'comp' . (int) $competency->id,
            new lang_string('competency_classreport_column', 'theme_dimensions', $competency->title),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($gradesql, 'grade', $gradeparams)
            ->add_field($proficiencysql, 'proficiency', $proficiencyparams)
            ->add_field($useridsql, 'learnerid')
            ->set_is_sortable(true)
            ->set_disabled_aggregation_all()
            ->add_callback([self::class, 'format_rating'], [
                'competencyid' => (int) $competency->id,
                'courseid' => $courseid,
                'items' => $items,
                'hooks' => $hooks,
            ]);
    }

    /**
     * The filter of one competency: one rating of its scale, or "Not rated".
     *
     * Over the same SQL as the column's rating, with its own parameters. The options are the scale's items in the
     * escaped spelling the filter form's select prints raw; a value outside them filters nothing.
     *
     * @param string $useridsql The SQL of the learner's id in the outer query.
     * @param int $courseid The course.
     * @param stdClass $competency The competency: id, and its escaped name in `title`.
     * @param array $items The scale's items, rating => escaped name.
     * @return filter The filter.
     */
    public function rating_filter(string $useridsql, int $courseid, stdClass $competency, array $items): filter {
        [$sql, $params] = $this->grade_sql($useridsql, $courseid, (int) $competency->id);

        return (new filter(
            rating::class,
            'comp' . (int) $competency->id,
            new lang_string('competency_classreport_filter', 'theme_dimensions', $competency->title),
            $this->get_entity_name(),
            $sql,
            $params
        ))
            ->add_joins($this->get_joins())
            ->set_options([0 => get_string('competency_classreport_notrated', 'theme_dimensions')] + $items);
    }

    /**
     * A rating cell: the rating's name in the status label of its proficiency, linked to core's page of the learner's
     * competency in the course.
     *
     * The words carry the meaning, not the colour: the name, then "Proficient" or "Not proficient" (visually hidden,
     * read and downloaded); "Not rated" alone where there is no rating. The grading dialogue's hooks are written only
     * when the report says the viewer may rate. A rating outside the scale (one edited since) is shown as its number.
     *
     * @param mixed $value The rating, 0 when none.
     * @param stdClass $row The column's fields: grade, proficiency, learnerid.
     * @param mixed $arguments competencyid, courseid, items (rating => plain name), hooks.
     * @return string The cell's markup.
     */
    public static function format_rating($value, stdClass $row, $arguments = null): string {
        global $OUTPUT;

        $grade = (int) $value;
        $userid = (int) $row->learnerid;
        $competencyid = (int) $arguments['competencyid'];
        $courseid = (int) $arguments['courseid'];
        $items = (array) $arguments['items'];

        $context = [
            'url' => \core_competency\url::user_competency_in_course($userid, $competencyid, $courseid)->out(false),
            'title' => get_string('usercompetencysummary', 'report_competency'),
            'hooks' => !empty($arguments['hooks']),
            'userid' => $userid,
            'competencyid' => $competencyid,
            'courseid' => $courseid,
            'rated' => $grade > 0,
            'tone' => '',
            'icon' => 'circle-minus',
            'label' => get_string('competency_classreport_notrated', 'theme_dimensions'),
            'state' => '',
        ];
        if ($grade > 0) {
            $proficient = (int) $row->proficiency === 1;
            $context['tone'] = $proficient ? 'proficient' : 'notproficient';
            $context['icon'] = $proficient ? 'circle-check' : 'circle-exclamation';
            $context['label'] = $items[$grade] ?? (string) $grade;
            $context['state'] = $proficient
                ? get_string('competency_classreport_proficient', 'theme_dimensions')
                : get_string('competency_notproficient', 'theme_dimensions');
        }

        return $OUTPUT->render_from_template(self::CELL_TEMPLATE, $context);
    }
}
