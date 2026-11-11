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
 * Theme Dimensions - One request for a course's landing page
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use theme_dimensions\local\blockless;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\related\shortcode;
use theme_dimensions\output\landing_guestentry;
use theme_dimensions\output\landing_hero;
use theme_dimensions\output\landing_page;
use theme_dimensions\output\opengraph;

/**
 * One request for a course's landing page, decided once for both entry points.
 *
 * The page answers from two addresses: the route (route\controller\landing, the
 * canonical while the site's router is configured) and the script (landing.php,
 * the canonical otherwise, a redirect to the route the rest of the time). Both
 * call {@see handle()}, which runs the whole decision in one order and answers
 * either a redirect target or a page ready to render; each entry point only
 * translates that answer into its own way of redirecting and writing. The order
 * is a security property and moves as one block:
 *
 * 1. A visitor - not logged in, or the guest - is refused before the course is
 *    looked up unless the course is public: one refusal, the login page, for a
 *    course that is unlisted, hidden or not there at all, so that an anonymous
 *    client cannot tell which ids exist. wantsurl brings somebody entitled back
 *    here after logging in.
 * 2. The front page: everybody is enrolled there.
 * 3. A logged-in user who must not learn that the course exists gets the ghost
 *    page, decided by the predicate the standard enrolment page is ghosted with,
 *    a missing id included ({@see redirector::is_address_ghosted()}, asked before
 *    the lookup so that the lookup's error page cannot tell it apart), and asked
 *    before the model check on purpose: a course without a model is
 *    forwarded to that page, and the ghost must not depend on the forward.
 *    Visitors are never ghosted; redirector::is_ghosted() says why.
 * 4. A course without a landing page model keeps the standard enrolment page.
 * 5. The guards enrol/index.php applies: a hidden course, a login-as session, a
 *    category the user may not see. The last one is not asked of a visitor: it
 *    ends in a capability check, accesslib denies every capability to user id 0
 *    under forcelogin, and its visibility half is already inside
 *    publicaccess::is_public(), which the visitor passed.
 * 6. The guest entry ({@see guestaccess}), asked for by guest=1 on the address or by the
 *    interstitial's form, where the course offers it: a logged-in session (the guest's included)
 *    goes straight to the course; the form's POST with the session key logs an anonymous visitor
 *    in as the guest and sends them there; an anonymous visitor of the address gets the
 *    interstitial. Where the course does not offer it, the answer is the plain landing page. It
 *    comes after every guard above, so an anonymous visitor reaches it only for a public course.
 *
 * The guards mirror enrol/index.php rather than calling require_login($course),
 * which is exactly the call that sends a non-enrolled user to the standard
 * enrolment page. forcelogin is not an ambient gate: it is a set of explicit
 * reads in core, none of which is made here, and login/index.php is anonymous
 * for the same reason.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /**
     * Constructor: exactly one of the two answers.
     *
     * @param \core\url|null $redirect Where to send the client, when the answer is a redirect.
     * @param landing_page|landing_guestentry|null $page The page to render: the landing page, or the guest entry's interstitial.
     */
    private function __construct(
        /** @var \core\url|null Where to send the client, when the answer is a redirect. */
        public readonly ?\core\url $redirect,
        /** @var landing_page|landing_guestentry|null The page to render: the landing page, or the guest entry's interstitial. */
        public readonly landing_page|landing_guestentry|null $page,
    ) {
    }

    /**
     * Decide a request for a course's landing page.
     *
     * Sets up $PAGE and, for a public course, the head tags; the caller renders
     * the header right after. Throws where the standard enrolment page throws, except for a missing id,
     * where a logged-in user is ghosted.
     *
     * @param int $courseid The course id from the request.
     * @param bool $guest Whether the address asks for the guest entry (guest=1, {@see links::guest_page()}).
     * @return self A redirect target or a page.
     * @throws \moodle_exception For a hidden course, a login-as session, or a category the user may not see.
     */
    public static function handle(int $courseid, bool $guest = false): self {
        global $CFG, $PAGE, $SESSION, $USER;

        // A banner armed for an earlier page of this process is never this page's.
        landing_hero::reset();

        $landingurl = links::page($courseid);

        $visitor = !isloggedin() || isguestuser();
        if ($visitor && !publicaccess::is_public($courseid)) {
            $SESSION->wantsurl = $landingurl->out(false);
            return self::redirect_to(new \core\url(get_login_url()));
        }

        /* A logged-in session is answered the same for a missing id as for a course it must not learn about:
           the lookup below throws for the first and the ghost redirects for the second, which would tell
           which ids exist. Visitors never reach this line and a site without the plugin is not ghosted. */
        if (!$visitor && redirector::is_address_ghosted($courseid)) {
            return self::redirect_to(redirector::ghost_url());
        }

        $course = get_course($courseid);
        $context = \core\context\course::instance($course->id, MUST_EXIST);

        if ($course->id == SITEID) {
            return self::redirect_to(new \core\url('/'));
        }

        if (redirector::is_ghosted((int) $course->id)) {
            return self::redirect_to(redirector::ghost_url());
        }

        /* The course selects the organisation flavour; the context is the category's, as on
           enrol/index.php - the viewer is usually not enrolled. Both are set before any field is
           read: a textarea value goes through format_text(), a text filter reads $PAGE->theme,
           and once the theme is set up the course can no longer change. */
        $PAGE->set_course($course);
        $PAGE->set_context($context->get_parent_context());

        /* The page draws a [relatedcourses] shortcode written in the course's texts in its band's place, after both
           columns; told before the first of those texts is formatted. */
        shortcode::lift((int) $course->id);
        $values = landingfields::values($course->id);
        if (!$values->model) {
            return self::redirect_to(new \core\url('/enrol/index.php', ['id' => $course->id]));
        }

        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context)) {
            throw new \moodle_exception('coursehidden');
        }

        // Do not allow enrols when in a login-as session (same rule as enrol/index.php).
        if (\core\session\manager::is_loggedinas() && $USER->loginascontext->contextlevel == CONTEXT_COURSE) {
            throw new \moodle_exception(
                'loginasnoenrol',
                '',
                $CFG->wwwroot . '/course/view.php?id=' . $USER->loginascontext->instanceid
            );
        }

        if (!$visitor && !\core_course_category::can_view_course_info($course) && !is_enrolled($context, $USER, '', true)) {
            throw new \moodle_exception('coursehidden', '', $CFG->wwwroot . '/');
        }

        /* The guest entry, before the resolver: enrol_page_hook() may redirect or enrol, and none of
           that belongs to a request that is only passing through. The form's field counts only with
           the session's own key, read without required_param() so that a stale or missing key is the
           plain landing page and never an error page; a visitor whose session expired between the two
           pages lands on the landing page, where the guest button starts again. Only a POST counts: a GET
           carrying the field and a valid key (a pasted or prefetched address) falls through to the
           page. */
        $sesskey = optional_param('sesskey', '', PARAM_RAW);
        $enter = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && optional_param(guestaccess::ENTER_PARAM, 0, PARAM_BOOL)
            && $sesskey !== ''
            && confirm_sesskey($sesskey);
        if (($guest || $enter) && guestaccess::available($course)) {
            if (isloggedin()) {
                return self::redirect_to(guestaccess::target((int) $course->id));
            }
            if ($enter && guestaccess::log_in()) {
                return self::redirect_to(guestaccess::target((int) $course->id));
            }
            if ($guest) {
                return self::guest_entry($course, $context, $values, $landingurl);
            }
        }

        // The page type is spelled out because under the router core would derive it from r.php.
        $PAGE->set_url($landingurl);
        $PAGE->set_pagetype('theme-dimensions-landing');
        $PAGE->set_pagelayout('standard');
        $PAGE->set_secondary_navigation(false);
        $PAGE->add_body_class('theme_dimensions-landing');
        $PAGE->set_title(format_string($course->fullname, true, ['context' => $context]));
        $PAGE->set_heading('');

        /* The page is the course's door, read before anybody is inside: no block region (blockless says how) and no
           course index, which core_course_drawer() would otherwise draw for a reader who may enter the course. The
           index has core's own switch, the first thing that function reads. The guest entry's interstitial needs
           neither: the embedded layout draws fake blocks alone and no drawer. */
        blockless::apply($PAGE);
        $PAGE->set_show_course_index(false);

        $ispublic = publicaccess::is_public((int) $course->id);

        $resolution = resolver::resolve($course);

        if ($resolution->state === resolution::STATE_VISITOR) {
            // The login CTA relies on wantsurl to come back here.
            $SESSION->wantsurl = $landingurl->out(false);
        }

        /* Anything a method needs before output starts happens here: enrol_page_hook() may
           redirect or enrol, and both the hooks and our own modal/payment buttons register
           AMD requirements, which is only possible before the header is printed. */
        $fallbackhtml = [];
        foreach ($resolution->methods as $enrolmethod) {
            switch ($enrolmethod->type) {
                case method::TYPE_SELF:
                    if ($enrolmethod->requirespassword) {
                        $PAGE->requires->js_call_amd('enrol_self/enrol_page', 'initEnrol', [$enrolmethod->instance->id]);
                    }
                    break;
                case method::TYPE_APPLY:
                    $PAGE->requires->js_call_amd('enrol_apply/enrol_page', 'init', [$enrolmethod->instance->id]);
                    break;
                case method::TYPE_FEE:
                    $PAGE->requires->js_call_amd('core_payment/gateways_modal', 'init');
                    break;
                case method::TYPE_FALLBACK:
                    $plugin = enrol_get_plugin($enrolmethod->instance->enrol);
                    $widget = $plugin ? $plugin->enrol_page_hook($enrolmethod->instance) : null;
                    if ($widget) {
                        $fallbackhtml[$enrolmethod->instance->id] = $widget;
                    }
                    break;
            }
        }

        /* The banner's format is the site's: full width, it is drawn by the page header (full_header() reads the
           registry while the layout renders, after this method returns), and the body class lets the stylesheet take
           the header out of the content column. */
        $fullwidth = heroformat::is_fullwidth();
        $page = new landing_page($course, $values, $resolution, $fallbackhtml, $ispublic, $fullwidth);
        if ($fullwidth) {
            $PAGE->add_body_class(heroformat::BODY_CLASS);
            landing_hero::arm(new landing_hero($page));
        }

        /* A public course gets its head tags whoever is looking, so that an editor can check them
           from the page source and a scraper - which is always a visitor - finds them. Registered
           last, right before the header: the head hook fires from the error page as well, and a
           throw in the enrol hooks above would otherwise put this course's tags on it. */
        if ($ispublic) {
            opengraph::set(opengraph::for_course($course, $values->publicdescription, $landingurl));
        }

        return self::render($page);
    }

    /**
     * The answer for an anonymous visitor of the guest entry's address: the interstitial.
     *
     * A 200 carrying the landing page's own head tags (og:url and the canonical stay the landing page's
     * address, the robots rule is the head hook's), so a shared link unfurls exactly as the landing page's
     * does, on a layout that prints the standard head and none of the site's navigation. $PAGE's own
     * address is the guest entry's, so a language switch comes back here rather than to the landing page.
     *
     * @param \stdClass $course The course.
     * @param \core\context\course $context The course context.
     * @param \stdClass $values The course's landing page field values.
     * @param \core\url $landingurl The landing page's canonical address.
     * @return self The answer.
     */
    private static function guest_entry(
        \stdClass $course,
        \core\context\course $context,
        \stdClass $values,
        \core\url $landingurl
    ): self {
        global $PAGE;

        $PAGE->set_url(links::guest_page((int) $course->id));
        $PAGE->set_pagetype('theme-dimensions-landing');
        $PAGE->set_pagelayout('embedded');
        $PAGE->add_body_class('theme_dimensions-guestentry');
        $PAGE->set_title(format_string($course->fullname, true, ['context' => $context]));
        $PAGE->set_heading('');
        $PAGE->requires->js_call_amd('theme_dimensions/landingguest', 'init', [landing_guestentry::FORM_ID]);

        $page = new landing_guestentry($course, $landingurl);

        // The landing page's rule, armed last for the landing page's reason: tags for a public course only.
        if (publicaccess::is_public((int) $course->id)) {
            opengraph::set(opengraph::for_course($course, $values->publicdescription, $landingurl));
        }

        return self::render($page);
    }

    /**
     * The answer "send the client there".
     *
     * @param \core\url $url The target.
     * @return self The answer.
     */
    private static function redirect_to(\core\url $url): self {
        return new self($url, null);
    }

    /**
     * The answer "render this".
     *
     * @param landing_page|landing_guestentry $page The page.
     * @return self The answer.
     */
    private static function render(landing_page|landing_guestentry $page): self {
        return new self(null, $page);
    }
}
