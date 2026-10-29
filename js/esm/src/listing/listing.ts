// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The showcase's shared listing, under the names its AMD clients import it by.
 *
 * The AMD clients reach it through `core/esm!@moodle/lms/theme_dimensions/listing/listing` (R4b1 of
 * docs/vitrine-react/R4b.md). It goes with the last AMD client (R4b3); the store imports the modules directly.
 *
 * @module     theme_dimensions/listing/listing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export {
    PARAM, SCOPE, buildUrl, catOf, chipOf, dropCriterion, favOf, filterArgs, isGrouped, isRoot, narrows, pageSizeOf,
    periodOf, readUrl, rootOnly, sortOf, syncFold, withPanel,
} from './address';
export {LINE} from './events';
export {writeUrl} from './history';
export {focusTarget, mountAreas, mountCalendar, mountLine, tellAnswer} from './mounts';
export {
    armTreebox, closestOf, markTree, paintChips, paintControls, paintCount, paintDates, paintDirect, paintEraser,
    paintFavToggle, paintFavourites, paintFilterBadge, paintGrid, paintGroups, paintPager, paintView, renderCards,
    restoreMore,
} from './paint';
