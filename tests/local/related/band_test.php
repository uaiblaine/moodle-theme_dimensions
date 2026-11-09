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
 * Theme Dimensions - Tests for the landing page's related courses band
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use core_competency\course_competency;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\step;
use theme_dimensions\local\trail\trail;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../category/fixture.php');

/**
 * Whether a landing page draws the band, how many courses it lists, and which courses it leaves out.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(band::class)]
final class band_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var int The category every course of a test lives in. */
    private int $categoryid;

    /** @var int[] Competency ids by name. */
    private array $comps = [];

    /**
     * Competencies on, as an administrator, with one framework of three competencies.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        self::forget();
        set_config('enabled', 1, 'core_competency');

        $this->categoryid = (int) self::getDataGenerator()->create_category()->id;
        $framework = self::competencies()->create_framework(['idnumber' => 'FW1']);
        foreach (['a', 'b', 'c'] as $name) {
            $this->comps[$name] = (int) self::competencies()->create_competency([
                'competencyframeworkid' => $framework->get('id'),
                'idnumber' => strtoupper($name),
            ])->get('id');
        }
    }

    /**
     * Core's competency generator.
     *
     * @return \core_competency_generator
     */
    private static function competencies(): \core_competency_generator {
        return self::getDataGenerator()->get_plugin_generator('core_competency');
    }

    /**
     * A public course in the test's category, linked to competencies for evidence.
     *
     * @param string[] $names The competencies' names.
     * @param string $fullname The course name.
     * @param array $record More course record fields, custom fields included.
     * @return \stdClass The course record.
     */
    private function course(array $names, string $fullname = 'Curso', array $record = []): \stdClass {
        $course = self::getDataGenerator()->create_course(
            $record + ['category' => $this->categoryid, 'fullname' => $fullname]
        );
        foreach ($names as $name) {
            self::competencies()->create_course_competency([
                'courseid' => $course->id,
                'competencyid' => $this->comps[$name],
                'ruleoutcome' => course_competency::OUTCOME_EVIDENCE,
            ]);
        }
        $this->set_course_state((int) $course->id, discoverability::STATE_PUBLIC);
        return $course;
    }

    /**
     * The landing page values of a course choosing a line of the related select.
     *
     * @param int $related The stored value: 0 for none, a RELATED_* constant, or any other line.
     * @param array $pairs The manual choice, as [framework idnumber, competency idnumber] pairs.
     * @return \stdClass
     */
    private static function values(int $related, array $pairs = []): \stdClass {
        return (object) ['model' => landingfields::MODEL_COURSE, 'related' => $related, 'relatedpairs' => choice::encode($pairs)];
    }

    /**
     * A trail built from steps that reference these courses, without mod_subcourse.
     *
     * @param int $courseid The trail course.
     * @param int[] $refs The steps' referenced courses.
     * @return trail
     */
    private function trail(int $courseid, array $refs): trail {
        $steps = [];
        foreach ($refs as $position => $ref) {
            $steps[] = new step($position + 1, 'Step', $ref, $this->categoryid, true, 0, 0, false);
        }
        return \Closure::bind(static fn(): trail => new trail($courseid, $steps), null, trail::class)();
    }

    /**
     * The ids the band lists.
     *
     * @param \stdClass $course The landing page's course.
     * @param \stdClass $values Its values.
     * @param trail|null $trail Its trail.
     * @return int[]
     */
    private static function listed(\stdClass $course, \stdClass $values, ?trail $trail = null): array {
        return array_map(
            static fn(array $entry): int => $entry['candidate']->courseid,
            band::courses($course, $values, $trail)
        );
    }

    /**
     * Automatic, the empty choice and Manual draw the band; Off and a line this release does not know draw nothing.
     */
    public function test_the_select_decides_whether_the_band_is_drawn(): void {
        $parent = $this->course(['a']);
        $related = (int) $this->course(['a'])->id;

        $this->assertSame([$related], self::listed($parent, self::values(landingfields::RELATED_AUTO)));
        $this->assertSame([$related], self::listed($parent, self::values(0)), 'An empty choice reads as automatic.');
        $this->assertSame([], self::listed($parent, self::values(landingfields::RELATED_OFF)));
        $this->assertSame([], self::listed($parent, self::values(4)), 'A line appended later is not automatic.');
        $this->assertSame([$related], self::listed($parent, self::values(landingfields::RELATED_MANUAL, [['FW1', 'A']])));
        $this->assertSame(finder::MODE_MANUAL, band::mode(self::values(landingfields::RELATED_MANUAL)));
        $this->assertSame(finder::MODE_AUTO, band::mode(self::values(0)));
        $this->assertNull(band::mode(self::values(landingfields::RELATED_OFF)));
    }

    /**
     * Manual relates by the chosen competencies alone, and only by those still linked to the course.
     */
    public function test_manual_relates_by_the_chosen_competencies_among_the_courses(): void {
        $parent = $this->course(['a', 'b']);
        $bya = (int) $this->course(['a'], 'Por A')->id;
        $byb = (int) $this->course(['b'], 'Por B')->id;
        $this->course(['c'], 'Por C');

        $this->assertEqualsCanonicalizing([$bya, $byb], self::listed($parent, self::values(0)), 'Control: automatic.');
        $this->assertSame([$byb], self::listed($parent, self::values(landingfields::RELATED_MANUAL, [['FW1', 'B']])));
        $this->assertSame(
            [$byb],
            self::listed($parent, self::values(landingfields::RELATED_MANUAL, [['FW1', 'B'], ['FW1', 'C']])),
            'A chosen competency the course is not linked to relates nothing.'
        );
        $this->assertSame([], self::listed($parent, self::values(landingfields::RELATED_MANUAL, [['FW1', 'C']])));
        $this->assertSame([], self::listed($parent, self::values(landingfields::RELATED_MANUAL)), 'Nothing chosen, no band.');
        $this->assertSame([], self::listed($parent, self::values(landingfields::RELATED_MANUAL, [['FW9', 'B']])));
    }

    /**
     * Manual orders by how many of the chosen competencies a course covers, and a trail chooses among its steps' too.
     */
    public function test_manual_counts_the_chosen_and_reads_a_trails_steps(): void {
        $parent = $this->course(['a', 'b']);
        $one = (int) $this->course(['a'], 'A só')->id;
        $both = (int) $this->course(['a', 'b'], 'Z ambas')->id;
        $chosen = [['FW1', 'A'], ['FW1', 'B']];

        $this->assertSame([$both, $one], self::listed($parent, self::values(landingfields::RELATED_MANUAL, $chosen)));

        $trail = $this->course([]);
        $step = (int) $this->course(['c'])->id;
        $byc = (int) $this->course(['c'], 'Por C')->id;
        $manualc = self::values(landingfields::RELATED_MANUAL, [['FW1', 'C']]);
        $this->assertSame([], self::listed($trail, $manualc), 'Control: the trail course alone has no C.');
        $this->assertSame([$byc], self::listed($trail, $manualc, $this->trail((int) $trail->id, [$step])));
    }

    /**
     * The values a real course reads: no row is automatic, and the field's Off is read back as Off.
     */
    public function test_the_field_reads_back_as_the_band_wants_it(): void {
        $parent = $this->course(['a']);
        $related = (int) $this->course(['a'])->id;
        $off = $this->course(['a'], 'Desligado', ['customfield_' . landingfields::SHORTNAME_RELATED => landingfields::RELATED_OFF]);

        $this->assertSame(0, landingfields::values((int) $parent->id)->related);
        $this->assertEqualsCanonicalizing(
            [$related, (int) $off->id],
            self::listed($parent, landingfields::values((int) $parent->id))
        );
        $this->assertSame(landingfields::RELATED_OFF, landingfields::values((int) $off->id)->related);
        $this->assertSame([], self::listed($off, landingfields::values((int) $off->id)));
    }

    /**
     * The setting caps the band, and a value outside its choices reads as the default of four.
     */
    public function test_the_setting_caps_the_band(): void {
        $parent = $this->course(['a']);
        for ($i = 0; $i < 6; $i++) {
            $this->course(['a'], 'Curso ' . $i);
        }

        $this->assertSame(band::DEFAULT_COUNT, band::count());
        $this->assertCount(4, self::listed($parent, self::values(0)));

        set_config(band::SETTING_COUNT, '2', 'theme_dimensions');
        $this->assertSame(2, band::count());
        $this->assertCount(2, self::listed($parent, self::values(0)));

        foreach (['0', '13', 'x', ''] as $stored) {
            set_config(band::SETTING_COUNT, $stored, 'theme_dimensions');
            $this->assertSame(band::DEFAULT_COUNT, band::count(), "Stored '{$stored}'.");
        }
        set_config(band::SETTING_COUNT, (string) finder::MAX_LIMIT, 'theme_dimensions');
        $this->assertSame(finder::MAX_LIMIT, band::count());
    }

    /**
     * A trail's set is its own and its steps' competencies, and neither the trail nor a step is related.
     */
    public function test_a_trails_steps_bring_their_competencies_and_are_never_related(): void {
        $parent = $this->course([]);
        $step = (int) $this->course(['b'])->id;
        $related = (int) $this->course(['b'])->id;

        $this->assertSame([], self::listed($parent, self::values(0)), 'Control: the course alone has no competency.');
        $this->assertSame([$related], self::listed($parent, self::values(0), $this->trail((int) $parent->id, [$step])));
    }

    /**
     * The parent is never its own related course.
     */
    public function test_the_parent_is_left_out(): void {
        $parent = $this->course(['c']);

        $this->assertSame([], self::listed($parent, self::values(0)));
        $other = (int) $this->course(['c'])->id;
        $this->assertSame([$other], self::listed($parent, self::values(0)), 'Control: a second course is related.');
    }

    /**
     * A site provisioned before the field gets it from the upgrade.
     */
    public function test_the_upgrade_creates_the_field(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/theme/dimensions/db/upgrade.php');

        $where = ['shortname' => landingfields::SHORTNAME_RELATED];
        $this->assertSame(1, $DB->count_records('customfield_field', $where));
        $DB->delete_records('customfield_field', $where);
        $this->assertSame(0, $DB->count_records('customfield_field', $where));
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        $this->assertSame(0, $DB->count_records('customfield_field', $where), 'Nothing recreates it on a read.');
        $this->assertNull(landingfields::get_field(landingfields::SHORTNAME_RELATED), 'Control: the field is gone.');

        set_config('version', 2026100594, 'theme_dimensions');
        xmldb_theme_dimensions_upgrade(2026100594);
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();

        $this->assertNotNull(landingfields::get_field(landingfields::SHORTNAME_RELATED));
        $this->assertGreaterThanOrEqual(2026100595, (int) get_config('theme_dimensions', 'version'));
    }

    /**
     * A site provisioned with the two lines and no pairs field gets the manual line and the field from the upgrade.
     */
    public function test_the_upgrade_brings_manual_mode(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/theme/dimensions/db/upgrade.php');

        $where = ['shortname' => landingfields::SHORTNAME_RELATEDPAIRS];
        $DB->delete_records('customfield_field', $where);
        $select = $DB->get_record('customfield_field', ['shortname' => landingfields::SHORTNAME_RELATED], '*', MUST_EXIST);
        $config = json_decode($select->configdata, true);
        $lines = explode("\n", $config['options']);
        $this->assertCount(3, $lines, 'A fresh install has the manual line.');
        $config['options'] = implode("\n", array_slice($lines, 0, 2));
        $DB->set_field('customfield_field', 'configdata', json_encode($config), ['id' => $select->id]);
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        $this->assertSame(0, $DB->count_records('customfield_field', $where), 'Control: the field is gone.');
        $this->assertCount(2, landingfields::option_lines(landingfields::SHORTNAME_RELATED), 'Control: two lines.');

        set_config('version', 2026100598, 'theme_dimensions');
        xmldb_theme_dimensions_upgrade(2026100598);
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();

        $this->assertNotNull(landingfields::get_field(landingfields::SHORTNAME_RELATEDPAIRS));
        $this->assertSame(
            get_string('landingfield_related_manual', landingfields::COMPONENT),
            landingfields::option_label(landingfields::SHORTNAME_RELATED, landingfields::RELATED_MANUAL)
        );
        $this->assertFalse(landingfields::add_related_manual_option(), 'Run again, the step appends nothing.');
        $this->assertCount(3, landingfields::option_lines(landingfields::SHORTNAME_RELATED));
        $this->assertGreaterThanOrEqual(2026100599, (int) get_config('theme_dimensions', 'version'));
    }
}
