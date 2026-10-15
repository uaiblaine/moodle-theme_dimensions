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
 * Theme Dimensions - Login page helpers
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Configuration reader and pure helpers for the login page features of this theme.
 *
 * Everything the login page decides is decided here, free of the renderer, so that each decision
 * can be tested on its own: which page is shown, where the panel's logo comes from, how an identity
 * provider is drawn, and what the login layout's context becomes when the custom login page is on.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class login {
    /** @var int Stored value of the 'yes' option in this theme's binary select settings. */
    public const SELECT_YES = 1;

    /** @var int Stored value of the 'no' option in this theme's binary select settings. */
    public const SELECT_NO = 0;

    /** @var string Body class the login layout carries while the theme's login page is on; every login style hangs off it. */
    public const BODY_CLASS = 'theme_dimensions-login';

    /**
     * @var string The class which knows where auth_loginsteps' band logo is, when that plugin is installed.
     *
     * A name, not a dependency. The theme reads the logo through it so that the login page and the
     * plugin's access screens show the same image: the band's logo, made for a dark background as
     * the panel is. But the page must keep rendering when the plugin is not there: a login page
     * which dies over a missing plugin locks everyone out, the admin included.
     */
    public const LOGO_PROVIDER = '\auth_loginsteps\local\shell_assets';

    /** @var string The core oauth2 issuer servicetype this theme has a local mark for. */
    public const GOOGLE_SERVICETYPE = 'google';

    /** @var string Session key counting the wrong passwords this visitor has typed on the login page. */
    public const FAILURES_SESSION_KEY = 'theme_dimensions_loginfailures';

    /** @var int Wrong passwords in a row after which the page suggests resetting it. */
    public const RESET_HINT_AFTER = 2;

    /**
     * Count one more wrong password for this visitor, and return the count.
     *
     * Counted per session, on purpose, and never per account. Reading the account's own
     * login_failed_count would mean looking the typed username up, and a hint that appears only
     * when the lookup succeeds tells an anonymous visitor which usernames exist. The session count
     * says nothing about any account: it is the same for a username that exists and one that does not.
     *
     * It does not need resetting. The page is not shown while logged in, and a logout terminates
     * the session and the count with it.
     *
     * @return int Wrong passwords so far in this session, this one included.
     */
    public static function record_failed_attempt(): int {
        global $SESSION;

        $count = (int) ($SESSION->{self::FAILURES_SESSION_KEY} ?? 0) + 1;
        $SESSION->{self::FAILURES_SESSION_KEY} = $count;

        return $count;
    }

    /**
     * Whether the page should suggest resetting the password, after this many wrong ones.
     *
     * @param int $failures Wrong passwords in this session.
     * @return bool
     */
    public static function suggest_reset(int $failures): bool {
        return $failures >= self::RESET_HINT_AFTER;
    }

    /**
     * Whether the theme's login page replaces Boost Union's.
     *
     * Defaults to 'yes'. A value which was never written - a site whose admin settings for this
     * theme have not been applied yet - is the default, and only a stored 'no' hands the page back to
     * Boost Union; reading the config with a falsy fallback would hide the page on a fresh install.
     *
     * @return bool True when this theme draws the login page itself.
     */
    public static function page_enabled(): bool {
        $config = get_config('theme_dimensions', 'loginpage');
        if ($config === false || $config === '') {
            return true;
        }
        return ((int) $config) === self::SELECT_YES;
    }

    /**
     * Url of the institutional logo for the panel, or '' when the panel should show the wordmark.
     *
     * Three sources in order. First, Boost Union's compact logo, the brand image the design
     * prints. The renderer resolves it - it owns $OUTPUT, and Boost Union's get_compact_logo_url()
     * answers an applicable flavour's logo first, then the theme's own setting, then core's - and
     * hands the url in, so this method stays static and testable. Second, the logo an admin
     * uploaded for the band of auth_loginsteps' access screens, so a site that set its logo only
     * there shows it on this panel too; the plugin serves it without a login and with a cache
     * revision in the url. Third, '' - the site's short name as a wordmark.
     *
     * Asking the plugin is a soft coupling on purpose: the theme declares no dependency on it, and
     * a site running the theme without the plugin - or with a version of it that has no logo yet -
     * gets the wordmark instead of an error page where the login form should be. There is no
     * try/catch: a catch-all would make the class_exists()/method_exists() guard dead code, and a
     * provider that throws is a real fault that should show as one.
     *
     * Both parameters are injected so that every case is testable without the plugin and without a
     * renderer: a compact logo, a provider which does not exist, one which has no method, and one
     * which answers.
     *
     * @param string $compactlogo Boost Union's compact logo url as the renderer resolved it, or ''.
     * @param string $provider Name of a class with a static logo_url() returning the url or ''.
     * @return string The url, or '' for the wordmark.
     */
    public static function logo_url(string $compactlogo = '', string $provider = self::LOGO_PROVIDER): string {
        if ($compactlogo !== '') {
            return $compactlogo;
        }

        if (!class_exists($provider) || !method_exists($provider, 'logo_url')) {
            return '';
        }

        return (string) $provider::logo_url();
    }

    /**
     * The Font Awesome Brands glyph for an identity provider's service, or '' when there is none.
     *
     * The service comes from core's own servicetype field on the issuer, which auth_loginsteps
     * returns beside the url, so no url is ever sniffed. The glyph is one colour and the official
     * mark is four, which is why the template inherits the text colour rather than painting it.
     *
     * @param string $servicetype The issuer's servicetype, e.g. 'google'.
     * @return string A Font Awesome class such as 'fa-google', or ''.
     */
    public static function idp_mark(string $servicetype): string {
        return match ($servicetype) {
            self::GOOGLE_SERVICETYPE => 'fa-google',
            default => '',
        };
    }

    /**
     * An icon url this site serves itself, or '' for one that lives anywhere else.
     *
     * An identity provider's image url points at the PROVIDER's servers, so rendering it makes
     * every visitor's browser announce itself to the provider before the person has chosen
     * anything. A url under this site's own wwwroot - a theme pix icon, say - is harmless and is
     * kept; nothing else ever reaches the page.
     *
     * @param string $url The icon url as core prepared it.
     * @return string The same url when this site serves it, '' otherwise.
     */
    public static function local_icon_url(string $url): string {
        global $CFG;

        if ($url === '' || !str_starts_with($url, rtrim($CFG->wwwroot, '/') . '/')) {
            return '';
        }

        return $url;
    }

    /**
     * The identity providers as the theme's login page draws them.
     *
     * A provider with a known service gets a local mark and no image at all; any other provider
     * gets its name and, only when this site serves it, its icon. No remote image, ever.
     *
     * The label names the product - "Google Workspace" - only while that is unambiguous. The login
     * flow of auth_loginsteps admits several issuers at once, one per organisation being the shape
     * the platform is built around, and two Google issuers labelled identically would be two
     * buttons that read the same; with more than one, each keeps its own name.
     *
     * The name arrives as core prepared it. auth_oauth2 hands over the raw display name and
     * auth_loginsteps a format_string()-escaped one, and neither can be told apart here, so a
     * name with a bare ampersand from the latter shows the entity - the same shape core's own
     * login template has, and something only the plugin can settle at its source.
     *
     * @param array $identityproviders As prepare_identity_providers_for_output() returns them: url,
     *                                 iconurl and name per provider, plus the servicetype auth_loginsteps adds.
     * @return array One entry per provider with the keys url, mark, iconurl and label.
     */
    public static function providers_for_output(array $identityproviders): array {
        $googlecount = 0;
        foreach ($identityproviders as $idp) {
            if ((string) ($idp['servicetype'] ?? '') === self::GOOGLE_SERVICETYPE) {
                $googlecount++;
            }
        }

        $providers = [];
        foreach ($identityproviders as $idp) {
            $servicetype = (string) ($idp['servicetype'] ?? '');
            $mark = self::idp_mark($servicetype);
            $providers[] = [
                'url' => (string) ($idp['url'] ?? ''),
                'mark' => $mark,
                'iconurl' => ($mark === '') ? self::local_icon_url((string) ($idp['iconurl'] ?? '')) : '',
                'label' => ($servicetype === self::GOOGLE_SERVICETYPE && $googlecount === 1)
                    ? get_string('login_idp_google', 'theme_dimensions')
                    : get_string('login_idp', 'theme_dimensions', (string) ($idp['name'] ?? '')),
            ];
        }

        return $providers;
    }

    /**
     * The copy of the institutional panel.
     *
     * Each line is the theme setting when the admin typed one, and the language string otherwise -
     * in that order, so that a site which never touched the settings shows the copy in each
     * user's language, and a site which did shows exactly what it typed. The monitoring notice
     * has its own switch, as the registration panel of auth_loginsteps has, and may carry a link.
     *
     * @return array{kicker: string, title: string, text: string, foot: string} The foot is HTML, the rest plain text.
     */
    public static function panel(): array {
        $foot = '';
        if (self::monitoring_notice_shown()) {
            $raw = trim((string) get_config('theme_dimensions', 'loginpanelfoot'));
            if ($raw === '') {
                $raw = get_string('login_panel_foot', 'theme_dimensions');
            }
            $foot = trim(format_text($raw, FORMAT_HTML, ['context' => \context_system::instance()]));
        }

        return [
            'kicker' => self::panel_text('loginpanelkicker', 'login_panel_kicker'),
            'title' => self::panel_text('loginpaneltitle', 'login_panel_title'),
            'text' => self::panel_text('loginpaneltext', 'login_panel_text'),
            'foot' => $foot,
        ];
    }

    /**
     * Whether the monitoring notice is shown at the foot of the panel.
     *
     * Defaults to 'yes', read the way the other switches of this theme are read.
     *
     * @return bool
     */
    public static function monitoring_notice_shown(): bool {
        $config = get_config('theme_dimensions', 'loginpanelshowmonitoring');
        if ($config === false || $config === '') {
            return true;
        }
        return ((int) $config) === self::SELECT_YES;
    }

    /**
     * One line of the panel: the setting when it holds something, the language string otherwise.
     *
     * Plain text in the plain spelling, for the template's double stashes: format_string() strips
     * any markup an admin pasted and leaves the escaping to Mustache.
     *
     * @param string $setting Name of the theme setting.
     * @param string $string Name of the language string it falls back to.
     * @return string
     */
    private static function panel_text(string $setting, string $string): string {
        $value = trim((string) get_config('theme_dimensions', $setting));
        if ($value === '') {
            $value = get_string($string, 'theme_dimensions');
        }

        return format_string($value, true, ['context' => \context_system::instance(), 'escape' => false]);
    }

    /**
     * Rewrite the login layout's template context for the custom login page.
     *
     * Three things happen to it. The arrangement is forced to the side-by-side one whatever Boost
     * Union's setting says, because the panel lives in the aside that only that arrangement draws;
     * the body gains the class every login style of this theme is scoped to, so switching the page
     * off leaves Boost Union's own login page untouched; and the panel's logo and copy are added
     * for the core/login_panel override to render. Nothing else is written onto the body: the
     * class is the whole of what this theme puts there.
     *
     * The body class is spliced into the attribute string the way Boost Union's own login layout
     * does it, because by the time the layout runs the page has already generated its body
     * attributes and $PAGE->add_body_class() would throw.
     *
     * The compact logo is handed IN rather than resolved here: it needs a renderer, so the decision
     * belongs to the caller and this method stays a pure rewrite that every case can be tested
     * through.
     *
     * @param array $context The context Boost Union's layout/login.php built.
     * @param string $compactlogo Boost Union's compact logo url as the renderer resolved it, or ''.
     * @return array The same context, rewritten.
     */
    public static function layout_context(array $context, string $compactlogo = ''): array {
        global $SITE;

        $context['usesidebysideloginarrangement'] = true;

        $body = (string) ($context['bodyattributes'] ?? '');
        $body = str_replace(
            'theme_boost_union-loginarrangement-legacy',
            'theme_boost_union-loginarrangement-sidebyside',
            $body
        );
        if (preg_match('/\bclass="([^"]*)"/', $body)) {
            $body = preg_replace('/\bclass="([^"]*)"/', 'class="$1 ' . self::BODY_CLASS . '"', $body, 1);
        } else {
            $body .= ' class="' . self::BODY_CLASS . '"';
        }
        $context['bodyattributes'] = $body;

        if (!isset($context['sitename'])) {
            $context['sitename'] = format_string(
                $SITE->shortname,
                true,
                ['context' => \context_course::instance(SITEID), 'escape' => false]
            );
        }
        $context['dimensionsloginpage'] = true;
        $context['dimensionsloginlogourl'] = self::logo_url($compactlogo);
        $context['dimensionsloginpanel'] = self::panel();

        return $context;
    }
}
