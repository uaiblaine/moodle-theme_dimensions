# Give an open disclosure's summary the old quiet tint instead of the brand tone's ground: the pressed face no
# longer one tone.
s/(\n    \.dim-line__organise\[open\] > \.dim-line__iconbtn,\n    \.dim-line__viewmenu\[open\] > \.dim-line__iconbtn \{\n        )background: var\(--dim-brand-tint\);/${1}background: var(--dim-tint);/;
