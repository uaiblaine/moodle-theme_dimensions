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
use theme_dimensions\local\card\boost_union;
use theme_dimensions\local\category\branding;
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
 * Everything a card says is decided here from data the listing already read in bulk: the population
 * row (state, favourite, visibility), the course row, the landing page values and the image. Nothing
 * in this class queries the database - a page of cards must cost no statement per card - and the one
 * context it asks for was preloaded by the population. With filterall on, format_string() still loads
 * each course context's filters; the render budget tests assert that precondition. Every text leaves
 * in the plain spelling for the template's double stashes; a card holds no final HTML.
 *
 * The state pill is one icon and one sentence per classifier state. Its class is the design system's,
 * never Bootstrap's .badge or a bg-* utility (Bootstrap 5's .badge forces white text and its own
 * padding); scss/category.scss pairs each class with a background and a text colour token in both
 * colour modes. The icon is aria-hidden: the sentence carries the state.
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
 * The badge is about the course; the link is about the viewer. A viewer who may enter the course
 * without enrolling (core's {@see can_access_course()} through {@see is_viewing()}) is sent to the
 * course by every card, whatever the badge says: the table's row decides the word, {@see may_enter()}
 * the destination, so such a viewer is never sent to the landing page.
 *
 * The route line: a card about a row of the viewer's that is not active (it starts later, it is an
 * application, it is suspended, it ended) also says when a method would take them now, and its call to
 * action then goes there. The routes are local_unlistedcourses' batch's, read once for the page
 * ({@see \theme_dimensions\local\category\cards::next_actions()}); the card never decides one itself. A
 * pending or waitlisted card keeps "View status" as its button, whose page offers the route as well.
 * The opening line: a card with nothing on offer, suspended or ended, refused only because an enrolment
 * window has not opened yet, says on which date it opens - the same batch's `opens` ({@see opens_label()}).
 *
 * A held event (the event model, an end date in the past) prints "Held" whatever the classifier said,
 * muted and with nothing to press, unless the viewer may enter the course, in which case it keeps its
 * badge and stays reachable. The anonymous shell prints "Public access" (a property of the course, not
 * of the viewer) and its one call to action is the course's landing page; a public course without a
 * landing page model has nothing to send a visitor to and gets none.
 *
 * The star carries three labels: starlabel is its current state's, what a reader without JavaScript
 * gets; starlabeladd and starlabelremove let the client relabel the button the instant it is pressed.
 *
 * "More information" is a link to the card's own page ({@see url()}), and with JavaScript it opens a
 * dialogue. What the page decided stays on the card (the kicker and the two buttons under `modal`,
 * {@see modal()}, written onto the link); what only the dialogue shows is fetched when it opens
 * ({@see \theme_dimensions\local\category\dialogue}).
 *
 * A course tool_courserating rates, and somebody rated, opens its meta line with the rating on both
 * shells (the plugin shows the aggregate to whoever may see the course listed): read once for the page
 * ({@see \theme_dimensions\local\category\cards::ratings()}) and spelt as the dialogue spells it
 * ({@see \theme_dimensions\local\category\dialogue::rating_line()}).
 *
 * For the filter line's preview (js/esm/src/filterline/preview.ts) the card also carries the state chip
 * the course counts under (`chip`, {@see classifier::chip()}, what the scope's filter keeps it by), the
 * value of each filter field ({@see field_values()}) and the start day in the site's timezone
 * (`startday`, the day {@see criteria::day_start()} compares a period's bounds with); the favourite is
 * the star's own aria-pressed. A visitor's card states less: no chip and no star, only the fields a
 * visitor may see, and a start day only on an event's date tile. None of it costs a statement.
 *
 * A card Boost Union's course overview settings draw without its cover, or a row without its thumbnail,
 * keeps an edge in its course category's colour (`band`, {@see \theme_dimensions\local\category\cards::bands()}),
 * written on the article as --dim-card-band. It leaves only through {@see branding::hex()}, so the style
 * attribute holds a #rrggbb or nothing. Such a card also draws a row at the top of its body (`hastop`)
 * holding the kind, the crests and the star in the cover's place, when it has any of the three.
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
     * The icons are Font Awesome 6 names; card_markup_test checks each against the icon font core ships.
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
     * The landing page's grid holds four; a card's corner holds three. At 56px each, three crests,
     * their gaps and the date tile already run most of the width of a card on a phone, and a fourth
     * would reach the tile on the other side.
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
        classifier::STATE_ENROLLED => ['category_modal_continue', 'fa-arrow-right'],
        classifier::STATE_FREE => ['category_modal_free', 'fa-arrow-right'],
        classifier::STATE_OPEN => ['category_modal_enrol', 'fa-arrow-right'],
    ];

    /**
     * @var array The strings every card of the request spells alike: language => component => key => text.
     *
     * A listing builds a card per course, so a label without parameters is read once per request; the
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
     * @param \moodle_url|string|null $image The card image: the course's own, the renderer's answer for a course with
     *   none (a data URI or Boost Union's fallback image, {@see \theme_dimensions\local\category\cards::images()}), or
     *   null for the flat fallback.
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
     * @param string|null $band The course category's colour ({@see \theme_dimensions\local\category\cards::bands()}), the
     *   edge of a card or row drawn without its image; null where the listing read none.
     */
    public function __construct(
        /** @var \stdClass The course row. */
        private readonly \stdClass $course,
        /** @var \stdClass The population row. */
        private readonly \stdClass $row,
        /** @var \stdClass The landing page values. */
        private readonly \stdClass $values,
        /** @var \moodle_url|string|null The card image. */
        private readonly \moodle_url|string|null $image,
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
        /** @var string|null The course category's colour, for the edge of a card drawn without its image. */
        private readonly ?string $band = null,
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
        $showcover = boost_union::show_images(boost_union::VIEW_CARD);
        $showthumb = boost_union::show_images(boost_union::VIEW_LIST);
        $showprogress = boost_union::show_progress();
        $band = $this->band === null ? null : branding::hex($this->band);

        return (object) [
            'id' => $courseid,
            'fullname' => $fullname,
            'url' => $url?->out(false),
            'hasurl' => $url !== null,
            'overline' => $this->overline,
            'hasoverline' => $this->overline !== null && $this->overline !== '',
            'imageurl' => $this->image instanceof \moodle_url ? $this->image->out(false) : $this->image,
            'hasimage' => $this->image !== null,
            /* Boost Union's course overview settings, where it applies: the cover of the card
               view, the thumbnail of the list view, and the progress row. One markup serves both views, so the media
               band is drawn while either view shows it, and the article says which view leaves it out. */
            'showcover' => $showcover,
            'showthumb' => $showthumb,
            'showmedia' => $showcover || $showthumb,
            'showprogress' => $showprogress,
            // The progress row: on an enrolled card alone, the only card the logged-in shell asks the details of.
            'hasprogress' => $enrolled && $showprogress,
            // The category's colour, a #rrggbb after branding::hex() or nothing: the edge of a card or row without its image.
            'band' => $band ?? '',
            'hasband' => $band !== null,
            // The row at the top of the body that holds the kind, the crests and the star where the cover would.
            'hastop' => !$showcover && ($kindkey !== null || $this->showstar || !empty($badges)),
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
            /* "More information" opens the course dialogue, which is armed for a signed-in viewer only: a visitor's card
               draws its one button, "Learn more", and nothing beside it. */
            'hasdetails' => !$this->anonymous && $url !== null,
            'detailslabel' => self::text('category_details'),
            /* What an enrolled card becomes once the details service says the viewer completed the course:
               the seal's words and the main action's. The client applies them with dim-card--done; no other card
               is asked about its completion, so no other card carries them. */
            'done' => $enrolled ? [
                'label' => self::text('category_state_completed'),
                'review' => self::text('category_cta_review'),
            ] : null,
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
     * The secondary button is the card's own link ({@see url()} with {@see may_enter()}), named after the course
     * or the event; there is none where the card links nowhere. The primary button is {@see modal_primary()}.
     * When the primary would open the page the secondary opens (the enrolled reader's "Continue course" is the
     * course page), only the primary is drawn; a disabled primary goes nowhere, so the secondary stays beside it.
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
     * whose way in opens later (the card's opening line, {@see opens_label()}) gets a disabled button carrying
     * that date, which the dialogue prints nowhere else. Enrolled, free and open are relabelled
     * ({@see MODAL_PRIMARY}): the first two go where the card goes (the course, since {@see may_enter()} lets
     * both in), and "Enrol" goes to the enrolment surface ({@see url()} asked with false), so staff who may
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
     * The crests over the card image ({@see crests()}).
     *
     * @return array Entries of ['url' => string, 'alt' => string], in grid order, plain spelling.
     */
    private function badges(): array {
        return self::crests($this->course, $this->values);
    }

    /**
     * A course's crests: its own landing page badges, up to {@see MAX_BADGES}, named through the theme's file route.
     *
     * The course's badges, never the category's: those belong to the category's hero and directory card,
     * and name something else (these are the institutions running the course). A course with no landing
     * page model shows none, even where badge files survive a cleared model, because the route serves a
     * badge only while the course carries a model ({@see publicfiles::resolve()}).
     *
     * No statement: {@see \theme_dimensions\local\landingfields::values_many()} already read the badge
     * files. Always the theme's own file route, whoever is looking: the picture fields are visible to
     * nobody, so the picture type's own URL refuses them to everybody, while the route's badge branch
     * serves a visitor a public course's badge and a logged-in viewer any course they may see. Shared
     * with theme_dimensions_course_badges(), the callback other plugins' cards ask.
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
     * Whether this viewer may walk into the course without enrolling.
     *
     * Core's {@see can_access_course()} with its enrolment half already answered by the classifier, so
     * what is left is the branch that lets staff in without an enrolment: {@see is_viewing()},
     * `moodle/course:view` at the course context. The context was preloaded by the population and the
     * capability is answered from the access data the request holds, so it costs no statement per card
     * (pinned in the budget test).
     *
     * Core's order is kept: `can_access_course()` refuses a hidden course to anybody without
     * `moodle/course:viewhiddencourses` before it asks `is_viewing()`, so `moodle/course:view` alone
     * does not open a hidden course, and no viewer is handed a link that then refuses them. The
     * listing's own gate agrees: it admits a hidden course only to a holder of
     * `moodle/course:viewhiddencourses`, and an enrolment does not rescue it
     * ({@see \theme_dimensions\local\category\population::gate()}).
     *
     * Enrolled and free (guest access with no password) always let the viewer in. Guest access behind
     * a password reads key, whose way in is the key form on the standard enrolment page.
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
           the enrolment surface, whatever the badge beside it says (see may_enter()). */
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
            /* The outline style follows the label, not the state: a viewer who may walk in is
               offered the course, which is always a primary button. */
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
