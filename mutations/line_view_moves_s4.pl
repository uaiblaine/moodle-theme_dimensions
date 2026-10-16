# Keep the view toggle on the two-line step's first line: the line overflows and the toggle is twice on the page.
s/(> \.dim-line__view \{\n            display: )none;/${1}flex;/;
