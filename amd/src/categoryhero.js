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
 * The colour half of the category appearance form: swatches, live validity and the contrast panel.
 *
 * The form is complete without this module: the two colours are plain text fields holding a
 * #rrggbb value, and the server refuses both a value that is not a hex colour and a pair under
 * the WCAG AA floor of 4.5:1. What this module adds is what a text field cannot do: a swatch
 * opening the browser's own colour picker, a field marked invalid while it is being typed rather
 * than at the next save, and a live panel grading the pair, with up to two one-click fixes when
 * it fails AA.
 *
 * The theme does not depend on local_dimensions at runtime. The contrast
 * maths follow WCAG 2.x (sRGB linearisation, relative luminance, (L1 + 0.05) / (L2 + 0.05)) and
 * mirror {@see branding::contrast()}; tests/fixtures/contrast_pairs.json is the fixture both are
 * measured against, so the panel cannot grade a pair differently from the server.
 *
 * Nothing here moves a form row: relocating moodleform rows with JavaScript hides any field the
 * script does not know about, and can leave a field focusable while it is painted nowhere. The
 * panel is appended as the last child of the colours fieldset's container and every row stays
 * where the form drew it. The editor's two columns are a CSS grid over that container, switched
 * on by the class this module adds once the panel is mounted, so with JavaScript off the section
 * is a single column with no empty track.
 *
 * @module     theme_dimensions/categoryhero
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Templates from 'core/templates';
import {getStrings} from 'core/str';

/** @type {Object} Every hook this module reads: the two form fields, the panel and the preview. */
const SELECTORS = {
    bg: 'input[name="bgcolour"]',
    ink: 'input[name="inkcolour"]',
    panel: '[data-region="dim-contrast-panel"]',
    preview: '[data-region="dim-pair-preview"]',
    container: '.fcontainer',
};

/** @type {String} The panel partial, rendered once and then written into by this module. */
const PANEL_TEMPLATE = 'theme_dimensions/categoryhero_contrast';

/**
 * @type {String} The class this module puts on the colours fieldset once the panel is mounted.
 *
 * It turns the stylesheet's two-column grid on, and it is added here rather than written by the
 * form because with JavaScript off there is no panel, and a grid switched on by the server would
 * leave an empty right-hand track beside the fields.
 */
const SPLIT_CLASS = 'dim-colours--split';

/** @type {String} The component every string of this module comes from. */
const COMPONENT = 'theme_dimensions';

/** @type {RegExp} Matches a #RGB or #RRGGBB hex colour. */
const HEXRE = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

/**
 * @type {Object} WCAG thresholds for normal text, which the hero's tagline and description are.
 *
 * `aa` is also the floor branding::save() refuses under ({@see branding::FLOOR}), so the panel
 * and the server agree on what is publishable; `attention` is only the tone of the verdict pill
 * between 3 and AA.
 */
const THRESHOLDS = {aa: 4.5, aaa: 7, attention: 3};

/** @type {Object} Glyphs used for the verdict pill and the badge state icons. */
const GLYPH = {pass: '✓', warn: '!', fail: '✕', muted: '–'};

/** @type {Object} Maps a badge state to its feedback tone. */
const TONE_BY_STATE = {pass: 'success', fail: 'danger', muted: 'muted'};

/** @type {?Object} Cached lang strings used by the panel and the field messages, by short name. */
let labels = null;

/**
 * Parse a #RGB or #RRGGBB string into 8-bit channels.
 *
 * @param {String} value Raw input value.
 * @return {?Object} {r, g, b} or null when the value is not a valid hex colour.
 */
const parseHex = (value) => {
    const match = HEXRE.exec((value || '').trim());
    if (!match) {
        return null;
    }
    let hex = match[1];
    if (hex.length === 3) {
        hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
    }
    return {
        r: parseInt(hex.slice(0, 2), 16),
        g: parseInt(hex.slice(2, 4), 16),
        b: parseInt(hex.slice(4, 6), 16),
    };
};

/**
 * Linearise one 8-bit sRGB channel.
 *
 * @param {Number} channel Channel value 0-255.
 * @return {Number} Linear-light value 0-1.
 */
const linearise = (channel) => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
};

/**
 * Relative luminance of an sRGB colour.
 *
 * @param {Object} rgb {r, g, b} channels 0-255.
 * @return {Number} Relative luminance 0-1.
 */
const luminance = (rgb) => 0.2126 * linearise(rgb.r) + 0.7152 * linearise(rgb.g) + 0.0722 * linearise(rgb.b);

/**
 * WCAG contrast ratio between two colours.
 *
 * @param {Object} rgb1 First colour {r, g, b}.
 * @param {Object} rgb2 Second colour {r, g, b}.
 * @return {Number} Contrast ratio in the range 1-21.
 */
const contrast = (rgb1, rgb2) => {
    const l1 = luminance(rgb1);
    const l2 = luminance(rgb2);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
};

/**
 * Convert an sRGB colour to HSL.
 *
 * @param {Object} rgb {r, g, b} channels 0-255.
 * @return {Object} {h: 0-360, s: 0-100, l: 0-100}.
 */
const rgbToHsl = (rgb) => {
    const r = rgb.r / 255;
    const g = rgb.g / 255;
    const b = rgb.b / 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const delta = max - min;
    let h = 0;
    if (delta !== 0) {
        if (max === r) {
            h = ((g - b) / delta) % 6;
        } else if (max === g) {
            h = (b - r) / delta + 2;
        } else {
            h = (r - g) / delta + 4;
        }
        h *= 60;
        if (h < 0) {
            h += 360;
        }
    }
    const l = (max + min) / 2;
    const s = delta === 0 ? 0 : delta / (1 - Math.abs(2 * l - 1));
    return {h: h, s: s * 100, l: l * 100};
};

/**
 * Convert an HSL colour to sRGB.
 *
 * @param {Object} hsl {h: 0-360, s: 0-100, l: 0-100}.
 * @return {Object} {r, g, b} channels 0-255.
 */
const hslToRgb = (hsl) => {
    const s = hsl.s / 100;
    const l = hsl.l / 100;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs(((hsl.h / 60) % 2) - 1));
    const m = l - c / 2;
    let r = 0;
    let g = 0;
    let b = 0;
    if (hsl.h < 60) {
        r = c;
        g = x;
    } else if (hsl.h < 120) {
        r = x;
        g = c;
    } else if (hsl.h < 180) {
        g = c;
        b = x;
    } else if (hsl.h < 240) {
        g = x;
        b = c;
    } else if (hsl.h < 300) {
        r = x;
        b = c;
    } else {
        r = c;
        b = x;
    }
    return {
        r: Math.round((r + m) * 255),
        g: Math.round((g + m) * 255),
        b: Math.round((b + m) * 255),
    };
};

/**
 * Format an sRGB colour as a #rrggbb string.
 *
 * @param {Object} rgb {r, g, b} channels 0-255.
 * @return {String} Lowercase #rrggbb hex.
 */
const rgbToHex = (rgb) => '#' + [rgb.r, rgb.g, rgb.b]
    .map((v) => v.toString(16).padStart(2, '0'))
    .join('');

/**
 * Format a contrast ratio as "4.59:1" (two decimals, or one for values >= ~10).
 *
 * @param {Number} ratio Raw contrast ratio.
 * @return {String} The ratio followed by ":1".
 */
const formatRatio = (ratio) => (ratio >= 9.995 ? ratio.toFixed(1) : ratio.toFixed(2)) + ':1';

/**
 * Expand a normalised hex to the #rrggbb form a native colour input accepts.
 *
 * @param {String} colour A normalised (valid) hex colour, or an empty string.
 * @return {String} A #rrggbb colour, or an empty string when the input is empty.
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
 * Write a value into a colour field and tell every listener, exactly as typing would.
 *
 * The swatch, the panel and the browser's own validation all hang off these two events; a
 * value assigned without them is a field the page believes still holds the old colour.
 *
 * @param {HTMLInputElement} input The field to write into.
 * @param {String} value The hex value to write.
 */
const writeColour = (input, value) => {
    input.value = value;
    input.dispatchEvent(new Event('input', {bubbles: true}));
    input.dispatchEvent(new Event('change', {bubbles: true}));
};

/**
 * Find a replacement colour that reaches the target ratio against a fixed colour.
 *
 * Keeps the hue and saturation of the colour being adjusted and walks lightness from 0 to 100,
 * choosing the lightness closest to the original that meets the target contrast.
 *
 * @param {Object} fixedrgb The colour that stays put {r, g, b}.
 * @param {Object} adjustrgb The colour being adjusted {r, g, b}.
 * @param {Number} target Target contrast ratio.
 * @return {?Object} {hex, ratio} of the suggestion, or null when none reaches the target.
 */
const suggest = (fixedrgb, adjustrgb, target) => {
    const hsl = rgbToHsl(adjustrgb);
    let best = null;
    for (let l = 0; l <= 100; l++) {
        const candidate = hslToRgb({h: hsl.h, s: hsl.s, l: l});
        const ratio = contrast(fixedrgb, candidate);
        if (ratio < target) {
            continue;
        }
        const distance = Math.abs(l - hsl.l);
        if (best === null || distance < best.distance) {
            best = {distance: distance, hex: rgbToHex(candidate), ratio: ratio};
        }
    }
    return best;
};

/**
 * Resolve the verdict for a ratio: label key, feedback tone, and glyph.
 *
 * @param {Number} ratio Contrast ratio.
 * @return {Object} {key, tone, glyph} where tone is success|warning|danger.
 */
const verdictFor = (ratio) => {
    if (ratio >= THRESHOLDS.aaa) {
        return {key: 'excellent', tone: 'success', glyph: GLYPH.pass};
    }
    if (ratio >= THRESHOLDS.aa) {
        return {key: 'pass', tone: 'success', glyph: GLYPH.pass};
    }
    if (ratio >= THRESHOLDS.attention) {
        return {key: 'attention', tone: 'warning', glyph: GLYPH.warn};
    }
    return {key: 'fail', tone: 'danger', glyph: GLYPH.fail};
};

/**
 * Apply a feedback tone class (is-success/is-warning/is-danger/is-muted) to an element.
 *
 * @param {HTMLElement} element The element to tone.
 * @param {String} tone success|warning|danger|muted.
 */
const setTone = (element, tone) => {
    element.classList.remove('is-success', 'is-warning', 'is-danger', 'is-muted');
    element.classList.add('is-' + tone);
};

/**
 * Paint one half of the pair preview the form rendered, from a colour that already parsed.
 *
 * The preview is the server's: it opens carrying the stored pair and is correct without this
 * module. All this does is keep it in step while the pair is typed - and only from the valid-hex
 * path, so a half-typed value leaves the last valid pair on the band rather than blanking it.
 *
 * @param {String} property The style property this field paints: backgroundColor or color.
 * @param {String} colour A #rrggbb colour the swatch logic has accepted.
 */
const paintPreview = (property, colour) => {
    const preview = document.querySelector(SELECTORS.preview);
    if (preview) {
        preview.style[property] = colour;
    }
};

/**
 * Prepend a live, clickable swatch to one colour field.
 *
 * The swatch wraps the field in a row of its own so the two sit side by side; the field itself
 * stays inside the form row moodleform drew, with its label and its error message.
 *
 * @param {HTMLInputElement} input The colour field.
 * @param {String} pickerlabel Accessible label for the native colour input.
 * @param {String} property The half of the pair preview this field paints.
 */
const decorate = (input, pickerlabel, property) => {
    if (!input || !input.parentNode || input.dataset.dimSwatch === '1') {
        return;
    }
    input.dataset.dimSwatch = '1';

    const row = document.createElement('span');
    row.className = 'dim-colour-row';

    const picker = document.createElement('label');
    picker.className = 'dim-colour-swatch';
    const preview = document.createElement('span');
    preview.className = 'dim-colour-preview';
    const colourinput = document.createElement('input');
    colourinput.type = 'color';
    colourinput.className = 'dim-colour-input';
    colourinput.setAttribute('aria-label', pickerlabel);
    picker.appendChild(preview);
    picker.appendChild(colourinput);

    input.parentNode.insertBefore(row, input);
    row.appendChild(picker);
    row.appendChild(input);

    const sync = () => {
        const raw = (input.value || '').trim();
        const colour = HEXRE.test(raw) ? raw : '';
        preview.style.backgroundColor = colour || 'transparent';
        preview.classList.toggle('dim-colour-preview--empty', !colour);
        const full = toSixDigit(colour);
        if (full) {
            colourinput.value = full;
            paintPreview(property, full);
        }
    };

    // Typing in the hex field updates the swatch and the native picker.
    input.addEventListener('input', sync);
    // Picking a colour writes it back to the hex field and notifies every listener.
    colourinput.addEventListener('input', () => writeColour(input, colourinput.value));
    sync();
};

/**
 * Build one suggestion row (swatch, label, new hex + ratio, Apply button).
 *
 * @param {Object} option {label, hex, ratio, apply} for the suggestion.
 * @param {Function} onapply Called with the option when Apply is pressed.
 * @return {HTMLElement} The suggestion row element.
 */
const buildSuggestionRow = (option, onapply) => {
    const row = document.createElement('div');
    row.className = 'dim-contrast__suggestion';

    const swatch = document.createElement('span');
    swatch.className = 'dim-contrast__suggestion-swatch';
    swatch.style.backgroundColor = option.hex;

    const text = document.createElement('span');
    text.className = 'dim-contrast__suggestion-text';
    const which = document.createElement('span');
    which.className = 'dim-contrast__suggestion-which';
    which.textContent = option.label;
    const meta = document.createElement('span');
    meta.className = 'dim-contrast__suggestion-meta';
    meta.textContent = option.hex + ' · ' + formatRatio(option.ratio);
    text.appendChild(which);
    text.appendChild(meta);

    const apply = document.createElement('button');
    apply.type = 'button';
    apply.className = 'dim-contrast__apply btn btn-sm btn-primary';
    apply.textContent = labels.apply;
    apply.setAttribute('aria-label', labels.apply + ': ' + option.label);
    apply.addEventListener('click', () => onapply(option));

    row.appendChild(swatch);
    row.appendChild(text);
    row.appendChild(apply);
    return row;
};

/**
 * Panel controller: caches its DOM hooks and applies computed state to them.
 *
 * @param {HTMLElement} panel The rendered panel root.
 * @param {HTMLInputElement} bginput The background colour field.
 * @param {HTMLInputElement} inkinput The text colour field.
 * @return {Object} An object with an update() method.
 */
const controller = (panel, bginput, inkinput) => {
    const dom = {
        ratio: panel.querySelector('[data-region="dim-contrast-ratio"]'),
        verdict: panel.querySelector('[data-region="dim-contrast-verdict"]'),
        verdictglyph: panel.querySelector('[data-region="dim-contrast-verdict-glyph"]'),
        verdictlabel: panel.querySelector('[data-region="dim-contrast-verdict-label"]'),
        aa: panel.querySelector('[data-region="dim-contrast-aa"]'),
        aaglyph: panel.querySelector('[data-region="dim-contrast-aa-glyph"]'),
        aamin: panel.querySelector('[data-region="dim-contrast-aa-min"]'),
        aaname: panel.querySelector('[data-region="dim-contrast-aa"] .dim-contrast__badge-name'),
        aaa: panel.querySelector('[data-region="dim-contrast-aaa"]'),
        aaaglyph: panel.querySelector('[data-region="dim-contrast-aaa-glyph"]'),
        aaamin: panel.querySelector('[data-region="dim-contrast-aaa-min"]'),
        aaaname: panel.querySelector('[data-region="dim-contrast-aaa"] .dim-contrast__badge-name'),
        suggestions: panel.querySelector('[data-region="dim-contrast-suggestions"]'),
        suggestionstitle: panel.querySelector('[data-region="dim-contrast-suggestions-title"]'),
        list: panel.querySelector('[data-region="dim-contrast-suggestions-list"]'),
        note: panel.querySelector('[data-region="dim-contrast-note"]'),
        noteglyph: panel.querySelector('[data-region="dim-contrast-note-glyph"]'),
        notetext: panel.querySelector('[data-region="dim-contrast-note-text"]'),
    };

    /**
     * Set the verdict pill's tone, glyph and label.
     *
     * @param {String} tone success|warning|danger|muted.
     * @param {String} glyph Icon character (empty for muted).
     * @param {String} label Verdict text.
     */
    const setVerdict = (tone, glyph, label) => {
        setTone(dom.verdict, tone);
        dom.verdictglyph.textContent = glyph;
        dom.verdictlabel.textContent = label;
    };

    /**
     * Set one WCAG badge (AA or AAA) to pass, fail or muted.
     *
     * @param {Object} badge {wrapper, glyph, min, name} elements of the badge.
     * @param {String} state pass|fail|muted.
     * @param {Number} threshold The badge's minimum ratio.
     */
    const setBadge = (badge, state, threshold) => {
        setTone(badge.wrapper, TONE_BY_STATE[state] || 'muted');
        badge.glyph.textContent = GLYPH[state] || GLYPH.muted;
        badge.min.textContent = labels.min + ' ' + formatRatio(threshold);
        let status = '—';
        if (state === 'pass') {
            status = labels.pass;
        } else if (state === 'fail') {
            status = labels.fail;
        }
        badge.wrapper.setAttribute('aria-label', badge.name.textContent.trim() + ': ' + status);
    };

    /** @type {Object} The AA badge's elements, as setBadge wants them. */
    const aabadge = {wrapper: dom.aa, glyph: dom.aaglyph, min: dom.aamin, name: dom.aaname};

    /** @type {Object} The AAA badge's elements. */
    const aaabadge = {wrapper: dom.aaa, glyph: dom.aaaglyph, min: dom.aaamin, name: dom.aaaname};

    /**
     * Show or hide the fallback note.
     *
     * @param {?String} tone success|warning|danger|muted, or null to hide.
     * @param {String} glyph Icon character (empty for none).
     * @param {String} text Note text.
     */
    const setNote = (tone, glyph, text) => {
        if (tone === null) {
            dom.note.hidden = true;
            return;
        }
        setTone(dom.note, tone);
        dom.noteglyph.textContent = glyph;
        dom.notetext.textContent = text;
        dom.note.hidden = false;
    };

    /**
     * Write a suggestion into its field and re-run the swatch and panel updates.
     *
     * @param {Object} option {hex, apply} where apply is 'ink' or 'bg'.
     */
    const applyOption = (option) => {
        const input = option.apply === 'ink' ? inkinput : bginput;
        writeColour(input, option.hex);
        input.focus();
    };

    /**
     * Populate the suggestions block for a failing pair. Returns whether any were shown.
     *
     * @param {Object} bg Background colour {r, g, b}.
     * @param {Object} ink Text colour {r, g, b}.
     * @return {Boolean} True when at least one suggestion was rendered.
     */
    const renderSuggestions = (bg, ink) => {
        dom.list.textContent = '';
        const options = [];
        const fixink = suggest(bg, ink, THRESHOLDS.aa);
        if (fixink) {
            options.push({label: labels.suggestink, hex: fixink.hex, ratio: fixink.ratio, apply: 'ink'});
        }
        const fixbg = suggest(ink, bg, THRESHOLDS.aa);
        if (fixbg) {
            options.push({label: labels.suggestbg, hex: fixbg.hex, ratio: fixbg.ratio, apply: 'bg'});
        }
        if (!options.length) {
            dom.suggestions.hidden = true;
            return false;
        }
        dom.suggestionstitle.textContent = labels.suggestheading;
        options.forEach((option) => dom.list.appendChild(buildSuggestionRow(option, applyOption)));
        dom.suggestions.hidden = false;
        return true;
    };

    /**
     * Recompute everything from the current field values.
     */
    const update = () => {
        const bg = parseHex(bginput.value);
        const ink = parseHex(inkinput.value);

        if (!bg || !ink) {
            panel.classList.add('dim-contrast--invalid');
            dom.ratio.textContent = '—';
            setVerdict('muted', '', '—');
            setBadge(aabadge, 'muted', THRESHOLDS.aa);
            setBadge(aaabadge, 'muted', THRESHOLDS.aaa);
            dom.suggestions.hidden = true;
            dom.list.textContent = '';
            setNote('muted', '', labels.invalidhex);
            return;
        }

        panel.classList.remove('dim-contrast--invalid');
        const ratio = contrast(bg, ink);
        dom.ratio.textContent = formatRatio(ratio);

        const verdict = verdictFor(ratio);
        setVerdict(verdict.tone, verdict.glyph, labels[verdict.key]);
        setBadge(aabadge, ratio >= THRESHOLDS.aa ? 'pass' : 'fail', THRESHOLDS.aa);
        setBadge(aaabadge, ratio >= THRESHOLDS.aaa ? 'pass' : 'fail', THRESHOLDS.aaa);

        if (ratio >= THRESHOLDS.aa) {
            dom.suggestions.hidden = true;
            dom.list.textContent = '';
            setNote(null, '', '');
            return;
        }

        if (renderSuggestions(bg, ink)) {
            setNote(null, '', '');
        } else {
            setNote('danger', GLYPH.fail, labels.belowaa);
        }
    };

    return {update: update};
};

/**
 * Fetch and cache every string the swatches, the panel and the field messages write.
 *
 * @return {Promise<void>}
 */
const loadStrings = async() => {
    const aamin = formatRatio(THRESHOLDS.aa);
    /* Keyed by name rather than by position, so a string added to the list cannot renumber the
       labels after it. The two strings with a parameter are appended last and read by index. */
    const plain = {
        pickerbg: 'categoryhero_colourpicker_bg',
        pickerink: 'categoryhero_colourpicker_ink',
        excellent: 'categoryhero_contrast_excellent',
        pass: 'categoryhero_contrast_pass',
        attention: 'categoryhero_contrast_attention',
        fail: 'categoryhero_contrast_fail',
        min: 'categoryhero_contrast_min',
        suggestink: 'categoryhero_contrast_suggest_text',
        suggestbg: 'categoryhero_contrast_suggest_bg',
        apply: 'categoryhero_contrast_apply',
        invalidhex: 'categoryhero_contrast_invalidhex',
        invalidcolour: 'categoryhero_invalidcolour',
    };
    const names = Object.keys(plain);
    const requests = names.map((name) => ({key: plain[name], component: COMPONENT}));
    requests.push({key: 'categoryhero_contrast_suggest_heading', component: COMPONENT, param: aamin});
    requests.push({key: 'categoryhero_contrast_belowaa', component: COMPONENT, param: aamin});
    const strings = await getStrings(requests);
    labels = {};
    names.forEach((name, index) => {
        labels[name] = strings[index];
    });
    labels.suggestheading = strings[names.length];
    labels.belowaa = strings[names.length + 1];
};

/**
 * The form row a field was drawn inside, which is where its own message is written.
 *
 * Read as an anchor and never as something to move: the row is the field's, the form drew it,
 * and the only thing appended to it is the invalid-value message that belongs to that field.
 *
 * @param {HTMLInputElement} input A form field.
 * @return {HTMLElement} The field's row, or the field itself when the form has no rows.
 */
const rowOf = (input) => input.closest('.fitem') || input.closest('.form-group') || input;

/**
 * Mark one colour field invalid, with its reason beside it, while it is being typed.
 *
 * The server refuses a value that is not a hex colour at save; this says so at the field, on
 * every keystroke and on blur, with the string the form's validation() answers with, so the two
 * cannot disagree. It is not a guard: nothing here stops a submission.
 *
 * An empty field is not invalid: it is the state of a field halfway through being cleared, and
 * the server reads it as "no value".
 *
 * The message element is created once per field, carries an id of its own and is named by the
 * input's aria-describedby only while it applies, so a screen reader reads the reason with the
 * field and is not left with a stale one attached to a value that is now fine.
 *
 * @param {HTMLInputElement} input The colour field.
 */
const markValidity = (input) => {
    const raw = (input.value || '').trim();
    const invalid = raw !== '' && !HEXRE.test(raw);
    const id = input.id + '-dim-invalid';
    let feedback = document.getElementById(id);
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.id = id;
        feedback.className = 'invalid-feedback';
        feedback.textContent = labels.invalidcolour;
        const fieldrow = rowOf(input);
        fieldrow.appendChild(feedback);
    }
    input.classList.toggle('is-invalid', invalid);
    /* Bootstrap shows .invalid-feedback only after an .is-invalid sibling, and the swatch moved
       the input into a span of its own, so that rule never matches here: d-block shows the
       message and Bootstrap's own display: none hides it again. The hidden attribute takes it
       out of the accessibility tree as well. */
    feedback.classList.toggle('d-block', invalid);
    feedback.hidden = !invalid;
    const described = (input.getAttribute('aria-describedby') || '')
        .split(' ')
        .filter((token) => token !== '' && token !== id);
    if (invalid) {
        described.push(id);
    }
    if (described.length) {
        input.setAttribute('aria-describedby', described.join(' '));
    } else {
        input.removeAttribute('aria-describedby');
    }
};

/**
 * Build and wire everything. Separated from init() so init can stay synchronous.
 *
 * @return {Promise<void>}
 */
const setup = async() => {
    const bginput = document.querySelector(SELECTORS.bg);
    const inkinput = document.querySelector(SELECTORS.ink);
    if (!bginput || !inkinput || bginput.dataset.dimContrast === '1') {
        return;
    }
    bginput.dataset.dimContrast = '1';

    await loadStrings();

    /* One accessible name each: two controls called "Colour picker" would be two identical names
       in the tab order, and the form gives the swatch no label saying which colour it paints. */
    decorate(bginput, labels.pickerbg, 'backgroundColor');
    decorate(inkinput, labels.pickerink, 'color');

    /* Typed and left: `input` says it while the value is being written, `blur` catches the
       reader who tabbed away from a half-typed one without touching it again. */
    [bginput, inkinput].forEach((input) => {
        const mark = () => markValidity(input);
        input.addEventListener('input', mark);
        input.addEventListener('blur', mark);
        mark();
    });

    const {html, js} = await Templates.renderForPromise(PANEL_TEMPLATE, {});
    const aside = document.createElement('div');
    aside.className = 'dim-contrast-aside';
    await Templates.replaceNodeContents(aside, html, js);
    const panel = aside.querySelector(SELECTORS.panel);
    if (!panel) {
        return;
    }
    /* The last child of the colours fieldset's container, the box the stylesheet draws the grid
       on: the reading order stays fields, preview, panel, and the CSS places the panel in the
       right-hand track. The container is core's (lib/form/templates/element-header.mustache
       renders `.fcontainer` inside the header's fieldset); without that section the panel is
       not mounted at all. */
    const fieldset = inkinput.closest('fieldset');
    const container = fieldset && fieldset.querySelector(SELECTORS.container);
    if (!container) {
        return;
    }
    container.appendChild(aside);
    fieldset.classList.add(SPLIT_CLASS);

    const panelcontroller = controller(panel, bginput, inkinput);
    [bginput, inkinput].forEach((input) => {
        input.addEventListener('input', panelcontroller.update);
        input.addEventListener('change', panelcontroller.update);
    });
    panelcontroller.update();
};

/**
 * Entry point: decorate the two colour fields and mount the contrast panel.
 */
export const init = () => {
    setup().catch(Notification.exception);
};
