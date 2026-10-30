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
 * Theme Dimensions - Tests for the invariants of the cover film's client module
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_dimensions\local\category;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Pins the invariants of the cover film by reading its source: amd/src/local/coverfilm.js, the film, and
 * amd/src/covervideo.js, the entry the page arms, which imports the film on the first intent towards a cover.
 *
 * Same shape and reasoning as {@see amd_source_test} and {@see daterange_source_test}: plugin CI
 * runs no JavaScript unit tests, so the properties of this module whose loss costs a wrong page
 * rather than a screenshot are pinned by reading its source, each assertion scoped to the function
 * it is about. The build-currency assertion at the foot is the only one that reads what a browser
 * is actually served; without it every other assertion here could be green over a module no
 * browser runs.
 *
 * @package    theme_dimensions
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class covervideo_source_test extends \basic_testcase {
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
        $path = $this->plugin_root() . '/amd/src/local/coverfilm.js';
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * The source the committed build was actually made from.
     *
     * @return string
     */
    private function built_from(): string {
        $path = $this->plugin_root() . '/amd/build/local/coverfilm.min.js.map';
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
        $source = $this->source();
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $source, $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in the module.");
        $this->assertNotSame('', trim($matches[1]), "The body of {$name} is empty.");
        return $matches[1];
    }

    /**
     * The extractor really extracts one function and not the file around it.
     *
     * Without this, every scoped assertion below would also pass if body() returned the whole
     * module.
     */
    public function test_the_extractor_returns_one_function_and_not_the_file(): void {
        $start = $this->body('start');
        $this->assertStringContainsString('video.muted = true;', $start);
        $this->assertStringNotContainsString(
            'requestFullscreen',
            $start,
            'The extractor is returning more than one function.'
        );
        $this->assertStringContainsString('requestFullscreen', $this->body('expand'));
    }

    /**
     * Every listener is on the document: one delegated set serves every card of the page.
     *
     * The count assertion holds it: every addEventListener in the module is on the document, so
     * no card carries a binding of its own.
     *
     * Media events do not bubble, so the timeupdate listener is registered in the capture phase,
     * where an ancestor is called on the way down to the target. Without the flag the progress bar
     * never moves and nothing errors.
     */
    public function test_every_listener_is_on_the_document(): void {
        $init = $this->body('init');
        $events = ['pointerover', 'pointerout', 'focusout', 'click', 'keydown', 'fullscreenchange'];
        foreach ($events as $event) {
            $this->assertMatchesRegularExpression(
                "~document\.addEventListener\('" . $event . "', on~",
                $init,
                'The ' . $event . ' listener is no longer delegated on the document.'
            );
        }
        $this->assertStringContainsString(
            "document.addEventListener('timeupdate', onTime, true);",
            $init,
            'timeupdate does not bubble: without the capture flag this listener is never called at all.'
        );
        $source = $this->source();
        $this->assertSame(
            substr_count($source, 'addEventListener('),
            substr_count($source, 'document.addEventListener('),
            'A listener on anything but the document is a per-card binding.'
        );
        $this->assertStringNotContainsString(
            'createElement(',
            $source,
            'Every control of the cluster is the template\'s: a control built here would have no string.'
        );
        // And arming twice registers one set of listeners, not two.
        $this->assertMatchesRegularExpression('~if \(listening\) \{\s*return;\s*\}\s*listening = true;~', $init);
    }

    /**
     * A hover starts a film only where a hover means something, and only after the intent delay.
     *
     * A touch screen reports a hover for the tap it is about to deliver, so a preview gated on
     * hover alone would start on every press, the play control's included. The pointer half of
     * the query tells the two apart; the play control is the path a finger has.
     *
     * The delay equals the account's shell's PEEK_DELAY (180 ms, js/esm/src/listing/account.ts): a pointer
     * crossing a grid on its way elsewhere should start nothing, and both features read the same intent off the
     * same gesture.
     *
     * Changes that must make it fail: calling start(band) directly instead of through the timer,
     * which starts one film per card a passing pointer crosses.
     */
    public function test_a_hover_starts_a_film_only_where_a_hover_means_something(): void {
        $source = $this->source();
        $this->assertStringContainsString(
            "const HOVER_QUERY = '(hover: hover) and (pointer: fine)';",
            $source,
            'Both halves: a coarse pointer answers "hover" to a tap, and a fine one that cannot hover has no preview.'
        );
        $this->assertStringContainsString('const INTENT_DELAY = 180;', $source, 'The hover peek\'s own number.');
        $this->assertStringContainsString(
            'const PEEK_DELAY = 180;',
            (string) file_get_contents(dirname(__DIR__, 3) . '/js/esm/src/listing/account.ts'),
            'And the peek\'s, which it stays in step with.'
        );
        $this->assertMatchesRegularExpression(
            '~^const hoverable = \(\) => window\.matchMedia\(HOVER_QUERY\)\.matches;$~m',
            $source
        );

        $over = $this->body('onOver');
        $this->assertMatchesRegularExpression(
            '~if \(!hoverable\(\) \|\| stilled\(\)\) \{~',
            $over,
            'The hover path is the only one that asks these two questions.'
        );
        $this->assertMatchesRegularExpression(
            '~timer = window\.setTimeout\(\(\) => start\(band\), INTENT_DELAY\);~',
            $over,
            'A pointer passing through starts nothing: it has to rest.'
        );
    }

    /**
     * A reader who asked for less movement is never played at, and keeps every control.
     *
     * prefers-reduced-motion asks for less movement, not fewer features, so the query is asked on
     * the hover path and nowhere else: the play control, the sound control and full screen work as
     * they do for anybody, because a reader who presses play has asked for the film. The count is
     * the load-bearing half: a guard copied into the press path would leave a control that does
     * nothing when pressed, which no assertion about onOver() alone could see.
     *
     * Changes that must make it fail: dropping `|| stilled()` from onOver(), or adding the same
     * guard to toggle().
     */
    public function test_a_reader_who_asked_for_less_movement_is_never_played_at(): void {
        $source = $this->source();
        $this->assertStringContainsString("const MOTION_QUERY = '(prefers-reduced-motion: reduce)';", $source);
        $this->assertMatchesRegularExpression(
            '~^const stilled = \(\) => window\.matchMedia\(MOTION_QUERY\)\.matches;$~m',
            $source
        );
        $this->assertStringContainsString('stilled()', $this->body('onOver'));
        $this->assertSame(
            1,
            substr_count($source, 'stilled()'),
            'The question is asked on the hover path and nowhere else: pressing play is asking for the film.'
        );
    }

    /**
     * A touch pointer reaches neither half of the hover.
     *
     * HOVER_QUERY describes the device; this predicate describes the event, and a guard on the
     * query alone covers only the entering half. A finger delivers pointerover as it touches down
     * and pointerout as it lifts, before the click that follows. Read as "the pointer left the
     * card", the lift would pause, rewind and forget the film, and the press a moment later would
     * land on a card at rest: a second tap of the play control would restart the film instead of
     * pausing it, and a tap of the sound control would restart it too.
     *
     * A pen is answered like a finger: hovering is a property of the hardware rather than of the
     * platform, and a pen pressing the cluster is served by the path a finger has. An event
     * carrying no pointerType at all - a legacy mouse event, or one a test dispatched - is a
     * mouse, which leaves the desktop page as it was.
     *
     * The behaviour itself is pinned by the Behat scenario "A finger pauses the film in place, and
     * the sound control leaves it paused", which dispatches the real event sequence in a browser.
     *
     * Changes that must make it fail: removing the hovers() check from onOut() alone.
     */
    public function test_a_touch_pointer_reaches_neither_half_of_the_hover(): void {
        $source = $this->source();
        $this->assertMatchesRegularExpression(
            "~^const hovers = \(event\) => event\.pointerType !== 'touch' && event\.pointerType !== 'pen';$~m",
            $source,
            'Both are pointers with no hover state; a mouse, and an event with no pointerType, are not.'
        );

        $over = $this->body('onOver');
        $this->assertMatchesRegularExpression(
            '~\A\s*if \(!hovers\(event\)\) \{~',
            $over,
            'onOver() asks first: a finger touching down has no hover state to record.'
        );
        foreach (['hovered', 'timer'] as $state) {
            $this->assertLessThan(
                strpos($over, $state),
                strpos($over, 'hovers(event)'),
                'onOver() touches ' . $state . ' before it has asked whether this pointer can hover at all.'
            );
        }

        $out = $this->body('onOut');
        $this->assertMatchesRegularExpression(
            '~\A\s*if \(!hovers\(event\)\) \{~',
            $out,
            'onOut() asks first, and this is the half that shipped: a lift is not a departure.'
        );
        foreach (['hovered', 'timer', 'stop('] as $state) {
            $this->assertLessThan(
                strpos($out, $state),
                strpos($out, 'hovers(event)'),
                'onOut() reaches ' . $state . ' for a finger\'s lift, which is the press arriving rather than leaving.'
            );
        }
    }

    /**
     * Every start is muted, whatever the card before it did.
     *
     * A page of cards is walked over rather than opened, so a film that inherited the last card's
     * sound would speak to somebody who only moved a pointer across it. Browsers refuse to
     * autoplay an unmuted film anyway, so the inherited state would not even produce sound: it
     * would leave the still in place and read as a preview that is broken.
     *
     * resume() is the one path that does not remute, and it is the control case: the same reader,
     * seconds later, pressing play on a film whose sound they themselves asked for.
     *
     * Changes that must make it fail: setting `video.muted` to false in start().
     */
    public function test_every_start_is_muted_whatever_the_card_before_it_did(): void {
        $start = $this->body('start');
        $this->assertStringContainsString(
            'video.muted = true;',
            $start,
            'A film that inherits the last card\'s sound speaks to somebody who only moved a pointer.'
        );
        $this->assertMatchesRegularExpression(
            '~mark\(band\.querySelector\(SELECTORS\.mute\), true, ICONS\.silent, ICONS\.sound\);~',
            $start,
            'And the control says so, in both the spellings a reader may be given.'
        );
        $resume = $this->body('resume');
        $this->assertStringNotContainsString(
            'muted',
            $resume,
            'Resuming is the same reader seconds later: it must not undo the sound they asked for.'
        );
    }

    /**
     * One film plays at a time, which is one SOUND at a time.
     *
     * Two muted previews at once is noise on a page of cards; two films one of which has been
     * unmuted is a page that cannot be made quiet, because the control that would quiet it is on
     * a card the reader has already left.
     *
     * Changes that must make it fail: turning the condition into `if (false)`, which leaves every
     * card a pointer crosses playing.
     */
    public function test_one_film_plays_at_a_time(): void {
        $start = $this->body('start');
        $this->assertMatchesRegularExpression(
            '~if \(playing && playing !== band\) \{\s*(?://[^\n]*\n\s*)*stop\(playing\);~',
            $start,
            'Starting one film stops the other, sound and all.'
        );
        $this->assertMatchesRegularExpression('~^\s*playing = band;$~m', $start);
    }

    /**
     * Leaving pauses, rewinds and brings the still back - and so does scrolling the card away.
     *
     * The still is what the card is: a reader who walked past must find the picture they would
     * have found had they never hovered, at the frame it starts on rather than at whatever frame
     * the pointer happened to leave it on.
     *
     * The containment guard matters: pointerout fires for every element the pointer steps off,
     * including each button of the cluster, so without it the film would stop the moment a
     * pointer moved from the band onto the play control that is inside it.
     */
    public function test_leaving_pauses_rewinds_and_brings_the_still_back(): void {
        $stop = $this->body('stop');
        $this->assertMatchesRegularExpression(
            '~band\.classList\.remove\(PLAYING_CLASS\);~',
            $stop,
            'The still comes back by losing the class that faded it, not by moving anything.'
        );
        $this->assertMatchesRegularExpression(
            '~video\.pause\(\);\s*video\.currentTime = 0;~',
            $stop,
            'Rewound, so the next reader meets the film at its first frame.'
        );
        $this->assertStringContainsString('observer.unobserve(band);', $stop);

        /* Both controls go back, not only the play one. The next start is muted whatever this
           reader asked for, so a stopped card whose sound control still reads "sound on" would
           report its own last press rather than what the card will do, and under (hover: none)
           the cluster is on screen permanently, so the wrong glyph would never go away. */
        $this->assertMatchesRegularExpression(
            '~mark\(band\.querySelector\(SELECTORS\.mute\), true, ICONS\.silent, ICONS\.sound\);~',
            $stop,
            'A stopped card still saying "sound on" reports the last press and not the next start.'
        );

        $out = $this->body('onOut');
        $this->assertMatchesRegularExpression(
            '~if \(event\.relatedTarget instanceof Node && band\.contains\(event\.relatedTarget\)\) \{~',
            $out,
            'pointerout fires for every element the pointer steps off, the cluster\'s buttons included.'
        );
        $this->assertMatchesRegularExpression('~if \(playing === band\) \{\s*stop\(band\);~', $out);

        // And the one observer watches the playing band alone, which is what stops a film scrolled away.
        $watch = $this->body('watch');
        $this->assertMatchesRegularExpression(
            '~if \(!entry\.isIntersecting && entry\.target === playing\) \{\s*stop\(entry\.target\);~',
            $watch
        );
        $this->assertStringContainsString('observer.observe(band);', $watch);
    }

    /**
     * The play control is the touch and the keyboard path, and it holds a frame rather than losing it.
     *
     * Under (hover: none) nothing auto-plays at all, so this control is the only way in a finger
     * has; a keyboard reaches it because it is a real button of the template rather than anything
     * built here. Pressing it while a film runs pauses in place and keeps the frame: the reader
     * asked to look at what is on screen, and bringing the still back would take away the thing
     * they stopped to see. Leaving the card is what rewinds.
     */
    public function test_the_play_control_is_the_touch_and_keyboard_path(): void {
        $click = $this->body('onClick');
        foreach (['play' => 'toggle', 'mute' => 'sound', 'fullscreen' => 'expand'] as $hook => $call) {
            $this->assertMatchesRegularExpression(
                '~if \(target\.closest\(SELECTORS\.' . $hook . '\)\) \{\s*' . $call . '\(band\);~',
                $click,
                'The ' . $hook . ' control is matched by its data-action, so one rule serves every card.'
            );
        }

        $toggle = $this->body('toggle');
        $this->assertMatchesRegularExpression(
            '~if \(playing !== band\) \{\s*start\(band\);~',
            $toggle,
            'A press on a card that is not playing starts it, which is the whole touch path.'
        );
        $this->assertMatchesRegularExpression('~if \(video\.paused\) \{\s*resume\(band\);~', $toggle);
        $this->assertMatchesRegularExpression(
            '~video\.pause\(\);\s*mark\(band\.querySelector\(SELECTORS\.play\), false~',
            $toggle,
            'A pause holds the frame: the class stays and the still does not come back.'
        );
        $this->assertStringNotContainsString(
            'currentTime',
            $toggle,
            'Pausing is not rewinding; leaving the card is what rewinds.'
        );

        // Escape is scoped to the cluster, so it never takes a film away from a page-wide press.
        $key = $this->body('onKey');
        $this->assertMatchesRegularExpression("~if \(event\.key !== 'Escape'\) \{~", $key);
        $this->assertStringContainsString('!target.closest(SELECTORS.controls)', $key);
    }

    /**
     * The sound control leaves a film paused in place paused, and starts one only from rest.
     *
     * Only one of the two halves may start anything. A card at rest starts its film on this press:
     * the press is the user gesture a browser wants before it plays anything audible, and start()
     * mutes whatever it starts, so a press that only changed the glyph would produce no film and no
     * sound. A film paused in place stays paused: the reader paused it to hold that frame, and on a
     * touch screen, where the controls sit side by side and the cluster never goes away, the sound
     * control is the likeliest of the two to be pressed by accident.
     *
     * Changes that must make it fail: an `|| video.paused` term in the branch that starts the film.
     */
    public function test_the_sound_control_leaves_a_film_paused_in_place_paused(): void {
        $sound = $this->body('sound');
        $this->assertMatchesRegularExpression(
            '~if \(playing !== band\) \{\s*start\(band\);\s*\}~',
            $sound,
            'A card at rest starts on this press; anything else this branch tests is a resumed film.'
        );
        $this->assertStringNotContainsString(
            'paused',
            $sound,
            'A film paused in place is not started again: the reader asked to hold that frame.'
        );
        $this->assertStringNotContainsString(
            'resume(',
            $sound,
            'This control changes the sound; the play control is what carries a film on.'
        );
        $this->assertMatchesRegularExpression(
            '~video\.muted = !video\.muted;\s*mark\(band\.querySelector\(SELECTORS\.mute\), video\.muted, ICONS\.silent~',
            $sound,
            'The flag and the glyph are written from one call site, in the state the film really ended in.'
        );
        $this->assertStringNotContainsString(
            'SELECTORS.play',
            $sound,
            'And the play control is left saying what it said: a paused film is still a paused film.'
        );
    }

    /**
     * A pause that lands while the film is still loading holds it; it is not a refusal.
     *
     * A second press of the play control before the film has started pauses it while play() is
     * still pending, and the browser rejects that play() with an AbortError. Read as a refusal,
     * the rejection stopped the band - rewound and forgotten - so the sound control's next press
     * met a card at rest and started the film the reader had just paused. The Behat scenario
     * "A finger pauses the film in place" meets this whenever the fixture loads slowly.
     *
     * Changes that must make it fail: reading every play() rejection as a refusal.
     */
    public function test_a_pause_while_the_film_loads_is_not_a_refusal(): void {
        $refused = $this->body('refused');
        $guard = "error && error\\.name === 'AbortError' && playing === band && video && video\\.paused";
        $this->assertMatchesRegularExpression(
            '~if \(' . $guard . '\) \{\s*return;\s*\}~',
            $refused,
            'A pause of our own interrupting a pending play() leaves the band adopted and paused.'
        );
        $this->assertStringContainsString('stop(band);', $refused, 'Any other rejection brings the still back.');
        $starters = ['start', 'resume'];
        foreach ($starters as $name) {
            $this->assertStringContainsString(
                '.catch((error) => refused(band, error));',
                $this->body($name),
                "{$name}() hands its rejection to the one reader of it."
            );
        }
        $this->assertDoesNotMatchRegularExpression(
            '~\.catch\(\(\) =>~',
            $this->source(),
            'No play() rejection is read without asking why it happened.'
        );
    }

    /**
     * The browser's own control bar is drawn in exactly one place: full screen.
     *
     * Over a card that bar would cover most of the picture, which is why the element carries no
     * controls attribute at all and the template draws its own three buttons instead. At full
     * screen the card is gone and the browser's bar is the right control, and writing the
     * attribute in one place only is what keeps it from surviving the way out.
     *
     * The iPhone has no Fullscreen API on an element: a video goes to the system player, which
     * brings its own controls. That branch is a fallback and not an alternative, so it is asked
     * for second.
     */
    public function test_the_browsers_own_bar_is_drawn_in_full_screen_and_nowhere_else(): void {
        $source = $this->source();
        $this->assertSame(
            1,
            substr_count($source, "setAttribute('controls'"),
            'One place writes the attribute; anything else is a bar that outlives the state that earned it.'
        );
        $full = $this->body('onFullscreen');
        $this->assertMatchesRegularExpression(
            '~if \(video !== full\) \{\s*video\.removeAttribute\(\'controls\'\);~',
            $full,
            'Leaving takes the bar off again, from whichever film has it.'
        );
        $this->assertMatchesRegularExpression(
            '~if \(full instanceof Element && full\.matches\(SELECTORS\.video\)\) \{\s*full\.setAttribute~',
            $full,
            'And only a film of this theme is given one: the page has other fullscreen elements.'
        );
        $this->assertStringContainsString(
            "video.removeAttribute('controls');",
            $this->body('stop'),
            'A film that stops while full screen was refused keeps no bar.'
        );

        $expand = $this->body('expand');
        $this->assertMatchesRegularExpression(
            '~if \(video\.requestFullscreen\) \{\s*video\.requestFullscreen\(\)\.catch\(ignored\);~',
            $expand,
            'A refusal is not an error worth a dialogue: the card simply stays a card.'
        );
        $this->assertMatchesRegularExpression(
            '~if \(video\.webkitEnterFullscreen\) \{~',
            $expand,
            'The iPhone fallback, asked for second because it is a fallback.'
        );
    }

    /**
     * Full screen adopts the film before it asks for the screen.
     *
     * The control is reached with the film stopped wherever nothing auto-plays - under
     * prefers-reduced-motion and on touch devices - and full screen on a film nothing started is a
     * black rectangle; a film then started from the browser's own bar would outlive full screen
     * untracked, under the still, with nothing able to stop it. The reasoning, and why the
     * adoption goes through toggle(), is in expand()'s docblock in amd/src/covervideo.js.
     *
     * Changes that must make it fail: treating the film as already running, which leaves the
     * adoption unreachable.
     */
    public function test_full_screen_adopts_a_film_that_is_not_running(): void {
        $expand = $this->body('expand');
        $this->assertMatchesRegularExpression(
            '~const running = playing === band && !video\.paused;\s*if \(!running\) \{\s*toggle\(band\);~',
            $expand,
            'Full screen on a film nothing started is a black rectangle, and nothing can stop it afterwards.'
        );
        $this->assertLessThan(
            strpos($expand, 'requestFullscreen'),
            strpos($expand, 'toggle(band);'),
            'The adoption comes first: the screen is asked for once the film is the one being tracked.'
        );
    }

    /**
     * The module asks the server for nothing at all, which is what lets the anonymous shell have it.
     *
     * The anonymous shell runs none of this theme's web services, and this is the one client
     * module it loads. A module with no fetch in it is inside that rule rather than an exception
     * to it, so the assertion is "no import" rather than "no core/ajax": the property that cannot
     * be eroded one import at a time.
     */
    public function test_the_module_asks_the_server_for_nothing(): void {
        $source = $this->source();
        $this->assertDoesNotMatchRegularExpression(
            '~^import ~m',
            $source,
            'This module imports nothing: the anonymous shell runs none of this theme\'s web services.'
        );
        foreach (['core/ajax', 'fetch(', 'XMLHttpRequest', 'navigator.sendBeacon'] as $call) {
            $this->assertStringNotContainsString($call, $source, 'The cover film asks the server for nothing: ' . $call);
        }
    }

    /**
     * The cluster is revealed by this module's own mark, and never by the listing's.
     *
     * dim-js is the account's listing mark (js/esm/src/listing/account.ts), and it also hides the toolbar's
     * no-JavaScript "Filter" submit. This module runs where that one never does - the whole anonymous shell and the
     * guest's pages - and there that submit is the only filter control the reader has. Writing
     * dim-js from here would take it away and put nothing in its place.
     */
    public function test_the_cluster_is_revealed_by_this_modules_own_mark(): void {
        $source = $this->source();
        $this->assertStringContainsString("const BODY_CLASS = 'dim-cover-js';", $source);
        $this->assertStringContainsString('document.body.classList.add(BODY_CLASS);', $this->body('init'));
        $this->assertStringNotContainsString(
            "'dim-js'",
            $source,
            'The vitrine module\'s mark hides the anonymous shell\'s only filter control.'
        );
        // Every hook this module addresses is a data attribute, so a design rename cannot disarm it.
        $selectors = $this->body('SELECTORS');
        preg_match_all("~: '([^']+)',~", $selectors, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $selector) {
            $this->assertMatchesRegularExpression(
                '~^\[data-(region|action)="dim-cover-~',
                $selector,
                'A class selector here is a hook a stylesheet rename can silently break: ' . $selector
            );
        }
    }

    /**
     * Both shells arm the module, and the logged-in one arms it for everybody.
     *
     * An arming inside the vitrine module's guard, which skips the guest, would leave the guest's
     * pages without films, and the anonymous shell draws the same cards for visitors. Neither
     * failure errors, and nothing else compares a module's name with the place it is armed, so the
     * two calls are read out of the two files.
     *
     * The indentation is what makes the assertion bite: at eight spaces the call is a statement of
     * the method, and inside the vitrine module's `if` it would be at twelve.
     */
    public function test_both_shells_arm_the_module(): void {
        $renderer = (string) file_get_contents($this->plugin_root() . '/classes/output/core/course_renderer.php');
        $this->assertMatchesRegularExpression(
            "~^        \\\$this->page->requires->js_call_amd\\('theme_dimensions/covervideo', 'init'\\);$~m",
            $renderer,
            'The logged-in shell arms it outside the vitrine module\'s guard, or the root directory has no films.'
        );
        $request = (string) file_get_contents($this->plugin_root() . '/classes/local/category/request.php');
        $this->assertMatchesRegularExpression(
            "~^        \\\$PAGE->requires->js_call_amd\\('theme_dimensions/covervideo', 'init'\\);$~m",
            $request,
            'The anonymous shell draws the same cards, and a visitor gets the cluster too.'
        );
    }

    /**
     * The committed build was made from the source that is committed beside it - exactly.
     *
     * Moodle serves amd/build and never amd/src, so a source edit that was never rebuilt is
     * invisible in a browser, and every other assertion in this file reads amd/src.
     */
    public function test_the_committed_build_was_made_from_the_committed_source(): void {
        $this->assertSame(
            $this->source(),
            $this->built_from(),
            'amd/build is not this source: rebuild it with grunt amd and commit both files.'
        );
        $build = (string) file_get_contents($this->plugin_root() . '/amd/build/local/coverfilm.min.js');
        $this->assertStringContainsString('dim-cover-media', $build);
        $this->assertStringContainsString('dim-card__media--playing', $build);
    }

    /**
     * The entry's source and the source its committed build was made from.
     *
     * @return string[] The source, then the build map's source.
     */
    private function entry(): array {
        $source = (string) file_get_contents($this->plugin_root() . '/amd/src/covervideo.js');
        $map = json_decode((string) file_get_contents($this->plugin_root() . '/amd/build/covervideo.min.js.map'), true);
        return [$source, (string) ($map['sourcesContent'][0] ?? '')];
    }

    /**
     * The body of one top-level arrow function of the entry.
     *
     * @param string $name The name it is bound to.
     * @return string
     */
    private function entry_body(string $name): string {
        $pattern = '~\n(?:export )?const ' . preg_quote($name, '~') . ' = [^\n]*\{\n(.*?)\n\};~s';
        $found = preg_match($pattern, $this->entry()[0], $matches);
        $this->assertSame(1, $found, "No top-level function named {$name} in the entry.");
        return $matches[1];
    }

    /**
     * The page arms the entry, and the entry imports the film on the first intent towards a cover.
     *
     * Changes that must make it fail: the film imported with the page again; the body left unmarked, so the cluster
     * stays hidden until a cover is approached and a finger has no control to press; the first-use listeners left in
     * the bubbling phase, where a card's own listener can stop the intent; the load not pending, so a reader of the
     * page (Behat) finds a film half-armed; the listeners left on once the film listens.
     */
    public function test_the_entry_imports_the_film_on_first_intent(): void {
        [$source, $built] = $this->entry();
        $this->assertSame($source, $built, 'amd/build/covervideo is not this source: rebuild it with grunt amd.');
        preg_match_all("~^import [^\n]* from '([^']+)';$~m", $source, $imports);
        $this->assertSame(['core/pending'], $imports[1], 'The entry imports core\'s pending tracker with the page, nothing else.');
        preg_match_all("~\\bimport\\('([^']+)'\\)~", $source, $dynamic);
        $this->assertSame(['theme_dimensions/local/coverfilm'], $dynamic[1]);

        $init = $this->entry_body('init');
        $this->assertStringContainsString('document.body.classList.add(BODY_CLASS);', $init);
        $this->assertStringContainsString("INTENTS.forEach((type) => document.addEventListener(type, onIntent, true));", $init);
        $this->assertStringContainsString("document.addEventListener('click', onPress, true);", $init);
        $this->assertStringContainsString("const BODY_CLASS = 'dim-cover-js';", $source);
        $this->assertStringContainsString("const INTENTS = ['pointerover', 'focusin', 'pointerdown'];", $source);

        $load = $this->entry_body('load');
        $this->assertStringContainsString("new Pending('theme_dimensions/covervideo:load')", $load);
        $this->assertStringContainsString('Film.init();', $load);
        $this->assertStringContainsString("INTENTS.forEach((type) => document.removeEventListener(type, onIntent, true));", $load);
        $this->assertStringContainsString("document.removeEventListener('click', onPress, true);", $load);
        $this->assertStringContainsString('.finally(() => pending.resolve());', $load);
    }

    /**
     * A press that arrives before the film is held and given back; a hover is told again only while it holds.
     *
     * Changes that must make it fail: a press on a control before the film has loaded left to do nothing (a button
     * that does nothing); the press given back to another element; a hover replayed after the pointer has left, which
     * starts a film nothing will stop.
     */
    public function test_an_early_press_is_held_and_a_hover_replayed_only_while_it_holds(): void {
        $press = $this->entry_body('onPress');
        $this->assertStringContainsString('if (ready || !control) {', $press);
        $this->assertStringContainsString('event.preventDefault();', $press);
        $this->assertStringContainsString('event.stopImmediatePropagation();', $press);
        $this->assertStringContainsString('control.click();', $press);

        $intent = $this->entry_body('onIntent');
        $this->assertStringContainsString("event.type === 'pointerover' && where.matches(':hover') && target.isConnected", $intent);
        $this->assertStringContainsString("new PointerEvent('pointerover', {", $intent);
        $this->assertStringContainsString('pointerType: event.pointerType,', $intent);
    }
}
