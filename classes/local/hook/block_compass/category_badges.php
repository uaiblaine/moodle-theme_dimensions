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
 * Theme Dimensions - The answer to block_compass's category_badges hook
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\hook\block_compass;

use theme_dimensions\local\category\branding;
use theme_dimensions\local\landing\publicfiles;

/**
 * Gives the categories block_compass's open-courses line names their first crest (block_compass ADR-019).
 *
 * Registered in db/hooks.php for \block_compass\hook\category_badges; without the block nothing calls this.
 * Only categories the current user may see are answered (core_course_category::is_uservisible()). The
 * crests come from the branding cache the hero and the showcase fill; a category missing from it is read
 * with one statement for all the misses together, and the cache is not filled from here: a full branding
 * build costs seven statements a category, which the line must not pay.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_badges {
    /**
     * Answers the hook: the first crest of each asked category the reader may see.
     *
     * @param \block_compass\hook\category_badges $hook The question, with the categories the block names.
     * @param callable|null $source Stands in for first_crests() in tests: category ids in, category id =>
     *     crest out; null for first_crests().
     * @return void
     */
    public static function answer(\block_compass\hook\category_badges $hook, ?callable $source = null): void {
        try {
            // Core reads db/hooks.php from disk, so this runs on a site where the theme is not installed yet.
            if (!get_config('theme_dimensions', 'version')) {
                return;
            }
            $ids = $hook->get_categoryids();
            $crests = $source ? $source($ids) : self::first_crests($ids);
            foreach ($crests as $categoryid => $crest) {
                $hook->add_badges((int) $categoryid, [$crest]);
            }
        } catch (\Throwable $exception) {
            debugging(
                'theme_dimensions: the category crests for block_compass could not be read: ' . $exception->getMessage(),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * The first crest of each visible category among the given ones.
     *
     * @param int[] $categoryids Category ids.
     * @return array Category id => ['url' => string, 'alt' => string], for the categories that have one.
     */
    public static function first_crests(array $categoryids): array {
        $visible = [];
        foreach (\core_course_category::get_many($categoryids) as $categoryid => $category) {
            if ($category->is_uservisible()) {
                $visible[] = (int) $categoryid;
            }
        }
        if (!$visible) {
            return [];
        }

        $first = [];
        $missing = [];
        $cache = \core_cache\cache::make(branding::COMPONENT, branding::CACHE);
        foreach ($cache->get_many($visible) as $categoryid => $payload) {
            if (!is_array($payload)) {
                $missing[] = (int) $categoryid;
                continue;
            }
            $badges = $payload['badges'] ?? [];
            if ($badges) {
                $area = (string) array_key_first($badges);
                $first[(int) $categoryid] = [$area, $badges[$area]];
            }
        }
        if ($missing) {
            $first += self::first_badge_files($missing);
        }

        $crests = [];
        foreach ($first as $categoryid => [$area, $badge]) {
            $crests[$categoryid] = [
                'url' => branding::badge_url($categoryid, $area, $badge)->out(false),
                'alt' => publicfiles::badge_alt((string) $badge['filename']),
            ];
        }
        return $crests;
    }

    /**
     * The first badge file of each given category, read in one statement.
     *
     * @param int[] $categoryids Category ids missing from the branding cache.
     * @return array Category id => [area, ['contextid' => int, 'contenthash' => string, 'filename' => string]].
     */
    private static function first_badge_files(array $categoryids): array {
        global $DB;

        [$catsql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        [$areasql, $areaparams] = $DB->get_in_or_equal(branding::AREA_BADGES, SQL_PARAMS_NAMED, 'area');
        $params += $areaparams + [
            'level' => CONTEXT_COURSECAT,
            'component' => branding::COMPONENT,
            'dot' => '.',
        ];
        // Index: context (contextlevel, instanceid); files (contextid, component, filearea, itemid). Bound: three
        // areas of at most one file each, for at most the line's twenty categories.
        $sql = "SELECT f.id, ctx.instanceid, f.contextid, f.filearea, f.contenthash, f.filename
                  FROM {context} ctx
                  JOIN {files} f ON f.contextid = ctx.id
                                AND f.component = :component
                                AND f.filearea {$areasql}
                                AND f.itemid = 0
                                AND f.filename <> :dot
                 WHERE ctx.contextlevel = :level
                   AND ctx.instanceid {$catsql}";
        $order = array_flip(branding::AREA_BADGES);
        $first = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $categoryid = (int) $row->instanceid;
            $area = (string) $row->filearea;
            if (isset($first[$categoryid]) && $order[$first[$categoryid][0]] <= $order[$area]) {
                continue;
            }
            $first[$categoryid] = [$area, [
                'contextid' => (int) $row->contextid,
                'contenthash' => (string) $row->contenthash,
                'filename' => (string) $row->filename,
            ]];
        }
        return $first;
    }
}
