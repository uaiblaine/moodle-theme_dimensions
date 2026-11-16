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
 * Theme Dimensions - Tests for the invariants of the category showcase's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of the listing store and of the shared listing it paints with by reading their source.
 *
 * Both shells run js/esm/src/listing/store.ts over the painters and the address helpers of js/esm/src/listing;
 * the shells' own rules are listing_account_source_test's and
 * categorypublic_source_test's, and the behaviour Jest's (js/esm/tests/listing). The test keeps the name it was
 * written under, for the AMD client the store replaced.
 *
 * The properties whose loss costs a server rather than a screenshot are pinned here as well, the way
 * category_scss_test pins class names: assertions about spelling, each scoped to the one function it is about
 * through body(). The builds are esm_source_test's.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class amd_source_test extends \basic_testcase {
    /** @var string[] The shared listing's sources under js/esm/src/listing, without their extension. */
    private const LISTING = ['events', 'address', 'history', 'mounts', 'paint'];

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * One file of the theme.
     *
     * @param string $path The path under the theme root.
     * @return string
     */
    private function read(string $path): string {
        $file = $this->plugin_root() . '/' . $path;
        $this->assertFileExists($file);
        return (string) file_get_contents($file);
    }

    /**
     * The store's source.
     *
     * @return string
     */
    private function source(): string {
        return $this->read('js/esm/src/listing/store.ts');
    }

    /**
     * The shared listing's sources: the painters and the address helpers the store paints with.
     *
     * @return string
     */
    private function shared(): string {
        $text = '';
        foreach (self::LISTING as $file) {
            $text .= $this->read('js/esm/src/listing/' . $file . '.ts') . "\n";
        }
        return $text;
    }

    /**
     * The store's and the shared listing's production builds, as the browser receives them.
     *
     * @return string
     */
    private function shared_build(): string {
        $text = '';
        foreach (array_merge(self::LISTING, ['store']) as $file) {
            $text .= $this->read('js/esm/build/listing/' . $file . '.js') . "\n";
        }
        return $text;
    }

    /**
     * Both sources, for an assertion about what neither of them may contain.
     *
     * @return string
     */
    private function sources(): string {
        return $this->source() . "\n" . $this->shared();
    }

    /**
     * The body of one top-level arrow function, in the store or else in the shared listing.
     *
     * Every function of these files is a top-level `const <name> = (…) => {` (or `export const`) closed by a `};` in
     * the first column, which is what makes the extraction unambiguous. Scoping is the point: an assertion made over
     * the whole file passes when the line it looks for has been moved somewhere it never runs.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source(), $matches);
        if ($found !== 1) {
            $found = preg_match($pattern, $this->shared(), $matches);
        }
        $this->assertSame(1, $found, "No top-level function named {$name} in the store or the shared listing.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The body of the store's init().
     *
     * @return string
     */
    private function init_body(): string {
        return $this->body('init');
    }

    /**
     * A cell that was absent and comes back is drawn where the order puts it, not at the end.
     *
     * Changes that must make it fail: dropping the ordered insertion in paintGroup() (a returning
     * cell is appended last, "All, Open, My courses"); handing the scope over in the answer's
     * order instead of SCOPE's.
     */
    public function test_a_returning_cell_keeps_its_place_in_the_order(): void {
        $group = $this->body('paintGroup');
        $this->assertStringContainsString('ordered.push(input, label);', $group);
        $this->assertStringContainsString('track.insertBefore(node, expected);', $group);
        $this->assertStringContainsString('if (node !== expected) {', $group, 'Only what is out of place moves.');
        $status = $this->body('paintStatus');
        $this->assertStringContainsString('options.filter(isScope).sort((a, b) => rank(a) - rank(b))', $status);
        $this->assertStringContainsString('const rank = (option: Option): number => SCOPE.indexOf(String(option.value));', $status);
    }

    /**
     * The extractor really extracts one function and not the file around it.
     *
     * Without this, every scoped assertion below would also pass if body() returned the whole
     * module.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $tree = $this->body('onTree');
        $pager = $this->body('paintPager');
        $this->assertStringContainsString('state.cat = cat;', $tree);
        $this->assertStringNotContainsString('state.cat = cat;', $pager, 'The extractor is returning more than one function.');
        $this->assertStringNotContainsString('PAGE_WINDOW', $tree, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('PAGE_WINDOW', $pager);
    }

    /**
     * The live-page window is ten pages, and closing it hands paging to the real link.
     *
     * Without the handover the button keeps appending and the document grows without bound.
     *
     * Changes that must make it fail: raising PAGE_WINDOW; deleting `|| closed` from the button's
     * hidden expression (the button would append for ever); deleting `&& closed` from the link's
     * (the link would show beside the button on every page).
     */
    public function test_the_live_page_window_is_ten_and_hands_over_to_the_pager(): void {
        $this->assertMatchesRegularExpression('~^const PAGE_WINDOW = 10;$~m', $this->shared());
        $pager = $this->body('paintPager');
        $this->assertMatchesRegularExpression('~closed = appended >= PAGE_WINDOW;~', $pager);
        $this->assertMatchesRegularExpression('~more\.hidden = !response\.hasmore \|\| closed;~', $pager);
        $this->assertMatchesRegularExpression('~next\.hidden = !\(response\.hasmore && closed\);~', $pager);
    }

    /**
     * The typed term waits 300 ms, and the wait and the request it leads to are pending JavaScript.
     *
     * The wait is the constant rather than a number beside it. The quiet period and the request are
     * held as one pending token, resolved when the answer is painted or the term dropped: no request
     * covers the 300 ms, so without it Behat moves on from a typed term before anything has been
     * asked. The visitor's client holds its search the same way
     * ({@see categorypublic_source_test::test_the_search_is_debounced_and_never_waits_for_a_panel()}).
     *
     * Changes that must make it fail: changing SEARCH_DEBOUNCE, or replacing it in the timer with
     * a literal 300, which is how the two would drift apart; releasing the token before the term has
     * been dealt with, or never releasing a dropped one.
     */
    public function test_the_search_is_debounced_at_three_hundred_milliseconds(): void {
        $this->assertMatchesRegularExpression('~^const SEARCH_DEBOUNCE = 300;$~m', $this->source());
        // Delegated on the form: the line's component replaces the server's field with its own.
        $this->assertMatchesRegularExpression(
            "~form\\.addEventListener\\('input', \\(event\\) => \\{\\s*"
                . 'const search = Listing\\.closestOf\\(event, SELECTORS\\.search\\);\\s*'
                . 'if \\(search\\) \\{\\s*onSearchInput\\(search as HTMLInputElement\\);~',
            $this->body('listen')
        );
        $this->assertMatchesRegularExpression(
            '~^    cancelSearch\(\);\n    Listing\.paintEraser\(root\);\n'
                . '    const pending = new Pending\(\'theme_dimensions/listing:search\'\);\n'
                . '    searchpending = pending;\n    searchtimer = window\.setTimeout\(async\(\) => \{\n'
                . '        searchpending = null;\n        try \{\n            await onSearch\(search\.value\);\n'
                . '        \} finally \{\n            pending\.resolve\(\);\n        \}\n    \}, SEARCH_DEBOUNCE\);$~',
            $this->body('onSearchInput'),
            'One token from the keystroke until the term it leads to has been dealt with.'
        );
        $this->assertMatchesRegularExpression(
            '~window\.clearTimeout\(searchtimer\);\s*if \(searchpending\) \{\s*searchpending\.resolve\(\);~',
            $this->body('cancelSearch'),
            'A term dropped before it was sent releases its token.'
        );
        $this->assertMatchesRegularExpression('~^    cancelSearch\(\);$~m', $this->body('onClear'));
        $this->assertMatchesRegularExpression(
            "~notice\\.hidden = true;\\s*\\}\\s*state\\.query = term;\\s*await apply\\('term'\\);~",
            $this->body('onSearch'),
            'The token outlives the request only if the request is awaited.'
        );
    }

    /**
     * A row is rendered by the server's own template and never assembled from a string here.
     *
     * One template is what keeps a row from page four identical to a row from page one; markup
     * assembled in the client would be a second copy of the card, and an injection sink for any
     * course name that reaches it.
     *
     * Changes that must make it fail: replacing the renderForPromise call with an
     * insertAdjacentHTML of a template literal.
     */
    public function test_rows_are_rendered_through_core_templates_and_never_from_a_string(): void {
        $sources = $this->sources();
        $this->assertStringNotContainsString('insertAdjacentHTML', $sources);
        $this->assertStringNotContainsString('innerHTML', $sources);
        $shared = $this->shared();
        $this->assertStringContainsString("const Templates = await load('core/templates');", $shared);
        $this->assertMatchesRegularExpression('~Templates\.renderForPromise\(CARD_TEMPLATE~', $shared);
        $this->assertStringContainsString(
            'const items = await Listing.renderCards(response.cards || []);',
            $this->body('applyResponse'),
            'The store renders its rows with the shared listing\'s renderer.'
        );
    }

    /**
     * Focus after an append lands on a control that can take it.
     *
     * Disabling the button for the request blurs it to the document body, and a disabled element
     * cannot take focus, so focusing it before re-enabling it does nothing and leaves the reader
     * at the top of the document.
     *
     * Changes that must make it fail: deleting the restoreMore() call from the button branch of
     * focusAfterAppend().
     */
    public function test_the_load_more_button_is_restored_before_focus_is_put_back_on_it(): void {
        $restore = $this->body('restoreMore');
        $this->assertMatchesRegularExpression('~more\.disabled = false;~', $restore);

        $focus = $this->body('focusAfterAppend');
        $found = preg_match('~if \(more && !more\.hidden\) \{(.*?)\n    \}~s', $focus, $matches);
        $this->assertSame(1, $found, 'focusAfterAppend no longer has the button branch.');
        $this->assertMatchesRegularExpression(
            '~restoreMore\(more\);\s*\n\s*more\.focus\(\);~',
            $matches[1],
            'The button is focused while still disabled, which does nothing at all.'
        );
    }

    /**
     * An empty option list leaves the group as the page drew it, never emptied.
     *
     * The page was drawn with the state group, then an administrator switched the state sort off,
     * so the web service answers with no options. Read as "hide every pill", the repaint would
     * empty a group the page still renders.
     *
     * Changes that must make it fail: deleting the option-length term from the guard.
     */
    public function test_an_empty_option_list_leaves_the_group_as_the_page_drew_it(): void {
        $this->assertMatchesRegularExpression(
            '~if \(!fieldset \|\| !options\.length\) \{~',
            $this->body('paintGroup'),
            'An empty option list would hide every pill of a group the page still renders.'
        );
    }

    /**
     * The toggle is repainted whole - attribute, glyph, value and hidden field - and the pill's star is gone.
     *
     * The favourites were a pill of the state group, and a pill the client created wore the card's
     * star; they are a toggle of their own now, so no pill wears one, in the source or in the build.
     *
     * Changes that must make it fail: dropping any of the four repaints of paintFavToggle(); painting an
     * answer that does not offer the toggle; leaving the pill's star in the module.
     */
    public function test_the_favourites_toggle_is_repainted_whole_and_no_pill_wears_the_star(): void {
        $star = $this->body('paintFavToggle');
        $this->assertStringContainsString("star.setAttribute('aria-pressed', on ? 'true' : 'false');", $star);
        $this->assertStringContainsString("star.value = on ? '0' : '1';", $star);
        $this->assertStringContainsString("glyph.classList.toggle('fa-solid', on);", $star);
        $this->assertStringContainsString("glyph.classList.toggle('fa-regular', !on);", $star);
        $this->assertStringContainsString('field.disabled = !on;', $star);
        $this->assertStringContainsString("star: '[data-region=\"dim-line-star\"]',", $this->shared());
        $this->assertStringContainsString("favfield: '[data-region=\"dim-fav-field\"]',", $this->shared());
        $this->assertMatchesRegularExpression(
            '~if \(!toggle \|\| !toggle\.available\) \{\s*return;\s*\}~',
            $this->body('paintFavourites')
        );
        $this->assertStringContainsString('paintFavToggle(root, state.fav);', $this->body('paintControls'));
        foreach (['isfavourites', 'dim-pill__star'] as $gone) {
            $this->assertStringNotContainsString($gone, $this->sources());
            $this->assertStringNotContainsString($gone, $this->shared_build());
        }
    }

    /**
     * Core's own favourite web service is never called; the theme's guarded one is, from the star.
     *
     * core_course_set_favourite_courses instantiates a course context and checks nothing else,
     * so it would star a course this page is not allowed to admit exists. The positive half is the
     * control: a module that called no web service at all would pass the negative one on its own.
     * The star is its own module, js/esm/src/listing/star.ts, armed by the account's shell;
     * listing_star_source_test holds the rest.
     *
     * Changes that must make it fail: calling core's service instead of the theme's from the star.
     */
    public function test_cores_own_favourite_service_is_never_called(): void {
        $source = $this->sources();
        $star = $this->read('js/esm/src/listing/star.ts');
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $source);
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $star);
        $this->assertStringContainsString('theme_dimensions_toggle_wishlist', $star);
        $shell = $this->read('js/esm/src/listing/account.ts');
        $this->assertStringContainsString("\n    armStar();\n", $shell, 'And the account\'s shell arms it.');
    }

    /**
     * The address carries the organisation and the period, and reads them back.
     *
     * Every state of this listing has an address. Without the write, a reader who picked "A-Z"
     * and a period and then shared the page, reloaded it or pressed back would land on the
     * grouped default with no dates; without the read, the back button would paint controls the
     * next request does not carry.
     *
     * Each bound of the period is written on its own, because a start alone and an end alone are
     * periods the server answers (criteria): an address that wrote them only as a pair would drop a
     * one-bound period on the next write while the listing under it stayed narrowed by it. They are
     * read back through the calendar's reading of a period, which drops what the server drops and
     * orders a pair given backwards, as the page's own render did - the web service refuses such a
     * pair, so a hand-edited link read raw would turn the first fetch into an error.
     *
     * Changes that must make it fail: deleting the params.set of the sort; deleting either date
     * line; writing the dates only as a pair; reading the address raw.
     */
    public function test_the_address_carries_the_organisation_and_the_period(): void {
        $build = $this->body('buildUrl');
        $this->assertMatchesRegularExpression(
            '~\[PARAM\.query, PARAM\.chip, PARAM\.fav, PARAM\.page, PARAM\.view, PARAM\.sort, PARAM\.from, '
                . 'PARAM\.to, PARAM\.cat\]~',
            $build,
            'Every parameter the listing owns is cleared before it is written, the tree\'s category included.'
        );
        $this->assertMatchesRegularExpression(
            "~if \(state\.sort !== '' && state\.sort !== \(state\.defaultsort \|\| ''\)\) \{\s*"
                . "params\.set\(PARAM\.sort, state\.sort\);~",
            $build,
            'The organisation is written unless it is the category\'s default, as sort_params() mints it.'
        );
        $this->assertMatchesRegularExpression(
            "~if \(state\.datefrom !== ''\) \{\s*params\.set\(PARAM\.from, state\.datefrom\);\s*\}~",
            $build,
            'The start is written whenever it is set, with an end or without one.'
        );
        $this->assertMatchesRegularExpression(
            "~if \(state\.dateto !== ''\) \{\s*params\.set\(PARAM\.to, state\.dateto\);\s*\}~",
            $build,
            'And so is the end.'
        );
        $this->assertStringNotContainsString(
            "state.datefrom !== '' && state.dateto !== ''",
            $build,
            'The bounds are never written only as a pair.'
        );

        $read = $this->body('readUrl');
        $this->assertMatchesRegularExpression(
            "~state\.sort = sortOf\(params, state\.defaultsort\);~",
            $read,
            'The back button restores the organisation from the address, the category\'s default for none.'
        );
        $this->assertMatchesRegularExpression(
            "~return SORTS\.indexOf\(sort\) > 0 \? sort : \(defaultsort \|\| ''\);~",
            $this->body('sortOf'),
            'An address naming no organisation, or an unknown one, is not read as the category\'s default.'
        );
        $this->assertStringContainsString("const SORTS = ['', 'category', 'name', 'recent'];", $this->shared());
        $this->assertSame(['', 'category', 'name', 'recent'], criteria::SORTS, 'The client\'s copy of the vocabulary drifted.');
        $this->assertMatchesRegularExpression(
            '~const period = periodOf\(params\);\s*state\.datefrom = period\.from;\s*state\.dateto = period\.to;~',
            $read,
            'And the period, read as the server reads it.'
        );
        $this->assertStringContainsString(
            'export const periodOf = (params: URLSearchParams): Period => '
                . 'calendarPeriod(params.get(PARAM.from), params.get(PARAM.to));',
            $this->shared()
        );
        $init = $this->init_body();
        $this->assertStringContainsString(
            'const range = Listing.periodOf(params);',
            $init,
            'The first load reads it the same way.'
        );
        $this->assertMatchesRegularExpression(
            "~const defaultsort = typeof payload\.defaultsort === 'string' \? payload\.defaultsort : '';\s*"
                . '/\*[^*]*\*/\s*const remembered = payload\.remembered === true;\s*'
                . "const sort = remembered && typeof payload\.sort === 'string' \? payload\.sort : "
                . 'Listing\.sortOf\(params, defaultsort\);~',
            $init,
            'The first load reads the organisation against the category\'s default from the payload, '
                . 'or the payload\'s own when the page was drawn from the remembered state.'
        );
        $this->assertMatchesRegularExpression('~defaultsort: defaultsort,\s*sort: sort,~', $init);
        $this->assertMatchesRegularExpression('~datefrom: range\.from,\s*dateto: range\.to,~', $init);
        $range = $this->body('setRange');
        $this->assertMatchesRegularExpression(
            '~const range = period\(datefrom, dateto\);\s*'
                . 'state\.datefrom = range\.from;\s*state\.dateto = range\.to;~',
            $range,
            'And whatever hands a period over - the calendar or a quick option - is read the same way too.'
        );
    }

    /**
     * The count line is four regions of the server's, and every answer writes the four, the bar's count and "Show".
     *
     * The count line is the number in bold and the sentence after it; "See all as a list" when the
     * server offers one and the automatic opening's note, which a new answer always takes away,
     * follow the chips in the row under the filter line.
     * Writing the whole paragraph's text would wipe the other regions. Nothing is composed: the
     * number and the sentence arrive formatted, and the note is the answer's own sentence, shown
     * only once the panel's module says it opened the area. The same number is the bar's count on a
     * line the server drew and goes into the Filters panel's "Show", in the server's own sentence. The
     * sentence is visually hidden exactly where the answer offers no "See all as a list", the template's
     * own condition.
     *
     * Changes that must make it fail: the sentence's visibility read from anything but the answer's
     * seeallurl; the bar's count left unwritten on the server's line, or written from another key; the
     * "Show" label composed here rather than filled into the server's sentence.
     */
    public function test_the_count_line_is_four_regions_the_answer_writes(): void {
        $count = $this->body('paintCount');
        $this->assertStringContainsString("const number = response.countnumber || '';", $count);
        $this->assertStringContainsString('bold.textContent = number;', $count);
        $this->assertMatchesRegularExpression(
            '~const total = root\.querySelector\(SELECTORS\.total\);\s*if \(total && !drawnByLine\(total\)\) \{\s*'
                . 'total\.textContent = number;~',
            $count,
            'The bar\'s count is the sentence\'s number.'
        );
        $this->assertStringContainsString("line.classList.toggle('visually-hidden', url === '');", $count);
        $this->assertStringContainsString("const template = show ? show.dataset.dimTemplate || '' : '';", $count);
        $this->assertStringContainsString('show.textContent = template.split(SHOW_NUMBER).join(number);', $count);
        $this->assertStringContainsString("const SHOW_NUMBER = '{n}';", $this->shared());
        $this->assertSame(\theme_dimensions\output\category_toolbar::SHOW_PLACEHOLDER, '{n}', 'The server\'s spelling.');
        $this->assertStringContainsString("total: '[data-region=\"dim-line-total\"]',", $this->shared());
        $this->assertStringContainsString("filtersshow: '[data-action=\"dim-filters-show\"]',", $this->shared());
        $this->assertStringContainsString(
            "text.replaceChildren(document.createTextNode(response.countline || ''), marker);",
            $count,
            'The sentence goes into its own region, with the zero-width marker that makes a repeat speak.'
        );
        $this->assertStringContainsString("const url = response.seeallurl || '';", $count);
        $this->assertStringContainsString("seeall.hidden = url === '';", $count);
        $this->assertMatchesRegularExpression(
            '~const note = root\.querySelector<HTMLElement>\(SELECTORS\.countnote\);\s*if \(note\) \{\s*note\.hidden = true;~',
            $count,
            'Every answer takes the automatic opening\'s note away.'
        );
        $this->assertStringNotContainsString('line.replaceChildren', $count, 'The paragraph itself is never rewritten whole.');
        foreach (['countnumber', 'counttext', 'seeall', 'countnote'] as $region) {
            $this->assertStringContainsString("{$region}: '[data-region=\"dim-vitrine-{$region}\"]',", $this->shared());
        }
        // The note's own sentence is the account's shell's (listing_account_source_test).
        $this->assertMatchesRegularExpression(
            "~pending: 'dim-vitrine:pending',\s*criteria: 'dim-vitrine:criteria',\s*failed: 'dim-vitrine:failed',\s*"
                . "autoopened: 'dim-panel:autoopened',~",
            $this->shared()
        );
    }

    /**
     * The courses sitting here follow the answer's total on a grouped page with areas, the skip link too.
     *
     * A narrowed page shows where its results are, so "Courses in" goes when nothing sitting here
     * matches and comes back when something does - the server draws the same rule. A page with no
     * areas keeps the section, which is the page.
     */
    public function test_the_courses_sitting_here_follow_the_total(): void {
        $direct = $this->body('paintDirect');
        $this->assertMatchesRegularExpression(
            '~if \(!direct \|\| !root\.querySelector\(SELECTORS\.area\)\) \{\s*return;~',
            $direct,
            'A page with no areas keeps the section whatever it holds.'
        );
        $this->assertStringContainsString('const none = Number(response.total) === 0;', $direct);
        $this->assertStringContainsString('direct.hidden = none;', $direct);
        $this->assertStringContainsString('skip.hidden = none;', $direct);
        $this->assertStringContainsString("directskip: '[data-region=\"dim-direct-skip\"]',", $this->shared());
    }

    /**
     * The searched term is marked in the titles of every row this module draws, and of the server's.
     *
     * markTitles() is js/esm/src/areas/mark.ts's pure helper; this is where the listing calls it: on each
     * list item a render just produced, the replace and the append alike, and once at load over the server's
     * own grid - never over the panel, whose rail is the posters' component's.
     */
    public function test_the_term_is_marked_in_the_rows_this_module_draws(): void {
        $apply = $this->body('paintGrid');
        $this->assertStringContainsString('items.forEach((item) => markTitles(item, state.query));', $apply);
        $this->assertLessThan(
            (int) strpos($apply, 'markTitles('),
            (int) strpos($apply, 'reveal(items);'),
            'The rows are marked as they are revealed, on both paths.'
        );
        $this->assertStringContainsString('markTitles(root.querySelector(SELECTORS.grid), state.query);', $this->init_body());
    }

    /**
     * The rows of a repainted grid are held as pending JavaScript until their fade has run.
     *
     * They are inserted at opacity 0 and revealed two frames later, and WebDriver reads an element at
     * opacity 0 as not displayed, so a step counting a repainted grid's visible rows would find none
     * while the frames are still to come. The token is taken before the frames and given
     * back after the stylesheet's own duration, so Behat waits for the settled grid by itself.
     *
     * Changes that must make it fail: taking the token inside the frames; releasing it before the
     * class comes off; restating the duration instead of reading it.
     */
    public function test_a_repainted_grid_is_pending_until_its_rows_are_shown(): void {
        $reveal = $this->body('reveal');
        $this->assertMatchesRegularExpression(
            "~^    const pending = new Pending\('theme_dimensions/listing:reveal'\);\n"
                . "    window\.requestAnimationFrame\(~",
            $reveal,
            'The token is taken before the first frame.'
        );
        $this->assertLessThan(
            (int) strpos($reveal, 'pending.resolve()'),
            (int) strpos($reveal, "classList.remove('dim-vitrine__item--enter')"),
            'And given back only after the class has come off.'
        );
        $this->assertStringContainsString('window.getComputedStyle(items[0]).transitionDuration', $reveal);
        $this->assertStringContainsString('window.setTimeout(() => pending.resolve(), fade || 0);', $reveal);
    }

    /**
     * The hero's fold form follows the address, so folding after a filter keeps the filter.
     *
     * The form is drawn with the criteria of the server's render, so after a filter applied here
     * it would reload the page with the old ones and drop the reader's filters. Every write of the
     * address rewrites the form's criteria from it, the panel's two included, and the back button
     * does too. The panel's own parameters never ride into a flat organisation, whose server drops
     * them.
     */
    public function test_the_fold_form_follows_the_address(): void {
        $this->assertMatchesRegularExpression('~\n    syncFold\(\);\n$~', "\n" . $this->body('writeUrl') . "\n");
        $fold = $this->body('syncFold');
        $this->assertStringContainsString('document.querySelector(SELECTORS.fold)', $fold);
        $this->assertStringContainsString('FOLD_PARAMS.indexOf(name) >= 0 || name.startsWith(PARAM.fieldprefix)', $fold);
        $this->assertStringContainsString('new URL(window.location.href).searchParams.forEach((value, name) => {', $fold);
        $this->assertStringContainsString('Listing.syncFold();', $this->body('onPopState'), 'The back button moves it too.');
        $this->assertMatchesRegularExpression(
            '~if \(!isGrouped\(state\.sort\)\) \{\s*params\.delete\(PARAM\.open\);\s*params\.delete\(PARAM\.psort\);~',
            $this->body('buildUrl')
        );
        $this->assertStringContainsString('clear.href = Listing.withPanel(response.clearurl);', $this->body('applyResponse'));
    }

    /**
     * Nothing of the retired accordion is left in the listing or in its builds.
     *
     * The posters and the panel between their rows, the React component of js/esm/src/areas, replaced it; its
     * requests, its cache, its "Expand all", its category-card rendering and the star (now listing/star.ts's) left
     * the listing with it.
     */
    public function test_no_code_of_the_accordion_is_left(): void {
        $source = $this->sources();
        $residue = [
            'dim-accordion',
            'dim-expandall',
            'onExpandAll',
            'requestPanel',
            'paintPanel',
            'criteriaKey',
            'DIRECTORY_TEMPLATE',
            'getString',
            'core/str',
            'paintStar',
            'toggleStar',
        ];
        foreach ($residue as $gone) {
            $this->assertStringNotContainsString($gone, $source, $gone . ' is the retired accordion\'s or moved out.');
        }
        $this->assertStringNotContainsString('dim-accordion', $this->shared_build());
    }

    /**
     * The badge on the Filter summary is repainted in both of its halves, on every replaced page.
     *
     * It is the only live number a reader who collapsed the filter panel can still see, and
     * without the repaint it would go on reporting the filters of the address the page was loaded
     * with. Both halves are asserted because they are two elements - the digit and the sentence a
     * screen reader is read - and a repaint of the digit alone looks right.
     *
     * Neither half is composed here: what counts as one filter is
     * {@see criteria::filter_count()}'s rule and the sentence is a language string.
     */
    public function test_the_filter_badge_is_repainted_in_both_of_its_halves(): void {
        $paint = $this->body('paintFilterBadge');
        $this->assertStringContainsString('Number(response.filtercount || 0)', $paint);
        $this->assertStringContainsString('number.textContent = String(count);', $paint);
        $this->assertStringContainsString("label.textContent = response.filtercountlabel || '';", $paint);
        $this->assertStringContainsString('badge.hidden = count === 0;', $paint);

        // And it runs where the rest of the toolbar's live numbers are written, on a real page.
        $apply = $this->body('applyResponse');
        $this->assertStringContainsString('Listing.paintFilterBadge(root, response);', $apply);
    }

    /**
     * The native date fields are hidden behind the calendar and kept in step with it.
     *
     * They are the form's own transport and they are not removed: a control removed from the
     * document cannot come back if the calendar ever fails to mount, and one left with a stale
     * value is a control that stops being true the moment it stops being visible. The tabindex
     * is set beside the hidden attribute so that nothing which unhides a field by CSS alone can
     * put it silently back into the tab order.
     */
    public function test_the_native_date_fields_are_hidden_behind_the_calendar_and_kept_in_step(): void {
        $this->assertStringContainsString('appendCalendar(root, fieldset, region, state, onpick);', $this->body('mountCalendar'));
        $append = $this->body('appendCalendar');
        $this->assertStringContainsString('firstday: fieldset.dataset.firstday,', $append);
        $this->assertStringContainsString('today: fieldset.dataset.today,', $append);
        $this->assertStringContainsString('onpick(detail.from, detail.to);', $append);
        $this->assertStringContainsString(
            'Listing.mountCalendar(root, state, setRange).catch(shell.unmounted);',
            $this->init_body(),
            'And the store hands it setRange, which fetches the period picked.'
        );
        $this->assertMatchesRegularExpression(
            '~region\.addEventListener\(CALENDAR\.mounted, \(\) => \{\s*hideDateFields\(root\);~',
            $append,
            'A calendar that never says it is drawn leaves the two fields exactly as the server drew them.'
        );
        $hide = $this->body('hideDateFields');
        $this->assertStringContainsString('fields.hidden = true;', $hide);
        $this->assertStringContainsString('input.tabIndex = -1;', $hide);

        $dates = $this->body('paintDates');
        $this->assertStringNotContainsString('DateRange.', $dates, 'No shell mounts the AMD calendar since R4a.');
        $this->assertStringContainsString('tellCalendar(root.querySelector(SELECTORS.daterange), state);', $dates);
        $this->assertStringContainsString('input.value = state.datefrom;', $dates);
        $this->assertStringContainsString('input.value = state.dateto;', $dates);
    }

    /**
     * Nothing of the retired subcategory selector is left in the module or in the build.
     *
     * The templates no longer draw the subcategory select, so its autocomplete enhancement would
     * be dead code here. The build is read as well as the source, because the build is what a
     * browser is sent.
     */
    public function test_no_code_of_the_retired_subcategory_selector_is_left(): void {
        $source = $this->source();
        $residue = [
            'subcatselect',
            'subcatform',
            'enhanceSubcategories',
            'plainSelect',
            'onJump',
            'core/form-autocomplete',
            'core/aria',
        ];
        foreach ($residue as $gone) {
            $this->assertStringNotContainsString($gone, $source, $gone . ' is residue of the retired subcategory select.');
        }
        $this->assertStringNotContainsString('dimenhanced', $this->shared_build());
    }

    /**
     * The address carries the tree's category, and the grouped organisation carries none.
     *
     * {@see criteria} drops the criterion at construction when the organisation is the grouped
     * one, so an address naming both states a filter the answer does not have: the badge would
     * count it, the clear link would offer to remove it and no tree would be on the page to show
     * it. The organisation is also the one control of this toolbar that navigates, so this is what
     * drops the criterion on the way from a flat order into the grouped shell.
     *
     * Changes that must make it fail: dropping the `!isGrouped(state.sort)` term from buildUrl(),
     * or the `isGrouped(sort)` term from catOf() - the same rule written and read.
     */
    public function test_the_address_carries_the_trees_category_and_a_grouped_one_never_does(): void {
        $build = $this->body('buildUrl');
        $this->assertMatchesRegularExpression(
            '~if \(state\.cat > 0 && !isGrouped\(state\.sort\)\) \{\s*params\.set\(PARAM\.cat, String\(state\.cat\)\);~',
            $build,
            'The criterion is written for a flat organisation and for no other.'
        );
        $this->assertMatchesRegularExpression(
            '~PARAM\.to, PARAM\.cat\]\s*\.forEach\(\(key\) => params\.delete\(key\)\);~',
            $build,
            'And it is cleared first, like every other parameter the listing owns.'
        );
        $this->assertStringContainsString(
            'return isGrouped(sort) || !Number.isInteger(cat) || cat <= 0 ? 0 : cat;',
            $this->body('catOf'),
            'Reading it back follows the same rule, so the back button cannot restore a criterion the page drops.'
        );
        $this->assertStringContainsString('state.cat = catOf(params, state.sort);', $this->body('readUrl'));
        $this->assertStringContainsString('cat: Listing.catOf(params, sort),', $this->init_body());
    }

    /**
     * The tree is a disclosure a phone opens, and a width that hides its summary reopens it.
     *
     * The box ships open, because that is the page a reader without the client gets at every
     * width, and the stylesheet hides the summary from md up, so the list simply shows there.
     * Below md the module closes the box, or a category with many children pushes the first
     * course far down the screen.
     *
     * The change listener is required: from md up the summary is `display: none`, so a box closed
     * on a phone that a reader then widened would leave no tree and no control to bring it back.
     *
     * Changes that must make it fail: deleting the change listener.
     */
    public function test_the_tree_is_a_disclosure_on_a_phone_and_a_list_above_it(): void {
        $this->assertMatchesRegularExpression(
            "~^const TREE_PHONE = '\(max-width: 767\.98px\)';$~m",
            $this->shared(),
            'One place says where the phone ends, and it is the stylesheet\'s own md breakpoint.'
        );
        $arm = $this->body('armTreebox');
        $this->assertStringContainsString('const phone = window.matchMedia(TREE_PHONE);', $arm);
        $this->assertStringContainsString('box.open = !phone.matches;', $arm);
        $this->assertMatchesRegularExpression(
            "~phone\.addEventListener\('change', \(event\) => \{\s*box\.open = !event\.matches;~",
            $arm,
            'A reader who widens the page gets the tree back, where the summary cannot give it to them.'
        );
        $this->assertStringContainsString('Listing.armTreebox(root);', $this->init_body());
    }

    /**
     * The row under the filter line is painted from the answer, and a chip drops its one criterion.
     *
     * Nothing of a chip is composed here: its label, its address, what it drops and the words that
     * name the press are the answer's (category_toolbar::chips()), painted with the markup of the
     * server's category_chip. The row shows while the answer says something narrows, and keeps
     * only its count sentence at rest; "Clear all" follows the answer's clearall, and the Filters
     * panel's "Clear filters" whether anything narrows.
     * The state's status group is split between the scope and "More statuses" by SCOPE, the
     * server's own list. A pressed chip drops in the state exactly what its address drops, and the
     * eraser empties the search and asks again.
     *
     * Changes that must make it fail: the rest class toggled on anything but the answer's hasfilters;
     * the count sentence's visibility written here again (paintCount() writes it, from seeallurl); the
     * panel's "Clear filters" left as the page drew it; the clear link shown on hasfilters again; a chip whose
     * address loses the open panel; the period dropping one bound only; the chip press not
     * intercepted; the split read from anything but SCOPE.
     */
    public function test_the_row_under_the_line_is_painted_from_the_answer(): void {
        $chips = $this->body('paintChips');
        $this->assertStringContainsString('const narrowed = Boolean(response.hasfilters);', $chips);
        $this->assertStringContainsString("row.classList.toggle('dim-line__chips--rest', !narrowed);", $chips);
        $this->assertStringNotContainsString('SELECTORS.count', $chips, 'The sentence\'s visibility is paintCount()\'s.');
        $this->assertMatchesRegularExpression(
            '~const panelclear = root\.querySelector<HTMLElement>\(SELECTORS\.filtersclear\);\s*'
                . 'if \(panelclear\) \{\s*panelclear\.hidden = !narrowed;~',
            $chips
        );
        $this->assertStringContainsString('clear.hidden = !response.clearall;', $chips);
        $this->assertStringContainsString('chips.filter((chip) => chip.param !== CHIP_PERIOD).map(createChip)', $chips);
        $this->assertStringContainsString('chips.filter((chip) => chip.param === CHIP_PERIOD).map(createChip)', $chips);
        $create = $this->body('createChip');
        $this->assertStringContainsString('link.href = withPanel(chip.url);', $create, 'The open panel rides along.');
        $this->assertStringContainsString("link.dataset.action = 'dim-chip-remove';", $create);
        $this->assertStringContainsString('link.dataset.param = chip.param;', $create);
        $this->assertStringContainsString("document.createTextNode(chip.label + ' ')", $create);
        $this->assertStringContainsString("name.textContent = chip.removelabel || '';", $create);
        $this->assertStringNotContainsString('getString', $this->shared(), 'The shared listing composes no sentence.');

        $drop = $this->body('dropCriterion');
        $this->assertMatchesRegularExpression(
            "~\} else if \(param === CHIP_PERIOD\) \{\s*state\.datefrom = '';\s*state\.dateto = '';~",
            $drop,
            'The period\'s chip drops both bounds, as its address does.'
        );
        $this->assertStringContainsString('delete state.filters[param.slice(PARAM.fieldprefix.length)];', $drop);
        $this->assertMatchesRegularExpression(
            "~if \(param === PARAM\.query\) \{\s*state\.query = '';~",
            $drop,
            'The search\'s chip, shown where the line folded the field away, drops the term.'
        );
        $this->assertStringContainsString("const CHIP_PERIOD = 'period';", $this->shared());
        $this->assertSame(\theme_dimensions\output\category_toolbar::CHIP_PERIOD, 'period', 'The server\'s spelling.');

        $status = $this->body('paintStatus');
        $this->assertStringContainsString(
            'const isScope = (option: Option): boolean => SCOPE.indexOf(String(option.value)) >= 0;',
            $status
        );
        $this->assertStringContainsString('more.hidden = others.length === 0;', $status);

        $apply = $this->body('applyResponse');
        $this->assertStringContainsString('Listing.paintChips(root, response);', $apply);
        $this->assertStringNotContainsString('response.hasfilters', $apply, 'The clear link\'s visibility is paintChips\'.');
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~const chip = Listing\.closestOf\(event, SELECTORS\.chipremove\);\s*if \(chip && onChipRemove\(chip\)\) \{\s*'
                . '(?://[^\n]*\s*)?event\.preventDefault\(\);~',
            $listen,
            'A chip is a fetch with JavaScript, and its link otherwise.'
        );
        $this->assertMatchesRegularExpression(
            '~if \(Listing\.closestOf\(event, SELECTORS\.eraser\)\) \{\s*event\.preventDefault\(\);\s*(?://[^\n]*\s*)?onErase\(\)~',
            $listen
        );
        $remove = $this->body('onChipRemove');
        $this->assertMatchesRegularExpression(
            "~shell\\.normalise\\(state\\);\\s*Listing\\.paintControls\\(root, state\\);\\s*apply\\('filter'\\);~",
            $remove
        );
        $erase = $this->body('onErase');
        $this->assertMatchesRegularExpression("~search\.value = '';\s*search\.focus\(\{preventScroll: true\}\);~", $erase);
        $this->assertStringContainsString("await onSearch('');", $erase);
        $this->assertStringContainsString("eraser.hidden = search.value === '';", $this->body('paintEraser'));
        $this->assertStringContainsString('paintEraser(root);', $this->body('paintControls'));
    }
}
