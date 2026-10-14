# Let an area that a criterion zeroes stop opening its panel. The visitor's client keeps every
# poster a link to its panel and converts none, on the strength of this rule. Both sides escape the
# dollar: perl interpolates a variable in the replacement too, which would write a parse error.
s/return \$filtered \|\| \$count > 0;/return \$count > 0;/;
