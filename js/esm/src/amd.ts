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
 * The one door from the theme's ES modules to Moodle's AMD modules.
 *
 * A component cannot import an AMD module: the import map Moodle serves resolves `@moodle/lms/`,
 * react and react-dom, and nothing of RequireJS, so `import Templates from 'core/templates'` in a
 * .tsx fails to resolve in the browser and the component never mounts. AMD is reached through
 * core's loader (`@moodle/lms/core/amd`) instead, and only from this file.
 *
 * Every AMD module the theme's ESM tree may load is a key of `Modules`, so a name outside it is a
 * type error under the tsc gate, and the visitor's import walk
 * ({@see tests/local/category/categorypublic_source_test.php}) reads every AMD edge of the tree
 * from the call sites' literal names. Strings and the pending counter are not here: 5.3 ships them
 * as ES modules (`@moodle/lms/core/stringUtils`, `@moodle/lms/core/pending`), and `core/str` is
 * itself a shim over the first.
 *
 * @module     theme_dimensions/amd
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAsync, requireManyAsync} from '@moodle/lms/core/amd';

/** What `core/templates` renders: the markup and the JavaScript its blocks collected. */
export type Rendered = {html: string, js: string};

/** The part of `core/templates` the theme uses: Mustache stays the one description of the content. */
export type TemplatesModule = {
    renderForPromise: (name: string, context: object) => Promise<Rendered>,
    runTemplateJS: (js: string) => void,
};

/** One message for core's notification area. */
export type Notice = {message: string, type?: 'error' | 'warning' | 'info' | 'success'};

/** The part of `core/notification` the theme uses. */
export type NotificationModule = {
    addNotification: (notice: Notice) => unknown,
    exception: (error: unknown) => Promise<void>,
};

/** Every AMD module the ESM tree may load, by its RequireJS name. */
type Modules = {
    'core/templates': TemplatesModule,
    'core/notification': NotificationModule,
};

/** The name of an AMD module the ESM tree may load. */
export type ModuleName = keyof Modules;

/** The modules a list of names loads, in the same order. */
export type Loaded<K extends ModuleName[]> = {[I in keyof K]: K[I] extends ModuleName ? Modules[K[I]] : never};

/**
 * Load one AMD module.
 *
 * @param {string} name The module's RequireJS name, one of `Modules`.
 * @returns {Promise} Resolves with the module; rejects with RequireJS's error when it cannot load.
 */
export const load = <K extends ModuleName>(name: K): Promise<Modules[K]> => requireAsync<Modules[K]>(name);

/**
 * Load several AMD modules in one RequireJS call.
 *
 * @param {Array} names The modules' RequireJS names, each one of `Modules`.
 * @returns {Promise} Resolves with the modules in the order named; rejects when any cannot load.
 */
export const loadMany = <K extends ModuleName[]>(names: [...K]): Promise<Loaded<K>> =>
    requireManyAsync(names) as Promise<Loaded<K>>;
