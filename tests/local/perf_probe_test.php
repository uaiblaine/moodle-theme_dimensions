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
 * Theme Dimensions - Tests for the browser probe's opt-in and its median
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../behat/theme_dimensions_perf_probe.php');

/**
 * The probe is off unless a site opts in, which is what keeps it out of ordinary suites and CI.
 *
 * The browser half cannot run here (it needs a Selenium Chrome); the two pure parts can: the
 * reading of the opt-in, whose failure in one direction would make every CI run spend minutes on
 * the probe and in the other would skip it where it was asked for, and the median the
 * reports keep.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\theme_dimensions_perf_probe::class)]
final class perf_probe_test extends \advanced_testcase {
    /**
     * Leave neither opt-in behind.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $CFG;
        unset($CFG->{\theme_dimensions_perf_probe::CONFIG});
        putenv(\theme_dimensions_perf_probe::ENV);
        parent::tearDown();
    }

    /**
     * Nothing set, an empty value and a zero are all off; true and 1 are the defaults.
     */
    public function test_the_probe_is_off_unless_a_site_opts_in(): void {
        global $CFG;
        unset($CFG->{\theme_dimensions_perf_probe::CONFIG});
        putenv(\theme_dimensions_perf_probe::ENV);
        $this->assertNull(\theme_dimensions_perf_probe::options(), 'Nothing set: off.');

        foreach (['', '0', ' 0 '] as $off) {
            putenv(\theme_dimensions_perf_probe::ENV . '=' . $off);
            $this->assertNull(\theme_dimensions_perf_probe::options(), "The environment's '{$off}' is off.");
        }
        $CFG->{\theme_dimensions_perf_probe::CONFIG} = false;
        $this->assertNull(\theme_dimensions_perf_probe::options(), 'A false config value is off too.');

        putenv(\theme_dimensions_perf_probe::ENV . '=1');
        $this->assertSame(\theme_dimensions_perf_probe::DEFAULTS, \theme_dimensions_perf_probe::options());
        putenv(\theme_dimensions_perf_probe::ENV);
        $CFG->{\theme_dimensions_perf_probe::CONFIG} = true;
        $this->assertSame(\theme_dimensions_perf_probe::DEFAULTS, \theme_dimensions_perf_probe::options());
    }

    /**
     * The config opt-in outlives core's clean-up of $CFG in the Behat process, where the probe reads it.
     *
     * behat_clean_init_config() unsets every property that is neither on core's short allowlist nor
     * behat_-prefixed; an opt-in it removed would skip every scenario on a site that opted in.
     */
    public function test_the_config_opt_in_survives_the_behat_process_clean_up(): void {
        global $CFG;
        require_once($CFG->libdir . '/behat/lib.php');
        $saved = clone $CFG;
        try {
            $CFG->{\theme_dimensions_perf_probe::CONFIG} = true;
            $CFG->theme_dimensions_control = true;
            behat_clean_init_config();
            $kept = \theme_dimensions_perf_probe::options();
            $control = isset($CFG->theme_dimensions_control);
        } finally {
            foreach (get_object_vars($saved) as $name => $value) {
                $CFG->{$name} = $value;
            }
            unset($CFG->theme_dimensions_control);
        }
        $this->assertFalse($control, 'The clean-up ran: an unprefixed property is gone.');
        $this->assertSame(\theme_dimensions_perf_probe::DEFAULTS, $kept, 'The opt-in is still there after it.');
    }

    /**
     * An array or a JSON object overrides the defaults it names and nothing else, with a floor of one run.
     */
    public function test_the_opt_in_overrides_only_the_conditions_it_names(): void {
        global $CFG;
        $CFG->{\theme_dimensions_perf_probe::CONFIG} = ['runs' => 3, 'latency' => '40', 'bogus' => 9];
        $options = \theme_dimensions_perf_probe::options();
        $this->assertSame(3, $options['runs']);
        $this->assertSame(40, $options['latency']);
        $this->assertArrayNotHasKey('bogus', $options);
        $this->assertSame(\theme_dimensions_perf_probe::DEFAULTS['cpu'], $options['cpu']);

        unset($CFG->{\theme_dimensions_perf_probe::CONFIG});
        putenv(\theme_dimensions_perf_probe::ENV . '={"runs": 0, "cpu": 6}');
        $options = \theme_dimensions_perf_probe::options();
        $this->assertSame(1, $options['runs'], 'Never fewer than one run.');
        $this->assertSame(6, $options['cpu']);
    }

    /**
     * The median is taken per number, through nested counts, leaving out a run that lacks the value.
     */
    public function test_the_median_is_taken_per_number(): void {
        $runs = [
            ['fcp' => 300, 'inp' => null, 'lcpelement' => 'img', 'beforeload' => ['scripts' => ['count' => 10]]],
            ['fcp' => 100, 'inp' => 40, 'lcpelement' => 'img', 'beforeload' => ['scripts' => ['count' => 12]]],
            ['fcp' => 200, 'inp' => 60, 'lcpelement' => 'img', 'beforeload' => ['scripts' => ['count' => 10]]],
            ['fcp' => 500, 'inp' => 50, 'lcpelement' => 'img', 'beforeload' => ['scripts' => ['count' => 10]]],
        ];
        $median = \theme_dimensions_perf_probe::median($runs);
        $this->assertEquals(250, $median['fcp'], 'Four runs: the mean of the middle two.');
        $this->assertSame(50, $median['inp'], 'A run with no value is left out, not counted as zero.');
        $this->assertSame('img', $median['lcpelement']);
        $this->assertSame(10, $median['beforeload']['scripts']['count']);
    }
}
