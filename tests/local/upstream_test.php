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
 * Theme Dimensions - Tests for the upstream snapshots of the theme's copies
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Every copy of core or Boost Union code has a snapshot of its upstream, and every snapshot is today's upstream.
 *
 * The second test is the one that reddens on purpose: when core or Boost Union changes a template or a method
 * the theme copies, the copy has to be brought back to parity in the same change that takes the new upstream
 * in. scripts/upstream-merge.sh applies the upstream change to the copy and refreshes the snapshot.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(upstream::class)]
final class upstream_test extends \advanced_testcase {
    /**
     * Each copy has its snapshot on disk, and no snapshot is left without a copy.
     *
     * @return void
     */
    public function test_every_copy_has_a_snapshot_and_every_snapshot_a_copy(): void {
        $expected = array_column(upstream::entries(), 'snapshot');

        $this->assertNotEmpty($expected);
        $this->assertSame($expected, upstream::snapshot_files());
    }

    /**
     * Every snapshot holds, byte for byte, the upstream the page would use today.
     *
     * All the stale ones are named at once, so one run lists the whole re-diff.
     *
     * @return void
     */
    public function test_every_snapshot_is_todays_upstream(): void {
        $stale = [];
        foreach (upstream::entries() as $entry) {
            $snapshot = upstream::root() . '/' . $entry['snapshot'];
            if (!is_readable($snapshot) || file_get_contents($snapshot) !== $entry['text']) {
                $stale[] = "{$entry['copy']} (upstream {$entry['source']})";
            }
        }

        $this->assertSame(
            [],
            $stale,
            'Upstream changed since these copies were last re-diffed: run scripts/upstream-merge.sh, '
                . 'review the merged copies, and commit them with their refreshed snapshots.'
        );
    }

    /**
     * The upstream of a template is the one the parent theme renders: Boost Union's override shadows core's.
     *
     * The control for the resolution: theme_boost/primary-drawer-mobile exists in Boost and in Boost Union, and
     * the theme's copy is Boost Union's.
     *
     * @return void
     */
    public function test_a_template_upstream_is_the_parent_themes_override(): void {
        $entries = array_column(upstream::entries(), null, 'copy');
        $entry = $entries['templates/theme_boost/primary-drawer-mobile.mustache'];

        $this->assertStringEndsWith('/theme/boost_union/templates/theme_boost/primary-drawer-mobile.mustache', $entry['source']);
        $this->assertFileExists(
            \core_component::get_component_directory('theme_boost') . '/templates/primary-drawer-mobile.mustache'
        );
    }

    /**
     * The theme's own templates under a component's name have no upstream, and none of them may gain one unseen.
     *
     * If core or Boost Union ships one of these names, the theme's file becomes an override of it, and has to be
     * re-diffed and snapshotted like the others.
     *
     * @return void
     */
    public function test_the_own_templates_have_no_upstream(): void {
        $parent = \theme_config::load('dimensions')->parents[0];
        foreach (upstream::OWN_TEMPLATES as $name) {
            $this->assertFileExists(upstream::root() . '/templates/' . $name . '.mustache');
            try {
                $found = \core\output\mustache_template_finder::get_template_filepath($name, $parent);
            } catch (\moodle_exception $e) {
                $found = null;
            }
            $this->assertNull($found, "{$name} has an upstream now: take it out of OWN_TEMPLATES and snapshot it.");
        }
    }

    /**
     * The upstream of a method copy is the method it replaces, found through the parent class.
     *
     * The control: Boost Union's course renderer does not override coursecat_subcategories(), so the upstream is
     * core's, two classes up.
     *
     * @return void
     */
    public function test_a_method_upstream_is_the_method_the_copy_replaces(): void {
        $entries = array_column(upstream::entries(), null, 'snapshot');
        $entry = $entries['classes/output/core/course_renderer.php.coursecat_subcategories.upstream'];

        $this->assertSame('core_course_renderer::coursecat_subcategories', $entry['source']);
        $this->assertStringStartsWith('    protected function coursecat_subcategories(', $entry['text']);
        $this->assertStringEndsWith("    }\n", $entry['text']);
    }

    /**
     * The upstream of a function copy is read from its file's tokens, from its signature to its closing brace.
     *
     * Boost Union's flavours/styles.php serves a request from its top level, so it is never included. The control
     * is the function declared right after the one read: its signature is not in the text.
     *
     * @return void
     */
    public function test_a_function_upstream_is_read_from_its_file_without_including_it(): void {
        $entries = array_column(upstream::entries(), null, 'snapshot');
        $entry = $entries['classes/local/flavourcss/builder.php.store.upstream'];

        $this->assertSame(
            'theme/boost_union/flavours/styles.php::theme_boost_union_flavour_styles_generate_and_store',
            $entry['source']
        );
        $this->assertStringStartsWith('function theme_boost_union_flavour_styles_generate_and_store(', $entry['text']);
        $this->assertStringEndsWith("    return \$candidatesheet;\n}\n", $entry['text']);
        $this->assertStringNotContainsString('function theme_boost_union_flavour_styles_fallback_content', $entry['text']);
        $this->assertFalse(function_exists('theme_boost_union_flavour_styles_generate_and_store'));
    }

    /**
     * The upstream of a script copied whole is the script, verbatim, read as text: core's iCal feed, which the theme's
     * calendar answers in its place (classes/local/calendar/ical.php).
     *
     * @return void
     */
    public function test_a_whole_file_upstream_is_the_script_itself(): void {
        global $CFG;

        $entries = array_column(upstream::entries(), null, 'snapshot');
        $entry = $entries['classes/local/calendar/ical.php.export_execute.upstream'];

        $this->assertSame('calendar/export_execute.php', $entry['source']);
        $this->assertSame('classes/local/calendar/ical.php', $entry['copy']);
        $raw = (string) file_get_contents($CFG->dirroot . '/calendar/export_execute.php');
        $this->assertSame(upstream::masked($raw), $entry['text']);
        $this->assertStringNotContainsString('TO' . 'DO', $entry['text'], 'The leftover gate refuses the marker.');
        $this->assertSame(strlen($raw) + substr_count($raw, 'TO' . 'DO'), strlen($entry['text']), 'One byte per marker.');
        $this->assertStringContainsString("define('NO_MOODLE_COOKIES', true);", $entry['text']);
    }

    /**
     * A function the file does not declare is an error, never an empty upstream.
     *
     * @return void
     */
    public function test_a_function_the_file_does_not_declare_is_refused(): void {
        global $CFG;

        $this->expectException(\coding_exception::class);
        upstream::function_source($CFG->dirroot . '/theme/boost_union/flavours/styles.php', 'theme_boost_union_no_such_function');
    }
}
