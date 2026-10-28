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
 * The filter line's preview, imported on first use: the first pointer or focus on the toolbar, or the first
 * suggestion walked to (P0 of docs/vitrine-react/PLAN.md, as amd/src/categoryline.js loads categorypreview).
 *
 * One function in a module of its own, because the browser runs the dynamic import and Jest cannot: core's
 * ts-jest compiles the tree as CommonJS under node16, which keeps import() native, and Jest's runtime
 * refuses a native import() without --experimental-vm-modules. The component's suite stands in for this
 * module; everything else of the preview is tested as it ships.
 *
 * @module     theme_dimensions/filterline/lazy
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The preview's module. */
export type PreviewModule = typeof import('./preview');

/**
 * Import the preview.
 *
 * @returns {Promise} Resolving with the module.
 */
export const importPreview = (): Promise<PreviewModule> => import('./preview');
