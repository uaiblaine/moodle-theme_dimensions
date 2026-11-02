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
 * Theme Dimensions - The per-page bulk reads of the category showcase
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\access;
use theme_dimensions\local\landing\publicfiles;
use theme_dimensions\local\landingfields;

/**
 * What a page of cards needs beyond its ids, read once per layer over the page slice and never per course.
 *
 * Three layers, one statement each, every one bounded by the page size:
 * the course rows themselves, the landing page custom field values
 * ({@see landingfields::values_many()}) and the course images. Two more are read
 * only for the cards that need them, so a page without such a card pays nothing
 * for them: the routes beside a relationship and the opening dates
 * ({@see next_actions()}), and the prerequisite of a conditional card
 * ({@see prerequisites()}). Never build core_course_list_element one at a time for these:
 * get_custom_fields() and has_course_overviewfiles() each fall back to a
 * per-course query, and the last one has no bulk escape hatch in core at all.
 *
 * The course contacts, the summary, the venue and the programme are not read
 * here: only the course dialogue shows them, and it fetches them for one course
 * when it opens ({@see dialogue}), so a page pays nothing for what nobody opened.
 *
 * The images reproduce core's own choice - the first file of the course's
 * overviewfiles area, in filename order, that passes the site's accepted
 * types, within courseoverviewfileslimit, and is a real image - from one
 * {files} statement over the page's contexts, and then write the answer into
 * core's course_image cache, the one Boost Union's cards and the Dashboard
 * read, so the next reader of the same course pays nothing. The cache is not
 * the source of the answer: it has a data source, and a miss on get_many()
 * would load one course at a time. It is only compared, so that an entry
 * already right is not written again ({@see warm_course_image_cache()}).
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cards {
    /** @var string The columns a card needs from {course}; the summary is the dialogue's ({@see dialogue::COURSE_FIELDS}). */
    private const COURSE_FIELDS = 'id, category, fullname, shortname, idnumber, startdate, enddate, visible, cacherev, '
        . 'timemodified';

    /**
     * @var string[] The landing page fields a card reads, and the only ones {@see values()} asks for.
     *
     * {@see \theme_dimensions\output\category_card} reads the model (the kind, the date tile, the call to
     * action, the crests' gate), the modality, the workload and the city (the meta line) and the four
     * crests. The venue and the programme are the dialogue's ({@see dialogue::SHORTNAMES}). A card that
     * starts reading another value names its field here, or reads the empty value: cards_test compares the
     * card's keys read this way with every field read, and reads the card's source for the keys it compares.
     */
    public const CARD_SHORTNAMES = [
        landingfields::SHORTNAME_MODEL,
        landingfields::SHORTNAME_MODALITY,
        landingfields::SHORTNAME_WORKLOAD,
        landingfields::SHORTNAME_CITY,
        landingfields::SHORTNAME_CREST1,
        landingfields::SHORTNAME_CREST2,
        landingfields::SHORTNAME_CREST3,
        landingfields::SHORTNAME_CREST4,
    ];

    /**
     * The course rows of the page, one statement.
     *
     * @param int[] $courseids The page's ids.
     * @return \stdClass[] courseid => course row, in the order given.
     */
    public static function records(array $courseids): array {
        global $DB;

        $courseids = array_map('intval', $courseids);
        if (!$courseids) {
            return [];
        }
        // Rides the primary key.
        $rows = $DB->get_records_list('course', 'id', $courseids, '', self::COURSE_FIELDS);
        $ordered = [];
        foreach ($courseids as $courseid) {
            if (isset($rows[$courseid])) {
                $ordered[$courseid] = $rows[$courseid];
            }
        }
        return $ordered;
    }

    /**
     * The landing page field values of the page, one statement, the textareas unfiltered.
     *
     * The rows, not the ids, because the reader formats every field name in
     * the course's context and would otherwise fetch one context per card:
     * the page's contexts are rebuilt from the preload columns first. The
     * textareas skip the text filter pass ({@see landingfields::values_many()}
     * says why): a card shows an excerpt, and a filter pass per course context
     * is a recordset plus whatever each active filter reads, per card. Only the
     * fields a card reads are read ({@see CARD_SHORTNAMES}): the landing page's
     * other eleven would be a controller each per card for keys nobody prints.
     *
     * @param \stdClass[] $rows Population rows carrying the context preload columns, keyed by course id.
     * @return \stdClass[] courseid => the typed values of landingfields::values(), the card's keys filled.
     */
    public static function values(array $rows): array {
        population::page_contexts($rows);
        return landingfields::values_many(array_map('intval', array_keys($rows)), false, self::CARD_SHORTNAMES);
    }

    /**
     * The card image of every course on the page, one statement, and core's cache warmed with the answer.
     *
     * Two routes, one file. Core's own course/overviewfiles route answers a visitor with the
     * login page whenever the site forces login: file_pluginfile() runs require_login() for that
     * area (lib/filelib.php), so the cover of a card on the public category listing loads for
     * nobody. The public shell therefore asks for the theme's own file route
     * ({@see publicfiles::cover_url()}), which carries its checks in resolve() and needs no
     * session - the same escape the landing page makes for its hero image. It is safe to mint here
     * because the public shell's page holds public courses only
     * ({@see \local_unlistedcourses\access::filter_courses_public()} runs over the page slice),
     * and it is refused anyway for a course that stops being public.
     *
     * Core's course_image cache keeps core's URL in both modes, which is why both are computed:
     * that cache is what Boost Union's own cards and the Dashboard read, and a theme route
     * written into it would leak onto pages behind the login, where it is not the one to use.
     *
     * @param \stdClass[] $rows Population rows carrying the context preload columns, keyed by course id.
     * @param bool $public Whether this is the public shell, whose reader has no session.
     * @return array courseid => \moodle_url or null, for every row given.
     */
    public static function images(array $rows, bool $public = false): array {
        global $CFG, $DB;

        $images = [];
        foreach ($rows as $courseid => $row) {
            $images[(int) $courseid] = null;
        }
        if (!$rows || empty($CFG->courseoverviewfileslimit)) {
            return $images;
        }
        $contexts = population::page_contexts($rows);
        $bycontext = [];
        foreach ($contexts as $courseid => $context) {
            $bycontext[$context->id] = $courseid;
        }

        // Rides component-filearea-contextid-itemid of {files}; the page's contexts are the bounded list.
        [$insql, $params] = $DB->get_in_or_equal(array_keys($bycontext), SQL_PARAMS_NAMED, 'ctx');
        $files = $DB->get_records_sql(
            "SELECT f.id, f.contenthash, f.pathnamehash, f.contextid, f.component, f.filearea, f.itemid, f.filepath,
                    f.filename, f.userid, f.filesize, f.mimetype, f.status, f.source, f.author, f.license,
                    f.timecreated, f.timemodified, f.sortorder, f.referencefileid
               FROM {files} f
              WHERE f.component = :component AND f.filearea = :filearea AND f.itemid = 0
                    AND f.contextid {$insql} AND f.filename <> :dot
           ORDER BY f.contextid, f.filename",
            $params + ['component' => 'course', 'filearea' => 'overviewfiles', 'dot' => '.']
        );

        // Warm the image-info cache for every hash at once; is_valid_image() then reads the static layer.
        $hashes = [];
        foreach ($files as $file) {
            $hashes[$file->contenthash] = true;
        }
        if ($hashes) {
            \cache::make('core', 'file_imageinfo')->get_many(array_keys($hashes));
        }

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/course/lib.php');
        $accepted = (new \core_form\filetypes_util())->normalize_file_types($CFG->courseoverviewfilesext);
        $anytype = in_array('*', $accepted, true) || empty($accepted);
        $limit = (int) $CFG->courseoverviewfileslimit;
        $fs = get_file_storage();

        $seen = [];
        $picked = [];
        foreach ($files as $record) {
            $courseid = $bycontext[(int) $record->contextid];
            if (!$anytype && !file_extension_in_typegroup($record->filename, $accepted)) {
                continue;
            }
            $seen[$courseid] = ($seen[$courseid] ?? 0) + 1;
            if ($seen[$courseid] > $limit || $images[$courseid] !== null) {
                continue;
            }
            $file = $fs->get_file_instance($record);
            if (!$file->is_valid_image()) {
                continue;
            }
            $picked[$courseid] = $file;
            $images[$courseid] = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
        }

        self::warm_course_image_cache($images);

        // The cache is warm with core's answer before this: the public reader gets the other route.
        if ($public) {
            foreach ($picked as $courseid => $file) {
                $images[$courseid] = publicfiles::cover_url((object) ['id' => $courseid], $file);
            }
        }
        return $images;
    }

    /**
     * Write core's answer into core's course_image cache, for the courses whose entry is missing or says otherwise.
     *
     * A page usually finds the cache already holding what it computed (the previous request wrote it), and the
     * file store pays a write per key, so the entries are compared first. They are read without the cache's
     * data source: has() asks the store alone, and get_many() is then handed only keys the store holds, because a
     * missing key in get_many() is loaded through the data source one course at a time. An entry evicted between
     * the two calls is loaded that way once, and compared like any other. A course with no image is cached as
     * null, as the data source caches it.
     *
     * @param array $images courseid => \moodle_url or null: core's own route, never the public shell's.
     * @return void
     */
    private static function warm_course_image_cache(array $images): void {
        $cache = \cache::make('core', 'course_image');
        $held = [];
        foreach (array_keys($images) as $courseid) {
            if ($cache->has($courseid)) {
                $held[] = $courseid;
            }
        }
        $stored = $held ? $cache->get_many($held) : [];
        $writes = [];
        foreach ($images as $courseid => $url) {
            $value = $url?->out_as_local_url();
            if (!array_key_exists($courseid, $stored) || $stored[$courseid] !== $value) {
                $writes[$courseid] = $value;
            }
        }
        if ($writes) {
            $cache->set_many($writes);
        }
    }

    /**
     * The routes and the opening dates local_unlistedcourses' batch reports for the page's cards that print one, in one call.
     *
     * A card that tells the viewer about a row of theirs - one that starts later, an application, a suspension,
     * an end - may also say that a method would take them now (decision D2 of the enrolment matrix, core's
     * default kept). A card with nothing on offer, and a suspended or ended one, may instead say when a method
     * will open to them. Both answers are the plugin's own batch, {@see access::get_next_actions()}, asked once
     * for the cards {@see asks()} names: a page holding none of them costs nothing here, and the batch's
     * statements are bounded by the page, never by the population. The batch may err towards open where a
     * plugin's rule cannot be read in SQL, as the classifier does; it never invents a route a method refuses,
     * and it dates an opening only where the window is the one refusal, so a date it gives is a promise.
     *
     * @param \stdClass[] $rows Population rows carrying the classifier state and opening flag, keyed by course id.
     * @return array courseid => ['routes' => the routes, each ['kind' => plugin name, 'instanceid' => int],
     *         'opens' => int|null, the timestamp a refused method opens], for the cards asked about; a course
     *         missing from the answer has neither.
     */
    public static function next_actions(array $rows): array {
        $ids = [];
        foreach ($rows as $courseid => $row) {
            if (self::asks($row)) {
                $ids[] = (int) $courseid;
            }
        }
        if (!$ids) {
            return [];
        }
        $answers = [];
        foreach (access::get_next_actions($ids) as $courseid => $next) {
            $answers[(int) $courseid] = ['routes' => $next['routes'], 'opens' => $next['opens']];
        }
        return $answers;
    }

    /**
     * Whether a card asks local_unlistedcourses' batch about its course: the one decision of who pays for it.
     *
     * Every card beside a relationship that is not active ({@see classifier::ROUTE_STATES}), for its route line
     * and, suspended or ended, for its opening date; and a card with nothing on offer only where the classifier
     * saw an enrolment window starting later, the only kind of instance the batch could date. No other card
     * prints either answer.
     *
     * @param \stdClass $row A population row carrying the classifier state and opening flag.
     * @return bool
     */
    private static function asks(\stdClass $row): bool {
        $state = (string) ($row->state ?? '');
        if (in_array($state, classifier::ROUTE_STATES, true)) {
            return true;
        }
        return $state === classifier::STATE_NONE && !empty($row->opening);
    }

    /**
     * The course each conditional card of the page waits for, in one statement with its context preloaded.
     *
     * The classifier names the prerequisite only to a viewer actively enrolled in it, so the course exists and
     * the card may link to it. Its context arrives with it, so the card formats the name in the right context at
     * no further cost; a page holding no conditional card pays nothing.
     *
     * @param \stdClass[] $rows Population rows carrying the classifier state and prerequisiteid, keyed by course id.
     * @return \stdClass[] prerequisite course id => {id, fullname}, for every prerequisite the page names.
     */
    public static function prerequisites(array $rows): array {
        global $DB;

        $ids = [];
        foreach ($rows as $row) {
            if (($row->state ?? '') === classifier::STATE_CONDITIONAL && !empty($row->prerequisiteid)) {
                $ids[(int) $row->prerequisiteid] = true;
            }
        }
        if (!$ids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($ids), SQL_PARAMS_NAMED, 'pre');
        $ctxselect = \core\context_helper::get_preload_record_columns_sql('ctx');
        // Rides the primary key, and the context's instance index.
        $records = $DB->get_records_sql(
            "SELECT c.id, c.fullname, {$ctxselect}
               FROM {course} c
               JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
              WHERE c.id {$insql}",
            $params + ['contextcourse' => CONTEXT_COURSE]
        );
        $prerequisites = [];
        foreach ($records as $record) {
            \core\context_helper::preload_from_record($record);
            $prerequisites[(int) $record->id] = (object) ['id' => (int) $record->id, 'fullname' => (string) $record->fullname];
        }
        return $prerequisites;
    }
}
