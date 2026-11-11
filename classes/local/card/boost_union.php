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
 * Theme Dimensions - The reader of Boost Union's course overview settings for the course cards
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\card;

/**
 * The one reader of the three Boost Union settings that decide a course card.
 *
 * The settings apply while the request's theme is Boost Union or names it among its parents, and Boost Union is
 * installed; theme_dimensions always is such a child, and the check stays because block_compass and local_dimensions
 * carry a copy of this class (their local\card_settings) that runs under any theme; keep the three in step. Outside
 * Boost Union every answer is the card's own rule: the progress row drawn,
 * the images drawn, and the image of a course with none left to the theme's renderer.
 *
 * Every value is compared with literals equal to Boost Union's constants (THEME_BOOST_UNION_SETTING_* in its lib.php),
 * which exist only once that file is loaded. A value that is missing reads as Boost Union's default: its settings page
 * writes one on install, and an upgrade that has not run yet must not hide a card's progress or its cover.
 * Nothing here reads the database: get_config() is served from the config cache.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class boost_union {
    /** @var string Boost Union's component, the owner of the three settings. */
    public const COMPONENT = 'theme_boost_union';

    /** @var string Boost Union's theme name, as the request's theme or one of its parents spells it. */
    public const THEME = 'boost_union';

    /** @var string Whether the course progress is shown: {@see YES} or 'no'. */
    public const SETTING_PROGRESS = 'courseoverviewshowcourseprogress';

    /** @var string Where the image of a course with none comes from: {@see SOURCE_PATTERN} or {@see SOURCE_FALLBACK}. */
    public const SETTING_IMAGESOURCE = 'courseoverviewimagesource';

    /** @var string In which views the course image is shown: a comma list of {@see VIEWS}, '' for none. */
    public const SETTING_IMAGES = 'courseoverviewshowcourseimages';

    /** @var string Boost Union's "yes" (THEME_BOOST_UNION_SETTING_SELECT_YES in its lib.php). */
    public const YES = 'yes';

    /** @var string The course image, else the generated one, the default. */
    public const SOURCE_PATTERN = 'coursepluspattern';

    /** @var string The course image, else Boost Union's fallback image. */
    public const SOURCE_FALLBACK = 'courseplusfallback';

    /** @var string The card view: the cover of every card surface. */
    public const VIEW_CARD = 'card';

    /** @var string The list view: the thumbnail of every list surface. */
    public const VIEW_LIST = 'list';

    /** @var string The summary view: no card surface is one. */
    public const VIEW_SUMMARY = 'summary';

    /** @var string[] The views of {@see SETTING_IMAGES}, all three on by default. */
    public const VIEWS = [self::VIEW_CARD, self::VIEW_LIST, self::VIEW_SUMMARY];

    /**
     * Whether Boost Union's settings decide the cards of this request.
     *
     * @param \moodle_page|null $page The page whose theme is asked; the request's ($PAGE) by default. In a web
     *   service it is read after validate_context(), which sets the theme the request draws in.
     * @return bool True when the theme is Boost Union or a child of it, and Boost Union is installed.
     */
    public static function active(?\moodle_page $page = null): bool {
        global $PAGE;

        $theme = ($page ?? $PAGE)->theme;
        $chain = array_merge([(string) $theme->name], array_map('strval', (array) ($theme->parents ?? [])));
        if (!in_array(self::THEME, $chain, true)) {
            return false;
        }
        return \core_component::get_component_directory(self::COMPONENT) !== null;
    }

    /**
     * Whether the cards draw the progress row and its percentage.
     *
     * Only the row: the completed seal and the cover's green base stay whatever this says.
     *
     * @param \moodle_page|null $page The page whose theme is asked; the request's by default.
     * @return bool False only when Boost Union applies and its setting says anything but "yes".
     */
    public static function show_progress(?\moodle_page $page = null): bool {
        if (!self::active($page)) {
            return true;
        }
        $value = get_config(self::COMPONENT, self::SETTING_PROGRESS);
        return $value === false || $value === self::YES;
    }

    /**
     * Where the image of a course with none of its own comes from.
     *
     * @param \moodle_page|null $page The page whose theme is asked; the request's by default.
     * @return string {@see SOURCE_FALLBACK} when Boost Union applies and it is chosen, {@see SOURCE_PATTERN}
     *   otherwise: missing, empty or a value Boost Union does not know, as its own renderer reads it.
     */
    public static function image_source(?\moodle_page $page = null): string {
        if (!self::active($page)) {
            return self::SOURCE_PATTERN;
        }
        $value = get_config(self::COMPONENT, self::SETTING_IMAGESOURCE);
        return $value === self::SOURCE_FALLBACK ? self::SOURCE_FALLBACK : self::SOURCE_PATTERN;
    }

    /**
     * Whether the course image is drawn in one view.
     *
     * The setting is a multi-checkbox: missing means all three views (Boost Union's default), while an empty value
     * is an administrator who unchecked every box, and shows none.
     *
     * @param string $view One of {@see VIEWS}.
     * @param \moodle_page|null $page The page whose theme is asked; the request's by default.
     * @return bool
     * @throws \coding_exception For a view outside {@see VIEWS}.
     */
    public static function show_images(string $view, ?\moodle_page $page = null): bool {
        if (!in_array($view, self::VIEWS, true)) {
            throw new \coding_exception('Unknown course overview view: ' . $view);
        }
        if (!self::active($page)) {
            return true;
        }
        $value = get_config(self::COMPONENT, self::SETTING_IMAGES);
        if ($value === false) {
            return true;
        }
        return in_array($view, array_map('trim', explode(',', (string) $value)), true);
    }
}
