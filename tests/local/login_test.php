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
 * Theme Dimensions - Tests for the login page configuration reader
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the login page configuration reader.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(login::class)]
final class login_test extends \advanced_testcase {
    /**
     * A site which never wrote the setting gets the documented default, which is enabled.
     *
     * @return void
     */
    public function test_cpf_validation_is_enabled_when_the_setting_was_never_written(): void {
        $this->resetAfterTest();

        unset_config('logincpfvalidation', 'theme_dimensions');

        $this->assertFalse(get_config('theme_dimensions', 'logincpfvalidation'));
        $this->assertTrue(login::cpf_validation_enabled());
    }

    /**
     * An explicitly stored 'no' switches the feature off.
     *
     * This is the assertion which pins the reading of the setting: a default-on setting read with a
     * falsy fallback would report the stored '0' as enabled and leave the feature permanently on.
     *
     * @return void
     */
    public function test_cpf_validation_is_disabled_when_the_setting_says_no(): void {
        $this->resetAfterTest();

        set_config('logincpfvalidation', login::SELECT_NO, 'theme_dimensions');

        $this->assertFalse(login::cpf_validation_enabled());
    }

    /**
     * An explicitly stored 'yes' switches the feature on.
     *
     * @return void
     */
    public function test_cpf_validation_is_enabled_when_the_setting_says_yes(): void {
        $this->resetAfterTest();

        set_config('logincpfvalidation', login::SELECT_YES, 'theme_dimensions');

        $this->assertTrue(login::cpf_validation_enabled());
    }

    /**
     * The bypass parameter takes the CPF handling off this one page load.
     *
     * This is the way back to a plain login form for an account whose username is not a CPF, the
     * site admin above all. If it stops working, installing this theme locks the admin out.
     *
     * @return void
     */
    public function test_the_bypass_request_turns_the_cpf_handling_off(): void {
        $this->resetAfterTest();

        set_config('logincpfvalidation', login::SELECT_YES, 'theme_dimensions');

        $this->assertTrue(login::cpf_validation_applies(false));
        $this->assertFalse(login::cpf_validation_applies(true));
    }

    /**
     * With the setting off, the CPF handling never applies, bypass or no bypass.
     *
     * @return void
     */
    public function test_cpf_validation_never_applies_when_the_setting_is_off(): void {
        $this->resetAfterTest();

        set_config('logincpfvalidation', login::SELECT_NO, 'theme_dimensions');

        $this->assertFalse(login::cpf_validation_applies(false));
        $this->assertFalse(login::cpf_validation_applies(true));
    }

    /**
     * The bypass sticks for the rest of the session once it has been asked for.
     *
     * The login form posts with no query string, so without this a wrong password on the bypass
     * URL would bounce the admin straight back into the CPF form.
     *
     * @return void
     */
    public function test_the_bypass_sticks_for_the_session(): void {
        global $SESSION;
        $this->resetAfterTest();

        $this->assertFalse(login::bypass_requested());

        // Ask for it once, the way the URL does.
        $_GET[login::BYPASS_PARAM] = '1';
        $this->assertTrue(login::bypass_requested());
        $this->assertTrue(!empty($SESSION->{login::BYPASS_SESSION_KEY}));

        // The next request carries no parameter - the form posts without one - and it still holds.
        unset($_GET[login::BYPASS_PARAM]);
        $this->assertTrue(login::bypass_requested());

        // A session which never asked is unaffected.
        unset($SESSION->{login::BYPASS_SESSION_KEY});
        $this->assertFalse(login::bypass_requested());
    }

    /**
     * An empty stored value is not a decision and falls back to the default.
     *
     * @return void
     */
    public function test_cpf_validation_falls_back_to_the_default_on_an_empty_stored_value(): void {
        $this->resetAfterTest();

        set_config('logincpfvalidation', '', 'theme_dimensions');

        $this->assertSame('', get_config('theme_dimensions', 'logincpfvalidation'));
        $this->assertTrue(login::cpf_validation_enabled());
    }

    /**
     * The theme's login page is shown on a site which never wrote the setting.
     *
     * @return void
     */
    public function test_the_dimensions_page_is_shown_when_the_setting_was_never_written(): void {
        $this->resetAfterTest();

        unset_config('loginpage', 'theme_dimensions');

        $this->assertFalse(get_config('theme_dimensions', 'loginpage'));
        $this->assertTrue(login::page_enabled());
    }

    /**
     * A stored 'no' hands the login page back to Boost Union.
     *
     * The same weight as the CPF 'no' case: a default-on setting read with a falsy fallback would
     * report the stored 0 as enabled and leave Boost Union's page unreachable.
     *
     * @return void
     */
    public function test_a_stored_no_hands_the_login_page_back_to_boost_union(): void {
        $this->resetAfterTest();

        set_config('loginpage', login::SELECT_NO, 'theme_dimensions');

        $this->assertFalse(login::page_enabled());
    }

    /**
     * A stored 'yes' shows the custom login page.
     *
     * @return void
     */
    public function test_a_stored_yes_shows_the_dimensions_page(): void {
        $this->resetAfterTest();

        set_config('loginpage', login::SELECT_YES, 'theme_dimensions');

        $this->assertTrue(login::page_enabled());
    }

    /**
     * Without the registration plugin, the panel shows the wordmark.
     *
     * The provider is a class which does not exist, which is what a site without auth_loginsteps
     * looks like to the theme. This is the guard that keeps a missing plugin from taking the login
     * page down, and with it every way into the site.
     *
     * @return void
     */
    public function test_the_logo_falls_back_to_the_wordmark_without_the_registration_plugin(): void {
        $provider = '\theme_dimensions\local\no_such_registration';

        $this->assertFalse(class_exists($provider));
        $this->assertSame('', login::logo_url('', $provider));
    }

    /**
     * A provider which exists but has no logo yet - an older plugin - falls back too.
     *
     * @return void
     */
    public function test_the_logo_falls_back_when_the_provider_has_no_logo_method(): void {
        $provider = get_class(new class {
        });

        $this->assertTrue(class_exists($provider));
        $this->assertFalse(method_exists($provider, 'logo_url'));
        $this->assertSame('', login::logo_url('', $provider));
    }

    /**
     * With a provider which knows the logo, the panel shows it.
     *
     * The control for the two fallbacks above: it proves the guard is a guard and not a function
     * that returns '' whatever it is given.
     *
     * @return void
     */
    public function test_the_logo_comes_from_the_provider(): void {
        $provider = get_class(new class {
            /**
             * The url the plugin would hand over.
             *
             * @return string
             */
            public static function logo_url(): string {
                return 'https://www.example.com/moodle/pluginfile.php/1/auth_loginsteps/shell_logo/0/logo.png?rev=7';
            }
        });

        $this->assertSame(
            'https://www.example.com/moodle/pluginfile.php/1/auth_loginsteps/shell_logo/0/logo.png?rev=7',
            login::logo_url('', $provider)
        );
    }

    /**
     * Without a compact logo the panel shows the band logo of auth_loginsteps' access screens.
     *
     * The provider is the plugin's shell_assets, whose band logo is made for a dark background as
     * the panel is; the registration panel's logo it used to read was retired by the plugin in
     * 2026100609. Where the plugin is installed the logo is stored and must come back through the
     * default provider; the control is the same call before the file exists, which answers the
     * wordmark. Without the plugin only the name is pinned.
     *
     * Changes that must make it fail: the provider pointed back at the retired registration class,
     * or at any class that does not read the band's logo.
     *
     * @return void
     */
    public function test_the_default_provider_is_the_plugins_band_logo(): void {
        $this->assertSame('\\auth_loginsteps\\local\\shell_assets', login::LOGO_PROVIDER);
        if (!class_exists(login::LOGO_PROVIDER)) {
            $this->markTestSkipped('auth_loginsteps is not installed beside the theme');
        }
        $this->resetAfterTest();

        // Control: no band logo stored, so the default provider answers the wordmark.
        $this->assertSame('', login::logo_url());

        get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'auth_loginsteps',
            'filearea' => 'shell_logo',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'band.png',
        ], 'the bytes');

        $url = login::logo_url();
        $this->assertStringContainsString('/auth_loginsteps/shell_logo/band.png', $url);
        $this->assertSame(\auth_loginsteps\local\shell_assets::logo_url(), $url);
    }

    /**
     * Boost Union's compact logo wins over the plugin's, and the plugin is not even asked for it.
     *
     * Wherever the design prints the brand, the image is theme_boost_union | logocompact, which
     * Boost Union resolves through an applicable flavour first, so an organisation's own mark reaches
     * the panel. The plugin's logo is the fallback behind it, so that no site already running that
     * plugin loses the image it uploaded there.
     *
     * The control is the fixture: the provider it is given answers a url, and the assertion is that
     * that url is not what comes back. Without it the test would pass against a method that ignored
     * the provider entirely.
     *
     * @return void
     */
    public function test_the_compact_logo_comes_first_and_the_plugin_second(): void {
        $compact = 'https://www.example.com/moodle/pluginfile.php/1/theme_boost_union/logo/300x300/7/brand.png';
        $plugin = 'https://www.example.com/moodle/pluginfile.php/1/auth_loginsteps/shell_logo/0/logo.png?rev=7';
        $provider = get_class(new class {
            /**
             * The url the plugin would hand over.
             *
             * @return string
             */
            public static function logo_url(): string {
                return 'https://www.example.com/moodle/pluginfile.php/1/auth_loginsteps/shell_logo/0/logo.png?rev=7';
            }
        });

        // The compact logo is the answer whoever else could have answered.
        $this->assertSame($compact, login::logo_url($compact, $provider));
        $this->assertSame($compact, login::logo_url($compact, '\theme_dimensions\local\no_such_registration'));
        // Without one, the plugin is asked - and it really does answer something else.
        $this->assertSame($plugin, login::logo_url('', $provider));
        // And with neither, the wordmark.
        $this->assertSame('', login::logo_url('', '\theme_dimensions\local\no_such_registration'));
    }

    /**
     * The class is the whole of what this theme writes onto the body, whatever logo there is.
     *
     * The logo reaches the panel through the context and never travels on the body, so a page of
     * another plugin that renders on this layout gets the login flow's class and nothing of its
     * own. The control is the logo itself: it was resolved and it is in the context, so the bare
     * attribute string is not a logo that never arrived.
     *
     * @return void
     */
    public function test_the_body_gains_the_class_and_nothing_else(): void {
        $this->resetAfterTest();

        $compact = 'https://www.example.com/moodle/pluginfile.php/1/theme_boost_union/logo/300x300/7/brand.png';
        $out = login::layout_context(['bodyattributes' => 'id="page-login-index" class="pagelayout-login"'], $compact);

        $this->assertSame($compact, $out['dimensionsloginlogourl']);
        $this->assertSame(
            'id="page-login-index" class="pagelayout-login ' . login::BODY_CLASS . '"',
            $out['bodyattributes']
        );
    }

    /**
     * A provider whose service this theme knows gets a local mark, a product label and no image.
     *
     * The image url is the provider's own favicon on the provider's servers - exactly what the
     * page must never fetch - and it is dropped even though it is present.
     *
     * @return void
     */
    public function test_a_known_service_gets_a_local_mark_and_no_image(): void {
        $this->resetAfterTest();

        $out = login::providers_for_output([
            [
                'url' => 'https://www.example.com/moodle/auth/loginsteps/login.php?id=1',
                'iconurl' => 'https://accounts.google.com/favicon.ico',
                'name' => 'Google',
                'servicetype' => 'google',
            ],
        ]);

        $this->assertCount(1, $out);
        $this->assertSame('https://www.example.com/moodle/auth/loginsteps/login.php?id=1', $out[0]['url']);
        $this->assertSame('fa-google', $out[0]['mark']);
        $this->assertSame('', $out[0]['iconurl']);
        $this->assertSame('Sign in with Google Workspace', $out[0]['label']);
    }

    /**
     * A provider this theme has no mark for never gets a remote image either: only its name.
     *
     * This is also the shape auth_oauth2's own list has - no servicetype key at all.
     *
     * @return void
     */
    public function test_an_unknown_provider_never_gets_a_remote_image(): void {
        $this->resetAfterTest();

        $out = login::providers_for_output([
            [
                'url' => 'https://www.example.com/moodle/auth/oauth2/login.php?id=2',
                'iconurl' => 'https://idp.example.org/mark.png',
                'name' => 'Other IdP',
            ],
        ]);

        $this->assertSame('', $out[0]['mark']);
        $this->assertSame('', $out[0]['iconurl']);
        $this->assertSame('Sign in with Other IdP', $out[0]['label']);
    }

    /**
     * A known mark suppresses the image even when this site would serve it.
     *
     * The rule is "a glyph and no image at all", and it is only distinguishable from "a glyph and
     * a local icon" with a fixture that offers both.
     *
     * @return void
     */
    public function test_a_known_mark_suppresses_even_a_local_icon(): void {
        global $CFG;
        $this->resetAfterTest();

        $out = login::providers_for_output([
            [
                'url' => 'https://www.example.com/moodle/auth/loginsteps/login.php?id=1',
                'iconurl' => $CFG->wwwroot . '/theme/image.php/boost/auth_oauth2/1/google',
                'name' => 'Google',
                'servicetype' => 'google',
            ],
        ]);

        $this->assertSame('fa-google', $out[0]['mark']);
        $this->assertSame('', $out[0]['iconurl']);
    }

    /**
     * Two Google issuers keep their own names; one alone gets the product name.
     *
     * @return void
     */
    public function test_two_google_issuers_keep_their_own_names(): void {
        $this->resetAfterTest();

        $beta = [
            'url' => 'https://www.example.com/moodle/auth/loginsteps/login.php?id=1',
            'iconurl' => '',
            'name' => 'Beta Workspace',
            'servicetype' => 'google',
        ];
        $alfa = ['name' => 'Alfa Workspace'] + $beta;

        $out = login::providers_for_output([$beta, $alfa]);
        $this->assertSame('Sign in with Beta Workspace', $out[0]['label']);
        $this->assertSame('Sign in with Alfa Workspace', $out[1]['label']);
        $this->assertSame('fa-google', $out[0]['mark']);
        $this->assertSame('fa-google', $out[1]['mark']);

        $out = login::providers_for_output([$beta]);
        $this->assertSame('Sign in with Google Workspace', $out[0]['label']);
    }

    /**
     * An icon this site serves itself is kept - the control for the rule above.
     *
     * A url merely starting with the wwwroot's characters is not this site: the check is on the
     * wwwroot followed by a slash.
     *
     * @return void
     */
    public function test_an_icon_this_site_serves_is_kept(): void {
        global $CFG;
        $this->resetAfterTest();

        $local = $CFG->wwwroot . '/theme/image.php/boost/auth_other/1/icon';
        $lookalike = $CFG->wwwroot . 'evil/theme/image.php/boost/auth_other/1/icon';

        $this->assertSame($local, login::local_icon_url($local));
        $this->assertSame('', login::local_icon_url($lookalike));
        $this->assertSame('', login::local_icon_url(''));

        $out = login::providers_for_output([
            ['url' => 'https://www.example.com/moodle/auth/other/login.php', 'iconurl' => $local, 'name' => 'Other'],
        ]);
        $this->assertSame($local, $out[0]['iconurl']);
    }

    /**
     * The login layout is forced side by side, gains the body class and carries the panel.
     *
     * The input is what Boost Union's layout builds for its legacy arrangement, which is the case
     * that has to be overruled: the panel lives in the aside only the side-by-side one draws.
     *
     * @return void
     */
    public function test_the_layout_context_forces_the_side_by_side_arrangement(): void {
        $this->resetAfterTest();

        $out = login::layout_context([
            'usesidebysideloginarrangement' => false,
            'bodyattributes' => 'id="page-login-index" class="pagelayout-login theme_boost_union-loginarrangement-legacy"',
            'sitename' => 'Acme',
        ]);

        $this->assertTrue($out['usesidebysideloginarrangement']);
        $this->assertStringContainsString('theme_boost_union-loginarrangement-sidebyside', $out['bodyattributes']);
        $this->assertStringNotContainsString('loginarrangement-legacy', $out['bodyattributes']);
        $this->assertStringContainsString(login::BODY_CLASS, $out['bodyattributes']);
        // Spliced into the one class attribute, not added as a second one.
        $this->assertSame(1, substr_count($out['bodyattributes'], 'class="'));
        $this->assertStringContainsString('pagelayout-login', $out['bodyattributes']);
        $this->assertTrue($out['dimensionsloginpage']);
        $this->assertSame('', $out['dimensionsloginlogourl']);
        $this->assertSame('Acme', $out['sitename']);
        $this->assertSame(get_string('login_panel_kicker', 'theme_dimensions'), $out['dimensionsloginpanel']['kicker']);
        $this->assertSame(get_string('login_panel_title', 'theme_dimensions'), $out['dimensionsloginpanel']['title']);
        $this->assertSame(get_string('login_panel_text', 'theme_dimensions'), $out['dimensionsloginpanel']['text']);
        $this->assertStringContainsString(
            get_string('login_panel_foot', 'theme_dimensions'),
            $out['dimensionsloginpanel']['foot']
        );
    }

    /**
     * With nothing typed into the settings, the panel shows the language strings.
     *
     * @return void
     */
    public function test_the_panel_copy_falls_back_to_the_language_strings(): void {
        $this->resetAfterTest();

        foreach (['loginpanelkicker', 'loginpaneltitle', 'loginpaneltext', 'loginpanelfoot'] as $setting) {
            unset_config($setting, 'theme_dimensions');
        }

        $panel = login::panel();

        $this->assertSame(get_string('login_panel_kicker', 'theme_dimensions'), $panel['kicker']);
        $this->assertSame(get_string('login_panel_title', 'theme_dimensions'), $panel['title']);
        $this->assertSame(get_string('login_panel_text', 'theme_dimensions'), $panel['text']);
        $this->assertStringContainsString(get_string('login_panel_foot', 'theme_dimensions'), $panel['foot']);
    }

    /**
     * A setting an admin typed wins over the language string, and the notice may carry a link.
     *
     * A setting holding only whitespace is not a choice and falls back, so that a field cleared
     * in the admin form never leaves the panel blank.
     *
     * @return void
     */
    public function test_the_panel_copy_comes_from_the_settings_when_set(): void {
        $this->resetAfterTest();

        set_config('loginpanelkicker', 'Ensino a distância', 'theme_dimensions');
        set_config('loginpaneltitle', 'Um título nosso', 'theme_dimensions');
        set_config('loginpaneltext', 'Um texto nosso.', 'theme_dimensions');
        set_config(
            'loginpanelfoot',
            '<p>Uso monitorado. <a href="https://example.com/politica">Política de segurança</a></p>',
            'theme_dimensions'
        );

        $panel = login::panel();

        $this->assertSame('Ensino a distância', $panel['kicker']);
        $this->assertSame('Um título nosso', $panel['title']);
        $this->assertSame('Um texto nosso.', $panel['text']);
        $this->assertStringContainsString('<a href="https://example.com/politica">Política de segurança</a>', $panel['foot']);

        set_config('loginpaneltitle', '   ', 'theme_dimensions');
        $this->assertSame(get_string('login_panel_title', 'theme_dimensions'), login::panel()['title']);
    }

    /**
     * Markup pasted into a plain line of the panel is stripped, not rendered and not shown as text.
     *
     * @return void
     */
    public function test_markup_pasted_into_a_panel_line_is_stripped(): void {
        $this->resetAfterTest();

        set_config('loginpaneltitle', 'Título <b>forte</b>', 'theme_dimensions');

        $this->assertSame('Título forte', login::panel()['title']);
    }

    /**
     * The monitoring notice has its own switch, on by default.
     *
     * @return void
     */
    public function test_the_monitoring_notice_can_be_switched_off(): void {
        $this->resetAfterTest();

        unset_config('loginpanelshowmonitoring', 'theme_dimensions');
        $this->assertTrue(login::monitoring_notice_shown());
        $this->assertNotSame('', login::panel()['foot']);

        set_config('loginpanelshowmonitoring', login::SELECT_NO, 'theme_dimensions');
        $this->assertFalse(login::monitoring_notice_shown());
        $this->assertSame('', login::panel()['foot']);

        set_config('loginpanelshowmonitoring', login::SELECT_YES, 'theme_dimensions');
        $this->assertTrue(login::monitoring_notice_shown());
        $this->assertStringContainsString(get_string('login_panel_foot', 'theme_dimensions'), login::panel()['foot']);
    }

    /**
     * Wrong passwords are counted in the session, one per call.
     *
     * @return void
     */
    public function test_wrong_passwords_are_counted_per_session(): void {
        global $SESSION;
        $this->resetAfterTest();

        unset($SESSION->{login::FAILURES_SESSION_KEY});

        $this->assertSame(1, login::record_failed_attempt());
        $this->assertSame(2, login::record_failed_attempt());
        $this->assertSame(3, login::record_failed_attempt());
        $this->assertSame(3, $SESSION->{login::FAILURES_SESSION_KEY});
    }

    /**
     * The reset hint comes with the second wrong password, and stays from then on.
     *
     * @return void
     */
    public function test_the_reset_hint_comes_with_the_second_wrong_password(): void {
        $this->assertSame(2, login::RESET_HINT_AFTER);
        $this->assertFalse(login::suggest_reset(0));
        $this->assertFalse(login::suggest_reset(1));
        $this->assertTrue(login::suggest_reset(2));
        $this->assertTrue(login::suggest_reset(3));
    }

    /**
     * A body with no class attribute at all gets one.
     *
     * @return void
     */
    public function test_the_layout_context_adds_a_class_attribute_when_there_is_none(): void {
        $this->resetAfterTest();

        $out = login::layout_context(['bodyattributes' => 'id="page-login-index"']);

        $this->assertSame('id="page-login-index" class="' . login::BODY_CLASS . '"', $out['bodyattributes']);
        $this->assertNotSame('', $out['sitename']);
    }
}
