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
 * Theme Dimensions - What a course's "More information" dialogue says beyond its card
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course_category;
use theme_dimensions\local\landingfields;

/**
 * The content of one course's dialogue: the excerpt, the fact tiles and the programme's first lines.
 *
 * The dialogue has two halves. What the card already decided - the cover, the state pill, the kicker,
 * the title and the two buttons, the classifier's run over the page - stays on the card and travels to
 * the dialogue from its markup ({@see \theme_dimensions\output\category_card}). What only the dialogue
 * shows is built here, for one course, and fetched when a reader asks for it
 * ({@see \theme_dimensions\external\get_course_dialogue}, the one caller of {@see content()}): none of
 * it changes the page's decision about the course, so a page of forty-eight cards pays nothing for it
 * until somebody opens one.
 *
 * The reads are one course's: its landing page values ({@see landingfields::values_many()} over
 * {@see SHORTNAMES}, the textareas unfiltered as a listing reads them), core's contacts for it and, with
 * tool_courserating installed and rating somewhere, its summary row ({@see rating()}). The course row is the
 * caller's, read with {@see COURSE_FIELDS}.
 *
 * Every value leaves in the PLAIN spelling for the template's double stashes: the summary and the
 * programme are reduced to text, because the dialogue shows an excerpt and a checklist, not a document.
 * The contacts are named to a logged-in reader only, which is the only reader the service admits: the
 * landing page, the theme's other anonymous surface, names nobody to the internet either.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dialogue {
    /** @var string Component name for get_string calls. */
    private const COMPONENT = 'theme_dimensions';

    /**
     * @var string The columns the dialogue reads from {course}, with the three core's visibility question reads.
     *
     * can_view_course_info() reads id, category and visible; the excerpt reads the summary, the event's
     * date line the start and end dates.
     */
    public const COURSE_FIELDS = 'id, category, visible, summary, summaryformat, startdate, enddate';

    /**
     * @var string[] The landing page fields the dialogue reads: the model (an event's date line), the workload,
     *   the modality, the city and the venue (the fact tiles, with their stored names) and the programme.
     */
    public const SHORTNAMES = [
        landingfields::SHORTNAME_MODEL,
        landingfields::SHORTNAME_MODALITY,
        landingfields::SHORTNAME_WORKLOAD,
        landingfields::SHORTNAME_CITY,
        landingfields::SHORTNAME_VENUE,
        landingfields::SHORTNAME_PROGRAMME,
    ];

    /** @var int How many characters of the summary the dialogue shows. */
    public const EXCERPT_LENGTH = 240;

    /** @var int How many lines of the programme the dialogue lists: the first five. */
    public const LEARN_LINES = 5;

    /** @var array The decorative icon of a fact tile, by the kind of fact it states. */
    private const FACT_ICONS = [
        'date' => 'fa-calendar-day',
        'hours' => 'fa-clock',
        'mode' => 'fa-display',
        'place' => 'fa-location-dot',
        'contact' => 'fa-user',
    ];

    /**
     * The dialogue's content for one course the caller has already admitted.
     *
     * Checks nothing itself. The caller must have decided, in this order, core's
     * {@see \core_course_category::can_view_course_info()} for this viewer and then
     * {@see \local_unlistedcourses\access::filter_courses()} for this course, as
     * {@see \theme_dimensions\external\get_course_dialogue::execute()} and {@see enrichment::details()} do.
     *
     * @param \stdClass $course The course row, with at least {@see COURSE_FIELDS}.
     * @return array excerpt (plain), hasexcerpt, facts (rows of label, value and icon), hasfacts, factslabel,
     *   learn (plain lines), haslearn, learnlabel, rating ({@see rating()}, null where it draws none) and hasrating.
     */
    public static function content(\stdClass $course): array {
        $courseid = (int) $course->id;
        $context = \core\context\course::instance($courseid);
        /* The excerpt before the values: the values' textarea loads lib/filelib.php as well, and the excerpt must
           not lean on it (get_course_dialogue_without_filelib_test runs it in a process that has not loaded it). */
        $excerpt = self::excerpt($course, $context);
        $values = landingfields::values_many([$courseid], false, self::SHORTNAMES)[$courseid];
        $facts = self::facts($course, $values, self::contacts($courseid, $context));
        $learn = self::learn($values);
        $rating = self::rating($courseid);
        return [
            'excerpt' => $excerpt,
            'hasexcerpt' => $excerpt !== '',
            'facts' => $facts,
            'hasfacts' => !empty($facts),
            'factslabel' => get_string('category_modal_facts', self::COMPONENT),
            'learn' => $learn,
            'haslearn' => !empty($learn),
            'learnlabel' => get_string('category_learn', self::COMPONENT),
            'rating' => $rating,
            'hasrating' => $rating !== null,
        ];
    }

    /**
     * The event's date line: the start date, and the end date after a dash on a multi-day event.
     *
     * Shared with the card's meta line ({@see \theme_dimensions\output\category_card}), so the card and its
     * dialogue spell an event's dates alike.
     *
     * @param \stdClass $course The course row: its start and end dates.
     * @param string $format The strftime format of one date, the language's strftimedatefullshort.
     * @return string|null The line, or null without a start date.
     */
    public static function dateline(\stdClass $course, string $format): ?string {
        $start = (int) $course->startdate;
        if (!$start) {
            return null;
        }
        $end = (int) $course->enddate;
        if ($end && usergetmidnight($start) !== usergetmidnight($end)) {
            return userdate($start, $format) . ' – ' . userdate($end, $format);
        }
        return userdate($start, $format);
    }

    /**
     * The workload text, the landing page's spelling; shared with the card's meta line as {@see dateline()} is.
     *
     * @param float $workload The hours.
     * @return string
     */
    public static function hours(float $workload): string {
        return get_string('landing_hours', self::COMPONENT, format_float($workload, 1, true, true));
    }

    /**
     * A course's rating as the card's meta line and the dialogue's block both spell it, as {@see hours()} is shared.
     *
     * The average to one decimal in the reader's language (4,7 in pt_br), the count, and the one sentence a
     * screen reader is told in place of the glyph and the digits.
     *
     * @param float $avg The average, tool_courserating's avgrating.
     * @param int $count How many ratings, its cntall.
     * @return array ['avg' => plain, 'count' => int, 'label' => plain].
     */
    public static function rating_line(float $avg, int $count): array {
        $avgtext = format_float($avg, 1, true);
        $a = (object) ['avg' => $avgtext, 'count' => $count];
        if ($count === 1) {
            $label = get_string('category_rating_label_one', self::COMPONENT, $a);
        } else {
            $label = get_string('category_rating_label', self::COMPONENT, $a);
        }
        return ['avg' => $avgtext, 'count' => $count, 'label' => $label];
    }

    /**
     * The dialogue's rating block for one course, or null where it draws none.
     *
     * None without tool_courserating, on a site where it rates no course, and for a course the plugin does not rate
     * or nobody rated: {@see cards::RATED}, the card's own predicate, over the one summary row. The caller has already
     * admitted the course for this viewer, so the aggregate follows the card, as it does on the page.
     *
     * The reviews are never shown here. When the course holds some and the plugin lets this viewer read them
     * ({@see \tool_courserating\permission::can_view_reviews()}: its allowreviews setting, hidden reviews to its
     * reports capability only), the block offers the plugin's own popup through its own trigger
     * (`hasreviewslink`), which its document listener opens.
     *
     * @param int $courseid The course, already admitted.
     * @return array|null The rating_line() keys and courseid, heading, counttext, stars (five, each a Font Awesome
     *   class list), distribution (five rows from five stars down to one: stars, count, label), reviews,
     *   hasreviewslink and reviewslabel; null where the dialogue draws no block.
     */
    public static function rating(int $courseid): ?array {
        global $DB;

        if (!cards::ratings_enabled()) {
            return null;
        }
        // Rides the summary's unique index on courseid.
        $record = $DB->get_record_select(
            'tool_courserating_summary',
            'courseid = :courseid AND ' . cards::RATED,
            ['courseid' => $courseid] + cards::rated_params(),
            'courseid, avgrating, cntall, cntreviews, cnt01, cnt02, cnt03, cnt04, cnt05'
        );
        if (!$record) {
            return null;
        }
        $avg = (float) $record->avgrating;
        $count = (int) $record->cntall;
        $line = self::rating_line($avg, $count);

        $counts = [
            5 => (int) $record->cnt05,
            4 => (int) $record->cnt04,
            3 => (int) $record->cnt03,
            2 => (int) $record->cnt02,
            1 => (int) $record->cnt01,
        ];
        $distribution = [];
        foreach ($counts as $stars => $rowcount) {
            $a = (object) ['stars' => $stars, 'count' => $rowcount];
            if ($stars === 1) {
                $rowlabel = get_string('category_rating_row_one', self::COMPONENT, $a);
            } else {
                $rowlabel = get_string('category_rating_row', self::COMPONENT, $a);
            }
            $distribution[] = ['stars' => $stars, 'count' => $rowcount, 'label' => $rowlabel];
        }

        $reviews = (int) $record->cntreviews;
        $hasreviewslink = $reviews > 0 && \tool_courserating\permission::can_view_reviews($courseid);
        $reviewslabel = '';
        if ($hasreviewslink) {
            if ($reviews === 1) {
                $reviewslabel = get_string('category_rating_reviews_one', self::COMPONENT);
            } else {
                $reviewslabel = get_string('category_rating_reviews', self::COMPONENT, $reviews);
            }
        }
        if ($count === 1) {
            $counttext = get_string('category_rating_count_one', self::COMPONENT);
        } else {
            $counttext = get_string('category_rating_count', self::COMPONENT, $count);
        }

        return $line + [
            'courseid' => $courseid,
            'heading' => get_string('category_rating_heading', self::COMPONENT),
            'counttext' => $counttext,
            'stars' => self::stars($avg),
            'distribution' => $distribution,
            'reviews' => $reviews,
            'hasreviewslink' => $hasreviewslink,
            'reviewslabel' => $reviewslabel,
        ];
    }

    /**
     * The five stars of an average, full, half or empty, as tool_courserating draws them.
     *
     * The plugin's own thresholds (\tool_courserating\external\stars_exporter::star()): of each star's share of the
     * average, under a quarter is empty, from three quarters it is full, and a half between; the average is first
     * rounded to a twentieth as the plugin rounds it.
     *
     * @param float $avg The average.
     * @return array Five rows, ['icon' => the Font Awesome classes of that star].
     */
    public static function stars(float $avg): array {
        $avg = round($avg * 20) / 20;
        $stars = [];
        for ($i = 0; $i < 5; $i++) {
            $share = max(0.0, min(1.0, $avg - $i));
            if ($share >= 0.75) {
                $icon = 'fa-solid fa-star';
            } else if ($share >= 0.25) {
                $icon = 'fa-solid fa-star-half-stroke';
            } else {
                $icon = 'fa-regular fa-star';
            }
            $stars[] = ['icon' => $icon];
        }
        return $stars;
    }

    /**
     * The course contacts of one course, through core's bulk preload over that course alone.
     *
     * The same spelling the card's facts used when every card carried them: the full name, and the role
     * name as core_course_list_element::get_course_contacts() spells it. A contact holding two roles is named
     * once, under the first.
     *
     * @param int $courseid The course.
     * @param \core\context\course $context Its context, where the role names are formatted.
     * @return array userid => ['fullname' => plain, 'rolename' => plain].
     */
    private static function contacts(int $courseid, \core\context\course $context): array {
        $courses = [$courseid => (object) ['id' => $courseid, 'managers' => []]];
        core_course_category::preload_course_contacts($courses);
        $contacts = [];
        foreach ($courses[$courseid]->managers ?? [] as $manager) {
            $userid = (int) $manager->id;
            if (isset($contacts[$userid])) {
                continue;
            }
            $role = (object) [
                'id' => $manager->roleid,
                'name' => $manager->rolename,
                'shortname' => $manager->roleshortname,
                'coursealias' => $manager->rolecoursealias,
            ];
            $contacts[$userid] = [
                'fullname' => fullname($manager),
                'rolename' => role_get_name($role, $context, ROLENAME_ALIAS),
            ];
        }
        return $contacts;
    }

    /**
     * The fact tiles, labelled with the stored field names as the landing page's card does.
     *
     * @param \stdClass $course The course row.
     * @param \stdClass $values The course's landing page values over {@see SHORTNAMES}.
     * @param array $contacts userid => ['fullname' => plain, 'rolename' => plain].
     * @return array Rows of ['label' => plain, 'value' => plain, 'icon' => the tile's decorative icon].
     */
    private static function facts(\stdClass $course, \stdClass $values, array $contacts): array {
        $names = $values->names;
        $facts = [];
        if ($values->model === landingfields::MODEL_EVENT) {
            $dateline = self::dateline($course, get_string('strftimedatefullshort', 'langconfig'));
            if ($dateline !== null) {
                $facts[] = ['label' => get_string('date'), 'value' => $dateline, 'icon' => self::FACT_ICONS['date']];
            }
        } else if ($values->workload !== null && isset($names[landingfields::SHORTNAME_WORKLOAD])) {
            $facts[] = [
                'label' => $names[landingfields::SHORTNAME_WORKLOAD],
                'value' => self::hours((float) $values->workload),
                'icon' => self::FACT_ICONS['hours'],
            ];
        }
        $modality = landingfields::option_label(landingfields::SHORTNAME_MODALITY, $values->modality);
        if ($modality !== null && isset($names[landingfields::SHORTNAME_MODALITY])) {
            $facts[] = [
                'label' => $names[landingfields::SHORTNAME_MODALITY],
                'value' => $modality,
                'icon' => self::FACT_ICONS['mode'],
            ];
        }
        if ($values->modality === landingfields::MODALITY_INPERSON) {
            $place = array_filter([$values->venue, $values->city]);
            if ($place && isset($names[landingfields::SHORTNAME_CITY])) {
                $facts[] = [
                    'label' => $names[landingfields::SHORTNAME_CITY],
                    'value' => implode(' · ', $place),
                    'icon' => self::FACT_ICONS['place'],
                ];
            }
        }
        if ($contacts) {
            $people = [];
            foreach ($contacts as $contact) {
                $people[] = $contact['fullname'] . ' (' . $contact['rolename'] . ')';
            }
            $facts[] = [
                'label' => get_string('category_contact', self::COMPONENT),
                'value' => implode(', ', $people),
                'icon' => self::FACT_ICONS['contact'],
            ];
        }
        return $facts;
    }

    /**
     * The summary reduced to one paragraph of text.
     *
     * Formatted with the text filters OFF, like the landing page textareas a listing reads
     * ({@see landingfields::values_many()} says why). The pluginfile rewrite is kept so nothing in the text
     * refers to a draft area.
     *
     * @param \stdClass $course The course row: its summary and its format.
     * @param \core\context\course $context The course context.
     * @return string Plain text, shortened; '' when the summary is empty.
     */
    private static function excerpt(\stdClass $course, \core\context\course $context): string {
        global $CFG;

        $summary = (string) ($course->summary ?? '');
        if (trim($summary) === '') {
            return '';
        }
        // A web-service request bootstraps no lib/filelib.php; the PHPUnit bootstrap does, which hides it.
        require_once($CFG->libdir . '/filelib.php');
        $html = file_rewrite_pluginfile_urls($summary, 'pluginfile.php', $context->id, 'course', 'summary', null);
        $html = format_text($html, (int) $course->summaryformat, [
            'context' => $context,
            'filter' => false,
            'overflowdiv' => false,
            'para' => false,
        ]);
        $text = implode(' ', self::lines($html));
        return $text === '' ? '' : shorten_text($text, self::EXCERPT_LENGTH);
    }

    /**
     * The first lines of the programme, one checklist item each.
     *
     * @param \stdClass $values The course's landing page values.
     * @return string[] Up to {@see LEARN_LINES} plain lines.
     */
    private static function learn(\stdClass $values): array {
        $programme = $values->programme;
        if ($programme === null || trim((string) $programme) === '') {
            return [];
        }
        return array_slice(self::lines((string) $programme), 0, self::LEARN_LINES);
    }

    /**
     * Reduce HTML to its non-empty lines of text, one per block element.
     *
     * A block boundary becomes a line break, every other tag disappears, entities are decoded and whitespace
     * collapsed - what an excerpt and a checklist need, at a fraction of html_to_text()'s cost.
     *
     * @param string $html The HTML.
     * @return string[] The lines, trimmed, empty ones dropped, plain spelling.
     */
    private static function lines(string $html): array {
        $html = preg_replace('~<br\s*/?>|</?(?:p|div|li|ul|ol|h[1-6]|tr|blockquote|pre)\b[^>]*>~i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    }
}
