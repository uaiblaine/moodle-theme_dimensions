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
 * Theme Dimensions - Tests for who sees which step of a trail
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\trail;

use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use theme_dimensions\local\landingfields;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../category/fixture.php');

/**
 * Who sees which step of a public trail, and how.
 *
 * One trail whose steps refer to one course of each kind. A visitor sees the public course in full and the listed one
 * as a ghost, and nothing of the rest; an account sees what the showcase would list to it, an unlisted course it is
 * enrolled in included, and never a ghost. A step hidden by its restriction is nobody's.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(display::class)]
final class display_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var array The referenced course ids, by kind. */
    private array $refs = [];

    /** @var array The steps' cmids, by kind. */
    private array $cmids = [];

    /** @var \stdClass The trail course. */
    private \stdClass $trail;

    /**
     * Skip without the module; build the trail as an administrator.
     */
    protected function setUp(): void {
        parent::setUp();
        if (\core_component::get_component_directory('mod_subcourse') === null) {
            $this->markTestSkipped('mod_subcourse is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        self::forget();

        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Gestão & Governo']);
        $hidden = $generator->create_category(['name' => 'Oculta', 'visible' => 0]);
        $unlistedcategory = $generator->create_category(['name' => 'Fora da lista']);
        $this->set_category_state((int) $unlistedcategory->id, category_discoverability::STATE_UNLISTED);

        $make = function (string $kind, array $record = []) use ($generator, $root): void {
            $course = $generator->create_course($record + ['category' => $root->id, 'fullname' => "Curso {$kind}"]);
            $this->refs[$kind] = (int) $course->id;
        };
        $make('public');
        $this->set_course_state($this->refs['public'], discoverability::STATE_PUBLIC);
        $make('listed');
        $make('unlistedmine');
        $this->set_course_state($this->refs['unlistedmine'], discoverability::STATE_UNLISTED);
        $make('unlistedclosed');
        $this->set_course_state($this->refs['unlistedclosed'], discoverability::STATE_UNLISTED);
        $make('hidden', ['visible' => 0]);
        // Visible itself: core would otherwise create it hidden, like its category, and the course's own flag
        // would decide what the category's must.
        $make('inhiddencategory', ['category' => $hidden->id, 'visible' => 1]);
        $make('inunlistedcategory', ['category' => $unlistedcategory->id]);
        $make('restricted');
        $this->set_course_state($this->refs['restricted'], discoverability::STATE_PUBLIC);

        $this->trail = $generator->create_course(['category' => $root->id, 'fullname' => 'Trilha']);
        \core_course\customfield\course_handler::create()->instance_form_save((object) [
            'id' => $this->trail->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL,
        ]);
        $this->set_course_state((int) $this->trail->id, discoverability::STATE_PUBLIC);
        foreach ($this->refs as $kind => $refid) {
            $extra = [];
            if ($kind === 'restricted') {
                // Opens in a year, and its restriction hides it entirely until then.
                $extra['availability'] = json_encode([
                    'op' => '&',
                    'c' => [['type' => 'date', 'd' => '>=', 't' => time() + YEARSECS]],
                    'showc' => [false],
                ]);
            }
            $activity = $generator->create_module('subcourse', ['course' => $this->trail->id, 'refcourse' => $refid] + $extra);
            $this->cmids[$kind] = (int) $activity->cmid;
        }
    }

    /**
     * The steps one reader sees, as kind => FULL or GHOST, in the trail's order.
     *
     * @return array
     */
    private function seen(): array {
        $kinds = array_flip($this->cmids);
        $seen = [];
        foreach (display::steps(trail::of(get_course($this->trail->id))) as $entry) {
            $seen[$kinds[$entry['step']->cmid]] = $entry['kind'];
        }
        return $seen;
    }

    /**
     * A visitor sees the public course in full and the listed one as a ghost, and nothing else.
     *
     * The unlisted courses, the hidden one, the ones in a hidden or an unlisted category and the step its restriction
     * hides are not drawn at all; the full step carries its course's row, the ghost carries nothing.
     */
    public function test_a_visitor_sees_the_public_course_and_a_ghost_of_the_listed_one(): void {
        $this->become(null);

        $this->assertSame(['public' => display::FULL, 'listed' => display::GHOST], $this->seen());

        $entries = display::steps(trail::of(get_course($this->trail->id)));
        $this->assertSame($this->refs['public'], (int) $entries[0]['row']->id);
        $this->assertNull($entries[1]['row'], 'A ghost carries nothing read from its course.');
    }

    /**
     * An account sees what the showcase would list to it: the public and listed courses and the unlisted one it is
     * enrolled in, in full; no ghost, and nothing else.
     */
    public function test_an_account_sees_what_the_showcase_lists_to_it_and_no_ghost(): void {
        $learner = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($learner->id, $this->refs['unlistedmine']);
        $this->become($learner);

        $this->assertSame([
            'public' => display::FULL,
            'listed' => display::FULL,
            'unlistedmine' => display::FULL,
        ], $this->seen());
    }

    /**
     * An unlisted course with a way in for this account is drawn to it; the same course without one is not.
     */
    public function test_an_unlisted_course_shows_to_an_account_that_may_enrol(): void {
        $this->enable_enrol('self');
        $this->enable_instance($this->refs['unlistedclosed'], 'self');
        $this->become(self::getDataGenerator()->create_user());

        $seen = $this->seen();
        $this->assertSame(display::FULL, $seen['unlistedclosed'] ?? null, 'Self enrolment open: a way in.');
        $this->assertArrayNotHasKey('unlistedmine', $seen, 'Unlisted, and nothing open to this account.');
    }

    /**
     * Staff see the hidden course and the one in the hidden category, which the showcase lists to them; the step the
     * restriction hides stays hidden from a visitor and a learner, and core shows it to staff.
     */
    public function test_core_visibility_decides_for_staff(): void {
        $this->setAdminUser();
        self::forget();

        $seen = $this->seen();
        $this->assertSame(display::FULL, $seen['hidden'] ?? null);
        $this->assertSame(display::FULL, $seen['inhiddencategory'] ?? null);
        $this->assertSame(display::FULL, $seen['restricted'] ?? null, 'Core shows staff a restricted activity.');
    }
}
