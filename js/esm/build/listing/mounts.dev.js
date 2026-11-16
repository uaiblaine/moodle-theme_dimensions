var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The listing's three React components, mounted for both shells, and what the listing tells them.
 *
 * The components are named here ({@see COMPONENTS}) and nowhere else, and are visitor-safe by rule
 * (categorypublic_source_test walks them). Every mount puts its listeners on before its node: core's
 * react_autoinit may mount it at once, and a component already imported must find somebody to hear it.
 *
 * @module     theme_dimensions/listing/mounts
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Pending from "@moodle/lms/core/pending";
import { CHIP_PERIOD, SCOPE } from "./address";
import { AREAS, CALENDAR, LINE, LINE_DRAWN, SELECTORS } from "./events";
const COMPONENTS = {
  calendar: "@moodle/lms/theme_dimensions/daterange/DateRange",
  line: "@moodle/lms/theme_dimensions/filterline/FilterLine",
  areas: "@moodle/lms/theme_dimensions/areas/Areas"
};
const drawnByLine = /* @__PURE__ */ __name((element) => {
  const line = element ? element.closest(SELECTORS.line) : null;
  return element !== null && line !== null && line.hasAttribute(LINE_DRAWN) && !element.closest(SELECTORS.adopted);
}, "drawnByLine");
const filterGroupsShown = /* @__PURE__ */ __name((root) => {
  const panel = root.querySelector(SELECTORS.filterspanel);
  return panel !== null && Array.from(panel.querySelectorAll(SELECTORS.group)).some((group) => !group.hidden);
}, "filterGroupsShown");
const tellLine = /* @__PURE__ */ __name((root, type, detail) => {
  const line = root.querySelector(SELECTORS.line);
  if (!line || !line.hasAttribute(LINE_DRAWN)) {
    return false;
  }
  line.dispatchEvent(new CustomEvent(type, { detail }));
  return true;
}, "tellLine");
const tellAnswer = /* @__PURE__ */ __name((root, response) => {
  const group = response.statusgroup;
  const options = group ? group.options || [] : [];
  const rank = /* @__PURE__ */ __name((option) => SCOPE.indexOf(String(option.value)), "rank");
  const chips = Array.isArray(response.chips) ? response.chips : [];
  const period = chips.find((chip) => chip.param === CHIP_PERIOD);
  tellLine(root, LINE.answer, {
    // The bar's count is the count sentence's number, as the server formatted it.
    total: typeof response.countnumber === "string" ? response.countnumber : void 0,
    // No option at all is a page whose state sort was switched off under it: the scope stays as drawn.
    scope: options.length ? options.filter((option) => rank(option) >= 0).sort((a, b) => rank(a) - rank(b)) : null,
    favourites: response.favourites || null,
    filtercount: Number(response.filtercount || 0),
    filtercountlabel: response.filtercountlabel || "",
    // Whether the Filters panel holds a group, which decides whether its control is drawn.
    filtergroups: filterGroupsShown(root),
    // The date button's name carries the period chip's words; no chip is no period, and the button its name alone.
    period: period ? period.label || "" : ""
  });
}, "tellAnswer");
const focusTarget = /* @__PURE__ */ __name((root) => {
  const search = root.querySelector(SELECTORS.search);
  const opener = root.querySelector(SELECTORS.opener);
  if (!search || search.getClientRects().length > 0 || !opener || opener.getClientRects().length === 0) {
    return search;
  }
  return opener;
}, "focusTarget");
const mountLine = /* @__PURE__ */ __name((root, dropTerm) => {
  const form = root.querySelector(SELECTORS.toolbar);
  const line = form ? form.querySelector(SELECTORS.line) : null;
  const formid = form ? form.getAttribute("id") : null;
  if (!line || !line.querySelector(SELECTORS.linefield) || !line.querySelector(SELECTORS.listbox) || !formid) {
    return false;
  }
  const pending = new Pending("theme_dimensions/listing:line");
  line.addEventListener(LINE.dropterm, () => dropTerm());
  line.addEventListener(LINE.mounted, () => pending.resolve(), { once: true });
  line.addEventListener(LINE.failed, () => pending.resolve(), { once: true });
  line.dataset.reactComponent = COMPONENTS.line;
  line.dataset.reactProps = JSON.stringify({ form: formid });
  const focused = line.contains(document.activeElement) ? document.activeElement : null;
  line.parentNode?.insertBefore(line, line.nextSibling);
  if (focused) {
    focused.focus({ preventScroll: true });
  }
  return true;
}, "mountLine");
const hideDateFields = /* @__PURE__ */ __name((root) => {
  const fields = root.querySelector(SELECTORS.dateinputs);
  if (fields) {
    fields.hidden = true;
    fields.querySelectorAll('input[type="date"]').forEach((input) => {
      input.tabIndex = -1;
    });
  }
}, "hideDateFields");
const tellCalendar = /* @__PURE__ */ __name((region, state) => {
  if (region) {
    region.dispatchEvent(new CustomEvent(CALENDAR.set, { detail: { from: state.datefrom, to: state.dateto } }));
  }
}, "tellCalendar");
const appendCalendar = /* @__PURE__ */ __name((root, fieldset, region, state, onpick) => {
  region.addEventListener(CALENDAR.pick, (event) => {
    const detail = event.detail;
    onpick(detail.from, detail.to);
  });
  region.addEventListener(CALENDAR.mounted, () => {
    hideDateFields(root);
    tellCalendar(region, state);
  });
  const node = document.createElement("div");
  node.dataset.reactComponent = COMPONENTS.calendar;
  node.dataset.reactProps = JSON.stringify({
    id: state.categoryid,
    firstday: fieldset.dataset.firstday,
    today: fieldset.dataset.today,
    from: state.datefrom,
    to: state.dateto
  });
  region.append(node);
}, "appendCalendar");
const mountCalendar = /* @__PURE__ */ __name(async (root, state, onpick) => {
  const fieldset = root.querySelector(SELECTORS.date);
  const region = fieldset ? fieldset.querySelector(SELECTORS.daterange) : null;
  if (!fieldset || !region) {
    return;
  }
  appendCalendar(root, fieldset, region, state, onpick);
}, "mountCalendar");
const mountAreas = /* @__PURE__ */ __name((areas, props, client) => {
  if (!areas || !areas.querySelector(SELECTORS.panel)) {
    return false;
  }
  const pending = new Pending("theme_dimensions/listing:areas");
  areas.addEventListener(AREAS.ask, (event) => {
    const detail = event.detail;
    detail.answer = client.ask(detail.poster, detail.psort);
  });
  if (client.question) {
    const question = client.question;
    areas.addEventListener(AREAS.question, (event) => {
      const detail = event.detail;
      detail.question = question(detail.poster, detail.psort);
    });
  }
  if (client.unanswered) {
    const unanswered = client.unanswered;
    areas.addEventListener(AREAS.unanswered, (event) => {
      const detail = event.detail;
      detail.taken = unanswered(detail.poster, detail.psort);
    });
  }
  if (client.painted) {
    const painted = client.painted;
    areas.addEventListener(AREAS.painted, (event) => painted(event.detail.panel));
  }
  areas.addEventListener(AREAS.mounted, () => pending.resolve(), { once: true });
  const node = document.createElement("div");
  node.hidden = true;
  node.dataset.reactComponent = COMPONENTS.areas;
  node.dataset.reactProps = JSON.stringify(props);
  areas.append(node);
  return true;
}, "mountAreas");
export {
  drawnByLine,
  filterGroupsShown,
  focusTarget,
  mountAreas,
  mountCalendar,
  mountLine,
  tellAnswer,
  tellCalendar,
  tellLine
};
//# sourceMappingURL=mounts.dev.js.map
