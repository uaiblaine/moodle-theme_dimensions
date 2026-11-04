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
 * The showcase's shared listing, under the names the store and its two shells call it by.
 *
 * It exports what a shell calls and nothing else (listing_source_test).
 *
 * @module     theme_dimensions/listing/listing
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export {
    PARAM, buildUrl, catOf, dropCriterion, filterArgs, isGrouped, isRoot, narrows, pageSizeOf, periodOf, readUrl, rootOnly,
    sortOf, syncFold, withPanel,
} from './address';
export {writeUrl} from './history';
export {focusTarget, mountAreas, mountCalendar, mountLine, tellAnswer} from './mounts';
export {
    armTreebox, closestOf, markTree, paintChips, paintControls, paintCount, paintDates, paintDirect, paintEraser,
    paintFavToggle, paintFavourites, paintFilterBadge, paintGrid, paintGroups, paintPager, paintView, renderCards,
    restoreMore,
} from './paint';
