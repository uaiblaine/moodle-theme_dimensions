# Cut the open layer from the latest start instead of the earliest: still the cap's worth of courses, the wrong ones.
s/ORDER BY c\.startdate, c\.id",(\n\s+\$params \+ \['contextcourse')/ORDER BY c.startdate DESC, c.id DESC",$1/;
