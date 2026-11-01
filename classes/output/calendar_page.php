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
 * Theme Dimensions - The calendar page: the server-drawn shell and the client's first answer.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\page;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\state;
use theme_dimensions\local\calendar\window;
use theme_dimensions\local\landingfields;

/**
 * The calendar page as theme_dimensions/calendar_page draws it.
 *
 * Two layers in one template. The server's layer works without JavaScript: the toolbar's previous, next and today
 * are links, the key chips, the category and the "More" filters are one GET form, and the month's items are a
 * plain agenda grouped by day under a notice linking core's calendar. The client's layer is the mount node of
 * `calendar/Calendar`, whose props embed the range's answer for the month grid ({@see range::answer()}, computed
 * once by {@see page::handle()}), so the first paint asks for nothing; the component hides the server's layer
 * when it has drawn its own.
 *
 * Every text in the props is in the plain spelling (the answer's own, format_string() with escape off), for the
 * client's textContent; the template's double stashes escape the server's layer once.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class calendar_page implements renderable, templatable {
    /** @var string The component the mount node names. */
    public const COMPONENT = '@moodle/lms/theme_dimensions/calendar/Calendar';

    /** @var string[] The strings the client reads, handed to it with the page so its first paint asks for none. */
    public const STRINGS = [
        'calendar_actiononly',
        'calendar_barlabel',
        'calendar_category',
        'calendar_categoryall',
        'calendar_categorycourses',
        'calendar_categorynone',
        'calendar_categorysearch',
        'calendar_categorysearchplaceholder',
        'calendar_categorytree',
        'calendar_city',
        'calendar_cityany',
        'calendar_classic',
        'calendar_clearfilters',
        'calendar_colourby',
        'calendar_colourbycategory',
        'calendar_colourbykey',
        'calendar_dayitems_many',
        'calendar_dayitems_none',
        'calendar_dayitems_one',
        'calendar_daylabel',
        'calendar_daytoday',
        'calendar_filtercount',
        'calendar_key_activity',
        'calendar_key_category',
        'calendar_key_course',
        'calendar_key_group',
        'calendar_key_open',
        'calendar_key_other',
        'calendar_key_site',
        'calendar_key_user',
        'calendar_keys',
        'calendar_modality',
        'calendar_model',
        'calendar_moreaction',
        'calendar_morefilters',
        'calendar_morelabel',
        'calendar_nextmonth',
        'calendar_overdue',
        'calendar_prevmonth',
        'calendar_recursive',
        'calendar_span',
        'calendar_title',
        'calendar_today',
        'calendar_truncated',
        'calendar_until',
        'calendar_view',
        'calendar_viewmonth',
        'calendar_withsub',
    ];

    /** @var string The month, YYYY-MM. */
    private string $month;

    /** @var string Today in the viewer's timezone, YYYY-MM-DD. */
    private string $today;

    /** @var int The weekday a week begins on, 0 for Sunday. */
    private int $firstday;

    /** @var string The viewer's timezone, as core_date names it. */
    private string $timezone;

    /** @var window The month grid's days. */
    private window $window;

    /** @var array The range's answer for the window. */
    private array $answer;

    /** @var state What the page shows. */
    private state $state;

    /** @var array The categories the picker offers. */
    private array $tree;

    /**
     * Hold what the page draws; {@see page::handle()} computes every part.
     *
     * @param string $month The month, YYYY-MM.
     * @param string $today Today in the viewer's timezone, YYYY-MM-DD.
     * @param int $firstday The weekday a week begins on, 0 for Sunday.
     * @param string $timezone The viewer's timezone, as core_date names it.
     * @param window $window The month grid's days.
     * @param array $answer The range's answer for the window.
     * @param state $state What the page shows.
     * @param array $tree The categories the picker offers ({@see \theme_dimensions\local\calendar\tree}).
     */
    public function __construct(
        string $month,
        string $today,
        int $firstday,
        string $timezone,
        window $window,
        array $answer,
        state $state,
        array $tree
    ) {
        $this->month = $month;
        $this->today = $today;
        $this->firstday = $firstday;
        $this->timezone = $timezone;
        $this->window = $window;
        $this->answer = $answer;
        $this->state = $state;
        $this->tree = $tree;
    }

    /**
     * The name of a key, as the chips print it.
     *
     * @param string $key One of keys::ALL.
     * @return string
     */
    public static function key_label(string $key): string {
        return match ($key) {
            keys::SITE => get_string('calendar_key_site', 'theme_dimensions'),
            keys::CATEGORY => get_string('calendar_key_category', 'theme_dimensions'),
            keys::COURSE => get_string('calendar_key_course', 'theme_dimensions'),
            keys::GROUP => get_string('calendar_key_group', 'theme_dimensions'),
            keys::USER => get_string('calendar_key_user', 'theme_dimensions'),
            keys::ACTIVITY => get_string('calendar_key_activity', 'theme_dimensions'),
            keys::OPEN => get_string('calendar_key_open', 'theme_dimensions'),
            default => get_string('calendar_key_other', 'theme_dimensions'),
        };
    }

    /**
     * The month after or before this one.
     *
     * @param string $month YYYY-MM.
     * @param int $offset Whole months.
     * @return string YYYY-MM.
     */
    public static function month_at(string $month, int $offset): string {
        $first = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', new \DateTimeZone('UTC'));
        return $first->modify(($offset >= 0 ? '+' : '') . $offset . ' months')->format('Y-m');
    }

    /**
     * Where an item leads: its own page, else its course's page, else the site's home.
     *
     * The C2 seam of opening an item; C3 opens the popover instead.
     *
     * @param array $item A range item.
     * @param array $courses The answer's courses, keyed by id.
     * @return string The address.
     */
    public static function item_url(array $item, array $courses): string {
        if ($item['url'] !== '') {
            return $item['url'];
        }
        if (isset($courses[$item['courseid']])) {
            return $courses[$item['courseid']]['url'];
        }
        return (new \core\url('/'))->out(false);
    }

    /**
     * The option lines of one of the course select fields, as the "More" panel offers them.
     *
     * @param string $shortname The field.
     * @return array[] ['value' => int, 'label' => string] each.
     */
    private static function options(string $shortname): array {
        $options = [];
        foreach (landingfields::option_lines($shortname) ?? [] as $index => $line) {
            $options[] = ['value' => $index + 1, 'label' => format_string($line, true, ['escape' => false])];
        }
        return $options;
    }

    /**
     * The cities the "More" panel offers: those of the courses the answer names, and the one kept.
     *
     * @return string[] Sorted.
     */
    private function cities(): array {
        $cities = [];
        foreach ($this->answer['courses'] as $course) {
            if ($course['city'] !== '') {
                $cities[$course['city']] = true;
            }
        }
        if ($this->state->city !== '') {
            $cities[$this->state->city] = true;
        }
        $cities = array_keys($cities);
        \core_collator::asort($cities);
        return array_values($cities);
    }

    /**
     * The client's props: everything it draws its first paint from.
     *
     * @return array
     */
    public function props(): array {
        return [
            'month' => $this->month,
            'today' => $this->today,
            'firstday' => $this->firstday,
            'timezone' => $this->timezone,
            'answer' => $this->answer,
            'state' => $this->state->to_array(),
            'tree' => $this->tree,
            'options' => [
                'model' => self::options(landingfields::SHORTNAME_MODEL),
                'modality' => self::options(landingfields::SHORTNAME_MODALITY),
                'city' => $this->cities(),
            ],
            'urls' => [
                'page' => page::url()->out(false),
                'calendar' => (new \core\url('/calendar/view.php'))->out(false),
                'home' => (new \core\url('/'))->out(false),
            ],
        ];
    }

    /**
     * The month's items as an agenda: each item once, under the first day of the month it touches.
     *
     * @return array[] One entry per day that has items: ['label', 'items' => [['name', 'url', 'when', 'key', 'keylabel']]].
     */
    private function agenda(): array {
        $timezone = \core_date::get_user_timezone_object();
        $courses = [];
        foreach ($this->answer['courses'] as $course) {
            $courses[$course['id']] = $course;
        }
        $days = [];
        foreach ($this->answer['events'] as $item) {
            $start = $item['timestart'];
            $last = $start + max(0, $item['timeduration'] - 1);
            $first = max($this->window->day_index($start), 0);
            $end = $this->window->day_index($last);
            // The month's own days only: the neighbours' days are the grid's padding.
            $index = null;
            for ($i = $first; $i <= $end; $i++) {
                if (str_starts_with($this->window->days[$i], $this->month)) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null || $last < $this->window->tstart || $start > $this->window->tend) {
                continue;
            }
            $multiday = $first !== $end || $item['source'] === 'course';
            if ($multiday) {
                $when = get_string('calendar_span', 'theme_dimensions', (object) [
                    'from' => userdate($start, get_string('strftimedateshort', 'langconfig')),
                    'to' => userdate($last, get_string('strftimedateshort', 'langconfig')),
                ]);
            } else {
                $when = userdate($start, get_string('strftimetime', 'langconfig'));
            }
            $days[$index][] = [
                'name' => $item['name'],
                'url' => self::item_url($item, $courses),
                'when' => $when,
                'key' => $item['key'],
                'keylabel' => self::key_label($item['key']),
            ];
        }
        ksort($days);
        $agenda = [];
        foreach ($days as $index => $items) {
            $midnight = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->window->days[$index], $timezone);
            $agenda[] = [
                'label' => userdate($midnight->getTimestamp(), get_string('strftimedaydate', 'langconfig')),
                'items' => $items,
            ];
        }
        return $agenda;
    }

    /**
     * The template context.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $query = $this->state->query();
        $counts = array_fill_keys(keys::ALL, 0);
        foreach ($this->answer['events'] as $item) {
            $counts[$item['key']]++;
        }
        $on = $this->state->on();
        $keyrows = [];
        foreach (keys::ALL as $key) {
            $keyrows[] = [
                'key' => $key,
                'label' => self::key_label($key),
                'checked' => in_array($key, $on, true),
                'count' => $counts[$key],
            ];
        }

        $categories = [];
        foreach ($this->tree as $node) {
            $categories[] = [
                'id' => $node['id'],
                // A select cannot nest: the depth is drawn with non-breaking spaces, as core's own category menus do.
                'label' => str_repeat("\u{00A0}\u{00A0}", $node['depth']) . $node['name'],
                'selected' => $node['id'] === $this->state->categoryid,
            ];
        }

        $fieldoptions = static function (array $options, array $kept): array {
            return array_map(static fn(array $option): array => $option + [
                'checked' => in_array($option['value'], $kept, true),
            ], $options);
        };
        $props = $this->props();
        $filtercount = count($this->state->models) + count($this->state->modalities)
            + ($this->state->city !== '' ? 1 : 0) + ($this->state->actiononly ? 1 : 0);
        $agenda = $this->agenda();

        return [
            'month' => $this->month,
            'caption' => userdate(
                \DateTimeImmutable::createFromFormat('!Y-m-d', $this->month . '-01', \core_date::get_user_timezone_object())
                    ->getTimestamp(),
                get_string('strftimemonthyear', 'langconfig')
            ),
            'action' => page::url()->out(false),
            'todayurl' => page::url([state::PARAM_MONTH => substr($this->today, 0, 7)] + $query)->out(false),
            'prevurl' => page::url([state::PARAM_MONTH => self::month_at($this->month, -1)] + $query)->out(false),
            'nexturl' => page::url([state::PARAM_MONTH => self::month_at($this->month, 1)] + $query)->out(false),
            'classicurl' => page::classic($this->month)->out(false),
            'clearurl' => page::url([state::PARAM_MONTH => $this->month] + (new state())->query())->out(false),
            'keys' => $keyrows,
            'categories' => $categories,
            'hascategories' => !empty($categories),
            'nocategory' => $this->state->categoryid === 0,
            'recursive' => $this->state->recursive,
            'models' => $fieldoptions($props['options']['model'], $this->state->models),
            'modalities' => $fieldoptions($props['options']['modality'], $this->state->modalities),
            'cities' => array_map(fn(string $city): array => [
                'value' => $city,
                'selected' => $city === $this->state->city,
            ], $props['options']['city']),
            'actiononly' => $this->state->actiononly,
            'colourkey' => $this->state->colour === state::COLOUR_KEY,
            'colourcategory' => $this->state->colour === state::COLOUR_CATEGORY,
            'filtercount' => $filtercount,
            'truncated' => !empty($this->answer['truncated']),
            'agenda' => $agenda,
            'agendaempty' => empty($agenda),
            'component' => self::COMPONENT,
            'props' => json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
