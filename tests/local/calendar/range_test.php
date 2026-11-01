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
 * Theme Dimensions - The calendar range's answer: dictionaries, day counts and filters
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Each course and category once, a count per key per day, and the "More" filters on course-bound items only.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(range::class)]
#[CoversClass(filters::class)]
final class range_test extends \advanced_testcase {
    use fixture;

    /** @var \stdClass The viewer. */
    private \stdClass $student;

    /** @var array Ids by name. */
    private array $ids = [];

    /**
     * Three courses (a course model, an event model, one in a hidden category), a site and a user event.
     *
     * @return void
     */
    private function seed(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $hidden = $generator->create_category();
        $this->student = $generator->create_user();
        $peer = $generator->create_user();
        $gone = $generator->create_user();
        $frozen = $generator->create_user(['suspended' => 1]);
        $erased = $generator->create_user();

        $course = $generator->create_course(['category' => $category->id]);
        self::set_model((int) $course->id, landingfields::MODEL_COURSE, [
            landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_ONLINE,
            landingfields::SHORTNAME_CITY => 'Recife',
        ]);
        $event = self::event_course((int) $category->id, 5, 6);
        self::set_model((int) $event->id, landingfields::MODEL_EVENT, [
            landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
            landingfields::SHORTNAME_CITY => 'Natal',
            landingfields::SHORTNAME_VENUE => 'Teatro & Arena',
        ]);
        $inhidden = $generator->create_course(['category' => $hidden->id]);
        foreach ([$course, $event, $inhidden] as $each) {
            $generator->enrol_user($this->student->id, $each->id, 'student');
        }
        $generator->enrol_user($peer->id, $event->id, 'student');
        // Hidden after the course was made, so the course itself stays visible: core lists it, the category it names not.
        $DB->set_field('course_categories', 'visible', 0, ['id' => $hidden->id]);
        \cache_helper::purge_by_event('changesincoursecat');
        $generator->enrol_user($gone->id, $event->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        // Active enrolments of accounts that are suspended and deleted: neither is a participant.
        $generator->enrol_user($frozen->id, $event->id, 'student');
        $generator->enrol_user($erased->id, $event->id, 'student');
        $DB->set_field('user', 'deleted', 1, ['id' => $erased->id]);

        $this->ids = [
            'category' => (int) $category->id,
            'hidden' => (int) $hidden->id,
            'course' => (int) $course->id,
            'event' => (int) $event->id,
            'inhidden' => (int) $inhidden->id,
            'frozen' => (int) $frozen->id,
            'erased' => (int) $erased->id,
            'long' => (int) $generator->create_event([
                'eventtype' => 'course',
                'courseid' => $course->id,
                'timestart' => self::at(3, 0),
                'timeduration' => 10 * DAYSECS,
            ])->id,
            'hiddencourseevent' => (int) $generator->create_event([
                'eventtype' => 'course',
                'courseid' => $inhidden->id,
                'timestart' => self::at(4),
            ])->id,
            'site' => (int) $generator->create_event(['eventtype' => 'site', 'timestart' => self::at(2)])->id,
            'user' => (int) $generator->create_event([
                'eventtype' => 'user',
                'userid' => $this->student->id,
                'timestart' => self::at(2),
            ])->id,
        ];
        $assign = $generator->create_module('assign', [
            'course' => $course->id,
            'duedate' => self::at(8, 17),
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $this->ids['assign'] = (int) $assign->id;
    }

    /**
     * The student's answer over next month.
     *
     * @param filters|null $filters The filters.
     * @return array
     */
    private function answer(?filters $filters = null): array {
        $this->as_viewer($this->student);
        return range::answer(self::month_window(), scope::for_viewer(), keys::ALL, $filters ?? new filters());
    }

    /**
     * Each course once with its facts; categories named only where the viewer may see them; no image data.
     *
     * @return void
     */
    public function test_the_dictionaries(): void {
        global $DB;
        $this->seed();
        $answer = $this->answer();

        $courses = array_column($answer['courses'], null, 'id');
        $this->assertSame([$this->ids['course'], $this->ids['event'], $this->ids['inhidden']], array_keys($courses));
        $event = $courses[$this->ids['event']];
        $this->assertSame(landingfields::MODEL_EVENT, $event['model']);
        $this->assertSame(landingfields::MODALITY_INPERSON, $event['modality']);
        $this->assertSame('Natal', $event['city']);
        $this->assertSame('Teatro & Arena', $event['venue']);
        $this->assertSame(2, $event['participants'], 'Two active enrolments; suspended, deleted and suspended-account ones not.');
        $this->assertTrue($event['enrolled']);
        $this->assertNotSame('', $event['landingurl']);
        $this->assertNull($courses[$this->ids['course']]['participants']);
        $this->assertSame($this->ids['category'], $courses[$this->ids['course']]['categoryid']);
        $this->assertSame(0, $courses[$this->ids['inhidden']]['categoryid'], 'A hidden category is not named.');
        $this->assertSame('', $courses[$this->ids['inhidden']]['landingurl']);

        $categories = array_column($answer['categories'], null, 'id');
        $this->assertSame([$this->ids['category']], array_keys($categories));
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $categories[$this->ids['category']]['bg']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $categories[$this->ids['category']]['ink']);

        // The controls: undo the one difference of each account and it is counted.
        $participants = function (): int {
            $courses = array_column($this->answer()['courses'], null, 'id');
            return $courses[$this->ids['event']]['participants'];
        };
        $DB->set_field('user', 'suspended', 0, ['id' => $this->ids['frozen']]);
        $this->assertSame(3, $participants());
        $DB->set_field('user', 'deleted', 0, ['id' => $this->ids['erased']]);
        $this->assertSame(4, $participants());

        $json = json_encode($answer);
        $this->assertStringNotContainsString('data:image', $json);
        $this->assertStringNotContainsString('<', $json);
        // The control: the hidden category's course event is in the answer, so its course had to be named somehow.
        $this->assertContains($this->ids['hiddencourseevent'], array_column($answer['events'], 'id'));
    }

    /**
     * A ten-day event counts on ten days, and nowhere else.
     *
     * @return void
     */
    public function test_the_day_counts(): void {
        $this->seed();
        $answer = $this->answer();

        $this->assertSame(self::month_window()->days, array_column($answer['days'], 'date'));
        $course = array_map(static fn($day) => $day['counts'][keys::COURSE], $answer['days']);
        // Day 3 to day 12 carry the ten-day event; days 5 and 6 add the event course; day 4 the hidden category's.
        $expected = array_fill(0, count($course), 0);
        for ($day = 3; $day <= 12; $day++) {
            $expected[$day - 1]++;
        }
        $expected[3]++;
        $expected[4]++;
        $expected[5]++;
        $this->assertSame($expected, $course);
        $this->assertSame(1, $answer['days'][1]['counts'][keys::SITE]);
        $this->assertSame(1, $answer['days'][1]['counts'][keys::USER]);
        $this->assertSame(keys::ALL, array_keys($answer['days'][0]['counts']));
    }

    /**
     * Model, modality and city drop course-bound items only; "only things to do" keeps the open due date alone.
     *
     * @return void
     */
    public function test_the_filters(): void {
        $this->seed();
        $ids = static fn(array $answer): array => array_map(
            static fn($item) => $item['source'] . ':' . $item['id'],
            $answer['events']
        );
        $all = $ids($this->answer());
        $site = 'core:' . $this->ids['site'];
        $user = 'core:' . $this->ids['user'];
        $long = 'core:' . $this->ids['long'];
        $eventcourse = 'course:' . $this->ids['event'];

        foreach (
            [
                'model' => [new filters([landingfields::MODEL_EVENT]), [$eventcourse], [$long]],
                'modality' => [new filters([], [landingfields::MODALITY_ONLINE]), [$long], [$eventcourse]],
                'city' => [new filters([], [], '  Natal '), [$eventcourse], [$long]],
            ] as $name => [$filters, $in, $out]
        ) {
            $kept = $ids($this->answer($filters));
            foreach (array_merge($in, [$site, $user]) as $id) {
                $this->assertContains($id, $kept, "$name keeps $id");
            }
            foreach ($out as $id) {
                $this->assertContains($id, $all, "$name control");
                $this->assertNotContains($id, $kept, "$name drops $id");
            }
        }

        $todo = $this->answer(new filters([], [], '', true));
        $this->assertCount(1, $todo['events']);
        $this->assertSame('assign', $todo['events'][0]['modulename']);
        $this->assertSame($this->ids['assign'], $todo['events'][0]['instance']);
        $this->assertTrue($todo['events'][0]['action']['actionable']);
    }

    /**
     * The city is compared after the same cleaning on both sides, so a stored markup or ampersand still matches.
     *
     * @return void
     */
    public function test_the_city_filter_cleans_both_sides_alike(): void {
        global $DB;
        $this->seed();
        $field = landingfields::get_field(landingfields::SHORTNAME_CITY);
        $row = ['fieldid' => $field->get('id'), 'instanceid' => $this->ids['event']];
        $eventcourse = 'course:' . $this->ids['event'];
        $kept = fn(string $city): array => array_map(
            static fn($item) => $item['source'] . ':' . $item['id'],
            $this->answer(new filters([], [], $city))['events']
        );

        $DB->set_field('customfield_data', 'charvalue', 'Sao & Paulo', $row);
        $this->assertContains($eventcourse, $kept('Sao & Paulo'));
        $this->assertNotContains($eventcourse, $kept('Natal'), 'The control: another city drops it.');

        // The service hands the filter its city cleaned as PARAM_TEXT: tags gone. The stored value is raw.
        $DB->set_field('customfield_data', 'charvalue', 'Rio <b>Norte</b>', $row);
        $this->assertContains($eventcourse, $kept(clean_param('Rio <b>Norte</b>', PARAM_TEXT)));
        $this->assertNotContains($eventcourse, $kept('Rio Sul'));
    }
}
