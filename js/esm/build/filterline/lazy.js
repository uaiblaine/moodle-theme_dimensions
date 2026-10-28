/**
 * The filter line's preview, imported on first use: the first pointer or focus on the toolbar, or the first
 * suggestion walked to (P0 of docs/vitrine-react/PLAN.md, as amd/src/categoryline.js loads categorypreview).
 *
 * One function in a module of its own, because the browser runs the dynamic import and Jest cannot: core's
 * ts-jest compiles the tree as CommonJS under node16, which keeps import() native, and Jest's runtime
 * refuses a native import() without --experimental-vm-modules. The component's suite stands in for this
 * module; everything else of the preview is tested as it ships.
 *
 * @module     theme_dimensions/filterline/lazy
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const e=()=>import("./preview");export{e as importPreview};
