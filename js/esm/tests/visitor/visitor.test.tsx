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
 * The three components on the visitor's own markup, mounted as the visitor's module mounts them (R4a).
 *
 * The page is ./page's: the anonymous shell's toolbar and areas as a public category page draws them. Each
 * component must draw it and say so - the listing holds a pending until it does - and a line mounted again
 * after core's react_autoinit unmounted it (a move of the line, or of an ancestor) must be drawn again
 * rather than left empty.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {act, render, waitFor} from '@testing-library/react';
import {createRoot} from 'react-dom/client';
import Areas, {EVENTS as AREAS} from '../../src/areas/Areas';
import DateRange, {EVENTS as CALENDAR} from '../../src/daterange/DateRange';
import FilterLine, {DRAWN, EVENTS as LINE} from '../../src/filterline/FilterLine';
import * as Measure from '../../src/filterline/measure';
import {VISITOR_FORM, drawVisitorPage} from './page';

// The browser imports the preview on first use; Jest's runtime cannot run a native import().
jest.mock('../../src/filterline/lazy', () => ({
    importPreview: () => Promise.resolve(jest.requireActual('../../src/filterline/preview')),
}));

// jsdom lays nothing out: a test about the steps gives the widths.
jest.mock('../../src/filterline/measure', () => {
    const actual = jest.requireActual('../../src/filterline/measure');
    return Object.assign({}, actual, {stepWidths: jest.fn(actual.stepWidths)});
});

/** The ResizeObserver callbacks the page has registered, in the order the mounts made them. */
let observers: Array<(entries: Array<{contentRect: {width: number}}>) => void> = [];

/** The calendar's strings. */
const STRINGS = [
    'category_daterange', 'category_datefrom', 'category_dateto', 'category_dateclearfrom', 'category_dateclearto',
    'category_dateprevmonth', 'category_datenextmonth', 'category_dateclear', 'category_datedone',
    'category_datechoosefrom', 'category_datechooseto',
];

/** The template renderer the panel asks for, rendering nothing a test reads. */
const templates = {
    renderForPromise: jest.fn(() => Promise.resolve({html: '<section data-region="dim-panel-section"></section>', js: ''})),
    runTemplateJS: jest.fn(),
    replaceNodeContents: jest.fn((element: Element, html: string) => {
        element.innerHTML = html;
    }),
    appendNodeContents: jest.fn((element: Element, html: string) => {
        element.insertAdjacentHTML('beforeend', html);
    }),
};

beforeEach(() => {
    jest.useFakeTimers();
    mockAmdModule('core/templates', templates);
    STRINGS.forEach((key) => mockString(key, 'theme_dimensions', key));
    window.history.replaceState(null, '', '/theme_dimensions/category/8');
    window.matchMedia = jest.fn().mockImplementation((query: string) => ({
        media: query,
        matches: false,
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
    }));
    observers = [];
    // A value queued for a call a failed test never made must not reach the next test.
    (Measure.stepWidths as jest.Mock).mockReset().mockImplementation(jest.requireActual('../../src/filterline/measure').stepWidths);
    (window as unknown as {ResizeObserver: unknown}).ResizeObserver = class {
        /**
         * Keep the callback for the test to call.
         *
         * @param {Function} callback The observer's callback.
         */
        constructor(callback: (entries: Array<{contentRect: {width: number}}>) => void) {
            observers.push(callback);
        }

        /**
         * Nothing to observe in jsdom.
         *
         * @returns {void}
         */
        observe(): void {
            return undefined;
        }

        /**
         * Nothing to stop.
         *
         * @returns {void}
         */
        disconnect(): void {
            return undefined;
        }
    };
});

afterEach(() => {
    jest.useRealTimers();
    document.body.replaceChildren();
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

describe('the components on the visitor\'s markup', () => {
    it('draws the visitor\'s filter line, which has no scope and no star, and says so', async() => {
        const {line} = drawVisitorPage();
        const mounted = jest.fn();
        const failed = jest.fn();
        line.addEventListener(LINE.mounted, mounted);
        line.addEventListener(LINE.failed, failed);
        await act(async() => {
            render(<FilterLine form={VISITOR_FORM} />, {container: line});
        });

        expect(mounted).toHaveBeenCalledTimes(1);
        expect(failed).not.toHaveBeenCalled();
        expect(line.hasAttribute(DRAWN)).toBe(true);
        expect(line.querySelector('input[type="search"]')?.getAttribute('name')).toBe('q');
        expect(line.querySelector('[data-region="dim-line-star"]')).toBeNull();
        expect(line.querySelector('[data-role="scope"]')).toBeNull();
        // The Filters panel the server drew is adopted whole, the period's region in it.
        expect(line.querySelector('[data-region="dim-filters-panel"] [data-region="dim-daterange"]')).not.toBeNull();
    });

    it('draws the line again when it is mounted again after an unmount emptied it', async() => {
        const {line} = drawVisitorPage();
        const mounted = jest.fn();
        line.addEventListener(LINE.mounted, mounted);
        // Core's mount: a root over the line node itself.
        const first = createRoot(line);
        await act(async() => {
            first.render(<FilterLine form={VISITOR_FORM} />);
        });
        const fields = line.querySelectorAll('[data-region="dim-vitrine-group"][data-name^="cf_"]').length;
        expect(fields).toBeGreaterThan(0);

        // What core's react_autoinit does to a mounted node that leaves the document and comes back.
        await act(async() => {
            first.unmount();
        });
        expect(line.children).toHaveLength(0);
        const second = createRoot(line);
        await act(async() => {
            second.render(<FilterLine form={VISITOR_FORM} />);
        });

        expect(mounted).toHaveBeenCalledTimes(2);
        expect(line.hasAttribute(DRAWN)).toBe(true);
        expect(line.querySelector('input[type="search"]')).not.toBeNull();
        expect(line.querySelectorAll('[data-region="dim-vitrine-group"][data-name^="cf_"]')).toHaveLength(fields);
        expect(line.querySelector('[data-region="dim-filters-panel"] [data-region="dim-daterange"]')).not.toBeNull();
        await act(async() => {
            second.unmount();
        });
    });

    it('folds a line mounted again at its new observer\'s first entry', async() => {
        const {line} = drawVisitorPage();
        const first = createRoot(line);
        await act(async() => {
            first.render(<FilterLine form={VISITOR_FORM} />);
        });
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 840, 620]);
        await act(async() => {
            observers[0]([{contentRect: {width: 700}}]);
        });
        expect(line.classList.contains('dim-line--s3')).toBe(true);
        expect(line.style.visibility).toBe('');
        await act(async() => {
            first.unmount();
        });

        const second = createRoot(line);
        await act(async() => {
            second.render(<FilterLine form={VISITOR_FORM} />);
        });
        // Drawn from what the unmount kept, hidden and measured only once its own observer reports the line's width.
        expect(observers).toHaveLength(2);
        expect(Measure.stepWidths).toHaveBeenCalledTimes(1);
        expect(line.style.visibility).toBe('hidden');
        (Measure.stepWidths as jest.Mock).mockReturnValueOnce([900, 840, 620, 480]);
        await act(async() => {
            observers[1]([{contentRect: {width: 500}}]);
        });
        expect(Measure.stepWidths).toHaveBeenCalledTimes(2);
        expect(Measure.stepWidths).toHaveBeenLastCalledWith(line, null, 500);
        expect(line.classList.contains('dim-line--s4')).toBe(true);
        expect(line.classList.contains('dim-line--folded')).toBe(false);
        expect(line.style.visibility).toBe('');
        await act(async() => {
            second.unmount();
        });
    });

    it('says it failed, rather than nothing, on markup that is not a line it can draw', async() => {
        const {line} = drawVisitorPage();
        line.querySelector('[data-region="dim-line-suggest"]')?.remove();
        const mounted = jest.fn();
        const failed = jest.fn();
        line.addEventListener(LINE.mounted, mounted);
        line.addEventListener(LINE.failed, failed);
        await act(async() => {
            render(<FilterLine form={VISITOR_FORM} />, {container: line});
        });

        expect(failed).toHaveBeenCalledTimes(1);
        expect(mounted).not.toHaveBeenCalled();
        expect(line.hasAttribute(DRAWN)).toBe(false);
    });

    it('draws the visitor\'s calendar in the period the line adopted', async() => {
        const {line} = drawVisitorPage();
        await act(async() => {
            render(<FilterLine form={VISITOR_FORM} />, {container: line});
        });
        const fieldset = line.querySelector<HTMLElement>('[data-region="dim-date"]') as HTMLElement;
        const region = fieldset.querySelector<HTMLElement>('[data-region="dim-daterange"]') as HTMLElement;
        const node = document.createElement('div');
        region.append(node);
        const mounted = jest.fn();
        region.addEventListener(CALENDAR.mounted, mounted);
        await act(async() => {
            render(<DateRange id={8} firstday={Number(fieldset.dataset.firstday)} today={fieldset.dataset.today || ''} from="" to="" />,
                {container: node});
        });
        await flush();

        await waitFor(() => expect(mounted).toHaveBeenCalledTimes(1));
        expect(region.querySelector('[data-action="dim-daterange-open"][data-bound="from"]')).not.toBeNull();
    });

    it('takes the visitor\'s posters over as links announced as buttons, and asks after the quiet', async() => {
        const {areas} = drawVisitorPage();
        const asked: number[] = [];
        const questions: number[] = [];
        areas.addEventListener(AREAS.ask, (event: Event) => {
            const detail = (event as CustomEvent).detail;
            asked.push(Number(detail.poster.dataset.categoryid));
            detail.answer = new Promise(() => undefined);
        });
        areas.addEventListener(AREAS.question, (event: Event) => {
            const detail = (event as CustomEvent).detail;
            questions.push(Number(detail.poster.dataset.categoryid));
            detail.question = `q${detail.poster.dataset.categoryid}`;
        });
        const mounted = jest.fn();
        areas.addEventListener(AREAS.mounted, mounted);
        const node = document.createElement('div');
        node.hidden = true;
        areas.append(node);
        await act(async() => {
            render(<Areas links={true} quiet={120} autoopen={false} />, {container: node});
        });

        expect(mounted).toHaveBeenCalledTimes(1);
        const posters = Array.from(areas.querySelectorAll<HTMLElement>('[data-region="dim-area"]'));
        expect(posters.length).toBeGreaterThan(0);
        posters.forEach((poster) => {
            const control = poster.querySelector('[data-region="dim-poster-control"]') as HTMLElement;
            expect(control.tagName).toBe('A');
            expect(control.getAttribute('role')).toBe('button');
            expect(control.getAttribute('aria-controls')).toBe('dim-panel');
        });
        const first = posters[0];
        await act(async() => {
            (first.querySelector('[data-region="dim-poster-control"]') as HTMLElement).click();
        });
        await flush();
        expect(asked).toEqual([]);
        await flush(120);
        expect(asked).toEqual([Number(first.dataset.categoryid)]);
        expect(questions).toContain(Number(first.dataset.categoryid));
    });
});
