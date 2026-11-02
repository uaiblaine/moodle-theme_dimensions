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
 * Tests for the public calendar's client, the visitor's shell (PROPOSAL.md section 19.2).
 *
 * Rendered inside a page like calendar_page.mustache's visitor mode; the visitor's service is core/ajax's fetchOne,
 * mocked. What is read: the first paint, the two chips, the one service asked without a login and with the page's
 * language, the latest answer winning, a failure loading the address, and the popover's text and way in.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, fireEvent, render, waitFor} from '@testing-library/react';
import {fetchOne} from '@moodle/lms/core/ajax';
import Config from '@moodle/lms/core/config';
import {redirect} from '@moodle/lms/core/location';
import PublicCalendar, {
    METHOD,
    publicAddress,
    readPublicAddress,
} from '../../src/calendar/PublicCalendar';
import type {PublicAnswer, PublicProps} from '../../src/calendar/PublicCalendar';
import type {Course, Item} from '../../src/calendar/model';
import {STRINGS, answer, at, item} from './fixture';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));
jest.mock('@moodle/lms/core/location', () => ({redirect: jest.fn()}));

const fetched = fetchOne as jest.Mock;
const redirected = redirect as jest.Mock;

/** The page's address without a query. */
const PAGE = 'http://localhost/r.php/theme_dimensions/calendar';

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
    urls: {page: PAGE, home: 'http://localhost/'},
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
 * @returns {Array} [request, options] pairs.
 */
const calls = (): Array<[{methodname: string, args: Record<string, unknown>}, Record<string, unknown> | undefined]> =>
    fetched.mock.calls as Array<[{methodname: string, args: Record<string, unknown>}, Record<string, unknown> | undefined]>;

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
    Config.language = 'pt_br';
    Object.entries(STRINGS).forEach(([key, value]) => mockString(key, 'theme_dimensions', value));
    fetched.mockReset();
    fetched.mockImplementation(({args}: {args: {from: string, to: string}}) =>
        Promise.resolve({...answer([], args.from, args.to), descriptions: []}));
    window.history.replaceState({}, '', '/r.php/theme_dimensions/calendar');
    redirected.mockReset();
});

afterEach(() => {
    document.body.replaceChildren();
});

describe('the first paint', () => {
    it('draws from the embedded answer, asks for nothing, and hides the server\'s layer', async() => {
        const page = await mount(props(publicAnswer([fair()])));
        expect(fetched).not.toHaveBeenCalled();
        expect(page.querySelector<HTMLElement>('[data-region="dim-cal-server"]')?.hidden).toBe(true);
        expect(gridItem(5)).not.toBeNull();
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

describe('the service', () => {
    it('a chip off asks the visitor\'s service without a login, with the page\'s language, and replaces the address', async() => {
        const replace = jest.spyOn(window.history, 'replaceState');
        await mount(props());
        await act(async() => {
            fireEvent.click(document.querySelector('[data-region="dim-cal-types"] [data-key="category"]') as HTMLElement);
        });
        expect(document.querySelector('[data-action="dim-cal-types"]')?.textContent).toBe('Event types1 of 2');
        expect(calls()).toHaveLength(1);
        const [request, options] = calls()[0];
        expect(request.methodname).toBe(METHOD);
        expect(request.args).toEqual({from: '2026-11-01', to: '2026-12-05', keys: ['open'], lang: 'pt_br'});
        expect(options).toEqual({loginrequired: false});
        expect(replace.mock.calls.some(([, , url]) => String(url).includes('keys%5B%5D=open'))).toBe(true);
        replace.mockRestore();
    });

    it('"Clear filters" turns both keys back on in one request, and is gone', async() => {
        await mount(props(publicAnswer(), {keys: ['open']}));
        const clear = document.querySelector<HTMLElement>('[data-action="dim-cal-clear"]') as HTMLElement;
        await act(async() => {
            fireEvent.click(clear);
        });
        expect(calls()).toHaveLength(1);
        expect(calls()[0][0].args.keys).toEqual(['category', 'open']);
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
        expect(calls().map(([request]) => request.args.keys)).toEqual([['category'], ['category']]);
        expect(calls()[1][0].args.from).toBe('2026-11-29');
        expect(push.mock.calls.some(([, , url]) => String(url).includes('month=2026-12'))).toBe(true);
        push.mockRestore();
    });

    it('the latest request wins', async() => {
        let release: (value: PublicAnswer) => void = () => undefined;
        fetched.mockImplementationOnce(() => new Promise((resolve) => {
            release = resolve;
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
        fetched.mockImplementation(() => Promise.resolve(publicAnswer()));
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
