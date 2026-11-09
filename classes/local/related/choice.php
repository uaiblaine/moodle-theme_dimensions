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
 * Theme Dimensions - A landing page's manual choice of competencies for its related courses band
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\related;

use core_customfield\data_controller;
use theme_dimensions\local\landingfields;

/**
 * The competencies an editor chose for a landing page's related courses band in manual mode (R2, D3, D9).
 *
 * The choice is stored in the course field `dimensions_relatedpairs` as framework and competency idnumber pairs, a
 * JSON list of two-string lists: a competency idnumber is unique only inside its framework, and identifiers, unlike
 * ids, survive a backup restored on another site. The course form never shows that field; it offers an autocomplete
 * of the competencies linked to the course (for a trail, to the trail and its steps' courses), and the submission
 * hook turns the ids chosen there back into pairs. The band reads the pairs through {@see competencies()}, and keeps
 * only those still linked to the course, so a competency unlinked since is dropped without anybody editing the choice.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class choice {
    /** @var string The autocomplete that stands in the course form for the pairs field. */
    public const ELEMENT = 'theme_dimensions_relatedcompetencies';

    /** @var string A hidden element saying the form carried the autocomplete, so an empty choice is still an answer. */
    public const MARKER = 'theme_dimensions_relatedform';

    /** @var int The longest stored choice: customfield_text's column, char(1333). */
    public const MAX_LENGTH = 1333;

    /**
     * The pairs a stored value holds; nothing for an empty or malformed value.
     *
     * @param string|null $raw The field's stored value.
     * @return array List of [framework idnumber, competency idnumber], each a non-empty string, each pair once.
     */
    public static function decode(?string $raw): array {
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $pairs = [];
        foreach ($decoded as $pair) {
            if (!is_array($pair) || count($pair) !== 2 || !is_string($pair[0] ?? null) || !is_string($pair[1] ?? null)) {
                continue;
            }
            [$framework, $idnumber] = [trim($pair[0]), trim($pair[1])];
            if ($framework !== '' && $idnumber !== '') {
                $pairs[json_encode([$framework, $idnumber])] = [$framework, $idnumber];
            }
        }
        return array_values($pairs);
    }

    /**
     * The stored value of a choice: '' for none, and only the leading pairs that fit the field's column.
     *
     * @param array $pairs List of [framework idnumber, competency idnumber].
     * @return string
     */
    public static function encode(array $pairs): string {
        $kept = [];
        $encoded = '';
        foreach ($pairs as $pair) {
            $next = json_encode(array_merge($kept, [array_values($pair)]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (\core_text::strlen($next) > self::MAX_LENGTH) {
                break;
            }
            $kept[] = array_values($pair);
            $encoded = $next;
        }
        return $encoded;
    }

    /**
     * The competencies linked to these courses, with what the form shows and what the field stores of each.
     *
     * One statement. The competency's and its framework's idnumbers are what a pair stores; a competency with an
     * empty idnumber, or in a framework without one, cannot be named by a pair and is not offered.
     *
     * @param int[] $courseids The parent, and for a trail its steps' courses.
     * @return \stdClass[] competency id => (id, shortname, idnumber, framework, frameworkname, frameworkcontextid),
     *   ordered by framework, then the competency's path in its tree.
     */
    public static function linked(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!$courseids || !finder::available()) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
        $rows = $DB->get_records_sql(
            "SELECT c.id, c.shortname, c.idnumber, f.idnumber AS framework, f.shortname AS frameworkname,
                    f.contextid AS frameworkcontextid, c.path, c.sortorder
               FROM {competency} c
               JOIN {competency_framework} f ON f.id = c.competencyframeworkid
              WHERE c.id IN (SELECT cc.competencyid FROM {competency_coursecomp} cc WHERE cc.courseid {$insql})
                    AND c.idnumber <> '' AND f.idnumber <> ''
           ORDER BY f.shortname, f.id, c.path, c.sortorder, c.id",
            $params
        );
        $linked = [];
        foreach ($rows as $row) {
            $linked[(int) $row->id] = (object) [
                'id' => (int) $row->id,
                'shortname' => $row->shortname,
                'idnumber' => $row->idnumber,
                'framework' => $row->framework,
                'frameworkname' => $row->frameworkname,
                'frameworkcontextid' => (int) $row->frameworkcontextid,
            ];
        }
        return $linked;
    }

    /**
     * The competencies a course's choice picks among: {@see linked()} over {@see band::courseids()}.
     *
     * @param \stdClass $course The course; one not saved yet (no id) has none.
     * @return \stdClass[] {@see linked()}'s answer.
     */
    public static function linked_to_course(\stdClass $course): array {
        if (empty($course->id)) {
            return [];
        }
        return self::linked(band::courseids($course, band::trail_of($course)));
    }

    /**
     * The autocomplete's options: each linked competency, named with its framework.
     *
     * @param \stdClass[] $linked {@see linked()}'s answer.
     * @return string[] competency id => label, plain text.
     */
    public static function options(array $linked): array {
        $options = [];
        foreach ($linked as $id => $competency) {
            $context = \core\context::instance_by_id($competency->frameworkcontextid);
            $options[$id] = get_string('landingfield_relatedpairs_option', landingfields::COMPONENT, (object) [
                'competency' => format_string($competency->shortname, true, ['context' => $context, 'escape' => false]),
                'idnumber' => $competency->idnumber,
                'framework' => format_string($competency->frameworkname, true, ['context' => $context, 'escape' => false]),
            ]);
        }
        return $options;
    }

    /**
     * The pairs of the competencies chosen in the form, in the order given, keeping only linked competencies.
     *
     * @param array $ids The competency ids the autocomplete submitted.
     * @param \stdClass[] $linked {@see linked()}'s answer: an id outside it is dropped, never stored.
     * @return array List of [framework idnumber, competency idnumber].
     */
    public static function pairs(array $ids, array $linked): array {
        $pairs = [];
        foreach ($ids as $id) {
            $competency = $linked[(int) $id] ?? null;
            if ($competency !== null) {
                $pairs[$competency->id] = [$competency->framework, $competency->idnumber];
            }
        }
        return array_values($pairs);
    }

    /**
     * The ids of the linked competencies a stored choice names: what the form preselects.
     *
     * @param array $pairs List of [framework idnumber, competency idnumber].
     * @param \stdClass[] $linked {@see linked()}'s answer.
     * @return int[] The competency ids, in the choice's order.
     */
    public static function chosen(array $pairs, array $linked): array {
        $byname = [];
        foreach ($linked as $competency) {
            $byname[json_encode([$competency->framework, $competency->idnumber])] = $competency->id;
        }
        $ids = [];
        foreach ($pairs as $pair) {
            $id = $byname[json_encode(array_values($pair))] ?? null;
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * The competency ids a stored choice names, read by framework: one statement per framework named.
     *
     * {@see finder::resolve()} reads a competency idnumber inside its framework (D9). The band then keeps those linked
     * to the course; the ids are not asked to be, so a choice copied from another course resolves the same way.
     *
     * @param array $pairs List of [framework idnumber, competency idnumber].
     * @return int[] The competency ids found, in the choice's order, each once.
     */
    public static function competencies(array $pairs): array {
        $byframework = [];
        foreach ($pairs as [$framework, $idnumber]) {
            $byframework[$framework][] = $idnumber;
        }
        $found = [];
        foreach ($byframework as $framework => $idnumbers) {
            foreach (finder::resolve((string) $framework, $idnumbers)['ids'] as $id) {
                $found[$id] = $id;
            }
        }
        return array_values($found);
    }

    /**
     * Store a course's choice in its pairs field, creating the row when it has none.
     *
     * @param int $courseid The course.
     * @param array $pairs List of [framework idnumber, competency idnumber]; none stores ''.
     * @return bool Whether the field resolves and was written.
     */
    public static function store(int $courseid, array $pairs): bool {
        global $DB;

        $field = landingfields::get_field(landingfields::SHORTNAME_RELATEDPAIRS);
        if (!$field) {
            return false;
        }
        $fieldid = (int) $field->get('id');
        $id = (int) $DB->get_field('customfield_data', 'id', ['instanceid' => $courseid, 'fieldid' => $fieldid]);
        $data = $id
            ? data_controller::create($id, null, $field)
            : data_controller::create(0, (object) ['instanceid' => $courseid, 'fieldid' => $fieldid], $field);
        $value = self::encode($pairs);
        $data->set($data->datafield(), $value);
        $data->set('value', $value);
        $data->set('contextid', \core\context\course::instance($courseid)->id);
        $data->save();
        return true;
    }
}
