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
 * Theme Dimensions - The listing's state a reader left each category in
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

/**
 * The organisation, the view and the filters a reader left each category's listing in.
 *
 * One user preference holds a JSON object keyed by category id, the least recently written entry
 * first. An entry has up to six keys, each optional: `s` the organisation (one of
 * {@see criteria::SORTS} but the empty one, which is stored as the grouped organisation it means),
 * `p` the grouped order (A-Z, stored as name, or absent for the newest first; {@see criteria::$psort}),
 * `v` the view (cards or list), `t` the state chip ({@see classifier::filters()}), `fav` the
 * favourites toggle (true, or absent for off) and `f` the custom field filters (field id => option
 * value, over the site's filter fields, {@see facets::fields()}). The search term, the period and the
 * tree's category are not remembered: what is remembered is the organisation, the view and the filters
 * (the custom fields, the state and the favourites), and a period such as "this week" is a different
 * period the next time. An entry written while the favourites were a value of the state chip
 * (`t` = favourites) is read as the toggle with no state ({@see entry()}).
 *
 * Remembered per category because a filter carried into another category makes that category look
 * empty: an entry is read only on the page of its own category.
 *
 * The value is validated on the way in and on the way out ({@see decode()}): every key and value is
 * checked against the vocabularies above, so a stale field (taken off the site's filter list, or an
 * option removed from its field), an id that is not one, or a hand-edited row is dropped rather than
 * handed to {@see criteria}. And it is bounded ({@see MAX_LENGTH}): at most {@see MAX_CATEGORIES}
 * categories, the least recently written going first, and then as many more as it takes to fit.
 *
 * Only a real account remembers anything ({@see can_remember()}): the guest account's preferences
 * live in its session alone and a visitor has none, so both read and write nothing here.
 *
 * Writes come from two places: the listing's client, through the theme's web service
 * ({@see \theme_dimensions\external\remember_category_state}), whenever the reader changes
 * the organisation, a filter or the view - "clear filters" included; and the page itself for a view
 * named in the address ({@see \theme_dimensions\output\category_listing::view()}), which is
 * how the toggle works without JavaScript. Nothing writes from an address otherwise: following a
 * link someone shared must not rewrite what the reader left the category in.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remembered {
    /** @var string The user preference holding the map. Not declared in lib.php, on purpose: see the web service. */
    public const PREFERENCE = 'theme_dimensions_categorystate';

    /** @var int The most categories remembered: the least recently written goes first. */
    public const MAX_CATEGORIES = 20;

    /**
     * @var int The longest value stored, in bytes. The theme's own bound: user_preferences.value has been a text column
     *   since Moodle 5.0 (it was char(1333) before). Set so that {@see MAX_CATEGORIES} entries carrying every key fit
     *   (about 2.5 KB), which leaves the count as the bound that applies; this one only stops a malformed map.
     */
    public const MAX_LENGTH = 4096;

    /** @var string Key of an entry: the organisation. */
    public const KEY_SORT = 's';

    /** @var string Key of an entry: the grouped order, stored as criteria::SORT_NAME and absent for the newest first. */
    public const KEY_PSORT = 'p';

    /** @var string Key of an entry: the view. */
    public const KEY_VIEW = 'v';

    /** @var string Key of an entry: the state chip. */
    public const KEY_STATE = 't';

    /** @var string Key of an entry: the favourites toggle, stored as true and absent when off. */
    public const KEY_FAVOURITES = 'fav';

    /** @var string Key of an entry: the custom field filters. */
    public const KEY_FIELDS = 'f';

    /** @var string[] The views an entry may hold: category_toolbar::VIEW_CARDS and VIEW_LIST. */
    private const VIEWS = ['cards', 'list'];

    /**
     * Whether the session's reader remembers anything: a real account, never the guest, never a visitor.
     *
     * @return bool
     */
    public static function can_remember(): bool {
        return isloggedin() && !isguestuser();
    }

    /**
     * What the session's reader left one category in, validated, or null for nothing.
     *
     * @param int $categoryid The category, 0 for the root, which remembers nothing.
     * @return array|null The entry: any of the KEY_* keys, each valid.
     */
    public static function for_category(int $categoryid): ?array {
        if ($categoryid <= 0 || !self::can_remember()) {
            return null;
        }
        return self::map()[$categoryid] ?? null;
    }

    /**
     * Every category a reader remembers, validated, the least recently written first.
     *
     * @param int|null $userid The reader, null for the session's.
     * @return array categoryid => entry.
     */
    public static function map(?int $userid = null): array {
        $raw = get_user_preferences(self::PREFERENCE, null, $userid);
        return $raw === null ? [] : self::decode((string) $raw);
    }

    /**
     * Change what the session's reader left one category in, and store it as the most recent.
     *
     * Each key of $changes replaces the entry's own and a null removes it; a key absent is kept as
     * it was. A value outside its vocabulary is refused and the entry keeps what it held for that
     * key (of a set of field filters, the valid ones are kept), and an entry left with nothing in it
     * is removed. The favourites toggle is switched off with a null, as every key is forgotten: its
     * one stored value is true. Nothing happens for a reader who remembers nothing, nor for the root.
     *
     * @param int $categoryid The category.
     * @param array $changes Any of the KEY_* keys: a value, or null to forget it.
     * @return void
     */
    public static function record(int $categoryid, array $changes): void {
        if ($categoryid <= 0 || !self::can_remember()) {
            return;
        }
        $map = self::map();
        $entry = $map[$categoryid] ?? [];
        $options = self::field_options();
        $keys = [self::KEY_SORT, self::KEY_PSORT, self::KEY_VIEW, self::KEY_STATE, self::KEY_FAVOURITES, self::KEY_FIELDS];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $changes)) {
                continue;
            }
            if ($changes[$key] === null) {
                unset($entry[$key]);
                continue;
            }
            // A value outside its vocabulary is refused, and the entry keeps what it held.
            $valid = self::entry([$key => $changes[$key]], $options);
            /* Read by its own key: the older spelling of the favourites as a state validates to the
               toggle's key and not to this one, so a write of it is refused like any value outside
               the vocabulary. Only a stored entry is read in that spelling. */
            if ($valid !== null && isset($valid[$key])) {
                $entry[$key] = $valid[$key];
            }
        }
        // Out and in again, so it goes to the end: the most recent is the last one dropped.
        unset($map[$categoryid]);
        $entry = self::entry($entry, $options);
        if ($entry !== null) {
            $map[$categoryid] = $entry;
        }
        set_user_preference(self::PREFERENCE, self::encode($map));
    }

    /**
     * The stored spelling of a map: canonical JSON, trimmed to fit.
     *
     * The keys of an entry are written in one order and the empty ones are left out, so an entry
     * that did not change is the same string and core's set_user_preference() writes nothing.
     * Past {@see MAX_CATEGORIES} the oldest entries go, and then as many more as it takes for the
     * string to fit {@see MAX_LENGTH}.
     *
     * @param array $map categoryid => entry, already valid, the least recently written first.
     * @return string The JSON object.
     */
    public static function encode(array $map): string {
        return self::json(self::bounded($map));
    }

    /**
     * A stored value read back: every entry checked against the vocabularies, the bounds applied.
     *
     * Anything that is not a JSON object of category ids to objects is no map at all; within one,
     * a key that is not a category id, an entry that is not an object, and every value outside its
     * vocabulary are dropped one by one.
     *
     * @param string $json The stored value.
     * @return array categoryid => entry, the least recently written first.
     */
    public static function decode(string $json): array {
        $raw = json_decode($json, true);
        if (!is_array($raw)) {
            return [];
        }
        $options = self::field_options();
        $map = [];
        foreach ($raw as $key => $value) {
            $categoryid = filter_var($key, FILTER_VALIDATE_INT);
            if ($categoryid === false || $categoryid <= 0 || !is_array($value)) {
                continue;
            }
            $entry = self::entry($value, $options);
            if ($entry !== null) {
                $map[$categoryid] = $entry;
            }
        }
        // A value stored past the bounds (by hand, or before they were lowered) reads as if trimmed.
        return self::bounded($map);
    }

    /**
     * One entry, keeping each valid key and nothing else.
     *
     * @param array $raw The entry as stored or as asked for.
     * @param array $options fieldid => how many options it has, for the site's filter fields.
     * @return array|null The valid entry, in the canonical order, or null when nothing in it is valid.
     */
    public static function entry(array $raw, array $options): ?array {
        $entry = [];
        $sort = $raw[self::KEY_SORT] ?? null;
        if (is_string($sort) && $sort !== '' && in_array($sort, criteria::SORTS, true)) {
            $entry[self::KEY_SORT] = $sort;
        }
        // The newest first is the grouped default and is stored as no key; A-Z is the one value kept.
        if (($raw[self::KEY_PSORT] ?? null) === criteria::SORT_NAME) {
            $entry[self::KEY_PSORT] = criteria::SORT_NAME;
        }
        $view = $raw[self::KEY_VIEW] ?? null;
        if (is_string($view) && in_array($view, self::VIEWS, true)) {
            $entry[self::KEY_VIEW] = $view;
        }
        $state = $raw[self::KEY_STATE] ?? null;
        if (is_string($state) && in_array($state, classifier::filters(), true)) {
            $entry[self::KEY_STATE] = $state;
        }
        /* The toggle is a boolean and only its true is stored: a string, a number or a false is no
           toggle at all. An entry from before the toggle held the favourites as its state chip, and is
           read as the toggle with no state, which is what that page showed. */
        if (($raw[self::KEY_FAVOURITES] ?? null) === true || $state === classifier::CHIP_FAVOURITES) {
            $entry[self::KEY_FAVOURITES] = true;
        }
        $fields = [];
        foreach ((array) ($raw[self::KEY_FIELDS] ?? []) as $fieldid => $value) {
            $fieldid = filter_var($fieldid, FILTER_VALIDATE_INT);
            $value = filter_var($value, FILTER_VALIDATE_INT);
            if ($fieldid === false || $value === false || !isset($options[$fieldid])) {
                continue;
            }
            if ($value >= 1 && $value <= $options[$fieldid]) {
                $fields[$fieldid] = $value;
            }
        }
        if ($fields) {
            ksort($fields);
            $entry[self::KEY_FIELDS] = $fields;
        }
        return $entry ?: null;
    }

    /**
     * How many options each of the site's filter fields has: the values a field filter may hold are 1 to that.
     *
     * @return array fieldid => count.
     */
    public static function field_options(): array {
        $options = [];
        foreach (facets::fields() as $fieldid => $field) {
            $options[(int) $fieldid] = count(facets::option_labels($field));
        }
        return $options;
    }

    /**
     * A map trimmed to the bounds: the least recently written entries go first.
     *
     * By count to {@see MAX_CATEGORIES}, then one at a time until its JSON fits {@see MAX_LENGTH}.
     * An entry is never cut short: a category is remembered whole or not at all.
     *
     * @param array $map categoryid => entry, the least recently written first.
     * @return array The map as it is stored.
     */
    private static function bounded(array $map): array {
        while (count($map) > self::MAX_CATEGORIES) {
            unset($map[array_key_first($map)]);
        }
        while ($map && strlen(self::json($map)) > self::MAX_LENGTH) {
            unset($map[array_key_first($map)]);
        }
        return $map;
    }

    /**
     * The JSON of a map, always an object, the fields of an entry always an object too.
     *
     * @param array $map categoryid => entry.
     * @return string
     */
    private static function json(array $map): string {
        return (string) json_encode((object) array_map(static function (array $entry): object {
            if (isset($entry[self::KEY_FIELDS])) {
                $entry[self::KEY_FIELDS] = (object) $entry[self::KEY_FIELDS];
            }
            return (object) $entry;
        }, $map), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
