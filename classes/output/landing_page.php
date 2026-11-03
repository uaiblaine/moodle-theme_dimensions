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
 * Theme Dimensions - Enrolment landing page renderable
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use local_unlistedcourses\access;
use local_unlistedcourses\category_access;
use theme_dimensions\local\landing\guestaccess;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\method;
use theme_dimensions\local\landing\offer;
use theme_dimensions\local\landing\publicfiles;
use theme_dimensions\local\landing\redirector;
use theme_dimensions\local\hook\course\after_form_validation;
use theme_dimensions\local\landing\resolution;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\styleurl;

/**
 * Builds the template context of the enrolment landing page.
 *
 * All escaping decisions live here: names and plain-text values arrive in the
 * plain spelling (the template's double stashes escape for themselves), while
 * the summary, the textarea sections and the fallback widgets are final HTML
 * for triple stashes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class landing_page implements \core\output\named_templatable, \core\output\renderable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * Constructor.
     *
     * @param \stdClass $course The course record.
     * @param \stdClass $values The course's landing page field values ({@see landingfields::values()}).
     * @param resolution $resolution The resolved enrolment state.
     * @param array $fallbackhtml Map of instance id => HTML for TYPE_FALLBACK methods whose
     *   enrol_page_hook() returned a widget (collected by the page before output started).
     * @param bool $ispublic Whether the course is public - served to visitors who are not logged in -
     *   in which case the summary is cleaned for everybody.
     */
    public function __construct(
        /** @var \stdClass The course record. */
        private readonly \stdClass $course,
        /** @var \stdClass The course's landing page field values. */
        private readonly \stdClass $values,
        /** @var resolution The resolved enrolment state. */
        private readonly resolution $resolution,
        /** @var array Map of instance id => fallback widget HTML. */
        private readonly array $fallbackhtml = [],
        /** @var bool Whether the course is public: served to visitors who are not logged in. */
        private readonly bool $ispublic = false,
    ) {
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'theme_dimensions/landing_page';
    }

    /**
     * Export the page context.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        $context = \core\context\course::instance($this->course->id);

        $chips = $this->chips();
        $facts = $this->facts();
        $sections = $this->sections();
        $methods = $this->method_contexts();

        $state = $this->resolution->state;
        if ($state === resolution::STATE_OPEN && !$methods) {
            // Every method was a fallback whose hook rendered nothing: closed after all.
            $state = resolution::STATE_CLOSED;
        }

        /* A held event enrols nobody, whatever an instance's window says: the seal
           replaces the whole enrolment area. Enrolled participants keep their way
           into the course (that is where the materials live), with the seal beside it. */
        $isheld = $this->event_held();
        if ($isheld && $state !== resolution::STATE_ENROLLED) {
            $state = null;
            $methods = [];
        }
        /* The note that goes with the viewer's own row, above the methods: a waiting list under the pending
           state, a suspension or an end under whatever is open or closed, and the prerequisite of a conditional
           enrolment where nothing is open. A held event drops them with everything else. */
        $relationship = $state === null ? access::RELATIONSHIP_NONE : $this->resolution->relationship;
        $iswaitlisted = $state === resolution::STATE_PENDING && $relationship === access::RELATIONSHIP_WAITLISTED;
        $prerequisite = $state === resolution::STATE_CLOSED ? $this->prerequisite() : null;

        /* The tracks of a hybrid event (D3): two or more ways to enrol in one event are its two ways to take
           part, each headed by its instance's own name. Read off the methods as they will be drawn, after a
           held event or an empty fallback has emptied them. */
        $istracks = $state === resolution::STATE_OPEN && $this->is_event() && $this->is_hybrid() && count($methods) >= 2;
        if ($istracks) {
            $methods = $this->name_tracks($methods);
        }

        $badges = $this->badges();
        $haslivechip = $this->values->liveclasses;
        $exported = (object) [
            'courseid' => $this->course->id,
            'fullname' => $this->plain($this->course->fullname, $context),
            'categoryname' => $this->category_name(),
            'heroimageurl' => $this->hero_image_url(),
            'badges' => $badges,
            'hasbadges' => !empty($badges),
            'video' => $this->video($context, $isheld),
            'summaryhtml' => $this->summary_html($context),
            'chips' => $chips,
            // The list is drawn for either half: the text chips, or the live-classes chip after them.
            'haschips' => !empty($chips) || $haslivechip,
            'haslivechip' => $haslivechip,
            'herodate' => $this->hero_date(),
            'heroticket' => $this->hero_ticket(),
            'herocomp' => $this->hero_comp(),
            'heroplace' => $this->hero_place(),
            'facts' => $facts,
            'hasfacts' => !empty($facts),
            'sections' => $sections,
            'hassections' => !empty($sections),
            'isheld' => $isheld,
            'isheldonly' => $isheld && $state !== resolution::STATE_ENROLLED,
            'heldon' => $isheld ? userdate((int) $this->course->enddate, $this->format_date()) : null,
            'isvisitor' => $state === resolution::STATE_VISITOR,
            /* The login leads a visitor's card only where a login could take them somewhere;
               otherwise guest access is the way in, or nothing is. */
            'showlogin' => $state === resolution::STATE_VISITOR && $this->resolution->loginwayin,
            'isguestaccess' => $state === resolution::STATE_VISITOR && !$this->resolution->loginwayin && !empty($methods),
            'isenrolled' => $state === resolution::STATE_ENROLLED,
            'ispending' => $state === resolution::STATE_PENDING && !$iswaitlisted,
            'iswaitlisted' => $iswaitlisted,
            'issuspended' => $relationship === access::RELATIONSHIP_SUSPENDED,
            'isexpired' => $relationship === access::RELATIONSHIP_EXPIRED,
            'expiredon' => $relationship === access::RELATIONSHIP_EXPIRED ? $this->scheduled_date($this->resolution->endsat) : null,
            'isconditional' => $prerequisite !== null,
            'prerequisite' => $prerequisite,
            'isscheduled' => $state === resolution::STATE_SCHEDULED,
            'scheduledfrom' => $this->scheduled_date($this->resolution->startsat),
            'scheduleduntil' => $this->scheduled_date($this->resolution->endsat),
            'isopen' => $state === resolution::STATE_OPEN,
            'tracksheading' => $istracks ? get_string('landing_tracksheading', self::COMPONENT) : null,
            'isclosed' => $state === resolution::STATE_CLOSED,
            // The closed sentence sends the reader to a focal point; a conditional enrolment says what to do instead.
            'showclosed' => $state === resolution::STATE_CLOSED && $prerequisite === null,
            'loginurl' => get_login_url(),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false),
            'appliedon' => $this->applied_on(),
            'methods' => $methods,
            'hasmethods' => !empty($methods),
            'offers' => $state === resolution::STATE_VISITOR ? $this->visitor_offers() : [],
            'manage' => $this->manage($state),
        ];
        $exported->hasoffers = !empty($exported->offers);
        /* A visitor no login would take anywhere, on a course without guest access, has nothing to press:
           the closed sentence, as a closed course says it, and no bar. */
        if ($exported->isvisitor && !$exported->showlogin && !$exported->hasmethods) {
            $exported->showclosed = true;
        }

        $this->add_bar($exported);
        return $exported;
    }

    /**
     * The way into the course for somebody who manages it without an enrolment, or null.
     *
     * An administrator, a manager or an editor who holds no enrolment reads the card as anybody does and had no way
     * in from it. Offered to a reader who views the course without an enrolment (is_viewing(), core's
     * moodle/course:view) or may edit it (moodle/course:update), and only where core lets them in
     * (can_access_course()), so an editor locked out of the course gets no button that lands on the enrolment page.
     * An enrolled reader already has the card's own way in. A visitor and the guest account hold neither capability.
     *
     * @param string|null $state The state the card is drawn in (null for a held event).
     * @return array|null The address, the label and the note under it, or null.
     */
    private function manage(?string $state): ?array {
        if ($state === resolution::STATE_ENROLLED) {
            return null;
        }
        $context = \core\context\course::instance($this->course->id);
        if (!is_viewing($context) && !has_capability('moodle/course:update', $context)) {
            return null;
        }
        if (!can_access_course($this->course)) {
            return null;
        }
        $event = $this->is_event();
        return [
            'url' => (new \moodle_url('/course/view.php', ['id' => $this->course->id]))->out(false),
            'label' => $event
                ? get_string('landing_manage_event', self::COMPONENT)
                : get_string('landing_manage_course', self::COMPONENT),
            'note' => $event
                ? get_string('landing_managenote_event', self::COMPONENT)
                : get_string('landing_managenote_course', self::COMPONENT),
        ];
    }

    /**
     * What a visitor is told of the methods open to anybody, above the login button (D7).
     *
     * Each entry is {@see offer::export()}'s lines, named after its instance when there are
     * several, so two deadlines on one card are never read as one.
     *
     * @return array The offer contexts.
     */
    private function visitor_offers(): array {
        $offers = [];
        $several = count($this->resolution->offers) > 1;
        foreach ($this->resolution->offers as $instance) {
            $lines = offer::export($instance, time());
            if ($lines === null) {
                continue;
            }
            $component = 'enrol_' . $instance->enrol;
            $offers[] = ['name' => $several ? $this->instance_title($instance, $component) : null] + $lines;
        }
        return $offers;
    }

    /**
     * Name each method of a hybrid event as one of its tracks (D3).
     *
     * The track's label is the instance's custom name, or the plugin's own name when it has none: no
     * naming convention is read, so nothing here guesses which track is the on-site one, and no icon is
     * drawn beside the name.
     *
     * @param array $methods The method contexts, each carrying its instance id.
     * @return array The same contexts, each with its trackname.
     */
    private function name_tracks(array $methods): array {
        $instances = [];
        foreach ($this->resolution->methods as $enrolmethod) {
            $instances[(int) $enrolmethod->instance->id] = $enrolmethod->instance;
        }
        foreach ($methods as $i => $entry) {
            $instance = $instances[$entry['instanceid']];
            $methods[$i]['trackname'] = $this->instance_title($instance, 'enrol_' . $instance->enrol);
        }
        return $methods;
    }

    /**
     * The mobile enrolment bar: one action, mirroring the card.
     *
     * Direct links where the action is a plain navigation (enrolled, the
     * visitor's login, guest access as the primary action); an anchor down to
     * the card where the card's own controls carry modal or
     * payment wiring that must not be duplicated (the plugins' AMD modules
     * bind their buttons by data attributes, and a second button with the
     * same attributes would confuse them).
     *
     * @param \stdClass $exported The context being built.
     * @return void
     */
    private function add_bar(\stdClass $exported): void {
        $exported->bar = null;
        /* The bar keeps the deadline of the action it stands for in sight (D6): the primary method's
           on an open card, and on a visitor's the first listed offer's. */
        $note = null;
        $primary = null;
        foreach ($exported->methods as $entry) {
            if (!empty($entry['primary'])) {
                $primary = $entry;
                $note = $entry['offer']['untilshort'] ?? null;
                break;
            }
        }
        if ($exported->isvisitor) {
            $note = $exported->offers[0]['untilshort'] ?? null;
        }
        /* Guest access standing as the card's primary action (a visitor no login would take anywhere, or an
           open card with nothing else to press) is a plain link as well, and declares no deadline. */
        $guest = $primary !== null && $primary['isguest'] ? $primary : null;
        if ($exported->isenrolled) {
            $exported->bar = [
                'url' => $exported->courseurl,
                'label' => get_string('landing_accesscourse', self::COMPONENT),
            ];
        } else if ($exported->showlogin) {
            $exported->bar = [
                'url' => $exported->loginurl,
                'label' => get_string('landing_loginbutton', self::COMPONENT),
            ];
        } else if ($guest !== null && ($exported->isvisitor || $exported->isopen)) {
            $exported->bar = [
                'url' => $guest['guesturl'],
                'label' => get_string('landing_guestbutton', self::COMPONENT),
            ];
        } else if ($exported->isopen) {
            $exported->bar = [
                'url' => '#dimensions-landing-card',
                'label' => get_string('landing_enrolbutton', self::COMPONENT),
            ];
        }
        if ($exported->bar !== null) {
            $exported->bar['note'] = $exported->isenrolled ? null : $note;
        }
    }

    /**
     * Whether this is an event whose end has passed.
     *
     * Only an explicit course end date can hold an event: with no end date
     * there is no moment after which "held" is a fact rather than a guess.
     *
     * @return bool Whether the event was held.
     */
    private function event_held(): bool {
        return $this->is_event()
            && (int) $this->course->enddate > 0
            && (int) $this->course->enddate < time();
    }

    /**
     * The hero's big date block (online events only): time is the place.
     *
     * A hybrid event carries its date in the double ticket instead (hero_ticket(), just below), where the
     * broadcast is one of two ways to take part rather than the whole of it.
     *
     * @return array|null Block of datebig/sub/countdown, or null off the online-event scenario.
     */
    private function hero_date(): ?array {
        if (!$this->is_event() || $this->is_inperson() || $this->is_hybrid()) {
            return null;
        }
        $start = (int) $this->course->startdate;
        if (!$start) {
            return null;
        }
        $sub = $this->event_schedule_line(false);
        $live = get_string('landing_live', self::COMPONENT);
        return [
            'datebig' => userdate($start, get_string('landing_fmtdatebig', self::COMPONENT)),
            'sub' => $sub !== null ? ($sub . ' · ' . $live) : $live,
            'countdown' => $this->countdown(),
        ];
    }

    /**
     * The hybrid event's double ticket (D1): the date, which holds for every participant, then the two
     * ways to take part - on site, in the city, or live from anywhere.
     *
     * The city is one of the two ways and never the headline: a big city tells the remote audience the
     * event is not for them, which is the variant the owner rejected.
     *
     * @return array|null Keys datebig, sub, countdown, site, venue, live and livesub (plain spelling), or
     *   null off the hybrid event or without a start date.
     */
    private function hero_ticket(): ?array {
        if (!$this->is_event() || !$this->is_hybrid()) {
            return null;
        }
        $start = (int) $this->course->startdate;
        if (!$start) {
            return null;
        }
        $city = $this->values->city;
        $weekday = userdate($start, get_string('landing_fmtweekday', self::COMPONENT));
        return [
            'datebig' => userdate($start, get_string('landing_fmtdatebig', self::COMPONENT)),
            'sub' => $weekday . ' · ' . $this->event_schedule_line(false),
            'countdown' => $this->countdown(),
            'site' => $city !== null
                ? get_string('landing_ticketsitein', self::COMPONENT, $city)
                : get_string('landing_ticketsite', self::COMPONENT),
            'venue' => $this->values->venue,
            'live' => get_string('landing_ticketlive', self::COMPONENT),
            'livesub' => get_string('landing_ticketlivesub', self::COMPONENT),
        ];
    }

    /**
     * The workload of a hybrid course split into its online and in-person hours, or null.
     *
     * The one reading of the split, shared by the hero's composition bar and the workload row of the
     * card, so the two cannot disagree. Both hours must be known; an in-person figure above the total
     * (which the course form refuses, and a restore or a web service can still write) is no split at
     * all rather than a negative online share.
     *
     * @return array|null Keys online, inperson and total (hours, float), or null.
     */
    private function hours_split(): ?array {
        $total = $this->values->workload;
        $inperson = $this->values->inpersonworkload;
        if ($this->is_event() || !$this->is_hybrid() || $total === null || $inperson === null) {
            return null;
        }
        if ($inperson > $total) {
            return null;
        }
        return ['online' => $total - $inperson, 'inperson' => $inperson, 'total' => $total];
    }

    /**
     * The hybrid course's composition (D2): the total workload as one bar split into online and in-person
     * hours, with the in-person meetings and their city.
     *
     * The bar's two widths are printed into a style attribute, which is a CSS-context sink (the theme
     * CLAUDE.md, "Four CSS-context sinks"): Mustache's double stash escapes for HTML, and a browser hands
     * the CSS parser the decoded attribute. So onlinepct is an INTEGER made here, by round() and an
     * (int) cast, clamped to 0..100, and inpersonpct is 100 minus it; the attribute can only ever read
     * `width: N%`. hours_split() already keeps the share inside that range, and the clamp stays as the
     * sink's own guard, independent of what the split decides.
     *
     * @return array|null Keys total, onlinelabel, inpersonlabel, meetings and arialabel (plain spelling),
     *   onlinepct and inpersonpct (int 0..100), or null when the hours are not both known.
     */
    private function hero_comp(): ?array {
        $split = $this->hours_split();
        if ($split === null) {
            return null;
        }
        $onlinepct = max(0, min(100, (int) round($split['online'] / $split['total'] * 100)));
        $hours = [
            'online' => $this->hours_number($split['online']),
            'inperson' => $this->hours_number($split['inperson']),
        ];
        return [
            'total' => $this->hours_label(),
            'onlinelabel' => get_string('landing_componline', self::COMPONENT, $hours['online']),
            'inpersonlabel' => get_string('landing_compinperson', self::COMPONENT, $hours['inperson']),
            'onlinepct' => $onlinepct,
            'inpersonpct' => 100 - $onlinepct,
            'meetings' => $this->meetings_line(),
            'arialabel' => get_string('landing_comparia', self::COMPONENT, (object) $hours),
        ];
    }

    /**
     * The in-person meetings with their city, for the composition's head.
     *
     * @return string|null The meetings "in" the city, either half alone, or null when neither is known.
     */
    private function meetings_line(): ?string {
        $meetings = $this->values->meetings;
        $city = $this->values->city;
        if ($meetings !== null && $city !== null) {
            return get_string('landing_compmeetingsin', self::COMPONENT, (object) ['meetings' => $meetings, 'city' => $city]);
        }
        if ($city !== null) {
            return get_string('landing_compcity', self::COMPONENT, $city);
        }
        return $meetings;
    }

    /**
     * The hero's place block (in-person offerings): the place decides.
     *
     * Big for events - the city is the first filter between editions in
     * different cities - and medium for courses, which are chosen by content
     * and confirmed by place. A hybrid course whose two workloads are not both
     * known has no composition to draw ({@see self::hero_comp()}), and falls back
     * to the medium block with its meetings line.
     *
     * @return array|null Block of city/venue/dateline/meetings/big, or null off the in-person scenarios.
     */
    private function hero_place(): ?array {
        $hybridcourse = !$this->is_event() && $this->is_hybrid();
        if ((!$this->is_inperson() && !$hybridcourse) || $this->values->city === null) {
            return null;
        }
        if ($hybridcourse && $this->hero_comp() !== null) {
            return null;
        }
        $isevent = $this->is_event();
        return [
            'city' => $this->values->city,
            'venue' => $this->values->venue,
            'dateline' => $isevent ? $this->event_dateline() : null,
            'meetings' => $hybridcourse ? $this->values->meetings : null,
            'big' => $isevent,
        ];
    }

    /**
     * The event's full date line: the whole range on a multi-day event, the
     * date plus its times on a one-day one. For the hero and the card.
     *
     * @return string|null The line, or null when there is no start date.
     */
    private function event_dateline(): ?string {
        $start = (int) $this->course->startdate;
        if (!$start) {
            return null;
        }
        $end = (int) $this->course->enddate;
        if ($end && usergetmidnight($start) !== usergetmidnight($end)) {
            return userdate($start, $this->format_date()) . ' – ' . userdate($end, $this->format_date());
        }
        return userdate($start, $this->format_date()) . ' · ' . $this->event_schedule_line(true);
    }

    /**
     * The event's schedule: a time range on a one-day event, a date range across days.
     *
     * @param bool $timeonly With true, a multi-day event returns null (the caller prints its date range).
     * @return string|null The schedule text (the start time alone when there is no end date), or null.
     */
    private function event_schedule_line(bool $timeonly): ?string {
        $start = (int) $this->course->startdate;
        $end = (int) $this->course->enddate;
        $fmttime = get_string('landing_fmttime', self::COMPONENT);
        if (!$end) {
            return userdate($start, $fmttime);
        }
        if (usergetmidnight($start) === usergetmidnight($end)) {
            return userdate($start, $fmttime) . '–' . userdate($end, $fmttime);
        }
        // A multi-day event: the range is dates, not times.
        $range = userdate($start, $this->format_date()) . ' – ' . userdate($end, $this->format_date());
        return $timeonly ? null : $range;
    }

    /**
     * The discreet countdown chip: whole days to the event, in the user's timezone.
     *
     * @return string|null The chip text ("today" for a later hour of today), or null once the event has started.
     */
    private function countdown(): ?string {
        $start = (int) $this->course->startdate;
        $now = time();
        if (!$start || $start <= $now) {
            return null;
        }
        $days = (int) round((usergetmidnight($start) - usergetmidnight($now)) / DAYSECS);
        if ($days <= 0) {
            return get_string('landing_today', self::COMPONENT);
        }
        if ($days === 1) {
            return get_string('landing_oneday', self::COMPONENT);
        }
        return get_string('landing_daysleft', self::COMPONENT, $days);
    }

    /**
     * Whether the course picked the event page model.
     *
     * @return bool Whether the model field holds the event option.
     */
    private function is_event(): bool {
        return $this->values->model === landingfields::MODEL_EVENT;
    }

    /**
     * The short date format shared by the seal, the date line and the card row.
     *
     * @return string The strftime format.
     */
    private function format_date(): string {
        return get_string('strftimedatefullshort', 'langconfig');
    }

    /**
     * The hero chips: modality, workload and the study format.
     *
     * The city is deliberately absent: on in-person offerings it lives in the
     * hero's place block, with the emphasis the scenario gives it. The live
     * classes are a chip of their own, with a dot, drawn by the template after
     * these from haslivechip. The format arrives already dropped where it would
     * contradict the model or the modality (decision D10, landingfields::coherent()).
     *
     * @return string[] The chip texts, plain spelling.
     */
    private function chips(): array {
        $chips = [];
        $modality = landingfields::option_label(landingfields::SHORTNAME_MODALITY, $this->values->modality);
        if ($modality !== null) {
            $chips[] = $modality;
        }
        if ($this->values->workload !== null) {
            $chips[] = $this->hours_label();
        }
        $format = landingfields::option_label(landingfields::SHORTNAME_FORMAT, $this->values->format);
        if ($format !== null) {
            $chips[] = $format;
        }
        return $chips;
    }

    /**
     * The enrolment card's fact rows, labelled with the stored field names.
     *
     * The round-6 rows (D8) read values landingfields::coherent() has already
     * cleared of contradictions (D10): the format only on a course, the live
     * classes only on a tutored one, the meetings only on a hybrid offering.
     *
     * @return array Rows of ['label' => string, 'value' => string, 'note' => string|null], plain spelling.
     */
    private function facts(): array {
        $names = $this->values->names;
        $facts = [];
        if ($this->is_event()) {
            $dateline = $this->event_dateline();
            if ($dateline !== null) {
                $facts[] = $this->fact(get_string('date'), $dateline);
            }
        }
        $modality = landingfields::option_label(landingfields::SHORTNAME_MODALITY, $this->values->modality);
        if ($modality !== null && isset($names[landingfields::SHORTNAME_MODALITY])) {
            $facts[] = $this->fact($names[landingfields::SHORTNAME_MODALITY], $modality);
        }
        $format = landingfields::option_label(landingfields::SHORTNAME_FORMAT, $this->values->format);
        if ($format !== null && isset($names[landingfields::SHORTNAME_FORMAT])) {
            // A self-paced course gives immediate access and a pace of the learner's own (D8).
            $selfpaced = $this->values->format === landingfields::FORMAT_SELFPACED;
            $note = $selfpaced ? get_string('landing_selfpacednote', self::COMPONENT) : null;
            $facts[] = $this->fact($names[landingfields::SHORTNAME_FORMAT], $format, $note);
        }
        // A tutored course starts with its class, on the course's own start date (D8).
        if ($this->values->format === landingfields::FORMAT_TUTORED && (int) $this->course->startdate > 0) {
            $facts[] = $this->fact(
                get_string('landing_factstart', self::COMPONENT),
                userdate((int) $this->course->startdate, $this->format_date())
            );
        }
        if ($this->values->liveclasses && isset($names[landingfields::SHORTNAME_LIVECLASSES])) {
            $facts[] = $this->fact(
                $names[landingfields::SHORTNAME_LIVECLASSES],
                $this->values->liveclasseswhen ?? get_string('yes')
            );
        }
        if ($this->values->meetings !== null && isset($names[landingfields::SHORTNAME_MEETINGS])) {
            $facts[] = $this->fact($names[landingfields::SHORTNAME_MEETINGS], $this->values->meetings);
        }
        if ($this->values->workload !== null && isset($names[landingfields::SHORTNAME_WORKLOAD])) {
            $split = $this->hours_split();
            $note = $split === null ? null : get_string('landing_workloadsplit', self::COMPONENT, (object) [
                'online' => $this->hours_number($split['online']),
                'inperson' => $this->hours_number($split['inperson']),
            ]);
            $facts[] = $this->fact($names[landingfields::SHORTNAME_WORKLOAD], $this->hours_label(), $note);
        }
        if ($this->is_inperson() || $this->is_hybrid()) {
            if ($this->values->venue !== null && isset($names[landingfields::SHORTNAME_VENUE])) {
                $facts[] = $this->fact($names[landingfields::SHORTNAME_VENUE], $this->values->venue);
            }
            if ($this->values->city !== null && isset($names[landingfields::SHORTNAME_CITY])) {
                $facts[] = $this->fact($names[landingfields::SHORTNAME_CITY], $this->values->city);
            }
            // The street address is for whoever goes there once, on a date: not for a hybrid course's meetings.
            $withaddress = !$this->is_hybrid() || $this->is_event();
            if ($withaddress && $this->values->address !== null && isset($names[landingfields::SHORTNAME_ADDRESS])) {
                $facts[] = $this->fact($names[landingfields::SHORTNAME_ADDRESS], $this->values->address);
            }
        }
        return $facts;
    }

    /**
     * One row of the card's facts.
     *
     * @param string $label The row's label, plain spelling.
     * @param string $value The row's value, plain spelling.
     * @param string|null $note A second, smaller line under the value, or null.
     * @return array The row.
     */
    private function fact(string $label, string $value, ?string $note = null): array {
        return ['label' => $label, 'value' => $value, 'note' => $note];
    }

    /**
     * The content sections: one per non-empty textarea field, in a fixed order.
     *
     * On a public course every section is run through the HTML cleaner, as the
     * summary is; see {@see summary_html()} for why.
     *
     * @return array Sections of ['title' => plain string, 'html' => final HTML].
     */
    private function sections(): array {
        $sections = [];
        $order = [
            landingfields::SHORTNAME_AUDIENCE => $this->values->audience,
            landingfields::SHORTNAME_PROGRAMME => $this->values->programme,
            landingfields::SHORTNAME_CERTIFICATION => $this->values->certification,
        ];
        foreach ($order as $shortname => $html) {
            if ($html !== null && trim((string) $html) !== '' && isset($this->values->names[$shortname])) {
                /* Same rule as the summary: a public page is cleaned for everybody. The
                   textarea's export_value() honours the value's trust mark, so with
                   enabletrusttext on, an editor holding moodle/site:trustcontent ships
                   uncleaned HTML - fine behind login, stored script on the open internet. */
                $html = $this->ispublic ? clean_text($html, FORMAT_HTML) : $html;
                $sections[] = ['title' => $this->values->names[$shortname], 'html' => $html];
            }
        }
        return $sections;
    }

    /**
     * One template context per usable method.
     *
     * The markup contracts come from the plugins' own hooks and AMD modules:
     * enrol_self/enrol_page binds a button[type=submit][data-instance] with
     * data-form/data-id/data-title; enrol_apply/enrol_page binds
     * [data-instance][data-form] with apply.php as the no-JS transport; and
     * core_payment/gateways_modal binds [data-action=core_payment/triggerPayment]
     * carrying component/paymentarea/itemid/cost/successurl/description.
     *
     * @return array The method contexts.
     */
    private function method_contexts(): array {
        $context = \core\context\course::instance($this->course->id);
        $contexts = [];
        $primaryassigned = false;
        $isvisitor = $this->resolution->state === resolution::STATE_VISITOR;
        // The visitor's login button is the card's primary action wherever a login could lead somewhere.
        $loginprimary = $isvisitor && $this->resolution->loginwayin;
        foreach ($this->resolution->methods as $enrolmethod) {
            $instance = $enrolmethod->instance;
            $entry = [
                'instanceid' => (int) $instance->id,
                'isself' => false,
                'isapply' => false,
                'isfee' => false,
                'isguest' => false,
                'isfallback' => false,
                'primary' => false,
                'guestnote' => false,
                // A hybrid event names its tracks afterwards, once the methods to be drawn are known.
                'trackname' => null,
            ];
            switch ($enrolmethod->type) {
                case method::TYPE_SELF:
                    $entry['isself'] = true;
                    $entry['requirespassword'] = $enrolmethod->requirespassword;
                    $entry['datatitle'] = $this->instance_title($instance, 'enrol_self');
                    $entry['dataform'] = \enrol_self\form\enrol_form::class;
                    $entry['pageurl'] = $this->page_url();
                    $entry['enrolurl'] = (new \moodle_url('/enrol/index.php', [
                        'id' => $this->course->id,
                        'action' => 'enrol',
                        'sesskey' => sesskey(),
                    ]))->out(false);
                    break;
                case method::TYPE_APPLY:
                    $entry['isapply'] = true;
                    $entry['datatitle'] = $this->instance_title($instance, 'enrol_apply');
                    $entry['dataform'] = 'enrol_apply\\form\\application_form';
                    $entry['applyurl'] = (new \moodle_url('/enrol/apply/apply.php'))->out(false);
                    break;
                case method::TYPE_FEE:
                    $entry['isfee'] = true;
                    $entry['costdisplay'] = \core_payment\helper::get_cost_as_string($enrolmethod->cost, $enrolmethod->currency);
                    $entry['datacost'] = $enrolmethod->cost;
                    $entry['successurl'] = \enrol_fee\payment\service_provider::get_success_url('fee', (int) $instance->id)
                        ->out(false);
                    $coursename = $this->plain($this->course->fullname, $context);
                    $entry['description'] = get_string('purchasedescription', 'enrol_fee', $coursename);
                    $entry['pageurl'] = $this->page_url();
                    break;
                case method::TYPE_GUEST:
                    $entry['isguest'] = true;
                    // Guest access is the only way in for this visitor: say that nothing is enrolled.
                    $entry['guestnote'] = $isvisitor && !$this->resolution->loginwayin;
                    $entry['guesturl'] = $this->guest_url($enrolmethod)->out(false);
                    break;
                case method::TYPE_FALLBACK:
                    if (empty($this->fallbackhtml[$instance->id])) {
                        // The plugin's own hook decided to render nothing: hidden.
                        continue 2;
                    }
                    $entry['isfallback'] = true;
                    $entry['widgethtml'] = $this->fallbackhtml[$instance->id];
                    break;
            }
            // What the instance declares about its seats and its deadline, above its own button (D5).
            $entry['offer'] = offer::export($instance, time());
            /* The first real enrolment action gets the primary button; a fallback widget
               never does, because it carries its own button. Guest access is secondary
               beside a real enrolment and is promoted below only where none was found. */
            if (!$primaryassigned && in_array($enrolmethod->type, [method::TYPE_SELF, method::TYPE_APPLY, method::TYPE_FEE])) {
                $entry['primary'] = true;
                $primaryassigned = true;
            }
            $contexts[] = $entry;
        }
        /* With nothing else to enrol through, the first guest access is the card's primary action:
           a guest-only course, for a visitor no login would take further or for a reader already logged in.
           Never beside the visitor's login button, which then stays the primary action. */
        if (!$primaryassigned && !$loginprimary) {
            foreach ($contexts as $i => $entry) {
                if ($entry['isguest']) {
                    $contexts[$i]['primary'] = true;
                    break;
                }
            }
        }
        return $contexts;
    }

    /**
     * Where a guest access method's button leads.
     *
     * Guest access with a password lives in enrol_guest's widget on the standard page; the bypass
     * parameter keeps that request there instead of being forwarded straight back to this landing page.
     * Without a password, a reader who is logged in - the guest account included - opens the course
     * page, where enrol_guest lets them in. A visitor who is not logged in would only meet the login
     * page there, so where the course offers the guest entry the button leads to it instead
     * ({@see guestaccess}): the entry logs the visitor in as the guest and opens the course.
     *
     * @param method $enrolmethod The guest access method.
     * @return \core\url The button's address.
     */
    private function guest_url(method $enrolmethod): \core\url {
        $courseid = (int) $this->course->id;
        if ($enrolmethod->requirespassword) {
            return redirector::standard_page($courseid);
        }
        if (!isloggedin() && guestaccess::available($this->course)) {
            return links::guest_page($courseid);
        }
        return guestaccess::target($courseid);
    }

    /**
     * Whether the course is configured as an in-person offering.
     *
     * @return bool Whether the modality field holds the in-person option.
     */
    private function is_inperson(): bool {
        return $this->values->modality === landingfields::MODALITY_INPERSON;
    }

    /**
     * Whether the course is configured as a hybrid offering: on site and online at once.
     *
     * @return bool Whether the modality field holds the hybrid option.
     */
    private function is_hybrid(): bool {
        return $this->values->modality === landingfields::MODALITY_HYBRID;
    }

    /**
     * The landing page URL, for the modal and payment forms' no-JS transports.
     *
     * @return string The URL.
     */
    private function page_url(): string {
        return links::page((int) $this->course->id)->out(false);
    }

    /**
     * The workload chip/fact text.
     *
     * @return string The localised label.
     */
    private function hours_label(): string {
        return get_string('landing_hours', self::COMPONENT, $this->hours_number($this->values->workload));
    }

    /**
     * A number of hours as the page prints it: one decimal at most, trailing zeros stripped, localised.
     *
     * @param float $hours The hours.
     * @return string The number.
     */
    private function hours_number(float $hours): string {
        return format_float($hours, 1, true, true);
    }

    /**
     * A scheduled enrolment's start or end date, formatted in the viewer's time zone.
     *
     * @param int|null $timestamp The time, 0 or null for none.
     * @return string|null The short date, or null when there is no date.
     */
    private function scheduled_date(?int $timestamp): ?string {
        if (!$timestamp) {
            return null;
        }
        return userdate($timestamp, get_string('strftimedatefullshort', 'langconfig'));
    }

    /**
     * The course a conditional enrolment waits for: its name in the plain spelling and its address.
     *
     * The resolver names it only to a viewer actively enrolled in it ({@see access::get_next_action()}), so
     * the link opens a course the reader may enter.
     *
     * @return array|null ['name' => plain, 'url' => string], or null when the enrolment is not conditional.
     */
    private function prerequisite(): ?array {
        $courseid = (int) $this->resolution->prerequisiteid;
        if (!$courseid) {
            return null;
        }
        $course = get_course($courseid);
        $context = \core\context\course::instance($courseid);
        return [
            'name' => $this->plain($course->fullname, $context),
            'url' => (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
        ];
    }

    /**
     * The submission date of the pending application, formatted, when there is one.
     *
     * @return string|null The formatted date.
     */
    private function applied_on(): ?string {
        if (!$this->resolution->appliedtime) {
            return null;
        }
        return userdate($this->resolution->appliedtime, get_string('strftimedatefullshort', 'langconfig'));
    }

    /**
     * The category name in the plain spelling, for the hero kicker.
     *
     * The third argument of get() is alwaysreturnhidden, and it stays true: an anonymous
     * visitor of a public course must read the name under forcelogin, and
     * discoverability::is_public() already refuses a public course under an unlisted
     * category, so nothing reaches here that the visitor may not be told.
     *
     * The category guard below backs up redirector::is_ghosted(), which request::handle()
     * asks first: a viewer who reaches this page through a relationship with the course
     * (enrolled, awaiting a decision, staff) passes that guard and would otherwise read the
     * name of a category they must not discover. A visitor is not asked: is_public() has
     * already refused every course under an unlisted category.
     *
     * @return string|null The name, or null when the category cannot be read or must not be named.
     */
    private function category_name(): ?string {
        $category = \core_course_category::get($this->course->category, IGNORE_MISSING, true);
        if (!$category) {
            return null;
        }
        $loggedin = isloggedin() && !isguestuser();
        if ($loggedin && !category_access::is_category_discoverable((int) $this->course->category)) {
            return null;
        }
        return format_string($category->name, true, [
            'context' => \core\context\coursecat::instance($category->id),
            'escape' => false,
        ]);
    }

    /**
     * The badge grid: the stored pictures, each through the theme's own file route.
     *
     * Always the theme's route, public course or not: the picture type's own URL
     * applies the field's visibility rule, and the badge fields are visible to
     * nobody. The alternative text is the file name without its extension - what
     * the field's description tells the editor, and the one thing the editor
     * controls (route B has no caption field).
     *
     * @return array Entries of url and alt, in grid order, plain spelling.
     */
    private function badges(): array {
        $badges = [];
        foreach ($this->values->crests as $file) {
            $badges[] = [
                'url' => publicfiles::badge_url($this->course, $file)->out(false),
                'alt' => publicfiles::badge_alt($file->get_filename()),
            ];
        }
        return $badges;
    }

    /**
     * The presentation video, embedded by core's media manager, or null.
     *
     * The same check the course form made decides again here, so an address a
     * later change of the enabled players stopped embedding renders nothing
     * rather than a bare link. The player's own markup arrives as HTML for a
     * triple stash, sized by the stylesheet's 16:9 box: whichever enabled player
     * takes the address first (Video.JS by default, which also plays YouTube, or
     * the YouTube and Vimeo players' iframes). The caption follows the model, and
     * a held event shows the same address as its recording.
     *
     * The caption is also the frame's title, on purpose: the YouTube and Vimeo
     * players escape the name they are given with s() and then print it through
     * a double stash ({@see \media_youtube_plugin::embed_external()}), so a course
     * name holding an ampersand would read "&amp;" to a screen reader. The caption
     * has none, and it says what the frame holds.
     *
     * @param \core\context\course $context The course context.
     * @param bool $isheld Whether the event was held.
     * @return array|null Keys html, caption and host (the address's host name, plain spelling).
     */
    private function video(\core\context\course $context, bool $isheld): ?array {
        $url = $this->values->videourl;
        if ($url === null || !after_form_validation::embeddable($url)) {
            return null;
        }
        if ($isheld) {
            $caption = get_string('landing_videorecording', self::COMPONENT);
        } else if ($this->is_event()) {
            $caption = get_string('landing_videoevent', self::COMPONENT);
        } else {
            $caption = get_string('landing_videocourse', self::COMPONENT);
        }
        $html = \core_media_manager::instance()->embed_url(new \moodle_url($url), $caption);
        return [
            'html' => $html,
            'caption' => $caption,
            'host' => preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)),
        ];
    }

    /**
     * The course image URL (first overview file), safe for a style attribute.
     *
     * On a public course the image goes through the theme's own file route,
     * whoever is looking: core's course/overviewfiles answers a visitor with
     * a redirect to the login page under forcelogin, and a public page is the
     * same page for everybody. Otherwise, core's cached course image URL.
     *
     * The URL is refused outright unless it may stand inside url('...'), which
     * is {@see styleurl}'s decision: the template prints it into a style
     * attribute, and a double stash is an HTML escaping rather than a CSS one -
     * a browser hands the CSS parser the decoded attribute, so s() turning a
     * quote into `&#039;` gives the engine a quote back. PARAM_URL alone passes
     * a quote and a parenthesis straight through.
     *
     * A refusal costs the hero its image and nothing else, and no real image is
     * refused: both sources above build through core's pluginfile url factory,
     * whose path segments are percent-encoded.
     *
     * @return string|null The URL, or null when the course has no image.
     */
    private function hero_image_url(): ?string {
        if ($this->ispublic) {
            $file = publicfiles::hero_file($this->course);
            $url = ($file === null) ? null : publicfiles::hero_url($this->course, $file)->out(false);
        } else {
            $url = \core_course\external\course_summary_exporter::get_course_image($this->course);
        }
        if (!$url) {
            return null;
        }
        $safe = styleurl::safe((string) $url);
        return ($safe === '') ? null : $safe;
    }

    /**
     * The course summary as final HTML (the canonical core rendering path).
     *
     * On a public course the summary is cleaned, whoever is looking. noclean
     * skips HTMLPurifier unless the site sets forceclean - it bypasses even the
     * trusted-author gate, because format_text() consults trust only when the
     * flag is left null - and moodle/course:update is RISK_XSS and CAP_ALLOW for
     * editing teachers. Behind login that is the accepted trust model, and the
     * non-public page keeps it; on a page served to the open internet it is
     * stored script executing same-origin.
     *
     * @param \core\context\course $context The course context.
     * @return string|null The HTML, or null when the summary is empty.
     */
    private function summary_html(\core\context\course $context): ?string {
        global $CFG;
        if (trim((string) $this->course->summary) === '') {
            return null;
        }
        require_once($CFG->libdir . '/filelib.php');
        if ($this->ispublic) {
            // Embedded files go through the theme's route, the only one a visitor can fetch.
            $text = publicfiles::rewrite_summary((string) $this->course->summary, $context);
        } else {
            // Files are stored under itemid 0 but the pluginfile URL carries no itemid segment.
            $text = file_rewrite_pluginfile_urls($this->course->summary, 'pluginfile.php', $context->id, 'course', 'summary', null);
        }
        return format_text($text, $this->course->summaryformat, [
            'context' => $context,
            'overflowdiv' => true,
            'noclean' => !$this->ispublic,
            'para' => false,
        ]);
    }

    /**
     * A modal title for one enrol instance: its name, or the plugin's own name.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param string $component The enrol plugin component for the fallback name.
     * @return string The title, plain spelling.
     */
    private function instance_title(\stdClass $instance, string $component): string {
        $context = \core\context\course::instance($this->course->id);
        $name = trim((string) $instance->name);
        return ($name !== '') ? $this->plain($name, $context) : get_string('pluginname', $component);
    }

    /**
     * Format an admin-set name for a double-stash sink (which escapes for itself).
     *
     * @param string $value The stored value.
     * @param \core\context\course $context The course context.
     * @return string The plain spelling.
     */
    private function plain(string $value, \core\context\course $context): string {
        return format_string($value, true, ['context' => $context, 'escape' => false]);
    }
}
