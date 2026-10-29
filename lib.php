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
 * Theme Dimensions - Library
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 *             based on code by Daniel Poggenpohl and Alexander Bias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Constants which are used throughout this theme.
define('THEME_DIMENSIONS_SETTING_INHERITANCE_INHERIT', 0);
define('THEME_DIMENSIONS_SETTING_INHERITANCE_DUPLICATE', 1);
define('THEME_DIMENSIONS_SETTING_NAVBARSTYLE_NAVY', 0);
define('THEME_DIMENSIONS_SETTING_NAVBARSTYLE_INHERIT', 1);

/**
 * Returns the main SCSS content.
 *
 * @param \core\output\theme_config $theme The theme config object.
 * @return string
 */
function theme_dimensions_get_main_scss_content($theme) {
    global $CFG;

    // Require the necessary libraries.
    require_once($CFG->dirroot . '/theme/boost_union/lib.php');

    // As a start, get the compiled main SCSS from Boost Union.
    // This way, this theme will ship the same SCSS code as Boost Union itself.
    $scss = theme_boost_union_get_main_scss_content(\core\output\theme_config::load('boost_union'));

    // And add this theme's main SCSS file to the stack.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/post.scss');

    // Then the enrolment landing page styles, which consume the tokens post.scss declares.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/landing.scss');

    // And the login page, scoped to the body class the renderer adds while the custom login page is on.
    // Every sheet is named here by hand: a file added to scss/ and not listed is never served.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/login.scss');

    // And the sign-in steps of other components drawn on auth_loginsteps' shell (layout/login.php),
    // scoped under the class only that layout's bare page writes.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/loginshell.scss');

    // And the focus mode styles, which consume the same tokens, only while the master switch is on:
    // off, nothing of the feature is loaded (the setting rebuilds the theme's CSS when it changes).
    // Every rule in that file is scoped under a class only a page that can switch focus mode carries.
    if (\theme_dimensions\local\focusmode::master_enabled()) {
        $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/focusmode.scss');
    }

    // Then the category showcase, scoped under the body class its renderer adds.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/category.scss');

    // And the category appearance form, scoped under the body class that page adds. It is a
    // second file rather than a section of the one above because the two share no scope: the
    // showcase's tokens are declared under its own body class and never reach the editor.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/categoryhero.scss');

    // And the course notice, which replaces Boost Union's course related hints on every course page.
    // Every rule in it is qualified with the notice's own class.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/coursenotice.scss');

    // And the mobile drawer of a logged-in account (classes/local/drawernav.php). Every rule in it is
    // nested under the drawer's own class, and it reads the family tokens post.scss declares.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/drawer.scss');

    // And the navbar that follows the reading (classes/local/readingbar.php): the band's track, the context layer
    // and auto-hide. Every rule is scoped to a body class the renderer adds or to the navbar's own class, and it
    // reads the navbar pill mixin and the tokens post.scss declares.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/readingbar.scss');

    // And the navbar's compact mode on tablets (classes/local/compactnav.php), every rule nested under the body class
    // the renderer writes where the mode may happen, between the md and xl breakpoints.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/compactnav.scss');

    // And the notifications popover and the user menu as bottom sheets on phones, every rule under the navbar's own
    // class and the region's, below the sm breakpoint.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/sheets.scss');

    // And the navbar's controls that move into the user menu on phones (classes/local/phonenav.php), every rule under
    // the navbar's own class.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/phonenav.scss');

    // And the competency framework's pages and dialogues (tool_lp, report_competency, block_lp), scoped
    // to the body classes core derives for those scripts, the block's root and the dim-comp- classes
    // only the theme's copies of their templates carry. It reads Font Awesome's fa-content() and
    // $fa-var-* from the Boost SCSS compiled above it.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/competency.scss');

    return $scss;
}

/**
 * Get SCSS to prepend.
 *
 * @param \core\output\theme_config $theme The theme config object.
 * @return string
 */
function theme_dimensions_get_pre_scss($theme) {
    global $CFG;

    // Require the necessary libraries.
    require_once($CFG->dirroot . '/theme/boost_union/lib.php');

    // As a start, initialize the Pre SCSS code with an empty string.
    $scss = '';

    // Then, if configured, get the compiled pre SCSS code from Boost Union.
    // This should not be necessary as Moodle core calls the *_get_pre_scss() functions from all parent themes as well.
    // However, as soon as Boost Union would use $theme->settings in this function, $theme would be this theme here and
    // not Boost Union. The Boost Union developers are aware of this topic, but faults can always happen.
    // If such a fault happens, the administrator can switch the inheritance to 'Duplicate'.
    // This way, we will add the pre SCSS code with the explicit use of the Boost Union configuration to the stack.
    $inheritanceconfig = get_config('theme_dimensions', 'prescssinheritance');
    if ($inheritanceconfig == THEME_DIMENSIONS_SETTING_INHERITANCE_DUPLICATE) {
        $scss .= theme_boost_union_get_pre_scss(\core\output\theme_config::load('boost_union'));
    }

    // And add this theme's pre SCSS file to the stack.
    $scss .= file_get_contents($CFG->dirroot . '/theme/dimensions/scss/pre.scss');

    // No brand colour is assigned here: $primary is Boost Union's (its brandcolor setting and a
    // flavour's look_brandcolor) and core's otherwise. A settings-driven assignment would belong in
    // this callback and not in the extra SCSS, which runs after Bootstrap has been compiled.

    // The context bar's own colour, when an administrator chose one: scss/readingbar.scss reads it with a !default
    // and derives one from $primary otherwise, so the derivation follows a flavour's brand colour too. Written with a
    // !default itself: a flavour's raw initial SCSS, compiled before this callback, may set its own and must win.
    $contextcolour = \theme_dimensions\local\readingbar::context_colour();
    if ($contextcolour !== null) {
        $scss .= "\n\$dimensions-ctxbar-color: {$contextcolour} !default;\n";
    }

    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param \core\output\theme_config $theme The theme config object.
 * @return string
 */
function theme_dimensions_get_extra_scss($theme) {
    global $CFG;

    // Require the necessary libraries.
    require_once($CFG->dirroot . '/theme/boost_union/lib.php');

    // As a start, initialize the Extra SCSS code with an empty string.
    $scss = '';

    // Then, if configured, get the compiled extra SCSS code from Boost Union.
    // The reasoning is the same as in theme_dimensions_get_pre_scss().
    $inheritanceconfig = get_config('theme_dimensions', 'extrascssinheritance');
    if ($inheritanceconfig == THEME_DIMENSIONS_SETTING_INHERITANCE_DUPLICATE) {
        $scss .= theme_boost_union_get_extra_scss(\core\output\theme_config::load('boost_union'));
    }

    // Then, the settings-dependent rules which need Bootstrap's mixins, so they cannot live in the
    // pre SCSS.
    $scss .= theme_dimensions_get_settings_extra_scss();

    // And the addresses of the logo variants for dark surfaces, as Boost does its background image: the
    // stylesheet is rebuilt when a file setting changes. Config and file settings only, never the page:
    // this callback also runs on the command line.
    $scss .= \theme_dimensions\local\darklogo::scss($theme);

    return $scss;
}

/**
 * Decide where the user's name is shown, from this theme's and Boost Union's settings.
 *
 * Boost Union's showfullnameinusermenu governs the name block at the top of the user menu, and it
 * ships off. This theme switches that block on in the renderer so that a collapsed navbar still
 * identifies the session - but only as a fallback. When the admin has turned the setting on
 * themselves, they asked for the name in the menu and it stays there at every width, even though
 * the navbar shows the same name from md up; when they have not, the block is hidden again from md
 * up, where the navbar carries it.
 *
 * @return string
 */
function theme_dimensions_get_settings_extra_scss() {
    if (get_config('theme_boost_union', 'showfullnameinusermenu') == THEME_BOOST_UNION_SETTING_SELECT_YES) {
        return '';
    }

    return '
@include media-breakpoint-up(md) {
    #user-action-menu .loggedinas,
    #user-action-menu .loggedinas + .dropdown-divider {
        display: none;
    }
}
';
}

/**
 * Declare the user preferences this theme owns.
 *
 * Core discovers them through get_plugins_with_function('user_preferences'), which is what lets
 * core_user_update_user_preferences() accept a write from the focus mode AMD module. The
 * permission callback is core's own "only the person themselves", which is the whole access rule
 * for a reading-width choice.
 *
 * @return array[]
 */
function theme_dimensions_user_preferences(): array {
    return [
        \theme_dimensions\local\focusmode::PREFERENCE_EXPANDED => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // The category showcase's view, cards or the dense list. Declared here so core's preference
        // service accepts the write from the listing's client module; without JavaScript the page
        // writes it from its own form.
        \theme_dimensions\output\category_listing::PREFERENCE_VIEW => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => \theme_dimensions\output\category_toolbar::VIEW_CARDS,
            'choices' => [
                \theme_dimensions\output\category_toolbar::VIEW_CARDS,
                \theme_dimensions\output\category_toolbar::VIEW_LIST,
            ],
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // The fold of the showcase's hero: folded, the hero keeps its title row and its
        // tabs and drops the tagline, the description and the counts. Written from the hero's own
        // GET form for a logged-in person, so the page without JavaScript remembers it too.
        \theme_dimensions\output\category_hero::PREFERENCE_FOLDED => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // The fold of the course notice's promotion section, written by its AMD module; unset (the
        // default) draws the notice unfolded.
        \theme_dimensions\output\course_notice::PREFERENCE_COLLAPSED => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => false,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // The person's look: a Boost Union flavour id, or 0 for the site default. Any integer is
        // accepted here because the offered list changes; local\flavourchoice reads a value the
        // setting no longer offers as the site default.
        \theme_dimensions\local\flavourchoice::PREFERENCE => [
            'type' => PARAM_INT,
            'null' => NULL_NOT_ALLOWED,
            'default' => \theme_dimensions\local\flavourchoice::SITE_DEFAULT,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Add the Look link to the person's own preferences page.
 *
 * Core calls this while it builds the user settings navigation, on every page that builds it and for
 * every installed theme, so the link is added only on /user/preferences.php, only on a page this theme
 * draws (a look chosen under another theme would never be applied) and only for the person's own
 * preferences: an administrator looking at someone else's has no look to choose for them. It is left
 * out while nothing is offered, as the user menu's Look submenu is. The page it opens is the choice
 * page, whose form comes back to the preferences page. This is tool_mfa's pattern for the same page.
 *
 * @param navigation_node $navigation The user settings navigation.
 * @param stdClass $user The user whose settings are shown.
 * @param context_user $usercontext That user's context.
 * @param stdClass $course The course of the page.
 * @param context_course $coursecontext That course's context.
 * @return void
 */
function theme_dimensions_extend_navigation_user_settings(
    navigation_node $navigation,
    stdClass $user,
    $usercontext,
    stdClass $course,
    $coursecontext
): void {
    global $PAGE, $USER;

    $preferences = new moodle_url('/user/preferences.php');
    if (!$PAGE->has_set_url() || !$PAGE->url->compare($preferences, URL_MATCH_BASE)) {
        return;
    }
    if ((int) $user->id !== (int) $USER->id) {
        return;
    }
    if (!\theme_dimensions\local\flavourchoice::drawn_by_this_theme($PAGE)) {
        return;
    }
    if (\theme_dimensions\local\flavourchoice::menu($preferences) === null) {
        return;
    }

    $account = $navigation->find('useraccount', navigation_node::TYPE_CONTAINER);
    if ($account) {
        $account->add_node(navigation_node::create(
            get_string('lookmenu', 'theme_dimensions'),
            new moodle_url('/theme/dimensions/flavour.php'),
            navigation_node::TYPE_SETTING,
            null,
            'dimensionslook'
        ));
    }
}

/**
 * Keep the courses which never answered the focus mode question on the answer that applied to them.
 *
 * Registered with set_updatedcallback(), which takes a plain function name, so this is a shim over
 * the class method that does the work. admin_apply_default_settings() runs it during an install,
 * when there is no field and no course to write into, and a callback which does not short-circuit
 * there breaks the install.
 *
 * @return void
 */
function theme_dimensions_sync_focusmode_default(): void {
    global $CFG;

    if (during_initial_install() || !empty($CFG->upgraderunning)) {
        return;
    }

    $newdefault = get_config('theme_dimensions', 'focusmodenewcourses');
    \theme_dimensions\local\focusmodefields::sync_default(
        $newdefault == \theme_dimensions\local\login::SELECT_YES
    );
}

/**
 * Give every offered look its cohort, and empty the cohorts of looks no longer offered.
 *
 * The updated callback of the offered-flavours setting, a shim like the one above, and for the same
 * reason it does nothing during an install or an upgrade.
 *
 * @return void
 */
function theme_dimensions_sync_look_cohorts(): void {
    global $CFG;

    if (during_initial_install() || !empty($CFG->upgraderunning)) {
        return;
    }

    \theme_dimensions\local\lookcohorts::provision();
}

/**
 * Callback function for theme_boost_union to allow this theme to add cards to the Boost Union settings overview page.
 * This function is expected to return an array of arrays containing values with the keys 'label', 'desc', 'btn' and 'url'.
 *
 * @return array
 */
function theme_dimensions_extend_busettingsoverview() {
    $cards = [];

    $cards[] = [
        'label' => get_string('pluginname', 'theme_dimensions'),
        'desc' => get_string('settingsoverviewdesc', 'theme_dimensions'),
        'btn' => 'primary',
        'url' => new \core\url('/admin/settings.php', ['section' => 'theme_dimensions']),
    ];

    return $cards;
}

/**
 * Callback function which allows themes to alter the CSS URLs.
 * We use this function to change the CSS URL to the flavour CSS URL if a flavour applies to the current page.
 *
 * @param mixed $urls The CSS URLs (passed as reference).
 */
function theme_dimensions_alter_css_urls(&$urls) {
    global $CFG;

    // Require Boost Union library.
    require_once($CFG->dirroot . '/theme/boost_union/lib.php');

    // Call Boost Union's theme_boost_union_alter_css_urls() function which implements the logic to change the CSS URL for flavours.
    theme_boost_union_alter_css_urls($urls);
}

/**
 * Keep a profile away from an authenticated user who has no course in common with its owner.
 *
 * Registered as the core_user profile callback: \core\user::can_view_profile() dispatches it through
 * \core\user::process_profile_callbacks() before it consults has_coursecontact_role(), so a veto here is
 * final. The rule itself, and why it is a veto and never a force-allow, is in the class docblock.
 *
 * get_plugins_with_function() reads this file for every installed theme, active or not, so the
 * rule applies even while the site uses or previews another theme. For a privacy rule that is the
 * safe direction, but it is a surprise worth expecting.
 *
 * @param stdClass $user The user whose profile is being asked for.
 * @param stdClass|null $course The course whose page the request came from, if any.
 * @param context|null $usercontext The user context, as core passes it.
 * @return int One of the core_user::VIEWPROFILE_* constants.
 */
function theme_dimensions_control_view_profile($user, $course = null, $usercontext = null) {
    return \theme_dimensions\local\profileaccess::control_view_profile($user, $course, $usercontext);
}

/**
 * Serve the theme's file areas: the public landing page's, the category branding's and the dark logos'.
 *
 * file_pluginfile() hands any component carrying an underscore to <component>_pluginfile()
 * with no login check of its own, which is what makes an anonymous image route possible
 * under forcelogin. Every check is in publicfiles::resolve() and branding::resolve(); a
 * request they refuse gets a bare 404. Core's sending options are not passed on to either
 * route: each decides cacheability itself.
 *
 * @param stdClass $course The course, as core resolved it from the context.
 * @param stdClass|null $cm Unused: no area lives at module level.
 * @param context $context The context the URL names.
 * @param string $filearea The file area.
 * @param array $args The remaining path segments.
 * @param bool $forcedownload Passed on to the theme's setting files alone; the other routes decide it themselves.
 * @param array $options Core's sending options, passed on to the theme's setting files alone.
 * @return void
 */
function theme_dimensions_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    // The hero image of a category on the category showcase, its card's cover and cover video,
    // its three badges and the Open Graph derivative its public page advertises: every check is
    // in branding::resolve().
    $branding = \theme_dimensions\local\category\branding::class;
    $brandingareas = array_merge(
        [$branding::AREA_HERO, $branding::AREA_COVER, $branding::AREA_COVERVIDEO, $branding::AREA_OGIMAGE],
        $branding::AREA_BADGES
    );
    if ($context->contextlevel == CONTEXT_COURSECAT && in_array($filearea, $brandingareas, true)) {
        $branding::serve($context, $filearea, $args);
    }
    // The site's logo variants for dark surfaces: theme settings' own files in the system context, public
    // like Boost Union's logos, which every visitor's navbar and login page draw.
    if (\theme_dimensions\local\darklogo::serves($context, $filearea)) {
        $theme = \core\output\theme_config::load('dimensions');
        $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        return;
    }
    // The site-wide fallback of that image: a theme setting's own file in the system context,
    // institutional content with nothing to protect; core serves it cacheable by browsers and
    // proxies, as it serves Boost Union's own global images.
    if ($context->contextlevel == CONTEXT_SYSTEM && $filearea === $branding::AREA_FALLBACK) {
        $theme = \core\output\theme_config::load('dimensions');
        $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        return;
    }
    \theme_dimensions\local\landing\publicfiles::serve($context, $filearea, $args);
}

/**
 * Add the category appearance editor to a category's settings navigation.
 *
 * Core folds the category's settings into its secondary navigation, so the node becomes one
 * of the tabs the showcase's hero merges into itself, and the page's own "More" menu holds it
 * when the row is full. Gated exactly as the editor is (moodle/category:manage) and
 * only while the showcase is switched on, because the hero the editor shapes is not drawn
 * otherwise.
 *
 * @param navigation_node $parentnode The category's settings node.
 * @param context_coursecat $context The category's context.
 * @return void
 */
function theme_dimensions_extend_navigation_category_settings(
    navigation_node $parentnode,
    context_coursecat $context
): void {
    if (!\theme_dimensions\local\category\population::enabled()) {
        return;
    }
    if (!has_capability('moodle/category:manage', $context)) {
        return;
    }
    $parentnode->add(
        get_string('categoryhero_nav', 'theme_dimensions'),
        new moodle_url('/theme/dimensions/categoryhero.php', ['id' => $context->instanceid]),
        navigation_node::TYPE_SETTING,
        null,
        'categoryappearance',
        new pix_icon('i/edit', '')
    );
}

/**
 * Add this theme's recommendations to Boost Union's recommendations overview.
 *
 * Boost Union calls this through get_plugins_with_function(), which caches the list of plugins that
 * define the function, so a new site or a purge is needed before a recommendation added here shows.
 * Boost Union's categories are a fixed list a plugin cannot extend, so every id starts with
 * "dimensions_" and every title with "Dimensions" to keep the items recognisable.
 *
 * @return string[] The class names, each extending \theme_boost_union\recommendation\recommendation.
 */
function theme_dimensions_extend_burecommendations(): array {
    return [
        \theme_dimensions\recommendation\profileveto::class,
        \theme_dimensions\recommendation\unlistedcourses::class,
        \theme_dimensions\recommendation\customfieldpicture::class,
        \theme_dimensions\recommendation\landingaddresses::class,
        \theme_dimensions\recommendation\lookcohorts_hidden::class,
    ];
}

/**
 * The course's own institutional crests, for another plugin's course cards: at most three per course, in grid order.
 *
 * block_compass asks for them through its own hook, \block_compass\hook\course_badges, which this theme answers in
 * {@see \theme_dimensions\local\hook\block_compass\course_badges::answer()} by calling this function with the hook's ids;
 * no core callback looks it up by name any more. It stays as the hook's answer and the one place its viewer filter
 * lives. The answer is what the category showcase's own card draws
 * ({@see \theme_dimensions\output\category_card::crests()}): the badges of the course's landing page, named through the
 * theme's file route. A course with no landing page model gets none, even where badge files survive a model that was
 * cleared.
 *
 * Only for courses the route would serve the current reader
 * ({@see \theme_dimensions\local\landing\publicfiles::viewer_may_see_many()}): a crest's address and
 * its alternative text name the file, so a course the reader may not discover - hidden, unlisted, or in a listing
 * they cannot view and are not enrolled in - gets none, whatever the caller passed. A visitor gets none.
 *
 * One statement for the courses and their contexts, one batch of local_unlistedcourses' course states, the
 * category contexts of the courses' categories, then landingfields::values_many() over the courses kept, which
 * reads the field data in one statement and each uploaded badge file through customfield_picture, one lookup per
 * badge row, its own per-row cost.
 *
 * @param int[] $courseids Course ids.
 * @return array courseid => list of ['url' => string, 'alt' => plain string], for every positive id given; empty for
 *         a course with no model, no badge, no row, or one the reader may not see.
 */
function theme_dimensions_course_badges(array $courseids): array {
    global $DB;

    $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids), static fn(int $id): bool => $id > 0)));
    $badges = array_fill_keys($courseids, []);
    if (!$courseids) {
        return $badges;
    }
    [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
    $ctxselect = \core\context_helper::get_preload_record_columns_sql('ctx');
    // Rides the primary key and the context's instance index; the contexts arrive with the rows, so values_many() pays none.
    $courses = $DB->get_records_sql(
        "SELECT c.id, c.category, c.visible, {$ctxselect}
           FROM {course} c
           JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
          WHERE c.id {$insql}",
        $params + ['contextcourse' => CONTEXT_COURSE]
    );
    foreach ($courses as $course) {
        \core\context_helper::preload_from_record($course);
    }
    $kept = \theme_dimensions\local\landing\publicfiles::viewer_may_see_many($courses);
    $values = \theme_dimensions\local\landingfields::values_many($kept, false);
    foreach ($values as $courseid => $coursevalues) {
        $badges[(int) $courseid] = \theme_dimensions\output\category_card::crests(
            (object) ['id' => (int) $courseid],
            $coursevalues
        );
    }
    return $badges;
}
