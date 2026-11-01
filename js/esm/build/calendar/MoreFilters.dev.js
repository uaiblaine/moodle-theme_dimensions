var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
import { jsxDEV } from "react/jsx-dev-runtime";
/**
 * The "More filters" panel (decision D5): the course model, the modality, the city, "only what I have to do" and
 * the colouring (decision D6).
 *
 * A disclosure: the button says how many filters are on, the panel applies every change at once (the page asks
 * the range again and remembers the state after a quiet), and "Clear filters" puts every filter of the panel
 * back, the colouring and the category included in neither.
 *
 * @module     theme_dimensions/calendar/MoreFilters
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { usePopover } from "./popover";
import { fill } from "./strings";
const filterCount = /* @__PURE__ */ __name((state) => state.model.length + state.modality.length + (state.city !== "" ? 1 : 0) + (state.actiononly ? 1 : 0), "filterCount");
const toggled = /* @__PURE__ */ __name((list, value, present) => present ? [...list.filter((other) => other !== value), value] : list.filter((other) => other !== value), "toggled");
const MoreFilters = /* @__PURE__ */ __name(({ strings, state, options, onChange }) => {
  const popover = usePopover();
  const count = filterCount(state);
  const panelid = "dim-cal-morepanel";
  const cities = options.city.includes(state.city) || state.city === "" ? options.city : [...options.city, state.city];
  const group = /* @__PURE__ */ __name((name, legend, choices, kept) => choices.length ? /* @__PURE__ */ jsxDEV("fieldset", { children: [
    /* @__PURE__ */ jsxDEV("legend", { children: legend }, void 0, false, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
      lineNumber: 85,
      columnNumber: 13
    }),
    choices.map((option) => /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__check", children: [
      /* @__PURE__ */ jsxDEV(
        "input",
        {
          type: "checkbox",
          name,
          value: option.value,
          checked: kept.includes(option.value),
          onChange: (event) => onChange({ [name]: toggled(kept, option.value, event.target.checked) })
        },
        void 0,
        false,
        {
          fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
          lineNumber: 88,
          columnNumber: 21
        }
      ),
      option.label
    ] }, option.value, true, {
      fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
      lineNumber: 87,
      columnNumber: 17
    }))
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
    lineNumber: 84,
    columnNumber: 9
  }) : null, "group");
  return /* @__PURE__ */ jsxDEV("div", { className: "dim-cal__picker", "data-region": "dim-cal-more", children: [
    /* @__PURE__ */ jsxDEV(
      "button",
      {
        ref: popover.button,
        type: "button",
        className: "btn btn-secondary dim-cal__pickerbtn",
        "aria-expanded": popover.open,
        "aria-controls": panelid,
        onClick: popover.toggle,
        children: [
          /* @__PURE__ */ jsxDEV("i", { className: "fa fa-sliders", "aria-hidden": "true" }, void 0, false, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
            lineNumber: 111,
            columnNumber: 17
          }),
          strings.calendar_morefilters,
          count ? /* @__PURE__ */ jsxDEV("span", { className: "dim-cal__badge", children: [
            /* @__PURE__ */ jsxDEV("span", { "aria-hidden": "true", children: count }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
              lineNumber: 115,
              columnNumber: 25
            }),
            /* @__PURE__ */ jsxDEV("span", { className: "visually-hidden", children: fill(strings.calendar_filtercount, count) }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
              lineNumber: 116,
              columnNumber: 25
            })
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
            lineNumber: 114,
            columnNumber: 21
          }) : null
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
        lineNumber: 103,
        columnNumber: 13
      }
    ),
    /* @__PURE__ */ jsxDEV(
      "div",
      {
        ref: popover.panel,
        className: "dim-cal__popover",
        id: panelid,
        role: "group",
        "aria-label": strings.calendar_morefilters,
        hidden: !popover.open,
        onKeyDown: popover.onKeyDown,
        children: [
          group("model", strings.calendar_model, options.model, state.model),
          group("modality", strings.calendar_modality, options.modality, state.modality),
          /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__field", children: [
            /* @__PURE__ */ jsxDEV("span", { children: strings.calendar_city }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
              lineNumber: 132,
              columnNumber: 21
            }),
            /* @__PURE__ */ jsxDEV(
              "select",
              {
                className: "form-select",
                value: state.city,
                onChange: (event) => onChange({ city: event.target.value }),
                children: [
                  /* @__PURE__ */ jsxDEV("option", { value: "", children: strings.calendar_cityany }, void 0, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
                    lineNumber: 138,
                    columnNumber: 25
                  }),
                  cities.map((city) => /* @__PURE__ */ jsxDEV("option", { value: city, children: city }, city, false, {
                    fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
                    lineNumber: 139,
                    columnNumber: 47
                  }))
                ]
              },
              void 0,
              true,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
                lineNumber: 133,
                columnNumber: 21
              }
            )
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
            lineNumber: 131,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__check", children: [
            /* @__PURE__ */ jsxDEV(
              "input",
              {
                type: "checkbox",
                checked: state.actiononly,
                onChange: (event) => onChange({ actiononly: event.target.checked })
              },
              void 0,
              false,
              {
                fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
                lineNumber: 143,
                columnNumber: 21
              }
            ),
            strings.calendar_actiononly
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
            lineNumber: 142,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV("fieldset", { children: [
            /* @__PURE__ */ jsxDEV("legend", { children: strings.calendar_colourby }, void 0, false, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
              lineNumber: 151,
              columnNumber: 21
            }),
            ["key", "category"].map((colour) => /* @__PURE__ */ jsxDEV("label", { className: "dim-cal__check", children: [
              /* @__PURE__ */ jsxDEV(
                "input",
                {
                  type: "radio",
                  name: "dim-cal-colour",
                  value: colour,
                  checked: state.colour === colour,
                  onChange: () => onChange({ colour })
                },
                void 0,
                false,
                {
                  fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
                  lineNumber: 154,
                  columnNumber: 29
                }
              ),
              colour === "key" ? strings.calendar_colourbykey : strings.calendar_colourbycategory
            ] }, colour, true, {
              fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
              lineNumber: 153,
              columnNumber: 25
            }))
          ] }, void 0, true, {
            fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
            lineNumber: 150,
            columnNumber: 17
          }),
          /* @__PURE__ */ jsxDEV(
            "button",
            {
              type: "button",
              className: "btn btn-link dim-cal__clear",
              disabled: count === 0,
              onClick: () => onChange({ model: [], modality: [], city: "", actiononly: false }),
              children: strings.calendar_clearfilters
            },
            void 0,
            false,
            {
              fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
              lineNumber: 165,
              columnNumber: 17
            }
          )
        ]
      },
      void 0,
      true,
      {
        fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
        lineNumber: 120,
        columnNumber: 13
      }
    )
  ] }, void 0, true, {
    fileName: "public/theme/dimensions/js/esm/src/calendar/MoreFilters.tsx",
    lineNumber: 102,
    columnNumber: 9
  });
}, "MoreFilters");
var MoreFilters_default = MoreFilters;
export {
  MoreFilters_default as default,
  filterCount
};
//# sourceMappingURL=MoreFilters.dev.js.map
