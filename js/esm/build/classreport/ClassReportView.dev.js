var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The class competency report, drawn in React (phase 6 part two of docs/competency/PLAN.md).
 *
 * report/competency/index.php without a learner renders the shell (theme_dimensions\output\class_report_view)
 * with configuration and strings; the learners and the figures come from the theme's web services, which read
 * the system report class_ratings headlessly, so access, the group rule, the stored filters and the chosen
 * columns are the report's, as on core's table. Three regions load on their own:
 *
 * - the rows (theme_dimensions_class_report_rows): a sort or a page reloads them alone;
 * - the band (theme_dimensions_class_report_summary): a filter or the search reload it with the rows;
 * - the columns (theme_dimensions_set_class_report_preference, the preference's one writer): a column change
 *   is written, and the rows and the band are read again only once the write has landed, because the report
 *   reads the columns in initialise() and the band's figures are over the shown columns. A width is written
 *   and read again by nobody: it is presentation.
 *
 * Every request posts the group the page resolved (the props' groupid, never -1), so a read never depends on
 * the session's choice changing in another tab. One request is in flight per region in effect: core's ajax
 * cannot abort, so each region numbers its requests and drops any answer but the newest. The search is sent
 * 300 ms after the last key, from two characters. The first load draws skeletons with aria-busy; later ones
 * keep the old rows, dimmed, under a 2 px line. The view (Automatic, Table, Cards) is remembered in this browser
 * only, and Automatic is the cards under a 576 px container.
 *
 * A grader's rating cell carries the grading dialogue's hooks; theme_dimensions/classratings (armed by the
 * shell) opens core's dialogue from it and, when it closes, dispatches RATED on the shell's root: the view reads
 * the rows (the page kept) and the band again. Composed after local_evidencehub's hub/HubView.tsx, without its
 * scope, presets, selection, dock and drawer.
 *
 * @module     theme_dimensions/classreport/ClassReportView
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { rememberView, storedView, useNarrow } from "../datareport/layout";
import { Pager } from "../datareport/Pager";
import { hold } from "../datareport/pending";
import { RowsRegion } from "../datareport/Rows";
import { Band } from "./Band";
import { Bar } from "./Bar";
import { Cards } from "./Cards";
import { LEAD, SIZING } from "./cells";
import { withChoice, without } from "./filters";
import { Grid } from "./Grid";
import { getRows, getSummary, resetFilters, setFilters, setPreference } from "./repository";
const RATED = "theme_dimensions/classratings:rated";
const ROOT = '[data-region="dim-comp-classreport"]';
const SEARCH_DELAY = 300;
const WIDTH_DELAY = 500;
const VIEW_KEY = "theme_dimensions_classreport_view";
const SKELETON_COLUMNS = [LEAD, "a", "b", "c", "d"];
const widthList = /* @__PURE__ */ __name((map) => Object.entries(map).map(([key, width]) => ({ key, width })), "widthList");
const preferenceOf = /* @__PURE__ */ __name((usedefault, chosen, widths) => ({ usedefault: false, columns: usedefault ? [] : chosen, widths: widthList(widths) }), "preferenceOf");
const ClassReportView = /* @__PURE__ */ __name((props) => {
  const { labels, reportid } = props;
  const target = useMemo(
    () => ({ courseid: props.courseid, moduleid: props.moduleid, groupid: props.groupid }),
    [props.courseid, props.moduleid, props.groupid]
  );
  const root = useRef(null);
  const narrow = useNarrow(root);
  const [view, setViewState] = useState(() => storedView(VIEW_KEY));
  const initial = { search: "", page: 0, perpage: props.perpage, sort: "", direction: "asc" };
  const query = useRef(initial);
  const [current, setCurrent] = useState(initial);
  const [searchtext, setSearchtext] = useState("");
  const [rows, setRows] = useState(null);
  const [rowsloading, setRowsloading] = useState(true);
  const [rowserror, setRowserror] = useState(false);
  const [summary, setSummary] = useState(null);
  const [bandloading, setBandloading] = useState(true);
  const [banderror, setBanderror] = useState(false);
  const [writing, setWriting] = useState(false);
  const writes = useRef(0);
  const [widths, setWidths] = useState({});
  const [counted, setCounted] = useState({});
  const rowsseq = useRef(0);
  const bandseq = useRef(0);
  const rowshold = useRef(null);
  const bandhold = useRef(null);
  const searchtimer = useRef(null);
  const searchhold = useRef(null);
  const widthsread = useRef(false);
  const widthtimer = useRef(null);
  const widthhold = useRef(null);
  const pendingwidths = useRef(null);
  const flushref = useRef(() => void 0);
  const loadRows = useCallback((asked, facets) => {
    const seq = ++rowsseq.current;
    if (!rowshold.current) {
      rowshold.current = hold("theme_dimensions/classreport:rows");
    }
    setRowsloading(true);
    getRows(target, asked, facets).then((answer) => {
      if (seq !== rowsseq.current) {
        return;
      }
      if (answer.counted) {
        setCounted(Object.fromEntries(answer.filters.map((filter) => [filter.name, filter])));
      }
      if (!widthsread.current) {
        widthsread.current = true;
        setWidths(Object.fromEntries(answer.preference.widths.map((entry) => [entry.key, entry.width])));
      }
      if (answer.page !== query.current.page) {
        query.current = { ...query.current, page: answer.page };
        setCurrent(query.current);
      }
      setRows(answer);
      setRowserror(false);
      setRowsloading(false);
      return;
    }).catch(() => {
      if (seq === rowsseq.current) {
        setRowserror(true);
        setRowsloading(false);
      }
    });
  }, [target]);
  const loadBand = useCallback((asked) => {
    const seq = ++bandseq.current;
    if (!bandhold.current) {
      bandhold.current = hold("theme_dimensions/classreport:band");
    }
    setBandloading(true);
    getSummary(target, asked.search).then((answer) => {
      if (seq === bandseq.current) {
        setSummary(answer);
        setBanderror(false);
        setBandloading(false);
      }
      return;
    }).catch(() => {
      if (seq === bandseq.current) {
        setBanderror(true);
        setBandloading(false);
      }
    });
  }, [target]);
  const run = useCallback((change, readband) => {
    query.current = { ...query.current, ...change };
    setCurrent(query.current);
    loadRows(query.current, readband);
    if (readband) {
      loadBand(query.current);
    }
  }, [loadRows, loadBand]);
  useEffect(() => {
    if (!rowsloading && rowshold.current) {
      rowshold.current();
      rowshold.current = null;
    }
  }, [rowsloading, rows, rowserror]);
  useEffect(() => {
    if (!bandloading && bandhold.current) {
      bandhold.current();
      bandhold.current = null;
    }
  }, [bandloading, summary, banderror]);
  useEffect(() => {
    loadRows(query.current, true);
    loadBand(query.current);
  }, [loadRows, loadBand]);
  useEffect(() => {
    const element = root.current;
    const host = element?.closest(ROOT) ?? document;
    const rated = /* @__PURE__ */ __name(() => {
      run({}, true);
    }, "rated");
    host.addEventListener(RATED, rated);
    return () => host.removeEventListener(RATED, rated);
  }, [run]);
  useEffect(() => () => {
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
    }
    searchhold.current?.();
    flushref.current();
  }, []);
  const setView = /* @__PURE__ */ __name((chosen) => {
    setViewState(chosen);
    rememberView(VIEW_KEY, chosen);
  }, "setView");
  const onSearch = /* @__PURE__ */ __name((text) => {
    setSearchtext(text);
    if (searchtimer.current !== null) {
      window.clearTimeout(searchtimer.current);
      searchtimer.current = null;
    }
    const trimmed = text.trim();
    const applied = trimmed.length >= props.searchmin ? trimmed : "";
    if (applied === query.current.search) {
      searchhold.current?.();
      searchhold.current = null;
      return;
    }
    if (text === "") {
      searchhold.current?.();
      searchhold.current = null;
      run({ search: "", page: 0 }, true);
      return;
    }
    if (!searchhold.current) {
      searchhold.current = hold("theme_dimensions/classreport:search");
    }
    searchtimer.current = window.setTimeout(() => {
      searchtimer.current = null;
      run({ search: applied, page: 0 }, true);
      searchhold.current?.();
      searchhold.current = null;
    }, SEARCH_DELAY);
  }, "onSearch");
  const writeThenRead = /* @__PURE__ */ __name((key, write, change) => {
    const release = hold(key);
    writes.current += 1;
    setWriting(true);
    write().then(() => {
      run(change, true);
      return;
    }).catch(() => {
      setRowserror(true);
    }).finally(() => {
      writes.current -= 1;
      setWriting(writes.current > 0);
      release();
    });
  }, "writeThenRead");
  const writeFilters = /* @__PURE__ */ __name((write) => writeThenRead("theme_dimensions/classreport:filters", write, { page: 0 }), "writeFilters");
  const filters = (rows?.filters ?? []).map((filter) => counted[filter.name] ? { ...filter, options: counted[filter.name].options } : filter);
  const allcolumns = rows?.columns ?? [];
  const usedefault = rows?.preference.usedefault ?? true;
  const chosenkeys = allcolumns.filter((column) => !column.fixed && column.active).map((column) => column.key);
  const onColumn = /* @__PURE__ */ __name((key, on) => {
    const shown2 = chosenkeys.length + (on ? 1 : -1);
    if (!on && shown2 < 1) {
      return;
    }
    if (on && shown2 > props.maxcolumns) {
      return;
    }
    const next = on ? [...chosenkeys, key] : chosenkeys.filter((chosen) => chosen !== key);
    if (rows) {
      setRows({
        ...rows,
        columns: rows.columns.map((column) => column.key === key ? { ...column, active: on } : column),
        preference: { ...rows.preference, usedefault: false, columns: next }
      });
    }
    const preference = { usedefault: false, columns: next, widths: widthList(widths) };
    writeThenRead("theme_dimensions/classreport:columns", () => setPreference(props.courseid, preference), {});
  }, "onColumn");
  const flushWidths = /* @__PURE__ */ __name(() => {
    if (widthtimer.current !== null) {
      window.clearTimeout(widthtimer.current);
      widthtimer.current = null;
    }
    const next = pendingwidths.current;
    pendingwidths.current = null;
    if (next !== null) {
      const release = hold("theme_dimensions/classreport:width");
      setPreference(props.courseid, preferenceOf(usedefault, chosenkeys, next)).catch(() => void 0).finally(release);
    }
    widthhold.current?.();
    widthhold.current = null;
  }, "flushWidths");
  flushref.current = flushWidths;
  const onWidth = /* @__PURE__ */ __name((key, width, defer = false) => {
    const next = { ...widths };
    if (width === null) {
      delete next[key];
    } else {
      next[key] = width;
    }
    setWidths(next);
    pendingwidths.current = next;
    if (!defer) {
      flushWidths();
      return;
    }
    if (widthtimer.current !== null) {
      window.clearTimeout(widthtimer.current);
    }
    if (!widthhold.current) {
      widthhold.current = hold("theme_dimensions/classreport:widthkeys");
    }
    widthtimer.current = window.setTimeout(() => flushref.current(), WIDTH_DELAY);
  }, "onWidth");
  const onSort = /* @__PURE__ */ __name((key) => {
    const sorted = rows?.sort ?? "";
    const direction = sorted === key && rows?.direction === "asc" ? "desc" : "asc";
    run({ sort: key, direction, page: 0 }, false);
  }, "onSort");
  const list = useMemo(() => (rows?.rows ?? []).map((row) => ({ ...row, id: row.userid })), [rows]);
  const filtered = filters.some((filter) => filter.applied) || current.search !== "";
  const identity = allcolumns.filter((column) => column.key.startsWith("identity:"));
  const competencies = allcolumns.filter((column) => !column.fixed && column.active);
  const shown = allcolumns.filter((column) => column.key === LEAD || !column.fixed && column.active);
  const cards = view === "cards" || view === "auto" && narrow;
  let body = null;
  if (rows !== null) {
    body = cards ? /* @__PURE__ */ jsxDEV(Cards, { labels, columns: competencies, identity, rows: list, single: narrow }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
      lineNumber: 477,
      columnNumber: 13
    }) : /* @__PURE__ */ jsxDEV(
      Grid,
      {
        labels,
        columns: shown,
        identity,
        rows: list,
        widths,
        sort: rows.sort,
        direction: rows.direction,
        onSort,
        onWidth,
        onWidthCommit: () => flushref.current()
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
        lineNumber: 479,
        columnNumber: 13
      }
    );
  }
  const skeleton = SKELETON_COLUMNS.map((key) => ({
    key,
    title: key === LEAD ? labels.learner : "",
    sortable: false,
    "default": true,
    active: true,
    fixed: key === LEAD
  }));
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-comp-rview-root", ref: root, "data-region": "dim-comp-rview-view", children: [
    /* @__PURE__ */ jsxDEV(
      Band,
      {
        labels,
        summary,
        loading: bandloading || writing,
        error: banderror,
        onRetry: () => loadBand(query.current)
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
        lineNumber: 504,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      Bar,
      {
        labels,
        narrow,
        total: rows === null ? null : rows.total,
        filters,
        columns: allcolumns.filter((column) => !column.key.startsWith("identity:")),
        maxcolumns: props.maxcolumns,
        usedefault,
        haswidths: Object.keys(widths).length > 0,
        view,
        search: searchtext,
        searchmin: props.searchmin,
        searching: rowsloading,
        tableurl: props.tableurl,
        downloadurl: props.downloadurl,
        formats: props.formats,
        moduleid: props.moduleid,
        groupid: props.groupid,
        appliedsearch: current.search,
        downloadable: rows !== null && rows.total > 0,
        onSearch,
        onFilter: (filter, value) => writeFilters(() => setFilters(reportid, target, withChoice(filters, filter, value))),
        onRemoveFilter: (filter) => writeFilters(() => setFilters(reportid, target, without(filters, filter))),
        onClearFilters: () => writeFilters(() => resetFilters(reportid, target)),
        onClearAll: () => {
          setSearchtext("");
          query.current = { ...query.current, search: "" };
          if (filters.some((filter) => filter.applied)) {
            writeFilters(() => resetFilters(reportid, target));
          } else {
            run({ page: 0 }, true);
          }
        },
        onColumn,
        onDefaultColumns: () => {
          setWidths({});
          if (rows) {
            setRows({ ...rows, preference: { usedefault: true, columns: [], widths: [] } });
          }
          writeThenRead(
            "theme_dimensions/classreport:columns",
            () => setPreference(props.courseid, { usedefault: true, columns: [], widths: [] }),
            {}
          );
        },
        onResetWidths: () => {
          setWidths({});
          const release = hold("theme_dimensions/classreport:width");
          setPreference(props.courseid, preferenceOf(usedefault, chosenkeys, {})).catch(() => void 0).finally(release);
        },
        onView: setView
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
        lineNumber: 511,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      RowsRegion,
      {
        labels,
        loading: rowsloading || writing,
        loaded: rows !== null,
        error: rowserror,
        total: rows?.total ?? 0,
        filtered,
        cards,
        narrow,
        skeleton: { columns: skeleton, lead: LEAD, widths, sizing: SIZING },
        onRetry: () => loadRows(query.current, true),
        children: body
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
        lineNumber: 562,
        columnNumber: 13
      }
    ),
    rows !== null && rows.total > 0 ? /* @__PURE__ */ jsxDEV(
      Pager,
      {
        labels,
        page: current.page,
        perpage: current.perpage,
        perpages: props.perpages,
        total: rows.total,
        shown: rows.rows.length,
        narrow,
        onPage: (page) => run({ page }, false),
        onPerpage: (perpage) => run({ perpage, page: 0 }, false)
      },
      void 0,
      false,
      {
        fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
        lineNumber: 577,
        columnNumber: 17
      }
    ) : null
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/classreport/ClassReportView.tsx",
    lineNumber: 503,
    columnNumber: 9
  });
}, "ClassReportView");
var ClassReportView_default = ClassReportView;
export {
  RATED,
  ROOT,
  ClassReportView_default as default,
  preferenceOf
};
//# sourceMappingURL=ClassReportView.dev.js.map
