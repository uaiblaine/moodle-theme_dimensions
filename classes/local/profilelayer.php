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
 * Theme Dimensions - The profile layer of a logged-in account
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Whether the page gets the profile layer, and the template data it is drawn from.
 *
 * The layer is theme_dimensions/profile_layer, printed once per page after the content by the core renderer's
 * standard_after_main_region_html(), closed, and opened by amd/src/profilelayer.js, which the renderer arms beside
 * the print (moodle-dev/docs/design-system/components/side-layer.md). Nothing
 * here renders: the builder reads the user menu core and Boost Union already put in the layout context
 * (core\navigation\output\primary::get_user_menu(), Boost Union's smart menus merged in), the session's user for
 * the identity ({@see identity}), and the three preferences {@see drawernav} already builds, so every clause is
 * testable alone.
 *
 * The rows of core's menu are classified by address and title identifier, never by position, because the site
 * configures the list (customusermenuitems), the extend_user_menu hook adds to it and Boost Union appends its smart
 * menus:
 *
 * - Profile (/user/profile.php) leaves the list: the header's "View profile" button carries it.
 * - Preferences and the two switch role items go to "Account".
 * - Log out, or under "log in as" core's way back (/course/loginas.php, also titled Log out), goes to the foot.
 * - The language submenu and the theme's Look become rows of "Display", beside the colour mode control.
 * - Every other row that has an address goes to "My area" in core's order; a Boost Union smart menu with children
 *   becomes a row that opens a sub-view of its items. Dividers, and items with no address (core's text and header
 *   items, an invalid custom line), are not drawn.
 *
 * Every key of every row is always set: the template's context stack would otherwise resolve a missing one from
 * the context around it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class profilelayer {
    /** @var string Component name, used for every config read and string. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The feature's setting, an admin_setting_configcheckbox that ships on. */
    public const SETTING = 'profilelayer';

    /** @var string The group of the site's own user menu items. */
    public const GROUP_MYAREA = 'myarea';

    /** @var string The group of Preferences and the switch role items. */
    public const GROUP_ACCOUNT = 'account';

    /** @var string The group of the person's display preferences: colour mode, Look and Language. */
    public const GROUP_DISPLAY = 'display';

    /** @var string The key of the language sub-view. */
    public const VIEW_LANGUAGE = 'language';

    /** @var string The key of the Look sub-view. */
    public const VIEW_LOOK = 'look';

    /** @var string The prefix of a smart menu's sub-view key, followed by a counter. */
    public const VIEW_MENU_PREFIX = 'menu-';

    /** @var string The submenu id {@see \theme_dimensions\output\core_renderer::apply_look_menu()} gives the Look. */
    public const LOOK_SUBMENU = 'dimensions-look';

    /**
     * @var array The Font Awesome classes of core's default user menu destinations, by the address's local path.
     *
     * Each is the glyph core's own pix icon maps to (lib/classes/output/icon_system_fontawesome.php): i/grades,
     * i/calendar, i/privatefiles, i/report. The grades item's address is whichever report core's mygrades_url()
     * answers, so the whole /grade/ tree maps to it. A path ending in '/' is a prefix.
     */
    public const ICONS = [
        '/grade/' => 'fa fa-clipboard-check',
        '/calendar/' => 'fa-regular fa-calendar',
        '/user/files.php' => 'fa fa-file-circle-minus',
        '/reportbuilder/' => 'fa fa-chart-column',
    ];

    /** @var string The glyph of Preferences: core's i/settings. */
    public const ICON_PREFERENCES = 'fa fa-gear';

    /** @var string The glyph of the switch role items: core's i/switchrole. */
    public const ICON_SWITCHROLE = 'fa fa-people-arrows';

    /** @var string The glyph of Log out and of the way back: core's a/logout. */
    public const ICON_LOGOUT = 'fa fa-arrow-right-to-bracket';

    /** @var string The glyph of the Language row: core's i/language. */
    public const ICON_LANGUAGE = 'fa fa-language';

    /** @var string The glyph of the Look row, the drawer's. */
    public const ICON_LOOK = 'fa fa-palette';

    /** @var string The glyph of a row that has neither a mapped address nor an icon of its own: the drawer's. */
    public const DEFAULT_ICON = drawernav::DEFAULT_ICON;

    /** @var string[] The title identifiers core gives the "Account" items. */
    private const ACCOUNT_IDENTIFIERS = ['preferences,moodle', 'switchroleto,moodle', 'switchrolereturn,moodle'];

    /** @var string[] The local paths of the "Account" items. */
    private const ACCOUNT_PATHS = ['/user/preferences.php', '/course/switchrole.php'];

    /** @var string The local path of the profile, lifted out of the list. */
    private const PROFILE_PATH = '/user/profile.php';

    /** @var string The local path of core's Log out. */
    private const LOGOUT_PATH = '/login/logout.php';

    /** @var string The local path of core's way back from "log in as". */
    private const REVERT_PATH = '/course/loginas.php';

    /**
     * Whether the feature is switched on.
     *
     * A default-on checkbox reads only an explicit '0' as off, so a site that never saved the setting (false) has
     * the feature without an upgrade step writing it.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (string) get_config(self::COMPONENT, self::SETTING) !== '0';
    }

    /**
     * Whether the page being rendered gets the profile layer.
     *
     * A logged-in real account only (an administrator logged in as somebody holds one): a visitor and the guest
     * account have no user menu to replace. The layouts are guestnav's and the drawer's, for the same reason, and
     * the Moodle app is left alone, as there.
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function applies(\moodle_page $page): bool {
        if (!self::enabled()) {
            return false;
        }
        if (!isloggedin()) {
            return false;
        }
        if (isguestuser()) {
            return false;
        }
        if (in_array((string) $page->pagelayout, guestnav::EXCLUDED_LAYOUTS, true)) {
            return false;
        }
        if (\core_useragent::is_moodle_app()) {
            return false;
        }

        return true;
    }

    /**
     * The template data of theme_dimensions/profile_layer.
     *
     * @param array $usermenu The layout context's usermenu, as core's get_user_menu() and Boost Union built it.
     * @param \moodle_page $page The page being rendered, for the language menu and the Look's way back.
     * @param \renderer_base $output The renderer, for the language menu's export.
     * @param array|null $lookmenu {@see flavourchoice::menu()}'s answer for this page, which the renderer already
     *      asked for the user menu's Look submenu: null while the theme offers no choice.
     * @return array avatar, title, lines, profileurl, editurl, loggedinas, groups, foot and subviews.
     */
    public static function context(array $usermenu, \moodle_page $page, \renderer_base $output, ?array $lookmenu): array {
        global $USER;

        $sorted = self::sort((array) ($usermenu['items'] ?? []), (array) ($usermenu['submenus'] ?? []));

        $subviews = $sorted['subviews'];
        $display = [];
        $look = drawernav::look_from($lookmenu);
        if ($look !== null) {
            $label = get_string('lookmenu', self::COMPONENT);
            $display[] = self::subview_row(self::VIEW_LOOK, $label, self::ICON_LOOK, (string) $look['current']);
            $subviews[] = self::look_view($look);
        }
        $language = drawernav::language($page, $output);
        if ($language !== null) {
            $label = get_string('language');
            $display[] = self::subview_row(self::VIEW_LANGUAGE, $label, self::ICON_LANGUAGE, (string) $language['current']);
            $subviews[] = self::language_view($language);
        }
        $appearance = drawernav::appearance();

        $groups = [];
        foreach ([self::GROUP_MYAREA, self::GROUP_ACCOUNT] as $key) {
            if ($sorted[$key]) {
                $groups[] = self::group($key, $sorted[$key], false);
            }
        }
        if ($display || $appearance !== null) {
            $modes = $appearance === null ? false : [
                'cookieattributes' => $appearance['cookieattributes'],
                'options' => $appearance['modes'],
            ];
            $groups[] = self::group(self::GROUP_DISPLAY, $display, $modes);
        }
        foreach (array_keys($groups) as $index) {
            $groups[$index]['first'] = $index === 0;
        }

        $edit = self::edit_url($USER);

        return [
            'avatar' => self::avatar((array) ($usermenu['avatardata'] ?? [])),
            'title' => identity::title($USER),
            'lines' => array_map(static fn(string $text): array => ['text' => $text], identity::lines($USER)),
            'profileurl' => (new \moodle_url(self::PROFILE_PATH, ['id' => $USER->id]))->out(false),
            'editurl' => $edit === null ? false : $edit->out(false),
            'loggedinas' => self::loggedinas($sorted['revert']),
            'groups' => $groups,
            'foot' => $sorted['foot'] ?? false,
            'subviews' => $subviews,
        ];
    }

    /**
     * Where "Edit profile" leads for the person's own record, or null where core's profile page draws no such link.
     *
     * The rule of core's profile page for its editprofile node (core_myprofile_navigation(), lib/myprofilelib.php),
     * for the viewer's own record on the site profile: moodle/user:update in the system context leads to
     * editadvanced.php; otherwise moodle/user:editownprofile (or moodle/user:editprofile in the user's context for
     * an account that is not an administrator) and an authentication plugin that lets the profile be edited lead
     * to that plugin's address, or to edit.php. The two capabilities of the second branch are asked in the other
     * order than core's, the own-profile one first, so the common case reads no user context; the answer is the
     * same. An authentication plugin that is no longer installed draws no link, where core's page would throw.
     * profilelayer_test compares the answer with core's own tree.
     *
     * @param \stdClass $user The session's user record.
     * @return \moodle_url|null
     */
    public static function edit_url(\stdClass $user): ?\moodle_url {
        if (!isloggedin() || isguestuser($user) || is_mnet_remote_user($user)) {
            return null;
        }
        $system = \context_system::instance();
        if (has_capability('moodle/user:update', $system)) {
            return new \moodle_url('/user/editadvanced.php', ['id' => $user->id, 'course' => SITEID, 'returnto' => 'profile']);
        }
        if (
            !has_capability('moodle/user:editownprofile', $system)
            && !(has_capability('moodle/user:editprofile', \context_user::instance($user->id)) && !is_siteadmin($user))
        ) {
            return null;
        }

        $auth = \core\di::get(\core\authentication::class);
        if (empty($user->auth) || !$auth->plugin_exists((string) $user->auth)) {
            return null;
        }
        $plugin = $auth->get_plugin((string) $user->auth);
        if (!$plugin->can_edit_profile()) {
            return null;
        }
        $url = $plugin->edit_profile_url();
        if (empty($url)) {
            return new \moodle_url('/user/edit.php', ['id' => $user->id, 'returnto' => 'profile']);
        }

        return $url instanceof \moodle_url ? $url : new \moodle_url((string) $url);
    }

    /**
     * Core's menu items, sorted into the layer's places.
     *
     * @param array $items The usermenu's items: core's objects, Boost Union's smart menus (objects or arrays).
     * @param array $submenus The usermenu's submenus, by which a submenu link is resolved.
     * @return array myarea and account (rows), foot (array|null: url, label), revert (the way back's url, or null)
     *      and subviews (the smart menus').
     */
    private static function sort(array $items, array $submenus): array {
        $byid = [];
        $languageid = null;
        foreach ($submenus as $submenu) {
            $submenu = (object) $submenu;
            $id = (string) ($submenu->id ?? '');
            $byid[$id] = $submenu;
            if ((string) ($submenu->title ?? '') === get_string('languageselector')) {
                $languageid = $id;
            }
        }

        $sorted = [self::GROUP_MYAREA => [], self::GROUP_ACCOUNT => [], 'foot' => null, 'revert' => null, 'subviews' => []];
        $counter = 0;
        foreach ($items as $item) {
            $item = (object) $item;
            $type = (string) ($item->itemtype ?? '');
            if ($type === 'divider' || $type === 'invalid') {
                continue;
            }
            if ($type === 'submenu-link' || !empty($item->submenulink)) {
                $id = (string) ($item->submenuid ?? '');
                // The language and the Look are drawn from their own builders, in "Display".
                if ($id === $languageid || $id === self::LOOK_SUBMENU || !isset($byid[$id])) {
                    continue;
                }
                $counter++;
                $key = self::VIEW_MENU_PREFIX . $counter;
                $sorted[self::GROUP_MYAREA][] = self::subview_row($key, self::label($item), self::DEFAULT_ICON, '');
                $sorted['subviews'][] = self::menu_view($key, self::label($item), $byid[$id], $byid);
                continue;
            }

            $url = self::url($item->url ?? null);
            if ($url === null) {
                continue;
            }
            $path = self::local_path($url);
            $identifier = (string) ($item->titleidentifier ?? '');
            if ($path === self::PROFILE_PATH) {
                continue;
            }
            if ($path === self::LOGOUT_PATH || $path === self::REVERT_PATH) {
                $sorted['foot'] = ['url' => $url->out(false), 'label' => self::label($item)];
                $sorted['revert'] = $path === self::REVERT_PATH ? $url->out(false) : null;
                continue;
            }
            if (in_array($path, self::ACCOUNT_PATHS, true) || in_array($identifier, self::ACCOUNT_IDENTIFIERS, true)) {
                $icon = $path === '/user/preferences.php' || $identifier === 'preferences,moodle'
                    ? self::ICON_PREFERENCES : self::ICON_SWITCHROLE;
                $sorted[self::GROUP_ACCOUNT][] = self::link_row(self::label($item), $url->out(false), $icon, false);
                continue;
            }
            $ownicon = self::own_icon($item);
            $icon = $ownicon === false ? self::icon_of($path) : '';
            $sorted[self::GROUP_MYAREA][] = self::link_row(self::label($item), $url->out(false), $icon, $ownicon);
        }

        return $sorted;
    }

    /**
     * The "logged in as" notice, or false outside "log in as".
     *
     * The identity above it is the session's user, the account logged in as (D76); the notice names nobody but
     * says so, and its link is core's way back, labelled with the real user's name as core labels it.
     *
     * @param string|null $revert The way back's address, read from core's menu.
     * @return array|false text, returnurl (string, '' when core's menu carried no way back) and returnlabel.
     */
    private static function loggedinas(?string $revert) {
        if (!\core\session\manager::is_loggedinas()) {
            return false;
        }

        return [
            'text' => get_string('profilelayer_loggedinas', self::COMPONENT),
            'returnurl' => (string) $revert,
            'returnlabel' => get_string('returntooriginaluser', 'moodle', fullname(\core\session\manager::get_realuser())),
        ];
    }

    /**
     * The avatar of the session's user: core's picture, the one core marks current.
     *
     * Printed raw by the template, as core's own user menu prints it: core wrote that HTML (user_picture()).
     *
     * @param array $avatardata The usermenu's avatardata, each with content and classes.
     * @return string HTML.
     */
    private static function avatar(array $avatardata): string {
        $first = '';
        foreach ($avatardata as $avatar) {
            $avatar = (array) $avatar;
            if ((string) ($avatar['classes'] ?? '') === 'current') {
                return (string) ($avatar['content'] ?? '');
            }
            $first = $first === '' ? (string) ($avatar['content'] ?? '') : $first;
        }

        return $first;
    }

    /**
     * A group of the main view.
     *
     * @param string $key One of the GROUP_ constants.
     * @param array $rows The group's rows.
     * @param array|false $modes The colour mode control (cookieattributes, options), or false.
     * @return array key, label, first, modes, hasrows and rows.
     */
    private static function group(string $key, array $rows, $modes): array {
        $label = match ($key) {
            self::GROUP_MYAREA => get_string('profilelayer_myarea', self::COMPONENT),
            self::GROUP_ACCOUNT => get_string('profilelayer_account', self::COMPONENT),
            self::GROUP_DISPLAY => get_string('profilelayer_display', self::COMPONENT),
        };

        return [
            'key' => $key,
            'label' => $label,
            'first' => false,
            'modes' => $modes,
            'hasrows' => $rows !== [],
            'rows' => array_values($rows),
        ];
    }

    /**
     * A row that is a link.
     *
     * @param string $label The plain label.
     * @param string $url The address, plain (the template escapes it once).
     * @param string $icon The Font Awesome classes, '' when the row has an icon of its own.
     * @param array|false $ownicon A smart menu item's own pix icon (key, component), or false.
     * @return array label, url, icon, ownicon, subview and value.
     */
    private static function link_row(string $label, string $url, string $icon, $ownicon): array {
        return ['label' => $label, 'url' => $url, 'icon' => $icon, 'ownicon' => $ownicon, 'subview' => '', 'value' => ''];
    }

    /**
     * A row that opens a sub-view.
     *
     * @param string $key The sub-view's key.
     * @param string $label The plain label.
     * @param string $icon The Font Awesome classes.
     * @param string $value The value in force, shown at the row's end ('' for none).
     * @return array label, url, icon, ownicon, subview and value.
     */
    private static function subview_row(string $key, string $label, string $icon, string $value): array {
        return ['label' => $label, 'url' => '', 'icon' => $icon, 'ownicon' => false, 'subview' => $key, 'value' => $value];
    }

    /**
     * The Look sub-view: each offered look, the one in force checked.
     *
     * @param array $look {@see drawernav::look_from()}'s answer.
     * @return array key, title, radio, look and options.
     */
    private static function look_view(array $look): array {
        $options = [];
        foreach ($look['items'] as $item) {
            $option = self::option((string) $item['text'], (string) $item['url'], (bool) $item['iscurrent']);
            $option['flavour'] = (string) $item['id'];
            $option['mark'] = $item['mark'] ?? false;
            $options[] = $option;
        }

        return [
            'key' => self::VIEW_LOOK,
            'title' => get_string('lookmenu', self::COMPONENT),
            'radio' => true,
            'look' => true,
            'options' => $options,
        ];
    }

    /**
     * The language sub-view: core's language menu, the current language checked.
     *
     * @param array $language {@see drawernav::language()}'s answer.
     * @return array key, title, radio, look and options.
     */
    private static function language_view(array $language): array {
        $options = [];
        foreach ($language['items'] as $item) {
            $option = self::option((string) $item['text'], (string) $item['url'], (bool) $item['iscurrent']);
            $option['lang'] = (string) $item['lang'];
            $options[] = $option;
        }

        return [
            'key' => self::VIEW_LANGUAGE,
            'title' => get_string('language'),
            'radio' => true,
            'look' => false,
            'options' => $options,
        ];
    }

    /**
     * A Boost Union smart menu's sub-view: its items as links, a third level's items after the item that holds them.
     *
     * @param string $key The sub-view's key.
     * @param string $title The plain title.
     * @param \stdClass $submenu The smart menu's submenu, with its items.
     * @param array $byid Every submenu by id, for a third level.
     * @return array key, title, radio, look and options.
     */
    private static function menu_view(string $key, string $title, \stdClass $submenu, array $byid): array {
        $options = [];
        foreach (self::menu_items((array) ($submenu->items ?? []), $byid) as $item) {
            $url = self::url($item->url ?? null);
            if ($url !== null) {
                $options[] = self::option(self::label($item), $url->out(false), false);
            }
        }

        return ['key' => $key, 'title' => $title, 'radio' => false, 'look' => false, 'options' => $options];
    }

    /**
     * A smart menu's items with a third level flattened in place, dividers dropped.
     *
     * @param array $items The submenu's items.
     * @param array $byid Every submenu by id.
     * @return \stdClass[]
     */
    private static function menu_items(array $items, array $byid): array {
        $flat = [];
        foreach ($items as $item) {
            $item = (object) $item;
            if (!empty($item->divider) || (string) ($item->itemtype ?? '') === 'divider') {
                continue;
            }
            $flat[] = $item;
            $id = (string) ($item->submenuid ?? '');
            if ($id !== '' && isset($byid[$id])) {
                foreach ((array) ($byid[$id]->items ?? []) as $child) {
                    $child = (object) $child;
                    if (empty($child->divider) && (string) ($child->itemtype ?? '') !== 'divider') {
                        $flat[] = $child;
                    }
                }
            }
        }

        return $flat;
    }

    /**
     * One option of a sub-view, with no language, no look and no mark; the callers set the ones they have.
     *
     * @param string $label The plain label.
     * @param string $url The address, plain.
     * @param bool $iscurrent Whether it is the value in force.
     * @return array label, url, iscurrent, lang (the lang attribute's spelling, '' for none), flavour (the look's id
     *      for theme_dimensions/flavourchoice, '' for none) and mark (theme_dimensions/look_mark's context, or false).
     */
    private static function option(string $label, string $url, bool $iscurrent): array {
        return ['label' => $label, 'url' => $url, 'iscurrent' => $iscurrent, 'lang' => '', 'flavour' => '', 'mark' => false];
    }

    /**
     * An item's plain label: core's title (already a plain string), else the text with its markup dropped.
     *
     * @param \stdClass $item The item.
     * @return string
     */
    private static function label(\stdClass $item): string {
        $title = trim((string) ($item->title ?? ''));
        if ($title !== '') {
            return drawernav::plain($title);
        }

        return drawernav::plain((string) ($item->text ?? ''));
    }

    /**
     * The glyph of a row: the mapped address's, else the default.
     *
     * @param string|null $path The item's local path, or null for an address of another site.
     * @return string Font Awesome classes.
     */
    private static function icon_of(?string $path): string {
        if ($path !== null) {
            foreach (self::ICONS as $prefix => $icon) {
                if ($path === $prefix || (str_ends_with($prefix, '/') && str_starts_with($path, $prefix))) {
                    return $icon;
                }
            }
        }

        return self::DEFAULT_ICON;
    }

    /**
     * A Boost Union smart menu item's own icon, as its menuicon setting names it ("component:icon"), or false.
     *
     * @param \stdClass $item The item.
     * @return array|false key and component.
     */
    private static function own_icon(\stdClass $item) {
        $menuicon = is_object($item->itemdata ?? null) ? (string) ($item->itemdata->menuicon ?? '') : '';
        if ($menuicon === '' || $menuicon === '0') {
            return false;
        }
        $parts = explode(':', $menuicon, 2);

        return ['key' => $parts[1] ?? $parts[0], 'component' => isset($parts[1]) ? $parts[0] : 'moodle'];
    }

    /**
     * An item's address as a moodle_url, or null for an item without one.
     *
     * @param mixed $url A moodle_url, a string or null.
     * @return \moodle_url|null
     */
    private static function url($url): ?\moodle_url {
        if ($url instanceof \moodle_url) {
            return $url;
        }
        if (is_string($url) && $url !== '' && $url !== '#') {
            return new \moodle_url($url);
        }

        return null;
    }

    /**
     * An address's path inside this site, or null for an address of another site.
     *
     * @param \moodle_url $url The address.
     * @return string|null The path from the site's root, with its leading slash.
     */
    private static function local_path(\moodle_url $url): ?string {
        $root = new \moodle_url('/');
        if (
            $url->get_scheme() !== $root->get_scheme()
            || $url->get_host() !== $root->get_host()
            || $url->get_port() !== $root->get_port()
        ) {
            return null;
        }
        $base = rtrim($root->get_path(false), '/');
        $path = $url->get_path(false);
        if ($base !== '' && !str_starts_with($path, $base . '/')) {
            return null;
        }

        return substr($path, strlen($base));
    }
}
