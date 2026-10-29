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
 * Theme Dimensions - One cohort per offered look, kept in step with the choice
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * The look cohorts: one per offered flavour, holding exactly the people who chose that look.
 *
 * Boost Union's smart menus have no extension point, but they can be restricted by cohort, and their
 * per-user cache is purged by Boost Union's own observer whenever a person joins or leaves a cohort.
 * So the choice is carried into a cohort and the administrator restricts a menu by it with Boost Union's
 * own form (docs/flavourchoice/PLAN.md, section 4.7).
 *
 * The preference is the record and membership is derived from it: {@see self::sync_user()} follows each
 * choice, and {@see self::reconcile()} puts membership back in step from the preferences, never the
 * reverse.
 *
 * The cohorts carry this component, which is the lock tool_dynamic_cohorts uses on its own: core's cohort
 * pages refuse to edit, delete or assign a stamped cohort. Core's cohort web services do not look at the
 * stamp, so a deletion can still come through them. That is why {@see self::cohort_deleted()} hides every
 * menu and item restricted by the cohort before Boost Union strips it from the rule: a rule left with no
 * cohort shows the menu to everyone. For the same reason a cohort is never deleted here. A flavour leaving
 * the list empties its cohort, and uninstalling releases the cohorts (the stamp cleared, members kept).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lookcohorts {
    /** @var string The component every look cohort is stamped with. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The idnumber prefix of a look cohort, followed by its flavour id. */
    public const IDNUMBER_PREFIX = 'theme_dimensions_look_';

    /** @var int Above this many members a cohort is emptied by an adhoc task rather than in the request. */
    public const EMPTY_INLINE = 500;

    /** @var string The setting recording the menus and items hidden after a look cohort was deleted. */
    public const HIDDEN_SETTING = 'lookcohorthidden';

    /**
     * The idnumber of a flavour's look cohort.
     *
     * @param int $flavourid The flavour.
     * @return string
     */
    public static function idnumber(int $flavourid): string {
        return self::IDNUMBER_PREFIX . $flavourid;
    }

    /**
     * The look cohorts this theme owns, by flavour id.
     *
     * Ownership is both facts at once: this component's stamp and the idnumber prefix. A cohort with the
     * prefix but another stamp, or none, is somebody else's and is never touched.
     *
     * @return array Flavour id => cohort record.
     */
    public static function cohorts(): array {
        global $DB;

        $like = $DB->sql_like('idnumber', ':prefix');
        $records = $DB->get_records_select(
            'cohort',
            "component = :component AND $like",
            ['component' => self::COMPONENT, 'prefix' => $DB->sql_like_escape(self::IDNUMBER_PREFIX) . '%'],
            'id ASC'
        );

        $cohorts = [];
        foreach ($records as $record) {
            $cohorts[(int) substr($record->idnumber, strlen(self::IDNUMBER_PREFIX))] = $record;
        }

        return $cohorts;
    }

    /**
     * Make sure every offered look has its cohort, and empty the cohorts of looks no longer offered.
     *
     * Check-then-act on a table with no unique index, so it runs under a core lock; a request that cannot
     * take it leaves the work to the next run (the reconcile task runs daily). A flavour whose idnumber a
     * cohort of another owner already holds is left alone and reported, never taken over.
     *
     * @return int[] The flavour ids whose cohort could not be made because another owner holds the idnumber.
     */
    public static function provision(): array {
        global $DB;

        $lock = self::lock('provision');
        if (!$lock) {
            return [];
        }

        $refused = [];
        try {
            $offered = flavourchoice::offered();
            $ours = self::cohorts();
            $context = \context_system::instance();
            foreach ($offered as $flavourid => $title) {
                $name = get_string('lookcohortname', self::COMPONENT, $title);
                if (isset($ours[$flavourid])) {
                    if ($ours[$flavourid]->name !== $name) {
                        $cohort = $ours[$flavourid];
                        $cohort->name = $name;
                        cohort_update_cohort($cohort);
                    }
                    continue;
                }
                if ($DB->record_exists('cohort', ['idnumber' => self::idnumber($flavourid)])) {
                    $refused[] = $flavourid;
                    continue;
                }
                cohort_add_cohort((object) [
                    'contextid' => $context->id,
                    'name' => $name,
                    'idnumber' => self::idnumber($flavourid),
                    'description' => get_string('lookcohortdescription', self::COMPONENT),
                    'descriptionformat' => FORMAT_HTML,
                    'component' => self::COMPONENT,
                    'visible' => 1,
                ]);
            }
            foreach ($ours as $flavourid => $cohort) {
                if (!array_key_exists($flavourid, $offered)) {
                    self::empty_cohort((int) $cohort->id);
                }
            }
        } finally {
            $lock->release();
        }

        return $refused;
    }

    /**
     * Put a person in the cohort of the look they chose, and out of every other look cohort.
     *
     * The choice is read here, from the stored preference, never taken from the caller: under the
     * per-person lock the record is what decides, so two tabs choosing at once, or a choice made while
     * the reconcile task runs, leave the person where their last choice put them. A look the setting no
     * longer offers, the guest account and a deleted or missing account belong to no look cohort. A
     * chosen look whose cohort does not exist yet is provisioned first.
     *
     * @param int $userid The person.
     * @return void
     */
    public static function sync_user(int $userid): void {
        global $DB;

        $ours = self::cohorts();
        $chosen = self::recorded_choice($userid);
        if ($chosen !== flavourchoice::SITE_DEFAULT && !isset($ours[$chosen])) {
            self::provision();
            $ours = self::cohorts();
        }

        $lock = self::lock('user' . $userid);
        if (!$lock) {
            return;
        }
        try {
            $chosen = self::recorded_choice($userid);
            foreach ($ours as $flavourid => $cohort) {
                $member = $DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $userid]);
                if ($flavourid === $chosen && !$member) {
                    cohort_add_member($cohort->id, $userid);
                } else if ($flavourid !== $chosen && $member) {
                    cohort_remove_member($cohort->id, $userid);
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * The look a person's stored preference names, if it is one they may hold now.
     *
     * Read from the table rather than through get_user_preferences(), which answers the current user
     * from the session's copy.
     *
     * @param int $userid The person.
     * @return int The flavour id, or {@see flavourchoice::SITE_DEFAULT}.
     */
    private static function recorded_choice(int $userid): int {
        global $CFG, $DB;

        $account = $DB->get_record('user', ['id' => $userid], 'id, deleted');
        if (!$account || $account->deleted || $userid === (int) $CFG->siteguest) {
            return flavourchoice::SITE_DEFAULT;
        }
        $stored = (int) $DB->get_field('user_preferences', 'value', ['userid' => $userid, 'name' => flavourchoice::PREFERENCE]);
        if (!array_key_exists($stored, flavourchoice::offered())) {
            return flavourchoice::SITE_DEFAULT;
        }

        return $stored;
    }

    /**
     * Remove every member of a look cohort, and keep the cohort.
     *
     * Member by member through core's own function, so each removal fires core's event and Boost Union
     * purges that person's menu cache. Above {@see self::EMPTY_INLINE} members the work goes to an adhoc
     * task instead of the request.
     *
     * @param int $cohortid The cohort.
     * @return void
     */
    public static function empty_cohort(int $cohortid): void {
        global $DB;

        if ($DB->count_records('cohort_members', ['cohortid' => $cohortid]) > self::EMPTY_INLINE) {
            $task = new \theme_dimensions\task\empty_look_cohort();
            $task->set_custom_data(['cohortid' => $cohortid]);
            \core\task\manager::queue_adhoc_task($task, true);
            return;
        }

        self::empty_members($cohortid);
    }

    /**
     * Remove every member of a cohort, member by member through core's function.
     *
     * @param int $cohortid The cohort.
     * @return void
     */
    public static function empty_members(int $cohortid): void {
        global $DB;

        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = :cohortid', ['cohortid' => $cohortid]);
        foreach ($members as $userid) {
            cohort_remove_member($cohortid, (int) $userid);
        }
    }

    /**
     * Put every look cohort's membership back in step with the preferences, and provision first.
     *
     * The members of a listed look's cohort are exactly the people whose stored choice is that look and
     * who may hold it ({@see self::sync_user()}). A membership added or removed behind the theme's back,
     * through core's web services, is undone here. Each person out of step is settled by sync_user(),
     * which reads their choice again under their lock, so a choice made while this runs is never undone.
     * The menus recorded as hidden after a deletion are pruned of those shown again.
     *
     * @return void
     */
    public static function reconcile(): void {
        global $DB;

        self::provision();
        $offered = flavourchoice::offered();
        $unsettled = [];
        foreach (self::cohorts() as $flavourid => $cohort) {
            if (!array_key_exists($flavourid, $offered)) {
                continue;
            }
            $chose = array_map('intval', $DB->get_fieldset_select(
                'user_preferences',
                'userid',
                'name = :name AND value = :value',
                ['name' => flavourchoice::PREFERENCE, 'value' => (string) $flavourid]
            ));
            $members = array_map('intval', $DB->get_fieldset_select(
                'cohort_members',
                'userid',
                'cohortid = :cohortid',
                ['cohortid' => $cohort->id]
            ));
            foreach (array_merge(array_diff($chose, $members), array_diff($members, $chose)) as $userid) {
                $unsettled[$userid] = true;
            }
        }
        foreach (array_keys($unsettled) as $userid) {
            self::sync_user((int) $userid);
        }

        self::prune_hidden();
    }

    /**
     * Release the look cohorts: clear the stamp and keep the cohorts with their members.
     *
     * For uninstall. Deleting them would show every menu restricted by them alone to everyone; released,
     * they become ordinary cohorts an administrator can manage, and every menu keeps its audience.
     *
     * @return void
     */
    public static function release(): void {
        foreach (self::cohorts() as $cohort) {
            $cohort->component = '';
            cohort_update_cohort($cohort);
        }
    }

    /**
     * When a look cohort is deleted, hide every smart menu and item its rule names, before Boost Union strips it.
     *
     * Registered at a priority above Boost Union's own observer of the same event, which removes the
     * deleted cohort from every rule: a rule left with no cohort shows the menu to everyone. Hidden first,
     * such a menu stays out of sight until an administrator re-points and shows it; the hidden menus and
     * items are recorded for the theme's recommendation, and the reconcile task makes the cohort again.
     * Any other cohort's deletion is left to Boost Union.
     *
     * @param \core\event\cohort_deleted $event The event.
     * @return void
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        global $DB;

        $cohort = $event->get_record_snapshot('cohort', $event->objectid);
        if (!$cohort || !self::owns($cohort)) {
            return;
        }

        $id = (int) $cohort->id;
        $hidden = self::hidden();
        foreach ($DB->get_records('theme_boost_union_menus') as $menu) {
            if ($menu->visible && self::names($menu->cohorts, $id)) {
                \theme_boost_union\smartmenu::instance($menu)->update_visible(false);
                $hidden['menus'][] = (int) $menu->id;
            }
        }
        foreach ($DB->get_records('theme_boost_union_menuitems') as $item) {
            if ($item->visible && self::names($item->cohorts, $id)) {
                \theme_boost_union\smartmenu_item::instance($item)->update_field('visible', false);
                $hidden['items'][] = (int) $item->id;
            }
        }
        self::record_hidden($hidden);
    }

    /**
     * The menus and items hidden after a look cohort was deleted and not shown again since.
     *
     * @return array menus and items: lists of ids.
     */
    public static function hidden(): array {
        $stored = json_decode((string) get_config(self::COMPONENT, self::HIDDEN_SETTING), true);

        return [
            'menus' => array_values(array_map('intval', (array) ($stored['menus'] ?? []))),
            'items' => array_values(array_map('intval', (array) ($stored['items'] ?? []))),
        ];
    }

    /**
     * Drop from the record the menus and items that are shown again or gone.
     *
     * @return void
     */
    public static function prune_hidden(): void {
        global $DB;

        $hidden = self::hidden();
        $hidden['menus'] = array_values(array_filter(
            $hidden['menus'],
            fn($id) => $DB->record_exists('theme_boost_union_menus', ['id' => $id, 'visible' => 0])
        ));
        $hidden['items'] = array_values(array_filter(
            $hidden['items'],
            fn($id) => $DB->record_exists('theme_boost_union_menuitems', ['id' => $id, 'visible' => 0])
        ));
        self::record_hidden($hidden);
    }

    /**
     * Store the record of hidden menus and items.
     *
     * @param array $hidden menus and items: lists of ids.
     * @return void
     */
    private static function record_hidden(array $hidden): void {
        $hidden = [
            'menus' => array_values(array_unique($hidden['menus'])),
            'items' => array_values(array_unique($hidden['items'])),
        ];
        set_config(self::HIDDEN_SETTING, ($hidden['menus'] || $hidden['items']) ? json_encode($hidden) : '', self::COMPONENT);
    }

    /**
     * Whether a cohort is one of this theme's look cohorts: the stamp and the idnumber prefix, both.
     *
     * @param \stdClass $cohort A cohort record.
     * @return bool
     */
    private static function owns(\stdClass $cohort): bool {
        return $cohort->component === self::COMPONENT && str_starts_with((string) $cohort->idnumber, self::IDNUMBER_PREFIX);
    }

    /**
     * Whether a smart menu's or item's cohort rule names the cohort.
     *
     * Boost Union stores the rule as a JSON list of ids, as strings or as numbers.
     *
     * @param string|null $rule The stored rule.
     * @param int $cohortid The cohort.
     * @return bool
     */
    private static function names(?string $rule, int $cohortid): bool {
        $ids = json_decode((string) $rule, true);

        return is_array($ids) && in_array($cohortid, array_map('intval', $ids), true);
    }

    /**
     * A core lock of this theme's look cohorts.
     *
     * @param string $resource What the lock serialises.
     * @return \core\lock\lock|false The lock, or false when it could not be taken in time.
     */
    private static function lock(string $resource): \core\lock\lock|false {
        return \core\lock\lock_config::get_lock_factory('theme_dimensions_lookcohorts')->get_lock($resource, 10);
    }
}
