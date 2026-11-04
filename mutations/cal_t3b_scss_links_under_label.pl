# Draw the connectors over the trail column, across the courses' names.
s/(    \.dim-cal__traillinks \{\n(?:        [^\n]*\n)*?        )z-index: 2;/$1z-index: 4;/;
