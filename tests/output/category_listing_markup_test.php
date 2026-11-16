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
 * Theme Dimensions - Tests for the rendered markup of the category showcase's templates
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\output;

use core_course_category;
use PHPUnit\Framework\Attributes\CoversNothing;
use theme_dimensions\local\category\criteria;
use theme_dimensions\local\category\population;

/**
 * Pins the markup of the showcase: the tree, the areas and their panel, the toolbar and the directory.
 *
 * Each template is rendered through the real engine, mostly from its own example context,
 * so what is parsed here is what a browser receives. The export behind them is
 * {@see category_listing}'s and is tested in `category_listing_test`; this file is about
 * the shapes the page has to keep, which no static check reads:
 *
 * - The navigation is a tree of links inside `.dim-vitrine__tree`, the region the Behat
 *   scenarios scope to, every entry a link and the current category marked.
 * - Depth travels as a number on the item, which the stylesheet indents by; a name carries
 *   no indentation characters.
 * - The grouped organisation draws the areas - one poster per child and one panel slot
 *   between their rows - and then the courses sitting directly in the category; an open
 *   panel is drawn by the server right after its poster, placed by integers alone, and every
 *   control of it is a link without JavaScript.
 * - The filter disclosure is a native details holding every control that narrows the
 *   listing, the two date fields and the quick options among them, and its no-JavaScript
 *   submit.
 * - The directory is a search line and a poster per category, and nothing else of the
 *   listing: no filters, no organisation, no pager. The root has no course of its own to
 *   filter or to page.
 * - Under a criterion a poster wears its pill and describes its control with it, a zero
 *   poster leaves its grid by the hidden attribute on its item, and the zero areas are named
 *   in a closed summary; a zero panel suggests the other areas as links, or as buttons in
 *   the client's copy.
 * - A poster's colours travel in one style attribute, a CSS context Mustache's escaping does
 *   not protect, so what is asserted is the whole vocabulary of that attribute.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class category_listing_markup_test extends \advanced_testcase {
    /** @var string U+2003, the em space: depth is drawn by the stylesheet, never by this character in a name. */
    private const INDENT = "\u{2003}";

    /**
     * The example context of a template of this theme, decoded.
     *
     * @param string $name The template name without the component.
     * @return array
     */
    private function example_context(string $name): array {
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/' . $name . '.mustache');
        $found = preg_match('/Example context \(json\):\s*(.*?)\n\}\}/s', $source, $matches);
        $this->assertSame(1, $found, "No example context in {$name}.");
        $context = json_decode($matches[1], true);
        $this->assertIsArray($context, "The example context of {$name} is not valid JSON.");
        return $context;
    }

    /**
     * Render a template of this theme from its example context and parse the result.
     *
     * @param string $name The template name without the component.
     * @return \DOMXPath
     */
    private function render(string $name): \DOMXPath {
        return $this->render_context($name, $this->example_context($name));
    }

    /**
     * Render a template of this theme from a context of the caller's own and parse the result.
     *
     * The example context is what almost every test here renders, because a template's own
     * example is the one context the mustache lint also validates. A test asserting what a
     * template does NOT draw for a given key needs the other side of that key, which no single
     * example can carry: the directory's example holds cards on both sides of the reveal's cut,
     * so the absence of the reveal under the cut can only be rendered from a context built for
     * it.
     *
     * @param string $name The template name without the component.
     * @param array $context The context to render.
     * @return \DOMXPath
     */
    private function render_context(string $name, array $context): \DOMXPath {
        global $PAGE;
        $PAGE->set_url('/course/index.php');
        $html = $PAGE->get_renderer('core')->render_from_template('theme_dimensions/' . $name, $context);
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return new \DOMXPath($document);
    }

    /**
     * An XPath predicate matching one class token.
     *
     * @param string $class The class name.
     * @return string
     */
    private static function has_class(string $class): string {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    /**
     * Whether a class attribute carries one class token, read in PHP rather than in XPath.
     *
     * @param string $attribute The whole class attribute.
     * @param string $class The token.
     * @return bool
     */
    private static function has_token(string $attribute, string $class): bool {
        return in_array($class, preg_split('/\s+/', trim($attribute)) ?: [], true);
    }

    /**
     * The tree is a list of filter links inside a collapsible box, the chosen entry marked.
     *
     * The entries are filters of this same page, and the list sits in a native disclosure a
     * phone can collapse. Both are asserted as markup here - which element carries which
     * attribute - while what the addresses hold and when the tree is drawn at all belong to
     * the exporter's tests in category_listing_test.
     */
    public function test_the_navigation_is_a_tree_of_links_with_the_current_category_marked(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_listing');

        $navs = $xpath->query('//nav[' . self::has_class('dim-vitrine__tree') . '][@data-region="dim-vitrine-tree"]');
        $this->assertSame(1, $navs->length, 'The region the scenarios scope to.');
        $nav = $navs->item(0);
        $this->assertNotSame('', $nav->getAttribute('aria-label'), 'The region names itself.');

        /* The disclosure the phone collapses: open in the markup, because a reader without the
           client module - or a wide screen, where the stylesheet hides the summary - must see the
           list, and the client is what closes it at a phone's width. */
        $boxes = $xpath->query('.//details[@data-region="dim-vitrine-treebox"]', $nav);
        $this->assertSame(1, $boxes->length, 'The tree sits inside its own disclosure.');
        $this->assertTrue($boxes->item(0)->hasAttribute('open'), 'Open in the document, closed by the client.');
        $summaries = $xpath->query('./summary', $boxes->item(0));
        $this->assertSame(1, $summaries->length, 'Which is opened and closed by a summary of its own.');
        $this->assertNotSame('', trim($summaries->item(0)->textContent), 'And the summary names what it holds.');
        $this->assertSame(
            1,
            $xpath->query('.//details[@data-region="dim-vitrine-treebox"]//ul[' . self::has_class('dim-tree') . ']', $nav)
                ->length,
            'The list is INSIDE the disclosure, or collapsing it would hide nothing.'
        );

        $entries = $this->example_context('category_listing')['tree'];
        $this->assertGreaterThan(2, count($entries), 'The example must carry the current category and two depths.');
        $items = $xpath->query('.//li[' . self::has_class('dim-tree__item') . ']', $nav);
        $this->assertSame(count($entries), $items->length, 'One item per entry, in order.');

        foreach ($entries as $index => $entry) {
            $item = $items->item($index);
            $this->assertSame(
                (string) $entry['depth'],
                $item->getAttribute('data-depth'),
                'Depth is a number the stylesheet indents by, never characters inside the name.'
            );
            $links = $xpath->query('.//a', $item);
            // Every entry is a filter of this page, so none is drawn as text.
            $this->assertSame(1, $links->length, 'Entry ' . $entry['name'] . ' is a link.');
            $this->assertSame($entry['url'], $links->item(0)->getAttribute('href'));
            $this->assertSame($entry['name'], $links->item(0)->textContent);
            $this->assertSame(
                (string) $entry['id'],
                $links->item(0)->getAttribute('data-categoryid'),
                'Each link names the category it filters by, which is what the client reads.'
            );
            // The value is "true" and not "page": the entry is the chosen filter of this page, not another page.
            $this->assertSame(
                empty($entry['current']) ? '' : 'true',
                $links->item(0)->getAttribute('aria-current'),
                'The chosen category says so, and no other does.'
            );
        }

        // No name carries em-space indentation.
        $this->assertStringNotContainsString(
            self::INDENT,
            $nav->textContent,
            'Depth is drawn by the stylesheet now; a name carries no indentation of its own.'
        );
    }

    /**
     * The grouped page is the areas, then the courses sitting directly here: posters, one empty panel slot, no accordion.
     *
     * The areas section comes first and holds the posters in a grid of their own - never the
     * direct grid, which the listing's module repaints with course cards - with the one
     * panel slot at its end, hidden and empty while nothing is open, the hint below it and the
     * status paragraph outside every grid. The direct courses follow in a section of their own,
     * whose heading is where the skip link beside the areas' heading lands.
     */
    public function test_the_grouped_page_is_the_areas_then_the_courses_sitting_directly_here(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_listing');
        $xpath = $this->render('category_listing');

        $areas = $xpath->query('//section[@data-region="dim-areas"]');
        $this->assertSame(1, $areas->length, 'One areas section.');
        $direct = $xpath->query('//section[@data-region="dim-direct"]');
        $this->assertSame(1, $direct->length, 'One section of the direct courses.');
        $this->assertSame(
            1,
            $xpath->query('//section[@data-region="dim-areas"]/following::section[@data-region="dim-direct"]')->length,
            'The areas come first, the direct courses after them.'
        );
        $this->assertSame(
            $context['areas']['heading'],
            trim($xpath->query('.//h2[@id="dim-areas-title"]', $areas->item(0))->item(0)->textContent)
        );
        $this->assertSame(
            $context['direct']['heading'],
            trim($xpath->query('.//h2[@id="dim-direct-title"]', $direct->item(0))->item(0)->textContent)
        );
        $skip = $xpath->query('.//a[@data-region="dim-direct-skip"]', $areas->item(0));
        $this->assertSame(1, $skip->length);
        $this->assertSame('#dim-direct-title', $skip->item(0)->getAttribute('href'), 'The skip link lands on that heading.');

        $grid = $xpath->query('./ul[@data-region="dim-areas-grid"]', $areas->item(0));
        $this->assertSame(1, $grid->length);
        $posters = $xpath->query('./li/article[@data-region="dim-area"]', $grid->item(0));
        $this->assertSame(count($context['areas']['posters']), $posters->length, 'One poster per area.');
        $slot = $xpath->query('./li[@data-region="dim-panel"]', $grid->item(0));
        $this->assertSame(1, $slot->length, 'One panel slot, in the posters grid.');
        $this->assertTrue($slot->item(0)->hasAttribute('hidden'), 'Hidden while nothing is open.');
        $this->assertSame('', trim($slot->item(0)->textContent), 'And empty: the client fills it.');
        $this->assertSame(0, $xpath->query('./following-sibling::li', $slot->item(0))->length, 'At the end of the grid.');
        $this->assertSame(1, $xpath->query('.//*[@data-region="dim-areas-hint"]', $areas->item(0))->length);
        $this->assertSame(
            0,
            $xpath->query('//*[@data-region="dim-areas-grid"]//*[@data-region="dim-vitrine-grid"]')->length,
            'The posters grid holds no direct grid.'
        );
        $this->assertSame(
            1,
            $xpath->query('.//ul[@data-region="dim-vitrine-grid"]', $direct->item(0))->length,
            'The direct grid is the direct section\'s.'
        );
        $absent = [
            'an accordion' => '//*[@data-region="dim-accordion"]',
            'an accordion panel' => '//details[@data-region="dim-accordion-panel"]',
            'an "expand all"' => '//*[@data-action="dim-expandall"]',
        ];
        foreach ($absent as $what => $query) {
            $this->assertSame(0, $xpath->query($query)->length, "The grouped page draws {$what}.");
        }

        // With nothing sitting directly here, that section is hidden, never removed.
        $this->assertFalse($skip->item(0)->hasAttribute('hidden'), 'Control: the link shows while the section does.');
        $none = $context;
        $none['direct']['show'] = false;
        $none['areas']['hasdirectlink'] = false;
        $hidden = $this->render_context('category_listing', $none);
        $section = $hidden->query('//section[@data-region="dim-direct"]');
        $this->assertSame(1, $section->length, 'Still in the document: the client repaints its grid.');
        $this->assertTrue($section->item(0)->hasAttribute('hidden'));
        $link = $hidden->query('//a[@data-region="dim-direct-skip"]');
        $this->assertSame(1, $link->length, 'The link to it is still written, so the client can offer it again.');
        $this->assertTrue($link->item(0)->hasAttribute('hidden'), 'And hidden with it.');
    }

    /**
     * An open panel is drawn right after its poster, placed by integers alone, with links for every control.
     *
     * The server's panel is the reader without JavaScript's panel - every anonymous visitor's -
     * so every control in it is a link: the two orders land on the panel, Close lands back on the
     * poster by its fragment, the open poster itself is the same address as Close. The five
     * integers that place its row and the open area's band are the style attribute's whole
     * vocabulary. Every poster carries the "Open" tag, which the stylesheet shows on the open one
     * only, hidden from assistive technology (the control's state says it).
     */
    public function test_an_open_panel_is_drawn_after_its_poster_and_placed_by_integers(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_areas');
        $xpath = $this->render('category_areas');
        $this->assertTrue($context['isopen'], 'Precondition: the example has a panel open.');
        $open = $context['posters'][0];
        $this->assertTrue($open['isopen']);

        $section = $xpath->query('//section[@data-region="dim-areas"]')->item(0);
        $this->assertStringContainsString('dim-areas--open', $section->getAttribute('class'), 'The spotlight class.');
        $poster = $xpath->query('//article[@data-region="dim-area"][@id="dim-area-' . $open['id'] . '"]');
        $this->assertSame(1, $poster->length);
        $this->assertStringContainsString('dim-poster--open', $poster->item(0)->getAttribute('class'));
        $tag = $xpath->query('.//span[@class="dim-poster__open"]', $poster->item(0));
        $this->assertSame(1, $tag->length, 'The open poster says so in words.');
        $this->assertSame('true', $tag->item(0)->getAttribute('aria-hidden'));
        $this->assertSame(get_string('category_poster_open', 'theme_dimensions'), trim($tag->item(0)->textContent));
        $this->assertSame(
            count($context['posters']) + count($context['postersmore']),
            $xpath->query('//span[@class="dim-poster__open"]')->length,
            'Every poster carries it, because the panel module moves the open class.'
        );
        $this->assertSame('li', $poster->item(0)->parentNode->nodeName, 'Each poster is the whole of a list item of the grid.');
        $next = $xpath->query('./following-sibling::li[1]', $poster->item(0)->parentNode)->item(0);
        $this->assertSame('dim-panel', $next->getAttribute('data-region'), 'The panel is the next item after its poster\'s.');
        $this->assertSame('dim-panel', $next->getAttribute('id'));
        $this->assertFalse($next->hasAttribute('hidden'));
        $this->assertSame(1, $xpath->query('//li[@data-region="dim-panel"]')->length, 'And there is one slot only.');
        $this->assertMatchesRegularExpression(
            '~^(--dim-(row|col)-[123]: \d+; )+--dim-area-bg: #[0-9a-f]{6};$~',
            $next->getAttribute('style')
        );
        $this->assertSame($context['panelstyle'], $next->getAttribute('style'));
        $this->assertSame(
            0,
            $xpath->query('//*[@data-region="dim-areas-hint"]')->length,
            'The hint goes once a panel is open.'
        );

        $region = $xpath->query('.//section[@data-region="dim-panel-section"][@role="region"]', $next);
        $this->assertSame(1, $region->length, 'The panel is a region labelled by its heading.');
        $this->assertSame(1, $xpath->query('.//h3[@id="dim-panel-title"]', $next)->length);
        $sort = $xpath->query('.//*[@data-region="dim-panel-sort"][@role="group"]/a', $next);
        $this->assertSame(2, $sort->length, 'Two orders, as links.');
        $this->assertSame($context['panel']['sorturlrecent'], $sort->item(0)->getAttribute('href'));
        $this->assertSame($context['panel']['sorturlname'], $sort->item(1)->getAttribute('href'));
        $this->assertSame('true', $sort->item(0)->getAttribute('aria-current'), 'The newest first is the order in force.');
        $this->assertFalse($sort->item(1)->hasAttribute('aria-current'));
        $close = $xpath->query('.//a[@data-action="dim-panel-close"]', $next);
        $this->assertSame(1, $close->length);
        $this->assertStringEndsWith('#dim-area-' . $open['id'], $close->item(0)->getAttribute('href'));
        $control = $xpath->query('.//a[@data-region="dim-poster-control"]', $poster->item(0))->item(0);
        $this->assertSame(
            $close->item(0)->getAttribute('href'),
            $control->getAttribute('href'),
            'The open poster and Close are one address: without JavaScript either closes the panel.'
        );
        $this->assertSame(
            get_string('category_card_close', 'theme_dimensions'),
            trim($xpath->query('.//*[@data-region="dim-poster-trigger"]', $poster->item(0))->item(0)->textContent),
            'And the open poster says it hides the courses.'
        );

        // A poster with nothing to open at rest is a link to its page, and says so to a screen reader.
        $empty = $context['posters'][1];
        $this->assertFalse($empty['expandable'], 'Precondition: the second poster has nothing to open.');
        $link = $xpath->query('//article[@id="dim-area-' . $empty['id'] . '"]//a[@data-region="dim-poster-control"]')->item(0);
        $this->assertSame($empty['url'], $link->getAttribute('href'));
        $this->assertSame(
            $empty['name'] . ' ' . get_string('category_card_nocourseslink', 'theme_dimensions'),
            trim(preg_replace('/\s+/u', ' ', $link->textContent)),
            'Its accessible name carries the state the chevron-less trigger cannot say.'
        );
        /* Whether a poster opens can change without a reload, so both states are written - the
           chevron, which the stylesheet hides on an empty poster, the empty word beside the other
           two on the trigger, and the note, hidden while the poster opens. */
        $article = $xpath->query('//article[@id="dim-area-' . $empty['id'] . '"]')->item(0);
        $this->assertTrue(self::has_token($article->getAttribute('class'), 'dim-poster--empty'), 'It is an empty poster.');
        $this->assertSame('0', $article->getAttribute('data-expandable'));
        $this->assertSame(1, $xpath->query('.//*[' . self::has_class('dim-poster__chev') . ']', $article)->length);
        $trigger = $xpath->query('.//*[@data-region="dim-poster-trigger"]', $article)->item(0);
        $this->assertSame(get_string('category_card_nocourses', 'theme_dimensions'), trim($trigger->textContent));
        $words = [
            'data-open' => 'category_card_open',
            'data-close' => 'category_card_close',
            'data-empty' => 'category_card_nocourses',
        ];
        foreach ($words as $name => $key) {
            $this->assertSame(
                get_string($key, 'theme_dimensions'),
                $trigger->getAttribute($name),
                $name . ' is written.'
            );
        }
        $note = $xpath->query('.//*[@data-region="dim-poster-emptynote"]', $article)->item(0);
        $this->assertFalse($note->hasAttribute('hidden'), 'Its note speaks while the poster is a link.');
        $opennote = $xpath->query('.//*[@data-region="dim-poster-emptynote"]', $poster->item(0))->item(0);
        $this->assertTrue($opennote->hasAttribute('hidden'), 'Control: the open poster writes the note, hidden.');
    }

    /**
     * An open poster past the slice draws the reveal open, with the panel inside the revealed grid.
     */
    public function test_an_open_poster_past_the_slice_opens_the_reveal(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_areas');
        $context['posters'][0]['isopen'] = false;
        $context['postersmore'][0]['isopen'] = true;
        $context['moreopen'] = true;
        $context['panelinmain'] = false;
        $context['panelinmore'] = true;
        $xpath = $this->render_context('category_areas', $context);

        $reveal = $xpath->query('//details[@data-region="dim-areas-more"]');
        $this->assertTrue($reveal->item(0)->hasAttribute('open'), 'The reveal holding the open poster is open.');
        $slot = $xpath->query(
            '//details[@data-region="dim-areas-more"]//ul[@data-region="dim-areas-grid"]/li[@data-region="dim-panel"]'
        );
        $this->assertSame(1, $slot->length, 'The panel is in the revealed grid.');
        $this->assertSame(1, $xpath->query('//li[@data-region="dim-panel"]')->length, 'And nowhere else.');

        $closed = $this->render('category_areas');
        $this->assertFalse(
            $closed->query('//details[@data-region="dim-areas-more"]')->item(0)->hasAttribute('open'),
            'The control: with the open poster in the main grid the reveal is closed.'
        );
    }

    /**
     * The toolbar is one line in a fixed slot order, the too-short notice, and the row under the line.
     *
     * The line is the Design System's compact bar: the scope, a spacer, the cluster - the result
     * count and a capsule of the star, the Filters disclosure, the Organise menu and the view
     * toggle - and the search's own capsule with the search button, the field and the managers'
     * menu; then the close button and the listbox, which only the client draws. The order in the
     * document is the order on screen and the order of the tab. Each group's legend is its visible
     * label, with no second spelling of it, save the scope's, which the platter's cells name and
     * the legend names for assistive technology alone.
     *
     * Changes that must make it fail: two slots swapped; a control moved out of its capsule; the
     * Filters word shown on the bar again; the Filters disclosure drawn open; the clear link drawn
     * with one criterion.
     */
    public function test_the_line_holds_its_slots_in_the_contracts_order(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_toolbar');

        $children = $xpath->query('//form[@data-region="dim-vitrine-toolbar"]/*[not(self::input)][not(self::button)]');
        $this->assertSame(
            ['dim-line', 'dim-toolbar-notice', 'dim-line-chips'],
            array_map(static fn(\DOMElement $node): string => $node->getAttribute('data-region'), iterator_to_array($children)),
            'The line, the too-short notice right after it, then the row under the line.'
        );

        $slots = static function (\DOMNodeList $nodes): array {
            $found = [];
            foreach ($nodes as $node) {
                if (self::has_token($node->getAttribute('class'), 'dim-toolbar__manage')) {
                    $found[] = 'manage';
                } else if (self::has_token($node->getAttribute('class'), 'dim-line__spacer')) {
                    $found[] = 'spacer';
                } else {
                    $found[] = $node->getAttribute('data-role')
                        ?: ($node->getAttribute('data-region') ?: $node->getAttribute('data-action'));
                }
            }
            return $found;
        };
        $this->assertSame(
            ['scope', 'spacer', 'dim-line-cluster', 'dim-line-searchcap', 'dim-line-suggest'],
            $slots($xpath->query('//div[@data-region="dim-line"]/*')),
            'Scope, spacer, the cluster and the search\'s capsule: the contract\'s order, then the listbox, which '
                . 'only the client draws.'
        );
        $this->assertSame(
            ['dim-line-total', 'dim-line-capsule'],
            $slots($xpath->query('//div[@data-region="dim-line-cluster"]/*')),
            'The result count, then the capsule, on the cluster\'s one ground.'
        );
        $this->assertSame(
            ['dim-line-star', 'dim-filters', 'dim-period', 'dim-organise', 'dim-view-row'],
            $slots($xpath->query('//div[@data-region="dim-line-capsule"]/*')),
            'The star, Filters, the date button right after it, Organise and the view toggle, in one capsule.'
        );
        $this->assertSame(
            ['dim-search-open', 'dim-line-search', 'manage', 'dim-search-close'],
            $slots($xpath->query('//div[@data-region="dim-line-searchcap"]/*')),
            'The search button, the field the server draws open for a page without the client, the managers\' menu and the '
                . 'button that closes the search, which only the client shows.'
        );
        foreach (['dim-line-capsule', 'dim-line-searchcap'] as $capsule) {
            $this->assertTrue(
                self::has_token(
                    $xpath->query('//div[@data-region="' . $capsule . '"]')->item(0)->getAttribute('class'),
                    'dim-line__capsule'
                ),
                "{$capsule} wears the capsule."
            );
        }

        // The scope's legend names the group for assistive technology; the cells are what the eye reads.
        $scope = $xpath->query('//fieldset[@data-role="scope"]/legend');
        $this->assertSame('visually-hidden', $scope->item(0)->getAttribute('class'));
        // Every other group names itself once, with a legend that is not hidden and no duplicate beside it.
        $legends = $xpath->query('//fieldset[' . self::has_class('dim-toolbar__group') . '][not(@data-role="scope")]/legend');
        $this->assertGreaterThan(2, $legends->length, 'The organisation, "More statuses", a facet and the dates.');
        foreach ($legends as $legend) {
            $this->assertStringNotContainsString('visually-hidden', $legend->getAttribute('class'));
            $this->assertNotSame('', trim($legend->textContent));
        }
        $this->assertSame(
            0,
            $xpath->query('//*[@aria-hidden="true"][' . self::has_class('dim-toolbar__grouplabel') . ']')->length,
            'The aria-hidden duplicate of the legend is gone.'
        );

        // Filters: the sliders, the word out of the picture, the badge; closed on load, whatever narrows the page.
        $panel = $xpath->query('//div[@data-region="dim-line-capsule"]/details[@data-region="dim-filters"]');
        $this->assertSame(1, $panel->length);
        $this->assertFalse($panel->item(0)->hasAttribute('open'), 'Closed on load: the chips row says what narrows.');
        $summary = $xpath->query('./summary', $panel->item(0))->item(0);
        $this->assertTrue(self::has_token($summary->getAttribute('class'), 'dim-line__iconbtn'), 'An icon button.');
        $this->assertSame(1, $xpath->query('./i[' . self::has_class('fa-sliders') . ']', $summary)->length);
        $word = $xpath->query('./span[' . self::has_class('dim-line__label') . ']', $summary)->item(0);
        $this->assertSame('Filters', $word->textContent, 'The word, in a span of its own: the summary\'s accessible name.');
        $this->assertTrue(self::has_token($word->getAttribute('class'), 'visually-hidden'), 'Out of the picture at every width.');
        $this->assertSame(1, $xpath->query('./*[@data-region="dim-filtercount"]', $summary)->length, 'And the badge.');
        $this->assertSame(0, $xpath->query('.//input[@type="date"]', $panel->item(0))->length, 'Option groups only.');
        $this->assertSame(
            2,
            $xpath->query('//details[@data-region="dim-period"]//input[@type="date"]')->length,
            'Two date fields in the date button\'s layer, which is what the client\'s single calendar writes into.'
        );
        $quick = $xpath->query('//details[@data-region="dim-period"]//ul[' . self::has_class('dim-toolbar__quick') . ']//a');
        $this->assertGreaterThan(0, $quick->length, 'The quick options are links, so they work with no script.');
        foreach ($quick as $link) {
            $this->assertStringContainsString('from=', $link->getAttribute('href'));
            $this->assertStringContainsString('to=', $link->getAttribute('href'));
        }

        // Organise: a menu whose summary is a named glyph, holding the sort and its Apply.
        $organise = $xpath->query('//div[@data-region="dim-line-capsule"]/details[@data-region="dim-organise"]');
        $this->assertFalse($organise->item(0)->hasAttribute('open'));
        $toggle = $xpath->query('./summary', $organise->item(0))->item(0);
        $word = $xpath->query('./span[' . self::has_class('visually-hidden') . ']', $toggle)->item(0);
        $this->assertSame('Organise', $word->textContent, 'Its name is a word out of the picture.');
        $this->assertSame(1, $xpath->query('./i[@aria-hidden="true"]', $toggle)->length);
        $this->assertSame(
            1,
            $xpath->query(
                './/fieldset[@data-region="dim-sort"][' . self::has_class('dim-toolbar__group--sort') . ']',
                $organise->item(0)
            )->length,
            'The organisation chips are in the menu, their fieldset named for the inline legend.'
        );
        $this->assertSame(0, $xpath->query('//*[@data-region="dim-showas"]')->length, 'The view toggle never moves into it.');

        // The view: icon buttons of the capsule, each named by a visually hidden word.
        foreach ($xpath->query('//*[@data-region="dim-view-row"]//button[@data-action="dim-view"]') as $button) {
            $this->assertTrue(self::has_token($button->getAttribute('class'), 'dim-line__iconbtn'));
            $labels = $xpath->query('./span[' . self::has_class('visually-hidden') . ']', $button);
            $this->assertSame(1, $labels->length, 'An icon-only button still says what it is.');
            $this->assertSame(1, $xpath->query('./i[' . self::has_class('fa') . ']', $button)->length);
        }

        // The open field: magnifier, field, and the eraser - a client's control, named, never a cross.
        $eraser = $xpath->query('//*[@data-region="dim-line-search"]/button[@data-action="dim-search-clear"]')->item(0);
        $this->assertSame('button', $eraser->getAttribute('type'), 'It never submits the form.');
        $this->assertSame('Clear text', $eraser->getAttribute('aria-label'));
        $this->assertTrue(self::has_token($eraser->getAttribute('class'), 'dim-live-only'), 'Either client reveals it.');
        $this->assertSame(1, $xpath->query('./i[' . self::has_class('fa-eraser') . ']', $eraser)->length);
        $this->assertFalse($eraser->hasAttribute('hidden'), 'The example holds a term, so it shows.');
        $context = $this->example_context('category_toolbar');
        $empty = $this->render_context('category_toolbar', ['query' => '', 'hasquery' => false] + $context);
        $this->assertTrue(
            $empty->query('//button[@data-action="dim-search-clear"]')->item(0)->hasAttribute('hidden'),
            'Control: on an empty field it waits hidden.'
        );

        /* The way out of every filter at once is a link in the row under the line, one of the page's
           dim-clear controls: a word with no glyph and none of Bootstrap's button classes. */
        $clear = $xpath->query('//div[@data-region="dim-line-chips"]/a[@data-region="dim-vitrine-clear"]');
        $this->assertSame(1, $clear->length);
        $this->assertSame('dim-toolbar__clear', $clear->item(0)->getAttribute('class'));
        $this->assertSame(0, $xpath->query('.//i', $clear->item(0))->length, 'No glyph before the words.');
        $this->assertSame($context['clearlabel'], trim($clear->item(0)->textContent), 'The words alone.');
        $this->assertSame('dim-clear', $clear->item(0)->getAttribute('data-action'));
        $this->assertFalse($clear->item(0)->hasAttribute('hidden'), 'Offered: the example narrows by several criteria.');
        $one = $this->render_context('category_toolbar', ['clearall' => false] + $context);
        $this->assertTrue(
            $one->query('//a[@data-region="dim-vitrine-clear"]')->item(0)->hasAttribute('hidden'),
            'Control: with one criterion its own control undoes it, and "Clear all" waits hidden.'
        );
    }

    /**
     * Every icon-only control of the bar is named, names itself in the bar's tooltip, and carries no title.
     *
     * The Design System's tooltip (D51) is the stylesheet's, drawn from data-dim-tip after its own delay;
     * a title would draw the browser's tooltip beside it after the browser's. The tooltip repeats the
     * control's name and never stands for it: the name is the control's aria-label or its visually
     * hidden word, the same words.
     *
     * Changes that must make it fail: a title put back on any control of the line; a control's
     * data-dim-tip dropped; a control named only by its tooltip.
     */
    public function test_every_icon_only_control_has_a_name_and_a_tip_and_no_title(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_toolbar');
        $line = $xpath->query('//div[@data-region="dim-line"]')->item(0);
        $this->assertSame(0, $xpath->query('.//*[@title]', $line)->length, 'No title anywhere on the line.');

        $controls = [
            'star' => './/button[@data-region="dim-line-star"]',
            'filters' => './/details[@data-region="dim-filters"]/summary',
            'date' => './/details[@data-region="dim-period"]/summary',
            'organise' => './/details[@data-region="dim-organise"]/summary',
            'list' => './/button[@data-action="dim-view"][@value="list"]',
            'cards' => './/button[@data-action="dim-view"][@value="cards"]',
            'search' => './/button[@data-action="dim-search-open"]',
            'eraser' => './/button[@data-action="dim-search-clear"]',
            'close' => './/button[@data-action="dim-search-close"]',
        ];
        $this->assertSame(count($controls), $xpath->query('.//*[@data-dim-tip]', $line)->length, 'Each of them, and nothing else.');
        foreach ($controls as $name => $query) {
            $control = $xpath->query($query, $line)->item(0);
            $this->assertNotNull($control, "The {$name} control is drawn.");
            $tip = $control->getAttribute('data-dim-tip');
            $this->assertNotSame('', $tip, "The {$name} control names itself in the tooltip.");
            $hidden = $xpath->query('./span[' . self::has_class('visually-hidden') . ']', $control)->item(0);
            $accessible = $control->getAttribute('aria-label') ?: ($hidden ? trim($hidden->textContent) : '');
            $this->assertNotSame('', $accessible, "The {$name} control has a name of its own.");
            $this->assertStringStartsWith($tip, $accessible, "The {$name} control's tooltip repeats its name.");
        }

        // Core's kebab is the managers' menu: the theme takes its title away and gives it the bar's tooltip.
        global $PAGE;
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $PAGE->set_url('/course/index.php', ['categoryid' => $category->id]);
        $PAGE->set_context(\core\context\coursecat::instance($category->id));
        $actions = new category_actions($PAGE, core_course_category::get($category->id));
        $menu = $actions->render_actions($PAGE->get_renderer('core'));
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>' . $menu . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $trigger = (new \DOMXPath($document))->query('//*[' . self::has_class('dropdown-toggle') . ']')->item(0);
        $this->assertNotNull($trigger, 'Precondition: an administrator gets the menu.');
        $this->assertFalse($trigger->hasAttribute('title'), 'Core\'s title goes.');
        $this->assertSame(get_string('moreactions'), $trigger->getAttribute('data-dim-tip'));
        $this->assertSame(
            get_string('moreactions'),
            trim((new \DOMXPath($document))->query('.//span[' . self::has_class('visually-hidden') . ']', $trigger)->item(0)
                ->textContent),
            'Its name stays core\'s visually hidden word.'
        );
    }

    /**
     * The bar's result count is the count sentence's own number, drawn by the server, out of the accessibility tree.
     *
     * Both come from the listing's countnumber, so a page without JavaScript shows the right number and a
     * screen reader is read it once, in the sentence.
     *
     * Changes that must make it fail: the count written from another key; the count read out beside the
     * sentence (aria-hidden dropped); the count moved off the cluster.
     */
    public function test_the_result_count_on_the_bar_is_the_sentences_number(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_toolbar');
        foreach ([$context['countnumber'], '1.234'] as $number) {
            $xpath = $this->render_context('category_toolbar', ['countnumber' => $number] + $context);
            $total = $xpath->query('//div[@data-region="dim-line-cluster"]/span[@data-region="dim-line-total"]');
            $this->assertSame(1, $total->length);
            $this->assertSame('true', $total->item(0)->getAttribute('aria-hidden'), 'The sentence is what is read.');
            $this->assertSame($number, $total->item(0)->textContent, 'The bare number.');
            $this->assertSame(
                $number,
                $xpath->query('//b[@data-region="dim-vitrine-countnumber"]')->item(0)->textContent,
                'The sentence\'s own number.'
            );
        }
    }

    /**
     * The Filters panel's foot: "Clear filters" and "Show {n}", core's small buttons, and "Filter" without JavaScript.
     *
     * "Clear filters" is the clear link by another name - its address, its action - offered while anything
     * narrows; "Show" is a plain button the client arms, its words the server's sentence for the count and
     * the same sentence with {n} where the client writes each answer's number.
     *
     * Changes that must make it fail: the foot moved out of the panel; "Clear filters" given another address
     * or action, or drawn at rest; "Show" made a submit, or drawn without its template; the no-JavaScript
     * "Filter" dropped.
     */
    public function test_the_filters_panel_foot_clears_and_shows(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_toolbar');
        $xpath = $this->render_context('category_toolbar', $context);
        $foot = $xpath->query('//div[@data-region="dim-filters-panel"]/*[last()][@data-region="dim-filters-foot"]');
        $this->assertSame(1, $foot->length, 'The panel\'s last block.');
        $this->assertSame(
            ['dim-filters-clear', 'dim-filters-show', 'dim-toolbar__submit dim-nojs-only'],
            array_map(
                static fn(\DOMElement $node): string => $node->getAttribute('data-region')
                    ?: ($node->getAttribute('data-action') ?: $node->getAttribute('class')),
                iterator_to_array($xpath->query('./*', $foot->item(0)))
            )
        );
        $clear = $xpath->query('./a[@data-region="dim-filters-clear"]', $foot->item(0))->item(0);
        $this->assertSame('btn btn-sm btn-outline-secondary dim-toolbar__footclear', $clear->getAttribute('class'));
        $this->assertSame($context['clearurl'], $clear->getAttribute('href'), 'The clear link\'s own address.');
        $this->assertSame('dim-clear', $clear->getAttribute('data-action'), 'And its action.');
        $this->assertSame(get_string('category_clearfilters', 'theme_dimensions'), trim($clear->textContent));
        $this->assertFalse($clear->hasAttribute('hidden'), 'Offered: the example narrows.');
        $show = $xpath->query('./button[@data-action="dim-filters-show"]', $foot->item(0))->item(0);
        $this->assertSame('button', $show->getAttribute('type'), 'It closes the panel; it submits nothing.');
        $this->assertSame('btn btn-sm btn-primary dim-toolbar__footshow dim-live-only', $show->getAttribute('class'));
        $this->assertSame($context['showlabel'], trim($show->textContent));
        $this->assertSame($context['showtemplate'], $show->getAttribute('data-dim-template'));
        $this->assertSame(
            'Show {n}',
            get_string('category_showcount', 'theme_dimensions', category_toolbar::SHOW_PLACEHOLDER),
            'The server\'s sentence with the client\'s placeholder in it.'
        );

        $rest = $this->render_context('category_toolbar', ['hasfilters' => false] + $context);
        $this->assertTrue(
            $rest->query('//a[@data-region="dim-filters-clear"]')->item(0)->hasAttribute('hidden'),
            'Control: with nothing narrowing there is nothing to clear.'
        );
    }

    /**
     * The date button: a disclosure right after Filters, its summary the calendar icon named with the period.
     *
     * The summary is a capsule's icon button: the calendar glyph, named by its aria-label (the filter, then the
     * period's sentence while one is set), its tooltip the filter's name, and the dot at its corner, drawn while a
     * period is set and out of the accessibility tree. It carries the same name with {period} in the sentence's place,
     * which the client fills from each answer's period chip. Closed on load, as Filters is.
     *
     * Changes that must make it fail: the button moved off its place after Filters; the dot drawn at rest or read out;
     * the name or the template dropped; the disclosure drawn open; the glyph a caret or no calendar.
     */
    public function test_the_date_button_follows_filters_named_with_its_period_and_dotted(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_toolbar');
        $xpath = $this->render_context('category_toolbar', $context);
        $details = $xpath->query(
            '//details[@data-region="dim-filters"]/following-sibling::*[1][self::details][@data-region="dim-period"]'
        );
        $this->assertSame(1, $details->length, 'Right after Filters.');
        $this->assertFalse($details->item(0)->hasAttribute('open'), 'Closed on load.');
        $summary = $xpath->query('./summary[@data-region="dim-period-button"]', $details->item(0))->item(0);
        $this->assertTrue(self::has_token($summary->getAttribute('class'), 'dim-line__iconbtn'));
        $this->assertSame(1, $xpath->query('./i[@class="fa fa-calendar-days"][@aria-hidden="true"]', $summary)->length);
        $this->assertSame($context['datebuttonlabel'], $summary->getAttribute('aria-label'));
        $this->assertStringStartsWith(
            $context['datelegend'] . ': ',
            $summary->getAttribute('aria-label'),
            'The filter, then the period.'
        );
        $this->assertSame($context['datelegend'], $summary->getAttribute('data-dim-tip'));
        $this->assertSame($context['datebuttontemplate'], $summary->getAttribute('data-dim-template'));
        $this->assertSame(
            get_string('category_datebutton', 'theme_dimensions', (object) [
                'filter' => get_string('category_dategroup', 'theme_dimensions'),
                'period' => category_toolbar::PERIOD_PLACEHOLDER,
            ]),
            'Start date: {period}',
            'The server\'s sentence with the client\'s placeholder in it.'
        );
        $dot = $xpath->query('./span[@data-region="dim-period-dot"]', $summary)->item(0);
        $this->assertSame('dim-line__dot', $dot->getAttribute('class'));
        $this->assertSame('true', $dot->getAttribute('aria-hidden'), 'The dot is decorative: the name says the period.');
        $this->assertFalse($dot->hasAttribute('hidden'), 'Drawn: the example holds a period.');

        $rest = $this->render_context('category_toolbar', ['hasperiod' => false, 'datebuttonlabel' => 'Start date'] + $context);
        $this->assertTrue(
            $rest->query('//span[@data-region="dim-period-dot"]')->item(0)->hasAttribute('hidden'),
            'Control: with no period there is no dot.'
        );
        $this->assertSame(
            'Start date',
            $rest->query('//summary[@data-region="dim-period-button"]')->item(0)->getAttribute('aria-label')
        );
    }

    /**
     * The date layer holds the period and its foot; the Filters panel holds option groups only.
     *
     * The layer is the date fieldset - its legend the heading, the quick options, the client's empty region and
     * the two native date fields posting the two parameters - then the foot: "Clear period", a link to this listing
     * with both bounds dropped (the period chip's address), "Show" with the panel foot's two sentences, and
     * "Filter" for a reader without JavaScript. Without JavaScript the summary opens the layer and both fields and
     * the quick links work: the fields are in the toolbar form.
     *
     * Changes that must make it fail: the date fieldset left in the Filters panel; a native field renamed or moved
     * out of the form; the foot's three controls missing, reordered or given another action, address or template.
     */
    public function test_the_date_layer_holds_the_period_and_its_foot_and_works_without_javascript(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_toolbar');
        $xpath = $this->render_context('category_toolbar', $context);
        $this->assertSame(0, $xpath->query('//details[@data-region="dim-filters"]//*[@data-region="dim-date"]')->length);
        $this->assertSame(0, $xpath->query('//details[@data-region="dim-filters"]//input[@type="date"]')->length);
        $layer = $xpath->query('//details[@data-region="dim-period"]/div[@data-region="dim-period-layer"]');
        $this->assertSame(1, $layer->length);
        $this->assertSame(
            ['dim-date', 'dim-period-foot'],
            array_map(
                static fn(\DOMElement $node): string => $node->getAttribute('data-region'),
                iterator_to_array($xpath->query('./*', $layer->item(0)))
            ),
            'The period\'s fieldset, then the foot.'
        );
        $fieldset = $xpath->query('./fieldset[@data-region="dim-date"]', $layer->item(0))->item(0);
        $legend = $xpath->query('./legend', $fieldset)->item(0);
        $this->assertSame($context['datelegend'], trim($legend->textContent), 'The heading.');
        $this->assertSame((string) $context['today'], $fieldset->getAttribute('data-today'));
        $this->assertSame((string) $context['firstday'], $fieldset->getAttribute('data-firstday'));
        $this->assertSame(count($context['datequick']), $xpath->query('.//a[@data-action="dim-quick"]', $fieldset)->length);
        $this->assertSame(1, $xpath->query('.//div[@data-region="dim-daterange"][not(node())]', $fieldset)->length);
        $inputs = $xpath->query(
            '//form[@data-region="dim-vitrine-toolbar"]//details[@data-region="dim-period"]//input[@type="date"]'
        );
        $this->assertSame(
            [[criteria::PARAM_DATEFROM, $context['datefrom']], [criteria::PARAM_DATETO, $context['dateto']]],
            array_map(
                static fn(\DOMElement $input): array => [$input->getAttribute('name'), $input->getAttribute('value')],
                iterator_to_array($inputs)
            ),
            'Without JavaScript the two native fields post the two parameters, from inside the form.'
        );

        $foot = $xpath->query('./div[@data-region="dim-period-foot"]', $layer->item(0))->item(0);
        $this->assertSame(
            ['dim-period-clear', 'dim-period-show', 'dim-toolbar__submit dim-nojs-only'],
            array_map(
                static fn(\DOMElement $node): string => $node->getAttribute('data-action') ?: $node->getAttribute('class'),
                iterator_to_array($xpath->query('./*', $foot))
            ),
            '"Clear period" at the start, "Show" at the end, "Filter" in its place without JavaScript.'
        );
        $clear = $xpath->query('./a[@data-action="dim-period-clear"]', $foot)->item(0);
        $this->assertSame('dim-datelayer__clear', $clear->getAttribute('class'));
        $this->assertSame($context['dateclearurl'], $clear->getAttribute('href'));
        $this->assertSame(get_string('category_dateclear', 'theme_dimensions'), trim($clear->textContent));
        $show = $xpath->query('./button[@data-action="dim-period-show"]', $foot)->item(0);
        $this->assertSame('button', $show->getAttribute('type'), 'It closes the layer; it submits nothing.');
        $this->assertSame('btn btn-sm btn-primary dim-datelayer__show dim-live-only', $show->getAttribute('class'));
        $this->assertSame($context['showlabel'], trim($show->textContent));
        $this->assertSame($context['showtemplate'], $show->getAttribute('data-dim-template'));
    }

    /**
     * The state group is split by criteria::SCOPE: the line's cells, and the panel's "More statuses".
     *
     * Both halves are one radio group by name, so one choice holds across them. The client splits
     * every answer's status group by the same list, mirrored as SCOPE in js/esm/src/listing/address.ts:
     * the two lists are pinned equal here, so neither side can draw a value where the other paints it.
     *
     * Changes that must make it fail: a value moved between the two lists on one side only; a
     * "More statuses" value drawn on the line.
     */
    public function test_the_scope_holds_the_scope_values_and_more_statuses_the_rest(): void {
        global $CFG;
        $this->resetAfterTest();

        $source = (string) file_get_contents($CFG->dirroot . '/theme/dimensions/js/esm/src/listing/address.ts');
        $this->assertSame(1, preg_match("~^export const SCOPE = \[(.*?)\];$~m", $source, $match), 'The client names its list.');
        $client = array_map(static fn(string $value): string => trim($value, " '"), explode(',', $match[1]));
        $this->assertSame(criteria::SCOPE, $client, 'The client splits the status group by the server\'s own list.');

        $statusgroup = [
            'legend' => 'Situação',
            'name' => 'chip',
            'options' => array_map(
                static fn(string $value): array => [
                    'id' => 'dim-chip-7-' . ($value === '' ? 'all' : $value),
                    'value' => $value,
                    'label' => $value === '' ? 'Todos' : $value,
                    'count' => 1,
                    'hascount' => true,
                    'checked' => $value === 'pending',
                ],
                ['', 'enrolled', 'open', 'scheduled', 'pending', 'free']
            ),
        ];
        $method = new \ReflectionMethod(category_toolbar::class, 'split_status');
        [$scope, $more] = $method->invoke(null, $statusgroup);
        $context = ['scope' => $scope, 'hasscope' => true, 'more' => $more, 'hasmorestatuses' => true]
            + $this->example_context('category_toolbar');
        $xpath = $this->render_context('category_toolbar', $context);

        $values = static fn(\DOMNodeList $inputs): array => array_map(
            static fn(\DOMElement $input): string => $input->getAttribute('value'),
            iterator_to_array($inputs)
        );
        $this->assertSame(
            criteria::SCOPE,
            $values($xpath->query('//div[@data-region="dim-line"]/fieldset[@data-role="scope"]//input[@type="radio"]')),
            'The line holds exactly the scope, in its order.'
        );
        $this->assertSame(
            ['pending', 'free'],
            $values($xpath->query('//details[@data-region="dim-filters"]//fieldset[@data-role="more"]//input[@type="radio"]')),
            '"More statuses" holds the rest, inside the panel.'
        );
        $this->assertSame(6, $xpath->query('//input[@type="radio"][@name="chip"]')->length, 'One radio group by name.');
        $this->assertSame(1, $xpath->query('//input[@type="radio"][@name="chip"][@checked]')->length, 'One choice across both.');
        $this->assertSame(
            get_string('category_morestatuses', 'theme_dimensions'),
            trim($xpath->query('//fieldset[@data-role="more"]/legend')->item(0)->textContent)
        );

        // With nothing to offer, "More statuses" is drawn hidden, so a client has a group to paint into.
        [$scope, $none] = $method->invoke(null, ['options' => array_slice($statusgroup['options'], 0, 4)] + $statusgroup);
        $bare = $this->render_context('category_toolbar', ['scope' => $scope, 'more' => $none] + $context);
        $this->assertTrue($bare->query('//fieldset[@data-role="more"]')->item(0)->hasAttribute('hidden'));
        $this->assertNull($method->invoke(null, null)[0], 'No state group, no scope: a visitor\'s line draws none.');
    }

    /**
     * The Filters control is in the document either way and carries the hidden attribute only without a group.
     *
     * The client and the hooks find the element on every page; only its visibility follows
     * category_toolbar's hasfiltergroups.
     *
     * Changes that must make it fail: the attribute dropped for a panel with no group, or written for one with.
     */
    public function test_the_filters_control_is_hidden_exactly_when_no_group_is_drawn(): void {
        $query = '//div[@data-region="dim-line"]//details[@data-region="dim-filters"]';
        $example = $this->example_context('category_toolbar');
        $this->assertTrue($example['hasfiltergroups'], 'The example draws a group.');
        $shown = $this->render_context('category_toolbar', $example);
        $this->assertSame(1, $shown->query($query)->length);
        $this->assertFalse($shown->query($query)->item(0)->hasAttribute('hidden'));

        $bare = $this->render_context('category_toolbar', ['hasfiltergroups' => false] + $example);
        $this->assertSame(1, $bare->query($query)->length, 'Still in the document.');
        $this->assertTrue($bare->query($query)->item(0)->hasAttribute('hidden'));
        $this->assertSame(1, $bare->query($query . '/summary')->length, 'The control keeps its summary for the client.');
    }

    /**
     * Without JavaScript every control of the line submits the form, and the default submit is still first.
     *
     * Enter in the search box presses the form's first submit, which is the name-less one; then the
     * star, the panel's "Filter", the menu's "Apply" and the two view buttons each post the form.
     *
     * Changes that must make it fail: a named submit moved before the default one; a control of the
     * line turned into a plain button.
     */
    public function test_without_javascript_every_control_of_the_line_submits(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_toolbar');
        $submits = $xpath->query('//form[@data-region="dim-vitrine-toolbar"]//button[@type="submit"]');
        $first = $submits->item(0);
        $this->assertTrue(self::has_token($first->getAttribute('class'), 'dim-toolbar__defaultsubmit'), 'Enter presses it.');
        $this->assertFalse($first->hasAttribute('name'));
        $found = [];
        foreach ($submits as $submit) {
            $found[] = $submit->getAttribute('data-region') ?: ($submit->getAttribute('data-action') ?: trim(
                str_replace(' dim-nojs-only', '', $submit->getAttribute('class'))
            ));
        }
        $this->assertSame(
            [
                'visually-hidden dim-toolbar__defaultsubmit',
                'dim-line-star',
                'dim-toolbar__submit',
                'dim-toolbar__submit',
                'dim-toolbar__sortsubmit',
                'dim-view',
                'dim-view',
            ],
            $found,
            'The default first, the star, the panel\'s and the date layer\'s Filter, the menu\'s Apply, the two views.'
        );
        foreach (['dim-toolbar__sortsubmit', 'dim-toolbar__submit'] as $name) {
            $submit = $xpath->query('//button[@type="submit"][' . self::has_class($name) . ']');
            $this->assertSame($name . ' dim-nojs-only', $submit->item(0)->getAttribute('class'), $name . ', the theme\'s own.');
        }
        $this->assertSame(
            1,
            $xpath->query(
                '//details[@data-region="dim-organise"]//button[' . self::has_class('dim-toolbar__sortsubmit') . ']'
            )->length,
            'The menu\'s Apply is in the menu it applies.'
        );
    }

    /**
     * The row under the line: hidden at rest but for its count sentence, which stays the live region.
     *
     * The count is its number in bold and the sentence; "See all as a list" and the automatic
     * opening's note follow the chips; and the period's chip is in the calendar's own region. The
     * sentence is visually hidden wherever the bar's count says what it says, and shown only where it
     * says where the results lie across the areas, which is exactly when it comes with "See all as a
     * list" (seeallurl): never removed and never given the hidden attribute.
     *
     * Changes that must make it fail: the count sentence removed or hidden with the hidden
     * attribute at rest (a live region out of the accessibility tree announces nothing); the rest
     * class drawn under a criterion; the sentence shown under a criterion that offers no "See all as a
     * list", or hidden where it does.
     */
    public function test_the_row_under_the_line_is_the_count_at_rest_and_the_chips_under_a_criterion(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_toolbar');
        $xpath = $this->render_context('category_toolbar', $context);
        $row = $xpath->query('//div[@data-region="dim-line-chips"]')->item(0);
        $this->assertFalse(self::has_token($row->getAttribute('class'), 'dim-line__chips--rest'), 'Something narrows.');
        $count = $xpath->query('./p[@data-region="dim-vitrine-count"][@role="status"][@aria-live="polite"]', $row);
        $this->assertSame(1, $count->length);
        $this->assertFalse(
            self::has_token($count->item(0)->getAttribute('class'), 'visually-hidden'),
            'Visible where it says where the results lie, with "See all as a list".'
        );
        $number = $xpath->query('./b[@data-region="dim-vitrine-countnumber"]', $count->item(0));
        $this->assertSame($context['countnumber'], $number->item(0)->textContent, 'The number first, in bold.');
        $this->assertSame(
            $context['countnumber'] . ' ' . $context['countline'],
            $count->item(0)->textContent,
            'One line of text, exactly the server\'s: nothing a scenario reads is split by the markup.'
        );
        $this->assertSame(
            ['dim-vitrine-count', 'dim-line-chiplist', 'dim-daterange-pill', 'dim-vitrine-clear', 'dim-vitrine-seeall',
                'dim-vitrine-countnote'],
            array_map(
                static fn(\DOMElement $node): string => $node->getAttribute('data-region'),
                iterator_to_array($xpath->query('./*', $row))
            ),
            'The sentence, the chips, the period\'s chip, "Clear all", "See all as a list" and the note, in that order.'
        );
        $chips = $xpath->query('.//a[@data-action="dim-chip-remove"]', $row);
        $this->assertSame(count($context['chips']), $chips->length, 'One link per chip of the context.');
        foreach ($chips as $index => $chip) {
            // The list holds the field and the state; the period's chip comes after it, in its own region.
            $expected = $context['chips'][[0, 2, 1][$index]]['param'];
            $this->assertSame($expected, $chip->getAttribute('data-param'));
            $this->assertSame(1, $xpath->query('./i[' . self::has_class('fa-xmark') . '][@aria-hidden="true"]', $chip)->length);
            $this->assertSame(
                'Remove filter',
                $xpath->query('./span[' . self::has_class('visually-hidden') . ']', $chip)->item(0)->textContent
            );
        }
        $this->assertSame(
            1,
            $xpath->query('./*[@data-region="dim-daterange-pill"]/a[@data-param="period"]', $row)->length,
            'The period\'s chip is in the calendar\'s own region of the row.'
        );
        $seeall = $xpath->query('./span[@data-region="dim-vitrine-seeall"]', $row);
        $this->assertFalse($seeall->item(0)->hasAttribute('hidden'), 'Offered: the example is a narrowed page.');
        $this->assertSame($context['seeallurl'], $xpath->query('./a', $seeall->item(0))->item(0)->getAttribute('href'));
        $this->assertSame(1, $xpath->query('./span[@data-region="dim-vitrine-countnote"][@hidden]', $row)->length);

        // Narrowed with nothing to say across the areas: the row shows its chips, the bar's count says the number.
        $flat = $this->render_context('category_toolbar', ['seeallurl' => ''] + $context);
        $row = $flat->query('//div[@data-region="dim-line-chips"]')->item(0);
        $this->assertFalse(self::has_token($row->getAttribute('class'), 'dim-line__chips--rest'), 'Precondition: it narrows.');
        $count = $flat->query('./p[@data-region="dim-vitrine-count"][@role="status"][@aria-live="polite"]', $row)->item(0);
        $this->assertTrue(self::has_token($count->getAttribute('class'), 'visually-hidden'), 'Out of the picture.');
        $this->assertFalse($count->hasAttribute('hidden'), 'And still the live region.');
        $this->assertSame($context['countnumber'] . ' ' . $context['countline'], $count->textContent, 'Its words unchanged.');

        $rest = $this->render_context('category_toolbar', [
            'hasfilters' => false,
            'chips' => [],
            'haschips' => false,
            'clearall' => false,
            'seeallurl' => '',
        ] + $context);
        $row = $rest->query('//div[@data-region="dim-line-chips"]')->item(0);
        $this->assertTrue(
            self::has_token($row->getAttribute('class'), 'dim-line__chips--rest'),
            'At rest the row is out of the picture.'
        );
        $this->assertFalse($row->hasAttribute('hidden'), 'But never out of the document.');
        $count = $rest->query('./p[@data-region="dim-vitrine-count"][@role="status"]', $row)->item(0);
        $this->assertNotNull($count, 'The count sentence stays: it is the page\'s live region.');
        $this->assertTrue(self::has_token($count->getAttribute('class'), 'visually-hidden'), 'Visually hidden at rest.');
        $this->assertFalse($count->hasAttribute('hidden'), 'Hidden from the eye only, never from assistive technology.');
        $this->assertSame(0, $rest->query('//a[@data-action="dim-chip-remove"]')->length);
        $period = $rest->query('//*[@data-region="dim-daterange-pill"]')->item(0);
        $this->assertSame('', $period->textContent, 'Empty, so the stylesheet drops it.');
    }

    /**
     * The directory draws its search line, one poster per category, and nothing a category cannot answer.
     *
     * The root's search is its one control that narrows: the search line is the category toolbar's
     * first line - the same form and hook, the name-less default submit first, the same box - with
     * no filter disclosure, no organisation, no view toggle and no count row, and a visible submit
     * for a reader without the listing's module.
     */
    public function test_the_directory_is_a_card_per_category_and_no_listing(): void {
        $this->resetAfterTest();
        $xpath = $this->render('category_directory');

        $sections = $xpath->query('//section[' . self::has_class('dim-vitrine--directory') . '][@data-region="dim-vitrine"]');
        $this->assertSame(1, $sections->length, 'The directory is the showcase\'s own region.');
        $section = $sections->item(0);
        $this->assertTrue($section->hasAttribute('data-vitrine'), 'The listing\'s module reads its term off the root.');

        $forms = $xpath->query('./form[@data-region="dim-vitrine-toolbar"]', $section);
        $this->assertSame(1, $forms->length, 'The search line comes first, as the root\'s one control.');
        $form = $forms->item(0);
        $this->assertSame('search', $form->getAttribute('role'));
        $this->assertStringContainsString('dim-toolbar--searchonly', $form->getAttribute('class'));
        $submits = $xpath->query('.//button[@type="submit"]', $form);
        $this->assertSame(2, $submits->length, 'The name-less default submit and the visible one.');
        $first = $submits->item(0)->getAttribute('class');
        $this->assertStringContainsString('dim-toolbar__defaultsubmit', $first, 'Enter presses it.');
        $this->assertFalse($submits->item(0)->hasAttribute('name'));
        $this->assertStringContainsString('dim-nojs-only', $submits->item(1)->getAttribute('class'), 'The module hides it.');
        $this->assertSame(1, $xpath->query('.//input[@type="search"][@name="q"]', $form)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-region="dim-toolbar-notice"]', $form)->length);
        $listingonly = ['dim-filters', 'dim-organise', 'dim-sort', 'dim-view-row', 'dim-vitrine-count', 'dim-vitrine-clear',
            'dim-line-chips'];
        foreach ($listingonly as $region) {
            $this->assertSame(0, $xpath->query('.//*[@data-region="' . $region . '"]', $form)->length, "No {$region} at the root.");
        }

        $opengrid = './section[@data-region="dim-areas"]/ul[@data-region="dim-areas-grid"]';
        $posters = $xpath->query($opengrid . '/li/article[@data-region="dim-area"]', $section);
        $entries = $this->example_context('category_directory')['areas']['posters'];
        /* An example context that emptied would make every assertion below pass by never running,
           and the two entries are not interchangeable: one carries an image, the other the band
           with its watermark. */
        $this->assertGreaterThan(1, count($entries), 'The example context must exercise the image AND the band.');
        $this->assertNotSame(
            empty($entries[0]['hasbadges']),
            empty($entries[1]['hasbadges']),
            'And one entry with crests beside one without, or the assertions below run on one case.'
        );
        $this->assertSame(count($entries), $posters->length, 'One poster per admitted top-level category.');

        foreach ($entries as $index => $entry) {
            $poster = $posters->item($index);
            $this->assertSame((string) $entry['id'], $poster->getAttribute('data-categoryid'));
            $this->assertSame('dim-area-' . $entry['id'], $poster->getAttribute('id'));

            $links = $xpath->query('.//h3[' . self::has_class('dim-poster__name') . ']/a', $poster);
            $this->assertSame(1, $links->length, 'The name holds the poster\'s one control.');
            $this->assertSame(
                $entry['name'],
                trim($xpath->query('./span[' . self::has_class('dim-poster__nm') . ']', $links->item(0))->item(0)->textContent)
            );
            $this->assertTrue(
                $xpath->query('.//*[@data-region="dim-poster-emptynote"]', $links->item(0))->item(0)->hasAttribute('hidden'),
                'Every poster of the root opens, so the empty note is written hidden.'
            );
            $this->assertSame($entry['controlurl'], $links->item(0)->getAttribute('href'), 'It opens the panel.');
            $this->assertSame($entry['shell']['titleurl'], $poster->getAttribute('data-titleurl'));

            // No number: a poster names a category and says nothing about its size.
            $this->assertSame(
                0,
                $xpath->query('.//*[' . self::has_class('dim-card__meta') . ']', $poster)->length,
                'A poster prints no meta line: it has no number to put in one.'
            );

            /* The colours are the category's own pair, in one style attribute on the item. That
               attribute is a CSS context Mustache's escaping does not protect, so the guard is the
               vocabulary, and the assertion is that the attribute holds nothing else. */
            $style = $poster->getAttribute('style');
            $this->assertSame($entry['style'], $style, 'The colours are the exported declaration, unaltered.');
            $this->assertMatchesRegularExpression(
                "~^(--dim-card-(band|ink): #[0-9a-f]{6}; ?|--dim-mark-a: [01]\\.\\d{2}; ?|--dim-mark: url\\('[^'&]*'\\); ?)+$~",
                $style
            );
            $this->assertSame(
                empty($entry['hasimage']) ? 0 : 1,
                $xpath->query('.//*[' . self::has_class('dim-poster__media') . ']//img', $poster)->length,
                'The image is drawn exactly where the branding has one.'
            );
            $this->assertSame(
                empty($entry['hasmark']) ? 0 : 2,
                $xpath->query('.//*[' . self::has_class('dim-poster__mark') . ']', $poster)->length,
                'And the watermark where it has none: under the text and in the thumbnail.'
            );

            // The category's own crests, in the poster's top row.
            $crests = $xpath->query(
                './/*[' . self::has_class('dim-poster__top') . ']/ul[' . self::has_class('dim-poster__crests') . ']',
                $poster
            );
            $this->assertSame(empty($entry['hasbadges']) ? 0 : 1, $crests->length, 'One crest list where there are crests.');
            foreach ($entry['badges'] as $badgeindex => $badge) {
                $image = $xpath->query('.//img', $crests->item(0))->item($badgeindex);
                $this->assertSame($badge['url'], $image->getAttribute('src'));
                $this->assertSame($badge['alt'], $image->getAttribute('alt'));
            }

            $excerpt = $xpath->query('.//p[' . self::has_class('dim-poster__desc') . ']', $poster);
            $this->assertSame(empty($entry['hasdescription']) ? 0 : 1, $excerpt->length);
        }

        // Nothing of the listing: a category has no state, no enrolment and no favourite, and
        // the root has no course of its own to filter or to page through.
        $absent = [
            'a star' => '//*[@data-action="dim-star"]',
            'a pager' => '//*[@data-region="dim-vitrine-pager"]',
            'a load-more button' => '//*[@data-action="dim-loadmore"]',
            'an accordion panel' => '//details[@data-region="dim-accordion-panel"]',
            'a card disclosure' => '//article//details',
            'a state badge' => '//*[' . self::has_class('dim-badge') . ']',
        ];
        foreach ($absent as $what => $query) {
            $this->assertSame(0, $xpath->query($query)->length, "The directory draws {$what}.");
        }
    }

    /**
     * Past twenty-four, the rest of the posters are a closed native disclosure and nothing else.
     *
     * Four things hold at once: the disclosure is there and closed (an open one is a second
     * grid, not a reveal), its summary is the label the server built, the posters inside it are
     * the entries the export put there and no others, and every image in it carries the lazy
     * loading attribute, which is what the cut is for. The example context carries entries on
     * each side of the cut, so the absence of the disclosure under the cut is asserted from a
     * context of its own. The export decides that flag and `category_listing_test` pins it at
     * twenty-four and twenty-five.
     */
    public function test_the_rest_of_the_directory_sits_in_a_closed_disclosure(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_areas');
        $context['posters'][0]['isopen'] = false;
        $context['isopen'] = false;
        $context['panelinmain'] = false;
        $context['panelstyle'] = '';
        $context['panel'] = null;
        $xpath = $this->render_context('category_areas', $context);

        // Non-vacuity: the example context must really exercise both sides of the cut.
        $this->assertNotEmpty($context['posters'], 'The example context has no open poster.');
        $this->assertNotEmpty($context['postersmore'], 'And none behind the reveal.');

        $reveal = $xpath->query('//details[@data-region="dim-areas-more"]');
        $this->assertSame(1, $reveal->length, 'One reveal, in the region the scenarios read.');
        $details = $reveal->item(0);
        $this->assertFalse($details->hasAttribute('open'), 'It is CLOSED: an open one reveals nothing.');

        $summary = $xpath->query('./summary', $details);
        $this->assertSame(1, $summary->length, 'The button is the disclosure\'s own summary.');
        $this->assertSame($context['morelabel'], trim($summary->item(0)->textContent));

        $hidden = $xpath->query('.//article[@data-region="dim-area"]', $details);
        $this->assertSame(count($context['postersmore']), $hidden->length, 'The rest of the posters, and no others.');
        foreach ($context['postersmore'] as $index => $entry) {
            $this->assertSame((string) $entry['id'], $hidden->item($index)->getAttribute('data-categoryid'));
        }

        // The open grid holds the others, so no poster is drawn twice and none is lost.
        $open = $xpath->query(
            '//section[@data-region="dim-areas"]/ul[@data-region="dim-areas-grid"]/li/article[@data-region="dim-area"]'
        );
        $this->assertSame(count($context['posters']), $open->length);

        foreach ($xpath->query('.//img', $details) as $image) {
            $this->assertSame('lazy', $image->getAttribute('loading'), 'A hidden poster fetches nothing until revealed.');
        }

        // Under the cut: the same posters, the flag off, and no disclosure drawn at all.
        $under = $context;
        $under['postersmore'] = [];
        $under['hasmore'] = false;
        $small = $this->render_context('category_areas', $under);
        $this->assertSame(
            0,
            $small->query('//details[@data-region="dim-areas-more"]')->length,
            'Twenty-four posters or fewer: there is no reveal on the page, not a closed one.'
        );
        $this->assertSame(
            count($context['posters']),
            $small->query('//article[@data-region="dim-area"]')->length,
            'Control: the open posters are still drawn, so the absence above is not an empty render.'
        );
    }

    /**
     * The stylesheet paints the card band from the custom property, with a fallback.
     *
     * The stylesheet must still paint a band when the inline custom property is absent (a card
     * rendered outside this page, or an export that stopped emitting the style), because an
     * unresolved var() is an invalid declaration and not a default: the band would have no colour
     * at all. That the property can only hold a hex colour is asserted on the rendered poster in
     * test_the_directory_is_a_card_per_category_and_no_listing; that it carries the category's own
     * colour is asserted on the directory entries, in population_test.
     */
    public function test_the_stylesheet_paints_the_card_band_from_the_custom_property(): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scss/category.scss');
        $this->assertNotEmpty($source);
        $this->assertMatchesRegularExpression(
            '~--dim-card-band:\s*[^;]+;~',
            $source,
            'The stylesheet declares its own default for the band: the inline style of a card '
                . 'overrides it, and a card rendered without one still has a band. Reading an '
                . 'undeclared custom property with no fallback is an INVALID declaration rather '
                . 'than a default, so the band would have no colour at all.'
        );
        $this->assertMatchesRegularExpression(
            '~background(-color)?:\s*var\(\s*--dim-card-band~',
            $source,
            'And the band is actually painted from it.'
        );
        $this->assertStringNotContainsString(
            '.dim-card__media--#{$name}',
            $source,
            'The per-key band rules are gone with the palette.'
        );
    }

    /**
     * A narrowed page's posters wear their pill, a zero one leaves the grid, and the summary names the zero areas.
     *
     * Rendered from the areas partial's own example, which draws an empty poster at rest beside a
     * poster wearing its pill and a zero poster the criterion hid. The pill is what the control is
     * described by, so it carries the id the description names; the zero poster's item is hidden,
     * never removed; the summary is a closed native disclosure after the grids carrying both of
     * its sentences, naming a public area as a link and a visitor's merely listed one as text; and
     * the "nothing matches" box carries the page's way out, one of its dim-clear controls.
     */
    public function test_a_narrowed_page_draws_pills_leaves_zero_posters_and_names_them_in_a_summary(): void {
        $this->resetAfterTest();
        $context = $this->example_context('category_areas');
        $xpath = $this->render('category_areas');

        $section = $xpath->query('//section[@data-region="dim-areas"]')->item(0);
        $this->assertSame('1', $section->getAttribute('data-filtered'), 'The section says it is narrowed.');
        $this->assertSame($context['elsewhere'], $section->getAttribute('data-elsewhere'));
        $rest = $this->render_context('category_areas', ['filtered' => false] + $context);
        $this->assertSame('0', $rest->query('//section[@data-region="dim-areas"]')->item(0)->getAttribute('data-filtered'));

        [$open, $empty, $zero] = $context['posters'];
        $this->assertTrue($open['results']['has'] && !$empty['results']['has'] && $zero['results']['iszero'], 'Precondition.');
        $pill = $xpath->query('//article[@id="dim-area-' . $open['id'] . '"]//*[@data-region="dim-poster-pill"]')->item(0);
        $this->assertSame('dim-area-results-' . $open['id'], $pill->getAttribute('id'));
        $this->assertSame($open['results']['label'], $pill->textContent);
        $this->assertFalse($pill->hasAttribute('hidden'));
        $this->assertFalse(self::has_token($pill->getAttribute('class'), 'dim-poster__pill--zero'));
        $this->assertSame(
            'span',
            $pill->parentNode->nodeName,
            'Inside the tags, under the kind.'
        );
        $this->assertTrue(self::has_token($pill->parentNode->getAttribute('class'), 'dim-poster__tags'));
        $control = $xpath->query('//article[@id="dim-area-' . $open['id'] . '"]//*[@data-region="dim-poster-control"]')->item(0);
        $this->assertSame($pill->getAttribute('id'), $control->getAttribute('aria-describedby'), 'The control is described by it.');
        $article = $xpath->query('//article[@id="dim-area-' . $open['id'] . '"]')->item(0);
        $this->assertSame((string) $open['count'], $article->getAttribute('data-count'));
        $this->assertSame($open['results']['count'], $article->getAttribute('data-resultcount'));

        $emptypill = $xpath->query('//article[@id="dim-area-' . $empty['id'] . '"]//*[@data-region="dim-poster-pill"]')->item(0);
        $this->assertTrue($emptypill->hasAttribute('hidden'), 'At rest the pill is written, hidden, for the client.');
        $this->assertFalse(
            $xpath->query('//article[@id="dim-area-' . $empty['id'] . '"]//*[@data-region="dim-poster-control"]')->item(0)
                ->hasAttribute('aria-describedby'),
            'And describes nothing.'
        );
        $zeropill = $xpath->query('//article[@id="dim-area-' . $zero['id'] . '"]//*[@data-region="dim-poster-pill"]')->item(0);
        $this->assertTrue(self::has_token($zeropill->getAttribute('class'), 'dim-poster__pill--zero'), 'In the zero colours.');

        $items = [];
        $grid = '//section[@data-region="dim-areas"]/ul[@data-region="dim-areas-grid"]/li['
            . self::has_class('dim-areas__item') . ']';
        foreach ($xpath->query($grid) as $item) {
            $items[$xpath->query('./article', $item)->item(0)->getAttribute('data-categoryid')] = $item->hasAttribute('hidden');
        }
        $this->assertSame(
            [(string) $open['id'] => false, (string) $empty['id'] => false, (string) $zero['id'] => true],
            $items,
            'The zero poster leaves the grid through its item, still in the document.'
        );

        $summary = $xpath->query(
            '//section[@data-region="dim-areas"]/details[@id="dim-nomatch"][@data-region="dim-areas-nomatch"]'
        );
        $this->assertSame(1, $summary->length, 'One summary, a direct child of the section, where Close lands.');
        $details = $summary->item(0);
        $this->assertFalse($details->hasAttribute('open'), 'Closed.');
        $this->assertFalse($details->hasAttribute('hidden'), 'And shown: it has areas to name.');
        $this->assertSame($context['nomatch']['labelall'], $details->getAttribute('data-label-all'));
        $this->assertSame($context['nomatch']['labelless'], $details->getAttribute('data-label-less'));
        $this->assertSame(
            $context['nomatch']['label'],
            $xpath->query('./summary[@data-region="dim-nomatch-summary"]', $details)->item(0)->textContent
        );
        $this->assertSame(
            1,
            $xpath->query('./preceding-sibling::details[@data-region="dim-areas-more"]', $details)->length,
            'After the grids, the reveal among them.'
        );
        $entries = $xpath->query('./ul[@data-region="dim-nomatch-list"]/li[@data-categoryid]', $details);
        $this->assertSame(count($context['nomatch']['areas']), $entries->length);
        foreach ($context['nomatch']['areas'] as $index => $area) {
            $entry = $entries->item($index);
            $this->assertSame((string) $area['id'], $entry->getAttribute('data-categoryid'));
            $links = $xpath->query('./a', $entry);
            $this->assertSame($area['haslink'] ? 1 : 0, $links->length, $area['name'] . ': a link, or its name as text.');
            if ($area['haslink']) {
                $this->assertSame($area['url'], $links->item(0)->getAttribute('href'));
            }
            $this->assertSame($area['name'], $entry->textContent);
        }
        $none = $this->render_context('category_areas', ['nomatch' => ['show' => false] + $context['nomatch']] + $context);
        $this->assertTrue(
            $none->query('//details[@data-region="dim-areas-nomatch"]')->item(0)->hasAttribute('hidden'),
            'Control: with nothing to name it waits hidden.'
        );

        $box = $xpath->query('//section[@data-region="dim-areas"]/div[@data-region="dim-areas-empty"]');
        $this->assertSame(1, $box->length);
        $this->assertFalse($box->item(0)->hasAttribute('hidden'), 'The example says nothing matched anywhere.');
        $ways = $xpath->query('./p[' . self::has_class('dim-areas__allemptyways') . ']', $box->item(0));
        $this->assertSame(1, $ways->length, 'Its ways out, on a line of their own.');
        $clear = $xpath->query('./a[@data-action="dim-clear"]', $ways->item(0));
        $this->assertSame($context['clearurl'], $clear->item(0)->getAttribute('href'), 'Its way out is the page\'s own.');
        $this->assertSame($context['clearlabel'], $clear->item(0)->textContent);
        // The same term searched in every area, offered where the reader may open the root.
        $searchall = $xpath->query('./a[@data-region="dim-areas-searchall"]', $ways->item(0));
        $this->assertSame(1, $searchall->length);
        $this->assertSame($context['searchallurl'], $searchall->item(0)->getAttribute('href'));
        $this->assertFalse($searchall->item(0)->hasAttribute('hidden'));
        $this->assertSame(get_string('category_searchall', 'theme_dimensions'), $searchall->item(0)->textContent);
        $this->assertTrue(
            $this->render_context('category_areas', ['searchallurl' => ''] + $context)
                ->query('//a[@data-region="dim-areas-searchall"]')->item(0)->hasAttribute('hidden'),
            'Control: with nothing to offer, it waits hidden.'
        );
        $this->assertSame(
            [$context['allemptylabel'], $context['allemptyhint']],
            array_map(
                static fn(\DOMElement $p): string => $p->textContent,
                iterator_to_array($xpath->query('./p[not(@class)]', $box->item(0)))
            ),
            'Each sentence on one line of its own.'
        );
        $this->assertTrue(
            $this->render_context('category_areas', ['allempty' => false] + $context)
                ->query('//div[@data-region="dim-areas-empty"]')->item(0)->hasAttribute('hidden'),
            'Control: waits hidden.'
        );
        $hiddenmore = $this->render_context('category_areas', ['morehidden' => true] + $context);
        $this->assertTrue(
            $hiddenmore->query('//details[@data-region="dim-areas-more"]')->item(0)->hasAttribute('hidden'),
            'A reveal holding no result is hidden, still in the document.'
        );
        // The root has no course of its own to skip to, but its search draws the summary and the empty box.
        $root = $this->render_context('category_areas', ['isroot' => true] + $context);
        $this->assertSame(0, $root->query('//*[@data-region="dim-direct-skip"]')->length, 'The root draws no skip link.');
        foreach (['dim-areas-nomatch', 'dim-areas-empty'] as $region) {
            $this->assertSame(1, $root->query('//*[@data-region="' . $region . '"]')->length, "The root draws its {$region}.");
        }
    }

    /**
     * A zero panel says so with the term and suggests the areas with a result: links on the page, buttons in the client.
     *
     * Rendered from a real export (an area opened under a search it misses), because one example
     * context renders one state and the template's is the rows state. Both copies carry the same
     * action, area id and focus key on each suggestion, which is what lets the client convert the
     * server's links into its buttons the way it converts the orders and Close.
     */
    public function test_a_zero_panel_suggests_the_areas_with_a_result_as_links_or_buttons(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = self::getDataGenerator();
        $root = $generator->create_category(['name' => 'Raiz']);
        $areas = [];
        foreach (['Aviacao' => 'Voo', 'Brigada' => 'Curso de incendio', 'Transito' => 'Curso de blitz'] as $name => $fullname) {
            $areas[$name] = (int) $generator->create_category(['parent' => $root->id, 'name' => $name])->id;
            $generator->create_course(['category' => $areas[$name], 'fullname' => $fullname]);
        }
        $this->setUser($generator->create_user());
        $PAGE->set_url('/course/index.php', ['categoryid' => $root->id]);
        $criteria = new criteria((int) $root->id, ['search' => 'Curso', 'open' => $areas['Aviacao'], 'withposters' => true]);
        $listing = new category_listing(
            core_course_category::get((int) $root->id),
            $criteria,
            population::resolve($criteria),
            category_toolbar::VIEW_CARDS
        );
        $panel = $listing->export_for_template($PAGE->get_renderer('core'))->areas['panel'];
        $this->assertTrue($panel['iszero'] && $panel['hassuggestions'], 'Precondition: the zero state with suggestions.');

        foreach (['the page\'s copy' => [false, 'a'], 'the client\'s copy' => [true, 'button']] as $what => [$jsmode, $element]) {
            $xpath = $this->render_context('category_panel', ['jsmode' => $jsmode] + $panel);
            $state = $xpath->query('//*[@data-region="dim-panel-body"]/*[' . self::has_class('dim-panel__state') . ']');
            $this->assertSame(1, $state->length, $what);
            $this->assertSame($panel['zero'], trim($xpath->query('./p/b', $state->item(0))->item(0)->textContent));
            $sugg = $xpath->query('./div[@data-region="dim-panel-suggestions"]', $state->item(0));
            $this->assertSame(1, $sugg->length, $what . ': the suggestions follow the sentence.');
            $this->assertSame(
                get_string('category_panel_resultsin', 'theme_dimensions'),
                $xpath->query('./span', $sugg->item(0))->item(0)->textContent
            );
            $chips = $xpath->query('./' . $element . '[@data-action="dim-panel-openarea"]', $sugg->item(0));
            $this->assertSame(['Brigada', 'Transito'], array_map(
                static fn(\DOMElement $chip): string => trim($chip->firstChild->textContent),
                iterator_to_array($chips)
            ), $what . ': one per other area with a result, in order.');
            foreach ($chips as $index => $chip) {
                $suggestion = $panel['suggestions'][$index];
                $this->assertSame((string) $suggestion['id'], $chip->getAttribute('data-categoryid'));
                $this->assertSame('suggest-' . $suggestion['id'], $chip->getAttribute('data-focuskey'));
                $digit = $xpath->query('./span[' . self::has_class('dim-panel__suggestioncount') . ']', $chip)->item(0);
                $this->assertSame($suggestion['count'], $digit->textContent);
                /* The digit alone would be read with no unit ("Transito 1"): it is hidden from assistive
                   technology and the pill's words are read in its place. */
                $this->assertSame('true', $digit->getAttribute('aria-hidden'), $what . ': the bare digit is not read.');
                $spoken = $xpath->query('./span[' . self::has_class('visually-hidden') . ']', $chip);
                $this->assertSame(1, $spoken->length, $what . ': the count is read as results.');
                $this->assertSame($suggestion['countlabel'], $spoken->item(0)->textContent);
                $this->assertSame(
                    get_string('category_results_one', 'theme_dimensions'),
                    $suggestion['countlabel'],
                    'The pill\'s own words for one result.'
                );
                if ($jsmode) {
                    $this->assertSame('button', $chip->getAttribute('type'));
                } else {
                    $this->assertSame($suggestion['url'], $chip->getAttribute('href'), 'The link reopens this page on that area.');
                }
            }
        }
    }
}
