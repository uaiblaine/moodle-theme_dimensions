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
 * Theme Dimensions - Tests of the service that remembers a category's state
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\external;

use core_external\external_api;
use local_unlistedcourses\category_discoverability;
use PHPUnit\Framework\Attributes\CoversClass;
use theme_dimensions\local\category\classifier;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\facets;
use theme_dimensions\local\category\remembered;
use theme_dimensions\local\category\rows;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../local/category/fixture.php');

/**
 * Tests of the remembering service: what it stores, what it clears, and whom it refuses alike.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(remember_category_state::class)]
#[CoversClass(rows::class)]
final class remember_category_state_test extends \advanced_testcase {
    use \theme_dimensions\local\category\fixture;

    /** @var string The registered name of the function under test. */
    private const FUNCTION = 'theme_dimensions_remember_category_state';

    /**
     * Call the function the way the client does.
     *
     * @param array $args The arguments.
     * @return array The framework's response.
     */
    private function call(array $args): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::FUNCTION, $args, false);
    }

    /**
     * A select field on the site's filter list, with two options.
     *
     * @return int The field id.
     */
    private static function filter_field(): int {
        $generator = self::getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $generator->create_category(['component' => 'core_course', 'area' => 'course', 'itemid' => 0]);
        $field = $generator->create_field([
            'categoryid' => $category->get('id'),
            'type' => 'select',
            'shortname' => 'turno',
            'name' => 'Turno',
            'configdata' => ['options' => "Manhã\nTarde", 'defaultvalue' => ''],
        ]);
        set_config(facets::SETTING, 'turno', 'theme_dimensions');
        return (int) $field->get('id');
    }

    /**
     * The client's whole state is stored for the category, and an empty filter set clears the remembered filters.
     */
    public function test_the_state_is_stored_and_clearing_the_filters_clears_them(): void {
        $this->resetAfterTest();
        $fieldid = self::filter_field();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->become(self::getDataGenerator()->create_user());

        $response = $this->call([
            'categoryid' => $categoryid,
            'sort' => criteria::SORT_NAME,
            'view' => 'list',
            'chip' => 'open',
            'fav' => true,
            'filters' => [['fieldid' => $fieldid, 'value' => 2]],
        ]);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertTrue($response['data']['remembered']);
        $this->assertSame(
            ['s' => 'name', 'v' => 'list', 't' => 'open', 'fav' => true, 'f' => [$fieldid => 2]],
            remembered::for_category($categoryid)
        );

        // The clear control: the organisation and the view stay, the chip, the favourites and the fields go.
        $response = $this->call(['categoryid' => $categoryid, 'sort' => criteria::SORT_NAME]);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertSame(['s' => 'name', 'v' => 'list'], remembered::for_category($categoryid));

        // The theme's default organisation is remembered as the grouped one it is, and a bad value is dropped.
        $this->call(['categoryid' => $categoryid, 'sort' => '', 'chip' => 'nosuchchip', 'view' => 'grid']);
        $this->assertSame(['s' => criteria::SORT_CATEGORY, 'v' => 'list'], remembered::for_category($categoryid));
    }

    /**
     * "Group by" and "Sort by" by name are remembered in the entry's spelling, the grouped A-Z order included.
     *
     * The client sends the grouping with the order in sort; the entry keeps the organisation and, for the grouped
     * A-Z order alone, its order. A choice of the newest first, and then of no grouping, forgets the order; an
     * unknown sort without a grouping is refused and the entry keeps what it held, as before the grouping existed.
     *
     * Changes that must make it fail: the group parameter not declared or not read; the grouped A-Z order not stored,
     * or kept under a flat organisation; an unknown sort overwriting the remembered organisation.
     */
    public function test_the_grouping_and_its_order_are_remembered_in_the_entrys_spelling(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->become(self::getDataGenerator()->create_user());

        $this->assertFalse($this->call(['categoryid' => $categoryid, 'group' => 'category', 'sort' => 'name'])['error']);
        $this->assertSame(['s' => criteria::SORT_CATEGORY, 'p' => criteria::SORT_NAME], remembered::for_category($categoryid));
        $this->call(['categoryid' => $categoryid, 'group' => 'category', 'sort' => 'recent']);
        $this->assertSame(['s' => criteria::SORT_CATEGORY], remembered::for_category($categoryid), 'The newest first is no key.');
        $this->call(['categoryid' => $categoryid, 'group' => 'category', 'sort' => 'name']);
        $this->call(['categoryid' => $categoryid, 'group' => 'none', 'sort' => 'name']);
        $this->assertSame(['s' => criteria::SORT_NAME], remembered::for_category($categoryid), 'A flat order keeps none.');
        $this->call(['categoryid' => $categoryid, 'sort' => 'bogus']);
        $this->assertSame(['s' => criteria::SORT_NAME], remembered::for_category($categoryid), 'An unknown sort is refused.');
    }

    /**
     * A named grouping is held to the listing services' vocabulary, and a refused call remembers nothing.
     *
     * The known pair first is the control: it is stored, so the refusals that follow had an entry to overwrite.
     *
     * Changes that must make it fail: the service no longer calling rows::require_known_organisation() for a
     * named grouping, so an unknown grouping is read as none and an unknown order as the default.
     */
    public function test_an_unknown_grouping_or_order_of_a_named_grouping_is_refused(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->become(self::getDataGenerator()->create_user());

        $this->assertFalse($this->call(['categoryid' => $categoryid, 'group' => 'none', 'sort' => 'name'])['error']);
        $held = ['s' => criteria::SORT_NAME];
        $this->assertSame($held, remembered::for_category($categoryid));

        $refused = [
            ['group' => 'bogus', 'sort' => 'recent'],
            ['group' => 'category', 'sort' => 'bogus'],
            ['group' => 'none', 'sort' => 'category'],
        ];
        foreach ($refused as $pair) {
            $response = $this->call(['categoryid' => $categoryid] + $pair);
            $this->assertTrue($response['error'], json_encode($pair));
            $this->assertSame('invalidparameter', $response['exception']->errorcode, json_encode($pair));
            $this->assertSame($held, remembered::for_category($categoryid), json_encode($pair));
        }
    }

    /**
     * The favourites are a boolean of their own: the older chip spelling of them is never stored, and no
     * value outside the boolean reaches the function at all.
     *
     * fav=true is the control; the favourites sent as a chip are dropped like any chip outside the
     * vocabulary, so nothing turns the toggle on; and a value that is not a boolean is refused by the
     * framework before the function runs.
     */
    public function test_the_favourites_are_a_boolean_and_never_a_chip(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        $this->become(self::getDataGenerator()->create_user());

        $response = $this->call(['categoryid' => $categoryid, 'chip' => classifier::CHIP_FAVOURITES]);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertSame(
            ['s' => criteria::SORT_CATEGORY],
            remembered::for_category($categoryid),
            'The organisation is stored, and nothing of the favourites as a chip: neither a chip nor the toggle.'
        );

        $response = $this->call(['categoryid' => $categoryid, 'sort' => criteria::SORT_NAME, 'fav' => 'sometimes']);
        $this->assertTrue($response['error'], 'A value that is not a boolean is refused.');
        $this->assertSame('invalidparameter', $response['exception']->errorcode);
        $this->assertSame(['s' => criteria::SORT_CATEGORY], remembered::for_category($categoryid), 'Before anything is written.');

        $response = $this->call(['categoryid' => $categoryid, 'fav' => true]);
        $this->assertFalse($response['error'], $response['exception']->message ?? '');
        $this->assertSame(
            ['s' => criteria::SORT_CATEGORY, 'fav' => true],
            remembered::for_category($categoryid),
            'The control: the toggle is stored.'
        );
        $this->call(['categoryid' => $categoryid, 'fav' => false]);
        $this->assertSame(
            ['s' => criteria::SORT_CATEGORY],
            remembered::for_category($categoryid),
            'Switched off, the key is forgotten.'
        );
    }

    /**
     * Every category this reader may not discover is refused like one that does not exist, and so is the guest.
     */
    public function test_every_category_it_withholds_answers_alike(): void {
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $admitted = (int) $generator->create_category()->id;
        $hidden = (int) $generator->create_category(['visible' => 0])->id;
        $unlisted = (int) $generator->create_category()->id;
        $this->set_category_state($unlisted, category_discoverability::STATE_UNLISTED);
        $missing = $unlisted + 1000;

        $viewer = $generator->create_user();
        $this->become($viewer);
        $control = $this->call(['categoryid' => $admitted, 'sort' => criteria::SORT_NAME]);
        $this->assertFalse($control['error'], 'Control: an admitted category is remembered.');

        $refusals = [
            'a category id nothing holds' => $this->call(['categoryid' => $missing, 'sort' => criteria::SORT_NAME]),
            'a category core hides' => $this->call(['categoryid' => $hidden, 'sort' => criteria::SORT_NAME]),
            'a category the plugin withholds' => $this->call(['categoryid' => $unlisted, 'sort' => criteria::SORT_NAME]),
        ];
        $this->setGuestUser();
        self::forget();
        $refusals['the guest account'] = $this->call(['categoryid' => $admitted, 'sort' => criteria::SORT_NAME]);

        $first = reset($refusals)['exception'];
        foreach ($refusals as $case => $response) {
            $this->assertTrue($response['error'], $case . ' was not refused.');
            $this->assertSame($first->errorcode, $response['exception']->errorcode, $case . ' answered differently.');
            $this->assertSame($first->message, $response['exception']->message, $case . ' answered differently.');
        }

        $this->become($viewer);
        $this->assertSame([$admitted], array_keys(remembered::map()), 'Nothing refused reached the map.');

        // The root is a shape refusal, alike for everybody: it remembers nothing.
        $root = $this->call(['categoryid' => 0, 'sort' => criteria::SORT_NAME]);
        $this->assertTrue($root['error']);
        $this->assertSame('invalidparameter', $root['exception']->errorcode);
    }

    /**
     * The listing's service answers the reader's own listing with addresses that name the organisation.
     */
    public function test_the_listing_service_names_the_organisation_for_a_remembered_category(): void {
        $this->resetAfterTest();
        $categoryid = (int) self::getDataGenerator()->create_category()->id;
        self::getDataGenerator()->create_course(['category' => $categoryid]);
        $this->become(self::getDataGenerator()->create_user());
        $category = \core_course_category::get($categoryid);

        // Control: nothing remembered, the default organisation is left out of the clear link.
        $answer = rows::fetch($category, ['query' => 'xx']);
        $this->assertStringNotContainsString('sort=', $answer['clearurl']);

        remembered::record($categoryid, [remembered::KEY_SORT => criteria::SORT_CATEGORY]);
        $answer = rows::fetch($category, ['query' => 'xx']);
        $this->assertStringContainsString('sort=' . criteria::SORT_CATEGORY, $answer['clearurl']);
    }
}
