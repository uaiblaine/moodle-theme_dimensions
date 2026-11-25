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
 * The card view (AR-D9): the same rows, page and order as the grid, one article each.
 *
 * A card is a first line (who, and the row's action), the view's fixed lines, then the other chosen
 * fields in a list, at once on a wide screen (a grid of 17.5rem cards) and behind "More" on a narrow
 * one (one column). What each part holds is the view's (`renderCard`). Copied from local_evidencehub's
 * datareport/Cards.tsx without the selection variant.
 *
 * Strings read from the view's labels: more, less.
 *
 * @module     theme_dimensions/datareport/Cards
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useState} from 'react';
import type {ReactElement, ReactNode} from 'react';
import type {RowBase} from './types';

/** One field of a card's list. */
export type CardField = {key: string, title: string, content: ReactNode};

/** What a view draws in one card. */
export type CardContent = {
    /** The first line: who, and the row's action. */
    head: ReactNode,
    /** The fixed lines under it. */
    lines: ReactNode,
    /** The other chosen fields, each with something in it. */
    fields: CardField[],
};

type CardsProps<R extends RowBase> = {
    labels: Record<string, string>,
    rows: R[],
    single: boolean,
    renderCard: (row: R) => CardContent,
};

/**
 * One card.
 *
 * @param {object} props The strings, the row, whether the list folds and the renderer.
 * @returns {object} The article.
 */
const Card = <R extends RowBase>({labels, row, single, renderCard}: {labels: Record<string, string>, row: R,
        single: boolean, renderCard: (row: R) => CardContent}): ReactElement => {
    const [more, setMore] = useState(false);
    const content = renderCard(row);
    const shown = !single || more;

    return (
        <article className="dim-comp-rview-card" role="listitem" data-record={row.id}>
            <div className="dim-comp-rview-card-l1">{content.head}</div>
            {content.lines}
            {content.fields.length > 0 ? (
                <div className="dim-comp-rview-card-more">
                    {single ? (
                        <button
                            type="button"
                            className="btn btn-link btn-sm dim-comp-rview-linkbtn"
                            aria-expanded={more}
                            onClick={() => setMore(!more)}
                        >
                            {more ? labels.less : labels.more}
                        </button>
                    ) : null}
                    {shown ? (
                        <dl className="dim-comp-rview-card-list">
                            {content.fields.map((field) => (
                                <div key={field.key} className="dim-comp-rview-card-item">
                                    <dt>{field.title}</dt>
                                    <dd>{field.content}</dd>
                                </div>
                            ))}
                        </dl>
                    ) : null}
                </div>
            ) : null}
        </article>
    );
};

/**
 * The cards.
 *
 * @param {object} props See CardsProps.
 * @returns {object} The list of cards.
 */
export const Cards = <R extends RowBase>({labels, rows, single, renderCard}: CardsProps<R>): ReactElement => (
    <div className={'dim-comp-rview-cards' + (single ? ' dim-comp-rview-cards--single' : '')} role="list">
        {rows.map((row) => (
            <Card key={row.id} labels={labels} row={row} single={single} renderCard={renderCard} />
        ))}
    </div>
);

/**
 * The cards' skeleton.
 *
 * @param {object} props Whether the cards are one column.
 * @returns {object} The skeleton.
 */
export const CardsSkeleton = ({single}: {single: boolean}): ReactElement => (
    <div className={'dim-comp-rview-cards' + (single ? ' dim-comp-rview-cards--single' : '')} aria-hidden="true">
        {Array.from({length: single ? 4 : 6}, (_, i) => (
            <div key={i} className="dim-comp-rview-card dim-comp-rview-sk">
                <span className="dim-comp-rview-skb dim-comp-rview-skb--w60" />
                <span className="dim-comp-rview-skb dim-comp-rview-skb--w80" />
                <span className="dim-comp-rview-skb dim-comp-rview-skb--w60" />
                <span className="dim-comp-rview-skb dim-comp-rview-skb--w40" />
            </div>
        ))}
    </div>
);
