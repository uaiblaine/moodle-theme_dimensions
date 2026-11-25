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
 * Theme Dimensions - The report builder persistents of the theme's system reports
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_reportbuilder\local\report\base;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * Which of core's report persistents are the theme's, and their removal (the fleet's decision D146).
 *
 * The filter values a reader stores on a system report are rows core writes (`reportbuilder_user_filter`) under a
 * report persistent, and core's privacy provider covers custom reports only; so they are the theme's personal data.
 * Core's services build a system report from the source, context, component, area and item id a client sends, and
 * create its persistent before they ask can_view(), so a persistent is the theme's when it is a system report whose
 * SOURCE is one of the theme's system report classes, whatever component it was created under. The source is
 * compared without case and leading separator, as PHP resolves a class name. Mirrors local_evidencehub's
 * `reportpersistents`.
 *
 * A persistent is deleted through core's model, whose before_delete() removes the stored filter values, columns,
 * conditions, audiences and schedules hanging on it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reportpersistents {
    /** @var string[] The theme's system report classes. */
    public const SOURCES = [class_ratings::class];

    /**
     * The condition that a report persistent is the theme's.
     *
     * @param string $alias The alias of {reportbuilder_report} in the statement.
     * @param string $prefix The prefix of the parameters' names, unique in the statement.
     * @return array [the SQL condition, its named parameters].
     */
    public static function is_themes(string $alias, string $prefix = 'rp'): array {
        global $DB;

        $sources = [];
        foreach (self::SOURCES as $source) {
            $sources[] = strtolower($source);
            $sources[] = '\\' . strtolower($source);
        }
        [$insql, $params] = $DB->get_in_or_equal($sources, SQL_PARAMS_NAMED, $prefix . 'src');
        $params[$prefix . 'type'] = base::TYPE_SYSTEM_REPORT;

        return ["({$alias}.type = :{$prefix}type AND LOWER({$alias}.source) {$insql})", $params];
    }

    /**
     * One user's stored filter values of the theme's reports, oldest first, with the context of each report.
     *
     * @param int $userid The reader.
     * @return array Records: id, filterdata, timecreated, timemodified, contextid.
     */
    public static function user_filters(int $userid): array {
        global $DB;

        [$isthemes, $params] = self::is_themes('r');

        return array_values($DB->get_records_sql(
            "SELECT f.id, f.filterdata, f.timecreated, f.timemodified, r.contextid
               FROM {" . user_filter::TABLE . "} f
               JOIN {" . report::TABLE . "} r ON r.id = f.reportid AND {$isthemes}
              WHERE f.usercreated = :userid
           ORDER BY f.id",
            $params + ['userid' => $userid]
        ));
    }

    /**
     * Keep the oldest of the report's persistents of one context and delete the others, with what hangs on them.
     *
     * The table has no unique index, and core's factory creates a persistent when it finds none: two first views at
     * once can create two, after which core's finder (`report::get_record()`) answers with either and debugs. The
     * oldest (lowest id) is the one every reader keeps, with its stored filter values. No lock: the cut is
     * idempotent (a row already deleted by a racing reader is skipped), core's own services create persistents
     * through the factory without this method, so a lock here would not close the race, and the page must draw
     * whatever a lock would answer; the next read repairs a duplicate that appears after this one.
     *
     * @param int $contextid The report's context.
     * @param string $source The system report class.
     * @param string $component The persistent's component.
     * @param string $area The persistent's area.
     * @param int $itemid The persistent's item id.
     * @return void
     */
    public static function keep_oldest(int $contextid, string $source, string $component, string $area, int $itemid): void {
        global $DB;

        $ids = $DB->get_fieldset_select(
            report::TABLE,
            'id',
            'type = :type AND source = :source AND contextid = :contextid AND component = :component'
                . ' AND area = :area AND itemid = :itemid',
            [
                'type' => base::TYPE_SYSTEM_REPORT,
                'source' => $source,
                'contextid' => $contextid,
                'component' => $component,
                'area' => $area,
                'itemid' => $itemid,
            ],
            'id'
        );
        array_shift($ids);
        foreach ($ids as $id) {
            if ($extra = report::get_record(['id' => (int) $id])) {
                $extra->delete();
            }
        }
    }

    /**
     * Delete one user's stored filter values of the theme's reports, in core's table.
     *
     * The one cut shared by the privacy requests and the deletion of the account, after which the user context the
     * privacy finder reads is gone.
     *
     * @param int $userid The reader.
     * @return void
     */
    public static function delete_user_filters(int $userid): void {
        global $DB;

        [$isthemes, $params] = self::is_themes('r');
        $DB->delete_records_select(
            user_filter::TABLE,
            'usercreated = :userid AND reportid IN (SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isthemes})",
            $params + ['userid' => $userid]
        );
    }

    /**
     * Delete every report of the theme, with what hangs on each: the theme's uninstall.
     *
     * @return void
     */
    public static function delete_all(): void {
        global $DB;

        [$isthemes, $params] = self::is_themes('r');
        $ids = $DB->get_fieldset_sql('SELECT r.id FROM {' . report::TABLE . "} r WHERE {$isthemes}", $params);
        foreach ($ids as $id) {
            (new report((int) $id))->delete();
        }
    }

    /**
     * Delete the theme's report persistents of one context, through core's model so the stored filter values,
     * columns and conditions hanging on each go too.
     *
     * @param int $contextid The context the persistents were created in.
     * @return void
     */
    public static function delete_for_context(int $contextid): void {
        global $DB;

        [$isthemes, $params] = self::is_themes('r');
        $ids = $DB->get_fieldset_sql(
            'SELECT r.id FROM {' . report::TABLE . "} r WHERE r.contextid = :contextid AND {$isthemes}",
            $params + ['contextid' => $contextid]
        );
        foreach ($ids as $id) {
            if ($persistent = report::get_record(['id' => (int) $id])) {
                $persistent->delete();
            }
        }
    }

    /**
     * A course was deleted: the class report's persistents of its context, and so every reader's stored filter
     * values, go, and every reader's columns on it ({@see classreportpreference::delete_for_course()}). The context row
     * is gone by then, so the persistents are found by the context id the event carries. Runs after the deletion
     * commits; a failure here must not undo or hide the course's deletion, and one cut failing leaves the other done.
     *
     * @param \core\event\course_deleted $event The event.
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        try {
            self::delete_for_context((int) $event->contextid);
        } catch (\Throwable $exception) {
            debugging('theme_dimensions: the class report of a deleted course was not removed: '
                . $exception->getMessage(), DEBUG_DEVELOPER);
        }
        try {
            classreportpreference::delete_for_course((int) $event->objectid);
        } catch (\Throwable $exception) {
            debugging('theme_dimensions: the class report columns of a deleted course were not removed: '
                . $exception->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * A user was deleted: their stored filter values of the theme's reports go. The privacy finder reads their user
     * context, which is gone by then.
     *
     * @param \core\event\user_deleted $event The event.
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        self::delete_user_filters((int) $event->objectid);
    }
}
