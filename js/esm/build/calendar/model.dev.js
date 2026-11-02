var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The calendar page's data: the range's answer, the page's state, and the addresses both are spelled in.
 *
 * The answer is the range service's (classes/local/calendar/range.php, get_calendar_range's returns); the state is
 * classes/local/calendar/state.php's canonical array, and {@see query()} spells it as state::query() does, so an
 * address the client writes is one the server reads back as the same state. An item opens the event popover and a
 * day the day drawer (or the phone's sheet); the two addresses they opened in C2 stay here as their fallbacks:
 * {@see itemHref()} is every item's href (a middle click, a copied link) and the popover's "Open", {@see dayHref()}
 * the drawer's link to core's day view. The popover's primary action is chosen in one place, {@see primaryOf()}.
 *
 * @module     theme_dimensions/calendar/model
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { parse } from "../daterange/calendar";
const KEYS = ["site", "category", "course", "group", "user", "activity", "other", "open"];
const RANGE_METHOD = "theme_dimensions_get_calendar_range";
const REMEMBER_METHOD = "theme_dimensions_remember_calendar_state";
const EVENT_METHOD = "core_calendar_get_calendar_event_by_id";
const DAY_PARAM = "day";
const DEFAULT_STATE = {
  off: [],
  categoryid: 0,
  recursive: true,
  model: [],
  modality: [],
  city: "",
  actiononly: false,
  colour: "key"
};
const keysOn = /* @__PURE__ */ __name((state) => KEYS.filter((key) => !state.off.includes(key)), "keysOn");
const sameState = /* @__PURE__ */ __name((a, b) => JSON.stringify(normalise(a)) === JSON.stringify(normalise(b)), "sameState");
const normalise = /* @__PURE__ */ __name((state) => ({
  off: KEYS.filter((key) => state.off.includes(key)),
  categoryid: Math.max(0, Math.trunc(Number(state.categoryid) || 0)),
  recursive: state.recursive !== false,
  model: [...new Set(state.model)].sort((a, b) => a - b),
  modality: [...new Set(state.modality)].sort((a, b) => a - b),
  city: String(state.city || "").trim(),
  actiononly: state.actiononly === true,
  colour: state.colour === "category" ? "category" : "key"
}), "normalise");
const query = /* @__PURE__ */ __name((state) => {
  const pairs = [];
  const on = keysOn(state);
  if (state.off.length) {
    (on.length ? on : [""]).forEach((key) => pairs.push(["keys[]", key]));
  }
  if (state.categoryid) {
    pairs.push(["categoryid", String(state.categoryid)]);
  }
  pairs.push(["recursive", state.recursive ? "1" : "0"]);
  state.model.forEach((value) => pairs.push(["model[]", String(value)]));
  state.modality.forEach((value) => pairs.push(["modality[]", String(value)]));
  if (state.city !== "") {
    pairs.push(["city", state.city]);
  }
  if (state.actiononly) {
    pairs.push(["actiononly", "1"]);
  }
  pairs.push(["colour", state.colour]);
  return pairs;
}, "query");
const address = /* @__PURE__ */ __name((base, month, state, day = "") => {
  const url = new URL(base, window.location.href);
  url.search = "";
  url.searchParams.append("month", month);
  if (day !== "") {
    url.searchParams.append(DAY_PARAM, day);
  }
  query(state).forEach(([name, value]) => url.searchParams.append(name, value));
  return url.toString();
}, "address");
const withDay = /* @__PURE__ */ __name((href, day) => {
  const url = new URL(href, window.location.href);
  if (day === "") {
    url.searchParams.delete(DAY_PARAM);
  } else {
    url.searchParams.set(DAY_PARAM, day);
  }
  return url.toString();
}, "withDay");
const isMonth = /* @__PURE__ */ __name((month) => parse(`${String(month)}-01`) !== null && /^\d{4}-\d{2}$/.test(String(month)), "isMonth");
const readAddress = /* @__PURE__ */ __name((href) => {
  const params = new URL(href, window.location.href).searchParams;
  const month = params.get("month");
  const named = ["keys[]", "categoryid", "recursive", "model[]", "modality[]", "city", "actiononly", "colour"].some((name) => params.has(name));
  if (!named) {
    return { month: isMonth(month) ? month : null, state: null };
  }
  const keys = params.getAll("keys[]");
  const ints = /* @__PURE__ */ __name((name) => params.getAll(name).map(Number).filter((value) => Number.isInteger(value)), "ints");
  return {
    month: isMonth(month) ? month : null,
    state: normalise({
      off: params.has("keys[]") ? KEYS.filter((key) => !keys.includes(key)) : [],
      categoryid: Number(params.get("categoryid") || 0),
      recursive: params.get("recursive") !== "0",
      model: ints("model[]"),
      modality: ints("modality[]"),
      city: params.get("city") || "",
      actiononly: params.get("actiononly") === "1",
      colour: params.get("colour") === "category" ? "category" : "key"
    })
  };
}, "readAddress");
const rangeArgs = /* @__PURE__ */ __name((from, to, state) => ({
  from,
  to,
  keys: keysOn(state),
  categoryid: state.categoryid,
  recursive: state.recursive,
  model: state.model,
  modality: state.modality,
  city: state.city,
  actiononly: state.actiononly
}), "rangeArgs");
const emptyAnswer = /* @__PURE__ */ __name((days) => ({
  from: days[0],
  to: days[days.length - 1],
  truncated: false,
  events: [],
  courses: [],
  categories: [],
  days: days.map((date) => ({ date, counts: Object.fromEntries(KEYS.map((key) => [key, 0])) }))
}), "emptyAnswer");
const safeHref = /* @__PURE__ */ __name((url, fallback) => {
  try {
    const { protocol } = new URL(url, window.location.href);
    return url !== "" && (protocol === "http:" || protocol === "https:") ? url : fallback;
  } catch {
    return fallback;
  }
}, "safeHref");
const itemHref = /* @__PURE__ */ __name((item, courses, home) => {
  const own = safeHref(item.url, "");
  if (own !== "") {
    return own;
  }
  return safeHref(courses.get(item.courseid)?.url ?? "", safeHref(home, "/"));
}, "itemHref");
const dayHref = /* @__PURE__ */ __name((calendar, iso) => {
  const parts = parse(iso);
  const url = new URL(calendar, window.location.href);
  url.search = "";
  url.searchParams.set("view", "day");
  if (parts) {
    url.searchParams.set("cal_d", String(parts.d));
    url.searchParams.set("cal_m", String(parts.m));
    url.searchParams.set("cal_y", String(parts.y));
  }
  url.searchParams.set("classic", "1");
  return url.toString();
}, "dayHref");
const primaryOf = /* @__PURE__ */ __name((item, courses, home) => {
  const actionUrl = item.action === null ? "" : safeHref(item.action.url, "");
  if (item.action !== null && item.action.actionable && actionUrl !== "") {
    return { kind: "action", name: item.action.name, url: actionUrl };
  }
  const course = courses.get(item.courseid);
  if (item.source === "course" && course && (course.join === "open" || course.join === "free" || course.join === "key")) {
    return { kind: "join", join: course.join, url: safeHref(course.landingurl, itemHref(item, courses, home)) };
  }
  return { kind: "open", url: itemHref(item, courses, home) };
}, "primaryOf");
const monthHref = /* @__PURE__ */ __name((calendar, month) => {
  const url = new URL(calendar, window.location.href);
  url.search = "";
  url.searchParams.set("view", "month");
  url.searchParams.set("cal_d", "1");
  url.searchParams.set("cal_m", String(Number(month.slice(5, 7))));
  url.searchParams.set("cal_y", String(Number(month.slice(0, 4))));
  url.searchParams.set("classic", "1");
  return url.toString();
}, "monthHref");
const hex = /* @__PURE__ */ __name((value) => /^#[0-9a-f]{6}$/i.test(String(value)) ? String(value) : null, "hex");
export {
  DAY_PARAM,
  DEFAULT_STATE,
  EVENT_METHOD,
  KEYS,
  RANGE_METHOD,
  REMEMBER_METHOD,
  address,
  dayHref,
  emptyAnswer,
  hex,
  isMonth,
  itemHref,
  keysOn,
  monthHref,
  normalise,
  primaryOf,
  query,
  rangeArgs,
  readAddress,
  safeHref,
  sameState,
  withDay
};
//# sourceMappingURL=model.dev.js.map
