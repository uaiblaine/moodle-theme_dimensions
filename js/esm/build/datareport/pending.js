import r from"@moodle/lms/core/pending";/**
 * A hold on Moodle's pending-JavaScript counter, the one Behat waits on, taken at once and released once.
 *
 * Core's ajax holds the counter only while a request is in flight; between its answer and the render that
 * draws it, and across a write followed by the reads it causes, nothing would be pending and Behat would read
 * the page as settled before the rows exist. Core 5.3's Pending (`@moodle/lms/core/pending`) takes the counter
 * in its constructor, synchronously. local_evidencehub's copy calls M.util itself; the theme's tree uses core's
 * module, as its other components do.
 *
 * @module     theme_dimensions/datareport/pending
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const i=n=>{const o=new r(n);let e=!0;return()=>{e&&(e=!1,o.resolve())}};export{i as hold};
