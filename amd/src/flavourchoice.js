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
 * The Look items of the user menu and of the mobile drawer: a choice recorded in place, then a reload.
 *
 * Every item is a link to flavour.php with its flavour preselected, which is the whole control without
 * JavaScript: that page shows a form and writes nothing until it is posted. With this module a press on
 * an item records the choice through theme_dimensions_choose_flavour and reloads the page, which the
 * server then draws in the new look; the current item does nothing. The listener is delegated from the
 * document, because the user menu's submenus and the drawer's panels are drawn by templates this module
 * never owns.
 *
 * @module     theme_dimensions/flavourchoice
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

/** @var {String} The attribute every Look item carries: the flavour id it chooses, 0 for the site default. */
const ATTRIBUTE = 'data-dimensions-flavour';

/** @var {Boolean} Whether the document listener is already registered. */
let armed = false;

/**
 * Record the choice, then reload the page in the new look.
 *
 * core/pending is held until the reload starts, so Behat waits for the choice to be recorded.
 *
 * @param {Number} flavourid The chosen flavour id, 0 for the site default.
 * @return {Promise}
 */
const choose = async(flavourid) => {
    const pending = new Pending('theme_dimensions/flavourchoice:choose');
    try {
        await Ajax.call([{
            methodname: 'theme_dimensions_choose_flavour',
            args: {flavourid: flavourid},
        }])[0];
        window.location.reload();
    } catch (error) {
        Notification.exception(error);
        pending.resolve();
    }
};

/**
 * Listen for presses on the Look items, once per page.
 *
 * @return {void}
 */
export const init = () => {
    if (armed) {
        return;
    }
    armed = true;

    document.addEventListener('click', (event) => {
        const item = event.target.closest(`[${ATTRIBUTE}]`);
        if (!item || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey
            || event.altKey) {
            return;
        }
        event.preventDefault();
        if (item.getAttribute('aria-current') === 'true') {
            return;
        }
        choose(parseInt(item.getAttribute(ATTRIBUTE), 10));
    });
};
