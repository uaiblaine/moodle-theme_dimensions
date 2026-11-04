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
 * Theme Dimensions - Tests for the public event courses a visitor is shown, and the visitor's whole answer
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\source\event_courses;
use theme_dimensions\local\calendar\source\public_courses;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\publicaccess;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The visitor's event courses and {@see publicrange::answer()}.
 *
 * Every course that may not be shown has its control: the same course answered once the one reason is gone.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(public_courses::class)]
#[CoversClass(publicrange::class)]
#[CoversClass(publicaccess::class)]
final class public_courses_test extends \advanced_testcase {
    use fixture;

    /**
     * The ids a visitor is answered for next month, as a new request.
     *
     * @return int[]
     */
    private function answered(): array {
        $this->setUser(null);
        self::forget_calendar();
        return self::item_ids((new public_courses())->items(self::month_window()), 'course');
    }

    /**
     * Publish a course, as somebody who may.
     *
     * @param int $courseid The course.
     * @param int $state The state.
     * @return void
     */
    private static function state(int $courseid, int $state): void {
        discoverability::set_state($courseid, $state, (int) get_admin()->id);
    }

    /**
     * Only an event course whose landing page a visitor may open, each refusal beside its control.
     *
     * @return void
     */
    public function test_only_a_public_event_course_is_answered(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = (int) self::getDataGenerator()->create_category()->id;
        $course = self::event_course($category, 5, 7, ['fullname' => 'Congresso & Feira']);
        $id = (int) $course->id;
        $this->assertSame([], $this->answered(), 'A listed course is not public.');
        self::state($id, discoverability::STATE_PUBLIC);
        $this->assertSame([$id], $this->answered(), 'Control: published, it is answered.');

        $this->setAdminUser();
        self::state($id, discoverability::STATE_UNLISTED);
        $this->assertSame([], $this->answered(), 'An unlisted course.');
        self::state($id, discoverability::STATE_PUBLIC);

        $DB->set_field('course', 'visible', 0, ['id' => $id]);
        $this->assertSame([], $this->answered(), 'A hidden course.');
        $DB->set_field('course', 'visible', 1, ['id' => $id]);
        $this->assertSame([$id], $this->answered(), 'Control: shown again.');

        $DB->set_field('course_categories', 'visible', 0, ['id' => $category]);
        $this->assertSame([], $this->answered(), 'A course in a hidden category.');
        $DB->set_field('course_categories', 'visible', 1, ['id' => $category]);

        $this->setAdminUser();
        category_discoverability::set_state($category, category_discoverability::STATE_UNLISTED, (int) get_admin()->id);
        $this->assertSame([], $this->answered(), 'A course in an unlisted category.');
        category_discoverability::set_state($category, category_discoverability::STATE_DEFAULT, (int) get_admin()->id);
        $this->assertSame([$id], $this->answered(), 'Control: the category listed again.');

        self::set_model($id, landingfields::MODEL_COURSE);
        $this->assertSame([], $this->answered(), 'A public course of the course model is no event.');
        self::set_model($id, landingfields::MODEL_EVENT);
        $this->assertSame([$id], $this->answered(), 'Control: the event model.');

        $DB->set_field('course', 'startdate', self::month_window()->tend + 1, ['id' => $id]);
        $DB->set_field('course', 'enddate', self::month_window()->tend + DAYSECS, ['id' => $id]);
        $this->assertSame([], $this->answered(), 'An event course after the window.');
    }

    /**
     * An item and its course's entry: the landing page, "Inscrever-se", and nothing a visitor may not be told.
     *
     * @return void
     */
    public function test_an_event_course_leads_to_its_landing_page(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $public = (int) self::getDataGenerator()->create_category(['name' => 'Publica'])->id;
        $listed = (int) self::getDataGenerator()->create_category(['name' => 'Listada'])->id;
        category_discoverability::set_state($public, category_discoverability::STATE_PUBLIC, (int) get_admin()->id);
        $inpublic = self::event_course($public, 3, 4, ['fullname' => 'Feira & Cia', 'shortname' => 'FC']);
        $inlisted = self::event_course($listed, 8, 9, ['fullname' => 'Congresso']);
        $student = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($student->id, $inpublic->id, 'student');
        foreach ([$inpublic, $inlisted] as $course) {
            self::state((int) $course->id, discoverability::STATE_PUBLIC);
        }
        $this->setUser(null);
        self::forget_calendar();

        $answer = publicrange::answer(self::month_window(), [keys::OPEN]);
        $this->assertSame([(int) $inpublic->id, (int) $inlisted->id], array_column($answer['events'], 'id'));
        $item = $answer['events'][0];
        $this->assertSame(range::ITEM_KEYS, array_keys($item));
        $this->assertSame([keys::OPEN, 'course'], [$item['key'], $item['source']]);
        $this->assertSame(links::page((int) $inpublic->id)->out(false), $item['url']);
        $this->assertSame('Feira & Cia', $item['name'], 'The plain spelling.');

        $courses = array_column($answer['courses'], null, 'id');
        $entry = $courses[(int) $inpublic->id];
        $this->assertSame($item['url'], $entry['url'], 'The landing page is the course\'s one page a visitor opens.');
        $this->assertSame($item['url'], $entry['landingurl']);
        $this->assertSame(['', null, false, 'open'], [$entry['image'], $entry['participants'], $entry['enrolled'],
            $entry['join']], 'No image, no participant count (one is enrolled), the way in.');
        $this->assertSame($public, $entry['categoryid'], 'A public category is named.');
        $this->assertSame(0, $courses[(int) $inlisted->id]['categoryid'], 'A listed one is not.');
        $this->assertSame([$public], array_column($answer['categories'], 'id'));
        $this->assertSame('Publica', $answer['categories'][0]['name']);
        $this->assertSame([], $answer['descriptions']);
    }

    /**
     * The keys: each source is asked only for its key, and none asks nothing; the days count what was answered.
     *
     * @return void
     */
    public function test_each_key_asks_its_own_source(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = (int) self::getDataGenerator()->create_category()->id;
        category_discoverability::set_state($category, category_discoverability::STATE_PUBLIC, (int) get_admin()->id);
        $course = self::event_course($category, 5, 5);
        self::state((int) $course->id, discoverability::STATE_PUBLIC);
        $event = self::getDataGenerator()->create_event([
            'eventtype' => 'category',
            'categoryid' => $category,
            'timestart' => self::at(6),
            'description' => 'Texto',
        ]);
        $this->setUser(null);
        $window = self::month_window();
        $both = publicrange::answer($window, visitor::KEYS);
        $this->assertSame([[(int) $course->id, 'course'], [(int) $event->id, 'core']], array_map(
            static fn(array $item): array => [$item['id'], $item['source']],
            $both['events']
        ));
        $this->assertSame([['id' => (int) $event->id, 'text' => 'Texto']], $both['descriptions']);
        $this->assertSame(['core'], array_column(publicrange::answer($window, [keys::CATEGORY])['events'], 'source'));
        $this->assertSame(['course'], array_column(publicrange::answer($window, [keys::OPEN])['events'], 'source'));
        $none = publicrange::answer($window, []);
        $this->assertSame([], $none['events']);
        $this->assertSame([], publicrange::answer($window, [keys::SITE, keys::USER])['events'], 'A key outside the two.');
        $counts = array_column($both['days'], 'counts', 'date');
        $this->assertSame(1, $counts[self::month_start()->modify('+5 days')->format('Y-m-d')][keys::CATEGORY]);
        $this->assertSame(1, $counts[self::month_start()->modify('+4 days')->format('Y-m-d')][keys::OPEN]);
    }

    /**
     * At most OPEN_CAP courses are answered, and truncated says when more were left; one fewer is whole.
     *
     * @return void
     */
    public function test_the_cap(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = (int) self::getDataGenerator()->create_category()->id;
        $ids = [];
        for ($i = 0; $i <= event_courses::OPEN_CAP; $i++) {
            $course = self::event_course($category, 1 + ($i % 27), 0);
            self::state((int) $course->id, discoverability::STATE_PUBLIC);
            $ids[] = (int) $course->id;
        }
        $this->setUser(null);
        self::forget_calendar();
        $source = new public_courses();
        $this->assertCount(event_courses::OPEN_CAP, $source->items(self::month_window()));
        $this->assertTrue($source->truncated());

        $this->setAdminUser();
        self::state((int) end($ids), discoverability::STATE_DEFAULT);
        $this->setUser(null);
        self::forget_calendar();
        $this->assertCount(event_courses::OPEN_CAP, $source->items(self::month_window()));
        $this->assertFalse($source->truncated(), 'Control: exactly the cap is whole.');
    }

    /**
     * Withheld courses cost no place among the candidates: a public course after OPEN_CANDIDATES listed ones is answered.
     *
     * The statement reads only the courses whose own state row says public, so the listed ones before it are never
     * candidates. The control is the same listed courses, one of them published, answered beside it.
     *
     * @return void
     */
    public function test_withheld_courses_cost_no_place(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $category = (int) self::getDataGenerator()->create_category()->id;
        $listed = [];
        for ($i = 0; $i < event_courses::OPEN_CANDIDATES; $i++) {
            $listed[] = (int) self::event_course($category, 1, 0)->id;
        }
        $public = self::event_course($category, 2, 0);
        self::state((int) $public->id, discoverability::STATE_PUBLIC);
        self::state($listed[0], discoverability::STATE_PUBLIC);
        $this->assertSame([$listed[0], (int) $public->id], $this->answered());
    }

    /**
     * The landing gate in bulk fails closed and never answers the site, beside the same ids answered.
     *
     * @return void
     */
    public function test_the_landing_gate_in_bulk(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = self::getDataGenerator()->create_course();
        self::state((int) $course->id, discoverability::STATE_PUBLIC);
        $ids = [(int) $course->id, SITEID, 0, -4];
        $this->assertSame([(int) $course->id], publicaccess::are_public($ids));
        $this->assertSame([], publicaccess::are_public($ids, '\\local_unlistedcourses\\no_such_class'), 'Fails closed.');
        $this->assertSame([], publicaccess::are_public([]));
        $this->assertSame(publicaccess::is_public((int) $course->id), in_array((int) $course->id, publicaccess::are_public($ids)));
    }
}
