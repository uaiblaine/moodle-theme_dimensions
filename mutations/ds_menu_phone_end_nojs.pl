# The same where no client measures the line.
s/(\.dim-line:not\(\[data-line-drawn\]\) \.dim-line__viewmenu \{\n\s*position: )static;/${1}relative;/;
