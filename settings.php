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
 * Theme Dimensions - Settings file
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 *             based on code by Daniel Poggenpohl and Alexander Bias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_boost_union\admin_settingspage_tabs_with_tertiary;
use theme_dimensions\local\calendar\blocks;
use theme_dimensions\local\calendar\coreview;
use theme_dimensions\local\calendar\keycolour_setting;
use theme_dimensions\local\calendar\keycolours;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\filterfields_setting;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\compactnav;
use theme_dimensions\local\darklogo;
use theme_dimensions\local\drawernav;
use theme_dimensions\local\focusmode;
use theme_dimensions\local\guesthome;
use theme_dimensions\local\guestnav;
use theme_dimensions\local\landing\heroformat;
use theme_dimensions\local\landingdefaults;
use theme_dimensions\local\login;
use theme_dimensions\local\readingbar;
use theme_dimensions\local\related\band;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('theme/boost_union:configure', context_system::instance())) {
    // How this file works:
    // Boost Union's settings are divided into multiple settings pages which reside in its own settings category.
    // You will understand it as soon as you look at /theme/boost_union/settings.php.
    // This settings file here is built in a way that it adds another settings page to this existing settings
    // category.

    // However, there is still the $settings variable which is expected by Moodle core to be filled with the theme
    // settings and which is automatically linked from the theme selector page.
    // To avoid that there appears a broken settings page, we redirect the user to the settings page which we
    // really use if he opens this page.
    $mainsettingspageurl = new \core\url('/admin/settings.php', ['section' => 'themesettingdimensions']);
    if ($ADMIN->fulltree && $PAGE->has_set_url() && $PAGE->url->compare($mainsettingspageurl)) {
        redirect(new \core\url('/admin/settings.php', ['section' => 'theme_dimensions']));
    }

    // Create empty settings page structure to make the site administration work on non-admin pages.
    if (!$ADMIN->fulltree) {
        // Create the settings page of this theme
        // (and allow users with the theme/boost_union:configure capability to access it).
        $tab = new admin_settingpage(
            'theme_dimensions',
            get_string('configtitle', 'theme_dimensions', null, true),
            'theme/boost_union:configure'
        );
        $ADMIN->add('theme_boost_union', $tab);

        // Create full settings page structure.
        // phpcs:disable moodle.ControlStructures.ControlSignature.Found
    } else if ($ADMIN->fulltree) {
        // Require the necessary libraries.
        require_once($CFG->dirroot . '/theme/boost_union/lib.php');
        require_once($CFG->dirroot . '/theme/boost_union/locallib.php');
        require_once($CFG->dirroot . '/theme/dimensions/lib.php');
        require_once($CFG->dirroot . '/theme/dimensions/locallib.php');

        // Prepare options array for select settings.
        // Due to MDL-58376, we will use binary select settings instead of checkbox settings throughout this theme.
        $yesnooption = [
                login::SELECT_YES => get_string('yes'),
                login::SELECT_NO => get_string('no'),
        ];

        // Create the settings page of this theme with tabs and tertiary navigation
        // (and allow users with the theme/boost_union:configure capability to access it).
        $page = new admin_settingspage_tabs_with_tertiary(
            'theme_dimensions',
            get_string('configtitle', 'theme_dimensions', null, true),
            'theme/boost_union:configure'
        );

        // Create general settings tab.
        $tab = new admin_settingpage(
            'theme_dimensions_general',
            get_string('generalsettings', 'theme_boost', null, true)
        );

        // Create inheritance heading.
        $name = 'theme_dimensions/inheritanceheading';
        $title = get_string('inheritanceheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Prepare inheritance options.
        $inheritanceoptions = [
                THEME_DIMENSIONS_SETTING_INHERITANCE_INHERIT =>
                        get_string('inheritanceinherit', 'theme_dimensions'),
                THEME_DIMENSIONS_SETTING_INHERITANCE_DUPLICATE =>
                        get_string('inheritanceduplicate', 'theme_dimensions'),
        ];

        // Setting: Pre SCSS inheritance setting.
        $name = 'theme_dimensions/prescssinheritance';
        $title = get_string('prescssinheritancesetting', 'theme_dimensions', null, true);
        $description = get_string('prescssinheritancesetting_desc', 'theme_dimensions', null, true) . '<br />' .
                get_string('inheritanceoptionsexplanation', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            THEME_DIMENSIONS_SETTING_INHERITANCE_INHERIT,
            $inheritanceoptions
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Setting: Extra SCSS inheritance setting.
        $name = 'theme_dimensions/extrascssinheritance';
        $title = get_string('extrascssinheritancesetting', 'theme_dimensions', null, true);
        $description = get_string('extrascssinheritancesetting_desc', 'theme_dimensions', null, true) . '<br />' .
                get_string('inheritanceoptionsexplanation', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            THEME_DIMENSIONS_SETTING_INHERITANCE_INHERIT,
            $inheritanceoptions
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Create look heading.
        $name = 'theme_dimensions/lookheading';
        $title = get_string('lookheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: The Boost Union flavours a person may choose as their look. The choices are a
        // closure, so this file reads no table while it loads. An array callable would not do: the setting
        // keeps any array it is given as the choices themselves and then never calls the loader.
        // No stylesheet depends on the list, so it needs no theme cache reset. Each offered look has a
        // cohort, made or emptied on save. With no flavour the setting draws and saves nothing, so
        // db/install.php stores its empty list ({@see \theme_dimensions\local\flavourchoice::ensure_setting()}).
        $name = 'theme_dimensions/' . \theme_dimensions\local\flavourchoice::SETTING;
        $title = get_string('selectableflavours', 'theme_dimensions', null, true);
        $description = get_string('selectableflavours_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configmulticheckbox(
            $name,
            $title,
            $description,
            [],
            fn(): array => \theme_dimensions\local\flavourchoice::choices()
        );
        $setting->set_updatedcallback('theme_dimensions_sync_look_cohorts');
        $tab->add($setting);

        // Setting: Each look's mark before its name in the look menus. Read when a menu is drawn, so it needs no
        // theme cache reset.
        $name = 'theme_dimensions/' . \theme_dimensions\local\flavourchoice::ICONS_SETTING;
        $title = get_string('lookmenufavicons', 'theme_dimensions', null, true);
        $description = get_string('lookmenufavicons_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 0);
        $tab->add($setting);

        // Add tab to settings page.
        $page->add($tab);

        // Create login page tab.
        $tab = new admin_settingpage(
            'theme_dimensions_login',
            get_string('logintab', 'theme_dimensions', null, true)
        );

        // Create login page heading.
        $name = 'theme_dimensions/loginpageheading';
        $title = get_string('loginpageheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: Which login page is shown.
        // No cache reset on change: the login styles are always compiled, and the body class the
        // renderer adds is what switches them on, so the choice takes effect on the next request.
        $name = 'theme_dimensions/loginpage';
        $title = get_string('loginpage', 'theme_dimensions', null, true);
        $description = get_string('loginpage_desc', 'theme_dimensions', null, true);
        $loginpageoptions = [
                login::SELECT_YES => get_string('loginpage_custom', 'theme_dimensions'),
                login::SELECT_NO => get_string('loginpage_boostunion', 'theme_dimensions'),
        ];
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            login::SELECT_YES,
            $loginpageoptions
        );
        $tab->add($setting);

        // Create institutional panel heading.
        // Empty settings fall back to the language strings, which follow each user's language; a
        // value typed here is shown to everyone exactly as typed.
        $name = 'theme_dimensions/loginpanelheading';
        $title = get_string('loginpanelheading', 'theme_dimensions', null, true);
        $description = get_string('loginpanelheading_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, $description);
        $tab->add($setting);

        // Setting: Panel kicker.
        $name = 'theme_dimensions/loginpanelkicker';
        $title = get_string('loginpanelkicker', 'theme_dimensions', null, true);
        $description = get_string('loginpanelkicker_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configtext($name, $title, $description, '', PARAM_TEXT);
        $tab->add($setting);

        // Setting: Panel title.
        $name = 'theme_dimensions/loginpaneltitle';
        $title = get_string('loginpaneltitle', 'theme_dimensions', null, true);
        $description = get_string('loginpaneltitle_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configtext($name, $title, $description, '', PARAM_TEXT);
        $tab->add($setting);

        // Setting: Panel text.
        $name = 'theme_dimensions/loginpaneltext';
        $title = get_string('loginpaneltext', 'theme_dimensions', null, true);
        $description = get_string('loginpaneltext_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_TEXT);
        $tab->add($setting);

        // Setting: Show the monitoring notice.
        $name = 'theme_dimensions/loginpanelshowmonitoring';
        $title = get_string('loginpanelshowmonitoring', 'theme_dimensions', null, true);
        $description = get_string('loginpanelshowmonitoring_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect($name, $title, $description, login::SELECT_YES, $yesnooption);
        $tab->add($setting);

        // Setting: Monitoring notice text. PARAM_RAW because it may carry a link to the policy it
        // names; format_text() cleans it on the way out, as it does Boost Union's own instructions.
        $name = 'theme_dimensions/loginpanelfoot';
        $title = get_string('loginpanelfoot', 'theme_dimensions', null, true);
        $description = get_string('loginpanelfoot_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_RAW);
        $tab->add($setting);

        // Create guest heading.
        $name = 'theme_dimensions/guesthomepageheading';
        $title = get_string('guesthomepageheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: the guest account's home page, applied in memory per request by local\guesthome
        // and only while the site's own home page is a URL, which core sends the guest to as well.
        $name = 'theme_dimensions/' . guesthome::SETTING;
        $title = get_string('guesthomepage', 'theme_dimensions', null, true);
        $description = get_string('guesthomepage_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            guesthome::OPTION_SITE,
            [
                guesthome::OPTION_SITE => get_string('guesthomepage_site', 'theme_dimensions', null, true),
                guesthome::OPTION_URL => get_string('guesthomepage_url', 'theme_dimensions', null, true),
            ]
        );
        $tab->add($setting);

        // Setting: the custom URL, root-relative as core's own URL home page is.
        $name = 'theme_dimensions/' . guesthome::SETTING_URL;
        $title = get_string('guesthomepageurl', 'theme_dimensions', null, true);
        $description = get_string('guesthomepageurl_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configtext($name, $title, $description, '', PARAM_LOCALURL);
        $tab->add($setting);
        $page->hide_if(
            'theme_dimensions/' . guesthome::SETTING_URL,
            'theme_dimensions/' . guesthome::SETTING,
            'neq',
            guesthome::OPTION_URL
        );

        // Add tab to settings page.
        $page->add($tab);

        // Create identity tab.
        $tab = new admin_settingpage(
            'theme_dimensions_identity',
            get_string('identitytab', 'theme_dimensions', null, true)
        );

        // Create brand heading.
        $name = 'theme_dimensions/brandheading';
        $title = get_string('brandheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: Navbar style.
        $name = 'theme_dimensions/navbarstyle';
        $title = get_string('navbarstyle', 'theme_dimensions', null, true);
        $description = get_string('navbarstyle_desc', 'theme_dimensions', null, true);
        $navbarstyleoptions = [
                THEME_DIMENSIONS_SETTING_NAVBARSTYLE_NAVY =>
                        get_string('navbarstyle_navy', 'theme_dimensions'),
                THEME_DIMENSIONS_SETTING_NAVBARSTYLE_INHERIT =>
                        get_string('navbarstyle_inherit', 'theme_dimensions'),
        ];
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            THEME_DIMENSIONS_SETTING_NAVBARSTYLE_NAVY,
            $navbarstyleoptions
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Setting: The visitor's navbar actions. Default on, and read as on unless an explicit '0' was
        // stored ({@see guestnav::enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . guestnav::SETTING;
        $title = get_string('navbarguestactions', 'theme_dimensions', null, true);
        $description = get_string('navbarguestactions_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: The mobile drawer of a logged-in account. Default on, and read as on unless an explicit
        // '0' was stored ({@see drawernav::enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . drawernav::SETTING;
        $title = get_string('navbardrawer', 'theme_dimensions', null, true);
        $description = get_string('navbardrawer_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: The context bar on activity pages and the course page. Default on, and read as on unless an
        // explicit '0' was stored ({@see readingbar::context_enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . readingbar::SETTING_CONTEXT;
        $title = get_string('navbarcontextbar', 'theme_dimensions', null, true);
        $description = get_string('navbarcontextbar_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: The context bar's own colour. Empty by default, which derives one from the brand colour; the value is
        // compiled into the stylesheet ({@see readingbar::context_colour()}), so a change rebuilds it.
        $name = 'theme_dimensions/' . readingbar::SETTING_COLOUR;
        $title = get_string('navbarcontextcolor', 'theme_dimensions', null, true);
        $description = get_string('navbarcontextcolor_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcolourpicker($name, $title, $description, '');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);
        $page->hide_if('theme_dimensions/' . readingbar::SETTING_COLOUR, 'theme_dimensions/' . readingbar::SETTING_CONTEXT);

        // Setting: The page kinds whose navbar hides on scroll down. None by default ({@see readingbar::autohide_kinds()}).
        $name = 'theme_dimensions/' . readingbar::SETTING_AUTOHIDE;
        $title = get_string('navbarautohide', 'theme_dimensions', null, true);
        $description = get_string('navbarautohide_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configmulticheckbox($name, $title, $description, [], readingbar::kinds());
        $tab->add($setting);

        // Setting: The compact navbar on tablets. Default on, and read as on unless an explicit '0' was stored
        // ({@see compactnav::enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . compactnav::SETTING;
        $title = get_string('navbarcompact', 'theme_dimensions', null, true);
        $description = get_string('navbarcompact_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: The full logo's variant for dark surfaces, the partner of Boost Union's logo ({@see darklogo}).
        // Images only, as Boost Union's own logo settings; the stylesheet carries its address, so a change
        // rebuilds it.
        $name = 'theme_dimensions/' . darklogo::FULL;
        $title = get_string('logodark', 'theme_dimensions', null, true);
        $description = get_string('logodark_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configstoredfile(
            $name,
            $title,
            $description,
            darklogo::FULL,
            0,
            ['maxfiles' => 1, 'accepted_types' => 'web_image']
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Setting: The compact logo's variant for dark surfaces, the partner of Boost Union's compact logo.
        $name = 'theme_dimensions/' . darklogo::COMPACT;
        $title = get_string('logocompactdark', 'theme_dimensions', null, true);
        $description = get_string('logocompactdark_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configstoredfile(
            $name,
            $title,
            $description,
            darklogo::COMPACT,
            0,
            ['maxfiles' => 1, 'accepted_types' => 'web_image']
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Add tab to settings page.
        $page->add($tab);

        // Create focus mode tab.
        $tab = new admin_settingpage(
            'theme_dimensions_focusmode',
            get_string('focusmodetab', 'theme_dimensions', null, true)
        );

        // Create focus mode heading.
        $name = 'theme_dimensions/focusmodeheading';
        $title = get_string('focusmodeheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: The master switch.
        // The description names the bypass parameter, because an admin looking for a way to see an
        // ordinary activity page without switching the whole site off will look here and nowhere else.
        // It ships off (focusmode::master_enabled() reads an unset value as off too), and it decides
        // whether the focus mode stylesheet is compiled at all, so a change rebuilds the theme's CSS.
        $name = 'theme_dimensions/focusmode';
        $title = get_string('focusmode', 'theme_dimensions', null, true);
        $description = get_string('focusmode_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            login::SELECT_NO,
            $yesnooption
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Prepare the reading column width options.
        $focuswidthoptions = [
                focusmode::WIDTH_CONTRACTED =>
                        get_string('focusmodedefaultwidth_contracted', 'theme_dimensions'),
                focusmode::WIDTH_EXPANDED =>
                        get_string('focusmodedefaultwidth_expanded', 'theme_dimensions'),
        ];

        // Setting: The reading column width a person sees before choosing one.
        $name = 'theme_dimensions/focusmodedefaultwidth';
        $title = get_string('focusmodedefaultwidth', 'theme_dimensions', null, true);
        $description = get_string('focusmodedefaultwidth_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            focusmode::WIDTH_CONTRACTED,
            $focuswidthoptions
        );
        $tab->add($setting);

        // Setting: The page types focus mode never applies to.
        $name = 'theme_dimensions/focusmodeexcludedpagetypes';
        $title = get_string('focusmodeexcludedpagetypes', 'theme_dimensions', null, true);
        $description = get_string('focusmodeexcludedpagetypes_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configtextarea(
            $name,
            $title,
            $description,
            focusmode::DEFAULT_EXCLUDED_PAGETYPES,
            PARAM_RAW,
            60,
            5
        );
        $tab->add($setting);

        // Setting: What a course created from now on answers.
        // Its callback writes the previous answer into the courses which never answered when the
        // default changes, or they would all change state.
        $name = 'theme_dimensions/focusmodenewcourses';
        $title = get_string('focusmodenewcourses', 'theme_dimensions', null, true);
        $description = get_string('focusmodenewcourses_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            login::SELECT_NO,
            $yesnooption
        );
        $setting->set_updatedcallback('theme_dimensions_sync_focusmode_default');
        $tab->add($setting);

        // The rest of the tab means nothing while the master switch is off.
        foreach (['focusmodedefaultwidth', 'focusmodeexcludedpagetypes', 'focusmodenewcourses'] as $dependent) {
            $page->hide_if(
                'theme_dimensions/' . $dependent,
                'theme_dimensions/focusmode',
                'neq',
                login::SELECT_YES
            );
        }

        // Add tab to settings page.
        $page->add($tab);

        // Create landing pages tab.
        $tab = new admin_settingpage(
            'theme_dimensions_landing',
            get_string('landingtab', 'theme_dimensions', null, true)
        );

        // Create the banner heading.
        $name = 'theme_dimensions/landingheroheading';
        $title = get_string('landingheroheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: How the banner of every course landing page is drawn. Only a stored 'fullwidth' moves it out of the
        // content column (heroformat::is_fullwidth()), so the default keeps the page a site had before the setting.
        $name = 'theme_dimensions/' . heroformat::SETTING;
        $title = get_string('landingheroformat', 'theme_dimensions', null, true);
        $description = get_string('landingheroformat_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            heroformat::COLUMN,
            heroformat::choices()
        );
        $tab->add($setting);

        // Create the defaults for new courses heading.
        $name = 'theme_dimensions/landingdefaultsheading';
        $title = get_string('landingdefaultsheading', 'theme_dimensions', null, true);
        $description = get_string('landingdefaultsheading_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, $description);
        $tab->add($setting);

        // Settings: What a course created from now on answers in the page model, the modality and the format.
        // No updated callback, unlike focus mode's default: these fields keep their own default value at ''
        // for good, so moving a setting here changes no course that exists and writes nothing.
        foreach (landingdefaults::settings() as $shortname => $key) {
            $name = 'theme_dimensions/' . $key;
            $title = get_string($key, 'theme_dimensions', null, true);
            $description = get_string($key . '_desc', 'theme_dimensions', null, true);
            $setting = new admin_setting_configselect(
                $name,
                $title,
                $description,
                landingdefaults::NONE,
                landingdefaults::choices($shortname)
            );
            $tab->add($setting);
        }

        // Create the related courses heading.
        $name = 'theme_dimensions/landingrelatedheading';
        $title = get_string('landingrelatedheading', 'theme_dimensions', null, true);
        $description = get_string('landingrelatedheading_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, $description);
        $tab->add($setting);

        // Setting: How many related courses the band at the end of a landing page lists at most.
        $name = 'theme_dimensions/' . band::SETTING_COUNT;
        $title = get_string('landingrelatedcount', 'theme_dimensions', null, true);
        $description = get_string('landingrelatedcount_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect($name, $title, $description, band::DEFAULT_COUNT, band::choices());
        $tab->add($setting);

        // Add tab to settings page.
        $page->add($tab);

        // Create category showcase tab.
        $tab = new admin_settingpage(
            'theme_dimensions_vitrine',
            get_string('vitrinetab', 'theme_dimensions', null, true)
        );

        // Create category showcase heading.
        $name = 'theme_dimensions/vitrineheading';
        $title = get_string('vitrineheading', 'theme_dimensions', null, true);
        $setting = new admin_setting_heading($name, $title, null);
        $tab->add($setting);

        // Setting: The kill switch. Default-on; only a stored 0 switches the page off: the
        // logged-in category page is then exactly what Boost Union renders, and the anonymous
        // shell is closed with it (publicaccess::enabled() asks this switch first).
        $name = 'theme_dimensions/' . population::SETTING_ENABLED;
        $title = get_string('categorypageenabled', 'theme_dimensions', null, true);
        $description = get_string('categorypageenabled_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect($name, $title, $description, login::SELECT_YES, $yesnooption);
        $tab->add($setting);

        // Setting: The anonymous shell. Default-on; only a stored 0 switches it off, and
        // then every anonymous request is refused with the login page and nobody is forwarded,
        // whatever state local_unlistedcourses holds a category in.
        $name = 'theme_dimensions/' . publicaccess::SETTING_ENABLED;
        $title = get_string('categorypublicenabled', 'theme_dimensions', null, true);
        $description = get_string('categorypublicenabled_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect($name, $title, $description, login::SELECT_YES, $yesnooption);
        $tab->add($setting);

        /* Setting: the root of the course index on the internet. Read the opposite way round from
           the two above it: they are on unless an explicit '0' closes them, this one is off unless
           an explicit '1' opens it, because the root was never public before this setting and an
           upgrade may not put a site's whole top level on the internet by arriving. A checkbox
           rather than the tab's yes/no selects for the same reason - it is not one more of the
           feature's switches but a decision about what the site publishes. */
        $name = 'theme_dimensions/' . publicaccess::SETTING_ROOT;
        $title = get_string('categoryrootpublic', 'theme_dimensions', null, true);
        $description = get_string('categoryrootpublic_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 0);
        $tab->add($setting);

        /* Setting: the visitor's web service. Off unless an explicit '1' opens it, like the root
           above: it is a new endpoint on the internet, and an upgrade may not open one. It sits
           beside the surface's switch rather than inside it, so with it off the public pages are
           still served, reloading for every search, filter and page. */
        $name = 'theme_dimensions/' . publicaccess::SETTING_CLIENT;
        $title = get_string('categorypublicclient', 'theme_dimensions', null, true);
        $description = get_string('categorypublicclient_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 0);
        $tab->add($setting);

        // Setting: Courses per page.
        $name = 'theme_dimensions/' . population::SETTING_PAGESIZE;
        $title = get_string('categorypagesize', 'theme_dimensions', null, true);
        $description = get_string('categorypagesize_desc', 'theme_dimensions', null, true);
        $pagesizeoptions = [];
        foreach (population::PAGESIZES as $pagesize) {
            $pagesizeoptions[$pagesize] = $pagesize;
        }
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            population::DEFAULT_PAGESIZE,
            $pagesizeoptions
        );
        $tab->add($setting);

        // Setting: Group by enrolment state, the page cut in PHP; off, the page is cut in
        // SQL with core's order alone and no state chips - the hatch for a subtree past ten
        // thousand courses.
        $name = 'theme_dimensions/' . population::SETTING_STATESORT;
        $title = get_string('categorystatesort', 'theme_dimensions', null, true);
        $description = get_string('categorystatesort_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect($name, $title, $description, login::SELECT_YES, $yesnooption);
        $tab->add($setting);

        // Setting: How deep the navigation goes. It bounds the tree beside the listing and the
        // accordion above it, and never the listing itself: a course four levels down is on the
        // page of a category whose tree stops at two. Zero offers the whole subtree.
        $name = 'theme_dimensions/' . population::SETTING_TREEDEPTH;
        $title = get_string('categorytreedepth', 'theme_dimensions', null, true);
        $description = get_string('categorytreedepth_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            population::DEFAULT_TREEDEPTH,
            [
                0 => get_string('categorytreedepth_all', 'theme_dimensions', null, true),
                1 => 1,
                2 => 2,
                3 => 3,
                4 => 4,
            ]
        );
        $tab->add($setting);

        // Setting: The filter groups: an allowlist over the live select-type course custom
        // fields, at most four, validated against the vocabulary rather than typed.
        $name = 'theme_dimensions/categoryfilterfields';
        $title = get_string('categoryfilterfields', 'theme_dimensions', null, true);
        $description = get_string('categoryfilterfields_desc', 'theme_dimensions', null, true);
        $setting = new filterfields_setting($name, $title, $description);
        $tab->add($setting);

        /* Setting: how large a category's card cover video may be, 20 MB by default. The vocabulary
           is core's own upload sizes rather than a free field, because the value is read straight
           back into the field's maxbytes - a configtext defaults to PARAM_RAW and validates
           nothing, so a plausible number typed by hand would silently become the limit. The stored
           value is passed as the custom size so that whatever a site chose is always one of the
           options, and the site's own upload limit still applies on top: the field is bounded by
           the smaller of the two, which is what the help string says (an admin holding
           ignorefilesizelimits is exempt from both, as core intends).
           $CFG->maxbytes is read through ?? and never bare: during a fresh install the admin tree
           is built before core's own defaults are stored, so the property does not exist yet and
           the bare read is a PHP warning, which fails moodle-plugin-ci's install step. */
        $name = 'theme_dimensions/' . branding::SETTING_COVERVIDEO_MAXBYTES;
        $title = get_string('categorycovervideomaxbytes', 'theme_dimensions', null, true);
        $description = get_string('categorycovervideomaxbytes_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configselect(
            $name,
            $title,
            $description,
            branding::DEFAULT_COVERVIDEO_MAXBYTES,
            get_max_upload_sizes((int) ($CFG->maxbytes ?? 0), 0, 0, branding::covervideo_maxbytes())
        );
        $tab->add($setting);

        // Setting: The site-wide hero image: the photograph behind a category hero whose
        // layout asks for an image while the category has none of its own. Its URL carries the theme
        // revision, so the caches are reset when it changes.
        $name = 'theme_dimensions/' . branding::SETTING_FALLBACK;
        $title = get_string('categoryherofallback', 'theme_dimensions', null, true);
        $description = get_string('categoryherofallback_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configstoredfile(
            $name,
            $title,
            $description,
            branding::AREA_FALLBACK,
            0,
            ['maxfiles' => 1, 'accepted_types' => 'web_image']
        );
        $setting->set_updatedcallback('theme_reset_all_caches');
        $tab->add($setting);

        // Add tab to settings page.
        $page->add($tab);

        // Create calendar tab: the theme's calendar page, core's calendar sent to it, the blocks and the public calendar.
        $tab = new admin_settingpage(
            'theme_dimensions_calendar',
            get_string('calendartab', 'theme_dimensions', null, true)
        );

        // Setting: Core's calendar and its iCal feed lead to the theme's calendar and its feed. Default on, and read as
        // on unless an explicit '0' was stored ({@see coreview::enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . coreview::SETTING;
        $title = get_string('calendarcoreview', 'theme_dimensions', null, true);
        $description = get_string('calendarcoreview_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: Core's month and upcoming calendar blocks drawn with the theme's calendar. Default on, and read as
        // on unless an explicit '0' was stored ({@see blocks::enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . blocks::SETTING;
        $title = get_string('calendarblocks', 'theme_dimensions', null, true);
        $description = get_string('calendarblocks_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: The Trilhas view of the calendar page and its choice in the view switch. Default on, and read as on
        // unless an explicit '0' was stored ({@see page::trails_enabled()}), so no upgrade step writes it.
        $name = 'theme_dimensions/' . page::TRAILS_SETTING;
        $title = get_string('calendartrails', 'theme_dimensions', null, true);
        $description = get_string('calendartrails_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 1);
        $tab->add($setting);

        // Setting: The calendar page and its web service for visitors who are not logged in. Default off, and read as on
        // only for an explicit '1' ({@see visitor::enabled()}): a page and an endpoint on the internet are opened on purpose.
        $name = 'theme_dimensions/' . visitor::SETTING;
        $title = get_string('calendarpublic', 'theme_dimensions', null, true);
        $description = get_string('calendarpublic_desc', 'theme_dimensions', null, true);
        $setting = new admin_setting_configcheckbox($name, $title, $description, 0);
        $tab->add($setting);

        // Setting: The event types' colours, one hexadecimal picker per key,
        // each defaulting to its key's own edge, so an untouched site keeps the stylesheet's colours: keycolours hands
        // the pre SCSS callback only a hex that differs from its default.
        $name = 'theme_dimensions/' . keycolours::HEADING;
        $title = get_string('calendarcolours', 'theme_dimensions', null, true);
        $description = get_string('calendarcolours_desc', 'theme_dimensions', null, true);
        $tab->add(new admin_setting_heading($name, $title, $description));
        foreach (keycolours::DEFAULTS as $key => $default) {
            $name = 'theme_dimensions/' . keycolours::setting($key);
            [$title, $description] = keycolours::labels($key);
            $setting = new keycolour_setting($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $tab->add($setting);
        }

        // Add tab to settings page.
        $page->add($tab);

        // Add settings page to the admin settings category.
        $ADMIN->add('theme_boost_union', $page);
    }
}
