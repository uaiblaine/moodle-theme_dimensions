# Let a day's $ match before a final newline, which the week's snap then cannot parse.
s/\$\/D', \$day, \$parts/\$\/', \$day, \$parts/;
