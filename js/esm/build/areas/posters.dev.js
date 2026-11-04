var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The posters' result layer: what Areas.tsx writes onto the server's posters, and nobody else does.
 *
 * The posters are the server's markup
 * (category_directory_card) and stay it: the component never re-renders one. What changes with an answer -
 * the pill, the counts and the shell on the article, whether the poster opens (its control a button or the
 * link to its page), its open state, its item's place in the grid, the reveal and the "nothing anywhere"
 * box - is written here, by Areas.tsx alone, on both shells. Every write is made only when it changes something,
 * so a pass that agrees with the page invalidates no style.
 *
 * Two kinds of poster control. The logged-in shell's become buttons. The visitor's stay the links the server
 * drew (`links`), each announced as the button it acts as - its role, its state, the panel it controls - so a
 * press meant for another tab or window still goes to its address; the visitor's module points those
 * addresses at each area's panel under the address on screen, which is the one write on a poster that is not
 * this module's.
 *
 * @module     theme_dimensions/areas/posters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { CLASSES, PANEL_ID, SELECTORS } from "./model";
const PENDING = "\u2026";
const setHidden = /* @__PURE__ */ __name((element, hidden) => {
  if (element.hidden !== hidden) {
    element.hidden = hidden;
  }
}, "setHidden");
const setClass = /* @__PURE__ */ __name((element, name, on) => {
  if (element.classList.contains(name) !== on) {
    element.classList.toggle(name, on);
  }
}, "setClass");
const setAttr = /* @__PURE__ */ __name((element, name, value) => {
  if (element.getAttribute(name) === value) {
    return;
  }
  if (value === null) {
    element.removeAttribute(name);
  } else {
    element.setAttribute(name, value);
  }
}, "setAttr");
const posterOf = /* @__PURE__ */ __name((areas, id) => {
  if (!Number.isInteger(id) || id <= 0) {
    return null;
  }
  return areas.querySelector(`${SELECTORS.area}[data-categoryid="${id}"]`);
}, "posterOf");
const itemOf = /* @__PURE__ */ __name((poster) => poster.parentElement, "itemOf");
const shellOf = /* @__PURE__ */ __name((poster) => ({
  id: Number(poster.dataset.categoryid),
  name: poster.dataset.name || "",
  titleurl: poster.dataset.titleurl || "",
  ctaurl: poster.dataset.ctaurl || "",
  ctalabel: poster.dataset.ctalabel || ""
}), "shellOf");
const isExpandable = /* @__PURE__ */ __name((poster) => Boolean(poster) && poster?.dataset.expandable === "1", "isExpandable");
const isFiltered = /* @__PURE__ */ __name((areas) => areas.dataset.filtered === "1", "isFiltered");
const isZeroed = /* @__PURE__ */ __name((areas, poster) => Boolean(poster) && isFiltered(areas) && Number(poster?.dataset.count) === 0, "isZeroed");
const shownItems = /* @__PURE__ */ __name((grid, panel) => Array.from(grid.children).filter((el) => el !== panel && !el.hidden && el.querySelector(SELECTORS.area)), "shownItems");
const toButton = /* @__PURE__ */ __name((link, attributes) => {
  const button = document.createElement("button");
  button.type = "button";
  button.className = link.className;
  Object.keys(link.dataset).forEach((key) => {
    button.dataset[key] = link.dataset[key];
  });
  Object.keys(attributes).forEach((name) => button.setAttribute(name, attributes[name]));
  button.append(...Array.from(link.childNodes));
  link.replaceWith(button);
  return button;
}, "toButton");
const toLink = /* @__PURE__ */ __name((button, href) => {
  const link = document.createElement("a");
  link.className = button.className;
  link.href = href;
  Object.keys(button.dataset).forEach((key) => {
    link.dataset[key] = button.dataset[key];
  });
  const described = button.getAttribute("aria-describedby");
  if (described) {
    link.setAttribute("aria-describedby", described);
  }
  link.append(...Array.from(button.childNodes));
  button.replaceWith(link);
  return link;
}, "toLink");
const driven = /* @__PURE__ */ __name((poster, links) => {
  const control = poster ? poster.querySelector(SELECTORS.control) : null;
  if (!control) {
    return null;
  }
  if (control.tagName === "BUTTON") {
    return control;
  }
  return links && control.tagName === "A" && isExpandable(poster) ? control : null;
}, "driven");
const convertPoster = /* @__PURE__ */ __name((poster, open, links = false) => {
  const control = poster.querySelector(SELECTORS.control);
  if (!control || control.tagName === "BUTTON" || !isExpandable(poster)) {
    return;
  }
  const attributes = {
    "aria-expanded": Number(poster.dataset.categoryid) === open ? "true" : "false",
    "aria-controls": PANEL_ID
  };
  if (links) {
    attributes.role = "button";
    Object.keys(attributes).forEach((name) => setAttr(control, name, attributes[name]));
    return;
  }
  const described = control.getAttribute("aria-describedby");
  if (described) {
    attributes["aria-describedby"] = described;
  }
  toButton(control, attributes);
}, "convertPoster");
const setExpandable = /* @__PURE__ */ __name((poster, on, open, links = false) => {
  if (isExpandable(poster) === on || Number(poster.dataset.categoryid) === open) {
    return;
  }
  poster.dataset.expandable = on ? "1" : "0";
  setClass(poster, CLASSES.posterempty, !on);
  const note = poster.querySelector(SELECTORS.emptynote);
  if (note) {
    setHidden(note, on);
  }
  const trigger = poster.querySelector(SELECTORS.trigger);
  const word = trigger ? trigger.dataset[on ? "open" : "empty"] : "";
  if (trigger && word) {
    trigger.textContent = word;
  }
  const control = poster.querySelector(SELECTORS.control);
  const focused = Boolean(control) && document.activeElement === control;
  if (on) {
    convertPoster(poster, open, links);
  } else if (control && control.tagName === "BUTTON" && poster.dataset.titleurl) {
    toLink(control, poster.dataset.titleurl);
  } else if (control && links) {
    ["role", "aria-expanded", "aria-controls"].forEach((name) => setAttr(control, name, null));
  }
  const now = poster.querySelector(SELECTORS.control);
  if (focused && now && now !== control) {
    now.focus({ preventScroll: true });
  }
}, "setExpandable");
const setPosterOpen = /* @__PURE__ */ __name((poster, on, links = false) => {
  if (!poster) {
    return;
  }
  setClass(poster, CLASSES.posteropen, on);
  const control = driven(poster, links);
  if (control) {
    setAttr(control, "aria-expanded", on ? "true" : "false");
  }
  const trigger = poster.querySelector(SELECTORS.trigger);
  const word = trigger ? trigger.dataset[on ? "close" : "open"] : "";
  if (trigger && word && trigger.textContent !== word) {
    trigger.textContent = word;
  }
}, "setPosterOpen");
const unveil = /* @__PURE__ */ __name((poster) => {
  const reveal = poster.closest(SELECTORS.more);
  if (reveal) {
    setHidden(reveal, false);
    if (!reveal.open) {
      reveal.open = true;
    }
  }
  setHidden(itemOf(poster), false);
}, "unveil");
const pending = /* @__PURE__ */ __name((areas, filtered) => {
  areas.querySelectorAll(SELECTORS.grid).forEach((grid) => setAttr(grid, "aria-busy", "true"));
  if (!filtered) {
    return;
  }
  areas.querySelectorAll(SELECTORS.pill).forEach((pill) => {
    pill.textContent = PENDING;
    setHidden(pill, false);
    setClass(pill, CLASSES.pillzero, false);
  });
}, "pending");
const paintPill = /* @__PURE__ */ __name((poster, filtered) => {
  const pill = poster.querySelector(SELECTORS.pill);
  const label = poster.dataset.resultlabel || "";
  const has = filtered && Boolean(pill) && label !== "";
  if (pill) {
    if (pill.textContent !== label) {
      pill.textContent = label;
    }
    setHidden(pill, !has);
    setClass(pill, CLASSES.pillzero, has && Number(poster.dataset.count) === 0);
  }
  const control = poster.querySelector(SELECTORS.control);
  if (control) {
    setAttr(control, "aria-describedby", has && pill ? pill.id : null);
  }
}, "paintPill");
const failed = /* @__PURE__ */ __name((areas) => {
  areas.querySelectorAll(SELECTORS.grid).forEach((grid) => setAttr(grid, "aria-busy", null));
  areas.querySelectorAll(SELECTORS.area).forEach((poster) => paintPill(poster, isFiltered(areas)));
}, "failed");
const paintPosters = /* @__PURE__ */ __name((areas, accordion, filtered, open, expandable, links = false) => {
  if (!filtered) {
    const named = new Set(accordion.map((entry) => Number(entry.id)));
    areas.querySelectorAll(SELECTORS.area).forEach((poster) => {
      if (named.has(Number(poster.dataset.categoryid))) {
        return;
      }
      poster.dataset.resultcount = "";
      poster.dataset.resultlabel = "";
      paintPill(poster, false);
      setHidden(itemOf(poster), false);
    });
  }
  accordion.forEach((entry) => {
    const id = Number(entry.id);
    const poster = posterOf(areas, id);
    if (!poster) {
      return;
    }
    const count = Number(entry.count) || 0;
    poster.dataset.count = String(count);
    poster.dataset.resultcount = entry.resultcount || "";
    poster.dataset.resultlabel = entry.resultlabel || "";
    poster.dataset.ctaurl = entry.ctaurl || "";
    poster.dataset.ctalabel = entry.ctalabel || "";
    paintPill(poster, filtered);
    expandable.set(id, Boolean(entry.expandable));
    setExpandable(poster, Boolean(entry.expandable), open, links);
    setHidden(itemOf(poster), filtered && count === 0 && id !== open);
  });
}, "paintPosters");
const fitReveal = /* @__PURE__ */ __name((areas) => {
  const more = areas.querySelector(SELECTORS.more);
  if (!more) {
    return;
  }
  const shown = Array.from(more.querySelectorAll(SELECTORS.area)).some((poster) => !itemOf(poster).hidden);
  setHidden(more, isFiltered(areas) && !shown);
}, "fitReveal");
const paintReveal = /* @__PURE__ */ __name((areas, word) => {
  const more = areas.querySelector(SELECTORS.more);
  if (!more) {
    return;
  }
  const summary = more.querySelector(SELECTORS.moresummary);
  if (summary && word.morelabel && summary.textContent !== word.morelabel) {
    summary.textContent = word.morelabel;
  }
  fitReveal(areas);
  if (Boolean(word.filtered) && Number(word.moreresults) > 0 && !more.open) {
    more.open = true;
  }
}, "paintReveal");
const paintAllEmpty = /* @__PURE__ */ __name((areas, allempty, open) => {
  const box = areas.querySelector(SELECTORS.allempty);
  if (box) {
    setHidden(box, !(allempty && !open));
  }
}, "paintAllEmpty");
const paintSearchAll = /* @__PURE__ */ __name((areas, url) => {
  const link = areas.querySelector(SELECTORS.searchall);
  if (!link) {
    return;
  }
  if (url) {
    setAttr(link, "href", url);
  }
  setHidden(link, !url);
}, "paintSearchAll");
const loadedAllEmpty = /* @__PURE__ */ __name((areas, root) => {
  const box = areas.querySelector(SELECTORS.allempty);
  if (!box || !isFiltered(areas)) {
    return false;
  }
  if (!box.hidden) {
    return true;
  }
  const direct = document.querySelector(SELECTORS.direct);
  const nonehere = root || Boolean(direct) && Boolean(direct?.hidden);
  const posters = Array.from(areas.querySelectorAll(SELECTORS.area));
  return nonehere && posters.every((poster) => Number(poster.dataset.count) === 0);
}, "loadedAllEmpty");
export {
  PENDING,
  convertPoster,
  driven,
  failed,
  fitReveal,
  isExpandable,
  isFiltered,
  isZeroed,
  itemOf,
  loadedAllEmpty,
  paintAllEmpty,
  paintPill,
  paintPosters,
  paintReveal,
  paintSearchAll,
  pending,
  posterOf,
  setAttr,
  setClass,
  setExpandable,
  setHidden,
  setPosterOpen,
  shellOf,
  shownItems,
  toButton,
  toLink,
  unveil
};
//# sourceMappingURL=posters.dev.js.map
