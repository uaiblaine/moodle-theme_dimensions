# Hover a profile layer row on the inset ground again, where the family's hover is meant.
s/(\.dimensions-layer-row \{.*?&:hover \{\s*)background-color: var\(--dim-layer-hover\);/${1}background-color: var(--dim-layer-surface-inset);/s;
