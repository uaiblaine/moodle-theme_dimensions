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
 * Theme Dimensions - Tests of the state a reader left each category in
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\output\category_listing;
use theme_dimensions\output\category_toolbar;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests of the remembered state: validation, the bound, who remembers, and the precedence of the request.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(remembered::class)]
#[CoversClass(criteria::class)]
#[CoversClass(category_listing::class)]
final class remembered_test extends \advanced_testcase {
    use fixture;

    /**
     * Leave no query parameter behind for the next test.
     *
     * @return void
     */
    protected function tearDown(): void {
        foreach (['sort', 'chip', 'q', 'open', 'from', 'to', 'cat', 'view'] as $name) {
            unset($_GET[$name]);
        }
        foreach (array_keys($_GET) as $name) {
            if (str_starts_with((string) $name, criteria::PARAM_FIELD_PREFIX)) {
                unset($_GET[$name]);
            }
        }
        self::forget();
        parent::tearDown();
    }

    /**
     * A select field on the site's filter list, with three options.
     *
     * @param string $shortname The field's short name.
     * @return int The field id.
     */
    private static function filter_field(string $shortname = 'nivel'): int {
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['component' => 'core_course', 'area' => 'course', 'itemid' => 0]);
        $field = $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'select',
            'shortname' => $shortname,
            'name' => ucfirst($shortname),
            'configdata' => ['options' => "Um\nDois\nTrês", 'defaultvalue' => ''],
        ]);
        set_config(facets::SETTING, $shortname, 'theme_dimensions');
        return (int) $field->get('id');
    }

    /**
     * The request the page builds from an address, for the session's reader.
     *
     * @param int $categoryid The category.
     * @param array $get The query string.
     * @param array $fieldids The allowlisted field ids.
     * @param bool $anonymous Whether the anonymous shell asks.
     * @return criteria
     */
    private static function request(int $categoryid, array $get = [], array $fieldids = [], bool $anonymous = false): criteria {
        foreach ($get as $name => $value) {
            $_GET[$name] = $value;
        }
        $criteria = criteria::from_request($categoryid, $fieldids, $anonymous);
        foreach (array_keys($get) as $name) {
            unset($_GET[$name]);
        }
        return $criteria;
    }

    /**
     * Every key and value is checked against its vocabulary on the way in, and only the valid ones are stored.
     */
    public function test_a_state_is_validated_before_it_is_stored(): void {
        $this->resetAfterTest();
        $fieldid = self::filter_field();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->setUser(self::getDataGenerator()->create_user());

        remembered::record($categoryid, [
            remembered::KEY_SORT => criteria::SORT_NAME,
            remembered::KEY_VIEW => category_toolbar::VIEW_LIST,
            remembered::KEY_STATE => 'open',
            remembered::KEY_FIELDS => [$fieldid => 2],
        ]);
        $this->assertSame([
            remembered::KEY_SORT => criteria::SORT_NAME,
            remembered::KEY_VIEW => category_toolbar::VIEW_LIST,
            remembered::KEY_STATE => 'open',
            remembered::KEY_FIELDS => [$fieldid => 2],
        ], remembered::for_category($categoryid), 'Control: a valid state is stored whole.');

        // Every value outside its vocabulary: an unknown organisation, view and chip, a field that is
        // not on the site's filter list, an option the field does not have, and a value that is not a number.
        remembered::record($categoryid, [
            remembered::KEY_SORT => 'bogus',
            remembered::KEY_VIEW => 'grid',
            remembered::KEY_STATE => 'nosuchstate',
            remembered::KEY_FIELDS => [$fieldid + 1000 => 1, $fieldid => 4],
        ]);
        $this->assertSame([
            remembered::KEY_SORT => criteria::SORT_NAME,
            remembered::KEY_VIEW => category_toolbar::VIEW_LIST,
            remembered::KEY_STATE => 'open',
            remembered::KEY_FIELDS => [$fieldid => 2],
        ], remembered::for_category($categoryid), 'Every value refused: the entry keeps what it held.');

        // Of a set of field filters the valid ones are kept; a null forgets a key; nothing left is no entry.
        remembered::record($categoryid, [remembered::KEY_FIELDS => [$fieldid => 3, $fieldid + 1000 => 1, 'x' => '1']]);
        $this->assertSame([$fieldid => 3], remembered::for_category($categoryid)[remembered::KEY_FIELDS]);
        remembered::record($categoryid, [
            remembered::KEY_SORT => null,
            remembered::KEY_VIEW => null,
            remembered::KEY_STATE => null,
            remembered::KEY_FIELDS => null,
        ]);
        $this->assertNull(remembered::for_category($categoryid));
        $this->assertSame('{}', get_user_preferences(remembered::PREFERENCE));
    }

    /**
     * A stored value is validated again when it is read: a hand-edited or stale row hands nothing invalid on.
     */
    public function test_a_stored_value_is_validated_on_the_way_out(): void {
        $this->resetAfterTest();
        $fieldid = self::filter_field();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->setUser(self::getDataGenerator()->create_user());

        $stored = json_encode([
            (string) $categoryid => ['s' => 'recent', 'v' => 'list', 't' => 'evil', 'f' => [(string) $fieldid => 2, '999999' => 1]],
            'abc' => ['s' => 'name'],
            '-4' => ['s' => 'name'],
            '77' => 'not an entry',
        ]);
        set_user_preference(remembered::PREFERENCE, $stored);
        $this->assertSame([
            $categoryid => ['s' => 'recent', 'v' => 'list', 'f' => [$fieldid => 2]],
        ], remembered::map());

        // A field taken off the site's filter list is dropped from what was remembered with it.
        set_config(facets::SETTING, '', 'theme_dimensions');
        $this->assertSame([$categoryid => ['s' => 'recent', 'v' => 'list']], remembered::map());

        set_user_preference(remembered::PREFERENCE, 'not json');
        $this->assertSame([], remembered::map());
        $this->assertNull(remembered::for_category($categoryid));
    }

    /**
     * Twenty categories are remembered, the least recently written going first, and writing one again makes it the newest.
     */
    public function test_the_least_recently_written_category_goes_first(): void {
        $this->resetAfterTest();
        $this->setUser(self::getDataGenerator()->create_user());
        $ids = [];
        for ($i = 0; $i < remembered::MAX_CATEGORIES; $i++) {
            $ids[] = 1000 + $i;
        }
        foreach ($ids as $id) {
            remembered::record($id, [remembered::KEY_SORT => criteria::SORT_NAME]);
        }
        $this->assertSame($ids, array_keys(remembered::map()), 'Control: the twenty fit, oldest first.');

        // The first is written again, so it is the newest; the next one past the bound drops the second.
        remembered::record($ids[0], [remembered::KEY_VIEW => category_toolbar::VIEW_LIST]);
        remembered::record(5000, [remembered::KEY_SORT => criteria::SORT_RECENT]);
        $map = remembered::map();
        $this->assertCount(remembered::MAX_CATEGORIES, $map);
        $this->assertArrayNotHasKey($ids[1], $map, 'The least recently written category went.');
        $this->assertSame([$ids[0], 5000], array_slice(array_keys($map), -2));
        $this->assertSame(
            [remembered::KEY_SORT => criteria::SORT_NAME, remembered::KEY_VIEW => category_toolbar::VIEW_LIST],
            $map[$ids[0]],
            'A change keeps what the entry held besides it.'
        );
    }

    /**
     * However long the entries, the stored value fits core's column, the newest categories kept whole.
     */
    public function test_the_stored_value_always_fits_the_column(): void {
        $this->resetAfterTest();
        $map = [];
        for ($i = 0; $i < remembered::MAX_CATEGORIES; $i++) {
            $map[900000 + $i] = [
                remembered::KEY_SORT => criteria::SORT_RECENT,
                remembered::KEY_VIEW => category_toolbar::VIEW_CARDS,
                remembered::KEY_STATE => classifier::CHIP_FAVOURITES,
                remembered::KEY_FIELDS => [100001 => 1, 100002 => 2, 100003 => 3, 100004 => 4],
            ];
        }
        $this->assertGreaterThan(remembered::MAX_LENGTH, strlen(json_encode($map)), 'Precondition: twenty of these do not fit.');

        $json = remembered::encode($map);
        $this->assertLessThanOrEqual(remembered::MAX_LENGTH, strlen($json));
        $kept = array_map('intval', array_keys(json_decode($json, true)));
        $this->assertNotEmpty($kept);
        $this->assertSame(array_slice(array_keys($map), -count($kept)), $kept, 'The newest are the ones kept.');
        $this->assertSame(['s', 'v', 't', 'f'], array_keys(json_decode($json, true)[(string) end($kept)]), 'Kept whole.');
    }

    /**
     * The guest account and a visitor remember nothing, and read nothing; neither does the root.
     */
    public function test_the_guest_a_visitor_and_the_root_remember_nothing(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;

        // Control: a real account remembers.
        $this->setUser(self::getDataGenerator()->create_user());
        remembered::record($categoryid, [remembered::KEY_SORT => criteria::SORT_NAME]);
        $this->assertNotNull(remembered::for_category($categoryid));
        remembered::record(0, [remembered::KEY_SORT => criteria::SORT_NAME]);
        $this->assertArrayNotHasKey(0, remembered::map(), 'The root reads its search alone.');

        $this->setGuestUser();
        remembered::record($categoryid, [remembered::KEY_SORT => criteria::SORT_NAME]);
        $this->assertNull(remembered::for_category($categoryid));
        $this->assertNull(get_user_preferences(remembered::PREFERENCE), 'Nothing reached the guest account\'s preferences.');
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view(category_toolbar::VIEW_LIST, $categoryid));
        $this->assertSame(
            category_toolbar::VIEW_CARDS,
            category_listing::view('', $categoryid),
            'The guest gets a view for one page only.'
        );

        $this->setUser(null);
        remembered::record($categoryid, [remembered::KEY_SORT => criteria::SORT_NAME]);
        $this->assertNull(remembered::for_category($categoryid));
        $this->assertFalse(self::request($categoryid)->remembered);
    }

    /**
     * A bare address gets the reader's remembered state, and the request then names its organisation everywhere.
     */
    public function test_a_bare_address_opens_in_the_remembered_state(): void {
        $this->resetAfterTest();
        $fieldid = self::filter_field();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->setUser(self::getDataGenerator()->create_user());

        // Control: nothing remembered is the category's default, and the default is left out of addresses.
        $plain = self::request($categoryid, [], [$fieldid]);
        $this->assertFalse($plain->remembered);
        $this->assertSame('', $plain->sort);
        $this->assertSame([], $plain->sort_params());

        remembered::record($categoryid, [
            remembered::KEY_SORT => criteria::SORT_NAME,
            remembered::KEY_STATE => 'open',
            remembered::KEY_FIELDS => [$fieldid => 2],
        ]);
        $criteria = self::request($categoryid, [], [$fieldid]);
        $this->assertTrue($criteria->remembered);
        $this->assertSame(criteria::SORT_NAME, $criteria->sort);
        $this->assertSame('open', $criteria->state);
        $this->assertSame([$fieldid => 2], $criteria->fields);
        $this->assertSame(
            ['chip' => 'open', 'cf_' . $fieldid => 2, 'sort' => criteria::SORT_NAME],
            $criteria->query_params(),
            'Every address minted from it names the whole state.'
        );

        // The remembered organisation wins over the category's own default.
        branding::save($categoryid, (object) ['defaultsort' => criteria::SORT_RECENT]);
        $this->assertSame(criteria::SORT_NAME, self::request($categoryid, [], [$fieldid])->sort);

        // Another category is not this one: nothing is carried into it.
        $other = (int) self::getDataGenerator()->create_category()->id;
        $elsewhere = self::request($other, [], [$fieldid]);
        $this->assertFalse($elsewhere->remembered);
        $this->assertNull($elsewhere->state);
        $this->assertSame([], $elsewhere->fields);

        // And the anonymous shell never reads it, even in a session that has it.
        $this->assertFalse(self::request($categoryid, [], [$fieldid], true)->remembered);
    }

    /**
     * An address that names anything of the state is read as written: the address wins, whole.
     */
    public function test_an_address_that_names_the_state_wins_over_the_remembered_one(): void {
        $this->resetAfterTest();
        $fieldid = self::filter_field();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        branding::save($categoryid, (object) ['defaultsort' => criteria::SORT_RECENT]);
        $this->setUser(self::getDataGenerator()->create_user());
        remembered::record($categoryid, [
            remembered::KEY_SORT => criteria::SORT_NAME,
            remembered::KEY_STATE => 'open',
            remembered::KEY_FIELDS => [$fieldid => 2],
        ]);

        // The organisation named, no filter: the clear link's shape. The remembered filters stay out.
        $named = self::request($categoryid, ['sort' => criteria::SORT_RECENT], [$fieldid]);
        $this->assertSame(criteria::SORT_RECENT, $named->sort);
        $this->assertNull($named->state);
        $this->assertSame([], $named->fields);
        $this->assertTrue($named->remembered, 'The reader still has a state here, so addresses name the organisation.');
        $this->assertSame(['sort' => criteria::SORT_RECENT], $named->sort_params(), 'The default too, while remembered.');

        // A filter named and no organisation: the category's default, not the remembered one.
        $filtered = self::request($categoryid, ['cf_' . $fieldid => '3'], [$fieldid]);
        $this->assertSame(criteria::SORT_RECENT, $filtered->sort);
        $this->assertSame([$fieldid => 3], $filtered->fields);
        $this->assertNull($filtered->state);

        // A term, a period, a panel: each makes the address one to read as written.
        foreach ([['q' => 'resgate'], ['from' => '2026-01-01'], ['open' => '12'], ['chip' => 'free']] as $get) {
            $request = self::request($categoryid, $get, [$fieldid]);
            $this->assertSame([], $request->fields, 'Named ' . key($get) . ': no remembered field.');
            $this->assertNotSame(criteria::SORT_NAME, $request->sort, 'Named ' . key($get) . ': no remembered organisation.');
        }

        // The grouped organisation, named while remembered, travels as sort=category.
        $grouped = self::request($categoryid, ['sort' => criteria::SORT_CATEGORY], [$fieldid]);
        $this->assertSame(['sort' => criteria::SORT_CATEGORY], $grouped->sort_params());
        $this->assertSame(['sort' => criteria::SORT_CATEGORY], (new criteria($categoryid, ['remembered' => true]))->sort_params());
    }

    /**
     * The view: the address's, else the one this category was left in, else the last one chosen anywhere, else cards.
     */
    public function test_the_view_is_remembered_per_category_with_the_last_one_as_the_fallback(): void {
        $this->resetAfterTest();
        $one = (int) self::getDataGenerator()->create_category()->id;
        $two = (int) self::getDataGenerator()->create_category()->id;
        $this->setUser(self::getDataGenerator()->create_user());

        $this->assertSame(category_toolbar::VIEW_CARDS, category_listing::view('', $one), 'Control: nothing chosen is cards.');

        // A view named in the address is this category's and the last one chosen.
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view(category_toolbar::VIEW_LIST, $one));
        $this->assertSame([remembered::KEY_VIEW => category_toolbar::VIEW_LIST], remembered::for_category($one));
        $this->assertSame(category_toolbar::VIEW_LIST, get_user_preferences(category_listing::PREFERENCE_VIEW));
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view('', $two), 'A category with no view of its own.');

        // The last view chosen anywhere moves on; the first category keeps its own.
        category_listing::view(category_toolbar::VIEW_CARDS, $two);
        $this->assertSame(category_toolbar::VIEW_LIST, category_listing::view('', $one));
        $this->assertSame(category_toolbar::VIEW_CARDS, category_listing::view('', $two));
        $this->assertSame(category_toolbar::VIEW_CARDS, get_user_preferences(category_listing::PREFERENCE_VIEW));
    }
}
