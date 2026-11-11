var __defProp = Object.defineProperty;
var __name = (target2, value) => __defProp(target2, "name", { value, configurable: true });
/**
 * The month calendar block's client: the arrows move the month through the range service, the days are one Tab stop.
 *
 * The server draws the whole block (theme_dimensions/calendar_block_month, classes/output/calendar_block_month.php),
 * and every control is a link to the theme's calendar page, so the block works without this module. Armed, a press of
 * an arrow asks the range service for the next month's grid with the state the block drew (nothing is asked while
 * every key is off), builds the partial's context the way the server builds it ({@see context()}), and paints
 * theme_dimensions/calendar_block_month_body in place; the latest press wins, and a failure follows the link the arrow
 * already is. The keyboard is the page's grid contract in small: arrows by day and week, Home and End, PageUp and
 * PageDown by month, Enter follows the day's link.
 *
 * @module     theme_dimensions/calendar/block
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import Pending from "@moodle/lms/core/pending";
import { redirect } from "@moodle/lms/core/location";
import { getStrings } from "@moodle/lms/core/stringUtils";
import { load } from "../amd";
import { say, shift } from "../daterange/calendar";
import { gridWeeks, monthAfter } from "./days";
import { dayDots } from "./layout";
import { RANGE_METHOD } from "./account";
import { DAY_PARAM, emptyAnswer, keysOn, normalise, rangeArgs } from "./model";
import { fill } from "./strings";
const TEMPLATE = "theme_dimensions/calendar_block_month_body";
const SELECTORS = {
  root: '[data-region="dim-calblock-month"]',
  body: '[data-region="dim-calblock-body"]',
  status: '[data-region="dim-calblock-status"]',
  nav: '[data-action="dim-calblock-nav"]',
  day: '[data-region="dim-calblock-day"]',
  weekday: '[data-region="dim-calblock-weekday"]'
};
const MARK = "dim-calblock-js";
const STRING_KEYS = [
  "calendar_dayitems_many",
  "calendar_dayitems_none",
  "calendar_dayitems_one",
  "calendar_daylabel",
  "calendar_daytoday"
];
const LONG_DAY = { weekday: "long", day: "numeric", month: "long", year: "numeric" };
const pageHref = /* @__PURE__ */ __name((page, month, day = "") => {
  const url = new URL(page, window.location.href);
  url.search = "";
  url.searchParams.set("month", month);
  if (day !== "") {
    url.searchParams.set(DAY_PARAM, day);
  }
  return url.toString();
}, "pageHref");
const countText = /* @__PURE__ */ __name((labels, count) => {
  if (count === 0) {
    return labels.calendar_dayitems_none;
  }
  return count === 1 ? labels.calendar_dayitems_one : fill(labels.calendar_dayitems_many, count);
}, "countText");
const context = /* @__PURE__ */ __name((config, answer, weekdays, labels, language) => {
  const counts = new Map(answer.days.map((day) => [day.date, day.counts]));
  const prev = monthAfter(config.month, -1);
  const next = monthAfter(config.month, 1);
  return {
    caption: say(`${config.month}-01`, { month: "long", year: "numeric" }, language),
    monthurl: pageHref(config.page, config.month),
    prevmonth: prev,
    prevurl: pageHref(config.page, prev),
    nextmonth: next,
    nexturl: pageHref(config.page, next),
    weekdays,
    weeks: gridWeeks(config.month, config.firstday).map((week) => ({ days: week.map((date) => {
      const dayCounts = counts.get(date);
      const count = Object.values(dayCounts ?? {}).reduce((sum, value) => sum + value, 0);
      const dots = dayDots(dayCounts);
      const today = date === config.today;
      const label = fill(labels.calendar_daylabel, { date: say(date, LONG_DAY, language), count: countText(labels, count) });
      return {
        date,
        number: Number(date.slice(8)),
        url: pageHref(config.page, date.slice(0, 7), date),
        label: today ? `${label}, ${labels.calendar_daytoday}` : label,
        outside: !date.startsWith(config.month),
        today,
        dots: dots.keys.map((key) => ({ key })),
        more: dots.more
      };
    }) }))
  };
}, "context");
const readConfig = /* @__PURE__ */ __name((root) => {
  const { month, today, firstday, page, state } = root.dataset;
  if (!month || !today || !page || !/^\d{4}-\d{2}$/.test(month)) {
    return null;
  }
  let parsed;
  try {
    parsed = normalise(JSON.parse(state || "{}"));
  } catch {
    return null;
  }
  return { month, today, firstday: Number(firstday) || 0, page, state: parsed };
}, "readConfig");
const tickets = /* @__PURE__ */ new WeakMap();
const ownDays = /* @__PURE__ */ __name((root) => {
  const month = root.dataset.month || "";
  return Array.from(root.querySelectorAll(SELECTORS.day)).filter((day) => (day.dataset.date || "").startsWith(month));
}, "ownDays");
const rove = /* @__PURE__ */ __name((root, wanted = "") => {
  const days = Array.from(root.querySelectorAll(SELECTORS.day));
  const own = ownDays(root);
  const stop = days.find((day) => wanted !== "" && day.dataset.date === wanted) ?? own.find((day) => day.dataset.date === root.dataset.today) ?? own[0] ?? null;
  days.forEach((day) => day.setAttribute("tabindex", day === stop ? "0" : "-1"));
  return stop;
}, "rove");
const target = /* @__PURE__ */ __name((key, date, column) => {
  switch (key) {
    case "ArrowLeft":
      return shift(date, -1);
    case "ArrowRight":
      return shift(date, 1);
    case "ArrowUp":
      return shift(date, -7);
    case "ArrowDown":
      return shift(date, 7);
    case "Home":
      return shift(date, -column);
    case "End":
      return shift(date, 6 - column);
    default:
      return null;
  }
}, "target");
const sameDay = /* @__PURE__ */ __name((date, month) => {
  const last = shift(`${monthAfter(month, 1)}-01`, -1);
  const wanted = `${month}-${date.slice(8)}`;
  return wanted > last ? last : wanted;
}, "sameDay");
const go = /* @__PURE__ */ __name(async (root, month, href, refocus) => {
  const config = readConfig(root);
  const body = root.querySelector(SELECTORS.body);
  if (!config || !body) {
    redirect(href);
    return;
  }
  const seq = (tickets.get(root) ?? 0) + 1;
  tickets.set(root, seq);
  const pending = new Pending("theme_dimensions/calendar:block");
  try {
    const days = gridWeeks(month, config.firstday).flat();
    const answer = keysOn(config.state).length ? await fetchOne({ methodname: RANGE_METHOD, args: rangeArgs(days[0], days[days.length - 1], config.state) }) : emptyAnswer(days);
    if (seq !== tickets.get(root)) {
      return;
    }
    const values = await getStrings(STRING_KEYS.map((key) => ({ key, component: "theme_dimensions" })));
    const labels = Object.fromEntries(STRING_KEYS.map((key, index) => [key, values[index]]));
    const weekdays = Array.from(root.querySelectorAll(SELECTORS.weekday)).map((cell) => ({
      "short": cell.textContent?.trim() ?? "",
      "long": cell.querySelector("abbr")?.getAttribute("title") ?? ""
    }));
    const language = document.documentElement.lang || void 0;
    const made = context({ ...config, month }, answer, weekdays, labels, language);
    const templates = await load("core/templates");
    const { html, js } = await templates.renderForPromise(TEMPLATE, made);
    if (seq !== tickets.get(root)) {
      return;
    }
    templates.replaceNodeContents(body, html, js);
    root.dataset.month = month;
    const status = root.querySelector(SELECTORS.status);
    if (status) {
      status.textContent = String(made.caption);
    }
    refocus(root);
  } catch (failure) {
    if (seq === tickets.get(root)) {
      redirect(href);
    }
  } finally {
    pending.resolve();
  }
}, "go");
const plain = /* @__PURE__ */ __name((event) => event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey, "plain");
const arm = /* @__PURE__ */ __name((root) => {
  if (root.classList.contains(MARK) || !readConfig(root)) {
    return;
  }
  root.classList.add(MARK);
  rove(root);
  root.addEventListener("click", (event) => {
    const nav = event.target?.closest(SELECTORS.nav);
    if (!nav || !plain(event) || !/^\d{4}-\d{2}$/.test(nav.dataset.month || "")) {
      return;
    }
    event.preventDefault();
    const forward = nav.dataset.month === monthAfter(root.dataset.month || "", 1);
    go(root, nav.dataset.month || "", nav.href, (painted) => {
      rove(painted);
      painted.querySelectorAll(SELECTORS.nav)[forward ? 1 : 0]?.focus();
    });
  });
  root.addEventListener("keydown", (event) => {
    const day = event.target?.closest(SELECTORS.day);
    const date = day?.dataset.date || "";
    if (!day || !date || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }
    const cell = day.closest("td");
    const column = cell ? Array.from(cell.parentElement?.children ?? []).indexOf(cell) : 0;
    if (event.key === "PageUp" || event.key === "PageDown") {
      event.preventDefault();
      const month2 = monthAfter(root.dataset.month || date.slice(0, 7), event.key === "PageUp" ? -1 : 1);
      const wanted = sameDay(date, month2);
      go(root, month2, pageHref(root.dataset.page || "", month2), (painted) => rove(painted, wanted)?.focus());
      return;
    }
    const next = target(event.key, date, column);
    if (next === null) {
      return;
    }
    event.preventDefault();
    const found = root.querySelector(`${SELECTORS.day}[data-date="${next}"]`);
    if (found) {
      rove(root, next);
      found.focus();
      return;
    }
    const month = next.slice(0, 7);
    go(root, month, pageHref(root.dataset.page || "", month, next), (painted) => rove(painted, next)?.focus());
  });
}, "arm");
const start = /* @__PURE__ */ __name(() => {
  document.querySelectorAll(SELECTORS.root).forEach((root) => arm(root));
}, "start");
export {
  SELECTORS,
  TEMPLATE,
  arm,
  context,
  go,
  pageHref,
  readConfig,
  rove,
  sameDay,
  start,
  target
};
//# sourceMappingURL=block.dev.js.map
