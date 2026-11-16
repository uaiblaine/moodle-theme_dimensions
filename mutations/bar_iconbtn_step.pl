# Size the icon button with a literal: no taller under a coarse pointer.
s/(\n    \.dim-line__iconbtn \{\n(?:        [^\n]*\n)*?        )height: var\(--dim-bar-inner\);/${1}height: 40px;/;
