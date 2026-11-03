# Leave the drawer at the top of the frame, out of view under a long month.
s/(    \.dim-cal__drawer--drawer \{\n(?:        --[^\n]*\n\n)?)        position: sticky;/$1        position: static;/;
