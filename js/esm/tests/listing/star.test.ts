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
 * Tests for the card's star: one listener, an optimistic flip, one round trip at a time, a refusal put back.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {fetchOne} from '@moodle/lms/core/ajax';
import {arm} from '../../src/listing/star';

jest.mock('@moodle/lms/core/ajax', () => ({fetchOne: jest.fn()}));

const fetched = fetchOne as jest.Mock;

/**
 * A card's star as the template draws it, not a favourite yet.
 *
 * @returns {HTMLElement} The star button.
 */
const star = (): HTMLElement => {
    document.body.innerHTML = '<article data-region="dim-card"><button type="button" data-action="dim-star" data-courseid="7"'
        + ' aria-pressed="false" data-star-add="Add to favourites" data-star-remove="Remove from favourites">'
        + '<i class="fa-regular fa-star"></i><span data-region="dim-star-label">Add to favourites</span></button></article>';
    return document.querySelector('[data-action="dim-star"]') as HTMLElement;
};

/**
 * Let the round trip settle, the bridge's loading of core/notification included.
 *
 * @returns {Promise}
 */
const settle = async(): Promise<void> => {
    for (let round = 0; round < 3; round++) {
        await new Promise((resolve) => setTimeout(resolve, 0));
    }
};

/**
 * The star's three faces.
 *
 * @param {HTMLElement} button The star.
 * @returns {Array}
 */
const faces = (button: HTMLElement): Array<string | boolean | null> => [
    button.getAttribute('aria-pressed'),
    Boolean(button.querySelector('i')?.classList.contains('fa-solid')),
    button.querySelector('[data-region="dim-star-label"]')?.textContent || null,
];

beforeEach(() => {
    fetched.mockReset();
});

describe('theme_dimensions/listing/star', () => {
    it('marks the body and installs one listener however often it is armed', async() => {
        document.body.className = '';
        arm();
        arm();
        expect(document.body.classList.contains('dim-star-js')).toBe(true);
        fetched.mockResolvedValue({favourite: true});
        star().click();
        await settle();
        expect(fetched).toHaveBeenCalledTimes(1);
        expect(fetched.mock.calls[0][0]).toEqual({methodname: 'theme_dimensions_toggle_wishlist', args: {courseid: 7, state: true}});
    });

    it('flips at once, holds one round trip at a time, and paints what the server stored', async() => {
        arm();
        let answer: (value: {favourite: boolean}) => void = () => null;
        fetched.mockImplementation(() => new Promise((resolve) => {
            answer = resolve;
        }));
        const button = star();
        button.click();
        expect(faces(button)).toEqual(['true', true, 'Remove from favourites']);
        button.click();
        expect(fetched).toHaveBeenCalledTimes(1);
        // The server stored something else: what it says wins.
        answer({favourite: false});
        await settle();
        expect(faces(button)).toEqual(['false', false, 'Add to favourites']);
        expect(button.dataset.busy).toBe('0');
    });

    it('puts a refused star back and shows core\'s dialogue', async() => {
        arm();
        const Notification = {exception: jest.fn(() => Promise.resolve())};
        mockAmdModule('core/notification', Notification);
        const error = new Error('refused');
        fetched.mockImplementation(() => Promise.reject(error));
        const button = star();
        button.click();
        await settle();
        expect(faces(button)).toEqual(['false', false, 'Add to favourites']);
        expect(Notification.exception).toHaveBeenCalledWith(error);
        expect(button.dataset.busy).toBe('0');
    });
});
