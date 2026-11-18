# Hover a drawer row on the inset ground again, where the family's hover is meant.
s/(\.dimensions-drawer-item \{.*?&:hover \{\s*)background-color: var\(--dim-drawer-hover\);/${1}background-color: var(--dim-drawer-inset);/s;
