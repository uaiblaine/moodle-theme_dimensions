# Write an end's day in the page's surface, an ink the dark mode does not pair with the fill.
s/(\.dim-daterange__day--end \{\n        border-radius: var\(--dim-radius-sm\);\n        background: var\(--dim-cta\);\n        color: )var\(--dim-cta-ink\);/${1}var(--dim-surface);/;
