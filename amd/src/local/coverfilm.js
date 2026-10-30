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
 * The cover film of a category card: a muted preview a pointer starts by resting on it.
 *
 * The page without this module is complete and is what most readers get: a still, drawn from the
 * category's cover image or from its hero image, over the colour band. The film sits under that
 * still with no controls attribute and preload="none", so a reader without JavaScript sees the
 * picture and nothing half-working, and no byte of any film is fetched until something here asks
 * for one.
 *
 * It calls no web service and imports nothing, which is what lets the anonymous shell load it:
 * that page runs none of this theme's AJAX. It is not armed by the page: theme_dimensions/covervideo
 * is, and imports this module on the first intent towards a cover (a pointer over one, a focus or a
 * finger in one), so a page whose covers nobody approaches never loads it.
 *
 * The rules:
 *
 * - A hover starts a film only on a pointer that can hover, (hover: hover) and (pointer: fine):
 *   a touch screen reports a hover for the tap that is about to become a press, so it never
 *   starts one and the play control is its path instead. Both halves of a hover are gated, on
 *   the event: a touch pointer fires pointerout when the finger lifts, before the click, and read
 *   as a departure that lift would stop the film the press is about to pause or unmute.
 *   hovers() is the predicate, and onOver() and onOut() both ask it before anything else.
 * - A pointer must rest for INTENT_DELAY before a film starts: a pointer crossing a grid on its
 *   way somewhere else should start nothing.
 * - prefers-reduced-motion: reduce never auto-plays. The controls all still work: the reader has
 *   asked for less movement, not for less feature.
 * - Every start is muted, whatever the card before it did. Sound is a press, never an inheritance.
 * - One film at a time. Starting one stops the other, including the other's sound.
 * - Leaving pauses, rewinds and brings the still back, and so does scrolling the card away.
 * - The browser's own control bar is drawn in exactly one place: full screen.
 *
 * @module     theme_dimensions/local/coverfilm
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Object} Every hook this module reads, all of them data attributes of one template. */
const SELECTORS = {
    media: '[data-region="dim-cover-media"]',
    video: '[data-region="dim-cover-video"]',
    controls: '[data-region="dim-cover-controls"]',
    bar: '[data-region="dim-cover-bar"]',
    glyph: '[data-region="dim-cover-glyph"]',
    play: '[data-action="dim-cover-play"]',
    mute: '[data-action="dim-cover-mute"]',
    fullscreen: '[data-action="dim-cover-fullscreen"]',
};

/**
 * @type {Number} Milliseconds a pointer must rest on a card before its film starts. Equal to
 * the account's shell's PEEK_DELAY (js/esm/src/listing/account.ts) on purpose: both read the same
 * intent off the same gesture, so keep the two in step (covervideo_source_test holds both).
 */
const INTENT_DELAY = 180;

/**
 * @type {String} A pointer that can hover and is precise. A touch screen answers "hover" to the
 * tap it is about to deliver, so the pointer half of this pair keeps a press from being read as a
 * hover; the play control is the path a finger and a keyboard have.
 *
 * It describes the device and not the event, which is why hovers() exists beside it: a touch
 * device still delivers pointerover and pointerout for every tap, the second when the finger
 * lifts, so this query alone would refuse to start a film for a finger and still let the lift
 * stop one.
 */
const HOVER_QUERY = '(hover: hover) and (pointer: fine)';

/** @type {String} The reader who has asked the platform for less movement. */
const MOTION_QUERY = '(prefers-reduced-motion: reduce)';

/** @type {String} The class the band wears while its film is on screen; the still fades under it. */
const PLAYING_CLASS = 'dim-card__media--playing';

/**
 * @type {String} The body class that reveals the control cluster, written here and nowhere else.
 *
 * Deliberately not the showcase's dim-js, which is the account's listing mark and also hides the
 * toolbar's no-JavaScript "Filter" submit: this module is also armed where that one is not (the
 * anonymous shell and the guest's page), and there that submit is the only filter control a
 * reader has.
 */
const BODY_CLASS = 'dim-cover-js';

/** @type {Object} The Font Awesome glyphs the two toggles swap between. */
const ICONS = {
    play: 'fa-play',
    pause: 'fa-pause',
    sound: 'fa-volume-high',
    silent: 'fa-volume-xmark',
};

/** @type {HTMLElement|null} The band whose film is on screen, playing or paused in place. */
let playing = null;

/** @type {HTMLElement|null} The band the pointer is inside. */
let hovered = null;

/** @type {Number} The intent delay's timer. */
let timer = 0;

/** @type {IntersectionObserver|null} Watches the playing band and nothing else. */
let observer = null;

/** @type {Boolean} Whether the listeners are already on the document. */
let listening = false;

/**
 * Something that swallows a rejection without pretending it was handled.
 *
 * @return {Boolean} Always false.
 */
const ignored = () => false;

/**
 * The media band an event landed in, if it landed in one at all.
 *
 * @param {EventTarget|null} node What the event was aimed at.
 * @return {HTMLElement|null} The band, or null.
 */
const media = (node) => (node instanceof Element ? node.closest(SELECTORS.media) : null);

/**
 * The film of a band.
 *
 * @param {HTMLElement|null} band The media band.
 * @return {HTMLVideoElement|null} The video element, or null on a card that has no film.
 */
const film = (band) => (band ? band.querySelector(SELECTORS.video) : null);

/**
 * Whether a hover means anything on this device.
 *
 * @return {Boolean}
 */
const hoverable = () => window.matchMedia(HOVER_QUERY).matches;

/**
 * Whether the reader has asked the platform for less movement.
 *
 * @return {Boolean}
 */
const stilled = () => window.matchMedia(MOTION_QUERY).matches;

/**
 * Whether an event came from a pointer that has a hover state at all.
 *
 * A finger and a stylus do not: the browser reports a pointerover as they touch down and a
 * pointerout as they lift, both of them parts of one press rather than of any hover. A pen is
 * answered alike because a pen that hovers is a feature of the hardware, not of the platform,
 * and a pen user pressing the cluster is served by the same path a finger is. An event with no
 * pointerType at all - a legacy mouse event, or one a test dispatched - is a mouse, which is the
 * reading that leaves the desktop page exactly as it was.
 *
 * @param {PointerEvent} event The event.
 * @return {Boolean} True for a pointer whose hover means something.
 */
const hovers = (event) => event.pointerType !== 'touch' && event.pointerType !== 'pen';

/**
 * Write a toggle's state onto it: the flag a screen reader is given, and the glyph an eye reads.
 *
 * Both halves, from one call site, because a control that says one thing to one reader and
 * another to the next is worse than one that says nothing.
 *
 * @param {HTMLElement|null} button The control.
 * @param {Boolean} pressed Whether it is pressed.
 * @param {String} on The glyph of the pressed state.
 * @param {String} off The glyph of the unpressed one.
 * @return {void}
 */
const mark = (button, pressed, on, off) => {
    if (!button) {
        return;
    }
    button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    const glyph = button.querySelector(SELECTORS.glyph);
    if (glyph) {
        glyph.classList.remove(on, off);
        glyph.classList.add(pressed ? on : off);
    }
};

/**
 * Draw how far through the film is.
 *
 * @param {HTMLElement} band The media band.
 * @param {Number} ratio How much of it has played, 0 to 1.
 * @return {void}
 */
const paint = (band, ratio) => {
    const bar = band.querySelector(SELECTORS.bar);
    if (bar) {
        bar.style.width = (Math.min(1, Math.max(0, ratio)) * 100) + '%';
    }
};

/**
 * Stop a band's film: pause it, rewind it and bring the still back.
 *
 * Both controls are put back, not only the play one: the next start is muted whatever this
 * reader asked for, so a sound control still reading "sound on" would report its own last press
 * rather than what the card will do, and on a touch device the cluster is always on screen. The
 * film's own muted property is start()'s to set and nothing reads it before then, so only the
 * control is put back.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const stop = (band) => {
    const video = film(band);
    band.classList.remove(PLAYING_CLASS);
    if (video) {
        video.pause();
        video.currentTime = 0;
        video.removeAttribute('controls');
    }
    mark(band.querySelector(SELECTORS.play), false, ICONS.pause, ICONS.play);
    mark(band.querySelector(SELECTORS.mute), true, ICONS.silent, ICONS.sound);
    paint(band, 0);
    if (observer) {
        observer.unobserve(band);
    }
    if (playing === band) {
        playing = null;
    }
};

/**
 * Watch the playing band, so that scrolling it away stops it.
 *
 * One observer, watching the playing band alone: only one film plays at a time, so no other band
 * needs watching.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const watch = (band) => {
    if (!window.IntersectionObserver) {
        return;
    }
    if (!observer) {
        observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting && entry.target === playing) {
                    stop(entry.target);
                }
            });
        });
    }
    observer.observe(band);
};

/**
 * A play() that did not end in a playing film.
 *
 * A browser may refuse to play at all - autoplay refused, a file it cannot decode - and the still
 * is then what a reader keeps. But a pause() arriving while the film is still loading rejects the
 * pending play() too, with an AbortError, and that is the reader holding the frame with a second
 * press, not a refusal: stopping on it would rewind the film and forget it, so the sound control's
 * next press would meet a card at rest and start it again. A pause of our own leaves the band
 * adopted and its video paused; anything else is stopped.
 *
 * @param {HTMLElement} band The media band.
 * @param {Error} error Why the play() was rejected.
 * @return {void}
 */
const refused = (band, error) => {
    const video = film(band);
    if (error && error.name === 'AbortError' && playing === band && video && video.paused) {
        return;
    }
    stop(band);
};

/**
 * Start a band's film from wherever it stands, muted.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const start = (band) => {
    const video = film(band);
    if (!video) {
        return;
    }
    if (playing && playing !== band) {
        // One film at a time: two previews at once is noise, and two sounds at once is worse.
        stop(playing);
    }
    playing = band;
    band.classList.add(PLAYING_CLASS);
    /* Every start is muted, whatever the card before it did. A page of cards is walked over, not
       opened, so the next film a pointer touches would otherwise speak without being asked - and
       browsers refuse to autoplay an unmuted one anyway, which would leave the still in place and
       read as a card whose preview is broken. */
    video.muted = true;
    mark(band.querySelector(SELECTORS.mute), true, ICONS.silent, ICONS.sound);
    mark(band.querySelector(SELECTORS.play), true, ICONS.pause, ICONS.play);
    watch(band);
    const started = video.play();
    if (started) {
        started.catch((error) => refused(band, error));
    }
};

/**
 * Carry on from where the film was paused, without muting it again.
 *
 * The one path that does not remute: it is the same reader seconds later, who paused a film whose
 * sound they had asked for, and pressing play again is not a new card.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const resume = (band) => {
    const video = film(band);
    if (!video) {
        return;
    }
    mark(band.querySelector(SELECTORS.play), true, ICONS.pause, ICONS.play);
    const started = video.play();
    if (started) {
        started.catch((error) => refused(band, error));
    }
};

/**
 * The play control: the touch and keyboard path in, and the way to hold a frame still.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const toggle = (band) => {
    const video = film(band);
    if (!video) {
        return;
    }
    if (playing !== band) {
        start(band);
        return;
    }
    if (video.paused) {
        resume(band);
        return;
    }
    /* A pause holds the frame rather than bringing the still back: the reader asked to look at
       what is on screen. Leaving the card is what rewinds it. */
    video.pause();
    mark(band.querySelector(SELECTORS.play), false, ICONS.pause, ICONS.play);
};

/**
 * The sound control, which has two halves and must not confuse them.
 *
 * On a card at rest it starts the film as well: the press is the user gesture a browser wants
 * before it plays anything audible, and start() mutes whatever it starts, so without the start
 * the press would change a glyph and play nothing.
 *
 * On a film paused in place it changes the sound and nothing else: the reader paused to hold
 * that frame, and on a touch screen, where the two controls sit side by side and are always on
 * screen, this is the likeliest accidental press. The play control is left saying what it said.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const sound = (band) => {
    const video = film(band);
    if (!video) {
        return;
    }
    if (playing !== band) {
        start(band);
    }
    video.muted = !video.muted;
    mark(band.querySelector(SELECTORS.mute), video.muted, ICONS.silent, ICONS.sound);
};

/**
 * The full screen control.
 *
 * It adopts the film first. Full screen on a film that is not running is a black rectangle, and
 * the control is reached in that state wherever nothing auto-plays: under prefers-reduced-motion
 * and on touch devices. Without the adoption, a film started from the browser's own bar (see
 * onFullscreen()) would keep running after full screen ends, hidden under the still and with
 * nothing able to stop it: onOut(), onFocusOut() and the IntersectionObserver all test
 * `playing === band`, and only start() calls watch(). The adoption goes through toggle() so that
 * a film paused in place is resumed and keeps the reader's own sound.
 *
 * @param {HTMLElement} band The media band.
 * @return {void}
 */
const expand = (band) => {
    const video = film(band);
    if (!video) {
        return;
    }
    const running = playing === band && !video.paused;
    if (!running) {
        toggle(band);
    }
    if (video.requestFullscreen) {
        video.requestFullscreen().catch(ignored);
        return;
    }
    if (video.webkitEnterFullscreen) {
        /* The iPhone has no Fullscreen API on an element at all: a video goes to the system's
           own player, which brings its own controls and its own way back out. Nothing below is
           reached there, which is why the controls attribute is not this branch's business. */
        video.webkitEnterFullscreen();
    }
};

/**
 * A pointer arrived somewhere.
 *
 * @param {PointerEvent} event The event.
 * @return {void}
 */
const onOver = (event) => {
    if (!hovers(event)) {
        /* A finger's contact is not a hover: it is the first half of a press, and the cluster is
           the path that press has. Nothing here is touched for it - see onOut() for the half
           that matters. */
        return;
    }
    const band = media(event.target);
    if (!band || band === hovered) {
        // Either nowhere near a card, or moving between the elements of the band it is already in.
        return;
    }
    hovered = band;
    window.clearTimeout(timer);
    if (!hoverable() || stilled()) {
        /* A coarse pointer reports a hover for the tap it is about to deliver, and a reader who
           asked for less movement gets none they did not press for. Both still have the cluster. */
        return;
    }
    timer = window.setTimeout(() => start(band), INTENT_DELAY);
};

/**
 * A pointer left somewhere.
 *
 * @param {PointerEvent} event The event.
 * @return {void}
 */
const onOut = (event) => {
    if (!hovers(event)) {
        /* A touch pointer fires pointerout when the finger lifts, before the click it belongs to.
           Read as a departure, it would stop and rewind the film that press is about to pause or
           unmute. Only a pointer with a hover state can leave. */
        return;
    }
    const band = media(event.target);
    if (!band || band !== hovered) {
        return;
    }
    if (event.relatedTarget instanceof Node && band.contains(event.relatedTarget)) {
        // Still inside this band: pointerout fires for every element the pointer steps off.
        return;
    }
    hovered = null;
    window.clearTimeout(timer);
    if (playing === band) {
        stop(band);
    }
};

/**
 * The keyboard walked out of a band.
 *
 * Only the keyboard's exit stops a film, and only while no pointer is resting on the card: a
 * reader who tabbed to the play control, pressed it and then tabbed on has left it, while one
 * whose pointer is still on the card has not.
 *
 * @param {FocusEvent} event The event.
 * @return {void}
 */
const onFocusOut = (event) => {
    const band = media(event.target);
    if (!band || playing !== band || hovered === band) {
        return;
    }
    if (event.relatedTarget instanceof Node && band.contains(event.relatedTarget)) {
        return;
    }
    stop(band);
};

/**
 * A press anywhere in the document.
 *
 * @param {MouseEvent} event The event.
 * @return {void}
 */
const onClick = (event) => {
    const band = media(event.target);
    const target = event.target instanceof Element ? event.target : null;
    if (!band || !target) {
        return;
    }
    if (target.closest(SELECTORS.play)) {
        toggle(band);
        return;
    }
    if (target.closest(SELECTORS.mute)) {
        sound(band);
        return;
    }
    if (target.closest(SELECTORS.fullscreen)) {
        expand(band);
    }
};

/**
 * Escape on a control of the cluster stops the film it belongs to.
 *
 * @param {KeyboardEvent} event The event.
 * @return {void}
 */
const onKey = (event) => {
    if (event.key !== 'Escape') {
        return;
    }
    const target = event.target instanceof Element ? event.target : null;
    const band = media(target);
    if (!band || !target.closest(SELECTORS.controls) || playing !== band) {
        return;
    }
    stop(band);
};

/**
 * The film moved on: draw how far through it is.
 *
 * @param {Event} event The event, whose target is the video element.
 * @return {void}
 */
const onTime = (event) => {
    const video = event.target;
    const band = media(video);
    if (!band || !Number.isFinite(video.duration) || video.duration <= 0) {
        return;
    }
    paint(band, video.currentTime / video.duration);
};

/**
 * Full screen was entered or left.
 *
 * The browser's own control bar is drawn in exactly one place, and this is it: over a 150 px card
 * that bar is most of the picture, and it is why the element carries no controls attribute at all.
 * Leaving takes it off again, from whichever film has it, so a card never keeps a bar it earned
 * in another state.
 *
 * @return {void}
 */
const onFullscreen = () => {
    const full = document.fullscreenElement;
    document.querySelectorAll(SELECTORS.video + '[controls]').forEach((video) => {
        if (video !== full) {
            video.removeAttribute('controls');
        }
    });
    if (full instanceof Element && full.matches(SELECTORS.video)) {
        full.setAttribute('controls', 'controls');
    }
};

/**
 * Arm the module: one set of listeners on the document, for every card of the page.
 *
 * @return {void}
 */
export const init = () => {
    if (listening) {
        return;
    }
    listening = true;
    /* The cluster and the progress bar are hidden until the body carries this class. The entry
       (theme_dimensions/covervideo) writes it first, since it holds a press until this module has
       loaded; writing it here too keeps this module whole on its own. */
    document.body.classList.add(BODY_CLASS);
    document.addEventListener('pointerover', onOver);
    document.addEventListener('pointerout', onOut);
    document.addEventListener('focusout', onFocusOut);
    document.addEventListener('click', onClick);
    document.addEventListener('keydown', onKey);
    // The timeupdate event does not bubble, so this listener captures it on the way down.
    document.addEventListener('timeupdate', onTime, true);
    document.addEventListener('fullscreenchange', onFullscreen);
};
