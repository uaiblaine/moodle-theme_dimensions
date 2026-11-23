# Paint the elevation with a literal shadow: the popover leaves the elevation scale and the dark mode's black shadow.
s/box-shadow: var\(--dimensions-elevation-md\);(\s*
\s*
\s*\.popover-body)/box-shadow: 0 4px 12px rgba(0, 0, 0, .1);${1}/;
