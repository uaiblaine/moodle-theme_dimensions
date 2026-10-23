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
 * Theme Dimensions - The category card excerpt in a process that has not loaded lib/filelib.php
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\output\category_card;

/**
 * A card's excerpt rewrites the pluginfile URLs of the course summary, which needs lib/filelib.php.
 *
 * With `courseoverviewfileslimit` at 0 the card images return before their own require of that file, and the
 * landing fields load it only when they format a textarea, so a card can reach the excerpt without it (the
 * category rows web service has no course login to load it either). The card is built from hand-made values for
 * that reason: the landing fields' reader would load the file first. No test can see that state in its own process: PHPUnit
 * has loaded filelib.php long before a test starts. The call runs in a child PHP process that boots the test
 * site as admin/tool/phpunit/cli/util.php does (PHPUNIT_UTIL: lib/setup.php and nothing more). A boot that
 * rebuilds the caches may include the file, so a child that finds it loaded is run once more.
 */
#[CoversClass(category_card::class)]
final class get_category_rows_without_filelib_test extends \advanced_testcase {
    /** @var string The child script; argv: Composer's autoload.php, lib/phpunit/bootstrap.php, the course id. */
    private const CHILD = <<<'PHP'
        <?php
        define('PHPUNIT_UTIL', true);
        require($argv[1]);
        require($argv[2]);
        \core\session\manager::set_user(get_admin());
        $result = ['loadedbefore' => function_exists('file_rewrite_pluginfile_urls')];
        try {
            $course = get_course((int) $argv[3]);
            $values = (object) [
                'model' => \theme_dimensions\local\landingfields::MODEL_COURSE,
                'workload' => null,
                'modality' => 0,
                'city' => null,
                'venue' => null,
                'programme' => null,
                'crests' => [],
                'names' => [],
            ];
            $card = new \theme_dimensions\output\category_card(
                $course,
                (object) ['state' => 'none', 'visible' => 1, 'category' => $course->category],
                $values,
                null,
                [],
                null,
                false,
                true
            );
            $result['excerpt'] = $card->export_for_template($PAGE->get_renderer('core'))->excerpt;
        } catch (\Throwable $e) {
            $result['error'] = get_class($e) . ': ' . $e->getMessage();
        }
        echo json_encode($result);
        PHP;

    /**
     * The excerpt of a course with a summary is built without filelib.php loaded beforehand.
     */
    public function test_the_excerpt_loads_filelib_itself(): void {
        // The child process reads the test database, so the rows must be committed rather than rolled back.
        $this->preventResetByRollback();
        $this->resetAfterTest();
        set_config('courseoverviewfileslimit', 0);
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course([
            'category' => $category->id,
            'summary' => '<p>A summary worth an excerpt.</p>',
            'summaryformat' => FORMAT_HTML,
        ]);

        $result = $this->run_child((int) $course->id);
        if ($result['loadedbefore']) {
            $result = $this->run_child((int) $course->id);
        }

        // The state of the failing request: without it the test proves nothing.
        $this->assertFalse($result['loadedbefore'], 'The child process booted with lib/filelib.php loaded.');
        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');
        $this->assertSame('A summary worth an excerpt.', $result['excerpt']);
    }

    /**
     * Run the child script and decode what it prints.
     *
     * @param int $courseid The course whose card is exported.
     * @return array Keys: loadedbefore, and excerpt or error.
     */
    private function run_child(int $courseid): array {
        global $CFG, $DB;
        $script = make_request_directory() . '/category_rows_child.php';
        file_put_contents($script, self::CHILD);
        $loader = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
        $command = [
            PHP_BINARY,
            $script,
            dirname($loader->getFileName(), 2) . '/autoload.php',
            $CFG->libdir . '/phpunit/bootstrap.php',
            (string) $courseid,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        // The child writes through its own connection, so the reset after this test cannot see which
        // tables it touched: mark them all, or its rows outlive the test and collide with later ones.
        foreach (array_keys($DB->get_tables(false)) as $table) {
            \testing_util::set_table_modified_by_sql(' ' . $DB->get_prefix() . $table);
        }

        $result = json_decode((string) $stdout, true);
        $this->assertIsArray($result, "The child process exited with $exit:\n$stdout\n$stderr");
        return $result;
    }
}
