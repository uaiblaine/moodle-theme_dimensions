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
 * Theme Dimensions - Tests for what a listing request asks for
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for what a listing request reads from its parameters, and what a malformed value becomes.
 *
 * The search term, the page, the chip and the field filters of {@see criteria} are exercised
 * through the population, which is where they are read; this file covers the organisation, the
 * period, the tree's category, the panel's parameters, the root's reading of the address, and the
 * one thing only this class can be asked: what a malformed value becomes.
 *
 * Every date here is resolved in the site timezone, so the tests set one explicitly rather than
 * trusting whatever the test site is set to. Two are chosen for what they can catch: a timezone
 * whose daylight saving transitions happened at midnight (America/Sao_Paulo) and one half an hour
 * off a whole number of hours (Asia/Kolkata), because arithmetic on 86400-second days is right in
 * neither.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criteria::class)]
final class criteria_test extends \advanced_testcase {
    /**
     * The timezones every date rule has to hold in.
     *
     * @return array
     */
    public static function timezones_provider(): array {
        return [
            'the site\'s own' => ['America/Sao_Paulo'],
            'half an hour off a whole hour' => ['Asia/Kolkata'],
            'UTC' => ['UTC'],
        ];
    }

    /**
     * An ISO date becomes midnight of that day, and the upper bound becomes midnight of the day after.
     *
     * @param string $timezone The site timezone.
     */
    #[DataProvider('timezones_provider')]
    public function test_the_bounds_are_midnights_of_the_site_timezone(string $timezone): void {
        $this->resetAfterTest();
        $this->setTimezone($timezone);

        $criteria = new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-19']);
        $this->assertSame('2026-09-17', $criteria->datefrom);
        $this->assertSame('2026-09-19', $criteria->dateto);
        $this->assertSame(make_timestamp(2026, 9, 17, 0, 0, 0, $timezone), $criteria->fromtime);
        $this->assertSame(
            make_timestamp(2026, 9, 20, 0, 0, 0, $timezone),
            $criteria->totime,
            'The upper bound is the midnight AFTER the day the reader picked, so the whole day is inside it.'
        );
        $this->assertGreaterThan($criteria->fromtime, $criteria->totime);

        // And the resolution is the SITE's, not the viewer's: a person in another timezone gets the same range.
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'Pacific/Auckland']));
        $again = new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-19']);
        $this->assertSame($criteria->fromtime, $again->fromtime);
        $this->assertSame($criteria->totime, $again->totime);
    }

    /**
     * A day boundary is reached through the calendar and not by adding a day's worth of seconds.
     *
     * Over a daylight saving transition two consecutive midnights are 23 or 25
     * hours apart, so a range built by adding 86400 seconds would begin or end
     * an hour into the wrong day.
     */
    public function test_a_day_across_a_daylight_saving_transition_is_not_86400_seconds(): void {
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');

        // Two of Brazil's transitions, both at midnight: forward on 15 October 2017, back on 18 February 2018.
        $forward = new criteria(7, ['datefrom' => '2017-10-14', 'dateto' => '2017-10-15']);
        $this->assertSame(make_timestamp(2017, 10, 14, 0, 0, 0, 'America/Sao_Paulo'), $forward->fromtime);
        $this->assertSame(make_timestamp(2017, 10, 16, 0, 0, 0, 'America/Sao_Paulo'), $forward->totime);
        $this->assertNotSame(
            $forward->fromtime + (2 * DAYSECS),
            $forward->totime,
            'Two days that are not 48 hours: the clock moved, and the calendar is what decides the boundary.'
        );

        $back = new criteria(7, ['datefrom' => '2018-02-17', 'dateto' => '2018-02-18']);
        $this->assertNotSame($back->fromtime + (2 * DAYSECS), $back->totime);
    }

    /**
     * A date that is not a date is no bound at all, and the request still resolves.
     *
     * These arrive from a query string, so every one of them is something a
     * person can type into the address bar or a crawler can invent. A page that
     * threw over one could not be linked to.
     */
    public function test_a_malformed_date_is_dropped_rather_than_guessed(): void {
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');

        $malformed = [
            '17/09/2026' => 'another calendar\'s spelling',
            '2026-9-7' => 'no zero padding',
            'next tuesday' => 'something strtotime() would have resolved',
            '2026-09-31' => 'a day September does not have',
            '2025-02-29' => 'a leap day of a common year',
            '2026-09-17 10:00' => 'a time beside the date',
            ' 2026-09-17x' => 'a date with a tail',
            '' => 'nothing at all',
        ];
        foreach ($malformed as $value => $why) {
            $criteria = new criteria(7, ['datefrom' => $value, 'dateto' => $value]);
            $this->assertSame('', $criteria->datefrom, "Refused: {$why}.");
            $this->assertSame('', $criteria->dateto, "Refused: {$why}.");
            $this->assertNull($criteria->fromtime);
            $this->assertNull($criteria->totime);
            $this->assertFalse($criteria->has_filters(), "A dropped bound narrows nothing: {$why}.");
        }

        // The control: the same shape, well formed, IS a bound and DOES narrow.
        $good = new criteria(7, ['datefrom' => '2026-09-17']);
        $this->assertSame('2026-09-17', $good->datefrom);
        $this->assertNotNull($good->fromtime);
        $this->assertTrue($good->has_filters());
    }

    /**
     * The three shapes of a period: a start alone, an end alone, and both.
     *
     * Each bound is optional and independent (criteria's class note is the rule): a start alone
     * has no upper bound and an end alone no lower one, and each is a filter by itself. The
     * instants are the ones the predicate compares - midnight of the start, and midnight AFTER the
     * end - and a malformed bound beside a good one is dropped while the good one stands.
     */
    public function test_each_bound_of_a_period_stands_alone(): void {
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');
        $timezone = \core_date::get_server_timezone();

        $start = new criteria(7, ['datefrom' => '2026-09-17']);
        $this->assertSame(['2026-09-17', ''], [$start->datefrom, $start->dateto]);
        $this->assertSame(make_timestamp(2026, 9, 17, 0, 0, 0, $timezone), $start->fromtime);
        $this->assertNull($start->totime, 'A start alone has no upper bound.');
        $this->assertTrue($start->has_filters());
        $this->assertSame(1, $start->filter_count());

        $end = new criteria(7, ['dateto' => '2026-09-19']);
        $this->assertSame(['', '2026-09-19'], [$end->datefrom, $end->dateto]);
        $this->assertNull($end->fromtime, 'An end alone has no lower bound.');
        $this->assertSame(make_timestamp(2026, 9, 20, 0, 0, 0, $timezone), $end->totime, 'Midnight AFTER the last day.');
        $this->assertTrue($end->has_filters());
        $this->assertSame(1, $end->filter_count());

        $both = new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-19']);
        $this->assertSame([$start->fromtime, $end->totime], [$both->fromtime, $both->totime]);
        $this->assertSame(1, $both->filter_count(), 'Both bounds are still one filter.');

        // A malformed bound beside a good one is dropped, and the good one stands alone.
        $broken = new criteria(7, ['datefrom' => '2026-02-30', 'dateto' => '2026-09-19']);
        $this->assertSame(['', '2026-09-19'], [$broken->datefrom, $broken->dateto]);
        $this->assertSame($end->totime, $broken->totime);
        $broken = new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => 'next tuesday']);
        $this->assertSame(['2026-09-17', ''], [$broken->datefrom, $broken->dateto]);
        $this->assertNull($broken->totime);
    }

    /**
     * A pair of dates given backwards is the same period, put in order.
     *
     * The address is the only place a pair can arrive backwards - the no-JavaScript form's two
     * fields, or a hand-edited link - and a period runs between its two days whichever was named
     * first. Left as given it would keep nothing at all while the toolbar stated a filter; put in
     * order, it is what every address minted from the request carries from then on. The same day
     * on both sides is the one-day period and is not touched.
     */
    public function test_a_pair_given_backwards_is_put_in_order(): void {
        $this->resetAfterTest();
        $backwards = new criteria(7, ['datefrom' => '2026-09-19', 'dateto' => '2026-09-17']);
        $ordered = new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-19']);
        $this->assertSame(['2026-09-17', '2026-09-19'], [$backwards->datefrom, $backwards->dateto]);
        $this->assertSame([$ordered->fromtime, $ordered->totime], [$backwards->fromtime, $backwards->totime]);
        $this->assertLessThan($backwards->totime, $backwards->fromtime, 'A period that keeps something.');
        $this->assertSame(
            [criteria::PARAM_DATEFROM => '2026-09-17', criteria::PARAM_DATETO => '2026-09-19'],
            $backwards->query_params(),
            'And every address minted from it carries the pair in order.'
        );
        // Across a month and a year, because the comparison is of the whole date.
        $years = new criteria(7, ['datefrom' => '2027-01-02', 'dateto' => '2026-12-31']);
        $this->assertSame(['2026-12-31', '2027-01-02'], [$years->datefrom, $years->dateto]);

        $oneday = new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-17']);
        $this->assertSame(['2026-09-17', '2026-09-17'], [$oneday->datefrom, $oneday->dateto]);
    }

    /**
     * A start alone and an end alone each round-trip through the address as themselves.
     *
     * The pager, the quick options, the clear link and the hero's fold form all mint their
     * addresses from query_params(), so a one-bound period that did not travel there would be
     * lost by the next one of them a reader pressed while the listing was still narrowed by it.
     */
    public function test_a_one_bound_period_round_trips_through_the_address(): void {
        $this->resetAfterTest();
        foreach ([criteria::PARAM_DATEFROM => 'datefrom', criteria::PARAM_DATETO => 'dateto'] as $param => $option) {
            $criteria = new criteria(7, [$option => '2026-09-17', 'sort' => criteria::SORT_RECENT]);
            $params = $criteria->query_params();
            $this->assertSame([$param => '2026-09-17', criteria::PARAM_SORT => 'recent'], $params, 'The one bound, and no other.');
            $this->assertSame([$param => '2026-09-17'], $criteria->narrowing_params(), 'And so does a panel\'s call to action.');
            $again = new criteria(7, [
                'datefrom' => $params[criteria::PARAM_DATEFROM] ?? '',
                'dateto' => $params[criteria::PARAM_DATETO] ?? '',
                'sort' => $params[criteria::PARAM_SORT],
            ]);
            $this->assertEquals($criteria, $again, 'A request built from its own address asks for the same period.');
        }
    }

    /**
     * An unknown organisation is the default one, and the default has two spellings.
     */
    public function test_the_organisation_is_an_allowlist_whose_default_has_two_spellings(): void {
        $this->resetAfterTest();
        $this->assertSame('', (new criteria(7, ['sort' => 'bogus']))->sort);
        $this->assertSame('', (new criteria(7))->sort);
        $this->assertTrue((new criteria(7))->is_grouped(), 'No organisation named is the grouped one.');
        $this->assertTrue(
            (new criteria(7, ['sort' => criteria::SORT_CATEGORY]))->is_grouped(),
            'And so is the one the default chip posts.'
        );
        $this->assertFalse((new criteria(7, ['sort' => criteria::SORT_NAME]))->is_grouped());
        $this->assertFalse((new criteria(7, ['sort' => criteria::SORT_RECENT]))->is_grouped());
        $this->assertSame([''], array_values(array_diff(criteria::SORTS, ['category', 'name', 'recent'])));
    }

    /**
     * The three parameters travel in the address, so the pager and the clear link keep them.
     */
    public function test_the_parameters_round_trip_through_the_query(): void {
        $this->resetAfterTest();
        $criteria = new criteria(7, [
            'search' => 'resgate',
            'sort' => criteria::SORT_RECENT,
            'datefrom' => '2026-09-17',
            'dateto' => '2026-09-19',
        ]);
        $params = $criteria->query_params();
        $this->assertSame('recent', $params[criteria::PARAM_SORT]);
        $this->assertSame('2026-09-17', $params[criteria::PARAM_DATEFROM]);
        $this->assertSame('2026-09-19', $params[criteria::PARAM_DATETO]);

        // A second request built from those very parameters asks for the same thing.
        $again = new criteria(7, [
            'search' => $params[criteria::PARAM_SEARCH],
            'sort' => $params[criteria::PARAM_SORT],
            'datefrom' => $params[criteria::PARAM_DATEFROM],
            'dateto' => $params[criteria::PARAM_DATETO],
        ]);
        $this->assertEquals($criteria, $again);

        // The default organisation names itself with no parameter at all, in both spellings.
        $this->assertArrayNotHasKey(criteria::PARAM_SORT, (new criteria(7))->query_params());
        $this->assertSame([], (new criteria(7))->sort_params());
        $this->assertSame(
            [criteria::PARAM_SORT => 'category'],
            (new criteria(7, ['sort' => criteria::SORT_CATEGORY]))->sort_params()
        );
    }

    /**
     * A date range is ONE filter, and the organisation is none.
     *
     * The number on the filter panel's own summary is what a reader judges a
     * collapsed panel by, and the two bounds of one control are cleared together,
     * so counting them as two filters would overstate it.
     */
    public function test_the_filter_count_reads_a_date_range_as_one_filter(): void {
        $this->resetAfterTest();
        $this->assertSame(0, (new criteria(7))->filter_count());
        $this->assertSame(0, (new criteria(7, ['sort' => criteria::SORT_NAME]))->filter_count());
        $this->assertFalse(
            (new criteria(7, ['sort' => criteria::SORT_NAME]))->has_filters(),
            'Reordering a listing hides nothing, so it is not something to clear.'
        );
        $this->assertSame(1, (new criteria(7, ['datefrom' => '2026-09-17']))->filter_count());
        $this->assertSame(
            1,
            (new criteria(7, ['datefrom' => '2026-09-17', 'dateto' => '2026-09-19']))->filter_count(),
            'Two bounds of one control are one filter.'
        );
        $this->assertSame(
            3,
            (new criteria(7, [
                'search' => 'resgate',
                'fields' => [4 => 1],
                'datefrom' => '2026-09-17',
            ]))->filter_count()
        );
    }

    /**
     * The quick options' arithmetic: the day, the day after, and the week the site starts weeks on.
     */
    public function test_the_calendar_helpers_answer_in_the_site_timezone(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setTimezone('America/Sao_Paulo');

        $noon = make_timestamp(2026, 9, 17, 12, 0, 0, 'America/Sao_Paulo');
        $this->assertSame('2026-09-17', criteria::iso($noon));
        $this->assertSame('2026-09-18', criteria::iso($noon, 1));
        $this->assertSame('2026-10-01', criteria::iso($noon, 14), 'The month rolls over by itself.');
        $this->assertSame('2026-09-23', criteria::shift('2026-09-17', 6));
        $this->assertSame('', criteria::shift('nonsense', 1));

        // 17 September 2026 is a Thursday. With weeks starting on Sunday the week began on the 13th.
        $CFG->calendar_startwday = 0;
        $this->assertSame('2026-09-13', criteria::week_start($noon));
        $this->assertSame('2026-09-20', criteria::week_start($noon, 1));

        // And with weeks starting on Monday, on the 14th - the site's setting decides, not this class.
        $CFG->calendar_startwday = 1;
        $this->assertSame('2026-09-14', criteria::week_start($noon));
        $this->assertSame('2026-09-21', criteria::week_start($noon, 1));

        /* The same number the client's calendar is handed on the date fieldset, read from the same
           place: a popover whose grid began its weeks on another day would put the range a reader
           had just followed from "this week" one column away from where they picked it. */
        $this->assertSame(1, criteria::week_start_day());
        $CFG->calendar_startwday = 0;
        $this->assertSame(0, criteria::week_start_day());
    }

    /**
     * The tree's category is a filter of its own, and the grouped organisation has none.
     *
     * Two halves, and the second is the one nothing on a page could show. A
     * `cat` is read, counted, carried and cleared exactly as the search term
     * is - that is the first half, and every control of the toolbar depends on
     * it. The second is that the grouped organisation drops it HERE, at the
     * one place every later reader of the request goes through: that page
     * draws no tree at all, so a `cat` arriving with it is an address nothing
     * on screen could have written, and a filter that narrows a listing while
     * no control on the page can clear it is worse than no filter.
     */
    public function test_the_tree_criterion_is_a_filter_and_the_grouped_organisation_drops_it(): void {
        $this->resetAfterTest();

        $flat = new criteria(7, ['sort' => criteria::SORT_NAME, 'cat' => 9]);
        $this->assertSame(9, $flat->cat);
        $this->assertTrue($flat->has_filters(), 'The tree narrows the listing, so it is something to clear.');
        $this->assertSame(1, $flat->filter_count(), 'And it is one filter, like the period and the term.');
        $this->assertSame(9, $flat->query_params()[criteria::PARAM_CAT], 'It travels in the address.');
        $this->assertSame(
            3,
            (new criteria(7, [
                'sort' => criteria::SORT_RECENT,
                'search' => 'resgate',
                'datefrom' => '2026-09-17',
                'cat' => 9,
            ]))->filter_count(),
            'Beside the others it counts once, and the period still counts once for its two bounds.'
        );

        // The address round-trips: a request built from those parameters asks for the same thing.
        $again = new criteria(7, [
            'sort' => $flat->query_params()[criteria::PARAM_SORT],
            'cat' => $flat->query_params()[criteria::PARAM_CAT],
        ]);
        $this->assertEquals($flat, $again);

        // Nothing is a category: a zero, a negative id and an absent parameter are one answer.
        foreach ([0, -3] as $nothing) {
            $none = new criteria(7, ['sort' => criteria::SORT_NAME, 'cat' => $nothing]);
            $this->assertSame(0, $none->cat);
            $this->assertFalse($none->has_filters());
            $this->assertArrayNotHasKey(criteria::PARAM_CAT, $none->query_params());
        }

        /* The second half. Both spellings of the grouped organisation drop it, because both draw
           the posters and neither draws a tree - and the flat one beside them is the control,
           without which this test would pass against a class that dropped every cat there is. */
        foreach (['', criteria::SORT_CATEGORY] as $grouped) {
            $request = new criteria(7, ['sort' => $grouped, 'cat' => 9]);
            $this->assertTrue($request->is_grouped());
            $this->assertSame(0, $request->cat, 'The grouped page draws no tree, so it reads no cat.');
            $this->assertFalse($request->has_filters());
            $this->assertSame(0, $request->filter_count());
            $this->assertArrayNotHasKey(criteria::PARAM_CAT, $request->query_params());
        }
        $this->assertSame(9, (new criteria(7, ['sort' => criteria::SORT_RECENT, 'cat' => 9]))->cat);
    }

    /**
     * A panel's switch is off unless a request asks for it, and it is not a filter.
     *
     * It does not reach the address: the page has no organisation that draws a
     * panel's population, and a reader who typed one would get a listing whose
     * count line, pager and hero disagreed with every other address of the same
     * category. It is also not a filter - it hides no course - so "clear
     * filters" does not drop it and the number on the filter panel does not
     * count it. A cut to the category's direct courses is not an option, so
     * asking for one is refused like any other unknown key.
     */
    public function test_a_panels_switch_is_off_by_default_and_travels_in_no_address(): void {
        $this->resetAfterTest();
        $plain = new criteria(7);
        $this->assertFalse($plain->withcategories);
        $this->assertTrue(population::partitions($plain), 'The page partitions its own grouped listing.');

        $panel = new criteria(7, ['sort' => criteria::SORT_RECENT, 'withcategories' => true]);
        $this->assertTrue($panel->withcategories);
        $this->assertFalse(
            population::partitions($panel),
            'A panel asks for a flat order, so its population is the whole subtree and nothing is partitioned.'
        );
        $this->assertSame(
            [criteria::PARAM_SORT => criteria::SORT_RECENT],
            $panel->query_params(),
            'The switch travels in no address; only the order does.'
        );
        $this->assertFalse($panel->has_filters());
        $this->assertSame(0, $panel->filter_count());

        $this->expectException(\coding_exception::class);
        new criteria(7, ['direct' => true]);
    }

    /**
     * The open panel and its order travel in the address, and neither of them is a filter.
     *
     * The pager, the quick dates, the hero's fold form and the forward all mint their addresses
     * from query_params(), so a panel that did not travel there would close under every one of
     * them. The newest-first order is the default and names itself with no parameter at all.
     */
    public function test_the_panel_travels_in_the_address_and_narrows_nothing(): void {
        $this->resetAfterTest();
        $criteria = new criteria(7, ['open' => 9, 'psort' => criteria::SORT_NAME]);
        $this->assertSame(9, $criteria->open);
        $this->assertSame(criteria::SORT_NAME, $criteria->psort);
        $params = $criteria->query_params();
        $this->assertSame(9, $params[criteria::PARAM_OPEN]);
        $this->assertSame(criteria::SORT_NAME, $params[criteria::PARAM_PSORT]);
        $again = new criteria(7, ['open' => $params[criteria::PARAM_OPEN], 'psort' => $params[criteria::PARAM_PSORT]]);
        $this->assertEquals($criteria, $again, 'A request built from its own address asks for the same panel.');

        $this->assertFalse($criteria->has_filters(), 'Opening a panel hides no course.');
        $this->assertSame(0, $criteria->filter_count());

        $recent = new criteria(7, ['open' => 9]);
        $this->assertSame([criteria::PARAM_OPEN => 9], $recent->query_params(), 'The default order names itself with nothing.');
        $this->assertSame([], (new criteria(7))->panel_params(), 'And no panel is no parameter at all.');
    }

    /**
     * A panel is dropped where it cannot apply, and an order outside the vocabulary is the default one.
     *
     * The flat organisations draw no poster, so an open arriving with one names nothing on the page;
     * the grouped one beside it is the control. An order means nothing while no panel is open.
     */
    public function test_a_panel_is_dropped_where_it_cannot_apply(): void {
        $this->resetAfterTest();
        foreach (['', criteria::SORT_CATEGORY] as $grouped) {
            $this->assertSame(9, (new criteria(7, ['sort' => $grouped, 'open' => 9]))->open, 'The grouped page keeps it.');
        }
        foreach ([criteria::SORT_NAME, criteria::SORT_RECENT] as $flat) {
            $request = new criteria(7, ['sort' => $flat, 'open' => 9, 'psort' => criteria::SORT_NAME]);
            $this->assertSame(0, $request->open, 'A flat page draws no poster, so it opens no panel.');
            $this->assertSame('', $request->psort, 'And an order of no panel is no order.');
            $this->assertArrayNotHasKey(criteria::PARAM_OPEN, $request->query_params());
            $this->assertArrayNotHasKey(criteria::PARAM_PSORT, $request->query_params());
        }
        foreach (['bogus', 'recent', 'category', 'NAME'] as $bogus) {
            $this->assertSame(
                '',
                (new criteria(7, ['open' => 9, 'psort' => $bogus]))->psort,
                $bogus . ' is not an order of a panel.'
            );
        }
        $this->assertSame('', (new criteria(7, ['psort' => criteria::SORT_NAME]))->psort, 'No panel open, no order.');
        $this->assertSame(0, (new criteria(7, ['open' => -4]))->open);
        $this->assertSame([''], array_values(array_diff(criteria::PSORTS, [criteria::SORT_NAME])));
    }

    /**
     * "Clear filters" keeps the organisation and the panel; a panel's call to action carries what narrows, only.
     *
     * The two lists are complements over the address: everything that ORGANISES or OPENS stays
     * with the clear link, and everything that NARROWS - never the tree's category, the order, the
     * page size, the page or the panel - travels to the area's own page.
     */
    public function test_clear_keeps_the_panel_and_a_call_to_action_carries_what_narrows(): void {
        $this->resetAfterTest();
        $grouped = new criteria(7, [
            'search' => 'resgate',
            'state' => classifier::STATE_OPEN,
            'fields' => [5 => 2],
            'datefrom' => '2026-09-17',
            'dateto' => '2026-09-19',
            'sort' => criteria::SORT_CATEGORY,
            'pagesize' => 12,
            'page' => 2,
            'open' => 9,
            'psort' => criteria::SORT_NAME,
        ]);
        $this->assertSame(
            [criteria::PARAM_SORT => 'category', criteria::PARAM_OPEN => 9, criteria::PARAM_PSORT => 'name'],
            $grouped->keep_params()
        );
        $this->assertSame([criteria::PARAM_OPEN => 9], (new criteria(7, ['open' => 9]))->keep_params());
        $this->assertSame(
            [
                criteria::PARAM_SEARCH => 'resgate',
                criteria::PARAM_STATE => classifier::STATE_OPEN,
                criteria::PARAM_FIELD_PREFIX . '5' => 2,
                criteria::PARAM_DATEFROM => '2026-09-17',
                criteria::PARAM_DATETO => '2026-09-19',
            ],
            $grouped->narrowing_params()
        );
        $flat = new criteria(7, ['sort' => criteria::SORT_RECENT, 'cat' => 9, 'search' => 'resgate', 'pagesize' => 12]);
        $this->assertSame(9, $flat->cat, 'Precondition: the flat page keeps its tree criterion.');
        $this->assertSame(
            [criteria::PARAM_SEARCH => 'resgate'],
            $flat->narrowing_params(),
            'Never the tree, the order or the size.'
        );
        $this->assertSame([], (new criteria(7))->narrowing_params());
    }

    /**
     * The root reads its search and its panel off the address and nothing else; the page reads
     * everything and asks for its posters.
     *
     * The root draws a search box and no other control that narrows, so a chip, a field filter, a period,
     * a tree category, an organisation, a page size or a page on its address is dropped: nothing on the
     * page could show or clear it.
     */
    public function test_the_root_reads_its_search_and_its_panel_alone_and_the_posters_switch_travels_nowhere(): void {
        $this->resetAfterTest();
        $_GET = ['open' => '9', 'psort' => 'name', 'q' => 'resgate', 'cf_5' => '2', 'sort' => 'recent', 'page' => '3'];
        $this->assertSame(['open' => 9, 'psort' => 'name'], criteria::panel_options_from_request());

        $_GET = [
            'open' => '9',
            'psort' => 'name',
            'q' => 'resgate',
            'cf_5' => '2',
            'chip' => classifier::STATE_ENROLLED,
            'sort' => 'recent',
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'cat' => '4',
            'perpage' => '6',
            'page' => '3',
        ];
        $root = criteria::root_from_request();
        $this->assertSame(0, $root->categoryid);
        $this->assertSame('resgate', $root->search, 'The root reads its term.');
        $this->assertSame([9, 'name'], [$root->open, $root->psort], 'And its panel.');
        $this->assertTrue($root->withposters);
        $this->assertSame(
            [null, [], '', '', '', 0, null, 0],
            [$root->state, $root->fields, $root->sort, $root->datefrom, $root->dateto, $root->cat, $root->pagesize, $root->page],
            'And nothing else the address says.'
        );
        $this->assertSame(['q' => 'resgate', 'open' => 9, 'psort' => 'name'], $root->query_params(), 'What its forms carry.');
        $this->assertFalse($root->anonymous);
        $this->assertTrue(criteria::root_from_request(true)->anonymous, 'The anonymous shell says so.');

        $_GET = ['open' => '9', 'psort' => 'name', 'q' => 'resgate'];
        $request = criteria::from_request(7, []);
        $this->assertTrue($request->withposters, 'The page asks for its posters.');
        $this->assertSame(9, $request->open);
        $this->assertSame('name', $request->psort);
        $this->assertSame('resgate', $request->search, 'The control: the page reads its filters too.');

        $this->assertFalse((new criteria(7))->withposters, 'Off unless asked for, which is what the web service gets.');
        $this->assertSame([], (new criteria(7, ['withposters' => true]))->query_params(), 'It travels in no address.');
    }
}
