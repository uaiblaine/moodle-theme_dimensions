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
 * Bootstrap 5 spellings only - the Bootstrap 4 names resolve on Moodle 5.x only
 * through a compatibility layer Moodle 6.0 removes.
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
     * Moodle 5.3's single dark-mode mechanism is the data-bs-theme attribute on the html
     * element, which is an ancestor of the body: a rule that only applies in dark mode has
     * to name it before the body class. Nothing else may precede the scope.
     *
     * @var string
     */
    private const DARK_PREFIX = ':root[data-bs-theme="dark"] ';

    /** @var string The second half of the card's transition while motion is welcome: the surface a preview recedes. */
    private const PREVIEW_EASE = 'background-color var(--dim-card-transition) var(--dim-ease)';

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
        // The shared listing's painters, in TypeScript: they write pills, chips and the count's marker.
        $files = array_merge($files, glob($root . '/js/esm/src/listing/*.ts'));
        /* The calendar's component does not match the globs, and it writes every class of its
           month grid and its day cells. A file the scanner cannot see is one the Bootstrap rules do
           not apply to, which is why the membership test below names it. */
        $files[] = $root . '/js/esm/src/daterange/DateRange.tsx';
        /* Nor does the cover film's: its entry writes the body class that reveals the cluster, and
           the film the band's playing class and the Font Awesome glyph each toggle swaps to. */
        $files[] = $root . '/amd/src/covervideo.js';
        $files[] = $root . '/amd/src/local/coverfilm.js';
        /* The refusal page, what a reader gets instead of a listing, does not match the globs
           either. The page scripts (category.php, categoryhero.php) and the editor's form
           (classes/form/categoryhero.php) write no class name: the form builds moodleform
           elements and the scripts add at most a body class. Add a file here the day it writes one. */
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
            'paint.ts',
            'account.ts',
            'star.ts',
            'categorycoursemodal.js',
            'category_card_modal.mustache',
            'category_course_modal.mustache',
            'category_areas.mustache',
            'category_panel.mustache',
            'category_directory_card.mustache',
            'category_panel.php',
            'categoryhero.js',
            'DateRange.tsx',
            'covervideo.js',
            'coverfilm.js',
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
            '~--dim-card-cover-height:\s*var\(--dimensions-card-cover-height, 150px\);~',
            $scss,
            'The media band is the contract\'s cover height, 150px where the family block is absent.'
        );
        $card = $this->rule_body($scss, '.dim-card');
        $this->assertMatchesRegularExpression(
            '~--dim-card-crest-w:\s*56px;\s*--dim-card-crest-h:\s*62px;~',
            $card,
            'The crests are 56 x 62 on a card.'
        );
        $this->assertSame(1, substr_count($scss, '--dim-card-crest-w: 56px;'), 'At every width: no query steps them.');
        $this->assertMatchesRegularExpression(
            '~\.dim-card__media\s*\{[^}]*height:\s*var\(--dim-card-cover-height\);~',
            $scss,
            'And the band reads that property rather than repeating the number.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-card__crests\s*\{[^}]*top:\s*calc\(var\(--dim-card-cover-height\) - var\(--dim-card-crest-h\) - 8px\);~',
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
     * Core's and Boost Union's static ancestors of #region-main set none (the body takes overflow:
     * hidden only while a drawer is open at md and below, or a modal locks the scroll, when the
     * tree is covered anyway); this test holds the half that
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
            (string) file_get_contents($this->plugin_root() . '/amd/src/local/coverfilm.js')
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
     * The visitor's shell (js/esm/src/listing/visitor.ts) marks the body dim-public-js, never dim-js. The twin
     * of dim-js's rule hides the same no-JavaScript submits, because the module drives the same controls in place; load more
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
     * The option tracks wrap inside the box they are in, and never widen the page.
     *
     * The Design System's filter panel lays a group's options out in a row that wraps, 6px apart
     * (moodle-dev/docs/design-system/components/filter-panel.md): every option stays in view at every
     * width, where a sideways scroll hid the last ones behind a scrollbar an overlay platform draws
     * only once it is moving, and clipped the focus ring drawn outside an option. The automatic
     * minimum size of a flex item is its content, so the disclosure's min-width: 0 stays part of the
     * rule: without it the track's widest option could still set the disclosure's width on a phone.
     *
     * Changes that must make it fail: the wrap taken off the track (gate ds_bar_option_track_wraps); a
     * scroll put back on it; the disclosure's min-width deleted.
     */
    public function test_the_option_tracks_wrap_inside_their_box_and_never_widen_the_page(): void {
        $scss = $this->stylesheet('category.scss');

        $track = $this->rule_body($scss, '.dim-toolbar__pills');
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $track, 'A row of options.');
        $this->assertMatchesRegularExpression('~flex-wrap:\s*wrap;~', $track, 'That wraps onto further lines.');
        $this->assertMatchesRegularExpression('~gap:\s*\.375rem;~', $track, 'Six pixels between options.');
        $this->assertMatchesRegularExpression('~min-width:\s*0;~', $track);
        $this->assertDoesNotMatchRegularExpression(
            '~overflow|scrollbar-width|max-width~',
            $track,
            'No scroll container: it would hide the last options and clip the focus ring.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~background|border-radius~',
            $track,
            'No band behind the options: the track draws no background and no radius.'
        );

        $filters = $this->rule_body($scss, '.dim-toolbar__filters');
        $this->assertMatchesRegularExpression(
            '~min-width:\s*0;~',
            $filters,
            'Without this the track\'s own content sets the disclosure\'s width and the page overflows.'
        );
        $this->assertStringNotContainsString(
            '.dim-toolbar__pills {',
            $this->wide_block($scss),
            'The track wraps at every width, so the md query holds no fallback for it.'
        );
    }

    /**
     * The toolbar's controls wear the fleet Design System's measures, and each chosen face reads in both colour modes.
     *
     * The values are moodle-dev/docs/design-system's (filter-bar.md, segmented-control.md, icon-button.md,
     * filter-panel.md, chip.md, counter.md), each family name read through this file's alias: brand-tint
     * --dim-brand-tint, brand-edge --dim-brand-edge, brand-ink --dim-primary-ink, surface-alt --dim-surface-subtle,
     * control-line --dim-control-line, radius sm, md and lg --dim-radius-sm, --dim-radius-md and --dim-radius.
     * A panel option is a 28px rectangle at .8125rem / 500; chosen, it takes the chip's look, the brand tone's
     * ground, edge and ink, never a solid fill; so does the quick option in force. The bar's icon buttons sit in
     * capsules (icon-button.md): no border, transparent and muted at rest, the button's 8px corners, the
     * alternative surface on hover and the brand tone pressed or open; core's kebab is drawn as one. The cluster
     * is the inset ground of the result count and the capsule; a capsule is the page's surface on a hairline at
     * 12px corners with the small elevation and the one named 3px padding. The Filters panel's foot is a hairline
     * over core's small buttons, held at the bar's 32px.
     *
     * The Filters badge and the calendar's two ends keep the call to action's pair: --dim-cta is the brand
     * primary on the light page and #4456ff on the dark one, each with its own ink, because the page's surface is
     * no ink on a primary that does not turn.
     */
    public function test_the_toolbar_controls_wear_the_designs_skin(): void {
        $scss = $this->stylesheet('category.scss');

        $toolbar = $this->rule_body($scss, '.dim-toolbar');
        $this->assertMatchesRegularExpression('~gap:\s*\.5rem;~', $toolbar, 'The bar\'s rows are 8px apart.');
        $this->assertDoesNotMatchRegularExpression(
            '~padding|border|background~',
            $toolbar,
            'The toolbar is no card: the design\'s controls sit on the page itself.'
        );

        $option = $this->rule_body($scss, '.dim-pill');
        $declarations = [
            'min-height:\s*1\.75rem;',
            'padding:\s*0 \.5rem;',
            'gap:\s*\.375rem;',
            'border:\s*1px solid var\(--dim-control-line\);',
            'border-radius:\s*var\(--dim-radius-sm\);',
            'background:\s*var\(--dim-surface\);',
            'color:\s*var\(--dim-ink\);',
            'font-size:\s*\.8125rem;',
            'font-weight:\s*500;',
        ];
        foreach ($declarations as $declaration) {
            $this->assertMatchesRegularExpression('~' . $declaration . '~', $option, 'The panel option: ' . $declaration);
        }
        $this->assertStringNotContainsString('--dim-radius-pill', $option, 'An option is a rectangle, never a pill.');
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*background:\s*var\(--dim-surface-subtle\);\s*color:\s*var\(--dim-ink\);~',
            $option,
            'The option keeps its hover background, with its ink.'
        );
        $checked = $this->rule_body($scss, '.dim-pill__input:checked + .dim-pill');
        $this->assertMatchesRegularExpression('~border-color:\s*var\(--dim-brand-edge\);~', $checked);
        $this->assertMatchesRegularExpression(
            '~background:\s*var\(--dim-brand-tint\);~',
            $checked,
            'The chosen option takes the brand tone\'s ground, never a solid fill.'
        );
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-primary-ink\);~', $checked, 'The ink follows the ground.');
        $this->assertDoesNotMatchRegularExpression('~--dim-cta|box-shadow~', $checked, 'No solid fill, no raised face.');
        $focus = $this->rule_body($scss, '.dim-pill__input:focus-visible + .dim-pill');
        $this->assertMatchesRegularExpression('~outline:\s*3px solid var\(--dim-ring\);\s*outline-offset:\s*2px;~', $focus);
        $count = $this->rule_body($scss, '.dim-pill__count');
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-muted\);\s*font-size:\s*\.75rem;\s*font-weight:\s*600;\s*font-variant-numeric:\s*tabular-nums;~',
            $count,
            'The quiet counter: .75rem at 600 in the muted ink.'
        );
        $this->assertStringNotContainsString('opacity', $count, 'Quiet by its ink, not by fading.');
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-primary-ink\);~',
            $this->rule_body($scss, '.dim-pill__input:checked + .dim-pill .dim-pill__count'),
            'A chosen option\'s count takes its brand ink.'
        );

        $quick = $this->rule_body($scss, '.dim-quick');
        $this->assertMatchesRegularExpression('~min-height:\s*1\.75rem;\s*padding:\s*0 \.5rem;~', $quick, 'The option\'s box.');
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--dim-radius-sm\);~', $quick);
        $this->assertMatchesRegularExpression('~font-size:\s*\.8125rem;\s*font-weight:\s*500;~', $quick);
        $quickon = $this->rule_body($scss, '.dim-quick--on');
        $this->assertMatchesRegularExpression(
            '~border-color:\s*var\(--dim-brand-edge\);\s*background:\s*var\(--dim-brand-tint\);\s*'
                . 'color:\s*var\(--dim-primary-ink\);~',
            $quickon,
            'The quick option in force is a chosen option.'
        );
        $this->assertStringNotContainsString('--dim-surface', $quickon, 'The page\'s surface is never its ink.');
        $end = $this->rule_body($scss, '.dim-daterange__day--end');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);\s*color:\s*var\(--dim-cta-ink\);~', $end);
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*background:\s*var\(--dim-cta\);\s*color:\s*var\(--dim-cta-ink\);~',
            $end,
            'An end under the pointer keeps its pair: the plain day\'s pale hover would leave a white digit on it.'
        );

        $icon = $this->rule_body($scss, '.dim-line__iconbtn');
        $this->assertMatchesRegularExpression(
            '~min-width:\s*var\(--dim-bar-inner\);\s*height:\s*var\(--dim-bar-inner\);\s*padding:\s*0 \.375rem;\s*'
                . 'border:\s*0;\s*border-radius:\s*var\(--dim-radius-md\);\s*background:\s*transparent;\s*'
                . 'color:\s*var\(--dim-muted\);\s*font-size:\s*1rem;~',
            $icon,
            'The icon button: the bar\'s step, no border, transparent and muted at rest, 8px corners, a 16px glyph.'
        );
        $this->assertMatchesRegularExpression(
            '~&:hover \{\s*background:\s*var\(--dim-surface-subtle\);\s*color:\s*var\(--dim-ink\);~',
            $icon
        );
        $pressed = '~background:\s*var\(--dim-brand-tint\);\s*color:\s*var\(--dim-primary-ink\);~';
        $this->assertMatchesRegularExpression(
            '~&\[aria-pressed="true"\],\s*&\[aria-expanded="true"\] \{\s*background:\s*var\(--dim-brand-tint\);\s*'
                . 'color:\s*var\(--dim-primary-ink\);~',
            $icon,
            'Pressed or open, the brand tone.'
        );
        $this->assertMatchesRegularExpression(
            $pressed,
            $this->rule_body(
                $scss,
                ".dim-toolbar__filters[open] > .dim-line__iconbtn,\n    .dim-line__date[open] > .dim-line__iconbtn,\n"
                    . "    .dim-line__organise[open] > .dim-line__iconbtn"
            ),
            'An open disclosure\'s summary wears the pressed face.'
        );
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-star-on\);~',
            $this->rule_body($scss, '.dim-toolbar__star[aria-pressed="true"] .fa-star'),
            'The star, pressed, in the card star\'s gold.'
        );
        $badge = $this->rule_body($scss, '.dim-toolbar__filtercount');
        $this->assertMatchesRegularExpression(
            '~position:\s*absolute;\s*top:\s*-\.25rem;\s*right:\s*-\.25rem;\s*min-width:\s*1rem;\s*height:\s*1rem;\s*'
                . 'padding:\s*0 \.25rem;~',
            $badge,
            'The loud counter on the control\'s corner, at every width.'
        );
        $this->assertMatchesRegularExpression(
            '~font-size:\s*\.75rem;\s*font-weight:\s*700;\s*line-height:\s*1rem;~',
            $badge,
            'The loud counter: 16px at .75rem / 700.'
        );

        $cluster = $this->rule_body($scss, '.dim-line__cluster');
        $this->assertMatchesRegularExpression(
            '~border-radius:\s*var\(--dim-radius\);\s*background:\s*var\(--dim-surface-strong\);~',
            $cluster,
            'The cluster is one inset ground at the capsule\'s 12px corners.'
        );
        $this->assertMatchesRegularExpression(
            '~padding:\s*0 \.5rem 0 \.75rem;\s*color:\s*var\(--dim-muted\);\s*font-size:\s*\.875rem;\s*font-weight:\s*600;\s*'
                . 'font-variant-numeric:\s*tabular-nums;~',
            $this->rule_body($scss, '.dim-line__total'),
            'The result count: quiet, .875rem / 600, tabular.'
        );
        $capsule = $this->rule_body($scss, '.dim-line__capsule');
        $this->assertMatchesRegularExpression(
            '~gap:\s*\.125rem;\s*padding:\s*\.1875rem;\s*border:\s*1px solid var\(--dim-line\);\s*'
                . 'border-radius:\s*var\(--dim-radius\);\s*background:\s*var\(--dim-surface\);\s*'
                . 'box-shadow:\s*var\(--dim-elevation-sm\);~',
            $capsule,
            'A capsule: 2px between its buttons, the named 3px padding, a hairline, 12px corners, the small elevation.'
        );
        $this->assertStringNotContainsString('position', $capsule, 'Not positioned: its layers hang from the line.');

        $view = $this->rule_body($scss, '.dim-toolbar__view');
        $this->assertDoesNotMatchRegularExpression(
            '~border|background|overflow~',
            $view,
            'The view toggle is two of the capsule\'s icon buttons, no segmented box of its own.'
        );
        $this->assertMatchesRegularExpression('~gap:\s*\.125rem;~', $view);

        $kebab = $this->rule_body($scss, '.dim-toolbar__manage .dropdown-toggle');
        $this->assertMatchesRegularExpression(
            '~min-width:\s*var\(--dim-bar-inner\);\s*width:\s*auto;\s*height:\s*var\(--dim-bar-inner\);\s*'
                . 'padding:\s*0 \.375rem;\s*border:\s*0;\s*border-radius:\s*var\(--dim-radius-md\);\s*'
                . 'background:\s*transparent;\s*color:\s*var\(--dim-muted\);~',
            $kebab,
            'Core\'s kebab is the search capsule\'s last icon button.'
        );
        $this->assertMatchesRegularExpression(
            '~&\[aria-expanded="true"\] \{\s*background:\s*var\(--dim-brand-tint\);\s*color:\s*var\(--dim-primary-ink\);~',
            $kebab
        );

        $foot = $this->rule_body($scss, '.dim-toolbar__foot');
        $this->assertMatchesRegularExpression(
            '~justify-content:\s*space-between;\s*gap:\s*\.5rem;\s*margin-left:\s*0;\s*padding-top:\s*\.75rem;\s*'
                . 'border-top:\s*1px solid var\(--dim-line\);~',
            $foot,
            'The panel\'s foot: a hairline, 12px above its buttons, 8px between them.'
        );
        $this->assertMatchesRegularExpression('~\.btn \{\s*min-height:\s*2rem;\s*\}~', $foot, 'Core\'s .btn-sm held at 32px.');
        $this->assertGreaterThan(
            strpos($scss, '.dim-toolbar__actions {'),
            strpos($scss, '.dim-toolbar__foot {'),
            'After the actions box it replaces: at equal specificity the later rule wins.'
        );

        $apply = $this->rule_body($scss, ".dim-toolbar__sortsubmit,\n    .dim-toolbar__submit");
        $this->assertMatchesRegularExpression('~min-height:\s*40px;~', $apply);
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);\s*color:\s*var\(--dim-cta-ink\);~', $apply);

        // The date layer's "Clear period" is the same text button, by construction: one rule.
        $clear = $this->rule_body($scss, ".dim-toolbar__clear,\n    .dim-datelayer__clear");
        $this->assertMatchesRegularExpression('~padding:\s*0 \.25rem;~', $clear, 'A text button, not a box.');
        $this->assertMatchesRegularExpression('~border:\s*0;\s*background:\s*none;~', $clear);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-ink\);~', $clear);
        $this->assertMatchesRegularExpression('~font-size:\s*\.8125rem;\s*font-weight:\s*600;~', $clear);
        $this->assertMatchesRegularExpression('~text-decoration:\s*underline;\s*text-underline-offset:\s*2px;~', $clear);

        $panel = $this->rule_body($scss, '.dim-toolbar__panel');
        $this->assertMatchesRegularExpression('~padding:\s*\.75rem;~', $panel);
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--dim-radius\);~', $panel, 'The panel\'s 12px corners.');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-surface\);\s*color:\s*var\(--dim-ink\);~', $panel);
        $legend = $this->rule_body($scss, '.dim-toolbar__grouplabel');
        $this->assertMatchesRegularExpression('~margin:\s*0 0 6px;~', $legend);
        $this->assertMatchesRegularExpression(
            '~font-size:\s*\.75rem;\s*font-weight:\s*600;~',
            $legend,
            'The group\'s name is the Design System\'s eyebrow: 0.75rem at 600.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~letter-spacing|text-transform~',
            $legend,
            'The eyebrow is in sentence case, as its string is written: no capitals and no tracking.'
        );

        $row = $this->rule_body($scss, '.dim-line__chips');
        $this->assertMatchesRegularExpression(
            '~margin:\s*\.25rem 0;~',
            $row,
            'The design\'s 12px above and 18px below, less the gaps already there (8px and 14px).'
        );
        $this->assertMatchesRegularExpression('~gap:\s*14px;~', $this->rule_body($scss, '.dim-vitrine'), 'The 14px it counts on.');
    }

    /**
     * Where there is room the filter groups share a row, each as wide as its own options.
     *
     * Equal grid tracks answer "how many columns", but the groups differ in width: a wide facet
     * wraps inside its track while another track stands empty. So this pins a wrapping flex row
     * whose groups neither grow nor take a basis of their own (`flex: 0 1 auto`; a group that grew
     * would be an equal column again), the foot as a row of its own, and no `display: grid`
     * or `grid-template-columns` in the block at all. Groups that do not fit one row stack, which
     * needs no rule: it is what a wrapping row does.
     *
     * Changes that must make it fail: `flex: 1 1 0` on the groups (the second assertion);
     * `display: grid` restored (the last); the foot's basis dropped (the third).
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
            '~\.dim-toolbar__panel > \.dim-toolbar__actions \{\s*flex:\s*1 1 100%;~',
            $wide,
            'The foot is a row of its own.'
        );
        $this->assertStringNotContainsString('group--date', $scss, 'The panel holds no date group any more.');
        $this->assertDoesNotMatchRegularExpression(
            '~display:\s*grid|grid-template-columns~',
            $wide,
            'Equal tracks wrapped the Status options inside 358px of a 1124px panel; they stay out.'
        );
    }

    /**
     * The filter line is one wrapping row of the bar's controls, and the Filters panel is a popover at every width.
     *
     * The line wraps where it does not fit, which is its whole narrow behaviour without
     * JavaScript, and is the positioned ancestor of the panel, which opens under the line (not under
     * its own control, which sits in a capsule) at every width and grows with its content (it is no
     * scroll container, or it would clip the date calendar), at the popover's elevation. The scope is
     * the Design System's segmented control: an inset platter at the panel's 12px corners holding cells
     * of the bar's step at 8px, the chosen one raised in the page's surface with no border. The row under
     * the line is no box at rest - display: contents, so it takes no gap of the toolbar's column - and
     * everything in it but the count sentence goes; narrowed, it wraps its chips, 28px rectangles in the
     * brand tone, 44px on a phone. The open field's floor is what the client measures the line with.
     *
     * Changes that must make it fail: the wrap dropped from the line; the line not positioned (the
     * panel is then placed against the page); the popover's absolute position dropped, or an
     * overflow or max-height given to it; the row at rest kept as a box; the chips row given its
     * sideways scroll back (gate ds_bar_chips_row_wraps); a chip rounded into a pill
     * (ds_bar_chip_rectangle); the platter's corners back at 6px (ds_bar_scope_platter_radius); a cell
     * off the bar's step (ds_bar_scope_cell_height); the open field's floor dropped (the client would
     * open it in place on a line with no room for it).
     */
    public function test_the_filter_line_wraps_and_its_panel_is_a_popover_from_sm(): void {
        $scss = $this->stylesheet('category.scss');

        $line = $this->rule_body($scss, '.dim-line');
        $this->assertMatchesRegularExpression('~position:\s*relative;~', $line, 'The panel is placed against the line.');
        $this->assertMatchesRegularExpression('~display:\s*flex;\s*flex-wrap:\s*wrap;~', $line, 'One row that wraps.');
        $this->assertMatchesRegularExpression('~gap:\s*\.5rem;~', $line, 'The bar\'s 8px gap.');
        $this->assertMatchesRegularExpression(
            '~flex:\s*1 1 13rem;\s*min-width:\s*0;~',
            $this->rule_body($scss, '.dim-toolbar__search'),
            'The root\'s search box is what grows and shrinks, and never sets its row\'s width.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~padding-right~',
            $this->rule_body($scss, '.dim-toolbar__search'),
            'Nothing is lifted out of the line any more, so the search reserves no room for it.'
        );
        $this->assertMatchesRegularExpression('~height:\s*40px;~', $this->rule_body($scss, '.dim-toolbar__input'));
        $field = $this->rule_body($scss, '.dim-line .dim-line__search');
        $this->assertMatchesRegularExpression(
            '~gap:\s*\.375rem;\s*min-width:\s*10rem;\s*height:\s*var\(--dim-bar-inner\);\s*padding:\s*0 \.5rem;\s*'
                . 'border-radius:\s*var\(--dim-radius-md\);\s*background:\s*var\(--dim-surface-strong\);~',
            $field,
            'The open field: the button\'s height, 8px in, 6px between its parts, 8px corners, the inset ground, and its floor.'
        );
        $this->assertMatchesRegularExpression(
            '~&:focus-within \{\s*outline:\s*3px solid var\(--dim-ring\);~',
            $field,
            'The ring is the field\'s.'
        );
        $this->assertMatchesRegularExpression(
            '~width:\s*11\.875rem;\s*min-width:\s*3\.75rem;[^}]*font-size:\s*\.875rem;~',
            $this->rule_body($scss, '.dim-line .dim-line__search .dim-toolbar__input'),
            'The input is 190px at .875rem, 60px at its narrowest.'
        );
        $this->assertMatchesRegularExpression(
            '~width:\s*1\.5rem;\s*height:\s*1\.5rem;\s*margin-inline-end:\s*-\.25rem;\s*'
                . 'border-radius:\s*var\(--dim-radius-xs\);~',
            $this->rule_body($scss, '.dim-line .dim-line__eraser'),
            'The eraser: 24px at radius xs.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~overflow|max-height~',
            $this->rule_body($scss, '.dim-toolbar__panel'),
            'The panel is no scroll container: the date calendar opens inside it and would be clipped.'
        );
        $this->assertMatchesRegularExpression(
            '~position:\s*relative;\s*z-index:\s*31;~',
            $this->rule_body($scss, '.dim-hero__tabs'),
            'The tab rail stacks above the filter line (30), so its More menu is not hidden behind it.'
        );
        $icon = $this->rule_body($scss, '.dim-line__iconbtn');
        $this->assertMatchesRegularExpression('~&:focus-visible \{\s*outline:\s*3px solid var\(--dim-ring\);~', $icon);

        $platter = $this->rule_body($scss, '.dim-line__cells');
        $this->assertMatchesRegularExpression(
            '~gap:\s*2px;\s*padding:\s*3px;\s*border:\s*1px solid transparent;\s*border-radius:\s*var\(--dim-radius\);\s*'
                . 'background:\s*var\(--dim-surface-strong\);~',
            $platter,
            'The scope\'s inset platter: 2px between cells, the one named 3px padding, the panel\'s 12px corners.'
        );
        $scopecell = $this->rule_body($scss, '.dim-line__scope .dim-pill');
        $this->assertMatchesRegularExpression(
            '~min-height:\s*var\(--dim-bar-inner\);\s*padding:\s*0 \.75rem;\s*border:\s*0;\s*'
                . 'border-radius:\s*var\(--dim-radius-md\);~',
            $scopecell,
            'A cell of the bar\'s step at 8px corners: with the platter\'s padding and border, the bar\'s height.'
        );
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-muted\);\s*font-size:\s*\.875rem;\s*font-weight:\s*500;~',
            $scopecell
        );
        $cell = $this->rule_body($scss, '.dim-line__scope .dim-pill__input:checked + .dim-pill');
        $this->assertMatchesRegularExpression(
            '~background:\s*var\(--dim-surface\);\s*color:\s*var\(--dim-ink\);\s*font-weight:\s*600;\s*'
                . 'box-shadow:\s*var\(--dim-elevation-sm\);~',
            $cell,
            'The chosen cell is raised.'
        );
        $this->assertStringNotContainsString('border', $cell, 'The chosen cell has no border.');
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-muted\);~',
            $this->rule_body($scss, '.dim-line__scope .dim-pill__input:checked + .dim-pill .dim-pill__count'),
            'A chosen cell\'s count stays quiet.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~overflow~',
            $this->rule_body($scss, '.dim-line__cells'),
            'The platter wraps rather than scrolls: a scroll container would clip the cells\' ring.'
        );

        $found = preg_match(
            '~\n    \.dim-toolbar__panel \{(\s*position:\s*absolute;[^}]*)\}~',
            $scss,
            $popover
        );
        $this->assertSame(1, $found, 'The panel is a popover by a rule of its own, at every width.');
        $this->assertMatchesRegularExpression('~position:\s*absolute;~', $popover[1]);
        $this->assertMatchesRegularExpression('~top:\s*calc\(100% \+ 6px\);~', $popover[1], 'Under the line.');
        $this->assertMatchesRegularExpression('~max-width:\s*100%;~', $popover[1], 'Never wider than the line.');
        $this->assertMatchesRegularExpression('~box-shadow:\s*var\(--dim-elevation-md\);~', $popover[1], 'A popover\'s elevation.');
        $this->assertDoesNotMatchRegularExpression(
            '~overflow|max-height~',
            $popover[1],
            'It grows with its content: a scroll container would clip the date calendar that opens inside it.'
        );
        $this->assertStringNotContainsString(
            '@media (min-width: 576px) {' . "\n" . '        .dim-toolbar__panel',
            $scss,
            'Its disclosure is in the capsule, which has no room for it in the flow at any width.'
        );

        $rest = $this->rule_body($scss, '.dim-line__chips--rest');
        $this->assertMatchesRegularExpression('~display:\s*contents;~', $rest, 'At rest the row is no box and takes no gap.');
        $this->assertMatchesRegularExpression(
            '~> :not\(\.dim-vitrine__count\) \{\s*display:\s*none;~',
            $rest,
            'Everything but the count sentence goes, and the sentence stays the live region.'
        );
        $row = $this->rule_body($scss, '.dim-line__chips');
        $this->assertMatchesRegularExpression('~flex-wrap:\s*wrap;~', $row, 'The chips row wraps onto further lines.');
        $this->assertMatchesRegularExpression('~gap:\s*\.375rem;~', $row, 'Six pixels between its items.');
        $this->assertDoesNotMatchRegularExpression(
            '~overflow|scrollbar-width~',
            $row,
            'No sideways scroll: it would hide chips and clip their focus ring.'
        );
        $this->assertMatchesRegularExpression(
            '~> \* \{\s*flex:\s*0 1 auto;\s*min-width:\s*0;\s*\}~',
            $row,
            'An item wider than the row on its own shrinks, so the row never widens the page.'
        );
        $this->assertMatchesRegularExpression('~flex-wrap:\s*wrap;~', $this->rule_body($scss, '.dim-line__chiplist'));
        $chip = $this->rule_body($scss, '.dim-chip');
        $this->assertMatchesRegularExpression(
            '~gap:\s*\.375rem;\s*min-height:\s*1\.75rem;\s*padding:\s*0 \.375rem 0 \.5rem;\s*'
                . 'border:\s*1px solid var\(--dim-brand-edge\);\s*border-radius:\s*var\(--dim-radius-sm\);\s*'
                . 'background:\s*var\(--dim-brand-tint\);\s*color:\s*var\(--dim-primary-ink\);~',
            $chip,
            'A chip is a 28px rectangle in the brand tone, never a pill.'
        );
        $this->assertMatchesRegularExpression('~font-size:\s*\.8125rem;\s*font-weight:\s*500;~', $chip);
        $this->assertMatchesRegularExpression('~\.fa \{\s*font-size:\s*\.75rem;\s*\}~', $chip, 'Its cross is 12px.');
        $this->assertMatchesRegularExpression('~&:focus-visible \{\s*outline:\s*3px solid var\(--dim-ring\);~', $chip);

        $phone = $this->phone_block($scss);
        $this->assertMatchesRegularExpression('~\.dim-chip \{\s*min-height:\s*44px;~', $phone, 'The chips are 44px on a phone.');
        $this->assertDoesNotMatchRegularExpression(
            '~dim-line__iconbtn|dim-toolbar__input|dim-line__scope|dim-toolbar__filterslabel|dim-toolbar__manage~',
            $phone,
            'The bar\'s controls take their size from the pointer, never from the width (the coarse step).'
        );
    }

    /**
     * The date layer's boxes, months and days wear the Design System's measures (date-button.md, D64).
     *
     * The boxes are the open search field's: side by side 6px apart, each 32px at the 8px corners on the inset
     * ground with a card's line, a 16px glyph and .8125rem, keeping the room of its 24px clear (4px corners)
     * whether or not the clear is there; the active box takes the brand tone's edge. The clear is positioned
     * inside its box, and its own display would outrank the hidden attribute the client writes on it: the
     * stylesheet has to say that hidden means hidden. The months are in the layer's flow - no popover of their
     * own - each seven 32px columns; a day is a 32 x 28px cell, the weekday initials .75rem in the faint ink, and
     * the turns 32px icon buttons. The layer is the Filters panel's popover, capped by the viewport.
     *
     * Changes that must make it fail: a box grown to the layer or given another height, corner or ground; the
     * active box's edge dropped; the hidden rule of the clear deleted; a day's cell or a month's width moved off
     * the scale; the months made a popover again; the layer capped by the line again.
     */
    public function test_the_date_layer_wears_the_date_buttons_measures(): void {
        $scss = $this->stylesheet('category.scss');

        $boxes = $this->rule_body($scss, '.dim-daterange__boxes');
        $this->assertMatchesRegularExpression('~display:\s*flex;\s*gap:\s*\.375rem;~', $boxes, 'Side by side, 6px apart.');
        $box = $this->rule_body($scss, '.dim-daterange__box');
        $this->assertMatchesRegularExpression('~position:\s*relative;~', $box);
        $this->assertMatchesRegularExpression('~flex:\s*1 1 0;\s*min-width:\s*0;~', $box, 'Two equal boxes.');

        $field = $this->rule_body($scss, '.dim-daterange__field');
        $this->assertMatchesRegularExpression('~height:\s*2rem;~', $field, '32px.');
        $this->assertMatchesRegularExpression('~padding:\s*0 2rem 0 \.5rem;~', $field, 'The room of its clear.');
        $this->assertMatchesRegularExpression(
            '~border:\s*1px solid var\(--dim-line\);\s*border-radius:\s*var\(--dim-radius-md\);\s*'
                . 'background:\s*var\(--dim-surface-strong\);\s*color:\s*var\(--dim-ink\);\s*font-size:\s*\.8125rem;~',
            $field,
            'The open search field\'s box: a card\'s line, the 8px corners, the inset ground, .8125rem.'
        );
        $this->assertDoesNotMatchRegularExpression('~width:\s*100%|form-control~', $field);
        $this->assertMatchesRegularExpression(
            '~border-color:\s*var\(--dim-brand-edge\);~',
            $this->rule_body($scss, '.dim-daterange__field--active'),
            'The box the next press sets: the brand tone\'s edge.'
        );
        $this->assertMatchesRegularExpression('~font-size:\s*1rem;~', $this->rule_body($scss, '.dim-daterange__icon'));

        $clear = $this->rule_body($scss, '.dim-daterange__clear');
        $this->assertMatchesRegularExpression('~position:\s*absolute;~', $clear, 'Inside its box.');
        $this->assertMatchesRegularExpression('~display:\s*inline-flex;~', $clear);
        $this->assertMatchesRegularExpression('~width:\s*1\.5rem;\s*height:\s*1\.5rem;~', $clear, '24px.');
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--dim-radius-xs\);~', $clear, 'The 4px corners.');
        $this->assertMatchesRegularExpression(
            '~&\[hidden\] \{\s*display:\s*none;\s*\}~',
            $clear,
            'Its own display would otherwise draw an empty box\'s control the client has hidden.'
        );

        $this->assertStringNotContainsString('.dim-daterange__pop', $scss, 'The months are in the layer\'s flow: no popover.');
        $this->assertMatchesRegularExpression('~width:\s*14rem;~', $this->rule_body($scss, '.dim-daterange__monthblock'));
        $this->assertMatchesRegularExpression('~gap:\s*1rem;~', $this->rule_body($scss, '.dim-daterange__months'));
        $day = $this->rule_body($scss, '.dim-daterange__day');
        $this->assertMatchesRegularExpression('~width:\s*2rem;\s*height:\s*1\.75rem;~', $day, 'A 32 x 28px cell.');
        $this->assertMatchesRegularExpression('~font-size:\s*\.8125rem;~', $day);
        $this->assertMatchesRegularExpression('~&:hover \{\s*background:\s*var\(--dim-surface-subtle\);~', $day);
        $dow = $this->rule_body($scss, '.dim-daterange__dow');
        $this->assertMatchesRegularExpression('~width:\s*2rem;~', $dow);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-ink-faint\);\s*font-size:\s*\.75rem;~', $dow);
        $this->assertMatchesRegularExpression(
            '~width:\s*2rem;\s*height:\s*2rem;~',
            $this->rule_body($scss, '.dim-daterange__step'),
            'The turns are the bar\'s 32px icon buttons.'
        );

        $layer = $this->rule_body($scss, '.dim-datelayer');
        $this->assertMatchesRegularExpression('~position:\s*absolute;~', $layer, 'A popover under the line.');
        $this->assertMatchesRegularExpression('~width:\s*max-content;\s*max-width:\s*calc\(100vw - 2rem\);~', $layer);
        $this->assertMatchesRegularExpression('~padding:\s*\.75rem;~', $layer);
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--dim-radius\);~', $layer, 'The panel\'s 12px corners.');
        $this->assertMatchesRegularExpression('~box-shadow:\s*var\(--dim-elevation-md\);~', $layer);
        $this->assertMatchesRegularExpression(
            '~padding:\s*0 0 \.375rem;\s*color:\s*var\(--dim-muted\);\s*font-size:\s*\.75rem;\s*font-weight:\s*600;~',
            $this->rule_body($scss, '.dim-datelayer__head'),
            'The heading is the panel\'s group label.'
        );
        $this->assertMatchesRegularExpression(
            '~justify-content:\s*space-between;[^}]*padding-top:\s*\.75rem;\s*border-top:\s*1px solid var\(--dim-line\);~',
            $this->rule_body($scss, '.dim-datelayer__foot'),
            'The foot: a hairline over "Clear period" and "Show".'
        );
    }

    /**
     * Today's ring and the period's ends are the brand tone's in both colour modes, and the dot is the brand fill.
     *
     * Today is a fact, a 1px ring of the brand tone's edge; the ends are a choice, the call to action's pair - the
     * fill with its computed ink, which the dark block turns to the design's blue with white - and the days between
     * the brand tint's band in the brand ink, square. The dot is the same fill. Every one of these reads an alias
     * the dark mode declares again, so the pairs turn with the page.
     *
     * Changes that must make it fail: today's ring painted in another token; an end's fill or ink swapped for a
     * token the dark mode does not turn; the band rounded; the dot on another fill; a pair's dark half dropped.
     */
    public function test_today_and_the_period_ends_are_the_brand_tones_in_both_modes(): void {
        $scss = $this->stylesheet('category.scss');
        $today = $this->rule_body($scss, '.dim-daterange__day[aria-current="date"]');
        $this->assertMatchesRegularExpression(
            '~border-radius:\s*var\(--dim-radius-sm\);\s*box-shadow:\s*inset 0 0 0 1px var\(--dim-brand-edge\);~',
            $today,
            'Today: one pixel of the brand tone\'s edge at the 6px corners.'
        );
        $end = $this->rule_body($scss, '.dim-daterange__day--end');
        $this->assertMatchesRegularExpression(
            '~border-radius:\s*var\(--dim-radius-sm\);\s*background:\s*var\(--dim-cta\);\s*color:\s*var\(--dim-cta-ink\);\s*'
                . 'font-weight:\s*600;~',
            $end,
            'The ends: a 6px square in the fill with its ink, at 600.'
        );
        $between = $this->rule_body($scss, '.dim-daterange__day--between');
        $this->assertMatchesRegularExpression(
            '~border-radius:\s*0;\s*background:\s*var\(--dim-brand-tint\);\s*color:\s*var\(--dim-primary-ink\);~',
            $between,
            'The band: square, the brand tint, the brand ink.'
        );
        $dot = $this->rule_body($scss, '.dim-line__dot');
        $this->assertMatchesRegularExpression(
            '~position:\s*absolute;\s*top:\s*-2px;\s*inset-inline-end:\s*-2px;\s*width:\s*\.5rem;\s*height:\s*\.5rem;\s*'
                . 'border-radius:\s*50%;\s*background:\s*var\(--dim-cta\);~',
            $dot,
            'The dot: 8px, round, the brand fill, at the button\'s top end corner.'
        );
        $this->assertDoesNotMatchRegularExpression('~display:~', $dot, 'The hidden attribute alone takes it away.');
        // The dark halves: the fill and its ink turn in this file's dark block, the tone in post.scss's.
        $source = (string) file_get_contents($this->plugin_root() . '/scss/category.scss');
        $this->assertMatchesRegularExpression(
            '~:root\[data-bs-theme="dark"\] body\.dim-category \.dim-vitrine,\s*'
                . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{\s*'
                . '--dim-cta:\s*var\(--dim-ds-blue-400, #4456ff\);\s*--dim-cta-ink:\s*#fff;~',
            $source
        );
        $this->assertMatchesRegularExpression('~--dim-primary-ink:\s*var\(--dim-ds-blue-200, #adb2f0\);~', $source);
        $post = (string) file_get_contents($this->plugin_root() . '/scss/post.scss');
        $this->assertGreaterThanOrEqual(2, substr_count($post, '--dimensions-brand-edge: var(--bs-primary-border-subtle);'));
        $this->assertGreaterThanOrEqual(2, substr_count($post, '--dimensions-brand-tint: var(--bs-primary-bg-subtle);'));
        $this->assertMatchesRegularExpression(
            '~--dim-brand-edge:\s*var\(--dimensions-brand-edge, ~',
            $source,
            'The category scope reads the tone through the theme\'s alias, which the dark mode turns.'
        );
    }

    /**
     * The calendar's two months fit the layer from the module's breakpoint up.
     *
     * The module draws the second month from WIDE (768px), and the months are fixed widths: two months, their
     * gap, the layer's padding and its border must fit the layer's ceiling (the viewport less 2rem) at that
     * width, and one month the narrowest phone's sheet. Nothing else reads both files.
     */
    public function test_the_calendars_two_months_fit_the_layer_from_the_modules_breakpoint(): void {
        $scss = $this->stylesheet('category.scss');
        $module = (string) file_get_contents($this->plugin_root() . '/js/esm/src/daterange/DateRange.tsx');
        $this->assertSame(1, preg_match("~^const WIDE = '\(min-width: (\d+)px\)';$~m", $module, $wide));
        $rem = static function (string $body, string $property): float {
            preg_match('~(?<![\w-])' . preg_quote($property, '~') . ':\s*([\d.]+)rem;~', $body, $found);
            return (float) ($found[1] ?? 0);
        };
        $month = $rem($this->rule_body($scss, '.dim-daterange__monthblock'), 'width');
        $gap = $rem($this->rule_body($scss, '.dim-daterange__months'), 'gap');
        $padding = $rem($this->rule_body($scss, '.dim-datelayer'), 'padding');
        $this->assertSame([14.0, 1.0, 0.75], [$month, $gap, $padding], 'Precondition: the measures read.');
        $two = (2 * $month + $gap + 2 * $padding) * 16 + 2;
        $this->assertLessThanOrEqual((int) $wide[1] - 32, $two, 'Two months fit the layer at the module\'s WIDE.');
        $this->assertLessThanOrEqual(320 - 32, ($month + 2 * $padding) * 16 + 2, 'One month fits the narrowest phone.');
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
        $module = file_get_contents($this->plugin_root() . '/js/esm/src/listing/paint.ts');

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
            '~animation:\s*dim-poster-pan-in 600ms var\(--dim-ds-motion-ease\) 180ms forwards,\s*'
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
     * The organisation reads "Organise:" inline before its track, and the row under the line follows the design.
     *
     * The sort group (in the Organise menu) is a flex row, and its legend is floated: a floated
     * legend is not the fieldset's rendered legend, so it lays out as a flex item beside the track
     * instead of above it. Bootstrap's reboot gives every legend the whole width, which would put
     * the label on its own line, so the width is reset too. The colon is the stylesheet's, so the
     * string stays the group's name. The row under the line centres its controls 6px apart (the
     * Design System's chips row) and the clear link right after the chips rather than pushed to the far end; an empty
     * period region is out of the row, or its two gaps would double the distance. On a phone every pill and quick
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

        $row = $this->rule_body($scss, '.dim-line__chips');
        $this->assertMatchesRegularExpression('~align-items:\s*center;~', $row);
        $this->assertMatchesRegularExpression('~gap:\s*\.375rem;~', $row);
        $this->assertDoesNotMatchRegularExpression(
            '~margin-left:\s*auto~',
            $this->rule_body($scss, ".dim-toolbar__clear,\n    .dim-datelayer__clear"),
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
        $this->assertStringNotContainsString(
            'dim-toolbar__manage',
            $phone,
            'The kebab is a capsule\'s icon button, sized by the pointer like the others.'
        );
    }

    /**
     * The Filter badge and its open face read in both colour modes; the summary's links are 44px on a phone.
     *
     * The badge is the call to action's pair: the brand primary with its contrasting ink on the
     * light page, #4456ff and white (5.2:1) on the dark one. The page's surface is never its ink,
     * because the brand primary does not turn with the mode and the dark surface on it would not
     * read. The open summary is the brand tone (ground, edge, ink), which post.scss turns with the
     * mode; the ink is the dark mode's lighter blue rather than a primary the dark card swallows. On a
     * phone the area links of the "nothing in N areas" summary take the 44px of every other control there.
     */
    public function test_the_filter_badge_reads_in_the_dark_and_the_summarys_links_are_44px(): void {
        $scss = $this->stylesheet('category.scss');
        $badge = $this->rule_body($scss, '.dim-toolbar__filtercount');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-cta\);~', $badge);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-cta-ink\);~', $badge);
        $this->assertStringNotContainsString('--dim-surface', $badge, 'The dark surface is never the badge\'s ink.');
        $dark = ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-vitrine,\s*'
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{[^}]*';
        $this->assertMatchesRegularExpression(
            '~' . $dark . '--dim-cta:\s*var\(--dim-ds-blue-400, #4456ff\);~',
            $scss,
            'The fill follows the mode: the lighter blue on the dark page.'
        );
        $open = $this->rule_body(
            $scss,
            ".dim-toolbar__filters[open] > .dim-line__iconbtn,\n    .dim-line__date[open] > .dim-line__iconbtn,\n"
                . "    .dim-line__organise[open] > .dim-line__iconbtn"
        );
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-brand-tint\);~', $open);
        $this->assertMatchesRegularExpression(
            '~color:\s*var\(--dim-primary-ink\);~',
            $open,
            'The open summary\'s ink is the brand ink that follows the mode, not the navy the dark card swallows.'
        );
        $this->assertMatchesRegularExpression(
            '~:root\[data-bs-theme="dark"\] body\.dim-category \.dim-hero,\s*' . $dark . '--dim-primary-ink:\s*'
                . 'var\(--dim-ds-blue-200, #adb2f0\);~',
            $scss
        );
        $tokens = $this->rule_body($scss, ".dim-vitrine,\n    .dim-cmodal");
        $this->assertMatchesRegularExpression(
            '~--dim-brand-tint:\s*var\(--dimensions-brand-tint,~',
            $tokens,
            'The brand tone is the family\'s, which post.scss declares for both modes.'
        );
        $this->assertMatchesRegularExpression('~--dim-brand-edge:\s*var\(--dimensions-brand-edge,~', $tokens);

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
        $this->assertMatchesRegularExpression('~border-radius:\s*var\(--dim-ds-radius-pill, 50rem\);~', $suggestion);

        $mark = $this->rule_body($scss, 'mark.dim-mark');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-mark-bg\);~', $mark);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-mark-ink\);~', $mark);
        $this->assertMatchesRegularExpression('~padding:\s*0 1px;~', $mark);
        $this->assertStringContainsString('--dim-mark-bg: var(--bs-highlight-bg, #fff3cd);', $scss);
        $this->assertStringContainsString('--dim-mark-ink: var(--bs-highlight-color, var(--dim-ink));', $scss);
    }

    /**
     * The open area wears its own colour: the poster's ring, and the panel's rail, head, glow and arrow.
     *
     * Written without color-mix(): the band is the area's own custom property, painted whole or as a layer
     * at an opacity, with a one-pixel edge beside the ring and a white lift over the rail that only the dark
     * page switches on. The default band of the panel is the open ring, declared where it is read.
     *
     * Changes that must make it fail: the ring back to the open ring; the rail, the tint or the glow
     * dropped; the lift switched on (or off) in the wrong mode; the arrow's tint moved off the arrow.
     */
    public function test_the_open_area_wears_its_own_colour_on_the_ring_and_the_panel(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression(
            '~box-shadow:\s*0 0 0 3px var\(--dim-card-band\), 0 0 0 4px var\(--dim-area-edge\), var\(--dim-ds-shadow-lg\);~',
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
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{[^}]*';
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-area-edge:\s*rgba\(255, 255, 255, \.45\);~', $scss);
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-area-lift:\s*\.45;~', $scss);
        $this->assertMatchesRegularExpression('~' . $dark . '--dim-area-tint:\s*\.16;~', $scss);
    }

    /**
     * While a panel is open the other posters go grey under the vignette, and the open one says so in words.
     *
     * Image and text dim together, easing in and out on the card's own transition, lifting most of
     * the way under a pointer and all the way under the focus; the list view greys and fades instead.
     * The vignette is the card's last box, transparent to the pointer. The open poster carries "Open",
     * shown by its open class alone.
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
        $this->assertMatchesRegularExpression(
            '~transition:\s*opacity var\(--dim-ds-motion-base\) var\(--dim-ds-motion-ease\);~',
            $vignette
        );
        $this->assertMatchesRegularExpression(
            '~filter var\(--dim-ds-motion-base\) var\(--dim-ds-motion-ease\)~',
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
     * A compact or folded hero keeps its crests on the title's line at 36x40, and 26x29 on a phone.
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
            '~\n    \.dim-vitrine,\s*\.dim-cmodal \{\s*--dim-surface:~',
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
        // The skeleton the dialogue opens with is quiet: its bars pulse only where motion is allowed, beside the entrance.
        $this->assertSame(1, substr_count($scss, 'animation: dim-cmodal-pulse '));
        $this->assertMatchesRegularExpression(
            '~@media \(prefers-reduced-motion: no-preference\) \{\s*\.dim-cmodal\.show \.modal-dialog \{[^}]*\}\s*'
                . '\.dim-cmodal__bone \{\s*animation: dim-cmodal-pulse 900ms~',
            $scss
        );
        $bone = $this->rule_body($scss, '.dim-cmodal__bone');
        $this->assertStringNotContainsString('animation', $bone, 'Outside the gate, the bars stand still.');
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
     * The filter line's steps, its search, its morph, its sheet and its search chip are the client's classes alone.
     *
     * The filter line's component (js/esm/src/filterline) writes a step on the line (dim-line--s1, one line, or
     * dim-line--s2, two lines), dim-line--over where the open field does not fit beside the controls,
     * dim-line--open while the search is open in place, dim-line--searching while it is open over the line,
     * and dim-line--measuring while it measures; it marks the line drawn (data-line-drawn). The stylesheet does
     * everything else, and without the client none of it applies: the server's field stays open.
     *
     * Changes that must make it fail: the search's button shown, or the field folded, on a line the client
     * never drew; the field left open at rest on a drawn line, or folded while open; the measure left to wrap,
     * to shrink the controls or to grow the spacer; a slid-out control left in the focus order; a transition
     * outside the no-preference query, or running while the client measures; the sheet given no fixed foot, no
     * cap or no scroll of its own; the calendar left a popover inside it; the search chip shown while the field is.
     */
    public function test_the_filter_lines_steps_morph_sheet_and_search_chip(): void {
        $scss = $this->stylesheet('category.scss');

        // What only the client draws is hidden until the client draws the line.
        $this->assertMatchesRegularExpression(
            '~\.dim-line__searchopen,\s*\.dim-line__searchclose \{\s*display:\s*none;\s*\}~',
            $scss
        );
        $this->assertStringNotContainsString('dim-line__showas', $scss, 'The view toggle never moves into the Organise menu.');
        $this->assertMatchesRegularExpression(
            '~\.dim-line\[data-line-drawn\] > \.dim-line__searchcap \{\s*'
                . '> \.dim-line__searchopen \{\s*display:\s*inline-flex;\s*\}\s*'
                . '> \.dim-line__search \{\s*display:\s*none;\s*\}\s*\}~',
            $scss,
            'At rest a drawn line\'s search is its button, at every step.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-line\[data-line-drawn\]\.dim-line--open,\s*\.dim-line\[data-line-drawn\]\.dim-line--searching \{\s*'
                . '> \.dim-line__searchcap > \.dim-line__searchopen \{\s*display:\s*none;\s*\}\s*'
                . '> \.dim-line__searchcap > \.dim-line__search \{\s*display:\s*inline-flex;\s*\}~',
            $scss,
            'Open, in place or over the line, the field takes the button\'s place.'
        );
        // The measure: one row, no control shrinking, the spacer taking no room, every cell on one line.
        $measuring = $this->rule_body($scss, '.dim-line--measuring');
        $this->assertMatchesRegularExpression('~^\s*flex-wrap:\s*nowrap;~', $measuring);
        $this->assertMatchesRegularExpression('~> \* \{\s*flex-shrink:\s*0;\s*\}~', $measuring);
        $this->assertMatchesRegularExpression('~> \.dim-line__spacer \{\s*flex-grow:\s*0;\s*\}~', $measuring);
        $this->assertMatchesRegularExpression('~\.dim-line__cells \{\s*flex-wrap:\s*nowrap;\s*\}~', $measuring);
        $this->assertMatchesRegularExpression(
            '~\.dim-line--measuring\.dim-line--s2 > \.dim-line__scope \{\s*display:\s*none;\s*\}~',
            $scss,
            'The scope of the two-line step has a line of its own and is out of the measure.'
        );
        $this->assertMatchesRegularExpression('~flex:\s*1 1 0;~', $this->rule_body($scss, '.dim-line__spacer'));
        // The one-line step keeps its line; the two-line step gives the scope a scrolling line with room for its ring.
        $this->assertMatchesRegularExpression('~\.dim-line--s1 \{\s*flex-wrap:\s*nowrap;~', $scss);
        $this->assertMatchesRegularExpression(
            '~\.dim-line--s2 \{\s*> \.dim-line__scope \{\s*flex:\s*0 0 calc\(100% \+ 10px\);\s*'
                . 'min-width:\s*0;\s*max-width:\s*none;\s*margin:\s*-5px;\s*padding:\s*5px;\s*overflow-x:\s*auto;~',
            $scss
        );
        $this->assertDoesNotMatchRegularExpression('~dim-line--s[34]|dim-line--folded~', $scss, 'Two steps, and no fold class.');

        // The morph: a slid-out control is out of the focus order, and the motion is gated.
        $this->assertMatchesRegularExpression(
            '~\.dim-line--searching \{\s*&\.dim-line--s1 > :not\(\.dim-line__searchcap\):not\(\.dim-line__searchclose\)'
                . ':not\(\.dim-line__suggest\),\s*&\.dim-line--s2 > [^{]*:not\(\.dim-line__scope\) \{[^}]*max-width:\s*0;'
                . '[^}]*opacity:\s*0;\s*visibility:\s*hidden;\s*pointer-events:\s*none;\s*\}~',
            $scss
        );
        $this->assertMatchesRegularExpression(
            '~> \.dim-line__searchcap \{\s*flex:\s*1 1 auto;\s*\}~',
            $this->rule_body($scss, '.dim-line--searching'),
            'Over the line the search\'s capsule takes it.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-line\[data-line-drawn\]\.dim-line--open,\s*\.dim-line\[data-line-drawn\]\.dim-line--searching \{'
                . '.*?> \.dim-line__searchcap > \.dim-line__searchclose \{\s*display:\s*inline-flex;\s*\}~s',
            $scss,
            'The close button is drawn in the capsule in both open states, in the managers\' menu\'s place.'
        );
        $motion = null;
        $offset = 0;
        while (($at = strpos($scss, '@media (prefers-reduced-motion: no-preference) {', $offset)) !== false) {
            $block = $this->block_at($scss, $at + strlen('@media (prefers-reduced-motion: no-preference) {'));
            if (str_contains($block, 'dim-line--over')) {
                $motion = $block;
                break;
            }
            $offset = $at + 1;
        }
        $this->assertNotNull($motion, 'The slide lives in a no-preference query: none under reduced motion.');
        $this->assertStringContainsString('.dim-line--over:not(.dim-line--measuring)', $motion, 'And never while measuring.');
        $this->assertMatchesRegularExpression('~max-width 220ms ease,~', $motion);
        $this->assertMatchesRegularExpression('~opacity 160ms ease~', $motion);
        $this->assertMatchesRegularExpression('~visibility 0s linear 220ms;~', $motion, 'Hidden once the slide is over.');
        $outside = str_replace($motion, '', $scss);
        $this->assertStringNotContainsString('visibility 0s linear 220ms', $outside);
        $this->assertStringNotContainsString('max-width 220ms', $outside);

        // The suggestions: a layer under the line, scrolling inside itself; the active option ringed inside.
        $suggest = $this->rule_body($scss, '.dim-line__suggest');
        $this->assertMatchesRegularExpression('~position:\s*absolute;~', $suggest);
        $this->assertMatchesRegularExpression('~overflow-y:\s*auto;~', $suggest);
        $this->assertMatchesRegularExpression(
            '~outline:\s*3px solid var\(--dim-ring\);\s*outline-offset:\s*-3px;~',
            $this->rule_body($scss, '.dim-line__option--active')
        );

        // The sheets of the two-line step: the Filters panel and the date layer, one rule.
        $this->assertMatchesRegularExpression(
            '~\.dim-line--s2 \{\s*\.dim-toolbar__filters\[open\] \{\s*flex:\s*none;\s*\}\s*'
                . '\.dim-toolbar__panel,\s*\.dim-datelayer \{\s*'
                . 'position:\s*fixed;\s*top:\s*auto;\s*right:\s*0;\s*bottom:\s*0;\s*left:\s*0;[^}]*max-height:\s*80vh;'
                . '[^}]*overflow-y:\s*auto;[^}]*border-radius:\s*var\(--dim-radius-xl\) var\(--dim-radius-xl\) 0 0;[^}]*\}\s*\}~',
            $scss
        );
        $this->assertMatchesRegularExpression('~\.dim-toolbar--sheet \{\s*z-index:\s*1029;\s*\}~', $scss);

        // The fades, and the search's chip shown only on a drawn line whose search is its button.
        $this->assertMatchesRegularExpression('~\.dim-scroll--fade-left \{\s*mask-image:\s*linear-gradient\(~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-scroll--fade-right \{\s*mask-image:\s*linear-gradient\(~', $scss);
        $this->assertMatchesRegularExpression('~\.dim-chip\[data-param="q"\] \{\s*display:\s*none;\s*\}~', $scss);
        $this->assertMatchesRegularExpression(
            '#\.dim-line\[data-line-drawn\]:not\(\.dim-line--open\):not\(\.dim-line--searching\) ~ \.dim-line__chips '
                . '\.dim-chip\[data-param="q"\] \{\s*display:\s*inline-flex;\s*\}#',
            $scss
        );
    }

    /**
     * The bar is 40px, and 44px wherever the pointer is coarse, whatever the width (D26).
     *
     * --dim-bar-inner is the bar's height step, read by the scope's cells, every icon button, core's kebab
     * and the open field: 32px, which with a capsule's 3px padding and 1px border is the 40px bar, and 36px
     * (the 44px bar) under a coarse pointer. It replaces the phone width's 44px rules for those controls: a
     * finger on a tablet gets the large bar, a mouse in a narrow window the small one.
     *
     * Changes that must make it fail: the coarse step dropped or moved under a width query; a bar control
     * sized by a literal rather than by the step.
     */
    public function test_the_bar_is_40px_and_44px_under_a_coarse_pointer(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression('~^\s*--dim-bar-inner:\s*2rem;~', $this->rule_body($scss, '.dim-line'));
        $this->assertMatchesRegularExpression(
            '~@media \(pointer: coarse\) \{\s*\.dim-line \{\s*--dim-bar-inner:\s*2\.25rem;\s*\}\s*\}~',
            $scss
        );
        foreach (['.dim-line__iconbtn', '.dim-toolbar__manage .dropdown-toggle'] as $selector) {
            $rule = $this->rule_body($scss, $selector);
            $this->assertMatchesRegularExpression(
                '~min-width:\s*var\(--dim-bar-inner\);~',
                $rule,
                "{$selector} is as wide as the step at least."
            );
            $this->assertMatchesRegularExpression('~height:\s*var\(--dim-bar-inner\);~', $rule, "{$selector} is the step tall.");
        }
        $this->assertMatchesRegularExpression(
            '~height:\s*var\(--dim-bar-inner\);~',
            $this->rule_body($scss, '.dim-line .dim-line__search')
        );
        $this->assertMatchesRegularExpression(
            '~min-height:\s*var\(--dim-bar-inner\);~',
            $this->rule_body($scss, '.dim-line__scope .dim-pill')
        );
        $this->assertMatchesRegularExpression('~padding:\s*3px;~', $this->rule_body($scss, '.dim-line__cells'));
        $this->assertMatchesRegularExpression('~padding:\s*\.1875rem;~', $this->rule_body($scss, '.dim-line__capsule'));
    }

    /**
     * The bar's tooltip is the stylesheet's, drawn from data-dim-tip after 100ms of rest, and out of the way when it must be.
     *
     * tooltip.md's balloon, drawn below the control (the category hero's tab row stacks over the toolbar): the body
     * ink as its ground and the surface as its text (so the dark mode turns it), radius sm, .75rem, at most 16.25rem
     * wide, 8px below, with its 5px arrow pointing up. It shows on hover and on keyboard focus after D51's 100ms,
     * never under a pointer that cannot hover, never while its control's layer
     * is open (aria-expanded, an open details) and never while Escape has put the line's tooltips off. The open
     * values of tooltip.md are block_compass's: .25rem .5rem of padding, no shadow, z 5.
     *
     * Changes that must make it fail: the delay dropped or moved off 100ms; the tooltip shown on a tap, over an
     * open layer or after Escape; its words taken from anything but data-dim-tip; its colours not the turning pair.
     */
    public function test_the_bars_tooltip_shows_after_a_rest_and_never_over_an_open_layer(): void {
        $scss = $this->stylesheet('category.scss');
        $balloon = $this->rule_body($scss, '.dim-line [data-dim-tip]::after');
        $declarations = [
            'content:\s*attr\(data-dim-tip\);',
            'top:\s*calc\(100% \+ \.5rem\);',
            'max-width:\s*16\.25rem;',
            'padding:\s*\.25rem \.5rem;',
            'border-radius:\s*var\(--dim-radius-sm\);',
            'background:\s*var\(--dim-ink\);',
            'color:\s*var\(--dim-surface\);',
            'font:\s*400 \.75rem / 1\.35 var\(--dim-font-brand\);',
        ];
        foreach ($declarations as $declaration) {
            $this->assertMatchesRegularExpression('~' . $declaration . '~', $balloon, 'The balloon: ' . $declaration);
        }
        $this->assertStringNotContainsString(
            'box-shadow',
            $balloon,
            'No shadow: tooltip.md leaves it open, block_compass has none.'
        );
        $both = $this->rule_body($scss, ".dim-line [data-dim-tip]::after,\n    .dim-line [data-dim-tip]::before");
        $this->assertMatchesRegularExpression('~z-index:\s*5;~', $both);
        $this->assertMatchesRegularExpression('~opacity:\s*0;\s*pointer-events:\s*none;~', $both, 'At rest it is not there.');
        $this->assertMatchesRegularExpression(
            '~transition:\s*opacity var\(--dim-motion\) ease;~',
            $both,
            'It fades over the motion scale\'s fast step.'
        );
        // The arrow's own rule: its selector also heads the shared rule above, which rule_body() would find first.
        $this->assertMatchesRegularExpression(
            '~\n    \.dim-line \[data-dim-tip\]::before \{\s*content:\s*"";[^}]*border:\s*5px solid transparent;\s*'
                . 'border-bottom-color:\s*var\(--dim-ink\);~',
            $scss
        );
        $this->assertStringNotContainsString(
            'bottom: calc(100% + .5rem)',
            $balloon,
            'Above the control the tab row of the hero covers the balloon.'
        );
        // The far end of the line opens towards the line, with the arrow on the control's centre.
        $end = $this->rule_body(
            $scss,
            ".dim-line__searchcap [data-dim-tip]::after,\n    .dim-line__searchclose[data-dim-tip]::after"
        );
        $this->assertMatchesRegularExpression('~right:\s*0;\s*left:\s*auto;\s*transform:\s*none;~', $end);
        $this->assertMatchesRegularExpression(
            '~\.dim-line__searchclose\[data-dim-tip\]::before \{\s*right:\s*calc\(var\(--dim-bar-inner\) / 2 - 5px\);~',
            $scss
        );

        $this->assertMatchesRegularExpression(
            '~\.dim-line:not\(\.dim-line--tipsoff\) \[data-dim-tip\]:not\(\[aria-expanded="true"\]\) \{\s*'
                . '&:hover::after,\s*&:hover::before,\s*&:focus-visible::after,\s*&:focus-visible::before \{\s*'
                . 'opacity:\s*1;\s*transition-delay:\s*100ms;\s*\}\s*\}~',
            $scss,
            'Shown on hover and keyboard focus after 100ms, never on an open control or after Escape.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-line \[open\] > \[data-dim-tip\]::after,\s*\.dim-line \[open\] > \[data-dim-tip\]::before \{\s*'
                . 'visibility:\s*hidden;\s*\}~',
            $scss,
            'A summary\'s tooltip goes while its details is open.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(hover: none\) \{\s*\.dim-line \[data-dim-tip\]:not\(:focus-visible\):hover::after,\s*'
                . '\.dim-line \[data-dim-tip\]:not\(:focus-visible\):hover::before \{\s*visibility:\s*hidden;\s*\}\s*\}~',
            $scss,
            'A tap leaves no tooltip stuck on a touch screen; a keyboard\'s focus still shows it.'
        );
    }

    /**
     * A Filters control drawn hidden (no group to open) takes no room in the capsule.
     *
     * The attribute is the only switch; a plugin-owned selector keeps any display a later rule gives the disclosure
     * from showing it, and the fold's measure, which reads boxes, then counts nothing for it.
     *
     * Changes that must make it fail: the rule dropped, or the disclosure given a display that shows it.
     */
    public function test_the_filters_control_takes_no_room_while_hidden(): void {
        $this->assertMatchesRegularExpression(
            '~display:\s*none;~',
            $this->rule_body($this->stylesheet('category.scss'), '.dim-toolbar__filters[hidden]'),
            'A Filters control with no group to open takes no room in the capsule.'
        );
    }

    /**
     * While the search is open the managers' menu is gone from its capsule, and the fold's measure sees the same.
     *
     * The field with its eraser is all the capsule shows, in place (dim-line--open) and over the line
     * (dim-line--searching), the close button taking the menu's place. The rule is not gated on the measuring
     * class, so the open state is measured as it is drawn.
     *
     * Changes that must make it fail: either state left out; the rule gated on the client's measuring class.
     */
    public function test_the_managers_menu_waits_while_the_search_is_open(): void {
        $scss = $this->stylesheet('category.scss');
        $this->assertMatchesRegularExpression(
            '~\.dim-line\[data-line-drawn\]\.dim-line--open,\s*\.dim-line\[data-line-drawn\]\.dim-line--searching \{\s*'
                . '> \.dim-line__searchcap > \.dim-toolbar__manage \{\s*display:\s*none;\s*\}\s*\}~',
            $scss,
            'More actions is hidden while the search is open, in place and over the line.'
        );
        $this->assertStringNotContainsString(
            'dim-line--measuring) > .dim-line__searchcap > .dim-toolbar__manage',
            $scss,
            'The measure reads the open state as drawn.'
        );
    }

    /**
     * The filter line's preview lights a kept card with a 2px ring and lets the others recede by their own tokens.
     *
     * The shared course card's preview, drawn as block_compass's calendar strip draws it: the ring is the accent,
     * 2px and inside the card's edge (a rail that scrolls would clip an outside one), a pixel narrower than the 3px
     * focus ring; a dim card paints its surface, its inks and its line with the receding values (the card's own
     * colours are contract tokens, which only the family token feeds, so the receding card sets what they paint),
     * and remaps every state pill's fill with its ink, the call to action, "More information" and the film over its
     * image, and nothing of it is opacity or a filter, which would fade the focus
     * ring of whatever is inside it. The change eases only where motion is welcome. Each of the tokens has its
     * dark value in a block at the foot of the file, where the badge test reads the dark half.
     *
     * Changes that must make it fail: the ring 3px or outside the card; an opacity or a filter on a dim card; a
     * pill's fill remapped without its ink; the film drawn outside the preview or over the pointer; the easing
     * outside the no-preference query; a token without its dark value.
     */
    public function test_the_preview_lights_with_a_ring_and_recedes_by_its_own_tokens(): void {
        $scss = $this->stylesheet('category.scss');

        $lit = $this->rule_body($scss, '.dim-vitrine--previewing .dim-card--lit');
        $this->assertMatchesRegularExpression('~outline:\s*2px solid var\(--dim-preview-ring\);\s*outline-offset:\s*-2px;~', $lit);

        $dim = $this->rule_body($scss, '.dim-vitrine--previewing .dim-card--dim');
        $remapped = [
            '--dim-primary-ink' => '--dim-recede-ink',
            '--dim-star-on' => '--dim-recede-ink',
            '--dim-card-film' => '--dim-recede-film',
        ];
        foreach ($remapped as $token => $receding) {
            $this->assertMatchesRegularExpression(
                '~' . preg_quote($token, '~') . ':\s*var\(' . preg_quote($receding, '~') . '\);~',
                $dim,
                "{$token} recedes."
            );
        }
        $this->assertSame(0, preg_match_all('~(?<![\w-])(?:opacity|filter)\s*:~', $dim), 'No opacity, no filter.');
        // Every pill not already the receding pair (none's) recedes, its fill with its ink.
        $tokens = $this->rule_body(substr($scss, 0, (int) strpos($scss, ':root[data-bs-theme="dark"]')), '.dim-vitrine,'
            . "\n" . '    .dim-cmodal');
        preg_match_all('~--dim-badge-([a-z]+)-bg:\s*([^;]+);~', $tokens, $declared, PREG_SET_ORDER);
        $none = array_column($declared, 2, 1)['none'] ?? '';
        $coloured = array_filter($declared, static fn(array $badge): bool => $badge[2] !== $none);
        $this->assertGreaterThanOrEqual(10, count($coloured), 'Precondition: the coloured states are read off the roots.');
        foreach ($coloured as $badge) {
            $this->assertStringContainsString("--dim-badge-{$badge[1]}-bg: var(--dim-recede-pill-bg);", $dim);
            $this->assertStringContainsString(
                "--dim-badge-{$badge[1]}-fg: var(--dim-recede-pill-fg);",
                $dim,
                "The {$badge[1]} pill's fill recedes with its ink."
            );
            $this->assertStringContainsString(
                "--dim-badge-{$badge[1]}-edge: var(--dim-recede-pill-edge);",
                $dim,
                "The {$badge[1]} pill's edge recedes with its fill."
            );
        }
        $this->assertMatchesRegularExpression(
            '~\.dim-card__cta \{\s*--bs-btn-bg:\s*var\(--dim-recede-pill-bg\);\s*--bs-btn-color:\s*var\(--dim-recede-pill-fg\);~',
            $dim,
            'The call to action is re-pointed through its own variables, fill with ink.'
        );
        $this->assertMatchesRegularExpression(
            '~\.dim-card__summary \{\s*--bs-btn-color:\s*var\(--dim-recede-ink\);\s*'
                . '--bs-btn-border-color:\s*var\(--dim-recede-line\);~',
            $dim,
            '"More information" recedes through its own variables too.'
        );
        // The second rule of the same selector, flat: the first is the token remap rule_body() returns.
        $this->assertSame(
            1,
            preg_match('~\n    \.dim-vitrine--previewing \.dim-card--dim \{([^{}]*background:[^{}]*)\}~', $scss, $ground),
            'The receding card paints what its contract tokens would.'
        );
        $this->assertMatchesRegularExpression('~border-color:\s*var\(--dim-recede-line\);~', $ground[1]);
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-recede-surface\);~', $ground[1]);
        $this->assertMatchesRegularExpression('~color:\s*var\(--dim-recede-ink\);~', $ground[1]);
        $this->assertSame(0, preg_match_all('~(?<![\w-])(?:opacity|filter)\s*:~', $ground[1]), 'No opacity, no filter.');
        foreach (['title a', 'over', 'meta', 'pct', 'route', 'opens'] as $ink) {
            $this->assertStringContainsString(
                '.dim-vitrine--previewing .dim-card--dim .dim-card__' . $ink,
                $scss,
                "The {$ink} line recedes with the card."
            );
        }

        $this->assertMatchesRegularExpression('~--dim-card-film:\s*transparent;~', $this->rule_body($scss, '.dim-card'));
        $film = $this->rule_body($scss, '.dim-vitrine--previewing .dim-card__media::after');
        $this->assertMatchesRegularExpression('~background:\s*var\(--dim-card-film\);~', $film);
        $this->assertMatchesRegularExpression('~z-index:\s*3;~', $film, 'Over the still, the film and the tag of the band.');
        $this->assertMatchesRegularExpression('~pointer-events:\s*none;~', $film);

        $motion = null;
        $offset = 0;
        while (($at = strpos($scss, '@media (prefers-reduced-motion: no-preference) {', $offset)) !== false) {
            $block = $this->block_at($scss, $at + strlen('@media (prefers-reduced-motion: no-preference) {'));
            if (str_contains($block, self::PREVIEW_EASE)) {
                $motion = $block;
                break;
            }
            $offset = $at + 1;
        }
        $this->assertNotNull($motion, 'The receding surface eases inside a no-preference query.');
        $this->assertMatchesRegularExpression('~^\s*\.dim-card \{\s*transition:~', $motion);
        $this->assertSame(1, substr_count($scss, self::PREVIEW_EASE), 'And nowhere outside it.');
        $this->assertMatchesRegularExpression(
            '~transition:\s*box-shadow var\(--dim-card-transition\) var\(--dim-ease\);~',
            $this->rule_body($scss, '.dim-card'),
            'The card\'s own rule eases its shadow alone, so under reduced motion the change is instant.'
        );

        $split = strpos($scss, ':root[data-bs-theme="dark"]');
        $this->assertNotFalse($split);
        $light = substr($scss, 0, $split);
        $darkhalf = substr($scss, $split);
        $roots = $this->rule_body($light, '.dim-vitrine,' . "\n" . '    .dim-cmodal');
        $tokens = [
            '--dim-preview-ring',
            '--dim-recede-surface',
            '--dim-recede-ink',
            '--dim-recede-line',
            '--dim-recede-pill-bg',
            '--dim-recede-pill-fg',
            '--dim-recede-film',
        ];
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression(
                '~' . preg_quote($token, '~') . ':~',
                $roots,
                "{$token} is declared on the roots."
            );
        }
        $this->assertMatchesRegularExpression('~--dim-recede-pill-bg:\s*var\(--dim-badge-none-bg\);~', $roots);
        $this->assertMatchesRegularExpression('~--dim-recede-pill-fg:\s*var\(--dim-badge-none-fg\);~', $roots);
        $this->assertMatchesRegularExpression('~--dim-recede-pill-edge:\s*var\(--dim-badge-none-edge\);~', $roots);
        $dark = ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-vitrine,\s*'
            . ':root\[data-bs-theme="dark"\] body\.dim-category \.dim-cmodal \{([^}]*)\}';
        $this->assertSame(1, preg_match('~' . $dark . '\s*$~', $darkhalf, $last), 'The preview\'s dark block closes the file.');
        foreach (['--dim-preview-ring', '--dim-recede-surface', '--dim-recede-ink', '--dim-recede-film'] as $token) {
            $this->assertMatchesRegularExpression('~' . preg_quote($token, '~') . ':~', $last[1], "{$token} has its dark value.");
        }
    }

    /**
     * The card is one target: the title's link stretches over it, and every control of the card stays over the link.
     *
     * The stretched link is armed by dim-card--link alone, which the template writes on a
     * card the reader may open; the call to action, "More information" and the star are positioned over its z-index,
     * the list view's star included (in the flow there, and still positioned, or the link would swallow its press).
     * Without its image the card view drops the band and keeps a top edge in the category's colour, and
     * the list view drops the thumbnail and keeps a leading edge.
     */
    public function test_the_card_is_one_target_with_its_controls_over_it(): void {
        $scss = $this->stylesheet('category.scss');
        $after = $this->rule_body($scss, '.dim-card--link .dim-card__title a::after');
        $this->assertMatchesRegularExpression('~position:\s*absolute;\s*inset:\s*0;\s*z-index:\s*1;~', $after);
        $this->assertMatchesRegularExpression(
            '~\n    \.dim-card__cta,\s*\.dim-card__summary,\s*\.dim-card__star \{\s*position:\s*relative;\s*z-index:\s*2;\s*\}~',
            $scss,
            'Every control of the card is lifted over the link.'
        );
        $this->assertMatchesRegularExpression(
            '~position:\s*relative;\s*inset:\s*auto;~',
            $this->rule_body($scss, '.dim-vitrine--list .dim-card__star'),
            'The list view\'s star stays positioned, so its z-index holds.'
        );
        $this->assertMatchesRegularExpression(
            '~outline:\s*var\(--dim-card-focus-width\) solid var\(--dim-card-focus-ring\);~',
            $this->rule_body($scss, '.dim-card--link .dim-card__title a:focus-visible::after'),
            'The keyboard ring is the card\'s, from the focus tokens.'
        );
        $this->assertMatchesRegularExpression(
            '~display:\s*none;~',
            $this->rule_body($scss, '.dim-vitrine--cards .dim-card--nocover .dim-card__media'),
            'A card without its cover draws no band.'
        );
        $this->assertMatchesRegularExpression(
            '~border-top:\s*var\(--dim-card-done-edge\) solid var\(--dim-card-band, var\(--dim-card-cover-fallback\)\);~',
            $this->rule_body($scss, '.dim-vitrine--cards .dim-card--nocover')
        );
        $this->assertMatchesRegularExpression(
            '~display:\s*none;~',
            $this->rule_body($scss, '.dim-vitrine--list .dim-card--nothumb .dim-card__media'),
            'A row without its thumbnail draws no cell.'
        );
        $this->assertMatchesRegularExpression(
            '~border-inline-start:\s*var\(--dim-card-done-edge\) solid var\(--dim-card-band, var\(--dim-card-cover-fallback\)\);~',
            $this->rule_body($scss, '.dim-vitrine--list .dim-card--nothumb')
        );
        $this->assertMatchesRegularExpression('~display:\s*none;~', $this->rule_body($scss, '.dim-card__top'));
        $this->assertMatchesRegularExpression(
            '~display:\s*flex;~',
            $this->rule_body($scss, '.dim-vitrine--cards .dim-card--nocover .dim-card__top'),
            'The top row shows in the card view of a card without its cover only.'
        );
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
        $summary = $this->rule_body($scss, '.dim-line__iconbtn');
        $this->assertStringContainsString('&::-webkit-details-marker', $summary, 'The first nested rule.');
        $this->assertStringContainsString('&:focus-visible', $summary, 'And one past it, which a regex would have lost.');
        $this->assertStringNotContainsString(
            '.dim-line__organise[open]',
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
            if (str_contains($block, 'dim-toolbar__sortsubmit')) {
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

    /**
     * The course dialogue's close button wears the white filter under the name core's Bootstrap declares.
     *
     * The cross is a dark SVG on a dark disc, so it is visible only through Bootstrap's white filter. From Bootstrap
     * 5.3.4 that filter is --bs-btn-close-filter, set by its btn-close-white() mixin, and --bs-btn-close-white-filter
     * is no longer declared; the declared-property test of this class exempts every --bs-* name, so this one reads
     * core's Bootstrap for the two names, and fails when Bootstrap renames them again.
     *
     * @return void
     */
    public function test_the_dialogue_close_button_wears_bootstraps_white_filter(): void {
        global $CFG;

        $close = (string) file_get_contents($CFG->root . '/lib/bundles/bootstrap/scss/_close.scss');
        $this->assertStringContainsString('filter: var(--#{$prefix}btn-close-filter);', $close);
        $this->assertStringContainsString('@mixin btn-close-white() {', $close);
        $this->assertStringNotContainsString('--#{$prefix}btn-close-white-filter:', $close);

        $scss = $this->stylesheet('category.scss');
        $found = preg_match('~\n(\s*)\.dim-cmodal \.dim-cmodal__close \{\n(.*?)\n\1\}~s', $scss, $rule);
        $this->assertSame(1, $found, 'The dialogue\'s close disc is missing.');
        $found = preg_match('~\.btn-close \{([^}]*)\}~', $rule[2], $button);
        $this->assertSame(1, $found, 'The disc styles no close button.');
        $this->assertStringContainsString('@include btn-close-white();', $button[1]);
        $this->assertStringContainsString('filter: var(--bs-btn-close-filter);', $button[1]);
        $this->assertStringNotContainsString('btn-close-white-filter', $scss);
    }

    /**
     * A selector's specificity as a browser counts it: ids, classes with attributes and pseudo-classes, elements.
     *
     * A :not() counts as its argument; pseudo-elements are not used in the selectors this reads.
     *
     * @param string $selector One compound selector list item.
     * @return array The three counts, ids first.
     */
    private static function specificity(string $selector): array {
        $total = [0, 0, 0];
        while (preg_match('~:not\(([^()]*)\)~', $selector, $not)) {
            $inner = self::specificity($not[1]);
            foreach ($inner as $i => $count) {
                $total[$i] += $count;
            }
            $selector = str_replace($not[0], ' ', $selector);
        }
        $selector = (string) preg_replace('~\[[^\]]*\]~', ' .attr ', $selector);
        $total[0] += preg_match_all('~#[\w-]+~', $selector);
        $total[1] += preg_match_all('~\.[\w-]+|:(?!:)[\w-]+~', $selector);
        $total[2] += preg_match_all('/(?:^|[\s>+~])([a-z][\w-]*)/i', $selector);
        return $total;
    }

    /**
     * A card's title keeps no underline at rest in dark mode, and the hover and the keyboard's focus underline it: the
     * two rules outweigh core's dark-mode underline of #region-main's links, read from core's stylesheet.
     *
     * @return void
     */
    public function test_the_card_title_outranks_cores_dark_underline(): void {
        global $CFG;
        $core = (string) file_get_contents($CFG->dirroot . '/theme/boost/scss/moodle/dark.scss');
        $link = 'a:not(.btn):not(.nav-link):not(.dropdown-item):not(.list-group-item):not(.page-link)';
        $this->assertStringContainsString(
            $link . " {\n            text-decoration: underline;",
            $core,
            "Precondition: core's rule, as read on 5.3."
        );
        $cores = self::specificity('[data-bs-theme="dark"] #region-main ' . $link);
        $this->assertSame([1, 6, 1], $cores, 'The helper reads core\'s weight as a browser does.');

        $scss = $this->stylesheet('category.scss');
        $dark = ':root[data-bs-theme="dark"] body.dim-category';
        $rest = '#region-main .dim-card .dim-card__title a[href]:not(.btn)';
        $hover = '#region-main .dim-card--link:hover .dim-card__title a[href]:not(.btn)';
        $focus = '#region-main .dim-card--link .dim-card__title a[href]:not(.btn):focus-visible';
        $this->assertMatchesRegularExpression(
            '~^' . preg_quote($dark, '~') . ' \{\s*' . preg_quote($rest, '~') . ' \{\s*text-decoration: none;\s*\}\s*'
                . preg_quote($hover, '~') . ',\s*' . preg_quote($focus, '~') . ' \{\s*text-decoration: underline;\s*\}~m',
            $scss
        );
        $atrest = self::specificity($dark . ' ' . $rest);
        $this->assertGreaterThan(0, $atrest <=> $cores, 'The title at rest must outweigh core: ' . implode(',', $atrest));
        foreach ([$hover, $focus] as $state) {
            $weight = self::specificity($dark . ' ' . $state);
            $this->assertGreaterThan(0, $weight <=> $atrest, 'Hover and focus must outweigh the rest: ' . implode(',', $weight));
        }
    }

    /**
     * The panel's heading link is underlined on hover and focus only, in both modes.
     *
     * The link reads as the card's title does. In light mode the heading's own rule says so; in
     * dark mode core underlines every link of #region-main at (1,6,1), so the theme's two rules outweigh it, as the
     * card title's do ({@see test_the_card_title_outranks_cores_dark_underline()}).
     */
    public function test_the_panel_heading_link_underlines_on_hover_and_focus(): void {
        $scss = $this->stylesheet('category.scss');
        $title = $this->rule_body($scss, '.dim-panel__title');
        $this->assertMatchesRegularExpression(
            '~\n        a \{\s*color: var\(--dim-primary-ink\);\s*text-decoration: none;\s*\}~',
            $title,
            'Not underlined at rest.'
        );
        $this->assertMatchesRegularExpression(
            '~\n        a:hover,\s*a:focus-visible \{\s*text-decoration: underline;\s*\}~',
            $title,
            'Underlined on hover and on the keyboard\'s focus.'
        );

        $cores = self::specificity('[data-bs-theme="dark"] #region-main '
            . 'a:not(.btn):not(.nav-link):not(.dropdown-item):not(.list-group-item):not(.page-link)');
        $dark = ':root[data-bs-theme="dark"] body.dim-category';
        $rest = '#region-main .dim-panel__title a[href]:not(.btn)';
        $hover = $rest . ':hover';
        $focus = $rest . ':focus-visible';
        $this->assertMatchesRegularExpression(
            '~^' . preg_quote($dark, '~') . ' \{\s*' . preg_quote($rest, '~') . ' \{\s*text-decoration: none;\s*\}\s*'
                . preg_quote($hover, '~') . ',\s*' . preg_quote($focus, '~') . ' \{\s*text-decoration: underline;\s*\}~m',
            $scss
        );
        $atrest = self::specificity($dark . ' ' . $rest);
        $this->assertGreaterThan(0, $atrest <=> $cores, 'The link at rest must outweigh core: ' . implode(',', $atrest));
        foreach ([$hover, $focus] as $state) {
            $weight = self::specificity($dark . ' ' . $state);
            $this->assertGreaterThan(0, $weight <=> $atrest, 'Hover and focus must outweigh the rest: ' . implode(',', $weight));
        }
    }

    /**
     * The progress row is a bar and a percentage in the card's tokens, and a completed card trades it for the seal.
     *
     * A track (--dim-card-track, --dim-card-fill, --dim-card-track-height) whose fill is the course's percentage,
     * the number at the right in tabular figures; hidden until filled, and never drawn on a completed card, whose
     * pill is the green seal and whose cover gets its green base (--dim-card-done, --dim-card-done-edge).
     */
    public function test_the_progress_row_and_the_completed_card_paint_through_the_card_tokens(): void {
        $scss = $this->stylesheet('category.scss');

        $row = $this->rule_body($scss, '.dim-card__progress');
        $this->assertMatchesRegularExpression('~display:\s*flex;~', $row);
        $this->assertStringContainsString('--dim-progress: 0%;', $row, 'Nothing is filled until the shell writes the number.');
        $this->assertMatchesRegularExpression(
            '~\n    \.dim-card__progress\[hidden\],\s*\.dim-card--done \.dim-card__progress \{\s*display: none;\s*\}~',
            $scss,
            'Hidden until filled, whatever display the row has, and never beside the seal.'
        );
        $track = $this->rule_body($scss, '.dim-card__track');
        $this->assertStringContainsString('height: var(--dim-card-track-height);', $track);
        $this->assertStringContainsString('background: var(--dim-card-track);', $track);
        $fill = $this->rule_body($scss, '.dim-card__fill');
        $this->assertStringContainsString('width: var(--dim-progress);', $fill);
        $this->assertStringContainsString('background: var(--dim-card-fill);', $fill);
        $pct = $this->rule_body($scss, '.dim-card__pct');
        $this->assertStringContainsString('font-variant-numeric: tabular-nums;', $pct);
        $this->assertStringContainsString('text-align: right;', $pct);

        $seal = $this->rule_body($scss, '.dim-badge--done');
        $this->assertStringContainsString('border-color: var(--dim-card-done-rim);', $seal);
        $this->assertStringContainsString('background: var(--dim-card-done-bg);', $seal);
        $this->assertStringContainsString('color: var(--dim-card-done-ink);', $seal);
        $base = $this->rule_body($scss, '.dim-card--done .dim-card__media::before');
        $this->assertStringContainsString('bottom: 0;', $base);
        $this->assertStringContainsString('height: var(--dim-card-done-edge);', $base);
        $this->assertStringContainsString('background: var(--dim-card-done);', $base);
    }
}
