# Let a month's $ match before a final newline, which the grid then cannot parse.
s/\$\/D', \$month, \$parts/\$\/', \$month, \$parts/;
