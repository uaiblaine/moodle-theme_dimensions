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
 * Tests for the visitor's shell: its one service without a session, its posters' links and its panels' questions.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {AREAS} from '../../src/listing/events';
import {start} from '../../src/listing/visitor';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));

const fetched = fetchOne as jest.Mock;

/**
 * A visitor's flat page with two posters, a public area and a merely listed one, and a tree.
 *
 * @param {string} search The address's query string.
 * @param {string} payload The listing's payload, as the server writes it.
 * @returns {void}
 */
const page = (search: string, payload = '{"page":0}'): void => {
    window.history.replaceState(null, '', '/theme/dimensions/category.php' + search);
    document.body.className = '';
    document.body.innerHTML = '<section data-region="dim-vitrine" data-vitrine=\'' + payload + '\'>'
        + '<form data-region="dim-vitrine-toolbar" id="t"><input type="hidden" name="id" value="7"></form>'
        + '<p data-region="dim-toolbar-notice" hidden></p>'
        + '<nav data-region="dim-vitrine-tree"><a data-region="dim-tree-link" data-categoryid="9" href="#">Listed</a></nav>'
        + '<div data-region="dim-areas"><ol>'
        + '<li><article data-region="dim-area" data-categoryid="8" data-titleurl="/c/8">'
        + '<a data-region="dim-poster-control" href="/old">Public</a></article></li>'
        + '<li><article data-region="dim-area" data-categoryid="9" data-titleurl="">'
        + '<a data-region="dim-poster-control" href="/old">Listed</a></article></li>'
        + '<li data-region="dim-panel" hidden></li></ol></div>'
        + '<ul data-region="dim-vitrine-grid"></ul></section>';
};

/**
 * The address a poster links to.
 *
 * @param {number} id The area.
 * @returns {string}
 */
const linkOf = (id: number): string => document.querySelector<HTMLAnchorElement>(
    `[data-region="dim-area"][data-categoryid="${id}"] [data-region="dim-poster-control"]`)?.href || '';

/**
 * Ask the posters' component's question for an area, as the component does.
 *
 * @param {string} type One of AREAS' events.
 * @param {number} id The area.
 * @returns {object} The event's detail, answered.
 */
const askAbout = (type: string, id: number): Record<string, unknown> => {
    const poster = document.querySelector(`[data-region="dim-area"][data-categoryid="${id}"]`);
    const detail: Record<string, unknown> = {poster: poster, psort: 'name'};
    document.querySelector('[data-region="dim-areas"]')?.dispatchEvent(new CustomEvent(type, {detail: detail}));
    return detail;
};

beforeEach(() => {
    fetched.mockReset();
    fetched.mockReturnValue(new Promise(() => null));
});

describe('theme_dimensions/listing/visitor', () => {
    it('marks the page as the visitor\'s and points every poster at its panel under the address on screen', () => {
        page('?id=7&sort=name&q=fire', '{"page":0,"query":"fire"}');
        start(7);
        expect(document.body.classList.contains('dim-public-js')).toBe(true);
        expect(document.body.classList.contains('dim-js')).toBe(false);
        expect(linkOf(8)).toBe('http://localhost/theme/dimensions/category.php?id=7&q=fire&sort=name&open=8#dim-panel');
        expect(fetched).not.toHaveBeenCalled();
    });

    it('asks its own service with no session, no cachekey and the page\'s language, and links the posters again', () => {
        page('?id=7&sort=name');
        start(7);
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        expect(fetched).toHaveBeenCalledTimes(1);
        const [request, options] = fetched.mock.calls[0];
        expect(request.methodname).toBe('theme_dimensions_get_public_category_rows');
        expect(request.args).toMatchObject({categoryid: 7, page: 0, cat: 9, sort: 'name', lang: 'en'});
        expect(request.args).not.toHaveProperty('chip');
        expect(request.args).not.toHaveProperty('fav');
        expect(options).toEqual({loginrequired: false});
        expect(linkOf(8)).toContain('cat=9&open=8');
    });

    it('drops the state chip and the favourites an address names, and asks the root for its term alone', () => {
        page('?q=fire&chip=open&fav=1&cat=4', '{"page":0,"query":"fire"}');
        start(0);
        expect(linkOf(8)).toBe('http://localhost/theme/dimensions/category.php?q=fire&open=8#dim-panel');
        const detail = askAbout(AREAS.question, 8);
        expect(JSON.parse(String(detail.question))).toEqual({
            categoryid: 8, page: 0, perpage: 8, sort: 'name', query: 'fire', withcategories: true, lang: 'en',
        });
    });

    it('asks a public area by its id and a merely listed one as a flat slice of this page', () => {
        page('?id=7&sort=name&cf_3=2');
        start(7);
        expect(JSON.parse(String(askAbout(AREAS.question, 8).question))).toMatchObject({
            categoryid: 8, page: 0, perpage: 8, withcategories: true, filters: [{fieldid: 3, value: 2}],
        });
        const slice = JSON.parse(String(askAbout(AREAS.question, 9).question));
        expect(slice).toMatchObject({categoryid: 7, slice: 9, sort: 'name'});
        expect(slice).not.toHaveProperty('page');
        expect(slice).not.toHaveProperty('withcategories');
        askAbout(AREAS.ask, 9);
        expect(fetched.mock.calls[0][1]).toEqual({loginrequired: false});
    });

    it('tells a failure in the page\'s own sentence, never the service\'s, and takes a panel\'s failure over', async() => {
        mockString('category_publicfallback', 'theme_dimensions', 'Loading the page instead.');
        page('?id=7&sort=name');
        start(7);
        fetched.mockReturnValue(Promise.reject({message: 'Service words', errorcode: 'x'}));
        (document.querySelector('[data-region="dim-tree-link"]') as HTMLElement).click();
        for (let round = 0; round < 3; round++) {
            await new Promise((resolve) => setTimeout(resolve, 0));
        }
        const notice = document.querySelector<HTMLElement>('[data-region="dim-toolbar-notice"]') as HTMLElement;
        expect(notice.hidden).toBe(false);
        expect(notice.textContent).toBe('Loading the page instead.');
        expect(askAbout(AREAS.unanswered, 9).taken).toBe(true);
    });
});
