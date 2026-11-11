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
 * The landing page's summary, cut after its third paragraph behind a "See more" toggle.
 *
 * The cut is measured, not counted: a summary written in the editor is as often one block broken by
 * <br> as a run of <p>, so a paragraph ends at the bottom of a leaf block or at a line break, and the
 * text is clipped at the third such end. A summary with no more than three paragraphs is never cut and
 * shows no toggle. The page loads cut every time: the choice is not stored.
 *
 * With JavaScript off the summary is whole and the toggle stays hidden, so nothing is out of reach.
 *
 * @module     theme_dimensions/landingsummary
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Number} How many paragraphs the summary shows before its toggle. */
export const PARAGRAPHS = 3;

/** @type {Number} Two ends closer than this many pixels are one paragraph end (a <br> closing a <p>). */
const SAME_END_PX = 4;

/**
 * @type {Number} The fade the stylesheet draws under the cut, in rem: text left under the cut that fits in it shows
 * through the fade and is not worth a toggle. Keep it equal to the 3rem of `scss/landing.scss`'s cut rule.
 */
const SLACK_REM = 3;

/** @type {String} The elements whose bottom ends a paragraph. */
const ENDS = 'p, li, h1, h2, h3, h4, h5, h6, blockquote, pre, figure, table, br';

/** @type {Object} Every hook this module reads. */
const SELECTORS = {
    root: '[data-region="dimensions-landing-summary"]',
    text: '[data-region="dimensions-landing-summarytext"]',
    toggle: '[data-region="dimensions-landing-summarytoggle"]',
    label: '[data-region="dimensions-landing-summarylabel"]',
};

/** @type {String} The class that clips the text at the cut. */
const CUT_CLASS = 'dimensions-landing__summary--cut';

/** @type {String} The custom property the stylesheet reads the cut's height from. */
const CUT_PROPERTY = '--dim-landing-summary-cut';

/**
 * The bottom of one paragraph end, in viewport coordinates.
 *
 * A <br> has no box of its own in every engine, so its line is read through a range around it.
 *
 * @param {HTMLElement} element A leaf block or a line break.
 * @return {Number} The bottom edge in pixels.
 */
const bottomOf = (element) => {
    if (element.tagName !== 'BR') {
        return element.getBoundingClientRect().bottom;
    }
    const range = document.createRange();
    range.selectNode(element);
    const rects = range.getClientRects();
    return rects.length ? rects[rects.length - 1].bottom : element.getBoundingClientRect().bottom;
};

/**
 * Where the cut falls, measured from the top of the text, or null when the text needs none.
 *
 * Only leaf blocks count (a <ul> ends where its last <li> does), and a line break right at the end of a
 * block is the same end as the block's.
 *
 * @param {HTMLElement} text The summary's text.
 * @return {?Number} The cut's height in pixels.
 */
export const cutHeight = (text) => {
    const top = text.getBoundingClientRect().top;
    const ends = [];
    text.querySelectorAll(ENDS).forEach((element) => {
        if (element.tagName !== 'BR' && element.querySelector(ENDS)) {
            return;
        }
        const end = Math.round(bottomOf(element) - top);
        if (!ends.length || end - ends[ends.length - 1] > SAME_END_PX) {
            ends.push(end);
        }
    });
    if (ends.length < PARAGRAPHS) {
        return null;
    }
    const cut = ends[PARAGRAPHS - 1];
    const slack = SLACK_REM * parseFloat(getComputedStyle(document.documentElement).fontSize);
    return text.scrollHeight - cut > slack ? cut : null;
};

/**
 * Bring the toggle's label, chevron and aria state in line with the text.
 *
 * @param {HTMLElement} toggle The toggle.
 * @param {Boolean} expanded Whether the whole text shows.
 */
const syncToggle = (toggle, expanded) => {
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const label = toggle.querySelector(SELECTORS.label);
    if (label) {
        label.textContent = expanded ? toggle.dataset.labelLess : toggle.dataset.labelMore;
    }
    const icon = toggle.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-chevron-down', !expanded);
        icon.classList.toggle('fa-chevron-up', expanded);
    }
};

/**
 * Cut the summary after its third paragraph and wire its toggle.
 */
export const init = () => {
    const root = document.querySelector(SELECTORS.root);
    const text = root ? root.querySelector(SELECTORS.text) : null;
    const toggle = root ? root.querySelector(SELECTORS.toggle) : null;
    if (!text || !toggle) {
        return;
    }

    let expanded = false;

    /* The cut is measured again whenever the text could have reflowed: a narrower column moves every line
       break, and an image that finishes loading pushes the paragraphs under it down. Clipping does not
       move the text's children, so the measure holds while the text is cut. */
    const measure = () => {
        const cut = cutHeight(text);
        if (cut === null) {
            root.classList.remove(CUT_CLASS);
            toggle.hidden = true;
            return;
        }
        text.style.setProperty(CUT_PROPERTY, `${cut}px`);
        toggle.hidden = false;
        root.classList.toggle(CUT_CLASS, !expanded);
    };

    toggle.addEventListener('click', () => {
        expanded = !expanded;
        root.classList.toggle(CUT_CLASS, !expanded);
        syncToggle(toggle, expanded);
        // Closing a long text from its end leaves the reader far below it: bring the toggle back to them.
        if (!expanded && toggle.getBoundingClientRect().top < 0) {
            toggle.scrollIntoView({block: 'nearest'});
        }
    });

    if (typeof ResizeObserver !== 'undefined') {
        let width = text.clientWidth;
        new ResizeObserver(() => {
            if (text.clientWidth !== width) {
                width = text.clientWidth;
                measure();
            }
        }).observe(text);
    }
    text.querySelectorAll('img, iframe').forEach((media) => {
        media.addEventListener('load', measure, {once: true});
    });

    syncToggle(toggle, expanded);
    measure();
};
