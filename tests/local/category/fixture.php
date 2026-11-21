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
 * Theme Dimensions - Shared fixture helpers of the category showcase tests
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\access;
use local_unlistedcourses\category_access;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;

/**
 * What every showcase test needs: states written as admin, caches reset on every user switch, the meter.
 *
 * Not a test case (no _test suffix, so PHPUnit never collects it); the test
 * classes require_once this file and use the trait.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fixture {
    /**
     * Leave no memo behind: PHPUnit resets the sequences, so the next test's ids repeat this one's,
     * and a per-request answer memoised here would answer for a different course there.
     *
     * @return void
     */
    protected function tearDown(): void {
        self::forget();
        parent::tearDown();
    }

    /**
     * Forget local_unlistedcourses' per-request memos and the theme's static hero registry.
     *
     * @return void
     */
    protected static function forget(): void {
        access::reset_caches();
        category_access::reset_caches();
        discoverability::reset_caches();
        category_discoverability::reset_caches();
        // The hero registry is static too: a hero armed for one test's page must not reach the next.
        \theme_dimensions\output\category_hero::reset();
        // So is the visitor's web service's count of calls, which a new request starts without.
        \theme_dimensions\external\get_public_category_rows::reset();
        // And the cards' labels, which a test may change under the same language by writing a pack.
        \theme_dimensions\output\category_card::reset_strings();
    }

    /**
     * Log in as somebody (null for a visitor) and forget every memo keyed by the previous viewer.
     *
     * @param \stdClass|null $user The user, or null for a visitor.
     * @return void
     */
    protected function become(?\stdClass $user): void {
        if ($user === null) {
            $this->setUser(null);
        } else {
            $this->setUser($user);
        }
        self::forget();
    }

    /**
     * Put a course in a discoverability state, as admin, whoever is logged in.
     *
     * @param int $courseid The course.
     * @param int $state A discoverability::STATE_* constant.
     * @return void
     */
    protected function set_course_state(int $courseid, int $state): void {
        $current = $GLOBALS['USER'];
        $this->setAdminUser();
        discoverability::set_state($courseid, $state);
        $this->setUser($current);
        self::forget();
    }

    /**
     * Put a category in a discoverability state, as admin, whoever is logged in.
     *
     * @param int $categoryid The category.
     * @param int $state A category_discoverability::STATE_* constant.
     * @return void
     */
    protected function set_category_state(int $categoryid, int $state): void {
        $current = $GLOBALS['USER'];
        $this->setAdminUser();
        category_discoverability::set_state($categoryid, $state);
        $this->setUser($current);
        self::forget();
    }

    /**
     * A user holding one capability at one context through a fresh role, and nothing else.
     *
     * @param string $capability The capability.
     * @param \core\context $context Where it is held.
     * @return \stdClass The user.
     */
    protected function user_with(string $capability, \core\context $context): \stdClass {
        $user = self::getDataGenerator()->create_user();
        $roleid = self::getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        return $user;
    }

    /**
     * Enable an enrol plugin site-wide.
     *
     * @param string $plugin The plugin name.
     * @return void
     */
    protected function enable_enrol(string $plugin): void {
        \core\plugininfo\enrol::enable_plugin($plugin, true);
    }

    /**
     * Enable a course's existing instance of one method, with extra fields, and return it fresh.
     *
     * @param int $courseid The course.
     * @param string $enrol The plugin name.
     * @param array $fields Extra instance fields to store first.
     * @return \stdClass The {enrol} record.
     */
    protected function enable_instance(int $courseid, string $enrol, array $fields = []): \stdClass {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => $enrol], '*', MUST_EXIST);
        foreach ($fields as $name => $value) {
            $instance->$name = $value;
        }
        $DB->update_record('enrol', $instance);
        enrol_get_plugin($enrol)->update_status($instance, ENROL_INSTANCE_ENABLED);
        return $DB->get_record('enrol', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Give a course an enabled enrol_coursecompleted instance naming its prerequisite, written as the plugin stores it.
     *
     * Written as a row rather than through the plugin, so the classifier's rule is tested on a site without
     * enrol_coursecompleted too: the classifier reads the instance and the site's list of enabled plugins, never
     * the plugin's code. Nothing that asks the plugin itself may run on such an instance while the plugin is absent.
     *
     * @param \stdClass $course The course.
     * @param int $prerequisiteid The course whose completion enrols.
     * @param array $fields Extra instance fields.
     * @return int The instance id.
     */
    protected function completed_instance(\stdClass $course, int $prerequisiteid, array $fields = []): int {
        global $DB, $CFG;

        $enabled = array_filter(explode(',', (string) $CFG->enrol_plugins_enabled));
        if (!in_array('coursecompleted', $enabled, true)) {
            $enabled[] = 'coursecompleted';
            set_config('enrol_plugins_enabled', implode(',', $enabled));
        }
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        return (int) $DB->insert_record('enrol', (object) ($fields + [
            'enrol' => 'coursecompleted',
            'courseid' => (int) $course->id,
            'status' => ENROL_INSTANCE_ENABLED,
            'sortorder' => 9,
            'roleid' => (int) $studentrole->id,
            'customint1' => $prerequisiteid,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]));
    }

    /**
     * Let tool_courserating rate every course, or skip the test where the plugin is not installed.
     *
     * The plugin is optional (the theme asks it by class name) and a CI dependency, so a test that needs it runs in
     * CI and skips cleanly elsewhere. Under PHPUnit its ratingmode setting defaults to RATEBY_NOONE; its generator's
     * set_config() reindexes every course's summary row to the new mode, so the rows are the plugin's own.
     *
     * @return \tool_courserating_generator The plugin's generator, to rate with.
     */
    protected function ratings_on(): \tool_courserating_generator {
        if (!class_exists('tool_courserating\helper')) {
            $this->markTestSkipped('tool_courserating is not installed.');
        }
        $generator = self::getDataGenerator()->get_plugin_generator('tool_courserating');
        $generator->set_config(\tool_courserating\constants::SETTING_RATINGMODE, \tool_courserating\constants::RATEBY_ANYTIME);
        return $generator;
    }

    /**
     * Rate a course through tool_courserating's own API, one new user per rating, so its summary row is the plugin's.
     *
     * @param int $courseid The course.
     * @param int[] $ratings The ratings, 1 to 5.
     * @param int $reviews How many of them, the first ones, carry a review.
     * @return void
     */
    protected function rate(int $courseid, array $ratings, int $reviews = 0): void {
        $generator = self::getDataGenerator();
        $plugin = $generator->get_plugin_generator('tool_courserating');
        foreach (array_values($ratings) as $index => $rating) {
            $plugin->create_rating((int) $generator->create_user()->id, $courseid, $rating, $index < $reviews ? 'Muito bom.' : '');
        }
    }

    /**
     * The course ids of one resolved page, in display order.
     *
     * @param int $categoryid The root.
     * @param array $options Criteria options.
     * @return int[]
     */
    protected function ids(int $categoryid, array $options = []): array {
        return population::resolve(new criteria($categoryid, $options))->ids();
    }

    /**
     * Reads issued by a callable, after the per-request state has been reset the way a new request would.
     *
     * @param callable $fn The code to meter.
     * @return int The reads.
     */
    protected function reads(callable $fn): int {
        global $DB, $FILTERLIB_PRIVATE;
        self::forget();
        \core\context_helper::reset_caches();
        // Core's own per-request filter caches, which a real second request starts without.
        $FILTERLIB_PRIVATE = null;
        \filter_manager::reset_caches();
        $before = (int) $DB->perf_get_reads();
        $fn();
        return (int) $DB->perf_get_reads() - $before;
    }
}
