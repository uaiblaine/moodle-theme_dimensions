# Put the line's icon buttons back at 6px corners, under the button's 8px.
s/(\n    \.dim-line__iconbtn \{\n(?:        [^\n]*\n)*?        border-radius: )var\(--dim-radius-md\);/${1}var(--dim-radius-sm);/;
