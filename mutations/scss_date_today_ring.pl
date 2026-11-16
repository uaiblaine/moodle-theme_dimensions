# Ring today in the card's line instead of the brand tone's edge.
s/(\.dim-daterange__day\[aria-current="date"\] \{\n        border-radius: var\(--dim-radius-sm\);\n        box-shadow: inset 0 0 0 1px )var\(--dim-brand-edge\);/${1}var(--dim-line);/;
