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
 * Theme Dimensions - What identifies a person in the theme's own surfaces
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The text that identifies a person: the navbar's name, and the title and further lines of the profile layer.
 *
 * Which field each one shows is the administrator's choice among {@see fields()}: the full name as the site
 * composes it, five columns of the user record, and each custom profile field whose type keeps a short string
 * in the session's user record ({@see DATATYPES}). Every answer is plain text, already masked by the field's
 * display pattern ({@see identitypattern}); the template's double stash escapes it once. Nothing here renders.
 *
 * The rules:
 *
 * - The navbar's name and the title fall back to the full name when the chosen field is empty, unknown or one
 *   the person may not see; a further line is skipped instead, and so is one whose text is the title's.
 * - A custom profile field is shown only while core's own rule shows it to the viewer
 *   (profile_field_base::is_visible()): the session's user record carries every field, the hidden ones included.
 * - The methods are meant for the viewer's own record. Handed another person's, they show that person's full
 *   name and the custom fields core shows the viewer, and no column (username, email, ID number...).
 * - The values are read from the record handed in, which the caller takes from the session, so they are as old
 *   as the login: a column or field somebody else changes reaches the person at their next login, as core's own
 *   user menu name does.
 *
 * With the settings at their defaults (the full name twice, no line, no pattern) no cache and no table is read.
 * Otherwise the only read is {@see definitions()}, a shared application cache of the custom fields' definitions
 * that the profile field events empty for every node; a request static holds nothing but the parsed patterns.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class identity {
    /** @var string Component name, used for every config read, cache and string. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The setting naming the field beside the avatar in the navbar. */
    public const SETTING_NAVBAR = 'identitynavbar';

    /** @var string The setting naming the field that is the profile layer's title. */
    public const SETTING_TITLE = 'identitytitle';

    /** @var string The setting listing the fields shown as further lines, comma-separated as core stores checkboxes. */
    public const SETTING_LINES = 'identitylines';

    /** @var string The setting holding the display patterns, one "field=pattern" per line. */
    public const SETTING_PATTERNS = 'identitypatterns';

    /** @var string The field every setting defaults and falls back to: fullname(), in the site's format. */
    public const FULLNAME = 'fullname';

    /** @var string[] The columns of the user record that may identify a person, in the vocabulary's order. */
    public const COLUMNS = ['alternatename', 'firstname', 'username', 'email', 'idnumber'];

    /** @var string The prefix of a custom profile field's key, core's own spelling for identity fields. */
    public const PROFILE_PREFIX = 'profile_field_';

    /**
     * @var string[] The custom field types offered: those whose value in the user record is a short string. A text area
     *      is never in the record, and a checkbox and a date keep a flag and a timestamp.
     */
    public const DATATYPES = ['text', 'menu', 'social'];

    /** @var string The application cache of the custom fields' definitions, and its one key. */
    public const CACHE = 'identityfields';

    /** @var array The parsed patterns of the request, with the setting's value they were parsed from. */
    private static array $parsed = ['setting' => null, 'patterns' => []];

    /**
     * The name beside the avatar in the navbar.
     *
     * @param \stdClass $user The session's user record.
     * @return string Plain text, masked; never empty while the person has a name.
     */
    public static function navbar(\stdClass $user): string {
        return self::named(self::SETTING_NAVBAR, $user);
    }

    /**
     * The title beside the photo in the profile layer.
     *
     * @param \stdClass $user The session's user record.
     * @return string Plain text, masked; never empty while the person has a name.
     */
    public static function title(\stdClass $user): string {
        return self::named(self::SETTING_TITLE, $user);
    }

    /**
     * The further lines under the title, in the vocabulary's order.
     *
     * @param \stdClass $user The session's user record.
     * @return string[] Plain texts, masked; no empty one and none equal to the title.
     */
    public static function lines(\stdClass $user): array {
        $chosen = array_filter(explode(',', (string) get_config(self::COMPONENT, self::SETTING_LINES)), 'strlen');
        if (!$chosen) {
            return [];
        }

        $fields = array_intersect(array_merge([self::FULLNAME], self::COLUMNS), $chosen);
        // The definitions are read only when a custom field is ticked.
        if (array_filter($chosen, fn(string $field): bool => self::shortname($field) !== null)) {
            foreach (array_keys(self::definitions()) as $shortname) {
                if (in_array(self::PROFILE_PREFIX . $shortname, $chosen, true)) {
                    $fields[] = self::PROFILE_PREFIX . $shortname;
                }
            }
        }

        $title = self::title($user);
        $lines = [];
        foreach ($fields as $field) {
            $text = self::text($field, $user);
            if ($text === '') {
                continue;
            }
            if ($text === $title) {
                continue;
            }
            $lines[] = $text;
        }

        return $lines;
    }

    /**
     * The field vocabulary: every key a setting may store, in order.
     *
     * @return string[]
     */
    public static function fields(): array {
        $fields = array_merge([self::FULLNAME], self::COLUMNS);
        foreach (array_keys(self::definitions()) as $shortname) {
            $fields[] = self::PROFILE_PREFIX . $shortname;
        }

        return $fields;
    }

    /**
     * The vocabulary with its labels, for the settings' choices.
     *
     * Called by the settings only when a choice list is drawn or saved, never while settings.php loads. Core's
     * select setting escapes its options and its checkbox setting prints its labels as HTML, so each asks for
     * the spelling it needs.
     *
     * @param bool $escaped Whether the labels are escaped for HTML (the checkboxes) or plain (the selects).
     * @return array Field key => label.
     */
    public static function choices(bool $escaped = false): array {
        $choices = [
            self::FULLNAME => get_string('identityfield_fullname', self::COMPONENT),
            'alternatename' => get_string('alternatename'),
            'firstname' => get_string('firstname'),
            'username' => get_string('username'),
            'email' => get_string('email'),
            'idnumber' => get_string('idnumber'),
        ];
        $networks = null;
        foreach (self::definitions() as $shortname => $definition) {
            if ($definition['datatype'] === 'social') {
                // A social field's stored name is the network's key; core shows the network's own name.
                $networks ??= \profilefield_social\helper::get_networks();
                $name = $networks[$definition['name']];
            } else {
                $name = format_string($definition['name'], true, ['context' => \context_system::instance(), 'escape' => false]);
            }
            $choices[self::PROFILE_PREFIX . $shortname] = get_string('identityfield_profile', self::COMPONENT, $name);
        }

        return $escaped ? array_map('s', $choices) : $choices;
    }

    /**
     * The definitions of the custom profile fields the vocabulary offers, by shortname, in core's display order.
     *
     * Shared by every node (an application cache) and emptied by {@see definitions_changed()}. A miss costs one
     * statement. Each entry carries what core's field class needs to answer is_visible() and nothing else: no
     * description, no default, and a menu's options (param1) dropped, because the user record already holds the
     * chosen option. A social field whose network core does not know is left out.
     *
     * @return array Shortname => the field's row as an array.
     */
    public static function definitions(): array {
        global $DB;

        $cache = \cache::make(self::COMPONENT, self::CACHE);
        $definitions = $cache->get(self::CACHE);
        if (is_array($definitions)) {
            return $definitions;
        }

        [$insql, $params] = $DB->get_in_or_equal(self::DATATYPES, SQL_PARAMS_NAMED, 'type');
        $sql = "SELECT f.id, f.shortname, f.name, f.datatype, f.categoryid, f.sortorder, f.required, f.locked,
                       f.visible, f.forceunique, f.signup, f.param1
                  FROM {user_info_field} f
             LEFT JOIN {user_info_category} c ON c.id = f.categoryid
                 WHERE f.datatype $insql
              ORDER BY c.sortorder ASC, f.sortorder ASC, f.id ASC";
        $definitions = [];
        $networks = null;
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $definition = (array) $row;
            if ($definition['datatype'] === 'social') {
                // Core's social field reads its network's name by this key without checking it: a row holding
                // another name (written past core's form) would raise a warning wherever the field is asked.
                $networks ??= \profilefield_social\helper::get_networks();
                if (!array_key_exists((string) $definition['name'], $networks)) {
                    continue;
                }
            } else {
                $definition['param1'] = null;
            }
            $definitions[$definition['shortname']] = $definition;
        }
        $cache->set(self::CACHE, $definitions);

        return $definitions;
    }

    /**
     * Forget the cached definitions: a profile field or one of their categories was created, changed or deleted.
     *
     * @param \core\event\base $event The event, unread: the cache is one entry.
     * @return void
     */
    public static function definitions_changed(\core\event\base $event): void {
        \cache::make(self::COMPONENT, self::CACHE)->delete(self::CACHE);
    }

    /**
     * The text of the field a setting names, or the full name when that text is empty.
     *
     * @param string $setting One of the two single-field settings.
     * @param \stdClass $user The session's user record.
     * @return string
     */
    private static function named(string $setting, \stdClass $user): string {
        $field = (string) get_config(self::COMPONENT, $setting);
        if ($field !== '' && $field !== self::FULLNAME) {
            $text = self::text($field, $user);
            if ($text !== '') {
                return $text;
            }
        }

        return self::text(self::FULLNAME, $user);
    }

    /**
     * One field's text for one person, masked by the field's pattern.
     *
     * @param string $field A key of the vocabulary; any other answers ''.
     * @param \stdClass $user The session's user record.
     * @return string Plain text, '' when there is nothing to show.
     */
    private static function text(string $field, \stdClass $user): string {
        if ($field === self::FULLNAME) {
            $value = fullname($user);
        } else if (in_array($field, self::COLUMNS, true)) {
            // A column has no visibility rule of its own here, so it is shown to its owner alone.
            $value = self::own($user) ? trim((string) ($user->{$field} ?? '')) : '';
        } else {
            $value = self::custom($field, $user);
        }
        if ($value === '') {
            return '';
        }

        $pattern = self::patterns()[$field] ?? null;

        return $pattern === null ? $value : identitypattern::mask($value, $pattern);
    }

    /**
     * A custom profile field's value from the user record, as plain text.
     *
     * @param string $field The field's key.
     * @param \stdClass $user The session's user record.
     * @return string '' for a field that does not exist, holds nothing or is not shown to the viewer.
     */
    private static function custom(string $field, \stdClass $user): string {
        $shortname = self::shortname($field);
        if ($shortname === null) {
            return '';
        }
        $definition = self::definitions()[$shortname] ?? null;
        if ($definition === null) {
            return '';
        }
        // A login path that set no profile on the record reads as an empty field.
        $raw = is_array($user->profile ?? null) ? ($user->profile[$shortname] ?? '') : '';
        if (!is_scalar($raw) || trim((string) $raw) === '') {
            return '';
        }
        if (!self::visible($definition, $user)) {
            return '';
        }

        return trim(format_string((string) $raw, true, ['context' => \context_system::instance(), 'escape' => false]));
    }

    /**
     * Whether core shows this custom field of this person to the current viewer.
     *
     * Core's own field object answers, built from the cached definition, which makes its constructor read no
     * table. For the viewer's own record the system context is handed in: core then reads no user context (a
     * statement on a cold context cache) and its answer is the same, because every branch that reads the context
     * is behind "not my own record", and a field visible to nobody is decided at the system context anyway.
     *
     * @param array $definition The field's entry of {@see definitions()}.
     * @param \stdClass $user The person the value belongs to.
     * @return bool
     */
    private static function visible(array $definition, \stdClass $user): bool {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        // Core's set_field() expects every column of the row; the ones nobody reads here are neutral.
        $record = (object) ($definition + [
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'defaultdata' => '',
            'defaultdataformat' => FORMAT_HTML,
            'param2' => null,
            'param3' => null,
            'param4' => null,
            'param5' => null,
        ]);
        $userid = (int) ($user->id ?? 0);
        $object = profile_get_user_field($definition['datatype'], (int) $definition['id'], $userid, $record);

        return $object->is_visible(self::own($user) ? \context_system::instance() : null);
    }

    /**
     * Whether a record is the current viewer's own.
     *
     * @param \stdClass $user A user record.
     * @return bool
     */
    private static function own(\stdClass $user): bool {
        global $USER;

        $userid = (int) ($user->id ?? 0);

        return $userid > 0 && $userid === (int) ($USER->id ?? 0);
    }

    /**
     * The shortname in a custom field's key.
     *
     * @param string $field A field key.
     * @return string|null Null for a key that is not a custom field's.
     */
    private static function shortname(string $field): ?string {
        if (!str_starts_with($field, self::PROFILE_PREFIX)) {
            return null;
        }
        $shortname = substr($field, strlen(self::PROFILE_PREFIX));

        return $shortname === '' ? null : $shortname;
    }

    /**
     * The display patterns in force, parsed once per value of the setting.
     *
     * @return array Field => pattern.
     */
    private static function patterns(): array {
        $setting = (string) get_config(self::COMPONENT, self::SETTING_PATTERNS);
        if (self::$parsed['setting'] !== $setting) {
            self::$parsed = ['setting' => $setting, 'patterns' => identitypattern::parse($setting)];
        }

        return self::$parsed['patterns'];
    }
}
