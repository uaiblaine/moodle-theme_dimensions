# Rank by count alone: a filter that merely contains the text outranks one whose word starts with it.
s/\.sort\(\(a, b\) => a\.rank - b\.rank \|\| /.sort((a, b) => /;
