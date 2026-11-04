# The trails' window is one month whatever count it is asked for.
s/return window::whole_months\(\$month, \$months\);/return window::whole_months(\$month, 1);/;
