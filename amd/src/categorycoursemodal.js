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
 * "More information" is a link to the card's own page, and that is the whole fallback: a modified press (a new
 * tab, a new window), the guest and the visitor, whose pages never arm this module, follow it. A plain press
 * opens the dialogue at once with what the card already holds - the cover, the state pill and the title from
 * its markup, the kicker and the two buttons the server wrote onto the link (category_card) - and a skeleton;
 * then the content region alone is replaced with theme_dimensions_get_course_dialogue's answer, rendered from
 * the same template, category_card_modal. A failed request keeps the header and the buttons and says so in one
 * sentence, with the course's page.
 *
 * The request starts on intent (a pointer or the focus resting on the link for INTENT_DELAY), and answers are
 * kept per course for the page's life. core/modal, core/ajax and core/templates are imported on first use: most
 * readers never open a dialogue. The press is pending until the content is painted or the failure said.
 *
 * The content may carry tool_courserating's "Read reviews", that plugin's own trigger (its document listener
 * opens the reviews popup): this module closes the dialogue on that press and lets the event through, so two
 * dialogues never stack.
 *
 * The service refuses the guest, so course_renderer arms this module for a real account only. The listeners are
 * on the document because cards arrive after it is armed: a page appended by "Load more", a filter answered in
 * place, a panel.
 *
 * @module     theme_dimensions/categorycoursemodal
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Pending from 'core/pending';

/** @type {Object} The hooks this module reads: data attributes only, never a class name. */
const SELECTORS = {
    more: '[data-region="dim-card-more"]',
    card: '[data-region="dim-card"]',
    cover: '[data-region="dim-card-cover"]',
    pill: '[data-region="dim-card-pill"]',
    cardtitle: '[data-region="dim-card-title"]',
    sheet: '[data-region="dim-cmodal-sheet"]',
    content: '[data-region="dim-cmodal-content"]',
    actions: '[data-region="dim-cmodal-actions"]',
    // The rating block's trigger of tool_courserating's reviews popup, which that plugin's own listener opens.
    reviews: '[data-region="dim-cmodal-reviews"]',
    // Core's own title hook, on the title the cover carries: what core/modal's root is labelled by.
    title: '[data-region="title"]',
};

/** @type {String} The frame: core/modal with the plugin's class and a header holding the close button alone. */
const FRAME = 'theme_dimensions/category_course_modal';

/** @type {String} The sheet and the actions, rendered twice: with the skeleton, then with the content. */
const SHEET = 'theme_dimensions/category_card_modal';

/** @type {String} The service answering a course's content. */
const METHOD = 'theme_dimensions_get_course_dialogue';

/** @type {String} The dialogue's own class, on the root core/modal appends to the body: its tokens and every rule. */
const ROOT_CLASS = 'dim-cmodal';

/** @type {String} The footer's class while the sheet above it has more to scroll. */
const RAISED_CLASS = 'dim-cmodal--raised';

/**
 * @type {String} The card's own class on its state pill, which the dialogue's pill does not wear: the rest of the
 * pill's classes are the design system's pair, copied as they are.
 */
const CARD_PILL_CLASS = 'dim-card__badge';

/** @type {Number} Pixels of sub-pixel rounding a sheet scrolled to its end may still report. */
const SCROLL_SLACK = 2;

/**
 * @type {Number} Milliseconds a pointer or the focus must rest on the link before its content is asked for: a
 * pointer crossing the grid on its way elsewhere asks nothing. The cover film's own delay, in step with
 * amd/src/local/coverfilm.js, so the card answers a resting pointer once.
 */
const INTENT_DELAY = 180;

/** @type {Boolean} Whether the document listeners are already registered. */
let armed = false;

/** @type {Boolean} Whether a dialogue is being built: a second press meanwhile opens nothing more. */
let opening = false;

/** @type {Map<Number, Promise>} Each course's answer, or its request in flight, for the page's life. */
const answers = new Map();

/** @type {Object|null} The link a pointer or the focus rests on, and its timer. */
let intent = null;

/**
 * The card's "More information" link an event reached, or null.
 *
 * @param {EventTarget} target The event's target.
 * @return {HTMLAnchorElement|null}
 */
const linkOf = (target) => (target instanceof Element ? target.closest(SELECTORS.more) : null);

/**
 * The course a link opens, or null where its card names none.
 *
 * @param {HTMLElement} link The card's link.
 * @return {Number|null}
 */
const courseOf = (link) => {
    const card = link.closest(SELECTORS.card);
    const courseid = card ? Number(card.dataset.courseid) : NaN;
    return Number.isInteger(courseid) && courseid > 0 ? courseid : null;
};

/**
 * A course's content: the answer kept, the request in flight, or a new request.
 *
 * A failed request is forgotten, so the next opening asks again; an answer is kept for the page's life.
 *
 * @param {Number} courseid The course.
 * @return {Promise} Resolving with the service's answer.
 */
const content = (courseid) => {
    if (!answers.has(courseid)) {
        const request = import('core/ajax').then((Ajax) => Ajax.call([{methodname: METHOD, args: {courseid: courseid}}])[0]);
        request.catch(() => answers.delete(courseid));
        answers.set(courseid, request);
    }
    return answers.get(courseid);
};

/**
 * Forget a pointer or a focus resting on a link.
 *
 * @return {void}
 */
const forget = () => {
    if (intent) {
        window.clearTimeout(intent.timer);
        intent = null;
    }
};

/**
 * A pointer or the focus came to rest on a link: ask for its content and the dialogue's templates after INTENT_DELAY.
 *
 * @param {HTMLElement} link The card's link.
 * @return {void}
 */
const intend = (link) => {
    if (intent && intent.link === link) {
        return;
    }
    forget();
    const courseid = courseOf(link);
    if (courseid === null || answers.has(courseid)) {
        return;
    }
    intent = {
        link: link,
        timer: window.setTimeout(() => {
            intent = null;
            content(courseid);
            import('core/templates')
                .then((Templates) => Templates.prefetchTemplates([FRAME, SHEET]))
                .catch(() => undefined);
        }, INTENT_DELAY),
    };
};

/**
 * A pointer came over an element: over a link, it may be intent.
 *
 * A finger brackets every tap with an over and an out, and its press asks for the content itself, so both
 * halves are gated by the pointer that sent the event ({@see onPointerOut}).
 *
 * @param {PointerEvent} event The pointerover.
 * @return {void}
 */
const onPointerOver = (event) => {
    if (event.pointerType === 'touch') {
        return;
    }
    const link = linkOf(event.target);
    if (link) {
        intend(link);
    }
};

/**
 * A pointer left an element: leaving the link it rested on, it was not intent.
 *
 * @param {PointerEvent} event The pointerout.
 * @return {void}
 */
const onPointerOut = (event) => {
    if (event.pointerType === 'touch') {
        return;
    }
    const link = linkOf(event.target);
    if (!link || !intent || intent.link !== link) {
        return;
    }
    if (event.relatedTarget instanceof Node && link.contains(event.relatedTarget)) {
        return;
    }
    forget();
};

/**
 * The focus reached an element: reaching a link, it is intent.
 *
 * @param {FocusEvent} event The focusin.
 * @return {void}
 */
const onFocusIn = (event) => {
    const link = linkOf(event.target);
    if (link) {
        intend(link);
    }
};

/**
 * The focus left an element: leaving the link it rested on, it was not intent.
 *
 * @param {FocusEvent} event The focusout.
 * @return {void}
 */
const onFocusOut = (event) => {
    const link = linkOf(event.target);
    if (link && intent && intent.link === link) {
        forget();
    }
};

/**
 * What the dialogue takes from the card, the page's decision about the course: never asked of the service.
 *
 * The cover, the pill and the title are read from the card's markup; the kicker and the buttons from the
 * attributes the server wrote onto the link from the card's modal keys. The pill's classes are copied, not
 * hooked: they are the design system's pair the dialogue's pill wears too.
 *
 * @param {HTMLElement} link The card's link.
 * @return {Object} The sheet's context without its content.
 */
const fromCard = (link) => {
    const card = link.closest(SELECTORS.card);
    const cover = card ? card.querySelector(SELECTORS.cover) : null;
    const pill = card ? card.querySelector(SELECTORS.pill) : null;
    const title = card ? card.querySelector(SELECTORS.cardtitle) : null;
    const icon = pill ? pill.querySelector('i') : null;
    const data = link.dataset;
    const primary = data.primary ? {
        label: data.primary,
        url: data.primaryUrl || null,
        disabled: data.primaryClosed !== undefined,
        note: data.primaryClosed || '',
        icon: data.primaryIcon || '',
    } : null;
    const secondary = data.secondary ? {label: data.secondary, url: data.secondaryUrl || ''} : null;
    return {
        fullname: title ? title.textContent.trim() : '',
        imageurl: cover ? cover.getAttribute('src') : '',
        hasimage: Boolean(cover),
        badge: pill ? {
            classes: Array.from(pill.classList).filter((name) => name !== CARD_PILL_CLASS).join(' '),
            icon: icon ? Array.from(icon.classList).filter((name) => name !== 'fa').join(' ') : '',
            label: pill.textContent.trim(),
        } : null,
        kicker: data.kicker || '',
        haskicker: Boolean(data.kicker),
        primary: primary,
        hasprimary: primary !== null,
        secondary: secondary,
        hassecondary: secondary !== null,
    };
};

/**
 * Render the sheet and the actions into detached elements.
 *
 * @param {Object} context The sheet's context.
 * @return {Promise<DocumentFragment>} The rendered parts.
 */
const render = async(context) => {
    const Templates = await import('core/templates');
    const {html} = await Templates.renderForPromise(SHEET, context);
    const holder = document.createElement('template');
    holder.innerHTML = html;
    return holder.content;
};

/**
 * What the content region says when the course's content could not be fetched: one sentence and the course's page.
 *
 * @param {HTMLAnchorElement} link The card's link, whose page is the course's.
 * @return {Promise<Object>} The failure's keys of the sheet's context.
 */
const failure = async(link) => {
    const Str = await import('core/str');
    const [label, page] = await Str.getStrings([
        {key: 'category_modal_failed', component: 'theme_dimensions'},
        {key: 'category_modal_coursepage', component: 'theme_dimensions'},
    ]);
    return {failed: true, failedlabel: label, failedlink: page, failedurl: link.href};
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
 * Fill the open dialogue's content region with the answer, or with the failure's sentence.
 *
 * Only the region is replaced: the title above it holds the id the dialogue is labelled by.
 *
 * @param {HTMLElement} body The modal's body.
 * @param {Object} head What the dialogue took from the card.
 * @param {Promise} request The course's content.
 * @param {HTMLAnchorElement} link The card's link.
 * @return {Promise} Resolving once the region is painted.
 */
const fill = async(body, head, request, link) => {
    let context;
    try {
        context = Object.assign({}, head, await request, {loading: false, failed: false});
    } catch (error) {
        context = Object.assign({}, head, {loading: false}, await failure(link));
    }
    const fresh = (await render(context)).querySelector(SELECTORS.content);
    const region = body.querySelector(SELECTORS.content);
    if (fresh && region) {
        region.replaceWith(fresh);
    }
};

/**
 * Open one card's dialogue at once, then fill it.
 *
 * @param {HTMLAnchorElement} link The link that was pressed, which gets the focus back on close.
 * @param {Number} courseid The course.
 * @return {Promise} Resolving once the content is painted.
 */
const open = async(link, courseid) => {
    forget();
    const request = content(courseid);
    const head = fromCard(link);
    const parts = await render(Object.assign({}, head, {loading: true}));
    const sheet = parts.querySelector(SELECTORS.sheet);
    const actions = parts.querySelector(SELECTORS.actions);
    if (!sheet || !actions) {
        return;
    }
    const title = sheet.querySelector(SELECTORS.title);
    const Modal = await import('core/modal');
    const modal = await Modal.create({
        template: FRAME,
        templateContext: {title: head.fullname},
        removeOnClose: true,
        returnElement: link,
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
    /* The reviews are tool_courserating's popup, opened by that plugin's listener on the document from its own
       trigger in the rating block: this dialogue closes on the way, so two dialogues never stack, and the press
       goes on to the document untouched. destroy(), as core's own close button does under removeOnClose: hide()
       alone would leave the closed dialogue in the page. */
    root.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest(SELECTORS.reviews)) {
            modal.destroy();
        }
    });
    await modal.show();
    // On screen: another card may be pressed once this one is closed, whatever its content is still doing.
    opening = false;
    raise(body, footer);
    await fill(body, head, request, link);
    raise(body, footer);
};

/**
 * Tell assistive technology that a card's link opens a dialogue.
 *
 * @param {Element} scope The element whose links to mark.
 * @return {void}
 */
const mark = (scope) => {
    scope.querySelectorAll(SELECTORS.more).forEach((link) => {
        if (courseOf(link) !== null) {
            link.setAttribute('aria-haspopup', 'dialog');
        }
    });
};

/**
 * Tell the reader that the dialogue could not be built, through core's own exception dialogue.
 *
 * Imported here rather than with the page, as core/modal is: it is needed only when something failed.
 *
 * @param {Error} error What failed.
 * @return {Promise} Resolving once the reader has been told.
 */
const report = async(error) => {
    const Notification = await import('core/notification');
    Notification.exception(error);
};

/**
 * A press anywhere on the page: a plain press on a card's link opens its dialogue instead of its page.
 *
 * A modified press (a new tab, a new window) and any button but the main one follow the link; a middle
 * press is an auxclick, which never reaches here. Enter on the focused link is a plain press.
 *
 * @param {MouseEvent} event The click.
 * @return {void}
 */
const onClick = (event) => {
    const link = linkOf(event.target);
    if (!link || event.defaultPrevented) {
        return;
    }
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    const courseid = courseOf(link);
    if (courseid === null) {
        return;
    }
    event.preventDefault();
    if (opening) {
        return;
    }
    opening = true;
    const pending = new Pending('theme_dimensions/categorycoursemodal:open');
    open(link, courseid)
        .catch(report)
        .finally(() => {
            opening = false;
            pending.resolve();
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
    document.addEventListener('pointerover', onPointerOver);
    document.addEventListener('pointerout', onPointerOut);
    document.addEventListener('focusin', onFocusIn);
    document.addEventListener('focusout', onFocusOut);
    mark(document);
    // Cards a client module renders later are marked as they arrive.
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node instanceof Element) {
            mark(node);
        }
    }))).observe(document.body, {childList: true, subtree: true});
};
