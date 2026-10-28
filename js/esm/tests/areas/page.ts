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
 * A showcase page with areas for the posters' and the panel's tests, and a stand-in for category_panel.
 *
 * The markup carries the hooks category_listing, category_areas, category_directory_card and category_panel
 * write (areas_component_source_test holds every hook the component reads to those templates); the panel
 * stand-in renders the hooks and the states the component reads from a context, as core/templates would
 * render the real template.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** One poster of the page. */
export type Poster = {id: number, name: string, count: number, expandable?: boolean, hidden?: boolean, label?: string};

/** The page's shape. */
export type Page = {
    posters?: Poster[],
    more?: Poster[],
    filtered?: boolean,
    open?: number,
    nomatch?: {all: string, less: string, ids: number[]},
    list?: boolean,
};

/** The four posters of the default page. */
export const POSTERS: Poster[] = [
    {id: 8, name: 'Brigade', count: 12},
    {id: 9, name: 'Traffic', count: 3},
    {id: 10, name: 'Aviation', count: 5},
    {id: 11, name: 'Lifesaving', count: 2},
];

/**
 * Escape text for an attribute or a text node.
 *
 * @param {string} text The text.
 * @returns {string}
 */
const esc = (text: string): string => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');

/**
 * One poster as category_directory_card draws it.
 *
 * @param {object} poster The poster.
 * @param {number} open The open area.
 * @returns {string}
 */
const poster = (poster: Poster, open: number): string => {
    const expandable = poster.expandable !== false;
    const label = poster.label || '';
    const word = !expandable ? 'No courses yet' : (poster.id === open ? 'Close' : 'Open');
    return `<li class="dim-areas__item"${poster.hidden ? ' hidden' : ''}>`
        + `<article class="dim-poster${expandable ? '' : ' dim-poster--empty'}${poster.id === open ? ' dim-poster--open' : ''}"`
        + ` id="dim-area-${poster.id}" data-region="dim-area" data-categoryid="${poster.id}" data-name="${esc(poster.name)}"`
        + ` data-titleurl="https://example.com/course/index.php?categoryid=${poster.id}"`
        + ` data-ctaurl="https://example.com/course/index.php?categoryid=${poster.id}&amp;sort=recent"`
        + ` data-ctalabel="Go to ${esc(poster.name)}" data-expandable="${expandable ? '1' : '0'}" data-count="${poster.count}"`
        + ` data-resultcount="${label ? poster.count : ''}" data-resultlabel="${esc(label)}" style="--dim-card-band: #12${poster.id}4f;">`
        + `<span class="dim-poster__pill" id="dim-area-results-${poster.id}" data-region="dim-poster-pill"${label ? '' : ' hidden'}>${esc(label)}</span>`
        + `<a class="dim-poster__control" href="https://example.com/course/index.php?categoryid=7&amp;open=${poster.id}#dim-panel"`
        + ` data-region="dim-poster-control"${label ? ` aria-describedby="dim-area-results-${poster.id}"` : ''}>`
        + `<span class="dim-poster__nm">${esc(poster.name)}</span>`
        + `<span class="visually-hidden" data-region="dim-poster-emptynote"${expandable ? ' hidden' : ''}> No courses yet</span></a>`
        + `<span class="dim-poster__trigtext" data-region="dim-poster-trigger" data-open="Open" data-close="Close"`
        + ` data-empty="No courses yet">${word}</span>`
        + `<div data-region="dim-cover-controls"><button type="button" data-action="dim-cover-play">Play</button></div>`
        + '</article></li>';
};

/**
 * The panel stand-in: the regions and the states category_panel draws, from its context.
 *
 * @param {object} context The template context.
 * @returns {string}
 */
export const panelHtml = (context: Record<string, unknown>): string => {
    const name = esc(String(context.name || ''));
    const js = Boolean(context.jsmode);
    const psortname = Boolean(context.psortname);
    let sub = String(context.subtitle || '');
    if (context.isloading || context.isskeleton) {
        sub = `Loading ${name}`;
    } else if (context.iserror) {
        sub = `Could not load ${name}`;
    }
    const sorts = js
        ? '<div class="dim-panel__sort" role="radiogroup" data-region="dim-panel-sort">'
            + `<button type="button" role="radio" data-action="dim-panel-sort" data-psort="recent" data-focuskey="psort-recent"`
            + ` aria-checked="${psortname ? 'false' : 'true'}" tabindex="${psortname ? '-1' : '0'}">Newest</button>`
            + `<button type="button" role="radio" data-action="dim-panel-sort" data-psort="name" data-focuskey="psort-name"`
            + ` aria-checked="${psortname ? 'true' : 'false'}" tabindex="${psortname ? '0' : '-1'}">A-Z</button></div>`
            + `<button type="button" class="dim-panel__close" data-action="dim-panel-close" data-focuskey="close"`
            + ` aria-label="Close ${name}">x</button>`
        : '<div class="dim-panel__sort" role="group" data-region="dim-panel-sort">'
            + `<a href="?open=${context.id}" data-action="dim-panel-sort" data-psort="recent"${psortname ? '' : ' aria-current="true"'}>Newest</a>`
            + `<a href="?open=${context.id}&amp;psort=name" data-action="dim-panel-sort" data-psort="name"`
            + `${psortname ? ' aria-current="true"' : ''}>A-Z</a></div>`
            + `<a class="dim-panel__close" href="?" data-action="dim-panel-close" aria-label="Close ${name}">x</a>`;
    const cards = (context.cards as Array<{id: number, fullname: string}> || []).map((card) => (
        `<li class="dim-panel__item"><article data-region="dim-card" data-courseid="${card.id}">`
        + `<h3 data-region="dim-card-title"><a href="#" data-focuskey="card-${card.id}">${esc(card.fullname)}</a></h3></article></li>`
    )).join('');
    let body = '';
    if (context.isrows) {
        body = '<button type="button" class="dim-panel__arrow dim-panel__arrow--prev dim-panel__arrow--off" data-action="dim-panel-prev">p</button>'
            + '<button type="button" class="dim-panel__arrow dim-panel__arrow--next" data-action="dim-panel-next">n</button>'
            + `<ul class="dim-panel__rail" data-region="dim-panel-rail">${cards}</ul>`;
    } else if (context.isskeleton) {
        body = '<ul class="dim-panel__rail" data-region="dim-panel-rail" aria-busy="true"><li aria-hidden="true">skel</li></ul>';
    } else if (context.iserror) {
        body = '<p>Could not load.</p><button type="button" data-action="dim-panel-retry" data-focuskey="retry">Retry</button>';
    } else if (context.isempty) {
        body = '<p>Nothing here yet.</p>';
    } else if (context.iszero) {
        const offered = (context.suggestions as Array<{id: number, name: string}> || []).map((one) => (js
            ? `<button type="button" data-action="dim-panel-openarea" data-categoryid="${one.id}">${esc(one.name)}</button>`
            : `<a href="?open=${one.id}" data-action="dim-panel-openarea" data-categoryid="${one.id}">${esc(one.name)}</a>`
        )).join('');
        body = `<p>${esc(String(context.zero || ''))}</p>${offered}`;
    }
    const cta = context.cta as {has: boolean, url: string, label: string} | undefined;
    return `<section data-region="dim-panel-section" data-categoryid="${context.id}">`
        + `<h3 id="dim-panel-title" tabindex="-1" data-region="dim-panel-title" data-focuskey="title">Courses in ${name}</h3>`
        + `<p data-region="dim-panel-subtitle">${esc(sub)}</p>${sorts}`
        + '<nav data-region="dim-panel-also" hidden></nav>'
        + `<div data-region="dim-panel-body">${body}</div>`
        + `<div data-region="dim-panel-foot">${context.isloading ? '' : '<span data-region="dim-panel-counter">1 / 2</span>'}`
        + (cta && cta.has ? `<a href="${esc(cta.url)}" data-region="dim-panel-cta">${esc(cta.label)}</a>` : '')
        + '</div></section>';
};

/**
 * Draw the page into the document, the way the server draws it.
 *
 * @param {object} page The page's shape.
 * @returns {object} The areas section and the mount node the panel module appends.
 */
export const drawPage = (page: Page = {}): {areas: HTMLElement, mount: HTMLElement} => {
    const posters = page.posters || POSTERS;
    const open = page.open || 0;
    const all = posters.concat(page.more || []);
    const opened = all.find((one) => one.id === open);
    const panel = opened
        ? `<li class="dim-panel" id="dim-panel" data-region="dim-panel" style="--dim-row-3: 2; --dim-row-2: 2; --dim-row-1: 2;`
            + ` --dim-col-3: 0; --dim-col-2: 0; --dim-area-bg: #12${open}4f;">`
            + panelHtml({id: open, name: opened.name, subtitle: `${opened.count} courses`, isrows: true, jsmode: false,
                cards: [{id: 101, fullname: 'Rescue at sea'}], cta: {has: true, url: '/x', label: 'Go'}})
            + '</li>'
        : '';
    const items = (list: Poster[]): string => list.map((one) => poster(one, open) + (one.id === open ? panel : '')).join('');
    const inmore = Boolean(page.more && page.more.some((one) => one.id === open));
    const nomatch = page.nomatch || {all: '', less: '', ids: []};
    const label = nomatch.ids.indexOf(open) >= 0 ? nomatch.less : nomatch.all;
    const shown = Boolean(page.filtered) && label !== '';
    document.body.className = '';
    document.body.innerHTML = `<section class="dim-vitrine ${page.list ? 'dim-vitrine--list' : 'dim-vitrine--cards'}" data-region="dim-vitrine">`
        + '<form id="dim-toolbar" data-region="dim-vitrine-toolbar"><input type="search" name="q" value=""></form>'
        + '<form data-region="dim-hero-fold"><button type="submit">Fold</button></form>'
        + '<a data-region="dim-vitrine-clear" href="https://example.com/course/index.php?categoryid=7">Clear</a>'
        + `<section class="dim-areas${open ? ' dim-areas--open' : ''}" data-region="dim-areas" data-categoryid="7"`
        + ` data-filtered="${page.filtered ? '1' : '0'}" data-elsewhere="There are results in one other area.">`
        + `<ul class="dim-vitrine__grid dim-areas__grid" data-region="dim-areas-grid">${items(posters)}`
        + (opened ? '' : '<li class="dim-panel" id="dim-panel" data-region="dim-panel" hidden></li>') + '</ul>'
        + '<p class="dim-areas__hint" data-region="dim-areas-hint">Press an area.</p>'
        + '<div data-region="dim-areas-empty" hidden><a data-region="dim-areas-searchall" href="" hidden>All</a></div>'
        + (page.more ? `<details data-region="dim-areas-more"${inmore ? ' open' : ''}><summary data-region="dim-areas-moresummary">More</summary>`
            + `<ul class="dim-vitrine__grid dim-areas__grid" data-region="dim-areas-grid">${items(page.more)}</ul></details>` : '')
        + `<details id="dim-nomatch" data-region="dim-areas-nomatch" data-label-all="${esc(nomatch.all)}"`
        + ` data-label-less="${esc(nomatch.less)}"${shown ? '' : ' hidden'}>`
        + `<summary data-region="dim-nomatch-summary">${shown ? esc(label) : esc(nomatch.all)}</summary>`
        + '<ul data-region="dim-nomatch-list">'
        + nomatch.ids.map((id) => {
            const one = all.find((each) => each.id === id);
            return `<li data-categoryid="${id}"${id === open ? ' hidden' : ''}><a href="/course/index.php?categoryid=${id}">`
                + `${esc(one ? one.name : '')}</a></li>`;
        }).join('')
        + '</ul></details>'
        + '<p class="visually-hidden" role="status" aria-live="polite" data-region="dim-panel-status"></p>'
        + '</section></section>';
    const areas = document.querySelector('[data-region="dim-areas"]') as HTMLElement;
    const mount = document.createElement('div');
    mount.hidden = true;
    areas.append(mount);
    return {areas, mount};
};

/**
 * The poster of an area.
 *
 * @param {number} id The area.
 * @returns {Element}
 */
export const posterEl = (id: number): HTMLElement => document.querySelector(`[data-region="dim-area"][data-categoryid="${id}"]`) as HTMLElement;

/**
 * The control of an area's poster.
 *
 * @param {number} id The area.
 * @returns {Element}
 */
export const controlOf = (id: number): HTMLElement => posterEl(id).querySelector('[data-region="dim-poster-control"]') as HTMLElement;

/**
 * The panel's element.
 *
 * @returns {Element}
 */
export const panelEl = (): HTMLElement => document.querySelector('[data-region="dim-panel"]') as HTMLElement;
