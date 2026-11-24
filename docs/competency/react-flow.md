# React Flow for the competency hub: feasibility

Status: research, 2026-10-19. Nothing installed or built. Sources: Moodle 5.3 core
(`~/dev/moodle-503`), `block_compass`, the npm registry and reactflow.dev.

## Facts

- Core 5.3 vendors React 19.2.4 (`lib/bundles/react`), mapped by the import map
  (`lib/classes/output/requirements/import_map.php`): `react`, `react-dom` and `@moodle/lms/<component>/`
  resolve to core's copies and to each component's `js/esm/build`. A plugin entry is not preloaded.
- Plugins write `.ts`/`.tsx` under `js/esm/src`, built by `grunt esm` (alias `grunt react`) into the
  tracked `js/esm/build`. Mounting: `html_writer::react_component()`, the Mustache helper
  `{{#react}}{"component": ..., "props": ...}{{/react}}`, or `react_component_renderable`;
  `react_autoinit` mounts every `[data-react-component]`.
- React exists from 5.2 on. The owner's scope (2026-10-19): this work, like the theme, is Moodle 5.3+
  only, so the React Flow hub lands on `local_dimensions` main (`MOODLE_503_STABLE`) alone; the
  405/501/502 branches keep today's AMD hub and get no port.
- block_compass (5.2/5.3) bundles only its own files with `moodle-dev/ci/esm-bundle.mjs`
  (`bundle: true`, `react` and `@moodle/lms/*` external) and imports no npm package besides React.
- `@xyflow/react` 12.12.0: MIT, peer `react >=17`, depends on zustand and `@xyflow/system`. It needs
  its stylesheet (`@xyflow/react/dist/style.css`). Built in: MiniMap, Controls, Background, custom
  nodes and edges, sub-flows; auto layout needs dagre or elkjs as separate packages. Keyboard:
  Tab focuses nodes and edges, Enter/Space selects, arrows move, `ariaLabelConfig` localises the
  screen-reader text. It renders DOM and SVG, so Selenium can reach nodes.

## Verdict

Feasible on 5.3 (the only target), in `local_dimensions` (the plugin owns the data and the services), with
the theme mounting the same component by its specifier when the plugin is installed
(`core_component::get_component_directory('local_dimensions')` first, or the import fails).

## Open risks (each needs a spike before a plan)

1. No core path for an npm dependency inside a plugin: where `@xyflow/react` is installed for
   development and CI, and how the bundle is kept fresh (block_compass's `bundle_test.php`
   pattern).
2. The library's CSS has to ship in the plugin's `styles.css` and pass core's stylelint and the
   plugin-before-theme specificity rule.
3. Accessibility: a canvas editor needs a list or table view beside it; keyboard edge creation is
   not confirmed; RTL not checked.
4. Behat: drag and pan are flaky; keep scenarios thin, logic in PHPUnit and Jest.
5. Bundle size not measured; loaded only on the pages that mount it.
6. The Moodle App has no React: the hub stays web-only.
