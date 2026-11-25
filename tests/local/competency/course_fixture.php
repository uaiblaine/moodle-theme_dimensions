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
 * Theme Dimensions - A course with competencies, learners and a teacher, for the course competencies page's tests
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\competency;

use core_competency\user_competency_course;

/**
 * Builds the course the tests of the course competencies page read, and writes ratings without core's events, so a
 * test decides which event, if any, follows a write.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait course_fixture {
    /**
     * A course with completion on, competencies linked, learners enrolled as students and an editing teacher.
     *
     * @param int $learners How many students.
     * @param int $competencies How many course competencies.
     * @return array ['course' => stdClass, 'competencies' => list of ids, 'learners' => list of users,
     *     'teacher' => user, 'framework' => competency_framework].
     */
    protected function course_with_competencies(int $learners, int $competencies): array {
        $generator = self::getDataGenerator();
        $lpg = $generator->get_plugin_generator('core_competency');
        $framework = $lpg->create_framework();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $ids = [];
        for ($i = 0; $i < $competencies; $i++) {
            $competency = $lpg->create_competency(['competencyframeworkid' => $framework->get('id')]);
            $lpg->create_course_competency(['courseid' => $course->id, 'competencyid' => $competency->get('id')]);
            $ids[] = (int) $competency->get('id');
        }
        $students = [];
        for ($i = 0; $i < $learners; $i++) {
            $students[] = $generator->create_and_enrol($course, 'student');
        }
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        return [
            'course' => $course,
            'competencies' => $ids,
            'learners' => $students,
            'teacher' => $teacher,
            'framework' => $framework,
        ];
    }

    /**
     * Write a learner's rating in a course, as the persistent writes it: no event.
     *
     * @param int $userid The learner.
     * @param int $courseid The course.
     * @param int $competencyid The competency.
     * @param int|null $grade The grade, null for no rating.
     * @param bool|null $proficient The proficiency.
     * @return user_competency_course
     */
    protected function rate(int $userid, int $courseid, int $competencyid, ?int $grade, ?bool $proficient): user_competency_course {
        $record = user_competency_course::get_record([
            'userid' => $userid,
            'courseid' => $courseid,
            'competencyid' => $competencyid,
        ]);
        if (!$record) {
            $record = user_competency_course::create_relation($userid, $competencyid, $courseid);
            $record->create();
        }
        $record->set('grade', $grade);
        $record->set('proficiency', $proficient);
        $record->update();

        return $record;
    }

    /**
     * The class figures of one competency, read through the cache.
     *
     * @param int $courseid The course.
     * @param int $competencyid The competency.
     * @return array [learners, rated, proficient], zeros for a competency without a row.
     */
    protected function figures(int $courseid, int $competencyid): array {
        $counts = courseclass::counts($courseid)[$competencyid] ?? ['learners' => 0, 'rated' => 0, 'proficient' => 0];

        return [$counts['learners'], $counts['rated'], $counts['proficient']];
    }
}
