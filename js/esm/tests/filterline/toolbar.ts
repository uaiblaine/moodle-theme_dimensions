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
 * The toolbar the server draws, as the filter line's component finds it.
 *
 * templates/category_toolbar.mustache rendered from its own example context (the line's part has no
 * helper; the chips row's one string is written out), so the component is tested against the markup it
 * replaces. filterline_component_source_test renders the template through Moodle and holds the line of
 * this copy equal to it: change the template, render it again, and paste it here.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The form, as the template draws it. */
export const TOOLBAR = `<form class="dim-toolbar" id="dim-vitrine-form-7" method="get" action="https://example.com/course/index.php" data-region="dim-vitrine-toolbar">
    <input type="hidden" name="categoryid" value="7">
    <input type="hidden" name="perpage" value="24">
    <input type="hidden" name="open" value="8" data-region="dim-open-field">
    <input type="hidden" name="psort" value="name" data-region="dim-psort-field">
    <input type="hidden" name="fav" value="1" data-region="dim-fav-field" disabled>
    <button type="submit" class="visually-hidden dim-toolbar__defaultsubmit" tabindex="-1">Filter</button>
    <div class="dim-line" data-region="dim-line">
        <fieldset class="dim-toolbar__group dim-line__scope" data-region="dim-vitrine-group" data-name="chip" data-role="scope">
            <legend class="visually-hidden">Status</legend>
            <div class="dim-line__cells" data-region="dim-pill-track">
                <input class="dim-pill__input visually-hidden" type="radio" name="chip" id="dim-chip-7-all" value="">
                <label class="dim-pill" for="dim-chip-7-all">All <span class="dim-pill__count" data-region="dim-pill-count">14</span></label>
                <input class="dim-pill__input visually-hidden" type="radio" name="chip" id="dim-chip-7-enrolled" value="enrolled">
                <label class="dim-pill" for="dim-chip-7-enrolled">My courses <span class="dim-pill__count" data-region="dim-pill-count">3</span></label>
                <input class="dim-pill__input visually-hidden" type="radio" name="chip" id="dim-chip-7-open" value="open" hidden>
                <label class="dim-pill" for="dim-chip-7-open" hidden>Open <span class="dim-pill__count" data-region="dim-pill-count">0</span></label>
            </div>
        </fieldset>
        <span class="dim-line__spacer" aria-hidden="true"></span>
        <div class="dim-line__cluster" data-region="dim-line-cluster">
            <span class="dim-line__total" data-region="dim-line-total" aria-hidden="true">16</span>
            <div class="dim-line__capsule" data-region="dim-line-capsule">
                <button type="submit" class="dim-toolbar__star dim-line__iconbtn" name="fav" value="1" aria-pressed="false" data-dim-tip="Favourites" data-action="dim-fav" data-region="dim-line-star"><i class="fa-regular fa-star" aria-hidden="true"></i><span class="visually-hidden">Favourites <span data-region="dim-fav-count">2</span></span></button>
                <details class="dim-toolbar__filters dim-line__filters" data-region="dim-filters">
                    <summary class="dim-toolbar__filterstoggle dim-line__iconbtn" data-dim-tip="Filters"><i class="fa fa-sliders" aria-hidden="true"></i> <span class="visually-hidden dim-line__label dim-toolbar__filterslabel">Filters</span> <span class="dim-toolbar__filtercount" data-region="dim-filtercount"><span aria-hidden="true" data-region="dim-filtercount-number">2</span><span class="visually-hidden" data-region="dim-filtercount-label">Applied filters: 2</span></span></summary>
                    <div class="dim-toolbar__panel" data-region="dim-filters-panel">
                        <fieldset class="dim-toolbar__group" data-region="dim-vitrine-group" data-name="chip" data-role="more">
                            <legend class="dim-toolbar__grouplabel">More statuses</legend>
                            <div class="dim-toolbar__pills" data-region="dim-pill-track">
                                <input class="dim-pill__input visually-hidden" type="radio" name="chip" id="dim-chip-7-pending" value="pending" checked>
                                <label class="dim-pill" for="dim-chip-7-pending">Under review <span class="dim-pill__count" data-region="dim-pill-count">1</span></label>
                            </div>
                        </fieldset>
                        <fieldset class="dim-toolbar__group" data-region="dim-vitrine-group" data-name="cf_12">
                            <legend class="dim-toolbar__grouplabel">Modality</legend>
                            <div class="dim-toolbar__pills" data-region="dim-pill-track">
                                <input class="dim-pill__input visually-hidden" type="radio" name="cf_12" id="dim-cf-12-any" value="0">
                                <label class="dim-pill" for="dim-cf-12-any">All</label>
                                <input class="dim-pill__input visually-hidden" type="radio" name="cf_12" id="dim-cf-12-1" value="1" checked>
                                <label class="dim-pill" for="dim-cf-12-1">Online <span class="dim-pill__count" data-region="dim-pill-count">9</span></label>
                                <input class="dim-pill__input visually-hidden" type="radio" name="cf_12" id="dim-cf-12-2" value="2">
                                <label class="dim-pill" for="dim-cf-12-2">In-person <span class="dim-pill__count" data-region="dim-pill-count">5</span></label>
                            </div>
                        </fieldset>
                        <div class="dim-toolbar__actions dim-toolbar__foot" data-region="dim-filters-foot">
                            <a class="btn btn-sm btn-outline-secondary dim-toolbar__footclear" href="https://example.com/course/index.php?categoryid=7" data-region="dim-filters-clear" data-action="dim-clear">Clear filters</a>
                            <button type="button" class="btn btn-sm btn-primary dim-toolbar__footshow dim-live-only" data-action="dim-filters-show" data-dim-template="Show {n}">Show 16</button>
                            <button type="submit" class="dim-toolbar__submit dim-nojs-only">Filter</button>
                        </div>
                    </div>
                </details>
                <details class="dim-line__date" data-region="dim-period">
                    <summary class="dim-line__iconbtn dim-line__datetoggle" aria-label="Start date: 17 September 2026 to 24 September 2026" data-dim-tip="Start date" data-dim-template="Start date: {period}" data-region="dim-period-button"><i class="fa fa-calendar-days" aria-hidden="true"></i><span class="dim-line__dot" data-region="dim-period-dot" aria-hidden="true"></span></summary>
                    <div class="dim-datelayer" data-region="dim-period-layer">
                        <fieldset class="dim-datelayer__group" data-region="dim-date" data-firstday="0" data-today="2026-09-18">
                            <legend class="dim-datelayer__head">Start date</legend>
                            <ul class="dim-toolbar__quick" role="list">
                                <li><a class="dim-quick" href="https://example.com/course/index.php?categoryid=7&amp;from=2026-09-17&amp;to=2026-09-17" data-action="dim-quick" data-from="2026-09-17" data-to="2026-09-17">Today</a></li>
                                <li><a class="dim-quick dim-quick--on" href="https://example.com/course/index.php?categoryid=7&amp;from=2026-09-14&amp;to=2026-09-20" data-action="dim-quick" data-from="2026-09-14" data-to="2026-09-20" aria-current="true">This week</a></li>
                            </ul>
                            <div class="dim-toolbar__daterange" data-region="dim-daterange"></div>
                            <div class="dim-toolbar__dates" data-region="dim-dateinputs">
                                <div class="dim-toolbar__datefield">
                                    <label class="dim-toolbar__datelabel" for="dim-datefrom-7">From</label>
                                    <input class="form-control dim-toolbar__dateinput" type="date" id="dim-datefrom-7" name="from" value="2026-09-17">
                                </div>
                                <div class="dim-toolbar__datefield">
                                    <label class="dim-toolbar__datelabel" for="dim-dateto-7">To</label>
                                    <input class="form-control dim-toolbar__dateinput" type="date" id="dim-dateto-7" name="to" value="2026-09-24">
                                </div>
                            </div>
                        </fieldset>
                        <div class="dim-datelayer__foot" data-region="dim-period-foot">
                            <a class="dim-datelayer__clear" href="https://example.com/course/index.php?categoryid=7&amp;q=rescue&amp;chip=pending&amp;cf_12=1" data-action="dim-period-clear">Clear period</a>
                            <button type="button" class="btn btn-sm btn-primary dim-datelayer__show dim-live-only" data-action="dim-period-show" data-dim-template="Show {n}">Show 16</button>
                            <button type="submit" class="dim-toolbar__submit dim-nojs-only">Filter</button>
                        </div>
                    </div>
                </details>
                <details class="dim-line__organise" data-region="dim-organise">
                    <summary class="dim-line__iconbtn dim-line__organisetoggle" data-dim-tip="Organise"><i class="fa fa-arrow-down-wide-short" aria-hidden="true"></i><span class="visually-hidden">Organise</span></summary>
                    <div class="dim-line__menu" data-region="dim-organise-menu">
                        <fieldset class="dim-toolbar__group dim-toolbar__group--sort" data-region="dim-sort" data-name="sort">
                            <legend class="dim-toolbar__grouplabel">Organise</legend>
                            <div class="dim-toolbar__pills">
                                <input class="dim-pill__input visually-hidden" type="radio" name="sort" id="dim-sort-7-category" value="category" checked>
                                <label class="dim-pill" for="dim-sort-7-category">By category</label>
                                <input class="dim-pill__input visually-hidden" type="radio" name="sort" id="dim-sort-7-name" value="name">
                                <label class="dim-pill" for="dim-sort-7-name">A-Z</label>
                                <input class="dim-pill__input visually-hidden" type="radio" name="sort" id="dim-sort-7-recent" value="recent">
                                <label class="dim-pill" for="dim-sort-7-recent">Recent</label>
                            </div>
                        </fieldset>
                        <button type="submit" class="dim-toolbar__sortsubmit dim-nojs-only">Apply</button>
                    </div>
                </details>
                <div class="dim-toolbar__view dim-line__view" role="group" aria-label="View" data-region="dim-view-row">
                    <button type="submit" class="dim-toolbar__viewbtn dim-line__iconbtn" data-action="dim-view" name="view" value="list" aria-pressed="false" data-dim-tip="List"><i class="fa fa-list" aria-hidden="true"></i><span class="visually-hidden">List</span></button>
                    <button type="submit" class="dim-toolbar__viewbtn dim-line__iconbtn" data-action="dim-view" name="view" value="cards" aria-pressed="true" data-dim-tip="Cards"><i class="fa fa-border-all" aria-hidden="true"></i><span class="visually-hidden">Cards</span></button>
                </div>
            </div>
        </div>
        <div class="dim-line__capsule dim-line__searchcap" data-region="dim-line-searchcap">
            <button type="button" class="dim-line__iconbtn dim-line__searchopen" data-action="dim-search-open" aria-controls="dim-vitrine-q-7" aria-expanded="false" data-dim-tip="Search courses"><i class="fa fa-magnifying-glass" aria-hidden="true"></i><span class="visually-hidden">Search courses</span></button>
            <div class="dim-toolbar__search dim-line__search" data-region="dim-line-search">
                <label class="visually-hidden" for="dim-vitrine-q-7">Search courses</label>
                <i class="fa fa-magnifying-glass dim-toolbar__searchicon" aria-hidden="true"></i>
                <input class="form-control dim-toolbar__input" type="search" id="dim-vitrine-q-7" name="q" value="rescue" placeholder="Search by name" autocomplete="off">
                <button type="button" class="dim-line__eraser dim-live-only" data-action="dim-search-clear" aria-label="Clear text" data-dim-tip="Clear text"><i class="fa fa-eraser" aria-hidden="true"></i></button>
            </div>
            <div class="dim-toolbar__manage" data-region="dim-line-manage"><div class="action-menu"><a href="#">Manage courses</a></div></div>
            <button type="button" class="dim-line__iconbtn dim-line__searchclose" data-action="dim-search-close" aria-label="Close search" data-dim-tip="Close search"><i class="fa fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="dim-line__suggest" id="dim-vitrine-suggest-7" data-region="dim-line-suggest" data-label-filters="Filters" data-label-start="Start with a filter" hidden></div>
    </div>
    <p class="dim-toolbar__notice" role="status" data-region="dim-toolbar-notice" hidden>Type at least 2 characters to search.</p>
    <div class="dim-line__chips" data-region="dim-line-chips">
        <p class="dim-vitrine__count" role="status" aria-live="polite" data-region="dim-vitrine-count"><b class="dim-vitrine__countnumber" data-region="dim-vitrine-countnumber">16</b> <span data-region="dim-vitrine-counttext">courses for “rescue”: 2 in this category and 14 in 4 areas</span></p>
        <div class="dim-line__chiplist" data-region="dim-line-chiplist"><a class="dim-chip" href="https://example.com/course/index.php?categoryid=7&amp;q=rescue&amp;chip=pending&amp;from=2026-09-17&amp;to=2026-09-24" data-action="dim-chip-remove" data-param="cf_12">Online <i class="fa fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Remove filter</span></a>
<a class="dim-chip" href="https://example.com/course/index.php?categoryid=7&amp;q=rescue&amp;cf_12=1&amp;from=2026-09-17&amp;to=2026-09-24" data-action="dim-chip-remove" data-param="chip">Under review <i class="fa fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Remove filter</span></a>
</div>
        <div class="dim-toolbar__pillbar" data-region="dim-daterange-pill"><a class="dim-chip" href="https://example.com/course/index.php?categoryid=7&amp;q=rescue&amp;chip=pending&amp;cf_12=1" data-action="dim-chip-remove" data-param="period">17 September 2026 to 24 September 2026 <i class="fa fa-xmark" aria-hidden="true"></i><span class="visually-hidden">Remove filter</span></a>
</div>
        <a class="dim-toolbar__clear" href="https://example.com/course/index.php?categoryid=7" data-region="dim-vitrine-clear" data-action="dim-clear">Clear all</a>
        <span class="dim-vitrine__seeall" data-region="dim-vitrine-seeall"><a href="https://example.com/course/index.php?categoryid=7&amp;q=rescue&amp;sort=recent">See all as a list</a></span>
        <span class="dim-vitrine__countnote" data-region="dim-vitrine-countnote" role="status" hidden><span data-region="dim-vitrine-countnotetext"></span></span>
    </div>
</form>`;
