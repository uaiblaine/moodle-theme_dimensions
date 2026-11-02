# Name the address's month in the week view, not the open day's or the week's.
s/return \[self::VIEW_WEEK, substr\(\$day !== '' \? \$day : \$week, 0, 7\), \$week, \$day, \$from, \$to\];/return [self::VIEW_WEEK, \$month, \$week, \$day, \$from, \$to];/;
