# Read the scroller at the day zoom's width whatever the zoom.
s/const pitch = days > 0 \? total \/ \(days \+ TRACK\.label \/ day\) : 0;/const pitch = days > 0 ? total \/ (days + TRACK.label \/ TRACK.day) : 0;/;
