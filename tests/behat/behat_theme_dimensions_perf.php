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
require_once(__DIR__ . '/theme_dimensions_perf_probe.php');

use Behat\Mink\Exception\ExpectationException;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use Moodle\BehatExtension\Exception\SkippedException;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\landing\links;

/**
 * Theme Dimensions - the showcase's browser probe
 *
 * Steps of theme_dimensions_perf.feature only. The measuring is theme_dimensions_perf_probe's;
 * this context opts in or skips, builds the fixture, resolves the page and writes the report.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_theme_dimensions_perf extends behat_base {
    /** @var int Courses in the flat category, each with a cover: one page at the smallest size, population::PAGESIZES[0]. */
    private const FLAT_COURSES = 24;

    /** @var int Areas under the grouped category. */
    private const AREAS = 6;

    /** @var int Courses in each area. */
    private const AREA_COURSES = 4;

    /** @var string The directory under the faildump path the reports go to. */
    private const REPORT_DIR = 'theme_dimensions_perf';

    /**
     * Skip the scenario unless the site opted in and the browser is a Chrome the probe can throttle.
     *
     * The opt-in is `$CFG->behat_theme_dimensions_perfprobe` or the THEME_DIMENSIONS_PERFPROBE
     * environment variable ({@see theme_dimensions_perf_probe::options()}), so an ordinary suite and
     * CI skip these scenarios rather than spend minutes on numbers nobody reads.
     *
     * @Given /^the theme_dimensions performance probe is enabled$/
     * @return void
     * @throws SkippedException When the site did not opt in, or the browser cannot be throttled.
     */
    public function the_theme_dimensions_performance_probe_is_enabled(): void {
        if (theme_dimensions_perf_probe::options() === null) {
            throw new SkippedException('The performance probe runs only where $CFG->' . theme_dimensions_perf_probe::CONFIG
                . ' or ' . theme_dimensions_perf_probe::ENV . ' opts in.');
        }
        if (!$this->running_javascript()) {
            throw new SkippedException('The performance probe needs a JavaScript session.');
        }
        $browser = $this->webdriver()?->getCapabilities()?->getBrowserName();
        if ($browser !== 'chrome') {
            throw new SkippedException('The performance probe throttles through Chrome DevTools; this browser is "'
                . $browser . '".');
        }
    }

    /**
     * Build the probe's pages: a flat category of courses with covers, a category of areas, a student, public states.
     *
     * PERFFLAT holds FLAT_COURSES courses, every one with a landing page model and a cover, all
     * public, in a public category whose hero has the image layout and a picture; PERFAREAS holds
     * AREAS areas of AREA_COURSES listed courses each, every other one with a cover. The student
     * "perfstudent" is enrolled in three of the flat courses. The scenarios log in with "I log in as",
     * core's Behat login script, which authenticates with the username as the password and only adds a
     * notification when that fails: a student made with the generator's default password would be
     * measured as a visitor.
     *
     * @Given /^the showcase performance fixture exists$/
     * @return void
     */
    public function the_showcase_performance_fixture_exists(): void {
        global $USER;

        $generator = \testing_util::get_data_generator();
        $previous = $USER;
        \core\session\manager::set_user(get_admin());
        try {
            $flat = $generator->create_category(['name' => 'Performance flat', 'idnumber' => 'PERFFLAT']);
            $areas = $generator->create_category(['name' => 'Performance areas', 'idnumber' => 'PERFAREAS']);
            $student = $generator->create_user([
                'username' => 'perfstudent',
                'password' => 'perfstudent',
                'firstname' => 'Perf',
                'lastname' => 'Student',
            ]);
            $today = usergetmidnight(time());
            for ($i = 0; $i < self::FLAT_COURSES; $i++) {
                $course = $generator->create_course([
                    'fullname' => sprintf('Performance course %02d', $i),
                    'shortname' => sprintf('PERF%02d', $i),
                    'category' => $flat->id,
                    'startdate' => $today + ($i - 6) * DAYSECS,
                    'summary' => '<p>A course of the performance probe, with a summary long enough for the excerpt.</p>',
                    'customfield_dimensions_model' => 1 + (int) ($i % 5 === 0),
                ]);
                $this->cover((int) $course->id, $i);
                $this->state((int) $course->id, discoverability::STATE_PUBLIC);
                if ($i < 3) {
                    $generator->enrol_user($student->id, $course->id, 'student');
                }
            }
            for ($a = 1; $a <= self::AREAS; $a++) {
                $area = $generator->create_category(['name' => "Area {$a}", 'idnumber' => "PERFAREA{$a}", 'parent' => $areas->id]);
                for ($c = 0; $c < self::AREA_COURSES; $c++) {
                    $course = $generator->create_course([
                        'fullname' => "Area {$a} course {$c}",
                        'shortname' => "PERFA{$a}C{$c}",
                        'category' => $area->id,
                        'customfield_dimensions_model' => 1,
                    ]);
                    if ($c % 2 === 0) {
                        $this->cover((int) $course->id, 100 + 10 * $a + $c);
                    }
                    $this->state((int) $course->id, discoverability::STATE_DEFAULT);
                }
            }
            category_discoverability::set_state((int) $flat->id, category_discoverability::STATE_PUBLIC, (int) get_admin()->id);
            branding::save((int) $flat->id, (object) [
                'layout' => branding::LAYOUT_IMAGE,
                'tagline' => 'Every course of the performance probe',
                'publicsummary' => 'The performance probe of the category showcase.',
            ]);
            get_file_storage()->create_file_from_string([
                'contextid' => \core\context\coursecat::instance((int) $flat->id)->id,
                'component' => branding::COMPONENT,
                'filearea' => branding::AREA_HERO,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'hero.jpg',
            ], self::jpeg(1600, 600, 7));
            branding::forget_all();
        } finally {
            \core\session\manager::set_user($previous);
        }
    }

    /**
     * Measure a page's first paint, five runs (or the opt-in's number), and write the report under the faildumps.
     *
     * The page is "<kind>:<idnumber>[|<query>]": kind "category" is course/index.php (the logged-in
     * shell), "public" the visitor's page of the category, "index" the course index (no idnumber),
     * e.g. "category:PERFFLAT|sort=name". "pressing" names a CSS selector of one control to press
     * after each load, whose interaction is the run's INP. The numbers are a trend and never fail
     * the step; a page on which the collector never ran does, because then nothing was measured.
     *
     * @When /^I probe the "(cold|warm)" first paint of "([^"]*)" as "([^"]*)"(?: pressing "([^"]*)")?$/
     * @param string $mode cold (an empty browser cache every run) or warm.
     * @param string $page The page, "<kind>:<idnumber>[|<query>]".
     * @param string $label The report's name for the page.
     * @param string $press The CSS selector of the control to press, '' for none.
     * @return void
     * @throws ExpectationException When the collector never ran on the page.
     */
    public function i_probe_the_first_paint(string $mode, string $page, string $label, string $press = ''): void {
        global $CFG;

        $options = theme_dimensions_perf_probe::options();
        if ($options === null) {
            throw new SkippedException('The performance probe is not enabled here.');
        }
        $url = $this->probe_url($page);
        $probe = new theme_dimensions_perf_probe($this->webdriver(), $options);
        $cold = $mode === 'cold';
        $runs = $probe->series($url, $cold, $press);
        foreach ($runs as $run) {
            if (!isset($run['domnodes'])) {
                throw new ExpectationException('The probe\'s collector did not run on ' . $url, $this->getSession());
            }
        }
        $dir = ($CFG->behat_faildump_path ?? '') ?: $CFG->behat_dataroot;
        theme_dimensions_perf_probe::write($dir . '/' . self::REPORT_DIR, $probe->report($label, $url, $cold, $runs));
    }

    /**
     * The session's php-webdriver driver, or null when Mink's driver is not a WebDriver one.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver|null
     */
    private function webdriver(): ?\Facebook\WebDriver\Remote\RemoteWebDriver {
        $driver = $this->getSession()->getDriver();
        if (!method_exists($driver, 'getWebDriver')) {
            return null;
        }
        if (!$driver->isStarted()) {
            $driver->start();
        }
        return $driver->getWebDriver();
    }

    /**
     * The absolute address of the page a probe step names.
     *
     * @param string $page "<kind>:<idnumber>[|<query>]", kind category, public or index.
     * @return string The address.
     * @throws ExpectationException For a kind this context does not know.
     */
    private function probe_url(string $page): string {
        global $DB;

        [$kind, $identifier] = array_pad(explode(':', $page, 2), 2, '');
        [$idnumber, $query] = array_pad(explode('|', $identifier, 2), 2, '');
        parse_str($query, $params);
        if ($kind === 'index') {
            return (new moodle_url('/course/index.php', $params))->out(false);
        }
        if ($kind !== 'category' && $kind !== 'public') {
            throw new ExpectationException('Unknown probe page kind "' . $kind . '"', $this->getSession());
        }
        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        if ($kind === 'public') {
            return links::category_page($categoryid, $params)->out(false);
        }
        return (new moodle_url('/course/index.php', ['categoryid' => $categoryid] + $params))->out(false);
    }

    /**
     * Give a course a cover: a JPEG made by GD in core's overview files area, as an editor's upload would be.
     *
     * @param int $courseid The course.
     * @param int $seed Picks the colour.
     * @return void
     */
    private function cover(int $courseid, int $seed): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\course::instance($courseid)->id,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'cover.jpg',
        ], self::jpeg(960, 540, $seed));
    }

    /**
     * Put a course in a discoverability state, as the site administrator, whatever the site's creation default was.
     *
     * @param int $courseid The course.
     * @param int $state A discoverability::STATE_* constant.
     * @return void
     */
    private function state(int $courseid, int $state): void {
        if (discoverability::get_state($courseid) !== $state) {
            discoverability::set_state($courseid, $state, (int) get_admin()->id);
        }
    }

    /**
     * A JPEG of one colour.
     *
     * @param int $width Width.
     * @param int $height Height.
     * @param int $seed Picks the colour.
     * @return string The bytes.
     */
    private static function jpeg(int $width, int $height, int $seed): string {
        $image = imagecreatetruecolor($width, $height);
        $colour = imagecolorallocate($image, (37 * $seed) % 200 + 20, (71 * $seed) % 200 + 20, (113 * $seed) % 200 + 20);
        imagefill($image, 0, 0, $colour);
        ob_start();
        imagejpeg($image, null, 75);
        imagedestroy($image);
        return (string) ob_get_clean();
    }
}
