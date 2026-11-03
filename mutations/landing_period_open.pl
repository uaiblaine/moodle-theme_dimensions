# An earlier step's end closes a trail whose later step never ends.
s/\$open = \$open \|\| \$step->end <= 0;/\$open = \$open;/;
