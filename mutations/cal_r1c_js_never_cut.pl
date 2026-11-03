# The empty day's band is centred even when the box is shorter than it: its start is cut.
s/return Math\.max\(0, Math\.min\(centred, first\)\);/return Math.max(0, centred);/;
