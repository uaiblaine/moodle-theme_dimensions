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
 * The filter line of the showcase, on both shells: its folding, the search that grows over it and its
 * suggestions, its floating layers and its rows that scroll sideways.
 *
 * Drawn on both shells; on the visitor's no star is drawn and the scope may be absent: it draws what the server
 * drew. block_compass's FilterLine implements the same contract on its own; nothing is shared.
 *
 * Who owns what. The mount node is the server's line itself (dim-line), which the listing's
 * mountLine() names for core's react_autoinit, so the line keeps its place, its classes and the sibling
 * rules the stylesheet writes against it. A mount replaces its markup, so the first render draws the
 * markup the server drew, read off it ({@see ./model read()}), with the same names inside the same GET
 * form - the no-JavaScript submits, the listing's form reading and every Behat step keep working. React
 * draws the line's own controls: the scope, the search with its eraser and its icon, the star, the two
 * summaries (Filters and its badge, Organise), the view toggle, the close button and the listbox. Three
 * frames it draws hold server markup it adopts whole on the first commit and never diffs inside: the
 * Filters panel (More statuses, the custom fields, the period with its calendar, the quick dates),
 * the Organise menu and the managers' menu. The listing's painters keep painting those three, and paint
 * nothing React draws: they tell the line instead, through events on it ({@see EVENTS}), which
 * js/esm/src/listing/events.ts spells:
 *
 * - `dim-line:state`, detail {query, chip, fav, view} or any part of it: the listing's own state, after a
 *   press it handled, Back, a clear or a removed chip;
 * - `dim-line:answer`, detail {scope, favourites, filtercount, filtercountlabel}: an answer's numbers;
 * - `dim-line:dropterm`, sent by the line before it presses the control a suggestion names, so the one
 *   request that control causes carries no term;
 * - `dim-line:mounted`, sent once the line is drawn, after it marked itself drawn (data-line-drawn), which
 *   is what the painters read;
 * - `dim-line:failed`, sent instead where the markup is not a line this component can draw, so the listing
 *   releases what it holds for the mount: the line stays the server's.
 *
 * A second mount draws the line again. Core's react_autoinit unmounts a mounted node that leaves the document
 * and mounts it when it comes back, so a line moved after the hand-over - or under an ancestor that moved -
 * is mounted a second time onto a node React has just emptied. Unmounting therefore gives the adopted frames'
 * nodes back to the server's frames and keeps them, with the line's last state, for that node
 * ({@see KEPT}); a later mount draws from them instead of reading markup that is no longer there.
 *
 * The step is measured once per content on the line's own width with every scope cell drawn
 * ({@see ./measure stepWidths()}), and taken again from a width alone on every ResizeObserver entry. The
 * first measure is the observer's first entry's, after the browser's own layout and before the paint, so the
 * line is folded before it is painted and the commit forces no layout. Every request is the listing's:
 * nothing here calls a web service, reads the session or composes a word.
 *
 * @module     theme_dimensions/filterline/FilterLine
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment, useLayoutEffect, useReducer, useRef, useState} from 'react';
import type {KeyboardEvent, ReactNode, RefObject} from 'react';
import {flushSync} from 'react-dom';
import Pending from '@moodle/lms/core/pending';
import {normalise, pieces} from './mark';
import {importPreview} from './lazy';
import type {PreviewModule} from './lazy';
import {contentWidth, fades, itemOf, place, placeList, reveal, shown, stepWidths} from './measure';
import {SELECTORS, answered, read, stated} from './model';
import type {Answer, Line, Told} from './model';
import {MIN_TERM, offers, remember, suggest} from './suggest';
import type {Offer, Rest} from './suggest';
import {CLASSES as STEP_CLASSES, TWO_LINES, choose, morphs, paint, same, settled} from './steps';
import type {Fold} from './steps';
import {LINE, LINE_DRAWN} from '../listing/events';

/** The events the line and the listing exchange on the line, spelled once in listing/events.ts. */
export const EVENTS = LINE;

/** The attribute the line carries once this component draws it: the listing's painters read it. */
export const DRAWN = LINE_DRAWN;

/** Milliseconds a Tab press counts as the reason the field was focused. */
const TAB_INTENT = 1000;

/** The toolbar's other hooks this component reads. */
const HOOKS = {
    listing: '[data-region="dim-vitrine"]',
    chips: '[data-region="dim-line-chips"]',
    showas: '[data-region="dim-showas"] [data-action="dim-view"]',
    daterange: '[data-region="dim-daterange"]',
    option: '[role="option"]',
};

/** The class the form carries while the Filters panel is a bottom sheet. */
const SHEET = 'dim-toolbar--sheet';

/** The class of the active option of the listbox. */
const ACTIVE = 'dim-line__option--active';

/** The functions the native listeners call, by name: always the latest render's. */
type Handlers = Record<string, (...args: never[]) => void>;

/** What data-react-props carries: the id of the toolbar form the line is in. */
type FilterLineProps = {form: string};

/** What an unmount keeps of a drawn line for the next mount of the same node: the last state and the server's frames. */
type Kept = {model: Line, panel: Element | null, menu: Element | null, manage: Element | null};

/**
 * The lines this component drew and was unmounted from, by node: what a mount onto a line React has emptied draws.
 *
 * A WeakMap, so a line removed for good takes its entry with it.
 */
const KEPT: WeakMap<Element, Kept> = new WeakMap();

/** The line's behaviour state, read and written synchronously, never through a render. */
type Live = {
    fold: Fold | null,
    widths: number[],
    width: number,
    searching: boolean,
    open: boolean,
    items: Offer[],
    active: number,
    from: HTMLElement | null,
    quiet: boolean,
    intent: boolean,
    tabat: number,
    refocus: Element | null,
    // Whether the first measure was taken: until then nothing on the line reads layout ({@see measureFirst}).
    measuredOnce: boolean,
    // The line's own inline visibility while it is hidden until the first fold; null while it is not hidden.
    veiled: string | null,
};

/**
 * Whether the reader asked for no motion.
 *
 * @returns {boolean}
 */
const still = (): boolean => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Move the server's nodes of one frame into the frame this component drew, untouched.
 *
 * @param {Element|null} source The server's frame.
 * @param {Element|null} target The component's frame.
 * @returns {void}
 */
const adopt = (source: Element | null, target: Element | null): void => {
    if (source && target && source !== target) {
        target.append(...Array.from(source.childNodes));
    }
};

/**
 * The element of the drawn line that stands for one the reader had focused on the server's line.
 *
 * @param {HTMLElement} line The line.
 * @param {Element} was The server's element.
 * @returns {HTMLElement|null}
 */
const counterpart = (line: HTMLElement, was: Element): HTMLElement | null => {
    if (was.id) {
        const found = line.querySelector<HTMLElement>(`[id="${was.id}"]`);
        if (found) {
            return found;
        }
    }
    const action = was.getAttribute('data-action');
    if (action) {
        const value = was.getAttribute('value');
        const selector = `[data-action="${action}"]` + (value === null ? '' : `[value="${value}"]`);
        return line.querySelector<HTMLElement>(selector);
    }
    const summary = was.closest('details');
    const region = summary ? summary.getAttribute('data-region') : null;
    return region ? line.querySelector<HTMLElement>(`[data-region="${region}"] > summary`) : null;
};

/**
 * The filter line.
 *
 * @param {object} props The id of the toolbar form; see FilterLineProps.
 * @returns {object|null} The line's controls, or nothing where the markup is not a line.
 */
const FilterLine = ({form}: FilterLineProps) => {
    // Read once, during the first render, while the server's markup is still in the line.
    const [setup] = useState(() => {
        const toolbar = document.getElementById(form);
        const line = toolbar ? toolbar.querySelector<HTMLElement>(SELECTORS.line) : null;
        const active = document.activeElement;
        // A line this component drew before is empty now: it is drawn again from what the unmount kept.
        const kept = line ? KEPT.get(line) : undefined;
        if (line && kept) {
            KEPT.delete(line);
            return {
                toolbar: toolbar,
                line: line,
                first: kept.model,
                panel: kept.panel,
                menu: kept.menu,
                manage: kept.manage,
                focused: null,
            };
        }
        return {
            toolbar: toolbar,
            line: line,
            first: read(line),
            panel: line ? line.querySelector(SELECTORS.panel) : null,
            menu: line ? line.querySelector(SELECTORS.menu) : null,
            manage: line ? line.querySelector(SELECTORS.manage) : null,
            focused: line && active && line.contains(active) ? active : null,
        };
    });
    const [model, setModel] = useState<Line | null>(setup.first);
    // The state the latest render drew from, which an unmount keeps for the next mount of this node.
    const latest = useRef<Line | null>(model);
    latest.current = model;
    const [, rerender] = useReducer((count: number) => count + 1, 0);
    const [answers, setAnswers] = useState<number>(0);
    const [relist, setRelist] = useState<number>(0);

    const live = useRef<Live>({
        fold: null,
        widths: [],
        width: -1,
        searching: false,
        open: false,
        items: [],
        active: -1,
        from: null,
        quiet: false,
        intent: false,
        tabat: 0,
        refocus: null,
        measuredOnce: false,
        veiled: null,
    });
    const rest = useRef<WeakMap<Element, Rest>>(new WeakMap());
    const preview = useRef<PreviewModule | null>(null);
    const previewing = useRef<Promise<PreviewModule> | null>(null);
    const field = useRef<HTMLInputElement>(null);
    const opener = useRef<HTMLButtonElement>(null);
    const scope = useRef<HTMLFieldSetElement>(null);
    const filters = useRef<HTMLDetailsElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const organise = useRef<HTMLDetailsElement>(null);
    const menu = useRef<HTMLDivElement>(null);
    const manage = useRef<HTMLDivElement>(null);
    const listbox = useRef<HTMLDivElement>(null);
    // What the open listbox was last placed for; empty while it is closed.
    const placed = useRef<string>('');
    const drawn = useRef<boolean>(false);
    // What the native listeners call: this render's functions, which read the live state above.
    const api = useRef<Handlers>({});

    const line = setup.line;
    const toolbar = setup.toolbar;
    const st = live.current;

    /**
     * Whether the line is at its two-line step.
     *
     * @returns {boolean}
     */
    const twolines = (): boolean => st.fold !== null && st.fold.step === TWO_LINES;

    /**
     * Move the focus without it counting as the reader's: the field's focus listener stays quiet.
     *
     * @param {Element|null} element Where the focus goes.
     * @returns {void}
     */
    const quietly = (element: Element | null): void => {
        if (!(element instanceof HTMLElement)) {
            return;
        }
        st.quiet = true;
        try {
            element.focus({preventScroll: true});
        } finally {
            st.quiet = false;
        }
    };

    /**
     * Import the preview and arm it over the form, once; the first-use listeners go when it is armed.
     *
     * Pending while it loads, so nothing that waits for the page reads it half-armed.
     *
     * @returns {Promise} Resolving with the preview once it listens.
     */
    const loadPreview = (): Promise<PreviewModule> => {
        if (previewing.current === null) {
            const pending = new Pending('theme_dimensions/filterline:preview');
            previewing.current = importPreview()
                .then((module) => {
                    const listing = toolbar ? toolbar.closest<HTMLElement>(HOOKS.listing) : null;
                    if (toolbar && listing) {
                        module.arm(listing, toolbar, {controlOf: controlOfOption});
                    }
                    preview.current = module;
                    return module;
                })
                .finally(() => pending.resolve());
        }
        return previewing.current;
    };

    /**
     * The control a suggestion of the listbox names.
     *
     * @param {Element} option The option.
     * @returns {HTMLElement|null}
     */
    const controlOfOption = (option: Element): HTMLElement | null => {
        const item = live.current.items[Number((option as HTMLElement).dataset.index)];
        return item ? item.control : null;
    };

    /**
     * Preview a control, importing the preview first where it is not loaded yet.
     *
     * @param {HTMLElement|null} control The control, or null for none.
     * @returns {void}
     */
    const previewShow = (control: HTMLElement | null): void => {
        if (preview.current) {
            preview.current.show(control);
        } else if (control) {
            loadPreview().then((module) => {
                const active = live.current;
                module.show(active.open && active.active >= 0 ? active.items[active.active].control : null);
                return module;
            }).catch(() => null);
        }
    };

    /**
     * Take every mark of a preview off, where one is loaded.
     *
     * @returns {void}
     */
    const previewClear = (): void => {
        preview.current?.clear();
    };

    /**
     * Fill the listbox for what is in the field, or take it down when nothing is left to offer.
     *
     * @returns {void}
     */
    const relistNow = (): void => {
        if (!st.open || !toolbar || !field.current) {
            return;
        }
        if (st.active >= 0) {
            // The active suggestion goes with the list it was in.
            previewClear();
        }
        st.items = suggest(offers(toolbar, rest.current), field.current.value);
        st.active = -1;
        rerender();
    };

    /**
     * Take the listbox down.
     *
     * @returns {void}
     */
    const closeList = (): void => {
        if (st.active >= 0) {
            previewClear();
        }
        st.open = false;
        st.items = [];
        st.active = -1;
        rerender();
    };

    /**
     * Grow the search over the line or take it back, at once: a field folded into its icon can take the
     * focus only once the class has shown it.
     *
     * @param {boolean} on Whether the search is over the line.
     * @returns {void}
     */
    const searchOver = (on: boolean): void => {
        st.searching = on;
        line?.classList.toggle(STEP_CLASSES.searching, on);
        rerender();
    };

    /**
     * Open the search: over the line from the third step, under the field before it, with the suggestions.
     *
     * @param {HTMLElement|null} from The control that opened it, which gets the focus back when it closes.
     * @returns {void}
     */
    const openSearch = (from: HTMLElement | null): void => {
        if (morphs(st.fold) && !st.searching) {
            st.from = from || field.current;
            searchOver(true);
        }
        st.open = true;
        if (document.activeElement !== field.current) {
            quietly(field.current);
        }
        relistNow();
    };

    /**
     * Close the search and its suggestions; with back, the focus returns to the control that opened it.
     *
     * The focus is moved quietly, so it does not open the search again. A control that is no longer drawn
     * - the field folded away again, the icon at a step that shows the field - hands over to the one that is.
     *
     * @param {boolean} back Whether the focus goes back to the control that opened the search.
     * @returns {void}
     */
    const closeSearch = (back: boolean): void => {
        closeList();
        if (!st.searching) {
            return;
        }
        searchOver(false);
        const from = st.from;
        st.from = null;
        if (back) {
            quietly([from, field.current, opener.current].find((control) => shown(control)) || null);
        }
    };

    /**
     * Mark one option of the listbox as the active one, or none, and preview the control it names.
     *
     * @param {number} index The option's place, -1 for none.
     * @returns {void}
     */
    const activate = (index: number): void => {
        st.active = index >= 0 && index < st.items.length ? index : -1;
        rerender();
        // The active suggestion is the keyboard's focus on the control it names: preview that control.
        previewShow(st.active >= 0 ? st.items[st.active].control : null);
    };

    /**
     * Apply one suggestion: the term dropped, then the very control it names pressed.
     *
     * The listing's handler of that control makes the one request, and the listing has dropped the typed
     * term from its state first (dropterm), so that request carries no term; the field is emptied and the
     * search stays open for the next pick, offering what is left once the press is drawn.
     *
     * @param {number} index The option's place.
     * @returns {void}
     */
    const pick = (index: number): void => {
        const item = st.items[index];
        if (!item || !item.control.isConnected || !line) {
            return;
        }
        setModel((current) => (current ? stated(current, {query: ''}) : current));
        if (field.current) {
            field.current.value = '';
        }
        line.dispatchEvent(new CustomEvent(EVENTS.dropterm, {bubbles: true}));
        item.control.click();
        setRelist((count) => count + 1);
    };

    /**
     * Take the fold a width gives, from the widths measured for this content.
     *
     * A fold that no longer grows the search over the line ends an open search there, and a scope about to
     * move (to its own line, or back) has its focused cell given the focus back once moved.
     *
     * @returns {void}
     */
    const refold = (): void => {
        if (!line) {
            return;
        }
        const next = choose(st.widths, st.width);
        if (same(next, st.fold)) {
            return;
        }
        const scopeel = scope.current;
        st.refocus = scopeel && scopeel.contains(document.activeElement) ? document.activeElement : null;
        st.fold = next;
        if (st.searching && !morphs(next)) {
            st.searching = false;
            line.classList.remove(STEP_CLASSES.searching);
        }
        rerender();
    };

    /**
     * Measure the width each step needs for this content, and fold to the line's width.
     *
     * @returns {void}
     */
    const remeasure = (): void => {
        if (!line || !line.isConnected) {
            return;
        }
        st.width = contentWidth(line);
        st.widths = stepWidths(line, st.fold, st.width);
        refold();
    };

    /**
     * Show the line again, with the inline visibility it had before it was hidden until the first fold.
     *
     * @returns {void}
     */
    const unveil = (): void => {
        if (line && st.veiled !== null) {
            line.style.visibility = st.veiled;
            st.veiled = null;
        }
    };

    /**
     * Take the first measure, and fold to it.
     *
     * A line that cannot be measured stays at the full step, and is still the line: it is drawn and said so.
     *
     * @param {number} width The line's content width, from the observer's first entry; read off the line without one.
     * @returns {void}
     */
    const measureFirst = (width?: number): void => {
        if (!line) {
            return;
        }
        st.measuredOnce = true;
        try {
            st.width = width === undefined ? contentWidth(line) : width;
            st.widths = stepWidths(line, st.fold, st.width);
            refold();
        } catch (error) {
            st.widths = [];
        }
        unveil();
        // The layers are placed once the line is measured, whatever the fold ({@see foldkey}).
        rerender();
    };

    /**
     * The field's keys: the arrows walk the suggestions, Enter and Space choose, Escape closes.
     *
     * Space chooses only once an option is active, so a reader typing two words is never interrupted.
     *
     * @param {KeyboardEvent} event The key.
     * @returns {void}
     */
    const onFieldKey = (event: KeyboardEvent<HTMLInputElement>): void => {
        const count = st.items.length;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!st.open) {
                openSearch(st.from || field.current);
                return;
            }
            activate(count ? (st.active + 1) % count : -1);
        } else if (event.key === 'ArrowUp' && st.open) {
            event.preventDefault();
            activate(st.active <= 0 ? -1 : st.active - 1);
        } else if ((event.key === 'Enter' || event.key === ' ') && st.active >= 0) {
            event.preventDefault();
            pick(st.active);
        } else if (event.key === 'Escape' && (st.open || st.searching)) {
            // Before the browser empties a search field on the same key.
            event.preventDefault();
            closeSearch(true);
        }
    };

    /**
     * The field took the focus: the search opens on the reader's own focus only - a press on it or a Tab
     * into it - and never on a focus a client moved there, or quietly here.
     *
     * @returns {void}
     */
    const onFieldFocus = (): void => {
        const intent = st.intent || Date.now() - st.tabat < TAB_INTENT;
        st.intent = false;
        if (st.quiet || !intent) {
            return;
        }
        openSearch(field.current);
    };

    /**
     * The reader typed: the suggestions follow the text, and typing opens the search.
     *
     * @param {string} value What is in the field.
     * @returns {void}
     */
    const onFieldInput = (value: string): void => {
        setModel((current) => (current ? stated(current, {query: value}) : current));
        if (st.open) {
            relistNow();
        } else {
            openSearch(field.current);
        }
    };

    /**
     * The eraser: the field emptied and the focus kept in it; the listing searches again for nothing.
     *
     * @returns {void}
     */
    const onErase = (): void => {
        setModel((current) => (current ? stated(current, {query: ''}) : current));
        if (field.current) {
            field.current.value = '';
        }
        quietly(field.current);
        if (st.open) {
            relistNow();
        }
    };

    /**
     * A scope cell pressed: the radio holds the choice, the listing makes the request.
     *
     * @param {string} value The cell's value.
     * @returns {void}
     */
    const onCell = (value: string): void => {
        setModel((current) => (current ? stated(current, {chip: value}) : current));
    };

    /**
     * Close an open menu or panel of the line on Escape, and give its summary the focus; an open search
     * closes first. The calendar inside the Filters panel keeps Escape for its own draft.
     *
     * @param {KeyboardEvent} event The key.
     * @returns {void}
     */
    const onLineKey = (event: globalThis.KeyboardEvent): void => {
        if (event.key !== 'Escape' || event.defaultPrevented || !(event.target instanceof Element) || !line) {
            return;
        }
        if (st.searching && event.target !== field.current) {
            event.preventDefault();
            closeSearch(true);
            return;
        }
        const details = event.target.closest<HTMLDetailsElement>('details[open]');
        if (details && line.contains(details) && !event.target.closest(HOOKS.daterange)) {
            event.preventDefault();
            details.open = false;
            quietly(details.querySelector('summary'));
        }
    };

    /**
     * The focus left something of the line: the suggestions go when it stays on the line, the search
     * closes where it stands when it leaves the line, and nothing it hid keeps the focus.
     *
     * @param {FocusEvent} event The focusout.
     * @returns {void}
     */
    const onLineBlur = (event: FocusEvent): void => {
        const next = event.relatedTarget instanceof Node ? event.relatedTarget : null;
        if (!line || next === field.current || (next && listbox.current && listbox.current.contains(next))) {
            return;
        }
        if (next && line.contains(next)) {
            closeList();
            return;
        }
        closeSearch(false);
    };

    /**
     * A floating layer opened or closed: placed under the line, and the panel a sheet at the two-line step.
     *
     * @param {Event} event The toggle, heard in the capture phase: it does not bubble.
     * @returns {void}
     */
    const onToggle = (event: Event): void => {
        if (!line) {
            return;
        }
        if (event.target === filters.current && filters.current) {
            toolbar?.classList.toggle(SHEET, twolines() && filters.current.open);
            place(line, panel.current, twolines(), twolines());
        } else if (event.target === organise.current) {
            place(line, menu.current, twolines(), false);
        }
    };

    /**
     * A "Show as" button of the Organise menu: the menu closes and its summary keeps the focus; the
     * listing's own view handler reads the button's value after this.
     *
     * @param {MouseEvent} event The click.
     * @returns {void}
     */
    const onLineClick = (event: MouseEvent): void => {
        const view = event.target instanceof Element ? event.target.closest(HOOKS.showas) : null;
        if (view && organise.current) {
            organise.current.open = false;
            quietly(organise.current.querySelector('summary'));
        }
    };

    /**
     * A press anywhere: outside the line it closes the search, outside the bottom sheet it closes the sheet.
     *
     * @param {PointerEvent} event The press.
     * @returns {void}
     */
    const onDocumentPress = (event: PointerEvent): void => {
        const target = event.target instanceof Node ? event.target : null;
        if (!target || !line) {
            return;
        }
        if ((st.open || st.searching) && !line.contains(target)) {
            closeSearch(false);
        }
        // The bottom sheet has no summary beside it to press again, so a press outside it closes it.
        if (twolines() && filters.current && filters.current.open && !filters.current.contains(target)) {
            filters.current.open = false;
        }
    };

    /**
     * Tab is a reason for the field's focus; the slash key goes to the search when the line is on screen
     * and the focus is in no field.
     *
     * @param {KeyboardEvent} event The key.
     * @returns {void}
     */
    const onDocumentKey = (event: globalThis.KeyboardEvent): void => {
        if (event.key === 'Tab') {
            st.tabat = Date.now();
            return;
        }
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented || !line) {
            return;
        }
        const active = document.activeElement;
        if (active instanceof HTMLElement && (active.isContentEditable || active.closest('input, textarea, select'))) {
            return;
        }
        const rect = line.getBoundingClientRect();
        if (rect.bottom <= 0 || rect.top >= window.innerHeight || !rect.width) {
            return;
        }
        event.preventDefault();
        openSearch(st.fold !== null && st.fold.folded && !st.searching ? opener.current : field.current);
    };

    /**
     * The first pointer or focus on the toolbar: import the preview, and give it the event again, to the
     * same control, while it still holds - the pointer still over it, the focus still on it.
     *
     * @param {Event} event The pointerover or the focusin.
     * @returns {void}
     */
    const onPreviewIntent = (event: Event): void => {
        const target = event.target instanceof Element ? event.target : null;
        if (!target || preview.current) {
            return;
        }
        loadPreview().then(() => {
            if (event.type === 'pointerover' && target.matches(':hover')) {
                const pointer = event as PointerEvent;
                target.dispatchEvent(new PointerEvent('pointerover', {
                    bubbles: true,
                    pointerType: pointer.pointerType,
                    isPrimary: pointer.isPrimary,
                }));
            } else if (event.type === 'focusin' && document.activeElement === target) {
                target.dispatchEvent(new FocusEvent('focusin', {bubbles: true}));
            }
            return null;
        }).catch(() => null);
    };

    /**
     * The listing's state, told: the term, the scope's choice, the star, the view.
     *
     * @param {Event} event The state event.
     * @returns {void}
     */
    const onState = (event: Event): void => {
        const told = ((event as CustomEvent).detail || {}) as Told;
        setModel((current) => (current ? stated(current, told) : current));
    };

    /**
     * An answer, told: the numbers move, then the counts are remembered, the step and the suggestions
     * follow and the preview's cards are the new ones.
     *
     * @param {Event} event The answer event.
     * @returns {void}
     */
    const onAnswer = (event: Event): void => {
        const answer = ((event as CustomEvent).detail || {}) as Answer;
        setModel((current) => (current ? answered(current, answer) : current));
        setAnswers((count) => count + 1);
    };

    /**
     * A radio of the form changed: a choice of "More statuses" unchecks the scope's cell in the browser,
     * which the scope's own state follows, so a later draw never checks it back.
     *
     * @param {Event} event The change.
     * @returns {void}
     */
    const onFormChange = (event: Event): void => {
        const input = event.target;
        const current = model;
        if (!(input instanceof HTMLInputElement) || input.type !== 'radio' || !current || !current.scope
            || input.name !== current.scope.name || !toolbar) {
            return;
        }
        const checked = toolbar.querySelector<HTMLInputElement>(`input[type="radio"][name="${input.name}"]:checked`);
        setModel((now) => (now ? stated(now, {chip: checked ? checked.value : null}) : now));
    };

    api.current = {
        onLineKey, onLineBlur, onToggle, onLineClick, onDocumentPress, onDocumentKey, onPreviewIntent, onState,
        onAnswer, onFormChange, remeasure, measureFirst,
    } as Handlers;

    // The first commit: the server's frames adopted, every listener on; the line is folded before it is painted.
    useLayoutEffect(() => {
        if (!line || !toolbar || !setup.first) {
            // Not a line this component can draw: the listing stops holding the mount, and the line stays the server's.
            line?.dispatchEvent(new CustomEvent(EVENTS.failed, {bubbles: true}));
            return undefined;
        }
        adopt(setup.panel, panel.current);
        adopt(setup.menu, menu.current);
        adopt(setup.manage, manage.current);

        /**
         * Hand an event to this render's handler.
         *
         * @param {string} name The handler's name.
         * @returns {Function} The listener.
         */
        const to = (name: string) => (event: Event): void => (api.current[name] as (event: Event) => void)(event);
        const listeners: Array<[EventTarget, string, EventListener, boolean | AddEventListenerOptions]> = [
            [line, 'keydown', to('onLineKey'), false],
            [line, 'focusout', to('onLineBlur'), false],
            [line, 'toggle', to('onToggle'), true],
            [line, 'click', to('onLineClick'), false],
            [line, EVENTS.state, to('onState'), false],
            [line, EVENTS.answer, to('onAnswer'), false],
            [toolbar, 'change', to('onFormChange'), false],
            [toolbar, 'pointerover', to('onPreviewIntent'), true],
            [toolbar, 'focusin', to('onPreviewIntent'), true],
            [document, 'pointerdown', to('onDocumentPress'), false],
            [document, 'keydown', to('onDocumentKey'), false],
        ];
        const rows = [scope.current, toolbar.querySelector<HTMLElement>(HOOKS.chips)].filter(
            (row): row is HTMLElement => row !== null
        );
        rows.forEach((row) => {
            listeners.push([row, 'scroll', () => fades(row), {passive: true}]);
            listeners.push([row, 'focusin', (event: Event) => reveal(row, itemOf(toolbar, event.target), still()), false]);
            listeners.push([row, 'click', (event: Event) => reveal(row, itemOf(toolbar, event.target), still()), false]);
        });
        listeners.forEach(([target, type, listener, options]) => target.addEventListener(type, listener, options));

        remember(toolbar, field.current ? field.current.value : '', rest.current);
        /* The first measure waits for the observer's first entry. Read inside this commit, it would force the page's
           first full layout; the entry comes after the browser's own layout and before the paint, so the fold is
           still drawn before the line is first painted, and its width is the entry's content box - what
           contentWidth() reads off the line - with no layout read for it. Without an observer it is taken here. */
        if (typeof ResizeObserver === 'undefined') {
            measureFirst();
        } else if (!setup.focused && !line.contains(document.activeElement)) {
            /* Hidden until that first fold: a fold in the observer's callback moves nodes the browser has already laid
               out once, which counts as a layout shift, and hidden nodes count for none; visibility keeps the line's
               box, so nothing below it moves either. A line holding the reader's focus is not hidden, because a
               focused element inside a hidden ancestor loses the focus: there the rare shift is the lesser cost. */
            st.veiled = line.style.visibility;
            line.style.visibility = 'hidden';
        }

        // The line's own width, never the viewport's: a drawer opening changes the one and not the other.
        const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver((entries) => {
            const width = entries[0].contentRect.width;
            if (!live.current.measuredOnce) {
                flushSync(() => (api.current.measureFirst as (width: number) => void)(width));
                return;
            }
            if (Math.round(width) === Math.round(live.current.width)) {
                return;
            }
            live.current.width = width;
            // Before the frame is painted: a width is a fold, and no layout is read for it.
            flushSync(() => (api.current.remeasureWidth as () => void)());
        });
        observer?.observe(line);
        // A webfont arriving late changes every width on the line; before the first measure, that measure has it.
        document.fonts?.ready.then(() => {
            if (line.isConnected && live.current.measuredOnce) {
                flushSync(() => (api.current.remeasure as () => void)());
            }
            return null;
        }).catch(() => null);

        line.setAttribute(DRAWN, 'true');
        drawn.current = true;
        if (setup.focused) {
            quietly(setup.focused.isConnected ? setup.focused : counterpart(line, setup.focused));
        }
        line.dispatchEvent(new CustomEvent(EVENTS.mounted, {bubbles: true}));
        return () => {
            observer?.disconnect();
            unveil();
            listeners.forEach(([target, type, listener, options]) => target.removeEventListener(type, listener, options));
            line.removeAttribute(DRAWN);
            /* React is about to empty the line: the adopted nodes go back to the server's frames, kept with the last
               state, so a mount of this node again (react_autoinit, after a move) draws the line again. Run before the
               frames are removed: a component's layout cleanup runs ahead of its host nodes' removal. */
            adopt(panel.current, setup.panel);
            adopt(menu.current, setup.menu);
            adopt(manage.current, setup.manage);
            if (latest.current) {
                KEPT.set(line, {model: latest.current, panel: setup.panel, menu: setup.menu, manage: setup.manage});
            }
        };
    // The first commit's effect, once: every later change reaches the listeners through api.
    }, []);

    // A width the tries measured so far already decide (see settled() in ./steps) only refolds; a narrower one measures the rest.
    api.current.remeasureWidth = (() => (settled(st.widths, st.width) ? refold() : remeasure())) as (...args: never[]) => void;

    /* What changes a control's width: the words, which slots there are, and how many characters a count has - never
       its value, because every count on the line is drawn in tabular figures (the pills' and the Filters badge's), so
       an answer that moves 12 to 15 keeps every width. The star's count is visually hidden and takes no width. */
    const signature = model ? JSON.stringify([
        model.scope ? model.scope.cells.map((cell) => [cell.label, String(cell.count).length, cell.hascount]) : null,
        [model.filters.hascount, String(model.filters.count).length],
    ]) : '';
    const measured = useRef<string>(signature);
    useLayoutEffect(() => {
        if (!drawn.current || measured.current === signature) {
            return;
        }
        measured.current = signature;
        // Before the first measure, that measure reads this content: measuring in this commit would force the layout.
        if (st.measuredOnce) {
            remeasure();
        }
    }, [signature]);

    // An answer was drawn: its counts are remembered while the field is empty, and what read the old ones follows.
    useLayoutEffect(() => {
        if (!drawn.current || !toolbar || answers === 0) {
            return;
        }
        remember(toolbar, field.current ? field.current.value : '', rest.current);
        relistNow();
        preview.current?.refresh();
    }, [answers]);

    // A suggestion was chosen and its press drawn: what is left is offered.
    useLayoutEffect(() => {
        if (drawn.current && relist > 0) {
            relistNow();
        }
    }, [relist]);

    // Every draw: the fold and the search on the line's classes, the layers in their places, the focus kept.
    useLayoutEffect(() => {
        if (!line || !toolbar) {
            return;
        }
        paint(line, st.fold);
        line.classList.toggle(STEP_CLASSES.searching, st.searching);
        toolbar.classList.toggle(SHEET, twolines() && Boolean(filters.current && filters.current.open));
        if (st.refocus) {
            const refocus = st.refocus;
            st.refocus = null;
            if (refocus.isConnected && document.activeElement !== refocus) {
                quietly(refocus);
            }
        }
        if (listbox.current && !listbox.current.hidden) {
            /* Placed when the list opens and when what places it moves (the fold, the search, the width), not on every
               key: the field and the line keep their boxes while the reader types, and each placement reads layout. */
            const fold = st.fold ? `${st.fold.step}${st.fold.folded ? 'f' : ''}` : '';
            const key = [st.measuredOnce, fold, st.searching, Math.round(st.width)].join('|');
            if (placed.current !== key) {
                placed.current = key;
                placeList(line, listbox.current, st.fold === null || st.fold.step === 's1' || st.fold.step === 's2', twolines());
            }
            const option = st.active >= 0 ? listbox.current.querySelectorAll(HOOKS.option)[st.active] : null;
            option?.scrollIntoView?.({block: 'nearest'});
        } else {
            placed.current = '';
        }
    });

    /* The layers follow a new fold, and so do the rows' fades. Both read layout, so they wait for the first measure
       as it does - read in the first commit, they would force the page's first full layout themselves - and the key
       changes when it is taken, the fold or no fold it gives. */
    const foldkey = (st.measuredOnce ? 'm' : '') + (st.fold ? `${st.fold.step}${st.fold.folded ? 'f' : ''}` : '');
    useLayoutEffect(() => {
        if (!line || !drawn.current || !st.measuredOnce) {
            return;
        }
        place(line, panel.current, twolines(), twolines());
        place(line, menu.current, twolines(), false);
        fades(scope.current);
        fades(toolbar ? toolbar.querySelector<HTMLElement>(HOOKS.chips) : null);
    }, [foldkey]);

    if (!model || !line) {
        return null;
    }

    const listed = st.open && st.items.length > 0;
    const scopenode = drawScope(model, scope, onCell);
    const scopedown = twolines();
    const search = model.search;
    return (
        <>
            {scopedown ? null : scopenode}
            <div key="search" className="dim-toolbar__search dim-line__search" data-region="dim-line-search">
                <label className="visually-hidden" htmlFor={search.id}>{search.label}</label>
                <i className="fa fa-magnifying-glass dim-toolbar__searchicon" aria-hidden="true"></i>
                <input
                    ref={field}
                    className="form-control dim-toolbar__input"
                    type="search"
                    id={search.id}
                    name={search.name}
                    value={search.value}
                    placeholder={search.placeholder}
                    autoComplete="off"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded={listed ? 'true' : 'false'}
                    aria-controls={model.suggest.id}
                    aria-activedescendant={listed && st.active >= 0 ? `${model.suggest.id}-${st.active}` : undefined}
                    onPointerDown={() => {
                        st.intent = true;
                    }}
                    onFocus={onFieldFocus}
                    onClick={() => {
                        if (!st.open) {
                            openSearch(st.from || field.current);
                        }
                    }}
                    onChange={(event) => onFieldInput(event.target.value)}
                    onKeyDown={onFieldKey}
                />
                <button
                    type="button"
                    className="dim-line__eraser dim-live-only"
                    data-action="dim-search-clear"
                    aria-label={search.clearlabel}
                    title={search.clearlabel}
                    hidden={search.value === ''}
                    onClick={onErase}
                ><i className="fa fa-eraser" aria-hidden="true"></i></button>
            </div>
            <button
                key="opener"
                ref={opener}
                type="button"
                className="dim-line__iconbtn dim-line__searchopen"
                data-action="dim-search-open"
                aria-controls={search.id}
                aria-expanded={st.searching ? 'true' : 'false'}
                title={model.openlabel}
                onClick={() => openSearch(opener.current)}
            >
                <i className="fa fa-magnifying-glass" aria-hidden="true"></i>
                <span className="visually-hidden">{model.openlabel}</span>
            </button>
            {drawStar(model)}
            {drawFilters(model, filters, panel)}
            <details key="organise" ref={organise} className="dim-line__organise" data-region="dim-organise">
                <summary className="dim-line__iconbtn dim-line__organisetoggle" title={model.organiselabel}>
                    <i className="fa fa-arrow-down-wide-short" aria-hidden="true"></i>
                    <span className="visually-hidden">{model.organiselabel}</span>
                </summary>
                <div ref={menu} className="dim-line__menu" data-region="dim-organise-menu"></div>
            </details>
            {drawViews(model)}
            {model.manage ? (
                <div key="manage" ref={manage} className="dim-toolbar__manage" data-region="dim-line-manage"></div>
            ) : null}
            <button
                key="closer"
                type="button"
                className="dim-line__iconbtn dim-line__searchclose"
                data-action="dim-search-close"
                aria-label={model.closelabel}
                title={model.closelabel}
                onClick={() => closeSearch(true)}
            ><i className="fa fa-xmark" aria-hidden="true"></i></button>
            {scopedown ? scopenode : null}
            {drawList(model, listbox, listed ? st.items : [], st.active, pick)}
        </>
    );
};

/**
 * The scope: one radio group drawn as counted cells, every cell written, a cell with nothing to offer
 * hidden (absent), as category_toolbar draws it.
 *
 * @param {object} model The line.
 * @param {object} ref The fieldset's ref.
 * @param {Function} onCell Called with a cell's value when the reader presses it.
 * @returns {object|null}
 */
const drawScope = (model: Line, ref: RefObject<HTMLFieldSetElement | null>, onCell: (value: string) => void): ReactNode => {
    const scope = model.scope;
    if (!scope) {
        return null;
    }
    return (
        <fieldset
            key="scope"
            ref={ref}
            className="dim-toolbar__group dim-line__scope"
            data-region="dim-vitrine-group"
            data-name={scope.name}
            data-role="scope"
        >
            <legend className="visually-hidden">{scope.legend}</legend>
            <div className="dim-line__cells" data-region="dim-pill-track">
                {scope.cells.map((cell) => (
                    <Fragment key={cell.value}>
                        <input
                            className="dim-pill__input visually-hidden"
                            type="radio"
                            name={scope.name}
                            id={cell.id}
                            value={cell.value}
                            checked={cell.checked}
                            hidden={cell.absent}
                            onChange={() => onCell(cell.value)}
                        />
                        <label className="dim-pill" htmlFor={cell.id} hidden={cell.absent}>
                            {`${cell.label} `}
                            <span className="dim-pill__count" data-region="dim-pill-count" hidden={!cell.hascount}>
                                {cell.count}
                            </span>
                        </label>
                    </Fragment>
                ))}
            </div>
        </fieldset>
    );
};

/**
 * The favourites toggle: a submit button carrying the value a press turns it to, for a reader without
 * the listing's module; the listing intercepts the press and tells the line the new state.
 *
 * @param {object} model The line.
 * @returns {object|null}
 */
const drawStar = (model: Line): ReactNode => {
    const star = model.star;
    if (!star) {
        return null;
    }
    return (
        <button
            key="star"
            type="submit"
            className="dim-toolbar__star dim-line__iconbtn"
            name={star.name}
            value={star.pressed ? '0' : '1'}
            aria-pressed={star.pressed ? 'true' : 'false'}
            title={star.label}
            data-action="dim-fav"
            data-region="dim-line-star"
        >
            <i className={`${star.pressed ? 'fa-solid' : 'fa-regular'} fa-star`} aria-hidden="true"></i>
            <span className="visually-hidden">{`${star.label} `}<span data-region="dim-fav-count">{star.count}</span></span>
        </button>
    );
};

/**
 * The Filters disclosure: its summary with the word and both halves of the badge, and the panel's frame,
 * which holds the server's panel, adopted on the first commit and never diffed.
 *
 * @param {object} model The line.
 * @param {object} ref The details element's ref.
 * @param {object} panel The panel frame's ref.
 * @returns {object}
 */
const drawFilters = (
    model: Line,
    ref: RefObject<HTMLDetailsElement | null>,
    panel: RefObject<HTMLDivElement | null>
): ReactNode => (
    <details key="filters" ref={ref} className="dim-toolbar__filters dim-line__filters" data-region="dim-filters">
        <summary className="dim-toolbar__filterstoggle">
            <i className="fa fa-sliders" aria-hidden="true"></i>
            {' '}
            <span className="dim-line__label dim-toolbar__filterslabel">{model.filters.label}</span>
            {' '}
            <span className="dim-toolbar__filtercount" data-region="dim-filtercount" hidden={!model.filters.hascount}>
                <span aria-hidden="true" data-region="dim-filtercount-number">{model.filters.count}</span>
                <span className="visually-hidden" data-region="dim-filtercount-label">{model.filters.countlabel}</span>
            </span>
        </summary>
        <div ref={panel} className="dim-toolbar__panel" data-region="dim-filters-panel"></div>
    </details>
);

/**
 * The view toggle: List first, icons only, each named by a visually hidden word and a tooltip.
 *
 * @param {object} model The line.
 * @returns {object}
 */
const drawViews = (model: Line): ReactNode => (
    <div
        key="view"
        className="dim-toolbar__view dim-line__view"
        role="group"
        aria-label={model.viewlabel}
        data-region="dim-view-row"
    >
        {model.views.map((view) => (
            <button
                key={view.value}
                type="submit"
                className="dim-toolbar__viewbtn"
                data-action="dim-view"
                name={model.viewname}
                value={view.value}
                aria-pressed={view.pressed ? 'true' : 'false'}
                title={view.label}
            ><i className={`fa ${view.icon}`} aria-hidden="true"></i><span className="visually-hidden">{view.label}</span></button>
        ))}
    </div>
);

/**
 * The listbox of the suggestions: one group under the server's heading, each option with its label (the
 * term marked), its group and the count it would give, all in its accessible name. A press on it keeps
 * the focus in the field, which is the combobox.
 *
 * @param {object} model The line.
 * @param {object} ref The listbox's ref.
 * @param {Array} items The suggestions on offer; none takes the listbox down.
 * @param {number} active The active option's place, -1 for none.
 * @param {Function} pick Called with an option's place when the reader presses it.
 * @returns {object}
 */
const drawList = (
    model: Line,
    ref: RefObject<HTMLDivElement | null>,
    items: Offer[],
    active: number,
    pick: (index: number) => void
): ReactNode => {
    const typed = normalise(model.search.value).length >= MIN_TERM;
    const term = typed ? normalise(model.search.value) : '';
    const id = model.suggest.id;
    return (
        <div
            key="suggest"
            ref={ref}
            className="dim-line__suggest"
            id={id}
            data-region="dim-line-suggest"
            data-label-filters={model.suggest.filters}
            data-label-start={model.suggest.start}
            role="listbox"
            aria-label={model.search.label}
            hidden={items.length === 0}
            onMouseDown={(event) => event.preventDefault()}
        >
            {items.length ? (
                <div role="group" aria-labelledby={`${id}-heading`}>
                    <div id={`${id}-heading`} className="dim-line__suggesthead" role="presentation">
                        {typed ? model.suggest.filters : model.suggest.start}
                    </div>
                    {items.map((item, index) => (
                        <div
                            key={index}
                            id={`${id}-${index}`}
                            className={`dim-line__option${index === active ? ` ${ACTIVE}` : ''}`}
                            role="option"
                            aria-selected={index === active ? 'true' : 'false'}
                            data-index={String(index)}
                            onClick={() => pick(index)}
                        >
                            <i className="fa fa-plus dim-line__optionplus" aria-hidden="true"></i>
                            <span className="dim-line__optionlabel">
                                {pieces(item.label, term).map((piece, at) => (piece.marked
                                    ? <mark key={at} className="dim-mark">{piece.text}</mark>
                                    : <Fragment key={at}>{piece.text}</Fragment>))}
                            </span>
                            {item.group !== '' ? <>{' '}<span className="dim-line__optiongroup">{item.group}</span></> : null}
                            {item.count !== null ? <>{' '}<span className="dim-line__optioncount">{item.count}</span></> : null}
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
};

export default FilterLine;
