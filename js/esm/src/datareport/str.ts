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
 * Words and numbers: filling the strings the props carry (copied from local_evidencehub's datareport/str.ts).
 *
 * The strings arrive in the props, translated with their placeholders ("{$a} learners"), so the first paint
 * waits for no string request; only the client knows the value.
 *
 * @module     theme_dimensions/datareport/str
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Put a value into every placeholder of a string, as get_string() does in PHP.
 *
 * Split and join rather than replace(): a replacement string would read "$&" in a name as a pattern.
 *
 * @param {string} template The translated string.
 * @param {string} value What replaces {$a}.
 * @returns {string} The filled string.
 */
export const fill = (template: string | undefined, value: string): string =>
    (template || '').split('{$a}').join(value);

/**
 * Put several values into a string's object placeholders ({$a->name}).
 *
 * @param {string} template The translated string.
 * @param {object} values What replaces each one, by name.
 * @returns {string} The filled string.
 */
export const fillObject = (template: string | undefined, values: Record<string, string>): string =>
    Object.entries(values).reduce((text, [name, value]) => text.split(`{$a->${name}}`).join(value), template || '');

/**
 * Put a count into the string that fits it: the `<key>_one` string for exactly 1.
 *
 * @param {object} labels The view's strings.
 * @param {string} key The plain string's name.
 * @param {number} count The number the string states.
 * @param {string} shown What the placeholder reads.
 * @returns {string} The string with the count in it.
 */
export const fillCount = (labels: Record<string, string>, key: string, count: number, shown: string): string =>
    fill(count === 1 && labels[`${key}_one`] ? labels[`${key}_one`] : labels[key], shown);

/**
 * A number in the reader's own grouping, as the page's language writes it.
 *
 * @param {number} value The number.
 * @returns {string} The number, formatted.
 */
export const num = (value: number): string => {
    const lang = document.documentElement.lang || undefined;
    try {
        return new Intl.NumberFormat(lang).format(value);
    } catch {
        return String(value);
    }
};

/**
 * The text of a piece of the report's formatted HTML, for a title or an accessible name.
 *
 * Parsed in an inert template, so no script runs and no image loads.
 *
 * @param {string} html The cell's HTML.
 * @returns {string} Its text, with the line breaks between its parts read as one space.
 */
export const plain = (html: string): string => {
    const template = document.createElement('template');
    template.innerHTML = html;

    return (template.content.textContent || '').replace(/\s+/g, ' ').trim();
};
