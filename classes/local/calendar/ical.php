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
 * Theme Dimensions - Core's iCal feed, with the theme's withholding.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use theme_dimensions\local\calendar\source\core_events;

/**
 * A copy of core's calendar/export_execute.php, answered in its place.
 *
 * Core's script exports the category events of every category for `preset_what=all` and `categories` (it hands core's
 * retrieval `$paramcategory = true`, read as "all categories"), so a category event the theme's calendar withholds
 * (local_unlistedcourses' category_access, {@see core_events::shown_categories()}) or core hides from the viewer (a
 * hidden category) would reach the feed.
 * The theme's after_config callback answers the script's address with {@see serve()} while {@see coreview::enabled()}
 * holds.
 *
 * The copy keeps core's parameters, its token check ({@see authenticated()}, line for line, the loose comparisons
 * included), its periods and its output byte for byte, split into three methods so each part has a test. What it
 * changes:
 *
 * - the events go through {@see admitted()} before they are serialised: an event of a category the range withholds,
 *   or of a category that no longer exists, is left out. Course events are kept as the range keeps them: the range
 *   reads the viewer's own calendar courses and withholds none of them, and the feed reads the viewer's enrolled
 *   courses (and, from a course calendar, a course the viewer can access), so nothing more is withheld there;
 * - an empty `preset_what` or `preset_time`, on which core's script ends in a TypeError (its period and its sets are
 *   never assigned), is sent to the export page, as an unknown one is.
 *
 * The snapshot beside this file (ical.php.export_execute.upstream, {@see \theme_dimensions\local\upstream::FILES}) is
 * core's file as this copy was last read against it: upstream_test reddens the moment core changes it.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ical {
    /** @var string[] The values of preset_what core accepts. */
    public const WHAT = ['all', 'user', 'groups', 'courses', 'categories'];

    /** @var string[] The values of preset_time core accepts. */
    public const TIME = ['weeknow', 'weeknext', 'monthnow', 'monthnext', 'recentupcoming', 'custom'];

    /**
     * Answer the request as core's script does: the feed, a refusal in plain text, or a redirect; the request ends.
     *
     * @return void
     */
    public static function serve(): void {
        global $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');
        require_once($CFG->libdir . '/bennu/bennu.inc.php');

        raise_memory_limit(MEMORY_HUGE);

        $userid = optional_param('userid', 0, PARAM_INT);
        $username = optional_param('username', '', PARAM_TEXT);
        $authtoken = required_param('authtoken', PARAM_ALPHANUM);
        $generateurl = optional_param('generateurl', '', PARAM_TEXT);
        $courseid = optional_param('course', 0, PARAM_INT);

        if (empty($CFG->enablecalendarexport)) {
            die('no export');
        }

        $user = self::authenticated($userid, $username, $authtoken);
        if ($user === null) {
            die('Invalid authentication');
        }

        $what = optional_param('preset_what', 'all', PARAM_ALPHA);
        $time = optional_param('preset_time', 'weeknow', PARAM_ALPHA);

        if (!empty($generateurl)) {
            self::start_session($user);
            $authtoken = calendar_get_export_token($user);
            $params = [];
            $params['preset_what'] = $what;
            $params['preset_time'] = $time;
            $params['userid'] = $userid;
            $params['authtoken'] = $authtoken;
            $params['generateurl'] = true;
            if (!empty($courseid) && $courseid != SITEID) {
                $params['course'] = $courseid;
            }

            $link = new \core\url('/calendar/export.php', $params);
            redirect($link->out());
            die;
        }

        $serialized = self::feed_as($user, $userid, $what, $time, $courseid);
        if ($serialized === null) {
            // Parameters given but incorrect, redirect back to export page.
            redirect($CFG->wwwroot . '/calendar/export.php');
            die();
        }
        if (empty($serialized)) {
            die('bad serialization');
        }

        $filename = 'icalexport.ics';

        if (!defined('BEHAT_SITE_RUNNING')) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
            header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . 'GMT');
            header('Pragma: no-cache');
            header('Accept-Ranges: none');
            header('Content-disposition: attachment; filename=' . $filename);
            header('Content-length: ' . strlen($serialized));
            header('Content-type: text/calendar; charset=utf-8');
        }

        echo $serialized;
    }

    /**
     * Make the account the request's user, including web access logging, and give the page its context.
     *
     * @param \stdClass $user The account the token named.
     * @return void
     */
    private static function start_session(\stdClass $user): void {
        global $PAGE;

        \core\session\manager::set_user($user);
        $PAGE->set_context(\core\context\system::instance());
    }

    /**
     * The feed of the account the token named, read as that account: the session first, then {@see feed()}.
     *
     * The order is the contract: the feed's filter reads $USER (core's visibility of a category), so the user must be
     * the token's before it runs. serve() only sends what this returns.
     *
     * @param \stdClass $user The account the token named.
     * @param int $userid The userid parameter.
     * @param string $what The set, one of {@see WHAT}.
     * @param string $time The period, one of {@see TIME}.
     * @param int $courseid The course of a course calendar's export, 0 or SITEID for none.
     * @return string|null The iCalendar text, or null for a set or a period core does not accept.
     */
    public static function feed_as(\stdClass $user, int $userid, string $what, string $time, int $courseid): ?string {
        self::start_session($user);
        return self::feed($user, $userid, $what, $time, $courseid);
    }

    /**
     * The account a request's credentials name, when its token is the one core issued; core's two checks, verbatim.
     *
     * The user id and core's token, or (the fallback of old addresses, MDL-27542) the username and the hash of the
     * password with the export salt. The comparisons are core's own loose ones.
     *
     * @param int $userid The userid parameter.
     * @param string $username The username parameter.
     * @param string $authtoken The authtoken parameter.
     * @return \stdClass|null The account, or null where core answers "Invalid authentication".
     */
    public static function authenticated(int $userid, string $username, string $authtoken): ?\stdClass {
        global $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $user = false;
        $checkuserid = !empty($userid) && $user = \core_user::get_user($userid);
        // Allowing for fallback check of old url - MDL-27542.
        $checkusername = !empty($username) && $user = \core_user::get_user_by_username($username);
        if ((!$checkuserid && !$checkusername) || !$user) {
            // No such user.
            return null;
        }

        // Check authentication token.
        $authuserid = !empty($userid) && $authtoken == calendar_get_export_token($user);
        // Allowing for fallback check of old url - MDL-27542.
        $authusername = !empty($username) && $authtoken == sha1($username . $user->password . $CFG->calendar_exportsalt);
        if (!$authuserid && !$authusername) {
            return null;
        }
        return $user;
    }

    /**
     * The feed of an account for a set and a period, serialised: core's selection and serialisation, the events admitted
     * by the theme's rule first.
     *
     * @param \stdClass $user The account, already the session's ({@see feed_as()}).
     * @param int $userid The userid parameter, which core hands get_fast_modinfo() as written (0 for the old addresses).
     * @param string $what The set, one of {@see WHAT}.
     * @param string $time The period, one of {@see TIME}.
     * @param int $courseid The course of a course calendar's export, 0 or SITEID for none.
     * @return string|null The iCalendar text, or null for a set or a period core does not accept.
     * @throws \coding_exception When an event carries a negative duration.
     */
    public static function feed(\stdClass $user, int $userid, string $what, string $time, int $courseid): ?string {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/calendar/lib.php');
        require_once($CFG->libdir . '/bennu/bennu.inc.php');

        if (empty($what) || empty($time) || !in_array($what, self::WHAT) || !in_array($time, self::TIME)) {
            return null;
        }

        // Get the calendar type we are using.
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $now = $calendartype->timestamp_to_date_array(time());

        $paramcategory = false;
        $courses = enrol_get_users_courses($user->id, true, 'id, visible, shortname');
        // Array of courses that we will pass to calendar_get_legacy_events() which
        // is initially set to the list of the user's courses.
        $paramcourses = $courses;
        if ($what == 'all' || $what == 'groups') {
            $groups = [];
            foreach ($courses as $course) {
                $coursegroups = groups_get_all_groups($course->id, $user->id);
                $groups = array_merge($groups, array_keys($coursegroups));
            }
            if (empty($groups)) {
                $groups = false;
            }
        }
        if ($what == 'all') {
            $users = $user->id;
            $courses[SITEID] = new \stdClass();
            $courses[SITEID]->shortname = get_string('siteevents', 'calendar');
            $paramcourses[SITEID] = $courses[SITEID];
            $paramcategory = true;
        } else if ($what == 'groups') {
            $users = false;
            $paramcourses = [];
        } else if ($what == 'user') {
            $users = $user->id;
            $groups = false;
            $paramcourses = [];
        } else if ($what == 'categories') {
            $users = $user->id;
            $groups = false;
            $paramcourses = [];
            $paramcategory = true;
        } else {
            $users = false;
            $groups = false;
        }

        // A course calendar's export is restricted to that course, as core's script restricts it (MDL-74915).
        if (!empty($courseid) && $courseid != SITEID) {
            $selectedcourse = $DB->get_record('course', ['id' => $courseid], 'id, visible, shortname, category');
            $hasaccess = $selectedcourse && can_access_course($selectedcourse, $user);

            // Drop every other course, but keep the site, which provides the site events.
            $paramcourses = array_intersect_key($paramcourses, [SITEID => true]);

            if ($hasaccess && in_array($what, ['all', 'courses'])) {
                $paramcourses[$courseid] = $selectedcourse;
                $courses[$courseid] = $selectedcourse;
            }

            if ($groups !== false) {
                // Keep only the user's groups within the selected course.
                $coursegroupids = $hasaccess ? array_keys(groups_get_all_groups($courseid, $user->id)) : [];
                $groups = array_values(array_intersect((array) $groups, $coursegroupids));
                if (empty($groups)) {
                    $groups = false;
                }
            }

            if ($paramcategory === true) {
                // Limit category events to the course category and its parents, as the calendar does.
                $categorypath = $hasaccess
                    ? $DB->get_field('course_categories', 'path', ['id' => $selectedcourse->category])
                    : false;
                $paramcategory = $categorypath ? array_map('intval', array_filter(explode('/', $categorypath))) : [];
            }
        }

        [$timestart, $timeend] = self::period($time, $calendartype, $now);

        $limitnum = 0;
        $events = calendar_get_legacy_events(
            $timestart,
            $timeend,
            $users,
            $groups,
            array_keys($paramcourses),
            false,
            true,
            $paramcategory,
            $limitnum
        );
        // The theme's one change: the events of a category the range withholds are left out.
        $events = self::admitted($events);

        $ical = new \iCalendar();
        $ical->add_property('method', 'PUBLISH');
        $ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');
        foreach ($events as $event) {
            if (!empty($event->modulename)) {
                $instances = get_fast_modinfo($event->courseid, $userid)->get_instances_of($event->modulename);
                if (empty($instances[$event->instance]->uservisible)) {
                    continue;
                }
            }
            $hostaddress = str_replace('http://', '', $CFG->wwwroot);
            $hostaddress = str_replace('https://', '', $hostaddress);

            $me = new \calendar_event($event); // To use moodle calendar event services.
            $ev = new \iCalendar_event(); // To export in ical format.
            $ev->add_property('uid', $event->id . '@' . $hostaddress);

            // Set iCal event summary from event name.
            $ev->add_property('summary', format_string($event->name, true, ['context' => $me->context]));

            // Format the description text.
            $description = format_text($me->description, $me->format, ['context' => $me->context]);
            // Then convert it to plain text, since it's the only format allowed for the event description property.
            // We use html_to_text in order to convert <br> and <p> tags to new line characters for descriptions in HTML format.
            $description = html_to_text($description, 0);
            $ev->add_property('description', $description);

            $ev->add_property('class', 'PUBLIC'); // PUBLIC / PRIVATE / CONFIDENTIAL.
            $ev->add_property('last-modified', \Bennu::timestamp_to_datetime($event->timemodified));

            if (!empty($event->location)) {
                $ev->add_property('location', $event->location);
            }

            $ev->add_property('dtstamp', \Bennu::timestamp_to_datetime()); // Now.
            if ($event->timeduration > 0) {
                // Dtend is better than duration, because it works in Microsoft Outlook and works better in Korganizer.
                $ev->add_property('dtstart', \Bennu::timestamp_to_datetime($event->timestart)); // When event starts.
                $ev->add_property('dtend', \Bennu::timestamp_to_datetime($event->timestart + $event->timeduration));
            } else if ($event->timeduration == 0) {
                // When no duration is present, the event is instantaneous event, ex - Due date of a module.
                // Moodle doesn't support all day events yet. See MDL-56227.
                $ev->add_property('dtstart', \Bennu::timestamp_to_datetime($event->timestart));
                $ev->add_property('dtend', \Bennu::timestamp_to_datetime($event->timestart));
            } else {
                // This can be used to represent all day events in future.
                throw new \coding_exception("Negative duration is not supported yet.");
            }
            if ($event->courseid != 0) {
                $coursecontext = \core\context\course::instance($event->courseid);
                $ev->add_property(
                    'categories',
                    format_string($courses[$event->courseid]->shortname, true, ['context' => $coursecontext])
                );
            }
            $ical->add_component($ev);
        }

        return $ical->serialize();
    }

    /**
     * The legacy events minus those of a category the theme withholds from the account, or of one that is gone.
     *
     * The predicate is the page's ({@see core_events::shown_categories()}: core's visibility for the current user, then
     * the unlisted rule), asked once for every category the events name. A category no longer in the table names
     * nothing the account may see: its events are left out. It reads $USER, so the session's user must be the feed's.
     *
     * @param \stdClass[] $events Core's legacy events, keyed as calendar_get_legacy_events() keys them.
     * @return \stdClass[] The same events and keys, the withheld categories' left out.
     */
    public static function admitted(array $events): array {
        $ids = [];
        foreach ($events as $event) {
            if (!empty($event->categoryid)) {
                $ids[(int) $event->categoryid] = (int) $event->categoryid;
            }
        }
        if (!$ids) {
            return $events;
        }
        $categories = array_filter(
            \core_course_category::get_many(array_values($ids)),
            static fn($category): bool => $category instanceof \core_course_category
        );
        $listed = $categories ? core_events::shown_categories($categories) : [];
        return array_filter(
            $events,
            static fn(\stdClass $event): bool => empty($event->categoryid) || isset($listed[(int) $event->categoryid])
        );
    }

    /**
     * The period of a preset, from and to: core's switch, verbatim.
     *
     * @param string $time The period, one of {@see TIME}.
     * @param \core_calendar\type_base $calendartype The account's calendar type.
     * @param array $now The current date in that type.
     * @return array [timestart, timeend].
     */
    private static function period(string $time, \core_calendar\type_base $calendartype, array $now): array {
        global $CFG;

        // Store the number of days in the week.
        $numberofdaysinweek = $calendartype->get_num_weekdays();

        switch ($time) {
            case 'weeknow':
                $startweekday = calendar_get_starting_weekday();
                $startmonthday = find_day_in_month(
                    $now['mday'] - ($numberofdaysinweek - 1),
                    $startweekday,
                    $now['mon'],
                    $now['year']
                );
                $startmonth = $now['mon'];
                $startyear = $now['year'];
                if ($startmonthday > calendar_days_in_month($startmonth, $startyear)) {
                    [$startmonth, $startyear] = $calendartype->get_next_month($startyear, $startmonth);
                    $startmonthday = find_day_in_month(1, $startweekday, $startmonth, $startyear);
                }
                $gregoriandate = $calendartype->convert_to_gregorian($startyear, $startmonth, $startmonthday);
                $timestart = self::stamp($gregoriandate);

                $endmonthday = $startmonthday + $numberofdaysinweek;
                $endmonth = $startmonth;
                $endyear = $startyear;
                if ($endmonthday > calendar_days_in_month($endmonth, $endyear)) {
                    [$endmonth, $endyear] = $calendartype->get_next_month($endyear, $endmonth);
                    $endmonthday = find_day_in_month(1, $startweekday, $endmonth, $endyear);
                }
                $gregoriandate = $calendartype->convert_to_gregorian($endyear, $endmonth, $endmonthday);
                $timeend = self::stamp($gregoriandate);
                break;
            case 'weeknext':
                $startweekday = calendar_get_starting_weekday();
                $startmonthday = find_day_in_month($now['mday'] + 1, $startweekday, $now['mon'], $now['year']);
                $startmonth = $now['mon'];
                $startyear = $now['year'];
                if ($startmonthday > calendar_days_in_month($startmonth, $startyear)) {
                    [$startmonth, $startyear] = $calendartype->get_next_month($startyear, $startmonth);
                    $startmonthday = find_day_in_month(1, $startweekday, $startmonth, $startyear);
                }
                $gregoriandate = $calendartype->convert_to_gregorian($startyear, $startmonth, $startmonthday);
                $timestart = self::stamp($gregoriandate);

                $endmonthday = $startmonthday + $numberofdaysinweek;
                $endmonth = $startmonth;
                $endyear = $startyear;
                if ($endmonthday > calendar_days_in_month($endmonth, $endyear)) {
                    [$endmonth, $endyear] = $calendartype->get_next_month($endyear, $endmonth);
                    $endmonthday = find_day_in_month(1, $startweekday, $endmonth, $endyear);
                }
                $gregoriandate = $calendartype->convert_to_gregorian($endyear, $endmonth, $endmonthday);
                $timeend = self::stamp($gregoriandate);
                break;
            case 'monthnow':
                // Convert to gregorian.
                $gregoriandate = $calendartype->convert_to_gregorian($now['year'], $now['mon'], 1);

                $timestart = self::stamp($gregoriandate);
                $timeend = $timestart + (calendar_days_in_month($now['mon'], $now['year']) * DAYSECS);
                break;
            case 'monthnext':
                // Get the next month for this calendar.
                [$nextmonth, $nextyear] = $calendartype->get_next_month($now['year'], $now['mon']);

                // Convert to gregorian.
                $gregoriandate = $calendartype->convert_to_gregorian($nextyear, $nextmonth, 1);

                // Create the timestamps.
                $timestart = self::stamp($gregoriandate);
                $timeend = $timestart + (calendar_days_in_month($nextmonth, $nextyear) * DAYSECS);
                break;
            case 'recentupcoming':
                // Events in the last 5 or next 60 days.
                $timestart = time() - 432000;
                $timeend = time() + 5184000;
                break;
            default:
                // Events based on custom date range: 'custom', the last of TIME, which feed() checked.
                $timestart = time() - $CFG->calendar_exportlookback * DAYSECS;
                $timeend = time() + $CFG->calendar_exportlookahead * DAYSECS;
                break;
        }
        return [$timestart, $timeend];
    }

    /**
     * The timestamp of a Gregorian date array, as core's script makes each end of a period.
     *
     * @param array $gregoriandate A date from convert_to_gregorian(): year, month, day, hour, minute.
     * @return int
     */
    private static function stamp(array $gregoriandate): int {
        return make_timestamp(
            $gregoriandate['year'],
            $gregoriandate['month'],
            $gregoriandate['day'],
            $gregoriandate['hour'],
            $gregoriandate['minute']
        );
    }
}
