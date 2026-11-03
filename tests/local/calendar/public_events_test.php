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
 * Theme Dimensions - Tests for the category events a visitor is shown
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\calendar;

use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\calendar\source\public_events;
use theme_dimensions\local\category\population;
use theme_dimensions\local\category\publicaccess;
use theme_dimensions\local\landing\links;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * The visitor's category events: who is public, which rows are category events, and what an item says.
 *
 * Every "never answered" case has its control: the same row or the same category answered once the one reason is gone,
 * in the same test, so no assertion passes against a source that answers nothing. Asked as a visitor in a new request
 * each time (the plugin's memos forgotten).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(public_events::class)]
#[CoversClass(visitor::class)]
#[CoversClass(publicaccess::class)]
final class public_events_test extends \advanced_testcase {
    use fixture;

    /** @var array The fixture's category ids, by role. */
    private array $ids = [];

    /**
     * A public category, a listed sibling, an unlisted one, a hidden one, and a public one under a hidden parent.
     *
     * @return void
     */
    private function seed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $adminid = (int) get_admin()->id;
        $public = (int) $generator->create_category(['name' => 'Agenda & Cia'])->id;
        $listed = (int) $generator->create_category(['name' => 'Listada'])->id;
        $unlisted = (int) $generator->create_category(['name' => 'Fora da lista'])->id;
        $hidden = (int) $generator->create_category(['name' => 'Oculta', 'visible' => 0])->id;
        $parent = (int) $generator->create_category(['name' => 'Pai oculto', 'visible' => 0])->id;
        $child = (int) $generator->create_category(['name' => 'Filha publica', 'parent' => $parent])->id;
        // Core hides a category created under a hidden one: the child's own row is shown, the ancestor alone hidden.
        $DB->set_field('course_categories', 'visible', 1, ['id' => $child]);
        foreach ([$public, $hidden, $child] as $categoryid) {
            category_discoverability::set_state($categoryid, category_discoverability::STATE_PUBLIC, $adminid);
        }
        category_discoverability::set_state($unlisted, category_discoverability::STATE_UNLISTED, $adminid);
        $this->ids = ['public' => $public, 'listed' => $listed, 'unlisted' => $unlisted, 'hidden' => $hidden,
            'parent' => $parent, 'child' => $child];
        self::forget_calendar();
    }

    /**
     * A category event of next month.
     *
     * @param int $categoryid The category.
     * @param array $more Further fields.
     * @return int The event id.
     */
    private function event(int $categoryid, array $more = []): int {
        $this->setAdminUser();
        $event = self::getDataGenerator()->create_event($more + [
            'name' => 'Feira',
            'eventtype' => 'category',
            'categoryid' => $categoryid,
            'timestart' => self::at(10),
        ]);
        return (int) $event->id;
    }

    /**
     * The ids a visitor is answered for next month, as a new request.
     *
     * @return int[]
     */
    private function answered(): array {
        $this->setUser(null);
        self::forget_calendar();
        return self::item_ids((new public_events())->items(self::month_window(), publicaccess::public_ids()));
    }

    /**
     * Only an effectively public category's events are answered, each refusal beside its control.
     *
     * @return void
     */
    public function test_only_an_effectively_public_category_is_answered(): void {
        global $DB;
        $this->seed();
        $ids = $this->ids;
        $events = [];
        foreach ($ids as $role => $categoryid) {
            $events[$role] = $this->event($categoryid, ['name' => 'Evento ' . $role]);
        }
        $this->assertSame([$events['public']], $this->answered(), 'Only the public category\'s event.');

        $adminid = (int) get_admin()->id;
        // A merely listed category: its own row is not public. Control: made public.
        category_discoverability::set_state($ids['listed'], category_discoverability::STATE_PUBLIC, $adminid);
        $this->assertContains($events['listed'], $this->answered(), 'Control: listed, then public, is answered.');
        category_discoverability::set_state($ids['listed'], category_discoverability::STATE_DEFAULT, $adminid);
        $this->assertNotContains($events['listed'], $this->answered());

        // An unlisted category. Control: made public.
        category_discoverability::set_state($ids['unlisted'], category_discoverability::STATE_PUBLIC, $adminid);
        $this->assertContains($events['unlisted'], $this->answered(), 'Control: the unlisted one, made public.');
        category_discoverability::set_state($ids['unlisted'], category_discoverability::STATE_UNLISTED, $adminid);
        $this->assertNotContains($events['unlisted'], $this->answered());

        // A public category core hides. Control: shown.
        $DB->set_field('course_categories', 'visible', 1, ['id' => $ids['hidden']]);
        $this->assertContains($events['hidden'], $this->answered(), 'Control: the hidden one, shown.');
        $DB->set_field('course_categories', 'visible', 0, ['id' => $ids['hidden']]);
        $this->assertNotContains($events['hidden'], $this->answered());

        // A public category under a hidden ancestor. Control: the ancestor shown.
        $DB->set_field('course_categories', 'visible', 1, ['id' => $ids['parent']]);
        $this->assertContains($events['child'], $this->answered(), 'Control: the ancestor shown.');
        $DB->set_field('course_categories', 'visible', 0, ['id' => $ids['parent']]);
        $this->assertNotContains($events['child'], $this->answered());
    }

    /**
     * A public category under an unlisted ancestor is not public, beside the same ancestor merely listed.
     *
     * @return void
     */
    public function test_an_unlisted_ancestor_withholds_a_public_category(): void {
        $this->seed();
        $adminid = (int) get_admin()->id;
        $parent = (int) self::getDataGenerator()->create_category(['name' => 'Pai'])->id;
        $child = (int) self::getDataGenerator()->create_category(['name' => 'Filha', 'parent' => $parent])->id;
        category_discoverability::set_state($child, category_discoverability::STATE_PUBLIC, $adminid);
        $event = $this->event($child);
        $this->assertContains($event, $this->answered(), 'Control: under a listed parent.');
        category_discoverability::set_state($parent, category_discoverability::STATE_UNLISTED, $adminid);
        $this->assertNotContains($event, $this->answered());
    }

    /**
     * Only a category event as core writes one: each other shape of the row is dropped, and answered once restored.
     *
     * @return void
     */
    public function test_only_a_visible_category_event_with_no_course_group_or_component(): void {
        global $DB;
        $this->seed();
        $id = $this->event($this->ids['public']);
        $this->assertSame([$id], $this->answered(), 'Control: the category event.');
        $course = self::getDataGenerator()->create_course();
        $cases = [
            'a user event' => ['eventtype', 'user', 'category'],
            'a site event' => ['eventtype', 'site', 'category'],
            'a course event' => ['eventtype', 'course', 'category'],
            'a hidden event' => ['visible', 0, 1],
            'a course on the row' => ['courseid', (int) $course->id, 0],
            'a group on the row' => ['groupid', 7, 0],
            'a component\'s event' => ['component', 'mod_assign', null],
        ];
        foreach ($cases as $case => [$field, $value, $restore]) {
            $DB->set_field('event', $field, $value, ['id' => $id]);
            $this->assertSame([], $this->answered(), $case . ' is never answered.');
            $DB->set_field('event', $field, $restore, ['id' => $id]);
            $this->assertSame([$id], $this->answered(), $case . ': answered once restored.');
        }
    }

    /**
     * The window: an event starting inside it, or spanning into it, is answered; one ending at its start is not.
     *
     * @return void
     */
    public function test_the_window_keeps_what_starts_in_it_or_spans_into_it(): void {
        $this->seed();
        $start = self::month_window()->tstart;
        $inside = $this->event($this->ids['public'], ['timestart' => self::at(28, 23)]);
        $spanning = $this->event($this->ids['public'], ['timestart' => $start - DAYSECS, 'timeduration' => DAYSECS + 1]);
        $ending = $this->event($this->ids['public'], ['timestart' => $start - DAYSECS, 'timeduration' => DAYSECS]);
        $before = $this->event($this->ids['public'], ['timestart' => $start - 1]);
        $after = $this->event($this->ids['public'], ['timestart' => self::month_window()->tend + 1]);
        $answered = $this->answered();
        $this->assertContains($inside, $answered);
        $this->assertContains($spanning, $answered, 'Spanning into the window.');
        $this->assertNotContains($ending, $answered, 'Ending at the window\'s first instant.');
        $this->assertNotContains($before, $answered);
        $this->assertNotContains($after, $answered);
    }

    /**
     * An item: the plain spelling in one language (multilang alone), the public page as its address, no creator, the
     * description as text.
     *
     * @return void
     */
    public function test_an_item_says_what_a_visitor_may_read(): void {
        $this->seed();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        filter_set_applies_to_strings('multilang', true);
        \filter_manager::reset_caches();
        $multilang = '<span lang="en" class="multilang">Fair</span><span lang="pt_br" class="multilang">Feira</span>';
        $id = $this->event($this->ids['public'], [
            'name' => 'Ciência & ' . $multilang,
            'location' => 'Sala <b>2</b>',
            'description' => '<p>Leve <strong>caneta</strong>.</p><p><img src="@@PLUGINFILE@@/mapa.png" alt="Mapa"></p>',
            'format' => FORMAT_HTML,
        ]);
        $this->setUser(null);
        self::forget_calendar();
        $source = new public_events();
        $items = $source->items(self::month_window(), publicaccess::public_ids());
        $this->assertCount(1, $items);
        $item = $items[0];
        $this->assertSame(range::ITEM_KEYS, array_keys($item), 'The allowlist: no creator, no description, no html.');
        $this->assertSame('Ciência & Fair', $item['name'], 'Plain, tags stripped, the language of the answer alone.');
        $this->assertSame('Sala 2', $item['location']);
        $this->assertSame(links::category_page($this->ids['public'])->out(false), $item['url'], 'The public category page.');
        $this->assertSame([keys::CATEGORY, $this->ids['public'], 0, 0], [$item['key'], $item['categoryid'], $item['courseid'],
            $item['groupid']]);
        $text = $source->descriptions()[$id];
        $this->assertStringContainsStringIgnoringCase('Leve caneta.', $text, 'html_to_text() capitalises a strong.');
        $this->assertStringContainsString('[Mapa]', $text, 'An image is its words.');
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsString('PLUGINFILE', $text, 'No file is addressed.');
        $this->assertStringNotContainsString('pluginfile', $text);

        // The filter is really on: a format_string() with filters reads one language.
        $this->assertSame('Fair', format_string($multilang, true, ['context' => \core\context\system::instance()]));
    }

    /**
     * The names follow the site's multilang filter, as the logged-in page's do: on, one language; off, or merely
     * available, the raw text. No other filter runs, even one that is on and filters strings (emailprotect).
     *
     * @return void
     */
    public function test_names_run_the_multilang_filter_alone(): void {
        $this->seed();
        $multilang = '<span lang="en" class="multilang">Fair</span><span lang="pt_br" class="multilang">Feira</span>';
        $this->event($this->ids['public'], ['name' => $multilang, 'location' => 'Sala de contato@example.com']);
        filter_set_global_state('emailprotect', TEXTFILTER_ON);
        filter_set_applies_to_strings('emailprotect', true);
        $states = [
            'on' => [TEXTFILTER_ON, 'Fair'],
            'available' => [TEXTFILTER_OFF, 'FairFeira'],
            'disabled' => [TEXTFILTER_DISABLED, 'FairFeira'],
        ];
        foreach ($states as $label => [$state, $expected]) {
            $this->setAdminUser();
            filter_set_global_state('multilang', $state);
            filter_set_applies_to_strings('multilang', true);
            \filter_manager::reset_caches();
            $this->setUser(null);
            \cache::make_from_params(\cache_store::MODE_REQUEST, 'theme_dimensions', 'visitormultilang')->purge();
            $items = (new public_events())->items(self::month_window(), publicaccess::public_ids());
            $this->assertSame($expected, $items[0]['name'], $label);
            $this->assertSame('Sala de contato@example.com', $items[0]['location'], "{$label}: no other filter runs.");
        }
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'theme_dimensions', 'visitormultilang')->purge();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $this->assertNotSame(
            'Sala de contato@example.com',
            format_string('Sala de contato@example.com', true, ['context' => \core\context\system::instance(), 'escape' => false]),
            'Control: the emailprotect filter really rewrites a string.'
        );
    }

    /**
     * At most CAP events a window, earliest first; one more says the answer was cut, and exactly CAP does not.
     *
     * @return void
     */
    public function test_the_cap(): void {
        global $DB;
        $this->seed();
        $template = $DB->get_record('event', ['id' => $this->event($this->ids['public'], ['timestart' => self::at(1)])]);
        unset($template->id);
        $rows = [];
        for ($i = 1; $i < public_events::CAP; $i++) {
            $rows[] = (object) (['timestart' => self::at(2) + $i] + (array) $template);
        }
        $DB->insert_records('event', $rows);
        $this->setUser(null);

        $source = new public_events();
        $this->assertCount(public_events::CAP, $source->items(self::month_window(), publicaccess::public_ids()));
        $this->assertFalse($source->truncated(), 'Control: exactly the cap is whole.');
        $this->assertFalse(publicrange::answer(self::month_window(), [keys::CATEGORY])['truncated'], 'Control.');

        $late = $this->event($this->ids['public'], ['timestart' => self::at(20)]);
        $this->setUser(null);
        $items = $source->items(self::month_window(), publicaccess::public_ids());
        $this->assertCount(public_events::CAP, $items);
        $this->assertTrue($source->truncated());
        $this->assertNotContains($late, array_column($items, 'id'), 'The latest is the one left out.');
        $this->assertSame((int) $template->timestart, $items[0]['timestart'], 'Earliest first.');
        $this->assertTrue(publicrange::answer(self::month_window(), [keys::CATEGORY])['truncated'], "The answer's cut.");
    }

    /**
     * The statement itself reads at most one row past the cap: the slice alone would answer the same items, so only the
     * source can show that an anonymous request never reads a window's every event.
     *
     * @return void
     */
    public function test_the_statement_reads_one_row_past_the_cap(): void {
        $source = file_get_contents(dirname(__DIR__, 3) . '/classes/local/calendar/source/public_events.php');
        $this->assertMatchesRegularExpression(
            '~\$rows = \$DB->get_records_sql\(.*?\],\s*0,\s*self::CAP \+ 1\s*\);~s',
            (string) $source
        );
    }

    /**
     * A description longer than the bound is cut, and a row without one has none.
     *
     * @return void
     */
    public function test_a_long_description_is_cut_and_an_empty_one_is_none(): void {
        $this->seed();
        $long = $this->event($this->ids['public'], ['description' => str_repeat('a', public_events::MAX_DESCRIPTION + 50)]);
        $short = $this->event($this->ids['public'], ['description' => 'curto']);
        $none = $this->event($this->ids['public'], ['description' => '']);
        $this->setUser(null);
        $source = new public_events();
        $source->items(self::month_window(), publicaccess::public_ids());
        $descriptions = $source->descriptions();
        $this->assertSame(public_events::MAX_DESCRIPTION, \core_text::strlen($descriptions[$long]));
        $this->assertSame('curto', $descriptions[$short], 'Control: a short one is whole.');
        $this->assertArrayNotHasKey($none, $descriptions);
    }

    /**
     * Who is public fails closed: the surface's switches and a missing predicate answer no category, each beside the
     * same request answered.
     *
     * @return void
     */
    public function test_who_is_public_fails_closed(): void {
        $this->seed();
        $this->assertSame([$this->ids['public']], publicaccess::public_ids(), 'Control.');
        $this->assertSame([], publicaccess::public_ids('\\local_unlistedcourses\\no_such_class'), 'A missing predicate.');
        foreach ([publicaccess::SETTING_ENABLED, population::SETTING_ENABLED] as $setting) {
            set_config($setting, '0', 'theme_dimensions');
            $this->assertSame([], publicaccess::public_ids(), $setting . ' off.');
            set_config($setting, '1', 'theme_dimensions');
            $this->assertSame([$this->ids['public']], publicaccess::public_ids(), $setting . ' on again.');
        }
        foreach ($this->ids as $categoryid) {
            $this->assertSame(
                in_array($categoryid, publicaccess::public_ids(), true),
                publicaccess::is_public($categoryid),
                'The batch is is_public() for the whole site.'
            );
        }
        $this->assertSame([], (new public_events())->items(self::month_window(), []), 'No public category, no read.');
    }
}
