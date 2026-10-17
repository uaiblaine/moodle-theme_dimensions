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
 * Theme Dimensions - Tests for the facts a card carries for the filter line's preview
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use core_course\customfield\course_handler;
use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use local_unlistedcourses\discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\external\get_category_rows;
use theme_dimensions\local\landingfields;
use theme_dimensions\output\category_card;
use theme_dimensions\output\category_listing;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The facts the filter line's preview reads off a card (contract section 6), on both shells.
 *
 * A card carries the state chip the course counts under, each filter field's value and the start day in the
 * site's timezone, and its star says whether the course is a favourite; the preview decides from those alone.
 * What a card states is what its reader may already see on it: a visitor's card carries no chip and no star,
 * no field a visitor cannot see, and a start day only where it prints one. Each refusal has its control beside
 * it - the logged-in card stating what the visitor's does not, a staff reader seeing the teachers' field - so
 * none of the absences below passes against a card that states nothing at all.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(category_card::class)]
#[CoversClass(population::class)]
final class preview_facts_test extends \advanced_testcase {
    use fixture;

    /** @var string The site's timezone: a start late in the evening there is the next day in UTC. */
    private const TIMEZONE = 'America/Sao_Paulo';

    /** @var array The fixture's ids, by role. */
    private array $ids = [];

    /**
     * A public category with a course, an event and a favourite, and a filter field of every visibility.
     *
     * The filter fields, in order: the landing page model (hidden from everybody, but printed by the card as
     * its kind tag), a field visible to all, one visible to nobody and one visible to the course's editors.
     *
     * @return void
     */
    private function seed(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->setTimezone(self::TIMEZONE, self::TIMEZONE);
        $CFG->forcelogin = 1;
        $CFG->coursecontact = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        course_handler::reset_caches();
        landingfields::reset_field_cache();
        landingfields::ensure_fields();
        landingfields::reset_field_cache();
        set_config(population::SETTING_STATESORT, '1', 'theme_dimensions');

        $customfields = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $fieldcategory = $customfields->create_category(['name' => 'Vitrine']);
        $visibilities = [
            'area' => course_handler::VISIBLETOALL,
            'internal' => course_handler::NOTVISIBLE,
            'staff' => course_handler::VISIBLETOTEACHERS,
        ];
        foreach ($visibilities as $shortname => $visibility) {
            $field = $customfields->create_field([
                'categoryid' => $fieldcategory->get('id'),
                'shortname' => 'dimprev' . $shortname,
                'name' => ucfirst($shortname),
                'type' => 'select',
                'configdata' => ['options' => "Alfa\nBeta", 'defaultvalue' => '', 'visibility' => $visibility],
            ]);
            $this->ids['field' . $shortname] = (int) $field->get('id');
        }
        course_handler::reset_caches();
        $setting = landingfields::SHORTNAME_MODEL . ',dimprevarea,dimprevinternal,dimprevstaff';
        set_config(facets::SETTING, $setting, 'theme_dimensions');
        $this->ids['fieldmodel'] = (int) $DB->get_field('customfield_field', 'id', ['shortname' => landingfields::SHORTNAME_MODEL]);

        $generator = self::getDataGenerator();
        $root = (int) $generator->create_category(['name' => 'Vitrine publica'])->id;
        category_discoverability::set_state($root, category_discoverability::STATE_PUBLIC, (int) get_admin()->id);
        $handler = course_handler::create();
        $make = function (string $fullname, int $model, int $start, array $values) use ($root, $handler): int {
            $record = ['category' => $root, 'fullname' => $fullname, 'startdate' => $start, 'enddate' => 0];
            $course = self::getDataGenerator()->create_course($record);
            $form = ['id' => $course->id, 'customfield_' . landingfields::SHORTNAME_MODEL => $model];
            foreach ($values as $shortname => $value) {
                $form['customfield_dimprev' . $shortname] = $value;
            }
            $handler->instance_form_save((object) $form);
            discoverability::set_state((int) $course->id, discoverability::STATE_PUBLIC, (int) get_admin()->id);
            return (int) $course->id;
        };
        $later = (int) make_timestamp(2030, 11, 12, 23, 30, 0, self::TIMEZONE);
        $eventday = (int) make_timestamp(2030, 12, 3, 9, 0, 0, self::TIMEZONE);
        $this->ids['root'] = $root;
        $this->ids['course'] = $make('A Curso', landingfields::MODEL_COURSE, $later, ['area' => 1, 'internal' => 2, 'staff' => 1]);
        $this->ids['event'] = $make('B Evento', landingfields::MODEL_EVENT, $eventday, ['internal' => 1]);
        $this->ids['favourite'] = $make('C Favorito', landingfields::MODEL_COURSE, 0, ['area' => 2]);

        $this->ids['learner'] = $generator->create_user();
        $generator->enrol_user($this->ids['learner']->id, $this->ids['course']);
        $favourites = \core_favourites\service_factory::get_service_for_user_context(
            \core\context\user::instance($this->ids['learner']->id)
        );
        $favourite = $this->ids['favourite'];
        $favourites->create_favourite('core_course', 'courses', $favourite, \core\context\course::instance($favourite));
        $this->ids['editor'] = $this->user_with('moodle/course:update', \core\context\coursecat::instance($root));
        self::forget();
    }

    /**
     * The exported cards of the root's flat listing, keyed by course id, as a reader or a visitor gets them.
     *
     * @param \stdClass|null $user The reader, null for a visitor.
     * @return \stdClass[] courseid => card context.
     */
    private function cards(?\stdClass $user): array {
        $GLOBALS['PAGE'] = new \moodle_page();
        $this->become($user);
        $result = population::resolve(new criteria($this->ids['root'], [
            'anonymous' => $user === null,
            'sort' => criteria::SORT_NAME,
        ]));
        $cards = [];
        $output = $GLOBALS['PAGE']->get_renderer('core');
        foreach (category_listing::export_cards($result, $this->ids['root'], false, $output) as $card) {
            $cards[(int) $card->id] = $card;
        }
        $this->assertSame(
            [$this->ids['course'], $this->ids['event'], $this->ids['favourite']],
            array_keys($cards),
            'Precondition: the three courses are on the page, in the order of their names.'
        );
        return $cards;
    }

    /**
     * The opening tag of a rendered card.
     *
     * @param \stdClass $card The card context.
     * @return string The article's opening tag.
     */
    private function article(\stdClass $card): string {
        $html = $GLOBALS['PAGE']->get_renderer('core')->render_from_template('theme_dimensions/category_card', $card);
        $this->assertSame(1, preg_match('~<article\b[^>]*>~', $html, $match), 'The card renders its article.');
        return $match[0];
    }

    /**
     * A field's pair, as a card states it.
     *
     * @param string $field The field's role in the fixture: model, area, internal, staff.
     * @param int $value The option value, 0 for none.
     * @return string
     */
    private function pair(string $field, int $value): string {
        return $this->ids['field' . $field] . ':' . $value;
    }

    /**
     * A logged-in reader's card states its chip, the fields its reader may see and its start day.
     *
     * Changes that must make it fail: the population not handing the field values to the rows; the visibility
     * check dropped (the hidden field stated); the start day taken in UTC rather than the site's timezone.
     */
    public function test_a_readers_card_states_its_chip_its_fields_and_its_start_day(): void {
        $this->seed();
        $cards = $this->cards($this->ids['learner']);

        $course = $cards[$this->ids['course']];
        $this->assertSame('enrolled', $course->chip, 'The chip the scope keeps an enrolled course by.');
        $this->assertSame(classifier::chip($cards[$this->ids['event']]->state), $cards[$this->ids['event']]->chip);
        $this->assertSame(
            implode(' ', [$this->pair('model', landingfields::MODEL_COURSE), $this->pair('area', 1)]),
            $course->fieldvalues,
            'The model the card prints and the field visible to all; neither the hidden field nor the teachers\'.'
        );
        $this->assertSame(
            implode(' ', [$this->pair('model', landingfields::MODEL_EVENT), $this->pair('area', 0)]),
            $cards[$this->ids['event']]->fieldvalues,
            'A field the course holds no value for is stated as 0, never left out.'
        );
        $this->assertTrue($course->hasstartday);
        $this->assertSame('2030-11-12', $course->startday, 'The day in the site\'s timezone: in UTC it is already the 13th.');
        $this->assertTrue($cards[$this->ids['favourite']]->hasstartday, 'A reader\'s card states a start day it has none of.');
        $this->assertSame('', $cards[$this->ids['favourite']]->startday);

        $article = $this->article($course);
        $this->assertStringContainsString('data-chip="enrolled"', $article);
        $this->assertStringContainsString('data-fields="' . $course->fieldvalues . '"', $article);
        $this->assertStringContainsString('data-start="2030-11-12"', $article);
        $this->assertStringContainsString('data-start=""', $this->article($cards[$this->ids['favourite']]));

        // The favourite is the card's own star, whose pressed state the star module keeps live.
        $favourite = $GLOBALS['PAGE']->get_renderer('core')->render_from_template(
            'theme_dimensions/category_card',
            $cards[$this->ids['favourite']]
        );
        $this->assertMatchesRegularExpression('~<button[^>]*class="dim-card__star[^"]*"[^>]*aria-pressed="true"~', $favourite);
        $this->assertMatchesRegularExpression(
            '~<button[^>]*class="dim-card__star[^"]*"[^>]*aria-pressed="false"~',
            $GLOBALS['PAGE']->get_renderer('core')->render_from_template('theme_dimensions/category_card', $course)
        );
    }

    /**
     * The course's editors see the field visible to teachers, which the learner's card does not state.
     *
     * The control of the visibility check: without it, "no teachers' field" could be a card that never states one.
     */
    public function test_an_editor_reads_the_teachers_field_on_the_card(): void {
        $this->seed();
        $cards = $this->cards($this->ids['editor']);
        $this->assertSame(
            implode(' ', [$this->pair('model', landingfields::MODEL_COURSE), $this->pair('area', 1), $this->pair('staff', 1)]),
            $cards[$this->ids['course']]->fieldvalues,
            'The field visible to teachers, for a reader who may edit the course; never the hidden one.'
        );
    }

    /**
     * A visitor's card states no favourite, no chip, no field a visitor cannot see and no start day it does not print.
     *
     * Changes that must make it fail: a chip worked out for a visitor; the star drawn for one; a hidden or a
     * teachers' field stated to one; a course card stating its start day, which a visitor's card never prints.
     */
    public function test_a_visitors_card_states_nothing_a_visitor_cannot_see(): void {
        $this->seed();
        $cards = $this->cards(null);

        foreach ($cards as $card) {
            $this->assertSame('', $card->chip, 'A visitor\'s card knows no viewer state.');
            $this->assertFalse($card->showstar, 'And draws no star, so it says nothing about a favourite.');
            $article = $this->article($card);
            $this->assertStringContainsString('data-chip=""', $article);
            $this->assertStringNotContainsString(
                'dim-star',
                $GLOBALS['PAGE']->get_renderer('core')->render_from_template('theme_dimensions/category_card', $card)
            );
        }
        $course = $cards[$this->ids['course']];
        $this->assertSame(
            implode(' ', [$this->pair('model', landingfields::MODEL_COURSE), $this->pair('area', 1)]),
            $course->fieldvalues,
            'The model the card prints and the field visible to all; the hidden field and the teachers\' are not stated.'
        );
        $this->assertStringNotContainsString($this->ids['fieldinternal'] . ':', $course->fieldvalues);
        $this->assertStringNotContainsString($this->ids['fieldstaff'] . ':', $course->fieldvalues);

        $this->assertFalse($course->hasstartday, 'A course card prints no date, so a visitor\'s states none.');
        $this->assertStringNotContainsString('data-start', $this->article($course));
        $event = $cards[$this->ids['event']];
        $this->assertTrue($event->hasstartday, 'An event prints its date tile, so it states its day.');
        $this->assertSame('2030-12-03', $event->startday);
        $this->assertStringContainsString('data-start="2030-12-03"', $this->article($event));
    }

    /**
     * The card a client renders from the web service's answer carries the same facts as the page's.
     *
     * The answer is cleaned against the returns, which keep only the keys they declare: a fact the returns
     * left out would reach the page's cards and never the client's.
     */
    public function test_a_client_rendered_card_carries_the_same_facts(): void {
        $this->seed();
        $page = $this->cards($this->ids['learner']);
        $_POST['sesskey'] = sesskey();
        $answer = external_api::clean_returnvalue(
            get_category_rows::execute_returns(),
            rows::fetch(\core_course_category::get($this->ids['root'], MUST_EXIST, true), ['sort' => criteria::SORT_NAME])
        );
        $this->assertCount(3, $answer['cards']);
        foreach ($answer['cards'] as $card) {
            $card = (object) json_decode(json_encode($card));
            $expected = $page[(int) $card->id];
            foreach (['chip', 'fieldvalues', 'hasstartday', 'startday'] as $key) {
                $this->assertSame($expected->$key, $card->$key, "{$key} of course {$card->id}");
            }
            $this->assertSame($this->article($expected), $this->article($card));
        }
    }

    /**
     * With the page cut in SQL the rows carry no field values, so the cards state no field.
     *
     * That mode reads no map of the fields' values, and a card stating a field it was not handed would be stating 0
     * for every course; the preview of a field option stands down there instead.
     */
    public function test_the_sql_cut_states_no_field(): void {
        $this->seed();
        set_config(population::SETTING_STATESORT, '0', 'theme_dimensions');
        $cards = $this->cards($this->ids['learner']);
        foreach ($cards as $card) {
            $this->assertSame('', $card->fieldvalues);
        }
        $this->assertSame('2030-11-12', $cards[$this->ids['course']]->startday, 'The start day needs no map and stays.');
    }
}
