# The family tokens (`--dimensions-*`)

## Purpose

`theme_dimensions` and its two sibling plugins, `local_dimensions` and `block_dimensions`, paint the
same competency and course surfaces. Each plugin carries its own colour tokens, built from
Bootstrap's `--bs-*` properties, so that it looks right under any theme. Under this theme the three
should look like one product, so the theme declares one set of family tokens and the plugins read
them first.

The theme is the provider and the plugins are readers: the theme never reads a plugin's token, and a
plugin never declares a `--dimensions-*` name. The names and their meanings below are the contract.
The values are the theme's, in `scss/post.scss` (section "Family tokens"), and may change with the
theme's design without notice.

## How a plugin reads a token

Wrap the plugin's own chain, unchanged, in the family token:

```css
body {
    --local-dimensions-surface: var(--dimensions-surface, var(--bs-body-bg, var(--white, #fff)));
}
```

Without the theme the outer `var()` falls through to the plugin's own chain, so the plugin looks
exactly as it does today on any other theme. Under the theme it takes the theme's value.

**Wrap the dark redeclarations as well.** A plugin that redeclares a token for the dark mode does so
under a more specific selector (for example `body[data-bs-theme="dark"]`), and that declaration wins
over the plugin's light one whatever it holds. Written with a bare value, it replaces the theme's
dark value with the plugin's own:

```css
/* Wrong: under the theme the plugin's literal still wins in the dark mode. */
body[data-bs-theme="dark"] {
    --local-dimensions-shadow: rgb(0 0 0 / 55%);
}

/* Right. */
body[data-bs-theme="dark"] {
    --local-dimensions-shadow: var(--dimensions-shadow, rgb(0 0 0 / 55%));
}
```

## Where the theme declares them

On three roots, light values first: `:root`, `body` and `.modal` (the dialogue `core/modal` appends
to `body`). The dark values follow under `:root[data-bs-theme="dark"]` and the same three roots:
Moodle 5.3 writes its colour mode on the `html` element, and Boost Union also writes the attribute on
its navbar, which must not switch the tokens of what sits inside it. Every name is declared once in
each block. A token chained to a `--bs-*` property is computed on the root that declares it, which is
why the roots are listed rather than left to inheritance.

Never declare or read a `--mds-*` name: that is core's design-system namespace (Moodle 5.2 and later
ship `$mds-*` tokens), and a name there could collide with core.

## Shared tokens (33)

Both plugins read these, from the byte-identical token block they share.

| Token | Meaning |
|---|---|
| `--dimensions-surface` | The ground of a card or a panel. |
| `--dimensions-surface-alt` | A second, quieter ground: a band, a header, a zebra row. |
| `--dimensions-surface-inset` | A platter a control sits in; not a ground for coloured ink. |
| `--dimensions-line` | A card's edge and a divider. |
| `--dimensions-ink` | Body text. |
| `--dimensions-ink-strong` | Headings and emphasised text. |
| `--dimensions-ink-muted` | Secondary text: labels, metadata. |
| `--dimensions-ink-faint` | Inactive-control text and placeholders only; never a border, fill or outline. |
| `--dimensions-accent` | A link and an interactive text. |
| `--dimensions-accent-hover` | The same, hovered. |
| `--dimensions-brand-fill` | The solid brand fill, only under `--dimensions-on-brand-fill`; never an ink or a ring. |
| `--dimensions-brand-ink` | The brand as text, readable in both modes. |
| `--dimensions-brand-tint` | A light ground of the brand hue. |
| `--dimensions-brand-edge` | The edge of a brand-tinted element. |
| `--dimensions-on-brand-fill` | The only text that may sit on the brand fill. |
| `--dimensions-focus-ring` | The colour of every focus ring. |
| `--dimensions-success-tint` | The ground of a success message or pill. |
| `--dimensions-success-edge` | Its edge. |
| `--dimensions-success-ink` | Its text. |
| `--dimensions-warning-tint` | The ground of a warning message or pill. |
| `--dimensions-warning-edge` | Its edge. |
| `--dimensions-warning-ink` | Its text. |
| `--dimensions-danger-tint` | The ground of an error message or pill. |
| `--dimensions-danger-edge` | Its edge. |
| `--dimensions-danger-ink` | Its text. |
| `--dimensions-info-tint` | The ground of an information message or pill. |
| `--dimensions-info-edge` | Its edge. |
| `--dimensions-info-ink` | Its text. |
| `--dimensions-neutral-tint` | The ground of a neutral pill. |
| `--dimensions-neutral-edge` | Its edge. |
| `--dimensions-neutral-ink` | Its text. |
| `--dimensions-shadow` | The colour of a shadow (a colour, not a whole `box-shadow`). |
| `--dimensions-scrim` | A translucent veil over content, such as a loading cover. |

## Local-only tokens (21)

Only `local_dimensions` reads these.

| Token | Meaning |
|---|---|
| `--dimensions-motion-fast` | The duration of a small transition. |
| `--dimensions-motion-base` | The duration of a larger transition. |
| `--dimensions-motion-ease` | The easing curve of every transition. |
| `--dimensions-state-enrolled-bg` | The ground of the "enrolled" state pill. |
| `--dimensions-state-enrolled-edge` | Its edge. |
| `--dimensions-state-enrolled-ink` | Its text. |
| `--dimensions-state-guest-bg` | The ground of the free and guest-access state pill. |
| `--dimensions-state-guest-edge` | Its edge. |
| `--dimensions-state-guest-ink` | Its text. |
| `--dimensions-state-neutral-bg` | The ground of the suspended, expired and "nothing on offer" state pill. |
| `--dimensions-state-neutral-edge` | Its edge. |
| `--dimensions-state-neutral-ink` | Its text. |
| `--dimensions-state-open-bg` | The ground of the "open for enrolment" state pill. |
| `--dimensions-state-open-edge` | Its edge. |
| `--dimensions-state-open-ink` | Its text. |
| `--dimensions-state-pending-bg` | The ground of the pending and waiting-list state pill. |
| `--dimensions-state-pending-edge` | Its edge. |
| `--dimensions-state-pending-ink` | Its text. |
| `--dimensions-state-scheduled-bg` | The ground of the scheduled and conditional state pill. |
| `--dimensions-state-scheduled-edge` | Its edge. |
| `--dimensions-state-scheduled-ink` | Its text. |

The state pills are the theme's own category showcase's: the same colour pairs, in both modes. Each
`-edge` is a one pixel line that shows against its pill's ground: darker than the ground on the light
page and lighter on the dark one, mixed from the pill's own ink and ground (the solid "enrolled" fill
takes the palette's next stop instead).

## Design system tokens (10)

The scales of the fleet Design System, read by every plugin of the family. The radius steps and the
ring's width are the same in both modes; the elevations are navy-tinted on the light page and black
on the dark one.

| Token | Meaning |
|---|---|
| `--dimensions-control-line` | The border of an outlined control and of a filter panel's option, darker than `--dimensions-line`. |
| `--dimensions-focus-width` | The width of the focus ring, 3px (a plugin's fallback is 2px). |
| `--dimensions-radius-xs` | 4px: menu items, the search field's eraser. |
| `--dimensions-radius-sm` | 6px: chips, panel options, status labels, tooltips. |
| `--dimensions-radius-md` | 8px: buttons, menus, the search field. |
| `--dimensions-radius-lg` | 12px: cards, panels, the filter bar's capsule. |
| `--dimensions-radius-xl` | 16px: the top of a sheet. |
| `--dimensions-elevation-sm` | The shadow of a resting card, the capsule, a chosen segment. |
| `--dimensions-elevation-md` | The shadow of a popover or a menu. |
| `--dimensions-elevation-lg` | The shadow of a dialogue or a sheet. |

## Card tokens (30)

The course card's tokens, read by every plugin that draws a course card (`block_compass` and `local_dimensions`
besides the theme's own cards), each through its own name: `--dim-card-*`, `--block_compass-card-*`,
`--local-dimensions-card-*`. The card's anatomy, states and the plugins' fallback chains are in
`moodle-dev/docs/course-card/CONTRACT.md`; the design record is `docs/course-card/mockup.html`. The theme declares
these names in its card round, on the same roots and in the same light and dark blocks as the tokens above.

| Name | Meaning |
|---|---|
| `--dimensions-card-radius` | The card's corner radius. |
| `--dimensions-card-border-width` | The width of its outline. |
| `--dimensions-card-border` | The colour of its outline. |
| `--dimensions-card-surface` | Its ground. |
| `--dimensions-card-ink` | Its title and body text. |
| `--dimensions-card-ink-muted` | Its secondary lines: category, meta. |
| `--dimensions-card-title-hover` | The title's colour on hover and focus. |
| `--dimensions-card-cover-height` | The cover's height. |
| `--dimensions-card-cover-fallback` | The ground of a cover with no image from the theme's renderer. |
| `--dimensions-card-cover-overlay` | The gradient laid over a coloured cover. |
| `--dimensions-card-chip-bg` | The ground of the kind pill on the cover. |
| `--dimensions-card-chip-ink` | Its text. |
| `--dimensions-card-extra-bg` | The ground of the plugin slot and of the activities drawer. |
| `--dimensions-card-extra-icon` | The plugin slot's icon. |
| `--dimensions-card-track` | The progress bar's track. |
| `--dimensions-card-fill` | The progress bar's fill. |
| `--dimensions-card-track-height` | The progress bar's thickness. |
| `--dimensions-card-done` | The completed card's green base and edge. |
| `--dimensions-card-done-bg` | The ground of the "Concluído" seal. |
| `--dimensions-card-done-ink` | Its text. |
| `--dimensions-card-done-edge` | The thickness of the completed base. |
| `--dimensions-card-shadow` | The card's shadow at rest. |
| `--dimensions-card-shadow-hover` | Its shadow on hover and focus. |
| `--dimensions-card-focus-ring` | The focus ring of the card and its controls; read before `--dimensions-focus-ring`. |
| `--dimensions-card-focus-width` | The ring's width. |
| `--dimensions-card-locked-opacity` | The opacity of the cover of a card the viewer cannot open. |
| `--dimensions-card-close-bg` | The ground of the trail popover's close disc. |
| `--dimensions-card-close-bg-hover` | Its ground on hover. |
| `--dimensions-card-close-edge` | Its edge. |
| `--dimensions-card-transition` | The duration of the card's hover. |

## Not part of the contract

`--dimensions-navbar-band` (the 3px rule under the navbar, which a Boost Union flavour sets),
`--dimensions-pill-border` and `--dimensions-pill-hover` (the navbar's focus mode pill), and every
`--dim-ds-*` and `--dim-*` name are the theme's own and may change or go at any release. A plugin
reads only the names listed above.
