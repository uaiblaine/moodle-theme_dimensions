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
 * Tests for the hour column's scroll box re-placing itself when the grid hands the drawer its height (PROPOSAL.md
 * section 30.7): until the reader takes the box, and once more for each new opening.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fireEvent, render} from '@testing-library/react';
import HourScroll from '../../src/calendar/HourScroll';

describe('the hour box position when its height changes (R1c)', () => {
    let viewport = 960;
    let top = 0;
    const saved = ['scrollHeight', 'clientHeight', 'scrollTop']
        .map((name) => [name, Object.getOwnPropertyDescriptor(HTMLElement.prototype, name)]);

    beforeEach(() => {
        viewport = 960;
        top = 0;
        Object.defineProperty(HTMLElement.prototype, 'scrollHeight', {configurable: true, get: () => 960});
        Object.defineProperty(HTMLElement.prototype, 'clientHeight', {configurable: true, get: () => viewport});
        Object.defineProperty(HTMLElement.prototype, 'scrollTop', {configurable: true,
            get: () => top,
            set(value: number) {
                // An uncapped box has no overflow: the browser clamps to what it can scroll.
                top = Math.max(0, Math.min(value, 960 - viewport));
            }});
    });

    afterEach(() => {
        saved.forEach(([name, descriptor]) => {
            if (descriptor) {
                Object.defineProperty(HTMLElement.prototype, name as string, descriptor as PropertyDescriptor);
            } else {
                delete (HTMLElement.prototype as unknown as Record<string, unknown>)[name as string];
            }
        });
    });

    const box = (opening: string, height: number, busy = false) => (
        <HourScroll
            band={{from: 8, to: 18}}
            busy={busy}
            opening={opening}
            height={height}
            label="Hours"
            focusable={!busy}
            role={busy ? undefined : 'group'}
            variant="day"
        >
            <div></div>
        </HourScroll>
    );

    it('centres an empty day opened before the height arrives once it arrives', () => {
        const view = render(box('2027-02-10:empty', 0));
        expect(top).toBe(0);
        viewport = 499;
        view.rerender(box('2027-02-10:empty', 622));
        // Centred: the band's middle (13h at 40px) minus half the box.
        expect(top).toBe(13 * 40 - 499 / 2);
    });

    it('stops re-placing at the reader\'s first wheel, touch or key', () => {
        ['wheel', 'touchStart', 'keyDown'].forEach((kind) => {
            viewport = 960;
            top = 0;
            const view = render(box(`2027-02-10:${kind}`, 0));
            const scroll = view.container.querySelector('[data-region="dim-cal-hourscroll"]') as HTMLElement;
            viewport = 499;
            view.rerender(box(`2027-02-10:${kind}`, 622));
            expect(top).toBe(270.5);
            (fireEvent as unknown as Record<string, (element: Element) => void>)[kind](scroll);
            top = 100;
            viewport = 400;
            view.rerender(box(`2027-02-10:${kind}`, 700));
            expect(top).toBe(100);
            view.unmount();
        });
    });

    it('re-places again for a new opening', () => {
        const view = render(box('2027-02-10:empty', 622));
        const scroll = view.container.querySelector('[data-region="dim-cal-hourscroll"]') as HTMLElement;
        viewport = 499;
        view.rerender(box('2027-02-10:empty', 600));
        fireEvent.wheel(scroll);
        top = 100;
        view.rerender(box('2027-02-10:empty', 650));
        expect(top).toBe(100);
        view.rerender(box('2027-02-11:empty', 650));
        expect(top).toBe(270.5);
        viewport = 450;
        view.rerender(box('2027-02-11:empty', 700));
        expect(top).toBe(13 * 40 - 225);
    });

    it('keeps a busy day at its first hour and the fades follow the re-placing', () => {
        const view = render(box('2027-02-10:busy', 0, true));
        viewport = 499;
        view.rerender(box('2027-02-10:busy', 622, true));
        expect(top).toBe(320);
        expect(view.container.querySelector('.dim-cal__hourfade--up')).not.toBeNull();
        expect(view.container.querySelector('.dim-cal__hourfade--down')).not.toBeNull();
    });
});
