# A day view's day= opens the drawer as well.
s/\(span\.view === 'day' \? null : dayIn\(day, days\)\);/dayIn(day, days);/;
