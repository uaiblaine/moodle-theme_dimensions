# A separate-groups grader in two groups reads both, whatever group is chosen.
s/return in_array\(\$chosen, \$own, true\) \? \[\$chosen\] : \[\];/return \$own;/;
