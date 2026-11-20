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
 * Theme Dimensions - Enrolment method eligibility resolver
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\landing;

use local_unlistedcourses\access;

/**
 * Decides which enrolment methods the current user can actually use.
 *
 * Core has no unified API for this: enrol/index.php renders whatever each
 * plugin's enrol_page_hook() returns, and the per-user gating lives inside
 * those hooks. can_self_enrol() exists but only enrol_self implements it -
 * enrol_fee keeps its user checks inside its hook and enrol_guest works
 * through try_guestaccess() during require_login. So this resolver carries
 * one adapter per known method, mirroring each plugin's own checks, and marks
 * instances of unknown plugins TYPE_FALLBACK, to be rendered through their own
 * hook. enrol_paypal and enrol_autoenrol are rendered through their hooks too,
 * but listed only where their own checks say the hook would offer something,
 * so the category card ({@see \theme_dimensions\local\category\classifier})
 * can be held against this class for them as for the others.
 *
 * A method the user cannot use right now is hidden, never shown with a
 * reason or a teaser.
 *
 * The viewer's relationship is decided beside the methods, not instead of
 * them: a viewer enrolled from a later date, with an application, suspended or
 * whose enrolment ended is told so, and offered every method that would really
 * take them now. The relationship is local_unlistedcourses' own
 * ({@see access::get_enrolment_state()}). enrol_coursecompleted offers nothing
 * to press, so it is no method: a viewer working towards its prerequisite is
 * told which course unlocks this one ({@see access::get_next_action()}), and
 * nobody else hears of it.
 *
 * The resolver is deliberately side-effect free: it never calls
 * enrol_page_hook() (hooks may enrol the user, redirect, or register AMD
 * requirements) and never touches $PAGE, so it is unit-testable and safe to
 * run before deciding anything about output.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolver {
    /**
     * Resolve the enrolment area for one course and the current user ($USER).
     *
     * The signature takes no user on purpose: enrol_self::can_self_enrol() reads
     * the global $USER, and so do this class's own mirrors of the other plugins'
     * checks, so resolving for someone else would silently answer for $USER.
     *
     * @param \stdClass $course The course record.
     * @return resolution The resolved state and usable methods.
     */
    public static function resolve(\stdClass $course): resolution {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return new resolution(
                resolution::STATE_VISITOR,
                self::visitor_methods($course),
                offers: self::visitor_offers($course),
                loginwayin: self::login_way_in($course)
            );
        }

        $context = \core\context\course::instance($course->id);
        if (is_enrolled($context, $USER, '', true)) {
            return new resolution(resolution::STATE_ENROLLED);
        }

        // The ranking is local_unlistedcourses' own, one statement for the course.
        $enrolment = access::get_enrolment_state((int) $course->id);

        $methods = [];
        $completed = false;
        $plugins = enrol_get_plugins(true);
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if (!isset($plugins[$instance->enrol])) {
                continue;
            }
            $plugin = $plugins[$instance->enrol];

            switch ($instance->enrol) {
                case 'self':
                    /* Three-state contract: true, a reason string (truthy!), or falsy.
                       Only the strict true means the user can enrol right now. */
                    if ($plugin->can_self_enrol($instance) === true) {
                        $methods[] = method::for_self($instance, (string) $instance->password !== '');
                    }
                    break;

                case 'guest':
                    /* Guest access is advertised whenever the instance is enabled: it is a
                       secondary action beside a real enrolment and the primary one where
                       nothing else is offered (the page decides which), and passwordless
                       guest access never even reaches core's enrolment page (require_login
                       grants it silently through try_guestaccess()). */
                    $methods[] = method::for_guest($instance, (string) $instance->password !== '');
                    break;

                case 'fee':
                    $method = self::resolve_fee($instance, $plugin);
                    if ($method) {
                        $methods[] = $method;
                    }
                    break;

                case 'paypal':
                    // Its own hook draws the payment button, so it stays a fallback; listed only where the hook would offer one.
                    if (self::payable_cost($instance, $plugin) !== null) {
                        $methods[] = method::for_fallback($instance);
                    }
                    break;

                case 'autoenrol':
                    /* enrol_allowed() is the plugin's own eligibility check and only reads; its hook asks it
                       first and renders nothing when it says no. A build without it keeps the plain fallback. */
                    $allowed = is_callable([$plugin, 'enrol_allowed'])
                        ? $plugin->enrol_allowed($instance, $USER)
                        : self::overrides_enrol_page_hook($plugin);
                    if ($allowed) {
                        $methods[] = method::for_fallback($instance);
                    }
                    break;

                case 'apply':
                    $method = self::resolve_apply($instance, $plugin);
                    if ($method) {
                        $methods[] = $method;
                    }
                    break;

                case 'coursecompleted':
                    // Never a fallback: its widget names the prerequisite to anybody. Asked below, for the viewer.
                    $completed = true;
                    break;

                default:
                    /* No adapter. A plugin which never renders an enrolment widget
                       (manual, cohort, ...) inherits the base enrol_page_hook() and is
                       skipped outright; one that overrides it gets the fallback card,
                       whose hook decides at render time whether anything shows. */
                    if (self::overrides_enrol_page_hook($plugin)) {
                        $methods[] = method::for_fallback($instance);
                    }
                    break;
            }
        }

        /* The row an administrator dated comes first: the viewer is told when the course opens to them,
           beside any method that would take them now. */
        if ($enrolment['type'] === access::RELATIONSHIP_SCHEDULED) {
            return new resolution(
                resolution::STATE_SCHEDULED,
                $methods,
                null,
                $enrolment['startsat'],
                $enrolment['endsat'],
                $enrolment['type']
            );
        }

        $appliedtime = self::pending_application((int) $course->id);
        if ($appliedtime !== null) {
            // A waiting-list application is one awaiting a decision too; the relationship tells the two apart.
            $waitlisted = $enrolment['type'] === access::RELATIONSHIP_WAITLISTED;
            return new resolution(
                resolution::STATE_PENDING,
                $methods,
                $appliedtime,
                null,
                null,
                $waitlisted ? access::RELATIONSHIP_WAITLISTED : access::RELATIONSHIP_PENDING
            );
        }

        $ended = in_array($enrolment['type'], [access::RELATIONSHIP_SUSPENDED, access::RELATIONSHIP_EXPIRED], true);
        // Only a page with nothing to press names the prerequisite; the plugin's rule decides who may hear of it.
        $prerequisiteid = null;
        if ($completed && !$methods) {
            $conditional = access::get_next_action((int) $course->id)['conditional'];
            $prerequisiteid = $conditional ? (int) $conditional['prerequisiteid'] : null;
        }
        return new resolution(
            $methods ? resolution::STATE_OPEN : resolution::STATE_CLOSED,
            $methods,
            null,
            null,
            $enrolment['type'] === access::RELATIONSHIP_EXPIRED ? $enrolment['endsat'] : null,
            $ended ? $enrolment['type'] : access::RELATIONSHIP_NONE,
            $prerequisiteid
        );
    }

    /**
     * When the current user's application to this course was lodged, if one is still awaiting a decision.
     *
     * The rule is enrol_apply's own queue, {@see \enrol_apply\local\queue::awaiting_decision_where()}:
     * a row that is not active and has not expired. Keep the two in step. An approved row whose
     * period ran out is not one: it stays active under the plugin's keep-on-expiry default, and the
     * suspend action re-suspends it with its past end date, which the second clause excludes.
     *
     * Read as the queue reads it, with no test of the instance's status or of the plugin being
     * enabled: an application lodged before either was switched off is still the viewer's, and still
     * waits. It is shown and nothing is offered for it, because the method loop lists enabled
     * instances of enabled plugins only.
     *
     * @param int $courseid The course id.
     * @return int|null The application's timecreated (the first apply instance in sort order wins), or null.
     */
    private static function pending_application(int $courseid): ?int {
        global $DB, $USER;

        $rows = $DB->get_records_sql(
            "SELECT ue.id, ue.timecreated
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid
                AND e.courseid = :courseid
                AND e.enrol = :enrol
                AND ue.status <> :active
                AND (ue.timeend = 0 OR ue.timeend > :now)
           ORDER BY e.sortorder, e.id",
            [
                'userid' => $USER->id,
                'courseid' => $courseid,
                'enrol' => 'apply',
                'active' => ENROL_USER_ACTIVE,
                'now' => time(),
            ],
            0,
            1
        );
        $row = reset($rows);
        return $row ? (int) $row->timecreated : null;
    }

    /**
     * Whether an enrol plugin overrides enrol_page_hook().
     *
     * The base implementation returns null unconditionally, so a plugin that
     * does not override it can never render an enrolment widget and its
     * instances are not worth a fallback card.
     *
     * @param \enrol_plugin $plugin The plugin instance.
     * @return bool Whether the hook is overridden.
     */
    public static function overrides_enrol_page_hook(\enrol_plugin $plugin): bool {
        $reflection = new \ReflectionMethod($plugin, 'enrol_page_hook');
        return $reflection->getDeclaringClass()->getName() !== \enrol_plugin::class;
    }

    /**
     * The methods a visitor (not logged in, or the guest account) is offered.
     *
     * Eligibility for the real methods depends on who logs in (cohorts,
     * capabilities, existing applications), so the visitor state offers only
     * the login CTA, where a login could lead somewhere ({@see login_way_in()}) -
     * plus guest access, which needs no identity.
     *
     * @param \stdClass $course The course record.
     * @return method[] The methods.
     */
    private static function visitor_methods(\stdClass $course): array {
        $methods = [];
        $plugins = enrol_get_plugins(true);
        foreach (enrol_get_instances($course->id, true) as $instance) {
            if ($instance->enrol === 'guest' && isset($plugins['guest'])) {
                $methods[] = method::for_guest($instance, (string) $instance->password !== '');
            }
        }
        return $methods;
    }

    /**
     * The instances whose seats and deadline a visitor is told.
     *
     * A visitor cannot enrol before logging in, and who logs in decides most of the rules (a
     * cohort, an application already sent), so the visitor sees the login button and, above it,
     * what the methods open to anybody at all declare: an instance passes {@see open_to_anybody()}.
     * A cohort-restricted instance passes too: without a login the cohort cannot be known, and
     * leaving it out would take the information off every restricted public course. An instance
     * with nothing to say ({@see offer::export()}) is not listed.
     *
     * @param \stdClass $course The course record.
     * @return \stdClass[] The {enrol} records, in instance sortorder.
     */
    private static function visitor_offers(\stdClass $course): array {
        $plugins = enrol_get_plugins(true);
        $now = time();
        $offers = [];
        foreach (enrol_get_instances($course->id, true) as $instance) {
            $type = $instance->enrol;
            if (!isset($plugins[$type]) || !in_array($type, offer::DEADLINE_PLUGINS, true)) {
                continue;
            }
            if (!self::open_to_anybody($instance, $plugins[$type], $now)) {
                continue;
            }
            if (offer::export($instance, $now) !== null) {
                $offers[] = $instance;
            }
        }
        return $offers;
    }

    /**
     * Whether logging in could lead a visitor anywhere on this course.
     *
     * The visitor's page leads with "Log in to enrol" only where a login could end in an
     * enrolment, an application, a payment or a named prerequisite. An enabled instance of an
     * enabled plugin counts when:
     * - its plugin is one of {@see offer::DEADLINE_PLUGINS} and it passes the filters the
     *   visitor's offers pass, {@see open_to_anybody()} - one predicate, so the button and the
     *   seats above it can never disagree about a closed window, a switched-off method or a full one;
     * - or its plugin overrides enrol_page_hook(), the resolver's fallback rule, which is also what
     *   carries enrol_coursecompleted (its hook names the prerequisite after the login).
     * Never guest access, which needs no login and is offered beside the button anyway, and never a
     * plugin inheriting the base hook (manual, cohort, meta, ...): nothing a learner can press.
     *
     * @param \stdClass $course The course record.
     * @return bool Whether a login could lead somewhere.
     */
    private static function login_way_in(\stdClass $course): bool {
        $plugins = enrol_get_plugins(true);
        $now = time();
        foreach (enrol_get_instances($course->id, true) as $instance) {
            $type = $instance->enrol;
            if (!isset($plugins[$type]) || $type === 'guest') {
                continue;
            }
            if (in_array($type, offer::DEADLINE_PLUGINS, true)) {
                if (self::open_to_anybody($instance, $plugins[$type], $now)) {
                    return true;
                }
                continue;
            }
            if (self::overrides_enrol_page_hook($plugins[$type])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether an instance of one of {@see offer::DEADLINE_PLUGINS} is open to anybody who logs in.
     *
     * Its window is open, it takes new enrolments, its cap is not reached and, for an apply method,
     * its card is not hidden from visitors ({@see apply_hides_card_from_visitors()}); who logs in may still
     * be refused (a cohort, an application already sent), which a visitor cannot be told. Shared by
     * {@see visitor_offers()} and {@see login_way_in()} so the two cannot drift.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param \enrol_plugin $plugin Its enabled plugin.
     * @param int $now The current time.
     * @return bool Whether the instance is open to anybody.
     */
    private static function open_to_anybody(\stdClass $instance, \enrol_plugin $plugin, int $now): bool {
        global $DB;

        $newenrols = ['self' => 'customint6', 'autoenrol' => 'customint4', 'apply' => 'customint6'];
        $caps = ['self' => 'customint3', 'autoenrol' => 'customint5'];
        $type = $instance->enrol;
        if ($instance->enrolstartdate && $instance->enrolstartdate > $now) {
            return false;
        }
        if ($instance->enrolenddate && $instance->enrolenddate < $now) {
            return false;
        }
        if (isset($newenrols[$type]) && empty($instance->{$newenrols[$type]})) {
            return false;
        }
        if (isset($caps[$type]) && (int) $instance->{$caps[$type]} > 0) {
            // The plugins' own count: every row of the instance, whatever its status.
            if ($DB->count_records('user_enrolments', ['enrolid' => $instance->id]) >= (int) $instance->{$caps[$type]}) {
                return false;
            }
        }
        if ($type === 'apply' && self::apply_hides_card_from_visitors($instance, $plugin)) {
            return false;
        }
        return !($type === 'apply' && self::apply_is_full($instance, $plugin));
    }

    /**
     * Whether an enrol_apply method keeps its card from a visitor who is not logged in.
     *
     * The method's "Hide this card from people outside the cohort" option, on a cohort-restricted
     * method, makes the card invisible to a visitor, who is a member of no cohort: advertising its
     * seats and deadline above the login button would announce what the method hides. The logged-in
     * path needs no such check, as allow_apply() and the category classifier already agree on it.
     * is_callable() on the object is the guard, as for allow_apply() and is_full(): an enrol_apply
     * build predating hides_card_from() keeps advertising the method.
     *
     * @param \stdClass $instance The {enrol} record of an apply method.
     * @param \enrol_plugin $plugin The enabled enrol_apply plugin.
     * @return bool Whether the card is hidden from a visitor.
     */
    private static function apply_hides_card_from_visitors(\stdClass $instance, \enrol_plugin $plugin): bool {
        if (!is_callable([$plugin, 'hides_card_from'])) {
            return false;
        }
        return (bool) $plugin->hides_card_from($instance, null);
    }

    /**
     * Adapter for enrol_fee, which implements neither can_self_enrol() nor
     * is_self_enrol_available(): its user-facing checks exist only inside its
     * enrol_page_hook() and are mirrored by {@see payable_cost()}.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param \enrol_plugin $plugin The fee plugin.
     * @return method|null The usable method, or null to hide it.
     */
    private static function resolve_fee(\stdClass $instance, \enrol_plugin $plugin): ?method {
        $cost = self::payable_cost($instance, $plugin);
        if ($cost === null) {
            return null;
        }
        return method::for_fee($instance, $cost, (string) $instance->currency);
    }

    /**
     * What the current user would pay on an enrol_fee or enrol_paypal instance, or null where its page offers no payment.
     *
     * Both plugins keep their user-facing checks inside enrol_page_hook() and check the same three
     * things there (enrol/fee/classes/plugin.php and enrol/paypal/lib.php): no row of the user
     * on this instance, the enrolment window, and a cost above zero on the instance or in the
     * plugin's own default. An instance with no cost is misconfigured: core shows an error there,
     * and this hides it.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param \enrol_plugin $plugin The fee or paypal plugin, whose default cost fills in.
     * @return float|null The cost, or null to hide the method.
     */
    private static function payable_cost(\stdClass $instance, \enrol_plugin $plugin): ?float {
        global $DB, $USER;

        $now = time();
        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > $now) {
            return null;
        }
        if ($instance->enrolenddate != 0 && $instance->enrolenddate < $now) {
            return null;
        }
        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return null;
        }

        $cost = (float) $instance->cost;
        if ($cost <= 0) {
            $cost = (float) $plugin->get_config('cost');
        }
        return (abs($cost) < 0.01) ? null : $cost;
    }

    /**
     * Adapter for enrol_apply.
     *
     * allow_apply() is the plugin's own eligibility predicate (enabled, new
     * applications allowed, enrolment window, cohort restriction with the -1
     * fail-closed sentinel); the already-applied and max-applicants checks
     * live outside it, in the hook and in submit_application(), and are
     * mirrored here. Any row of the user on the instance hides the form:
     * an application awaiting a decision, a deferred one, an approved
     * enrolment and one whose period ran out alike. Whether a row is an
     * application still awaiting a decision is {@see pending_application()}'s
     * question, asked once for the whole course.
     *
     * @param \stdClass $instance The {enrol} instance record.
     * @param \enrol_plugin $plugin The apply plugin.
     * @return method|null The usable method, or null to hide it.
     */
    private static function resolve_apply(\stdClass $instance, \enrol_plugin $plugin): ?method {
        global $DB, $USER;

        if (!is_callable([$plugin, 'allow_apply'])) {
            // Not the enrol_apply this adapter knows; give it the fallback treatment.
            return self::overrides_enrol_page_hook($plugin) ? method::for_fallback($instance) : null;
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return null;
        }

        if ($plugin->allow_apply($instance) !== true) {
            return null;
        }

        if (self::apply_is_full($instance, $plugin)) {
            return null;
        }

        return method::for_apply($instance);
    }

    /**
     * Whether an enrol_apply instance has no place left.
     *
     * Asks the plugin rather than re-deriving the answer, so this surface and enrol_apply's own
     * pages agree on "full". enrol_apply does not count expired enrolments against the cap (it
     * ships expiredaction = ENROL_EXT_REMOVED_KEEP, under which core changes nothing when a
     * period runs out), so a local count would read a course whose places expiry freed as
     * closed while enrol_apply accepts the application.
     *
     * It goes through the plugin object and not \enrol_apply\local\capacity: enrol_apply is an
     * optional dependency here, so a class in its namespace would have to be resolved on a site
     * without the plugin. is_callable() on the object is the guard every allow_apply() call in
     * this file uses.
     *
     * The inline fallback is what an enrol_apply build predating is_full() means by "full": on
     * such a build the unfiltered count is the plugin's rule.
     *
     * @param \stdClass $instance Enrol instance belonging to the apply plugin.
     * @param \enrol_plugin $plugin The apply plugin instance.
     * @return bool True when the instance has no place left.
     */
    private static function apply_is_full(\stdClass $instance, \enrol_plugin $plugin): bool {
        global $DB;

        if (is_callable([$plugin, 'is_full'])) {
            return (bool) $plugin->is_full($instance);
        }

        $cap = (int) $instance->customint3;

        return $cap > 0 && $DB->count_records('user_enrolments', ['enrolid' => $instance->id]) >= $cap;
    }
}
