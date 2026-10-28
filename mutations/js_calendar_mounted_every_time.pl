# Hear the calendar's mounted event once: a calendar mounted again with a moved line keeps the period it was
# appended with, and the native fields' state is never told again.
s/^(        tellCalendar\(region, state\);\n    \})\);/$1, {once: true});/m;
