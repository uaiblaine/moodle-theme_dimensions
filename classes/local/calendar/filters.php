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
 * Theme Dimensions - The "More" filters of the calendar range.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

/**
 * The course model, the modality, the city and "only things to do".
 *
 * The first three are facts of a course (the theme's custom fields), so they apply to course-bound items
 * alone: an item of a course other than the site, whether core's (a course, group, activity or component
 * event) or an event course. A site, user or category event has no course to ask and stays whatever they
 * say. "Only things to do" applies to every item: what stays is an action event whose action core's
 * module callback still offers (an assignment not yet submitted), so a site event, an event course and a
 * submitted assignment all go.
 *
 * An empty list or an empty city is no filter. A model or modality value is the field's stored option
 * index (0 = none chosen), never validated against the field's lines: an index nothing holds simply keeps
 * nothing. The city is compared trimmed on both sides ({@see plain_city()}), against the course's
 * plain city.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class filters {
    /** @var int[] The models to keep, empty for every model. */
    public readonly array $models;

    /** @var int[] The modalities to keep, empty for every modality. */
    public readonly array $modalities;

    /** @var string The city to keep, '' for every city. */
    public readonly string $city;

    /** @var bool Whether only actionable action events stay. */
    public readonly bool $actiononly;

    /**
     * Hold the filters.
     *
     * @param int[] $models The models to keep.
     * @param int[] $modalities The modalities to keep.
     * @param string $city The city to keep.
     * @param bool $actiononly Whether only things to do stay.
     */
    public function __construct(array $models = [], array $modalities = [], string $city = '', bool $actiononly = false) {
        $this->models = array_values(array_unique(array_map('intval', $models)));
        $this->modalities = array_values(array_unique(array_map('intval', $modalities)));
        $this->city = self::plain_city($city);
        $this->actiononly = $actiononly;
    }

    /**
     * A city as both sides of the comparison spell it: trimmed.
     *
     * Both sides already arrive in the same plain spelling: the input through the service's PARAM_TEXT, the
     * stored value through values_many()'s format_string() (tags stripped, `&` kept), so trimming is all that
     * is left to do. range_test::test_the_city_filter_cleans_both_sides_alike pins a stored `&` and a stored tag.
     *
     * @param string $city A city, typed or stored.
     * @return string
     */
    private static function plain_city(string $city): string {
        return trim($city);
    }

    /**
     * Whether the course facts are asked at all.
     *
     * @return bool
     */
    public function on_courses(): bool {
        return $this->models || $this->modalities || $this->city !== '';
    }

    /**
     * Whether an item stays.
     *
     * @param array $item A range item.
     * @param \stdClass|null $values Its course's typed values (landingfields::values_many()), null when it has no course.
     * @return bool
     */
    public function keeps(array $item, ?\stdClass $values): bool {
        if ($this->actiononly && ($item['action'] === null || !$item['action']['actionable'])) {
            return false;
        }
        if (!$this->on_courses() || !range::course_bound($item)) {
            return true;
        }
        if ($values === null) {
            return false;
        }
        if ($this->models && !in_array((int) $values->model, $this->models, true)) {
            return false;
        }
        if ($this->modalities && !in_array((int) $values->modality, $this->modalities, true)) {
            return false;
        }
        if ($this->city !== '' && self::plain_city((string) $values->city) !== $this->city) {
            return false;
        }
        return true;
    }
}
