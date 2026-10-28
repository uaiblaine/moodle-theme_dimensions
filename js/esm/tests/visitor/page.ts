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
 * The anonymous shell's toolbar and areas, as a visitor's public category page draws them.
 *
 * Captured from the page a 5.3 site served a visitor (category_toolbar and category_areas from the anonymous
 * shell's own export, 2026-10-28), with the site's address replaced. It is the visitor's markup, which the
 * logged-in fixtures are not: no scope, no favourites star or field, no managers' menu, the route as the
 * form's action, merely listed areas (an empty data-titleurl). Recapture it from a public category page
 * when the toolbar's or the posters' templates change.
 *
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** The id of the visitor's toolbar form. */
export const VISITOR_FORM = 'dim-vitrine-form-8';

/** The toolbar form, as the anonymous shell draws it. */
export const VISITOR_TOOLBAR = `<form class="dim-toolbar" id="dim-vitrine-form-8" method="get" action="https://example.com/theme_dimensions/category/8" data-region="dim-vitrine-toolbar">
        
        <button type="submit" class="visually-hidden dim-toolbar__defaultsubmit" tabindex="-1">Filter</button>
        <div class="dim-line" data-region="dim-line">
            <div class="dim-toolbar__search dim-line__search" data-region="dim-line-search">
                <label class="visually-hidden" for="dim-vitrine-q-8">Search courses</label>
                <i class="fa fa-magnifying-glass dim-toolbar__searchicon" aria-hidden="true"></i>
                <input class="form-control dim-toolbar__input" type="search" id="dim-vitrine-q-8" name="q" value="" placeholder="Search by name" autocomplete="off">
                <button type="button" class="dim-line__eraser dim-live-only" data-action="dim-search-clear" aria-label="Clear text" title="Clear text" hidden><i class="fa fa-eraser" aria-hidden="true"></i></button>
            </div>
            <button type="button" class="dim-line__iconbtn dim-line__searchopen" data-action="dim-search-open" aria-controls="dim-vitrine-q-8" aria-expanded="false" title="Search courses"><i class="fa fa-magnifying-glass" aria-hidden="true"></i><span class="visually-hidden">Search courses</span></button>
            <details class="dim-toolbar__filters dim-line__filters" data-region="dim-filters">
                <summary class="dim-toolbar__filterstoggle"><i class="fa fa-sliders" aria-hidden="true"></i> <span class="dim-line__label dim-toolbar__filterslabel">Filters</span> <span class="dim-toolbar__filtercount" data-region="dim-filtercount" hidden><span aria-hidden="true" data-region="dim-filtercount-number">0</span><span class="visually-hidden" data-region="dim-filtercount-label">Filters applied: 0</span></span></summary>
                <div class="dim-toolbar__panel" data-region="dim-filters-panel">
                    <fieldset class="dim-toolbar__group" data-region="dim-vitrine-group" data-name="cf_49">
                        <legend class="dim-toolbar__grouplabel">Modality</legend>
                        <div class="dim-toolbar__pills" data-region="dim-pill-track">
                            <input class="dim-pill__input visually-hidden" type="radio" name="cf_49" id="dim-cf-49-any" value="0" checked>
                            <label class="dim-pill" for="dim-cf-49-any">All</label>
                            <input class="dim-pill__input visually-hidden" type="radio" name="cf_49" id="dim-cf-49-1" value="1">
                            <label class="dim-pill" for="dim-cf-49-1">Online <span class="dim-pill__count" data-region="dim-pill-count">100</span></label>
                        </div>
                    </fieldset>
                    <fieldset class="dim-toolbar__group" data-region="dim-vitrine-group" data-name="cf_51">
                        <legend class="dim-toolbar__grouplabel">Study format</legend>
                        <div class="dim-toolbar__pills" data-region="dim-pill-track">
                            <input class="dim-pill__input visually-hidden" type="radio" name="cf_51" id="dim-cf-51-any" value="0" checked>
                            <label class="dim-pill" for="dim-cf-51-any">All</label>
                            <input class="dim-pill__input visually-hidden" type="radio" name="cf_51" id="dim-cf-51-1" value="1">
                            <label class="dim-pill" for="dim-cf-51-1">Self-paced <span class="dim-pill__count" data-region="dim-pill-count">100</span></label>
                        </div>
                    </fieldset>
                    <fieldset class="dim-toolbar__group dim-toolbar__group--date" data-region="dim-date" data-firstday="1" data-today="2026-10-28">
                        <legend class="dim-toolbar__grouplabel">Start date</legend>
                        <div class="dim-toolbar__daterange" data-region="dim-daterange"></div>
                        <div class="dim-toolbar__dates" data-region="dim-dateinputs">
                            <div class="dim-toolbar__datefield">
                                <label class="dim-toolbar__datelabel" for="dim-datefrom-8">From</label>
                                <input class="form-control dim-toolbar__dateinput" type="date" id="dim-datefrom-8" name="from" value="">
                            </div>
                            <div class="dim-toolbar__datefield">
                                <label class="dim-toolbar__datelabel" for="dim-dateto-8">To</label>
                                <input class="form-control dim-toolbar__dateinput" type="date" id="dim-dateto-8" name="to" value="">
                            </div>
                        </div>
                        <ul class="dim-toolbar__quick" role="list">
                            <li><a class="dim-quick" href="https://example.com/theme_dimensions/category/8?from=2026-10-28&amp;to=2026-10-28" data-action="dim-quick" data-from="2026-10-28" data-to="2026-10-28">Today</a></li>
                            <li><a class="dim-quick" href="https://example.com/theme_dimensions/category/8?from=2026-10-29&amp;to=2026-10-29" data-action="dim-quick" data-from="2026-10-29" data-to="2026-10-29">Tomorrow</a></li>
                            <li><a class="dim-quick" href="https://example.com/theme_dimensions/category/8?from=2026-10-26&amp;to=2026-11-01" data-action="dim-quick" data-from="2026-10-26" data-to="2026-11-01">This week</a></li>
                            <li><a class="dim-quick" href="https://example.com/theme_dimensions/category/8?from=2026-11-02&amp;to=2026-11-08" data-action="dim-quick" data-from="2026-11-02" data-to="2026-11-08">Next week</a></li>
                        </ul>
                    </fieldset>
                    <div class="dim-toolbar__actions">
                        <button type="submit" class="dim-toolbar__submit dim-nojs-only">Filter</button>
                    </div>
                </div>
            </details>
            <details class="dim-line__organise" data-region="dim-organise">
                <summary class="dim-line__iconbtn dim-line__organisetoggle" title="Organise"><i class="fa fa-arrow-down-wide-short" aria-hidden="true"></i><span class="visually-hidden">Organise</span></summary>
                <div class="dim-line__menu" data-region="dim-organise-menu">
                    <fieldset class="dim-toolbar__group dim-toolbar__group--sort" data-region="dim-sort" data-name="sort">
                        <legend class="dim-toolbar__grouplabel">Organise</legend>
                        <div class="dim-toolbar__pills">
                            <input class="dim-pill__input visually-hidden" type="radio" name="sort" id="dim-sort-8-category" value="category" checked>
                            <label class="dim-pill" for="dim-sort-8-category">By area</label>
                            <input class="dim-pill__input visually-hidden" type="radio" name="sort" id="dim-sort-8-name" value="name">
                            <label class="dim-pill" for="dim-sort-8-name">A-Z</label>
                            <input class="dim-pill__input visually-hidden" type="radio" name="sort" id="dim-sort-8-recent" value="recent">
                            <label class="dim-pill" for="dim-sort-8-recent">Recent</label>
                        </div>
                    </fieldset>
                    <button type="submit" class="dim-toolbar__sortsubmit dim-nojs-only">Apply</button>
                    <div class="dim-line__showas" role="group" aria-labelledby="dim-vitrine-form-8-showas" data-region="dim-showas">
                        <span class="dim-toolbar__grouplabel" id="dim-vitrine-form-8-showas">Show as</span>
                        <button type="button" class="dim-line__showasbtn" data-action="dim-view" value="list" aria-pressed="false"><i class="fa fa-list" aria-hidden="true"></i> List</button>
                        <button type="button" class="dim-line__showasbtn" data-action="dim-view" value="cards" aria-pressed="true"><i class="fa fa-border-all" aria-hidden="true"></i> Cards</button>
                    </div>
                </div>
            </details>
            <div class="dim-toolbar__view dim-line__view" role="group" aria-label="View" data-region="dim-view-row">
                <button type="submit" class="dim-toolbar__viewbtn" data-action="dim-view" name="view" value="list" aria-pressed="false" title="List"><i class="fa fa-list" aria-hidden="true"></i><span class="visually-hidden">List</span></button>
                <button type="submit" class="dim-toolbar__viewbtn" data-action="dim-view" name="view" value="cards" aria-pressed="true" title="Cards"><i class="fa fa-border-all" aria-hidden="true"></i><span class="visually-hidden">Cards</span></button>
            </div>
            <button type="button" class="dim-line__iconbtn dim-line__searchclose" data-action="dim-search-close" aria-label="Close search" title="Close search"><i class="fa fa-xmark" aria-hidden="true"></i></button>
            <div class="dim-line__suggest" id="dim-vitrine-suggest-8" data-region="dim-line-suggest" data-label-filters="Filters" data-label-start="Start with a filter" hidden></div>
        </div>
        <p class="dim-toolbar__notice" role="status" data-region="dim-toolbar-notice" hidden>Type at least 2 characters to search.</p>
        <div class="dim-line__chips dim-line__chips--rest" data-region="dim-line-chips">
            <p class="dim-vitrine__count visually-hidden" role="status" aria-live="polite" data-region="dim-vitrine-count"><b class="dim-vitrine__countnumber" data-region="dim-vitrine-countnumber">100</b> <span data-region="dim-vitrine-counttext">courses in Probe area 1</span></p>
            <div class="dim-line__chiplist" data-region="dim-line-chiplist"></div>
            <div class="dim-toolbar__pillbar" data-region="dim-daterange-pill"></div>
            <a class="dim-toolbar__clear" href="https://example.com/theme_dimensions/category/8" data-region="dim-vitrine-clear" data-action="dim-clear" hidden>Clear all</a>
            <span class="dim-vitrine__seeall" data-region="dim-vitrine-seeall" hidden><a href="">See all as a list</a></span>
            <span class="dim-vitrine__countnote" data-region="dim-vitrine-countnote" role="status" hidden><span data-region="dim-vitrine-countnotetext"></span></span>
        </div>
    </form>`;

/** The areas section, as the anonymous shell draws it: five merely listed areas, no panel open. */
export const VISITOR_AREAS = `<section class="dim-areas" data-region="dim-areas" data-categoryid="8" data-filtered="0" data-elsewhere="" aria-labelledby="dim-areas-title">
                <div class="dim-areas__head">
                    <h2 class="dim-areas__title" id="dim-areas-title">Areas in Probe area 1</h2>
                    <a class="dim-areas__skip" href="#dim-direct-title" data-region="dim-direct-skip" hidden>Go to this category&#039;s courses</a>
                </div>
                <ul class="dim-vitrine__grid dim-areas__grid" role="list" data-region="dim-areas-grid">
                    <li class="dim-areas__item"><article class="dim-poster dim-poster--text" id="dim-area-9" data-region="dim-area" data-categoryid="9" data-name="Probe unit 1.0" data-titleurl="" data-ctaurl="https://example.com/theme_dimensions/category/8?sort=recent&amp;cat=9" data-ctalabel="See all as a list" data-expandable="1" data-count="4" data-resultcount="" data-resultlabel="" style="--dim-card-band: #0a4a85; --dim-card-ink: #ffffff;">
    <div class="dim-poster__card" data-region="dim-cover-media">
        <div class="dim-poster__media">
        </div>
        <div class="dim-poster__top">
            <span class="dim-poster__nocrests"></span>
            <span class="dim-poster__tags"><span class="dim-poster__kind">Category</span><span class="dim-poster__open" aria-hidden="true"><i class="fa-solid fa-eye" aria-hidden="true"></i>Open</span><span class="dim-poster__pill" id="dim-area-results-9" data-region="dim-poster-pill" hidden></span></span>
        </div>
        <span class="dim-poster__veil"></span>
        <div class="dim-poster__base">
            <h3 class="dim-poster__name">
                <a class="dim-poster__control" href="https://example.com/theme_dimensions/category/8?open=9#dim-panel" data-region="dim-poster-control"><span class="dim-poster__nm">Probe unit 1.0</span><span class="visually-hidden" data-region="dim-poster-emptynote" hidden> (no courses yet; opens the area's page)</span></a>
            </h3>
            <span class="dim-poster__trig" aria-hidden="true"><span class="dim-poster__trigtext" data-region="dim-poster-trigger" data-open="View courses" data-close="Hide courses" data-empty="No courses yet">View courses</span><span class="dim-poster__chev"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></span>
        </div>
    </div>
</article>
</li>
                    <li class="dim-areas__item"><article class="dim-poster dim-poster--text" id="dim-area-41" data-region="dim-area" data-categoryid="41" data-name="Probe unit 1.1" data-titleurl="" data-ctaurl="https://example.com/theme_dimensions/category/8?sort=recent&amp;cat=41" data-ctalabel="See all as a list" data-expandable="1" data-count="24" data-resultcount="" data-resultlabel="" style="--dim-card-band: #0a4a85; --dim-card-ink: #ffffff;">
    <div class="dim-poster__card" data-region="dim-cover-media">
        <div class="dim-poster__media">
        </div>
        <div class="dim-poster__top">
            <span class="dim-poster__nocrests"></span>
            <span class="dim-poster__tags"><span class="dim-poster__kind">Category</span><span class="dim-poster__open" aria-hidden="true"><i class="fa-solid fa-eye" aria-hidden="true"></i>Open</span><span class="dim-poster__pill" id="dim-area-results-41" data-region="dim-poster-pill" hidden></span></span>
        </div>
        <span class="dim-poster__veil"></span>
        <div class="dim-poster__base">
            <h3 class="dim-poster__name">
                <a class="dim-poster__control" href="https://example.com/theme_dimensions/category/8?open=41#dim-panel" data-region="dim-poster-control"><span class="dim-poster__nm">Probe unit 1.1</span><span class="visually-hidden" data-region="dim-poster-emptynote" hidden> (no courses yet; opens the area's page)</span></a>
            </h3>
            <span class="dim-poster__trig" aria-hidden="true"><span class="dim-poster__trigtext" data-region="dim-poster-trigger" data-open="View courses" data-close="Hide courses" data-empty="No courses yet">View courses</span><span class="dim-poster__chev"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></span>
        </div>
    </div>
</article>
</li>
                    <li class="dim-areas__item"><article class="dim-poster dim-poster--text" id="dim-area-49" data-region="dim-area" data-categoryid="49" data-name="Probe unit 1.9" data-titleurl="" data-ctaurl="https://example.com/theme_dimensions/category/8?sort=recent&amp;cat=49" data-ctalabel="See all as a list" data-expandable="1" data-count="24" data-resultcount="" data-resultlabel="" style="--dim-card-band: #0a4a85; --dim-card-ink: #ffffff;">
    <div class="dim-poster__card" data-region="dim-cover-media">
        <div class="dim-poster__media">
        </div>
        <div class="dim-poster__top">
            <span class="dim-poster__nocrests"></span>
            <span class="dim-poster__tags"><span class="dim-poster__kind">Category</span><span class="dim-poster__open" aria-hidden="true"><i class="fa-solid fa-eye" aria-hidden="true"></i>Open</span><span class="dim-poster__pill" id="dim-area-results-49" data-region="dim-poster-pill" hidden></span></span>
        </div>
        <span class="dim-poster__veil"></span>
        <div class="dim-poster__base">
            <h3 class="dim-poster__name">
                <a class="dim-poster__control" href="https://example.com/theme_dimensions/category/8?open=49#dim-panel" data-region="dim-poster-control"><span class="dim-poster__nm">Probe unit 1.9</span><span class="visually-hidden" data-region="dim-poster-emptynote" hidden> (no courses yet; opens the area's page)</span></a>
            </h3>
            <span class="dim-poster__trig" aria-hidden="true"><span class="dim-poster__trigtext" data-region="dim-poster-trigger" data-open="View courses" data-close="Hide courses" data-empty="No courses yet">View courses</span><span class="dim-poster__chev"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></span>
        </div>
    </div>
</article>
</li>
                    <li class="dim-areas__item"><article class="dim-poster dim-poster--text" id="dim-area-53" data-region="dim-area" data-categoryid="53" data-name="Probe unit 1.13" data-titleurl="" data-ctaurl="https://example.com/theme_dimensions/category/8?sort=recent&amp;cat=53" data-ctalabel="See all as a list" data-expandable="1" data-count="24" data-resultcount="" data-resultlabel="" style="--dim-card-band: #0a4a85; --dim-card-ink: #ffffff;">
    <div class="dim-poster__card" data-region="dim-cover-media">
        <div class="dim-poster__media">
        </div>
        <div class="dim-poster__top">
            <span class="dim-poster__nocrests"></span>
            <span class="dim-poster__tags"><span class="dim-poster__kind">Category</span><span class="dim-poster__open" aria-hidden="true"><i class="fa-solid fa-eye" aria-hidden="true"></i>Open</span><span class="dim-poster__pill" id="dim-area-results-53" data-region="dim-poster-pill" hidden></span></span>
        </div>
        <span class="dim-poster__veil"></span>
        <div class="dim-poster__base">
            <h3 class="dim-poster__name">
                <a class="dim-poster__control" href="https://example.com/theme_dimensions/category/8?open=53#dim-panel" data-region="dim-poster-control"><span class="dim-poster__nm">Probe unit 1.13</span><span class="visually-hidden" data-region="dim-poster-emptynote" hidden> (no courses yet; opens the area's page)</span></a>
            </h3>
            <span class="dim-poster__trig" aria-hidden="true"><span class="dim-poster__trigtext" data-region="dim-poster-trigger" data-open="View courses" data-close="Hide courses" data-empty="No courses yet">View courses</span><span class="dim-poster__chev"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></span>
        </div>
    </div>
</article>
</li>
                    <li class="dim-areas__item"><article class="dim-poster dim-poster--text" id="dim-area-57" data-region="dim-area" data-categoryid="57" data-name="Probe unit 1.17" data-titleurl="" data-ctaurl="https://example.com/theme_dimensions/category/8?sort=recent&amp;cat=57" data-ctalabel="See all as a list" data-expandable="1" data-count="24" data-resultcount="" data-resultlabel="" style="--dim-card-band: #0a4a85; --dim-card-ink: #ffffff;">
    <div class="dim-poster__card" data-region="dim-cover-media">
        <div class="dim-poster__media">
        </div>
        <div class="dim-poster__top">
            <span class="dim-poster__nocrests"></span>
            <span class="dim-poster__tags"><span class="dim-poster__kind">Category</span><span class="dim-poster__open" aria-hidden="true"><i class="fa-solid fa-eye" aria-hidden="true"></i>Open</span><span class="dim-poster__pill" id="dim-area-results-57" data-region="dim-poster-pill" hidden></span></span>
        </div>
        <span class="dim-poster__veil"></span>
        <div class="dim-poster__base">
            <h3 class="dim-poster__name">
                <a class="dim-poster__control" href="https://example.com/theme_dimensions/category/8?open=57#dim-panel" data-region="dim-poster-control"><span class="dim-poster__nm">Probe unit 1.17</span><span class="visually-hidden" data-region="dim-poster-emptynote" hidden> (no courses yet; opens the area's page)</span></a>
            </h3>
            <span class="dim-poster__trig" aria-hidden="true"><span class="dim-poster__trigtext" data-region="dim-poster-trigger" data-open="View courses" data-close="Hide courses" data-empty="No courses yet">View courses</span><span class="dim-poster__chev"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span></span>
        </div>
    </div>
</article>
</li>
                    <li class="dim-panel" id="dim-panel" data-region="dim-panel" hidden></li>
                </ul>
                <p class="dim-areas__hint" data-region="dim-areas-hint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Tap an area to see its courses.</p>
                <div class="dim-areas__allempty" data-region="dim-areas-empty" hidden>
                    <p><b>No course matches the search and the filters.</b></p>
                    <p>Try another term or clear the filters.</p>
                    <p class="dim-areas__allemptyways">
                        <a class="dim-areas__allemptyclear" href="https://example.com/theme_dimensions/category/8" data-action="dim-clear">Clear filters</a>
                        <a class="dim-areas__searchall" href="" data-region="dim-areas-searchall" hidden>Search all areas</a>
                    </p>
                </div>
                <details class="dim-areas__nomatch" id="dim-nomatch" data-region="dim-areas-nomatch" data-label-all="" data-label-less="" hidden>
                    <summary class="dim-areas__nomatchsummary" data-region="dim-nomatch-summary"></summary>
                    <ul class="dim-areas__nomatchlist" role="list" data-region="dim-nomatch-list">
                    </ul>
                </details>
                <p class="visually-hidden" role="status" aria-live="polite" data-region="dim-panel-status"></p>
            </section>`;

/**
 * Draw the visitor's page: the listing with its toolbar and its areas.
 *
 * @returns {object} The form, the line and the areas section.
 */
export const drawVisitorPage = (): {form: HTMLFormElement, line: HTMLElement, areas: HTMLElement} => {
    document.body.className = 'dim-category dim-category-public notloggedin';
    document.body.innerHTML = `<section class="dim-vitrine dim-vitrine--cards" data-region="dim-vitrine">${VISITOR_TOOLBAR}${VISITOR_AREAS}</section>`;
    return {
        form: document.getElementById(VISITOR_FORM) as HTMLFormElement,
        line: document.querySelector('[data-region="dim-line"]') as HTMLElement,
        areas: document.querySelector('[data-region="dim-areas"]') as HTMLElement,
    };
};
