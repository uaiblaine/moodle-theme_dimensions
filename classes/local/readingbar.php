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
 * Theme Dimensions - The navbar that follows the reading
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Where the navbar follows the reading, and what it says while it does: phase 3 of the navbar.
 *
 * The features share one page predicate and live here, so the renderer only asks:
 *
 * - The band as progress (C3, {@see applies()}): on an activity page the 3px signature band fills from the left as
 *   the page is scrolled; on the course page it shows the reader's completion of the course, computed here
 *   ({@see client_config()}). Everywhere else it stays full, as before.
 * - The context bar (C2, {@see context_applies()}): past the page header the navbar's content swaps for a layer at
 *   the same 60px carrying the activity's (or the course's) name, the course and its completion, a read-only
 *   status of the activity and, where the course index exists, a control that opens it with core's own toggler
 *   attributes. On by default ({@see SETTING_CONTEXT}).
 * - The role shortcut (D5, {@see edit_applies()}): a reader core lets edit the page gets core's edit mode switch in
 *   the layer, since the layer covers the navbar's own.
 * - Auto-hide (C1, {@see autohide_applies()}): the navbar slides up on scroll down and returns on the first scroll
 *   up, its 3px band left at the top. Off by default: an administrator picks the page kinds
 *   ({@see SETTING_AUTOHIDE}).
 *
 * The page kinds are two ({@see kind()}): an activity page (the incourse layout with a course module) and the
 * course's home page. None of it reaches the site course, focus mode (whose bar replaces the navbar already), the
 * category showcase or the Moodle app. Nothing here renders: every clause is testable alone, and the scroll itself
 * is amd/src/readingbar.js's.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class readingbar {
    /** @var string Component name, used for every config read and string. */
    public const COMPONENT = 'theme_dimensions';

    /** @var string The context bar's setting, an admin_setting_configcheckbox that ships on. */
    public const SETTING_CONTEXT = 'navbarcontextbar';

    /** @var string The auto-hide setting, an admin_setting_configmulticheckbox of page kinds that ships empty. */
    public const SETTING_AUTOHIDE = 'navbarautohide';

    /** @var string The page kind of an activity's own page. */
    public const KIND_ACTIVITY = 'activity';

    /** @var string The page kind of a course's home page. */
    public const KIND_COURSE = 'course';

    /** @var string The body class that arms the band's track ({@see applies()}). */
    public const BODY_READING = 'dimensions-readingbar';

    /** @var string The body class that arms auto-hide's offsets ({@see autohide_applies()}). */
    public const BODY_AUTOHIDE = 'dimensions-autohide';

    /** @var string The id core's drawers template gives the course index drawer, Boost Union's copy alike. */
    public const COURSEINDEX = 'theme_boost-drawers-courseindex';

    /** @var string The completion status of an activity the reader has completed. */
    public const STATE_COMPLETE = 'complete';

    /** @var string The completion status of an activity the reader has not completed yet. */
    public const STATE_PENDING = 'pending';

    /**
     * The page kinds auto-hide may be switched on for, in the order the setting lists them.
     *
     * @return string[] Kind => its label.
     */
    public static function kinds(): array {
        return [
            self::KIND_ACTIVITY => get_string('navbarautohide_activity', self::COMPONENT),
            self::KIND_COURSE => get_string('navbarautohide_course', self::COMPONENT),
        ];
    }

    /**
     * The kind of reading page being rendered, or null when it is none.
     *
     * An activity page is what focus mode anchors on ({@see focusmode}): the incourse layout, which require_login()
     * assigns if and only if a course module reached it, in a module context with the course module set. The course
     * page is core's course/view.php: the course layout under a course-view-* page type (course/section.php shares the
     * layout under another type, and is left alone). The site course is neither: its activities and its home page
     * belong to the front page.
     *
     * @param \moodle_page $page The page being rendered.
     * @return string|null One of the KIND_ constants.
     */
    public static function kind(\moodle_page $page): ?string {
        $course = $page->course;
        if (!$course || (int) $course->id === (int) SITEID) {
            return null;
        }
        $layout = (string) $page->pagelayout;
        if ($layout === 'incourse') {
            $context = $page->context;
            /* Never empty($page->cm): moodle_page has no __isset(), so empty() answers true even with a cm set
               (see focusmode::evaluate()). */
            if ($context && (int) $context->contextlevel === CONTEXT_MODULE && $page->cm !== null) {
                return self::KIND_ACTIVITY;
            }
            return null;
        }
        if ($layout === 'course' && str_starts_with((string) $page->pagetype, 'course-view-')) {
            return self::KIND_COURSE;
        }

        return null;
    }

    /**
     * Whether the page being rendered is a reading page this phase reaches: the band follows it.
     *
     * The base of the three features. Not in the Moodle app, which draws its own chrome around a plugin's web
     * view, and not in focus mode, whose bar replaces the navbar and prints no navbar_plugin_output().
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function applies(\moodle_page $page): bool {
        if (self::kind($page) === null) {
            return false;
        }
        if (\core_useragent::is_moodle_app()) {
            return false;
        }
        if (focusmode::applies($page)) {
            return false;
        }

        return true;
    }

    /**
     * Whether the context bar is switched on.
     *
     * A default-on checkbox reads only an explicit '0' as off, so a site that never saved the setting (false) has
     * the feature without an upgrade step writing it.
     *
     * @return bool
     */
    public static function context_enabled(): bool {
        return (string) get_config(self::COMPONENT, self::SETTING_CONTEXT) !== '0';
    }

    /**
     * Whether the page being rendered gets the context bar.
     *
     * A visitor whose navbar carries the theme's "Log in" and "Create account" ({@see guestnav::applies()}) does
     * not: the layer covers the whole bar, and those two actions are the reason the bar is there for them. The
     * decision is guestnav's own, so a site that switched those actions off gives its visitors the context bar.
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function context_applies(\moodle_page $page): bool {
        if (!self::context_enabled()) {
            return false;
        }
        if (!self::applies($page)) {
            return false;
        }
        if (guestnav::applies($page)) {
            return false;
        }

        return true;
    }

    /**
     * The page kinds an administrator switched auto-hide on for.
     *
     * The multicheckbox stores the checked keys joined by commas, and '' when none is; a key this theme does not
     * know (a kind removed in a later release) is dropped.
     *
     * @return string[] KIND_ constants.
     */
    public static function autohide_kinds(): array {
        $stored = (string) get_config(self::COMPONENT, self::SETTING_AUTOHIDE);
        if ($stored === '') {
            return [];
        }

        return array_values(array_intersect(explode(',', $stored), array_keys(self::kinds())));
    }

    /**
     * Whether the navbar of the page being rendered hides on scroll down.
     *
     * Never on the Behat site: a navbar moved off the screen takes its controls out of Selenium's reach, and a
     * scenario that scrolled would fail on the next click into the bar. The behaviour is tested through this
     * predicate and the body class instead.
     *
     * @param \moodle_page $page The page being rendered.
     * @param bool|null $behatsite Whether this is the Behat site; null asks the request (a test passes it).
     * @return bool
     */
    public static function autohide_applies(\moodle_page $page, ?bool $behatsite = null): bool {
        if ($behatsite ?? self::behat_site()) {
            return false;
        }
        if (!in_array(self::kind($page), self::autohide_kinds(), true)) {
            return false;
        }

        return self::applies($page);
    }

    /**
     * Whether this request is served to the Behat site.
     *
     * @return bool
     */
    public static function behat_site(): bool {
        return defined('BEHAT_SITE_RUNNING');
    }

    /**
     * The body classes of the page being rendered: the band's track, and auto-hide's offsets.
     *
     * Added by the renderer's body_attributes(), which Boost Union's layout calls with the page's course module
     * already set. scss/readingbar.scss scopes every rule to them.
     *
     * @param \moodle_page $page The page being rendered.
     * @param bool|null $behatsite Whether this is the Behat site; null asks the request.
     * @return string[]
     */
    public static function body_classes(\moodle_page $page, ?bool $behatsite = null): array {
        if (!self::applies($page)) {
            return [];
        }
        $classes = [self::BODY_READING];
        if (self::autohide_applies($page, $behatsite)) {
            $classes[] = self::BODY_AUTOHIDE;
        }

        return $classes;
    }

    /**
     * The arguments of amd/src/readingbar.js's init().
     *
     * No text the page shows: the module reads its layer from the markup. The course page's progress is the
     * reader's completion of the course, floored as the drawer's card floors it, so a course one activity short of
     * the end never reads as complete; null where there is no number, and on an activity page, where the module
     * measures the scroll instead.
     *
     * @param \moodle_page $page The page being rendered.
     * @param bool|null $behatsite Whether this is the Behat site; null asks the request.
     * @return array kind (string), progress (int percent or null), autohide (bool), behat (bool).
     */
    public static function client_config(\moodle_page $page, ?bool $behatsite = null): array {
        $behatsite = $behatsite ?? self::behat_site();
        $kind = self::kind($page);
        $progress = null;
        if ($kind === self::KIND_COURSE) {
            $counts = self::course_completion($page->course);
            if ($counts !== null) {
                $progress = intdiv(100 * $counts['completed'], $counts['total']);
            }
        }

        return [
            'kind' => (string) $kind,
            'progress' => $progress,
            'autohide' => self::autohide_applies($page, $behatsite),
            'behat' => $behatsite,
        ];
    }

    /**
     * The template data of theme_dimensions/navbar_readingbar, the context layer.
     *
     * Everything is in the plain spelling, for the template's double stashes:
     *
     * - The title: the activity's name on an activity page, the course's on the course page.
     * - The sub line: the course's name on an activity page, then "X of Y completed" while the reader is tracked
     *   in the course; on the course page the count alone.
     * - The status of the activity, read-only: on 5.3 core's linear navigation footer owns the manual completion
     *   button, and a status cannot duplicate it. Read from core's own cm_completion_details, never computed here.
     * - The course index control, only where the drawer exists ({@see has_course_index()}).
     * - Whether the reader gets core's edit mode switch in the layer ({@see edit_applies()}); the renderer adds the
     *   switch itself, core's edit_switch() output, as editswitch.
     *
     * @param \moodle_page $page The page being rendered; {@see context_applies()} said yes.
     * @return array kind, isactivity, title, subline, hasindex, drawerid, hasstatus, cmid, state, complete, canedit.
     */
    public static function context(\moodle_page $page): array {
        $kind = self::kind($page);
        $course = $page->course;
        $coursecontext = \core\context\course::instance((int) $course->id);
        $coursename = format_string(
            get_course_display_name_for_list($course),
            true,
            ['context' => $coursecontext, 'escape' => false]
        );

        $parts = [];
        $data = [
            'kind' => (string) $kind,
            'isactivity' => $kind === self::KIND_ACTIVITY,
            'title' => $coursename,
            'subline' => '',
            'hasindex' => self::has_course_index($page),
            'drawerid' => self::COURSEINDEX,
            'hasstatus' => false,
            'cmid' => 0,
            'state' => '',
            'complete' => false,
            'canedit' => self::edit_applies($page),
        ];

        if ($kind === self::KIND_ACTIVITY) {
            $cm = $page->cm;
            $data['title'] = format_string($cm->name, true, ['context' => $cm->context, 'escape' => false]);
            $data['cmid'] = (int) $cm->id;
            $parts[] = $coursename;
            $status = self::activity_status($cm);
            if ($status !== null) {
                $data['hasstatus'] = true;
                $data['state'] = $status;
                $data['complete'] = $status === self::STATE_COMPLETE;
            }
        }

        $counts = self::course_completion($course);
        if ($counts !== null) {
            $parts[] = get_string('drawer_progress', self::COMPONENT, (object) $counts);
        }
        $data['subline'] = implode(' · ', $parts);

        return $data;
    }

    /**
     * Whether the reader gets core's edit mode switch in the context layer (D5).
     *
     * Core's own predicate, the one core_renderer::edit_switch() asks before it draws the switch in the navbar: the
     * layer covers that switch while it is shown, so a reader who may edit the page finds it again there. A student
     * gets nothing new; core's linear footer already carries "Next".
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function edit_applies(\moodle_page $page): bool {
        return $page->user_allowed_editing();
    }

    /**
     * Whether the course index drawer exists on the page being rendered.
     *
     * The clauses of core_course_drawer(), which Boost Union's layout calls to print the drawer: the page did not
     * hide the index, and the course format uses one ({@see \core_courseformat\base::uses_course_index()}). Its
     * site-course and can_access_course() clauses hold already on every page {@see kind()} accepts.
     *
     * @param \moodle_page $page The page being rendered.
     * @return bool
     */
    public static function has_course_index(\moodle_page $page): bool {
        if ($page->get_show_course_index() === false) {
            return false;
        }

        return course_get_format($page->course)->uses_course_index();
    }

    /**
     * The reader's completion status of one activity, or null when there is none to show.
     *
     * Core's own answer: cm_completion_details, as core's activity header asks it. Null for a visitor and the guest
     * account, for an activity without completion and for a person completion does not track.
     *
     * @param \cm_info $cm The activity.
     * @return string|null STATE_COMPLETE or STATE_PENDING.
     */
    public static function activity_status(\cm_info $cm): ?string {
        global $CFG, $USER;
        require_once($CFG->libdir . '/completionlib.php');

        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $details = \core_completion\cm_completion_details::get_instance($cm, (int) $USER->id, false);
        if (!$details->has_completion() || !$details->is_tracked_user()) {
            return null;
        }

        return $details->is_overall_complete() ? self::STATE_COMPLETE : self::STATE_PENDING;
    }

    /**
     * The reader's completed and total activities of a course, or null when there is no number.
     *
     * {@see drawersummary::completion()}, the counts core's progress percentage divides, for a logged-in real
     * account only: core tracks no visitor and no guest account.
     *
     * @param \stdClass $course The course record.
     * @return array|null completed and total.
     */
    public static function course_completion(\stdClass $course): ?array {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return null;
        }

        return drawersummary::completion($course, (int) $USER->id);
    }
}
