# Raise the whole month name instead of its first letter: a capital-letter label, which the ::first-letter exception must not cover.
s/(\.dim-cal__trailmonthname)::first-letter( \{\n        text-transform: uppercase;)/${1}${2}/;
