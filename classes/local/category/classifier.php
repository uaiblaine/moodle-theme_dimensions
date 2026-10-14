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
 * Theme Dimensions - The enrolment state of every course on a listing
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\access;

/**
 * Sorts every course of a listing into one of eleven states, in five statements, never per course.
 *
 * The states are what the card prints and what the listing groups by. The first six are the
 * viewer's relationship with the course, read from their own enrolment rows by
 * local_unlistedcourses' {@see access::classify_enrolment()}: enrolled, enrolled from a later
 * date, an application pending, on its waiting list, suspended, ended. A course the viewer holds
 * none of these with gets the next action instead: open to this viewer, free (guest access), guest
 * access behind a key, conditional (enrol_coursecompleted will enrol them on completing another
 * course), or nothing on offer.
 *
 * The next action is an approximation that may only err towards "open". The authoritative answer
 * is {@see \theme_dimensions\local\hotsite\resolver}, which the card links to and which
 * asks each enrol plugin its own question, one course at a time and with queries per instance; a
 * listing of thousands cannot pay that, so this class reproduces the SQL-expressible half of each
 * plugin's rule from a handful of bulk statements and leaves the rest to the page the card opens.
 * A course this class calls open may turn out closed on that page; a course it calls closed never
 * turns out open. The superset test (classifier_test) runs both over one fixture and holds that
 * direction.
 *
 * Why not {@see access::get_next_actions()}, the plugin's own batch: it takes the course ids in
 * one IN list and says to pass a page of courses, never a population, and this class classifies
 * the whole population before any page is cut, for the chips' counts and the grouped order. So
 * the population is classified here, driven by the categories, and the page's cards ask the
 * plugin's batch for the routes and the opening dates they print ({@see cards::next_actions()});
 * the superset test also holds this class against that batch.
 *
 * The statements, and what drives each one - a bounded list, never the
 * population's course ids, because get_in_or_equal() does not chunk:
 *
 * 1. the viewer's own {user_enrolments} joined to {enrol} and {course}, driven
 *    by ue.userid alone ({@see enrolments()}); the intersection with the
 *    subtree happens in PHP. Read first, because the population's core gate
 *    needs it too;
 * 2. the enabled instances of the site-enabled plugins under the admitted
 *    categories, driven by c.category IN (...);
 * 3. one grouped count of the places taken on every capped instance under the
 *    same categories;
 * 4. the viewer's cohort ids, driven by userid;
 * 5. the viewer's favourite courses, driven by userid - resolved here, over
 *    the whole population and before any page is cut, so that the favourites
 *    chip and the star are one statement and the chip can filter.
 *
 * Which of each plugin's checks are reproduced, and which are dropped
 * (dropped means: the classifier says open where the plugin might say no,
 * which is the permitted direction). The methods named are the ones this
 * class must agree with; re-read them on each upgrade of core or of enrol_apply.
 *
 * - enrol_self ({@see \enrol_self_plugin::is_self_enrol_available()},
 *   {@see \enrol_self_plugin::can_self_enrol()}): instance enabled, the
 *   enrolment window, customint6 (new enrolments allowed), customint3 (the
 *   places cap against a count of user_enrolments), customint5 (the cohort,
 *   which the viewer must belong to; a deleted cohort answers no because the
 *   viewer belongs to nothing by that id), and "already holds a row on this
 *   instance" (any status). Dropped: the enrol/self:enrolself capability.
 * - enrol_apply ({@see \enrol_apply_plugin::allow_apply()},
 *   {@see \enrol_apply_plugin::is_full()} over
 *   {@see \enrol_apply\local\capacity::applications_closed()}): enabled,
 *   customint6, the window, customint5 with its -1 fail-closed sentinel, the
 *   cap against a count of the non-expired rows of every status, and an
 *   existing row meaning an application is pending.
 * - enrol_fee (its enrol_page_hook(), mirrored by the resolver's
 *   resolve_fee()): the window, a cost above zero on the instance or in the
 *   plugin's config, no existing row.
 * - enrol_paypal ({@see \enrol_paypal_plugin::enrol_page_hook()}): the same
 *   three checks as enrol_fee, with the plugin's own default cost.
 * - enrol_autoenrol (\enrol_autoenrol_plugin::enrol_allowed(), which the
 *   resolver asks): instance enabled, the window, customint4 (new enrolments
 *   allowed) and "already holds a row on this instance". Dropped: the
 *   customtext2 rule (availability conditions judged per user), the
 *   customint5 cap, and customint8's refusal of somebody who holds a row of
 *   any status by another method.
 * - enrol_guest ({@see \enrol_guest_plugin::try_guestaccess()}): an enabled
 *   instance with no password is free access; the plugin has no window and no
 *   cap. One with a password lets nobody straight in - the key is asked on the
 *   enrolment page - so it reads key, whose card points there.
 * - enrol_coursecompleted (access::get_next_action()'s rule for it, which the
 *   plugin's batch shares): an enabled instance inside its window, on which the
 *   viewer holds no row, for a viewer actively enrolled in the course customint1
 *   names - read from the viewer's own enrolments, which statement 1 already
 *   holds. Anyone else is told nothing about it: naming the prerequisite to them
 *   would defeat the unlisting.
 *
 * The one grouped count serves both capped plugins and uses enrol_apply's
 * shape - rows whose timeend is 0 or in the future - because the two shapes
 * differ and only one direction of error is allowed: enrol_self counts every
 * row, expired ones included, so counting fewer than it does can only make a
 * full self instance look open, never an open one look full.
 *
 * A guest is a viewer who can never enrol (can_self_enrol() refuses guests
 * outright and the resolver returns its visitor state before asking any
 * plugin), so for a guest nothing is ever open or conditional; guest access
 * reads free or key for them as for anybody, because the resolver offers the
 * guest account the guest method, keyed or not.
 *
 * The states that are no relationship answer the chips in a coarser vocabulary
 * ({@see chip()}): the toolbar's chips are the six it always had, so a
 * waiting-list application counts under "under review", guest access behind a
 * key under "open for enrolment", and suspended, ended and conditional under
 * none, which has no chip.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classifier {
    /** @var string The viewer holds an active enrolment. */
    public const STATE_ENROLLED = 'enrolled';

    /** @var string The viewer is enrolled from a later date: the course is theirs, but not yet open to them. */
    public const STATE_SCHEDULED = 'scheduled';

    /** @var string The viewer has applied and is waiting for a decision. */
    public const STATE_PENDING = 'pending';

    /** @var string The viewer's application is on enrol_apply's waiting list. */
    public const STATE_WAITLISTED = 'waitlisted';

    /** @var string The viewer's enrolment is suspended and has not ended. */
    public const STATE_SUSPENDED = 'suspended';

    /** @var string The viewer's enrolment has ended: its end date has passed. */
    public const STATE_EXPIRED = 'expired';

    /** @var string At least one enrolment method would take the viewer right now. */
    public const STATE_OPEN = 'open';

    /** @var string Guest access with no password is enabled, and nothing better is on offer. */
    public const STATE_FREE = 'free';

    /** @var string Guest access behind a key is enabled, and nothing better is on offer. */
    public const STATE_KEY = 'key';

    /** @var string The viewer will be enrolled on completing another course, and nothing better is on offer. */
    public const STATE_CONDITIONAL = 'conditional';

    /** @var string Nothing this viewer could use. Shown, with less emphasis. */
    public const STATE_NONE = 'none';

    /** @var string The favourites chip - not a state, but a filter the same control offers. */
    public const CHIP_FAVOURITES = 'favourites';

    /**
     * @var array The group each state sorts into: the viewer's own courses, then what they can join, then the rest.
     *
     * The group follows the chip ({@see chip()}): a state counted under another state's chip sorts with it.
     */
    public const RANK = [
        self::STATE_ENROLLED => 0,
        self::STATE_SCHEDULED => 0,
        self::STATE_PENDING => 0,
        self::STATE_WAITLISTED => 0,
        self::STATE_OPEN => 1,
        self::STATE_FREE => 1,
        self::STATE_KEY => 1,
        self::STATE_SUSPENDED => 2,
        self::STATE_EXPIRED => 2,
        self::STATE_CONDITIONAL => 2,
        self::STATE_NONE => 2,
    ];

    /**
     * @var string[] The relationships whose card may also print a route: the viewer holds a row that is not active.
     *
     * Decision D2 of the enrolment matrix: a method that would really take the viewer now is shown beside the row,
     * which is core's default. The routes themselves come from local_unlistedcourses' batch ({@see cards::next_actions()}).
     */
    public const ROUTE_STATES = [
        self::STATE_SCHEDULED,
        self::STATE_PENDING,
        self::STATE_WAITLISTED,
        self::STATE_SUSPENDED,
        self::STATE_EXPIRED,
    ];

    /** @var array The chip each state counts under: the six chip values, see the class docblock. */
    private const CHIP = [
        self::STATE_ENROLLED => self::STATE_ENROLLED,
        self::STATE_SCHEDULED => self::STATE_SCHEDULED,
        self::STATE_PENDING => self::STATE_PENDING,
        self::STATE_WAITLISTED => self::STATE_PENDING,
        self::STATE_OPEN => self::STATE_OPEN,
        self::STATE_FREE => self::STATE_FREE,
        self::STATE_KEY => self::STATE_OPEN,
        self::STATE_SUSPENDED => self::STATE_NONE,
        self::STATE_EXPIRED => self::STATE_NONE,
        self::STATE_CONDITIONAL => self::STATE_NONE,
        self::STATE_NONE => self::STATE_NONE,
    ];

    /**
     * @var array The rank of each relationship type of local_unlistedcourses: when several rows exist the highest wins.
     *
     * The order {@see access::get_enrolment_state()} documents and ranks by; keep the two in step.
     */
    private const RELATIONSHIP_RANK = [
        access::RELATIONSHIP_ENROLLED => 6,
        access::RELATIONSHIP_SCHEDULED => 5,
        access::RELATIONSHIP_PENDING => 4,
        access::RELATIONSHIP_WAITLISTED => 3,
        access::RELATIONSHIP_SUSPENDED => 2,
        access::RELATIONSHIP_EXPIRED => 1,
        access::RELATIONSHIP_NONE => 0,
    ];

    /** @var string[] The enrol plugins this class has a rule for. Anything else is ignored. */
    private const PLUGINS = ['self', 'guest', 'fee', 'apply', 'paypal', 'autoenrol', 'coursecompleted'];

    /** @var array How good an offer is: open beats free beats key beats conditional beats nothing. */
    private const OFFER_RANK = [
        self::STATE_NONE => 0,
        self::STATE_CONDITIONAL => 1,
        self::STATE_KEY => 2,
        self::STATE_FREE => 3,
        self::STATE_OPEN => 4,
    ];

    /**
     * The eleven states, in display order: the relationships, then the offers.
     *
     * @return string[]
     */
    public static function states(): array {
        return [
            self::STATE_ENROLLED,
            self::STATE_SCHEDULED,
            self::STATE_PENDING,
            self::STATE_WAITLISTED,
            self::STATE_SUSPENDED,
            self::STATE_EXPIRED,
            self::STATE_OPEN,
            self::STATE_FREE,
            self::STATE_KEY,
            self::STATE_CONDITIONAL,
            self::STATE_NONE,
        ];
    }

    /**
     * The six values a state chip counts and filters by, in display order.
     *
     * @return string[]
     */
    public static function chips(): array {
        return array_values(array_unique(self::CHIP));
    }

    /**
     * The chip a state counts under, and which the state chip's filter keeps it by.
     *
     * @param string $state One of the STATE_* constants.
     * @return string One of {@see chips()}; none for a state this class does not know.
     */
    public static function chip(string $state): string {
        return self::CHIP[$state] ?? self::STATE_NONE;
    }

    /**
     * Every value the state chip may carry: the six chip values and the favourites chip.
     *
     * @return string[]
     */
    public static function filters(): array {
        return array_merge(self::chips(), [self::CHIP_FAVOURITES]);
    }

    /**
     * The sort group of a state.
     *
     * @param string $state One of the STATE_* constants.
     * @return int
     */
    public static function rank(string $state): int {
        return self::RANK[$state] ?? self::RANK[self::STATE_NONE];
    }

    /**
     * Every enrolment row this user holds, keyed by course, in one statement driven by the user id.
     *
     * A user holds tens of enrolments; a subtree holds thousands of courses.
     * So the statement never mentions the subtree, and the caller intersects
     * in PHP. Rides the userid foreign key of {user_enrolments}.
     *
     * Every row is judged by local_unlistedcourses' access::classify_enrolment(), the one owner of
     * the rule, so the population's clamp rescue and the plugin's primed memo cannot disagree.
     * "Active" is what is_enrolled($context, $user, '', true) means: the row active, the
     * instance enabled, and now inside the row's window. "Scheduled" is an active row on an
     * enabled instance whose start date is still ahead: core does not call the user enrolled
     * yet, but an administrator decided they take part. "Pending" is an application awaiting a
     * decision by enrol_apply's own queue rule: a row that is not active, on an apply instance,
     * whose end date is unset or still ahead. It reads neither the start date nor the instance
     * status, so a waiting-list row (status 2) sets it too: for the rescue a waiting-list
     * application is as much the viewer's as any other. "Inactive" is a relationship that is
     * not active yet - scheduled or pending. A suspended or expired row, an application whose
     * end date has passed, or a row on a disabled instance, sets none of these flags: those
     * keep no unlisted course discoverable (the plugin's own allow-list), so they rescue nothing.
     *
     * The flags are the rescue's; the card reads `type`, the best relationship over the rows by
     * the plugin's own rank, which keeps the waiting list, a suspension and an end apart.
     *
     * @param int $userid The viewer. 0 or a guest returns nothing without a statement.
     * @return \stdClass[] courseid => {active, scheduled, inactive, pending, type, startsat, endsat,
     *         endedat, category, instances: enrolid => true}; type is a RELATIONSHIP_* value of
     *         local_unlistedcourses; startsat and endsat are the earliest scheduled row's dates, 0
     *         when there is none; endedat is the latest end date of an expired row, 0 when there is none.
     */
    public static function enrolments(int $userid): array {
        global $DB;

        if ($userid <= 0 || isguestuser($userid)) {
            return [];
        }
        $rows = $DB->get_records_sql(
            "SELECT ue.id, ue.enrolid, ue.status, ue.timestart, ue.timeend,
                    e.courseid, e.enrol, e.status AS instancestatus, c.category
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
              WHERE ue.userid = :userid",
            ['userid' => $userid]
        );
        $now = time();
        $map = [];
        foreach ($rows as $row) {
            $courseid = (int) $row->courseid;
            if (!isset($map[$courseid])) {
                $map[$courseid] = (object) [
                    'active' => false,
                    'scheduled' => false,
                    'inactive' => false,
                    'pending' => false,
                    'type' => access::RELATIONSHIP_NONE,
                    'startsat' => 0,
                    'endsat' => 0,
                    'endedat' => 0,
                    'category' => (int) $row->category,
                    'instances' => [],
                ];
            }
            $map[$courseid]->instances[(int) $row->enrolid] = true;
            self::record_relationship($map[$courseid], access::classify_enrolment($row, $now));
        }
        return $map;
    }

    /**
     * Fold one classified enrolment row into its course's entry of the enrolments map.
     *
     * Two halves. The flags are the population's rescue: active for an active row; scheduled and
     * inactive for a row that starts later; pending and inactive for an application awaiting a
     * decision, on the waiting list or not. The type is the card's: the best relationship over
     * the rows by the plugin's rank, with the dates of its own row - between two scheduled rows
     * the earlier start, between two ended ones the later end, as
     * {@see access::get_enrolment_state()} picks them.
     *
     * @param \stdClass $entry The course's entry: active, scheduled, inactive, pending, type, startsat, endsat, endedat.
     * @param array $state What access::classify_enrolment() answered: type, startsat, endsat.
     */
    private static function record_relationship(\stdClass $entry, array $state): void {
        $type = self::theme_relationship((string) $state['type']);
        if ($type === access::RELATIONSHIP_ENROLLED) {
            $entry->active = true;
        } else if ($type === access::RELATIONSHIP_SCHEDULED) {
            $entry->scheduled = true;
            $entry->inactive = true;
            if (!$entry->startsat || $state['startsat'] < $entry->startsat) {
                $entry->startsat = $state['startsat'];
                $entry->endsat = $state['endsat'];
            }
        } else if ($type === access::RELATIONSHIP_PENDING || $type === access::RELATIONSHIP_WAITLISTED) {
            $entry->pending = true;
            $entry->inactive = true;
        } else if ($type === access::RELATIONSHIP_EXPIRED && (int) $state['endsat'] > $entry->endedat) {
            $entry->endedat = (int) $state['endsat'];
        }
        if (self::RELATIONSHIP_RANK[$type] > self::RELATIONSHIP_RANK[$entry->type]) {
            $entry->type = $type;
        }
    }

    /**
     * Read a relationship type of local_unlistedcourses, every type this theme does not know as none.
     *
     * The plugin answers enrolled, scheduled, pending, waitlisted, suspended, expired and none.
     * A type a later release of the plugin adds reads none here - no label, no flag, no rescue -
     * until the card is taught it.
     *
     * @param string $type A RELATIONSHIP_* value of local_unlistedcourses.
     * @return string The same value when this theme knows it, else access::RELATIONSHIP_NONE.
     */
    private static function theme_relationship(string $type): string {
        return isset(self::RELATIONSHIP_RANK[$type]) ? $type : access::RELATIONSHIP_NONE;
    }

    /**
     * Classify these courses for the viewer of the scope: four statements, none driven by course ids.
     *
     * The relationship decides first, before any instance is read: a viewer who holds a row with
     * the course is told about that row, and the routes beside it are the card's own line
     * ({@see cards::next_actions()}), never a state that would hide the row. Only a viewer related to
     * the course by nothing is classified by what its instances offer.
     *
     * `opening` says only whom the card asks for an opening date, never the date: it is set on a
     * course with nothing on offer that carries an instance whose enrolment window starts later,
     * which is every instance local_unlistedcourses' batch could date ({@see access::get_next_actions()},
     * `opens`), so a page whose cards could print no date never asks it. It errs towards asking:
     * whether that window is the only refusal is the batch's rule, never this class's.
     *
     * @param int[] $courseids The courses to classify - the population, before any page is cut.
     * @param scope $scope The admitted subtree, carrying the viewer and their enrolments.
     * @return array courseid => ['state' => STATE_*, 'favourite' => bool, 'startsat' => int, 'endsat' => int,
     *         'prerequisiteid' => int, 'opening' => bool], for every id given; the two dates are the scheduled
     *         enrolment's, endsat alone the end of an expired one, 0 for any other state; prerequisiteid is the
     *         course a conditional one waits for, 0 for any other state; opening is false on any state but none.
     */
    public static function classify(array $courseids, scope $scope): array {
        global $CFG, $DB;

        $courseids = array_map('intval', $courseids);
        $verdicts = [];
        if (!$courseids) {
            return $verdicts;
        }
        $canenrol = $scope->viewerid > 0 && !isguestuser($scope->viewerid);

        // 2. The enabled instances of the enabled plugins under the admitted categories.
        $enabled = array_filter(explode(',', (string) ($CFG->enrol_plugins_enabled ?? '')));
        $plugins = array_values(array_intersect(self::PLUGINS, $enabled));
        $instances = [];
        $capped = false;
        if ($plugins) {
            [$catsql, $catparams] = $DB->get_in_or_equal($scope->population_categories(), SQL_PARAMS_NAMED, 'cat');
            [$plsql, $plparams] = $DB->get_in_or_equal($plugins, SQL_PARAMS_NAMED, 'pl');
            /* Rides the courseid foreign key of {enrol} through the category index of {course}. In the
               course's own order of methods, so the conditional instance reported is the first one, as
               the plugin's summary reports it. */
            $rows = $DB->get_records_sql(
                "SELECT e.id, e.courseid, e.enrol, e.enrolstartdate, e.enrolenddate, e.password,
                        e.customint1, e.customint3, e.customint4, e.customint5, e.customint6, e.cost, e.currency
                   FROM {enrol} e
                   JOIN {course} c ON c.id = e.courseid
                  WHERE c.category {$catsql} AND e.status = :enabled AND e.enrol {$plsql}
               ORDER BY e.sortorder, e.id",
                $catparams + $plparams + ['enabled' => ENROL_INSTANCE_ENABLED]
            );
            foreach ($rows as $row) {
                $instances[(int) $row->courseid][] = $row;
                if ((int) $row->customint3 > 0 && in_array($row->enrol, ['self', 'apply'], true)) {
                    $capped = true;
                }
            }
        }

        // 3. The places taken on every capped instance, one grouped count (enrol_apply's shape, see the class docblock).
        $taken = [];
        if ($capped) {
            [$catsql, $catparams] = $DB->get_in_or_equal($scope->population_categories(), SQL_PARAMS_NAMED, 'cap');
            $taken = $DB->get_records_sql_menu(
                "SELECT ue.enrolid, COUNT(ue.id) AS n
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {course} c ON c.id = e.courseid
                  WHERE c.category {$catsql} AND e.customint3 > 0 AND e.enrol IN ('self', 'apply')
                        AND (ue.timeend = 0 OR ue.timeend > :now)
               GROUP BY ue.enrolid",
                $catparams + ['now' => time()]
            );
        }

        // 4. The viewer's cohorts, driven by userid; rides the userid foreign key of {cohort_members}.
        $cohorts = [];
        if ($canenrol) {
            $cohorts = array_flip(array_map('intval', $DB->get_fieldset_select(
                'cohort_members',
                'cohortid',
                'userid = :userid',
                ['userid' => $scope->viewerid]
            )));
        }

        // 5. The viewer's favourite courses, driven by userid; rides the userid foreign key of {favourite}.
        $favourites = [];
        if ($canenrol) {
            $favourites = array_flip(array_map('intval', $DB->get_fieldset_select(
                'favourite',
                'itemid',
                'userid = :userid AND component = :component AND itemtype = :itemtype',
                ['userid' => $scope->viewerid, 'component' => 'core_course', 'itemtype' => 'courses']
            )));
        }

        // The site-wide default each paying plugin falls back to when an instance carries no cost of its own.
        $defaultcosts = [
            'fee' => (float) get_config('enrol_fee', 'cost'),
            'paypal' => (float) get_config('enrol_paypal', 'cost'),
        ];
        $now = time();
        foreach ($courseids as $courseid) {
            $mine = $scope->enrolments[$courseid] ?? null;
            $type = $mine ? $mine->type : access::RELATIONSHIP_NONE;
            $startsat = 0;
            $endsat = 0;
            $prerequisiteid = 0;
            $opening = false;
            /* The relationship is decided before the instance loop, never after it: a viewer who
               already holds a row - one an administrator dated, an application, a suspension - is
               told about that row, and an open self instance beside it is a route the card adds
               ({@see cards::next_actions()}), never the state. The order is the plugin's own rank,
               access::get_enrolment_state(): enrolled, scheduled, pending, waitlisted, suspended,
               expired. */
            if ($mine && $mine->active) {
                $state = self::STATE_ENROLLED;
            } else if ($mine && $mine->scheduled) {
                $state = self::STATE_SCHEDULED;
                $startsat = (int) $mine->startsat;
                $endsat = (int) $mine->endsat;
            } else if ($mine && $mine->pending) {
                $state = $type === access::RELATIONSHIP_WAITLISTED ? self::STATE_WAITLISTED : self::STATE_PENDING;
            } else if ($type === access::RELATIONSHIP_SUSPENDED) {
                $state = self::STATE_SUSPENDED;
            } else if ($type === access::RELATIONSHIP_EXPIRED) {
                $state = self::STATE_EXPIRED;
                $endsat = (int) $mine->endedat;
            } else {
                $state = self::STATE_NONE;
                foreach ($instances[$courseid] ?? [] as $instance) {
                    $offer = self::instance_state(
                        $instance,
                        $now,
                        $canenrol,
                        isset($mine->instances[(int) $instance->id]),
                        (int) ($taken[(int) $instance->id] ?? 0),
                        $cohorts,
                        $defaultcosts,
                        !empty($scope->enrolments[(int) $instance->customint1]->active)
                    );
                    if ($offer === self::STATE_CONDITIONAL && !$prerequisiteid) {
                        $prerequisiteid = (int) $instance->customint1;
                    }
                    if ($canenrol && (int) $instance->enrolstartdate > $now) {
                        $opening = true;
                    }
                    $state = self::better($state, $offer);
                }
                if ($state !== self::STATE_CONDITIONAL) {
                    $prerequisiteid = 0;
                }
                // Only a card with nothing on offer prints an opening date; any better offer is the card's own way in.
                $opening = $opening && $state === self::STATE_NONE;
            }
            $verdicts[$courseid] = [
                'state' => $state,
                'favourite' => isset($favourites[$courseid]),
                'startsat' => $startsat,
                'endsat' => $endsat,
                'prerequisiteid' => $prerequisiteid,
                'opening' => $opening,
            ];
        }
        return $verdicts;
    }

    /**
     * What one enabled instance offers this viewer: open, free, key, conditional or nothing.
     *
     * @param \stdClass $instance The {enrol} row (id, enrol, window, password, customint1/3/4/5/6, cost).
     * @param int $now The time the windows are judged against.
     * @param bool $canenrol Whether the viewer is a real logged-in user.
     * @param bool $holdsrow Whether the viewer already holds a row on this instance.
     * @param int $taken Places taken on the instance, per the grouped count.
     * @param array $cohorts The viewer's cohort ids as keys.
     * @param array $defaultcosts The site-wide default cost of each paying plugin, keyed by plugin name ('fee', 'paypal').
     * @param bool $prerequisite Whether the viewer is actively enrolled in the course customint1 names.
     * @return string STATE_OPEN, STATE_FREE, STATE_KEY, STATE_CONDITIONAL or STATE_NONE.
     */
    private static function instance_state(
        \stdClass $instance,
        int $now,
        bool $canenrol,
        bool $holdsrow,
        int $taken,
        array $cohorts,
        array $defaultcosts,
        bool $prerequisite
    ): string {
        if ($instance->enrol === 'guest') {
            // Behind a password nobody walks straight in: the key is asked on the enrolment page, where the card sends them.
            return ((string) $instance->password === '') ? self::STATE_FREE : self::STATE_KEY;
        }
        if (!$canenrol || $holdsrow) {
            return self::STATE_NONE;
        }
        $start = (int) $instance->enrolstartdate;
        $end = (int) $instance->enrolenddate;
        if (($start > 0 && $start > $now) || ($end > 0 && $end < $now)) {
            return self::STATE_NONE;
        }

        switch ($instance->enrol) {
            case 'self':
            case 'apply':
                if ((int) $instance->customint6 !== 1) {
                    return self::STATE_NONE;
                }
                $cap = (int) $instance->customint3;
                if ($cap > 0 && $taken >= $cap) {
                    return self::STATE_NONE;
                }
                $cohortid = (int) $instance->customint5;
                if ($cohortid < 0) {
                    // The enrol_apply restore sentinel: there was a restriction this site cannot honour.
                    return self::STATE_NONE;
                }
                if ($cohortid > 0 && !isset($cohorts[$cohortid])) {
                    return self::STATE_NONE;
                }
                return self::STATE_OPEN;

            case 'fee':
            case 'paypal':
                $cost = (float) $instance->cost;
                if ($cost <= 0) {
                    $cost = (float) ($defaultcosts[$instance->enrol] ?? 0);
                }
                return (abs($cost) < 0.01) ? self::STATE_NONE : self::STATE_OPEN;

            case 'autoenrol':
                // The plugin's own test is a plain "!customint4": any value other than zero allows new enrolments.
                return ((int) $instance->customint4 === 0) ? self::STATE_NONE : self::STATE_OPEN;

            case 'coursecompleted':
                // It enrols nobody now; only a viewer working towards the course it names is promised this one.
                return $prerequisite ? self::STATE_CONDITIONAL : self::STATE_NONE;

            default:
                return self::STATE_NONE;
        }
    }

    /**
     * The better of two offers: open beats free beats key beats conditional beats nothing.
     *
     * The plugin's own summary order ({@see access::get_next_action()}): a route, then guest
     * access, free before keyed, then a conditional enrolment.
     *
     * @param string $current The state so far.
     * @param string $candidate What the next instance offers.
     * @return string
     */
    private static function better(string $current, string $candidate): string {
        return (self::OFFER_RANK[$candidate] > self::OFFER_RANK[$current]) ? $candidate : $current;
    }
}
