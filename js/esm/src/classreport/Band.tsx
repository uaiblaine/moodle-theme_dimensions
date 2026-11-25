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
 * The class report's band: the figures of the listed learners over the shown competency columns.
 *
 * Four tiles - the learners; the ratings given, of all the cells (learners times shown columns), with the
 * percentage as the sub-line; the proficient cells, with theirs; the learners with no rating in any shown
 * column - and one panel, "By competency": a line per shown competency in the columns' order, its name,
 * "{proficient} of {learners} proficient" and a meter of that share (progress-bar.md). No tile is a link and no
 * line a filter. A figure with no value reads "–": a share of no cell, the figures over no competency column,
 * a competency's line over no learner (which draws no meter, never "0 %").
 *
 * Every number is the server's (classreportengine::summary()), over the rows the rows call lists: the group,
 * the search and the stored filters included. Shaped after local_evidencehub's hub/Band.tsx.
 *
 * @module     theme_dimensions/classreport/Band
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type {ReactElement} from 'react';
import {fill, fillObject, num} from '../datareport/str';
import type {CompetencyFigures, SummaryAnswer} from './types';

/** What a figure without a value reads (data-report.md, "Empty"). */
export const NONE = '–';

type BandProps = {
    labels: Record<string, string>,
    summary: SummaryAnswer | null,
    loading: boolean,
    error: boolean,
    onRetry: () => void,
};

/**
 * A share as a whole percentage, or null when there is nothing to take it of.
 *
 * @param {number} part The part.
 * @param {number} whole The whole.
 * @returns {number|null} 0 to 100, or null.
 */
export const percent = (part: number, whole: number): number | null =>
    (whole > 0 ? Math.round(Math.min(1, Math.max(0, part / whole)) * 100) : null);

/**
 * One tile.
 *
 * @param {object} props The label, the value and the line under it.
 * @returns {object} The tile.
 */
const Tile = ({label, value, sub, name}: {label: string, value: string, sub: string, name: string}): ReactElement => (
    <div className="dim-comp-rview-tile" data-tile={name}>
        <div className="dim-comp-rview-tile-label">{label}</div>
        <div className="dim-comp-rview-tile-value">{value}</div>
        <div className="dim-comp-rview-tile-sub">{sub}</div>
    </div>
);

/**
 * One competency's line: its name, its sentence and its meter.
 *
 * @param {object} props The strings, the competency's figures and the learners listed.
 * @returns {object} The line.
 */
const CompetencyLine = ({labels, figures, learners}: {labels: Record<string, string>, figures: CompetencyFigures,
        learners: number}): ReactElement => {
    const share = percent(figures.proficient, learners);

    return (
        <li className="dim-comp-rview-hitem" data-competency={figures.competencyid}>
            <span className="dim-comp-rview-hrow-label" title={figures.title}>{figures.title}</span>
            <span className="dim-comp-rview-hrow-n">
                {share === null ? NONE : fillObject(labels.proficientof, {
                    proficient: num(figures.proficient),
                    learners: num(learners),
                })}
            </span>
            {share === null ? null : (
                <span className="dim-comp-rview-meter">
                    <span
                        className="dim-comp-rview-meter-track"
                        role="progressbar"
                        aria-valuenow={share}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={fillObject(labels.meterlabel, {title: figures.title, percent: num(share)})}
                    >
                        <span className="dim-comp-rview-meter-fill" style={{width: `${share}%`}} />
                    </span>
                    <span className="dim-comp-rview-meter-pct" aria-hidden="true">{fill(labels.percent, num(share))}</span>
                </span>
            )}
        </li>
    );
};

/**
 * The band's skeleton, in the shape it will settle to.
 *
 * @returns {object} The skeleton.
 */
const BandSkeleton = (): ReactElement => (
    <div aria-hidden="true">
        <div className="dim-comp-rview-band">
            {Array.from({length: 4}, (_, i) => (
                <div key={i} className="dim-comp-rview-tile dim-comp-rview-sk">
                    <span className="dim-comp-rview-skb dim-comp-rview-skb--w60" />
                    <span className="dim-comp-rview-skb dim-comp-rview-skb--big" />
                    <span className="dim-comp-rview-skb dim-comp-rview-skb--w80" />
                </div>
            ))}
        </div>
        <div className="dim-comp-rview-dist">
            <div className="dim-comp-rview-panel dim-comp-rview-sk">
                <span className="dim-comp-rview-skb dim-comp-rview-skb--w40" />
                <span className="dim-comp-rview-skb" />
                <span className="dim-comp-rview-skb" />
                <span className="dim-comp-rview-skb" />
            </div>
        </div>
    </div>
);

/**
 * The tiles' values and sub-lines for an answer.
 *
 * @param {object} labels The strings.
 * @param {object} summary The answer.
 * @returns {Array} Each tile's name, label, value and sub-line.
 */
export const tiles = (labels: Record<string, string>, summary: SummaryAnswer): {name: string, label: string, value: string,
        sub: string}[] => {
    const columns = summary.competencies.length;
    const rated = percent(summary.rated, summary.cells);
    const proficient = percent(summary.proficient, summary.cells);

    return [
        {
            name: 'learners',
            label: labels.learners,
            value: num(summary.learners),
            sub: fill(columns === 1 ? labels.learnerssub_one : labels.learnerssub, num(columns)),
        },
        {
            name: 'rated',
            label: labels.ratingsgiven,
            value: rated === null ? NONE : num(summary.rated),
            sub: rated === null ? NONE : fillObject(labels.ofcells, {percent: num(rated), cells: num(summary.cells)}),
        },
        {
            name: 'proficient',
            label: labels.proficienttile,
            value: proficient === null ? NONE : num(summary.proficient),
            sub: proficient === null ? NONE : fillObject(labels.ofcells, {percent: num(proficient), cells: num(summary.cells)}),
        },
        {
            name: 'unrated',
            label: labels.unratedtile,
            value: columns === 0 ? NONE : num(summary.unrated),
            sub: labels.unratedsub,
        },
    ];
};

/**
 * The band.
 *
 * @param {object} props See BandProps.
 * @returns {object} The band's region.
 */
export const Band = ({labels, summary, loading, error, onRetry}: BandProps): ReactElement => {
    const retry = (
        <p className="dim-comp-rview-error">
            {labels.loaderror}{' '}
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onRetry}>{labels.retry}</button>
        </p>
    );
    let body: ReactElement;
    if (summary === null && error) {
        body = retry;
    } else if (summary === null) {
        body = (
            <>
                <BandSkeleton />
                <span className="visually-hidden" role="status">{labels.loadband}</span>
            </>
        );
    } else {
        body = (
            <div className="dim-comp-rview-dim">
                {error ? retry : null}
                <div className="dim-comp-rview-band">
                    {tiles(labels, summary).map((tile) => (
                        <Tile key={tile.name} name={tile.name} label={tile.label} value={tile.value} sub={tile.sub} />
                    ))}
                </div>
                <div className="dim-comp-rview-dist">
                    <div className="dim-comp-rview-panel">
                        <div className="dim-comp-rview-panel-title"><span>{labels.bycompetency}</span></div>
                        {summary.competencies.length > 0 ? (
                            <ul className="dim-comp-rview-hlist">
                                {summary.competencies.map((figures) => (
                                    <CompetencyLine
                                        key={figures.key}
                                        labels={labels}
                                        figures={figures}
                                        learners={summary.learners}
                                    />
                                ))}
                            </ul>
                        ) : <p className="dim-comp-rview-note">{labels.nocompetencies}</p>}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <section
            className="dim-comp-rview-region dim-comp-rview-bandregion"
            aria-label={labels.bandlabel}
            aria-busy={loading}
            data-region="dim-comp-rview-band"
        >
            {loading && summary !== null ? <div className="dim-comp-rview-progress" /> : null}
            {body}
        </section>
    );
};
