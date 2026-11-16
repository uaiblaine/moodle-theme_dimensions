# Leave the field open at rest on a line the client drew: the search is no longer its button.
s/(\.dim-line\[data-line-drawn\] > \.dim-line__searchcap \{\n        > \.dim-line__searchopen \{\n            display: inline-flex;\n        \}\n\n        > \.dim-line__search \{\n            display: )none;/${1}inline-flex;/;
