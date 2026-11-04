# Give the chip its old 6px a side, a pixel too wide for the column.
s/(    \.dim-cal__trailchip \{\n        max-width: 100%;\n        height: 1\.5rem;\n)        padding: 0 \.25rem;/$1        padding: 0 .375rem;/;
