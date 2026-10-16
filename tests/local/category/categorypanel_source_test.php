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
 * Theme Dimensions - Tests for the invariants of the panel's and the star's client modules
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of the panel's, the star's and the marker's client modules by reading their source.
 *
 * categoryareapanel holds the panel itself - opening, placing, rendering, the address, the keyboard -
 * and both clients arm it; categorypanel holds what the logged-in client adds to it: the listing's
 * web service a panel is asked through, the progress, the star and the automatic opening. The
 * visitor's half is categorypublic_source_test's.
 *
 * Plugin CI runs no JavaScript unit tests, so the properties whose loss costs a reader something are
 * pinned the way amd_source_test pins the listing's module, each assertion scoped to the function it
 * is about: a grep over the whole file stays green with the line moved somewhere it never runs. The
 * build-currency assertions are the ones that read what a browser is actually served.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categorypanel_source_test extends \basic_testcase {
    /** @var string The panel module both clients arm, which holds every function categorypanel does not. */
    private const SHARED = 'categoryareapanel';

    /**
     * Absolute path to the theme root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 3);
    }

    /**
     * One module's source.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function source(string $module = 'categorypanel'): string {
        $path = $this->plugin_root() . '/amd/src/' . $module . '.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * Both panel sources, for an assertion about what neither of them may contain.
     *
     * A line moved from the logged-in client into the shared module is still a line of the panel,
     * so "never in the panel" reads both files: read in one, it would pass by the move alone.
     *
     * @return string
     */
    private function sources(): string {
        return $this->source() . "\n" . $this->source(self::SHARED);
    }

    /**
     * One module as the browser actually receives it.
     *
     * @param string $module The module's name.
     * @return string
     */
    private function build(string $module): string {
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
    private function built_from(string $module): string {
        $path = $this->plugin_root() . '/amd/build/' . $module . '.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * The body of one top-level arrow function of a module.
     *
     * Every function of these modules is a top-level `const <name> = (…) => {` (or `export const`)
     * closed by a `};` in the first column, which is what makes the extraction unambiguous. The
     * posters' painters are categoryposters', which the visitor's module paints with too. A function
     * of the panel is looked for in categorypanel and then in the shared panel module: the two
     * files give their functions distinct names, so the body found is the one that runs.
     *
     * @param string $name The function's name.
     * @param string $module The module's name.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name, string $module = 'categorypanel'): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source($module), $matches);
        if ($found !== 1 && $module === 'categorypanel') {
            $found = preg_match($pattern, $this->source(self::SHARED), $matches);
        }
        $this->assertSame(1, $found, "No top-level function named {$name} in {$module}.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The body of one module's exported init().
     *
     * @param string $module The module's name.
     * @return string
     */
    private function init_body(string $module): string {
        $found = preg_match('~\nexport const init = [^\n]*\{\n(.*?)\n\};~s', $this->source($module), $matches);
        $this->assertSame(1, $found, "{$module} exports no init().");
        return $matches[1];
    }

    /**
     * The extractor really extracts one function and not the file around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $args = $this->body('panelArgs');
        $write = $this->body('writeAddress');
        $this->assertStringContainsString('withcategories', $args);
        $this->assertStringNotContainsString('withcategories', $write, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('replaceState', $write);
        $this->assertStringNotContainsString('replaceState', $args, 'The extractor is returning more than one function.');
    }

    /**
     * A panel asks for the area's whole subtree, eight courses in the panel's own order, the page's criteria.
     *
     * A panel is the whole area, so a course of a subarea is in it, drawn with its overline
     * (the request names no `direct`: the service has no such parameter, and an unknown key is
     * refused). The tree's category never travels: a panel is asked about a category below the
     * page's, and the service drops a cat that is not an admitted category below the one it is asked
     * about ({@see \theme_dimensions\local\category\population::cat_ids()}), so the page's own
     * would be a criterion that narrows nothing.
     */
    public function test_a_panel_asks_for_the_subtree_eight_courses_and_the_pages_criteria(): void {
        $this->assertMatchesRegularExpression('~^const PANEL_SIZE = 8;$~m', $this->source());
        $args = $this->body('panelArgs');
        $this->assertDoesNotMatchRegularExpression(
            '~\bdirect\b~',
            $args,
            'The service takes no direct key: an unknown one is refused.'
        );
        $this->assertMatchesRegularExpression('~\bwithcategories: true,~', $args, 'And a panel\'s request, carrying its context.');
        $this->assertStringContainsString('perpage: PANEL_SIZE,', $args);
        $this->assertStringContainsString('page: 0,', $args);
        $this->assertStringContainsString('categoryid: id,', $args, 'It asks about the area, not the page.');
        $this->assertStringContainsString('sort: psort === PSORT_NAME ? SORT.name : SORT.recent,', $args);
        $criteria = [
            'query: params.get(PARAM.query)',
            "chip: chip === LEGACY_FAVOURITES ? '' : chip,",
            "fav: params.get(PARAM.fav) === '1' || chip === LEGACY_FAVOURITES,",
            'filters: filters,',
            'datefrom: period.from,',
            'dateto: period.to,',
        ];
        foreach ($criteria as $argument) {
            $this->assertStringContainsString($argument, $args, 'A panel carries the page\'s ' . $argument);
        }
        /* The period is the address's read through the calendar's own reading of it, either bound
           alone or both in order: a hand-edited pair given backwards, which the page put in order,
           would otherwise reach a service that refuses it and no panel would open. */
        $this->assertStringContainsString(
            'const period = DateRange.period(params.get(PARAM.from), params.get(PARAM.to));',
            $args
        );
        $this->assertStringContainsString("import * as DateRange from 'theme_dimensions/daterange';", $this->source());
        $this->assertDoesNotMatchRegularExpression('~\bcat:~', $args, 'The tree\'s category never travels with a panel.');
        $this->assertStringContainsString("SORT = {\n    recent: 'recent',\n    name: 'name',\n};", $this->source());

        $ask = $this->body('ask');
        $this->assertStringContainsString("methodname: 'theme_dimensions_get_category_rows',", $ask);
        $this->assertStringContainsString('args: panelArgs(id, psort),', $ask);
        $this->assertStringContainsString(
            'AreaPanel.arm({ask: ask, painted: paintProgress})',
            $this->init_body('categorypanel'),
            'The shared panel asks through this module and nothing else.'
        );
        /* The answer's context is the server's panel, plus the cards the service returns at the top
           level and the page's own suggestions for a zero panel, which the service cannot know:
           they are read off the posters. */
        $merge = $this->body('answerContext');
        $this->assertMatchesRegularExpression(
            '~return Object\.assign\(\{\}, response\.panel, \{\s*cards: response\.cards \|\| \[\],\s*'
                . 'jsmode: true,\s*suggestions: offered,\s*hassuggestions: offered\.length > 0,\s*\}\);~',
            $merge
        );
        $this->assertStringContainsString('const offered = suggestions();', $merge);
        $this->assertStringContainsString('const context = answerContext(response, id);', $this->body('request'));
    }

    /**
     * The address is written with replaceState, and pushState is not in the module at all.
     *
     * Opening a panel is not a state to go back through.
     */
    public function test_the_address_is_replaced_and_never_pushed(): void {
        $write = $this->body('writeAddress');
        $this->assertStringContainsString("window.history.replaceState(window.history.state, '', url.toString());", $write);
        $this->assertStringContainsString('url.searchParams.set(PARAM.open, String(state.open));', $write);
        $this->assertStringContainsString('url.searchParams.delete(PARAM.open);', $write);
        $this->assertStringContainsString('url.searchParams.set(PARAM.psort, PSORT_NAME);', $write);
        $this->assertStringNotContainsString('pushState(', $this->sources(), 'Opening a panel is not a history entry.');
    }

    /**
     * Opening an area closes the one that was open first: one panel at a time.
     */
    public function test_one_panel_is_open_at_a_time(): void {
        $open = $this->body('openArea');
        $close = strpos($open, 'setPosterOpen(prev, false);');
        $this->assertIsInt($close, 'Opening an area no longer closes the one that was open.');
        $this->assertGreaterThan($close, (int) strpos($open, 'setPosterOpen(poster, true);'), 'The previous one closes FIRST.');
        $this->assertStringContainsString('state.open = id;', $open);
        $poster = $this->body('setPosterOpen');
        $this->assertStringContainsString("control.setAttribute('aria-expanded', on ? 'true' : 'false');", $poster);
        $this->assertStringContainsString('poster.classList.toggle(CLASSES.posteropen, on);', $poster);
        $this->assertStringContainsString("trigger.dataset[on ? 'close' : 'open']", $poster);
    }

    /**
     * Escape closes from the panel or its poster and puts the focus back on the poster's button.
     *
     * Not from the search box, where Escape empties the field, and not from the film's controls,
     * where covervideo stops the film on the same key: the panel must not close under either.
     */
    public function test_escape_closes_the_panel_and_gives_the_focus_back_to_its_poster(): void {
        $key = $this->body('onKeydown');
        $this->assertStringContainsString("event.key === 'Escape' && state.open", $key);
        $this->assertStringContainsString('active.matches(SELECTORS.search)', $key, 'Not from the search box.');
        $this->assertStringContainsString('active.closest(SELECTORS.covercontrols)', $key, 'Not from the film\'s controls.');
        $this->assertMatchesRegularExpression(
            '~if \(panel\.contains\(active\) \|\| \(poster && poster\.contains\(active\)\)\) \{\s*'
                . 'event\.preventDefault\(\);\s*closeArea\(true, true\);~',
            $key,
            'Escape from inside the panel or on its poster closes it AND returns the focus.'
        );
        $close = $this->body('closeArea');
        $this->assertMatchesRegularExpression(
            '~if \(focus && control\) \{\s*control\.focus\(\{preventScroll: true\}\);~',
            $close,
            'The focus goes back to the poster\'s button.'
        );
        $this->assertStringContainsString("search: 'input[type=\"search\"]',", $this->source(self::SHARED));
        $this->assertStringContainsString("covercontrols: '[data-region=\"dim-cover-controls\"]',", $this->source(self::SHARED));
    }

    /**
     * The skeleton waits 150ms, and a failure is the panel's own inline state and never a dialogue.
     *
     * The 150ms are counted from the moment the question goes out, so a client that waits for a
     * quiet first (the visitor's) adds it; the logged-in client names none.
     */
    public function test_the_skeleton_waits_and_a_failure_is_inline(): void {
        $this->assertMatchesRegularExpression('~^const SKELETON_DELAY = 150;$~m', $this->source(self::SHARED));
        $request = $this->body('request');
        $this->assertMatchesRegularExpression(
            '~state\.skeleton = window\.setTimeout\(\(\) => \{\s*if \(ticket === state\.ticket\) \{\s*'
                . 'transplant\(shellContext\(poster, \{isskeleton: true\}\), ticket\);\s*\}\s*\}, '
                . 'SKELETON_DELAY \+ client\.quiet\);~',
            $request
        );
        $this->assertMatchesRegularExpression(
            "~^    quiet: 0,$~m",
            $this->source(self::SHARED),
            'No quiet unless a client names one.'
        );
        $found = preg_match('~\} catch \(error\) \{(.*?)\} finally \{~s', $request, $matches);
        $this->assertSame(1, $found, 'The request has no catch block.');
        $this->assertStringContainsString('transplant(shellContext(poster, {iserror: true}), ticket)', $matches[1]);
        $this->assertStringNotContainsString('Notification', $this->sources(), 'A panel never answers with a dialogue.');
        // The latest request wins.
        $this->assertStringContainsString('const ticket = ++state.ticket;', $request);
        $this->assertStringContainsString('if (ticket !== state.ticket) {', $request);
    }

    /**
     * The latest request wins after the render resolves, not only before it is started.
     *
     * Both renders are asynchronous. A check made only before one starts lets an older state write
     * over a newer one: the skeleton's render and the answer's run side by side when the answer lands
     * just after the skeleton's delay, and whichever resolved last owned the body - the panel could
     * stay a skeleton for good; and a superseded opening's whole render wrote its area's header, which
     * the next transplant keeps, over the rows of the area opened since. So each render is handed the
     * token it was started under and compares it once the render is back, before touching the page.
     */
    public function test_a_render_that_resolves_after_a_newer_one_writes_nothing(): void {
        $whole = $this->body('renderWhole');
        $render = strpos($whole, 'await Templates.renderForPromise(TEMPLATE, context);');
        $check = strpos($whole, "if (turn !== state.turn) {\n        return false;\n    }");
        $write = strpos($whole, 'Templates.replaceNodeContents(panel, rendered.html, rendered.js);');
        $this->assertIsInt($render);
        $this->assertIsInt($check, 'A whole render checks its opening is still the current one.');
        $this->assertIsInt($write);
        $this->assertTrue($render < $check && $check < $write, 'The check sits between the render and the write.');

        $transplant = $this->body('transplant');
        $paint = strpos($transplant, 'const paint = ++state.paint;');
        $render = strpos($transplant, 'await Templates.renderForPromise(TEMPLATE, context);');
        $check = strpos($transplant, "if (ticket !== state.ticket || paint !== state.paint) {\n        return false;\n    }");
        $write = strpos($transplant, 'Templates.appendNodeContents(holder, rendered.html, rendered.js);');
        $this->assertIsInt($paint, 'A transplant numbers itself before it renders.');
        $this->assertIsInt($check, 'A transplant writes only for the latest request AND as the latest transplant.');
        $this->assertTrue(
            $paint < $render && $render < $check && $check < $write,
            'The number is taken before the render and compared between the render and the write.'
        );

        // Every caller hands over the token it was started under, and stops when the answer is false.
        $request = $this->body('request');
        $this->assertSame(3, substr_count($request, 'transplant('), 'Three transplants: the skeleton, the answer, the error.');
        $this->assertSame(3, substr_count($request, ', ticket)'), 'Each one under the request\'s ticket.');
        $this->assertStringContainsString('if (!(await transplant(context, ticket))) {', $request);
        $this->assertStringContainsString('if (!(await transplant(shellContext(poster, {iserror: true}), ticket))) {', $request);
        $open = $this->body('openArea');
        $this->assertStringContainsString('const turn = ++state.turn;', $open);
        $this->assertSame(2, substr_count($open, 'renderWhole(shell, turn)'), 'Both branches of an opening pass their turn.');
        $this->assertStringContainsString('state.turn += 1;', $this->body('closeArea'), 'A close supersedes an opening too.');
    }

    /**
     * Every step that starts a transition first clears the three classes another step may have left.
     *
     * The crossfade (fading), the swap between rows (static) and the collapsed box (shut) each last one
     * transition, and the step that added one takes it off when it finishes. One superseded before it
     * finishes never gets there: a second press inside the crossfade left `fading` on the panel, whose
     * inner box it draws at opacity 0, so every later opening grew an invisible box and put the focus
     * inside it; an opening in the same row while the first render was still out left `shut`, a panel
     * collapsed to nothing. The invariant is that no step ADDS one of these classes except through
     * settle(), which removes all three first - so whatever a superseded step left, the next one clears.
     */
    public function test_every_step_starts_from_the_transition_classes_it_sets_itself(): void {
        $settle = $this->body('settle');
        $this->assertStringContainsString('panel.classList.remove(CLASSES.shut, CLASSES.still, CLASSES.fading);', $settle);
        $this->assertLessThan(
            (int) strpos($settle, 'panel.classList.add(...classes);'),
            (int) strpos($settle, 'panel.classList.remove('),
            'It clears before it sets.'
        );
        $source = $this->sources();
        $this->assertStringContainsString($settle, $source, 'The control: settle() is in the files read.');
        $outside = str_replace($settle, '', $source);
        foreach (['fading', 'still', 'shut'] as $class) {
            $this->assertDoesNotMatchRegularExpression(
                '~classList\.add\([^)]*CLASSES\.' . $class . '\b~',
                $outside,
                "Only settle() puts {$class} on the panel."
            );
        }
        $open = $this->body('openArea');
        $this->assertStringContainsString('settle(CLASSES.fading);', $open, 'The crossfade.');
        $this->assertStringContainsString('settle(CLASSES.still, CLASSES.shut);', $open, 'The swap, from another row or closed.');
        $this->assertStringContainsString('settle(CLASSES.shut);', $this->body('closeArea'), 'The close.');
        // A step that finds itself superseded writes no class at all: the step that superseded it settled them.
        $this->assertStringContainsString('if (turn !== state.turn || !(await renderWhole(shell, turn))) {', $open);
    }

    /**
     * A focus inside what a transplant replaces, or inside a panel Back closes, is put back and not lost.
     *
     * The template gives a focus key to the header's controls and the footer's call to action; the
     * card links, the stars, the strip's pills and the tile have none. A transplant replacing one of
     * those while it held the focus left the focus on the body - the top of the document - so the
     * restore runs whenever the focus WAS inside the panel, by its key when it had one and on the
     * title otherwise. And Back closing the panel empties it: a focus inside goes to the poster.
     */
    public function test_a_focus_inside_a_replaced_region_or_a_closed_panel_is_put_back(): void {
        $transplant = $this->body('transplant');
        $this->assertStringContainsString('const inside = Boolean(active) && panel.contains(active);', $transplant);
        $this->assertMatchesRegularExpression(
            '~if \(inside && !panel\.contains\(document\.activeElement\)\) \{.*?'
                . 'const target = keyed \|\| panel\.querySelector\(SELECTORS\.title\);~s',
            $transplant,
            'Restored whenever the focus was inside, on the title when it had no key.'
        );
        $this->assertStringNotContainsString("if (key !== '' && !panel.contains", $transplant, 'Not only for a keyed control.');
        /* Back closes with the focus rule of every other close: a focus inside the panel, or on the
           open poster - which leaves the grid when its area is zeroed - is put on the poster or on
           the summary, never dropped to the body. */
        $pop = $this->body('onPopState');
        $this->assertStringContainsString('const open = posterOf(state.open);', $pop);
        $this->assertStringContainsString(
            'closeArea(panel.contains(active) || (Boolean(open) && open.contains(active)), false);',
            $pop,
            'Back returns a focus from inside the panel, or on the open poster, to where closeArea() puts it.'
        );
    }

    /**
     * The module's two breakpoints are the stylesheet's, where the panel's row changes.
     */
    public function test_the_breakpoints_are_the_stylesheets_own(): void {
        $source = $this->source(self::SHARED);
        $this->assertMatchesRegularExpression("~^const WIDE = '\(max-width: 1199\.98px\)';$~m", $source);
        $this->assertMatchesRegularExpression("~^const PHONE = '\(max-width: 575\.98px\)';$~m", $source);
        $scss = (string) file_get_contents($this->plugin_root() . '/scss/category.scss');
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 1199\.98px\) \{\s*\.dim-panel \{[^}]*grid-row: var\(--dim-row-2\);~',
            $scss,
            'The two-track row is read at the module\'s own WIDE.'
        );
        $this->assertMatchesRegularExpression(
            '~@media \(max-width: 575\.98px\) \{\s*\.dim-panel \{[^}]*grid-row: var\(--dim-row-1\);~',
            $scss,
            'The one-track row is read at the module\'s own PHONE.'
        );
        $listen = $this->body('listen');
        $this->assertStringContainsString('[WIDE, PHONE, FINE].forEach((query) => {', $listen);
        $this->assertStringContainsString("window.matchMedia(query).addEventListener('change', relayout);", $listen);
        $this->assertStringContainsString('document.addEventListener(EVENTS.view, relayout);', $listen);
        $this->assertStringNotContainsString(
            "'resize'",
            $this->sources(),
            'The panel is placed on a breakpoint change, never on a resize.'
        );
    }

    /**
     * The three other holders of the open panel follow every write of the address.
     *
     * The toolbar form (its no-JavaScript submit), the hero's fold form and the clear link: each
     * would otherwise reload the page with the panel it had when it loaded.
     */
    public function test_the_three_holders_of_the_panel_follow_it(): void {
        $this->assertMatchesRegularExpression('~\n    syncHolders\(\);\n$~', "\n" . $this->body('writeAddress') . "\n");
        $holders = $this->body('syncHolders');
        $this->assertStringContainsString(
            '[document.querySelector(SELECTORS.toolbar), document.querySelector(SELECTORS.fold)].forEach((form) => {',
            $holders
        );
        $this->assertStringContainsString('syncField(form, SELECTORS.openfield, PARAM.open, open);', $holders);
        $this->assertStringContainsString('syncField(form, SELECTORS.psortfield, PARAM.psort, psort);', $holders);
        $this->assertStringContainsString('const clear = document.querySelector(SELECTORS.clear);', $holders);
        $this->assertStringContainsString('clear.href = url.toString();', $holders);
        $source = $this->source(self::SHARED);
        foreach (['dim-open-field', 'dim-psort-field', 'dim-hero-fold', 'dim-vitrine-clear', 'dim-vitrine-toolbar'] as $hook) {
            $this->assertStringContainsString('[data-region="' . $hook . '"]', $source, $hook . ' is a hook of this module.');
        }
    }

    /**
     * The panel moves with moveBefore(), which keeps the focus, and falls back to insertBefore().
     */
    public function test_the_panel_moves_with_move_before_and_falls_back_to_insert_before(): void {
        $move = $this->body('moveAfter');
        $this->assertStringContainsString("typeof parent.moveBefore === 'function'", $move);
        $this->assertStringContainsString('parent.moveBefore(node, next);', $move);
        $this->assertStringContainsString('parent.insertBefore(node, next);', $move);
        $this->assertStringContainsString('active.focus({preventScroll: true});', $move, 'The fallback puts the focus back.');
        // And it goes after the LAST poster of the open poster's row, measured on the laid-out page.
        $place = $this->body('place');
        $this->assertStringContainsString('if (Math.abs(el.offsetTop - top) < 2) {', $place);
        $this->assertStringContainsString('moveAfter(panel, last);', $place);
        foreach (['--dim-row-3', '--dim-row-2', '--dim-row-1', '--dim-col-3', '--dim-col-2'] as $property) {
            $this->assertStringContainsString("panel.style.setProperty('" . $property . "'", $place);
        }
        /* And the open area's band, copied from the poster it opens under on every placement (round
           I): a panel moved to another area keeping the first one's colour would wear the wrong rail. */
        $this->assertMatchesRegularExpression(
            "~const band = poster\\.style\\.getPropertyValue\\('--dim-card-band'\\)\\.trim\\(\\);\\s*"
                . "if \\(band !== ''\\) \\{\\s*panel\\.style\\.setProperty\\('--dim-area-bg', band\\);~",
            $place
        );
    }

    /**
     * Nothing either module draws is assembled from a string: the panel is the server's template.
     */
    public function test_nothing_is_assembled_from_a_string(): void {
        foreach (['categorypanel', self::SHARED, 'categorystar', 'categorymark', 'categoryposters'] as $module) {
            $source = $this->source($module);
            $this->assertStringNotContainsString('innerHTML', $source, $module);
            $this->assertStringNotContainsString('insertAdjacentHTML', $source, $module);
        }
        $this->assertMatchesRegularExpression(
            "~^const TEMPLATE = 'theme_dimensions/category_panel';$~m",
            $this->source(self::SHARED)
        );
        $this->assertStringContainsString('Templates.renderForPromise(TEMPLATE, context)', $this->body('renderWhole'));
        $this->assertStringContainsString('Templates.renderForPromise(TEMPLATE, context)', $this->body('transplant'));
    }

    /**
     * The star calls the theme's guarded wishlist service and never core's, from one listener armed once.
     *
     * core_course_set_favourite_courses instantiates a course context and checks nothing else, so it
     * would star a course this page is not allowed to admit exists. The second half is the control:
     * a module that called no service at all would pass the first on its own.
     */
    public function test_the_star_calls_the_themes_own_service_and_is_armed_once_by_both_modules(): void {
        $star = $this->source('categorystar');
        $this->assertStringNotContainsString('core_course_set_favourite_courses', $star);
        $this->assertStringContainsString(
            "methodname: 'theme_dimensions_toggle_wishlist',",
            $this->body('toggleStar', 'categorystar')
        );
        $this->assertStringContainsString('theme_dimensions_toggle_wishlist', $this->build('categorystar'));
        $init = $this->init_body('categorystar');
        $this->assertMatchesRegularExpression(
            '~if \(armed\) \{\s*return;\s*\}\s*armed = true;~',
            $init,
            'One listener, whoever calls.'
        );
        $this->assertStringContainsString("document.addEventListener('click', (event) => {", $init, 'Delegated, for later cards.');
        foreach (['categoryvitrine', 'categorypanel'] as $module) {
            $this->assertStringContainsString(
                "import * as CategoryStar from 'theme_dimensions/categorystar';",
                $this->source($module)
            );
            $this->assertStringContainsString('CategoryStar.init();', $this->init_body($module), $module . ' arms the star.');
        }
    }

    /**
     * Each module writes its own mark on the body, and never another module's.
     *
     * The panel's mark is the shared module's, written when a client arms it: the stylesheet's panel
     * rules - its transitions, the rail's arrows and its counter - are what that module drives, on
     * either shell. The logged-in client writes none of its own.
     */
    public function test_each_module_writes_its_own_mark(): void {
        $marks = [self::SHARED => 'dim-panel-js', 'categorystar' => 'dim-star-js'];
        foreach ($marks as $module => $mark) {
            $this->assertMatchesRegularExpression("~^const BODY_CLASS = '" . $mark . "';$~m", $this->source($module));
            $entry = $module === self::SHARED ? $this->body('arm', $module) : $this->init_body($module);
            $this->assertStringContainsString('document.body.classList.add(BODY_CLASS);', $entry);
        }
        foreach (['categorypanel', self::SHARED, 'categorystar'] as $module) {
            $this->assertStringNotContainsString(
                "'dim-js'",
                $this->source($module),
                $module . ' never writes the listing\'s mark.'
            );
        }
        $this->assertStringNotContainsString('BODY_CLASS', $this->source(), 'The logged-in client writes no mark of its own.');
    }

    /**
     * The rail's scroll is heard in the capture phase: a scroll event does not bubble.
     */
    public function test_the_rails_scroll_is_heard_in_the_capture_phase(): void {
        $this->assertMatchesRegularExpression(
            "~panel\.addEventListener\('scroll', \(event\) => \{.*?updateRail\(\);\s*\}\s*\}, true\);~s",
            $this->body('listen')
        );
    }

    /**
     * The committed builds of both modules were made from the sources committed beside them.
     *
     * Moodle serves amd/build and never amd/src, so a source edit that was never rebuilt is
     * invisible in the browser and green in every other assertion of this file.
     */
    public function test_the_committed_builds_were_made_from_the_committed_sources(): void {
        foreach (['categorypanel', self::SHARED, 'categorystar', 'categorymark'] as $module) {
            $this->assertSame(
                $this->source($module),
                $this->built_from($module),
                "amd/build/{$module} is not this source: rebuild it with grunt amd and commit the build."
            );
        }
        $this->assertStringContainsString('theme_dimensions_get_category_rows', $this->build('categorypanel'));
        $this->assertStringContainsString('theme_dimensions/' . self::SHARED, $this->build('categorypanel'));
        $this->assertStringContainsString('theme_dimensions/category_panel', $this->build(self::SHARED));
        $this->assertStringContainsString('theme_dimensions/categoryposters', $this->build('categorypanel'));
        $this->assertStringContainsString('dim-card-title', $this->build('categorymark'));
    }

    /**
     * The criteria's answer is written in one order, and the panel is asked again after the posters.
     *
     * The grids stop being busy first; the posters take their numbers, their opening state and
     * their place in the grid; the summary, the reveal and the "nothing anywhere" box follow; the
     * open panel is placed among the posters still shown and asked again - after the posters,
     * because a zero panel's suggestions are read off their counts - and the automatic opening
     * comes last. The pending event dims the pills and puts "..." on each, a placeholder and never
     * a sentence.
     */
    public function test_the_criteria_answer_is_written_in_order_and_the_panel_asked_after_the_posters(): void {
        $criteria = $this->body('criteria');
        $order = [
            "grid.removeAttribute('aria-busy')",
            'Posters.paintPosters(areas, accordion, filtered, state, setExpandable);',
            'Posters.paintSummary(areas, accordion, data, state.open);',
            'Posters.paintReveal(areas, data);',
            'paintAllEmpty();',
            'place();',
            'request();',
        ];
        $last = -1;
        foreach ($order as $step) {
            $at = strpos($criteria, $step);
            $this->assertIsInt($at, 'The answer no longer runs ' . $step);
            $this->assertGreaterThan($last, $at, $step . ' runs out of the design\'s order.');
            $last = $at;
        }
        $this->assertStringContainsString('state.allempty = Boolean(data.allempty);', $criteria);
        $this->assertStringContainsString("areas.dataset.elsewhere = data.elsewhere || '';", $criteria);
        // The logged-in client's automatic opening comes after the shared painting and the re-asked panel.
        $this->assertMatchesRegularExpression(
            '~AreaPanel\.criteria\(detail\);\s*AreaPanel\.autoOpen\(detail\.areas \|\| \{\}\);~',
            $this->body('onCriteria')
        );
        $listen = $this->body('listenToListing');
        $this->assertStringContainsString(
            'document.addEventListener(EVENTS.criteria, (event) => onCriteria(event.detail || {}));',
            $listen
        );
        $this->assertStringContainsString(
            'document.addEventListener(EVENTS.pending, (event) => Posters.pending(areas, event.detail || {}));',
            $listen
        );
        $this->assertMatchesRegularExpression(
            "~pending: 'dim-vitrine:pending',\s*criteria: 'dim-vitrine:criteria',\s*failed: 'dim-vitrine:failed',~",
            $this->source()
        );
        $this->assertMatchesRegularExpression("~autoopened: 'dim-panel:autoopened',~", $this->source(self::SHARED));

        $pending = $this->body('pending', 'categoryposters');
        $this->assertStringContainsString("grid.setAttribute('aria-busy', 'true')", $pending);
        $this->assertMatchesRegularExpression(
            '~if \(!detail\.filtered\) \{\s*return;~',
            $pending,
            'Only a narrowing request waits.'
        );
        $this->assertStringContainsString('pill.textContent = PENDING;', $pending);
        $this->assertStringContainsString('pill.classList.remove(CLASSES.pillzero);', $pending);
        $this->assertMatchesRegularExpression("~^const PENDING = '…';$~m", $this->source('categoryposters'));

        // The pill and its description are written from the server's words, and nothing is composed.
        $posters = $this->body('paintPosters', 'categoryposters');
        $this->assertStringContainsString("poster.dataset.resultcount = entry.resultcount || '';", $posters);
        $this->assertStringContainsString("poster.dataset.resultlabel = entry.resultlabel || '';", $posters);
        $this->assertStringContainsString('paintPill(poster, filtered);', $posters);
        $pill = $this->body('paintPill', 'categoryposters');
        $this->assertStringContainsString("const label = poster.dataset.resultlabel || '';", $pill);
        $this->assertStringContainsString('pill.textContent = label;', $pill);
        $this->assertStringContainsString("control.setAttribute('aria-describedby', pill.id);", $pill);
        $this->assertStringContainsString("control.removeAttribute('aria-describedby');", $pill);
    }

    /**
     * A failed request puts every pill back and the grids stop waiting.
     *
     * The pending event makes every pill say "..." and every grid busy; the answer's event is the
     * only other thing that touches them, and a failed request sends none. The listing's module
     * says so (EVENTS.failed), and each pill is rewritten from its poster's own data - the words the
     * server drew on the article and every answer rewrote - under the page's result mode, which the
     * failed question never changed. The pill is also its control's description, so a pill left at
     * "..." would have every poster read as "...".
     */
    public function test_a_failed_request_puts_the_pills_back_and_the_grids_stop_waiting(): void {
        $failed = $this->body('failed', 'categoryposters');
        $this->assertStringContainsString(
            "areas.querySelectorAll(SELECTORS.grid).forEach((grid) => grid.removeAttribute('aria-busy'));",
            $failed
        );
        $this->assertStringContainsString(
            'areas.querySelectorAll(SELECTORS.area).forEach((poster) => paintPill(poster, isFiltered(areas)));',
            $failed,
            'Every pill is written again from its poster, under the result mode the page is still in.'
        );
        $this->assertStringContainsString(
            'document.addEventListener(EVENTS.failed, () => Posters.failed(areas));',
            $this->body('listenToListing')
        );
        $this->assertMatchesRegularExpression("~failed: 'dim-vitrine:failed',~", $this->source());
        $card = (string) file_get_contents($this->plugin_root() . '/templates/category_directory_card.mustache');
        $this->assertStringContainsString(
            'data-resultlabel="{{results.label}}"',
            $card,
            'The server draws the pill\'s words on the article, so a page that has had no answer yet can put them back.'
        );
    }

    /**
     * The automatic opening keeps the focus where it is and never swaps a panel the reader opened.
     *
     * The reader is typing in the search box when the answer arrives: a panel that took the focus
     * would take their next keystroke, so it opens with focus false and scroll false. And an area
     * the reader opened is theirs: a later answer naming another single area opens nothing over
     * it. The opening records who made it, so an automatic panel can be swapped by the next
     * automatic one and a reader's cannot.
     */
    public function test_an_automatic_opening_keeps_the_focus_and_never_swaps_a_readers_panel(): void {
        $auto = $this->body('autoOpen');
        $this->assertStringContainsString('openArea(id, {focus: false, scroll: false, auto: true});', $auto);
        $this->assertMatchesRegularExpression(
            "~if \(!id \|\| id === state\.open \|\| \(state\.open && state\.openedby === 'user'\)\) \{\s*return;~",
            $auto,
            'A panel the reader opened is never swapped for the single match.'
        );
        $this->assertStringContainsString(
            'document.dispatchEvent(new CustomEvent(EVENTS.autoopened, {detail: {id: id}}));',
            $auto,
            'The count line is told, so it can say why the panel is open.'
        );
        $open = $this->body('openArea');
        $this->assertStringContainsString("state.openedby = options.auto ? 'auto' : 'user';", $open);
        $this->assertMatchesRegularExpression(
            '~if \(!options\.auto\) \{\s*hideHint\(\);~',
            $open,
            'An automatic opening leaves the hint: the reader has not found what it says.'
        );
        $this->assertStringContainsString(
            "state.openedby = 'user';",
            $this->body('adopt'),
            'A panel of the address is the reader\'s.'
        );
        $this->assertStringContainsString("state.openedby = '';", $this->body('closeArea'));
    }

    /**
     * A zeroed open poster stays in its grid until it is closed, and closing it focuses the summary.
     *
     * An answer hides every area with no result except the open one, whose panel the reader is
     * looking at; closing it - Close, its own button, Escape, Back - sends it to the summary, and
     * since its poster is gone the focus goes to the summary's own control rather than falling to
     * the body. Moving from it to another area hides it first and is always a move to another row,
     * because the row it shared may be gone.
     */
    public function test_a_zeroed_open_poster_stays_until_closed_and_its_close_focuses_the_summary(): void {
        $this->assertStringContainsString(
            'itemOf(poster).hidden = filtered && count === 0 && id !== state.open;',
            $this->body('paintPosters', 'categoryposters'),
            'The open area stays in its grid whatever the answer says.'
        );
        $close = $this->body('closeArea');
        $this->assertStringContainsString('const zeroed = isZeroed(poster);', $close);
        $this->assertMatchesRegularExpression('~if \(zeroed\) \{\s*itemOf\(poster\)\.hidden = true;~', $close);
        $this->assertMatchesRegularExpression(
            '~if \(focus && zeroed\) \{\s*focusSummary\(\);\s*\} else if \(focus && control\) \{~',
            $close,
            'A closed zeroed area gives the focus to the summary it has just joined.'
        );
        $this->assertLessThan(
            (int) strpos($close, 'setPosterOpen(poster, false);'),
            (int) strpos($close, 'const zeroed = isZeroed(poster);'),
            'Whether it was zeroed is read before anything about it changes.'
        );
        $this->assertStringContainsString(
            'summary.focus({preventScroll: true});',
            $this->body('focusSummary')
        );
        $open = $this->body('openArea');
        $this->assertStringContainsString('const prevhides = isZeroed(prev);', $open);
        $this->assertMatchesRegularExpression('~if \(prevhides\) \{\s*itemOf\(prev\)\.hidden = true;~', $open);
        $this->assertStringContainsString('const sameRow = sharesRow(prev, poster);', $open);
        $this->assertMatchesRegularExpression(
            '~const sharesRow = \(prev, poster\) => Boolean\(prev\) && !isZeroed\(prev\) && !panel\.hidden~',
            $this->source(self::SHARED),
            'A zeroed poster never shares a row with the next: its row may be gone.'
        );
        $this->assertMatchesRegularExpression(
            "~const isZeroed = \(poster\) => Boolean\(poster\) && isFiltered\(\) && Number\(poster\.dataset\.count\) === 0;~",
            $this->source(self::SHARED)
        );
        // The summary counts what it lists: the open area's entry is hidden while its poster stays.
        $recount = $this->body('recountSummary', 'categoryposters');
        $this->assertStringContainsString('item.hidden = Number(item.dataset.categoryid) === open;', $recount);
        $this->assertStringContainsString(
            'const recountSummary = () => Posters.recountSummary(areas, state.open);',
            $this->source(self::SHARED),
            'The panel hands the shared summary its open area.'
        );
        $this->assertStringContainsString(
            "const label = (openzero ? box.dataset.labelLess : box.dataset.labelAll) || '';",
            $recount
        );
        // And the server's Close of a zeroed area lands on the summary: that fragment is the page's own.
        $this->assertStringContainsString("url.hash === '#' + NOMATCH_ID", $this->body('writeAddress'));
        $this->assertMatchesRegularExpression("~^const NOMATCH_ID = 'dim-nomatch';$~m", $this->source(self::SHARED));
    }

    /**
     * A poster's opening state follows the criteria both ways, and keeps the pill's description.
     *
     * A page loaded under a criterion draws every poster that can open; when the reader clears the
     * filters in place, an area with no course at rest must be a link to its page again, reading
     * "No courses yet", not a button opening an empty panel - and the other way round. toButton()
     * copies the data attributes alone, so the pill's aria-describedby is handed over by name in
     * both directions. The open poster waits until it closes.
     */
    public function test_the_opening_state_follows_the_criteria_both_ways_and_keeps_the_description(): void {
        $posters = $this->body('paintPosters', 'categoryposters');
        $this->assertStringContainsString('expandable(poster, Boolean(entry.expandable));', $posters);
        $this->assertStringContainsString('state.expandable.set(id, Boolean(entry.expandable));', $posters);
        $this->assertStringContainsString(
            'Posters.paintPosters(areas, accordion, filtered, state, setExpandable);',
            $this->body('criteria'),
            'The panel hands the shared painter its own setExpandable().'
        );
        $flip = $this->body('setExpandable');
        $this->assertStringContainsString("poster.dataset.expandable = on ? '1' : '0';", $flip);
        $this->assertStringContainsString('poster.classList.toggle(CLASSES.posterempty, !on);', $flip);
        $this->assertStringContainsString('note.hidden = on;', $flip);
        $this->assertStringContainsString("trigger.dataset[on ? 'open' : 'empty']", $flip);
        $this->assertStringContainsString('convertPoster(poster);', $flip, 'To a button.');
        $this->assertStringContainsString('toLink(control, poster.dataset.titleurl);', $flip, 'And back to the area\'s page.');
        $this->assertMatchesRegularExpression(
            '~if \(isExpandable\(poster\) === on \|\| Number\(poster\.dataset\.categoryid\) === state\.open\) \{~',
            $flip,
            'The open poster keeps its control until the panel closes.'
        );
        $this->assertStringContainsString('applyExpandable(poster);', $this->body('closeArea'));
        $this->assertStringContainsString('applyExpandable(prev);', $this->body('openArea'));

        $link = $this->body('toLink');
        $this->assertStringContainsString("link.setAttribute('aria-describedby', described);", $link);
        $this->assertStringContainsString('link.append(...button.childNodes);', $link);
        $convert = $this->body('convertPoster');
        $this->assertStringContainsString("const described = control.getAttribute('aria-describedby');", $convert);
        $this->assertStringContainsString("attributes['aria-describedby'] = described;", $convert);
        $this->assertStringNotContainsString('describedby', $this->body('toButton'), 'toButton() really does drop it.');
    }

    /**
     * A zero panel's suggestions are the page's posters with a result, and buttons opening their area.
     *
     * They are page data: the web service answers for one area. So they are read off the posters -
     * the others, holding a result under the criterion - and merged into the context like the cards;
     * a panel the server drew carries them as links, which the adoption turns into buttons; and a
     * press acts on the BUTTON alone and opens that area in place, giving it the focus.
     */
    public function test_a_zero_panels_suggestions_are_the_pages_and_open_their_area(): void {
        $offer = $this->body('suggestions');
        $this->assertMatchesRegularExpression('~if \(!isFiltered\(\)\) \{\s*return \[\];~', $offer);
        $this->assertStringContainsString(
            '.filter((poster) => Number(poster.dataset.categoryid) !== state.open && Number(poster.dataset.count) > 0)',
            $offer
        );
        $this->assertStringContainsString("count: poster.dataset.resultcount || '',", $offer);
        $this->assertStringContainsString(
            "countlabel: poster.dataset.resultlabel || '',",
            $offer,
            'Each suggestion carries the pill\'s words, which a screen reader is read in place of the digit.'
        );
        $this->assertMatchesRegularExpression(
            "~panel\.querySelectorAll\('a' \+ SELECTORS\.openarea\)\.forEach\(\(link\) => \{\s*toButton\(link, \{\}\);~",
            $this->body('convertPanel')
        );
        $this->assertMatchesRegularExpression(
            '~const suggestion = closestOf\(event, SELECTORS\.openarea\);\s*'
                . "if \(suggestion && suggestion\.tagName === 'BUTTON'\) \{\s*event\.preventDefault\(\);\s*"
                . 'openArea\(Number\(suggestion\.dataset\.categoryid\), \{focus: true\}\);~',
            $this->body('onPanelClick')
        );
        $this->assertStringContainsString("openarea: '[data-action=\"dim-panel-openarea\"]',", $this->source(self::SHARED));
        // And a zero panel is announced in the server's two sentences: its own, and where the results are.
        $this->assertStringContainsString(
            "said = (String(context.zero || '') + ' ' + (areas.dataset.elsewhere || '')).trim();",
            $this->body('request')
        );
    }

    /**
     * The searched term is marked on the text of the card's title heading, never through markup.
     *
     * categorymark finds a title by the heading's hook and not by the link's, because a visitor's
     * card of a course with no page to open draws its title as plain text, and the panel's rail on
     * a visitor's page is made of exactly those. It walks the heading's text nodes, folds case and
     * accents on both sides keeping a map back to the source characters, and builds each mark with
     * createElement and textContent. The two modules call it where they draw cards: the panel after
     * the answer's transplant and on adoption, the listing after each render and at load.
     */
    public function test_the_term_is_marked_on_the_text_of_the_title_heading(): void {
        $mark = $this->source('categorymark');
        $this->assertMatchesRegularExpression("~^const TITLE = '\[data-region=\"dim-card-title\"\]';$~m", $mark);
        $this->assertStringNotContainsString('dim-card-titlelink', $mark, 'The link alone would miss a visitor\'s plain title.');
        $this->assertStringContainsString('document.createTreeWalker(title, NodeFilter.SHOW_TEXT);', $mark);
        $this->assertStringContainsString(
            "const fold = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();",
            $mark
        );
        $this->assertMatchesRegularExpression("~^const MIN_LENGTH = 2;$~m", $mark, 'The server\'s own floor.');
        $node = $this->body('markNode', 'categorymark');
        $this->assertStringContainsString("const mark = document.createElement('mark');", $node);
        $this->assertStringContainsString('mark.textContent = text.slice(start, end);', $node);
        $this->assertStringContainsString('source.push(index);', $node, 'Each folded character maps back to its source.');
        $this->assertStringContainsString('node.replaceWith(fragment);', $node);
        $this->assertStringContainsString(
            'mark.replaceWith(document.createTextNode(mark.textContent));',
            $this->body('unmark', 'categorymark')
        );
        $this->assertMatchesRegularExpression(
            '~export const mark = \(root, term\) => \{.*?unmark\(title\);~s',
            $mark,
            'Idempotent: the marks a previous call left go first.'
        );
        $panel = $this->source(self::SHARED);
        $this->assertStringContainsString("import * as CategoryMark from 'theme_dimensions/categorymark';", $panel);
        $this->assertStringContainsString('CategoryMark.mark(panel, termOf());', $this->body('request'));
        $this->assertStringContainsString('CategoryMark.mark(panel, termOf());', $this->body('adopt'));
        $vitrine = $this->source('categoryvitrine');
        $this->assertStringContainsString("import * as CategoryMark from 'theme_dimensions/categorymark';", $vitrine);
        $this->assertStringContainsString(
            'items.forEach((item) => CategoryMark.mark(item, state.query));',
            $this->body('paintGrid', 'categorylisting')
        );
        $this->assertStringContainsString(
            'CategoryMark.mark(root.querySelector(SELECTORS.grid), state.query);',
            $this->init_body('categoryvitrine')
        );
    }

    /**
     * At the ROOT a panel carries the term and nothing else, marks it, and the posters go back to rest.
     *
     * The root reads its search and no filter, so a panel opened under a search there asks about its
     * area with the term alone (never a filter a hand-edited address carries), and its rail titles are
     * marked with it. The root at rest counts nothing, so an answer at rest names no area: every poster
     * it does not name goes back to rest, or a search cleared in place would leave the last term's
     * pills and hidden posters behind. And the "nothing anywhere" box's second way out is written from
     * each answer.
     *
     * Changes that must make it fail: the root's panel sending no term (the first assertion).
     */
    public function test_at_the_root_a_panel_carries_the_term_alone_and_the_posters_go_back_to_rest(): void {
        $args = $this->body('panelArgs');
        $this->assertMatchesRegularExpression(
            '~if \(isRoot\(\)\) \{\s*params = new URLSearchParams\(\);\s*'
                . 'if \(address\.has\(PARAM\.query\)\) \{\s*params\.set\(PARAM\.query, address\.get\(PARAM\.query\)\);~',
            $args,
            'The root\'s panel carries the term, and a fresh set of parameters holds nothing else.'
        );
        $this->assertStringContainsString(
            "const termOf = () => new URL(window.location.href).searchParams.get(PARAM.query) || '';",
            $this->source(self::SHARED),
            'The rail is marked at the root too.'
        );

        $posters = $this->body('paintPosters', 'categoryposters');
        $this->assertMatchesRegularExpression(
            '~if \(!filtered\) \{.*?if \(named\.has\(Number\(poster\.dataset\.categoryid\)\)\) \{\s*return;\s*\}'
                . '.*?paintPill\(poster, false\);\s*itemOf\(poster\)\.hidden = false;~s',
            $posters,
            'At rest every poster the answer does not name goes back to rest.'
        );

        $this->assertStringContainsString("Posters.paintSearchAll(areas, data.searchallurl || '');", $this->body('criteria'));
        $this->assertStringContainsString('link.hidden = !url;', $this->body('paintSearchAll', 'categoryposters'));
        $this->assertStringContainsString('isRoot() || (Boolean(direct) && direct.hidden)', $this->body('loadedAllEmpty'));
    }

    /**
     * Every timer and scroll of an opening is held as pending JavaScript until it has settled.
     *
     * A smooth scroll moves the posters for a few hundred milliseconds after an opening, and a press
     * aimed at one while it moves lands wherever the page has gone: the visitor's scenario that opens
     * a second poster lost that press on a slow leg, silently. The scroll, the crossfade's timer and
     * the request's quiet are covered by no request, so each takes a core/pending token, and Behat
     * waits for the settled page by itself.
     *
     * Changes that must make it fail: scheduling the scroll rule without a token; giving the token
     * back before the scroll reports its end; resolving the scroll's promise without a time cap;
     * dropping the crossfade's or the request's token.
     */
    public function test_an_openings_timers_and_scroll_are_pending_until_settled(): void {
        $shared = $this->source(self::SHARED);
        $this->assertStringContainsString("import Pending from 'core/pending';", $shared);
        $this->assertMatchesRegularExpression('~^const SCROLL_END = 1000;$~m', $shared);
        $open = $this->body('openArea');
        $this->assertMatchesRegularExpression(
            "~const scroll = new Pending\('theme_dimensions/categoryareapanel:scroll'\);\n"
                . "\s*window\.setTimeout\(async\(\) => \{\n\s*try \{\n\s*await ensureVisible\(\);\n"
                . "\s*\} finally \{\n\s*scroll\.resolve\(\);~",
            $open,
            'The scroll rule is held from the moment it is scheduled until the page has stopped.'
        );
        $this->assertMatchesRegularExpression(
            "~const crossfade = new Pending\('theme_dimensions/categoryareapanel:crossfade'\);\n"
                . "\s*window\.setTimeout\(async\(\) => \{\n\s*try \{~",
            $open
        );
        $this->assertMatchesRegularExpression(
            '~request\(\);\n\s*\} finally \{\n\s*crossfade\.resolve\(\);~',
            $open,
            'The crossfade gives its token back only once the request holds its own.'
        );
        $start = strpos($shared, "const ensureVisible = () => new Promise((resolve) => {\n");
        $this->assertNotFalse($start, 'The scroll rule is a promise.');
        $visible = substr($shared, $start, (int) strpos($shared, "\n});\n", $start) - $start);
        $this->assertStringContainsString("window.addEventListener('scrollend', () => {", $visible);
        $this->assertStringContainsString('const timer = window.setTimeout(resolve, SCROLL_END);', $visible);
        $this->assertLessThan(
            (int) strpos($visible, 'window.scrollBy('),
            (int) strpos($visible, "addEventListener('scrollend'"),
            'The end is listened for before the scroll starts.'
        );
        $request = $this->body('request');
        $this->assertLessThan(
            (int) strpos($request, 'await quiet(ticket)'),
            (int) strpos($request, "const pending = new Pending('theme_dimensions/categoryareapanel:request');"),
            'The request is held before its quiet.'
        );
        $this->assertMatchesRegularExpression('~\} finally \{\n.*\n\s*pending\.resolve\(\);\n    \}$~s', $request);
    }
}
