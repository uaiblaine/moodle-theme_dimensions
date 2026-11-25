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
 * Theme Dimensions - The columns a reader chose on the class report of one course
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

/**
 * A reader's columns and widths on the class report of one course, in one user preference per course.
 *
 * The columns of the class report are the course's competencies, so the choice is the course's: the preference
 * `theme_dimensions_classreport_<courseid>` holds, as compact JSON, the chosen competency ids in the course's order (`c`,
 * at most {@see classreport::MAX_COLUMNS}, null for no choice) and the widths by column key (`w`, at most {@see MAX_WIDTHS},
 * each clamped to {@see MIN_WIDTH}..{@see MAX_WIDTH} pixels). It holds ids, never column definitions: the report reads
 * it in initialise() ({@see \theme_dimensions\reportbuilder\local\systemreports\class_ratings}) and shows only the
 * catalogue's competencies it names ({@see classreport::chosen()}).
 *
 * Cleaned on write and again on every read: the stored value is untrusted (core's preference table is writable by
 * other code), so a read keeps only integer ids of competencies of THAT course at read time, and a value of any other
 * shape reads as no choice. The one writer is the theme's web service (`set_class_report_preference`); core's
 * preference services may not write it ({@see preference_editable()}). The row goes with the course
 * ({@see delete_for_course()}, from the course_deleted observer), with the account (core deletes a deleted user's
 * preferences) and with the theme ({@see delete_all()}, db/uninstall.php). A user preference is the database's, so
 * every node reads the same.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classreportpreference {
    /** @var string The preference's name before the course id. */
    public const PREFIX = 'theme_dimensions_classreport_';

    /** @var string The names of every course's preference, as core's declaration of a preference by pattern reads it. */
    public const PATTERN = '/^theme_dimensions_classreport_\d+$/';

    /** @var int The most column keys the preference's service reads in one request: more is refused before cleaning. */
    public const MAX_POSTED_COLUMNS = 200;

    /** @var int The most widths the preference's service reads in one request: more is refused before cleaning. */
    public const MAX_POSTED_WIDTHS = 100;

    /** @var int The most widths kept: the learner, the identity fields and the shown competencies hold fewer. */
    public const MAX_WIDTHS = 40;

    /** @var int The narrowest width kept, in pixels; a smaller one is raised to it. */
    public const MIN_WIDTH = 48;

    /** @var int The widest width kept, in pixels; a larger one is lowered to it. */
    public const MAX_WIDTH = 1600;

    /**
     * @var int The longest value stored, in characters: the 1333 core's user_preferences.value held as a char column
     *     (a text column on 5.3), kept as the bound so one reader's choice stays small whatever a client posts.
     */
    public const MAX_LENGTH = 1333;

    /**
     * The preference's name for a course.
     *
     * @param int $courseid The course.
     * @return string
     */
    public static function name(int $courseid): string {
        return self::PREFIX . $courseid;
    }

    /**
     * Whether a string has the shape of a column key of the class report: `learner`, `identity:<field>` or
     * `comp:<competency id>`. The shape only: whether it names a column is the report's call.
     *
     * @param string $key The key.
     * @return bool
     */
    public static function valid_key(string $key): bool {
        // Anchored at the string's start and end: a "$" would also match before a trailing newline.
        return preg_match('/\A(?:learner|identity:[a-z0-9_]{1,40}|comp:[1-9][0-9]{0,17})\z/', $key) === 1;
    }

    /**
     * The competency id a column key names, or null for a key that names none.
     *
     * @param mixed $key The key.
     * @return int|null
     */
    public static function competency_of($key): ?int {
        if (!is_string($key) || preg_match('/\Acomp:([1-9][0-9]{0,17})\z/', $key, $match) !== 1) {
            return null;
        }
        $id = filter_var($match[1], FILTER_VALIDATE_INT);

        return $id === false ? null : $id;
    }

    /**
     * A reader's choice on one course's class report, as stored, cleaned and re-validated against the course now.
     *
     * The ids are kept only while they are competencies of that course; the widths keep their shape alone (they are
     * presentation and name no column). Reading a stored choice costs one statement, the course's competency ids;
     * no choice costs none.
     *
     * @param int $courseid The course.
     * @param int|null $userid The reader; the current user when null.
     * @return array 'columns' => competency ids in the course's order, or null for the default; 'widths' => key =>
     *     pixels.
     */
    public static function read(int $courseid, ?int $userid = null): array {
        global $USER;

        $raw = get_user_preferences(self::name($courseid), null, $userid ?? (int) $USER->id);
        $stored = is_string($raw) ? json_decode($raw, true) : null;
        $clean = self::clean(is_array($stored) ? $stored : []);
        if ($clean['columns'] !== null) {
            $clean['columns'] = self::of_course($courseid, $clean['columns']);
        }

        return $clean;
    }

    /**
     * Store a reader's choice on one course's class report.
     *
     * The keys are column keys (`comp:<id>`); every other key, a forged id and an id of another course are dropped,
     * and a choice of which nothing is left is the default. The stored value never exceeds {@see MAX_LENGTH}: the
     * widths are dropped from the last before anything else, and the ids alone always fit.
     *
     * @param int $courseid The course.
     * @param array|null $keys The chosen column keys, in any order (stored in the course's), or null for the default
     *     columns.
     * @param array $widths Column key => pixels; empty for the default widths.
     * @param int|null $userid The reader; the current user when null.
     * @return array What {@see read()} now returns.
     */
    public static function write(int $courseid, ?array $keys, array $widths, ?int $userid = null): array {
        $columns = null;
        if ($keys !== null) {
            $columns = [];
            foreach ($keys as $key) {
                $id = self::competency_of($key);
                if ($id !== null) {
                    $columns[] = $id;
                }
            }
        }
        $clean = self::clean(['c' => $columns, 'w' => $widths]);
        $clean['columns'] = $clean['columns'] === null ? null : self::of_course($courseid, $clean['columns']);

        return self::store($courseid, $clean, $userid);
    }

    /**
     * Forget a reader's choice on one course's class report: the default columns and widths again, and no row left.
     *
     * @param int $courseid The course.
     * @param int|null $userid The reader; the current user when null.
     * @return array What {@see read()} now returns.
     */
    public static function use_default(int $courseid, ?int $userid = null): array {
        return self::store($courseid, ['columns' => null, 'widths' => []], $userid);
    }

    /**
     * The choice as the class report's web services return it.
     *
     * @param array $stored As {@see read()} returns it.
     * @return array usedefault, columns (column keys), widths (each key and width).
     */
    public static function export(array $stored): array {
        $widths = [];
        foreach ($stored['widths'] as $key => $width) {
            $widths[] = ['key' => (string) $key, 'width' => (int) $width];
        }

        return [
            'usedefault' => $stored['columns'] === null,
            'columns' => array_map(static fn(int $id): string => 'comp:' . $id, $stored['columns'] ?? []),
            'widths' => $widths,
        ];
    }

    /**
     * Whether core's preference services may write this preference: never.
     *
     * The preference is declared by pattern in lib.php so that core's services find a definition that refuses,
     * rather than none: the one writer is the theme's service, behind the class report's own access rule, and its
     * cleaning ({@see write()}). set_user_preference() asks no callback.
     *
     * @param \stdClass $user The user whose preference would be written.
     * @param string $name The preference's name.
     * @return bool False.
     */
    public static function preference_editable(\stdClass $user, string $name): bool {
        return false;
    }

    /**
     * Delete every reader's choice on one course: the course is gone.
     *
     * @param int $courseid The course.
     * @return void
     */
    public static function delete_for_course(int $courseid): void {
        global $DB;

        $DB->delete_records('user_preferences', ['name' => self::name($courseid)]);
    }

    /**
     * Delete every reader's choice on every course: the theme's uninstall.
     *
     * @return void
     */
    public static function delete_all(): void {
        global $DB;

        $DB->delete_records_select(
            'user_preferences',
            $DB->sql_like('name', ':name', true, false),
            ['name' => $DB->sql_like_escape(self::PREFIX) . '%']
        );
    }

    /**
     * One user's choices on every course, as stored: for the privacy export.
     *
     * @param int $userid The reader.
     * @return array Course id => the stored value.
     */
    public static function stored_for_user(int $userid): array {
        global $DB;

        $records = $DB->get_records_select(
            'user_preferences',
            'userid = :userid AND ' . $DB->sql_like('name', ':name', true, false),
            ['userid' => $userid, 'name' => $DB->sql_like_escape(self::PREFIX) . '%'],
            'name',
            'id, name, value'
        );
        $stored = [];
        foreach ($records as $record) {
            if (preg_match(self::PATTERN, (string) $record->name) === 1) {
                $stored[(int) substr((string) $record->name, strlen(self::PREFIX))] = (string) $record->value;
            }
        }

        return $stored;
    }

    /**
     * Settings of any shape, cleaned: positive integer ids once each, at most {@see classreport::MAX_COLUMNS}, the
     * first kept; widths under keys of a key's shape, in whole pixels within the bounds, at most {@see MAX_WIDTHS}.
     *
     * @param array $settings Raw settings: `c` (or `columns`) and `w` (or `widths`); anything else is dropped.
     * @return array 'columns' => ids or null; 'widths' => key => pixels.
     */
    public static function clean(array $settings): array {
        $rawcolumns = array_key_exists('c', $settings) ? $settings['c'] : ($settings['columns'] ?? null);
        $columns = null;
        if (is_array($rawcolumns)) {
            // A keyed set, and the loop stops at the cap: a long list costs no more than MAX_COLUMNS distinct ids.
            $seen = [];
            foreach ($rawcolumns as $id) {
                $id = is_int($id) ? $id : filter_var($id, FILTER_VALIDATE_INT);
                if ($id !== false && $id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    if (count($seen) >= classreport::MAX_COLUMNS) {
                        break;
                    }
                }
            }
            $columns = $seen === [] ? null : array_keys($seen);
        }

        $rawwidths = array_key_exists('w', $settings) ? $settings['w'] : ($settings['widths'] ?? []);
        $widths = [];
        foreach (is_array($rawwidths) ? $rawwidths : [] as $key => $width) {
            if (!is_string($key) || !self::valid_key($key) || !is_numeric($width)) {
                continue;
            }
            $widths[$key] = max(self::MIN_WIDTH, min(self::MAX_WIDTH, (int) round((float) $width)));
            if (count($widths) >= self::MAX_WIDTHS) {
                break;
            }
        }

        return ['columns' => $columns, 'widths' => $widths];
    }

    /**
     * The ids that are competencies of the course now, in the course's own order whatever order they were given in;
     * null when none is.
     *
     * @param int $courseid The course.
     * @param array $ids Competency ids.
     * @return array|null
     */
    private static function of_course(int $courseid, array $ids): ?array {
        global $DB;

        $wanted = array_flip(array_map('intval', $ids));
        $kept = [];
        // The order of classreport::catalogue(), so the stored list and the report's columns agree.
        $coursecomps = $DB->get_fieldset_select(
            'competency_coursecomp',
            'competencyid',
            'courseid = :courseid ORDER BY sortorder, id',
            ['courseid' => $courseid]
        );
        foreach ($coursecomps as $id) {
            if (isset($wanted[(int) $id])) {
                $kept[] = (int) $id;
            }
        }

        return $kept === [] ? null : $kept;
    }

    /**
     * Store a cleaned choice: a choice of all defaults removes the preference, so no row is left behind; a value
     * too long for core's column loses its widths, from the last, until it fits.
     *
     * @param int $courseid The course.
     * @param array $clean As {@see clean()} returns it, the ids already the course's.
     * @param int|null $userid The reader; the current user when null.
     * @return array The choice stored.
     */
    private static function store(int $courseid, array $clean, ?int $userid): array {
        global $USER;

        $userid ??= (int) $USER->id;
        if ($clean['columns'] === null && $clean['widths'] === []) {
            unset_user_preference(self::name($courseid), $userid);
            return $clean;
        }
        $encode = static fn(array $choice): string => json_encode([
            'c' => $choice['columns'],
            // A JSON object whatever the keys: an empty list and an empty map would decode alike otherwise.
            'w' => (object) $choice['widths'],
        ]);
        while (\core_text::strlen($encode($clean)) > self::MAX_LENGTH && $clean['widths'] !== []) {
            array_pop($clean['widths']);
        }
        set_user_preference(self::name($courseid), $encode($clean), $userid);

        return $clean;
    }
}
