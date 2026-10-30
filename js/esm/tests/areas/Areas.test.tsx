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
 * Tests for the showcase's posters and panel component, mounted the way the shared listing's mountAreas() mounts it.
 *
 * The page is ./page's (the hooks of category_areas, category_directory_card and category_panel), the panel
 * template is its stand-in behind core/templates' mock, and the page's client is played by the listeners of
 * its events on the areas section: a test answers the component's questions when it chooses, which is how
 * the races are written. The logged-in shell's client names no question and takes no failure over; the
 * visitor's names both and mounts the component with its options (VISITOR). jsdom lays nothing out, so the
 * grid's tracks come from a stubbed matchMedia.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, render} from '@testing-library/react';
import Areas, {EVENTS} from '../../src/areas/Areas';
import {optionsOf} from '../../src/areas/panel';
import {controlOf, drawPage, panelEl, panelHtml, posterEl} from './page';
import type {Page} from './page';

/** What the page's client may answer besides the rows: the visitor's names its question and takes a failure over. */
type Client = {question?: (id: number, psort: string) => string, unanswered?: (id: number, psort: string) => boolean};

/** The visitor's options, as js/esm/src/listing/visitor.ts mounts the component. */
const VISITOR = {links: true, quiet: 120, autoopen: false};

/** One question the component asked, held until a test answers it. */
type Question = {id: number, psort: string, resolve: (answer: object) => void, reject: (error: Error) => void};

/** One render the templates' mock holds, for the tests that order renders by hand. */
type Held = {context: Record<string, unknown>, release: () => void};

let questions: Question[] = [];
let held: Held[] = [];
let holdRenders: (context: Record<string, unknown>) => boolean = () => false;
let painted: number = 0;
let mounted: number = 0;
let media: Record<string, boolean> = {};
const replaceState = jest.spyOn(window.history, 'replaceState');
const pushState = jest.spyOn(window.history, 'pushState');

const templates = {
    renderForPromise: jest.fn((name: string, context: Record<string, unknown>) => new Promise((resolve) => {
        const done = (): void => resolve({html: panelHtml(context), js: ''});
        if (holdRenders(context)) {
            held.push({context: context, release: done});
        } else {
            done();
        }
    })),
    runTemplateJS: jest.fn(),
    replaceNodeContents: jest.fn((element: Element, html: string) => {
        element.innerHTML = html;
    }),
    appendNodeContents: jest.fn((element: Element, html: string) => {
        element.insertAdjacentHTML('beforeend', html);
    }),
};

/**
 * The answer of the rows service for an area's panel.
 *
 * @param {number} id The area.
 * @param {object} panel More of the panel's context.
 * @returns {object}
 */
const answerOf = (id: number, panel: Record<string, unknown> = {}): object => ({
    panel: {id: id, name: `Area ${id}`, subtitle: `${id} courses`, isrows: true, cta: {has: false, url: '', label: ''}, ...panel},
    cards: [{id: 200 + id, fullname: `Course of ${id}`}],
});

/**
 * Let every promise and timer queued so far run, inside act().
 *
 * @param {number} ms How far the fake clock moves.
 * @returns {Promise}
 */
const flush = async(ms: number = 0): Promise<void> => {
    await act(async() => {
        jest.advanceTimersByTime(ms);
        for (let round = 0; round < 6; round++) {
            await Promise.resolve();
        }
    });
};

/**
 * Draw the page, play the page's client, and mount the component on the appended node.
 *
 * @param {object} page The page's shape.
 * @param {object} props The mount's props: none on the logged-in shell, VISITOR on the visitor's.
 * @param {object} client What the client answers besides the rows.
 * @returns {Promise} The areas section.
 */
const mount = async(page: Page = {}, props: object = {}, client: Client = {}): Promise<HTMLElement> => {
    const {areas, mount: node} = drawPage(page);
    if (client.question) {
        const name = client.question;
        areas.addEventListener(EVENTS.question, (event: Event) => {
            const detail = (event as CustomEvent).detail;
            detail.question = name(Number(detail.poster.dataset.categoryid), detail.psort);
        });
    }
    if (client.unanswered) {
        const take = client.unanswered;
        areas.addEventListener(EVENTS.unanswered, (event: Event) => {
            const detail = (event as CustomEvent).detail;
            detail.taken = take(Number(detail.poster.dataset.categoryid), detail.psort);
        });
    }
    areas.addEventListener(EVENTS.ask, (event: Event) => {
        const detail = (event as CustomEvent).detail;
        detail.answer = new Promise((resolve, reject) => {
            questions.push({id: Number(detail.poster.dataset.categoryid), psort: detail.psort, resolve, reject});
        });
    });
    areas.addEventListener(EVENTS.painted, () => {
        painted++;
    });
    areas.addEventListener(EVENTS.mounted, () => {
        mounted++;
    });
    render(<Areas {...props} />, {container: node});
    await flush();
    return areas;
};

/**
 * Press a control, as a click.
 *
 * @param {Element} element The control.
 * @returns {Promise}
 */
const press = async(element: Element): Promise<void> => {
    await act(async() => {
        (element as HTMLElement).click();
    });
    await flush();
};

/**
 * Answer the oldest question still open.
 *
 * @param {object} answer The answer.
 * @returns {Promise}
 */
const answer = async(answer?: object): Promise<void> => {
    const question = questions.shift() as Question;
    question.resolve(answer || answerOf(question.id));
    await flush();
};

/**
 * Send a key to the focused element, as the document hears it.
 *
 * @param {string} key The key.
 * @returns {Promise}
 */
const key = async(key: string): Promise<void> => {
    await act(async() => {
        (document.activeElement || document.body).dispatchEvent(new KeyboardEvent('keydown', {key: key, bubbles: true}));
    });
    await flush();
};

/**
 * Tell the component a criteria answer, as the listing's store does.
 *
 * @param {object} detail The accordion and the word on the areas.
 * @returns {Promise}
 */
const criteria = async(detail: object): Promise<void> => {
    await act(async() => {
        document.dispatchEvent(new CustomEvent(EVENTS.criteria, {detail: detail}));
    });
    await flush();
};

beforeEach(() => {
    jest.useFakeTimers();
    questions = [];
    held = [];
    holdRenders = () => false;
    painted = 0;
    mounted = 0;
    media = {};
    window.history.replaceState(null, '', '/course/index.php?categoryid=7');
    replaceState.mockClear();
    pushState.mockClear();
    mockAmdModule('core/templates', templates);
    window.matchMedia = jest.fn().mockImplementation((query: string) => ({
        media: query,
        matches: Boolean(media[query]),
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
    }));
});

afterEach(() => {
    jest.useRealTimers();
});

describe('theme_dimensions/areas/Areas', () => {
    it('takes the posters over at mount, drawing the server\'s summary back and reading no layout', async() => {
        const rect = jest.spyOn(Element.prototype, 'getBoundingClientRect');
        const top = jest.spyOn(HTMLElement.prototype, 'offsetTop', 'get');
        const page = {filtered: true, posters: [
            {id: 8, name: 'Brigade', count: 2, label: '2 results'},
            {id: 9, name: 'Traffic', count: 0, label: '0 results', hidden: true},
        ], nomatch: {all: 'Nothing in one area', less: '', ids: [9]}};
        drawPage(page);
        const before = document.querySelector('[data-region="dim-areas-nomatch"]')?.outerHTML;
        document.body.innerHTML = '';

        await mount(page);

        expect(mounted).toBe(1);
        expect(questions).toHaveLength(0);
        expect(document.body.classList.contains('dim-panel-js')).toBe(true);
        const control = controlOf(8);
        expect(control.tagName).toBe('BUTTON');
        expect(control.getAttribute('aria-expanded')).toBe('false');
        expect(control.getAttribute('aria-controls')).toBe('dim-panel');
        expect(control.getAttribute('aria-describedby')).toBe('dim-area-results-8');
        expect(control.dataset.region).toBe('dim-poster-control');
        // React draws the summary now, and draws exactly what the server drew.
        expect(document.querySelector('[data-region="dim-areas-nomatch"]')?.outerHTML).toBe(before);
        expect(rect).not.toHaveBeenCalled();
        expect(top).not.toHaveBeenCalled();
        rect.mockRestore();
        top.mockRestore();
    });

    it('adopts a panel the server drew: no question, no measure, its links buttons, its place the row\'s end', async() => {
        const rect = jest.spyOn(Element.prototype, 'getBoundingClientRect');
        await mount({open: 8});

        expect(questions).toHaveLength(0);
        expect(rect).not.toHaveBeenCalled();
        rect.mockRestore();
        expect(painted).toBe(1);
        const panel = panelEl();
        expect(panel.hidden).toBe(false);
        // The DOM order follows the row: after Aviation, the third poster of a three-track grid.
        expect(panel.previousElementSibling?.querySelector('[data-categoryid="10"]')).not.toBeNull();
        // The integers the server wrote are the ones the component would write: none is rewritten.
        expect(panel.getAttribute('style')).toContain('--dim-row-3: 2;');
        const sort = panel.querySelector('[data-region="dim-panel-sort"]') as HTMLElement;
        expect(sort.getAttribute('role')).toBe('radiogroup');
        expect(Array.from(sort.querySelectorAll('button')).map((one) => one.getAttribute('aria-checked'))).toEqual(['true', 'false']);
        expect(panel.querySelector('[data-action="dim-panel-close"]')?.tagName).toBe('BUTTON');
        expect(controlOf(8).getAttribute('aria-expanded')).toBe('true');
        // The server's panel keeps its no-JavaScript presentation until the component first changes it.
        expect(document.body.classList.contains('dim-panel-js')).toBe(false);

        await press(panel.querySelector('[data-action="dim-panel-close"]') as HTMLElement);
        expect(document.body.classList.contains('dim-panel-js')).toBe(true);
        expect(panel.classList.contains('dim-panel--shut')).toBe(true);
        expect(document.activeElement).toBe(controlOf(8));
        expect(window.location.search).toBe('?categoryid=7');
        await flush(300);
        expect(panel.hidden).toBe(true);
    });

    it('opens a pressed poster in place: the address replaced, the holders in step, the rows asked and painted', async() => {
        await mount();
        await press(controlOf(9));

        expect(pushState).not.toHaveBeenCalled();
        expect(replaceState).toHaveBeenCalledTimes(1);
        expect(window.location.search).toBe('?categoryid=7&open=9');
        expect((document.querySelector('[data-region="dim-vitrine-toolbar"] [data-region="dim-open-field"]') as HTMLInputElement).value)
            .toBe('9');
        expect(document.querySelector('[data-region="dim-hero-fold"] [data-region="dim-open-field"]')).not.toBeNull();
        expect(document.querySelector('[data-region="dim-vitrine-clear"]')?.getAttribute('href')).toContain('open=9');
        expect(controlOf(9).getAttribute('aria-expanded')).toBe('true');
        expect(posterEl(9).querySelector('[data-region="dim-poster-trigger"]')?.textContent).toBe('Close');
        expect(document.querySelector('[data-region="dim-areas-hint"]')?.hasAttribute('hidden')).toBe(true);
        const panel = panelEl();
        expect(panel.hidden).toBe(false);
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('Loading Traffic');
        expect(document.activeElement).toBe(panel.querySelector('[data-region="dim-panel-title"]'));
        expect(questions.map((one) => [one.id, one.psort])).toEqual([[9, '']]);

        await answer();
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('9 courses');
        expect(panel.querySelector('[data-region="dim-card"]')?.getAttribute('data-courseid')).toBe('209');
        expect(painted).toBe(1);
        await flush(20);
        expect(document.querySelector('[data-region="dim-panel-status"]')?.textContent).toBe('9 courses');
        // The panel's colour is the open poster's band, and its row the five integers of the second poster.
        expect(panel.style.getPropertyValue('--dim-area-bg')).toBe('#1294f');
        expect(panel.style.getPropertyValue('--dim-col-3')).toBe('1');
    });

    it('places the panel by the breakpoint\'s tracks, read through matchMedia', async() => {
        media['(max-width: 1199.98px)'] = true;
        await mount();
        await press(controlOf(10));

        const panel = panelEl();
        // Two tracks: Aviation (third) starts the second row, which ends with Lifesaving.
        expect(panel.previousElementSibling?.querySelector('[data-categoryid="11"]')).not.toBeNull();
        expect(panel.style.getPropertyValue('--dim-row-2')).toBe('3');
        expect(panel.style.getPropertyValue('--dim-row-3')).toBe('2');
        expect(panel.style.getPropertyValue('--dim-row-1')).toBe('4');
    });

    it('lets only the latest request write, checked after each async render', async() => {
        await mount();
        await press(controlOf(8));
        await press(controlOf(11));
        expect(questions.map((one) => one.id)).toEqual([8, 11]);

        // The first area's answer lands after the reader moved on: it writes nothing.
        await answer(answerOf(8));
        const panel = panelEl();
        expect(panel.querySelector('[data-region="dim-panel-section"]')?.getAttribute('data-categoryid')).toBe('11');
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('Loading Lifesaving');
        // Nor does it stop the newer request's skeleton from coming.
        await flush(150);
        expect(panel.querySelector('[data-region="dim-panel-rail"][aria-busy="true"]')).not.toBeNull();
        await answer(answerOf(11));
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('11 courses');
    });

    it('never lets an opening superseded while its render was out write its header', async() => {
        await mount();
        holdRenders = (context) => Boolean(context.isloading) && context.id === 8;
        await press(controlOf(8));
        expect(held).toHaveLength(1);
        holdRenders = () => false;
        await press(controlOf(11));
        await act(async() => held[0].release());
        await flush();
        const panel = panelEl();
        expect(panel.querySelector('[data-region="dim-panel-section"]')?.getAttribute('data-categoryid')).toBe('11');
        expect(questions.map((one) => one.id)).toEqual([11]);
    });

    it('never lets a skeleton rendered after the answer stay the panel\'s body', async() => {
        await mount();
        holdRenders = (context) => Boolean(context.isskeleton);
        await press(controlOf(8));
        await flush(150);
        expect(held).toHaveLength(1);

        await answer();
        const panel = panelEl();
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('8 courses');
        await act(async() => held[0].release());
        await flush();
        expect(panel.querySelector('[data-region="dim-card"]')).not.toBeNull();
        expect(panel.querySelector('[aria-busy="true"]')).toBeNull();
    });

    it('shows the skeleton when the answer is slow, and the inline error with its retry when it fails', async() => {
        await mount();
        await press(controlOf(8));
        await flush(150);
        const panel = panelEl();
        expect(panel.querySelector('[data-region="dim-panel-rail"][aria-busy="true"]')).not.toBeNull();

        await act(async() => (questions.shift() as Question).reject(new Error('down')));
        await flush();
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('Could not load Brigade');
        await press(panel.querySelector('[data-action="dim-panel-retry"]') as HTMLElement);
        expect(questions.map((one) => one.id)).toEqual([8]);
        await answer();
        expect(panel.querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('8 courses');
    });

    it('starts every step from the transition classes it sets itself', async() => {
        await mount();
        await press(controlOf(8));
        await answer();
        const panel = panelEl();
        // Traffic shares Brigade's row: the content crossfades.
        await press(controlOf(9));
        expect(panel.classList.contains('dim-panel--fading')).toBe(true);
        // Closed inside the crossfade: the close step's state, and nothing the superseded one set.
        await act(async() => (panel.querySelector('[data-region="dim-panel-title"]') as HTMLElement).focus());
        await key('Escape');
        expect(Array.from(panel.classList).filter((name) => name.startsWith('dim-panel--'))).toEqual(['dim-panel--shut']);
        await flush(400);
        expect(Array.from(panel.classList).filter((name) => name.startsWith('dim-panel--'))).toEqual(['dim-panel--shut']);
        expect(panel.hidden).toBe(true);

        await press(controlOf(11));
        await flush(50);
        expect(panel.classList.contains('dim-panel--fading')).toBe(false);
        expect(panel.classList.contains('dim-panel--shut')).toBe(false);
    });

    it('closes on Escape from inside the panel or its poster only', async() => {
        await mount();
        await press(controlOf(8));
        await answer();

        (document.querySelector('input[type="search"]') as HTMLElement).focus();
        await key('Escape');
        expect(window.location.search).toContain('open=8');
        (posterEl(8).querySelector('[data-action="dim-cover-play"]') as HTMLElement).focus();
        await key('Escape');
        expect(window.location.search).toContain('open=8');
        controlOf(9).focus();
        await key('Escape');
        expect(window.location.search).toContain('open=8');

        controlOf(8).focus();
        await key('Escape');
        expect(window.location.search).not.toContain('open=');
        expect(document.activeElement).toBe(controlOf(8));
    });

    it('writes an answer onto the posters, keeps a zeroed open poster until it closes, then focuses the summary', async() => {
        await mount();
        await press(controlOf(8));
        await answer();

        await act(async() => {
            document.dispatchEvent(new CustomEvent(EVENTS.pending, {detail: {filtered: true}}));
        });
        expect(posterEl(9).querySelector('[data-region="dim-poster-pill"]')?.textContent).toBe('…');

        await criteria({
            accordion: [
                {id: 8, count: 0, resultlabel: '0 results', resultcount: '0', expandable: true, ctaurl: '/c8', ctalabel: 'Go 8',
                    linkable: true, url: '/a8'},
                {id: 9, count: 3, resultlabel: '3 results', resultcount: '3', expandable: true},
                {id: 10, count: 0, resultlabel: '0 results', resultcount: '0', expandable: true, linkable: true, url: '/a10'},
                {id: 11, count: 1, resultlabel: '1 result', resultcount: '1', expandable: true},
            ],
            areas: {filtered: true, nomatchlabelall: 'Nothing in 2 areas', nomatchlabelless: 'Nothing in one area'},
        });
        expect(posterEl(8).dataset.ctaurl).toBe('/c8');
        expect((posterEl(8).parentElement as HTMLElement).hidden).toBe(false);
        expect((posterEl(10).parentElement as HTMLElement).hidden).toBe(true);
        expect(posterEl(9).querySelector('[data-region="dim-poster-pill"]')?.textContent).toBe('3 results');
        expect(controlOf(9).getAttribute('aria-describedby')).toBe('dim-area-results-9');
        const box = document.querySelector('[data-region="dim-areas-nomatch"]') as HTMLElement;
        expect(box.hidden).toBe(false);
        expect(box.querySelector('summary')?.textContent).toBe('Nothing in one area');
        expect(Array.from(box.querySelectorAll('li')).map((item) => [item.dataset.categoryid, item.hidden])).toEqual([['8', true], ['10', false]]);
        // The open panel is asked again for the new criteria.
        expect(questions.map((one) => one.id)).toEqual([8]);
        await answer(answerOf(8, {isrows: false, iszero: true, zero: 'Nothing here.'}));
        expect(panelEl().querySelector('[data-action="dim-panel-openarea"]')).not.toBeNull();

        controlOf(8).focus();
        await press(controlOf(8));
        expect((posterEl(8).parentElement as HTMLElement).hidden).toBe(true);
        expect(box.querySelector('summary')?.textContent).toBe('Nothing in 2 areas');
        expect(document.activeElement).toBe(box.querySelector('summary'));
    });

    it('puts every pill back when the request fails', async() => {
        await mount({filtered: true, posters: [{id: 8, name: 'Brigade', count: 2, label: '2 results'}]});
        await act(async() => {
            document.dispatchEvent(new CustomEvent(EVENTS.pending, {detail: {filtered: true}}));
        });
        const pill = posterEl(8).querySelector('[data-region="dim-poster-pill"]') as HTMLElement;
        expect(pill.textContent).toBe('…');
        expect(document.querySelector('[data-region="dim-areas-grid"]')?.getAttribute('aria-busy')).toBe('true');
        await act(async() => {
            document.dispatchEvent(new CustomEvent(EVENTS.failed));
        });
        expect(pill.textContent).toBe('2 results');
        expect(document.querySelector('[data-region="dim-areas-grid"]')?.hasAttribute('aria-busy')).toBe(false);
    });

    it('opens the one area holding every result by itself, never over a panel the reader opened', async() => {
        const told: number[] = [];
        document.addEventListener(EVENTS.autoopened, (event) => told.push((event as CustomEvent).detail.id));
        await mount();
        const search = document.querySelector('input[type="search"]') as HTMLElement;
        search.focus();
        await criteria({accordion: [], areas: {filtered: true, autoopen: 10}});
        expect(window.location.search).toContain('open=10');
        expect(document.activeElement).toBe(search);
        expect(told).toEqual([10]);
        await answer();

        await press(controlOf(9));
        await criteria({accordion: [], areas: {filtered: true, autoopen: 11}});
        expect(window.location.search).toContain('open=9');
        expect(told).toEqual([10]);
    });

    it('makes a poster the link to its page again when an answer at rest says it opens nothing', async() => {
        await mount();
        await criteria({accordion: [{id: 11, count: 0, expandable: false}], areas: {filtered: false}});
        const control = controlOf(11);
        expect(control.tagName).toBe('A');
        expect(control.getAttribute('href')).toBe('https://example.com/course/index.php?categoryid=11');
        expect(posterEl(11).classList.contains('dim-poster--empty')).toBe(true);
        expect(posterEl(11).querySelector('[data-region="dim-poster-trigger"]')?.textContent).toBe('No courses yet');
        expect((posterEl(11).querySelector('[data-region="dim-poster-emptynote"]') as HTMLElement).hidden).toBe(false);
    });

    it('follows the back button: a panel opened or closed by the address', async() => {
        await mount();
        await press(controlOf(8));
        await answer();
        await act(async() => {
            window.history.replaceState(null, '', '/course/index.php?categoryid=7');
            window.dispatchEvent(new PopStateEvent('popstate'));
        });
        await flush(300);
        expect(panelEl().hidden).toBe(true);
        await act(async() => {
            window.history.replaceState(null, '', '/course/index.php?categoryid=7&open=11&psort=name');
            window.dispatchEvent(new PopStateEvent('popstate'));
        });
        await flush();
        expect(questions.map((one) => [one.id, one.psort])).toEqual([[11, 'name']]);
    });

    it('changes the order from the radio group, one question at a time', async() => {
        await mount();
        await press(controlOf(8));
        await answer();
        const panel = panelEl();
        const name = panel.querySelector('[data-psort="name"]') as HTMLElement;
        await press(name);
        expect(window.location.search).toContain('psort=name');
        expect(name.getAttribute('aria-checked')).toBe('true');
        expect(questions.map((one) => one.psort)).toEqual(['name']);
        // While the question is out the orders are busy, and a press asks nothing.
        await press(panel.querySelector('[data-psort="recent"]') as HTMLElement);
        expect(questions).toHaveLength(1);
    });
});

describe('theme_dimensions/areas/Areas on the visitor\'s shell', () => {
    it('reads its options from the props, and anything but the exact values as the logged-in shell\'s', () => {
        expect(optionsOf({})).toEqual({links: false, quiet: 0, autoopen: true});
        expect(optionsOf(VISITOR)).toEqual({links: true, quiet: 120, autoopen: false});
        expect(optionsOf({links: 'true', quiet: -5, autoopen: 0})).toEqual({links: false, quiet: 0, autoopen: true});
        expect(optionsOf({quiet: 1.5})).toEqual({links: false, quiet: 0, autoopen: true});
    });

    it('keeps every poster the link it was, announced as the button it acts as', async() => {
        await mount({filtered: true, posters: [{id: 8, name: 'Brigade', count: 2, label: '2 results'}]}, VISITOR);

        const control = controlOf(8);
        expect(control.tagName).toBe('A');
        expect(control.getAttribute('href')).toBe('https://example.com/course/index.php?categoryid=7&open=8#dim-panel');
        expect(control.getAttribute('role')).toBe('button');
        expect(control.getAttribute('aria-expanded')).toBe('false');
        expect(control.getAttribute('aria-controls')).toBe('dim-panel');
        expect(control.getAttribute('aria-describedby')).toBe('dim-area-results-8');
        expect(document.body.classList.contains('dim-panel-js')).toBe(true);
        expect(questions).toHaveLength(0);
    });

    it('opens on a plain press, leaves a press meant for another tab to the link, and waits for the quiet', async() => {
        await mount({}, VISITOR);
        // Heard after the component's listener: what it left to the link, then no navigation for jsdom.
        const left: boolean[] = [];
        const record = (event: Event): void => {
            left.push(!event.defaultPrevented);
            event.preventDefault();
        };
        window.addEventListener('click', record);

        await act(async() => {
            controlOf(9).dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true, ctrlKey: true}));
        });
        await flush(200);
        expect(left).toEqual([true]);
        expect(panelEl().hidden).toBe(true);
        expect(controlOf(9).getAttribute('aria-expanded')).toBe('false');

        await act(async() => {
            controlOf(9).dispatchEvent(new MouseEvent('click', {bubbles: true, cancelable: true}));
        });
        await flush();
        window.removeEventListener('click', record);
        expect(left).toEqual([true, false]);
        expect(controlOf(9).tagName).toBe('A');
        expect(controlOf(9).getAttribute('aria-expanded')).toBe('true');
        expect(window.location.search).toBe('?categoryid=7&open=9');
        expect(document.activeElement).toBe(panelEl().querySelector('[data-region="dim-panel-title"]'));
        // Nothing is asked before the quiet is over, and the skeleton waits for it too.
        await flush(119);
        expect(questions).toHaveLength(0);
        await flush(1);
        expect(questions.map((one) => one.id)).toEqual([9]);
        expect(panelEl().querySelector('[aria-busy="true"]')).toBeNull();
        await flush(150);
        expect(panelEl().querySelector('[aria-busy="true"]')).not.toBeNull();
        await answer();
        expect(panelEl().querySelector('[data-region="dim-card"]')?.getAttribute('data-courseid')).toBe('209');
    });

    it('asks only for the last of several presses within the quiet', async() => {
        await mount({}, VISITOR);
        await press(controlOf(8));
        await flush(60);
        await press(controlOf(9));
        await flush(60);
        await press(controlOf(10));
        // A move within the row crossfades first (150 ms), then the quiet.
        await flush(150);
        await flush(120);
        expect(questions.map((one) => one.id)).toEqual([10]);
    });

    it('opens and closes a poster that stays a link with Space, as a button; the logged-in shell leaves Space alone', async() => {
        await mount({}, VISITOR);
        controlOf(8).focus();
        await key(' ');
        expect(controlOf(8).getAttribute('aria-expanded')).toBe('true');
        controlOf(8).focus();
        await key(' ');
        expect(controlOf(8).getAttribute('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(controlOf(8));
    });

    it('leaves Space to the browser on the logged-in shell\'s buttons', async() => {
        await mount();
        controlOf(8).focus();
        await key(' ');
        expect(controlOf(8).getAttribute('aria-expanded')).toBe('false');
        expect(panelEl().hidden).toBe(true);
    });

    it('asks a question once per opening: an answer to unchanged criteria is the panel on screen', async() => {
        await mount({}, VISITOR, {question: (id, psort) => `${id}/${psort}/${window.location.search.replace(/[?&]open=\d+/, '')}`});
        await press(controlOf(8));
        await flush(120);
        await answer();
        expect(questions).toHaveLength(0);

        await criteria({accordion: [], areas: {}});
        await flush(200);
        expect(questions).toHaveLength(0);

        window.history.replaceState(null, '', '/course/index.php?categoryid=7&q=sea&open=8');
        await criteria({accordion: [], areas: {}});
        await flush(120);
        expect(questions.map((one) => one.id)).toEqual([8]);
        await answer();

        // A new opening of the same area is a new question, whatever it would ask.
        await press(controlOf(8));
        await flush(300);
        await press(controlOf(8));
        await flush(120);
        expect(questions.map((one) => one.id)).toEqual([8]);
    });

    it('asks every time where the client names no question', async() => {
        await mount();
        await press(controlOf(8));
        await answer();
        await criteria({accordion: [], areas: {}});
        expect(questions.map((one) => one.id)).toEqual([8]);
    });

    it('counts a panel the server drew as the answer to its address\'s question', async() => {
        const asked: string[] = [];
        await mount({open: 8}, VISITOR, {question: (id, psort) => {
            asked.push(`${id}/${psort}`);
            return `${id}/${psort}`;
        }});
        expect(asked).toEqual(['8/']);
        expect(controlOf(8).getAttribute('aria-expanded')).toBe('true');
        await criteria({accordion: [], areas: {}});
        await flush(300);
        expect(questions).toHaveLength(0);
    });

    it('hands a failure to the client before the inline error, and asks again after it', async() => {
        const failures: number[] = [];
        await mount({}, VISITOR, {question: (id) => `q${id}`, unanswered: (id) => {
            failures.push(id);
            return true;
        }});
        await press(controlOf(8));
        await flush(120);
        await act(async() => {
            (questions.shift() as Question).reject(new Error('Refused.'));
        });
        await flush(300);
        expect(failures).toEqual([8]);
        expect(panelEl().querySelector('[data-region="dim-panel-subtitle"]')?.textContent).not.toBe('Could not load Brigade');
        expect(panelEl().querySelector('[data-action="dim-panel-retry"]')).toBeNull();

        await criteria({accordion: [], areas: {}});
        await flush(120);
        expect(questions.map((one) => one.id)).toEqual([8]);
    });

    it('draws the inline error where the client leaves the failure', async() => {
        await mount({}, VISITOR, {unanswered: () => false});
        await press(controlOf(8));
        await flush(120);
        await act(async() => {
            (questions.shift() as Question).reject(new Error('Refused.'));
        });
        await flush();
        expect(panelEl().querySelector('[data-region="dim-panel-subtitle"]')?.textContent).toBe('Could not load Brigade');
    });

    it('never opens an area by itself', async() => {
        const told: number[] = [];
        const listener = (event: Event): void => {
            told.push((event as CustomEvent).detail.id);
        };
        document.addEventListener(EVENTS.autoopened, listener);
        await mount({}, VISITOR);
        await criteria({accordion: [], areas: {filtered: true, autoopen: 10}});
        await flush(300);
        document.removeEventListener(EVENTS.autoopened, listener);
        expect(window.location.search).toBe('?categoryid=7');
        expect(panelEl().hidden).toBe(true);
        expect(told).toEqual([]);
        expect(questions).toHaveLength(0);
    });

    it('takes the button state off a link that opens nothing, and gives it back', async() => {
        await mount({}, VISITOR);
        const before = controlOf(11).getAttribute('href');
        await criteria({accordion: [{id: 11, count: 0, expandable: false}], areas: {filtered: false}});
        const control = controlOf(11);
        expect(control.tagName).toBe('A');
        expect(control.getAttribute('href')).toBe(before);
        ['role', 'aria-expanded', 'aria-controls'].forEach((name) => expect(control.hasAttribute(name)).toBe(false));
        expect(posterEl(11).classList.contains('dim-poster--empty')).toBe(true);

        await criteria({accordion: [{id: 11, count: 2, expandable: true}], areas: {filtered: false}});
        expect(controlOf(11).getAttribute('role')).toBe('button');
        expect(controlOf(11).getAttribute('aria-expanded')).toBe('false');
    });
});
