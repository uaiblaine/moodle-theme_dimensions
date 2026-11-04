# Keep the bars' margins by months, so a day of .25rem draws no bar at all.
s/(    \.dim-cal__trails--month \.dim-cal__trailbar \{\n        )margin-inline: 0;/$1margin-inline: 2px;/;
