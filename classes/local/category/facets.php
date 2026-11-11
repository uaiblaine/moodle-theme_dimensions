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
 * Theme Dimensions - The custom field facets of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;
use core_customfield\field_controller;

/**
 * The facet groups: up to four select-type course custom fields, their values over a subtree, and their counts.
 *
 * One statement, then PHP. The values of the allowlisted fields for every
 * course under the admitted categories are read once - rows O(N), statements
 * flat, driven by the category list and never by course ids - and everything
 * else is arithmetic over that map: the filter a request carries is applied to
 * the post-gate id list, and each group's counts are taken over the
 * post-filter list with the other groups' filters applied, so every number
 * answers "how many would remain if I also picked this". A course the
 * population withheld is never in the id list, so it is never counted.
 *
 * A GROUP BY carrying the population's capped exception lists would print no
 * number past either cap; reading the values instead costs O(N) rows the
 * listing already pays for its ids, has no cap, and makes the filter and the
 * counts one computation that cannot disagree. The grouped statement is used
 * only by {@see groups_sql()}, for the page cut in SQL.
 *
 * Only select fields are facets, and only their 1-based option index is
 * stored (in intvalue, which the fieldid-intvalue index covers); the label is
 * read back from the field's configuration at render time, as
 * {@see \theme_dimensions\local\landingfields::option_label()}
 * already does, because an admin may have edited the option texts.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class facets {
    /** @var int The most groups the toolbar offers, whatever the setting lists. */
    public const MAX_FIELDS = 4;

    /** @var string The setting naming the fields, comma-separated shortnames in display order. */
    public const SETTING = 'categoryfilterfields';

    /**
     * @var array|null The last answer of {@see fields()}: ['setting' => string, 'all' => the fields read, 'ids' => int[]].
     *
     * One page asks for the filter fields from several places (the renderer, the listing, the population, the
     * toolbar, the remembered state), and the answer depends on the setting and on the course fields alone.
     * The memo is keyed on both: the raw setting, and the very list of field controllers the course handler
     * hands back (compared by identity, which costs no read of a field). The handler builds that list anew
     * whenever its fields change through it and is itself rebuilt for each PHPUnit test, so a changed setting,
     * a field added, edited or deleted, or the next test's fields under the same ids, are each a miss.
     */
    private static ?array $memo = null;

    /**
     * The allowlisted fields, in the configured order, keyed by field id.
     *
     * Reads the setting as shortnames, so a field deleted and recreated keeps
     * its place, resolves them against the live course custom fields, keeps
     * the select-type ones only and caps the list at {@see MAX_FIELDS}. A
     * shortname the site no longer has is skipped in silence: the toolbar
     * simply offers one group fewer.
     *
     * @return field_controller[] fieldid => field, at most MAX_FIELDS entries.
     */
    public static function fields(): array {
        $setting = (string) get_config('theme_dimensions', self::SETTING);
        $wanted = array_values(array_filter(array_map('trim', explode(',', $setting))));
        if (!$wanted) {
            return [];
        }
        $all = course_handler::create()->get_fields();
        if (self::$memo !== null && self::$memo['setting'] === $setting && self::$memo['all'] === $all) {
            $fields = [];
            foreach (self::$memo['ids'] as $fieldid) {
                $fields[$fieldid] = $all[$fieldid];
            }
            return $fields;
        }
        $byshortname = [];
        foreach ($all as $field) {
            if ($field->get('type') === 'select') {
                $byshortname[$field->get('shortname')] = $field;
            }
        }
        $fields = [];
        foreach ($wanted as $shortname) {
            if (isset($byshortname[$shortname])) {
                $fields[(int) $byshortname[$shortname]->get('id')] = $byshortname[$shortname];
            }
            if (count($fields) >= self::MAX_FIELDS) {
                break;
            }
        }
        self::$memo = ['setting' => $setting, 'all' => $all, 'ids' => array_keys($fields)];
        return $fields;
    }

    /**
     * Forget the memo of {@see fields()}; {@see \theme_dimensions\local\landingfields::reset_field_cache()} calls it.
     *
     * The key already follows the setting and the handler's list of fields; this is for a field changed in
     * place on a controller the handler still holds, which the identity of the list cannot see.
     *
     * @return void
     */
    public static function reset_fields(): void {
        self::$memo = null;
    }

    /**
     * The stored values of these fields for every course under these categories, in one statement.
     *
     * Rides the fieldid-intvalue index of {customfield_data} for the field
     * side and the category index of {course} for the population side. Rows
     * without a value (intvalue 0, which is what an unset select stores) are
     * not read: a course absent from a field's map has no option for it.
     *
     * @param int[] $fieldids The allowlisted field ids.
     * @param int[] $categoryids The admitted categories - a bounded list, never course ids.
     * @return array fieldid => [courseid => option value], every requested field present.
     */
    public static function values(array $fieldids, array $categoryids): array {
        global $DB;

        $fieldids = array_map('intval', $fieldids);
        $values = array_fill_keys($fieldids, []);
        if (!$fieldids || !$categoryids) {
            return $values;
        }
        [$fsql, $fparams] = $DB->get_in_or_equal($fieldids, SQL_PARAMS_NAMED, 'fld');
        [$csql, $cparams] = $DB->get_in_or_equal(array_map('intval', $categoryids), SQL_PARAMS_NAMED, 'cat');
        $rows = $DB->get_records_sql(
            "SELECT d.id, d.fieldid, d.instanceid, d.intvalue
               FROM {customfield_data} d
               JOIN {course} c ON c.id = d.instanceid
              WHERE d.fieldid {$fsql} AND d.intvalue > 0
                    AND d.component = :component AND d.area = :area AND d.itemid = 0
                    AND c.category {$csql}",
            $fparams + $cparams + ['component' => 'core_course', 'area' => 'course']
        );
        foreach ($rows as $row) {
            $values[(int) $row->fieldid][(int) $row->instanceid] = (int) $row->intvalue;
        }
        return $values;
    }

    /**
     * The courses, of those given, that carry every requested value.
     *
     * @param array $values The map from {@see values()}.
     * @param int[] $courseids The post-gate course ids, in order.
     * @param array $filters fieldid => option value, from the request.
     * @return int[] The ids that match every filter, in the order given.
     */
    public static function apply(array $values, array $courseids, array $filters): array {
        if (!$filters) {
            return $courseids;
        }
        return array_values(array_filter($courseids, static function (int $courseid) use ($values, $filters): bool {
            foreach ($filters as $fieldid => $value) {
                if (($values[$fieldid][$courseid] ?? 0) !== (int) $value) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * The facet groups with their counts over these courses.
     *
     * Each group is counted over the courses that pass every OTHER group's
     * filter, so its numbers say how many would remain if the viewer also
     * picked that option - and a group whose own option is picked still shows
     * the alternatives. An option nobody in the list holds is not offered.
     *
     * @param field_controller[] $fields The allowlist, fieldid => field.
     * @param array $values The map from {@see values()}.
     * @param int[] $courseids The post-gate course ids, every filter but the facets applied.
     * @param array $filters fieldid => option value, from the request.
     * @return array fieldid => ['name' => plain string, 'options' => [value => ['label' => plain string, 'count' => int]]].
     */
    public static function groups(array $fields, array $values, array $courseids, array $filters): array {
        $groups = [];
        foreach ($fields as $fieldid => $field) {
            $others = $filters;
            unset($others[$fieldid]);
            $remaining = self::apply($values, $courseids, $others);
            $histogram = [];
            foreach ($remaining as $courseid) {
                $value = $values[$fieldid][$courseid] ?? 0;
                if ($value > 0) {
                    $histogram[$value] = ($histogram[$value] ?? 0) + 1;
                }
            }
            $options = [];
            foreach (self::option_labels($field) as $value => $label) {
                if (isset($histogram[$value])) {
                    $options[$value] = ['label' => $label, 'count' => $histogram[$value]];
                }
            }
            $groups[$fieldid] = [
                'name' => format_string($field->get('name'), true, ['context' => \context_system::instance(), 'escape' => false]),
                'options' => $options,
            ];
        }
        return $groups;
    }

    /**
     * The facet groups counted in SQL, for the page cut in SQL (the categorystatesort hatch).
     *
     * One grouped statement over the same predicate the page and its count
     * use, so the three numbers agree; every active filter applies to every
     * group, its own included, which is the one thing this shape gives up
     * against {@see groups()}. Rides the fieldid-intvalue index.
     *
     * @param field_controller[] $fields The allowlist, fieldid => field.
     * @param string $where The population predicate over the course alias c.
     * @param array $params Its parameters.
     * @param string $join The joins the predicate needs beside {course} c.
     * @return array The same shape as {@see groups()}.
     */
    public static function groups_sql(array $fields, string $where, array $params, string $join): array {
        global $DB;

        if (!$fields) {
            return [];
        }
        [$fsql, $fparams] = $DB->get_in_or_equal(array_map('intval', array_keys($fields)), SQL_PARAMS_NAMED, 'fct');
        $rows = $DB->get_records_sql(
            "SELECT " . $DB->sql_concat('d.fieldid', "'-'", 'd.intvalue') . " AS k, d.fieldid, d.intvalue, COUNT(1) AS n
               FROM {customfield_data} d
               JOIN {course} c ON c.id = d.instanceid
                    {$join}
              WHERE d.fieldid {$fsql} AND d.intvalue > 0
                    AND d.component = :fctcomponent AND d.area = :fctarea AND d.itemid = 0
                    AND ({$where})
           GROUP BY d.fieldid, d.intvalue",
            $params + $fparams + ['fctcomponent' => 'core_course', 'fctarea' => 'course']
        );
        $histograms = [];
        foreach ($rows as $row) {
            $histograms[(int) $row->fieldid][(int) $row->intvalue] = (int) $row->n;
        }
        $groups = [];
        foreach ($fields as $fieldid => $field) {
            $options = [];
            foreach (self::option_labels($field) as $value => $label) {
                if (isset($histograms[$fieldid][$value])) {
                    $options[$value] = ['label' => $label, 'count' => $histograms[$fieldid][$value]];
                }
            }
            $groups[$fieldid] = [
                'name' => format_string($field->get('name'), true, ['context' => \context_system::instance(), 'escape' => false]),
                'options' => $options,
            ];
        }
        return $groups;
    }

    /**
     * The option labels of a select field, keyed by the 1-based value the data table stores.
     *
     * @param field_controller $field A select field.
     * @return string[] value => plain label.
     */
    public static function option_labels(field_controller $field): array {
        // The same split as customfield_select's field_controller::get_options(), which prepends the empty choice.
        $options = preg_split("/\s*\n\s*/", trim((string) $field->get_configdata_property('options')), -1, PREG_SPLIT_NO_EMPTY);
        $labels = [];
        foreach ($options ?: [] as $index => $option) {
            $labels[$index + 1] = format_string($option, true, [
                'context' => \context_system::instance(),
                'escape' => false,
            ]);
        }
        return $labels;
    }
}
