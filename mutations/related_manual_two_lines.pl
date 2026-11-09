# The step would append the manual line to a select that already has a third line.
s/(function add_related_manual_option.*?)count\(array_filter\(\$lines, \x27strlen\x27\)\) !== 2/$1count(array_filter(\$lines, \x27strlen\x27)) < 2/s;
