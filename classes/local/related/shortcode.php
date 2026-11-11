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
 * Theme Dimensions - The [relatedcourses] shortcode
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use theme_dimensions\output\related_band;

/**
 * The `[relatedcourses]` shortcode of filter_shortcodes, declared in db/shortcodes.php.
 *
 * It draws the landing page's related courses band anywhere filter_shortcodes runs: a page of local_page, a label, a
 * category description. The attributes name identifiers, never ids, which break on a backup restored elsewhere:
 *
 * - `course="shortname"`: the parent course; by default the course the text belongs to, none on the site's pages;
 * - `framework="FW01" competencies="LID01,LID02"`: manual mode over those competencies, read inside that framework
 *   (a competency idnumber is unique only inside its framework); with a parent, only those linked to it (to a trail
 *   or its courses) count, without one any of the framework's do;
 * - `mode="auto|manual"`: automatic, the default, relates by the parent's whole set and needs a parent; manual needs
 *   `competencies`;
 * - `match="any|all"`: manual mode, a course linked to any of the competencies or to all of them;
 * - `limit="4"`: how many courses, 1 to {@see finder::MAX_LIMIT}, the site's band setting by default.
 *
 * Anybody who writes text may use it (a forum post), so it shows only what {@see finder} lets the reader see, and its
 * cost is capped: {@see MAX_PER_REQUEST} instances a request, each at most {@see finder::MAX_LIMIT} cards. A card shows
 * another course's name, which a site filtering every string would filter again, so an instance drawn inside another
 * draws nothing ({@see $depth}). The band's styles are the theme's, so a page another theme draws gets nothing; so does
 * a site with competencies off. A reference this site does not have draws nothing, and a reader who may edit the
 * text's course or category (moodle/course:update in its context) is told what was not found.
 *
 * On a course's landing page the band has its own place, after both columns: {@see lift()} names the course whose page
 * the request draws, and an instance in a text of that course's own context (its summary, its landing page sections)
 * draws nothing where it is written. The first one is kept, and the page draws {@see lifted()}'s answer in its band's
 * place instead of the automatic band. A text of another context (a label, a field's site-wide default) draws in place.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcode {
    /** @var string The shortcode's name, as db/shortcodes.php declares it. */
    public const NAME = 'relatedcourses';

    /** @var int The instances one request draws; the next ones draw nothing. */
    public const MAX_PER_REQUEST = 4;

    /** @var string The band's data-region on a page the shortcode draws it on. */
    public const REGION = 'dimensions-related-block';

    /** @var string The theme whose stylesheet draws the band. */
    private const THEME = 'dimensions';

    /** @var string The component of the strings. */
    private const COMPONENT = 'theme_dimensions';

    /** @var int How many instances are being drawn right now: one inside another draws nothing. */
    private static int $depth = 0;

    /** @var int How many instances this request has drawn. */
    private static int $drawn = 0;

    /** @var int|null The course whose landing page this request draws, {@see lift()}; null on any other page. */
    private static ?int $liftfor = null;

    /** @var array|null The first instance written in that course's texts: ['args' => array, 'context' => context]. */
    private static ?array $lifted = null;

    /**
     * filter_shortcodes' callback.
     *
     * @param string $shortcode The shortcode's name.
     * @param array $args Its attributes: name => value, true for an attribute without one.
     * @param string|null $content The wrapped content; none, the shortcode wraps nothing.
     * @param object $env The filter's environment; its context is the text's.
     * @param \Closure $next The filter's next step, unused: nothing is wrapped.
     * @return string The block's HTML, or '' for nothing.
     */
    public static function handle($shortcode, $args, $content, $env, $next): string {
        if (self::$depth > 0) {
            return '';
        }
        $context = $env->context ?? null;
        if (!$context instanceof \core\context) {
            $context = \core\context\system::instance();
        }
        $args = is_array($args) ? $args : [];
        if (self::lifts($context)) {
            // Read before the theme or the competencies are asked: the page's texts are formatted before its setup ends.
            self::$lifted ??= ['args' => $args, 'context' => $context];
            return '';
        }
        if (self::$drawn >= self::MAX_PER_REQUEST || !self::themed() || !finder::available()) {
            return '';
        }
        self::$drawn++;
        self::$depth++;
        try {
            return self::draw($args, $context);
        } finally {
            self::$depth--;
        }
    }

    /**
     * Forget the instances drawn so far, as a new request starts with none. For tests.
     *
     * @return void
     */
    public static function reset(): void {
        self::$depth = 0;
        self::$drawn = 0;
        self::$liftfor = null;
        self::$lifted = null;
    }

    /**
     * Name the course whose landing page this request draws, before any of its texts is formatted: an instance in a
     * text of that course's own context is kept for the page's band ({@see lifted()}) and draws nothing in place.
     *
     * @param int $courseid The course.
     * @return void
     */
    public static function lift(int $courseid): void {
        self::$liftfor = $courseid;
        self::$lifted = null;
    }

    /**
     * What the first instance written in the landing page course's texts asks for, or null when none was.
     *
     * Resolved here, when the page draws its band, never while the text was formatted. With competencies off it is
     * null too: the page's own band draws nothing then either.
     *
     * @return array|null ['found' => {@see finder::find()}'s answer, 'notice' => the editors' notice context or null].
     */
    public static function lifted(): ?array {
        if (self::$lifted === null || !finder::available()) {
            return null;
        }
        return self::resolve(self::$lifted['args'], self::$lifted['context']);
    }

    /**
     * Whether an instance in this context belongs to the landing page this request draws.
     *
     * @param \core\context $context The text's context.
     * @return bool
     */
    private static function lifts(\core\context $context): bool {
        return self::$liftfor !== null
            && $context->contextlevel === CONTEXT_COURSE
            && (int) $context->instanceid === self::$liftfor;
    }

    /**
     * Whether the page is drawn by this theme, whose stylesheet the band needs, or by a child of it.
     *
     * @return bool
     */
    private static function themed(): bool {
        global $PAGE;
        $theme = $PAGE->theme;
        return $theme->name === self::THEME || in_array(self::THEME, (array) $theme->parents, true);
    }

    /**
     * Read the attributes, find the courses and draw them, with the editors' notice.
     *
     * @param array $args The attributes.
     * @param \core\context $context The text's context.
     * @return string
     */
    private static function draw(array $args, \core\context $context): string {
        global $PAGE;

        ['found' => $found, 'notice' => $notice] = self::resolve($args, $context);
        if (!$found && $notice === null) {
            return '';
        }
        // The page's renderer, not $OUTPUT: a text formatted before the page's header (a test, a script) still meets
        // the bootstrap renderer there, which cannot render.
        $output = $PAGE->get_renderer('core');
        return $output->render_from_template('theme_dimensions/related_block', [
            'notice' => $notice,
            'band' => $found ? (new related_band($found, self::REGION))->export_for_template($output) : null,
        ]);
    }

    /**
     * The courses the attributes find, and the editors' notice of what was not found.
     *
     * @param array $args The attributes.
     * @param \core\context $context The text's context.
     * @return array ['found' => {@see finder::find()}'s answer, 'notice' => core/notification_warning's context or null].
     */
    private static function resolve(array $args, \core\context $context): array {
        $request = self::request($args, $context);
        $found = $request->problems ? [] : finder::find(
            $request->set,
            $request->excluded,
            $request->mode,
            $request->match,
            $request->limit
        );
        $notice = null;
        if ($request->problems && has_capability('moodle/course:update', $context)) {
            $notice = [
                'message' => s(get_string('related_shortcode_problem', self::COMPONENT, implode('; ', $request->problems))),
                'closebutton' => false,
                'announce' => false,
            ];
        }
        return ['found' => $found, 'notice' => $notice];
    }

    /**
     * What the attributes ask for, resolved: the set, the courses never related, the mode, the match and the limit;
     * or the problems that make it ask for nothing.
     *
     * @param array $args The attributes.
     * @param \core\context $context The text's context.
     * @return \stdClass set (int[]), excluded (int[]), mode, match, limit (int), problems (string[], plain text).
     */
    private static function request(array $args, \core\context $context): \stdClass {
        $request = (object) [
            'set' => [],
            'excluded' => [],
            'mode' => finder::MODE_AUTO,
            'match' => self::text($args, 'match') === finder::MATCH_ALL ? finder::MATCH_ALL : finder::MATCH_ANY,
            'limit' => self::limit($args),
            'problems' => [],
        ];

        $parent = null;
        $shortname = self::text($args, 'course');
        if ($shortname !== '') {
            $parent = self::course_named($shortname);
            if ($parent === null) {
                $request->problems[] = get_string('related_shortcode_notfound', self::COMPONENT, $shortname);
            }
        } else {
            $parent = self::course_of($context);
        }
        if ($parent !== null) {
            $request->excluded = band::courseids($parent, band::trail_of($parent));
        }
        $parentset = $parent === null ? [] : finder::competencies_of($request->excluded);

        $framework = self::text($args, 'framework');
        $idnumbers = array_values(array_filter(
            array_map('trim', explode(',', self::text($args, 'competencies'))),
            static fn(string $idnumber): bool => $idnumber !== ''
        ));
        if ($idnumbers || self::text($args, 'mode') === finder::MODE_MANUAL) {
            $request->mode = finder::MODE_MANUAL;
            if (!$idnumbers) {
                $request->problems[] = get_string('related_shortcode_nocompetencies', self::COMPONENT);
            } else if ($framework === '') {
                $request->problems[] = get_string('related_shortcode_noframework', self::COMPONENT);
            } else {
                $resolved = finder::resolve($framework, $idnumbers);
                foreach ($resolved['missing'] as $idnumber) {
                    $request->problems[] = get_string('related_shortcode_notfound', self::COMPONENT, "{$framework}/{$idnumber}");
                }
                // With a parent, a manual choice picks among its set; without one, any of the framework's.
                $request->set = $parent === null ? $resolved['ids'] : array_values(array_intersect($resolved['ids'], $parentset));
            }
        } else if ($parent === null) {
            if ($shortname === '') {
                $request->problems[] = get_string('related_shortcode_nocourse', self::COMPONENT);
            }
        } else {
            $request->set = $parentset;
        }
        return $request;
    }

    /**
     * The course a shortname names, never the site. SITEID is $SITE->id, which a database answers as a string.
     *
     * @param string $shortname The shortname.
     * @return \stdClass|null The course record.
     */
    private static function course_named(string $shortname): ?\stdClass {
        global $DB;
        $course = $DB->get_record('course', ['shortname' => $shortname]);
        return $course && (int) $course->id !== (int) SITEID ? $course : null;
    }

    /**
     * The course a text belongs to: its context's course, never the site; none on a category's or the system's.
     *
     * @param \core\context $context The text's context.
     * @return \stdClass|null The course record.
     */
    private static function course_of(\core\context $context): ?\stdClass {
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext || (int) $coursecontext->instanceid === (int) SITEID) {
            return null;
        }
        return get_course((int) $coursecontext->instanceid);
    }

    /**
     * An attribute as trimmed text; '' when absent or written without a value.
     *
     * @param array $args The attributes.
     * @param string $name The attribute's name.
     * @return string
     */
    private static function text(array $args, string $name): string {
        $value = $args[$name] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    /**
     * The `limit` attribute; the site's band setting when absent or not a number. {@see finder::find()} clamps it.
     *
     * @param array $args The attributes.
     * @return int
     */
    private static function limit(array $args): int {
        $limit = self::text($args, 'limit');
        return ctype_digit($limit) ? (int) $limit : band::count();
    }
}
