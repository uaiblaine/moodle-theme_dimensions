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
 * Theme Dimensions - Tests for the category public-access adapter
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for the fail-closed adapter over local_unlistedcourses' public category predicate.
 *
 * Every refusal is asserted beside a category that is served in the same run, so no assertion
 * here can pass against an adapter that answers "no" to everything, which is how a fail-closed
 * guard degrades.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(publicaccess::class)]
final class publicaccess_test extends \advanced_testcase {
    use fixture;

    /**
     * A category in the public state, created and marked as the administrator.
     *
     * @return int The category id.
     */
    private function public_category(): int {
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->set_category_state($categoryid, category_discoverability::STATE_PUBLIC);
        return $categoryid;
    }

    /**
     * Only a category the plugin holds in the public state is served anonymously.
     *
     * @return void
     */
    public function test_only_a_category_in_the_public_state_is_public(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $public = $this->public_category();
        $listed = (int) $generator->create_category()->id;
        $unlisted = (int) $generator->create_category()->id;
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);

        $this->assertTrue(publicaccess::is_public($public), 'Control: the public one is served.');
        $this->assertFalse(publicaccess::is_public($listed), 'A listed category has no public page.');
        $this->assertFalse(publicaccess::is_public($unlisted), 'Nor has an unlisted one.');
    }

    /**
     * An id that is no category's, and a negative one, answer "not public", never an exception.
     *
     * @return void
     */
    public function test_an_id_that_is_no_category_is_never_public(): void {
        global $DB;

        $this->resetAfterTest();
        $public = $this->public_category();
        $this->assertTrue(publicaccess::is_public($public), 'Control: a real public category is served.');

        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $this->assertFalse(publicaccess::is_public(-5), 'An id no category could ever have.');
        $this->assertFalse(publicaccess::is_public($missing), 'And a missing id is an answer, not a throw.');
    }

    /**
     * The root is public only with its setting, the surface, the feature and the predicate class.
     *
     * Each of the four clauses is switched off with the other three left on, so no refusal can
     * pass because something else happened to be off; the case with all four on is the control
     * that the chain can answer yes at all. A real public category is resolved beside them,
     * which tells "the root is closed" apart from an adapter that says no to everything.
     *
     * The default matters most: a site that has never saved the root's setting must keep the
     * root off the internet until an administrator opens it, so the first assertion is made
     * before anything is stored.
     *
     * @return void
     */
    public function test_the_root_is_public_only_with_the_setting_the_surface_the_feature_and_the_predicate(): void {
        $this->resetAfterTest();
        $public = $this->public_category();

        $this->assertFalse(
            publicaccess::root_enabled(),
            'Default off: a site that never visited the tab keeps the root closed.'
        );
        $this->assertFalse(publicaccess::is_public(0), 'And the root is not on the internet.');
        $this->assertTrue(publicaccess::is_public($public), 'Control: the surface itself is open.');

        // All four on: the root answers yes.
        set_config(publicaccess::SETTING_ROOT, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::root_enabled());
        $this->assertTrue(publicaccess::is_public(0), 'The setting, the surface, the feature and the predicate.');

        // Clause 1: the root's own setting, which is read for an explicit '1' and nothing else.
        set_config(publicaccess::SETTING_ROOT, '0', 'theme_dimensions');
        $this->assertFalse(publicaccess::is_public(0), 'A stored 0 closes it.');
        set_config(publicaccess::SETTING_ROOT, 'yes', 'theme_dimensions');
        $this->assertFalse(publicaccess::is_public(0), 'And so does anything that is not the string 1.');
        set_config(publicaccess::SETTING_ROOT, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::is_public(0), 'Control: back on.');

        // Clause 2: the anonymous surface, which closes every public page including this one.
        set_config(publicaccess::SETTING_ENABLED, '0', 'theme_dimensions');
        $this->assertFalse(publicaccess::is_public(0), 'The anonymous surface is switched off.');
        $this->assertFalse(publicaccess::is_public($public), 'Control: and so is every other public page.');
        set_config(publicaccess::SETTING_ENABLED, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::is_public(0), 'Control: back on.');

        // Clause 3: the feature's own kill switch, one level above the surface.
        set_config(population::SETTING_ENABLED, '0', 'theme_dimensions');
        $this->assertFalse(publicaccess::is_public(0), 'The whole showcase is off.');
        set_config(population::SETTING_ENABLED, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::is_public(0), 'Control: back on.');

        /* Clause 4: the predicate class. It is never asked about the root, which holds no row it
           could read, but a missing or broken local_unlistedcourses withholds every child from
           the directory, so a root left open would answer the internet with an empty page
           instead of the refusal every other page gives. */
        $this->assertFalse(
            publicaccess::is_public(0, 'theme_dimensions\\local\\category\\no_such_predicate'),
            'No predicate, no public root.'
        );
        $this->assertTrue(publicaccess::is_public(0), 'Control: the real predicate leaves it open.');
    }

    /**
     * An id that cannot be a category is refused before the predicate is consulted at all.
     *
     * The predicate handed in here exists, so the fail-closed branch is not the one exercised,
     * but it has no is_public(), so reaching it raises an Error. A false answer can therefore
     * only mean the id was refused first. The control is the same call for a real public
     * category, which does reach the predicate and does raise.
     *
     * The root (id 0) is not used here: it is answered by the settings and never reaches the
     * predicate, so its clauses have a test of their own above.
     *
     * @return void
     */
    public function test_an_impossible_id_is_refused_before_the_predicate_is_asked(): void {
        $this->resetAfterTest();
        $public = $this->public_category();
        $predicate = \core\url::class;
        $this->assertTrue(class_exists($predicate), 'Precondition: the class exists, so nothing fails closed.');

        $this->assertFalse(publicaccess::is_public(-5, $predicate));

        // Control: a real id walks on to the predicate, and this one cannot answer.
        $this->expectException(\Error::class);
        publicaccess::is_public($public, $predicate);
    }

    /**
     * The site's switch closes the surface for every category at once, and only an explicit '0' does.
     *
     * @return void
     */
    public function test_the_setting_switches_the_whole_surface_off(): void {
        $this->resetAfterTest();
        $public = $this->public_category();

        $this->assertTrue(publicaccess::enabled(), 'Default on: a site that never visited the tab still serves.');
        $this->assertTrue(publicaccess::is_public($public), 'Control: the surface is open.');

        set_config(publicaccess::SETTING_ENABLED, '0', 'theme_dimensions');
        $this->assertFalse(publicaccess::enabled());
        $this->assertFalse(publicaccess::is_public($public), 'Switched off, the state stays stored but nothing is served.');

        set_config(publicaccess::SETTING_ENABLED, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::is_public($public), 'Control: a stored 1 serves.');

        // The showcase's own kill switch closes this surface too: off, both shells render what
        // the site renders without the showcase.
        set_config(population::SETTING_ENABLED, '0', 'theme_dimensions');
        $this->assertFalse(publicaccess::is_public($public), 'The whole feature is off.');
        set_config(population::SETTING_ENABLED, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::enabled());
        $this->assertTrue(publicaccess::is_public($public), 'And switching it back on serves the same category again.');
    }

    /**
     * Without the predicate class nothing is public: the adapter fails closed.
     *
     * The opposite of {@see \theme_dimensions\local\hotsite\redirector::is_category_ghosted()},
     * on purpose. The control is the same category through the real predicate, which says yes.
     *
     * @return void
     */
    public function test_a_missing_predicate_fails_closed(): void {
        $this->resetAfterTest();
        $public = $this->public_category();
        $this->assertTrue(publicaccess::is_public($public), 'Control: the real predicate says public.');

        $this->assertFalse(
            publicaccess::is_public($public, 'theme_dimensions\\local\\category\\no_such_predicate'),
            'A missing predicate must read as not public.'
        );
    }

    /**
     * The visitor's web service has a switch of its own, off until a stored '1', and it never closes a page.
     *
     * It sits beside is_public() and not inside it: is_public() is the gate of the public page, the
     * forward, the image route and the head tags, so a switch read inside it would take every public
     * page off the internet on a site that never opened the service - which is every site, by default.
     * The public category and the root are asked under each value of the switch; the root's own
     * setting is the control that is_public() can still say no.
     *
     * @return void
     */
    public function test_the_web_service_switch_is_off_until_opened_and_never_closes_a_public_page(): void {
        $this->resetAfterTest();
        $public = $this->public_category();

        $this->assertFalse(publicaccess::client_enabled(), 'Default off: a site that never saved it keeps the service closed.');
        $this->assertTrue(publicaccess::is_public($public), 'And the public page is served all the same.');
        foreach (['0', 'yes', ''] as $stored) {
            set_config(publicaccess::SETTING_CLIENT, $stored, 'theme_dimensions');
            $this->assertFalse(publicaccess::client_enabled(), "A stored '{$stored}' keeps it closed.");
            $this->assertTrue(publicaccess::is_public($public), "And the page served with '{$stored}'.");
        }
        set_config(publicaccess::SETTING_CLIENT, '1', 'theme_dimensions');
        $this->assertTrue(publicaccess::client_enabled(), 'A stored 1 opens it.');
        $this->assertTrue(publicaccess::is_public($public));
        $this->assertFalse(publicaccess::is_public(0), 'Control: the root stays closed - the service opens no page.');
    }
}
