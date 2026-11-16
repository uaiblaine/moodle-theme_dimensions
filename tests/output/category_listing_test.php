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
 * Theme Dimensions - Tests for the category showcase renderables
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course\customfield\course_handler;
use core_course_category;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use theme_dimensions\local\category\branding;
use theme_dimensions\local\category\cards;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\dialogue;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\page_result;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * What the listing, the toolbar and the cards export, state by state.
 *
 * The fixture holds one course per state plus the two kinds of course a
 * viewer's class changes: a hidden course (staff only) and an event already
 * held. Every assertion about a state has a neighbour in the same export, so
 * a card builder that printed one badge for everything cannot pass.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_listing::class)]
#[CoversClass(category_card::class)]
#[CoversClass(dialogue::class)]
#[CoversClass(category_toolbar::class)]
#[CoversClass(category_panel::class)]
final class category_listing_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string A valid 1x1 PNG, for the one directory card that carries a branding image. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /**
     * Seed a root with one course per state, a child category, a hidden course and a held event.
     *
     * @return \stdClass The viewer, a student enrolled in one course.
     */
    private function seed(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(facets::SETTING, landingfields::SHORTNAME_MODALITY, 'theme_dimensions');
        $this->enable_enrol('self');
        $this->enable_enrol('guest');

        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Root & Co']);
        $child = $generator->create_category(['parent' => $root->id, 'name' => 'Brigada']);
        $viewer = $generator->create_user();
        $handler = course_handler::create();

        $make = function (array $course, array $fields = []) use ($generator, $handler): int {
            $record = $generator->create_course($course);
            $data = ['id' => $record->id, 'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE];
            foreach ($fields as $shortname => $value) {
                $data['customfield_' . $shortname] = $value;
            }
            $handler->instance_form_save((object) $data);
            return (int) $record->id;
        };

        $this->ids['enrolled'] = $make(['category' => $root->id, 'fullname' => 'Resgate & Salvamento',
            'summary' => '<p>Resumo do <b>curso</b>.</p>', 'summaryformat' => FORMAT_HTML], [
            landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
            landingfields::SHORTNAME_CITY => 'São Paulo',
            landingfields::SHORTNAME_WORKLOAD => 36,
            landingfields::SHORTNAME_PROGRAMME . '_editor' => [
                'text' => '<p>Um</p><p>Dois</p><ul><li>Três</li></ul>',
                'format' => FORMAT_HTML,
            ],
        ]);
        $generator->enrol_user($viewer->id, $this->ids['enrolled']);

        $this->ids['open'] = $make(['category' => $child->id, 'fullname' => 'Curso Aberto'], [
            landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_ONLINE,
        ]);
        $this->enable_instance($this->ids['open'], 'self');

        $this->ids['free'] = $make(['category' => $root->id, 'fullname' => 'Curso Livre']);
        $this->enable_instance($this->ids['free'], 'guest');

        $this->ids['none'] = $make(['category' => $root->id, 'fullname' => 'Curso Fechado']);

        $this->ids['held'] = (int) $generator->create_course([
            'category' => $root->id,
            'fullname' => 'Evento Passado',
            'startdate' => time() - 3 * DAYSECS,
            'enddate' => time() - 2 * DAYSECS,
        ])->id;
        $handler->instance_form_save((object) [
            'id' => $this->ids['held'],
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_EVENT,
        ]);
        $this->enable_instance($this->ids['held'], 'self');

        $this->ids['hidden'] = $make(['category' => $root->id, 'fullname' => 'Curso Oculto', 'visible' => 0]);
        $this->ids['root'] = (int) $root->id;
        $this->ids['child'] = (int) $child->id;
        return $viewer;
    }

    /**
     * Export the listing for the current user.
     *
     * @param array $options Criteria options.
     * @param string $view The view.
     * @param bool $firstpaint Whether the listing is the page's own first paint, as the page's entries build it.
     * @return \stdClass The exported context.
     */
    private function export(
        array $options = [],
        string $view = category_toolbar::VIEW_CARDS,
        bool $firstpaint = false
    ): \stdClass {
        global $PAGE;
        $PAGE->set_url('/course/index.php', ['categoryid' => $this->ids['root']]);
        // The page's own request asks for its posters (criteria::from_request()); a test may say otherwise.
        $criteria = new criteria($this->ids['root'], $options + ['withposters' => true]);
        $result = population::resolve($criteria);
        $category = core_course_category::get($this->ids['root'], MUST_EXIST, true);
        $listing = new category_listing($category, $criteria, $result, $view, '', null, $firstpaint);
        return $listing->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * The exported cards keyed by course id.
     *
     * @param \stdClass $context The exported listing.
     * @return \stdClass[]
     */
    private function cards(\stdClass $context): array {
        $cards = [];
        foreach ($context->cards as $card) {
            $cards[(int) $card->id] = $card;
        }
        return $cards;
    }

    /**
     * Each state prints its badge and its call to action; the muted ones print none.
     */
    public function test_each_state_prints_its_badge_and_call_to_action(): void {
        global $DB;

        $viewer = $this->seed();
        $this->become($viewer);
        /* The flat organisation: one of the fixture's courses sits in the child, and the grouped
           page lists only this category's own courses. What is under test is the card a state
           produces, which is the same card in either organisation. */
        $context = $this->export(['sort' => criteria::SORT_NAME]);
        $cards = $this->cards($context);

        /* The five admitted courses in the order this organisation asks for, by name, the hidden
           one withheld. The state-first order the grouped default keeps is asserted where it is
           the subject, in population_test::test_state_groups_come_first_then_core_order_unless_the_hatch_is_off;
           what matters here is that every state is on the page and drew its own card. */
        $this->assertSame(
            [$this->ids['open'], $this->ids['none'], $this->ids['free'], $this->ids['held'], $this->ids['enrolled']],
            array_keys($cards)
        );

        $enrolled = $cards[$this->ids['enrolled']];
        $this->assertSame('Resgate & Salvamento', $enrolled->fullname, 'Plain spelling, escaped by the template.');
        $this->assertSame('dim-badge dim-badge--enrolled', $enrolled->badge['classes']);
        $this->assertSame(get_string('category_state_enrolled', 'theme_dimensions'), $enrolled->badge['label']);
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $enrolled->cta['label']);
        $this->assertStringContainsString('/course/view.php?id=' . $this->ids['enrolled'], $enrolled->cta['url']);
        $this->assertTrue($enrolled->cta['primary']);
        $this->assertFalse($enrolled->ismuted);
        $this->assertNull($enrolled->overline, 'A course in the listed root has no overline.');
        $hours = get_string('landing_hours', 'theme_dimensions', '36');
        $this->assertSame($hours . ' · In-person · São Paulo', $enrolled->meta);
        // The summary and the programme are the dialogue's, fetched when it opens; the card carries neither.
        $this->assertObjectNotHasProperty('excerpt', $enrolled);
        $this->assertObjectNotHasProperty('learn', $enrolled);
        $content = dialogue::content($DB->get_record('course', ['id' => $this->ids['enrolled']], dialogue::COURSE_FIELDS));
        $this->assertSame('Resumo do curso.', $content['excerpt']);
        $this->assertSame(['Um', 'Dois', 'Três'], $content['learn']);
        $this->assertTrue($enrolled->showstar);
        $this->assertFalse($enrolled->favourite);

        $open = $cards[$this->ids['open']];
        $this->assertSame('dim-badge dim-badge--open', $open->badge['classes']);
        $this->assertSame(get_string('category_cta_enrol', 'theme_dimensions'), $open->cta['label']);
        $this->assertStringContainsString('landing', $open->cta['url'], 'A course with a model enrols on its landing page.');
        $this->assertSame('Brigada', $open->overline, 'A course in a child names it.');

        $free = $cards[$this->ids['free']];
        $this->assertSame('dim-badge dim-badge--free', $free->badge['classes']);
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $free->cta['label']);
        $this->assertStringContainsString('/course/view.php', $free->cta['url']);

        $none = $cards[$this->ids['none']];
        $this->assertSame('dim-badge dim-badge--none', $none->badge['classes']);
        $this->assertNull($none->cta, 'Shown, muted, no call to action and no reason.');
        $this->assertTrue($none->ismuted);

        $held = $cards[$this->ids['held']];
        $this->assertSame(get_string('category_state_held', 'theme_dimensions'), $held->badge['label']);
        $this->assertNull($held->cta, 'An event already held enrols nobody, whatever its instance says.');
        $this->assertTrue($held->ismuted);
        $this->assertTrue($held->isevent);
        $this->assertNotNull($held->datetile);
    }

    /**
     * A course the viewer is enrolled in from a later date draws its own badge with the date, and a counted chip.
     *
     * It is the viewer's course but not yet open to them: the card is not muted, offers no way in, and the details
     * button goes to the landing page. The controls are the cards of the same page that are not scheduled. The course
     * offers no other method, so there is no route line (the route is
     * test_a_relationship_card_prints_a_route_only_where_the_batch_reports_one's subject).
     */
    public function test_a_scheduled_course_prints_the_date_and_a_chip(): void {
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $handler = course_handler::create();
        $course = $generator->create_course(['category' => $this->ids['root'], 'fullname' => 'Curso Agendado']);
        $handler->instance_form_save((object) [
            'id' => $course->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        $start = time() + 6 * DAYSECS;
        $generator->enrol_user($viewer->id, $course->id, 'student', 'manual', $start, 0);

        $this->become($viewer);
        $context = $this->export(['sort' => criteria::SORT_NAME]);
        $cards = $this->cards($context);

        $card = $cards[(int) $course->id];
        $date = userdate($start, get_string('strftimedatefullshort', 'langconfig'));
        $this->assertSame('scheduled', $card->state);
        $this->assertSame('dim-badge dim-badge--scheduled', $card->badge['classes']);
        $this->assertSame(get_string('category_state_scheduled', 'theme_dimensions', $date), $card->badge['label']);
        $this->assertFalse($card->ismuted, 'The course is the viewer\'s: it is not drawn as unavailable.');
        $this->assertSame(get_string('category_cta_details', 'theme_dimensions'), $card->cta['label']);
        $this->assertFalse($card->cta['primary'], 'The way to the details is the quiet button.');
        $this->assertSame(links::page((int) $course->id)->out(false), $card->cta['url'], 'To the landing page, not the course.');
        $this->assertStringNotContainsString('/course/view.php', $card->url);
        $this->assertSame('fa-calendar', $card->badge['icon']);
        $this->assertFalse($card->hasroute, 'No other method: no route line.');
        $this->assertSame('', $card->routelabel);

        $this->assertSame('open', $cards[$this->ids['open']]->state, 'Control: the open course on the same page is open.');
        $this->assertSame('enrolled', $cards[$this->ids['enrolled']]->state, 'Control: the enrolled one is enrolled.');

        $options = [];
        foreach ($context->toolbar->statusgroup['options'] as $option) {
            $options[$option['value']] = $option['count'];
        }
        $this->assertSame(1, $options[classifier::STATE_SCHEDULED]);
        $this->assertSame(1, $options[classifier::STATE_ENROLLED], 'Control: the enrolled chip does not count it.');
    }

    /**
     * Guest access behind a key wears its own pill and sends the reader to the key form, never into the course.
     *
     * The key is asked by enrol_guest's widget on the standard enrolment page, which the landing page does not draw, so the
     * call to action goes there with the landing page forward bypassed, even for a course that chose a landing page model. The
     * control is the free course of the same page: guest access with no password keeps its free badge and its
     * "Access" to the course.
     */
    public function test_guest_access_behind_a_key_is_not_offered_as_free_access(): void {
        $viewer = $this->seed();
        $course = self::getDataGenerator()->create_course(['category' => $this->ids['root'], 'fullname' => 'Curso com Chave']);
        $keyed = (int) $course->id;
        course_handler::create()->instance_form_save((object) [
            'id' => $keyed,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
        ]);
        $this->enable_instance($keyed, 'guest', ['password' => 'chave']);

        $this->become($viewer);
        $cards = $this->cards($this->export(['sort' => criteria::SORT_NAME]));

        $card = $cards[$keyed];
        $this->assertSame(classifier::STATE_KEY, $card->state);
        $this->assertSame('dim-badge dim-badge--key', $card->badge['classes']);
        $this->assertSame('fa-key', $card->badge['icon']);
        $this->assertSame(get_string('category_state_key', 'theme_dimensions'), $card->badge['label']);
        $this->assertSame(get_string('category_cta_key', 'theme_dimensions'), $card->cta['label']);
        $this->assertTrue($card->cta['primary']);
        $this->assertSame(
            (new \moodle_url('/enrol/index.php', ['id' => $keyed, 'landing' => 0]))->out(false),
            $card->cta['url'],
            'The key form, past the landing page forward.'
        );
        $this->assertStringNotContainsString('/course/view.php', $card->url);
        $this->assertFalse($card->ismuted);
        $this->assertFalse($card->hasroute, 'Key is an offer, not a relationship: no route line.');

        $free = $cards[$this->ids['free']];
        $this->assertSame(classifier::STATE_FREE, $free->state, 'Control: no password is still free access.');
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $free->cta['label']);
        $this->assertStringContainsString('/course/view.php?id=' . $this->ids['free'], $free->cta['url']);
    }

    /**
     * A relationship card prints a route line and its "Enrol now" only where local_unlistedcourses' batch reports a route.
     *
     * A viewer whose enrolment starts later, was suspended or
     * ended may be told that a method would take them now. Each card with a route has a twin without one - the same
     * relationship on a course offering no method - and the route's destination is the card's own enrolment surface,
     * the landing page where the course has a model. Staff, who may walk in, get "Access" and no route on the same course.
     */
    public function test_a_relationship_card_prints_a_route_only_where_the_batch_reports_one(): void {
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $handler = course_handler::create();
        $root = $this->ids['root'];
        $make = function (string $name, bool $route, bool $model) use ($generator, $handler, $root): int {
            $course = $generator->create_course(['category' => $root, 'fullname' => $name]);
            if ($model) {
                $handler->instance_form_save((object) [
                    'id' => $course->id,
                    'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
                ]);
            }
            if ($route) {
                $this->enable_instance((int) $course->id, 'self', ['customint6' => 1]);
            }
            return (int) $course->id;
        };
        $start = time() + 6 * DAYSECS;
        $end = time() - 2 * DAYSECS;
        $ids = [
            'scheduledroute' => $make('Agendado com via', true, true),
            'suspendedroute' => $make('Suspenso com via', true, true),
            'suspended' => $make('Suspenso sem via', false, true),
            'expiredroute' => $make('Encerrado com via', true, false),
            'expired' => $make('Encerrado sem via', false, false),
        ];
        $generator->enrol_user($viewer->id, $ids['scheduledroute'], 'student', 'manual', $start, 0);
        foreach (['suspendedroute', 'suspended'] as $role) {
            $generator->enrol_user($viewer->id, $ids[$role], 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        }
        foreach (['expiredroute', 'expired'] as $role) {
            $generator->enrol_user($viewer->id, $ids[$role], 'student', 'manual', $end - 9 * DAYSECS, $end);
        }

        $this->become($viewer);
        $cards = $this->cards($this->export(['sort' => criteria::SORT_NAME]));
        $routelabel = get_string('category_route_enrol', 'theme_dimensions');
        $enrolnow = get_string('category_cta_enrolnow', 'theme_dimensions');
        $date = userdate($end, get_string('strftimedatefullshort', 'langconfig'));

        $scheduled = $cards[$ids['scheduledroute']];
        $this->assertSame(classifier::STATE_SCHEDULED, $scheduled->state);
        $this->assertTrue($scheduled->hasroute);
        $this->assertSame($routelabel, $scheduled->routelabel);
        $this->assertSame($enrolnow, $scheduled->cta['label'], 'The route replaces the details button.');
        $this->assertFalse($scheduled->cta['primary']);
        $this->assertSame(links::page($ids['scheduledroute'])->out(false), $scheduled->cta['url'], 'Where the methods are listed.');

        $suspended = $cards[$ids['suspendedroute']];
        $this->assertSame(classifier::STATE_SUSPENDED, $suspended->state);
        $this->assertSame('dim-badge dim-badge--suspended', $suspended->badge['classes']);
        $this->assertSame('fa-circle-pause', $suspended->badge['icon']);
        $this->assertSame(get_string('category_state_suspended', 'theme_dimensions'), $suspended->badge['label']);
        $this->assertTrue($suspended->hasroute);
        $this->assertSame($enrolnow, $suspended->cta['label']);
        $this->assertSame(links::page($ids['suspendedroute'])->out(false), $suspended->cta['url']);
        $this->assertFalse($suspended->ismuted, 'The course is the viewer\'s: it is not drawn as unavailable.');

        $plain = $cards[$ids['suspended']];
        $this->assertSame(classifier::STATE_SUSPENDED, $plain->state, 'Control: the same relationship.');
        $this->assertFalse($plain->hasroute, 'No method, no route line.');
        $this->assertSame('', $plain->routelabel);
        $this->assertNull($plain->cta, 'And no call to action.');

        $expired = $cards[$ids['expiredroute']];
        $this->assertSame(classifier::STATE_EXPIRED, $expired->state);
        $this->assertSame('dim-badge dim-badge--expired', $expired->badge['classes']);
        $this->assertSame(get_string('category_state_expired', 'theme_dimensions', $date), $expired->badge['label']);
        $this->assertTrue($expired->hasroute);
        $this->assertStringContainsString(
            '/enrol/index.php?id=' . $ids['expiredroute'],
            $expired->cta['url'],
            'No model: core\'s own enrolment page.'
        );

        $expiredplain = $cards[$ids['expired']];
        $this->assertSame(classifier::STATE_EXPIRED, $expiredplain->state);
        $this->assertFalse($expiredplain->hasroute);
        $this->assertNull($expiredplain->cta);

        $this->assertFalse($cards[$this->ids['open']]->hasroute, 'Control: an open card is the route itself, no line.');

        // The same viewer, now allowed to view the course without an enrolment: the course is their way in.
        $roleid = $generator->create_role();
        $context = \core\context\course::instance($ids['suspendedroute']);
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $viewer->id, $context->id);
        $this->become($viewer);
        $staff = $this->cards($this->export(['sort' => criteria::SORT_NAME]))[$ids['suspendedroute']];
        $this->assertSame(classifier::STATE_SUSPENDED, $staff->state, 'Precondition: the same relationship.');
        $this->assertFalse($staff->hasroute, 'Somebody who may walk in is offered the course, not a route.');
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $staff->cta['label']);
    }

    /**
     * The card prints a route only beside a relationship, whatever routes it is handed, and a conditional card needs its course.
     *
     * The card is built directly from the page's own rows with the state swapped, so its own guards are what is tested:
     * an open card handed a route draws no line (the control is the same course read as suspended, which does), and a
     * conditional card handed no prerequisite reads as none rather than naming nothing.
     */
    public function test_a_card_prints_a_route_only_beside_a_relationship(): void {
        global $PAGE;

        $viewer = $this->seed();
        $this->become($viewer);
        $result = population::resolve(new criteria($this->ids['root'], ['sort' => criteria::SORT_NAME]));
        $courses = cards::records($result->ids());
        $values = cards::values($result->rows);
        $renderer = $PAGE->get_renderer('core');
        $id = $this->ids['open'];
        $course = $courses[$id];
        $value = $values[$id];
        $base = $result->rows[$id];
        $export = function (string $state, array $routes, ?\stdClass $pre = null) use ($course, $value, $base, $renderer) {
            $row = clone $base;
            $row->state = $state;
            $row->startsat = 0;
            $row->endsat = 0;
            $card = new category_card($course, $row, $value, null, null, true, false, $routes, $pre);
            return $card->export_for_template($renderer);
        };
        $route = [['kind' => 'self', 'instanceid' => 1]];

        $this->assertFalse($export(classifier::STATE_OPEN, $route)->hasroute, 'An open card is the route itself.');
        $this->assertTrue($export(classifier::STATE_SUSPENDED, $route)->hasroute, 'Control: beside a relationship it is drawn.');
        $this->assertFalse($export(classifier::STATE_SUSPENDED, [])->hasroute, 'And never without a route.');

        $orphan = $export(classifier::STATE_CONDITIONAL, []);
        $this->assertSame(classifier::STATE_NONE, $orphan->state, 'No prerequisite to name: nothing on offer.');
        $this->assertNull($orphan->cta);
        $named = $export(classifier::STATE_CONDITIONAL, [], (object) ['id' => $this->ids['enrolled'], 'fullname' => 'Base']);
        $this->assertSame(classifier::STATE_CONDITIONAL, $named->state, 'Control: with its course it is conditional.');
    }

    /**
     * A card says when enrolment opens only where local_unlistedcourses dates the window as the one refusal.
     *
     * Every course offers one self instance; on the dated ones its window opens tomorrow. The card with nothing on
     * offer, the suspended one and the ended one print the plugin's date under the pill. The controls on the same
     * page: a window that has closed, the open course of the fixture, a window ahead restricted to a cohort the
     * viewer is outside (the plugin gives no date), a scheduled enrolment beside a window ahead (its card has a
     * date of its own), staff who may walk in, and a visitor, whom the plugin dates nothing.
     */
    public function test_a_card_says_when_enrolment_opens_only_where_the_window_is_the_one_refusal(): void {
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $root = $this->ids['root'];
        $tomorrow = time() + DAYSECS;
        $make = function (string $name, array $fields) use ($generator, $root): int {
            $course = $generator->create_course(['category' => $root, 'fullname' => $name]);
            $this->enable_instance((int) $course->id, 'self', $fields + ['customint6' => 1]);
            return (int) $course->id;
        };
        $cohort = $generator->create_cohort();
        $ids = [
            'ahead' => $make('Abre amanha', ['enrolstartdate' => $tomorrow]),
            'suspended' => $make('Suspenso abre amanha', ['enrolstartdate' => $tomorrow]),
            'expired' => $make('Encerrado abre amanha', ['enrolstartdate' => $tomorrow]),
            'scheduled' => $make('Agendado abre amanha', ['enrolstartdate' => $tomorrow]),
            'closed' => $make('Janela encerrada', ['enrolenddate' => time() - DAYSECS]),
            'cohort' => $make('Somente a turma', ['enrolstartdate' => $tomorrow, 'customint5' => (int) $cohort->id]),
        ];
        $generator->enrol_user($viewer->id, $ids['suspended'], 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $generator->enrol_user($viewer->id, $ids['expired'], 'student', 'manual', time() - 9 * DAYSECS, time() - 2 * DAYSECS);
        $generator->enrol_user($viewer->id, $ids['scheduled'], 'student', 'manual', time() + 6 * DAYSECS, 0);

        $this->become($viewer);
        $cards = $this->cards($this->export(['sort' => criteria::SORT_NAME]));
        $line = get_string(
            'category_state_enrolmentopens',
            'theme_dimensions',
            userdate($tomorrow, get_string('strftimedatefullshort', 'langconfig'))
        );

        $expected = [
            'ahead' => classifier::STATE_NONE,
            'suspended' => classifier::STATE_SUSPENDED,
            'expired' => classifier::STATE_EXPIRED,
        ];
        foreach ($expected as $role => $state) {
            $card = $cards[$ids[$role]];
            $this->assertSame($state, $card->state, "Precondition: the {$role} card.");
            $this->assertTrue($card->hasopens, "The {$role} card says when enrolment opens.");
            $this->assertSame($line, $card->openslabel);
            $this->assertFalse($card->hasroute, 'Nothing takes the viewer now: no route line beside it.');
        }
        $this->assertSame('dim-badge dim-badge--none', $cards[$ids['ahead']]->badge['classes'], 'The pill is unchanged.');

        $controls = [
            'closed' => [classifier::STATE_NONE, 'A window that has closed opens nothing.'],
            'cohort' => [classifier::STATE_NONE, 'A cohort the viewer is outside refuses them at opening too.'],
            'scheduled' => [classifier::STATE_SCHEDULED, 'A scheduled card carries its own date.'],
        ];
        foreach ($controls as $role => [$state, $message]) {
            $card = $cards[$ids[$role]];
            $this->assertSame($state, $card->state, "Precondition: the {$role} card.");
            $this->assertFalse($card->hasopens, $message);
            $this->assertSame('', $card->openslabel);
        }
        $this->assertFalse($cards[$this->ids['open']]->hasopens, 'An open course is its own way in.');
        $this->assertFalse($cards[$this->ids['none']]->hasopens, 'No window at all: nothing to wait for.');

        // The same viewer, now allowed to view the course without an enrolment: the course is their way in.
        $roleid = $generator->create_role();
        $context = \core\context\course::instance($ids['ahead']);
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $viewer->id, $context->id);
        $this->become($viewer);
        $staff = $this->cards($this->export(['sort' => criteria::SORT_NAME]))[$ids['ahead']];
        $this->assertSame(classifier::STATE_NONE, $staff->state, 'Precondition: still nothing on offer.');
        $this->assertFalse($staff->hasopens, 'Somebody who may walk in is offered the course, not a date.');

        // A visitor is dated nothing by the plugin, the card's only source of a date.
        $this->become(null);
        $this->assertNull(\local_unlistedcourses\access::get_next_actions([$ids['ahead']])[$ids['ahead']]['opens']);
        $this->assertNull(\local_unlistedcourses\access::get_next_action($ids['ahead'])['opens']);
    }

    /**
     * The card prints the opening date only on none, suspended and expired, and never for a visitor or a held event.
     *
     * The card is built directly from the page's own rows with the state swapped and a date handed in, so its own
     * guard is what is tested; the none card of the same course is the control that the line is drawn at all.
     */
    public function test_a_card_prints_the_opening_date_only_where_it_has_no_date_or_route_of_its_own(): void {
        global $PAGE;

        $viewer = $this->seed();
        $this->become($viewer);
        $result = population::resolve(new criteria($this->ids['root'], ['sort' => criteria::SORT_NAME]));
        $courses = cards::records($result->ids());
        $values = cards::values($result->rows);
        $renderer = $PAGE->get_renderer('core');
        $opens = time() + 3 * DAYSECS;
        $rows = $result->rows;
        $export = function (int $id, string $state, bool $anonymous = false) use ($courses, $values, $rows, $renderer, $opens) {
            $row = clone $rows[$id];
            $row->state = $state;
            $row->startsat = 0;
            $row->endsat = 0;
            $card = new category_card($courses[$id], $row, $values[$id], null, null, true, $anonymous, [], null, $opens);
            return $card->export_for_template($renderer);
        };
        $line = get_string(
            'category_state_enrolmentopens',
            'theme_dimensions',
            userdate($opens, get_string('strftimedatefullshort', 'langconfig'))
        );

        $id = $this->ids['none'];
        foreach ([classifier::STATE_NONE, classifier::STATE_SUSPENDED, classifier::STATE_EXPIRED] as $state) {
            $card = $export($id, $state);
            $this->assertTrue($card->hasopens, "A {$state} card prints the date.");
            $this->assertSame($line, $card->openslabel);
        }
        // Conditional is left out: handed no prerequisite, the card reads it as none (see the route test above).
        $others = array_diff(classifier::states(), [
            classifier::STATE_NONE,
            classifier::STATE_SUSPENDED,
            classifier::STATE_EXPIRED,
            classifier::STATE_CONDITIONAL,
        ]);
        $this->assertCount(7, $others, 'Every other state is a control.');
        foreach ($others as $state) {
            $this->assertFalse($export($id, $state)->hasopens, "A {$state} card has a date or a way of its own.");
        }
        $this->assertFalse($export($id, classifier::STATE_NONE, true)->hasopens, 'A visitor\'s card knows no viewer.');
        $this->assertSame('', $export($id, classifier::STATE_NONE, true)->openslabel);
        $this->assertFalse($export($this->ids['held'], classifier::STATE_NONE)->hasopens, 'An event already held enrols nobody.');
    }

    /**
     * A waiting-list or pending application prints its pill and "View status", and a route line where one exists.
     *
     * Runs only where enrol_apply is installed. The pending card is the control of the waiting list's own pill.
     */
    public function test_an_application_prints_its_pill_and_keeps_view_status(): void {
        global $DB;

        if (!\core_component::get_component_directory('enrol_apply')) {
            $this->markTestSkipped('enrol_apply is not installed.');
        }
        $viewer = $this->seed();
        $this->enable_enrol('apply');
        $generator = self::getDataGenerator();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $ids = [];
        foreach (['waiting' => 2, 'pending' => ENROL_USER_SUSPENDED] as $role => $status) {
            $course = $generator->create_course(['category' => $this->ids['root'], 'fullname' => "Candidatura {$role}"]);
            enrol_get_plugin('apply')->add_instance($course, [
                'status' => ENROL_INSTANCE_ENABLED,
                'roleid' => $studentrole->id,
                'customint6' => 1,
            ]);
            $this->enable_instance((int) $course->id, 'self', ['customint6' => 1]);
            $generator->enrol_user($viewer->id, $course->id, null, 'apply', 0, 0, $status);
            $ids[$role] = (int) $course->id;
        }

        $this->become($viewer);
        $cards = $this->cards($this->export(['sort' => criteria::SORT_NAME]));
        $waiting = $cards[$ids['waiting']];
        $this->assertSame(classifier::STATE_WAITLISTED, $waiting->state);
        $this->assertSame('dim-badge dim-badge--waitlisted', $waiting->badge['classes']);
        $this->assertSame(get_string('category_state_waitlisted', 'theme_dimensions'), $waiting->badge['label']);
        $this->assertSame(get_string('category_cta_status', 'theme_dimensions'), $waiting->cta['label']);
        $this->assertTrue($waiting->hasroute, 'The self instance beside the application is a route.');
        $pending = $cards[$ids['pending']];
        $this->assertSame(classifier::STATE_PENDING, $pending->state, 'Control: the other application is pending.');
        $this->assertSame(get_string('category_state_pending', 'theme_dimensions'), $pending->badge['label']);
        $this->assertSame(get_string('category_cta_status', 'theme_dimensions'), $pending->cta['label']);
    }

    /**
     * A conditional card names its prerequisite in the plain spelling, and its button opens that course.
     *
     * The name carries a bare ampersand and a bare "<", the two characters format_string() escapes and a double
     * stash escapes again: the export holds them plain and the rendered card escapes each once. A viewer not
     * enrolled in the prerequisite is the control, told nothing on the same course.
     */
    public function test_a_conditional_card_names_its_prerequisite_once_escaped(): void {
        global $OUTPUT;

        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $elsewhere = $generator->create_category();
        $prerequisite = $generator->create_course(['category' => $elsewhere->id, 'fullname' => 'Base & Prática < 2']);
        $course = $generator->create_course(['category' => $this->ids['root'], 'fullname' => 'Curso Condicional']);
        $this->completed_instance($course, (int) $prerequisite->id);
        $generator->enrol_user($viewer->id, $prerequisite->id);

        $this->become($viewer);
        $cards = $this->cards($this->export(['sort' => criteria::SORT_NAME]));
        $card = $cards[(int) $course->id];
        $this->assertSame(classifier::STATE_CONDITIONAL, $card->state);
        $this->assertSame('dim-badge dim-badge--conditional', $card->badge['classes']);
        $this->assertSame('fa-lock', $card->badge['icon']);
        $this->assertSame(
            get_string('category_state_conditional', 'theme_dimensions', 'Base & Prática < 2'),
            $card->badge['label'],
            'The plain spelling: the template escapes it.'
        );
        $this->assertSame(get_string('category_cta_prerequisite', 'theme_dimensions'), $card->cta['label']);
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => $prerequisite->id]))->out(false), $card->cta['url']);
        $this->assertFalse($card->cta['primary']);
        $this->assertFalse($card->ismuted);
        $this->assertFalse($card->hasroute);

        $html = $OUTPUT->render_from_template('theme_dimensions/category_card', $card);
        $this->assertStringContainsString('Base &amp; Prática &lt; 2', $html, 'Escaped once by the double stash.');
        $this->assertStringNotContainsString('&amp;amp;', $html, 'Never twice.');

        $this->become($generator->create_user());
        $stranger = $this->cards($this->export(['sort' => criteria::SORT_NAME]));
        $this->assertSame(classifier::STATE_NONE, $stranger[(int) $course->id]->state, 'Control: nobody else is told.');
        $this->assertStringNotContainsString('Base', $stranger[(int) $course->id]->badge['label']);
    }

    /**
     * Staff see the hidden course with its badge; the star is not drawn for the guest account.
     */
    public function test_staff_see_the_hidden_badge_and_a_guest_gets_no_star(): void {
        $this->seed();
        $this->setAdminUser();
        self::forget();
        $context = $this->export();
        $cards = $this->cards($context);
        $this->assertArrayHasKey($this->ids['hidden'], $cards);
        $this->assertTrue($cards[$this->ids['hidden']]->ishidden);
        $this->assertFalse($cards[$this->ids['none']]->ishidden, 'Control: a visible course carries no hidden badge.');
        $this->assertTrue($context->toolbar->hasfavourites, 'Control: a real account gets the favourites toggle.');

        $this->setGuestUser();
        self::forget();
        $context = $this->export();
        $cards = $this->cards($context);
        $this->assertArrayNotHasKey($this->ids['hidden'], $cards);
        $this->assertFalse($cards[$this->ids['none']]->showstar, 'No star for the guest account.');
        $this->assertTrue($context->toolbar->hasstatusgroup, 'Precondition: the guest account is offered the states.');
        $this->assertFalse($context->toolbar->hasfavourites, 'And no favourites toggle: the guest account has no favourites.');
        $this->assertNull($context->toolbar->favourites);
    }

    /**
     * The Filters control is drawn only while its panel would show a group: a facet, or "More statuses" with an option.
     *
     * Three cases of the one rule: the viewer with a configured field and no state group (the state sort off) keeps the
     * control for the facet; a viewer with the state group and no configured field keeps it for "More statuses" (the
     * fixture's free course is outside the line's scope); neither draws it hidden.
     *
     * Changes that must make it fail: the facets left out of the rule; "More statuses" left out of it.
     */
    public function test_the_filters_control_needs_a_facet_or_a_more_statuses_option(): void {
        $viewer = $this->seed();
        $this->become($viewer);

        $both = $this->export()->toolbar;
        $this->assertTrue($both->hasfacets);
        $this->assertNotEmpty($both->more['options']);
        $this->assertTrue($both->hasfiltergroups);

        // A facet alone: with the state sort off the viewer has no state group, so no "More statuses" either.
        set_config(population::SETTING_STATESORT, '0', 'theme_dimensions');
        $facetsonly = $this->export()->toolbar;
        $this->assertNull($facetsonly->more);
        $this->assertTrue($facetsonly->hasfacets);
        $this->assertTrue($facetsonly->hasfiltergroups, 'A configured field is a group.');

        // The "More statuses" group alone: no allowlisted field.
        set_config(population::SETTING_STATESORT, '1', 'theme_dimensions');
        set_config(facets::SETTING, '', 'theme_dimensions');
        $moreonly = $this->export()->toolbar;
        $this->assertFalse($moreonly->hasfacets);
        $this->assertTrue($moreonly->more['hasoptions']);
        $this->assertTrue($moreonly->hasfiltergroups, '"More statuses" with an option is a group.');

        // Neither: the state sort off and no field. The control is drawn hidden.
        set_config(population::SETTING_STATESORT, '0', 'theme_dimensions');
        $neither = $this->export()->toolbar;
        $this->assertNull($neither->more);
        $this->assertFalse($neither->hasfacets);
        $this->assertFalse($neither->hasfiltergroups);
    }

    /**
     * The toolbar offers the states the viewer holds courses in, with post-filter counts, and the facet groups.
     */
    public function test_the_toolbar_offers_counted_chips_and_facets(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $context = $this->export();
        $toolbar = $context->toolbar;

        $this->assertTrue($toolbar->hasstatusgroup);
        $options = [];
        foreach ($toolbar->statusgroup['options'] as $option) {
            $options[$option['value']] = $option['count'];
        }
        // All, then the states with a course: open counts the held event, whose instance is still
        // enabled (the classifier may only err towards open); scheduled and pending are not offered
        // at zero, "none" has no chip at all, and the favourites are a toggle of their own.
        $this->assertSame(
            ['' => 5, classifier::STATE_ENROLLED => 1, classifier::STATE_OPEN => 2, classifier::STATE_FREE => 1],
            $options
        );
        $this->assertTrue($toolbar->statusgroup['options'][0]['checked']);
        $this->assertTrue($toolbar->hasfavourites);
        $this->assertSame(
            ['name' => 'fav', 'value' => 1, 'pressed' => false, 'count' => 0, 'label' => 'Favourites'],
            $toolbar->favourites,
            'Released and drawn even at zero, a press turning it on.'
        );

        $this->assertTrue($toolbar->hasfacets);
        $this->assertCount(1, $toolbar->facets);
        $facet = $toolbar->facets[0];
        $this->assertSame(criteria::PARAM_FIELD_PREFIX . array_key_first(facets::fields()), $facet['name']);
        $labels = array_column($facet['options'], 'count', 'label');
        $this->assertSame(['Online' => 1, 'In-person' => 1], $labels);
        $this->assertTrue($facet['anychecked']);
        $this->assertFalse($toolbar->hasfilters);
        $this->assertFalse($toolbar->searchtooshort);
        /* The two calendar facts the client is given rather than allowed to compute, read off the
           export because the markup test can only read the template's example context. `today` is
           the site's own day: a browser's clock is the reader's, and for part of every day the two
           name different days, so a calendar that asked it would mark a cell the "Today" link
           beside it does not filter by. */
        $this->assertSame(criteria::iso(time()), $toolbar->today);
        $this->assertSame(criteria::week_start_day(), $toolbar->firstday);
        $this->assertSame(
            [$toolbar->today, $toolbar->today],
            [$toolbar->datequick[0]['from'], $toolbar->datequick[0]['to']],
            'And the first quick option is that same day, from the same helper: one source, two readers.'
        );
        /* Two numbers side by side on one export: the "all" pill, the Online facet above and the
           count line at rest count the whole subtree, five courses, the hero's number; the grid
           draws the page, which on the grouped organisation holds this category's own four. An
           implementation that cut the population instead of the page, or a count line printing
           the page's total, would print four in both places. */
        $this->assertSame('5', $context->countnumber, 'The count line at rest is the subtree\'s number.');
        $this->assertSame('courses in Root & Co', $context->countline, 'Plain: the sink escapes it.');
        $this->assertSame('', $context->seeallurl, 'At rest there is no list to offer beside it.');
        $this->assertCount(4, $context->cards, 'While the grid draws the page: this category\'s own four.');
        $this->assertSame('', $context->pager, 'Everything fits one page: no pager.');
        /* This export is the grouped organisation, which draws no tree: the posters name the same
           children and open their courses. The tree is asserted on a flat export of this fixture,
           in test_the_tree_is_a_filter_of_the_flat_orders_and_the_grouped_one_has_none. */
        $this->assertSame([], $context->tree);
        $this->assertFalse($context->hastree);
        $this->assertFalse($context->isdirectory);

        // And the areas of the default organisation: the one child as a poster, then this category's own courses.
        $this->assertTrue($context->isgrouped);
        $this->assertTrue($context->hasareas);
        $this->assertSame(
            [['Brigada', 1]],
            array_map(static fn(array $poster): array => [$poster['name'], $poster['count']], $context->areas['posters'])
        );
        $this->assertSame('Areas in Root & Co', $context->areas['heading'], 'Plain: the sink escapes it.');
        $this->assertSame(['heading' => 'Courses in Root & Co', 'show' => true], $context->direct);
        foreach (['accordion', 'hasaccordion', 'herecount', 'herelabel', 'expandalllabel', 'accesslabel'] as $gone) {
            $this->assertObjectNotHasProperty($gone, $context, 'The accordion\'s ' . $gone . ' is gone with it.');
        }
    }

    /**
     * A grandchild sits one level deeper in the tree, and only its parent is a poster of the areas.
     *
     * The depth is what says which parent a deep entry belongs to, and the parent's own entry
     * is the control: a test asserting the grandchild's number alone would pass against a tree
     * that gave everything the same one. The posters are the other half of the same fixture: a
     * poster counts its child's whole subtree, so the grandchild is inside its parent's number
     * and is not a poster of its own - two posters on one path would count the same course twice.
     */
    public function test_a_grandchild_is_one_level_deeper_in_the_tree_and_is_no_panel_of_its_own(): void {
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $this->setAdminUser();
        $grandchild = $generator->create_category(['parent' => $this->ids['child'], 'name' => 'Salvamento']);
        $generator->create_course(['category' => $grandchild->id]);
        $this->become($viewer);

        /* The depths are read off a flat organisation, the only one that draws a tree; the
           posters below are read off the grouped export, the only one that has them. */
        $flat = $this->export(['sort' => criteria::SORT_NAME]);
        $depths = array_column($flat->tree, 'depth', 'name');
        $this->assertSame(1, $depths['Brigada'], 'A direct child is one level in.');
        $this->assertSame(2, $depths['Salvamento']);

        $context = $this->export();

        $this->assertSame(
            [['Brigada', 2]],
            array_map(static fn(array $poster): array => [$poster['name'], $poster['count']], $context->areas['posters']),
            'One poster, the direct child, counting the grandchild\'s course with its own.'
        );
    }

    /**
     * The tree's depth stops at the last level the stylesheet insets, whatever the setting offers.
     *
     * The setting's "every level" lets the walk reach a sixth level, and the stylesheet styles
     * `data-depth` 1 to 4 only: an entry exported deeper would be drawn with no inset at all,
     * flush with the root. The assertions that keep this from passing vacuously are the sixth
     * level being present (the setting really offered it), the third keeping its own number (a
     * clamp that flattened every level would fail) and the cap being the constant the stylesheet
     * is written against.
     */
    public function test_the_trees_depth_is_capped_at_the_last_inset_the_stylesheet_draws(): void {
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $this->setAdminUser();
        set_config(population::SETTING_TREEDEPTH, 0, 'theme_dimensions');
        $parent = $this->ids['child'];
        foreach (range(2, 6) as $level) {
            $parent = (int) $generator->create_category(['parent' => $parent, 'name' => 'Level ' . $level])->id;
        }
        $generator->create_course(['category' => $parent]);
        $this->become($viewer);

        $flat = $this->export(['sort' => criteria::SORT_NAME]);
        $depths = array_column($flat->tree, 'depth', 'name');

        $this->assertArrayHasKey('Level 6', $depths, 'The setting offered every level, so the sixth is in the tree.');
        $this->assertSame(3, $depths['Level 3'], 'A level the stylesheet draws keeps its own number.');
        $this->assertSame(4, $depths['Level 4']);
        $this->assertSame(category_listing::TREE_MAX_INDENT_DEPTH, $depths['Level 5']);
        $this->assertSame(category_listing::TREE_MAX_INDENT_DEPTH, $depths['Level 6']);
        $this->assertLessThanOrEqual(4, max($depths));

        // The constant is the stylesheet's own last rule, not a number that merely agrees today.
        $scss = file_get_contents(__DIR__ . '/../../scss/category.scss');
        preg_match_all('/\.dim-tree__item\[data-depth="(\d+)"\]/', $scss, $matches);
        $this->assertSame(category_listing::TREE_MAX_INDENT_DEPTH, max(array_map('intval', $matches[1])));
    }

    /**
     * The tree is a filter of the flat organisations, and the grouped one draws none at all.
     *
     * Each entry is an address of this page carrying the tree's own parameter and every other
     * filter the request already holds, so choosing a category narrows the listing and keeps
     * the reader where they are; the first entry is the same address without that parameter,
     * which is how the filter is cleared, and exactly one entry is marked. Under "by category"
     * there is no tree in the export at all, because the posters name the same children and
     * open their courses.
     *
     * The disclosure's label is asserted here rather than in the markup test: a template
     * renders from its own example context and would stay green over an exporter that had
     * stopped sending it.
     */
    public function test_the_tree_is_a_filter_of_the_flat_orders_and_the_grouped_one_has_none(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $root = $this->ids['root'];
        $child = $this->ids['child'];

        $flat = $this->export(['sort' => criteria::SORT_NAME]);
        $this->assertTrue($flat->hastree, 'A flat organisation draws the tree.');
        $this->assertCount(2, $flat->tree, 'This category and its one child.');
        $this->assertSame($root, (int) $flat->tree[0]['id']);
        $this->assertSame($child, (int) $flat->tree[1]['id']);
        $this->assertStringContainsString(
            'categoryid=' . $root,
            $flat->tree[1]['url'],
            'A child\'s entry is an address of THIS page, not of the child\'s own.'
        );
        $this->assertStringContainsString(
            criteria::PARAM_CAT . '=' . $child,
            $flat->tree[1]['url'],
            'And it carries the tree\'s own parameter, so it narrows the listing.'
        );
        $this->assertStringNotContainsString(
            criteria::PARAM_CAT . '=',
            $flat->tree[0]['url'],
            'The first entry carries none, which is what clears the filter.'
        );
        $this->assertTrue($flat->tree[0]['current'], 'With nothing chosen, the page\'s own entry is the one marked.');
        $this->assertFalse($flat->tree[1]['current']);

        // Every other filter travels with the entry: choosing a category keeps the search term.
        self::forget();
        $searched = $this->export(['sort' => criteria::SORT_NAME, 'search' => 'Curso']);
        $this->assertStringContainsString(
            criteria::PARAM_SEARCH . '=Curso',
            $searched->tree[1]['url'],
            'Or choosing a category would silently drop the term the reader typed.'
        );

        // With a category chosen, the marking moves to it and the first entry is the way back.
        self::forget();
        $narrowed = $this->export(['sort' => criteria::SORT_NAME, 'cat' => $child]);
        $this->assertFalse($narrowed->tree[0]['current']);
        $this->assertTrue($narrowed->tree[1]['current'], 'The chosen entry says so, and it is the only one.');
        $this->assertSame(
            1,
            count(array_filter($narrowed->tree, static fn(array $entry): bool => !empty($entry['current']))),
            'Exactly one entry is marked, whatever is chosen.'
        );
        $this->assertStringNotContainsString(criteria::PARAM_CAT . '=', $narrowed->tree[0]['url']);

        // The second half: the grouped organisation has no tree, in either spelling of it.
        foreach (['' => 'the default', criteria::SORT_CATEGORY => 'the named chip'] as $sort => $what) {
            self::forget();
            $grouped = $this->export(['sort' => $sort]);
            $this->assertSame([], $grouped->tree, 'The grouped organisation draws no tree (' . $what . ').');
            $this->assertFalse($grouped->hastree, 'And the shell lays out no column for one (' . $what . ').');
            $this->assertNotEmpty($grouped->areas['posters'], 'While the posters that replace it are there.');
        }
        $this->assertNull($flat->areas, 'And a flat organisation draws no poster.');

        // The label of the disclosure the client works with, read where only the export shows it.
        $this->assertNotSame('', (string) $flat->treeboxlabel, 'The disclosure the phone collapses names itself.');
    }

    /**
     * A filtered request keeps its parameters in the pager and reports the too-short search.
     */
    public function test_a_filtered_request_keeps_its_parameters_and_flags_a_short_search(): void {
        $viewer = $this->seed();
        $this->become($viewer);

        /* The flat organisation for the two searches: "Curso Aberto" sits in the child, and the
           pager is only drawn over a listing that does not fit one page. */
        $context = $this->export(['pagesize' => 2, 'search' => 'Curso', 'sort' => criteria::SORT_NAME]);
        $this->assertSame('3', $context->countnumber, 'A flat listing counts its whole total, not the page.');
        $this->assertSame('courses for “Curso” in Root & Co', $context->countline);
        $this->assertSame('', $context->seeallurl, 'A flat listing already is the list.');
        // The Filters panel's "Show" is the count's own number in its sentence, and the sentence the client fills.
        $this->assertSame(get_string('category_showcount', 'theme_dimensions', '3'), $context->showlabel);
        $this->assertSame('Show {n}', $context->toolbar->showtemplate, 'The server\'s words, the number left to the client.');
        $this->assertStringContainsString('page=1', $context->pager);
        $this->assertStringContainsString('q=Curso', $context->pager);
        $this->assertStringContainsString('perpage=2', $context->pager);
        $this->assertTrue($context->toolbar->hasfilters);
        $this->assertSame('Curso', $context->toolbar->query);
        $this->assertFalse($context->toolbar->hasfiltercount, 'The term is in its own box, not behind the Filters badge.');
        /* The number of filters the panel holds is printed twice on the summary of the filter
           disclosure: once for the eye and once, as this label, for a screen reader, the only one
           that says what the number means. The label must hold the number and no unsubstituted
           placeholder: in a single-quoted language string, a backslash before the dollar of {$a}
           survives and get_string() then has no placeholder to substitute. */
        $fieldid = array_key_first(facets::fields());
        $context = $this->export([
            'search' => 'Curso',
            'sort' => criteria::SORT_NAME,
            'datefrom' => '2020-01-01',
            'fields' => [$fieldid => landingfields::MODALITY_ONLINE],
        ]);
        $this->assertTrue($context->toolbar->hasfiltercount, 'The field is in the panel; the period is the date button\'s.');
        $this->assertSame(1, $context->toolbar->filtercount);
        $this->assertStringContainsString(
            (string) $context->toolbar->filtercount,
            $context->toolbar->filtercountlabel,
            'The label names the number it is the name of.'
        );
        $this->assertStringNotContainsString(
            '{',
            $context->toolbar->filtercountlabel,
            'An unsubstituted placeholder is read out of a screen reader exactly as it is written.'
        );

        $context = $this->export(['search' => 'a', 'sort' => criteria::SORT_NAME]);
        $this->assertTrue($context->toolbar->searchtooshort);
        $this->assertSame('a', $context->toolbar->query);
        $this->assertSame(
            ['5', 'courses in Root & Co'],
            [$context->countnumber, $context->countline],
            'A one-letter query is no search.'
        );

        $context = $this->export(['state' => classifier::STATE_ENROLLED], category_toolbar::VIEW_LIST);
        $this->assertSame([$this->ids['enrolled']], array_keys($this->cards($context)));
        $this->assertSame('dim-vitrine--list', $context->viewclass);
        $this->assertTrue($context->toolbar->views[0]['current'], 'List, the first of the pair.');
    }

    /**
     * The empty state blames the filters only when there are some; a page past the end is the last page.
     */
    public function test_the_empty_state_names_its_cause_and_a_far_page_is_the_last_page(): void {
        $viewer = $this->seed();
        $this->become($viewer);

        $context = $this->export(['search' => 'zzzz']);
        $this->assertFalse($context->hascards);
        $this->assertSame(get_string('category_empty', 'theme_dimensions'), $context->emptylabel);

        /* The empty state draws a second "clear filters", because the toolbar's own sits on a row
           the reader has already passed; it is the same control and carries the same address -
           the organisation kept, everything that narrows dropped. It renders only when the page
           is empty, which is where no other assertion about that address can reach it. */
        $this->assertStringNotContainsString('sort=', $context->clearurl, 'Control: the default names no sort.');
        $sorted = $this->export(['search' => 'zzzz', 'sort' => criteria::SORT_NAME]);
        $this->assertFalse($sorted->hascards, 'Precondition: the empty state is what is drawn.');
        $this->assertTrue($sorted->hasfilters, 'Precondition: and it offers the way out.');
        $this->assertStringContainsString('sort=' . criteria::SORT_NAME, $sorted->clearurl);
        $this->assertStringNotContainsString('q=zzzz', $sorted->clearurl);
        $this->assertSame($sorted->toolbar->clearurl, $sorted->clearurl, 'One address, drawn twice.');

        $context = $this->export(['page' => 9, 'pagesize' => 2]);
        $this->assertTrue($context->hascards);
        $this->assertSame(['5', 'courses in Root & Co'], [$context->countnumber, $context->countline]);

        /* A filter that keeps the child's course and none of this category's own leaves the
           section of the direct courses with nothing to draw, so it is hidden: the poster's count
           shows where the result is. The two controls are in the same run: the poster counts one,
           and the same request organised flat finds that course. */
        $here = $this->export(['search' => 'Aberto']);
        $this->assertFalse($here->hascards, 'Precondition: nothing of this category itself matched.');
        $this->assertSame([1], array_column($here->areas['posters'], 'count'), 'And the child still has one.');
        $this->assertFalse($here->direct['show'], 'The direct section hides under a criterion that matched nothing here.');
        $this->assertFalse($here->areas['hasdirectlink'], 'And so does the link past the posters to it.');
        $this->assertTrue(
            $this->export(['search' => 'Livre'])->direct['show'],
            'Control: under a criterion that matches a course here, the section shows.'
        );
        $this->assertCount(
            1,
            $this->cards($this->export(['search' => 'Aberto', 'sort' => criteria::SORT_NAME])),
            'Control: the course the headers count is really there, in the flat listing of the same request.'
        );
        /* And the rule follows the numbers, not the shape: a filter that matched nothing anywhere
           leaves every poster at zero, and then there really is no course. */
        $this->assertSame(
            get_string('category_empty', 'theme_dimensions'),
            $this->export(['search' => 'zzzz'])->emptylabel,
            'Control: with every header at zero the ordinary message is the honest one.'
        );

        global $PAGE;
        $empty = self::getDataGenerator()->create_category();
        $criteria = new criteria((int) $empty->id);
        $listing = new category_listing(
            core_course_category::get((int) $empty->id, MUST_EXIST, true),
            $criteria,
            population::resolve($criteria),
            category_toolbar::VIEW_CARDS
        );
        $context = $listing->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($context->hascards);
        $this->assertSame(get_string('category_emptycategory', 'theme_dimensions'), $context->emptylabel);
    }

    /**
     * The pager hands the client a real address for the page after this one, and nothing on the last.
     *
     * The client appends at most a fixed number of pages into one document and then stops,
     * offering this link instead; a link it had to compose itself would be a second, invisible
     * copy of the address rules the pager already owns.
     */
    public function test_the_pager_names_the_next_page_for_the_client(): void {
        $viewer = $this->seed();
        $this->become($viewer);

        $context = $this->export(['pagesize' => 2]);
        $this->assertTrue($context->hasmore);
        $this->assertNotSame('', $context->nexturl);
        $this->assertStringContainsString('page=1', $context->nexturl);
        $this->assertStringContainsString('perpage=2', $context->nexturl, 'The page size travels with it.');

        /* A filter is part of the address too, or the next page would drop it. Flat, because
           the three matches are spread over the subtree and two of them sit in this category:
           grouped, the listing would fit one page and there would be no next address at all. */
        $context = $this->export(['pagesize' => 2, 'search' => 'curso', 'sort' => criteria::SORT_NAME]);
        $this->assertStringContainsString('q=curso', $context->nexturl);

        // The last page names no next page at all, and the button it feeds stays hidden.
        $context = $this->export(['pagesize' => 50]);
        $this->assertFalse($context->hasmore);
        $this->assertSame('', $context->nexturl);
    }

    /**
     * The payload the client reads says whether this viewer's view choice can be stored at all.
     *
     * A guest gets the view for the page they are on and no request is issued for it: core
     * refuses to write a preference for the guest account, so asking would produce a console
     * error and nothing else.
     */
    public function test_the_client_payload_says_whether_the_view_can_be_stored(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $payload = json_decode($this->export([])->vitrine, true);
        $this->assertTrue($payload['canpersist']);

        $this->setGuestUser();
        $payload = json_decode($this->export([])->vitrine, true);
        $this->assertFalse($payload['canpersist']);
    }

    /**
     * The view is remembered for a person and never for the guest account; unknown values are ignored.
     */
    public function test_the_view_is_remembered_per_person_only(): void {
        $this->resetAfterTest();
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        $this->assertSame(category_toolbar::VIEW_CARDS, category_listing::view(''));
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view(category_toolbar::VIEW_LIST));
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view(''), 'Remembered.');
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view('bogus'), 'Ignored, the memory stands.');
        $this->assertSame(category_toolbar::VIEW_LIST, get_user_preferences(category_listing::PREFERENCE_VIEW, null, $user));

        $this->setGuestUser();
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view(category_toolbar::VIEW_LIST), 'For this page.');
        $this->assertSame(category_toolbar::VIEW_CARDS, category_listing::view(''), 'Not remembered.');
    }

    /**
     * A public root with a public child, one public course in each, and a listed course beside them.
     *
     * The listed course is what makes the anonymous assertions non-vacuous: the shell has to
     * leave something out for "the public ones alone" to mean anything.
     *
     * @return void
     */
    private function seed_public(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        // The public page is the one surface that exists while the site forces login, and the
        // router is declared off so that the addresses below are the script's, spelled exactly.
        $CFG->forcelogin = 1;
        $CFG->routerconfigured = false;

        $generator = self::getDataGenerator();
        $handler = course_handler::create();
        $adminid = (int) get_admin()->id;
        $root = $generator->create_category(['name' => 'Vitrine & Cia']);
        $child = $generator->create_category(['parent' => $root->id, 'name' => 'Brigada']);

        $make = function (int $categoryid, string $fullname, bool $public) use ($generator, $handler, $adminid): int {
            $course = $generator->create_course(['category' => $categoryid, 'fullname' => $fullname]);
            $handler->instance_form_save((object) [
                'id' => $course->id,
                'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_COURSE,
            ]);
            if ($public) {
                \local_unlistedcourses\discoverability::set_state(
                    (int) $course->id,
                    \local_unlistedcourses\discoverability::STATE_PUBLIC,
                    $adminid
                );
            }
            return (int) $course->id;
        };

        $this->ids['root'] = (int) $root->id;
        $this->ids['child'] = (int) $child->id;
        $this->ids['public'] = $make((int) $root->id, 'Curso Publico', true);
        $this->ids['childpublic'] = $make((int) $child->id, 'Curso do Filho', true);
        $this->ids['listed'] = $make((int) $root->id, 'Curso Interno', false);
        // A public course that chose no landing page model, and a teacher on the public one: what a
        // visitor's card must not link to and must not name.
        $nomodel = $generator->create_course(['category' => $root->id, 'fullname' => 'Curso Sem Modelo']);
        \local_unlistedcourses\discoverability::set_state(
            (int) $nomodel->id,
            \local_unlistedcourses\discoverability::STATE_PUBLIC,
            $adminid
        );
        $this->ids['nomodel'] = (int) $nomodel->id;
        $CFG->coursecontact = $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $teacher = $generator->create_user(['firstname' => 'Tereza', 'lastname' => 'Contato']);
        $generator->enrol_user($teacher->id, $this->ids['public'], 'editingteacher');
        foreach ([(int) $root->id, (int) $child->id] as $categoryid) {
            \local_unlistedcourses\category_discoverability::set_state(
                $categoryid,
                \local_unlistedcourses\category_discoverability::STATE_PUBLIC,
                $adminid
            );
        }
        self::forget();
    }

    /**
     * Every address the anonymous listing draws is the public page's, and nothing is a favourite.
     *
     * The logged-in export of the same category at the end is the control: it keeps pointing at
     * core's /course/index.php, so a links::showcase() that had simply stopped choosing - or a
     * toolbar that had lost its hidden field - could not pass both halves.
     *
     * @return void
     */
    public function test_the_anonymous_listing_points_at_the_public_page_and_shows_no_star(): void {
        global $DB;

        $this->seed_public();
        $viewer = self::getDataGenerator()->create_user();

        $this->become(null);
        $context = $this->export(['anonymous' => true, 'pagesize' => 1]);

        /* The three public courses are the whole population - the listed one is not in it - and
           the count line at rest counts all three, the subtree's number; two of them sit in this
           category and the third is inside the child's own poster, which counts it. */
        $this->assertSame(['3', 'courses in Vitrine & Cia'], [$context->countnumber, $context->countline]);
        $this->assertSame(2, json_decode($context->vitrine)->total, 'While the page lists the two sitting here, by one.');
        $this->assertSame([1], array_column($context->areas['posters'], 'count'), 'The child\'s public course.');

        $script = '/theme/dimensions/category.php?id=';
        /* The tree is a flat organisation's control, so a visitor's is read off a flat export of
           the same request; the grouped one above draws the posters instead. */
        $this->assertSame([], $context->tree, 'And the grouped shell of a visitor draws none either.');
        self::forget();
        $flat = $this->export(['anonymous' => true, 'sort' => criteria::SORT_NAME]);
        $this->assertSame(
            [['Vitrine & Cia', 0, true], ['Brigada', 1, false]],
            array_map(
                static fn(array $entry): array => [$entry['name'], $entry['depth'], $entry['current']],
                $flat->tree
            )
        );
        /* Every entry of a visitor's tree is an address of the anonymous shell: a filter that
           left the shell would land the reader on the login form. */
        foreach ($flat->tree as $entry) {
            $this->assertStringContainsString($script, $entry['url']);
        }
        $this->assertStringContainsString(
            criteria::PARAM_CAT . '=' . $this->ids['child'],
            $flat->tree[1]['url'],
            'And it narrows this page rather than opening the child\'s own.'
        );
        $this->assertSame(
            [links::category_page($this->ids['child'])->out(false)],
            array_column($context->areas['posters'], 'url'),
            'And so is a poster\'s page.'
        );
        $this->assertStringContainsString(
            $script . $this->ids['root'],
            $context->areas['posters'][0]['controlurl'],
            'And the poster opens its panel on the public page, never on the logged-in one.'
        );
        $this->assertStringContainsString('open=' . $this->ids['child'], $context->areas['posters'][0]['controlurl']);
        $this->assertSame(links::category_page($this->ids['root'])->out(false), $context->clearurl);
        $this->assertStringContainsString($script . $this->ids['root'], $context->pager);
        $this->assertStringNotContainsString('/course/index.php', $context->pager);

        // The form posts to the script with the id as a hidden field: a GET drops the action's query.
        $toolbar = $context->toolbar;
        $this->assertStringEndsWith('/theme/dimensions/category.php', $toolbar->action);
        $this->assertSame([['name' => 'id', 'value' => $this->ids['root']]], $toolbar->hidden);
        $this->assertSame(links::category_page($this->ids['root'])->out(false), $toolbar->clearurl);

        $this->assertFalse($context->cards[0]->showstar, 'A visitor has no favourites.');

        // The whole page, unpaged: the modelled public course links to its landing page and offers it as
        // the call to action, the one without a model links nowhere at all (the enrolment page would
        // answer a visitor with the login form), and no card names a course contact to the internet:
        // the contacts are the course dialogue's, which a visitor never opens.
        $cards = $this->cards($this->export(['anonymous' => true]));
        $card = $cards[$this->ids['public']];
        $this->assertFalse($card->showstar);
        $this->assertSame(get_string('category_state_public', 'theme_dimensions'), $card->badge['label']);
        $this->assertSame(get_string('category_cta_landing', 'theme_dimensions'), $card->cta['label']);
        $this->assertSame(links::page((int) $card->id)->out(false), $card->cta['url']);
        $this->assertTrue($card->cta['primary']);
        $this->assertFalse($card->hasdetails, 'The visitor\'s one button: no "More information", whose dialogue is never armed.');
        $this->assertTrue($cards[$this->ids['public']]->hasurl);
        $this->assertSame(links::page($this->ids['public'])->out(false), $cards[$this->ids['public']]->url);
        $this->assertFalse($cards[$this->ids['nomodel']]->hasurl, 'Nowhere a visitor may go.');
        $this->assertNull($cards[$this->ids['nomodel']]->url);
        $this->assertFalse($cards[$this->ids['nomodel']]->hascta);
        $this->assertObjectNotHasProperty('facts', $cards[$this->ids['public']]);
        $this->assertStringNotContainsString('Tereza Contato', json_encode($cards[$this->ids['public']]));

        // Control: the same listing for somebody logged in still belongs to core's category page.
        $this->become($viewer);
        $context = $this->export();
        $this->assertStringEndsWith('/course/index.php', $context->toolbar->action);
        $this->assertSame([['name' => 'categoryid', 'value' => $this->ids['root']]], $context->toolbar->hidden);
        $this->assertStringContainsString('/course/index.php?categoryid=' . $this->ids['root'], $context->clearurl);
        self::forget();
        $this->assertStringContainsString(
            '/course/index.php?categoryid=' . $this->ids['root'],
            $this->export(['sort' => criteria::SORT_NAME])->tree[1]['url'],
            'Control: a person\'s tree belongs to core\'s page.'
        );
        $this->assertTrue($this->cards($context)[$this->ids['public']]->showstar, 'Control: a person gets the star.');
        $cards = $this->cards($context);
        // Control: the contact is real, and named to a person - in the dialogue, not on the card.
        $public = $DB->get_record('course', ['id' => $this->ids['public']], dialogue::COURSE_FIELDS);
        $publicfacts = dialogue::content($public)['facts'];
        $contactlabel = get_string('category_contact', 'theme_dimensions');
        $this->assertContains($contactlabel, array_column($publicfacts, 'label'), 'Control: named to a person.');
        $this->assertStringContainsString('Tereza Contato', implode(' ', array_column($publicfacts, 'value')));
        $this->assertTrue($cards[$this->ids['nomodel']]->hasurl, 'Control: a person gets the enrolment page.');
        $this->assertStringContainsString('/enrol/index.php?id=' . $this->ids['nomodel'], $cards[$this->ids['nomodel']]->url);
    }

    /**
     * A visitor's card carries its cover through the theme's route; a person's keeps core's.
     *
     * Under forcelogin, core's course/overviewfiles route answers a visitor with a redirect to the
     * login page (file_pluginfile() calls require_login() for that area), so a visitor's card
     * mints the theme's own cover route, as the landing page does for its hero image. PHPUnit fetches
     * no image, so the address is what is asserted.
     *
     * The logged-in export at the end is the control, and it makes the route a choice rather than
     * a replacement: core's route is the right one behind the login, and it is what core's own
     * image cache holds.
     *
     * @return void
     */
    public function test_the_visitors_card_carries_its_cover_through_the_theme_route(): void {
        global $CFG;

        $this->seed_public();
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $viewer = self::getDataGenerator()->create_user();
        $courseid = $this->ids['public'];
        $contextid = \core\context\course::instance($courseid)->id;
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'course',
            'filearea' => 'overviewfiles',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.png',
        ], base64_decode(self::PNG));

        $this->become(null);
        $card = $this->cards($this->export(['anonymous' => true]))[$courseid];
        $this->assertTrue($card->hasimage, 'Precondition: the fixture course has a cover at all.');
        $this->assertStringEndsWith(
            "/pluginfile.php/{$contextid}/theme_dimensions/cover/{$courseid}/{$file->get_contenthash()}/capa.png",
            $card->imageurl
        );
        $this->assertStringNotContainsString('/course/overviewfiles/', $card->imageurl, 'Not the route that asks.');

        // Control: the same course, exported for somebody logged in, keeps core's own route.
        $this->become($viewer);
        $card = $this->cards($this->export())[$courseid];
        $this->assertStringEndsWith("/pluginfile.php/{$contextid}/course/overviewfiles/capa.png", $card->imageurl);
        $this->assertStringNotContainsString('/theme_dimensions/cover/', $card->imageurl);
    }

    /**
     * A viewer who may already enter the course is sent to the course, whatever badge the card wears.
     *
     * The badge is about the course and the link is about the viewer. A manager reading the
     * listing holds `moodle/course:view` at the category, so core's own `can_access_course()`
     * lets them walk into every course under it; the enrolment surface, for a course with a
     * landing page model a page asking them to apply, is the wrong place to send them.
     *
     * Three cards carry it: the course open for enrolment (whose enrolment surface is its
     * landing page), the course nobody may join (otherwise muted, with no link and no call to action)
     * and the event already held (otherwise muted, with nothing to press). Every badge is asserted
     * beside the link, because the rule changes the link and never the badge: an event that
     * finished is still an event that finished.
     *
     * The control is the same export for the plain student of the fixture, in the same test:
     * without it, every assertion here would pass over a card builder that sent everybody to
     * /course/view.php.
     */
    public function test_a_viewer_who_may_enter_the_course_is_sent_to_it_and_not_to_the_enrolment_surface(): void {
        $viewer = $this->seed();
        $manager = $this->user_with('moodle/course:view', \core\context\coursecat::instance($this->ids['root']));

        $this->become($manager);
        // Flat: one of the courses asked about sits in the child, which the grouped page does not list.
        $cards = $this->cards($this->export(['sort' => criteria::SORT_NAME]));

        $open = $cards[$this->ids['open']];
        $this->assertStringContainsString('/course/view.php?id=' . $this->ids['open'], $open->url);
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $open->cta['label']);
        $this->assertStringContainsString('/course/view.php?id=' . $this->ids['open'], $open->cta['url']);
        $this->assertTrue($open->cta['primary']);
        $this->assertSame('dim-badge dim-badge--open', $open->badge['classes'], 'The badge still reads the course.');

        $none = $cards[$this->ids['none']];
        $this->assertStringContainsString('/course/view.php?id=' . $this->ids['none'], $none->url);
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $none->cta['label']);
        $this->assertFalse($none->ismuted, 'A card somebody may walk into is not the muted one.');
        $this->assertSame('dim-badge dim-badge--none', $none->badge['classes']);

        $held = $cards[$this->ids['held']];
        $this->assertStringContainsString('/course/view.php?id=' . $this->ids['held'], $held->url);
        $this->assertSame(get_string('category_cta_access', 'theme_dimensions'), $held->cta['label']);
        $this->assertFalse($held->ismuted);
        $this->assertSame(
            get_string('category_state_held', 'theme_dimensions'),
            $held->badge['label'],
            'An event that finished still says so: the fix is the link, never the badge.'
        );

        // The control: the student of the fixture, who may enter none of the three. Flat, as above.
        $this->become($viewer);
        $plain = $this->cards($this->export(['sort' => criteria::SORT_NAME]));
        $this->assertSame(
            links::page($this->ids['open'])->out(false),
            $plain[$this->ids['open']]->cta['url'],
            'A person who has to enrol is still sent to the enrolment surface.'
        );
        $this->assertSame(get_string('category_cta_enrol', 'theme_dimensions'), $plain[$this->ids['open']]->cta['label']);
        $this->assertNull($plain[$this->ids['none']]->cta);
        $this->assertTrue($plain[$this->ids['none']]->ismuted);
        $this->assertNull($plain[$this->ids['held']]->cta);
        $this->assertTrue($plain[$this->ids['held']]->ismuted);
    }

    /**
     * Holding moodle/course:view does not open a hidden course, because in core it does not either.
     *
     * `can_access_course()` refuses a course hidden from students to anybody without
     * `moodle/course:viewhiddencourses` before it ever asks `is_viewing()`, so a card that
     * rescued a hidden course on the capability alone would hand out a link the course itself
     * then refuses. The listing's own gate admits a hidden course to viewhiddencourses and to
     * nothing else, so this pair is built by hand: the same course, the same viewer, the same
     * landing page values, and one flipped column between them.
     *
     * Changes that must make it fail: category_card::may_enter() admitting a hidden course
     * without moodle/course:viewhiddencourses.
     */
    public function test_a_hidden_course_is_not_opened_by_the_view_capability(): void {
        global $PAGE;
        $this->seed();
        $manager = $this->user_with('moodle/course:view', \core\context\coursecat::instance($this->ids['root']));
        $this->become($manager);

        $PAGE->set_url('/course/index.php', ['categoryid' => $this->ids['root']]);
        $courseid = $this->ids['open'];
        // Flat: the course under test sits in the child, which the grouped page partitions away.
        $result = population::resolve(new criteria($this->ids['root'], ['sort' => criteria::SORT_NAME]));
        $this->assertArrayHasKey($courseid, $result->rows, 'The listing admits the visible course to this viewer.');
        $row = $result->rows[$courseid];
        $course = cards::records([$courseid])[$courseid];
        $values = cards::values([$courseid => $row])[$courseid];
        $renderer = $PAGE->get_renderer('core');

        $build = function (\stdClass $populationrow) use ($course, $values, $renderer): \stdClass {
            $card = new category_card($course, $populationrow, $values, null, null, false);
            return $card->export_for_template($renderer);
        };

        $visible = $build($row);
        $this->assertStringContainsString(
            '/course/view.php?id=' . $courseid,
            $visible->url,
            'The control: the same viewer, the same course, visible.'
        );

        $hiddenrow = clone $row;
        $hiddenrow->visible = 0;
        $hidden = $build($hiddenrow);
        $this->assertSame(
            links::page($courseid)->out(false),
            $hidden->url,
            'Hidden, the card falls back to the enrolment surface: the capability core asks for first is not held.'
        );
        $this->assertSame(get_string('category_cta_enrol', 'theme_dimensions'), $hidden->cta['label']);
        $this->assertTrue($hidden->ishidden, 'And it is the hidden course that was asked about.');
    }

    /**
     * A course card wears the course's crests; a directory card wears the category's; never crossed.
     *
     * The two sets are stored in different places by different people: a course's are the
     * landing page's four picture fields, a category's are the three areas of its own appearance
     * editor. The fixture gives the category one crest and one of its courses two others, and
     * each surface is asserted to carry its own and not the other's - an assertion naming only
     * its own would pass against a card reading whichever set it liked.
     */
    public function test_a_course_card_wears_the_courses_crests_and_a_category_card_the_categorys(): void {
        if (!landingfields::picture_type_available()) {
            $this->markTestSkipped('customfield_picture is not installed or not enabled.');
        }
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        /** @var \core_customfield_generator $customfields */
        $customfields = $generator->get_plugin_generator('core_customfield');

        // The category's own crest, in its first slot.
        get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($this->ids['root'])->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_BADGES[0],
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'categoria.png',
        ], base64_decode(self::PNG));
        branding::forget_all();

        /* The course's own, in the landing page's fourth and second badge fields: stored in that
           order so the assertion below reads the fields' order and not the upload's, with the
           third slot empty so the gap has to close. */
        $coursecontext = \core\context\course::instance($this->ids['enrolled']);
        $files = [landingfields::SHORTNAME_CREST4 => 'quarto.png', landingfields::SHORTNAME_CREST2 => 'segundo.png'];
        foreach ($files as $shortname => $filename) {
            $data = $customfields->add_instance_data(landingfields::get_field($shortname), $this->ids['enrolled'], 1);
            get_file_storage()->create_file_from_string([
                'contextid' => $coursecontext->id,
                'component' => 'customfield_picture',
                'filearea' => 'file',
                'itemid' => (int) $data->get('id'),
                'filepath' => '/',
                'filename' => $filename,
            ], base64_decode(self::PNG));
        }

        $this->become($viewer);
        $cards = $this->cards($this->export());
        $card = $cards[$this->ids['enrolled']];

        $this->assertTrue($card->hasbadges);
        $this->assertSame(['segundo', 'quarto'], array_column($card->badges, 'alt'), 'The grid\'s order, gap closed.');
        foreach ($card->badges as $badge) {
            $this->assertStringContainsString(
                '/theme_dimensions/badge/' . $this->ids['enrolled'] . '/',
                $badge['url'],
                'Through the theme\'s own badge route, the only one that serves a field nobody may see.'
            );
        }
        $this->assertStringNotContainsString(
            'categoria.png',
            json_encode($card->badges),
            'The category\'s own crest is NOT on a course card, whatever the course carries.'
        );
        $this->assertFalse(
            $cards[$this->ids['none']]->hasbadges,
            'And a course with no badge of its own shows none: it inherits nothing from its category.'
        );

        // The other half of the rule, on the directory card of the very same category.
        $this->setAdminUser();
        $entries = array_column(population::resolve(new criteria(0))->directory, null, 'id');
        $entry = $entries[$this->ids['root']];
        $this->assertTrue($entry['hasbadges']);
        $this->assertSame(['categoria'], array_column($entry['badges'], 'alt'));
        $this->assertStringContainsString(
            '/theme_dimensions/categorybadge1/' . $this->ids['root'] . '/',
            $entry['badges'][0]['url'],
            'The category\'s card carries the CATEGORY\'s route, never a course\'s.'
        );
        $this->assertStringNotContainsString('segundo', json_encode($entry['badges']), 'And none of its courses\' crests.');
    }

    /**
     * The root of the course index exports a directory of categories and nothing of a listing.
     *
     * The template it names is the directory's, the toolbar and the grid of courses are absent
     * from the export, and each entry carries what a poster draws: the name, the address of that
     * category's own showcase and the band's colour as custom properties - and no number. The
     * control is the same fixture exported at the category below, which still has a toolbar and
     * cards, so a directory export that had simply lost its keys could not pass both halves.
     */
    public function test_the_root_exports_a_directory_of_categories_and_no_toolbar(): void {
        global $PAGE;
        $viewer = $this->seed();
        $this->become($viewer);

        $PAGE->set_url('/course/index.php');
        $criteria = new criteria(0);
        $result = population::resolve($criteria);
        $listing = new category_listing(core_course_category::top(), $criteria, $result, category_toolbar::VIEW_CARDS);
        $renderer = $PAGE->get_renderer('core');

        $this->assertSame('theme_dimensions/category_directory', $listing->get_template_name($renderer));
        $context = $listing->export_for_template($renderer);

        $this->assertTrue($context->isdirectory);
        $this->assertTrue($context->hasdirectory);
        $this->assertSame(0, $context->categoryid);
        $this->assertObjectNotHasProperty('toolbar', $context, 'The root has no toolbar.');
        $this->assertObjectNotHasProperty('cards', $context, 'The root lists no course.');
        $this->assertObjectNotHasProperty('pager', $context);

        $entries = array_column($result->directory, null, 'id');
        $this->assertArrayHasKey($this->ids['root'], $entries, 'The fixture\'s own root is a top-level category and a card.');
        $entry = $entries[$this->ids['root']];
        $this->assertSame('Root & Co', $entry['name'], 'The plain spelling, as every other name of the page.');
        $this->assertStringContainsString('/course/index.php?categoryid=' . $this->ids['root'], $entry['url']);
        $this->assertSame(
            [
                'id', 'name', 'url', 'description', 'hasdescription', 'style', 'imageurl', 'hasimage',
                'hasvideo', 'videourl', 'videotype', 'badges', 'hasbadges', 'badgeslabel', 'hasmark', 'markfolder',
            ],
            array_keys($entry),
            'A directory entry carries exactly these keys, and no count among them.'
        );
        $this->assertSame(
            '--dim-card-band: ' . branding::DEFAULT_BG . '; --dim-card-ink: ' . branding::DEFAULT_INK . '; --dim-mark-a: 0.20;',
            $entry['style'],
            'The default pair, and the folder watermark of a category with neither a still nor a crest: the dark '
                . 'default blue has room for the whole .20 under white text.'
        );
        $this->assertFalse($entry['hasimage']);
        $this->assertTrue($entry['markfolder']);

        // The page draws them as the areas' posters, every one of which opens a panel at the root.
        $posters = array_column($context->areas['posters'], null, 'id');
        $poster = $posters[$this->ids['root']];
        $this->assertTrue($context->areas['isroot']);
        $this->assertSame(get_string('category_areas_root', 'theme_dimensions'), $context->areas['heading']);
        $this->assertFalse($context->areas['hasdirectlink'], 'The root has no courses of its own to skip to.');
        $this->assertTrue($poster['expandable'], 'The root counts nothing, so every poster opens.');
        $this->assertStringEndsWith(
            '/course/index.php?open=' . $this->ids['root'] . '#dim-panel',
            $poster['controlurl'],
            'The root\'s address, with the panel, landing on it.'
        );
        $this->assertSame(
            get_string('category_panel_cta', 'theme_dimensions', 'Root & Co'),
            $poster['shell']['ctalabel'],
            'At the root the call to action counts nothing either.'
        );

        /* With twenty-four posters or fewer there is no reveal at all, and the export says so
           rather than leaving the template to work it out: the next test seeds the other side of
           the cut. */
        $this->assertFalse($context->areas['hasmore'], 'A small directory is one grid and no disclosure.');
        $this->assertSame([], $context->areas['postersmore']);

        // The control: the category below still exports the listing, its toolbar and its cards.
        $below = $this->export();
        $this->assertFalse($below->isdirectory);
        $this->assertNotEmpty($below->cards);
        $this->assertNotEmpty($below->toolbar);
    }

    /**
     * Past twenty-four posters the export cuts the directory in two, and an open poster past the cut opens the reveal.
     *
     * The export is where the cut becomes visible, so this is where the boundary is asserted
     * over real categories rather than over a helper's arguments: twenty-five top-level
     * categories, twenty-four open, one behind the reveal, and the label the singular key spells
     * because there is exactly one. The population's own test pins the helper; what this one
     * adds is that the context the template reads is built from it, that no poster is lost or
     * drawn twice between the two lists - and that a panel open on the poster behind the reveal
     * draws the reveal open, with the panel in the revealed grid, or it would be drawn where
     * nobody can see it. The same panel on a poster of the open grid is the control.
     *
     * @return void
     */
    public function test_the_root_export_cuts_the_directory_at_twenty_four(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $wanted = population::DIRECTORY_SLICE + 1;
        $existing = $DB->count_records('course_categories', ['parent' => 0]);
        for ($i = $existing; $i < $wanted; $i++) {
            $generator->create_category(['name' => sprintf('Branch %02d', $i)]);
        }
        $this->become($generator->create_user());

        $PAGE->set_url('/course/index.php');
        $export = function (array $options = []) use ($PAGE): array {
            $criteria = new criteria(0, $options + ['withposters' => true]);
            $result = population::resolve($criteria);
            $listing = new category_listing(core_course_category::top(), $criteria, $result, category_toolbar::VIEW_CARDS);
            return [$result, $listing->export_for_template($PAGE->get_renderer('core'))];
        };
        [$result, $context] = $export();
        $areas = $context->areas;

        $this->assertCount($wanted, $result->directory, 'Precondition: every category is still an entry.');
        $this->assertCount(population::DIRECTORY_SLICE, $areas['posters'], 'Twenty-four posters are open.');
        $this->assertCount(1, $areas['postersmore'], 'The twenty-fifth is behind the reveal.');
        $this->assertTrue($areas['hasmore']);
        $this->assertFalse($areas['moreopen'], 'Closed while nothing behind it is open.');
        $this->assertSame(
            get_string('category_directory_more_one', 'theme_dimensions'),
            $areas['morelabel'],
            'One hidden poster is named by the singular key, chosen by a literal branch.'
        );
        $this->assertTrue($context->hasdirectory, 'The empty state stays away: the directory is not empty.');

        $open = array_column($areas['posters'], 'id');
        $hidden = array_column($areas['postersmore'], 'id');
        $this->assertSame([], array_intersect($open, $hidden), 'No poster is in both lists.');
        $this->assertSame(
            array_column($result->directory, 'id'),
            array_merge($open, $hidden),
            'And the two lists are the directory, in its own order.'
        );

        // A panel open on the poster behind the reveal opens the reveal, and sits in its grid.
        [, $pastcut] = $export(['open' => (int) $hidden[0]]);
        $this->assertTrue($pastcut->areas['isopen'], 'Precondition: the panel is drawn.');
        $this->assertTrue($pastcut->areas['moreopen'], 'The reveal holding the open poster is drawn open.');
        $this->assertTrue($pastcut->areas['panelinmore']);
        $this->assertFalse($pastcut->areas['panelinmain']);
        $this->assertTrue($pastcut->areas['postersmore'][0]['isopen']);
        $this->assertSame(
            '--dim-row-3: 2; --dim-row-2: 2; --dim-row-1: 2; --dim-col-3: 0; --dim-col-2: 0;'
                . ' --dim-area-bg: ' . branding::DEFAULT_BG . ';',
            $pastcut->areas['panelstyle'],
            'Its row counts from the revealed grid, where it is the first poster.'
        );
        [, $incut] = $export(['open' => (int) $open[4]]);
        $this->assertTrue($incut->areas['isopen'], 'The control: a panel on a poster of the open grid.');
        $this->assertFalse($incut->areas['moreopen'], 'Leaves the reveal closed.');
        $this->assertTrue($incut->areas['panelinmain']);

        /* The other arm of the same literal branch, which the singular case above cannot reach:
           two hidden posters are named by the plural key, and the count reaches it as its
           placeholder. */
        self::getDataGenerator()->create_category(['name' => 'Branch ' . $wanted]);
        self::forget();
        [, $plural] = $export();
        $this->assertCount(2, $plural->areas['postersmore'], 'Precondition: two posters are now behind the reveal.');
        $this->assertSame(
            get_string('category_directory_more', 'theme_dimensions', 2),
            $plural->areas['morelabel'],
            'Two hidden posters are named by the plural key, which carries the count.'
        );
        $this->assertStringContainsString('2', $plural->areas['morelabel'], 'And the count really is in the sentence.');
    }

    /**
     * Each poster links its own panel, and an open one is drawn with the integers, the links and the shell of its poster.
     *
     * The open poster is the third, so every one of the five integers differs from the defaults
     * and from each other's reading. The page of the direct courses and the page size travel with
     * every no-JavaScript address, or opening a panel from the second page of this category's
     * own courses would drop the reader back on the first.
     */
    public function test_the_posters_open_their_panel_and_the_open_one_carries_its_shell(): void {
        $viewer = $this->seed();
        $generator = self::getDataGenerator();
        $this->setAdminUser();
        $areas = [];
        foreach (['Defesa', 'Transito', 'Aviacao'] as $name) {
            $area = $generator->create_category(['parent' => $this->ids['root'], 'name' => $name]);
            $generator->create_course(['category' => $area->id, 'fullname' => 'Curso de ' . $name]);
            $areas[$name] = (int) $area->id;
        }
        $this->become($viewer);
        $root = $this->ids['root'];

        $rest = $this->export();
        $posters = array_column($rest->areas['posters'], null, 'name');
        $this->assertSame(['Brigada', 'Defesa', 'Transito', 'Aviacao'], array_keys($posters));
        foreach ($posters as $name => $poster) {
            $id = (int) $poster['id'];
            $this->assertStringEndsWith(
                '/course/index.php?categoryid=' . $root . '&open=' . $id . '#dim-panel',
                $poster['controlurl'],
                $name . ' opens its own panel on this page, landing on it.'
            );
            $this->assertSame(links::showcase($id, [], false)->out(false), $poster['shell']['titleurl']);
            $this->assertSame(links::showcase($id, ['sort' => 'recent'], false)->out(false), $poster['shell']['ctaurl']);
            $this->assertSame(get_string('category_panel_cta', 'theme_dimensions', $name), $poster['shell']['ctalabel']);
            $this->assertFalse($poster['isopen']);
        }
        $this->assertFalse($rest->areas['isopen']);
        $this->assertTrue($rest->areas['hashint'], 'The hint shows while nothing is open.');
        $this->assertSame('', $rest->areas['panelstyle']);
        $this->assertNull($rest->areas['panel']);
        $this->assertSame([], $rest->toolbar->openfields, 'No panel, no hidden field.');

        self::forget();
        $open = $this->export(['open' => $areas['Transito'], 'psort' => 'name', 'page' => 1, 'pagesize' => 2]);
        $this->assertTrue($open->areas['isopen']);
        $this->assertFalse($open->areas['hashint'], 'The hint goes once a panel is open.');
        $this->assertSame(
            '--dim-row-3: 2; --dim-row-2: 3; --dim-row-1: 4; --dim-col-3: 2; --dim-col-2: 0;'
                . ' --dim-area-bg: ' . branding::DEFAULT_BG . ';',
            $open->areas['panelstyle'],
            'The third poster: row 2 at three columns, row 3 at two, row 4 at one; column 2 of 3, column 0 of 2.'
        );
        $this->assertMatchesRegularExpression(
            '~^(--dim-(row|col)-[123]: \d+; )+--dim-area-bg: #[0-9a-f]{6};$~',
            $open->areas['panelstyle'],
            'Five integers and one colour are the attribute\'s whole vocabulary.'
        );
        /* The colour is the open area's band - here the theme's, since nobody branded it - and a
           branded area's panel wears that area's own, the colour its poster is painted with. */
        $this->setAdminUser();
        branding::save($areas['Transito'], (object) ['bgcolour' => '#12484F', 'inkcolour' => '#ffffff', 'owncolours' => 1]);
        branding::forget_all();
        $this->become($viewer);
        $branded = $this->export(['open' => $areas['Transito'], 'psort' => 'name', 'page' => 1, 'pagesize' => 2]);
        $this->assertStringEndsWith(' --dim-area-bg: #12484f;', $branded->areas['panelstyle']);
        $this->assertStringStartsWith(
            '--dim-card-band: #12484f;',
            array_column($branded->areas['posters'], null, 'name')['Transito']['style']
        );
        self::forget();
        $transito = array_column($open->areas['posters'], null, 'name')['Transito'];
        $this->assertTrue($transito['isopen']);
        $panel = $open->areas['panel'];
        $this->assertSame($areas['Transito'], $panel['id']);
        $this->assertSame('Transito', $panel['name']);
        $this->assertTrue($panel['psortname']);
        $this->assertSame(get_string('category_panel_count_one', 'theme_dimensions'), $panel['subtitle']);
        $this->assertCount(1, $panel['cards']);
        $this->assertSame('Transito', $panel['cards'][0]->overline, 'A mini names its area even at the area\'s own level.');
        $this->assertStringEndsWith('#dim-area-' . $areas['Transito'], $panel['closeurl']);
        $this->assertSame($panel['closeurl'], $transito['controlurl'], 'The open poster and Close are one address.');
        foreach (['closeurl', 'sorturlrecent', 'sorturlname'] as $key) {
            $this->assertStringContainsString('page=1', $panel[$key], $key . ' keeps the page of the direct courses.');
            $this->assertStringContainsString('perpage=2', $panel[$key], $key . ' keeps the page size.');
        }
        $this->assertStringNotContainsString('open=', $panel['closeurl'], 'Close is the page without the panel.');
        /* The grouped A-Z order holds with no panel open: Close and every other poster keep it, so the next panel
           opens in it; only the panel's own "Recent" link drops it (asserted below). */
        $this->assertStringContainsString('psort=name', $panel['closeurl'], 'Close keeps the grouped order.');
        $this->assertStringContainsString(
            'psort=name',
            array_column($open->areas['posters'], null, 'name')['Defesa']['controlurl'],
            'Another poster opens its panel in it.'
        );
        $this->assertStringContainsString('open=' . $areas['Transito'], $panel['sorturlrecent']);
        $this->assertStringNotContainsString('psort=', $panel['sorturlrecent']);
        $this->assertStringContainsString('psort=name', $panel['sorturlname']);
        $this->assertStringEndsWith('#dim-panel', $panel['sorturlname']);
        $this->assertSame(
            links::showcase($areas['Transito'], ['sort' => 'name'], false)->out(false),
            $panel['cta']['url'],
            'The call to action opens the area flat, in the panel\'s order.'
        );
        $this->assertSame(
            [
                ['name' => 'open', 'value' => $areas['Transito'], 'region' => 'dim-open-field'],
                ['name' => 'psort', 'value' => 'name', 'region' => 'dim-psort-field'],
            ],
            $open->toolbar->openfields,
            'The toolbar carries the panel, so a filter submitted without JavaScript keeps it open.'
        );

        // A panel the page does not draw is dropped, and the grouped order with it stays (it needs no panel).
        self::forget();
        $stale = $this->export(['open' => $root, 'psort' => 'name']);
        $this->assertFalse($stale->areas['isopen']);
        $this->assertSame(
            [['name' => 'psort', 'value' => 'name', 'region' => 'dim-psort-field']],
            $stale->toolbar->openfields,
            'The grouped order travels without the panel.'
        );

        // Under a criterion the call to action carries what narrows, and nothing else of the page.
        self::forget();
        $searched = $this->export(['open' => $areas['Transito'], 'search' => 'Curso']);
        $this->assertSame(
            links::showcase($areas['Transito'], ['q' => 'Curso', 'sort' => 'recent'], false)->out(false),
            $searched->areas['panel']['cta']['url']
        );
    }

    /**
     * "Clear filters" keeps the organisation and the open panel, in both of the builders that draw it.
     *
     * The empty state draws a second copy of the toolbar's control, on the one page with no rows
     * to show what it did: a builder that forgot the panel would close it under the reader.
     */
    public function test_clear_filters_keeps_the_open_panel_in_both_builders(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $context = $this->export(['search' => 'zzzz', 'open' => $this->ids['child'], 'psort' => 'name']);
        $this->assertTrue($context->areas['isopen'], 'Precondition: under a criterion the poster opens.');
        $this->assertFalse($context->hascards, 'Precondition: the empty state is what is drawn.');
        foreach (['the empty state' => $context->clearurl, 'the toolbar' => $context->toolbar->clearurl] as $where => $url) {
            $this->assertStringContainsString('open=' . $this->ids['child'], $url, $where . ' keeps the panel.');
            $this->assertStringContainsString('psort=name', $url, $where . ' keeps its order.');
            $this->assertStringNotContainsString('q=zzzz', $url, $where . ' drops the search.');
        }
        $this->assertSame($context->toolbar->clearurl, $context->clearurl, 'One address, drawn twice.');
    }

    /**
     * A visitor's merely listed area opens a slice of this page, whose one way out is "see all as a list".
     *
     * The public child is the control: its panel keeps the title link and the call to action,
     * both on the public page. Every address is the anonymous shell's.
     *
     * @return void
     */
    public function test_a_visitors_listed_area_opens_a_slice_whose_way_out_is_the_list(): void {
        $this->seed_public();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $listed = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Listada']);
        $course = $generator->create_course(['category' => $listed->id, 'fullname' => 'Curso Listado Publico']);
        \local_unlistedcourses\discoverability::set_state(
            (int) $course->id,
            \local_unlistedcourses\discoverability::STATE_PUBLIC,
            (int) get_admin()->id
        );
        $script = '/theme/dimensions/category.php?id=';

        $this->become(null);
        $context = $this->export(['anonymous' => true, 'open' => (int) $listed->id]);
        $poster = array_column($context->areas['posters'], null, 'name')['Listada'];
        $this->assertTrue($poster['istext'], 'A text poster.');
        $this->assertSame('text', $poster['variant']);
        $this->assertSame('', $poster['shell']['titleurl'], 'It has no page a visitor may open.');
        $panel = $context->areas['panel'];
        $this->assertTrue($panel['isd20']);
        $this->assertFalse($panel['haslink']);
        $this->assertFalse($panel['cta']['has'], 'No call to action to a page that does not exist.');
        $this->assertFalse($panel['also']['has'], 'No strip: it is a slice of this page.');
        $this->assertTrue($panel['seeaslist']['has']);
        $ways = ['the see-as-list link' => $panel['seeaslist']['url'], 'the poster\'s shell' => $poster['shell']['ctaurl']];
        foreach ($ways as $what => $url) {
            $this->assertStringContainsString($script . $this->ids['root'], $url, $what . ' is this page\'s public address.');
            $this->assertStringContainsString('cat=' . $listed->id, $url, $what . ' narrows it to the area.');
            $this->assertStringContainsString('sort=recent', $url, $what . ' in a flat order.');
        }
        $this->assertSame(
            ['Curso Listado Publico'],
            array_map(static fn(\stdClass $card): string => $card->fullname, $panel['cards'])
        );

        // The control: the public child's panel has its title and its call to action, public both.
        self::forget();
        $public = $this->export(['anonymous' => true, 'open' => $this->ids['child']])->areas['panel'];
        $this->assertFalse($public['isd20']);
        $this->assertTrue($public['cta']['has']);
        $this->assertSame(links::category_page($this->ids['child'], ['sort' => 'recent'])->out(false), $public['cta']['url']);
        $this->assertSame(links::category_page($this->ids['child'])->out(false), $public['titleurl']);
    }

    /**
     * A visitor's flat tree links a merely listed child, as a filter of this page, like a public one.
     *
     * The population marks the listed child not `linkable` because it has no public page of its
     * own, and the posters keep that gate: theirs is the child's page. The tree's entry never
     * leaves this page, so it is a link carrying `cat`, and following it narrows the listing to the
     * public course the page already lists while the tree stays the one at rest. The public child is
     * the control of the link and of the sibling kept, and a listed child holding no public course
     * the control of the naming: the visitor's tree never offers a filter that opens onto nothing.
     *
     * @return void
     */
    public function test_a_visitors_flat_tree_links_a_listed_child_as_a_filter_of_this_page(): void {
        $this->seed_public();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $listed = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Listada']);
        $course = $generator->create_course(['category' => $listed->id, 'fullname' => 'Curso Listado Publico']);
        \local_unlistedcourses\discoverability::set_state(
            (int) $course->id,
            \local_unlistedcourses\discoverability::STATE_PUBLIC,
            (int) get_admin()->id
        );
        $empty = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Vazia']);
        $generator->create_course(['category' => $empty->id, 'fullname' => 'Curso Interno Vazio']);
        $script = '/theme/dimensions/category.php?id=' . $this->ids['root'];

        $this->become(null);
        $flat = $this->export(['anonymous' => true, 'sort' => criteria::SORT_NAME]);
        $tree = array_column($flat->tree, null, 'name');
        $this->assertArrayNotHasKey('Vazia', $tree, 'A child holding no public course is not named at all.');
        foreach (['Listada' => (int) $listed->id, 'Brigada' => $this->ids['child']] as $name => $id) {
            $this->assertArrayHasKey($name, $tree);
            $this->assertStringContainsString($script, $tree[$name]['url'], $name . ' is an address of this public page.');
            $this->assertStringContainsString(
                criteria::PARAM_CAT . '=' . $id,
                $tree[$name]['url'],
                $name . ' narrows it to the child.'
            );
        }

        // Following the listed child's entry: the criterion stands and the page holds its public course alone.
        self::forget();
        $narrowed = $this->export(['anonymous' => true, 'sort' => criteria::SORT_NAME, 'cat' => (int) $listed->id]);
        $this->assertTrue(array_column($narrowed->tree, 'current', 'name')['Listada'], 'The filter is in force.');
        $this->assertSame([(int) $course->id], array_keys($this->cards($narrowed)));
        /* The tree is drawn at rest, not from the narrowed population: the sibling stays a choice,
           and the child holding no public course is still not one. */
        $this->assertSame(
            ['Vitrine & Cia', 'Brigada', 'Listada'],
            array_column($narrowed->tree, 'name'),
            'Choosing one child keeps its sibling in the tree.'
        );

        // The posters keep the own-page gate: the listed child is a text poster, the public one a link.
        self::forget();
        $posters = array_column($this->export(['anonymous' => true])->areas['posters'], null, 'name');
        $this->assertTrue($posters['Listada']['istext'], 'A listed child has no page a visitor may open.');
        $this->assertFalse($posters['Brigada']['istext']);
    }

    /**
     * A directory card's description and its image are built by the server: a plain excerpt and a branding URL.
     *
     * A markup test rendering the template from its own example context proves the template
     * reads the keys, never that the server produces them: a wrong argument order in the image
     * URL, a null description reaching format_text() or a shortener returning the untrimmed HTML
     * would all pass it.
     *
     * Every assertion has its opposite beside it in the same directory: a category with no
     * description and no image, whose two flags are false.
     */
    public function test_a_directory_card_carries_a_plain_excerpt_and_its_branding_image(): void {
        global $DB;
        $viewer = $this->seed();
        $generator = self::getDataGenerator();

        // A long HTML description on the fixture's own root, an image on a category of its own.
        $long = '<p>Formação continuada para a organização, com <b>cursos técnicos</b>, eventos e '
            . 'atividades de campo ao longo de todo o ano letivo, em várias cidades do estado.</p>';
        $DB->set_field('course_categories', 'description', $long, ['id' => $this->ids['root']]);
        $DB->set_field('course_categories', 'descriptionformat', FORMAT_HTML, ['id' => $this->ids['root']]);
        $branded = (int) $generator->create_category(['name' => 'Com capa', 'description' => ''])->id;
        $generator->create_course(['category' => $branded]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \core\context\coursecat::instance($branded)->id,
            'component' => branding::COMPONENT,
            'filearea' => branding::AREA_HERO,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'capa.png',
        ], base64_decode(self::PNG));

        $this->become($viewer);
        $directory = population::resolve(new criteria(0))->directory;
        $entries = array_column($directory, null, 'name');

        $root = $entries['Root & Co'];
        $this->assertTrue($root['hasdescription']);
        $this->assertStringNotContainsString('<', $root['description'], 'The excerpt is plain text, not markup.');
        $this->assertStringContainsString('Formação continuada', $root['description']);
        $this->assertLessThanOrEqual(
            population::EXCERPT_LENGTH + 10,
            \core_text::strlen($root['description']),
            'A description longer than the excerpt is shortened, ellipsis included.'
        );

        $capa = $entries['Com capa'];
        $this->assertFalse($capa['hasdescription'], 'A blank description is no excerpt.');
        $this->assertSame('', $capa['description']);
        $this->assertTrue($capa['hasimage']);
        $this->assertStringContainsString(
            '/theme_dimensions/categoryhero/' . $branded . '/' . $file->get_contenthash() . '/capa.png',
            $capa['imageurl'],
            'The image URL names the category, the content hash and the file.'
        );
        $this->assertFalse($root['hasimage'], 'An unbranded category keeps the flat band.');
    }

    /**
     * A root whose every top-level category is withheld says so about categories, not about courses.
     *
     * The directory lists categories, so its empty state cannot be the
     * listing's "there is no course to show here yet" - the reader would be
     * told the site has no courses when what it has is nothing they may
     * browse. The control is the same export a moment earlier, with the
     * category admitted.
     */
    public function test_an_empty_directory_names_categories_and_not_courses(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $withheld = (int) $generator->create_category(['name' => 'Restrita'])->id;
        $generator->create_course(['category' => $withheld]);
        $viewer = $generator->create_user();

        $PAGE->set_url('/course/index.php');
        $renderer = $PAGE->get_renderer('core');
        $export = function () use ($renderer): \stdClass {
            $criteria = new criteria(0);
            $listing = new category_listing(
                core_course_category::top(),
                $criteria,
                population::resolve($criteria),
                category_toolbar::VIEW_CARDS
            );
            return $listing->export_for_template($renderer);
        };

        // The control: while the category is admitted there IS a directory.
        $this->become($viewer);
        $this->assertTrue($export()->hasdirectory);

        /* Every top-level category, not only the one seeded here: a Moodle site always carries
           the default Miscellaneous category, which is a card like any other. */
        foreach ($DB->get_fieldset_select('course_categories', 'id', 'parent = 0') as $topid) {
            $this->set_category_state(
                (int) $topid,
                \local_unlistedcourses\category_discoverability::STATE_UNLISTED
            );
        }
        $this->become($viewer);
        $context = $export();
        $this->assertFalse($context->hasdirectory, 'Nothing this viewer may browse is left.');
        $this->assertSame(
            get_string('category_directory_empty', 'theme_dimensions'),
            $context->areas['emptylabel']
        );
        $this->assertNotSame(
            get_string('category_emptycategory', 'theme_dimensions'),
            $context->areas['emptylabel'],
            'The listing\'s own empty state talks about courses and is the wrong sentence here.'
        );
    }

    /**
     * The count line says how many and where, in every organisation and under every criterion.
     *
     * The number, set in bold, travels apart from the sentence after it. Grouped at rest it is
     * the subtree's; grouped under a criterion it is what the page draws, and the sentence says
     * where - the direct matches and the areas holding the rest - with the flat Recent list
     * carrying the criterion as "see all as a list"; flat, it is the listing's total in the
     * tree's chosen category. The direct-only and the areas-only answers are the two halves the
     * "both" answer joins, and nothing matched is the branch with no link.
     */
    public function test_the_count_line_says_how_many_and_where_in_every_organisation(): void {
        $viewer = $this->seed();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $transito = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Transito']);
        foreach (['Curso de Transito', 'Curso de Patrulha', 'Blitz'] as $fullname) {
            $generator->create_course(['category' => $transito->id, 'fullname' => $fullname]);
        }
        $this->become($viewer);
        $root = $this->ids['root'];
        $line = static fn(\stdClass $context): array => [$context->countnumber, $context->countline, $context->seeallurl];
        $seeall = static fn(array $params): string => links::showcase($root, $params + ['sort' => 'recent'], false)->out(false);

        $this->assertSame(['8', 'courses in Root & Co', ''], $line($this->export()), 'At rest: the subtree, and no link.');
        self::forget();
        $this->assertSame(
            ['5', 'courses for “Curso”: 2 in this category and 3 in 2 areas', $seeall(['q' => 'Curso'])],
            $line($this->export(['search' => 'Curso'])),
            'Under a search: the direct matches and the areas holding the rest, and the flat Recent list.'
        );
        self::forget();
        $this->assertSame(
            ['1', 'course for “Livre”: 1 in this category', $seeall(['q' => 'Livre'])],
            $line($this->export(['search' => 'Livre'])),
            'The direct half alone.'
        );
        self::forget();
        $this->assertSame(
            ['1', 'course for “Patrulha”: 1 in 1 area', $seeall(['q' => 'Patrulha'])],
            $line($this->export(['search' => 'Patrulha'])),
            'The areas half alone, in its singular.'
        );
        self::forget();
        $this->assertSame(
            ['0', 'courses for “zzzz”', ''],
            $line($this->export(['search' => 'zzzz'])),
            'Nothing matched: the head alone, and nothing to list.'
        );
        self::forget();
        $this->assertSame(
            ['1', 'course with these filters: 1 in this category', $seeall(['chip' => classifier::STATE_ENROLLED])],
            $line($this->export(['state' => classifier::STATE_ENROLLED])),
            'A filter and no term: "these filters", and the list carries the filter.'
        );
        self::forget();
        $this->assertSame(
            ['2', 'courses with these filters: 1 in this category and 1 in 1 area'],
            array_slice($line($this->export(['state' => classifier::STATE_OPEN])), 0, 2),
            'Both halves under a filter.'
        );
        self::forget();
        $this->assertSame(
            ['1', 'course in Brigada', ''],
            $line($this->export(['sort' => criteria::SORT_NAME, 'cat' => $this->ids['child']])),
            'Flat: the tree\'s chosen category is where the courses are counted.'
        );
        self::forget();
        $this->assertSame(
            ['2', 'courses for “Curso” in Transito', ''],
            $line($this->export(['sort' => criteria::SORT_RECENT, 'cat' => (int) $transito->id, 'search' => 'Curso'])),
            'Flat under a search: the term and the chosen category.'
        );
    }

    /**
     * The areas' own sentences in result mode, from the accordion in hand: every arm of every branch.
     *
     * category_listing::results() is a pure function of the page's accordion, its total and its
     * request, so its arms are read here from pages built by hand - the zero areas counted with
     * and without the open one, "elsewhere" at none, one and several, the reveal's label, "nothing
     * anywhere" and the one area opened by itself - where a seeded fixture reaches a few per run.
     * A page at rest and a page with no area are the controls: the same accordion answers nothing.
     */
    public function test_the_areas_results_name_the_zero_areas_elsewhere_the_reveal_and_the_area_to_open(): void {
        $this->resetAfterTest();
        $page = static function (int $total, array $counts): page_result {
            $result = new page_result();
            $result->total = $total;
            foreach ($counts as $index => $count) {
                $result->accordion[] = [
                    'id' => 100 + $index,
                    'name' => 'Area ' . $index,
                    'count' => $count,
                    'url' => '',
                    'linkable' => true,
                ];
            }
            return $result;
        };
        $str = static fn(string $key, $a = null): string => get_string($key, 'theme_dimensions', $a);
        $term = static fn(string $count): \stdClass => (object) ['term' => 'fogo', 'count' => $count];
        $search = new criteria(7, ['search' => 'fogo']);

        $results = category_listing::results($page(2, [0, 5, 0, 0]), $search, 0);
        $this->assertTrue($results['filtered']);
        $this->assertSame($str('category_nomatch', $term('3')), $results['nomatchlabelall'], 'Every zero area.');
        $this->assertSame($str('category_nomatch', $term('2')), $results['nomatchlabelless'], 'One fewer, for an open one.');
        $this->assertSame($str('category_panel_zeroelsewhere_one'), $results['elsewhere']);
        $this->assertFalse($results['allempty'], 'Two courses sit here.');
        $this->assertSame(0, $results['autoopen'], 'With a result sitting here, nothing opens by itself.');

        $single = category_listing::results($page(0, [0, 5, 0]), $search, 0);
        $this->assertSame(101, $single['autoopen'], 'Nothing here and one area holding every result: it opens.');
        $this->assertSame($str('category_countline_autoopened', 'Area 1'), $single['autoopenlabel']);
        $this->assertSame($str('category_nomatch_one', 'fogo'), $single['nomatchlabelless']);
        $this->assertSame($str('category_panel_zeroelsewhere_one'), $single['elsewhere']);
        $this->assertSame(
            $str('category_panel_zeronowhere'),
            category_listing::results($page(0, [0, 5, 0]), $search, 101)['elsewhere'],
            'Seen from the open area itself, no OTHER area holds one.'
        );

        $two = category_listing::results($page(0, [3, 5, 0]), $search, 0);
        $this->assertSame([0, ''], [$two['autoopen'], $two['autoopenlabel']], 'Two areas: the reader chooses.');
        $this->assertSame($str('category_panel_zeroelsewhere', '2'), $two['elsewhere']);
        $this->assertSame($str('category_nomatch_one', 'fogo'), $two['nomatchlabelall']);
        $this->assertSame('', $two['nomatchlabelless'], 'No sentence for no area.');

        $none = category_listing::results($page(0, [0, 0]), $search, 0);
        $this->assertTrue($none['allempty'], 'Nothing here and nothing in any area.');
        $this->assertSame(0, $none['autoopen']);
        $this->assertSame($str('category_panel_zeronowhere'), $none['elsewhere']);
        $this->assertFalse(category_listing::results($page(1, [0, 0]), $search, 0)['allempty'], 'Control: one here.');

        $filters = new criteria(7, ['state' => classifier::STATE_ENROLLED]);
        $byfilter = category_listing::results($page(0, [0, 0, 4]), $filters, 0);
        $this->assertSame($str('category_nomatchfilters', '2'), $byfilter['nomatchlabelall'], 'No term: "these filters".');
        $this->assertSame($str('category_nomatchfilters_one'), $byfilter['nomatchlabelless']);

        // The reveal past the slice: how many of its posters hold a result, and its label.
        $slice = population::DIRECTORY_SLICE;
        $more = category_listing::results($page(0, array_fill(0, $slice + 2, 1)), $search, 0);
        $this->assertSame([2, $str('category_directory_moreresults', 2)], [$more['moreresults'], $more['morelabel']]);
        $counts = array_fill(0, $slice + 2, 1);
        $counts[$slice] = 0;
        $one = category_listing::results($page(0, $counts), $search, 0);
        $this->assertSame([1, $str('category_directory_moreresults_one')], [$one['moreresults'], $one['morelabel']]);
        $counts[$slice + 1] = 0;
        $empty = category_listing::results($page(0, $counts), $search, 0);
        $this->assertSame(
            [0, $str('category_directory_more', 2)],
            [$empty['moreresults'], $empty['morelabel']],
            'None behind it: the label at rest.'
        );

        // The controls: at rest, and a page with no area, answer nothing whatever the numbers.
        $rest = category_listing::results($page(0, array_fill(0, $slice + 1, 1)), new criteria(7), 0);
        $this->assertFalse($rest['filtered']);
        $this->assertSame(['', '', '', 0, false, 0], [
            $rest['nomatchlabelall'],
            $rest['nomatchlabelless'],
            $rest['elsewhere'],
            $rest['moreresults'],
            $rest['allempty'],
            $rest['autoopen'],
        ]);
        $this->assertSame($str('category_directory_more_one'), $rest['morelabel'], 'The reveal keeps its label at rest.');
        $this->assertFalse(category_listing::results($page(0, []), $search, 0)['filtered'], 'No area, no result mode.');
    }

    /**
     * The root under a search is in result mode with no course of its own and opens nothing by itself.
     *
     * results() reads a page's total as the courses sitting directly in it, and the root's total
     * is the hero's number - the matches of the whole site - so without the root's own reading
     * "nothing anywhere" could never be said at the root while anything matched in a category no
     * poster shows, and an area would open by itself whenever one poster held every match; the
     * root never opens an area by itself. A category page with the same numbers is the control:
     * its total is the courses sitting there, and one area holding every result opens.
     */
    public function test_the_root_under_a_search_opens_no_area_and_has_no_course_of_its_own(): void {
        $this->resetAfterTest();
        $page = static function (int $total, array $counts): page_result {
            $result = new page_result();
            $result->total = $total;
            foreach ($counts as $index => $count) {
                $result->accordion[] = [
                    'id' => 100 + $index,
                    'name' => 'Area ' . $index,
                    'count' => $count,
                    'url' => '',
                    'linkable' => true,
                ];
            }
            return $result;
        };
        $root = new criteria(0, ['search' => 'fogo']);

        $single = category_listing::results($page(5, [0, 5, 0]), $root, 0);
        $this->assertTrue($single['filtered'], 'A search puts the root in result mode.');
        $this->assertSame([0, ''], [$single['autoopen'], $single['autoopenlabel']], 'Nothing opens by itself at the root.');
        $this->assertFalse($single['allempty']);
        $this->assertSame('', $single['searchallurl'], 'The root is where "search all areas" leads: it offers none.');

        $none = category_listing::results($page(3, [0, 0]), $root, 0);
        $this->assertTrue($none['allempty'], 'No poster holds a match: nothing anywhere, whatever the hero counts.');

        // The control: a category page, whose total is the courses sitting directly in it.
        $category = new criteria(7, ['search' => 'fogo']);
        $this->assertSame(101, category_listing::results($page(0, [0, 5, 0]), $category, 0)['autoopen']);
        $this->assertFalse(category_listing::results($page(3, [0, 0]), $category, 0)['allempty'], 'Three sit here.');
    }

    /**
     * "Search all areas" is the root carrying the term, offered where the reader may open the root.
     *
     * A real account always reaches the root; a visitor only where the site opened it; the guest's
     * root is core's course index, which ignores the term, so it is offered nothing. A page
     * narrowed by filters alone has nothing to carry to a root that reads the search only.
     */
    public function test_search_all_areas_is_the_root_carrying_the_term_where_the_root_is_reachable(): void {
        $this->resetAfterTest();
        $zero = new page_result();
        $zero->accordion = [['id' => 100, 'name' => 'Area', 'count' => 0, 'url' => '', 'linkable' => true]];
        $search = new criteria(7, ['search' => 'fogo']);

        $this->setUser(self::getDataGenerator()->create_user());
        $url = category_listing::results($zero, $search, 0)['searchallurl'];
        $this->assertStringEndsWith('/course/index.php?q=fogo', $url, 'The logged-in root, the term alone.');
        $this->assertSame(
            '',
            category_listing::results($zero, new criteria(7, ['state' => classifier::STATE_ENROLLED]), 0)['searchallurl'],
            'Filters alone: the root reads none of them.'
        );

        $this->setGuestUser();
        $this->assertSame('', category_listing::results($zero, $search, 0)['searchallurl'], 'The guest\'s root ignores a term.');

        $this->setUser(null);
        $anonymous = clone $zero;
        $anonymous->anonymous = true;
        $this->assertSame('', category_listing::results($anonymous, $search, 0)['searchallurl'], 'A closed root is a login wall.');
        set_config(publicaccess::SETTING_ROOT, '1', 'theme_dimensions');
        $this->assertSame(
            links::showcase(0, [criteria::PARAM_SEARCH => 'fogo'], true)->out(false),
            category_listing::results($anonymous, $search, 0)['searchallurl'],
            'The control: a root the site opened is the visitor\'s own public root, carrying the term.'
        );
    }

    /**
     * Under a criterion a zero poster leaves the grid, the open one stays, and the summary names them.
     *
     * Every poster wears its pill; a zero poster is `hidden` on its item and named in the closed
     * summary after the grids, linking its own page without the criterion; the open one stays in
     * the grid saying "0 results", closes onto that summary, and its zero state suggests the other
     * areas holding a result. The panel's row is counted among the visible posters. At rest the
     * same fixture is the control: an empty poster stays, a link to its page, and nothing wears a
     * pill.
     */
    public function test_under_a_criterion_a_zero_poster_leaves_the_grid_and_the_open_one_stays(): void {
        $viewer = $this->seed();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $areas = ['Brigada' => $this->ids['child']];
        $courses = [
            'Aviacao' => ['Voo livre'],
            'Transito' => ['Curso de Transito', 'Curso de Patrulha'],
            'Vazia' => [],
            'Defesa' => ['Curso de Defesa'],
        ];
        foreach ($courses as $name => $fullnames) {
            $area = $generator->create_category(['parent' => $this->ids['root'], 'name' => $name]);
            foreach ($fullnames as $fullname) {
                $generator->create_course(['category' => $area->id, 'fullname' => $fullname]);
            }
            $areas[$name] = (int) $area->id;
        }
        $this->become($viewer);
        $str = static fn(string $key, $a = null): string => get_string($key, 'theme_dimensions', $a);

        $rest = $this->export();
        $byname = array_column($rest->areas['posters'], null, 'name');
        $this->assertSame(['Brigada', 'Aviacao', 'Transito', 'Vazia', 'Defesa'], array_keys($byname));
        $this->assertFalse($byname['Vazia']['hidden'], 'At rest an empty poster stays in the grid.');
        $this->assertFalse($byname['Vazia']['expandable'], 'As a link to its page.');
        $pills = array_map(static fn(array $poster): bool => $poster['results']['has'], $rest->areas['posters']);
        $this->assertSame([false, false, false, false, false], $pills, 'No pill at rest.');
        $this->assertFalse($rest->areas['filtered']);
        $this->assertFalse($rest->areas['nomatch']['show']);
        $this->assertSame([], $rest->areas['nomatch']['areas']);

        self::forget();
        $searched = $this->export(['search' => 'Curso']);
        $posters = $searched->areas['posters'];
        $this->assertTrue($searched->areas['filtered']);
        $this->assertSame(
            [
                $str('category_results_one'),
                $str('category_results', '0'),
                $str('category_results', '2'),
                $str('category_results', '0'),
                $str('category_results_one'),
            ],
            array_map(static fn(array $poster): string => $poster['results']['label'], $posters),
            'Every poster wears its number.'
        );
        $this->assertSame(
            ['1', '0', '2', '0', '1'],
            array_map(static fn(array $poster): string => $poster['results']['count'], $posters)
        );
        $this->assertSame([false, true, false, true, false], array_column($posters, 'hidden'), 'The zero ones leave the grid.');
        $this->assertSame(
            [false, true, false, true, false],
            array_map(static fn(array $poster): bool => $poster['results']['iszero'], $posters),
            'In the zero colours.'
        );
        $nomatch = $searched->areas['nomatch'];
        $this->assertTrue($nomatch['show']);
        $this->assertSame($str('category_nomatch', (object) ['term' => 'Curso', 'count' => '2']), $nomatch['label']);
        $this->assertSame($nomatch['labelall'], $nomatch['label'], 'No zero poster is open: every zero area is counted.');
        $this->assertSame($str('category_nomatch_one', 'Curso'), $nomatch['labelless']);
        $this->assertSame(
            [
                [
                    'id' => $areas['Aviacao'],
                    'name' => 'Aviacao',
                    'url' => links::showcase($areas['Aviacao'], [], false)->out(false),
                    'haslink' => true,
                    'isopen' => false,
                ],
                [
                    'id' => $areas['Vazia'],
                    'name' => 'Vazia',
                    'url' => links::showcase($areas['Vazia'], [], false)->out(false),
                    'haslink' => true,
                    'isopen' => false,
                ],
            ],
            $nomatch['areas'],
            'Named in the posters\' order, each linking its own page WITHOUT the criterion: it would open empty.'
        );
        $this->assertSame($str('category_panel_zeroelsewhere', '3'), $searched->areas['elsewhere']);
        $this->assertFalse($searched->areas['allempty']);

        self::forget();
        $open = $this->export(['search' => 'Curso', 'open' => $areas['Defesa']]);
        $this->assertTrue($open->areas['isopen']);
        $this->assertSame(
            '--dim-row-3: 2; --dim-row-2: 3; --dim-row-1: 4; --dim-col-3: 2; --dim-col-2: 0;'
                . ' --dim-area-bg: ' . branding::DEFAULT_BG . ';',
            $open->areas['panelstyle'],
            'Defesa is the fifth poster and the third VISIBLE one: its row is counted among what the reader sees.'
        );
        $this->assertStringEndsWith(
            '#dim-area-' . $areas['Defesa'],
            $open->areas['panel']['closeurl'],
            'It closes onto its poster.'
        );
        $this->assertSame([], $open->areas['panel']['suggestions'], 'A panel with results suggests nothing.');

        self::forget();
        $zero = $this->export(['search' => 'Curso', 'open' => $areas['Aviacao']]);
        $byname = array_column($zero->areas['posters'], null, 'name');
        $this->assertTrue($zero->areas['isopen'], 'Under a criterion a zero area opens.');
        $this->assertFalse($byname['Aviacao']['hidden'], 'The open poster stays in the grid, zero and all.');
        $this->assertTrue($byname['Aviacao']['results']['iszero']);
        $this->assertTrue($byname['Vazia']['hidden'], 'Control: the other zero poster leaves.');
        $panel = $zero->areas['panel'];
        $this->assertStringEndsWith(
            '#' . category_panel::ANCHOR_NOMATCH,
            $panel['closeurl'],
            'It closes onto the summary it goes into.'
        );
        $this->assertSame($panel['closeurl'], $byname['Aviacao']['controlurl'], 'The open poster and Close are one address.');
        $this->assertTrue($panel['iszero']);
        $this->assertSame($str('category_panel_zero', (object) ['term' => 'Curso', 'name' => 'Aviacao']), $panel['zero']);
        $this->assertTrue($panel['hassuggestions']);
        $this->assertSame(['Brigada', 'Transito', 'Defesa'], array_column($panel['suggestions'], 'name'));
        $this->assertSame(['1', '2', '1'], array_column($panel['suggestions'], 'count'));
        $this->assertSame(
            [
                category_panel::results_label(1),
                category_panel::results_label(2),
                category_panel::results_label(1),
            ],
            array_column($panel['suggestions'], 'countlabel'),
            'And the pill\'s words, which a screen reader is read in place of the digit.'
        );
        foreach ($panel['suggestions'] as $suggestion) {
            $this->assertStringContainsString('open=' . $suggestion['id'], $suggestion['url']);
            $this->assertStringContainsString('q=Curso', $suggestion['url'], 'It keeps the page\'s criterion.');
            $this->assertStringEndsWith('#dim-panel', $suggestion['url']);
        }
        $this->assertSame($str('category_nomatch_one', 'Curso'), $zero->areas['nomatch']['label'], 'The open one is not counted.');
        $this->assertSame([true, false], array_column($zero->areas['nomatch']['areas'], 'isopen'), 'But is listed, hidden.');

        self::forget();
        $nothing = $this->export(['search' => 'zzzz']);
        $this->assertTrue($nothing->areas['allempty'], 'Nothing here and nothing in any area: the box says so.');
        $this->assertFalse($nothing->direct['show']);
        $this->assertSame($nothing->clearurl, $nothing->areas['clearurl'], 'Its way out is the page\'s own.');
        self::forget();
        $this->assertFalse(
            $this->export(['search' => 'zzzz', 'open' => $this->ids['child']])->areas['allempty'],
            'Not while a panel is open, which says so itself.'
        );
    }

    /**
     * An open panel's call to action counts results under a criterion and courses at rest.
     *
     * Ten matching courses in one area: past the panel's eight, two are left for the area's page,
     * and under a search they are two results, not two courses. The page at rest is the control, so
     * the assertion cannot pass on a panel that always words its call to action one way.
     */
    public function test_an_open_panels_call_to_action_counts_results_under_a_criterion(): void {
        $viewer = $this->seed();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $area = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Salvamento']);
        for ($i = 1; $i <= population::PANEL_SIZE + 2; $i++) {
            $generator->create_course(['category' => $area->id, 'fullname' => 'Curso de Salvamento ' . $i]);
        }
        $this->become($viewer);
        $str = static fn(string $key, $a = null): string => get_string($key, 'theme_dimensions', $a);

        $rest = $this->export(['open' => (int) $area->id]);
        $this->assertTrue($rest->areas['isopen'], 'Precondition: the panel is open at rest.');
        $this->assertSame(
            $str('category_panel_cta_more', (object) ['name' => 'Salvamento', 'count' => '2']),
            $rest->areas['panel']['cta']['label'],
            'Control: at rest the two left are courses.'
        );

        self::forget();
        $searched = $this->export(['search' => 'Salvamento', 'open' => (int) $area->id]);
        $this->assertTrue($searched->areas['filtered'], 'Precondition: the page is under a criterion.');
        $this->assertTrue($searched->areas['isopen'], 'Precondition: the panel is open under it.');
        $this->assertSame(
            $str('category_panel_cta_moreresults', (object) ['name' => 'Salvamento', 'count' => '2']),
            $searched->areas['panel']['cta']['label'],
            'Under a search the two left are results.'
        );
    }

    /**
     * The reveal past twenty-four opens by itself when it holds a result, and is hidden when it holds none.
     *
     * At rest it is closed with its label at rest. Under a criterion matching a course behind it
     * it is drawn open and relabelled with the areas holding a result; under one matching nothing
     * behind it, it is hidden - still in the document for the client - unless the open poster is
     * behind it, which keeps it drawn and open.
     */
    public function test_the_reveal_opens_by_itself_when_it_holds_a_result(): void {
        $viewer = $this->seed();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        // Brigada is the first area: twenty-four more, and the last of them is behind the reveal.
        $last = 0;
        for ($i = 1; $i <= population::DIRECTORY_SLICE; $i++) {
            $area = $generator->create_category(['parent' => $this->ids['root'], 'name' => sprintf('Area %02d', $i)]);
            $generator->create_course([
                'category' => $area->id,
                'fullname' => $i === population::DIRECTORY_SLICE ? 'Unico' : 'Comum ' . $i,
            ]);
            $last = (int) $area->id;
        }
        $this->become($viewer);
        $str = static fn(string $key, $a = null): string => get_string($key, 'theme_dimensions', $a);
        $reveal = static fn(\stdClass $context): array => [
            $context->areas['hasmore'],
            $context->areas['moreopen'],
            $context->areas['morehidden'],
            $context->areas['morelabel'],
        ];

        $rest = $this->export();
        $this->assertSame([(int) $last], array_map('intval', array_column($rest->areas['postersmore'], 'id')), 'Precondition.');
        $this->assertSame([true, false, false, $str('category_directory_more_one')], $reveal($rest), 'At rest: closed.');
        self::forget();
        $this->assertSame(
            [true, true, false, $str('category_directory_moreresults_one')],
            $reveal($this->export(['search' => 'Unico'])),
            'A result behind it: drawn open, and saying so.'
        );
        self::forget();
        $this->assertSame(
            [true, false, true, $str('category_directory_more_one')],
            $reveal($this->export(['search' => 'Comum'])),
            'None behind it: hidden, in the document for the client.'
        );
        self::forget();
        $this->assertSame(
            [true, true, false, $str('category_directory_more_one')],
            $reveal($this->export(['search' => 'Comum', 'open' => $last])),
            'Unless the open poster is behind it, zero and all.'
        );
    }

    /**
     * A visitor's zero areas are the posters at rest, named in the summary, a merely listed one as text.
     *
     * Under a search the visitor's posters are still the children holding a public course - the one
     * with none is no poster at all - and the ones the search misses collapse into the summary: the
     * public one as a link to its public page without the criterion, the merely listed one as its
     * name, since a visitor has no page of it to open.
     */
    public function test_a_visitors_zero_areas_are_named_in_the_summary_and_a_listed_one_as_text(): void {
        $this->seed_public();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $public = function (int $categoryid, string $fullname) use ($generator, $adminid): void {
            $course = $generator->create_course(['category' => $categoryid, 'fullname' => $fullname]);
            \local_unlistedcourses\discoverability::set_state(
                (int) $course->id,
                \local_unlistedcourses\discoverability::STATE_PUBLIC,
                $adminid
            );
        };
        $listed = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Listada']);
        $public((int) $listed->id, 'Curso Listado');
        $resgate = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Resgate']);
        $interna = $generator->create_category(['parent' => $this->ids['root'], 'name' => 'Interna']);
        foreach ([$resgate, $interna] as $category) {
            \local_unlistedcourses\category_discoverability::set_state(
                (int) $category->id,
                \local_unlistedcourses\category_discoverability::STATE_PUBLIC,
                $adminid
            );
        }
        $public((int) $resgate->id, 'Curso de Resgate');
        $generator->create_course(['category' => $interna->id, 'fullname' => 'Curso Fechado Dois']);

        $this->become(null);
        $context = $this->export(['anonymous' => true, 'search' => 'Filho']);
        $this->assertSame(
            ['Brigada', 'Listada', 'Resgate'],
            array_column($context->areas['posters'], 'name'),
            'The posters at rest: an area with no public course is none of them.'
        );
        $this->assertSame([1, 0, 0], array_column($context->areas['posters'], 'count'));
        $entries = array_column($context->areas['nomatch']['areas'], null, 'name');
        $this->assertSame(['Listada', 'Resgate'], array_keys($entries));
        $this->assertFalse($entries['Listada']['haslink'], 'A merely listed area is named as text.');
        $this->assertSame('', $entries['Listada']['url']);
        $this->assertTrue($entries['Resgate']['haslink'], 'Control: the public one is a link.');
        $this->assertSame(
            links::category_page((int) $resgate->id)->out(false),
            $entries['Resgate']['url'],
            'To its own public page, without the criterion.'
        );
        $this->assertSame(
            get_string('category_nomatch', 'theme_dimensions', (object) ['term' => 'Filho', 'count' => '2']),
            $context->areas['nomatch']['label']
        );
    }

    /**
     * The filter panel is closed on load whatever narrows the page, and its badge counts what it holds.
     *
     * The row under the line says what narrows - every criterion the line does not show is a chip
     * there - so the panel needs no opening to say it, and the toolbar's context carries no such
     * switch. The badge counts the field, which lives in the panel, and not the term (its box), a
     * scope value (a cell of the line) nor the period (the date button); the row shows for every one
     * of them.
     */
    public function test_the_filter_panel_is_closed_on_load_and_its_badge_counts_what_it_holds(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $rest = $this->export()->toolbar;
        $this->assertObjectNotHasProperty('filtersopen', $rest, 'Nothing opens the panel on the server.');
        $this->assertFalse($rest->hasfilters, 'At rest the row under the line is the count sentence alone.');
        $fieldid = array_key_first(facets::fields());
        $cases = [
            'a search' => [['search' => 'Curso'], 0],
            'a scope value' => [['state' => classifier::STATE_ENROLLED], 0],
            'a field' => [['fields' => [$fieldid => landingfields::MODALITY_ONLINE]], 1],
            // The period is the date button's: its dot and its name say it, never the Filters badge.
            'a start' => [['datefrom' => '2026-01-01'], 0],
            'an end' => [['dateto' => '2026-12-31'], 0],
        ];
        foreach ($cases as $what => [$options, $badge]) {
            self::forget();
            $toolbar = $this->export($options)->toolbar;
            $this->assertObjectNotHasProperty('filtersopen', $toolbar, $what);
            $this->assertSame($badge, $toolbar->filtercount, $what . ' on the badge.');
            $this->assertTrue($toolbar->hasfilters, $what . ' shows the row under the line.');
        }
    }

    /**
     * The date button's name and dot follow the period in force, in the page's own export.
     *
     * Without a period the button is named by the filter alone and draws no dot; with one, a bound alone or both,
     * its name is the filter followed by the period chip's own sentence and its dot is drawn, so a page read
     * without JavaScript names the period on the line. Its template is the same name with the client's
     * placeholder in the sentence's place, and its layer's "Clear period" goes where the period chip goes.
     *
     * Changes that must make it fail: the name left the filter's alone under a period; the dot drawn at rest or
     * missing under a period; the name written from anything but the period chip's sentence; "Clear period"
     * keeping a bound.
     */
    public function test_the_date_button_is_named_with_the_period_and_dotted_while_one_is_set(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $legend = get_string('category_dategroup', 'theme_dimensions');
        $rest = $this->export()->toolbar;
        $this->assertFalse($rest->hasperiod, 'No dot at rest.');
        $this->assertSame($legend, $rest->datebuttonlabel, 'The filter\'s name alone.');
        $this->assertSame($legend . ': ' . category_toolbar::PERIOD_PLACEHOLDER, $rest->datebuttontemplate);
        $periods = [
            ['datefrom' => '2026-01-01'],
            ['dateto' => '2026-12-31'],
            ['datefrom' => '2026-01-01', 'dateto' => '2026-12-31'],
        ];
        foreach ($periods as $bounds) {
            self::forget();
            $toolbar = $this->export($bounds)->toolbar;
            $chips = array_values(array_filter(
                $toolbar->chips,
                static fn(array $chip): bool => $chip['param'] === category_toolbar::CHIP_PERIOD
            ));
            $this->assertCount(1, $chips, 'Precondition: the period has its chip.');
            $this->assertTrue($toolbar->hasperiod, 'The dot, while a period is set.');
            $this->assertSame($legend . ': ' . $chips[0]['label'], $toolbar->datebuttonlabel, 'The chip\'s own words.');
            $this->assertSame($chips[0]['url'], $toolbar->dateclearurl, '"Clear period" goes where the period chip goes.');
            $this->assertStringNotContainsString('from=', $toolbar->dateclearurl);
            $this->assertStringNotContainsString('to=', $toolbar->dateclearurl);
        }
    }

    /**
     * The two page-cutting modes.
     *
     * @return array
     */
    public static function modes_provider(): array {
        return ['state sort on (page cut in PHP)' => [true], 'state sort off (page cut in SQL)' => [false]];
    }

    /**
     * Under a criterion the count line counts what the page draws, so a course no poster shows is left out of it.
     *
     * A course the viewer is enrolled in, inside a child category the viewer may not browse, is in
     * the population (the enrolment rescues it) but in no poster (the child is withheld, so it is no
     * area of the page). At rest the count line is the subtree's number and counts it; under a
     * criterion it is the direct matches plus the posters' counts, which cannot, while "See all as a
     * list" opens the flat order, which does - and the automatic opening names the one area with a
     * result although a second match exists. Recorded as it is (category_listing::count_line()), in
     * both modes, so that a change to either number is a decision and not an accident.
     *
     * @param bool $statesort The page-cutting mode.
     */
    #[DataProvider('modes_provider')]
    public function test_under_a_criterion_the_count_line_leaves_out_a_course_no_poster_shows(bool $statesort): void {
        $this->resetAfterTest();
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Raiz']);
        $open = $generator->create_category(['parent' => $root->id, 'name' => 'Aberta']);
        $withheld = $generator->create_category(['parent' => $root->id, 'name' => 'Restrita']);
        $generator->create_course(['category' => $open->id, 'fullname' => 'Curso Aberto']);
        $rescued = $generator->create_course(['category' => $withheld->id, 'fullname' => 'Curso Restrito']);
        $this->set_category_state((int) $withheld->id, \local_unlistedcourses\category_discoverability::STATE_UNLISTED);
        $viewer = $generator->create_user();
        $generator->enrol_user($viewer->id, $rescued->id);
        $this->ids['root'] = (int) $root->id;
        $this->become($viewer);

        $rest = $this->export();
        $this->assertSame(['Aberta'], array_column($rest->areas['posters'], 'name'), 'The withheld child is no area.');
        $this->assertSame(
            ['2', 'courses in Raiz'],
            [$rest->countnumber, $rest->countline],
            'At rest: the subtree, the rescued course included.'
        );

        self::forget();
        $search = $this->export(['search' => 'Curso']);
        $this->assertSame(
            ['1', 'course for “Curso”: 1 in 1 area'],
            [$search->countnumber, $search->countline],
            'Under a criterion: what the posters count, and the rescued course is in none of them.'
        );
        $this->assertSame((int) $open->id, $search->results['autoopen'], 'The one area with a result opens by itself.');
        $this->assertStringContainsString('sort=recent', $search->seeallurl);

        self::forget();
        $flat = population::resolve(new criteria((int) $root->id, ['search' => 'Curso', 'sort' => criteria::SORT_RECENT]));
        $this->assertSame(2, $flat->total, 'While the list behind "See all as a list" holds both matches.');
    }

    /**
     * The four shells a state chip can arrive on: who is looking, the page-cutting mode, and whether the chip narrows.
     *
     * @return array
     */
    public static function chip_shells_provider(): array {
        return [
            'a visitor, state sort on' => [true, true, false],
            'a visitor, state sort off' => [true, false, false],
            'logged in, state sort off' => [false, false, false],
            'logged in, state sort on (the control)' => [false, true, true],
        ];
    }

    /**
     * A state chip the page does not honour draws the page at rest.
     *
     * Only a logged-in page cut in PHP classifies its courses, and only its toolbar offers the state
     * group. A visitor's page and the SQL cut narrow nothing by a chip on the address, so the page
     * must not say that anything narrows it: no pill on any poster, no result mode, the row under
     * the filter line at rest, the count line at rest, no clear control, and no chip handed to the
     * client to send back.
     * Each case is compared with the same page without the chip; the logged-in page with the state
     * sort on is the control, where the same chip really narrows the page and every one of those
     * flips.
     *
     * @param bool $anonymous Whether a visitor is looking.
     * @param bool $statesort The page-cutting mode.
     * @param bool $honoured Whether the chip narrows this page.
     */
    #[DataProvider('chip_shells_provider')]
    public function test_a_state_chip_the_page_does_not_honour_draws_the_page_at_rest(
        bool $anonymous,
        bool $statesort,
        bool $honoured
    ): void {
        if ($anonymous) {
            $this->seed_public();
            $viewer = null;
        } else {
            $viewer = $this->seed();
        }
        set_config(population::SETTING_STATESORT, $statesort ? '1' : '0', 'theme_dimensions');
        $this->become($viewer);
        $rest = $this->export(['anonymous' => $anonymous]);
        self::forget();
        $chip = $this->export(['anonymous' => $anonymous, 'state' => classifier::STATE_ENROLLED]);
        $this->assertNotSame([], $chip->areas['posters'] ?? [], 'Precondition: the page has a poster to wear a pill.');

        $pills = array_column(array_column($chip->areas['posters'], 'results'), 'has');
        $drawn = [
            'count line' => [$chip->countnumber, $chip->countline, $chip->seeallurl],
            'pills' => $pills,
            'result mode' => $chip->areas['filtered'],
            'row under the line' => $chip->toolbar->hasfilters,
            'clear control' => $chip->hasfilters,
            'chip for the client' => json_decode($chip->vitrine)->chip,
        ];
        $atrest = [
            'count line' => [$rest->countnumber, $rest->countline, ''],
            'pills' => array_fill(0, count($pills), false),
            'result mode' => false,
            'row under the line' => false,
            'clear control' => false,
            'chip for the client' => null,
        ];
        if (!$honoured) {
            $this->assertSame($atrest, $drawn, 'A chip nothing on this page honours changes nothing on it.');
            return;
        }
        foreach ($drawn as $what => $value) {
            $this->assertNotSame($atrest[$what], $value, "Control: the chip narrows this page, so the {$what} says so.");
        }
    }

    /**
     * The query of a chip's address, as name => value.
     *
     * @param string $url The chip's address.
     * @return array
     */
    private static function chip_query(string $url): array {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        ksort($query);
        return $query;
    }

    /**
     * Each chip's address drops its one criterion and keeps every other part of the request.
     *
     * One chip per criterion the line does not show - the search (drawn by the stylesheet only where
     * the line's client folds the field away), the field, the period, a "More statuses" state, the
     * tree's category - and none for the scope or the favourites, which the line draws itself. Each
     * address is the request's own query less that one criterion: the other criteria, the
     * organisation and the page size stay, and so does the open panel, as "Clear all" keeps it. The
     * period's two bounds go together.
     *
     * Changes that must make it fail: a chip address dropping a second criterion or keeping its own;
     * the organisation or the open panel lost; a chip for a scope value; the period losing one bound.
     */
    public function test_each_chip_drops_its_one_criterion_and_keeps_everything_else(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $fieldid = (int) array_key_first(facets::fields());
        $options = [
            'sort' => criteria::SORT_NAME,
            'pagesize' => 2,
            'search' => 'Curso',
            'state' => classifier::STATE_FREE,
            'favourites' => true,
            'fields' => [$fieldid => landingfields::MODALITY_ONLINE],
            'datefrom' => '2020-01-01',
            'dateto' => '2030-12-31',
            'cat' => $this->ids['child'],
        ];
        $context = $this->export($options);
        $criteria = new criteria($this->ids['root'], $options);
        $chips = $context->toolbar->chips;
        $field = criteria::PARAM_FIELD_PREFIX . $fieldid;
        $this->assertSame(
            [criteria::PARAM_SEARCH, $field, category_toolbar::CHIP_PERIOD, criteria::PARAM_STATE, criteria::PARAM_CAT],
            array_column($chips, 'param'),
            'The search, the field, the period, the "More statuses" state and the tree\'s category, and nothing the line shows.'
        );
        $drops = [
            criteria::PARAM_SEARCH => [criteria::PARAM_SEARCH],
            $field => [$field],
            category_toolbar::CHIP_PERIOD => [criteria::PARAM_DATEFROM, criteria::PARAM_DATETO],
            criteria::PARAM_STATE => [criteria::PARAM_STATE],
            criteria::PARAM_CAT => [criteria::PARAM_CAT],
        ];
        $all = array_map('strval', $criteria->query_params());
        $this->assertArrayHasKey(criteria::PARAM_SORT, $all, 'Precondition: the request names its organisation.');
        $this->assertArrayHasKey(criteria::PARAM_FAVOURITES, $all, 'And the favourites, which no chip may drop.');
        foreach ($chips as $chip) {
            $expected = $all;
            foreach ($drops[$chip['param']] as $name) {
                unset($expected[$name]);
            }
            $expected['categoryid'] = (string) $this->ids['root'];
            ksort($expected);
            $this->assertSame($expected, self::chip_query($chip['url']), $chip['param'] . ' drops exactly its criterion.');
            $this->assertSame(get_string('category_removefilter', 'theme_dimensions'), $chip['removelabel']);
        }
        $labels = array_column($chips, 'label', 'param');
        $this->assertSame(get_string('category_chip_free', 'theme_dimensions'), $labels[criteria::PARAM_STATE]);
        $this->assertSame('Curso', $labels[criteria::PARAM_SEARCH], 'The term in force, plain.');
        $this->assertSame('Brigada', $labels[criteria::PARAM_CAT], 'The category as the tree names it.');
        $format = get_string('strftimedate', 'langconfig');
        $timezone = \core_date::get_server_timezone();
        $this->assertSame(
            get_string('category_daterangepill', 'theme_dimensions', (object) [
                'from' => userdate((int) criteria::day_start('2020-01-01'), $format, $timezone),
                'to' => userdate((int) criteria::day_start('2030-12-31'), $format, $timezone),
            ]),
            $labels[category_toolbar::CHIP_PERIOD],
            'The period in the site timezone, as criteria resolves it.'
        );
        /* The other criteria leave no course with that option, so the facet no longer offers it:
           the chip still names the option, as the field defines it, and never the field alone. */
        $definition = facets::option_labels(facets::fields()[$fieldid]);
        $this->assertSame($definition[landingfields::MODALITY_ONLINE], $labels[$field], 'The option\'s label.');
        self::forget();
        $offered = $this->export(['sort' => criteria::SORT_NAME, 'fields' => [$fieldid => landingfields::MODALITY_ONLINE]]);
        $facet = array_values(array_filter($offered->toolbar->facets, static fn(array $group): bool => $group['name'] === $field));
        $picked = array_values(array_filter($facet[0]['options'], static fn(array $option): bool => $option['checked']));
        $this->assertSame(
            $picked[0]['label'],
            $offered->toolbar->chips[0]['label'],
            'Where the facet offers it, the label its facet carries.'
        );
        $this->assertTrue($context->toolbar->clearall, 'Seven criteria: "Clear all" is offered.');

        // The open panel rides along on a grouped page, as it does on "Clear all".
        self::forget();
        $grouped = ['fields' => [$fieldid => landingfields::MODALITY_ONLINE], 'open' => $this->ids['child']];
        $panelcontext = $this->export($grouped);
        $this->assertSame(
            [$field],
            array_column($panelcontext->toolbar->chips, 'param'),
            'Precondition: one chip.'
        );
        $query = self::chip_query($panelcontext->toolbar->chips[0]['url']);
        $this->assertSame((string) $this->ids['child'], $query[criteria::PARAM_OPEN] ?? null, 'The open panel stays open.');
        $this->assertArrayNotHasKey($field, $query);
        $this->assertFalse($panelcontext->toolbar->clearall, 'One criterion: its chip undoes it, and "Clear all" waits.');

        // A scope value is on the line: no chip for it; the search's is its only chip.
        self::forget();
        $line = $this->export(['state' => classifier::STATE_ENROLLED, 'search' => 'Curso']);
        $this->assertSame([criteria::PARAM_SEARCH], array_column($line->toolbar->chips, 'param'));
        $this->assertTrue($line->toolbar->hasfilters, 'The row under the line still shows, with the count sentence.');
    }

    /**
     * The chips are one builder's: the page and both web services hand back the same ones for one request.
     *
     * The client paints the chips of an answer and composes none, so a chip drawn on the page and
     * the same chip after a fetch must be the same label and the same address. A visitor's page
     * honours no state chip and has no favourites, so neither ever becomes a chip there, whatever
     * the request says.
     *
     * Changes that must make it fail: the web service building its chips apart from the toolbar; a
     * visitor handed a state chip.
     */
    public function test_the_chips_are_the_same_from_the_page_and_both_services(): void {
        global $PAGE;
        $viewer = $this->seed();
        $this->become($viewer);
        $fieldid = (int) array_key_first(facets::fields());
        $category = core_course_category::get($this->ids['root'], MUST_EXIST, true);
        $page = $this->export([
            'sort' => criteria::SORT_NAME,
            'search' => 'Curso',
            'state' => classifier::STATE_FREE,
            'fields' => [$fieldid => landingfields::MODALITY_ONLINE],
            'datefrom' => '2020-01-01',
            'cat' => $this->ids['child'],
        ]);
        $this->assertCount(5, $page->toolbar->chips, 'Precondition: a chip of every kind.');
        self::forget();
        // A request of its own, as the web service is: the page's context was set by the export above.
        $PAGE = new \moodle_page();
        $answer = \theme_dimensions\local\category\rows::fetch($category, [
            'sort' => criteria::SORT_NAME,
            'query' => 'Curso',
            'chip' => classifier::STATE_FREE,
            'filters' => [$fieldid => landingfields::MODALITY_ONLINE],
            'datefrom' => '2020-01-01',
            'cat' => $this->ids['child'],
        ]);
        $strip = static fn(array $chips): array => array_map(
            static fn(array $chip): array => array_diff_key($chip, ['isperiod' => true]),
            $chips
        );
        $this->assertSame($strip($page->toolbar->chips), $answer['chips'], 'The logged-in service answers the page\'s chips.');
        $this->assertSame($page->toolbar->clearall, $answer['clearall']);

        // The visitor's shell: the page and the visitor's service, and never a state chip.
        $this->seed_public();
        $this->become(null);
        $public = core_course_category::get($this->ids['root'], MUST_EXIST, true);
        $options = [
            'anonymous' => true,
            'sort' => criteria::SORT_NAME,
            'search' => 'Curso',
            'state' => classifier::STATE_PENDING,
            'favourites' => true,
            'datefrom' => '2020-01-01',
            'cat' => $this->ids['child'],
        ];
        $visitor = $this->export($options);
        $this->assertSame(
            [criteria::PARAM_SEARCH, category_toolbar::CHIP_PERIOD, criteria::PARAM_CAT],
            array_column($visitor->toolbar->chips, 'param'),
            'The search, the period and the tree, and no state chip for a visitor.'
        );
        $this->assertNull($visitor->toolbar->favourites, 'And no star.');
        self::forget();
        $PAGE = new \moodle_page();
        $answer = \theme_dimensions\local\category\rows::fetch($public, [
            'anonymous' => true,
            'sort' => criteria::SORT_NAME,
            'query' => 'Curso',
            'chip' => classifier::STATE_PENDING,
            'datefrom' => '2020-01-01',
            'cat' => $this->ids['child'],
        ]);
        $this->assertSame($strip($visitor->toolbar->chips), $answer['chips'], 'The visitor\'s service answers the same chips.');
        $this->assertNotContains(criteria::PARAM_STATE, array_column($answer['chips'], 'param'));
    }

    /**
     * A search in force is one chip, built by the toolbar's one builder, whose address drops the term alone.
     *
     * The line's client folds the field into an icon on a narrow line, and a term left in force must
     * still say so: the server draws it as a chip under the line (the stylesheet shows it only while
     * the field is folded away), labelled with the term, linking to the listing without it and with
     * everything else kept. A term under the search's floor is no criterion and gets no chip; no term,
     * no chip.
     *
     * Changes that must make it fail: no chip for the term; its address dropping a second criterion or
     * keeping the term; a chip for a term too short to search.
     */
    public function test_a_search_in_force_is_one_chip_that_drops_the_term_alone(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $fieldid = (int) array_key_first(facets::fields());
        $options = [
            'sort' => criteria::SORT_NAME,
            'search' => 'Curso',
            'fields' => [$fieldid => landingfields::MODALITY_ONLINE],
        ];
        $chips = $this->export($options)->toolbar->chips;
        $this->assertSame(criteria::PARAM_SEARCH, $chips[0]['param'], 'The search\'s chip comes first.');
        $this->assertSame('Curso', $chips[0]['label']);
        $expected = array_map('strval', (new criteria($this->ids['root'], $options))->query_params());
        $this->assertSame('Curso', $expected[criteria::PARAM_SEARCH] ?? null, 'Precondition: the request carries the term.');
        unset($expected[criteria::PARAM_SEARCH]);
        $expected['categoryid'] = (string) $this->ids['root'];
        ksort($expected);
        $this->assertSame($expected, self::chip_query($chips[0]['url']), 'The term alone is dropped, the rest kept.');
        $this->assertSame(1, count(array_keys(array_column($chips, 'param'), criteria::PARAM_SEARCH)), 'One chip.');

        self::forget();
        $short = $this->export(['sort' => criteria::SORT_NAME, 'search' => 'C']);
        $this->assertNotContains(criteria::PARAM_SEARCH, array_column($short->toolbar->chips, 'param'), 'Too short to search.');
        self::forget();
        $none = $this->export(['sort' => criteria::SORT_NAME]);
        $this->assertSame([], $none->toolbar->chips, 'Nothing narrows: no chip at all.');
    }

    /**
     * Every cell of the scope is in the toolbar's context, a cell with nothing to offer marked absent.
     *
     * The filter line's client measures the line with every cell of the scope drawn, so the template
     * writes them all, an absent one hidden. The web services' status group is untouched: a client
     * repaints a cell from an answer that offers it and hides one an answer leaves out.
     *
     * Changes that must make it fail: an absent cell left out of the context; one drawn without the
     * mark; the absent cells reaching the status group the services hand back.
     */
    public function test_every_scope_cell_is_drawn_and_a_cell_with_nothing_to_offer_is_absent(): void {
        $viewer = $this->seed();
        $this->become($viewer);
        $toolbar = $this->export(['sort' => criteria::SORT_NAME])->toolbar;
        $cells = $toolbar->scope['options'];
        $this->assertSame(criteria::SCOPE, array_map(static fn(array $cell): string => (string) $cell['value'], $cells));
        $absent = array_values(array_filter($cells, static fn(array $cell): bool => $cell['absent']));
        $this->assertSame([classifier::STATE_SCHEDULED], array_column($absent, 'value'), 'The fixture holds nothing scheduled.');
        $this->assertSame(0, $absent[0]['count']);
        $this->assertFalse($absent[0]['checked']);
        $offered = array_map(static fn(array $option): string => (string) $option['value'], $toolbar->statusgroup['options']);
        $this->assertNotContains(classifier::STATE_SCHEDULED, $offered, 'The services\' status group offers no absent cell.');
        foreach ($cells as $cell) {
            if (!$cell['absent']) {
                $this->assertContains((string) $cell['value'], $offered, 'Every cell drawn is one the group offers.');
            }
        }
    }

    /**
     * The covers of the first row of the first page load at once, and every other cover lazily.
     *
     * A lazy image is not even requested before layout, and the first row is the top of what the reader sees: one of
     * its covers is the page's largest paint when the category has no hero image. Below it, a later page, a grouped
     * page whose posters open the listing and a panel keep the lazy load, which is what spares the page the covers
     * nobody scrolls to. The posters that open a grouped page are its first row instead.
     */
    public function test_the_first_row_of_the_first_page_loads_its_covers_at_once(): void {
        $this->become($this->seed());

        $flat = array_values($this->export(['sort' => criteria::SORT_NAME], category_toolbar::VIEW_CARDS, true)->cards);
        $this->assertGreaterThan(category_listing::FIRST_ROW, count($flat), 'Precondition: more cards than one row.');
        $this->assertSame(
            array_merge(
                array_fill(0, category_listing::FIRST_ROW, true),
                array_fill(0, count($flat) - category_listing::FIRST_ROW, false)
            ),
            array_map(static fn(\stdClass $card): bool => $card->eager, $flat),
            'The first row of a flat listing, and no card past it.'
        );

        $options = ['sort' => criteria::SORT_NAME, 'pagesize' => 2, 'page' => 1];
        $later = $this->export($options, category_toolbar::VIEW_CARDS, true)->cards;
        $this->assertNotEmpty($later, 'Precondition: the second page has cards.');
        $this->assertSame([false], array_unique(array_map(static fn(\stdClass $card): bool => $card->eager, $later)));

        $grouped = $this->export([], category_toolbar::VIEW_CARDS, true);
        $this->assertTrue($grouped->hasareas, 'Precondition: the grouped page opens on its posters.');
        foreach ($grouped->cards as $card) {
            $this->assertFalse($card->eager, 'Under the posters, the direct courses are not the first row.');
        }
        $this->assertTrue($grouped->areas['posters'][0]['eager'], 'The first poster is the first row of a grouped page.');

        // The same flat request outside a first paint - what the web service answers - marks nothing.
        $answer = $this->export(['sort' => criteria::SORT_NAME]);
        $this->assertSame([false], array_unique(array_map(static fn(\stdClass $card): bool => $card->eager, $answer->cards)));
    }

    /**
     * The page's two entries build the listing as its first paint, and the web service never does.
     *
     * Nothing renders differently enough for a test of the page to notice a lost flag - a lazy first row still
     * loads - so the three places a listing is built are read for it.
     */
    public function test_the_page_entries_and_only_they_ask_for_the_first_paint(): void {
        $root = dirname(__DIR__, 2);
        $this->assertStringContainsString(
            '$listing = new category_listing($coursecat, $criteria, $result, $view, $manageactions, $pages, true);',
            (string) file_get_contents($root . '/classes/output/core/course_renderer.php')
        );
        $this->assertStringContainsString(
            "\$listing = new category_listing(\$category, \$criteria, \$result, \$view, '', "
                . "category_pages::export(\$categoryid), true);",
            (string) file_get_contents($root . '/classes/local/category/request.php')
        );
        $this->assertStringContainsString(
            '$listing = new category_listing($category, $criteria, $result, category_toolbar::VIEW_CARDS);',
            (string) file_get_contents($root . '/classes/local/category/rows.php'),
            'The web service answers a filter, a panel or a later page: never a first paint.'
        );
    }

    /**
     * The first cover of the first paint is fetched ahead of every other image, and only where the hero paints none.
     *
     * Of the first row's eager cards, the first with a cover to fetch gets the priority and no other card does: the first
     * card in order has no image of its own here (its drawn cover is a data URI), so a rule that marked the first card,
     * or every eager one, is seen. A hero that
     * paints a picture is the page's largest paint and already asks first, so with one armed for this category no
     * card asks; a hero armed for another category is the control that the hero's own picture is what is read. A
     * web service answer, which is never a first paint, marks nothing.
     */
    public function test_the_first_cover_is_fetched_first_only_where_the_hero_paints_no_picture(): void {
        global $CFG;

        $this->become($this->seed());
        $CFG->courseoverviewfileslimit = 1;
        $CFG->courseoverviewfilesext = '.jpg,.gif,.png';
        $order = array_map(static fn(\stdClass $card): int => (int) $card->id, array_values(
            $this->export(['sort' => criteria::SORT_NAME], category_toolbar::VIEW_CARDS, true)->cards
        ));
        $this->assertGreaterThan(category_listing::FIRST_ROW, count($order), 'Precondition: more cards than one row.');
        foreach (array_slice($order, 1) as $courseid) {
            get_file_storage()->create_file_from_string([
                'contextid' => \core\context\course::instance($courseid)->id,
                'component' => 'course',
                'filearea' => 'overviewfiles',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'capa.png',
            ], base64_decode(self::PNG));
        }
        \cache::make('core', 'course_image')->purge();
        $priority = fn(\stdClass $context): array => array_map(
            static fn(\stdClass $card): bool => $card->priority,
            array_values($context->cards)
        );

        $flat = $this->export(['sort' => criteria::SORT_NAME], category_toolbar::VIEW_CARDS, true);
        $this->assertStringStartsWith(
            'data:',
            array_values($flat->cards)[0]->imageurl,
            'Precondition: the first card has no cover of its own, only the drawn one, which nothing fetches.'
        );
        $expected = array_fill(0, count($order), false);
        $expected[1] = true;
        $this->assertSame($expected, $priority($flat), 'The first cover of the first row, and nothing else.');

        $answer = $this->export(['sort' => criteria::SORT_NAME]);
        $this->assertSame([false], array_unique($priority($answer)), 'An answer is never a first paint.');

        // A hero armed for another category paints its picture elsewhere: this page's first cover still asks.
        $elsewhere = (int) self::getDataGenerator()->create_category()->id;
        $this->hero_with_picture($elsewhere);
        $this->assertSame(
            $expected,
            $priority($this->export(['sort' => criteria::SORT_NAME], category_toolbar::VIEW_CARDS, true))
        );

        $this->hero_with_picture($this->ids['root']);
        $this->assertTrue(category_hero::current()->draws_image(), 'Precondition: the hero paints a picture.');
        $this->assertSame(
            [false],
            array_unique($priority($this->export(['sort' => criteria::SORT_NAME], category_toolbar::VIEW_CARDS, true))),
            'Under a hero picture, no cover asks first.'
        );
    }

    /**
     * Arm a hero for a category whose branding paints an image of its own.
     *
     * @param int $categoryid The category.
     * @return void
     */
    private function hero_with_picture(int $categoryid): void {
        branding::save($categoryid, (object) [
            'layout' => branding::LAYOUT_IMAGE,
            'bgcolour' => '#12484f',
            'inkcolour' => '#ffffff',
            'overlay' => 78,
        ]);
        $context = \core\context\coursecat::instance($categoryid);
        if (!get_file_storage()->file_exists($context->id, branding::COMPONENT, branding::AREA_HERO, 0, '/', 'hero.png')) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id,
                'component' => branding::COMPONENT,
                'filearea' => branding::AREA_HERO,
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'hero.png',
            ], base64_decode(self::PNG));
        }
        branding::forget_all();
        $category = core_course_category::get($categoryid, MUST_EXIST, true);
        $criteria = new criteria($categoryid);
        category_hero::arm(new category_hero($category, population::resolve($criteria), $criteria));
    }

    /**
     * The labels every card spells alike are read once per language, and a language switched mid-request is read anew.
     */
    public function test_the_cards_labels_follow_the_language_in_force(): void {
        global $CFG;

        $this->become($this->seed());
        $english = array_values($this->export(['sort' => criteria::SORT_NAME])->cards)[0];
        $this->assertSame(get_string('category_details', 'theme_dimensions'), $english->detailslabel);

        $folder = $CFG->dataroot . '/lang/xx';
        check_dir_exists($folder);
        file_put_contents(
            $folder . '/langconfig.php',
            "<?php\n\$string['thislanguage'] = 'Xx';\n\$string['parentlanguage'] = '';\n"
        );
        file_put_contents($folder . '/theme_dimensions.php', "<?php\n\$string['category_details'] = 'XX detalhes';\n");
        get_string_manager()->reset_caches();
        $this->assertTrue(get_string_manager()->translation_exists('xx', false), 'Precondition: the pack is installed.');

        $previous = force_current_language('xx');
        try {
            $spoken = array_values($this->export(['sort' => criteria::SORT_NAME])->cards)[0];
        } finally {
            force_current_language($previous);
        }
        $this->assertSame('XX detalhes', $spoken->detailslabel, 'The language in force, not the first one read.');
        $again = array_values($this->export(['sort' => criteria::SORT_NAME])->cards)[0];
        $this->assertSame($english->detailslabel, $again->detailslabel, 'And back.');
    }
}
