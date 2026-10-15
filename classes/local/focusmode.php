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
 * The whole rule lives here and nowhere else: the renderer only asks. Every
 * clause is evaluated in one place, {@see evaluate()}, so the predicate, the
 * developer trace and the "offer the way in" question can never disagree - a
 * second copy of the rule is exactly how a button appears on a page that is
 * already focused.
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

    /** @var string User preference remembering that the reading column was widened. */
    public const PREFERENCE_EXPANDED = 'theme_dimensions_focus_expanded';

    /**
     * @var string Request parameter which turns focus mode off for one page load.
     *
     * The smallest of the four kill switches, and the one an administrator can use to
     * reproduce what a learner reports without changing anything for anybody else.
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
     * @return bool True when the reduced shell replaces Boost Union's.
     */
    public static function applies(\moodle_page $page): bool {
        return self::evaluate($page) === 'applies';
    }

    /**
     * The name of the first clause which refused, or 'applies' when none did.
     *
     * The developer trace, and it is called rather than emitted: nothing renders it into the
     * page. Answering "why is focus mode not on here?" means asking it from a debugging
     * snippet - a CLI one-liner, an Xdebug watch, or a throwaway debugging() call. An HTML
     * comment would put markup on every page and inside the very output the "the bar is
     * absent" assertions are made on.
     *
     * The vocabulary is fixed and pinned by a test, because it is the clause names of the
     * predicate itself and the two must not drift apart: 'master', 'focusoff',
     * 'pagelayout', 'context', 'cm', 'siteid', 'editing', 'app', 'pagetype',
     * 'course', 'applies'.
     *
     * @param \moodle_page $page The page about to be rendered.
     * @return string One name of the vocabulary above.
     */
    public static function why(\moodle_page $page): string {
        return self::evaluate($page);
    }

    /**
     * Whether the navbar should offer the way into focus mode on this page.
     *
     * True on exactly the pages {@see applies()} would accept if the course had
     * switched focus mode on: every clause but the last holds, the course did
     * not switch it on, and no manual session is open for it. So the button is
     * absent while editing, on an excluded page type, in the app, on a course
     * page and on a page which is already focused, with no second copy of the rule.
     *
     * @param \moodle_page $page The page about to be rendered.
     * @return bool True when the entry button belongs on this page.
     */
    public static function offers_entry(\moodle_page $page): bool {
        return self::evaluate($page) === 'course';
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
        /* The layout is the anchor of the whole predicate: require_login() and
           require_course_login() assign 'incourse' if and only if a course module
           reached them, and course/modedit.php forces it back to 'admin' afterwards,
           which is what keeps the activity settings form out of focus mode. */
        if ($page->pagelayout !== 'incourse') {
            return 'pagelayout';
        }
        $context = $page->context;
        if (!$context || (int) $context->contextlevel !== CONTEXT_MODULE) {
            return 'context';
        }
        /* Never empty($page->cm): moodle_page declares __get() and __set() but no
           __isset(), and empty() on an inaccessible property answers from __isset()
           alone - so it is true even when a cm is set, and the whole predicate dies
           quietly with focus mode never applying anywhere. The identity test against
           null is what actually reaches magic_get_cm(), whose backing property is
           null until set_cm() runs (lib/pagelib.php:148,1268). */
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
        if (!self::enabled_for_course($courseid) && !self::in_session($courseid)) {
            return 'course';
        }
        return 'applies';
    }

    /**
     * Whether the site-wide master switch is on.
     *
     * The switch ships on, so a value which was never written - a site whose admin
     * settings for this theme have not been applied yet - must read as enabled.
     * Reading the config with a falsy fallback instead would switch the feature off
     * on a fresh install and leave nothing on screen to say why. Same shape as
     * {@see login::page_enabled()}.
     *
     * @return bool True when focus mode is allowed to apply anywhere on this site.
     */
    private static function master_enabled(): bool {
        $config = get_config(self::COMPONENT, 'focusmode');
        if ($config === false || $config === '') {
            return true;
        }
        return ((int) $config) === login::SELECT_YES;
    }

    /**
     * Whether a course asked for focus mode on its activities.
     *
     * Two queries on the first call of a request and none afterwards: the field
     * controller is resolved once through {@see focusmodefields::get_field()} and
     * the answer is cached per course. The read mirrors core's own
     * data_controller::get_value() - the checkbox type stores in intvalue, and a
     * course with no row follows the field's checkbydefault - so this can never
     * disagree with what the course settings form shows.
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
     * Core's own pattern for returning to an activity's place in its course, taken
     * from mod/label/view.php: the format decides whether that is the course page
     * with a section anchor or a section page. The single activity format is the one
     * exception - its course page redirects straight back into the activity, so
     * closing there has to leave the course altogether: to the learner's own courses,
     * or, for a visitor or the guest account, to the login page. Neither has courses
     * of its own: /my/courses.php lists nothing for the guest account and sends a
     * visitor through the login to a list of courses they do not have, so the login
     * page itself is the honest exit.
     *
     * @param \cm_info $cm The activity the learner is closing.
     * @return \moodle_url The URL to return to.
     */
    public static function close_url(\cm_info $cm): \moodle_url {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $cm->get_course();
        if (course_get_format($course)->get_format() === 'singleactivity') {
            if (!isloggedin() || isguestuser()) {
                return new \moodle_url(get_login_url());
            }
            return new \moodle_url('/my/courses.php');
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
     * Whether this session opened focus mode manually in one course.
     *
     * @param int $courseid The course id.
     * @return bool True when the flag is held for that course.
     */
    public static function in_session(int $courseid): bool {
        global $SESSION;

        $flags = (array) ($SESSION->{self::SESSION_KEY} ?? []);
        return !empty($flags[$courseid]);
    }

    /**
     * Open a manual focus session for one course.
     *
     * Keyed by course id, never a single boolean for the whole session: a learner
     * following a link into another course must find that course exactly as its own
     * switch says.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function enter_session(int $courseid): void {
        global $SESSION;

        $flags = (array) ($SESSION->{self::SESSION_KEY} ?? []);
        $flags[$courseid] = true;
        $SESSION->{self::SESSION_KEY} = $flags;
    }

    /**
     * Close the manual focus session of one course, leaving the others alone.
     *
     * @param int $courseid The course id.
     * @return void
     */
    public static function leave_session(int $courseid): void {
        global $SESSION;

        $flags = (array) ($SESSION->{self::SESSION_KEY} ?? []);
        unset($flags[$courseid]);
        $SESSION->{self::SESSION_KEY} = $flags;
    }

    /**
     * Whether the reading column starts widened for the person looking.
     *
     * Read on the server so the class is right at first paint and there is no flash
     * of the wrong measure. Somebody who has not chosen yet - and everybody whose
     * choice cannot be stored - gets the width the administrator set.
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
     * Guests get focus mode - the manual session lives in the session, which a guest
     * has - but core refuses to write a preference for them, so the AMD module is
     * told to toggle the class for that page view and never issue the request.
     *
     * @return bool True when the preference may be written.
     */
    public static function can_persist_width(): bool {
        return isloggedin() && !isguestuser();
    }

    /**
     * Reduce Boost Union's layout context to the one the focus template consumes.
     *
     * Nothing is switched off server-side and no key is removed: Boost Union's layout
     * file has already built the whole context and this only adds to it and rewrites two
     * fields. The activity <h2> goes because the bar carries the same name as the page's
     * h1, and the activity information box goes because the automatic conditions are
     * re-offered as one line at the foot of the card - the manual completion button and
     * the automatic status badge ride into the bar through the page's header actions,
     * which is why neither activity_header::disable() nor set_hidecompletion(true) may
     * ever be called: both would take those actions away with the box.
     *
     * The second field is rewritten and not blanked, because the box is also the only
     * place the activity description renders ({@see description_only()}).
     *
     * The renderer arrives as an argument rather than being reached for: the bar is a
     * templatable and needs one, while this class still owns no renderer of its own.
     *
     * @param array $context The layout template context Boost Union built.
     * @param \moodle_page $page The page being rendered.
     * @param \core\output\renderer_base $output The renderer rendering it.
     * @return array The same context, reduced for the focus template.
     */
    public static function layout_context(array $context, \moodle_page $page, \core\output\renderer_base $output): array {
        $bar = new \theme_dimensions\output\focusmode_bar($page, $context);
        $context['focusbar'] = $bar->export_for_template($output);

        $headercontent = (array) ($context['headercontent'] ?? []);
        $context['focuscompletion'] = self::completion_line($headercontent);
        if ($headercontent !== []) {
            /* Keep every other key: the partial still emits the #maincontent anchor. The title
               needs no blanking - Boost and Boost Union declare activityheaderconfig notitle
               (theme/boost/config.php:189-191, Boost Union config.php:252), so core exports it
               empty and the partial never draws the h2; the renderer test pins that outcome. */
            $headercontent['completion'] = self::description_only($headercontent, $output);
            $context['headercontent'] = $headercontent;
        }

        $expanded = self::wants_expanded();
        $context['focusmodeexpanded'] = $expanded;

        $page->requires->js_call_amd(
            self::COMPONENT . '/focusmode',
            'init',
            [
                [
                    'expanded' => $expanded,
                    'canpersist' => self::can_persist_width(),
                    'preference' => self::PREFERENCE_EXPANDED,
                ],
            ]
        );

        return $context;
    }

    /**
     * The activity description alone, in place of the whole activity information render.
     *
     * The key this replaces holds core's rendered core_course/activity_info, and that template
     * is the only place the activity description appears: Moodle 4.0 moved the intro out of
     * every module's own page and into the header. Blanking the key would remove content and
     * not chrome - a forum's or a page's intro text would vanish from the reading column, with
     * the page otherwise looking exactly right.
     *
     * The description is already finished HTML, built by format_module_intro() inside
     * activity_header, which is why the template triple-stashes it.
     *
     * @param array $headercontent The exported activity header context.
     * @param \core\output\renderer_base $output The renderer rendering the page.
     * @return string The rendered description, or the empty string when the activity has none.
     */
    private static function description_only(array $headercontent, \core\output\renderer_base $output): string {
        $description = (string) ($headercontent['description'] ?? '');
        if (trim($description) === '') {
            return '';
        }

        return $output->render_from_template(
            self::COMPONENT . '/focusmode_description',
            ['description' => $description]
        );
    }

    /**
     * The completion line's own sub-context, or false when there is nothing to show.
     *
     * Reads what activity_header::export_for_template() already merged into the header
     * content, so no query is added. The three conditions are core's own: a learner who
     * cannot see the activity, an activity with no completion and one whose completion is
     * manual all get nothing - the manual case is the button in the bar, not a condition.
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
