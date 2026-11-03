# A period that starts after the month is read as meeting it.
s/\(\$first === 0 \|\| \$first <= \$window->tend\)/(true)/;
