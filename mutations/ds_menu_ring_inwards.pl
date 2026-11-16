# Draw a menu item's focus ring outside it again: it covers the neighbouring items.
s/(Inwards: the items touch each other.*?\n\s*&:focus-visible \{\n\s*outline: 3px solid var\(--dim-ring\);\n\s*outline-offset: )-2px;/${1}2px;/s;
