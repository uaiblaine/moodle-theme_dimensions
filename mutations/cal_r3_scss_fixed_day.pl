# Let the days stretch, so the client's day width is not the drawn one.
s/repeat\(var\(--dim-cal-trail-days, 31\), var\(--dim-cal-trail-day\)\)/repeat(var(--dim-cal-trail-days, 31), minmax(var(--dim-cal-trail-day), 1fr))/;
