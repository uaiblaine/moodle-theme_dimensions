var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The logged-in shell of the showcase listing: what only an account has, over the shared store.
 *
 * The listing's and a panel's rows, the progress of the enrolled courses on screen (once per course), the state
 * remembered per category and the view preference, the hover peek, the automatic opening's note and the dim-js mark;
 * a failure is core's exception dialogue. Armed by amd/src/listingaccount.js for a real account only, never the guest
 * (course_renderer.php); the visitor's page never loads it (categorypublic_source_test).
 *
 * @module     theme_dimensions/listing/account
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import Fetch from "@moodle/lms/core/fetch";
import { redirect } from "@moodle/lms/core/location";
import { load } from "../amd";
import { period as calendarPeriod } from "../daterange/calendar";
import { LISTING, SELECTORS } from "./events";
import * as Listing from "./listing";
import { current, init } from "./store";
const HOOKS = {
  details: '[data-region="dim-card-details"]',
  panel: '[data-region="dim-card-panel"]',
  peek: ".dim-card__peek",
  progress: '[data-region="dim-card-progress"]',
  areapanel: '[data-region="dim-panel"]',
  countnotetext: '[data-region="dim-vitrine-countnotetext"]'
};
const ROWS_METHOD = "theme_dimensions_get_category_rows";
const DETAILS_METHOD = "theme_dimensions_get_card_details";
const REMEMBER_METHOD = "theme_dimensions_remember_category_state";
const VIEW_PREFERENCE = "theme_dimensions_categoryview";
const BODY_CLASS = "dim-js";
const REMEMBER_DEBOUNCE = 500;
const REMEMBER_WAIT = 1e3;
const VIEW_DEBOUNCE = 500;
const BATCH_SIZE = 24;
const BATCH_INTERVAL = 100;
const OBSERVER_MARGIN = "200px";
const PEEK_DELAY = 180;
const PEEK_WIDTH = 340;
const PEEK_MIN_WIDTH = 576;
const PANEL_SIZE = 8;
const LEGACY_FAVOURITES = "favourites";
const PSORT_NAME = "name";
const SORT = {
  recent: "recent",
  name: "name"
};
const UNREMEMBERED = ["term", "tree", "period"];
let root;
let canpersist = false;
let autoopenlabel = "";
let viewtimer;
let remembertimer;
let rememberedsent = "";
let peektimer;
let observer = null;
const pending = /* @__PURE__ */ new Set();
const settled = /* @__PURE__ */ new Set();
const answers = /* @__PURE__ */ new Map();
let inflight = false;
const exception = /* @__PURE__ */ __name(async (error) => {
  const Notification = await load("core/notification");
  await Notification.exception(error);
}, "exception");
const isRootPage = /* @__PURE__ */ __name(() => Listing.isRoot(current()), "isRootPage");
const rowsArgs = /* @__PURE__ */ __name((page) => {
  const state = current();
  if (isRootPage()) {
    return { categoryid: 0, query: state.query };
  }
  return {
    categoryid: state.categoryid,
    page,
    query: state.query,
    chip: state.chip,
    fav: state.fav,
    perpage: state.pagesize,
    filters: Listing.filterArgs(state),
    sort: state.sort,
    datefrom: state.datefrom,
    dateto: state.dateto,
    cat: state.cat
  };
}, "rowsArgs");
const rows = /* @__PURE__ */ __name((page) => {
  const answer = fetchOne({
    methodname: ROWS_METHOD,
    args: rowsArgs(page)
  });
  answer.catch(exception);
  return answer;
}, "rows");
const rememberArgs = /* @__PURE__ */ __name(() => {
  const state = current();
  return {
    categoryid: state.categoryid,
    sort: state.sort,
    view: state.view,
    chip: state.chip,
    fav: state.fav,
    filters: Listing.filterArgs(state)
  };
}, "rememberArgs");
const remember = /* @__PURE__ */ __name((now) => {
  if (!canpersist || isRootPage()) {
    return Promise.resolve();
  }
  current().remembered = true;
  window.clearTimeout(remembertimer);
  const send = /* @__PURE__ */ __name(() => {
    const args = rememberArgs();
    const key = JSON.stringify(args);
    if (key === rememberedsent) {
      return Promise.resolve();
    }
    rememberedsent = key;
    return fetchOne({ methodname: REMEMBER_METHOD, args }).catch(exception);
  }, "send");
  if (now) {
    return send();
  }
  remembertimer = window.setTimeout(send, REMEMBER_DEBOUNCE);
  return Promise.resolve();
}, "remember");
const leave = /* @__PURE__ */ __name(async () => {
  await Promise.race([
    remember(true),
    new Promise((resolve) => window.setTimeout(resolve, REMEMBER_WAIT))
  ]);
  redirect(Listing.buildUrl(current()).toString());
}, "leave");
const changed = /* @__PURE__ */ __name((kind) => {
  if (UNREMEMBERED.indexOf(kind) >= 0) {
    return;
  }
  remember(false);
  if (kind !== "view" || !canpersist) {
    return;
  }
  const view = current().view;
  window.clearTimeout(viewtimer);
  viewtimer = window.setTimeout(() => {
    Fetch.performPost("core_user", `current/preferences/${VIEW_PREFERENCE}`, { body: { value: view } }).catch(exception);
  }, VIEW_DEBOUNCE);
}, "changed");
const paintAutoOpened = /* @__PURE__ */ __name(() => {
  const note = root.querySelector(SELECTORS.countnote);
  const text = note ? note.querySelector(HOOKS.countnotetext) : null;
  if (!note || !text || autoopenlabel === "") {
    return;
  }
  text.textContent = autoopenlabel;
  note.hidden = false;
}, "paintAutoOpened");
const cardOf = /* @__PURE__ */ __name((courseid) => {
  if (!Number.isInteger(courseid)) {
    return null;
  }
  return root.querySelector('[data-region="dim-card"][data-courseid="' + courseid + '"]');
}, "cardOf");
const settle = /* @__PURE__ */ __name((ids) => {
  ids.forEach((id) => {
    settled.add(id);
    pending.delete(id);
    const card = cardOf(id);
    if (card && observer) {
      observer.unobserve(card);
    }
  });
}, "settle");
const discard = /* @__PURE__ */ __name((card) => {
  const courseid = Number(card.dataset.courseid);
  if (observer) {
    observer.unobserve(card);
  }
  pending.delete(courseid);
}, "discard");
const paintAnswer = /* @__PURE__ */ __name((card, courseid) => {
  const label = answers.get(courseid);
  if (!card || !label) {
    return;
  }
  const slot = card.querySelector(HOOKS.progress);
  if (!slot) {
    return;
  }
  slot.textContent = label;
  slot.hidden = false;
}, "paintAnswer");
const paintProgress = /* @__PURE__ */ __name((details) => {
  (details || []).forEach((detail) => {
    const courseid = Number(detail.id);
    if (!detail.progresslabel) {
      return;
    }
    answers.set(courseid, detail.progresslabel);
    paintAnswer(cardOf(courseid), courseid);
  });
}, "paintProgress");
const drainPending = /* @__PURE__ */ __name(async () => {
  if (inflight || pending.size === 0) {
    return;
  }
  const batch = Array.from(pending).filter((id) => !settled.has(id)).slice(0, BATCH_SIZE);
  if (!batch.length) {
    return;
  }
  batch.forEach((id) => pending.delete(id));
  inflight = true;
  try {
    const response = await fetchOne({
      methodname: DETAILS_METHOD,
      args: { courseids: batch }
    });
    paintProgress(response.details);
  } catch (error) {
    exception(error);
  } finally {
    settle(batch);
    inflight = false;
  }
}, "drainPending");
const watchCard = /* @__PURE__ */ __name((card) => {
  const courseid = Number(card.dataset.courseid);
  if (!observer || card.dataset.state !== "enrolled" || !Number.isInteger(courseid)) {
    return;
  }
  if (settled.has(courseid)) {
    paintAnswer(card, courseid);
    return;
  }
  observer.observe(card);
}, "watchCard");
const peekable = /* @__PURE__ */ __name(() => window.matchMedia("(pointer: fine)").matches && window.innerWidth >= PEEK_MIN_WIDTH && current().view === "cards", "peekable");
const closePeeks = /* @__PURE__ */ __name(() => {
  window.clearTimeout(peektimer);
  root.querySelectorAll(".dim-card--peeking").forEach((card) => card.classList.remove("dim-card--peeking"));
}, "closePeeks");
const closeOthers = /* @__PURE__ */ __name((keep) => {
  root.querySelectorAll(HOOKS.details).forEach((details) => {
    if (details !== keep) {
      details.open = false;
    }
  });
  closePeeks();
}, "closeOthers");
const openPeek = /* @__PURE__ */ __name((card) => {
  const panel = card.querySelector(HOOKS.panel);
  const details = card.querySelector(HOOKS.details);
  if (!panel || details && details.open) {
    return;
  }
  closeOthers(null);
  let peek = card.querySelector(HOOKS.peek);
  if (!peek) {
    peek = document.createElement("div");
    peek.className = "dim-card__peek";
    peek.setAttribute("aria-hidden", "true");
    const clone = panel.cloneNode(true);
    while (clone.firstChild) {
      peek.append(clone.firstChild);
    }
    card.append(peek);
  }
  const grid = root.querySelector(SELECTORS.grid);
  const edge = grid ? grid.getBoundingClientRect().right : window.innerWidth;
  peek.classList.toggle("dim-card__peek--flip", card.getBoundingClientRect().right + PEEK_WIDTH > edge);
  card.classList.add("dim-card--peeking");
}, "openPeek");
const attachCards = /* @__PURE__ */ __name((cards) => {
  cards.forEach((card) => {
    watchCard(card);
    card.addEventListener("mouseenter", () => {
      window.clearTimeout(peektimer);
      if (!peekable()) {
        return;
      }
      peektimer = window.setTimeout(() => openPeek(card), PEEK_DELAY);
    });
    card.addEventListener("mouseleave", () => {
      window.clearTimeout(peektimer);
      card.classList.remove("dim-card--peeking");
    });
  });
}, "attachCards");
const painted = /* @__PURE__ */ __name((items) => {
  const cards = items.map((item) => item.querySelector(SELECTORS.card));
  attachCards(cards.filter((card) => card !== null));
}, "painted");
const failed = /* @__PURE__ */ __name((page, append) => {
  if (!append && root.querySelector(SELECTORS.area)) {
    document.dispatchEvent(new CustomEvent(LISTING.failed));
  }
}, "failed");
const answered = /* @__PURE__ */ __name((response) => {
  const word = response.areas || {};
  autoopenlabel = typeof word.autoopenlabel === "string" ? word.autoopenlabel : "";
}, "answered");
const panelArgs = /* @__PURE__ */ __name((id, psort) => {
  const address = new URL(window.location.href).searchParams;
  let params = address;
  if (isRootPage()) {
    params = new URLSearchParams();
    if (address.has(Listing.PARAM.query)) {
      params.set(Listing.PARAM.query, address.get(Listing.PARAM.query) || "");
    }
  }
  const filters = [];
  params.forEach((value, name) => {
    if (name.startsWith(Listing.PARAM.fieldprefix) && Number(value) > 0) {
      filters.push({ fieldid: Number(name.slice(Listing.PARAM.fieldprefix.length)), value: Number(value) });
    }
  });
  const range = calendarPeriod(params.get(Listing.PARAM.from), params.get(Listing.PARAM.to));
  const chip = params.get(Listing.PARAM.chip) || "";
  return {
    categoryid: id,
    page: 0,
    perpage: PANEL_SIZE,
    sort: psort === PSORT_NAME ? SORT.name : SORT.recent,
    query: params.get(Listing.PARAM.query) || "",
    chip: chip === LEGACY_FAVOURITES ? "" : chip,
    fav: params.get(Listing.PARAM.fav) === "1" || chip === LEGACY_FAVOURITES,
    filters,
    datefrom: range.from,
    dateto: range.to,
    withcategories: true
  };
}, "panelArgs");
const ask = /* @__PURE__ */ __name((poster, psort) => {
  const id = Number(poster.dataset.categoryid);
  return fetchOne({
    methodname: ROWS_METHOD,
    args: panelArgs(id, psort)
  });
}, "ask");
const paintPanelProgress = /* @__PURE__ */ __name(async (panel) => {
  const cards = Array.from(panel.querySelectorAll(SELECTORS.card)).filter((card) => card.dataset.state === "enrolled");
  const ids = cards.map((card) => Number(card.dataset.courseid)).filter((id) => Number.isInteger(id) && id > 0);
  if (!ids.length) {
    return;
  }
  try {
    const response = await fetchOne({
      methodname: DETAILS_METHOD,
      args: { courseids: ids.slice(0, PANEL_SIZE) }
    });
    (response.details || []).forEach((detail) => {
      const card = cards.find((one) => Number(one.dataset.courseid) === Number(detail.id));
      const slot = card ? card.querySelector(HOOKS.progress) : null;
      if (!slot || !detail.progresslabel) {
        return;
      }
      slot.textContent = detail.progresslabel;
      slot.style.setProperty("--dim-progress", Number(detail.progress || 0) + "%");
      slot.hidden = false;
    });
  } catch (error) {
  }
}, "paintPanelProgress");
const listen = /* @__PURE__ */ __name(() => {
  root.addEventListener("toggle", (event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open) {
      return;
    }
    if (details.matches(HOOKS.details)) {
      closeOthers(details);
    }
  }, true);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeOthers(null);
    }
  });
  document.addEventListener(LISTING.autoopened, paintAutoOpened);
}, "listen");
const nothing = /* @__PURE__ */ __name(() => void 0, "nothing");
const SHELL = {
  mark: BODY_CLASS,
  normalise: Listing.rootOnly,
  rows,
  failed,
  changed,
  written: nothing,
  leave,
  discard,
  painted,
  answered,
  // A calendar that could not mount is core's exception dialogue, as categoryvitrine had it.
  unmounted: exception,
  areas: {
    props: {},
    client: {
      ask,
      painted: paintPanelProgress
    }
  }
};
const start = /* @__PURE__ */ __name(() => {
  const found = document.querySelector(SELECTORS.root);
  if (!found) {
    return;
  }
  root = found;
  let payload = {};
  try {
    payload = JSON.parse(root.dataset.vitrine || "{}");
  } catch (error) {
    payload = {};
  }
  canpersist = Boolean(payload.canpersist);
  if (!init(SHELL, Number(payload.categoryid) || Number(root.dataset.categoryid) || 0)) {
    return;
  }
  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const courseid = Number(entry.target.dataset.courseid);
      if (!Number.isInteger(courseid)) {
        return;
      }
      if (entry.isIntersecting) {
        pending.add(courseid);
      } else {
        pending.delete(courseid);
      }
    });
  }, { rootMargin: OBSERVER_MARGIN });
  window.setInterval(drainPending, BATCH_INTERVAL);
  attachCards(Array.from(root.querySelectorAll(SELECTORS.card)).filter((card) => !card.closest(HOOKS.areapanel)));
  listen();
  if (current().remembered) {
    rememberedsent = JSON.stringify(rememberArgs());
    Listing.writeUrl(current(), false);
  }
}, "start");
export {
  start
};
//# sourceMappingURL=account.dev.js.map
