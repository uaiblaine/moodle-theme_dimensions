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
 * Theme Dimensions - The Boost Union flavour a person chose for themselves
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * The look a logged-in person chose among the flavours the administrator offers, and how it reaches the page.
 *
 * Boost Union decides the flavour of a page in theme_boost_union_get_flavour_which_applies() alone, and
 * every place that applies one - the stylesheet URL, the logos, the favicon, the body class, the navbar
 * colour, the footer, this theme's dark logo - asks it. That function reads its session cache
 * (theme_boost_union/flavours, keyed by the page's category id) before anything else, so writing the
 * chosen flavour's record there before the first of those calls makes every one of them follow the
 * choice. That is the whole mechanism: {@see self::prime()}, from the before_http_headers hook, which core
 * dispatches in core_renderer::header() before it includes the layout.
 *
 * The choice wins over the administrator's category and cohort flavours (the owner's decision F1 of
 * docs/flavourchoice/PLAN.md). "Site default", the value 0, writes nothing: Boost Union then resolves
 * its own rules exactly as it does without this theme, so nothing of its resolver is copied here.
 *
 * Boost Union memoises its answer in a function static for the rest of the request. Nothing outside the
 * function can reset it, which is why the prime has to come first, and why a test that goes through the
 * resolver runs in a process of its own.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class flavourchoice {
    /** @var string The user preference: the chosen flavour's id, or 0 for the site default. */
    public const PREFERENCE = 'theme_dimensions_flavour';

    /** @var string The setting listing the flavours a person may choose, under theme_dimensions. */
    public const SETTING = 'selectableflavours';

    /** @var int The choice that leaves the page to Boost Union's own rules. */
    public const SITE_DEFAULT = 0;

    /** @var string The one key of this theme's session cache. */
    private const MARKER = 'choice';

    /**
     * The flavours an administrator may offer, for the setting: id => title, in Boost Union's order.
     *
     * The setting's choices are this callable rather than an array, so settings.php reads no table
     * while it is loaded, which happens during a fresh install before the table may be there. The
     * titles are in the ESCAPED spelling, because core's multi-checkbox template prints each label
     * with a triple stash ({{{label}}} in core_admin/setting_configmulticheckbox).
     *
     * @return array Flavour id => formatted title.
     */
    public static function choices(): array {
        global $DB;

        $choices = [];
        foreach ($DB->get_records_menu('theme_boost_union_flavours', null, 'sort ASC, id ASC', 'id, title') as $id => $title) {
            $choices[(int) $id] = format_string($title, true, ['context' => \context_system::instance()]);
        }

        return $choices;
    }

    /**
     * The ids the setting offers today.
     *
     * The multi-checkbox setting stores the ticked keys joined by commas; an unset or empty setting
     * offers nothing, which is how the feature is off.
     *
     * @return int[] The offered flavour ids.
     */
    public static function selectable_ids(): array {
        $stored = (string) get_config('theme_dimensions', self::SETTING);
        if ($stored === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $stored))));
    }

    /**
     * The choice in force for the current person: the stored preference while the setting still offers it.
     *
     * The stored value is never trusted on its own: a flavour taken off the list reads as the site
     * default, whatever the preference still says. Whether the flavour still exists is the record's
     * question, answered in {@see self::prime()}. Reads only memory: the preference is in
     * $USER->preference and the setting in the config cache.
     *
     * @return int The chosen flavour id, or {@see self::SITE_DEFAULT}.
     */
    public static function chosen_id(): int {
        if (!isloggedin() || isguestuser()) {
            return self::SITE_DEFAULT;
        }

        $chosen = (int) get_user_preferences(self::PREFERENCE, self::SITE_DEFAULT);
        if ($chosen === self::SITE_DEFAULT || !in_array($chosen, self::selectable_ids(), true)) {
            return self::SITE_DEFAULT;
        }

        return $chosen;
    }

    /**
     * Write the chosen flavour into Boost Union's session cache for the page about to be drawn.
     *
     * Steps, each cheap unless the session's choice changed:
     * 1. Nothing for a page this theme does not draw, a visitor, the guest, or Boost Union's own
     *    flavour preview, which takes its flavour from the URL.
     * 2. The marker - this theme's session cache - remembers which choice the session was primed with
     *    and that flavour's record. A different choice (made in another session, or a flavour taken off
     *    the list) purges Boost Union's flavour cache for this session, every category at once, and
     *    reads the record again. The marker's cache definition carries Boost Union's invalidation
     *    events, so a flavour edited, re-sorted or deleted refreshes it as well.
     * 3. The site default, or a chosen flavour that no longer exists, writes nothing.
     * 4. Boost Union's purge flag is honoured once and cleared ({@see self::absorb_purge_flag()}).
     * 5. The page's category key gets the record, unless it already holds it.
     *
     * @param \moodle_page $page The page whose header is being printed.
     * @return void
     */
    public static function prime(\moodle_page $page): void {
        global $DB;

        if (!self::applies($page)) {
            return;
        }

        $chosen = self::chosen_id();
        $marker = \cache::make('theme_dimensions', 'flavourchoice');
        $entry = $marker->get(self::MARKER);
        if (!is_object($entry) || (int) $entry->chosen !== $chosen) {
            \cache::make('theme_boost_union', 'flavours')->purge();
            $record = $chosen === self::SITE_DEFAULT ? false :
                $DB->get_record('theme_boost_union_flavours', ['id' => $chosen]);
            /* The marker keeps the chosen id even when its flavour is gone, with no record beside it,
               so a deleted flavour does not read as a new choice - a purge and a query - on every page. */
            $entry = (object) ['chosen' => $chosen, 'record' => $record ?: null];
            $marker->set(self::MARKER, $entry);
        }

        if ($entry->record === null) {
            return;
        }

        self::absorb_purge_flag();

        $flavours = \cache::make('theme_boost_union', 'flavours');
        $key = self::category_key($page);
        $cached = $flavours->get($key);
        if (!is_object($cached) || (int) $cached->id !== (int) $entry->record->id) {
            $flavours->set($key, $entry->record);
        }
    }

    /**
     * Forget what this session was primed with, so the next page header primes it again.
     *
     * For the request that writes a new choice; any other session of the same person notices the new
     * preference on its own (step 2 of {@see self::prime()}).
     *
     * @return void
     */
    public static function forget(): void {
        \cache::make('theme_dimensions', 'flavourchoice')->purge();
        \cache::make('theme_boost_union', 'flavours')->purge();
    }

    /**
     * Whether core's preference services may write the look preference: never.
     *
     * The permission callback of the preference's definition (theme_dimensions_user_preferences()).
     * Core asks it from core_user_set_user_preferences and core_user_update_user_preferences, which
     * would store the value without moving the person's look cohort, so a smart menu restricted by look
     * would disagree with the look shown until the reconcile task ran. {@see self::choose()} writes
     * through set_user_preference(), which asks no callback.
     *
     * @param \stdClass $user The person whose preference would be written.
     * @param string $name The preference's name.
     * @return bool
     */
    public static function preference_editable(\stdClass $user, string $name): bool {
        return false;
    }

    /**
     * Record a new choice for the current person, if it is one the setting offers.
     *
     * The one write path: the web service of the menu and the form of the choice page both come here.
     * The site default is always a valid answer; any other id must be on the list now, so a stale menu
     * or a hand-made request cannot store a flavour the administrator does not offer. The person then
     * moves into the cohort of the look they chose ({@see lookcohorts::sync_user()}).
     *
     * @param int $flavourid The chosen flavour id, or {@see self::SITE_DEFAULT}.
     * @return bool Whether the choice was recorded.
     */
    public static function choose(int $flavourid): bool {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return false;
        }
        if ($flavourid !== self::SITE_DEFAULT && !array_key_exists($flavourid, self::offered())) {
            return false;
        }

        set_user_preference(self::PREFERENCE, $flavourid);
        self::forget();
        lookcohorts::sync_user((int) $USER->id);

        return true;
    }

    /**
     * The flavours the setting offers that still exist: id => plain title, in Boost Union's order.
     *
     * Read on every page that draws the user menu, so it is cached for the whole site under a key made
     * of the current language and the offered ids: a change of the setting is a new key, and Boost
     * Union's invalidation events (a flavour edited, re-sorted or deleted) empty the cache. The titles
     * are in the PLAIN spelling; each template that prints one says which spelling it wants.
     *
     * @return array Flavour id => plain title.
     */
    public static function offered(): array {
        global $DB;

        $ids = self::selectable_ids();
        if (!$ids) {
            return [];
        }

        $cache = \cache::make('theme_dimensions', 'flavourmenu');
        $key = current_language() . '_' . implode('_', $ids);
        $offered = $cache->get($key);
        if (is_array($offered)) {
            return $offered;
        }

        $offered = [];
        $options = ['context' => \context_system::instance(), 'escape' => false];
        foreach ($DB->get_records_list('theme_boost_union_flavours', 'id', $ids, 'sort ASC, id ASC', 'id, title') as $record) {
            $offered[(int) $record->id] = format_string($record->title, true, $options);
        }
        $cache->set($key, $offered);

        return $offered;
    }

    /**
     * The look menu for the current person, or null when there is nothing to choose.
     *
     * Nothing for a visitor or the guest, and nothing while the setting offers no flavour that still
     * exists. The site default is always the first item. Each item's url is the choice page with that
     * flavour preselected: the address without JavaScript, which only shows a form and writes nothing;
     * with JavaScript, theme_dimensions/flavourchoice writes the choice through the web service and
     * reloads the page.
     *
     * @param \moodle_url $returnurl The page the choice is made on, where the person comes back to.
     * @return array|null current (the current item's plain label) and items (id, text: plain label,
     *      url: \moodle_url, iscurrent).
     */
    public static function menu(\moodle_url $returnurl): ?array {
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $offered = self::offered();
        if (!$offered) {
            return null;
        }

        $chosen = self::chosen_id();
        if (!array_key_exists($chosen, $offered)) {
            $chosen = self::SITE_DEFAULT;
        }

        $labels = [self::SITE_DEFAULT => get_string('flavoursitedefault', 'theme_dimensions')] + $offered;
        $items = [];
        foreach ($labels as $id => $label) {
            $items[] = [
                'id' => (int) $id,
                'text' => $label,
                'url' => self::page_url((int) $id, $returnurl),
                'iscurrent' => (int) $id === $chosen,
            ];
        }

        return ['current' => $labels[$chosen], 'items' => $items];
    }

    /**
     * The choice page, with a flavour preselected and the page to come back to.
     *
     * @param int $flavourid The flavour to preselect, or {@see self::SITE_DEFAULT}.
     * @param \moodle_url $returnurl The page to come back to.
     * @return \moodle_url
     */
    public static function page_url(int $flavourid, \moodle_url $returnurl): \moodle_url {
        return new \moodle_url('/theme/dimensions/flavour.php', [
            'flavour' => $flavourid,
            'returnurl' => $returnurl->out_as_local_url(false),
        ]);
    }

    /**
     * Whether this theme, or a child of it, draws the page.
     *
     * Callbacks of an installed theme run whatever theme draws the page (hook callbacks and lib.php
     * callbacks alike), and a look chosen under another theme would never be applied: every entry point
     * of the choice asks this first.
     *
     * @param \moodle_page $page The page.
     * @return bool
     */
    public static function drawn_by_this_theme(\moodle_page $page): bool {
        $theme = $page->theme;

        return $theme->name === 'dimensions' || in_array('dimensions', $theme->parents, true);
    }

    /**
     * Whether the choice may be written for this page at all.
     *
     * Hook callbacks of an installed theme run whatever theme draws the page, so the page's own theme
     * is asked first; a child theme of this one counts as this one.
     *
     * @param \moodle_page $page The page.
     * @return bool
     */
    private static function applies(\moodle_page $page): bool {
        if (during_initial_install()) {
            return false;
        }

        if (!self::drawn_by_this_theme($page)) {
            return false;
        }

        if (!isloggedin() || isguestuser()) {
            return false;
        }

        $preview = new \core\url('/theme/boost_union/flavours/preview.php');

        return !$preview->compare($page->url, URL_MATCH_BASE);
    }

    /**
     * The key Boost Union caches the page's flavour under: the page's category id, 0 outside a category.
     *
     * Spelled as theme_boost_union_get_flavour_which_applies() spells it, because a different key would
     * be a cache entry its resolver never reads.
     *
     * @param \moodle_page $page The page.
     * @return int
     */
    private static function category_key(\moodle_page $page): int {
        if ($page->category != null && $page->category->id != null) {
            return (int) $page->category->id;
        }

        return 0;
    }

    /**
     * Honour Boost Union's flavour purge flag once, and clear it.
     *
     * Boost Union sets theme_boost_union_flavours_purgesessioncache when a person joins or leaves a cohort
     * that a flavour applies to (classes/eventobservers.php), and its resolver purges the session cache
     * whenever the flag is set, before it reads the cache. Nothing in Boost Union ever clears that flag,
     * so from then on the resolver would purge, on every page, the record {@see self::prime()} has just
     * written, and the choice would be lost for that person for good. The purge the flag asks for is
     * done here, once, and the flag cleared. flavourchoice_test pins the defect: when Boost Union clears
     * its own flag, that test reddens and this step goes.
     *
     * @return void
     */
    private static function absorb_purge_flag(): void {
        if (get_user_preferences('theme_boost_union_flavours_purgesessioncache', false) == true) {
            \cache::make('theme_boost_union', 'flavours')->purge();
            unset_user_preference('theme_boost_union_flavours_purgesessioncache');
        }
    }
}
