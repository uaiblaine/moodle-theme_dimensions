var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The panel between the poster rows, on both shells: one area at a time, its place, its address, its
 * orders, its rail, its keyboard and its states, behind Areas.tsx.
 *
 * The visitor's half is a set of options ({@see Options}): posters that stay links, a question asked once per
 * opening after a quiet, a failure the page takes over, and no automatic opening. The state is read and written
 * synchronously; what React draws from it (the summary of the areas with no result) is told through `ui`.
 *
 * The panel's element - the one list item of the grid the server always writes - is this module's frame: its
 * hidden state, its three transition classes (only through {@see settle}), the five integers of its row and the
 * open area's colour on its style, and its place in the DOM. Its content is category_panel rendered by
 * core/templates and inserted whole ({@see renderWhole}) or region by region ({@see transplant}), never diffed.
 * The cards in it keep their own modules (the film, the dialogue, and on the logged-in shell what an account
 * adds, painted on `painted`).
 *
 * Layout: the row (the five integers) and the DOM order (after the last poster of the open one's row) are both
 * counted from the posters shown and the grid's tracks (matchMedia and the view class), never read off a box.
 * The reads left, each said where it is made: a move from one open area to another reads the pressed poster's
 * top before and after, to keep it under the pointer; the scroll rule reads the panel's box and the navbar's
 * height once the opening has settled; a style read (no layout) fixes the shut state before the grow; the rail
 * reads its scroll from a ResizeObserver entry and from its own scroll events. Nothing is measured at mount.
 *
 * @module     theme_dimensions/areas/panel
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Pending from "@moodle/lms/core/pending";
import { load } from "../amd";
import { markTitles } from "./mark";
import {
  BODY_CLASS,
  CLASSES,
  MOTION,
  MOTION_BASE,
  MOTION_FAST,
  PHONE,
  PSORT_NAME,
  SCROLL_END,
  SCROLL_GAP,
  SELECTORS,
  SETTLE,
  SKELETON_DELAY,
  TEMPLATE,
  WIDE,
  addressWith,
  announcement,
  answerContext,
  columns,
  holderHref,
  lastOfRow,
  placement,
  readPanel,
  sameRow,
  shellContext,
  suggestions,
  summaryOf,
  termOf
} from "./model";
import {
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
  unveil
} from "./posters";
import { dragRail, paintRail, railState, scrollRail } from "./rail";
import { AREAS, LISTING } from "../listing/events";
import { replaceKeeping } from "../listing/history";
const EVENTS = { ...AREAS, ...LISTING };
const optionsOf = /* @__PURE__ */ __name((props) => ({
  links: props.links === true,
  quiet: typeof props.quiet === "number" && Number.isInteger(props.quiet) && props.quiet > 0 ? props.quiet : 0,
  autoopen: props.autoopen !== false
}), "optionsOf");
const drawnOpen = /* @__PURE__ */ __name((nodes) => {
  const section = nodes.panel.querySelector(SELECTORS.section);
  const id = section ? Number(section.dataset.categoryid) : 0;
  return !nodes.panel.hidden && isExpandable(posterOf(nodes.areas, id)) ? id : 0;
}, "drawnOpen");
const elsewhere = /* @__PURE__ */ __name((event) => event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey, "elsewhere");
const createPanel = /* @__PURE__ */ __name((nodes, ui, mode = optionsOf({})) => {
  const { areas, panel, status } = nodes;
  const st = {
    open: 0,
    psort: "",
    openedby: "",
    ticket: 0,
    asked: "",
    askedticket: -1,
    turn: 0,
    paint: 0,
    skeleton: 0,
    allempty: false,
    expandable: /* @__PURE__ */ new Map(),
    dragged: false,
    armed: false
  };
  let templates = null;
  let railobserver = null;
  const renderer = /* @__PURE__ */ __name(() => {
    templates = templates || load("core/templates");
    return templates;
  }, "renderer");
  const reduced = /* @__PURE__ */ __name(() => window.matchMedia(MOTION).matches, "reduced");
  const isRoot = /* @__PURE__ */ __name(() => Number(areas.dataset.categoryid) === 0, "isRoot");
  const tracks = /* @__PURE__ */ __name(() => {
    const listing = areas.closest(SELECTORS.listing);
    return columns(
      Boolean(listing) && Boolean(listing?.classList.contains(CLASSES.list)),
      window.matchMedia(PHONE).matches,
      window.matchMedia(WIDE).matches
    );
  }, "tracks");
  const arm = /* @__PURE__ */ __name(() => {
    if (!st.armed) {
      st.armed = true;
      document.body.classList.add(BODY_CLASS);
    }
  }, "arm");
  const settle = /* @__PURE__ */ __name((...classes) => {
    panel.classList.remove(CLASSES.shut, CLASSES.still, CLASSES.fading);
    panel.classList.add(...classes);
  }, "settle");
  const ask = /* @__PURE__ */ __name((poster, psort) => {
    const detail = { poster, psort, answer: null };
    areas.dispatchEvent(new CustomEvent(EVENTS.ask, { detail }));
    return detail.answer || Promise.reject(new Error("Nothing on the page answers the panel."));
  }, "ask");
  const question = /* @__PURE__ */ __name((poster, psort) => {
    const detail = { poster, psort, question: "" };
    areas.dispatchEvent(new CustomEvent(EVENTS.question, { detail }));
    return typeof detail.question === "string" ? detail.question : "";
  }, "question");
  const answered = /* @__PURE__ */ __name((asking) => asking !== "" && asking === st.asked && st.askedticket === st.ticket, "answered");
  const quiet = /* @__PURE__ */ __name(async (ticket) => {
    if (mode.quiet > 0) {
      await new Promise((resolve) => window.setTimeout(resolve, mode.quiet));
    }
    return ticket === st.ticket;
  }, "quiet");
  const unanswered = /* @__PURE__ */ __name((poster, psort) => {
    const detail = { poster, psort, taken: false };
    areas.dispatchEvent(new CustomEvent(EVENTS.unanswered, { detail }));
    return detail.taken === true;
  }, "unanswered");
  const painted = /* @__PURE__ */ __name(() => {
    areas.dispatchEvent(new CustomEvent(EVENTS.painted, { detail: { panel } }));
  }, "painted");
  const observeRail = /* @__PURE__ */ __name(() => {
    const rail = panel.querySelector(SELECTORS.rail);
    if (railobserver === null && typeof ResizeObserver !== "undefined") {
      railobserver = new ResizeObserver((entries) => {
        const target = entries[entries.length - 1].target;
        if (panel.contains(target)) {
          paintRail(panel, railState(target));
        }
      });
    }
    railobserver?.disconnect();
    if (rail) {
      railobserver?.observe(rail);
    }
  }, "observeRail");
  const onScroll = /* @__PURE__ */ __name((event) => {
    if (event.target instanceof HTMLElement && event.target.matches(SELECTORS.rail)) {
      paintRail(panel, railState(event.target));
    }
  }, "onScroll");
  const moveAfter = /* @__PURE__ */ __name((node, ref) => {
    const parent = ref.parentElement;
    const next = ref.nextElementSibling;
    if (next === node || node === ref) {
      return;
    }
    const active = document.activeElement;
    const inside = Boolean(active) && node.contains(active);
    if (typeof parent.moveBefore === "function") {
      try {
        parent.moveBefore(node, next);
        return;
      } catch (error) {
      }
    }
    parent.insertBefore(node, next);
    if (inside && active && document.activeElement !== active) {
      active.focus({ preventScroll: true });
    }
  }, "moveAfter");
  const place = /* @__PURE__ */ __name(() => {
    const poster = posterOf(areas, st.open);
    if (!poster) {
      return;
    }
    const item = itemOf(poster);
    const grid = item.parentElement;
    if (panel.parentElement !== grid) {
      moveAfter(panel, item);
    }
    const items = shownItems(grid, panel);
    const index = Math.max(0, items.indexOf(item));
    const integers = placement(index);
    Object.keys(integers).forEach((name) => {
      if (panel.style.getPropertyValue(name).trim() !== integers[name]) {
        panel.style.setProperty(name, integers[name]);
      }
    });
    const band = poster.style.getPropertyValue("--dim-card-band").trim();
    if (band !== "" && panel.style.getPropertyValue("--dim-area-bg").trim() !== band) {
      panel.style.setProperty("--dim-area-bg", band);
    }
    moveAfter(panel, items[lastOfRow(index, items.length, tracks())] || item);
  }, "place");
  const ensureVisible = /* @__PURE__ */ __name(() => new Promise((resolve) => {
    if (!st.open || panel.hidden) {
      resolve();
      return;
    }
    const rect = panel.getBoundingClientRect();
    const bar = parseFloat(window.getComputedStyle(areas).getPropertyValue("--dim-navbar")) || 0;
    if (rect.bottom > window.innerHeight || rect.top < bar) {
      const done = /* @__PURE__ */ __name(() => {
        window.clearTimeout(timer);
        window.removeEventListener("scrollend", done);
        resolve();
      }, "done");
      const timer = window.setTimeout(done, SCROLL_END);
      window.addEventListener("scrollend", done);
      window.scrollBy({ top: rect.top - (bar + SCROLL_GAP), behavior: reduced() ? "auto" : "smooth" });
      return;
    }
    resolve();
  }), "ensureVisible");
  const announce = /* @__PURE__ */ __name((text) => {
    if (!status) {
      return;
    }
    status.textContent = "";
    window.requestAnimationFrame(() => {
      status.textContent = text;
    });
  }, "announce");
  const renderWhole = /* @__PURE__ */ __name(async (context, turn) => {
    const engine = await renderer();
    const rendered = await engine.renderForPromise(TEMPLATE, context);
    if (turn !== st.turn) {
      return false;
    }
    engine.replaceNodeContents(panel, rendered.html, rendered.js);
    observeRail();
    return true;
  }, "renderWhole");
  const transplant = /* @__PURE__ */ __name(async (context, ticket) => {
    const paint = ++st.paint;
    const engine = await renderer();
    const rendered = await engine.renderForPromise(TEMPLATE, context);
    if (ticket !== st.ticket || paint !== st.paint) {
      return false;
    }
    const holder = document.createElement("div");
    engine.appendNodeContents(holder, rendered.html, rendered.js);
    const active = document.activeElement;
    const inside = Boolean(active) && panel.contains(active);
    const key = inside && active ? active.dataset.focuskey || "" : "";
    const subtitle = panel.querySelector(SELECTORS.subtitle);
    const freshsubtitle = holder.querySelector(SELECTORS.subtitle);
    if (subtitle && freshsubtitle) {
      subtitle.textContent = freshsubtitle.textContent;
    }
    [SELECTORS.also, SELECTORS.body, SELECTORS.foot].forEach((selector) => {
      const current = panel.querySelector(selector);
      const fresh = holder.querySelector(selector);
      if (current && fresh) {
        current.replaceWith(fresh);
      }
    });
    if (inside && !panel.contains(document.activeElement)) {
      const keyed = key !== "" ? panel.querySelector(`[data-focuskey="${key}"]`) : null;
      const target = keyed || panel.querySelector(SELECTORS.title);
      target?.focus({ preventScroll: true });
    }
    observeRail();
    return true;
  }, "transplant");
  const setSortBusy = /* @__PURE__ */ __name((busy) => {
    panel.querySelectorAll(SELECTORS.sortoption).forEach((option) => setAttr(option, "aria-disabled", busy ? "true" : "false"));
  }, "setSortBusy");
  const paintSort = /* @__PURE__ */ __name(() => {
    panel.querySelectorAll(SELECTORS.sortoption).forEach((option) => {
      const on = option.dataset.psort === PSORT_NAME === (st.psort === PSORT_NAME);
      setAttr(option, "aria-checked", on ? "true" : "false");
      option.tabIndex = on ? 0 : -1;
    });
  }, "paintSort");
  const offered = /* @__PURE__ */ __name(() => suggestions(
    Array.from(areas.querySelectorAll(SELECTORS.area)).map((poster) => ({
      id: Number(poster.dataset.categoryid),
      name: poster.dataset.name || "",
      count: Number(poster.dataset.count),
      resultcount: poster.dataset.resultcount || "",
      resultlabel: poster.dataset.resultlabel || ""
    })),
    st.open,
    isFiltered(areas)
  ), "offered");
  const request = /* @__PURE__ */ __name(async () => {
    const id = st.open;
    const poster = posterOf(areas, id);
    if (!poster) {
      return;
    }
    const asking = question(poster, st.psort);
    if (answered(asking)) {
      return;
    }
    arm();
    const ticket = ++st.ticket;
    st.asked = asking;
    st.askedticket = ticket;
    const held = new Pending("theme_dimensions/areas:request");
    window.clearTimeout(st.skeleton);
    setSortBusy(true);
    st.skeleton = window.setTimeout(() => {
      if (ticket === st.ticket) {
        transplant(shellContext(shellOf(poster), st.psort, window.location.href, { isskeleton: true }), ticket);
      }
    }, SKELETON_DELAY + mode.quiet);
    try {
      if (!await quiet(ticket)) {
        return;
      }
      const response = await ask(poster, st.psort);
      if (ticket !== st.ticket) {
        return;
      }
      window.clearTimeout(st.skeleton);
      const context = answerContext(response, id, offered());
      if (!await transplant(context, ticket)) {
        return;
      }
      markTitles(panel, termOf(window.location.href));
      const body = panel.querySelector(SELECTORS.body);
      announce(announcement(context, body ? body.textContent || "" : "", areas.dataset.elsewhere || ""));
      painted();
    } catch (error) {
      if (ticket !== st.ticket) {
        return;
      }
      window.clearTimeout(st.skeleton);
      st.asked = "";
      if (unanswered(poster, st.psort)) {
        return;
      }
      if (!await transplant(shellContext(shellOf(poster), st.psort, window.location.href, { iserror: true }), ticket)) {
        return;
      }
      const subtitle = panel.querySelector(SELECTORS.subtitle);
      announce(subtitle ? (subtitle.textContent || "").trim() : "");
    } finally {
      if (ticket === st.ticket) {
        setSortBusy(false);
      }
      held.resolve();
    }
  }, "request");
  const syncField = /* @__PURE__ */ __name((form, selector, name, value) => {
    if (!form) {
      return;
    }
    let input = form.querySelector(selector);
    if (value === "") {
      input?.remove();
      return;
    }
    if (!input) {
      input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.dataset.region = selector === SELECTORS.openfield ? "dim-open-field" : "dim-psort-field";
      form.prepend(input);
    }
    input.value = value;
  }, "syncField");
  const syncHolders = /* @__PURE__ */ __name(() => {
    const open = st.open > 0 ? String(st.open) : "";
    const psort = st.open > 0 && st.psort === PSORT_NAME ? PSORT_NAME : "";
    [document.querySelector(SELECTORS.toolbar), document.querySelector(SELECTORS.fold)].forEach((form) => {
      syncField(form, SELECTORS.openfield, "open", open);
      syncField(form, SELECTORS.psortfield, "psort", psort);
    });
    const links = [document.querySelector(SELECTORS.clear)].concat(Array.from(document.querySelectorAll(SELECTORS.chip)));
    links.forEach((link) => {
      const href = link ? link.getAttribute("href") : null;
      if (link && href) {
        link.href = holderHref(href, window.location.href, st.open, st.psort);
      }
    });
  }, "syncHolders");
  const writeAddress = /* @__PURE__ */ __name(() => {
    replaceKeeping(addressWith(window.location.href, st.open, st.psort));
    syncHolders();
  }, "writeAddress");
  const focusTitle = /* @__PURE__ */ __name(() => {
    panel.querySelector(SELECTORS.title)?.focus({ preventScroll: true });
  }, "focusTitle");
  const focusSummary = /* @__PURE__ */ __name(() => {
    areas.querySelector(SELECTORS.nomatchsummary)?.focus({ preventScroll: true });
  }, "focusSummary");
  const applyExpandable = /* @__PURE__ */ __name((poster) => {
    const id = poster ? Number(poster.dataset.categoryid) : 0;
    if (poster && st.expandable.has(id)) {
      setExpandable(poster, Boolean(st.expandable.get(id)), st.open, mode.links);
    }
  }, "applyExpandable");
  const recountSummary = /* @__PURE__ */ __name(() => {
    ui.sync(() => ui.opened(st.open));
  }, "recountSummary");
  const sharesRow = /* @__PURE__ */ __name((prev, poster) => {
    if (!prev || isZeroed(areas, prev) || panel.hidden) {
      return false;
    }
    const grid = itemOf(poster).parentElement;
    if (itemOf(prev).parentElement !== grid) {
      return false;
    }
    const items = shownItems(grid, panel);
    return sameRow(items.indexOf(itemOf(prev)), items.indexOf(itemOf(poster)), tracks());
  }, "sharesRow");
  const crossfade = /* @__PURE__ */ __name((shell, turn, focus) => {
    place();
    settle(CLASSES.fading);
    const held = new Pending("theme_dimensions/areas:crossfade");
    window.setTimeout(async () => {
      try {
        if (turn !== st.turn || !await renderWhole(shell, turn)) {
          return;
        }
        panel.classList.remove(CLASSES.fading);
        if (focus) {
          focusTitle();
        }
        request();
      } finally {
        held.resolve();
      }
    }, MOTION_FAST);
  }, "crossfade");
  const moveTo = /* @__PURE__ */ __name(async (poster, prev, before, turn, shell, options) => {
    settle(CLASSES.still, CLASSES.shut);
    setHidden(panel, false);
    if (!await renderWhole(shell, turn)) {
      return;
    }
    place();
    if (prev) {
      const after = poster.getBoundingClientRect().top;
      if (Math.abs(after - before) > 1) {
        window.scrollBy(0, after - before);
      }
    }
    void window.getComputedStyle(panel).opacity;
    window.requestAnimationFrame(() => {
      if (turn !== st.turn) {
        return;
      }
      panel.classList.remove(CLASSES.still);
      window.requestAnimationFrame(() => {
        if (turn === st.turn) {
          panel.classList.remove(CLASSES.shut);
        }
      });
    });
    if (options.focus) {
      focusTitle();
    }
    request();
    if (options.scroll !== false) {
      const scroll = new Pending("theme_dimensions/areas:scroll");
      window.setTimeout(async () => {
        try {
          await ensureVisible();
        } finally {
          scroll.resolve();
        }
      }, (reduced() ? 0 : MOTION_BASE) + SETTLE);
    }
  }, "moveTo");
  const openArea = /* @__PURE__ */ __name(async (id, options) => {
    const poster = posterOf(areas, id);
    if (!poster || !isExpandable(poster) || id === st.open) {
      return;
    }
    arm();
    unveil(poster);
    const prev = st.open ? posterOf(areas, st.open) : null;
    const before = prev ? poster.getBoundingClientRect().top : 0;
    const prevhides = isZeroed(areas, prev);
    const samerow = sharesRow(prev, poster);
    setPosterOpen(prev, false, mode.links);
    setPosterOpen(poster, true, mode.links);
    if (prev && prevhides) {
      setHidden(itemOf(prev), true);
      fitReveal(areas);
    }
    st.open = id;
    st.openedby = options.auto ? "auto" : "user";
    st.psort = options.psort || "";
    st.ticket += 1;
    const turn = ++st.turn;
    applyExpandable(prev);
    recountSummary();
    paintAllEmpty(areas, st.allempty, st.open);
    setClass(areas, CLASSES.areasopen, true);
    if (!options.auto) {
      const hint = areas.querySelector(SELECTORS.hint);
      if (hint) {
        setHidden(hint, true);
      }
    }
    if (options.write !== false) {
      writeAddress();
    }
    const shell = shellContext(shellOf(poster), st.psort, window.location.href, { isloading: true });
    if (samerow && !reduced()) {
      crossfade(shell, turn, Boolean(options.focus));
      return;
    }
    await moveTo(poster, prev, before, turn, shell, options);
  }, "openArea");
  const closeArea = /* @__PURE__ */ __name((focus, write) => {
    if (!st.open) {
      return;
    }
    arm();
    const poster = posterOf(areas, st.open);
    const zeroed = isZeroed(areas, poster);
    setPosterOpen(poster, false, mode.links);
    st.open = 0;
    st.openedby = "";
    st.psort = "";
    st.ticket += 1;
    st.turn += 1;
    window.clearTimeout(st.skeleton);
    if (poster && zeroed) {
      setHidden(itemOf(poster), true);
      fitReveal(areas);
    }
    applyExpandable(poster);
    recountSummary();
    paintAllEmpty(areas, st.allempty, st.open);
    setClass(areas, CLASSES.areasopen, false);
    settle(CLASSES.shut);
    const hide = /* @__PURE__ */ __name(() => {
      if (!st.open) {
        setHidden(panel, true);
        panel.replaceChildren();
        railobserver?.disconnect();
      }
    }, "hide");
    if (reduced()) {
      hide();
    } else {
      window.setTimeout(hide, MOTION_BASE);
    }
    if (status) {
      status.textContent = "";
    }
    if (write) {
      writeAddress();
    }
    const control = poster ? poster.querySelector(SELECTORS.control) : null;
    if (focus && zeroed) {
      focusSummary();
    } else if (focus && control) {
      control.focus({ preventScroll: true });
    }
  }, "closeArea");
  const setPsort = /* @__PURE__ */ __name((psort) => {
    const value = psort === PSORT_NAME ? PSORT_NAME : "";
    if (!st.open || st.psort === value || panel.querySelector(`${SELECTORS.sortoption}[aria-disabled="true"]`)) {
      return;
    }
    st.psort = value;
    paintSort();
    writeAddress();
    request();
  }, "setPsort");
  const toggleArea = /* @__PURE__ */ __name((poster) => {
    const id = Number(poster.dataset.categoryid);
    if (id === st.open) {
      closeArea(true, true);
    } else {
      openArea(id, { focus: true });
    }
  }, "toggleArea");
  const onAreasClick = /* @__PURE__ */ __name((event) => {
    const control = event.target instanceof Element ? event.target.closest(SELECTORS.control) : null;
    if (!control || panel.contains(control)) {
      return;
    }
    const poster = control.closest(SELECTORS.area);
    if (!poster || !isExpandable(poster) || driven(poster, mode.links) !== control) {
      return;
    }
    if (control.tagName === "A" && elsewhere(event)) {
      return;
    }
    event.preventDefault();
    toggleArea(poster);
  }, "onAreasClick");
  const onSpace = /* @__PURE__ */ __name((event) => {
    if (event.key !== " " || event.repeat || !mode.links) {
      return false;
    }
    const control = event.target instanceof Element ? event.target.closest(SELECTORS.control) : null;
    const poster = control ? control.closest(SELECTORS.area) : null;
    if (!control || !poster || control.tagName !== "A" || panel.contains(control) || driven(poster, true) !== control) {
      return false;
    }
    event.preventDefault();
    toggleArea(poster);
    return true;
  }, "onSpace");
  const onPanelClick = /* @__PURE__ */ __name((event) => {
    if (!(event.target instanceof Element)) {
      return;
    }
    const target = event.target;
    const option = target.closest(SELECTORS.sortoption);
    if (option && option.tagName === "BUTTON") {
      event.preventDefault();
      setPsort(option.dataset.psort || "");
      return;
    }
    const suggestion = target.closest(SELECTORS.openarea);
    if (suggestion && suggestion.tagName === "BUTTON") {
      event.preventDefault();
      openArea(Number(suggestion.dataset.categoryid), { focus: true });
      return;
    }
    const close = target.closest(SELECTORS.close);
    if (close && close.tagName === "BUTTON") {
      event.preventDefault();
      closeArea(true, true);
      return;
    }
    if (target.closest(SELECTORS.retry)) {
      event.preventDefault();
      request();
      return;
    }
    if (target.closest(SELECTORS.prev)) {
      scrollRail(panel, -1, reduced());
      return;
    }
    if (target.closest(SELECTORS.next)) {
      scrollRail(panel, 1, reduced());
    }
  }, "onPanelClick");
  const onDragClick = /* @__PURE__ */ __name((event) => {
    if (st.dragged) {
      event.preventDefault();
      event.stopPropagation();
      st.dragged = false;
    }
  }, "onDragClick");
  const onPointerDown = /* @__PURE__ */ __name((event) => {
    dragRail(event, () => {
      st.dragged = true;
      window.setTimeout(() => {
        st.dragged = false;
      }, 0);
    });
  }, "onPointerDown");
  const onDragStart = /* @__PURE__ */ __name((event) => {
    if (event.target instanceof Element && event.target.closest(SELECTORS.rail)) {
      event.preventDefault();
    }
  }, "onDragStart");
  const onKeydown = /* @__PURE__ */ __name((event) => {
    if (event.key === "Escape" && st.open) {
      const active = document.activeElement;
      if (!(active instanceof Element) || active.matches(SELECTORS.search) || active.closest(SELECTORS.covercontrols)) {
        return;
      }
      const poster = posterOf(areas, st.open);
      if (panel.contains(active) || poster && poster.contains(active)) {
        event.preventDefault();
        closeArea(true, true);
      }
      return;
    }
    if (onSpace(event)) {
      return;
    }
    const option = event.target instanceof Element ? event.target.closest(SELECTORS.sortoption) : null;
    const arrows = ["ArrowLeft", "ArrowRight", "ArrowUp", "ArrowDown"];
    if (option && option.tagName === "BUTTON" && arrows.indexOf(event.key) >= 0) {
      event.preventDefault();
      setPsort(st.psort === PSORT_NAME ? "" : PSORT_NAME);
      panel.querySelector(`${SELECTORS.sortoption}[aria-checked="true"]`)?.focus();
    }
  }, "onKeydown");
  const onPopState = /* @__PURE__ */ __name(() => {
    const { open: id, psort } = readPanel(window.location.href);
    if (id === st.open && psort === st.psort) {
      return;
    }
    if (id === 0) {
      const active = document.activeElement;
      const open = posterOf(areas, st.open);
      closeArea(panel.contains(active) || Boolean(open) && Boolean(open?.contains(active)), false);
      syncHolders();
      return;
    }
    if (id === st.open) {
      st.psort = psort;
      paintSort();
      syncHolders();
      request();
      return;
    }
    if (isExpandable(posterOf(areas, id))) {
      openArea(id, { focus: false, scroll: false, psort, write: false });
      syncHolders();
    }
  }, "onPopState");
  const relayout = /* @__PURE__ */ __name(() => {
    if (st.open) {
      place();
    }
  }, "relayout");
  const onToggle = /* @__PURE__ */ __name((event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.matches(SELECTORS.more)) {
      return;
    }
    const poster = posterOf(areas, st.open);
    if (!details.open && poster && details.contains(poster)) {
      closeArea(false, true);
    } else {
      relayout();
    }
  }, "onToggle");
  const autoOpen = /* @__PURE__ */ __name((word) => {
    const id = Number(word.autoopen) || 0;
    if (!mode.autoopen || !id || id === st.open || st.open && st.openedby === "user") {
      return;
    }
    openArea(id, { focus: false, scroll: false, auto: true });
    if (st.open === id) {
      document.dispatchEvent(new CustomEvent(EVENTS.autoopened, { detail: { id } }));
    }
  }, "autoOpen");
  const criteria = /* @__PURE__ */ __name((detail) => {
    const accordion = detail.accordion || [];
    const word = detail.areas || {};
    const filtered = Boolean(word.filtered);
    areas.querySelectorAll(SELECTORS.grid).forEach((grid) => setAttr(grid, "aria-busy", null));
    areas.dataset.filtered = filtered ? "1" : "0";
    areas.dataset.elsewhere = word.elsewhere || "";
    paintPosters(areas, accordion, filtered, st.open, st.expandable, mode.links);
    ui.sync(() => {
      ui.filtered(filtered);
      ui.summary(summaryOf(accordion, word, (id) => {
        const poster = posterOf(areas, id);
        return poster ? poster.dataset.name || "" : null;
      }));
    });
    paintReveal(areas, word);
    st.allempty = Boolean(word.allempty);
    paintAllEmpty(areas, st.allempty, st.open);
    paintSearchAll(areas, word.searchallurl || "");
    if (st.open) {
      place();
      request();
    }
    autoOpen(word);
  }, "criteria");
  const convertPanel = /* @__PURE__ */ __name(() => {
    panel.querySelectorAll(`a${SELECTORS.openarea}`).forEach((link) => toButton(link, {}));
    const sort = panel.querySelector(SELECTORS.sort);
    if (sort) {
      sort.setAttribute("role", "radiogroup");
      sort.querySelectorAll(`a${SELECTORS.sortoption}`).forEach((link) => {
        const checked = link.getAttribute("aria-current") === "true";
        const button = toButton(link, {
          "role": "radio",
          "aria-checked": checked ? "true" : "false",
          "tabindex": checked ? "0" : "-1"
        });
        button.removeAttribute("aria-current");
      });
    }
    const close = panel.querySelector(`a${SELECTORS.close}`);
    if (close) {
      toButton(close, { "aria-label": close.getAttribute("aria-label") || "" });
    }
  }, "convertPanel");
  const adopt = /* @__PURE__ */ __name(() => {
    st.allempty = loadedAllEmpty(areas, isRoot());
    const id = drawnOpen(nodes);
    if (id) {
      st.open = id;
      st.openedby = "user";
      st.psort = readPanel(window.location.href).psort;
      st.asked = question(posterOf(areas, id), st.psort);
      st.askedticket = st.ticket;
      convertPanel();
      markTitles(panel, termOf(window.location.href));
      setClass(panel, CLASSES.shut, false);
      setClass(areas, CLASSES.areasopen, true);
    }
    areas.querySelectorAll(SELECTORS.area).forEach((poster) => convertPoster(poster, st.open, mode.links));
    if (!st.open) {
      arm();
      return;
    }
    place();
    observeRail();
    painted();
  }, "adopt");
  const disconnect = /* @__PURE__ */ __name(() => {
    railobserver?.disconnect();
    window.clearTimeout(st.skeleton);
  }, "disconnect");
  const onPending = /* @__PURE__ */ __name((detail) => pending(areas, Boolean(detail.filtered)), "onPending");
  const onFailed = /* @__PURE__ */ __name(() => failed(areas), "onFailed");
  const state = /* @__PURE__ */ __name(() => st, "state");
  return {
    adopt,
    disconnect,
    criteria,
    relayout,
    onAreasClick,
    onPanelClick,
    onDragClick,
    onPointerDown,
    onDragStart,
    onKeydown,
    onPopState,
    onToggle,
    onScroll,
    onPending,
    onFailed,
    state
  };
}, "createPanel");
export {
  EVENTS,
  createPanel,
  drawnOpen,
  elsewhere,
  optionsOf
};
//# sourceMappingURL=panel.dev.js.map
