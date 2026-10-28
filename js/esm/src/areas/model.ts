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
 * The panel between the poster rows, as numbers and words: what Areas.tsx decides without the page.
 *
 * Stage R3 of docs/vitrine-react/PLAN.md. Every function here is pure - an address, a context, a
 * place in a grid - and is named after the function of amd/src/categoryareapanel.js it stands for,
 * which the visitor's shell keeps until R4: a rule of the panel changes in both.
 *
 * @module     theme_dimensions/areas/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Every hook the component reads, all of them data attributes of the showcase's templates. */
export const SELECTORS = {
    areas: '[data-region="dim-areas"]',
    listing: '[data-region="dim-vitrine"]',
    grid: '[data-region="dim-areas-grid"]',
    more: '[data-region="dim-areas-more"]',
    moresummary: '[data-region="dim-areas-moresummary"]',
    hint: '[data-region="dim-areas-hint"]',
    allempty: '[data-region="dim-areas-empty"]',
    searchall: '[data-region="dim-areas-searchall"]',
    nomatch: '[data-region="dim-areas-nomatch"]',
    nomatchsummary: '[data-region="dim-nomatch-summary"]',
    nomatchlist: '[data-region="dim-nomatch-list"]',
    status: '[data-region="dim-panel-status"]',
    area: '[data-region="dim-area"]',
    control: '[data-region="dim-poster-control"]',
    trigger: '[data-region="dim-poster-trigger"]',
    emptynote: '[data-region="dim-poster-emptynote"]',
    pill: '[data-region="dim-poster-pill"]',
    covercontrols: '[data-region="dim-cover-controls"]',
    panel: '[data-region="dim-panel"]',
    section: '[data-region="dim-panel-section"]',
    title: '[data-region="dim-panel-title"]',
    subtitle: '[data-region="dim-panel-subtitle"]',
    sort: '[data-region="dim-panel-sort"]',
    sortoption: '[data-action="dim-panel-sort"]',
    close: '[data-action="dim-panel-close"]',
    also: '[data-region="dim-panel-also"]',
    body: '[data-region="dim-panel-body"]',
    rail: '[data-region="dim-panel-rail"]',
    prev: '[data-action="dim-panel-prev"]',
    next: '[data-action="dim-panel-next"]',
    foot: '[data-region="dim-panel-foot"]',
    counter: '[data-region="dim-panel-counter"]',
    retry: '[data-action="dim-panel-retry"]',
    openarea: '[data-action="dim-panel-openarea"]',
    card: '[data-region="dim-card"]',
    search: 'input[type="search"]',
    toolbar: '[data-region="dim-vitrine-toolbar"]',
    fold: '[data-region="dim-hero-fold"]',
    clear: '[data-region="dim-vitrine-clear"]',
    chip: '[data-action="dim-chip-remove"]',
    openfield: '[data-region="dim-open-field"]',
    psortfield: '[data-region="dim-psort-field"]',
    direct: '[data-region="dim-direct"]',
};

/** The classes the component writes; it reads none of them back as state. */
export const CLASSES = {
    posteropen: 'dim-poster--open',
    posterempty: 'dim-poster--empty',
    areasopen: 'dim-areas--open',
    shut: 'dim-panel--shut',
    still: 'dim-panel--static',
    fading: 'dim-panel--fading',
    arrowoff: 'dim-panel__arrow--off',
    dragging: 'dim-panel__rail--dragging',
    pillzero: 'dim-poster__pill--zero',
    list: 'dim-vitrine--list',
};

/** The panel's template: the server renders it for ?open=, the component for a press. */
export const TEMPLATE = 'theme_dimensions/category_panel';

/** The panel element's id, which every poster's control controls. */
export const PANEL_ID = 'dim-panel';

/** The summary's id, which the server's Close of a zeroed area lands on: category_panel::ANCHOR_NOMATCH. */
export const NOMATCH_ID = 'dim-nomatch';

/** The panel's mark on the body, the shared module's own: the stylesheet's panel rules are written against it. */
export const BODY_CLASS = 'dim-panel-js';

/** Below this width the grid has two tracks: the stylesheet's own number. */
export const WIDE = '(max-width: 1199.98px)';

/** Below this width the grid has one track: the stylesheet's own number. */
export const PHONE = '(max-width: 575.98px)';

/** The reader who has asked the platform for less movement. */
export const MOTION = '(prefers-reduced-motion: reduce)';

/** Milliseconds a request may take before the skeleton replaces the empty body. */
export const SKELETON_DELAY = 150;

/** The stylesheet's --dim-ds-motion-base: the panel opens and closes over it. */
export const MOTION_BASE = 250;

/** The stylesheet's --dim-ds-motion-fast: the content crossfades over it. */
export const MOTION_FAST = 150;

/** Milliseconds past the opening before the scroll rule looks at the panel. */
export const SETTLE = 90;

/** Milliseconds a scroll is given to report its end before it is taken to be over. */
export const SCROLL_END = 1000;

/** Pixels between the fixed navbar and a panel scrolled into view. */
export const SCROLL_GAP = 48;

/** Pixels a mouse must travel before a press on the rail is a drag rather than a click. */
export const DRAG_THRESHOLD = 5;

/** The rail's own gap, which one step of its arrows adds to a card's width. */
export const RAIL_GAP = 16;

/** The request parameter names, the same ones the listing's modules and the server use. */
export const PARAM = {
    open: 'open',
    psort: 'psort',
    query: 'q',
};

/** The one order a panel can be asked for besides its default. */
export const PSORT_NAME = 'name';

/** The five integers that place the panel's row and its arrow, per breakpoint. */
export type Placement = {
    '--dim-row-3': string,
    '--dim-row-2': string,
    '--dim-row-1': string,
    '--dim-col-3': string,
    '--dim-col-2': string,
};

/** What a poster carries for its panel's shell, written there by the server and every answer. */
export type Shell = {
    id: number,
    name: string,
    titleurl: string,
    ctaurl: string,
    ctalabel: string,
};

/** One area of a criteria answer's accordion. */
export type Entry = {
    id: number | string,
    name?: string,
    count?: number | string,
    resultlabel?: string,
    resultcount?: string,
    expandable?: boolean,
    ctaurl?: string,
    ctalabel?: string,
    linkable?: boolean,
    url?: string,
};

/** A criteria answer's word on the areas as a whole. */
export type Word = {
    filtered?: boolean,
    elsewhere?: string,
    nomatchlabelall?: string,
    nomatchlabelless?: string,
    morelabel?: string,
    moreresults?: number | string,
    allempty?: boolean,
    searchallurl?: string,
    autoopen?: number | string,
};

/** One area a zero panel suggests: page data, read off the posters. */
export type Suggestion = {id: number, name: string, count: string, countlabel: string, url: string};

/** The panel template's context; the server builds it, the shell's and the answer's are drawn from it. */
export type PanelContext = Record<string, unknown> & {
    subtitle?: string,
    isrows?: boolean,
    iszero?: boolean,
    zero?: string,
};

/** A rows answer, as far as a panel reads it. */
export type Answer = {
    panel?: Record<string, unknown> & {id?: number | string} | null,
    cards?: unknown[],
};

/** One entry of the "nothing in N areas" summary: an area with no result. */
export type Missing = {id: number, name: string, url: string};

/** The summary's entries and its two sentences, the server's both. */
export type Summary = {entries: Missing[], all: string, less: string};

/**
 * The five integers of the panel's row: the row after the open poster's at three, two and one
 * column, and the column its arrow points at. Rows are one-based, and the panel's row is the one after
 * its poster's, which auto-placement then skips.
 *
 * @param {number} index The open poster's place among the posters shown in its grid, zero-based.
 * @returns {object} The five custom properties and their values.
 */
export const placement = (index: number): Placement => ({
    '--dim-row-3': String(Math.floor(index / 3) + 2),
    '--dim-row-2': String(Math.floor(index / 2) + 2),
    '--dim-row-1': String(index + 2),
    '--dim-col-3': String(index % 3),
    '--dim-col-2': String(index % 2),
});

/**
 * How many tracks the grid has: the stylesheet's three, two and one, and one at every width in the list view.
 *
 * The breakpoints are read through matchMedia by the caller, never off a box: the stylesheet decides the
 * tracks from the same two numbers ({@see WIDE}, {@see PHONE}).
 *
 * @param {boolean} list Whether the listing is in its list view.
 * @param {boolean} phone Whether the viewport is under the phone breakpoint.
 * @param {boolean} wide Whether the viewport is under the extra-large breakpoint.
 * @returns {number} 1, 2 or 3.
 */
export const columns = (list: boolean, phone: boolean, wide: boolean): number => {
    if (list || phone) {
        return 1;
    }
    return wide ? 2 : 3;
};

/**
 * The last poster sharing a row with the open one: the panel's place in the DOM, so the Tab order and a
 * screen reader meet it where the eye does.
 *
 * The grid places its posters in DOM order, row by row, so the row is the index's division by the
 * tracks; the panel's own row is explicit and skipped by auto-placement, so it never shifts a poster.
 *
 * @param {number} index The open poster's place among the posters shown.
 * @param {number} count How many posters are shown in its grid.
 * @param {number} tracks The grid's tracks ({@see columns}).
 * @returns {number} The place of the last poster of that row.
 */
export const lastOfRow = (index: number, count: number, tracks: number): number =>
    Math.max(0, Math.min(count - 1, Math.floor(index / tracks) * tracks + tracks - 1));

/**
 * Whether two posters of one grid share a row: the panel then moves inside it.
 *
 * @param {number} first One poster's place among the posters shown.
 * @param {number} second The other's.
 * @param {number} tracks The grid's tracks.
 * @returns {boolean}
 */
export const sameRow = (first: number, second: number, tracks: number): boolean =>
    first >= 0 && second >= 0 && Math.floor(first / tracks) === Math.floor(second / tracks);

/**
 * The call to action's address for the order the panel is in: the shell's, with its sort swapped.
 *
 * @param {string} href The shell's address.
 * @param {string} psort The panel's order.
 * @param {string} base The page's address, against which a relative one is read.
 * @returns {string}
 */
export const ctaUrl = (href: string, psort: string, base: string): string => {
    if (href === '' || psort !== PSORT_NAME) {
        return href;
    }
    const url = new URL(href, base);
    url.searchParams.set('sort', PSORT_NAME);
    return url.toString();
};

/**
 * The panel's context before its rows arrive: the shell the poster carries, and one state.
 *
 * Nothing here is composed: the name, the title's address and the call to action come off the poster,
 * written there by the server (and rewritten by every answer), and every sentence is a string of the
 * template.
 *
 * @param {object} shell The poster's shell.
 * @param {string} psort The panel's order.
 * @param {string} base The page's address.
 * @param {object} flags One of isloading, isskeleton or iserror.
 * @returns {object} The template context.
 */
export const shellContext = (shell: Shell, psort: string, base: string, flags: Record<string, boolean>): PanelContext => {
    const cta = ctaUrl(shell.ctaurl, psort, base);
    return {
        id: shell.id,
        name: shell.name,
        titleurl: shell.titleurl,
        haslink: shell.titleurl !== '',
        isd20: false,
        subtitle: '',
        psortname: psort === PSORT_NAME,
        sorturlrecent: '',
        sorturlname: '',
        closeurl: '',
        also: {has: false, pills: [], hasmore: false, morecount: '', moreurl: ''},
        hascards: false,
        few: false,
        counter: '',
        tile: {has: false, count: '', label: '', url: ''},
        isrows: false,
        isempty: false,
        iszero: false,
        zero: '',
        suggestions: [],
        hassuggestions: false,
        cta: {has: cta !== '', url: cta, label: shell.ctalabel},
        seeaslist: {has: false, url: ''},
        jsmode: true,
        isloading: false,
        isskeleton: false,
        iserror: false,
        cards: [],
        ...flags,
    };
};

/**
 * The panel's context from an answer: the server's panel, its cards and the page's suggestions.
 *
 * An answer carrying no panel, or another area's, is not this panel's, and is a failure like any other.
 *
 * @param {object} response The answer.
 * @param {number} id The area asked about.
 * @param {Array} offered The page's suggestions for a zero panel ({@see suggestions}).
 * @returns {object} The template context.
 */
export const answerContext = (response: Answer, id: number, offered: Suggestion[]): PanelContext => {
    if (!response || !response.panel || Number(response.panel.id) !== id) {
        throw new Error('The answer carries no panel of this area.');
    }
    return {
        ...response.panel,
        cards: response.cards || [],
        jsmode: true,
        suggestions: offered,
        hassuggestions: offered.length > 0,
    };
};

/**
 * The other areas of the page holding a result, which a zero panel suggests, in the posters' order.
 *
 * They are page data, like the cards: the web service answers for one area and knows nothing of its
 * siblings, so they are read off what the server wrote on each poster and every later answer rewrote.
 *
 * @param {Array} posters Each poster's id, name, count, formatted count and pill words.
 * @param {number} open The open area, left out.
 * @param {boolean} filtered Whether the page is in result mode; at rest nothing is suggested.
 * @returns {Array}
 */
export const suggestions = (
    posters: Array<{id: number, name: string, count: number, resultcount: string, resultlabel: string}>,
    open: number,
    filtered: boolean
): Suggestion[] => {
    if (!filtered) {
        return [];
    }
    return posters.filter((poster) => poster.id !== open && poster.count > 0).map((poster) => ({
        id: poster.id,
        name: poster.name,
        count: poster.resultcount,
        countlabel: poster.resultlabel,
        url: '',
    }));
};

/**
 * The address with the open area and its order written in, every other parameter left as it is.
 *
 * The no-JavaScript fragments are this page's own, and a stale one would scroll a reload there, so they go.
 *
 * @param {string} href The page's address.
 * @param {number} open The open area, 0 for none.
 * @param {string} psort The panel's order.
 * @returns {string}
 */
export const addressWith = (href: string, open: number, psort: string): string => {
    const url = new URL(href);
    if (open > 0) {
        url.searchParams.set(PARAM.open, String(open));
    } else {
        url.searchParams.delete(PARAM.open);
    }
    if (open > 0 && psort === PSORT_NAME) {
        url.searchParams.set(PARAM.psort, PSORT_NAME);
    } else {
        url.searchParams.delete(PARAM.psort);
    }
    if (url.hash === '#' + PANEL_ID || url.hash === '#' + NOMATCH_ID || url.hash.startsWith('#dim-area-')) {
        url.hash = '';
    }
    return url.toString();
};

/**
 * A holder's address (the clear link, a chip) carrying the open panel, as the server's own addresses do.
 *
 * @param {string} href The holder's address as written.
 * @param {string} base The page's address.
 * @param {number} open The open area, 0 for none.
 * @param {string} psort The panel's order.
 * @returns {string}
 */
export const holderHref = (href: string, base: string, open: number, psort: string): string => {
    const url = new URL(href, base);
    if (open > 0) {
        url.searchParams.set(PARAM.open, String(open));
    } else {
        url.searchParams.delete(PARAM.open);
    }
    if (open > 0 && psort === PSORT_NAME) {
        url.searchParams.set(PARAM.psort, PSORT_NAME);
    } else {
        url.searchParams.delete(PARAM.psort);
    }
    return url.toString();
};

/**
 * The open area and its order the address names.
 *
 * @param {string} href The page's address.
 * @returns {object} open (0 for none) and psort ('' or name).
 */
export const readPanel = (href: string): {open: number, psort: string} => {
    const params = new URL(href).searchParams;
    return {
        open: Number(params.get(PARAM.open)) || 0,
        psort: params.get(PARAM.psort) === PSORT_NAME ? PSORT_NAME : '',
    };
};

/**
 * The term the page was searched for, which the rail's titles are marked with - the root's too.
 *
 * @param {string} href The page's address.
 * @returns {string}
 */
export const termOf = (href: string): string => new URL(href).searchParams.get(PARAM.query) || '';

/**
 * What an answered panel says through the live region.
 *
 * The rows say how many (the subtitle); the empty state says it in the body's own sentence; a zero panel
 * in two sentences of the server's, its own and the page's word on where the results are.
 *
 * @param {object} context The answer's context.
 * @param {string} body The body's text as rendered.
 * @param {string} elsewhere The page's sentence on the other areas.
 * @returns {string}
 */
export const announcement = (context: PanelContext, body: string, elsewhere: string): string => {
    if (context.iszero) {
        return (String(context.zero || '') + ' ' + elsewhere).trim();
    }
    if (!context.isrows) {
        return body.trim().replace(/\s+/g, ' ');
    }
    return String(context.subtitle || '');
};

/**
 * The summary's sentence for the open panel: the shorter count while the open area is one of the zeros.
 *
 * @param {object} summary The entries and the two sentences.
 * @param {number} open The open area, 0 for none.
 * @returns {string}
 */
export const summaryLabel = (summary: Summary, open: number): string =>
    (summary.entries.some((entry) => entry.id === open) ? summary.less : summary.all) || '';

/**
 * The summary rebuilt from a criteria answer: every zero area, its own page or its name as text.
 *
 * Under no criterion there is no summary. The name is the poster's own where the page has it.
 *
 * @param {Array} accordion The answer's areas.
 * @param {object} word The answer's word on the areas.
 * @param {Function} nameOf The poster's name by id, or null where the page draws none.
 * @returns {object}
 */
export const summaryOf = (accordion: Entry[], word: Word, nameOf: (id: number) => string | null): Summary => ({
    all: word.nomatchlabelall || '',
    less: word.nomatchlabelless || '',
    entries: (word.filtered ? accordion.filter((entry) => Number(entry.count) === 0) : []).map((entry) => {
        const id = Number(entry.id);
        const name = nameOf(id);
        return {
            id: id,
            name: name === null ? String(entry.name || '') : name,
            url: entry.linkable && entry.url ? String(entry.url) : '',
        };
    }),
});

