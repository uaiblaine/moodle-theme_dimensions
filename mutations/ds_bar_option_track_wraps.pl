# Keep a group's options on one line: past the panel's width they run out of the box and widen the page.
s/(\n    \.dim-toolbar__pills \{\n        display: flex;\n        flex-wrap: )wrap;/${1}nowrap;/;
