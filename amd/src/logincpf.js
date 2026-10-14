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
 * CPF handling for the username field of the login form.
 *
 * The username field becomes a CPF field: the mask (000.000.000-00) is always on, every character
 * which is not a digit is dropped as it is typed, the digits stop at eleven - a twelfth is refused
 * and said so - and the form cannot be sent unless those eleven digits pass the check-digit rule of
 * auth_loginsteps\local\cpf, which is where a CPF becomes a username in the first place, stored as
 * the bare 11-digit string.
 *
 * A theme cannot validate a login server-side, because nothing of the theme runs before the
 * credentials are checked. So this is a convenience layer, and the two things that keep it from
 * doing damage are:
 *
 * - The mask is removed again before the request leaves the browser. This happens twice, on
 *   purpose: in a capture-phase submit listener on the document (which runs before any listener on
 *   the form, core_form/submit included) and in the formdata event (which fires even for a
 *   submission started from script, where no submit event is dispatched at all).
 * - An account whose username is not a CPF - the site admin above all - reaches a plain login form
 *   through the bypass parameter, which the server checks before this module is ever loaded. The
 *   setting description names the URL.
 *
 * An invalid CPF is refused through setCustomValidity(), so the browser stops the submission before
 * the submit event is dispatched. Blocking it from a submit listener instead would leave
 * core_form/submit's already-disabled login button behind, with no way to try again.
 *
 * @module     theme_dimensions/logincpf
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
    form: 'form#login',
    username: '#username',
};

/** @type {Number} Number of digits in a CPF. */
const CPFLENGTH = 11;

/** @type {String} Id of the feedback element which this module injects below the username field. */
const FEEDBACKID = 'theme_dimensions-cpf-feedback';

/** @type {RegExp} Characters which the field refuses: anything but a digit or a mask separator. */
const REJECTED = /[^\d.\s-]/;

/**
 * Strip every non-digit character from a value.
 *
 * @param {String} value Raw field value.
 * @return {String} Digits only.
 */
const digitsOf = (value) => value.replace(/\D/g, '');

/**
 * Validate a CPF check-digit pair.
 *
 * Accepts only the bare 11-digit form. The ten repeated-digit sequences satisfy the mod-11
 * arithmetic and are rejected explicitly.
 *
 * @param {String} digits Candidate CPF, digits only.
 * @return {Boolean} True when the value is a structurally valid CPF.
 */
const isValidCpf = (digits) => {
    if (!/^\d{11}$/.test(digits)) {
        return false;
    }
    if (/^(\d)\1{10}$/.test(digits)) {
        return false;
    }
    for (let pos = 9; pos < CPFLENGTH; pos++) {
        let sum = 0;
        for (let i = 0; i < pos; i++) {
            sum += parseInt(digits[i], 10) * ((pos + 1) - i);
        }
        const remainder = sum % 11;
        const digit = (remainder < 2) ? 0 : 11 - remainder;
        if (digit !== parseInt(digits[pos], 10)) {
            return false;
        }
    }
    return true;
};

/**
 * Format a digit string as 000.000.000-00, as far as the digits go.
 *
 * @param {String} digits Digits only.
 * @return {String} Masked value.
 */
const formatCpf = (digits) => {
    const d = digits.slice(0, CPFLENGTH);
    let out = d.slice(0, 3);
    if (d.length > 3) {
        out += '.' + d.slice(3, 6);
    }
    if (d.length > 6) {
        out += '.' + d.slice(6, 9);
    }
    if (d.length > 9) {
        out += '-' + d.slice(9, 11);
    }
    return out;
};

/**
 * Why the field is about to drop something the user typed, if it is.
 *
 * The two reasons get their own message because they are different mistakes: one is a username
 * which is not a CPF at all, the other is a CPF with a digit too many.
 *
 * @param {String} value Raw field value.
 * @return {String|null} Key of the message to show, or null when nothing is dropped.
 */
const rejectionReason = (value) => {
    if (REJECTED.test(value)) {
        return 'digitsonly';
    }
    if (digitsOf(value).length > CPFLENGTH) {
        return 'maxdigits';
    }
    return null;
};

/**
 * Reformat the field in place, keeping the caret after the same digit it followed before.
 *
 * Everything which is not a digit is dropped and the digits are capped at eleven, so the field
 * always holds a CPF or a prefix of one.
 *
 * @param {HTMLInputElement} input The username field.
 */
const reformat = (input) => {
    const value = input.value;
    const target = formatCpf(digitsOf(value));
    if (target === value) {
        return;
    }
    const focused = document.activeElement === input;
    const digitsbefore = digitsOf(value.slice(0, input.selectionStart ?? value.length)).length;
    input.value = target;
    if (!focused) {
        return;
    }
    let pos = 0;
    let seen = 0;
    while (pos < target.length && seen < digitsbefore) {
        if (/\d/.test(target[pos])) {
            seen++;
        }
        pos++;
    }
    input.setSelectionRange(pos, pos);
};

/**
 * Tell the browser, the keyboard and the reader that this field now takes a CPF.
 *
 * No maxlength is set, deliberately. It would work - the masked form is exactly fourteen characters
 * - but the browser then swallows the twelfth digit without firing an input event, so the field
 * would stop accepting keystrokes with nothing on screen to say why. Capping the digits in the
 * input handler instead costs one repaint and buys an explanation.
 *
 * The label is left as core wrote it: core's login scenarios fill the field by that label
 * (I set the field "Username" to ...), so renaming it would break them on a site running this
 * theme. The placeholder carries the hint instead.
 *
 * @param {HTMLInputElement} input The username field.
 * @param {Object} messages The messages handed over by the server.
 */
const prepareField = (input, messages) => {
    input.setAttribute('inputmode', 'numeric');
    if (messages.placeholder) {
        input.setAttribute('placeholder', messages.placeholder);
    }
};

/**
 * Create (once) the feedback element which carries the validation message.
 *
 * The element is created empty and stays in the page, because a live region has to exist before the
 * text is written into it for screen readers to announce the change reliably.
 *
 * @param {HTMLInputElement} input The username field.
 * @return {HTMLElement} The feedback element.
 */
const ensureFeedback = (input) => {
    let feedback = document.getElementById(FEEDBACKID);
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.id = FEEDBACKID;
        // The d-block utility is needed because the element is not inside a validated form group.
        feedback.className = 'invalid-feedback d-block';
        feedback.setAttribute('role', 'alert');
        input.insertAdjacentElement('afterend', feedback);
    }
    const describedby = input.getAttribute('aria-describedby');
    if (!describedby) {
        input.setAttribute('aria-describedby', FEEDBACKID);
    } else if (!describedby.split(' ').includes(FEEDBACKID)) {
        input.setAttribute('aria-describedby', describedby + ' ' + FEEDBACKID);
    }
    return feedback;
};

/**
 * Show a validation message.
 *
 * @param {HTMLInputElement} input The username field.
 * @param {HTMLElement} feedback The feedback element.
 * @param {String} message The message to show.
 */
const showError = (input, feedback, message) => {
    feedback.textContent = message;
    input.classList.add('is-invalid');
    input.setAttribute('aria-invalid', 'true');
};

/**
 * Remove the validation message.
 *
 * @param {HTMLInputElement} input The username field.
 * @param {HTMLElement} feedback The feedback element.
 */
const clearError = (input, feedback) => {
    feedback.textContent = '';
    input.classList.remove('is-invalid');
    input.removeAttribute('aria-invalid');
};

/**
 * Keep the browser's own constraint validation in step with the current value.
 *
 * An empty field is left to Moodle, which has its own answer for a login with no username.
 *
 * @param {HTMLInputElement} input The username field.
 * @param {Object} messages The messages handed over by the server.
 */
const updateValidity = (input, messages) => {
    const digits = digitsOf(input.value);
    const invalid = digits.length > 0 && !isValidCpf(digits);
    input.setCustomValidity(invalid ? messages.invalid : '');
};

/**
 * Make sure that the value which leaves the browser is the bare CPF and not the masked one.
 *
 * @param {HTMLFormElement} form The login form.
 * @param {HTMLInputElement} input The username field.
 */
const stripMaskOnSubmit = (form, input) => {
    document.addEventListener('submit', (event) => {
        if (event.target === form) {
            input.value = digitsOf(input.value);
        }
    }, true);
    form.addEventListener('formdata', (event) => {
        if (input.name) {
            event.formData.set(input.name, digitsOf(input.value));
        }
    });
};

/**
 * Turn the login form's username field into a CPF field, if there is one on this page.
 *
 * @param {Object} messages The messages handed over by the server, keyed by purpose.
 */
export const init = (messages) => {
    const form = document.querySelector(SELECTORS.form);
    const input = form ? form.querySelector(SELECTORS.username) : null;
    if (!form || !input) {
        return;
    }
    const feedback = ensureFeedback(input);
    prepareField(input, messages);

    input.addEventListener('input', () => {
        const reason = rejectionReason(input.value);
        reformat(input);
        updateValidity(input, messages);
        if (reason) {
            showError(input, feedback, messages[reason]);
        } else {
            clearError(input, feedback);
        }
    });
    input.addEventListener('blur', () => {
        updateValidity(input, messages);
        if (!input.validity.valid) {
            showError(input, feedback, messages.invalid);
        }
    });
    input.addEventListener('invalid', (event) => {
        // Suppress the browser's own bubble; the feedback element says the same thing, in place.
        event.preventDefault();
        showError(input, feedback, messages.invalid);
        input.focus();
    });

    stripMaskOnSubmit(form, input);

    // A value redisplayed after a failed login arrives unmasked, and a username remembered from an
    // earlier session may not be a CPF at all - in which case this clears it, quietly, on load.
    reformat(input);
    updateValidity(input, messages);
};
