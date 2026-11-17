# Leave the hero's ring on the row: its white ink, invisible on the capsule's surface.
s/(\.dim-hero \.dim-navpills :focus-visible \{\s*)outline-color: var\(--dimensions-focus-ring, var\(--bs-emphasis-color, #000\)\);/${1}outline-color: inherit;/;
