var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The logged-in shell of the showcase listing: what only an account has, over the shared store.
 *
 * The listing's and a panel's rows, the progress and completion of the enrolled courses on screen (once per course), the state
 * remembered per category and the view preference, the automatic opening's note and the dim-js mark;
 * a failure is core's exception dialogue; it arms the card's star (./star). Armed by amd/src/listingaccount.js for a
 * real account only, never the guest (course_renderer.php); the visitor's page never loads it
 * (categorypublic_source_test).
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
import { arm as armStar } from "./star";
import { current, init } from "./store";
const HOOKS = {
  progress: '[data-region="dim-card-progress"]',
  pct: '[data-region="dim-card-pct"]',
  pill: '[data-region="dim-card-pill"]',
  cta: '[data-region="dim-card-cta"]',
  areapanel: '[data-region="dim-panel"]',
  countnotetext: '[data-region="dim-vitrine-countnotetext"]'
};
const ROWS_METHOD = "theme_dimensions_get_category_rows";
const DETAILS_METHOD = "theme_dimensions_get_card_details";
const DONE_CLASS = "dim-card--done";
const DONE_PILL = "dim-badge--done";
const DONE_ICON = "fa fa-check";
const REMEMBER_METHOD = "theme_dimensions_remember_category_state";
const VIEW_PREFERENCE = "theme_dimensions_categoryview";
const BODY_CLASS = "dim-js";
const REMEMBER_DEBOUNCE = 500;
const REMEMBER_WAIT = 1e3;
const VIEW_DEBOUNCE = 500;
const BATCH_SIZE = 24;
const BATCH_INTERVAL = 100;
const OBSERVER_MARGIN = "200px";
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
let observer = null;
const pending = /* @__PURE__ */ new Set();
const settled = /* @__PURE__ */ new Set();
const answers = /* @__PURE__ */ new Map();
let inflight = false;
let drainTimer = null;
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
const markDone = /* @__PURE__ */ __name((card) => {
  const seal = card.dataset.done || "";
  const review = card.dataset.review || "";
  card.classList.add(DONE_CLASS);
  const row = card.querySelector(HOOKS.progress);
  if (row) {
    row.hidden = true;
  }
  const pill = card.querySelector(HOOKS.pill);
  if (pill) {
    Array.from(pill.classList).filter((name) => name.startsWith("dim-badge--")).forEach((name) => pill.classList.remove(name));
    pill.classList.add(DONE_PILL);
    const icon = pill.querySelector("i");
    pill.textContent = " " + seal;
    if (icon) {
      icon.className = DONE_ICON;
      pill.prepend(icon);
    }
  }
  const cta = card.querySelector(HOOKS.cta);
  if (cta && review) {
    cta.textContent = review;
    cta.classList.remove("btn-primary");
    cta.classList.add("btn-outline-primary");
  }
}, "markDone");
const paintDetail = /* @__PURE__ */ __name((card, detail) => {
  if (detail.completed && card.dataset.done) {
    markDone(card);
    return;
  }
  const row = card.querySelector(HOOKS.progress);
  if (!row || !detail.progresslabel || typeof detail.progress !== "number") {
    return;
  }
  row.style.setProperty("--dim-progress", detail.progress + "%");
  row.setAttribute("aria-valuenow", String(detail.progress));
  row.setAttribute("aria-label", detail.progresslabel);
  const pct = row.querySelector(HOOKS.pct);
  if (pct) {
    pct.textContent = detail.progresspct || "";
  }
  row.hidden = false;
}, "paintDetail");
const paintAnswer = /* @__PURE__ */ __name((card, courseid) => {
  const detail = answers.get(courseid);
  if (!card || !detail) {
    return;
  }
  paintDetail(card, detail);
}, "paintAnswer");
const paintProgress = /* @__PURE__ */ __name((details) => {
  (details || []).forEach((detail) => {
    const courseid = Number(detail.id);
    answers.set(courseid, detail);
    paintAnswer(cardOf(courseid), courseid);
  });
}, "paintProgress");
const stopDraining = /* @__PURE__ */ __name(() => {
  if (drainTimer !== null) {
    window.clearInterval(drainTimer);
    drainTimer = null;
  }
}, "stopDraining");
const drainPending = /* @__PURE__ */ __name(async () => {
  if (pending.size === 0) {
    stopDraining();
    return;
  }
  if (inflight) {
    return;
  }
  const batch = Array.from(pending).filter((id) => !settled.has(id)).slice(0, BATCH_SIZE);
  if (!batch.length) {
    pending.clear();
    stopDraining();
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
const startDraining = /* @__PURE__ */ __name(() => {
  if (drainTimer === null) {
    drainTimer = window.setInterval(drainPending, BATCH_INTERVAL);
  }
}, "startDraining");
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
const attachCards = /* @__PURE__ */ __name((cards) => {
  cards.forEach((card) => {
    watchCard(card);
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
      if (card) {
        paintDetail(card, detail);
      }
    });
  } catch (error) {
  }
}, "paintPanelProgress");
const listen = /* @__PURE__ */ __name(() => {
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
  // A calendar that could not mount is core's exception dialogue.
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
  armStar();
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
  drainTimer = null;
  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const courseid = Number(entry.target.dataset.courseid);
      if (!Number.isInteger(courseid)) {
        return;
      }
      if (entry.isIntersecting) {
        pending.add(courseid);
        startDraining();
      } else {
        pending.delete(courseid);
      }
    });
  }, { rootMargin: OBSERVER_MARGIN });
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
