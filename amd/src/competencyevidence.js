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
 * The evidence page: core's prior-learning evidence and the platform's records in one dated list, the bar above it and
 * the selected evidence beside it.
 *
 * The theme's copy of tool_lp/user_evidence_list_page starts this module from its js block, on the first paint and
 * again every time core's tool_lp/user_evidence_actions replaces the region after an action (it renders the template
 * on the client from tool_lp_data_for_user_evidence_list_page and hands the js block to templates.replaceNode(), which
 * runs it). That re-run is the re-arm point, so no observer is needed. The state below lives as long as the page.
 *
 * - First paint (the root carries data-dim-firstpaint): whether the viewer may read the platform's records is
 *   remembered, and the first page of them is fetched.
 * - Core re-rendered (no data-dim-firstpaint): the records already drawn are put back at once (their nodes are kept
 *   here, outside the region core replaced), then the first page is fetched again, since an action can change what
 *   the platform recorded. The latest request wins; a failure keeps what is there and says so in one quiet line.
 * - The list: every item (core's evidence nodes, the records, the folded lines) is put in date order and headed by
 *   month on every paint. Core's nodes are moved, never copied, so core's menus keep their handlers.
 * - The bar: the scope (all, prior learning, from the platform), the count, Filters (a competency, a plan, a course,
 *   from the service's facets, one choice per group, applied on press), the period (quick options and two days),
 *   the chips of what is in force and a search over the titles shown.
 * - The detail: a plain press on a title opens the item's template element beside the list (a bottom sheet on a
 *   phone, by the stylesheet); its buttons press the item's own menu entry, so core's handler runs.
 *
 * @module     theme_dimensions/competencyevidence
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Pending from 'core/pending';
import Templates from 'core/templates';
import {getString} from 'core/str';

/** The web service of the platform's records. */
const FEED = 'theme_dimensions_get_user_evidence_feed';

/** The template of one page of records. */
const RECORDS = 'theme_dimensions/competency_evidence_records';

/** The entries of core's menu a detail may press: tool_lp/user_evidence_actions' three handlers. */
const PROXIES = ['link-competency', 'send-competencies-review', 'user-evidence-delete'];

/** The kinds of a record, as the service names them; each is a flag of the records template. */
const KINDS = ['activity', 'course', 'rule', 'rating', 'restore', 'unlinked', 'record'];

/** The Filters panel's groups, in order: the filter's key, the facet list, the group's string. */
const GROUPS = [
    {
        key: 'competencyid', facet: 'competencies', label: 'competency_evidencefilter_competency',
        find: 'competency_evidencefind_competency', none: 'competency_evidencenone_competency',
    },
    {
        key: 'planid', facet: 'plans', label: 'competency_evidencefilter_plan',
        find: 'competency_evidencefind_plan', none: 'competency_evidencenone_plan',
    },
    {
        key: 'courseid', facet: 'courses', label: 'competency_evidencefilter_course',
        find: 'competency_evidencefind_course', none: 'competency_evidencenone_course',
    },
];

/** A group with more options than this is a searched list (filter-panel.md, D115). */
const LISTED = 8;

/** The rows a searched list draws at most. */
const ROWS = 50;

/** The bar's narrow step (filter-bar.md): the panels are bottom sheets. */
const NARROW = '(max-width: 575.98px)';

const SELECTORS = {
    ROOT: '[data-region="user-evidence-list"]',
    NODE: '[data-region="user-evidence-node"]',
    LIST: '[data-region="dim-comp-ev-list"]',
    ITEM: '[data-dim-key]',
    MONTH: '[data-region="dim-comp-ev-month"]',
    RECORD: 'li[data-region="dim-comp-ev-record"]',
    SOURCE: 'template[data-region="dim-comp-ev-detailsrc"]',
    TITLE: '.dim-comp-ev-title',
    OPEN: '[data-action="dim-comp-ev-open"]',
    BAR: '[data-region="dim-comp-bar"]',
    SCOPE: '[data-region="dim-comp-scope"]',
    CELL: '[data-region="dim-comp-scope"] [role="radio"]',
    CELLCOUNT: '[data-region="dim-comp-scope-count"]',
    TOTAL: '[data-region="dim-comp-total"]',
    FILTERS: '[data-action="dim-comp-filters"]',
    BADGE: '[data-region="dim-comp-badge"]',
    PANEL: '[data-region="dim-comp-fpanel"]',
    PANELBODY: '[data-region="dim-comp-fpanel-body"]',
    CLEARFILTERS: '[data-action="dim-comp-clearfilters"]',
    SHOW: '[data-action="dim-comp-show"]',
    PERIOD: '[data-action="dim-comp-ev-period"]',
    PERIODLAYER: '[data-region="dim-comp-ev-periodlayer"]',
    PERIODDOT: '[data-region="dim-comp-ev-perioddot"]',
    QUICK: '[data-dim-quick]',
    FROM: '[data-region="dim-comp-ev-from"]',
    TO: '[data-region="dim-comp-ev-to"]',
    PERIODCLEAR: '[data-action="dim-comp-ev-periodclear"]',
    PERIODSHOW: '[data-action="dim-comp-ev-periodshow"]',
    CHIPS: '[data-region="dim-comp-chips"]',
    SEARCHOPEN: '[data-action="dim-comp-search-open"]',
    FIELD: '.dim-comp-search-field',
    INPUT: '.dim-comp-search-input',
    ERASER: '[data-action="dim-comp-search-clear"]',
    NOPRIOR: '[data-region="dim-comp-ev-noprior"]',
    NOMATCH: '[data-region="dim-comp-ev-nomatch"]',
    LOADING: '[data-region="dim-comp-ev-loading"]',
    FAILED: '[data-region="dim-comp-ev-failed"]',
    MORE: '[data-action="dim-comp-ev-more"]',
    DETAIL: '[data-region="dim-comp-ev-detail"]',
    DETAILBODY: '[data-region="dim-comp-ev-detailbody"]',
    CLOSE: '[data-action="dim-comp-ev-close"]',
    PROXY: '[data-dim-ev-proxy]',
    TRIGGER: '.dim-comp-plan-menu-trigger',
    TRIGGERNAME: '.visually-hidden',
};

/** What the page remembers between core's re-renders. */
const state = {
    userid: 0,
    platform: false,
    scope: 'all',
    text: '',
    filters: {competencyid: 0, planid: 0, courseid: 0},
    period: {from: '', to: ''},
    bounds: {from: 0, to: 0},
    nodes: new Map(),
    folds: new Map(),
    facets: {competencies: [], courses: [], plans: []},
    total: -1,
    hasmore: false,
    cursor: {time: 0, id: 0},
    loading: false,
    failed: false,
    ticket: 0,
    paints: 0,
    selected: '',
    layer: '',
    docwired: false,
};

/**
 * A text as the search compares it: lower case, accents dropped.
 *
 * @param {string} text The text.
 * @returns {string}
 */
const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/**
 * The current region: core replaces it whole, so it is looked up again rather than kept.
 *
 * @returns {HTMLElement|null}
 */
const region = () => document.querySelector(SELECTORS.ROOT);

/**
 * A new element with a class and, when given, its text.
 *
 * @param {string} tag The element's name.
 * @param {string} classname Its class list.
 * @param {string|undefined} text Its text, set as text.
 * @returns {HTMLElement}
 */
const make = (tag, classname, text) => {
    const element = document.createElement(tag);
    element.className = classname;
    if (text !== undefined) {
        element.textContent = text;
    }
    return element;
};

/**
 * A number read from an attribute, 0 when it is none.
 *
 * @param {Element} element The element.
 * @param {string} name The attribute.
 * @returns {number}
 */
const numberOf = (element, name) => {
    const value = Number(element.getAttribute(name));
    return Number.isFinite(value) ? value : 0;
};

/**
 * The competencies an item feeds, from its attribute.
 *
 * @param {Element} item The item.
 * @returns {number[]}
 */
const competenciesOf = (item) => (item.getAttribute('data-dim-comps') || '').split(/\s+/).filter(Boolean).map(Number);

/**
 * Whether a period is set.
 *
 * @returns {boolean}
 */
const hasPeriod = () => state.period.from !== '' || state.period.to !== '';

/**
 * A calendar day as an instant of the browser's own midnight, for when the service has not resolved the period.
 *
 * @param {string} day YYYY-MM-DD, or ''.
 * @param {number} shift Days to add.
 * @returns {number} Seconds, 0 for ''.
 */
const localInstant = (day, shift) => {
    const parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(day);
    if (!parts) {
        return 0;
    }
    return new Date(Number(parts[1]), Number(parts[2]) - 1, Number(parts[3]) + shift).getTime() / 1000;
};

/**
 * The period's bounds: the service's, in the reader's Moodle timezone, else the browser's.
 *
 * @returns {{from: number, to: number}}
 */
const bounds = () => {
    if (state.platform && (state.bounds.from || state.bounds.to)) {
        return state.bounds;
    }
    return {from: localInstant(state.period.from, 0), to: localInstant(state.period.to, 1)};
};

/**
 * The plan in force, from the facets.
 *
 * @returns {Object|null}
 */
const planInForce = () => state.facets.plans.find((plan) => plan.id === state.filters.planid) || null;

/**
 * The filters in force, as chips' labels need them: the group's key and the option's name.
 *
 * @returns {Array} [{key, name}].
 */
const inForce = () => GROUPS.filter((group) => state.filters[group.key] > 0).map((group) => {
    const option = state.facets[group.facet].find((one) => one.id === state.filters[group.key]);
    return {key: group.key, label: group.label, name: option ? option.name : String(state.filters[group.key])};
});

/**
 * Whether a prior-learning node passes the filters the server cannot apply to it.
 *
 * @param {Element} node The node.
 * @returns {boolean}
 */
const priorPasses = (node) => {
    if (state.filters.courseid > 0) {
        return false;
    }
    const feeds = competenciesOf(node);
    if (state.filters.competencyid > 0 && !feeds.includes(state.filters.competencyid)) {
        return false;
    }
    const plan = planInForce();
    if (state.filters.planid > 0 && (!plan || !feeds.some((id) => plan.competencyids.includes(id)))) {
        return false;
    }
    const range = bounds();
    const time = numberOf(node, 'data-dim-time');
    return !(range.from && time < range.from) && !(range.to && time >= range.to);
};

/**
 * Draw a folded line from what the pages added up.
 *
 * @param {Object} line The line: key, month, monthlabel, time, count, label.
 * @returns {Promise<HTMLElement>}
 */
const foldNode = async(line) => {
    const item = make('li', 'dim-comp-ev-item dim-comp-ev-fold');
    item.setAttribute('data-region', 'dim-comp-ev-fold');
    item.setAttribute('data-dim-kind', 'fold');
    item.setAttribute('data-dim-key', line.key);
    item.setAttribute('data-dim-time', String(line.time));
    item.setAttribute('data-dim-month', line.month);
    item.setAttribute('data-dim-monthlabel', line.monthlabel);
    const rail = make('span', 'dim-comp-ev-rail');
    rail.setAttribute('aria-hidden', 'true');
    const earlier = line.key.endsWith('|earlier');
    const words = line.count === 1
        ? await getString(earlier ? 'competency_evidencefoldearlierone' : 'competency_evidencefoldone', 'theme_dimensions',
            {component: line.label, month: line.monthlabel})
        : await getString(earlier ? 'competency_evidencefoldearlier' : 'competency_evidencefold', 'theme_dimensions',
            {count: line.count, component: line.label, month: line.monthlabel});
    item.append(make('span', 'dim-comp-ev-when'), rail, make('p', 'dim-comp-ev-quiet', words));
    return item;
};

/**
 * Ask for a page of the platform's records and keep it: the first page anew (reset), or the next one.
 *
 * @param {boolean} reset Whether this is the first page under the criteria in force.
 * @returns {Promise<void>}
 */
const fetchPage = async(reset) => {
    if (!state.platform || state.userid === 0) {
        return;
    }
    const ticket = ++state.ticket;
    const pending = new Pending('theme_dimensions/competencyevidence:feed');
    state.loading = true;
    state.failed = false;
    showProgress();
    try {
        const args = {
            userid: state.userid,
            competencyid: state.filters.competencyid,
            planid: state.filters.planid,
            courseid: state.filters.courseid,
            datefrom: state.period.from,
            dateto: state.period.to,
            beforetime: reset ? 0 : state.cursor.time,
            beforeid: reset ? 0 : state.cursor.id,
        };
        const answer = await Ajax.call([{methodname: FEED, args}])[0];
        if (ticket !== state.ticket) {
            return;
        }
        const records = answer.records.map((record) => {
            const flags = {};
            KINDS.forEach((kind) => {
                flags[`is${kind}`] = record.kind === kind;
            });
            return Object.assign({}, record, flags);
        });
        const {html} = await Templates.renderForPromise(RECORDS, {records});
        const lines = await Promise.all(answer.folds.map((line) => {
            const before = reset ? null : state.folds.get(line.key);
            const merged = before
                ? Object.assign({}, line, {count: before.data.count + line.count, time: Math.max(before.data.time, line.time)})
                : line;
            return foldNode(merged).then((node) => ({data: merged, node}));
        }));
        if (ticket !== state.ticket) {
            return;
        }
        if (reset) {
            state.nodes.clear();
            state.folds.clear();
            state.total = answer.total;
            state.facets = answer.facets;
        }
        const holder = document.createElement('template');
        holder.innerHTML = html;
        holder.content.querySelectorAll(SELECTORS.RECORD).forEach((node) => {
            state.nodes.set(node.getAttribute('data-dim-key'), node);
        });
        lines.forEach((line) => state.folds.set(line.data.key, line));
        state.bounds = {from: answer.periodfrom, to: answer.periodto};
        state.hasmore = answer.hasmore;
        state.cursor = {time: answer.nexttime, id: answer.nextid};
        state.loading = false;
        const current = region();
        if (current) {
            armFilters(current);
            await paint(current);
        }
    } catch (error) {
        if (ticket === state.ticket) {
            state.loading = false;
            state.failed = true;
            showProgress();
        }
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Show the loading line, the failure line and the button that loads older records as the state says.
 */
const showProgress = () => {
    const root = region();
    if (!root) {
        return;
    }
    const platformshown = state.platform && state.scope !== 'prior';
    root.querySelector(SELECTORS.LOADING).hidden = !(state.loading && platformshown);
    root.querySelector(SELECTORS.FAILED).hidden = !(state.failed && platformshown);
    root.querySelector(SELECTORS.MORE).hidden = !(platformshown && state.hasmore && !state.loading);
};

/**
 * Every item of the list in date order, newest first, with whether each is shown.
 *
 * Core's nodes come from the region; the records and the folded lines from the state. While older records are still
 * to be fetched, an evidence older than the last record fetched waits for them, so the list never shows a gap that a
 * later page would fill above it.
 *
 * @param {HTMLElement} root The region.
 * @returns {Array} [{node, shown, title}].
 */
const items = (root) => {
    const text = fold(state.text);
    const priorshown = state.scope !== 'platform';
    const platformshown = state.platform && state.scope !== 'prior';
    const floor = platformshown && state.hasmore ? state.cursor.time : 0;
    const all = [];
    root.querySelectorAll(SELECTORS.NODE).forEach((node) => {
        const title = node.querySelector(SELECTORS.TITLE);
        const matches = text === '' || fold(title ? title.textContent : '').includes(text);
        const waits = floor > 0 && numberOf(node, 'data-dim-time') < floor;
        all.push({node, shown: priorshown && matches && !waits && priorPasses(node), title: true});
    });
    state.nodes.forEach((node) => {
        const title = node.querySelector(SELECTORS.TITLE);
        const matches = text === '' || fold(title ? title.textContent : '').includes(text);
        all.push({node, shown: platformshown && matches, title: true});
    });
    state.folds.forEach((line) => {
        all.push({node: line.node, shown: platformshown && text === '', title: false});
    });
    const key = (node) => node.getAttribute('data-dim-key') || '';
    return all.sort((a, b) => (numberOf(b.node, 'data-dim-time') - numberOf(a.node, 'data-dim-time'))
        || key(b.node).localeCompare(key(a.node)));
};

/**
 * Put every item in order and head each month of the shown ones, moving core's nodes rather than copying them.
 *
 * @param {HTMLElement} root The region.
 * @param {Array} ordered What items() answered.
 */
const arrange = (root, ordered) => {
    const list = root.querySelector(SELECTORS.LIST);
    list.querySelectorAll(SELECTORS.MONTH).forEach((month) => month.remove());
    const children = [];
    let month = '';
    ordered.forEach((item) => {
        item.node.hidden = !item.shown;
        if (item.shown && item.node.getAttribute('data-dim-month') !== month) {
            month = item.node.getAttribute('data-dim-month') || '';
            const head = make('li', 'dim-comp-ev-month');
            head.setAttribute('data-region', 'dim-comp-ev-month');
            head.append(make('h3', 'dim-comp-ev-monthtitle', item.node.getAttribute('data-dim-monthlabel') || ''));
            children.push(head);
        }
        children.push(item.node);
    });
    list.replaceChildren(...children);
};

/**
 * The chips of the criteria in force: each filter, the period, the search text while its field is closed.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<Array>} [{label, remove}].
 */
const chipsOf = async(root) => {
    const chips = [];
    for (const filter of inForce()) {
        chips.push({
            label: await getString('competency_evidencefilterchip', 'theme_dimensions',
                {group: await getString(filter.label, 'theme_dimensions'), value: filter.name}),
            remove: () => {
                state.filters[filter.key] = 0;
                return true;
            },
        });
    }
    if (hasPeriod()) {
        chips.push({
            label: await periodLabel(),
            remove: () => {
                state.period = {from: '', to: ''};
                return true;
            },
        });
    }
    if (state.text !== '' && root.querySelector(SELECTORS.FIELD).hidden) {
        chips.push({
            label: await getString('competency_evidencesearchchip', 'theme_dimensions', state.text),
            remove: () => {
                clearText(root);
                return false;
            },
        });
    }
    return chips;
};

/**
 * A day as the reader's language writes it, from YYYY-MM-DD.
 *
 * @param {string} day The day.
 * @returns {string}
 */
const dayLabel = (day) => {
    const date = new Date(`${day}T00:00:00Z`);
    if (Number.isNaN(date.getTime())) {
        return day;
    }
    return new Intl.DateTimeFormat(document.documentElement.lang || undefined, {dateStyle: 'medium', timeZone: 'UTC'})
        .format(date);
};

/**
 * The period in words, for its chip and its button's name.
 *
 * @returns {Promise<string>}
 */
const periodLabel = async() => {
    const {from, to} = state.period;
    let words = '';
    if (from !== '' && to !== '') {
        words = await getString('competency_evidenceperiodrange', 'theme_dimensions', {from: dayLabel(from), to: dayLabel(to)});
    } else if (from !== '') {
        words = await getString('competency_evidenceperiodsince', 'theme_dimensions', dayLabel(from));
    } else {
        words = await getString('competency_evidenceperioduntil', 'theme_dimensions', dayLabel(to));
    }
    return getString('competency_evidenceperiodset', 'theme_dimensions', words);
};

/**
 * Draw the chips row: a button per chip, named for what it removes, then "Clear all" with two or more.
 *
 * A chip of a filter or of the period asks for the records again; the search's chip only repaints.
 *
 * @param {HTMLElement} root The region.
 * @param {Array} chips What chipsOf() answered.
 * @returns {Promise<void>}
 */
const writeChips = async(root, chips) => {
    const buttons = await Promise.all(chips.map(async(chip) => {
        const button = make('button', 'dim-comp-chip', chip.label);
        button.type = 'button';
        button.setAttribute('aria-label', await getString('competency_evidenceremovefilter', 'theme_dimensions', chip.label));
        const cross = make('i', 'fa fa-xmark');
        cross.setAttribute('aria-hidden', 'true');
        button.append(cross);
        button.addEventListener('click', () => {
            const refetch = chip.remove();
            focusAfterRemoval(root);
            if (refetch) {
                fetchPage(true);
            }
            paint(root);
        });
        return button;
    }));
    if (buttons.length > 1) {
        const all = make('button', 'dim-comp-clearall', await getString('competency_evidenceclearall', 'theme_dimensions'));
        all.type = 'button';
        all.addEventListener('click', () => {
            state.filters = {competencyid: 0, planid: 0, courseid: 0};
            state.period = {from: '', to: ''};
            clearText(root);
            focusAfterRemoval(root);
            fetchPage(true);
            paint(root);
        });
        buttons.push(all);
    }
    const row = root.querySelector(SELECTORS.CHIPS);
    row.replaceChildren(...buttons);
    row.hidden = buttons.length === 0;
};

/**
 * After a chip went, the focus goes to Filters, else to the search button.
 *
 * @param {HTMLElement} root The region.
 */
const focusAfterRemoval = (root) => {
    const next = [root.querySelector(SELECTORS.FILTERS), root.querySelector(SELECTORS.SEARCHOPEN)]
        .find((element) => element && !element.hidden);
    if (next) {
        next.focus();
    }
};

/**
 * How many items the count says: what the criteria match, the records not fetched yet being in the service's total;
 * while a search is typed, what is shown, since the search reads the titles on the page only.
 *
 * @param {Array} ordered What items() answered.
 * @returns {number}
 */
const countOf = (ordered) => {
    const titled = ordered.filter((item) => item.shown && item.title);
    if (state.text !== '') {
        return titled.length;
    }
    const priorshown = titled.filter((item) => item.node.matches(SELECTORS.NODE)).length;
    if (state.scope === 'prior' || !state.platform) {
        return priorshown;
    }
    return priorshown + (state.total >= 0 ? state.total : state.nodes.size);
};

/**
 * Write what the bar and the list's messages say without a string: the scope's counts and choice, the messages shown,
 * Filters' badge and the period's dot.
 *
 * @param {HTMLElement} root The region.
 * @param {number} shown The titled items shown.
 * @param {number} filtered The filters in force.
 */
const writeState = (root, shown, filtered) => {
    const prior = root.querySelectorAll(SELECTORS.NODE).length;
    const loaded = state.nodes.size;
    const platform = Math.max(state.total, loaded);
    const noprior = root.querySelector(SELECTORS.NOPRIOR);
    if (noprior) {
        noprior.hidden = state.platform && state.scope !== 'prior' && (loaded > 0 || state.folds.size > 0);
    }
    const nothing = prior === 0 && loaded === 0 && state.folds.size === 0 && filtered === 0 && !hasPeriod();
    root.querySelector(SELECTORS.NOMATCH).hidden = shown > 0 || state.loading || nothing;

    const counts = {all: prior + platform, prior, platform};
    root.querySelectorAll(SELECTORS.CELL).forEach((cell) => {
        cell.querySelector(SELECTORS.CELLCOUNT).textContent = String(counts[cell.dataset.value] || 0);
        const chosen = cell.dataset.value === state.scope;
        cell.setAttribute('aria-checked', chosen ? 'true' : 'false');
        cell.tabIndex = chosen ? 0 : -1;
    });
    root.querySelector(SELECTORS.SCOPE).hidden = !state.platform;
    const badge = root.querySelector(SELECTORS.BADGE);
    badge.textContent = String(filtered);
    badge.hidden = filtered === 0;
    root.querySelector(SELECTORS.PERIODDOT).hidden = !hasPeriod();
};

/**
 * Show what the scope, the filters, the period and the search leave, in date order, and say how many.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<void>}
 */
const paint = async(root) => {
    const ordered = items(root);
    arrange(root, ordered);
    showProgress();
    const shown = ordered.filter((item) => item.shown && item.title).length;
    const total = countOf(ordered);
    const filtered = inForce().length;
    writeState(root, shown, filtered);
    keepSelection(root);

    const paintid = ++state.paints;
    const pending = new Pending('theme_dimensions/competencyevidence:count');
    try {
        const words = total === 1
            ? await getString('competency_evidencecountone', 'theme_dimensions')
            : await getString('competency_evidencecount', 'theme_dimensions', total);
        const filtersname = filtered === 0
            ? await getString('competency_evidencefilters', 'theme_dimensions')
            : await getString('competency_evidencefilterscount', 'theme_dimensions', filtered);
        const periodname = hasPeriod() ? await periodLabel() : await getString('competency_evidenceperiod', 'theme_dimensions');
        const chips = await chipsOf(root);
        if (paintid === state.paints) {
            root.querySelector(SELECTORS.TOTAL).textContent = words;
            const filters = root.querySelector(SELECTORS.FILTERS);
            filters.setAttribute('aria-label', filtersname);
            filters.setAttribute('data-dim-comp-tip', filtersname);
            root.querySelector(SELECTORS.PERIOD).setAttribute('aria-label', periodname);
            await writeChips(root, chips);
        }
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * The item a key names, in the region or among the kept records.
 *
 * @param {HTMLElement} root The region.
 * @param {string} key The item's key.
 * @returns {HTMLElement|null}
 */
const itemOf = (root, key) => {
    if (key === '') {
        return null;
    }
    return [...root.querySelectorAll(SELECTORS.ITEM)].find((item) => item.getAttribute('data-dim-key') === key)
        || state.nodes.get(key) || null;
};

/**
 * Open an item's detail beside the list, from its own template element.
 *
 * @param {HTMLElement} root The region.
 * @param {string} key The item's key.
 * @param {boolean} focus Whether the panel takes the focus.
 */
const openDetail = (root, key, focus) => {
    const item = itemOf(root, key);
    const source = item ? item.querySelector(SELECTORS.SOURCE) : null;
    const panel = root.querySelector(SELECTORS.DETAIL);
    if (!source || !panel) {
        closeDetail(root, false);
        return;
    }
    state.selected = key;
    const body = panel.querySelector(SELECTORS.DETAILBODY);
    body.replaceChildren(source.content.cloneNode(true));
    const title = body.querySelector('h3');
    if (title) {
        title.id = `${panel.id}-title`;
        panel.setAttribute('aria-labelledby', title.id);
    }
    root.querySelectorAll(SELECTORS.ITEM).forEach((other) => other.removeAttribute('aria-current'));
    item.setAttribute('aria-current', 'true');
    panel.hidden = false;
    root.classList.add('dim-comp-ev--detail');
    if (focus) {
        panel.focus();
    }
};

/**
 * Close the detail, the focus going back to the item's title when asked.
 *
 * @param {HTMLElement} root The region.
 * @param {boolean} focus Whether the item's title takes the focus.
 */
const closeDetail = (root, focus) => {
    const item = itemOf(root, state.selected);
    state.selected = '';
    const panel = root.querySelector(SELECTORS.DETAIL);
    if (panel) {
        panel.hidden = true;
        panel.querySelector(SELECTORS.DETAILBODY).replaceChildren();
    }
    root.classList.remove('dim-comp-ev--detail');
    root.querySelectorAll(SELECTORS.ITEM).forEach((other) => other.removeAttribute('aria-current'));
    const link = item ? item.querySelector(SELECTORS.OPEN) : null;
    if (focus && link) {
        link.focus();
    }
};

/**
 * Keep the open detail while its item is shown; close it when the item went (deleted, filtered out).
 *
 * @param {HTMLElement} root The region.
 */
const keepSelection = (root) => {
    if (state.selected === '') {
        return;
    }
    const item = itemOf(root, state.selected);
    if (!item || item.hidden || !root.contains(item)) {
        closeDetail(root, false);
    } else if (root.querySelector(SELECTORS.DETAIL).hidden) {
        openDetail(root, state.selected, false);
    }
};

/**
 * Press core's own menu entry of the evidence a detail shows: tool_lp/menubar hears the press on the entry's item and
 * calls core's handler, which finds the evidence from the node above the entry.
 *
 * @param {HTMLElement} root The region.
 * @param {string} action The entry's data-action, one of PROXIES.
 */
const pressEntry = (root, action) => {
    if (!PROXIES.includes(action) || !state.selected.startsWith('p')) {
        return;
    }
    const id = state.selected.slice(1);
    const node = [...root.querySelectorAll(SELECTORS.NODE)].find((one) => one.getAttribute('data-id') === id);
    const entry = node ? [...node.querySelectorAll('[data-action]')].find((one) => one.getAttribute('data-action') === action)
        : null;
    if (entry) {
        entry.click();
    }
};

/**
 * A layer of the bar and its trigger: 'panel' is Filters' panel, 'period' the period's.
 *
 * @param {HTMLElement} root The region.
 * @param {string} name 'panel' or 'period'.
 * @returns {{layer: HTMLElement, trigger: HTMLElement}}
 */
const partsOf = (root, name) => ({
    layer: root.querySelector(name === 'panel' ? SELECTORS.PANEL : SELECTORS.PERIODLAYER),
    trigger: root.querySelector(name === 'panel' ? SELECTORS.FILTERS : SELECTORS.PERIOD),
});

/**
 * Place a layer under the bar's control row, at the bar's end; at the narrow step the stylesheet makes it a sheet.
 *
 * @param {HTMLElement} root The region.
 * @param {string} name 'panel' or 'period'.
 */
const place = (root, name) => {
    const {layer} = partsOf(root, name);
    layer.style.removeProperty('top');
    if (window.matchMedia(NARROW).matches) {
        return;
    }
    const bar = root.querySelector(SELECTORS.BAR).getBoundingClientRect();
    const top = [SELECTORS.SCOPE, '[data-region="dim-comp-cluster"]', '[data-region="dim-comp-search"]']
        .map((selector) => root.querySelector(selector))
        .filter((element) => element && !element.hidden)
        .reduce((bottom, element) => Math.max(bottom, element.getBoundingClientRect().bottom), bar.top);
    layer.style.top = `${Math.round(top - bar.top + 6)}px`;
};

/**
 * Close the open layer, if any, its trigger taking the focus back when asked.
 *
 * @param {HTMLElement} root The region.
 * @param {boolean} focus Whether the trigger takes the focus.
 */
const closeLayer = (root, focus) => {
    if (state.layer === '') {
        return;
    }
    const {layer, trigger} = partsOf(root, state.layer);
    state.layer = '';
    layer.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    if (focus) {
        trigger.focus();
    }
};

/**
 * Open a layer, closing the other: one layer of the bar is open at a time.
 *
 * @param {HTMLElement} root The region.
 * @param {string} name 'panel' or 'period'.
 */
const openLayer = (root, name) => {
    closeLayer(root, false);
    const {layer, trigger} = partsOf(root, name);
    state.layer = name;
    layer.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    place(root, name);
    const first = layer.querySelector('input, button');
    if (first) {
        first.focus();
    }
};

/**
 * Apply a press on an option of a group: a press on the chosen one releases it, any other replaces the choice.
 *
 * @param {HTMLElement} root The region.
 * @param {object} group The group of GROUPS.
 * @param {number} id The pressed option's id.
 */
const choose = (root, group, id) => {
    state.filters[group.key] = state.filters[group.key] === id ? 0 : id;
    fetchPage(true);
    paint(root);
};

/**
 * A row of a searched list: a pressed toggle with a decorative check box, its name as text.
 *
 * @param {HTMLElement} root The region.
 * @param {object} group The group of GROUPS.
 * @param {object} option The facet's option.
 * @param {Function} redraw Draws the list's rows again.
 * @returns {HTMLElement}
 */
const listRow = (root, group, option, redraw) => {
    const row = make('button', 'dim-comp-fpanel-row');
    row.type = 'button';
    row.setAttribute('data-value', String(option.id));
    row.setAttribute('aria-pressed', state.filters[group.key] === option.id ? 'true' : 'false');
    const box = make('span', 'dim-comp-menu-box');
    box.setAttribute('aria-hidden', 'true');
    box.append(make('i', 'fa fa-check'));
    row.append(box, make('span', '', option.name));
    row.addEventListener('click', async() => {
        choose(root, group, option.id);
        await redraw();
        const again = [...row.parentNode.querySelectorAll('button')]
            .find((other) => other.getAttribute('data-value') === String(option.id));
        if (again) {
            again.focus();
        }
    });
    return row;
};

/**
 * Draw a searched list's rows: the option in force pinned on top whatever the search holds, then the matches, at
 * most ROWS of them, with a note when more match or when none does.
 *
 * @param {HTMLElement} root The region.
 * @param {HTMLElement} list The list.
 * @param {object} group The group of GROUPS.
 * @param {object[]} options The group's facet.
 * @param {string} find The search's text.
 * @returns {Promise<void>}
 */
const writeRows = async(root, list, group, options, find) => {
    const pending = new Pending('theme_dimensions/competencyevidence:rows');
    const ticket = (list.dimTicket = (list.dimTicket || 0) + 1);
    try {
        const folded = fold(find);
        const chosen = state.filters[group.key];
        const pinned = options.filter((option) => option.id === chosen);
        const rest = options.filter((option) => option.id !== chosen && (folded === '' || fold(option.name).includes(folded)));
        const shown = [...pinned, ...rest].slice(0, ROWS);
        const redraw = () => writeRows(root, list, group, options, find);
        const rows = shown.map((option) => listRow(root, group, option, redraw));
        const more = pinned.length + rest.length - shown.length;
        if (more > 0) {
            rows.push(make('p', 'dim-comp-fpanel-note', await getString('competency_planfindmore', 'theme_dimensions', more)));
        } else if (shown.length === 0) {
            rows.push(make('p', 'dim-comp-fpanel-note', await getString(group.none, 'theme_dimensions')));
        }
        if (ticket === list.dimTicket) {
            list.replaceChildren(...rows);
        }
    } catch (error) {
        window.console.error(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Build the Filters panel from the facets: a group per filter with options, its label on a line of its own, each
 * option a pressed toggle (a searched list past LISTED), one choice per group, applied on press.
 *
 * @param {HTMLElement} root The region.
 * @returns {Promise<void>}
 */
const buildPanel = async(root) => {
    const body = root.querySelector(SELECTORS.PANELBODY);
    const uid = root.querySelector(SELECTORS.PANEL).id;
    const parts = [];
    for (const [index, group] of GROUPS.entries()) {
        const options = state.facets[group.facet] || [];
        if (options.length === 0) {
            continue;
        }
        const head = make('div', 'dim-comp-fpanel-head', await getString(group.label, 'theme_dimensions'));
        head.id = `${uid}-g${index}`;
        if (options.length > LISTED) {
            const label = await getString(group.find, 'theme_dimensions', options.length);
            const find = make('input', 'form-control form-control-sm dim-comp-fpanel-find');
            find.type = 'search';
            find.autocomplete = 'off';
            find.setAttribute('aria-label', label);
            find.placeholder = label;
            const rows = make('div', 'dim-comp-fpanel-list');
            rows.setAttribute('role', 'group');
            rows.setAttribute('aria-labelledby', head.id);
            find.addEventListener('input', () => writeRows(root, rows, group, options, find.value));
            parts.push(head, find, rows);
            await writeRows(root, rows, group, options, '');
            continue;
        }
        const list = make('div', 'dim-comp-fpanel-opts');
        list.setAttribute('role', 'group');
        list.setAttribute('aria-labelledby', head.id);
        options.forEach((option) => {
            const button = make('button', 'dim-comp-fpanel-opt', option.name);
            button.type = 'button';
            button.setAttribute('aria-pressed', state.filters[group.key] === option.id ? 'true' : 'false');
            button.addEventListener('click', () => {
                choose(root, group, option.id);
                list.querySelectorAll('button').forEach((other) => {
                    other.setAttribute('aria-pressed', other === button && state.filters[group.key] ? 'true' : 'false');
                });
            });
            list.append(button);
        });
        parts.push(head, list);
    }
    body.replaceChildren(...parts);
};

/**
 * Show Filters only where the service offered something to filter by.
 *
 * @param {HTMLElement} root The region.
 */
const armFilters = (root) => {
    const filters = root.querySelector(SELECTORS.FILTERS);
    filters.hidden = !state.platform || GROUPS.every((group) => (state.facets[group.facet] || []).length === 0);
    if (filters.hidden && state.layer === 'panel') {
        closeLayer(root, false);
    }
};

/**
 * A day of the browser's calendar as YYYY-MM-DD.
 *
 * @param {Date} date The day.
 * @returns {string}
 */
const isoOf = (date) => [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0')].join('-');

/**
 * The two days a quick option of the period stands for, ending today.
 *
 * @param {string} quick month, quarter or year.
 * @returns {{from: string, to: string}}
 */
const quickPeriod = (quick) => {
    const today = new Date();
    const start = new Date(today.getFullYear(), today.getMonth(), 1);
    if (quick === 'quarter') {
        start.setMonth(start.getMonth() - 2);
    } else if (quick === 'year') {
        start.setMonth(start.getMonth() - 11);
    }
    return {from: isoOf(start), to: isoOf(today)};
};

/**
 * Arm the period's layer: the quick options fill both days, a day typed is taken, "Clear period" empties both, and
 * each change asks for the records again.
 *
 * @param {HTMLElement} root The region.
 */
const wirePeriod = (root) => {
    const layer = root.querySelector(SELECTORS.PERIODLAYER);
    const from = layer.querySelector(SELECTORS.FROM);
    const to = layer.querySelector(SELECTORS.TO);
    const quicks = [...layer.querySelectorAll(SELECTORS.QUICK)];
    const sync = () => {
        from.value = state.period.from;
        to.value = state.period.to;
        quicks.forEach((quick) => {
            const range = quickPeriod(quick.getAttribute('data-dim-quick'));
            const same = range.from === state.period.from && range.to === state.period.to;
            quick.setAttribute('aria-pressed', same ? 'true' : 'false');
        });
    };
    const apply = () => {
        state.bounds = {from: 0, to: 0};
        sync();
        fetchPage(true);
        paint(root);
    };
    sync();
    root.querySelector(SELECTORS.PERIOD).addEventListener('click', () => {
        if (state.layer === 'period') {
            closeLayer(root, false);
        } else {
            sync();
            openLayer(root, 'period');
        }
    });
    quicks.forEach((quick) => quick.addEventListener('click', () => {
        state.period = quickPeriod(quick.getAttribute('data-dim-quick'));
        apply();
    }));
    [from, to].forEach((input) => input.addEventListener('change', () => {
        const valid = (value) => (/^\d{4}-\d{2}-\d{2}$/.test(value) ? value : '');
        state.period = {from: valid(from.value), to: valid(to.value)};
        apply();
    }));
    root.querySelector(SELECTORS.PERIODCLEAR).addEventListener('click', () => {
        state.period = {from: '', to: ''};
        apply();
    });
    root.querySelector(SELECTORS.PERIODSHOW).addEventListener('click', () => closeLayer(root, true));
    layer.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeLayer(root, true);
        }
    });
};

/**
 * Arm Filters and its panel.
 *
 * @param {HTMLElement} root The region.
 */
const wireFilters = (root) => {
    const panel = root.querySelector(SELECTORS.PANEL);
    root.querySelector(SELECTORS.FILTERS).addEventListener('click', async() => {
        if (state.layer === 'panel') {
            closeLayer(root, false);
            return;
        }
        await buildPanel(root);
        openLayer(root, 'panel');
    });
    panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeLayer(root, true);
        }
    });
    root.querySelector(SELECTORS.CLEARFILTERS).addEventListener('click', async() => {
        state.filters = {competencyid: 0, planid: 0, courseid: 0};
        await buildPanel(root);
        fetchPage(true);
        paint(root);
    });
    root.querySelector(SELECTORS.SHOW).addEventListener('click', () => closeLayer(root, true));
};

/**
 * Arm the scope: a press or the arrows move the choice, roving the tab stop.
 *
 * @param {HTMLElement} root The region.
 */
const wireScope = (root) => {
    const cells = [...root.querySelectorAll(SELECTORS.CELL)];
    const choose = (value, focus) => {
        state.scope = value;
        if (focus) {
            cells.find((cell) => cell.dataset.value === value).focus();
        }
        paint(root);
    };
    cells.forEach((cell, index) => {
        cell.addEventListener('click', () => choose(cell.dataset.value, false));
        cell.addEventListener('keydown', (e) => {
            const last = cells.length - 1;
            const moves = {
                ArrowRight: index === last ? 0 : index + 1,
                ArrowDown: index === last ? 0 : index + 1,
                ArrowLeft: index === 0 ? last : index - 1,
                ArrowUp: index === 0 ? last : index - 1,
                Home: 0,
                End: last,
            };
            if (moves[e.key] === undefined) {
                return;
            }
            e.preventDefault();
            choose(cells[moves[e.key]].dataset.value, true);
        });
    });
};

/**
 * Open or close the search field in place of its button.
 *
 * @param {HTMLElement} root The region.
 * @param {boolean} open Whether to open it.
 * @param {boolean} focus Whether the field (open) or the button (closed) takes the focus.
 */
const setSearchOpen = (root, open, focus) => {
    const button = root.querySelector(SELECTORS.SEARCHOPEN);
    const field = root.querySelector(SELECTORS.FIELD);
    button.hidden = open;
    field.hidden = !open;
    button.classList.toggle('dim-comp-iconbtn--active', !open && state.text !== '');
    if (focus) {
        (open ? root.querySelector(SELECTORS.INPUT) : button).focus();
    }
    paint(root);
};

/**
 * Empty the search, from its chip or "Clear all": the field folds back to its button.
 *
 * @param {HTMLElement} root The region.
 */
const clearText = (root) => {
    state.text = '';
    root.querySelector(SELECTORS.INPUT).value = '';
    root.querySelector(SELECTORS.ERASER).hidden = true;
    setSearchOpen(root, false, false);
};

/**
 * Arm the search in place: the button opens the field, Escape closes it keeping the text, the eraser clears it.
 *
 * @param {HTMLElement} root The region.
 */
const wireSearch = (root) => {
    const input = root.querySelector(SELECTORS.INPUT);
    const eraser = root.querySelector(SELECTORS.ERASER);
    input.value = state.text;
    eraser.hidden = state.text === '';
    root.querySelector(SELECTORS.FIELD).hidden = state.text === '';
    root.querySelector(SELECTORS.SEARCHOPEN).hidden = state.text !== '';
    root.querySelector(SELECTORS.SEARCHOPEN).addEventListener('click', () => setSearchOpen(root, true, true));
    input.addEventListener('input', () => {
        state.text = input.value;
        eraser.hidden = state.text === '';
        paint(root);
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            setSearchOpen(root, false, true);
        }
    });
    input.addEventListener('blur', () => {
        if (input.value === '' && !root.querySelector(SELECTORS.FIELD).contains(document.activeElement)) {
            setSearchOpen(root, false, false);
        }
    });
    eraser.addEventListener('click', () => {
        input.value = '';
        state.text = '';
        eraser.hidden = true;
        input.focus();
        paint(root);
    });
};

/**
 * Arm the list and the detail on one render of the region: a plain press on a title opens the detail (a modified
 * press follows the link), the close button and Escape close it, a detail's button presses core's entry, and "Load
 * older records" asks for the next page.
 *
 * @param {HTMLElement} root The region.
 */
const wireList = (root) => {
    root.addEventListener('click', (e) => {
        const target = e.target instanceof Element ? e.target : null;
        const link = target ? target.closest(SELECTORS.OPEN) : null;
        if (link && root.contains(link) && e.button === 0 && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey) {
            const item = link.closest(SELECTORS.ITEM);
            if (item && item.querySelector(SELECTORS.SOURCE)) {
                e.preventDefault();
                openDetail(root, item.getAttribute('data-dim-key'), true);
            }
            return;
        }
        const proxy = target ? target.closest(SELECTORS.PROXY) : null;
        if (proxy) {
            e.preventDefault();
            pressEntry(root, proxy.getAttribute('data-dim-ev-proxy'));
        }
    });
    root.querySelector(SELECTORS.CLOSE).addEventListener('click', () => closeDetail(root, true));
    root.querySelector(SELECTORS.DETAIL).addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeDetail(root, true);
        }
    });
    root.querySelector(SELECTORS.MORE).addEventListener('click', () => fetchPage(false));
};

/**
 * The document's listener, wired once for the page view: a press outside the open layer and its trigger closes it.
 */
const wireDocument = () => {
    if (state.docwired) {
        return;
    }
    state.docwired = true;
    document.addEventListener('click', (e) => {
        const current = region();
        if (!current || state.layer === '' || !document.contains(e.target)) {
            return;
        }
        const {layer, trigger} = partsOf(current, state.layer);
        if (!layer.contains(e.target) && !trigger.contains(e.target)) {
            closeLayer(current, false);
        }
    });
};

/**
 * Name each item's menu trigger in its tooltip, as text, from the visually hidden name the template wrote.
 *
 * @param {HTMLElement} root The region.
 */
const nameTriggers = (root) => {
    root.querySelectorAll(SELECTORS.TRIGGER).forEach((trigger) => {
        const name = trigger.querySelector(SELECTORS.TRIGGERNAME);
        if (name) {
            trigger.setAttribute('data-dim-comp-tip', name.textContent.trim());
        }
    });
};

/**
 * Start on the first paint, and again after each of core's re-renders of the region.
 */
export const init = () => {
    const root = region();
    if (!root) {
        return;
    }
    if (root.hasAttribute('data-dim-firstpaint')) {
        state.platform = root.getAttribute('data-dim-platform') === '1';
        state.nodes.clear();
        state.folds.clear();
    }
    // Core's own key, on the root of both renders: an empty list has no node to read it from.
    const userid = Number(root.getAttribute('data-dim-userid'));
    state.userid = Number.isInteger(userid) && userid > 0 ? userid : 0;
    nameTriggers(root);
    state.layer = '';
    wireDocument();
    wireScope(root);
    wireFilters(root);
    wirePeriod(root);
    wireSearch(root);
    wireList(root);
    armFilters(root);
    root.querySelector(SELECTORS.BAR).hidden = false;
    paint(root);
    fetchPage(true);
};
