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
 * Tests for the posters' result layer, the rail's reading of its scroll and the marks on a panel's titles.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {markTitles} from '../../src/areas/mark';
import {
    fitReveal, loadedAllEmpty, paintAllEmpty, paintPosters, paintReveal, paintSearchAll, setHidden,
} from '../../src/areas/posters';
import {paintRail, railFrom} from '../../src/areas/rail';
import {drawPage, posterEl} from './page';

describe('theme_dimensions/areas/posters', () => {
    it('sends a search cleared in place back to rest on every poster the answer does not name', () => {
        const {areas} = drawPage({filtered: true, posters: [
            {id: 8, name: 'A', count: 2, label: '2 results'},
            {id: 9, name: 'B', count: 0, label: '0 results', hidden: true},
        ]});
        paintPosters(areas, [], false, 0, new Map());
        const pill = posterEl(9).querySelector('[data-region="dim-poster-pill"]') as HTMLElement;
        expect(pill.hidden).toBe(true);
        expect(posterEl(9).dataset.resultlabel).toBe('');
        expect((posterEl(9).parentElement as HTMLElement).hidden).toBe(false);
        expect(posterEl(8).querySelector('[data-region="dim-poster-control"]')?.hasAttribute('aria-describedby')).toBe(false);
    });

    it('records whether each area opens, and hides the reveal when nothing behind it is left', () => {
        const {areas} = drawPage({filtered: true, more: [{id: 20, name: 'Late', count: 0}]});
        const record = new Map<number, boolean>();
        paintPosters(areas, [{id: 20, count: 0, expandable: true}], true, 0, record);
        expect(record.get(20)).toBe(true);
        fitReveal(areas);
        const more = areas.querySelector('[data-region="dim-areas-more"]') as HTMLDetailsElement;
        expect(more.hidden).toBe(true);
        // The open poster behind the reveal keeps it.
        paintPosters(areas, [{id: 20, count: 0, expandable: true}], true, 20, record);
        fitReveal(areas);
        expect(more.hidden).toBe(false);
        paintReveal(areas, {filtered: true, moreresults: 1, morelabel: 'Show 1 more area'});
        expect(more.open).toBe(true);
        expect(more.querySelector('summary')?.textContent).toBe('Show 1 more area');
    });

    it('shows "nothing anywhere" only with no panel open, and its second way out only with an address', () => {
        const {areas} = drawPage({filtered: true});
        const box = areas.querySelector('[data-region="dim-areas-empty"]') as HTMLElement;
        paintAllEmpty(areas, true, 8);
        expect(box.hidden).toBe(true);
        paintAllEmpty(areas, true, 0);
        expect(box.hidden).toBe(false);
        const link = areas.querySelector('[data-region="dim-areas-searchall"]') as HTMLAnchorElement;
        paintSearchAll(areas, 'https://example.com/course/index.php?q=x');
        expect(link.hidden).toBe(false);
        expect(link.getAttribute('href')).toBe('https://example.com/course/index.php?q=x');
        paintSearchAll(areas, '');
        expect(link.hidden).toBe(true);
    });

    it('reads "nothing anywhere" off a render drawn with a zeroed area open', () => {
        const {areas} = drawPage({filtered: true, open: 8, posters: [{id: 8, name: 'A', count: 0}, {id: 9, name: 'B', count: 0}]});
        // The root has no course of its own.
        expect(loadedAllEmpty(areas, true)).toBe(true);
        expect(loadedAllEmpty(areas, false)).toBe(false);
        setHidden(areas.querySelector('[data-region="dim-areas-empty"]') as HTMLElement, false);
        expect(loadedAllEmpty(areas, false)).toBe(true);
    });

    it('reads the rail\'s arrows and counter from four numbers and writes only what changed', () => {
        expect(railFrom(0, 400, 200, 4)).toEqual({prevoff: true, nextoff: false, at: 1, count: 4});
        expect(railFrom(399, 400, 200, 4)).toEqual({prevoff: false, nextoff: true, at: 3, count: 4});
        expect(railFrom(0, 0, 0, 0)).toEqual({prevoff: true, nextoff: true, at: 0, count: 0});
        document.body.innerHTML = '<li data-region="dim-panel"><button data-action="dim-panel-prev" class="dim-panel__arrow--off"></button>'
            + '<button data-action="dim-panel-next"></button><span data-region="dim-panel-counter">1 / 4</span></li>';
        const panel = document.body.firstElementChild as HTMLElement;
        paintRail(panel, railFrom(200, 400, 200, 4));
        expect(panel.querySelector('[data-action="dim-panel-prev"]')?.className).toBe('');
        expect(panel.querySelector('[data-region="dim-panel-counter"]')?.textContent).toBe('2 / 4');
    });

    it('marks the term on the titles\' text, folded, and unmarks first', () => {
        document.body.innerHTML = '<div><h3 data-region="dim-card-title"><a href="#">Resgate e Salvamento</a></h3></div>';
        const root = document.body.firstElementChild as HTMLElement;
        markTitles(root, 'salvamento');
        expect(root.querySelector('mark.dim-mark')?.textContent).toBe('Salvamento');
        markTitles(root, 'RESGATE');
        expect(Array.from(root.querySelectorAll('mark')).map((mark) => mark.textContent)).toEqual(['Resgate']);
        markTitles(root, 'r');
        expect(root.querySelector('mark')).toBeNull();
        expect(root.textContent).toBe('Resgate e Salvamento');
    });
});
