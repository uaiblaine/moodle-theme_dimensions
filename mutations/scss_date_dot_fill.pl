# Paint the dot in the brand tint instead of the brand fill.
s/(\.dim-line__dot \{\n(?:        [^\n]*\n)*?        background: )var\(--dim-cta\);/${1}var(--dim-brand-tint);/;
