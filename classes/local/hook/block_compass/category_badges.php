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
 * Only categories the current user may see are answered, by core's rule (first_crests() says how). A full
 * branding build costs seven statements a category, which the line must not pay: this listener keeps its
 * own small entry per category in the branding cache and reads its misses in one statement.
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

    /** @var string Key prefix of this listener's entries in the categorybranding cache, beside branding's int keys. */
    private const KEY = 'compasscrest_';

    /**
     * The first crest of each category among the given ones that the current user may see.
     *
     * Each category's entry - its visible flag, its context record and its first crest - lives in the
     * categorybranding cache under its own key, so branding::forget_all() drops it whenever it drops the
     * branding (an editor save, a category updated, moved or deleted). The misses are read in one statement.
     * Who may see a category is core's own rule, core_course_category::can_view_category(), asked with the
     * cached flag and the preloaded context, so a warm answer reads nothing.
     *
     * @param int[] $categoryids Category ids.
     * @return array Category id => ['url' => string, 'alt' => string], for the visible categories that have one.
     */
    public static function first_crests(array $categoryids): array {
        $ids = [];
        foreach ($categoryids as $categoryid) {
            if ((int) $categoryid > 0) {
                $ids[(int) $categoryid] = self::KEY . (int) $categoryid;
            }
        }
        if (!$ids) {
            return [];
        }
        $cache = \core_cache\cache::make(branding::COMPONENT, branding::CACHE);
        $entries = [];
        $missing = [];
        $cached = $cache->get_many(array_values($ids));
        foreach ($ids as $categoryid => $key) {
            if (is_array($cached[$key] ?? null)) {
                $entries[$categoryid] = $cached[$key];
            } else {
                $missing[] = $categoryid;
            }
        }
        if ($missing) {
            $fresh = self::read($missing);
            $toset = [];
            foreach ($fresh as $categoryid => $entry) {
                $toset[self::KEY . $categoryid] = $entry;
            }
            if ($toset) {
                $cache->set_many($toset);
            }
            $entries += $fresh;
        }

        $crests = [];
        foreach ($entries as $categoryid => $entry) {
            if ($entry['crest'] === null) {
                continue;
            }
            \core\context_helper::preload_from_record((object) $entry['ctx']);
            if (!\core_course_category::can_view_category((object) ['id' => $categoryid, 'visible' => $entry['visible']])) {
                continue;
            }
            [$area, $badge] = $entry['crest'];
            $crests[$categoryid] = [
                'url' => branding::badge_url($categoryid, $area, $badge)->out(false),
                'alt' => publicfiles::badge_alt((string) $badge['filename']),
            ];
        }
        return $crests;
    }

    /**
     * The entries of the given categories, read in one statement: visible flag, context record, first crest.
     *
     * A category that no longer exists has no entry.
     *
     * @param int[] $categoryids Category ids missing from the cache.
     * @return array Category id => ['visible' => int, 'ctx' => array, 'crest' => [area, badge] or null].
     */
    private static function read(array $categoryids): array {
        global $DB;

        [$catsql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'cat');
        [$areasql, $areaparams] = $DB->get_in_or_equal(branding::AREA_BADGES, SQL_PARAMS_NAMED, 'area');
        $params += $areaparams + [
            'level' => CONTEXT_COURSECAT,
            'component' => branding::COMPONENT,
            'dot' => '.',
        ];
        // Index: course_categories by its primary key; context (contextlevel, instanceid); files (contextid,
        // component, filearea, itemid). Bound: the asked categories (the line's twenty at most), three areas
        // of at most one file each. The first column is unique: the file's id, or the category's negated.
        $sql = "SELECT COALESCE(f.id, -cc.id) AS rowkey, cc.id AS categoryid, cc.visible,
                       ctx.id AS ctxid, ctx.path AS ctxpath, ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel,
                       ctx.instanceid AS ctxinstance, ctx.locked AS ctxlocked,
                       f.filearea, f.contextid, f.contenthash, f.filename
                  FROM {course_categories} cc
                  JOIN {context} ctx ON ctx.contextlevel = :level AND ctx.instanceid = cc.id
             LEFT JOIN {files} f ON f.contextid = ctx.id
                                AND f.component = :component
                                AND f.filearea {$areasql}
                                AND f.itemid = 0
                                AND f.filename <> :dot
                 WHERE cc.id {$catsql}";
        $order = array_flip(branding::AREA_BADGES);
        $entries = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $categoryid = (int) $row->categoryid;
            $entries[$categoryid] ??= [
                'visible' => (int) $row->visible,
                'ctx' => [
                    'ctxid' => (int) $row->ctxid,
                    'ctxpath' => (string) $row->ctxpath,
                    'ctxdepth' => (int) $row->ctxdepth,
                    'ctxlevel' => (int) $row->ctxlevel,
                    'ctxinstance' => (int) $row->ctxinstance,
                    'ctxlocked' => (int) $row->ctxlocked,
                ],
                'crest' => null,
            ];
            if ($row->filearea === null) {
                continue;
            }
            $area = (string) $row->filearea;
            $current = $entries[$categoryid]['crest'];
            if ($current !== null && $order[$current[0]] <= $order[$area]) {
                continue;
            }
            $entries[$categoryid]['crest'] = [$area, [
                'contextid' => (int) $row->contextid,
                'contenthash' => (string) $row->contenthash,
                'filename' => (string) $row->filename,
            ]];
        }
        return $entries;
    }
}
