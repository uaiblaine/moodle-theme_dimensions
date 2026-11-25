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
 * Theme Dimensions - Privacy provider
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 *             based on code by Daniel Poggenpohl and Alexander Bias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\privacy;

use core_privacy\local\metadata\collection;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\user_filter;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\user_preference_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use theme_dimensions\local\calendar\state as calendar_state;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\competency\classreport;
use theme_dimensions\local\competency\classreportpreference;
use theme_dimensions\local\competency\reportpersistents;
use theme_dimensions\local\flavourchoice;
use theme_dimensions\local\focusmode;
use theme_dimensions\output\category_hero;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\course_notice;

/**
 * Privacy Subsystem implementation for theme_dimensions.
 *
 * The theme owns seven pieces of personal data that are user preferences: the reading column
 * width a person chose in focus mode, the view (cards or list) they chose on a category page,
 * the organisation, view and filters they left each of their most recent category pages in,
 * whether they folded the header of the category pages, whether they folded the course
 * notice of the course pages, the Boost Union flavour they chose as their look, and the event
 * keys, category and filters they left the theme's calendar page in. Its one table holds the branding of
 * categories - a layout, two colours, a tagline, public texts - and its only user-linked column
 * is usermodified, the id of the user who last saved a category's branding, kept as an
 * authorship stamp. That stamp is personal data, so the provider is a request provider as well:
 * a data request exports, in each category context whose branding the person last saved, when
 * they saved it; a deletion request DETACHES them (usermodified becomes 0) and keeps the row,
 * because the branding is the category's institutional content and removing it would repaint a
 * category's page as a side effect of somebody leaving the site (the shape of
 * local_unlistedcourses' provider). usermodified is not in the cached payload
 * ({@see branding::CACHED_KEYS}), so a detach needs no cache purge.
 *
 * The columns and widths a reader chooses on the class report of one course are an eighth kind of preference, one
 * per course ({@see classreportpreference}), exported as stored; core deletes them with the account and the theme
 * with the course and on uninstall.
 *
 * The filter values a reader stores on the class report of competency ratings are rows core writes
 * (`reportbuilder_user_filter`) under the theme's system report, which core's own provider leaves out (it covers
 * custom reports only; the fleet's decision D146): they are the reader's data, found in their user context, exported
 * with the course each report is of, deleted on request and when the account is deleted
 * ({@see reportpersistents::user_deleted()}).
 *
 * Everything else the theme shows or writes about a person belongs to core's own subsystems,
 * which describe and delete it themselves: the courses they starred are core_course's own
 * favourite rows (component core_course, {@see \theme_dimensions\local\category\wishlist}), the
 * memberships of the look cohorts are core_cohort's (declared as a subsystem link,
 * {@see \theme_dimensions\local\lookcohorts}), and the landing page short links the theme mints
 * carry user id 0.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 *             based on code by Daniel Poggenpohl and Alexander Bias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    user_preference_provider {
    /**
     * Describe the user preferences this theme stores and the authorship stamp its table keeps.
     *
     * @param collection $collection The collection of metadata items to add to.
     * @return collection The same collection, with this theme's items added.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'theme_dimensions_cat',
            [
                'usermodified' => 'privacy:metadata:cat:usermodified',
                'timemodified' => 'privacy:metadata:cat:timemodified',
            ],
            'privacy:metadata:cat'
        );
        $collection->add_user_preference(
            focusmode::PREFERENCE_EXPANDED,
            'privacy:metadata:preference:focus_expanded'
        );
        $collection->add_user_preference(
            category_listing::PREFERENCE_VIEW,
            'privacy:metadata:preference:category_view'
        );
        $collection->add_user_preference(
            category_hero::PREFERENCE_FOLDED,
            'privacy:metadata:preference:category_herofolded'
        );
        $collection->add_user_preference(
            remembered::PREFERENCE,
            'privacy:metadata:preference:category_state'
        );
        $collection->add_user_preference(
            course_notice::PREFERENCE_COLLAPSED,
            'privacy:metadata:preference:coursenotice_collapsed'
        );
        $collection->add_user_preference(
            flavourchoice::PREFERENCE,
            'privacy:metadata:preference:flavour'
        );
        $collection->add_user_preference(
            calendar_state::PREFERENCE,
            'privacy:metadata:preference:calendar_state'
        );
        $collection->add_subsystem_link('core_cohort', [], 'privacy:metadata:core_cohort');
        $collection->add_database_table(user_filter::TABLE, [
            'reportid' => 'privacy:metadata:reportbuilder_user_filter:reportid',
            'filterdata' => 'privacy:metadata:reportbuilder_user_filter:filterdata',
            'usercreated' => 'privacy:metadata:reportbuilder_user_filter:usercreated',
            'usermodified' => 'privacy:metadata:reportbuilder_user_filter:usermodified',
            'timecreated' => 'privacy:metadata:reportbuilder_user_filter:timecreated',
            'timemodified' => 'privacy:metadata:reportbuilder_user_filter:timemodified',
        ], 'privacy:metadata:reportbuilder_user_filter');
        // One preference per course, theme_dimensions_classreport_<courseid>: declared under the name's stem.
        $collection->add_user_preference(
            rtrim(classreportpreference::PREFIX, '_'),
            'privacy:metadata:preference:classreport'
        );

        return $collection;
    }

    /**
     * The category contexts whose branding this person last saved, and their own user context while they keep filter
     * values on the theme's reports.
     *
     * @param int $userid The user id.
     * @return contextlist The contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {" . branding::TABLE . "} b ON b.categoryid = ctx.instanceid
                 WHERE ctx.contextlevel = :categorylevel
                   AND b.usermodified = :userid";
        $contextlist->add_from_sql($sql, ['categorylevel' => CONTEXT_COURSECAT, 'userid' => $userid]);

        [$isthemes, $params] = reportpersistents::is_themes('r');
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {" . user_filter::TABLE . "} f
               JOIN {" . report::TABLE . "} r ON r.id = f.reportid AND {$isthemes}
               JOIN {context} ctx ON ctx.instanceid = f.usercreated AND ctx.contextlevel = :userlevel
              WHERE f.usercreated = :userid",
            $params + ['userlevel' => CONTEXT_USER, 'userid' => $userid]
        );

        return $contextlist;
    }

    /**
     * The person who last saved the branding of this category, if the context is a category's.
     *
     * @param userlist $userlist The userlist to add to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            [$isthemes, $params] = reportpersistents::is_themes('r');
            $userlist->add_from_sql(
                'usercreated',
                'SELECT f.usercreated
                   FROM {' . user_filter::TABLE . '} f
                   JOIN {' . report::TABLE . "} r ON r.id = f.reportid AND {$isthemes}
                  WHERE f.usercreated = :subject",
                $params + ['subject' => (int) $context->instanceid]
            );
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            return;
        }
        $sql = "SELECT usermodified
                  FROM {" . branding::TABLE . "}
                 WHERE categoryid = :categoryid
                   AND usermodified > 0";
        $userlist->add_from_sql('usermodified', $sql, ['categoryid' => $context->instanceid]);
    }

    /**
     * Export, per approved category, that this person last saved its branding and when.
     *
     * Only the stamp is the person's: the branding itself (colours, texts, files) is the
     * category's content and is not exported as theirs.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                static::export_report_filters($context, $userid);
                continue;
            }
            if ($context->contextlevel != CONTEXT_COURSECAT) {
                continue;
            }
            $row = $DB->get_record(branding::TABLE, ['categoryid' => $context->instanceid, 'usermodified' => $userid]);
            if (!$row) {
                continue;
            }
            writer::with_context($context)->export_data(
                [get_string('privacy:path:branding', 'theme_dimensions')],
                (object) [
                    'usermodified' => transform::user((int) $row->usermodified),
                    'timemodified' => transform::datetime((int) $row->timemodified),
                ]
            );
        }
    }

    /**
     * Detach every person from the branding of this category, keeping the branding.
     *
     * @param \context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel == CONTEXT_USER) {
            reportpersistents::delete_user_filters((int) $context->instanceid);
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            return;
        }
        $DB->set_field(branding::TABLE, 'usermodified', 0, ['categoryid' => $context->instanceid]);
    }

    /**
     * Detach this person from the branding of the approved categories they last saved.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && (int) $context->instanceid === $userid) {
                reportpersistents::delete_user_filters($userid);
                continue;
            }
            if ($context->contextlevel != CONTEXT_COURSECAT) {
                continue;
            }
            $DB->set_field(branding::TABLE, 'usermodified', 0, [
                'categoryid' => $context->instanceid,
                'usermodified' => $userid,
            ]);
        }
    }

    /**
     * Detach the approved people from the branding of this category.
     *
     * @param approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if ($context->contextlevel == CONTEXT_USER) {
            if (in_array((int) $context->instanceid, array_map('intval', $userids), true)) {
                reportpersistents::delete_user_filters((int) $context->instanceid);
            }
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSECAT || !$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['categoryid'] = $context->instanceid;
        $DB->set_field_select(branding::TABLE, 'usermodified', 0, "categoryid = :categoryid AND usermodified $insql", $params);
    }

    /**
     * The person's stored filter values of the theme's reports, each with the course its report is of.
     *
     * A report persistent's context is whatever a client named to core's services, which create it before the
     * report's can_view(); so the course is named only where the subject could open its class report (a course
     * context where they hold the report's capability at course level), judged for the subject, never for the user
     * the export runs as.
     *
     * @param \context $context The person's own context.
     * @param int $userid The person.
     * @return void
     */
    protected static function export_report_filters(\context $context, int $userid): void {
        $entries = [];
        foreach (reportpersistents::user_filters($userid) as $record) {
            $course = '';
            $reportcontext = \context::instance_by_id((int) $record->contextid, IGNORE_MISSING);
            if ($reportcontext instanceof \context_course && has_capability(classreport::USERVIEW, $reportcontext, $userid)) {
                $course = $reportcontext->get_context_name(false, false, false);
            }
            $entries[] = (object) [
                'course' => $course,
                'filters' => json_decode((string) $record->filterdata),
                'timecreated' => transform::datetime((int) $record->timecreated),
                'timemodified' => transform::datetime((int) $record->timemodified),
            ];
        }
        if ($entries) {
            writer::with_context($context)->export_data(
                [get_string('privacy:path:reportfilters', 'theme_dimensions')],
                (object) ['filters' => $entries]
            );
        }
    }

    /**
     * Export the user preferences this theme stores for one person.
     *
     * @param int $userid The id of the user whose data is to be exported.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        global $DB;

        $expanded = get_user_preferences(focusmode::PREFERENCE_EXPANDED, null, $userid);
        if ($expanded !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                focusmode::PREFERENCE_EXPANDED,
                $expanded ? get_string('yes') : get_string('no'),
                get_string('privacy:metadata:preference:focus_expanded', 'theme_dimensions')
            );
        }

        $view = get_user_preferences(category_listing::PREFERENCE_VIEW, null, $userid);
        if ($view !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                category_listing::PREFERENCE_VIEW,
                $view,
                get_string('privacy:metadata:preference:category_view', 'theme_dimensions')
            );
        }

        /* The stored value as it is - a JSON object keyed by category id, each entry naming the
           organisation, the view and the filters (see remembered) - because the export is of what
           is stored, not of what the page would still accept of it. */
        $state = get_user_preferences(remembered::PREFERENCE, null, $userid);
        if ($state !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                remembered::PREFERENCE,
                $state,
                get_string('privacy:metadata:preference:category_state', 'theme_dimensions')
            );
        }

        $folded = get_user_preferences(category_hero::PREFERENCE_FOLDED, null, $userid);
        if ($folded !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                category_hero::PREFERENCE_FOLDED,
                $folded ? get_string('yes') : get_string('no'),
                get_string('privacy:metadata:preference:category_herofolded', 'theme_dimensions')
            );
        }

        // The stored JSON, as the category state is exported: what the person left the calendar in, whole.
        $calendar = get_user_preferences(calendar_state::PREFERENCE, null, $userid);
        if ($calendar !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                calendar_state::PREFERENCE,
                (string) $calendar,
                get_string('privacy:metadata:preference:calendar_state', 'theme_dimensions')
            );
        }

        $collapsed = get_user_preferences(course_notice::PREFERENCE_COLLAPSED, null, $userid);
        if ($collapsed !== null) {
            writer::export_user_preference(
                'theme_dimensions',
                course_notice::PREFERENCE_COLLAPSED,
                $collapsed ? get_string('yes') : get_string('no'),
                get_string('privacy:metadata:preference:coursenotice_collapsed', 'theme_dimensions')
            );
        }

        /* The class report's columns, one preference per course, exported as stored (the competency ids in order and
           the widths, JSON) under the preference's own name, which carries the course id. */
        foreach (classreportpreference::stored_for_user($userid) as $courseid => $value) {
            writer::export_user_preference(
                'theme_dimensions',
                classreportpreference::name($courseid),
                $value,
                get_string('privacy:metadata:preference:classreport', 'theme_dimensions')
            );
        }

        /* The flavour's title beside its id, because an id alone tells the person nothing; a flavour
           deleted since is exported by its id, which is all that is left of it. */
        $flavourid = get_user_preferences(flavourchoice::PREFERENCE, null, $userid);
        if ($flavourid !== null) {
            $flavourid = (int) $flavourid;
            if ($flavourid === flavourchoice::SITE_DEFAULT) {
                $value = get_string('flavoursitedefault', 'theme_dimensions');
            } else {
                $title = $DB->get_field('theme_boost_union_flavours', 'title', ['id' => $flavourid]);
                if ($title === false) {
                    $value = (string) $flavourid;
                } else {
                    // Plain text: the export is data, and an escaped title would read "Campus &amp; Co" there.
                    $options = ['context' => \context_system::instance(), 'escape' => false];
                    $value = format_string($title, true, $options) . ' (' . $flavourid . ')';
                }
            }
            writer::export_user_preference(
                'theme_dimensions',
                flavourchoice::PREFERENCE,
                $value,
                get_string('privacy:metadata:preference:flavour', 'theme_dimensions')
            );
        }
    }
}
