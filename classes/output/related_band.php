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
 * Theme Dimensions - The related courses band's cards, for the landing page and the shortcode
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use theme_dimensions\local\category\cards;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;
use theme_dimensions\local\trail\display;

/**
 * The related courses band as templates/related_band.mustache draws it, from {@see \theme_dimensions\local\related\finder}'s
 * answer: the landing page's band ({@see landing_page}) and the `[relatedcourses]` shortcode
 * ({@see \theme_dimensions\local\related\shortcode}) draw the same cards.
 *
 * Each card leads to the course's landing page and carries its cover (the course image, or the renderer's generated
 * cover as a data URI, in one batch), its category and its workload. Names are handed over in the PLAIN spelling, for
 * the template's double stashes.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class related_band implements \renderable, \templatable {
    /** @var string The component of the strings. */
    private const COMPONENT = 'theme_dimensions';

    /** @var array The finder's answer. */
    private array $found;

    /** @var string The band's data-region. */
    private string $region;

    /**
     * Keep the finder's answer and the region the band is found by.
     *
     * @param array $found {@see \theme_dimensions\local\related\finder::find()}'s answer, not empty.
     * @param string $region The band's data-region: the landing page's or the shortcode's.
     */
    public function __construct(array $found, string $region) {
        $this->found = $found;
        $this->region = $region;
    }

    /**
     * The band's context: its region, its cards and how many.
     *
     * @param \renderer_base $output The renderer.
     * @return array ['region' => string, 'cards' => array, 'count' => int]
     */
    public function export_for_template(\renderer_base $output): array {
        $rows = [];
        foreach ($this->found as $entry) {
            $rows[(int) $entry['row']->id] = $entry['row'];
        }
        $images = cards::images($rows, display::visitor(), true);
        $hours = landingfields::values_many(array_keys($rows), false, [landingfields::SHORTNAME_WORKLOAD]);
        $categories = \core_course_category::get_many(
            array_values(array_unique(array_map(static fn(\stdClass $row): int => (int) $row->category, $rows)))
        );
        $cards = [];
        foreach ($rows as $courseid => $row) {
            // An <img> source, as the showcase's card prints it: a url, or the renderer's data URI.
            $image = $images[$courseid] ?? null;
            $category = $categories[(int) $row->category] ?? null;
            $workload = isset($hours[$courseid]) ? $hours[$courseid]->workload : null;
            $cards[] = [
                'courseid' => $courseid,
                'name' => format_string($row->fullname, true, [
                    'context' => \core\context\course::instance($courseid),
                    'escape' => false,
                ]),
                'url' => links::page($courseid)->out(false),
                'imageurl' => $image instanceof \moodle_url ? $image->out(false) : $image,
                'category' => $category === null ? null : format_string($category->name, true, [
                    'context' => \core\context\coursecat::instance($category->id),
                    'escape' => false,
                ]),
                'hours' => $workload === null ? null
                    : get_string('landing_hours', self::COMPONENT, format_float($workload, 1, true, true)),
            ];
        }
        return ['region' => $this->region, 'cards' => $cards, 'count' => count($cards)];
    }
}
