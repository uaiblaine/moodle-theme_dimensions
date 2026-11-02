# Begin every week on Sunday, whatever the viewer's first weekday.
s/return shift\(iso, -\(\(utc\(parts\)\.getUTCDay\(\) - firstday \+ WEEK\) % WEEK\)\);/return shift(iso, -utc(parts).getUTCDay());/;
