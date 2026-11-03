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
 * Theme Dimensions - One card of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course\customfield\course_handler;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\dialogue;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\publicfiles;
use theme_dimensions\local\landing\redirector;
use theme_dimensions\local\landingfields;

/**
 * Builds the template context of one course card, the same markup for the card and the list view.
 *
 * Everything a card says about a course is decided here from data the
 * listing already read in bulk: the population row (state, favourite,
 * visibility), the course row, the landing page values and the image.
 * Nothing in this class queries the database - a page of forty-eight
 * cards builds forty-eight of these and must cost no statement for it - and
 * the one context it asks for was preloaded by the population. With filterall
 * on, format_string() still loads each course context's filters; the render
 * budget tests assert that precondition.
 *
 * Names and every other plain-text value leave in the PLAIN spelling for the
 * template's double stashes. There is no final HTML in a card at all.
 *
 * The crests over the image are the COURSE's own landing page badges and never the
 * category's ({@see badges()}); a course without a landing page model shows none.
 *
 * The eleven classifier states and what each one prints: the state pill of the
 * enrolment matrix's shared state area, one icon and one sentence. The pill is
 * the design system's own, never a Bootstrap utility: one class per state, and
 * scss/category.scss pairs each state with a background AND a text colour
 * token in both colour modes. Bootstrap 5's .badge forces white text and its
 * own padding, which is why the card writes neither it nor any bg-* here.
 * The icon is decorative (aria-hidden): the sentence carries the state.
 *
 * | state       | pill class             | call to action                                          |
 * |-------------|------------------------|---------------------------------------------------------|
 * | enrolled    | dim-badge--enrolled    | access the course                                       |
 * | scheduled   | dim-badge--scheduled   | the route when there is one, else the landing page's details |
 * | pending     | dim-badge--pending     | view the application (outline)                          |
 * | waitlisted  | dim-badge--waitlisted  | view the application (outline)                          |
 * | suspended   | dim-badge--suspended   | the route when there is one, else none                  |
 * | expired     | dim-badge--expired     | the route when there is one, else none                  |
 * | open        | dim-badge--open        | enrol, on the landing page when there is one                 |
 * | free        | dim-badge--free        | access the course                                       |
 * | key         | dim-badge--key         | enter with the key, on the standard enrolment page      |
 * | conditional | dim-badge--conditional | view the prerequisite course (outline)                  |
 * | none        | dim-badge--none        | none: shown, muted, never explained                     |
 *
 * Every class string is "dim-badge" plus the class above. The three badges
 * outside the table read the same way: dim-badge plus dim-badge--held,
 * --public and --hidden.
 *
 * The route line (decision D2 of the enrolment matrix, core's default kept):
 * a card telling the viewer about a row of theirs that is not active - it
 * starts later, it is an application, it is suspended, it ended - also says
 * when a method would take them now, and its call to action then goes there.
 * The routes are local_unlistedcourses' batch's, read once for the page
 * ({@see \theme_dimensions\local\category\cards::routes()}); the card
 * never decides one itself. A pending or waitlisted card keeps "view status"
 * as its button, whose page offers the route as well.
 *
 * The opening line sits in the same place: a card with nothing on offer, or a
 * suspended or ended one, whose way in is refused only because an enrolment
 * window has not opened yet says on which date it opens. The date is the same
 * batch's `opens`, never worked out here ({@see opens_label()}); a scheduled,
 * pending or waitlisted card has its own date or route and never prints one.
 *
 * The badge is about the course; the link is about the viewer, and the table
 * above is the badge's half alone. A viewer who may already enter the course
 * without enrolling in it - a manager, a course creator, anybody core's own
 * {@see can_access_course()} would let in through {@see is_viewing()} - is
 * sent to the course by every card, whatever the badge says: the row of the
 * table decides the WORD, {@see may_enter()} decides the DESTINATION, so such
 * a viewer is never sent to the landing page, the page for somebody who has to
 * enrol.
 *
 * The star carries THREE labels, not one. starlabel is the label of its
 * current state, which is what a reader without JavaScript gets and never
 * changes; starlabeladd and starlabelremove are both spellings, so the client
 * module can relabel the button the instant it is pressed instead of asking
 * the server for a sentence it already had. They are present whenever the
 * star is, because a button whose pressed state flips needs the word for the
 * state it is flipping INTO before the flip, not after it.
 *
 * A held event (the event model, an end date in the past) prints "held"
 * whatever the classifier said; it is drawn in the muted style, and offers
 * nothing to press, unless the viewer may enter the course, in which case it
 * stays reachable with its badge unchanged. The anonymous shell
 * prints "public access" - a property of the course, not of the viewer - and
 * its one call to action is the course's own landing page, which is the page a
 * visitor may actually read; a public course that chose no landing page model has
 * nothing to send a visitor to, so it gets no call to action at all.
 *
 * "More information" is a link to the card's own page ({@see url()}), and with
 * JavaScript it opens a dialogue (round I, lot C2; stage L2 of
 * docs/vitrine-ratings/PROPOSAL.md). What the page decided stays on the card:
 * the kicker and the two buttons under `modal` ({@see modal()}), which the
 * template writes onto the link, and the cover, the pill and the title the
 * card already prints. What only the dialogue shows - the excerpt, the facts,
 * the programme - is fetched when it opens
 * ({@see \theme_dimensions\local\category\dialogue}). Its two buttons decide
 * nothing of their own: the secondary one is the card's link ({@see url()} with
 * {@see may_enter()}), the primary one the state's word over that same rule,
 * and where both would open one page only the primary is drawn.
 *
 * A course tool_courserating rates, and somebody rated, opens its meta line with
 * the rating (`rating`: the average to one decimal in the reader's language, the
 * count, and the sentence a screen reader is told instead of the glyph and the
 * digits), on both shells: the plugin shows the aggregate to whoever may see the
 * course listed. The listing reads it once for the page
 * ({@see \theme_dimensions\local\category\cards::ratings()}); the card spells
 * it as the dialogue does ({@see \theme_dimensions\local\category\dialogue::rating_line()}).
 *
 * The card also carries the facts the filter line's preview decides a criterion on (contract section 6,
 * js/esm/src/filterline/preview.ts): the state chip the course counts under (`chip`, the classifier's
 * {@see classifier::chip()} of the row's own state, exactly what the scope's filter keeps it by), the value
 * of each filter field ({@see field_values()}) and the start day in the site's timezone (`startday`, the
 * day {@see criteria::day_start()} compares a period's bounds with). The favourite is the star's own
 * aria-pressed, which the star module keeps live. Each is what the card already shows its reader, and a
 * visitor's card states less: no chip and no star (it knows no viewer), only the fields a visitor may see,
 * and a start day only where the card prints one, an event's date tile. Nothing of it costs a statement.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_card implements \core\output\renderable, \core\output\templatable {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * @var array state => [pill classes, label string key, Font Awesome icon]. One design-system class per state.
     *
     * The icons are the enrolment matrix mockup's, in the Font Awesome 6 names core 5.2 ships; card_markup_test
     * checks each name against core's own icon font.
     */
    private const BADGES = [
        classifier::STATE_ENROLLED => ['dim-badge dim-badge--enrolled', 'category_state_enrolled', 'fa-circle-check'],
        classifier::STATE_SCHEDULED => ['dim-badge dim-badge--scheduled', 'category_state_scheduled', 'fa-calendar'],
        classifier::STATE_PENDING => ['dim-badge dim-badge--pending', 'category_state_pending', 'fa-hourglass-half'],
        classifier::STATE_WAITLISTED => ['dim-badge dim-badge--waitlisted', 'category_state_waitlisted', 'fa-list-ul'],
        classifier::STATE_SUSPENDED => ['dim-badge dim-badge--suspended', 'category_state_suspended', 'fa-circle-pause'],
        classifier::STATE_EXPIRED => ['dim-badge dim-badge--expired', 'category_state_expired', 'fa-clock'],
        classifier::STATE_OPEN => ['dim-badge dim-badge--open', 'category_state_open', 'fa-right-to-bracket'],
        classifier::STATE_FREE => ['dim-badge dim-badge--free', 'category_state_free', 'fa-globe'],
        classifier::STATE_KEY => ['dim-badge dim-badge--key', 'category_state_key', 'fa-key'],
        classifier::STATE_CONDITIONAL => ['dim-badge dim-badge--conditional', 'category_state_conditional', 'fa-lock'],
        classifier::STATE_NONE => ['dim-badge dim-badge--none', 'category_state_none', 'fa-circle-minus'],
    ];

    /** @var string[] The states whose card may say when enrolment opens: no date or route of their own. */
    private const OPENS_STATES = [classifier::STATE_NONE, classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED];

    /** @var string[] The badge of an event already held. */
    private const BADGE_HELD = ['dim-badge dim-badge--held', 'category_state_held', 'fa-clock'];

    /** @var string[] The badge every card of the anonymous shell carries. */
    private const BADGE_PUBLIC = ['dim-badge dim-badge--public', 'category_state_public', 'fa-globe'];

    /** @var string[] The badge staff see on a course hidden from students. */
    private const BADGE_HIDDEN = ['dim-badge dim-badge--hidden', 'category_hiddenbadge', 'fa-eye-slash'];

    /**
     * @var int How many of the course's badges a card draws.
     *
     * The landing page's grid holds four; a card's corner holds three. The geometry decides it: at
     * 80px each, the smallest size the crests are legible at, three of them and their gaps already
     * run most of the width of a card in the three-column grid, and a fourth would reach the kind
     * tag on the other side.
     */
    public const MAX_BADGES = 3;

    /**
     * @var string[] The filter fields whose value the card prints whatever the field's visibility: the model
     *   (the kind tag over the image) and the modality (the meta line). Every other field is stated only where
     *   core lets the reader see it ({@see field_values()}).
     */
    private const PRINTED_FIELDS = [landingfields::SHORTNAME_MODEL, landingfields::SHORTNAME_MODALITY];

    /**
     * @var array The primary button of the dialogue for the three states it relabels: state => [label key, icon].
     *
     * Every other state keeps the card's own call to action ({@see cta()}), so the dialogue never offers less
     * than the card it opened from; a state the card offers nothing for gets no primary button either.
     */
    private const MODAL_PRIMARY = [
        classifier::STATE_ENROLLED => ['category_modal_continue', 'fa-play'],
        classifier::STATE_FREE => ['category_modal_free', 'fa-arrow-right'],
        classifier::STATE_OPEN => ['category_modal_enrol', 'fa-arrow-right'],
    ];

    /**
     * @var array The strings every card of the request spells alike: language => component => key => text.
     *
     * A listing builds a card per course and each one spelled the same dozen labels again; the
     * parameterised ones (the star's, the dates', the hours') still go through get_string() per card. Keyed
     * by the language in force, so a request or a test that switches language reads its own spelling;
     * {@see reset_strings()} is for a test that changes a language pack under the same language.
     */
    private static array $strings = [];

    /**
     * Constructor.
     *
     * @param \stdClass $course The course row ({@see \theme_dimensions\local\category\cards::records()}).
     * @param \stdClass $row The population row: state and favourite for a logged-in viewer, visible, category.
     * @param \stdClass $values The course's landing page values ({@see landingfields::values()} shape, filters off).
     * @param \moodle_url|null $image The card image, or null for the flat fallback.
     * @param string|null $overline The course's own subcategory, plain, or null when it sits in the listed root.
     * @param bool $showstar Whether the favourite star is drawn (a logged-in, non-guest viewer on the logged-in shell).
     * @param bool $anonymous Whether the anonymous shell is drawing, which knows no viewer state.
     * @param array $routes The routes local_unlistedcourses' batch reports for this course, each kind and instanceid.
     * @param \stdClass|null $prerequisite The course a conditional card waits for, {id, fullname}, context preloaded.
     * @param int|null $opens When a method refusing this viewer only for its window opens, per the same batch.
     * @param string|null $kicker The course's own category, plain, or null where it may not be named (the dialogue's kicker).
     * @param \core_customfield\field_controller[] $fields The page's filter fields, fieldid => field ({@see field_values()}).
     * @param \stdClass|null $rating The course's rating, {avg, count} ({@see \theme_dimensions\local\category\cards::ratings()}),
     *   null for a course the plugin does not rate, nobody rated, or a site without the plugin.
     */
    public function __construct(
        /** @var \stdClass The course row. */
        private readonly \stdClass $course,
        /** @var \stdClass The population row. */
        private readonly \stdClass $row,
        /** @var \stdClass The landing page values. */
        private readonly \stdClass $values,
        /** @var \moodle_url|null The card image. */
        private readonly ?\moodle_url $image,
        /** @var string|null The subcategory overline. */
        private readonly ?string $overline,
        /** @var bool Whether the star is drawn. */
        private readonly bool $showstar,
        /** @var bool Whether the anonymous shell is drawing. */
        private readonly bool $anonymous = false,
        /** @var array The routes beside a relationship, each ['kind' => string, 'instanceid' => int]. */
        private readonly array $routes = [],
        /** @var \stdClass|null The prerequisite of a conditional card. */
        private readonly ?\stdClass $prerequisite = null,
        /** @var int|null The batch's opening date for this course and viewer, a timestamp. */
        private readonly ?int $opens = null,
        /** @var string|null The course's own category name, plain: the dialogue's kicker. */
        private readonly ?string $kicker = null,
        /** @var \core_customfield\field_controller[] The page's filter fields, fieldid => field. */
        private readonly array $fields = [],
        /** @var \stdClass|null tool_courserating's aggregate for this course, {avg, count}, or null where it has none. */
        private readonly ?\stdClass $rating = null,
    ) {
    }

    /**
     * A string that holds no parameter, read once per language for the whole request ({@see self::$strings}).
     *
     * @param string $key The string's key.
     * @param string $component Its component, the theme's by default.
     * @return string
     */
    private static function text(string $key, string $component = self::COMPONENT): string {
        return self::$strings[current_language()][$component][$key] ??= get_string($key, $component);
    }

    /**
     * Forget the strings read so far: the next card reads every label from the string manager again.
     *
     * @return void
     */
    public static function reset_strings(): void {
        self::$strings = [];
    }

    /**
     * Export the card context.
     *
     * @param \core\output\renderer_base $output The renderer.
     * @return \stdClass The template context.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        $courseid = (int) $this->course->id;
        $context = \core\context\course::instance($courseid);
        $fullname = format_string($this->course->fullname, true, ['context' => $context, 'escape' => false]);

        $state = $this->anonymous ? null : (string) ($this->row->state ?? classifier::STATE_NONE);
        if ($state === classifier::STATE_CONDITIONAL && $this->prerequisite === null) {
            // A conditional card names its prerequisite or is no conditional card: nothing to name, nothing on offer.
            $state = classifier::STATE_NONE;
        }
        $isevent = $this->values->model === landingfields::MODEL_EVENT;
        $held = $isevent && (int) $this->course->enddate > 0 && (int) $this->course->enddate < time();
        $enrolled = $state === classifier::STATE_ENROLLED;
        $heldonly = !$this->anonymous && $held && !$enrolled;

        if ($this->anonymous) {
            [$badgeclasses, $badgekey, $badgeicon] = self::BADGE_PUBLIC;
        } else if ($heldonly) {
            [$badgeclasses, $badgekey, $badgeicon] = self::BADGE_HELD;
        } else {
            [$badgeclasses, $badgekey, $badgeicon] = self::BADGES[$state] ?? self::BADGES[classifier::STATE_NONE];
        }
        $badgelabel = $this->badge_label($heldonly || $this->anonymous ? null : $state, $badgekey, $context);
        $mayenter = $this->may_enter($state, $context);
        $muted = $this->anonymous ? false : (!$mayenter && ($heldonly || $state === classifier::STATE_NONE));
        $url = $this->url($mayenter);
        /* A route is printed beside a row of the viewer's that is not active, when the plugin's batch reports one;
           never for somebody who may walk in already, and never on an event already held, which enrols nobody. */
        $hasroute = !$this->anonymous && !$mayenter && !$heldonly && !empty($this->routes)
            && in_array($state, classifier::ROUTE_STATES, true);
        $openslabel = $this->opens_label($state, $mayenter, $heldonly);
        $cta = ($held && !$mayenter) ? null : $this->cta($state, $url, $mayenter, $hasroute);
        $favourite = !$this->anonymous && !empty($this->row->favourite);
        $kindkey = match ($this->values->model) {
            landingfields::MODEL_COURSE => 'landingfield_model_course',
            landingfields::MODEL_EVENT => 'landingfield_model_event',
            landingfields::MODEL_TRAIL => 'landingfield_model_trail',
            default => null,
        };
        $badges = $this->badges();
        $start = (int) $this->course->startdate;
        // A visitor's card states a start day only where it prints one: an event's date tile.
        $hasstartday = !$this->anonymous || ($isevent && $start > 0);
        $rating = $this->rating === null ? null : dialogue::rating_line($this->rating->avg, $this->rating->count);

        return (object) [
            'id' => $courseid,
            'fullname' => $fullname,
            'url' => $url?->out(false),
            'hasurl' => $url !== null,
            'overline' => $this->overline,
            'hasoverline' => $this->overline !== null && $this->overline !== '',
            'imageurl' => $this->image ? $this->image->out(false) : null,
            'hasimage' => $this->image !== null,
            // Lazy unless the listing puts the card in its first row ({@see category_listing::FIRST_ROW}).
            'eager' => false,
            // Never fetched ahead of the rest unless the listing names this cover the page's first ({@see category_listing}).
            'priority' => false,
            'badges' => $badges,
            'hasbadges' => !empty($badges),
            'badgeslabel' => self::text('category_badges'),
            'isevent' => $isevent,
            'kindlabel' => $kindkey ? self::text($kindkey) : null,
            'haskind' => $kindkey !== null,
            'datetile' => $this->datetile($isevent),
            'meta' => $this->meta($isevent),
            'rating' => $rating,
            'hasrating' => $rating !== null,
            'state' => $state ?? '',
            'chip' => $this->anonymous ? '' : classifier::chip((string) ($this->row->state ?? classifier::STATE_NONE)),
            'fieldvalues' => $this->field_values(),
            'hasstartday' => $hasstartday,
            'startday' => $hasstartday && $start > 0 ? criteria::iso($start) : '',
            'badge' => ['label' => $badgelabel, 'classes' => $badgeclasses, 'icon' => $badgeicon],
            'hasroute' => $hasroute,
            'routelabel' => $hasroute ? self::text('category_route_enrol') : '',
            'hasopens' => $openslabel !== '',
            'openslabel' => $openslabel,
            'ishidden' => empty($this->row->visible),
            'hiddenbadge' => [
                'label' => self::text(self::BADGE_HIDDEN[1]),
                'classes' => self::BADGE_HIDDEN[0],
            ],
            'ismuted' => $muted,
            'cta' => $cta,
            'hascta' => $cta !== null,
            'showstar' => $this->showstar,
            'favourite' => $favourite,
            'starlabel' => get_string($favourite ? 'category_star_remove' : 'category_star_add', self::COMPONENT, $fullname),
            'starlabeladd' => get_string('category_star_add', self::COMPONENT, $fullname),
            'starlabelremove' => get_string('category_star_remove', self::COMPONENT, $fullname),
            'detailslabel' => self::text('category_details'),
            'modal' => $this->modal($state, $url, $held, $openslabel, $isevent, $cta),
        ];
    }

    /**
     * The filter fields' values this card states, for the filter line's preview: `fieldid:value` pairs, space-separated.
     *
     * A field is stated when the card prints it ({@see PRINTED_FIELDS}) or when core lets this reader see it
     * ({@see course_handler::can_view()}: everybody for a field visible to all, the course's editors for one
     * visible to teachers, nobody for a hidden one), so a visitor's card never says more than a visitor may
     * see. Every stated field has a pair, `:0` where the course holds no value, and a field the card does not
     * state has none, which the preview reads as a fact it does not know. A row without the population's
     * values (the page cut in SQL) states none. The capability is answered from the access data the request
     * already holds, at the context the population preloaded: no statement.
     *
     * @return string The pairs, '' for none.
     */
    private function field_values(): string {
        $values = $this->row->fieldvalues ?? null;
        if (!is_array($values) || !$this->fields) {
            return '';
        }
        $handler = course_handler::create();
        $pairs = [];
        foreach ($this->fields as $fieldid => $field) {
            if (!array_key_exists((int) $fieldid, $values)) {
                continue;
            }
            $printed = in_array($field->get('shortname'), self::PRINTED_FIELDS, true);
            if ($printed || $handler->can_view($field, (int) $this->course->id)) {
                $pairs[] = (int) $fieldid . ':' . (int) $values[(int) $fieldid];
            }
        }
        return implode(' ', $pairs);
    }

    /**
     * The dialogue's keys the page decides: the kicker and its two buttons, written onto the card's link.
     *
     * The SECONDARY button is the card's own link, whatever it is ({@see url()} with {@see may_enter()}): the
     * course for somebody who may walk in, else the landing page of a course that chose a model, else the standard
     * enrolment page - and nothing on the anonymous shell for a course with no landing page, whose card links
     * nowhere either. It names the course or the event.
     *
     * The PRIMARY button is {@see modal_primary()}. When it would open the very page the secondary one opens -
     * the enrolled reader's "Continue course" is the course page - only the primary is drawn: it is the one
     * that says what the page is FOR. A disabled primary goes nowhere, so the secondary always stays beside it.
     *
     * @param string|null $state The classifier state, null on the anonymous shell.
     * @param \moodle_url|null $url The card's link ({@see url()}).
     * @param bool $held Whether this is an event already held.
     * @param string $openslabel The card's opening line ({@see opens_label()}), '' where it prints none.
     * @param bool $isevent Whether the course chose the event model.
     * @param array|null $cta The card's call to action ({@see cta()}).
     * @return array The `modal` keys of the card context.
     */
    private function modal(?string $state, ?\moodle_url $url, bool $held, string $openslabel, bool $isevent, ?array $cta): array {
        $primary = $this->modal_primary($state, $url, $held, $openslabel, $cta);
        $secondary = $url === null ? null : [
            'label' => self::text($isevent ? 'category_modal_eventpage' : 'category_modal_coursepage'),
            'url' => $url->out(false),
        ];
        if ($primary !== null && !$primary['disabled'] && $secondary !== null && $primary['url'] === $secondary['url']) {
            $secondary = null;
        }
        $kicker = $this->kicker ?? '';
        return [
            'kicker' => $kicker,
            'haskicker' => $kicker !== '',
            'primary' => $primary,
            'hasprimary' => $primary !== null,
            'secondary' => $secondary,
            'hassecondary' => $secondary !== null,
        ];
    }

    /**
     * The dialogue's primary button, or null where it draws none.
     *
     * An event already held enrols nobody and a visitor's card knows no state, so neither gets one. A course
     * whose way in opens later - the card's opening line, {@see opens_label()}, decided exactly as the card
     * decides it - gets a DISABLED button carrying that date, which is then printed nowhere else in the
     * dialogue. Enrolled, free and open are relabelled ({@see MODAL_PRIMARY}): the first two go where the card
     * goes (the course, since {@see may_enter()} lets both in), and "Enrol" goes to the enrolment surface the
     * card's link would be for a reader who may NOT walk in ({@see url()} asked with false), so staff who may
     * open the course are offered both. Every other state keeps the card's own call to action.
     *
     * @param string|null $state The classifier state, null on the anonymous shell.
     * @param \moodle_url|null $url The card's link ({@see url()}).
     * @param bool $held Whether this is an event already held.
     * @param string $openslabel The card's opening line, '' where it prints none.
     * @param array|null $cta The card's call to action ({@see cta()}).
     * @return array|null ['label' => plain, 'url' => string|null, 'disabled' => bool, 'note' => plain, 'icon' => string].
     */
    private function modal_primary(?string $state, ?\moodle_url $url, bool $held, string $openslabel, ?array $cta): ?array {
        if ($this->anonymous || $held) {
            return null;
        }
        if ($openslabel !== '') {
            // The format is the string's own day/month/year; userdate()'s fixday would drop the day's leading zero.
            $date = userdate((int) $this->opens, self::text('category_modal_opensformat'), 99, false);
            return [
                'label' => get_string('category_modal_opens', self::COMPONENT, $date),
                'url' => null,
                'disabled' => true,
                'note' => self::text('category_modal_opensnote'),
                'icon' => 'fa-calendar',
            ];
        }
        if (isset(self::MODAL_PRIMARY[$state])) {
            [$key, $icon] = self::MODAL_PRIMARY[$state];
            $target = $state === classifier::STATE_OPEN ? $this->url(false) : $url;
            if ($target === null) {
                return null;
            }
            return [
                'label' => self::text($key),
                'url' => $target->out(false),
                'disabled' => false,
                'note' => '',
                'icon' => $icon,
            ];
        }
        if ($cta === null) {
            return null;
        }
        return ['label' => $cta['label'], 'url' => $cta['url'], 'disabled' => false, 'note' => '', 'icon' => 'fa-arrow-right'];
    }

    /**
     * The sentence of the state pill, with the one value the three dated or named states carry.
     *
     * One literal branch per string that takes a value: the start date of a scheduled enrolment and the end
     * date of an ended one, in the viewer's time zone, and the prerequisite's name, formatted in its own context
     * (preloaded with it) in the plain spelling, because the pill renders it through a double stash.
     *
     * @param string|null $state The classifier state; null where the held or public badge stands in for it.
     * @param string $key The label string key the badge table gives.
     * @param \core\context\course $context The course's context, for a label carrying no other one.
     * @return string The plain sentence.
     */
    private function badge_label(?string $state, string $key, \core\context\course $context): string {
        switch ($state) {
            case classifier::STATE_SCHEDULED:
                return get_string('category_state_scheduled', self::COMPONENT, $this->date((int) ($this->row->startsat ?? 0)));
            case classifier::STATE_EXPIRED:
                return get_string('category_state_expired', self::COMPONENT, $this->date((int) ($this->row->endsat ?? 0)));
            case classifier::STATE_CONDITIONAL:
                $name = format_string($this->prerequisite->fullname, true, [
                    'context' => \core\context\course::instance((int) $this->prerequisite->id, IGNORE_MISSING) ?: $context,
                    'escape' => false,
                ]);
                return get_string('category_state_conditional', self::COMPONENT, $name);
            default:
                return self::text($key);
        }
    }

    /**
     * The opening line under the pill, or an empty string where the card prints none: the one decision of it.
     *
     * The date is local_unlistedcourses' `opens` ({@see \local_unlistedcourses\access::get_next_actions()}): set
     * only when every method refuses the viewer and one of them refuses them only because its window has not
     * opened, so a cohort, a full course, a capability or the viewer's own row give none, and the plugin answers
     * a visitor with nothing. It is printed on the states whose card has no date or route of its own - nothing on
     * offer, suspended, ended - and never for somebody who may walk in already, nor on an event already held,
     * which enrols nobody. Formatted as the pill's own dates are.
     *
     * @param string|null $state The classifier state, null on the anonymous shell.
     * @param bool $mayenter Whether this viewer may enter the course without enrolling ({@see may_enter()}).
     * @param bool $heldonly Whether the held badge stands in for the state.
     * @return string The plain sentence, or '' for none.
     */
    private function opens_label(?string $state, bool $mayenter, bool $heldonly): string {
        if ($mayenter || $heldonly || $this->opens === null) {
            return '';
        }
        // The anonymous shell's state is null, which no list holds: a visitor's card prints no date.
        if (!in_array($state, self::OPENS_STATES, true)) {
            return '';
        }
        return get_string('category_state_enrolmentopens', self::COMPONENT, $this->date($this->opens));
    }

    /**
     * The crests over the card image: the COURSE's own landing page badges, up to {@see MAX_BADGES}.
     *
     * The card shows the course's badges and never the category's ({@see crests()} says which).
     *
     * @return array Entries of ['url' => string, 'alt' => string], in grid order, plain spelling.
     */
    private function badges(): array {
        return self::crests($this->course, $this->values);
    }

    /**
     * A course's crests: its own landing page badges, up to {@see MAX_BADGES}, named through the theme's file route.
     *
     * The badges of the COURSE and never of the category it sits in, whose own crests belong to the
     * category's hero and to its directory card: the two sets are stored in different places by
     * different people and mean different things, these being the institutions running the course.
     * A course with no landing page model shows none, even where badge files survive a model that was
     * cleared, because the route serves a badge only while the course carries a model
     * ({@see publicfiles::resolve()}) and an address nobody may open is no crest.
     *
     * They cost no statement: {@see \theme_dimensions\local\landingfields::values_many()}
     * already read the badge files, so this only names files in hand. Always the theme's own file
     * route, whoever is looking: the picture fields are visible to nobody, so the picture type's own
     * URL refuses them to everybody, and that route's badge branch serves a visitor a public course's
     * badge and a logged-in viewer any course they may see. Shared with
     * theme_dimensions_course_badges(), the callback other plugins' cards ask.
     *
     * @param \stdClass $course The course row; only its id is read.
     * @param \stdClass $values The course's landing page values ({@see landingfields::values()} shape).
     * @return array Entries of ['url' => string, 'alt' => string], in grid order, plain spelling.
     */
    public static function crests(\stdClass $course, \stdClass $values): array {
        if ($values->model <= 0) {
            return [];
        }
        $badges = [];
        foreach (array_slice($values->crests, 0, self::MAX_BADGES) as $file) {
            $badges[] = [
                'url' => publicfiles::badge_url($course, $file)->out(false),
                'alt' => publicfiles::badge_alt($file->get_filename()),
            ];
        }
        return $badges;
    }

    /**
     * The card's one real link: into the course for somebody who may enter it, onto the enrolment surface otherwise.
     *
     * The landing page is the enrolment surface of a course that chose a model; the
     * standard enrolment page is everybody else's. Both are the pages the
     * after_config forwarding already lands people on, so the card points at
     * the destination directly rather than at a redirect.
     *
     * On the anonymous shell the standard enrolment page is no destination at all: it answers a
     * visitor with the login form, so a public course that chose no landing page model gets no link,
     * and its title is plain text.
     *
     * @param bool $mayenter Whether this viewer may enter the course without enrolling ({@see may_enter()}).
     * @return \moodle_url|null The link, or null for a card that must not link anywhere.
     */
    private function url(bool $mayenter): ?\moodle_url {
        $courseid = (int) $this->course->id;
        if ($mayenter) {
            return new \moodle_url('/course/view.php', ['id' => $courseid]);
        }
        if ($this->values->model > 0) {
            return links::page($courseid);
        }
        if ($this->anonymous) {
            return null;
        }
        return new \moodle_url('/enrol/index.php', ['id' => $courseid]);
    }

    /**
     * A date as the cards print it, in the viewer's time zone.
     *
     * @param int $timestamp The time.
     * @return string The short date.
     */
    private function date(int $timestamp): string {
        return userdate($timestamp, self::text('strftimedatefullshort', 'langconfig'));
    }

    /**
     * Whether this viewer may walk into the course, which is a question about the VIEWER and not about the course.
     *
     * This is core's own {@see can_access_course()} with its enrolment half
     * already answered: the classifier resolved the enrolment for the whole
     * page in bulk, so what is left is the branch that lets staff in without
     * an enrolment - {@see is_viewing()}, which is `moodle/course:view` at the
     * course context. That context was preloaded by the population, and the
     * capability is answered out of the access data the request already
     * carries, so a page of forty-eight cards asks it forty-eight times and
     * costs no statement for it (pinned in the budget test).
     *
     * Core's order is kept, and the order is the whole rule: `can_access_course()`
     * refuses a hidden course to anybody without `moodle/course:viewhiddencourses`
     * BEFORE it asks `is_viewing()`, so holding `moodle/course:view` alone does
     * not open a hidden course. A viewer who may not see it hidden must not be
     * handed a link that then refuses them, and the listing agrees: its own gate
     * admits a hidden course to an active enrolment or to that same capability
     * and to nothing else.
     *
     * The two states the classifier already resolved come first and are left
     * exactly as they were: an enrolment and guest access were always the
     * course's own door. Free is guest access with no password only; guest
     * access behind a key reads key, whose way in is the key form on the
     * standard enrolment page.
     *
     * @param string|null $state The classifier state, null on the anonymous shell.
     * @param \core\context\course $context The course context, preloaded by the population.
     * @return bool
     */
    private function may_enter(?string $state, \core\context\course $context): bool {
        if ($this->anonymous) {
            return false;
        }
        if ($state === classifier::STATE_ENROLLED || $state === classifier::STATE_FREE) {
            return true;
        }
        if (empty($this->row->visible) && !has_capability('moodle/course:viewhiddencourses', $context)) {
            return false;
        }
        return is_viewing($context);
    }

    /**
     * The call to action for a state, or null where the card draws none (shown, muted, no reason).
     *
     * @param string|null $state The classifier state, null on the anonymous shell.
     * @param \moodle_url|null $url The card's link, null on an anonymous card with nowhere to go.
     * @param bool $mayenter Whether this viewer may enter the course without enrolling ({@see may_enter()}).
     * @param bool $hasroute Whether the card prints a route line beside the viewer's row.
     * @return array|null ['label' => plain, 'url' => string, 'primary' => bool].
     */
    private function cta(?string $state, ?\moodle_url $url, bool $mayenter, bool $hasroute = false): ?array {
        if ($this->anonymous) {
            if (!$this->values->model) {
                return null;
            }
            return [
                'label' => self::text('category_cta_landing'),
                'url' => links::page((int) $this->course->id)->out(false),
                'primary' => true,
            ];
        }
        /* The viewer's own door first: somebody who may walk in is offered the course and not
           the enrolment surface, whatever the badge beside it says (see may_enter()). The two
           states that always could are inside that answer, so this branch is what they used to
           get and nothing about them changed. */
        $key = $mayenter ? 'category_cta_access' : match ($state) {
            classifier::STATE_OPEN => 'category_cta_enrol',
            classifier::STATE_PENDING, classifier::STATE_WAITLISTED => 'category_cta_status',
            /* The route, where the plugin's batch reports one; else the details, which live on the landing page: without
               one there is nothing to open that would not offer an enrolment. */
            classifier::STATE_SCHEDULED => $hasroute ? 'category_cta_enrolnow'
                : ($this->values->model > 0 ? 'category_cta_details' : null),
            classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED => $hasroute ? 'category_cta_enrolnow' : null,
            classifier::STATE_KEY => 'category_cta_key',
            classifier::STATE_CONDITIONAL => 'category_cta_prerequisite',
            default => null,
        };
        $target = match ($key) {
            // The key form is enrol_guest's widget on the standard page, which the landing page does not draw.
            'category_cta_key' => redirector::standard_page((int) $this->course->id),
            // The classifier names the prerequisite only to a viewer actively enrolled in it: a safe link.
            'category_cta_prerequisite' => new \moodle_url('/course/view.php', ['id' => (int) $this->prerequisite->id]),
            default => $url,
        };
        if ($key === null || $target === null) {
            return null;
        }
        return [
            'label' => self::text($key),
            'url' => $target->out(false),
            /* The outline is what "view the application" wears, so it follows the LABEL and not
               the state: a viewer who may walk in is offered the course, and that is never quiet. */
            'primary' => !in_array(
                $key,
                ['category_cta_status', 'category_cta_details', 'category_cta_enrolnow', 'category_cta_prerequisite'],
                true
            ),
        ];
    }

    /**
     * The event's date tile: day over month, in the user's timezone.
     *
     * @param bool $isevent Whether the course chose the event model.
     * @return array|null ['day' => string, 'month' => string], or null off an event with a start date.
     */
    private function datetile(bool $isevent): ?array {
        $start = (int) $this->course->startdate;
        if (!$isevent || !$start) {
            return null;
        }
        return ['day' => userdate($start, '%d'), 'month' => userdate($start, '%b')];
    }

    /**
     * The meta line under the title: the date or the workload, the modality, the city of an in-person offering.
     *
     * @param bool $isevent Whether the course chose the event model.
     * @return string The parts joined with a middle dot, plain spelling; '' when there is nothing.
     */
    private function meta(bool $isevent): string {
        $parts = [];
        if ($isevent) {
            $dateline = dialogue::dateline($this->course, self::text('strftimedatefullshort', 'langconfig'));
            if ($dateline !== null) {
                $parts[] = $dateline;
            }
        } else if ($this->values->workload !== null) {
            $parts[] = dialogue::hours((float) $this->values->workload);
        }
        $modality = landingfields::option_label(landingfields::SHORTNAME_MODALITY, $this->values->modality);
        if ($modality !== null) {
            $parts[] = $modality;
        }
        if ($this->values->modality === landingfields::MODALITY_INPERSON && $this->values->city !== null) {
            $parts[] = $this->values->city;
        }
        return implode(' · ', $parts);
    }
}
