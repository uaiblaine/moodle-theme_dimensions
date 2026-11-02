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
 * Theme Dimensions - Tests for the course dialogue's own keys on the category card
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
use theme_dimensions\local\category\cards;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\dialogue;
use theme_dimensions\local\category\population;
use theme_dimensions\local\landing\links;
use theme_dimensions\local\landing\redirector;
use theme_dimensions\local\landingfields;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * What "More information" offers, reader by reader and state by state.
 *
 * The secondary button is the card's own link and nothing else, so each of its destinations is asked
 * of a reader the card's rule sends there: staff who may walk in to the course, a learner to the
 * landing page of a course that chose one, else to the standard enrolment page, a visitor to nothing when
 * there is no landing page. The primary button is the state's word over the same rule; every assertion of
 * one state has a neighbour of another state in the same fixture, and the collapse of two buttons
 * into one is asserted beside a pair that stays two.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_card::class)]
#[CoversClass(dialogue::class)]
final class category_card_modal_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The plugin's component. */
    private const COMPONENT = 'theme_dimensions';

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /** @var int When the dated course's window opens. */
    private int $opens = 0;

    /**
     * Seed one category holding one course per case, and return the learner, who is enrolled in one of them.
     *
     * @return \stdClass The learner.
     */
    private function seed(): \stdClass {
        $this->resetAfterTest();
        $this->setAdminUser();
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        $this->enable_enrol('self');
        $this->enable_enrol('guest');

        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Brigada & Resgate']);
        $learner = $generator->create_user();
        $handler = course_handler::create();
        $make = function (string $name, int $model, array $course = []) use ($generator, $handler, $root): int {
            $record = $generator->create_course(['category' => $root->id, 'fullname' => $name] + $course);
            if ($model > 0) {
                $handler->instance_form_save((object) [
                    'id' => $record->id,
                    'customfield_' . landingfields::SHORTNAME_MODEL => $model,
                ]);
            }
            return (int) $record->id;
        };

        $this->ids['enrolled'] = $make('Curso Inscrito', landingfields::MODEL_COURSE);
        $generator->enrol_user($learner->id, $this->ids['enrolled']);
        $this->ids['free'] = $make('Curso Livre', landingfields::MODEL_COURSE);
        $this->enable_instance($this->ids['free'], 'guest');
        $this->ids['open'] = $make('Curso Aberto', landingfields::MODEL_COURSE);
        $this->enable_instance($this->ids['open'], 'self');
        $this->ids['openplain'] = $make('Curso Aberto Sem Landing page', 0);
        $this->enable_instance($this->ids['openplain'], 'self');
        $this->ids['none'] = $make('Curso Fechado', landingfields::MODEL_COURSE);
        $this->ids['noneplain'] = $make('Curso Fechado Sem Landing page', 0);
        $this->ids['event'] = $make('Evento Futuro', landingfields::MODEL_EVENT, [
            'startdate' => time() + 5 * DAYSECS,
            'enddate' => time() + 6 * DAYSECS,
        ]);
        $this->ids['held'] = $make('Evento Passado', landingfields::MODEL_EVENT, [
            'startdate' => time() - 3 * DAYSECS,
            'enddate' => time() - 2 * DAYSECS,
        ]);
        $this->enable_instance($this->ids['held'], 'self');
        // A window that opens later, the one refusal: local_unlistedcourses' batch dates it.
        $this->opens = time() + 3 * DAYSECS;
        $this->ids['ahead'] = $make('Curso Abre Depois', landingfields::MODEL_COURSE);
        $this->enable_instance($this->ids['ahead'], 'self', ['enrolstartdate' => $this->opens, 'customint6' => 1]);
        $this->ids['root'] = (int) $root->id;
        return $learner;
    }

    /**
     * The cards of the category for the current viewer, keyed by course id, through the listing itself.
     *
     * @return \stdClass[]
     */
    private function cards(): array {
        global $PAGE;
        $PAGE->set_url('/course/index.php', ['categoryid' => $this->ids['root']]);
        $criteria = new criteria($this->ids['root'], ['sort' => criteria::SORT_NAME]);
        $result = population::resolve($criteria);
        $category = core_course_category::get($this->ids['root'], MUST_EXIST, true);
        $listing = new category_listing($category, $criteria, $result, category_toolbar::VIEW_CARDS);
        $cards = [];
        foreach ($listing->export_for_template($PAGE->get_renderer('core'))->cards as $card) {
            $cards[(int) $card->id] = $card;
        }
        return $cards;
    }

    /**
     * One card built straight from the page's own row, for a state or a shell the fixture's viewer does not have.
     *
     * The row is read for the current viewer; a visitor's card is then exported with nobody logged in, the
     * anonymous shell's own request, whose population would refuse a category that is not public.
     *
     * @param int $id The course.
     * @param string|null $state The state to give the row, or null to keep the classifier's.
     * @param bool $anonymous Whether the anonymous shell draws it.
     * @return \stdClass The card context.
     */
    private function card(int $id, ?string $state = null, bool $anonymous = false): \stdClass {
        global $PAGE;
        $PAGE->set_url('/course/index.php', ['categoryid' => $this->ids['root']]);
        $result = population::resolve(new criteria($this->ids['root'], ['sort' => criteria::SORT_NAME]));
        $this->assertArrayHasKey($id, $result->rows, 'Precondition: the course is on the page.');
        $row = clone $result->rows[$id];
        if ($state !== null) {
            $row->state = $state;
        }
        $courses = cards::records([$id]);
        $values = cards::values([$id => $result->rows[$id]]);
        if ($anonymous) {
            $this->become(null);
        }
        $card = new category_card($courses[$id], $row, $values[$id], null, null, !$anonymous, $anonymous);
        return $card->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * A trail's card is tagged as a trail, beside an event and a course that keep their own tags.
     */
    public function test_a_trail_card_names_its_kind(): void {
        $this->seed();
        $trail = self::getDataGenerator()->create_course(['category' => $this->ids['root'], 'fullname' => 'Trilha Aberta']);
        course_handler::create()->instance_form_save((object) [
            'id' => $trail->id,
            'customfield_' . landingfields::SHORTNAME_MODEL => landingfields::MODEL_TRAIL,
        ]);

        $cards = $this->cards();

        $this->assertSame(get_string('landingfield_model_trail', self::COMPONENT), $cards[(int) $trail->id]->kindlabel);
        $this->assertSame(get_string('landingfield_model_event', self::COMPONENT), $cards[$this->ids['event']]->kindlabel);
        $this->assertSame(get_string('landingfield_model_course', self::COMPONENT), $cards[$this->ids['open']]->kindlabel);
        $this->assertFalse($cards[$this->ids['openplain']]->haskind, 'A course with no model has no tag.');
    }

    /**
     * The dialogue's sheet as categorycoursemodal renders it from a card: what the card holds, and no content.
     *
     * The module reads the kicker and the buttons off the attributes the card writes from its modal keys, and the
     * title and the pill off the card's markup; this is the same context, built from the card's own keys.
     *
     * @param \stdClass $card The card context.
     * @return string The rendered sheet and actions.
     */
    private function sheet(\stdClass $card): string {
        global $PAGE;
        $modal = json_decode(json_encode($card->modal), true);
        return $PAGE->get_renderer('core')->render_from_template('theme_dimensions/category_card_modal', [
            'fullname' => $card->fullname,
            'hasimage' => false,
            'badge' => json_decode(json_encode($card->badge), true),
            'kicker' => $modal['kicker'],
            'haskicker' => $modal['haskicker'],
            'primary' => $modal['primary'],
            'hasprimary' => $modal['hasprimary'],
            'secondary' => $modal['secondary'],
            'hassecondary' => $modal['hassecondary'],
            'loading' => false,
            'failed' => false,
        ]);
    }

    /**
     * The address of a course page, as the dialogue spells it.
     *
     * @param int $id The course.
     * @return string
     */
    private static function course_url(int $id): string {
        return (new \moodle_url('/course/view.php', ['id' => $id]))->out(false);
    }

    /**
     * The address of the standard enrolment page, as the dialogue spells it.
     *
     * @param int $id The course.
     * @return string
     */
    private static function enrol_url(int $id): string {
        return (new \moodle_url('/enrol/index.php', ['id' => $id]))->out(false);
    }

    /**
     * The secondary button goes where the card's own link goes, for each kind of reader the card's rule knows.
     *
     * Staff who may walk in are sent to the course; a learner to the landing page of a course that chose a model and to
     * the standard enrolment page of one that did not; a visitor to the landing page, and to nothing at all where the
     * course has none, whose card links nowhere either. The label names the course, or the event.
     */
    public function test_the_secondary_button_is_the_cards_own_link_for_every_reader(): void {
        $learner = $this->seed();

        // Staff: a manager of the category, enrolled nowhere. The course is their door.
        $manager = $this->user_with('moodle/course:view', \core\context\coursecat::instance($this->ids['root']));
        $this->become($manager);
        $staff = $this->cards()[$this->ids['open']];
        $this->assertSame(classifier::STATE_OPEN, $staff->state, 'Precondition: not enrolled, a method open.');
        $this->assertSame(self::course_url($this->ids['open']), $staff->url, 'Precondition: the card links the course.');
        $this->assertSame(self::course_url($this->ids['open']), $staff->modal['secondary']['url']);
        $this->assertSame(get_string('category_modal_coursepage', self::COMPONENT), $staff->modal['secondary']['label']);

        $this->become($learner);
        $cards = $this->cards();
        $landing = $cards[$this->ids['none']];
        $this->assertSame(classifier::STATE_NONE, $landing->state, 'Precondition: nothing on offer.');
        $this->assertSame(links::page($this->ids['none'])->out(false), $landing->modal['secondary']['url']);
        $this->assertTrue($landing->modal['hassecondary']);
        $plain = $cards[$this->ids['noneplain']];
        $this->assertSame(self::enrol_url($this->ids['noneplain']), $plain->modal['secondary']['url']);
        $this->assertSame($plain->url, $plain->modal['secondary']['url'], 'Exactly the card\'s own link.');

        // An event says so.
        $event = $cards[$this->ids['event']];
        $this->assertSame(get_string('category_modal_eventpage', self::COMPONENT), $event->modal['secondary']['label']);
        $this->assertSame(links::page($this->ids['event'])->out(false), $event->modal['secondary']['url']);
        $this->assertSame(get_string('category_modal_coursepage', self::COMPONENT), $landing->modal['secondary']['label']);

        // A visitor: the landing page where there is one, and no button at all where there is none.
        $visitor = $this->card($this->ids['none'], null, true);
        $this->assertSame(links::page($this->ids['none'])->out(false), $visitor->modal['secondary']['url']);
        $this->become($learner);
        $nowhere = $this->card($this->ids['noneplain'], null, true);
        $this->assertNull($nowhere->url, 'Precondition: the visitor\'s card links nowhere.');
        $this->assertNull($nowhere->modal['secondary']);
        $this->assertFalse($nowhere->modal['hassecondary']);
        $this->assertNull($nowhere->modal['primary'], 'And nothing to press at all.');
    }

    /**
     * The primary button says the state, and goes where the card's rule sends that state.
     *
     * Enrolled and free walk into the course, which is the secondary's page too, so the dialogue draws the
     * primary alone; "Enrol" goes to the landing page, or to the standard page of a course with no landing page, and is
     * likewise alone for a learner; staff who may walk in get "Enrol" AND the course page, the pair that stays two.
     */
    public function test_the_primary_button_follows_the_state_and_one_page_is_one_button(): void {
        $learner = $this->seed();
        $this->become($learner);
        $cards = $this->cards();

        $cases = [
            'enrolled' => [classifier::STATE_ENROLLED, 'category_modal_continue', self::course_url($this->ids['enrolled'])],
            'free' => [classifier::STATE_FREE, 'category_modal_free', self::course_url($this->ids['free'])],
            'open' => [classifier::STATE_OPEN, 'category_modal_enrol', links::page($this->ids['open'])->out(false)],
            'openplain' => [classifier::STATE_OPEN, 'category_modal_enrol', self::enrol_url($this->ids['openplain'])],
        ];
        foreach ($cases as $role => [$state, $key, $url]) {
            $modal = $cards[$this->ids[$role]]->modal;
            $this->assertSame($state, $cards[$this->ids[$role]]->state, "Precondition: the {$role} card.");
            $this->assertSame(get_string($key, self::COMPONENT), $modal['primary']['label'], "The {$role} card's word.");
            $this->assertSame($url, $modal['primary']['url'], "The {$role} card's destination.");
            $this->assertFalse($modal['primary']['disabled']);
            $this->assertSame($url, $cards[$this->ids[$role]]->url, "Precondition: the {$role} card links the same page.");
            $this->assertNull($modal['secondary'], "The {$role} card's two buttons open one page: one button.");
            $this->assertFalse($modal['hassecondary']);
        }

        // The pair that stays two: staff, for whom "Enrol" is the enrolment surface and the course their door.
        $this->become($this->user_with('moodle/course:view', \core\context\coursecat::instance($this->ids['root'])));
        $staff = $this->cards()[$this->ids['open']]->modal;
        $this->assertSame(get_string('category_modal_enrol', self::COMPONENT), $staff['primary']['label']);
        $this->assertSame(links::page($this->ids['open'])->out(false), $staff['primary']['url']);
        $this->assertSame(self::course_url($this->ids['open']), $staff['secondary']['url']);
        $this->assertTrue($staff['hasprimary'] && $staff['hassecondary']);
    }

    /**
     * Every other state keeps the card's own call to action as its primary button, and nothing on offer draws none.
     */
    public function test_the_other_states_keep_the_cards_call_to_action(): void {
        $learner = $this->seed();
        $this->become($learner);

        $key = $this->card($this->ids['none'], classifier::STATE_KEY);
        $this->assertSame($key->cta['label'], $key->modal['primary']['label']);
        $this->assertSame(get_string('category_cta_key', self::COMPONENT), $key->modal['primary']['label']);
        $this->assertSame(redirector::standard_page($this->ids['none'])->out(false), $key->modal['primary']['url']);
        $this->assertSame(links::page($this->ids['none'])->out(false), $key->modal['secondary']['url'], 'Two pages, two buttons.');

        $pending = $this->card($this->ids['none'], classifier::STATE_PENDING);
        $this->assertSame(get_string('category_cta_status', self::COMPONENT), $pending->modal['primary']['label']);

        $none = $this->card($this->ids['none'], classifier::STATE_NONE);
        $this->assertNull($none->cta, 'Precondition: the card offers nothing.');
        $this->assertNull($none->modal['primary'], 'A state that enrols nobody draws no primary button.');
        $this->assertFalse($none->modal['hasprimary']);
        $this->assertTrue($none->modal['hassecondary'], 'The page of the course is still offered.');
    }

    /**
     * An event already held and a visitor's card draw no primary button; the open event beside them does.
     */
    public function test_a_held_event_and_a_visitor_draw_no_primary_button(): void {
        $learner = $this->seed();
        $this->become($learner);
        $cards = $this->cards();

        $held = $cards[$this->ids['held']];
        $this->assertSame(classifier::STATE_OPEN, $held->state, 'Precondition: its instance is still open to the classifier.');
        $this->assertNull($held->modal['primary'], 'An event already held enrols nobody.');
        $this->assertSame(links::page($this->ids['held'])->out(false), $held->modal['secondary']['url']);
        $this->assertSame(get_string('category_modal_eventpage', self::COMPONENT), $held->modal['secondary']['label']);
        // The control: the same state on a course that is no held event.
        $this->assertNotNull($cards[$this->ids['open']]->modal['primary']);

        $visitor = $this->card($this->ids['open'], null, true);
        $this->assertNotNull($visitor->cta, 'Precondition: the visitor\'s card offers its landing page.');
        $this->assertNull($visitor->modal['primary'], 'A visitor\'s card knows no state to put a word to.');
        $this->assertSame(links::page($this->ids['open'])->out(false), $visitor->modal['secondary']['url']);
    }

    /**
     * A window that opens later is a disabled button carrying the date, which the dialogue prints nowhere else.
     *
     * The date is the plugin's own, through the card's opening rule, in day/month/full year; the card itself
     * still prints its own line under the pill, and that line is the one thing the dialogue must not repeat.
     */
    public function test_a_later_opening_is_a_disabled_button_and_its_date_is_printed_once(): void {
        global $PAGE;

        $learner = $this->seed();
        $this->become($learner);
        $card = $this->cards()[$this->ids['ahead']];
        $this->assertSame(classifier::STATE_NONE, $card->state, 'Precondition: nothing on offer yet.');
        $this->assertTrue($card->hasopens, 'Precondition: the card dates the window.');

        $date = userdate($this->opens, '%d/%m/%Y', 99, false);
        $this->assertMatchesRegularExpression('~^\d{2}/\d{2}/\d{4}$~', $date);
        $primary = $card->modal['primary'];
        $this->assertTrue($primary['disabled']);
        $this->assertNull($primary['url']);
        $this->assertSame(get_string('category_modal_opens', self::COMPONENT, $date), $primary['label']);
        $this->assertSame(get_string('category_modal_opensnote', self::COMPONENT), $primary['note']);
        $this->assertSame(links::page($this->ids['ahead'])->out(false), $card->modal['secondary']['url'], 'The page stays.');

        // The card carries the closed button onto its link: its label and what a screen reader is told, and no address.
        $cardhtml = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/category_card', $card);
        $this->assertMatchesRegularExpression('~<a [^>]*data-region="dim-card-more"[^>]*>~', $cardhtml);
        preg_match('~<a [^>]*data-region="dim-card-more"[^>]*>~', $cardhtml, $link);
        $this->assertStringContainsString('data-primary="' . s($primary['label']) . '"', $link[0]);
        $this->assertStringContainsString('data-primary-closed="' . s($primary['note']) . '"', $link[0]);
        $this->assertStringNotContainsString('data-primary-url=', $link[0], 'A closed button goes nowhere.');

        $html = $this->sheet($card);
        $this->assertSame(1, substr_count($html, $date), 'The date is on the button and nowhere else.');
        $this->assertStringNotContainsString(s($card->openslabel), $html, 'Nor is the card\'s own opening line.');
        $this->assertMatchesRegularExpression(
            '~<button type="button" class="[^"]*dim-cmodal__button--closed[^"]*" aria-disabled="true"[^>]*>~',
            $html
        );
        $this->assertStringNotContainsString(' disabled', preg_replace('~aria-disabled~', '', $html), 'Never disabled.');
        $this->assertStringContainsString(
            '<span class="visually-hidden"> ' . s(get_string('category_modal_opensnote', self::COMPONENT)) . '</span>',
            $html,
            'A screen reader hears why it does nothing.'
        );

        // The control: an open course draws a link, not a closed button, and its card writes the address.
        $open = $this->cards()[$this->ids['open']];
        $this->assertFalse($open->modal['primary']['disabled']);
        $openhtml = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/category_card', $open);
        $this->assertStringContainsString('data-primary-url="' . s($open->modal['primary']['url']) . '"', $openhtml);
        $this->assertStringNotContainsString('data-primary-closed=', $openhtml);
    }

    /**
     * The kicker is the course's own category on every page, the listed one included; the tiles carry icons core has.
     *
     * The tiles are the dialogue's fetched content ({@see dialogue::content()}), the kicker the card's.
     */
    public function test_the_kicker_names_the_courses_category_and_every_fact_has_an_icon(): void {
        global $CFG, $DB;

        $learner = $this->seed();
        course_handler::create()->instance_form_save((object) [
            'id' => $this->ids['open'],
            'customfield_' . landingfields::SHORTNAME_MODALITY => landingfields::MODALITY_INPERSON,
            'customfield_' . landingfields::SHORTNAME_CITY => 'São Paulo',
            'customfield_' . landingfields::SHORTNAME_WORKLOAD => 12,
        ]);
        $this->become($learner);
        $cards = $this->cards();
        $open = $cards[$this->ids['open']];
        $this->assertFalse($open->hasoverline, 'Precondition: on its own category\'s page the card names no category.');
        $this->assertSame('Brigada & Resgate', $open->modal['kicker'], 'The dialogue does, in the plain spelling.');
        $this->assertTrue($open->modal['haskicker']);

        $map = file_get_contents($CFG->root . '/lib/bundles/fontawesome/scss/_variables.scss');
        $content = dialogue::content($DB->get_record('course', ['id' => $this->ids['open']], dialogue::COURSE_FIELDS));
        $this->assertGreaterThanOrEqual(3, count($content['facts']), 'Precondition: workload, mode and place.');
        foreach ($content['facts'] as $fact) {
            $this->assertMatchesRegularExpression('~^fa-[a-z0-9-]+$~', $fact['icon']);
            $this->assertMatchesRegularExpression(
                '~^\s*"' . preg_quote(substr($fact['icon'], 3), '~') . '":\s~m',
                $map,
                "Core's Font Awesome has no icon named {$fact['icon']}."
            );
        }
    }
}
