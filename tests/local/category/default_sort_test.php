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
 * Theme Dimensions - Tests of a category's default organisation
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixture.php');

/**
 * Tests of a category's default organisation: what a bare address gets, and what every address names.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(criteria::class)]
#[CoversClass(branding::class)]
#[CoversClass(rows::class)]
final class default_sort_test extends \advanced_testcase {
    use fixture;

    /**
     * Leave no query parameter behind for the next test.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_GET[criteria::PARAM_SORT]);
        parent::tearDown();
    }

    /**
     * Store a category's default organisation through the editor's writer.
     *
     * @param int $categoryid The category.
     * @param string $sort One of criteria::SORTS.
     * @return void
     */
    private static function default_of(int $categoryid, string $sort): void {
        branding::save($categoryid, (object) ['defaultsort' => $sort]);
    }

    /**
     * The request the page builds from its own address.
     *
     * @param int $categoryid The category.
     * @param string|null $sort The sort the address names, null for none.
     * @return criteria
     */
    private static function request(int $categoryid, ?string $sort): criteria {
        unset($_GET[criteria::PARAM_SORT]);
        if ($sort !== null) {
            $_GET[criteria::PARAM_SORT] = $sort;
        }
        return criteria::from_request($categoryid, []);
    }

    /**
     * An address naming no organisation gets the category's default, and the address still names nothing.
     *
     * The category without a default is the control: its bare address is the grouped one.
     */
    public function test_an_address_naming_no_organisation_gets_the_categorys_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $flat = (int) $generator->create_category()->id;
        $plain = (int) $generator->create_category()->id;
        self::default_of($flat, criteria::SORT_NAME);

        $this->assertSame(criteria::SORT_NAME, criteria::default_sort($flat), 'The seam reads the stored default.');
        $this->assertSame('', criteria::default_sort($plain), 'A category that set none reads the theme\'s.');
        $this->assertSame('', criteria::default_sort(0), 'The root has no default of its own.');

        $bare = self::request($flat, null);
        $this->assertSame(criteria::SORT_NAME, $bare->sort, 'The bare address is not the category\'s default.');
        $this->assertFalse($bare->is_grouped());
        $this->assertSame([], $bare->sort_params(), 'The bare address must stay bare in what it mints.');

        $control = self::request($plain, null);
        $this->assertSame('', $control->sort);
        $this->assertTrue($control->is_grouped());
        $this->assertSame([], $control->sort_params());
    }

    /**
     * Every organisation round-trips against the category's default: the default may be left out, the rest is named.
     *
     * The grouped organisation is the case a theme-wide rule would get wrong: on a category whose
     * default is flat, "By area" must travel as sort=category, or the next address the page mints
     * takes the reader back to the flat order. Each minted parameter set is fed back as an address
     * and must ask for the same organisation.
     */
    public function test_every_organisation_round_trips_against_the_categorys_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $flat = (int) $generator->create_category()->id;
        $plain = (int) $generator->create_category()->id;
        self::default_of($flat, criteria::SORT_RECENT);

        $expected = [
            [$flat, criteria::SORT_CATEGORY, [criteria::PARAM_SORT => criteria::SORT_CATEGORY]],
            [$flat, criteria::SORT_NAME, [criteria::PARAM_SORT => criteria::SORT_NAME]],
            [$flat, criteria::SORT_RECENT, []],
            [$plain, criteria::SORT_CATEGORY, [criteria::PARAM_SORT => criteria::SORT_CATEGORY]],
            [$plain, criteria::SORT_NAME, [criteria::PARAM_SORT => criteria::SORT_NAME]],
            [$plain, criteria::SORT_RECENT, [criteria::PARAM_SORT => criteria::SORT_RECENT]],
        ];
        foreach ($expected as [$categoryid, $sort, $params]) {
            $asked = self::request($categoryid, $sort);
            $label = $categoryid === $flat ? 'flat default, ' . $sort : 'theme default, ' . $sort;
            $this->assertSame($sort, $asked->sort, $label);
            $this->assertSame($params, $asked->sort_params(), $label);
            $this->assertSame($params, array_intersect_key($asked->keep_params(), $params), 'Clear filters: ' . $label);
            $again = self::request($categoryid, $params[criteria::PARAM_SORT] ?? null);
            $this->assertSame($sort, $again->sort, 'The minted address asks for another organisation: ' . $label);
            $this->assertSame($asked->is_grouped(), $again->is_grouped(), $label);
        }
    }

    /**
     * The default orders the page, and a grouped default without subcategories changes nothing.
     *
     * A flat default puts the courses in its order with no poster; a category whose stored default
     * is "By area" and that has no subcategory answers exactly as a category with no default does.
     */
    public function test_the_default_orders_the_page_and_a_grouped_default_changes_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $sorted = (int) $generator->create_category()->id;
        $child = (int) $generator->create_category(['parent' => $sorted])->id;
        $zeta = (int) $generator->create_course(['category' => $sorted, 'fullname' => 'Zeta'])->id;
        $alpha = (int) $generator->create_course(['category' => $sorted, 'fullname' => 'Alpha'])->id;
        $mid = (int) $generator->create_course(['category' => $child, 'fullname' => 'Mid'])->id;
        self::default_of($sorted, criteria::SORT_NAME);

        $page = population::resolve(self::request($sorted, null));
        $this->assertSame([$alpha, $mid, $zeta], $page->ids(), 'A-Z over the whole subtree, as the flat order lists.');
        $this->assertSame([], $page->posters, 'A flat default draws no poster.');
        $grouped = population::resolve(self::request($sorted, criteria::SORT_CATEGORY));
        $this->assertNotEmpty($grouped->posters, 'The control: the same category asked for by area draws its posters.');

        $leaf = (int) $generator->create_category()->id;
        $other = (int) $generator->create_category()->id;
        foreach ([$leaf, $other] as $categoryid) {
            $generator->create_course(['category' => $categoryid, 'fullname' => 'Beta']);
            $generator->create_course(['category' => $categoryid, 'fullname' => 'Gamma']);
        }
        self::default_of($leaf, criteria::SORT_CATEGORY);
        $withdefault = self::request($leaf, null);
        $without = self::request($other, null);
        $this->assertTrue($withdefault->is_grouped());
        $this->assertSame([], $withdefault->sort_params(), 'The stored grouped default still mints a bare address.');
        $one = population::resolve($withdefault);
        $two = population::resolve($without);
        $this->assertSame(
            array_map(static fn(int $id): string => get_course($id)->fullname, $two->ids()),
            array_map(static fn(int $id): string => get_course($id)->fullname, $one->ids()),
            'A grouped default without subcategories lists like a category with no default.'
        );
        $this->assertSame($two->posters, $one->posters);
        $this->assertSame($two->total, $one->total);
    }

    /**
     * The listing's web service answers a request naming no organisation with the category's default.
     *
     * The client sends the organisation in force, so this is the address's rule kept for any
     * other caller; and a panel, which names its flat order, is never reorganised by the area's
     * own default. The page's own payload carries the default for the client to compare with.
     */
    public function test_the_web_service_and_the_payload_carry_the_default(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $sorted = $generator->create_category();
        $alpha = (int) $generator->create_course(['category' => $sorted->id, 'fullname' => 'Alpha'])->id;
        $zeta = (int) $generator->create_course(['category' => $sorted->id, 'fullname' => 'Zeta'])->id;
        self::default_of((int) $sorted->id, criteria::SORT_NAME);
        $category = \core_course_category::get((int) $sorted->id);

        $answer = rows::fetch($category, ['sort' => '']);
        $this->assertSame([$alpha, $zeta], array_column($answer['cards'], 'id'), 'The web service ignored the default.');
        $this->assertStringNotContainsString('sort=', $answer['clearurl'], 'Its clear link names the default.');

        $recent = rows::fetch($category, ['sort' => criteria::SORT_RECENT]);
        $this->assertSame([$zeta, $alpha], array_column($recent['cards'], 'id'), 'A named order is not the default.');
        $this->assertStringContainsString('sort=recent', $recent['clearurl']);

        $request = self::request((int) $sorted->id, null);
        $listing = new \theme_dimensions\output\category_listing(
            $category,
            $request,
            population::resolve($request),
            \theme_dimensions\output\category_toolbar::VIEW_CARDS
        );
        $export = $listing->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame(criteria::SORT_NAME, json_decode($export->vitrine)->defaultsort);
    }
}
