var __defProp = Object.defineProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
/**
 * The favourite star of the category showcase's course cards, wherever a card is drawn.
 *
 * A card's star lives on the listing's grid, inside a panel between the posters and on the root's panels; the
 * account's shell arms it on every page of the logged-in showcase, the root's directory included ({@see ./account
 * start}). One delegated listener on the document, never one per card, so a card painted into a panel or appended
 * by "load more" behaves like a card the server drew; arm() is idempotent.
 *
 * The body class it writes is its own mark and shows the star; one module's mark never stands for another's
 * presence. The visitor's page never loads it (categorypublic_source_test).
 *
 * @module     theme_dimensions/listing/star
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import { load } from "../amd";
const SELECTORS = {
  star: '[data-action="dim-star"]',
  starlabel: '[data-region="dim-star-label"]'
};
const BODY_CLASS = "dim-star-js";
const TOGGLE_METHOD = "theme_dimensions_toggle_wishlist";
let armed = false;
const paintStar = /* @__PURE__ */ __name((button, on) => {
  button.setAttribute("aria-pressed", on ? "true" : "false");
  const icon = button.querySelector("i");
  if (icon) {
    icon.classList.toggle("fa-solid", on);
    icon.classList.toggle("fa-regular", !on);
  }
  const label = button.querySelector(SELECTORS.starlabel);
  const text = on ? button.dataset.starRemove : button.dataset.starAdd;
  if (label && text) {
    label.textContent = text;
  }
}, "paintStar");
const toggleStar = /* @__PURE__ */ __name(async (button) => {
  if (button.dataset.busy === "1") {
    return;
  }
  button.dataset.busy = "1";
  const courseid = Number(button.dataset.courseid);
  const wanted = button.getAttribute("aria-pressed") !== "true";
  paintStar(button, wanted);
  try {
    const response = await fetchOne({
      methodname: TOGGLE_METHOD,
      args: { courseid, state: wanted }
    });
    paintStar(button, Boolean(response.favourite));
  } catch (error) {
    paintStar(button, !wanted);
    const Notification = await load("core/notification");
    Notification.exception(error);
  } finally {
    button.dataset.busy = "0";
  }
}, "toggleStar");
const arm = /* @__PURE__ */ __name(() => {
  document.body.classList.add(BODY_CLASS);
  if (armed) {
    return;
  }
  armed = true;
  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }
    const star = target.closest(SELECTORS.star);
    if (!star) {
      return;
    }
    event.preventDefault();
    toggleStar(star);
  });
}, "arm");
export {
  arm
};
//# sourceMappingURL=star.dev.js.map
