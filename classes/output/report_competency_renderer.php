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
 * Theme Dimensions - The report_competency renderer: the class report in place of the first learner's breakdown
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use context_course;
use report_competency\output\report;
use report_competency\output\user_course_navigation;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * Draws report/competency/index.php as the class report when no learner is named, and core's breakdown otherwise.
 *
 * The class name is derived by the renderer factory for `$PAGE->get_renderer('report_competency')`
 * (theme_overridden_renderer_factory: `theme_<name>\output\<component>_renderer`); neither Boost nor Boost Union
 * has one. index.php leaves no seam before it renders: without a `user` parameter it picks the first gradable
 * learner, builds core's renderable for them and prints their name as a heading. So the class mode, decided once per
 * course by {@see draws_class()} (no learner named in the request, and {@see classreport::may_view()}), takes the
 * three things index.php renders through this renderer:
 *
 * - {@see render_report()}: the class report ({@see class_ratings}) instead of core's export, which would insert a
 *   `competency_usercompcourse` row per missing competency of that first learner;
 * - {@see context_header()}: nothing instead of the first learner's heading; the page's heading stays the course's;
 * - {@see render_user_course_navigation()}: core's navigation with "All learners" chosen, the group selector
 *   pointing at the class report and the learners named limited to the ones the report lists
 *   ({@see classreport::learner_ids()}).
 *
 * With `?user=` or for a viewer who may not read the class (one who reads learners only through per-user grants),
 * every call is core's, through the theme's copy of `report_competency/report`, which is untouched.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_competency_renderer extends \report_competency\output\renderer {
    /** @var array Course id => whether this request draws the class report there. */
    private array $classmode = [];

    /**
     * Whether this request draws the class report of a course.
     *
     * @param int $courseid The course.
     * @return bool
     */
    public function draws_class(int $courseid): bool {
        if (!array_key_exists($courseid, $this->classmode)) {
            $this->classmode[$courseid] = $courseid > 0
                && classreport::requested()
                && classreport::may_view(context_course::instance($courseid));
        }

        return $this->classmode[$courseid];
    }

    /**
     * The report: the class report in class mode, core's breakdown of one learner otherwise.
     *
     * @param report $page Core's renderable.
     * @return string
     */
    public function render_report(report $page) {
        [$courseid, $moduleid] = classreport::renderable_ids($page);
        if (!$this->draws_class($courseid)) {
            return parent::render_report($page);
        }

        return $this->render_class_report($courseid, $moduleid);
    }

    /**
     * The class report of a course, with what the page must say above it.
     *
     * @param int $courseid The course.
     * @param int $moduleid A course module whose competencies are the columns, or 0.
     * @return string
     */
    protected function render_class_report(int $courseid, int $moduleid): string {
        $report = class_ratings::for_course(context_course::instance($courseid), $moduleid);
        $shown = $report->shown_competencies();
        $total = $report->total_competencies();
        $data = [
            'courseid' => $courseid,
            'nogroup' => $report->no_group(),
            'capped' => $total > $shown,
            'capnotice' => get_string('competency_classreport_cap', 'theme_dimensions', ['shown' => $shown, 'total' => $total]),
            'nocompetency' => $total === 0,
            'nocompetencynotice' => $moduleid > 0
                ? get_string('competency_classreport_nocompetencymodule', 'theme_dimensions')
                : get_string('competency_classreport_nocompetency', 'theme_dimensions'),
            'report' => $report->output(),
        ];

        return parent::render_from_template('theme_dimensions/competency_classreport', $data);
    }

    /**
     * Core's navigation of the report: in class mode with "All learners" chosen and the group selector on the class
     * report's address; on a breakdown, "All learners" offered first where the viewer may read the class.
     *
     * Choosing "All learners" sends core's module `user=0`, which is the class mode; core's activity select then keeps
     * `user=0` too, so an activity narrows the class report's columns.
     *
     * @param user_course_navigation $nav Core's renderable.
     * @return string
     */
    public function render_user_course_navigation(user_course_navigation $nav) {
        $data = $nav->export_for_template($this);
        $courseid = (int) $data->courseid;
        $classmode = $this->draws_class($courseid);
        $offered = $classmode || ($courseid > 0 && classreport::may_view(context_course::instance($courseid)));
        if (!empty($data->hasusers) && $classmode) {
            // Core lists the gradable users of its active group, where group 0 is everybody: the class report's own
            // group rule decides who is named here, so the selector never names a learner the report withholds.
            $admitted = array_flip(classreport::learner_ids(context_course::instance($courseid)));
            $data->users = array_values(array_filter(
                $data->users,
                static fn(\stdClass $user): bool => isset($admitted[(int) $user->id])
            ));
        }
        if (!empty($data->hasusers) && $offered) {
            foreach ($data->users as $user) {
                $user->selected = $user->selected && !$classmode;
            }
            array_unshift($data->users, (object) [
                'id' => 0,
                'fullname' => get_string('competency_classreport_alllearners', 'theme_dimensions'),
                'selected' => $classmode,
            ]);
        }
        if ($classmode) {
            $data->userid = 0;
            if ($data->groupselector !== '') {
                $course = get_course($courseid);
                $params = ['id' => $courseid];
                if ((int) $data->moduleid > 0) {
                    $params['mod'] = (int) $data->moduleid;
                }
                $data->groupselector = groups_allgroups_course_menu(
                    $course,
                    new \moodle_url('/report/competency/index.php', $params),
                    true,
                    groups_get_course_group($course, true)
                );
            }
        }

        return parent::render_from_template('report_competency/user_course_navigation', $data);
    }

    /**
     * The heading index.php prints for its learner: nothing in class mode, where that learner is only the first of
     * the list index.php picked; core's heading otherwise.
     *
     * @param mixed $headerinfo The heading's data.
     * @param int $headinglevel The heading's level.
     * @return string
     */
    public function context_header($headerinfo = null, $headinglevel = 1) {
        if ($this->draws_class((int) $this->page->course->id)) {
            return '';
        }

        return $this->output->context_header($headerinfo, $headinglevel);
    }
}
