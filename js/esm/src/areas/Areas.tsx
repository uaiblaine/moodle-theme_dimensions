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
 * The posters of the areas and the panel between their rows, on both shells of the showcase.
 *
 * The visitor's differences are the mount's props ({@see ./panel Options}): `{links: true, quiet, autoopen:
 * false}` on the visitor's page, nothing on the logged-in one. The rules live in the modules of this tree
 * (`panel.ts` for the panel, `posters.ts` for the posters' result layer, `rail.ts`, `model.ts`).
 *
 * The mount node is an empty, hidden child that mountAreas() (js/esm/src/listing/mounts.ts) appends to the
 * areas section, so the mount draws no box and the first paint stays the server's. The component is the only
 * writer of:
 *
 * - the panel's frame: the one list item of the grid (`panel.ts`), whose content is category_panel
 *   rendered by core/templates and inserted whole, never diffed;
 * - the posters' result layer (`posters.ts`): pills, counts and shells on the articles, the poster's
 *   control (a button that opens, or the link to its page), the open state, the items' hidden state, the
 *   reveal past the slice, the "nothing anywhere" box and its "search all" link, the hint, the live status;
 * - the "nothing in N areas" summary, which React draws: its sentence and its list, through portals into
 *   the server's `<summary>` and `<ul>`, read off them in the first render and drawn back hook for hook.
 *
 * Nothing else writes into those, except the address of a visitor's poster that stays a link, which the
 * visitor's shell points at the area's panel. The listing speaks to the component through events
 * ({@see EVENTS}): the listing client's `dim-vitrine:pending`, `:criteria` and `:failed` and the listing's
 * `:view` on the document; the component answers `dim-panel:autoopened` there. On the areas section,
 * mountAreas() hears, for the page's client, `dim-areas:ask` (the client fills the detail's `answer` with
 * its web service's promise: the component calls no service and reads no session), `dim-areas:question`
 * and `dim-areas:unanswered` (the visitor's names its question and takes a failure over),
 * `dim-areas:painted` (the logged-in shell paints what an account adds to the rows) and
 * `dim-areas:mounted`, which ends its pending.
 *
 * @module     theme_dimensions/areas/Areas
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Fragment, useLayoutEffect, useState} from 'react';
import {createPortal, flushSync} from 'react-dom';
import {PHONE, SELECTORS, WIDE, summaryLabel} from './model';
import type {Missing, Summary} from './model';
import {EVENTS, createPanel, drawnOpen, optionsOf} from './panel';
import type {Criteria, Nodes, Panel, Props} from './panel';
import {isFiltered, setHidden} from './posters';

export {EVENTS};

/** What the summary's markup holds, read off the server's before the first commit. */
type Drawn = {
    box: HTMLElement | null,
    label: HTMLElement | null,
    list: HTMLElement | null,
    summary: Summary,
    focused: Element | null,
};

/**
 * Read the "nothing in N areas" summary the server drew: its two sentences and its entries.
 *
 * @param {Element} areas The areas section.
 * @returns {object}
 */
const readSummary = (areas: HTMLElement): Drawn => {
    const box = areas.querySelector<HTMLElement>(SELECTORS.nomatch);
    const list = box ? box.querySelector<HTMLElement>(SELECTORS.nomatchlist) : null;
    const entries: Missing[] = list ? (Array.from(list.children) as HTMLElement[]).map((item) => ({
        id: Number(item.dataset.categoryid),
        name: item.textContent || '',
        url: item.querySelector('a')?.getAttribute('href') || '',
    })) : [];
    const active = document.activeElement;
    return {
        box: box,
        label: box ? box.querySelector<HTMLElement>(SELECTORS.nomatchsummary) : null,
        list: list,
        summary: {entries: entries, all: box?.dataset.labelAll || '', less: box?.dataset.labelLess || ''},
        focused: list && active && list.contains(active) ? active : null,
    };
};

/**
 * Draw a state change before the caller goes on: the summary the focus may be about to land in.
 *
 * @param {Function} update The state change.
 * @returns {void}
 */
const synchronously = (update: () => void): void => flushSync(update);

/**
 * The posters and their panel.
 *
 * @param {object} props The shell's options: links, quiet and autoopen, each optional ({@see ./panel optionsOf}).
 * @returns {object|null} The summary's portals, or nothing where the page has no areas with a panel.
 */
const Areas = (props: Props) => {
    // Read once, during the first render, while the server's markup is still the whole of it.
    const [setup] = useState(() => {
        const areas = document.querySelector<HTMLElement>(SELECTORS.areas);
        const panel = areas ? areas.querySelector<HTMLElement>(SELECTORS.panel) : null;
        const nodes: Nodes | null = areas && panel
            ? {areas: areas, panel: panel, status: areas.querySelector<HTMLElement>(SELECTORS.status)}
            : null;
        return {
            nodes: nodes,
            drawn: areas ? readSummary(areas) : null,
            open: nodes ? drawnOpen(nodes) : 0,
            filtered: areas ? isFiltered(areas) : false,
        };
    });
    const [summary, setSummary] = useState<Summary>(setup.drawn ? setup.drawn.summary : {entries: [], all: '', less: ''});
    const [opened, setOpened] = useState<number>(setup.open);
    const [filtered, setFiltered] = useState<boolean>(setup.filtered);
    const [adopted, setAdopted] = useState<boolean>(false);
    const [panel] = useState<Panel | null>(() => (setup.nodes ? createPanel(setup.nodes, {
        summary: setSummary,
        opened: setOpened,
        filtered: setFiltered,
        sync: synchronously,
    }, optionsOf(props || {})) : null));

    // The first commit: the summary handed over, the server's panel adopted, every listener on.
    useLayoutEffect(() => {
        if (!setup.nodes || !panel) {
            return undefined;
        }
        const {areas, panel: frame} = setup.nodes;
        const drawn = setup.drawn;
        // React draws the summary from here on: the server's nodes go, the same ones come back before paint.
        if (drawn && drawn.label && drawn.list) {
            drawn.label.textContent = '';
            drawn.list.replaceChildren();
            setAdopted(true);
        }
        try {
            panel.adopt();
        } catch (error) {
            // The posters stay the server's links, which open the panel by a page load: the mount is still said.
        }
        const listeners: Array<[EventTarget, string, EventListener, boolean]> = [
            [areas, 'click', panel.onAreasClick, false],
            [frame, 'click', panel.onDragClick, true],
            [frame, 'click', panel.onPanelClick, false],
            [frame, 'scroll', panel.onScroll, true],
            [frame, 'pointerdown', panel.onPointerDown as EventListener, false],
            [frame, 'dragstart', panel.onDragStart, false],
            [areas, 'toggle', panel.onToggle, true],
            [document, 'keydown', panel.onKeydown as EventListener, false],
            [window, 'popstate', panel.onPopState, false],
            [document, EVENTS.view, panel.relayout, false],
            [document, EVENTS.pending, (event: Event) => panel.onPending((event as CustomEvent).detail || {}), false],
            [document, EVENTS.failed, panel.onFailed, false],
            [document, EVENTS.criteria, (event: Event) => panel.criteria((event as CustomEvent<Criteria>).detail || {}), false],
        ];
        listeners.forEach(([target, type, listener, capture]) => target.addEventListener(type, listener, capture));
        const queries = [WIDE, PHONE].map((query) => window.matchMedia(query));
        queries.forEach((query) => query.addEventListener('change', panel.relayout));
        areas.dispatchEvent(new CustomEvent(EVENTS.mounted, {bubbles: true}));
        return () => {
            listeners.forEach(([target, type, listener, capture]) => target.removeEventListener(type, listener, capture));
            queries.forEach((query) => query.removeEventListener('change', panel.relayout));
            panel.disconnect();
        };
    // The first commit's effect, once: the panel's state lives in createPanel(), read synchronously.
    }, []);

    // The summary's box shows exactly when the page is in result mode and its sentence says something.
    const label = summaryLabel(summary, opened);
    useLayoutEffect(() => {
        const drawn = setup.drawn;
        if (!adopted || !drawn || !drawn.box) {
            return;
        }
        setHidden(drawn.box, !filtered || label === '');
        // A focus the reader had on an entry the server drew goes to the same entry, drawn by React.
        if (drawn.focused instanceof HTMLAnchorElement && !drawn.focused.isConnected && drawn.list) {
            const href = drawn.focused.getAttribute('href');
            drawn.list.querySelector<HTMLElement>(`a[href="${CSS.escape(href ?? '')}"]`)?.focus({preventScroll: true});
            drawn.focused = null;
        }
    }, [adopted, filtered, label]);

    const drawn = setup.drawn;
    if (!adopted || !drawn || !drawn.label || !drawn.list) {
        return null;
    }
    const entries = summary.entries.map((entry) => (
        <li key={entry.id} data-categoryid={entry.id} hidden={entry.id === opened}>
            {entry.url ? <a href={entry.url}>{entry.name}</a> : entry.name}
        </li>
    ));
    return (
        <Fragment>
            {createPortal(label, drawn.label)}
            {createPortal(entries, drawn.list)}
        </Fragment>
    );
};

export default Areas;
