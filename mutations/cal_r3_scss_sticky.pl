# Let the trail column and the last column scroll away with the days.
s/(\.dim-cal__traillabel,\n    \.dim-cal__trailspill \{\n        )position: sticky;/$1position: relative;/;
