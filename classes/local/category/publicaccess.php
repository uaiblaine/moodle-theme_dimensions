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
 * Theme Dimensions - Whether a category page may be served to visitors who are not logged in
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

/**
 * Whether a category's public page may be served to a visitor who is not logged in.
 *
 * A thin adapter over local_unlistedcourses' category_discoverability::is_public(),
 * which is the gate for an anonymous page on a site that keeps forcelogin on: it
 * requires the public state, a visible category and a visible ancestor path, and it
 * is a property of the category, not of the viewer.
 *
 * The site's own switches are folded in here, which is the point of the class: the
 * page, the forwarding of /course/index.php, the hero image route and the Open Graph
 * tags all ask this one question, so switching the surface off closes every one of
 * them at once.
 *
 * Fails closed: when the predicate class is missing (the dependency uninstalled, a
 * broken deploy) the answer is "not public" and the visitor gets the login page.
 * {@see \theme_dimensions\local\landing\redirector::is_category_ghosted()}
 * deliberately fails open instead, because ghosting strands a logged-in user who is
 * entitled to the category. One withholds a page from the internet, the other from
 * somebody who may be entitled to it, so do not harmonise them. Both are pinned by
 * tests that hand the adapter a class that does not exist.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publicaccess {
    /** @var string The setting that serves public categories to visitors at all. */
    public const SETTING_ENABLED = 'categorypublicenabled';

    /** @var string The setting that puts the root of the course index on the internet. */
    public const SETTING_ROOT = 'categoryrootpublic';

    /** @var string The setting that opens the visitor's web service, get_public_category_rows. */
    public const SETTING_CLIENT = 'categorypublicclient';

    /** @var string The predicate this adapter delegates to. */
    public const PREDICATE = \local_unlistedcourses\category_discoverability::class;

    /**
     * Whether the anonymous surface is switched on at all.
     *
     * Two switches, and the showcase's own comes first: the kill switch of the whole
     * feature ({@see population::enabled()}; off, both shells revert to what the site renders
     * without the showcase) and then this surface's own. Both default-on: only an explicitly stored
     * '0' switches either off, so a site that never visited the settings tab serves the
     * public categories it marked public.
     *
     * @return bool True unless either setting holds an explicit '0'.
     */
    public static function enabled(): bool {
        if (!population::enabled()) {
            return false;
        }
        $stored = get_config('theme_dimensions', self::SETTING_ENABLED);
        return !($stored !== false && (string) $stored === '0');
    }

    /**
     * Whether the root of the course index is on the internet.
     *
     * The inverse of {@see enabled()}'s convention, although the two settings sit side by side
     * in the same tab: the surface is on unless an explicit '0' closes it, and the root is off
     * unless an explicit '1' opens it, so a site that never saved the setting keeps the root off.
     *
     * The pseudo-category holds no row, no context and no discoverability state, so there is
     * nothing the plugin's predicate could be asked about it: this setting is the whole of the
     * root's own "public" state, which is why it lives here rather than in
     * local_unlistedcourses (whose set_state() refuses the top level outright).
     *
     * @return bool True only when the setting holds an explicit '1'.
     */
    public static function root_enabled(): bool {
        $stored = get_config('theme_dimensions', self::SETTING_ROOT);
        return $stored !== false && (string) $stored === '1';
    }

    /**
     * Whether visitors are answered by the web service get_public_category_rows.
     *
     * Off unless an explicit '1' opens it, like {@see root_enabled()}: a new endpoint on the
     * internet is opened on purpose, never by an upgrade arriving.
     *
     * Read BESIDE {@see is_public()}, never inside it. is_public() is the gate of the public page,
     * the forward of /course/index.php, the image route and the head tags, so a switch folded into
     * it would take every public page off the internet while this default is off. The web service
     * asks both.
     *
     * @return bool True only when the setting holds an explicit '1'.
     */
    public static function client_enabled(): bool {
        $stored = get_config('theme_dimensions', self::SETTING_CLIENT);
        return $stored !== false && (string) $stored === '1';
    }

    /**
     * Whether the category's public page may be served anonymously.
     *
     * Never throws for an id that is not there: the callers are the anonymous surface,
     * where the id came from a visitor, so a missing category is an answer and not an
     * exception. A negative id has no public page at all.
     *
     * The root (id 0) answers from three switches and never from the predicate, which has no
     * row to read for the pseudo-category: the whole feature ({@see population::enabled()}),
     * this surface ({@see enabled()}) and the root's own setting ({@see root_enabled()}), plus
     * the predicate class existing. Without that last clause a site whose
     * local_unlistedcourses is missing or broken would answer the internet with an empty root
     * rather than a refusal, because {@see population::directory()} keeps only the children
     * the plugin calls public.
     *
     * @param int $categoryid The category id from the request, not yet validated.
     * @param string|null $predicate The predicate class; tests pass a nonexistent one to pin the fail-closed answer.
     * @return bool True only when the surface is on, the predicate exists and says the category is public.
     */
    public static function is_public(int $categoryid, ?string $predicate = null): bool {
        if ($categoryid < 0) {
            return false;
        }
        if (!self::enabled()) {
            return false;
        }
        $predicate = $predicate ?? self::PREDICATE;
        if (!class_exists($predicate)) {
            // Fail closed: no predicate, no public category.
            return false;
        }
        if ($categoryid === 0) {
            return self::root_enabled();
        }
        return (bool) $predicate::is_public($categoryid);
    }
}
