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
 * Tests for the panel's pure half: its place in the grid, its address, its contexts and its words.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    addressWith, announcement, answerContext, columns, ctaUrl, holderHref, lastOfRow, placement, readPanel, sameRow,
    shellContext, suggestions, summaryLabel, summaryOf,
} from '../../src/areas/model';

const BASE = 'https://example.com/course/index.php?categoryid=7';

describe('theme_dimensions/areas/model', () => {
    it('writes the five integers of the row after the open poster at three, two and one column', () => {
        expect(placement(0)).toEqual({'--dim-row-3': '2', '--dim-row-2': '2', '--dim-row-1': '2', '--dim-col-3': '0', '--dim-col-2': '0'});
        expect(placement(4)).toEqual({'--dim-row-3': '3', '--dim-row-2': '4', '--dim-row-1': '6', '--dim-col-3': '1', '--dim-col-2': '0'});
        expect(placement(5)).toEqual({'--dim-row-3': '3', '--dim-row-2': '4', '--dim-row-1': '7', '--dim-col-3': '2', '--dim-col-2': '1'});
    });

    it('counts the tracks from the breakpoints and the view, as the stylesheet does', () => {
        expect(columns(false, false, false)).toBe(3);
        expect(columns(false, false, true)).toBe(2);
        expect(columns(false, true, true)).toBe(1);
        // The list view is one track at every width.
        expect(columns(true, false, false)).toBe(1);
    });

    it('finds the last poster of a row, a short last row included', () => {
        expect(lastOfRow(0, 7, 3)).toBe(2);
        expect(lastOfRow(4, 7, 3)).toBe(5);
        expect(lastOfRow(6, 7, 3)).toBe(6);
        expect(lastOfRow(1, 2, 3)).toBe(1);
        expect(lastOfRow(3, 7, 1)).toBe(3);
        expect(lastOfRow(2, 7, 2)).toBe(3);
        expect(sameRow(0, 2, 3)).toBe(true);
        expect(sameRow(2, 3, 3)).toBe(false);
        expect(sameRow(2, 3, 2)).toBe(true);
        expect(sameRow(-1, 0, 3)).toBe(false);
    });

    it('swaps the call to action\'s sort for the name order and nothing else', () => {
        const href = 'https://example.com/course/index.php?categoryid=8&q=rescue&sort=recent';
        expect(ctaUrl(href, '', BASE)).toBe(href);
        expect(ctaUrl(href, 'name', BASE)).toBe('https://example.com/course/index.php?categoryid=8&q=rescue&sort=name');
        expect(ctaUrl('', 'name', BASE)).toBe('');
    });

    it('builds the shell before the rows from the poster\'s data, composing nothing', () => {
        const shell = {id: 8, name: 'Brigade', titleurl: '', ctaurl: 'https://example.com/x?sort=recent', ctalabel: 'Go to Brigade'};
        const context = shellContext(shell, 'name', BASE, {isskeleton: true});
        expect(context).toMatchObject({id: 8, name: 'Brigade', haslink: false, psortname: true, jsmode: true, isskeleton: true});
        expect(context.cta).toEqual({has: true, url: 'https://example.com/x?sort=name', label: 'Go to Brigade'});
        expect(context.isloading).toBe(false);
    });

    it('takes an answer only for the area it asked about, and merges the page\'s suggestions', () => {
        const offered = [{id: 9, name: 'Traffic', count: '3', countlabel: '3 results', url: ''}];
        expect(() => answerContext({panel: null}, 8, [])).toThrow();
        expect(() => answerContext({panel: {id: 9}}, 8, [])).toThrow();
        expect(answerContext({panel: {id: '8', subtitle: 'x'}, cards: [{id: 1}]}, 8, offered)).toEqual({
            id: '8', subtitle: 'x', cards: [{id: 1}], jsmode: true, suggestions: offered, hassuggestions: true,
        });
    });

    it('suggests the other areas holding a result, and nothing at rest', () => {
        const posters = [
            {id: 8, name: 'A', count: 0, resultcount: '0', resultlabel: '0 results'},
            {id: 9, name: 'B', count: 3, resultcount: '3', resultlabel: '3 results'},
            {id: 10, name: 'C', count: 2, resultcount: '2', resultlabel: '2 results'},
        ];
        expect(suggestions(posters, 10, true)).toEqual([{id: 9, name: 'B', count: '3', countlabel: '3 results', url: ''}]);
        expect(suggestions(posters, 10, false)).toEqual([]);
    });

    it('writes the panel into the address, keeps every other parameter and drops the stale fragments', () => {
        expect(addressWith(BASE + '&q=x#dim-panel', 8, 'name')).toBe(BASE + '&q=x&open=8&psort=name');
        expect(addressWith(BASE + '&open=8&psort=name#dim-area-8', 0, 'name')).toBe(BASE);
        expect(addressWith(BASE + '&open=8#dim-nomatch', 9, '')).toBe(BASE + '&open=9');
        expect(addressWith(BASE + '#elsewhere', 9, '')).toBe(BASE + '&open=9#elsewhere');
        expect(holderHref('/course/index.php?categoryid=7&q=x', BASE, 8, 'name'))
            .toBe('https://example.com/course/index.php?categoryid=7&q=x&open=8&psort=name');
        expect(holderHref('/course/index.php?categoryid=7&open=8&psort=name', BASE, 0, ''))
            .toBe('https://example.com/course/index.php?categoryid=7');
        expect(readPanel(BASE + '&open=8&psort=name')).toEqual({open: 8, psort: 'name'});
        expect(readPanel(BASE + '&open=x&psort=other')).toEqual({open: 0, psort: ''});
    });

    it('announces the rows by their subtitle, the empty state by its body, a zero panel in two sentences', () => {
        expect(announcement({isrows: true, subtitle: '12 courses'}, 'ignored', 'E')).toBe('12 courses');
        expect(announcement({isrows: false}, '  Nothing\n  here ', 'E')).toBe('Nothing here');
        expect(announcement({iszero: true, zero: 'No results here.'}, 'x', 'Results elsewhere.')).toBe('No results here. Results elsewhere.');
    });

    it('picks the shorter sentence while the open area is one of the zeros, and rebuilds the entries', () => {
        const summary = summaryOf(
            [{id: 8, count: 0, linkable: true, url: '/a'}, {id: 9, count: 3}, {id: 10, count: '0', name: 'Server name'}],
            {filtered: true, nomatchlabelall: 'Nothing in 2 areas', nomatchlabelless: 'Nothing in one area'},
            (id) => (id === 8 ? 'Brigade' : null)
        );
        expect(summary.entries).toEqual([{id: 8, name: 'Brigade', url: '/a'}, {id: 10, name: 'Server name', url: ''}]);
        expect(summaryLabel(summary, 8)).toBe('Nothing in one area');
        expect(summaryLabel(summary, 9)).toBe('Nothing in 2 areas');
        expect(summaryOf([{id: 8, count: 0}], {filtered: false}, () => 'x').entries).toEqual([]);
    });
});
