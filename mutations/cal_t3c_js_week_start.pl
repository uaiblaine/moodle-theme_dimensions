# Start every week on a Monday, whatever the viewer's first weekday.
s/weekdayOf\(iso\) === firstday\)/weekdayOf(iso) === 1)/;
