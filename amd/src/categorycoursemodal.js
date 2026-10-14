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
 * The course dialogue of the category showcase: "More information" opens a core/modal.
 *
 * Every course card draws its own dialogue into an inert template element (category_card_modal,
 * from the card's own context, on the server and in every card a client module renders). This
 * module intercepts a press on the card's details summary, copies that template's two parts into
 * a core/modal built from category_course_modal - the sheet into the body, the actions into the
 * footer - and shows it. Without this module, or on a card carrying no template, the press is the
 * native disclosure's and opens its panel in place, which is the whole of the fallback.
 *
 * core/modal brings the focus trap, Escape, the close button and the focus given back to the
 * summary; this module adds what core's markup cannot say on its own: the dialogue's own class on
 * the root it appends to the body, the id core's root is labelled by on the title the cover
 * carries (core's header keeps only its close button here, so that the title scrolls away with
 * its picture), and the footer's shadow while the sheet has more to scroll.
 *
 * It calls no web service and reads nothing of a session, so it is armed on both shells and for
 * everybody, the visitor included. Its listener is on the document because cards arrive long
 * after it is armed: a page appended by "Load more", a filter answered in place, a panel.
 *
 * @module     theme_dimensions/categorycoursemodal
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Notification from 'core/notification';

/** @type {Object} The hooks this module reads: data attributes only, never a class name. */
const SELECTORS = {
    summary: '[data-region="dim-card-details"] > summary',
    card: '[data-region="dim-card"]',
    parts: 'template[data-region="dim-card-modal"]',
    sheet: '[data-region="dim-cmodal-sheet"]',
    actions: '[data-region="dim-cmodal-actions"]',
    // Core's own title hook, on the title the cover carries: what core/modal's root is labelled by.
    title: '[data-region="title"]',
};

/** @type {String} The frame: core/modal with the plugin's class and a header holding the close button alone. */
const TEMPLATE = 'theme_dimensions/category_course_modal';

/** @type {String} The dialogue's own class, on the root core/modal appends to the body: its tokens and every rule. */
const ROOT_CLASS = 'dim-cmodal';

/** @type {String} The footer's class while the sheet above it has more to scroll. */
const RAISED_CLASS = 'dim-cmodal--raised';

/** @type {Number} Pixels of sub-pixel rounding a sheet scrolled to its end may still report. */
const SCROLL_SLACK = 2;

/** @type {Boolean} Whether the document listener is already registered. */
let armed = false;

/** @type {Boolean} Whether a dialogue is being built: a second press meanwhile opens nothing more. */
let opening = false;

/**
 * The dialogue a summary's card carries, or null where it carries none.
 *
 * @param {HTMLElement} summary The card's details summary.
 * @return {HTMLTemplateElement|null}
 */
const partsOf = (summary) => {
    const card = summary.closest(SELECTORS.card);
    return card ? card.querySelector(SELECTORS.parts) : null;
};

/**
 * Lift the footer off the sheet while the sheet has more to scroll, and settle it at the end.
 *
 * @param {HTMLElement} scroller The modal's body, which is what scrolls.
 * @param {HTMLElement} footer The modal's footer.
 * @return {void}
 */
const raise = (scroller, footer) => {
    const more = scroller.scrollTop + scroller.clientHeight < scroller.scrollHeight - SCROLL_SLACK;
    footer.classList.toggle(RAISED_CLASS, more);
};

/**
 * Build the dialogue of one card and show it.
 *
 * @param {HTMLElement} summary The summary that was pressed, which gets the focus back on close.
 * @param {HTMLTemplateElement} parts The card's dialogue.
 * @return {Promise} Resolving once the dialogue is on screen.
 */
const open = async(summary, parts) => {
    const content = parts.content.cloneNode(true);
    const sheet = content.querySelector(SELECTORS.sheet);
    const actions = content.querySelector(SELECTORS.actions);
    if (!sheet || !actions) {
        return;
    }
    const title = sheet.querySelector(SELECTORS.title);
    const modal = await Modal.create({
        template: TEMPLATE,
        templateContext: {title: title ? title.textContent : ''},
        removeOnClose: true,
        returnElement: summary,
    });
    const root = modal.getRoot()[0];
    root.classList.add(ROOT_CLASS);
    /* The root is labelled by the frame's hidden title in the header, which holds the close button
       besides; the course's own title on the cover takes that id instead, so the dialogue's one
       heading is the one a reader sees, and it scrolls away with the cover it is printed on. */
    const labelledby = root.getAttribute('aria-labelledby');
    const placeholder = labelledby ? root.querySelector('#' + CSS.escape(labelledby)) : null;
    if (title && placeholder) {
        placeholder.remove();
        title.id = labelledby;
    }
    const body = modal.getBody()[0];
    const footer = modal.getFooter()[0];
    body.append(sheet);
    footer.append(actions);
    body.addEventListener('scroll', () => raise(body, footer), {passive: true});
    await modal.show();
    raise(body, footer);
};

/**
 * Tell assistive technology that a card's summary opens a dialogue, wherever this module will open one.
 *
 * @param {Element} scope The element whose summaries to mark.
 * @return {void}
 */
const mark = (scope) => {
    scope.querySelectorAll(SELECTORS.summary).forEach((summary) => {
        if (partsOf(summary)) {
            summary.setAttribute('aria-haspopup', 'dialog');
        }
    });
};

/**
 * A press anywhere on the page: a card's summary opens its dialogue instead of its disclosure.
 *
 * Preventing the click's default is what keeps the native disclosure shut - a summary toggles its
 * details as the click's default action, from the keyboard (Enter, Space) as well as the pointer.
 * A card with no dialogue drawn is left to the disclosure.
 *
 * @param {Event} event The click.
 * @return {void}
 */
const onClick = (event) => {
    const summary = event.target instanceof Element ? event.target.closest(SELECTORS.summary) : null;
    const parts = summary ? partsOf(summary) : null;
    if (!parts) {
        return;
    }
    event.preventDefault();
    if (opening) {
        return;
    }
    opening = true;
    open(summary, parts)
        .catch(Notification.exception)
        .finally(() => {
            opening = false;
        });
};

/**
 * Arm the course dialogue on this page. Idempotent.
 *
 * @return {void}
 */
export const init = () => {
    if (armed) {
        return;
    }
    armed = true;
    document.addEventListener('click', onClick);
    mark(document);
    // Cards a client module renders later are marked as they arrive.
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
            mark(node);
        }
    }))).observe(document.body, {childList: true, subtree: true});
};
