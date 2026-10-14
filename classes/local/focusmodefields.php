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
 * Theme Dimensions - Focus mode course custom field
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use core_course\customfield\course_handler;
use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\data_controller;
use core_customfield\field_controller;

/**
 * Provisions the per course switch of focus mode, and moves its default for new courses.
 *
 * The switch is a core course custom field of type checkbox rather than a table of
 * this theme's own, so it backs up, restores and bulk-uploads with no plugin code -
 * tool_uploadcourse writes it through its customfield_<shortname> column. Modelled
 * on {@see hotsitefields}: idempotent, serialised under a core Lock API lock,
 * guarded on the customfield tables existing, called from db/install.php.
 *
 * The field is created once and never deleted: it holds what course authors decided
 * about their own courses.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class focusmodefields {
    /** @var string Component name, used for config reads and the lock namespace. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string Plugin config key remembering the provisioned category id. */
    public const CATEGORYID_CONFIG = 'focusmodecfcategoryid';

    /** @var string Shortname of the per course focus mode checkbox. */
    public const SHORTNAME = 'dimensions_focusmode';

    /**
     * @var int Above this many courses without an answer, the write moves into an adhoc task.
     *
     * Changing the default for new courses writes one row per course which has never
     * saved its settings form, so on a large site the administrator's own request would
     * carry thousands of writes. The synchronous path stays for the sites where it is
     * imperceptible.
     */
    public const MATERIALISE_INLINE_LIMIT = 500;

    /** @var bool Whether the request cache below has been populated. */
    private static bool $fieldloaded = false;

    /** @var field_controller|null Request cache of the resolved field (null = missing). */
    private static ?field_controller $field = null;

    /**
     * The full definition of every field this class provisions.
     *
     * Single source of truth for provisioning and for the tests. The entry carries the
     * customfield type, the lang string key of its name (the description uses the same
     * key with a '_desc' suffix) and the type-specific configdata.
     *
     * The field is invisible on course listings: it changes how an activity page is
     * drawn, which is not a fact about the course anybody reading a course card needs.
     *
     * @return array Map of shortname => ['type' => string, 'stringkey' => string, 'config' => array].
     */
    public static function definitions(): array {
        return [
            self::SHORTNAME => [
                'type' => 'checkbox',
                'stringkey' => 'focusmodefield_enabled',
                'config' => [
                    'checkbydefault' => 0,
                    'required' => 0,
                    'locked' => 0,
                    'visibility' => course_handler::NOTVISIBLE,
                ],
            ],
        ];
    }

    /**
     * Get the focus mode field, or null when it is missing or has the wrong type.
     *
     * A field whose shortname matches but whose type differs (an administrator created
     * it by hand before this theme was installed) is treated as unavailable rather than
     * adopted; {@see load_field_cache()} reports it through debugging().
     *
     * @return field_controller|null The field controller.
     */
    public static function get_field(): ?field_controller {
        if (!self::$fieldloaded) {
            self::load_field_cache();
        }
        return self::$field;
    }

    /**
     * Reset the request-level field cache (used by tests and after provisioning).
     *
     * @return void
     */
    public static function reset_field_cache(): void {
        self::$fieldloaded = false;
        self::$field = null;
    }

    /**
     * Ensure the focus mode course custom field exists, creating it when missing.
     *
     * Invoked from db/install.php, and by any upgrade step that introduces
     * the field. Guarded on the customfield tables existing, not on during_initial_install() -
     * that stays true for the whole of a fresh site install, which is exactly when
     * db/install.php runs (the same reasoning as {@see hotsitefields::ensure_fields()}).
     *
     * @return void
     */
    public static function ensure_fields(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('customfield_field')) {
            return;
        }

        // Fast path: the field resolves (request-cached).
        if (self::get_field()) {
            return;
        }

        /* Provisioning is check-then-act and the customfield tables have no unique
           indexes, so concurrent first requests must be serialised. */
        $lock = self::get_lock();
        if (!$lock) {
            // Another request is provisioning right now; nothing to do here.
            return;
        }
        try {
            // Re-read fresh: the lock winner may have created the field already.
            self::reset_field_cache();
            if (!self::get_field()) {
                self::create_field();
            }
        } finally {
            self::reset_field_cache();
            $lock->release();
        }
    }

    /**
     * Move the default for new courses without changing any existing course's answer.
     *
     * The checkbox's own checkbydefault does two jobs: it is what a new course's form
     * shows pre-ticked and what every course WITHOUT a data row reads. Moving it alone
     * would therefore silently switch focus mode on - or off - in every course whose
     * settings form has never been saved since the field was created, which is the one
     * failure this setting exists to avoid. So the previous answer is written into those
     * courses first, and only then does the default move.
     *
     * Above $inlinelimit row-less courses the write moves into an adhoc task, which
     * finishes the sequence on the next cron run.
     *
     * @param bool $new The new default for courses created from now on.
     * @param int $inlinelimit Row-less courses above which the write is queued instead.
     * @return void
     */
    public static function sync_default(bool $new, int $inlinelimit = self::MATERIALISE_INLINE_LIMIT): void {
        global $CFG, $DB;

        // This runs as a setting's updated callback, which must do nothing during install or upgrade.
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }
        if (!$DB->get_manager()->table_exists('customfield_field')) {
            return;
        }
        $field = self::get_field();
        if (!$field) {
            return;
        }

        $old = $field->get_configdata_property('checkbydefault') ? 1 : 0;
        if ($old === (int) $new) {
            // The default is already where the setting says; nothing to materialise.
            return;
        }

        $lock = self::get_lock();
        if (!$lock) {
            return;
        }
        try {
            $fieldid = (int) $field->get('id');
            if (self::count_courses_without_data($fieldid) > $inlinelimit) {
                $task = new \theme_dimensions\task\materialise_focusmode_default();
                $task->set_custom_data(['old' => $old, 'new' => (int) $new]);
                \core\task\manager::queue_adhoc_task($task, true);
                return;
            }
            self::materialise_default($old);
            self::set_default($new);
        } finally {
            $lock->release();
        }
    }

    /**
     * Write one answer into every course which has never stored one.
     *
     * Through core's own data controller, so the row shape is core's: the course
     * settings form, backup, restore and tool_uploadcourse all read it without knowing
     * this code exists. The site course is skipped - it has no ordinary settings form
     * to host the switch, and focus mode never applies there.
     *
     * @param int $old The answer to write, as the checkbox stores it (0 or 1).
     * @return int The number of rows written.
     */
    public static function materialise_default(int $old): int {
        $field = self::get_field();
        if (!$field) {
            return 0;
        }
        $fieldid = (int) $field->get('id');

        $written = 0;
        foreach (self::courses_without_data($fieldid) as $courseid) {
            $context = \core\context\course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                // The course went away while this loop was running.
                continue;
            }
            $data = data_controller::create(0, (object) [
                'instanceid' => $courseid,
                'fieldid' => $fieldid,
            ], $field);
            $data->set('intvalue', $old);
            $data->set('value', (string) $old);
            $data->set('contextid', $context->id);
            $data->save();
            $written++;
        }
        return $written;
    }

    /**
     * Write the default for new courses into the field's configuration.
     *
     * The last step of {@see sync_default()}, and the last thing the adhoc task does on the
     * deferred path. Goes through the customfield api rather than the handler so a field
     * provisioned earlier in this same request cannot fail the handler's own memoised
     * lookup; the api fires the field_updated event either way.
     *
     * @param bool $new The new default for courses created from now on.
     * @return void
     */
    public static function set_default(bool $new): void {
        $field = self::get_field();
        if (!$field) {
            return;
        }
        $config = (array) $field->get('configdata');
        $config['checkbydefault'] = (int) $new;
        api::save_field_configuration($field, (object) ['configdata' => $config]);
        self::reset_field_cache();
    }

    /**
     * The provisioning lock, shared by the creation and the default-moving paths.
     *
     * @return \core\lock\lock|false The held lock, or false when somebody else holds it.
     */
    private static function get_lock() {
        $lockfactory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        return $lockfactory->get_lock('focusmodefields', 10);
    }

    /**
     * The ids of the courses which have no data row for the field.
     *
     * @param int $fieldid The field id.
     * @return array The course ids, as integers.
     */
    private static function courses_without_data(int $fieldid): array {
        global $DB;

        $sql = "SELECT c.id " . self::courses_without_data_sql();
        $ids = $DB->get_fieldset_sql($sql, ['siteid' => SITEID, 'fieldid' => $fieldid]);
        return array_map('intval', $ids);
    }

    /**
     * How many courses have no data row for the field.
     *
     * @param int $fieldid The field id.
     * @return int The number of courses.
     */
    private static function count_courses_without_data(int $fieldid): int {
        global $DB;

        $sql = "SELECT COUNT(1) " . self::courses_without_data_sql();
        return (int) $DB->count_records_sql($sql, ['siteid' => SITEID, 'fieldid' => $fieldid]);
    }

    /**
     * The FROM and WHERE of the row-less course query, shared by the count and the list.
     *
     * One spelling, so the number which decides between the inline and the queued path
     * can never describe a different set of courses from the one actually written to.
     *
     * @return string The SQL fragment, with the siteid and fieldid placeholders.
     */
    private static function courses_without_data_sql(): string {
        return "FROM {course} c
                 WHERE c.id <> :siteid
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {customfield_data} d
                        WHERE d.fieldid = :fieldid
                          AND d.instanceid = c.id
                   )";
    }

    /**
     * Resolve the field in one pass over the course field area.
     *
     * Reads through {@see api::get_categories_with_fields()} directly instead of the
     * course handler instance: the handler memoises its category list per request, which
     * would hide a field another request provisioned while we waited on the lock. The api
     * call is plain SQL every time, and scoped to component core_course it never returns
     * shared categories.
     *
     * @return void
     */
    private static function load_field_cache(): void {
        $definition = self::definitions()[self::SHORTNAME];
        self::$field = null;
        self::$fieldloaded = true;

        foreach (api::get_categories_with_fields('core_course', 'course', 0) as $category) {
            foreach ($category->get_fields() as $field) {
                if ($field->get('shortname') !== self::SHORTNAME) {
                    continue;
                }
                if ($field->get('type') !== $definition['type']) {
                    debugging(
                        self::COMPONENT . ": field '" . self::SHORTNAME . "' exists with unexpected type '" .
                            $field->get('type') . "', expected '" . $definition['type'] . "' - not used.",
                        DEBUG_DEVELOPER
                    );
                    continue;
                }
                self::$field = $field;
            }
        }
    }

    /**
     * Create the field inside the theme's category, creating the category first if needed.
     *
     * The display name and description are resolved through get_string() at creation time
     * and stored once - custom field names are not translatable after creation, exactly
     * like the other fields this theme provisions.
     *
     * @return void
     */
    private static function create_field(): void {
        $definition = self::definitions()[self::SHORTNAME];
        $handler = course_handler::create();

        if (!api::is_shortname_unique($handler, self::SHORTNAME, 0)) {
            /* A field with this shortname exists somewhere the handler can see (possibly a
               shared field, possibly the wrong type). Creating another one would collide in
               the course edit form element names. */
            debugging(
                self::COMPONENT . ": shortname '" . self::SHORTNAME . "' already taken - field not created.",
                DEBUG_DEVELOPER
            );
            return;
        }

        $category = self::get_or_create_category();
        if (!$category) {
            return;
        }

        $config = $definition['config'] + [
            'defaultvalue' => '',
            'defaultvalueformat' => FORMAT_PLAIN,
            'uniquevalues' => 0,
        ];

        $record = (object) [
            'type' => $definition['type'],
            'shortname' => self::SHORTNAME,
            'name' => get_string($definition['stringkey'], self::COMPONENT),
            'description' => get_string($definition['stringkey'] . '_desc', self::COMPONENT),
            'descriptionformat' => FORMAT_HTML,
            'configdata' => json_encode($config),
        ];

        $field = field_controller::create(0, (object) ['type' => $definition['type']], $category);
        $handler->save_field_configuration($field, $record);
    }

    /**
     * Resolve the theme's focus mode category, creating it when missing.
     *
     * The created category id is remembered in plugin config so renames by the
     * administrator do not break the lookup; a stale id (category deleted) falls back to
     * creating a fresh category.
     *
     * @return category_controller|null The category controller.
     */
    private static function get_or_create_category(): ?category_controller {
        $categories = api::get_categories_with_fields('core_course', 'course', 0);

        $storedid = (int) get_config(self::COMPONENT, self::CATEGORYID_CONFIG);
        if ($storedid) {
            foreach ($categories as $category) {
                if ((int) $category->get('id') === $storedid) {
                    return $category;
                }
            }
        }

        $handler = course_handler::create();
        $categoryid = $handler->create_category(get_string('focusmodefield_category', self::COMPONENT));
        set_config(self::CATEGORYID_CONFIG, $categoryid, self::COMPONENT);
        return category_controller::create($categoryid);
    }
}
