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
 * Theme Dimensions - The shell of the class competency report's React view
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use context_course;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\local\competency\classreportengine;
use theme_dimensions\reportbuilder\local\systemreports\class_ratings;

/**
 * The class report as a data report (phase 6 part two): the shell report/competency/index.php renders in class mode.
 *
 * One React mount (core's `{{#react}}`, react_autoinit) whose props carry configuration and strings only; every
 * learner and every figure arrives through the theme's web services (theme_dimensions_class_report_rows and
 * _summary), which read the same system report ({@see classreportengine}). Nothing here may put a record into the
 * props: they are in the page for anyone who reads its source, whatever the component draws. The page carries core's
 * own rendering of the report inside a noscript element instead, without its filter form (a dynamic form that does
 * nothing without JavaScript); `?view=table` ({@see table_requested()}) renders core's table whole, as part one
 * does. Copied from local_evidencehub's hub_report_view and adapted.
 *
 * The props name the group the page resolved (index.php's groups_get_course_group() with the selector's update), and
 * the client posts it on every read, so a request never reads the session's choice of another tab. The theme mounts
 * through core's `{{#react}}` like local_evidencehub, not through an AMD entry like the category listing: the view is
 * one component with props and no server-drawn rows to adopt, which is what core's mount exists for.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class class_report_view implements renderable, templatable {
    /** @var string The request parameter that asks for core's table instead of the view. */
    public const PARAM_VIEW = 'view';

    /** @var string The one value of {@see PARAM_VIEW} that is not the view: core's table. */
    public const VIEW_TABLE = 'table';

    /**
     * @var string The component core's react_autoinit mounts (js/esm/src/classreport/ClassReportView.tsx); the template
     *     names the same literal.
     */
    public const COMPONENT = '@moodle/lms/theme_dimensions/classreport/ClassReportView';

    /** @var context_course The course's context. */
    private $context;

    /** @var int The activity whose competencies are the columns, or 0. */
    private $moduleid;

    /** @var class_ratings The report, as the page built it (the session's group, no search). */
    private $report;

    /**
     * The shell of a course's class report.
     *
     * @param context_course $context The course's context.
     * @param int $moduleid The activity whose competencies are the columns, or 0.
     * @param class_ratings $report The report of that course and activity, as the page built it.
     */
    public function __construct(context_course $context, int $moduleid, class_ratings $report) {
        $this->context = $context;
        $this->moduleid = max(0, $moduleid);
        $this->report = $report;
    }

    /**
     * Whether the request asks for core's table: `view=table`, and nothing else. Any other value, or none, is the view.
     *
     * @return bool
     */
    public static function table_requested(): bool {
        return optional_param(self::PARAM_VIEW, '', PARAM_ALPHA) === self::VIEW_TABLE;
    }

    /**
     * The group the page resolved for the course: index.php has already read the selector's choice into the session
     * (groups_get_course_group() with the update); 0 for all participants and for a course without groups.
     *
     * @param \stdClass $course The course.
     * @return int
     */
    public static function page_group(\stdClass $course): int {
        return max(0, (int) groups_get_course_group($course));
    }

    /**
     * Core's table of the class report: index.php with view=table, the activity kept.
     *
     * @return \moodle_url
     */
    public function table_url(): \moodle_url {
        $params = ['id' => $this->context->instanceid];
        if ($this->moduleid > 0) {
            $params['mod'] = $this->moduleid;
        }
        $params[self::PARAM_VIEW] = self::VIEW_TABLE;

        return new \moodle_url('/report/competency/index.php', $params);
    }

    /**
     * The component's props: configuration and strings, never a record.
     *
     * @return array The props; see js/esm/src/classreport/types.ts, ViewProps.
     */
    public function props(): array {
        $reportid = (int) $this->report->get_report_persistent()->get('id');

        return [
            'courseid' => (int) $this->context->instanceid,
            'moduleid' => $this->moduleid,
            'groupid' => self::page_group(get_course((int) $this->context->instanceid)),
            'reportid' => $reportid,
            'perpage' => classreportengine::DEFAULT_PERPAGE,
            'perpages' => classreportengine::PERPAGES,
            'searchmin' => classreport::SEARCH_MIN,
            'maxcolumns' => classreport::MAX_COLUMNS,
            'tableurl' => $this->table_url()->out(false),
            'downloadurl' => (new \moodle_url('/reportbuilder/download.php', ['id' => $reportid]))->out(false),
            'formats' => self::formats(),
            'labels' => self::labels(),
        ];
    }

    /**
     * The download formats the site has enabled, for the bar's Download menu: each a link to core's download.php,
     * which accepts only an enabled format.
     *
     * @return array Each: value (the dataformat's name), label (its plain name).
     */
    public static function formats(): array {
        $formats = [];
        foreach (array_keys(\core\plugininfo\dataformat::get_enabled_plugins()) as $name) {
            $formats[] = ['value' => (string) $name, 'label' => get_string('dataformat', 'dataformat_' . $name)];
        }

        return $formats;
    }

    /**
     * The strings the component draws, by the name it reads them under, in the plain spelling.
     *
     * A literal per string, so tools that check string ids find every one of them. Strings that take a value arrive
     * with their placeholder, which the client fills (js/esm/src/datareport/str.ts). The names the shared parts read
     * (datareport/) are the reference's, so a later sync with it is a diff.
     *
     * @return array Name => string.
     */
    public static function labels(): array {
        return [
            'always' => get_string('competency_classreport_always', 'theme_dimensions'),
            'any' => get_string('competency_classreport_any', 'theme_dimensions'),
            'bandlabel' => get_string('competency_classreport_bandlabel', 'theme_dimensions'),
            'bycompetency' => get_string('competency_classreport_bycompetency', 'theme_dimensions'),
            'caption' => get_string('competency_classreport_caption', 'theme_dimensions'),
            'cardline' => get_string('competency_classreport_cardline', 'theme_dimensions'),
            'cards' => get_string('competency_classreport_cards', 'theme_dimensions'),
            'clearall' => get_string('competency_classreport_clearall', 'theme_dimensions'),
            'clearfilters' => get_string('competency_classreport_clearfilters', 'theme_dimensions'),
            'cleartext' => get_string('competency_classreport_cleartext', 'theme_dimensions'),
            'columnlimit' => get_string('competency_classreport_columnlimit', 'theme_dimensions'),
            'columns' => get_string('competency_classreport_columns', 'theme_dimensions'),
            'coretable' => get_string('competency_classreport_tableview', 'theme_dimensions'),
            'count' => get_string('competency_classreport_count', 'theme_dimensions'),
            'count_one' => get_string('competency_classreport_count_one', 'theme_dimensions'),
            'defaultcolumns' => get_string('competency_classreport_defaultcolumns', 'theme_dimensions'),
            'download' => get_string('competency_classreport_download', 'theme_dimensions'),
            'empty' => get_string('competency_classreport_empty', 'theme_dimensions'),
            'filterchip' => get_string('competency_classreport_filterchip', 'theme_dimensions'),
            'filters' => get_string('competency_classreport_filters', 'theme_dimensions'),
            'filterson' => get_string('competency_classreport_filterson', 'theme_dimensions'),
            'grid' => get_string('competency_classreport_grid', 'theme_dimensions'),
            'learner' => get_string('competency_classreport_learner', 'theme_dimensions'),
            'learners' => get_string('competency_classreport_learners', 'theme_dimensions'),
            'learnerssub' => get_string('competency_classreport_learnerssub', 'theme_dimensions'),
            'learnerssub_one' => get_string('competency_classreport_learnerssub_one', 'theme_dimensions'),
            'less' => get_string('competency_classreport_less', 'theme_dimensions'),
            'loadband' => get_string('competency_classreport_loadband', 'theme_dimensions'),
            'loaderror' => get_string('competency_classreport_loaderror', 'theme_dimensions'),
            'loading' => get_string('competency_classreport_loading', 'theme_dimensions'),
            'meterlabel' => get_string('competency_classreport_meterlabel', 'theme_dimensions'),
            'more' => get_string('competency_classreport_more', 'theme_dimensions'),
            'morefilters' => get_string('competency_classreport_morefilters', 'theme_dimensions'),
            'morefiltershelp' => get_string('competency_classreport_morefiltershelp', 'theme_dimensions'),
            'moreoptions' => get_string('competency_classreport_moreoptions', 'theme_dimensions'),
            'next' => get_string('competency_classreport_next', 'theme_dimensions'),
            'nocompetencies' => get_string('competency_classreport_nocompetencies', 'theme_dimensions'),
            'nofacets' => get_string('competency_classreport_nofacets', 'theme_dimensions'),
            'nomatch' => get_string('competency_classreport_nomatch', 'theme_dimensions'),
            'nomatchhelp' => get_string('competency_classreport_nomatchhelp', 'theme_dimensions'),
            'nooptionmatch' => get_string('competency_classreport_nooptionmatch', 'theme_dimensions'),
            'ofcells' => get_string('competency_classreport_ofcells', 'theme_dimensions'),
            'pageof' => get_string('competency_classreport_pageof', 'theme_dimensions'),
            'pages' => get_string('competency_classreport_pages', 'theme_dimensions'),
            'percent' => get_string('competency_classreport_percent', 'theme_dimensions'),
            'perpage' => get_string('competency_classreport_perpage', 'theme_dimensions'),
            'previous' => get_string('competency_classreport_previous', 'theme_dimensions'),
            'proficientof' => get_string('competency_classreport_proficientof', 'theme_dimensions'),
            'proficienttile' => get_string('competency_classreport_proficienttile', 'theme_dimensions'),
            'range' => get_string('competency_classreport_range', 'theme_dimensions'),
            'ratingsgiven' => get_string('competency_classreport_ratingsgiven', 'theme_dimensions'),
            'removefilter' => get_string('competency_classreport_removefilter', 'theme_dimensions'),
            'resetwidths' => get_string('competency_classreport_resetwidths', 'theme_dimensions'),
            'resize' => get_string('competency_classreport_resize', 'theme_dimensions'),
            'retry' => get_string('competency_classreport_retry', 'theme_dimensions'),
            'scrollhint' => get_string('competency_classreport_scrollhint', 'theme_dimensions'),
            'search' => get_string('competency_classreport_search', 'theme_dimensions'),
            'searchchip' => get_string('competency_classreport_searchchip', 'theme_dimensions'),
            'searchoptions' => get_string('competency_classreport_searchoptions', 'theme_dimensions'),
            'show' => get_string('competency_classreport_show', 'theme_dimensions'),
            'showcount' => get_string('competency_classreport_showcount', 'theme_dimensions'),
            'table' => get_string('competency_classreport_table', 'theme_dimensions'),
            'unratedsub' => get_string('competency_classreport_unratedsub', 'theme_dimensions'),
            'unratedtile' => get_string('competency_classreport_unratedtile', 'theme_dimensions'),
            'view' => get_string('competency_classreport_view', 'theme_dimensions'),
            'viewauto' => get_string('competency_classreport_viewauto', 'theme_dimensions'),
            'viewautohint' => get_string('competency_classreport_viewautohint', 'theme_dimensions'),
        ];
    }

    /**
     * Export the shell.
     *
     * @param renderer_base $output The renderer.
     * @return array courseid, the notices part one prints, props (JSON), tableurl, noscript (core's
     *     rendering of the report).
     */
    public function export_for_template(renderer_base $output): array {
        // Without JavaScript core's filter form is a dynamic form that cannot submit, and its script, which the
        // template registers on the page whatever the browser does with a noscript, would look up a form a
        // scripting browser never builds.
        $this->report->set_filter_form_default(false);
        $total = $this->report->total_competencies();

        return [
            'courseid' => (int) $this->context->instanceid,
            'nogroup' => $this->report->no_group(),
            'nocompetency' => $total === 0,
            'nocompetencynotice' => $this->moduleid > 0
                ? get_string('competency_classreport_nocompetencymodule', 'theme_dimensions')
                : get_string('competency_classreport_nocompetency', 'theme_dimensions'),
            'props' => json_encode($this->props(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE),
            'tableurl' => $this->table_url()->out(false),
            'noscript' => $this->report->output(),
        ];
    }
}
