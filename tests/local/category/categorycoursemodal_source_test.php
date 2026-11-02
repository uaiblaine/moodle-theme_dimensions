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
 * Theme Dimensions - Tests for the invariants of the course dialogue's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of amd/src/categorycoursemodal.js by reading its source.
 *
 * Same shape and reasoning as {@see covervideo_source_test}: plugin CI runs no JavaScript unit test,
 * so what this module must keep doing is read out of its source, each assertion scoped to the
 * function it is about, and the build-currency assertion at the foot is what says the browser is
 * served this source. Behat is the gate that executes it (the client feature's dialogue scenario).
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class categorycoursemodal_source_test extends \basic_testcase {
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
        $path = $this->plugin_root() . '/amd/src/categorycoursemodal.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The source the committed build was actually made from.
     *
     * @return string
     */
    private function built_from(): string {
        $path = $this->plugin_root() . '/amd/build/categorycoursemodal.min.js.map';
        $this->assertFileExists($path, 'The source map is missing; Moodle serves the build beside it.');
        $map = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($map, 'The source map is not readable JSON.');
        $this->assertArrayHasKey('sourcesContent', $map);
        return (string) ($map['sourcesContent'][0] ?? '');
    }

    /**
     * The body of one top-level arrow function or object literal of the module.
     *
     * @param string $name The name it is bound to.
     * @return string The body, without the signature or the closing brace.
     */
    private function body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->source(), $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in the module.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The extractor really extracts one function and not the file around it.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $click = $this->body('onClick');
        $this->assertStringContainsString('event.preventDefault();', $click);
        $this->assertStringNotContainsString('Modal.create', $click, 'The extractor is returning more than one function.');
        $this->assertStringContainsString('Modal.create', $this->body('open'));
    }

    /**
     * A plain press on a card's link opens the dialogue instead of the page, and a modified press follows the link.
     *
     * Following the link is the default action of the click - from the keyboard (Enter) as well - so preventing it is
     * the one line between the dialogue and the course page. A press with a modifier or another button (a new tab, a
     * new window) and a link whose card names no course return BEFORE that line: the link is the fallback.
     */
    public function test_a_plain_press_on_the_link_opens_the_dialogue_instead_of_the_page(): void {
        $click = $this->body('onClick');
        $modified = strpos($click, 'if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {');
        $nocourse = strpos($click, 'if (courseid === null) {');
        $prevent = strpos($click, 'event.preventDefault();');
        $this->assertNotFalse($modified, 'A modified press, or another button, follows the link.');
        $this->assertNotFalse($nocourse, 'A card that names no course is left to its link.');
        $this->assertNotFalse($prevent, 'A plain press must not also follow the link.');
        $this->assertLessThan($prevent, $modified, 'Both are left alone before anything is prevented.');
        $this->assertLessThan($prevent, $nocourse);
        $this->assertStringContainsString('open(link, courseid)', $click);
        $this->assertStringContainsString("document.addEventListener('click', onClick);", $this->body('init'));
        $this->assertStringContainsString("more: '[data-region=\"dim-card-more\"]',", $this->source());
    }

    /**
     * The dialogue is core/modal: removed on close, the focus given back to the link, the plugin's class on its root.
     *
     * The class is what every rule of the stylesheet is qualified by and the token root it reads; core/modal
     * appends the root to the body, outside every element of the listing that declares a token.
     */
    public function test_the_dialogue_is_core_modal_with_the_plugins_root_class_and_the_focus_returned(): void {
        $source = $this->source();
        // Imported on the first press, not with the page: see test_core_modal_is_imported_on_the_first_press.
        $this->assertStringContainsString("const Modal = await import('core/modal');", $this->body('open'));
        $this->assertStringContainsString("const FRAME = 'theme_dimensions/category_course_modal';", $source);
        $this->assertStringContainsString("const SHEET = 'theme_dimensions/category_card_modal';", $source);
        $this->assertStringContainsString("const ROOT_CLASS = 'dim-cmodal';", $source);
        $open = $this->body('open');
        $this->assertStringContainsString('template: FRAME,', $open);
        $this->assertStringContainsString('removeOnClose: true,', $open);
        $this->assertStringContainsString('returnElement: link,', $open);
        $this->assertStringContainsString('root.classList.add(ROOT_CLASS);', $open);
        $this->assertStringContainsString('body.append(sheet);', $open);
        $this->assertStringContainsString('footer.append(actions);', $open);
    }

    /**
     * The dialogue is labelled by the title the reader sees, the frame's hidden placeholder removed.
     */
    public function test_the_dialogue_is_labelled_by_the_title_on_the_cover(): void {
        $open = $this->body('open');
        $this->assertStringContainsString("const labelledby = root.getAttribute('aria-labelledby');", $open);
        $this->assertStringContainsString('placeholder.remove();', $open);
        $this->assertStringContainsString('title.id = labelledby;', $open);
        $this->assertStringContainsString("title: '[data-region=\"title\"]',", $this->source(), 'Core\'s own title hook.');
    }

    /**
     * The footer's shadow follows the scroll of the sheet, and is set once the dialogue is on screen and once it is filled.
     */
    public function test_the_footer_is_raised_while_the_sheet_has_more_to_scroll(): void {
        $this->assertStringContainsString(
            'scroller.scrollTop + scroller.clientHeight < scroller.scrollHeight - SCROLL_SLACK',
            $this->body('raise')
        );
        $this->assertStringContainsString('footer.classList.toggle(RAISED_CLASS, more);', $this->body('raise'));
        $open = $this->body('open');
        $this->assertStringContainsString("body.addEventListener('scroll', () => raise(body, footer), {passive: true});", $open);
        $this->assertLessThan(strrpos($open, 'raise(body, footer);'), strpos($open, 'await modal.show();'));
        $this->assertLessThan(strrpos($open, 'raise(body, footer);'), strpos($open, 'await fill('));
    }

    /**
     * The dialogue opens at once with what the card holds, and only its content region is filled afterwards.
     *
     * The page's decision about the course - the buttons, the kicker - is read off the card's link, never asked of the
     * service; the cover, the pill and the title off the card's markup. The sheet is rendered with the skeleton before
     * core/modal shows it, and the answer replaces the content region alone, so the title holding the id the dialogue
     * is labelled by is never replaced. A failure is the content region's too: one sentence and the course's page.
     */
    public function test_the_dialogue_opens_with_the_cards_half_and_fills_its_content_region_alone(): void {
        $card = $this->body('fromCard');
        $reads = [
            'data.primary',
            'data.primaryUrl',
            'data.primaryClosed',
            'data.primaryIcon',
            'data.secondary',
            'data.secondaryUrl',
            'data.kicker',
            'SELECTORS.cover',
            'SELECTORS.pill',
            'SELECTORS.cardtitle',
        ];
        foreach ($reads as $read) {
            $this->assertStringContainsString($read, $card, "The card's half reads {$read}.");
        }
        $open = $this->body('open');
        $this->assertStringContainsString('const request = content(courseid);', $open);
        $this->assertStringContainsString('const parts = await render(Object.assign({}, head, {loading: true}));', $open);
        $this->assertLessThan(strpos($open, 'await fill('), strpos($open, 'await modal.show();'), 'Shown, then filled.');
        $this->assertLessThan(
            strpos($open, 'await modal.show();'),
            strpos($open, 'const request = content(courseid);'),
            'The request is under way before the dialogue is built.'
        );

        $fill = $this->body('fill');
        $this->assertStringContainsString('const region = body.querySelector(SELECTORS.content);', $fill);
        $this->assertStringContainsString('region.replaceWith(fresh);', $fill);
        $this->assertStringContainsString('await failure(link)', $fill);
        $this->assertStringContainsString("key: 'category_modal_failed'", $this->body('failure'));
        $this->assertStringContainsString('failedurl: link.href', $this->body('failure'));
        $this->assertNotSame('', get_string('category_modal_failed', 'theme_dimensions'));
    }

    /**
     * The content is asked for on intent, once per course for the page's life, and the press reuses what it finds.
     *
     * A pointer or the focus resting on the link for the cover film's own INTENT_DELAY starts the request; a finger is
     * gated out of both halves of the hover, because it brackets every tap with an over and an out (the repository's
     * CLAUDE.md, "Hover logic gates BOTH halves"). An answer is kept per course; a failure is forgotten, so the next
     * opening asks again.
     */
    public function test_the_content_is_asked_for_on_intent_and_kept_per_course(): void {
        $source = $this->source();
        $this->assertStringContainsString('const INTENT_DELAY = 180;', $source);
        $film = (string) file_get_contents($this->plugin_root() . '/amd/src/local/coverfilm.js');
        $this->assertStringContainsString('const INTENT_DELAY = 180;', $film, 'In step with the cover film\'s delay.');
        foreach (['onPointerOver', 'onPointerOut'] as $half) {
            $this->assertStringContainsString(
                "if (event.pointerType === 'touch') {\n        return;\n    }",
                $this->body($half),
                "{$half} leaves a finger alone."
            );
        }
        $this->assertStringContainsString('intend(link);', $this->body('onFocusIn'));
        $this->assertStringContainsString('forget();', $this->body('onFocusOut'));
        $intend = $this->body('intend');
        $this->assertStringContainsString('if (courseid === null || answers.has(courseid)) {', $intend);
        $this->assertStringContainsString('}, INTENT_DELAY),', $intend);
        $this->assertStringContainsString('content(courseid);', $intend);

        $content = $this->body('content');
        $this->assertStringContainsString('if (!answers.has(courseid)) {', $content, 'One request per course.');
        $this->assertStringContainsString("methodname: METHOD, args: {courseid: courseid}", $content);
        $this->assertStringContainsString('request.catch(() => answers.delete(courseid));', $content, 'A failure is forgotten.');
        $this->assertStringContainsString('return answers.get(courseid);', $content);
        $init = $this->body('init');
        foreach (['pointerover', 'pointerout', 'focusin', 'focusout'] as $event) {
            $this->assertStringContainsString("document.addEventListener('{$event}', ", $init);
        }
    }

    /**
     * The module calls one service, the dialogue's, and reads data attributes only.
     *
     * A class selector is a hook a stylesheet rename can silently break. The pill's classes are copied, not hooked.
     */
    public function test_it_calls_one_service_and_addresses_hooks_only(): void {
        $source = $this->source();
        $this->assertStringContainsString("const METHOD = 'theme_dimensions_get_course_dialogue';", $source);
        $this->assertSame(1, substr_count($source, 'methodname:'), 'One call, the dialogue\'s.');
        foreach (['fetch(', 'XMLHttpRequest', 'fetchOne'] as $call) {
            $this->assertStringNotContainsString($call, $source, 'No other way to the server: ' . $call);
        }
        $selectors = $this->body('SELECTORS');
        preg_match_all("~: '([^']+)',~", $selectors, $matches);
        $this->assertCount(10, $matches[1]);
        foreach ($matches[1] as $selector) {
            $this->assertMatchesRegularExpression(
                '~^\[data-region="[a-z-]+"\]$~',
                $selector,
                'A class selector here is a hook a stylesheet rename can silently break: ' . $selector
            );
        }
    }

    /**
     * Only a real account's page arms the module: never the guest's, never a visitor's (decision V1).
     *
     * The service it calls refuses the guest, so the arming sits inside the listing client's own guard, where the
     * indentation is twelve spaces; a visitor's "More information" is the link to the public landing page.
     */
    public function test_only_a_real_accounts_page_arms_the_module(): void {
        $renderer = (string) file_get_contents($this->plugin_root() . '/classes/output/core/course_renderer.php');
        $found = preg_match(
            "~\n        if \(isloggedin\(\) && !isguestuser\(\)\) \{\n(.*?)\n        \}\n~s",
            $renderer,
            $guard
        );
        $this->assertSame(1, $found, 'The listing client\'s guard is where it was.');
        $this->assertMatchesRegularExpression(
            "~^            \\\$this->page->requires->js_call_amd\\('theme_dimensions/categorycoursemodal', 'init'\\);$~m",
            $guard[1],
            'The logged-in shell arms it inside the real account\'s guard.'
        );
        $this->assertSame(1, substr_count($renderer, "js_call_amd('theme_dimensions/categorycoursemodal'"), 'Once.');
        $request = (string) file_get_contents($this->plugin_root() . '/classes/local/category/request.php');
        $this->assertStringNotContainsString(
            "js_call_amd('theme_dimensions/categorycoursemodal'",
            $request,
            'A visitor\'s "More information" is the link to the public landing page.'
        );
        // Control: the visitor's page still arms its own modules, so a missing call above is a decision.
        $this->assertStringContainsString("js_call_amd('theme_dimensions/covervideo', 'init');", $request);
    }

    /**
     * The committed build was made from the source that is committed beside it - exactly.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $this->assertSame(
            $this->source(),
            $this->built_from(),
            'amd/build is not this source: rebuild it with grunt amd and commit both files.'
        );
        $build = (string) file_get_contents($this->plugin_root() . '/amd/build/categorycoursemodal.min.js');
        $this->assertStringContainsString('dim-card-more', $build);
        $this->assertStringContainsString('theme_dimensions/category_course_modal', $build);
        $this->assertStringContainsString('theme_dimensions_get_course_dialogue', $build);
    }

    /**
     * core/modal is imported on the first press, and the press is pending until the dialogue's content is painted.
     *
     * Changes that must make it fail: the modal stack, the service's client or the template engine imported with the
     * page again (every reader pays for a dialogue most never open); the press not held as pending, so a reader of the
     * page (Behat) finds neither the dialogue nor its content between the press and the paint; the pending never
     * resolved, so the page never settles; the fill not awaited, so the pending ends on a skeleton.
     */
    public function test_core_modal_is_imported_on_the_first_press(): void {
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $this->source(), $imports);
        $this->assertSame(['core/pending'], $imports[1], 'Only core\'s pending tracker is imported with the page.');
        $click = $this->body('onClick');
        $this->assertStringContainsString("const pending = new Pending('theme_dimensions/categorycoursemodal:open');", $click);
        $this->assertStringContainsString('pending.resolve();', $click);
        $this->assertStringContainsString('.catch(report)', $click);
        $this->assertStringContainsString('await fill(body, head, request, link);', $this->body('open'));
        $this->assertStringContainsString("await import('core/notification');", $this->body('report'));
    }

    /**
     * A press on the rating block's "Read reviews" closes the dialogue and goes on to tool_courserating's listener.
     *
     * The trigger is the plugin's own (category_card_modal draws it to match its document listener), so the theme
     * opens nothing for it: the dialogue's root hears the press on its way up, closes the dialogue so two never stack,
     * and stops neither the press nor its default action, which the plugin's listener on the document prevents
     * itself. Changes that must make it fail: the listener gone, the close gone, or the press stopped on the way.
     */
    public function test_a_press_on_the_reviews_closes_the_dialogue_and_reaches_the_plugin(): void {
        $this->assertStringContainsString("reviews: '[data-region=\"dim-cmodal-reviews\"]',", $this->body('SELECTORS'));
        $open = $this->body('open');
        $found = preg_match(
            "~\n    root\.addEventListener\('click', \(event\) => \{\n(.*?)\n    \}\);~s",
            $open,
            $listener
        );
        $this->assertSame(1, $found, 'The dialogue\'s root listens for the press.');
        $this->assertStringContainsString('event.target.closest(SELECTORS.reviews)', $listener[1]);
        $this->assertStringContainsString('modal.destroy();', $listener[1], 'It closes and removes the dialogue.');
        foreach (['preventDefault', 'stopPropagation', 'stopImmediatePropagation'] as $stop) {
            $this->assertStringNotContainsString($stop, $listener[1], 'The press goes on to the plugin: ' . $stop);
        }
        // Control: the root listener is the one place; the page-wide press handler still prevents a card link's default.
        $this->assertStringContainsString('event.preventDefault();', $this->body('onClick'));

        $template = (string) file_get_contents($this->plugin_root() . '/templates/category_card_modal.mustache');
        $this->assertMatchesRegularExpression(
            '~<span class="tool_courserating-cfield"><button type="button" class="[^"]*\btool_courserating-ratings '
                . 'tool_courserating-ratings-courseid-\{\{courseid\}\}" data-region="dim-cmodal-reviews">~',
            $template,
            'The trigger matches the plugin\'s listener and carries the hook the dialogue closes on.'
        );
    }
}
