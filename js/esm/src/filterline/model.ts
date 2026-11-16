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
 * What the filter line draws, read off the server's line and moved by what the listing tells it.
 *
 * A React mount replaces the markup it is mounted on (core's createRoot empties the node on its first
 * commit), so the component's first render has to draw the boxes the server drew. It does by reading
 * them: {@see read} takes every word, name, id, count and state of the line's own controls off the
 * server's markup while it is still in the document, during the first render, and the component draws
 * that same markup back (the template category_toolbar's, hook for hook). Nothing is passed twice, and
 * whatever the reader did on the server's line before the mount - a typed term, a radio pressed - is read
 * as it stands.
 *
 * After that the listing tells the line what moved, through two events ({@see answered}, {@see stated});
 * every change here is a function of the model and what it is told, and nothing reads the page again.
 *
 * @module     theme_dimensions/filterline/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** One cell of the scope: a radio and its label, with the count it shows. */
export type Cell = {
    id: string,
    value: string,
    label: string,
    count: string,
    hascount: boolean,
    checked: boolean,
    absent: boolean,
};

/** One item of the View menu: the view, its word and icon, and whether it is the one in force. */
export type View = {value: string, label: string, icon: string, checked: boolean};

/** Everything the line's own controls show, as the server drew it. */
export type Line = {
    scope: {legend: string, name: string, cells: Cell[]} | null,
    // The result count on the bar, the count sentence's number as the server formatted it.
    total: string,
    search: {
        id: string,
        name: string,
        label: string,
        placeholder: string,
        value: string,
        clearlabel: string,
    },
    openlabel: string,
    star: {name: string, pressed: boolean, count: string, label: string} | null,
    // Available: whether the panel holds a group; the control carries the hidden attribute while it does not.
    filters: {label: string, count: string, countlabel: string, hascount: boolean, available: boolean},
    // The date button: the filter's name (its tooltip), its accessible name, the name with {period} in the
    // period's place, and whether a period is set (its dot); null where the server drew none.
    date: {label: string, name: string, template: string, set: boolean} | null,
    organiselabel: string,
    // The View menu: its items, the button's name, the parameter its items post, its section's heading and its id.
    views: View[],
    viewlabel: string,
    viewname: string,
    viewheading: string,
    viewheadid: string,
    manage: boolean,
    closelabel: string,
    suggest: {id: string, filters: string, start: string},
};

/** One option of an answer's status group, as the listing hands the line its scope part. */
export type Option = {
    id?: string,
    value: string | number,
    label?: string,
    count?: number | string | null,
    hascount?: boolean,
    checked?: boolean,
};

/** What the listing tells the line from an answer ({@see answered}). */
export type Answer = {
    scope?: Option[] | null,
    total?: string,
    favourites?: {available?: boolean, pressed?: boolean, count?: number | string} | null,
    filtercount?: number | string,
    filtercountlabel?: string,
    // Whether the Filters panel holds a group a reader can see.
    filtergroups?: boolean,
    // The period chip's sentence, the empty string for no period.
    period?: string,
};

/** What the listing tells the line of its own state ({@see stated}); any key may be left out. */
export type Told = {query?: string, chip?: string | null, fav?: boolean, view?: string};

/** The hooks the line is read from, all of them written by the toolbar's template. */
export const SELECTORS = {
    line: '[data-region="dim-line"]',
    total: '[data-region="dim-line-total"]',
    scope: '[data-region="dim-vitrine-group"][data-role="scope"]',
    search: '[data-region="dim-line-search"]',
    field: 'input[type="search"]',
    eraser: '[data-action="dim-search-clear"]',
    opener: '[data-action="dim-search-open"]',
    closer: '[data-action="dim-search-close"]',
    star: '[data-region="dim-line-star"]',
    favcount: '[data-region="dim-fav-count"]',
    filters: '[data-region="dim-filters"]',
    filterslabel: '.dim-toolbar__filterslabel',
    badge: '[data-region="dim-filtercount"]',
    badgenumber: '[data-region="dim-filtercount-number"]',
    badgelabel: '[data-region="dim-filtercount-label"]',
    panel: '[data-region="dim-filters-panel"]',
    date: '[data-region="dim-period"]',
    datebutton: '[data-region="dim-period-button"]',
    datedot: '[data-region="dim-period-dot"]',
    datelayer: '[data-region="dim-period-layer"]',
    organise: '[data-region="dim-organise"]',
    menu: '[data-region="dim-organise-menu"]',
    viewmenu: '[data-region="dim-view"]',
    viewlayer: '[data-region="dim-view-menu"]',
    view: '[data-action="dim-view"]',
    menuhead: '.dim-menu__head',
    menulabel: '.dim-menu__label',
    manage: '[data-region="dim-line-manage"]',
    listbox: '[data-region="dim-line-suggest"]',
    pillcount: '[data-region="dim-pill-count"]',
};

/**
 * The attribute an icon-only control names itself in for the bar's tooltip, its accessible name's words.
 *
 * The controls carry no title: the stylesheet draws the tooltip from this.
 */
export const TIP = 'data-dim-tip';

/**
 * An element's text, trimmed.
 *
 * @param {Element|null} element The element.
 * @returns {string}
 */
const text = (element: Element | null): string => (element ? (element.textContent || '').trim() : '');

/**
 * A label's own words, without the count drawn inside it.
 *
 * @param {Element} label The label.
 * @returns {string}
 */
const wordsOf = (label: Element): string => Array.from(label.childNodes)
    .filter((node) => node.nodeType === Node.TEXT_NODE)
    .map((node) => node.textContent || '')
    .join('')
    .trim();

/**
 * The cells of the server's scope, every one of them: a cell with nothing to offer is drawn hidden.
 *
 * @param {Element} fieldset The scope's fieldset.
 * @returns {Array}
 */
const cellsOf = (fieldset: Element): Cell[] => Array.from(fieldset.querySelectorAll<HTMLInputElement>('input[type="radio"]'))
    .map((input) => {
        const label = fieldset.querySelector<HTMLElement>(`label[for="${input.id}"]`);
        const count = label ? label.querySelector<HTMLElement>(SELECTORS.pillcount) : null;
        return {
            id: input.id,
            value: input.value,
            label: label ? wordsOf(label) : '',
            count: text(count),
            hascount: Boolean(count) && !(count as HTMLElement).hidden,
            checked: input.checked,
            absent: input.hidden || Boolean(label && label.hidden),
        };
    });

/**
 * An attribute of the first element a selector finds, or the empty string.
 *
 * @param {Element} root Where to look.
 * @param {string} selector The element.
 * @param {string} name The attribute.
 * @returns {string}
 */
const attrOf = (root: Element, selector: string, name: string): string => {
    const element = root.querySelector(selector);
    return element ? element.getAttribute(name) || '' : '';
};

/**
 * The scope as the server drew it, or null where the line draws none.
 *
 * @param {Element} line The server's line.
 * @returns {object|null}
 */
const scopeOf = (line: Element): Line['scope'] => {
    const scope = line.querySelector<HTMLElement>(SELECTORS.scope);
    if (!scope) {
        return null;
    }
    return {legend: text(scope.querySelector('legend')), name: scope.dataset.name || 'chip', cells: cellsOf(scope)};
};

/**
 * The favourites star as the server drew it, or null where the reader has none.
 *
 * @param {Element} line The server's line.
 * @returns {object|null}
 */
const starOf = (line: Element): Line['star'] => {
    const star = line.querySelector<HTMLButtonElement>(SELECTORS.star);
    if (!star) {
        return null;
    }
    return {
        name: star.name,
        pressed: star.getAttribute('aria-pressed') === 'true',
        count: text(star.querySelector(SELECTORS.favcount)),
        label: star.getAttribute(TIP) || '',
    };
};

/**
 * The Filters summary: its word and its badge, both halves.
 *
 * @param {Element} line The server's line.
 * @returns {object}
 */
const filtersOf = (line: Element): Line['filters'] => {
    const filters = line.querySelector(SELECTORS.filters);
    const badge = filters ? filters.querySelector<HTMLElement>(SELECTORS.badge) : null;
    return {
        label: text(filters ? filters.querySelector(SELECTORS.filterslabel) : null),
        count: text(badge ? badge.querySelector(SELECTORS.badgenumber) : null),
        countlabel: text(badge ? badge.querySelector(SELECTORS.badgelabel) : null),
        hascount: badge !== null && !badge.hidden,
        available: filters !== null && !(filters as HTMLElement).hidden,
    };
};

/** Where the period's sentence goes in the date button's name: category_toolbar::PERIOD_PLACEHOLDER. */
export const PERIOD = '{period}';

/**
 * The date button as the server drew it, or null where it drew none.
 *
 * @param {Element} line The server's line.
 * @returns {object|null}
 */
const dateOf = (line: Element): Line['date'] => {
    const button = line.querySelector<HTMLElement>(`${SELECTORS.date} > ${SELECTORS.datebutton}`);
    if (!button) {
        return null;
    }
    const dot = button.querySelector<HTMLElement>(SELECTORS.datedot);
    return {
        label: button.getAttribute(TIP) || '',
        name: button.getAttribute('aria-label') || '',
        template: button.dataset.dimTemplate || '',
        set: dot !== null && !dot.hidden,
    };
};

/**
 * The date button's name for a period: the filter's name alone without one, the server's sentence with the
 * period written into it with one.
 *
 * @param {object} date The date button.
 * @param {string} period The period chip's sentence, or the empty string.
 * @returns {string}
 */
export const dateName = (date: NonNullable<Line['date']>, period: string): string => {
    if (period === '' || date.template.indexOf(PERIOD) < 0) {
        return date.label;
    }
    return date.template.split(PERIOD).join(period);
};

/**
 * The Font Awesome icon an element draws: its fa- class, without the family or the menu's own classes.
 *
 * @param {Element|null} icon The icon.
 * @returns {string}
 */
const iconOf = (icon: Element | null): string =>
    (icon ? Array.from(icon.classList).find((name) => name.startsWith('fa-')) || '' : '');

/**
 * The View menu's items, List first as the server draws them.
 *
 * @param {Element|null} menu The View menu.
 * @returns {Array}
 */
const viewsOf = (menu: Element | null): View[] => (menu ? Array.from(menu.querySelectorAll<HTMLButtonElement>(SELECTORS.view)) : [])
    .map((item) => ({
        value: item.value,
        label: text(item.querySelector(SELECTORS.menulabel)),
        icon: iconOf(item.querySelector('i')),
        checked: item.getAttribute('aria-checked') === 'true',
    }));

/**
 * Read the line the server drew, or null where it is not a line this component can draw.
 *
 * A line with its search field and its listbox, which is what arms it. The
 * listing's mountLine() asks the same before it mounts, so this answers null only for markup that
 * changed under it.
 *
 * @param {Element|null} line The server's line.
 * @returns {object|null}
 */
export const read = (line: Element | null): Line | null => {
    const search = line ? line.querySelector(SELECTORS.search) : null;
    const field = search ? search.querySelector<HTMLInputElement>(SELECTORS.field) : null;
    const listbox = line ? line.querySelector<HTMLElement>(SELECTORS.listbox) : null;
    if (!line || !search || !field || !listbox) {
        return null;
    }
    const view = line.querySelector(SELECTORS.viewmenu);
    const layer = view ? view.querySelector(SELECTORS.viewlayer) : null;
    const head = layer ? layer.querySelector(SELECTORS.menuhead) : null;
    return {
        scope: scopeOf(line),
        total: text(line.querySelector(SELECTORS.total)),
        search: {
            id: field.id,
            name: field.name,
            label: text(search.querySelector(`label[for="${field.id}"]`)),
            placeholder: field.placeholder,
            value: field.value,
            clearlabel: attrOf(search, SELECTORS.eraser, 'aria-label'),
        },
        openlabel: attrOf(line, SELECTORS.opener, TIP),
        star: starOf(line),
        filters: filtersOf(line),
        date: dateOf(line),
        organiselabel: attrOf(line, `${SELECTORS.organise} > summary`, TIP),
        views: viewsOf(layer),
        viewlabel: view ? attrOf(view, 'summary', TIP) : '',
        viewname: layer ? attrOf(layer, SELECTORS.view, 'name') || 'view' : 'view',
        viewheading: text(head),
        viewheadid: head ? head.id : '',
        manage: line.querySelector(SELECTORS.manage) !== null,
        closelabel: attrOf(line, SELECTORS.closer, 'aria-label'),
        suggest: {id: listbox.id, filters: listbox.dataset.labelFilters || '', start: listbox.dataset.labelStart || ''},
    };
};

/**
 * The scope after an answer: each cell's count and state from the answer's option of its value, and a
 * cell the answer does not offer hidden (absent), unchecked.
 *
 * The cells keep the server's order, which is the scope's own (criteria::SCOPE: every cell is drawn, an
 * absent one hidden); an option of no cell drawn - which the server never answers - goes last, in the
 * answer's order, which the listing has already put in SCOPE's.
 *
 * @param {Array} cells The cells drawn.
 * @param {Array} options The answer's scope options.
 * @returns {Array} The cells to draw.
 */
export const scopeAfter = (cells: Cell[], options: Option[]): Cell[] => {
    const byvalue = new Map(options.map((option) => [String(option.value), option]));
    const known = new Set(cells.map((cell) => cell.value));
    const drawn = cells.map((cell): Cell => {
        const option = byvalue.get(cell.value);
        if (!option) {
            return Object.assign({}, cell, {absent: true, checked: false});
        }
        return {
            id: option.id || cell.id,
            value: cell.value,
            label: option.label === undefined ? cell.label : option.label,
            count: option.hascount ? String(option.count ?? '') : '',
            hascount: Boolean(option.hascount),
            checked: Boolean(option.checked),
            absent: false,
        };
    });
    options.filter((option) => !known.has(String(option.value)) && option.id).forEach((option) => {
        drawn.push({
            id: String(option.id),
            value: String(option.value),
            label: option.label || '',
            count: option.hascount ? String(option.count ?? '') : '',
            hascount: Boolean(option.hascount),
            checked: Boolean(option.checked),
            absent: false,
        });
    });
    return drawn;
};

/**
 * The line after an answer: the result count, the scope's counts and states, the star, the Filters badge, the
 * date button's name and dot.
 *
 * Each part the answer leaves out stays as it is: no scope options is a page whose state sort was
 * switched off under it (the listing's paintStatus() reads it the same way), and a toggle the answer
 * says is not available is left alone, because a page drawn without one has nothing to repaint.
 *
 * @param {object} model The line.
 * @param {object} answer What the listing told.
 * @returns {object} The new line.
 */
export const answered = (model: Line, answer: Answer): Line => {
    const next: Line = Object.assign({}, model);
    if (typeof answer.total === 'string') {
        next.total = answer.total;
    }
    if (model.scope && answer.scope && answer.scope.length) {
        next.scope = Object.assign({}, model.scope, {cells: scopeAfter(model.scope.cells, answer.scope)});
    }
    const toggle = answer.favourites;
    if (model.star && toggle && toggle.available) {
        next.star = Object.assign({}, model.star, {pressed: Boolean(toggle.pressed), count: String(Number(toggle.count) || 0)});
    }
    if (answer.filtercount !== undefined) {
        const count = Number(answer.filtercount || 0);
        next.filters = Object.assign({}, model.filters, {
            count: String(count),
            countlabel: answer.filtercountlabel || '',
            hascount: count !== 0,
        });
    }
    if (typeof answer.filtergroups === 'boolean') {
        next.filters = Object.assign({}, next.filters, {available: answer.filtergroups});
    }
    if (model.date && typeof answer.period === 'string') {
        next.date = Object.assign({}, model.date, {name: dateName(model.date, answer.period), set: answer.period !== ''});
    }
    return next;
};

/**
 * The line after the listing told it its state: the term, the scope's choice, the star, the view.
 *
 * @param {object} model The line.
 * @param {object} told What the listing told; a key left out changes nothing.
 * @returns {object} The new line.
 */
export const stated = (model: Line, told: Told): Line => {
    const next: Line = Object.assign({}, model);
    if (typeof told.query === 'string') {
        next.search = Object.assign({}, model.search, {value: told.query});
    }
    // A chip of null checks no cell: the choice is one of "More statuses", in the panel.
    if (told.chip !== undefined && model.scope) {
        next.scope = Object.assign({}, model.scope, {
            cells: model.scope.cells.map((cell) => Object.assign({}, cell, {checked: cell.value === told.chip})),
        });
    }
    if (typeof told.fav === 'boolean' && model.star) {
        next.star = Object.assign({}, model.star, {pressed: told.fav});
    }
    if (typeof told.view === 'string') {
        next.views = model.views.map((view) => Object.assign({}, view, {checked: view.value === told.view}));
    }
    return next;
};
