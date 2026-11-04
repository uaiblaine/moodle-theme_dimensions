# Read an open period as one that ended before every month.
s/\(trail\.last <= 0 \|\| zone\.day\(trail\.last\) >= from\)/(zone.day(trail.last) >= from)/;
