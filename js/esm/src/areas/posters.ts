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
 * The posters' result layer: what Areas.tsx writes onto the server's posters, and nobody else does.
 *
 * The posters are the server's markup
 * (category_directory_card) and stay it: the component never re-renders one. What changes with an answer -
 * the pill, the counts and the shell on the article, whether the poster opens (its control a button or the
 * link to its page), its open state, its item's place in the grid, the reveal and the "nothing anywhere"
 * box - is written here, by Areas.tsx alone, on both shells. Every write is made only when it changes something,
 * so a pass that agrees with the page invalidates no style.
 *
 * Two kinds of poster control. The logged-in shell's become buttons. The visitor's stay the links the server
 * drew (`links`), each announced as the button it acts as - its role, its state, the panel it controls - so a
 * press meant for another tab or window still goes to its address; the visitor's module points those
 * addresses at each area's panel under the address on screen, which is the one write on a poster that is not
 * this module's.
 *
 * @module     theme_dimensions/areas/posters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CLASSES, PANEL_ID, SELECTORS} from './model';
import type {Entry, Shell, Word} from './model';

/**
 * What a pill says while the criteria's answer is out: a placeholder glyph and not a sentence, so nothing is
 * read out and nothing on the poster moves when the number arrives.
 */
export const PENDING = '…';

/**
 * Set an element's hidden state, only when it changes.
 *
 * @param {Element} element The element.
 * @param {boolean} hidden Whether it is hidden.
 * @returns {void}
 */
export const setHidden = (element: HTMLElement, hidden: boolean): void => {
    if (element.hidden !== hidden) {
        element.hidden = hidden;
    }
};

/**
 * Set a class, only when it changes.
 *
 * @param {Element} element The element.
 * @param {string} name The class.
 * @param {boolean} on Whether it is on.
 * @returns {void}
 */
export const setClass = (element: Element, name: string, on: boolean): void => {
    if (element.classList.contains(name) !== on) {
        element.classList.toggle(name, on);
    }
};

/**
 * Set an attribute, or remove it with null, only when it changes.
 *
 * @param {Element} element The element.
 * @param {string} name The attribute.
 * @param {string|null} value Its value, or null for none.
 * @returns {void}
 */
export const setAttr = (element: Element, name: string, value: string | null): void => {
    if (element.getAttribute(name) === value) {
        return;
    }
    if (value === null) {
        element.removeAttribute(name);
    } else {
        element.setAttribute(name, value);
    }
};

/**
 * One poster of the areas by its area's id.
 *
 * @param {Element} areas The areas section.
 * @param {number} id The area.
 * @returns {HTMLElement|null}
 */
export const posterOf = (areas: Element, id: number): HTMLElement | null => {
    if (!Number.isInteger(id) || id <= 0) {
        return null;
    }
    return areas.querySelector<HTMLElement>(`${SELECTORS.area}[data-categoryid="${id}"]`);
};

/**
 * The grid item a poster sits in.
 *
 * @param {Element} poster The poster.
 * @returns {HTMLElement}
 */
export const itemOf = (poster: Element): HTMLElement => poster.parentElement as HTMLElement;

/**
 * The shell a poster carries for its panel.
 *
 * @param {Element} poster The poster.
 * @returns {object}
 */
export const shellOf = (poster: HTMLElement): Shell => ({
    id: Number(poster.dataset.categoryid),
    name: poster.dataset.name || '',
    titleurl: poster.dataset.titleurl || '',
    ctaurl: poster.dataset.ctaurl || '',
    ctalabel: poster.dataset.ctalabel || '',
});

/**
 * Whether a poster opens a panel at all. One with nothing to open is a link to the area's page.
 *
 * @param {Element|null} poster The poster.
 * @returns {boolean}
 */
export const isExpandable = (poster: HTMLElement | null): boolean => Boolean(poster) && poster?.dataset.expandable === '1';

/**
 * Whether the page is in result mode: the server's own switch, written on the areas section and rewritten
 * from every answer.
 *
 * @param {Element} areas The areas section.
 * @returns {boolean}
 */
export const isFiltered = (areas: HTMLElement): boolean => areas.dataset.filtered === '1';

/**
 * Whether a poster's area holds no result under the page's criterion, which is what sends it to the summary.
 *
 * @param {Element} areas The areas section.
 * @param {Element|null} poster The poster.
 * @returns {boolean}
 */
export const isZeroed = (areas: HTMLElement, poster: HTMLElement | null): boolean =>
    Boolean(poster) && isFiltered(areas) && Number(poster?.dataset.count) === 0;

/**
 * The items of a grid that hold a poster shown: what the panel's row is counted among. A DOM read alone.
 *
 * @param {Element} grid The grid.
 * @param {Element} panel The panel's item, left out.
 * @returns {Array}
 */
export const shownItems = (grid: Element, panel: Element): HTMLElement[] =>
    (Array.from(grid.children) as HTMLElement[]).filter((el) => el !== panel && !el.hidden && el.querySelector(SELECTORS.area));

/**
 * Replace a link by a button of the same class, children and data attributes.
 *
 * The page drew links, because that is what works without the component; with it the same controls change
 * something on the page and are buttons. The children move over, so the name and the glyph are the
 * server's own and nothing is composed. Only the data attributes travel: any other attribute is handed
 * over by name.
 *
 * @param {Element} link The link.
 * @param {object} attributes The attributes the button carries besides its class and data attributes.
 * @returns {HTMLElement} The button.
 */
export const toButton = (link: HTMLElement, attributes: Record<string, string>): HTMLButtonElement => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = link.className;
    Object.keys(link.dataset).forEach((key) => {
        button.dataset[key] = link.dataset[key];
    });
    Object.keys(attributes).forEach((name) => button.setAttribute(name, attributes[name]));
    button.append(...Array.from(link.childNodes));
    link.replaceWith(button);
    return button;
};

/**
 * Replace a poster's button by the link it was, to the area's own page: toButton() the other way.
 *
 * Only the class, the data attributes and the pill's description travel; a button's state does not belong
 * on a link.
 *
 * @param {Element} button The poster's button.
 * @param {string} href The area's own page.
 * @returns {HTMLElement} The link.
 */
export const toLink = (button: HTMLElement, href: string): HTMLAnchorElement => {
    const link = document.createElement('a');
    link.className = button.className;
    link.href = href;
    Object.keys(button.dataset).forEach((key) => {
        link.dataset[key] = button.dataset[key];
    });
    const described = button.getAttribute('aria-describedby');
    if (described) {
        link.setAttribute('aria-describedby', described);
    }
    link.append(...Array.from(button.childNodes));
    button.replaceWith(link);
    return link;
};

/**
 * The control of a poster that opens its panel in place, or null.
 *
 * A button always is. A link is only on a shell that keeps the posters links, and only while its poster
 * opens: a link to an area with nothing to open goes to its page.
 *
 * @param {Element|null} poster The poster.
 * @param {boolean} links Whether the posters stay links.
 * @returns {HTMLElement|null}
 */
export const driven = (poster: HTMLElement | null, links: boolean): HTMLElement | null => {
    const control = poster ? poster.querySelector<HTMLElement>(SELECTORS.control) : null;
    if (!control) {
        return null;
    }
    if (control.tagName === 'BUTTON') {
        return control;
    }
    return links && control.tagName === 'A' && isExpandable(poster) ? control : null;
};

/**
 * Turn an expandable poster's link into the control that opens its panel.
 *
 * On the logged-in shell the link becomes a button. toButton() copies the data attributes alone, so the
 * pill's description is handed over by name: a poster drawn in result mode is described by its pill, and a
 * button without it would stop saying how many results the area holds. A shell keeping the posters links has
 * the link announced as the button it now acts as - its role, its state and the panel it controls - while it
 * keeps its address.
 *
 * @param {Element} poster The poster.
 * @param {number} open The open area.
 * @param {boolean} links Whether the posters stay links.
 * @returns {void}
 */
export const convertPoster = (poster: HTMLElement, open: number, links: boolean = false): void => {
    const control = poster.querySelector<HTMLElement>(SELECTORS.control);
    if (!control || control.tagName === 'BUTTON' || !isExpandable(poster)) {
        return;
    }
    const attributes: Record<string, string> = {
        'aria-expanded': Number(poster.dataset.categoryid) === open ? 'true' : 'false',
        'aria-controls': PANEL_ID,
    };
    if (links) {
        attributes.role = 'button';
        Object.keys(attributes).forEach((name) => setAttr(control, name, attributes[name]));
        return;
    }
    const described = control.getAttribute('aria-describedby');
    if (described) {
        attributes['aria-describedby'] = described;
    }
    toButton(control, attributes);
};

/**
 * Make a poster open a panel, or make it the link to its area's page again.
 *
 * Everything that says which one a poster is flips together: its class, its attribute, the note naming the
 * link's destination, the trigger's word and the control. The open poster is left as it is until it closes,
 * so the panel the reader has open never loses its control. A shell keeping the posters links leaves the link
 * in place and takes its button state off it.
 *
 * @param {Element} poster The poster.
 * @param {boolean} on Whether it opens a panel.
 * @param {number} open The open area.
 * @param {boolean} links Whether the posters stay links.
 * @returns {void}
 */
export const setExpandable = (poster: HTMLElement, on: boolean, open: number, links: boolean = false): void => {
    if (isExpandable(poster) === on || Number(poster.dataset.categoryid) === open) {
        return;
    }
    poster.dataset.expandable = on ? '1' : '0';
    setClass(poster, CLASSES.posterempty, !on);
    const note = poster.querySelector<HTMLElement>(SELECTORS.emptynote);
    if (note) {
        setHidden(note, on);
    }
    const trigger = poster.querySelector<HTMLElement>(SELECTORS.trigger);
    const word = trigger ? trigger.dataset[on ? 'open' : 'empty'] : '';
    if (trigger && word) {
        trigger.textContent = word;
    }
    const control = poster.querySelector<HTMLElement>(SELECTORS.control);
    const focused = Boolean(control) && document.activeElement === control;
    if (on) {
        convertPoster(poster, open, links);
    } else if (control && control.tagName === 'BUTTON' && poster.dataset.titleurl) {
        toLink(control, poster.dataset.titleurl);
    } else if (control && links) {
        ['role', 'aria-expanded', 'aria-controls'].forEach((name) => setAttr(control, name, null));
    }
    const now = poster.querySelector<HTMLElement>(SELECTORS.control);
    if (focused && now && now !== control) {
        now.focus({preventScroll: true});
    }
};

/**
 * Put a poster in or out of the open state: its class, its control's state and its trigger's word.
 *
 * @param {Element|null} poster The poster.
 * @param {boolean} on Whether it is the open one.
 * @param {boolean} links Whether the posters stay links.
 * @returns {void}
 */
export const setPosterOpen = (poster: HTMLElement | null, on: boolean, links: boolean = false): void => {
    if (!poster) {
        return;
    }
    setClass(poster, CLASSES.posteropen, on);
    const control = driven(poster, links);
    if (control) {
        setAttr(control, 'aria-expanded', on ? 'true' : 'false');
    }
    const trigger = poster.querySelector<HTMLElement>(SELECTORS.trigger);
    const word = trigger ? trigger.dataset[on ? 'close' : 'open'] : '';
    if (trigger && word && trigger.textContent !== word) {
        trigger.textContent = word;
    }
};

/**
 * Put an area's poster where its panel can be seen: the reveal around it shown and open, its item in the grid.
 *
 * @param {Element} poster The poster.
 * @returns {void}
 */
export const unveil = (poster: HTMLElement): void => {
    const reveal = poster.closest<HTMLDetailsElement>(SELECTORS.more);
    if (reveal) {
        setHidden(reveal, false);
        if (!reveal.open) {
            reveal.open = true;
        }
    }
    setHidden(itemOf(poster), false);
};

/**
 * A request for new criteria went out: the grids are busy and, if it narrows, every pill waits.
 *
 * @param {Element} areas The areas section.
 * @param {boolean} filtered Whether the requested criteria narrow the page.
 * @returns {void}
 */
export const pending = (areas: HTMLElement, filtered: boolean): void => {
    areas.querySelectorAll(SELECTORS.grid).forEach((grid) => setAttr(grid, 'aria-busy', 'true'));
    if (!filtered) {
        return;
    }
    areas.querySelectorAll<HTMLElement>(SELECTORS.pill).forEach((pill) => {
        pill.textContent = PENDING;
        setHidden(pill, false);
        setClass(pill, CLASSES.pillzero, false);
    });
};

/**
 * Write a poster's pill from what the latest answer left on the poster: its words, whether it shows, its
 * zero colours, and whether the control is described by it (only while it shows).
 *
 * @param {Element} poster The poster.
 * @param {boolean} filtered Whether the page is in result mode.
 * @returns {void}
 */
export const paintPill = (poster: HTMLElement, filtered: boolean): void => {
    const pill = poster.querySelector<HTMLElement>(SELECTORS.pill);
    const label = poster.dataset.resultlabel || '';
    const has = filtered && Boolean(pill) && label !== '';
    if (pill) {
        if (pill.textContent !== label) {
            pill.textContent = label;
        }
        setHidden(pill, !has);
        setClass(pill, CLASSES.pillzero, has && Number(poster.dataset.count) === 0);
    }
    const control = poster.querySelector<HTMLElement>(SELECTORS.control);
    if (control) {
        setAttr(control, 'aria-describedby', has && pill ? pill.id : null);
    }
};

/**
 * The request for new criteria failed: the grids stop being busy and every pill says the latest answer again.
 *
 * @param {Element} areas The areas section.
 * @returns {void}
 */
export const failed = (areas: HTMLElement): void => {
    areas.querySelectorAll(SELECTORS.grid).forEach((grid) => setAttr(grid, 'aria-busy', null));
    areas.querySelectorAll<HTMLElement>(SELECTORS.area).forEach((poster) => paintPill(poster, isFiltered(areas)));
};

/**
 * Write each area of the criteria's answer onto its poster: the shell, the pill, the counts, whether it
 * opens, and whether it stays in the grid.
 *
 * An area with no result under a criterion leaves its grid through the hidden attribute on its item - never
 * removed - except the open one, which stays until the reader closes it. Back at rest, every poster the
 * answer does not name goes back to rest with it: the root at rest counts nothing, so its answer names no
 * area, and a search cleared in place would otherwise leave the last term's pills and hidden posters.
 *
 * @param {Element} areas The areas section.
 * @param {Array} accordion The answer's areas.
 * @param {boolean} filtered Whether the answer is in result mode.
 * @param {number} open The open area, 0 for none.
 * @param {Map} expandable Each area's opening state as the latest answer gave it, by id: written here.
 * @param {boolean} links Whether the posters stay links.
 * @returns {void}
 */
export const paintPosters = (
    areas: HTMLElement,
    accordion: Entry[],
    filtered: boolean,
    open: number,
    expandable: Map<number, boolean>,
    links: boolean = false
): void => {
    if (!filtered) {
        const named = new Set(accordion.map((entry) => Number(entry.id)));
        areas.querySelectorAll<HTMLElement>(SELECTORS.area).forEach((poster) => {
            if (named.has(Number(poster.dataset.categoryid))) {
                return;
            }
            poster.dataset.resultcount = '';
            poster.dataset.resultlabel = '';
            paintPill(poster, false);
            setHidden(itemOf(poster), false);
        });
    }
    accordion.forEach((entry) => {
        const id = Number(entry.id);
        const poster = posterOf(areas, id);
        if (!poster) {
            return;
        }
        const count = Number(entry.count) || 0;
        poster.dataset.count = String(count);
        poster.dataset.resultcount = entry.resultcount || '';
        poster.dataset.resultlabel = entry.resultlabel || '';
        poster.dataset.ctaurl = entry.ctaurl || '';
        poster.dataset.ctalabel = entry.ctalabel || '';
        paintPill(poster, filtered);
        expandable.set(id, Boolean(entry.expandable));
        setExpandable(poster, Boolean(entry.expandable), open, links);
        setHidden(itemOf(poster), filtered && count === 0 && id !== open);
    });
};

/**
 * Hide the reveal past the slice under a criterion when no poster behind it is left in its grid: the
 * server's own rule (category_listing::areas(), morehidden), read off the page.
 *
 * @param {Element} areas The areas section.
 * @returns {void}
 */
export const fitReveal = (areas: HTMLElement): void => {
    const more = areas.querySelector<HTMLElement>(SELECTORS.more);
    if (!more) {
        return;
    }
    const shown = Array.from(more.querySelectorAll(SELECTORS.area)).some((poster) => !itemOf(poster).hidden);
    setHidden(more, isFiltered(areas) && !shown);
};

/**
 * Bring the reveal past the slice in line with the answer: its words, its place, and open by itself when an
 * area behind it holds a result.
 *
 * @param {Element} areas The areas section.
 * @param {object} word The answer's word on the areas.
 * @returns {void}
 */
export const paintReveal = (areas: HTMLElement, word: Word): void => {
    const more = areas.querySelector<HTMLDetailsElement>(SELECTORS.more);
    if (!more) {
        return;
    }
    const summary = more.querySelector(SELECTORS.moresummary);
    if (summary && word.morelabel && summary.textContent !== word.morelabel) {
        summary.textContent = word.morelabel;
    }
    fitReveal(areas);
    if (Boolean(word.filtered) && Number(word.moreresults) > 0 && !more.open) {
        more.open = true;
    }
};

/**
 * Show the "nothing matches anywhere" box exactly when the criteria said so and no panel is open.
 *
 * @param {Element} areas The areas section.
 * @param {boolean} allempty Whether nothing matches anywhere, as the latest criteria said it.
 * @param {number} open The open area, 0 for none.
 * @returns {void}
 */
export const paintAllEmpty = (areas: HTMLElement, allempty: boolean, open: number): void => {
    const box = areas.querySelector<HTMLElement>(SELECTORS.allempty);
    if (box) {
        setHidden(box, !(allempty && !open));
    }
};

/**
 * Write the "nothing anywhere" box's second way out from the latest answer: the same term in every area.
 *
 * @param {Element} areas The areas section.
 * @param {string} url The server's address, or the empty string where there is nothing to offer.
 * @returns {void}
 */
export const paintSearchAll = (areas: HTMLElement, url: string): void => {
    const link = areas.querySelector<HTMLAnchorElement>(SELECTORS.searchall);
    if (!link) {
        return;
    }
    if (url) {
        setAttr(link, 'href', url);
    }
    setHidden(link, !url);
};

/**
 * Whether nothing matches anywhere, as the server's render says it with no panel open.
 *
 * The server draws the "nothing anywhere" box hidden while a panel is open, so a page loaded with a zeroed
 * area open does not say what its close must show; it is read off what the same render does say.
 *
 * @param {Element} areas The areas section.
 * @param {boolean} root Whether the page is the root, where no course sits.
 * @returns {boolean}
 */
export const loadedAllEmpty = (areas: HTMLElement, root: boolean): boolean => {
    const box = areas.querySelector<HTMLElement>(SELECTORS.allempty);
    if (!box || !isFiltered(areas)) {
        return false;
    }
    if (!box.hidden) {
        return true;
    }
    const direct = document.querySelector<HTMLElement>(SELECTORS.direct);
    const nonehere = root || (Boolean(direct) && Boolean(direct?.hidden));
    const posters = Array.from(areas.querySelectorAll<HTMLElement>(SELECTORS.area));
    return nonehere && posters.every((poster) => Number(poster.dataset.count) === 0);
};
