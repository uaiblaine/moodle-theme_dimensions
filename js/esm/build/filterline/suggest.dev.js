var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The filter line's suggestions: which filters a text offers, and in what order.
 *
 * The suggestions are the filters already on the page - every scope cell, the favourites star, every option
 * of the Filters panel and its quick dates - whose label or group matches the typed text, accent- and
 * case-insensitively, word starts first and then by count, each with its group and a count the page has
 * shown for it.
 *
 * That count is the server's last answer given while the field held no term ({@see remember}), so a term
 * typed since - which the client searches as it is typed, and which zeroes the options of a word that
 * names no course - neither changes nor hides a suggestion. A page opened on an address that already
 * carries a term has no such answer and offers the counts it shows, which include the term: the count
 * without the term is the server's to answer, and asking it would cost a request per keystroke.
 *
 * @module     theme_dimensions/filterline/suggest
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { normalise } from "./mark";
import { TIP } from "./model";
const MIN_TERM = 2;
const START_COUNT = 5;
const MATCH_COUNT = 8;
const SELECTORS = {
  group: '[data-region="dim-vitrine-group"]',
  pillcount: '[data-region="dim-pill-count"]',
  star: '[data-region="dim-line-star"]',
  favcount: '[data-region="dim-fav-count"]',
  date: '[data-region="dim-date"]',
  quick: '[data-action="dim-quick"]'
};
const matchOf = /* @__PURE__ */ __name((text, term) => {
  let at = text.indexOf(term);
  if (at < 0) {
    return -1;
  }
  while (at >= 0) {
    if (at === 0 || !/[a-z0-9]/.test(text.charAt(at - 1))) {
      return 0;
    }
    at = text.indexOf(term, at + 1);
  }
  return 1;
}, "matchOf");
const rankOf = /* @__PURE__ */ __name((item, term) => {
  const label = matchOf(normalise(item.label), term);
  const group = matchOf(normalise(item.group), term);
  if (label === 0) {
    return 0;
  }
  if (group === 0) {
    return 1;
  }
  if (label === 1) {
    return 2;
  }
  return group === 1 ? 3 : -1;
}, "rankOf");
const suggest = /* @__PURE__ */ __name((items, text) => {
  const term = normalise(text);
  const count = /* @__PURE__ */ __name((item) => item.count === null ? -1 : item.count, "count");
  if (term.length < MIN_TERM) {
    return items.slice().sort((a, b) => count(b) - count(a) || a.order - b.order).slice(0, START_COUNT);
  }
  return items.map((item) => ({ item, rank: rankOf(item, term) })).filter((entry) => entry.rank >= 0).sort((a, b) => a.rank - b.rank || count(b.item) - count(a.item) || a.item.order - b.item.order).slice(0, MATCH_COUNT).map((entry) => entry.item);
}, "suggest");
const wordsOf = /* @__PURE__ */ __name((label) => Array.from(label.childNodes).filter((node) => node.nodeType === Node.TEXT_NODE).map((node) => node.textContent || "").join("").trim(), "wordsOf");
const countOf = /* @__PURE__ */ __name((label) => {
  const count = label.querySelector(SELECTORS.pillcount);
  if (!count || count.hidden || (count.textContent || "").trim() === "") {
    return null;
  }
  return Number(count.textContent);
}, "countOf");
const legendOf = /* @__PURE__ */ __name((fieldset) => {
  const legend = fieldset ? fieldset.querySelector("legend") : null;
  return legend ? (legend.textContent || "").trim() : "";
}, "legendOf");
const eachRadio = /* @__PURE__ */ __name((form, each) => {
  form.querySelectorAll(SELECTORS.group).forEach((fieldset) => {
    fieldset.querySelectorAll('input[type="radio"]').forEach((input) => {
      const label = fieldset.querySelector(`label[for="${input.id}"]`);
      if (label) {
        each(input, label, fieldset);
      }
    });
  });
}, "eachRadio");
const remember = /* @__PURE__ */ __name((form, text, rest) => {
  if (normalise(text) !== "") {
    return;
  }
  eachRadio(form, (input, label, fieldset) => {
    rest.set(input, { count: countOf(label), hidden: fieldset.hidden || input.hidden || label.hidden });
  });
  const star = form.querySelector(SELECTORS.star);
  const count = star ? star.querySelector(SELECTORS.favcount) : null;
  if (star && count) {
    rest.set(star, { count: Number(count.textContent), hidden: false });
  }
}, "remember");
const offers = /* @__PURE__ */ __name((form, rest) => {
  const found = [];
  const add = /* @__PURE__ */ __name((control, label, group, count) => {
    if (label !== "" && count !== 0) {
      found.push({ control, label, group, count, order: found.length });
    }
  }, "add");
  eachRadio(form, (input, label, fieldset) => {
    if (input.checked || input.value === "" || input.value === "0") {
      return;
    }
    const known = rest.get(input) || { count: countOf(label), hidden: input.hidden || label.hidden };
    if (!known.hidden) {
      add(input, wordsOf(label), legendOf(fieldset), known.count);
    }
  });
  const star = form.querySelector(SELECTORS.star);
  if (star && star.getAttribute("aria-pressed") !== "true") {
    const count = star.querySelector(SELECTORS.favcount);
    const known = rest.get(star) || { count: count ? Number(count.textContent) : null, hidden: false };
    add(star, star.getAttribute(TIP) || "", "", known.count);
  }
  const date = form.querySelector(SELECTORS.date);
  if (date) {
    date.querySelectorAll(SELECTORS.quick).forEach((link) => {
      if (!link.hasAttribute("aria-current")) {
        add(link, (link.textContent || "").trim(), legendOf(date), null);
      }
    });
  }
  return found;
}, "offers");
export {
  MATCH_COUNT,
  MIN_TERM,
  START_COUNT,
  countOf,
  matchOf,
  offers,
  rankOf,
  remember,
  suggest,
  wordsOf
};
//# sourceMappingURL=suggest.dev.js.map
