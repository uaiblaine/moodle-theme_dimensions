# An account is shown every referenced course, hidden and unlisted ones included.
s/\$admitted = population::admitted\(population::scope\(new criteria\(0\)\), \$rows\);/\$admitted = \$rows;/;
