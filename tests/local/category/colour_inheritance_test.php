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
 * Theme Dimensions - Tests of the colour pair a category inherits
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
 * Tests of the colour pair a category inherits: nearest ancestor, whole pair, one statement, a cache that follows.
 *
 * Every pair here is distinct from the theme's default and from every other pair of the same test,
 * so an assertion naming one can only pass against the category it names.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(branding::class)]
#[CoversClass(population::class)]
final class colour_inheritance_test extends \advanced_testcase {
    use fixture;

    /** @var string[] A wine pair, AA on its own. */
    private const WINE = ['#7a1f2b', '#ffffff'];

    /** @var string[] A forest pair, AA on its own. */
    private const FOREST = ['#1e4d2b', '#fff8e7'];

    /** @var string[] A pale pair with a dark ink, AA on its own. */
    private const SAND = ['#f2e3c6', '#2b1d0e'];

    /**
     * Give a category a pair of its own, as the editor does with the box ticked.
     *
     * @param int $categoryid The category.
     * @param string[] $pair [background, ink].
     * @return void
     */
    private static function own(int $categoryid, array $pair): void {
        branding::save($categoryid, (object) [
            'owncolours' => 1,
            'bgcolour' => $pair[0],
            'inkcolour' => $pair[1],
        ]);
    }

    /**
     * The resolved pair of a category, as the hero and the cards read it.
     *
     * @param int $categoryid The category.
     * @return string[] [background, ink].
     */
    private static function pair(int $categoryid): array {
        $payload = branding::for_category($categoryid);
        return [$payload['bgcolour'], $payload['inkcolour']];
    }

    /**
     * A category without a pair takes its nearest ancestor's; a top-level one with no ancestor takes the theme's.
     *
     * The middle category has no row at all and the grandchild has a row WITHOUT colours of its own
     * (saved for its tagline, as most rows are), so both ways of "having none" are asked. The
     * sibling tree is the control: nothing above it has a pair, so it reads the theme's.
     */
    public function test_a_category_without_colours_takes_its_nearest_ancestors_pair(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = (int) $generator->create_category()->id;
        $middle = (int) $generator->create_category(['parent' => $top])->id;
        $bottom = (int) $generator->create_category(['parent' => $middle])->id;
        $other = (int) $generator->create_category()->id;
        self::own($top, self::WINE);
        branding::save($bottom, (object) ['owncolours' => 0, 'tagline' => 'Uma frase']);

        $this->assertSame(self::WINE, self::pair($top), 'The category with the pair draws it.');
        $this->assertSame(self::WINE, self::pair($middle), 'A category with no row inherits.');
        $this->assertSame(self::WINE, self::pair($bottom), 'A category whose row has no pair inherits two levels.');
        $this->assertSame($top, branding::for_category($bottom)['colourfrom']);
        $this->assertSame(0, branding::for_category($bottom)['owncolours']);
        $this->assertSame($top, branding::for_category($top)['colourfrom'], 'A pair of its own names itself.');
        $this->assertSame(1, branding::for_category($top)['owncolours']);
        $this->assertSame([branding::DEFAULT_BG, branding::DEFAULT_INK], self::pair($other));
        $this->assertSame(0, branding::for_category($other)['colourfrom'], 'The theme\'s pair names no category.');
    }

    /**
     * The most specific pair wins: a subcategory's own pair overrides its parent's for itself and below.
     */
    public function test_the_nearest_pair_wins_over_a_farther_one(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = (int) $generator->create_category()->id;
        $middle = (int) $generator->create_category(['parent' => $top])->id;
        $bottom = (int) $generator->create_category(['parent' => $middle])->id;
        self::own($top, self::WINE);
        self::own($middle, self::FOREST);

        $this->assertSame(self::FOREST, self::pair($bottom), 'The grandchild reads its parent, not its grandparent.');
        $this->assertSame($middle, branding::for_category($bottom)['colourfrom']);
        $this->assertSame(self::FOREST, self::pair($middle));
        $this->assertSame(self::WINE, self::pair($top), 'And the override reaches nothing above it.');
        // The batched reader answers the same as the single one, for the whole family at once.
        $all = branding::colours([$top, $middle, $bottom]);
        $this->assertSame($top, $all[$top]['from']);
        $this->assertSame($middle, $all[$middle]['from']);
        $this->assertSame($middle, $all[$bottom]['from']);
    }

    /**
     * The pair travels whole: never a background from one category and an ink from another.
     *
     * Two ways it could mix. A row WITHOUT a pair of its own still stores two colours (the
     * default pair on a save, anything at all on a raw row), and they must not be read: the
     * grandchild reads its grandparent's pair, not the leftovers of the row between. And a pair of
     * its own whose background does not parse reads that half as the theme's default - the record's
     * own rule - and never borrows the parent's background beside its own ink.
     */
    public function test_the_pair_is_taken_whole_from_one_category(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = (int) $generator->create_category()->id;
        $middle = (int) $generator->create_category(['parent' => $top])->id;
        $bottom = (int) $generator->create_category(['parent' => $middle])->id;
        $broken = (int) $generator->create_category(['parent' => $top])->id;
        self::own($top, self::WINE);
        // A row between them without a pair of its own, holding colours nothing may read.
        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => $middle,
            'layout' => branding::LAYOUT_SOLID,
            'bgcolour' => self::SAND[0],
            'inkcolour' => self::SAND[1],
            'owncolours' => 0,
            'overlay' => branding::DEFAULT_OVERLAY,
            'tagline' => '',
            'defaultsort' => '',
        ]);
        // A pair of its own with a background no save could have written.
        $DB->insert_record(branding::TABLE, (object) [
            'categoryid' => $broken,
            'layout' => branding::LAYOUT_SOLID,
            'bgcolour' => 'banana',
            'inkcolour' => '#0a0a0a',
            'owncolours' => 1,
            'overlay' => branding::DEFAULT_OVERLAY,
            'tagline' => '',
            'defaultsort' => '',
        ]);
        branding::forget_all();

        $this->assertSame(self::WINE, self::pair($middle), 'A row without a pair of its own is not read for colours.');
        $this->assertSame(self::WINE, self::pair($bottom));
        $this->assertSame($top, branding::for_category($bottom)['colourfrom']);
        $this->assertSame(
            [branding::DEFAULT_BG, '#0a0a0a'],
            self::pair($broken),
            'A half that does not parse is the default\'s half, never the parent\'s.'
        );
        $this->assertSame($broken, branding::for_category($broken)['colourfrom']);
    }

    /**
     * Saving an ancestor's pair reaches a descendant whose resolved pair was already cached.
     *
     * The descendant is read first, so its entry holds the old pair; the save writes the ANCESTOR's
     * row, which names nothing about the descendant.
     */
    public function test_saving_an_ancestor_reaches_a_cached_descendant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = (int) $generator->create_category()->id;
        $child = (int) $generator->create_category(['parent' => $top])->id;
        self::own($top, self::WINE);
        $this->assertSame(self::WINE, self::pair($child), 'Precondition: the child\'s entry is cached with the old pair.');

        self::own($top, self::FOREST);

        $this->assertSame(self::FOREST, self::pair($child), 'The child kept the pair its parent no longer has.');
    }

    /**
     * Deleting an ancestor's row reaches a descendant whose resolved pair was already cached.
     */
    public function test_deleting_an_ancestors_row_reaches_a_cached_descendant(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $top = (int) $generator->create_category()->id;
        $child = (int) $generator->create_category(['parent' => $top])->id;
        self::own($top, self::WINE);
        $this->assertSame(self::WINE, self::pair($child), 'Precondition: the child\'s entry is cached with the pair.');

        branding::delete_for_category($top);

        $this->assertSame([branding::DEFAULT_BG, branding::DEFAULT_INK], self::pair($child));
        $this->assertSame(0, branding::for_category($child)['colourfrom']);
    }

    /**
     * Moving a category under another parent changes its cached pair and its descendants'.
     *
     * Through core's own move, change_parent(), whose course_category_updated event is the only
     * thing that reaches the theme: nothing of the theme runs on a move.
     */
    public function test_moving_a_category_reaches_its_cached_pair_and_its_descendants(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $wine = (int) $generator->create_category()->id;
        $forest = (int) $generator->create_category()->id;
        $moved = $generator->create_category(['parent' => $wine]);
        $below = (int) $generator->create_category(['parent' => $moved->id])->id;
        self::own($wine, self::WINE);
        self::own($forest, self::FOREST);
        $this->assertSame(self::WINE, self::pair((int) $moved->id), 'Precondition: cached under the first parent.');
        $this->assertSame(self::WINE, self::pair($below), 'Precondition: the grandchild is cached too.');

        \core_course_category::get((int) $moved->id)->change_parent($forest);

        $this->assertSame(self::FOREST, self::pair((int) $moved->id), 'The moved category kept its old parent\'s pair.');
        $this->assertSame(self::FOREST, self::pair($below), 'Its descendant kept the old parent\'s pair.');
        $this->assertSame($forest, branding::for_category($below)['colourfrom']);
    }

    /**
     * A grid's colours cost one statement for the whole grid, whatever its size.
     *
     * Two measurements. colours() alone is one read for two categories and for eight. And a cold
     * for_categories() over eight cards costs exactly seven reads fewer than the same eight cards
     * asked for one at a time, each a cold miss of its own: the per-card reads (the row and the
     * file areas) are the same in both, so the difference is the colour lookup, paid once for the
     * grid and once per card for the singles. A lookup per card makes the two equal.
     */
    public function test_a_grid_resolves_its_colours_in_one_statement(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $inheriting = (int) $generator->create_category()->id;
        self::own($inheriting, self::WINE);
        $heirs = [];
        for ($i = 0; $i < 8; $i++) {
            $heirs[] = (int) $generator->create_category(['parent' => $inheriting])->id;
        }

        foreach ([2, 8] as $size) {
            $before = (int) $DB->perf_get_reads();
            $colours = branding::colours(array_slice($heirs, 0, $size));
            $this->assertSame(1, (int) $DB->perf_get_reads() - $before, 'colours() is not one statement at ' . $size);
            $this->assertSame($inheriting, $colours[$heirs[0]]['from']);
        }

        // A cold cache each time; reads() empties the context cache too, alike for both ways of asking.
        branding::forget_all();
        $grid = $this->reads(static fn() => branding::for_categories($heirs));
        branding::forget_all();
        $singles = $this->reads(static function () use ($heirs): void {
            foreach ($heirs as $id) {
                branding::for_category($id);
            }
        });
        $this->assertSame(
            count($heirs) - 1,
            $singles - $grid,
            'The grid did not resolve its eight cards\' colours in one statement: a per-card lookup.'
        );
        $payloads = branding::for_categories($heirs);
        foreach ($heirs as $id) {
            $this->assertSame(self::WINE, [$payloads[$id]['bgcolour'], $payloads[$id]['inkcolour']]);
        }
    }

    /**
     * The cards and the strip of a page draw the inherited pair: the posters, the directory, the dots.
     *
     * One parent with a pair and two children without one: the posters of the parent's page and the
     * root's directory card of the parent each carry the pair in their style, and a panel's strip
     * carries it as the dot. The sibling with a pair of its own is the control.
     */
    public function test_the_cards_and_the_strip_draw_the_inherited_pair(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = self::getDataGenerator();
        $parent = $generator->create_category(['name' => 'Parent']);
        $heir = $generator->create_category(['parent' => $parent->id, 'name' => 'Heir']);
        $owner = $generator->create_category(['parent' => $parent->id, 'name' => 'Owner']);
        $generator->create_course(['category' => $heir->id]);
        $generator->create_course(['category' => $owner->id]);
        self::own((int) $parent->id, self::WINE);
        self::own((int) $owner->id, self::FOREST);

        $page = population::resolve(new criteria((int) $parent->id, ['withposters' => true]));
        $styles = array_column($page->posters, 'style', 'name');
        $this->assertStringStartsWith(
            '--dim-card-band: ' . self::WINE[0] . '; --dim-card-ink: ' . self::WINE[1] . ';',
            $styles['Heir']
        );
        $this->assertStringStartsWith('--dim-card-band: ' . self::FOREST[0] . ';', $styles['Owner']);

        $root = population::resolve(new criteria(0));
        $cards = array_column($root->directory, 'style', 'name');
        $this->assertStringStartsWith('--dim-card-band: ' . self::WINE[0] . ';', $cards['Parent']);

        $panel = population::resolve(new criteria((int) $parent->id, [
            'sort' => criteria::SORT_RECENT,
            'withcategories' => true,
        ]));
        $dots = array_column($panel->subareas, 'style', 'name');
        $this->assertSame('--dim-dot: ' . self::WINE[0] . ';', $dots['Heir']);
        $this->assertSame('--dim-dot: ' . self::FOREST[0] . ';', $dots['Owner']);
    }
}
