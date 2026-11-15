# Keep the chips row on one line: the chips past its end run off the page.
s/(\n    \.dim-line__chips \{\n        display: flex;\n        flex-wrap: )wrap;/${1}nowrap;/;
