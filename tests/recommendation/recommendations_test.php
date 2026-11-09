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
 * Theme Dimensions - Tests for the Boost Union recommendations
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\recommendation;

use core\plugininfo\customfield;
use core_course\customfield\course_handler;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_boost_union\recommendation\manager;
use theme_boost_union\recommendation\recommendation;
use theme_dimensions\local\landingfields;

/**
 * Tests for the recommendations this theme adds to Boost Union's overview.
 *
 * Every status is asserted in both of its states, because a check that always answers OK passes
 * the half that expects OK.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(base::class)]
#[CoversClass(profileveto::class)]
#[CoversClass(unlistedcourses::class)]
#[CoversClass(customfieldpicture::class)]
#[CoversClass(landingaddresses::class)]
#[CoversClass(lookcohorts_hidden::class)]
#[CoversClass(relatedshortcode::class)]
final class recommendations_test extends \advanced_testcase {
    /**
     * Provision the theme's course fields, as an installed site has them.
     *
     * @return void
     */
    private function provision(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
    }

    /**
     * The callback names exactly the six classes, each one a recommendation with a prefixed id and title.
     *
     * @return void
     */
    public function test_the_callback_returns_the_theme_recommendations_with_unique_prefixed_ids(): void {
        $this->resetAfterTest();
        $classes = theme_dimensions_extend_burecommendations();

        $this->assertEqualsCanonicalizing(
            [
                profileveto::class,
                unlistedcourses::class,
                customfieldpicture::class,
                landingaddresses::class,
                lookcohorts_hidden::class,
                relatedshortcode::class,
            ],
            $classes
        );
        $ids = [];
        foreach ($classes as $classname) {
            $instance = new $classname();
            $this->assertInstanceOf(recommendation::class, $instance);
            $this->assertStringStartsWith(base::ID_PREFIX, $instance->get_id());
            $this->assertStringStartsWith('Dimensions', $instance->get_title(), 'The title groups the item by name.');
            $this->assertNotSame('', $instance->get_summary());
            $this->assertNotSame('', $instance->get_description());
            $this->assertArrayHasKey($instance->get_category(), manager::get_supported_categories());
            $ids[] = $instance->get_id();
        }
        $this->assertSame($ids, array_values(array_unique($ids)), 'Every id is unique.');
    }

    /**
     * Boost Union's manager finds all six through the plugin callback.
     *
     * @return void
     */
    public function test_the_manager_lists_the_theme_recommendations(): void {
        global $CFG;
        $this->provision();
        unset($CFG->routerconfigured);
        purge_all_caches();

        $listed = [];
        foreach (manager::get_recommendations() as $recommendation) {
            if (str_starts_with($recommendation->get_id(), base::ID_PREFIX)) {
                $listed[$recommendation->get_id()] = $recommendation->get_status();
            }
        }

        $this->assertEquals(
            [
                // Recommended, not required: a notice on a site without customfield_picture.
                'dimensions_customfieldpicture' => landingfields::picture_type_available()
                    ? recommendation::OK
                    : recommendation::NOTICE,
                'dimensions_landingaddresses' => recommendation::NOTICE,
                'dimensions_lookcohorts' => recommendation::OK,
                'dimensions_profileveto' => recommendation::OK,
                // A notice until filter_shortcodes is installed and on, which a fresh test site leaves to the admin.
                'dimensions_relatedshortcode' => self::shortcodes_on() ? recommendation::OK : recommendation::NOTICE,
                'dimensions_unlistedcourses' => recommendation::OK,
            ],
            $listed
        );
        $this->assertNotNull(manager::get_recommendation_by_id('dimensions_profileveto'));
    }

    /**
     * The profile rule is fine while core requires a login for profiles, and a warning once it does not.
     *
     * An unset setting reads as on, because core's default is on and a fresh site has not stored it.
     *
     * @return void
     */
    public function test_the_profile_rule_warns_only_while_core_opens_every_profile(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->forceloginforprofiles = 1;
        $on = new profileveto();
        $this->assertSame(recommendation::OK, $on->get_status());
        unset($CFG->forceloginforprofiles);
        $unset = new profileveto();
        $this->assertSame(recommendation::OK, $unset->get_status(), 'Unset reads as on.');
        $CFG->forceloginforprofiles = 0;
        $off = new profileveto();
        $this->assertSame(recommendation::WARNING, $off->get_status());
        $this->assertNotSame($on->get_summary(), $off->get_summary());
        $this->assertSame(recommendation::CATEGORY_MOODLECORE, $off->get_category());
        $this->assertStringContainsString('sitepolicies', $off->get_action_url()->out(false));
    }

    /**
     * Whether filter_shortcodes is installed and on, read the way the site reads it.
     *
     * @return bool
     */
    private static function shortcodes_on(): bool {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');
        return \core_component::get_component_directory('filter_shortcodes') !== null && filter_is_enabled('shortcodes');
    }

    /**
     * The related courses shortcode is fine while filter_shortcodes is on, a notice while it is off, and a notice with
     * no action while the plugin is missing.
     *
     * @return void
     */
    public function test_the_shortcode_notes_a_missing_or_disabled_filter(): void {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');
        $this->resetAfterTest();

        if (\core_component::get_component_directory('filter_shortcodes') === null) {
            $missing = new relatedshortcode();
            $this->assertSame(recommendation::NOTICE, $missing->get_status());
            $this->assertNull($missing->get_action_url());
            $this->markTestSkipped('filter_shortcodes is not installed: its on and off states cannot be set.');
        }

        filter_set_global_state('shortcodes', TEXTFILTER_ON);
        // Core keeps the enabled filters in a request cache that setting a state leaves as it was.
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'core_filter', 'global_filters')->purge();
        $on = new relatedshortcode();
        $this->assertSame(recommendation::OK, $on->get_status());

        filter_set_global_state('shortcodes', TEXTFILTER_DISABLED);
        // Core keeps the enabled filters in a request cache that setting a state leaves as it was.
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'core_filter', 'global_filters')->purge();
        $off = new relatedshortcode();
        $this->assertSame(recommendation::NOTICE, $off->get_status());
        $this->assertNotSame($on->get_summary(), $off->get_summary());
        $this->assertStringContainsString('/admin/filters.php', $off->get_action_url()->out(false));
        $this->assertSame(recommendation::CATEGORY_THIRDPARTY, $off->get_category());
    }

    /**
     * The landing page addresses are fine while the router is configured, and a notice while it is not.
     *
     * @return void
     */
    public function test_the_landing_addresses_note_an_unconfigured_router(): void {
        global $CFG;
        $this->resetAfterTest();

        $CFG->routerconfigured = true;
        $on = new landingaddresses();
        $this->assertSame(recommendation::OK, $on->get_status());

        unset($CFG->routerconfigured);
        $off = new landingaddresses();
        $this->assertSame(recommendation::NOTICE, $off->get_status());
        $this->assertNotSame($on->get_summary(), $off->get_summary());
        $this->assertSame(recommendation::CATEGORY_MOODLECORE, $off->get_category());
        $this->assertStringContainsString('/admin/environment.php', $off->get_action_url()->out(false));
    }

    /**
     * The discoverability plugin is fine when upgraded, and a warning while its database is behind its code.
     *
     * @return void
     */
    public function test_the_discoverability_plugin_warns_when_it_is_not_upgraded(): void {
        $this->resetAfterTest();
        $this->assertSame(recommendation::OK, (new unlistedcourses())->get_status());

        $version = (int) get_config('local_unlistedcourses', 'version');
        $this->assertGreaterThan(0, $version, 'Precondition: the plugin is installed.');
        set_config('version', $version - 1, 'local_unlistedcourses');
        \core_plugin_manager::reset_caches();

        $behind = new unlistedcourses();
        $this->assertSame(recommendation::WARNING, $behind->get_status());
        $this->assertNotSame('', $behind->get_summary());
        $this->assertSame(recommendation::CATEGORY_THIRDPARTY, $behind->get_category());
    }

    /**
     * Skip a test that needs customfield_picture where the optional plugin is not installed and enabled.
     *
     * @return void
     */
    private function require_picture_type(): void {
        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
    }

    /**
     * The picture type is recommended, not required: fine when it is there, a notice when it is not.
     *
     * A disabled type stands in for an absent one, which the suite cannot uninstall: both make
     * landingfields::picture_type_available() answer false, and neither may read as a warning or offer
     * a fix that would create the five picture fields.
     *
     * @return void
     */
    public function test_the_picture_type_is_a_notice_when_it_is_unavailable(): void {
        $this->provision();
        $this->require_picture_type();

        $this->assertSame(recommendation::OK, (new customfieldpicture())->get_status());

        customfield::enable_plugin(landingfields::PICTURE_TYPE, 0);
        $disabled = new customfieldpicture();
        $this->assertSame(recommendation::NOTICE, $disabled->get_status(), 'Recommended, not required.');
        $this->assertSame(
            get_string('recommendation_customfieldpicture_summary_type', 'theme_dimensions'),
            $disabled->get_summary(),
            'No picture field is reported missing while its type is unavailable.'
        );
        $this->assertFalse($disabled->supports_autofix(), 'Enabling a field type is the administrator\'s decision.');
        $this->assertStringContainsString('managecustomfields', $disabled->get_action_url()->out(false));

        customfield::enable_plugin(landingfields::PICTURE_TYPE, 1);
        $this->assertSame(recommendation::OK, (new customfieldpicture())->get_status(), 'Control: enabled again.');
    }

    /**
     * Without the picture type, a missing field of another type is still a warning, and the fix creates it alone.
     *
     * @return void
     */
    public function test_a_missing_field_warns_without_the_picture_type_and_the_fix_skips_the_pictures(): void {
        $this->provision();
        $this->require_picture_type();
        customfield::enable_plugin(landingfields::PICTURE_TYPE, 0);
        $field = landingfields::get_field(landingfields::SHORTNAME_PROGRAMME);
        $this->assertNotNull($field, 'Precondition: the field exists.');
        $field->delete();
        course_handler::reset_caches();

        $missing = new customfieldpicture();
        $this->assertSame(recommendation::WARNING, $missing->get_status());
        $this->assertSame(
            get_string('recommendation_customfieldpicture_summary_missing', 'theme_dimensions', landingfields::SHORTNAME_PROGRAMME),
            $missing->get_summary(),
            'The one missing field is named, and none of the picture fields core hides.'
        );
        $this->assertTrue($missing->supports_autofix());

        $missing->autofix();

        $this->assertSame(recommendation::NOTICE, $missing->get_status(), 'Fixed, and the type is still a notice.');
        landingfields::reset_field_cache();
        $this->assertNotNull(landingfields::get_field(landingfields::SHORTNAME_PROGRAMME));
        $this->assertDebuggingNotCalled('The fix tried to create no picture field over the hidden ones.');
        customfield::enable_plugin(landingfields::PICTURE_TYPE, 1);
    }

    /**
     * A deleted field is a warning, and the automatic fix creates it again.
     *
     * @return void
     */
    public function test_the_picture_fields_warn_when_a_field_is_missing_and_the_fix_creates_it(): void {
        $this->provision();
        $this->require_picture_type();
        $field = landingfields::get_field(landingfields::SHORTNAME_CREST1);
        $this->assertNotNull($field, 'Precondition: the field exists.');
        $field->delete();
        course_handler::reset_caches();

        $missing = new customfieldpicture();
        $this->assertSame(recommendation::WARNING, $missing->get_status());
        $this->assertStringContainsString(landingfields::SHORTNAME_CREST1, $missing->get_summary());
        $this->assertTrue($missing->supports_autofix());

        $missing->autofix();

        $this->assertSame(recommendation::OK, $missing->get_status(), 'The same instance reads the site again.');
        $this->assertFalse($missing->supports_autofix());
        landingfields::reset_field_cache();
        $this->assertNotNull(landingfields::get_field(landingfields::SHORTNAME_CREST1));
        $this->assertSame(recommendation::OK, (new customfieldpicture())->get_status());
    }
}
