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
 * Theme Dimensions - Tests for the category showcase stylesheet and its class vocabulary
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Guards what nothing in the pipeline reads about the showcase's stylesheets and its markup.
 *
 * Both stylesheets are concatenated into the theme's main SCSS and reach every
 * page of the site, so every rule of them has to be scoped under the body class
 * of the page they belong to; stylelint knows nothing about which page a rule
 * reaches. There are two: scss/category.scss under the showcase's own body class,
 * and scss/categoryhero.scss - the appearance editor's swatches, pair preview
 * and contrast panel - under the editor page's. Each must read only custom
 * properties it declares (an unresolved var() invalidates the whole declaration),
 * use no !important, and the templates and classes of the showcase must write
 * Bootstrap 5 spellings only - the Bootstrap 4 names resolve on 5.2 through a
 * compatibility layer Moodle 6.0 removes.
 *
 * The editor's file cannot borrow the showcase's tokens: those are declared
 * inside a block scoped to the showcase's body class and no element of the
 * editor page is inside it, so the second file declares its own, which the
 * "every var() it reads it declares" check below proves once per file.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class category_scss_test extends \basic_testcase {
    /**
     * The stylesheets of the showcase and the body class each one must be scoped under.
     *
     * @var array File name relative to scss/ => the body class selector.
     */
    private const SHEETS = [
        'category.scss' => 'body.dim-category',
        'categoryhero.scss' => 'body.dim-categoryhero',
    ];

    /**
     * The one other thing a top-level selector may carry in front of its scope.
     *
     * Moodle 5.2's single dark-mode mechanism is the data-bs-theme attribute on the html
     * element, which is an ancestor of the body: a rule that only applies in dark mode has
     * to name it before the body class. Nothing else may precede the scope.
     *
     * @var string
     */
    private const DARK_PREFIX = ':root[data-bs-theme="dark"] ';

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * One stylesheet of the showcase, with its comments removed.
     *
     * @param string $name The file name relative to scss/.
     * @return string
     */
    private function stylesheet(string $name): string {
        return $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/' . $name));
    }

    /**
     * Remove block and line comments from a stylesheet.
     *
     * @param string $source The stylesheet source.
     * @return string
     */
    private function strip_comments(string $source): string {
        $source = preg_replace('~/\*.*?\*/~s', '', $source);
        return preg_replace('~(^|\s)//[^\n]*~m', '$1', $source);
    }

    /**
     * Every selector of the stylesheet which is not already inside another rule.
     *
     * Walks the braces: a selector counts as top-level at the root of the file or directly
     * inside an at-rule at the root.
     *
     * @param string $scss The stylesheet source, comments already removed.
     * @return array The selector strings, whitespace collapsed.
     */
    private function top_level_selectors(string $scss): array {
        $selectors = [];
        $stack = [];
        $buffer = '';
        $length = strlen($scss);
        for ($i = 0; $i < $length; $i++) {
            $char = $scss[$i];
            if ($char === '{') {
                $selector = trim((string) preg_replace('~\s+~', ' ', $buffer));
                $depth = count($stack);
                $isatrule = str_starts_with($selector, '@');
                $underatrule = $depth === 1 && str_starts_with($stack[0], '@');
                if (!$isatrule && ($depth === 0 || $underatrule)) {
                    $selectors[] = $selector;
                }
                $stack[] = $selector;
                $buffer = '';
            } else if ($char === '}') {
                array_pop($stack);
                $buffer = '';
            } else if ($char === ';') {
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        return $selectors;
    }

    /**
     * The files of the showcase whose contents can put a class name in front of a person.
     *
     * @return string[] Absolute paths.
     */
    private function markup_files(): array {
        $root = $this->plugin_root();
        $files = glob($root . '/templates/category*.mustache');
        $files = array_merge($files, glob($root . '/classes/output/category_*.php'));
        /* The modules write Bootstrap class names into the DOM themselves (the contrast panel's
           Apply buttons are btn btn-sm btn-primary), and phpcs, the Mustache lint and stylelint
           never read a class name out of a JavaScript file. */
        $files = array_merge($files, glob($root . '/amd/src/category*.js'));
        /* The calendar module's name does not match the glob, and it writes every class of its
           month grid, its day cells and the removable pill of the count row. A file the scanner
           cannot see is one the Bootstrap rules do not apply to, which is why the membership test
           below names it. */
        $files[] = $root . '/amd/src/daterange.js';
        /* Nor does covervideo's: it writes the band's playing class, the body class that reveals
           the cluster and the Font Awesome glyph each toggle swaps to. */
        $files[] = $root . '/amd/src/covervideo.js';
        /* The refusal page, what a reader gets instead of a listing, does not match the globs
           either. The page scripts (category.php, categoryhero.php) and the editor's form
           (classes/form/categoryhero.php) write no class name: the form builds moodleform
           elements and the scripts only add a body class. Add a file here the day it writes one. */
        $files[] = $root . '/templates/unavailable.mustache';
        foreach (array_keys(self::SHEETS) as $sheet) {
            $files[] = $root . '/scss/' . $sheet;
        }
        sort($files);
        return $files;
    }

    /**
     * The scanner really holds every file of the showcase that can put a class name on a screen.
     *
     * A glob that stops matching, or a named file dropped from the list, would leave every
     * assertion below passing over fewer files with nothing to say so. The set is asserted by
     * membership rather than by equality, so a template added to the feature does not have to be
     * added here as well.
     */
    public function test_the_scanner_reaches_every_file_of_the_showcase(): void {
        $files = array_map('basename', $this->markup_files());
        $expected = [
            'category_listing.mustache',
            'category_card.mustache',
            'category_directory.mustache',
            'category_toolbar.mustache',
            'category_hero.mustache',
            'unavailable.mustache',
            'category_listing.php',
            'category_card.php',
            'category_daterange.mustache',
            'categoryvitrine.js',
            'categorylisting.js',
            'categoryposters.js',
            'categorypublic.js',
            'categorypanel.js',
            'categoryareapanel.js',
            'categorystar.js',
            'categorycoursemodal.js',
            'category_card_modal.mustache',
            'category_course_modal.mustache',
            'category_areas.mustache',
            'category_panel.mustache',
            'category_directory_card.mustache',
            'category_panel.php',
            'categoryhero.js',
            'daterange.js',
            'covervideo.js',
            'category.scss',
            'categoryhero.scss',
        ];
        foreach ($expected as $name) {
            $this->assertContains($name, $files, $name . ' is no longer scanned for Bootstrap 4 names.');
        }
    }

    /**
     * Is this one selector part scoped to the given body class?
     *
     * The one predicate both the check over the real stylesheets and its self-check below run:
     * a self-check that re-typed the expression would prove the copy, not the rule.
     *
     * The scope has to end where it is written: body.dim-category is a prefix of
     * body.dim-categoryhero, so a plain str_starts_with would let the editor's body class pass
     * as the showcase's.
     *
     * @param string $part One comma-separated part of a top-level selector, trimmed.
     * @param string $scope The body class selector the file must be scoped under.
     * @return bool
     */
    private function is_scoped(string $part, string $scope): bool {
        foreach ([$scope, self::DARK_PREFIX . $scope] as $prefix) {
            if (!str_starts_with($part, $prefix)) {
                continue;
            }
            $next = substr($part, strlen($prefix), 1);
            if ($next === '' || !preg_match('~[a-z0-9_-]~i', $next)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every rule of each stylesheet is scoped under the body class of the page it belongs to.
     */
    public function test_every_top_level_selector_is_scoped_to_its_own_page(): void {
        foreach (self::SHEETS as $sheet => $scope) {
            $selectors = $this->top_level_selectors($this->stylesheet($sheet));
            $this->assertNotEmpty($selectors, $sheet . ' has no rules at all, so this proves nothing.');
            foreach ($selectors as $selector) {
                foreach (explode(',', $selector) as $part) {
                    $part = trim($part);
                    if ($part === '') {
                        continue;
                    }
                    $this->assertTrue(
                        $this->is_scoped($part, $scope),
                        $sheet . ': unscoped selector, it would reach every page: ' . $part
                    );
                }
            }
        }
    }

    /**
     * The scope check really can tell an unscoped selector from a scoped one.
     */
    public function test_the_scope_check_sees_an_unscoped_selector(): void {
        $fixture = "body.dim-category .x { color: red; }\n.dim-card { color: blue; }\n"
            . ":root[data-bs-theme=\"dark\"] body.dim-category .z { color: green; }\n"
            . "[data-bs-theme=\"dark\"] .x { color: teal; }\n"
            . ":root[data-bs-theme=\"dark\"] .x { color: olive; }\n"
            . "@media (min-width: 1px) { .y { z-index: 1; } }\n";
        $found = $this->top_level_selectors($fixture);
        $this->assertSame(
            [
                'body.dim-category .x',
                '.dim-card',
                ':root[data-bs-theme="dark"] body.dim-category .z',
                '[data-bs-theme="dark"] .x',
                ':root[data-bs-theme="dark"] .x',
                '.y',
            ],
            $found
        );
        // And the rule the scope check applies to each of them: two pass, four do not. The two
        // rejected dark cases are the near misses a future author writes - one without the
        // :root prefix, one with it and without the body class.
        $accepted = array_values(array_filter($found, function (string $part): bool {
            return $this->is_scoped($part, 'body.dim-category');
        }));
        $this->assertSame(
            ['body.dim-category .x', ':root[data-bs-theme="dark"] body.dim-category .z'],
            $accepted,
            'A dark selector naming the attribute without the body class is NOT scoped.'
        );
    }

    /**
     * One body class is not another whose name it starts.
     *
     * body.dim-category is a prefix of body.dim-categoryhero: a scope check comparing prefixes
     * alone would accept every rule of the editor's stylesheet as a rule of the showcase's.
     */
    public function test_the_scope_check_does_not_accept_a_longer_body_class(): void {
        $this->assertFalse($this->is_scoped('body.dim-categoryhero .dim-contrast', 'body.dim-category'));
        $this->assertFalse($this->is_scoped('body.dim-categoryhero', 'body.dim-category'));
        $this->assertTrue($this->is_scoped('body.dim-categoryhero .dim-contrast', 'body.dim-categoryhero'));
        $this->assertTrue($this->is_scoped('body.dim-category .dim-card', 'body.dim-category'));
        // The scope alone, and the scope carrying a state, are both the page's own.
        $this->assertTrue($this->is_scoped('body.dim-category', 'body.dim-category'));
        $this->assertTrue($this->is_scoped('body.dim-category.drawer-open-left .x', 'body.dim-category'));
    }

    /**
     * Every custom property the stylesheet reads is declared by it or by the theme's tokens, Bootstrap's own aside.
     *
     * An unresolved var() invalidates the whole declaration, and a fallback chain that always
     * falls through to its literal is a colour no flavour can move. Bootstrap's --bs-* names are
     * the one set declared elsewhere.
     */
    public function test_every_custom_property_it_reads_is_declared(): void {
        $tokens = $this->strip_comments(file_get_contents($this->plugin_root() . '/scss/post.scss'));
        foreach (array_keys(self::SHEETS) as $sheet) {
            $scss = $this->stylesheet($sheet);
            preg_match_all('~var\((--[a-z0-9-]+)~', $scss, $reads);
            preg_match_all('~^\s*(--[a-z0-9-]+)\s*:~m', $scss . "\n" . $tokens, $declarations);
            $this->assertNotEmpty($reads[1], $sheet . ' reads no custom property at all.');
            $missing = [];
            foreach (array_unique($reads[1]) as $name) {
                if (!str_starts_with($name, '--bs-') && !in_array($name, $declarations[1], true)) {
                    $missing[] = $name;
                }
            }
            $this->assertSame([], $missing, $sheet . ' reads but never declares: ' . implode(', ', $missing));
        }
    }

    /**
     * Neither stylesheet uses !important: specificity does the work.
     */
    public function test_the_stylesheets_use_no_important(): void {
        foreach (array_keys(self::SHEETS) as $sheet) {
            $this->assertStringNotContainsString('!important', $this->stylesheet($sheet), $sheet);
        }
    }

    /**
     * Every scope in the map is a body class a page of this theme really adds.
     *
     * The editor's whole stylesheet is scoped under a class one line adds
     * (categoryhero.php's add_body_class), and deleting that line unstyles the swatches, the
     * pair preview and the contrast panel without failing anything else: the @javascript
     * scenario asserts text and field values, which an unstyled panel still has. This ties the
     * two together, so a scope cannot outlive the line that puts it on the page. The adder is
     * named per sheet rather than searched for, because one of the two adds the class through a
     * constant and a search that accepted either spelling would accept the class merely being
     * mentioned somewhere.
     *
     * @return void
     */
    public function test_every_scope_is_a_body_class_a_page_of_this_theme_adds(): void {
        $adders = [
            'body.dim-category' => [
                'classes/local/category/request.php',
                'add_body_class(category_listing::BODY_CLASS)',
                \theme_dimensions\output\category_listing::BODY_CLASS,
            ],
            'body.dim-categoryhero' => [
                'categoryhero.php',
                "add_body_class('dim-categoryhero')",
                'dim-categoryhero',
            ],
        ];
        $this->assertSame(
            array_values(self::SHEETS),
            array_keys($adders),
            'One adder per stylesheet: a sheet added to the map needs the page that scopes it named here.'
        );
        foreach ($adders as $scope => [$file, $call, $resolved]) {
            $this->assertSame(
                substr($scope, strlen('body.')),
                $resolved,
                $file . ' adds ' . $call . ', and that resolves to the class ' . $scope . ' is scoped under.'
            );
            $this->assertStringContainsString(
                $call,
                file_get_contents($this->plugin_root() . '/' . $file),
                $file . ' no longer adds the class every rule of the sheet scoped to ' . $scope . ' hangs off.'
            );
        }
    }

    /**
     * The grid keeps three explicit tracks and the crests are positioned against the card's own band.
     *
     * A card grid back on a flex basis would let a lone card fill its row, and a crest list whose
     * offset stopped reading the band's height would sit over the title of a card that otherwise
     * renders correctly. Both are asserted on the source, because no test here can read a
     * computed box.
     */
    public function test_the_grid_keeps_three_tracks_and_the_crests_read_the_bands_height(): void {
        $scss = $this->stylesheet('category.scss');

        $this->assertMatchesRegularExpression(
            '~\.dim-vitrine__grid\s*\{[^}]*grid-template-columns:\s*1fr 1fr 1fr;~',
            $scss,
            'The grid names three tracks: one or two cards keep a third of the width each.'
        );
        $this->assertStringNotContainsString(
            'flex: 1 1 30%',
            $scss,
            'The old percentage basis is gone: with any grow factor a lone card fills its row.'
        );
        $this->assertMatchesRegularExpression(
            '~--dim-card-media:\s*150px;~',
            $scss,
            'The media band is 150px, which is what makes room for an 88px crest beside the tiles.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-card__media\s*\{[^}]*height:\s*var\(--dim-card-media\);~',
            $scss,
            'And the band reads that property rather than repeating the number.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-card__crests\s*\{[^}]*top:\s*calc\(var\(--dim-card-media\) - var\(--dim-card-crest-h\) - 8px\);~',
            $scss,
            'The crests hang off the band\'s own height: they are positioned against the CARD, '
                . 'because they are written in the badge row so that the list view can move them.'
        );
    }

    /**
     * The editor's two columns are a grid over the form's own fieldset, gated on the split class.
     *
     * The moodleform rows stay where core drew them and a CSS grid over the colours fieldset's
     * container places them, so the columns are the form's own arrangement and never a
     * JavaScript relocation of its rows, which can leave a field focusable but painted nowhere.
     * What this pins:
     *
     * - the grid is gated on `.dim-colours--split`, which only the module adds and only once the
     *   contrast panel is really mounted. Ungated, a reader with JavaScript off gets a two-track
     *   grid with nothing in the right-hand track;
     * - the selectors name core's own markup, which the form test renders rather than assumes
     *   (categoryhero_test::test_the_colours_are_a_fieldset_whose_container_the_grid_can_place);
     * - the columns only exist from lg up, and the panel is placed by grid-column, not by an
     *   order the DOM would have to be rewritten for.
     */
    public function test_the_editors_two_columns_are_a_gated_grid_over_the_forms_own_fieldset(): void {
        $scss = $this->stylesheet('categoryhero.scss');

        $container = array_values(array_filter(
            $this->top_level_selectors($scss),
            static fn($selector) => str_contains($selector, '.fcontainer')
        ));
        $this->assertNotEmpty($container, 'Nothing lays the colours fieldset out at all any more.');
        foreach ($container as $selector) {
            $this->assertStringContainsString(
                'body.dim-categoryhero',
                $selector,
                'A rule over a form container escapes the editor page: ' . $selector
            );
            $this->assertStringContainsString(
                'fieldset.dim-colours--split',
                $selector,
                'The grid applies to a form section the module never marked as split: ' . $selector
            );
        }

        $this->assertMatchesRegularExpression(
            '~fieldset\.dim-colours--split > \.fcontainer \{[^}]*display: grid;[^}]*'
                . 'grid-template-columns: minmax\(0, 1fr\) minmax\(0, 22rem\);~s',
            $scss,
            'The container is no longer two tracks, a flexible one and a capped one for the panel.'
        );
        $this->assertMatchesRegularExpression(
            '~> \.fcontainer > \.dim-contrast-aside \{[^}]*grid-column: 2;[^}]*grid-row: 1 / span 8;~s',
            $scss,
            'The panel no longer takes the right track from the first line.'
        );
        $this->assertMatchesRegularExpression(
            '~> \.fcontainer > \.fitem,\s*body\.dim-categoryhero fieldset\.dim-colours--split > \.fcontainer'
                . ' > \.dim-pair-preview \{\s*grid-column: 1;~',
            $scss,
            'The rows and the preview no longer keep the left track, so a row could land under the panel.'
        );

        // And the whole arrangement is inside the lg query: below it the container is a block.
        $query = strpos($scss, '@media (min-width: 992px)');
        $this->assertIsInt($query, 'The two-column arrangement is no longer gated on a width at all.');
        $this->assertSame(
            1,
            substr_count($scss, 'display: grid'),
            'A second grid is declared in this stylesheet; the width gate below covers only one.'
        );
        $this->assertGreaterThan(
            $query,
            strpos($scss, 'display: grid'),
            'The grid is declared outside the lg query, so the two columns apply on a phone as well.'
        );
    }

    /**
     * The sidebar follows the reader down the page, scrolls inside itself, and stops on a phone.
     *
     * A long tree would otherwise leave a reader deep in the listing with no way to change
     * category without returning to the top. Four declarations make it and each is useless
     * without the others: sticky with no offset hides under the site's fixed navbar, sticky with
     * no max-height pins a column taller than the screen and never moves, a max-height with no
     * overflow clips the categories past it, and without align-self the flex item is the row's
     * height and the box never leaves the top.
     *
     * Sticky also stops working, silently, under any ancestor whose overflow is not visible.
     * Core's and Boost Union's ancestors of #region-main set none; this test holds the half that
     * is ours: no rule of this stylesheet puts an overflow on the two boxes between the tree and
     * #region-main.
     *
     * Changes that must make it fail: deleting the max-height (the third assertion); putting
     * `overflow: hidden` on .dim-vitrine__shell (the last).
     */
    public function test_the_sidebar_is_sticky_with_its_own_scroll_and_nothing_of_ours_clips_it(): void {
        $scss = $this->stylesheet('category.scss');

        $rule = $this->rule_body($scss, '.dim-vitrine__tree');
        $this->assertMatchesRegularExpression('~position:\s*sticky;~', $rule);
        $this->assertMatchesRegularExpression(
            '~top:\s*calc\(var\(--dim-navbar\) \+ 1rem\);~',
            $rule,
            "The offset clears the site's own fixed navbar, and reads its height rather than repeating it."
        );
        $this->assertMatchesRegularExpression(
            '~max-height:\s*calc\(100vh - var\(--dim-navbar\) - 2rem\);~',
            $rule,
            'A sticky column taller than the screen is a column that never moves.'
        );
        $this->assertMatchesRegularExpression('~overflow-y:\s*auto;~', $rule, 'And past that height it scrolls itself.');
        $this->assertMatchesRegularExpression(
            '~overscroll-behavior:\s*contain;~',
            $rule,
            'A scroll that ends must not become the page\'s.'
        );
        $this->assertMatchesRegularExpression('~align-self:\s*flex-start;~', $rule);
        $this->assertMatchesRegularExpression(
            '~--dim-navbar:\s*#\{\$navbar-height\};~',
            $scss,
            "The offset is Boost's own navbar height, read from the Sass variable this file is compiled beside."
        );

        /* And on a phone it is none of those things: stacked above the listing, a pinned tree
           would take the top of the screen away from the courses it exists to lead to. */
        $narrow = $this->narrow_block($scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-vitrine__tree \{[^}]*position:\s*static;~',
            $narrow,
            'Below the breakpoint the column stacks and stops being sticky.'
        );

        foreach (['.dim-vitrine__shell', '.dim-vitrine__content'] as $selector) {
            $this->assertDoesNotMatchRegularExpression(
                '~overflow~',
                $this->rule_body($scss, $selector),
                $selector . ' carries an overflow: any value but visible on an ancestor kills the '
                    . 'sticky tree, silently and at every width.'
            );
        }
    }

    /**
     * The poster states its stack, so the film paints under the still it is revealed by.
     *
     * The template writes the <img> first and the <video> second (card_markup_test pins that
     * order, because the still has to be painted for a poster that never plays), and a later
     * positioned sibling paints on top at equal z-index, so with nothing stated the film covers
     * the still. The card is the band covervideo binds to and a stacking context; inside it the
     * media sits at -1 under the top row (1), the control stretched over the card (2) and the
     * film's controls (3), and inside the media the film is 0 and the still 1.
     *
     * Changes that must make it fail: the film's 0 swapped for a 2 (the ordering); the card's
     * z-index deleted (the last assertion).
     */
    public function test_the_posters_stack_puts_the_cover_film_under_the_still(): void {
        $scss = $this->stylesheet('category.scss');

        $layers = [
            '.dim-poster__video' => '0',
            '.dim-poster__img' => '1',
            '.dim-poster__media' => '-1',
            '.dim-poster__top' => '1',
            '.dim-poster__filmctl' => '3',
            '.dim-poster__bar' => '1',
        ];
        foreach ($layers as $selector => $expected) {
            $this->assertMatchesRegularExpression(
                '~z-index:\s*' . preg_quote($expected, '~') . ';~',
                $this->rule_body($scss, $selector),
                $selector . ' is no longer on layer ' . $expected . ' of the poster.'
            );
        }
        $this->assertMatchesRegularExpression(
            '~&::after \{[^}]*position:\s*absolute;[^}]*inset:\s*0;[^}]*z-index:\s*2;~s',
            $this->rule_body($scss, '.dim-poster__control'),
            'The control is stretched over the whole poster, over the media and under the film\'s controls.'
        );
        $this->assertMatchesRegularExpression(
            '~position:\s*absolute;~',
            $this->rule_body($scss, '.dim-poster__video'),
            'A film that is not positioned takes a box of its own and doubles the band\'s height.'
        );
        $this->assertMatchesRegularExpression(
            '~position:\s*relative;~',
            $this->rule_body($scss, '.dim-poster__img'),
            'A z-index on a static element is ignored, so the still would still be under the film.'
        );
        $this->assertMatchesRegularExpression(
            '~opacity:\s*0;~',
            $this->rule_body($scss, '.dim-poster__card.dim-card__media--playing .dim-poster__img'),
            'Playing fades the still out; it is never removed, so the poster cannot change height.'
        );
        $this->assertMatchesRegularExpression(
            '~z-index:\s*0;~',
            $this->rule_body($scss, '.dim-poster__card'),
            'Without a stacking context of its own the poster lets its media rank against the page.'
        );
        // The playing class is still the one covervideo writes on the element it binds to.
        $this->assertStringContainsString(
            "PLAYING_CLASS = 'dim-card__media--playing'",
            (string) file_get_contents($this->plugin_root() . '/amd/src/covervideo.js')
        );
    }

    /**
     * The film's controls are reachable by every reader, and drawn only where their module runs.
     *
     * They hide with opacity rather than with display or visibility, because those two take the
     * buttons out of the focus order and :focus-within could then never bring them back; the hover
     * rule reveals them for a pointer, the focus-within rule for a keyboard, and the (hover: none)
     * query for a finger, which has no hover and for which the play control is the only way in.
     * They are drawn only under covervideo's own body class, never the listing module's, and each
     * control states the focus ring.
     *
     * Changes that must make it fail: the cluster's `opacity: 0` turned into `1` (the first
     * assertion); the focus-within half of the reveal deleted (the hover and focus assertion).
     */
    public function test_the_film_controls_hide_without_leaving_a_control_nobody_can_see(): void {
        $scss = $this->stylesheet('category.scss');

        $cluster = $this->rule_body($scss, '.dim-poster__filmctl');
        $this->assertMatchesRegularExpression('~opacity:\s*0;~', $cluster, 'It is out of the way until it is wanted.');
        foreach (['display:\s*none', 'visibility:\s*hidden'] as $removal) {
            $this->assertDoesNotMatchRegularExpression(
                '~' . $removal . '~',
                $cluster,
                'That takes the buttons out of the focus order, and :focus-within can then never reveal them.'
            );
        }
        $this->assertMatchesRegularExpression(
            '~\.dim-poster__card:hover \.dim-poster__filmctl,\s*\.dim-poster__filmctl:focus-within \{\s*opacity: 1;~',
            $scss,
            'A pointer and a keyboard each reveal it, and the keyboard half is what keeps it from being a ghost.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(hover: none\) \{\s*\.dim-poster__filmctl \{\s*opacity: 1;~',
            $scss,
            'A finger has no hover: there the cluster is simply always on screen.'
        );
        $this->assertMatchesRegularExpression(
            '~display:\s*none;~',
            $this->rule_body($scss, '.dim-cover-js-only'),
            'Three buttons that do nothing are worse than no buttons: without the module there is no cluster.'
        );
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $this->rule_body($scss, '&.dim-cover-js .dim-poster__filmctl'));
        $this->assertMatchesRegularExpression('~display:\s*block;~', $this->rule_body($scss, '&.dim-cover-js .dim-poster__bar'));
        $this->assertStringNotContainsString(
            '&.dim-js .dim-poster__filmctl',
            $scss,
            'The vitrine module\'s mark cannot stand for this module being present.'
        );
        $this->assertMatchesRegularExpression(
            '~&:focus-visible \{\s*outline:\s*3px solid var\(--dim-ring\);\s*outline-offset:\s*2px;~',
            $this->rule_body($scss, '.dim-poster__filmbtn'),
            'Three identical discs revealed BY focus, and nothing saying which one the keyboard is on.'
        );
    }

    /**
     * The visitor's live listing has a mark of its own, and it hides and reveals what the logged-in client's does.
     *
     * categorypublic marks the body dim-public-js, never dim-js. The twin of dim-js's rule hides the
     * same no-JavaScript submits, because the module drives the same controls in place; load more
     * and the continue link carry dim-live-only, which either mark reveals; and dim-js-only - the
     * star's - is revealed by the logged-in mark alone, a visitor having no star. The twins come
     * after the rules they mirror, because at equal specificity the source order decides.
     *
     * Changes that must make it fail: the twin hiding nothing; dim-live-only revealed by one mark only.
     */
    public function test_the_visitors_client_hides_and_reveals_by_its_own_mark(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression('~display:\s*none;~', $this->rule_body($scss, '&.dim-public-js .dim-nojs-only'));
        $this->assertMatchesRegularExpression('~display:\s*none;~', $this->rule_body($scss, '.dim-live-only'));
        $this->assertMatchesRegularExpression(
            '~&\.dim-js \.dim-live-only,\s*&\.dim-public-js \.dim-live-only \{\s*display:\s*inline-flex;\s*\}~',
            $scss,
            'Either client reveals load more and the continue link.'
        );
        $this->assertStringNotContainsString(
            '&.dim-public-js .dim-js-only',
            $scss,
            'The visitor\'s mark never reveals what only the logged-in client drives.'
        );
        $this->assertLessThan(
            (int) strpos($scss, '&.dim-public-js .dim-nojs-only {'),
            (int) strpos($scss, '&.dim-js .dim-nojs-only {'),
            'The twin comes after the rule it mirrors.'
        );
    }

    /**
     * The pill tracks are the design's row of chips, and scroll sideways rather than widening the page.
     *
     * The overflow: the automatic minimum size of a flex item is its content, so on a phone a
     * track of options wider than the toolbar widens the disclosure and then the document, and the
     * page scrolls sideways. The disclosure's min-width: 0 is therefore part of the same rule:
     * without it the track's max-width has nothing to resolve against but a box the track itself
     * widened.
     *
     * The scroll is the phone's answer alone. From the md breakpoint the panel lays the facets
     * out side by side, each narrower than the panel, and a one-line track there would hide its
     * last options behind a scrollbar an overlay platform draws only once it is being scrolled.
     * Both halves are pinned: nowrap and the scroll in the rule itself, wrap inside the md query.
     *
     * The skin is the design's .pills, a plain row of chips 6px apart with no band behind them.
     * The scroll container would clip the focus ring, which is drawn outside the chip (3px at
     * 2px), so the track keeps 5px of room for it and takes the same 5px back as a negative
     * margin: the ring is drawn whole and the chips do not move.
     *
     * Changes that must make it fail: the disclosure's min-width deleted (its assertion); the wrap
     * deleted from the md query (the last).
     */
    public function test_the_pill_tracks_hug_their_options_and_scroll_rather_than_widen_the_page(): void {
        $scss = $this->stylesheet('category.scss');

        $track = [1 => $this->rule_body($scss, '.dim-toolbar__pills')];
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $track[1], 'The design\'s .pills: a row of chips.');
        $this->assertMatchesRegularExpression('~gap:\s*6px;~', $track[1], 'Six pixels between chips, the design\'s.');
        $this->assertDoesNotMatchRegularExpression(
            '~background|border-radius~',
            $track[1],
            'No band behind the chips: the track draws no background and no radius.'
        );
        $this->assertMatchesRegularExpression(
            '~max-width:\s*calc\(100% \+ 10px\);~',
            $track[1],
            'Never wider than the box it is in, plus the room its ring is drawn in.'
        );
        $this->assertMatchesRegularExpression('~flex-wrap:\s*nowrap;~', $track[1], 'One line that scrolls, never two.');
        $this->assertMatchesRegularExpression('~overflow-x:\s*auto;~', $track[1]);
        $this->assertMatchesRegularExpression(
            '~scrollbar-width:\s*thin;~',
            $track[1],
            'A track that scrolls and never says so is a track whose last option nobody finds.'
        );
        $this->assertMatchesRegularExpression(
            '~margin:\s*-5px;\s*padding:\s*5px;~',
            $track[1],
            'The ring outside a chip needs room inside the scroll container, and the chips must not move for it.'
        );

        $filters = [1 => $this->rule_body($scss, '.dim-toolbar__filters')];
        $this->assertMatchesRegularExpression(
            '~min-width:\s*0;~',
            $filters[1],
            'Without this the track\'s own content sets the disclosure\'s width and the page overflows.'
        );

        /* The other half: from md up a track wider than the whole panel wraps instead of
           scrolling, because a desktop overlay scrollbar draws nothing until it is scrolled. A
           group that fits keeps its content's width, so this is the last resort, not the layout. */
        $wide = $this->wide_block($scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-toolbar__pills \{\s*flex-wrap:\s*wrap;\s*\}~',
            $wide,
            'A facet whose options are wider than the panel wraps them; it does not hide them.'
        );
    }

    /**
     * The toolbar's controls wear the design's skin, and each chosen face reads in both colour modes.
     *
     * The values are the design's as a browser computes them (the design's mockup:
     * .tb, .chip, .pills input:checked + .chip, .tb__fbtn, .tb__badge, .viewseg, .tb__apply,
     * .linkbtn, .tb__panel, .fgroup legend), each design token mapped onto the one this file
     * already reads: --btn is --dim-cta, --btn-ink white, --control-line --dim-control-line,
     * --surface --dim-surface, --text --dim-ink, --tint --dim-tint, --tint-ink and --link
     * --dim-primary-ink, --radius-pill and --radius-sm the theme's own. The design has no kebab,
     * no calendar and no quick options; they take the same language, the quick options as chips.
     *
     * The chosen chip, the quick option in force and the calendar's two ends use the call to
     * action's pair: --dim-cta is the navy on the light page and #4456ff on the dark one, where
     * --dim-primary with the page's surface as its ink reads 1.1:1.
     */
    public function test_the_toolbar_controls_wear_the_designs_skin(): void {
        $scss = $this->stylesheet('category.scss');

        $toolbar = $this->rule_body($scss, '.dim-toolbar');
        $this->assertMatchesRegularExpression('~gap:\s*10px;~', $toolbar, 'The design\'s rows are 10px apart.');
        $this->assertDoesNotMatchRegularExpression(
            '~padding|border|background~',
            $toolbar,
            'The toolbar is no card: the design\'s controls sit on the page itself.'
        );

        $chip = $this->rule_body($scss, '.dim-pill');
        $declarations = [
            'min-height:\s*36px;',
            'padding:\s*6px 12px;',
            'gap:\s*6px;',
            'border:\s*1px solid var\(--dim-control-line\);',
            'border-radius:\s*var\(--dim-radius-pill\);',
            'background:\s*var\(--dim-surface\);',
            'color:\s*var\(--dim-ink\);',
            'font-family:\s*var\(--dim-font-brand\);',
            'font-size:\s*\.82rem;',
            'font-weight:\s*600;',
        ];
        foreach ($declarations as $declaration) {
            $this->assertMatchesRegularExpression('~' . $declaration . '~', $chip, 'The design\'s .chip: ' . $declaration);
        }
        $this->assertMatchesRegularExpression('~&:hover \{\s*background:~', $chip, 'The chip keeps its hover background.');
        $checked = $this->rule_body($scss, '.dim-pill__input:checked + .dim-pill');
        $this->assertMatchesRegularExpression('~border-color:\s*var\(--dim-cta\);~', $checked);
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);~', $checked, 'The chosen chip is FILLED.');
        $this->assertMatchesRegularExpression('~color:\s*#fff;~', $checked);
        $this->assertDoesNotMatchRegularExpression('~box-shadow~', $checked, 'The track\'s raised white face is gone.');
        $focus = $this->rule_body($scss, '.dim-pill__input:focus-visible + .dim-pill');
        $this->assertMatchesRegularExpression('~outline:\s*3px solid var\(--dim-ring\);\s*outline-offset:\s*2px;~', $focus);
        $count = $this->rule_body($scss, '.dim-pill__count');
        $this->assertMatchesRegularExpression('~opacity:\s*\.8;~', $count);
        $this->assertMatchesRegularExpression('~font-size:\s*\.72rem;~', $count);

        $quickon = $this->rule_body($scss, '.dim-quick--on');
        $this->assertMatchesRegularExpression(
            '~background:\s*var\(--dim-cta\);~',
            $quickon,
            'The quick option in force is a chosen chip.'
        );
        $this->assertMatchesRegularExpression('~color:\s*#fff;~', $quickon);
        $this->assertStringNotContainsString('--dim-surface', $quickon, 'The page\'s surface is never the ink on the navy.');
        $edge = $this->rule_body($scss, '.dim-daterange__day--edge');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);\s*color:\s*#fff;~', $edge);
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*background:\s*var\(--dim-cta-hover\);\s*color:\s*#fff;~',
            $edge,
            'An end under the pointer keeps its pair: the plain day\'s pale hover would leave a white digit on it.'
        );

        $summary = $this->rule_body($scss, '.dim-toolbar__filterstoggle');
        $this->assertMatchesRegularExpression('~padding:\s*0 14px;~', $summary);
        $this->assertMatchesRegularExpression(
            '~border-radius:\s*var\(--dim-radius-sm\);~',
            $summary,
            'The design\'s Filter is no pill.'
        );
        $this->assertMatchesRegularExpression('~font-size:\s*\.9375rem;~', $summary);
        $this->assertMatchesRegularExpression(
            '~&:focus-visible \{\s*outline:\s*3px solid var\(--dim-ring\);\s*outline-offset:\s*2px;~',
            $summary
        );
        $this->assertMatchesRegularExpression(
            '~background:\s*var\(--dim-tint\);~',
            $this->rule_body($scss, '.dim-toolbar__filters[open] .dim-toolbar__filterstoggle'),
            'Open, it wears the design\'s pressed face.'
        );
        $badge = $this->rule_body($scss, '.dim-toolbar__filtercount');
        $this->assertMatchesRegularExpression('~min-width:\s*20px;\s*height:\s*20px;\s*padding:\s*0 6px;~', $badge);
        $this->assertMatchesRegularExpression('~font-size:\s*\.72rem;~', $badge);

        $view = $this->rule_body($scss, '.dim-toolbar__view');
        $this->assertMatchesRegularExpression('~border:\s*1px solid var\(--dim-control-line\);~', $view, 'A segmented control.');
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--dim-radius-sm\);~', $view);
        $this->assertMatchesRegularExpression('~overflow:\s*hidden;~', $view);
        $this->assertDoesNotMatchRegularExpression('~background~', $view, 'The view switch has no grey track behind it.');
        $button = $this->rule_body($scss, '.dim-toolbar__viewbtn');
        $this->assertMatchesRegularExpression('~min-height:\s*40px;~', $button);
        $this->assertMatchesRegularExpression('~padding:\s*6px 12px;~', $button);
        $this->assertMatchesRegularExpression(
            '~&\[aria-pressed="true"\] \{\s*background:\s*var\(--dim-tint\);\s*color:\s*var\(--dim-primary-ink\);~',
            $button
        );
        $this->assertMatchesRegularExpression(
            '~&:focus-visible \{\s*outline:\s*3px solid var\(--dim-ring\);\s*outline-offset:\s*-3px;~',
            $button,
            'The segment clips its buttons, so the ring is drawn inside them.'
        );
        $this->assertStringNotContainsString(
            '.dim-toolbar__viewbtn + .dim-toolbar__viewbtn',
            $scss,
            'No divider: the design writes one and renders none (its button rule outranks it), and the page matches the render.'
        );

        $kebab = $this->rule_body($scss, '.dim-toolbar__manage .dropdown-toggle');
        $this->assertMatchesRegularExpression('~width:\s*42px;\s*height:\s*42px;~', $kebab, 'As tall as the toggle beside it.');
        $this->assertMatchesRegularExpression('~border:\s*1px solid var\(--dim-control-line\);~', $kebab);

        $apply = $this->rule_body($scss, ".dim-toolbar__sortsubmit,\n    .dim-toolbar__submit");
        $this->assertMatchesRegularExpression('~min-height:\s*40px;~', $apply);
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);\s*color:\s*#fff;~', $apply);

        $clear = $this->rule_body($scss, '.dim-toolbar__clear');
        $this->assertMatchesRegularExpression('~padding:\s*0;~', $clear, 'A text button, not a 29.5px box.');
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-primary-ink\);~', $clear);
        $this->assertMatchesRegularExpression('~text-underline-offset:\s*2px;~', $clear);
        $this->assertDoesNotMatchRegularExpression('~font-weight~', $clear, 'The sentence\'s own weight.');

        $panel = $this->rule_body($scss, '.dim-toolbar__panel');
        $this->assertMatchesRegularExpression('~padding:\s*14px 16px;~', $panel);
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--radius-md, 8px\);~', $panel);
        $legend = $this->rule_body($scss, '.dim-toolbar__grouplabel');
        $this->assertMatchesRegularExpression('~margin:\s*0 0 6px;~', $legend);
        $this->assertMatchesRegularExpression('~font-size:\s*\.72rem;~', $legend);
        $this->assertMatchesRegularExpression('~letter-spacing:\s*\.1em;~', $legend);
        $this->assertMatchesRegularExpression('~text-transform:\s*uppercase;~', $legend);

        $row = $this->rule_body($scss, '.dim-toolbar__row--count');
        $this->assertMatchesRegularExpression(
            '~margin:\s*2px 0 4px;~',
            $row,
            'The design\'s 12px above and 18px below, less the gaps already there (10px and 14px).'
        );
        $this->assertMatchesRegularExpression('~gap:\s*14px;~', $this->rule_body($scss, '.dim-vitrine'), 'The 14px it counts on.');
    }

    /**
     * Where there is room the filter groups share a row, each as wide as its own options.
     *
     * Equal grid tracks answer "how many columns", but the groups differ in width: a wide facet
     * wraps inside its track while another track stands empty. So this pins a wrapping flex row
     * whose groups neither grow nor take a basis of their own (`flex: 0 1 auto`; a group that grew
     * would be an equal column again), the date group as a row of its own, and no `display: grid`
     * or `grid-template-columns` in the block at all. Groups that do not fit one row stack, which
     * needs no rule: it is what a wrapping row does.
     *
     * Changes that must make it fail: `flex: 1 1 0` on the groups (the second assertion);
     * `display: grid` restored (the last); the date group's basis dropped (the third).
     */
    public function test_the_filter_groups_are_as_wide_as_their_options_and_share_a_row_where_they_fit(): void {
        $scss = $this->stylesheet('category.scss');
        $wide = $this->wide_block($scss);

        $this->assertMatchesRegularExpression(
            '~\.dim-toolbar__panel \{[^}]*flex-direction:\s*row;[^}]*flex-wrap:\s*wrap;~s',
            $wide,
            'From the md breakpoint up the panel is a row that wraps BETWEEN its groups.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-toolbar__panel > \.dim-toolbar__group \{\s*flex:\s*0 1 auto;~',
            $wide,
            'A group is as wide as its options: it never grows, so it is never an equal column.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-toolbar__panel > \.dim-toolbar__group--date,\s*'
                . '\.dim-toolbar__panel > \.dim-toolbar__actions \{\s*flex:\s*1 1 100%;~',
            $wide,
            'The date group is a row of its own: its field and its quick options sit side by side.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~display:\s*grid|grid-template-columns~',
            $wide,
            'Equal tracks wrapped the Status options inside 358px of a 1124px panel; they stay out.'
        );
    }

    /**
     * The Filter control shares the search line, and the panel it opens is the whole row.
     *
     * The summary is taken out of the flow and placed at the end of the first line; what is left
     * of the disclosure in the flow is its panel, which is the row. The row is therefore the
     * positioned ancestor (the disclosure is a box of a different height), and the search box
     * reserves the control's width with a padding rather than being sized against it, so neither
     * moves when the panel opens or closes, however tall the panel is.
     *
     * The word on the control goes at the phone's width and the reserve shrinks with it. It is
     * hidden from the picture and not from the document: it is the summary's accessible name.
     *
     * The line is the toolbar's first row, 44px tall at every width: the search box and the
     * summary are both held to it outside any media query.
     *
     * Changes that must make it fail: `position: relative` deleted from the row (the summary is
     * then placed against whichever positioned ancestor comes next); the search box's padding
     * deleted (a typed term runs under the control).
     */
    public function test_the_filter_control_shares_the_search_line_at_every_width(): void {
        $scss = $this->stylesheet('category.scss');

        $row = [1 => $this->rule_body($scss, '.dim-toolbar__row--narrow')];
        $this->assertMatchesRegularExpression('~position:\s*relative;~', $row[1], 'The row is what the summary is placed in.');
        $this->assertMatchesRegularExpression(
            '~row-gap:\s*0;~',
            $row[1],
            'A closed disclosure is a zero-height line, and a row gap would leave a strip under the search box.'
        );

        $summary = [1 => $this->rule_body($scss, '.dim-toolbar__filterstoggle')];
        $this->assertMatchesRegularExpression('~position:\s*absolute;~', $summary[1]);
        $this->assertMatchesRegularExpression('~right:\s*0;~', $summary[1], 'At the end of the line.');
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*background:~',
            $summary[1],
            'And it hovers like the button it is drawn as (item 3).'
        );

        $search = [1 => $this->rule_body($scss, '.dim-toolbar__search')];
        $this->assertMatchesRegularExpression(
            '~padding-right:\s*8\.5rem;~',
            $search[1],
            'The search box reserves the control\'s width instead of being laid out against it.'
        );
        $this->assertMatchesRegularExpression(
            '~height:\s*44px;~',
            $this->rule_body($scss, '.dim-toolbar__input'),
            'The search box is 44px tall at every width, not on the phone alone.'
        );
        $this->assertMatchesRegularExpression('~min-height:\s*44px;~', $summary[1], 'And so is the Filter summary beside it.');

        $phone = $this->phone_block($scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-toolbar__filterslabel \{[^}]*clip-path:\s*inset\(50%\);~',
            $phone,
            'The word is taken out of the picture and left in the accessibility tree.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~\.dim-toolbar__filterslabel \{[^}]*display:\s*none~',
            $phone,
            'display:none would take the control\'s name away from a screen reader with it.'
        );
        /* On a phone the view toggle shares the line: the reserve is the summary, the toggle and a
           gap before each (44 + 10 + 87 + 10), and wider while the count badge shows, when the
           summary is 72px (72 + 10 + 87 + 10). */
        $this->assertMatchesRegularExpression('~\.dim-toolbar__search \{[^}]*padding-right:\s*151px;~', $phone);
        $this->assertMatchesRegularExpression(
            '~:has\(\[data-region="dim-filtercount"\]:not\(\[hidden\]\)\) \.dim-toolbar__search \{[^}]*padding-right:\s*179px;~',
            $phone
        );
    }

    /**
     * Each date box's width is its own, and the popover's is its months'.
     *
     * A box with a flex basis in a row as wide as the panel grows to the panel's width and looks
     * like the search box. Each of the two is shrink-to-fit between a floor and a ceiling, which
     * also lets the quick options share their line, and keeps the room its clearing control is
     * drawn in whether or not the control is there. That control is positioned inside the box,
     * and its own display would outrank the hidden attribute the client writes on it: the
     * stylesheet has to say that hidden means hidden.
     *
     * The popover is capped by the viewport, not by its region: the region is only as wide as
     * the boxes, so `max-width: 100%` would squeeze a seven-column month grid into the width of
     * the buttons that open it. It holds one month below md and two from md up.
     *
     * Changes that must make it fail: `flex: 1 1 14rem` back on a box (the second assertion); the
     * popover left at `max-width: 100%`; the hidden rule of the clearing control deleted; the md
     * width deleted.
     */
    public function test_the_date_boxes_are_never_the_width_of_the_panel(): void {
        $scss = $this->stylesheet('category.scss');

        $group = [1 => $this->rule_body($scss, '.dim-toolbar__group--date')];
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $group[1], 'The boxes and the quick options are a row.');
        $this->assertMatchesRegularExpression('~flex-wrap:\s*wrap;~', $group[1]);

        $field = [1 => $this->rule_body($scss, '.dim-daterange__field')];
        $this->assertMatchesRegularExpression('~flex:\s*0 1 auto;~', $field[1], 'It is sized by its content.');
        $this->assertMatchesRegularExpression('~width:\s*auto;~', $field[1]);
        $this->assertDoesNotMatchRegularExpression('~width:\s*100%~', $field[1]);
        $this->assertMatchesRegularExpression('~max-width:\s*14rem;~', $field[1], 'And never wider than that.');
        $this->assertMatchesRegularExpression('~min-width:\s*9\.5rem;~', $field[1], 'Nor narrower than a date reads in.');
        $this->assertMatchesRegularExpression('~padding-right:\s*2\.25rem;~', $field[1], 'The room of its clearing control.');

        $boxes = [1 => $this->rule_body($scss, '.dim-daterange__boxes')];
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $boxes[1]);
        $this->assertMatchesRegularExpression('~flex-wrap:\s*wrap;~', $boxes[1], 'The two boxes wrap as units.');
        $this->assertMatchesRegularExpression('~position:\s*relative;~', $this->rule_body($scss, '.dim-daterange__box'));

        $clear = [1 => $this->rule_body($scss, '.dim-daterange__clear')];
        $this->assertMatchesRegularExpression('~position:\s*absolute;~', $clear[1], 'Inside its box.');
        $this->assertMatchesRegularExpression('~display:\s*inline-flex;~', $clear[1]);
        $this->assertMatchesRegularExpression(
            '~&\[hidden\] \{\s*display:\s*none;\s*\}~',
            $clear[1],
            'Its own display would otherwise draw an empty box\'s control the client has hidden.'
        );

        $quick = [1 => $this->rule_body($scss, '.dim-toolbar__quick')];
        $this->assertMatchesRegularExpression(
            '~margin:\s*0;~',
            $quick[1],
            'The options share the boxes\' line, so they carry no margin above it.'
        );

        $pop = [1 => $this->rule_body($scss, '.dim-daterange__pop')];
        $this->assertMatchesRegularExpression('~width:\s*20rem;~', $pop[1], 'One month below md.');
        $this->assertMatchesRegularExpression(
            '~max-width:\s*calc\(100vw - 2rem\);~',
            $pop[1],
            'The popover is capped by the viewport and no longer by the boxes that open it.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-daterange__pop \{\s*width:\s*38rem;\s*\}~',
            $this->wide_block($scss),
            'Two months side by side from md up.'
        );

        $phone = $this->phone_block($scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-daterange__field \{\s*min-height:\s*44px;\s*\}~',
            $phone,
            'Every control of the toolbar is 44px on a phone, the two boxes included.'
        );
    }

    /**
     * The calendar's two-month breakpoint is one number, written in the stylesheet and read by the module.
     *
     * The stylesheet widens the popover for two months from md up and the module draws the second
     * month from the same width. A module drawing two months in a 20rem popover squeezes both; one
     * drawing one month in the wide popover leaves half of it empty; and one walking the focus into
     * a month the other has not drawn closes the control. Nothing else reads both files.
     */
    public function test_the_calendars_two_months_are_the_same_breakpoint_in_the_stylesheet_and_the_module(): void {
        $scss = $this->stylesheet('category.scss');
        $module = (string) file_get_contents($this->plugin_root() . '/amd/src/daterange.js');
        $this->assertMatchesRegularExpression("~^const WIDE = '\(min-width: 768px\)';$~m", $module);
        $this->assertStringContainsString('dim-daterange__pop', $this->wide_block($scss), 'The popover widens in the md block.');
        $this->assertSame(
            1,
            preg_match_all('~@media \(min-width: 768px\) \{(?:(?!\n    \}).)*\.dim-daterange__pop \{~s', $scss),
            'And the md block is the one at min-width: 768px, the module\'s WIDE.'
        );
    }

    /**
     * The tree's breakpoint is one number, written in the stylesheet and read by the module.
     *
     * The stylesheet hides the tree's summary from the breakpoint up and the module closes the
     * box below it. A module closing the box at a width where the summary is hidden leaves a
     * reader with no tree and no control to bring it back, so the numbers may not drift, and
     * nothing else reads both files. Changing either number must make this fail.
     */
    public function test_the_trees_breakpoint_is_the_same_number_in_the_stylesheet_and_the_module(): void {
        $scss = $this->stylesheet('category.scss');
        $module = file_get_contents($this->plugin_root() . '/amd/src/categorylisting.js');

        $this->assertMatchesRegularExpression(
            '~\.dim-vitrine__treetoggle \{\s*display:\s*none;\s*\}~',
            $scss,
            'The summary is drawn only where it is a control.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-vitrine__treetoggle \{[^}]*display:\s*flex;~',
            $this->narrow_block($scss),
            'And below the breakpoint it is one.'
        );
        $this->assertMatchesRegularExpression(
            "~const TREE_PHONE = '\(max-width: 767\.98px\)';~",
            $module,
            'The module closes the box at the same width the stylesheet gives the summary back.'
        );
    }

    /**
     * The panel's row is one of three integers by breakpoint, and the list view's single track's.
     *
     * An explicitly placed item is placed first and auto-placement skips its row, so the panel sits
     * under its poster's row at every width with no script - provided the stylesheet reads the
     * integer for its breakpoint, which is what this pins, and reads the one-track integer in the
     * list view at every width, or the panel falls rows away from its poster there. The defaults
     * are declared on the element the server's style attribute is on, which wins over them.
     */
    public function test_the_panels_row_follows_the_breakpoint_and_the_list_view(): void {
        $scss = $this->stylesheet('category.scss');
        $panel = $this->rule_body($scss, '.dim-panel');
        $this->assertMatchesRegularExpression('~grid-column:\s*1 / -1;~', $panel);
        $this->assertMatchesRegularExpression('~grid-row:\s*var\(--dim-row-3\);~', $panel, 'Three tracks from 1200px up.');
        foreach (['--dim-row-3', '--dim-row-2', '--dim-row-1'] as $row) {
            $this->assertMatchesRegularExpression('~' . $row . ':\s*2;~', $panel, $row . ' has its default where it is read.');
        }
        foreach (['--dim-col-3', '--dim-col-2'] as $col) {
            $this->assertMatchesRegularExpression('~' . $col . ':\s*0;~', $panel);
        }
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 1199\.98px\) \{\s*\.dim-panel \{[^}]*grid-row:\s*var\(--dim-row-2\);~',
            $scss,
            'Two tracks below 1200px.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\) \{\s*\.dim-panel \{[^}]*grid-row:\s*var\(--dim-row-1\);~',
            $scss,
            'One track below 576px.'
        );
        $this->assertMatchesRegularExpression(
            '~grid-row:\s*var\(--dim-row-1\);~',
            $this->rule_body($scss, '.dim-vitrine--list .dim-panel'),
            'The list view is one track at every width.'
        );
        $this->assertMatchesRegularExpression('~left:\s*30px;~', $this->rule_body($scss, '.dim-vitrine--list .dim-panel::before'));
    }

    /**
     * The poster's pan is for a fine pointer with motion allowed, and for nothing else.
     *
     * Both animations are declared inside one media query and nowhere else: a pan that escaped it
     * would move a photograph under a finger's tap or for a reader who asked for less movement.
     */
    public function test_the_pan_is_for_a_fine_pointer_with_motion_allowed_only(): void {
        $scss = $this->stylesheet('category.scss');
        $query = '@media (hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference) {';
        $at = strpos($scss, $query);
        $this->assertIsInt($at, 'The pan has no media query of its own any more.');
        $block = $this->block_at($scss, $at + strlen($query));
        $this->assertMatchesRegularExpression(
            '~animation:\s*dim-poster-pan-in 600ms var\(--motion-ease\) 180ms forwards,\s*'
                . 'dim-poster-pan 12s linear 780ms infinite alternate;~',
            $block,
            'The lead-in waits 180ms, lasts 600ms, and the pan runs 12s each way from 780ms.'
        );
        $this->assertStringContainsString('.dim-poster--photo:not(.dim-poster--film)', $block, 'Never over a film.');
        $this->assertStringContainsString('.dim-vitrine--cards', $block, 'Never in the list view.');
        $outside = substr($scss, 0, $at) . substr($scss, $at + strlen($query) + strlen($block));
        $this->assertDoesNotMatchRegularExpression(
            '~animation:[^;]*dim-poster-pan~',
            $outside,
            'A pan declared outside the query moves pictures under a finger and under reduced motion.'
        );
    }

    /**
     * Every keyframes of the showcase is nested inside the body scope, never at the file's root.
     *
     * scssphp bubbles a nested @keyframes to the root unchanged, so the animation works; what the
     * nesting buys is that the scope walker never meets a `from` or a `to` as a top-level selector.
     */
    public function test_the_keyframes_are_nested_inside_the_body_scope(): void {
        $scss = $this->stylesheet('category.scss');
        foreach (['dim-poster-pan-in', 'dim-poster-pan', 'dim-panel-shine'] as $name) {
            $at = strpos($scss, '@keyframes ' . $name . ' {');
            $this->assertIsInt($at, 'No @keyframes ' . $name . '.');
            $depth = substr_count(substr($scss, 0, $at), '{') - substr_count(substr($scss, 0, $at), '}');
            $this->assertSame(1, $depth, '@keyframes ' . $name . ' is not directly inside the body scope.');
        }
    }

    /**
     * The poster's watermark is drawn only where a mask can be, under the text, and from the theme's pix.
     *
     * A browser without masks would paint the mark as a solid block of ink over the band; the base
     * of a band poster is its own stacking context so the mark sits over its band and under its text;
     * and the folder is a file of the theme, never a data: url, which Moodle's stylelint config forbids.
     */
    public function test_the_watermark_is_drawn_only_under_a_mask_and_under_the_text(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression('~display:\s*none;~', $this->rule_body($scss, '.dim-poster__mark'));
        $query = '@supports (mask-image: none) or (-webkit-mask-image: none) {';
        $at = strpos($scss, $query);
        $this->assertIsInt($at, 'The watermark is no longer gated on a mask.');
        $this->assertMatchesRegularExpression(
            '~\.dim-poster__base > \.dim-poster__mark \{\s*display:\s*block;~',
            $this->block_at($scss, $at + strlen($query))
        );
        $this->assertMatchesRegularExpression(
            '~isolation:\s*isolate;~',
            $this->rule_body($scss, '.dim-poster--band .dim-poster__base')
        );
        $this->assertMatchesRegularExpression(
            '~--dim-mark:\s*url\(\[\[pix:theme\|poster-folder\]\]\);~',
            $this->rule_body($scss, '.dim-poster__mark--folder')
        );
        $this->assertFileExists($this->plugin_root() . '/pix/poster-folder.svg');
        $this->assertDoesNotMatchRegularExpression('~url\(\s*["\']?data:~', $scss, 'No data: url in this stylesheet.');
    }

    /**
     * The two copies of the view toggle swap at 575.98px, one copy per width.
     *
     * display:none on the idle copy keeps it out of the tab order and the accessibility tree, and
     * the number is the grid's own phone breakpoint.
     */
    public function test_the_phone_copy_of_the_view_toggle_swaps_with_the_organisation_rows(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression(
            '~display:\s*none;~',
            $this->rule_body($scss, '.dim-toolbar__view.dim-toolbar__view--phone'),
            'From 576px up the phone copy is out of the document\'s tabs.'
        );
        $found = 0;
        $query = '@media (max-width: 575.98px) {';
        $offset = 0;
        while (($at = strpos($scss, $query, $offset)) !== false) {
            $offset = $at + strlen($query);
            $part = $this->block_at($scss, $offset);
            if (!str_contains($part, 'dim-toolbar__view--phone')) {
                continue;
            }
            $found++;
            $this->assertMatchesRegularExpression(
                '~\.dim-toolbar__row--organise \.dim-toolbar__view \{\s*display:\s*none;~',
                $part,
                'Below 576px the organisation row\'s copy goes.'
            );
            $this->assertMatchesRegularExpression(
                '~\.dim-toolbar__view\.dim-toolbar__view--phone \{[^}]*display:\s*inline-flex;~',
                $part,
                'And the phone copy comes.'
            );
            $this->assertMatchesRegularExpression(
                '~\.dim-toolbar__view\.dim-toolbar__view--phone \{[^}]*border:\s*0;[^}]*overflow:\s*visible;~',
                $part,
                'The row copy is one segment that clips; the phone copy is a boundary per button, and takes neither.'
            );
            $this->assertMatchesRegularExpression(
                '~\.dim-toolbar__filterstoggle \{\s*right:\s*97px;~',
                $part,
                'The summary sits the toggle\'s 87px and the design\'s 10px gap from the end of the line.'
            );
        }
        $this->assertSame(1, $found, 'One phone block holds the swap.');
    }

    /**
     * The organisation row reads "Organise:" inline before its track, and the count row follows the design.
     *
     * The sort group is a flex row, and its legend is floated: a floated legend is not the
     * fieldset's rendered legend, so it lays out as a flex item beside the track instead of above
     * it. Bootstrap's reboot gives every legend the whole width, which would put the label on its
     * own line, so the width is reset too. The colon is the stylesheet's, so the string stays the
     * group's name. The count row sits on one baseline with the design's gaps and the clear link
     * right after the sentence rather than pushed to the far end; an empty range-pill region is
     * out of the row, or its two gaps would double the distance. On a phone every pill and quick
     * option of the toolbar is 44px tall.
     */
    public function test_the_organise_row_labels_itself_inline_and_the_count_row_follows_the_design(): void {
        $scss = $this->stylesheet('category.scss');
        $group = $this->rule_body($scss, '.dim-toolbar__group--sort');
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $group);
        $this->assertMatchesRegularExpression('~align-items:\s*center;~', $group);
        $this->assertMatchesRegularExpression('~gap:\s*10px;~', $group, 'The design renders its label 10px before the track.');
        $legend = $this->rule_body($scss, '.dim-toolbar__group--sort > .dim-toolbar__grouplabel');
        $this->assertMatchesRegularExpression(
            '~float:\s*left;~',
            $legend,
            'A floated legend is a flex item beside the track, not the rendered legend above it.'
        );
        $this->assertMatchesRegularExpression('~width:\s*auto;~', $legend, 'Not the reboot\'s whole width.');
        $this->assertMatchesRegularExpression('~font-size:\s*\.88rem;~', $legend);
        $this->assertMatchesRegularExpression('~&::after \{\s*content:\s*":";~', $legend);
        $template = (string) file_get_contents($this->plugin_root() . '/templates/category_toolbar.mustache');
        $this->assertStringContainsString(
            '<fieldset class="dim-toolbar__group dim-toolbar__group--sort" data-region="dim-sort"',
            $template,
            'The rule is for the class the template writes on the sort group.'
        );

        $row = $this->rule_body($scss, '.dim-toolbar__row--count');
        $this->assertMatchesRegularExpression('~align-items:\s*baseline;~', $row);
        $this->assertMatchesRegularExpression('~gap:\s*4px 14px;~', $row);
        $this->assertDoesNotMatchRegularExpression(
            '~margin-left:\s*auto~',
            $this->rule_body($scss, '.dim-toolbar__clear'),
            'The clear link follows the sentence; it is not pushed to the end of the line.'
        );
        $this->assertMatchesRegularExpression('~&:empty \{\s*display:\s*none;~', $this->rule_body($scss, '.dim-toolbar__pillbar'));
        $this->assertMatchesRegularExpression('~font-size:\s*\.92rem;~', $this->rule_body($scss, '.dim-vitrine__count'));
        $number = $this->rule_body($scss, '.dim-vitrine__countnumber');
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-ink\);~', $number);
        $this->assertMatchesRegularExpression('~font-weight:\s*700;~', $number);
        $this->assertMatchesRegularExpression('~font-variant-numeric:\s*tabular-nums;~', $number);

        $phone = $this->phone_block($scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-pill,\s*\.dim-quick \{[^}]*min-height:\s*44px;~',
            $phone,
            'Every pill and quick option of the toolbar is 44px on a phone.'
        );
        $this->assertMatchesRegularExpression('~\.dim-toolbar__manage \{\s*margin-left:\s*auto;~', $phone);
        $this->assertMatchesRegularExpression(
            '~\.dim-toolbar__manage \.dropdown-toggle \{\s*min-width:\s*44px;\s*min-height:\s*44px;~',
            $phone,
            'And so is the kebab that ends the organisation row: it was the one 32px control of the toolbar.'
        );
    }

    /**
     * The Filter badge and its open outline read in both colour modes; the summary's links are 44px on a phone.
     *
     * The badge is the call to action's pair: the navy and white on the light page, #4456ff and
     * white (5.2:1) on the dark one. The page's surface is never its ink, because the institutional
     * navy is not redefined for the dark mode and the dark surface on it reads 1.1:1. The open
     * summary's line is the open poster's ring, which follows the mode the same way instead of
     * vanishing into the dark card. On a phone the area links of the "nothing in N areas" summary
     * take the 44px of every other control there.
     */
    public function test_the_filter_badge_reads_in_the_dark_and_the_summarys_links_are_44px(): void {
        $scss = $this->stylesheet('category.scss');
        $badge = $this->rule_body($scss, '.dim-toolbar__filtercount');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);~', $badge);
        $this->assertMatchesRegularExpression('~color:\s*#fff;~', $badge);
        $this->assertStringNotContainsString('--dim-surface', $badge, 'The dark surface is never the badge\'s ink.');
        $dark = ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-vitrine,\s*'
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-card__panel,\s*'
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{[^}]*';
        $this->assertMatchesRegularExpression(
            '~' . $dark . '--dim-cta:\s*var\(--dim-ds-blue-400, #4456ff\);~',
            $scss,
            'The fill follows the mode: the lighter blue on the dark page.'
        );
        $this->assertMatchesRegularExpression(
            '~border-color:\s*var\(--dim-open-ring\);~',
            $this->rule_body($scss, '.dim-toolbar__filters[open] .dim-toolbar__filterstoggle'),
            'The open summary\'s line is the open poster\'s ring, not the navy the dark card swallows.'
        );
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-open-ring:\s*var\(--dim-ds-blue-200, #adb2f0\);~', $scss);

        $found = preg_match_all('~@media \(max-width: 575\.98px\) \{(.*?)\n    \}~s', $scss, $matches);
        $this->assertGreaterThan(0, $found);
        $posters = array_values(array_filter(
            $matches[1],
            static fn(string $block): bool => str_contains($block, 'dim-areas__allemptyclear')
        ));
        $this->assertCount(1, $posters, 'One phone block holds the areas\' own controls.');
        $this->assertMatchesRegularExpression(
            '~\.dim-areas__nomatchlist \{\s*align-items:\s*center;\s*a \{\s*display:\s*inline-flex;\s*'
                . 'align-items:\s*center;\s*min-height:\s*44px;~',
            $posters[0],
            'The summary\'s area links are 44px on a phone, like every other control there.'
        );
    }

    /**
     * A poster's result pill is the design's invariant pair, and the search results' surfaces are styled.
     *
     * The pill sits on a photograph or on a band of any colour, so its two pairs are the design
     * system's primitives - never the flavour-following primary - and nothing redeclares them for
     * the dark mode: the pill is identical in both. It carries no white-space rule, because the
     * design's has none; while an answer is out it is dimmed; in the list view the row keeps clear
     * of it. An empty poster has no chevron, the "nothing in N areas" summary and the "nothing
     * anywhere" box are the design's, and the mark reads Bootstrap's own highlight pair.
     */
    public function test_the_result_pill_is_invariant_and_the_rounds_surfaces_are_styled(): void {
        $scss = $this->stylesheet('category.scss');
        $pill = $this->rule_body($scss, '.dim-poster__pill');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-pill-bg\);~', $pill);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-pill-ink\);~', $pill);
        $this->assertMatchesRegularExpression('~font-size:\s*\.78rem;~', $pill);
        $this->assertMatchesRegularExpression('~min-width:\s*3\.2em;~', $pill);
        $this->assertMatchesRegularExpression('~box-shadow:\s*0 1px 3px rgba\(0, 0, 0, \.35\);~', $pill);
        $this->assertDoesNotMatchRegularExpression('~white-space~', $pill, 'The design\'s pill wraps where it wraps.');
        $zero = $this->rule_body($scss, '.dim-poster__pill--zero');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-pill-zero-bg\);~', $zero);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-pill-zero-ink\);~', $zero);
        $tokens = [
            '--dim-pill-bg' => 'var(--dim-ds-blue-600, #070093)',
            '--dim-pill-ink' => 'var(--dim-ds-blue-050, #edeefb)',
            '--dim-pill-zero-bg' => 'var(--dim-ds-gray-800, #343a40)',
            '--dim-pill-zero-ink' => 'var(--dim-ds-gray-100, #f2f3f5)',
        ];
        foreach ($tokens as $token => $value) {
            $this->assertSame(1, substr_count($scss, $token . ':'), $token . ' is declared once, and never for the dark mode.');
            $this->assertStringContainsString($token . ': ' . $value . ';', $scss);
        }
        $this->assertMatchesRegularExpression(
            '~opacity:\s*\.7;~',
            $this->rule_body($scss, '.dim-areas__grid[aria-busy="true"] .dim-poster__pill')
        );
        $this->assertMatchesRegularExpression(
            '~padding-right:\s*164px;~',
            $this->rule_body($scss, '.dim-vitrine--list .dim-poster__card:has(.dim-poster__pill:not([hidden]))'),
            'The list view\'s row keeps clear of the pill.'
        );
        $this->assertMatchesRegularExpression(
            '~display:\s*none;~',
            $this->rule_body($scss, '.dim-poster--empty .dim-poster__chev'),
            'An area with nothing to open has no chevron.'
        );

        $nomatch = $this->rule_body($scss, '.dim-areas__nomatch');
        $this->assertMatchesRegularExpression('~margin-top:\s*16px;~', $nomatch);
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-surface-subtle\);~', $nomatch);
        $this->assertMatchesRegularExpression('~scroll-margin-top:\s*calc\(var\(--dim-navbar\) \+ 48px\);~', $nomatch);
        $empty = $this->rule_body($scss, '.dim-areas__allempty');
        $this->assertMatchesRegularExpression('~border:\s*1\.5px dashed var\(--dim-control-line\);~', $empty);
        $this->assertMatchesRegularExpression('~padding:\s*28px 20px;~', $empty);
        $suggestion = $this->rule_body($scss, '.dim-panel__suggestion');
        $this->assertMatchesRegularExpression('~min-height:\s*34px;~', $suggestion);
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--radius-pill, 50rem\);~', $suggestion);

        $mark = $this->rule_body($scss, 'mark.dim-mark');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-mark-bg\);~', $mark);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-mark-ink\);~', $mark);
        $this->assertMatchesRegularExpression('~padding:\s*0 1px;~', $mark);
        $this->assertStringContainsString('--dim-mark-bg: var(--bs-highlight-bg, #fff3cd);', $scss);
        $this->assertStringContainsString('--dim-mark-ink: var(--bs-highlight-color, var(--dim-ink));', $scss);
    }

    /**
     * The open area wears its own colour: the poster's ring, and the panel's rail, head, glow and arrow (round I).
     *
     * Variant B of the round's mockup, ported without color-mix(): the band is the area's own
     * custom property, painted whole or as a layer at an opacity, with a one-pixel edge beside the
     * ring and a white lift over the rail that only the dark page switches on. The default band of
     * the panel is the open ring, declared where it is read.
     *
     * Changes that must make it fail: the ring back to the open ring; the rail, the tint or the glow
     * dropped; the lift switched on (or off) in the wrong mode; the arrow's tint moved off the arrow.
     */
    public function test_the_open_area_wears_its_own_colour_on_the_ring_and_the_panel(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression(
            '~box-shadow:\s*0 0 0 3px var\(--dim-card-band\), 0 0 0 4px var\(--dim-area-edge\), var\(--shadow-lg\);~',
            $this->rule_body($scss, '.dim-poster--open .dim-poster__card'),
            'The open poster\'s ring is its own band with the edge outside it.'
        );
        $this->assertMatchesRegularExpression(
            '~box-shadow:\s*0 0 0 3px var\(--dim-card-band\), 0 0 0 4px var\(--dim-area-edge\);~',
            $this->rule_body($scss, '.dim-vitrine--list .dim-poster--open .dim-poster__card'),
            'And the list view\'s open row.'
        );
        $this->assertMatchesRegularExpression('~--dim-area-bg:\s*var\(--dim-open-ring\);~', $this->rule_body($scss, '.dim-panel'));

        $box = $this->rule_body($scss, '.dim-panel__box');
        $this->assertMatchesRegularExpression('~border-inline-start:\s*6px solid var\(--dim-area-bg\);~', $box, 'The rail.');
        $this->assertMatchesRegularExpression('~isolation:\s*isolate;~', $box);
        $tint = $this->rule_body($scss, '.dim-panel__box::before');
        $this->assertMatchesRegularExpression(
            '~background:\s*linear-gradient\(to bottom, var\(--dim-area-bg\), transparent 110px\);~',
            $tint
        );
        $this->assertMatchesRegularExpression('~opacity:\s*var\(--dim-area-tint\);~', $tint);
        $this->assertMatchesRegularExpression('~z-index:\s*-1;~', $tint, 'Under the content, over the box\'s own surface.');
        $lift = $this->rule_body($scss, '.dim-panel__box::after');
        $this->assertMatchesRegularExpression('~inset-inline-start:\s*-6px;~', $lift, 'Exactly over the rail.');
        $this->assertMatchesRegularExpression('~opacity:\s*var\(--dim-area-lift\);~', $lift);
        $glow = $this->rule_body($scss, '.dim-panel__inner::before');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-area-bg\);~', $glow);
        $this->assertMatchesRegularExpression('~filter:\s*blur\(12px\);~', $glow);
        $inner = $this->rule_body($scss, '.dim-panel__inner');
        $this->assertMatchesRegularExpression('~margin-bottom:\s*-16px;~', $inner, 'The glow\'s room, given back.');
        $this->assertMatchesRegularExpression('~padding-bottom:\s*16px;~', $inner);
        $this->assertMatchesRegularExpression('~overflow:\s*hidden;~', $inner, 'Still the clip of the grow.');

        // The arrow's tint is the same square in the same place as the arrow, in both views.
        $arrow = $this->rule_body($scss, '.dim-panel::before');
        $layer = $this->rule_body($scss, '.dim-panel::after');
        preg_match('~\bleft:\s*([^;]+);~', $arrow, $left);
        $this->assertStringContainsString('left: ' . $left[1] . ';', $layer);
        $this->assertMatchesRegularExpression('~opacity:\s*var\(--dim-area-tint\);~', $layer);
        $this->assertMatchesRegularExpression('~left:\s*30px;~', $this->rule_body($scss, '.dim-vitrine--list .dim-panel::after'));
        $this->assertMatchesRegularExpression(
            '~\.dim-panel--shut::before,\s*\.dim-panel--shut::after,\s*'
                . '\.dim-panel--shut \.dim-panel__inner::before \{\s*opacity:\s*0;~',
            $scss,
            'A shut panel shows no arrow, no tinted arrow and no glow.'
        );

        // The three tokens: off on the light page, on in the dark.
        $this->assertStringContainsString('--dim-area-edge: rgba(0, 0, 0, .4);', $scss);
        $this->assertStringContainsString('--dim-area-lift: 0;', $scss);
        $this->assertStringContainsString('--dim-area-tint: .1;', $scss);
        $dark = ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-vitrine,\s*'
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-card__panel,\s*'
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{[^}]*';
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-area-edge:\s*rgba\(255, 255, 255, \.45\);~', $scss);
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-area-lift:\s*\.45;~', $scss);
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-area-tint:\s*\.16;~', $scss);
    }

    /**
     * While a panel is open the other posters go grey under the vignette, and the open one says so in words.
     *
     * The owner's round I: image AND text dim (the earlier rule dimmed only the images), easing in
     * and out on the card's own transition, lifting most of the way under a pointer and all the way
     * under the focus; the list view greys and fades instead. The vignette is the card's last box,
     * transparent to the pointer. The open poster carries "Open", shown by its open class alone.
     */
    public function test_the_other_posters_dim_under_a_vignette_and_the_open_one_says_so(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertDoesNotMatchRegularExpression(
            '~\.dim-areas--open [^{]*\.dim-poster__media[^{]*\{~',
            $scss,
            'The images-only spotlight is gone: the dimming is the whole card\'s.'
        );

        $vignette = $this->rule_body($scss, '.dim-poster__card::after');
        $this->assertMatchesRegularExpression('~background:\s*radial-gradient\(~', $vignette);
        $this->assertMatchesRegularExpression('~opacity:\s*0;~', $vignette, 'Transparent at rest.');
        $this->assertMatchesRegularExpression('~pointer-events:\s*none;~', $vignette, 'The poster under it still opens.');
        $this->assertMatchesRegularExpression('~z-index:\s*2;~', $vignette, 'Over the text and the stretched control.');
        $this->assertMatchesRegularExpression('~transition:\s*opacity var\(--motion-base\) var\(--motion-ease\);~', $vignette);
        $this->assertMatchesRegularExpression(
            '~filter var\(--motion-base\) var\(--motion-ease\)~',
            $this->rule_body($scss, '.dim-poster__card'),
            'The grey eases in and out with the card.'
        );

        $other = '.dim-vitrine--cards .dim-areas--open .dim-poster:not(.dim-poster--open) .dim-poster__card';
        $this->assertMatchesRegularExpression('~filter:\s*grayscale\(\.85\);~', $this->rule_body($scss, $other));
        $this->assertMatchesRegularExpression('~opacity:\s*1;~', $this->rule_body($scss, $other . '::after'));
        $this->assertMatchesRegularExpression('~filter:\s*grayscale\(\.3\);~', $this->rule_body($scss, $other . ':hover'));
        $this->assertMatchesRegularExpression('~opacity:\s*\.35;~', $this->rule_body($scss, $other . ':hover::after'));
        $focus = '.dim-vitrine .dim-areas.dim-areas--open .dim-poster:not(.dim-poster--open) .dim-poster__card:focus-within';
        $this->assertMatchesRegularExpression('~filter:\s*none;~', $this->rule_body($scss, $focus), 'The focus lifts all of it.');
        $this->assertMatchesRegularExpression('~opacity:\s*0;~', $this->rule_body($scss, $focus . '::after'));
        $listother = '.dim-vitrine--list .dim-areas--open .dim-poster:not(.dim-poster--open) .dim-poster__card';
        $this->assertMatchesRegularExpression('~filter:\s*grayscale\(\.85\);~', $this->rule_body($scss, $listother));
        $this->assertMatchesRegularExpression('~opacity:\s*\.7;~', $this->rule_body($scss, $listother));

        $this->assertMatchesRegularExpression('~display:\s*none;~', $this->rule_body($scss, '.dim-poster__open'));
        $this->assertMatchesRegularExpression(
            '~display:\s*inline-flex;~',
            $this->rule_body($scss, '.dim-poster--open .dim-poster__open')
        );

        // Nothing of it moves for a reader who asked for less movement.
        $query = '@media (prefers-reduced-motion: reduce) {';
        $blocks = [];
        $offset = 0;
        while (($at = strpos($scss, $query, $offset)) !== false) {
            $blocks[] = $this->block_at($scss, $at + strlen($query));
            $offset = $at + 1;
        }
        $reduced = implode("\n", $blocks);
        foreach (['.dim-poster__card::after,', '.dim-panel::after,', '.dim-panel__inner::before,'] as $selector) {
            $this->assertStringContainsString($selector, $reduced, $selector . ' still moves under reduced motion.');
        }
    }

    /**
     * A compact or folded hero keeps its crests on the title's line at 36x40, and 26x29 on a phone (round I).
     */
    public function test_a_compact_hero_keeps_small_crests_on_the_title_line(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression(
            '~flex-wrap:\s*nowrap;~',
            $this->rule_body($scss, '.dim-hero--compact .dim-hero__inner')
        );
        $badge = $this->rule_body($scss, '.dim-hero--compact .dim-hero__badge');
        $this->assertMatchesRegularExpression('~img \{\s*width:\s*36px;\s*height:\s*40px;~', $badge);
        $this->assertMatchesRegularExpression(
            '~width:\s*80px;\s*height:\s*88px;~',
            $this->rule_body($scss, '.dim-hero__badge'),
            'The full band.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\) \{\s*\.dim-hero--compact \.dim-hero__badges \{[^}]*\}\s*'
                . '\.dim-hero--compact \.dim-hero__badge img \{\s*width:\s*26px;\s*height:\s*29px;~',
            $scss
        );
    }

    /**
     * The course dialogue reads the plugin's tokens on the root core/modal appends to the body, and every rule names it.
     *
     * core/modal appends its root to the BODY, outside .dim-vitrine, so a token declared only on the listing's roots
     * is undeclared inside the dialogue and every var() reading it is an invalid declaration, in both modes. Bootstrap's
     * modal is re-pointed through its own variables (the width, the surface, the radius) and never outranked; the
     * entrance runs only where motion is allowed; a phone gets a bottom sheet the height of the screen less 12px.
     */
    public function test_the_course_dialogue_declares_its_tokens_on_its_own_root(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression(
            '~\n    \.dim-vitrine,\s*\.dim-card__panel,\s*\.dim-cmodal \{\s*--dim-surface:~',
            $scss,
            'The listing\'s tokens are declared on the dialogue\'s root as well.'
        );
        foreach (['--dim-badge-enrolled-bg:', '--dim-cta:', '--dim-primary-ink:'] as $token) {
            $this->assertMatchesRegularExpression(
                '~:root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal[,\s][^}]*' . preg_quote($token, '~') . '~',
                $scss,
                "And so is the dark half of {$token}."
            );
        }

        $root = $this->rule_body($scss, '.dim-cmodal.modal');
        $this->assertMatchesRegularExpression('~--bs-modal-width:\s*780px;~', $root);
        $this->assertMatchesRegularExpression('~--bs-modal-bg:\s*var\(--dim-surface\);~', $root);
        $this->assertMatchesRegularExpression('~--dim-cmodal-cover:\s*280px;~', $root);
        $this->assertMatchesRegularExpression(
            '~--dim-cmodal-cover-end:\s*var\(--dim-ds-blue-700, #050073\);~',
            $root,
            'Light: navy.'
        );
        $this->assertMatchesRegularExpression(
            '~:root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{\s*--dim-cmodal-cover-end:\s*var\(--dim-surface\);~',
            $scss,
            'Dark: the dialogue\'s own surface.'
        );
        $cover = $this->rule_body($scss, '.dim-cmodal__cover');
        $this->assertMatchesRegularExpression('~height:\s*var\(--dim-cmodal-cover\);~', $cover);
        $this->assertMatchesRegularExpression('~border-bottom:\s*3px solid var\(--dim-cmodal-signature\);~', $cover);
        $this->assertMatchesRegularExpression(
            '~linear-gradient\(\s*to bottom,\s*var\(--dim-cmodal-shade\),\s*transparent 26%,\s*transparent 38%,\s*'
                . 'var\(--dim-cmodal-cover-end\)\s*\)~',
            $cover,
            'The cover fades into the end colour.'
        );

        // Every rule of the dialogue is qualified by a class of its own: Bootstrap's bare .modal rules win any tie.
        preg_match_all('~^\s*([^{}\n;]*\.modal-(?:dialog|content|body|footer|backdrop)[^{}\n]*)\{~m', $scss, $rules);
        $this->assertNotEmpty($rules[1]);
        foreach ($rules[1] as $selector) {
            $this->assertMatchesRegularExpression('~\.dim-cmodal~', $selector, 'Unqualified core modal rule: ' . $selector);
        }

        // The entrance only where motion is allowed, and only on the dialogue's own root.
        $this->assertSame(1, substr_count($scss, 'animation: dim-cmodal-in '));
        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: no-preference\) \{\s*\.dim-cmodal\.show \.modal-dialog \{\s*'
                . 'animation: dim-cmodal-in 300ms~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\) and \(prefers-reduced-motion: no-preference\) \{\s*'
                . '\.dim-cmodal\.show \.modal-dialog \{\s*animation-name: dim-cmodal-sheet;~',
            $scss
        );
        // The phone's bottom sheet.
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\) \{\s*\.dim-cmodal\.modal \{[^}]*--bs-modal-margin:\s*0;[^}]*\}\s*'
                . '\.dim-cmodal \.modal-dialog \{\s*height:\s*calc\(100% - 12px\);\s*margin-top:\s*12px;~',
            $scss
        );

        // The backdrop is core's shared one: tinted only while this dialogue is shown.
        $this->assertMatchesRegularExpression('~&:has\(\.dim-cmodal\.show\) \.modal-backdrop \{~', $scss);
    }

    /**
     * The body of the block that opens at an offset: from there to its matching closing brace.
     *
     * @param string $scss The stylesheet, comments already removed.
     * @param int $start The offset just after the block's opening brace.
     * @return string The block's body.
     */
    private function block_at(string $scss, int $start): string {
        $depth = 1;
        $length = strlen($scss);
        for ($i = $start; $i < $length; $i++) {
            if ($scss[$i] === '{') {
                $depth++;
            } else if ($scss[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($scss, $start, $i - $start);
                }
            }
        }
        $this->fail('The block is not closed.');
    }

    /**
     * The whole body of one rule of a stylesheet, nested rules included.
     *
     * A regex of the shape `selector \{([^}]*)\}` stops at the first closing brace, which is the
     * end of the first nested rule rather than of the rule asked for - so an assertion made over
     * it silently reads none of the states (&:hover, &:focus-visible) this file is largely
     * about. The braces are walked instead, and the rule is matched at the start of its own line
     * so that a compound selector ending in the same name cannot answer for it.
     *
     * @param string $scss The stylesheet, comments already removed.
     * @param string $selector The selector, exactly as it is written.
     * @return string The body between the rule's own braces.
     */
    private function rule_body(string $scss, string $selector): string {
        $needle = $selector . ' {';
        $offset = 0;
        while (($at = strpos($scss, $needle, $offset)) !== false) {
            $linestart = strrpos(substr($scss, 0, $at), "\n");
            $indent = $linestart === false ? substr($scss, 0, $at) : substr($scss, $linestart + 1, $at - $linestart - 1);
            if (trim($indent) === '') {
                $start = $at + strlen($needle);
                $depth = 1;
                $length = strlen($scss);
                for ($i = $start; $i < $length; $i++) {
                    if ($scss[$i] === '{') {
                        $depth++;
                    } else if ($scss[$i] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            return substr($scss, $start, $i - $start);
                        }
                    }
                }
            }
            $offset = $at + 1;
        }
        $this->fail('No rule for ' . $selector . ' in the stylesheet.');
    }

    /**
     * The extractor really returns one whole rule, nested states included.
     *
     * Without this, every assertion that reads a hover or a focus ring out of a rule could be
     * passing over a body that stops at the first nested brace, as a `[^}]*` regex does.
     */
    public function test_the_rule_extractor_returns_a_whole_rule_and_its_nested_states(): void {
        $scss = $this->stylesheet('category.scss');
        $summary = $this->rule_body($scss, '.dim-toolbar__filterstoggle');
        $this->assertStringContainsString('&::-webkit-details-marker', $summary, 'The first nested rule.');
        $this->assertStringContainsString('&:focus-visible', $summary, 'And one past it, which a regex would have lost.');
        $this->assertStringNotContainsString(
            '.dim-toolbar__filterslabel',
            $summary,
            'And it stops at the rule\'s own closing brace rather than running into the next one.'
        );
    }

    /**
     * The block below the md breakpoint that holds the listing's own narrow rules.
     *
     * Several queries share that breakpoint, so this one is found by the tree rule it holds.
     *
     * @param string $scss The stylesheet, comments already removed.
     * @return string The block's body.
     */
    private function narrow_block(string $scss): string {
        $found = preg_match_all('~@media \\(max-width: 767\\.98px\\) \\{(.*?)\\n    \\}~s', $scss, $matches);
        $this->assertGreaterThan(0, $found, 'The stylesheet has no md media query at all any more.');
        foreach ($matches[1] as $block) {
            if (str_contains($block, 'dim-vitrine__tree')) {
                return $block;
            }
        }
        $this->fail('No md media query holds the listing\'s own narrow rules.');
    }

    /**
     * The block from the md breakpoint up that lays the filter panel out.
     *
     * Several queries share that breakpoint, so this one is found by the filter panel rule it
     * holds rather than by its position.
     *
     * @param string $scss The stylesheet, comments already removed.
     * @return string The block's body.
     */
    private function wide_block(string $scss): string {
        $found = preg_match_all('~@media \(min-width: 768px\) \{(.*?)\n    \}~s', $scss, $matches);
        $this->assertGreaterThan(0, $found, 'The stylesheet has no md media query at all any more.');
        foreach ($matches[1] as $block) {
            if (str_contains($block, 'dim-toolbar__panel')) {
                return $block;
            }
        }
        $this->fail('No md media query holds the filter panel\'s own columns.');
    }

    /**
     * The block below the sm breakpoint that holds the toolbar's phone rules.
     *
     * Several queries share that breakpoint, so this one is found by the toolbar rule it holds.
     *
     * @param string $scss The stylesheet, comments already removed.
     * @return string The block's body.
     */
    private function phone_block(string $scss): string {
        $found = preg_match_all('~@media \(max-width: 575\.98px\) \{(.*?)\n    \}~s', $scss, $matches);
        $this->assertGreaterThan(0, $found, 'The stylesheet has no sm media query at all any more.');
        foreach ($matches[1] as $block) {
            if (str_contains($block, 'dim-toolbar__filterslabel')) {
                return $block;
            }
        }
        $this->fail('No sm media query holds the toolbar\'s own phone rules.');
    }

    /**
     * No Bootstrap 4 only class name reaches the browser from the showcase's files.
     */
    public function test_no_bootstrap_4_only_class_name_reaches_the_browser(): void {
        $patterns = [
            '~(?<![\w-])(m[lr]|p[lr])-(0|1|2|3|4|5|auto)(?![\w-])~' => 'the ms-/me-/ps-/pe- spacers',
            '~(?<![\w-])text-(left|right)(?![\w-])~' => 'text-start / text-end',
            '~(?<![\w-])float-(left|right)(?![\w-])~' => 'float-start / float-end',
            '~(?<![\w-])sr-only(?![\w-])~' => 'visually-hidden',
            '~(?<![\w-])no-gutters(?![\w-])~' => 'g-0',
            '~(?<![\w-])border-(left|right)(?![\w-])~' => 'border-start / border-end',
            '~(?<![\w-])rounded-(left|right)(?![\w-])~' => 'rounded-start / rounded-end',
            '~(?<![\w-])font-weight-(bold|bolder|normal|light|lighter)(?![\w-])~' => 'fw-*',
            '~(?<![\w-])text-monospace(?![\w-])~' => 'font-monospace',
            '~(?<![\w-])custom-select(?![\w-])~' => 'form-select',
            '~(?<![\w-])form-row(?![\w-])~' => 'row g-*',
            '~(?<![\w-])badge-(primary|secondary|success|info|warning|danger|light|dark)(?![\w-])~' =>
                'badge bg-* with a text utility',
            '~data-toggle=~' => 'data-bs-toggle',
        ];
        $files = $this->markup_files();
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            foreach (file($file) as $number => $line) {
                $trimmed = ltrim($line);
                if (
                    $trimmed === ''
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*')
                    || str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '{{!')
                ) {
                    continue;
                }
                foreach ($patterns as $pattern => $instead) {
                    $this->assertDoesNotMatchRegularExpression(
                        $pattern,
                        $line,
                        basename($file) . ':' . ($number + 1) . ' writes a Bootstrap 4 name; use ' . $instead . '.'
                    );
                }
            }
        }
    }
}
