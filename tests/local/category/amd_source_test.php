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
 * Pins the invariants of amd/src/categoryvitrine.js and of the shared listing it paints with by reading their source.
 *
 * moodle-plugin-ci has no JavaScript unit-test runner, so the properties of this module whose loss
 * costs a server rather than a screenshot are pinned the way category_scss_test pins class names.
 * They are assertions about spelling, each scoped to the one function it is about through body().
 *
 * The painters and the address helpers live in amd/src/categorylisting.js, which the visitor's
 * module paints with too; body() finds a function in either file, and the module's bindings to the
 * shared functions are one line each, which body() never matches, so a function's body is always the
 * code that runs.
 *
 * The build-currency test compares the committed source map's sourcesContent with amd/src byte for
 * byte. Moodle serves the build, so without it every source assertion here could be green over a
 * module no browser runs.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class amd_source_test extends \basic_testcase {
    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * The module's source.
     *
     * @return string
     */
    private function source(): string {
        $path = $this->plugin_root() . '/amd/src/categoryvitrine.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The shared listing's source: the painters and the address helpers this module is bound to.
     *
     * @return string
     */
    private function shared(): string {
        $path = $this->plugin_root() . '/amd/src/categorylisting.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
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
     * One module as the browser actually receives it.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function build(string $module = 'categoryvitrine'): string {
        $path = $this->plugin_root() . '/amd/build/' . $module . '.min.js';
        $this->assertFileExists($path, 'The build is missing: Moodle serves amd/build, never amd/src.');
        return (string) file_get_contents($path);
    }

    /**
     * The source one module's committed build was actually made from.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function built_from(string $module = 'categoryvitrine'): string {
        $path = $this->plugin_root() . '/amd/build/' . $module . '.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * The body of one top-level arrow function, in the module or else in the shared listing.
     *
     * Every function of the two files is a top-level `const <name> = (…) => {` (or `export const`)
     * closed by a `};` in the first column, which is what makes the extraction unambiguous. A
     * binding of the module to a shared function is one line with no brace at its end, so it is
     * never matched: the body found is the code that runs. Scoping is the point: an assertion made
     * over the whole file passes when the line it looks for has been moved somewhere it never runs.
     *
     * @param string $name The function's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source(), $matches);
        if ($found !== 1) {
            $found = preg_match($pattern, $this->shared(), $matches);
        }
        $this->assertSame(1, $found, "No top-level function named {$name} in the module or the shared listing.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
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
        $this->assertStringContainsString('const rank = (option) => SCOPE.indexOf(String(option.value));', $status);
    }

    /**
     * The body of the module's one exported function.
     *
     * @return string
     */
    private function init_body(): string {
        $source = $this->source();
        $found = preg_match('~\nexport const init = [^\n]*\{\n(.*?)\n\};~s', $source, $matches);
        $this->assertSame(1, $found, 'The module exports no init().');
        return $matches[1];
    }

    /**
     * The extractor really extracts one function and not the file around it.
     *
     * Without this, every scoped assertion below would also pass if body() returned the whole
     * module.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $settle = $this->body('settle');
        $pager = $this->body('paintPager');
        $this->assertStringContainsString('unobserve', $settle);
        $this->assertStringNotContainsString('unobserve', $pager, 'The extractor is returning more than one function.');
        $this->assertStringNotContainsString('PAGE_WINDOW', $settle, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('PAGE_WINDOW', $pager);
    }

    /**
     * A settled id is unobserved, and settling is what a finished batch does whatever it answered.
     *
     * Progress does not change while the page is open, so a settled card needs no more watching.
     * Without the unobserve the watch list grows with every row appended; and settling in the
     * `finally` is what stops a batch that failed being asked for again on every scroll.
     *
     * Changes that must make it fail: deleting `observer.unobserve(card);` from settle(), or moving
     * the `settle(batch)` call out of the `finally` into the `try`.
     */
    public function test_a_settled_course_is_unobserved_and_every_finished_batch_settles(): void {
        $settle = $this->body('settle');
        $this->assertMatchesRegularExpression('~observer\.unobserve\(card\)~', $settle);
        $this->assertMatchesRegularExpression('~settled\.add\(id\)~', $settle);
        /* It leaves the pending set too: the observer keeps firing while a batch is in flight, so
           a card that scrolled out and back would otherwise be asked for again by the next drain. */
        $this->assertMatchesRegularExpression('~pending\.delete\(id\)~', $settle);

        $drain = $this->body('drainPending');
        $found = preg_match('~\}\s*finally\s*\{(.*?)\n    \}~s', $drain, $matches);
        $this->assertSame(1, $found, 'The details drain has no finally block, so a failed batch settles nothing.');
        $this->assertMatchesRegularExpression(
            '~settle\(batch\)~',
            $matches[1],
            'settle() is not in the finally: a batch that FAILED would be asked again on every scroll.'
        );
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
        // Delegated on the form: the line's component (stage R2) replaces the server's field with its own.
        $this->assertMatchesRegularExpression(
            "~form\\.addEventListener\\('input', \\(event\\) => \\{\\s*"
                . 'const search = closestOf\\(event, SELECTORS\\.search\\);\\s*'
                . 'if \\(search\\) \\{\\s*onSearchInput\\(search\\);~',
            $this->body('listen')
        );
        $this->assertMatchesRegularExpression(
            '~^    cancelSearch\(\);\n    paintEraser\(\);\n'
                . '    const token = new Pending\(\'theme_dimensions/categoryvitrine:search\'\);\n'
                . '    searchpending = token;\n    searchtimer = window\.setTimeout\(async\(\) => \{\n'
                . '        searchpending = null;\n        try \{\n            await onSearch\(search\.value\);\n'
                . '        \} finally \{\n            token\.resolve\(\);\n        \}\n    \}, SEARCH_DEBOUNCE\);$~',
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
            '~notice\.hidden = true;\s*\}\s*state\.query = term;\s*state\.page = 0;\s*writeUrl\(true\);\s*'
                . 'await requestRows\(0, false\);~',
            $this->body('onSearch'),
            'The token outlives the request only if the request is awaited.'
        );
    }

    /**
     * The details batch is capped at 24 ids and drains on a fixed 100 ms interval.
     *
     * 24 is {@see enrichment::MAX_COURSES}: get_card_details refuses a larger batch. The interval is
     * fixed because a timeout reset per id sends nothing at all during a continuous scroll.
     *
     * Changes that must make it fail: raising BATCH_SIZE; deleting the `.slice(0, BATCH_SIZE)`;
     * replacing `setInterval` with a `setTimeout` reset per id.
     */
    public function test_the_details_batch_is_capped_and_drains_on_a_fixed_interval(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression('~^const BATCH_SIZE = 24;$~m', $source);
        $this->assertMatchesRegularExpression('~^const BATCH_INTERVAL = 100;$~m', $source);
        $this->assertMatchesRegularExpression('~\.slice\(0, BATCH_SIZE\)~', $this->body('drainPending'));
        $this->assertMatchesRegularExpression('~setInterval\(drainPending, BATCH_INTERVAL\)~', $this->init_body());
    }

    /**
     * One panel of the grid is open at a time, whichever of the two ways it was opened.
     *
     * The disclosure and the hover peek show the same panel, so without this a reader can have
     * many panels open over the cards at once. Four things must hold, and each fails silently on
     * its own: the closing loop exists, it spares the disclosure being opened, it closes the
     * pointer's peek as well, and the toggle listener is registered in the capture phase - a
     * details element's toggle event does not bubble, so a delegated listener without that flag
     * is never called.
     *
     * Changes that must make it fail: emptying the loop body of closeOthers(); dropping the
     * `details !== keep` test; removing the closePeeks() call; passing `false` as the toggle
     * listener's capture flag.
     */
    public function test_only_one_panel_of_the_grid_is_open_at_a_time(): void {
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~root\.addEventListener\(\'toggle\',.*?closeOthers\(details\);.*?\}, true\);~s',
            $listen,
            'The toggle listener is delegated to the grid AND registered in the capture phase, '
                . 'which is the only phase a non-bubbling toggle event reaches an ancestor in.'
        );
        $closers = $this->body('closeOthers');
        $this->assertMatchesRegularExpression('~root\.querySelectorAll\(SELECTORS\.details\)~', $closers);
        $this->assertMatchesRegularExpression('~details\.open = false;~', $closers);
        $this->assertMatchesRegularExpression('~details !== keep~', $closers, 'The one being opened is spared.');
        $this->assertMatchesRegularExpression('~closePeeks\(\);~', $closers, 'A peek is a panel too.');
        // And the other direction: a peek that opens closes the disclosures, and Escape closes both.
        $this->assertMatchesRegularExpression('~closeOthers\(null\);~', $this->body('openPeek'));
        $found = preg_match('~event\.key === \'Escape\'\) \{(.*?)\}~s', $listen, $matches);
        $this->assertSame(1, $found, 'The module handles no Escape key.');
        $this->assertMatchesRegularExpression('~closeOthers\(null\)~', $matches[1]);
    }

    /**
     * A peek never opens over a disclosure the reader opened on the same card.
     *
     * The pointer is still inside the card whose summary was just pressed, so the next mouseenter
     * would promote a peek, and the peek would close that disclosure: the reader's own panel
     * collapses on a card they never left.
     *
     * Changes that must make it fail: removing the `details && details.open` term.
     */
    public function test_a_peek_never_replaces_the_disclosure_of_its_own_card(): void {
        $peek = $this->body('openPeek');
        $this->assertMatchesRegularExpression(
            '~const details = card\.querySelector\(SELECTORS\.details\);~',
            $peek
        );
        $this->assertMatchesRegularExpression('~!panel \|\| \(details && details\.open\)~', $peek);
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
        $this->assertStringContainsString("import Templates from 'core/templates';", $shared);
        $this->assertMatchesRegularExpression('~Templates\.renderForPromise\(CARD_TEMPLATE~', $shared);
        $this->assertStringContainsString(
            'const renderCards = (cards) => Listing.renderCards(cards);',
            $this->source(),
            'The module renders its rows with the shared listing\'s renderer.'
        );
    }

    /**
     * A replaced grid gives its cards back to the observer before it discards them.
     *
     * settle() unobserves the ids of a completed batch only. An enrolled card discarded by a
     * filter before it was ever scrolled into view was never settled, and IntersectionObserver
     * holds its targets strongly, so every filter change would leak such cards.
     *
     * The replace branch is the shared listing's paintGrid(), which hands each card to the caller's
     * discard() before it goes; this module's discard() is what holds the observer and the pending set.
     *
     * Changes that must make it fail: deleting the unobserve from discard(); dropping the discard
     * loop before replaceChildren; binding paintGrid without this module's discard.
     */
    public function test_a_replaced_grid_unobserves_the_cards_it_discards(): void {
        // The replace branch is in paintGrid(), which applyResponse() skips at the root: it has no course grid.
        $this->assertStringContainsString('paintGrid(grid, items, append);', $this->body('applyResponse'));
        $this->assertStringContainsString(
            'const paintGrid = (grid, items, append) => Listing.paintGrid(root, state, grid, items, append, discard);',
            $this->source(),
            'The module hands the shared grid painter its own discard().'
        );
        $apply = $this->body('paintGrid');
        $found = preg_match('~\} else \{(.*?)grid\.replaceChildren~s', $apply, $matches);
        $this->assertSame(1, $found, 'paintGrid has no replace branch to read.');
        $this->assertMatchesRegularExpression(
            '~grid\.querySelectorAll\(SELECTORS\.card\)\.forEach\(\(card\) => discard\(card\)\);~',
            $matches[1],
            'The replace branch discards its cards without handing them to the caller first.'
        );
        $discard = $this->body('discard');
        $this->assertMatchesRegularExpression(
            '~observer\.unobserve\(card\)~',
            $discard,
            'A discarded card is not given back to the observer.'
        );
        $this->assertMatchesRegularExpression(
            '~pending\.delete\(courseid\)~',
            $discard,
            'A discarded card stays in the pending set and is asked about after it is gone.'
        );
    }

    /**
     * An appended page is state of this document and is never written into the address bar.
     *
     * The server's page number comes back for both paths, but only a replace is a new address:
     * after two appends the document holds pages 0 to 2, and writing "page=2" into the address
     * would hand out a link to a document showing page two alone, with the numbered pager hidden
     * and no route back to the pages above it.
     *
     * Changes that must make it fail: moving `state.page = lastpage;` out of the `!append` branch;
     * asking for `state.page + 1` instead of `lastpage + 1` in the load-more handler.
     */
    public function test_the_appended_page_is_not_the_page_the_address_names(): void {
        $apply = $this->body('applyResponse');
        $this->assertMatchesRegularExpression('~lastpage = Number\(response\.page\);~', $apply);
        $this->assertMatchesRegularExpression(
            '~if \(!append\) \{\n\s*state\.page = lastpage;\n\s*\}~',
            $apply,
            'state.page follows the ADDRESS, so it may only move when the grid was replaced.'
        );
        $this->assertMatchesRegularExpression(
            '~requestRows\(lastpage \+ 1, true\)~',
            $this->body('listen'),
            'The next append follows the GRID, so it asks for the page after the last one drawn.'
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
     * Every rows request carries the page size the address asked for.
     *
     * The showcase honours a perpage in its query string, so a listing can be open at a size the
     * site setting never mentions; a request that does not carry it is answered at the site
     * default, and the page clamp then turns "page one of a six-row listing" into "page 0 of a
     * forty-eight-row one" - the whole listing appended over the rows on screen.
     *
     * Changes that must make it fail: deleting the perpage line from rowsArgs(); making
     * pageSizeOf() return 0 unconditionally.
     */
    public function test_the_page_size_of_the_address_travels_with_every_rows_request(): void {
        $this->assertMatchesRegularExpression('~perpage: \x27perpage\x27,~', $this->shared());
        $this->assertMatchesRegularExpression(
            '~perpage: state\.pagesize,~',
            $this->body('rowsArgs'),
            'The rows request does not carry the page size, so later pages are cut differently.'
        );
        $this->assertStringContainsString('args: rowsArgs(page),', $this->body('requestRows'), 'It is the request\'s own.');
        $reader = $this->body('pageSizeOf');
        $this->assertMatchesRegularExpression('~params\.get\(PARAM\.perpage\)~', $reader);
        $this->assertMatchesRegularExpression('~size > 0 \? size : 0~', $reader);
    }

    /**
     * The state the observer watches for is the classifier's own, spelled the same on both sides.
     *
     * The literal in the module and the constant in PHP are one vocabulary written twice. If the
     * classifier renames the state, the module stops watching any card and progress never
     * appears, on a page that is otherwise correct.
     *
     * Changes that must make it fail: changing the JavaScript literal.
     */
    public function test_the_watched_state_is_the_classifiers_own_spelling(): void {
        $this->assertStringContainsString(
            "card.dataset.state !== '" . classifier::STATE_ENROLLED . "'",
            $this->body('watchCard'),
            'The module watches a state the classifier does not produce.'
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
     * The favourites toggle is a fetch with a history entry, sent with every request and remembered.
     *
     * The toggle combines with the state chip, so it rides beside it in the rows request and in the
     * remembered state, is written into the address as fav=1 and read back from it - an older
     * address's favourites chip read as the toggle with no chip, as the server reads it - and is
     * repainted from every answer. The press is intercepted, because the toggle is a submit button
     * for a reader without this module.
     *
     * Changes that must make it fail: dropping fav from either set of arguments; deleting the
     * interception or the toggle in onFav(); writing the address without fav; reading the legacy chip
     * as a chip; not repainting the toggle from an answer.
     */
    public function test_the_favourites_toggle_is_a_fetch_sent_with_every_request(): void {
        $this->assertMatchesRegularExpression('~chip: state\.chip,\s*fav: state\.fav,~', $this->body('rowsArgs'));
        $this->assertMatchesRegularExpression(
            '~view: state\.view,\s*chip: state\.chip,\s*fav: state\.fav,\s*filters: filterArgs\(\),~',
            $this->source(),
            'The remembered state carries the toggle beside the chip.'
        );
        $this->assertStringContainsString("fav: '[data-action=\"dim-fav\"]',", $this->source());
        $this->assertMatchesRegularExpression(
            '~if \(closestOf\(event, SELECTORS\.fav\)\) \{[^}]*?event\.preventDefault\(\);\s*onFav\(\);~s',
            $this->body('listen')
        );
        $fav = $this->body('onFav');
        $steps = [
            'state.fav = !state.fav;',
            'state.page = 0;',
            'paintFavToggle();',
            'remember(false);',
            'writeUrl(true);',
            'requestRows(0, false);',
        ];
        foreach ($steps as $step) {
            $this->assertStringContainsString($step, $fav);
        }
        $this->assertStringContainsString('paintFavourites(response);', $this->body('applyResponse'));
        $this->assertMatchesRegularExpression(
            "~if \(state\.fav\) \{\s*params\.set\(PARAM\.fav, '1'\);\s*\}~",
            $this->body('buildUrl')
        );
        $read = $this->body('readUrl');
        $this->assertStringContainsString('state.chip = chipOf(params);', $read);
        $this->assertStringContainsString('state.fav = favOf(params);', $read);
        $this->assertStringContainsString(
            "export const favOf = (params) => params.get(PARAM.fav) === '1' || params.get(PARAM.chip) === LEGACY_FAVOURITES;",
            $this->shared()
        );
        $this->assertMatchesRegularExpression(
            "~const chip = params\.get\(PARAM\.chip\) \|\| '';\s*return chip === LEGACY_FAVOURITES \? '' : chip;~",
            $this->body('chipOf')
        );
        $this->assertStringContainsString("const LEGACY_FAVOURITES = 'favourites';", $this->shared());
        $this->assertStringContainsString('PARAM.fav', $this->body('buildUrl'));
        $this->assertStringContainsString("fav: payload.fav === true,", $this->init_body());
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
            $this->assertStringNotContainsString($gone, $this->build('categorylisting'));
        }
    }

    /**
     * Core's own favourite web service is never called; the theme's guarded one is, from categorystar.
     *
     * core_course_set_favourite_courses instantiates a course context and checks nothing else,
     * so it would star a course this page is not allowed to admit exists. The positive half is the
     * control: a module that called no web service at all would pass the negative one on its own.
     * The star is its own module, categorystar, armed by this module and by categorypanel;
     * categorypanel_source_test holds the rest.
     *
     * Changes that must make it fail: calling core's service instead of the theme's from categorystar.
     */
    public function test_cores_own_favourite_service_is_never_called(): void {
        $source = $this->sources();
        $star = (string) file_get_contents($this->plugin_root() . '/amd/src/categorystar.js');
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $source);
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $star);
        $this->assertStringContainsString('theme_dimensions_toggle_wishlist', $star);
        $this->assertStringContainsString('CategoryStar.init();', $this->init_body(), 'And this module arms it.');
    }

    /**
     * Moving between the two shells LOADS the address; moving inside one fetches into it.
     *
     * The grouped organisation draws the posters of the areas and the flat ones draw a course grid
     * alone, so the two are different documents. A fetch across that line paints an answer into
     * markup that cannot hold it, and nothing errors. Between the two flat orders the shell is
     * the same, so a change there is an ordinary fetch with a history entry.
     *
     * Changes that must make it fail: replacing the leave() call (whose last line is the
     * location.assign()) with a requestRows(0, false); widening GROUPED_SORTS beyond the two grouped values
     * {@see criteria::is_grouped()} accepts.
     */
    public function test_the_organisation_is_a_navigation_between_shells_and_a_fetch_inside_one(): void {
        $source = $this->shared();
        $this->assertMatchesRegularExpression(
            "~^const GROUPED_SORTS = \['', 'category'\];$~m",
            $source,
            'The empty value and the named default are the one grouped organisation, as the server has it.'
        );
        $sort = $this->body('onSort');
        $this->assertMatchesRegularExpression(
            '~if \(was !== isGrouped\(sort\)\) \{\s*leave\(\);\s*return;~',
            $sort,
            'A change of shell must load the address rather than fetch into markup that cannot hold it.'
        );
        $this->assertStringContainsString(
            'window.location.assign(buildUrl().toString());',
            $this->body('leave'),
            'And what it calls loads the address (once the state it leaves is remembered).'
        );
        $this->assertStringContainsString('requestRows(0, false);', $sort, 'And a change inside one shell fetches.');

        // And the chips really reach that function: the sort group is a radio group like the others.
        $choose = $this->body('onChoose');
        $this->assertMatchesRegularExpression(
            '~if \(name === PARAM\.sort\) \{\s*onSort\(input\.value\);~',
            $choose,
            'The organisation chips are radios of the same delegated change handler.'
        );
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
            'Every parameter this module owns is cleared before it is written, the tree\'s category included.'
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
            'export const periodOf = (params) => DateRange.period(params.get(PARAM.from), params.get(PARAM.to));',
            $this->shared()
        );
        $init = $this->init_body();
        $this->assertStringContainsString('const period = periodOf(params);', $init, 'The first load reads it the same way.');
        $this->assertMatchesRegularExpression(
            "~const defaultsort = typeof payload\.defaultsort === 'string' \? payload\.defaultsort : '';\s*"
                . '/\*[^*]*\*/\s*const isremembered = payload\.remembered === true;\s*'
                . "const sort = isremembered && typeof payload\.sort === 'string' \? payload\.sort : "
                . 'Listing\.sortOf\(params, defaultsort\);~',
            $init,
            'The first load reads the organisation against the category\'s default from the payload, '
                . 'or the payload\'s own when the page was drawn from the remembered state.'
        );
        $this->assertMatchesRegularExpression('~defaultsort: defaultsort,\s*sort: sort,~', $init);
        $this->assertMatchesRegularExpression('~datefrom: period\.from,\s*dateto: period\.to,~', $init);
        $range = $this->body('setRange');
        $this->assertMatchesRegularExpression(
            '~const period = DateRange\.period\(datefrom, dateto\);\s*'
                . 'state\.datefrom = period\.from;\s*state\.dateto = period\.to;~',
            $range,
            'And whatever hands a period over - the calendar or a quick option - is read the same way too.'
        );
    }

    /**
     * A replaced answer tells the posters' component each area's shell, and writes none itself.
     *
     * The shell - the call to action's address and label a panel shows before its rows arrive -
     * carries the page's criteria, so a filter changes it; the server composes it for every area,
     * it travels in the event's accordion and the component writes it, the posters being its alone
     * since stage R3. The event is what makes the component ask the open panel again. It runs on a
     * replaced grid and never on an appended page, which is more of the same question.
     *
     * Changes that must make it fail: this module writing a poster's shell again (gate
     * js_areas_shells_untouched).
     *
     * The event also carries what the posters need - the answer's areas, whether it narrows, its
     * total - and the count line and the courses sitting here are painted before it, so the count
     * is announced first and the panel's status after.
     */
    public function test_a_narrowed_page_writes_the_shells_and_tells_the_panel(): void {
        $paint = $this->body('paintShells');
        // Since stage R3 the posters are the component's to write: the shells travel in the event's accordion.
        $this->assertStringNotContainsString('poster.dataset', $paint);
        $this->assertStringNotContainsString('SELECTORS.area', $paint);
        $this->assertStringContainsString(
            "poster.dataset.ctaurl = entry.ctaurl || '';",
            (string) file_get_contents(dirname(__DIR__, 3) . '/js/esm/src/areas/posters.ts')
        );
        $this->assertMatchesRegularExpression(
            '~document\.dispatchEvent\(new CustomEvent\(EVENTS\.criteria, \{detail: \{\s*accordion: accordion,\s*'
                . 'areas: areas,\s*filtered: Boolean\(areas\.filtered\),\s*total: Number\(response\.total\),\s*\}\}\)\);~',
            $paint
        );
        $this->assertStringContainsString('const areas = response.areas || {};', $paint);
        $this->assertStringContainsString("autoopenlabel = areas.autoopenlabel || '';", $paint);
        $apply = $this->body('applyResponse');
        $this->assertMatchesRegularExpression(
            '~if \(!append\) \{\s*paintDirect\(response\);\s*paintShells\(response\);~',
            $apply
        );
        $this->assertLessThan(
            (int) strpos($apply, 'paintShells(response);'),
            (int) strpos($apply, 'paintCount(response);'),
            'The count line is painted before the panel\'s module is told.'
        );
        $this->assertMatchesRegularExpression(
            "~view: 'dim-vitrine:view',\s*pending: 'dim-vitrine:pending',\s*criteria: 'dim-vitrine:criteria',~",
            $this->source()
        );
        // And the view's own event, so the panel's row follows the list view's single track.
        $this->assertStringContainsString(
            'document.dispatchEvent(new CustomEvent(EVENTS.view, {detail: {view: view}}));',
            $this->body('paintView')
        );
    }

    /**
     * The count line is four regions of the server's, and every answer writes the four.
     *
     * The count line is the number in bold and the sentence after it; "See all as a list" when the
     * server offers one and the automatic opening's note, which a new answer always takes away,
     * follow the chips in the row under the filter line.
     * Writing the whole paragraph's text would wipe the other regions. Nothing is composed: the
     * number and the sentence arrive formatted, and the note is the answer's own sentence, shown
     * only once the panel's module says it opened the area.
     */
    public function test_the_count_line_is_four_regions_the_answer_writes(): void {
        $count = $this->body('paintCount');
        $this->assertStringContainsString("number.textContent = response.countnumber || '';", $count);
        $this->assertStringContainsString(
            "text.replaceChildren(document.createTextNode(response.countline || ''), marker);",
            $count,
            'The sentence goes into its own region, with the zero-width marker that makes a repeat speak.'
        );
        $this->assertStringContainsString("const url = response.seeallurl || '';", $count);
        $this->assertStringContainsString("seeall.hidden = url === '';", $count);
        $this->assertMatchesRegularExpression(
            '~const note = root\.querySelector\(SELECTORS\.countnote\);\s*if \(note\) \{\s*note\.hidden = true;~',
            $count,
            'Every answer takes the automatic opening\'s note away.'
        );
        $this->assertStringNotContainsString('line.replaceChildren', $count, 'The paragraph itself is never rewritten whole.');
        foreach (['countnumber', 'counttext', 'seeall', 'countnote'] as $region) {
            $this->assertStringContainsString("{$region}: '[data-region=\"dim-vitrine-{$region}\"]',", $this->shared());
        }
        foreach (['countnote', 'countnotetext'] as $region) {
            $this->assertStringContainsString("{$region}: '[data-region=\"dim-vitrine-{$region}\"]',", $this->source());
        }
        $note = $this->body('paintAutoOpened');
        $this->assertStringContainsString('text.textContent = autoopenlabel;', $note);
        $this->assertStringContainsString('note.hidden = false;', $note);
        $this->assertStringContainsString('document.addEventListener(EVENTS.autoopened, paintAutoOpened);', $this->body('listen'));
        $this->assertMatchesRegularExpression(
            "~pending: 'dim-vitrine:pending',\s*criteria: 'dim-vitrine:criteria',\s*autoopened: 'dim-panel:autoopened',~",
            $this->source()
        );
    }

    /**
     * The panel's module is told when a request for new criteria goes out, and whether it narrows.
     *
     * Its pills then say "..." and its grids are busy until the answer, so nothing on the posters
     * jumps. Only a replacing request on a page with areas tells it: an append is more of the same
     * question. Whether it narrows mirrors {@see criteria::has_filters()}, read off the requested
     * state: a period narrows with either of its bounds.
     */
    public function test_the_panel_module_is_told_a_request_is_out(): void {
        $rows = $this->body('requestRows');
        $this->assertMatchesRegularExpression(
            '~if \(!append && root\.querySelector\(SELECTORS\.area\)\) \{\s*'
                . 'document\.dispatchEvent\(new CustomEvent\(EVENTS\.pending, \{detail: \{filtered: narrows\(\)\}\}\)\);~',
            $rows
        );
        $this->assertLessThan(
            (int) strpos($rows, 'await Ajax.call('),
            (int) strpos($rows, 'EVENTS.pending'),
            'It is told before the request goes out.'
        );
        $this->assertMatchesRegularExpression(
            "~export const narrows = \(state\) => state\.query !== ''\s*\|\| state\.chip !== ''\s*"
                . "\|\| Boolean\(state\.fav\)\s*\|\| Object\.keys\(state\.filters\)\.length > 0\s*"
                . "\|\| state\.datefrom !== ''\s*\|\| state\.dateto !== '';~",
            $this->shared()
        );
        $this->assertStringContainsString('const narrows = () => Listing.narrows(state);', $this->source());
    }

    /**
     * The panel's module is told when the latest replacing request fails, so its pills and grids stop waiting.
     *
     * The pending event makes every pill say "..." and every grid busy until the answer; the answer's
     * event is the only thing that undoes it, and a failed request sends none. So the catch tells the
     * panel's module the question will not be answered - only for the latest request (an overtaken
     * one leaves it to the newer one) and only where the pending event was sent: a replacing request
     * on a page with areas.
     */
    public function test_a_failed_request_tells_the_panel_module_its_answer_is_not_coming(): void {
        $rows = $this->body('requestRows');
        $found = preg_match('~\} catch \(error\) \{(.*?)\} finally \{~s', $rows, $matches);
        $this->assertSame(1, $found, 'The rows request has no catch block.');
        $this->assertMatchesRegularExpression(
            '~if \(ticket === seq && !append && root\.querySelector\(SELECTORS\.area\)\) \{\s*'
                . 'document\.dispatchEvent\(new CustomEvent\(EVENTS\.failed\)\);~',
            $matches[1],
            'A failed latest request tells the posters, where the pending event told them one was out.'
        );
        $this->assertStringContainsString('Notification.exception(error);', $matches[1], 'And the reader is still told.');
        $this->assertMatchesRegularExpression(
            "~autoopened: 'dim-panel:autoopened',\s*failed: 'dim-vitrine:failed',~",
            $this->source()
        );
    }

    /**
     * An answer overtaken while its cards were rendering paints nothing and tells the panel's module nothing.
     *
     * The render is asynchronous, so "the latest request wins" is checked again once it resolves,
     * as the panel's module does after each of its renders. Checked only before, an older answer
     * landing while a newer request is out would paint the count line, rewrite the posters, take
     * the newer request's pending state away and could open an area the newer answer would not -
     * and nothing closes a panel on its own. The page's state follows the painted answer, so it is
     * written after the check.
     */
    public function test_an_answer_overtaken_while_rendering_paints_nothing(): void {
        $apply = $this->body('applyResponse');
        $render = strpos($apply, 'const items = await renderCards(response.cards || []);');
        $check = strpos($apply, "if (ticket !== seq) {\n        return;\n    }");
        $state = strpos($apply, 'lastpage = Number(response.page);');
        $shells = strpos($apply, 'paintShells(response);');
        $this->assertIsInt($render);
        $this->assertIsInt($check, 'The answer checks it is still the latest once its cards are rendered.');
        $this->assertIsInt($state);
        $this->assertIsInt($shells);
        $this->assertTrue(
            $render < $check && $check < $state && $state < $shells,
            'The check sits between the render and every write, the page\'s state and the panel\'s event included.'
        );
        $this->assertStringContainsString('await applyResponse(response, append, ticket);', $this->body('requestRows'));
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
     * "Clear filters" clears in place and puts the focus in the search box.
     *
     * Three controls carry the action - the count row's link, the areas' "nothing matches" box and
     * the empty listing's - and each is a real address without this module. With it the press is
     * intercepted, the narrowing criteria, the tree's category and the page are emptied while the
     * organisation stays (as {@see criteria::keep_params()} keeps it), the controls are repainted
     * and the page asked again; and the focus goes to the search box, because the control pressed
     * is about to hide itself and the focus would fall to the body.
     */
    public function test_clearing_the_filters_is_a_fetch_that_focuses_the_search_box(): void {
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~const clear = closestOf\(event, SELECTORS\.clearaction\);\s*if \(clear\) \{[^}]*?'
                . 'event\.preventDefault\(\);\s*onClear\(\);~s',
            $listen
        );
        $this->assertStringContainsString("clearaction: '[data-action=\"dim-clear\"]',", $this->source());
        $clear = $this->body('onClear');
        $steps = [
            "state.query = '';",
            "state.chip = '';",
            'state.fav = false;',
            'state.filters = {};',
            "state.datefrom = '';",
            "state.dateto = '';",
            'state.cat = 0;',
            'state.page = 0;',
            'paintControls();',
            'writeUrl(true);',
            'requestRows(0, false);',
        ];
        foreach ($steps as $step) {
            $this->assertStringContainsString($step, $clear, 'Clearing no longer runs ' . $step);
        }
        $this->assertMatchesRegularExpression(
            '~const search = focusTarget\(\);\s*if \(search\) \{\s*search\.focus\(\{preventScroll: true\}\);~',
            $clear,
            'The focus goes to the search box, without a scroll - or to its icon where the line folded the field away.'
        );
        $this->assertStringContainsString('const focusTarget = () => Listing.focusTarget(root);', $this->source());
        $this->assertLessThan(
            (int) strpos($clear, 'search.focus('),
            (int) strpos($clear, 'requestRows(0, false);'),
            'After the request is out, as the design does it.'
        );
    }

    /**
     * The searched term is marked in the titles of every row this module draws, and of the server's.
     *
     * categorymark is a pure helper (categorypanel_source_test holds its shape); this is where the
     * listing calls it: on each list item a render just produced, the replace and the append alike,
     * and once at load over the server's own grid - never over the panel, whose rail is the panel
     * module's.
     */
    public function test_the_term_is_marked_in_the_rows_this_module_draws(): void {
        $apply = $this->body('paintGrid');
        $this->assertStringContainsString('items.forEach((item) => CategoryMark.mark(item, state.query));', $apply);
        $this->assertLessThan(
            (int) strpos($apply, 'CategoryMark.mark('),
            (int) strpos($apply, 'reveal(items);'),
            'The rows are marked as they are revealed, on both paths.'
        );
        $this->assertStringContainsString(
            'CategoryMark.mark(root.querySelector(SELECTORS.grid), state.query);',
            $this->init_body()
        );
    }

    /**
     * The rows of a repainted grid are held as pending JavaScript until their fade has run.
     *
     * They are inserted at opacity 0 and revealed two frames later, and WebDriver reads an element at
     * opacity 0 as not displayed: a step counting a repainted grid's visible rows found none on a
     * slow leg while the frames were still to come. The token is taken before the frames and given
     * back after the stylesheet's own duration, so Behat waits for the settled grid by itself.
     *
     * Changes that must make it fail: taking the token inside the frames; releasing it before the
     * class comes off; restating the duration instead of reading it.
     */
    public function test_a_repainted_grid_is_pending_until_its_rows_are_shown(): void {
        $reveal = $this->body('reveal');
        $this->assertMatchesRegularExpression(
            "~^    const pending = new Pending\('theme_dimensions/categorylisting:reveal'\);\n"
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
     * A course card of the panel is never peeked: init arms the listing's cards and no mini.
     *
     * A mini is the card in a smaller presentation with its disclosure out of reach; the peek
     * would open that unreachable disclosure's copy beside a card in a rail that clips it, and its
     * progress is the panel module's to ask for. applyResponse arms only the direct grid's cards.
     */
    public function test_a_card_of_the_panel_is_never_peeked(): void {
        $this->assertStringContainsString(
            'attachCards(Array.from(root.querySelectorAll(SELECTORS.card)).filter((card) => !card.closest(SELECTORS.areapanel)));',
            $this->init_body()
        );
        $this->assertStringContainsString("areapanel: '[data-region=\"dim-panel\"]',", $this->source());
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
        $this->assertStringContainsString('syncFold();', $this->body('listen'), 'The back button moves it too.');
        $this->assertMatchesRegularExpression(
            '~if \(!isGrouped\(state\.sort\)\) \{\s*params\.delete\(PARAM\.open\);\s*params\.delete\(PARAM\.psort\);~',
            $this->body('buildUrl')
        );
        $this->assertStringContainsString('clear.href = withPanel(response.clearurl);', $this->body('applyResponse'));
    }

    /**
     * Nothing of the retired accordion is left in the module or in the build.
     *
     * The posters and the panel between their rows, which are categoryareapanel's (armed for this
     * shell by categorypanel), replaced it; its requests, its cache, its "Expand all", its
     * category-card rendering and the star (now categorystar's) left this module with it.
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
        $this->assertStringNotContainsString('dim-accordion', $this->build());
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
        $this->assertStringContainsString('paintFilterBadge(response);', $apply);
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
        $this->assertStringContainsString('onpick(event.detail.from, event.detail.to);', $append);
        $this->assertStringContainsString(
            'const mountCalendar = () => Listing.mountCalendar(root, state, setRange);',
            $this->source(),
            'And this module hands it setRange, which fetches the period picked.'
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
        $this->assertStringNotContainsString('dimenhanced', $this->build());
    }

    /**
     * A tree entry is a filter this module fetches, and it never leaves the page.
     *
     * The entries are real addresses, which is what makes the control work with no JavaScript;
     * with this module the press is intercepted and becomes a fetch with a history entry, exactly
     * as a chip is. Three things have to hold together and each fails silently on its own: the
     * press is intercepted at all (without it the browser follows the address and the page
     * reloads, losing every appended page and the scroll); the criterion goes onto the state and
     * into the rows request (without it the address, the marking and the filter badge all say the
     * listing is narrowed while the rows are the whole subtree); and the id comes off the link
     * rather than out of its address.
     *
     * Changes that must make it fail: deleting `event.preventDefault();` from the tree branch;
     * deleting `state.cat = cat;`; dropping `cat: state.cat` from the rows request.
     */
    public function test_a_tree_entry_is_a_filter_this_module_fetches_and_never_a_navigation(): void {
        $this->assertMatchesRegularExpression("~^    cat: 'cat',$~m", $this->shared(), 'The criterion is a parameter.');

        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~const branch = closestOf\(event, SELECTORS\.treelink\);\s*if \(branch\) \{'
                . '[^}]*?event\.preventDefault\(\);\s*onTree\(branch\);~s',
            $listen,
            'A tree entry is intercepted: without the preventDefault the browser follows the address.'
        );

        $tree = $this->body('onTree');
        $this->assertStringContainsString('const id = Number(link.dataset.categoryid);', $tree);
        $this->assertStringContainsString(
            'const cat = id === state.categoryid ? 0 : id;',
            $tree,
            "The page's own entry means all of it, which is how the filter is cleared."
        );
        $this->assertStringContainsString('state.cat = cat;', $tree);
        $this->assertStringContainsString('writeUrl(true);', $tree);
        $this->assertStringContainsString('markTree();', $tree);
        $this->assertStringContainsString('requestRows(0, false);', $tree);
        $this->assertStringNotContainsString(
            'window.location',
            $tree,
            'A tree entry is a fetch: the one control of this toolbar that navigates is the organisation.'
        );

        // Exactly one entry carries the marking, and it is a loop over every link rather than a swap.
        $mark = $this->body('markTree');
        $this->assertStringContainsString('root.querySelectorAll(SELECTORS.treelink)', $mark);
        $this->assertStringContainsString(
            'const on = state.cat === 0 ? id === state.categoryid : id === state.cat;',
            $mark
        );
        $this->assertStringContainsString("link.classList.toggle('dim-tree__link--on', on);", $mark);
        $this->assertStringContainsString("link.setAttribute('aria-current', 'true');", $mark);
        $this->assertStringContainsString("link.removeAttribute('aria-current');", $mark);
        $this->assertStringContainsString('markTree(root, state);', $this->body('paintControls'), 'The back button moves it too.');

        $this->assertStringContainsString('cat: state.cat,', $this->body('rowsArgs'), 'And the request carries it.');
        /* Nothing else of this module sends it. A panel's request is categorypanel's and asks about
           a category below the page's, under which the web service would drop this page's cat
           (categorypanel_source_test holds that half). */
        $this->assertSame(
            1,
            substr_count($this->source(), 'cat: state.cat'),
            'The tree\'s criterion travels with the rows request and with nothing else.'
        );
    }

    /**
     * The state a reader leaves a category in is remembered on every change, and the address then names it (round I).
     *
     * One function sends it, for a real account on a category page only; every control that changes
     * the remembered state calls it before it writes the address, because from then on the bare
     * address means that state to this reader and every address names the organisation; the
     * organisation that changes the shell waits for it (or a second) before loading the page that
     * reads it; and a page drawn from the remembered state writes its own address out in full.
     *
     * Changes that must make it fail: the guard on the account or the root dropped; a control that
     * changes the state no longer remembering it ("clear filters" above all); the address no longer
     * naming the organisation for a remembered category; the landing address left bare.
     */
    public function test_the_state_left_on_a_category_is_remembered_and_the_address_names_it(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const REMEMBER_METHOD = 'theme_dimensions_remember_category_state';",
            $source
        );
        $remember = $this->body('remember');
        $this->assertMatchesRegularExpression(
            '~^\s*if \(!state\.canpersist \|\| isRootPage\(\)\) \{\s*return Promise\.resolve\(\);\s*\}\s*'
                . 'state\.remembered = true;~',
            $remember,
            'Only a real account on a category page, and the addresses name the organisation from then on.'
        );
        $this->assertStringContainsString('methodname: REMEMBER_METHOD', $remember);
        $this->assertStringContainsString('if (key === rememberedsent) {', $remember, 'An unchanged state is not sent again.');

        foreach (['onClear', 'onChoose', 'setView', 'onSort'] as $name) {
            $body = $this->body($name);
            $at = strpos($body, 'remember(false);');
            $this->assertIsInt($at, $name . ' does not remember the state it leaves.');
            $this->assertGreaterThan(
                $at,
                strpos($body, 'writeUrl(true);'),
                $name . ' writes the address before it names the organisation.'
            );
        }
        $this->assertMatchesRegularExpression('~if \(was !== isGrouped\(sort\)\) \{\s*leave\(\);~', $this->body('onSort'));
        $leave = $this->body('leave');
        $this->assertMatchesRegularExpression(
            '~await Promise\.race\(\[\s*remember\(true\),[^;]*REMEMBER_WAIT\)\),\s*\]\);\s*'
                . 'window\.location\.assign\(buildUrl\(\)\.toString\(\)\);~',
            $leave,
            'The page that reads the state is loaded once it is remembered, or once the wait is over.'
        );
        $this->assertMatchesRegularExpression(
            '~readUrl\(\);\s*paintControls\(\);\s*syncFold\(\);\s*[^\n]*\n\s*remember\(false\);~',
            $this->body('listen'),
            'Back and forward remember the state they land on.'
        );

        $this->assertMatchesRegularExpression(
            "~if \(state\.remembered\) \{\s*params\.set\(PARAM\.sort, state\.sort === '' \? 'category' : state\.sort\);\s*\}~",
            $this->body('buildUrl'),
            'A remembered category names its organisation in every address, as criteria::sort_params() does then.'
        );
        $init = $this->init_body();
        $this->assertStringContainsString('remembered: isremembered,', $init);
        $this->assertMatchesRegularExpression(
            '~if \(state\.remembered\) \{\s*rememberedsent = JSON\.stringify\(rememberArgs\(\)\);\s*writeUrl\(false\);\s*\}\s*$~',
            $init,
            'A page drawn from the remembered state writes its address out in full, last.'
        );
        $this->assertStringContainsString('theme_dimensions_remember_category_state', $this->build());
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
            'And it is cleared first, like every other parameter this module owns.'
        );
        $this->assertStringContainsString(
            'return isGrouped(sort) || !Number.isInteger(cat) || cat <= 0 ? 0 : cat;',
            $this->body('catOf'),
            'Reading it back follows the same rule, so the back button cannot restore a criterion the page drops.'
        );
        $this->assertStringContainsString('state.cat = catOf(params, state.sort);', $this->body('readUrl'));
        $this->assertStringContainsString('cat: catOf(params, sort),', $this->init_body());
    }

    /**
     * The tree is a disclosure a phone opens, and a width that hides its summary reopens it.
     *
     * The box ships open, because that is the page a reader without this module gets at every
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
        $this->assertStringContainsString('armTreebox();', $this->init_body());
    }

    /**
     * The root arms this module for its search line, and there it asks for the term alone.
     *
     * The root searches as the reader types, as a category page does. Three things make that work:
     * init goes on at a root that draws a search line, the request carries the term and nothing
     * else there (the web service refuses anything else at the root), and an answer with no course
     * grid is still painted, posters and all. The state drops what the root does not read, on the
     * way in and on the way back.
     *
     * Changes that must make it fail: making init() return at the root whatever it draws.
     */
    public function test_the_root_is_armed_for_its_search_line_and_asks_for_the_term_alone(): void {
        $init = $this->init_body();
        $this->assertMatchesRegularExpression(
            '~if \(isRootPage\(\) && !root\.querySelector\(SELECTORS\.toolbar\)\) \{\s*return;~',
            $init,
            'A root with a search line is armed; only one with nothing to search is not.'
        );
        $this->assertStringNotContainsString('if (!state.categoryid) {', $init, 'The root is not turned away.');
        $this->assertStringContainsString('rootOnly();', $init);
        $this->assertStringContainsString('rootOnly(state);', $this->body('readUrl'), 'And on the way back through the history.');
        $this->assertStringContainsString('const isRootPage = () => state.categoryid === 0;', $this->source());

        $this->assertMatchesRegularExpression(
            '~^    if \(isRootPage\(\)\) \{\s*return \{categoryid: 0, query: state\.query\};~m',
            $this->body('rowsArgs'),
            'The root asks for its term and nothing else.'
        );
        $only = $this->body('rootOnly');
        $dropped = [
            'state.chip = \'\';',
            'state.fav = false;',
            'state.filters = {};',
            'state.sort = \'\';',
            'state.cat = 0;',
            'state.pagesize = 0;',
        ];
        foreach ($dropped as $line) {
            $this->assertStringContainsString($line, $only);
        }

        $apply = $this->body('applyResponse');
        $this->assertMatchesRegularExpression('~if \(grid\) \{\s*paintGrid\(grid, items, append\);\s*\}~', $apply);
        $this->assertDoesNotMatchRegularExpression(
            '~if \(!grid\) \{\s*return;~',
            $apply,
            'An answer with no course grid - the root\'s - is still painted.'
        );
        $this->assertStringContainsString('paintShells(response);', $apply, 'Its posters among it.');
    }

    /**
     * The committed build was made from the source that is committed beside it - exactly.
     *
     * Moodle serves amd/build and never amd/src, whatever cachejs says, so a source edit that
     * was never rebuilt is invisible in the browser and green in every other gate, every other
     * assertion in this file included.
     *
     * The check is the source map's sourcesContent, which grunt writes from the very bytes it
     * compiled, so it catches any unbuilt edit, not only one that changes a string literal.
     *
     * Changes that must make it fail: appending one comment line to amd/src/categoryvitrine.js
     * without rebuilding.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $this->assertSame(
            $this->source(),
            $this->built_from(),
            'amd/build is not this source: rebuild it with grunt amd for this plugin and '
                . 'commit the rebuilt .min.js and .map with the change.'
        );
        $this->assertSame(
            $this->shared(),
            $this->built_from('categorylisting'),
            'amd/build/categorylisting is not its source: rebuild it with grunt amd and commit the build.'
        );
        $this->assertStringContainsString('theme_dimensions/categorylisting', $this->build());

        // And the build names the theme's own services; the star's lives in categorystar, whose
        // build categorypanel_source_test checks.
        $build = $this->build();
        $this->assertStringContainsString('theme_dimensions_get_category_rows', $build);
        $this->assertStringContainsString('theme_dimensions_get_card_details', $build);
        $this->assertStringContainsString('theme_dimensions/categorystar', $build);
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $build);
    }

    /**
     * The row under the filter line is painted from the answer, and a chip drops its one criterion.
     *
     * Nothing of a chip is composed here: its label, its address, what it drops and the words that
     * name the press are the answer's (category_toolbar::chips()), painted with the markup of the
     * server's category_chip. The row shows while the answer says something narrows, and keeps
     * only its visually hidden count sentence at rest; "Clear all" follows the answer's clearall.
     * The state's status group is split between the scope and "More statuses" by SCOPE, the
     * server's own list. A pressed chip drops in the state exactly what its address drops, and the
     * eraser empties the search and asks again.
     *
     * Changes that must make it fail: the rest class or the count's visually-hidden toggled on
     * anything but the answer's hasfilters; the clear link shown on hasfilters again; a chip whose
     * address loses the open panel; the period dropping one bound only; the chip press not
     * intercepted; the split read from anything but SCOPE.
     */
    public function test_the_row_under_the_line_is_painted_from_the_answer(): void {
        $chips = $this->body('paintChips');
        $this->assertStringContainsString('const narrowed = Boolean(response.hasfilters);', $chips);
        $this->assertStringContainsString("row.classList.toggle('dim-line__chips--rest', !narrowed);", $chips);
        $this->assertStringContainsString("count.classList.toggle('visually-hidden', !narrowed);", $chips);
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
        $this->assertStringContainsString('const isScope = (option) => SCOPE.indexOf(String(option.value)) >= 0;', $status);
        $this->assertStringContainsString('more.hidden = others.length === 0;', $status);

        $apply = $this->body('applyResponse');
        $this->assertStringContainsString('paintChips(response);', $apply);
        $this->assertStringNotContainsString('response.hasfilters', $apply, 'The clear link\'s visibility is paintChips\'.');
        $listen = $this->body('listen');
        $this->assertMatchesRegularExpression(
            '~const chip = closestOf\(event, SELECTORS\.chipremove\);\s*if \(chip && onChipRemove\(chip\)\) \{\s*'
                . '(?://[^\n]*\s*)?event\.preventDefault\(\);~',
            $listen,
            'A chip is a fetch with JavaScript, and its link otherwise.'
        );
        $this->assertMatchesRegularExpression(
            '~if \(closestOf\(event, SELECTORS\.eraser\)\) \{\s*event\.preventDefault\(\);\s*onErase\(\)~',
            $listen
        );
        $remove = $this->body('onChipRemove');
        $this->assertMatchesRegularExpression('~remember\(false\);\s*writeUrl\(true\);\s*requestRows\(0, false\);~', $remove);
        $erase = $this->body('onErase');
        $this->assertMatchesRegularExpression("~search\.value = '';\s*search\.focus\(\{preventScroll: true\}\);~", $erase);
        $this->assertStringContainsString("await onSearch('');", $erase);
        $this->assertStringContainsString("eraser.hidden = search.value === '';", $this->body('paintEraser'));
        $this->assertStringContainsString('paintEraser(root);', $this->body('paintControls'));
    }
}
