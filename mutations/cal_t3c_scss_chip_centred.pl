# Let the chip hang from the middle of the row instead of being centred on it.
s/(    \.dim-cal__trailnoend \{\n(?:        [^\n]*\n){4})        margin-top: -\.625rem;\n/$1/;
