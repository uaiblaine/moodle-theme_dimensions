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
 * Theme Dimensions - The public calendar's switch and its two keys.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * Whether the calendar page answers a visitor who is not logged in, and what of the page's state a visitor may name.
 *
 * The switch is read BESIDE {@see \theme_dimensions\local\category\publicaccess}, never inside it, as the showcase's
 * `categorypublicclient` is: publicaccess::is_public() gates every public category page, its image route and its
 * head tags, so a switch folded into it would take them off the internet while this one ships off.
 *
 * A visitor's state is two keys and nothing else: no category narrowing (the categories are the public ones), no
 * "More" filters, no colouring, and nothing saved. It is read from the address alone.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class visitor {
    /** @var string The setting that opens the page and its web service to visitors. */
    public const SETTING = 'calendarpublic';

    /** @var string[] The keys a visitor's calendar carries, in the order the chips are drawn (a subset of keys::ALL). */
    public const KEYS = [keys::CATEGORY, keys::OPEN];

    /**
     * Whether visitors are served the public calendar.
     *
     * Off unless an explicit '1' opens it: a new page and a new endpoint on the internet are opened on purpose, never
     * by an upgrade arriving.
     *
     * @return bool True only when the setting holds an explicit '1'.
     */
    public static function enabled(): bool {
        $stored = get_config('theme_dimensions', self::SETTING);
        return $stored !== false && (string) $stored === '1';
    }

    /**
     * The visitor's keys that are on, from a list asked for: anything outside the two dropped, an empty list for none.
     *
     * @param string[] $names The keys asked for.
     * @return string[] The keys on, in {@see KEYS} order.
     */
    public static function on(array $names): array {
        return array_values(array_intersect(self::KEYS, $names));
    }

    /**
     * The keys a request names, the way the page's address and its form spell them: both when the address names
     * none, the ones listed otherwise, and none for an empty member alone. A value outside the two is dropped, and so is
     * a member that is itself a list: optional_param_array() throws on one, which would answer an anonymous page 500.
     *
     * @return string[] The keys on.
     */
    public static function on_from_request(): array {
        if (!isset($_GET[state::PARAM_KEYS]) && !isset($_POST[state::PARAM_KEYS])) {
            return self::KEYS;
        }
        $raw = $_POST[state::PARAM_KEYS] ?? $_GET[state::PARAM_KEYS];
        if (!is_array($raw)) {
            return [];
        }
        $members = array_filter($raw, static fn($member): bool => is_scalar($member));
        return self::on(array_map(static fn($member): string => clean_param((string) $member, PARAM_ALPHA), $members));
    }

    /**
     * The address parameters naming the keys: none while both are on, the ones on otherwise, an empty member for none.
     *
     * @param string[] $on The keys on.
     * @return array name => value.
     */
    public static function query(array $on): array {
        $on = self::on($on);
        if (count($on) === count(self::KEYS)) {
            return [];
        }
        return [state::PARAM_KEYS => $on ?: ['']];
    }

    /**
     * The page's state for a visitor: every key outside the visitor's off, and the ones the visitor switched off.
     *
     * The renderable reads a {@see state}; this is the one a visitor's page draws, with nothing of a session in it.
     *
     * @param string[] $on The visitor's keys on.
     * @return state
     */
    public static function state(array $on): state {
        return new state(array_diff(keys::ALL, self::on($on)));
    }
}
