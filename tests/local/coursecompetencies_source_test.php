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
 * Theme Dimensions - Source tests for the course competencies page's module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins amd/src/coursecompetencies.js by reading its source, as plancards_source_test does: the re-arm point is the
 * template's js block, the Pending token around the refill, the ticket that lets only the latest answer write, the
 * slots keyed without data-id, and markup written only from the theme's own templates. The behaviour itself is the
 * Behat scenarios' (theme_dimensions_course_competencies.feature).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class coursecompetencies_source_test extends \basic_testcase {
    /**
     * A file of the theme.
     *
     * @param string $path The path from the theme's root.
     * @return string
     */
    private function read(string $path): string {
        $file = dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($file);

        return (string) file_get_contents($file);
    }

    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        return $this->read('amd/src/coursecompetencies.js');
    }

    /**
     * The template's js block starts the module with core's course id, beside core's own module: that block is what
     * core runs again after every replace, so it is the re-arm point.
     *
     * @return void
     */
    public function test_the_js_block_starts_the_module(): void {
        $template = $this->read('templates/tool_lp/course_competencies_page.mustache');
        $this->assertMatchesRegularExpression(
            "~\{\{#js\}\}\s*require\(\['tool_lp/competencies'\].*?require\(\['theme_dimensions/coursecompetencies'\], " .
            "function\(Extras\) \{\s*Extras\.init\(\{\{courseid\}\}\);\s*\}\);\s*\{\{/js\}\}~s",
            $template
        );
    }

    /**
     * The refill holds a Pending token from before the request to after the answer is written, released in a finally.
     *
     * @return void
     */
    public function test_the_refill_is_pending_until_written(): void {
        $source = $this->source();
        $pending = strpos($source, "const pending = new Pending('theme_dimensions/coursecompetencies:refill');");
        $request = strpos($source, 'await Ajax.call([{methodname: EXTRAS,');
        $this->assertNotFalse($pending);
        $this->assertNotFalse($request);
        $this->assertGreaterThan($pending, $request, 'The token is taken before the request.');
        $this->assertMatchesRegularExpression(
            '~restore\(current\);\s*\}\s*\} catch \(error\) \{\s*window\.console\.error\(error\);' .
            '\s*\} finally \{\s*pending\.resolve\(\);~s',
            $source
        );
        $this->assertStringContainsString("const EXTRAS = 'theme_dimensions_get_course_competency_extras';", $source);
    }

    /**
     * Only the latest request writes: the ticket is compared after both awaits (the service and the templates), before
     * anything is written.
     *
     * @return void
     */
    public function test_only_the_latest_answer_writes(): void {
        $source = $this->source();
        $this->assertStringContainsString('const ticket = ++state.ticket;', $source);
        $rendered = strpos($source, 'const fresh = await render(answer);');
        $check = strpos($source, "if (ticket !== state.ticket) {\n            return;\n        }");
        $write = strpos($source, 'state.extras = fresh.extras;');
        $this->assertNotFalse($rendered);
        $this->assertNotFalse($check, 'No ticket check.');
        $this->assertGreaterThan($rendered, $check, 'The ticket is compared after the last await.');
        $this->assertGreaterThan($check, $write, 'The ticket is compared before anything is written.');
    }

    /**
     * The first paint is remembered, a re-render restored and refilled, and a page whose first paint had no extras is
     * left as core drew it.
     *
     * @return void
     */
    public function test_the_two_runs_differ_and_an_unarmed_page_stays_cores(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            "~if \(root\.hasAttribute\('data-dim-extras'\)\) \{\s*state\.armed = true;\s*state\.courseid = Number\(courseid\);" .
            "\s*remember\(root\);\s*return;\s*\}\s*if \(!state\.armed \|\| state\.courseid !== Number\(courseid\)\) \{" .
            "\s*return;\s*\}\s*restore\(root\);\s*refill\(\);~",
            $source
        );
    }

    /**
     * Markup is written only into the two slots, and only from what the theme's own templates rendered; the slots are
     * found by their own attributes, never data-id, which core reads through closest('[data-id]').
     *
     * @return void
     */
    public function test_markup_comes_from_the_themes_templates_alone(): void {
        $source = $this->source();
        $this->assertSame(2, substr_count($source, 'innerHTML ='));
        $this->assertStringContainsString("EXTRA: 'theme_dimensions/competency_course_extra',", $source);
        $this->assertStringContainsString("CM: 'theme_dimensions/competency_course_cm',", $source);
        $this->assertStringContainsString("EXTRA: '[data-region=\"dim-comp-extra\"]',", $source);
        $this->assertStringContainsString("CM: '[data-region=\"dim-comp-cm\"]',", $source);
        $this->assertStringContainsString("slot.getAttribute('data-cmid')", $source);
        $this->assertStringNotContainsString('jquery', strtolower($source));
        $this->assertStringNotContainsString('core/notification', $source);
    }
}
