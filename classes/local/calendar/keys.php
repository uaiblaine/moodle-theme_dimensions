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
 * Theme Dimensions - The calendar's event keys: the vocabulary, the classification, what the vault is asked.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use core_calendar\local\event\entities\event_interface;

/**
 * The eight keys an item of the calendar range carries, and which of them the event vault can leave out for us.
 *
 * Six keys are core's columns read the way core's exporter normalises them
 * ({@see \core_calendar\external\event_exporter_base::get_other_values()}): an event of a course module is an
 * activity, whatever its eventtype (a due date, a user or a group override); an event that names a component
 * and no module is "other" (an enrol plugin's, a competency's), whose eventtype string core cannot name
 * either; a site event, or a course event of the site course, is "site"; otherwise the eventtype is
 * the key (user, group, course, category), and an eventtype none of those names is "other". Two keys are the
 * theme's own sources: "course" also holds the event courses the viewer is enrolled in, and "open" the event
 * courses the viewer could join ({@see source\event_courses}).
 *
 * A key that is off is never answered: every item is classified and dropped after the vault (the post-filter,
 * always run). Where it is safe, the vault is not even asked ({@see arguments()}): an argument of
 * api::get_events() is emptied only when every key it can feed is off, because core's retrieval strategy
 * ({@see \core_calendar\local\event\strategies\raw_event_retrieval_strategy}) uses its arguments for more than
 * the rows they name:
 *
 * - users feeds the user events, the user overrides of an activity (courseid 0, picked by the MIN(priority)
 *   subquery: without them the viewer would be shown the activity's general date instead of their own), the
 *   group events (the subquery's group condition is built only inside the users branch, so with no user the
 *   group events find no partner row and vanish) and component events of the user;
 * - groups feeds the group events and the group overrides of an activity (left out, the override still wins
 *   the subquery and the activity's date disappears altogether) and component events of a group;
 * - the course ids feed course, activity and component events; the site course among them feeds site events,
 *   front page activities and component events of the site;
 * - categories feed category events and component events of a category.
 *
 * So "other" on keeps every argument, and each of the others is a drop only together with its companions;
 * keys_test pins every row of that table against the vault's real answer.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class keys {
    /** @var string Site events, and course events of the site course. */
    public const SITE = 'site';
    /** @var string Category events. */
    public const CATEGORY = 'category';
    /** @var string Course events, and the event courses the viewer is enrolled in. */
    public const COURSE = 'course';
    /** @var string Group events. */
    public const GROUP = 'group';
    /** @var string The viewer's own events. */
    public const USER = 'user';
    /** @var string Every event of a course module: due dates, openings, overrides, completion expected. */
    public const ACTIVITY = 'activity';
    /** @var string Events a component writes without a module. */
    public const OTHER = 'other';
    /** @var string Event courses the viewer is not enrolled in and could join now. */
    public const OPEN = 'open';

    /** @var string[] The whole vocabulary, in the order the chips are drawn. */
    public const ALL = [
        self::SITE,
        self::CATEGORY,
        self::COURSE,
        self::GROUP,
        self::USER,
        self::ACTIVITY,
        self::OTHER,
        self::OPEN,
    ];

    /** @var string[] The keys core's events can carry: when all are off, the vault is not called at all. */
    public const CORE = [
        self::SITE,
        self::CATEGORY,
        self::COURSE,
        self::GROUP,
        self::USER,
        self::ACTIVITY,
        self::OTHER,
    ];

    /** @var array The keys each argument of api::get_events() can feed; see the class docblock. */
    private const FEEDS = [
        'users' => [self::USER, self::ACTIVITY, self::GROUP, self::OTHER],
        'groups' => [self::GROUP, self::ACTIVITY, self::OTHER],
        'courses' => [self::COURSE, self::ACTIVITY, self::OTHER],
        'site' => [self::SITE, self::ACTIVITY, self::OTHER],
        'categories' => [self::CATEGORY, self::OTHER],
    ];

    /**
     * The keys that are on, validated: an empty list means every key; a name outside the vocabulary is refused.
     *
     * @param string[] $names The keys asked for.
     * @return string[] The keys on, in vocabulary order.
     * @throws refusal On a name outside the vocabulary.
     */
    public static function parse(array $names): array {
        if (!$names) {
            return self::ALL;
        }
        foreach ($names as $name) {
            if (!in_array($name, self::ALL, true)) {
                throw new refusal();
            }
        }
        return array_values(array_intersect(self::ALL, $names));
    }

    /**
     * The key of one of core's events.
     *
     * @param event_interface $event The event, as api::get_events() returns it.
     * @return string One of the CORE keys.
     */
    public static function classify(event_interface $event): string {
        if ($event->get_course_module()) {
            return self::ACTIVITY;
        }
        if ((string) $event->get_component() !== '') {
            return self::OTHER;
        }
        $type = (string) $event->get_type();
        $course = $event->get_course();
        if ($type === 'site' || ($course && (int) $course->get('id') === (int) SITEID && $type === 'course')) {
            return self::SITE;
        }
        if (in_array($type, [self::USER, self::GROUP, self::COURSE, self::CATEGORY], true)) {
            return $type;
        }
        return self::OTHER;
    }

    /**
     * The users, groups, courses and categories arguments of api::get_events() for these keys.
     *
     * The scope's own sets, with an argument emptied only where every key it feeds is off ({@see FEEDS}).
     * An emptied argument is [] (core's "none"), never null (core's "all").
     *
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on.
     * @return array [users, groups, courses, categories], each array|null as api::get_events() takes it.
     */
    public static function arguments(scope $scope, array $on): array {
        $off = static fn(string $argument): bool => !array_intersect(self::FEEDS[$argument], $on);

        $users = $off('users') ? [] : $scope->users;
        $groups = $off('groups') ? [] : $scope->groups;
        $courses = $scope->courseids;
        if (is_array($courses)) {
            $dropcourses = $off('courses');
            $dropsite = $off('site');
            $courses = array_values(array_filter($courses, static function ($courseid) use ($dropcourses, $dropsite): bool {
                return ((int) $courseid === (int) SITEID) ? !$dropsite : !$dropcourses;
            }));
        }
        $categories = $off('categories') ? [] : $scope->categories;
        return [$users, $groups, $courses, $categories];
    }
}
