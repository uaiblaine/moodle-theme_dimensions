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
 * Theme Dimensions - Tests for the enrolment state classifier
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use local_unlistedcourses\access;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\landing\method;
use theme_dimensions\local\landing\resolution;
use theme_dimensions\local\landing\resolver;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests for the classifier: a superset of the landing page resolver, in a bounded number of statements.
 *
 * The contract is one-directional and the superset test holds it shape by
 * shape: whatever the resolver calls enrolled, pending or open, the
 * classifier calls the same; what the resolver calls closed the classifier
 * may call closed or, where it deliberately drops a check, open - never the
 * reverse. Every shape that must read closed is asserted closed as well, so
 * the classifier cannot pass the superset test by calling everything open.
 * A suspended or ended relationship is the one exception, and the reason the
 * card has a route line: the resolver's open method beside such a row is the
 * route local_unlistedcourses' batch reports, which the test holds instead.
 * The same test holds the classifier against that batch: never closed where
 * the batch offers something.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(classifier::class)]
final class classifier_test extends \advanced_testcase {
    use fixture;

    /**
     * Add an enabled instance of one method to a course.
     *
     * @param \stdClass $course The course.
     * @param string $enrol The plugin.
     * @param array $fields Extra instance fields.
     * @return \stdClass The {enrol} record.
     */
    private function add_instance(\stdClass $course, string $enrol, array $fields = []): \stdClass {
        global $DB;
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $id = enrol_get_plugin($enrol)->add_instance($course, $fields + [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
        ]);
        return $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Classify one root's courses for the current user, through the population's scope.
     *
     * @param int $rootid The category.
     * @param int[] $courseids The courses.
     * @return array courseid => verdict.
     */
    private function classify(int $rootid, array $courseids): array {
        self::forget();
        $scope = population::scope(new criteria($rootid));
        return classifier::classify($courseids, $scope);
    }

    /**
     * What the resolver says a course is, for the current user.
     *
     * @param int $courseid The course.
     * @return array The resolver's state at 0 and whether a non-guest method is offered at 1.
     */
    private function resolved(int $courseid): array {
        $resolution = resolver::resolve(get_course($courseid));
        $real = false;
        foreach ($resolution->methods as $method) {
            if ($method->type !== method::TYPE_GUEST) {
                $real = true;
            }
        }
        return [$resolution->state, $real];
    }

    /**
     * Whatever the resolver admits, the classifier admits; every shape that must read closed reads closed.
     */
    public function test_the_classifier_is_a_superset_of_the_resolver_shape_by_shape(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');
        $this->enable_enrol('fee');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $viewer = $generator->create_user();
        $other = $generator->create_user();
        $cohort = $generator->create_cohort();
        $foreign = $generator->create_cohort();
        require_once($CFG->dirroot . '/cohort/lib.php');
        cohort_add_member($cohort->id, $viewer->id);

        $expected = [];
        $make = function (string $label, string $state, callable $setup) use (&$expected, $generator, $root): void {
            $course = $generator->create_course(['category' => $root->id, 'fullname' => $label]);
            $setup($course);
            $expected[(int) $course->id] = [$label, $state];
        };

        $make('self open', classifier::STATE_OPEN, function ($c) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1]);
        });
        $make('self new enrolments off', classifier::STATE_NONE, function ($c) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 0]);
        });
        $make('self full', classifier::STATE_NONE, function ($c) use ($other, $generator) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'customint3' => 1]);
            $generator->enrol_user($other->id, $c->id, null, 'self');
        });
        $make('self one place left', classifier::STATE_OPEN, function ($c) use ($other, $generator) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'customint3' => 2]);
            $generator->enrol_user($other->id, $c->id, null, 'self');
        });
        $make('self window closed', classifier::STATE_NONE, function ($c) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'enrolenddate' => time() - DAYSECS]);
        });
        $make('self window not yet open', classifier::STATE_NONE, function ($c) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'enrolstartdate' => time() + DAYSECS]);
        });
        $make('self cohort member', classifier::STATE_OPEN, function ($c) use ($cohort) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'customint5' => $cohort->id]);
        });
        $make('self cohort non-member', classifier::STATE_NONE, function ($c) use ($foreign) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'customint5' => $foreign->id]);
        });
        $make('self cohort deleted', classifier::STATE_NONE, function ($c) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1, 'customint5' => 999999]);
        });
        $make('self already holds a suspended row', classifier::STATE_SUSPENDED, function ($c) use ($viewer, $generator) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1]);
            $generator->enrol_user($viewer->id, $c->id, null, 'self', 0, 0, ENROL_USER_SUSPENDED);
        });
        $make('suspended beside an open self instance', classifier::STATE_SUSPENDED, function ($c) use ($viewer, $generator) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1]);
            $generator->enrol_user($viewer->id, $c->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        });
        $make('ended beside an open self instance', classifier::STATE_EXPIRED, function ($c) use ($viewer, $generator) {
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1]);
            $generator->enrol_user($viewer->id, $c->id, null, 'manual', time() - 9 * DAYSECS, time() - DAYSECS);
        });
        $make('guest only', classifier::STATE_FREE, function ($c) {
            $this->enable_instance((int) $c->id, 'guest');
        });
        $make('guest beside self', classifier::STATE_OPEN, function ($c) {
            $this->enable_instance((int) $c->id, 'guest');
            $this->enable_instance((int) $c->id, 'self', ['customint6' => 1]);
        });
        $make('guest behind a key', classifier::STATE_KEY, function ($c) {
            $this->enable_instance((int) $c->id, 'guest', ['password' => 'chave']);
        });
        $make('fee with a cost', classifier::STATE_OPEN, function ($c) {
            $this->add_instance($c, 'fee', ['cost' => 10, 'currency' => 'BRL']);
        });
        $make('fee without a cost', classifier::STATE_NONE, function ($c) {
            $this->add_instance($c, 'fee', ['cost' => 0, 'currency' => 'BRL']);
        });
        $this->enable_enrol('paypal');
        $make('paypal with a cost', classifier::STATE_OPEN, function ($c) {
            $this->add_instance($c, 'paypal', ['cost' => 10, 'currency' => 'BRL']);
        });
        $make('paypal without a cost', classifier::STATE_NONE, function ($c) {
            $this->add_instance($c, 'paypal', ['cost' => 0, 'currency' => 'BRL']);
        });
        $make('enrolled', classifier::STATE_ENROLLED, function ($c) use ($viewer, $generator) {
            $generator->enrol_user($viewer->id, $c->id);
        });
        $make('enrolment suspended', classifier::STATE_SUSPENDED, function ($c) use ($viewer, $generator) {
            $generator->enrol_user($viewer->id, $c->id, null, 'manual', 0, 0, ENROL_USER_SUSPENDED);
        });
        $make('enrolment expired', classifier::STATE_EXPIRED, function ($c) use ($viewer, $generator) {
            $generator->enrol_user($viewer->id, $c->id, null, 'manual', 0, time() - DAYSECS);
        });
        $make('nothing on offer', classifier::STATE_NONE, function ($c) {
        });
        if (\core_component::get_component_directory('enrol_apply')) {
            $this->enable_enrol('apply');
            $make('apply open', classifier::STATE_OPEN, function ($c) {
                $this->add_instance($c, 'apply', ['customint6' => 1]);
            });
            $make('apply pending', classifier::STATE_PENDING, function ($c) use ($viewer, $generator) {
                $this->add_instance($c, 'apply', ['customint6' => 1]);
                $generator->enrol_user($viewer->id, $c->id, null, 'apply', 0, 0, ENROL_USER_SUSPENDED);
            });
            $make('apply waiting list', classifier::STATE_WAITLISTED, function ($c) use ($viewer, $generator) {
                $this->add_instance($c, 'apply', ['customint6' => 1]);
                $generator->enrol_user($viewer->id, $c->id, null, 'apply', 0, 0, 2);
            });
            $make('apply cohort unresolved', classifier::STATE_NONE, function ($c) {
                $this->add_instance($c, 'apply', ['customint6' => 1, 'customint5' => -1]);
            });
            $make('apply full', classifier::STATE_NONE, function ($c) use ($other, $generator) {
                $this->add_instance($c, 'apply', ['customint6' => 1, 'customint3' => 1]);
                $generator->enrol_user($other->id, $c->id, null, 'apply', 0, 0, ENROL_USER_SUSPENDED);
            });
        }
        if (\core_component::get_component_directory('enrol_autoenrol')) {
            $this->enable_enrol('autoenrol');
            $make('autoenrol taking new enrolments', classifier::STATE_OPEN, function ($c) {
                $this->add_instance($c, 'autoenrol', ['customint4' => 1]);
            });
            $make('autoenrol with new enrolments off', classifier::STATE_NONE, function ($c) {
                $this->add_instance($c, 'autoenrol', ['customint4' => 0]);
            });
        }
        if (\core_component::get_component_directory('enrol_coursecompleted')) {
            $this->enable_enrol('coursecompleted');
            $prerequisite = $generator->create_course(['category' => $root->id, 'fullname' => 'prerequisite']);
            $generator->enrol_user($viewer->id, $prerequisite->id);
            $expected[(int) $prerequisite->id] = ['prerequisite', classifier::STATE_ENROLLED];
            $elsewhere = $generator->create_course(['category' => $root->id, 'fullname' => 'unrelated prerequisite']);
            $expected[(int) $elsewhere->id] = ['unrelated prerequisite', classifier::STATE_NONE];
            $make('course completed, working towards it', classifier::STATE_CONDITIONAL, function ($c) use ($prerequisite) {
                $this->completed_instance($c, (int) $prerequisite->id);
            });
            $make('course completed, not working towards it', classifier::STATE_NONE, function ($c) use ($elsewhere) {
                $this->completed_instance($c, (int) $elsewhere->id);
            });
        }
        set_config('cost', 0, 'enrol_fee');
        set_config('cost', 0, 'enrol_paypal');

        $this->become($viewer);
        $verdicts = $this->classify((int) $root->id, array_keys($expected));
        $batch = access::get_next_actions(array_keys($expected));
        foreach ($expected as $courseid => [$label, $state]) {
            $this->assertSame($state, $verdicts[$courseid]['state'], $label);
            [$resolved, $real] = $this->resolved($courseid);
            $classified = $verdicts[$courseid]['state'];
            $ended = in_array($classified, [classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED], true);
            switch ($resolved) {
                case resolution::STATE_ENROLLED:
                    $this->assertSame(classifier::STATE_ENROLLED, $classified, "{$label}: resolver enrolled");
                    break;
                case resolution::STATE_PENDING:
                    $this->assertContains(
                        $classified,
                        [classifier::STATE_PENDING, classifier::STATE_WAITLISTED],
                        "{$label}: resolver pending"
                    );
                    break;
                case resolution::STATE_OPEN:
                    if ($real && $ended) {
                        // The row is the card's state; the resolver's method is the route its line prints.
                        $this->assertNotEmpty($batch[$courseid]['routes'], "{$label}: the batch reports the route");
                    } else if ($real) {
                        $this->assertSame(classifier::STATE_OPEN, $classified, "{$label}: resolver open");
                    } else {
                        $this->assertContains(
                            $classified,
                            [classifier::STATE_FREE, classifier::STATE_KEY, classifier::STATE_OPEN],
                            "{$label}: guest"
                        );
                    }
                    break;
                default:
                    $this->assertSame(resolution::STATE_CLOSED, $resolved, $label);
                    $this->assertNotContains(
                        $classified,
                        [classifier::STATE_ENROLLED, classifier::STATE_PENDING, classifier::STATE_WAITLISTED],
                        "{$label}: resolver closed"
                    );
                    break;
            }
            if (!$ended && classifier::rank($classified) > 0) {
                // An offer: never more closed than what local_unlistedcourses' own batch offers.
                $this->assertGreaterThanOrEqual(
                    self::offer_rank(self::batch_offer($batch[$courseid])),
                    self::offer_rank($classified),
                    "{$label}: the batch offers {$batch[$courseid]['type']}"
                );
            }
        }
        $this->assertGreaterThanOrEqual(23, count($expected), 'Precondition: every shape was built.');
    }

    /**
     * The classifier state a next action of local_unlistedcourses' batch amounts to.
     *
     * @param array $next One answer of access::get_next_actions().
     * @return string STATE_OPEN, STATE_FREE, STATE_KEY, STATE_CONDITIONAL or STATE_NONE.
     */
    private static function batch_offer(array $next): string {
        return match ($next['type']) {
            access::NEXT_OPEN => classifier::STATE_OPEN,
            access::NEXT_GUEST => $next['guest'] === access::GUEST_FREE ? classifier::STATE_FREE : classifier::STATE_KEY,
            access::NEXT_CONDITIONAL => classifier::STATE_CONDITIONAL,
            default => classifier::STATE_NONE,
        };
    }

    /**
     * How good an offer is, written out here so that no reading of the class decides it.
     *
     * @param string $state An offer state.
     * @return int Open 4, free 3, key 2, conditional 1, anything else 0.
     */
    private static function offer_rank(string $state): int {
        return [
            classifier::STATE_OPEN => 4,
            classifier::STATE_FREE => 3,
            classifier::STATE_KEY => 2,
            classifier::STATE_CONDITIONAL => 1,
        ][$state] ?? 0;
    }

    /**
     * A paypal instance with no cost of its own falls back to enrol_paypal's default, never to enrol_fee's.
     *
     * Both halves are needed: with only paypal's default set, a classifier that ignored defaults would fail; with
     * only enrol_fee's set, one that read the wrong plugin's default would. The resolver answers each half too.
     */
    public function test_a_paypal_instance_falls_back_to_its_own_plugins_default_cost(): void {
        $this->resetAfterTest();
        $this->enable_enrol('paypal');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $course = $generator->create_course(['category' => $root->id]);
        $this->add_instance($course, 'paypal', ['cost' => 0, 'currency' => 'BRL']);
        $courseid = (int) $course->id;
        $this->become($generator->create_user());

        set_config('cost', 0, 'enrol_fee');
        set_config('cost', 30, 'enrol_paypal');
        $this->assertSame(classifier::STATE_OPEN, $this->classify((int) $root->id, [$courseid])[$courseid]['state']);
        $this->assertSame(resolution::STATE_OPEN, $this->resolved($courseid)[0], 'The resolver agrees.');

        set_config('cost', 30, 'enrol_fee');
        set_config('cost', 0, 'enrol_paypal');
        $this->assertSame(classifier::STATE_NONE, $this->classify((int) $root->id, [$courseid])[$courseid]['state']);
        $this->assertSame(resolution::STATE_CLOSED, $this->resolved($courseid)[0], 'The resolver agrees.');
    }

    /**
     * Guest access behind a key reads key, for a real user and for the guest account alike, and never free.
     *
     * A key-protected instance lets nobody straight into the course; the key is asked on the enrolment page, which
     * is where a key card points. The guest account is offered the keyed method by the resolver, so it reads key
     * too. Free stays for guest access with no password, in the same run, for both viewers; an open self instance
     * beside the key wins, because a route beats guest access.
     */
    public function test_guest_access_behind_a_key_reads_key_and_never_free(): void {
        $this->resetAfterTest();
        $this->enable_enrol('guest');
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $keyed = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $keyed->id, 'guest', ['password' => 'chave']);
        $free = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $free->id, 'guest');
        $both = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $both->id, 'guest', ['password' => 'chave']);
        $this->enable_instance((int) $both->id, 'self', ['customint6' => 1]);
        $ids = [(int) $keyed->id, (int) $free->id, (int) $both->id];

        $this->become($generator->create_user());
        $verdicts = $this->classify((int) $root->id, $ids);
        $this->assertSame(classifier::STATE_KEY, $verdicts[(int) $keyed->id]['state'], 'The key is asked where the card points.');
        $this->assertSame(classifier::STATE_FREE, $verdicts[(int) $free->id]['state'], 'Control: no password is free access.');
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $both->id]['state'], 'A route beats the key.');
        $this->assertSame(classifier::STATE_OPEN, classifier::chip(classifier::STATE_KEY), 'Key counts under the open chip.');

        $this->setGuestUser();
        $verdicts = $this->classify((int) $root->id, $ids);
        $this->assertSame(classifier::STATE_KEY, $verdicts[(int) $keyed->id]['state'], 'The guest account is offered the key.');
        $this->assertSame(classifier::STATE_FREE, $verdicts[(int) $free->id]['state'], 'Control: free for the guest account too.');
        $this->assertSame(classifier::STATE_KEY, $verdicts[(int) $both->id]['state'], 'A route is never the guest account\'s.');
    }

    /**
     * An enrol_coursecompleted instance reads conditional for a viewer actively enrolled in its prerequisite, and only then.
     *
     * The controls each break one condition of the rule on an identical course: a viewer with no enrolment in the
     * prerequisite, one whose enrolment there starts later, a closed window, a row of the viewer's on the instance
     * itself, and a guest. An open route and free guest access both beat it, as the plugin's own summary orders them.
     * The instance is written as a row, so this runs where enrol_coursecompleted is not installed.
     */
    public function test_a_course_completed_instance_is_conditional_for_a_viewer_working_towards_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $elsewhere = $generator->create_category();
        $prerequisite = $generator->create_course(['category' => $elsewhere->id]);
        $conditional = $generator->create_course(['category' => $root->id]);
        $this->completed_instance($conditional, (int) $prerequisite->id);
        $shut = $generator->create_course(['category' => $root->id]);
        $this->completed_instance($shut, (int) $prerequisite->id, ['enrolenddate' => time() - DAYSECS]);
        $held = $generator->create_course(['category' => $root->id]);
        $heldinstance = $this->completed_instance($held, (int) $prerequisite->id);
        $routed = $generator->create_course(['category' => $root->id]);
        $this->completed_instance($routed, (int) $prerequisite->id);
        $this->enable_instance((int) $routed->id, 'self', ['customint6' => 1]);
        $guested = $generator->create_course(['category' => $root->id]);
        $this->completed_instance($guested, (int) $prerequisite->id);
        $this->enable_instance((int) $guested->id, 'guest');
        $ids = [(int) $conditional->id, (int) $shut->id, (int) $held->id, (int) $routed->id, (int) $guested->id];

        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $prerequisite->id);
        $DB->insert_record('user_enrolments', (object) [
            'enrolid' => $heldinstance,
            'userid' => $learner->id,
            'status' => ENROL_USER_SUSPENDED,
            'timestart' => 0,
            'timeend' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->become($learner);
        $verdicts = $this->classify((int) $root->id, $ids);
        $this->assertSame(classifier::STATE_CONDITIONAL, $verdicts[(int) $conditional->id]['state']);
        $this->assertSame((int) $prerequisite->id, $verdicts[(int) $conditional->id]['prerequisiteid']);
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $shut->id]['state'], 'A closed window offers nothing.');
        $this->assertSame(0, $verdicts[(int) $shut->id]['prerequisiteid'], 'And names no prerequisite.');
        $this->assertSame(classifier::STATE_SUSPENDED, $verdicts[(int) $held->id]['state'], 'A row on it is a relationship.');
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $routed->id]['state'], 'A route beats it.');
        $this->assertSame(0, $verdicts[(int) $routed->id]['prerequisiteid'], 'An open card names no prerequisite.');
        $this->assertSame(classifier::STATE_FREE, $verdicts[(int) $guested->id]['state'], 'Free guest access beats it.');
        $this->assertSame(classifier::STATE_NONE, classifier::chip(classifier::STATE_CONDITIONAL), 'It counts under no chip.');

        $stranger = $generator->create_user();
        $this->become($stranger);
        $verdicts = $this->classify((int) $root->id, [(int) $conditional->id]);
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $conditional->id]['state'], 'Nobody else is told about it.');
        $this->assertSame(0, $verdicts[(int) $conditional->id]['prerequisiteid']);

        $later = $generator->create_user();
        $generator->enrol_user($later->id, $prerequisite->id, 'student', 'manual', time() + 3 * DAYSECS);
        $this->become($later);
        $verdicts = $this->classify((int) $root->id, [(int) $conditional->id]);
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $conditional->id]['state'], 'Not working towards it yet.');

        $this->setGuestUser();
        $verdicts = $this->classify((int) $root->id, [(int) $conditional->id]);
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $conditional->id]['state'], 'Never the guest account\'s.');
    }

    /**
     * The relationship states come from local_unlistedcourses' types, with the dates of their own rows.
     *
     * Suspended and ended are read from real rows, each beside an open self instance that would read open with no
     * row at all (the control, the same course for a stranger). Between two ended rows the later end wins; a
     * scheduled row beats a suspended one on another method; the dates of every other state are 0.
     */
    public function test_the_relationship_states_are_the_plugins_with_their_own_dates(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $suspended = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $suspended->id, 'self', ['customint6' => 1]);
        $ended = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $ended->id, 'self', ['customint6' => 1]);
        $outranked = $generator->create_course(['category' => $root->id]);
        $ids = [(int) $suspended->id, (int) $ended->id, (int) $outranked->id];

        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $first = time() - 20 * DAYSECS;
        $last = time() - 2 * DAYSECS;
        $generator->enrol_user($viewer->id, $ended->id, 'student', 'manual', $first - DAYSECS, $first);
        $generator->enrol_user($viewer->id, $ended->id, 'student', 'self', $last - DAYSECS, $last);
        $start = time() + 5 * DAYSECS;
        $generator->enrol_user($viewer->id, $outranked->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->enable_instance((int) $outranked->id, 'self', ['customint6' => 1]);
        $generator->enrol_user($viewer->id, $outranked->id, 'student', 'self', $start, 0);

        $this->become($viewer);
        $verdicts = $this->classify((int) $root->id, $ids);
        $this->assertSame(classifier::STATE_SUSPENDED, $verdicts[(int) $suspended->id]['state']);
        $this->assertSame([0, 0], [$verdicts[(int) $suspended->id]['startsat'], $verdicts[(int) $suspended->id]['endsat']]);
        $this->assertSame(classifier::STATE_EXPIRED, $verdicts[(int) $ended->id]['state']);
        $this->assertSame($last, $verdicts[(int) $ended->id]['endsat'], 'The later of the two ends.');
        $this->assertSame(0, $verdicts[(int) $ended->id]['startsat']);
        $this->assertSame(classifier::STATE_SCHEDULED, $verdicts[(int) $outranked->id]['state'], 'Scheduled outranks suspended.');
        $this->assertSame($start, $verdicts[(int) $outranked->id]['startsat']);
        foreach ([classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED] as $state) {
            $this->assertSame(classifier::STATE_NONE, classifier::chip($state), "{$state} counts under no chip.");
            $this->assertSame(classifier::rank(classifier::STATE_NONE), classifier::rank($state), "{$state} sorts with none.");
        }

        $this->become($generator->create_user());
        $verdicts = $this->classify((int) $root->id, $ids);
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $suspended->id]['state'], 'Control: with no row it is open.');
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $ended->id]['state'], 'Control: with no row it is open.');
    }

    /**
     * Every state has its own chip, rank and place in the display order, and every chip is a filter.
     *
     * Written out rather than derived, so a state added to one table and forgotten in another reddens here: an
     * uncounted state would be an undefined key in the histogram, an unranked one would sort as none in silence.
     */
    public function test_every_state_has_a_chip_and_a_rank_and_every_chip_is_a_filter(): void {
        $this->assertSame([
            classifier::STATE_ENROLLED => classifier::STATE_ENROLLED,
            classifier::STATE_SCHEDULED => classifier::STATE_SCHEDULED,
            classifier::STATE_PENDING => classifier::STATE_PENDING,
            classifier::STATE_WAITLISTED => classifier::STATE_PENDING,
            classifier::STATE_SUSPENDED => classifier::STATE_NONE,
            classifier::STATE_EXPIRED => classifier::STATE_NONE,
            classifier::STATE_OPEN => classifier::STATE_OPEN,
            classifier::STATE_FREE => classifier::STATE_FREE,
            classifier::STATE_KEY => classifier::STATE_OPEN,
            classifier::STATE_CONDITIONAL => classifier::STATE_NONE,
            classifier::STATE_NONE => classifier::STATE_NONE,
        ], array_combine(classifier::states(), array_map([classifier::class, 'chip'], classifier::states())));
        $this->assertSame(
            ['enrolled', 'scheduled', 'pending', 'open', 'free', 'none'],
            classifier::chips(),
            'The six chips the toolbar always had, in its order.'
        );
        $this->assertSame([...classifier::chips(), classifier::CHIP_FAVOURITES], classifier::filters());
        $this->assertEqualsCanonicalizing(classifier::states(), array_keys(classifier::RANK), 'Every state is ranked.');
        foreach (classifier::states() as $state) {
            $this->assertSame(
                classifier::RANK[classifier::chip($state)],
                classifier::rank($state),
                "{$state} sorts with the chip it counts under."
            );
        }
        $this->assertSame(classifier::STATE_NONE, classifier::chip('lapsed'), 'A state nobody knows counts as none.');
    }

    /**
     * The grouped count uses enrol_apply's shape, so an expired self enrolment frees a place here that core still counts.
     *
     * The permitted direction of error, pinned: the resolver (core's count of
     * every row) says full, the classifier says open. The control beside it
     * is the same cap with a live enrolment, which both call full.
     */
    public function test_the_count_shape_errs_towards_open_on_an_expired_enrolment(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $viewer = $generator->create_user();
        $other = $generator->create_user();

        $expired = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $expired->id, 'self', ['customint6' => 1, 'customint3' => 1]);
        $generator->enrol_user($other->id, $expired->id, null, 'self', 0, time() - DAYSECS);
        $live = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $live->id, 'self', ['customint6' => 1, 'customint3' => 1]);
        $generator->enrol_user($other->id, $live->id, null, 'self');

        $this->become($viewer);
        $verdicts = $this->classify((int) $root->id, [(int) $expired->id, (int) $live->id]);
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $expired->id]['state']);
        $this->assertSame(resolution::STATE_CLOSED, $this->resolved((int) $expired->id)[0], 'Core counts the expired row.');
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $live->id]['state'], 'Never optimistic on a live cap.');
    }

    /**
     * Four statements for the population whatever its size, the enrolments one more, all driven by the viewer or the categories.
     */
    public function test_classifying_costs_the_same_for_two_courses_and_for_forty(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $small = $generator->create_category();
        $large = $generator->create_category();
        $viewer = $generator->create_user();
        $smallids = [];
        for ($i = 0; $i < 2; $i++) {
            $course = $generator->create_course(['category' => $small->id]);
            $this->enable_instance((int) $course->id, 'self', ['customint6' => 1, 'customint3' => 5]);
            $smallids[] = (int) $course->id;
        }
        $largeids = [];
        for ($i = 0; $i < 40; $i++) {
            $course = $generator->create_course(['category' => $large->id]);
            $this->enable_instance((int) $course->id, 'self', ['customint6' => 1, 'customint3' => 5]);
            $largeids[] = (int) $course->id;
        }
        $generator->enrol_user($viewer->id, $largeids[7]);

        $this->become($viewer);
        $smallscope = population::scope(new criteria((int) $small->id));
        $largescope = population::scope(new criteria((int) $large->id));
        classifier::classify($smallids, $smallscope);
        classifier::classify($largeids, $largescope);

        $smallreads = $this->reads(fn() => classifier::classify($smallids, $smallscope));
        $largereads = $this->reads(fn() => classifier::classify($largeids, $largescope));
        $this->assertSame($smallreads, $largereads, "{$smallreads} reads for two courses, {$largereads} for forty.");
        $this->assertLessThanOrEqual(4, $largereads);

        $enrolmentreads = $this->reads(function () use ($viewer, $largeids) {
            $map = classifier::enrolments((int) $viewer->id);
            $this->assertTrue($map[$largeids[7]]->active);
            $this->assertFalse($map[$largeids[7]]->pending);
            $this->assertCount(1, $map);
        });
        $this->assertSame(1, $enrolmentreads);
        $this->assertSame([], classifier::enrolments(0), 'A visitor holds no enrolments and costs no statement.');
    }

    /**
     * The enrolments map follows the plugin's one rule: a start date ahead is a relationship, a suspended row is not.
     *
     * The rows must agree with local_unlistedcourses' access::classify_enrolment(), or the population's clamp rescue
     * and the plugin's primed memo would disagree about who belongs to a course.
     */
    public function test_the_enrolments_map_counts_a_scheduled_row_and_not_a_suspended_one(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $viewer = $generator->create_user();
        $scheduled = $generator->create_course();
        $suspended = $generator->create_course();
        $active = $generator->create_course();
        $start = time() + 4 * DAYSECS;
        $generator->enrol_user($viewer->id, $scheduled->id, 'student', 'manual', $start, 0);
        $generator->enrol_user($viewer->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($viewer->id, $active->id);

        $map = classifier::enrolments((int) $viewer->id);

        $this->assertTrue($map[(int) $scheduled->id]->scheduled);
        $this->assertTrue($map[(int) $scheduled->id]->inactive, 'A scheduled row is a relationship that is not active yet.');
        $this->assertFalse($map[(int) $scheduled->id]->active);
        $this->assertSame($start, $map[(int) $scheduled->id]->startsat);
        $this->assertTrue($map[(int) $active->id]->active, 'Control: an active row is active.');
        $this->assertFalse($map[(int) $active->id]->scheduled);
        $this->assertFalse($map[(int) $suspended->id]->inactive, 'A suspended manual row relates to nothing.');
        $this->assertFalse($map[(int) $suspended->id]->active);
        $this->assertFalse($map[(int) $suspended->id]->pending);
    }

    /**
     * Fold one stubbed classification into a fresh entry of the enrolments map, as enrolments() does per row.
     *
     * The type is handed in as a string and the plugin is never asked, so a type the plugin has never answered
     * can be folded too.
     *
     * @param string $type The relationship type a row was classified as.
     * @param \stdClass|null $entry An entry to fold into, a fresh one when null.
     * @param int $endsat The row's end date.
     * @return \stdClass The entry: active, scheduled, inactive, pending, type, startsat, endsat, endedat.
     */
    private function fold(string $type, ?\stdClass $entry = null, int $endsat = 200): \stdClass {
        $entry = $entry ?? (object) [
            'active' => false,
            'scheduled' => false,
            'inactive' => false,
            'pending' => false,
            'type' => 'none',
            'startsat' => 0,
            'endsat' => 0,
            'endedat' => 0,
        ];
        $method = new \ReflectionMethod(classifier::class, 'record_relationship');
        $method->invoke(null, $entry, ['type' => $type, 'startsat' => 100, 'endsat' => $endsat]);
        return $entry;
    }

    /**
     * The flags of an entry that relates the viewer to nothing, written out so no fold decides what "none" means.
     *
     * @return bool[] active, scheduled, inactive, pending.
     */
    private function noflags(): array {
        return ['active' => false, 'scheduled' => false, 'inactive' => false, 'pending' => false];
    }

    /**
     * The flags a fold sets, for a comparison that names them all.
     *
     * @param \stdClass $entry The entry.
     * @return bool[] active, scheduled, inactive, pending.
     */
    private function flags(\stdClass $entry): array {
        return [
            'active' => $entry->active,
            'scheduled' => $entry->scheduled,
            'inactive' => $entry->inactive,
            'pending' => $entry->pending,
        ];
    }

    /**
     * The controls of the folds below: the three types the plugin always answered each set their own flags.
     *
     * Without them a fold that writes nothing for any type would pass every "rescues nothing" case.
     */
    public function test_the_fold_writes_the_flags_of_the_three_original_types(): void {
        $none = $this->noflags();
        $this->assertSame([...$none, 'active' => true], $this->flags($this->fold('enrolled')));
        $this->assertSame(
            [...$none, 'scheduled' => true, 'inactive' => true],
            $this->flags($this->fold('scheduled'))
        );
        $this->assertSame([...$none, 'pending' => true, 'inactive' => true], $this->flags($this->fold('pending')));
        $this->assertSame($none, $this->flags($this->fold('none')), 'The plugin\'s own none sets nothing.');
        $scheduled = $this->fold('scheduled');
        $this->assertSame([100, 200], [$scheduled->startsat, $scheduled->endsat]);
        $this->assertSame('scheduled', $scheduled->type);
    }

    /**
     * A waiting-list row is pending for the rescue and waitlisted for the card.
     */
    public function test_a_waitlisted_row_rescues_as_pending_and_reads_waitlisted(): void {
        $entry = $this->fold('waitlisted');
        $this->assertSame($this->flags($this->fold('pending')), $this->flags($entry), 'The same flags as pending.');
        $this->assertSame('waitlisted', $entry->type, 'Told apart from pending by its type.');
        $this->assertSame([0, 0, 0], [$entry->startsat, $entry->endsat, $entry->endedat], 'Only a scheduled row carries dates.');
        $this->assertSame('pending', $this->fold('pending', $entry)->type, 'Pending outranks the waiting list.');
    }

    /**
     * A suspended row rescues nothing and reads suspended.
     */
    public function test_a_suspended_row_rescues_nothing_and_reads_suspended(): void {
        $this->assertTrue($this->fold('enrolled')->active, 'Control: the fold writes a flag for a type it knows.');
        $entry = $this->fold('suspended');
        $this->assertSame($this->noflags(), $this->flags($entry));
        $this->assertSame('suspended', $entry->type);
        $this->assertSame(0, $entry->endedat);
    }

    /**
     * An ended row rescues nothing and reads expired, with the latest end date of its kind.
     */
    public function test_an_expired_row_rescues_nothing_and_reads_expired_with_the_latest_end(): void {
        $this->assertTrue($this->fold('scheduled')->scheduled, 'Control: the fold writes a flag for a type it knows.');
        $entry = $this->fold('expired', null, 300);
        $this->assertSame($this->noflags(), $this->flags($entry));
        $this->assertSame('expired', $entry->type);
        $this->assertSame(300, $entry->endedat);
        $this->assertSame(300, $this->fold('expired', $entry, 250)->endedat, 'An earlier end does not replace it.');
        $this->assertSame(400, $this->fold('expired', $entry, 400)->endedat, 'A later one does.');
        $this->assertSame('suspended', $this->fold('suspended', $entry)->type, 'Suspended outranks expired.');
    }

    /**
     * A type this theme has never heard of rescues nothing, reads none, and raises no warning on the way.
     *
     * The rank table has no row for such a type, so folding it untranslated would read an undefined key: a warning
     * on every listing of a site whose plugin is newer than the theme. The warning is turned into a failure here,
     * because the answer alone would be the same.
     */
    public function test_a_type_the_theme_does_not_know_reads_as_none(): void {
        $this->assertTrue($this->fold('pending')->pending, 'Control: the fold writes a flag for a type it knows.');
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            $entry = $this->fold('lapsed');
        } finally {
            restore_error_handler();
        }
        $this->assertSame($this->noflags(), $this->flags($entry));
        $this->assertSame('none', $entry->type);
    }

    /**
     * A waiting-list application rescues as pending and reads waitlisted, read from a real enrol_apply row.
     *
     * Runs only where enrol_apply is installed. A lapsed waiting-list row is the control: no longer awaited, so it
     * rescues nothing, and it reads expired with its own end date (the plugin's rule, owner answer d).
     */
    public function test_a_waiting_list_application_is_pending_in_the_map(): void {
        $this->resetAfterTest();
        if (!\core_component::get_component_directory('enrol_apply')) {
            $this->markTestSkipped('enrol_apply is not installed.');
        }
        $this->enable_enrol('apply');
        $generator = self::getDataGenerator();
        $viewer = $generator->create_user();
        $course = $generator->create_course();
        $this->add_instance($course, 'apply', ['customint6' => 1]);
        $generator->enrol_user($viewer->id, $course->id, null, 'apply', 0, 0, 2);
        $lapsed = $generator->create_course();
        $this->add_instance($lapsed, 'apply', ['customint6' => 1]);
        $generator->enrol_user($viewer->id, $lapsed->id, null, 'apply', 0, time() - DAYSECS, 2);

        $map = classifier::enrolments((int) $viewer->id);

        $this->assertTrue($map[(int) $course->id]->pending);
        $this->assertTrue($map[(int) $course->id]->inactive);
        $this->assertSame(access::RELATIONSHIP_WAITLISTED, $map[(int) $course->id]->type);
        $this->assertFalse($map[(int) $lapsed->id]->pending, 'Control: a lapsed application is no longer awaited.');
        $this->assertSame(access::RELATIONSHIP_EXPIRED, $map[(int) $lapsed->id]->type);
        $this->assertGreaterThan(0, $map[(int) $lapsed->id]->endedat);
    }

    /**
     * A scheduled enrolment is decided before the instance loop: an open self instance beside it must not win.
     *
     * The viewer holds a manual row that starts in three days, on a course whose open self instance would
     * otherwise read OPEN and offer an enrolment that skips the date. The controls are in the same run:
     * somebody with no row reads open on that same course, and somebody with an active row reads enrolled.
     */
    public function test_a_scheduled_enrolment_is_not_overridden_by_an_open_self_instance(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $category = $generator->create_category();
        $course = $generator->create_course(['category' => $category->id]);
        $this->add_instance($course, 'self', ['customint6' => 1, 'customint3' => 5]);

        $start = time() + 3 * DAYSECS;
        $end = $start + 30 * DAYSECS;
        $scheduled = $generator->create_user();
        $generator->enrol_user($scheduled->id, $course->id, 'student', 'manual', $start, $end);
        $outsider = $generator->create_user();
        $member = $generator->create_user();
        $generator->enrol_user($member->id, $course->id);

        $this->become($scheduled);
        $verdict = $this->classify((int) $category->id, [(int) $course->id])[(int) $course->id];
        $this->assertSame(classifier::STATE_SCHEDULED, $verdict['state']);
        $this->assertSame($start, $verdict['startsat']);
        $this->assertSame($end, $verdict['endsat']);
        $this->assertSame(resolution::STATE_SCHEDULED, $this->resolved((int) $course->id)[0], 'The resolver agrees.');

        $this->become($outsider);
        $control = $this->classify((int) $category->id, [(int) $course->id])[(int) $course->id];
        $this->assertSame(classifier::STATE_OPEN, $control['state'], 'Control: with no row the same course is open.');
        $this->assertSame(0, $control['startsat']);

        $this->become($member);
        $this->assertSame(
            classifier::STATE_ENROLLED,
            $this->classify((int) $category->id, [(int) $course->id])[(int) $course->id]['state'],
            'Control: an active row beats everything.'
        );
    }

    /**
     * A guest is never open, sees guest access as free, and holds no favourites.
     */
    public function test_a_guest_is_never_open_but_sees_free_access(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $self = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $self->id, 'self', ['customint6' => 1]);
        $guest = $generator->create_course(['category' => $root->id]);
        $this->enable_instance((int) $guest->id, 'guest');

        $this->setGuestUser();
        $verdicts = $this->classify((int) $root->id, [(int) $self->id, (int) $guest->id]);
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $self->id]['state']);
        $this->assertSame(classifier::STATE_FREE, $verdicts[(int) $guest->id]['state']);
        $this->assertFalse($verdicts[(int) $guest->id]['favourite']);

        // Control: a real user is open on the same course.
        $this->become($generator->create_user());
        $verdicts = $this->classify((int) $root->id, [(int) $self->id]);
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $self->id]['state']);
    }

    /**
     * The opening flag marks a card with nothing on offer whose course carries a window starting later, and no other.
     *
     * The flag says only whom the card asks local_unlistedcourses' batch for an opening date, so it must cover every
     * instance the batch could date. The controls on the same page: a window that has closed, a course open now
     * beside a window still ahead (an offer is the card's own way in), a suspended row (asked for its route line
     * anyway) and the guest account, which can enrol in nothing.
     */
    public function test_the_opening_flag_marks_a_card_with_nothing_on_offer_and_a_window_ahead(): void {
        $this->resetAfterTest();
        $this->enable_enrol('self');
        $generator = self::getDataGenerator();
        $root = $generator->create_category();
        $tomorrow = time() + DAYSECS;
        $make = function (array $fields) use ($generator, $root): \stdClass {
            $course = $generator->create_course(['category' => $root->id]);
            $this->enable_instance((int) $course->id, 'self', $fields + ['customint6' => 1]);
            return $course;
        };
        $ahead = $make(['enrolstartdate' => $tomorrow]);
        $closed = $make(['enrolenddate' => time() - DAYSECS]);
        $open = $make([]);
        $this->add_instance($open, 'self', ['customint6' => 1, 'enrolstartdate' => $tomorrow]);
        $suspended = $make(['enrolstartdate' => $tomorrow]);
        $ids = [(int) $ahead->id, (int) $closed->id, (int) $open->id, (int) $suspended->id];
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $suspended->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);

        $this->become($viewer);
        $verdicts = $this->classify((int) $root->id, $ids);
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $ahead->id]['state'], 'Precondition: nothing on offer yet.');
        $this->assertTrue($verdicts[(int) $ahead->id]['opening'], 'A window starting later: the card asks.');
        $this->assertSame(classifier::STATE_NONE, $verdicts[(int) $closed->id]['state']);
        $this->assertFalse($verdicts[(int) $closed->id]['opening'], 'A window that has closed opens nothing.');
        $this->assertSame(classifier::STATE_OPEN, $verdicts[(int) $open->id]['state']);
        $this->assertFalse($verdicts[(int) $open->id]['opening'], 'An open course is its own way in.');
        $this->assertSame(classifier::STATE_SUSPENDED, $verdicts[(int) $suspended->id]['state']);
        $this->assertFalse($verdicts[(int) $suspended->id]['opening'], 'A relationship is asked for its route line anyway.');

        $this->setGuestUser();
        $verdicts = $this->classify((int) $root->id, [(int) $ahead->id]);
        $this->assertFalse($verdicts[(int) $ahead->id]['opening'], 'The guest account can enrol in nothing.');
    }
}
