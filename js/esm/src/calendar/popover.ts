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
 * The behaviour the category picker and the "More filters" panel share: a disclosure button and its panel.
 *
 * The button carries aria-expanded and aria-controls; Escape inside the panel shuts it and gives the focus back to
 * the button; a press outside both shuts it. The listener is on the document only while the panel is open.
 *
 * @module     theme_dimensions/calendar/popover
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from 'react';
import type {KeyboardEvent, RefObject} from 'react';

/** What a disclosure hands its markup. */
export type Popover = {
    open: boolean,
    toggle: () => void,
    shut: (refocus: boolean) => void,
    button: RefObject<HTMLButtonElement | null>,
    panel: RefObject<HTMLDivElement | null>,
    onKeyDown: (event: KeyboardEvent) => void,
};

/**
 * A disclosure's state and its listeners.
 *
 * @returns {object} See Popover.
 */
export const usePopover = (): Popover => {
    const [open, setOpen] = useState<boolean>(false);
    const button = useRef<HTMLButtonElement | null>(null);
    const panel = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (!open) {
            return undefined;
        }
        /**
         * Shut the panel on a press outside it and its button.
         *
         * @param {Event} event The press.
         * @returns {void}
         */
        const outside = (event: Event): void => {
            const target = event.target as Node | null;
            if (target && !panel.current?.contains(target) && !button.current?.contains(target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', outside);
        return () => document.removeEventListener('mousedown', outside);
    }, [open]);

    /**
     * Shut the panel.
     *
     * @param {boolean} refocus Whether the focus goes back to the button.
     * @returns {void}
     */
    const shut = (refocus: boolean): void => {
        setOpen(false);
        if (refocus) {
            button.current?.focus();
        }
    };

    return {
        open: open,
        /**
         * Open or shut the panel.
         *
         * @returns {void}
         */
        toggle: (): void => setOpen((was) => !was),
        shut: shut,
        button: button,
        panel: panel,
        /**
         * Escape inside the panel shuts it.
         *
         * @param {object} event The key press.
         * @returns {void}
         */
        onKeyDown: (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                shut(true);
            }
        },
    };
};
