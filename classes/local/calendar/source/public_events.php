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
 * Theme Dimensions - The category events of public categories, for a visitor.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar\source;

use theme_dimensions\local\calendar\keys;
use theme_dimensions\local\calendar\range;
use theme_dimensions\local\calendar\visitor;
use theme_dimensions\local\calendar\window;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\landing\links;

/**
 * The window's category events of the categories a visitor may be shown, read in one statement, with no viewer.
 *
 * Not a {@see \theme_dimensions\local\calendar\source}: those are handed a viewer's scope, and core's closure they keep
 * ends in has_capability(), which refuses user id 0 under forcelogin. Who is public is decided before this class is
 * asked, by {@see publicaccess::public_ids()} (the surface's switches, then local_unlistedcourses' effective answer:
 * the category's own row public, every ancestor visible, none unlisted), and nothing here weakens it.
 *
 * The statement keeps a row only when it is a category event as core writes one: `eventtype` category, no course, no
 * group, no component (a component's event is the "other" key, {@see keys::classify()}), visible, in one of those
 * categories, and starting inside the window or spanning into it (core's own vault rule with the duration). It rides
 * core's `groupid-courseid-categoryid-visible-userid` index of {event}. The creator (`userid`) is never selected.
 *
 * Every text is the plain spelling: the name and the location through {@see visitor::name()} in the category's context,
 * core's multilang filter alone and the others off (a filter would cost statements per context and may print what a
 * visitor must not read), and the description as text ({@see text()}, decision V2: core's `calendar` file area asks for
 * a login under forcelogin, so no image of it is ever addressed), cut at {@see MAX_DESCRIPTION} characters. An item
 * leads to the category's public page ({@see links::category_page()}), the one category page a visitor can open. At
 * most {@see CAP} events are answered, earliest first.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class public_events {
    /** @var string The source name items carry: they are core's event rows, as core_events' items are. */
    public const SOURCE = core_events::SOURCE;

    /** @var int The longest description answered, in characters: a popover's worth, and a bound on an anonymous payload. */
    public const MAX_DESCRIPTION = 2000;

    /**
     * @var int The most category events one window answers a visitor, earliest first: a bound on what an anonymous
     *     request costs, at the price of {@see truncated()} (PROPOSAL.md section 32.1). The logged-in range keeps none.
     */
    public const CAP = 200;

    /** @var array The descriptions of the last call: event id => text, for the events that have one. */
    private array $descriptions = [];

    /** @var bool Whether the last call left events out past {@see CAP}. */
    private bool $truncated = false;

    /**
     * Whether the last call left events out past {@see CAP}.
     *
     * @return bool
     */
    public function truncated(): bool {
        return $this->truncated;
    }

    /**
     * The descriptions of the events the last call answered.
     *
     * @return array event id => text.
     */
    public function descriptions(): array {
        return $this->descriptions;
    }

    /**
     * The window's category events of these categories.
     *
     * @param window $window The days asked for.
     * @param int[] $categoryids The categories a visitor may be shown ({@see publicaccess::public_ids()}).
     * @return array[] Items with exactly the keys of range::ITEM_KEYS.
     */
    public function items(window $window, array $categoryids): array {
        global $DB;

        $this->descriptions = [];
        $this->truncated = false;
        if (!$categoryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $categoryids), SQL_PARAMS_NAMED, 'pubcat');
        $rows = $DB->get_records_sql(
            "SELECT e.id, e.name, e.location, e.description, e.format, e.categoryid, e.timestart, e.timeduration
               FROM {event} e
              WHERE e.groupid = 0 AND e.courseid = 0 AND e.categoryid {$insql} AND e.visible = 1
                    AND e.eventtype = :eventtype AND COALESCE(e.component, '') = ''
                    AND e.timestart <= :windowend
                    AND (e.timestart >= :windowstart OR e.timestart + e.timeduration > :spanstart)
           ORDER BY e.timestart, e.id",
            $params + [
                'eventtype' => keys::CATEGORY,
                'windowend' => $window->tend,
                'windowstart' => $window->tstart,
                'spanstart' => $window->tstart,
            ],
            0,
            self::CAP + 1
        );
        // One row past the cap says more were left out, without counting them.
        if (count($rows) > self::CAP) {
            $this->truncated = true;
            $rows = array_slice($rows, 0, self::CAP, true);
        }

        $items = [];
        foreach ($rows as $row) {
            $categoryid = (int) $row->categoryid;
            $context = \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
            if (!$context) {
                // The category went between the predicate's read and this one.
                continue;
            }
            $id = (int) $row->id;
            $text = self::text((string) $row->description, (int) $row->format, $context);
            if ($text !== '') {
                $this->descriptions[$id] = \core_text::strlen($text) > self::MAX_DESCRIPTION
                    ? rtrim(\core_text::substr($text, 0, self::MAX_DESCRIPTION - 1)) . '…'
                    : $text;
            }
            $items[] = range::item([
                'id' => $id,
                'source' => self::SOURCE,
                'key' => keys::CATEGORY,
                'name' => visitor::name((string) $row->name, $context),
                'timestart' => (int) $row->timestart,
                'timeduration' => max(0, (int) $row->timeduration),
                'courseid' => 0,
                'categoryid' => $categoryid,
                'groupid' => 0,
                'modulename' => '',
                'instance' => 0,
                'component' => '',
                'eventtype' => keys::CATEGORY,
                'location' => visitor::name((string) $row->location, $context),
                'url' => links::category_page($categoryid)->out(false),
                'actionevent' => false,
                'action' => null,
                'overdue' => false,
                'held' => false,
            ]);
        }
        return $items;
    }

    /**
     * An event description as plain text: format_text() with the filters off, then html_to_text() without wrapping.
     *
     * format_text() is what turns each of the four formats into cleaned HTML; an image becomes its alternative text in
     * brackets, a link its words alone, and nothing addresses a file.
     *
     * @param string $description The stored description.
     * @param int $format Its format.
     * @param \core\context $context The category's context.
     * @return string The text, '' for none.
     */
    private static function text(string $description, int $format, \core\context $context): string {
        if (trim($description) === '') {
            return '';
        }
        $html = format_text($description, $format, ['context' => $context, 'filter' => false, 'para' => false]);
        return trim(html_to_text($html, 0, false));
    }
}
