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
 * Theme Dimensions - Tests for the invariants of an account's listing client
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of an account's listing client by reading its source: js/esm/src/listing/account.ts, the shell
 * over the shared store, and amd/src/listingaccount.js, its entry.
 *
 * This shell replaced the account's two AMD clients: what only an
 * account has (the remembered state, the view preference, the progress, the panel's request) is the shell's,
 * and what both shells do (the tickets, the appended page, the tree, the chips, clear) is the store's, read here for
 * the gates that were written over the account's client. Their behaviour is Jest's (js/esm/tests/listing); these are
 * the PHPUnit side the mutation sweep can redden, each scoped to one function through body().
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class listing_account_source_test extends \basic_testcase {
    /** @var string The account's shell. */
    private const SHELL = 'js/esm/src/listing/account.ts';

    /** @var string The shared store under it. */
    private const STORE = 'js/esm/src/listing/store.ts';

    /** @var string The AMD entry course_renderer.php arms. */
    private const ENTRY = 'amd/src/listingaccount.js';

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
     * The body of one top-level arrow function, a signature on several lines included.
     *
     * @param string $name The function's name.
     * @param string $path The source's path under the theme root: the shell by default.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $path = self::SHELL): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~')
            . ' = (?:[^\n]*\(\n(?:    [^\n]*\n)*?\)[^\n]*|[^\n]*)\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->read($path), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in {$path}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The extractor returns one function and not the module around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $settle = $this->body('settle');
        $drain = $this->body('drainPending');
        $this->assertStringContainsString('settled.add(id);', $settle);
        $this->assertStringNotContainsString('BATCH_SIZE', $settle, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('BATCH_SIZE', $drain);
        $this->assertStringContainsString('const ticket = ++seq;', $this->body('requestRows', self::STORE));
    }

    /**
     * The entry starts the shell and loads nothing else; the shell arms the star, first.
     *
     * The star (./star) is armed whatever the page draws: the root's directory has no listing to arm, and its
     * panels' cards have stars.
     *
     * Changes that must make it fail: the star no longer armed (gate js_account_arms_star); the entry loading
     * another module (gate js_vitrine_listing_through_esm); the entry or its build not made from this source.
     */
    public function test_the_entry_starts_the_shell_which_arms_the_star(): void {
        $entry = $this->read(self::ENTRY);
        $this->assertStringContainsString(
            "\nimport {start} from 'core/esm!@moodle/lms/theme_dimensions/listing/account';\n",
            $entry
        );
        $this->assertStringContainsString("\nexport const init = () => start();\n", $entry);
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $entry, $imports);
        $this->assertCount(1, $imports[1], 'The shell, nothing else.');
        $this->assertMatchesRegularExpression(
            '~^    // [^\n]*\n    armStar\(\);\n    const found = document\.querySelector<HTMLElement>\(SELECTORS\.root\);~',
            $this->body('start'),
            'The star first, before the listing is looked for.'
        );
        $map = json_decode($this->read('amd/build/listingaccount.min.js.map'), true);
        $this->assertSame($entry, $map['sourcesContent'][0] ?? null, 'amd/build is not this source: rebuild it.');
        $this->assertStringContainsString(
            '["exports","core/esm!@moodle/lms/theme_dimensions/listing/account"]',
            $this->read('amd/build/listingaccount.min.js')
        );
    }

    /**
     * The shell asks the logged-in services and writes the listing's mark through the store, and nothing else does.
     *
     * Every failure is core's exception dialogue, loaded through the bridge, a calendar that could not mount included;
     * the progress of a panel's cards is decoration and fails in silence.
     *
     * Changes that must make it fail: the calendar's failure swallowed on the account's shell (gate
     * js_calendar_failure_told).
     */
    public function test_the_shell_is_the_logged_in_one_and_reports_through_core(): void {
        $shell = $this->read(self::SHELL);
        foreach (['get_category_rows', 'get_card_details', 'remember_category_state'] as $service) {
            $this->assertStringContainsString("_METHOD = 'theme_dimensions_{$service}';", $shell);
        }
        $this->assertStringContainsString("const BODY_CLASS = 'dim-js';", $shell);
        $this->assertStringContainsString("const SHELL: Shell = {\n    mark: BODY_CLASS,\n", $shell);
        $this->assertStringContainsString("import {current, init} from './store';", $shell);
        $this->assertStringContainsString(
            "const Notification = await load('core/notification');\n    await Notification.exception(error);",
            $this->body('exception')
        );
        $this->assertStringContainsString('answer.catch(exception);', $this->body('rows'), 'Every failed page is shown.');
        $this->assertStringContainsString('exception(error);', $this->body('drainPending'));
        // A calendar that could not mount is the dialogue too, as it was with the AMD clients; the store asks the shell.
        $this->assertStringContainsString("\n    unmounted: exception,\n", $shell);
        $this->assertStringContainsString(
            'Listing.mountCalendar(root, state, setRange).catch(shell.unmounted);',
            $this->body('init', self::STORE)
        );
        $this->assertMatchesRegularExpression(
            '~\} catch \(error\) \{\s*// [^\n]*\n\s*\}$~',
            $this->body('paintPanelProgress'),
            'A panel\'s progress fails in silence.'
        );
    }

    /**
     * A settled id is unobserved, and settling is what a finished batch does whatever it answered.
     *
     * Changes that must make it fail: deleting `observer.unobserve(card);` from settle() (gate js_unobserve_on_settle);
     * leaving a settled id in the pending set (gate js_settle_leaves_pending); moving `settle(batch)` into the `try`.
     */
    public function test_a_settled_course_is_unobserved_and_every_finished_batch_settles(): void {
        $settle = $this->body('settle');
        $this->assertMatchesRegularExpression('~observer\.unobserve\(card\);~', $settle);
        $this->assertStringContainsString('settled.add(id);', $settle);
        $this->assertStringContainsString('pending.delete(id);', $settle);

        $drain = $this->body('drainPending');
        $found = preg_match('~\}\s*finally\s*\{(.*?)\n    \}~s', $drain, $matches);
        $this->assertSame(1, $found, 'The details drain has no finally block, so a failed batch settles nothing.');
        $this->assertStringContainsString('settle(batch);', $matches[1]);
        $this->assertStringContainsString("methodname: DETAILS_METHOD,\n            args: {courseids: batch},", $drain);
    }

    /**
     * The details batch is capped at 24 ids and drains on a fixed 100 ms interval.
     *
     * 24 is {@see enrichment::MAX_COURSES}: get_card_details refuses a larger batch. The interval is fixed because a
     * timeout reset per id sends nothing at all during a continuous scroll.
     */
    public function test_the_details_batch_is_capped_and_drains_on_a_fixed_interval(): void {
        $shell = $this->read(self::SHELL);
        $this->assertSame(enrichment::MAX_COURSES, 24, 'The server\'s cap.');
        $this->assertMatchesRegularExpression('~^const BATCH_SIZE = 24;$~m', $shell);
        $this->assertMatchesRegularExpression('~^const BATCH_INTERVAL = 100;$~m', $shell);
        $this->assertStringContainsString('.slice(0, BATCH_SIZE)', $this->body('drainPending'));
        $this->assertStringContainsString('window.setInterval(drainPending, BATCH_INTERVAL);', $this->body('startDraining'));
    }

    /**
     * The shell holds no card disclosure: "More information" is a link the course dialogue's module intercepts.
     *
     * The card's details panel, its one-open-at-a-time rule and its Escape are gone; what the shell still
     * hears on its own is the automatic opening's note.
     */
    public function test_the_shell_holds_no_card_disclosure(): void {
        $source = $this->read(self::SHELL);
        foreach (['dim-card-details', 'dim-card-panel', 'closeOthers', "'toggle'", 'Escape', 'HTMLDetailsElement'] as $gone) {
            $this->assertStringNotContainsString($gone, $source, "The account's shell still names {$gone}.");
        }
        $this->assertStringContainsString('document.addEventListener(LISTING.autoopened, paintAutoOpened);', $this->body('listen'));
    }

    /**
     * A replaced grid gives its cards back to the observer before it discards them.
     *
     * Changes that must make it fail: deleting the unobserve from discard() (gate js_replace_unobserves); the store
     * painting the grid without the shell's discard; the shell handing none.
     */
    public function test_a_replaced_grid_unobserves_the_cards_it_discards(): void {
        $this->assertStringContainsString(
            'Listing.paintGrid(root, state, grid, items, append, shell.discard);',
            $this->body('applyResponse', self::STORE)
        );
        $this->assertStringContainsString("\n    discard: discard,\n", $this->read(self::SHELL));
        $this->assertStringContainsString(
            'grid.querySelectorAll<HTMLElement>(SELECTORS.card).forEach((card) => discard(card));',
            $this->body('paintGrid', 'js/esm/src/listing/paint.ts')
        );
        $discard = $this->body('discard');
        $this->assertMatchesRegularExpression('~if \(observer\) \{\s*observer\.unobserve\(card\);\s*\}~', $discard);
        $this->assertStringContainsString('pending.delete(courseid);', $discard);
    }

    /**
     * Every card an answer draws is armed, an appended page's as a replaced one's, and a card of the panel never is.
     *
     * Changes that must make it fail: arming the panel's cards at start (gate js_mini_no_peek); the store no longer
     * handing the shell the cards it drew.
     */
    public function test_every_card_drawn_is_armed_and_a_card_of_the_panel_never_is(): void {
        $this->assertStringContainsString(
            'attachCards(Array.from(root.querySelectorAll<HTMLElement>(SELECTORS.card)).filter((card) => '
                . '!card.closest(HOOKS.areapanel)));',
            $this->body('start')
        );
        $this->assertStringContainsString("areapanel: '[data-region=\"dim-panel\"]',", $this->read(self::SHELL));
        $this->assertStringContainsString('attachCards(cards.filter(', $this->body('painted'));
        $this->assertMatchesRegularExpression(
            '~Listing\.paintFavourites\(root, response\);\s*shell\.painted\(items\);\s*Listing\.paintPager~',
            $this->body('applyResponse', self::STORE)
        );
        $this->assertStringContainsString(
            "card.dataset.state !== '" . classifier::STATE_ENROLLED . "'",
            $this->body('watchCard'),
            'The shell watches the state the classifier produces.'
        );
    }

    /**
     * An appended page is state of the document and is never written into the address bar.
     *
     * Changes that must make it fail: moving `state.page = lastpage;` out of the `!append` branch (gate
     * js_appended_page_not_in_address); asking for `state.page + 1` instead of `lastpage + 1`.
     */
    public function test_the_appended_page_is_not_the_page_the_address_names(): void {
        $apply = $this->body('applyResponse', self::STORE);
        $this->assertMatchesRegularExpression(
            '~lastpage = Number\(response\.page\);\n    if \(!append\) \{\n        state\.page = lastpage;\n    \}~',
            $apply
        );
        $this->assertStringContainsString('requestRows(lastpage + 1, true);', $this->body('listen', self::STORE));
    }

    /**
     * An answer overtaken while its cards were rendering paints nothing and tells the posters nothing.
     *
     * Changes that must make it fail: the latest-request check only before the render (gate js_rows_ticket_after_render).
     */
    public function test_an_answer_overtaken_while_rendering_paints_nothing(): void {
        $apply = $this->body('applyResponse', self::STORE);
        $render = strpos($apply, 'const items = await Listing.renderCards(response.cards || []);');
        $check = strpos($apply, "if (ticket !== seq) {\n        return;\n    }");
        $state = strpos($apply, 'lastpage = Number(response.page);');
        $areas = strpos($apply, 'paintAreas(response);');
        $this->assertIsInt($render);
        $this->assertIsInt($check, 'The answer checks it is still the latest once its cards are rendered.');
        $this->assertTrue($render < $check && $check < $state && $state < $areas, 'Between the render and every write.');
        $request = $this->body('requestRows', self::STORE);
        $this->assertStringContainsString('await applyResponse(response, append, ticket);', $request);
    }

    /**
     * Every rows request carries the page size and the favourites the address names, and the root its term alone.
     *
     * Changes that must make it fail: dropping perpage (gate js_rows_carry_page_size) or fav (gate js_rows_carry_fav)
     * from rowsArgs(); the root asking for more than its term.
     */
    public function test_every_rows_request_carries_the_addresss_criteria(): void {
        $args = $this->body('rowsArgs');
        $this->assertMatchesRegularExpression(
            '~\n    if \(isRootPage\(\)\) \{\n        return \{categoryid: 0, query: state\.query\};\n    \}~',
            $args,
            'The root asks for its term and nothing else.'
        );
        $this->assertStringContainsString("\n    normalise: Listing.rootOnly,\n", $this->read(self::SHELL));
        $only = $this->body('rootOnly', 'js/esm/src/listing/address.ts');
        $dropped = ["state.chip = '';", 'state.fav = false;', 'state.filters = {};', "state.sort = '';", 'state.cat = 0;'];
        foreach ($dropped as $line) {
            $this->assertStringContainsString($line, $only);
        }
        $criteria = [
            'categoryid: state.categoryid,',
            'page: page,',
            'query: state.query,',
            "chip: state.chip,\n        fav: state.fav,\n        perpage: state.pagesize,",
            'filters: Listing.filterArgs(state),',
            'sort: state.sort,',
            'datefrom: state.datefrom,',
            'dateto: state.dateto,',
            'cat: state.cat,',
        ];
        foreach ($criteria as $argument) {
            $this->assertStringContainsString($argument, $args);
        }
        $this->assertStringContainsString('args: rowsArgs(page),', $this->body('rows'));
        $this->assertStringContainsString('const response = await shell.rows(page);', $this->body('requestRows', self::STORE));
    }

    /**
     * The favourites toggle is intercepted and fetched with a history entry; the address carries it.
     *
     * Changes that must make it fail: the press let through to its submit (gate js_fav_toggle_intercepted); the toggle
     * not flipped or not applied in onFav().
     */
    public function test_the_favourites_toggle_is_a_fetch_with_a_history_entry(): void {
        $this->assertMatchesRegularExpression(
            '~if \(Listing\.closestOf\(event, SELECTORS\.fav\)\) \{\s*// [^\n]*\s*event\.preventDefault\(\);\s*onFav\(\);~',
            $this->body('listen', self::STORE)
        );
        $this->assertStringContainsString("fav: '[data-action=\"dim-fav\"]',", $this->read('js/esm/src/listing/events.ts'));
        $this->assertSame(
            "    state.fav = !state.fav;\n    Listing.paintFavToggle(root, state.fav);\n    apply('filter');",
            $this->body('onFav', self::STORE)
        );
        $this->assertMatchesRegularExpression(
            "~if \\(state\\.fav\\) \\{\\s*params\\.set\\(PARAM\\.fav, '1'\\);\\s*\\}~",
            $this->body('buildUrl', 'js/esm/src/listing/address.ts')
        );
        $read = $this->body('readUrl', 'js/esm/src/listing/address.ts');
        $this->assertStringContainsString('state.chip = chipOf(params);', $read);
        $this->assertStringContainsString('state.fav = favOf(params);', $read);
        // An older address's favourites chip is read as the toggle with no chip, as the server reads it.
        $this->assertStringContainsString(
            "export const favOf = (params: URLSearchParams): boolean =>\n"
                . "    params.get(PARAM.fav) === '1' || params.get(PARAM.chip) === LEGACY_FAVOURITES;",
            $this->read('js/esm/src/listing/address.ts')
        );
        $init = $this->body('init', self::STORE);
        $this->assertStringContainsString('fav: payload.fav === true,', $init);
        $this->assertStringContainsString("chip: typeof payload.chip === 'string' ? payload.chip : '',", $init);
    }

    /**
     * A tree entry is intercepted and fetched: the one control of the toolbar that is a filter and no navigation.
     *
     * Changes that must make it fail: deleting `event.preventDefault();` from the tree branch (gate
     * js_tree_click_fetches); deleting `state.cat = cat;`.
     */
    public function test_a_tree_entry_is_a_filter_the_store_fetches_and_never_a_navigation(): void {
        $this->assertMatchesRegularExpression(
            '~const branch = Listing\.closestOf\(event, SELECTORS\.treelink\);\s*if \(branch\) \{\s*'
                . 'event\.preventDefault\(\);\s*onTree\(branch\);~',
            $this->body('listen', self::STORE)
        );
        $tree = $this->body('onTree', self::STORE);
        $this->assertStringContainsString('const cat = id === state.categoryid ? 0 : id;', $tree);
        $this->assertStringContainsString('state.cat = cat;', $tree);
        $this->assertStringContainsString("apply('tree');", $tree);
        $this->assertStringNotContainsString('window.location', $tree);
        $this->assertStringContainsString('Listing.markTree(root, state);', $tree);
        $this->assertSame(1, substr_count($this->read(self::SHELL), 'cat: state.cat'), 'The rows request carries it, alone.');
    }

    /**
     * A chip and "Clear filters" are fetched in place, and the focus goes to the search box.
     *
     * Changes that must make it fail: the focus left where Clear was pressed (gate js_clear_focuses_search); a chip
     * left to its link (gate js_chip_intercepted); the term kept for a suggestion's press (gate js_vitrine_drop_term).
     */
    public function test_a_chip_and_clear_are_fetched_in_place_and_focus_the_search(): void {
        $listen = $this->body('listen', self::STORE);
        $this->assertMatchesRegularExpression(
            '~const clear = Listing\.closestOf\(event, SELECTORS\.clearaction\);\s*if \(clear\) \{\s*'
                . 'event\.preventDefault\(\);\s*onClear\(\);~',
            $listen
        );
        $this->assertMatchesRegularExpression(
            '~const chip = Listing\.closestOf\(event, SELECTORS\.chipremove\);\s*if \(chip && onChipRemove\(chip\)\) \{\s*'
                . 'event\.preventDefault\(\);~',
            $listen
        );
        $clear = $this->body('onClear', self::STORE);
        $steps = ["state.query = '';", "state.chip = '';", 'state.fav = false;', 'state.filters = {};', 'state.cat = 0;'];
        foreach ($steps as $step) {
            $this->assertStringContainsString($step, $clear);
        }
        $this->assertMatchesRegularExpression("~apply\\('filter'\\);\\s*focusSearch\\(\\);$~", $clear);
        $this->assertMatchesRegularExpression(
            '~const search = Listing\.focusTarget\(root\);\s*if \(search\) \{\s*search\.focus\(\{preventScroll: true\}\);~',
            $this->body('focusSearch', self::STORE)
        );
        $drop = $this->body('dropTerm', self::STORE);
        $this->assertMatchesRegularExpression("~^    cancelSearch\\(\\);\\n    state\\.query = '';~", $drop);
    }

    /**
     * The organisation that changes the shell loads the page, once the state it leaves is remembered or a second passed.
     */
    public function test_the_organisation_is_a_navigation_between_shells_and_a_fetch_inside_one(): void {
        $sort = $this->body('onSort', self::STORE);
        $this->assertMatchesRegularExpression(
            '~if \(was !== Listing\.isGrouped\(sort\)\) \{\s*shell\.leave\(Listing\.buildUrl\(state\)\);\s*return;\s*\}\s*'
                . "apply\\('filter'\\);~",
            $sort
        );
        $this->assertMatchesRegularExpression(
            '~await Promise\.race\(\[\s*remember\(true\),[^;]*REMEMBER_WAIT\)\),\s*\]\);\s*'
                . 'redirect\(Listing\.buildUrl\(current\(\)\)\.toString\(\)\);~',
            $this->body('leave'),
            'The address is built after the state is remembered: it names the organisation from then on.'
        );
        $address = $this->read('js/esm/src/listing/address.ts');
        $this->assertStringContainsString("export const GROUPED_SORTS = ['', 'category'];", $address);
        $this->assertMatchesRegularExpression(
            '~if \(name === Listing\.PARAM\.sort\) \{\s*onSort\(input\.value\);\s*return;~',
            $this->body('onChoose', self::STORE),
            'The organisation chips are radios of the same delegated change handler.'
        );
    }

    /**
     * The state a reader leaves a category in is remembered on every change, and the address then names it.
     *
     * Changes that must make it fail: the guard on the account or the root dropped (gate js_remember_guard); "clear
     * filters" no longer remembering (gate js_clear_remembers); the change told after the write (gate
     * js_changed_before_write); the landing address pushed, a history entry Back stops on (gate
     * js_history_start_replaces).
     */
    public function test_the_state_left_on_a_category_is_remembered_and_the_address_names_it(): void {
        $remember = $this->body('remember');
        $this->assertMatchesRegularExpression(
            '~^    if \(!canpersist \|\| isRootPage\(\)\) \{\s*return Promise\.resolve\(\);\s*\}\s*'
                . 'current\(\)\.remembered = true;~',
            $remember
        );
        $this->assertStringContainsString('methodname: REMEMBER_METHOD', $remember);
        $this->assertStringContainsString('if (key === rememberedsent) {', $remember, 'An unchanged state is not sent again.');
        $this->assertStringContainsString("const UNREMEMBERED = ['term', 'tree', 'period'];", $this->read(self::SHELL));
        $this->assertMatchesRegularExpression(
            '~^    if \(UNREMEMBERED\.indexOf\(kind\) >= 0\) \{\s*return;\s*\}\s*remember\(false\);~',
            $this->body('changed')
        );

        $apply = $this->body('apply', self::STORE);
        $this->assertMatchesRegularExpression('~shell\.changed\(kind\);\s*Listing\.writeUrl\(state, true\);~', $apply);
        $this->assertMatchesRegularExpression(
            "~shell\\.changed\\('view'\\);\\s*Listing\\.writeUrl\\(state, true\\);~",
            $this->body('setView', self::STORE)
        );
        $this->assertMatchesRegularExpression(
            "~Listing\\.paintControls\\(root, state\\);\\s*apply\\('filter'\\);\\s*focusSearch\\(\\);$~",
            $this->body('onClear', self::STORE),
            'Clearing the filters clears the remembered ones too.'
        );
        $this->assertStringContainsString("shell.changed('history');", $this->body('onPopState', self::STORE));

        $init = $this->body('init', self::STORE);
        $this->assertStringContainsString('const remembered = payload.remembered === true;', $init);
        $this->assertStringContainsString(
            "const sort = remembered && typeof payload.sort === 'string' ? payload.sort : Listing.sortOf(params, defaultsort);",
            $init
        );
        $this->assertMatchesRegularExpression(
            '~if \(current\(\)\.remembered\) \{\s*rememberedsent = JSON\.stringify\(rememberArgs\(\)\);\s*'
                . 'Listing\.writeUrl\(current\(\), false\);\s*\}$~',
            $this->body('start'),
            'A page drawn from the remembered state writes its address out in full, replacing, last.'
        );
        $this->assertMatchesRegularExpression(
            "~if \\(state\\.remembered\\) \\{\\s*"
                . "params\\.set\\(PARAM\\.sort, state\\.sort === '' \\? 'category' : state\\.sort\\);~",
            $this->body('buildUrl', 'js/esm/src/listing/address.ts')
        );
    }

    /**
     * The view is the person's preference for a real account only, written after a quiet, through core's own route.
     *
     * Core refuses the guest's preference and stores none for a visitor; canpersist is the server's word for it.
     *
     * Changes that must make it fail: writing the preference whatever the account (gate
     * js_view_preference_real_account).
     */
    public function test_the_view_preference_is_written_for_a_real_account_only(): void {
        $changed = $this->body('changed');
        $this->assertMatchesRegularExpression("~if \\(kind !== 'view' \\|\\| !canpersist\\) \\{\\s*return;\\s*\\}~", $changed);
        // Core's own route for one preference, as core_user/repository's setUserPreference() spells it.
        $this->assertMatchesRegularExpression(
            '~Fetch\.performPost\(\'core_user\', .current/preferences/\$\{VIEW_PREFERENCE\}., \{body: \{value: view\}\}\)'
                . '\.catch\(exception\);~',
            $changed
        );
        $this->assertStringContainsString('}, VIEW_DEBOUNCE);', $changed);
        $this->assertStringContainsString("const VIEW_PREFERENCE = 'theme_dimensions_categoryview';", $this->read(self::SHELL));
        $this->assertStringContainsString('canpersist = Boolean(payload.canpersist);', $this->body('start'));
    }

    /**
     * An answer tells the posters' component and writes none of the posters; a failure tells them it is not coming.
     *
     * The component holds the posters' numbers from the pending event until the answer's; a failed request sends none,
     * so the shell tells it, for the latest replacing request on a page with areas, beside core's dialogue.
     *
     * Changes that must make it fail: a poster written from the store again (gate js_areas_shells_untouched); the
     * shell handed the answer after the posters' component, which then opens an area with no note (gate
     * js_answered_before_criteria); the failure not told (gate js_failed_tells_posters).
     */
    public function test_an_answer_tells_the_posters_and_writes_none_of_them(): void {
        $areas = $this->body('paintAreas', self::STORE);
        $this->assertStringNotContainsString('poster.', $areas);
        $this->assertStringNotContainsString('SELECTORS.area', $areas);
        // The shell first: the component opens an area inside the dispatch, and the note is the answer's sentence.
        $this->assertLessThan(
            (int) strpos($areas, 'new CustomEvent(LISTING.criteria'),
            (int) strpos($areas, 'shell.answered(response);'),
            'The shell is handed the answer before the posters\' component hears it.'
        );
        $this->assertStringContainsString('shell.answered(response);', $areas);
        // The posters hold their numbers while a request is out, told whether it narrows: a period by either bound.
        $this->assertMatchesRegularExpression(
            "~export const narrows = \\(state: ListingState\\): boolean => state\\.query !== ''\\s*\\|\\| state\\.chip !== ''\\s*"
                . "\\|\\| Boolean\\(state\\.fav\\)\\s*\\|\\| Object\\.keys\\(state\\.filters\\)\\.length > 0\\s*"
                . "\\|\\| state\\.datefrom !== ''\\s*\\|\\| state\\.dateto !== '';~",
            $this->read('js/esm/src/listing/address.ts')
        );
        $apply = $this->body('applyResponse', self::STORE);
        $this->assertLessThan(
            (int) strpos($apply, 'paintAreas(response);'),
            (int) strpos($apply, 'Listing.paintCount(root, response);'),
            'The count line is painted before the posters are told.'
        );
        $this->assertMatchesRegularExpression(
            "~autoopenlabel = typeof word\\.autoopenlabel === 'string' \\? word\\.autoopenlabel : '';~",
            $this->body('answered')
        );
        $this->assertMatchesRegularExpression(
            '~if \(!append && root\.querySelector\(SELECTORS\.area\)\) \{\s*'
                . 'document\.dispatchEvent\(new CustomEvent\(LISTING\.failed\)\);\s*\}~',
            $this->body('failed')
        );
        $this->assertMatchesRegularExpression(
            '~\} catch \(error\) \{\s*if \(ticket === seq\) \{\s*shell\.failed\(page, append, error\);\s*\}~',
            $this->body('requestRows', self::STORE),
            'Only the latest request\'s failure is the shell\'s to tell.'
        );
        $note = $this->body('paintAutoOpened');
        $this->assertStringContainsString('text.textContent = autoopenlabel;', $note);
        $this->assertStringContainsString('note.hidden = false;', $note);
        $this->assertStringContainsString('document.addEventListener(LISTING.autoopened, paintAutoOpened);', $this->body('listen'));
    }

    /**
     * A panel asks for the area's whole subtree, eight courses in the panel's own order, the page's criteria.
     *
     * The request names no `direct` (the service refuses an unknown key), never the tree's category (the service drops
     * one not below the area), and reads the period as the calendar reads one, so a hand-edited pair given backwards
     * never reaches a service that refuses it. At the root it carries the term alone.
     *
     * Changes that must make it fail: a direct key sent (gate js_panel_sends_no_direct); the period read raw (gate
     * js_panel_period_read); the root's term not sent (gate js_root_panel_carries_query); no progress painter handed
     * to the shared mount (gate js_areas_progress_painted).
     */
    public function test_a_panel_asks_for_the_subtree_eight_courses_and_the_pages_criteria(): void {
        $shell = $this->read(self::SHELL);
        $this->assertMatchesRegularExpression('~^const PANEL_SIZE = 8;$~m', $shell);
        $args = $this->body('panelArgs');
        $this->assertDoesNotMatchRegularExpression('~\bdirect\b~', $args);
        $this->assertDoesNotMatchRegularExpression('~\bcat:~', $args, 'The tree\'s category never travels with a panel.');
        $criteria = [
            'categoryid: id,',
            'page: 0,',
            'perpage: PANEL_SIZE,',
            'sort: psort === PSORT_NAME ? SORT.name : SORT.recent,',
            "query: params.get(Listing.PARAM.query) || '',",
            "chip: chip === LEGACY_FAVOURITES ? '' : chip,",
            "fav: params.get(Listing.PARAM.fav) === '1' || chip === LEGACY_FAVOURITES,",
            'filters: filters,',
            'datefrom: range.from,',
            'dateto: range.to,',
            'withcategories: true,',
        ];
        foreach ($criteria as $argument) {
            $this->assertStringContainsString($argument, $args);
        }
        $this->assertStringContainsString(
            'const range = calendarPeriod(params.get(Listing.PARAM.from), params.get(Listing.PARAM.to));',
            $args
        );
        $this->assertStringContainsString("import {period as calendarPeriod} from '../daterange/calendar';", $shell);
        $this->assertMatchesRegularExpression(
            '~if \(isRootPage\(\)\) \{\s*params = new URLSearchParams\(\);\s*'
                . 'if \(address\.has\(Listing\.PARAM\.query\)\) \{\s*params\.set\(Listing\.PARAM\.query,~',
            $args,
            'The root\'s panel carries the term, and a fresh set of parameters holds nothing else.'
        );
        $this->assertStringContainsString('args: panelArgs(id, psort),', $this->body('ask'));
        $this->assertStringContainsString(
            "    areas: {\n        props: {},\n        client: {\n"
                . "            ask: ask,\n            painted: paintPanelProgress,\n",
            $shell
        );
        $this->assertStringContainsString(
            'Listing.mountAreas(areas, shell.areas.props, shell.areas.client);',
            $this->body('init', self::STORE)
        );
    }

    /**
     * A panel's enrolled courses get their progress in one batch of at most eight, onto the cards the rows drew, through
     * the flat list's own painter: one painting path for both (the progress row and the completed state).
     */
    public function test_a_panels_progress_is_one_batch_of_eight(): void {
        $paint = $this->body('paintPanelProgress');
        $this->assertStringContainsString("filter((card) => card.dataset.state === 'enrolled')", $paint);
        $this->assertStringContainsString('args: {courseids: ids.slice(0, PANEL_SIZE)},', $paint);
        $this->assertStringContainsString('paintDetail(card, detail);', $paint);
        $this->assertStringNotContainsString('--dim-progress', $paint, 'The panel paints nothing of its own.');
        $this->assertStringContainsString('paintDetail(card, detail);', $this->body('paintAnswer'));

        $detail = $this->body('paintDetail');
        $this->assertStringContainsString("row.style.setProperty('--dim-progress', detail.progress + '%');", $detail);
        $this->assertStringContainsString('row.hidden = false;', $detail);
        // A completed course never shows the bar: the seal returns before the row is painted.
        $this->assertMatchesRegularExpression(
            '~if \(detail\.completed && card\.dataset\.done\) \{\s*markDone\(card\);\s*return;\s*\}~',
            $detail
        );
        $this->assertStringContainsString('row.hidden = true;', $this->body('markDone'));
    }
}
