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
 * Theme Dimensions - Focus mode decision
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

/**
 * Whether the reading mode replaces Boost Union's chrome on the page being rendered.
 *
 * The whole rule lives here; the renderer only asks. Every clause is evaluated in
 * {@see evaluate()}, so {@see applies()}, {@see why()} and {@see available()} cannot
 * disagree about a page.
 *
 * The page carries both skins whenever {@see available()} holds: Boost Union's
 * layout renders untouched, the bar is added beside it, and the body class that
 * {@see applies()} decides selects which skin the stylesheet shows.
 * The AMD module moves the class in place and records the choice through
 * {@see \theme_dimensions\external\set_focus_mode}.
 *
 * The clauses are ordered cheapest first. Only the last one reads the
 * database, and its answer is cached per request and per course.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class focusmode {
    /** @var string Component name, used for every config read. */
    public const COMPONENT = 'theme_dimensions';

    /**
     * @var string Session key holding the manual focus sessions, as an array keyed by course id.
     *
     * A flag and not a user preference on purpose: entering focus mode from the navbar button
     * stores nothing about the person and ends with the session.
     */
    public const SESSION_KEY = 'theme_dimensions_focus';

    /**
     * @var string Session key holding the courses left in place, as an array keyed by course id.
     *
     * The mirror of {@see SESSION_KEY} for a course that switched focus mode on: leaving it lasts
     * the rest of the session, in that course alone.
     */
    public const SESSION_KEY_OUT = 'theme_dimensions_focusout';

    /** @var string User preference remembering that the reading column was widened. */
    public const PREFERENCE_EXPANDED = 'theme_dimensions_focus_expanded';

    /**
     * @var string Request parameter which turns focus mode off for one page load.
     *
     * Lets an administrator reproduce what a learner reports without changing anything
     * for anybody else.
     */
    public const PARAM_OFF = 'focusoff';

    /** @var int Stored value of the 'contracted' option of the default width setting. */
    public const WIDTH_CONTRACTED = 0;

    /** @var int Stored value of the 'expanded' option of the default width setting. */
    public const WIDTH_EXPANDED = 1;

    /**
     * @var string The page types focus mode never applies to until an administrator says otherwise.
     *
     * An exit control beside a running quiz timer is data loss, so the three quiz pages
     * ship excluded. The value seeds the admin textarea and is the fallback while it is unset.
     */
    public const DEFAULT_EXCLUDED_PAGETYPES = "mod-quiz-attempt\nmod-quiz-summary\nmod-quiz-review";

    /** @var array Request cache of the per course answer, keyed by course id. */
    private static array $coursecache = [];

    /**
     * Whether focus mode applies to the page being rendered.
     *
     * @param \moodle_page $page The page about to be rendered.
     * @return bool True when the page opens in focus mode: the body class goes on.
     */
    public static function applies(\moodle_page $page): bool {
        return self::evaluate($page) === 'applies';
    }

    /**
     * The name of the first clause which refused, or 'applies' when none did.
     *
     * A developer trace that nothing renders into the page: call it from a debugger or a
     * debugging() call to answer "why is focus mode not on here?".
     *
     * The vocabulary is the predicate's clause names, pinned by a test: 'master', 'focusoff',
     * 'pagelayout', 'context', 'cm', 'siteid', 'editing', 'app', 'pagetype',
     * 'course' (the course did not switch focus mode on and the session did not enter it),
     * 'optout' (the course did, and the session left it), 'applies'.
     *
     * @param \moodle_page $page The page about to be rendered.
     * @return string One name of the vocabulary above.
     */
    public static function why(\moodle_page $page): string {
        return self::evaluate($page);
    }

    /**
     * Whether the page can switch into focus mode and out of it in place.
     *
     * True on exactly the pages every clause but the last accepts: the course's own
     * switch and the session's choice are the only answers that remain, and both move
     * without a reload. Such a page carries the bar, the entry button and the module
     * whichever skin it opens in; every other page is Boost Union's alone.
     *
     * @param \moodle_page $page The page about to be rendered.
     * @return bool True when both skins belong on this page.
     */
    public static function available(\moodle_page $page): bool {
        return in_array(self::evaluate($page), ['applies', 'course', 'optout'], true);
    }

    /**
     * Evaluate the clauses in order and name the first one which refused.
     *
     * @param \moodle_page $page The page about to be rendered.
     * @return string The clause name, or 'applies'.
     */
    private static function evaluate(\moodle_page $page): string {
        if (!self::master_enabled()) {
            return 'master';
        }
        if ((bool) optional_param(self::PARAM_OFF, 0, PARAM_BOOL)) {
            return 'focusoff';
        }
        /* require_login() and require_course_login() set the 'incourse' layout when
           given a course module, and course/modedit.php sets 'admin' afterwards, which
           keeps the activity settings form out of focus mode. */
        if ($page->pagelayout !== 'incourse') {
            return 'pagelayout';
        }
        $context = $page->context;
        if (!$context || (int) $context->contextlevel !== CONTEXT_MODULE) {
            return 'context';
        }
        /* Never empty($page->cm): moodle_page has __get() but no __isset(), so empty()
           is true even when a cm is set. The comparison with null goes through
           magic_get_cm(), which answers null until set_cm() runs. */
        if ($page->cm === null) {
            return 'cm';
        }
        $courseid = (int) $page->course->id;
        if ($courseid === (int) SITEID) {
            return 'siteid';
        }
        /* Turning editing on is the exit, and the entrance, for staff: the edit
           switch lives in the navbar focus mode replaces, so a focus mode which
           survived editing would leave a teacher unable to turn it off again. */
        if ($page->user_is_editing()) {
            return 'editing';
        }
        if (\core_useragent::is_moodle_app()) {
            return 'app';
        }
        if (in_array(strtolower((string) $page->pagetype), self::excluded_pagetypes(), true)) {
            return 'pagetype';
        }
        /* The last clause, and the only one the session moves: the course's own switch, then the
           session's choice in this course. Entering wins over a course that did not switch focus
           mode on, leaving over one that did; {@see choose()} keeps the two flags exclusive. */
        $forced = self::enabled_for_course($courseid);
        if (!self::in_session($courseid) && (!$forced || self::opted_out($courseid))) {
            return $forced ? 'optout' : 'course';
        }
        return 'applies';
    }

    /**
     * Whether the site-wide master switch is on.
     *
     * The switch ships off: a value which was never written reads as off, as the
     * settings page shows it, and only an explicit Yes switches it on.
     *
     * While it is off nothing of the feature is loaded: no bar, no entry button, no
     * module, no stylesheet ({@see theme_dimensions_get_main_scss_content()}), no course
     * form field, and the web service refuses.
     *
     * @return bool True when focus mode is allowed to apply anywhere on this site.
     */
    public static function master_enabled(): bool {
        return ((int) get_config(self::COMPONENT, 'focusmode')) === yesno::YES;
    }

    /**
     * Whether a course asked for focus mode on its activities.
     *
     * The field controller is resolved once per request through
     * {@see focusmodefields::get_field()} and the answer is cached per course. The read
     * mirrors {@see \core_customfield\data_controller::get_value()} for the checkbox type
     * (stored in intvalue; a course with no row follows the field's checkbydefault), so it
     * agrees with what the course settings form shows.
     *
     * @param int $courseid The course id.
     * @return bool True when the course switched focus mode on.
     */
    public static function enabled_for_course(int $courseid): bool {
        global $DB;

        if (array_key_exists($courseid, self::$coursecache)) {
            return self::$coursecache[$courseid];
        }

        $enabled = false;
        $field = focusmodefields::get_field();
        if ($field) {
            $stored = $DB->get_field('customfield_data', 'intvalue', [
                'fieldid' => $field->get('id'),
                'instanceid' => $courseid,
            ]);
            if ($stored === false) {
                $enabled = (bool) $field->get_configdata_property('checkbydefault');
            } else {
                $enabled = (bool) $stored;
            }
        }

        self::$coursecache[$courseid] = $enabled;
        return $enabled;
    }

    /**
     * The page types which always open with the ordinary theme.
     *
     * A value which was never written falls back to the shipped list; a value the
     * administrator deliberately emptied does not, because emptying the textarea is
     * how the exclusion list is switched off.
     *
     * @return array The page types, trimmed and lower-cased, without empty lines.
     */
    public static function excluded_pagetypes(): array {
        $raw = get_config(self::COMPONENT, 'focusmodeexcludedpagetypes');
        if ($raw === false) {
            $raw = self::DEFAULT_EXCLUDED_PAGETYPES;
        }

        $pagetypes = [];
        foreach (preg_split('/\R/', (string) $raw) as $line) {
            $line = strtolower(trim($line));
            if ($line !== '') {
                $pagetypes[] = $line;
            }
        }
        return $pagetypes;
    }

    /**
     * Where "Exit focus mode" goes from this activity.
     *
     * Core's pattern for returning to an activity's place in its course, as in
     * mod/label/view.php: the format decides whether that is the course page with a
     * section anchor or a section page. It is the bar's course overline and, where the
     * page cannot leave focus mode in place ({@see leaves_course()}), the exit itself.
     *
     * The single activity format is the exception: its course page redirects straight
     * back into the activity, so the way out is the site home page, with redirect=0 so a
     * reader whose defaulthomepage is the Dashboard stays there. While enablemyhome is
     * off (its default) index.php ignores redirect=0 and sends the reader on to their own
     * home page, or a visitor to the login page.
     *
     * @param \cm_info $cm The activity the learner is closing.
     * @return \moodle_url The URL to return to.
     */
    public static function close_url(\cm_info $cm): \moodle_url {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $cm->get_course();
        if (self::is_single_activity($course)) {
            return new \moodle_url('/', ['redirect' => 0]);
        }

        $url = course_get_url($course, $cm->sectionnum);
        if (!$url) {
            // A format is free to answer null; the course page is always a valid way back.
            $url = new \moodle_url('/course/view.php', ['id' => $course->id]);
        }
        $url->set_anchor('module-' . $cm->id);
        return $url;
    }

    /**
     * Whether the only way out of focus mode on this activity is to leave the course.
     *
     * A single activity course that switched focus mode on: its course page is
     * the activity itself, so there is no ordinary page to switch to and the exit is a link
     * to {@see close_url()}. Every other page leaves in place.
     *
     * @param \cm_info $cm The activity.
     * @return bool True when the exit is a link out of the course.
     */
    public static function leaves_course(\cm_info $cm): bool {
        $course = $cm->get_course();
        return self::is_single_activity($course) && self::enabled_for_course((int) $course->id);
    }

    /**
     * Whether a course uses the single activity format.
     *
     * @param \stdClass $course The course record.
     * @return bool True for the single activity format.
     */
    private static function is_single_activity(\stdClass $course): bool {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        return course_get_format($course)->get_format() === 'singleactivity';
    }

    /**
     * Whether this session entered focus mode in one course.
     *
     * @param int $courseid The course id.
     * @return bool True when the entry flag is held for that course.
     */
    public static function in_session(int $courseid): bool {
        global $SESSION;

        $flags = (array) ($SESSION->{self::SESSION_KEY} ?? []);
        return !empty($flags[$courseid]);
    }

    /**
     * Whether this session left focus mode in one course.
     *
     * @param int $courseid The course id.
     * @return bool True when the exit flag is held for that course.
     */
    public static function opted_out(int $courseid): bool {
        global $SESSION;

        $flags = (array) ($SESSION->{self::SESSION_KEY_OUT} ?? []);
        return !empty($flags[$courseid]);
    }

    /**
     * Record the session's choice in one course: in focus mode, or out of it.
     *
     * Two flags keyed by course id, never one boolean for the whole session: a learner
     * following a link into another course finds that course as its own switch says. The
     * choice sets one flag and clears the other, so the last choice is the one
     * {@see evaluate()} reads whatever the course's switch says; both end with the session.
     * Called by {@see \theme_dimensions\external\set_focus_mode} and focus.php.
     *
     * @param int $courseid The course id.
     * @param bool $active True to be in focus mode, false to be out of it.
     * @return void
     */
    public static function choose(int $courseid, bool $active): void {
        global $SESSION;

        $in = (array) ($SESSION->{self::SESSION_KEY} ?? []);
        $out = (array) ($SESSION->{self::SESSION_KEY_OUT} ?? []);
        unset($in[$courseid], $out[$courseid]);
        if ($active) {
            $in[$courseid] = true;
        } else {
            $out[$courseid] = true;
        }
        $SESSION->{self::SESSION_KEY} = $in;
        $SESSION->{self::SESSION_KEY_OUT} = $out;
    }

    /**
     * Whether the session's choice in one course, as {@see choose()} recorded it, is focus mode.
     *
     * The last clause of {@see evaluate()} alone, for the web service's answer, which has no page.
     *
     * @param int $courseid The course id.
     * @return bool True when the course's activities open in focus mode for this session.
     */
    public static function chosen(int $courseid): bool {
        if (self::in_session($courseid)) {
            return true;
        }
        return self::enabled_for_course($courseid) && !self::opted_out($courseid);
    }

    /**
     * Whether the reading column starts widened for the person looking.
     *
     * Read on the server so the class is right at first paint. Somebody who has not
     * chosen yet, and everybody whose choice cannot be stored, gets the width the
     * administrator set.
     *
     * @return bool True when the expanded class belongs on the body.
     */
    public static function wants_expanded(): bool {
        $default = ((int) get_config(self::COMPONENT, 'focusmodedefaultwidth')) === self::WIDTH_EXPANDED;
        if (!self::can_persist_width()) {
            return $default;
        }
        return (bool) get_user_preferences(self::PREFERENCE_EXPANDED, $default);
    }

    /**
     * Whether the width choice can be stored for the person looking.
     *
     * Guests get focus mode (the manual choice lives in the session, which a guest has),
     * but core refuses to write a preference for them, so the AMD module toggles the class
     * for that page view and sends no request.
     *
     * @return bool True when the preference may be written.
     */
    public static function can_persist_width(): bool {
        return isloggedin() && !isguestuser();
    }

    /**
     * What the page adds to Boost Union's layout so it can switch skins in place.
     *
     * Boost Union's layout context is read, never rewritten, for the three things focus
     * mode adds: the bar (in the navbar's colours, so a flavour recolours it), the
     * completion line at the foot of the content, and the AMD module's arguments. The
     * renderer prints the bar and the line
     * ({@see \theme_dimensions\output\core_renderer::standard_top_of_body_html()},
     * {@see \theme_dimensions\output\core_renderer::activity_navigation()}).
     *
     * Never call activity_header::disable() or set_hidecompletion(true): both take the
     * manual completion button (or, under linear navigation, its status) out of the
     * header actions the bar carries. The actions are printed where the first paint shows
     * them and the module moves the same nodes on every switch, so there is no second copy.
     *
     * @param array $context The layout template context Boost Union built.
     * @param \moodle_page $page The page being rendered, which {@see available()} accepted.
     * @param \core\output\renderer_base $output The renderer rendering it.
     * @return array active (bool), bar (array, the focusmode_bar context), completion (array|false).
     */
    public static function shell(array $context, \moodle_page $page, \core\output\renderer_base $output): array {
        $active = self::applies($page);
        $bar = new \theme_dimensions\output\focusmode_bar($page, $context, $active);
        $headercontent = (array) ($context['headercontent'] ?? []);
        $expanded = self::wants_expanded();
        $cm = $page->cm;

        $page->requires->js_call_amd(
            self::COMPONENT . '/focusmode',
            'init',
            [
                [
                    'active' => $active,
                    'cmid' => (int) $cm->id,
                    'courseid' => (int) $page->course->id,
                    'leavescourse' => self::leaves_course($cm),
                    'expanded' => $expanded,
                    'canpersist' => self::can_persist_width(),
                    'preference' => self::PREFERENCE_EXPANDED,
                ],
            ]
        );

        return [
            'active' => $active,
            'bar' => $bar->export_for_template($output),
            'completion' => self::completion_line($headercontent),
        ];
    }

    /**
     * The completion line's own sub-context, or false when there is nothing to show.
     *
     * Reads what activity_header::export_for_template() already merged into the header
     * content, so no query is added. An activity the learner cannot see, one with no
     * completion and one with manual completion (its button is in the bar) get nothing.
     *
     * @param array $headercontent The exported activity header context.
     * @return array|false The line's context, or false when the line does not render.
     */
    private static function completion_line(array $headercontent) {
        if (
            empty($headercontent['hascompletion'])
            || empty($headercontent['isautomatic'])
            || empty($headercontent['uservisible'])
        ) {
            return false;
        }

        /* The loop lives inside a section carrying istrackeduser, because core's
           completion_automatic partial reads that flag from the enclosing context,
           exactly as course/templates/activity_info.mustache arranges it. */
        return [
            'istrackeduser' => !empty($headercontent['istrackeduser']),
            'completiondetails' => $headercontent['completiondetails'] ?? [],
        ];
    }

    /**
     * Reset the request caches (used by tests and after provisioning).
     *
     * The field controller cache belongs to {@see focusmodefields} and is reset here
     * too, because the per course answer is read through it: a test which provisions
     * the field after a first read would otherwise keep the negative answer.
     *
     * @return void
     */
    public static function reset_caches(): void {
        self::$coursecache = [];
        focusmodefields::reset_field_cache();
    }
}
