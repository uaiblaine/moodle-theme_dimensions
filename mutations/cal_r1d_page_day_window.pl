# The day view's window is the month the address names, not the day's.
s/\[\$from, \$to\] = self::grid\(substr\(\$day, 0, 7\), \$firstday\);/[\$from, \$to] = self::grid(\$month, \$firstday);/;
