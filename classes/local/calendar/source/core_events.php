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
 * Theme Dimensions - Core's calendar events as range items.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar\source;

use core_calendar\local\api;
use core_calendar\local\event\entities\action_event_interface;
use core_calendar\local\event\entities\event_interface;
use local_unlistedcourses\category_access;
use theme_dimensions\local\calendar\editing;
use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\scope;
use theme_dimensions\local\calendar\source;
use theme_dimensions\local\calendar\window;

/**
 * Core's events in the window, read exactly as core's month view reads them, reduced to slim items.
 *
 * The read is {@see \calendar_get_view()}'s: api::get_events() over the window with no limit, the viewer's
 * calendar sets ({@see scope}), $withduration and $ignorehidden on, and calendar_get_view()'s own closure
 * ({@see visible()}): a module event is kept when its cm_info is uservisible, a category event when the
 * category is is_uservisible(). The event factory has already dropped what core's bail-out check drops
 * (a hidden or unavailable module, a hidden course, a completion-expected event already completed, a category
 * the viewer may not see), and core's retrieval strategy applies the override rule (one row per activity
 * date, the viewer's user override first, then their group's): no override check of the theme's own runs
 * here. The parity test holds the ids against calendar_get_view() per role.
 *
 * What an item carries is an allowlist: no description, no image, no formatted HTML. The name and the
 * location are format_string()'s plain spelling (`escape => false`: tags stripped, nothing escaped), in the
 * event's own context, as core's exporter formats them. The url is the one core's exporter computes
 * ({@see \core_calendar\external\event_exporter::get_other_values()}) where it is the event's own: the
 * activity's view page, the category's page. Every other event links to its course's page, which the answer
 * carries once per course (courses[].url), so the item's url is '' (the site's home when it has no course).
 *
 * A category event whose category local_unlistedcourses withholds from the viewer is dropped ({@see unwithheld()}):
 * a documented difference from core's calendar, which shows it, kept so the range names no category the
 * showcase hides.
 *
 * The entity has no type: an activity event that came back without an action is either a plain event (a
 * quiz's opening) or an action event whose callback returned nothing (submitted, done). Its row is read for
 * that, one statement for the window's activity events ({@see action_rows()}), and the item says it in
 * `actionevent`.
 *
 * Asked with editing on ({@see __construct()}), it also keeps core's editing answers for the events it turned into items
 * ({@see editing::entries()}, read back with {@see editing()}): the entities are here, and nowhere after.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_events implements source {
    /** @var string The source name items carry. */
    public const SOURCE = 'core';

    /** @var bool Whether core's editing answers are read for the items. */
    private bool $withediting;

    /** @var array[] Core's editing answers for the last call's items ({@see editing::entries()}). */
    private array $editing = [];

    /**
     * A source of core's events.
     *
     * @param bool $withediting Whether to read core's editing answers too: the page and its service do, the blocks,
     *     which draw no editing, do not.
     */
    public function __construct(bool $withediting = false) {
        $this->withediting = $withediting;
    }

    /**
     * Core's editing answers for the items of the last call, one per event core lets the viewer edit.
     *
     * @return array[] Entries with exactly the keys of editing::ENTRY_KEYS.
     */
    public function editing(): array {
        return $this->editing;
    }

    /**
     * The keys this source's items can carry.
     *
     * @return string[]
     */
    public function keys(): array {
        return keys::CORE;
    }

    /**
     * The window's events this viewer may see, for the keys that are on.
     *
     * @param window $window The days asked for.
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on.
     * @return array[] Items with exactly the keys of range::ITEM_KEYS.
     */
    public function items(window $window, scope $scope, array $on): array {
        self::prime($scope);
        $events = self::unwithheld(self::events($window, $scope, $on));
        $types = self::action_rows($events);
        $items = [];
        $kept = [];
        $now = time();
        foreach ($events as $event) {
            $key = keys::classify($event);
            if (!in_array($key, $on, true)) {
                continue;
            }
            $items[] = self::item($event, $key, isset($types[(int) $event->get_id()]), $now);
            $kept[] = $event;
        }
        $this->editing = $this->withediting ? editing::entries($kept) : [];
        return $items;
    }

    /**
     * The window's events through api::get_events(), with core's month-view arguments and closure.
     *
     * Public for the tests, which compare its ids with calendar_get_view()'s.
     *
     * @param window $window The days asked for.
     * @param scope $scope The viewer's calendar sets.
     * @param string[] $on The keys that are on; an argument whose keys are all off is emptied ({@see keys::arguments()}).
     * @return event_interface[]
     */
    public static function events(window $window, scope $scope, array $on): array {
        [$users, $groups, $courses, $categories] = keys::arguments($scope, $on);
        return api::get_events(
            $window->tstart,
            $window->tend,
            null,
            null,
            null,
            null,
            0,
            null,
            $users,
            $groups,
            $courses,
            $categories,
            true,
            true,
            [self::class, 'visible']
        );
    }

    /**
     * The events minus those of a category local_unlistedcourses withholds from this viewer, in one batched call.
     *
     * A deliberate difference from core's calendar, which shows such a category event: its category id, name and
     * address would name a category the showcase and the course index hide.
     *
     * @param event_interface[] $events The events core's closure kept.
     * @return event_interface[] The same events, the withheld category's dropped.
     */
    private static function unwithheld(array $events): array {
        $categories = [];
        foreach ($events as $event) {
            if ($proxy = $event->get_category()) {
                $categories[(int) $proxy->get('id')] = $proxy->get_proxied_instance();
            }
        }
        if (!$categories) {
            return $events;
        }
        $listed = category_access::filter_categories($categories);
        return array_values(array_filter($events, static function (event_interface $event) use ($listed): bool {
            $proxy = $event->get_category();
            return !$proxy || isset($listed[(int) $proxy->get('id')]);
        }));
    }

    /**
     * Build modinfo for the viewer's courses from their full records, before the event factory asks for it.
     *
     * The factory's bail-out check calls get_fast_modinfo() with a bare course id, and a modinfo built from an
     * id reads the course row first (get_course()), one statement per course. Built here from one statement for
     * every course, each later call is a hit on modinfo's static cache. That cache holds
     * {@see \course_modinfo::MAX_CACHE_SIZE} courses, so past that number nothing is primed: the factory's
     * events come in date order across the courses and would evict what was primed before it was read.
     * Measured on the C0 probe's shape (range_budget_test): 116 to 112 statements at 5 courses, 531 unchanged at
     * 25. The rest of the per-event cost is the module callbacks' (an assignment's action reads its instance,
     * the viewer's submission and their completion), which no caller can prime.
     *
     * @param scope $scope The viewer's calendar sets.
     * @return void
     */
    private static function prime(scope $scope): void {
        global $DB;
        $ids = $scope->course_ids();
        if (!$ids || count($ids) > \course_modinfo::MAX_CACHE_SIZE) {
            return;
        }
        foreach ($DB->get_records_list('course', 'id', $ids) as $course) {
            get_fast_modinfo($course, $scope->viewerid);
        }
    }

    /**
     * Core's month-view closure, verbatim: the calendar_get_view() filter of lib.php.
     *
     * @param event_interface $event The event.
     * @return bool Whether the viewer sees it.
     */
    public static function visible(event_interface $event): bool {
        if ($proxy = $event->get_course_module()) {
            $cminfo = $proxy->get_proxied_instance();
            return $cminfo->uservisible;
        }
        if ($proxy = $event->get_category()) {
            $category = $proxy->get_proxied_instance();
            return $category->is_uservisible();
        }
        return true;
    }

    /**
     * The activity events among these whose row is an action event, in one statement.
     *
     * Only events the entity cannot answer for are asked about: an action_event_interface is one already.
     * Rides the primary key of {event}; bounded by the window's activity events.
     *
     * @param event_interface[] $events The events.
     * @return array event id => true, for each one whose row's type is CALENDAR_EVENT_TYPE_ACTION.
     */
    private static function action_rows(array $events): array {
        global $DB;

        $ids = [];
        $types = [];
        foreach ($events as $event) {
            if ($event instanceof action_event_interface) {
                $types[(int) $event->get_id()] = true;
            } else if ($event->get_course_module()) {
                $ids[] = (int) $event->get_id();
            }
        }
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ev');
            $params['action'] = CALENDAR_EVENT_TYPE_ACTION;
            foreach ($DB->get_fieldset_select('event', 'id', "id {$insql} AND type = :action", $params) as $id) {
                $types[(int) $id] = true;
            }
        }
        return $types;
    }

    /**
     * One event as a range item.
     *
     * @param event_interface $event The event.
     * @param string $key Its key.
     * @param bool $actionevent Whether its row is an action event.
     * @param int $now The time "overdue" is judged against.
     * @return array An item with exactly the keys of range::ITEM_KEYS.
     */
    private static function item(event_interface $event, string $key, bool $actionevent, int $now): array {
        $times = $event->get_times();
        $start = $times->get_start_time()->getTimestamp();
        $courseid = (int) $event->get_course()->get('id');
        $cmproxy = $event->get_course_module();
        $cm = $cmproxy ? $cmproxy->get_proxied_instance() : null;
        $categoryproxy = $event->get_category();
        $categoryid = $categoryproxy ? (int) $categoryproxy->get('id') : 0;
        $groupproxy = $event->get_group();
        $context = self::context($event, $cm, $courseid, $categoryid);

        // The course's page, the commonest answer, travels once per course in the dictionary: '' here.
        $url = '';
        if ($cm) {
            $url = (new \core\url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false);
        } else if ($event->get_type() === 'category' && $categoryid) {
            $url = $categoryproxy->get_proxied_instance()->get_view_link()->out(false);
        }

        $action = null;
        $overdue = false;
        if ($event instanceof action_event_interface) {
            $eventaction = $event->get_action();
            $action = [
                'name' => (string) $eventaction->get_name(),
                'url' => $eventaction->get_url()->out(false),
                'itemcount' => (int) $eventaction->get_item_count(),
                'actionable' => (bool) $eventaction->is_actionable(),
            ];
            $overdue = $action['actionable'] && $times->get_sort_time()->getTimestamp() < $now;
        }

        return range::item([
            'id' => (int) $event->get_id(),
            'source' => self::SOURCE,
            'key' => $key,
            'name' => format_string($event->get_name(), true, ['context' => $context, 'escape' => false]),
            'timestart' => $start,
            'timeduration' => $times->get_end_time()->getTimestamp() - $start,
            'courseid' => $courseid,
            'categoryid' => $categoryid,
            'groupid' => $groupproxy ? (int) $groupproxy->get('id') : 0,
            'modulename' => $cm ? (string) $cm->modname : '',
            'instance' => $cm ? (int) $cm->instance : 0,
            'component' => (string) $event->get_component(),
            'eventtype' => (string) $event->get_type(),
            'location' => format_string((string) $event->get_location(), true, ['context' => $context, 'escape' => false]),
            'url' => $url,
            'actionevent' => $actionevent,
            'action' => $action,
            'overdue' => $overdue,
            'held' => false,
        ]);
    }

    /**
     * The context an event's text is formatted in: its module's, its category's, its course's, else the system's.
     *
     * The module's comes with modinfo, which the event factory already loaded; the others are core's context
     * caches.
     *
     * @param event_interface $event The event.
     * @param \cm_info|null $cm Its module, when it has one.
     * @param int $courseid Its course, 0 for none.
     * @param int $categoryid Its category, 0 for none.
     * @return \core\context
     */
    private static function context(event_interface $event, ?\cm_info $cm, int $courseid, int $categoryid): \core\context {
        if ($cm) {
            return $cm->context;
        }
        if ($categoryid) {
            return \core\context\coursecat::instance($categoryid);
        }
        if ($courseid && $courseid !== (int) SITEID) {
            return \core\context\course::instance($courseid);
        }
        $user = $event->get_user();
        if ($user && $event->get_type() === keys::USER) {
            return \core\context\user::instance((int) $user->get('id'));
        }
        return \core\context\system::instance();
    }
}
