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
 * Tests for the public calendar's client, the visitor's shell.
 *
 * Rendered inside a page like calendar_page.mustache's visitor mode; the visitor's two halves are plain GETs, the global
 * fetch mocked. What is read: the first paint, the two event types, only the halves whose key is on asked without a
 * cookie, the latest answer winning, a failure loading the address, and the popover's text and way in.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {redirect} from '@moodle/lms/core/location';
import PublicCalendar, {
    publicAddress,
    readPublicAddress,
} from '../../src/calendar/PublicCalendar';
import type {PublicAnswer, PublicProps} from '../../src/calendar/PublicCalendar';
import type {Course, Item} from '../../src/calendar/model';
import {STRINGS, answer, at, item} from './fixture';

jest.mock('@moodle/lms/core/location', () => ({redirect: jest.fn()}));

const fetched = jest.fn();
const redirected = redirect as jest.Mock;

/** The page's address without a query. */
const PAGE = 'http://localhost/r.php/theme_dimensions/calendar';

/** The halves' address prefixes, as calendar_page::props() hands them. */
const EVENTS = 'http://localhost/r.php/theme_dimensions/calendar/public/events/t1/pt_br';
const COURSES = 'http://localhost/r.php/theme_dimensions/calendar/public/courses/t2/pt_br';

/**
 * A response of the public route.
 *
 * @param {object} body The half.
 * @param {number} status The status.
 * @returns {object} What fetch resolves with.
 */
const respond = (body: unknown, status = 200): Response => ({
    ok: status >= 200 && status < 300,
    status: status,
    json: () => Promise.resolve(body),
} as unknown as Response);

/**
 * The window an address asks for: its last two segments.
 *
 * @param {string} url The address.
 * @returns {object} {from, to}.
 */
const windowOf = (url: string): {from: string, to: string} => {
    const parts = url.split('/');
    return {from: parts[parts.length - 2], to: parts[parts.length - 1]};
};

/**
 * An answer of the visitor's shape.
 *
 * @param {Array} events The items.
 * @param {object} more Fields that differ.
 * @returns {object} The answer.
 */
const publicAnswer = (events: Item[] = [], more: Partial<PublicAnswer> = {}): PublicAnswer => ({
    ...answer(events, '2026-11-01', '2026-12-05'),
    descriptions: [],
    ...more,
});

/**
 * A public event course's dictionary entry: its landing page as its one address.
 *
 * @param {number} id The course.
 * @returns {object}
 */
const course = (id: number): Course => ({
    id: id,
    fullname: `Congress ${id}`,
    shortname: `C${id}`,
    categoryid: 0,
    url: `http://localhost/theme/dimensions/landing.php?id=${id}`,
    image: '',
    model: 2,
    modality: 0,
    city: '',
    venue: '',
    landingurl: `http://localhost/theme/dimensions/landing.php?id=${id}`,
    enrolled: false,
    participants: null,
    join: 'open',
});

/**
 * The visitor's props for November 2026.
 *
 * @param {object} given The answer.
 * @param {object} more Props that differ.
 * @returns {object}
 */
const props = (given: PublicAnswer = publicAnswer(), more: Partial<PublicProps> = {}): PublicProps => ({
    month: '2026-11',
    view: 'month',
    week: '',
    day: '',
    today: '2026-11-17',
    now: at('2026-11-17', 10),
    firstday: 0,
    timezone: 'UTC',
    answer: given,
    keys: ['category', 'open'],
    modalities: [],
    urls: {page: PAGE, home: 'http://localhost/', events: EVENTS, courses: COURSES},
    ...more,
});

/**
 * Render the page and wait for its grid.
 *
 * @param {object} given The props.
 * @returns {Promise} The page's root.
 */
const mount = async(given: PublicProps): Promise<HTMLElement> => {
    const page = document.createElement('div');
    page.dataset.region = 'dim-cal';
    const server = document.createElement('div');
    server.dataset.region = 'dim-cal-server';
    const node = document.createElement('div');
    page.append(server, node);
    document.body.append(page);
    await act(async() => {
        render(<PublicCalendar {...given} />, {container: node});
    });
    await waitFor(() => expect(page.querySelector('[role="grid"]')).not.toBeNull());
    return page;
};

/**
 * The requests made, with their options.
 *
 * @returns {Array} [address, options] pairs.
 */
const calls = (): Array<[string, RequestInit]> => fetched.mock.calls as Array<[string, RequestInit]>;

/**
 * The addresses asked, in order.
 *
 * @returns {Array} The addresses.
 */
const urls = (): string[] => calls().map(([url]) => url);

/**
 * An item of the grid by its source and id.
 *
 * @param {number} id The id.
 * @param {string} source The source.
 * @returns {HTMLElement}
 */
const gridItem = (id: number, source = 'core'): HTMLElement => {
    const selector = `[data-region="dim-cal-grid"] [data-cal-pop][data-source="${source}"][data-id="${id}"]`;
    return document.querySelector<HTMLElement>(selector) as HTMLElement;
};

/**
 * The open popover, or null.
 *
 * @returns {HTMLElement|null}
 */
const popover = (): HTMLElement | null => document.querySelector<HTMLElement>('[data-region="dim-cal-popover"]');

/** The category event every popover test opens. */
const fair = (): Item => item({id: 5, key: 'category', eventtype: 'category', categoryid: 3, name: 'Fair',
    url: 'http://localhost/theme/dimensions/category.php?id=3', timestart: at('2026-11-10', 9)});

beforeEach(() => {
    document.documentElement.lang = 'en';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    fetched.mockReset();
    fetched.mockImplementation((url: string) => {
        const {from, to} = windowOf(url);
        return Promise.resolve(respond({...answer([], from, to), descriptions: []}));
    });
    (global as unknown as {fetch: unknown}).fetch = fetched;
    window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar');
    redirected.mockReset();
});

afterEach(() => {
    document.body.replaceChildren();
    delete (global as unknown as {fetch?: unknown}).fetch;
});

describe('the first paint', () => {
    it('draws from the embedded answer, asks for nothing, and hides the server\'s layer', async() => {
        const page = await mount(props(publicAnswer([fair()])));
        expect(fetched).not.toHaveBeenCalled();
        expect(page.querySelector<HTMLElement>('[data-region="dim-cal-server"]')?.hidden).toBe(true);
        // The root is marked mounted: the stylesheet retires its placeholder and cancels the server layer's fail-safe.
        expect(page.classList.contains('dim-cal--mounted')).toBe(true);
        expect(gridItem(5)).not.toBeNull();
    });

    it('tells a cut answer in the visitor\'s own sentence', async() => {
        const page = await mount(props(publicAnswer([fair()], {truncated: true})));
        expect(page.textContent).toContain(STRINGS.calendar_truncatedpublic);
        expect(page.textContent).not.toContain(STRINGS.calendar_truncated);
    });

    it('draws the two event types and nothing that needs a session or a login', async() => {
        const page = await mount(props());
        const boxes = Array.from(page.querySelectorAll<HTMLInputElement>('[data-region="dim-cal-types"] input[data-key]'));
        expect(boxes.map((box) => box.dataset.key)).toEqual(['category', 'open']);
        expect(boxes.every((box) => box.checked)).toBe(true);
        expect(page.querySelector('[data-action="dim-cal-types"]')?.textContent).toBe('Event typesAll');
        expect(page.querySelector('[data-action="dim-cal-clear"]')).toBeNull();
        expect(page.querySelector('a[href*="calendar/view.php"]')).toBeNull();
        expect(page.querySelector('[role="tree"]')).toBeNull();
        expect(page.textContent).not.toContain(STRINGS.calendar_morefilters);
        expect(page.textContent).not.toContain(STRINGS.calendar_category + ' ');
    });
});

describe('the halves', () => {
    it('a chip off asks the other half alone, a plain GET with no cookie, and replaces the address', async() => {
        const replace = jest.spyOn(window.history, 'replaceState');
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-region="dim-cal-types"] [data-key="category"]') as HTMLElement);
        });
        expect(document.querySelector('[data-action="dim-cal-types"]')?.textContent).toBe('Event types1 of 2');
        expect(calls()).toHaveLength(1);
        const [url, options] = calls()[0];
        expect(url).toBe(`${COURSES}/2026-11-01/2026-12-05`);
        expect(options).toEqual({credentials: 'omit', headers: {Accept: 'application/json'}});
        expect(replace.mock.calls.some(([, , address]) => String(address).includes('keys%5B%5D=open'))).toBe(true);
        replace.mockRestore();
    });

    it('both keys on ask both halves at once, and show their merge once both have answered', async() => {
        const congress = item({id: 12, source: 'course', key: 'open', courseid: 12, name: 'Congress 12', eventtype: '',
            url: course(12).landingurl, timestart: at('2026-12-01'), timeduration: 86400});
        const fairdec = item({id: 5, key: 'category', eventtype: 'category', categoryid: 3, name: 'Fair',
            timestart: at('2026-12-10', 9)});
        let release: () => void = () => undefined;
        const gate = new Promise<void>((resolve) => {
            release = resolve;
        });
        fetched.mockImplementation(async(url: string) => {
            const {from, to} = windowOf(url);
            await gate;
            return url.startsWith(EVENTS)
                ? respond({...answer([fairdec], from, to), descriptions: [{id: 5, text: 'Bring a pen'}]})
                : respond({...answer([congress], from, to), courses: [course(12)], descriptions: []});
        });
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        // Both asked before either answered.
        expect(urls()).toEqual([`${EVENTS}/2026-11-29/2027-01-02`, `${COURSES}/2026-11-29/2027-01-02`]);
        await act(async() => {
            release();
        });
        await waitFor(() => expect(gridItem(5)).not.toBeNull());
        expect(gridItem(12, 'course')).not.toBeNull();
        await act(async() => {
            fireEvent.click(gridItem(5));
        });
        expect(popover()?.querySelector('[data-region="dim-cal-description"]')?.textContent).toBe('Bring a pen');
    });

    it('one half refused fails the whole request: the address is loaded, nothing half drawn', async() => {
        fetched.mockImplementation((url: string) => {
            const {from, to} = windowOf(url);
            return Promise.resolve(url.startsWith(COURSES)
                ? respond({}, 500)
                : respond({...answer([], from, to), descriptions: []}));
        });
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(redirected).toHaveBeenCalledTimes(1));
        expect(String(redirected.mock.calls[0][0])).toBe(`${PAGE}?month=2026-12`);
    });

    it('"Clear filters" turns both keys back on, asking both halves, and is gone', async() => {
        await mount(props(publicAnswer(), {keys: ['open']}));
        const clear = document.querySelector<HTMLElement>('[data-action="dim-cal-clear"]') as HTMLElement;
        await act(async() => {
            fireEvent.click(clear);
        });
        expect(urls()).toEqual([`${EVENTS}/2026-11-01/2026-12-05`, `${COURSES}/2026-11-01/2026-12-05`]);
        expect(document.querySelector('[data-action="dim-cal-clear"]')).toBeNull();
        expect(document.activeElement).toBe(document.querySelector('[data-action="dim-cal-types"]'));
    });

    it('both keys off ask nothing; a month moved asks again and pushes the address', async() => {
        const push = jest.spyOn(window.history, 'pushState');
        await mount(props(publicAnswer(), {keys: ['open']}));
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="open"]') as HTMLElement);
        });
        expect(fetched).not.toHaveBeenCalled();
        await act(async() => {
            fireEvent.click(document.querySelector('[data-key="category"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        expect(urls()).toEqual([`${EVENTS}/2026-11-01/2026-12-05`, `${EVENTS}/2026-11-29/2027-01-02`]);
        expect(push.mock.calls.some(([, , url]) => String(url).includes('month=2026-12'))).toBe(true);
        push.mockRestore();
    });

    it('the latest request wins', async() => {
        let release: (value: PublicAnswer) => void = () => undefined;
        fetched.mockImplementationOnce(() => new Promise((resolve) => {
            release = (value) => resolve(respond(value));
        }));
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-caption"]')?.textContent).toBe('December 2026'));
        await act(async() => {
            release(publicAnswer([item({id: 9, name: 'Stale', key: 'category', timestart: at('2026-12-10', 9)})]));
        });
        expect(document.body.textContent).not.toContain('Stale');
    });

    it('a refusal of the route loads the address of what was asked for, which the server draws', async() => {
        fetched.mockImplementationOnce(() => Promise.resolve(respond({error: 'Refused'}, 404)));
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(redirected).toHaveBeenCalledTimes(1));
        expect(String(redirected.mock.calls[0][0])).toBe(`${PAGE}?month=2026-12`);
        expect(document.body.textContent).not.toContain('Refused');
    });

    it('a failure loads the address of what was asked for, which the server draws', async() => {
        fetched.mockImplementationOnce(() => Promise.reject(new Error('Refused')));
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-next"]') as HTMLElement);
        });
        await waitFor(() => expect(redirected).toHaveBeenCalledTimes(1));
        expect(String(redirected.mock.calls[0][0])).toBe(`${PAGE}?month=2026-12`);
        expect(document.body.textContent).not.toContain('Refused');
    });
});

describe('the popover', () => {
    it('draws the answer\'s description as text, asks core nothing, and links the category\'s public page', async() => {
        const description = '<b>Bring</b> a pen';
        await mount(props(publicAnswer([fair()], {descriptions: [{id: 5, text: description}]})));
        await act(async() => {
            fireEvent.click(gridItem(5));
        });
        const dialog = popover() as HTMLElement;
        const text = dialog.querySelector('[data-region="dim-cal-description"]') as HTMLElement;
        expect(text.textContent).toBe(description);
        expect(text.querySelector('b')).toBeNull();
        expect(dialog.querySelector('.dim-cal__poploading')).toBeNull();
        expect(fetched).not.toHaveBeenCalled();
        expect(dialog.querySelector<HTMLAnchorElement>('[data-action="dim-cal-open"]')?.href)
            .toBe('http://localhost/theme/dimensions/category.php?id=3');
    });

    it('an event course leads to its landing page, "Enrol" first, and no course page beside it', async() => {
        const congress = item({id: 12, source: 'course', key: 'open', courseid: 12, name: 'Congress 12', eventtype: '',
            url: course(12).landingurl, timestart: at('2026-11-03'), timeduration: 2 * 86400});
        await mount(props(publicAnswer([congress], {courses: [course(12)]})));
        await act(async() => {
            fireEvent.click(gridItem(12, 'course'));
        });
        const dialog = popover() as HTMLElement;
        const primary = dialog.querySelector<HTMLAnchorElement>('[data-action="dim-cal-join"]') as HTMLAnchorElement;
        expect(primary.textContent).toBe(STRINGS.calendar_join_open);
        expect(primary.href).toBe(course(12).landingurl);
        expect(dialog.querySelector('[data-action="dim-cal-course"]')).toBeNull();
        expect(dialog.querySelector('[data-region="dim-cal-description"]')).toBeNull();
    });

    it('a click on a day\'s ground opens it for a visitor too', async() => {
        await mount(props(publicAnswer([fair()])));
        const cell = document.querySelector<HTMLElement>('[data-role="day"][data-day="2026-11-12"]')?.parentElement as HTMLElement;
        fireEvent.click(cell.querySelector('.dim-cal__ground') as HTMLElement, {detail: 1});
        await waitFor(() => expect(document.querySelector('[data-region="dim-cal-day"]')?.getAttribute('data-day')).toBe('2026-11-12'));
    });

    it('the day drawer has no link to core\'s calendar', async() => {
        await mount(props(publicAnswer([fair()])));
        fireEvent.click(document.querySelector('[data-role="day"][data-day="2026-11-10"]') as HTMLElement);
        const drawer = await waitFor(() => {
            const found = document.querySelector<HTMLElement>('[data-region="dim-cal-day"]');
            expect(found).not.toBeNull();
            return found as HTMLElement;
        });
        expect(drawer.querySelector('a[href*="calendar/view.php"]')).toBeNull();
        expect(drawer.textContent).toContain('Fair');
    });
});

describe('the address', () => {
    it('names the keys only when one is off, and none with an empty member', () => {
        expect(publicAddress(PAGE, '2026-11', ['category', 'open'])).toBe(`${PAGE}?month=2026-11`);
        expect(publicAddress(PAGE, '2026-11', ['open'], '2026-11-03')).toBe(`${PAGE}?month=2026-11&day=2026-11-03&keys%5B%5D=open`);
        expect(publicAddress(PAGE, '2026-11', [])).toBe(`${PAGE}?month=2026-11&keys%5B%5D=`);
    });

    it('reads back what it writes, and drops a key of the logged-in page', () => {
        expect(readPublicAddress(`${PAGE}?month=2026-11`)).toEqual({month: '2026-11', on: ['category', 'open']});
        expect(readPublicAddress(`${PAGE}?month=2026-11&keys%5B%5D=open`)).toEqual({month: '2026-11', on: ['open']});
        expect(readPublicAddress(`${PAGE}?keys%5B%5D=`)).toEqual({month: null, on: []});
        expect(readPublicAddress(`${PAGE}?keys%5B%5D=site&keys%5B%5D=user`)).toEqual({month: null, on: []});
    });
});

describe('the visitor edits nothing (C5)', () => {
    it('draws no "New event", no Edit or Delete and no drag, whatever an answer would list', async() => {
        const events = [item({id: 4, name: 'Fair', key: 'category', timestart: at('2026-11-17', 9)})];
        const editing = [{id: 4, edit: true, delete: true, drag: true, count: 1, minday: '', maxday: '', minerror: '', maxerror: ''}];
        fetched.mockImplementation(() => Promise.resolve(respond(publicAnswer())));
        const page = await mount(props(publicAnswer(events, {editing: editing}), {day: '2026-11-17'}));
        const element = page.querySelector<HTMLElement>('[data-region="dim-cal-grid"] [data-cal-pop][data-id="4"]') as HTMLElement;
        expect(element).not.toBeNull();
        expect(element.hasAttribute('draggable')).toBe(false);
        expect(page.querySelector('[data-cal-drop]')).toBeNull();
        expect(page.querySelector('[data-action="dim-cal-new"]')).toBeNull();
        await act(async() => {
            fireEvent.click(element);
        });
        expect(page.querySelector('[data-region="dim-cal-popover"]')).not.toBeNull();
        expect(page.querySelector('[data-region="dim-cal-edit"]')).toBeNull();
    });
});

describe('the Day view, a phone\'s (R1d)', () => {
    /** The callbacks the page's ResizeObservers registered, the latest last. */
    let observers: Array<(entries: unknown[]) => void> = [];

    beforeEach(() => {
        observers = [];
        (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
            /**
             * Keep the callback.
             *
             * @param {Function} callback The observer's callback.
             */
            constructor(callback: (entries: unknown[]) => void) {
                observers.push(callback);
            }

            /**
             * Nothing to note.
             *
             * @returns {void}
             */
            observe(): void {
                return;
            }

            /**
             * Nothing to release.
             *
             * @returns {void}
             */
            disconnect(): void {
                return;
            }
        };
    });

    afterEach(() => {
        delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
    });

    /**
     * Report a width for the frame, through the latest observer.
     *
     * @param {number} width The frame's width, in px.
     * @returns {Promise}
     */
    const measure = async(width: number): Promise<void> => {
        const frame = document.querySelector('[data-region="dim-cal-body"]') as Element;
        await act(async() => {
            observers[observers.length - 1]([{target: frame, contentRect: {width: width, height: 600}}]);
        });
    };

    it('offers Day on a phone, asks the visitor\'s halves for that day alone and draws it', async() => {
        await mount(props(publicAnswer([fair()])));
        await measure(900);
        expect(document.querySelector('[data-action="dim-cal-view-day"]')).toBeNull();
        await measure(400);
        fetched.mockImplementation((url: string) => {
            const {from, to} = windowOf(url);
            return Promise.resolve(respond({...answer(url.startsWith(EVENTS) ? [fair()] : [], from, to), descriptions: []}));
        });
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-view-day"]') as HTMLElement);
        });
        expect(urls()).toEqual([`${EVENTS}/2026-11-17/2026-11-17`, `${COURSES}/2026-11-17/2026-11-17`]);
        expect(window.location.search).toBe('?view=day&day=2026-11-17');
        await act(async() => {
            fireEvent.click(document.querySelector('[data-action="dim-cal-prev"]') as HTMLElement);
        });
        const view = document.querySelector('[data-region="dim-cal-dayview"]') as HTMLElement;
        expect(view.dataset.day).toBe('2026-11-16');
        expect(window.location.search).toBe('?view=day&day=2026-11-16');
        expect(view.querySelector('[data-action="dim-cal-new"]')).toBeNull();
    });

    it('shows a view=day address on a desktop as the month with that day\'s drawer', async() => {
        await mount(props(publicAnswer([fair()]), {view: 'day', day: '2026-11-10'}));
        await measure(900);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).toBeNull();
        const drawer = document.querySelector('[data-region="dim-cal-day"]') as HTMLElement;
        expect([drawer.dataset.day, drawer.classList.contains('dim-cal__drawer--drawer')]).toEqual(['2026-11-10', true]);
        expect(urls()).toEqual([`${EVENTS}/2026-11-01/2026-12-05`, `${COURSES}/2026-11-01/2026-12-05`]);
        expect(window.location.search).toBe('?month=2026-11&day=2026-11-10');
    });
});

describe('the first paint, guessed by a media query (R1d)', () => {
    /** The callbacks the page's ResizeObservers registered, the latest last. */
    let observers: Array<(entries: unknown[]) => void> = [];
    /** The queries asked of matchMedia. */
    let asked: string[] = [];

    /**
     * Stand in for matchMedia, answering every query with one verdict.
     *
     * @param {boolean} matches The verdict.
     * @returns {void}
     */
    const media = (matches: boolean): void => {
        asked = [];
        (window as unknown as {matchMedia: unknown}).matchMedia = (query: string) => {
            asked.push(query);
            return {matches: matches, media: query, addEventListener: () => undefined, removeEventListener: () => undefined};
        };
    };

    beforeEach(() => {
        observers = [];
        (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
            /**
             * Keep the callback.
             *
             * @param {Function} callback The observer's callback.
             */
            constructor(callback: (entries: unknown[]) => void) {
                observers.push(callback);
            }

            /**
             * Nothing to note.
             *
             * @returns {void}
             */
            observe(): void {
                return;
            }

            /**
             * Nothing to release.
             *
             * @returns {void}
             */
            disconnect(): void {
                return;
            }
        };
    });

    afterEach(() => {
        delete (window as unknown as {ResizeObserver?: unknown}).ResizeObserver;
        delete (window as unknown as {matchMedia?: unknown}).matchMedia;
    });

    /**
     * Render the page without waiting for a grid, which a phone's Day view does not draw.
     *
     * @param {object} given The props.
     * @returns {Promise}
     */
    const draw = async(given: PublicProps): Promise<void> => {
        const page = document.createElement('div');
        page.dataset.region = 'dim-cal';
        const node = document.createElement('div');
        page.append(node);
        document.body.append(page);
        await act(async() => {
            render(<PublicCalendar {...given} />, {container: node});
        });
        await waitFor(() => expect(page.querySelector('[data-region="dim-cal-body"]')).not.toBeNull());
    };

    /**
     * Report a width for the frame, through the latest observer.
     *
     * @param {number} width The frame's width, in px.
     * @returns {Promise}
     */
    const measure = async(width: number): Promise<void> => {
        const frame = document.querySelector('[data-region="dim-cal-body"]') as Element;
        await act(async() => {
            observers[observers.length - 1]([{target: frame, contentRect: {width: width, height: 600}}]);
        });
    };

    /**
     * The view switch's choices.
     *
     * @returns {Array} Their names.
     */
    const choices = (): string[] => Array.from(document.querySelectorAll('[data-region="dim-cal-views"] button'))
        .map((button) => button.textContent || '');

    /**
     * The range requests made.
     *
     * @returns {Array} Their [from, to].
     */
    const asks = (): string[][] => urls().map((url) => [windowOf(url).from, windowOf(url).to]);

    it('draws the phone layout and offers Day before any observer entry when the query matches', async() => {
        media(true);
        await draw(props(publicAnswer([fair()])));
        expect(asked).toEqual(['(max-width: 591px)']);
        expect(choices()).toEqual(['Month', 'Week', 'Day']);
        expect(document.querySelector('.dim-cal__grid--phone')).not.toBeNull();
    });

    it('draws a view=day address as the Day view at once, with no request before the first measure', async() => {
        media(true);
        await draw(props(publicAnswer([fair()]), {view: 'day', day: '2026-11-10'}));
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).not.toBeNull();
        expect(document.querySelector('[data-region="dim-cal-day"]')).toBeNull();
        expect(asks()).toEqual([]);
        await measure(400);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).not.toBeNull();
        expect(asks()).toEqual([]);
    });

    it('lets a desktop-width first entry replace the guess', async() => {
        media(true);
        await draw(props(publicAnswer([fair()]), {view: 'day', day: '2026-11-10'}));
        await measure(900);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).toBeNull();
        expect(choices()).toEqual(['Month', 'Week']);
        expect((document.querySelector('[data-region="dim-cal-day"]') as HTMLElement).dataset.day).toBe('2026-11-10');
    });

    it('keeps today\'s behaviour when the query does not match', async() => {
        media(false);
        await draw(props(publicAnswer([fair()]), {view: 'day', day: '2026-11-10'}));
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).toBeNull();
        expect((document.querySelector('[data-region="dim-cal-day"]') as HTMLElement).dataset.day).toBe('2026-11-10');
        expect(choices()).toEqual(['Month', 'Week']);
        await measure(400);
        expect(document.querySelector('[data-region="dim-cal-dayview"]')).not.toBeNull();
    });
});
