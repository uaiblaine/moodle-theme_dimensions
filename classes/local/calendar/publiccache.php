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
 * Theme Dimensions - The public calendar's two halves, kept in a shared cache under a revision token each.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * Where the public calendar's answer is kept, and when it stops being the answer.
 *
 * The visitor's answer is the union of two halves that never share an item ({@see publicrange}): the category events of
 * the public categories ({@see EVENTS}, the key "category") and the public event courses ({@see COURSES}, the key
 * "open"). Nothing in either depends on who asks, so each half is the same for every visitor and is kept in two places:
 *
 * - the MUC application cache `publiccalendar`, shared by every web node, under a key naming the half, its token, the
 *   language and the window ({@see half()});
 * - a CDN or any shared cache in front of the site, through the route that answers a half
 *   ({@see \theme_dimensions\route\controller\calendar_public}), whose address carries the token.
 *
 * The token is the half's revision, kept in the MUC application cache `publiccalendarrev`: a change that can alter a
 * half mints a new one ({@see bump()}), and the page, which is never cached, hands the client the current one. So a page
 * loaded after a change asks for addresses no cache holds yet, and no CDN is ever purged; the route answers only the
 * current token and sends any other to it, uncached. A token also expires by itself after {@see ROTATION} seconds, for
 * the writes no event announces (a direct SQL update, an import that bypasses the APIs, the multilang filter switched,
 * the site's timezone or router configuration changed).
 *
 * The two halves age differently, by the owner's rule (2026-11-21): a course published by mistake must leave the
 * internet quickly, a category event may stay cached for longer. Hence a token and a CDN lifetime per half
 * ({@see lifetime()}, two settings).
 *
 * local_unlistedcourses forbids caching ITS answers across requests because tool_dynamic_cohorts writes cohort members
 * without events, which a viewer's answer depends on; the public answer has no viewer and no cohort term, and every
 * fact it reads (the state rows, the visibility and path of a category or a course, the events) changes through an
 * event observed here. The owner signed that distinction off on 2026-11-21 for the public answer only.
 *
 * Every store this class writes is shared (MUC application caches); nothing is node-local, so a cold node reads the
 * same tokens, and no task or lock is involved.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publiccache {
    /** @var string The half of the public categories' category events. */
    public const EVENTS = 'events';

    /** @var string The half of the public event courses. */
    public const COURSES = 'courses';

    /** @var string[] The halves, in the order an answer is merged. */
    public const HALVES = [self::EVENTS, self::COURSES];

    /** @var string[] The key each half answers, half => key ({@see visitor::KEYS}). */
    public const KEY_OF = [self::EVENTS => keys::CATEGORY, self::COURSES => keys::OPEN];

    /** @var int[] How long a token stands without an event, in seconds, half => seconds. */
    public const ROTATION = [self::EVENTS => DAYSECS, self::COURSES => HOURSECS];

    /** @var string[] The settings of the lifetime a shared cache may keep a half, half => setting name. */
    public const SETTING_LIFETIME = [
        self::EVENTS => 'calendarpubliceventslifetime',
        self::COURSES => 'calendarpubliccourseslifetime',
    ];

    /** @var int[] Those lifetimes' defaults, in seconds, half => seconds: a day for events, five minutes for courses. */
    public const DEFAULT_LIFETIME = [self::EVENTS => DAYSECS, self::COURSES => 5 * MINSECS];

    /** @var int How long a browser may keep a half, in seconds: a visitor moving back and forth asks nothing again. */
    public const BROWSER_LIFETIME = 60;

    /** @var int How far from this month, in months either way, a window's answer is kept ({@see near()}). */
    public const REACH_MONTHS = 24;

    /** @var string The MUC definition of the tokens. */
    private const TOKENS = 'publiccalendarrev';

    /** @var string The MUC definition of the halves. */
    private const ANSWERS = 'publiccalendar';

    /**
     * The half's current token, minted when there is none or when the one there is older than {@see ROTATION}.
     *
     * @param string $half {@see EVENTS} or {@see COURSES}.
     * @return string Eight lower-case hexadecimal characters.
     */
    public static function token(string $half): string {
        $cache = \cache::make('theme_dimensions', self::TOKENS);
        $stored = $cache->get($half);
        $now = \core\di::get(\core\clock::class)->time();
        $fresh = is_array($stored) && isset($stored['token'], $stored['minted'])
            && $now - (int) $stored['minted'] < self::ROTATION[$half];
        if ($fresh) {
            return (string) $stored['token'];
        }
        return self::mint($half, $now);
    }

    /**
     * Mint new tokens: the halves named stop being the answer for every page loaded from now on.
     *
     * The answers kept under the old tokens are dropped too; they are unreachable already (the token is in their key),
     * so this only gives the memory back.
     *
     * @param string ...$halves The halves, {@see HALVES} when none is named.
     * @return void
     */
    public static function bump(string ...$halves): void {
        $now = \core\di::get(\core\clock::class)->time();
        foreach ($halves ?: self::HALVES as $half) {
            self::mint($half, $now);
        }
        \cache::make('theme_dimensions', self::ANSWERS)->purge();
    }

    /**
     * Write a fresh token for a half.
     *
     * Random rather than counted: MUC has no atomic increment, and a cache-buster needs only to differ from the last.
     *
     * @param string $half The half.
     * @param int $now The time it is minted at.
     * @return string The token.
     */
    private static function mint(string $half, int $now): string {
        $token = bin2hex(random_bytes(4));
        \cache::make('theme_dimensions', self::TOKENS)->set($half, ['token' => $token, 'minted' => $now]);
        return $token;
    }

    /**
     * One half for one window in the current language: from the shared cache, or computed and kept there.
     *
     * The key carries the token, so an answer kept before a {@see bump()} is never read again.
     *
     * @param string $half {@see EVENTS} or {@see COURSES}.
     * @param window $window The days.
     * @param bool $keep Whether the answer is read from and kept in the cache; false computes it alone.
     * @return array The half, in publicrange's answer shape.
     */
    public static function half(string $half, window $window, bool $keep = true): array {
        if (!$keep) {
            return $half === self::EVENTS ? publicrange::events($window) : publicrange::courses($window);
        }
        $cache = \cache::make('theme_dimensions', self::ANSWERS);
        $key = self::key($half, self::token($half), $window);
        $answer = $cache->get($key);
        if (is_array($answer)) {
            return $answer;
        }
        $answer = $half === self::EVENTS ? publicrange::events($window) : publicrange::courses($window);
        $cache->set($key, $answer);
        return $answer;
    }

    /**
     * The key a half is kept under: the half, its token, the current language and the window.
     *
     * The token is in the key although a bump also purges the cache, for the request that read the old token, then
     * computed the half while a bump ran, and writes it after the purge: that late answer lands under the old token,
     * where nothing reads it.
     *
     * @param string $half The half.
     * @param string $token The token the answer was computed under.
     * @param window $window The days.
     * @return string Letters, digits and underscores (MUC's simple keys).
     */
    public static function key(string $half, string $token, window $window): string {
        return implode('_', [
            $half,
            $token,
            current_language(),
            str_replace('-', '', $window->from),
            str_replace('-', '', $window->to),
        ]);
    }

    /**
     * How long a shared cache may keep a half, in seconds: its setting, or its default while the setting is unset.
     *
     * @param string $half The half.
     * @return int Seconds, never negative.
     */
    public static function lifetime(string $half): int {
        $stored = get_config('theme_dimensions', self::SETTING_LIFETIME[$half]);
        if ($stored === false || $stored === '' || !is_numeric($stored)) {
            return self::DEFAULT_LIFETIME[$half];
        }
        return max(0, (int) $stored);
    }

    /**
     * Whether a window has a shape the visitor's calendar asks for: a day, a week, or a month's grid.
     *
     * The client asks for those three only ({@see page::grid()}, {@see page::week()} from {@see page::week_start()}, and
     * the phone's day view), so refusing every other window bounds the windows anybody can make the route answer.
     *
     * @param string $from The first day, YYYY-MM-DD, already parsed as a real day.
     * @param string $to The last day, YYYY-MM-DD, already parsed as a real day.
     * @param int $firstday The weekday a week begins on, 0 for Sunday ({@see calendar_get_starting_weekday()}).
     * @return bool
     */
    public static function canonical(string $from, string $to, int $firstday): bool {
        if ($from === $to) {
            return true;
        }
        if ([$from, $to] === page::week(page::week_start($from, $firstday))) {
            return true;
        }
        // A month's grid starts at most six days before the 1st, so the sixth day after its start is in that month.
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, new \DateTimeZone('UTC'));
        return [$from, $to] === page::grid($start->modify('+6 days')->format('Y-m'), $firstday);
    }

    /**
     * Whether a window starts within {@see REACH_MONTHS} of this month, either way: the windows that are kept.
     *
     * A window further away is still answered, uncached ({@see half()} with $keep false, and `no-store` on the route),
     * so a page fixed on a distant month keeps working while what a cache can be made to hold stays bounded.
     *
     * @param string $from The first day, YYYY-MM-DD, already parsed as a real day.
     * @return bool
     */
    public static function near(string $from): bool {
        $utc = new \DateTimeZone('UTC');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, $utc);
        $month = (new \DateTimeImmutable('@' . \core\di::get(\core\clock::class)->time()))->setTimezone($utc)
            ->modify('first day of this month')->setTime(0, 0);
        return $start >= $month->modify('-' . self::REACH_MONTHS . ' months')
            && $start < $month->modify('+' . (self::REACH_MONTHS + 1) . ' months');
    }

    /**
     * A calendar event changed: a category event bumps the events' half; any other event is no visitor's.
     *
     * @param \core\event\base $event calendar_event_created, calendar_event_updated or calendar_event_deleted.
     * @return void
     */
    public static function calendar_event_changed(\core\event\base $event): void {
        if ((int) $event->contextlevel === CONTEXT_COURSECAT) {
            self::bump(self::EVENTS);
        }
    }

    /**
     * A course changed (its name, dates, fields, visibility, category or state): the courses' half.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function course_changed(\core\event\base $event): void {
        self::bump(self::COURSES);
    }

    /**
     * A category changed (its name, visibility, place or state): both halves, which both name categories.
     *
     * @param \core\event\base $event The event.
     * @return void
     */
    public static function category_changed(\core\event\base $event): void {
        self::bump();
    }
}
