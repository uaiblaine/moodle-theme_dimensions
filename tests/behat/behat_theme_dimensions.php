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
}
