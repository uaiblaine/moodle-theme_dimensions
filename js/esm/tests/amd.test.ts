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
 * Tests for the theme's door to AMD.
 *
 * Core's Jest setup (`.jest/globalSetup.ts`) mocks `@moodle/lms/core/amd` for every test and serves
 * the modules a test registers with `mockAmdModule()`; a component test of the theme does the same.
 * What is asserted is what the bridge asks core's loader for and what the caller gets back.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAsync, requireManyAsync} from '@moodle/lms/core/amd';
import {load, loadMany} from '../src/amd';

const templates = {renderForPromise: jest.fn(), runTemplateJS: jest.fn()};
const notification = {addNotification: jest.fn(), exception: jest.fn()};

beforeEach(() => {
    mockAmdModule('core/templates', templates);
    mockAmdModule('core/notification', notification);
});

describe('theme_dimensions/amd', () => {
    it('loads one module with one call to core\'s loader, naming it alone', async() => {
        await expect(load('core/templates')).resolves.toBe(templates);
        expect(requireAsync).toHaveBeenCalledTimes(1);
        expect(requireAsync).toHaveBeenCalledWith('core/templates');
        expect(requireManyAsync).not.toHaveBeenCalled();
    });

    it('loads several modules with one call, in the order named', async() => {
        const [first, second] = await loadMany(['core/notification', 'core/templates']);
        expect(first).toBe(notification);
        expect(second).toBe(templates);
        expect(requireManyAsync).toHaveBeenCalledTimes(1);
        expect(requireManyAsync).toHaveBeenCalledWith(['core/notification', 'core/templates']);
        expect(requireAsync).not.toHaveBeenCalled();
    });

    it('hands the caller the rejection of core\'s loader, untouched', async() => {
        const failure = new Error('Script error for "core/notification"');
        (requireAsync as jest.Mock).mockImplementationOnce(() => Promise.reject(failure));
        (requireManyAsync as jest.Mock).mockImplementationOnce(() => Promise.reject(failure));

        await expect(load('core/notification')).rejects.toBe(failure);
        await expect(loadMany(['core/templates', 'core/notification'])).rejects.toBe(failure);
    });
});
