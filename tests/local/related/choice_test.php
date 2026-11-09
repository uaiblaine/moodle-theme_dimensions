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
 * Theme Dimensions - Tests for the related courses band's manual choice
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use core_competency\course_competency;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

/**
 * How a manual choice is stored, offered, read back and resolved.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(choice::class)]
final class choice_test extends \advanced_testcase {
    /** @var int[] Competency ids by framework and idnumber, 'FW1/A'. */
    private array $comps = [];

    /**
     * Competencies on, as an administrator, with two frameworks that both hold a competency 'A'.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        \core_course\customfield\course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();

        foreach (['FW1' => ['A', 'B'], 'FW2' => ['A']] as $framework => $idnumbers) {
            $created = self::competencies()->create_framework(['idnumber' => $framework, 'shortname' => "Estrutura {$framework}"]);
            foreach ($idnumbers as $idnumber) {
                $this->comps["{$framework}/{$idnumber}"] = (int) self::competencies()->create_competency([
                    'competencyframeworkid' => $created->get('id'),
                    'idnumber' => $idnumber,
                    'shortname' => "Liderança {$idnumber} & cia",
                ])->get('id');
            }
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
     * A course linked to competencies.
     *
     * @param string[] $keys The competencies, 'FW1/A'.
     * @return \stdClass The course record.
     */
    private function course(array $keys): \stdClass {
        $course = self::getDataGenerator()->create_course();
        foreach ($keys as $key) {
            self::competencies()->create_course_competency([
                'courseid' => $course->id,
                'competencyid' => $this->comps[$key],
                'ruleoutcome' => course_competency::OUTCOME_EVIDENCE,
            ]);
        }
        return $course;
    }

    /**
     * A choice round-trips through its stored value; a malformed value holds nothing, a repeated pair counts once.
     */
    public function test_a_choice_is_stored_as_idnumber_pairs(): void {
        $pairs = [['FW1', 'A'], ['FW2', 'A'], ['FW1', 'Ação/1']];
        $this->assertSame('[["FW1","A"],["FW2","A"],["FW1","Ação/1"]]', choice::encode($pairs));
        $this->assertSame($pairs, choice::decode(choice::encode($pairs)));

        $this->assertSame('', choice::encode([]));
        foreach (['', 'FW1:A', '{"a":1}', '[["FW1"]]', '[["FW1",2]]', '[["", "A"]]', '[[" ", "A"]]', null] as $raw) {
            $this->assertSame([], choice::decode($raw), var_export($raw, true));
        }
        $this->assertSame([['FW1', 'A']], choice::decode('[[" FW1 ","A"],["FW1","A"],"x"]'));
    }

    /**
     * A choice longer than the field's column keeps its leading pairs whole.
     */
    public function test_a_choice_too_long_for_the_column_keeps_its_leading_pairs(): void {
        $pairs = [];
        for ($i = 0; $i < 200; $i++) {
            $pairs[] = ['FW1', sprintf('COMP%04d', $i)];
        }
        $encoded = choice::encode($pairs);

        $this->assertLessThanOrEqual(choice::MAX_LENGTH, \core_text::strlen($encoded));
        $kept = choice::decode($encoded);
        $this->assertNotEmpty($kept);
        $this->assertSame(array_slice($pairs, 0, count($kept)), $kept);
        $this->assertLessThan(count($pairs), count($kept));
        $this->assertGreaterThan(
            choice::MAX_LENGTH,
            \core_text::strlen(json_encode(array_slice($pairs, 0, count($kept) + 1))),
            'Control: one more pair would not have fit.'
        );
    }

    /**
     * The form offers the competencies linked to the courses given, named with their framework, and only those.
     */
    public function test_the_form_offers_the_linked_competencies(): void {
        $course = $this->course(['FW1/B', 'FW2/A']);
        $step = $this->course(['FW1/A']);
        $this->course(['FW1/A', 'FW1/B']);

        $linked = choice::linked([(int) $course->id]);
        $this->assertEqualsCanonicalizing([$this->comps['FW1/B'], $this->comps['FW2/A']], array_keys($linked));
        $this->assertSame('FW2', $linked[$this->comps['FW2/A']]->framework);
        $this->assertSame('A', $linked[$this->comps['FW2/A']]->idnumber);

        $options = choice::options($linked);
        $this->assertSame('Liderança A & cia (A) · Estrutura FW2', $options[$this->comps['FW2/A']]);

        $this->assertCount(3, choice::linked([(int) $course->id, (int) $step->id]), 'A trail offers its steps\' too.');
        $this->assertSame([], choice::linked([]));

        set_config('enabled', 0, 'core_competency');
        $this->assertSame([], choice::linked([(int) $course->id]), 'Nothing while competencies are off.');
    }

    /**
     * The submitted ids become pairs among the linked competencies, and a stored choice preselects them back.
     */
    public function test_ids_become_pairs_and_pairs_ids_among_the_linked(): void {
        $linked = choice::linked([(int) $this->course(['FW1/B', 'FW2/A'])->id]);

        $pairs = choice::pairs([$this->comps['FW2/A'], $this->comps['FW1/A'], $this->comps['FW1/B'], 'x'], $linked);
        $this->assertSame([['FW2', 'A'], ['FW1', 'B']], $pairs, 'An id the course is not linked to is dropped.');

        $this->assertSame(
            [$this->comps['FW1/B'], $this->comps['FW2/A']],
            choice::chosen([['FW1', 'B'], ['FW1', 'A'], ['FW2', 'A']], $linked),
            'A pair the course is not linked to preselects nothing.'
        );
    }

    /**
     * A choice reads each idnumber inside its own framework.
     */
    public function test_a_choice_resolves_each_idnumber_in_its_framework(): void {
        $this->assertSame([$this->comps['FW2/A']], choice::competencies([['FW2', 'A']]));
        $this->assertSame(
            [$this->comps['FW1/B'], $this->comps['FW1/A'], $this->comps['FW2/A']],
            choice::competencies([['FW1', 'B'], ['FW1', 'A'], ['FW2', 'A'], ['FW9', 'A']])
        );
    }

    /**
     * Storing writes the course's row and a second store rewrites the same row; the page reads it back.
     */
    public function test_store_writes_the_course_row_once(): void {
        global $DB;

        $courseid = (int) self::getDataGenerator()->create_course()->id;
        $fieldid = (int) landingfields::get_field(landingfields::SHORTNAME_RELATEDPAIRS)->get('id');
        $where = ['instanceid' => $courseid, 'fieldid' => $fieldid];
        $this->assertSame('', landingfields::values($courseid)->relatedpairs);

        $this->assertTrue(choice::store($courseid, [['FW1', 'A']]));
        $this->assertSame([['FW1', 'A']], choice::decode(landingfields::values($courseid)->relatedpairs));

        $this->assertTrue(choice::store($courseid, [['FW2', 'A'], ['FW1', 'B']]));
        $this->assertSame(1, $DB->count_records('customfield_data', $where));
        $this->assertSame([['FW2', 'A'], ['FW1', 'B']], choice::decode(landingfields::values($courseid)->relatedpairs));

        $this->assertTrue(choice::store($courseid, []));
        $this->assertSame('', landingfields::values($courseid)->relatedpairs);
    }
}
