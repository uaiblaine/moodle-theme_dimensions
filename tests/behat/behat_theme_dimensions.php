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

// NOTE: no MOODLE_INTERNAL check here on purpose - behat includes context files
// before config.php exists, so the guard's die() would silently kill the whole
// behat run for every plugin on the site.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Theme Dimensions - Behat page resolvers
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_theme_dimensions extends behat_base {
    /**
     * Convert page names to URLs for steps like 'When I am on the "identifier" "type" page'.
     *
     * Recognised page types, with the identifier each one expects:
     * - 'Enrolment landing page' (course shortname): the theme's landing page.
     * - 'Landing page short address' (course shortname): the page's public address, the short one on a
     *   site whose router is configured and the page's own elsewhere, minted here if need be.
     * - 'Legacy landing page address' (course shortname): the script, landing.php?id=N, whatever the
     *   router's state; on a site whose router is configured it redirects to the route.
     * - 'Routed landing page address' (course shortname): the route spelled through r.php, which every
     *   web server answers, rewrite or not - the way to reach the controller without the rewrite.
     * - 'Standard enrolment page' (course shortname): core's enrol/index.php, to exercise the
     *   forwarding.
     * - 'Landing page guest entry' (course shortname): the landing page asking for the guest entry
     *   (links::guest_page()), the route or the script as links::page() is.
     * - 'Activity with focus mode off' (activity name): a mod_page view carrying the support
     *   parameter, which is the only way a scenario can reach a URL built from a course module
     *   id it does not know.
     * - 'Category showcase' (category idnumber, optionally followed by a bar and a query string,
     *   e.g. "TESTCAT|perpage=1"): the category page with the showcase's own parameters, which is
     *   how a scenario reaches a second page without seeding two dozen courses.
     * - 'Public category page' (category idnumber, optionally followed by a bar and a query
     *   string, e.g. "PUBCAT|q=Resgate"): the anonymous shell of the showcase, the route on a
     *   site whose router is configured and the script elsewhere - exact either way.
     * - 'Category appearance' (category idnumber): the editor of the category's hero.
     *
     * @param string $type The page type.
     * @param string $identifier The page identifier.
     * @return moodle_url The page URL.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'enrolment landing page':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return \theme_dimensions\local\landing\links::page($courseid);
            case 'landing page short address':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return \theme_dimensions\local\landing\links::share((int) $courseid);
            case 'legacy landing page address':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return \theme_dimensions\local\landing\links::legacy((int) $courseid);
            case 'routed landing page address':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/r.php/theme_dimensions/landing/' . (int) $courseid);
            case 'landing page guest entry':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return \theme_dimensions\local\landing\links::guest_page((int) $courseid);
            case 'standard enrolment page':
                $courseid = $DB->get_field('course', 'id', ['shortname' => $identifier], MUST_EXIST);
                return new moodle_url('/enrol/index.php', ['id' => $courseid]);
            case 'category showcase':
                [$idnumber, $query] = array_pad(explode('|', $identifier, 2), 2, '');
                $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
                $url = new moodle_url('/course/index.php', ['categoryid' => $categoryid]);
                parse_str($query, $params);
                foreach ($params as $name => $value) {
                    $url->param($name, $value);
                }
                return $url;
            case 'public category page':
                [$idnumber, $query] = array_pad(explode('|', $identifier, 2), 2, '');
                $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
                parse_str($query, $params);
                return \theme_dimensions\local\landing\links::category_page((int) $categoryid, $params);
            case 'category appearance':
                $categoryid = $DB->get_field('course_categories', 'id', ['idnumber' => $identifier], MUST_EXIST);
                return new moodle_url('/theme/dimensions/categoryhero.php', ['id' => $categoryid]);
            case 'activity with focus mode off':
                $cm = $this->get_cm_by_activity_name('page', $identifier);
                return new moodle_url('/mod/page/view.php', [
                    'id' => $cm->id,
                    \theme_dimensions\local\focusmode::PARAM_OFF => 1,
                ]);
            default:
                throw new Exception('Unrecognised theme_dimensions page type "' . $type . '"');
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "type" page'.
     *
     * One page has no instance to name: the root of the course index, which is the
     * pseudo-category 0 and has no row an idnumber could find.
     *
     * - 'Public category root': the anonymous directory of the top-level categories, the route on
     *   a site whose router is configured and the script elsewhere - exact either way.
     *
     * @param string $page The page name.
     * @return moodle_url The page URL.
     * @throws Exception For a page name this theme does not answer for.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'public category root':
                return \theme_dimensions\local\landing\links::category_page(0);
            default:
                throw new Exception('Unrecognised theme_dimensions page type "' . $page . '"');
        }
    }

    /**
     * Put a course into a discoverability state, as the site administrator.
     *
     * The state lives in local_unlistedcourses and is written through its
     * set_state(), so the publish capability is checked there against the
     * admin - the same path a manager takes through the course settings form.
     *
     * @Given /^the course "(?P<shortname>(?:[^"]|\\")*)" is "(?P<state>listed|unlisted|public)" for discoverability$/
     * @param string $shortname The course shortname.
     * @param string $state One of listed, unlisted, public.
     * @return void
     */
    public function the_course_is_for_discoverability(string $shortname, string $state): void {
        global $DB;

        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $states = [
            'listed' => \local_unlistedcourses\discoverability::STATE_DEFAULT,
            'unlisted' => \local_unlistedcourses\discoverability::STATE_UNLISTED,
            'public' => \local_unlistedcourses\discoverability::STATE_PUBLIC,
        ];
        \local_unlistedcourses\discoverability::set_state($courseid, $states[$state], (int) get_admin()->id);
    }

    /**
     * Assert that the page shows the public address of a course's landing page, as text.
     *
     * The expected address is built by the code the course form uses, so on a site whose
     * router is configured it is the short one and elsewhere the page's own: exact either way.
     *
     * @Then /^I should see the landing page address of course "(?P<shortname>(?:[^"]|\\")*)"$/
     * @param string $shortname The course shortname.
     * @return void
     */
    public function i_should_see_the_landing_page_address_of_course(string $shortname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $address = \theme_dimensions\local\landing\links::share($courseid)->out(false);
        $this->execute('behat_general::assert_page_contains_text', [$address]);
    }

    /**
     * Assert that the page does not show the public address of a course's landing page.
     *
     * The address is spelled as the positive step spells it; run this after an editor's visit has
     * minted the code, or the step would mint it from the CLI process itself.
     *
     * @Then /^I should not see the landing page address of course "(?P<shortname>(?:[^"]|\\")*)"$/
     * @param string $shortname The course shortname.
     * @return void
     */
    public function i_should_not_see_the_landing_page_address_of_course(string $shortname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $address = \theme_dimensions\local\landing\links::share($courseid)->out(false);
        $this->execute('behat_general::assert_page_not_contains_text', [$address]);
    }

    /**
     * Assert that the browser ended up on the canonical address of a course's landing page.
     *
     * The expected address is links::page(), the route on a site whose router is configured
     * and the script elsewhere - so the step proves the script's redirect where the router is
     * configured and still holds where it is not.
     *
     * @Then /^the browser is on the landing page of course "(?P<shortname>(?:[^"]|\\")*)"$/
     * @param string $shortname The course shortname.
     * @return void
     */
    public function the_browser_is_on_the_landing_page_of_course(string $shortname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $expected = \theme_dimensions\local\landing\links::page($courseid)->out(false);
        $actual = $this->getSession()->getCurrentUrl();
        if ($actual !== $expected) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The browser is on \"$actual\", not on the landing page \"$expected\".",
                $this->getSession()
            );
        }
    }

    /**
     * Give a course a course image: a JPEG made by GD, stored as an editor's upload would be.
     *
     * @Given /^the course "(?P<shortname>(?:[^"]|\\")*)" has a course image$/
     * @param string $shortname The course shortname.
     * @return void
     */
    public function the_course_has_a_course_image(string $shortname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $image = imagecreatetruecolor(1600, 900);
        imagefill($image, 0, 0, imagecolorallocate($image, 7, 0, 147));
        ob_start();
        imagejpeg($image);
        imagedestroy($image);
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($courseid)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.jpg',
        ], (string) ob_get_clean());
    }

    /**
     * Give a category its first crest: a PNG made by GD, stored as the category's branding form would.
     *
     * @Given /^the category "(?P<idnumber>(?:[^"]|\\")*)" has a crest$/
     * @param string $idnumber The category idnumber.
     * @return void
     */
    public function the_category_has_a_crest(string $idnumber): void {
        global $DB;
        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        $image = imagecreatetruecolor(80, 88);
        imagefill($image, 0, 0, imagecolorallocate($image, 7, 0, 147));
        ob_start();
        imagepng($image);
        imagedestroy($image);
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($categoryid)->id,
            'component' => 'theme_dimensions',
            'filearea' => \theme_dimensions\local\category\branding::AREA_BADGES[0],
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'brasao.png',
        ], (string) ob_get_clean());
    }

    /**
     * Tap a control with a touch pointer, in the order a browser really delivers one.
     *
     * Selenium drives a mouse, so no click step produces what a finger does. A touch pointer has
     * no hover state: the browser reports a pointerover as the finger touches down and a pointerout
     * as it lifts, a fraction of a second before the click, so code that reads a pointerout as "the
     * pointer left" acts on it before the press arrives. That ordering cannot be asserted from a
     * module's source; it has to be delivered.
     *
     * So the step dispatches pointerover, pointerdown, pointerup and pointerout, then a click, on
     * the node Behat resolved, with pointerType "touch" - the property such guards read. A
     * dispatched click carries no user activation, so a browser would refuse to play a film with
     * sound after one; every start of the cover film is muted, so nothing here needs it.
     *
     * The node is deliberately not put through ensure_node_is_visible(): the cover film's controls
     * are revealed by hover or keyboard focus, which a finger brings neither of, so to Selenium they
     * sit at opacity 0 and that helper refuses them. The node is scrolled into view instead, which
     * the film needs anyway: the module stops a film whose card leaves the screen.
     *
     * @Given /^I tap "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" as a touch pointer$/
     * @param string $element The element locator.
     * @param string $selectortype The type of selector the locator is for.
     * @return void
     */
    public function i_tap_as_a_touch_pointer(string $element, string $selectortype): void {
        $this->require_javascript();
        $node = $this->get_selected_node($selectortype, $element);
        $script = 'var node = {{ELEMENT}};'
            . ' node.scrollIntoView({block: "center"});'
            . ' var options = {bubbles: true, cancelable: true, pointerType: "touch", isPrimary: true};'
            . ' ["pointerover", "pointerdown", "pointerup", "pointerout"].forEach(function(type) {'
            . ' node.dispatchEvent(new PointerEvent(type, options)); });'
            . ' node.dispatchEvent(new MouseEvent("click", {bubbles: true, cancelable: true}));';
        $this->execute_js_on_node($node, $script);
    }

    /**
     * Leave a mark on the window of the page in the browser, which any page load takes away.
     *
     * A change made in place and one made by loading the page can draw the same markup: the
     * visitor's panel opened by its module and the panel the server draws for the same address are
     * one template. The mark tells them apart, since it survives everything a page does in place.
     *
     * @Given /^I mark the page in the browser$/
     * @return void
     */
    public function i_mark_the_page_in_the_browser(): void {
        $this->require_javascript();
        $this->execute_script('window.dimBehatMarked = true;');
    }

    /**
     * Check whether the page in the browser still carries the mark, that is whether no page was loaded since.
     *
     * @Then /^the page in the browser is( not)? the one I marked$/
     * @param string $not ' not' when a page must have been loaded since the mark was left.
     * @return void
     * @throws \Behat\Mink\Exception\ExpectationException When the page says otherwise.
     */
    public function the_page_in_the_browser_is_the_one_i_marked(string $not = ''): void {
        $this->require_javascript();
        $marked = (bool) $this->evaluate_script('return window.dimBehatMarked === true;');
        if ($marked === ($not !== '')) {
            throw new \Behat\Mink\Exception\ExpectationException(
                $marked ? 'No page was loaded since the mark was left.' : 'A page was loaded since the mark was left.',
                $this->getSession()
            );
        }
    }

    /**
     * Put the keyboard focus on a day button of the calendar page's grid, where Tab lands on the selected day.
     *
     * Core has no step that focuses an element without pressing it, and pressing a day opens core's day view; the
     * grid's keyboard is then driven with core's "I press the ... key".
     *
     * @Given /^I focus the calendar day "(?P<day>\d{4}-\d{2}-\d{2})"$/
     * @param string $day The day, YYYY-MM-DD.
     * @return void
     */
    public function i_focus_the_calendar_day(string $day): void {
        $this->require_javascript();
        $this->execute_script("document.querySelector('[data-role=\"day\"][data-day=\"{$day}\"]').focus();");
    }

    /**
     * Switch focus mode's master switch on, with the stylesheet that goes with it.
     *
     * The switch ships off, and while it is off lib.php compiles no focus mode stylesheet; the Behat
     * site's CSS is built once at init time and put back after every scenario
     * (behat_util::restore_saved_themes()), so a scenario that sets the switch alone would test the
     * two skins with no rule telling them apart. Under JavaScript this step therefore compiles this
     * theme's CSS again, with the switch on, into the theme's CSS cache, which the scenario's pages are
     * served from and the reset afterwards replaces. Without JavaScript nothing reads the CSS, and the
     * compilation is skipped.
     *
     * @Given /^focus mode is switched on for the site$/
     * @return void
     */
    public function focus_mode_is_switched_on_for_the_site(): void {
        global $CFG;

        set_config('focusmode', \theme_dimensions\local\login::SELECT_YES, 'theme_dimensions');
        if (!$this->running_javascript()) {
            return;
        }
        require_once($CFG->libdir . '/outputlib.php');
        theme_build_css_for_themes([\core\output\theme_config::load('dimensions')], ['ltr'], true);
    }

    /**
     * Put the focus mode switch of a course into a state, as an administrator would.
     *
     * A direct write through core's own data controller rather than through the course settings
     * form: the row shape is core's either way, and the form path would need the scenario's own
     * user to hold the capability to edit a field which is deliberately invisible.
     *
     * @Given /^the course "(?P<shortname>(?:[^"]|\\")*)" has focus mode "(?P<state>on|off)"$/
     * @param string $shortname The course shortname.
     * @param string $state Either on or off.
     * @return void
     */
    public function the_course_has_focus_mode(string $shortname, string $state): void {
        global $DB;

        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);

        \theme_dimensions\local\focusmodefields::ensure_fields();
        \theme_dimensions\local\focusmodefields::reset_field_cache();
        $field = \theme_dimensions\local\focusmodefields::get_field();
        if (!$field) {
            throw new Exception('The focus mode course custom field is not provisioned on this site.');
        }

        $value = ($state === 'on') ? 1 : 0;
        $fieldid = (int) $field->get('id');
        $existing = $DB->get_record('customfield_data', ['fieldid' => $fieldid, 'instanceid' => $courseid]);
        if ($existing) {
            $data = \core_customfield\data_controller::create((int) $existing->id, null, $field);
        } else {
            $data = \core_customfield\data_controller::create(0, (object) [
                'instanceid' => $courseid,
                'fieldid' => $fieldid,
            ], $field);
        }
        $data->set('intvalue', $value);
        $data->set('value', (string) $value);
        $data->set('contextid', \core\context\course::instance($courseid)->id);
        $data->save();

        \theme_dimensions\local\focusmode::reset_caches();
    }

    /**
     * Show the navbar's context layer, as amd/src/readingbar.js does once the page header has scrolled away.
     *
     * The Behat site never swaps the bar with the scroll (readingbar::client_config() hands the module its flag): a
     * scenario that scrolled would find the global bar's controls out of reach. So this step writes the one class
     * the module's setContext() writes, on the main navbar, and waits for the fade, after which Selenium sees the
     * layer as displayed. On the Behat site the course index control keeps core's toggler attribute throughout.
     *
     * @Given /^I show the context bar of the navbar$/
     * @return void
     * @throws \Behat\Mink\Exception\ExpectationException When the page has no context layer to show.
     */
    public function i_show_the_context_bar_of_the_navbar(): void {
        $this->require_javascript();
        $layer = 'document.querySelector(\'nav.navbar.fixed-top:not(.boost-union-menubar) [data-region="dimensions-ctxbar"]\')';
        if (!$this->evaluate_script("return {$layer} !== null;")) {
            throw new \Behat\Mink\Exception\ExpectationException('The navbar has no context layer.', $this->getSession());
        }
        // Not the focus bar, which a page that can switch focus mode in place prints before the navbar.
        $navbar = "document.querySelector('nav.navbar.fixed-top:not(.boost-union-menubar):not(.dimensions-focusbar)')";
        $this->execute_script("{$navbar}.classList.add('dimensions-ctx-on');");
        $this->getSession()->wait(self::get_timeout() * 1000, "window.getComputedStyle({$layer}).opacity === '1'");
    }

    /**
     * Assert that the drawer's current panel, once its slide has ended, sits where the drawer's viewport shows it.
     *
     * The viewport clips the panels with overflow hidden, which still makes it a scroll container: a scrolling focus
     * into a panel still sliding in scrolls it, and the panel then stays drawn to the left of it for as long as another
     * panel keeps the viewport wider than itself. So this reads the boxes after the slide, and the scroll offset.
     *
     * @Then /^the current panel of the drawer is in view$/
     * @return void
     * @throws \Behat\Mink\Exception\ExpectationException When the panel is drawn off to the side.
     */
    public function the_current_panel_of_the_drawer_is_in_view(): void {
        $this->require_javascript();
        $panel = "document.querySelector('[data-region=\"dimensions-drawer-panel\"][data-state=\"current\"]')";
        $this->getSession()->wait(
            self::get_timeout() * 1000,
            "{$panel} !== null && {$panel}.getAnimations().length === 0"
        );
        $state = $this->evaluate_script("return (function() {
            var panel = {$panel};
            var viewport = panel.closest('.dimensions-drawer-viewport');
            return {
                scroll: Math.round(viewport.scrollLeft),
                offset: Math.round(panel.getBoundingClientRect().left - viewport.getBoundingClientRect().left)
            };
        })();");
        if ((int) $state['scroll'] !== 0 || (int) $state['offset'] !== 0) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The drawer's viewport is scrolled by {$state['scroll']}px and its current panel drawn "
                    . "{$state['offset']}px from its edge.",
                $this->getSession()
            );
        }
    }

    /**
     * Give the site's primary navigation a number of custom menu items, one per line of core's custommenuitems.
     *
     * The setting is a multi-line text, which a step table cannot carry. Each item is "Catalogue item N" and links to
     * the course index.
     *
     * @Given /^the site navigation has "(?P<count>\d+)" custom menu items$/
     * @param string $count How many items.
     * @return void
     */
    public function the_site_navigation_has_custom_menu_items(string $count): void {
        $lines = [];
        for ($i = 1; $i <= (int) $count; $i++) {
            $lines[] = "Catalogue item {$i}|/course/index.php?item={$i}";
        }
        set_config('custommenuitems', implode("\n", $lines));
    }

    /**
     * Offer these Boost Union flavours, by title, as the looks a person may choose.
     *
     * The setting stores ids, which a feature cannot know, so this step looks them up.
     *
     * @Given /^the Boost Union flavours "(?P<titles>(?:[^"]|\\")*)" are offered as looks$/
     * @param string $titles Comma-separated flavour titles.
     * @return void
     */
    public function the_boost_union_flavours_are_offered_as_looks(string $titles): void {
        global $DB;

        $ids = [];
        foreach (array_map('trim', explode(',', $titles)) as $title) {
            $ids[] = (int) $DB->get_field('theme_boost_union_flavours', 'id', ['title' => $title], MUST_EXIST);
        }
        set_config(\theme_dimensions\local\flavourchoice::SETTING, implode(',', $ids), 'theme_dimensions');
    }

    /**
     * Assert which Boost Union flavour the page is drawn in, by title, or that it is drawn in none.
     *
     * Read from the body's flavour-<id> class, which Boost Union writes from the flavour its resolver chose.
     *
     * @Then /^the page is drawn in the Boost Union flavour "(?P<title>(?:[^"]|\\")*)"$/
     * @param string $title The flavour's title, or "none".
     * @return void
     */
    public function the_page_is_drawn_in_the_boost_union_flavour(string $title): void {
        global $DB;

        $classes = (string) $this->find('css', 'body')->getAttribute('class');
        preg_match_all('~\\bflavour-(\\d+)\\b~', $classes, $matches);
        $expected = $title === 'none' ? [] :
            [(string) $DB->get_field('theme_boost_union_flavours', 'id', ['title' => $title], MUST_EXIST)];
        if ($matches[1] !== $expected) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "The page is drawn in flavour(s) [" . implode(', ', $matches[1]) . "], not in \"{$title}\".",
                $this->getSession()
            );
        }
    }
}
