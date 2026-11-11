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
 * Keeps the swatch of an event type's colour setting in step with its hexadecimal field.
 *
 * The server draws the whole control (templates/admin_setting_keycolour.mustache), so the field works alone without
 * this module. It adds the two-way sync, with one input listener delegated from the document so that nine settings, or
 * one added to the page later, cost one listener: typing updates the preview and the native colour input, and picking
 * writes the hex into the field and dispatches input and change on it.
 *
 * @module     theme_dimensions/keycolour
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const ROW = '[data-dim-keycolour]';
const FIELD = '.dim-keycolour__field';
const NATIVE = '.dim-keycolour__native';
const PREVIEW = '.dim-keycolour__preview';
const EMPTY = 'dim-keycolour__preview--empty';

/** Whether the document's listeners are in place. */
let wired = false;

/**
 * Return a normalised 3/6-digit hex colour, or an empty string.
 *
 * @param {String} value Raw input value.
 * @return {String}
 */
const normaliseColour = (value) => {
    const trimmed = (value || '').trim();
    return (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i).test(trimmed) ? trimmed : '';
};

/**
 * Expand a normalised hex to the #rrggbb form a native colour input accepts.
 *
 * @param {String} colour A normalised (valid) hex colour, or an empty string.
 * @return {String} A #rrggbb colour, or an empty string when there is none.
 */
const toSixDigit = (colour) => {
    if (!colour) {
        return '';
    }
    if (colour.length === 4) {
        return '#' + colour[1] + colour[1] + colour[2] + colour[2] + colour[3] + colour[3];
    }
    return colour;
};

/**
 * Draw the preview and the native input from the field's value.
 *
 * @param {Element} row The control's row.
 */
const sync = (row) => {
    const field = row.querySelector(FIELD);
    const native = row.querySelector(NATIVE);
    const preview = row.querySelector(PREVIEW);
    if (!field || !native || !preview) {
        return;
    }
    const colour = normaliseColour(field.value);
    preview.style.backgroundColor = colour;
    preview.classList.toggle(EMPTY, !colour);
    const full = toSixDigit(colour);
    if (full) {
        native.value = full;
    }
};

/**
 * Wire the document once: typing in a field and picking from a native input.
 */
export const init = () => {
    if (wired) {
        return;
    }
    wired = true;

    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const row = target.closest(ROW);
        if (!row) {
            return;
        }
        if (target.matches(NATIVE)) {
            const field = row.querySelector(FIELD);
            if (field) {
                field.value = target.value;
                field.dispatchEvent(new Event('input', {bubbles: true}));
                field.dispatchEvent(new Event('change', {bubbles: true}));
            }
            return;
        }
        if (target.matches(FIELD)) {
            sync(row);
        }
    });
};
