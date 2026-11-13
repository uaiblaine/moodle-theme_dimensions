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
 * The choice wins over the administrator's category and cohort flavours. "Site default", the value 0, writes nothing:
 * Boost Union then resolves its own rules exactly as it does without this theme, so nothing of its resolver is copied
 * here.
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

    /** @var string The setting that puts each look's mark before its name in the look menus, under theme_dimensions. */
    public const ICONS_SETTING = 'lookmenufavicons';

    /** @var int The choice that leaves the page to Boost Union's own rules. */
    public const SITE_DEFAULT = 0;

    /** @var string The one key of this theme's session cache. */
    private const MARKER = 'choice';

    /**
     * The flavours an administrator may offer, for the setting: id => title, in Boost Union's order.
     *
     * The setting's choices are a closure over this method rather than an array, so settings.php reads
     * no table while it is loaded. Core still calls it during a fresh install, when
     * admin_apply_default_settings() writes every default before Boost Union's tables exist, so it
     * answers no choices until the flavours table is there. The titles are in the ESCAPED spelling,
     * because core's multi-checkbox template prints each label with a triple stash ({{{label}}} in
     * core_admin/setting_configmulticheckbox).
     *
     * @return array Flavour id => formatted title.
     */
    public static function choices(): array {
        global $DB;

        if (during_initial_install() || !$DB->get_manager()->table_exists('theme_boost_union_flavours')) {
            return [];
        }

        $choices = [];
        foreach ($DB->get_records_menu('theme_boost_union_flavours', null, 'sort ASC, id ASC', 'id, title') as $id => $title) {
            $choices[(int) $id] = format_string($title, true, ['context' => \context_system::instance()]);
        }

        return $choices;
    }

    /**
     * Store an empty list in the setting when it holds no value at all; a stored value, empty or not, is kept.
     *
     * Core's multi-checkbox setting draws no field and writes nothing while its choices are empty, as they are
     * on a site with no Boost Union flavour yet, so neither admin_apply_default_settings() nor a Save ever
     * stores it. Left unset, it counts as a new setting for good: admin/index.php sends every administrator to
     * upgradesettings.php, which draws "New settings - Dimensions" with no field, and its Save brings the same
     * page back. An empty list is the setting's default and offers no look ({@see self::selectable_ids()}).
     * Called from db/install.php and db/upgrade.php.
     *
     * @return void
     */
    public static function ensure_setting(): void {
        if (get_config('theme_dimensions', self::SETTING) === false) {
            set_config(self::SETTING, '', 'theme_dimensions');
        }
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
     * 1. Nothing for a visitor, the guest, or Boost Union's own flavour preview, which takes its flavour
     *    from the URL. A page another theme draws drops what this session was primed with
     *    ({@see self::unprime()}), or Boost Union's resolver would answer it with the chosen look.
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
            self::unprime($page);
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
     * moves into the cohort of the look they chose ({@see lookcohorts::sync_user()}). Somebody logged in
     * as another user is refused: the choice is that person's, and so is the cohort it moves them into.
     *
     * @param int $flavourid The chosen flavour id, or {@see self::SITE_DEFAULT}.
     * @return bool Whether the choice was recorded.
     */
    public static function choose(int $flavourid): bool {
        global $USER;

        if (!isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
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
     * @return array Flavour id => plain title.
     */
    public static function offered(): array {
        return self::looks()['titles'];
    }

    /**
     * The offered flavours' titles and marks, as the look menus read them.
     *
     * Read on every page that draws the user menu, so it is cached for the whole site under a key made
     * of the current language and the offered ids: a change of the setting is a new key, and Boost
     * Union's invalidation events (a flavour edited, re-sorted or deleted) empty the cache. The titles
     * are in the PLAIN spelling; each template that prints one says which spelling it wants. A mark keeps
     * the favicon's file name, never its address, because the address carries the theme revision.
     *
     * @return array titles (id => plain title) and marks (id => favicon: file name or null, colour: a
     *      colour safe in a style attribute, or null), both in Boost Union's order.
     */
    private static function looks(): array {
        global $DB;

        $ids = self::selectable_ids();
        if (!$ids) {
            return ['titles' => [], 'marks' => []];
        }

        $cache = \cache::make('theme_dimensions', 'flavourmenu');
        $key = current_language() . '_' . implode('_', $ids);
        $looks = $cache->get($key);
        // A miss, or an entry without both halves, is built again.
        if (is_array($looks) && isset($looks['titles'], $looks['marks'])) {
            return $looks;
        }

        $looks = ['titles' => [], 'marks' => []];
        $options = ['context' => \context_system::instance(), 'escape' => false];
        $records = $DB->get_records_list(
            'theme_boost_union_flavours',
            'id',
            $ids,
            'sort ASC, id ASC',
            'id, title, look_favicon, look_brandcolor'
        );
        foreach ($records as $record) {
            $looks['titles'][(int) $record->id] = format_string($record->title, true, $options);
            $looks['marks'][(int) $record->id] = [
                'favicon' => (string) $record->look_favicon !== '' ? (string) $record->look_favicon : null,
                'colour' => self::safe_colour((string) $record->look_brandcolor),
            ];
        }
        $cache->set($key, $looks);

        return $looks;
    }

    /**
     * A flavour's brand colour as a style attribute may carry it, or null.
     *
     * Boost Union's colour picker rule accepts a hex code with or without its #, a colour name and the
     * rgb(), rgba(), hsl() and hsla() forms. Each is let through only in a shape that holds no quote, no
     * semicolon and no other property; transparent, currentColor and inherit draw no dot and read as none.
     *
     * @param string $colour The flavour's look_brandcolor.
     * @return string|null The colour, a hex code always with its #, or null.
     */
    private static function safe_colour(string $colour): ?string {
        $colour = trim($colour);
        if (preg_match('/^#?(?:[[:xdigit:]]{3}){1,2}$/', $colour)) {
            return '#' . ltrim($colour, '#');
        }
        if (in_array(strtolower($colour), ['transparent', 'currentcolor', 'inherit'], true)) {
            return null;
        }
        if (preg_match('/^[a-z]+$/i', $colour) || preg_match('/^(?:rgba?|hsla?)\([0-9., %]+\)$/i', $colour)) {
            return $colour;
        }

        return null;
    }

    /**
     * Whether the look menus show each look's mark before its name.
     *
     * @return bool
     */
    public static function marks_shown(): bool {
        return (bool) get_config('theme_dimensions', self::ICONS_SETTING);
    }

    /**
     * The site's favicon, as Boost Union gives it to a page no flavour applies to.
     *
     * Mirrors the last branch of {@see \theme_boost_union\output\core_renderer::favicon()}: Boost Union's own
     * favicon setting, else the theme's pix. That method answers for the page being drawn, so it gives the
     * applied flavour's favicon wherever one applies, which is why the site default's mark cannot ask it.
     * Boost Union's tenant branding (local_boost_union_mwp) is not followed. flavourchoice_test compares the
     * two on a page no flavour applies to.
     *
     * @return \moodle_url
     */
    public static function site_favicon(): \moodle_url {
        global $PAGE;

        $logo = get_config('theme_boost_union', 'favicon');
        if (empty($logo)) {
            return $PAGE->theme->image_url('favicon', 'theme');
        }

        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'theme_boost_union',
            'favicon',
            '64x64/',
            theme_get_revision(),
            $logo
        );
    }

    /**
     * A flavour's favicon at the address Boost Union puts in the browser tab of the flavour's pages.
     *
     * The same address as {@see \theme_boost_union\output\core_renderer::favicon()} builds, size included, so
     * the browser's cache serves the menu the image the tab already fetched; flavourchoice_test compares them.
     *
     * @param int $flavourid The flavour.
     * @param string $filename The flavour's look_favicon.
     * @return \moodle_url
     */
    public static function flavour_favicon(int $flavourid, string $filename): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'theme_boost_union',
            'flavours_look_favicon',
            $flavourid,
            '/64x64/' . theme_get_revision(),
            '/' . $filename
        );
    }

    /**
     * The mark before a look's name: its favicon, else a dot in its brand colour, else an empty box.
     *
     * The site default's mark is the site's favicon. The empty box keeps the names in line.
     *
     * @param int $id The flavour, or {@see self::SITE_DEFAULT}.
     * @param array $marks The marks of {@see self::looks()}.
     * @return array url (string, the image's address) or null, colour or null.
     */
    private static function mark(int $id, array $marks): array {
        if ($id === self::SITE_DEFAULT) {
            return ['url' => self::site_favicon()->out(false), 'colour' => null];
        }
        $mark = $marks[$id] ?? ['favicon' => null, 'colour' => null];
        if ($mark['favicon'] !== null) {
            return ['url' => self::flavour_favicon($id, $mark['favicon'])->out(false), 'colour' => null];
        }

        return ['url' => null, 'colour' => $mark['colour']];
    }

    /**
     * The look menu for the current person, or null when there is nothing to choose.
     *
     * Nothing for a visitor, the guest or somebody logged in as another user ({@see self::choose()}
     * refuses them), and nothing while the setting offers no flavour that still
     * exists. The site default is always the first item. Each item's url is the choice page with that
     * flavour preselected: the address without JavaScript, which only shows a form and writes nothing;
     * with JavaScript, theme_dimensions/flavourchoice writes the choice through the web service and
     * reloads the page.
     *
     * @param \moodle_url $returnurl The page the choice is made on, where the person comes back to.
     * @return array|null current (the current item's plain label) and items (id, text: plain label,
     *      url: \moodle_url, iscurrent, mark: null while the setting hides the marks, else
     *      {@see self::mark()}'s url and colour).
     */
    public static function menu(\moodle_url $returnurl): ?array {
        if (!isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
            return null;
        }
        $looks = self::looks();
        $offered = $looks['titles'];
        if (!$offered) {
            return null;
        }
        $showmarks = self::marks_shown();

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
                'mark' => $showmarks ? self::mark((int) $id, $looks['marks']) : null,
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
        $back = new \moodle_url($returnurl);
        $back->remove_params('sesskey');

        return new \moodle_url('/theme/dimensions/flavour.php', [
            'flavour' => $flavourid,
            'returnurl' => $back->out_as_local_url(false),
        ]);
    }

    /**
     * Where the choice page sends the person back to: the local address it was given, or the preferences page.
     *
     * The address is cleaned here rather than trusted from the caller, and loses its session key: the
     * choice page redirects to it with a GET after the form's POST, and a page whose address carries an
     * action and its key would run that action again. Without the key core's action pages refuse it.
     *
     * @param string $returnurl The address the choice page was given.
     * @return \moodle_url
     */
    public static function back_url(string $returnurl): \moodle_url {
        $local = clean_param($returnurl, PARAM_LOCALURL);
        if ($local === '') {
            return new \moodle_url('/user/preferences.php');
        }
        $back = new \moodle_url($local);
        $back->remove_params('sesskey');

        return $back;
    }

    /**
     * Record the choice the choice page's form carries, if the form was submitted.
     *
     * The page's one write, kept here so its rule is testable: only the form's POST writes, and
     * moodleform answers it only with the session key and an option the form offered; a GET, the
     * preselection the menu's link carries included, only shows the form.
     *
     * @param \theme_dimensions\form\flavourchoice $form The choice page's form.
     * @return bool|null Null when nothing valid was submitted, else whether {@see self::choose()} recorded it.
     */
    public static function submitted(\theme_dimensions\form\flavourchoice $form): ?bool {
        $data = $form->get_data();
        if (!$data) {
            return null;
        }

        return self::choose((int) $data->flavour);
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
     * Drop the chosen look from Boost Union's session cache before another theme draws a page.
     *
     * The entry prime() wrote lives for the session under the page's category key, and Boost Union's
     * resolver reads that cache before it computes anything, so a page of the same category drawn by
     * Boost Union itself (a course or category theme, a user theme, ?theme=) would show the look this
     * theme applies. Only a session this theme primed pays the purge; the next page this theme draws
     * primes again.
     *
     * @param \moodle_page $page The page whose header is being printed.
     * @return void
     */
    private static function unprime(\moodle_page $page): void {
        if (during_initial_install() || self::drawn_by_this_theme($page)) {
            return;
        }
        $entry = \cache::make('theme_dimensions', 'flavourchoice')->get(self::MARKER);
        if (is_object($entry) && $entry->record !== null) {
            self::forget();
        }
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
     * when it sees the flag, before it reads the cache. The resolver runs after {@see self::prime()}, so
     * that purge would erase the record prime() has just written and the page would show Boost Union's
     * own flavour. Boost Union 2026100501 clears the flag after that purge, which limits the loss to the
     * first page after each cohort change; 2026100500, which this theme still accepts, never clears it,
     * so every later page would lose the choice too. Doing the purge here, before the record is written,
     * and clearing the flag keeps the choice on that first page under both. The real-page test after a
     * cohort change in flavourchoice_test holds it.
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
