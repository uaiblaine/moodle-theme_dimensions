# End an open trail's line at its first step's 30 days, not where the latest of its steps ends.
s/trail\.first > 0 && trail\.last <= 0 \? openTrailEnd\(trail, zone\) : null/null/;
