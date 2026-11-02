# Take a week of any year (security read F2).
s/const valid = parse\(week\) !== null && year >= 1900 && year <= 2999;/const valid = parse(week) !== null \&\& year >= 0;/;
