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
 * Tests for the listing's address: read as the server's criteria reads it, written as its links spell it.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    buildUrl, catOf, dropCriterion, favOf, filterArgs, groupOf, narrows, orderOf, organise, organisationOf, pageSizeOf,
    periodOf, readUrl, rootOnly, sortOf, syncFold, withPanel,
} from '../../src/listing/address';
import type {ListingState} from '../../src/listing/address';

/**
 * A state at rest on category 7, with the given fields changed.
 *
 * @param {object} changes The fields to change.
 * @returns {object}
 */
const stateOf = (changes: Partial<ListingState> = {}): ListingState => ({
    categoryid: 7, page: 0, query: '', chip: '', fav: false, filters: {}, sort: '', psort: '', defaultsort: '',
    remembered: false,
    datefrom: '', dateto: '', cat: 0, view: 'cards', viewchosen: false, pagesize: 0, ...changes,
});

/**
 * Put an address in the bar.
 *
 * @param {string} search The query string, with its question mark.
 * @returns {void}
 */
const at = (search: string): void => window.history.replaceState(null, '', '/course/index.php' + search);

/**
 * The query string a state is written to.
 *
 * @param {object} state The state.
 * @returns {string}
 */
const searchOf = (state: ListingState): string => buildUrl(state).search;

beforeEach(() => {
    at('?categoryid=7');
    document.body.innerHTML = '';
});

describe('theme_dimensions/listing/address', () => {
    it('writes the organisation only where it is not the category\'s default, and always for a remembered state', () => {
        expect(searchOf(stateOf({sort: 'name'}))).toBe('?categoryid=7&sort=name');
        expect(searchOf(stateOf({sort: 'name', defaultsort: 'name'}))).toBe('?categoryid=7');
        // The grouped organisation is named on a category whose default is flat, or the bare address sends it back.
        expect(searchOf(stateOf({sort: 'category', defaultsort: 'name'}))).toBe('?categoryid=7&sort=category');
        expect(searchOf(stateOf({remembered: true}))).toBe('?categoryid=7&sort=category');
        expect(searchOf(stateOf({remembered: true, sort: 'recent', defaultsort: 'recent'}))).toBe('?categoryid=7&sort=recent');
    });

    it('writes the view only when it was chosen, and never an appended page beyond the state\'s', () => {
        expect(searchOf(stateOf({view: 'list'}))).toBe('?categoryid=7');
        expect(searchOf(stateOf({view: 'list', viewchosen: true}))).toBe('?categoryid=7&view=list');
        expect(searchOf(stateOf({page: 2}))).toBe('?categoryid=7&page=2');
    });

    it('writes each bound of the period on its own, the fields, and the tree\'s category only on a flat order', () => {
        expect(searchOf(stateOf({datefrom: '2026-03-01'}))).toBe('?categoryid=7&from=2026-03-01');
        expect(searchOf(stateOf({dateto: '2026-03-01'}))).toBe('?categoryid=7&to=2026-03-01');
        expect(searchOf(stateOf({filters: {12: 3, 13: 0}}))).toBe('?categoryid=7&cf_12=3');
        expect(searchOf(stateOf({cat: 9}))).toBe('?categoryid=7');
        expect(searchOf(stateOf({cat: 9, sort: 'name'}))).toBe('?categoryid=7&sort=name&cat=9');
        expect(searchOf(stateOf({query: 'rescue', chip: 'open', fav: true}))).toBe('?categoryid=7&q=rescue&chip=open&fav=1');
    });

    it('keeps the page size and the open panel, and drops the panel into a flat order', () => {
        at('?categoryid=7&perpage=6&open=9&psort=name&cf_4=1&page=3');
        expect(searchOf(stateOf({psort: 'name'}))).toBe('?categoryid=7&perpage=6&open=9&psort=name');
        expect(searchOf(stateOf({sort: 'recent'}))).toBe('?categoryid=7&perpage=6&sort=recent');
    });

    it('writes the grouped order from the state, with a panel open or not, and never the grouping by name', () => {
        // The state is the one holder of the grouped order the store writes; the newest first is named by nothing.
        expect(searchOf(stateOf({psort: 'name'}))).toBe('?categoryid=7&psort=name');
        at('?categoryid=7&psort=name');
        expect(searchOf(stateOf())).toBe('?categoryid=7');
        // What the toolbar's form posts without JavaScript is written back in the older spelling.
        at('?categoryid=7&group=none&sort=name');
        expect(searchOf(stateOf({sort: 'name'}))).toBe('?categoryid=7&sort=name');
        const remembered = stateOf({sort: 'category', psort: 'name', remembered: true});
        expect(searchOf(remembered)).toBe('?categoryid=7&sort=category&psort=name');
    });

    it('reads both spellings of the organisation as the server does (criteria::organisation())', () => {
        const read = (search: string, defaultsort = ''): object => organisationOf(new URLSearchParams(search), defaultsort);
        expect(read('')).toEqual({sort: '', psort: ''});
        expect(read('', 'name')).toEqual({sort: 'name', psort: ''});
        expect(read('sort=category')).toEqual({sort: 'category', psort: ''});
        expect(read('open=9&psort=name')).toEqual({sort: '', psort: 'name'});
        expect(read('psort=name')).toEqual({sort: '', psort: 'name'});
        expect(read('sort=recent&psort=name')).toEqual({sort: 'recent', psort: ''});
        expect(read('group=category&sort=name')).toEqual({sort: 'category', psort: 'name'});
        expect(read('group=category&sort=recent&psort=name')).toEqual({sort: 'category', psort: ''});
        expect(read('group=category&psort=name')).toEqual({sort: 'category', psort: 'name'});
        expect(read('group=none&sort=recent')).toEqual({sort: 'recent', psort: ''});
        expect(read('group=none&psort=name')).toEqual({sort: 'name', psort: ''});
        expect(read('group=none&sort=category')).toEqual({sort: 'recent', psort: ''});
        expect(read('group=bogus&sort=name')).toEqual({sort: 'name', psort: ''});
    });

    it('keeps each choice of Organise as the other one left it', () => {
        const state = stateOf({sort: 'name'});
        expect([groupOf(state), orderOf(state)]).toEqual(['none', 'name']);
        organise(state, 'category', orderOf(state));
        expect([state.sort, state.psort, groupOf(state), orderOf(state)]).toEqual(['category', 'name', 'category', 'name']);
        organise(state, groupOf(state), 'recent');
        expect([state.sort, state.psort]).toEqual(['category', '']);
        // Within the grouping the spelling stays: a reorder names no organisation the address did not.
        const bare = stateOf();
        organise(bare, 'category', 'name');
        expect([bare.sort, bare.psort]).toEqual(['', 'name']);
        organise(state, 'none', orderOf(state));
        expect([state.sort, state.psort]).toEqual(['recent', '']);
    });

    it('reads an address as the server reads it', () => {
        at('?categoryid=7&q=fire&chip=favourites&from=2026-05-02&to=2026-05-01&cf_3=2&cf_4=x&page=4&view=list&perpage=12');
        const state = stateOf();
        readUrl(state);
        expect(state).toMatchObject({
            query: 'fire', chip: '', fav: true, datefrom: '2026-05-01', dateto: '2026-05-02', filters: {3: 2}, page: 0,
            view: 'list', viewchosen: true, pagesize: 12, cat: 0,
        });
        // An address naming no view keeps the view in force.
        at('?categoryid=7');
        readUrl(state);
        expect(state.view).toBe('list');
    });

    it('reads the root\'s term and nothing else', () => {
        at('?q=fire&chip=open&from=2026-05-01&sort=name&cat=3&perpage=6');
        const state = stateOf({categoryid: 0});
        readUrl(state);
        expect(state).toMatchObject({query: 'fire', chip: '', datefrom: '', sort: '', cat: 0, pagesize: 0});
        const category = stateOf({chip: 'open'});
        rootOnly(category);
        expect(category.chip).toBe('open');
    });

    it('resolves the organisation, the tree, the page size, the favourites and the period', () => {
        expect(sortOf(new URLSearchParams('sort=bogus'), 'name')).toBe('name');
        expect(sortOf(new URLSearchParams('sort=category'), 'name')).toBe('category');
        expect(sortOf(new URLSearchParams(''), '')).toBe('');
        expect(catOf(new URLSearchParams('cat=5'), '')).toBe(0);
        expect(catOf(new URLSearchParams('cat=5'), 'name')).toBe(5);
        expect(catOf(new URLSearchParams('cat=-5'), 'name')).toBe(0);
        expect(pageSizeOf(new URLSearchParams('perpage=1.5'))).toBe(0);
        expect(pageSizeOf(new URLSearchParams('perpage=24'))).toBe(24);
        expect(favOf(new URLSearchParams('fav=1'))).toBe(true);
        expect(favOf(new URLSearchParams('fav=0'))).toBe(false);
        expect(periodOf(new URLSearchParams('from=2026-02-30&to=2026-03-01'))).toEqual({from: '', to: '2026-03-01'});
    });

    it('says whether a state narrows, and drops one criterion as a chip does', () => {
        expect(narrows(stateOf())).toBe(false);
        expect(narrows(stateOf({cat: 4}))).toBe(false);
        expect(narrows(stateOf({dateto: '2026-01-01'}))).toBe(true);
        const state = stateOf({query: 'a', datefrom: '2026-01-01', dateto: '2026-02-01', filters: {3: 1}, page: 2});
        expect(dropCriterion(state, 'period')).toBe(true);
        expect(state).toMatchObject({datefrom: '', dateto: '', page: 0});
        expect(dropCriterion(state, 'cf_3')).toBe(true);
        expect(filterArgs(state)).toEqual([]);
        expect(dropCriterion(state, 'sort')).toBe(false);
        expect(filterArgs(stateOf({filters: {5: 2}}))).toEqual([{fieldid: 5, value: 2}]);
    });

    it('carries the address bar\'s open panel into an address the server built, and drops one it does not have', () => {
        at('?categoryid=7&open=9');
        expect(withPanel('/course/index.php?categoryid=7&q=a&psort=name')).toBe('http://localhost/course/index.php?categoryid=7&q=a&open=9');
    });

    it('rewrites the fold form\'s criteria from the address and leaves its identity field alone', () => {
        document.body.innerHTML = '<form data-region="dim-hero-fold"><input type="hidden" name="categoryid" value="7">'
            + '<input type="hidden" name="q" value="old"><input type="hidden" name="cf_2" value="1"><button>Fold</button></form>';
        at('?categoryid=7&q=new&open=9&view=list');
        syncFold();
        const fields = Array.from(document.querySelectorAll<HTMLInputElement>('input')).map((input) => input.name + '=' + input.value);
        expect(fields).toEqual(['categoryid=7', 'q=new', 'open=9']);
        expect(document.querySelector<HTMLInputElement>('input[name="open"]')?.dataset.region).toBe('dim-open-field');
    });
});
