# Size the scope's cells with a literal off the bar's step: the platter no longer the bar's height, and no taller
# under a coarse pointer.
s/(\n    \.dim-line__scope \.dim-pill \{\n        min-height: )var\(--dim-bar-inner\);/${1}34px;/;
