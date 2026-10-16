# Take no step when none fits: a line too narrow for the last one keeps whatever step the loop left.
s/return index === TRIES\.length - 1 \|\| !overflows\(\);/return !overflows();/;
