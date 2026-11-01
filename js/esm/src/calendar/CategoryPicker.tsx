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
 * The category picker: a tree of the categories the viewer can see, with a search and "include subcategories".
 *
 * The tree is local\calendar\tree's, flattened in display order: a treeitem per category with aria-level,
 * aria-setsize and aria-posinset, aria-expanded on a category with children and aria-selected on the one in force;
 * "All my categories" heads it. One item is in the Tab order (roving tabindex); ArrowUp and ArrowDown move between
 * the visible items, ArrowRight opens a closed category or moves to its first child, ArrowLeft shuts an open one or
 * moves to its parent, Home and End go to the first and the last, Enter and Space choose. A search shows the
 * categories whose name holds it, accents and case aside, with their ancestors open. Each category's swatch is its
 * branding pair, ground and ink together.
 *
 * @module     theme_dimensions/calendar/CategoryPicker
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useLayoutEffect, useMemo, useRef, useState} from 'react';
import type {CSSProperties, KeyboardEvent} from 'react';
import {hex} from './model';
import type {TreeNode} from './model';
import {usePopover} from './popover';
import {fill} from './strings';
import type {Strings} from './strings';

/** What the picker is handed. */
type CategoryPickerProps = {
    strings: Strings,
    tree: TreeNode[],
    categoryid: number,
    recursive: boolean,
    onChange: (change: {categoryid?: number, recursive?: boolean}) => void,
};

/** The id of "All my categories". */
const ALL = 0;

/**
 * A name as a search compares it: no accents, no case.
 *
 * @param {string} text The text.
 * @returns {string}
 */
export const folded = (text: string): string => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * The ids of a node's ancestors, nearest first.
 *
 * @param {Map} byid The tree by id.
 * @param {number} id The node.
 * @returns {Array} The ancestors' ids.
 */
const ancestors = (byid: Map<number, TreeNode>, id: number): number[] => {
    const found: number[] = [];
    let node = byid.get(id);
    while (node && node.parent !== 0 && byid.has(node.parent)) {
        found.push(node.parent);
        node = byid.get(node.parent);
    }
    return found;
};

/**
 * The nodes on show: under a search, every match and its ancestors; otherwise every node whose ancestors are open.
 *
 * @param {Array} tree The tree in display order.
 * @param {Set} expanded The open categories.
 * @param {string} search The search, folded.
 * @returns {Array} The nodes, in display order.
 */
export const visibleNodes = (tree: TreeNode[], expanded: Set<number>, search: string): TreeNode[] => {
    const byid = new Map(tree.map((node) => [node.id, node]));
    if (search !== '') {
        const keep = new Set<number>();
        tree.forEach((node) => {
            if (folded(node.name).includes(search)) {
                keep.add(node.id);
                ancestors(byid, node.id).forEach((id) => keep.add(id));
            }
        });
        return tree.filter((node) => keep.has(node.id));
    }
    return tree.filter((node) => ancestors(byid, node.id).every((id) => expanded.has(id)));
};

/**
 * The item ArrowDown, ArrowUp, Home or End moves to among the visible ones, or null for another key.
 *
 * @param {string} key The key.
 * @param {Array} ids The visible items' ids, in order.
 * @param {number} index Where the active item stands.
 * @returns {number|null}
 */
export const verticalTarget = (key: string, ids: number[], index: number): number | null => {
    switch (key) {
        case 'ArrowDown':
            return ids[Math.min(ids.length - 1, index + 1)];
        case 'ArrowUp':
            return ids[Math.max(0, index - 1)];
        case 'Home':
            return ids[0];
        case 'End':
            return ids[ids.length - 1];
        default:
            return null;
    }
};

/** What a row of the tree is handed. */
type TreeRowProps = {
    node: TreeNode,
    strings: Strings,
    siblings: TreeNode[],
    haschildren: boolean,
    open: boolean,
    selected: boolean,
    active: boolean,
    onChoose: (id: number) => void,
    onOpen: (id: number, open: boolean) => void,
};

/**
 * One category of the tree: its toggle, its branding swatch, its name and its count of the viewer's courses.
 *
 * @param {object} props See TreeRowProps.
 * @returns {object} The treeitem.
 */
const TreeRow = ({node, strings, siblings, haschildren, open, selected, active, onChoose, onOpen}: TreeRowProps) => {
    const bg = hex(node.bg);
    const ink = hex(node.ink);
    const swatch = (bg && ink ? {'--dim-cal-tint': bg, '--dim-cal-ink': ink} : {}) as CSSProperties;
    return (
        <li
            className="dim-cal__treerow"
            role="treeitem"
            aria-level={node.depth + 2}
            aria-setsize={siblings.length}
            aria-posinset={siblings.indexOf(node) + 1}
            aria-selected={selected}
            aria-expanded={haschildren ? open : undefined}
            tabIndex={active ? 0 : -1}
            data-id={node.id}
            style={{paddingInlineStart: `${0.5 + node.depth * 1.25}rem`}}
            onClick={() => onChoose(node.id)}
        >
            <span
                className="dim-cal__treetoggle"
                aria-hidden="true"
                onClick={(event) => {
                    if (haschildren) {
                        event.stopPropagation();
                        onOpen(node.id, !open);
                    }
                }}
            >
                {haschildren ? <i className={`fa fa-chevron-${open ? 'down' : 'right'}`}></i> : null}
            </span>
            <span className="dim-cal__treeswatch" style={swatch}></span>
            <span>{node.name}</span>
            {node.courses ? (
                <span className="dim-cal__treecount">{fill(strings.calendar_categorycourses, node.courses)}</span>
            ) : null}
        </li>
    );
};

/**
 * The picker.
 *
 * @param {object} props See CategoryPickerProps.
 * @returns {object} The button and its panel.
 */
const CategoryPicker = ({strings, tree, categoryid, recursive, onChange}: CategoryPickerProps) => {
    const popover = usePopover();
    const byid = useMemo(() => new Map(tree.map((node) => [node.id, node])), [tree]);
    const parents = useMemo(() => new Set(tree.map((node) => node.parent)), [tree]);
    const [expanded, setExpanded] = useState<Set<number>>(() => new Set(ancestors(byid, categoryid)));
    const [search, setSearch] = useState<string>('');
    const [active, setActive] = useState<number>(categoryid);
    const [focusing, setFocusing] = useState<number>(0);
    const list = useRef<HTMLUListElement | null>(null);

    const shown = visibleNodes(tree, expanded, folded(search.trim()));
    const ids = [ALL, ...shown.map((node) => node.id)];
    const current = ids.includes(active) ? active : ids[0];
    const selected = byid.get(categoryid);

    useLayoutEffect(() => {
        if (focusing) {
            list.current?.querySelector<HTMLElement>(`[data-id="${current}"]`)?.focus();
        }
    }, [focusing]);

    /**
     * Move the active item and its focus.
     *
     * @param {number} id The item.
     * @returns {void}
     */
    const moveTo = (id: number): void => {
        setActive(id);
        setFocusing((tick) => tick + 1);
    };

    /**
     * Open or shut a category.
     *
     * @param {number} id The category.
     * @param {boolean} open Whether it opens.
     * @returns {void}
     */
    const setOpen = (id: number, open: boolean): void => {
        setExpanded((was) => {
            const next = new Set(was);
            if (open) {
                next.add(id);
            } else {
                next.delete(id);
            }
            return next;
        });
    };

    /**
     * Choose a category, or all of them.
     *
     * @param {number} id The category, ALL for none.
     * @returns {void}
     */
    const choose = (id: number): void => {
        setActive(id);
        if (id !== categoryid) {
            onChange({categoryid: id});
        }
    };

    /**
     * The tree's keyboard (see the module's docblock).
     *
     * @param {object} event The key press.
     * @returns {void}
     */
    const onTreeKey = (event: KeyboardEvent): void => {
        const index = ids.indexOf(current);
        const node = byid.get(current);
        const searching = search.trim() !== '';
        const target = verticalTarget(event.key, ids, index);
        /**
         * ArrowRight: open a closed category, or move to the first child of an open one.
         *
         * @returns {void}
         */
        const right = (): void => {
            if (!node || !parents.has(node.id)) {
                return;
            }
            if (!expanded.has(node.id) && !searching) {
                setOpen(node.id, true);
            } else if (byid.get(ids[index + 1])?.parent === node.id) {
                moveTo(ids[index + 1]);
            }
        };
        /**
         * ArrowLeft: shut an open category, or move to the parent (the top for a category at the first level).
         *
         * @returns {void}
         */
        const left = (): void => {
            if (!node) {
                return;
            }
            if (parents.has(node.id) && expanded.has(node.id) && !searching) {
                setOpen(node.id, false);
            } else {
                moveTo(byid.has(node.parent) ? node.parent : ALL);
            }
        };
        if (target !== null) {
            moveTo(target);
        } else if (event.key === 'ArrowRight') {
            right();
        } else if (event.key === 'ArrowLeft') {
            left();
        } else if (event.key === 'Enter' || event.key === ' ') {
            choose(current);
        } else {
            return;
        }
        event.preventDefault();
    };

    const label = selected ? selected.name : strings.calendar_categoryall;
    const panelid = 'dim-cal-categorypanel';

    return (
        <div className="dim-cal__picker" data-region="dim-cal-category">
            <button
                ref={popover.button}
                type="button"
                className="btn btn-secondary dim-cal__pickerbtn"
                aria-expanded={popover.open}
                aria-controls={panelid}
                onClick={popover.toggle}
            >
                <i className="fa fa-folder-open" aria-hidden="true"></i>
                <span className="visually-hidden">{strings.calendar_category}: </span>
                <span>{label}</span>
                {selected && recursive ? <span className="dim-cal__pickernote">· {strings.calendar_withsub}</span> : null}
                <i className="fa fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div
                ref={popover.panel}
                className="dim-cal__popover"
                id={panelid}
                role="group"
                aria-label={strings.calendar_category}
                hidden={!popover.open}
                onKeyDown={popover.onKeyDown}
            >
                <label className="dim-cal__field">
                    <span>{strings.calendar_categorysearch}</span>
                    <input
                        type="search"
                        className="form-control"
                        value={search}
                        placeholder={strings.calendar_categorysearchplaceholder}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </label>
                <ul
                    ref={list}
                    className="dim-cal__tree"
                    role="tree"
                    aria-label={strings.calendar_categorytree}
                    onKeyDown={onTreeKey}
                >
                    <li
                        className="dim-cal__treerow"
                        role="treeitem"
                        aria-level={1}
                        aria-selected={categoryid === ALL}
                        tabIndex={current === ALL ? 0 : -1}
                        data-id={ALL}
                        onClick={() => choose(ALL)}
                    >
                        <span className="dim-cal__treetoggle" aria-hidden="true"></span>
                        {strings.calendar_categoryall}
                    </li>
                    {shown.map((node) => (
                        <TreeRow
                            key={node.id}
                            node={node}
                            strings={strings}
                            siblings={shown.filter((other) => other.parent === node.parent)}
                            haschildren={parents.has(node.id)}
                            open={expanded.has(node.id) || search.trim() !== ''}
                            selected={node.id === categoryid}
                            active={current === node.id}
                            onChoose={choose}
                            onOpen={setOpen}
                        />
                    ))}
                </ul>
                {!shown.length && search.trim() !== '' ? <p className="dim-cal__status">{strings.calendar_categorynone}</p> : null}
                <label className="dim-cal__check">
                    <input
                        type="checkbox"
                        checked={recursive}
                        onChange={(event) => onChange({recursive: event.target.checked})}
                    />
                    {strings.calendar_recursive}
                </label>
            </div>
        </div>
    );
};

export default CategoryPicker;
