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
 * Theme Dimensions - The calendar's keys: classification, the post-filter and the vault-level drops
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\source\core_events;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Each key off drops exactly its items, and an argument the vault is spared changes nothing else.
 *
 * The fixture carries one item of every key, and the two rows that make the vault-level drops dangerous: a
 * user override and a group override of an assignment's due date, which core resolves through the users and
 * the groups arguments (keys' class docblock).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(keys::class)]
#[CoversClass(range::class)]
final class keys_test extends \advanced_testcase {
    use fixture;

    /**
     * A student with one item of every key, and two overridden due dates.
     *
     * @return array [student, ids by name]
     */
    private function seed(): array {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id, 'groupmode' => SEPARATEGROUPS]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $ids = [];
        $ids['site'] = (int) $generator->create_event(['eventtype' => 'site', 'timestart' => self::at(2)])->id;
        $ids['category'] = (int) $generator->create_event([
            'eventtype' => 'category',
            'categoryid' => $category->id,
            'timestart' => self::at(3),
        ])->id;
        $ids['course'] = (int) $generator->create_event([
            'eventtype' => 'course',
            'courseid' => $course->id,
            'timestart' => self::at(4),
        ])->id;
        $ids['group'] = (int) $generator->create_event([
            'eventtype' => 'group',
            'courseid' => $course->id,
            'groupid' => $group->id,
            'timestart' => self::at(5),
        ])->id;
        $ids['user'] = (int) $generator->create_event([
            'eventtype' => 'user',
            'userid' => $student->id,
            'timestart' => self::at(6),
        ])->id;
        $ids['other'] = (int) $generator->create_event([
            'eventtype' => 'course',
            'courseid' => $course->id,
            'component' => 'enrol_self',
            'timestart' => self::at(7),
        ])->id;

        /** @var \mod_assign_generator $assigns */
        $assigns = $generator->get_plugin_generator('mod_assign');
        $plain = $generator->create_module('assign', ['course' => $course->id, 'duedate' => self::at(8, 17)]);
        $ids['activity'] = (int) $DB->get_field('event', 'id', ['modulename' => 'assign', 'instance' => $plain->id,
            'eventtype' => 'due']);
        $useroverridden = $generator->create_module('assign', ['course' => $course->id, 'duedate' => self::at(10, 17)]);
        $assigns->create_override(['assignid' => $useroverridden->id, 'userid' => $student->id, 'duedate' => self::at(11, 17)]);
        $ids['useroverride'] = (int) $DB->get_field('event', 'id', ['modulename' => 'assign',
            'instance' => $useroverridden->id, 'userid' => $student->id, 'eventtype' => 'due']);
        $groupoverridden = $generator->create_module('assign', ['course' => $course->id, 'duedate' => self::at(12, 17)]);
        $assigns->create_override([
            'assignid' => $groupoverridden->id,
            'groupid' => $group->id,
            'duedate' => self::at(13, 17),
            'sortorder' => 1,
        ]);
        $ids['groupoverride'] = (int) $DB->get_field('event', 'id', ['modulename' => 'assign',
            'instance' => $groupoverridden->id, 'groupid' => $group->id, 'eventtype' => 'due']);

        return [$student, $ids];
    }

    /**
     * The (source, id, key) of every item of an answer.
     *
     * @param array $answer A range answer.
     * @return string[] Sorted.
     */
    private static function signature(array $answer): array {
        $out = [];
        foreach ($answer['events'] as $item) {
            $out[] = $item['source'] . ':' . $item['id'] . ':' . $item['key'];
        }
        sort($out);
        return $out;
    }

    /**
     * The answer for these keys, as a new request would compute it.
     *
     * @param \stdClass $user The viewer.
     * @param string[] $on The keys.
     * @return array
     */
    private function answer(\stdClass $user, array $on): array {
        $this->as_viewer($user);
        return range::answer(self::month_window(), scope::for_viewer(), $on, new filters());
    }

    /**
     * Every key, then each key off in turn: what goes is exactly the items of that key.
     *
     * @return void
     */
    public function test_each_key_off_drops_exactly_its_items(): void {
        [$student, $ids] = $this->seed();
        $all = $this->answer($student, keys::ALL);

        $bykey = [];
        foreach ($all['events'] as $item) {
            $bykey[$item['key']][] = (int) $item['id'];
        }
        // The controls: every core key is present, and each override answers instead of its activity's own date.
        foreach (keys::CORE as $key) {
            $this->assertArrayHasKey($key, $bykey, $key);
        }
        foreach (['site', 'category', 'course', 'group', 'user', 'other', 'activity'] as $name) {
            $this->assertContains($ids[$name], $bykey[$name], $name);
        }
        $this->assertContains($ids['useroverride'], $bykey[keys::ACTIVITY]);
        $this->assertContains($ids['groupoverride'], $bykey[keys::ACTIVITY]);

        foreach (keys::CORE as $key) {
            $expected = array_values(array_filter(self::signature($all), static fn($s) => !str_ends_with($s, ':' . $key)));
            $this->assertSame($expected, self::signature($this->answer($student, array_diff(keys::ALL, [$key]))), $key);
        }
    }

    /**
     * An argument the vault is spared is emptied, never nulled, and only when every key it feeds is off.
     *
     * @return void
     */
    public function test_the_vault_is_spared_an_argument_only_when_every_key_it_feeds_is_off(): void {
        [$student] = $this->seed();
        $this->as_viewer($student);
        $scope = scope::for_viewer();
        // Core's course list is keyed from 1 once the site course is moved last; the argument is a plain list.
        $full = [$scope->users, $scope->groups, array_values($scope->courseids), $scope->categories];
        $nosite = array_values(array_diff($scope->courseids, [(int) SITEID]));

        $this->assertSame($full, keys::arguments($scope, keys::ALL));
        // One key off alone spares nothing: "other" still feeds every argument.
        foreach (keys::CORE as $key) {
            $this->assertSame($full, keys::arguments($scope, array_diff(keys::ALL, [$key])), $key);
        }
        $cases = [
            'categories' => [[keys::CATEGORY, keys::OTHER], [$scope->users, $scope->groups, $full[2], []]],
            'users and groups' => [
                [keys::USER, keys::GROUP, keys::ACTIVITY, keys::OTHER],
                [[], [], $full[2], $scope->categories],
            ],
            'site' => [[keys::SITE, keys::ACTIVITY, keys::OTHER, keys::GROUP], [$scope->users, [], $nosite, $scope->categories]],
            'courses' => [[keys::COURSE, keys::ACTIVITY, keys::OTHER, keys::GROUP], [$scope->users, [], [(int) SITEID],
                $scope->categories]],
            'users kept for the groups' => [[keys::USER, keys::ACTIVITY, keys::OTHER], [$scope->users, $scope->groups,
                $full[2], $scope->categories]],
        ];
        foreach ($cases as $name => [$off, $expected]) {
            $this->assertEquals($expected, keys::arguments($scope, array_values(array_diff(keys::ALL, $off))), $name);
        }
        $this->assertContains((int) SITEID, array_map('intval', $scope->courseids));
        $this->assertNotEmpty($scope->users);
        $this->assertNotEmpty($scope->groups);
    }

    /**
     * With arguments spared, every key still on answers exactly what it answered with nothing spared.
     *
     * @return void
     */
    public function test_a_spared_argument_changes_no_other_key(): void {
        [$student] = $this->seed();
        $all = $this->answer($student, keys::ALL);
        foreach (
            [
                [keys::CATEGORY, keys::OTHER],
                [keys::USER, keys::GROUP, keys::ACTIVITY, keys::OTHER],
                [keys::SITE, keys::ACTIVITY, keys::OTHER, keys::GROUP],
                [keys::COURSE, keys::ACTIVITY, keys::OTHER, keys::GROUP],
                // Nothing is spared here: the users argument still feeds the group events (the class docblock of keys).
                [keys::USER, keys::ACTIVITY, keys::OTHER],
                [keys::GROUP, keys::ACTIVITY, keys::OTHER],
                // Nor here: the users and the groups arguments carry the overrides of an activity's date.
                [keys::USER, keys::GROUP, keys::OTHER],
            ] as $off
        ) {
            $expected = array_values(array_filter(self::signature($all), static function (string $s) use ($off): bool {
                return !in_array(substr($s, strrpos($s, ':') + 1), $off, true);
            }));
            $on = array_values(array_diff(keys::ALL, $off));
            $this->assertSame($expected, self::signature($this->answer($student, $on)), implode(',', $off));
        }
    }

    /**
     * Why "other" keeps the users argument: without the viewer, core answers the activity's own date, not theirs.
     *
     * @return void
     */
    public function test_the_users_argument_carries_the_viewers_override(): void {
        global $DB;
        [$student, $ids] = $this->seed();
        $this->as_viewer($student);
        $window = self::month_window();
        $scope = scope::for_viewer();
        $instance = $DB->get_field('event', 'instance', ['id' => $ids['useroverride']]);
        $base = (int) $DB->get_field_select(
            'event',
            'id',
            "modulename = 'assign' AND instance = :instance AND eventtype = 'due' AND priority IS NULL",
            ['instance' => $instance]
        );
        $this->assertNotSame(0, $base);

        $with = array_map(static fn($e) => (int) $e->get_id(), core_events::events($window, $scope, keys::ALL));
        $this->as_viewer($student);
        $without = array_map(static fn($e) => (int) $e->get_id(), \core_calendar\local\api::get_events(
            $window->tstart,
            $window->tend,
            null,
            null,
            null,
            null,
            0,
            null,
            [],
            $scope->groups,
            $scope->courseids,
            $scope->categories,
            true,
            true,
            [core_events::class, 'visible']
        ));

        $this->assertContains($ids['useroverride'], $with);
        $this->assertNotContains($base, $with);
        $this->assertNotContains($ids['useroverride'], $without);
        $this->assertContains($base, $without);
    }

    /**
     * A key outside the vocabulary is the one refusal; none at all is every key.
     *
     * @return void
     */
    public function test_parse(): void {
        $this->assertSame(keys::ALL, keys::parse([]));
        $this->assertSame([keys::SITE, keys::OPEN], keys::parse([keys::OPEN, keys::SITE, keys::OPEN]));
        $this->expectException(refusal::class);
        keys::parse([keys::SITE, 'holidays']);
    }
}
